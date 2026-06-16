<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\lti;

defined('MOODLE_INTERNAL') || die();

/**
 * LTI 1.3 launch processor (provider side — Sentientia launched as a tool).
 *
 * Given an incoming `id_token` JWT and the OIDC `state`, this validates the
 * launch end-to-end and returns the verified claims. The caller (the launch
 * endpoint) then maps claims → a Sentientia user/session.
 *
 * Validation order (all must pass):
 *   1. Decode the JWT header to read iss/aud loosely for registration lookup.
 *   2. Resolve the registration by (iss, client_id) within the tenant.
 *   3. Fetch the platform public key from the registration; fail closed if absent.
 *   4. Consume the stored nonce (replay protection) and recover the expected nonce.
 *   5. Verify the JWT signature + claims via jwt_service::verify().
 *
 * @package local_sentientia_api
 */
class launch {

    /**
     * Process an incoming LTI 1.3 launch.
     *
     * @param string $idtoken      The id_token JWT from the platform.
     * @param string $state        The OIDC state echoed back.
     * @param int    $costcenterid Tenant the launch endpoint is scoped to.
     * @return array{registration:\stdClass, claims:array}
     * @throws \moodle_exception on any validation failure.
     */
    public static function process(string $idtoken, string $state, int $costcenterid): array {
        // 1. Peek at unverified header/payload to find iss + client_id.
        //    We do NOT trust these yet — they only select which registration
        //    (and thus which public key) to verify against.
        [, $unverified] = jwt_service::decode($idtoken);
        $iss = (string) ($unverified['iss'] ?? '');
        // In LTI the tool's client_id is the `aud` claim.
        $aud = $unverified['aud'] ?? '';
        $clientid = is_array($aud) ? (string) ($aud[0] ?? '') : (string) $aud;

        if ($iss === '' || $clientid === '') {
            throw new \moodle_exception('lti_invalid_token', 'local_sentientia_api');
        }

        // 2. Resolve the registration (tenant-scoped).
        $reg = registration::find($iss, $clientid, $costcenterid);
        if (!$reg) {
            throw new \moodle_exception('lti_no_registration', 'local_sentientia_api');
        }

        // 3. Public key (fail closed when unavailable).
        $pubkey = registration::public_key($reg);
        if ($pubkey === '') {
            throw new \moodle_exception('lti_no_key', 'local_sentientia_api');
        }

        // 4. Consume nonce by state → recover the expected nonce value.
        $noncerec = null;
        if ($state !== '') {
            global $DB;
            $bystate = $DB->get_record('local_sentientia_api_lti_nonce',
                ['state' => $state, 'regid' => $reg->id]);
            if ($bystate) {
                $noncerec = registration::consume_nonce($bystate->nonce);
            }
        }
        $expectednonce = $noncerec ? (string) $noncerec->nonce : null;
        if ($expectednonce === null) {
            // No matching, unconsumed, fresh nonce — reject as replay/forgery.
            throw new \moodle_exception('lti_bad_nonce', 'local_sentientia_api');
        }

        // 5. Full signature + claim verification.
        $claims = jwt_service::verify($idtoken, $pubkey, $iss, $clientid, $expectednonce);

        return ['registration' => $reg, 'claims' => $claims];
    }
}
