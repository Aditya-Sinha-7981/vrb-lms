# VRB LMS VPS Deployment & Troubleshooting Record

## Purpose

This document records the deployment and troubleshooting work performed
while moving the VRB LMS (Moodle 5.1.5+) onto the VPS.

It is intended as a recovery/reference document for future server setup,
migration, debugging, or handover work.

------------------------------------------------------------------------

# 1. Final Environment

## VPS

-   OS: Ubuntu 22.04 LTS
-   Web server: Nginx
-   PHP: 8.4.25
-   PHP binary used for Moodle CLI: `/www/server/php/84/bin/php`
-   PHP-FPM socket: `/tmp/php-cgi-84.sock`
-   Moodle document root: `/www/wwwroot/learning.vrbconsumer.com/public`
-   Moodle configuration:
    `/www/wwwroot/learning.vrbconsumer.com/config.php`
-   Moodle data directory: `/www/moodledata`
-   Moodle version: `5.1.5+ (Build: 20260714)`
-   Moodle internal version: `2025100605.05`
-   Database type: `mariadb`

## Intended production URL

``` text
https://learning.vrbconsumer.com
```

At the time of this troubleshooting session, public DNS/HTTPS was not
yet fully configured.

------------------------------------------------------------------------

# 2. Initial Problem

After deploying the Moodle files/database/configuration, the Moodle
environment check reported:

``` text
CRITICAL: Environment (core_environment)

ERROR | Environment (core_environment)
      | You must solve all the environmental problems (errors) found
      | above before proceeding to install this Moodle version!
```

The first debugging attempt produced:

``` text
===== unicode =====
status=FAIL
level=required
error_code=1

===== database =====
status=FAIL
level=required
error_code=1

===== php =====
status=FAIL
level=required
error_code=1

===== extensions =====
status=FAIL
level=required
error_code=1

===== settings =====
status=FAIL
level=required
error_code=1
```

This output was misleading because the custom diagnostic script was
passing the wrong version value into the environment functions.

------------------------------------------------------------------------

# 3. Moodle Environment Version Investigation

The Moodle installation reported:

``` text
CFG version: 2025100605.05
Release: 5.1.5+ (Build: 20260714)
```

The environment matrix contained entries such as:

``` xml
<MOODLE version="5.1" requires="4.2.3">
```

and the environment file contained versions from Moodle 1.5 through 5.2.

The important discovery was:

``` text
get_environment_for_version("5.1", ENV_SELECT_RELEASE)
=> FOUND
```

while:

``` text
get_environment_for_version("2025100605.05", ENV_SELECT_RELEASE)
=> NOT FOUND
```

This is normal.

## Why

`$CFG->version` is Moodle's internal build/version number:

``` text
2025100605.05
```

The environment compatibility matrix uses release versions:

``` text
5.1
5.0
4.5
...
```

Moodle's actual environment checker maps the release:

``` text
5.1.5+ (Build: 20260714)
```

to the appropriate environment matrix entry.

Therefore:

> The `get_environment_for_version($CFG->version, ...) => NOT FOUND`
> result was not itself the problem.

The real failing environment requirement had to be identified from
Moodle's actual environment-check result.

------------------------------------------------------------------------

# 4. Correct Environment Probe

A direct probe was created to expose the individual failed environment
checks:

``` php
define('CLI_SCRIPT', true);

require('/www/wwwroot/learning.vrbconsumer.com/config.php');
require_once($CFG->libdir . '/environmentlib.php');

[$ok, $res] = check_moodle_environment($CFG->release, ENV_SELECT_NEWER);
```

The result was:

``` text
overall: FAIL
dbtype=mariadb

php_extension optional  status=0 err=0 info=exif
php_setting   optional  status=0 err=0 info=opcache.enable
custom_check  optional  status=0 err=0 info=Composer vendor directory not found
custom_check  required  status=0 err=0 info=max_input_vars
              feedback=settingmaxinputvarsrequired
```

This isolated the actual required failure:

``` text
max_input_vars
```

The other reported items were optional and were not blocking the
environment check.

------------------------------------------------------------------------

# 5. PHP Configuration Problem

