<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * Finds the one live account an employee id names (ADR-032, mapping doc section 10, transcript).
 *
 * The BizLMS transcript was loaded from a spreadsheet keyed by employee id; its userid is often empty. A row is
 * given a user only when the employee id names EXACTLY ONE non-deleted account: first by open_employeeid, then
 * (only when no account has that open_employeeid) by idnumber. No account, or more than one, gives 0: the row
 * stays in the table as an off-platform record and nothing guesses between two people.
 *
 * One pass over {user}, paged by id, building two maps of lower-cased trimmed values; no query per row.
 * Built once per context. The framework's lookups class holds ids and org paths, not employee ids, so this is
 * the step's own read of {user}.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_identity_index {

    /** Users per read. */
    private const PAGE = 5000;

    /** @var \WeakMap<context, self>|null */
    private static ?\WeakMap $memo = null;

    /** @var array<string, int[]> lower-cased open_employeeid => non-deleted user ids. */
    private array $byemployee = [];

    /** @var array<string, int[]> lower-cased idnumber => non-deleted user ids. */
    private array $byidnumber = [];

    /**
     * @param context $ctx
     * @return self
     */
    public static function for_context(context $ctx): self {
        self::$memo ??= new \WeakMap();
        if (!isset(self::$memo[$ctx])) {
            self::$memo[$ctx] = new self();
        }
        return self::$memo[$ctx];
    }

    /**
     * Build the maps.
     */
    private function __construct() {
        global $DB;
        $columns = $DB->get_columns('user');
        $hasemployee = array_key_exists('open_employeeid', $columns);
        $select = 'id, idnumber' . ($hasemployee ? ', open_employeeid' : '');
        $after = 0;
        do {
            $rows = $DB->get_records_sql(
                "SELECT {$select} FROM {user} WHERE deleted = 0 AND id > :after ORDER BY id",
                ['after' => $after], 0, self::PAGE);
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $this->add($this->byidnumber, (string) $row->idnumber, $after);
                if ($hasemployee) {
                    $this->add($this->byemployee, (string) $row->open_employeeid, $after);
                }
            }
        } while (count($rows) === self::PAGE);
    }

    /**
     * The user an employee id names.
     *
     * @param string|null $employeeid
     * @return int User id; 0 when no live account has the id or more than one does.
     */
    public function resolve(?string $employeeid): int {
        $key = self::key($employeeid);
        if ($key === '') {
            return 0;
        }
        if (isset($this->byemployee[$key])) {
            // An employee id that more than one account holds is ambiguous: stop, do not fall through.
            return count($this->byemployee[$key]) === 1 ? $this->byemployee[$key][0] : 0;
        }
        if (isset($this->byidnumber[$key])) {
            return count($this->byidnumber[$key]) === 1 ? $this->byidnumber[$key][0] : 0;
        }
        return 0;
    }

    /**
     * @param array<string, int[]> $map
     * @param string $value
     * @param int $userid
     * @return void
     */
    private function add(array &$map, string $value, int $userid): void {
        $key = self::key($value);
        if ($key !== '') {
            $map[$key][] = $userid;
        }
    }

    /**
     * @param string|null $value
     * @return string
     */
    private static function key(?string $value): string {
        return \core_text::strtolower(trim((string) $value));
    }
}
