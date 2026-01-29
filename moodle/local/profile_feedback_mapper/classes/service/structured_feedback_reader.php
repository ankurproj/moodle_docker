<?php
namespace local_profile_feedback_mapper\service;

defined('MOODLE_INTERNAL') || die();

class structured_feedback_reader {

    /**
     * Get latest structured feedback values per user
     * for a given assignment + criterion name
     */
    public function get_latest_feedback(
        int $assignmentid,
        string $criterionname
    ): array {
        global $DB;

        /* --------------------------------------------
         * 1. Resolve criterion index from criteria set
         * -------------------------------------------- */
        $cs = $DB->get_record(
            'assignfeedback_structured_cs',
            [],
            '*',
            IGNORE_MISSING
        );

        if (!$cs || empty($cs->criteria)) {
            return [];
        }

        $criteria = json_decode($cs->criteria, true);
        $criterionindex = null;

        foreach ($criteria as $index => $c) {
            if ($c['name'] === $criterionname) {
                $criterionindex = $index;
                break;
            }
        }

        if ($criterionindex === null) {
            return [];
        }

        /* --------------------------------------------
         * 2. Fetch ONLY the latest feedback per user
         * -------------------------------------------- */
        $sql = "
            SELECT
                afs.id,
                g.userid,
                afs.commenttext AS value
            FROM {assignfeedback_structured} afs
            JOIN {assign_grades} g
              ON g.id = afs.grade
            JOIN (
                SELECT grade, criterion, MAX(id) AS maxid
                FROM {assignfeedback_structured}
                WHERE assignment = :assignmentid
                GROUP BY grade, criterion
            ) latest
              ON latest.maxid = afs.id
            WHERE afs.assignment = :assignmentid2
              AND afs.criterion = :criterion
        ";

        return $DB->get_records_sql($sql, [
            'assignmentid'  => $assignmentid,
            'assignmentid2' => $assignmentid,
            'criterion'     => $criterionindex
        ]);
    }
}
