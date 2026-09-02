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
 * VRB certificates management: preview, issue, and browse issued certificates.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_vrbcert_manage');

$context = context_system::instance();
require_capability('local/vrbcert:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$baseurl = new moodle_url('/local/vrbcert/index.php');

// --- Preview: stream a sample PDF, no storage --------------------------
if ($action === 'preview') {
    $pdf = \local_vrbcert\certificate_generator::preview();
    send_file($pdf, 'vrbcert-preview.pdf', 0, 0, true, false, 'application/pdf');
    exit;
}

// --- Revoke one issued certificate -----------------------------------
if ($action === 'revoke') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    \local_vrbcert\issued_certificate::delete($id);
    redirect($baseurl, get_string('revoked', 'local_vrbcert'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Brand list (used by both the issue-scope selector and the issued-list filter).
$brands = \local_vrblms\api::get_brands();

// --- Run issuance ---------------------------------------------------
$issuesummary = null;
if ($action === 'issue' && confirm_sesskey() && data_submitted()) {
    $period = trim(optional_param('period', '', PARAM_TEXT));
    $reissue = (bool) optional_param('reissue', 0, PARAM_BOOL);
    $scopeval = optional_param('scope', 'all', PARAM_RAW);
    $topnoverride = trim(optional_param('topnoverride', '', PARAM_RAW));

    $scope = [];
    if ($scopeval === 'overall') {
        $scope['tracks'] = ['overall'];
    } else if ($scopeval === 'perbrand:all') {
        $scope['tracks'] = ['perbrand'];
    } else if (strpos($scopeval, 'perbrand:') === 0) {
        $wantbrand = substr($scopeval, strlen('perbrand:'));
        // Only accept a real brand idnumber.
        foreach ($brands as $b) {
            if ($b->idnumber === $wantbrand) {
                $scope['tracks'] = ['perbrand'];
                $scope['brandkeys'] = [$wantbrand];
                break;
            }
        }
    }
    if ($topnoverride !== '' && (int) $topnoverride > 0) {
        $scope['topn'] = (int) $topnoverride;
    }

    $issuesummary = \local_vrbcert\issuer::run($period !== '' ? $period : null, $USER->id, $reissue, $scope);
}

// --- Filters for the issued list ----------------------------------
$filterperiod = optional_param('fperiod', '', PARAM_TEXT);
$filterbrand = optional_param('fbrand', '', PARAM_TEXT);

$config = get_config('local_vrbcert');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_vrbcert'));

// Configuration summary.
$strategylabel = trim((string) $config->strategykey) === ''
    ? get_string('strategy_sitedefault', 'local_vrbcert')
    : (\local_vrblms\ranking\strategy_manager::get_available_strategies()[$config->strategykey]
        ?? $config->strategykey);
$summarydata = (object) [
    'perbrand' => !empty($config->perbrand_enabled)
        ? get_string('enabledyes', 'local_vrbcert', (int) $config->perbrand_topn)
        : get_string('enabledno', 'local_vrbcert'),
    'overall' => !empty($config->overall_enabled)
        ? get_string('enabledyes', 'local_vrbcert', (int) $config->overall_topn)
        : get_string('enabledno', 'local_vrbcert'),
    'strategy' => $strategylabel,
    'period' => s($config->period),
];
echo $OUTPUT->box(
    html_writer::tag('h3', get_string('currentconfig', 'local_vrbcert'))
    . html_writer::tag('p', get_string('configsummary', 'local_vrbcert', $summarydata))
    . html_writer::div(
        html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'local_vrbcert']),
            get_string('settings')
        ) . ' &nbsp;|&nbsp; ' .
        html_writer::link(
            new moodle_url($baseurl, ['action' => 'preview']),
            get_string('previewcertificate', 'local_vrbcert'),
            ['class' => 'btn btn-secondary btn-sm', 'target' => '_blank', 'rel' => 'noopener']
        )
    ),
    'generalbox'
);

