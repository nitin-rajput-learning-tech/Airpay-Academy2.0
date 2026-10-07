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

        // Sort whitelist.
        $allowed_sort = ['timecreated', 'total_amount', 'status', 'orderid'];
        $sort = in_array($params['sort'], $allowed_sort, true)
            ? $params['sort'] : 'timecreated';
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
                        h.timecreated, h.timepaid, h.notes, u.email AS buyer_email{$names->selects}
                   FROM {local_sentientia_cart_history} h
              LEFT JOIN {user} u ON u.id = h.userid
                  WHERE $wheresql
               ORDER BY h.$sort $sortdir, h.id DESC",
                $sqlparams,
                $params['page'] * $params['perpage'], $params['perpage']);
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
                $rows[] = [
                    'id'           => (int) $r->id,
                    'orderid'      => (int) ($r->orderid ?? 0),
                    'userid'       => (int) $r->userid,
                    'total_amount' => (float) $r->total_amount,
                    'currency'     => $r->currency,
                    'status'       => $r->status,
                    'gateway'      => $r->gateway ?? '',
                    'billing_name' => $billingname,
                    'billing_email' => $billingemail,
                    'placed_on'    => userdate($r->timecreated, '%d %b %Y'),
                    'paid_on'      => $r->timepaid ? userdate($r->timepaid, '%d %b %Y') : '',
                    // Staff notes (the ADR-031 "Refund due" line, gateway
                    // failure reasons): order admins only, as get_order.
                    // Shown in the admin_orders.php "Staff notes" column.
                    'notes'        => $can_view_all ? (string) ($r->notes ?? '') : '',
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

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, ''),
                    'orderid'      => new external_value(PARAM_INT, ''),
                    'userid'       => new external_value(PARAM_INT, ''),
                    'total_amount' => new external_value(PARAM_FLOAT, ''),
                    'currency'     => new external_value(PARAM_ALPHA, ''),
                    'status'       => new external_value(PARAM_ALPHANUMEXT, ''),
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
                ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
