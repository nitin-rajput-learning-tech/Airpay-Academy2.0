<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Reviewer badge — show count of pending review items in nav.
 */
function local_sentientia_proctoring_extend_navigation_user_settings(navigation_node $navigation, $user, $context) {
    global $USER, $DB;
    if ($USER->id != $user->id) return;
    $ctx = context_system::instance();
    if (has_capability('local/sentientia_proctoring:review', $ctx)) {
        // ADR-031: count only what list_review_queue would show this reviewer
        // (tenant::sql_filter: 1=1 cross-tenant, their tenant otherwise, 1=0
        // with no tenant). The badge used to count every tenant's flags.
        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::sql_filter();
        $pending = $DB->count_records_select('local_sentientia_proctor_sessions',
            "status = :pstatus AND {$tnsql}", ['pstatus' => 'flagged'] + $tnargs);
        $label = get_string('reviewqueue', 'local_sentientia_proctoring');
        if ($pending > 0) $label .= " ($pending)";
        $navigation->add($label,
            new moodle_url('/local/sentientia_proctoring/review.php'),
            navigation_node::TYPE_SETTING, null, 'proctorreview',
            new pix_icon('i/checked', ''));
    }
}
