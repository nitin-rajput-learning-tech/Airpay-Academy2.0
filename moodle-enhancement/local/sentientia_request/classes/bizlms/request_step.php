<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\step;
use local_sentientia_platform\bizlms\tenant_resolver;
use local_sentientia_request\approver_routing;

defined('MOODLE_INTERNAL') || die();

/**
 * What the three request load steps share: the decisions they read, the tenant of a requester and the
 * status, route and approver of a request BizLMS left pending.
 *
 * Every step writes (or folds into) local_sentientia_request, so the target is fixed here.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class request_step extends step {

    /** @var \stdClass|null The owner's decisions, read once per step. */
    private ?\stdClass $settings = null;

    /**
     * @return string
     */
    final public function targettable(): string {
        return legacy_request::TARGET;
    }

    /**
     * The owner decisions this feature reads (docs/cutover/bizlms-import-decisions.json).
     *
     * A decision the file carries with a value the importer cannot carry out blocks here as well as in
     * preflight, so a context built without a preflight still cannot guess.
     *
     * @param context $ctx
     * @return \stdClass pending, stale, decidedroute, hidden, unresolved (strings).
     * @throws blocked
     */
    protected function settings(context $ctx): \stdClass {
        if ($this->settings === null) {
            $settings = (object) [
                'pending' => (string) $ctx->decision('request.pending'),
                'stale' => (string) $ctx->decision('request.pending_stale'),
                'decidedroute' => (string) $ctx->decision('request.decided_route'),
                'hidden' => (string) $ctx->decision('request.hidden_rows'),
                'unresolved' => (string) $ctx->decision('tenant.unresolved.request'),
            ];
            // The decisions that have one carried-out value: classroom and program requests are history only,
            // a certification has no Sentientia entity, the tenant is the requester's current root, legacy
            // pending requests are routed by Sentientia.
            $fixed = [
                'request.pending_classroom_program' => 'history_only',
                'request.certification' => 'unmapped',
                'request.tenant_basis' => 'requester_current_root',
                'request.pending_approver' => 'sentientia_routing',
            ];
            foreach ($fixed as $key => $supported) {
                if ((string) $ctx->decision($key) !== $supported) {
                    throw new blocked('unsupported_decision_value:' . $key);
                }
            }
            // Comments fold into the decision note, signed as fold_into_decision_note; the owner's review of any rows
            // that exist (preflight blocks until then) is fold_reviewed. Both are carried out the same way.
            if (!in_array((string) $ctx->decision('request.comments'), ['fold_into_decision_note', 'fold_reviewed'], true)) {
                throw new blocked('unsupported_decision_value:request.comments');
            }
            $this->settings = $settings;
        }
        return $this->settings;
    }

    /**
     * The tenant root of a requester: the first segment of the requester's CURRENT open_path, when it is a
     * registered tenant. BizLMS stores no tenant on a request (mapping doc section 19, "Tenant rule"), and
     * request_manager::submit() takes it the same way.
     *
     * @param context $ctx
     * @param int $userid
     * @return array{0: int, 1: string} [root, method]: root 0 and method unresolved when there is none.
     */
    protected function tenant_root(context $ctx, int $userid): array {
        $raw = $ctx->lookups->user_path($userid);
        $path = tenant_resolver::normalise($raw);
        if ($path === null) {
            return [0, 'unresolved'];
        }
        $root = (int) explode('/', ltrim($path, '/'))[0];
        try {
            \local_sentientia_platform\tenant::assert_valid($root);
        } catch (\Throwable $e) {
            return [0, 'unresolved'];
        }
        return [$root, $raw === $path ? 'exact' : 'normalised'];
    }

    /**
     * Status, route and approver of a request BizLMS left pending.
     *
     * Decision request.pending: a course or path request stays actionable, routed the way a new one would be
     * (the requester's supervisor, the course owner, the default approver); readonly keeps every one out of
     * every inbox; expired closes them. Classroom, program and certification requests are history only in
     * every case, because request_manager::decide() can enrol into a course or a path and nothing else.
     *
     * Decision request.pending_stale (COMMS-R1): with history_only, a pending course or path request whose requester is
     * deleted or suspended, or whose item is gone, is not routed either. Approving it would enrol and message an
     * account that has left, or point at nothing, and no person can usefully decide it. It keeps its status pending,
     * with route admin and no approver, so it is in nobody's inbox but admins still see it in All requests.
     *
     * @param context $ctx
     * @param \stdClass $settings See settings().
     * @param string $itemtype course, path, classroom, program or certification.
     * @param int $itemid The Sentientia item id.
     * @param int $courseid The course id (0 unless the item is a course).
     * @param \stdClass $user The requester, with open_supervisorid and open_managerid where the site has them, and
     *        deleted and suspended.
     * @param bool $itemgone The item no longer exists (a course that is gone, a path with no map entry).
     * @return array{0: string, 1: string, 2: int|null, 3: string|null} [status, route, approver user id or null,
     *         warning code or null]
     */
    protected function pending_plan(context $ctx, \stdClass $settings, string $itemtype, int $itemid, int $courseid,
                                    \stdClass $user, bool $itemgone = false): array {
        if ($settings->pending === 'expired') {
            return ['expired', approver_routing::ROUTE_ADMIN, null, null];
        }
        if ($settings->pending === 'readonly' || !in_array($itemtype, legacy_request::DECIDABLE, true)) {
            return ['pending', approver_routing::ROUTE_ADMIN, null, null];
        }
        $requesterleft = (int) ($user->deleted ?? 0) === 1 || (int) ($user->suspended ?? 0) === 1;
        if ($settings->stale === 'history_only' && ($requesterleft || $itemgone)) {
            return ['pending', approver_routing::ROUTE_ADMIN, null, 'pending_history_only'];
        }
        // The FULL user row, as request_manager::submit() routes with $USER: the routing reads the supervisor
        // columns from it, and a partial row sends every request to the default approver (mapping doc, section 19,
        // verification corrections). One read per pending course or path request, which are few.
        $full = $ctx->legacy->fetch('user', [(int) $user->id], ['*'])[(int) $user->id] ?? $user;
        [$route, $approver] = $itemtype === 'path'
            ? approver_routing::for_path($full, $itemid)
            : approver_routing::for_course($full, $courseid);
        return ['pending', $route, $approver > 0 ? $approver : null, null];
    }

    /**
     * Plain text from a BizLMS message or rejection note (they came from an editor, so they may hold markup).
     *
     * @param mixed $html
     * @return string Trimmed; empty when there is nothing.
     */
    protected static function plain_text(mixed $html): string {
        return trim(html_to_text((string) $html, 0, false));
    }
}
