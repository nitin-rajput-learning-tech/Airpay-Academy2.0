<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI smoke test for the AI Quiz generation pipeline — LIVE mode.
 *
 * C8 stabilization (2026-05-28). Closes F-017 — the AI Quiz pipeline
 * has been mock-mode-complete for weeks; this is the first call that
 * actually POSTs to api.anthropic.com.
 *
 * MUST be run with explicit human authorisation per CLAUDE.md §3
 * absolute rule: "NEVER POST to Anthropic without [CONFIRM]". The
 * `--confirm-live-anthropic-call` flag must be passed explicitly;
 * --commit alone is not enough.
 *
 * Cost estimate per call (claude-3-5-sonnet, March 2026 pricing):
 *   - Input:  ~3,000 tokens × $0.003/1K = $0.009
 *   - Output: ~2,000 tokens × $0.015/1K = $0.030
 *   - Total:  approximately $0.04 USD per call
 *
 * Prerequisites checked BEFORE the call:
 *   1. ANTHROPIC_API_KEY env var OR plugin config api_key set
 *   2. Feature flag `sentientia.aiquiz.live` = enabled
 *   3. `--confirm-live-anthropic-call` flag present on command line
 *
 * All three required — any one missing aborts with a clear error.
 *
 * Usage:
 *   php local/sentientia_aiquiz/cli/live_smoke.php --confirm-live-anthropic-call
 *
 * @package    local_sentientia_aiquiz
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'confirm-live-anthropic-call' => false,
    'numquestions' => 5,
    'help' => false,
], ['h' => 'help']);

if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__, false, null, 0, 2000));
    exit(0);
}

// ── Prerequisite 1: explicit human authorisation flag ───────────────
if (!$options['confirm-live-anthropic-call']) {
    cli_writeln('');
    cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    cli_writeln('  [CONFIRM REQUIRED] This script POSTs to api.anthropic.com.');
    cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    cli_writeln('  Estimated cost: ~$0.04 USD per call (claude-3-5-sonnet,');
    cli_writeln('  3k input + 2k output tokens).');
    cli_writeln('');
    cli_writeln('  CLAUDE.md §3 absolute rule: "NEVER POST to Anthropic');
    cli_writeln('  without [CONFIRM]". Re-run with the explicit flag:');
    cli_writeln('');
    cli_writeln('    php local/sentientia_aiquiz/cli/live_smoke.php \\');
    cli_writeln('         --confirm-live-anthropic-call');
    cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    exit(1);
}

// ── Prerequisite 2: API key configured ──────────────────────────────
$apikey = get_config('local_sentientia_aiquiz', 'api_key');
if (empty($apikey)) {
    $apikey = getenv('ANTHROPIC_API_KEY') ?: '';
}
if (empty($apikey)) {
    cli_writeln('✗ ANTHROPIC API KEY not configured.');
    cli_writeln('  Set either:');
    cli_writeln('    (a) Plugin config: Site Admin → Plugins → Local plugins → AI Quiz');
    cli_writeln('    (b) Env var: ANTHROPIC_API_KEY="sk-ant-..."');
    exit(2);
}

// ── Prerequisite 3: feature flag enabled ────────────────────────────
$flagon = false;
if (class_exists('\\local_airpay_core\\feature_flags')) {
    $flagon = \local_airpay_core\feature_flags::is_enabled(
        'sentientia.aiquiz.live');
}
if (!$flagon) {
    cli_writeln('✗ Feature flag `sentientia.aiquiz.live` is OFF.');
    cli_writeln('  Enable it via Site Admin → Plugins → Local plugins → Switchboard');
    cli_writeln('  OR via CLI flag-flip (see local_airpay_core/admin/switchboard.php).');
    exit(2);
}

// ── All prerequisites met. Make the call. ───────────────────────────
cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
cli_writeln('  LIVE call to api.anthropic.com starting...');
cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
cli_writeln('  API key:        ' . substr($apikey, 0, 12) . '...');
cli_writeln('  Model:          claude-3-5-sonnet-20241022');
cli_writeln('  Num questions:  ' . (int) $options['numquestions']);
cli_writeln('');

// Use a short, deterministic source so cost stays bounded.
$sourcetext = "Airpay Academy is the learning platform for Airpay Payment Services. "
            . "It supports three tenants: AIRPAY (internal staff), Public (external "
            . "learners), and ZEEA (Zanzibar subsidiary). The platform offers courses "
            . "in payments, compliance, and technical skills. Learners complete courses, "
            . "earn certificates, and track skill progression. Managers can see team "
            . "compliance status. The platform is built on Moodle 5.1 with custom "
            . "plugins under the local_airpay_* and local_sentientia_* namespaces.";

$start = microtime(true);

try {
    $result = \local_sentientia_aiquiz\anthropic_client::call_live(
        $sourcetext,
        (int) $options['numquestions'],
        'claude-3-5-sonnet-20241022',
        null);
} catch (\Throwable $e) {
    cli_writeln('✗ Call failed: ' . $e->getMessage());
    exit(3);
}

$elapsed = microtime(true) - $start;

cli_writeln('✓ Call succeeded in ' . round($elapsed, 2) . 's.');
cli_writeln('');
cli_writeln('Returned ' . count($result) . ' parsed questions:');
foreach ($result as $i => $q) {
    if ($i >= 3) {
        cli_writeln('  ...(' . (count($result) - 3) . ' more)');
        break;
    }
    cli_writeln('  ' . ($i + 1) . '. ' . substr($q['question'] ?? '?', 0, 80));
}

cli_writeln('');
cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
cli_writeln('  First live AI call complete. F-017 closed (was: never live-called).');
cli_writeln('  Plugin can be re-stamped MATURITY_STABLE in the next chip.');
cli_writeln('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
