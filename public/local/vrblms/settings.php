<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_vrblms', get_string('pluginname', 'local_vrblms'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configselect(
        'local_vrblms/defaultstrategy',
        get_string('defaultstrategy', 'local_vrblms'),
        get_string('defaultstrategy_desc', 'local_vrblms'),
        \local_vrblms\ranking\strategy_manager::DEFAULT_KEY,
        \local_vrblms\ranking\strategy_manager::get_available_strategies()
    ));
}
