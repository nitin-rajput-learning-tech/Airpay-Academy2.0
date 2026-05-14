<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_assistant';
$plugin->version   = 2026051401;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.1.0-beta';
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
