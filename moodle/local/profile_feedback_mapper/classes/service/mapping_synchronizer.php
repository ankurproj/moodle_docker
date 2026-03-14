<?php
namespace local_profile_feedback_mapper\service;

defined('MOODLE_INTERNAL') || die();

class mapping_synchronizer {

    /**
     * Sync all enabled mappings for all users.
     *
     * @return void
     */
    public function sync_all_enabled_mappings(): void {
        global $DB;

        $mappings = $DB->get_records('local_pf_fb_map', ['enabled' => 1]);
        if (!$mappings) {
            return;
        }

        foreach ($mappings as $mapping) {
            $this->sync_mapping($mapping);
        }
    }

    /**
     * Sync all enabled mappings for a single assignment and user.
     *
     * @param int $assignmentid
     * @param int $userid
     * @return void
     */
    public function sync_assignment_user(int $assignmentid, int $userid): void {
        global $DB;

        if ($assignmentid <= 0 || $userid <= 0) {
            return;
        }

        $mappings = $DB->get_records('local_pf_fb_map', [
            'assignmentid' => $assignmentid,
            'enabled' => 1,
        ]);

        if (!$mappings) {
            return;
        }

        foreach ($mappings as $mapping) {
            $this->sync_mapping_for_user($mapping, $userid);
        }
    }

    /**
     * Sync a mapping for all users with available feedback.
     *
     * @param \stdClass $mapping
     * @return void
     */
    protected function sync_mapping(\stdClass $mapping): void {
        $reader = new structured_feedback_reader();
        $writer = new profile_writer();

        $rows = $reader->get_latest_feedback(
            (int)$mapping->assignmentid,
            (string)$mapping->feedback_field
        );

        foreach ($rows as $row) {
            $writer->write_profile_value(
                (int)$row->userid,
                (string)$mapping->profile_field,
                trim(strip_tags((string)$row->value))
            );
        }
    }

    /**
     * Sync a mapping for one specific user.
     *
     * @param \stdClass $mapping
     * @param int $userid
     * @return void
     */
    protected function sync_mapping_for_user(\stdClass $mapping, int $userid): void {
        $reader = new structured_feedback_reader();
        $writer = new profile_writer();

        $value = $reader->get_latest_feedback_value(
            (int)$mapping->assignmentid,
            (string)$mapping->feedback_field,
            $userid
        );

        if ($value === null) {
            return;
        }

        $writer->write_profile_value(
            $userid,
            (string)$mapping->profile_field,
            trim(strip_tags($value))
        );
    }
}