<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * Turns the history lines of one BizLMS order into the one row Sentientia keeps per order (ADR-032, mapping doc
 * section 13, "history group -> cart_history").
 *
 * BizLMS writes one history row per item, grouped by an integer cart identifier; Sentientia keeps one history row
 * per order with an items_json snapshot, and callback.php and return.php load it by order number. The history
 * step builds the row, and the invoice step builds the same row again to take the buyer, tenant, line items and
 * totals from, so both read the same code and cannot disagree.
 *
 * PURE: it reads only through the context (users, the legacy reader) and returns a value. It never writes, and it
 * never calls the cart manager, the invoicer or the notifier.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class order_builder {

    /** @var bool BizLMS itempriceisnet: the line price is net of tax (otherwise gross). */
    private bool $pricenet;

    /**
     * @param bool $pricenet The BizLMS itempriceisnet setting.
     */
    public function __construct(bool $pricenet) {
        $this->pricenet = $pricenet;
    }

    /**
     * Build the order of one group of history lines.
     *
     * @param int $identifier The BizLMS order number; 0 for a line that has none (it becomes an order of its own).
     * @param \stdClass[] $lines The lines, ordered by id.
     * @param context $ctx
     * @return array{order: \stdClass|null, reason: string, detail: string} order is null when the group cannot be
     *         imported; reason and detail then name why (codes only).
     */
    public function build(int $identifier, array $lines, context $ctx): array {
        $evidence = evidence::of($ctx->legacy);

        // One buyer per order.
        $buyers = [];
        foreach ($lines as $line) {
            $buyers[(int) $line->userid] = true;
        }
        if (count($buyers) !== 1) {
            return self::refuse('mixed_buyers', 'mixed_buyers');
        }
        $userid = (int) array_key_first($buyers);
        if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            return self::refuse('orphan_user', 'user_not_found');
        }

        // One currency per order, and it must be one.
        $currencies = [];
        foreach ($lines as $line) {
            $code = support::currency($line->currency ?? null);
            if ($code === null) {
                return self::refuse('currency_invalid', 'currency_invalid');
            }
            $currencies[$code] = true;
        }
        if (count($currencies) !== 1) {
            return self::refuse('currency_invalid', 'currency_mixed');
        }
        $currency = (string) array_key_first($currencies);

        $warnings = [];
        $paid = [];
        $cancelled = [];
        $open = [];
        foreach ($lines as $line) {
            $status = (int) $line->paymentstatus;
            if ($status === 2) {
                $paid[] = $line;
            } else if ($status === 3) {
                $cancelled[] = $line;
            } else {
                $open[] = $line;
            }
        }

        // Order status. Never open, pending, failed, refunded or partial_refund: those are states a native order
        // is in, and the native engine would act on them (see the mapping doc, "Order status").
        if ($paid && $cancelled) {
            $status = 'part_cancelled';
        } else if ($paid) {
            $status = 'paid';
        } else if ($cancelled) {
            $status = 'cancelled';
        } else {
            $status = 'abandoned';
        }
        $settled = $paid || $cancelled;
        if ($settled && $open) {
            $warnings[] = 'mixed_status_lines';
        }

        // Totals, in integers. BizLMS price already has the discount subtracted; the undiscounted price is
        // price + discount (BizLMS biz_cart.php). Sentientia total = subtotal - discount + tax.
        $counted = $settled ? array_merge($paid, $cancelled) : $lines;
        $pricepaise = 0;
        $discountpaise = 0;
        $taxmilli = 0;
        foreach ($counted as $line) {
            $pricepaise += support::paise($line->price ?? 0);
            $discountpaise += support::paise($line->discount ?? 0);
            $taxmilli += support::milli($line->tax ?? 0);
        }
        $taxpaise = (int) round($taxmilli / 10);
        if ($this->pricenet) {
            $subtotal = $pricepaise + $discountpaise;
            $total = $pricepaise + $taxpaise;
        } else {
            $subtotal = $pricepaise + $discountpaise - $taxpaise;
            $total = $pricepaise;
        }

        $items = $this->items($lines, $ctx);
        $encoded = json_encode($items, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            $encoded = '[]';
            $warnings[] = 'items_unencodable';
        }

        // The payment method and what it means.
        $reference = $paid ? $paid[0] : $lines[0];
        $method = trim((string) ($reference->payment ?? ''));
        if ($method === '0') {
            $gateway = $identifier > 0 ? $evidence->online_gateway($identifier, $userid) : 'online';
        } else {
            $gateway = support::gateway_label($method);
            if ($gateway === null) {
                $gateway = 'unknown';
                $warnings[] = 'gateway_unknown';
            }
        }
        $gatewayref = null;
        if ($settled && $identifier > 0) {
            $gatewayref = $evidence->gateway_ref($identifier, $userid);
        }

        // Timestamps: the source's own, never the time of the import.
        $created = [];
        $modified = 0;
        foreach ($lines as $line) {
            $when = support::epoch($line->timecreated ?? 0) ?: support::epoch($line->timemodified ?? 0);
            if ($when > 0) {
                $created[] = $when;
            }
            $modified = max($modified, support::epoch($line->timemodified ?? 0));
        }
        $timecreated = $created ? min($created) : 0;
        if (!$created) {
            $warnings[] = 'no_timestamp';
        }
        if ($modified <= 0) {
            $modified = $timecreated;
        }

        // When it was paid: the earliest sale row of the ledger, else the time the paid lines were last touched
        // (BizLMS sets it on success), else what the gateway recorded. Never the import time, and NULL for an
        // order nobody paid.
        $timepaid = null;
        if ($settled) {
            $when = $identifier > 0 ? $evidence->ledger_paid_time($identifier) : 0;
            if ($when <= 0 && $paid) {
                foreach ($paid as $line) {
                    $when = max($when, support::epoch($line->timemodified ?? 0));
                }
                if ($when > 0) {
                    $warnings[] = 'derived_timestamp';
                }
            }
            if ($when <= 0 && $identifier > 0) {
                $when = $evidence->gateway_paid_time($identifier, $userid);
                if ($when > 0) {
                    $warnings[] = 'derived_timestamp';
                }
            }
            if ($when > 0) {
                $timepaid = $when;
            }
        }

        [$root, $method_of_tenant] = support::root_of_user($userid, $ctx);

        $ids = array_map(static fn(\stdClass $line): int => (int) $line->id, $lines);
        $shown = array_slice($ids, 0, 50);
        $notes = 'Imported from BizLMS: identifier ' . ($identifier > 0 ? $identifier : 'none')
            . ', legacy history ids [' . implode(',', $shown) . (count($ids) > count($shown) ? ',...' : '')
            . '], method ' . ($method === '' ? 'none' : $method) . '.';
        if ($identifier <= 0) {
            $notes .= ' BizLMS recorded no order number for this line; one was allocated at import.';
            $warnings[] = 'order_number_allocated';
        }
        if (in_array('mixed_status_lines', $warnings, true)) {
            $notes .= ' Some lines of this order were never completed.';
        }

        return [
            'order' => (object) [
                'orderid' => $identifier > 0 ? $identifier : null,
                'userid' => $userid,
                'costcenterid' => $root,
                'tenantmethod' => $method_of_tenant,
                'items_json' => $encoded,
                'subtotal' => $subtotal / 100,
                'discount_amount' => $discountpaise / 100,
                'tax_amount' => $taxpaise / 100,
                'total_amount' => $total / 100,
                'currency' => $currency,
                'status' => $status,
                'gateway' => $ctx->text->fit($gateway, 30, 'gateway'),
                'gateway_ref' => $gatewayref === null ? null : $ctx->text->fit($gatewayref, 120, 'gateway_ref'),
                'notes' => $notes,
                'timecreated' => $timecreated,
                'timepaid' => $timepaid,
                'timemodified' => $modified,
                'warnings' => $warnings,
            ],
            'reason' => '',
            'detail' => '',
        ];
    }

    /**
     * The items snapshot: one object per line, in id order.
     *
     * @param \stdClass[] $lines
     * @param context $ctx
     * @return array[]
     */
    private function items(array $lines, context $ctx): array {
        $courseids = [];
        foreach ($lines as $line) {
            if (self::is_course_line($line)) {
                $courseids[] = (int) $line->itemid;
            }
        }
        $courses = $courseids ? $ctx->legacy->fetch('course', $courseids, ['shortname']) : [];

        $items = [];
        foreach ($lines as $line) {
            $status = (int) $line->paymentstatus;
            $courseid = self::is_course_line($line) ? (int) $line->itemid : 0;
            $pricem = support::milli($line->price ?? 0);
            $discountm = support::milli($line->discount ?? 0);
            $taxm = support::milli($line->tax ?? 0);
            // The undiscounted NET price of the line (BizLMS price + discount, less the tax when price is gross).
            $netm = $pricem + $discountm - ($this->pricenet ? 0 : $taxm);

            $item = [
                'courseid' => $courseid,
                'name' => (string) support::clean_text($line->itemname ?? ''),
            ];
            if ($courseid > 0 && isset($courses[$courseid])) {
                $item['shortname'] = (string) support::clean_text($courses[$courseid]->shortname ?? '');
            }
            $item += [
                'price' => round($netm / 1000, 2),
                'discount' => round($discountm / 1000, 2),
                'discount_pct' => 0,
                'tax' => round($taxm / 1000, 3),
                'status' => $status === 2 ? 'paid' : ($status === 3 ? 'cancelled' : 'not_completed'),
                // Facts only, and no actor ids: whoever booked the line is not part of the snapshot.
                'legacy' => [
                    'historyid' => (int) $line->id,
                    'componentname' => support::clean_text($line->componentname ?? ''),
                    'area' => support::clean_text($line->area ?? ''),
                    'itemid' => (int) ($line->itemid ?? 0),
                    'payment' => support::clean_text($line->payment ?? ''),
                    'paymentstatus' => $status,
                    'usecredit' => isset($line->usecredit) ? (int) $line->usecredit : null,
                    'taxpercentage' => isset($line->taxpercentage) ? (float) $line->taxpercentage : null,
                    'taxcategory' => support::clean_text($line->taxcategory ?? ''),
                    'canceluntil' => isset($line->canceluntil) ? (int) $line->canceluntil : null,
                    'serviceperiodstart' => isset($line->serviceperiodstart) ? (int) $line->serviceperiodstart : null,
                    'serviceperiodend' => isset($line->serviceperiodend) ? (int) $line->serviceperiodend : null,
                ],
            ];
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Is the line a course the catalogue sold? BizLMS also books other components; a rebooked item keeps its
     * component local_biz_cart while its itemid is swapped to the original item, so it is not a course line.
     *
     * @param \stdClass $line
     * @return bool
     */
    private static function is_course_line(\stdClass $line): bool {
        return (string) ($line->componentname ?? '') === 'local_courses'
            && (string) ($line->area ?? '') === 'option'
            && (int) ($line->itemid ?? 0) > 0;
    }

    /**
     * A group that cannot be imported.
     *
     * @param string $reason
     * @param string $detail
     * @return array{order: null, reason: string, detail: string}
     */
    private static function refuse(string $reason, string $detail): array {
        return ['order' => null, 'reason' => $reason, 'detail' => $detail];
    }
}