The PHP CLI initially reported:

``` text
max_input_vars=1000
```

The PHP CLI configuration was:

``` text
/www/server/php/84/etc/php-cli.ini
```

The important discovery was that there were effectively different PHP
configuration contexts.

The main PHP configuration contained:

``` ini
;max_input_vars = 5000
```

while the CLI configuration contained:

``` ini
;max_input_vars = 1000
```

The active CLI configuration was confirmed with:

``` bash
/www/server/php/84/bin/php --ini
```

which reported:

``` text
Loaded Configuration File: /www/server/php/84/etc/php-cli.ini
```

After correcting the active PHP configuration, the check showed:

``` text
max_input_vars=5000
```

The Moodle environment check then passed.

## Important lesson

Do not assume that editing `php.ini` automatically changes every PHP
execution context.

On this server there were separate configuration files/contexts,
including:

``` text
php.ini
php-cli.ini
PHP-FPM
```

For Moodle web requests, PHP-FPM is the relevant context.

For Moodle CLI commands such as:

``` bash
php admin/cli/checks.php
```

the CLI configuration is relevant.

Always verify the actual configuration with:

``` bash
/www/server/php/84/bin/php --ini
```

and:

``` bash
/www/server/php/84/bin/php -r 'echo ini_get("max_input_vars"), PHP_EOL;'
```

------------------------------------------------------------------------

# 6. Database Configuration

During investigation, the environment checker also showed that the
database type needed to be correctly represented as MariaDB.

The final configuration was verified as:

``` text
dbtype=mariadb
```

This matters because Moodle distinguishes MariaDB from a generic
MySQL/Mysqli configuration during its environment checks.

The final environment probe showed:

``` text
dbtype=mariadb
```

and after the configuration was corrected, the environment check passed.

------------------------------------------------------------------------

# 7. Moodledata / open_basedir Problem

After the Moodle CLI environment checks passed, the web server itself
returned:

``` text
HTTP/1.1 500 Internal Server Error
```

The Nginx site-specific error log revealed the actual problem:

``` text
PHP Warning:
realpath(): open_basedir restriction in effect.

File(/www/moodledata) is not within the allowed path(s):
(/www/wwwroot/learning.vrbconsumer.com/:/tmp/)
```

Moodle's configuration correctly pointed to:

``` php
$CFG->dataroot = '/www/moodledata';
```

The directory itself existed and had appropriate ownership:

``` text
drwxrws--- 12 www www /www/moodledata
```

So the problem was not that the directory was missing.

The problem was that PHP-FPM's `open_basedir` restriction did not permit
PHP to access it.

------------------------------------------------------------------------

# 8. Finding the Hidden open_basedir Configuration

Searching the obvious PHP configuration files did not reveal an active
`open_basedir` setting.

The search eventually found:

``` text
/www/wwwroot/learning.vrbconsumer.com/public/.user.ini
```

containing:

``` ini
open_basedir=/www/wwwroot/learning.vrbconsumer.com/:/tmp/
```

This was the actual source of the restriction.

The important detail is that `.user.ini` is a per-site PHP configuration
mechanism and can affect PHP-FPM requests even when `php.ini` itself
does not contain an active `open_basedir` directive.

------------------------------------------------------------------------

# 9. open_basedir Fix

The site's `.user.ini` was corrected so that Moodle's data directory was
allowed.

The resulting effective value was verified through a temporary PHP test:

``` text
open_basedir: /www/wwwroot/learning.vrbconsumer.com/:/www/moodledata/:/tmp/
moodledata: ACCESSIBLE
```

This fixed the HTTP 500 error.

## Important lesson

When a PHP web request reports an `open_basedir` error but:

``` bash
php -i | grep open_basedir
```

shows nothing useful, check:

``` text
.user.ini
```

inside the website root/document root.

Also remember that CLI PHP and PHP-FPM are separate execution contexts.

------------------------------------------------------------------------

# 10. Verifying the Moodle Web Request

Before the `open_basedir` fix, this request:

``` bash
curl -I -H "Host: learning.vrbconsumer.com" http://127.0.0.1
```

