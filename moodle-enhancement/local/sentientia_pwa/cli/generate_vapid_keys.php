<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * VAPID keypair generator — Sentientia LMS PWA push notifications.
 *
 * Run ONCE per install. Generates the P-256 ECDSA keypair that browser
 * push subscriptions are cryptographically bound to. The public key is
 * read by the subscribe.js AMD module via the WS endpoint; the private
 * key signs the JWT on each push delivery (Phase B.2.5).
 *
 * Usage:
 *   cd /path/to/moodle/public
 *   php local/sentientia_pwa/cli/generate_vapid_keys.php           # idempotent — refuses if exists
 *   php local/sentientia_pwa/cli/generate_vapid_keys.php -r        # force regenerate (invalidates subs)
 *   php local/sentientia_pwa/cli/generate_vapid_keys.php --info    # print current keypair info
 *
 * @package local_sentientia_pwa
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help'       => false,
        'regenerate' => false,
        'info'       => false,
    ],
    [
        'h' => 'help',
        'r' => 'regenerate',
        'i' => 'info',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognized));
}

if ($options['help']) {
    echo <<<EOT

Generate VAPID keypair for Sentientia LMS PWA push notifications.

Usage:
  php local/sentientia_pwa/cli/generate_vapid_keys.php             Generate (refuses if exists)
  php local/sentientia_pwa/cli/generate_vapid_keys.php -r          Force regenerate (invalidates subs)
  php local/sentientia_pwa/cli/generate_vapid_keys.php --info      Print current keypair info

Options:
  -h, --help        Show this help message
  -r, --regenerate  Force overwrite. CAUTION: every existing push subscription
                    becomes invalid because the browser-side subscription is
                    bound to the OLD public key. Users must re-subscribe.
  -i, --info        Just print the current keypair status without changing anything.


EOT;
    exit(0);
}

$manager_class = '\\local_sentientia_pwa\\vapid_key_manager';

// ── --info mode: just report current state ──
if ($options['info']) {
    if (!$manager_class::exists()) {
        cli_writeln('No VAPID keypair stored. Run without --info to generate one.');
        exit(1);
    }
    cli_writeln('VAPID keypair status:');
    cli_writeln('  Public key:    ' . $manager_class::get_public_key());
    cli_writeln('  Subject:       ' . $manager_class::get_subject());
    $generated_at = $manager_class::get_generated_at();
    if ($generated_at) {
        cli_writeln('  Generated at:  ' . userdate($generated_at, '%Y-%m-%d %H:%M:%S'));
    }
    global $DB;
    $subs = $DB->count_records('local_sentientia_push_subs');
    cli_writeln('  Active subs:   ' . $subs);
    exit(0);
}

// ── Already exists, no --regenerate: refuse ──
if ($manager_class::exists() && !$options['regenerate']) {
    cli_writeln('A VAPID keypair already exists. Use --info to view, or -r to force regenerate.');
    cli_writeln('Public key: ' . $manager_class::get_public_key());
    exit(0);
}

// ── --regenerate confirmation ──
if ($options['regenerate']) {
    global $DB;
    $sub_count = $DB->count_records('local_sentientia_push_subs');
    if ($sub_count > 0) {
        cli_writeln('');
        cli_writeln('!! WARNING: ' . $sub_count . ' active push subscription(s) will be ');
        cli_writeln('!! DELETED. Users must re-enable browser notifications after this.');
        cli_writeln('!! Type "yes" and press Enter to proceed, anything else to cancel:');
        $line = trim(fgets(STDIN));
        if (strtolower($line) !== 'yes') {
            cli_writeln('Cancelled.');
            exit(1);
        }
    }
}

// ── Generate ──
cli_writeln('Generating P-256 (prime256v1) VAPID keypair...');
try {
    if ($options['regenerate']) {
        $result = $manager_class::regenerate();
    } else {
        $result = $manager_class::generate_and_save();
    }
} catch (\Throwable $e) {
    cli_writeln('');
    cli_writeln('!! ERROR: ' . $e->getMessage());
    if (!extension_loaded('openssl')) {
        cli_writeln('!! The openssl extension is required. Install php-openssl and reload.');
    }
    exit(1);
}

cli_writeln('');
cli_writeln('Success.');
cli_writeln('  Public key (b64url):  ' . $result['public']);
cli_writeln('  Stored in:            mdl_config_plugin (plugin=local_sentientia_pwa)');
cli_writeln('  VAPID subject:        ' . $manager_class::get_subject());
if (isset($result['invalidated'])) {
    cli_writeln('  Invalidated subs:     ' . $result['invalidated']);
}
cli_writeln('');
cli_writeln('Next steps:');
cli_writeln('  1. Verify the subscribe button renders for any user at:');
cli_writeln('     ' . $CFG->wwwroot . '/local/sentientia_pwa/preferences.php');
cli_writeln('  2. Click "Enable browser notifications" — Chrome should prompt for permission.');
cli_writeln('  3. After Phase B.2.5 ships real delivery, run cli/test_push.php to send a test.');
cli_writeln('');
exit(0);
