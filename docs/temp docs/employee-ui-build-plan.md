# Employee-UI Design-Match Build Plan (`theme_vrblms` / `local_vrblms`)

## Context

The 2026-08-29 design-match audit found the live build is essentially stock
Moove/Boost: no Inter font, `#0f6cbf` Boost-blue primary instead of VRB Navy
`#000B43`, shadowed 8px cards instead of flat 1px-border cards, the
conecti.me footer credit still visible, and no branded treatment on any
employee screen. `docs/design_refer/` holds the target: 9 Tailwind-exported
screens + 3 `DESIGN.md` token docs.

This build gets the **employee-facing** surface as close to that design as
Moodle/Moove reasonably allow, **entirely inside the child theme
`theme_vrblms` ("VRB Learning Platform") and our own `local_vrblms`
plugin** — no Moodle core edits, no edits to `theme_moove`'s own files,
and (per LOG.md 2026-08-20) **no `$THEME->scss` pipeline** — all styling
stays in `$THEME->sheets` plain CSS + Mustache template overrides in the
child theme.

**This session's scope:** the shared token layer + the logged-out index
page + login + "My courses" (brand selection) + the leaderboard.
Course-format module-card grid and the deep quiz-flow restyle are heavier
(renderer/format-plugin territory) and are deferred to a later session —
Phase 5 below records the approach but is **not** part of this pass.

## Design tokens (from the per-screen `code.html` configs — authoritative over `DESIGN.md` prose)

