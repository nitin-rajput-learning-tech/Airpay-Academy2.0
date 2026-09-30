<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Proof that the CLI guard let an apply (or a rehearsal purge) through (ADR-032, "Gating").
 *
 * The guard conditions (confirm, arming, maintenance, noemailever, cron) used to be checked only
 * by cli/import_bizlms.php, so any other caller of the runner with apply = true skipped all of
 * them. The runner now refuses to write or delete without a permit, and only guard can issue one:
 * guard::permit() after the refusals came back empty, guard::test_permit() under PHPUnit.
 *
 * It is a seam, not a lock: PHP has no friend visibility, and code that wants to forge a permit
 * can. The caller check stops the honest mistakes (a new CLI, a cron task, a test helper that
 * forgot the guard), and the static scan keeps importers away from the guard class.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guard_permit {

    /** Permit for an apply run (--apply, --resume, --retry-skipped). */
    public const APPLY = 'apply';

    /** Permit for a rehearsal purge (--purge-feature). */
    public const PURGE = 'purge';

    /**
     * @param string $kind APPLY or PURGE.
     */
    private function __construct(public readonly string $kind) {
    }

    /**
     * Issue a permit. Only guard may call this.
     *
     * @param string $kind APPLY or PURGE.
     * @return self
     * @throws \coding_exception When another class calls it, or the kind is unknown.
     */
    public static function issue(string $kind): self {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? '';
        if ($caller !== guard::class) {
            throw new \coding_exception('only the guard issues a guard permit');
        }
        if (!in_array($kind, [self::APPLY, self::PURGE], true)) {
            throw new \coding_exception('unknown guard permit kind');
        }
        return new self($kind);
    }
}
