<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_biz_cart_ledger -> local_sentientia_cart_ledger, one row to one row (ADR-032, mapping doc section 13).
 *
 * Both ledgers are insert-only. BizLMS rows keep their amount, gateway, actor and timestamp; everything else the
 * BizLMS row says (item, tax, credits, fee, component, account, the gateway attempts) goes into payload_json, so
 * nothing is lost and nothing that no reader shows gets a column of its own.
 *
 * Money is imported as it is. A row BizLMS never wrote is not invented: the decision cart.synthesize_ledger is
 * false, so a paid line that has no ledger row has none here either, and the daily sums do not show it.
 *
 * The order a row belongs to is found by its identifier (the order number), else by the history line its
 * schistoryid names. A row with neither (the cash drawer, credits paid out) has historyid 0: it belongs to no
 * order and no tenant, so only cross-tenant administrators can see it.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ledger_step extends step {

    /** The table the rows land in. */
    public const TARGET = 'local_sentientia_cart_ledger';

    /**
     * @return string
     */
    public function key(): string {
        return 'cart.ledger';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_biz_cart_ledger';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return self::TARGET;
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx->legacy);

        // The history line each row leads to, resolved in one read for the whole batch.
        $leads = [];
        $need = [];
        foreach ($rows as $row) {
            $line = self::line_of($row, $evidence);
            $leads[(int) $row->id] = $line;
            if ($line !== null) {
                $need[$line] = true;
            }
        }
        $resolved = $need ? $ctx->map->resolve_many('local_biz_cart_history', array_keys($need)) : [];

        $out = [];
        foreach ($rows as $row) {
            $line = $leads[(int) $row->id];
            $out[] = $this->one($row, $line === null ? null : ($resolved[$line] ?? null), $evidence, $ctx);
        }
        return $out;
    }

    /**
     * The history line a ledger row leads to: the first line of its order, else the line it names itself.
     *
     * @param \stdClass $row
     * @param evidence $evidence
     * @return int|null
     */
    public static function line_of(\stdClass $row, evidence $evidence): ?int {
        $identifier = (int) ($row->identifier ?? 0);
        if ($identifier > 0) {
            $first = $evidence->first_line($identifier);
            if ($first !== null) {
                return $first;
            }
        }
        $named = (int) ($row->schistoryid ?? 0);
        return $named > 0 ? $named : null;
    }

    /**
     * One ledger row.
     *
     * @param \stdClass $row
     * @param int|null $historyid The imported order the row belongs to, null when there is none.
     * @param evidence $evidence
     * @param context $ctx
     * @return outcome
     */
    private function one(\stdClass $row, ?int $historyid, evidence $evidence, context $ctx): outcome {
        $id = (int) $row->id;
        $currency = support::currency($row->currency ?? null);
        if ($currency === null) {
            return outcome::skip($id, 'currency_invalid', 'currency_invalid');
        }

        $type = support::ledger_event_type($row);
        $identifier = (int) ($row->identifier ?? 0);
        $userid = (int) ($row->userid ?? 0);
        $warnings = [];
        if ($type === 'legacy_other') {
            $warnings[] = 'legacy_other_event';
        }

        $orderno = 0;
        if ($historyid !== null) {
            $orderno = $identifier > 0 ? $identifier : 0;
            if ($identifier <= 0 || $evidence->first_line($identifier) === null) {
                $warnings[] = 'order_from_history_line';
            }
        } else if (in_array($type, ['payment_received', 'legacy_cancel_to_credit'], true)) {
            // A sale or a cancellation with no order to belong to. The cash drawer and the credit bookings are
            // expected to have none.
            $warnings[] = 'ledger_without_order';
            if ($type === 'payment_received') {
                // A sale that belongs to no imported order has no order row to mark it as imported, so it would read
                // as something Sentientia recorded (the daily sums and the flag key on the order's legacy_source or a
                // legacy_ event). Its own event type keeps it on the imported side.
                $type = 'legacy_sale_without_order';
            }
        }

        $method = trim((string) ($row->payment ?? ''));
        if ($method === '0') {
            $gateway = $identifier > 0 ? $evidence->online_gateway($identifier, $userid) : 'online';
        } else {
            $gateway = support::gateway_label($method);
            if ($gateway === null) {
                $gateway = 'unknown';
                $warnings[] = 'gateway_unknown';
            }
        }

        $created = support::epoch($row->timecreated ?? 0) ?: support::epoch($row->timemodified ?? 0);
        if ($created <= 0) {
            $warnings[] = 'no_timestamp';
        }

        $fields = (object) [
            'historyid' => $historyid ?? 0,
            'orderid' => $orderno,
            'event_type' => $type,
            'amount' => round((float) ($row->price ?? 0), 2),
            'currency' => $currency,
            'gateway' => $ctx->text->fit($gateway, 30, 'gateway'),
            'gateway_ref' => null,
            'initiatedby' => max(0, (int) ($row->usermodified ?? 0)),
            'reason' => support::clean_text($row->annotation ?? ''),
            'payload_json' => $this->payload($row, $type, $identifier, $userid, $evidence),
            'timecreated' => $created,
        ];
        $outcome = outcome::insert($id, self::TARGET, $fields);
        foreach ($warnings as $warning) {
            $outcome->warn($warning);
        }
        return $outcome;
    }

    /**
     * Everything the BizLMS row says that has no column: item, tax, credits, fee, component, account and the
     * gateway attempts of the order (for a sale). The buyer's user id comes FIRST, so the privacy provider can find
     * and anonymise a row by a prefix match on the stored text (see privacy\provider).
     *
     * @param \stdClass $row
     * @param string $type
     * @param int $identifier
     * @param int $userid
     * @param evidence $evidence
     * @return string JSON
     */
    private function payload(\stdClass $row, string $type, int $identifier, int $userid, evidence $evidence): string {
        $payload = [
            'userid' => $userid,
            'identifier' => $identifier,
            'itemid' => (int) ($row->itemid ?? 0),
            'itemname' => support::clean_text($row->itemname ?? ''),
            'tax' => isset($row->tax) ? (float) $row->tax : null,
            'taxpercentage' => isset($row->taxpercentage) ? (float) $row->taxpercentage : null,
            'taxcategory' => support::clean_text($row->taxcategory ?? ''),
            'discount' => isset($row->discount) ? (float) $row->discount : null,
            'credits' => isset($row->credits) ? (float) $row->credits : null,
            'fee' => isset($row->fee) ? (float) $row->fee : null,
            'componentname' => support::clean_text($row->componentname ?? ''),
            'costcenter' => support::clean_text($row->costcenter ?? ''),
            'accountid' => isset($row->accountid) ? (int) $row->accountid : null,
            'payment' => support::clean_text($row->payment ?? ''),
            'paymentstatus' => (int) ($row->paymentstatus ?? 0),
            'canceluntil' => isset($row->canceluntil) ? (int) $row->canceluntil : null,
            'area' => support::clean_text($row->area ?? ''),
            'schistoryid' => isset($row->schistoryid) ? (int) $row->schistoryid : null,
        ];
        if (in_array($type, ['payment_received', 'legacy_sale_without_order'], true) && $identifier > 0) {
            // The attempt that collected the money, and the others, as the gateway recorded them. A sale whose
            // order was not imported keeps this evidence too: it is all that says how it was paid.
            $chosen = $evidence->paid_attempt($identifier, $userid);
            $others = [];
            foreach ($evidence->attempts($identifier, $userid) as $attempt) {
                if ($chosen !== null && (int) $attempt->id === (int) $chosen->id) {
                    continue;
                }
                $others[] = self::attempt($attempt);
            }
            $payload['paygw'] = [
                'chosen' => $chosen === null ? null : self::attempt($chosen),
                'others' => $others,
            ];
        }
        $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * One gateway attempt, as facts only.
     *
     * @param \stdClass $attempt
     * @return array
     */
    private static function attempt(\stdClass $attempt): array {
        return [
            'ap_orderid' => support::clean_text($attempt->ap_orderid ?? ''),
            'status' => (int) ($attempt->status ?? 0),
            'cost' => isset($attempt->cost) ? (int) $attempt->cost : null,
            'paymentid' => isset($attempt->paymentid) ? (int) $attempt->paymentid : null,
        ];
    }
}
