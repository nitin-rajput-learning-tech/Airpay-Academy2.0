<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'The Sentientia Core plugin stores no personal data. It provides a tenant-identity abstraction over existing user fields and a tenant registry (customer + tenant configuration: names, root ids, status), none of which is personal user data.';

// Tenant identity settings.
$string['settings_tenant_identity'] = 'Tenant identity';
$string['setting_legacy_openpath'] = 'Resolve tenant from BizLMS open_path (legacy)';
$string['setting_legacy_openpath_desc'] = 'When enabled (the default), Sentientia resolves a user\'s tenant from the legacy BizLMS <code>open_path</code> profile field — identical to current production behaviour. Turning this OFF is reserved for ADR-018 Wave 3+ once the Sentientia tenant registry exists; until then the service safely falls back to <code>open_path</code> anyway. Leave ON in production.';

// Org-hierarchy settings (ADR-020 Wave 3.1 seam).
$string['settings_org'] = 'Org hierarchy';
$string['setting_org_legacy'] = 'Resolve manager / org from BizLMS (legacy)';
$string['setting_org_legacy_desc'] = 'When enabled (the default), Sentientia resolves a user\'s manager from the legacy BizLMS <code>open_supervisorid</code> field — identical to current production behaviour. Turning this OFF is reserved for ADR-020 Wave 3.2+ once the Sentientia org model exists; until then the service safely falls back to <code>open_supervisorid</code> anyway. Leave ON in production.';
$string['setting_org_dualwrite'] = 'Mirror the legacy org graph into the Sentientia org model (dual-write)';
$string['setting_org_dualwrite_desc'] = 'When enabled, a scheduled task periodically mirrors the legacy BizLMS org graph (<code>open_path</code> cost-center tree + <code>open_supervisorid</code> manager links) into the Sentientia org model tables. The legacy graph stays the source of truth — this only keeps the new tables warm ahead of an eventual cutover. Default OFF (the task no-ops): turn ON only to populate the model for a parity check or rehearsal, then run <code>cli/parity_check_org.php</code> before considering a flip. Does NOT change manager resolution — that is still governed by the “Resolve manager / org from BizLMS (legacy)” flag above.';

// Scheduled task (ADR-020 Wave 3.2b).
$string['task_reconcile_org'] = 'Reconcile the Sentientia org model from the legacy graph';

// Tenant-path access (ADR-018 Wave 2 — tenant_identity::require_path_access).
$string['error_outoftenant'] = 'You do not have access to this resource — it belongs to a different tenant.';

// ── Tenant registry (ADR-021 Wave 4) ──────────────────────────────────────
// Capability.
$string['sentientia_core:managetenants'] = 'Manage the Sentientia tenant registry';

// Registry legacy flag (settings).
$string['settings_tenant_registry'] = 'Tenant registry';
$string['setting_legacy_registry'] = 'Validate tenants from the hardcoded allow-list (legacy)';
$string['setting_legacy_registry_desc'] = 'When enabled (the default), Sentientia validates tenant roots against the legacy hardcoded allow-list (<code>[1, 77, 177]</code>) — identical to current production behaviour. Turning this OFF reads the Sentientia tenant registry (the <em>Manage tenant registry</em> page below) instead. Only flip OFF after seeding the registry and confirming 100% parity with <code>cli/parity_check_tenants.php</code> (rehearse on a clone DB first). Leave ON in production until cutover.';

// assert_valid() failure (mirrors local_airpay_core\tenant::assert_valid).
$string['error_invalidtenant'] = 'Unknown tenant — this id is not in the tenant registry.';

// Manage UI.
$string['managetenants'] = 'Manage tenant registry';
$string['registry_flag_legacy_on'] = 'The tenant registry is DORMANT — tenant validation currently uses the legacy hardcoded allow-list. Rows managed here take effect only after the "Validate tenants from the hardcoded allow-list (legacy)" setting is turned OFF.';
$string['registry_flag_legacy_off'] = 'The tenant registry is LIVE — tenant validation now reads these rows. Removing or suspending a tenant here immediately affects who can access tenant-scoped resources.';
$string['customers'] = 'Customers';
$string['tenants'] = 'Tenants';
$string['tenantcount'] = 'Tenants';
$string['customer_missing'] = '(unknown customer)';
$string['nocustomers'] = 'No customers yet. Add one below, or run cli/seed_tenants.php.';
$string['notenants'] = 'No tenants registered yet. Add one below, or run cli/seed_tenants.php.';
$string['actions'] = 'Actions';
$string['suspend'] = 'Suspend';
$string['activate'] = 'Activate';
$string['tenant_statuschanged'] = 'Tenant status updated.';
$string['customer_saved'] = 'Customer saved.';
$string['tenant_saved'] = 'Tenant saved.';
$string['addcustomer'] = 'Add customer';
$string['addtenant'] = 'Add tenant';
$string['addcustomer_first'] = 'Add a customer before registering a tenant — every tenant must belong to one.';

// Status values.
$string['status_active'] = 'Active';
$string['status_suspended'] = 'Suspended';
$string['status_archived'] = 'Archived';

// Form fields.
$string['field_customername'] = 'Customer name';
$string['field_shortname'] = 'Short name';
$string['field_shortname_help'] = 'A short, unique, machine-friendly handle for the customer (letters, numbers, _ and -). Example: <code>airpay</code>. Used internally; not shown to learners.';
$string['field_status'] = 'Status';
$string['field_rootid'] = 'Tenant root id';
$string['field_rootid_help'] = 'The tenant root id. Today this is the BizLMS cost-center root (1 = Airpay, 77 = Public, 177 = ZEEA). Must be a positive integer and unique across the registry.';
$string['field_customer'] = 'Owning customer';
$string['field_tenantname'] = 'Tenant name';
$string['field_idnumber'] = 'External id (optional)';
$string['field_idnumber_help'] = 'An optional external key (for example an HRMS identifier) used to round-trip this tenant with an upstream sync. Leave blank if not applicable.';

// Validation errors.
$string['err_shortname_taken'] = 'That short name is already used by another customer.';
$string['err_rootid_positive'] = 'The tenant root id must be a positive integer.';
$string['err_rootid_taken'] = 'That tenant root id is already registered.';
