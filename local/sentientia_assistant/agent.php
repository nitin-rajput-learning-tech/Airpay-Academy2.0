<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Agentic Copilot surface (P1.3).
 *
 * A full-page chat surface for the RAG + tool-use copilot. Gated behind:
 *   - require_login + the :useagent capability
 *   - the sentientia.assistant.agentic.enabled feature flag (default OFF).
 *
 * When the flag is OFF, this page returns a "not available" notice — the
 * legacy nav-assistant chat bubble is entirely unaffected. The actual
 * conversation runs over the agent_turn / agent_confirm web services.
 *
 * @package local_sentientia_assistant
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/sentientia_assistant:useagent', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_assistant/agent.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('agent_title', 'local_sentientia_assistant'));
$PAGE->set_heading(get_string('agent_title', 'local_sentientia_assistant'));

$enabled = class_exists('\\local_sentientia_platform\\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled('sentientia.assistant.agentic.enabled');

echo $OUTPUT->header();

if (!$enabled) {
    // Flag OFF — additive feature is dark. Nothing of the legacy surface
    // is touched; we just tell the user this page isn't available.
    echo $OUTPUT->notification(
        get_string('agent_disabled_notice', 'local_sentientia_assistant'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

$live = \local_sentientia_assistant\agent\agent_client::is_live_ready();

echo $OUTPUT->render_from_template('local_sentientia_assistant/agent_panel', [
    'sesskey'   => sesskey(),
    'livemode'  => $live,
    'modelabel' => $live
        ? get_string('agent_mode_live', 'local_sentientia_assistant')
        : get_string('agent_mode_mock', 'local_sentientia_assistant'),
]);

echo $OUTPUT->footer();
