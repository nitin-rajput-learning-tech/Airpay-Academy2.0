<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_course_levels -> local_sentientia_course_levels (PRESERVE).
 *
 * Course rows store the legacy level id (course.open_level) and the import does not rewrite the course table, so
 * the level keeps its id. The owner's level map (decision skills.level_proficiency) gives each level its skill
 * level; a level the map does not name stops the feature before any row is written (importer::preflight()), and
 * this step refuses too if it is ever run without that check.
 *
 * The target code column is UNIQUE and the legacy one was never unique (the form's duplicate check only reads
 * and reports, and its empty check writes to the wrong variable, so blank codes exist). The lowest id keeps its
 * code; an empty code becomes level_<id>; a repeat becomes <code>_<id>. Each change is a warning in the report.
 * The legacy table keeps the original.
 *
 * Legacy usercreated and usermodified are not copied: the table holds no personal data and the plugin's privacy
 * provider says so.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class levels_step extends step {

    /** @var array<int, array{0: string, 1: string}>|null Final code of every legacy level by id, with its warning code. */
    private ?array $codes = null;

    /** @var array<int, int>|null The owner's level map. */
    private ?array $proficiency = null;

    public function key(): string {
        return 'skills.levels';
    }

    public function sourcetable(): string {
        return 'local_course_levels';
    }

    public function targettable(): string {
        return 'local_sentientia_course_levels';
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    public function external_refs(): array {
        // course.open_level stores the legacy level id and is not rewritten by the import.
        return [['course', 'open_level']];
    }

    public function columns(): array {
        return ['id', 'name', 'code', 'open_path', 'sortorder', 'timecreated', 'timemodified', 'costcenterid'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;

            $proficiency = $this->proficiency($ctx)[$id] ?? null;
            if ($proficiency === null) {
                throw new blocked('level_proficiency_csv_incomplete:' . $id);
            }

            // A level with no tenant is imported pathless even when the owner chose to skip such catalogue rows:
            // course.open_level points at the id, and a level that is not there would orphan those courses.
            [$path, $method] = catalogue_tenant::resolve($ctx, $row->open_path ?? null, $row->costcenterid ?? null);

            $name = trim((string) $row->name);
            $warnings = [];
            if ($name === '') {
                $name = 'Level ' . $id;
                $warnings[] = 'derived_name';
            }
            $created = (int) $row->timecreated;
            $modified = (int) ($row->timemodified ?? 0);
            if ($modified <= 0) {
                $modified = $created;
                $warnings[] = 'derived_timestamp';
            }
            $code = $this->code($ctx, $id, $warnings);
            $sort = isset($row->sortorder) && $row->sortorder !== '' ? (int) $row->sortorder : null;

            $o = outcome::insert($id, $this->targettable(), (object) [
                'name' => $ctx->text->fit($name, 255, 'name'),
                'code' => $ctx->text->fit($code, 255, 'code'),
                'open_path' => $path,
                'proficiency' => $proficiency,
                'sortorder' => $sort,
                'timecreated' => $created,
                'timemodified' => $modified,
            ])->tenant_method($method);
            foreach ($warnings as $warning) {
                $o->warn($warning);
            }
            $out[] = $o;
        }
        return $out;
    }

    /**
     * The owner's level map, parsed once.
     *
     * @param context $ctx
     * @return array<int, int>
     */
    private function proficiency(context $ctx): array {
        if ($this->proficiency === null) {
            $this->proficiency = level_map::parse($ctx->decision('skills.level_proficiency'))['map'];
        }
        return $this->proficiency;
    }

    /**
     * A code as the unique index sees it: lower case (the collation also ignores accents and trailing spaces; the
     * codes are trimmed, and two codes that differ only by an accent stay a limit a rehearsal would show as a
     * failed batch, never a silent merge).
     *
     * @param string $code
     * @return string
     */
    private static function fold(string $code): string {
        return \core_text::strtolower($code);
    }

    /**
     * The code a level gets. The lowest id keeps a code; blank and repeated codes are made unique.
     *
     * @param context $ctx
     * @param int $id
     * @param string[] $warnings Collects code_generated or code_made_unique.
     * @return string
     */
    private function code(context $ctx, int $id, array &$warnings): string {
        if ($this->codes === null) {
            $this->codes = [];
            $used = [];
            $after = 0;
            do {
                $page = $ctx->legacy->page('local_course_levels', $after, 1000, ['id', 'code']);
                foreach ($page as $levelid => $level) {
                    $base = trim((string) $level->code);
                    $generated = $base === '';
                    $candidate = $generated ? 'level_' . $levelid : $base;
                    $changed = $generated;
                    // The column's unique index compares with the database collation, which ignores case.
                    while (isset($used[self::fold($candidate)])) {
                        $candidate .= '_' . $levelid;
                        $changed = true;
                    }
                    $used[self::fold($candidate)] = true;
                    $this->codes[(int) $levelid] = [$candidate, $generated ? 'code_generated' : ($changed ? 'code_made_unique' : '')];
                    $after = (int) $levelid;
                }
            } while (count($page) === 1000);
        }
        [$code, $warning] = $this->codes[$id] ?? ['level_' . $id, 'code_generated'];
        if ($warning !== '') {
            $warnings[] = $warning;
        }
        return $code;
    }
}
