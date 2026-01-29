<?php
namespace local_profile_feedback_mapper\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class mapping_form extends moodleform {

    public function definition() {
        global $DB;

        $mform = $this->_form;

        /* =========================
         * Course dropdown
         * ========================= */
        $courses = $DB->get_records_menu('course', ['visible' => 1], 'fullname', 'id, fullname');
        unset($courses[1]); // remove site home

        $mform->addElement('select', 'courseid', get_string('course'), $courses);
        $mform->setType('courseid', PARAM_INT);
        $mform->addRule('courseid', null, 'required');

        /* =========================
         * Assignment dropdown
         * (passed from edit.php)
         * ========================= */
        $assignments = $this->_customdata['assignments'] ?? [];

        $mform->addElement(
            'select',
            'assignmentid',
            get_string('assignment', 'assign'),
            $assignments
        );
        $mform->setType('assignmentid', PARAM_INT);
        $mform->addRule('assignmentid', null, 'required');

        /* =========================
         * Structured feedback criteria
         * ========================= */
        $criteriaoptions = [];
        $cs = $DB->get_record('assignfeedback_structured_cs', [], '*', IGNORE_MISSING);
        if ($cs && $cs->criteria) {
            $criteria = json_decode($cs->criteria, true);
            foreach ($criteria as $c) {
                $criteriaoptions[$c['name']] = $c['name'];
            }
        }

        $mform->addElement(
            'select',
            'feedback_field',
            get_string('feedbackfield', 'local_profile_feedback_mapper'),
            $criteriaoptions
        );
        $mform->setType('feedback_field', PARAM_TEXT);
        $mform->addRule('feedback_field', null, 'required');

        /* =========================
         * User profile fields
         * ========================= */
        $profilefields = $DB->get_records_menu(
            'user_info_field',
            null,
            'name',
            'shortname, name'
        );

        $mform->addElement(
            'select',
            'profile_field',
            get_string('profilefield', 'local_profile_feedback_mapper'),
            $profilefields
        );
        $mform->setType('profile_field', PARAM_TEXT);
        $mform->addRule('profile_field', null, 'required');

        /* =========================
         * Enabled
         * ========================= */
        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('enabled', 'local_profile_feedback_mapper')
        );

        /* =========================
         * Hidden ID (edit mode)
         * ========================= */
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        /* =========================
         * Auto-submit on course change
         * ========================= */
        $mform->addElement(
            'html',
            '<script>
                document.addEventListener("DOMContentLoaded", function () {
                    const course = document.getElementById("id_courseid");
                    if (course) {
                        course.onchange = function () {
                            this.form.submit();
                        };
                    }
                });
            </script>'
        );

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