| Token | Value |
|---|---|
| Primary / VRB Navy | `#000B43` (hover `#041C72`), on-primary `#FFFFFF` |
| Page surface | `#F8F9FA` |
| Card / container | `#FFFFFF`, 1px border `#E9ECEF`, **no shadow** (hover lift only: `0 4px 12px rgba(0,0,0,.05)`) |
| Text | `#191C1D` primary, `#454651` secondary, `#757683` muted/icon |
| Brand accents | Veeba `#E31E24`, Wok Tok `#F37021`, Zyro `#008080`, warm `#FED563` |
| Semantic | success `#28A745`, error `#BA1A1A` |
| Font | **Inter** 400/500/600/700 (body 16px) |
| Radius | card **8px**, buttons/inputs **4px**, pills **12px** (NOT 9999px — `DESIGN.md`'s `full: 9999px` and `md: .375` appear in no code export; flagged) |
| Spacing | 4px base, 24px gutter, 40px desktop / 16px mobile margin, 1280px max container |

## Current-state facts (verified this session)

- `theme/vrblms/config.php`: `parents=['moove']`, `rendererfactory='theme_overridden_renderer_factory'`, `sheets=['custom']`, `doctype='html5'`. No `$THEME->layouts`, no `$THEME->scss`.
- `theme/vrblms/` already owns: `layout/frontpage.php` + `templates/frontpage.mustache` (minimal navy-gradient hero, hardcoded `herotitle`), `style/custom.css` (~274 lines of targeted fixes — login bg, nav-transparency fix, quiz `.qtext`/`.formulation`/`.info` fixes, `.moove-info-container` flex fix, admin tree spacing, leaderboard table/own-row/filters), `lang/en/theme_vrblms.php` (only `pluginname`).
- Child theme layout override works by dropping `layout/<name>.php` matching a parent layout name (that's how `frontpage.php` already works). Most non-frontpage pages use Moove's `drawers` layout.
- Moove `brandcolor` config = `#0f47ad` (default) but live `.btn-primary` = `#0f6cbf` → **Moove's `brandcolor`→SCSS `$brand-primary` does NOT reliably reach the child theme's compiled CSS.** Confirms: do the palette in `custom.css`, not via the Moove colour settings.
- `$CFG->defaulthomepage = 3` (HOMEPAGE_MYCOURSES) → employees land on `/my/courses.php` after login. That page = the design's "brand selection" screen (`docs/design_refer/dashboard_vrb_learning_hub/`).
- `/my/courses.php` renders `block_myoverview` "cards" view. Moove already overrides `templates/block_myoverview/view-cards.mustache` (it `{{<}}`-extends `core_course/coursecards`) and `progress-bar.mustache`.
- conecti.me credit lives in `theme_moove/templates/footer.mustache` → `<div class="copyright"><div class="madeby">` + lang string `themedevelopedby`. Removable via CSS (`.copyright{display:none}`) or a `footer.mustache` override.
- `local_vrblms/classes/output/leaderboard_view.php::render_table()` builds a plain `html_table` (`table table-sm vrb-leaderboard-table`). Row objects expose: `rank, userid, fullname, idnumber, state, city, region, score_percent, quizzes_completed, quizzes_total, total_time_seconds`. `leaderboard.php` (pagelayout `report`) loops the viewer's brands + an "Overall" section for employees; admins (`local/vrblms:viewfullleaderboard`) get the filter form.
- Known course IDs (LOG.md): Veeba Onboarding = 2, Wok Tok = 3, Zyro = 4. Brand cohorts: `brand_veeba` / `brand_woktok` / `brand_zyro`.
- No brand logo / VRB wordmark assets in the repo yet. No `theme/vrblms/pix/`.

## Assumptions (confirm or correct before Phase 1)

1. **No brand image assets available yet.** Plan uses a styled **text wordmark** ("VRB LMS" / brand names) + brand-colour blocks in place of logos. Swap in real SVGs later via `theme/vrblms/pix/`.
2. **Relabel the login username field to "Employee Code"** (design says so) — done in the `login.mustache` override, English only.
3. **Session scope = Phases 0–4** (tokens, index, login, my-courses, leaderboard). Phase 5 (course format + deep quiz) is a later session.
4. Per-brand card accent is keyed to the **known course IDs (2/3/4)** — acceptable for a fixed 3-brand internal LMS; documented as environment-specific.
5. No "Corporate Compliance Training" course exists → that button on the brand-selection screen is **omitted** for now (add when the course exists).

---

## Phase 0 — Foundation: token layer, Inter, flat elevation, navy primary, kill conecti

**Files:** `theme/vrblms/style/custom.css` (reorganise + extend), `theme/vrblms/version.php` (bump).

Keep **one** stylesheet (`custom.css`) to avoid multi-sheet cache/order
issues — reorganise it with a `:root` token block at the top and clearly
banigned sections. Do **not** split into multiple `$THEME->sheets` this pass.

1. **Token block** at top of `custom.css`:
   ```css
   :root{
     --vrb-navy:#000B43; --vrb-navy-hover:#041C72;
     --vrb-surface:#F8F9FA; --vrb-card:#FFFFFF; --vrb-border:#E9ECEF;
     --vrb-text:#191C1D; --vrb-text-2:#454651; --vrb-muted:#757683;
     --vrb-veeba:#E31E24; --vrb-woktok:#F37021; --vrb-zyro:#008080; --vrb-warm:#FED563;
     --vrb-success:#28A745; --vrb-error:#BA1A1A;
     --vrb-r-card:8px; --vrb-r-control:4px; --vrb-r-pill:12px;
   }
   ```
2. **Inter**: `@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');` at the very top, then
   `body, .btn, input, select, textarea, h1,h2,h3,h4,h5,h6, .h1,.h2,.h3,.h4,.h5,.h6 { font-family:'Inter',system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }`
   and `body{ font-size:16px; }`.
3. **Surface + flat elevation**:
   `body,#page{ background:var(--vrb-surface); }`
   `.block,.card{ box-shadow:none!important; border:1px solid var(--vrb-border); border-radius:var(--vrb-r-card); background:var(--vrb-card); }`
4. **Navy primary** (CSS overrides, since Moove `brandcolor` doesn't propagate):
   - `.btn-primary{ background:var(--vrb-navy); border-color:var(--vrb-navy); } .btn-primary:hover,.btn-primary:focus{ background:var(--vrb-navy-hover); border-color:var(--vrb-navy-hover); }`
   - `.btn-outline-primary{ color:var(--vrb-navy); border-color:var(--vrb-navy); } .btn-outline-primary:hover{ background:var(--vrb-navy); }`
   - `a{ color:var(--vrb-navy); }` (keep `:hover` legible)
   - `.secondary-navigation{ background:var(--vrb-navy); }` (course nav bar; verify active-item contrast stays white)
   - keep the existing `nav.navbar.fixed-top` white rule; set the "VRB LMS" wordmark text in it to navy
   - buttons/controls radius: `.btn,.form-control,input,select{ border-radius:var(--vrb-r-control); }`
5. **Kill conecti.me credit**: `footer .copyright, footer .madeby{ display:none!important; }`. Leave the rest of the footer; optionally lighten it later.
6. Keep every existing rule in `custom.css` (login, quiz, nav, admin-tree, leaderboard) — just re-slot them under the new section banners and let them consume the tokens where trivial.

**Touches every screen.** Do this first; everything else builds on it.

## Phase 1 — Index / logged-out landing page

**Files:** `theme/vrblms/layout/frontpage.php`, `theme/vrblms/templates/frontpage.mustache`, `theme/vrblms/lang/en/theme_vrblms.php`, `theme/vrblms/pix/` (new wordmark SVG placeholder + favicon), `custom.css` (frontpage section).

Design language: no dedicated export, so follow the login + brand-card
visual system — a **full-viewport navy hero**, centered, employee-focused.

1. `frontpage.mustache` (already ours): inside `.vrb-hero`, render:
   - VRB wordmark (inline `<svg>` text mark or `{{pix}}`/`<img>` from `theme/vrblms/pix/logo`), white
   - `<h1>` hero title + `<p>` subline (from lang strings `herotitle` / `herosubtitle`, passed via `frontpage.php` `$templatecontext`)
   - a primary CTA: `<a class="btn btn-lg btn-light-on-navy" href="{{config.wwwroot}}/login/index.php">` "Employee sign in"
   - small "Secure internal portal" line with a lock glyph
   - **Keep `{{{ output.main_content }}}`** in `#region-main` (LOG.md gotcha: the placeholder must render or `core_renderer::header()` fatals) but wrap that region in a `visually-hidden` / `d-none` container so the inherited course-search block doesn't show.
2. `frontpage.php`: replace hardcoded `'herotitle' => ...` with
   `get_string('herotitle','theme_vrblms')` + add `'herosubtitle'`; add
   `'loginurl'`, `'isloggedin'` (show a "Go to my courses" button instead
   of "Sign in" when already logged in).
3. `custom.css` `.vrb-hero` section: navy background per token (`#000B43`
   flat, or `linear-gradient(160deg,#000B43,#041C72)`), Inter, responsive
   `clamp()` title, keep the existing `@keyframes vrb-hero-fade-up` +
   `prefers-reduced-motion` fallback, style the CTA (white bg / navy text,
   4px radius) and the secure-portal line.
4. `pix/`: add a simple VRB wordmark SVG (text-based placeholder is fine)
   and a `favicon`. Note in LOG that real brand art is still pending.
5. Verify the "Log in" path still works from this page (draw/navbar wiring
   inherited from Moove — already confirmed working for the current hero).

## Phase 2 — Login page

**Files:** `custom.css` (login section — extend what's there), `theme/vrblms/templates/core/login.mustache` (new override — copy `theme_moove/templates/login.mustache` as the base), `theme/vrblms/lang/en/theme_vrblms.php`.

**Step 2a (CSS only — ship first):**
- `body#page-login-index #page{ background:var(--vrb-surface); }` (already close)
- `.login-container`: `max-width:420px; margin:auto; border:1px solid var(--vrb-border); border-radius:var(--vrb-r-card); box-shadow:0 4px 12px rgba(0,0,0,.05); padding:2rem;`
- navy 4px top accent bar: `.login-container::before{ content:""; display:block; height:4px; background:var(--vrb-navy); margin:-2rem -2rem 1.5rem; border-radius:var(--vrb-r-card) var(--vrb-r-card) 0 0; }`
- heading: navy, Inter 600, ~22px
- inputs: 4px radius (token), 1px `--vrb-border`, navy focus ring
- primary button: full width, navy (inherits from Phase 0) — drop the current `#2a4494` login-specific override
- add a "Secure internal portal" helper line under the form via `::after` or template

**Step 2b (template override — optional this session):**
`templates/core/login.mustache` to add: VRB logo above the card, relabel
the username field to **"Employee Code"** (`get_string` from theme lang or
inline), Material/FontAwesome icons in the field adornments, a
password show/hide toggle, a small copyright/version footer line. Keep all
Moodle form field names + hidden inputs intact (copy from Moove's template,
add markup around them only).

## Phase 3 — "My courses" / Brand selection (`/my/courses.php`)

**Target:** `docs/design_refer/dashboard_vrb_learning_hub/screen.png`.

**Files:** `theme/vrblms/templates/block_myoverview/view-cards.mustache` (new override — copy Moove's, which `{{<}}`-extends `core_course/coursecards`), possibly `theme/vrblms/templates/block_myoverview/main.mustache` (new — to inject the "Select your brand module" heading + subtitle), `custom.css` (dashboard section), `theme/vrblms/lang/en/theme_vrblms.php`.

1. **Card restyle** (`view-cards.mustache` override + CSS):
   - flat card: 1px `--vrb-border`, 8px radius, no shadow, hover lift
   - **brand-colour top accent bar** (4px) keyed to course id:
     `[data-course-id="2"] .dashboard-card{ border-top:4px solid var(--vrb-veeba); }`
     `[data-course-id="3"]{ …--vrb-woktok }` `[data-course-id="4"]{ …--vrb-zyro }`
     (documented as environment-specific; revisit if course IDs change)
   - hide the kebab / course-action menu (`.dashboard-card-footer.menu{ display:none; }`)
   - replace the auto-generated pattern banner with a solid brand-colour block or the brand wordmark (needs assets — placeholder = brand-colour block with brand name)
   - progress: keep Moodle's `%` value, restyle the bar to the brand colour + `--vrb-border` track; "N of 5" style label is an enhancement (needs activity-count logic) — note, don't block on it
2. **Heading + subtitle**: check core's `block_myoverview/main.mustache`
   during execution; override it in the child theme to prepend
   `<h2>Select your brand module</h2><p class="text-muted">Access training
   tailored to each brand you're part of.</p>` (strings from theme lang).
   If overriding `main.mustache` is awkward, fall back to a CSS
   `::before` content injection on the block header. Keep the core "My
   courses" H1 or hide it — decide visually.
3. Multi-brand employees (e.g. `kavitayadav`) already get one card per
   enrolled brand for free — verify the 3-up grid + accents render for
   both single-brand (`rksharma`) and multi-brand accounts.
4. "Corporate Compliance Training" button — **omitted** (no such course);
   add later.

## Phase 4 — Leaderboard visual pass (highest value, zero core coupling)

**Target:** `docs/design_refer/leaderboard_vrb_learning_hub/`.

**Files:** `local_vrblms/classes/output/leaderboard_view.php` (rewrite
`render_table()` + `render_filter_form()` markup), `local_vrblms/leaderboard.php`
(subtitle + heading string), `local_vrblms/lang/en/local_vrblms.php`
(heading/subtitle strings), `local_vrblms/version.php` (bump),
`custom.css` (leaderboard section — replace the current one).

Everything here is in our own plugin + our theme's CSS — no Moove/Boost/core
template touched. This screen was represented to the client as ahead of
scope, so its polish matters most.

1. **`render_table()`** — emit custom markup instead of `html_table`:
   - outer `.vrb-lb` container: `#FFFFFF`, 1px `--vrb-border`, 8px radius, `overflow:hidden`; keep a `.vrb-leaderboard-table-wrap{ overflow-x:auto }` inside for mobile
   - `<thead>` row: `background:#F3F4F5`, `--vrb-muted` uppercase `label-md` text, `padding:16px`
   - **Rank cell**: `.vrb-lb-rank` circular badge; rank 1 = `--vrb-warm` bg, 2 = silver `#C5C5D3`, 3 = bronze `#E0A96D`, 4+ = plain `--vrb-border` circle with `--vrb-text-2`
   - **Name cell**: `.vrb-lb-avatar` initials circle (derive from `$row->fullname`) + name; keep the `title=idnumber` affordance
   - **Location**: unchanged (`city, state`)
   - **Score**: bold, right-aligned, `--vrb-text`
   - **Modules**: `x/y` (`quizzes_completed`/`quizzes_total`)
   - **Time**: `format_seconds()` unchanged
   - **Own row** (`$highlightuserid`): keep the `.vrb-leaderboard-own-row`
     class but change its CSS from `#fff3cd` to navy tint
     `background:rgba(0,11,67,.05); border-left:4px solid var(--vrb-navy);`
     (dark-mode variant kept)
2. **Section titles** (`leaderboard.php` `$OUTPUT->heading(..., 3)` for
   each brand + "Overall"): wrap/replace with a `.vrb-lb-section` title —
   brand name + a short brand-colour underline/dot. Since `leaderboard.php`
   already loops `api::get_user_brands()`, pass the brand idnumber through
   so the title can carry the right accent colour.
3. **Page heading + subtitle**: change `get_string('leaderboard', …)` used
   in `$PAGE->set_heading` / `$OUTPUT->heading` to "Regional Leaderboard"
   (new string, keep nav-link string as "Leaderboard"); echo a
   `<p class="text-muted">See how you rank across the organisation.</p>`
   subtitle after the heading in `leaderboard.php`.
4. **Admin filter form** (`render_filter_form()`): wrap in a
   `.vrb-lb-filters-card` (white, 1px border, 8px radius, padding); style
   the selects to 4px radius / `--vrb-border`; keep the GET-based
   auto-submit behaviour (no AMD — per the file's own note). The "All
   Brands / My Brand" toggle from the design maps to the existing brand
   `<select>` + the employee-view sections; a real toggle is optional and
   can be a later enhancement.
5. Keep `require_login()`, capability check, and `pagelayout('report')`
   as-is.

---

## Phase 5 — Course module list + quiz flow (NOT this session — recorded for later)

- **Course/module list** (`docs/design_refer/veeba_learning_modules_*`): the
  card-grid-with-thumbnails/status/progress design needs a course-format
  renderer override or a `format_vrblms` plugin — `core_courseformat`
  output is upgrade-fragile (this repo's rules flag it). This session does
  **nothing** here. Later, prefer a **CSS-only lift** of the existing
  Topics accordion first (brand accent on `.course-content .section`,
  flat-card section styling, restyle the `.availabilityinfo` lock notice);
  treat the full card grid as a separate scoped decision.
- **Quiz flow** (instructions/attempt/review/results): keep the existing
  `custom.css` fixes. Later: a distraction-free treatment on
  `#page-mod-quiz-attempt` (hide course-index drawer + side blocks,
  constrain `#region-main` to ~800px, navy accent bar) via CSS; restyle
  the quiz view page + review summary via CSS; a `mod_quiz` renderer
  subclass in the theme for the metadata grid / score ring is
  MODERATE and deferred. **Do not** attempt per-option card-radio
  restyling of question-engine markup (brittle, cosmetic).

---

## Execution order & cache/versioning

1. Phase 0 → purge caches → verify globally.
2. Phase 1 (index) → 2 (login) → 3 (my courses) → 4 (leaderboard).
3. After **every** file change: `docker exec vrb-moodle php admin/cli/purge_caches.php`.
4. Bump `theme/vrblms/version.php` `$plugin->version` once per work session;
   bump `local_vrblms/version.php` when its templates/strings change; run
   `docker exec vrb-moodle php admin/cli/upgrade.php --non-interactive`.
5. `php -l` every changed `.php` before loading it in a browser.

## Verification

Run through the Chrome extension (localhost:8080), at **desktop (1512)**
and **mobile (390×844)** widths, for each of:

| Page | URL | Accounts |
|---|---|---|
| Index | `/` | logged out; also logged-in |
| Login | `/login/index.php` | logged out |
| My courses | `/my/courses.php` | `rksharma` (1 brand), `kavitayadav` (3 brands) |
| Leaderboard (employee) | `/local/vrblms/leaderboard.php` | `rksharma`, `kavitayadav` |
| Leaderboard (admin) | same | admin (needs admin creds from user) |

For each: screenshot vs the matching `docs/design_refer/*/screen.png`, plus
`getComputedStyle` spot-checks:
- `body` `font-family` starts with `Inter`, `font-size` 16px
- `.btn-primary` `background-color` = `rgb(0, 11, 67)`
- `.card` / `.block` — `box-shadow: none`, `border: 1px solid rgb(233,236,239)`, `border-radius: 8px`
- leaderboard own-row `background` = navy tint, `border-left` navy 4px
- no `.copyright`/conecti credit in the footer DOM (or `display:none`)
- brand accent bar colour on each `/my/courses.php` card matches the brand

Regression checks (must still work): employee login → forced-password flow,
Veeba Module-2 lock on the course page, quiz attempt/review pages render
(the existing `.qtext`/`.formulation` fixes intact), `nav.navbar.fixed-top`
still solid on scrolled pages.

## LOG.md entry

Append one entry per phase (or one per session) per repo convention —
what changed, why, exact files, verification done, gotchas.
