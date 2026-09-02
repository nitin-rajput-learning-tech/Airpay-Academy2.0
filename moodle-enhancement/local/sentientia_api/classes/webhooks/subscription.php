<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\webhooks;

defined('MOODLE_INTERNAL') || die();

/**
 * Outbound webhook subscription registry (ADR-030 Wave A).
 *
 * A subscription = (customer, tenant root or 0 for all, https URL, per-subscription
 * HMAC secret, set of event keys). URLs are validated https-only and run
 * through Moodle's curl security helper (blocked hosts / ports) at save time
 * so a subscription can never be pointed at internal infrastructure.
 *
 * @package local_sentientia_api
 */
class subscription {

    /** @var string */
    public const TABLE = 'local_sentientia_api_whsub';

    /** @var string[] Fixed v1 event vocabulary. */
    public const EVENTS = ['course.completed', 'enrolment.created', 'certificate.issued'];

    /**
     * Create a subscription. Generates the secret; returns the new id.
     *
     * Expected $data: name, url, events (array|csv), costcenterid (0 = all),
     * customerid (0 = default), enabled (default 1).
     *
     * @param \stdClass $data
     * @return int
     */
    public static function create(\stdClass $data): int {
        global $DB;
        self::validate_url((string) ($data->url ?? ''));
        $now = time();
        $rec = (object) [
            'customerid'   => (int) ($data->customerid ?? 0),
            'costcenterid' => (int) ($data->costcenterid ?? 0),
            'name'         => \core_text::substr(trim((string) ($data->name ?? '')), 0, 255),
            'url'          => trim((string) $data->url),
            'secret'       => self::generate_secret(),
            'events'       => self::normalise_events($data->events ?? []),
            'enabled'      => empty($data->enabled) && isset($data->enabled) ? 0 : 1,
            'lastsuccess'  => 0,
            'lastfailure'  => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        if ($rec->name === '') {
            throw new \moodle_exception('webhook_name_required', 'local_sentientia_api');
        }
        if ($rec->events === '') {
            throw new \moodle_exception('webhook_events_required', 'local_sentientia_api');
        }
        return (int) $DB->insert_record(self::TABLE, $rec);
    }

    /**
     * Update name/url/events/costcenterid/enabled. Secret is never changed here
     * (use rotate_secret()).
     *
     * @param int       $id
     * @param \stdClass $data
     * @return void
     */
    public static function update(int $id, \stdClass $data): void {
        global $DB;
        $rec = self::get($id);
        if (isset($data->url)) {
            self::validate_url((string) $data->url);
            $rec->url = trim((string) $data->url);
        }
        if (isset($data->name)) {
            $rec->name = \core_text::substr(trim((string) $data->name), 0, 255);
        }
        if (isset($data->events)) {
            $rec->events = self::normalise_events($data->events);
        }
        if (isset($data->costcenterid)) {
            $rec->costcenterid = (int) $data->costcenterid;
        }
        if (isset($data->enabled)) {
            $rec->enabled = $data->enabled ? 1 : 0;
        }
        $rec->timemodified = time();
        $DB->update_record(self::TABLE, $rec);
    }

    /**
     * Replace the secret; returns the new value (shown to the admin once).
     *
     * @param int $id
     * @return string
     */
    public static function rotate_secret(int $id): string {
        global $DB;
        $rec = self::get($id);
        $rec->secret = self::generate_secret();
        $rec->timemodified = time();
        $DB->update_record(self::TABLE, $rec);
        return $rec->secret;
    }

    /**
     * @param int  $id
     * @param bool $enabled
     * @return void
     */
    public static function set_enabled(int $id, bool $enabled): void {
        global $DB;
        self::get($id);
        $DB->set_field(self::TABLE, 'enabled', $enabled ? 1 : 0, ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Delete a subscription and its delivery history.
     *
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(queue::TABLE, ['subid' => $id]);
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
     * @return \stdClass[] All subscriptions, newest first.
     */
    public static function list_all(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'timecreated DESC');
    }

    /**
     * Enabled subscriptions that want $eventkey for tenant $costcenterid
     * (a subscription with costcenterid 0 listens to every tenant).
     *
     * @param string $eventkey
     * @param int    $costcenterid
     * @return \stdClass[]
     */
    public static function matching(string $eventkey, int $costcenterid): array {
        global $DB;
        $candidates = $DB->get_records_select(self::TABLE,
            'enabled = 1 AND (costcenterid = :cid OR costcenterid = 0)',
            ['cid' => $costcenterid], 'id ASC');
        $out = [];
        foreach ($candidates as $sub) {
            $events = array_filter(array_map('trim', explode(',', (string) $sub->events)));
            if (in_array($eventkey, $events, true)) {
                $out[$sub->id] = $sub;
            }
        }
        return $out;
    }

    /**
     * 64 hex chars (256 bits).
     *
     * @return string
     */
    public static function generate_secret(): string {
        return bin2hex(random_bytes(32));
    }

    /**
     * https-only + not blocked by Moodle's curl security helper (private
     * ranges, localhost, blocked ports). Throws on rejection.
     *
     * @param string $url
     * @return void
     */
    public static function validate_url(string $url): void {
        $url = trim($url);
        $parts = parse_url($url);
        if ($url === '' || $parts === false || empty($parts['scheme']) || empty($parts['host'])
            || strtolower($parts['scheme']) !== 'https') {
            throw new \moodle_exception('webhook_url_invalid', 'local_sentientia_api');
        }
        $helper = new \core\files\curl_security_helper();
        if ($helper->url_is_blocked($url)) {
            throw new \moodle_exception('webhook_url_blocked', 'local_sentientia_api');
        }
    }

    /**
     * Accept array or csv; keep only known event keys; return canonical csv.
     *
     * @param array|string $events
     * @return string
     */
    public static function normalise_events($events): string {
        if (is_string($events)) {
            $events = explode(',', $events);
        }
        $clean = [];
        foreach ((array) $events as $e) {
            $e = trim((string) $e);
            if (in_array($e, self::EVENTS, true)) {
                $clean[$e] = $e;
            }
        }
        return implode(',', array_values($clean));
    }
}
