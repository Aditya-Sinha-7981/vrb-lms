# VRB LMS — Deployment & Git Workflow

## Current environment

- **Local dev:** MacBook Pro M4 Pro, Docker Desktop, `moodlehq/moodle-docker`
  running Moodle source checked out at `MOODLE_501_STABLE` (5.1.5), served at
  `http://localhost:8080`.
- **Repo structure:** the Moodle source tree itself IS the git repo root
  (i.e. `admin/`, `lib/`, `public/`, `local/`, `blocks/` etc. sit at repo
  root — NOT nested inside a wrapper folder). `docs/` and `scripts/` live
  inside this same repo, alongside Moodle core, since a separate outer repo
  would create a nested-repo conflict with Moodle's own `.git`.
- **GitHub:** private repo, single `main` branch (an earlier mix-up
  accidentally pulled in Moodle's own unstable `main`/dev branch alongside
  the intended stable one — this has been cleaned up; if you ever see two
  branches with one tracking `5.3dev` or similar, that's the same mistake
  recurring, delete it).
- **`config.php` and `moodledata/` are NEVER committed to git, in any
  environment.** `config.php` holds live DB credentials; Moodle's own
  `.gitignore` already excludes it via `/config.php` — do not remove that
  exclusion. `moodledata/` is runtime content, lives outside the repo/web
  root entirely.

## Production hosting — status as of this writing

Client's own cPanel account (`vrbconsumer.com`, subdomain intended:
`learning.vrbconsumer.com`) has multiple blockers currently pending
resolution with the hosting provider:

- Shell/SSH access disabled account-wide (blocks both direct SSH and
  cPanel's Git Version Control tool, which depends on shell access under
  the hood).
- Per-domain PHP isolation disabled (PHP version is shared account-wide;
  Moodle needs 8.2+, the client's existing production site runs 7.4 — do
  NOT bump PHP account-wide without explicit confirmation the main site is
  compatible, this has already caused one live-site outage that was caught
  and reverted).
- Subdomain document root was initially nested inside the main site's
  `public_html` — needs to be a fully separate sibling folder before
  installing, not a subfolder of the main site's web root.

A support ticket covering all of the above has been sent; resolution timeline
unknown. **Do not treat any of these as resolved without explicit
confirmation** — check current status before assuming production deployment
is unblocked.

## Interim staging option

A separate cPanel account (agency-owned, full shell access already
available) may be used as a **staging environment** to validate the full
deployment pipeline (Git Version Control → `.cpanel.yml` → live site) ahead
of the client's own hosting access being resolved. This is explicitly for
de-risking the deployment mechanics early — confirm with the project lead
whether this is meant to stay staging-only or is being considered as a
longer-term host, since that's a business decision (client's own
infrastructure vs. agency-hosted), not a technical one.

## Deployment pipeline design (to be executed once shell access exists,
## on whichever cPanel account is used)

1. GitHub is the source of truth. Private repo, SSH deploy key
   (read-only, repo-scoped) for cPanel's outbound pull — do not use a
   personal account key, and never embed credentials directly in a clone
   URL (cPanel's Git Version Control form explicitly rejects this).
2. cPanel's Git Version Control tool clones into a **staging path** on the
   server, separate from the live Moodle install path.
3. A `.cpanel.yml` deploy config copies ONLY specific custom-code
   directories from the staging clone into the live install — `local/`,
   `blocks/`, `report/`, theme child-folder. **Never a blanket copy of the
   entire repo root over the live install** — Moodle core on the server was
   installed once via Softaculous and should not be overwritten wholesale by
   a deploy task.
4. `$DEPLOYPATH` in `.cpanel.yml` must be verified against the actual live
   install path (check cPanel File Manager) — do not assume a path.
5. First deploy run should be a verified no-op (confirm site still loads)
   before trusting the pipeline with real plugin code.

## Branching model

- **Phase A (current):** commit directly to `main`. No one outside the dev
  team is viewing the live/staging site yet — branch overhead isn't earning
  its keep.
- **Phase B (once client or project lead is actively reviewing a live URL):**
  switch to feature-branch → review → merge-to-main before any deploy. The
  trigger is "someone outside the dev team is looking at it," not a
  calendar date.

## What Claude Code should NOT do without asking first

- Do not push to GitHub `main` without the change being tested locally
  against Docker first.
- Do not write or modify `.cpanel.yml`'s `$DEPLOYPATH` with a guessed path —
  this must come from a verified File Manager check.
- Do not touch account-level hosting settings (PHP version, domain document
  roots, SSL) even if given server access — these have already caused one
  production incident when changed without full verification of downstream
  effects. Flag and ask before changing anything at the hosting-account
  level, as opposed to application-level (Moodle plugin) work.
