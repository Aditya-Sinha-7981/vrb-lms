# VRB LMS — VPS Production Deployment (current plan)

**Status: this is the authoritative production deployment plan.** It
supersedes the cPanel shared-hosting plan in `docs/DEPLOYMENT.md` (policy)
and `docs/CPANEL_DEPLOYMENT.md` (runbook). Those are historical record only —
do not follow their `.cpanel.yml` / Softaculous / Git-Version-Control steps.

**This is the VRB Consumer VPS (66.116.253.207). It is NOT the xoombox
(`thexoombox.in`) server** — different company, different box. Do not use the
`xoombox-vps` skill, its paths, or its guardrails for this project.

What carries over from `docs/DEPLOYMENT.md`: `config.php` and `moodledata/`
are never committed; nothing reaches production without being tested locally
against Docker first; Moodle core is not modified or upgraded.

History: written 2026-09-20 as a plan for a hand-built Nginx stack under
`/var/www/vrb-lms` + `www-data`. **Revised 2026-11-03**: the server was
actually built with **aaPanel**, so every path, the PHP binary and the web
user differ from that plan. The values below are the real ones, taken from
`docs/VRB_LMS_VPS_Deployment_Troubleshooting.md` (the record of the actual
server setup). Anything marked **UNVERIFIED** has not been checked on the box
yet — check it before relying on it.

> **Quick reference:** the copy-paste commands for a routine update are in
> **§7 (Quick commands)**. Read §5 (local vs. server differences) once.

---

## 1. Server environment (actual)

