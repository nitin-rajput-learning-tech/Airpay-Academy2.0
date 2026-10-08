<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_skills';
// P1 #22 (2026-05-16) — append-only audit log of skill-level changes
//                       (local_sentientia_user_skill_hist). Closes audit
//                       item #23 from parity-audit-2026-05-15/sentientia_skills.md.
// P1 #25 (2026-05-20) — learner self-rate workflow (new capability
//                       :self_rate, new WS local_sentientia_skills_self_rate_skill,
//                       new skills_manager::self_rate_skill()). Closes
//                       audit item #26 from the same audit doc.
// P1 #26 (2026-05-20) — learner self-rate UI (panel + modal + AMD).
//                       Wires the front-end to the P1 #25 back-end.
// P1 #32 (2026-05-20) — Hindi (hi) lang pack catch-up: 80 strings
//                       translated, covering all P1 #22/#25 additions
//                       plus the previously-missing admin CRUD + privacy
//                       metadata. Was at 19/80; now 80/80.
// 2026100801 - Moodle 5.3 compat FX-20: the learner self-rate dialog is a core/modal_save_cancel dialog (it used window.bootstrap.Modal, which exists in no tree); templates/view.mustache holds an inert source block for it. No schema change.
$plugin->version   = 2026100801;  // Moodle 5.3 compat FX-20 (on top of 2026093001 ADR-032 BizLMS import)
// 2026093001:  // ADR-032 BizLMS import (skills): course_levels + skill_interest tables, idnumber/open_path on categories and skills, names widened to 255, skills importer
// 2026092501:  // ADR-031 follow-up: new :mapcourses cap (manager default) for in-tenant course mapping; catalogue writes cross-tenant only
// 2026092500:  // ADR-031: :manage has no default grant (+ revoke step); learners/backfill tenant-scoped
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.0'; // + ADR-032 BizLMS skills importer (classes/bizlms), learner interests, source labels
$plugin->dependencies = [
    'local_sentientia_platform' => 2026093001,  // ADR-032 bizlms framework (importer contract); ADR-031 tenant::is_cross_tenant() / require_same_tenant_user()
];
