<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * Storefront basket -> order cart bridge (persona pass 2026-09-30, D2).
 *
 * The storefront basket (cart.php, commerce::get_cart(), kept in $SESSION) has always
 * ended for a paid course in a disabled "Payment Coming Soon" button: nothing carried
 * its lines to the order cart (local_sentientia_cart), which is where billing details,
 * the payment gateway, the order, the invoice and the enrolment on payment live.
 *
 * With sentientia.catalog.storefront_checkout.enabled ON, a logged-in buyer who may
 * purchase gets a "Proceed to checkout" button instead. It hands each PAID line to
 * \local_sentientia_cart\cart_manager::add_item() and sends the buyer to the order
 * cart's checkout page. Nothing about payment is decided here:
 *
 *  - The order cart re-applies its own gates to every line: the ADR-031 catalogue
 *    purchase gate (cart_manager::can_buy_course(): a course the buyer's own
 *    catalogue does not show is refused, whatever the storefront session held), the
 *    price (an enabled enrol_fee instance; a course with none is refused rather
 *    than given a price), and "not already enrolled". checkout() and mark_paid()
 *    repeat the purchase gate later.
 *  - The buyer must hold local/sentientia_cart:purchase and the cart must be switched
 *    on for their tenant (cart_manager::is_enabled_for_user(), the enabled_tenants
 *    setting), exactly as cart's own pages require.
 *
 * One price source (owner decision cart.price_source, 2026-10-07): the basket shows
 * and totals commerce::get_course_price(), which reads the enabled enrol_fee
 * instance, the same cost the order cart charges; the config setting
 * course_price_<id> is only a fallback for a course with no fee instance, and the
 * order cart refuses such a course rather than pricing it. hand_off() still
 * compares the basket's price with the order cart's for every line it moves and
 * reports a difference ('pricediffers'), which can now only mean the fee changed
 * after the line was put in the basket, so the buyer is told to check the amount on
 * the checkout page, which shows what will be charged.
 *
 * Default OFF: with the flag off (or any condition above unmet) can_hand_off() is
 * false and cart.php renders exactly what it did before. The flag stays off until
 * the payment gateway has been verified in sandbox.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 */
class checkout_bridge {

    /** Feature flag (db/feature_flags.php), default OFF. */
    public const FLAG = 'sentientia.catalog.storefront_checkout.enabled';

    /** The capability the order cart demands for add-to-cart and checkout. */
    private const PURCHASE_CAP = 'local/sentientia_cart:purchase';

