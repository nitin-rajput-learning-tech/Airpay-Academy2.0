<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Review and repair the role grants a BizLMS database carries on capabilities of plugins that are no
 * longer on disk (ADR-032, "Capabilities"). The replacement of the retired migrate_all.php capability copy.
 *
 * The copy replayed every BizLMS grant on its Sentientia equivalent. On a restored production database that
 * would give the tenant-admin role organisation delete, edit and cross-tenant visibility that ADR-031 reserves
 * for site administrators, and it would still miss the classroom grants that existed only as overrides. This
 * script lists the grants first and makes only the ones the owner approved, one line each.
 *
 *   php local/sentientia_platform/cli/repair_bizlms_capabilities.php
 *       inventory: every grant on a capability of a missing plugin, per role and context. Writes nothing.
 *   php local/sentientia_platform/cli/repair_bizlms_capabilities.php --allowlist=FILE
 *       checks the owner's allow-list against the inventory and says what --apply would do. Writes nothing.
 *   php local/sentientia_platform/cli/repair_bizlms_capabilities.php --allowlist=FILE --apply --confirm=<fingerprint>
 *       makes the approved grants. Never overwrites a grant that exists and never revokes.
 *
 * The allow-list is JSON: {"version": 1, "approved_by": "...", "approved_on": "YYYY-MM-DD", "basis": "...",
 * "grants": [{"role": "trainer", "context": "system", "legacy": "local/classroom:manageclassroom",
 * "target": "local/sentientia_classroom:manage", "permission": 1}],
 * "declined": [{"role": "manager", "context": "system", "legacy": "local/costcenter:manage_ownorganization",
 * "reason": "..."}, {"legacy_component": "local_forum", "reason": "..."}]}. role is a shortname, context is
 * "system" or a context id, permission is 1, -1 or -1000. A grant is refused unless the role really holds that
 * legacy grant, the target is the equivalent of the legacy capability and the target is installed.
 *
 * "declined" records what the owner reviewed and does not carry, each with a reason. A line names one role
 * grant, or a whole missing plugin (legacy_component). A plugin decline covers only the grants on capabilities
 * that have no Sentientia equivalent; the ten that do (capability_repair::MAP) are decided per role and
 * context, because that is where a real override hides. A line that is both granted and declined is refused.
 * Declined grants count as decided. The file is signed as a whole (approved_by, approved_on).
 *
 * "Already held" means the role holds the equivalent with the SAME permission. A role that holds it with a
 * different one (a BizLMS PROHIBIT at system context against an ALLOW the Sentientia manager archetype gave the
 * equivalent) is DIVERGENT: the script never overwrites a row, so it cannot carry the restriction, and the grant stays
 * undecided (exit 2) until a decline names that role grant. An approved grant against such a target is refused
 * (exit 1, target_held_with_a_different_permission).
 *
 * It never grants local/sentientia_org:manage, local/sentientia_org:manage_multiorganizations or
 * local/sentientia_platform:crosstenant, whatever the allow-list says. The cross-tenant capability goes to the
 * platform role Nitin names, by hand, in the cutover runbook.
 *
 * Options
 *   --allowlist=FILE       the owner's approved grants
 *   --apply                make them; needs --allowlist and every guard below
 *   --confirm=<fingerprint> required with --apply (php import_bizlms.php --status prints it)
 *   --allow-online         skip the maintenance requirement (rehearsal only; refused when bizlms_production = 1)
 *
 * Exit codes: 0 nothing left to decide; 1 an allow-list line was refused; 3 a guard refused;
 * 2 done, but grants remain that nobody approved or declined. 0, 1 and 2 match import_bizlms.php.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\capability_repair;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\guard_refused;

[$options, $unrecognised] = cli_get_params([
    'allowlist' => '', 'apply' => false, 'confirm' => '', 'allow-online' => false, 'help' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)), 1);
}
if ($options['help']) {
    $usage = file_get_contents(__FILE__);
    preg_match('/\/\*\*(.*?)\*\//s', (string) $usage, $m);
    cli_writeln(trim(preg_replace('/^\s*\* ?/m', '', $m[1] ?? 'See the header of this file.')));
    exit(0);
}

