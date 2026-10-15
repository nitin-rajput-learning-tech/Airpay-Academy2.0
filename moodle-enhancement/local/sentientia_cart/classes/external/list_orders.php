<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * List orders. Non-admins see only their own; admins with viewallorders
 * cap see all. Returns datatable-shape (total, rows, page, perpage).
 */
class list_orders extends external_api {

    /** SQL of the Placed date: the payment time, else the time the cart was opened (see placed_time()). */
    private const PLACED_SQL = 'COALESCE(NULLIF(h.timepaid, 0), h.timecreated)';

    /** The statuses cart_manager::refund() accepts. Keep the two in step. */
    private const REFUNDABLE_STATUSES = ['paid', 'partial_refund'];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'timecreated',
                                    string $sortdir = 'desc', int $page = 0,
                                    int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_cart:view', $ctx);

        $can_view_all = has_capability('local/sentientia_cart:viewallorders', $ctx);

        // Sort whitelist. 'placed' is the date the Placed column shows (the payment time, else the time the cart was
        // opened), so a click on that header sorts what the buyer reads (UI pass 2026-10-15).
        $allowed_sort = ['timecreated', 'placed', 'total_amount', 'status', 'orderid'];
        $sort = in_array($params['sort'], $allowed_sort, true)
            ? $params['sort'] : 'timecreated';
        $sortsql = $sort === 'placed' ? self::PLACED_SQL : 'h.' . $sort;
        $sortdir = strtolower($params['sortdir']) === 'asc' ? 'ASC' : 'DESC';

        $client_filters = json_decode($params['filters'] ?: '{}', true) ?: [];
        $status_filter = (string) ($client_filters['status'] ?? '');
        $tenant_filter = (int) ($client_filters['tenant'] ?? 0);

        // Every column is qualified: the buyer's user row is joined below (an imported order has no billing details).
        $where = ["h.status <> 'open'"];  // Exclude in-progress carts.
        $sqlparams = [];

