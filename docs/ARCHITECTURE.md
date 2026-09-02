# VRB LMS — Technical Architecture

This document summarizes the verified feasibility findings and the architecture
decisions built on top of them. Everything marked VERIFIED was checked against
the actual installed Moodle 5.1.5 source (branch MOODLE_501_STABLE) — not
assumed from documentation or vendor claims. Everything marked OPEN is a
genuine unresolved item — do not silently resolve these without flagging it.

## Golden rule: DO NOT MODIFY MOODLE CORE

All custom functionality lives in proper Moodle plugins, using Moodle's
documented extension points. Never edit files under core paths (`admin/`,
`lib/`, `mod/`, core `blocks/`, core `theme/boost/`, etc.) directly. If a
requirement seems to force a core modification, stop and flag it — that's a
blocker-level finding requiring a rethink, not something to route around by
editing core.

## Employee accounts & data model — VERIFIED

- **Employee Code as login identifier:** implement as Moodle's native
  `username` field. VERIFIED that Moodle usernames get force-lowercased and
  stripped of most special characters — store the human-readable Employee
  Code in the `idnumber` field, and derive a sanitized `username` from it
  during import (don't assume the raw HR-provided code survives untouched).
- **Brand modeling:** use Moodle **Cohorts** (`lib/cohortlib.php`), one
  cohort per brand (`brand_veeba`, `brand_woktok`, `brand_zyro`). VERIFIED
  (via the actual unique-index definition on `cohortid, userid`, not
  `userid` alone) that a single user can belong to multiple cohorts
  simultaneously — so an employee with access to more than one brand is a
  member of more than one Brand cohort at once. Cohorts are the right fit
  here specifically because Brand access needs cohort-level features
  (e.g. `enrol_cohort` for automatic course-category enrolment) — nothing
  else in the employee data model needs that.
- **State/City/Region modeling — NOT cohorts.** Corrected 2026-08-19: an
  earlier version of this doc described Region as a cohort alongside
  Brand, which was inconsistent with treating State/City/Region as a
  hierarchy (Brand → State → City) for the leaderboard. Cohorts are flat
  membership sets with no parent/child relationship to each other, so they
  don't fit a hierarchy — modeling State/City/Region as cohorts as well
  would mean the hierarchy exists only informally, in naming convention,
  with nothing enforcing it. State, City, and Region are all **user
  profile fields** instead: City reuses Moodle's standard built-in `city`
  user field (do not create a duplicate custom field with that shortname —
  it collides); State and Region are custom profile fields, shortnames
  `state` and `region` exactly (`user/profile/`), not a bespoke separate
  table, unless reporting/query needs prove otherwise during
  implementation. Dual/multi-cohort membership only applies to Brand — it
  does not apply to Region or any other location field.
- **Bulk import:** Moodle's native `admin/tool/uploaduser/` supports CSV
  import with custom profile fields and cohort assignment in one pass —
  confirm the exact column/format requirements against the installed
  version before building the HR import template.

## Learning structure mapping — DECISION MADE, confirm before building at scale

Recommended mapping (Option B from the audit): **1 Moodle Course = 1
Category** within a brand, with Sections/Activities representing the
individual learning modules within that category. Brands themselves are
represented as Course Categories.

This was chosen over alternatives (e.g., 1 course per brand with sections per
category) for simplicity of content management and reporting. Revisit only if
real content volume from the client makes this mapping awkward once actual
brand/category content arrives.

## Quiz gating (module locking) — VERIFIED AND EMPIRICALLY TESTED (Phase 2)

VERIFIED via `lib/completionlib.php` and `mod/quiz` source: the same
`COMPLETION_COMPLETE_PASS` constant is used consistently across grading,
completion, and availability-restriction code. This confirms the intended
chain — **Activity Completion + completion-linked Restrict Access conditions
+ quiz pass grade setting** — is genuinely wired together end-to-end, not
three coincidentally-compatible pieces.

Implementation: each module's quiz activity sets a pass grade; the next
module/section uses a Restrict Access condition keyed to
"quiz X: complete and passed." Pure configuration in the Moodle UI, zero
plugin development required.

**2026-08-19: tested end-to-end as a real (non-admin) employee account, not
just configured and assumed to work** — see `LOG.md` for the full
verification record. Built one real test brand structure (Veeba) and
confirmed: an employee failing Module 1's quiz sees Module 2 stay locked
with the restriction reason shown on the course page, and passing it
unlocks Module 2 live, in the same session, without needing a fresh login.
Also confirmed the employee's course list only ever shows brand-relevant
content (cohort-based enrolment via `enrol_cohort`, not manual per-user
enrolment).

### Exact settings combination (replicable for Wok Tok, Zyro, and any future module pair)

**Course structure** (per the mapping decision above): one Course Category
per brand → one Course per learning category within that brand → one
Section per module within that course.

**On the "gate" quiz (e.g. Module 1's quiz), under Activity completion:**
- *Completion tracking:* "Students must receive a grade to complete this
  activity" (this is `completion = COMPLETION_TRACKING_AUTOMATIC` +
  `completionusegrade = 1` under the hood — i.e. the module's completion
  state is driven by whether the student has a grade, not by "student can
  manually mark this done").
- *Require passing grade:* enabled (this is `completionpassgrade = 1`, and
  requires a "Grade to pass" to be set on the quiz's own grade item —
  without a grade-to-pass, this checkbox has nothing to key off and gets
  silently forced back off, so set the pass grade first).
- *Grade to pass* (on the quiz's Grade settings, not the completion
  settings): e.g. `60` out of the quiz's max grade of `100`, matching
  PROJECT_CONTEXT.md's example 60% passing threshold — client still needs
  to confirm the real number per brand/module.

**On the section/module that should stay locked (e.g. Module 2's
section), under Restrict access:**
- Add restriction → **Activity completion** → select the gate quiz (Module
  1's quiz) → condition dropdown set to **"must be complete with a
  required passing grade"** (this writes an availability condition with
  `"type":"completion"` and `"e":2`, where `2` is the
  `COMPLETION_COMPLETE_PASS` constant — the `"e":1` "must be marked
  complete" option is NOT sufficient on its own, since a quiz can be
  "complete" by exhausting all attempts without ever passing).
- Leave the eye icon in its default **shown/greyed-out** state (`"showc":
  true` in the underlying JSON), not hidden entirely — this is what makes
  the lock visible to the employee ("Not available unless: The activity
  Module 1 Quiz is complete and passed") instead of the module vanishing
  without explanation. TASKS.md's Phase 2 test relies on the employee
  being able to *see* that Module 2 is locked, so don't switch this to
  hidden.

**Brand-gated enrolment**, consistent with "Brand modeling" above: the
course uses an **enrol_cohort** enrolment method instance pointed at the
brand's cohort (e.g. `brand_veeba`), not manual per-user enrolment.
Anyone added to the Brand cohort gets course access automatically; this
was verified by running the cohort sync and confirming all Veeba-cohort
employees from the Phase 1 CSV import appeared as enrolled without being
added individually.

Also VERIFIED native: configurable pass percentage, max attempts, question
order randomization, question selection randomization — all standard quiz
settings.

## Leaderboard data availability — VERIFIED

- Quiz attempt data (attempt number, per-attempt score, first/best attempt,
  pass/fail, start/finish timestamps) lives in Moodle's quiz attempt tables
  and is accessible via the gradebook API / quiz attempt classes — prefer
  these APIs over raw table queries where a documented API path exists.
- **Completion speed is available for free** — VERIFIED that
  `timefinish - timestart` sits directly on the attempt row. Does not need to
  be derived or estimated.
- No single official API returns "leaderboard-ready" data pre-joined to
  cohort/profile info — this join is custom, see Custom Plugins section below.

## Reporting — architectural decision

Regional Leaderboard, Regional Reporting, and Top-Performer Certificate
Qualification should be built as **one shared service/query layer**, consumed
by three different presentation layers (a block for the leaderboard, a report
page for admin reporting, a scheduled task for certificate qualification) —
not three independent implementations of the same underlying join.

ProPanel (LearnDash's reporting addon) is NOT part of this stack — that
research was specific to the LearnDash evaluation that was ultimately
rejected. Not applicable here. Note this in case old research surfaces during
work — it does not apply to Moodle.

## Certificates — RESOLVED 2026-09-02: custom `local_vrbcert` plugin, PDF

**Decision (user, 2026-09-02):** a fully custom, self-coded plugin issuing
**PDF** certificates. Not `mod_customcert` (its activity-module model —
course context, `cmid`, completion-triggered issuance — fights our
ranking-driven, cross-course trigger, and it carried a real Moodle-5.1.5
third-party compatibility risk). Not core badges (the client wants a
printable PDF, not an OpenBadge).

Built as `public/local/vrbcert/` (see `LOG.md` 2026-09-02 and
`docs/temp docs/vrbcert-plugin-build-plan.md`):

- **Engine:** Moodle's bundled TCPDF wrapper `\pdf` (`lib/pdflib.php`) —
  no external dependency. Fixed A4-landscape code-drawn layout; swappable
  wording, signatory, and per-brand name + band colour via settings.
  Optional background-image artwork is a deferred add (client art pending).
- **Data source:** consumes `\local_vrblms\api` (`get_leaderboard` per
  Brand, `get_overall_leaderboard`) **exactly as-is** — every returned row
  is a qualifier, no re-ranking/re-filtering; `rank`/`score_percent` are
  copied onto the PDF for display only. `local_vrblms` was not modified.
- **Two independent tracks:** per-brand Top N (default 3) and overall Top N
  (default 10), both config-driven. An employee qualifying in several
  brands and/or overall gets one certificate per (brand-or-overall,
  period) — multiple certificates per period is expected.
- **Idempotent issuance** via `local_vrbcert_issued` (unique on
  `userid, brandkey, period`; PDFs in the File API). `issuer::run()` skips
  existing, `$reissue` replaces, a new `period` starts a fresh cycle.
- **Trigger:** `\local_vrbcert\task\issue_certificates` scheduled task,
  **disabled by default** — issuance cadence is an open business decision;
  run manually (admin "Issue now" page, or the CLI task) meanwhile.

**Still open (not build blockers, need client input before go-live):** the
final qualification rule (the two-track Top-N defaults are placeholders),
the certificate artwork, and the issuance cadence.

## Custom plugin architecture

Three custom local/block/report plugins, each owning a distinct
responsibility, all consuming a shared internal data-access layer:

- **`local_vrblms`** — the shared service layer. Owns the cross-table query
  logic joining quiz attempt/completion data to cohort/profile data (Brand,
  Region). Exposes internal APIs that the other plugins consume. This is
  where ranking-formula configurability should live (see PROJECT_CONTEXT.md
  — ranking formula is not finalized, so this needs to be a config-driven
  layer, not a hardcoded SQL query for one specific formula).
- **`block_vrblms_leaderboard`** — front-end leaderboard widget. Renders
  ranked lists with Brand/State/City filter dropdowns. Reads from
  `local_vrblms`, does not duplicate its query logic.
- **`report_vrblms`** — admin reporting view. Filterable/exportable by
  Brand/State/City/Region. Also reads from `local_vrblms`.
- **Certificate trigger logic** — wherever this ends up living (a scheduled
  task most likely), it also reads ranking results from `local_vrblms` to
  determine who qualifies, then calls into whichever certificate mechanism
  is chosen (see Certificates section above).
- **Employee/HR import** — likely a `local_vrbemployee` plugin or an
  extension of the native bulk-upload tool, handling Employee Code →
  username/idnumber mapping and cohort assignment from the HR CSV format.

## Frontend / theming — child theme created and VERIFIED (2026-08-20)

**`theme_vrblms` is the active theme**, not `moove` directly. Confirmed via
`$CFG->theme = 'vrblms'`. It's a child of Moove
(`public/theme/vrblms/`, `$THEME->parents = ['moove']`), currently a pure
passthrough with no visual/behavioral overrides of its own yet — Moove
remains the actual source of styling/layout, untouched, as an upstream
dependency. VRB-specific branding/dashboard work should happen in
`theme_vrblms`, not by editing `theme_moove`'s files directly, consistent
with this repo's "never modify a dependency directly" posture.

**Load-bearing gotcha, VERIFIED the hard way:** any child theme of a parent
that defines its own custom renderer class (Moove does, at
`theme/moove/classes/output/core_renderer.php`) will fatal site-wide the
moment it's activated, unless its `config.php` sets:
```php
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
```
Without this, Moodle's default `standard_renderer_factory` does not walk
the parent theme chain to find custom renderer methods, and any call to
one (e.g. Moove's `firstview_fakeblocks()`, called from Moove's own layout
files) fatals with "Call to undefined method
core\output\core_renderer::firstview_fakeblocks()". `theme_vrblms`'s
`config.php` already has this line — do not remove it, and apply the same
line to any other future child theme of Moove (or of any theme with a
custom renderer). Full incident detail in `LOG.md`'s 2026-08-20 entry.

Still open, not yet needed: whether the AMD JS build pipeline (`grunt`) is
functional in the local Docker environment — revisit before any JS-level
theme customization.

### Login/post-login flow — usable-now pass, 2026-08-20

Goal at this stage is a working flow, not final visuals — a full design
pass is explicitly deferred. Two decisions, both theme-layer only, no new
plugin, no core edits:

1. **Custom CSS injection:** `$THEME->sheets = ['custom'];` in
   `theme/vrblms/config.php`, backed by a plain (non-SCSS)
   `theme/vrblms/style/custom.css`. Confirmed this legacy-named but
   still-active mechanism (`theme_config::$sheets`, resolved in
   `lib/classes/output/theme_config.php`) works correctly for a Boost-family
   child theme in this Moodle version — safer and far lower-risk than
   touching the SCSS/`$THEME->scss` pipeline, which is exactly the layer
   that caused the `firstview_fakeblocks()` outage earlier the same day
   (see `LOG.md`). Currently used to neutralize Moove's default orange
   login background (the actual pattern lives on `#page`, not `body` or
   `.login-wrapper` — found by inspecting computed styles directly, not
   guessed) in favor of a plain neutral one, and to lightly polish the
   course-card grid. Extend this same file for future generic polish;
   don't reach for SCSS/preset overrides unless a real design pass
   specifically requires it.
2. **Post-login landing page = Moodle's own "My courses" grid**
   (`$CFG->defaulthomepage = HOMEPAGE_MYCOURSES`, i.e. `3`, set via
   `set_config()`). No custom PHP page, no custom redirect logic. Each
   enrolled course is already a category = Brand, so for any employee
   enrolled in more than one brand, this card grid **is** a functional
   brand-tile chooser today — one card per brand, click through to that
   brand's course. For a single-brand employee it's a one-card grid,
   which is still a normal, working landing page, not a broken/empty
   state. This was deliberately chosen over building a bespoke
   "brand-tile" PHP page or block: it reuses a page Moodle already renders
   correctly (styling, responsiveness, filtering) instead of adding new
   surface area to get right. Revisit only when the later full design
   pass wants true brand-specific tile art/layout beyond what course
   summary images can express.

### Admin/settings tree alignment — narrowed to employee-facing pages only, 2026-08-20

An initial pass fixed `admin/search.php`'s (Site Administration overview)
category-label column alignment. **That fix was explicitly reverted the
same day** at the user's direction: priority is the employee quiz-taking
flow, not admin-only screens. The sidebar "Navigation"/"Settings" tree
block fix (mid-word wrapping, misaligned icons — visible to admin/teacher
roles on course/activity pages) was left in place since it's harmless and
distinct from the admin overview page, but it has no effect on the
employee experience either way (employees never see that block). Full
detail, including a gotcha about Moodle's admin pages rendering an
**off-screen duplicate** of their nav tree (easy to mistake for the real,
visible one when inspecting via DOM queries), in `LOG.md`'s matching
2026-08-20 entries.

### Quiz-taking flow (employee-facing) — real fixes, 2026-08-20

Logged in as a real employee and went through the actual quiz flow
end-to-end (`mod/quiz/view.php` → `attempt.php` → `summary.php` →
`review.php`) specifically to find and fix real distortions, per the
user's explicit priority. Two confirmed root causes, both fixed in
`custom.css`:

- **`.qtext` (the question text box) was rendering ~240px tall for a
  single line of text**, leaving a large dead space above the answer
  options. Root cause: `.qtext` wasn't establishing its own block
  formatting context, so its height was inflating to "clear" a floated
  sibling (Moodle's `.info` question-info panel) instead of sizing to its
  own content. Fixed with `display: flow-root` — confirmed via live
  testing (not just computed-style inspection) that this collapses the
  box to the correct content-driven height without clipping longer
  question text. Reproduced identically across every question, every
  quiz (Veeba, and spot-checked Module 2), so this wasn't a one-question
  fluke.
- **The quiz review page's summary strip** (Status/Started/Completed/
  Duration/Marks/Grade) — Moove's own `.moove-info-container`/
  `.moove-infobox` components, meant to render as a row of small stat
  tiles — were rendering as a plain vertical stack (`.moove-info-container`
  computed to `display: block` instead of the flex row Moove's own
  naming/structure implies). Fixed by making it an actual flex row. This
  class pair isn't quiz-specific, so the fix applies anywhere else Moove
  uses the same "info tile row" pattern, not just quiz review pages.

Both fixes and the exact debugging path (including a `display:fit-content`
/`height:auto` dead end before finding `display:flow-root` actually
worked) are in `LOG.md`'s matching 2026-08-20 entry — worth reading before
touching quiz-page CSS again, since the height-inflation bug in particular
was not obvious from computed styles alone.

### Question-info heading + box color, and two sitewide bugs — 2026-08-20

Follow-up round, from a user screenshot: the "Question N" heading in the
`.info` panel used Bootstrap's `.h3` size (~26px) inside a ~105-130px
column, wrapping to two lines ("Question"/"1") — sized down to fit one
line. `.formulation`'s flat saturated teal background was replaced with a
neutral light gray-blue + border. Both in `custom.css`.

Two more, unrelated to quizzes specifically:

- **Logged-out front page**: superseded same day, see the next
  subsection below — the approach described here initially (reconfiguring
  Moove's own frontpage settings) was replaced with a separate new
  layout/template pair per explicit user direction, leaving Moove's
  original frontpage content/settings completely untouched.
- **Sitewide fixed nav bar was rendering fully transparent**
  (`background-color: rgba(0,0,0,0)`) on `nav.navbar.fixed-top`, on every
  logged-in page (not scoped to one page — this is the one shared nav
  every logged-in page uses). Barely noticeable at the very top of a
  short page, but scrolling any page revealed it clearly: whatever was
  behind the fixed bar (including the page's own gray side gutters) showed
  through in an inconsistent multi-tone strip. Fixed with an explicit
  solid background + shadow, with a `[data-bs-theme="dark"]` variant since
  Moove's own JS toggles that attribute based on `prefers-color-scheme`.

Full debugging detail (including a CSS specificity puzzle where a rule
with correct, higher specificity still lost until `!important` was added
— root cause never fully identified, likely a media-query-scoped rule
that a naive `styleSheets` traversal missed) in `LOG.md`'s matching
2026-08-20 entry.

### Logged-out front page — new layout/template, Moove's original untouched — 2026-08-20

Corrected approach, same day: the config-reuse approach above was
explicitly rejected in favor of a genuinely separate page. `theme_moove`'s
`displaymarketingbox`/`numbersfrontpage`/`slidercount` config was reverted
back to its original values (`1`, `1`, `0`) and `slidertitle1`/
`slidercap1` unset — Moove's own frontpage is byte-for-byte back to how
it shipped, nothing reconfigured.

`theme_vrblms` now has its own front page instead:
`theme/vrblms/layout/frontpage.php` (registered automatically — Moodle
resolves a child theme's own layout file over the parent's at the same
relative path, no explicit wiring needed) + `theme/vrblms/templates/
frontpage.mustache`. The layout file is Moove's original frontpage.php
copied and trimmed — same drawer/navbar/session-menu wiring (so login,
drawers, and everything else in the header still works exactly as
before), but it no longer touches `\theme_moove\util\settings::frontpage()`
(the demo-content data) and renders `theme_vrblms/frontpage` instead of
`theme_moove/frontpage`. The new template reuses `theme_moove/navbar` and
`theme_moove/footer` as partials, and in between them has just one
`.vrb-hero` section: an `<h1>` with the site name/tagline and a CSS
fade-up (`custom.css`, `@keyframes vrb-hero-fade-up`, responsive via
`clamp()`), nothing else.

One non-obvious requirement discovered building this: Moodle's
`core_renderer::header()` throws a `coding_exception` (`"page layout file
... does not contain the main content placeholder"`) if the rendered
output never contains what `{{{ output.main_content }}}` produces — this
isn't optional boilerplate, every page layout needs it somewhere in its
render output or the page fails to load at all. Moove's original template
has it inside its `#region-main` wrapper, easy to miss when trimming the
file down since it's deep inside the marketingbox/numbers/faq content
that otherwise gets removed. Kept the same `#region-main` wrapper (with
`output.course_content_header`/`main_content`/`course_content_footer`)
right after the hero section — this is also *why* "Available courses"
(the site's default category/course listing) still shows below the hero:
that's core Moodle frontpage behavior via this same region, not something
added deliberately, and left in since it's genuinely useful for a
logged-out visitor.

Moove's own `theme/moove/layout/frontpage.php` and `theme/moove/templates/
frontpage.mustache` are completely unmodified and still present — not
wired into anything anymore, but intact as a reference for the later real
design pass, per the "keep the original code" instruction.
