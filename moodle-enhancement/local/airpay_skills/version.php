<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_skills';
// P1 #22 (2026-05-16) — append-only audit log of skill-level changes
//                       (local_airpay_user_skill_hist). Closes audit
//                       item #23 from parity-audit-2026-05-15/airpay_skills.md.
// P1 #25 (2026-05-20) — learner self-rate workflow (new capability
//                       :self_rate, new WS local_airpay_skills_self_rate_skill,
//                       new skills_manager::self_rate_skill()). Closes
//                       audit item #26 from the same audit doc.
// P1 #26 (2026-05-20) — learner self-rate UI (panel + modal + AMD).
//                       Wires the front-end to the P1 #25 back-end.
$plugin->version   = 2026052002;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.1'; // + P1 #26 self-rate UI
