<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Mock-subscriber teardown — removes everything setup_mock_subscription
 * created. Safe to call repeatedly.
 *
 * Usage:
 *   php local/sentientia_pwa/cli/teardown_mock.php --userid=N
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'   => false,
    'userid' => 0,
], [
    'h' => 'help',
    'u' => 'userid',
]);

if ($options['help'] || empty($options['userid'])) {
    cli_writeln('Usage: php teardown_mock.php --userid=N');
    exit(1);
}

$userid = (int) $options['userid'];

global $DB;

// Remove ALL sub rows for this user that look like a mock (endpoint
// pointing at our mock_receiver.php).
$removed = $DB->delete_records_select(
    'local_sentientia_push_subs',
    "userid = :uid AND " . $DB->sql_like('endpoint', ':needle', false),
    [
        'uid'    => $userid,
        'needle' => '%/mock_receiver.php%',
    ]
);

// Remove the credentials file.
$mock_dir = $CFG->dataroot . '/sentientia_pwa_mock';
$creds_file = $mock_dir . '/mock_subscriber.json';
$received_file = $mock_dir . '/last_received.txt';
$creds_removed = file_exists($creds_file) ? unlink($creds_file) : false;
$received_removed = file_exists($received_file) ? unlink($received_file) : false;

cli_writeln('Teardown complete:');
cli_writeln('  push_subs rows removed:          ' . ($removed ? 'yes' : 'none'));
cli_writeln('  mock_subscriber.json removed:    ' . ($creds_removed ? 'yes' : 'no'));
cli_writeln('  last_received.txt removed:       ' . ($received_removed ? 'yes' : 'no'));

exit(0);
