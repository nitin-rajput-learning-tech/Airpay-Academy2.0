<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Push subscription CRUD — Phase B.2.
 *
 * Stores PushSubscription objects (one per browser per user) so the
 * Phase B.2.5 sender can encrypt + POST web-push messages to them.
 *
 * @package local_sentientia_pwa
 */
class subscription_manager {

    /** Failures before a subscription is auto-purged. */
    public const MAX_FAILURES = 5;

    /**
     * Save (upsert) a push subscription for the given user.
     *
     * If a row exists for (userid, endpoint_hash) it's updated (keys may
     * have rotated). Otherwise a new row is inserted.
     *
     * @param int    $userid     User the subscription belongs to
     * @param string $endpoint   Push service URL from PushSubscription.endpoint
     * @param string $p256dh     PushSubscription.getKey('p256dh') as base64url
     * @param string $auth       PushSubscription.getKey('auth') as base64url
     * @param string $user_agent Browser UA at subscribe time (informational)
     * @return int Row ID of the upserted subscription
     */
    public static function save(int $userid, string $endpoint, string $p256dh,
                                 string $auth, string $user_agent = ''): int {
        global $DB;
        if ($userid <= 0) {
            throw new \moodle_exception('invaliduser', 'core');
        }
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_pwa');
        }

        $endpoint_hash = sha1($endpoint);
        $now = time();

        // Derive customerid + tenantid from the user's open_path. Phase 0/1
        // customer is always 1 (Airpay); tenant is first segment of open_path.
        $user = $DB->get_record('user', ['id' => $userid], 'id, open_path', MUST_EXIST);
        $tenantid = 0;
        if (!empty($user->open_path)) {
            $parts = explode('/', trim($user->open_path, '/'));
            if (!empty($parts[0]) && ctype_digit($parts[0])) {
                $tenantid = (int) $parts[0];
            }
        }
        $customerid = 1;  // Phase 0/1: hardcoded Airpay
        if (class_exists('\\local_sentientia_platform\\customer')) {
            $customerid = \local_sentientia_platform\customer::current();
        }

        $existing = $DB->get_record('local_sentientia_push_subs', [
            'userid'        => $userid,
            'endpoint_hash' => $endpoint_hash,
        ]);

        if ($existing) {
            // Update keys (they may have rotated) + reset fail counter.
            $existing->endpoint     = $endpoint;
            $existing->p256dh       = $p256dh;
            $existing->auth_secret  = $auth;
            $existing->user_agent   = mb_substr($user_agent, 0, 255);
            $existing->customerid   = $customerid;
            $existing->tenantid     = $tenantid;
            $existing->fail_count   = 0;
            $existing->timemodified = $now;
            $DB->update_record('local_sentientia_push_subs', $existing);
            return (int) $existing->id;
        }

        $row = new \stdClass();
        $row->userid         = $userid;
        $row->customerid     = $customerid;
        $row->tenantid       = $tenantid;
        $row->endpoint       = $endpoint;
        $row->endpoint_hash  = $endpoint_hash;
        $row->p256dh         = $p256dh;
        $row->auth_secret    = $auth;
        $row->user_agent     = mb_substr($user_agent, 0, 255);
        $row->last_seen      = null;
        $row->fail_count     = 0;
        $row->timecreated    = $now;
        $row->timemodified   = $now;

