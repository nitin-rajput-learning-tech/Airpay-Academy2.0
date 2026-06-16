<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Talent Mobility — learner-facing internal opportunity board.
 *
 * Shows open opportunities in the viewer's tenant, each with the viewer's
 * skill-match %, and lets the viewer register / withdraw interest. Never
 * exposes succession data. Gated by the opportunities sub-flag + the
 * registerinterest / viewopportunities capabilities.
 *
 * @package local_sentientia_talent
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

// Master flag must be ON, AND the opportunity sub-flag must be ON.
\local_sentientia_talent\talent_manager::require_enabled();
if (!\local_sentientia_talent\talent_manager::opportunities_enabled()) {
    throw new \moodle_exception('error_featuredisabled', 'local_sentientia_talent');
}
require_capability('local/sentientia_talent:viewopportunities', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$oppid  = optional_param('oppid', 0, PARAM_INT);

// Handle register / withdraw (POST + sesskey only).
if ($action !== '' && $oppid > 0 && confirm_sesskey()) {
    require_sesskey();
    if ($action === 'register') {
        $message = optional_param('message', '', PARAM_TEXT);
        \local_sentientia_talent\talent_manager::register_interest($oppid, $message);
    } else if ($action === 'withdraw') {
        \local_sentientia_talent\talent_manager::withdraw_interest($oppid);
    }
    redirect(new moodle_url('/local/sentientia_talent/opportunities.php'),
        get_string('interest_saved', 'local_sentientia_talent'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url('/local/sentientia_talent/opportunities.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_opportunities', 'local_sentientia_talent'));
$PAGE->set_heading(get_string('heading_opportunities', 'local_sentientia_talent'));

$feed = \local_sentientia_talent\talent_manager::opportunity_feed();
$canregister = has_capability('local/sentientia_talent:registerinterest', $context);

// Career-path hint from the viewer's own designation (graceful: empty if none).
$mydesig = (string) ($USER->open_designation ?? '');
$mypaths = [];
if ($mydesig !== '' && has_capability('local/sentientia_talent:viewcareerpath', $context)) {
    foreach (\local_sentientia_talent\talent_manager::paths_from($mydesig) as $p) {
        $mypaths[] = [
            'name' => format_string($p->name),
            'to'   => format_string($p->to_designation),
        ];
    }
}

$opps = array_map(function ($o) use ($canregister) {
    $o['canregister'] = $canregister;
    $o['sesskey']     = sesskey();
    return $o;
}, $feed);

$data = [
    'has_opportunities' => !empty($opps),
    'opportunities'     => $opps,
    'has_paths'         => !empty($mypaths),
    'paths'             => $mypaths,
    'mydesignation'     => format_string($mydesig),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_talent/opportunities', $data);
echo $OUTPUT->footer();
