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
- **Brand + Region modeling:** use Moodle **Cohorts** (`lib/cohortlib.php`).
  VERIFIED (via the actual unique-index definition on `cohortid, userid`, not
  `userid` alone) that a single user can belong to multiple cohorts
  simultaneously — so one employee is in a Brand cohort AND a Region cohort
  at the same time. Do not model this as a single "location" field.
- **State/City/Region storage:** use Moodle custom user profile fields
  (`user/profile/`), not a bespoke separate table, unless reporting/query
  needs prove otherwise during implementation.
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

## Quiz gating (module locking) — VERIFIED, this is the most load-bearing finding

VERIFIED via `lib/completionlib.php` and `mod/quiz` source: the same
`COMPLETION_COMPLETE_PASS` constant is used consistently across grading,
completion, and availability-restriction code. This confirms the intended
chain — **Activity Completion + completion-linked Restrict Access conditions
+ quiz pass grade setting** — is genuinely wired together end-to-end, not
three coincidentally-compatible pieces.

Implementation: each module's quiz activity sets a pass grade; the next
module/section uses a Restrict Access condition keyed to
"quiz X: complete and passed." Confirmable/testable directly in the Moodle UI
without custom code — this part should require zero plugin development.

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

## Certificates — OPEN, unresolved

No certificate plugin is installed in the local dev environment as of this
writing. Two candidate approaches, neither fully decided:

1. **A dedicated certificate plugin** (e.g. Custom Certificate) — need to
   verify its `version.php` `requires` field against the installed Moodle
   version (`2025100605.05` / branch `501`) before assuming compatibility.
   Do not assume an older plugin is compatible with Moodle 5.1 without
   checking this directly.
2. **Moodle core badges** (`core_badges`) — VERIFIED that `badge::issue()`
   can issue a badge to an arbitrary determined `user_id`, which is exactly
   what's needed since certificate qualification is decided by our own
   ranking logic, not Moodle's built-in "completed a course" trigger. The
   open question here is a PRODUCT decision, not technical: does a badge
   (an OpenBadge — image + JSON) satisfy the client's idea of "a certificate
   of appreciation," or do they expect a formatted, printable PDF?

**Do not silently pick one of these.** This needs an explicit decision
(ideally with client input on what "certificate" means to them) before
building the qualification-trigger logic around it.

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

## Frontend / theming — scoped but NOT yet source-verified

Confirmed architecturally (Moodle theming is PHP renderer classes → Mustache
templates → Bootstrap/SCSS, default theme is "Boost"), but unlike the backend
sections above, **the theme/template layer of THIS specific installed version
has not been directly inspected yet.** Before doing significant dashboard/
branding work, verify against the actual local codebase:
- `theme/boost/templates/` structure for the version installed
- Correct child-theme override pattern for this Moodle version
- Whether the AMD JS build pipeline (`grunt`) is functional in the local
  Docker environment

Do not assume "Moodle theming is well-documented in general" is equivalent to
"verified against this codebase" — apply the same standard used for the
backend findings above.
