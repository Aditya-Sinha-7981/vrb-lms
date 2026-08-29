# Task: Full Design-Match Audit (design_refer vs. live Moodle build)

## Scope of this task — read carefully before starting

This is an AUDIT ONLY. The output is a report. You will not write, edit,
or touch any CSS, SCSS, template, PHP, or config file as part of this
task, under any circumstance. You will not propose a plan or a build
order — that happens in a separate task after this report is reviewed.
If you find yourself about to make a change "just to check something,"
stop — inspect via read-only means instead (dev tools, getComputedStyle,
viewing source), never by editing.

Read `AGENTS.md` and `LOG.md` first if you haven't already this session.

## Step 1 — Recursively inventory `docs/design_refer/`

Do not assume the folder structure. Walk it fully and report what you
actually find before doing any comparison work. For each screen/subfolder
present, identify:
- Any `code.html` (or equivalent exported code) — this is the
  authoritative source for exact values (colors, spacing, type scale,
  component markup). Treat this as ground truth over any prose
  description.
- Any design token / config file (e.g. a Tailwind config, a `DESIGN.md`,
  a style guide doc) — extract colors, typography, radius, spacing scale
  from here. If a prose doc and a code config disagree on a value (this
  has happened before — radius values previously conflicted between
  `DESIGN.md` and the per-screen Tailwind configs), **trust the code
  config, note the conflict explicitly, do not silently pick one without
  flagging it.**
- Any screenshot/image files — use these to confirm visual intent
  (layout, hierarchy, hover/state variations) where the code alone is
  ambiguous, but do not treat an image as more authoritative than the
  actual exported code.

List every screen found, even if a previous audit already covered some of
them — re-verify, don't rely on a prior report's memory, since the live
build may have changed since then (check LOG.md for any implementation
work done after the last audit, if one exists).

## Step 2 — Inspect the live build for each screen found

For every screen identified in Step 1, load the corresponding real page
in the running Moodle instance (localhost:8080) and inspect what's
ACTUALLY rendering — computed styles via dev tools inspection, real DOM
structure, real component markup. Do not infer this from memory of past
sessions or from reading source files alone — the rendered output is what
matters, since Moodle/Bootstrap can transform markup in ways that aren't
obvious from the template file alone.

Cover, at minimum, whichever of these have a design_refer counterpart:
login, post-login dashboard/landing, brand course/module list, quiz
attempt, leaderboard, and any others present in the export.

If testing a page requires a mutating/destructive action (e.g. starting a
fresh quiz attempt), use a non-destructive equivalent (e.g. a review page
using the same render pipeline) instead, and say clearly which stand-in
you used and why.

## Step 3 — Produce the comparison

Output a table, one row per screen:
`Screen | Design spec (brief) | Live render (brief) | Verdict`

Verdict must be one of exactly: **MATCHES / MINOR DRIFT / MAJOR DRIFT /
NOT YET BUILT**. Do not invent other labels. NOT YET BUILT means the
screen doesn't exist as a real page at all yet — don't conflate that with
"exists but looks different."

## Step 4 — Feasibility classification (this is the core deliverable)

For every DRIFT or NOT YET BUILT row, classify the work needed into
exactly one of:

- **EASY** — a CSS/token-level change in our own theme code
  (`theme_vrblms`'s `custom.css` or equivalent), no Moodle template or
  core involvement, low risk.
- **MODERATE** — new code but self-contained and low upstream coupling
  (e.g. a new page in a local plugin we already own, a body-class
  injection mechanism we control).
- **STRUCTURAL / HARD** — requires overriding Moodle core renderer
  output, core templates, or core layout files. Explicitly flag anything
  that would need touching `core_courseformat`, question-engine markup,
  or Moove/Boost's own layout mustaches — these are historically fragile
  across Moodle upgrades. Say so plainly.
- **NOT RECOMMENDED** — technically possible but fights Moodle's
  architecture badly enough (e.g. replacing Moove's entire navigation
  chrome with a custom sidebar) that it should probably not be attempted
  at all. Say so plainly, don't soften this into "hard" if you genuinely
  think it shouldn't be done.

For every EASY and MODERATE item, write a precise, actionable note (exact
property/value changes, or exact new-surface description) — specific
enough that a future implementation task could act on it directly without
re-deriving your reasoning. For STRUCTURAL/HARD and NOT RECOMMENDED items,
explain the specific coupling risk, not just "this is hard."

## Step 5 — Consolidated recommendation

Close with:
- A rough proportion: how much of the total gap is EASY/MODERATE
  (low-risk, worth doing) vs. STRUCTURAL/HARD/NOT RECOMMENDED (should
  probably be deferred or skipped).
- A suggested priority order for the EASY/MODERATE items specifically,
  ranked by (a) how many screens/how much of the site each change
  touches, and (b) how central it is to work we've already told the
  client is done (e.g. the leaderboard, since that's been represented as
  ahead of scope — its visual polish matters more than a page nobody's
  seen yet).
- An explicit list of anything you think should be OUT OF SCOPE entirely
  for the next build pass, with your reasoning, not just a vague "maybe
  later."

## Output format

A single markdown report in your response. Do not create a new file in
`docs/` for this — it's a point-in-time audit, not a permanent doc. Stop
completely after delivering the report. Do not draft an implementation
plan, do not start making changes, do not ask if you should proceed —
just deliver the audit and stop.

Design reference folder: @design_refer