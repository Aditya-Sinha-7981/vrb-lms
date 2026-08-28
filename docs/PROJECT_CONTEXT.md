# VRB LMS — Project Context

## What this is

An internal employee training/LMS platform for VRB Consumer, built on Moodle.
This is NOT a public course-selling platform — users are company employees only.

## Brands

Employees learn brand-specific content across three brands:
- Veeba
- Wok Tok
- Zyro

An employee may have access to one or more brands.

## User flow

```
Employee Login (via Employee Code)
  → Employee Dashboard
    → Brand selection (Veeba / Wok Tok / Zyro)
      → Category-wise learning modules within that brand
        → Module content
          → Assessment/quiz
            → Must PASS to unlock next module
              → (fail = next module stays locked)
        → ... repeat per module
    → Performance feeds into a Regional Leaderboard
      → Top performers may receive a Certificate of appreciation
```

## Employee data model

Per employee:
- Employee Code (intended as WordPress-style login identifier — see
  ARCHITECTURE.md for the Moodle-native implementation of this)
- Name
- Email (optional)
- Brand Access (one or more brands)
- State
- City
- Region

Employee accounts will be bulk-imported from an HR-provided list, not
self-signup. Example source columns:
`Employee Code | Name | Email | Brand | State | City | Region`

## Quiz / assessment rules

- Configurable passing percentage (client to confirm exact number, e.g. 60%)
- Max attempts (expected: 3)
- Randomized question order and/or question selection
- Score tracking, attempt history, completion tracking
- **Hard requirement:** next module stays locked until the current module's
  quiz is passed. This is the core mechanic of the whole product.

## Regional leaderboard — the primary custom requirement

Not a single global leaderboard. Segmented:

```
Brand → State → City → Employee Rankings
```

Example: Veeba → Madhya Pradesh → Indore → ranked list of employees.

Needs filters/dropdowns: Brand, State, City/Region.

**Ranking formula is NOT finalized.** Could be:
- First-attempt score
- Best-attempt score
- Average/overall performance
- Completion speed
- Completion date
- Some weighted combination

Because of this, the leaderboard's ranking logic must be **configurable/
extensible**, not hardcoded to one formula. Do not hardcode a ranking formula
into a plugin without an explicit config layer around it.

## Certificates

Auto-generated, for top-performing employees only (qualification criteria
TBD — could be Top 3, Top N per Brand→State→City, a score threshold, etc.).

Should include dynamic data: Employee Name, Brand, Achievement/score/badge.

**Status: open decision, not yet resolved.** See ARCHITECTURE.md § Certificates
for the two candidate approaches (certificate plugin vs. Moodle core badges)
and what's still unverified.

## Admin / reporting requirements

Admins need to see, filterable/exportable by Brand and by State/City/Region:
- Employee progress, completion %
- Quiz/test scores, attempts, pass/fail
- Completed/incomplete modules

**Architectural note:** Regional Leaderboard, Regional Reporting, and Top
Performer Certificate Qualification should share ONE underlying data/service
layer rather than being built as three separate systems. All three are reads
against the same combination of (quiz/completion data) + (employee brand/
region data).

## Mobile

Must be fully responsive — a significant portion of employees are field
staff accessing primarily via mobile. Not a nice-to-have.

## Content

Client supplies actual module content and quiz questions per brand/category.
We build the platform/shell only. Content delivery format (e.g. CSV template
for bulk quiz question import) still to be finalized with the client.

## What was explicitly evaluated and rejected before landing on this architecture

- **WordPress + LearnDash / Tutor LMS** — technically capable, but recurring
  license cost ($199-799/yr) was a blocker for an internal tool budget.
- **Standalone commercial scripts (Academy LMS, Rocket LMS)** — one-time
  purchase, full source access, but built for public course marketplaces
  (payments, affiliates, instructor storefronts) — real mismatch with a
  closed internal-training use case, and neither ships native
  region-segmented leaderboards either (the one thing that matters most is
  custom either way, regardless of platform).
- **Landed on Moodle** — free/open-source, no license cost ever, mature
  quiz/completion engine, built for exactly this use case (closed
  institutional/corporate training), full source + documented extension
  architecture (blocks/local/report plugin types) rather than a single
  vendor's undocumented codebase.

This decision was made based on a full feasibility audit against actual
Moodle 5.1.5 source code — see ARCHITECTURE.md for what was verified.
