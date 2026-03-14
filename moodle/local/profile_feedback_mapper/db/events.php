<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\local_profile_feedback_mapper\observer::submission_graded',
    ],
];