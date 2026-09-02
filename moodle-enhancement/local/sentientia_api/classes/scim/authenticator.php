<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * Bearer-token authentication for the SCIM endpoint (ADR-030 Wave B).
 *
 * Tokens are stored as sha256 hashes in local_sentientia_api_scimcli; the
 * lookup is by hash (unique index) so no plaintext comparison ever happens.
 * Same shape as the xAPI LRS authenticator.
 *
 * @package local_sentientia_api
 */
class authenticator {

    /**
     * Resolve the Authorization header from the server environment
     * (Apache/FPM expose it under different keys).
     *
     * @param array $server Typically $_SERVER
     * @return string
     */
    public static function header_from_server(array $server): string {
        if (!empty($server['HTTP_AUTHORIZATION'])) {
            return (string) $server['HTTP_AUTHORIZATION'];
        }
        if (!empty($server['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $server['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $k => $v) {
                if (strtolower((string) $k) === 'authorization') {
                    return (string) $v;
                }
            }
        }
        return '';
    }

    /**
     * Authenticate a bearer token. Returns the enabled client row or null.
     *
     * @param string $authheader Raw Authorization header value
     * @return \stdClass|null
     */
    public static function authenticate(string $authheader): ?\stdClass {
        global $DB;
        if (stripos($authheader, 'Bearer ') !== 0) {
            return null;
        }
        $token = trim(substr($authheader, 7));
        if ($token === '' || strlen($token) > 256) {
            return null;
        }
        $client = $DB->get_record(client::TABLE, ['tokenhash' => hash('sha256', $token), 'enabled' => 1]);
        if (!$client) {
            return null;
        }
        // Throttled lastseen write (one per minute per client).
        $now = time();
        if ($now - (int) $client->lastseen > 60) {
            $DB->set_field(client::TABLE, 'lastseen', $now, ['id' => $client->id]);
            $client->lastseen = $now;
        }
        return $client;
    }
}
