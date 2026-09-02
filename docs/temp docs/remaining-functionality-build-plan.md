# VRB LMS — Remaining Functionality Build Plan

**Created:** 2026-09-02
**Status:** active. Supersedes nothing; sits alongside `docs/TASKS.md`
(this is the concrete build breakdown for TASKS.md Phase 5 + Certificates).

Read `Agents.md`, `CLAUDE.md`, `docs/ARCHITECTURE.md`, `docs/PROJECT_CONTEXT.md`
and the relevant `LOG.md` entries before touching any of this.

Working method (user's instruction): **one functionality at a time — build
it, integrate it, test it end-to-end, LOG.md entry, then move on.** Check in
at phase boundaries.

---

## Where the project stands (done, verified)

- **TASKS.md Phase 0–4 complete** — env verification; employee data model
  (Brand cohorts `brand_veeba/woktok/zyro`, `state`/`region` profile fields,
  standard `city`, CSV import spec, 20 test employees, Employee-Code-as-
  password scheme); quiz-gating built + browser-tested for all 3 brands;
  `local_vrblms` shared service layer (`attempt_repository` + pluggable
  `ranking/*` strategies + `api.php` + `settings.php` default-strategy
  config); leaderboard as a dedicated page
  (`public/local/vrblms/leaderboard.php`) with an admin filter view + a
  fixed per-employee view + cross-brand "Overall", wired into the primary
  nav and the drawer.
- **Employee-facing UI design-match complete** (CSS-only "Option A",
  checkpoint 2026-09-02) — all in `theme_vrblms`, no core/Moove/SCSS/AMD.

## What's left

| # | Item | Blockers |
|---|------|----------|
| B | Top-performer certificates — **new fully-custom `local_vrbcert` plugin, PDF only** (detailed plan: `docs/temp docs/vrbcert-plugin-build-plan.md`) | qualification criteria still needs client input; build proceeds with configurable defaults |
| A | `report_vrblms` — admin regional reporting + CSV export (TASKS.md Phase 5) | none |
| C | Loose ends / client-dependent | various external inputs |

Decided 2026-09-02:
- Build order flipped to **B then A** — certificates are the current priority.
- Certificate mechanism: a **fully custom, self-coded `local_vrbcert`
  plugin** rendering **PDF only** via Moodle's bundled TCPDF. **Not**
  `mod_customcert` (activity-module model fights the ranking-driven trigger;
  also dodges the Moodle-5.1.5 third-party compatibility risk). Not core
  badges.
- See `docs/temp docs/vrbcert-plugin-build-plan.md` for the full Phase B
  breakdown (~5 dev-days); the section below is now just a summary.

---

## Phase A — `report_vrblms` (admin regional reporting)

Delivers PROJECT_CONTEXT's "Admin / reporting requirements": per-employee
progress / completion %, quiz scores / attempts / pass-fail, completed vs
incomplete modules — filterable **and exportable** by Brand and by
State / City / Region.

### A0 — Prep / re-verify
- Log in as a real admin. **Visually verify the Phase-4 admin leaderboard
  filter view** (`local/vrblms:viewfullleaderboard`) — it is code-complete
  but was never screenshot-verified (no admin creds on that pass, see
  LOG.md 2026-08-30 Phase 4 gotcha). Fix anything broken; this is the same
  filter-form pattern the report will reuse.
- Freeze the exact report column set with the user before building A1
  (proposed columns in A3).

### A1 — Extend `local_vrblms\api` with a reporting query
- Current `api.php` is leaderboard-shaped (rank, `score_percent`,
  `quizzes_completed/total`, `total_time_seconds`). The report needs more
  per-employee/per-module detail.
- Add `api::get_progress_report(?string $brand, ?string $state,
  ?string $city, ?string $region, array $opts = [])` returning, per
  employee: identity + location (Brand(s), State, City, Region), and per
  brand-course-module: completion state (complete / complete-pass /
  incomplete / not-attempted), best & first score, attempt count,
  pass/fail, last-attempt timestamp; plus an overall completion % and
  overall score (using the configured ranking strategy so report and
  leaderboard agree).
- Reuse `attempt_repository` joins; add completion columns from
  `{course_modules_completion}` + `{course_modules}` + `{quiz}` /
  `{quiz_attempts}`. **Documented core tables only, read-only, no schema
  added** — same posture as Phase 3.
- Add `api::get_regions()` (the filter dropdowns need Region; only
  brand/state/city exist today).
- Watch the `SQL_PARAMS_QM` join-vs-where param-ordering trap fixed in
  LOG.md 2026-08-21 — build `$joinparams` / `$whereparams` separately and
  merge join-first; verify params match the *rendered SQL* placeholder
  order, and don't trust a single coincidental test case.
- CLI-verify against the real dataset before any UI: `rksharma` 100% best /
  0% first, `priyagupta` 75%, `kavitayadav` multi-brand (must appear once
  per brand), orphaned `testemployee1` (blank location, 0% — must not
  crash the join).

### A2 — Scaffold the `report_vrblms` plugin
- Path: **`public/report/vrblms/`** (per ARCHITECTURE.md's custom-plugin
  list; a report plugin, not another `local_vrblms` page).
- `version.php` (`$plugin->dependencies = ['local_vrblms' => <version>]`),
  `db/access.php` (`report/vrblms:view`, CONTEXT_SYSTEM, `manager`
  archetype — site admins bypass anyway; leaves room for a real manager
  role), `lang/en/report_vrblms.php`, `index.php`,
  `classes/output/report_view.php`, `settings.php` (adds the link under
  Site Administration → Reports).
- Cross-check `db/access.php` shape against an existing core report plugin
  (`report/*`) rather than guessing capability names.
- Verify detection: `core_component::get_plugin_list('report')` →
  `admin/cli/upgrade.php --non-interactive` → `purge_caches.php`
  (Agents.md).

### A3 — Report UI
- Filter form: Brand / State / City / Region `<select>`s (reuse
  `api::get_brands/get_states/get_cities` + new `get_regions`), plus an
  optional per-course scope select. Plain `<form method="get">` with
  `onchange` auto-submit — **no AMD/JS** (ARCHITECTURE.md flags the AMD
  pipeline as unverified; the leaderboard block/page already set this
  precedent).
- Table (proposed columns): Employee Code, Name, Brand, State, City,
  Region, per-module {Status, Best %, Attempts, Pass/Fail}, Overall
  completion %, Overall score. For many modules, consider a
  employee×module long format for readability + export.
- Escape every DB-sourced label with `s()` before putting it in a
  `<select>` — `html_writer::select()` does not escape option labels
  (LOG.md 2026-08-20 Phase 4 gotcha).
- Branding: `theme_vrblms/style/custom.css` only, light touch (admin-
  facing, not the employee UI). New numbered section in that file.

### A4 — CSV export
- "Download" via core **`\core\dataformat::download_data()`** — gives CSV
  (the stated minimum) plus Excel/ODS/JSON for free from one code path.
- Export applies the currently-active filters (same querystring →
  `get_progress_report()` → dataformat rows).

### A5 — Integrate + test
- `core_component` detection, `admin/cli/upgrade.php`, `purge_caches` —
  clean, no errors. `php -l` every PHP file first.
- CLI verification script (scratchpad, not committed — the project's
  standing substitute for the absent PHPUnit harness) re-run against the
  real 20-employee/3-brand dataset; hand-check row counts, completion %,
  pass/fail against known attempt histories.
- Browser, as a real admin: exercise every filter combination incl.
  Region; confirm the table matches the leaderboard's numbers where they
  overlap; download the CSV and open it; confirm an unprivileged employee
  gets `report/vrblms:view` denied.
- Append a LOG.md entry (what / why / files / verification / gotchas).

**Phase A exit:** a working, filterable, CSV-exportable regional progress
report at Site Administration → Reports → "VRB regional report", consuming
`local_vrblms` only, verified in-browser as admin, logged.

---

## Phase B — Top-performer certificates — `local_vrbcert` (runs FIRST)

**Full breakdown: `docs/temp docs/vrbcert-plugin-build-plan.md`.** Summary:

- A new **fully custom, self-coded plugin `local_vrbcert`** at
  `public/local/vrbcert/`, depending on `local_vrblms`. **PDF only**,
  rendered with Moodle's bundled TCPDF (`\pdf`, `public/lib/pdflib.php`) —
  no `mod_customcert`, no core badges, no external/Composer dependency.
- Simple "template-ish" design: fixed code-drawn A4-landscape layout with
  the **brand name / label / accent colour swapped per brand**, body text
  as editable `{token}` strings. Ships with no artwork (optional
  background-image setting for when the client's design arrives).
- `local_vrbcert_issued` table for idempotency + audit; PDFs stored via
  the File API, served through `local_vrbcert_pluginfile()` with
  ownership/capability checks.
- **`issuer::run()`** resolves qualifiers from `\local_vrblms\api`
  (`get_leaderboard` / `get_overall_leaderboard`, which already return
  `rank` + `score_percent` + name + a Top-N `$limit` — **no `local_vrblms`
  changes needed**), generates + stores + records each cert; idempotent on
  re-run, re-issues on a new `period`.
- Criteria is **config-driven** (`settings.php`): `topn_per_brand`
  (default N=3) / `topn_overall` / `score_threshold`; ranking strategy
  reuses the `local_vrblms` default. Client finalises the rule later
  without code changes.
- Triggered manually (admin "Issue now" + CLI); a scheduled task is
  registered but **disabled by default** until the issuance cadence is
  agreed.
- Surfaces: employee **"My certificates"** page (list + download, new nav
  node) and an admin page (preview sample PDF, issue-now, issued list,
  revoke) under Site Admin → Reports.
- **Est. ≈ 5 dev-days.** Test end-to-end against the real
  20-employee/3-brand dataset (per-brand Top 3 = 9 certs, strategy flip
  changes the set, idempotent re-run, new-period re-issue, browser
  download as qualifier / empty state as non-qualifier, URL-guess
  ownership check). LOG.md entry + mark `ARCHITECTURE.md` § Certificates
  resolved.

**Open before go-live (not build blockers):** final qualification rule
(client) and real certificate artwork (client).

---

## Phase C — Loose ends / client-dependent (track, resolve as inputs arrive)

Not a build sprint. Each is small and mostly waiting on an external input.

- **Ranking formula** — still an open business decision (best-attempt is
  the shipped default; first-attempt also implemented). On client
  confirmation: set `local_vrblms/defaultstrategy`, and add a new strategy
  class if they want something not yet covered (average, speed-weighted,
  completion-date). The pluggable design is already proven.
- **Per-quiz pass marks** — 60% is hardcoded (incl.
  `theme_vrblms/javascript/quizresult.js`). If real quizzes get differing
  pass marks, replace the constant with the true grade-to-pass (needs a
  small server-side surface — `view.php`'s `.quizinfo` has it in the DOM,
  or a `mod_quiz` renderer).
- **Bulk quiz-question import** — PROJECT_CONTEXT says the client content
  format is "to be finalised". Spec + test Moodle's native question import
  (GIFT / Aiken / Moodle XML) against a sample brand's questions; produce a
  client-facing template doc, same shape as `docs/CSV_IMPORT_SPEC.md`.
- **Brand logos / wordmarks** — pending from client. When they arrive:
  drop into `public/theme/vrblms/pix/`, add `background-image` rules per
  `[data-course-id]` / `body.course-<id>` (Veeba=2, Wok Tok=3, Zyro=4 in
  this env — see LOG.md).
- **Multi-brand employees from one CSV row** — deliberate limitation
  (`docs/CSV_IMPORT_SPEC.md` §1): one row per brand, or a follow-up cohort
  step. Revisit only if the real HR file makes this painful.

## Explicitly NOT in this plan (unchanged from TASKS.md)

- Production / staging deployment — blocked on external hosting access.
- Course-format renderer / `format_vrblms` / `mod_quiz` renderer work (the
  3-column module-card grid etc.) — deferred by the user 2026-08-30;
  employee UI stays CSS-only "Option A".
- Real client brand content population — waiting on client-supplied
  content in an agreed format.