        return (int) $DB->insert_record('local_sentientia_push_subs', $row);
    }

    /**
     * Delete a subscription by user + endpoint. Called from the WS
     * endpoint when the user clicks "Unsubscribe" or when the browser
     * reports the subscription has been revoked.
     *
     * @param int    $userid
     * @param string $endpoint
     * @return bool True if a row was deleted; false if no match
     */
    public static function delete(int $userid, string $endpoint): bool {
        global $DB;
        if ($userid <= 0 || $endpoint === '') {
            return false;
        }
        return $DB->delete_records('local_sentientia_push_subs', [
            'userid'        => $userid,
            'endpoint_hash' => sha1($endpoint),
        ]);
    }

    /**
     * Return all push subscriptions for a user. Used by the sender to
     * deliver one message to every device the user has subscribed.
     *
     * Audit fix #4 (2026-05-21) — accepts optional customer + tenant
     * filters. Cross-tenant pushes are refused at the sender layer;
     * this method is the read gate. If a manager-level user needs to
     * push across tenants (rare, audit-able), they must construct
     * the call with an explicit tenant override — never the default.
     * See `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #4.
     *
     * @param int      $userid              The recipient user
     * @param int|null $expected_customerid If non-null, only return rows
     *                                      matching this customer
     * @param int|null $expected_tenantid   If non-null, only return rows
     *                                      matching this tenant
     * @return array Array of subscription stdClass objects
     */
    public static function for_user(int $userid,
                                     ?int $expected_customerid = null,
                                     ?int $expected_tenantid = null): array {
        global $DB;
        $conds = ['userid' => $userid];
        if ($expected_customerid !== null) {
            $conds['customerid'] = $expected_customerid;
        }
        if ($expected_tenantid !== null) {
            $conds['tenantid'] = $expected_tenantid;
        }
        return $DB->get_records('local_sentientia_push_subs', $conds,
            'timecreated DESC');
    }

    /**
     * Resolve a user's tenant ID from their `open_path` profile field.
     * Mirrors the BizLMS convention used across local_airpay_*: first
     * segment of `/N/M/...` is the costcenterid (1=Airpay, 77=Public,
     * 177=ZEEA per CLAUDE.md). Returns 0 if open_path is unset/blank.
     *
     * Shared helper so push_sender can do cross-tenant gating without
     * duplicating the parse logic.
     *
     * @param int $userid
     * @return int 0 if unknown, otherwise the tenant integer
     */
    public static function tenant_for_user(int $userid): int {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid],
            'id, open_path, deleted, suspended');
        if (!$user || $user->deleted || $user->suspended) {
            return 0;
        }
        if (empty($user->open_path)) {
            return 0;
        }
        $parts = explode('/', trim($user->open_path, '/'));
        if (!empty($parts[0]) && ctype_digit($parts[0])) {
            return (int) $parts[0];
        }
        return 0;
    }

    /**
     * Same as for_user, but with sensitive key fields stripped — safe to
     * return to the user themselves via WS for "manage my devices" UI.
     *
     * @param int $userid
     * @return array
     */
    public static function for_user_safe(int $userid): array {
        $rows = self::for_user($userid);
        $safe = [];
        foreach ($rows as $r) {
            $safe[] = [
                'id'           => (int) $r->id,
                'user_agent'   => $r->user_agent,
                'endpoint_host' => self::endpoint_host($r->endpoint),
                'last_seen'    => (int) ($r->last_seen ?? 0),
                'fail_count'   => (int) $r->fail_count,
                'timecreated'  => (int) $r->timecreated,
            ];
        }
        return $safe;
    }

    /**
     * Record a successful push delivery for a subscription. Updates
     * last_seen + resets fail_count. Called by the sender after the
     * push service returns 2xx.
     */
    public static function record_success(int $sub_id): void {
        global $DB;
        $DB->update_record('local_sentientia_push_subs', (object) [
            'id'         => $sub_id,
            'last_seen'  => time(),
            'fail_count' => 0,
            'timemodified' => time(),
        ]);
    }

    /**
     * Record a failed push delivery. Increments fail_count and auto-
     * purges the subscription when MAX_FAILURES is reached. Called by
     * the sender on 4xx/5xx response from the push service.
     */
    public static function record_failure(int $sub_id): void {
        global $DB;
        $sub = $DB->get_record('local_sentientia_push_subs',
            ['id' => $sub_id], 'id, fail_count');
        if (!$sub) {
            return;
        }
        $new_count = ((int) $sub->fail_count) + 1;
        if ($new_count >= self::MAX_FAILURES) {
            $DB->delete_records('local_sentientia_push_subs', ['id' => $sub_id]);
            return;
        }
        $DB->update_record('local_sentientia_push_subs', (object) [
            'id'           => $sub_id,
            'fail_count'   => $new_count,
            'timemodified' => time(),
        ]);
    }

    /**
     * Extract just the host part of a push endpoint URL — for showing in
     * the user's "my devices" UI without leaking the full subscription
     * token. fcm.googleapis.com / updates.push.services.mozilla.com etc.
     */
    public static function endpoint_host(string $endpoint): string {
        $parsed = parse_url($endpoint);
        return $parsed['host'] ?? 'unknown';
    }
}
