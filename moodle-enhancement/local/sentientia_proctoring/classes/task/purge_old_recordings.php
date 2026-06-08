<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Daily cron — delete recording chunks past their retain_until date.
 *
 * Deletes the S3 object AND marks the DB row as deleted. Compliance:
 * retention policy is enforced even if admin forgets.
 *
 * If S3 delete fails (network glitch, credentials revoked), the row
 * stays un-marked and we'll retry tomorrow. Idempotent.
 */
class purge_old_recordings extends \core\task\scheduled_task {

    public function get_name(): string {
        return 'Airpay Proctoring: purge old recordings (GDPR retention)';
    }

    public function execute() {
        global $DB;
        $now = time();
        $rows = $DB->get_records_select('local_sentientia_proctor_recordings',
            'deleted_at IS NULL AND retain_until IS NOT NULL AND retain_until < :n',
            ['n' => $now], 'retain_until ASC', '*', 0, 500);

        $purged = 0;
        $failed = 0;
        foreach ($rows as $r) {
            $ok = $this->delete_s3_object($r->s3_key);
            if ($ok) {
                $r->deleted_at = $now;
                $DB->update_record('local_sentientia_proctor_recordings', $r);
                $purged++;
            } else {
                $failed++;
            }
        }
        mtrace("sentientia_proctoring: purged=$purged failed=$failed pending="
            . $DB->count_records_select('local_sentientia_proctor_recordings',
                'deleted_at IS NULL AND retain_until IS NOT NULL AND retain_until < :n',
                ['n' => $now]));
    }

    /**
     * Delete an S3 object via SigV4-signed HTTPS DELETE.
     *
     * Returns true on 200 / 204 (success), true on 404 (object already
     * gone — idempotent, treat as success), false on any other status
     * or network failure (caller retries tomorrow).
     *
     * Phase 8.2 re-audit N2 fix: this method was a stub in Phase 8.1
     * which silently returned true even when the underlying S3 object
     * remained, meaning GDPR retention was DB-only and the actual
     * bytes lived forever in the bucket. Real implementation now:
     */
    private function delete_s3_object(string $key): bool {
        $bucket = (string) get_config('local_sentientia_proctoring', 'aws_s3_bucket');
        $region = (string) (get_config('local_sentientia_proctoring', 'aws_region') ?: 'ap-south-1');
        $access_key = (string) get_config('local_sentientia_proctoring', 'aws_access_key');
        $secret_key = (string) get_config('local_sentientia_proctoring', 'aws_secret_key');

        if (empty($bucket) || empty($key) || empty($access_key) || empty($secret_key)) {
            // Configuration incomplete — log and treat as failure so the
            // retention obligation isn't silently dropped.
            mtrace("sentientia_proctoring: S3 delete skipped (config incomplete) for key=$key");
            return false;
        }

        // The key may have been registered with a leading slash; strip it
        // for the canonical resource path.
        $key = ltrim($key, '/');

        $host     = $bucket . '.s3.' . $region . '.amazonaws.com';
        $endpoint = 'https://' . $host . '/' . str_replace('%2F', '/', rawurlencode($key));

        $now_ts   = gmdate('Ymd\THis\Z');
        $now_date = gmdate('Ymd');

        // SigV4 canonical request for S3 DELETE.
        $payload_hash = hash('sha256', '');

        $canonical_headers = "host:$host\nx-amz-content-sha256:$payload_hash\nx-amz-date:$now_ts\n";
        $signed_headers    = 'host;x-amz-content-sha256;x-amz-date';

        $canonical_uri    = '/' . str_replace('%2F', '/', rawurlencode($key));
        $canonical_request = "DELETE\n$canonical_uri\n\n$canonical_headers\n$signed_headers\n$payload_hash";

        $scope = "$now_date/$region/s3/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n$now_ts\n$scope\n" . hash('sha256', $canonical_request);

        $k_date    = hash_hmac('sha256', $now_date, 'AWS4' . $secret_key, true);
        $k_region  = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        $authz = "AWS4-HMAC-SHA256 Credential=$access_key/$scope, SignedHeaders=$signed_headers, Signature=$signature";

        $headers = [
            "Host: $host",
            "x-amz-content-sha256: $payload_hash",
            "x-amz-date: $now_ts",
            "Authorization: $authz",
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        // S3 DELETE returns 204 on success, 404 if already gone.
        if ($http === 204 || $http === 200 || $http === 404) {
            return true;
        }

        // Log the failure for ops triage.
        mtrace("sentientia_proctoring: S3 delete failed http=$http key=$key err="
            . substr($curl_err ?: (string) $body, 0, 200));
        return false;
    }
}
