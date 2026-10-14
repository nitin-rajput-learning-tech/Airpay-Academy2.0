<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'The Sentientia Core plugin stores org-unit membership rows (user, unit, role, direct manager) in local_sentientia_org_member, and the imported BizLMS admin log in local_sentientia_admin_log (who created, updated or deleted a course and when, with a description that names the person by first name). The tenant registry itself (customer + tenant configuration: names, root ids, status) carries no personal data.';

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

// assert_valid() failure (mirrors local_sentientia_platform\tenant::assert_valid).
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

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:org_member']             = 'A user\'s org-unit membership: unit, role and direct manager';
$string['privacy:metadata:org_member:userid']      = 'The member';
$string['privacy:metadata:org_member:unitid']      = 'The org unit the user belongs to';
$string['privacy:metadata:org_member:role']        = 'The user\'s role inside the unit';
$string['privacy:metadata:org_member:managerid']   = 'The user\'s direct manager';
$string['privacy:metadata:org_member:timecreated'] = 'When the membership was recorded';

// ── ADR-032 legacy_logs: the imported admin log (2026-09-30) ──────────────
$string['sentientia_core:viewadminlog'] = 'View the imported admin log';
$string['adminlog'] = 'Imported admin log';
$string['adminlog_intro'] = 'The BizLMS admin log (course created, updated or deleted) and the bulk course upload errors, imported at cutover. This is read-only history: nothing here can be edited or deleted. Descriptions name the person who acted, by first name, so treat the page as personal data.';
$string['adminlog_flagoff'] = 'This report is switched off. It is turned on by the feature flag sentientia.legacy_logs.report.enabled, which is OFF until the page has been reviewed.';
$string['adminlog_noentries'] = 'No entries match.';
$string['adminlog_notenant'] = 'Your account is not attached to a tenant, so there is nothing to show.';
$string['adminlog_total'] = '{$a} entries';
$string['adminlog_col_when'] = 'When';
$string['adminlog_col_who'] = 'Who';
$string['adminlog_col_source'] = 'Source';
$string['adminlog_col_event'] = 'Event';
$string['adminlog_col_module'] = 'Module';
$string['adminlog_col_item'] = 'Item';
$string['adminlog_col_description'] = 'Description';
$string['adminlog_source_local_logs'] = 'Course administration log';
$string['adminlog_source_local_courseerrors'] = 'Bulk course upload error';
$string['adminlog_filter_any'] = 'Any';
$string['adminlog_filter_apply'] = 'Filter';
$string['adminlog_actor_deleted'] = '{$a} (deleted user)';
$string['adminlog_actor_unknown'] = 'Unknown or erased';

// Privacy provider: the imported admin log.
$string['privacy:metadata:admin_log'] = 'The imported BizLMS admin log and bulk course upload errors: who acted, on what, and when. Kept as history when a person is erased; the person is removed from the row.';
$string['privacy:metadata:admin_log:source'] = 'Which BizLMS log the row came from';
$string['privacy:metadata:admin_log:event'] = 'What happened (insert, update, delete, or an upload error)';
$string['privacy:metadata:admin_log:module'] = 'The kind of item the entry is about';
$string['privacy:metadata:admin_log:description'] = 'Free text that names the acting person by first name; scrubbed when the person is erased';
$string['privacy:metadata:admin_log:itemref'] = 'The id of the item the entry is about, usually a course';
$string['privacy:metadata:admin_log:userid'] = 'The person who acted, or who ran the upload';
$string['privacy:metadata:admin_log:usermodified'] = 'The person who last modified the entry in BizLMS';
$string['privacy:metadata:admin_log:actor_path'] = 'The organisation path of the acting person when the entry was imported';
$string['privacy:metadata:admin_log:timecreated'] = 'When the entry was made in BizLMS';
$string['privacy:metadata:admin_log:timemodified'] = 'When the entry was last modified in BizLMS';
$string['privacy:metadata:course_creator'] = 'The BizLMS course-creator column on a course. The course is kept when its creator is erased; the creator is set to 0.';
$string['privacy:metadata:course_creator:open_coursecreator'] = 'The user who created the course, as BizLMS recorded it.';
