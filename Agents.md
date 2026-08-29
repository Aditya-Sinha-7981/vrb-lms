# AGENTS.md — Read This First, Before Anything Else

This file exists because AI agents (Claude Code, Codex, or any other tool
working on this repo) default to training knowledge about "Moodle" in
general, which is frequently WRONG for this specific installation. Moodle
5.1 changed its directory structure significantly, and outdated assumptions
here have already caused real, time-consuming bugs. Read this file
completely before writing, moving, or reasoning about any file path.

## The one fact that matters most: the `public/` restructure

**As of Moodle 5.1, the entire web-facing application — including ALL
plugin directories — lives under `moodle/public/`, not at the Moodle root.**

This is a real, confirmed structural change from older Moodle versions
(4.x and earlier), which is what most training data and most blog posts/
tutorials describe. If a source describes paths like `moodle/theme/`,
`moodle/local/`, `moodle/blocks/` directly off the repo root — that source
is describing an OLDER Moodle version and is WRONG for this installation.

**Confirmed correct paths for this installation:**
```
moodle/
├── config.php              ← at the true root, NOT inside public/
└── public/
    ├── admin/
    ├── theme/               ← ALL themes go here (confirmed: boost,
    │                          classic, moove all live here)
    ├── local/               ← custom local plugins go here
    ├── blocks/              ← custom block plugins go here
    ├── report/              ← custom report plugins go here
    ├── mod/
    └── lib/
```

**Before creating ANY new plugin folder, verify the correct parent
directory by checking what's already there — don't assume:**
```bash
docker exec vrb-moodle ls /var/www/html/public/theme/
docker exec vrb-moodle ls /var/www/html/public/local/
docker exec vrb-moodle ls /var/www/html/public/blocks/
```

This mistake already cost real time once on this project (a correctly
built, correctly versioned theme was placed one directory level too high
and was silently invisible to Moodle's plugin scanner for an entire
session). Do not repeat it.

## File locations — where things go in this repo

- **`LOG.md`** lives at the **repo root**, and only there. It is the one
  canonical, append-only work log for this project — read it (per the
  required-reading order below) and append to it after any meaningful unit
  of work. **Never create a log file anywhere else** (e.g. `docs/LOG.md`).
  This has already happened once: a separate agent session logged its work
  to `docs/LOG.md` instead of the root file, which went unnoticed until a
  later session found it, manually merged its content into the real
  `LOG.md`, and deleted the stray copy. If you're about to write a log
  entry and don't see `LOG.md` at the repo root, stop and check you're not
  about to recreate this mistake — do not start a new log file to work
  around it.
- **`docs/`** holds the durable, canonical reference docs for this
  project — the ones every session is expected to read and that stay
  accurate over time: `PROJECT_CONTEXT.md`, `ARCHITECTURE.md`,
  `DEPLOYMENT.md`, `TASKS.md`, `CSV_IMPORT_SPEC.md`,
  `moodle-feasibility-audit.html`, and similar. Treat this folder as
  curated — don't drop scratch files, one-off prompts, or generated sample
  data here.
- **`docs/temp docs/`** holds temporary/working artifacts that support a
  specific task but aren't part of the durable doc set: prompt files used
  to brief a particular agent session on a narrow piece of work (e.g.
  `child-theme-setup-prompt.md`, `fix-child-theme-renderer-prompt.md`,
  `csv-import-spec-prompt.md`), and generated/sample data such as
  `vrb_employees_dummy.csv`. These are not required reading and aren't
  guaranteed to stay accurate — don't treat anything in here as a source
  of truth the way `docs/`'s core files are. When creating a new working
  file for a narrow task, put it here, not in `docs/` directly.
- If you're unsure whether a new `.md` (or data) file you're about to
  create is "core" or "temp," ask: will a future session need to read this
  as ongoing reference (→ `docs/`), or does it only matter for the
  duration of one task/one prompt (→ `docs/temp docs/`)?

## Required reading, in this order, before starting any task

