<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Reviewer badge — show count of pending review items in nav.
 */
function local_airpay_proctoring_extend_navigation_user_settings(navigation_node $navigation, $user, $context) {
    global $USER, $DB;
    if ($USER->id != $user->id) return;
    $ctx = context_system::instance();
    if (has_capability('local/airpay_proctoring:review', $ctx)) {
        $pending = $DB->count_records('local_airpay_proctor_sessions',
            ['status' => 'flagged']);
        $label = get_string('reviewqueue', 'local_airpay_proctoring');
        if ($pending > 0) $label .= " ($pending)";
        $navigation->add($label,
            new moodle_url('/local/airpay_proctoring/review.php'),
            navigation_node::TYPE_SETTING, null, 'proctorreview',
            new pix_icon('i/checked', ''));
    }
}
