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
 * Admin settings and the management-page link for local_vrbcert.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_vrblms\ranking\strategy_manager;

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_vrbcert', get_string('pluginname', 'local_vrbcert'));
    $ADMIN->add('localplugins', $settings);

    // --- Master switch + qualification tracks -----------------------------
    $settings->add(new admin_setting_configcheckbox(
        'local_vrbcert/enabled',
        get_string('enabled', 'local_vrbcert'),
        get_string('enabled_desc', 'local_vrbcert'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_vrbcert/perbrand_enabled',
        get_string('perbrand_enabled', 'local_vrbcert'),
        get_string('perbrand_enabled_desc', 'local_vrbcert'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'local_vrbcert/perbrand_topn',
        get_string('perbrand_topn', 'local_vrbcert'),
        get_string('perbrand_topn_desc', 'local_vrbcert'),
        3,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_vrbcert/overall_enabled',
        get_string('overall_enabled', 'local_vrbcert'),
        get_string('overall_enabled_desc', 'local_vrbcert'),
        1
    ));
    $settings->add(new admin_setting_configtext(
        'local_vrbcert/overall_topn',
        get_string('overall_topn', 'local_vrbcert'),
        get_string('overall_topn_desc', 'local_vrbcert'),
        10,
        PARAM_INT
    ));

    // Ranking strategy - reuse local_vrblms' own registry so the option
    // list never drifts from the leaderboard's.
    $strategyoptions = ['' => get_string('strategy_sitedefault', 'local_vrbcert')]
        + strategy_manager::get_available_strategies();
    $settings->add(new admin_setting_configselect(
        'local_vrbcert/strategykey',
        get_string('strategykey', 'local_vrbcert'),
        get_string('strategykey_desc', 'local_vrbcert'),
        '',
        $strategyoptions
    ));

    $settings->add(new admin_setting_configtext(
        'local_vrbcert/period',
        get_string('period', 'local_vrbcert'),
        get_string('period_desc', 'local_vrbcert'),
        date('Y'),
        PARAM_TEXT
    ));

    // --- Certificate template text --------------------------------------
    $settings->add(new admin_setting_heading(
        'local_vrbcert/templateheading',
        get_string('certtitle', 'local_vrbcert'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_vrbcert/certtitle',
        get_string('certtitle', 'local_vrbcert'),
        get_string('certtitle_desc', 'local_vrbcert'),
        get_string('certtitle_default', 'local_vrbcert'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_vrbcert/presentedto',
        get_string('presentedto', 'local_vrbcert'),
        get_string('presentedto_desc', 'local_vrbcert'),
        get_string('presentedto_default', 'local_vrbcert'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_vrbcert/bodyline1',
        get_string('bodyline1', 'local_vrbcert'),
        get_string('bodyline_desc', 'local_vrbcert'),
        get_string('bodyline1_default', 'local_vrbcert'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_vrbcert/bodyline2',
        get_string('bodyline2', 'local_vrbcert'),
        get_string('bodyline_desc', 'local_vrbcert'),
        get_string('bodyline2_default', 'local_vrbcert'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_vrbcert/signatoryname',
        get_string('signatoryname', 'local_vrbcert'),
        '',
        get_string('signatoryname_default', 'local_vrbcert'),
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_vrbcert/signatorytitle',
        get_string('signatorytitle', 'local_vrbcert'),
        '',
        get_string('signatorytitle_default', 'local_vrbcert'),
        PARAM_TEXT
    ));

    // --- Colours / per-brand overrides --------------------------------
    $settings->add(new admin_setting_configcolourpicker(
        'local_vrbcert/accentcolour',
        get_string('accentcolour', 'local_vrbcert'),
        get_string('accentcolour_desc', 'local_vrbcert'),
        '#000B43'
    ));

    $branddefaults = ['veeba' => '#E31E24', 'woktok' => '#F37021', 'zyro' => '#008080'];
    foreach ($branddefaults as $slug => $colour) {
        $name = ucfirst($slug);
        $settings->add(new admin_setting_configtext(
            "local_vrbcert/brand_{$slug}_label",
            get_string('brandlabel', 'local_vrbcert', $name),
            '',
            '',
            PARAM_TEXT
        ));
        $settings->add(new admin_setting_configcolourpicker(
            "local_vrbcert/brand_{$slug}_colour",
            get_string('brandcolour', 'local_vrbcert', $name),
            '',
            $colour
        ));
    }
}

// Management page (issue / preview / issued list) - under Site administration > Reports.
// Access is gated by the 'local/vrbcert:manage' capability on the page itself.
$ADMIN->add('reports', new admin_externalpage(
    'local_vrbcert_manage',
    get_string('adminpageheading', 'local_vrbcert'),
    new moodle_url('/local/vrbcert/index.php'),
    'local/vrbcert:manage'
));
