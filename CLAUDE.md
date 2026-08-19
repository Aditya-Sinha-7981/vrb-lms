# CLAUDE.md — VRB LMS (Moodle 5.1.5)

This file is the always-loaded entry point for Claude Code sessions in this
repo. It summarizes and points to the fuller docs — it does not replace them.

## Read before doing anything else

In this exact order:
1. `Agents.md` (repo root) — environment facts, especially the `public/`
   directory restructure. Non-negotiable, read in full.
2. `docs/PROJECT_CONTEXT.md` — client requirements, business context.
3. `docs/ARCHITECTURE.md` — verified technical findings vs. still-OPEN
   decisions. Never treat an OPEN item as resolved on your own.
4. `docs/DEPLOYMENT.md` — git workflow, what not to touch.
5. `docs/TASKS.md` — current phased task list.
6. `LOG.md` (repo root) — append-only work log, written by agents for
   agents. Check it for prior entries relevant to whatever you're about to
   do — it has the specifics (exact commands, exact verification steps,
   dead ends already hit) that this file and the docs only summarize.
7. `docs/moodle-feasibility-audit.html` — full original audit, deep reference.

## File locations

`LOG.md` lives at the **repo root only** — never create a log file
anywhere else (this has already happened once and had to be cleaned up,
see `LOG.md`'s 2026-08-20 entries). `docs/` holds the durable, canonical
docs; `docs/temp docs/` holds scratch/working artifacts (task-briefing
prompts, generated sample data) that aren't part of the required reading
and aren't guaranteed to stay accurate. Full rule in `Agents.md`'s "File
locations" section.

## What this project is

Internal employee training LMS for VRB Consumer, built on Moodle. NOT a
public course-selling site — employees only, bulk-imported from HR, logging
in via an Employee Code. Three brands (Veeba, Wok Tok, Zyro), each employee
may belong to multiple brands. Core mechanic: sequential modules gated by
quiz pass/fail, feeding a **region-segmented** leaderboard (Brand → State →
City), with configurable/non-finalized ranking logic, and certificates for
top performers (mechanism TBD — see ARCHITECTURE.md § Certificates, OPEN).
Full detail in `docs/PROJECT_CONTEXT.md`.

## The one structural fact that matters most

**As of Moodle 5.1, all plugin directories live under `public/`, not the
repo root.** `config.php` is the exception — it stays at the true repo root.
Any source describing `moodle/theme/`, `moodle/local/`, `moodle/blocks/`
directly off the root is describing an older Moodle version and is wrong
here. Confirmed layout:

```
moodle/
├── config.php              ← true root, NOT inside public/
└── public/
    ├── theme/               ← boost, classic, moove
    ├── local/               ← custom local plugins
    ├── blocks/              ← custom block plugins
    ├── report/              ← custom report plugins
    ├── admin/, mod/, lib/
```

Before creating any new plugin folder, verify the parent directory by
listing what's already there — don't assume from memory. See `Agents.md`
for the `docker exec ... ls` commands and the plugin-detection verification
snippet.

## Verified environment facts

- Moodle **5.1.5+ (Build 20260714)**, version `2025100605.05`, branch
  `MOODLE_501_STABLE`. **Do not upgrade** — flag and get explicit
  confirmation if asked to.
- Local dev: Docker, containers `vrb-moodle` (app) + `vrb-moodle-db`
  (MariaDB 11.4), served at `http://localhost:8080`. Compose project lives
  at `~/Files/Webnoah Projects/vrb-lms` (note: `Agents.md`/`DEPLOYMENT.md`
  say `~/Projects/vrb-lms/` — that path is stale; use `docker ps` /
  `docker compose ps` from the actual repo location if the documented path
  doesn't resolve).
- Theme: **`theme_vrblms`** is the active theme (`$CFG->theme`), a child
  of **Moove** (`theme_moove`, release 5.1.2, at `public/theme/moove/`,
  untouched upstream dependency). vrblms is currently a pure passthrough
  with no overrides of its own — do VRB-specific branding work there, not
  in Moove directly. Its `config.php` requires
  `$THEME->rendererfactory = 'theme_overridden_renderer_factory';` or the
  site fatals on any page — see `docs/ARCHITECTURE.md`'s theming section
  and `LOG.md`'s 2026-08-20 entry.
- `config.php` / `moodledata/` are never committed. `.gitignore` excludes
  `/config.php` — confirmed not tracked. `moodledata` lives at
  `/var/www/moodledata`, outside the web root.
- `public/local/`, `public/blocks/`, `public/report/` confirmed clean —
  stock core plugins only, no custom `vrblms`-prefixed code yet.

## Incident log

Full detail lives in `LOG.md` — read it before starting new work. Short
version of the pattern to watch for: a correctly-built plugin placed one
directory level too high (i.e. outside `public/`) is silently invisible to
Moodle's plugin scanner, and this has already happened more than once on
this project (once producing an invisible plugin, once producing a
git-tracked stray duplicate). Always verify with the `core_component::
get_plugin_list()` snippet in `Agents.md` after adding any plugin folder,
not just a directory listing.

## Rules

- **Never modify Moodle core.** All custom functionality goes in plugins
  under `public/local/`, `public/blocks/`, `public/report/`, or a theme
  child-folder. If a requirement seems to force a core edit, stop and flag
  it — don't make the edit.
- **Verify, don't recall.** File paths, API signatures, plugin
  compatibility, directory structure — check the actual installed source
  (grep/find/read, or the `core_component` snippet) before trusting general
  Moodle knowledge or training data. This project has been burned by this
  more than once already.
- Purge caches after plugin file changes that don't seem to take effect:
  `docker exec vrb-moodle php admin/cli/purge_caches.php`.
- Don't commit or push without confirming the change works locally first.
- Don't touch hosting-account-level settings (PHP version, document roots,
  SSL) even with server access later — see `docs/DEPLOYMENT.md`.
- Don't upgrade Moodle core without explicit confirmation.
- Don't build certificate-issuing logic until the plugin-vs-badge decision
  in `docs/ARCHITECTURE.md` is resolved.
- Don't hardcode the leaderboard ranking formula — must stay config-driven
  per `docs/PROJECT_CONTEXT.md` and `docs/TASKS.md`.
- Only commit to git when the user explicitly asks.

## Current phase

Working through `docs/TASKS.md` Phase 1 (employee data model / cohorts).
Phase 0 environment verification is complete. Phase 1's cohort test (dual
cohort membership for a test user) is confirmed working — see `LOG.md` for
the exact verification. Remaining Phase 1 work: custom profile fields for
State/City/Region, bulk CSV import via `admin/tool/uploaduser/`. Check in
with the user at phase boundaries — don't run the full task list
unattended in one pass.
