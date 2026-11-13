<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\event;

defined('MOODLE_INTERNAL') || die();

/**
 * An order became paid.
 *
 * Decision A5 (2026-11-12): \local_sentientia_platform\audit_log::SENSITIVE_EVENTS has named this class since the
 * audit dashboard was built, but nothing created it, so no payment ever reached the audit trail. It is triggered once,
 * by cart_manager::mark_paid(), after the payment's transaction has committed: never for a refused order, an imported
 * order, or the idempotent replay of an order that is already paid. The standard logstore records it.
 *
 * The object is the order (local_sentientia_cart_history). The context is the system: one order can hold courses of
 * several categories, so no single course owns it. relateduserid is the buyer; userid is whoever ran the request
 * (0 for the gateway callback, which runs as no one).
 *
 * Payload contract, `other` (ids and codes only, no personal data):
 *   orderid   int     The order number (local_sentientia_cart_id.id).
 *   amount    int     The total charged, in hundredths of the currency unit (paise for INR); the cart stores two
 *                     decimals.
 *   currency  string  The ISO 4217 code of the order, e.g. INR.
 *   withheld  int     How many lines were paid for but not enrolled (ADR-031 decision 3): a refund is due for them.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order_paid extends \core\event\base {

    /**
     * Build the event for an order that mark_paid() has just recorded as paid.
     *
     * @param \stdClass $cart The order row, as mark_paid() left it (id, userid, orderid, total_amount, currency).
     * @param int $withheld How many of its lines were withheld.
     * @return self
     */
    public static function create_from_order(\stdClass $cart, int $withheld = 0): self {
        return self::create([
            'context'       => \context_system::instance(),
            'objectid'      => (int) $cart->id,
            'relateduserid' => (int) $cart->userid,
            'other'         => [
                'orderid'  => (int) ($cart->orderid ?? 0),
                'amount'   => (int) round((float) $cart->total_amount * 100),
                'currency' => (string) $cart->currency,
                'withheld' => max(0, $withheld),
            ],
        ]);
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_cart_history';
    }

    /**
     * The event's name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventorderpaid', 'local_sentientia_cart');
    }

    /**
     * Plain-English description, ids only.
     *
     * @return string
     */
    public function get_description(): string {
        $actor = $this->userid ? "the user with id '{$this->userid}'" : 'the payment gateway (no logged-in user)';
        return "The order with id '{$this->objectid}' (order number " . (int) ($this->other['orderid'] ?? 0)
            . ") of the user with id '{$this->relateduserid}' was marked paid by $actor: "
            . (int) ($this->other['amount'] ?? 0) . ' hundredths of ' . ($this->other['currency'] ?? '')
            . ', ' . (int) ($this->other['withheld'] ?? 0) . ' line(s) withheld.';
    }

    /**
     * The orders page of the administrators.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_cart/admin_orders.php');
    }

    /**
     * Orders are not part of a course backup, so the object cannot be mapped on restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_sentientia_cart_history', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * The order number is not part of a course backup either. amount, currency and withheld are not ids.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'orderid' => ['db' => 'local_sentientia_cart_id', 'restore' => \core\event\base::NOT_MAPPED],
        ];
    }

    /**
     * Refuse an event that lacks the other values the audit trail needs.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        foreach (['orderid', 'amount', 'currency'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("order_paid: the '$key' value must be set in other.");
            }
        }
    }
}
