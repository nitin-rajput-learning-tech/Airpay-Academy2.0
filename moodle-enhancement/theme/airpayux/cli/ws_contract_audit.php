<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI tool — audit every datatable consumer for client-contract compliance.
 *
 * Background — Goal A audit Bug #6, #10, #12 (2026-05-22):
 * theme_airpayux/datatable is a shared AMD client used by 9+ WS endpoints
 * across local_airpay_*. It always POSTs the contract
 * {search, sort, sortdir, page, perpage, filters}. Moodle's strict
 * external_function_parameters validator rejects unknown keys, so each
 * consumer WS must declare all 6 with VALUE_DEFAULT — otherwise the
 * datatable hangs on "Loading…" forever.
 *
 * This CLI tool wraps theme_airpayux\ws_contract_scanner so any dev can
 * run the audit on demand without writing PHP. Same logic + assertions
 * as theme/airpayux/tests/ws_contract_test.php — the PHPUnit gate that
 * runs in CI.
 *
 * Usage:
 *   cd <moodle-public-root>
 *   php ../theme/airpayux/cli/ws_contract_audit.php [--verbose] [--json]
 *
 * Options:
 *   --verbose, -v   Print one line per consumer (default: only summary +
 *                   failures)
 *   --json          Print machine-readable JSON output instead of human text
 *   --help, -h      Show this help
 *
 * Exit codes:
 *   0   All contracts satisfied
 *   1   One or more failures — output names + missing keys
 *   2   Scanner found zero consumers (config error?)
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/theme/airpayux/classes/ws_contract_scanner.php');

[$options, $unrecognised] = cli_get_params(
    [
        'help'    => false,
        'verbose' => false,
        'json'    => false,
    ],
    [
        'h' => 'help',
        'v' => 'verbose',
    ]
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(<<<EOT

theme_airpayux WS contract audit
================================

Walks every airpay-plugin mustache for data-region="airpay-datatable"
references, resolves each to its WS endpoint, and verifies the WS
accepts the full shared-client contract:

    {search, sort, sortdir, page, perpage, filters}

Usage:
    php theme/airpayux/cli/ws_contract_audit.php [options]

Options:
    -h, --help       Show this help
    -v, --verbose    Print one line per consumer (default: only summary
                     + failures)
        --json       Print machine-readable JSON instead of human text

Exit codes:
    0   All contracts satisfied
    1   One or more failures
    2   Scanner found zero consumers (config error?)

Background: this tool wraps theme_airpayux\\ws_contract_scanner, the
same code that powers the PHPUnit CI gate in tests/ws_contract_test.php.
Useful for ad-hoc debugging when a datatable hangs on "Loading…".

EOT
    );
    exit(0);
}

$result = \theme_airpayux\ws_contract_scanner::audit();

if ($options['json']) {
    cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit($result['ok'] ? 0 : (empty($result['consumers']) ? 2 : 1));
}

// Human-readable output.
$total = count($result['consumers']);
$nfailures = count($result['failures']);
$nskipped = count($result['skipped']);

cli_writeln('');
cli_writeln('=== theme_airpayux WS contract audit ===');
cli_writeln('');
cli_writeln(sprintf('Consumers scanned: %d', $total));
cli_writeln(sprintf('Failures:          %d', $nfailures));
cli_writeln(sprintf('Skipped:           %d  (class not loadable in this env)',
    $nskipped));
cli_writeln('');

if ($total === 0) {
    cli_writeln('WARNING: zero consumers found. Either the scanner regex is');
    cli_writeln('broken or no plugins use the shared datatable any more.');
    exit(2);
}

if ($options['verbose']) {
    cli_writeln('Per-consumer status:');
    foreach ($result['consumers'] as $wsname => $sources) {
        if (isset($result['failures'][$wsname])) {
            $status = 'FAIL';
        } else if (in_array($wsname, $result['skipped'], true)) {
            $status = 'SKIP';
        } else {
            $status = ' OK ';
        }
        cli_writeln(sprintf('  [%s] %s', $status, $wsname));
        foreach ($sources as $src) {
            cli_writeln(sprintf('         used by: %s', $src));
        }
    }
    cli_writeln('');
}

if ($nfailures > 0) {
    cli_writeln('FAILURES:');
    cli_writeln('');
    foreach ($result['failures'] as $wsname => $missing) {
        cli_writeln(sprintf('  %s', $wsname));
        cli_writeln(sprintf('    missing contract keys: %s',
            implode(', ', $missing)));
        if (isset($result['consumers'][$wsname])) {
            foreach ($result['consumers'][$wsname] as $src) {
                cli_writeln(sprintf('    used by: %s', $src));
            }
        }
        cli_writeln('');
    }
    cli_writeln('Fix pattern: see local/sentientia_request/classes/external/list_mine.php');
    cli_writeln('for the canonical execute_parameters() shape.');
    cli_writeln('');
    exit(1);
}

cli_writeln('AUDIT RESULT: ALL PASS');
cli_writeln('');
exit(0);
