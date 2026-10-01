<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * The cart importer (ADR-032, mapping doc section 13): the BizLMS shopping cart's orders, ledger, invoices and
 * credit journal become Sentientia cart history.
 *
 * Money history is imported as FROZEN, ADMIN-ONLY history (decisions cart.imported_visibility,
 * cart.admin_refund_imported_orders):
 *  - every imported order carries history.legacy_source = bizlms, and cart_manager::mark_paid(), mark_failed()
 *    and refund() refuse such a row, so no webhook, no admin and no web service can move imported money;
 *  - the cart's readers hide an imported order from its owner, and show it to an order administrator only while
 *    a flag is on (default OFF, see imported_history);
 *  - an order is never given a status the native engine acts on (open, pending, failed, refunded,
 *    partial_refund), so nothing adopts it, retries it or refunds it.
 *
 * Nothing is sent, enrolled, issued or recomputed by the import: the steps return outcomes, the writer writes
 * them, and none of them calls the cart manager, the invoicer or the notifier (the static scan fails the build if
 * one does). The two finance questions that are still open (what happens to the credit balances, whether the
 * ERPNext invoices are the legal tax invoices) are NOT declared here: the importer does the same whatever the
 * answer is, so it does not wait for it.
 *
 * Depends on nothing: the tenant of an order is the INT root of the buyer's open_path (history.costcenterid), not
 * a path in the organisation tree, so the org importer need not have run.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class importer implements \local_sentientia_platform\bizlms\importer {

    /** Plugin version that carries legacy_source on the history and the credit journal table. */
    public const REQUIRES_VERSION = 2026100101;

    /**
     * @return string
     */
    public function feature(): string {
        return 'cart';
    }

    /**
     * @return string
     */
    public function component(): string {
        return 'local_sentientia_cart';
    }

    /**
     * @return int
     */
    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    /**
     * @return string[]
     */
    public function depends(): array {
        return [];
    }

    /**
     * The five BizLMS tables. The history is required; the others exist on a site whose BizLMS cart is current,
     * and a step whose table is missing is recorded as not applicable.
     *
     * @return array<string, source_spec>
     */
    public function sources(): array {
        $status = [0 => 'pending', 1 => 'aborted', 2 => 'success', 3 => 'canceled'];
        $methods = [
            '' => 'not recorded', 0 => 'online', 1 => 'cashier', 2 => 'credits', 3 => 'cashier cash', 4 => 'debit card',
            5 => 'credit card', 7 => 'manual',
        ];
        return [
            'local_biz_cart_history' => new source_spec('local_biz_cart_history', true,
                ['paymentstatus' => $status, 'payment' => $methods],
                ['usecredit', 'costcenter', 'area', 'serviceperiodstart', 'serviceperiodend', 'canceluntil', 'tax',
                    'taxpercentage', 'taxcategory', 'discount']),
            'local_biz_cart_id' => new source_spec('local_biz_cart_id', false),
            'local_biz_cart_ledger' => new source_spec('local_biz_cart_ledger', false,
                ['paymentstatus' => $status, 'payment' => $methods + [6 => 'credits paid back by cash',
                    8 => 'credits paid back by transfer', 9 => 'credits correction']],
                ['tax', 'taxpercentage', 'taxcategory', 'credits', 'fee', 'costcenter', 'area', 'annotation',
                    'schistoryid', 'canceluntil', 'discount']),
            'local_biz_cart_invoices' => new source_spec('local_biz_cart_invoices', false),
            'local_biz_cart_credits' => new source_spec('local_biz_cart_credits', false),
        ];
    }

    /**
     * The gateway's own tables are READ as evidence of a payment (the transaction id, the time it was paid) and
     * never imported: the payment-gateway group decides what becomes of them.
     *
     * @return array<string, string>
     */
    public function declined_tables(): array {
        return [
            'paygw_airpay' => 'payment gateway attempts: read in place as evidence of a payment (gateway reference, '
                . 'time paid) and never imported; the payment-gateway decision owns them',
            'paygw_airpay_errorlog' => 'payment gateway error log: read in place as evidence (time paid), never imported',
            'paygw_course_enrolmentlog' => 'payment gateway enrolment log: read in place as evidence (gateway '
                . 'transaction id), never imported',
        ];
    }

    /**
     * @return string[]
     */
    public function target_tables(): array {
        return [
            history_step::TARGET,
            ledger_step::TARGET,
            invoices_step::TARGET,
            credits_step::TARGET,
            credits_step::BALANCE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function core_writes(): array {
        return [];
    }

    /**
     * The tenant columns of the cart tables hold an INT root (costcenterid), not a path in the organisation tree,
     * so the generic tenant verify (which checks normalised paths) does not apply; verify() checks the roots.
     *
     * @return array<string, string>
     */
    public function tenant_columns(): array {
        return [];
    }

    /**
     * @return reason[]
     */
    public function reasons(): array {
        return [
            // A line folded into the order row built from its sibling lines.
            new reason('order_line', false, false),
            // An order whose lines name two buyers. All or nothing: no half of it is written.
            new reason('mixed_buyers', false, true),
            // A buyer or credit holder who has no user row: nobody can see, export or erase the row.
            new reason('orphan_user', false, true),
            // Only with the decision tenant.unresolved.cart = skip.
            new reason('tenant_unresolved', false, true),
            // A currency that is not three letters, or an order that mixes two.
            new reason('currency_invalid', false, true),
            // A credit booking in a currency other than INR (the balance table holds one per user).
            new reason('currency_not_inr', false, true),
            // Only with the decision cart.abandoned = skip.
            new reason('abandoned_not_imported', false, false),
            // A generator row folded into the order it numbered, and one that numbered none.
            new reason('cart_id_of_order', false, false),
            new reason('cart_id_unused', false, false),
            // Invoices whose order, number or row cannot be imported.
            new reason('invoice_without_order', false, true),
            new reason('invoice_without_number', false, true),
            new reason('invoice_number_too_long', false, true),
            new reason('dup_invoice_number', false, false),
        ];
    }

    /**
     * Owner choices, all from the decisions file and none with a default. The two finance-confirm keys
     * (cart.credit_balances, cart.erpnext_invoices_legal) are deliberately NOT here: a declared key that is
     * finance-confirm blocks the feature, and what the import does with credits and invoices does not depend on
     * the answer.
     *
     * Each allowed list is what this importer implements. A different value in the file blocks the feature at
     * preflight instead of being silently ignored.
     *
     * @return decision[]
     */
    public function decisions(): array {
        return [
            new decision('tenant.unresolved.cart', 'An order or credit holder whose tenant cannot be resolved',
                true, null, ['pathless', 'skip']),
            new decision('cart.synthesize_ledger', 'Synthesize ledger payment rows BizLMS never wrote',
                true, null, [false]),
            new decision('cart.order_tenant', 'Tenant of an imported order', true, null, ['buyer']),
            new decision('cart.abandoned', 'Abandoned checkouts', true, null, ['admin_only', 'skip']),
            new decision('cart.imported_visibility', 'Who sees imported money history', true, null, ['admin_only']),
            new decision('cart.admin_refund_imported_orders', 'May administrators refund imported orders',
                true, null, [false]),
            new decision('cart.cash_drawer_rows_without_order', 'Cash-drawer ledger rows that belong to no order',
                true, null, ['import_admin_only']),
        ];
    }

    /**
     * @return bool
     */
    public function atomic(): bool {
        return false;
    }

    /**
     * @return array
     */
    public function steps(): array {
        return [
            new history_step(),
            new cart_id_step(),
            new ledger_step(),
            new invoices_step(),
            new credits_step(),
            new order_numbers_step(),
        ];
    }

    /**
     * Read-only. Reports what the import will meet, so the operator sees it before --apply.
     *
     * @param context $ctx
     * @return preflight
     */
    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        $legacy = $ctx->legacy;
        $dbman = $DB->get_manager();

        // The BizLMS settings the order numbers and totals depend on.
        $base = get_config(support::BIZLMS_COMPONENT, 'uniqueidentifier');
        if ($base !== false && !ctype_digit((string) $base)) {
            $pf->block('uniqueidentifier_not_a_number');
        } else if ($base === false) {
            $pf->warn('uniqueidentifier_unset_assumed_zero');
        }
        if (get_config(support::BIZLMS_COMPONENT, 'itempriceisnet') === false) {
            $pf->warn('itempriceisnet_unset_assumed_gross');
        }

        if ($legacy->exists('local_biz_cart_history')) {
            $this->warn_count($pf, 'lines_without_order_number', (int) $DB->count_records_select(
                'local_biz_cart_history', 'identifier IS NULL OR identifier <= 0'));
            $this->warn_count($pf, 'orders_with_mixed_buyers', (int) $DB->count_records_sql(
                'SELECT COUNT(1) FROM (SELECT identifier FROM {local_biz_cart_history} WHERE identifier > 0
                   GROUP BY identifier HAVING COUNT(DISTINCT userid) > 1) m'));
            $this->warn_count($pf, 'lines_of_unknown_buyers', (int) $DB->count_records_sql(
                'SELECT COUNT(1) FROM {local_biz_cart_history} t LEFT JOIN {user} u ON u.id = t.userid
                  WHERE u.id IS NULL'));

            // A native order that already holds a number the import is about to give an order: callback.php and
            // return.php open an order by its number, so two orders with one number would be confused.
            $this->block_count($pf, 'native_order_number_taken', (int) $DB->count_records_sql(
                'SELECT COUNT(1) FROM {local_sentientia_cart_history} n
                  WHERE n.legacy_source IS NULL AND n.orderid IS NOT NULL
                    AND n.orderid IN (SELECT h.identifier FROM {local_biz_cart_history} h WHERE h.identifier > 0)'));

            if (!$legacy->exists('paygw_airpay')) {
                $this->warn_count($pf, 'gateway_evidence_missing:paygw_airpay', (int) $DB->count_records_select(
                    'local_biz_cart_history', "payment = '0'"));
            }
        }

        if ($legacy->exists('local_biz_cart_ledger')) {
            // schistoryid was added to the ledger by a later BizLMS release; a site that never took it has no column.
            $noorder = '(identifier IS NULL OR identifier <= 0)';
            if ($legacy->has_column('local_biz_cart_ledger', 'schistoryid')) {
                $noorder .= ' AND (schistoryid IS NULL OR schistoryid <= 0)';
            }
            $this->warn_count($pf, 'ledger_rows_without_any_order',
                (int) $DB->count_records_select('local_biz_cart_ledger', $noorder));
        }

        if ($legacy->exists('local_biz_cart_credits')) {
            // A user whose credit balance row already exists, and was not made by an earlier run of this import:
            // the importer writes the balance once and would collide with it on the unique key.
            $this->block_count($pf, 'credit_balance_already_exists', (int) $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . credits_step::BALANCE . '} c
                  WHERE c.userid IN (SELECT t.userid FROM {local_biz_cart_credits} t)
                    AND NOT EXISTS (SELECT 1 FROM {local_sentientia_legacymap} m
                                     WHERE m.targettable = :bal AND m.targetid = c.id AND m.outcome = :imp)',
                ['bal' => credits_step::BALANCE, 'imp' => 'imported']));
            $this->warn_count($pf, 'credit_rows_not_inr', (int) $DB->count_records_select(
                'local_biz_cart_credits', "currency IS NOT NULL AND currency <> '' AND currency <> 'INR'"));
        }

        if ($dbman->table_exists('local_biz_cart_invoices')) {
            // The invoicer must never mint an ERPNEXT number, so a site whose own prefix is that is refused.
            if (strtoupper(trim((string) get_config('local_sentientia_cart', 'invoice_prefix')))
                    === rtrim(invoices_step::NUMBER_PREFIX, '-')) {
                $pf->block('invoice_prefix_is_the_erpnext_prefix');
            }
        }
        return $pf;
    }

    /**
     * Read-only, after load. Everything here is a fact the importer promises about what it wrote.
     *
     * @param context $ctx
     * @return string[]
     */
    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $src = ['src' => support::SOURCE_LABEL];
        $history = history_step::TARGET;
        $map = 'local_sentientia_legacymap';

        // No imported order is in a state the native engine acts on.
        $this->fail_count($failures, 'imported_order_in_a_native_status', (int) $DB->count_records_select($history,
            "legacy_source = :src AND status IN ('open', 'pending', 'failed', 'refunded', 'partial_refund')", $src));
        $this->fail_count($failures, 'imported_order_with_billing_details', (int) $DB->count_records_select($history,
            'legacy_source = :src AND (billing_name IS NOT NULL OR billing_email IS NOT NULL OR billing_phone IS NOT NULL
               OR billing_address IS NOT NULL OR billing_gstn IS NOT NULL)', $src));
        $this->fail_count($failures, 'imported_order_without_a_number', (int) $DB->count_records_select($history,
            'legacy_source = :src AND orderid IS NULL', $src));
        $this->fail_count($failures, 'imported_order_total_does_not_add_up', (int) $DB->count_records_select($history,
            'legacy_source = :src AND ABS(total_amount - (subtotal - discount_amount + tax_amount)) > 0.011', $src));
        $this->fail_count($failures, 'imported_order_number_used_twice', (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM (SELECT orderid FROM {' . $history . '} WHERE legacy_source = :src
               AND orderid IS NOT NULL GROUP BY orderid HAVING COUNT(1) > 1) d', $src));
        if ($ctx->decision('cart.abandoned') === 'skip') {
            $this->fail_count($failures, 'abandoned_order_imported_although_the_decision_says_not_to',
                (int) $DB->count_records_select($history, "legacy_source = :src AND status = 'abandoned'", $src));
        }

        // A ledger row that names an order names one that exists.
        $this->fail_count($failures, 'imported_ledger_row_without_its_order', (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . ledger_step::TARGET . '} l
               JOIN {' . $map . '} m ON m.targettable = :lt AND m.targetid = l.id AND m.outcome = :imp
          LEFT JOIN {' . $history . '} h ON h.id = l.historyid
              WHERE l.historyid > 0 AND h.id IS NULL',
            ['lt' => ledger_step::TARGET, 'imp' => 'imported']));

        // An imported invoice is a reference: ERPNEXT-<id>, legacy_external, never a number Sentientia issued.
        $this->fail_count($failures, 'imported_invoice_is_not_a_reference', (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . invoices_step::TARGET . '} i
               JOIN {' . $map . '} m ON m.targettable = :it AND m.targetid = i.id AND m.outcome = :imp
              WHERE i.status <> :st OR NOT ' . $DB->sql_like('i.invoice_number', ':prefix', true, true, false),
            ['it' => invoices_step::TARGET, 'imp' => 'imported', 'st' => 'legacy_external',
                'prefix' => $DB->sql_like_escape(invoices_step::NUMBER_PREFIX) . '%']));

        // A credit balance equals the newest booking of its journal.
        $this->fail_count($failures, 'credit_balance_differs_from_the_journal', (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . credits_step::BALANCE . '} c
               JOIN {' . $map . '} m ON m.targettable = :bt AND m.targetid = c.id AND m.outcome = :imp
               JOIN {' . credits_step::TARGET . '} t ON t.userid = c.userid
                AND t.id = (SELECT MAX(t2.id) FROM {' . credits_step::TARGET . '} t2 WHERE t2.userid = c.userid)
              WHERE ABS(c.balance - t.balance_after) > 0.011',
            ['bt' => credits_step::BALANCE, 'imp' => 'imported']));

        // The tenant roots are 0 (none) or registered, and the currencies are currencies.
        foreach ([$history, invoices_step::TARGET, credits_step::TARGET] as $table) {
            foreach ($DB->get_fieldset_sql('SELECT DISTINCT costcenterid FROM {' . $table . '}') as $root) {
                $root = (int) $root;
                if ($root !== 0 && !evidence::of($ctx->legacy)->root_registered($root)) {
                    $failures[] = 'invalid_tenant_value:' . $table . '.costcenterid=' . $root;
                }
            }
        }
        foreach ([$history, ledger_step::TARGET, invoices_step::TARGET] as $table) {
            foreach ($DB->get_fieldset_sql('SELECT DISTINCT currency FROM {' . $table . '}') as $currency) {
                if (!preg_match('/^[A-Z]{3}$/', (string) $currency)) {
                    $failures[] = 'invalid_currency:' . $table;
                    break;
                }
            }
        }
        return $failures;
    }

    /**
     * Outside any transaction. Records the highest order number the import used or reserved, so that
     * cart_manager::checkout() never gives a native order a number an imported order holds. (The mapping doc
     * planned a placeholder row in local_sentientia_cart_id for this; the framework writes no row with a chosen
     * id except by PRESERVE, which cannot express "base plus the source id", so the floor is a setting the
     * checkout honours.) Idempotent: the same value is set again.
     *
     * @param context $ctx
     * @return void
     */
    public function finalise(context $ctx): void {
        $floor = support::order_floor();
        if ($floor > 0) {
            set_config(support::FLOOR_CONFIG, $floor, 'local_sentientia_cart');
        }
    }

    /**
     * Record a count as a warning when it is not zero.
     *
     * @param preflight $pf
     * @param string $code
     * @param int $count
     * @return void
     */
    private function warn_count(preflight $pf, string $code, int $count): void {
        if ($count > 0) {
            $pf->warn($code . ':' . $count);
        }
    }

    /**
     * Record a count as a blocker when it is not zero.
     *
     * @param preflight $pf
     * @param string $code
     * @param int $count
     * @return void
     */
    private function block_count(preflight $pf, string $code, int $count): void {
        if ($count > 0) {
            $pf->block($code . ':' . $count);
        }
    }

    /**
     * Record a failure line when a count is not zero.
     *
     * @param string[] $failures
     * @param string $code
     * @param int $count
     * @return void
     */
    private function fail_count(array &$failures, string $code, int $count): void {
        if ($count > 0) {
            $failures[] = $code . ':' . $count;
        }
    }
}
