<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring\scd;

defined('MOODLE_INTERNAL') || die();

/**
 * JSON handling for a Sentientia Canonical Course Document (ADR-034 D1).
 *
 * A document is decoded with JSON objects as \stdClass and JSON arrays as PHP
 * lists, never as associative arrays: an associative decode cannot tell `{}`
 * from `[]` or `{"0": "a"}` from `["a"]`, and the schema's type checks and the
 * canonical hash both depend on that difference.
 *
 * The canonical form is the reference harness's `canonical_json()`
 * (pilot/lib/common.py: `json.dumps(obj, sort_keys=True, ensure_ascii=True,
 * separators=(",", ":"))`), byte for byte, so a document hashes the same in
 * PHP and in the harness:
 *   - object keys sorted by code point, recursively; list order kept
 *   - no whitespace
 *   - every character outside printable ASCII escaped as \uXXXX (lowercase
 *     hex, surrogate pairs above U+FFFF), DEL (0x7F) included, as Python does
 *   - '/' not escaped; floats keep a fractional part (1.0 stays 1.0)
 * Known limit: floats in exponent form (1e-05) print differently in the two
 * languages; the schema has no field that needs them.
 *
 * @package local_sentientia_authoring
 */
class document {

    /** Maximum JSON nesting depth accepted on decode. */
    private const MAX_DEPTH = 128;

    /**
     * Decode JSON text into a document value (objects as \stdClass).
     *
     * @param string $json
     * @return mixed The decoded value (a \stdClass for a well-formed document)
     * @throws \moodle_exception err_scd_invalidjson when the text is not JSON
     */
    public static function decode(string $json) {
        try {
            return json_decode($json, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \moodle_exception('err_scd_invalidjson', 'local_sentientia_authoring', '', $e->getMessage());
        }
    }

    /**
     * The canonical JSON text of a document value (see the class docblock).
     *
     * @param mixed $value
     * @return string
     * @throws \coding_exception when the value cannot be encoded (invalid UTF-8)
     */
    public static function canonical($value): string {
        $previous = ini_get('serialize_precision');
        // Shortest round-trip float form, as Python's repr(); PHP's default,
        // but a php.ini may set 17 and print 0.4 as 0.40000000000000002.
        ini_set('serialize_precision', '-1');
        try {
            $json = json_encode(self::sorted($value), JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        } finally {
            ini_set('serialize_precision', (string) $previous);
        }
        if ($json === false) {
            throw new \coding_exception('SCD canonical JSON encoding failed: ' . json_last_error_msg());
        }
        // Python's ensure_ascii escapes DEL; json_encode leaves it raw. A raw
        // 0x7F byte can only be DEL itself here: every non-ASCII character is
        // already a \u escape.
        return str_replace("\x7f", '\u007f', $json);
    }

    /**
     * sha256 of the canonical JSON text.
     *
     * @param mixed $value
     * @return string 64 lowercase hex characters
     */
    public static function sha256($value): string {
        return hash('sha256', self::canonical($value));
    }

    /**
     * Resolve a JSON Pointer (RFC 6901) against a document value.
     *
     * @param mixed  $doc
     * @param string $pointer '' for the whole document, '/a/0/b' for a member
     * @return mixed
     * @throws \OutOfBoundsException when the pointer does not resolve
     */
    public static function resolve($doc, string $pointer) {
        if ($pointer === '') {
            return $doc;
        }
        $node = $doc;
        foreach (array_slice(explode('/', $pointer), 1) as $raw) {
            $key = str_replace(['~1', '~0'], ['/', '~'], $raw);
            if (is_array($node) && ctype_digit($key) && array_key_exists((int) $key, $node)) {
                $node = $node[(int) $key];
            } else if ($node instanceof \stdClass && property_exists($node, $key)) {
                $node = $node->{$key};
            } else {
                throw new \OutOfBoundsException('JSON pointer does not resolve: ' . $pointer);
            }
        }
        return $node;
    }

    /**
     * Append one reference token to a JSON Pointer.
     *
     * @param string     $base
     * @param string|int $token
     * @return string
     */
    public static function join(string $base, $token): string {
        return $base . '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $token);
    }

    /**
     * A copy of the value with every object's keys sorted by code point.
     *
     * Objects are rebuilt as \stdClass (never as arrays), so an object whose
     * keys happen to be "0", "1", ... still encodes as an object.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function sorted($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sorted'], array_values($value));
        }
        if ($value instanceof \stdClass) {
            $props = [];
            foreach (get_object_vars($value) as $key => $member) {
                $props[(string) $key] = $member;
            }
            // UTF-8 byte order is code point order, which is Python's str order.
            uksort($props, static function ($a, $b): int {
                return strcmp((string) $a, (string) $b);
            });
            $out = new \stdClass();
            foreach ($props as $key => $member) {
                $out->{$key} = self::sorted($member);
            }
            return $out;
        }
        return $value;
    }
}
