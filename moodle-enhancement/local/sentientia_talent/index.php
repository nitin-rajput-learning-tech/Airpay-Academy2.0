<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Talent Mobility — HR / manager console.
 *
 * Shows tenant-scoped KPI counts, career paths, succession plans, and a
 * link into per-opportunity applicant lists. Every data call is capability-
 * gated + tenant-scoped inside talent_manager; this page only orchestrates.
 *
 * @package local_sentientia_talent
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

// Feature flag gate — clean "disabled" page when OFF (default).
\local_sentientia_talent\talent_manager::require_enabled();

// At least one HR-facing capability is required to see the console.
$cansuccession = has_capability('local/sentientia_talent:viewsuccession', $context);
$canpaths      = has_capability('local/sentientia_talent:managecareerpaths', $context);
$canopps       = has_capability('local/sentientia_talent:manageopportunities', $context);
if (!$cansuccession && !$canpaths && !$canopps) {
    require_capability('local/sentientia_talent:viewsuccession', $context); // throws.
}

$PAGE->set_url('/local/sentientia_talent/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_console', 'local_sentientia_talent'));
$PAGE->set_heading(get_string('heading_console', 'local_sentientia_talent'));

$counts = \local_sentientia_talent\talent_manager::counts();

$paths = [];
if ($canpaths) {
    foreach (\local_sentientia_talent\talent_manager::list_paths(false) as $p) {
        $paths[] = [
            'name'        => format_string($p->name),
            'from'        => format_string($p->from_designation),
            'to'          => format_string($p->to_designation),
            'active'      => (int) $p->active === 1,
        ];
    }
}

$successions = $cansuccession
    ? \local_sentientia_talent\talent_manager::list_succession()
    : [];

$opportunities = [];
if ($canopps) {
    foreach (\local_sentientia_talent\talent_manager::list_opportunities(false) as $o) {
        $opportunities[] = [
            'id'      => (int) $o->id,
            'title'   => format_string($o->title),
            'status'  => $o->status,
            'designation' => format_string((string) ($o->designation ?? '')),
        ];
    }
}

$data = [
    'count_paths'         => (int) $counts['paths'],
    'count_successions'   => (int) $counts['successions'],
    'count_opportunities' => (int) $counts['opportunities'],
    'can_paths'           => $canpaths,
    'can_succession'      => $cansuccession,
    'can_opps'            => $canopps,
    'has_paths'           => !empty($paths),
    'paths'               => $paths,
    'has_successions'     => !empty($successions),
    'successions'         => $successions,
    'has_opportunities'   => !empty($opportunities),
    'opportunities'       => $opportunities,
    'skillsource'         => get_string('skillsource_'
        . \local_sentientia_talent\skills_bridge::source(), 'local_sentientia_talent'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_talent/console', $data);
echo $OUTPUT->footer();
