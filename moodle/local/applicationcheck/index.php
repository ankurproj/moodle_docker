<?php
require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB;

$PAGE->set_url(new moodle_url('/admin/tool/applicationcheck/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Application Check Tool');
$PAGE->set_heading('Application Check Tool');

echo $OUTPUT->header();

echo html_writer::tag('h2', 'Admin Tool Plugin Loaded');
echo html_writer::tag('p', 'This page is created using an admin tool plugin.');

echo $OUTPUT->footer();

// Include the form definition
require_once(
    $CFG->dirroot . '/admin/tool/applicationcheck/classes/form/application_form.php'
);

$form = new tool_applicationcheck_application_form();

// Handle form submission
if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/search.php'));
} else if ($data = $form->get_data()) {

    /*INSERT INTO MOODLE DB HERE */

    $record = new stdClass();
    $record->applicationid = $data->applicationid;
    $record->dob = $data->dob;
    $record->email = $data->email;
    $record->timecreated = time();

    $DB->insert_record('tool_applicationcheck_apps', $record);

    echo html_writer::tag('h3', 'Saved successfully');

    /* -------------------------------
       QUERY FROM MOODLE DB HERE
       ------------------------------- */

    $app = $DB->get_record(
        'tool_applicationcheck_apps',
        ['applicationid' => $data->applicationid],
        '*',
        IGNORE_MISSING
    );

    if ($app) {
        echo html_writer::tag('p', 'Application ID: ' . s($app->applicationid));
        echo html_writer::tag('p', 'DOB: ' . userdate($app->dob));
        echo html_writer::tag('p', 'Email: ' . s($app->email));
    }

} else {

    $form->display();
}

echo $OUTPUT->footer();