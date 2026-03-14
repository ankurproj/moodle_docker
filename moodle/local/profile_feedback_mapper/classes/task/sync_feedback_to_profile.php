<?php
namespace local_profile_feedback_mapper\task;

use core\task\scheduled_task;
use local_profile_feedback_mapper\service\mapping_synchronizer;

defined('MOODLE_INTERNAL') || die();

class sync_feedback_to_profile extends scheduled_task {

    public function get_name() {
        return 'Sync structured feedback to user profile fields';
    }

    public function execute() {
        $synchronizer = new mapping_synchronizer();
        $synchronizer->sync_all_enabled_mappings();
    }
}
