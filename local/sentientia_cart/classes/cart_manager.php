<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Cart manager — orchestrates the cart lifecycle.
 *
 * Responsibilities:
 * - Open/close cart (one open cart per user at a time)
 * - Add/remove items with snapshot pricing
 * - Compute totals + GST per state rules
 * - Convert cart → order on checkout
 * - Record payment events to ledger
 * - Enrol user in purchased courses on payment success
 * - Issue invoices with sequential GST-compliant numbering
 *
 * @package local_sentientia_cart
 */
class cart_manager {

    /**
     * Is the cart available for this user?
     * Driven by admin setting "enabled_tenants" (CSV of tenant root IDs).
     */
    public static function is_enabled_for_user(\stdClass $user): bool {
        $enabled = get_config('local_sentientia_cart', 'enabled_tenants');
        if ($enabled === '' || $enabled === false) {
            return true;  // empty = all tenants
        }
        $top = self::get_tenant_root($user);
        $allowed = array_map('intval', array_filter(explode(',', $enabled),
            fn($v) => trim($v) !== ''));
        return in_array($top, $allowed, true);
    }

    /**
     * Tenant root from user.open_path. "/1/2/3" → 1, "/77" → 77.
     */
    public static function get_tenant_root(\stdClass $user): int {
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    /**
     * Get (or open) the user's current cart. Always exactly 1 row with
     * status='open' per user.
     */
    public static function get_or_open_cart(int $userid): \stdClass {
        global $DB, $USER;

        $cart = $DB->get_record('local_sentientia_cart_history',
            ['userid' => $userid, 'status' => 'open']);
        if ($cart) {
            return $cart;
        }

        // Tenant scope at creation time (cart sticks to the tenant the user
        // was in when they opened it — protects against tenant moves mid-cart).
        $user = $userid === $USER->id ? $USER : $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        $cart = (object) [
            'userid'          => $userid,
            'costcenterid'    => self::get_tenant_root($user),
            'items_json'      => '[]',
            'subtotal'        => 0,
            'discount_amount' => 0,
            'tax_amount'      => 0,
            'total_amount'    => 0,
            'currency'        => get_config('local_sentientia_cart', 'currency') ?: 'INR',
            'status'          => 'open',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $cart->id = $DB->insert_record('local_sentientia_cart_history', $cart);
        return $cart;
    }

    /**
     * Add a course to the user's cart.
     *
     * @throws \moodle_exception if course not available for purchase
     *                          or user already enrolled
     */
    public static function add_item(int $userid, int $courseid): \stdClass {
        global $DB;

        // Validate course is purchaseable.
        $price = local_sentientia_cart_get_course_price($courseid);
        if ($price === null) {
            throw new \moodle_exception('error_courseunavailable', 'local_sentientia_cart');
        }

        // Already enrolled?
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) {
            throw new \moodle_exception('error_alreadyenrolled', 'local_sentientia_cart');
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', MUST_EXIST);
        $cart = self::get_or_open_cart($userid);

        $items = json_decode($cart->items_json ?: '[]', true) ?: [];

        // Already in cart? Skip (idempotent add).
        foreach ($items as $item) {
            if ((int) $item['courseid'] === $courseid) {
                return $cart;
            }
        }

        $items[] = [
            'courseid'     => (int) $course->id,
            'name'         => $course->fullname,
            'shortname'    => $course->shortname,
            'price'        => (float) $price,
            'discount_pct' => 0,
        ];

        $cart->items_json = json_encode($items);
        return self::recompute_totals($cart);
    }

    /**
     * Remove a course from the cart.
     */
    public static function remove_item(int $userid, int $courseid): \stdClass {
        $cart = self::get_or_open_cart($userid);
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];
        $items = array_values(array_filter($items,
            fn($i) => (int) $i['courseid'] !== $courseid));
        $cart->items_json = json_encode($items);
        return self::recompute_totals($cart);
    }

    /**
     * Recompute subtotal + tax + total from items_json, persist.
     */
    public static function recompute_totals(\stdClass $cart): \stdClass {
        global $DB;
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];

        $subtotal = 0;
        $discount = 0;
        foreach ($items as $item) {
            $itemprice = (float) $item['price'];
            $itemdiscount = $itemprice * ((int) ($item['discount_pct'] ?? 0)) / 100;
            $subtotal += $itemprice;
            $discount += $itemdiscount;
        }

        $taxable = max(0, $subtotal - $discount);
        $gstrate = (float) (get_config('local_sentientia_cart', 'gst_rate') ?: 18);
        $tax = round($taxable * $gstrate / 100, 2);
        $total = round($taxable + $tax, 2);

        $cart->subtotal        = round($subtotal, 2);
        $cart->discount_amount = round($discount, 2);
        $cart->tax_amount      = $tax;
        $cart->total_amount    = $total;
        $cart->timemodified    = time();

        $DB->update_record('local_sentientia_cart_history', $cart);
        return $cart;
    }

