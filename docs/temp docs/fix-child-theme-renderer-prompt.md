# Task: Fix theme_vrblms renderer inheritance (500 error)

## Context

theme_vrblms was created as a child of theme_moove (Steps 1-2 from the
earlier child-theme prompt). Detection and registration both succeeded,
but activating it causes a fatal error on every page:

```
Exception - Call to undefined method core\output\core_renderer::firstview_fakeblocks()
```

Root cause: Moove has a custom renderer class at
`public/theme/moove/classes/output/core_renderer.php` that defines
`firstview_fakeblocks()`, which Moove's own layout files call. By default,
Moodle's `standard_renderer_factory` does NOT walk the parent theme chain
to find custom renderer classes — it only looks in the active theme
itself, then falls back to Moodle core's default renderer, which has no
such method. This is why the site breaks the moment vrblms (with no
renderer of its own) becomes active.

## STEP 0 — Immediate stabilization

Before doing anything else, revert the active theme back to `moove` so
the site is not broken while this gets fixed:

Site Administration → Appearance → Themes → Theme selector → set active
theme back to **moove**.

Confirm the site loads normally again before proceeding.

## STEP 1 — Try the simplest fix first: `$THEME->rendererfactory`

Open `public/theme/vrblms/config.php` and check whether it currently
contains a `$THEME->rendererfactory` line.

If it does NOT, add this line to the file (alongside the existing
`$THEME->name` and `$THEME->parents` lines):

```php
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
```

This is the standard Moodle mechanism for letting a child theme resolve
a parent theme's custom renderer classes — `theme_overridden_renderer_factory`
walks the theme's parent chain looking for renderer classes, whereas the
default factory does not. This is a one-line, low-risk change — do not
proceed to Step 2 until this has been tried and tested.

After adding this line:

```bash
docker exec vrb-moodle php admin/cli/purge_caches.php
```

Then re-activate vrblms as the theme (Site Administration → Appearance →
Themes → Theme selector) and reload the site. Report whether the fatal
error is gone.

**If this alone fixes it — stop here, do not proceed to Step 2.** Update
LOG.md noting this was the fix, and note in ARCHITECTURE.md's theme
section (or wherever the theme architecture is documented) that any
future child theme of a theme with a custom renderer needs this same
`rendererfactory` setting from the start.

## STEP 2 — Only if Step 1 alone does not resolve it

First, inspect Moove's actual renderer file to get its exact namespace
and class declaration — do not assume it matches a guess:

```bash
docker exec vrb-moodle head -20 /var/www/html/public/theme/moove/classes/output/core_renderer.php
```

Report the exact namespace and class declaration lines found.

Using the exact namespace/class name confirmed above, create a new file
at `public/theme/vrblms/classes/output/core_renderer.php`. It should be
an empty passthrough that extends Moove's renderer — do not copy or
reimplement any of Moove's methods, just extend the class so all of
Moove's methods (including `firstview_fakeblocks()`) are inherited
automatically:

```php
<?php
defined('MOODLE_INTERNAL') || die();

namespace theme_vrblms\output;

class core_renderer extends \theme_moove\output\core_renderer {
    // Intentionally empty. This class exists only so vrblms inherits
    // Moove's custom renderer methods (e.g. firstview_fakeblocks()).
    // Do not add methods here unless you specifically intend to
    // override one of Moove's — for a plain passthrough, leave empty.
}
```

If Step 1's inspection command showed a different namespace or class
structure than assumed above (e.g. a different base class name, or
methods split across multiple renderer files), adjust the `extends`
target and namespace to match exactly what was found — do not guess.

After creating this file:

```bash
docker exec vrb-moodle php admin/cli/purge_caches.php
```

Re-activate vrblms and reload the site. Report whether the fatal error
is gone.

## STEP 3 — Verification, required regardless of which step fixed it

Do not consider this done just because the page loads without a fatal
error. Confirm:

1. The site visually looks identical to how it looked under Moove
   directly (same layout, same styling) — this is expected, since
   vrblms should currently be a pure passthrough with no visual changes
   of its own yet.
2. Navigate to at least 3 different page types (front page, a course
   page, the dashboard) to confirm the fix holds across different
   layouts, not just the page you originally tested.
3. Run the cache purge one more time and reload once more, to rule out
   a stale-cache false positive:
   ```bash
   docker exec vrb-moodle php admin/cli/purge_caches.php
   ```

## Reporting

Report back:
- Which step actually fixed it (Step 1 alone, or Step 1 + Step 2).
- The exact contents of the final working config.php and (if created)
  core_renderer.php.
- Confirmation that Step 3's verification passed across multiple page
  types.

Add a LOG.md entry with this information, specifically flagging the
"child theme of a theme with a custom renderer needs rendererfactory
set" lesson for any future child-theme work (e.g. if vrblms is ever
rebuilt, or if a different parent theme is evaluated later).

Do not proceed to any dashboard/brand-tile UI work until this is fully
confirmed fixed and verified.
