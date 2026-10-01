<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Parity oracle of the BizLMS ratings import (ADR-032, mapping doc section 20): compare the average each item
 * shows now with what BizLMS's derived cache (local_ratings_likes) showed, and explain every difference
 * beyond 0.05 stars. Read-only. Run it after the ratings feature has been applied.
 *
 *   php local/sentientia_ratings/cli/ratings_oracle.php            summary and one line per differing item
 *   php local/sentientia_ratings/cli/ratings_oracle.php --json     the same as JSON
 *
 * Output carries item ids, averages and reason codes only, no personal data.
 *
 * Exit codes: 0 every difference is explained (or there is none); 2 a difference nothing explains, which is
 * unproven in the sense of migration_parity_check.php.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['help' => false, 'json' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unknown option: ' . implode(', ', $unrecognised));
}
if ($options['help']) {
    cli_writeln('Compare imported rating averages with the BizLMS cache. Options: --json, --help.');
    exit(0);
}

$result = \local_sentientia_ratings\bizlms\oracle::imported_vs_cache();

if ($options['json']) {
    cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    cli_writeln('items compared with the cache: ' . $result['compared']);
    cli_writeln('items that differ by more than ' . \local_sentientia_ratings\bizlms\oracle::TOLERANCE . ': '
        . count($result['differences']));
    cli_writeln('differences nothing explains: ' . $result['unexplained']);
    foreach ($result['differences'] as $difference) {
        cli_writeln(sprintf('%s cache=%s imported=%s because=%s', $difference['key'], $difference['cache'],
            $difference['imported'] === null ? 'none' : $difference['imported'], implode(',', $difference['because'])));
    }
}
exit($result['unexplained'] > 0 ? 2 : 0);
