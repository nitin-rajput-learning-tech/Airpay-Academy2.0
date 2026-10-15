<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Add "My requests" / "Pending approvals" links to user navigation.
 */
function local_sentientia_request_extend_navigation_user_settings(navigation_node $navigation, $user, $context) {
    global $USER;
    if ($USER->id != $user->id) return;
    $ctx = context_system::instance();
    if (has_capability('local/sentientia_request:request', $ctx)) {
        $navigation->add(get_string('myrequests', 'local_sentientia_request'),
            new moodle_url('/local/sentientia_request/index.php'),
            navigation_node::TYPE_SETTING, null, 'airpayrequests',
            new pix_icon('i/risk_personal', ''));
    }
    // has_approvals_entry(): holding :approve is not enough to be shown the link. Every employee now holds it
    // (db/access.php, 2026-10-15) so that a line manager can approve; the link appears for those a request can
    // be routed to, exactly as it did before for the manager archetype.
    if (\local_sentientia_request\request_manager::has_approvals_entry((int) $USER->id)) {
        $pending = \local_sentientia_request\request_manager::pending_count_for_approver($USER->id);
        $label = get_string('pendingapprovals', 'local_sentientia_request');
        if ($pending > 0) {
            $label .= " ($pending)";
        }
        $navigation->add($label,
            new moodle_url('/local/sentientia_request/approvals.php'),
            navigation_node::TYPE_SETTING, null, 'airpayapprovals',
            new pix_icon('i/checked', ''));
    }
}
