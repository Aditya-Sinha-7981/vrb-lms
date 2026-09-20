# VRB LMS — cPanel Production Deployment Runbook

Companion to `docs/DEPLOYMENT.md` (which is the policy / "what not to touch"
doc). This file is the **step-by-step**: what the production cPanel needs,
how the deploy actually works, and the exact commands.

> Status source: the Sep 8 2026 hosting investigation
> (`../deploying_project_to_production.md`). Anything marked
> **RE-VERIFY** below was last checked on Sep 8 and must be re-checked in
> the live cPanel before you rely on it.

---

## 0. The mental model — how this deploy works

This repo **is a complete, pinned Moodle 5.1.5 source tree** (not just
plugins). That changes the deployment shape vs. a "drop plugins onto a
Softaculous install" model:

```
GitHub (private repo, main)
        │  read-only SSH deploy key
        ▼
cPanel "Git Version Control"  ──►  bare-ish clone at ~/repos/vrb-lms
        │  .cpanel.yml runs on every pull to main
        ▼
DEPLOYPATH  =  ~/learning_vrbconsumer/            ← the Moodle dir (has config.php)
        └── public/                               ← subdomain DOCUMENT ROOT points HERE
config.php        ← lives in DEPLOYPATH root, authored on the server, NEVER in git
/home/<acct>/moodledata/   ← OUTSIDE document root, writable, NEVER in git
```

Key facts that drive every step below:

| Fact | Consequence |
|---|---|
| Moodle 5.1 uses the `public/` sub-tree as web root | cPanel subdomain **Document Root must be `…/learning_vrbconsumer/public`**, not the folder above it |
| `config.php` sits one level **above** `public/` (repo root), git-ignored | You hand-author it on the server once; deploys must never overwrite it |
| Core is pinned (`MOODLE_501_STABLE`, build 20260714) — do not upgrade | Deploy can safely rsync the whole tree; there is no "don't touch core" carve-out to engineer, because our repo *is* the core we want |
| `moodledata` is runtime state | Create it once, outside `public_html` and outside the repo; put its path in `config.php` |
| Custom code = `local_vrblms`, `local_vrbcert`, `local_vrbcontent`, `theme_vrblms` | These ride along in the same tree; a deploy is "update the tree, run upgrade.php, purge caches" |

> **Decision to confirm with project lead:** `docs/DEPLOYMENT.md` still
> describes a Softaculous-core + plugin-overlay model. That predates the
> repo containing the full 5.1.5 tree and predates confirming Softaculous
> can't offer 5.1.5. Recommended: **do not use Softaculous** — deploy this
> repo as the whole site. If the lead insists on Softaculous, the
> `.cpanel.yml` in §5 has to be narrowed to copy only `public/local/vrb*`
> and `public/theme/vrblms` and you inherit a core-version-skew risk.

---

## 1. Blockers — what must be true before deployment is even possible

Re-verify each in the live cPanel / terminal. Do not proceed past a ❌.

### 1.1 DNS — `learning.vrbconsumer.com` must resolve  ❌ RE-VERIFY (was NXDOMAIN)

DNS for `vrbconsumer.com` is on **GoDaddy**, not this cPanel, so cPanel's
Zone Editor is irrelevant.

**Check (cPanel Terminal):**
```bash
dig +short learning.vrbconsumer.com
dig +short vrbconsumer.com          # for comparison — the target IP
```
- Returns `192.250.235.88` → resolved, done.
- Returns nothing / `NXDOMAIN` → still missing.

