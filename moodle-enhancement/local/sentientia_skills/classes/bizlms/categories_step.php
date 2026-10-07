<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\provenance;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_skill_categories -> local_sentientia_skill_cats (MAP).
 *
 * - A legacy category whose name equals (ignoring case and surrounding spaces) the name of a category the import
 *   did not create (the platform seed, or one an admin made) folds into it and is not imported again. This is
 *   the owner's merge policy (decision skills.merge_categories = exact_name); with "none" every category is
 *   imported as its own row. Skills are never merged: that is the skills step.
 * - The legacy shortname becomes idnumber (globally unique in BizLMS, so it is safe to keep); the char sortorder
 *   becomes an integer; parentid, depth and path were never written by BizLMS and are not copied (a parentid
 *   above zero is reported).
 * - BizLMS let an admin delete a category that still had skills, so some skills point at none. One extra
 *   category, "Imported - uncategorised", is created for them (sub-key fallback of the lowest legacy category id)
 *   only when such skills exist.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class categories_step extends step {

    /** Name of the category orphan skills land in. */
    public const FALLBACK_NAME = 'Imported - uncategorised';

    /** Sub-key of the fallback category row. */
    public const FALLBACK_SUBKEY = 'fallback';

    /** @var array<string, int>|null Seed category id by lower-case name (lowest id wins). */
    private ?array $seeds = null;

    /** @var int|null Lowest legacy category id: the row that carries the fallback sub-row. */
    private ?int $lowest = null;

    /** @var bool|null Do skills exist whose category is not a legacy category? */
    private ?bool $orphans = null;

    public function key(): string {
        return 'skills.categories';
    }

    public function sourcetable(): string {
        return 'local_skill_categories';
    }

    public function targettable(): string {
        return 'local_sentientia_skill_cats';
    }

    public function columns(): array {
        return ['id', 'name', 'shortname', 'parentid', 'sortorder', 'open_path', 'timecreated', 'costcenterid'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $name = trim((string) $row->name);
            $created = (int) $row->timecreated;

            $primary = $this->primary($row, $id, $name, $created, $ctx);
            if ($primary->kind !== outcome::SKIP && (int) ($row->parentid ?? 0) > 0) {
                $primary->warn('parent_ignored');
            }
            $out[] = $primary;

            // The fallback category rides on the lowest legacy category, whatever became of that row itself.
            if ($id === $this->lowest_id($ctx) && $this->has_orphan_skills($ctx)) {
                $out[] = outcome::insert($id, $this->targettable(), (object) [
                    'name' => self::FALLBACK_NAME,
                    'description' => '',
                    'icon' => 'fa-cogs',
                    'color' => '#0066A7',
                    'sort_order' => 9999,
                    'timecreated' => $created,
                    'idnumber' => null,
                    'open_path' => null,
                ], self::FALLBACK_SUBKEY);
            }
        }
        return $out;
    }

    /**
     * The outcome of the category row itself.
     *
     * @param \stdClass $row
     * @param int $id
     * @param string $name
     * @param int $created
     * @param context $ctx
     * @return outcome
     */
    private function primary(\stdClass $row, int $id, string $name, int $created, context $ctx): outcome {
        if ($name === '') {
            return outcome::skip($id, 'empty_name');
        }

        if ($ctx->decision('skills.merge_categories') === 'exact_name') {
            $seed = $this->seeds($ctx)[\core_text::strtolower($name)] ?? null;
            if ($seed !== null) {
                return outcome::fold($id, $this->targettable(), $seed, 'seed_category_match');
            }
        }

        [$path, $method, $skip] = catalogue_tenant::resolve($ctx, $row->open_path ?? null, $row->costcenterid ?? null);
        if ($skip) {
            return outcome::skip($id, 'tenant_unresolved');
        }

        $shortname = trim((string) ($row->shortname ?? ''));
        $sortraw = trim((string) ($row->sortorder ?? ''));
        $sort = preg_match('/^-?[0-9]+$/', $sortraw) ? max(0, min(32767, (int) $sortraw)) : 0;

        $o = outcome::insert($id, $this->targettable(), (object) [
            'name' => $ctx->text->fit($name, 255, 'name'),
            'description' => '',
            'icon' => 'fa-cogs',
            'color' => '#0066A7',
            'sort_order' => $sort,
            'timecreated' => $created,
            'idnumber' => $shortname === '' ? null : $ctx->text->fit($shortname, 255, 'idnumber'),
            'open_path' => $path,
        ])->tenant_method($method);
        if ($sortraw !== '' && !preg_match('/^-?[0-9]+$/', $sortraw)) {
            $o->warn('sortorder_not_numeric');
        }
        return $o;
    }

    /**
     * Categories the import did not create, by lower-case name. Read once; the import's own rows are left out
     * with the provenance guard, so a category this run wrote earlier never counts as a seed.
     *
     * @param context $ctx
     * @return array<string, int>
     */
    private function seeds(context $ctx): array {
        if ($this->seeds === null) {
            $this->seeds = [];
            if ($ctx->legacy->exists($this->targettable())) {
                $filter = provenance::not_imported_sql('t', $this->targettable(), 'blmsc');
                $after = 0;
                do {
                    $page = $ctx->legacy->page($this->targettable(), $after, 2000, ['id', 'name'], $filter);
                    foreach ($page as $id => $seed) {
                        $key = \core_text::strtolower(trim((string) $seed->name));
                        if ($key !== '' && !isset($this->seeds[$key])) {
                            $this->seeds[$key] = (int) $id;
                        }
                        $after = (int) $id;
                    }
                } while (count($page) === 2000);
            }
        }
        return $this->seeds;
    }

    /**
     * @param context $ctx
     * @return int The lowest legacy category id (0 when there is none).
     */
    private function lowest_id(context $ctx): int {
        if ($this->lowest === null) {
            $first = $ctx->legacy->page('local_skill_categories', 0, 1, ['id']);
            $this->lowest = $first ? (int) array_key_first($first) : 0;
        }
        return $this->lowest;
    }

    /**
     * @param context $ctx
     * @return bool Some skill points at a category that does not exist, or at one with no name (which this step
     *         skips), so it has no category to land in.
     */
    private function has_orphan_skills(context $ctx): bool {
        if ($this->orphans === null) {
            $this->orphans = $ctx->legacy->exists('local_skill') && $ctx->legacy->count('local_skill', [
                "t.category NOT IN (SELECT c.id FROM {local_skill_categories} c WHERE c.name IS NOT NULL AND TRIM(c.name) <> '')",
                [],
            ]) > 0;
        }
        return $this->orphans;
    }
}
