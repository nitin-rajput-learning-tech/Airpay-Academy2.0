<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * One code of an importer's reason vocabulary (ADR-032).
 *
 * skip, merge, fold and archive outcomes may only use a code the importer
 * declared in importer::reasons(), so a typo cannot create a silent new category
 * in the parity report.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reason {

    /**
     * Reserved code the framework accepts from every importer.
     *
     * A single-feature dry run whose dependencies were not applied reports the
     * rows that cannot resolve a parent yet as deferred, not as failures. A step
     * uses it when context::is_deferred() is true for the parent table.
     */
    public const DEFERRED = 'deferred';

    /**
     * @param string $code Short code, for example orphan_user, dup_natural_key, not_history.
     * @param bool $retryable Re-attempted by --retry-skipped.
     * @param bool $needsowner Parity exits 2 until the decisions file accepts it.
     */
    public function __construct(
        public readonly string $code,
        public readonly bool $retryable,
        public readonly bool $needsowner,
    ) {
    }
}
