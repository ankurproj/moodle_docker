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

echo $OUTPUT->header();

$form = new \local_application_status_check\form\status_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/'));
} else if ($data = $form->get_data()) {
    $email = trim(core_text::strtolower($data->email ?? ''));
        $dobts = (int)($data->dob ?? 0);
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

        // 2) Validate DOB using custom profile field 'dob'.
        $dobvalid = false;
        $dobfield = $DB->get_record('user_info_field', ['shortname' => 'dob'], '*', IGNORE_MISSING);
        if ($dobfield) {
            $dobdata = $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $dobfield->id], '*', IGNORE_MISSING);
            if ($dobdata && !empty($dobdata->data)) {
                $normalize = function(string $datestr): ?string {
                    $datestr = trim($datestr);
                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datestr, $m)) {
                        if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
                        }
                    }
                    if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $datestr, $m)) {
                        if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
                        }
                    }
                    return null;
                };
                $inputymd = userdate($dobts, '%Y-%m-%d');
                $norminput = $normalize($inputymd);
                $normstored = $normalize($dobdata->data);
                if ($norminput !== null && $normstored !== null && $norminput === $normstored) {
                    $dobvalid = true;
                }
            }
        }
        if (!$dobvalid) {
            echo $OUTPUT->notification(get_string('dobmismatch', 'local_application_status_check'), 'notifyproblem');
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }

        // 3) If no course selected yet, build enrolled course list and re-display form.
        if ($courseid === 0) {
            require_once($CFG->dirroot . '/course/lib.php');
            $courses = enrol_get_users_courses($user->id, true, 'id,fullname,shortname');
            $options = [];
            foreach ($courses as $c) {
                $label = trim(($c->shortname ? $c->shortname . ' - ' : '') . $c->fullname);
                $options[$c->id] = format_string($label);
            }

            $form = new \local_application_status_check\form\status_form(null, ['courses' => $options]);
            $form->set_data(['email' => $email, 'dob' => $dobts]);
            echo $OUTPUT->notification(get_string('schemenotice', 'local_application_status_check'), 'notifymessage');
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }

        // 4) Validate enrolment and display read-only grade status.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = context_course::instance($course->id);
        if (!is_enrolled($coursecontext, $user)) {
            echo $OUTPUT->notification('Not enrolled in this course.', 'notifyproblem');
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }

        $coursegrade = grade_get_course_grade($user->id, $course->id);

        echo html_writer::tag('h3', get_string('statusresult', 'local_application_status_check'));
        $items = [];
        $items[] = format_string(get_string('email', 'local_application_status_check') . ': ' . s($email));
        $items[] = format_string(get_string('dob', 'local_application_status_check') . ': ' . userdate($dobts));
        $items[] = format_string(get_string('course', 'local_application_status_check') . ': ' . format_string($course->shortname ?: $course->fullname));

        if (!empty($coursegrade) && isset($coursegrade->grade)) {
            $statuslabel = !is_null($coursegrade->grade) ? get_string('gradestatus', 'local_application_status_check') . ': Found' : get_string('gradestatus', 'local_application_status_check') . ': Pending';
            $items[] = format_string($statuslabel);
            if (!is_null($coursegrade->grade)) {
                $items[] = format_string(get_string('finalgrade', 'local_application_status_check') . ': ' . format_float($coursegrade->grade, 2));
            }
        } else {
            $items[] = format_string(get_string('nogradedata', 'local_application_status_check'));
        }
        echo html_writer::alist($items);
        echo $OUTPUT->footer();
        exit;
    } else {
        $form->display();
        echo $OUTPUT->footer();
    }