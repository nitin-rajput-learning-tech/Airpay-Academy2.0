<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Source of legacy org facts for the dual-write reconciler — ADR-020 Wave 3.2b.
 *
 * Abstracts WHERE the org graph is read from, so {@see org_reconciler} can run
 * against either the live BizLMS columns ({@see org_legacy_source}) or a
 * synthetic in-memory fixture in unit tests. The reconciler never touches the
 * BizLMS-extended user / cost-center columns directly through this seam, so it
 * stays unit-testable on a vanilla Moodle PHPUnit DB (which has neither).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface org_source {

    /**
     * Iterate the org facts to mirror, one record per user.
     *
     * Each yielded object carries:
     *  - ->userid       int    mdl_user.id
     *  - ->openpath     string BizLMS open_path, e.g. '/1/2/3' (the path through
     *                          the cost-center tree; segment[0] is the tenant root)
     *  - ->supervisorid int    BizLMS open_supervisorid (direct line manager; 0 = none)
     *
     * Implementations SHOULD stream (generator / recordset) rather than
     * materialise every user, to stay within memory on large tenants.
     *
     * @return iterable<\stdClass>
     */
    public function users(): iterable;

    /**
     * Display name for a cost-center / unit id, or null when unknown.
     *
     * The reconciler falls back to "Unit <id>" when this returns null, so a
     * source with no name table is still usable.
     *
     * @param int $costcenterid
     * @return string|null
     */
    public function unit_name(int $costcenterid): ?string;
}
