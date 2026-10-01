<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_program -> local_sentientia_programs (PRESERVE).
 *
 * The id is kept because rows the import does not rewrite store it: issued certificates, the BizLMS enrol
 * instances, requests, e-mail log rows and ratings (ADR-032, "Id strategy"). A taken id is a blocker, never a
 * new id.
 *
 * What is copied is what a Sentientia reader shows or an engine uses (mapping doc R7); the audience CSVs, points,
 * capacity, approval flags, cached counters and the like stay in the legacy table, which is the archive. The one
 * use of the shortname is as the name of a program whose name is empty (warning name_from_shortname); a program with
 * neither is skipped as no_name, which needs the owner.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class program_step extends base_step {

    public function key(): string {
        return 'program.program';
    }

    public function sourcetable(): string {
        return 'local_program';
    }

    public function targettable(): string {
        return self::T_PROGRAMS;
    }

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * Rows the import does not rewrite that hold a program id (ADR-032 id table, program row).
     *
     * @return array<array{0: string, 1: string, 2: string}>
     */
    public function external_refs(): array {
        return [
            ['tool_certificate_issues', 'moduleid', "moduletype = 'program'"],
            ['local_rating', 'itemid', "ratearea = 'local_program'"],
            ['enrol', 'customint1', "enrol = 'program'"],
            ['local_request_records', 'componentid', "compname = 'program'"],
            ['local_emaillogs', 'moduleid', "moduletype = 'program'"],
        ];
    }

    public function columns(): array {
        return ['name', 'shortname', 'description', 'visible', 'status', 'nomination_startdate',
            'nomination_enddate', 'open_path', 'timecreated', 'usercreated', 'timemodified'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->program($row, $ctx);
        }
        return $out;
    }

    /**
     * One program.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome
     */
    private function program(\stdClass $row, context $ctx): outcome {
        $id = (int) $row->id;

        $name = trim((string) $row->name);
        $fromshortname = false;
        if ($name === '') {
            // The shortname is required in BizLMS, so it is the only honest label left. The map does not copy the
            // shortname, so the substitution is reported (warning name_from_shortname), never silent.
            $name = trim((string) $row->shortname);
            $fromshortname = $name !== '';
        }
        if ($name === '') {
            return outcome::skip($id, 'no_name');
        }

        // Tenant: the program's own path, else the root of its creator's path (decision program.pathless).
        $candidates = ['row' => $row->open_path];
        if ($ctx->decision('program.pathless') === 'creator_root') {
            $root = $ctx->tenant->root_of_user((int) $row->usercreated);
            if ($root > 0) {
                $candidates['creator'] = '/' . $root;
            }
        }
        [$path, $root, $method] = $ctx->tenant->resolve($candidates);
        if ($path === null && $ctx->decision('tenant.unresolved.program') === 'skip') {
            return outcome::skip($id, 'tenant_unresolved');
        }
        $org = $path === null ? null : $ctx->lookups->org_by_path($path);
        $costcenterid = $org !== null ? (int) $org->id : (int) $root;

        [$status, $oddstatus] = rules::program_status((int) $row->status,
            $row->visible === null ? null : (int) $row->visible, (string) $ctx->decision('program.inactive'));
        $visible = $row->visible === null ? 1 : rules::flag($row->visible);

        $created = (int) $row->timecreated;
        $modified = rules::time_or($row->timemodified, $created);

        $fields = (object) [
            'name' => $ctx->text->fit($name, 254, 'name'),
            // The editor text is stored raw and Sentientia renders HTML (mapping doc, program column map).
            'description' => $row->description,
            'descriptionformat' => FORMAT_HTML,
            'costcenterid' => $costcenterid,
            'open_path' => $path,
            'status' => $status,
            'visible' => $visible,
            // The enrolment window is BizLMS' nomination window; its own startdate and enddate were always forced to 0.
            'startdate' => (int) $row->nomination_startdate > 0 ? (int) $row->nomination_startdate : null,
            'enddate' => (int) $row->nomination_enddate > 0 ? (int) $row->nomination_enddate : null,
            'completion_required' => rules::program_completion_required($this->data($ctx)->program_criteria($id)),
            'timecreated' => $created,
            'timemodified' => $modified,
        ];

        $result = outcome::insert($id, self::T_PROGRAMS, $fields)->tenant_method($method);
        if ($oddstatus) {
            $result->warn('status_by_visible');
        }
        if ((int) $row->timemodified <= 0) {
            $result->warn('derived_timestamp');
        }
        if ($fromshortname) {
            $result->warn('name_from_shortname');
        }
        if ($path === null) {
            // Kept with no tenant path (decision tenant.unresolved.program = pathless): visible to cross-tenant
            // callers only, and always reported.
            $result->warn('tenant_unresolved');
        }
        return $result;
    }
}
