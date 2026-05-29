<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_airpay_pages.
 *
 * C10 P1 / Gap 3 (2026-05-29) — tenant-scoped certificate template
 * browser. Per CLAUDE.md §13 every new user-visible feature ships
 * behind a default-OFF flag whose OFF state matches today's
 * production behaviour (admins see ALL templates).
 *
 * @package local_airpay_pages
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.certificate.tenant_scope.enabled' => [
        'default'     => false,
        'description' => 'Tenant-scoped certificate template browser
                          (C10 / Gap 3). When OFF (default), the browser
                          at /local/airpay_pages/certificate_templates.php
                          shows ALL tool_certificate templates to every
                          admin — exactly matching today\'s production
                          behaviour. When ON, non-siteadmin tenant admins
                          see only GLOBAL templates plus templates mapped
                          to their own tenant via the
                          local_airpay_pages | cert_template_tenant_map
                          admin setting. Site admins always see everything,
                          with the per-template tenant assignment shown.
                          Reads tool_certificate tables read-only; never
                          mutates the vendored plugin.',
    ],

];
