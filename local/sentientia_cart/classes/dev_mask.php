<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The dev-copy masking of the cart's money tables that the BizLMS import filled with personal data.
 *
 * local_sentientia_platform/cli/mask_pii_for_dev.php masks a production snapshot before it becomes a development
 * database. It knew only the billing phone and address of the order header. The ADR-032 import (and refunds) put
 * more personal data into the ledger and the credit journal, which a dev copy built from an imported database
 * would carry unmasked (owner follow-up, 2026-10-07, finance cluster). This masks it:
 *
 *  - local_sentientia_cart_ledger.reason: BizLMS's free-text annotation, and a refund's reason, set to NULL;
 *  - local_sentientia_cart_credit_txn.reason: the reason given for a credit booking, set to NULL;
 *  - the initiatedby columns of both tables: the user who made the booking, set to 0 (the "no one" value
 *    install.xml documents: a gateway webhook);
 *  - local_sentientia_cart_ledger.payload_json: every "userid" and "usermodified" key, at any depth, set to 0. The key
 *    and its place stay (an imported row's payload starts {"userid":N, and the privacy provider finds a row by that
 *    prefix), only the identity goes. A payload that is not valid JSON is replaced by {}: it cannot be masked key by
 *    key, and a dev copy has no use for it.
 *
 *  - the buyer's details on the order header (local_sentientia_cart_history) and on every native invoice
 *    (local_sentientia_cart_invoices): billing_name becomes "Dev Buyer", billing_email, billing_phone and billing_address
 *    become NULL, and so does the header's free-text "notes" (staff notes name the learner and what was agreed with
 *    them). Review fix round 1, 2026-10-07: the CLI used to clear only the header's phone and address, and its own
 *    comment said the name and e-mail were "already masked via mdl_user", which they are not: they are copies the buyer
 *    typed at checkout. billing_gstn (a company's tax number, printed on the invoice) is kept: a developer needs a
 *    well-formed one and it is not a person's detail.
 *
 * Amounts, event types, currencies, order numbers and timestamps are left alone: they are what a developer needs and
 * identify no one. It is idempotent, changes only rows that still carry something, and lives in the cart (not in the
 * platform CLI) so the cart's PHPUnit suite can hold it. The CLI calls run() when this class exists.
 *
 * Run it only against a SCRATCH copy (the CLI refuses a production database name): it rewrites money history.
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dev_mask {

    /** The ledger. */
    public const LEDGER = 'local_sentientia_cart_ledger';

    /** The imported credit journal. */
    public const CREDIT_TXN = 'local_sentientia_cart_credit_txn';

    /** The order header: the buyer's billing details and the staff notes. */
    public const HISTORY = 'local_sentientia_cart_history';

    /** Native invoices. */
    public const INVOICES = 'local_sentientia_cart_invoices';

    /** What every billing name becomes. */
    public const MASKED_NAME = 'Dev Buyer';

    /**
     * The personal columns set to NULL, by table: column => true when it is a TEXT column (the empty-value test differs).
     * billing_name is masked separately (the invoice's is NOT NULL); billing_gstn stays.
     *
     * @var array<string, array<string, bool>>
     */
    public const PERSONAL_COLUMNS = [
        self::HISTORY => ['billing_email' => false, 'billing_phone' => false, 'billing_address' => true, 'notes' => true],
        self::INVOICES => ['billing_email' => false, 'billing_phone' => false, 'billing_address' => true],
    ];

    /** Keys inside payload_json that name a person (lower case). */
    public const PAYLOAD_PERSON_KEYS = ['userid', 'usermodified'];

    /** Payload rows read and rewritten per round. */
    private const BATCH = 500;

    /**
     * Mask everything above.
     *
     * @return array<string, int> what was masked, by "table.column", for the CLI to print; a table that does not
     *         exist is not listed
     */
    public static function run(): array {
        global $DB;
        $dbman = $DB->get_manager();
        $out = [];
        foreach ([self::LEDGER, self::CREDIT_TXN] as $table) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            $out[$table . '.reason'] = self::clear_reasons($table);
            $out[$table . '.initiatedby'] = self::zero_actors($table);
        }
        if ($dbman->table_exists(self::LEDGER)) {
            $out[self::LEDGER . '.payload_json'] = self::mask_payloads();
        }
        foreach (self::PERSONAL_COLUMNS as $table => $columns) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            $out[$table . '.billing_name'] = self::mask_names($table);
            foreach ($columns as $column => $istext) {
                $out[$table . '.' . $column] = self::clear_column($table, $column, $istext);
            }
        }
        return $out;
    }

    /**
     * Replace every billing name that is not already the placeholder.
     *
     * @param string $table
     * @return int rows changed
     */
    private static function mask_names(string $table): int {
        global $DB;
        $select = 'billing_name <> :masked';
        $params = ['masked' => self::MASKED_NAME];
        $count = $DB->count_records_select($table, $select, $params);
        if ($count) {
            $DB->set_field_select($table, 'billing_name', self::MASKED_NAME, $select, $params);
        }
        return $count;
    }

    /**
     * Set a personal column to NULL on every row that still holds a value (an empty string is left as it is).
     *
     * @param string $table
     * @param string $column
     * @param bool $istext True for a TEXT column.
     * @return int rows changed
     */
    private static function clear_column(string $table, string $column, bool $istext): int {
        global $DB;
        $select = $DB->sql_isnotempty($table, $column, true, $istext);
        $count = $DB->count_records_select($table, $select);
        if ($count) {
            $DB->set_field_select($table, $column, null, $select);
        }
        return $count;
    }

    /**
     * Set the free-text reason of every row that has one to NULL.
     *
     * @param string $table
     * @return int rows changed
     */
    private static function clear_reasons(string $table): int {
        global $DB;
        $select = 'reason IS NOT NULL';
        $count = $DB->count_records_select($table, $select);
        if ($count) {
            $DB->set_field_select($table, 'reason', null, $select);
        }
        return $count;
    }

    /**
     * Set initiatedby to 0 on every row that names a user.
     *
     * @param string $table
     * @return int rows changed
     */
    private static function zero_actors(string $table): int {
        global $DB;
        $select = 'initiatedby <> 0';
        $count = $DB->count_records_select($table, $select);
        if ($count) {
            $DB->set_field_select($table, 'initiatedby', 0, $select);
        }
        return $count;
    }

    /**
     * Zero the person keys of every ledger payload, in batches by id.
     *
     * @return int rows changed
     */
    private static function mask_payloads(): int {
        global $DB;
        $changed = 0;
        $last = 0;
        $notempty = $DB->sql_isnotempty(self::LEDGER, 'payload_json', true, true);
        do {
            $rows = $DB->get_records_select(self::LEDGER, "id > :last AND payload_json IS NOT NULL AND {$notempty}",
                ['last' => $last], 'id ASC', 'id, payload_json', 0, self::BATCH);
            foreach ($rows as $row) {
                $last = (int) $row->id;
                $masked = self::mask_payload((string) $row->payload_json);
                if ($masked !== null) {
                    $DB->set_field(self::LEDGER, 'payload_json', $masked, ['id' => $row->id]);
                    $changed++;
                }
            }
        } while (count($rows) === self::BATCH);
        return $changed;
    }

    /**
     * One payload, masked.
     *
     * @param string $json
     * @return string|null the new JSON, or null when the payload names no one and needs no change
     */
    public static function mask_payload(string $json): ?string {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            // Not an object or list: nothing to mask key by key, and nothing a dev copy can use.
            return $json === '{}' ? null : '{}';
        }
        $changed = false;
        $data = self::mask_node($data, $changed);
        if (!$changed) {
            return null;
        }
        $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Zero every person key in an array, at any depth.
     *
     * @param array $node
     * @param bool $changed set to true when something was zeroed
     * @return array
     */
    private static function mask_node(array $node, bool &$changed): array {
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::PAYLOAD_PERSON_KEYS, true)) {
                if ($value !== 0 && $value !== null) {
                    $node[$key] = 0;
                    $changed = true;
                }
            } else if (is_array($value)) {
                $node[$key] = self::mask_node($value, $changed);
            }
        }
        return $node;
    }
}