returned:

``` text
HTTP/1.1 500 Internal Server Error
```

After fixing `open_basedir`, the same virtual-host request returned:

``` text
HTTP/1.1 303 See Other
Server: nginx
X-Redirect-By: Moodle
Location: https://learning.vrbconsumer.com
```

This was a major milestone.

It proved that:

-   Nginx was routing the request to the correct virtual host.
-   PHP-FPM was processing Moodle.
-   Moodle itself was booting successfully.
-   Moodle was intentionally redirecting HTTP to HTTPS.

------------------------------------------------------------------------

# 11. Moodle CLI Verification

After fixing the environment requirements:

``` bash
/www/server/php/84/bin/php admin/cli/checks.php -v --filter=environment
```

returned:

``` text
OK: All 'status' checks OK
```

and:

``` text
Environment (core_environment)
Your server environment meets all minimum requirements.
```

Then:

``` bash
/www/server/php/84/bin/php admin/cli/upgrade.php --non-interactive
```

returned:

``` text
No upgrade needed for the installed version
5.1.5+ (Build: 20260714)
(2025100605.05).
```

This confirmed that the database/schema was already at the installed
Moodle version and no upgrade was required.

------------------------------------------------------------------------

# 12. Full Moodle Health Check

The final command:

``` bash
/www/server/php/84/bin/php admin/cli/checks.php -v
```

returned:

``` text
OK | Environment
OK | Upgrade
NA | Antivirus
WARNING | Cron running
OK | Tasks max fail delay
OK | Ad hoc task queue
OK | Long running tasks
```

The only warning was:

``` text
Cron running

The admin/cli/cron.php script has not been run for 36 mins
41 secs and should run every 1 min.
```

This does not mean Moodle is broken.

It means the Moodle scheduled task cron has not yet been
configured/running correctly.

------------------------------------------------------------------------

# 13. Cron

Moodle currently recommends the cron task run every minute.

Typical command:

``` bash
/www/server/php/84/bin/php /www/wwwroot/learning.vrbconsumer.com/admin/cli/cron.php
```

A cron entry can later be configured as:

``` cron
* * * * * /www/server/php/84/bin/php /www/wwwroot/learning.vrbconsumer.com/admin/cli/cron.php >/dev/null 2>&1
```

The one-minute interval is recommended for Moodle's task processing.

However:

> Cron was not the cause of the environment failure or the HTTP 500
> error.

It is a remaining deployment task.

------------------------------------------------------------------------

# 14. HTTPS / Port 443 Discovery

At one point the browser showed:

``` text
ERR_CONNECTION_REFUSED
```

The VPS was checked with:

``` bash
ss -lntp | grep -E ':80|:443'
```

The result was:

``` text
LISTEN 0 511 0.0.0.0:80
```

There was no listener on port 443.

UFW was checked:

``` bash
ufw status
```

and showed:

``` text
80/tcp   ALLOW
443/tcp  ALLOW
```

Therefore the firewall was not blocking HTTPS.

The actual situation was:

``` text
Port 80  -> Nginx listening
Port 443 -> nothing listening
```

Moodle was configured with:

``` php
$CFG->wwwroot = 'https://learning.vrbconsumer.com';
```

Therefore the request flow was:

``` text
Browser
  |
  | HTTP :80
  v
Nginx
  |
  v
Moodle
  |
  | HTTP -> HTTPS redirect
  v
HTTPS :443
  |
  X
No listener
```

This explained the browser's connection-refused error.

------------------------------------------------------------------------

# 15. Temporary Browser Testing

Because public DNS/HTTPS was not ready, temporary local testing was
performed.

The domain was temporarily mapped through `/etc/hosts` on the
development machine:

``` text
66.116.253.207 learning.vrbconsumer.com
```

This allowed the domain name to resolve to the VPS without waiting for
public DNS.

The initial attempt to access the site showed an aaPanel/Nginx:

``` text
Website not found
```

page because the browser was hitting the wrong local/virtual-host
context.

The correct domain mapping allowed the request to reach the VPS.

------------------------------------------------------------------------

# 16. Temporary HTTP Testing

