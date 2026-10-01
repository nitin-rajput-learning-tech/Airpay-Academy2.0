<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_biz_cart_id -> the order it numbered (ADR-032, mapping doc section 13).
 *
 * The BizLMS table is a bare number generator: a row is created every time a cart is opened for checkout, and
 * the order number (the identifier) is the configured base plus that row's id. It carries no buyer and nothing
 * else. Sentientia keeps the order number in history.orderid (receipts and Moodle's payments rows refer to the
 * identifier, and the order row keeps it), so a generator row is folded into the order it numbered, and one that
 * numbered no order (a checkout page view that produced none) is archived: it stays in the legacy table.
 *
 * This step does not preserve ids. The ADR and the mapping doc planned to copy the generator rows with the
 * identifier as the id, but the framework's PRESERVE writes the SOURCE row id, which is not the identifier
 * unless the BizLMS base is 0, and a row that is not the identifier would number nothing. What the copy was
 * for, that a new order never reuses a legacy number, is done by finalise() recording the highest imported
 * number and cart_manager::checkout() keeping native numbers above it.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cart_id_step extends step {

    /**
     * @return string
     */
    public function key(): string {
        return 'cart.id';
    }

    /**
     * @return string
     */
    public function sourcetable(): string {
        return 'local_biz_cart_id';
    }

    /**
     * The order a generator row folds into lives here.
     *
     * @return string
     */
    public function targettable(): string {
        return history_step::TARGET;
    }

    /**
     * @param \stdClass[] $rows
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx->legacy);
        $base = support::unique_identifier_base();

        // The first history line of the order each generator row numbered, and what the import made of it.
        $lines = [];
        foreach ($rows as $row) {
            $first = $evidence->first_line($base + (int) $row->id);
            if ($first !== null) {
                $lines[$first] = true;
            }
        }
        $orders = $lines ? $ctx->map->resolve_many('local_biz_cart_history', array_keys($lines)) : [];

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $first = $evidence->first_line($base + $id);
            $target = $first === null ? null : ($orders[$first] ?? null);
            if ($target === null) {
                $out[] = outcome::archive($id, 'cart_id_unused');
            } else {
                $out[] = outcome::fold($id, history_step::TARGET, $target, 'cart_id_of_order');
            }
        }
        return $out;
    }
}
