<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\step;

/**
 * What the two org-role tables have in common (mapping doc section 4): a row names an organisation, a role, one or
 * more users (a comma list in local_costcenter_permissions) and who made the assignment and when. Each subclass
 * says how to read those facts from its own columns; this class turns them into outcomes.
 *
 * transform() is pure: it reads through the context (and the plain reads named below) and returns outcomes.
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class assignment_step extends step {

    /**
     * What a row says, in one shape for both tables.
     *
     * @param \stdClass $row A source row.
     * @return \stdClass {included: bool, orgid: int, users: string, roleid: int, modifier: int, assigned: int,
     *         audited: int}. included is false for a row that is not an assignment. assigned is the value for
     *         role_assignments.timemodified, audited the value for the audit row's timecreated.
     */
    abstract protected function facts(\stdClass $row): \stdClass;

    /**
     * The audit row's reason: a code naming the source table, never free text.
     *
     * @return string
     */
    abstract protected function audit_reason(): string;

    /**
     * The primary target of the step. The assignments themselves are core rows (a declared core write).
     *
     * @return string
     */
    public function targettable(): string {
        return importer::AUDIT_TABLE;
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        // The signed choice is that only value = 1 counts. Any other value is refused at preflight; this keeps a
        // step from running on a choice it does not implement.
        if ($ctx->decision(importer::DECISION_VALUE_FILTER) !== 'value_1_only') {
            throw new blocked('decision_value_not_supported:' . importer::DECISION_VALUE_FILTER);
        }

        $facts = [];
        $orgids = [];
        foreach ($rows as $row) {
            $f = $this->facts($row);
            $facts[(int) $row->id] = $f;
            if ($f->included && $f->roleid > 0 && $f->orgid > 0) {
                $orgids[$f->orgid] = true;
            }
        }
        $orgs = org_contexts::for_orgs($ctx->legacy, array_keys($orgids));

        $out = [];
        foreach ($rows as $row) {
            foreach ($this->transform_row((int) $row->id, $facts[(int) $row->id], $ctx, $orgs) as $o) {
                $out[] = $o;
            }
        }
        return $out;
    }

    /**
     * One source row.
     *
     * @param int $sid
     * @param \stdClass $f Facts of the row.
     * @param context $ctx
     * @param array<int, \stdClass> $orgs org_contexts::for_orgs() result.
     * @return outcome[]
     */
    private function transform_row(int $sid, \stdClass $f, context $ctx, array $orgs): array {
        if (!$f->included) {
            return [outcome::archive($sid, importer::REASON_VALUE_NOT_ASSIGNED)];
        }
        if ($f->roleid <= 0) {
            return [outcome::skip($sid, importer::REASON_NO_ROLE)];
        }
        if (!$ctx->lookups->exists('role', $f->roleid)) {
            return [outcome::skip($sid, importer::REASON_ROLE_NOT_FOUND)];
        }

        // The organisation goes through the map, like every foreign legacy id, even though the org feature keeps ids.
        $mapped = $f->orgid > 0 ? $ctx->map->resolve(importer::COSTCENTER, $f->orgid) : null;
        if ($mapped === null) {
            $code = ($f->orgid > 0 && $ctx->is_deferred(importer::COSTCENTER))
                ? reason::DEFERRED : importer::REASON_ORG_NOT_FOUND;
            return [outcome::skip($sid, $code)];
        }
        $org = $orgs[$f->orgid] ?? null;
        if ($org === null || $org->contextid === null) {
            // Preflight blocks this before anything is written. Reaching it means the data changed since.
            throw new blocked('org_context_missing:' . $f->orgid);
        }

        // The users of the row. The position in the list (1-based) is what the map remembers, never the user id.
        $problems = [];
        $valid = [];
        $seen = [];
        foreach (explode(',', $f->users) as $index => $token) {
            $position = $index + 1;
            $token = trim($token);
            if (!preg_match('/^[0-9]{1,10}$/', $token) || (int) $token <= 0) {
                $problems[] = 'user_invalid';
                continue;
            }
            $userid = (int) $token;
            if (isset($seen[$userid])) {
                $problems[] = 'duplicate_user';
                continue;
            }
            $seen[$userid] = true;
            if (!$ctx->lookups->user_exists($userid)) {
                $problems[] = 'user_not_found';
                continue;
            }
            if (!$ctx->lookups->user_active($userid)) {
                $problems[] = 'user_deleted';
                continue;
            }
            $valid[] = [$position, $userid];
        }
        if (!$valid) {
            $skip = outcome::skip($sid, importer::REASON_NO_VALID_USER, $problems[0] ?? 'user_missing');
            foreach (array_unique($problems) as $code) {
                $skip->warn($code);
            }
            return [$skip];
        }

        $existing = $this->existing_assignments($f->roleid, (int) $org->contextid, array_column($valid, 1));
        $shortname = $this->role_shortname($f->roleid);
        $modifier = $ctx->lookups->user_exists($f->modifier) ? $f->modifier : 0;
        if ($f->modifier > 0 && $modifier === 0) {
            $problems[] = 'modifier_unknown';
        }
        if ($f->assigned <= 0) {
            $problems[] = 'no_source_time';
        }
        // The audit list is scoped by the actor's path or the target's, so the actor's path goes on the row.
        [$openpath, , $method] = $ctx->tenant->resolve(['actor' => $modifier > 0 ? $ctx->lookups->user_path($modifier) : null]);
        $orgroot = $org->path === null ? 0 : (int) explode('/', ltrim($org->path, '/'))[0];

        $primary = null;
        $subs = [];
        foreach ($valid as [$position, $userid]) {
            $userroot = $ctx->tenant->root_of_user($userid);
            if ($orgroot > 0 && $userroot > 0 && $userroot !== $orgroot) {
                $problems[] = 'user_outside_org_tenant';
            }
            $have = $existing[$userid] ?? null;
            if ($have !== null) {
                if ($primary === null) {
                    $primary = outcome::fold($sid, importer::ASSIGNMENTS, $have, importer::REASON_ALREADY_ASSIGNED);
                } else {
                    $problems[] = 'assignment_exists';
                }
                continue;
            }
            $assignment = (object) [
                'roleid' => $f->roleid,
                'contextid' => (int) $org->contextid,
                'userid' => $userid,
                'timemodified' => $f->assigned,
                'modifierid' => $modifier,
                'component' => '',
                'itemid' => 0,
                'sortorder' => 0,
            ];
            if ($primary === null) {
                $primary = outcome::insert($sid, importer::ASSIGNMENTS, $assignment);
            } else {
                $subs[] = outcome::insert($sid, importer::ASSIGNMENTS, $assignment, 'pos:' . $position);
            }
            $subs[] = outcome::insert($sid, importer::AUDIT_TABLE, (object) [
                'roleid' => $f->roleid,
                'roleshortname' => $ctx->text->fit($shortname, 100, 'roleshortname'),
                'action' => 'role_assigned',
                'capability' => null,
                'oldpermission' => null,
                'newpermission' => null,
                'contextid' => (int) $org->contextid,
                'targetuserid' => $userid,
                'changedby' => $modifier,
                'reason' => $this->audit_reason(),
                'open_path' => $openpath,
                'timecreated' => $f->audited,
            ], 'aud:' . $position);
        }

        $primary->tenant_method($method);
        foreach (array_unique($problems) as $code) {
            $primary->warn($code);
        }
        array_unshift($subs, $primary);
        return $subs;
    }

    /**
     * The manual assignments that already exist at a context, by user.
     *
     * component '' and itemid 0 is what role_assign() makes for a manual assignment and what the map names as the
     * natural key. A second row for the same key would make the user hold the role twice.
     *
     * @param int $roleid
     * @param int $contextid
     * @param int[] $userids
     * @return array<int, int> userid => assignment id (the lowest, when there are several)
     */
    private function existing_assignments(int $roleid, int $contextid, array $userids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'blmuser');
        $params['blmrole'] = $roleid;
        $params['blmcontext'] = $contextid;
        $params['blmcomponent'] = '';
        $params['blmitem'] = 0;
        // The first column keys the result: one row per user, so the lowest assignment id must come first.
        $rows = $DB->get_records_sql(
            "SELECT MIN(ra.id) AS id, ra.userid
               FROM {role_assignments} ra
              WHERE ra.roleid = :blmrole AND ra.contextid = :blmcontext AND ra.component = :blmcomponent
                AND ra.itemid = :blmitem AND ra.userid $insql
           GROUP BY ra.userid", $params);
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->userid] = (int) $row->id;
        }
        return $out;
    }

    /**
     * The role's shortname, to snapshot on the audit row (it survives the role's deletion).
     *
     * @param int $roleid
     * @return string
     */
    private function role_shortname(int $roleid): string {
        global $DB;
        return (string) $DB->get_field('role', 'shortname', ['id' => $roleid]);
    }

    /**
     * The first positive value, or zero.
     *
     * @param int ...$values
     * @return int
     */
    protected static function first_positive(int ...$values): int {
        foreach ($values as $value) {
            if ($value > 0) {
                return $value;
            }
        }
        return 0;
    }
}
