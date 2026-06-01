<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_core\org;
use local_sentientia_core\org_legacy_source;
use local_sentientia_core\org_reconciler;
use local_sentientia_core\tenant_registry;

/**
 * Scheduled task — mirrors the legacy BizLMS org graph into the Sentientia org
 * model. ADR-020 Wave 3.2b.
 *
 * No-ops unless `org_dualwrite_enabled` is ON (default OFF), so registering the
 * task changes nothing until an admin opts in. Tenant-scoped to the registry's
 * active roots. Idempotent — safe to run on any schedule.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_org extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_reconcile_org', 'local_sentientia_core');
    }

    /**
     * Run the reconciliation, unless dual-write is disabled.
     */
    public function execute(): void {
        if (!org::use_dualwrite()) {
            mtrace('local_sentientia_core: org dual-write disabled (org_dualwrite_enabled OFF) — skipping.');
            return;
        }
        $roots = tenant_registry::valid_roots();
        $reconciler = new org_reconciler(new org_legacy_source());
        $c = $reconciler->reconcile($roots);
        mtrace(sprintf(
            'local_sentientia_core org reconcile: %d users processed, %d skipped; '
            . 'units +%d created / %d updated; members +%d created / %d updated.',
            $c->usersprocessed, $c->usersskipped,
            $c->unitscreated, $c->unitsupdated, $c->memberscreated, $c->membersupdated
        ));
    }
}
