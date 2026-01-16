<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class tool_applicationcheck_application_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'applicationid', 'Application ID');
        $mform->setType('applicationid', PARAM_TEXT);
        $mform->addRule('applicationid', null, 'required');

        $mform->addElement('date_selector', 'dob', 'Date of Birth');

        $mform->addElement('text', 'email', 'Email Address');
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', null, 'required');

        $this->add_action_buttons(true, 'Submit');
    }
}
