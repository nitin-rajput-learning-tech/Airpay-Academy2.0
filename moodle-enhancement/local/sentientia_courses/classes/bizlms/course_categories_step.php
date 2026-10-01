<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_custom_category -> local_sentientia_course_category (PRESERVE).
 *
 * The ids are kept because rows the import does not rewrite store them: course.open_categoryid
 * (BizLMS local/courses/classes/form/custom_course_form.php:134-153). An occupied id that is not an
 * identical header copy blocks the whole feature.
 *
 * Column map (mapping doc, section 6, with its verification correction applied):
 *  - fullname, shortname: copied (a missing name becomes an empty string, so the id a course points at
 *    still resolves);
 *  - parentid: 0 or missing is a top-level category. A parent is resolved through the legacy map. The map
 *    cannot know a parent with a higher id than its child yet, because rows are written in id order; the
 *    parent is then taken from the source table, which is enough because a PRESERVE step keeps the id. A
 *    parent that exists nowhere is kept as it is and reported as orphan_parent: the category stays
 *    unreachable from the top, exactly as it was in BizLMS;
 *  - costcenterid: resolved to the ROOT of the cost centre's path ('/1'), through the tenant resolver. A
 *    missing costcenterid has no tenant (BizLMS set it from the form or the creator's open_path, and a site
 *    admin's category carried none). One that cannot be resolved to a registered tenant is imported with no
 *    tenant path and counted as 'unresolved' (decision tenant.unresolved.course_lookups): visible to
 *    cross-tenant callers only, never to a tenant;
 *  - path: the category-tree path of ids ('/3/12'), copied as it is. It is NOT an org path;
 *  - depth, usercreated, usermodified, timecreated, timemodified: kept.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_categories_step extends step {

    public function key(): string {
        return course_lookups_importer::FEATURE . '.categories';
    }

    public function sourcetable(): string {
        return 'local_custom_category';
    }

    public function targettable(): string {
        return course_lookups_importer::CATEGORY_TABLE;
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    public function external_refs(): array {
        return [['course', 'open_categoryid', 'custom category id']];
    }

    public function adopt_signature(): array {
        // The header copy has the source's id, name and creation time, as every adoptable copy does.
        return ['fullname', 'timecreated'];
    }

    public function transform(array $rows, context $ctx): array {
        $skipunresolved = $ctx->decision('tenant.unresolved.course_lookups') === 'skip';
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            [$path, $method] = lookup_tenant::of_costcenter($ctx, (int) ($row->costcenterid ?? 0), true);
            if ($method === 'unresolved' && $skipunresolved) {
                $out[] = outcome::skip($id, 'tenant_unresolved');
                continue;
            }
            [$parentid, $orphan] = $this->parent_of($row, $ctx);
            $fields = (object) [
                'fullname' => $ctx->text->fit((string) ($row->fullname ?? ''), 255, 'fullname'),
                'shortname' => $ctx->text->fit((string) ($row->shortname ?? ''), 255, 'shortname'),
                'parentid' => $parentid,
                'path' => $ctx->text->fit((string) ($row->path ?? ''), 512, 'path'),
                'depth' => max(0, (int) ($row->depth ?? 0)),
                'tenant_path' => $path,
                'usercreated' => (int) ($row->usercreated ?? 0),
                'usermodified' => (int) ($row->usermodified ?? 0),
                'timecreated' => (int) ($row->timecreated ?? 0),
                'timemodified' => (int) ($row->timemodified ?? 0),
            ];
            $o = outcome::insert($id, course_lookups_importer::CATEGORY_TABLE, $fields);
            if ($method !== null) {
                $o->tenant_method($method);
            }
            if ($orphan) {
                $o->warn('orphan_parent');
            }
            $out[] = $o;
        }
        return $out;
    }

    /**
     * The parent a category will point at.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @return array{0: int, 1: bool} [parent id, true when the parent exists nowhere]
     */
    private function parent_of(\stdClass $row, context $ctx): array {
        $legacyparent = (int) ($row->parentid ?? 0);
        if ($legacyparent <= 0) {
            return [0, false];
        }
        $resolved = $ctx->map->resolve($this->sourcetable(), $legacyparent);
        if ($resolved !== null && $resolved > 0) {
            return [$resolved, false];
        }
        // Not mapped yet: a parent with a higher id is written after this row, and a PRESERVE step keeps ids.
        $found = $ctx->legacy->fetch($this->sourcetable(), [$legacyparent], ['id']);
        return [$legacyparent, !isset($found[$legacyparent])];
    }
}
