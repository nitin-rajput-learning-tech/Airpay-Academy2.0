<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Gives an order number to each imported order that BizLMS never numbered (ADR-032, mapping doc section 13:
 * "a NULL or 0 identifier: the line becomes its own order, with orderid allocated from the new sequence above the
 * floor").
 *
 * return.php and callback.php open an order by its number, so an order without one could not be opened. The
 * history step cannot allocate it (a transform is pure and cannot see the numbers the writer will give), so it
 * writes the order with no number and this second pass numbers it: above every number an imported order, a
 * ledger row, an invoice or the BizLMS generator can hold (support::order_floor()), one after the other, in the
 * order the orders were imported.
 *
 * IDEMPOTENT: it only fills a number that is missing, so a run that finds every order numbered writes nothing,
 * and a resumed run carries on from the highest number given.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class order_numbers_step extends recompute_step {

    /**
     * @return string
     */
    public function key(): string {
        return 'cart.order_numbers';
    }

    /**
     * @return string
     */
    public function targettable(): string {
        return history_step::TARGET;
    }

    /**
     * @param int[] $targetids Imported orders, in the order they were imported.
     * @param context $ctx
     * @return outcome[] Update outcomes only.
     */
    public function recompute(array $targetids, context $ctx): array {
        global $DB;
        if (!$targetids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $targetids), SQL_PARAMS_NAMED, 'blmord');
        $params['src'] = support::SOURCE_LABEL;
        $missing = $DB->get_records_select(history_step::TARGET,
            "id {$insql} AND legacy_source = :src AND orderid IS NULL", $params, '', 'id');
        if (!$missing) {
            return [];
        }

        $next = support::order_floor();
        $out = [];
        foreach ($targetids as $id) {
            if (!isset($missing[(int) $id])) {
                continue;
            }
            $next++;
            $out[] = outcome::update(history_step::TARGET, (int) $id, (object) ['orderid' => $next]);
        }
        return $out;
    }
}
