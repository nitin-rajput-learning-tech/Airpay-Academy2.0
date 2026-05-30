<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'The Sentientia Core plugin stores no personal data; it provides a tenant-identity abstraction layer over existing user fields.';

// Tenant identity settings.
$string['settings_tenant_identity'] = 'Tenant identity';
$string['setting_legacy_openpath'] = 'Resolve tenant from BizLMS open_path (legacy)';
$string['setting_legacy_openpath_desc'] = 'When enabled (the default), Sentientia resolves a user\'s tenant from the legacy BizLMS <code>open_path</code> profile field — identical to current production behaviour. Turning this OFF is reserved for ADR-018 Wave 3+ once the Sentientia tenant registry exists; until then the service safely falls back to <code>open_path</code> anyway. Leave ON in production.';

// Org-hierarchy settings (ADR-020 Wave 3.1 seam).
$string['settings_org'] = 'Org hierarchy';
$string['setting_org_legacy'] = 'Resolve manager / org from BizLMS (legacy)';
$string['setting_org_legacy_desc'] = 'When enabled (the default), Sentientia resolves a user\'s manager from the legacy BizLMS <code>open_supervisorid</code> field — identical to current production behaviour. Turning this OFF is reserved for ADR-020 Wave 3.2+ once the Sentientia org model exists; until then the service safely falls back to <code>open_supervisorid</code> anyway. Leave ON in production.';

// Tenant-path access (ADR-018 Wave 2 — tenant_identity::require_path_access).
$string['error_outoftenant'] = 'You do not have access to this resource — it belongs to a different tenant.';
