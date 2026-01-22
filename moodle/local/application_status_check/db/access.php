<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/application_status_check:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'guest' => CAP_ALLOW,
            'user' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
