<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * AI spend ledger — admin view. Today/month headline numbers (the same
 * aggregates the quota checks read), a 30-day per-feature roll-up, and
 * the most recent calls.
 *
 * @package local_sentientia_ai
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_ai:viewledger', $context);

$PAGE->set_url('/local/sentientia_ai/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('ledger_title', 'local_sentientia_ai'));
$PAGE->set_heading(get_string('ledger_title', 'local_sentientia_ai'));

global $DB;

$tokenstoday = \local_sentientia_ai\ledger::tokens_today();
$costmonth   = \local_sentientia_ai\ledger::cost_this_month();
$summary     = \local_sentientia_ai\ledger::component_summary(time() - 30 * DAYSECS);
$recent      = $DB->get_records('local_sentientia_ai_ledger', null, 'timecreated DESC', '*', 0, 50);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('ledger_intro', 'local_sentientia_ai'));

// Headline aggregates.
echo html_writer::start_div('d-flex', ['style' => 'gap:16px;flex-wrap:wrap;margin-bottom:24px;']);
foreach ([
    [get_string('ledger_today', 'local_sentientia_ai'), number_format($tokenstoday)],
    [get_string('ledger_month', 'local_sentientia_ai'), '$' . number_format($costmonth, 4)],
] as [$label, $value]) {
    echo html_writer::div(
        html_writer::div(s($value), '', ['style' => 'font-size:1.5rem;font-weight:700;'])
        . html_writer::div(s($label), 'text-muted'),
        'card p-3');
}
echo html_writer::end_div();

// 30-day per-component roll-up.
echo $OUTPUT->heading(get_string('ledger_bycomponent', 'local_sentientia_ai'), 3);
if ($summary) {
    $table = new html_table();
    $table->head = [
        get_string('ledger_col_component', 'local_sentientia_ai'),
        get_string('ledger_col_calls', 'local_sentientia_ai'),
        get_string('ledger_col_tokens', 'local_sentientia_ai'),
        get_string('ledger_col_cost', 'local_sentientia_ai'),
    ];
    foreach ($summary as $row) {
        $table->data[] = [
            s($row->component),
            (int) $row->calls,
            number_format((int) $row->tokens),
            '$' . number_format((float) $row->estcost, 4),
        ];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('ledger_empty', 'local_sentientia_ai'), ['class' => 'text-muted']);
}

// Recent calls.
echo $OUTPUT->heading(get_string('ledger_recent', 'local_sentientia_ai'), 3);
if ($recent) {
    $table = new html_table();
    $table->head = [
        get_string('ledger_col_time', 'local_sentientia_ai'),
        get_string('ledger_col_component', 'local_sentientia_ai'),
        get_string('ledger_col_purpose', 'local_sentientia_ai'),
        get_string('ledger_col_user', 'local_sentientia_ai'),
        get_string('ledger_col_model', 'local_sentientia_ai'),
        get_string('ledger_col_tokens', 'local_sentientia_ai'),
        get_string('ledger_col_cost', 'local_sentientia_ai'),
        get_string('ledger_col_mode', 'local_sentientia_ai'),
    ];
    foreach ($recent as $row) {
        $table->data[] = [
            userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            s($row->component),
            s($row->purpose),
            (int) $row->userid,
            s($row->model),
            number_format($row->prompttokens) . ' / ' . number_format($row->completiontokens),
            '$' . number_format((float) $row->estcost, 4),
            s($row->mode) . ($row->error !== '' ? ' — ' . s($row->error) : ''),
        ];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('ledger_empty', 'local_sentientia_ai'), ['class' => 'text-muted']);
}

echo $OUTPUT->footer();
