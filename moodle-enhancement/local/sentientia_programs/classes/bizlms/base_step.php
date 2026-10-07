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

    /** Reason: the row's parent is in the source and the import chose not to keep it (its own reason is the detail). */
    public const PARENT_SKIPPED = 'parent_skipped';

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
     * Why a row's parent has no target row.
     *
     * Two different things look alike from the child: BizLMS deleted the parent and left its children behind (the
     * parent is not in the source, so the map has no entry for it: the orphan reason), or the import read the parent
     * and deliberately did not keep it (an empty level, a program with no name, a program no tenant could be found
     * for). The second is not an orphan, and reporting it as one sends the owner looking for deleted data that is
     * not deleted. It is reported as parent_skipped, with the parent's own reason as the detail. When the parent
     * is a level the import skipped only because ITS program was not kept (the level's own reason is parent_skipped
     * too), the detail walks up to the program's reason, so a row two steps down the tree still names the root cause
     * (no_name, tenant_unresolved) instead of repeating the code parent_skipped.
     *
     * @param context $ctx
     * @param string $parenttable Legacy table of the parent, e.g. local_program_levels.
     * @param int $parentid Legacy id of the parent.
     * @param string $orphan The reason to use when the parent is not in the map at all.
     * @return array{0: string, 1: string} [reason, detail]
     */
    protected function parent_gone(context $ctx, string $parenttable, int $parentid, string $orphan): array {
        $entry = $ctx->map->entry($parenttable, $parentid);
        if ($entry === null) {
            return [$orphan, ''];
        }
        $why = (string) ($entry['reason'] ?? '');
        if ($why === self::PARENT_SKIPPED && $parenttable === 'local_program_levels') {
            // The level was skipped because its own program was not kept: name that program's reason.
            $programid = $this->data($ctx)->level_program($parentid);
            $programentry = $programid === null ? null : $ctx->map->entry('local_program', $programid);
            $why = (string) ($programentry['reason'] ?? '');
        }
        // A detail is codes only (outcome::skip refuses anything else).
        return [self::PARENT_SKIPPED, preg_match('/^[a-z][a-z0-9_]{0,63}$/', $why) ? $why : ''];
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
