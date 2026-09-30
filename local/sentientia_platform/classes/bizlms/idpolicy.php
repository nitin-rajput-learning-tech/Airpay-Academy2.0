<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Id policy of an import step (ADR-032, "Id strategy").
 *
 * A step is PRESERVE only when both hold: rows the import does not rewrite store
 * its legacy ids (declared in step::external_refs()), and its target table holds
 * no seeded rows. Every other step is MAP: the target gets a new id and every
 * reader resolves the legacy id through legacymap::resolve().
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class idpolicy {

    /** The target row gets a new id; the map records the pairing. */
    public const MAP = 'map';

    /** The target row keeps the legacy id. A collision blocks the whole feature. */
    public const PRESERVE = 'preserve';

    /**
     * Every valid policy.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::MAP, self::PRESERVE];
    }
}
