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

        // If a detected course was provided via customdata, show the course and render the primary action.
        $detected = $this->_customdata['detectedcourse'] ?? null;
        if (is_array($detected) && !empty($detected['id'])) {
            $mform->addElement('html', \html_writer::tag('div', format_string($detected['label']), ['class' => 'detected-course']));
            $mform->addElement('hidden', 'courseid', (int)$detected['id']);
            $mform->setType('courseid', PARAM_INT);
            // Use an explicit named submit so controller can detect it reliably.
            $mform->addElement('submit', 'checkstatus', get_string('checkstatus', 'local_application_status_check'));
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
