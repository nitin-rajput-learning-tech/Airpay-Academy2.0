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
 * Every subject and body is a lang string (en and hi) built in the RECIPIENT's own language, not the language of
 * whichever session called mark_paid(): a gateway callback runs as no one, an administrator's refund runs as the
 * administrator, and a message the buyer reads must not come out in the admin's language (and the other way round).
 * The recipient's `lang` is applied through the string manager, which changes no session state; a user with no
 * language set gets the site default.
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
     * cart.withheld_line_refund (2026-10-07): the admin message also states what each withheld
     * line was charged (cart_manager::withheld_line_amounts(): price, discount and GST share, paise rounding),
     * marked "for review". It refunds nothing: the administrator decides, through a partial refund().
     *
     * @param \stdClass $cart the order row, already marked paid
     * @param int[] $withheld course ids paid for but not enrolled (empty: all granted)
     */
    public static function order_paid(\stdClass $cart, array $withheld = []): void {
        $withheld = array_values(array_unique(array_map('intval', $withheld)));
        $amounts = $withheld ? cart_manager::withheld_line_amounts($cart, $withheld) : [];

        self::send_to_user((int) $cart->userid, 'payment_received', function (string $lang) use ($cart, $withheld): array {
            return [
                self::str('ordersuccess', null, $lang),
                self::body_for_paid($cart, $withheld, $lang),
            ];
        });

        self::send_to_admins('admin_new_order', function (string $lang) use ($cart, $withheld, $amounts): array {
            $subject = self::str('notify_admin_subject', (int) $cart->orderid, $lang);
            if ($withheld) {
                $subject .= ' - ' . self::str('refunddue', null, $lang);
            }
            return [$subject, self::admin_body($cart, $withheld, $amounts, $lang)];
        });
    }

    public static function order_failed(\stdClass $cart, string $reason): void {
        self::send_to_user((int) $cart->userid, 'order_failed', function (string $lang) use ($cart, $reason): array {
            return [
                self::str('notify_failed_subject', (int) $cart->orderid, $lang),
                self::str('notify_failed_body', $reason, $lang) . "\n\n" . self::str('notify_failed_hint', null, $lang),
            ];
        });
    }

    public static function refund_processed(\stdClass $cart, float $amount, bool $isfull): void {
        self::send_to_user((int) $cart->userid, 'refund_processed', function (string $lang) use ($cart, $amount, $isfull): array {
            $a = (object) [
                'orderid'  => (int) $cart->orderid,
                'currency' => (string) $cart->currency,
                'amount'   => number_format($amount, 2),
            ];
            return [
                self::str('notify_refund_subject', null, $lang),
                self::str($isfull ? 'notify_refund_full' : 'notify_refund_partial', $a, $lang),
            ];
        });
    }

    /**
     * One string of this plugin in one language.
     *
     * @param string $id string identifier
     * @param mixed $a placeholders
     * @param string $lang language code
     * @return string
     */
    private static function str(string $id, mixed $a, string $lang): string {
        return get_string_manager()->get_string($id, 'local_sentientia_cart', $a, $lang);
    }

    /**
     * The language a user reads in: their own, else the site default.
     *
     * @param \stdClass $user
     * @return string
     */
    private static function lang_of(\stdClass $user): string {
        global $CFG;
        return !empty($user->lang) ? (string) $user->lang : ((string) ($CFG->lang ?? '') ?: 'en');
    }

    /**
     * The buyer's payment_received body. Lists only the courses they were
     * enrolled in; a withheld course is not named, only counted, with the
     * "cannot be accessed, will be refunded" line. No amounts: the figures to
     * refund are for the administrator (admin_body()).
     *
     * @param \stdClass $cart
     * @param int[] $withheld course ids not enrolled
     * @param string $lang the buyer's language
     * @return string plain text
     */
    private static function body_for_paid(\stdClass $cart, array $withheld, string $lang): string {
        $items = json_decode($cart->items_json ?: '[]', true) ?: [];
        $granted = array_filter($items,
            fn($i) => !in_array((int) ($i['courseid'] ?? 0), $withheld, true));

        $body = self::str('notify_paid_intro', (int) $cart->orderid, $lang) . "\n\n";
        if ($granted) {
            $list = array_map(fn($i) => '  - ' . ($i['name'] ?? ''), $granted);
            $body .= self::str('notify_paid_courses', null, $lang) . "\n" . implode("\n", $list) . "\n\n";
        }
        if ($withheld) {
            $body .= self::str('paid_withheld', count($withheld), $lang) . "\n\n";
        }
        $body .= self::str('notify_paid_total', (object) [
            'currency' => (string) $cart->currency,
            'amount'   => number_format((float) $cart->total_amount, 2),
        ], $lang);
        if ($granted) {
            $body .= "\n\n" . self::str('notify_paid_access', null, $lang);
        }
        return $body;
    }

    /**
     * The admin_new_order body; with an explicit refund-due line when
     * mark_paid() withheld any line, and what each withheld line was charged.
     *
     * @param \stdClass $cart
     * @param int[] $withheld course ids not enrolled
     * @param array[] $amounts cart_manager::withheld_line_amounts() of those lines
     * @param string $lang the administrator's language
     * @return string plain text
     */
    private static function admin_body(\stdClass $cart, array $withheld, array $amounts, string $lang): string {
        global $DB;
        $u = $DB->get_record('user', ['id' => $cart->userid], 'firstname, lastname, email');
        $currency = (string) $cart->currency;
        $body = self::str('notify_admin_body', (object) [
            'orderid'  => (int) $cart->orderid,
            'name'     => $u ? ($u->firstname . ' ' . $u->lastname) : self::str('notify_admin_unknown_buyer', null, $lang),
            'email'    => $u ? $u->email : '',
            'currency' => $currency,
            'amount'   => number_format((float) $cart->total_amount, 2),
        ], $lang);
        if ($withheld) {
            $body .= "\n\n" . self::str('admin_withheld', (object) [
                'orderid'   => (int) $cart->orderid,
                'courseids' => implode(', ', $withheld),
            ], $lang);
        }
        if ($amounts) {
            $lines = [self::str('admin_withheld_amounts', null, $lang)];
            $sum = 0.0;
            foreach ($amounts as $row) {
                $sum += $row['total'];
                $lines[] = self::str('admin_withheld_line', (object) [
                    'courseid' => $row['courseid'],
                    'name'     => $row['name'],
                    'price'    => number_format($row['price'], 2),
                    'discount' => number_format($row['discount'], 2),
                    'tax'      => number_format($row['tax'], 2),
                    'total'    => number_format($row['total'], 2),
                    'currency' => $currency,
                ], $lang);
            }
            $lines[] = self::str('admin_withheld_total', (object) [
                'total'      => number_format(round($sum, 2), 2),
                'ordertotal' => number_format((float) $cart->total_amount, 2),
                'currency'   => $currency,
            ], $lang);
            $body .= "\n\n" . implode("\n", $lines);
        }
        return $body;
    }

    /**
     * Send one message, composed in the recipient's language.
     *
     * @param int $userid recipient
     * @param string $event message provider name
     * @param \Closure $compose function (string $lang): array{0: string, 1: string}, the subject and the body
     */
    private static function send_to_user(int $userid, string $event, \Closure $compose): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*');
        if (!$user) {
            return;
        }
        [$subject, $body] = $compose(self::lang_of($user));
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

    /**
     * Send one message to every site admin, each composed in that admin's own language.
     *
     * @param string $event message provider name
     * @param \Closure $compose function (string $lang): array{0: string, 1: string}
     */
    private static function send_to_admins(string $event, \Closure $compose): void {
        $admins = get_admins();
        foreach ($admins as $admin) {
            self::send_to_user((int) $admin->id, $event, $compose);
        }
    }
}
