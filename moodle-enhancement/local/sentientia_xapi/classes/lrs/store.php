<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\lrs;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\model\statement;

/**
 * LRS statement store.
 *
 * Writes and reads xAPI statements from {local_sentientia_xapi_stmts}.
 * All writes are tenant-scoped. Statements are immutable once stored
 * per xAPI spec §2.5 — use void() to logically delete.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store {

    /** Statement source label for Moodle event observer emission. */
    public const SOURCE_MOODLE = 'moodle';

    /** Statement source label for external LRS endpoint POST. */
    public const SOURCE_LRS = 'lrs';

    /** Statement source label for bulk import. */
    public const SOURCE_IMPORT = 'import';

    /**
     * Store a single statement into the LRS.
     *
     * Assigns a UUID to the statement if none is provided.
     * Silently no-ops if the same (statementid, costcenterid) pair already
     * exists (xAPI spec: idempotent on same-id duplicate).
     *
     * @param statement $stmt          The validated statement.
     * @param int       $costcenterid  Tenant root ID (0 = platform-level).
     * @param int|null  $actorid       Resolved Moodle userid (null = external actor).
     * @param string    $source        SOURCE_* constant.
     * @return string  The stored statement UUID.
     */
    public function put(
        statement $stmt,
        int $costcenterid = 0,
        ?int $actorid = null,
        string $source = self::SOURCE_MOODLE
    ): string {
        global $DB;

        // Assign a UUID if the statement doesn't have one.
        if ($stmt->get_id() === null) {
            $stmt->set_id(statement::generate_uuid());
        }
        $uuid = $stmt->get_id();

        // Idempotency check — same (id, tenant) already stored.
        if ($DB->record_exists('local_sentientia_xapi_stmts', [
            'statementid'  => $uuid,
            'costcenterid' => $costcenterid,
        ])) {
            return $uuid;
        }

        $data = $stmt->to_array();
        $now  = time();

        // Parse the timestamp from the statement (or use now).
        $ts = null;
        if (!empty($data['timestamp'])) {
            $dt = \DateTime::createFromFormat(\DateTime::ATOM, $data['timestamp']);
            if ($dt !== false) {
                $ts = $dt->getTimestamp();
            }
        }

        // Denormalised result fields.
        $score_scaled = null;
        $score_raw    = null;
        $success      = null;
        $completion   = null;
        if (!empty($data['result'])) {
            $result = $data['result'];
            if (!empty($result['score']['scaled'])) {
                $score_scaled = (float) $result['score']['scaled'];
            }
            if (!empty($result['score']['raw'])) {
                $score_raw = (float) $result['score']['raw'];
            }
            if (isset($result['success'])) {
                $success = $result['success'] ? 1 : 0;
            }
            if (isset($result['completion'])) {
                $completion = $result['completion'] ? 1 : 0;
            }
        }

        // Denormalised verb display (en-US preferred).
        $verb_display = null;
        if (!empty($data['verb']['display'])) {
            $display = $data['verb']['display'];
            $verb_display = $display['en-US'] ?? $display['en'] ?? reset($display);
            if ($verb_display !== null) {
                $verb_display = substr((string) $verb_display, 0, 128);
            }
        }

        // Object id (truncated to 2048 chars).
        $object_id = null;
        if (!empty($data['object']['id'])) {
            $object_id = substr((string) $data['object']['id'], 0, 2048);
        }

        $record = new \stdClass();
        $record->statementid  = $uuid;
        $record->costcenterid = $costcenterid;
        $record->actorid      = $actorid;
        $record->actor        = json_encode($data['actor'] ?? [], JSON_UNESCAPED_SLASHES);
        $record->verb         = (string) ($data['verb']['id'] ?? '');
        $record->verbdisplay  = $verb_display;
        $record->object       = json_encode($data['object'] ?? [], JSON_UNESCAPED_SLASHES);
        $record->objectid     = $object_id;
        $record->result       = isset($data['result']) ? json_encode($data['result'], JSON_UNESCAPED_SLASHES) : null;
        $record->score_scaled = $score_scaled;
        $record->score_raw    = $score_raw;
        $record->success      = $success;
        $record->completion   = $completion;
        $record->context      = isset($data['context']) ? json_encode($data['context'], JSON_UNESCAPED_SLASHES) : null;
        $record->registration = !empty($data['context']['registration'])
            ? (string) $data['context']['registration'] : null;
        $record->authority    = isset($data['authority']) ? json_encode($data['authority'], JSON_UNESCAPED_SLASHES) : null;
        $record->timestamp    = $ts;
        $record->stored       = $now;
        $record->source       = substr($source, 0, 32);
        $record->voided       = 0;
        $record->timecreated  = $now;
        $record->timemodified = $now;

        $DB->insert_record('local_sentientia_xapi_stmts', $record);

        return $uuid;
    }

    /**
     * Get a single statement by its UUID and tenant.
     *
     * @param string $uuid         Statement UUID.
     * @param int    $costcenterid Tenant root.
     * @return \stdClass|false     DB row or false.
     */
    public function get(string $uuid, int $costcenterid): \stdClass|false {
        global $DB;
        return $DB->get_record('local_sentientia_xapi_stmts', [
            'statementid'  => $uuid,
            'costcenterid' => $costcenterid,
            'voided'       => 0,
        ]);
    }

    /**
     * Retrieve a page of statements filtered by tenant and optional criteria.
     *
     * @param int    $costcenterid  Tenant root (0 = all tenants, site admin only).
     * @param array  $filters       Optional: ['verb' => IRI, 'actorid' => int, 'registration' => UUID].
     * @param int    $limit         Max rows.
     * @param int    $offset        Row offset (for pagination).
     * @return \stdClass[]
     */
    public function get_statements(
        int $costcenterid,
        array $filters = [],
        int $limit = 50,
        int $offset = 0
    ): array {
        global $DB;

        $where  = ['s.voided = 0'];
        $params = [];

        if ($costcenterid > 0) {
            $where[]             = 's.costcenterid = :cid';
            $params['cid']       = $costcenterid;
        }

        if (!empty($filters['verb'])) {
            $where[]             = 's.verb = :verb';
            $params['verb']      = $filters['verb'];
        }

        if (!empty($filters['actorid'])) {
            $where[]             = 's.actorid = :aid';
            $params['aid']       = (int) $filters['actorid'];
        }

        if (!empty($filters['registration'])) {
            $where[]             = 's.registration = :reg';
            $params['reg']       = $filters['registration'];
        }

        $sql = "SELECT s.*
                  FROM {local_sentientia_xapi_stmts} s
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY s.stored DESC";

        return array_values($DB->get_records_sql($sql, $params, $offset, $limit));
    }

    /**
     * Void a statement (xAPI spec §4.2.2).
     * Does not delete — marks the row as voided = 1.
     *
     * @param string $uuid         Statement UUID to void.
     * @param int    $costcenterid Tenant root.
     * @return bool True if a row was found and voided.
     */
    public function void_statement(string $uuid, int $costcenterid): bool {
        global $DB;
        $row = $DB->get_record('local_sentientia_xapi_stmts', [
            'statementid'  => $uuid,
            'costcenterid' => $costcenterid,
            'voided'       => 0,
        ]);
        if (!$row) {
            return false;
        }
        $row->voided       = 1;
        $row->timemodified = time();
        $DB->update_record('local_sentientia_xapi_stmts', $row);
        return true;
    }

    /**
     * Delete statements older than the configured retention period.
     * Called by the nightly scheduled task.
     *
     * @return int  Number of rows deleted.
     */
    public function purge_old_statements(): int {
        global $DB;

        $retention_days = (int) get_config('local_sentientia_xapi', 'retention_days');
        if ($retention_days <= 0) {
            return 0;  // Keep forever.
        }

        $cutoff = time() - ($retention_days * DAYSECS);
        return $DB->count_records_select('local_sentientia_xapi_stmts', 'stored < :cutoff',
            ['cutoff' => $cutoff])
            + ($DB->delete_records_select('local_sentientia_xapi_stmts', 'stored < :cutoff',
                ['cutoff' => $cutoff]) ? 0 : 0);
    }

    /**
     * Resolve an actor to a Moodle user id.
     *
     * Tries in order:
     *   1. account IFI — name matches user.id (Sentientia-native pattern)
     *   2. mbox IFI — strips mailto: and looks up by email
     *   3. openid IFI — looks up by idnumber
     *
     * Returns null when no match is found (external actor).
     *
     * @param array $actor xAPI actor array.
     * @return int|null
     */
    public function resolve_actor_userid(array $actor): ?int {
        global $DB;

        // Account IFI — Sentientia uses user.id as account.name.
        if (!empty($actor['account']['name']) && ctype_digit((string) $actor['account']['name'])) {
            $uid = (int) $actor['account']['name'];
            if ($DB->record_exists('user', ['id' => $uid, 'deleted' => 0])) {
                return $uid;
            }
        }

        // mbox IFI.
        if (!empty($actor['mbox']) && strpos($actor['mbox'], 'mailto:') === 0) {
            $email = substr($actor['mbox'], 7);
            $user  = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id');
            if ($user) {
                return (int) $user->id;
            }
        }

        // openid IFI.
        if (!empty($actor['openid'])) {
            $user = $DB->get_record('user', ['idnumber' => (string) $actor['openid'], 'deleted' => 0], 'id');
            if ($user) {
                return (int) $user->id;
            }
        }

        return null;
    }
}
