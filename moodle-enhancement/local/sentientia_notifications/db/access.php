<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/sentientia_notifications:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],
    // ADR-031 (2026-09-25): no archetype default. Notification rules carry no
    // tenant - every rule fires for every tenant - so managing them is a
    // cross-tenant function, and every tenant admin holds a manager-archetype
    // role at system context. Writes also require tenant::is_cross_tenant()
    // (rule_manager::require_rule_admin). db/upgrade.php 2026092500 revokes
    // the grants the old default left behind.
    'local/sentientia_notifications:manage' => [
        'riskbitmask'  => RISK_CONFIG | RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
    // Phase 4 B.8 (2026-05-11) — notification log viewer access.
    // ADR-031: kept for tenant admins, but confined to the viewer's tenant
    // (classes/log_access.php).
    'local/sentientia_notifications:viewlogs' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