**Fix (GoDaddy → vrbconsumer.com → DNS → Add record):**
| Field | Value |
|---|---|
| Type | `A` |
| Name / Host | `learning` |
| Value / Points to | `192.250.235.88` (this account's shared IP — re-confirm in cPanel → *Server Information* → Shared IP Address) |
| TTL | 1 Hour (default) |

Wait for propagation, re-run `dig`. **Ownership question:** confirm *you*
have GoDaddy access, or this waits on the client.

### 1.2 PHP 8.2+ for the subdomain  ❌ RE-VERIFY (was "Site Isolation denied by server administrator")

Moodle 5.1 **requires PHP 8.2.0** (`public/admin/environment.xml`,
`<PHP version="8.2.0" level="required">`). Main site runs 7.4 and must
stay there.

**Check:** cPanel → **MultiPHP Manager**. You need a per-domain row for
`learning.vrbconsumer.com` with a selectable 8.2/8.3 version, while
`vrbconsumer.com` stays 7.4.

- If every domain shows `7.4 (account default)` with greyed toggles and a
  "Site Isolation has been denied" banner → **still blocked, server-side,
  above your account.** Only the hosting provider can lift it.
- **Support-ticket wording:** "Please enable PHP Site Isolation
  (per-domain `MultiPHP` selection) for cPanel account `<acct>` so the
  subdomain `learning.vrbconsumer.com` can run PHP 8.2 independently of
  `vrbconsumer.com` on 7.4. Screenshot of the current denied banner
  attached."

Also confirm **CLI PHP** version — cPanel's shell `php` often defaults to
the account default, not the domain's. You'll likely need the explicit
path in cron and deploy scripts:
```bash
/opt/cpanel/ea-php82/root/usr/bin/php -v      # adjust ea-php82 → actual
```

### 1.3 MariaDB 10.11+  ❌ RE-VERIFY (server had 10.6.28)

Moodle 5.1 **requires MariaDB 10.11.0** (`environment.xml`, the
`<MOODLE version="5.1">` block). Below that, **Moodle's installer refuses
to run** — this is a hard stop, not a warning.

On shared cPanel the DB engine is almost always **one version for the
whole server**, so this is not a per-account toggle.

**Check (cPanel Terminal):**
```bash
mysql --version
# or, once you have a DB user:
mysql -u <dbuser> -p -e "SELECT VERSION();"
```

**Support-ticket wording:** "cPanel account `<acct>` needs to run Moodle
5.1, which enforces a minimum of **MariaDB 10.11.0** at install time. The
server currently reports 10.6.28. Questions: (a) Is MariaDB fixed
server-wide on this plan or upgradable? (b) Do you have a plan/server with
MariaDB 10.11+ we can move this account to? (c) Is a remote/managed
MariaDB 10.11+ instance an option (does this plan allow outbound DB
connections)?"

If none of those land, this project needs a different host. **Do not**
build the deploy pipeline until this is answered.

### 1.4 SSL for the subdomain  ⚠️ RE-VERIFY (screenshot showed "Off / Not Redirected")

Employee login over plain HTTP is unacceptable. After DNS resolves:
cPanel → **SSL/TLS Status** → tick `learning.vrbconsumer.com` → **Run
AutoSSL**. Then cPanel → **Domains** → toggle **Force HTTPS Redirect** on
for that subdomain. `config.php` `$CFG->wwwroot` must be the `https://`
URL.

### 1.5 Shell + Git tooling  ✅ RE-VERIFY (Terminal confirmed working Sep 8)

**Check (cPanel Terminal):**
```bash
git --version          # need this for Git Version Control
which rsync             # .cpanel.yml uses it
php -v                  # note the version; compare to 1.2
```
cPanel → **Git™ Version Control** icon present and able to "Create" a repo
at a throwaway path (`~/git-test`) → the deploy tool works.

### 1.6 PHP extensions & limits  ⬜ NOT YET CHECKED

Once 8.2 is selectable, cPanel → **MultiPHP INI Editor** / **Select PHP
Version → Extensions** for the subdomain. Moodle 5.1 needs, at minimum:

`ctype`, `curl`, `dom`, `fileinfo`, `gd`, `iconv`, `intl`, `json`,
`mbstring`, `openssl`, `pcre`, `simplexml`, `sodium`, `spl`, `tokenizer`,
`xml`, `xmlreader`, `zip`, `zlib`, `hash`, `pgsql|mysqli` (need
`mysqli`), `exif` (recommended), `opcache` (recommended, strongly).

INI settings (MultiPHP INI Editor for the subdomain):
| Directive | Minimum | Notes |
|---|---|---|
| `memory_limit` | `256M` | `512M` recommended for CLI upgrade/backup |
| `max_input_vars` | `5000` | Moodle warns hard below this |
| `post_max_size` | `100M` | match content upload needs |
| `upload_max_filesize` | `100M` | ≤ `post_max_size` |
| `max_execution_time` | `120` (web) | CLI runs unlimited |
| `file_uploads` | `On` | |
| `session.save_path` | writable | usually fine by default |

### 1.7 Disk, inodes, DB size  ⬜ NOT YET CHECKED

A full Moodle tree is **~50–60k files**. Shared plans often cap inodes
(commonly 250k–500k). cPanel home → **Statistics** → *File Usage
(inodes)* and *Disk Usage*. If the inode headroom is under ~100k, raise
it with the host now — you'll also need room for `moodledata` (grows with
uploads/backups) and the DB.

### 1.8 Cron capability  ⬜ NOT YET CHECKED

cPanel → **Cron Jobs** must be available. Moodle needs `cron.php` every
minute (see §4.6).

