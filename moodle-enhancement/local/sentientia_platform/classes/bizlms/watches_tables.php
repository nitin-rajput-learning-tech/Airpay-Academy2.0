<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Optional capability of an importer: extra append-only tables the side-effect
 * tripwire should watch for this feature on top of its fixed list.
 *
 * ADR-032 names "the importer's own extra list" but the frozen importer
 * interface has no method for it. A separate interface keeps the contract
 * unchanged: the runner checks instanceof.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface watches_tables {

    /**
     * Append-only tables (with an id column) to watch in addition to the
     * framework's fixed list. Tables the importer declares as targets or core
     * writes are ignored.
     *
     * @return string[]
     */
    public function watched_tables(): array;
}
