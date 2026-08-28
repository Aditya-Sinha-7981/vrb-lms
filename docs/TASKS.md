# VRB LMS — Current Task Plan

Read PROJECT_CONTEXT.md and ARCHITECTURE.md before starting anything below.

**Current constraint:** production hosting access is blocked (see
DEPLOYMENT.md). All work right now happens against the local Docker
environment (`http://localhost:8080`) and is pushed to GitHub `main` as
normal — deployment to any live server is NOT part of current tasks.

## Phase 0 — Environment verification (do this first, don't skip)

- [ ] Confirm local Docker Moodle is up and matches `MOODLE_501_STABLE`.
- [ ] Confirm `local/`, `blocks/`, `report/` are currently empty of custom
      code (clean baseline).
- [ ] Verify `config.php` is git-ignored, not tracked
      (`git ls-files | grep config.php` should return nothing).

## Phase 1 — Employee data model (foundation for everything else)

- [ ] Create the two required cohorts as a manual test first (via Moodle UI,
      not code) — one Brand cohort (e.g. "Veeba"), one Region cohort (e.g.
      "Indore") — and manually add one test user to both. Confirm dual
      membership works as expected before writing any import code.
- [ ] Design the custom user profile field set for State/City/Region
      (`idnumber` already reserved for Employee Code per ARCHITECTURE.md —
      do not duplicate that).
- [ ] Build/test a bulk import CSV against `admin/tool/uploaduser/` using a
      small sample matching the client's stated column format
      (`Employee Code | Name | Email | Brand | State | City | Region`).
      Confirm cohort assignment and custom fields both populate correctly
      from one import pass.
- [ ] Document any limitation found in native bulk upload (e.g. if
      multi-cohort assignment from one CSV row turns out NOT to work
      natively) — if a custom import plugin (`local_vrbemployee`) turns out
      to be required, flag this back before building it, since the audit
      assumed native upload would be sufficient.

## Phase 2 — Learning structure + gating (validate the audit's core finding)

- [ ] Manually build ONE test brand (e.g. "Veeba") → one category → two
      dummy modules, each ending in a short quiz, via the Moodle UI.
- [ ] Configure module 2 to be Restrict-Access-locked behind module 1's quiz
      pass grade. Test end-to-end as a test employee account: fail the quiz
      → confirm module 2 stays locked; pass the quiz → confirm it unlocks.
      This is the single most important functional test in the whole
      project — do not skip or assume it works from the audit's code
      inspection alone. Verify it behaves correctly through the actual UI.
- [ ] Document the exact settings combination that achieves this (for
      eventual replication across all real brand/category content).

## Phase 3 — `local_vrblms` shared data/service layer

- [ ] Scaffold the plugin (`local/vrblms/`) following Moodle's standard
      local plugin structure (`version.php`, `db/`, `classes/`, etc.).
- [ ] Build the core query: given a Brand cohort + Region cohort filter,
      return ranked quiz results for members of both, pulling attempt data
      via the gradebook/quiz APIs identified in ARCHITECTURE.md — prefer
      documented APIs over raw SQL where one exists; fall back to direct
      query only where no API path exists, and note where that happens.
- [ ] **Ranking formula must be config-driven, not hardcoded** — the exact
      formula (first-attempt vs best, speed-weighted or not) is still an
      open business decision per PROJECT_CONTEXT.md. Build the query layer
      to accept a ranking strategy as a parameter/config, even if only one
      strategy is implemented for now.
- [ ] Write this as an internally-callable API (a class with clear public
      methods), since `block_vrblms_leaderboard`, `report_vrblms`, and the
      eventual certificate-trigger logic will all call into it — don't
      build leaderboard-specific logic that can't be reused by the other
      two consumers.

## Phase 4 — `block_vrblms_leaderboard`

- [ ] Build the front-end block: Brand/State/City filter dropdowns +
      ranked list display.
- [ ] Consumes `local_vrblms` only — no duplicate query logic in this
      plugin.
- [ ] Basic styling only for now — full branded design work is a separate,
      later effort (see ARCHITECTURE.md's note that the theme layer hasn't
      been source-verified yet; don't invest heavily in visuals before
      that's done).

## Phase 5 — `report_vrblms` (admin reporting)

- [ ] Same data source as Phase 4, different presentation: a filterable/
      exportable admin report view (Brand/State/City/Region).
- [ ] CSV export at minimum, matching the client's stated reporting need.

## Explicitly NOT in scope yet — do not start these without checking in first

- Certificate implementation — blocked on the open plugin-vs-badge decision
  in ARCHITECTURE.md. Do not build the qualification-trigger logic's output
  mechanism until this is resolved.
- Any theme/branding/dashboard redesign work — needs the theme-layer
  verification step noted in ARCHITECTURE.md first.
- Anything touching production/staging server deployment — see
  DEPLOYMENT.md, this is blocked on external hosting access.
- Real client brand content population — waiting on client-supplied content
  in an agreed format, not yet finalized.
