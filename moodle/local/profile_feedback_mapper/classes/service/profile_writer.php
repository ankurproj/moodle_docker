<?php
namespace local_profile_feedback_mapper\service;

defined('MOODLE_INTERNAL') || die();

class profile_writer {

    public function write_profile_value(int $userid, string $shortname, string $value): void {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
        if (!$field) {
            return;
        }

        $existing = $DB->get_record('user_info_data', [
            'userid' => $userid,
            'fieldid' => $field->id
        ]);

        if ($existing) {
            $existing->data = $value;
            $DB->update_record('user_info_data', $existing);
        } else {
            $rec = (object)[
                'userid' => $userid,
                'fieldid' => $field->id,
                'data' => $value,
                'dataformat' => 0
            ];
            $DB->insert_record('user_info_data', $rec);
        }
    }
}
