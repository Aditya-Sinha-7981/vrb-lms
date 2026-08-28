<?php
/**
 * Regional leaderboard page. Managers/admins (local/vrblms:viewfullleaderboard)
 * get full Brand/State/City/Ranking filters, including an "Overall (all
 * brands)" combined view. Everyone else gets a fixed, unfiltered view of
 * their own brand(s)' leaderboard(s) plus the overall one, with their own
 * row highlighted - no filter controls, per design (see LOG.md).
 */

require(__DIR__ . '/../../config.php');

use local_vrblms\api;
use local_vrblms\ranking\strategy_manager;
use local_vrblms\output\leaderboard_view;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/vrblms/leaderboard.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('leaderboard', 'local_vrblms'));
$PAGE->set_heading(get_string('leaderboard', 'local_vrblms'));

$canviewall = has_capability('local/vrblms:viewfullleaderboard', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('leaderboard', 'local_vrblms'));

if ($canviewall) {
    $brands = api::get_brands();
    if (empty($brands)) {
        echo html_writer::tag('p', get_string('nobrands', 'local_vrblms'));
        echo $OUTPUT->footer();
        die;
    }

    $brandidnumbers = array_map(fn($b) => $b->idnumber, $brands);

    // Empty string = "Overall (all brands)" - the admin's default view.
    $selectedbrand = optional_param('vrbbrand', '', PARAM_ALPHANUMEXT);
    if ($selectedbrand !== '' && !in_array($selectedbrand, $brandidnumbers, true)) {
        $selectedbrand = '';
    }
    $isoverall = ($selectedbrand === '');

    $states = $isoverall ? api::get_overall_states() : api::get_states($selectedbrand);
    $selectedstate = optional_param('vrbstate', '', PARAM_TEXT);
    if ($selectedstate !== '' && !in_array($selectedstate, $states, true)) {
        $selectedstate = '';
    }

    $cities = $isoverall
            ? api::get_overall_cities($selectedstate !== '' ? $selectedstate : null)
            : api::get_cities($selectedbrand, $selectedstate !== '' ? $selectedstate : null);
    $selectedcity = optional_param('vrbcity', '', PARAM_TEXT);
    if ($selectedcity !== '' && !in_array($selectedcity, $cities, true)) {
        $selectedcity = '';
    }

    $strategies = strategy_manager::get_available_strategies();
    $selectedstrategy = optional_param('vrbstrategy', '', PARAM_ALPHA);
    if ($selectedstrategy !== '' && !array_key_exists($selectedstrategy, $strategies)) {
        $selectedstrategy = '';
    }

    $rows = $isoverall
            ? api::get_overall_leaderboard(
                    $selectedstate !== '' ? $selectedstate : null,
                    $selectedcity !== '' ? $selectedcity : null,
                    $selectedstrategy !== '' ? $selectedstrategy : null)
            : api::get_leaderboard(
                    $selectedbrand,
                    $selectedstate !== '' ? $selectedstate : null,
                    $selectedcity !== '' ? $selectedcity : null,
                    $selectedstrategy !== '' ? $selectedstrategy : null);

    echo leaderboard_view::render_filter_form($PAGE->url, $brands, $selectedbrand, $states, $selectedstate,
            $cities, $selectedcity, $strategies, $selectedstrategy);
    echo leaderboard_view::render_table($rows);

} else {
    $ownbrands = api::get_user_brands($USER->id);

    if (empty($ownbrands)) {
        echo html_writer::tag('p', get_string('notenrolled', 'local_vrblms'));
    } else {
        foreach ($ownbrands as $brand) {
            echo $OUTPUT->heading(get_string('courseheading', 'local_vrblms', format_string($brand->name)), 3);
            $rows = api::get_leaderboard($brand->idnumber);
            echo leaderboard_view::render_table($rows, (int) $USER->id);
        }
    }

    echo $OUTPUT->heading(get_string('overallheading', 'local_vrblms'), 3);
    $overallrows = api::get_overall_leaderboard();
    echo leaderboard_view::render_table($overallrows, (int) $USER->id);
}

echo $OUTPUT->footer();
