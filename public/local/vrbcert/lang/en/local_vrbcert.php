<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for local_vrbcert.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'VRB certificates';

// Capabilities.
$string['vrbcert:viewown'] = 'View own VRB certificates';
$string['vrbcert:viewall'] = 'View all VRB certificates';
$string['vrbcert:manage'] = 'Manage and issue VRB certificates';

// Navigation / pages.
$string['mycertificates'] = 'My certificates';
$string['nocertificates'] = 'You have no certificates yet. Keep performing well in your training and you may earn one.';
$string['adminpageheading'] = 'VRB certificates';
$string['downloadpdf'] = 'Download PDF';
$string['overall'] = 'Overall';

// Issued-list columns.
$string['col_recipient'] = 'Recipient';
$string['col_empcode'] = 'Employee Code';
$string['col_brand'] = 'Brand / Track';
$string['col_period'] = 'Period';
$string['col_rank'] = 'Rank';
$string['col_score'] = 'Score';
$string['col_issued'] = 'Issued';
$string['col_actions'] = 'Actions';

// Admin management page.
$string['currentconfig'] = 'Current configuration';
$string['configsummary'] = 'Per-brand track: {$a->perbrand}. Overall track: {$a->overall}. Ranking strategy: {$a->strategy}. Award period: {$a->period}.';
$string['enabledyes'] = 'enabled (Top {$a})';
$string['enabledno'] = 'disabled';
$string['previewcertificate'] = 'Preview certificate';
$string['previewdesc'] = 'Opens a sample PDF built from the current template settings and dummy data. Nothing is stored.';
$string['issuenow'] = 'Issue certificates now';
$string['issuenowdesc'] = 'Evaluates the leaderboard now and issues certificates to everyone who qualifies for the period below. Safe to re-run - people who already hold a certificate for the same brand/period are skipped unless "re-issue" is ticked.';
$string['issuedlist'] = 'Issued certificates';
$string['periodlabel'] = 'Award period';
$string['reissue'] = 'Re-issue (replace) certificates that already exist for this period';
$string['runissuance'] = 'Run issuance';
$string['scopelabel'] = 'Scope';
$string['scopedesc'] = 'Which leaderboard(s) to issue from on this run. "All enabled tracks" uses whatever is turned on in settings.';
$string['scope_all'] = 'All enabled tracks (per-brand + overall)';
$string['scope_perbrand_all'] = 'Per-brand - every brand';
$string['scope_perbrand_one'] = 'Per-brand - {$a} only';
$string['scope_overall'] = 'Overall only';
$string['topnoverride'] = 'Top N (this run)';
$string['topnoverridedesc'] = 'Leave blank to use the number configured in settings for each track. Enter a number to override it for this run only (e.g. 5 = top 5).';
$string['issuancedone'] = 'Issuance complete for period "{$a->period}": {$a->issued} issued, {$a->reissued} re-issued, {$a->skipped} skipped, {$a->errors} error(s).';
$string['issuancedisabled'] = 'Certificate issuance is turned off. Enable it in the plugin settings first.';
$string['nobrandsfound'] = 'No brand cohorts were found via local_vrblms - nothing to issue.';
$string['revoke'] = 'Revoke';
$string['revokeconfirm'] = 'Revoke this certificate? The PDF is deleted and the record removed. The person can be issued a new one on the next run.';
$string['revoked'] = 'Certificate revoked.';
$string['filterbyperiod'] = 'Period';
$string['filterbybrand'] = 'Brand / track';
$string['allperiods'] = 'All periods';
$string['allbrands'] = 'All brands / tracks';
$string['nissued'] = '{$a} certificate(s) issued';
$string['backtomanage'] = 'Back to VRB certificates';

