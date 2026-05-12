<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Add "My requests" / "Pending approvals" links to user navigation.
 */
function local_airpay_request_extend_navigation_user_settings(navigation_node $navigation, $user, $context) {
    global $USER;
    if ($USER->id != $user->id) return;
    $ctx = context_system::instance();
    if (has_capability('local/airpay_request:request', $ctx)) {
        $navigation->add(get_string('myrequests', 'local_airpay_request'),
            new moodle_url('/local/airpay_request/index.php'),
            navigation_node::TYPE_SETTING, null, 'airpayrequests',
            new pix_icon('i/risk_personal', ''));
    }
    if (has_capability('local/airpay_request:approve', $ctx)) {
        $pending = \local_airpay_request\request_manager::pending_count_for_approver($USER->id);
        $label = get_string('pendingapprovals', 'local_airpay_request');
        if ($pending > 0) {
            $label .= " ($pending)";
        }
        $navigation->add($label,
            new moodle_url('/local/airpay_request/approvals.php'),
            navigation_node::TYPE_SETTING, null, 'airpayapprovals',
            new pix_icon('i/checked', ''));
    }
}
