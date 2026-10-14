<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The two feature flags of the GST tax invoice and receipt work (spec 2026-10-14, section 2), resolved for an ORDER.
 *
 * A payment callback and the cron task run as no one, and an administrator who opens a buyer's receipt is in a
 * different tenant from the order's; asking "is the flag on for the current user" would give the wrong answer in
 * both. So every question here is asked for the order's own customer and tenant:
 * \local_sentientia_platform\feature_flags::is_enabled_for($key, $customerid, $tenantid), the same resolution the
 * e-mail and webhook senders use (the customer comes from the tenant registry when it is live, else it is Airpay).
 *
 * A lookup that fails (registry not loadable, cache error) reads as OFF, the conservative answer: nothing is issued
 * and nothing new is shown.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flags {

    /** The buyer's payment receipt: page, PDF, links. */
    public const RECEIPTS = 'sentientia.cart.purchase_receipts';

    /** GST tax invoices and credit notes. While OFF nothing is issued. */
    public const INVOICES = 'sentientia.cart.gst_tax_invoices';

    /**
     * Is a flag ON for a tenant root?
     *
     * @param string $key RECEIPTS or INVOICES
     * @param int $costcenterid The tenant root of the order (0 when it has none).
     * @return bool
     */
    public static function enabled_for_tenant(string $key, int $costcenterid): bool {
        try {
            return \local_sentientia_platform\feature_flags::is_enabled_for(
                $key, self::customer_for_root($costcenterid), $costcenterid);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Is the receipt flag ON for the tenant of an order?
     *
     * @param \stdClass $order A history row (costcenterid).
     * @return bool
     */
    public static function receipts_for_order(\stdClass $order): bool {
        return self::enabled_for_tenant(self::RECEIPTS, (int) ($order->costcenterid ?? 0));
    }

    /**
     * Is the invoice flag ON for the tenant of an order?
     *
     * @param \stdClass $order A history row, an invoice or a credit note (costcenterid).
     * @return bool
     */
    public static function invoices_for_order(\stdClass $order): bool {
        return self::enabled_for_tenant(self::INVOICES, (int) ($order->costcenterid ?? 0));
    }

    /**
     * The customer a tenant root belongs to: the registry's answer when the tenant registry is live, else Airpay.
     *
     * @param int $root
     * @return int
     */
    public static function customer_for_root(int $root): int {
        if ($root > 0 && class_exists('\\local_sentientia_core\\tenant_registry')
                && !\local_sentientia_core\tenant_registry::use_legacy_registry()) {
            $customer = (int) \local_sentientia_core\tenant_registry::customer_of($root);
            if ($customer > 0) {
                return $customer;
            }
        }
        return \local_sentientia_platform\customer::AIRPAY;
    }
}