---

## 2. One-time production setup

Do this **on the agency staging cPanel first** if you want to rehearse the
pipeline (DEPLOYMENT.md §"Interim staging option") — it already has shell
access. Same steps, throwaway subdomain.

### 2.1 Create the subdomain with the correct document root

cPanel → **Domains** → **Create A Domain**:
- Domain: `learning.vrbconsumer.com`
- **Document Root: `/home/<acct>/learning_vrbconsumer/public`**
  — note the `/public` suffix, and note it is **outside `public_html`**
  (this was fixed Sep 8 to a sibling folder — keep it that way).

The parent `~/learning_vrbconsumer/` is where the repo tree (and
`config.php`) will live; only `public/` is served.

### 2.2 Create `moodledata` outside the web root

```bash
mkdir -p /home/<acct>/moodledata
chmod 750 /home/<acct>/moodledata
```
Never inside `learning_vrbconsumer/` or `public_html/`.

### 2.3 Create the database and user

cPanel → **MySQL® Databases**:
- Database: `<acct>_vrblms`
- User: `<acct>_vrblms` with a strong generated password (save it)
- Add user to DB with **ALL PRIVILEGES**

Confirm charset later: Moodle wants `utf8mb4` / `utf8mb4_unicode_ci`. On
MariaDB 10.11 defaults this is fine; `config.php` pins it anyway (§3).

### 2.4 Get the code onto the server

**Option A — cPanel Git Version Control (preferred, enables §5 pipeline):**
1. GitHub → repo → **Settings → Deploy keys → Add deploy key**. Generate
   on the server first:
   ```bash
   ssh-keygen -t ed25519 -C "cpanel-vrblms-deploy" -f ~/.ssh/vrblms_deploy -N ""
   cat ~/.ssh/vrblms_deploy.pub      # paste into GitHub, leave "Allow write" OFF
   ```
   Add to `~/.ssh/config`:
   ```
   Host github-vrblms
     HostName github.com
     User git
     IdentityFile ~/.ssh/vrblms_deploy
     IdentitiesOnly yes
   ```
2. cPanel → **Git Version Control** → **Create**:
   - Clone URL: `git@github-vrblms:Aditya-Sinha-7981/vrb-lms.git`
   - Repository Path: `/home/<acct>/repos/vrb-lms`  ← **staging clone, NOT the docroot parent**
   - Branch: `main`
3. This clone is the *source*. `.cpanel.yml` (§5) copies from it into
   `~/learning_vrbconsumer/`.

**Option B — manual first clone (if Git Version Control still blocked):**
```bash
cd /home/<acct>
git clone git@github-vrblms:Aditya-Sinha-7981/vrb-lms.git learning_vrbconsumer
```
Updates then have to be `git pull` by hand over SSH until the tool works.

Either way, after cloning verify the tree:
```bash
ls /home/<acct>/learning_vrbconsumer/public/local/    # vrblms vrbcert vrbcontent
ls /home/<acct>/learning_vrbconsumer/public/theme/    # vrblms present
```

### 2.5 Author `config.php` on the server (never from git)

Create `/home/<acct>/learning_vrbconsumer/config.php` from
`config-dist.php`. Minimum:

```php
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = '<acct>_vrblms';
$CFG->dbuser    = '<acct>_vrblms';
$CFG->dbpass    = '<the generated password>';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
    'dbpersist' => 0,
    'dbport'    => '',
    'dbsocket'  => '',
    'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'https://learning.vrbconsumer.com';
$CFG->dataroot  = '/home/<acct>/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 0755;

// REQUIRED by theme_vrblms or the site fatals on every page
// (see LOG.md 2026-08-20). theme_vrblms is a child of Moove.
// This line belongs in config.php per CLAUDE.md / ARCHITECTURE.md theming section:
// $THEME->rendererfactory is set in the theme's own config.php, but keep the
// theme selected in DB (§2.7) so it loads.

require_once(__DIR__ . '/public/lib/setup.php');
```

> The `theme_overridden_renderer_factory` requirement lives in
> `public/theme/vrblms/config.php`, not `config.php` — just don't remove
> it from the theme, and make sure `$CFG->theme` ends up `vrblms` (§2.7).

Lock it down:
```bash
chmod 600 /home/<acct>/learning_vrbconsumer/config.php
```

### 2.6 Install the Moodle database

**Choice: fresh install vs. migrate the local DB.**

