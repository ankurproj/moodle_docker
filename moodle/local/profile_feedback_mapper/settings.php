<?php
defined('MOODLE_INTERNAL') || die();

$ADMIN->add(
    'localplugins',
    new admin_externalpage(
        'local_profile_feedback_mapper',
        get_string('pluginname', 'local_profile_feedback_mapper'),
        new moodle_url('/local/profile_feedback_mapper/index.php')
    )
);
