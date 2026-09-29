<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Notifier — sends order lifecycle messages via Moodle message_send().
 *
 * Each method maps to a message provider in db/messages.php so users can
 * customise via Preferences → Notifications.
 *
 * NOTE: noemailever blocks SMTP on local dev — we still write to the
 * `mdl_notifications` table which is what the UI inbox + UAT-L3 checks.
 */
class notifier {

    /**
     * A payment was confirmed: tell the buyer, and the site admins.
     *
     * ADR-031 decision 3: cart_manager::mark_paid() withholds enrolment for a
     * line the buyer may no longer buy, but the money was still taken. Until
     * 2026-09-29 the only trace of that was "Refund due" in history.notes,
     * which nothing displayed, while this message told the buyer every course
     * was theirs. Now the buyer's message lists only the courses they were
     * enrolled in and says the rest cannot be accessed and will be refunded,
     * and the admin_new_order message (sent to the site admins, get_admins())
     * carries a refund-due line naming the order and the withheld course ids.
     *
     * @param \stdClass $cart the order row, already marked paid
     * @param int[] $withheld course ids paid for but not enrolled (empty: all granted)
     */
    public static function order_paid(\stdClass $cart, array $withheld = []): void {
        $withheld = array_values(array_unique(array_map('intval', $withheld)));

        self::send_to_user((int) $cart->userid, 'payment_received',
            get_string('ordersuccess', 'local_sentientia_cart'),
            self::body_for_paid($cart, $withheld));

        $subject = "New order #{$cart->orderid}";
        if ($withheld) {
            $subject .= ' - ' . get_string('refunddue', 'local_sentientia_cart');
        }
        self::send_to_admins('admin_new_order', $subject, self::admin_body($cart, $withheld));
    }

    public static function order_failed(\stdClass $cart, string $reason): void {
        self::send_to_user($cart->userid, 'order_failed',
            "Order #{$cart->orderid} failed",
            "Your order could not be processed. Reason: $reason\n\n"
            . "Please try again from your cart, or contact support.");
    }

    public static function refund_processed(\stdClass $cart, float $amount, bool $isfull): void {
        $body = $isfull
            ? "Your order #{$cart->orderid} has been fully refunded ({$cart->currency} "
              . number_format($amount, 2) . ")."
            : "A partial refund of {$cart->currency} " . number_format($amount, 2)
              . " has been processed for order #{$cart->orderid}.";
        self::send_to_user($cart->userid, 'refund_processed',
            'Refund processed', $body);
    }

    /**
     * The buyer's payment_received body. Lists only the courses they were
     * enrolled in; a withheld course is not named, only counted, with the
     * "cannot be accessed, will be refunded" line.
     *
     * @param \stdClass $cart
     * @param int[] $withheld course ids not enrolled
     * @return string plain text
     */
    private static function body_for_paid(\stdClass $cart, array $withheld = []): string {
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];
        $granted = array_filter($items,
            fn($i) => !in_array((int) ($i['courseid'] ?? 0), $withheld, true));

        $body = "Thank you! Your order #{$cart->orderid} has been confirmed.\n\n";
        if ($granted) {
            $list = array_map(fn($i) => '  - ' . ($i['name'] ?? ''), $granted);
            $body .= "Courses:\n" . implode("\n", $list) . "\n\n";
        }
        if ($withheld) {
            $body .= get_string('paid_withheld', 'local_sentientia_cart', count($withheld)) . "\n\n";
        }
        $body .= "Total: {$cart->currency} " . number_format((float) $cart->total_amount, 2);
        if ($granted) {
            $body .= "\n\nYou can now access your courses from the catalog.";
        }
        return $body;
    }

    /**
     * The admin_new_order body; with an explicit refund-due line when
     * mark_paid() withheld any line.
     *
     * @param \stdClass $cart
     * @param int[] $withheld course ids not enrolled
     * @return string plain text
     */
    private static function admin_body(\stdClass $cart, array $withheld = []): string {
        global $DB;
        $u = $DB->get_record('user', ['id' => $cart->userid], 'firstname, lastname, email');
        $name = $u ? ($u->firstname . ' ' . $u->lastname) : 'unknown';
        $email = $u ? $u->email : '';
        $body = "Order #{$cart->orderid} placed by $name ($email) for "
             . "{$cart->currency} " . number_format((float) $cart->total_amount, 2) . ".";
        if ($withheld) {
            $body .= "\n\n" . get_string('admin_withheld', 'local_sentientia_cart', (object) [
                'orderid'   => (int) $cart->orderid,
                'courseids' => implode(', ', $withheld),
            ]);
        }
        return $body;
    }

    private static function send_to_user(int $userid, string $event, string $subject, string $body): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*');
        if (!$user) {
            return;
        }
        $message = new \core\message\message();
        $message->component         = 'local_sentientia_cart';
        $message->name              = $event;
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user;
        $message->subject           = $subject;
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = nl2br(s($body));
        $message->smallmessage      = $subject;
        $message->notification      = 1;
        message_send($message);
    }

    private static function send_to_admins(string $event, string $subject, string $body): void {
        global $DB;
        $admins = get_admins();
        foreach ($admins as $admin) {
            self::send_to_user((int) $admin->id, $event, $subject, $body);
        }
    }
}
