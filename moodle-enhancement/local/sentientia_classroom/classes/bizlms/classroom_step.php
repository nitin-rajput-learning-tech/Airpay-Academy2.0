<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

defined('MOODLE_INTERNAL') || die();

/**
 * local_classroom -> local_sentientia_classroom (PRESERVE).
 *
 * The legacy id is kept because rows the import does not rewrite hold it: issued certificates, calendar
 * events, course enrolment instances, evaluation forms and ratings (external_refs). A header copy an old
 * copy script left at the same id is adopted and overwritten with the full mapping, which corrects the raw
 * BizLMS status it copied.
 *
 * The tenant path is the one thing a reader scopes on (open_path). It is normalised and checked against
 * the organisation tree; a classroom whose path cannot be resolved is imported with no path, visible to
 * cross-tenant callers only (decisions classroom.pathless and tenant.unresolved.classroom).
 *
 * Not copied, because nothing in Sentientia reads it (mapping doc rule R7): type, course, classroom_type,
 * institute_type, points, the cost columns, the cached counters, the feedback link, manage_approval,
 * allow_multi_session, allow_waitinglistusers, config, department, subdepartment, every open_* audience
 * column, approvalreqd, selfenrol and certificateid. They stay in local_classroom.
 *
 * @package    local_sentientia_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classroom_step extends step {

    /** @var legacy_venues */
    private legacy_venues $venues;

    /** @var trainer_index */
    private trainer_index $trainers;

    /**
     * @param legacy_venues $venues
     * @param trainer_index $trainers
     */
    public function __construct(legacy_venues $venues, trainer_index $trainers) {
        $this->venues = $venues;
        $this->trainers = $trainers;
    }

    /**
     * @return string
     */
    public function key(): string {
        return 'classroom.classrooms';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_classroom';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return 'local_sentientia_classroom';
    }

    /**
     * @return string
     */
    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * Rows the import does not rewrite that hold a classroom id (ADR-032, PRESERVE targets).
     *
     * @return array<array{0: string, 1: string, 2: string}>
     */
    public function external_refs(): array {
        return [
            ['tool_certificate_issues', 'moduleid', "moduletype = 'classroom'"],
            ['event', 'plugin_instance', "plugin = 'local_classroom'"],
            ['enrol', 'customint1', "enrol = 'classroom'"],
            ['local_evaluations', 'instance', "plugin = 'classroom'"],
            ['local_rating', 'itemid', "ratearea = 'local_classroom'"],
        ];
    }

    /**
     * The tenant path of a classroom row, by the rule the owner signed.
     *
     * The row's own open_path is tried first. With classroom.pathless = cross_tenant_only (the signed value)
     * nothing else is: the classroom is then visible to cross-tenant callers only. by_costcenter and
     * by_creator, the other two values the decision allows, add the cost centre and the creator's tenant as
     * further candidates.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @param string $pathless decision classroom.pathless
     * @return array{0: string|null, 1: string} [path or null, method for outcome::tenant_method()]
     */
    public static function tenant_of(\stdClass $row, context $ctx, string $pathless): array {
        $candidates = ['open_path' => mapping::text($row, 'open_path')];
        if ($pathless === 'by_costcenter' || $pathless === 'by_creator') {
            $costcenter = mapping::int($row, 'costcenter');
            if ($costcenter > 0) {
                $candidates['costcenter'] = '/' . $costcenter;
            }
        }
        if ($pathless === 'by_creator') {
            $root = $ctx->tenant->root_of_user(mapping::int($row, 'usercreated'));
            if ($root > 0) {
                $candidates['creator'] = '/' . $root;
            }
        }
        [$path, , $method] = $ctx->tenant->resolve($candidates);
        return [$path, $method];
    }

    /**
     * The parent maps this step resolves, loaded once instead of one query per distinct parent.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_location_institutes', '']];
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $newstates = $ctx->decision('classroom.status_new_hold') === 'add_5_6';
        $pathless = (string) $ctx->decision('classroom.pathless');
        $skipunresolved = $ctx->decision('tenant.unresolved.classroom') === 'skip';

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $warnings = [];

            $status = mapping::classroom_status(mapping::int($row, 'status', -1), $newstates);
            if ($status === null) {
                $out[] = outcome::skip($id, 'invalid_status');
                continue;
            }

            [$path, $method] = self::tenant_of($row, $ctx, $pathless);
            if ($path === null && $skipunresolved) {
                $out[] = outcome::skip($id, 'pathless_skipped')->tenant_method($method);
                continue;
            }

            $name = mapping::text($row, 'name');
            if ($name === '') {
                $name = 'Classroom ' . $id;
                $warnings[] = 'name_defaulted';
            }
            $shortname = mapping::text($row, 'shortname');

            // The venue: the institute's name is the text the classroom pages show.
            $locationid = null;
            $location = null;
            $instituteid = mapping::int($row, 'instituteid');
            if ($instituteid > 0) {
                $institute = $this->venues->institute($instituteid, $ctx);
                if ($institute === null) {
                    $warnings[] = 'institute_not_found';
                } else {
                    $locationid = $ctx->map->resolve('local_location_institutes', $instituteid);
                    $text = $ctx->text->fit(mapping::text($institute, 'fullname'), 254, 'location');
                    $location = $text !== '' ? $text : null;
                }
            }

            // The first trainer BizLMS attached whose user still exists. The attendance pages let exactly
            // the session's or classroom's trainer in, so a classroom without one is open to managers only.
            $trainerid = null;
            $attached = $this->trainers->for_classroom($id, $ctx);
            foreach ($attached as $candidate) {
                if ($ctx->lookups->user_exists($candidate)) {
                    $trainerid = $candidate;
                    break;
                }
            }
            if ($attached && $trainerid === null) {
                $warnings[] = 'trainer_not_found';
            }

            $capacity = mapping::int($row, 'capacity');
            if ($capacity > mapping::CAPACITY_MAX) {
                $warnings[] = 'capacity_clamped';
            }

            $created = mapping::time_created($row);
            $fields = (object) [
                'name' => $ctx->text->fit($name, 254, 'name'),
                'shortname' => $shortname !== '' ? $ctx->text->fit($shortname, 225, 'shortname') : null,
                'description' => isset($row->description) ? (string) $row->description : null,
                'costcenterid' => mapping::costcenter_of_path($path),
                'departmentid' => mapping::department_of_path($path),
                'open_path' => $path,
                'trainerid' => $trainerid,
                'location' => $location,
                'locationid' => $locationid,
                'capacity' => mapping::clamp($capacity, mapping::CAPACITY_MAX),
                'status' => $status,
                'visible' => mapping::int($row, 'visible', 1) === 0 ? 0 : 1,
                // The enrolment window is BizLMS's nomination window; the run dates are the training dates.
                'startdate' => mapping::date_or_null($row, 'nomination_startdate'),
                'enddate' => mapping::date_or_null($row, 'nomination_enddate'),
                'trainingstart' => mapping::date_or_null($row, 'startdate'),
                'trainingend' => mapping::date_or_null($row, 'enddate'),
                'timecompleted' => mapping::date_or_null($row, 'completiondate'),
                'createdby' => mapping::actor($row, 'usercreated'),
                'timecreated' => $created,
                'timemodified' => mapping::time_modified($row, $created),
            ];
            $o = outcome::insert($id, 'local_sentientia_classroom', $fields)->tenant_method($method);
            foreach ($warnings as $warning) {
                $o->warn($warning);
            }
            $out[] = $o;
        }
        return $out;
    }
}
