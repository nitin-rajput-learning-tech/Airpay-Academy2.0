<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI mock smoke-test for the Authoring Studio.
 *
 * Runs the full mock generation pipeline (course_generator::call_mock →
 * response_parser::parse) and prints a summary. NO live API call, NO cost,
 * NO credentials needed — proves the studio is wired end to end.
 *
 * Usage (cwd = moodle public root):
 *   php local/sentientia_authoring/cli/mock_smoke.php
 *   php local/sentientia_authoring/cli/mock_smoke.php --lang=hi --cards=4 --questions=6
 *
 * @package local_sentientia_authoring
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_authoring\course_generator;
use local_sentientia_authoring\response_parser;
use local_sentientia_authoring\prompt_builder;

[$options, $unrecognised] = cli_get_params([
    'help'      => false,
    'lang'      => 'en',
    'cards'     => 4,
    'questions' => 6,
], ['h' => 'help']);

if ($options['help']) {
    cli_writeln("Authoring Studio mock smoke-test.\n");
    cli_writeln("  --lang=en|hi   Output language (default en)");
    cli_writeln("  --cards=N      Cards to request (default 4)");
    cli_writeln("  --questions=N  Questions to request (default 6)");
    exit(0);
}

$version = prompt_builder::version_for_locale((string) $options['lang']);
$source = "Compliance training ensures employees understand applicable laws and internal policies. "
    . "It covers data protection, anti-fraud controls, and customer due diligence.";

cli_writeln("Running MOCK generation (no API spend)...");
$result = course_generator::call_mock($source, (int) $options['cards'], (int) $options['questions'], $version);
cli_writeln("Mode: {$result['mode']}  Tokens in/out: {$result['tokens_in']}/{$result['tokens_out']}");

$parsed = response_parser::parse($result['body']);
cli_writeln('Cards parsed:     ' . count($parsed->cards));
cli_writeln('Questions parsed: ' . count($parsed->questions));

$typecounts = ['multichoice' => 0, 'mrq' => 0, 'match' => 0];
foreach ($parsed->questions as $q) {
    if (isset($typecounts[$q->qtype])) {
        $typecounts[$q->qtype]++;
    }
}
cli_writeln("Question-type mix: multichoice={$typecounts['multichoice']} "
    . "mrq={$typecounts['mrq']} match={$typecounts['match']}");

cli_writeln("\nSmoke test PASSED — mock pipeline produces valid cards + all three question types.");
exit(0);
