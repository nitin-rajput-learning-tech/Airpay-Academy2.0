<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Board list + management page.
 *
 * Admin/trainer view: lists all boards visible to the caller with create,
 * edit, delete actions. Learner view: redirects to the first visible
 * board's view page (the block widget is the primary learner surface).
 *
 * @package local_sentientia_leaderboard
 */

require_once(__DIR__ . '/../../config.php');

require_login();
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$context = context_system::instance();
require_capability('local/sentientia_leaderboard:view', $context);

// Master flag gate.
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.leaderboards.enabled')) {
        $PAGE->set_url('/local/sentientia_leaderboard/index.php');
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('pluginname', 'local_sentientia_leaderboard'));
        $PAGE->set_heading(get_string('pluginname', 'local_sentientia_leaderboard'));
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('feature_disabled', 'local_sentientia_leaderboard'),
            \core\output\notification::NOTIFY_WARNING);
        echo $OUTPUT->footer();
        exit;
    }
}

$PAGE->set_url('/local/sentientia_leaderboard/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_index', 'local_sentientia_leaderboard'));
$PAGE->set_heading(get_string('heading_index', 'local_sentientia_leaderboard'));

$can_view_all = has_capability('local/sentientia_leaderboard:viewall', $context);
$viewer_root = \local_sentientia_platform\tenant::root_for_current_user();

$boards = \local_sentientia_leaderboard\board_manager::list_visible(
    $viewer_root, $can_view_all);

// Render the boards list.
$board_data = [];
foreach ($boards as $b) {
    $board_data[] = [
        'id'            => (int) $b->id,
        'name'          => format_string($b->name),
        'type'          => $b->type,
        'type_label'    => get_string('type_' . $b->type,
            'local_sentientia_leaderboard'),
        'scope'         => $b->scope,
        'scope_label'   => get_string('scope_' . $b->scope,
            'local_sentientia_leaderboard'),
        'view_url'      => (new moodle_url('/local/sentientia_leaderboard/view.php',
            ['id' => (int) $b->id]))->out(false),
        'last_recomputed' => (int) $b->last_recomputed > 0
            ? userdate((int) $b->last_recomputed) : '-',
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading_index', 'local_sentientia_leaderboard'));

if (empty($board_data)) {
    echo $OUTPUT->notification(
        get_string('block_none', 'local_sentientia_leaderboard'),
        \core\output\notification::NOTIFY_INFO);
} else {
    echo $OUTPUT->render_from_template('local_sentientia_leaderboard/boards_list',
        ['boards' => $board_data]);
}

echo $OUTPUT->footer();
