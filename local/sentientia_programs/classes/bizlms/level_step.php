<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_program_levels -> local_sentientia_programs_levels (MAP).
 *
 * Grouped by program: a level's position is its rank among the levels of its program, and only a
 * whole program's levels can be ranked. BizLMS ordered and locked levels by id (program.php:1627,2076,
 * renderer.php:727) and overwrote `position` on edit (program.php:1388,1392), so the rank is by id, never by
 * position.
 *
 * Also folds two criteria tables into the row it writes: the program criteria decide whether the level counts
 * towards the program (completion_required), the level criteria decide whether one course or every course
 * completes it (completion_rule). The criteria rows themselves are settled by the two criteria steps.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class level_step extends base_step {

    public function key(): string {
        return 'program.level';
    }

    public function sourcetable(): string {
        return 'local_program_levels';
    }

    public function targettable(): string {
        return self::T_LEVELS;
    }

    public function group_by(): array {
        return ['programid'];
    }

    public function columns(): array {
        return ['programid', 'level', 'description', 'timecreated'];
    }

    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $programid = (int) $first->programid;
        $target = $ctx->map->resolve('local_program', $programid);
        if ($target === null) {
            // BizLMS deleted a program and left its levels behind (PR externallib.php:132-148).
            return $this->skip_all($rows, 'orphan_program');
        }

        $data = $this->data($ctx);
        $programcriteria = $data->program_criteria($programid);
        $skipempty = $ctx->decision('program.empty_levels') === 'skip';

        $out = [];
        $rank = 0;
        foreach ($rows as $row) {
            $legacyid = (int) $row->id;
            // Auto-create made seven levels per program (program.php:1408-1421); most stay empty. An empty level
            // that is kept would count as completed (mapping doc, "EMPTY LEVELS").
            $empty = !$data->level_course_ids($legacyid) && !$data->level_has_completion($legacyid);
            if ($empty && $skipempty) {
                $out[] = outcome::skip($legacyid, 'empty_level');
                continue;
            }

            $criteria = $data->level_criteria($programid, $legacyid);
            $result = outcome::insert($legacyid, self::T_LEVELS, (object) [
                'programid' => $target,
                'name' => $ctx->text->fit(trim((string) $row->level), 254, 'name'),
                'description' => $row->description,
                'sortorder' => $rank++,
                'completion_required' => rules::level_required($programcriteria, $legacyid),
                'completion_rule' => rules::level_rule($criteria),
                'timecreated' => (int) $row->timecreated,
            ]);
            if ($empty) {
                $result->warn('empty_level_kept');
            }
            $out[] = $result;
        }
        return $out;
    }
}
