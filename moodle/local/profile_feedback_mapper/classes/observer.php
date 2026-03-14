<?php
namespace local_profile_feedback_mapper;

use local_profile_feedback_mapper\service\mapping_synchronizer;
use mod_assign\event\submission_graded;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Sync mapped profile fields as soon as an assignment grade is saved.
     *
     * @param submission_graded $event
     * @return void
     */
    public static function submission_graded(submission_graded $event): void {
        global $DB;

        $grade = $DB->get_record('assign_grades', ['id' => $event->objectid], 'id, assignment, userid', IGNORE_MISSING);
        if (!$grade) {
            return;
        }

        $userid = (int)($event->relateduserid ?: $grade->userid);
        if ($userid <= 0) {
            return;
        }

        $synchronizer = new mapping_synchronizer();
        $synchronizer->sync_assignment_user((int)$grade->assignment, $userid);
    }
}