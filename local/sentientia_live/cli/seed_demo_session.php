<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * QA / demo helper — seed one LIVE Sentientia Live session containing one
 * slide of every question type, a few anonymous participants, and a
 * spread of responses, so a trainer can immediately open trainer/run.php
 * and see populated result panels, and an audience browser can join by
 * code and respond.
 *
 * This exercises the full server-side chain on the REAL database:
 *   session_manager::create  -> slide_manager::add (x6)
 *   -> session_manager::start_session / set_current_slide
 *   -> participant_manager::join_or_resume (anonymous)
 *   -> response_recorder::submit (per type, with type-correct values)
 *   -> response_recorder::tally (printed back for verification)
 *
 * Each response is wrapped in try/catch and reported individually, so a
 * single bad value shape surfaces the exact moodle_exception without
 * aborting the rest of the seed.
 *
 * Prereq: the Live engagement flags must be ON
 *   php local/sentientia_live/cli/set_live_flags.php --on
 *
 * Usage:
 *   php local/sentientia_live/cli/seed_demo_session.php
 *
 * @package local_sentientia_live
 * @copyright 2026 Airpay Payment Services
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_live\session_manager;
use local_sentientia_live\slide_manager;
use local_sentientia_live\participant_manager;
use local_sentientia_live\response_recorder;

[$options, $unrecognized] = cli_get_params([
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    echo <<<EOT

Seed a LIVE Sentientia Live session with all 6 question types + responses.

Usage:
  php local/sentientia_live/cli/seed_demo_session.php

Prints the session id, join code, and the trainer + audience URLs. The
session is created with allow_anonymous=1 so a fresh browser can join by
code with just a display name (no login).

EOT;
    exit(0);
}

$admin = get_admin();
if (!$admin) {
    cli_error('No admin user found.');
}
\core\session\manager::set_user($admin);

cli_heading('Seeding Sentientia Live demo session (6 question types)');

// ── Session ─────────────────────────────────────────────────────────
$sid = session_manager::create(
    (int) $admin->id,
    'QA Demo — 6 Question Types ' . date('H:i'),
    ['allow_anonymous' => 1]
);
$sess = session_manager::get($sid);
cli_writeln("  session id = {$sid}");
cli_writeln("  join code  = {$sess->code}");
cli_writeln("  anon       = " . (json_decode($sess->settings_json, true)['allow_anonymous'] ?? '(not set)'));

// ── Slides — one per type, in a sensible demo order ─────────────────
$slidedefs = [
    'multichoice' => ['Favourite colour?',
        ['options' => ['Red', 'Green', 'Blue'], 'render_style' => 'radio']],
    'wordcloud'   => ['One word for today',
        []],
    'openended'   => ['Any feedback for us?',
        ['max_chars' => 500]],
    'rating'      => ['Rate this session',
        ['scale_type' => 'stars', 'scale_min' => 1, 'scale_max' => 5]],
    'quiz'        => ['Capital of France?',
        ['options' => ['Paris', 'London', 'Berlin'], 'correct_index' => 0]],
    'ranking'     => ['Rank by importance',
        ['items' => ['Quality', 'Speed', 'Price']]],
];

$slides = [];
cli_writeln('');
cli_heading('Slides');
foreach ($slidedefs as $type => [$title, $settings]) {
    try {
        $slides[$type] = slide_manager::add($sid, $type, $title, $settings);
        cli_writeln(sprintf('  [ok]   %-12s slide id=%d  "%s"',
            $type, $slides[$type], $title));
    } catch (\Throwable $e) {
        cli_writeln(sprintf('  [FAIL] %-12s %s', $type, $e->getMessage()));
    }
}

// ── Go live + park on the first slide ───────────────────────────────
session_manager::start_session($sid);
if (!empty($slides['multichoice'])) {
    session_manager::set_current_slide($sid, $slides['multichoice']);
}

// ── Participants (anonymous join) ───────────────────────────────────
cli_writeln('');
cli_heading('Participants (anonymous)');
$participants = [];
foreach (['Alice', 'Bob', 'Carol'] as $name) {
    try {
        $p = participant_manager::join_or_resume($sid, null, $name);
        $participants[$name] = (int) $p->id;
        cli_writeln(sprintf('  [ok]   %-6s participant id=%d', $name, $p->id));
    } catch (\Throwable $e) {
        cli_writeln(sprintf('  [FAIL] %-6s %s', $name, $e->getMessage()));
    }
}

// ── Responses — type-correct value shapes, per participant ──────────
// Format: type => [ participantName => [value_int, value_text], ... ]
$responses = [
    'multichoice' => [
        'Alice' => [0, null], 'Bob' => [1, null], 'Carol' => [0, null],
    ],
    'quiz' => [
        'Alice' => [0, null], 'Bob' => [2, null], 'Carol' => [0, null],
    ],
    'rating' => [
        'Alice' => [5, null], 'Bob' => [4, null], 'Carol' => [5, null],
    ],
    'openended' => [
        'Alice' => [null, 'Loved the pace and the live polls.'],
        'Bob'   => [null, 'Very practical, thank you.'],
    ],
    'wordcloud' => [
        'Alice' => [null, json_encode(['innovation', 'speed'])],
        'Bob'   => [null, json_encode(['trust'])],
        'Carol' => [null, json_encode(['innovation'])],
    ],
    'ranking' => [
        // JSON array of item indices in the responder's preferred order.
        'Alice' => [null, json_encode([0, 1, 2])],
        'Bob'   => [null, json_encode([1, 0, 2])],
        'Carol' => [null, json_encode([0, 2, 1])],
    ],
];

cli_writeln('');
cli_heading('Responses');
foreach ($responses as $type => $byparticipant) {
    if (empty($slides[$type])) {
        cli_writeln("  [skip] {$type} — slide not created");
        continue;
    }
    foreach ($byparticipant as $name => [$vint, $vtext]) {
        if (!isset($participants[$name])) {
            continue;
        }
        try {
            response_recorder::submit($slides[$type], $participants[$name],
                $vint, $vtext);
            cli_writeln(sprintf('  [ok]   %-12s %-6s', $type, $name));
        } catch (\Throwable $e) {
            cli_writeln(sprintf('  [FAIL] %-12s %-6s %s',
                $type, $name, $e->getMessage()));
        }
    }
}

// ── Tally readback — proves aggregation works per type ──────────────
cli_writeln('');
cli_heading('Tally readback');
foreach ($slides as $type => $slideid) {
    try {
        $tally = response_recorder::tally($slideid);
        cli_writeln(sprintf('  %-12s %s', $type,
            json_encode($tally, JSON_UNESCAPED_UNICODE)));
    } catch (\Throwable $e) {
        cli_writeln(sprintf('  [FAIL] %-12s %s', $type, $e->getMessage()));
    }
}

// ── URLs for the two-browser test ──────────────────────────────────
$base = $CFG->wwwroot;
cli_writeln('');
cli_heading('URLs');
cli_writeln("  Trainer run : {$base}/local/sentientia_live/trainer/run.php?id={$sid}");
cli_writeln("  Audience join: {$base}/local/sentientia_live/audience/join.php");
cli_writeln("  Join code   : {$sess->code}");
cli_writeln('');
cli_writeln('Done.');
exit(0);
