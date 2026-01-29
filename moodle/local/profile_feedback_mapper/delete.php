<?php
require('../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$record = $DB->get_record('local_pf_fb_map', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url('/local/profile_feedback_mapper/delete.php', ['id' => $id]);
$PAGE->set_title('Delete Mapping');
$PAGE->set_heading('Delete Mapping');

if ($confirm && confirm_sesskey()) {
    $DB->delete_records('local_pf_fb_map', ['id' => $id]);
    redirect(
        new moodle_url('/local/profile_feedback_mapper/index.php'),
        get_string('deleted', 'local_profile_feedback_mapper')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('confirmdelete', 'local_profile_feedback_mapper'),
    new moodle_url('/local/profile_feedback_mapper/delete.php',
        ['id' => $id, 'confirm' => 1, 'sesskey' => sesskey()]
    ),
    new moodle_url('/local/profile_feedback_mapper/index.php')
);
echo $OUTPUT->footer();
