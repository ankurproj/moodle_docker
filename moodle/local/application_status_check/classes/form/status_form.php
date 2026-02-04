<?php
namespace local_application_status_check\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class status_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // We use auto-detect flow; `courses` array is not used.

        $mform->addElement('text', 'email', get_string('email', 'local_application_status_check'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('required'), 'required');

        // DOB removed: using email-only lookup. (DOB field intentionally omitted.)

        // Ensure form submissions include the 'open' flag so the landing page isn't shown.
        $mform->addElement('hidden', 'open', 1);
        $mform->setType('open', PARAM_INT);
        // Include sesskey to avoid Moodle session-related redirects.
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_RAW);

        // Initial step: don't ask for a course name. Provide a button to auto-detect courses
        // based on the provided email and dob.
        $mform->addElement('html', '<div class="form-group"><p>' . get_string('autodetectcourseinfo', 'local_application_status_check') . '</p></div>');
        $mform->addElement('submit', 'getscheme', get_string('getscheme', 'local_application_status_check'));

        // If detected course(s) are provided via customdata, render each as a row
        // with the course fullname and a right-aligned check button that submits
        // the course id as the button value (name="checkstatus", value="{id}").
        $detected = $this->_customdata['detectedcourses'] ?? $this->_customdata['detectedcourse'] ?? null;
        if (!empty($detected)) {
            // Normalize to array of courses
            $courses = [];
            if (is_array($detected) && array_key_exists('id', $detected)) {
                $courses[] = $detected;
            } else if (is_array($detected)) {
                $courses = $detected;
            }

            $html = '<div class="application-courses">';
            foreach ($courses as $c) {
                $label = format_string($c['label'] ?? ($c['fullname'] ?? ''));
                $id = (int)($c['id'] ?? 0);
                // Each button will submit the parent form with name 'checkstatus' and value set to course id.
                $html .= '<div class="detected-course-row" style="display:flex;align-items:center;justify-content:space-between">';
                $html .= '<div class="detected-course">' . $label . '</div>';
                $html .= '<button type="submit" name="checkstatus" value="' . $id . '" class="btn btn-primary check-status-btn">' . get_string('checkstatus', 'local_application_status_check') . '</button>';
                $html .= '</div>';
            }
            $html .= '</div>';

            $mform->addElement('html', $html);
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // No DOB validation required (field removed).
        if (!empty($data['courseid'])) {
            if (!is_numeric($data['courseid'])) {
                $errors['courseid'] = get_string('invaliddata', 'error');
            }
        } else if (!empty($data['course'])) {
            // Accept any text; matching happens server-side.
        }
        return $errors;
    }
}
