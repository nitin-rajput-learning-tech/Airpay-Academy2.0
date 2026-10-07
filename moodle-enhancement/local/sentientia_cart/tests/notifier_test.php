<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The order notifications (classes/notifier.php): every subject and body is a lang string (en and hi), built in the
 * RECIPIENT's own language (owner follow-up, 2026-10-07; fix:cart unresolved item 4).
 *
 * Before, the buyer's and the administrators' subjects and bodies were English literals in the PHP, and the few that
 * were strings came out in whichever session called mark_paid(): a gateway callback runs as no one, an
 * administrator's refund runs as the administrator, so an administrator could be sent the triggering user's
 * language. What this locks in:
 *   1. The messages read as they always did in English (each is built from its lang string; no literal remains).
 *   2. A recipient reads their own language whoever triggered the message; a user with no language set gets the
 *      site default. (The Hindi cases need the Hindi language pack and are skipped where it is not installed.)
 *   3. The en and hi packs carry the same notification keys (100 % parity is enforced for the whole pack by
 *      tools/check-lang-parity.php; this holds the keys the notifier uses).
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_cart\notifier
 */
final class notifier_test extends \advanced_testcase {

    /** The strings the notifier builds its messages from. */
    private const KEYS = [
        'ordersuccess', 'refunddue', 'paid_withheld', 'admin_withheld', 'admin_withheld_amounts',
        'admin_withheld_line', 'admin_withheld_total', 'notify_paid_intro', 'notify_paid_courses',
        'notify_paid_total', 'notify_paid_access', 'notify_failed_subject', 'notify_failed_body',
        'notify_failed_hint', 'notify_refund_subject', 'notify_refund_full', 'notify_refund_partial',
        'notify_admin_subject', 'notify_admin_body', 'notify_admin_unknown_buyer',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** A user with a language. */
    private function user_in(string $lang): \stdClass {
        return $this->getDataGenerator()->create_user(['lang' => $lang]);
    }

    /** An order row as the notifier reads one. */
    private function order(int $userid, array $override = []): \stdClass {
        return (object) ($override + [
            'orderid' => 900123, 'userid' => $userid, 'currency' => 'INR', 'total_amount' => 1180.00,
            'subtotal' => 1000.00, 'discount_amount' => 0, 'tax_amount' => 180.00,
            'items_json' => json_encode([['courseid' => 7, 'name' => 'Payments Basics', 'price' => 1000.0, 'discount_pct' => 0]]),
        ]);
    }

    /** The message of one event sent to one user, from a sink's messages. */
    private function message_to(array $messages, int $userid, string $event): \stdClass {
        $found = array_values(array_filter($messages,
            fn($m) => (int) $m->useridto === $userid && $m->eventtype === $event));
        $this->assertCount(1, $found, "one {$event} message to user {$userid}");
        return $found[0];
    }

    private function hindi_installed(): bool {
        return get_string_manager()->translation_exists('hi', false);
    }

    // ── 1. English reads as it always did ────────────────────────────────

    public function test_the_failed_order_message_is_built_from_strings(): void {
        $buyer = $this->user_in('en');
        $sink = $this->redirectMessages();
        notifier::order_failed($this->order((int) $buyer->id), 'Card declined');
        $messages = $sink->get_messages();
        $sink->close();

        $m = $this->message_to($messages, (int) $buyer->id, 'order_failed');
        $this->assertSame('Order #900123 failed', $m->subject);
        $this->assertSame("Your order could not be processed. Reason: Card declined\n\n"
            . 'Please try again from your cart, or contact support.', $m->fullmessage);
    }

    public function test_the_refund_messages_state_the_amount(): void {
        $buyer = $this->user_in('en');
        $sink = $this->redirectMessages();
        notifier::refund_processed($this->order((int) $buyer->id), 1234.5, true);
        notifier::refund_processed($this->order((int) $buyer->id), 250.0, false);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(2, $messages);
        $this->assertSame('Refund processed', $messages[0]->subject);
        $this->assertSame('Your order #900123 has been fully refunded (INR 1,234.50).', $messages[0]->fullmessage);
        $this->assertSame('A partial refund of INR 250.00 has been processed for order #900123.', $messages[1]->fullmessage);
    }

    public function test_the_paid_messages_read_as_they_always_did(): void {
        $buyer = $this->user_in('en');
        $sink = $this->redirectMessages();
        notifier::order_paid($this->order((int) $buyer->id));
        $messages = $sink->get_messages();
        $sink->close();

        $tobuyer = $this->message_to($messages, (int) $buyer->id, 'payment_received');
        $this->assertSame('Your order has been placed successfully.', $tobuyer->subject);
        $this->assertSame("Thank you! Your order #900123 has been confirmed.\n\n"
            . "Courses:\n  - Payments Basics\n\n"
            . "Total: INR 1,180.00\n\n"
            . 'You can now access your courses from the catalog.', $tobuyer->fullmessage);

        $toadmins = array_values(array_filter($messages, fn($m) => $m->eventtype === 'admin_new_order'));
        $this->assertCount(count(get_admins()), $toadmins);
        $this->assertSame('New order #900123', $toadmins[0]->subject);
        $this->assertStringContainsString('Order #900123 placed by ' . $buyer->firstname . ' ' . $buyer->lastname
            . ' (' . $buyer->email . ') for INR 1,180.00.', $toadmins[0]->fullmessage);
        $this->assertStringNotContainsString('Refund due', $toadmins[0]->subject . $toadmins[0]->fullmessage);
    }

    public function test_an_order_of_a_user_who_no_longer_exists_names_an_unknown_buyer_to_the_admins(): void {
        $sink = $this->redirectMessages();
        notifier::order_paid($this->order(987654321));
        $messages = $sink->get_messages();
        $sink->close();

        $toadmins = array_values(array_filter($messages, fn($m) => $m->eventtype === 'admin_new_order'));
        $this->assertNotEmpty($toadmins);
        $this->assertStringContainsString('placed by unknown ()', $toadmins[0]->fullmessage);
        $this->assertSame([], array_values(array_filter($messages, fn($m) => $m->eventtype === 'payment_received')),
            'There is no buyer to tell.');
    }

    // ── 2. The recipient's own language ──────────────────────────────────

    public function test_each_recipient_reads_their_own_language_whoever_triggered_it(): void {
        if (!$this->hindi_installed()) {
            $this->markTestSkipped('The Hindi language pack is not installed in this environment.');
        }
        global $DB;
        $buyer = $this->user_in('hi');
        $admin = get_admin();
        $DB->set_field('user', 'lang', 'en', ['id' => $admin->id]);
        // The session that triggers the message is the (English) administrator, as with a manual approval.
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        notifier::order_paid($this->order((int) $buyer->id));
        $messages = $sink->get_messages();
        $sink->close();

        $sm = get_string_manager();
        $tobuyer = $this->message_to($messages, (int) $buyer->id, 'payment_received');
        $this->assertSame($sm->get_string('ordersuccess', 'local_sentientia_cart', null, 'hi'), $tobuyer->subject);
        $this->assertNotSame($sm->get_string('ordersuccess', 'local_sentientia_cart', null, 'en'), $tobuyer->subject,
            'The Hindi buyer is not sent the English a triggering administrator reads.');
        $this->assertStringContainsString($sm->get_string('notify_paid_intro', 'local_sentientia_cart', 900123, 'hi'),
            $tobuyer->fullmessage);

        $toadmin = $this->message_to($messages, (int) $admin->id, 'admin_new_order');
        $this->assertSame('New order #900123', $toadmin->subject, 'The English administrator reads English.');
    }

    public function test_a_hindi_administrator_triggering_an_english_buyers_message_does_not_change_the_buyers_language(): void {
        if (!$this->hindi_installed()) {
            $this->markTestSkipped('The Hindi language pack is not installed in this environment.');
        }
        global $DB;
        $buyer = $this->user_in('en');
        $admin = get_admin();
        $DB->set_field('user', 'lang', 'hi', ['id' => $admin->id]);
        $this->setAdminUser();

        $sink = $this->redirectMessages();
        notifier::refund_processed($this->order((int) $buyer->id), 100.0, false);
        notifier::order_paid($this->order((int) $buyer->id));
        $messages = $sink->get_messages();
        $sink->close();

        $refund = $this->message_to($messages, (int) $buyer->id, 'refund_processed');
        $this->assertSame('Refund processed', $refund->subject, 'The English buyer is not sent Hindi because an administrator is.');
        $toadmin = $this->message_to($messages, (int) $admin->id, 'admin_new_order');
        $this->assertSame(get_string_manager()->get_string('notify_admin_subject', 'local_sentientia_cart', 900123, 'hi'),
            $toadmin->subject, 'The Hindi administrator reads Hindi.');
    }

    public function test_a_user_with_no_language_gets_the_site_default(): void {
        global $DB, $CFG;
        $nolang = $this->user_in('en');
        $DB->set_field('user', 'lang', '', ['id' => $nolang->id]);

        $sink = $this->redirectMessages();
        notifier::refund_processed($this->order((int) $nolang->id), 10.0, false);
        $messages = $sink->get_messages();
        $sink->close();

        $default = get_string_manager()->get_string('notify_refund_subject', 'local_sentientia_cart', null,
            (string) ($CFG->lang ?: 'en'));
        $this->assertSame($default, $this->message_to($messages, (int) $nolang->id, 'refund_processed')->subject);
        $this->assertSame('Refund processed', $default, 'The test site default is English.');
    }

    // ── 3. The packs carry the same keys ─────────────────────────────────

    public function test_en_and_hi_both_define_every_string_the_notifier_uses(): void {
        $dir = \core_component::get_component_directory('local_sentientia_cart');
        $string = [];
        include($dir . '/lang/en/local_sentientia_cart.php');
        $en = $string;
        $string = [];
        include($dir . '/lang/hi/local_sentientia_cart.php');
        $hi = $string;

        foreach (self::KEYS as $key) {
            $this->assertArrayHasKey($key, $en, "en: {$key}");
            $this->assertArrayHasKey($key, $hi, "hi: {$key}");
            $this->assertNotSame('', trim($hi[$key]), "hi: {$key} is not empty");
            // The placeholders of the English string must all be in the Hindi one, or the message loses a value.
            preg_match_all('/\{\$a(?:->\w+)?\}/', $en[$key], $placeholders);
            foreach ($placeholders[0] as $placeholder) {
                $this->assertStringContainsString($placeholder, $hi[$key], "hi: {$key} keeps {$placeholder}");
            }
        }
    }

    public function test_the_notifier_has_no_english_literal_left_in_a_subject_or_body(): void {
        $source = (string) file_get_contents(
            \core_component::get_component_directory('local_sentientia_cart') . '/classes/notifier.php');
        // Strip comments, then look for the sentences that used to be literals.
        $code = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $source);
        foreach (['Thank you!', 'could not be processed', 'has been fully refunded', 'A partial refund of',
                'You can now access', 'New order #', 'Refund processed', 'placed by'] as $sentence) {
            $this->assertStringNotContainsString($sentence, $code, "'{$sentence}' must come from a lang string");
        }
    }
}
