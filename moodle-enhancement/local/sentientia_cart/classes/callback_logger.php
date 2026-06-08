<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * Logs every gateway callback to Moodle's PHP log for audit.
 *
 * Webhook payloads contain transaction references that finance needs
 * for reconciliation when something goes wrong (e.g. payment received
 * but our DB shows failed — we can compare gateway ref).
 */
class callback_logger {

    public static function log(string $gateway, array $payload, string $raw): void {
        // Redact sensitive fields before logging.
        $clean = $payload;
        foreach (['secret', 'checksum', 'password', 'card_number', 'cvv'] as $sensitive) {
            if (isset($clean[$sensitive])) {
                $clean[$sensitive] = '(redacted)';
            }
        }
        $msg = sprintf('[sentientia_cart] gateway=%s payload=%s',
            $gateway, json_encode($clean));
        error_log($msg);
    }
}
