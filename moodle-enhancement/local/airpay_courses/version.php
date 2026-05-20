<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version — Airpay Course Engine.
 *
 * Replaces BizLMS local_courses with Airpay-owned course management,
 * progress tracking, and open_* course field ownership.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_courses';
// P1 #21 (2026-05-16) — restore open_coursecompletiondays on edit form.
// P1 #28 (2026-05-20) — daily deadline-reminder cron task. Closes audit
//                       item #14 from parity-audit-2026-05-15/airpay_courses.md.
// P1 #29 (2026-05-20) — daily overdue manager-escalation cron. Closes
//                       audit item #15. Reuses P1 #28's _remind_sent
//                       table with negative bucket values.
$plugin->version   = 2026052002;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.11.0';
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
// Release history:
// 1.6.0  Phase F.5 — native enrol modal (replaces deep-link)
// 1.7.0  Sprint C (2026-05-13) — cross-tenant course sharing:
//          + local_airpay_courses_tenant_share table
//          + share_course / unshare_course / list_course_shares WS
//          + local/airpay_courses:share_to_tenant capability
//          + share.php admin page + share_modal.mustache template
//          + sharing_manager class (CRUD + catalog-filter builder)
//          + audit log entry per share/unshare
// 1.11.0 P1 #29 (2026-05-20) — daily overdue manager-escalation cron.
//          + classes/task/course_overdue.php
//          + db/tasks.php — register at 09:30 daily (offset from #28)
//          + db/messages.php — new course_overdue_supervisor provider
//          + settings.php — overdue_enabled/_days_after/_max_per_run/_last_run
//          + reuses P1 #28's _remind_sent table; negative bucket = post-deadline.
//          Closes audit item #15 from
//          parity-audit-2026-05-15/airpay_courses.md.
// 1.10.0 P1 #28 (2026-05-20) — daily learner deadline-reminder cron.
//          + classes/task/course_reminder.php
//          + db/tasks.php (registers at 09:00 daily, disabled)
//          + db/messages.php (course_reminder provider)
//          + settings.php (reminder_enabled/_days_before/_max_per_run/_last_run)
//          + local_airpay_courses_remind_sent table (idempotency)
//          Closes audit item #14 from
//          parity-audit-2026-05-15/airpay_courses.md.
// 1.9.0  P1 #21 (2026-05-16) — restore open_coursecompletiondays
//          numeric field on edit_course form. Column already existed
//          on mdl_course; persistence wiring in course_manager.
//          Closes audit item #28 from
//          parity-audit-2026-05-15/airpay_courses.md.
// 1.8.0  Sprint D (2026-05-13) — pull/request workflow:
//          + local_airpay_courses_requests table (pending/approved/rejected)
//          + request_course / approve_request / reject_request WS
//          + list_pending_requests / list_my_tenant_requests WS
//          + local/airpay_courses:request_course capability (manager-grantable)
//          + local/airpay_courses:approve_request capability (siteadmin only)
//          + browse_airpay.php (manager catalogue view of the lendable library)
//          + manage_requests.php (admin inbox)
//          + request_manager class
//          + course_share_requested / _approved / _rejected events
