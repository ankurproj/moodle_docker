<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_profile_feedback_mapper\service\structured_feedback_reader;

admin_externalpage_setup('local_profile_feedback_mapper');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_title('Profile Feedback Mapper');
$PAGE->set_heading('Profile Feedback Mapper');

/* ---------------- PARAMS ---------------- */
$courseid     = optional_param('courseid', 0, PARAM_INT);
$assignmentid = optional_param('assignmentid', 0, PARAM_INT);
$deleteid     = optional_param('delete', 0, PARAM_INT);
$editid       = optional_param('edit', 0, PARAM_INT);
$save         = optional_param('save', false, PARAM_BOOL);

$editing = null;
if ($editid) {
    $editing = $DB->get_record('local_pf_fb_map', ['id' => $editid], '*', MUST_EXIST);
    $courseid = $editing->courseid;
    $assignmentid = $editing->assignmentid;
}

$reader = new structured_feedback_reader();

echo $OUTPUT->header();

/* ---------------- DELETE ---------------- */
if ($deleteid && confirm_sesskey()) {
    $DB->delete_records('local_pf_fb_map', ['id' => $deleteid]);
    echo $OUTPUT->notification('Mapping deleted', 'success');
}

/* ---------------- SAVE (ADD / UPDATE) ---------------- */
if ($save && confirm_sesskey()) {

    $id = optional_param('id', 0, PARAM_INT);

    $record = (object)[
        'id'             => $id,
        'courseid'       => required_param('courseid', PARAM_INT),
        'assignmentid'   => required_param('assignmentid', PARAM_INT),
        'feedback_field' => required_param('feedback_field', PARAM_TEXT),
        'profile_field'  => required_param('profile_field', PARAM_TEXT),
        'enabled'        => 1,
        'timemodified'   => time(),
    ];

    // Prevent duplicate mappings, including legacy name-only mappings.
    $exists = false;
    $targetcriterionindex = $reader->get_criterion_index($record->feedback_field);
    $existingmappings = $DB->get_records('local_pf_fb_map', [
        'courseid' => $record->courseid,
        'assignmentid' => $record->assignmentid,
        'profile_field' => $record->profile_field,
    ]);

    foreach ($existingmappings as $existingmapping) {
        if ($id && (int)$existingmapping->id === $id) {
            continue;
        }

        $existingcriterionindex = $reader->get_criterion_index((string)$existingmapping->feedback_field);
        if (
            $targetcriterionindex !== null &&
            $existingcriterionindex !== null &&
            $targetcriterionindex === $existingcriterionindex
        ) {
            $exists = true;
            break;
        }

        if ((string)$existingmapping->feedback_field === (string)$record->feedback_field) {
            $exists = true;
            break;
        }
    }

    if ($exists && !$id) {
        echo $OUTPUT->notification('This mapping already exists', 'error');
    } else {
        if ($id) {
            $DB->update_record('local_pf_fb_map', $record);
            echo $OUTPUT->notification('Mapping updated', 'success');
        } else {
            $record->timecreated = time();
            $DB->insert_record('local_pf_fb_map', $record);
            echo $OUTPUT->notification('Mapping created', 'success');
        }
    }
}

/* ---------------- DATA ---------------- */

// Courses
$courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname', 'id, fullname');
unset($courses[1]);

// Assignments
$assignments = $courseid
    ? $DB->get_records_menu('assign', ['course' => $courseid], 'name', 'id, name')
    : [];

// Structured feedback criteria
$criteria = [];
if ($assignmentid) {
    $criteria = $reader->get_assignment_criteria_options($assignmentid);

    $selectedcriterion = $editing->feedback_field ?? '';
    if ($selectedcriterion !== '' && !array_key_exists($selectedcriterion, $criteria)) {
        $criteria[$selectedcriterion] = $reader->format_criterion_reference($selectedcriterion);
    }
}

// Profile fields
$profiles = $DB->get_records_menu(
    'user_info_field',
    null,
    'name',
    'shortname, name'
);

/* ---------------- FORM ---------------- */

echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::input_hidden_params($PAGE->url);
echo html_writer::empty_tag('input', [
    'type'  => 'hidden',
    'name'  => 'sesskey',
    'value' => sesskey()
]);

if ($editing) {
    echo html_writer::empty_tag('input', [
        'type'  => 'hidden',
        'name'  => 'id',
        'value' => $editing->id
    ]);
}

echo html_writer::tag('h3', $editing ? 'Edit Mapping' : 'Add Mapping');

/* Course */
echo html_writer::tag('label', 'Course');
echo html_writer::select(
    $courses,
    'courseid',
    $editing->courseid ?? $courseid,
    ['' => 'Select course'],
    ['onchange' => 'this.form.submit()']
);
echo '<br><br>';

/* Assignment */
if ($courseid) {
    echo html_writer::tag('label', 'Assignment');
    echo html_writer::select(
        $assignments,
        'assignmentid',
        $editing->assignmentid ?? $assignmentid,
        ['' => 'Select assignment'],
        ['onchange' => 'this.form.submit()']
    );
    echo '<br><br>';
}

/* Criterion + Profile field */
if ($assignmentid) {

    if (!$criteria) {
        echo $OUTPUT->notification(
            'No structured feedback criteria have been saved for this assignment yet. Grade one submission first, then create the mapping.',
            'warning'
        );
    }

    echo html_writer::tag('label', 'Structured Feedback Criterion');
    echo html_writer::select(
        $criteria,
        'feedback_field',
        $editing->feedback_field ?? '',
        ['' => 'Select criterion']
    );
    echo '<br><br>';

    echo html_writer::tag('label', 'Profile Field');
    echo html_writer::select(
        $profiles,
        'profile_field',
        $editing->profile_field ?? '',
        ['' => 'Select profile field']
    );
    echo '<br><br>';

    if ($criteria) {
        echo html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => 'save',
            'value' => $editing ? 'Update Mapping' : 'Save Mapping'
        ]);
    }
}

echo html_writer::end_tag('form');

/* ---------------- LIST ---------------- */

echo html_writer::tag('h3', 'Existing Mappings');

$mappings = $DB->get_records('local_pf_fb_map');

$table = new html_table();
$table->head = ['Course', 'Assignment', 'Criterion', 'Profile Field', 'Actions'];

foreach ($mappings as $m) {

    $course = $DB->get_field('course', 'fullname', ['id' => $m->courseid]);
    $assignment = $DB->get_field('assign', 'name', ['id' => $m->assignmentid]);
    $profilelabel = $DB->get_field(
        'user_info_field',
        'name',
        ['shortname' => $m->profile_field]
    );

    $table->data[] = [
        format_string($course),
        format_string($assignment),
        s($reader->format_criterion_reference((string)$m->feedback_field)),
        format_string($profilelabel),
        html_writer::link(
            new moodle_url($PAGE->url, ['edit' => $m->id]),
            'Edit'
        ) . ' | ' .
        html_writer::link(
            new moodle_url($PAGE->url, ['delete' => $m->id, 'sesskey' => sesskey()]),
            'Delete'
        )
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