For temporary testing, Moodle's `$CFG->wwwroot` was temporarily changed
to HTTP so the site could be viewed before HTTPS was configured.

The intended production configuration remains:

``` php
$CFG->wwwroot = 'https://learning.vrbconsumer.com';
```

Once testing was complete, the temporary HTTP change was reverted.

## Do not leave the temporary HTTP configuration in production.

------------------------------------------------------------------------

# 17. Temporary Test Files

Several temporary diagnostic scripts were created during
troubleshooting, including:

``` text
/tmp/envprobe.php
```

and:

``` text
public/test.php
```

These were removed after use.

The test scripts were used to inspect:

-   PHP SAPI
-   PHP version
-   `open_basedir`
-   Moodledata accessibility
-   environment-check output

No permanent application code depended on these scripts.

------------------------------------------------------------------------

# 18. Current Expected State

The Moodle installation should now have the following state:

``` text
Moodle files                  OK
Moodle database               OK
Moodle version                5.1.5+
Database type                 MariaDB
PHP                            8.4.25
max_input_vars                5000
Moodledata                    /www/moodledata
Moodledata access             OK
open_basedir                  Allows Moodledata
Environment check             PASS
Upgrade check                 PASS
Moodle web bootstrap          PASS
Nginx HTTP virtual host       PASS
UFW HTTP                      ALLOW
UFW HTTPS                     ALLOW
Cron                          NOT YET CONFIGURED / WARNING
HTTPS listener                NOT YET CONFIGURED
Public DNS                    NOT YET FULLY CONFIGURED
```

------------------------------------------------------------------------

# 19. Important Configuration Values

## Moodle config

File:

``` text
/www/wwwroot/learning.vrbconsumer.com/config.php
```

Important values:

``` php
$CFG->wwwroot = 'https://learning.vrbconsumer.com';
$CFG->dataroot = '/www/moodledata';
$CFG->dbtype = 'mariadb';
```

Do not casually change these values.

------------------------------------------------------------------------

# 20. Useful Verification Commands

## Check Moodle environment

``` bash
cd /www/wwwroot/learning.vrbconsumer.com

/www/server/php/84/bin/php admin/cli/checks.php -v --filter=environment
```

Expected:

``` text
OK: All 'status' checks OK
```

------------------------------------------------------------------------

## Check Moodle upgrade status

``` bash
/www/server/php/84/bin/php admin/cli/upgrade.php --non-interactive
```

Expected if already current:

``` text
No upgrade needed for the installed version...
```

------------------------------------------------------------------------

## Full Moodle health check

``` bash
/www/server/php/84/bin/php admin/cli/checks.php -v
```

A cron warning is acceptable until cron is configured.

------------------------------------------------------------------------

## Check PHP CLI configuration

``` bash
/www/server/php/84/bin/php --ini
```

Check a specific setting:

``` bash
/www/server/php/84/bin/php -r 'echo "max_input_vars=" . ini_get("max_input_vars") . PHP_EOL;'
```

Expected:

``` text
max_input_vars=5000
```

------------------------------------------------------------------------

## Check PHP-FPM open_basedir

Use a temporary web test if necessary:

``` php
<?php
echo ini_get('open_basedir');
```

The effective web value must permit:

``` text
/www/wwwroot/learning.vrbconsumer.com/
/www/moodledata/
/tmp/
```

------------------------------------------------------------------------

## Check Nginx listeners

``` bash
ss -lntp | grep -E ':80|:443'
```

------------------------------------------------------------------------

## Check UFW

``` bash
ufw status
```

Expected relevant rules:

``` text
80/tcp   ALLOW
443/tcp  ALLOW
```

------------------------------------------------------------------------

## Test the Moodle virtual host locally on the VPS

``` bash
curl -I -H "Host: learning.vrbconsumer.com" http://127.0.0.1
```

A redirect to the HTTPS URL is expected when Moodle's `wwwroot` uses
HTTPS.

------------------------------------------------------------------------

# 21. Troubleshooting Lessons

## Lesson 1: Don't trust the generic Moodle environment error

This:

``` text
You must solve all the environmental problems
```

