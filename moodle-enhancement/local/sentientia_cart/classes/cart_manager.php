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
            \local_airpay_core\tenant::require_access(
                (int) $cart->costcenterid, $viewerid);
        }
        return $cart;
    }
}
