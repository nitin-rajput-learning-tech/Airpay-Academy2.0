<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_skill -> local_sentientia_skills (MAP).
 *
 * A legacy skill is never merged into one of the platform's seeded skills, whatever its name: the seed is an
 * invented list (decision skills.seed_rows = keep), and two skills with the same name are two rows here as they
 * were in BizLMS. The category comes from the categories step through the map; a skill whose category is gone
 * (BizLMS let an admin delete a category that still had skills) lands in the fallback category.
 *
 * The legacy shortname becomes idnumber and the description keeps its HTML. Draft-file images inside a
 * description were never reachable once BizLMS saved it; they are counted in the report (draft_file_images).
 * parentid, usercreated, usermodified and timemodified are not copied: the target has no such columns.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class skills_step extends step {

    /** @var int|null Lowest legacy category id: the row the fallback category hangs on. */
    private ?int $lowest = null;

    public function key(): string {
        return 'skills.skills';
    }

    public function sourcetable(): string {
        return 'local_skill';
    }

    public function targettable(): string {
        return 'local_sentientia_skills';
    }

    public function columns(): array {
        return ['id', 'category', 'name', 'shortname', 'description', 'open_path', 'timecreated', 'costcenterid'];
    }

    public function preload(): array {
        return [['local_skill_categories', ''], ['local_skill_categories', categories_step::FALLBACK_SUBKEY]];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $name = trim((string) $row->name);
            if ($name === '') {
                $out[] = outcome::skip($id, 'empty_name');
                continue;
            }

            $categoryid = $ctx->map->resolve('local_skill_categories', (int) $row->category);
            $fallback = false;
            if ($categoryid === null) {
                $lowest = $this->lowest_category($ctx);
                $categoryid = $lowest > 0
                    ? $ctx->map->resolve('local_skill_categories', $lowest, categories_step::FALLBACK_SUBKEY)
                    : null;
                $fallback = true;
            }
            if ($categoryid === null) {
                $out[] = outcome::skip($id, 'category_missing');
                continue;
            }

            [$path, $method, $skip] = catalogue_tenant::resolve($ctx, $row->open_path ?? null, $row->costcenterid ?? null);
            if ($skip) {
                $out[] = outcome::skip($id, 'tenant_unresolved');
                continue;
            }

            $shortname = trim((string) ($row->shortname ?? ''));
            $description = (string) ($row->description ?? '');
            $o = outcome::insert($id, $this->targettable(), (object) [
                'categoryid' => $categoryid,
                'name' => $ctx->text->fit($name, 255, 'name'),
                'description' => $description === '' ? null : $description,
                'max_level' => 5,
                'sort_order' => 0,
                'timecreated' => (int) $row->timecreated,
                'idnumber' => $shortname === '' ? null : $ctx->text->fit($shortname, 255, 'idnumber'),
                'open_path' => $path,
            ])->tenant_method($method);
            if ($fallback) {
                $o->warn('category_fallback');
            }
            if (strpos($description, '@@PLUGINFILE@@') !== false || strpos($description, '/draftfile.php/') !== false) {
                $o->warn('draft_file_images');
            }
            $out[] = $o;
        }
        return $out;
    }

    /**
     * @param context $ctx
     * @return int The lowest legacy category id, 0 when there is none.
     */
    private function lowest_category(context $ctx): int {
        if ($this->lowest === null) {
            $first = $ctx->legacy->page('local_skill_categories', 0, 1, ['id']);
            $this->lowest = $first ? (int) array_key_first($first) : 0;
        }
        return $this->lowest;
    }
}
