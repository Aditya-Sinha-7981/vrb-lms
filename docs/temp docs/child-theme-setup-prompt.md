# Task: Create theme_vrblms child theme (Step 1 + Step 2 only)

Read AGENTS.md first — pay close attention to the section on Moodle 5.1's
`public/` directory restructure. Every plugin folder in this install lives
under `public/`, NOT at the repo root. Confirm this yourself before
creating anything by running:

```bash
docker exec vrb-moodle ls /var/www/html/public/theme/
```

You should see `moove` listed. Do not proceed until you've confirmed this.

---

## STEP 1 — Create the child theme skeleton

Create a new folder at `public/theme/vrblms/` (confirm this sits alongside
`moove/` and `boost/`, at the exact same directory depth — verify with
`ls` before moving to Step 2).

Inside it, create exactly these two files, with exactly this content.

### File: `public/theme/vrblms/version.php`

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_vrblms';
$plugin->version = 2026082000;
$plugin->requires = 2025100600;
$plugin->maturity = MATURITY_ALPHA;
```

### File: `public/theme/vrblms/config.php`

```php
<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'vrblms';
$THEME->parents = ['moove'];
$THEME->sheets = [];
$THEME->doctype = 'html5';
```

Do not add anything else to these two files yet. Do not create any other
files in this folder yet. Stop after creating these two files and move to
Step 2.

---

## STEP 2 — Verify Moodle detects it, before adding any custom code

Run:

```bash
docker exec vrb-moodle php admin/cli/purge_caches.php
```

Then run this verification check (this is a single command, copy it
exactly as-is, do not modify or retype it):

```bash
docker exec vrb-moodle php -r "define('CLI_SCRIPT', true); require('/var/www/html/config.php'); \$plugins = core_component::get_plugin_list('theme'); var_dump(isset(\$plugins['vrblms']) ? \$plugins['vrblms'] : 'NOT FOUND');"
```

Report the exact output of this command.

- **If it says `NOT FOUND`**: STOP and report back — do not guess at a
  fix. This is the exact same failure mode already documented in LOG.md
  for the Moove theme installation. Check LOG.md's entry about that for
  how it was diagnosed and fixed previously (it was almost always a wrong
  directory depth — the folder was placed one level too high, outside
  `public/`). Report what you find, do not attempt a fix without checking
  in first.

- **If it IS found** (shows a file path instead of `NOT FOUND`), continue:

```bash
docker exec vrb-moodle php admin/cli/upgrade.php --non-interactive
```

Then, in the browser, go to:
**Site Administration → Appearance → Themes → Theme selector**

Set the active theme to **vrblms** (not moove directly — from now on,
`vrblms` is the active theme, and it inherits Moove's look automatically
since it's declared as its parent in `config.php`).

Confirm the site still looks visually identical to how it looked under
Moove directly (same colors, same layout, same fonts). If it looks
broken, unstyled, or different in any way, STOP and report back — do not
attempt fixes without checking in.

---

## Do not proceed further

Do not proceed to any dashboard, brand-tile, or other UI customization
work until Steps 1 and 2 are both confirmed fully working. Report back
exactly what you found at each step, including the raw command output.
