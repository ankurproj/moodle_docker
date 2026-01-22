<?php
namespace local_application_status_check\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class status_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        $courses = $this->_customdata['courses'] ?? null; // array(id => label) when available.

        $mform->addElement('text', 'email', get_string('email', 'local_application_status_check'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('required'), 'required');

        $mform->addElement('date_selector', 'dob', get_string('dob', 'local_application_status_check'), ['optional' => false]);
        $mform->addRule('dob', get_string('required'), 'required');

        if (is_array($courses) && !empty($courses)) {
            $mform->addElement('autocomplete', 'courseid', get_string('course', 'local_application_status_check'), $courses, [
                'tags' => false,
                'multiple' => false,
                'noselectionstring' => get_string('choosedots'),
            ]);
            $mform->addRule('courseid', get_string('required'), 'required');
            // Freeze email and dob when we are selecting the course.
            $mform->freeze(['email', 'dob']);
        } else {
            // Initial step: allow free text course name to help find matches.
            $mform->addElement('text', 'course', get_string('course', 'local_application_status_check'));
            $mform->setType('course', PARAM_TEXT);
            $mform->addRule('course', get_string('required'), 'required');
        }

        $this->add_action_buttons(false, get_string('submit', 'local_application_status_check'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // Basic DOB sanity: timestamp expected from date_selector; skip format check.
        if (empty($data['dob'])) {
            $errors['dob'] = get_string('invaliddata', 'error');
        }
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
