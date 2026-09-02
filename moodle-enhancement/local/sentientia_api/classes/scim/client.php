<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * SCIM client registry (ADR-030 Wave B): admin CRUD, token issue/rotate,
 * and a self-contained per-client fixed-window rate limit that lives on the
 * client row (so the shared per-user rate table is untouched).
 *
 * @package local_sentientia_api
 */
class client {

    /** @var string */
    public const TABLE = 'local_sentientia_api_scimcli';

    /** @var string[] Auth plugins a client may assign to created users. */
    public const ALLOWED_AUTH = ['oauth2', 'oidc', 'saml2', 'ldap', 'manual', 'nologin'];

    /**
     * Create a client. Returns the id and the ONE-TIME plaintext token.
     *
     * @param \stdClass $data name, costcenterid, customerid, auth, ratelimit, enabled
     * @return array{id:int,token:string}
     */
    public static function create(\stdClass $data): array {
        global $DB;
        $name = \core_text::substr(trim((string) ($data->name ?? '')), 0, 255);
        if ($name === '') {
            throw new \moodle_exception('scim_client_name_required', 'local_sentientia_api');
        }
        $auth = (string) ($data->auth ?? 'oauth2');
        if (!in_array($auth, self::ALLOWED_AUTH, true)) {
            throw new \moodle_exception('scim_client_auth_invalid', 'local_sentientia_api');
        }
        $token = self::generate_token();
        $now = time();
        $id = (int) $DB->insert_record(self::TABLE, (object) [
            'customerid'   => (int) ($data->customerid ?? 0),
            'costcenterid' => (int) ($data->costcenterid ?? 0),
            'name'         => $name,
            'tokenhash'    => hash('sha256', $token),
            'auth'         => $auth,
            'enabled'      => (isset($data->enabled) && empty($data->enabled)) ? 0 : 1,
            'ratelimit'    => max(0, (int) ($data->ratelimit ?? 0)),
            'ratehits'     => 0,
            'ratewindow'   => 0,
            'lastseen'     => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        return ['id' => $id, 'token' => $token];
    }

    /**
     * Issue a new token (old one stops working immediately). Returns plaintext once.
     *
     * @param int $id
     * @return string
     */
    public static function rotate_token(int $id): string {
        global $DB;
        self::get($id);
        $token = self::generate_token();
        $DB->update_record(self::TABLE, (object) [
            'id' => $id, 'tokenhash' => hash('sha256', $token), 'timemodified' => time(),
        ]);
        return $token;
    }

    /**
     * @param int  $id
     * @param bool $enabled
     * @return void
     */
    public static function set_enabled(int $id, bool $enabled): void {
        global $DB;
        self::get($id);
        $DB->update_record(self::TABLE, (object) ['id' => $id, 'enabled' => $enabled ? 1 : 0, 'timemodified' => time()]);
    }

    /**
     * Delete a client and its externalId mappings (users are untouched).
     *
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(mapper::TABLE, ['cliid' => $id]);
            $DB->delete_records(self::TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * @param int $id
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * @return \stdClass[]
     */
    public static function list_all(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timecreated DESC');
    }

    /**
     * Fixed-window rate limit on the client row: one atomic UPDATE resets the
     * counter when the window rolls, otherwise increments it.
     *
     * @param \stdClass $client
     * @return void
     * @throws scim_exception 429 when over budget
     */
    public static function rate_check(\stdClass $client): void {
        global $DB;
        $window = \local_sentientia_api\rate_limiter::window();
        $budget = (int) $client->ratelimit > 0 ? (int) $client->ratelimit : \local_sentientia_api\rate_limiter::budget();
        $now = time();
        $ws = $now - ($now % $window);
        $DB->execute(
            "UPDATE {" . self::TABLE . "}
                SET ratehits = CASE WHEN ratewindow = :ws1 THEN ratehits + 1 ELSE 1 END,
                    ratewindow = :ws2
              WHERE id = :id",
            ['ws1' => $ws, 'ws2' => $ws, 'id' => $client->id]
        );
        $hits = (int) $DB->get_field(self::TABLE, 'ratehits', ['id' => $client->id]);
        if ($hits > $budget) {
            throw new scim_exception(429, get_string('ratelimited', 'local_sentientia_api', $budget), 'tooMany');
        }
    }

    /**
     * 64 hex chars (256 bits).
     *
     * @return string
     */
    public static function generate_token(): string {
        return bin2hex(random_bytes(32));
    }
}
