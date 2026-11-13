<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\event;

defined('MOODLE_INTERNAL') || die();

/**
 * A refund of an order was processed.
 *
 * Decision A5 (2026-11-12): \local_sentientia_platform\audit_log::SENSITIVE_EVENTS has named this class since the
 * audit dashboard was built, but nothing created it, so no refund ever reached the audit trail. It is triggered once
 * per refund, by cart_manager::refund(), after the refund's transaction has committed (the ledger row is written and
 * the order's status changed): never for a refused refund (wrong status, more than the balance, an imported order).
 * The standard logstore records it.
 *
 * The object is the order (local_sentientia_cart_history) whose status the refund changes; the ledger row the refund
 * wrote is in `other`. The context is the system: one order can hold courses of several categories. relateduserid is
 * the buyer; userid is the user the ledger records as having initiated the refund (cart_manager::refund()'s
 * $initiatedby, which the refund_order web service sets to the caller).
 *
 * Payload contract, `other` (ids and codes only, no personal data; the free-text reason is NOT carried):
 *   ledgerid  int     The refund row in local_sentientia_cart_ledger.
 *   orderid   int     The order number (local_sentientia_cart_id.id).
 *   amount    int     The amount refunded, positive, in hundredths of the currency unit (paise for INR).
 *   currency  string  The ISO 4217 code of the order, e.g. INR.
 *   status    string  The order's status after the refund: refunded (full) or partial_refund.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refund_processed extends \core\event\base {

    /**
     * Build the event for a refund that refund() has just committed.
     *
     * @param \stdClass $cart The order row, as refund() left it (id, userid, orderid, currency, the new status).
     * @param int $ledgerid The ledger row of the refund.
     * @param float $amount The amount refunded (positive, in currency units).
     * @param int $initiatedby The user the ledger records as having initiated it.
     * @return self
     */
    public static function create_from_refund(\stdClass $cart, int $ledgerid, float $amount, int $initiatedby): self {
        return self::create([
            'context'       => \context_system::instance(),
            'objectid'      => (int) $cart->id,
            'userid'        => $initiatedby,
            'relateduserid' => (int) $cart->userid,
            'other'         => [
                'ledgerid' => $ledgerid,
                'orderid'  => (int) ($cart->orderid ?? 0),
                // Rounded to two decimals first, as the ledger's DECIMAL column stores it, so a caller's float with
                // more decimals (refund_order takes PARAM_FLOAT) logs the same paise the ledger row holds.
                'amount'   => (int) round(round(abs($amount), 2) * 100),
                'currency' => (string) $cart->currency,
                'status'   => (string) $cart->status,
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
        return get_string('eventrefundprocessed', 'local_sentientia_cart');
    }

    /**
     * Plain-English description, ids only.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' refunded "
            . (int) ($this->other['amount'] ?? 0) . ' hundredths of ' . ($this->other['currency'] ?? '')
            . " on the order with id '{$this->objectid}' (order number " . (int) ($this->other['orderid'] ?? 0)
            . ") of the user with id '{$this->relateduserid}'. Ledger row " . (int) ($this->other['ledgerid'] ?? 0)
            . '; the order is now ' . ($this->other['status'] ?? '') . '.';
    }

    /**
     * The orders page of the administrators, where refunds are made.
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
     * Neither the ledger row nor the order number is part of a course backup. amount, currency and status are not
     * ids.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'ledgerid' => ['db' => 'local_sentientia_cart_ledger', 'restore' => \core\event\base::NOT_MAPPED],
            'orderid'  => ['db' => 'local_sentientia_cart_id', 'restore' => \core\event\base::NOT_MAPPED],
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
        foreach (['ledgerid', 'orderid', 'amount', 'currency', 'status'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("refund_processed: the '$key' value must be set in other.");
            }
        }
    }
}
