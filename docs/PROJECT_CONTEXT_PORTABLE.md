# VRB LMS — Project Context (Portable Version)

Use this to give a fresh AI chat (no file/repo access) enough context to
help with standalone tasks like generating dummy data, drafting content,
or answering questions about the project — without needing the full
codebase or conversation history.

## What this is

An internal employee training/LMS platform for VRB Consumer, built on
Moodle 5.1.5. Users are company employees only — this is NOT a public
course-selling platform.

## Brands

Employees learn brand-specific content across three brands:
- Veeba
- Wok Tok
- Zyro

An employee may have access to one or more brands.

## User flow

```
Employee Login (via Employee Code as username)
  → Employee Dashboard
    → Brand selection (Veeba / Wok Tok / Zyro)
      → Category-wise learning modules within that brand
        → Module content
          → Assessment/quiz
            → Must PASS to unlock next module
        → ... repeat per module
    → Performance feeds into a Regional Leaderboard
      → Top performers may receive a Certificate of appreciation
```

## Employee data model

Per employee:
- **Employee Code** — used as the Moodle username (login identifier)
- Name (first + last)
- Email (optional)
- Brand Access (one or more of: Veeba, Wok Tok, Zyro)
- State
- City
- Region

Employee accounts are bulk-imported from an HR-provided list, not
self-signup.

**Technical implementation note:** Brand and Region are each modeled as
Moodle **Cohorts** — an employee belongs to one Brand cohort AND one
Region cohort simultaneously (e.g. "Veeba" + "Indore"). State/City/Region
are also stored as custom user profile fields.

## Quiz / assessment rules

- Configurable passing percentage (not finalized, use 60% as a working
  default unless told otherwise)
- Max attempts: 3
- Randomized question order and/or selection
- **Hard requirement:** next module stays locked until the current
  module's quiz is passed.

## Regional leaderboard

Segmented, not global:
```
Brand → State → City → Employee Rankings
```
Example: Veeba → Madhya Pradesh → Indore → ranked list.

**Ranking formula is NOT finalized** — could be first-attempt score, best
score, average, completion speed, or a weighted combination. Don't assume
one formula is final when generating example data or content.

## Certificates

Auto-generated for top performers (qualification criteria TBD — Top 3,
Top N, or a score threshold). Includes Employee Name, Brand,
Achievement/score. **Implementation approach is still an open decision**
(dedicated certificate plugin vs. Moodle's built-in badge system) — don't
assume one is final.

## Admin / reporting

Admins need progress, completion %, scores, attempts, pass/fail — all
filterable/exportable by Brand and by State/City/Region.

## Mobile

Must be fully responsive — significant portion of employees are field
staff on mobile devices.

## Current build status (update this section as the project progresses)

- Moodle 5.1.5 installed locally (Docker) and theme (Moove) is active and
  styled reasonably.
- Employee cohort structure (Brand + Region) designed, not yet populated
  with real data.
- Course/module/quiz gating structure verified as technically feasible;
  one test flow built to confirm module-locking works end to end.
- Custom leaderboard/reporting/certificate plugins: **not yet built.**
  This is the largest remaining chunk of custom development work.
- Production hosting deployment: blocked on external hosting-provider
  permissions, being handled separately — not relevant to content/data
  tasks.

## What this document is good for

Generating realistic dummy content that fits this data model — test
employee lists, sample course/module names per brand, sample quiz
questions, example leaderboard entries, draft client-facing copy, etc.
Not intended for tasks requiring actual code or file access — for that,
use a coding agent with repo access instead.
