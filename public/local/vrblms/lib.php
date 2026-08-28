<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Adds a "Leaderboard" link to primary navigation for every logged-in
 * user. Confirmed against this Moodle version's
 * global_navigation::load_local_plugin_navigation() (lib/classes/
 * navigation/global_navigation.php) that this legacy per-plugin callback
 * is still dispatched for `local` plugins - simpler than the newer
 * core\hook\navigation\primary_extend hook, and still fully supported
 * here, so used in preference to it.
 */
function local_vrblms_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $navigation->add(
        get_string('leaderboard', 'local_vrblms'),
        new moodle_url('/local/vrblms/leaderboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'vrblmsleaderboard'
    );
}
