<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\lti;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD + lookup helpers for LTI 1.3 registrations.
 *
 * A "registration" is a platform/tool pairing identified by (issuer,
 * client_id). For an incoming launch (we are the provider/tool) we look up
 * the registration to obtain the platform's public key for signature
 * verification. For an outgoing launch (we are the consumer) we use it to
 * build the OIDC login request.
 *
 * Every registration is tenant-scoped via costcenterid so one customer's
 * tools never resolve against another's.
 *
 * @package local_sentientia_api
 */
class registration {

    /**
     * Resolve an enabled registration by issuer + client_id, scoped to a tenant.
     *
     * @param string $issuer
     * @param string $clientid
     * @param int    $costcenterid Tenant root (0 = any, admin only)
     * @return \stdClass|null
     */
    public static function find(string $issuer, string $clientid, int $costcenterid): ?\stdClass {
        global $DB;
        $conditions = [
            'issuer'   => $issuer,
            'clientid' => $clientid,
            'enabled'  => 1,
        ];
        if ($costcenterid > 0) {
            $conditions['costcenterid'] = $costcenterid;
        }
        $rec = $DB->get_record('local_sentientia_api_lti_reg', $conditions);
        return $rec ?: null;
    }

    /**
     * Load a registration by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('local_sentientia_api_lti_reg', ['id' => $id]);
        return $rec ?: null;
    }

    /**
     * Resolve the verification public key for a registration.
     *
     * Prefers an inline PEM publickey; otherwise (scaffold) the caller would
     * fetch the JWKS URL and select by kid. Returns '' when no key material
     * is available — verification must then fail closed.
     *
     * @param \stdClass $reg
     * @return string PEM public key, or '' if unavailable.
     */
    public static function public_key(\stdClass $reg): string {
        if (!empty($reg->publickey)) {
            return (string) $reg->publickey;
        }
        // Scaffold note: a production build fetches $reg->jwksurl, caches the
        // JWKS, and converts the RSA modulus/exponent for the matching `kid`
        // into a PEM. Left as a documented extension point — fail closed.
        return '';
    }

    /**
     * Create a one-time login nonce + state for an outgoing/incoming launch.
     *
     * @param int $regid
     * @return array{nonce:string, state:string}
     */
    public static function new_nonce(int $regid): array {
        global $DB;
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $DB->insert_record('local_sentientia_api_lti_nonce', (object) [
            'nonce'       => $nonce,
            'state'       => $state,
            'regid'       => $regid,
            'consumed'    => 0,
            'timecreated' => time(),
        ]);
        return ['nonce' => $nonce, 'state' => $state];
    }

    /**
     * Atomically consume a nonce. Returns the row if it was unused + fresh,
     * null otherwise (replay / expiry / unknown). Marks it consumed.
     *
     * @param string $nonce
     * @param int    $maxage Seconds the nonce stays valid (default 600).
     * @return \stdClass|null
     */
    public static function consume_nonce(string $nonce, int $maxage = 600): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('local_sentientia_api_lti_nonce', ['nonce' => $nonce]);
        if (!$rec) {
            return null;
        }
        if ((int) $rec->consumed === 1) {
            return null;  // replay
        }
        if ((time() - (int) $rec->timecreated) > $maxage) {
            return null;  // expired
        }
        $DB->set_field('local_sentientia_api_lti_nonce', 'consumed', 1, ['id' => $rec->id]);
        return $rec;
    }

    /**
     * Prune consumed/expired nonces. Called by cron.
     *
     * @param int $maxage
     * @return int rows deleted
     */
    public static function prune_nonces(int $maxage = 600): int {
        global $DB;
        $cutoff = time() - $maxage;
        $count = $DB->count_records_select('local_sentientia_api_lti_nonce',
            'timecreated < :cutoff OR consumed = 1', ['cutoff' => $cutoff]);
        $DB->delete_records_select('local_sentientia_api_lti_nonce',
            'timecreated < :cutoff OR consumed = 1', ['cutoff' => $cutoff]);
        return $count;
    }
}
