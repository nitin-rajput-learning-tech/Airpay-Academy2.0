<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * GST arithmetic for tax invoices and credit notes (spec 2026-10-14, sections 5 and 7). Pure: no database, no
 * settings, no language, so every rule can be tested by itself.
 *
 * Money is worked in paise (integers) wherever two figures must add up, and converted to rupees (two decimals)
 * only at the edge. Nothing is recomputed from today's gst_rate: every figure comes from what the order recorded
 * when the buyer was charged (taxable = subtotal - discount_amount, tax = tax_amount, total = total_amount).
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gst {

    /**
     * Rupees to paise.
     *
     * @param float|int|string $amount
     * @return int
     */
    public static function to_paise($amount): int {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Paise to rupees, two decimals.
     *
     * @param int $paise
     * @return float
     */
    public static function to_rupees(int $paise): float {
        return round($paise / 100, 2);
    }

    /**
     * Split a tax amount into CGST, SGST and IGST.
     *
     * Inside the state of supply (intra-state) the tax is CGST and SGST, each half; the odd paisa goes to SGST.
     * Anywhere else it is IGST.
     *
     * @param int $taxpaise The tax in paise.
     * @param bool $intrastate Is the place of supply the supplier's state?
     * @return int[] [cgst, sgst, igst] in paise
     */
    public static function split(int $taxpaise, bool $intrastate): array {
        if ($intrastate) {
            $cgst = intdiv($taxpaise, 2);
            return [$cgst, $taxpaise - $cgst, 0];
        }
        return [0, 0, $taxpaise];
    }

    /**
     * Tidy a GSTIN: trimmed and upper-case.
     *
     * @param string $gstin
     * @return string
     */
    public static function normalise_gstin(string $gstin): string {
        return strtoupper(trim($gstin));
    }

    /**
     * Does this look like a GSTIN: fifteen letters and digits starting with a two-digit state code?
     *
     * Deliberately not the full government pattern or its check digit: this is only to stop a typo from being
     * printed on a tax document as a GSTIN, and a stricter test would refuse real numbers.
     *
     * @param string $gstin A normalised GSTIN.
     * @return bool
     */
    public static function is_gstin_format(string $gstin): bool {
        return preg_match('/^\d{2}[0-9A-Z]{13}$/', $gstin) === 1;
    }

    /**
     * Do the buyer's chosen state and the state in their GSTIN agree?
     *
     * @param string $state A GST state code.
     * @param string $gstin A GSTIN; blank means there is nothing to compare.
     * @return bool True when there is no GSTIN, or its first two digits equal the state.
     */
    public static function state_matches_gstin(string $state, string $gstin): bool {
        $gstin = self::normalise_gstin($gstin);
        return $gstin === '' || gst_states::from_gstin($gstin) === $state;
    }

    /**
     * The state a supplier supplies from: the configured code, else the first two digits of their GSTIN.
     *
     * @param string $configured The supplier_state_code setting; blank means "from the GSTIN".
     * @param string $gstin The supplier's own GSTIN.
     * @return string A two-digit code, or '' when neither gives a valid state.
     */
    public static function supplier_state(string $configured, string $gstin): string {
        $configured = trim($configured);
        if ($configured !== '') {
            return gst_states::is_valid($configured) ? $configured : '';
        }
        $fromgstin = gst_states::from_gstin(self::normalise_gstin($gstin));
        return gst_states::is_valid($fromgstin) ? $fromgstin : '';
    }

    /**
     * The place of supply of a supply of services to a buyer.
     *
     * B2B (the buyer gave a GSTIN): the state in the GSTIN. B2C: the state the buyer chose. An order that holds
     * neither (placed before checkout asked for a state) is supplied at the supplier's own location, which is the
     * rule when the recipient's address is not on record (IGST Act, section 12(2)(b)).
     *
     * @param string $billingstate The buyer's state code, or ''.
     * @param string $gstin The buyer's GSTIN, or ''.
     * @param string $supplierstate The supplier's state code.
     * @return string A two-digit state code.
     */
    public static function place_of_supply(string $billingstate, string $gstin, string $supplierstate): string {
        $fromgstin = gst_states::from_gstin(self::normalise_gstin($gstin));
        if ($fromgstin !== '' && gst_states::is_valid($fromgstin)) {
            return $fromgstin;
        }
        if (gst_states::is_valid($billingstate)) {
            return $billingstate;
        }
        return $supplierstate;
    }

    /**
     * The rate an order was charged at: what checkout stored, else worked back from the recorded figures (an
     * order placed before the rate was stored).
     *
     * @param \stdClass $order A history row.
     * @return float Percent, two decimals.
     */
    public static function order_rate(\stdClass $order): float {
        if (isset($order->gst_rate) && $order->gst_rate !== null && $order->gst_rate !== '') {
            return round((float) $order->gst_rate, 2);
        }
        $taxable = self::to_paise($order->subtotal ?? 0) - self::to_paise($order->discount_amount ?? 0);
        $tax = self::to_paise($order->tax_amount ?? 0);
        return $taxable > 0 ? round($tax * 100 / $taxable, 2) : 0.0;
    }

    /**
     * Work out an order's tax document: the totals and one row per line, all from what the order recorded.
     *
     * Lines are spread with cart_manager::withheld_line_amounts(), the per-line method already used for withheld
     * lines (each line's price, discount, net and share of the recorded tax, in paise). Each line is rounded on its
     * own, so the lines can be a paisa or two off the order's totals; the difference is put on the LAST line, so
     * the lines always add up to the totals exactly. The totals, CGST and SGST are those of the whole order
     * (each half the tax, the odd paisa to SGST); the same lines are then made to add up to those too.
     *
     * @param \stdClass $order A history row: items_json, subtotal, discount_amount, tax_amount, total_amount, gst_rate.
     * @param string $placeofsupply A state code.
     * @param string $supplierstate A state code.
     * @param string $sac The SAC to print on each line.
     * @return array taxable, tax, total, rate, cgst, sgst, igst (floats); intrastate (bool); place_of_supply;
     *               lines: courseid, name, sac, price, discount, taxable, rate, tax, cgst, sgst, igst (floats)
     */
    public static function compute(\stdClass $order, string $placeofsupply, string $supplierstate, string $sac): array {
        $intrastate = ($placeofsupply !== '' && $placeofsupply === $supplierstate);
        $rate = self::order_rate($order);

        $taxablep = self::to_paise($order->subtotal ?? 0) - self::to_paise($order->discount_amount ?? 0);
        $taxp = self::to_paise($order->tax_amount ?? 0);
        $totalp = self::to_paise($order->total_amount ?? 0);
        [$cgstp, $sgstp, $igstp] = self::split($taxp, $intrastate);

        $items = json_decode($order->items_json ?: '[]', true) ?: [];
        $courseids = array_map(static fn($i) => (int) ($i['courseid'] ?? 0), $items);
        $rows = $courseids ? cart_manager::withheld_line_amounts($order, $courseids) : [];

        $lines = [];
        foreach ($rows as $row) {
            $linetaxp = self::to_paise($row['tax']);
            [$lc, $ls, $li] = self::split($linetaxp, $intrastate);
            $lines[] = [
                'courseid' => (int) $row['courseid'],
                'name'     => (string) $row['name'],
                'sac'      => $sac,
                'price'    => self::to_paise($row['price']),
                'discount' => self::to_paise($row['discount']),
                'taxable'  => self::to_paise($row['net']),
                'rate'     => $rate,
                'tax'      => $linetaxp,
                'cgst'     => $lc,
                'sgst'     => $ls,
                'igst'     => $li,
            ];
        }

        if ($lines) {
            // Make the lines add up to the order. The residue is a paisa or two of rounding, put on the last line.
            $last = count($lines) - 1;
            $lines[$last]['taxable'] += $taxablep - array_sum(array_column($lines, 'taxable'));
            $lines[$last]['tax'] += $taxp - array_sum(array_column($lines, 'tax'));
            $lines[$last]['cgst'] += $cgstp - array_sum(array_column($lines, 'cgst'));
            $lines[$last]['sgst'] += $sgstp - array_sum(array_column($lines, 'sgst'));
            $lines[$last]['igst'] += $igstp - array_sum(array_column($lines, 'igst'));
        }

        return [
            'taxable'         => self::to_rupees($taxablep),
            'tax'             => self::to_rupees($taxp),
            'total'           => self::to_rupees($totalp),
            'rate'            => $rate,
            'cgst'            => self::to_rupees($cgstp),
            'sgst'            => self::to_rupees($sgstp),
            'igst'            => self::to_rupees($igstp),
            'intrastate'      => $intrastate,
            'place_of_supply' => $placeofsupply,
            'lines'           => array_map(static function (array $l): array {
                foreach (['price', 'discount', 'taxable', 'tax', 'cgst', 'sgst', 'igst'] as $k) {
                    $l[$k] = self::to_rupees($l[$k]);
                }
                return $l;
            }, $lines),
        ];
    }

    /**
     * Work out a credit note for a refund (CGST Act section 34).
     *
     * The refunded amount is gross. taxable = round(amount / (1 + rate / 100), 2) and tax = amount - taxable, split
     * the way the original invoice was. When the credit notes of the invoice, this one included, now add up to the
     * whole invoice, the credit note takes exactly what is left of each figure of the invoice, so a fully
     * refunded invoice is cancelled to the paisa whatever the rounding of the earlier ones.
     *
     * @param \stdClass $invoice The original invoice row (taxable in `subtotal`, cgst, sgst, igst, total).
     * @param float $amount The gross amount refunded, in rupees.
     * @param float $rate The GST rate in percent.
     * @param bool $intrastate Was the invoice CGST + SGST?
     * @param array $earlier The paise already credited: taxable, cgst, sgst, igst, total (ints).
     * @return array taxable, cgst, sgst, igst, total (floats)
     */
    public static function credit_note_figures(\stdClass $invoice, float $amount, float $rate, bool $intrastate,
            array $earlier): array {
        $amountp = self::to_paise($amount);
        $invoicetotalp = self::to_paise($invoice->total);

        if ($earlier['total'] + $amountp === $invoicetotalp) {
            $taxablep = self::to_paise($invoice->subtotal) - $earlier['taxable'];
            $cgstp = self::to_paise($invoice->cgst) - $earlier['cgst'];
            $sgstp = self::to_paise($invoice->sgst) - $earlier['sgst'];
            $igstp = self::to_paise($invoice->igst) - $earlier['igst'];
        } else {
            $taxablep = (int) round($amountp / (1 + $rate / 100));
            $taxp = $amountp - $taxablep;
            [$cgstp, $sgstp, $igstp] = self::split($taxp, $intrastate);
        }
        return [
            'taxable' => self::to_rupees($taxablep),
            'cgst'    => self::to_rupees($cgstp),
            'sgst'    => self::to_rupees($sgstp),
            'igst'    => self::to_rupees($igstp),
            'total'   => self::to_rupees($amountp),
        ];
    }

    /**
     * An amount for a document: the Indian grouping (12,34,567.89) for rupees, thousands for anything else.
     *
     * @param float|int|string $amount
     * @param string $currency
     * @return string Digits only, no symbol.
     */
    public static function money($amount, string $currency = 'INR'): string {
        $amount = round((float) $amount, 2);
        if (strtoupper($currency) !== 'INR') {
            return number_format($amount, 2);
        }
        $negative = $amount < 0;
        [$int, $dec] = explode('.', number_format(abs($amount), 2, '.', ''));
        if (strlen($int) > 3) {
            $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
            $int = $head . ',' . substr($int, -3);
        }
        return ($negative ? '-' : '') . $int . '.' . $dec;
    }
}