    /**
     * Is the flag ON for this user's tenant?
     *
     * @param \stdClass $user a full user record (carries open_path)
     * @return bool
     */
    public static function is_enabled(\stdClass $user): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled_for_tenant(
            self::FLAG, enrolment::tenant_root($user));
    }

    /**
     * May this user hand the storefront basket to the order cart?
     *
     * All must hold: a real login (never the guest, who is sent to log in first), the
     * flag ON for their tenant, the order cart installed, the purchase capability, and
     * the cart switched on for their tenant. Cheapest and most common refusal first: a
     * site with the flag OFF pays for one flag lookup and nothing else.
     *
     * @param \stdClass $user a full user record (e.g. $USER)
     * @return bool
     */
    public static function can_hand_off(\stdClass $user): bool {
        if (empty($user->id) || isguestuser($user)) {
            return false;
        }
        if (!self::is_enabled($user)) {
            return false;
        }
        if (!class_exists('\\local_sentientia_cart\\cart_manager')) {
            return false;
        }
        if (!has_capability(self::PURCHASE_CAP, \context_system::instance(), $user)) {
            return false;
        }
        return \local_sentientia_cart\cart_manager::is_enabled_for_user($user);
    }

    /**
     * Move the storefront basket's PAID lines into the buyer's order cart.
     *
     * Per line, cart_manager::add_item() decides:
     *  - added:     now in the order cart (idempotent: a line already there counts as added);
     *               removed from the storefront basket.
     *  - redundant: the buyer is already enrolled; removed from the storefront basket, since
     *               it can never be bought.
     *  - refused:   the buyer may not buy it (another tenant's course, the ADR-031 gate), it
     *               has no order-cart price, or it is otherwise unavailable. Left in the
     *               storefront basket, untouched.
     * Free lines are never touched: they enrol through the free path, not the order cart.
     *
     * Any other Throwable from add_item() (an \Error such as an undefined function or a
     * TypeError, or a non-Moodle exception: a moodle_exception, which includes a dml_exception,
     * is handled above) is treated as refused for THAT line, logged with debugging(), and the
     * loop goes on: lines already moved have left the basket, so aborting part-way would leave
     * the buyer without a message about what happened.
     *
     * 'pricediffers' is not an outcome of its own: it lists the ADDED lines whose order-cart
     * price is not the price the basket showed (the two are different sources; see the class
     * docblock). Those lines are still moved: the checkout page shows what will be charged.
     *
     * The caller must have checked can_hand_off() and the sesskey; this does not.
     *
     * @param int $userid the buyer
     * @param callable|null $adder (int $userid, int $courseid): \stdClass, defaults to
     *                             cart_manager::add_item(); a seam for tests only
     * @return array{added: int[], redundant: int[], refused: int[], pricediffers: int[]} course ids
     */
    public static function hand_off(int $userid, ?callable $adder = null): array {
        $adder = $adder ?? [\local_sentientia_cart\cart_manager::class, 'add_item'];
        $result = ['added' => [], 'redundant' => [], 'refused' => [], 'pricediffers' => []];

        foreach (commerce::get_cart() as $item) {
            if (!empty($item['is_free'])) {
                continue;
            }
            $courseid = (int) ($item['courseid'] ?? 0);
            if ($courseid <= 0) {
                continue;
            }
            try {
                $ordercart = $adder($userid, $courseid);
            } catch (\moodle_exception $e) {
                if ($e->errorcode === 'error_alreadyenrolled') {
                    $result['redundant'][] = $courseid;
                    commerce::remove_from_cart($courseid);
                } else {
                    $result['refused'][] = $courseid;
                }
                continue;
            } catch (\Throwable $e) {
                debugging('local_sentientia_catalog checkout_bridge: course ' . $courseid
                    . ' could not be moved to the order cart: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $result['refused'][] = $courseid;
                continue;
            }
            $result['added'][] = $courseid;
            if (self::prices_differ($item, $ordercart, $courseid)) {
                $result['pricediffers'][] = $courseid;
            }
            commerce::remove_from_cart($courseid);
        }

        return $result;
    }

    /**
     * Does the order cart's price for a line differ from the price the basket showed?
     *
     * @param array $item the storefront basket line
     * @param mixed $ordercart what add_item() returned (the cart row, with items_json)
     * @param int $courseid
     * @return bool false when the order cart's line cannot be found (nothing to compare)
     */
    private static function prices_differ(array $item, $ordercart, int $courseid): bool {
        if (!is_object($ordercart) || !isset($ordercart->items_json)) {
            return false;
        }
        foreach (json_decode((string) $ordercart->items_json, true) ?: [] as $line) {
            if ((int) ($line['courseid'] ?? 0) === $courseid) {
                return abs((float) ($line['price'] ?? 0) - (float) ($item['price'] ?? 0)) > 0.005;
            }
        }
        return false;
    }

    /**
     * How many FREE lines the storefront basket still holds. Free lines never go through
     * hand_off(); after it they are all that is left of a mixed basket (plus refused paid
     * lines), and the buyer is told so because "Enroll in All (Free)" is on the basket page.
     *
     * @return int
     */
    public static function free_lines_left(): int {
        return count(array_filter(commerce::get_cart(), fn($line) => !empty($line['is_free'])));
    }

    /**
     * Where the buyer goes after hand_off(): the order cart's checkout page when at least
     * one line arrived there, back to the storefront basket otherwise.
     *
     * @param array $result the value hand_off() returned
     * @return \moodle_url
     */
    public static function next_url(array $result): \moodle_url {
        if (!empty($result['added'])) {
            return new \moodle_url('/local/sentientia_cart/checkout.php');
        }
        return new \moodle_url('/local/sentientia_catalog/cart.php');
    }

    /**
     * Queue the buyer's notifications for a hand_off() result. Shown on the page next_url()
     * sends them to.
     *
     * @param array $result the value hand_off() returned
     */
    public static function notify(array $result): void {
        $component = 'local_sentientia_catalog';
        if (!empty($result['added'])) {
            \core\notification::success(
                get_string('storefront_checkout_moved', $component, count($result['added'])));
        }
        if (!empty($result['pricediffers'])) {
            \core\notification::warning(
                get_string('storefront_checkout_pricediffers', $component, count($result['pricediffers'])));
        }
        if (!empty($result['redundant'])) {
            \core\notification::info(
                get_string('storefront_checkout_redundant', $component, count($result['redundant'])));
        }
        if (!empty($result['refused'])) {
            \core\notification::warning(
                get_string('storefront_checkout_refused', $component, count($result['refused'])));
        }
        if (!empty($result['added'])) {
            // The buyer is on the order cart's checkout page now, not the basket: tell them
            // where the free lines they left behind are.
            $freeleft = self::free_lines_left();
            if ($freeleft > 0) {
                \core\notification::info(get_string('storefront_checkout_freeleft', $component, $freeleft));
            }
        } else {
            // Nothing reached the order cart, so the buyer stays on the basket: say so.
            \core\notification::error(get_string('storefront_checkout_nothing', $component));
        }
    }
}
