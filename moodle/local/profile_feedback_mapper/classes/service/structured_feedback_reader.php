<?php
namespace local_profile_feedback_mapper\service;

defined('MOODLE_INTERNAL') || die();

class structured_feedback_reader {

    /** @var string separator used in stored criterion references */
    protected const CRITERION_SEPARATOR = '::';

    /**
     * Get available criteria for a specific assignment.
     *
     * @param int $assignmentid
     * @return array
     */
    public function get_assignment_criteria_options(int $assignmentid): array {
        global $DB;

        if ($assignmentid <= 0) {
            return [];
        }

        $sql = "
            SELECT DISTINCT criterion
              FROM {assignfeedback_structured}
             WHERE assignment = :assignmentid
          ORDER BY criterion ASC
        ";

        $records = $DB->get_records_sql($sql, ['assignmentid' => $assignmentid]);
        if (!$records) {
            return [];
        }

        $indexes = [];
        foreach ($records as $record) {
            $indexes[] = (int)$record->criterion;
        }

        $labels = $this->get_known_criterion_labels($indexes);
        $options = [];

        foreach ($indexes as $index) {
            $label = $labels[$index] ?? $this->get_fallback_label($index);
            $options[$this->encode_criterion_reference($index, $label)] = $label;
        }

        return $options;
    }

    /**
     * Encode a criterion reference for storage.
     *
     * @param int $criterionindex
     * @param string $label
     * @return string
     */
    public function encode_criterion_reference(int $criterionindex, string $label): string {
        $cleanlabel = trim((string)preg_replace('/\s+/', ' ', $label));
        if ($cleanlabel === '') {
            $cleanlabel = $this->get_fallback_label($criterionindex);
        }

        return $criterionindex . self::CRITERION_SEPARATOR . $cleanlabel;
    }

    /**
     * Format a stored criterion reference for display.
     *
     * @param string $criterionreference
     * @return string
     */
    public function format_criterion_reference(string $criterionreference): string {
        [$criterionindex, $label] = $this->parse_criterion_reference($criterionreference);

        if ($criterionindex !== null) {
            return $label !== '' ? $label : $this->get_fallback_label($criterionindex);
        }

        $criterionreference = trim($criterionreference);
        return $criterionreference !== '' ? $criterionreference : 'Unknown criterion';
    }

    /**
     * Resolve a stored criterion reference to its numeric index.
     *
     * @param string $criterionname
     * @return int|null
     */
    public function get_criterion_index(string $criterionname): ?int {
        [$criterionindex] = $this->parse_criterion_reference($criterionname);
        if ($criterionindex !== null) {
            return $criterionindex;
        }

        global $DB;

        $criterionname = trim($criterionname);
        if ($criterionname === '') {
            return null;
        }

        $rows = $DB->get_records(
            'assignfeedback_structured_cs',
            null,
            '',
            'id, criteria'
        );

        if (!$rows) {
            return null;
        }

        $matches = [];

        foreach ($rows as $row) {
            if (empty($row->criteria)) {
                continue;
            }

            $criteria = json_decode($row->criteria, true);
            if (!is_array($criteria)) {
                continue;
            }

            foreach ($criteria as $index => $criterion) {
                if (($criterion['name'] ?? null) === $criterionname) {
                    $matches[(int)$index] = true;
                }
            }
        }

        if (count($matches) !== 1) {
            return null;
        }

        return (int)array_key_first($matches);
    }

    /**
     * Parse a stored criterion reference.
     *
     * @param string $criterionreference
     * @return array
     */
    protected function parse_criterion_reference(string $criterionreference): array {
        $criterionreference = trim($criterionreference);
        if ($criterionreference === '') {
            return [null, ''];
        }

        $parts = explode(self::CRITERION_SEPARATOR, $criterionreference, 2);
        if (count($parts) === 2 && ctype_digit($parts[0])) {
            return [(int)$parts[0], trim($parts[1])];
        }

        if (ctype_digit($criterionreference)) {
            return [(int)$criterionreference, ''];
        }

        return [null, $criterionreference];
    }

    /**
     * Get unique labels for known criterion indexes.
     *
     * @param array $indexes
     * @return array
     */
    protected function get_known_criterion_labels(array $indexes): array {
        global $DB;

        if (!$indexes) {
            return [];
        }

        $indexes = array_values(array_unique(array_map('intval', $indexes)));
        $rows = $DB->get_records(
            'assignfeedback_structured_cs',
            null,
            '',
            'id, criteria'
        );

        if (!$rows) {
            return [];
        }

        $candidates = [];
        foreach ($rows as $row) {
            if (empty($row->criteria)) {
                continue;
            }

            $criteria = json_decode($row->criteria, true);
            if (!is_array($criteria)) {
                continue;
            }

            foreach ($indexes as $index) {
                if (!isset($criteria[$index]['name'])) {
                    continue;
                }

                $label = trim((string)$criteria[$index]['name']);
                if ($label === '') {
                    continue;
                }

                if (!isset($candidates[$index])) {
                    $candidates[$index] = [];
                }

                $candidates[$index][$label] = true;
            }
        }

        $labels = [];
        foreach ($candidates as $index => $names) {
            if (count($names) === 1) {
                $labels[$index] = (string)array_key_first($names);
            }
        }

        return $labels;
    }

    /**
     * Get a safe fallback label for a criterion index.
     *
     * @param int $criterionindex
     * @return string
     */
    protected function get_fallback_label(int $criterionindex): string {
        return 'Criterion #' . $criterionindex;
    }

    /**
     * Get latest structured feedback values per user
     * for a given assignment + criterion name
     */
    public function get_latest_feedback(
        int $assignmentid,
        string $criterionname
    ): array {
        global $DB;

        $criterionindex = $this->get_criterion_index($criterionname);

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

    /**
     * Get the latest structured feedback value for a single user.
     *
     * @param int $assignmentid
     * @param string $criterionname
     * @param int $userid
     * @return string|null
     */
    public function get_latest_feedback_value(
        int $assignmentid,
        string $criterionname,
        int $userid
    ): ?string {
        global $DB;

        $criterionindex = $this->get_criterion_index($criterionname);

        if ($criterionindex === null) {
            return null;
        }

        $sql = "
            SELECT afs.commenttext AS value
              FROM {assignfeedback_structured} afs
              JOIN {assign_grades} g
                ON g.id = afs.grade
             WHERE afs.assignment = :assignmentid
               AND afs.criterion = :criterion
               AND g.userid = :userid
          ORDER BY afs.id DESC
        ";

        $record = $DB->get_record_sql($sql, [
            'assignmentid' => $assignmentid,
            'criterion' => $criterionindex,
            'userid' => $userid,
        ], IGNORE_MISSING);

        if (!$record) {
            return null;
        }

        return (string)$record->value;
    }
}
