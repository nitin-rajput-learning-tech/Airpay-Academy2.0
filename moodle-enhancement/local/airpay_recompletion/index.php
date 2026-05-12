<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Recompletion rules list — Phase 5 A.3.
 *
 * @package local_airpay_recompletion
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_recompletion/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_airpay_recompletion'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_recompletion'));
require_capability('local/airpay_recompletion:view', $ctx);

$can_manage = has_capability('local/airpay_recompletion:manage', $ctx);

$rules = $DB->get_records('local_airpay_recompletion_rules', null, 'enabled DESC, name ASC');
$rows = [];
foreach ($rules as $r) {
    $course_name = '— all courses with completion —';
    if ($r->courseid > 0) {
        $c = $DB->get_record('course', ['id' => $r->courseid], 'fullname, shortname');
        $course_name = $c ? format_string($c->fullname) : "(deleted course #{$r->courseid})";
    }
    $rows[] = [
        'id'              => (int) $r->id,
        'name'            => format_string($r->name),
        'course'          => $course_name,
        'period_days'     => (int) $r->period_days,
        'trigger'         => $r->trigger_type,
        'enabled'         => (bool) $r->enabled,
        'last_run_at'     => $r->last_run_at ? userdate($r->last_run_at, '%d %b %Y %H:%M') : 'Never',
        'last_run_resets' => (int) ($r->last_run_resets ?? 0),
        'edit_url'        => $can_manage
            ? (new moodle_url('/local/airpay_recompletion/edit.php', ['id' => $r->id]))->out(false)
            : '',
    ];
}

$data = [
    'rules'      => $rows,
    'rule_count' => count($rows),
    'has_rules'  => !empty($rows),
    'can_manage' => $can_manage,
    'new_url'    => (new moodle_url('/local/airpay_recompletion/edit.php'))->out(false),
    'history_url' => (new moodle_url('/local/airpay_recompletion/history.php'))->out(false),
    'bulk_reset_url' => (new moodle_url('/local/airpay_recompletion/bulk_reset.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_recompletion/rules', $data);
echo $OUTPUT->footer();
