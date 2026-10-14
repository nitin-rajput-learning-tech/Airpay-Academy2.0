<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\check;

defined('MOODLE_INTERNAL') || die();

use core\check\check;
use core\check\result;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\registry_error;

/**
 * Core status check of the BizLMS import (ADR-032, "Transactions", visibility).
 *
 * Users must never see a half-imported feature. The site stays in maintenance for
 * the window, and this check reports CRITICAL while any applicable feature has
 * started but has no completion marker. Runbook rule: the site does not leave
 * maintenance until admin/cli/checks.php is clean.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bizlms_import extends check {

    /**
     * @return result
     */
    public function get_result(): result {
        global $DB;
        $component = 'local_sentientia_platform';
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_sentientia_legacystep')) {
            return new result(result::NA, get_string('checkbizlms_import_na', $component));
        }

        try {
            $importers = registry::load();
        } catch (registry_error $e) {
            return new result(result::UNKNOWN, get_string('checkbizlms_import_unknown', $component),
                implode("\n", $e->problems));
        }
        if (!$importers) {
            return new result(result::NA, get_string('checkbizlms_import_na', $component));
        }

        $unfinished = [];
        foreach ($importers as $feature => $importer) {
            $applicable = false;
            foreach (array_keys($importer->sources()) as $table) {
                if ($dbman->table_exists($table)) {
                    $applicable = true;
                    break;
                }
            }
            if (!$applicable || legacymap::feature_complete($feature)) {
                continue;
            }
            // Once the runbook has declared production, an applicable feature with no marker is unfinished
            // even if nothing ever started it: the site must not open on legacy history nobody imported.
            // A pending row is a step a run fingerprinted at its start and never opened, so it does not say the
            // feature started (a run writes one for every step of every feature it will process).
            $started = \local_sentientia_platform\bizlms\guard::is_production()
                || $DB->record_exists_select('local_sentientia_legacystep',
                    "feature = :f AND status NOT IN ('not_applicable', 'pending')", ['f' => $feature]);
            if ($started) {
                $unfinished[] = $feature;
            }
        }

        if ($unfinished) {
            return new result(result::CRITICAL,
                get_string('checkbizlms_import_critical', $component, implode(', ', $unfinished)));
        }
        return new result(result::OK, get_string('checkbizlms_import_ok', $component));
    }
}
