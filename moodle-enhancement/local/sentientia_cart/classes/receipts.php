<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The buyer's payment receipt (spec 2026-10-14, section 8), behind the flag sentientia.cart.purchase_receipts.
 *
 * A receipt says what was paid, for what, how and when; it is NOT a tax invoice and says so. It is worked out on
 * demand from the order (history row) and its ledger, so an order paid before the flag was switched on has one too.
 *
 * Access is the rule of the invoice (invoicer::require_view_access): the owner, or a :viewallorders holder inside
 * the order's tenant (ADR-031). An order imported from BizLMS is admin-only history and has no receipt.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class receipts {

    /** @var string[] The lang strings a receipt prints, without the receipt_ prefix. */
    private const LABELS = [
        'title', 'orderno', 'paidon', 'buyer', 'email', 'items', 'item', 'price', 'discount', 'subtotal', 'gst',
        'totalpaid', 'payment', 'gateway', 'reference', 'refunds', 'refundedon', 'amount', 'notinvoice',
        'taxinvoice', 'viewtaxinvoice', 'invoicewillfollow', 'creditnotes', 'viewcreditnote', 'downloadpdf', 'print',
        'status',
    ];

    /**
     * The order statuses that mean the money was taken.
     */
    private const PAID_STATUSES = ['paid', 'partial_refund', 'refunded'];

    /**
     * Is the receipt flag ON for the tenant of this order?
     *
     * @param \stdClass $order
     * @return bool
     */
    public static function enabled_for(\stdClass $order): bool {
        return flags::receipts_for_order($order);
    }

    /**
     * Is the receipt flag ON for the tenant a user belongs to? For the history page, which has no order yet.
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function enabled_for_user(\stdClass $user): bool {
        return flags::enabled_for_tenant(flags::RECEIPTS, cart_manager::get_tenant_root($user));
    }

    /**
     * Does the order history of this user get a Documents column: is the receipt flag or the invoice flag ON for
     * the user's tenant? With both OFF the history is exactly what it was.
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function history_column_enabled(\stdClass $user): bool {
        $root = cart_manager::get_tenant_root($user);
        return flags::enabled_for_tenant(flags::RECEIPTS, $root) || flags::enabled_for_tenant(flags::INVOICES, $root);
    }

    /**
     * The Documents cell of the order history: links to the receipt, the tax invoice and the credit notes of each
     * order, built as escaped HTML (html_writer escapes the text and the URL).
     *
     * Receipt: while the receipt flag is ON for the order's tenant. Tax invoice and credit notes: only the issued
     * ones (an invoice that awaits its IRN is not shown to the buyer), and only while a flag is ON for the order's
     * tenant, so that the history stays as it was until a flag is switched on.
     *
     * @param \stdClass[] $orders Rows of local_sentientia_cart_history keyed by id: id, status, costcenterid,
     *                            legacy_source.
     * @return array<int, string> history id => HTML ('' when the order has no document)
     */
    public static function history_links(array $orders): array {
        global $DB;
        if (!$orders) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', array_keys($orders)), SQL_PARAMS_NAMED, 'hid');
        $invoices = [];
        foreach ($DB->get_records_select('local_sentientia_cart_invoices',
                "historyid $insql AND status = :issued", $inparams + ['issued' => invoicer::STATUS_ISSUED],
                'id ASC', 'id, historyid, invoice_number') as $i) {
            $invoices[(int) $i->historyid] = $i;
        }
        $notes = [];
        foreach ($DB->get_records_select('local_sentientia_cart_creditnotes',
                "historyid $insql AND status = :issued", $inparams + ['issued' => invoicer::STATUS_ISSUED],
                'timecreated ASC, id ASC', 'id, historyid, cn_number') as $n) {
            $notes[(int) $n->historyid][] = $n;
        }

        $out = [];
        foreach ($orders as $id => $o) {
            $id = (int) $id;
            $links = [];
            $receiptflag = !imported_history::is_imported($o) && self::exists_for($o);
            $anyflag = $receiptflag || flags::invoices_for_order($o);
            if ($receiptflag) {
                $links[] = \html_writer::link(self::url($id), get_string('history_receipt', 'local_sentientia_cart'),
                    ['class' => 'me-2']);
            }
            if ($anyflag && isset($invoices[$id])) {
                $links[] = \html_writer::link(new \moodle_url('/local/sentientia_cart/invoice.php',
                    ['id' => $invoices[$id]->id]), get_string('history_taxinvoice', 'local_sentientia_cart'),
                    ['class' => 'me-2', 'title' => $invoices[$id]->invoice_number]);
            }
            if ($anyflag) {
                foreach ($notes[$id] ?? [] as $n) {
                    $links[] = \html_writer::link(new \moodle_url('/local/sentientia_cart/creditnote.php',
                        ['id' => $n->id]), get_string('history_creditnote', 'local_sentientia_cart'),
                        ['class' => 'me-2', 'title' => $n->cn_number]);
                }
            }
            $out[$id] = implode('', $links);
        }
        return $out;
    }

    /**
     * Does this order have a receipt a viewer could open: paid, native, flag ON?
     *
     * @param \stdClass $order
     * @return bool
     */
    public static function exists_for(\stdClass $order): bool {
        return !imported_history::is_imported($order)
            && in_array($order->status, self::PAID_STATUSES, true)
            && self::enabled_for($order);
    }

    /**
     * May $viewerid open the receipt of this order? Throws if not.
     *
     * The flag must be ON for the order's tenant (else the page does not exist), the order must be paid and native,
     * and the viewer must be its owner or a :viewallorders holder in the order's tenant (or a site administrator).
     *
     * @param \stdClass $order A local_sentientia_cart_history row.
     * @param int|null $viewerid Defaults to the current user.
     * @throws \moodle_exception error_notavailable, error_outoftenant
     */
    public static function require_view_access(\stdClass $order, ?int $viewerid = null): void {
        global $USER;
        $viewerid = $viewerid ?? (int) $USER->id;
        // Same refusal as a row of another tenant, so a probe learns nothing about imported history.
        if (imported_history::is_imported($order)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
        }
        if ((int) $order->userid !== $viewerid) {
            if (!is_siteadmin($viewerid)
                    && !has_capability('local/sentientia_cart:viewallorders', \context_system::instance(), $viewerid)) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_cart');
            }
            cart_manager::require_order_tenant((int) $order->costcenterid, $viewerid);
        }
        if (!self::exists_for($order)) {
            throw new \moodle_exception('error_notavailable', 'local_sentientia_cart');
        }
    }

    /**
     * The URL of an order's receipt page.
     *
     * @param int $historyid
     * @param bool $pdf The PDF download instead of the page.
     * @return \moodle_url
     */
    public static function url(int $historyid, bool $pdf = false): \moodle_url {
        $params = ['id' => $historyid];
        if ($pdf) {
            $params['download'] = 'pdf';
        }
        return new \moodle_url('/local/sentientia_cart/receipt.php', $params);
    }

    /**
     * The payment method as the buyer reads it: the gateway's name string, or its key when there is none.
     *
     * @param string $gateway The gateway key stored on the order (airpay, manual).
     * @return string
     */
    public static function gateway_label(string $gateway): string {
        if ($gateway === '') {
            return '';
        }
        $key = 'gateway_name_' . $gateway;
        return get_string_manager()->string_exists($key, 'local_sentientia_cart')
            ? get_string($key, 'local_sentientia_cart') : $gateway;
    }

    /**
     * The tax documents of an order that its buyer may see: the invoice (when it is issued), the credit notes that
     * are issued, and whether a tax invoice is still to come.
     *
     * @param \stdClass $order
     * @return array invoice (row or null), creditnotes (rows), invoice_follows (bool)
     */
    public static function documents_of(\stdClass $order): array {
        global $DB;
        $invoice = $DB->get_record('local_sentientia_cart_invoices', ['historyid' => $order->id]);
        $visible = null;
        $follows = false;
        if ($invoice && $invoice->status === imported_history::INVOICE_STATUS) {
            $invoice = null;
        }
        if ($invoice) {
            if ($invoice->status === invoicer::STATUS_AWAITING_IRN) {
                $follows = true;
            } else {
                $visible = $invoice;
            }
        } else if (!empty($order->invoice_due) && flags::invoices_for_order($order)) {
            // Paid, and the invoice is on its way.
            $follows = true;
        }
        $notes = [];
        if ($invoice) {
            foreach ($DB->get_records('local_sentientia_cart_creditnotes',
                    ['historyid' => $order->id, 'status' => invoicer::STATUS_ISSUED], 'timecreated ASC, id ASC') as $cn) {
                $notes[] = $cn;
            }
        }
        return ['invoice' => $visible, 'creditnotes' => $notes, 'invoice_follows' => $follows];
    }

    /**
     * The refunds of an order, from the ledger: what was refunded and when.
     *
     * @param \stdClass $order
     * @return \stdClass[] ledger rows (amount as a positive number in `refunded`, timecreated), oldest first
     */
    public static function refunds_of(\stdClass $order): array {
        global $DB;
        $rows = $DB->get_records_select('local_sentientia_cart_ledger',
            'historyid = :hid AND ' . $DB->sql_like('event_type', ':ev', true, true),
            ['hid' => $order->id, 'ev' => $DB->sql_like_escape('refund_') . '%'], 'timecreated ASC, id ASC');
        foreach ($rows as $row) {
            $row->refunded = abs((float) $row->amount);
        }
        return array_values($rows);
    }

    /**
     * The data of a receipt.
     *
     * @param \stdClass $order A paid, native order.
     * @param string|null $lang Language of the words: null for the viewer's, 'en' for the PDF.
     * @return array
     */
    public static function context(\stdClass $order, ?string $lang = null): array {
        $currency = (string) $order->currency;
        $symbol = invoicer::currency_symbol($currency);
        $fmt = static fn($v): string => $symbol . gst::money($v, $currency);
        $sm = get_string_manager();

        $items = [];
        foreach (json_decode($order->items_json ?: '[]', true) ?: [] as $i => $item) {
            $price = (float) ($item['price'] ?? 0);
            $discount = round($price * ((int) ($item['discount_pct'] ?? 0)) / 100, 2);
            $items[] = [
                'n'            => $i + 1,
                'name'         => (string) ($item['name'] ?? ''),
                'price'        => $fmt($price),
                'has_discount' => $discount > 0,
                'discount'     => $fmt($discount),
            ];
        }

        $refunds = [];
        foreach (self::refunds_of($order) as $r) {
            $refunds[] = ['date' => doc_view::date((int) $r->timecreated, $lang, true), 'amount' => $fmt($r->refunded)];
        }

        $docs = self::documents_of($order);
        $creditnotes = [];
        foreach ($docs['creditnotes'] as $cn) {
            $creditnotes[] = [
                'number' => (string) $cn->cn_number,
                'date'   => doc_view::date((int) $cn->timecreated, $lang),
                'url'    => (new \moodle_url('/local/sentientia_cart/creditnote.php', ['id' => $cn->id]))->out(false),
            ];
        }

        $tax = (float) $order->tax_amount;
        return [
            'l'               => doc_view::labels(self::LABELS, 'receipt_', $lang),
            'orderid'         => (int) $order->orderid,
            'paid_on'         => doc_view::date((int) ($order->timepaid ?: $order->timecreated), $lang, true),
            'buyer_name'      => (string) $order->billing_name,
            'buyer_email'     => (string) ($order->billing_email ?? ''),
            'items'           => $items,
            'subtotal'        => $fmt($order->subtotal),
            'has_discount'    => (float) $order->discount_amount > 0,
            'discount'        => $fmt($order->discount_amount),
            'tax'             => $fmt($tax),
            'total'           => $fmt($order->total_amount),
            'gateway'         => self::gateway_label((string) ($order->gateway ?? '')),
            'gateway_ref'     => (string) ($order->gateway_ref ?? ''),
            'has_refunds'     => !empty($refunds),
            'refunds'         => $refunds,
            'status_label'    => $sm->get_string('status_' . $order->status, 'local_sentientia_cart', null, $lang),
            'has_invoice'     => $docs['invoice'] !== null,
            'invoice_number'  => $docs['invoice'] ? (string) $docs['invoice']->invoice_number : '',
            'invoice_url'     => $docs['invoice']
                ? (new \moodle_url('/local/sentientia_cart/invoice.php', ['id' => $docs['invoice']->id]))->out(false) : '',
            'invoice_follows' => $docs['invoice_follows'],
            'has_creditnotes' => !empty($creditnotes),
            'creditnotes'     => $creditnotes,
        ];
    }
}
