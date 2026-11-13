<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring\scd;

defined('MOODLE_INTERNAL') || die();

/**
 * The Sentientia Canonical Course Document (SCD) JSON Schema, v0.1.
 *
 * ADR-034 D1 (accepted 2026-11-12). The schema file is
 * schema/sentientia-course.schema.json in this plugin, a byte-for-byte copy of
 * the reference harness's pilot/schema/sentientia-course.schema.json
 * (docs/research/2026-10-15-scorm-lxp/). Do not edit it here: change the
 * harness copy, re-run its tests, then copy it again, so the PHP side and the
 * reference implementation read the same rules. tests/scd_validator_test.php
 * pins the content hash.
 *
 * Decoded as associative arrays: schema keywords are read by name, and the
 * schema itself contains no empty JSON object whose type would be lost.
 *
 * @package local_sentientia_authoring
 */
class schema {

    /** The only document version this schema accepts (`scd_version` const). */
    public const VERSION = '0.1';

    /** Path of the schema file, relative to the plugin root. */
    public const RELATIVE_PATH = 'schema/sentientia-course.schema.json';

    /** @var array|null Decoded schema, cached for the request. */
    private static $cache = null;

    /**
     * Absolute path of the schema file.
     *
     * @return string
     */
    public static function path(): string {
        return dirname(__DIR__, 2) . '/' . self::RELATIVE_PATH;
    }

    /**
     * The decoded schema.
     *
     * @return array
     * @throws \coding_exception when the file is missing or is not a JSON object
     */
    public static function load(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = self::path();
        $raw = is_readable($path) ? file_get_contents($path) : false;
        if ($raw === false) {
            throw new \coding_exception('SCD schema file missing: ' . self::RELATIVE_PATH);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['$defs']) || !is_array($decoded['$defs'])) {
            throw new \coding_exception('SCD schema file is not a JSON Schema object: ' . self::RELATIVE_PATH);
        }
        self::$cache = $decoded;
        return self::$cache;
    }

    /**
     * The twelve x-rules (R01-R12) as declared in the schema, keyed by rule id.
     *
     * @return array<string,string> e.g. ['R01' => 'Every i18n object MUST ...', ...]
     */
    public static function rules(): array {
        $out = [];
        foreach (self::load()['x-rules'] ?? [] as $text) {
            if (is_string($text) && preg_match('/^(R\d{2})\s+(.*)$/s', $text, $m)) {
                $out[$m[1]] = $m[2];
            }
        }
        return $out;
    }

    /**
     * sha256 of the schema file with line endings normalised to LF, so a
     * Windows checkout (CRLF working tree) and the git blob hash the same.
     *
     * @return string 64 hex characters
     */
    public static function sha256(): string {
        $raw = (string) file_get_contents(self::path());
        return hash('sha256', str_replace("\r\n", "\n", $raw));
    }
}
