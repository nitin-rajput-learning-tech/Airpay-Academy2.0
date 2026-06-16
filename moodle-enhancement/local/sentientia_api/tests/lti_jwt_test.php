<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\lti;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for LTI 1.3 JWT (RS256) signature + claim verification.
 *
 * Generates a real RSA keypair, mints a signed JWT, and asserts the
 * verifier accepts a valid token and rejects: bad signature, wrong issuer,
 * wrong audience, expired token, the 'none' algorithm, and a tampered nonce.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\lti\jwt_service
 */
final class lti_jwt_test extends \advanced_testcase {

    /** @var string PEM private key */
    private string $privatekey;
    /** @var string PEM public key */
    private string $publickey;

    protected function setUp(): void {
        parent::setUp();
        if (!function_exists('openssl_pkey_new')) {
            $this->markTestSkipped('openssl extension not available.');
        }
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            $this->markTestSkipped('Could not generate RSA key.');
        }
        openssl_pkey_export($res, $this->privatekey);
        $details = openssl_pkey_get_details($res);
        $this->publickey = $details['key'];
    }

    /**
     * Mint a signed RS256 JWT for the given claims.
     */
    private function sign(array $claims, string $alg = 'RS256'): string {
        $header = jwt_service::b64url_encode(json_encode(['alg' => $alg, 'typ' => 'JWT']));
        $payload = jwt_service::b64url_encode(json_encode($claims));
        $signinginput = $header . '.' . $payload;
        if ($alg === 'none') {
            return $signinginput . '.';
        }
        openssl_sign($signinginput, $signature, $this->privatekey, OPENSSL_ALGO_SHA256);
        return $signinginput . '.' . jwt_service::b64url_encode($signature);
    }

    private function valid_claims(): array {
        return [
            'iss'   => 'https://platform.example.com',
            'aud'   => 'client-123',
            'exp'   => time() + 300,
            'iat'   => time(),
            'nonce' => 'expectednonce',
        ];
    }

    public function test_valid_token_verifies(): void {
        $jwt = $this->sign($this->valid_claims());
        $payload = jwt_service::verify($jwt, $this->publickey,
            'https://platform.example.com', 'client-123', 'expectednonce');
        $this->assertSame('https://platform.example.com', $payload['iss']);
    }

    public function test_tampered_signature_rejected(): void {
        $jwt = $this->sign($this->valid_claims());
        $jwt = substr($jwt, 0, -4) . 'AAAA';  // corrupt the signature tail
        $this->expectException(\moodle_exception::class);
        jwt_service::verify($jwt, $this->publickey,
            'https://platform.example.com', 'client-123', 'expectednonce');
    }

    public function test_none_algorithm_rejected(): void {
        $jwt = $this->sign($this->valid_claims(), 'none');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/alg/i');
        jwt_service::verify($jwt, $this->publickey,
            'https://platform.example.com', 'client-123', 'expectednonce');
    }

    public function test_wrong_issuer_rejected(): void {
        $jwt = $this->sign($this->valid_claims());
        $this->expectException(\moodle_exception::class);
        jwt_service::verify($jwt, $this->publickey,
            'https://evil.example.com', 'client-123', 'expectednonce');
    }

    public function test_wrong_audience_rejected(): void {
        $jwt = $this->sign($this->valid_claims());
        $this->expectException(\moodle_exception::class);
        jwt_service::verify($jwt, $this->publickey,
            'https://platform.example.com', 'other-client', 'expectednonce');
    }

    public function test_expired_token_rejected(): void {
        $claims = $this->valid_claims();
        $claims['exp'] = time() - 1000;
        $jwt = $this->sign($claims);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/expired/i');
        jwt_service::verify($jwt, $this->publickey,
            'https://platform.example.com', 'client-123', 'expectednonce');
    }

    public function test_nonce_mismatch_rejected(): void {
        $jwt = $this->sign($this->valid_claims());
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/nonce/i');
        jwt_service::verify($jwt, $this->publickey,
            'https://platform.example.com', 'client-123', 'wrongnonce');
    }

    public function test_malformed_token_rejected(): void {
        $this->expectException(\moodle_exception::class);
        jwt_service::decode('not-a-jwt');
    }
}
