<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Daily cron — sync local_sentientia_org tree into Moodle core cohorts.
 *
 * For each org node, ensures a cohort exists with idnumber="ap_org_{id}".
 * Then walks all users and ensures each is in the cohorts matching every
 * ancestor of their open_path. Stale cohort memberships (user moved to a
 * different dept) are removed.
 *
 * Why: Moodle core supports cohort-based enrolment, cohort-scoped reports,
 * and cohort permissions. By auto-syncing, we unlock all of that without
 * admin manual maintenance.
 *
 * Idempotent. Safe to run multiple times.
 *
 * Phase 6 F.5 (2026-05-11).
 */
class sync_cohorts extends \core\task\scheduled_task {

    private const IDNUMBER_PREFIX = 'ap_org_';

    public function get_name(): string {
        return 'Airpay Org: sync org tree → Moodle cohorts';
    }

    public function execute() {
        global $DB;
        require_once($GLOBALS['CFG']->dirroot . '/cohort/lib.php');

        $context_id = \context_system::instance()->id;

        $cohorts_created = 0;
        $cohorts_updated = 0;
        $memberships_added = 0;
        $memberships_removed = 0;

        // 1. Ensure a cohort exists for every org node.
        $org_nodes = $DB->get_records('local_sentientia_org', null, 'depth ASC, path ASC',
            'id, fullname, shortname, path, depth, visible');

        $org_to_cohort = [];  // org_id => cohort_id
        foreach ($org_nodes as $org) {
            if (!$org->visible) continue;
            $idnumber = self::IDNUMBER_PREFIX . $org->id;
            $existing = $DB->get_record('cohort', ['idnumber' => $idnumber]);
            if ($existing) {
                // Name might have changed.
                $expected_name = self::cohort_name_for($org);
                if ($existing->name !== $expected_name) {
                    $existing->name = $expected_name;
                    $existing->timemodified = time();
                    cohort_update_cohort($existing);
                    $cohorts_updated++;
                }
                $org_to_cohort[(int) $org->id] = (int) $existing->id;
            } else {
                $c = new \stdClass();
                $c->contextid     = $context_id;
                $c->name          = self::cohort_name_for($org);
                $c->idnumber      = $idnumber;
                $c->description   = "Auto-synced from local_sentientia_org id={$org->id} (path={$org->path}). "
                    . "Managed by local_sentientia_org sync_cohorts task — do not edit manually.";
                $c->descriptionformat = FORMAT_PLAIN;
                $c->visible       = 1;
                $c->component     = 'local_sentientia_org';  // marks as plugin-managed
                $cohort_id = cohort_add_cohort($c);
                $org_to_cohort[(int) $org->id] = (int) $cohort_id;
                $cohorts_created++;
            }
        }

        // 2. For each user with open_path set, ensure they're in cohorts
        //    for every ancestor in their path.
        $users = $DB->get_recordset_select('user',
            "deleted = 0 AND suspended = 0 AND open_path IS NOT NULL AND open_path != ''",
            null, '', 'id, open_path');

        $expected_memberships = [];  // userid => set of cohort_ids
        $now = time();

        foreach ($users as $u) {
            $parts = array_filter(explode('/', trim($u->open_path, '/')));
            $current_path = '';
            foreach ($parts as $part) {
                $current_path .= '/' . $part;
                $org = $DB->get_record('local_sentientia_org', ['path' => $current_path], 'id');
                if (!$org) continue;
                if (!isset($org_to_cohort[(int) $org->id])) continue;
                $cohort_id = $org_to_cohort[(int) $org->id];
                $expected_memberships[(int) $u->id][$cohort_id] = true;

                if (!$DB->record_exists('cohort_members',
                    ['cohortid' => $cohort_id, 'userid' => $u->id])) {
                    cohort_add_member($cohort_id, $u->id);
                    $memberships_added++;
                }
            }
        }
        $users->close();

        // 3. Remove stale memberships — users in plugin-owned cohorts who
        //    no longer have that org in their path.
        foreach ($org_to_cohort as $org_id => $cohort_id) {
            $current = $DB->get_records('cohort_members',
                ['cohortid' => $cohort_id], '', 'userid');
            foreach ($current as $row) {
                if (empty($expected_memberships[(int) $row->userid][$cohort_id])) {
                    cohort_remove_member($cohort_id, (int) $row->userid);
                    $memberships_removed++;
                }
            }
        }

        mtrace(sprintf("sentientia_org sync_cohorts: created=%d updated=%d "
            . "members_added=%d members_removed=%d",
            $cohorts_created, $cohorts_updated,
            $memberships_added, $memberships_removed));
    }

    /** Cohort display name from org node. Format: "<fullname> (path)". */
    private static function cohort_name_for(\stdClass $org): string {
        $name = trim($org->fullname ?? $org->shortname ?? "Org #{$org->id}");
        return $name . ' (' . $org->path . ')';
    }
}
