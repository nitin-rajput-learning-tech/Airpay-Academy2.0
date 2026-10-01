<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_biz_cart_invoices -> local_sentientia_cart_invoices (ADR-032, mapping doc section 13).
 *
 * A BizLMS invoice row is a pointer: the order number and the id ERPNext gave the invoice (BizLMS posted the
 * order to ERPNext when it was paid). So the invoice is imported as a REFERENCE: status legacy_external, number
 * ERPNEXT-<id>, the order's own buyer, tenant, items and totals, and no tax split (ERPNext worked the tax out).
 * Sentientia issues no number, builds no PDF, and does not link out to ERPNext until finance confirms that the
 * ERPNext invoices are the legal tax invoices (a finance-confirm decision, not read here).
 *
 * Grouped on the invoice id because invoice_number is unique in the target: two source rows that carry the same
 * id cannot both be imported, so the later one is merged into the first and stays in the legacy table.
 *
 * An invoice whose order was not imported (the order group was skipped or archived) is skipped and reported: the
 * target needs a history row to belong to.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class invoices_step extends step {

    /** The table the rows land in. */
    public const TARGET = 'local_sentientia_cart_invoices';

    /** Prefix of an imported invoice's number; the invoicer never issues it. */
    public const NUMBER_PREFIX = 'ERPNEXT-';

    /** Longest invoice number the target column holds. */
    private const NUMBER_MAX = 50;

    /**
     * @return string
     */
    public function key(): string {
        return 'cart.invoices';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_biz_cart_invoices';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return self::TARGET;
    }

    /**
     * @return string[]
     */
    public function group_by(): array {
        return ['invoiceid'];
    }

    /**
     * @param \stdClass[] $rows The invoices that share an ERPNext invoice id, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx->legacy);
        $builder = new order_builder(support::price_is_net());

        // The imported order of each invoice, resolved in one read.
        $need = [];
        foreach ($rows as $row) {
            $first = $evidence->first_line((int) ($row->identifier ?? 0));
            if ($first !== null) {
                $need[$first] = true;
            }
        }
        $resolved = $need ? $ctx->map->resolve_many('local_biz_cart_history', array_keys($need)) : [];

        $out = [];
        $winner = null;
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $number = trim((string) ($row->invoiceid ?? ''));
            if ($number === '') {
                $out[] = outcome::skip($id, 'invoice_without_number', 'invoice_without_number');
                continue;
            }
            $full = self::NUMBER_PREFIX . $number;
            if (\core_text::strlen($full) > self::NUMBER_MAX) {
                $out[] = outcome::skip($id, 'invoice_number_too_long', 'invoice_number_too_long');
                continue;
            }

            $identifier = (int) ($row->identifier ?? 0);
            $first = $evidence->first_line($identifier);
            $historyid = $first === null ? null : ($resolved[$first] ?? null);
            $order = null;
            if ($historyid !== null) {
                $lines = $ctx->legacy->fetch('local_biz_cart_history', $evidence->line_ids($identifier));
                $built = $builder->build($identifier, array_values($lines), $ctx);
                $order = $built['order'];
            }
            if ($order === null) {
                $out[] = outcome::skip($id, 'invoice_without_order', 'invoice_without_order');
                continue;
            }

            if ($winner !== null) {
                $out[] = outcome::merge($id, $winner, 'dup_invoice_number');
                continue;
            }
            $winner = $id;

            $created = support::epoch($row->timecreated ?? 0);
            $warnings = [];
            if ($created <= 0) {
                $created = $order->timepaid ?? $order->timecreated;
                $warnings[] = 'derived_timestamp';
            }
            $insert = outcome::insert($id, self::TARGET, (object) [
                'historyid' => $historyid,
                'orderid' => $identifier,
                'invoice_number' => $full,
                'userid' => $order->userid,
                'costcenterid' => $order->costcenterid,
                // BizLMS stored no billing details; the table needs a name, and an empty one says so.
                'billing_name' => '',
                'billing_email' => null,
                'billing_phone' => null,
                'billing_address' => null,
                'billing_gstn' => null,
                'line_items_json' => $order->items_json,
                'subtotal' => round($order->subtotal - $order->discount_amount, 2),
                'cgst' => 0,
                'sgst' => 0,
                'igst' => 0,
                'total' => $order->total_amount,
                'currency' => $order->currency,
                'pdf_filename' => null,
                'status' => 'legacy_external',
                'timecreated' => $created,
            ])->tenant_method($order->tenantmethod);
            foreach ($warnings as $warning) {
                $insert->warn($warning);
            }
            $out[] = $insert;
        }
        return $out;
    }
}
