<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $PAGE, $OUTPUT, $CFG;

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/application_status_check/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_application_status_check'));
$PAGE->set_heading(get_string('checkstatus', 'local_application_status_check'));

require_once($CFG->dirroot . '/local/application_status_check/classes/form/status_form.php');
require_once($CFG->libdir . '/gradelib.php');

$PAGE->requires->css(new moodle_url('/local/application_status_check/styles.css'));

echo $OUTPUT->header();

// Debug: log incoming request method and params to help diagnose why the landing
// page is shown after submits. Remove these logs after debugging.
error_log('[ASC DEBUG] REQUEST_METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? ''));
error_log('[ASC DEBUG] GET=' . json_encode($_GET));
error_log('[ASC DEBUG] POST=' . json_encode($_POST));

$form = new \local_application_status_check\form\status_form(new moodle_url('/local/application_status_check/index.php', ['open' => 1]));

// Show a simple landing page with a single button unless the form is explicitly opened.
$open = optional_param('open', 0, PARAM_INT);
if ($open == 0 && $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo $OUTPUT->heading(get_string('pluginname', 'local_application_status_check'));
    echo html_writer::tag('p', get_string('landinginfo', 'local_application_status_check'));
    $url = new moodle_url('/local/application_status_check/index.php', ['open' => 1]);
    echo html_writer::link($url, get_string('checkstatus', 'local_application_status_check'), ['class' => 'btn btn-primary btn-lg']);
    echo $OUTPUT->footer();
    exit;
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/'));
} else if ($data = $form->get_data()) {
    // Log form data as JSON to keep it on a single line in logs.
    error_log('[ASC DEBUG] form->get_data returned (before merge): ' . json_encode($data));
    // Some themes or form render flows don't define conditionally-added elements
    // (like 'courseid' or the 'checkstatus' submit) so moodleform->get_data()
    // omits them even when they are POSTed. Merge key POST values into $data
    // so action detection and course id are preserved.
    $postkeys = array_keys($_POST);
    $mergekeys = ['courseid', 'checkstatus', 'getscheme'];
    foreach ($mergekeys as $mk) {
        if (empty($data->{$mk}) && array_key_exists($mk, $_POST)) {
            $data->{$mk} = $_POST[$mk];
        }
        // Also handle image-button coords like checkstatus.x / checkstatus_y
        if (empty($data->{$mk})) {
            foreach ($postkeys as $pk) {
                if (stripos($pk, $mk) !== false && array_key_exists($pk, $_POST)) {
                    $data->{$mk} = $_POST[$pk];
                    break;
                }
            }
        }
    }
    error_log('[ASC DEBUG] form->get_data returned (after merge): ' . json_encode($data));
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    // Fallback: sometimes moodleform->get_data() returns null (sesskey/session
    // edge cases). If POST contains our expected fields, use raw POST as data
    // for testing only.
    $postkeys = array_keys($_POST);
    $expected = ['email', 'getscheme', 'courseid', 'checkstatus', 'sesskey', 'open'];
    $hasexpected = count(array_intersect($postkeys, $expected)) > 0;
    if ($hasexpected || preg_grep('/checkstatus/i', $postkeys) || isset($_POST['submitbutton'])) {
        $data = new stdClass();
        // copy known keys if present
        foreach ($expected as $k) {
            if (isset($_POST[$k])) {
                $data->{$k} = $_POST[$k];
            }
        }
        // some themes/forms send the submit under 'submitbutton' or as image coords (checkstatus.x)
        if (empty($data->checkstatus)) {
            if (!empty($_POST['submitbutton'])) {
                $data->checkstatus = $_POST['submitbutton'];
            } else {
                foreach ($postkeys as $pk) {
                    if (stripos($pk, 'checkstatus') !== false) {
                        $data->checkstatus = $_POST[$pk];
                        break;
                    }
                }
            }
        }
        // ensure courseid is captured if present
        if (empty($data->courseid) && isset($_POST['courseid'])) {
            $data->courseid = $_POST['courseid'];
        }

        error_log('[ASC DEBUG] fallback using raw POST as $data: ' . print_r($data, true));
        error_log('[ASC DEBUG] POST keys: ' . implode(',', $postkeys));
    }
}

// If we have form data (from moodleform or our fallback), process actions.
if (!empty($data)) {
    $email = trim(core_text::strtolower($data->email ?? ''));
    $schemename = trim($data->course ?? '');
    $courseid = isset($data->courseid) ? (int)$data->courseid : 0;

    // 1) Find user by email.
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], '*', IGNORE_MISSING);
    if (!$user) {
        echo $OUTPUT->notification(get_string('usernotfound', 'local_application_status_check'), 'notifyproblem');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    // For now allow TEST MODE: skip strict DOB validation.
    $dobvalid = true;

    // Determine action: prefer explicit $data flags, fall back to POST key presence.
    $postkeys = array_keys($_POST);
    $action_getscheme = !empty($data->getscheme) || isset($_POST['getscheme']) || in_array('getscheme', $postkeys, true);
    $action_checkstatus = !empty($data->checkstatus) || isset($_POST['checkstatus']) || isset($_POST['submitbutton']) || (count(preg_grep('/checkstatus/i', $postkeys)) > 0);

    // Handle actions: 'getscheme' to detect enrolled course, 'checkstatus' to show grade status.
    require_once($CFG->dirroot . '/course/lib.php');
    if ($action_getscheme) {
        $enrolled = enrol_get_users_courses($user->id, true, 'id,fullname,shortname');
        if (empty($enrolled)) {
            echo $OUTPUT->notification(get_string('notenrolledany', 'local_application_status_check'), 'notifyproblem');
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }

        // Pick the highest id as heuristic if multiple courses found.
        $chosen = null;
        foreach ($enrolled as $c) {
            if ($chosen === null || $c->id > $chosen->id) {
                $chosen = $c;
            }
        }

        $label = trim(($chosen->shortname ? $chosen->shortname . ' - ' : '') . $chosen->fullname);
        $form = new \local_application_status_check\form\status_form(new moodle_url('/local/application_status_check/index.php', ['open' => 1]), ['detectedcourse' => ['id' => $chosen->id, 'label' => $label]]);
        $form->set_data(['email' => $email, 'courseid' => $chosen->id]);
        echo $OUTPUT->notification(get_string('schemenotice', 'local_application_status_check'), 'notifymessage');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    } else if ($action_checkstatus && $courseid > 0) {
        // Show application status from gradebook scale.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Display status above the form (same UX as scheme detection).
        $label = trim(($course->shortname ? $course->shortname . ' - ' : '') . $course->fullname);

        $sql = "SELECT gg.finalgrade AS scale_index, s.scale AS scales
                    FROM {grade_items} gi
                    JOIN {grade_categories} gc ON gc.id = gi.categoryid
                    JOIN {grade_grades} gg ON gg.itemid = gi.id
                    JOIN {scale} s ON s.id = gi.scaleid
                   WHERE gi.courseid = :courseid
                     AND gi.itemtype = 'manual'
                     AND gi.itemname = :itemname
                     AND gc.fullname = :catfullname
                     AND gg.userid = :userid
                 LIMIT 1";
        $params = [
            'courseid' => $course->id,
            'itemname' => 'Application Status',
            'catfullname' => 'Scholarship Status',
            'userid' => $user->id,
        ];
        $record = $DB->get_record_sql($sql, $params, IGNORE_MISSING);

        if (!$record || $record->scale_index === null || $record->scale_index === '') {
            echo $OUTPUT->notification(get_string('nostatusfound', 'local_application_status_check'), 'notifyproblem');
            // Re-render form with detected course so user can re-check or change.
            $form = new \local_application_status_check\form\status_form(new moodle_url('/local/application_status_check/index.php', ['open' => 1]), ['detectedcourse' => ['id' => $course->id, 'label' => $label]]);
            $form->set_data(['email' => $email, 'courseid' => $course->id]);
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }

        $scaleitems = array_map('trim', preg_split('/[\r\n,]+/', trim($record->scales)));
        $final = (int)$record->scale_index;
        $status = isset($scaleitems[$final - 1]) ? $scaleitems[$final - 1] : get_string('unknown', 'local_application_status_check');

        // Show the status as a notification (with custom class) and re-render the form with the detected course.
        $message = format_string(get_string('gradestatus', 'local_application_status_check') . ': ' . s($status));
        echo \html_writer::tag('div', $message, ['class' => 'application-status notifymessage']);
        $form = new \local_application_status_check\form\status_form(new moodle_url('/local/application_status_check/index.php', ['open' => 1]), ['detectedcourse' => ['id' => $course->id, 'label' => $label]]);
        $form->set_data(['email' => $email, 'courseid' => $course->id]);
        $form->display();
        echo $OUTPUT->footer();
        exit;
    } else {
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }
} else {
    // No form data at all; show the form.
    $form->display();
    echo $OUTPUT->footer();
    exit;
}


