<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\task;

defined('MOODLE_INTERNAL') || die();

/**
 * External-trainer expiry (ADR-036 D9, session 5, 2026-11-20). Daily at 01:15.
 *
 * For every active external trainer: recompute the expiry from their sessions (max(last session end,
 * validfrom) + 30 days), remind the inviter and the tenant's admins once when it is 7 days away, and
 * suspend the account (and end its login sessions) once it is reached. The account is never deleted.
 * Then suspend again every expired or revoked trainer whose account is live (unsuspended by any path
 * other than Reactivate; review fix 2026-11-20), and back-fill the ADR-017 type rows once the platform
 * registers the type.
 *
 * Runs whatever sentientia.users.external_trainer.enabled says: expiry is a security control, and it
 * only touches accounts that were invited while the flag was ON. With no such account it does nothing.
 * A failure on one account is reported and does not stop the others (manager::run_expiry()).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_trainer_expiry extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task_exttrainer_expiry', 'local_sentientia_users');
    }

    public function execute(): void {
        $result = \local_sentientia_users\external_trainer\manager::run_expiry(time());
        mtrace('local_sentientia_users external_trainer_expiry: ' . $result['recomputed'] . ' expiry dates changed, '
            . $result['reminded'] . ' reminders, ' . $result['expired'] . ' accounts expired, '
            . $result['resuspended'] . ' ended accounts suspended again, '
            . $result['typed'] . ' user-type rows written.');
    }
}
