<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Skills Intelligence — skills-gap feed (P0.1.0 MVP).
 *
 * Two views:
 *   - tenant summary (default) — per-skill gap counts for the manager's
 *     tenant, ranked by business priority then affected-user count.
 *   - per-user feed — when ?userid=N is supplied, the individual gap feed
 *     for that user (manager may view any user in their tenant).
 *
 * A "Rebuild" button (POST) recomputes feeds. Gated by the master flag +
 * the gap_engine flag + :viewgaps capability + tenant access.
 *
 * @package local_sentientia_skillsai
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_skillsai\gap_engine;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')
            || !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.gap_engine')) {
        throw new moodle_exception('err_gap_feature_off', 'local_sentientia_skillsai');
    }
}

require_capability('local/sentientia_skillsai:viewgaps', $context);

$userid = optional_param('userid', 0, PARAM_INT);

$PAGE->set_url('/local/sentientia_skillsai/gaps.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('gaps_page_title', 'local_sentientia_skillsai'));
$PAGE->set_heading(get_string('gaps_page_heading', 'local_sentientia_skillsai'));

$manageall = has_capability('local/sentientia_skillsai:manage_all', $context);
$tenantroot = gap_engine::tenant_root_for($USER);

// ── POST: rebuild ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);
    if ($action === 'rebuilduser') {
        $rbuser = required_param('rbuserid', PARAM_INT);
        // Tenant access guard before touching another user's feed.
        if (!$manageall && class_exists('\\local_sentientia_platform\\tenant')) {
            $target = $DB->get_record('user', ['id' => $rbuser], 'id, open_path', MUST_EXIST);
            \local_sentientia_platform\tenant::require_access(gap_engine::tenant_root_for($target));
        }
        $n = gap_engine::rebuild_for_user($rbuser);
        redirect(new moodle_url('/local/sentientia_skillsai/gaps.php', ['userid' => $rbuser]),
            get_string('gaps_rebuilt', 'local_sentientia_skillsai', $n),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// Per-user feed.
if ($userid > 0) {
    // Tenant access guard.
    if (!$manageall && class_exists('\\local_sentientia_platform\\tenant')) {
        $target = $DB->get_record('user', ['id' => $userid], 'id, open_path', MUST_EXIST);
        \local_sentientia_platform\tenant::require_access(gap_engine::tenant_root_for($target));
    }
    $targetuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    echo $OUTPUT->heading(get_string('gaps_user_heading', 'local_sentientia_skillsai',
        fullname($targetuser)));

    $feed = gap_engine::feed_for_user($userid);

    // Rebuild button.
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'rebuilduser']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rbuserid', 'value' => $userid]);
    echo html_writer::tag('button', get_string('gaps_rebuild_user', 'local_sentientia_skillsai'),
        ['type' => 'submit', 'class' => 'btn btn-outline-primary btn-sm']);
    echo html_writer::end_tag('form');

    if (empty($feed)) {
        echo html_writer::div(get_string('gaps_user_none', 'local_sentientia_skillsai'),
            'alert alert-success', ['role' => 'status']);
    } else {
        $table = new html_table();
        $table->head = [
            get_string('col_skill', 'local_sentientia_skillsai'),
            get_string('col_required', 'local_sentientia_skillsai'),
            get_string('col_held', 'local_sentientia_skillsai'),
            get_string('col_gap', 'local_sentientia_skillsai'),
            get_string('col_impact', 'local_sentientia_skillsai'),
        ];
        $table->attributes['class'] = 'generaltable';
        foreach ($feed as $row) {
            $table->data[] = [
                format_string($row->skillname ?? ('#' . $row->skillid)),
                (int)$row->required_level,
                (int)$row->held_level,
                (int)$row->gap_size,
                (int)$row->impact_weight,
            ];
        }
        echo html_writer::table($table);
    }

    echo html_writer::link(new moodle_url('/local/sentientia_skillsai/gaps.php'),
        get_string('gaps_back_summary', 'local_sentientia_skillsai'), ['class' => 'btn btn-secondary mt-2']);
    echo $OUTPUT->footer();
    return;
}

// Tenant summary.
echo $OUTPUT->heading(get_string('gaps_summary_heading', 'local_sentientia_skillsai'));
echo html_writer::div(get_string('gaps_summary_intro', 'local_sentientia_skillsai'), 'mb-3 text-muted');

// Site admins (manage_all) see all tenants; others see their own.
$summary = gap_engine::tenant_summary($manageall ? 0 : $tenantroot, 200);

if (empty($summary)) {
    echo html_writer::div(get_string('gaps_summary_none', 'local_sentientia_skillsai'),
        'alert alert-info', ['role' => 'status']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('col_skill', 'local_sentientia_skillsai'),
        get_string('col_affected', 'local_sentientia_skillsai'),
        get_string('col_maxgap', 'local_sentientia_skillsai'),
        get_string('col_impact', 'local_sentientia_skillsai'),
    ];
    $table->attributes['class'] = 'generaltable';
    foreach ($summary as $row) {
        $table->data[] = [
            format_string($row->skillname ?? ('#' . $row->skillid)),
            (int)$row->affected_users,
            (int)$row->max_gap,
            (int)$row->impact_weight,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