        if (!$can_view_all) {
            $where[] = 'h.userid = :uid';
            $sqlparams['uid'] = (int) $USER->id;
            // ADR-032: an order imported from BizLMS is frozen, admin-only history. Its owner does not see it
            // (decision cart.imported_visibility), and an abandoned checkout, which only an import produces, is
            // never an owner's order either.
            $where[] = \local_sentientia_cart\imported_history::native_only_sql('h');
            $where[] = "h.status <> 'abandoned'";
        } else {
            // ── B1 fix: tenant scoping for admin views ──────────────────
            // Even with :viewallorders, a non-siteadmin only sees orders
            // in their own tenant. Without this clause a Public-tenant
            // manager could list Airpay's order history. Site admins
            // pass through with `1=1`.
            [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('h');
            $where[] = $tnsql;
            $sqlparams = array_merge($sqlparams, $tnargs);
            // ADR-032: imported orders are shown to administrators only while the reader flag is on.
            if (!\local_sentientia_cart\imported_history::orders_enabled()) {
                $where[] = \local_sentientia_cart\imported_history::native_only_sql('h');
            }
        }
        if ($status_filter !== '') {
            $where[] = 'h.status = :st';
            $sqlparams['st'] = $status_filter;
        }
        if ($tenant_filter > 0 && $can_view_all
                && \local_sentientia_platform\tenant::is_cross_tenant()) {
            // Only cross-tenant viewers (ADR-031) get the "filter to specific tenant" knob;
            // tenant-bound managers are already scoped above and can't
            // override that.
            $where[] = 'h.costcenterid = :tn';
            $sqlparams['tn'] = $tenant_filter;
        }
        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $search = [
                $DB->sql_like('h.billing_email', ':s1', false),
                $DB->sql_like('h.billing_name', ':s2', false),
                $DB->sql_like($DB->sql_cast_to_char('h.orderid'), ':s3', false),
            ];
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
            $sqlparams['s3'] = $term;
            if ($can_view_all) {
                // An imported order carries no billing details, so an administrator finds it by its buyer.
                $search[] = $DB->sql_like('u.email', ':s4', false);
                $search[] = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':s5', false);
                $sqlparams['s4'] = $term;
                $sqlparams['s5'] = $term;
            }
            $where[] = '(' . implode(' OR ', $search) . ')';
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_sentientia_cart_history} h
          LEFT JOIN {user} u ON u.id = h.userid
              WHERE $wheresql",
            $sqlparams);

        $rows = [];
        if ($total > 0) {
            $names = \core_user\fields::for_name()->get_sql('u');
            $records = $DB->get_records_sql(
                "SELECT h.id, h.orderid, h.userid, h.costcenterid, h.total_amount, h.currency,
                        h.status, h.gateway, h.billing_name, h.billing_email,
                        h.timecreated, h.timepaid, h.notes, h.legacy_source, h.invoice_due,
                        u.email AS buyer_email{$names->selects}
                   FROM {local_sentientia_cart_history} h
              LEFT JOIN {user} u ON u.id = h.userid
                  WHERE $wheresql
               ORDER BY $sortsql $sortdir, h.id DESC",
                $sqlparams,
                $params['page'] * $params['perpage'], $params['perpage']);
            // GST receipts and tax documents (2026-10-14): the receipt, tax invoice and credit note links of each order,
            // empty for every order while both flags are OFF.
            $documents = \local_sentientia_cart\receipts::history_links($records);
            // The Refund button (UI pass 2026-10-15): only for a holder of :refund who is also an order
            // administrator (the viewer of admin_orders.php); nobody else is sent a button.
            $canrefund = $can_view_all && has_capability('local/sentientia_cart:refund', $ctx);
            $refunded = $canrefund ? self::refunded_amounts($records) : [];
            foreach ($records as $r) {
                // An order without billing details (every imported one) shows its buyer to order administrators.
                $billingname = (string) ($r->billing_name ?? '');
                $billingemail = (string) ($r->billing_email ?? '');
                if ($can_view_all && $billingname === '') {
                    $billingname = fullname($r);
                }
                if ($can_view_all && $billingemail === '') {
                    $billingemail = (string) ($r->buyer_email ?? '');
                }
                $totalstr = self::total_label($r);
                $rows[] = [
                    'id'           => (int) $r->id,
                    'orderid'      => (int) ($r->orderid ?? 0),
                    'userid'       => (int) $r->userid,
                    'total_amount' => (float) $r->total_amount,
                    // The total with its currency symbol, formatted as the receipt and the tax documents are.
                    'total_str'    => $totalstr,
                    'currency'     => $r->currency,
                    'status'       => $r->status,
                    // The status in words (status_<key>), not the raw key (partial_refund).
                    'status_label' => self::status_label((string) $r->status),
                    'gateway'      => $r->gateway ?? '',
                    'billing_name' => $billingname,
                    'billing_email' => $billingemail,
                    // When the order was placed: the payment time, else (an order not paid yet) the time the cart was
                    // opened, which is the only time such an order has. The receipt dates an order the same way.
                    'placed_on'    => userdate(self::placed_time($r), '%d %b %Y'),
                    'paid_on'      => $r->timepaid ? userdate($r->timepaid, '%d %b %Y') : '',
                    // Staff notes (the ADR-031 "Refund due" line, gateway
                    // failure reasons): order admins only, as get_order.
                    // Shown in the admin_orders.php "Staff notes" column.
                    'notes'        => $can_view_all ? (string) ($r->notes ?? '') : '',
                    'documents'    => (string) ($documents[(int) $r->id] ?? ''),
                    'actions'      => $canrefund
                        ? self::refund_button($r, (float) ($refunded[(int) $r->id] ?? 0), $totalstr) : '',
                ];
            }
        }
        return [
            'total'   => $total,
            'rows'    => $rows,
            'page'    => $params['page'],
            'perpage' => $params['perpage'],
        ];
    }

    /**
     * When an order was placed, for the Placed column: the payment time, else the time the cart was opened.
     *
     * The history row has no separate checkout time (checkout() only stamps timemodified, which every later change
     * moves too), so an order that is not paid yet, or never will be, is dated by its cart. The receipt dates an
     * order the same way (receipts::context(), paid_on).
     *
     * @param \stdClass $r A local_sentientia_cart_history row.
     * @return int UNIX time
     */
    public static function placed_time(\stdClass $r): int {
        return (int) ($r->timepaid ?: $r->timecreated);
    }

    /**
     * An order status in words. A key without a string (none today) is shown as it is rather than as [[key]].
     *
     * @param string $status A history status (paid, partial_refund, ...).
     * @return string
     */
    public static function status_label(string $status): string {
        $id = 'status_' . $status;
        return get_string_manager()->string_exists($id, 'local_sentientia_cart')
            ? get_string($id, 'local_sentientia_cart') : $status;
    }

    /**
     * An order total with its currency symbol, formatted as the receipt and the tax documents format theirs.
     *
     * @param \stdClass $r A local_sentientia_cart_history row (total_amount, currency).
     * @return string e.g. ₹1,180.00
     */
    public static function total_label(\stdClass $r): string {
        $currency = (string) $r->currency;
        return \local_sentientia_cart\invoicer::currency_symbol($currency)
            . \local_sentientia_cart\gst::money($r->total_amount, $currency);
    }

    /**
     * Can this order be refunded at all: paid or part-refunded, and not an order imported from BizLMS.
     *
     * @param \stdClass $r A local_sentientia_cart_history row (status, legacy_source).
     * @return bool
     */
    private static function in_refundable_state(\stdClass $r): bool {
        return in_array($r->status, self::REFUNDABLE_STATUSES, true)
            && !\local_sentientia_cart\imported_history::is_imported($r);
    }

    /**
     * What has been refunded so far, for the orders of one page that can still be refunded: one query, summed over
     * the ledger the way cart_manager::refund() sums it.
     *
     * @param \stdClass[] $records The history rows of the page.
     * @return float[] historyid => amount refunded (positive); an order never refunded has no entry
     */
    public static function refunded_amounts(array $records): array {
        global $DB;
        $ids = [];
        foreach ($records as $r) {
            if (self::in_refundable_state($r)) {
                $ids[] = (int) $r->id;
            }
        }
        if (!$ids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'rfd');
        $sums = $DB->get_records_sql(
            "SELECT historyid, COALESCE(SUM(ABS(amount)), 0) AS refunded
               FROM {local_sentientia_cart_ledger}
              WHERE historyid $insql AND event_type IN ('refund_full', 'refund_partial')
           GROUP BY historyid", $inparams);
        $refunded = [];
        foreach ($sums as $historyid => $sum) {
            $refunded[(int) $historyid] = (float) $sum->refunded;
        }
        return $refunded;
    }

    /**
     * The Refund button of one row of admin_orders.php, or '' when the order cannot be refunded.
     *
     * The attributes are the ones amd/src/admin_orders.js reads (data-action="refund-order", data-historyid,
     * data-orderid, data-total) to open the Refund dialog. The caller has already checked :refund.
     *
     * @param \stdClass $r A local_sentientia_cart_history row.
     * @param float $refunded What has been refunded already (refunded_amounts()).
     * @param string $totalstr The total as shown in the Total column, repeated in the dialog.
     * @return string Escaped HTML
     */
    public static function refund_button(\stdClass $r, float $refunded, string $totalstr): string {
        if (!self::in_refundable_state($r) || round((float) $r->total_amount - $refunded, 2) <= 0) {
            return '';  // Wrong status, imported, or nothing left to refund.
        }
        return \html_writer::tag('button',
            \html_writer::tag('i', '', ['class' => 'fa fa-undo', 'aria-hidden' => 'true'])
                . ' ' . s(get_string('refund_button', 'local_sentientia_cart')),
            [
                'type'            => 'button',
                'class'           => 'btn btn-outline-danger btn-sm',
                'data-action'     => 'refund-order',
                'data-historyid'  => (int) $r->id,
                'data-orderid'    => (int) ($r->orderid ?? 0),
                'data-total'      => $totalstr,
            ]);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, ''),
                    'orderid'      => new external_value(PARAM_INT, ''),
                    'userid'       => new external_value(PARAM_INT, ''),
                    'total_amount' => new external_value(PARAM_FLOAT, ''),
                    'total_str'    => new external_value(PARAM_TEXT, 'The total with its currency symbol, e.g. ₹1,180.00'),
                    'currency'     => new external_value(PARAM_ALPHA, ''),
                    'status'       => new external_value(PARAM_ALPHANUMEXT, ''),
                    'status_label' => new external_value(PARAM_TEXT, 'The status in words, in the viewer\'s language'),
                    'gateway'      => new external_value(PARAM_ALPHANUMEXT, ''),
                    'billing_name' => new external_value(PARAM_TEXT, ''),
                    'billing_email' => new external_value(PARAM_TEXT, ''),
                    'placed_on'    => new external_value(PARAM_TEXT, ''),
                    'paid_on'      => new external_value(PARAM_TEXT, ''),
                    // PARAM_RAW: mark_failed() stores the raw gateway payload
                    // here; PARAM_TEXT would strip tags, clean_param() would change
                    // the value and validate_param() would throw, breaking the
                    // whole list. The datatable escapes plain columns.
                    'notes'        => new external_value(PARAM_RAW,
                        'Staff notes, e.g. an ADR-031 refund due; empty unless the viewer holds :viewallorders'),
                    // Escaped HTML built by html_writer (the datatable column has format html): links to the receipt,
                    // the tax invoice and the credit notes of the order. Empty while both GST flags are OFF.
                    'documents'    => new external_value(PARAM_RAW,
                        'Links to the receipt, tax invoice and credit notes of the order (HTML); empty when there are none'),
                    // Escaped HTML built by html_writer (the admin_orders.php column has format html): the Refund
                    // button of an order that can still be refunded. Empty for everyone who does not hold :refund.
                    'actions'      => new external_value(PARAM_RAW,
                        'Refund button (HTML) for an order that can be refunded; empty unless the viewer holds :refund'),
                ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
