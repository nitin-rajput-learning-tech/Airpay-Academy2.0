<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\tenant_resolver;

/**
 * Small pure rules the cart importer's steps share (ADR-032, mapping doc section 13).
 *
 * Nothing here writes. The two functions that read the database (order_floor(), and the config readers)
 * only read.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class support {

    /** legacy_source of an imported order. */
    public const SOURCE_LABEL = 'bizlms';

    /** Plugin config key that holds the highest order number an imported order uses (set by finalise()). */
    public const FLOOR_CONFIG = 'bizlms_order_floor';

    /** The BizLMS plugin whose config the importer reads (config_plugins rows stay when the code goes). */
    public const BIZLMS_COMPONENT = 'local_biz_cart';

    /** Largest value an INT(10) column is sure to hold; a larger timestamp or number is garbage. */
    public const MAX_INT = 2147483647;

    /**
     * Gateway label of a BizLMS payment method (BizLMS lib.php, LOCAL_BIZCART_PAYMENT_METHOD_*).
     *
     * Method 0 (online) is not here: its label is the gateway the evidence names (see evidence::online_gateway()).
     *
     * @var array<int, string>
     */
    private const GATEWAY_LABELS = [
        1 => 'cashier',
        2 => 'credits',
        3 => 'cashier_cash',
        4 => 'cashier_debitcard',
        5 => 'cashier_creditcard',
        6 => 'credits_payback_cash',
        7 => 'cashier_manual',
        8 => 'credits_payback_transfer',
        9 => 'credits_correction',
    ];

    /**
     * An ISO-style currency code, from what BizLMS stored.
     *
     * The column is char(255) and nullable, and get_config() returns false when the setting is unset (stored as
     * an empty string), so an empty value means the site currency: INR.
     *
     * @param mixed $raw
     * @return string|null Three capital letters, or null when the value cannot be one.
     */
    public static function currency(mixed $raw): ?string {
        $value = strtoupper(trim((string) $raw));
        if ($value === '') {
            return 'INR';
        }
        return preg_match('/^[A-Z]{3}$/', $value) ? $value : null;
    }

    /**
     * An amount as integer minor units (paise). Money is summed in integers so two-decimal values never drift.
     *
     * @param mixed $value
     * @return int
     */
    public static function paise(mixed $value): int {
        return (int) round(((float) $value) * 100);
    }

    /**
     * An amount as integer thousandths: BizLMS keeps tax to three decimals.
     *
     * @param mixed $value
     * @return int
     */
    public static function milli(mixed $value): int {
        return (int) round(((float) $value) * 1000);
    }

    /**
     * An epoch second an INT(10) column can hold; anything else is unknown (0).
     *
     * @param mixed $value
     * @return int
     */
    public static function epoch(mixed $value): int {
        $value = (int) $value;
        return ($value > 0 && $value <= self::MAX_INT) ? $value : 0;
    }

    /**
     * Valid UTF-8 text, or null when there is none. BizLMS columns are char(255) with whatever the site collation
     * let in, and the writer refuses invalid UTF-8.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function clean_text(mixed $value): ?string {
        $text = (string) $value;
        if ($text === '') {
            return null;
        }
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * The gateway label of a payment method code, for the methods that have a fixed one.
     *
     * @param string $method The BizLMS payment column, trimmed.
     * @return string|null Null for 0 (online: the gateway comes from the evidence) and for anything unknown.
     */
    public static function gateway_label(string $method): ?string {
        if ($method === '' || !ctype_digit($method)) {
            return null;
        }
        return self::GATEWAY_LABELS[(int) $method] ?? null;
    }

    /**
     * The kind of ledger event a BizLMS ledger row is (mapping doc, local_biz_cart_ledger map).
     *
     * @param \stdClass $row A local_biz_cart_ledger row.
     * @return string An event_type of local_sentientia_cart_ledger.
     */
    public static function ledger_event_type(\stdClass $row): string {
        $status = (int) ($row->paymentstatus ?? 0);
        $itemid = (int) ($row->itemid ?? 0);
        $userid = (int) ($row->userid ?? 0);
        $credits = (float) ($row->credits ?? 0);
        $method = trim((string) ($row->payment ?? ''));
        $area = (string) ($row->area ?? '');

        if ($status === 2) {
            if ($itemid > 0) {
                return 'payment_received';
            }
            if ($method === '6' || $method === '8') {
                return 'legacy_credit_payout';
            }
            if ($method === '9') {
                return 'legacy_credit_correction';
            }
            if ($userid > 0 && $credits < 0) {
                return 'legacy_credit_redeemed';
            }
            if ($userid === 0 && $area === 'cash') {
                return 'legacy_cash_drawer';
            }
            return 'legacy_other';
        }
        if ($status === 3) {
            return 'legacy_cancel_to_credit';
        }
        return 'legacy_other';
    }

    /**
     * The tenant root of a user, from the CURRENT open_path (BizLMS stores no tenant on a cart row).
     *
     * The root is an INT (history.costcenterid), not a path in the organisation tree, so this needs no org
     * importer: it normalises the stored open_path and asks the tenant registry whether the root is one.
     *
     * @param int $userid
     * @param context $ctx
     * @return array{0: int, 1: string} [root (0 when unresolved), tenant method]
     */
    public static function root_of_user(int $userid, context $ctx): array {
        $raw = $ctx->lookups->user_path($userid);
        $path = ($raw === null || trim($raw) === '') ? null : tenant_resolver::normalise($raw);
        if ($path === null) {
            return [0, 'unresolved'];
        }
        $root = (int) explode('/', ltrim($path, '/'))[0];
        if ($root <= 0 || !evidence::of($ctx->legacy)->root_registered($root)) {
            return [0, 'unresolved'];
        }
        return [$root, $raw === $path ? 'exact' : 'normalised'];
    }

    /**
     * The BizLMS base of the order numbers: identifier = base + local_biz_cart_id.id.
     *
     * @return int 0 when the setting is unset or not a number.
     */
    public static function unique_identifier_base(): int {
        $value = get_config(self::BIZLMS_COMPONENT, 'uniqueidentifier');
        return ($value !== false && ctype_digit((string) $value)) ? (int) $value : 0;
    }

    /**
     * Does BizLMS store the line price NET of tax (itempriceisnet)? Unset means gross, as in BizLMS.
     *
     * @return bool
     */
    public static function price_is_net(): bool {
        return (int) get_config(self::BIZLMS_COMPONENT, 'itempriceisnet') === 1;
    }

    /**
     * The highest order number the import has used or reserved: an imported order, or any identifier a BizLMS
     * ledger row or invoice names, or the highest number BizLMS could have issued (base plus the largest
     * local_biz_cart_id.id). finalise() records it, and cart_manager::checkout() keeps native order numbers
     * above it.
     *
     * @return int 0 when there is nothing.
     */
    public static function order_floor(): int {
        global $DB;
        $dbman = $DB->get_manager();
        $floor = 0;

        foreach (['local_biz_cart_history', 'local_biz_cart_ledger', 'local_biz_cart_invoices'] as $table) {
            if ($dbman->table_exists($table)) {
                $floor = max($floor, (int) $DB->get_field_sql('SELECT MAX(identifier) FROM {' . $table . '}'));
            }
        }
        if ($dbman->table_exists('local_biz_cart_id')) {
            $largest = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_biz_cart_id}');
            if ($largest > 0) {
                $floor = max($floor, self::unique_identifier_base() + $largest);
            }
        }
        $floor = max($floor, (int) $DB->get_field_sql(
            'SELECT MAX(orderid) FROM {local_sentientia_cart_history} WHERE legacy_source = :src',
            ['src' => self::SOURCE_LABEL]));
        return $floor;
    }
}
