<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_proctoring\identity;

defined('MOODLE_INTERNAL') || die();

/**
 * AWS Rekognition identity verifier.
 *
 * Uses Rekognition's CompareFaces API:
 *   POST https://rekognition.{region}.amazonaws.com/
 *   X-Amz-Target: RekognitionService.CompareFaces
 *
 * Body: { "SourceImage": {"Bytes": base64(id)},
 *         "TargetImage": {"Bytes": base64(selfie)},
 *         "SimilarityThreshold": 70 }
 *
 * Response: { "FaceMatches": [{ "Similarity": 92.5, "Face": {...} }] }
 *
 * Cost (2026): ~$0.001 per CompareFaces call. At 8,000 quiz attempts/quarter
 * that's ~$32/quarter or $128/year. Negligible.
 *
 * Auth: AWS Signature Version 4. Implementation here uses the SigV4
 * signer from a minimal in-tree helper (no SDK dependency).
 */
class aws_verifier implements identity_verifier_interface {

    public function get_name(): string {
        return 'aws';
    }

    public function verify(string $id_photo_bytes, string $selfie_bytes): array {
        $region = (string) (get_config('local_airpay_proctoring', 'aws_region') ?: 'ap-south-1');
        $key    = (string) get_config('local_airpay_proctoring', 'aws_key');
        $secret = (string) get_config('local_airpay_proctoring', 'aws_secret');
        $threshold = (int) (get_config('local_airpay_proctoring', 'match_threshold') ?: 85);

        if (empty($key) || empty($secret)) {
            return [
                'passed' => false, 'score' => 0.0,
                'error_code' => 'no_credentials',
                'error_msg'  => 'AWS credentials not configured',
            ];
        }

        $endpoint = "https://rekognition.{$region}.amazonaws.com/";
        $payload = json_encode([
            'SourceImage'         => ['Bytes' => base64_encode($id_photo_bytes)],
            'TargetImage'         => ['Bytes' => base64_encode($selfie_bytes)],
            'SimilarityThreshold' => (float) $threshold,
        ]);

        $headers = self::sign_request($endpoint, $payload, $region, $key, $secret);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http !== 200) {
            return [
                'passed' => false, 'score' => 0.0,
                'error_code' => 'aws_http_' . $http,
                'error_msg'  => substr($body, 0, 500),
            ];
        }

        $data = json_decode($body, true);
        $matches = $data['FaceMatches'] ?? [];
        if (empty($matches)) {
            return [
                'passed' => false, 'score' => 0.0,
                'error_code' => 'no_face_match',
                'error_msg'  => 'No matching face detected',
            ];
        }
        $best = max(array_column($matches, 'Similarity'));
        return [
            'passed'     => $best >= $threshold,
            'score'      => (float) $best,
            'error_code' => null, 'error_msg' => null,
        ];
    }

    /**
     * Minimal AWS SigV4 signer for the CompareFaces endpoint.
     * (No AWS SDK dependency — keeps the plugin lean.)
     */
    private static function sign_request(string $endpoint, string $payload,
                                          string $region, string $key,
                                          string $secret): array {
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $host = parse_url($endpoint, PHP_URL_HOST);
        $service = 'rekognition';
        $target  = 'RekognitionService.CompareFaces';
        $payload_hash = hash('sha256', $payload);

        $canonical = "POST\n/\n\n"
            . "content-type:application/x-amz-json-1.1\n"
            . "host:$host\n"
            . "x-amz-date:$now\n"
            . "x-amz-target:$target\n\n"
            . "content-type;host;x-amz-date;x-amz-target\n"
            . $payload_hash;

        $string_to_sign = "AWS4-HMAC-SHA256\n$now\n$date/$region/$service/aws4_request\n"
            . hash('sha256', $canonical);

        $k1 = hash_hmac('sha256', $date,      'AWS4' . $secret, true);
        $k2 = hash_hmac('sha256', $region,    $k1, true);
        $k3 = hash_hmac('sha256', $service,   $k2, true);
        $k4 = hash_hmac('sha256', 'aws4_request', $k3, true);
        $sig = hash_hmac('sha256', $string_to_sign, $k4);

        $auth = "AWS4-HMAC-SHA256 "
            . "Credential=$key/$date/$region/$service/aws4_request, "
            . "SignedHeaders=content-type;host;x-amz-date;x-amz-target, "
            . "Signature=$sig";

        return [
            'Authorization: ' . $auth,
            'Content-Type: application/x-amz-json-1.1',
            'Host: ' . $host,
            'X-Amz-Date: ' . $now,
            'X-Amz-Target: ' . $target,
        ];
    }
}
