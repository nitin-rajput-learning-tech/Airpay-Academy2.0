<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\webhooks;

defined('MOODLE_INTERNAL') || die();

/**
 * Performs one signed HTTPS POST for a queued delivery (ADR-030 Wave A).
 *
 * Uses \core\http_client, whose request middleware enforces Moodle's curl
 * security policy (blocked hosts/ports) at send time — the SSRF guard applies
 * even if a URL was valid when the subscription was saved. Redirects are NOT
 * followed (a 3xx to an internal host would otherwise bypass the guard).
 *
 * Tests inject a transport via self::$transport to avoid network I/O.
 *
 * @package local_sentientia_api
 */
class sender {

    /** @var int Seconds. */
    public const TIMEOUT = 10;

    /** @var string */
    public const USER_AGENT = 'Sentientia-Webhooks/1.0';

    /**
     * @var callable|null Test hook: fn(string $url, array $headers, string $body): array{0:int,1:string}
     *                    returning [httpstatus, errorstring].
     */
    public static $transport = null;

    /**
     * Deliver one queued row to its subscription endpoint.
     *
     * @param \stdClass $delivery Row from local_sentientia_api_whdel
     * @param \stdClass $sub      Row from local_sentientia_api_whsub
     * @return array{ok:bool,status:int,error:string}
     */
    public static function deliver(\stdClass $delivery, \stdClass $sub): array {
        $body = (string) $delivery->payload;
        $headers = [
            'Content-Type'          => 'application/json',
            'User-Agent'            => self::USER_AGENT,
            'X-Sentientia-Event'    => (string) $delivery->eventkey,
            'X-Sentientia-Delivery' => (string) $delivery->id,
            signer::HEADER          => signer::sign($body, (string) $sub->secret),
        ];

        if (self::$transport !== null) {
            [$status, $error] = call_user_func(self::$transport, (string) $sub->url, $headers, $body);
        } else {
            try {
                $client = new \core\http_client(['timeout' => self::TIMEOUT, 'connect_timeout' => 5]);
                $response = $client->post((string) $sub->url, [
                    'headers'         => $headers,
                    'body'            => $body,
                    'http_errors'     => false,
                    'allow_redirects' => false,
                ]);
                $status = $response->getStatusCode();
                $error = ($status >= 200 && $status < 300) ? '' : 'HTTP ' . $status;
            } catch (\Throwable $e) {
                $status = 0;
                $error = get_class($e) . ': ' . $e->getMessage();
            }
        }

        $ok = $status >= 200 && $status < 300;
        return [
            'ok'     => $ok,
            'status' => (int) $status,
            'error'  => $ok ? '' : \core_text::substr((string) $error, 0, 255),
        ];
    }
}
