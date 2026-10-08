<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

defined('MOODLE_INTERNAL') || die();

/**
 * local_request_records -> local_sentientia_request (mapping doc, section 19, "Column maps").
 *
 * One request is one row; duplicates are legal in the source (the BizLMS API does not stop a second request
 * for the same item) and stay separate rows. MAP: new ids, resolved through the legacy map.
 *
 * What a row becomes:
 *  - item_type / itemid / courseid from compname and componentid. A course id is a core id and is kept. A
 *    path, classroom or program goes through that feature's map; one that is gone gets itemid 0 (COMMS-R2: the
 *    legacy id could be given to a later item). A certification has no Sentientia entity (gap G3):
 *    item_type certification, the legacy id kept, history only.
 *  - status PENDING/APPROVED/REJECTED -> pending/approved/rejected. A decided row is routed 'admin' (owner
 *    decision request.decided_route) with the responder as approver and decider. A pending course or path
 *    row is routed the way a new submission would be, unless its requester is deleted or suspended or its item
 *    is gone (COMMS-R1, request.pending_stale = history_only: warning pending_history_only); a pending
 *    classroom, program or certification row has no approver and so sits in nobody's inbox.
 *  - costcenterid: the requester's CURRENT tenant root (the source stores none), 0 when there is none.
 *  - reason '': BizLMS recorded no reason (the column is NOT NULL text, so an empty string); the lists say so.
 *  - decision_note: the comment thread of the request, oldest first, as "[date] <full name>: text".
 *  - Times come from the source. timedue and timeescalated are NULL, so the escalation query (timedue < now)
 *    never selects an imported row, and the cron jobs skip legacy_source rows besides.
 *  - module_id (= componentid), usermodified and the seven columns nothing ever wrote are not copied.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class records_step extends request_step {

    /** Rows per keyset page when the comments table is read. */
    private const PAGE = 5000;

    /** @var user_cache Requesters and responders: supervisor fields and the suspended flag. */
    private user_cache $users;

    /** @var user_cache Authors of comments: the name fields. */
    private user_cache $authors;

    /** @var array<int, \stdClass[]>|null Request id => its comments. */
    private ?array $comments = null;

    /** @var bool Users primed. */
    private bool $primed = false;

    public function __construct() {
        $this->users = new user_cache(['deleted', 'suspended', 'open_supervisorid', 'open_managerid']);
        $this->authors = new user_cache(\core_user\fields::get_name_fields());
    }

    public function key(): string {
        return 'request.records';
    }

    public function sourcetable(): string {
        return legacy_request::SOURCE_RECORDS;
    }

    /**
     * The map of the three features a request can point at, so resolving an item is an array read.
     *
     * @return array<array{0: string, 1?: string}>
     */
    public function preload(): array {
        $pairs = [];
        foreach (legacy_request::ITEM_SOURCES as $source) {
            $pairs[] = [$source, ''];
        }
        return $pairs;
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
     * One request.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @param \stdClass $settings
     * @return outcome
     */
    private function one(\stdClass $row, context $ctx, \stdClass $settings): outcome {
        $id = (int) $row->id;
        $userid = (int) $row->createdbyid;
        $warnings = [];

        // A requester with no user row at all cannot be shown by any reader. (A deleted user is history and is
        // imported; the lists filter it.)
        if (!$ctx->lookups->user_exists($userid)) {
            return outcome::skip($id, 'orphan_user', 'user_not_found');
        }
        $user = $this->users->get($ctx, $userid) ?? (object) ['id' => $userid];

        $itemtype = legacy_request::COMPNAMES[(string) $row->compname] ?? null;
        $status = legacy_request::RECORD_STATUSES[(string) $row->status] ?? null;
        if ($itemtype === null || $status === null) {
            // Preflight blocks these values; reaching here means it was skipped.
            throw new blocked('unhandled_enum_value:' . legacy_request::SOURCE_RECORDS);
        }

        // The item.
        $componentid = legacy_request::int10($row->componentid);
        if ($componentid === null) {
            return outcome::skip($id, 'orphan_item', 'id_out_of_range');
        }
        $itemid = $componentid;
        $courseid = 0;
        $gone = false;
        if ($itemtype === 'course') {
            $courseid = $componentid;
            $gone = !$ctx->lookups->course_exists($componentid);
        } else if ($itemtype !== 'certification') {
            $source = legacy_request::ITEM_SOURCES[$itemtype];
            $entry = $ctx->map->entry($source, $componentid);
            if ($entry === null) {
                if ($ctx->is_deferred($source)) {
                    return outcome::skip($id, 'deferred');
                }
                // The component no longer exists in BizLMS. BizLMS hid the row; the owner chose to show it
                // (request.hidden_rows). It does NOT keep the legacy id (COMMS-R2): paths, classrooms and programs
                // keep their ids on import and reset their sequence to MAX(id)+1, so if the deleted item had the
                // highest id the next new one gets it, and this old request would show the new item's name, trip the
                // duplicate guard of submit_path() and, on approval, enrol the learner in an unrelated item. itemid 0
                // renders as "(deleted item)"; the legacy id stays in local_request_records.componentid and, with the
                // legacy map, is recoverable. A course id is a core id and is never reused, so a course keeps its id.
                $itemid = 0;
                $gone = true;
            } else if ($entry['targetid'] === null) {
                return outcome::skip($id, 'orphan_item', 'item_not_imported');
            } else {
                $itemid = (int) $entry['targetid'];
            }
        }
        if ($gone) {
            $warnings[] = 'item_deleted';
        }

        // Rows BizLMS hid: the component is gone, or the requester was deleted or suspended.
        $hiddenrequester = (int) ($user->deleted ?? 0) === 1 || (int) ($user->suspended ?? 0) === 1;
        if ($settings->hidden === 'filter' && ($gone || $hiddenrequester)) {
            return outcome::archive($id, 'hidden_in_bizlms');
        }

        [$root, $method] = $this->tenant_root($ctx, $userid);
        if ($method === 'unresolved') {
            if ($settings->unresolved === 'skip') {
                return outcome::skip($id, 'no_tenant');
            }
            $warnings[] = 'tenant_unresolved';
        }

        // Times: the source's own, with the fallbacks the mapping doc names.
        $created = legacy_request::timestamp($row->timecreated);
        $modified = legacy_request::timestamp($row->timemodified);
        $responded = legacy_request::timestamp($row->respondeddate);
        [$timecreated, $derivedc] = legacy_request::coalesce_time([$created, $modified, $responded]);
        [$timemodified, $derivedm] = legacy_request::coalesce_time([$modified, $responded, $created]);
        if ($derivedc || $derivedm) {
            $warnings[] = 'derived_timestamp';
        }

        // Decided, or still open.
        $responder = legacy_request::int10($row->responder);
        $responder = ($responder !== null && $responder > 0) ? $responder : null;
        if ($status === 'pending') {
            [$newstatus, $route, $approver, $planwarning] = $this->pending_plan($ctx, $settings, $itemtype, $itemid,
                $courseid, $user, $gone);
            if ($planwarning !== null) {
                $warnings[] = $planwarning;
            }
            $decidedby = null;
            $timedecided = null;
        } else {
            $newstatus = $status;
            $route = $settings->decidedroute;
            $approver = $responder;
            $decidedby = $responder;
            $timedecided = ($responded !== null && $responded > 0) ? $responded : null;
            if ($timedecided === null) {
                $warnings[] = 'no_decision_time';
            }
        }

        $fields = (object) [
            'userid' => $userid,
            'item_type' => $itemtype,
            'itemid' => $itemid,
            'courseid' => $courseid,
            'costcenterid' => $root,
            'reason' => '',
            'status' => $newstatus,
            'route' => $route,
            'approver_userid' => $approver,
            'decision_note' => $this->note_for($ctx, $id),
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
     * Load the requesters and responders of the whole table, and the comments, once.
     *
     * @param context $ctx
     * @return void
     */
    private function prime(context $ctx): void {
        if ($this->primed) {
            return;
        }
        $this->primed = true;
        $this->users->prime_from($ctx, legacy_request::SOURCE_RECORDS, ['createdbyid', 'responder']);
        $this->load_comments($ctx);
    }

    /**
     * Read every comment once (the table is expected to be empty), keyed by the request it belongs to.
     *
     * @param context $ctx
     * @return void
     */
    private function load_comments(context $ctx): void {
        if ($this->comments !== null) {
            return;
        }
        $this->comments = [];
        if (!$ctx->legacy->exists(legacy_request::SOURCE_COMMENTS)) {
            return;
        }
        $authors = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(legacy_request::SOURCE_COMMENTS, $after, self::PAGE,
                ['id', 'instanceid', 'createdbyid', 'dt', 'message']);
            foreach ($page as $commentid => $comment) {
                $after = (int) $commentid;
                $requestid = legacy_request::comment_request_id($comment->instanceid ?? '');
                if ($requestid > 0) {
                    $this->comments[$requestid][] = $comment;
                    $authors[(int) $comment->createdbyid] = (int) $comment->createdbyid;
                }
            }
        } while (count($page) === self::PAGE);
        $this->authors->ensure($ctx, array_values($authors));
    }

    /**
     * The comment thread of a request as a decision note: "[2026-03-05 14:22] Asha Rao: text" per line, oldest
     * first. Rebuilt from the source every time, so a re-run gives the same note.
     *
     * @param context $ctx
     * @param int $requestid
     * @return string|null Null when the request has no comment with text.
     */
    private function note_for(context $ctx, int $requestid): ?string {
        $thread = $this->comments[$requestid] ?? [];
        if (!$thread) {
            return null;
        }
        usort($thread, fn(\stdClass $a, \stdClass $b): int => [self::when($a->dt ?? null), (int) $a->id]
            <=> [self::when($b->dt ?? null), (int) $b->id]);
        $lines = [];
        foreach ($thread as $comment) {
            $text = self::plain_text($comment->message ?? '');
            if ($text === '') {
                continue;
            }
            $author = $this->authors->get($ctx, (int) ($comment->createdbyid ?? 0));
            $name = $author === null ? 'unknown user' : trim(fullname($author));
            $when = self::when($comment->dt ?? null);
            $lines[] = '[' . ($when === '' ? 'unknown date' : $when) . '] ' . $name . ': ' . $text;
        }
        return $lines ? implode("\n", $lines) : null;
    }

    /**
     * "Y-m-d H:i" from the comment's datetime string (BizLMS stored it as a string in the server's time zone).
     *
     * @param mixed $dt
     * @return string Empty when the value is not a date.
     */
    private static function when(mixed $dt): string {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/', trim((string) $dt), $m)) {
            // MySQL's zero date is how a datetime that was never set reads back: it is no date at all (F-80).
            return $m[1] === '0000-00-00' ? '' : $m[1] . ' ' . $m[2];
        }
        return '';
    }
}