| Item | Value |
|---|---|
| Host | 66.116.253.207 (primary IP), Ubuntu 22.04 LTS, 1 vCPU / ~2 GB RAM + 1 GB swap |
| SSH user | `webnoah-aditya` (sudo). Root SSH still enabled; key auth for `webnoah-aditya` not reliable (§9) |
| Control panel | **aaPanel** (manages Nginx, PHP, sites, cron, SSL) |
| Web server | Nginx (aaPanel-managed) |
| PHP | 8.4.25 — binary **`/www/server/php/84/bin/php`** (CLI ini: `/www/server/php/84/etc/php-cli.ini`) |
| PHP-FPM socket | `/tmp/php-cgi-84.sock` |
| Site root (repo root, `config.php` lives here) | **`/www/wwwroot/learning.vrbconsumer.com`** |
| Nginx document root | **`/www/wwwroot/learning.vrbconsumer.com/public`** (Moodle 5.1 `public/` layout) |
| `config.php` | `/www/wwwroot/learning.vrbconsumer.com/config.php` (server-only, never in git) |
| moodledata | **`/www/moodledata`** (`drwxrws--- www www`) |
| Web/PHP user | **`www`** (aaPanel's user — NOT `www-data`) |
| Database | MariaDB (`$CFG->dbtype = 'mariadb'`) |
| Moodle | 5.1.5+ (Build 20260714), `2025100605.05` |
| Public URL | `https://learning.vrbconsumer.com` (DNS at GoDaddy) |

`admin/cli/` scripts live at the **repo root**, outside `public/`, so every CLI
command runs from `/www/wwwroot/learning.vrbconsumer.com`.

Local dev is unchanged: Docker, containers `vrb-moodle` + `vrb-moodle-db`
(MariaDB 11.4), `http://localhost:8080`.

---

## 2. Decisions (still in force)

- **PHP 8.4 is acceptable.** `public/admin/environment.xml`'s `5.1` block
  requires PHP ≥ 8.2.0 with no `RESTRICT`. Confirmed live on the VPS:
  `admin/cli/checks.php -v --filter=environment` → OK, and `upgrade.php` →
  "No upgrade needed". Watch the PHP error log for 8.4 deprecations.
- **Native stack, not Docker** on the VPS (1 vCPU / 2 GB can't afford the
  overhead). Docker stays the local test bed only.
- **Whole repo = the site root.** Production runs exactly what is in git at a
  given commit (core + `local_vrblms`, `local_vrbcert`, `local_vrbcontent`,
  `theme_vrblms`). A deploy is `git pull` + `upgrade.php` + cache purge. No
  selective-copy pipeline, no CI yet (deliberately manual until the flow is
  proven; revisit later).

---

## 3. Server state

### Done (from the troubleshooting record)
- [x] Moodle files, DB and `config.php` in place; env check and upgrade check pass.
- [ ] `max_input_vars`: **NOT set in the server's PHP config** (decision 2026-11-03 — the system ini files are left untouched). Every Moodle CLI command passes `-d max_input_vars=5000` instead, via `$PHP` (§7, §7.9).
- [x] `open_basedir` fixed in `public/.user.ini` to include `/www/moodledata/`.
- [x] Nginx vhost serves Moodle (HTTP → Moodle's redirect to HTTPS works).
- [x] ufw allows 80/443.

### Not done — do NOT assume any of these
- [ ] **Is the site root a git checkout?** UNVERIFIED — §4 step 0. The
      whole update flow below depends on this.
- [ ] Public DNS A record `learning` → 66.116.253.207 (GoDaddy).
- [ ] HTTPS: nothing listens on 443 yet; issue an SSL cert (aaPanel → Website → SSL → Let's Encrypt).
- [ ] **Cron** (Moodle scheduled tasks — certificates depend on it; §7.4).
- [ ] **Backups** (DB + moodledata, off-box copy).
- [ ] Outbound email (SMTP) — not configured.
- [ ] Root SSH login still enabled; key auth for `webnoah-aditya` unreliable.
- [ ] Image upload error ("An image must have a valid URL") seen only on the
      VPS — likely `upload_max_filesize`/`post_max_size`/`client_max_body_size`
      or moodledata permissions (`LOG.md` 2026-09-23). Still open.

---

## 4. One-time setup so updates are easy (do this BEFORE the next update)

### Step 0 — find out how the code got onto the server
```bash
cd /www/wwwroot/learning.vrbconsumer.com
git status            # "fatal: not a git repository" → go to Step 2 (migration)
git remote -v
git log --oneline -3
ls -la | head -20     # who owns the files? (www? webnoah-aditya? root?)
```
If it IS a git repo on `main`, tracking `git@github.com:Aditya-Sinha-7981/vrb-lms.git`,
and `git status` shows only untracked server files (§5.1), skip to Step 3.

### Step 1 — deploy key (read-only, repo-scoped)
As `webnoah-aditya`:
```bash
ssh-keygen -t ed25519 -f ~/.ssh/vrb_deploy -N "" -C "vrb-vps-deploy"
cat ~/.ssh/vrb_deploy.pub     # add in GitHub → repo → Settings → Deploy keys (read-only)
cat >> ~/.ssh/config <<'EOF'
Host github-vrb
  HostName github.com
  User git
  IdentityFile ~/.ssh/vrb_deploy
  IdentitiesOnly yes
EOF
ssh -T github-vrb             # expect "successfully authenticated"
```
Never use a personal GitHub key; never put a token in the clone URL.

### Step 2 — (only if the site root is NOT a git repo) migrate to a git checkout
Take a backup first (§7.2). Then clone next to the live site, carry over the
server-only files, and swap folders. The live site is untouched until the
`mv`, and the old folder stays as instant rollback.
```bash
cd /www/wwwroot
git clone github-vrb:Aditya-Sinha-7981/vrb-lms.git learning.vrbconsumer.com.new
cd learning.vrbconsumer.com.new && git checkout main && git log --oneline -1

# carry over server-only files (see §5.1)
sudo cp -a ../learning.vrbconsumer.com/config.php ./config.php
sudo cp -a ../learning.vrbconsumer.com/public/.user.ini ./public/.user.ini   # may need: sudo chattr -i on the source first

# compare: anything in the live site that git doesn't have?
diff -rq ../learning.vrbconsumer.com . -x .git | grep -v '^Only in \.:' | head -50
```
Review that diff before swapping. Expected differences: aaPanel's own files
(`.user.ini`, `.htaccess`, `404.html`, `index.html`, `.well-known/`) and
anything someone edited by hand on the server. Anything else = stop and look.
Then (short downtime):
```bash
cd /www/wwwroot
sudo mv learning.vrbconsumer.com learning.vrbconsumer.com.old
sudo mv learning.vrbconsumer.com.new learning.vrbconsumer.com
# fix ownership (Step 3), purge caches (§7.1), reload PHP-FPM, test the site
```
aaPanel tracks sites by path, so the vhost keeps working after the rename.
Delete `.old` only after a few days of normal running.

### Step 3 — ownership, so `git pull` works without sudo-ing as `www`
Recommended model: **code owned by `webnoah-aditya`, group `www`,
read-only to the web user; moodledata owned by `www`.** Moodle never needs to
write to its code directory in normal use.
```bash
cd /www/wwwroot/learning.vrbconsumer.com
sudo chown -R webnoah-aditya:www .
sudo find . -path ./.git -prune -o -type d -exec chmod 750 {} +
sudo find . -path ./.git -prune -o -type f -exec chmod 640 {} +
sudo chmod 640 config.php
git config core.fileMode false     # so chmod changes never show as "modified"
git status                         # must be clean (except untracked server files)
```
(`public/.user.ini` is made immutable by aaPanel — leave its ownership alone;
if `chown` complains about it, that's expected.) If aaPanel later "fixes
permissions" on the site and changes the owner back to `www`, re-run this.

### Step 4 — cron (required: certificates and cohort sync run from it)
aaPanel → Cron → Add task → Shell script, every 1 minute, run as `www`:
```bash
/www/server/php/84/bin/php -d max_input_vars=5000 /www/wwwroot/learning.vrbconsumer.com/admin/cli/cron.php >/dev/null 2>&1
```
or `sudo crontab -u www -e` with `* * * * * <that command>`. Confirm with
`checks.php` (§7.5) — the "Cron running" warning should clear.

### Step 5 — find the PHP-FPM reload command (needed every deploy)
`purge_caches.php` clears Moodle's caches but NOT PHP's opcode cache
(`LOG.md` 2026-09-23). UNVERIFIED on this box — check which exists:
```bash
ls /etc/init.d/ | grep -i php        # aaPanel usually: php-fpm-84
```
then the deploy uses `sudo /etc/init.d/php-fpm-84 reload`. (Or aaPanel → App
Store → PHP 8.4 → Reload.) Write the confirmed command into §7.1.

---

## 5. Local vs. server — what differs and whether it affects deploys

### 5.1 Files that are different on each machine (git must never carry them)

| File | Local (Docker) | Server | In git? | Risk on `git pull` |
|---|---|---|---|---|
| `config.php` (repo root) | `dbhost=db`, `wwwroot=http://localhost:8080`, Docker DB creds | real DB creds, `wwwroot=https://learning.vrbconsumer.com`, `dataroot=/www/moodledata` | **No** — `.gitignore` `/config.php` | None. Never copy local → server. |
| `public/config.php` | Moodle core stub that loads `../config.php` | same | Yes (core file) | None — identical everywhere, don't edit. |
| `public/.user.ini` | doesn't exist | aaPanel's `open_basedir` (must include `/www/moodledata/`) | **No** — ignored since 2026-11-03 | None. Never delete — the site 500s without it. |
| aaPanel extras (`.htaccess`, `404.html`, `index.html`, `.well-known/`) | don't exist | may exist in site root | No | Harmless untracked files. |
| moodledata | Docker volume `/var/www/moodledata` | `/www/moodledata` | No (outside repo) | None. |
| `*.sql` dumps, `moodledata.tar.gz` | in repo root on the Mac | — | **No** — `.gitignore` `*.sql`, `*.tar.gz` | None. Never commit them (real employee data). |

**Rule:** if `git status` on the server ever shows a *tracked* file as
modified, someone edited code on the server. Stop, look at `git diff`, and
move that change into the repo properly before pulling.

### 5.2 Things stored in the DATABASE / moodledata — these do NOT travel with git

Production has **its own database** (restored from the 2026-09-20 dump). From
then on, local and production data diverge. A `git pull` deploys **code
only**. These do not move with it:

- **Admin settings changed through the UI** — Site administration values,
  `local_vrblms` ranking settings (`rankingstrategy`, `unattemptedpolicy`, …),
  `local_vrbcert` settings (titles, colours, body lines, `completion_enabled`,
  …), Moove/vrblms theme settings, logos uploaded via settings (those live in
  moodledata). **New** settings a plugin adds get their default on prod when
  `upgrade.php` runs. If you changed a setting locally, change it on prod
  through the UI as a separate deploy step, and write it in the deploy notes.
- **Content** — courses, books, quizzes, questions, cohorts, enrolment
  methods, users, attempts, issued certificates. Create these on prod
  (through the UI, the CSV user upload, or `local_vrbcontent` importers),
  never by copying the local DB over prod.
- **Scheduled task overrides** (enabled/disabled, timing) set in the UI.

This is why branding rules go in `custom.css` (git-tracked), not DB-only theme
settings — it deploys automatically (`LOG.md`, dark-mode decision).

### 5.3 Environment differences that can make "works locally" fail on prod

| | Local | Prod |
|---|---|---|
| Web server | Apache + mod_php (Docker) | Nginx + PHP-FPM |
| PHP | Docker image's | 8.4.25 |
| MariaDB | 11.4 | 10.x (older) — **don't move dumps local → prod** without testing on a scratch DB first |
| Upload limits | Docker defaults | aaPanel PHP + Nginx `client_max_body_size` |
| Opcode cache | cleared by `docker restart vrb-moodle` | cleared by PHP-FPM reload |

So after every deploy, check the specific thing you changed on prod (§7.1 step 7).

---

## 6. Deploy workflow (summary)

1. Change → test in local Docker → if a `version.php` was bumped, run
   `upgrade.php` locally and test again.
2. Commit + push to `main` (only when asked/confirmed — `CLAUDE.md`).
3. Write down: which plugins' `version.php` changed, and any admin settings
   that must be set by hand on prod (§5.2).
4. Run §7.1 on the server.

Branching: commit straight to `main` while only the dev team looks at the
site. Once the client is reviewing the live URL, `main` effectively *is*
production → move to feature branch → review → merge before deploy.

---

## 7. Quick commands

All commands on the VPS, as `webnoah-aditya`. Set these first in **every**
SSH session — `$PHP` carries the `max_input_vars` override, so every Moodle
command below gets it automatically (§7.9). Use `$PHP` unquoted, exactly as
written:
```bash
ssh webnoah-aditya@66.116.253.207
cd /www/wwwroot/learning.vrbconsumer.com
PHP="/www/server/php/84/bin/php -d max_input_vars=5000"
$PHP -r 'echo "max_input_vars=", ini_get("max_input_vars"), PHP_EOL;'   # must print 5000
```
`PHP Warning: Module "zip" is already loaded` on every command is harmless
(zip is listed twice in the CLI ini; §7.9) — ignore it.

### 7.1 Routine update (the normal deploy)
```bash
# 0. Pre-flight — the line above must have printed 5000; if it printed
#    1000, $PHP was set without the -d flag (new SSH session?) — set it again.

# 1. What's about to change?
git fetch origin
git log --oneline HEAD..origin/main             # commits coming in
git diff --stat HEAD..origin/main               # files coming in
git diff --name-only HEAD..origin/main | grep version.php   # any → DB upgrade needed → backup first (7.2)
git status --short                               # no " M" lines (modified tracked files)

# 2. Backup if any version.php changed (see 7.2)

# 3. Deploy — chained with && so a failure STOPS here and leaves
#    maintenance mode ON (never re-open the site on a half-done upgrade)
PREV=$(git rev-parse HEAD); echo "rollback point: $PREV"
sudo -u www $PHP admin/cli/maintenance.php --enable
git pull --ff-only origin main \
  && sudo -u www $PHP admin/cli/upgrade.php --non-interactive \
  && sudo -u www $PHP admin/cli/purge_caches.php \
  && sudo -u www $PHP admin/cli/build_theme_css.php --themes=vrblms \
  && sudo /etc/init.d/php-fpm-84 reload \
  && sudo -u www $PHP admin/cli/maintenance.php --disable
# If it stopped: read the error, fix it, re-run from the failed command.
# The site stays in maintenance mode until --disable runs.

# 4. Check
sudo -u www $PHP admin/cli/upgrade.php --non-interactive  # "No upgrade needed"
sudo -u www $PHP admin/cli/checks.php            # Environment/Upgrade must be OK
# then open the site, log in, check the feature you changed
# then apply any by-hand admin settings from the deploy notes (§5.2)
```
PHP-FPM reload command confirmed on the box 2026-11-03: `/etc/init.d/php-fpm-84`.

For a CSS/template-only change (no `version.php` bumped), you can skip
maintenance mode and the backup: `git pull --ff-only` → `purge_caches.php` →
`build_theme_css.php --themes=vrblms` → FPM reload.

**Why `build_theme_css.php` matters:** after a cache purge Moodle rebuilds
the theme CSS on the first page request, and until that finishes
(tens of seconds for Moove's SCSS) `theme/styles.php` serves a plain
fallback stylesheet (`theme_styles_fallback_content()`) — users see a
broken/unstyled layout for that time. Building it from the CLI first means
nobody ever gets the fallback.

### 7.2 Backup (before any deploy that bumps a version.php)
```bash
mkdir -p ~/backups
# DB name/user are in config.php ($CFG->dbname / $CFG->dbuser); -p prompts for the password
mysqldump --single-transaction -u <dbuser> -p <dbname> | gzip > ~/backups/db_$(date +%Y%m%d_%H%M).sql.gz
# moodledata (uploaded files, certificates) — less often, it's bigger
sudo tar -czf ~/backups/moodledata_$(date +%Y%m%d_%H%M).tar.gz -C /www moodledata
ls -lh ~/backups
```
Copy important backups off the box (e.g. `scp` to your Mac) — a backup on
the same VPS doesn't help if the VPS dies.

### 7.3 Rollback
```bash
sudo -u www $PHP admin/cli/maintenance.php --enable
git checkout $PREV                  # or the hash you noted
sudo -u www $PHP admin/cli/purge_caches.php
sudo /etc/init.d/php-fpm-84 reload
sudo -u www $PHP admin/cli/maintenance.php --disable
```
If `upgrade.php` already ran a DB upgrade, rolling back code alone isn't
enough: Moodle refuses to run older plugin code against a newer DB version.
Restore the DB backup too:
```bash
gunzip < ~/backups/db_YYYYMMDD_HHMM.sql.gz | mysql -u <dbuser> -p <dbname>
```
When fixed, go back to normal with `git checkout main && git pull --ff-only`.

### 7.4 Cron
```bash
sudo crontab -u www -l                                  # is it there?
sudo -u www $PHP admin/cli/cron.php                     # run once by hand
sudo -u www $PHP admin/cli/scheduled_task.php --list    # task status
sudo -u www $PHP admin/cli/scheduled_task.php --execute='\local_vrbcert\task\issue_completion_certificates'
```

### 7.5 Health checks
```bash
sudo -u www $PHP admin/cli/checks.php -v
sudo -u www $PHP admin/cli/checks.php -v --filter=environment
sudo -u www $PHP admin/cli/upgrade.php --non-interactive     # "No upgrade needed" = in sync
curl -I -H "Host: learning.vrbconsumer.com" http://127.0.0.1  # expect 303 → https
sudo -u www $PHP -r 'define("CLI_SCRIPT",1); require "config.php"; var_dump(array_keys(core_component::get_plugin_list("local")));' | grep -i vrb
```

### 7.6 Logs
```bash
sudo tail -n 100 /www/wwwlogs/learning.vrbconsumer.com.error.log   # aaPanel's usual per-site log — UNVERIFIED path
sudo tail -n 100 /www/server/php/84/var/log/php-fpm.log            # UNVERIFIED path
ls /www/wwwlogs/                                                    # find the real names
```

### 7.7 Users
```bash
sudo -u www $PHP admin/cli/reset_password.php        # interactive: reset any user's password
sudo -u www $PHP admin/cli/kill_all_sessions.php     # log everyone out
```
Account creation itself (site admin vs. Manager vs. employee, CSV bulk
upload) is done in the web UI — see `docs/CSV_IMPORT_SPEC.md`.

### 7.8 Sanity: is the server clean?
```bash
git status --short          # " M" lines = someone edited code on the server → stop
git log --oneline -1        # compare with your Mac's: git log --oneline -1 origin/main
```

### 7.9 Known server gotcha: `max_input_vars` on the CLI
Moodle's environment check (run by `upgrade.php` and `checks.php`) requires
`max_input_vars >= 5000`. On this server both
`/www/server/php/84/etc/php-cli.ini:425` and `php.ini:426` read
`;max_input_vars = 5000` — the leading `;` makes it a comment, so PHP uses
its default **1000**. On the 2026-11-03 deploy this made `upgrade.php` abort
while the (then unchained) commands carried on and re-opened the site with
code ahead of the DB; recovered by re-running the upgrade with the override.
(Same root issue as the initial setup:
`docs/VRB_LMS_VPS_Deployment_Troubleshooting.md` §5.)

**Decision (user, 2026-11-03): do NOT edit the server's PHP ini files.**
Instead every CLI command uses
`PHP="/www/server/php/84/bin/php -d max_input_vars=5000"` (§7 setup), the
cron line passes the same `-d` flag (§4 step 4), and the deploy chain uses
`&&` so any failure leaves maintenance mode on.

Also in those files: `extension = zip.so` appears twice (CLI lines
1850/1853, php.ini 1851/1854) → `PHP Warning: Module "zip" is already
loaded` on every command. Harmless; left as is by the same decision.

**Web side (PHP-FPM):** `php.ini` has the same commented line, so web
requests most likely also run with 1000. The site works, but Moodle forms
with more than 1000 fields (big role-permission or grading screens) could
be silently cut off. Check at Site administration → Server → Environment.
If it's red and a fix is wanted without touching the system ini, the
site-only option is `max_input_vars = 5000` in
`public/.user.ini` (aaPanel makes that file immutable: `chattr -i`, edit,
`chattr +i`; PHP re-reads it within `user_ini.cache_ttl`, 300 s default).

---

## 8. Upcoming deploy: V2 (completion certificates + leaderboard scoring)

Not yet committed as of 2026-11-03. When it ships:
- Bumps `local_vrbcert` 2026092300 → 2026101800 and `local_vrblms`
  2026083001 → 2026101800 → **DB upgrade → backup first (§7.2)**.
- New settings land with defaults: `local_vrblms/unattemptedpolicy = zero`
  (**leaderboard rankings change on prod immediately**: unattempted quizzes
  now count as 0%) and `local_vrbcert/completion_enabled = 1`.
- **Certificates:** with completion enabled, the daily catch-up task (once
  cron runs) and the admin "Issue for existing completions" button will issue
  a completion certificate to **every employee who has already passed all
  their brand's quizzes on prod**. Decide whether that's wanted before
  enabling cron / before the deploy; if not, untick `completion_enabled` in
  the plugin settings right after `upgrade.php`.
- New event observers + a new scheduled task → the FPM reload and cache
  purge are not optional for this one.

---

## 9. Open decisions / items not resolved by this doc

- **Backups** — no schedule, no off-box copy yet (§7.2 is manual).
- **Outbound email (SMTP)** — not configured; VPS providers often block port 25.
- **Key-based SSH** for `webnoah-aditya` — root cause unknown; until fixed,
  don't disable root login.
- **CI/CD** — deliberately deferred until the manual flow is proven.
- **Docker on the VPS** — only if it gets 2+ cores.
- **Moodle router** (`$CFG->routerconfigured`) — leave unset until a matching
  Nginx rewrite from Moodle 5.1's official docs is in the aaPanel vhost.

---

## 10. What Claude Code should NOT do without asking first

- Do not mix this server up with the xoombox VPS or use its skill.
- Do not `git pull` on prod for a change not tested locally in Docker; do not
  push to `main` untested; no commit/push unless the user asks.
- Do not commit `config.php`, `public/.user.ini`, moodledata, DB dumps,
  `moodledata.tar.gz`, credentials, or deploy keys.
- Do not copy local `config.php` or a local DB dump onto the server.
- Do not edit or upgrade Moodle core.
- Do not change system-wide things unasked: PHP version/ini, aaPanel global
  settings, a second PHP, MariaDB config beyond the Moodle DB, DNS records
  other than the `learning` A record, SSL. (The earlier cPanel PHP bump that
  broke a live site is why.)
- Do not disable root SSH before key auth works; enable firewall changes only
  with SSH allowed.
- Do not guess paths/values marked UNVERIFIED — check on the box.
- No destructive server commands (dropping DBs, `rm -rf` under `/www`,
  overwriting `config.php`/`.user.ini`, `git reset --hard`, firewall flushes)
  without confirmation.
