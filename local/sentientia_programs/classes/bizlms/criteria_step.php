<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * The two BizLMS criteria tables, folded into rows the program and level steps already wrote.
 *
 *  - local_bc_completion_criteria (per program) became programs.completion_required and the levels'
 *    completion_required flags.
 *  - local_bcl_cmplt_criteria (per level) became levels.completion_rule and the courses' mandatory flags.
 *
 * Neither has a unique key in BizLMS, and BizLMS read the first row it found, so the row with the lowest id is
 * the one that counted: it is recorded as folded into the program or level it shaped, and any other row for the
 * same program or level is merged into it. A row whose program or level the import did not keep is skipped, with
 * orphan_program / orphan_level when BizLMS deleted the parent and parent_skipped when the import chose not to keep
 * it (the detail is the parent's own reason, walked up to the program's when the level was skipped only because its
 * program was). A level criteria row that names another program than its level
 * belongs to shaped nothing (the level step reads criteria by the level's own program) and is skipped as
 * criteria_program_mismatch, not recorded as folded.
 *
 * The step's own target table (the nominal one the registry checks) is not written: a fold writes nothing, it
 * records where the row went. The program table cannot be the nominal target of a MAP step, because the
 * program step owns it as a PRESERVE table.
 *
 * Known framework limit: a program criteria row folds into local_sentientia_programs at the PRESERVED legacy id.
 * In a dry run that row is not written, and runner::settle() FOLD demands that a positive fold target exists in the
 * database, so the dry run blocks with fold_target_missing. An apply is not affected (the program step has written
 * the row by then). The importer cannot work around it without running a different transform in a dry run and in an
 * apply; the runner has to accept a preserved id it has simulated (see the build report, framework needs).
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria_step extends base_step {

    /** @var bool True for the per-level criteria table, false for the per-program one. */
    private bool $perlevel;

    /**
     * @param bool $perlevel
     */
    public function __construct(bool $perlevel) {
        $this->perlevel = $perlevel;
    }

    public function key(): string {
        return $this->perlevel ? 'program.levelcriteria' : 'program.programcriteria';
    }

    public function sourcetable(): string {
        return $this->perlevel ? 'local_bcl_cmplt_criteria' : 'local_bc_completion_criteria';
    }

    public function targettable(): string {
        return $this->perlevel ? self::T_COURSES : self::T_LEVELS;
    }

    public function group_by(): array {
        return $this->perlevel ? ['programid', 'levelid'] : ['programid'];
    }

    public function columns(): array {
        return $this->perlevel ? ['programid', 'levelid'] : ['programid'];
    }

    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $programtarget = $ctx->map->resolve('local_program', (int) $first->programid);
        if ($programtarget === null) {
            // BizLMS deleted the program (orphan_program) or the import chose not to keep it (parent_skipped).
            [$reason, $detail] = $this->parent_gone($ctx, 'local_program', (int) $first->programid, 'orphan_program');
            return $this->skip_all($rows, $reason, $detail);
        }

        if ($this->perlevel) {
            $target = $ctx->map->resolve('local_program_levels', (int) $first->levelid);
            if ($target === null) {
                // BizLMS deleted the level and left the criteria behind (orphan_level), or the import chose not to
                // keep it, e.g. an empty level (parent_skipped, detail empty_level).
                [$reason, $detail] = $this->parent_gone($ctx, 'local_program_levels', (int) $first->levelid,
                    'orphan_level');
                return $this->skip_all($rows, $reason, $detail);
            }
            // The level step reads its criteria by (the level's own program, the level), as BizLMS did, so a row that
            // names another program than the level's shaped nothing and is not recorded as folded into it.
            if ($this->data($ctx)->level_program((int) $first->levelid) !== (int) $first->programid) {
                return $this->skip_all($rows, 'criteria_program_mismatch');
            }
            $table = self::T_LEVELS;
        } else {
            $target = $programtarget;
            $table = self::T_PROGRAMS;
        }

        $winner = array_shift($rows);
        $out = [outcome::fold((int) $winner->id, $table, $target, 'criteria_folded')];
        foreach ($rows as $row) {
            $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'dup_criteria');
        }
        return $out;
    }
}
