<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\webhooks;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns platform events into queued webhook deliveries (ADR-030 Wave A).
 *
 * Gate: BOTH sentientia.api.enabled and sentientia.api.webhooks.enabled must
 * resolve ON for the affected user's (customer, tenant) — resolved through the
 * platform's 5-level flag resolver with server-to-server semantics (no $USER),
 * the same shape as ADR-029's keka_client gate. Fails closed when the platform
 * plugin is absent. Never performs HTTP itself — it only inserts queue rows.
 *
 * @package local_sentientia_api
 */
class dispatcher {

    /** @var string */
    public const FLAG_MASTER = 'sentientia.api.enabled';

    /** @var string */
    public const FLAG_WEBHOOKS = 'sentientia.api.webhooks.enabled';

    /**
     * Are outbound webhooks ON for this tenant root (0 = global scope)?
     *
     * @param int $costcenterid
     * @return bool
     */
    public static function enabled_for(int $costcenterid): bool {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        $customerid = self::customer_of($costcenterid);
        $ff = '\local_sentientia_platform\feature_flags';
        return $ff::is_enabled_for(self::FLAG_MASTER, $customerid, $costcenterid)
            && $ff::is_enabled_for(self::FLAG_WEBHOOKS, $customerid, $costcenterid);
    }

    /**
     * Customer id for a tenant root via the ADR-021 registry; 0 when unknown.
     *
     * @param int $costcenterid
     * @return int
     */
    public static function customer_of(int $costcenterid): int {
        if ($costcenterid > 0 && class_exists('\local_sentientia_core\tenant_registry')) {
            try {
                return (int) \local_sentientia_core\tenant_registry::customer_of($costcenterid);
            } catch (\Throwable $e) {
                return 0;
            }
        }
        return 0;
    }

    /**
     * Tenant root of a user (0 when unresolvable — vanilla schema, deleted user).
     *
     * @param int $userid
     * @return int
     */
    public static function tenant_of_user(int $userid): int {
        if ($userid <= 0 || !class_exists('\local_sentientia_platform\tenant')) {
            return 0;
        }
        try {
            $user = \core_user::get_user($userid);
            if (!$user) {
                return 0;
            }
            return (int) \local_sentientia_platform\tenant::root_for_user($user);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Queue one delivery per matching subscription. Returns rows queued.
     *
     * @param string $eventkey     One of subscription::EVENTS
     * @param int    $costcenterid Tenant root of the affected user (0 = unknown/global)
     * @param int    $userid       Affected user (privacy bookkeeping)
     * @param array  $data         Minimal event data (ids + timestamps only)
     * @return int
     */
    public static function enqueue(string $eventkey, int $costcenterid, int $userid, array $data): int {
        global $DB;
        if (!in_array($eventkey, subscription::EVENTS, true)) {
            return 0;
        }
        if (!self::enabled_for($costcenterid)) {
            return 0;
        }
        $subs = subscription::matching($eventkey, $costcenterid);
        if (!$subs) {
            return 0;
        }
        $now = time();
        $body = json_encode([
            'schema'      => 'sentientia.webhook.v1',
            'event'       => $eventkey,
            'occurred_at' => $now,
            'tenant'      => $costcenterid,
            'data'        => $data,
        ], JSON_UNESCAPED_SLASHES);

        $queued = 0;
        foreach ($subs as $sub) {
            $DB->insert_record(queue::TABLE, (object) [
                'subid'       => (int) $sub->id,
                'userid'      => $userid,
                'eventkey'    => $eventkey,
                'payload'     => $body,
                'status'      => queue::STATUS_QUEUED,
                'attempts'    => 0,
                'nextattempt' => $now,
                'httpstatus'  => 0,
                'lasterror'   => null,
                'timecreated' => $now,
                'timeupdated' => $now,
            ]);
            $queued++;
        }
        return $queued;
    }
}
