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
        return $out;
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
