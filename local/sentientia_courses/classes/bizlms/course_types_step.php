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
 * local_course_types -> local_sentientia_course_type (PRESERVE).
 *
 * The ids are kept because rows the import does not rewrite store them: every course's
 * open_identifiedas is a comma list of type ids (BizLMS local/courses/lib.php:1272 joins on it).
 * So the target keeps the legacy id, an occupied id that is not an identical header copy blocks the
 * whole feature, and the sequence is reset after the commit by the runner.
 *
 * Column map (mapping doc, section 6):
 *  - name, shortname: copied (the shortname already had its spaces stripped when BizLMS saved it);
 *  - orgid: 0 means every tenant in BizLMS (coursestypes.php:70-71) and becomes no tenant path; N becomes the
 *    path of local_costcenter N, validated through the tenant resolver; an N that cannot be resolved is
 *    imported with no tenant path and counted as 'unresolved' (decision tenant.unresolved.course_lookups);
 *  - active: 1 enabled, 0 disabled (preflight blocks any other value);
 *  - ids 1 to 5 are the built-in types: protected = 1 (coursestypes.php:59-63 offers no edit or delete for them);
 *  - usercreated, usermodified, timecreated, timemodified: kept (a missing timestamp becomes 0, never now).
 *
 * Every row is imported: there is nothing in this table to skip, and a skipped row would break the id a course
 * points at.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_types_step extends step {

    /** Highest id of a built-in (protected) course type. */
    public const LAST_PROTECTED_ID = 5;

    public function key(): string {
        return course_lookups_importer::FEATURE . '.types';
    }

    public function sourcetable(): string {
        return 'local_course_types';
    }

    public function targettable(): string {
        return course_lookups_importer::TYPE_TABLE;
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    public function external_refs(): array {
        return [['course', 'open_identifiedas', 'comma list of type ids']];
    }

    public function transform(array $rows, context $ctx): array {
        $skipunresolved = $ctx->decision('tenant.unresolved.course_lookups') === 'skip';
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            [$path, $method] = lookup_tenant::of_costcenter($ctx, (int) ($row->orgid ?? 0), false);
            if ($method === 'unresolved' && $skipunresolved) {
                $out[] = outcome::skip($id, 'tenant_unresolved');
                continue;
            }
            $fields = (object) [
                'name' => $ctx->text->fit((string) ($row->name ?? ''), 255, 'name'),
                'shortname' => $ctx->text->fit((string) ($row->shortname ?? ''), 255, 'shortname'),
                'tenant_path' => $path,
                'active' => (int) ($row->active ?? 0) === 1 ? 1 : 0,
                'protected' => ($id >= 1 && $id <= self::LAST_PROTECTED_ID) ? 1 : 0,
                'usercreated' => (int) ($row->usercreated ?? 0),
                'usermodified' => (int) ($row->usermodified ?? 0),
                'timecreated' => (int) ($row->timecreated ?? 0),
                'timemodified' => (int) ($row->timemodified ?? 0),
            ];
            $o = outcome::insert($id, course_lookups_importer::TYPE_TABLE, $fields);
            if ($method !== null) {
                $o->tenant_method($method);
            }
            $out[] = $o;
        }
        return $out;
    }
}