try {
    $repair = new capability_repair();
    $inventory = $repair->inventory();
    cli_writeln(count($inventory) . ' role grant(s) on capabilities of plugins that are missing from disk');
    foreach ($inventory as $row) {
        $state = $row['target'] === null ? 'no known equivalent'
            : ($row['divergent'] ? 'equivalent held as ' . capability_repair::permission_name((int) $row['target_permission'])
                . ', DIFFERENT from the legacy grant'
            : ($row['withheld'] ? 'equivalent is never granted by this script'
            : ($row['held'] ? 'equivalent already held' : 'equivalent not held')));
        cli_writeln(sprintf('  %-22s ctx %-6s level %-3s %-48s perm %-5s -> %s (%s)', $row['role'], $row['contextid'],
            $row['contextlevel'], $row['legacy'], $row['permission'], $row['target'] ?? '-', $state));
    }

    $grants = [];
    $declines = [];
    $allowlist = null;
    if ($options['allowlist'] !== '') {
        $allowlist = capability_repair::load_allowlist((string) $options['allowlist']);
        $grants = $allowlist['grants'];
        $declines = $allowlist['declines'];
        cli_writeln(sprintf('Allow-list %s approved by %s on %s (sha256 %s), %d grant(s), %d decline(s)',
            basename((string) $options['allowlist']), $allowlist['approved_by'], $allowlist['approved_on'],
            $allowlist['hash'], count($grants), count($declines)));
    }
    $plan = $repair->plan($grants, $inventory, $declines);

    foreach ($plan['refused'] as $line) {
        cli_writeln('REFUSED: ' . $line);
    }
    foreach ($plan['apply'] as $entry) {
        cli_writeln(sprintf('  WOULD GRANT %s to %s in context %d (permission %d), from %s', $entry['target'], $entry['role'],
            $entry['contextid'], $entry['permission'], $entry['legacy']));
    }
    foreach ($plan['held'] as $entry) {
        cli_writeln(sprintf('  already held: %s to %s in context %d', $entry['target'], $entry['role'], $entry['contextid']));
    }
    foreach ($plan['withheld'] as $line) {
        cli_writeln('  withheld (ADR-031, never granted here): ' . $line);
    }
    foreach ($plan['declined_by'] as $label => $declined) {
        cli_writeln(sprintf('  declined, %d grant(s): %s -- %s', $declined['rows'], $label, $declined['reason']));
    }
    foreach ($plan['unused_declines'] as $line) {
        cli_writeln('  note (not an error): ' . $line);
    }
    foreach ($plan['uncovered'] as $line) {
        cli_writeln('  UNAPPROVED: ' . $line);
    }
    foreach ($plan['divergent'] as $line) {
        cli_writeln('  DIVERGENT, NOT DECLINED (the repair never overwrites; decline this role grant by name): ' . $line);
    }
    foreach ($plan['unmapped'] as $line) {
        cli_writeln('  NO EQUIVALENT, NOT DECLINED: ' . $line);
    }

    if ($options['apply']) {
        if ($allowlist === null) {
            cli_error('--apply needs --allowlist=FILE.', 3);
        }
        if ($plan['refused']) {
            cli_writeln('RESULT: nothing was granted because allow-list lines were refused (exit 1)');
            exit(1);
        }
        $refusals = guard::refusals_for_repair(['confirm' => $options['confirm'], 'allow_online' => $options['allow-online']]);
        if ($refusals) {
            foreach ($refusals as $line) {
                cli_writeln('REFUSED: ' . $line);
            }
            exit(3);
        }
        $lock = guard::acquire_lock();
        try {
            $made = $repair->apply($plan['apply']);
        } finally {
            // A lock kept by a failed apply() would block the next run (the db_record factory holds it for hours).
            $lock->release();
        }
        cli_writeln("Granted {$made} capability grant(s).");
    }

    $exitcode = capability_repair::exit_code($plan);
    if ($exitcode === 1) {
        cli_writeln('RESULT: allow-list lines refused (exit 1)');
    } else if ($exitcode === 2) {
        cli_writeln('RESULT: ' . capability_repair::open_count($plan) . ' grant(s) still have no decision (exit 2)');
    } else {
        cli_writeln('RESULT: every grant is decided (exit 0)');
    }
    exit($exitcode);
} catch (guard_refused $e) {
    cli_writeln('REFUSED: ' . $e->getMessage());
    exit(3);
} catch (bizlms_exception $e) {
    cli_writeln('FAILED: ' . $e->getMessage());
    exit($e->exitcode());
}
