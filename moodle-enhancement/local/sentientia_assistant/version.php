<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_assistant';
// P1 #50 (2026-05-20) — Hindi top-up: 3 strings (enabled + privacy).
// Stabilization Audit D5 / F-061 (2026-05-28) — scope is ai_demo.php only.
// No production wiring; the `core_ai_bridge` class is a placeholder that
// has never POSTed to a live AI provider (F-017/F-018 finding). Stamped
// ALPHA until either (a) the assistant becomes a first-class chat surface,
// or (b) it's archived/removed.
$plugin->version   = 2026061600; // P1.3 Agentic Copilot — agent loop + tool registry + audit.
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.2.0-alpha';
// Release history
// 1.0.0-beta  initial release — chat bubble template + WS + ai_client
// 1.1.0-beta  Phase B0 polish:
//               + amd/src/chat.js + amd/build/chat.min.js (was missing!)
//               + Cmd+K / Esc keyboard shortcuts (manifesto §4.1)
//               + DOMParser-based sanitiser for bot responses
//                 (defense-in-depth on top of format_text + HTMLPurifier)
//               + feature_flags gate in hook_callbacks (Switchboard wired)
//               + a11y attrs on chat_bubble.mustache (role=dialog, aria-live)
//               + styles.css tokens migration (43 hex literals → 0)
//               + 6 a11y lang strings
// 1.2.0-alpha P1.3 Agentic Copilot (Gap analysis §6 P1.3 — 2026-06-16):
//               + db/access.php — 5 capabilities (useagent, enrol,
//                 bookilt, recommend, manageall)
//               + db/feature_flags.php — sentientia.assistant.agentic.enabled
//                 + .live_api (BOTH default OFF; nav Q&A unchanged when OFF)
//               + db/install.xml + upgrade.php — local_sentientia_agent_audit
//               + classes/agent/* — tool registry, base tool, 3 guarded tools,
//                 agent loop, agent_client (mock-mode default), context_builder
//               + classes/agent/integration/* — WhatsApp + Teams embedding
//                 hooks (class_exists-guarded reuse of whatsapp + m365)
//               + external_agent WS + agent.php surface + templates
//               + en + hi lang parity, PHPUnit suite