1. **This file (AGENTS.md)** — environment facts, read first.
2. **`docs/PROJECT_CONTEXT.md`** — client requirements, business context.
3. **`docs/ARCHITECTURE.md`** — verified technical findings from the
   feasibility audit (what's confirmed vs. still open) and the plugin
   architecture decisions.
4. **`docs/DEPLOYMENT.md`** — git/hosting workflow, what NOT to touch.
5. **`docs/TASKS.md`** — the current phased task list.
6. **`docs/moodle-feasibility-audit.html`** — the full original audit, for
   deep reference when a task needs more detail than ARCHITECTURE.md's
   summary provides.

## Verified environment facts (as of this writing)

- **Moodle version:** 5.1.5+ (Build: 20260714), version `2025100605.05`,
  branch `MOODLE_501_STABLE`. **Do not upgrade this.** Every finding in
  ARCHITECTURE.md was verified against exactly this version. A newer
  version (e.g. 5.2.x) has different PHP requirements, a different
  dependency-installation model (Composer-based), and at least one
  documented case of theme breakage on this exact upgrade path. If asked
  to "update Moodle," flag this constraint and ask for explicit
  confirmation before proceeding.
- **Local dev environment:** Docker (`docker compose up -d` from
  `~/Projects/vrb-lms/`), container name `vrb-moodle` (app) and
  `vrb-moodle-db` (MariaDB 11.4), served at `http://localhost:8080`.
- **Theme:** Moove (`theme_moove`, release `5.1.2`, explicitly declares
  Moodle 5.1 support) is installed and active, correctly placed at
  `public/theme/moove/`.
- **`config.php` and `moodledata/` are never committed to git.** Moodle's
  own `.gitignore` already excludes `config.php` — do not remove that
  exclusion, and do not commit either under any circumstance.
- **`moodledata` path:** `/var/www/moodledata` (outside the web root, per
  Moodle's standard security requirement).

## General rules for working in this codebase

- **Never modify Moodle core.** All custom functionality goes in proper
  plugins (`local/`, `block/`, `report/`, or a theme child-folder) under
  `public/`. If a requirement seems to force a core edit, stop and flag it
  rather than making the edit.
- **Verify against the actual installed source before trusting general
  Moodle knowledge**, especially for: file paths, API signatures, plugin
  compatibility, and directory structure. This project has already been
  burned twice by outdated general knowledge (once on the `public/`
  restructure, once on assuming a downloaded theme zip matched the
  installed version without checking `version.php` directly). When in
  doubt, check the actual file/database/API in this repo rather than
  answering from training data.
- **After placing any new plugin folder, always verify Moodle actually
  detects it before assuming the work is done:**
  ```bash
  docker exec vrb-moodle php -r "
  define('CLI_SCRIPT', true);
  require('/var/www/html/config.php');
  \$plugins = core_component::get_plugin_list('THEME_TYPE_HERE');
  var_dump(\$plugins);
  "
  ```
  (substitute `theme`, `local`, `block`, `report` etc. as appropriate)
  followed by:
  ```bash
  docker exec vrb-moodle php admin/cli/upgrade.php --non-interactive
  ```
  A plugin that isn't detected here is not installed, regardless of how
  correct the files look.
- **Purge caches after any plugin file change that doesn't seem to take
  effect:**
  ```bash
  docker exec vrb-moodle php admin/cli/purge_caches.php
  ```
- **Do not commit or push without confirming the change works locally
  first**, per DEPLOYMENT.md's branching rules.
- **Do not touch hosting-account-level settings** (PHP version, document
  roots, SSL) even if given server access later — see DEPLOYMENT.md for
  why this specifically matters on this project.

## What NOT to do

- Do not assume any Moodle file path from memory/training data without
  verifying it against this actual installed codebase first.
- Do not download or recommend a theme/plugin without checking its
  `version.php` `$plugin->requires` / `$plugin->supported` fields directly
  against this installed version (`2025100605.05` / branch `501`) — do not
  trust a marketplace listing's version label alone without spot-checking
  the file itself.
- Do not upgrade Moodle core without explicit confirmation.
- Do not build certificate-issuing logic until the plugin-vs-badge
  decision in ARCHITECTURE.md is resolved.
- Do not hardcode the leaderboard ranking formula — it must be
  config-driven per ARCHITECTURE.md and TASKS.md.
- Do not introduce a persistent left-hand sidebar nav for the
  employee-facing UI, and do not add a `$THEME->layouts` override / new
  layout file in `theme_vrblms` to get one. Some `docs/design_refer/`
  mockups (e.g. `veeba_learning_modules_*`) show a 240px dark left rail;
  the client-approved direction is to keep Moove's existing top navbar +
  drawers chrome and brand it via `custom.css` + Mustache overrides only.
  Confirmed with the user 2026-08-30 (Employee-UI Phase 5).