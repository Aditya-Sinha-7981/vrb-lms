# VRB LMS — VPS Production Deployment (current plan)

**Status: this is the authoritative production deployment plan.** It
supersedes the cPanel shared-hosting plan in `docs/DEPLOYMENT.md` (policy)
and `docs/CPANEL_DEPLOYMENT.md` (runbook). Those two files are kept as
historical record and are not edited beyond a pointer note — do not follow
their `.cpanel.yml` / Softaculous / Git-Version-Control instructions.

What carries over unchanged from `docs/DEPLOYMENT.md`: `config.php` and
`moodledata/` are never committed; nothing reaches production without being
tested locally against Docker first; Moodle core is not modified or upgraded.

Written 2026-09-20. Items marked **VERIFIED IN REPO** were checked against
files in this repo this session. Items marked **PROVIDER-REPORTED** or
**USER-REPORTED** came from the server owner (output of `php -v`, `free -h`
etc. run on the VPS) and have not been re-checked from this repo.

---

## 1. Server environment

| Item | Value | Source |
|---|---|---|
| OS | Ubuntu 22.04.5 LTS | user-reported (run on VPS) |
| CPU / RAM | 1 vCPU, ~2 GB RAM (1.9 Gi per `free -h`), 1 GB swap already configured | user-reported |
| Disk | 49 GB total, 35 GB free | user-reported |
| IPs | Two IPv4 addresses; **66.116.253.207 is the primary** and the one DNS will point at | user-reported |
| PHP | 8.4.25, pre-installed system-wide by the provider | user-reported (`php -v`) |
| Database | MariaDB 10.11.10, pre-installed system-wide by the provider | user-reported (`mysql --version`) |
| Web server | **None installed yet** (`nginx -v` / `apache2 -v` → not found) | user-reported |
| Target hostname | `learning.vrbconsumer.com` (DNS lives at GoDaddy) | project docs |

Local dev is unchanged: Docker (`moodlehq/moodle-docker`), MariaDB **11.4**,
`http://localhost:8080`. Note the DB **version skew**: local is 11.4,
production is 10.11.10. See §9 (open decisions) before ever moving a database
dump between the two.

### Moodle's own minimums vs. this server — VERIFIED IN REPO

- `public/admin/environment.xml`, the `<MOODLE version="5.1">` block (the
  block that governs our installed 5.1.5, release string
  `5.1.5+ (Build: 20260714)`, `public/version.php`): **MariaDB ≥ 10.11.0**
  → server's 10.11.10 satisfies it (just barely on the minor; fine).
- Same block: **PHP ≥ 8.2.0**, `level="required"`, with **no `<RESTRICT>`
  tag** → no upper bound declared. `composer.json` agrees (`"php": ">=8.2.0"`).
- Required PHP extensions (same block / `composer.json`): iconv, mbstring,
  curl, openssl, ctype, zip, zlib, gd, simplexml, spl, pcre, dom, xml,
  xmlreader, intl, json, hash, fileinfo, sodium, filter — plus **mysqli**
  for MariaDB. Optional: tokenizer, soap, exif. `memory_limit` ≥ 96M
  required; `opcache.enable=1` recommended. Check the provider's system PHP
  actually has all of these — do not assume (§6, step 5).
- No Composer step: 5.1.5 has no `vendor/` in the tree and `composer.json`
  lists only PHP + extensions as requirements. (`/vendor/` is git-ignored
  regardless.)

---

## 2. Decision 1 — PHP 8.4 is acceptable (with a live-check caveat)

**Decision:** use the provider's PHP 8.4.25. No downgrade, no side-by-side
PHP 8.3 install.

**How it was verified:** by reading `public/admin/environment.xml` in this
repo directly, not from general knowledge or the provider's claim. The
`<MOODLE version="4.5">` block **does** carry
`<RESTRICT function="restrict_php_version_84" …/>` (that's the block that
explicitly bans 8.4), but the `5.1` block has no RESTRICT on its PHP entry
(confirmed by grepping the whole 5.1 block for `RESTRICT` — no hits).

