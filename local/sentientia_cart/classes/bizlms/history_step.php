<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_biz_cart_history -> local_sentientia_cart_history (ADR-032, mapping doc section 13).
 *
 * BizLMS writes one history row per item, grouped by an integer cart identifier. Sentientia keeps ONE row per
 * order. This step is grouped on the identifier: the lines of one order arrive together, the first line (lowest
 * id) becomes the order row, and every other line is recorded as merged into it. Every source row therefore has
 * exactly one primary map row (ADR id strategy 1), which a derived group (#local_biz_cart_history.identifier)
 * could not give: a derived step has one primary row per group and no row for its lines.
 *
 * MAP, not PRESERVE. The order number is the identifier, kept in history.orderid (receipts and Moodle's payments
 * rows refer to it), and nothing reads a history row by its id from outside the cart.
 *
 * What it never does: check out, mark paid, refund, issue an invoice, send a message, enrol or unenrol. It
 * returns outcomes and the writer writes them. An imported order is frozen history: legacy_source marks it and
 * the cart manager refuses to act on it.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_step extends step {

    /** The table the orders land in. */
    public const TARGET = 'local_sentientia_cart_history';

    /**
     * @return string
     */
    public function key(): string {
        return 'cart.history';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_biz_cart_history';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return self::TARGET;
    }

    /**
     * The lines of one order are one group. A line with no order number (NULL or 0) falls into the group of its own
     * kind; transform() makes an order of each of its lines.
     *
     * @return string[]
     */
    public function group_by(): array {
        return ['identifier'];
    }

    /**
     * @param \stdClass[] $rows The lines of one order, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $builder = new order_builder(support::price_is_net());
        $identifier = (int) ($rows[0]->identifier ?? 0);

        if ($identifier <= 0) {
            // No order number: each line is an order of its own, numbered by the recompute step.
            $out = [];
            foreach ($rows as $row) {
                array_push($out, ...$this->one_order($builder, 0, [$row], $ctx));
            }
            return $out;
        }
        return $this->one_order($builder, $identifier, $rows, $ctx);
    }

    /**
     * The outcomes of one order.
     *
     * @param order_builder $builder
     * @param int $identifier
     * @param \stdClass[] $lines
     * @param context $ctx
     * @return outcome[]
     */
    private function one_order(order_builder $builder, int $identifier, array $lines, context $ctx): array {
        $built = $builder->build($identifier, $lines, $ctx);
        $order = $built['order'];
        if ($order === null) {
            return $this->skip_all($lines, $built['reason'], $built['detail']);
        }

        if ($order->status === 'abandoned' && $ctx->decision('cart.abandoned') === 'skip') {
            $out = [];
            foreach ($lines as $line) {
                $out[] = outcome::archive((int) $line->id, 'abandoned_not_imported');
            }
            return $out;
        }

        [$root, $method] = [$order->costcenterid, $order->tenantmethod];
        if ($root === 0 && $ctx->decision('tenant.unresolved.cart') === 'skip') {
            return $this->skip_all($lines, 'tenant_unresolved', 'tenant_not_resolved');
        }

        $winner = (int) $lines[0]->id;
        $insert = outcome::insert($winner, self::TARGET, (object) [
            'orderid' => $order->orderid,
            'userid' => $order->userid,
            'costcenterid' => $order->costcenterid,
            'items_json' => $order->items_json,
            'subtotal' => $order->subtotal,
            'discount_amount' => $order->discount_amount,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'status' => $order->status,
            'gateway' => $order->gateway,
            'gateway_ref' => $order->gateway_ref,
            'billing_name' => null,
            'billing_email' => null,
            'billing_phone' => null,
            'billing_address' => null,
            'billing_gstn' => null,
            'notes' => $order->notes,
            'timecreated' => $order->timecreated,
            'timepaid' => $order->timepaid,
            'timemodified' => $order->timemodified,
            'legacy_source' => support::SOURCE_LABEL,
        ])->tenant_method($method);
        foreach ($order->warnings as $warning) {
            $insert->warn($warning);
        }

        $out = [$insert];
        foreach (array_slice($lines, 1) as $line) {
            $out[] = outcome::merge((int) $line->id, $winner, 'order_line');
        }
        return $out;
    }

    /**
     * Every line of a group that cannot be imported is skipped with the same reason: the order is all or nothing,
     * so no half of it is ever written.
     *
     * @param \stdClass[] $lines
     * @param string $reason
     * @param string $detail
     * @return outcome[]
     */
    private function skip_all(array $lines, string $reason, string $detail): array {
        $out = [];
        foreach ($lines as $line) {
            $out[] = outcome::skip((int) $line->id, $reason, $detail);
        }
        return $out;
    }
}
