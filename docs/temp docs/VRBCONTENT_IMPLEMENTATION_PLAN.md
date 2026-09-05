# `local_vrbcontent` — Implementation Plan

**Status:** Plan only — no code written yet. This document is the handoff brief
for whichever agent (Claude Code) implements the plugin. It follows this
repo's established conventions: verify against the actual installed Moodle
5.1.5 source before coding, document decisions instead of silently picking
one, keep the scope small, and log real verification (not assumed
verification) in `LOG.md` when done.

**Read first, in order**, per this repo's own standing rule:
`Agents.md` → `CLAUDE.md` → `PROJECT_CONTEXT.md` → `ARCHITECTURE.md` →
`TASKS.md` → `LOG.md` (tail end, most recent entries) → this document.
Then inspect `public/local/vrblms/` and `public/local/vrbcert/` as the two
existing local plugins whose structure/conventions this one should match.

---

## 0. One-paragraph summary

`local_vrbcontent` is a manual-placement content import tool. An administrator
picks an existing course + section, picks or creates a Book or Quiz activity
inside it, defines (or reuses) a field template, uploads a structured CSV,
previews the parsed/validated result, and confirms. The plugin then does the
mechanical work of turning rows into Book chapters or quiz-bank MCQs. It does
not discover, infer, or automate *placement* — only *transformation*. No
changes to `local_vrblms` or `local_vrbcert`. No core changes.

---

## 1. Decisions locked in by the task brief (do not re-litigate)

These were explicitly decided in the task and should not be re-derived:

- Separate plugin: `public/local/vrbcontent/`.
- Two capabilities only: Book import, Quiz import. Nothing else.
- Human picks course/section/activity manually — no auto-discovery, no
  brand detection, no course creation.
- Field templates are schema-driven, not brand-hardcoded — arbitrary field
  counts, administrator-defined mapping.
- Source content is authoritative — never invent, normalize, or "fix" it.
  Preserve strings like `"Not specified in source — confirm with plant"`
  verbatim.
- Validation splits into **structural errors** (block import) vs
  **content warnings** (surfaced, non-blocking).
- Quiz source format is fixed: `Q.No, Topic, Question, A, B, C, D,
  Correct Answer, Explanation` — one row = one single-answer MCQ.
- Pass percentage and max attempts must be admin-configurable, not
  hardcoded (client's current content implies 70% / 3 attempts / 15
  questions, but that's data, not a constant).
- Gating must reuse this project's already-proven mechanism exactly:
  `completionusegrade` + `completionpassgrade` + a Restrict Access
  "activity completion" condition keyed to `COMPLETION_COMPLETE_PASS`
  (the `"e":2` condition), not the plain "complete" (`"e":1"`) condition.
  Visibility stays shown/greyed-out (`"showc": true`), matching
  `ARCHITECTURE.md`'s existing documented recipe — do not re-derive this,
  just reuse it, but re-verify the exact settings-form field names against
  installed source since Moodle versions can shift field wiring.
- No custom quiz/completion/learner-progress engine. No custom Book-like
  content model. Use Moodle's own Book and Quiz/question-bank APIs.
- No Composer/AI dependency unless justified in writing first.

---

## 2. Decisions this plan makes (and why), where the brief left it open

### 2.1 Source file format: **CSV for MVP**, not XLSX

**Decision: CSV only for v1.** XLSX support is explicitly deferred, not
silently dropped — this is the documented decision the task brief asked for.

Rationale:
- `DEPLOYMENT.md` documents a hosting environment with **SSH/shell disabled
  account-wide** and PHP version constraints already causing one production
  incident. A Composer-based dependency (e.g. PhpSpreadsheet) needs `composer
  install` to run somewhere — there is currently no verified way to run that
  on the target cPanel host, and the whole project posture (see
  `DEPLOYMENT.md`'s `.cpanel.yml` design) is "only copy plugin directories,
  never assume shell access on the live server."
- This repo has no existing Composer usage anywhere in `public/local/*` —
  introducing one now for a single optional file format is a new
  architectural pattern for a non-essential feature.
- CSV satisfies every real requirement in the brief: "structured tabular
  values," no dependence on cell color/merges/formulas/styling. A source
  workbook maintained in Excel/Sheets and exported as CSV loses nothing the
  importer is allowed to use anyway.
- If XLSX becomes a hard requirement later, re-open this decision explicitly
  rather than adding the dependency quietly — check at that time whether
  Moodle core already bundles a spreadsheet reader for admin tool use (some
  are bundled for `admin/tool/uploadcourse`-style tools) before reaching for
  PhpSpreadsheet.

**Action for Claude Code before coding:** grep `public/` for any existing
bundled spreadsheet-reading library (e.g. under `public/lib/`) before
concluding "CSV only" is the final word — if one is already vendored and
autoloadable with zero new dependency, note it as a fast-follow, not urgent.

### 2.2 Interface: **Moodle admin web UI**, not CLI, for MVP

**Decision: multi-step `moodleform`-based admin UI only.** No CLI in v1.

Rationale:
- The brief's own workflow (select course → select section → select/create
  Book or Quiz → define/select template → upload → parse → validate →
  preview → confirm) is inherently interactive and stateful across several
  human decisions — a poor fit for a single CLI invocation, and a good fit
  for Moodle's native multi-page `moodleform` wizard pattern (the same
  pattern Moodle's own `admin/tool/uploadcourse` and `admin/tool/uploaduser`
  use).