    /**
     * Convert cart → order. Reserves a fresh sequential order number, stamps
     * billing details, transitions status open→pending.
     *
     * After this, the cart row IS the order. A fresh cart row will be opened
     * lazily next time the user adds an item.
     */
    public static function checkout(int $userid, array $billing, string $gateway): \stdClass {
        global $DB;
        $cart = self::get_or_open_cart($userid);
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];
        if (empty($items)) {
            throw new \moodle_exception('error_emptycart', 'local_sentientia_cart');
        }

        // Validate billing.
        foreach (['billing_name', 'billing_email'] as $required) {
            if (empty($billing[$required] ?? '')) {
                throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart',
                    '', "Missing $required");
            }
        }

        // Reserve order number atomically.
        $orderid = $DB->insert_record('local_sentientia_cart_id',
            ['userid' => $userid, 'reserved' => time()]);

        $cart->orderid         = $orderid;
        $cart->status          = 'pending';
        $cart->gateway         = $gateway;
        $cart->billing_name    = (string) ($billing['billing_name'] ?? '');
        $cart->billing_email   = (string) ($billing['billing_email'] ?? '');
        $cart->billing_phone   = (string) ($billing['billing_phone'] ?? '');
        $cart->billing_address = (string) ($billing['billing_address'] ?? '');
        $cart->billing_gstn    = (string) ($billing['billing_gstn'] ?? '');
        $cart->timemodified    = time();

        $DB->update_record('local_sentientia_cart_history', $cart);
        return $cart;
    }

    /**
     * Mark an order as paid. Called by gateway webhook OR by manual approval.
     *
     * 1. Inserts ledger row (payment_received)
     * 2. Updates history.status → 'paid', timepaid
     * 3. Enrols user in all course items
     * 4. Issues invoice
     * 5. Sends order_placed + payment_received messages
     *
     * Idempotent — re-calling on an already-paid order is a no-op.
     */
    public static function mark_paid(int $historyid, string $gateway_ref, array $payload = []): bool {
        global $DB;

        $cart = $DB->get_record('local_sentientia_cart_history',
            ['id' => $historyid], '*', MUST_EXIST);

        if ($cart->status === 'paid') {
            return true;  // idempotent
        }
        if (!in_array($cart->status, ['pending', 'failed'], true)) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart');
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();

            // 1. Ledger.
            $DB->insert_record('local_sentientia_cart_ledger', (object) [
                'historyid'   => $cart->id,
                'orderid'     => (int) $cart->orderid,
                'event_type'  => 'payment_received',
                'amount'      => (float) $cart->total_amount,
                'currency'    => $cart->currency,
                'gateway'     => $cart->gateway,
                'gateway_ref' => $gateway_ref,
                'payload_json' => json_encode($payload),
                'timecreated' => $now,
            ]);

            // 2. History update.
            $cart->status       = 'paid';
            $cart->timepaid     = $now;
            $cart->timemodified = $now;
            $cart->gateway_ref  = $gateway_ref;
            $DB->update_record('local_sentientia_cart_history', $cart);

            // 3. Enrol user in each course.
            $items = json_decode($cart->items_json ?: '[]', true) ?: [];
            foreach ($items as $item) {
                self::enrol_user_in_course((int) $cart->userid,
                    (int) $item['courseid']);
            }

            // 4. Issue invoice.
            invoicer::issue_for_order($cart);

            // 5. Send notifications.
            notifier::order_paid($cart);

            $transaction->allow_commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return false;  // unreachable; rollback rethrows
        }
    }

    /**
     * Mark an order as failed (gateway declined / timeout / cancelled).
     */
    public static function mark_failed(int $historyid, string $reason = ''): void {
        global $DB;
        $cart = $DB->get_record('local_sentientia_cart_history',
            ['id' => $historyid], '*', MUST_EXIST);
        if ($cart->status === 'paid') {
            return;  // can't fail a paid order
        }
        $cart->status       = 'failed';
        $cart->timemodified = time();
        $cart->notes        = trim(($cart->notes ?? '') . "\n" . $reason);
        $DB->update_record('local_sentientia_cart_history', $cart);
        notifier::order_failed($cart, $reason);
    }

    /**
     * Issue a refund.
     *
     * @param int    $historyid Order to refund
     * @param float  $amount    Partial amount, OR 0 for full refund
     * @param string $reason
     * @param int    $initiatedby User who clicked refund
     */
    public static function refund(int $historyid, float $amount, string $reason, int $initiatedby): bool {
        global $DB;
        $cart = $DB->get_record('local_sentientia_cart_history',
            ['id' => $historyid], '*', MUST_EXIST);

        if (!in_array($cart->status, ['paid', 'partial_refund'], true)) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart');
        }

        // Tally previously refunded amount.
        $previousrefunds = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(ABS(amount)), 0) FROM {local_sentientia_cart_ledger}
              WHERE historyid = :hid AND event_type IN ('refund_full','refund_partial')",
            ['hid' => $cart->id]);

        $maxrefundable = (float) $cart->total_amount - $previousrefunds;
        if ($amount <= 0) {
            $amount = $maxrefundable;  // 0 = full remaining
        }
        if ($amount > $maxrefundable + 0.01) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart',
                '', 'Refund amount exceeds refundable balance');
        }

        $isfull = abs($amount - $maxrefundable) < 0.01 && abs($previousrefunds) < 0.01;

        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            $DB->insert_record('local_sentientia_cart_ledger', (object) [
                'historyid'   => $cart->id,
                'orderid'     => (int) $cart->orderid,
                'event_type'  => $isfull ? 'refund_full' : 'refund_partial',
                'amount'      => -1 * abs($amount),  // refunds are negative
                'currency'    => $cart->currency,
                'gateway'     => $cart->gateway,
                'initiatedby' => $initiatedby,
                'reason'      => $reason,
                'timecreated' => $now,
            ]);
            $cart->status = $isfull ? 'refunded' : 'partial_refund';
            $cart->timemodified = $now;
            $DB->update_record('local_sentientia_cart_history', $cart);

            // For a full refund, unenrol from purchased courses.
            if ($isfull) {
                $items = json_decode($cart->items_json ?: '[]', true) ?: [];
                foreach ($items as $item) {
                    self::unenrol_user_from_course((int) $cart->userid,
                        (int) $item['courseid']);
                }
            }

            notifier::refund_processed($cart, $amount, $isfull);
            $transaction->allow_commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return false;
        }
    }

    /**
     * Enrol user in a course using Moodle's manual enrol plugin.
     * Idempotent — won't double-enrol.
     */
    private static function enrol_user_in_course(int $userid, int $courseid): void {
        global $DB;
        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $userid)) {
            return;
        }

        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        if (!$instance) {
            // No manual instance — add one so we have somewhere to enrol.
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $manual = enrol_get_plugin('manual');
            $manual->add_default_instance($course);
            $instance = $DB->get_record('enrol',
                ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        }

        $studentroleid = (int) ($DB->get_field('role', 'id',
            ['shortname' => 'student']) ?: 5);
        $enrol->enrol_user($instance, $userid, $studentroleid, time(), 0, ENROL_USER_ACTIVE);
    }

    /**
     * Unenrol user (manual enrolment only — preserves other enrol methods).
     */
    private static function unenrol_user_from_course(int $userid, int $courseid): void {
        global $DB;
        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => 0]);
        if (!$instance) {
            return;
        }
        $plugin = enrol_get_plugin('manual');
        $plugin->unenrol_user($instance, $userid);
    }

    /**
     * Get one order with permission check.
     *
     * Three layers:
     *   1. Owner can always view their own.
     *   2. Other viewers need :viewallorders capability.
     *   3. Phase 8.1 B1 fix — even with the cap, viewer's tenant must
     *      match the order's tenant (or viewer must be site admin).
     *      A manager in Public tenant holding :viewallorders does NOT
     *      get to see Airpay-tenant order details.
     */
    public static function get_order(int $historyid, int $viewerid): \stdClass {
        global $DB;
        $cart = $DB->get_record('local_sentientia_cart_history',
            ['id' => $historyid], '*', MUST_EXIST);
        if ((int) $cart->userid !== $viewerid) {
            $ctx = \context_system::instance();
            if (!is_siteadmin($viewerid)
                && !has_capability('local/sentientia_cart:viewallorders', $ctx, $viewerid)) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
            }
            // ── B1 fix: tenant-equality even when cap held ──────────────
            self::require_order_tenant((int) $cart->costcenterid, $viewerid);
        }
        return $cart;
    }

    /**
     * ADR-031: refuse unless an order / invoice / ledger row of tenant
     * $costcenterid is in the viewer's tenant.
     *
     * :viewallorders and :refund say WHAT the viewer may do; this says WHERE.
     * Only a cross-tenant viewer (site admin or :crosstenant holder) is
     * unscoped. tenant::require_access() alone is not enough: it lets a viewer
     * whose own tenant does not resolve (0) through on every tenant-0 row, i.e.
     * every order placed by another tenantless user. A scoped viewer must have
     * a real tenant, and it must be the row's.
     *
     * @param int $costcenterid the row's tenant root
     * @param int|null $viewerid defaults to the current user
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_order_tenant(int $costcenterid, ?int $viewerid = null): void {
        global $DB, $USER;
        $viewerid = $viewerid ?? (int) $USER->id;
        if (\local_sentientia_platform\tenant::is_cross_tenant($viewerid)) {
            return;
        }
        $viewer = ($viewerid === (int) $USER->id) ? $USER : $DB->get_record('user', ['id' => $viewerid]);
        $root = $viewer ? self::get_tenant_root($viewer) : 0;
        if ($root <= 0 || $root !== $costcenterid) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
        }
    }

    /**
     * ADR-031: refuse unless $course is in the current user's tenant.
     *
     * For writes that name a course (pricing). A course-context capability
     * check does not scope anything for a tenant admin: their manager-archetype
     * role sits at system context and is inherited by every course on the site.
     * A course with no open_path cannot be shown to be in the caller's tenant,
     * so it is refused too (tenant::require_path_access() lets '' through).
     *
     * @param \stdClass $course a course record carrying open_path (may be absent on vanilla Moodle)
     * @throws \moodle_exception error_outoftenant
     */
    public static function require_course_in_tenant(\stdClass $course): void {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return;
        }
        $path = (string) ($course->open_path ?? '');
        if ($path === '') {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
        }
        \local_sentientia_platform\tenant::require_path_access($path);
    }

    /**
     * Courses the current user may price, with their current fee (set_price.php).
     *
     * Tenant-scoped (ADR-031): tenant::path_filter() is '1=1' for a
     * cross-tenant caller, the caller's own tenant tree otherwise, and '1=0'
     * for a caller with no tenant. Until 2026-09-25 this listed every
     * tenant's courses and prices to any :manageprices holder.
     *
     * @param int $limit maximum rows
     * @return \stdClass[] keyed by course id: id, fullname, shortname, price, currency, fee_status
     */
    public static function list_course_prices(int $limit = 200): array {
        global $DB;
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('c');
        return $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname,
                    e.cost AS price, e.currency, e.status AS fee_status
               FROM {course} c
          LEFT JOIN {enrol} e ON e.courseid = c.id AND e.enrol = 'fee'
              WHERE c.id > 1
                AND $tnsql
           ORDER BY c.fullname ASC",
            $tnargs, 0, $limit);
    }

    /**
     * Daily payment / refund sums from the immutable ledger, tenant-scoped.
     *
     * Single source for the daily_sums web service and daily_sums_csv.php.
     * The CSV used to run its own copy of this query without the tenant join,
     * so the scoped page's Export button handed every tenant admin every
     * tenant's totals (ADR-031 sweep, 2026-09-25).
     *
     * Ledger rows carry no tenant; the parent history row does. Cross-tenant
     * viewers get every tenant, scoped viewers their own, and a viewer with no
     * tenant nothing (tenant::sql_filter() fails closed).
     *
     * Bucketed in PHP rather than with DATE(FROM_UNIXTIME()) in SQL: that is
     * MySQL-only (the tenant_isolation CI gate runs on PostgreSQL), and it
     * cut days in the database session's timezone while the from/to bounds
     * are cut in PHP's. Amounts are summed in minor units so two-decimal
     * money does not pick up float drift. The old query was also keyed on
     * `day`, which get_records_sql() collapses when one day has two
     * gateways or currencies.
     *
     * @param int $fromts inclusive unix timestamp
     * @param int $tots   inclusive unix timestamp
     * @return \stdClass[] rows with day, gateway, currency, inflow, outflow, payments, refunds;
     *                     day DESC, then gateway, then currency
     */
    public static function daily_sums(int $fromts, int $tots): array {
        global $DB;
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('h');
        $rs = $DB->get_recordset_sql(
            "SELECT l.id, l.timecreated, l.gateway, l.currency, l.event_type, l.amount
               FROM {local_sentientia_cart_ledger} l
               JOIN {local_sentientia_cart_history} h ON h.id = l.historyid
              WHERE l.timecreated BETWEEN :f AND :t
                AND $tnsql",
            array_merge(['f' => $fromts, 't' => $tots], $tnargs));
        $buckets = [];
        foreach ($rs as $l) {
            $day = date('Y-m-d', (int) $l->timecreated);
            $key = $day . "\0" . $l->gateway . "\0" . $l->currency;
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['day' => $day, 'gateway' => (string) $l->gateway,
                    'currency' => (string) $l->currency, 'in' => 0, 'out' => 0,
                    'payments' => 0, 'refunds' => 0];
            }
            $minor = (int) round((float) $l->amount * 100);
            if ($l->event_type === 'payment_received') {
                $buckets[$key]['in'] += $minor;
                $buckets[$key]['payments']++;
            } else if ($l->event_type === 'refund_full' || $l->event_type === 'refund_partial') {
                $buckets[$key]['out'] += $minor;
                $buckets[$key]['refunds']++;
            }
        }
        $rs->close();

        usort($buckets, fn($a, $b) => [$b['day'], $a['gateway'], $a['currency']]
            <=> [$a['day'], $b['gateway'], $b['currency']]);
        return array_map(fn($b) => (object) [
            'day'      => $b['day'],
            'gateway'  => $b['gateway'],
            'currency' => $b['currency'],
            'inflow'   => $b['in'] / 100,
            'outflow'  => $b['out'] / 100,
            'payments' => $b['payments'],
            'refunds'  => $b['refunds'],
        ], $buckets);
    }
}
