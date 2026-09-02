# `local_vrbcert` — Custom PDF Certificate Plugin: Build Plan

**Created:** 2026-09-02
**Status:** active. This is the detailed breakdown for **Phase B** of
`docs/temp docs/remaining-functionality-build-plan.md`, promoted to run
**before** Phase A (`report_vrblms`) per the user (2026-09-02).

Read `Agents.md`, `CLAUDE.md`, `docs/ARCHITECTURE.md` (§ Certificates,
§ Custom plugin architecture), `docs/PROJECT_CONTEXT.md` (§ Certificates)
and `LOG.md`'s Phase 3 / Phase 4 entries first.

Working method (user's instruction): build it, integrate it, test it
end-to-end, LOG.md entry — then move to `report_vrblms`.

---

## Decision record (2026-09-02)

- **Mechanism:** a fully custom, self-coded plugin. **Not** `mod_customcert`
  (activity-module model fights our ranking-driven, cross-course trigger;
  also removes the Moodle-5.1.5 third-party compatibility risk).
- **Output:** **PDF only.** No PNG/JPG. No public verification/QR page.
- **Design:** simple and "template-ish" — a fixed base layout in code, with
  the **brand name / brand label / accent colour swapped per brand**, and
  the body text lines as editable token strings.
- **Engine:** Moodle's bundled TCPDF — `\pdf` at `public/lib/pdflib.php:159`
  (`class pdf extends TCPDF`), TCPDF + fonts under `public/lib/tcpdf/`.
  **No external dependency, no Composer.**
- **Order:** build this before `report_vrblms`.

### Leaderboard API consumption contract (hard constraint, user 2026-09-02)

`local_vrbcert` **must not reinterpret, re-rank, re-sort, re-filter, or
redesign any leaderboard logic.** It consumes the existing
`\local_vrblms\api` methods **exactly as they are**:

- **Per-brand:** `api::get_leaderboard($brandidnumber, null, null,
  $strategykey, $topn)` — one call per Brand cohort from
  `api::get_brands()`. Every row returned **is** a qualifier for that
  brand. State/City are always passed `null` (brand-level, not
  region-segmented, for certificates).
- **Overall:** `api::get_overall_leaderboard(null, null, $strategykey,
  $topn)` — every row returned **is** an overall qualifier.
- The row's `rank` and `score_percent` are used **for display on the
  certificate only** — never to re-compute or re-filter the qualifier set.
  The API already applied ranking + the Top-N `$limit`; `local_vrbcert`
  takes that output verbatim.

**Per-brand and overall are independent qualification tracks.** An
employee who places Top-N in more than one brand's leaderboard and/or in
the overall leaderboard qualifies **independently in each**, producing
**multiple certificates for the same award period** — one per brand where
they qualified, plus one for overall. The
`(userid, brandcohortid, period)` unique key (with `brandcohortid = NULL`
for overall) is what lets these coexist.

### Assumptions baked in (say if any is wrong — each is a config change, not a rebuild)

1. **Dedicated plugin** `local_vrbcert` at `public/local/vrbcert/`, declaring
   `$plugin->dependencies = ['local_vrblms' => 2026083001]`. (Kept separate
   from `local_vrblms`, which `ARCHITECTURE.md` frames as the *query/service*
   layer consumed by presentation layers — certificates are another such
   consumer, like the leaderboard page and the coming report.)
2. **Qualification is config-driven, two independent tracks**, both ON by
   default:
   - **Per-brand track** — `perbrand_enabled` (default on) + `perbrand_topn`
     (default 3). Issues one cert to each of the Top-N in *every* brand
     leaderboard.
   - **Overall track** — `overall_enabled` (default on) + `overall_topn`
     (default 10). Issues one cert to each of the Top-N in the overall
     leaderboard.
   A user can be issued from both tracks (and from multiple brands) in the
   same period. Ranking strategy defaults to the `local_vrblms` site
   default (`best`), overridable here. `score_threshold` mode is **not** in
   this build (noted under "out of scope" — easy add later).
3. **Award period / cycle:** a free-text "award period" setting
   (default = current calendar year, e.g. `2026`). Every issuance run
   stamps its certificates with the current value; uniqueness is
   `(userid, brandcohortid, period)`, so bumping the period re-issues for a
   new cycle. (Cadence — annual? quarterly? per training round? — is a
   business decision; the plugin doesn't assume one.)
4. **Issuance is triggered manually** (admin "Issue now" button + a CLI
   entry point). A scheduled task is registered but **disabled by default**
   — enable + schedule it once the cadence is known.
5. **Dynamic fields on the PDF:** recipient name, brand name, a short
   achievement line (e.g. "Top Performer — Rank {rank}"), overall score %,
   award period, issue date, Employee Code. All are token strings; drop any
   by editing the string.
6. **`local_vrblms` needs no changes** — `api::get_leaderboard($brand,
   null, null, $strategykey, $limit)` and `api::get_overall_leaderboard(...)`
   already return ranked rows with `rank, userid, fullname, idnumber,
   state, city, region, score_percent, quizzes_completed, quizzes_total,
   total_time_seconds` and honour a Top-N `$limit`. Confirmed by reading
   `public/local/vrblms/classes/api.php` on 2026-09-02.
7. **Real certificate artwork is pending from the client.** The layout is
   **code-drawn** (border, rules, VRB wordmark placeholder, seal
   placeholder) so it ships with zero assets; an optional
   "background image" admin setting is included for when real art arrives
   (drop-in, re-tune field Y-positions, ~half a day).

---

## Plugin structure

```
public/local/vrbcert/
├── version.php                 component local_vrbcert; requires 2025100600;
│                               dependencies => local_vrblms 2026083001
├── settings.php                admin settings (see § Settings)
├── lib.php                     local_vrbcert_pluginfile() (serve stored PDFs,
│                               capability-checked) + local_vrbcert_extend_navigation()
│                               ("My certificates" node, mirrors local_vrblms/lib.php)
├── mycertificates.php          employee-facing list + download page
├── index.php                   admin page: preview / issue-now / issued list
├── db/
│   ├── install.xml             local_vrbcert_issued table
│   ├── access.php              capabilities
│   └── tasks.php               scheduled task (disabled default schedule)
├── lang/en/local_vrbcert.php   all strings incl. the tokenised body lines
├── pix/                        placeholder wordmark + seal (SVG/PNG)
└── classes/
    ├── certificate_data.php        value object: everything one cert needs
    ├── template.php                resolves base + per-brand config -> render spec
    ├── certificate_generator.php   render spec + data -> PDF byte string (TCPDF)
    ├── issued_certificate.php      CRUD over local_vrbcert_issued + File API glue
    ├── issuer.php                  orchestration: qualifiers -> generate -> store -> record (idempotent)
    ├── task/
    │   └── issue_certificates.php  cron wrapper around issuer::run()
    └── output/
        ├── my_certificates_page.php   renderable for mycertificates.php
        └── admin_page.php             renderable for index.php
```

### `local_vrbcert_issued` table (`db/install.xml`)

| field | type | note |
|---|---|---|
| id | int, PK | |
| userid | int | FK-ish to `{user}` |
| brandkey | char(100) | brand cohort **idnumber** (`brand_veeba`…) for the per-brand track, or the literal `overall` for the overall track. Not null — avoids NULL-in-unique-key pitfalls, and matches the key the leaderboard API itself uses (no cohort `id` lookup needed → pure `api::*` consumption). |
| brandname | char(255) | denormalised display snapshot ("Veeba" / "Overall") |
| period | char(50) | award period label, e.g. `2026` |
| track | char(20) | `perbrand` / `overall` (redundant with `brandkey==='overall'`, kept explicit for queries) |
| rank | int, null | position at issue time, taken from the API row (display only) |
| scorepercent | number(6,2) | score at issue time, from the API row (display only) |
| strategykey | char(30) | ranking strategy used (`best` / `first` / …) |
| filename | char(255) | stored PDF filename |
| issuedby | int | admin userid, or 0 for cron |
| timecreated | int | |
| timemodified | int | |

Unique key: `(userid, brandkey, period)`.
PDF stored via File API: component `local_vrbcert`, filearea `certificate`,
`itemid = {this row}.id`, context `context_system::instance()`.

### Capabilities (`db/access.php`)

- `local/vrbcert:viewown` — archetype `user` (every employee), CAP_ALLOW.
- `local/vrbcert:viewall` — archetype `manager`.
- `local/vrbcert:manage` — archetype `manager` (issue-now, revoke,
  re-issue, edit template settings beyond what `settings.php` exposes).

Site admins bypass all checks — same posture as
`local/vrblms:viewfullleaderboard` (LOG.md 2026-08-21).

### Settings (`settings.php`, category: Site Admin → Plugins → Local plugins → "VRB certificates")

- `enabled` (checkbox — master on/off)
- `perbrand_enabled` (checkbox, default on) · `perbrand_topn` (int, default 3)
- `overall_enabled` (checkbox, default on) · `overall_topn` (int, default 10)
- `strategykey` (select: site default / best / first — reuses
  `\local_vrblms\ranking\strategy_manager` for the option list)
- `period` (text, default = `date('Y')`)
- `title` (text, default "Certificate of Appreciation")
- `bodyline1` / `bodyline2` (textarea, tokenised — see § Tokens)
- `signatoryname` / `signatorytitle` (text)
- `accentcolour` (Moodle `admin_setting_configcolourpicker`, default navy
  `#000B43`) — the fallback accent; per-brand overrides below
- `brand_veeba_label` / `brand_woktok_label` / `brand_zyro_label` (text —
  the printed brand name, default derived from the cohort name)
- `brand_veeba_colour` / `_woktok_colour` / `_zyro_colour` (colour, default
  the theme's brand accents `#E31E24` / `#F37021` / `#008080`)
- `backgroundimage` (filemanager, optional; empty = code-drawn layout)

All per-brand keys are plain settings (3 brands, fixed) — no dynamic
per-cohort settings UI.

### Tokens (expanded by `template.php` before rendering)

`{fullname}` `{firstname}` `{lastname}` `{brandname}` `{empcode}`
`{score}` (e.g. `92%`) `{rank}` (e.g. `2`) `{rankordinal}` (e.g. `2nd`)
`{period}` `{date}` (issue date, site date format) `{sitename}`

Example default `bodyline1`:
> "This certificate is proudly presented to **{fullname}** ({empcode}) for
> outstanding performance in the **{brandname}** training programme."

Example default `bodyline2`:
> "Ranked {rankordinal} with an overall score of {score} — {period}."

---

## Rendering (`certificate_generator.php`)

- `new \pdf('L', 'mm', 'A4')`; `setPrintHeader(false)`;
  `setPrintFooter(false)`; `SetAutoPageBreak(false)`; `SetMargins(0,0,0)`;
  `AddPage()`.
- Font: `freesans` (bundled, full Unicode) so accented / non-Latin names
  don't tofu. Verify the font name against `public/lib/tcpdf/fonts/`
  before coding (Agents.md: verify, don't recall).
- Layout (code-drawn, A4 landscape 297×210mm):
  - outer + inner border rectangles in the brand accent colour
  - VRB wordmark (`pix/`) top-centre; brand name band under it in the
    brand colour
  - `title` (large, centred)
  - `bodyline1` / `bodyline2` (centred, wrapped via `MultiCell`)
  - a horizontal rule, then signatory name + title left, issue date right
  - a seal/emblem placeholder bottom-centre
  - a small `{period} · {empcode}` reference line in the footer margin
- If `backgroundimage` is set: `Image($bg, 0, 0, 297, 210)` first, then the
  text layers only (border/wordmark/seal suppressed).
- `Output('', 'S')` → return the byte string. No file writes here — the
  caller stores it.
- **Preview mode:** `certificate_generator::preview()` builds a spec from
  current settings + a dummy `certificate_data` (`Jane Doe / EMP-0000 /
  Veeba / rank 1 / 95%`) and streams the PDF inline — used by the admin
  page's "Preview" button, no DB writes, no File API.

---

## Issuance (`issuer.php` + `task/issue_certificates.php`)

`issuer::run(?string $period = null, ?int $issuedby = 0, bool $reissue = false): array`

1. Read settings; bail if `!enabled`.
2. Build the qualifier list from the two independent tracks (a qualifier =
   `{userid, brandkey, brandname, track, rank, score_percent}`):
   - **Per-brand track** (if `perbrand_enabled`): for each brand from
     `\local_vrblms\api::get_brands()`, call
     `api::get_leaderboard($brand->idnumber, null, null, $strategykey,
     $perbrand_topn)` and take **every returned row** as a qualifier with
     `brandkey = $brand->idnumber`, `brandname = $brand->name`,
     `track = 'perbrand'`.
   - **Overall track** (if `overall_enabled`): call
     `api::get_overall_leaderboard(null, null, $strategykey,
     $overall_topn)` and take **every returned row** as a qualifier with
     `brandkey = 'overall'`, `brandname = 'Overall'`, `track = 'overall'`.
   - No dedup across tracks/brands — the same user legitimately appears
     multiple times with different `brandkey`. Rows are consumed verbatim
     from the API; no re-ranking or re-filtering here. Only `api::*` is
     called — no `attempt_repository` / no cohort-id lookup.
3. For each qualifier: skip if a `local_vrbcert_issued` row already exists
   for `(userid, brandkey, period)` and `!$reissue`; on `$reissue`,
   delete the old stored file + row first.
4. Build `certificate_data`, render via `certificate_generator`, store the
   PDF through the File API (`itemid` = the new row id — insert row first
   with a placeholder `filename`, store file, update `filename`), commit
   the row.
5. Return a summary: issued / skipped / re-issued / errors, grouped by
   track + brand.

- The scheduled task (`db/tasks.php`) is just `issuer::run(null, 0, false)`
  wrapped with `mtrace()` logging. **Default schedule: disabled**
  (`* * * * *` with `disabled => 1`), enabled by the admin when cadence is
  agreed.
- CLI for testing:
  `php admin/cli/scheduled_task.php --execute='\local_vrbcert\task\issue_certificates'`.

---

## Surfaces

- **Employee — `mycertificates.php`** (`login_required()`, standard
  pagelayout): lists the current user's `local_vrbcert_issued` rows
  (brand, period, rank, score, issue date) with a **Download PDF** link
  (via `pluginfile.php` → `local_vrbcert_pluginfile()`, which re-checks
  `local/vrbcert:viewown` and ownership). Nav node "My certificates" added
  in `lib.php` (mirror of `local_vrblms/lib.php`'s
  `local_vrblms_extend_navigation`). Empty state: "No certificates yet —
  keep going!"
- **Admin — `index.php`** (`require_capability('local/vrbcert:manage',
  context_system)`, `admin` pagelayout, added under Site Admin → Reports
  via `settings.php` `admin_externalpage`):
  - current criteria summary
  - **Preview** button → sample PDF inline
  - **Issue now** form (period field prefilled from settings; "re-issue
    existing" checkbox) → calls `issuer::run(...)`, shows the summary
  - **Issued list** table (all rows; filter by brand/period): user, brand,
    period, rank, score, issued date/by, Download, Revoke
- **Theme:** minimal `theme_vrblms/style/custom.css` section for the two
  pages (tables + the certificate card list) — admin-facing, so a light
  touch, consistent with the leaderboard styling approach.

---

## Build steps & estimate

| # | Step | Est. |
|---|------|------|
| 1 | Scaffold: `version.php`, `db/install.xml` (+access, +tasks), `lang`, `pix/` placeholders. Verify detection (`core_component::get_plugin_list('local')` → `admin/cli/upgrade.php` → `purge_caches`). | 0.5 d |
| 2 | `template.php` + `certificate_generator.php`: TCPDF landscape layout, token expansion, per-brand name/colour swap, `preview()`. Verify `\pdf` + font names against installed `public/lib/`. | 1.5 d |
| 3 | `issued_certificate.php` + File API storage + `local_vrbcert_pluginfile()` serving with capability/ownership checks. | 0.5 d |
| 4 | `issuer.php` + `task/issue_certificates.php` + `settings.php` (all criteria/template/brand settings) + CLI verified. | 1 d |
| 5 | `mycertificates.php` (employee) + `index.php` (admin preview/issue/list/revoke) + nav node + `custom.css` section. | 1 d |
| 6 | End-to-end test (below) + `LOG.md` entry + short update to `docs/ARCHITECTURE.md` § Certificates (mark resolved: custom `local_vrbcert`, PDF, TCPDF). | 0.75 d |

**Total ≈ 5 dev-days.** Trims: skip the admin `index.php` UI and drive
issuance from CLI only → ~4 d. Adds later: background-image artwork
integration (~0.5 d), PNG output (~0.5–1 d), verification/QR page
(~0.5 d), per-Brand→State→City criteria (~0.5 d + test permutations).

## Test plan (step 6)

- `php -l` every PHP file; plugin detected; `admin/cli/upgrade.php
  --non-interactive` clean; `purge_caches`.
- **CLI verification script** (scratchpad, not committed — the project's
  standing PHPUnit substitute) against the real 20-employee/3-brand
  dataset:
  - Per-brand N=3 → exactly 3 per brand (9 rows), each row's `rank`/`score`
    matching `api::get_leaderboard(brand, null, null, $s, 3)` verbatim
    (no re-ordering by `local_vrbcert`). Overall N=10 → 10 rows matching
    `api::get_overall_leaderboard(null, null, $s, 10)` verbatim. Total
    issued this run = 19 minus any exact `(userid, NULL, period)` vs
    `(userid, brandid, period)` — i.e. a user in Veeba Top-3 **and**
    overall Top-10 gets **2** certs, not 1 (different `brandcohortid`).
  - Multi-track confirmation: pick a user known to be Top-3 in a brand and
    Top-10 overall (e.g. `rksharma` / `kavitayadav` per LOG histories) —
    assert they have ≥2 rows for the period, one per `brandcohortid`.
  - Known histories: `rksharma` qualifies for Veeba at 100% under `best`,
    would **not** under `first` (0%) — flip `strategykey`, confirm the set
    changes. `kavitayadav` (multi-brand) can appear in more than one
    brand's per-brand set. Orphaned `testemployee1` (0%, blank location)
    must not crash generation.
  - Re-run `issuer::run()` → 0 issued / all skipped (idempotency). Run with
    `$reissue = true` → all re-issued, old files gone, one file per row.
  - Bump `period` → all issued again (new cycle), old rows intact.
- **Browser:** as a qualifying employee (`rksharma`) — "My certificates"
  lists the Veeba cert, PDF downloads, opens, shows correct name / brand /
  rank / score / period / date. As a non-qualifier — empty state. As admin
  — Preview renders; "Issue now" reports the summary; issued list + Revoke
  work; a revoked cert 404s on the old `pluginfile` URL.
- Confirm the employee cannot download another employee's cert by guessing
  the `pluginfile` URL (ownership check).

## Out of scope for this build

- `score_threshold` qualification mode (only Top-N per-brand + Top-N
  overall tracks in this build).
- PNG/JPG output, public verification/QR codes, printed-signature image
  upload, email/SMS delivery (in-Moodle notification only, if added).
- Real certificate artwork (client-pending) — code-drawn placeholder ships;
  `backgroundimage` setting is the drop-in point.
- Any `report_vrblms` work — that is Phase A, resumes after this.
