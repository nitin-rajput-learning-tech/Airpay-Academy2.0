<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Operator / QA helper — flip the Sentientia Live engagement feature flags
 * on or off in one command, without clicking through the Switchboard UI.
 *
 * Sentientia Live ships every flag default-OFF (per CLAUDE.md rule:
 * "NEVER ship a feature without a feature flag (default OFF)"). For a
 * demo, a QA pass, or the two-browser SSE acceptance test, an operator
 * needs the whole set ON at once. This wraps
 * {@see \local_airpay_core\feature_flags::set()} so the change is audited
 * and caches are invalidated — exactly as the Switchboard does it.
 *
 * Scope is global (tenant_id=0, customer_id=0) — the "all customers /
 * all tenants" override. To scope per-tenant or per-customer, use the
 * Switchboard UI instead.
 *
 * Usage:
 *   php local/sentientia_live/cli/set_live_flags.php --on
 *   php local/sentientia_live/cli/set_live_flags.php --off
 *   php local/sentientia_live/cli/set_live_flags.php --status
 *   php local/sentientia_live/cli/set_live_flags.php --on --no-anonymous
 *
 * @package local_sentientia_live
 * @copyright 2026 Airpay Payment Services
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'         => false,
    'on'           => false,
    'off'          => false,
    'status'       => false,
    'no-anonymous' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// The full Live engagement flag set. Order = master, transport, the six
// question-type ship gates, then the audience-access mode.
$allflags = [
    'live.enabled',
    'live.realtime.enabled',
    'live.questiontype.multichoice',
    'live.questiontype.wordcloud',
    'live.questiontype.openended',
    'live.questiontype.rating',
    'live.questiontype.quiz',
    'live.questiontype.ranking',
    'live.allow_anonymous',
];

if ($options['help'] || (!$options['on'] && !$options['off'] && !$options['status'])) {
    echo <<<EOT

Flip the Sentientia Live engagement feature flags on/off (global scope).

Usage:
  php local/sentientia_live/cli/set_live_flags.php --on
  php local/sentientia_live/cli/set_live_flags.php --off
  php local/sentientia_live/cli/set_live_flags.php --status

Options:
  --on            Enable the master flag + realtime + all 6 question types
                  (+ allow_anonymous, unless --no-anonymous is given).
  --off           Revert every Live flag to its registry default (removes
                  the global override rows).
  --status        Print the current resolved state of every Live flag.
  --no-anonymous  With --on, leave live.allow_anonymous at its default
                  (audience must authenticate). Use for compliance demos.
  -h, --help      Print this help.

Scope is global (all tenants / all customers). For per-tenant or
per-customer overrides, use the Switchboard admin UI instead.

EOT;
    exit(0);
}

// Elevate to admin so the audit row records a real user and any
// customer-layer guard checks resolve against an admin context.
$admin = get_admin();
if ($admin) {
    \core\session\manager::set_user($admin);
}
$byuserid = $admin ? (int) $admin->id : 0;

if ($options['status']) {
    cli_heading('Sentientia Live — feature flag status (global resolution)');
    foreach ($allflags as $flag) {
        $state = \local_airpay_core\feature_flags::is_enabled($flag) ? 'ON ' : 'off';
        cli_writeln(sprintf('  [%s]  %s', $state, $flag));
    }
    exit(0);
}

// --on / --off
$enable = (bool) $options['on'];
$reason = $enable
    ? 'CLI set_live_flags --on (QA / two-browser acceptance test)'
    : 'CLI set_live_flags --off (revert to registry defaults)';

cli_heading($enable
    ? 'Enabling Sentientia Live engagement flags (global)'
    : 'Reverting Sentientia Live engagement flags to defaults (global)');

foreach ($allflags as $flag) {
    // --no-anonymous keeps the anonymous-join gate at its (OFF) default.
    if ($enable && $flag === 'live.allow_anonymous' && $options['no-anonymous']) {
        cli_writeln(sprintf('  skip   %s (--no-anonymous)', $flag));
        continue;
    }

    // --on writes a true override; --off writes null to delete the
    // override row and fall back to the registry default.
    $value = $enable ? true : null;

    \local_airpay_core\feature_flags::set(
        $flag,      // key
        0,          // tenant_id — 0 = customer-wide
        $value,     // true | null(revert)
        $byuserid,  // changed_by
        $reason,    // audit note
        0           // customer_id — 0 = global
    );

    $now = \local_airpay_core\feature_flags::is_enabled($flag) ? 'ON ' : 'off';
    cli_writeln(sprintf('  [%s]  %s', $now, $flag));
}

cli_writeln('');
cli_writeln('Done. Run with --status to re-check resolved state.');
exit(0);
