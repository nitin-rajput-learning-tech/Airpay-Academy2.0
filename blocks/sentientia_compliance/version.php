<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'block_sentientia_compliance';
$plugin->version   = 2026092500;  // ADR-031: matrix + audit export tenant-scoped (classes/audit.php; no schema/cap change)
// 2026080400:  // 2026-08-04 privacy null-provider (GDPR registry closure)
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.1-beta';
// tenant::is_cross_tenant() / scope_path() arrived in platform 2026092500 (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => 2026092500,
];
