# CSV Import Spec — Employee Bulk Upload via `admin/tool/uploaduser`

This spec is based on direct inspection of the actual installed code at
`public/admin/tool/uploaduser/` (classes/process.php, user_form.php,
locallib.php, example.csv) and the actual live database state, on this
exact install (Moodle `2025100605.05`, branch `501`, first verified
2026-08-19, prerequisites resolved and format finalized 2026-08-19,
password-onboarding scheme revised 2026-08-20).
It is NOT based on general Moodle documentation. Where something couldn't
be verified because it doesn't exist yet in this install, it's called out
explicitly below rather than guessed.

**Status: FINAL.** All blocking prerequisites from the original version of
this spec are now resolved — see `LOG.md` for the resolution entries.
**2026-08-20 revision: §5's original "no password column" recommendation
was reversed** after it produced a real, unrecoverable onboarding gap in
practice — see §5 for the corrected scheme and why.

---

## 1. Final column headers, in order

```
username,idnumber,firstname,lastname,email,password,cohort1,city,profile_field_state,profile_field_region
```

| Column | Moodle field type | Required? | Notes |
|---|---|---|---|
| `username` | standard | **Required** | Login identifier. Must already be in final clean form — see §4, Moodle does not silently fix it for you on import. |
| `idnumber` | standard | Recommended (not tool-enforced) | Confirmed: plain `idnumber` column name, no prefix, is a recognized standard field (`process.php:152`). This is where the human-readable Employee Code goes, per ARCHITECTURE.md. |
| `firstname` | standard | **Required** | Enforced via `useredit_get_required_name_fields()`. |
| `lastname` | standard | **Required** | Same as above. |
| `email` | standard | **Required in practice** | Confirmed in `process.php:988-990`: a blank email hard-rejects the row (`'invalidemail'`, unconditional in the new-account path). Also must be unique across existing users — `$CFG->allowaccountssameemail = '0'` on this install. **For employees without a real email, use the placeholder pattern `{idnumber}@noemail.vrbconsumer.internal`** (e.g. `EMP-1042@noemail.vrbconsumer.internal`) — this satisfies the tool's non-blank+unique requirement (uniqueness follows from Employee Code uniqueness) without implying a real, monitored mailbox. |
| `password` | standard | **Required** (revised 2026-08-20) | **Set equal to the same value as that row's `idnumber`** (the Employee Code) — e.g. `idnumber=EMP-1042` → `password=EMP-1042`. See §5 for the full reasoning and the required upload-form settings this depends on. |
| `cohort1` | special field | Optional, but this is how Brand access is granted | Confirmed cohort assignment via CSV is natively supported (`locallib.php`, regex `^cohort\d+$`). **`cohort1` expects a Brand cohort's `idnumber` only** — one of `brand_veeba`, `brand_woktok`, `brand_zyro` — not a numeric id (ids differ across environments; idnumbers don't) and not a State/City/Region value (those are not cohorts — see the ARCHITECTURE.md correction referenced in §2). Only one `cohort1` column is used; an employee with access to more than one brand needs more than one row processed for cohort assignment (a second `cohort2` column was considered in an earlier draft of this spec but was dropped from the final format — not in scope for the current single-cohort-column CSV). |
| `city` | standard | Optional | Reuses Moodle's built-in `city` user field — no custom field was created for this, per the resolution in §2. |
| `profile_field_state` | custom profile field | Optional | Field confirmed created: shortname `state`, category "Employee Details" (`user_info_field.id=1`). |
| `profile_field_region` | custom profile field | Optional | Field confirmed created: shortname `region`, category "Employee Details" (`user_info_field.id=2`). |

## 2. Prerequisites — resolution status

All items below were open in the original version of this spec. Resolved
2026-08-19 (see `docs/ARCHITECTURE.md`'s Employee Accounts section and
`LOG.md` for the reasoning):

1. ✅ **State/City/Region are profile fields, not cohorts — confirmed as
   the final data model.** ARCHITECTURE.md previously had inconsistent
   language suggesting Region might be a cohort; that's been corrected.
   Cohorts are reserved for Brand only, because Brand is the only part of
   this model that needs cohort-level features (`enrol_cohort` for
   automatic course-category access). State/City/Region don't need that,
   and cohorts — being flat, unordered membership sets — don't fit the
   Brand → State → City *hierarchy* the leaderboard needs anyway.
