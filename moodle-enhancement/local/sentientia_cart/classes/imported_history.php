<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * What the cart shows of the history imported from BizLMS (ADR-032).
 *
 * The cart importer (classes/bizlms/) copies the BizLMS orders, ledger, invoices and credit journal into this
 * plugin's tables, as frozen money history (decisions cart.imported_visibility = admin_only,
 * cart.admin_refund_imported_orders = false). The import itself has no user-visible surface and never flips a
 * flag. What anybody SEES of those rows is a reader change, and CLAUDE.md requires a default-OFF flag for every
 * one:
 *
 *  - sentientia.cart.imported_orders.enabled    order administrators see imported orders, their ledger rows and
 *    invoice references (the order list, the order detail, the invoice view, the daily sums).
 *  - sentientia.cart.imported_credits.enabled   the admin credit page lists imported credit bookings and balances.
 *
 * With a flag OFF the cart answers exactly as it did before the import. With it ON an order administrator sees
 * the rows inside their own tenant (a row whose tenant could not be resolved is for cross-tenant administrators
 * only), and the OWNER of an imported order never sees it, whatever else they hold: it is admin-only history.
 *
 * Every reader asks this class, so the rule lives in one place. A flag lookup that fails (registry not loadable,
 * cache error) reads as OFF: the conservative answer.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class imported_history {

    /** Flag: order administrators see imported orders, ledger rows and invoice references. */
    public const FLAG_ORDERS = 'sentientia.cart.imported_orders.enabled';

    /** Flag: the admin credit page. */
    public const FLAG_CREDITS = 'sentientia.cart.imported_credits.enabled';

    /** legacy_source of an imported order. */
    public const SOURCE = 'bizlms';

    /** Plugin config key holding the highest order number an imported order uses (set by the importer's finalise()). */
    public const ORDER_FLOOR_CONFIG = 'bizlms_order_floor';

    /** Invoice status of an imported ERPNext reference. */
    public const INVOICE_STATUS = 'legacy_external';

    /**
     * May order administrators see imported orders, ledger rows and invoice references?
     *
     * @return bool
     */
    public static function orders_enabled(): bool {
        return self::flag(self::FLAG_ORDERS);
    }

    /**
     * May the admin credit page be used?
     *
     * @return bool
     */
    public static function credits_enabled(): bool {
        return self::flag(self::FLAG_CREDITS);
    }

    /**
     * SQL that keeps only what Sentientia itself wrote: an order that is not an import.
     *
     * @param string $alias Table alias of local_sentientia_cart_history, or '' for none.
     * @return string
     */
    public static function native_only_sql(string $alias = ''): string {
        return ($alias === '' ? '' : $alias . '.') . 'legacy_source IS NULL';
    }

    /**
     * Is this history row an imported BizLMS order?
     *
     * @param \stdClass $cart A local_sentientia_cart_history row.
     * @return bool
     */
    public static function is_imported(\stdClass $cart): bool {
        return !empty($cart->legacy_source);
    }

    /**
     * Is this ledger event one the BizLMS import wrote (a legacy_* type)?
     *
     * @param string $eventtype
     * @return bool
     */
    public static function is_legacy_event(string $eventtype): bool {
        return strncmp($eventtype, 'legacy_', 7) === 0;
    }

    /**
     * Refuse to act on an imported order. An imported order is frozen history: it is never checked out, marked
     * paid or failed, refunded or invoiced, so no webhook, administrator or web service can move imported money.
     *
     * @param \stdClass $cart A local_sentientia_cart_history row.
     * @return void
     * @throws \moodle_exception error_invalidstate
     */
    public static function refuse_if_imported(\stdClass $cart): void {
        if (self::is_imported($cart)) {
            throw new \moodle_exception('error_invalidstate', 'local_sentientia_cart', '', 'Imported BizLMS order');
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function flag(string $key): bool {
        try {
            return \local_sentientia_platform\feature_flags::is_enabled($key);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