- **Fresh install (clean, recommended for a real go-live):**
  ```bash
  cd /home/<acct>/learning_vrbconsumer/public
  <php82> admin/cli/install_database.php \
    --agree-license \
    --adminpass='<strong-pass>' \
    --adminemail='admin@vrbconsumer.com' \
    --fullname='VRB Learning' \
    --shortname='VRBLMS'
  ```
  Then rebuild the real content (cohorts, profile fields, brand
  categories, imported books/quizzes) on production — or re-run the
  import CSVs (`docs/CSV_IMPORT_SPEC.md`).

- **Migrate local → production (keeps all your test/real content):**
  `vrb_moodle_dump.sql` (repo root, git-ignored — move it via SFTP/File
  Manager) is a dump of the local DB.
  ```bash
  mysql -u <acct>_vrblms -p <acct>_vrblms < vrb_moodle_dump.sql
  ```
  Then **fix wwwroot references**:
  ```bash
  cd /home/<acct>/learning_vrbconsumer/public
  <php82> admin/cli/upgrade.php --non-interactive
  <php82> admin/tool/replace/cli/replace.php \
    --search='http://localhost:8080' --replace='https://learning.vrbconsumer.com' \
    --non-interactive --skiptables=config,config_plugins,log,logstore_standard_log
  <php82> admin/cli/purge_caches.php
  ```
  Review afterwards — `tool/replace` is a blunt instrument; check
  `$CFG->wwwroot`, then spot-check the leaderboard, a course, a quiz.

### 2.7 Register plugins + set theme

```bash
cd /home/<acct>/learning_vrbconsumer/public
<php82> admin/cli/upgrade.php --non-interactive          # picks up local_vrb*, theme_vrblms
<php82> admin/cli/cfg.php --name=theme --set=vrblms       # if not already from migration
<php82> admin/cli/purge_caches.php
```
Confirm all four plugins report installed:
```bash
<php82> admin/cli/cfg.php --component=core --name=allversionshash >/dev/null
<php82> -r 'define("CLI_SCRIPT",true); require("config.php"); \
  foreach (["local_vrblms","local_vrbcert","local_vrbcontent","theme_vrblms"] as $c) \
  echo $c." => ".get_config($c,"version")."\n";'
```

### 2.8 Permissions

```bash
cd /home/<acct>
chmod -R 755 learning_vrbconsumer/public
chmod 600 learning_vrbconsumer/config.php
chmod -R 750 moodledata
```
Do **not** make `moodledata` web-accessible.

### 2.9 SSL + force HTTPS

Per §1.4. Then verify `https://learning.vrbconsumer.com` loads the login
page with a valid padlock.

### 2.10 Cron

cPanel → **Cron Jobs** → every minute:
```
* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/<acct>/learning_vrbconsumer/public/admin/cli/cron.php >/dev/null 2>&1
```
Then Moodle → *Site admin → Server → Tasks → Scheduled tasks* should show
"last run" advancing.

---

## 3. `config.php` — full production template

See §2.5. Additional hardening lines to add once the site is up:

```php
$CFG->cronclionly = true;                 // block web cron.php
$CFG->passwordpolicy = true;
$CFG->preventexecpath = true;
// $CFG->loginhttps handled by Force HTTPS Redirect at cPanel level
$CFG->reverseproxy = false;               // set true only if host puts a proxy in front
```

---

## 4. Ongoing deploys — the `.cpanel.yml` pipeline

Only build this once §1.2 and §1.3 are cleared and §2 is done once.

### 4.1 Deploy key

Already created in §2.4 Option A.

### 4.2 `.cpanel.yml` (commit this to repo root)

Because our repo is the whole site, the deploy syncs the tree **except**
`config.php` and anything runtime:

```yaml
---
deployment:
  tasks:
    - export DEPLOYPATH=/home/<acct>/learning_vrbconsumer
    - /usr/bin/rsync -a --delete
        --exclude='.git/'
        --exclude='config.php'
        --exclude='/public/config.php'
        --exclude='vrb_moodle_dump.sql'
        --exclude='*.sql'
        /home/<acct>/repos/vrb-lms/  $DEPLOYPATH/
    - cd $DEPLOYPATH/public && /opt/cpanel/ea-php82/root/usr/bin/php admin/cli/upgrade.php --non-interactive
    - cd $DEPLOYPATH/public && /opt/cpanel/ea-php82/root/usr/bin/php admin/cli/purge_caches.php
```

