# LOG.md — Agent Work Log

This is a persistent, append-only log written by AI agents, for AI agents.
Purpose: a new agent/session picking up this project should be able to read
this file and not need the full prior conversation re-explained.

**Rules for anyone appending to this file:**
- Add an entry after any meaningful unit of work (a TASKS.md phase, a bug
  fix, a non-obvious decision, a wrong assumption corrected). Skip trivial
  one-line fixes.
- Never edit or delete a previous entry, even if superseded — add a new
  entry that references the old one instead.
- Be specific enough that a reader with zero conversation context could act
  on it without follow-up questions.
- Check this file for relevant prior entries before starting new work, in
  addition to `docs/`.

---

## [2026-08-19] — Discovered Moodle 5.1's `public/` directory restructure
**Agent:** unknown (prior to current session's conversation history)
**What:** Established that as of Moodle 5.1, all plugin directories
  (`theme/`, `local/`, `blocks/`, `report/`, `admin/`, `mod/`, `lib/`, etc.)
  live under `moodle/public/`, not at the repo root — `config.php` is the
  one exception and stays at the true root.
**Why:** General Moodle knowledge/training data and most tutorials describe
  the pre-5.1 layout (`moodle/theme/`, `moodle/local/`, etc. directly off
  root). Trusting that general knowledge for this specific installation
  produced a real bug (see next entry) before this was written down.
**Files touched:** none directly — this was a discovery, formalized in
  `Agents.md`.
**Verification done:** confirmed by listing the actual installed codebase
  (`docker exec vrb-moodle ls /var/www/html/public/theme/` etc. showing
  boost/classic/moove present under `public/`, not at root).
**Gotchas for future agents:** do not trust any path of the form
  `moodle/theme/...`, `moodle/local/...`, `moodle/blocks/...` from memory —
  always verify against the actual container filesystem first. See
  `Agents.md` for the exact verification commands.

## [2026-08-19] — Moove theme installation: version-mismatch dead end, then correct install
**Agent:** unknown (prior to current session's conversation history)
**What:** First attempt downloaded/installed a Moove theme zip declaring
  Moodle 5.2.1 compatibility, which was incompatible with this installation
  (Moodle 5.1.5+, `2025100605.05`, branch `MOODLE_501_STABLE`). That attempt
  was abandoned; the correct Moove release `5.1.2` (which explicitly
  declares Moodle 5.1 support) was sourced and installed instead, placed at
  `public/theme/moove/`.
**Why:** A marketplace/download listing's stated version label does not
  reliably match Moodle's actual `$plugin->requires` in `version.php`. The
  5.2.1 zip was likely picked based on being the "latest" without checking
  `version.php` directly against the installed Moodle version first.
**Files touched:** `public/theme/moove/` (all theme files; the discarded
  5.2.1 attempt's files are not present in current git history, so no path
  to clean up there).
**Verification done:** confirmed active theme via
  `docker exec vrb-moodle php -r "... echo $CFG->theme ..."` → `moove`,
  and confirmed `public/theme/moove/version.php` declares 5.1 support.
**Gotchas for future agents:** never trust a theme/plugin's version label
  from a marketplace listing or filename alone — always open the actual
  `version.php` inside the downloaded zip and check `$plugin->requires` /
  `$plugin->supported` against this install's exact version
  (`2025100605.05` / branch `501`) before installing. This has already
  wasted time once.

## [2026-08-19] — Created `Agents.md` to codify environment facts
**Agent:** unknown (prior to current session's conversation history)
**What:** Wrote `Agents.md` at repo root, capturing the `public/` restructure
  fact, verified environment details (Moodle version, Docker container
  names, theme, `moodledata` path), and rules for working in this codebase
  (never modify core, verify before trusting general knowledge, purge
  caches after plugin changes, etc.).
**Why:** Two prior incidents (the `public/` restructure mismatch and the
  Moove version-mismatch dead end above) both stemmed from trusting general
  Moodle knowledge over verifying against this actual installation. This
  file exists specifically to stop that pattern from recurring for any
  agent (Claude Code, Codex, or otherwise) touching this repo.
**Files touched:** `Agents.md`.
**Verification done:** none applicable — this is a documentation artifact,
  not a functional change.
**Gotchas for future agents:** `Agents.md` is required reading, first, in
  every session on this repo — see its own "Required reading" ordering,
  which this file's own instructions also follow.

## [2026-08-19] — Phase 0 verification pass + found & fixed a stray duplicate theme copy
**Agent:** Claude Code
**What:** Ran the Phase 0 verification checklist from `docs/TASKS.md`
  (Docker up, Moodle reachable at `localhost:8080`, `public/local`,
  `public/blocks`, `public/report` confirmed clean of custom code, Moove
  confirmed active via `$CFG->theme`, `config.php` confirmed git-ignored
  and untracked). In the process, found that commit `9f681dbbe8a` ("Added
  moove theme and markdown files") had added Moove theme files to **two**
  locations in the same commit: the correct `public/theme/moove/` and a
  stray legacy-path `theme/moove/` at repo root (132 files, byte-identical
  `version.php` in both). The user deleted the stray `theme/moove/` copy
  from disk directly (not via `git rm`).
**Why:** This is the exact failure mode `Agents.md` warns about (a
  correctly-built theme placed one directory level too high) — except this
  time the wrong-location copy was fully tracked in git rather than
  silently invisible. Left in place, it was a latent hazard for the
  planned `.cpanel.yml` deploy pipeline (`docs/DEPLOYMENT.md`), which
  copies specific plugin/theme directories to the live server — an
  ambiguous duplicate in git risked being deployed to the wrong path or
  edited by mistake instead of the active copy.
**Files touched:** deleted (on disk, not yet git-committed as of this
  entry): `theme/moove/**` (root-level, ~132 files). Not touched:
  `public/theme/moove/` (left intact, confirmed active).
**Verification done:** `docker exec vrb-moodle ls /var/www/html/theme` →
  no such directory (confirms removal is reflected in the running
  container, since the whole repo root is bind-mounted). Confirmed
  `public/theme/moove/` still present and `$CFG->theme` still resolves to
  `moove`. `git status --short` at time of writing still shows these as
  unstaged deletions (`D theme/moove/...`) — **not yet committed.**
**Gotchas for future agents:** if `git status` still shows `D
  theme/moove/...` when you read this, that cleanup commit was
  deliberately left unstaged (commit policy on this repo: only commit when
  explicitly asked). Stage and commit it, or check with the user, before
  assuming the repo is clean. Do not recreate a theme copy at any path
  outside `public/theme/`.

## [2026-08-19] — Created `CLAUDE.md`
**Agent:** Claude Code
**What:** Wrote `CLAUDE.md` at repo root — the always-loaded session entry
  point, consolidating `Agents.md` + the four `docs/` files into a shorter
  always-in-context summary, plus an "incident log" section (now superseded
  in granularity by this LOG.md — CLAUDE.md keeps only a short pointer,
  this file has the full detail).
**Why:** User requested a `CLAUDE.md` be created based on `Agents.md` and
  the verification work done this session.
**Files touched:** `CLAUDE.md`.
**Verification done:** none applicable — documentation artifact.
**Gotchas for future agents:** `CLAUDE.md` is intentionally a summary, not
  the source of truth — `docs/ARCHITECTURE.md`, `docs/TASKS.md`, and this
  `LOG.md` are more authoritative for anything CLAUDE.md's summary might
  simplify or go stale on.

## [2026-08-19] — Phase 1 step 1 verified: dual cohort membership confirmed via UI + DB
**Agent:** Claude Code (verification) + user (manual UI steps)
**What:** Per `docs/TASKS.md` Phase 1's first checklist item, the user
  manually created two cohorts via Site Administration → Cohorts UI —
  `Veeba` (idnumber `brand_veeba`, id=1) and `Indore` (idnumber
  `region_indore`, id=2) — created a test user `testemployee1` (userid=3),
  and added that user to both cohorts via the UI. Claude Code then verified
  the result directly against the database via Moodle's `$DB` API.
**Why:** `docs/TASKS.md` explicitly calls for this to be done manually via
  the UI first, not via code/import script, specifically to confirm dual
  cohort membership actually works end-to-end before any import automation
  is built on top of that assumption.
**Files touched:** none (database state change only, via Moodle UI; no
  repo files touched).
**Verification done:** `docker exec vrb-moodle php -r "..."` querying
  `mdl_cohort` and `mdl_cohort_members` directly confirmed: 2 cohort rows
  exist with the expected names/idnumbers, and `testemployee1` (userid=3)
  has exactly 2 rows in `mdl_cohort_members` — one per cohort (cohortid=1
  and cohortid=2), each with its own `timeadded`. This confirms
  `ARCHITECTURE.md`'s claim (previously only verified by reading the
  `cohortid, userid` unique index definition in source) actually holds at
  runtime: a single user can belong to a Brand cohort and a Region cohort
  simultaneously.
**Gotchas for future agents:** none — this step is now empirically
  confirmed, not just source-inspected. Next Phase 1 steps (custom profile
  fields for State/City/Region, bulk CSV import via
  `admin/tool/uploaduser/`) can proceed on top of this confirmed
  assumption.

## [2026-08-19] — Verified CSV column format for `admin/tool/uploaduser` bulk import
**Agent:** Claude Code
**What:** Investigated the actual installed `admin/tool/uploaduser` code
  (`public/admin/tool/uploaduser/classes/process.php`, `user_form.php`,
  `locallib.php`) and live DB state to produce a verified CSV column spec
  for bulk employee import. Output written to `docs/CSV_IMPORT_SPEC.md`.
  No dummy data generated — this was format-determination only, per task
  scope.
**Why:** ARCHITECTURE.md's uploaduser/cohort/username claims were
  previously verified by reading source in isolation; this pass re-verified
  them against the actual upload-processing code path and, in two places,
  found the real behavior differs from what was assumed/stated in the
  existing docs (see gotchas below) — worth checking before generating any
  real import data.
**Files touched:** `docs/CSV_IMPORT_SPEC.md` (created).
**Verification done:** Read `process.php`'s `standardfields` array (line
  ~147) directly to confirm `idnumber` and `city` are standard columns.
  Read the email-required check at `process.php:988-990` and traced it as
  unconditional in the new-account path. Read the cohort-assignment logic
  at `process.php:1090-1134` confirming `cohortN` columns accept either a
  numeric cohort id or an idnumber string, resolved via `is_number()`.
  Queried `mdl_user_info_field` and `mdl_user_info_category` directly —
  both empty, confirming no custom profile fields exist yet. Queried
  `mdl_cohort` — only the two Phase-1 test cohorts (`Veeba`, `Indore`)
  exist, not Wok Tok/Zyro. Read `\core\param::clean_param_value_username()`
  in `public/lib/classes/param.php` (this logic now lives here, not in
  `moodlelib.php` where older docs/training data would put it) and
  confirmed `$CFG->extendedusernamechars = '0'` on this install. Read the
  password-handling form options (`uupasswordnew`, `uuforcepasswordchange`)
  in `user_form.php`.
**Gotchas for future agents:**
  - **Email is NOT actually optional for this tool**, despite
    PROJECT_CONTEXT.md stating employee email is optional. A blank email
    on any row is a hard reject (`process.php:988-990`), unconditionally,
    in the new-account code path. This is a real conflict between the
    tool's behavior and the stated business requirement — not resolved,
    flagged in `docs/CSV_IMPORT_SPEC.md` §1 and the open-items list. Do
    not build the real import assuming blank emails will just work.
  - **`city` is already a standard Moodle user field** — do not create a
    custom profile field also shortnamed `city`, it collides. Recommended
    (not yet confirmed with the user): reuse the standard `city` column,
    only create custom fields for State and Region.
  - Custom profile fields for State/Region **do not exist yet** — this
    blocks generating a real (non-spec) import file. Must be created via
    Site Administration → Users → Profile fields first.
  - Only 2 of the real cohorts needed exist (`Veeba`, `Indore`, both from
    the Phase 1 test). Wok Tok and Zyro brand cohorts still need to be
    created before a real import.
  - Surfaced but did not resolve: TASKS.md's Phase 1 description ("one
    Brand cohort, one Region cohort") reads as if Region should be modeled
    as a cohort, but ARCHITECTURE.md's actual decision is State/City/
    Region as profile fields, with cohorts reserved for (possibly
    multiple) Brand access. The `Indore` cohort from Phase 1 was useful to
    mechanically prove dual cohort membership works, but shouldn't be
    assumed to be part of the ongoing architecture without an explicit
    decision — see `docs/CSV_IMPORT_SPEC.md` §2.4.
  - If searching for `PARAM_USERNAME`'s cleaning logic again: it's in
    `public/lib/classes/param.php` (method
    `clean_param_value_username()`), not `public/lib/moodlelib.php` — the
    constant in `moodlelib.php` just delegates to the `\core\param` enum
    class now. General Moodle knowledge/older docs will point you to the
    wrong file for this Moodle version.

## [2026-08-19] — Resolved: Brand=cohort, State/City/Region=profile fields; created the cohorts/fields
**Agent:** Claude Code
**What:** Resolved the open architecture question from the previous CSV
  spec entry above. Corrected `docs/ARCHITECTURE.md`'s Employee Accounts
  section: Brand is modeled as a Moodle Cohort; State, City, and Region are
  all user profile fields, not cohorts (City reuses the standard built-in
  `city` field; State and Region are new custom fields, shortnames `state`
  and `region`). Removed the prior language that described dual cohort
  membership as applying to Region. Created the two missing Brand cohorts
  (`Wok Tok` → idnumber `brand_woktok`, `Zyro` → idnumber `brand_zyro`;
  confirmed `Veeba`/`brand_veeba` already existed from Phase 1). Created a
  new "Employee Details" profile field category and the `state`/`region`
  custom text fields under it. Updated `docs/CSV_IMPORT_SPEC.md` to the
  final 9-column format and to note the `{idnumber}@noemail.vrbconsumer.
  internal` email placeholder convention.
**Why:** Cohorts are flat, unordered membership sets — they have no
  parent/child relationship to each other, so they can't represent the
  Brand → State → City *hierarchy* the leaderboard needs. Modeling Region
  as a cohort (as an earlier version of ARCHITECTURE.md's wording implied)
  would only encode that hierarchy informally, through naming convention,
  with nothing in the data model actually enforcing it. Brand is the one
  part of this model that genuinely needs cohort-level features — namely
  `enrol_cohort`, for automatically granting access to a brand's course
  category based on cohort membership — so Brand stays a cohort and
  everything location-related becomes a profile field instead, which can
  be queried/joined per-user without needing a membership-table join at
  all.
**Files touched:** `docs/ARCHITECTURE.md` (Employee accounts & data model
  section), `docs/CSV_IMPORT_SPEC.md` (rewritten to final state),
  `LOG.md` (this entry). Database changes (not repo files, see
  verification below): `mdl_cohort` (2 new rows), `mdl_user_info_category`
  (1 new row), `mdl_user_info_field` (2 new rows).
**Verification done:** Queried `mdl_cohort` directly after creation via
  `cohort_add_cohort()` (the same function the Cohorts admin UI calls) —
  confirmed exactly 4 cohorts exist: `Veeba`/`brand_veeba` (id=1, already
  existed), `Indore`/`region_indore` (id=2, pre-existing Phase 1 leftover,
  see gotcha below), `Wok Tok`/`brand_woktok` (id=3, newly created),
  `Zyro`/`brand_zyro` (id=4, newly created). Created the profile fields
  via `profile_save_category()` and `profile_save_field()` (the same
  functions the Profile Fields admin UI calls, not a raw `INSERT`) —
  confirmed `mdl_user_info_field` now has exactly 2 rows, shortnames
  `state` and `region`, both `datatype=text`, both under the new
  "Employee Details" category (`mdl_user_info_category.id=1`). Purged
  caches (`admin/cli/purge_caches.php`) and then called
  `profile_get_user_fields_with_data()` — the exact function
  `admin/tool/uploaduser` itself uses — and confirmed it resolves the two
  fields to CSV column names `profile_field_state` and
  `profile_field_region`, matching the spec doc exactly. No custom field
  was created for `city`.
**Gotchas for future agents:**
  - The `Indore` (`region_indore`) cohort from the Phase 1 manual test
    **still exists in the database** — it was not deleted as part of this
    resolution because deletion wasn't requested. It is *not* part of the
    Brand cohort set and should not be used as a model for how Region
    works going forward; if you see it and wonder why a 5th/non-brand
    cohort exists, this entry is why. Whether to delete it is still an
    open call for the user, similar to the stray-theme-folder situation
    earlier in this log.
  - Custom profile field creation was done via `profile_save_category()` /
    `profile_save_field()` in `public/user/profile/definelib.php` — the
    same internal functions the admin UI form submits to — rather than a
    raw `INSERT INTO mdl_user_info_field`, specifically so that all the
    NOT NULL columns Moodle expects (`sortorder`, `visible`, `param1`-
    `param5`, etc., per `public/lib/db/install.xml`'s `user_info_field`
    table definition) get populated exactly as the UI would populate them.
    If creating more profile fields later, prefer this same approach over
    a raw insert.
  - The CSV format's `cohort1` column is now Brand-only, singular (no
    `cohort2`/`cohort3`) per this resolution — an employee with access to
    multiple brands needs multiple import rows (or a separate cohort
    assignment step) to get all their brand memberships from one CSV. This
    was a deliberate simplification in the final format, not an oversight
    — see `docs/CSV_IMPORT_SPEC.md` §1.

## [2026-08-19] — Deleted the leftover `Indore` cohort
**Agent:** Claude Code
**What:** Deleted the `Indore` cohort (id=2, idnumber `region_indore`),
  the last piece of cleanup from the Brand/Region architecture correction
  described in the previous entry above ("Resolved: Brand=cohort,
  State/City/Region=profile fields..."). That entry had already flagged
  this cohort as an orphaned leftover but left the deletion as an open
  call for the user; the user has now confirmed the deletion.
**Why:** `Indore` was created during the Phase 1 manual dual-membership
  test, back when an earlier (incorrect) version of ARCHITECTURE.md's
  wording implied Region might be modeled as a cohort. That model was
  corrected — Region is a profile field, not a cohort — so this cohort no
  longer corresponds to anything in the current data model. Keeping it
  around risked a future agent mistaking it for a real/active part of the
  architecture (e.g. assuming City/Region-level cohorts are a supported
  pattern) rather than a mechanical test artifact from a superseded design.
**Files touched:** none (database-only change). `mdl_cohort` (1 row
  deleted, id=2), `mdl_cohort_members` (1 row deleted, the
  `testemployee1` ↔ `Indore` membership).
**Verification done:** Before deleting, checked every table with a
  `cohortid` column for references to id=2: `mdl_cohort_members` (1 row —
  `testemployee1`), `mdl_enrol` (`enrol_cohort` instances, none),
  `mdl_role_assignments` (`component='core_cohort'`, none),
  `mdl_competency_templatecohort` (none), `mdl_tool_cohortroles` (none).
  Also confirmed `testemployee1`'s Brand-relevant cohort membership is a
  *separate* row (`Veeba`/`brand_veeba`, id=1) — deleting `Indore` does
  not remove their only brand access. Deleted via the official
  `cohort_delete_cohort()` function (`cohort/lib.php`) rather than a raw
  `DELETE`, so it also ran the competency-subsystem hook and fired the
  `cohort_deleted` event correctly. After deletion, confirmed
  `mdl_cohort` contains exactly 3 rows — `Veeba`/`brand_veeba` (id=1),
  `Wok Tok`/`brand_woktok` (id=3), `Zyro`/`brand_zyro` (id=4) — and
  confirmed zero leftover `mdl_cohort_members` rows for cohortid=2.
**Gotchas for future agents:** none — this closes out the open item from
  the previous entry. The cohort list is now exactly the 3 Brand cohorts,
  nothing else. If a `region_*` or other non-`brand_*` cohort shows up
  again later, that's new and not a resurfacing of this one — this exact
  row is gone, not just hidden.

## [2026-08-19] — Phase 2: built and tested quiz-gating end-to-end as a real employee
**Agent:** Claude Code
**What:** Built one real test brand structure for Veeba and verified the
  module-locking mechanic TASKS.md's Phase 2 calls "the single most
  important functional test in the whole project." Created: Course
  Category "Veeba" (id=2) → Course "Veeba Onboarding"
  (`veeba_onboarding`, id=2) → Section "Module 1" (quiz, 4 questions,
  60% pass grade, completion tracking + require-passing-grade) → Section
  "Module 2" (quiz, 2 questions), with Module 2's section Restrict-Access
  locked to "Module 1 Quiz: complete and passed." Added an `enrol_cohort`
  instance linking the course to the `brand_veeba` cohort so Brand
  membership (not manual enrolment) drives course access, then logged in
  as a real imported employee (`rksharma`, EMP-1001, a Veeba-cohort member
  from the Phase 1 CSV import) via actual browser automation
  (claude-in-chrome) and: (1) confirmed their course list shows only
  Veeba Onboarding, nothing from other brands or admin areas; (2)
  deliberately answered Module 1's quiz entirely wrong (0/4, 0%) and
  confirmed Module 2 stayed locked with the restriction reason visible on
  the course page; (3) re-attempted and answered entirely correctly
  (4/4, 100%) and confirmed Module 2 unlocked live, in the same session,
  without a fresh login. Documented the exact settings combination in
  `docs/ARCHITECTURE.md`'s "Quiz gating" section for replication across
  Wok Tok and Zyro.
**Why:** ARCHITECTURE.md's gating claim was previously VERIFIED only by
  reading `completionlib.php`/`mod/quiz` source (confirming the same
  `COMPLETION_COMPLETE_PASS` constant is used consistently across grading,
  completion, and restrict-access code) — never actually exercised through
  the UI as a non-admin user. TASKS.md's Phase 2 explicitly calls this out
  as not safe to assume from code inspection alone, given this project's
  history of confident-but-wrong assumptions (the `public/` restructure,
  the Moove version mismatch). Doing it as a real employee login, not an
  admin preview or an internal API call, is what actually rules out a
  whole class of bugs (capability/role differences, cache staleness,
  session-scoped `cm_info` caching) that server-side-only testing would
  miss.
**Files touched:** `docs/ARCHITECTURE.md` ("Quiz gating" section,
  rewritten with the tested settings combination). Database/course
  content (not repo files): `mdl_course_categories` (+1, "Veeba"),
  `mdl_course` (+1), `mdl_course_sections` (2, renamed + Module 2's
  `availability` set), `mdl_question_categories` (+1), `mdl_question`
  (+6), `mdl_quiz` (+2), `mdl_quiz_slots` (+6), `mdl_course_modules` (+2,
  completion settings set), `mdl_enrol` (+1, `enrol_cohort` instance),
  `mdl_user_enrolments` (+8, via cohort sync), plus the actual quiz
  attempt data (2 attempts) created by testing as `rksharma`.
**Verification done:** This entire entry *is* the verification — every
  claim above was confirmed by direct observation, not assumed: DB queries
  confirmed `sumgrades`/`gradepass`/`completionpassgrade`/`availability`
  JSON were set correctly after provisioning (see gotcha below on a
  provisioning bug this caught); a real browser session logged in as
  `rksharma` (not admin, not an internal API simulation) showed the locked
  state, the failed attempt (0/100, "Failed: Receive a passing grade"),
  the still-locked state after failing, the passed attempt (100/100), and
  the live unlock — each confirmed via screenshot at the time.
**Gotchas for future agents:**
  - **Course/quiz/question provisioning was done via Moodle's own testing
    generator classes** (`lib/testing/generator/data_generator.php` +
    `mod/quiz/tests/generator/lib.php`), called directly from a one-off
    CLI script — NOT a custom plugin, and NOT raw `INSERT` statements.
    These are the same code paths Moodle's own Behat/PHPUnit suites use to
    build fixtures, so this counts as "pure configuration" in the sense
    TASKS.md means (no bespoke gating logic was written), just done via
    script instead of clicking through `course/modedit.php` by hand.
  - `question/tests/generator/lib.php`'s `create_question()` helper
    depends on `test_question_maker`, which requires
    `PHPUnit\Framework\TestCase` — **it is PHPUnit-only and will fatal
    ("Class PHPUnit\Framework\TestCase not found") in a plain CLI
    script.** Worked around this by calling
    `question_bank::get_qtype('truefalse')->save_question($question,
    $form)` directly (the same underlying API `create_question()` itself
    calls) with a hand-built `$form` object. If provisioning more
    questions later outside PHPUnit, build the form object directly rather
    than reaching for the question generator's `create_question()`.
  - `quiz_add_quiz_question()` (the modern, non-deprecated way to add a
    question to a quiz — `mod/quiz/locallib.php`) does **not** recompute
    the quiz's `sumgrades`. Provisioning left `mdl_quiz.sumgrades = 0`
    until this was caught by a verification query and fixed by calling
    `\mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->
    recompute_quiz_sumgrades()` explicitly. `quiz_update_sumgrades()` (the
    function most Moodle knowledge/training data would suggest) is
    deprecated in this version and lives in `mod/quiz/deprecatedlib.php` —
    calling it without requiring that file fatals. If a provisioned quiz
    ever shows a 0 or wrong max grade, check this first.
  - Creating a `label` activity via the generic module generator with a
    plain string `intro` field fataled
    (`mysqli::real_escape_string(): Argument #1 ($string) must be of type
    string, array given`) — did not chase down the root cause since
    per-module content wasn't required by the task; labels were dropped
    from both modules entirely and only the graded quiz activities were
    built. If real module content (not just quizzes) needs scripted
    provisioning later, expect to need the `introeditor` array form (or
    equivalent) rather than a plain `intro` string, and verify against a
    module type that isn't `label` first since label's content-handling
    may be a special case.
  - The completion UI's "Students must receive a grade to complete this
    activity" checkbox corresponds to `completionusegrade` only at the
    form layer — the actual persisted column is
    `completiongradeitemnumber` (`0` when enabled, `null` when not).
    `add_moduleinfo()` (`course/modlib.php`) silently forces
    `completionpassgrade` back to `0` if `completiongradeitemnumber` isn't
    set, regardless of what `completionpassgrade` was passed as — so grade
    tracking must be enabled *and* recognized before pass-grade tracking
    will stick.
  - Test employee `rksharma` (EMP-1001) had its password deliberately
    reset to a known test value via `update_internal_user_password()`
    purely to run this browser-based test (their Moodle-generated random
    password from the CSV import was never known/retrievable, since the
    account has no real email for a reset link to go to — see the open
    item this surfaces below). Forced password change was also cleared for
    this account so it didn't interrupt the test flow.
  - **Open item surfaced, not resolved:** employees created via
    `admin/tool/uploaduser` with "Create password if needed" and the
    `{idnumber}@noemail.vrbconsumer.internal` placeholder email (per
    `docs/CSV_IMPORT_SPEC.md`) have no real way to receive their initial
    password — Moodle's auto-generated password is normally emailed, and
    that email will silently fail to deliver to the placeholder domain.
    This is a real gap for actual employee onboarding (not just this
    test), not something this session resolved — needs a decision on how
    employees without real email actually get their first password
    (SMS? HR hands out a printed initial-password sheet? Employee Code
    doubles as both username and initial password with forced change?).

## [2026-08-20] — Child theme `theme_vrblms` created; renderer-inheritance bug found and fixed
**Agent:** unknown (a separate session, working from prompt files at
  `docs/temp docs/child-theme-setup-prompt.md` and
  `docs/temp docs/fix-child-theme-renderer-prompt.md`, run concurrently
  with a Claude Code session doing Phase 2 employee-onboarding work).
  **Merged into this canonical log by Claude Code** on 2026-08-20 — the
  other session logged this to a new, separate `docs/LOG.md` instead of
  this file; that stray file has not been deleted, see gotcha below.
**What:** Created `theme_vrblms` at `public/theme/vrblms/` as a child of
  `theme_moove` (`$THEME->parents = ['moove']`), and made it the site's
  active theme (`$CFG->theme = 'vrblms'`, confirmed live). Activating it
  initially fataled site-wide with `Exception - Call to undefined method
  core\output\core_renderer::firstview_fakeblocks()`. Root cause: Moove
  defines a custom renderer class
  (`public/theme/moove/classes/output/core_renderer.php`) with a
  `firstview_fakeblocks()` method that Moove's own layout files call, but
  Moodle's default `standard_renderer_factory` does not walk a theme's
  *parent* chain to find custom renderer classes — it only checks the
  active theme itself, then falls back to core's renderer, which has no
  such method. Fixed with a one-line addition to
  `public/theme/vrblms/config.php`:
  ```php
  $THEME->rendererfactory = 'theme_overridden_renderer_factory';
  ```
  No passthrough `core_renderer.php` class was needed — this factory
  alone correctly walks the parent chain and resolves Moove's renderer
  methods for the child theme.
**Why:** `theme_vrblms` is the intended real branding layer for this
  project going forward (a proper child theme, rather than editing Moove
  directly, per this repo's "never modify core / never modify a
  third-party theme's own files directly" posture) — Moove itself stays
  an untouched upstream dependency, and VRB-specific branding/dashboard
  work happens in `vrblms` instead.
**Files touched:** `public/theme/vrblms/version.php` (new),
  `public/theme/vrblms/config.php` (new, includes the
  `$THEME->rendererfactory` fix). Site config: `$CFG->theme` set to
  `vrblms` (was `moove`).
**Verification done:** `core_component::get_plugin_list('theme')`
  confirmed `vrblms` detected before activation (same check this repo
  always uses for new plugins — see `Agents.md`). After the renderer fix:
  confirmed the site loads without error on the front page, login page,
  and course categories page; confirmed visual appearance is identical to
  Moove directly (expected — vrblms is currently a pure passthrough with
  no styling of its own yet); ran a second cache purge + reload to rule
  out a stale-cache false positive. Independently reconfirmed by Claude
  Code the same day: a browser-based employee login
  (`rksharma`/EMP-1001) that had hit this exact fatal error mid-test on
  the *old* active theme configuration succeeded cleanly on retry after
  this fix landed, rendering `login/change_password.php` correctly.
**Gotchas for future agents:**
  - **Any future child theme of a parent theme that defines its own
    custom renderer class must set `$THEME->rendererfactory =
    'theme_overridden_renderer_factory';` in its `config.php` from the
    start.** Skipping this is not a cosmetic issue — it's a site-wide
    fatal error the moment the child theme is activated, on every page
    that touches the parent's custom renderer methods.
  - **`$CFG->theme` is now `vrblms`, not `moove`.** Any doc or memory
    saying "Moove is the active theme" is describing the parent, not what
    Moodle is actually rendering with — `moove` is still installed and
    still the visual/behavioral source (vrblms currently has no overrides
    of its own), but treat `vrblms` as the theme going forward for
    anything that depends on which theme is *active* (e.g. Site
    Administration → Appearance → Themes will show `vrblms`, not
    `moove`). `docs/ARCHITECTURE.md` and `CLAUDE.md` have been updated to
    reflect this — see the entry below.
  - **A stray `docs/LOG.md` exists, separate from this canonical
    `LOG.md`, containing only this one entry.** This is the exact same
    "wrong location, easy to miss" failure pattern this project has hit
    twice before (the duplicate theme folder, described earlier in this
    log). It has been merged into this entry so no information is lost,
    but the stray file itself has **not** been deleted — flagging for the
    user to remove it, consistent with how the stray theme folder was
    handled earlier (flagged here, user deleted it themselves). Do not
    write future log entries to `docs/LOG.md` — this file, at the repo
    root, is the only canonical log.
  - `docs/CSV_IMPORT_SPEC.md` and `docs/vrb_employees_dummy.csv` were
    found moved into a new `docs/temp docs/` folder (alongside this other
    session's prompt files) when this entry was merged — moved back to
    their canonical `docs/` location by Claude Code the same day, since
    they're real deliverables, not scratch/prompt artifacts like the
    `*-prompt.md` files that legitimately belong in `temp docs/`.

## [2026-08-20] — Resolved the employee password-onboarding gap flagged in Phase 2
**Agent:** Claude Code
**What:** Reversed `docs/CSV_IMPORT_SPEC.md` §5's original recommendation
  (no `password` column, rely on Moodle's auto-generated + emailed
  password). New scheme: the CSV's `password` column is set to that row's
  `idnumber` (Employee Code) value, the upload form uses "New user
  password: In file" + "Force password change: All passwords", and
  Moodle forces every new account through `login/change_password.php`
  before it can be used for anything else. Re-verified end-to-end with
  the real `rksharma` (EMP-1001) account: reset its password to `EMP-1001`
  and set the `auth_forcepasswordchange` preference to `1`, then logged in
  through an actual browser session — confirmed the forced-change screen
  appeared immediately ("You must change your password to proceed."),
  completed a real password change, and confirmed normal dashboard access
  resumed afterward. Updated `docs/CSV_IMPORT_SPEC.md` (§1, §3, §5, §6)
  to reflect this as the final scheme.
**Why:** The original recommendation assumed Moodle's auto-generated
  password would reach the employee by email — but every employee without
  a real email uses the `{idnumber}@noemail.vrbconsumer.internal`
  placeholder domain (itself a deliberate design choice, see the
  2026-08-19 CSV spec entry above), which cannot receive mail. That left
  auto-generated passwords silently undeliverable and unrecoverable —
  confirmed as a real, not theoretical, problem: `rksharma` was
  completely locked out under the old scheme until this session
  administratively reset their password directly, which isn't a viable
  process for onboarding real employees at volume. Employee-Code-as-
  initial-password sidesteps needing any delivery channel at all, since
  the employee already has their own Employee Code — the forced change on
  first login is what keeps this from being a lasting weak-password
  problem.
**Files touched:** `docs/CSV_IMPORT_SPEC.md` (§1 column list, §3 example
  row, §5 rewritten, §6 summary). Database: `rksharma`'s password and
  `auth_forcepasswordchange` preference (test-only reset, then the
  employee's own real password change completed the flow during
  verification — their account is now in a normal, changed-password
  state, not left mid-test).
**Verification done:** Confirmed `$CFG->passwordpolicy` is enabled on
  this install (min 8 chars, 1 digit, 1 lower, 1 upper, 1 special char)
  and that a raw Employee Code like `EMP-1001` does **not** satisfy it
  (no lowercase letter) — then confirmed in
  `admin/tool/uploaduser/classes/process.php:1029-1033` that a
  policy-violating password is only a soft `'warning'` via
  `check_password_policy()`, not a hard row-rejection, so this doesn't
  block the import. Confirmed the exact mechanism that forces the change:
  `process.php:1061-1063` calls `set_user_preference('auth_forcepasswordchange',
  1, $user)` when `uuforcepasswordchange == UU_PWRESET_ALL` (confirmed
  constant value `2` in `admin/tool/uploaduser/locallib.php`). Confirmed
  the full flow live in a real (non-simulated) browser session — login →
  forced redirect to `login/change_password.php` → "You must change your
  password to proceed." → successful change → normal access.
**Gotchas for future agents:**
  - The first attempt at this browser retest hit the
    `firstview_fakeblocks()` theme-renderer fatal error described in the
    entry above — that was an unrelated, concurrent theme change
    (`theme_vrblms` being activated by a separate session mid-test), not
    a problem with this password scheme. If `login/change_password.php`
    ever fatals again, check the theme-renderer entry above before
    assuming it's a password-flow bug.
  - Only `rksharma` (EMP-1001) has actually been migrated to the new
    Employee-Code-as-password scheme so far. **The other 19 employees
    imported in the Phase 1 CSV import still carry the old, unusable
    auto-generated passwords** (or in some cases may never have had a
    usable one at all, per the original gap). This session did not bulk-
    reset them, since that wasn't explicitly requested — it was treated
    as a documentation/process fix for *future* imports plus a
    single-account re-verification, consistent with how `rksharma` was
    handled in the original Phase 2 entry. If real use of those 19
    accounts is needed before a fresh import happens, they'll need either
    a bulk password reset to match this scheme, or a fresh CSV
    re-import using the corrected format.
  - `docs/vrb_employees_dummy.csv` (the actual 20-row file used for the
    Phase 1 import) was **not** regenerated with a `password` column —
    it still reflects what was actually imported at the time (no password
    column, old scheme). Treat `docs/CSV_IMPORT_SPEC.md` as the
    forward-looking template for the next real import, not this file.

## [2026-08-20] — Replicated the Veeba module-gating structure for Wok Tok and Zyro
**Agent:** Claude Code
**What:** Built the same category → course → 2-gated-module structure
  documented in `docs/ARCHITECTURE.md`'s "Quiz gating" section (originally
  built and tested for Veeba, see the 2026-08-19 Phase 2 entry above) for
  the other two brands:
  - **Wok Tok:** Category id=3, Course `woktok_onboarding` id=3, Module 1
    Quiz cmid=5 (4 questions, 60% pass), Module 2 Quiz (2 questions),
    Section 2 restrict-access keyed to cmid=5 COMPLETE_PASS, `enrol_cohort`
    linked to `brand_woktok`.
  - **Zyro:** Category id=4, Course `zyro_onboarding` id=4, Module 1 Quiz
    cmid=8 (4 questions, 60% pass), Module 2 Quiz (2 questions), Section 2
    restrict-access keyed to cmid=8 COMPLETE_PASS, `enrol_cohort` linked
    to `brand_zyro`.
  Cohort sync ran for both immediately after creating the enrolment
  method (not left for a scheduled task), confirmed via enrolled-user
  counts matching the Phase 1 CSV's brand distribution: Wok Tok 7
  employees, Zyro 6 (plus Veeba's earlier 7, inc. `testemployee1` = 20
  total across all three, matching the 20-row import exactly). Then spot-
  checked Wok Tok end-to-end as a real employee: reset `priyagupta`
  (EMP-1002)'s password to her Employee Code via the newly-resolved
  scheme from the entry above, forced password change, logged in through
  an actual browser session, confirmed her course list showed only "Wok
  Tok Onboarding" (nothing from Veeba/Zyro/admin), confirmed Module 2
  showed locked with the restriction reason visible, answered Module 1's
  quiz correctly (same True/False pattern as every brand's quiz, since
  all three were generated from the same question template), and
  confirmed Module 2 unlocked live afterward — same result as Veeba's
  original Phase 2 test.
**Why:** TASKS.md's phased plan calls for validating the gating mechanic
  once (Veeba, Phase 2) and then replicating it across brands using the
  documented recipe rather than re-deriving it — this is exactly that
  replication step, using the provisioning script's approach from the
  original Veeba build (Moodle's own testing-generator APIs, not a custom
  plugin) generalized to take brand name/slug/cohort as parameters.
**Files touched:** none in the repo (all database/course content, same
  categories of tables as the original Veeba entry, ×2). The provisioning
  script itself
  (`/private/tmp/.../scratchpad/provision_brand.php`) is a
  session-scratchpad file, not committed to the repo.
**Verification done:** DB queries confirmed `sumgrades`, `gradepass=60`,
  and each course's Section 2 `availability` JSON referencing the correct
  per-course Module 1 Quiz `cmid` (5 for Wok Tok, 8 for Zyro — these
  differ from Veeba's cmid=2 since course_modules ids are sequential
  across the whole site, not per-course) before any browser testing.
  Confirmed via direct DB query that `priyagupta` has exactly one cohort
  membership (`brand_woktok`) and exactly one course enrolment (Wok Tok
  Onboarding, via cohort) — this was checked specifically because the
  course-overview page showed 3 skeleton-loading placeholder cards for a
  moment before settling to the correct 1, which could have been mistaken
  for a real over-enrolment bug if not checked against the DB directly.
  Full browser-based pass-and-unlock cycle confirmed via screenshots at
  each step, same as the original Veeba test.
**Gotchas for future agents:**
  - `mod_quiz_generator::create_instance()` + `quiz_add_quiz_question()`
    still does not auto-recompute `sumgrades` (same gotcha as the
    original Veeba entry) — the generalized provisioning script calls
    `\mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->
    recompute_quiz_sumgrades()` explicitly right after adding questions,
    for both quizzes, every brand. If writing a new provisioning script
    from scratch (not reusing this one), don't forget this step again.
  - Course module ids (`cmid`) are **global/sequential across the whole
    site**, not scoped per course — Veeba's Module 1 Quiz is cmid=2,
    Wok Tok's is cmid=5, Zyro's is cmid=8. Don't hardcode a cmid assuming
    it'll be the same across brands/environments; always look it up via
    `get_coursemodule_from_instance()` like the provisioning script does.
  - The "3 skeleton cards briefly visible, then 1 real card" behavior on
    `/my/courses.php` is a normal AJAX-loading artifact of this Moodle
    version's course-overview block, not a bug — confirmed by checking
    the DB the first time this was seen. Don't re-investigate it as if
    it's new each time; take a screenshot after at least a 2-second wait
    post-navigation before trusting what a course-overview screenshot
    shows.
  - Only `rksharma` (Veeba) and `priyagupta` (Wok Tok) have been migrated
    to the Employee-Code-as-password scheme so far, both for testing
    purposes. No Zyro employee has been password-reset or browser-tested
    yet — the Zyro course structure is verified via DB only, following
    the same pattern as how Veeba's structure was DB-verified before its
    own browser spot-check. If a Zyro-specific browser confirmation is
    ever wanted, the same steps used for Wok Tok's `priyagupta` apply
    directly to any Zyro-cohort employee (e.g. `amitpatel`, EMP-1003).

## [2026-08-20] — Removed stray `docs/LOG.md`; codified file-location rules in `Agents.md`
**Agent:** Claude Code
**What:** At the user's explicit request: confirmed the stray
  `docs/LOG.md` flagged in the "Child theme `theme_vrblms`" entry above
  was already fully merged into this canonical `LOG.md` (it was, as of
  that same entry), then deleted `docs/LOG.md` outright. Also moved
  `docs/vrb_employees_dummy.csv` into `docs/temp docs/` (alongside the
  prompt files already there) — the user clarified that generated/sample
  data like this belongs in `temp docs/`, not `docs/` directly; only
  `docs/CSV_IMPORT_SPEC.md` itself (the actual spec, not the sample data)
  stays in `docs/` as a core reference doc. Added a new "File locations"
  section to `Agents.md` (and a short pointer to it in `CLAUDE.md`)
  spelling out the rule going forward: `LOG.md` only ever lives at the
  repo root; `docs/` is for durable/canonical reference docs; `docs/temp
  docs/` is for task-scoped scratch artifacts (briefing prompts, generated
  sample data) that aren't required reading and aren't guaranteed to stay
  accurate.
**Why:** The stray `docs/LOG.md` was a live instance of a failure pattern
  this project has now hit twice — a file landing in a plausible-looking
  but wrong location, undetected until someone happens to notice. The
  first time was a duplicate theme folder; this time it was a duplicate
  log. Writing the rule down explicitly (rather than relying on this
  log's entries as the only record) is meant to stop a third occurrence,
  including from sessions that haven't read this deep into `LOG.md`.
**Files touched:** `Agents.md` (new "File locations" section, inserted
  before "Required reading"), `CLAUDE.md` (new short "File locations"
  section), `docs/LOG.md` (deleted), `docs/vrb_employees_dummy.csv` →
  moved to `docs/temp docs/vrb_employees_dummy.csv`.
**Verification done:** Diffed `docs/LOG.md`'s content against this file's
  existing 2026-08-20 "Child theme `theme_vrblms`" entry before deleting,
  to confirm nothing would be lost (confirmed — that entry already covers
  everything `docs/LOG.md` contained, just reformatted into this log's
  standard entry structure rather than copied verbatim). Confirmed
  `docs/` and `docs/temp docs/` end in the state described above via
  directory listing.
**Gotchas for future agents:** None beyond what's now in `Agents.md`
  directly — that file is the canonical statement of this rule going
  forward; treat this entry as the historical record of why it exists,
  not as something you need to re-derive from here each time.

## [2026-08-20] — Bulk-migrated remaining 18 employees to Employee-Code-as-password
**Agent:** Claude Code
**What:** At the user's request, reset all imported employee accounts
  except `rksharma` and `priyagupta` (already migrated and left alone,
  per the two entries above) to the Employee-Code-as-initial-password
  scheme from `docs/CSV_IMPORT_SPEC.md` §5: password set equal to that
  account's `idnumber`, `auth_forcepasswordchange` preference set to `1`.
  18 accounts updated: `amitpatel` (EMP-1003) through `ashokpandey`
  (EMP-1020) — every user with an `idnumber LIKE 'EMP-%'` except the two
  explicitly excluded.
**Why:** User asked directly, after being told which 2 of the 20 imported
  accounts currently had a usable/known password and which 18 didn't —
  this closes that gap for the rest of the test data, using the exact
  same scheme already verified twice (Veeba/`rksharma`,
  Wok Tok/`priyagupta`).
**Files touched:** none (database only). `mdl_user.password` and the
  `auth_forcepasswordchange` user preference for the 18 accounts listed
  above.
**Verification done:** Script output confirmed all 18 target accounts
  were matched and updated, and that `rksharma`/`priyagupta` were
  correctly skipped (not touched). Not individually browser-tested per
  account — the forced-change flow itself is already verified working
  (twice, on two different brands) in the entries above, so this is
  applying a known-good mechanism at bulk scale, not re-verifying it.
**Gotchas for future agents:** Every one of the 20 imported employees can
  now log in with `username` = their Employee Code (e.g. `amitpatel` /
  `EMP-1003`) except `rksharma` and `priyagupta`, whose passwords are
  `NewPass!2026` (they already completed the forced-change flow during
  earlier testing — see the two Phase 2 entries above). If any of the 18
  are used for further testing and their password gets changed through
  the normal forced-change flow, update this note or add a new entry —
  don't assume Employee-Code-as-password still applies to an account
  once someone's actually logged into it and changed it.

## [2026-08-20] — Status re-check, login/dashboard usability pass, multi-brand test account
**Agent:** Claude Code
**What:** Three things, at the user's request:
  1. **Re-verified** (not re-derived from memory) that the Wok Tok/Zyro
     replication and the password-reset flow from the entries above
     actually landed: DB queries confirmed all 3 brand courses have
     correct `sumgrades`/`gradepass`/locked-Section-2 config and expected
     enrolment counts (8/7/6), and confirmed via `validate_internal_user_password()`
     that `rksharma`/`priyagupta` have changed passwords while a fresh
     sample of the bulk-reset accounts still have password == idnumber
     with a pending forced change — all without needing a browser login
     to check.
  2. **Login page + post-login landing, "usable now" pass** (full design
     pass explicitly deferred, per the user): added
     `theme/vrblms/style/custom.css` wired via `$THEME->sheets`, and set
     `$CFG->defaulthomepage = HOMEPAGE_MYCOURSES`. See the new
     "Login/post-login flow" subsection in `docs/ARCHITECTURE.md`'s
     theming section for the reasoning and exact mechanism — not
     repeated here.
  3. **Multi-brand test account:** added `kavitayadav` (previously
     Wok-Tok-only, EMP-1014) to `brand_veeba` and `brand_zyro` as well
     (already in `brand_woktok`), ran `enrol_cohort_sync()` for all 3
     brand courses, confirmed she now shows 3 enrolments. Logged in as
     her through an actual browser session: forced password change
     worked (same as every other account in the bulk reset), and
     post-login landed directly on a 3-card "My courses" grid — one card
     per brand (Veeba/Wok Tok/Zyro), confirming the `defaulthomepage`
     change actually produces a working multi-brand chooser, not just a
     configured-and-assumed one. Also clicked into Veeba Onboarding from
     that grid to confirm normal course entry and the Module 2 lock are
     both unaffected by the CSS/config changes.
**Why:** User explicitly asked for a "quick status check before building
  more" (item 1) and then, separately, for the login/dashboard work to
  prioritize working flow over visuals, with an explicit safety concern:
  don't repeat today's earlier `firstview_fakeblocks()` outage by working
  carelessly in the same fragile child-theme layer. That's why item 2
  avoided the SCSS/`$THEME->scss` pipeline entirely and used the simpler,
  already-proven-safe `$THEME->sheets` mechanism instead, and why the
  post-login "brand tile" experience reuses Moodle's own working "My
  courses" page rather than introducing a new custom PHP endpoint that
  would need its own login/context/security handling to get right.
**Files touched:** `docs/ARCHITECTURE.md` (new "Login/post-login flow"
  subsection under theming). `public/theme/vrblms/config.php`
  (`$THEME->sheets = ['custom'];`). `public/theme/vrblms/style/custom.css`
  (new file). Database: `$CFG->defaulthomepage` set to `3`
  (`HOMEPAGE_MYCOURSES`); `kavitayadav`'s cohort memberships (+2),
  enrolments (+2), password (changed via the same forced-change flow as
  every other test account), `auth_forcepasswordchange` preference
  (cleared after her real change).
**Verification done:** All of item 1 above (DB-level, no assumptions
  carried over from earlier entries without re-checking). For item 2/3:
  real browser session, screenshots at each step — clean login page with
  the orange background gone, forced-change screen for `kavitayadav`,
  the 3-card multi-brand grid, and a successful click-through into Veeba
  Onboarding with Module 2 still correctly locked.
**Gotchas for future agents:**
  - The orange background pattern on the login page is **not** on `body`
    or `.login-wrapper` (both plausible-looking guesses) — it's a
    data-URI SVG background on `#page` (`container-fluid pt-5 mt-0`),
    found by querying `getComputedStyle()` on every element via the
    browser's JS-exec tool rather than guessing from the DOM structure.
    If more login-page styling is needed later, remember `#page` is in
    play, not just the more obviously-named login-specific classes.
  - `$THEME->sheets` looks like a legacy/dead property at first glance
    (empty in Boost's and Classic's own `config.php`, and a grep of
    `lib/outputlib.php` turns up nothing) — it's actually still fully
    live, just handled inside the `theme_config` class
    (`lib/classes/output/theme_config.php`), not free functions. Don't
    conclude it's non-functional from an `outputlib.php`-only grep.
  - `kavitayadav`'s password is now `NewPass!2026` (same pattern as
    `rksharma`/`priyagupta` — she completed the forced-change flow during
    this verification). If you need a multi-brand test account again and
    she's already been used/changed further, any employee can be added to
    the other 2 brand cohorts the same way — it's a 5-line script, see
    this entry's "What" section for the exact calls
    (`cohort_add_member()` + `enrol_cohort_sync()` per course).
  - This was **not** a redesign — Moove's actual visual identity,
    layout, and branding are all still fully intact everywhere except the
    login background and a light card-hover polish. Don't read this
    entry as "theming is done"; it's explicitly a stopgap ahead of a
    later full design pass, per the user.

## [2026-08-20] — Fixed misaligned admin tree/settings list layout (quiz sidebar + Site administration)
**Agent:** Claude Code
**What:** At the user's request ("quiz-layout... basic alignment and some
  color fixes," "admin panel too... looking very bad/distorted"), fixed
  two real layout issues, both via the same low-risk
  `theme/vrblms/style/custom.css` mechanism as the login/dashboard work
  above:
  1. **Site administration overview** (`admin/search.php`): each category
     ("AI", "Competencies", etc.) sits in a Bootstrap `.col-sm-3` next to
     its links in a `.col`. `.col-sm-3` reserves 25% width regardless of
     how short the label text is, so a 2-letter category like "AI" left a
     huge, visually unbalanced empty gap before its links. Narrowed that
     column (`flex: 0 0 180px`) and added row separators.
  2. **"Navigation"/"Settings" tree blocks** (sidebar blocks shown to
     admin/teacher roles on course and activity pages, e.g. the quiz
     attempt page) — tree entries were wrapping mid-word ("Competencies"
     as "Compete\nncies") with icons misaligned from their text. Fixed
     with basic block-level spacing/wrapping rules on `.tree_item` scoped
     to `.block_navigation`/`.block_settings`.
**Why:** User asked for "basic alignment and color fixes," explicitly not
  a full redesign — deferred to later, per the pattern set in the
  previous entry. Both fixes are plain CSS in the already-proven-safe
  `$THEME->sheets` file, not SCSS/renderer changes.
**Files touched:** `public/theme/vrblms/style/custom.css` (new rules
  appended; an earlier draft of the admin/search.php rules was written,
  found not to match the real DOM, and replaced — see gotcha below).
**Verification done:** Real browser screenshots before/after for both
  pages, logged in as admin (the actual admin password, provided by the
  user in chat after a temporary-reset alternative was offered and
  declined). Confirmed via `getComputedStyle()` that the fix actually
  changed the rendered layout, not just that the CSS rule existed in the
  compiled stylesheet.
**Gotchas for future agents:**
  - **First attempt targeted the wrong DOM entirely and had zero visible
    effect**, despite the CSS rules being present and even "matching"
    real elements when queried carelessly. Moodle's admin pages render a
    `ul/li/p.tree_item`-based accessible nav tree **twice** — once as the
    real, visible content (or, on `admin/search.php`, the real content is
    actually plain Bootstrap `.row`/`.col` markup, not a tree at all),
    and once as a duplicate positioned far off-screen (found at
    `getBoundingClientRect().left` ≈ 1900+ on a ~1568px-wide viewport) —
    almost certainly for the collapsible drawer/keyboard-nav version of
    the same tree. A naive `document.querySelectorAll('*').find(...)` or
    `[...document.querySelectorAll('a')].filter(text match)` query
    without checking `getBoundingClientRect()` against actual viewport
    bounds **will silently grab the off-screen duplicate**, and any CSS
    written against what you find that way can end up correct-looking in
    a JS check yet invisible on screen. Always filter by
    `rect.left >= 0 && rect.left < viewportWidth` (or equivalent) before
    trusting an element you found by text/class match on a Moodle admin
    page — this cost real back-and-forth this session before being
    caught.
  - Confirmed `$THEME->sheets` content does get correctly concatenated
    into the single combined `theme/styles.php/vrblms/<rev>/all` output
    Moodle actually serves — checked via `curl` + `grep` on that URL
    directly when debugging why a rule "wasn't applying." Useful
    diagnostic step if this happens again: confirm the rule is in the
    served CSS at all before assuming a specificity/DOM-targeting
    problem, then check specificity/DOM targeting once presence is
    confirmed.
  - `admin/search.php`'s very first settings group (directly under the
    active top-level tab, e.g. "General") legitimately renders with no
    category label in its `.col-sm-3` — that's normal Moodle behavior
    (items belonging directly to the active category need no further
    sub-heading), not a bug. Don't "fix" it by fabricating a label.

## [2026-08-20] — Reverted admin/search.php CSS; fixed the real employee quiz-taking flow instead
**Agent:** Claude Code
**What:** At the user's explicit redirect ("primarily change quiz page,
  not site administration things"), reverted the `#page-admin-search`
  rules added in the entry immediately above (removed from
  `theme/vrblms/style/custom.css`, cache purged, confirmed the page is
  back to its prior unstyled state). Left the `.block_navigation`/
  `.block_settings` sidebar tree fix from that same entry in place — it's
  a different component, doesn't affect employees at all (they never see
  that block), and wasn't what the user asked to revert. Then logged in
  as a real employee (`rksharma`) and walked the entire quiz-taking flow
  end-to-end — `mod/quiz/view.php` (quiz landing) → `attempt.php`
  (answering questions, all 4 pages) → `summary.php` → `review.php` (the
  results/feedback page) — specifically looking for real distortions to
  fix, per the user's instruction to focus only on the employee quiz flow
  this round. Found and fixed two real bugs, and re-verified the fixes
  hold across a second quiz (Module 2) and via a full fresh attempt
  (fail/pass cycle not repeated — this pass, already-passed status was
  used since the gating mechanic itself was already proven in earlier
  entries; this session's focus was visual, not functional, verification).
**Why:** User's priority shifted from admin-page polish to the actual
  employee-facing quiz experience, which is the core product interaction
  (per PROJECT_CONTEXT.md — quiz gating is "the core mechanic of the
  whole product"). Also explicitly invited fixing anything else "worth
  changing" across pages an employee visits, not just the quiz pages
  literally — the review-page summary-tile fix below came from following
  that invitation, not a literal "quiz page" request.
**Files touched:** `public/theme/vrblms/style/custom.css` (removed the
  `#page-admin-search` block; added a new "Quiz attempt page" section and
  a new "Quiz review page" section).
**Verification done:** Real browser screenshots at every step of the quiz
  flow, before and after each fix, logged in as an actual employee
  account (not admin, not simulated) — consistent with this project's
  established standard of not trusting configuration/CSS changes without
  seeing them render for the actual user type they're meant for. Also
  re-confirmed the admin/search.php page reverted correctly (matches its
  pre-fix screenshot) after removing that CSS block.
**Gotchas for future agents:**
  - **The `.qtext` height bug (~240px for one line of text) was invisible
    to computed-style inspection at first** — `getComputedStyle()` showed
    no explicit `height`/`min-height` rule anywhere (confirmed by
    searching every loaded stylesheet's `cssRules` for `.qtext`/
    `.clearfix` selectors with height properties — zero matches), no
    `aspect-ratio`, no `content-visibility`/`contain-intrinsic-size`, no
    flex/grid stretch on any ancestor, and even `height: fit-content
    !important` injected live still computed to the same wrong 238px —
    meaning the browser's own "natural" content-based sizing was
    genuinely (and incorrectly) arriving at that value, not being
    overridden by some findable rule. **What actually worked:**
    `display: flow-root !important` injected live collapsed it instantly
    to the correct ~22px. This confirms the mechanism was a missing block
    formatting context — `.qtext`, in normal flow, was including space to
    clear a floated sibling (Moodle's `.info` panel is floated in the
    classic quiz question layout) even though visually there was no
    reason for it to. If a similarly "impossible" height/sizing bug shows
    up elsewhere in this theme, **try `display: flow-root` on the
    suspect element early** — it resolves an entire class of
    float-interaction bugs that are otherwise very hard to find via
    computed-style forensics alone, since computed styles show the
    *result* of the float interaction, not that a float interaction is
    the cause.
  - `.moove-info-container` / `.moove-infobox` / `.moove-infobox-title` /
    `.moove-infobox-content--small` are Moove's own component classes
    (not generic Bootstrap), used for the quiz review page's summary
    strip. Confirmed via `getComputedStyle` that `.moove-info-container`
    was computing to `display: block` rather than the flex row its
    structure clearly implies — fixed generically (not scoped to the quiz
    review page), so this same fix improves any other Moove page using
    this same "row of stat tiles" pattern, not just quiz review. Worth
    checking the course page and profile page for the same component if
    doing further employee-flow polish later.
  - Reverting a CSS block is as simple as it sounds here — no cache
    subtlety beyond the same `admin/cli/purge_caches.php` used for every
    other change in this theme. Confirmed by direct before/after
    screenshot comparison that the revert fully restored the prior
    (unfixed) appearance, not a partial/cached mix of old and new.

## [2026-08-20] — Fixed two more quiz-page issues the user spotted (question heading overflow, box color)
**Agent:** Claude Code
**What:** User provided a screenshot of an in-progress quiz question and
  called out two specific problems the previous pass had missed: the
  "Question N" heading in the left info panel was overflowing/wrapping
  awkwardly ("Question" / "1" on two lines, visually cramped against the
  panel edges), and the question box's flat teal/cyan background looked
  dated. Root-caused and fixed both in `theme/vrblms/style/custom.css`:
  1. The heading is `<h4 class="h3 w-100 mb-2">Question <span
     class="rui-qno">1</span></h4>` — Bootstrap's `.h3` utility class
     (computed font-size 26.25px) forced to 100% width inside a `.info`
     panel only ~105px wide. Sized it down to `1.05rem` so "Question N"
     fits on one line for any single/double-digit question number.
  2. `.formulation`'s background was a flat `rgb(204,230,234)` (~`#cce6ea`)
     applied unconditionally, regardless of answered/correct/incorrect
     state. Replaced with a neutral light gray-blue (`#f7f9fc`) + a subtle
     1px border, which reads as a clean card rather than a saturated flat
     color block, and doesn't fight with the actual state-indicator icons
     (the green check / red X Moodle already renders per-answer).
  Verified against the exact scenario in the user's screenshot: created a
  fresh attempt (`sunitakumari`, EMP-1004, Veeba — a Veeba employee who
  hadn't attempted this quiz yet, since `rksharma` had already exhausted
  all 3 allowed attempts), answered Q1 incorrectly, submitted, and
  confirmed the reviewed "Incorrect" state (red X icon, "The correct
  answer is 'True'" feedback banner) now renders cleanly with the fixed
  heading and background.
**Why:** The previous quiz-page pass tested the "Correct"/passing state
  (`rksharma`'s 100% attempt) and the multi-line `.qtext` bug, but didn't
  specifically screenshot-compare the "Question N" heading width or the
  "Incorrect" state's visual — the user caught what direct DOM/computed-
  style inspection alone didn't surface as clearly as an actual screenshot
  did. Reinforces this project's repeated lesson: verify against a real
  rendered screenshot, not just passing functional checks or computed
  styles, especially for anything explicitly about "how it looks."
**Files touched:** `public/theme/vrblms/style/custom.css` (`.que .info
  h4.h3` font-size rule added; `.que .formulation` background/border
  changed from the earlier border-radius-only rule).
**Verification done:** Live browser screenshots before and after, as a
  real employee, reproducing the exact "Incorrect" scenario from the
  user's own screenshot (not just the "Correct" case already covered by
  the prior entry). Confirmed via a fresh account+attempt rather than
  reusing `rksharma`'s exhausted attempts, so this is a genuinely
  independent verification, not a repeat of the same attempt data.
**Gotchas for future agents:**
  - `rksharma`'s Module 1 Quiz attempts are now exhausted (3 of 3 used,
    all from earlier sessions' testing) — `sunitakumari` (EMP-1004) was
    used for this round's fresh-attempt testing instead and now also has
    an attempt on record (1 of 3 used, scored 0/4 since only Q1 was
    deliberately answered, incorrectly, and Q2-4 were left unanswered on
    purpose to reach the review page quickly). If more fresh-attempt
    testing is needed on Veeba later, prefer an employee who hasn't used
    any attempts yet, or reset attempt counts via the DB rather than
    assuming any given test account still has attempts available.
  - The `.info` panel's ~105px width is itself untouched/not investigated
    further — this fix only reduced the heading font-size to fit within
    it, it didn't widen the column. If a future design pass wants a
    visually larger "Question N" heading, that requires understanding the
    same `.info` column-width mechanism from the earlier `.qtext` flow-
    root investigation, not just bumping font-size again.

## [2026-08-20] — New minimal front page (separate from Moove's original) + sitewide transparent-nav fix
**Agent:** Claude Code
**What:** Two more fixes from the user, at the end of this session:
  1. **Logged-out front page**, corrected after a first attempt was
     rejected. First attempt reconfigured `theme_moove`'s own frontpage
     settings (turned off `displaymarketingbox`/`numbersfrontpage`,
     turned on `slidercount=1` with a custom title, hid the stock slide
     photo via CSS) — functionally fine, but the user wanted the
     *original* Moove frontpage content/config left completely alone and
     a genuinely separate new page built instead, to keep as a reference
     for a later real design pass. Reverted all of it: `set_config`
     back to `displaymarketingbox=1`, `numbersfrontpage=1`,
     `slidercount=0`; `unset_config` on `slidertitle1`/`slidercap1`
     (confirmed this restores the exact original `false`/unset state, not
     just empty strings). Removed the `#mooveslideshow`-scoped CSS from
     `custom.css`. Then built a real separate implementation: `theme/
     vrblms/layout/frontpage.php` (new file — a trimmed copy of Moove's
     own frontpage.php, same drawer/navbar/session wiring, but skipping
     `\theme_moove\util\settings::frontpage()` and rendering a new
     `theme_vrblms/frontpage` template instead of `theme_moove/frontpage`)
     + `theme/vrblms/templates/frontpage.mustache` (new file — reuses
     `theme_moove/navbar` and `theme_moove/footer` as partials, with just
     one `.vrb-hero` section in between: an `<h1>` with a CSS fade-up,
     nothing else). `custom.css` got new `.vrb-hero`-scoped rules
     (replacing the removed `#mooveslideshow` ones) — background gradient,
     `clamp()`-based responsive font size, `@keyframes vrb-hero-fade-up`,
     a `prefers-reduced-motion` fallback. Verified in a real browser at
     both desktop and a 390×844 mobile viewport, and confirmed the "Log
     in" link in the new page's header still correctly reaches the login
     page (the drawer/navbar wiring copied from Moove's original file
     works as expected).
  2. **Sitewide fixed nav bar rendering fully transparent**
     (`nav.navbar.fixed-top { background-color: rgba(0,0,0,0) }`),
     reproduced on the Calendar page per the user's screenshot, then
     re-confirmed on "My courses" and Site Administration too since it's
     the one shared nav every logged-in page uses. Fixed with an explicit
     solid background (`#ffffff`, `#1a1a2e` under
     `[data-bs-theme="dark"]`) + a subtle shadow.
**Why:** (1) The user's correction was explicit and specific: don't
  repurpose/reconfigure the existing Moove frontpage assets, build a new
  one and leave the original as an untouched reference — "eventually we
  will style the original page, but later" implies the ORIGINAL demo
  template is the intended future starting point, not something to have
  been silently overwritten via config in the meantime. (2) Same root
  cause and reasoning as documented for the CSS-scoped fixes earlier this
  session — a plain, low-risk `!important`-free override in `custom.css`
  (via `$THEME->sheets`), not a SCSS/renderer change.
**Files touched:** `public/theme/vrblms/layout/frontpage.php` (new),
  `public/theme/vrblms/templates/frontpage.mustache` (new),
  `public/theme/vrblms/style/custom.css` (`#mooveslideshow` rules removed
  and replaced with `.vrb-hero` rules; new `nav.navbar.fixed-top` rule
  added). Database: `theme_moove` config for `displaymarketingbox`,
  `numbersfrontpage`, `slidercount`, `slidertitle1`, `slidercap1` — all
  reverted/unset back to original values (see "What" above for exact
  values). `public/theme/moove/layout/frontpage.php` and `public/theme/
  moove/templates/frontpage.mustache` — confirmed NOT touched, still
  exactly as shipped.
**Verification done:** Real browser screenshots: front page at desktop
  width (clean gradient hero, fade-up visible, "Available courses"
  listing below it still working), at a 390×844 mobile viewport (heading
  wraps and scales via `clamp()`, still fully visible — unlike Moove's
  own slideshow caption default, which is `d-none` below the `md`
  breakpoint, this new template has no such restriction since it's not
  using that component at all), and a full click-through from the new
  front page's "Log in" link to a working login page. Nav-bar fix
  verified scrolled-and-not on 3 different logged-in pages (Calendar, My
  courses, Site administration).
**Gotchas for future agents:**
  - **Every Moodle page layout file must produce `{{{ output.main_content
    }}}` somewhere in its rendered output, or `core_renderer::header()`
    throws `coding_exception: "... does not contain the main content
    placeholder"` and the page fails to load entirely.** This was hit
    directly building the new frontpage template — trimming Moove's
    template down to "just the hero" initially dropped this, since in the
    original file it's buried inside the `#region-main` wrapper deep in
    content that otherwise looked like removable demo boilerplate. This
    is **not** boilerplate — every custom layout/template pair needs it.
    Confirmed the exact check lives in
    `public/lib/classes/output/core_renderer.php` (~line 887); it checks
    the *rendered output* for a token, not the raw template source, so
    `{{{ output.main_content }}}` genuinely has to execute, not just
    exist as a comment.
  - The "Available courses" listing below the hero on the new front page
    is **not custom content** — it's core Moodle's own default frontpage
    behavior for the `#region-main` region (site category/course
    listing), inherited for free by keeping that wrapper for the
    `main_content` requirement above. If a future design pass wants the
    front page to be hero-only with nothing below it, that region can be
    removed, but doing so means re-solving the `main_content` placeholder
    requirement some other way (e.g. an empty/hidden wrapper) rather than
    just deleting the block outright.
  - Moove's original frontpage assets are correctly still present and
    completely unused by `vrblms` — don't be confused if you find TWO
    frontpage.php/frontpage.mustache pairs in the theme directories (one
    under `moove/`, one under `vrblms/`); this is intentional, not a
    duplicate-in-wrong-location bug like the earlier theme-folder/log-file
    incidents. The `moove/` copy is the deliberate reference kept for
    later; only the `vrblms/` copy is actually rendered.
  - Same nav-bar transparency root cause as the earlier employee-flow
    header fix this session (see the "!important... likely a media-query-
    scoped rule" gotcha in the entry above) — if it resurfaces after a
    Moodle core or Boost/Moove update, re-check whether the `!important`-
    free override still wins before assuming something new broke.

## [2026-08-20] — Phase 3: built and verified `local_vrblms` (shared leaderboard/service plugin)
**Agent:** Claude Code
**What:** Built the `local_vrblms` plugin at `public/local/vrblms/` per
  `TASKS.md` Phase 3 — the shared Brand+Region-joined quiz-ranking service
  layer that `block_vrblms_leaderboard` (Phase 4) and `report_vrblms`
  (Phase 5) are meant to consume, rather than each re-implementing the
  join. Structure:
  - `classes/local/attempt_repository.php` — all raw SQL against
    documented core tables only (`{cohort}`, `{cohort_members}`,
    `{enrol}`, `{quiz}`, `{quiz_attempts}`, `{user}`, `{user_info_data}`,
    `{user_info_field}`); no new DB tables, this plugin is read-only.
    Resolves the `state`/`region` profile field ids by shortname at
    runtime (cached per-request) rather than hardcoding their numeric ids.
  - `classes/ranking/` — `ranking_strategy` interface,
    `attempt_selection_strategy` abstract base (shared aggregation logic),
    and two concrete strategies: `best_attempt_strategy` (per-quiz max
    score) and `first_attempt_strategy` (per-quiz first attempt,
    retries ignored). `strategy_manager` is the registry/factory both
    `settings.php` and `api.php` go through — nothing hardcodes strategy
    names outside this one class.
  - `settings.php` — one `admin_setting_configselect` under Site
    Administration → Plugins → Local plugins ("VRB LMS leaderboard
    service") for the default ranking strategy — this is what makes the
    ranking formula genuinely admin-configurable per `TASKS.md`'s explicit
    requirement, not just structurally pluggable in code.
  - `classes/api.php` — the one public class other plugins should call:
    `get_brands()`, `get_states($brand)`, `get_cities($brand, $state)`
    (all three back Phase 4's filter dropdowns), and
    `get_leaderboard($brand, $state, $city, $strategykey, $limit)`, which
    returns every member of the brand's cohort (including employees with
    zero attempts, scored 0%/`0 of N` — the leaderboard shows the full
    roster, not just people who've started) ranked by
    `(score_percent desc, total_time_seconds asc among those who've
    completed at least one quiz, fullname asc)`.
**Why:** `PROJECT_CONTEXT.md` states the ranking formula is a genuinely
  open business decision, so `TASKS.md` requires the query layer to accept
  a ranking strategy as config/parameter rather than hardcoding one
  formula — implemented two working strategies (not just one, which is
  all `TASKS.md` strictly required) specifically to prove the pluggable
  design actually works end-to-end, not just structurally allow for it
  later. This was flagged as the highest-value remaining item for the
  client showcase, so it was built and verified directly against the real
  installed Moodle source and the real 20-employee/3-brand test dataset,
  not from general Moodle knowledge.
**Files touched:** `public/local/vrblms/version.php`, `settings.php`,
  `lang/en/local_vrblms.php`, `classes/api.php`,
  `classes/local/attempt_repository.php`,
  `classes/ranking/{ranking_strategy,attempt_selection_strategy,
  best_attempt_strategy,first_attempt_strategy,strategy_manager}.php`
  (all new). Database: `local_vrblms` plugin registration + version row
  (via `admin/cli/upgrade.php`) and one config value
  (`local_vrblms/defaultstrategy`), no schema/tables added.
**Verification done:**
  - Confirmed no pre-built Moodle API returns this cohort/profile-joined
    shape before writing custom SQL: `cohort/lib.php` has no
    `cohort_get_members()` function at all in this version (grepped the
    whole codebase, not just the one file), and `mod/quiz`'s own overview
    report (`mod/quiz/report/overview/report.php`) is scoped to one
    quiz/course, not cross-course — matches `ARCHITECTURE.md`'s existing
    "no single official API" note, this isn't a missed-API shortcut.
  - Verified every `$DB` method signature used
    (`get_records_select`, `get_fieldset_select`, `get_fieldset_sql`,
    `get_in_or_equal`, `sql_like`) directly against
    `lib/dml/moodle_database.php` before using it, and
    `admin_setting_configselect`'s constructor directly against
    `lib/adminlib.php`, rather than assuming from general Moodle
    knowledge.
  - `core_component::get_plugin_list('local')` confirmed `vrblms` detected
    before install. `admin/cli/upgrade.php --non-interactive` installed it
    cleanly (`-->local_vrblms ++ Success++`, new setting registered), no
    errors.
  - Ran a CLI verification script (session-scratchpad, not committed —
    same pattern as Phase 2's provisioning verification) calling every
    `api.php` method against the real dataset and hand-checked results
    against raw `{quiz_attempts}` rows:
    - `rksharma` (userid=4): best-attempt strategy → 100% (their 2nd/3rd
      attempts scored 4/4); first-attempt strategy → 0% (their 1st
      attempt scored 0/4) — exactly the divergence expected from their
      known 0%→100%→100% attempt history from the Phase 2 entries above.
    - `priyagupta` (userid=5): 75% under both strategies (only one
      attempt per quiz exists, so best==first) — 100% on Module 1
      (quiz=3, 4/4) averaged with 50% on Module 2 (quiz=4, 1/2), matching
      her known attempt rows exactly.
    - `kavitayadav` correctly appears in all three brands' leaderboards
      (Veeba/Wok Tok/Zyro), confirming the multi-brand cohort membership
      from the earlier entry is correctly reflected.
    - The orphaned `testemployee1` (Phase 1's manual test account,
      brand_veeba member, no profile-field/city data) appears correctly
      with a 0% score and blank state/city rather than crashing the
      directory join — a real edge case in the live data, not a
      synthetic one.
    - `get_states('brand_veeba')` / `get_cities('brand_veeba', 'Madhya
      Pradesh')` and the leaderboard's own `?state=`/`?city=` filters
      were cross-checked against each other and against the source CSV
      data and matched exactly.
  - Browser-verified (logged in as real admin, not simulated) that Site
    Administration → Plugins → Local plugins → "VRB LMS leaderboard
    service" renders the strategy dropdown and description text
    correctly with no page errors; separately confirmed via CLI
    (`set_config`/`get_config` round-trip) that changing the setting
    actually changes which strategy class `strategy_manager::get_strategy
    (null)` resolves to, then reverted the value back to `best` (the
    intended shipped default) afterward so no test state was left behind.
**Gotchas for future agents:**
  - There is genuinely no `cohort_get_members($cohortid)` function in
    this Moodle version — don't go looking for one from memory. Query
    `{cohort_members}` directly (it's a documented core table, two
    columns, `cohortid`/`userid`), which is what `attempt_repository`
    does.
  - `profile_user_record()` loads one user's profile fields at a time —
    deliberately not used here, since a leaderboard needs bulk
    state/region lookups for an entire brand's roster in one query.
    `attempt_repository::get_employee_directory()` does its own bulk
    `{user_info_data}` join instead (fetching user rows and profile rows
    as two separate queries and joining in PHP, not a SQL `CASE`-pivot —
    simpler to read, avoids any cross-DB-driver aggregation quirks for
    just two extra columns).
  - `state`/`region` profile field ids are looked up by shortname at
    runtime (`attempt_repository::get_profile_field_id()`, statically
    cached per request) rather than hardcoded as `1`/`2` — they happen to
    be `1`/`2` in this environment today, but that's an artifact of
    creation order, not a guarantee; don't hardcode them if extending this
    code.
  - The leaderboard's tie-break logic special-cases zero-attempt
    employees: two people who've both completed zero quizzes tie-break on
    name, not on `total_time_seconds` (which would otherwise put every
    0%-scored, zero-attempt employee ahead of anyone with real but slower
    completion time, since 0 seconds is falsely "fastest"). If extending
    the ranking logic later, keep this guard — it not just a stability
    tie-break, it fixes a real correctness bug that showed up immediately
    in the verification output (see Veeba's leaderboard: all the
    zero-attempt employees sort by name, not arbitrarily).
  - No PHPUnit harness exists in this environment (`phpunit.xml` absent,
    `admin/tool/phpunit` not present at the expected path) — don't assume
    one is available for future plugin work either; the CLI-script
    verification pattern used here (and in Phase 2) is the working
    substitute for this project.
  - Phase 4/5 should call `\local_vrblms\api::*` only — the repository and
    ranking classes are internal implementation detail, not meant to be
    called directly by the block/report plugins.

## [2026-08-20] — Phase 4: built, deployed, and verified `block_vrblms_leaderboard`
**Agent:** Claude Code
**What:** Built the regional leaderboard block at
  `public/blocks/vrblms_leaderboard/`, per the user's explicit instruction
  to build Phase 4 and stop there (not Phase 5/`report_vrblms`). It calls
  `\local_vrblms\api` exclusively — no query/ranking logic duplicated in
  the block, per `TASKS.md`'s Phase 4 requirement. Structure: `version.php`
  (declares `$plugin->dependencies = ['local_vrblms' => 2026082100]`),
  `db/access.php` (`block/vrblms_leaderboard:myaddinstance` at
  CONTEXT_SYSTEM granted to the `user` archetype — i.e. every authenticated
  employee, not just admins/teachers — so any employee can add this to
  their own Dashboard; `:addinstance` at CONTEXT_BLOCK for
  editingteacher/manager, the standard pattern for course/site pages),
  `lang/en/block_vrblms_leaderboard.php`, and
  `block_vrblms_leaderboard.php` (the block class). Renders four filters —
  Brand, State, City, Ranking (strategy) — as a plain `<form method="get">`
  with `onchange="this.form.submit()"` auto-submit selects (inline HTML
  attribute, not an AMD module) and a ranked table (#, Employee, Location,
  Score, Modules, Time) below it. Deliberately did not use the AMD/JS
  build pipeline for the filter interactivity, since `ARCHITECTURE.md`
  flags that pipeline as unverified in this environment — GET-param
  filtering means the whole thing works via plain page reloads, no JS
  dependency at all.
**Why:** This was flagged by the user as the natural next step after
  `local_vrblms` (previous entry) for the client showcase — a working,
  visible leaderboard, not just the service layer behind it. The
  capability design (any employee can self-add it to their Dashboard) was
  a deliberate choice matching `PROJECT_CONTEXT.md`'s framing of the
  leaderboard as an employee-facing, motivational feature, not an
  admin-only report.
**Files touched:** `public/blocks/vrblms_leaderboard/{version.php,
  block_vrblms_leaderboard.php, db/access.php,
  lang/en/block_vrblms_leaderboard.php}` (all new).
  `public/theme/vrblms/style/custom.css` (new "VRB LMS Leaderboard block"
  section — see gotcha below on why this was needed, not optional
  polish). Database: `block_vrblms_leaderboard` plugin registration (via
  `admin/cli/upgrade.php`); `$CFG->defaulthomepage`-adjacent state
  untouched. Site config: added a block instance to the **Default
  Dashboard page** template (`/my/indexsys.php`) and ran **"Reset
  Dashboard for all users"** (Site Administration → Appearance → Default
  Dashboard page) so all 20 existing test employees' *already-created*
  personal dashboards got the block too, not just new logins going
  forward — see gotcha below on what this actually does before reusing it.
**Verification done:**
  - `core_component::get_plugin_list('block')` confirmed `vrblms_
    leaderboard` detected; `admin/cli/upgrade.php --non-interactive`
    installed cleanly, no errors. `php -l` confirmed no syntax errors
    before ever loading it in a browser.
  - Verified `block_base`'s real method signatures/defaults directly
    against `public/blocks/moodleblock.class.php` (not `lib/blocklib.php`,
    which despite the name only holds `block_manager` — the actual
    `block_base` class lives in the `blocks/` folder, easy to guess wrong)
    before writing `applicable_formats()`/`get_content()` — confirmed the
    *default* `applicable_formats()` already covers course/site/my pages,
    so no override was even needed. Verified `html_writer::select()`,
    `::span()`, `moodle_url::params()`/`out_omit_querystring()`, and
    `admin_setting_configselect`'s constructor directly against their
    source in `lib/` before use, same standard as the Phase 3 entry above.
  - Cross-checked `db/access.php`'s capability shape against core's
    `block_html` (`blocks/html/db/access.php`) — same
    `myaddinstance`/`addinstance` pattern, same `clonepermissionsfrom`
    targets, rather than guessing the capability names/structure.
  - Browser-verified end to end, twice, as different real accounts (not
    admin-only, not simulated): (1) as **admin**, added the block to a
    Dashboard via the real "Add a block" UI flow, confirmed the Brand/
    State/City/Ranking filters and ranked table render, and confirmed
    filtering via URL params (`?vrbbrand=brand_woktok&vrbstrategy=first`)
    correctly re-renders the block with Wok Tok's roster and
    first-attempt scores. (2) as **`rksharma` (EMP-1001, a real employee,
    not admin)**, logged in fresh, used "Customise this page" → "Add a
    block" → "VRB LMS Leaderboard" himself (proving the `myaddinstance`
    capability grant actually works for the `user` archetype, not just
    admin who has every capability regardless), and confirmed it renders
    correctly showing him ranked #1 in Veeba. (3) as **`priyagupta`
    (EMP-1002, Wok Tok)**, an account that was never touched by hand,
    confirmed after the "Reset Dashboard for all users" step that the
    block now appears on her Dashboard automatically, with no per-account
    action needed — this is what actually confirms the rollout mechanism
    works for existing accounts, not just new ones.
**Gotchas for future agents:**
  - **`block_base` lives in `public/blocks/moodleblock.class.php`, not
    `public/lib/blocklib.php`** — the latter is 2800+ lines but is
    entirely `block_manager`/rendering-pipeline code, zero mention of
    `block_base`. Grepping the wrong file first cost a few minutes this
    session; grep across `public/` broadly (`grep -rl "class block_base"
    public/`) rather than guessing the file from the name.
  - **The ranked table genuinely overflowed the block's right edge and
    lost two whole columns (Modules, Time) with no way to reach them** —
    this was caught by an actual screenshot, not assumed from the HTML.
    `html_writer::table()` output has no built-in overflow handling; a
    6-column table does not fit a ~250-300px sidebar block. Fixed with
    `.block_vrblms_leaderboard .content { overflow-x: auto; }` plus a
    smaller/`white-space: nowrap` table in `custom.css` (same file/
    mechanism as every other theme fix this project has made). This is
    not "later branded design" scope creep — content that's silently
    unreachable is a real bug, not a cosmetic one, especially given
    PROJECT_CONTEXT.md's explicit mobile-responsiveness requirement.
  - **`html_writer::select()` does not escape option labels** (only
    optgroup labels — confirmed by reading its docblock and
    implementation directly). Brand/state/city values are free-text,
    DB-sourced strings (HR/CSV-imported), not a fixed enum, so
    `block_vrblms_leaderboard.php` wraps every label in `s()` explicitly
    before building the `$options` arrays passed to `render_select()`. If
    adding more filter dropdowns later, don't assume `html_writer::select`
    escapes for you.
  - **"Reset Dashboard for all users"** (the button on
    `/my/indexsys.php`) retroactively overwrites every existing user's
    *already-customized* personal Dashboard layout back to the current
    Default Dashboard page template — confirmed via the live progress bar
    ("Resetting user dashboards to default... 100%") and by observing
    `priyagupta`'s previously-existing dashboard change. This is a
    genuinely broad, hard-to-reverse action (any per-user dashboard
    customization is gone) — safe to use here only because all 20
    accounts are this project's own test data with no real
    customization to lose. **Do not reuse this against a live/production
    user base without explicit confirmation** — it would wipe real
    employees' personal dashboard layouts.
  - The block's filters are plain GET-param page reloads
    (`?vrbbrand=...&vrbstate=...&vrbcity=...&vrbstrategy=...`), not AJAX —
    intentional, see "Why" above. If a future design pass wants
    no-page-reload filtering, that would need the AMD/JS pipeline
    verification that `ARCHITECTURE.md` explicitly flags as still open;
    don't add AMD JS to this block without doing that verification first.
  - Not done in this pass (out of scope per the user's explicit "only go
    till leaderboard" instruction): `report_vrblms` (Phase 5, admin
    CSV-exportable report) and the certificate-qualification trigger.
    `local_vrblms\api` is already shaped to support both without changes.

## [2026-08-21] — Leaderboard moved from sidebar block to its own page; role-based views; cross-brand "Overall"; a real SQL param-ordering bug found and fixed
**Agent:** Claude Code
**What:** At the user's explicit direction, replaced `block_vrblms_
  leaderboard` (previous entry) with a dedicated page,
  `public/local/vrblms/leaderboard.php`, with two distinct views gated by
  a new capability:
  - **`local/vrblms:viewfullleaderboard`** (new `db/access.php`,
    CONTEXT_SYSTEM, `manager` archetype CAP_ALLOW — site admins already
    bypass all capability checks, so this alone correctly gates
    "admin-like" access without hardcoding `is_siteadmin()`, and leaves
    room for a future non-admin manager role). Holders get the same
    Brand/State/City/Ranking filter form the block had (relocated, not
    duplicated, into a new `classes/output/leaderboard_view.php` helper
    class), plus a new **"Overall (all brands)"** brand option.
  - Everyone else gets a fixed, unfiltered view: one leaderboard section
    per brand they actually belong to (via a new `api::get_user_brands()`
    → `attempt_repository::get_user_brand_cohorts()`), plus one "Overall"
    section — their own row highlighted (`.vrb-leaderboard-own-row`,
    `custom.css`) in every table so "where am I" is immediate. Zero
    filter controls anywhere on this path, per the user's explicit
    "only the admin can change stuff like all states, city, brand."
  - **"Overall" is a genuine cross-brand combined leaderboard** — one row
    per employee, aggregating attempts across *every* brand course
    they've taken, not per-brand-then-summed and not restricted to
    employees sharing the same brand combination. New `attempt_
    repository` methods (`get_all_brand_members()`, `get_all_brand_
    quizzes()`, `get_distinct_states_all_brands()`, `get_distinct_
    cities_all_brands()`) source this without touching the ranking layer
    at all — `ranking_strategy::evaluate()` already only cares about "this
    user's attempts" + "the relevant quiz set," so widening that set from
    one brand to all of them required zero changes there. `api.php`'s
    `get_leaderboard()` and the new `get_overall_leaderboard()` now share
    one `build_ranked_rows()` private method (refactored out) instead of
    duplicating the scoring/sorting/ranking block.
  - **Discoverability:** new `public/local/vrblms/lib.php` implements
    `local_vrblms_extend_navigation(global_navigation $navigation)`,
    adding a "Leaderboard" primary-nav link for every logged-in user —
    confirmed (not assumed) this legacy per-plugin callback is still
    dispatched in this Moodle version, see gotcha below.
  - **`block_vrblms_leaderboard` was fully removed** — `admin/cli/
    uninstall_plugins.php --plugins=block_vrblms_leaderboard --run` (a
    clean uninstall, not just deleting files) followed by deleting
    `public/blocks/vrblms_leaderboard/` outright.
**Why:** User's explicit direction: "instead of this [sidebar block],
  let's do a change... We make leaderboard as a page of its own, only
  the admin can change stuff like all states, city, brand for
  themselves... an employee should see the leaderboard for his/her own,
  they can see where they are on that specific course and overall... The
  admin meanwhile can check leaderboard of specific course AND overall
  too, filter by brands, state, city etc." A clarifying question resolved
  two ambiguities before building: (1) the user explicitly said the
  "per-course settings" idea from my first read was **not** wanted — they
  just meant keep the existing Brand/State/City/Ranking filters as-is,
  so no new per-brand config concept was built; (2) what "overall" means
  was left to my judgment ("even I am not sure... I will let you make the
  decision"), resolved as the union-based cross-brand design above,
  chosen specifically because it satisfies the admin's own stated need
  ("wanna see leaderboard of entire [company] to see which employee is
  performing the best") with one mechanism, and because a single-brand
  employee's "overall" then trivially degenerates to their one-brand
  score (verified below), rather than needing special-casing.
**Files touched:** New: `public/local/vrblms/{leaderboard.php, lib.php,
  db/access.php, classes/output/leaderboard_view.php}`. Modified:
  `public/local/vrblms/classes/api.php` (added `get_overall_leaderboard()`,
  `get_overall_states()`, `get_overall_cities()`, `get_user_brands()`,
  refactored shared logic into `build_ranked_rows()`); `public/local/
  vrblms/classes/local/attempt_repository.php` (added the four
  cross-brand methods above, **and fixed a param-ordering bug in four
  existing methods — see gotcha below**); `public/local/vrblms/lang/en/
  local_vrblms.php` (new strings); `public/local/vrblms/version.php`
  (bumped, to register the new capability); `public/theme/vrblms/style/
  custom.css` (swapped the old `.block_vrblms_leaderboard .content`
  overflow rule for a page-scoped `.vrb-leaderboard-table-wrap` one, and
  added the `.vrb-leaderboard-own-row` highlight). Deleted: `public/
  blocks/vrblms_leaderboard/` (entire plugin, all files — never
  committed to git, so nothing shows in `git status` for its removal).
  Database: block plugin uninstalled cleanly (config_plugins/block rows
  removed, all instances gone including the ones on the Default
  Dashboard template and every reset user dashboard from the previous
  entry); `local_vrblms` capability registered via `admin/cli/upgrade.php`.
**Verification done:**
  - `core_component::get_plugin_list('block')` confirmed `vrblms_
    leaderboard` gone after uninstall+delete; `get_plugin_list('local')`
    still resolves `vrblms` after adding `lib.php`/`db/access.php`.
    `admin/cli/upgrade.php --non-interactive` registered the new
    capability with no errors. `php -l` on every new/changed file before
    ever loading it in a browser.
  - Confirmed `navigation_node::add()`'s signature directly against
    `lib/classes/navigation/navigation_node.php:390`, and confirmed the
    legacy `local_*_extend_navigation()` callback is still dispatched by
    grepping for where `get_plugin_list_with_function('local',
    'extend_navigation')` is actually called
    (`lib/classes/navigation/global_navigation.php:469`) — not assumed
    from general Moodle knowledge, since this project has been burned by
    exactly that kind of assumption before.
  - **CLI verification script (scratchpad) caught a real bug before any
    browser testing**, then re-run after the fix — see gotcha below for
    the bug itself. Also confirmed: `get_overall_leaderboard()` returns
    exactly 21 rows (20 imported employees + `testemployee1`, no
    duplicates despite `kavitayadav` belonging to 3 cohorts — the `SELECT
    DISTINCT` in `get_all_brand_members()` is doing its job); `rksharma`'s
    overall score (100.00%) exactly matches his Veeba-only score even
    though the denominator changed from 2 total quizzes to 6 — the
    "single-brand overall == single-brand score" sanity check the design
    was chosen to satisfy, confirmed empirically, not just argued for.
  - Browser-verified as three real accounts, logged in fresh each time
    (not admin-impersonated, not simulated):
    - **admin**: full page with all four filters; defaults to "Overall
      (all brands)"; switching Brand/State/City/Ranking via the
      auto-submitting selects correctly re-queries (confirmed via
      `?vrbbrand=brand_woktok&vrbstate=Maharashtra` showing exactly the
      3 correct Maharashtra Wok Tok employees after the bug fix below);
      "Leaderboard" nav link present and correctly routes here.
    - **`rksharma`** (single-brand, Veeba): page shows exactly one
      "Veeba" section + one "Overall" section, zero filter controls
      anywhere, his own row visibly bold/highlighted in both tables
      (confirmed the CSS class was actually applied and computed to the
      intended `rgb(255, 243, 205)` background via `getComputedStyle()`,
      not just assumed from the screenshot — the highlight is real but
      visually subtle, screenshots alone didn't make it obvious).
    - **`kavitayadav`** (multi-brand: Veeba + Wok Tok + Zyro): page shows
      **three** brand sections (Veeba, Wok Tok, Zyro) plus one Overall
      section, her own row highlighted in all four tables — this is the
      concrete confirmation of the multi-brand "each course, and overall"
      requirement from the user's original request, not just a
      single-brand approximation of it.
**Gotchas for future agents:**
  - **Found and fixed a real, pre-existing SQL parameter-ordering bug**
    while testing the new "Overall" state filter, in code originally
    written during the Phase 3 entry above. `attempt_repository`'s
    `SQL_PARAMS_QM` (`?`) queries build their `$params` array by
    appending values in **code execution order** (cohortid/brand-match
    first, then join-related params, then where-related params), but
    Moodle's DB layer binds `?` placeholders by their **left-to-right
    position in the final SQL text** — and in every affected method, the
    `$joins` string (containing the state-filter's `fieldid = ?`
    placeholder) is concatenated into the SQL **before** the `WHERE`
    clause, while its param was being pushed into the array *after* the
    cohortid/brand param. This silently mis-binds values whenever a state
    filter is combined with a brand/cohort filter — `get_leaderboard
    ('brand_woktok', 'Maharashtra')` returned **zero** rows (should be
    3: `priyagupta`, `kavitayadav`, `vikramsingh`) because `cohortid` got
    bound to the `fieldid = ?` slot and `statefieldid` got bound to the
    `cohortid = ?` slot. **This went completely undetected in the Phase 3
    verification pass** because the one state-filtered test case tried
    there (`brand_veeba` + `Madhya Pradesh`) happened to work anyway —
    Veeba's cohort id and the `state` profile field's id are both `1` in
    this database, so the swapped bind values were coincidentally
    identical and masked the bug entirely. **Fixed** in
    `get_cohort_members()`, `get_distinct_cities()`, `get_all_brand_
    members()`, and `get_distinct_cities_all_brands()` by tracking
    `$joinparams` and `$whereparams` as separate arrays and merging them
    join-first (`array_merge($joinparams, $whereparams)`) rather than
    pushing everything into one array in code order. **If writing any
    more raw-SQL methods in this file (or anywhere using `SQL_PARAMS_QM`
    placeholders with a dynamically-built `$joins` string), verify the
    params array matches the *rendered SQL text's* left-to-right
    placeholder order, not the order the code happens to build them in —
    and don't trust a single lucky-coincidence test case as proof a query
    is correct.** `get_distinct_states()` and `get_distinct_states_all_
    brands()` were never affected — they only ever have one join
    placeholder and it was already ordered correctly relative to the one
    where-clause placeholder.
  - `html_table_row` (aliased as `\html_table_row`, real class at
    `lib/table/classes/output/html_table_row.php`) supports per-row
    `->attributes['class']` — this is how the own-row highlight is
    applied; confirmed this before assuming `html_writer::table()`
    offered per-row styling some other way.
  - The "Reset Dashboard for all users" step from the previous entry is
    now moot for the leaderboard specifically (the block that step
    distributed no longer exists), but the Default Dashboard template
    and every user's personal dashboard still have an empty/broken
    reference to nothing in particular — Moodle's clean plugin uninstall
    already removed the actual block instances as part of this session's
    uninstall step, so there is nothing left to clean up; don't re-run
    "Reset Dashboard for all users" expecting to find leftover block
    instances, there aren't any.
  - `report_vrblms` (Phase 5) and the certificate-qualification trigger
    remain out of scope, not started.

## [2026-08-29] — Employee-UI design-match: Phase 0 (token layer) + Phase 1 (index page)
**Agent:** Claude Code
**What:** First two phases of the employee-UI design-match build (plan:
  `docs/temp docs/employee-ui-build-plan.md`, target: `docs/design_refer/`).
  A design-match audit earlier the same day found the live build was
  essentially stock Moove/Boost (no Inter, Boost-blue primary, shadowed
  cards, conecti.me footer credit visible). All work is in `theme_vrblms`
  (child of Moove) — `style/custom.css` + the theme's own front-page
  layout/template. No core edits, no `theme_moove` edits, no `$THEME->scss`.
  - **Phase 0 (foundation, `style/custom.css` rewritten & reorganised):**
    added an `@import` for Inter (Google Fonts) + a `:root` design-token
    block (navy `#000B43`/`#041C72`, surface `#F8F9FA`, border `#E9ECEF`,
    text ramp, brand accents Veeba/Wok Tok/Zyro/warm, success/error,
    radii card 8px / control 4px / pill 12px). Global rules: Inter on
    body/headings/controls, body 16px, flat elevation
    (`.card,.block{box-shadow:none!important;border:1px solid var(--vrb-border);
    border-radius:8px}`), navy `.btn-primary`/`.btn-outline-primary` +
    focus ring, 4px control radius, navy content links with an explicit
    exclusion list for chrome (navbar/drawer/dropdown/secondary-nav/
    buttons) so nothing renders navy-on-navy, navy top-nav wordmark, navy
    course `.secondary-navigation` bar (both `.secondary-navigation` and
    its inner `.navigation`, so the side gutters recolour too), and hid
    the `#page-footer .copyright`/`.madeby` conecti.me credit strip. All
    the pre-existing targeted fixes (login bg, nav-transparency,
    `.qtext`/`.formulation`/`.info` quiz fixes, `.moove-info-container`
    flex, admin tree spacing, leaderboard table/own-row/filters) were
    kept, re-slotted under numbered section banners.
  - **Phase 1 (index / logged-out landing):** reworked the theme's own
    `layout/frontpage.php` + `templates/frontpage.mustache` (theme_moove's
    frontpage assets still untouched). New hero content: "VRB LMS"
    wordmark (LMS in warm yellow), `herotitle`/`herosubtitle` from new
    theme lang strings, a single CTA that is "Log in" for anonymous
    visitors and "My courses" for logged-in ones (`isloggedin`/`loginurl`/
    `mycoursesurl` passed from `frontpage.php`), and a "Secure internal
    portal" caption. The inherited `#region-main` (site course search) is
    kept in the DOM — `core_renderer::header()` still fatals without the
    `main_content` placeholder in the *rendered* output, LOG.md 2026-08-20
    — but wrapped in `.vrb-frontpage-hidden { display:none !important }`.
    Front-page navbar forced to solid white + navy text in both colour
    schemes (the hero is always dark navy; matches the "dashboard" design
    screen's white top bar).
**Files touched:** `public/theme/vrblms/style/custom.css` (rewritten),
  `public/theme/vrblms/layout/frontpage.php` (hero context vars),
  `public/theme/vrblms/templates/frontpage.mustache` (hero markup +
  `.vrb-frontpage-hidden` wrapper), `public/theme/vrblms/lang/en/theme_vrblms.php`
  (`herotitle`, `herosubtitle`, `secureportal`),
  `public/theme/vrblms/version.php` (`2026082000` → `2026082900`).
  `docs/temp docs/employee-ui-build-plan.md` (new, the full build plan).
**Verification done:** `php -l` on all changed PHP; `admin/cli/upgrade.php
  --non-interactive` (`theme_vrblms ++ Success ++`); `purge_caches.php`
  after each change. Live browser (Chrome ext, real sessions):
  - `getComputedStyle` on `/my/courses.php`: `body` font-family starts
    `Inter`, size 16px; `.btn-primary` background `rgb(0, 11, 67)` radius
    4px no shadow; `.block` white, 8px radius, no shadow, 1px
    `rgb(233,236,239)` border; navbar `.navbar-brand` `rgb(0,11,67)`.
  - conecti.me orange strip gone from the footer on every logged-in page
    checked (`/my/courses.php`, `/course/view.php`, front page).
  - Front page (`/?redirect=0`): renders the navy hero, wordmark,
    heading/subtitle, CTA. Confirmed the CTA text is visible
    (`rgb(0,11,67)` navy on white) after fixing an `a.btn { color: inherit }`
    rule that was making it white-on-white — now
    `a.btn:not(.vrb-hero__cta)`. Logged-out shows "Log in" CTA + footer
    "You are not logged in."; logged-in (kavitayadav) shows "My courses"
    CTA.
  - `/login/index.php`: heading now navy, "Log in" button now navy
    `#000B43` (the old login-specific `#2a4494` override was removed;
    Phase 0's navy primary flows through). Card is still Moove's
    full-width one — Phase 2 narrows/accents it.
  - `/course/view.php?id=2` as `rksharma`: secondary nav bar fully navy
    edge-to-edge with white links, section cards flat/8px, "Done"/"To do"
    pills intact, Module 2 unlock state unaffected.
**Gotchas for future agents:**
  - The test Chrome profile is in **dark mode**, so pages get
    `data-bs-theme="dark"` on `<body>` (set by the frontpage template's
    own inline JS and Moove elsewhere). The sitewide
    `[data-bs-theme="dark"] nav.navbar.fixed-top` rule turned the
    front-page navbar `#1a1a2e`; the fix pins the front-page navbar
    (`body#page-site-index`) to solid white in *both* schemes. Watch for
    other dark-mode-only rules when checking screenshots.
  - `a.btn { color: inherit }` (added to stop navy link colour bleeding
    onto non-primary buttons) is specific enough (0,0,1,1) to beat a
    single-class button colour rule — it clobbered `.vrb-hero__cta`. Now
    scoped `a.btn:not(.vrb-hero__cta)`, and the CTA rule is
    `.vrb-hero .vrb-hero__cta` for headroom. Any future themed button
    that is an `<a>` needs the same treatment or an explicit `color`.
  - `body,#page{background:var(--vrb-surface)}` (#F8F9FA) does **not** win
    against Moove's own body/`#page` background (`#f2f3f7` still computed)
    — a barely-perceptible difference, left as-is; raise specificity
    (`#page.drawers`, `body.pagelayout-*`) if it ever matters.
  - Chrome autofill kept repopulating the login username field mid-type
    during verification (`triple_click`+`ctrl+a`+`Delete` still left
    fragments). `form_input` with the element ref set it cleanly — prefer
    that over simulated typing for login fields.
  - Phases 2 (login), 3 (my-courses brand grid), 4 (leaderboard) not
    started. Phase 5 (course-format card grid, deep quiz restyle) is
    explicitly deferred — see the plan file.

## [2026-08-29] — Employee-UI Phase 0 follow-up: force light colour scheme + global text colours
**Agent:** Claude Code
**What:** Fixed unreadable dark-on-dark chrome reported by the user (the
  front-page user menu: navy text on a dark-navy dropdown). Root cause:
  the test browser's OS is in dark mode and the front-page template's own
  JS was setting `data-bs-theme="dark"` on `<body>`, so Bootstrap's dark
  dropdown/navbar tokens kicked in — and the Phase 0 front-page navbar
  rule `nav.navbar.fixed-top a { color: var(--vrb-navy) !important }` was
  broad enough to also recolour the user-menu dropdown links nested inside
  the navbar, giving navy-on-dark.
  Fixes, all in `theme/vrblms`:
  - **`style/custom.css`:** new "Force the light colour scheme everywhere"
    block — `:root, [data-bs-theme="dark"], body[data-bs-theme="dark"]`
    redefines the Bootstrap dark tokens (`--bs-body-bg/-color`,
    `--bs-secondary/tertiary-*`, `--bs-border-color`, `--bs-link-color*`,
    and the full `--bs-dropdown-*` set) back to the VRB light palette +
    `color-scheme: light`. The VRB design is light-only ("Neutral-First",
    ~90% white) so there is no dark variant to support.
  - Global text-colour rules: `h1–h6/.h1–.h6 → --vrb-text`;
    `p,li,dd,dt,td,th,label,.form-label,figcaption → inherit`;
    `.text-muted/.text-secondary/small/.small/.dimmed_text → --vrb-text-2
    !important`.
  - Explicit `.dropdown-menu` / `.dropdown-item` / hover / `.dropdown-header`
    / `.dropdown-divider` rules (white bg, `--vrb-text`, navy hover) so
    every menu is readable regardless of scheme. Removed `.dropdown-item`
    from the `color: inherit` chrome list (it was inheriting a dark
    parent's colour).
  - Front-page navbar override rescoped from `nav.navbar.fixed-top a` to
    the navbar's own link classes only (`.navbar-brand`,
    `.primary-navigation .nav-link`, `.moremenu .nav-link`,
    `> .navbar-nav .nav-link`) so it no longer touches the nested
    user-menu dropdown.
  - Removed the now-obsolete `[data-bs-theme="dark"]` rules added in the
    first Phase 0 pass (`nav.navbar.fixed-top` dark bg,
    `.vrb-leaderboard-own-row` dark bg).
  - **`templates/frontpage.mustache`:** deleted the `require(['jquery'])`
    block that set `data-bs-theme` from `prefers-color-scheme`. The
    front page (and, via the CSS above, the rest of the site) now renders
    light regardless of OS preference.
**Files touched:** `public/theme/vrblms/style/custom.css`,
  `public/theme/vrblms/templates/frontpage.mustache`. No version bump
  (CSS/template only; caches purged).
**Verification done:** `purge_caches.php`. Live browser (session logged in
  as `rksharma`, OS in dark mode):
  - Front page (`/?redirect=0`): opened the user menu — now white
    background, `rgb(25,28,29)` text on every item (Accessibility /
    Profile / Grades / Calendar / Private files / Reports / Preferences /
    Log out). `getComputedStyle('.dropdown-menu.show')` → bg
    `rgb(255,255,255)`, item colour `rgb(25,28,29)`. `document.body`
    `data-bs-theme` is now `light` (was `dark`).
  - `/my/courses.php`: `data-bs-theme=light`, `h2` colour `rgb(25,28,29)`,
    no dark surfaces.
**Gotchas for future agents:**
  - Moove ships a light/dark toggle in the navbar (the little switch near
    the user avatar). With the force-light CSS in place, toggling it to
    dark now has almost no visible effect — acceptable and on-design, but
    if a user asks "why doesn't dark mode work", this is why. Hiding the
    toggle was considered and skipped (low harm); revisit if it confuses
    people.
  - `body,#page{background:var(--vrb-surface)}` (#F8F9FA) still loses to
    Moove's own `#f2f3f7` body background — imperceptible, left as-is.
  - Any future themed control that is an `<a>` inside `.navbar` needs an
    explicit `color` (the `nav.navbar a { color: inherit }` rule + the
    dark-token neutralisation cover the common cases, but a hard-coded
    light colour on a custom navbar link will still need care).

## [2026-08-29] — Employee-UI Phase 2: login page
**Agent:** Claude Code
**What:** Restyled `/login/index.php` to the VRB design (branded card,
  navy accent, labelled fields with icons). All in `theme_vrblms`.
  - **New override `templates/core/loginform.mustache`** (based on
    `theme_moove/templates/core/loginform.mustache`, same field names /
    hidden inputs / `{{#js}}` wiring). Changes: a `.vrb-login-wordmark`
    ("VRB LMS") above the heading; a `.vrb-login-subtitle`; the username
    `<label>` is now visible and reads **"Username / Employee Code"**
    (new string `theme_vrblms/loginusername`), placeholder "e.g. EMP-1001"
    (`loginusernameplaceholder`); the password `<label>` is visible;
    both inputs wrapped in `.vrb-input-wrap` with a left FontAwesome icon
    (`fa-user` / `fa-lock`); a `.vrb-login-secure` "Secure internal
    portal" caption (reuses `theme_vrblms/secureportal`) below the form.
  - **`style/custom.css` section 5 rewritten:** card `max-width:460px`,
    centred, 1px `--vrb-border`, 8px radius, `0 4px 12px rgba(0,0,0,.05)`
    shadow, `padding:0` + `overflow:hidden` with a `::before` 4px navy
    top accent bar flush to the edge; `.loginform` gets the 2rem padding;
    heading navy 1.375rem/600 centred + muted subtitle; `.vrb-login-label`
    bold 0.875rem; `.vrb-input-wrap` icon absolutely positioned, input
    `padding-left:2.4rem` + `width:100%`; `#loginbtn` full width; forgot-
    password link right-aligned small; guest/cookies/lang row
    de-emphasised below a `--vrb-border` divider.
  - New lang strings in `lang/en/theme_vrblms.php`: `loginusername`,
    `loginusernameplaceholder`, `loginsubtitle`.
  - `version.php` → `2026082901`.
**Files touched:** `public/theme/vrblms/templates/core/loginform.mustache`
  (new), `public/theme/vrblms/style/custom.css` (section 5),
  `public/theme/vrblms/lang/en/theme_vrblms.php`,
  `public/theme/vrblms/version.php`.
**Verification done:** `php -l` lang file; `admin/cli/upgrade.php
  --non-interactive` (`++ Success ++`); `purge_caches.php` per change.
  Live browser (logged out): card renders at 460px, centred, navy accent
  bar, wordmark, heading, subtitle, "Username / Employee Code" label +
  person icon, password + lock icon, full-width navy "Log in", right-
  aligned "Lost password?", "🔒 Secure internal portal". `getComputedStyle`
  widths: `.login-container` 460, `.loginform` 458, inner col 394,
  `#loginbtn` 394. **Login submission tested end-to-end** — `rksharma` /
  `NewPass!2026` authenticated and landed on `/my/courses.php` ("Hi,
  Rajesh!"), so the form wiring is intact.
**Gotchas for future agents:**
  - **`.login-container` is a flex container in Moove's SCSS**, so the
    form (`{{{ output.main_content }}}` = `.loginform.row`) rendered as a
    flex *item* and collapsed to its min-content width (~183px) —
    everything wrapped character-by-character. Fix: force
    `.login-container { display:block }` and `.loginform { display:block;
    width:100% }` in `custom.css`. Keep `max-width` (not `width`) on the
    container — Moove sets the container `width` at higher specificity, so
    a `width:460px` there is ignored while `max-width:460px` wins.
  - Moove's loginform splits into two `col-lg-6` columns
    (`hastwocolumns`) when guest access is on; the override + CSS force a
    single stacked column (`.left-column/.right-column/[class*="col"]` →
    `flex:0 0 100%`).
  - The password show/hide toggle (`core/togglesensitive`, wired by the
    `{{#togglepassword}}` block kept from Moove) did not show a visible
    eye button at desktop width — likely `smallscreensonly` is true or
    `togglepassword` is unset in the login renderable. Not chased; a
    custom toggle would risk clashing with `core/togglesensitive`.
  - Heading text is still Moodle's `loginto` string ("Log in to VRB
    Learning Platform"), not the design's "Learning Hub" — deliberate,
    it's clearer; revisit if the client wants the exact wording.
  - Copyright / build-version footer line from the design mock was not
    added (Moove's login layout renders its own `loginfooter` partial).

## [2026-08-30] — Employee-UI Phase 3: My courses → brand selection
**Agent:** Claude Code
**What:** Restyled `/my/courses.php` (the post-login landing, and the
  `dashboard_vrb_learning_hub` design screen) into a "brand selection"
  chooser, by styling the stock `block_myoverview` cards + injecting a
  heading. No custom page/renderer — the user preferred styling the
  existing page. All in `theme_vrblms`.
  - **New override `templates/block_myoverview/main.mustache`** — a
    verbatim copy of core's `block_myoverview/main` with one addition: a
    `.vrb-mycourses-intro` block (`<h2>` "Select your brand module" +
    subtitle, strings `theme_vrblms/mycoursesheading` /
    `mycoursessubtitle`) prepended inside `#block-myoverview-{{uniqid}}`.
    The search/grouping/sort/display selectors and the `courses-view`
    partial + its `require([...])` init are copied unchanged.
  - **`style/custom.css` section 6 rewritten** (the old section used a
    `#page-my-courses` id selector that never matched — the body id is
    `page-my-index`; correct hook is the `.page-mycourses` body class):
    hides `.page-header-headings` ("My courses" H1), the block's
    `.card-title` ("Course overview", `h3#instance-N-header.card-title`),
    and the `[data-region="filter"]` search/sort row; centres the
    injected heading; `.course-card` → flat 1px border + 8px radius +
    4px navy top accent + hover lift; `.card-img-top` → solid brand
    colour block (pattern image removed) 84px tall; `.coursename` navy
    600; `.progress` light track + brand-coloured `.progress-bar`;
    `.card-footer.menu` (kebab) hidden. Per-brand accent + banner +
    progress colours keyed to `[data-course-id="2|3|4"]`
    (Veeba/Wok Tok/Zyro) — documented as environment-specific.
  - New strings `mycoursesheading`, `mycoursessubtitle` in
    `lang/en/theme_vrblms.php`. `version.php` → `2026082902`.
**Files touched:** `public/theme/vrblms/templates/block_myoverview/main.mustache`
  (new), `public/theme/vrblms/style/custom.css` (section 6),
  `public/theme/vrblms/lang/en/theme_vrblms.php`,
  `public/theme/vrblms/version.php`.
**Verification done:** `php -l` lang; `admin/cli/upgrade.php
  --non-interactive` (`++ Success ++`); `purge_caches.php` per change.
  Live browser as `kavitayadav` (3 brands): after scrolling the block
  into view (see gotcha), the 3 tiles render with Veeba-red / Wok Tok-
  orange / Zyro-teal banners + matching top accent bars, centred navy
  "Select your brand module" heading + subtitle, no "My courses" / "Course
  overview" titles, no search bar, no kebabs. Card links confirmed
  (`data-course-id` 2/3/4 → `/course/view.php?id=2|3|4`); clicking the
  Veeba tile navigated to "Veeba Onboarding" with Module 2 still correctly
  locked. Phase 0 chrome (navy secondary nav, flat sections) unaffected.
**Gotchas for future agents:**
  - **The earlier "block_myoverview is broken / infinite spinner" scare
    was a browser-automation artifact, NOT a real bug.** The
    Claude-in-Chrome automation tab runs with
    `document.visibilityState === "hidden"`, and `block_myoverview` (via
    `core/paged_content` + IntersectionObserver) defers rendering the
    card page until it is visible/scrolled into view. The cards render
    fine for real users (confirmed by a user screenshot). **To verify
    anything on `/my/courses.php` via automation, navigate then
    `scroll` the block into view** — the scroll fires the observer and
    the cards appear. Do not conclude the block is broken from a
    non-scrolled automation screenshot, and do not "fix" it. A full
    `theme_vrblms` revert to git HEAD + `docker restart vrb-moodle` were
    both tried during the scare and (correctly) changed nothing.
  - **Mustache `{{! ... }}` comments do NOT nest and end at the first
    `}}`** — an early draft of `main.mustache` had "`{{#js}}`" and a
    "`{}`" inside the doc comment, which closed the comment early and
    dumped "`init — is unchanged from core. Example context (json): {} }}`"
    onto the page. Keep `{{`, `}}`, `#js` etc. out of comment text.
  - "Course overview" on this page is `h3#instance-5-header.card-title`
    rendered by the block *wrapper* (outside `.block-myoverview`), so the
    hide rule targets `.page-mycourses .block_myoverview .card-title`
    (underscore = the outer block section class).
  - Brand banners are plain colour blocks — real brand logos/wordmarks
    are still pending from the client; drop them into
    `theme/vrblms/pix/` and add `background-image` rules per
    `[data-course-id]` when available. Course summary/description text
    (shown in the design mock) is not rendered by Moodle's course card
    and was not added.

## [2026-08-30] — Employee-UI Phase 4: leaderboard visual pass
**Agent:** Claude Code
**What:** Restyled `/local/vrblms/leaderboard.php` to the VRB design
  (docs/design_refer/leaderboard_vrb_learning_hub/). All in our own
  plugin + theme — no Moove/Boost/core template touched.
  - **`classes/output/leaderboard_view.php`:** `render_table()` rewritten
    to emit hand-built `<table class="vrb-lb-table">` markup (via
    `html_writer`, still fully escaped) instead of `html_table` —
    `#F3F4F5` header row, a `.vrb-lb-rank` circular badge per row
    (gold/silver/bronze `.vrb-lb-rank--1|2|3` for the top three), a
    `.vrb-lb-avatar` initials circle + name, right-aligned tabular Score
    (bold) / Modules (x/y) / Time. New `initials()` helper (first+last
    initial via `\core_text`). `format_seconds()` and the empty-location
    value now return an em-dash. New `render_section_heading($name, $key)`
    → `<h3 class="vrb-lb-section vrb-lb-section--{slug}">` where slug is
    derived from a `brand_*` idnumber or the literal `overall`.
    `render_filter_form()` now wraps the `<form class="vrb-lb-filters">`
    in a `.vrb-lb-filters-card`; `render_select()` div class →
    `vrb-lb-field`, label class dropped (CSS targets `.vrb-lb-field label`).
  - **`leaderboard.php`:** `set_title`/`set_heading` → new
    `local_vrblms/pageheading` ("Regional Leaderboard"); the in-content
    `$OUTPUT->heading()` call was removed (the `report` pagelayout
    already renders the page H1 from `set_heading` — keeping both showed
    the title twice). Subtitle `<p class="vrb-lb-subtitle">` added.
    Per-brand + "Overall" section titles now use
    `leaderboard_view::render_section_heading()` (passing `$brand->idnumber`
    / `'overall'`) instead of `$OUTPUT->heading(..., 3)`.
  - **`lang/en/local_vrblms.php`:** `pageheading`, `pagesubtitle` added
    (`leaderboard` = "Leaderboard" kept for the nav link in `lib.php`).
  - **`theme_vrblms/style/custom.css` section 10 rewritten** to the
    `.vrb-lb-*` system: subtitle, brand-accented `.vrb-lb-section`
    (`--veeba`/`--woktok`/`--zyro`/`--overall`), `.vrb-lb-filters-card`,
    `.vrb-lb-table-card` (white, 1px border, 8px radius, `overflow:hidden`)
    > `.vrb-lb-scroll` (`overflow-x:auto`) > `.vrb-lb-table`, the rank
    badge, the avatar. The own-row highlight (still class
    `vrb-leaderboard-own-row`, emitted by `render_table`) changed from
    `#fff3cd` to `rgba(0,11,67,.05)` + an `inset 4px 0 0` navy accent bar
    on the first cell.
  - `local_vrblms/version.php` → `2026083000`.
**Files touched:** `public/local/vrblms/classes/output/leaderboard_view.php`,
  `public/local/vrblms/leaderboard.php`,
  `public/local/vrblms/lang/en/local_vrblms.php`,
  `public/local/vrblms/version.php`,
  `public/theme/vrblms/style/custom.css` (section 10).
**Verification done:** `php -l` on all changed PHP; `admin/cli/upgrade.php
  --non-interactive` (`local_vrblms ++ Success ++`); `purge_caches.php`
  per change. Live browser as **`kavitayadav`** (multi-brand: Veeba +
  Wok Tok + Zyro): the employee view renders one brand-accented section
  per brand (Veeba red / Wok Tok orange / Zyro teal — confirmed via
  `getComputedStyle().borderLeftColor` = `rgb(227,30,36)` /
  `rgb(243,112,33)` / `rgb(0,128,128)`) plus an "Overall (all brands)"
  section (navy `rgb(0,11,67)`); each table has the grey header row, gold
  #1 / silver #2 / bronze #3 rank badges, initials avatars, right-aligned
  Score/Modules/Time; **Kavita's own row is highlighted (navy tint + navy
  left accent bar) in all four tables** — the concrete multi-brand
  "each course + overall" check. Single page H1 (no duplicate).
**Gotchas for future agents:**
  - The **admin/manager filter-form view** (capability
    `local/vrblms:viewfullleaderboard`, `manager` archetype) is
    code-complete but was **not visually verified** this pass — no admin
    credentials available in-session and `kavitayadav` doesn't hold the
    cap. The form markup + `.vrb-lb-filters-card` styling should be
    checked on the next admin login. The GET-param filtering logic itself
    is unchanged from the Phase 3 (2026-08-21) build.
  - `report` pagelayout renders `$PAGE->set_heading()` as the page H1 —
    do not also `echo $OUTPUT->heading()` the same string in the body.
  - Row objects from `\local_vrblms\api` expose: `rank, userid, fullname,
    idnumber, state, city, region, score_percent, quizzes_completed,
    quizzes_total, total_time_seconds` — `render_table()` consumes these
    directly.

## [2026-08-30] — "Leaderboard" added to the primary (header) navigation
**Agent:** Claude Code
**What:** Added a "Leaderboard" item to the site primary navigation bar
  (the `Home | Dashboard | My courses` row in the header), linking to
  `/local/vrblms/leaderboard.php`, via the
  `core\hook\navigation\primary_extend` hook.
  - New `public/local/vrblms/db/hooks.php` registering
    `\local_vrblms\hook_callbacks::extend_primary_navigation` for
    `\core\hook\navigation\primary_extend`.
  - New `public/local/vrblms/classes/hook_callbacks.php` — the callback
    calls `$hook->get_primaryview()->add(get_string('leaderboard',
    'local_vrblms'), new moodle_url('/local/vrblms/leaderboard.php'),
    \navigation_node::TYPE_ROOTNODE, null, 'vrblmsleaderboard')`, guarded
    by `isloggedin() && !isguestuser()`. `TYPE_ROOTNODE` matches what
    `\core\navigation\views\primary::initialise()` uses for the
    "My courses" node, so it renders inline with the others.
  - `local_vrblms/lib.php`'s existing `local_vrblms_extend_navigation()`
    (which adds the same link to the navigation *drawer* tree) was left
    as-is — core items like "My courses" appear in both places too.
  - `local_vrblms/version.php` → `2026083001` (needed so Moodle re-scans
    `db/hooks.php`).
**Files touched:** `public/local/vrblms/db/hooks.php` (new),
  `public/local/vrblms/classes/hook_callbacks.php` (new),
  `public/local/vrblms/version.php`.
**Verification done:** `php -l` both new files; `admin/cli/upgrade.php
  --non-interactive` (`local_vrblms ++ Success ++`); `purge_caches.php`.
  Live browser as `kavitayadav`: header now reads
  `Home | Dashboard | My courses | Leaderboard`; the item's href is
  `/local/vrblms/leaderboard.php`; clicking it loads the Regional
  Leaderboard page and the nav item shows active/bold there.
**Gotchas for future agents:**
  - `\core\hook\navigation\primary_extend` is dispatched at the end of
    `\core\navigation\views\primary::initialise()`
    (`lib/classes/navigation/views/primary.php:90`), right after Home /
    Dashboard / My courses are added — so hook-added nodes land after
    "My courses". `$hook->get_primaryview()->add()` is
    `navigation_node::add()` (public), same signature used by
    `lib.php`'s drawer callback.
  - Adding/altering `db/hooks.php` requires a plugin version bump or the
    new callback is not picked up.

## [2026-08-30] — Employee-UI Phase 5: course page + quiz flow (CSS-only, Option A)
**Agent:** Claude Code
**What:** Conservative brand lift of the Topics course page and the quiz
  flow (view / attempt / review) — entirely in `theme_vrblms/style/custom.css`.
  Per the user's call (2026-08-30) this is "Option A": no course-format
  renderer override, no `format_vrblms` plugin, no `mod_quiz` renderer
  subclass, no question-engine option restyling — all flagged as
  upgrade-fragile in `Agents.md`. The `veeba_learning_modules` design
  `screen.png` is a broken 28-byte placeholder; worked from its `code.html`.
  - **New token `--vrb-brand`** (default navy) re-pointed per course via
    `body.course-2 / -3 / -4` → Veeba red / Wok Tok orange / Zyro teal.
    Moodle stamps `body.course-<id>` on every course-context page, so this
    one place drives the accent for sections 7, 8, 11, 12. Verified:
    `getComputedStyle(body).--vrb-brand` = `#E31E24` on course 2,
    `#F37021` on course 3, `#008080` on course 4.
  - **Section 7 (quiz attempt) — distraction-free.** On
    `body#page-mod-quiz-attempt`: `display:none` on
    `#theme_boost-drawers-courseindex`, `#theme_boost-drawers-primary`,
    `#theme_boost-drawers-blocks` (the Quiz-navigation block), `.drawer-toggles`,
    `.drawer-toggler`, `.btn.drawertoggle`; `#page.drawers { margin-inline:
    auto !important }` to drop the drawer offsets AND keep the
    `.limitedwidth` centring (a flat `margin:0` pinned content left of
    centre — see gotcha); `#region-main { max-width:820px; margin-inline:auto }`;
    3px `--vrb-brand` top accent bar on `#responseform`; hide the
    inter-activity `.activity-navigation`. The "Finish attempt" flow is
    unaffected — the final page's bottom button is "Finish attempt ..."
    regardless of the nav block (verified on the 2-page Module 2 Quiz).
  - **Section 8 (quiz review) — extended.** `.moove-quizreviewsummary`
    → bordered 8px card (scoped to `#page-mod-quiz-review` so it doesn't
    double-border the same-class per-attempt boxes on the quiz *view*
    page); per-question status accent as a `border-left` on `.formulation`
    keyed to core's `.que.correct / .incorrect / .notanswered /
    .partiallycorrect`; `.que .outcome` flattened (it has a peach warning
    tint by default) and `.que .feedback` restyled to a calm `#f3f4f5`
    panel with `--vrb-text-2` text.
  - **Section 11 (new) — course page Topics brand lift.**
    `body#page-course-view-topics .course-section.main` → white flat card,
    1px border, 4px `--vrb-brand` left edge, 8px radius; `.sectionname`
    navy 600; `.activity-item` → bordered list rows with hover lift;
    completion pills → pill radius. `.availabilityinfo` (NOT page-scoped —
    also fires on activity pages) → grey surface panel, muted left border,
    muted badge. Verified with a real restriction: Zyro Module 2 shows
    "Not available unless: The activity Module 1 Quiz is complete and
    passed" in the new panel, lock glyph intact.
  - **Section 12 (new) — quiz view / instructions page.**
    `body#page-mod-quiz-view`: `.quizinfo` (Attempts allowed / Grading
    method / Grade to pass) → bordered card with `--vrb-brand` left edge;
    `.moove-summary-table` per-attempt → flat card; `.quizstartbuttondiv
    .btn-primary` → `--vrb-brand` fill (so "Attempt quiz" / "Continue your
    attempt" is Veeba-red in the Veeba course, navy elsewhere).
  - Header comment section index updated; `version.php` → `2026083002`.
  - **`Agents.md` + `CLAUDE.md`:** added a rule (user request) — do not
    add a persistent left-hand sidebar nav to the employee UI and do not
    add a `$THEME->layouts` override to build one; some `design_refer`
    mockups show a dark left rail, the approved direction is to keep
    Moove's top navbar + drawers and brand via CSS + Mustache only.
**Files touched:** `public/theme/vrblms/style/custom.css`,
  `public/theme/vrblms/version.php`, `Agents.md`, `CLAUDE.md`.
**Verification done:** `php -l` (docker) on `version.php`;
  `admin/cli/upgrade.php --non-interactive` → `theme_vrblms ++ Success ++`;
  `purge_caches.php` after every change. Live browser (localhost:8080,
  desktop 1512):
  - `rksharma` (Veeba only): course 2 sections = white cards, Veeba-red
    left accent (`rgb(227,30,36)`), 8px radius, bordered activity rows;
    quiz view (id 3) `.quizinfo` red-edged card + red "Continue your
    attempt" button; quiz attempt (id 3) = both left drawers + right quiz-nav
    drawer gone, `#region-main` 820px centred (x=530, right=530), red 3px
    accent bar, no bottom activity nav, navy "Next page", last page shows
    "Finish attempt ..."; quiz review (attempt 6) = summary card, green
    per-question accent, grey (not peach) "correct answer" panel.
  - `kavitayadav` (multi-brand): course 4 (Zyro) sections = teal
    (`rgb(0,128,128)`) left accent; Module 2 locked notice renders in the
    new `.availabilityinfo` panel.
**Gotchas for future agents:**
  - `body.course-<id>` is on every course/mod/quiz page — the cleanest
    hook for per-brand accent (Veeba=2, Wok Tok=3, Zyro=4). Env-specific;
    revisit if the courses are rebuilt.
  - On `#page-mod-quiz-attempt` the `.limitedwidth` layout centres `#page`
    with `margin-inline:auto`; the drawer-open state adds `margin-left:
    <drawer-width>`. To reclaim the space you must re-assert
    `margin-inline:auto !important`, NOT `margin:0` — the latter pins the
    column left of centre.
  - The quiz *view* page's per-attempt boxes carry the SAME
    `.moove-quizreviewsummary` class as the review page's summary strip —
    scope any card treatment of it to `#page-mod-quiz-review` /
    `#page-mod-quiz-view` respectively.
  - `.que .outcome` (the general-feedback wrapper on the review page) has
    a peach `rgb(252,239,220)` tint + padding by default — flatten it if
    you want the inner `.feedback` to be the visible panel.
  - Course/quiz page structure confirmed: courses 2/3/4 are `topics`
    format; section wrapper is `li.section.course-section.main#section-N`;
    Moove also emits decorative `.section.m-0.p-0.img-text` separators —
    target `.course-section.main`, not bare `.section`.
  - Phase 5's course/module-list design (the 3-col module-card grid with
    cover art / status pills / per-module progress) is NOT reproducible
    CSS-only — it needs a course-format renderer. Explicitly out of scope
    for this pass; the CSS lift brands the stock accordion instead.

## [2026-08-30] — Employee-UI Phase 5 follow-up: quiz attempt page pushed to the "assessment" design
**Agent:** Claude Code
**What:** The user supplied the real
  `docs/design_refer/veeba_learning_modules_vrb_learning_hub/screen.png`
  (the earlier 28-byte placeholder) — it is actually the **assessment /
  quiz-attempt** screen (same content as `assessment_vrb_learning_hub/
  code.html`), not a module list. Reworked Section 7 of
  `theme_vrblms/style/custom.css` to match it, still CSS-only, still
  scoped to `body#page-mod-quiz-attempt`.
  - **`.info` sidebar → question header.** Moodle floats `.que .info`
    (Question N / state / mark / flag) as a ~112px left column. Now
    `float:none; width:100%; display:flex` — "Question N" as a 1.5rem
    heading, state + mark as small `--vrb-muted` meta, "Flag question"
    pushed right, a 2px `--vrb-border` rule underneath. Moove's tinted
    panel bg/border/radius on `.info` explicitly flattened.
  - **Question card.** `.que .formulation` → white, 1px `--vrb-border`,
    3px `--vrb-brand` top edge, 8px radius, 2rem padding, faint shadow
    (overrides the shared `#f7f9fc` base, which still applies on review).
    `.qtext` → 1.5rem/600 navy heading, no inner padding.
  - **Option rows.** `.que .answer` → `flex-direction:column; gap`;
    each `.answer > div` → 1px bordered `rounded` row, `:hover` =
    `--vrb-brand` border, `div:has(input:checked)` = `--vrb-brand`
    border + `#f3f4f5` fill; radios get `accent-color:var(--vrb-brand)`
    (checked dot renders red) and the `<label>` is `flex:1` so most of
    the row is a click target. `:has()` is relied on — without it the
    row just doesn't tint, the red radio still shows the choice.
  - **Prev / Next.** `.submitbtns` → `display:flex; justify-content:
    space-between`; `.mod_quiz-prev-nav` → transparent + 1px border +
    muted text; `.mod_quiz-next-nav` stays navy, wider padding.
  - **`.tertiary-navigation`** ("Back" button) added to the
    already-hidden list — the design has only the header close icon.
  - Dropped the old `#responseform { border-top:3px }` (it drew a stray
    red line above the header once "Back" was gone); `#responseform` now
    just gets a little `padding-top`.
  - `version.php` → `2026083003`.
**Files touched:** `public/theme/vrblms/style/custom.css` (Section 7 rewrite),
  `public/theme/vrblms/version.php`.
**Verification done:** `admin/cli/upgrade.php --non-interactive` →
  `theme_vrblms ++ Success ++`; `purge_caches.php`. Live as `rksharma`,
  resumed the in-progress Module 2 attempt: page 1 (T/F) and page 2 both
  render the centred ~800px column, "Question N" header + divider, white
  card with red top bar + bold navy question, bordered option rows;
  selecting "False" gives a red-filled radio + red-bordered `#f3f4f5`
  row (`:has()` confirmed working); page 2 shows outline "Previous page"
  left + navy "Finish attempt ..." right. `#page-mod-quiz-review`
  re-checked — unchanged (all new rules are attempt-scoped).
**Gotchas for future agents:**
  - This screenshot file is misnamed — `veeba_learning_modules_*/screen.png`
    is the quiz-attempt design, matching `assessment_vrb_learning_hub/
    code.html`. There is still NO real module-list screenshot.
  - "Question 4 of 10", the % progress bar and the "Time Elapsed" pill in
    that design have no data source on `mod/quiz/attempt.php` once the
    quiz-navigation block is hidden (and this quiz has no time limit), so
    they were not reproduced — the "Question N" header + rule stands in.
  - `.que .info` carries a Moove tinted-panel style (bg `#F8F9FA` + border
    + radius); flatten bg/border/radius, not just the float, or the header
    reads as a stray box.

## [2026-08-30] — Employee-UI Phase 5 follow-up 2: quiz review + results screens
**Agent:** Claude Code
**What:** Matched `docs/design_refer/quiz_review_vrb_learning_hub/screen.png`
  and `.../quiz_results_vrb_learning_hub/screen.png`. Both live on
  `mod/quiz/review.php` (the post-submit landing — Moodle has no separate
  results page), so one page carries both treatments stacked.
  - **First theme JS.** New `public/theme/vrblms/javascript/quizresult.js`,
    declared `$THEME->javascripts_footer = ['quizresult']` in
    `config.php`. Plain footer script, no AMD build, no core edits. It
    early-returns on any body id != `page-mod-quiz-review` (inert
    everywhere else — confirmed on the dashboard). On the review page it
    reads the grade Moove already renders in
    `.moove-quizreviewsummary` (`Grade` box "<b>X</b> out of Y", plus
    `Marks` and `Duration`), computes the percent, and injects a
    `.vrb-qr-result` banner before the summary: an SVG score ring
    (dasharray = 2πr, r=45; offset = C·(1−pct/100)), a pass/fail heading
    ("Module passed" / "Not quite — 60% needed to pass"), a sub-line, and
    a Score/Time stat panel. It then `.hidden`s the verbose Moove strip
    (the banner already carries score + time) and prepends a
    "Review your answers" + subtitle heading before the first `.que`.
    **Pass threshold is hardcoded 60%** in the JS — the review page has
    no grade-to-pass element; 60% is this environment's uniform pass mark
    across all three brand quizzes (see earlier LOG entries). If quizzes
    ever get differing pass marks this needs the real value
    (server-side / a mod_quiz renderer).
  - **`custom.css` section 8 rewrite.** Review page now gets the same
    distraction-free canvas as the attempt page (drawers +
    quiz-navigation block + tertiary/activity nav hidden, `#page` re-
    centred, `#region-main` capped at 860px — "Finish review" still works
    via the copy at the end of the page content, styled as an outline
    button). `.info` → full-width header ("QUESTION N" uppercase muted,
    `.state` coloured by result, `.grade` → pill pushed right, bottom
    rule). `.que` → white card with a 4px `--vrb-success` /
    `--vrb-error` / `--vrb-warm` left edge from core's
    `.correct`/`.incorrect`/`.partiallycorrect`; `.formulation` inside
    flattened. Option rows: `.answer > div` bordered, `div.correct` →
    green border + tint + core's check icon right-aligned, `div.incorrect`
    → red border + tint + cross icon; the attempt-time radio/checkbox
    inputs are `display:none` on review. `.feedback` panel kept
    (`#f3f4f5`). Plus all the `.vrb-qr-*` banner styles.
  - `version.php` → `2026083004` (needed for the new
    `$THEME->javascripts_footer`).
**Files touched:** `public/theme/vrblms/javascript/quizresult.js` (new),
  `public/theme/vrblms/config.php`, `public/theme/vrblms/style/custom.css`
  (section 8), `public/theme/vrblms/version.php`.
**Verification done:** `php -l` config.php; `node --check` the JS;
  `admin/cli/upgrade.php --non-interactive` → `theme_vrblms ++ Success ++`;
  `purge_caches.php` per change. Live as `rksharma`, both real attempts of
  Module 1 Quiz:
  - attempt 1 (0/4): red-topped banner, empty ring + red "0%", "Not quite
    — 60% needed to pass", Score 0.00/4.00 / Time 42 secs; question cards
    red-accented, "Incorrect" red, "Mark 0.00 out of 1.00" pill, the
    wrongly-chosen option red-bordered + tinted + red ✗ icon, correct
    answer in the grey feedback panel.
  - attempt 6 (4/4): green-topped banner, full green ring + "100%",
    "Module passed"; cards green-accented, "Correct" green, correct option
    green-bordered + tinted + green ✓ icon.
  Moove summary strip hidden on both; "Finish review" renders as an
  outline button at the page end. Attempt page re-checked (unchanged —
  all review rules are `#page-mod-quiz-review` scoped); dashboard
  re-checked (JS no-ops, no console errors).
**Gotchas for future agents:**
  - For True/False questions in deferred-feedback mode Moodle marks only
    the wrongly-chosen option `.incorrect` — it does NOT add `.correct` to
    the unchosen right option, so the green "correct" row only appears on
    question types where core marks it (e.g. multichoice). The
    "The correct answer is ..." feedback line always covers it.
  - `$THEME->javascripts_footer` loads on every page; gate the script by
    `document.body.id` inside the file, not by hoping Moodle scopes it.
  - Adding `$THEME->javascripts_footer` needs a theme version bump for
    Moodle to re-read config.php.
  - The review page has no grade-to-pass in the DOM — `view.php`'s
    `.quizinfo` does ("Grade to pass: 60.00 out of 100.00") if a future
    change needs the real threshold there.

## [2026-09-02] — Employee-facing UI is complete (checkpoint entry)
**Agent:** Claude Code
**What:** Marking the employee-facing UI design-match build as **done**.
  This is a consolidating checkpoint, not new work — it records that every
  phase of the employee-UI plan (`docs/temp docs/employee-ui-build-plan.md`)
  has been built, verified, and committed across the entries above:
  - **Phase 0** — token layer / Inter / flat elevation / navy primary +
    the "force light colour scheme everywhere" follow-up
    (2026-08-29 ×2).
  - **Phase 1** — logged-out index / hero front page (2026-08-29).
  - **Phase 2** — login page: branded card, "Username / Employee Code"
    label, field icons, navy accent (2026-08-29).
  - **Phase 3** — `/my/courses.php` restyled into the brand-selection
    chooser (stock `block_myoverview` cards + injected heading)
    (2026-08-30).
  - **Phase 4** — leaderboard visual pass (`.vrb-lb-*` system, rank
    badges, avatars, own-row highlight) + "Leaderboard" added to both the
    nav drawer and the primary header nav (2026-08-30 ×2).
  - **Phase 5 (Option A, CSS-only)** — Topics course-page brand lift,
    distraction-free quiz attempt page, quiz view/instructions, plus the
    two follow-ups pushing the attempt page to the "assessment" design and
    building the quiz review + results screens (first theme JS,
    `javascript/quizresult.js`) (2026-08-30 ×3).
  All of it lives in `theme_vrblms` (+ `local_vrblms` for the leaderboard
  page) — no core edits, no `theme_moove` edits, no `$THEME->scss`, no
  AMD/grunt build, no `$THEME->layouts` override. Committed through
  `bf225c57997`.
**Files touched:** none in this entry (documentation checkpoint only).
**Verification done:** none new — see the per-phase entries above for the
  live-browser verification done at the time each phase landed.
**Gotchas / what "complete" does and does not mean here:**
  - "Complete" = the CSS-only ("Option A") scope agreed with the user on
    2026-08-30. The richer designs that need a course-format renderer (the
    3-column module-card grid with cover art and per-module progress) and
    any `format_vrblms` / `mod_quiz` renderer work are **explicitly out of
    scope** and not done — the stock accordion/cards were branded in place
    instead.
  - **Not visually verified:** the admin/manager leaderboard filter-form
    view (`local/vrblms:viewfullleaderboard`) — no admin creds in-session
    on the Phase 4 pass; the employee view is fully verified. Check it on
    the next admin login.
  - Brand banners/accents are plain colour blocks keyed to
    `data-course-id` / `body.course-<id>` (Veeba=2, Wok Tok=3, Zyro=4) —
    environment-specific, and real brand logos/wordmarks are still pending
    from the client (drop into `theme/vrblms/pix/` when they arrive).
  - The pass threshold is hardcoded 60% in `quizresult.js` — fine while
    all three brand quizzes share that mark; needs the real per-quiz value
    if that ever changes.
  - Next up on the plan is **Phase 5 of `docs/TASKS.md`** — `report_vrblms`
    (admin CSV-exportable regional report) — which is unrelated to the
    employee UI and still not started. `local_vrblms\api` already supports
    it.

## [2026-09-02] — Built `local_vrbcert`: custom PDF top-performer certificate plugin (Phase B, part 1)
**Agent:** Claude Code
**What:** New plugin `public/local/vrbcert/` — a fully custom, self-coded
  certificate system that issues **PDF** certificates to leaderboard top
  performers. Built ahead of `report_vrblms` (TASKS.md Phase 5) at the
  user's direction; certificates were made the current priority. Chosen
  over `mod_customcert` (its activity-module model fights a ranking-driven,
  cross-course trigger, and it carried a real Moodle-5.1.5 third-party
  compatibility risk) and over core badges. Plan:
  `docs/temp docs/vrbcert-plugin-build-plan.md` (+ the master
  `docs/temp docs/remaining-functionality-build-plan.md`).
  - **Engine:** Moodle's bundled TCPDF wrapper — `\pdf`
    (`public/lib/pdflib.php:159`), font `freesans` (bundled, Unicode). No
    external dependency, no Composer, no AMD build.
  - **Consumes `\local_vrblms\api` EXACTLY as-is** (hard constraint from
    the user): `api::get_leaderboard($brandidnumber, null, null,
    $strategykey, $topn)` per Brand from `api::get_brands()`, and
    `api::get_overall_leaderboard(null, null, $strategykey, $topn)`. Every
    row those return **is** a qualifier — no re-ranking, re-sorting,
    re-filtering or reinterpretation. The row's `rank` / `score_percent`
    are copied onto the certificate for display only. **No
    `attempt_repository` call, no cohort-id lookup** — the per-brand
    certificate is keyed by the brand cohort *idnumber* string
    (`brand_veeba` …), the overall one by the literal `overall`.
    `local_vrblms` was **not modified** (confirmed its `api.php` already
    exposes `rank`, `score_percent`, `fullname`, `idnumber` and a Top-N
    `$limit`).
  - **Two independent qualification tracks, both on by default:**
    per-brand (Top N, default 3) and overall (Top N, default 10). An
    employee who places in several brand leaderboards and/or the overall
    one qualifies **independently in each** and receives **one certificate
    per (brand-or-overall, period)**. Verified: 8 of the 19 issued in the
    test run are multi-track (e.g. Rajesh Sharma → `brand_veeba` + `overall`).
  - **Config-driven** (`settings.php`, under Site admin → Plugins → Local
    plugins): master `enabled`, `perbrand_enabled`/`perbrand_topn`,
    `overall_enabled`/`overall_topn`, `strategykey` (reuses
    `\local_vrblms\ranking\strategy_manager` for the option list — "site
    default" = follow local_vrblms), `period` (award-cycle label, default
    `date('Y')`), certificate title / presented-to line / two tokenised
    body lines / signatory name+title, default accent colour + per-brand
    label & colour overrides (Veeba/Wok Tok/Zyro). `score_threshold` mode
    was left out of this build (noted in the plan).
  - **Idempotent issuance.** New table `local_vrbcert_issued`
    (`db/install.xml`; columns incl. `brandkey`, `period`, `track`,
    `certrank`, `scorepercent`, `strategykey`, `filename`), unique on
    `(userid, brandkey, period)`. `issuer::run($period, $issuedby,
    $reissue)` skips anyone who already holds a cert for that
    brand/period; `$reissue = true` deletes + regenerates (new row id,
    old stored file removed). Bumping `period` issues a fresh set and
    leaves prior periods intact. PDFs stored via the File API (component
    `local_vrbcert`, filearea `certificate`, `itemid` = the row id, system
    context), served by `local_vrbcert_pluginfile()` in `lib.php` with an
    owner-or-manager access check.
  - **Trigger:** `\local_vrbcert\task\issue_certificates` scheduled task
    registered in `db/tasks.php` but **`disabled => 1`** — issuance
    cadence is a business decision; run it manually meanwhile (admin page
    "Issue now" button, or
    `php admin/cli/scheduled_task.php --execute='\local_vrbcert\task\issue_certificates'`).
  - **Surfaces:** employee `mycertificates.php` (own certs list +
    per-cert download, nav-drawer node "My certificates" via
    `local_vrbcert_extend_navigation`, capability `local/vrbcert:viewown`
    granted to the `user` archetype); admin `index.php` under Site admin →
    Reports → "VRB certificates" (`local/vrbcert:manage`) — config
    summary, inline **Preview** (sample PDF from current settings + dummy
    data, no DB writes), **Issue now** form (period + re-issue checkbox),
    and an **issued list** filterable by period/brand with per-row
    Download + Revoke.
  - **Privacy provider** implemented (`classes/privacy/provider.php`) —
    metadata for `local_vrbcert_issued` + the `core_files` link, plus
    export / delete for user, context, and userlist. (`local_vrblms` has
    none because it stores nothing; `local_vrbcert` does, so it needs one.)
**Files touched:** all new under `public/local/vrbcert/`: `version.php`,
  `settings.php`, `lib.php`, `index.php`, `mycertificates.php`,
  `db/{install.xml,access.php,tasks.php}`, `lang/en/local_vrbcert.php`,
  `classes/{certificate_data,template,certificate_generator,
  issued_certificate,issuer}.php`,
  `classes/task/issue_certificates.php`, `classes/privacy/provider.php`.
  Database: `local_vrbcert` registered via `admin/cli/upgrade.php`
  (new table `local_vrbcert_issued`, 3 capabilities, 1 disabled scheduled
  task, 20 config defaults). No repo files outside the new plugin dir; no
  core, Moove, or `local_vrblms` changes.
**Verification done:**
  - `php -l` on every plugin PHP file (clean).
  - `core_component::get_plugin_list('local')` shows `vrbcert` after a
    cache purge; `admin/cli/upgrade.php --non-interactive` →
    `-->local_vrbcert ++ Success ++`, all 20 settings registered. DB check
    confirmed the table's 13 columns, the 3 `local/vrbcert:*`
    capabilities, and the scheduled task present with `disabled=1`.
  - **CLI verification script** (scratchpad, not committed —
    `vrbcert_verify.php`, same pattern as Phase 3/4) against the real
    20-employee / 3-brand dataset: **14/14 assertions passed.** Covered:
    per-brand Top 3 (9) + overall Top 10 = 19 qualifier slots, all 19
    issued with a valid stored PDF (`%PDF` header, >500 bytes); every
    issued row's `certrank`/`scorepercent` equal the API row verbatim;
    8 multi-track recipients confirmed (brand + overall); re-run issues
    nothing (all 19 skipped); `$reissue=true` replaces all 19 (new row
    ids, zero orphaned files); a different `period` issues a fresh 19 and
    leaves the first period intact; `best` vs `first` strategy produces
    different per-brand sets (9 vs 9, not identical).
  - Generated a real preview PDF via CLI — 146 655 bytes, `%PDF-1.7`.
  - **Browser, logged in as a real employee (Kavita Yadav, multi-brand):**
    `/local/vrbcert/mycertificates.php` renders her **two** 2026
    certificates — "Overall" (rank 8) and "Zyro" (rank 2) — matching the
    issuance exactly; her own cert downloads through `pluginfile.php`;
    requesting **another employee's** cert file
    (`.../certificate/58/...`, Rajesh Sharma's) returns "Sorry, the
    requested file could not be found" (ownership check works); the admin
    page `/local/vrbcert/index.php` returns **Access denied** for her
    (`local/vrbcert:manage` gate works).
  - Left a real issued set in place for `period = 2026` (19 certs) so the
    feature is explorable.
**Gotchas for future agents:**
  - **DB column is `certrank`, not `rank`.** `rank` is a reserved word in
    MariaDB 10.2+ / SQL:2016 (window functions) — the XMLDB field and all
    code use `certrank`; the `certificate_data` PHP property is still
    `rank` and is mapped across in `issued_certificate::create()`.
  - **`issuer` must only ever call `\local_vrblms\api::*`** — never
    `attempt_repository` or a cohort table. The per-brand certificate key
    is the cohort **idnumber** (`brand_veeba`), chosen specifically so no
    `id` lookup (which would mean reaching past the API) is needed, and so
    the unique key has no NULLs (overall uses the literal string
    `overall`, not NULL — MariaDB treats NULLs as distinct in a unique
    index, which would have let duplicate overall certs through).
  - The test dataset has most employees at 0.0% (the dummy brand quizzes
    were only ever attempted by a handful of accounts). The leaderboard
    API still returns a full Top-N by its own name-based tie-break, and
    `local_vrbcert` issues to exactly that set — correct behaviour per the
    "consume verbatim" constraint, not a bug. Real content will make the
    sets meaningful.
  - `certificate_generator` uses fixed mm coordinates on an A4-landscape
    page (297×210). It's a clean code-drawn layout (border, wordmark,
    brand band, title, name, two body lines, signature + date, footer
    ref) — **no background image**. A `backgroundimage` setting was
    planned but deferred; when the client supplies real artwork, add the
    filemanager setting + an `Image(0,0,297,210)` first-layer and retune
    the text Y-positions (~half a day).
  - `send_stored_file(..., $forcedownload = true)` is hardcoded in
    `local_vrbcert_pluginfile()` — certificate links always download
    rather than render inline. Intentional.
  - **Not yet verified in-browser:** the admin management page UI itself
    (Preview button, Issue-now form, issued list, Revoke) — needs admin
    credentials, same gap as the Phase 4 admin leaderboard-filter view.
    The page's access gate (deny for non-managers) *is* verified. Do this
    on the next admin login.
  - **Still open / for go-live (not build blockers):** final qualification
    rule is a client decision (the two-track Top-N defaults are
    placeholders); real certificate artwork is client-pending; the
    scheduled task stays disabled until an issuance cadence is agreed.
  - Next: `report_vrblms` (TASKS.md Phase 5) — Phase A of the master plan.

## [2026-09-02] — `local_vrbcert` follow-up: targeted issuance, header nav, admin-page verified, 2 bugs fixed
**Agent:** Claude Code
**What:** Admin-side verification of `local_vrbcert` (previous entry) using
  real admin credentials, plus enhancements the user asked for.
  - **Targeted issuance.** `issuer::run()` gained a 4th arg
    `array $scope` (`tracks` = subset of `perbrand`/`overall`,
    `brandkeys` = limit the per-brand track to specific brand cohort
    idnumbers, `topn` = per-run Top-N override). `normalise_scope()`
    sanitises it; `collect_qualifiers()` honours it. Default (empty scope)
    is unchanged — both tracks, all brands, per-track settings N. The
    admin page (`index.php`) "Issue certificates now" form now has a
    **Scope** select (All enabled tracks / Per-brand every brand /
    Per-brand <Veeba|Wok Tok|Zyro> only / Overall only) and a **Top N
    (this run)** field. So "top 5 for Zyro only" = Scope "Per-brand - Zyro
    only" + Top N 5 + Run issuance. The result notification echoes the
    scope used. `\local_vrblms\api` consumption is unchanged - scope only
    decides which `api::get_leaderboard(...)` / `get_overall_leaderboard()`
    calls are made and with what `$limit`; rows are still taken verbatim.
  - **Header nav.** New `db/hooks.php` +
    `classes/hook_callbacks.php::extend_primary_navigation` on
    `\core\hook\navigation\primary_extend` (same mechanism
    `local_vrblms` uses for "Leaderboard"). Adds a **"My certificates"**
    item to the top header bar, **but only for a user who already holds
    at least one certificate** (`$DB->record_exists('local_vrbcert_issued',
    ['userid' => ...])`) - the header slot stays clean until there's
    something to see. The nav-drawer link
    (`local_vrbcert_extend_navigation` in `lib.php`) is unchanged and
    **always** shown to any logged-in non-guest with `local/vrbcert:viewown`.
  - `version.php` 2026090200 -> 2026090201 (needed for `db/hooks.php` to
    be picked up).
**Files touched:** `public/local/vrbcert/classes/issuer.php` (scope),
  `public/local/vrbcert/index.php` (scope form + 2 bug fixes below),
  `public/local/vrbcert/lang/en/local_vrbcert.php` (`issuedlist` +
  scope/topn strings), `public/local/vrbcert/db/hooks.php` (new),
  `public/local/vrbcert/classes/hook_callbacks.php` (new),
  `public/local/vrbcert/version.php`. Database: `local_vrbcert` re-upgraded
  (hook registered). Issued a real `period = 2026` set of 19 certificates
  (kept, for exploration); a `DEMO` period Zyro-top-5 set was created
  during the browser test and then deleted.
**Verification done:**
  - `php -l` clean; `admin/cli/upgrade.php --non-interactive` ->
    `local_vrbcert ++ Success ++`.
  - Re-ran the CLI verification script (`vrbcert_verify.php`) after the
    `issuer::run()` signature change: **14/14 still pass** (no regression).
    Added targeted-scope checks: `['tracks'=>['perbrand'],
    'brandkeys'=>['brand_zyro'],'topn'=>5]` -> 5 rows, all `brand_zyro`;
    `['tracks'=>['overall'],'topn'=>3]` -> 3 rows, all `overall`; empty
    scope -> full 19.
  - **Browser, logged in as the real admin:** management page renders
    under Site admin -> Reports -> "VRB certificates"; config summary
    correct; **Preview** button opens the sample PDF inline (VRB LMS
    wordmark, red "VEEBA" band, title, name, body lines, signatory + date
    - looks right); issued list shows all 19 with per-row Download +
    Revoke; the **Brand/track filter** narrows correctly (Zyro -> exactly
    the 3 Zyro rows); the new **Scope** dropdown has the 6 expected
    options; submitting **Scope "Per-brand - Zyro only" + Top N 5 + period
    DEMO** issued exactly 5 Zyro certs and the notification read
    *"5 issued ... Scope - tracks: perbrand; brands: brand_zyro; top 5"*.
  - Employee side (previous entry) already verified as Kavita Yadav:
    "My certificates" page + own download + cross-user denial + admin-page
    access denied.
**Gotchas for future agents:**
  - **`html_writer::tag('button', ..., ['disabled' => $x ? 'disabled' :
    null])` renders `disabled=""` when `$x` is false in this Moodle
    version** — `null` is stringified, not dropped, so the button is
    ALWAYS disabled and the form silently never submits. Fixed by only
    adding the `disabled` key to the attributes array when actually
    disabling. Watch for this pattern anywhere else (`readonly`,
    `checked`, `selected` too).
  - The `[[issuedlist]]` placeholder on the admin page was a missing lang
    string (`get_string('issuedlist', ...)` had no key) - added. If you
    see `[[somestring]]` rendered literally, it's a missing key in
    `lang/en/local_vrbcert.php`, not a code bug.
  - `mcp__claude-in-chrome` `computer` clicks by `ref_*` on the submit
    button did nothing here; clicking by coordinate worked. If a form
    won't submit under automation, fall back to a coordinate click on the
    button before assuming a server-side problem.
  - The header "My certificates" link is **conditional on the user
    holding >=1 issued certificate**; the drawer link is unconditional.
    If someone reports "I was told I have a certificate but there's no
    menu item", check (a) issuance actually ran for them, (b) they're
    looking at the header vs the drawer.
  - There is **no notification / email** sent to an employee when a
    certificate is issued - by design for now (placeholder `@noemail`
    domain can't receive mail anyway). If the client wants employees
    pinged, add a `message`/notification provider (small) - flagged, not
    built.
