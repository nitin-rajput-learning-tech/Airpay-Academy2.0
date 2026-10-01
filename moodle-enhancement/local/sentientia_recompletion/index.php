<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Recompletion rules list — Phase 5 A.3.
 *
 * @package local_sentientia_recompletion
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_recompletion/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_sentientia_recompletion'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_recompletion'));
require_capability('local/sentientia_recompletion:view', $ctx);

$can_manage = has_capability('local/sentientia_recompletion:manage', $ctx);

// ADR-031: a scoped caller lists only their tenant's rules (not global ones);
// a caller with no tenant, none. Until 2026-09-25 every tenant's rules showed.
[$rulesql, $ruleparams] = \local_sentientia_recompletion\rule_access::rules_filter();
$rules = $DB->get_records_select('local_sentientia_recompletion_rules', $rulesql, $ruleparams,
    'enabled DESC, name ASC');
$rows = [];
foreach ($rules as $r) {
    $course_name = '— all courses with completion —';
    if ($r->courseid > 0) {
        $c = $DB->get_record('course', ['id' => $r->courseid], 'fullname, shortname');
        $course_name = $c ? \local_sentientia_recompletion\evidence_report::plain_name($c->fullname)
            : "(deleted course #{$r->courseid})";
    }
    // ADR-032: a rule the BizLMS import made carries the course's legacy settings; say so, and show them.
    $legacy = [];
    foreach (\local_sentientia_recompletion\legacy_summary::lines($r->legacy_config ?? null) as $line) {
        $label = get_string('legacy_' . $line['name'], 'local_sentientia_recompletion');
        if ($line['kind'] === \local_sentientia_recompletion\legacy_summary::DAYS) {
            $value = get_string('legacy_days', 'local_sentientia_recompletion', $line['value']);
        } else if ($line['kind'] === \local_sentientia_recompletion\legacy_summary::SWITCH) {
            $value = get_string($line['value'] === '1' ? 'yes' : 'no', 'core');
        } else {
            $value = get_string('legacy_choice_' . $line['value'], 'local_sentientia_recompletion');
        }
        $legacy[] = ['label' => $label, 'value' => $value];
    }
    $rows[] = [
        'id'              => (int) $r->id,
        'name'            => \local_sentientia_recompletion\evidence_report::plain_name($r->name),
        'course'          => $course_name,
        'period_days'     => (int) $r->period_days,
        'trigger'         => $r->trigger_type,
        'enabled'         => (bool) $r->enabled,
        'last_run_at'     => $r->last_run_at ? userdate($r->last_run_at, '%d %b %Y %H:%M') : 'Never',
        'last_run_resets' => (int) ($r->last_run_resets ?? 0),
        'edit_url'        => $can_manage
            ? (new moodle_url('/local/sentientia_recompletion/edit.php', ['id' => $r->id]))->out(false)
            : '',
        'legacy'          => $r->legacy_config !== null,
        'legacy_lines'    => $legacy,
        'has_legacy_lines' => !empty($legacy),
        'legacy_dead_scorm' => \local_sentientia_recompletion\legacy_summary::has_dead_scorm_setting($r->legacy_config ?? null),
    ];
}

$data = [
    'rules'      => $rows,
    'rule_count' => count($rows),
    'has_rules'  => !empty($rows),
    'can_manage' => $can_manage,
    'new_url'    => (new moodle_url('/local/sentientia_recompletion/edit.php'))->out(false),
    'history_url' => (new moodle_url('/local/sentientia_recompletion/history.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_recompletion/rules', $data);
echo $OUTPUT->footer();