- This project's *prior* CLI usage (Phase 2/3 provisioning scripts logged in
  `LOG.md`) was disposable, scratchpad, one-off dev/test tooling — explicitly
  not committed to the repo. `local_vrbcontent` is a real, repeatedly-used
  administrator tool, which is a different category from those scripts.
- A CLI can be added later as a thin wrapper around the same internal
  Provisioner classes if a genuine headless/scripted need shows up — the
  Parser → Validated Data → Provisioner separation (§4) makes that cheap
  later. Do not build it speculatively now.

**Where it lives:** a normal `admin_externalpage` under **Site
administration → Reports** (matching `local_vrbcert`'s placement pattern) or
**Plugins → Local plugins** — verify which existing precedent
(`local_vrblms`/`local_vrbcert`) fits better and match it, don't invent a
third pattern.

### 2.3 Duplicate/re-import safety: one minimal tracking table

**Decision:** add `local_vrbcontent_import` (see §7), storing one row per
completed import batch: target activity, a content hash, row count, status,
timestamp, importing user. On a new upload targeting the same
course-module + template, compute the hash of the normalized parsed rows and
compare:
- **Identical hash already imported successfully** → block by default, show
  the admin "this exact content was already imported on {date}", offer
  **Re-import (replace)** — deletes/replaces the specific chapters/questions
  this plugin created for that batch (tracked via itemised child rows or a
  linked list of created object ids — see §7) and recreates them — or
  **Cancel**.
- **Different hash, same target** → proceed as a normal new import (this is
  the expected "content updated" case), but still show a warning banner
  ("N chapters/questions already exist here from a prior import") so the
  admin isn't surprised by growing content.

This is the smallest structure that satisfies the brief's "safe choice, not
silent duplication, not Git for mayonnaise" requirement, without inventing
versioning.

### 2.4 Question bank category structure

**Decision:** one top-level category per course named `VRB Content`
(course context, not system/category context — question banks in this
Moodle version are typically scoped per-course or per-category; **verify
the exact context model against installed source before coding**, since
recent Moodle versions have changed question bank context scoping — see
§8.3), with one child category per user-chosen "module label" string typed
in by the administrator at import time (e.g. `Module 1`, `Onboarding
Quiz`) — **not** inferred from brand/course name. This satisfies §17 of the
brief ("must not depend on automatically detecting the brand") while still
giving predictable, non-flat organization.

### 2.5 Book chapter structure per row

**Decision:** the first mapped template field is always the chapter title
(per the brief). Every other mapped field becomes one `<h5>{label}</h5>`
followed by the raw cell value (HTML-escaped, then passed through
`format_text()`/the Book's normal content path — do not hand-roll HTML
sanitization). Empty/missing values are preserved as an explicit "—" or the
literal source string, never suppressed or replaced with a placeholder the
importer invents.

---

## 3. Non-goals (copied from the brief, kept visible so they aren't
re-added later by scope creep)

