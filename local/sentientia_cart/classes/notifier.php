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

    public static function order_paid(\stdClass $cart): void {
        self::send_to_user($cart->userid, 'payment_received',
            get_string('ordersuccess', 'local_sentientia_cart'),
            self::body_for_paid($cart));

        // Also notify admins (via capability lookup).
        self::send_to_admins('admin_new_order',
            "New order #{$cart->orderid}",
            self::admin_body($cart));
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

    private static function body_for_paid(\stdClass $cart): string {
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];
        $list = array_map(fn($i) => '  - ' . ($i['name'] ?? ''), $items);
        return "Thank you! Your order #{$cart->orderid} has been confirmed.\n\n"
             . "Courses:\n" . implode("\n", $list) . "\n\n"
             . "Total: {$cart->currency} " . number_format((float) $cart->total_amount, 2) . "\n\n"
             . "You can now access your courses from the catalog.";
    }

    private static function admin_body(\stdClass $cart): string {
        global $DB;
        $u = $DB->get_record('user', ['id' => $cart->userid], 'firstname, lastname, email');
        $name = $u ? ($u->firstname . ' ' . $u->lastname) : 'unknown';
        return "Order #{$cart->orderid} placed by $name ({$u->email}) for "
             . "{$cart->currency} " . number_format((float) $cart->total_amount, 2) . ".";
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
