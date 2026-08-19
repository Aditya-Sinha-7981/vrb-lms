Read AGENTS.md and LOG.md first if you haven't already this session.

TASK: Investigate the actual, currently-installed Moodle 5.1.5 environment
and produce a precise CSV format specification for bulk-creating test
employee accounts via admin/tool/uploaduser. Do NOT generate the actual
dummy data yet — this task is only about determining the exact, verified
column format the CSV needs. Do not guess or use general Moodle knowledge
without confirming against this actual install — you already know why.

Specifically verify and report on:

1. **admin/tool/uploaduser's actual expected format.**
   - What are the required vs. optional column headers for this tool, in
     this installed version? Check the actual code
     (public/admin/tool/uploaduser/) rather than assuming from general
     Moodle knowledge, since column requirements/behavior can vary by
     version.
   - Confirm whether `username`, `password`, `firstname`, `lastname`,
     `email` are all mandatory, and what happens if `email` is blank
     (per our requirement, email is optional for employees).
   - Confirm the exact column name/format for setting `idnumber` (this is
     where we're storing the human-readable Employee Code, per
     ARCHITECTURE.md) via this upload tool.

2. **Custom profile fields for State/City/Region.**
   - Check whether these custom profile fields currently exist in this
     install (Site Administration → Users → Profile fields, or query the
     relevant DB table directly). If they don't exist yet, this needs to
     be flagged — the CSV format can't be finalized until the fields
     exist to receive the data.
   - If they exist, confirm the exact `profile_field_` column-naming
     convention this upload tool expects for custom fields (Moodle
     typically expects a `profile_field_shortname` format — confirm the
     exact shortnames configured, don't assume).

3. **Cohort assignment via this same CSV upload.**
   - Confirm whether admin/tool/uploaduser supports assigning cohort
     membership directly during bulk upload (some Moodle versions/configs
     require a separate cohort-sync step instead). Check the actual code
     to confirm, don't assume based on general knowledge.
   - If cohorts for our test Brands (Veeba, Wok Tok, Zyro) and a couple of
     test Regions don't exist yet in this install, note that as a
     prerequisite step, not something to skip past.
   - Confirm the exact column format/syntax needed if cohort assignment
     via CSV is supported (e.g. a `cohort1` column expecting a cohort ID
     or idnumber — check which, don't guess).

4. **Username generation constraint (already documented in
   ARCHITECTURE.md — verify it's still accurate for this install).**
   - Confirm Moodle's username validation rules (lowercase, allowed
     characters) haven't changed from what was previously documented, by
     checking the actual validation code
     (likely in public/lib/moodlelib.php or similar — find the actual
     function, don't assume the location).

5. **Password handling for bulk-created accounts.**
   - Determine whether the CSV should include a password column directly,
     or whether it's better to use uploaduser's "force password change"
     / auto-generated password option for test accounts. State which
     approach you're recommending and why.

OUTPUT: A markdown file at docs/CSV_IMPORT_SPEC.md containing:
- The exact, verified column headers required for our CSV, in the correct
  order, based on what you found in steps 1-5 (not a generic Moodle
  tutorial's format — OUR specific configured fields).
- One example row showing realistic (but clearly fake/placeholder) data
  matching every column.
- A short "prerequisites" section listing anything that must exist in
  Moodle BEFORE this CSV can be successfully imported (e.g. custom
  profile fields must be created first, cohorts must exist first) if you
  found any such dependency during investigation.
- A note on which fields are required vs. optional, based on what you
  actually confirmed, not assumed.

Add a LOG.md entry once this is done, per the standard format, noting
what was verified and any gotchas found (e.g. if the custom profile
fields didn't exist yet and had to be created first, or if cohort-CSV
assignment turned out not to be natively supported).
