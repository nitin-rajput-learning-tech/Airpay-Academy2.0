<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * local_learningplan to local_sentientia_learningpath (mapping doc, section 17).
 *
 * PRESERVE: the plan id is the path id. Certificates (tool_certificate_issues.moduleid), rating rows and
 * request rows that BizLMS wrote name a plan by that id and the import does not rewrite them.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class path_step extends step {

    public function key(): string {
        return 'learningplan.path';
    }

    public function sourcetable(): string {
        return importer::SRC_PLAN;
    }

    public function targettable(): string {
        return importer::PATHS;
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * Rows the import does not rewrite that hold a plan id.
     *
     * @return array<array{0: string, 1: string, 2?: string}>
     */
    public function external_refs(): array {
        return [
            ['tool_certificate_issues', 'moduleid', "moduletype = 'learningplan'"],
            ['local_rating', 'moduleid', "ratearea = 'local_learningplan'"],
            ['local_ratings_likes', 'module_id', "module_area = 'local_learningplan'"],
            ['local_request_records', 'componentid', "compname = 'learningplan'"],
        ];
    }

    /**
     * A target row at the plan id is an adoptable header copy when its name and created time match
     * (what the retired migrate_all.php copied).
     *
     * @return array<int|string, string>
     */
    public function adopt_signature(): array {
        return ['name', 'timecreated'];
    }

    /**
     * @return string[]
     */
    public function columns(): array {
        return ['id', 'name', 'shortname', 'description', 'objective', 'visible', 'startdate', 'enddate', 'open_path',
            'approvalreqd', 'selfenrol', 'lpsequence', 'learning_type', 'open_points', 'open_categoryid', 'open_skill',
            'open_level', 'usercreated', 'usermodified', 'timecreated', 'timemodified', 'costcenter', 'certificateid'];
    }

    /**
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_skill', ''], ['local_course_levels', '']];
    }

    /**
     * @param \stdClass[] $rows One plan.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $unresolved = (string) $ctx->decision('tenant.unresolved.learningplan');
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->one($row, $ctx, $unresolved);
        }
        return $out;
    }

    /**
     * One plan.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @param string $unresolved Decision tenant.unresolved.learningplan: pathless or skip.
     * @return outcome
     */
    private function one(\stdClass $row, context $ctx, string $unresolved): outcome {
        $id = (int) $row->id;
        $warnings = [];

        [$name, $changed] = plan_rules::clean_text($row->name ?? '');
        if (trim((string) $name) === '') {
            return outcome::skip($id, 'no_name');
        }
        if ($changed) {
            $warnings[] = 'invalid_utf8:name';
        }

        // Tenant: the plan's own path, then the fallback order the owner accepted
        // (learningplan.tenant_fallback_order). The enrolled users and the creator are read only when the
        // first two candidates give nothing.
        $costcenter = plan_rules::positive_or_null($row->costcenter ?? null);
        [$path, , $method] = $ctx->tenant->resolve([
            'open_path' => isset($row->open_path) ? (string) $row->open_path : null,
            'costcenter' => $costcenter,
        ]);
        if ($path === null) {
            $enrolled = plan_source::enrolled_root($ctx, $id);
            $creator = $ctx->tenant->root_of_user(plan_rules::user_id($row->usercreated ?? 0));
            [$path, , $method] = $ctx->tenant->resolve([
                'open_path' => null,
                'costcenter' => null,
                'enrolled_users' => $enrolled,
                'creator' => $creator > 0 ? $creator : null,
            ]);
        }
        if ($path === null && $unresolved === 'skip') {
            return outcome::skip($id, 'tenant_unresolved');
        }

        // costcenterid is the organisation the path is filed under (the edit form's meaning): the org at the
        // path. departmentid is the second segment of a path at least two deep.
        $costcenterid = 0;
        $departmentid = null;
        if ($path !== null) {
            $org = $ctx->lookups->org_by_path($path);
            if ($org !== null) {
                $costcenterid = (int) $org->id;
            } else {
                $warnings[] = 'costcenter_not_org';
            }
            $segments = explode('/', ltrim($path, '/'));
            if (count($segments) >= 2) {
                [$departmentid, $fits] = plan_rules::small_int($segments[1], 2147483647);
                if (!$fits) {
                    $warnings[] = 'department_out_of_range';
                }
            }
        }

        $active = (string) ($row->visible ?? '') === '1';

        // The full value stays in the legacy table; fit() records a truncated:<column> warning when it cuts.
        $name = $ctx->text->fit($name, 255, 'name');
        $shortname = null;
        if (trim((string) ($row->shortname ?? '')) !== '') {
            [$cleanshort, $changed] = plan_rules::clean_text($row->shortname);
            $shortname = $ctx->text->fit($cleanshort, 255, 'shortname');
            if ($changed) {
                $warnings[] = 'invalid_utf8:shortname';
            }
        }
        [$description, $changed] = plan_rules::clean_text($row->description ?? null);
        if ($changed) {
            $warnings[] = 'invalid_utf8:description';
        }
        [$objective, $changed] = plan_rules::clean_text($row->objective ?? null);
        if ($changed) {
            $warnings[] = 'invalid_utf8:objective';
        }

        [$learningtype, $fits] = plan_rules::small_int($row->learning_type ?? null);
        if (!$fits) {
            $warnings[] = 'learning_type_out_of_range';
        }

        // Skill and level resolve through the skills map; a plan whose skill was not imported keeps the plan.
        $skillid = null;
        $skill = plan_rules::positive_or_null($row->open_skill ?? null);
        if ($skill !== null) {
            $skillid = $ctx->map->resolve('local_skill', $skill);
            if ($skillid === null) {
                $warnings[] = 'skill_not_mapped';
            }
        }
        $levelid = null;
        $level = plan_rules::positive_or_null($row->open_level ?? null);
        if ($level !== null) {
            $levelid = $ctx->map->resolve('local_course_levels', $level);
            if ($levelid === null) {
                $warnings[] = 'level_not_mapped';
            }
        }

        [$created, $modified] = plan_rules::times($row);

        $fields = (object) [
            'name' => $name,
            'shortname' => $shortname,
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'objective' => $objective,
            'costcenterid' => $costcenterid,
            'departmentid' => $departmentid,
            'open_path' => $path === null ? null : tenant_resolver::normalise($path),
            'status' => $active ? 1 : 0,
            'visible' => $active ? 1 : 0,
            // Display-only in BizLMS; Sentientia's enrolment window (decision learningplan.dates_as). 0 means none.
            'startdate' => plan_rules::positive_or_null($row->startdate ?? null),
            'enddate' => plan_rules::positive_or_null($row->enddate ?? null),
            // The adaptive journey engine stays off for an imported path.
            'adaptive_mode' => 0,
            'score_threshold_low' => null,
            'score_threshold_high' => null,
            'learning_type' => $learningtype,
            // Stored, not enforced (decision learningplan.enforce_rules).
            'approvalreqd' => plan_rules::flag($row->approvalreqd ?? null),
            'selfenrol' => plan_rules::flag($row->selfenrol ?? null),
            'sequential' => plan_rules::flag($row->lpsequence ?? null),
            'points' => plan_rules::int_value($row->open_points ?? null) ?: null,
            'categoryid' => plan_rules::positive_or_null($row->open_categoryid ?? null),
            'skillid' => $skillid,
            'levelid' => $levelid,
            'certificateid' => plan_rules::positive_or_null($row->certificateid ?? null),
            'usercreated' => plan_rules::user_id($row->usercreated ?? 0),
            'usermodified' => plan_rules::user_id($row->usermodified ?? 0),
            'timecreated' => $created,
            'timemodified' => $modified,
        ];

        $outcome = outcome::insert($id, importer::PATHS, $fields)->tenant_method($method);
        foreach (array_unique($warnings) as $code) {
            $outcome->warn($code);
        }
        return $outcome;
    }
}
