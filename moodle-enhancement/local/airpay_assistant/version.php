<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_assistant';
// P1 #50 (2026-05-20) — Hindi top-up: 3 strings (enabled + privacy).
// Stabilization Audit D5 / F-061 (2026-05-28) — scope is ai_demo.php only.
// No production wiring; the `core_ai_bridge` class is a placeholder that
// has never POSTed to a live AI provider (F-017/F-018 finding). Stamped
// ALPHA until either (a) the assistant becomes a first-class chat surface,
// or (b) it's archived/removed.
$plugin->version   = 2026060100;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '1.1.3-alpha';
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