if (empty($config->enabled)) {
    echo $OUTPUT->notification(get_string('issuancedisabled', 'local_vrbcert'), 'warn');
}

// Issue-now form.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'issue']);
echo html_writer::tag('h3', get_string('issuenow', 'local_vrbcert'));
echo html_writer::tag('p', get_string('issuenowdesc', 'local_vrbcert'), ['class' => 'text-muted']);

// Scope: which track(s) / brand(s) to issue from this run.
$scopeoptions = ['all' => get_string('scope_all', 'local_vrbcert')];
if (!empty($config->perbrand_enabled)) {
    $scopeoptions['perbrand:all'] = get_string('scope_perbrand_all', 'local_vrbcert');
    foreach ($brands as $b) {
        $scopeoptions['perbrand:' . $b->idnumber] = get_string('scope_perbrand_one', 'local_vrbcert', s($b->name));
    }
}
if (!empty($config->overall_enabled)) {
    $scopeoptions['overall'] = get_string('scope_overall', 'local_vrbcert');
}

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('scopelabel', 'local_vrbcert'), 'vrbcert-scope', true, ['class' => 'd-block']);
echo html_writer::select($scopeoptions, 'scope', 'all', false, ['id' => 'vrbcert-scope', 'class' => 'custom-select']);
echo html_writer::tag('small', get_string('scopedesc', 'local_vrbcert'), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('topnoverride', 'local_vrbcert'), 'vrbcert-topn', true, ['class' => 'd-block']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'min' => 1, 'id' => 'vrbcert-topn', 'name' => 'topnoverride',
    'value' => '', 'class' => 'form-control', 'style' => 'max-width:8rem',
    'placeholder' => 'e.g. 5',
]);
echo html_writer::tag('small', get_string('topnoverridedesc', 'local_vrbcert'), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('form-group');
echo html_writer::label(get_string('periodlabel', 'local_vrbcert'), 'vrbcert-period', true, ['class' => 'd-block']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'vrbcert-period', 'name' => 'period',
    'value' => s($config->period), 'class' => 'form-control', 'style' => 'max-width:12rem',
]);
echo html_writer::end_div();

echo html_writer::start_div('form-check mb-2');
echo html_writer::empty_tag('input',
    ['type' => 'checkbox', 'name' => 'reissue', 'value' => 1, 'id' => 'vrbcert-reissue', 'class' => 'form-check-input']);
echo html_writer::label(get_string('reissue', 'local_vrbcert'), 'vrbcert-reissue', false, ['class' => 'form-check-label']);
echo html_writer::end_div();
$runattrs = ['type' => 'submit', 'class' => 'btn btn-primary mt-2'];
if (empty($config->enabled)) {
    $runattrs['disabled'] = 'disabled';
}
echo html_writer::tag('button', get_string('runissuance', 'local_vrbcert'), $runattrs);
echo html_writer::end_tag('form');

if ($issuesummary !== null) {
    if (!empty($issuesummary['disabled'])) {
        echo $OUTPUT->notification(get_string('issuancedisabled', 'local_vrbcert'), 'warn');
    } else {
        echo $OUTPUT->notification(get_string('issuancedone', 'local_vrbcert', (object) [
            'period' => s($issuesummary['period']),
            'issued' => $issuesummary['issued'],
            'reissued' => $issuesummary['reissued'],
            'skipped' => $issuesummary['skipped'],
            'errors' => count($issuesummary['errors']),
        ]), empty($issuesummary['errors']) ? 'success' : 'warn');
        foreach ($issuesummary['errors'] as $error) {
            echo html_writer::div(s($error), 'text-danger small');
        }
        if (!empty($issuesummary['scope'])) {
            $sc = $issuesummary['scope'];
            $scparts = ['tracks: ' . implode(' + ', $sc['tracks'])];
            if (!empty($sc['brandkeys'])) {
                $scparts[] = 'brands: ' . implode(', ', $sc['brandkeys']);
            }
            if (!empty($sc['topn'])) {
                $scparts[] = 'top ' . $sc['topn'];
            }
            echo html_writer::div(s('Scope - ' . implode('; ', $scparts)), 'text-muted small');
        }
    }
}

