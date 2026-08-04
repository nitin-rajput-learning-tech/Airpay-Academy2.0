<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_pages';
// C10 P1 / Gap 3 (2026-05-29) — tenant-scoped certificate template
// browser: new db/feature_flags.php + settings.php +
// certificate_templates.php + 19 lang strings. Reads tool_certificate
// READ-ONLY; gated behind sentientia.certificate.tenant_scope.enabled
// (default OFF = today's behaviour).
$plugin->version   = 2026080400;  // 2026-08-04 privacy null-provider (GDPR registry closure)
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1';