Do NOT build: automatic brand/category/course/module discovery or creation;
AI-generated content or questions; automatic correction of source content;
a custom quiz/completion/learner-progress engine; certificate or
leaderboard functionality; changes to `local_vrblms` or `local_vrbcert`;
production deployment tooling; unnecessary dashboards or REST APIs;
complicated sync/versioning.

---

## 4. Architecture: three layers, strictly separated

```
Source Parser              (pure PHP, no Moodle DB calls, unit-testable)
      ↓  produces
Internal Validated Data    (plain PHP value objects / arrays; errors + warnings)
      ↓  consumed by
Moodle Provisioner         (the only layer that touches $DB / Moodle APIs)
```

This mirrors the brief's explicit requirement (§24) and this repo's own
established pattern in `local_vrblms` (`attempt_repository` vs `api.php` vs
`ranking/*`) and `local_vrbcert` (`certificate_data`/`template` vs
`issuer`/`certificate_generator`). Reuse that separation style.

### 4.1 Suggested class layout

```
public/local/vrbcontent/
  version.php
  lib.php                          # nav hooks (drawer link, matching local_vrblms/local_vrbcert pattern)
  db/
    access.php                     # capabilities
    install.xml                    # local_vrbcontent_import (+ child rows table, see §7)
    hooks.php                      # primary_extend nav hook (optional, match local_vrbcert precedent)
  classes/
    hook_callbacks.php
    book/
      template.php                 # field-template value object + persistence (see §5)
      row_parser.php                # CSV -> array<row>, pure
      row_validator.php             # array<row> + template -> validated_result (errors/warnings), pure
      book_provisioner.php          # validated_result + course/section/book -> chapters (touches $DB/Moodle APIs)
    quiz/
      quiz_row_parser.php           # CSV -> array<row>, pure
      quiz_row_validator.php        # array<row> -> validated_result, pure
      question_provisioner.php      # validated_result -> question bank entries (touches $DB/Moodle APIs)
      quiz_configurator.php         # sets gradepass/attempts/completion/restrict-access (touches $DB/Moodle APIs)
    import_batch.php                # the local_vrbcontent_import record wrapper (hash, status, replace logic)
    validated_result.php            # shared: rows, errors[], warnings[], is_blocked()
  classes/form/
    select_destination_form.php     # course/section/activity picker (step 1)
    template_form.php                # define/select field template (step 2, Book only)
    upload_form.php                  # file upload (step 3)
    confirm_form.php                 # preview + confirm (step 4)
    quiz_settings_form.php           # pass %, max attempts, gating target (Quiz only)
  book_import.php                   # controller: the Book wizard, step-dispatched via $SESSION or a `step` param
  quiz_import.php                   # controller: the Quiz wizard
  lang/en/local_vrbcontent.php
  tests/
    row_parser_test.php
    row_validator_test.php
    quiz_row_validator_test.php
```

Do not create more directories/abstraction than this. If a step's form is
trivial, still keep it as its own `moodleform` class — that's the Moodle
convention this project already follows elsewhere (`local_vrbcert/index.php`
building multiple mforms in one page).

---

## 5. Book field template model