**Correction to the brief that produced this doc:** the brief described "the
last `<MOODLE>` block" as the 5.1.5 one requiring PHP 8.3.0. In this repo the
last block in the file is actually **`5.2`** (PHP 8.3.0), and **5.1** — ours —
requires PHP **8.2.0**. The conclusion is unaffected (neither block restricts
8.4; ours has a lower minimum than described), but the reasoning should cite
the `5.1` block. The `5.2` block is irrelevant to us; we do not upgrade
(`Agents.md`).

**Caveat — this is a static reading, not a live test.** Nothing has yet run
Moodle's environment checker on the VPS. Once the code is deployed (§6 step
8), run the authoritative check:

- Browser: *Site administration → Server → Environment*
  (`public/admin/environment.php`) — this is the real environment check
  (PHP version, extensions, settings, DB vendor/version).
- CLI: `sudo -u www-data php admin/cli/upgrade.php --non-interactive` runs the
  same `check_moodle_environment()` before doing anything (`admin/cli/upgrade.php`
  line ~193) and aborts with the errors if it fails. (Alternatively
  `install_database.php` does likewise on a fresh install.)
- **`admin/cli/checks.php` is a different tool** — it runs Moodle's *Check
  API* (status/security/performance checks), not the PHP-version environment
  check. It is worth running after deploy, but it does **not** confirm PHP 8.4
  compatibility on its own. (The brief named `checks.php` as the environment
  checker; verified in the source that it isn't.)

**If the live checker disagrees with our XML reading, the live checker wins**
— stop, report, and decide (e.g. install PHP 8.3 side-by-side) before
continuing. Also watch the error log for PHP 8.4 deprecation notices from
Moodle core / `theme_moove` / our `local_vrb*` plugins in the first days; the
environment checker does not test runtime deprecations.

---

## 3. Decision 2 — native stack (Nginx + PHP-FPM + MariaDB), NOT Docker

Local dev uses `moodlehq/moodle-docker`. We deliberately do **not**
replicate that on the VPS.

**Rationale:** the box has 1 vCPU and ~2 GB RAM. Docker adds container-runtime
overhead and runs each service as a separate isolated process set (Nginx,
PHP-FPM, MariaDB as three containers), a proportionally large tax on a
single-core machine that risks slow responses or OOM kills under real load.
Nginx + PHP-FPM + MariaDB installed directly on the host share the OS and
have much lower overhead, which fits this box.

**Consequences:** no `docker-compose.yml`, no `Dockerfile`, no container
orchestration for this target. The repo itself is the webroot.

**Revisit** Docker only if the VPS is later upgraded to 2+ cores.

Local Docker remains the **test bed** — changes are proven there first
(see §8). Prod (native) and local (Docker) differ in PHP/DB versions and web
server; a change that passes locally can still hit a prod-only difference, so
the first deploy verification (§6 step 10) is not optional.

---

## 4. Decision 3 — full-repo clone as webroot (simpler than the cPanel plan)

**Old cPanel plan (superseded):** Moodle core was to be installed separately
(Softaculous) into an existing account, and a `.cpanel.yml` script copied only
`local/`, `blocks/`, `report/`, theme folders from a staging clone into that
install, with `$DEPLOYPATH` verified by hand — never a blanket copy, to avoid
overwriting core.

**New VPS plan:** there is no pre-existing Moodle install and we have root, so
we `git clone` the **entire** repo — Moodle 5.1.5 core included, plus
`local_vrblms`, `local_vrbcert`, `local_vrbcontent`, `theme_vrblms` — straight
onto the server as the webroot.

Why this is meaningfully simpler, not just different:

- **One source of truth.** What's on the server is exactly what's in git at
  a given commit, core and custom code together. No second Moodle
  install to keep version-aligned with our repo (the cPanel plan risked core
  version skew between Softaculous's Moodle and our pinned 5.1.5).
- **No selective-copy pipeline.** No `.cpanel.yml`, no `$DEPLOYPATH`, no
  "which directories are safe to overwrite" carve-outs — the repo's own
  `.gitignore` already keeps `config.php`, `moodledata/`, `*.sql` and
  `/vendor/` out of git, so `git pull` cannot clobber them.
- **No hosting-panel dependency.** Everything (web server, PHP, DB, SSL,
  firewall) is under our control over SSH; none of the cPanel blockers
  (shell disabled, shared PHP version, nested docroot) apply.

### Deploy model

1. `git clone` the repo to `/var/www/vrb-lms` on the VPS. Do not clone as
   root; see §6 step 6 for ownership. (Path is a suggestion — "something like"
   `/var/www/vrb-lms`; if changed, change it everywhere below.)
2. **Nginx document root = `/var/www/vrb-lms/public`** — the `public/`
   subdirectory, per the Moodle 5.1 restructure (`Agents.md`, `LOG.md`
   2026-08-19). Do **not** point it at the repo root and do not assume the
   pre-5.1 layout. `admin/cli/` scripts live at the repo root, *outside*
   `public/` (verified: `admin/cli/purge_caches.php` exists there), so CLI
   commands run from `/var/www/vrb-lms`.
3. **`config.php` is created directly on the server at
   `/var/www/vrb-lms/config.php`** (true repo root, not inside `public/`) and
   is **never committed**. Verified: `.gitignore` line 22 is `/config.php`, and
   `git ls-files` does not track it. Do not remove that exclusion.
4. **`moodledata/` lives outside the repo and the webroot entirely**, e.g.
   `/var/www/moodledata`, same as local (`Agents.md`).
5. **To deploy a change:** SSH in, `git pull` inside `/var/www/vrb-lms`, then
   `sudo -u www-data php admin/cli/purge_caches.php`. If the pull touched any
   plugin `version.php` (all of our custom plugins bump versions on change),
   also run `sudo -u www-data php admin/cli/upgrade.php --non-interactive`
   before the purge.
6. **Deliberately manual for now** (SSH + `git pull`), **not** GitHub Actions
   or any CI. CI automation is a reasonable later addition once the manual flow
   has been proven working end-to-end — not before.

Note the repo is large (~29.6k tracked files, the whole Moodle tree); the
first clone is slow-ish but subsequent `git pull`s are small. Clone uses
disk space in `.git` too — 35 GB free is ample.

---

## 5. Current server state

### Done
- [x] System updated (`apt update && apt upgrade`) and **rebooted** (kernel
      update required it).
- [x] Non-root sudo user **`webnoah-aditya`** created (hyphen, since Linux
      usernames can't contain spaces).
- [x] `webnoah-aditya` is in the `sudo` group; `sudo whoami` returns `root`.
- [x] SSH login as `webnoah-aditya` works via **password** auth.

### Known open item (not a blocker)
- [ ] **Key-based SSH auth for `webnoah-aditya` is not reliably working.** A
      key setup was attempted (root's `authorized_keys` copied over with
      rsync; permissions verified: `~/.ssh` 700, `authorized_keys` 600,
      correct ownership), but logins via key don't work reliably and the root
      cause has **not been found**. Deprioritized — password + `sudo` is a
      functional fallback. Do not treat key auth as working.

### Pending / not done — do NOT assume any of these
- [ ] **`PermitRootLogin no` has NOT been applied. Root SSH login is still
      enabled** — do not assume otherwise. Intentionally deferred until key
      auth for `webnoah-aditya` is confirmed reliable, so a lockout is
      impossible.
- [ ] **`ufw` firewall is not configured.** (Until it is, the box relies only
      on whatever the provider filters upstream — unknown.)
- [ ] No web server installed (Nginx and PHP-FPM still to do).
- [ ] No Moodle database or DB user created.
- [ ] Repo not yet cloned; no `config.php`; no `moodledata/`.
- [ ] **DNS A record for `learning.vrbconsumer.com` → 66.116.253.207 not yet
      created in GoDaddy.**
- [ ] No SSL certificate.

---

## 6. Remaining setup sequence (in order)

Each step should be finished and verified before the next. Commands below are
the planned shape, not yet executed — check versions/paths on the box rather
than pasting blindly (consistent with the project's "verify, don't recall"
rule).

### Step 1 — SSH hardening (root login off only after key auth is proven)
1. Diagnose and fix key auth for `webnoah-aditya` (start with
   `ssh -v webnoah-aditya@66.116.253.207` and the server's
   `journalctl -u ssh`/`/var/log/auth.log` at the failed attempt; also check
   `AuthorizedKeysFile`, `PubkeyAuthentication`, and home-dir permissions in
   `sshd_config` — the cause is unknown, these are just where to look).
2. Confirm key login works **repeatedly**, from a fresh terminal, while a
   second already-open SSH session stays connected as a safety net.
3. **Only then** set `PermitRootLogin no` in `/etc/ssh/sshd_config` (check
   `sshd_config.d/*.conf` for overrides), `sudo sshd -t` to validate, reload
   sshd, and test a new `webnoah-aditya` login *before* closing the safety
   session. Optionally set `PasswordAuthentication no` last, same care.

### Step 2 — `ufw` firewall
Allow **OpenSSH first** (`sudo ufw allow OpenSSH`, or the actual SSH port if
non-default), then 80/tcp and 443/tcp, then `sudo ufw enable`. Enabling before
allowing SSH locks you out. Confirm with `sudo ufw status verbose` and a fresh
SSH login.

### Step 3 — Nginx + PHP-FPM
1. `sudo apt install nginx`. Provider pre-installed PHP 8.4.25 — verify whether
   that includes **PHP-FPM** (`php-fpm8.4`) or only the CLI; install FPM if
   missing (`systemctl status php8.4-fpm`, `dpkg -l | grep php8.4`).
2. Confirm all required extensions (§1) are present for **the FPM SAPI** as
   well as CLI (`php -m` shows CLI only; check FPM's config / a `phpinfo`
   probe that is deleted immediately). Install `php8.4-mysql` (mysqli),
   `-intl`, `-gd`, `-zip`, `-xml`, `-mbstring`, `-curl`, `-soap`,
   `-sodium` etc. as needed.
3. PHP tuning suited to 1 vCPU / 2 GB: `memory_limit` ≥ 96M (Moodle's
   minimum; higher e.g. 256M is common — but with a small RAM budget, size
   FPM pool `pm.max_children` conservatively so children × memory stays well
   under RAM; start small, e.g. 4–6, and watch), `opcache.enable=1`,
   `upload_max_filesize`/`post_max_size` sized for course content,
   `max_input_vars` per Moodle's docs. Verify each against the live
   Environment page later; don't hardcode numbers from memory.
4. Nginx server block for `learning.vrbconsumer.com`:
   `root /var/www/vrb-lms/public;`, PHP handled by the FPM socket,
   `client_max_body_size` matching PHP's upload limit. **Moodle 5.1 has a
   request router** (`public/r.php` exists; `public/lib/classes/router.php`).
   Verified: `$CFG->routerconfigured` (read in `public/lib/setup.php`,
   defaults to false) controls it — while false, Moodle generates routed URLs
   with a literal `/r.php` prefix; it should be set to `true` in
   `config.php` **only after** the Nginx vhost has the matching router
   fallback rewrite. Look up the exact Nginx directive in Moodle 5.1's
   official docs (not memory), add it, then set the flag and test a routed
   URL. Also make sure `moodledata` and non-`public/` paths are not reachable
   via the vhost.
5. `sudo nginx -t && sudo systemctl reload nginx`. Test with a plain
   `curl -I http://66.116.253.207` (default-server response is fine at this
   stage).

### Step 4 — MariaDB database and user for Moodle
Provider pre-installed MariaDB 10.11.10 — check it's running
(`systemctl status mariadb`) and that `mysql_secure_installation`-equivalent
hardening is done (root auth, anonymous users, test DB — unknown state).
Create a database (`utf8mb4`, `utf8mb4_unicode_ci`) and a dedicated user with
privileges **only on that database**, host `localhost`, strong generated
password (store in a password manager, not in this repo). Keep MariaDB bound
to localhost (no remote access; ufw does not open 3306). Tune InnoDB buffer
pool for a 2 GB box shared with PHP-FPM (don't leave it at a size that
starves PHP).

### Step 5 — Verify the stack is capable (before cloning)
PHP extension/CLI check against §1's list for both CLI and FPM; disk/swap
sanity (`free -h`, `df -h`); the DB user can connect from PHP.

### Step 6 — `git clone` + `config.php` + `moodledata`
1. As `webnoah-aditya` (not root): give GitHub a **read-only, repo-scoped
   deploy key** for this server (private repo; do not use a personal account
   key, and do not embed credentials in a clone URL), then
   `git clone <repo> /var/www/vrb-lms` (create the parent dir with sudo and
   chown appropriately first). Confirm the checked-out branch is `main` and
   the tree matches `MOODLE_501_STABLE`/5.1.5 (`public/version.php`).
2. Create `moodledata` **outside** the repo: e.g.
   `sudo mkdir /var/www/moodledata && sudo chown www-data:www-data …`,
   mode 0770 (or as Moodle's docs say), not web-reachable.
3. Create `/var/www/vrb-lms/config.php` by hand from `config-dist.php`:
   `dbtype = 'mariadb'`, `dbhost = 'localhost'`, the DB name/user/password from
   step 4, `wwwroot = 'https://learning.vrbconsumer.com'` (no trailing slash;
   final `https` form — use `http://` only for a pre-SSL test), `dataroot`,
   and leave `$CFG->routerconfigured` unset until the Nginx router rewrite
   is in place (step 3). **Local `config.php` is not the template** —
   it points at Docker container hostnames. Verify `git status` shows it
   untracked-and-ignored, never staged.
4. Ownership/permissions: code readable by `www-data` but **not writable by
   the web user** where practical (Moodle does not need to write to its code
   dir in normal operation); `moodledata` writable by `www-data`.
5. Database initialisation: see §9 (fresh install vs. importing local data —
   a decision that is **not yet made**). For a fresh install, run
   `sudo -u www-data php admin/cli/install_database.php …` from the repo root
   (this also runs the environment check).

### Step 7 — DNS A record (GoDaddy)
Create an **A record** `learning` → `66.116.253.207` on the `vrbconsumer.com`
zone in GoDaddy (DNS is there, not on the VPS or cPanel). Keep TTL short
(e.g. 600s) at first. Verify with `dig +short learning.vrbconsumer.com` from
outside the server before continuing. This must resolve before step 8 — the
certificate challenge needs it. Do not touch other records on the zone
(the main `vrbconsumer.com` site is live).

### Step 8 — SSL with certbot
Install certbot (snap or apt per current Let's Encrypt guidance), obtain a
cert for `learning.vrbconsumer.com` (Nginx plugin), confirm the redirect
HTTP→HTTPS, and confirm auto-renewal (`sudo certbot renew --dry-run`). Then
make sure `wwwroot` in `config.php` is the `https://` URL, and ufw allows 443.

### Step 9 — Cron
Moodle needs its scheduled tasks. `local_vrbcert` issues certificates via a
scheduled task (`classes/task/issue_certificates.php`) and `enrol_cohort`
sync also runs from cron, so a missing cron would silently break features.
Add a crontab entry for `www-data` running `admin/cli/cron.php` every minute
(`sudo crontab -u www-data -e`); confirm in *Site administration → Server →
Tasks*.

### Step 10 — First deploy verification
- Run the environment check for real (§2): *Site administration → Server →
  Environment* and/or `sudo -u www-data php admin/cli/upgrade.php
  --non-interactive`. **Everything green (or only known-acceptable optional
  warnings) before proceeding.** Then run `admin/cli/checks.php` too.
- Confirm plugin detection with the `core_component::get_plugin_list()`
  snippet from `Agents.md` (adapted: `sudo -u www-data php -r …` with
  `CLI_SCRIPT` and this server's `config.php` path), for `local` (expect
  `vrblms`, `vrbcert`, `vrbcontent`) and `theme` (expect `vrblms`).
- Theme sanity: `theme_vrblms` needs
  `$THEME->rendererfactory = 'theme_overridden_renderer_factory'` (see
  `CLAUDE.md`/`LOG.md` 2026-08-20) — check the login page and a logged-in
  page render, not just the front page.
- Load `https://learning.vrbconsumer.com`, log in as admin, check the error
  log (`/var/log/nginx/`, PHP-FPM log) for PHP 8.4 deprecations.
- Behavioural smoke test of the core mechanic (employee login → quiz gating →
  leaderboard) once real/test data exists on the server.

---

## 7. Day-to-day deploy checklist (once the server is live)

1. Change is committed and pushed to `main` **after** being tested locally
   against Docker (see §8).
2. `ssh webnoah-aditya@66.116.253.207`
3. `cd /var/www/vrb-lms && git pull` (note the before/after commit hash; if
   the pull is non-fast-forward or reports local changes, stop — someone edited
   on the server).
4. `sudo -u www-data php admin/cli/upgrade.php --non-interactive` if any
   plugin/core `version.php` changed.
5. `sudo -u www-data php admin/cli/purge_caches.php`
6. Load the site and check the specific thing that changed. Rollback path: 
   `git checkout <previous-hash>` + purge caches (DB schema upgrades are not
   automatically reversible — take a DB backup before any deploy that bumps a
   plugin version).

---

## 8. Branching / process notes

Same as `docs/DEPLOYMENT.md`: **Phase A** — commit directly to `main` while
only the dev team is looking; **Phase B** — feature-branch → review → merge
before any deploy once someone outside the dev team is reviewing a live URL.
Since the VPS deploy is a plain `git pull` of `main`, the moment the VPS URL
is shown to the client, `main` effectively *is* production — that is the
trigger to move to Phase B.

---

## 9. Open decisions / items not resolved by this doc

Do not silently resolve any of these:

- **How production gets its initial data.** Fresh Moodle install + re-run of
  `local_vrbcontent` / CSV imports, vs. restoring a dump from local Docker.
  Restoring means moving data from **MariaDB 11.4 → 10.11.10** (downgrade
  direction; collation/feature incompatibilities are possible — test the
  import on a scratch DB first) and copying `moodledata` file contents.
  `vrb_moodle_dump.sql` in the repo root is git-ignored (`*.sql`) and must stay
  that way. Client/employee data and real credentials must not be committed.
- **Backups** for the VPS (DB dumps + `moodledata`, off-box copy) — not
  planned yet; a single-VPS production with none is a risk.
- **Outbound email** (SMTP for Moodle notifications/password resets) — not
  configured/considered yet; VPS providers commonly block port 25.
- **Fixing key-based SSH** — root cause unknown (§5).
- Whether **the agency-owned cPanel staging account** from the old plan is
  still needed — probably moot now, but that's a project-lead call.
- **CI/CD** — deliberately deferred (§4 item 6).
- **Docker later?** Only if the VPS gains 2+ cores (§3).
- Client's existing cPanel host issues (`docs/DEPLOYMENT.md`) are now
  irrelevant to *this* deployment, but the `vrbconsumer.com` GoDaddy DNS and
  the live main site (PHP 7.4 on its own host) still are: only add the one
  new A record.

---

## 10. What Claude Code should NOT do without asking first

- **Do not disable root SSH login (`PermitRootLogin no`)** until key-based
  auth for `webnoah-aditya` has been confirmed working reliably. Never assume
  it is already disabled either. Enable ufw only after allowing SSH.
- **Do not run `git pull` on production** for a change that hasn't been tested
  locally against Docker first (carried over from `docs/DEPLOYMENT.md`), and
  do not push to GitHub `main` untested. Also: no commit/push at all unless
  the user explicitly asks (`CLAUDE.md`).
- **Do not assume PHP 8.4 is fully compatible** until Moodle's real
  environment check (*Site administration → Server → Environment* /
  `admin/cli/upgrade.php`) has been run against a live install on this VPS.
  If it disagrees with the XML reading in §2, the live checker wins.
  (`admin/cli/checks.php` alone does not answer this question.)
- **Do not commit `config.php`, `moodledata/`, DB dumps, credentials, the DB
  password, or deploy keys**, and do not remove `/config.php` from
  `.gitignore`.
- **Do not edit Moodle core** or upgrade Moodle (`Agents.md`).
- **Do not change provider-level/system-wide things unasked** — system PHP
  version or ini, installing a second PHP, system MariaDB config beyond the
  Moodle DB/user, DNS records other than the single `learning` A record, SSL
  setup. Ask first; the old account-level-change incident (a PHP bump that
  broke a live site, `docs/DEPLOYMENT.md`) is the reason for this rule, even
  though this VPS is a separate box.
- **Do not guess values** — hostnames, paths, IPs, versions, PHP-FPM
  socket paths: read them from the server or repo. Do not invent DB
  credentials.
- **Do not start Docker-based tooling on the VPS** or add
  `docker-compose.yml`/`Dockerfile` for this target (§3).
- **Do not add GitHub Actions / CI** for deploys yet (§4 item 6).
- **Do not run destructive commands on the server** (dropping DBs, `rm -rf`
  in `/var/www`, overwriting `config.php`, `git reset --hard`, firewall
  flushes) without confirmation.