2. ✅ **`city` naming collision — resolved by NOT creating a custom
   field.** Confirmed no `user_info_field` row exists with shortname
   `city`; the CSV's `city` column maps to Moodle's standard field
   directly.
3. ✅ **Custom profile fields created.** `mdl_user_info_field` now
   contains exactly two rows: `state` (id=1) and `region` (id=2), both
   under a new "Employee Details" category (`mdl_user_info_category`,
   id=1), both `datatype=text`. Verified via
   `profile_get_user_fields_with_data()` — the same function
   `admin/tool/uploaduser` itself calls — that it resolves them to CSV
   columns `profile_field_state` and `profile_field_region`.
4. ✅ **All three Brand cohorts now exist.** `mdl_cohort`:

   | id | name | idnumber |
   |---|---|---|
   | 1 | Veeba | `brand_veeba` |
   | 3 | Wok Tok | `brand_woktok` |
   | 4 | Zyro | `brand_zyro` |

   (id=2, `Indore`/`region_indore`, is a leftover from the Phase 1
   mechanical dual-cohort-membership test — it predates the State/City/
   Region-as-profile-fields decision and is **not** part of the Brand
   cohort set. It still exists in the database as of this writing; not
   removed as part of this task since deletion wasn't requested.)

## 3. Example row (fake/placeholder data)

```csv
username,idnumber,firstname,lastname,email,password,cohort1,city,profile_field_state,profile_field_region
rksharma-1042,EMP-1042,Rajesh,Sharma,EMP-1042@noemail.vrbconsumer.internal,EMP-1042,brand_veeba,Indore,Madhya Pradesh,Central
```

Notes on this example:
- `username` (`rksharma-1042`) is a placeholder derived-and-sanitized form
  of a name+code combo, already lowercase and containing only characters
  Moodle's username cleaner allows (see §4) — illustrative of the *shape*
  required, not a proposal for the actual derivation algorithm (separate
  design work, not yet done).
- `email` follows the finalized placeholder pattern
  `{idnumber}@noemail.vrbconsumer.internal` for an employee with no real
  email on file.
- `password` is set to the raw `idnumber` value (`EMP-1042`) — see §5 for
  why this is safe despite being a predictable/"weak" password.
- `cohort1` uses the Brand cohort's idnumber (`brand_veeba`), not its
  numeric id.

## 4. Username validation — reconfirmed against this install

Found the actual implementation at `public/lib/classes/param.php`, method
`clean_param_value_username()` (this logic moved out of `moodlelib.php` in
this Moodle version — the `PARAM_USERNAME` constant now just delegates to
`\core\param::USERNAME`). Confirmed behavior, with
`$CFG->extendedusernamechars` confirmed **off** (`'0'`) on this install:

1. Trim whitespace, lowercase everything.
2. Strip all spaces.
3. Strip every character that isn't `a-z`, `0-9`, `-`, `.`, `_`, or `@`.

This matches what ARCHITECTURE.md already stated, now confirmed against
the actual code rather than general knowledge. One additional detail
ARCHITECTURE.md didn't call out: **the uploader does not apply this
cleaning and use the result — it applies the cleaning and then compares it
to what you supplied, and rejects the row if they don't match**
(`process.php:438-440`, `'invalidusername'` error). Don't rely on Moodle to
sanitize a messy Employee Code for you at import time — the CSV generation
step must produce an already-clean username, or every row with an unclean
one will simply fail.

## 5. Password handling — REVISED 2026-08-20: Employee Code as initial password

**This section originally recommended omitting the `password` column
entirely and relying on Moodle's "Create password if needed" option. That
recommendation is reversed — it created a real, unrecoverable onboarding
gap in practice, surfaced during Phase 2 testing (see `LOG.md`'s
2026-08-19 Phase 2 entry) and now resolved.**

### What went wrong with the original recommendation

"Create password if needed" makes Moodle auto-generate a random password
per user. Moodle's normal way to get that password to the user is to
**email it to them.** Every employee without a real email uses the
`{idnumber}@noemail.vrbconsumer.internal` placeholder (§1) — a domain that
cannot receive mail. The random password was generated, silently
undeliverable, and then gone: not visible anywhere in the UI, not
recoverable via "Lost password?" (which also just tries to email a reset
link to the same undeliverable address), not retrievable by an admin
without directly manipulating the database. Confirmed by testing: this
left the Phase 1 test employee (`rksharma`) with no way to log in at all
under the original scheme.

### The fix: Employee Code doubles as the initial password

**Set the `password` column to the same value as that row's `idnumber`**
(§1, §3), and configure the upload form so every account is forced to
change it on first login. This works because the Employee Code is already
something the employee has (it's their login identifier too) — no new
secret needs to be distributed through an unreliable channel, and the
forced change means the Employee-Code password is never actually used
long-term.

**Required upload-form settings** (Site Administration → Users → Upload
users, or the equivalent CLI flags):
- **New user password:** "In file" (`uupasswordnew = 0`) — NOT "Create
  password if needed". This tells the tool to use the `password` column's
  value rather than generating one.
- **Force password change:** **"All passwords"** (`uuforcepasswordchange
  = UU_PWRESET_ALL`, confirmed constant value `2` in
  `admin/tool/uploaduser/locallib.php`) — not "Weak passwords only". Use
  "All passwords" specifically so every account is forced through the
  change flow uniformly, regardless of whether a given Employee Code
  happens to accidentally satisfy the site's password policy.

### Why "weak" passwords are accepted at all

This install has `$CFG->passwordpolicy` enabled (min 8 chars, 1 digit, 1
lowercase, 1 uppercase, 1 special character — confirmed via live config).
An Employee Code like `EMP-1042` does **not** satisfy this policy (no
lowercase letter). Confirmed in `process.php:1029-1033`: a policy-violating
password in the CSV is **not a hard error** — `check_password_policy()`
failing only logs a `'warning'` (`$this->weakpasswords++`) and the account
is still created with that password. The row is not rejected. Combined
with `uuforcepasswordchange = UU_PWRESET_ALL`, Moodle sets the
`auth_forcepasswordchange` user preference to `1` on every new account
(`process.php:1061-1063`), which is the same native mechanism the "Lost
password" / admin-reset flows use to require a change at next login.

### Verified end-to-end (2026-08-19/20, real browser login, not simulated)

Reset a real imported employee account (`rksharma`, EMP-1001) to this
exact scheme — password set to `EMP-1001`, `auth_forcepasswordchange`
preference set to `1` — then logged in through an actual browser session
(not an internal API call):
1. Logging in with username `rksharma` / password `EMP-1001` succeeded.
2. Moodle immediately redirected to `login/change_password.php` with
   "You must change your password to proceed." — the forced-change prompt
   actually appeared, not just configured-and-assumed.
3. Submitted a new password meeting the site's policy → "Password has been
   changed" → normal access to the dashboard resumed.

(This test run also hit and got past an unrelated theme-rendering bug on
`login/change_password.php` — see `LOG.md`'s 2026-08-20 child-theme entry.
That bug was specific to a mid-testing theme change, not to this password
scheme, and is fully resolved.)

### Why this is still safe despite being a "predictable" password

The Employee Code is not a long-term credential — it's a one-time
bootstrap value that's immediately invalidated the moment the employee
changes it, which they're forced to do before they can do anything else.
Anyone who could guess an Employee Code (e.g. a coworker) gains nothing
once the real employee has logged in once, since the Employee-Code
password stops working right after the forced change. This is a standard,
well-understood onboarding pattern (temporary/known initial credential +
mandatory first-login change) — not a weakened version of normal password
security.

## 6. Required vs. optional — final summary

**Always required, every row:** `username`, `firstname`, `lastname`,
`email` (use the `{idnumber}@noemail.vrbconsumer.internal` placeholder
pattern from §1 when no real email exists), `password` (set to that row's
`idnumber` value — see §5, revised 2026-08-20).

**Strongly recommended, not tool-enforced:** `idnumber` (Employee Code) —
nothing in the uploader forces this to be present, but every downstream
piece of ARCHITECTURE.md's design (leaderboard joins, reporting) assumes
it's populated, and as of the §5 revision the `password` column also
depends on it being present and correct.

**Optional, tool-enforced only when present:** `cohort1`, `city`,
`profile_field_state`, `profile_field_region`. A blank cell in any of
these is fine; Moodle just leaves that field unset for the row. Note that
leaving `cohort1` blank means the employee gets no Brand cohort
membership, and therefore no Brand-gated access — this should not be left
blank in real data.

**Required upload-form settings** (not CSV columns, but load-bearing for
§5 to work): New user password = "In file", Force password change = "All
passwords". See §5 for why both matter.
