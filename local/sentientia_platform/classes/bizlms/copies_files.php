<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Optional capability of an importer: it copies files through file_rehome (ADR-032, "Side-effect safety" 3,
 * owner decision IDN-04, signed key framework.file_rehome_copies).
 *
 * The {files} table is watched by the side-effect tripwire for EVERY importer. An importer that copies files (an
 * organisation logo, a cohort description, a learning-plan cover, a classroom or program logo) declares exactly which
 * file areas it reads and writes by implementing this interface, and so is allowed to add rows to {files} in those
 * target areas and in no others. The copies are a reviewed side effect, not a core write:
 *
 *  - copy-only: the source files are never touched (the legacy archive is never altered);
 *  - insert-only and idempotent: a target that already exists is skipped, so a re-run copies nothing;
 *  - not a core_writes() entry, so --purge-feature stays available. A purge leaves the copies behind, which is
 *    harmless: a re-run with PRESERVE ids finds the same item and copies nothing.
 *
 * After finalise() the runner checks that every new {files} row lies in one of the declared target areas (the
 * implementer that writes anywhere else trips the tripwire) and counts the copies per area in the run report
 * (files_copied, for example local_sentientia_org/org_logo = 3).
 *
 * A separate interface keeps the frozen importer contract unchanged: the runner checks instanceof, as it does for
 * watches_tables. Any future importer that calls file_rehome::copy_area() must implement it.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface copies_files {

    /**
     * The file areas this importer copies, one entry per source and target pair.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}> Each entry is
     *         [source component, source file area, target component, target file area]. The source half is
     *         documentation for the reviewer (the runner does not read the source files); the target half is what
     *         the runner enforces. No entry may be empty.
     */
    public function allowed_file_areas(): array;
}
