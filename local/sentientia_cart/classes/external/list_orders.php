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

        $where = ["status <> 'open'"];  // Exclude in-progress carts.
        $sqlparams = [];

        if (!$can_view_all) {
            $where[] = 'userid = :uid';
            $sqlparams['uid'] = (int) $USER->id;
        } else {
            // ── B1 fix: tenant scoping for admin views ──────────────────
            // Even with :viewallorders, a non-siteadmin only sees orders
            // in their own tenant. Without this clause a Public-tenant
            // manager could list Airpay's order history. Site admins
            // pass through with `1=1`.
            [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter('');
            $where[] = $tnsql;
            $sqlparams = array_merge($sqlparams, $tnargs);
        }
        if ($status_filter !== '') {
            $where[] = 'status = :st';
            $sqlparams['st'] = $status_filter;
        }
        if ($tenant_filter > 0 && $can_view_all && is_siteadmin()) {
            // Only site admins get the "filter to specific tenant" knob;
            // tenant-bound managers are already scoped above and can't
            // override that.
            $where[] = 'costcenterid = :tn';
            $sqlparams['tn'] = $tenant_filter;
        }
        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('billing_email', ':s1', false) . ' OR '
                . $DB->sql_like('billing_name', ':s2', false) . ' OR '
                . 'CAST(orderid AS CHAR) ' . $DB->sql_like(null, ':s3', false) . ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
            $sqlparams['s3'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_cart_history} WHERE $wheresql",
            $sqlparams);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT id, orderid, userid, costcenterid, total_amount, currency,
                        status, gateway, billing_name, billing_email,
                        timecreated, timepaid
                   FROM {local_sentientia_cart_history}
                  WHERE $wheresql
               ORDER BY $sort $sortdir, id DESC",
                $sqlparams,
                $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $rows[] = [
                    'id'           => (int) $r->id,
                    'orderid'      => (int) ($r->orderid ?? 0),
                    'userid'       => (int) $r->userid,
                    'total_amount' => (float) $r->total_amount,
                    'currency'     => $r->currency,
                    'status'       => $r->status,
                    'gateway'      => $r->gateway ?? '',
                    'billing_name' => $r->billing_name ?? '',
                    'billing_email' => $r->billing_email ?? '',
                    'placed_on'    => userdate($r->timecreated, '%d %b %Y'),
                    'paid_on'      => $r->timepaid ? userdate($r->timepaid, '%d %b %Y') : '',
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
                ])),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
