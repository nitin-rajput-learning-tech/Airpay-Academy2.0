<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * Common ground of the program load steps: the cached source reads of legacy_data, and the two target tables
 * and their names.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_step extends step {

    /** Target: programs (a PRESERVE table, see program_step). */
    public const T_PROGRAMS = 'local_sentientia_programs';
    /** Target: levels. */
    public const T_LEVELS = 'local_sentientia_programs_levels';
    /** Target: courses of a level. */
    public const T_COURSES = 'local_sentientia_programs_courses';
    /** Target: enrolments. */
    public const T_USERS = 'local_sentientia_programs_users';
    /** Target: stored level completions. */
    public const T_LVLCOMP = 'local_sentientia_programs_lvlcomp';
    /** Target: program trainers. */
    public const T_TRAINERS = 'local_sentientia_programs_trainers';
    /** Target: feedback on program trainers. */
    public const T_TRAINERFB = 'local_sentientia_programs_trainerfb';

    /** @var legacy_data|null */
    private ?legacy_data $data = null;

    /** @var context|null The context the cached data was built for. */
    private ?context $datactx = null;

    /**
     * The extra source reads for this run. A new context (a dry run, then an apply) gets fresh data.
     *
     * @param context $ctx
     * @return legacy_data
     */
    protected function data(context $ctx): legacy_data {
        if ($this->data === null || $this->datactx !== $ctx) {
            $this->data = new legacy_data($ctx);
            $this->datactx = $ctx;
        }
        return $this->data;
    }

    /**
     * Skip every row of a group with one reason.
     *
     * @param \stdClass[] $rows
     * @param string $reason
     * @param string $detail
     * @return \local_sentientia_platform\bizlms\outcome[]
     */
    protected function skip_all(array $rows, string $reason, string $detail = ''): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = \local_sentientia_platform\bizlms\outcome::skip((int) $row->id, $reason, $detail);
        }
        return $out;
    }
}