// Settings - tracks.
$string['enabled'] = 'Enable certificate issuance';
$string['enabled_desc'] = 'Master switch. When off, neither the scheduled task nor the "Issue now" button will issue anything.';
$string['perbrand_enabled'] = 'Per-brand track';
$string['perbrand_enabled_desc'] = 'Issue a certificate to the top performers in <em>each</em> brand leaderboard (Veeba, Wok Tok, Zyro).';
$string['perbrand_topn'] = 'Per-brand: number of top performers';
$string['perbrand_topn_desc'] = 'How many people from the top of each brand leaderboard receive a certificate.';
$string['overall_enabled'] = 'Overall track';
$string['overall_enabled_desc'] = 'Also issue a certificate to the top performers in the combined cross-brand ("Overall") leaderboard. An employee can qualify on this track and one or more brand tracks in the same period, receiving a certificate for each.';
$string['overall_topn'] = 'Overall: number of top performers';
$string['overall_topn_desc'] = 'How many people from the top of the Overall leaderboard receive a certificate.';
$string['strategykey'] = 'Ranking strategy';
$string['strategykey_desc'] = 'Which ranking strategy the leaderboard uses when deciding who the top performers are. "Site default" follows the local_vrblms setting.';
$string['strategy_sitedefault'] = 'Site default (from local_vrblms)';
$string['period'] = 'Award period';
$string['period_desc'] = 'A label stamped on every certificate issued and used to keep runs idempotent (e.g. "2026", "2026-Q1"). Change it to start a fresh award cycle - existing certificates are kept.';

// Settings - certificate template text.
$string['certtitle'] = 'Certificate title';
$string['certtitle_desc'] = 'The large heading printed on the certificate.';
$string['certtitle_default'] = 'Certificate of Appreciation';
$string['presentedto'] = 'Presented-to line';
$string['presentedto_desc'] = 'Small line printed just above the recipient name.';
$string['presentedto_default'] = 'This certificate is proudly presented to';
$string['bodyline1'] = 'Body line 1';
$string['bodyline2'] = 'Body line 2';
$string['bodyline_desc'] = 'Printed below the recipient name. Placeholders: {fullname} {brandname} {empcode} {score} {rank} {rankordinal} {period} {date} {sitename}';
$string['bodyline1_default'] = 'for outstanding performance in the {brandname} training programme.';
$string['bodyline2_default'] = 'Ranked {rankordinal} with an overall score of {score} for the {period} award period.';
$string['signatoryname'] = 'Signatory name';
$string['signatoryname_default'] = 'VRB Consumer';
$string['signatorytitle'] = 'Signatory title';
$string['signatorytitle_default'] = 'Learning & Development';
$string['issuedon'] = 'Issued: {$a}';

// Settings - colours / brand overrides.
$string['accentcolour'] = 'Default accent colour';
$string['accentcolour_desc'] = 'Used for the border and wordmark. Per-brand certificates use the brand colour below for the brand band.';
$string['brandlabel'] = '{$a} - printed name';
$string['brandcolour'] = '{$a} - brand colour';

// Task.
$string['taskissuecertificates'] = 'Issue VRB certificates to top performers';

// Privacy.
$string['privacy:metadata:local_vrbcert_issued'] = 'Records of certificates issued to a user.';
$string['privacy:metadata:local_vrbcert_issued:userid'] = 'The user the certificate was issued to.';
$string['privacy:metadata:local_vrbcert_issued:brandname'] = 'The brand (or "Overall") the certificate recognises.';
$string['privacy:metadata:local_vrbcert_issued:period'] = 'The award period the certificate was issued for.';
$string['privacy:metadata:local_vrbcert_issued:certrank'] = 'The leaderboard rank recorded on the certificate.';
$string['privacy:metadata:local_vrbcert_issued:scorepercent'] = 'The score percentage recorded on the certificate.';
$string['privacy:metadata:local_vrbcert_issued:timecreated'] = 'When the certificate was issued.';
$string['privacy:metadata:certificatefiles'] = 'The generated certificate PDF files issued to the user.';
