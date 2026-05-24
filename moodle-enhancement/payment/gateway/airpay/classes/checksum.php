<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Checksum helper for the paygw_airpay payment gateway.
 *
 * Loaded as a global `\checksum` class via require_once from
 * classes/airpay_helper.php — pay.php instantiates it as `new \checksum`.
 * Kept in the global namespace for backwards compatibility with the
 * vendor-supplied integration flow; a Phase 2 cleanup should move this
 * under the paygw_airpay namespace and update pay.php accordingly.
 *
 * @package    paygw_airpay
 * @copyright  2024 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class checksum {

    /**
     * MD5 checksum (DEPRECATED — MD5 is cryptographically broken).
     *
     * Kept for binary backwards compatibility with any external caller
     * that may exist outside this codebase. The production payment path
     * (pay.php) uses calculateChecksumSha256() — see paygw_airpay/pay.php:69.
     * The sole internal caller, verifyChecksum(), has been migrated to
     * the SHA-256 variant.
     *
     * @deprecated since 1.0.1 — use {@see calculateChecksumSha256()}
     * @param string $data
     * @param string $secret_key
     * @return string MD5 hex digest
     */
    public static function calculateChecksum($data, $secret_key) {
        debugging(
            'checksum::calculateChecksum() uses MD5 and is deprecated. ' .
            'Use checksum::calculateChecksumSha256() instead.',
            DEBUG_DEVELOPER
        );
        return md5($data . $secret_key);
    }

    public static function encrypt($data, $salt) {
        // Build a 256-bit $key which is a SHA256 hash of $salt and $password.
        return hash('SHA256', $salt . '@' . $data);
    }

    public static function encryptSha256($data) {
        return hash('SHA256', $data);
    }

    public static function calculateChecksumSha256($data, $salt) {
        return hash('SHA256', $salt . '@' . $data);
    }

    public static function outputForm($checksum) {
        echo '<input type="text" name="checksum" value="' . s($checksum) . '"/>' . "\n";
    }

    /**
     * Verify a SHA-256 checksum against a payload.
     *
     * @param string $checksum The checksum to verify
     * @param string $all      The payload that was checksummed
     * @param string $secret   The shared secret/salt
     * @return int 1 on match, 0 on mismatch
     */
    public static function verifyChecksum($checksum, $all, $secret) {
        $cal_checksum = self::calculateChecksumSha256($all, $secret);
        return hash_equals((string) $cal_checksum, (string) $checksum) ? 1 : 0;
    }
}