does not tell you which requirement failed.

Use:

``` bash
admin/cli/checks.php -v --filter=environment
```

or inspect the individual environment results.

------------------------------------------------------------------------

## Lesson 2: `$CFG->version` is not the environment matrix version

Do not test:

``` php
get_environment_for_version($CFG->version, ...)
```

and assume `NOT FOUND` means Moodle is broken.

For this installation:

``` text
$CFG->version = 2025100605.05
```

while the compatibility matrix uses:

``` text
5.1
```

The normal Moodle environment-check flow handles this mapping.

------------------------------------------------------------------------

## Lesson 3: CLI PHP and web PHP are different contexts

The server had separate PHP configuration contexts.

For CLI:

``` text
/www/server/php/84/etc/php-cli.ini
```

For web requests:

``` text
PHP-FPM
```

A setting can therefore appear correct in one context and wrong in
another.

Always test the context that is actually failing.

------------------------------------------------------------------------

## Lesson 4: Check `.user.ini`

If PHP-FPM reports:

``` text
open_basedir restriction in effect
```

but searching `php.ini` finds nothing, check:

``` text
.user.ini
```

in the site's document root.

That was the source of the Moodledata restriction in this deployment.

------------------------------------------------------------------------

## Lesson 5: A firewall rule does not mean a service is listening

UFW showed:

``` text
443/tcp ALLOW
```

but:

``` text
ss -lntp
```

showed no process listening on `443`.

An allowed port with no listener still results in connection refusal.

------------------------------------------------------------------------

# 22. Remaining Production Tasks

The Moodle application itself is now functioning, but the server is not
completely production-ready.

Remaining tasks:

1.  Configure public DNS for: `learning.vrbconsumer.com`

2.  Configure Nginx HTTPS on port `443`.

3.  Install/issue a valid SSL certificate.

4.  Confirm:

    ``` text
    https://learning.vrbconsumer.com
    ```

    works publicly.

5.  Configure Moodle cron:

    ``` cron
    * * * * * /www/server/php/84/bin/php /www/wwwroot/learning.vrbconsumer.com/admin/cli/cron.php >/dev/null 2>&1
    ```

6.  Re-run:

    ``` bash
    /www/server/php/84/bin/php admin/cli/checks.php -v
    ```

    and confirm the cron warning disappears.

7.  Test:

    -   Admin login
    -   Student login
    -   Course access
    -   Quiz functionality
    -   Certificates
    -   Leaderboard
    -   Custom VRB plugins
    -   File uploads
    -   Moodle scheduled tasks

------------------------------------------------------------------------

# 23. Do Not Undo These Fixes

The following are real configuration fixes and should remain:

### Database

``` text
dbtype = mariadb
```

### PHP

``` text
max_input_vars = 5000
```

### Moodledata access

`open_basedir` must allow:

``` text
/www/moodledata/
```

### Moodle URL

Production configuration:

``` php
$CFG->wwwroot = 'https://learning.vrbconsumer.com';
```

------------------------------------------------------------------------

# 24. Short Incident Summary

The deployment initially appeared broken because Moodle reported a
generic environment failure.

The actual blocking issue was:

``` text
max_input_vars
```

The active PHP CLI configuration was using:

``` text
max_input_vars=1000
```

instead of the required value. It was corrected to:

``` text
max_input_vars=5000
```

After that, Moodle's environment check passed.

The web server then returned HTTP 500 because PHP-FPM's site-specific
`.user.ini` restricted `open_basedir` to the website directory and
`/tmp`, preventing Moodle from accessing:

``` text
/www/moodledata
```

The restriction was corrected to include `/www/moodledata/`.

After that, the web request successfully reached Moodle and Moodle
returned its expected HTTP-to-HTTPS redirect.

The next browser problem was not Moodle at all. Port 443 was allowed by
UFW but had no service listening. This caused:

``` text
ERR_CONNECTION_REFUSED
```

Temporary local DNS/HTTP testing was used to inspect the deployed Moodle
site.

The Moodle installation now passes its environment and upgrade checks.
The remaining infrastructure work is primarily HTTPS/DNS and cron
configuration.