A template is: a name, and an ordered list of `{label, key, is_title}`
entries — `is_title` true for exactly one entry (the first, by default, but
allow reordering). Store templates in a plain table
(`local_vrbcontent_template` + `local_vrbcontent_template_field`, or a single
JSON-blob table if that's simpler and this repo's precedent supports it —
check `local_vrbcert`'s settings storage style before choosing) rather than
config strings, since an admin will want to reuse/edit templates across
imports of the same brand's product sheets.

CSV header row is matched against the template by exact label match
(case-insensitive, trimmed) — **not** by position. This is a structural
decision: if the CSV's header row doesn't contain every field the template
declares, that is a structural error (missing required header, per §10 of
the brief), not a silent skip.

---

## 6. Validation rules (concrete, not just categories)

### 6.1 Book — structural errors (block import)

- CSV header row missing one or more template-mapped column labels.
- A template maps the same source column label to two different fields
  (duplicate mapping).
- The designated title field is empty on any row.
- The file has zero data rows.
- File is not valid CSV (unparseable — e.g. inconsistent column count that
  cannot be reconciled with the header).

### 6.2 Book — content warnings (surfaced, non-blocking)

- Any non-title field is empty on a row.
- Cell value matches a recognized "placeholder/uncertain" pattern (e.g.
  contains "confirm with", "not specified", "TBD", "sold out") — flagged
  for visibility in the preview, never rewritten.

### 6.3 Quiz — structural errors (block import)

- Missing `Question` text on a row.
- Fewer than 2 of A–D populated (need at least 2 options for a valid MCQ).
- `Correct Answer` value is not one of the letters actually populated for
  that row (e.g. row has only A/B/C populated but Correct Answer = "D").
- Duplicate `Q.No` values in the same file (ambiguous ordering/identity).
- Empty `Q.No` (used as the row's identifier for duplicate-detection and
  error messages).

### 6.4 Quiz — content warnings (non-blocking)

- Missing/empty `Topic`.
- Missing/empty `Explanation` (still importable — general feedback field
  is simply left blank, not fabricated).

### 6.5 Preview requirement

The preview screen must render **both** lists distinctly (e.g. a red
"Errors — must fix before import" block and an amber "Warnings — will still
import" block), and the Confirm button must be disabled/absent whenever any
structural error exists for the current file. This is a hard UI requirement
from the brief (§19/§28), not a nice-to-have.

---

## 7. `local_vrbcontent_import` table (minimal, see §2.3)

```
local_vrbcontent_import
  id
  courseid
  cmid              -- the Book or Quiz course-module this batch targets
  importtype        -- 'book' | 'quiz'
  contenthash        -- sha256 of the normalized validated rows
  rowcount
  status             -- 'complete' | 'superseded' | 'failed'
  userid              -- who ran it
  timecreated

local_vrbcontent_import_item
  id
  importid          -- FK to local_vrbcontent_import
  itemtype           -- 'chapter' | 'question'
  itemid             -- the created mdl_book_chapters.id or mdl_question.id
```

`local_vrbcontent_import_item` is what makes "Re-import (replace)" safe —
delete exactly the rows this batch created, via their own Moodle APIs
(`book_delete_chapter()` equivalent, `question_delete_question()` or
equivalent — **verify exact function names against installed source**,
do not assume from general Moodle knowledge, consistent with this whole
project's rule), then re-run the provisioner. Never a raw `DELETE FROM
mdl_book_chapters`.

---

## 8. Verification checklist — do this BEFORE writing provisioning code

Per this repo's constitution (`Agents.md`, `ARCHITECTURE.md`'s "VERIFIED"
framing, `LOG.md`'s repeated lesson about trusting general Moodle knowledge
vs. the actual installed source), confirm each of the following against the
real `public/` tree before implementing, and note the file:line in the
eventual `LOG.md` entry:

### 8.1 Book

- `public/mod/book/lib.php` / `public/mod/book/locallib.php` — the real
  function(s) for creating a chapter (`book_add_chapter()`-style) and their
  exact parameter shape (title, content format, `pagenum`/ordering,
  subchapter flag).
- Whether chapter content must go through `file_save_draft_area_files()`
  even for pure-text content, or whether a plain `intro`/`content` field
  insert is sufficient (the `label` activity generator's array-vs-string
  `intro` gotcha logged in `LOG.md` 2026-08-19 is a warning sign to
  double-check this, not assume the simple path works).
- How to create the Book activity itself if the admin chooses "create new"
  rather than "select existing" — via `create_module()` /
  `\core_course\module_creation` or whichever is current in 5.1.5 (do not
  assume the pre-5.x helper names).

### 8.2 Quiz

- `public/mod/quiz/locallib.php`'s `quiz_add_quiz_question()` signature —
  already used successfully in this project's Phase 2 scratch scripts
  (see `LOG.md` 2026-08-19), reuse that exact call shape.
- **The `sumgrades` recompute gotcha already discovered and logged**:
  `quiz_add_quiz_question()` does NOT recompute `sumgrades` — must call
  `\mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->
  recompute_quiz_sumgrades()` explicitly afterward. Reuse this, it is
  already verified in this exact environment.
- The multichoice question creation API: confirm whether
  `question_bank::get_qtype('multichoice')->save_question($question, $form)`
  (the same low-level path this project used for `truefalse` in Phase 2,
  per the PHPUnit-generator gotcha already logged) is the correct pattern
  for a 4-option single-answer MCQ, and what the `$form` object needs
  (`answer[]`, `fraction[]`, `feedback[]`, `single`, `shuffleanswers`, etc.)
  — verify field names against `public/question/type/multichoice/edit_multichoice_form.php`
  and `public/question/type/multichoice/questiontype.php`, not from memory.
- Question bank category creation: verify the real 5.1.5 API — check
  `public/question/classes/bank/` and `public/lib/questionlib.php` for the
  current category-creation entry point (older Moodle used
  `question_category_object` in `question/editlib.php`, which itself is
  now flagged in the audit as sometimes-refactored between versions —
  confirm what actually exists in this branch before writing §2.4's
  provisioning code).

### 8.3 Question bank context scoping

Confirm in this Moodle version whether question categories are scoped by
course context or "question bank entry" / course-category context by
default for a course created via this project's existing course-category
mapping (Brand = category, one course per learning-category — see
`ARCHITECTURE.md`'s "Learning structure mapping" decision). Get this right
before building §2.4's category tree, since a wrong context choice would
make imported questions invisible to the target quiz's "add from question
bank" screen.

### 8.4 Completion / Restrict Access (reuse, but re-verify field names)

`ARCHITECTURE.md`'s "Quiz gating" section already documents the exact
verified settings combination (Section 03 of the feasibility audit +
Phase 2's empirical test). Re-verify only the **form field names**
(`completionusegrade`, `completionpassgrade`, `completiongradeitemnumber`)
still match this exact installed version before writing code that sets
them programmatically (via `add_moduleinfo()`/`update_moduleinfo()`-style
calls, not raw `mdl_course_modules` writes) — the `completiongradeitemnumber`
gotcha from Phase 2's `LOG.md` entry (checkbox maps to this column, not a
literal boolean, and gets silently forced back to 0 if grade-tracking isn't
also enabled) is a real trap to re-check here, since this plugin will be
setting these values programmatically rather than via a human clicking
through the form.

### 8.5 Restrict Access condition JSON

Reuse the exact JSON shape already verified in `ARCHITECTURE.md`:
`{"type":"completion","e":2}` wrapped in the standard availability
tree/operator structure Moodle expects for a section's `availability`
column — verify the wrapping structure (`op`, `show`, `c: [...]`) against
`public/availability/classes/tree.php` before hand-building the JSON string.

---

## 9. Quiz settings: verify the 70%/15/11 claim, don't just assume it

The brief states 15 questions at 70% pass corresponds to 11/15 once Moodle's
grading is applied, but explicitly asks for this to be verified rather than
hardcoded. Reasoning to confirm empirically against a real provisioned quiz
in this environment (do not just trust the arithmetic below without a live
check, consistent with this project's whole verification culture):

- `gradepass` is stored as a value out of the quiz's `grade` (max grade),
  and completion checks `$score >= $item->gradepass` (already verified in
  `ARCHITECTURE.md`/the audit, `completionlib.php`).
- With 15 equally-weighted one-mark questions and `gradepass` set to 70% of
  max grade, 10/15 = 66.67% < 70% (fails), 11/15 = 73.33% ≥ 70% (passes) —
  there is no integer score that lands exactly on 70% with 15 questions, so
  11/15 is the true effective minimum passing score.
- **Action:** after implementing, provision a real 15-question test quiz via
  this plugin, set pass percentage to 70 via the configurable setting (not
  hardcoded), attempt it as a real test employee scoring exactly 10/15 and
  separately 11/15, and confirm completion state (`COMPLETION_COMPLETE_FAIL`
  vs `COMPLETION_COMPLETE_PASS`) matches this reasoning — log the result in
  `LOG.md` per this project's existing standard (see the Phase 2 entry for
  the expected level of rigor: real login, real attempt, not assumed).

---

## 10. Security checklist (standard Moodle web-form hygiene, listed
explicitly so it isn't skipped)

- `require_login($courseid)` + a real capability check
  (`local/vrbcontent:import`, new capability, granted to `editingteacher`/
  `manager` archetypes — **not** the generic `user` archetype, unlike
  `local_vrblms`'s leaderboard or `local_vrbcert`'s "my certificates" —
  this tool touches course content and should not be self-service for
  employees) before any step of either wizard.
- CSRF: every `moodleform` gets this for free via `sesskey` — do not build
  a bespoke upload endpoint outside the `moodleform` file-manager pattern.
- File upload via Moodle's `filepicker`/draft-area APIs
  (`file_save_draft_area_files()` or the simpler single-file "upload" form
  element with server-side validation of extension/mimetype) — reject
  non-`.csv` uploads server-side, not just via the file-picker's client-side
  filter.
- Validate every request parameter with `required_param()`/`optional_param()`
  and the correct `PARAM_*` type (`PARAM_INT` for ids, `PARAM_ALPHANUMEXT`
  for template/module-label strings, etc.) — no raw `$_POST`/`$_GET` reads.
- Context checks: confirm the chosen course/section/cm actually exist and
  the admin's capability applies in *that* context (not just system
  context) before writing anything.

---

## 11. Testing plan (matches brief §25 — deliberately small)

Automated (PHPUnit or a plain scratchpad harness — check whether this repo
now has a working PHPUnit setup; `LOG.md`'s Phase 3 entry noted **no
PHPUnit harness existed** as of 2026-08-20, so verify current status first,
and if still absent, use the same "small CLI verification script" pattern
this project has used successfully for `local_vrblms`/`local_vrbcert`
instead of standing up PHPUnit from scratch just for this):

- `row_parser_test` — CSV → array, including malformed-CSV handling.
- `row_validator_test` — the Book VALID/INVALID/WARNING cases listed in
  brief §25 (5-field, 7-field, 11-field product sheets; a narrative
  company-knowledge sheet; missing required field; duplicate mapping;
  empty title; "confirm with pricing"/"not specified" warnings; sold-out
  preserved verbatim).
- `quiz_row_validator_test` — the Quiz VALID/INVALID cases (15-question
  valid sheet; missing question; missing option; invalid correct-answer
  letter; malformed row; duplicate Q.No).

Manual, against the real Moodle 5.1.5 instance, following brief §25's
12-step list exactly (create/select test course+section, import Book,
verify chapters render, import Quiz, verify questions/options/answer key,
verify grade-to-pass, verify max attempts, verify completion, verify
Restrict Access on the next section, test a failing learner, test a passing
learner, confirm lock/unlock) — using a **fresh, not-yet-used test course**
so this doesn't collide with the existing Veeba/Wok Tok/Zyro onboarding
courses already built and relied on by `local_vrblms`/`local_vrbcert`'s own
verification data. Record the manual pass in `LOG.md` in this project's
established entry format (What/Why/Files touched/Verification
done/Gotchas), the same as every other entry in that file.

---

## 12. Suggested build order (phases, mirroring `TASKS.md`'s style)

1. **Phase 0 — verification pass.** Work through §8 entirely (Book APIs,
   Quiz/question-bank APIs, category context scoping, completion field
   names, availability JSON shape) against the real installed source.
   Produce a short findings note (like the feasibility-audit HTML, or just
   a `LOG.md` entry) before writing provisioning code.
2. **Phase 1 — plugin skeleton + parser/validator layer only.** Scaffold
   `version.php`, `db/access.php`, `db/install.xml`. Build `row_parser`,
   `row_validator`, `quiz_row_parser`, `quiz_row_validator`,
   `validated_result` — pure PHP, no Moodle DB writes. Write the
   automated tests from §11 against this layer first, independent of any
   Moodle provisioning code.
3. **Phase 2 — Book wizard + provisioner.** Build the 4-step Book UI and
   `book_provisioner`. Manually verify against a real test course/section.
4. **Phase 3 — Quiz wizard + provisioner + gating configurator.** Build the
   Quiz UI, `question_provisioner`, `quiz_configurator` (grade-to-pass, max
   attempts, completion settings), and the Restrict-Access helper for the
   *next* section (operating only on an explicitly admin-selected target
   section, per brief §16 — never auto-discovered). Manually verify the
   full fail→locked / pass→unlocked cycle, per §9 and §11's manual steps.
5. **Phase 4 — duplicate/re-import safety.** Add
   `local_vrbcontent_import(+_item)`, hash-check, and the
   skip/replace/cancel UI.
6. **Phase 5 — polish only if time allows.** Nothing in `docs/` or this
   plan requires theming/branding for an admin-only tool; do not spend
   effort on `theme_vrblms` styling for this plugin's pages unless
   explicitly asked.

---

## 13. Acceptance criteria

Copied from the task brief verbatim — implementation is not "done" until
every box below is genuinely, verifiably true (not assumed):

**Book:** admin selects existing destination · defines a field template ·
uploads a structured CSV · importer validates before creating anything ·
preview shown before import · each valid row → one chapter · title field →
chapter title · remaining fields → labeled readable content · variable
field counts supported · missing/uncertain source values preserved
verbatim · structural errors block import · content warnings surfaced but
non-blocking.

**Quiz:** admin selects existing Quiz destination · uploads the
standardized quiz CSV · questions validated before creation · each valid
row → a standard Moodle MCQ · A–D preserved · correct answer preserved ·
explanation preserved in general feedback · questions placed in a
sensible, non-brand-inferred question-bank category · questions added to
the selected quiz · pass percentage configurable · max attempts
configurable.

**Gating:** grade-to-pass configured · completion requires passing grade ·
next section restrictable via passing-grade completion (`e:2`, not `e:1`) ·
lock reason stays visible to the learner · existing native gating
elsewhere in the site is untouched.

**Architecture:** zero changes to `local_vrblms` · zero changes to
`local_vrbcert` · zero core changes · parser/validator strictly separated
from provisioner · Moodle 5.1.5 APIs verified against installed source
(not assumed) · web UI has real capability/CSRF/context checks · duplicate
re-import handled safely · no scope creep beyond this document and the
original brief.

---

## 14. Explicitly open items for the implementer to resolve during Phase 0/1
(these are genuinely unspecified, not decided above — pick the smallest
reasonable answer and document it, per the brief's own instruction, rather
than pausing to ask)

- Exact admin-menu placement (Reports vs Plugins vs a new top-level node) —
  match whichever existing precedent (`local_vrblms` vs `local_vrbcert`)
  is the closer analogy once both are re-read.
- Whether template definitions are reusable across courses/brands (likely
  yes — a "Veeba Product" template shouldn't need re-entering per course)
  or scoped to one course — lean toward reusable-by-name, since brief §5's
  examples ("Template: Product", "Template: Wok Tok Product") imply
  named, reusable templates.
- Exact wording/placement of the skip/replace/cancel duplicate-import
  prompt (§2.3) — a simple radio-button choice on the same confirm page is
  sufficient, no new page needed.
