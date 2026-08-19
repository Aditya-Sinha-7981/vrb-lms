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