> - `DEPLOYPATH` must be **verified in File Manager**, not guessed
>   (DEPLOYMENT.md rule). Replace `<acct>` and the `ea-php82` path with the
>   real values from §1.2.
> - `--delete` makes the live tree match the repo exactly. That is safe
>   here *only because config.php and *.sql are excluded and moodledata is
>   outside DEPLOYPATH.* Double-check those exclusions before the first
>   run.
> - If the lead chose the Softaculous model instead, replace the single
>   rsync with narrow per-directory copies of `public/local/vrblms`,
>   `public/local/vrbcert`, `public/local/vrbcontent`,
>   `public/theme/vrblms` only.

### 4.3 First deploy = verified no-op

1. Make a trivial change on `main` (e.g. a comment in a plugin file).
2. Push. In cPanel → Git Version Control → **Manage → Pull or Deploy →
   Update from Remote**, then **Deploy HEAD Commit**.
3. Confirm `https://learning.vrbconsumer.com` still loads, login works,
   one course renders. Only then trust it with real changes.

### 4.4 Real deploys

Push to `main` → Update from Remote → Deploy HEAD Commit → smoke-test
(§6). Per DEPLOYMENT.md, once anyone outside the dev team is viewing the
live URL, switch to **feature-branch → review → merge → deploy**.

---

## 5. Go-live smoke test (run after every deploy)

- [ ] `https://learning.vrbconsumer.com` loads, valid SSL padlock
- [ ] Employee-code login works (test employee account)
- [ ] Dashboard renders with `theme_vrblms` branding (no fatal → the
      `theme_overridden_renderer_factory` line is intact)
- [ ] A brand course opens; a gated module is locked; passing its quiz
      unlocks the next (the Phase 2 core mechanic — see LOG.md)
- [ ] Leaderboard block loads and filters by Brand / State / City
- [ ] `local_vrbcert` certificate generates a PDF for a qualifying user
- [ ] `local_vrbcontent` book + quiz import screens load in admin
- [ ] *Site admin → Reports → Environment* — all **required** rows green
      (PHP 8.2+, MariaDB 10.11+, every extension)
- [ ] *Site admin → Server → Tasks → Scheduled tasks* — cron running
      within the last few minutes
- [ ] *Site admin → Reports → Security overview* — no critical items

---

## 6. What's still open on the app side (decide if these block go-live)

From `../deploying_project_to_production.md` and `docs/TASKS.md`:

| Item | State | Blocks go-live? |
|---|---|---|
| `report_vrblms` (admin CSV export, TASKS Phase 5) | **not started** — no `public/report/vrblms/` exists | Client decision — leaderboard block covers on-screen viewing; CSV export is a reporting gap |
| Certificate artwork / Top-N qualification numbers / issuance cadence | placeholders, client-pending (CLAUDE.md: keep config-driven) | No, if issued config-driven and tuned later |
| Leaderboard ranking formula | must stay config-driven, not finalized | No — just don't hardcode |
| Admin/manager leaderboard filter view + `local_vrbcert` admin page | coded, **not visually re-verified** after last theme pass | Re-verify on staging before go-live |
| Bulk CSV import end-to-end on production | proven locally (LOG.md) — re-run on prod data | Should be exercised during §2.6 |

---

## 7. Blocker status board (update as you go)

| # | Blocker | Owner | Status (Sep 8) | Re-verify |
|---|---|---|---|---|
| 1 | Shell / SSH / Terminal | host | ✅ working | `git --version` in Terminal |
| 2 | Subdomain doc root outside `public_html` | you | ✅ fixed | Domains → doc root ends `/public`, not under `public_html` |
| 3 | DNS A record `learning` → `192.250.235.88` | you / client (GoDaddy) | ❌ NXDOMAIN | `dig +short learning.vrbconsumer.com` |
| 4 | Per-domain PHP 8.2 (Site Isolation) | **host** | ❌ denied server-side | MultiPHP Manager shows a selectable 8.2 row for the subdomain |
| 5 | MariaDB ≥ 10.11.0 | **host** | ❌ server on 10.6.28 | `mysql --version` |
| 6 | SSL / Force HTTPS on subdomain | you (after DNS) | ⚠️ Off | SSL/TLS Status → AutoSSL green |
| 7 | PHP extensions + INI limits | you (after #4) | ⬜ unchecked | Environment report all green |
| 8 | Inode / disk headroom | host if low | ⬜ unchecked | cPanel Statistics |
| 9 | Cron every minute | you | ⬜ unchecked | Scheduled tasks advancing |

**Two hard blockers (#4, #5) are entirely on the hosting provider and one
of them (#5) may not be solvable on the current shared plan at all.** Send
both in one support ticket (wording in §1.2 and §1.3). Until they're
answered, everything in §2–§4 is on hold — there is no Moodle 5.1 install
possible on PHP 7.4 / MariaDB 10.6.