// --- Issued list -------------------------------------------------
echo html_writer::tag('h3', get_string('issuedlist', 'local_vrbcert'));

$periods = \local_vrbcert\issued_certificate::distinct_periods();
$brandoptions = ['' => get_string('allbrands', 'local_vrbcert')];
foreach ($brands as $b) {
    $brandoptions[$b->idnumber] = $b->name;
}
$brandoptions['overall'] = get_string('overall', 'local_vrbcert');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'form-inline mb-3']);
$periodopts = ['' => get_string('allperiods', 'local_vrbcert')];
foreach ($periods as $p) {
    $periodopts[$p] = $p;
}
echo html_writer::label(get_string('filterbyperiod', 'local_vrbcert'), 'fperiod', false, ['class' => 'mr-2']);
echo html_writer::select($periodopts, 'fperiod', $filterperiod, false, ['class' => 'mr-3', 'onchange' => 'this.form.submit()']);
echo html_writer::label(get_string('filterbybrand', 'local_vrbcert'), 'fbrand', false, ['class' => 'mr-2']);
echo html_writer::select($brandoptions, 'fbrand', $filterbrand, false, ['class' => 'mr-3', 'onchange' => 'this.form.submit()']);
echo html_writer::end_tag('form');

$records = \local_vrbcert\issued_certificate::get_all([
    'period' => $filterperiod,
    'brandkey' => $filterbrand,
]);

if (!$records) {
    echo $OUTPUT->notification(get_string('nissued', 'local_vrbcert', 0), 'info');
} else {
    $userids = array_column($records, 'userid');
    $users = $DB->get_records_list('user', 'id', array_unique($userids), '', 'id, firstname, lastname, idnumber');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable vrbcert-issued';
    $table->head = [
        get_string('col_recipient', 'local_vrbcert'),
        get_string('col_empcode', 'local_vrbcert'),
        get_string('col_brand', 'local_vrbcert'),
        get_string('col_period', 'local_vrbcert'),
        get_string('col_rank', 'local_vrbcert'),
        get_string('col_score', 'local_vrbcert'),
        get_string('col_issued', 'local_vrbcert'),
        get_string('col_actions', 'local_vrbcert'),
    ];

    foreach ($records as $record) {
        $user = $users[$record->userid] ?? null;
        $name = $user ? fullname($user) : ('#' . $record->userid);
        $empcode = $user ? $user->idnumber : '';
        $score = $record->scorepercent === null ? '' : format_float($record->scorepercent, 1) . '%';

        $download = html_writer::link(
            \local_vrbcert\issued_certificate::file_url($record),
            get_string('downloadpdf', 'local_vrbcert'),
            ['class' => 'btn btn-secondary btn-sm', 'target' => '_blank', 'rel' => 'noopener']
        );
        $revoke = html_writer::link(
            new moodle_url($baseurl, ['action' => 'revoke', 'id' => $record->id, 'sesskey' => sesskey()]),
            get_string('revoke', 'local_vrbcert'),
            [
                'class' => 'btn btn-outline-danger btn-sm ml-1',
                'data-confirmation' => 'modal',
                'onclick' => "return confirm('" . addslashes_js(get_string('revokeconfirm', 'local_vrbcert')) . "');",
            ]
        );

        $table->data[] = [
            s($name),
            s($empcode),
            s($record->brandname) . ' / ' . s($record->track),
            s($record->period),
            $record->certrank === null ? '' : s($record->certrank),
            $score,
            userdate($record->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $download . $revoke,
        ];
    }

    echo html_writer::table($table);
    echo html_writer::tag('p', get_string('nissued', 'local_vrbcert', count($records)), ['class' => 'text-muted']);
}

echo $OUTPUT->footer();
