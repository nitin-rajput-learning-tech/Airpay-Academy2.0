<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_learningplan_approval -> local_sentientia_request, item_type 'path' (mapping doc, section 19).
 *
 * BizLMS kept a second record of a learning-plan request here, written by the learning-plan plugin on
 * approve and reject. This feature owns the table (one owner per legacy table) although it belongs to
 * another BizLMS plugin.
 *
 * A request for a learning plan normally has BOTH a local_request_records row and an approval row. When an
 * imported request row exists for the same (user, plan), the approval row adds nothing and is FOLDED into
 * it (outcome fold, reason dup_of_request): the request history then shows one request, not two. The
 * mapping doc calls this "merged"; the framework's merge outcome needs a winner in the SAME step, and the
 * winner here is in another, so fold is the word that fits. When several request rows exist for the pair the
 * latest one (highest id) that was imported wins, because the approval row holds the state of the latest
 * request. An approval with no request row becomes a request of its own, the submit_path() shape.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class approvals_step extends request_step {

    /** Rows per keyset page when the request rows of learning plans are indexed. */
    private const PAGE = 5000;

    /** @var user_cache Requesters and approvers: supervisor fields and the suspended flag. */
    private user_cache $users;

    /** @var array<int, array<int, int[]>>|null User id => legacy plan id => request record ids, ascending. */
    private ?array $requests = null;

    /** @var bool Users primed. */
    private bool $primed = false;

    public function __construct() {
        $this->users = new user_cache(['deleted', 'suspended', 'open_supervisorid', 'open_managerid']);
    }

    public function key(): string {
        return 'request.approvals';
    }

    public function sourcetable(): string {
        return legacy_request::SOURCE_APPROVALS;
    }

    /**
     * The learning-plan map, and this feature's own request map (the records step has finished by now).
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        return [['local_learningplan', ''], [legacy_request::SOURCE_RECORDS, '']];
    }

    public function transform(array $rows, context $ctx): array {
        $settings = $this->settings($ctx);
        $this->prime($ctx);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->one($row, $ctx, $settings);
        }
        return $out;
    }

    /**
     * One approval row.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @param \stdClass $settings
     * @return outcome
     */
    private function one(\stdClass $row, context $ctx, \stdClass $settings): outcome {
        $id = (int) $row->id;
        $userid = (int) $row->userid;
        $planid = (int) $row->planid;
        $warnings = [];

        if (!$ctx->lookups->user_exists($userid)) {
            return outcome::skip($id, 'orphan_user', 'user_not_found');
        }
        $user = $this->users->get($ctx, $userid) ?? (object) ['id' => $userid];

        $status = legacy_request::APPROVAL_STATUSES[(int) $row->approvestatus] ?? null;
        if ($status === null) {
            // Preflight blocks these values; reaching here means it was skipped.
            throw new blocked('unhandled_enum_value:' . legacy_request::SOURCE_APPROVALS);
        }

        // Rows BizLMS hid: the requester was deleted or suspended.
        $hiddenrequester = (int) ($user->deleted ?? 0) === 1 || (int) ($user->suspended ?? 0) === 1;
        if ($settings->hidden === 'filter' && $hiddenrequester) {
            return outcome::archive($id, 'hidden_in_bizlms');
        }

        // The same request, already imported from local_request_records: nothing to add.
        $requestid = $this->imported_request($ctx, $userid, $planid);
        if ($requestid !== null) {
            return outcome::fold($id, legacy_request::TARGET, $requestid, 'dup_of_request');
        }

        // The plan. A plan that no longer exists in BizLMS has nothing to point at: skipped and reported (a
        // request row would have kept the legacy id; this table is the secondary record of a request).
        $entry = $ctx->map->entry('local_learningplan', $planid);
        if ($entry === null) {
            if ($ctx->is_deferred('local_learningplan')) {
                return outcome::skip($id, 'deferred');
            }
            return outcome::skip($id, 'orphan_item', 'item_not_found');
        }
        if ($entry['targetid'] === null) {
            return outcome::skip($id, 'orphan_item', 'item_not_imported');
        }
        $itemid = (int) $entry['targetid'];

        [$root, $method] = $this->tenant_root($ctx, $userid);
        if ($method === 'unresolved') {
            if ($settings->unresolved === 'skip') {
                return outcome::skip($id, 'no_tenant');
            }
            $warnings[] = 'tenant_unresolved';
        }

        $created = legacy_request::timestamp($row->timecreated);
        $modified = legacy_request::timestamp($row->timemodified);
        [$timecreated, $derivedc] = legacy_request::coalesce_time([$created, $modified]);
        [$timemodified, $derivedm] = legacy_request::coalesce_time([$modified, $created]);
        if ($derivedc || $derivedm) {
            $warnings[] = 'derived_timestamp';
        }

        if ($status === 'pending') {
            // The plan is in the map (a missing one was skipped above), so only the requester can make this row stale.
            [$newstatus, $route, $approver, $planwarning] = $this->pending_plan($ctx, $settings, 'path', $itemid, 0, $user);
            if ($planwarning !== null) {
                $warnings[] = $planwarning;
            }
            $decidedby = null;
            $timedecided = null;
        } else {
            // The learning-plan plugin records who decided in approvedby, and usermodified when that is 0.
            $approvedby = legacy_request::int10($row->approvedby) ?? 0;
            $modifier = legacy_request::int10($row->usermodified) ?? 0;
            $decider = $approvedby > 0 ? $approvedby : ($modifier > 0 ? $modifier : null);
            $newstatus = $status;
            $route = $settings->decidedroute;
            $approver = $decider;
            $decidedby = $decider;
            // The approval row carries one time for its last change; for a decided row that is the decision.
            $timedecided = $timemodified > 0 ? $timemodified : null;
        }

        $note = self::plain_text($row->reject_msg ?? '');
        $fields = (object) [
            'userid' => $userid,
            'item_type' => 'path',
            'itemid' => $itemid,
            'courseid' => 0,
            'costcenterid' => $root,
            'reason' => '',
            'status' => $newstatus,
            'route' => $route,
            'approver_userid' => $approver,
            'decision_note' => $note === '' ? null : $note,
            'decided_by_userid' => $decidedby,
            'timecreated' => $timecreated,
            'timedue' => null,
            'timedecided' => $timedecided,
            'timeescalated' => null,
            'timemodified' => $timemodified,
            'legacy_source' => legacy_request::MARK,
        ];
        $result = outcome::insert($id, legacy_request::TARGET, $fields)->tenant_method($method);
        foreach ($warnings as $warning) {
            $result->warn($warning);
        }
        return $result;
    }

    /**
     * The imported request row for a (user, plan) pair, if there is one.
     *
     * @param context $ctx
     * @param int $userid
     * @param int $planid Legacy plan id.
     * @return int|null Target id in local_sentientia_request; the latest imported one when there are several.
     */
    private function imported_request(context $ctx, int $userid, int $planid): ?int {
        $candidates = $this->requests[$userid][$planid] ?? [];
        if (!$candidates) {
            return null;
        }
        $targets = $ctx->map->resolve_many(legacy_request::SOURCE_RECORDS, $candidates);
        foreach (array_reverse($candidates) as $recordid) {
            if (($targets[$recordid] ?? null) !== null) {
                return (int) $targets[$recordid];
            }
        }
        return null;
    }

    /**
     * Load the users of the approval table, and index the learning-plan requests of local_request_records by
     * (user, plan), once.
     *
     * @param context $ctx
     * @return void
     */
    private function prime(context $ctx): void {
        if ($this->primed) {
            return;
        }
        $this->primed = true;
        $this->users->prime_from($ctx, legacy_request::SOURCE_APPROVALS, ['userid', 'approvedby', 'usermodified']);

        $this->requests = [];
        if (!$ctx->legacy->exists(legacy_request::SOURCE_RECORDS)) {
            return;
        }
        $after = 0;
        do {
            $page = $ctx->legacy->page(legacy_request::SOURCE_RECORDS, $after, self::PAGE,
                ['id', 'createdbyid', 'componentid'], ['compname = :blmcomp', ['blmcomp' => 'learningplan']]);
            foreach ($page as $recordid => $record) {
                $after = (int) $recordid;
                $this->requests[(int) $record->createdbyid][(int) $record->componentid][] = (int) $recordid;
            }
        } while (count($page) === self::PAGE);
    }
}
