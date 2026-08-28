<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/vrblms:viewfullleaderboard' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
