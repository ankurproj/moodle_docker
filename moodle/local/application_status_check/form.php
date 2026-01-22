<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_application_status_check_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'applicationid', get_string('applicationid', 'local_application_status_check'));
        $mform->setType('applicationid', PARAM_ALPHANUMEXT);
        $mform->addRule('applicationid', get_string('invalidinput', 'local_application_status_check'), 'required', null, 'client');

        $mform->addElement('date_selector', 'dob', get_string('dob', 'local_application_status_check'), ['optional' => false]);
        $mform->addRule('dob', get_string('invalidinput', 'local_application_status_check'), 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('email', 'local_application_status_check'));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('invalidinput', 'local_application_status_check'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('submit', 'local_application_status_check'));
    }
}
