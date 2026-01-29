<?php
namespace local_profile_feedback_mapper\task;

use core\task\scheduled_task;
use local_profile_feedback_mapper\service\structured_feedback_reader;
use local_profile_feedback_mapper\service\profile_writer;

defined('MOODLE_INTERNAL') || die();

class sync_feedback_to_profile extends scheduled_task {

    public function get_name() {
        return 'Sync structured feedback to user profile fields';
    }

    public function execute() {
        global $DB;

        $mappings = $DB->get_records(
            'local_pf_fb_map',
            ['enabled' => 1]
        );

        if (!$mappings) {
            return;
        }

        $reader = new structured_feedback_reader();
        $writer = new profile_writer();

        foreach ($mappings as $map) {

            $rows = $reader->get_latest_feedback(
                (int)$map->assignmentid,
                (string)$map->feedback_field
            );

            foreach ($rows as $row) {
                $writer->write_profile_value(
                    (int)$row->userid,
                    (string)$map->profile_field,
                    (string)trim(strip_tags($row->value))
                );
            }
        }
    }
}
