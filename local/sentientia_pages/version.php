<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_pages';
// C10 P1 / Gap 3 (2026-05-29) — tenant-scoped certificate template
// browser: new db/feature_flags.php + settings.php +
// certificate_templates.php + 19 lang strings. Reads tool_certificate
// READ-ONLY; gated behind sentientia.certificate.tenant_scope.enabled
// (default OFF = today's behaviour).
$plugin->version   = 2026111801;  // certificate templates page: plain-language banner, ID and empty-template badge
// 2026111801 also carries Phase C addendum (2026-11-19): V8-D onboarding wizard strings (onboard_*, en + hi; was hard-coded English); LINKEDIN-ORG: the certificate gallery's LinkedIn share takes the organisation name from the customer brand (was hard-coded).
// 2026111800: Surface walk 2026-11-18: certificate gallery "Download" opens the issued PDF (tool_certificate view_url), separate "Verify" link, page strings in en+hi with a singular count
// 2026092400: N5: qr_attendance + index refusals render real core messages (were raw "error/nopermission", "error/invalidpage")
// 2026092200: public homepage stats are /-bounded
// 2026080400:  // 2026-08-04 privacy null-provider (GDPR registry closure)
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.2';  // 1.2.2: certificate templates page: plain-language banner, ID and empty-template badge
