<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Role Management';

// Capabilities (must match db/access.php keys).
$string['airpay_roles:view']   = 'View role-management UI';
$string['airpay_roles:manage'] = 'Edit capability permissions on roles';
$string['airpay_roles:assign'] = 'Assign / unassign users to roles';
$string['airpay_roles:audit']  = 'View role-management audit log';
$string['airpay_roles:export'] = 'Export role + capability data to CSV';

// Page titles.
$string['heading_index']  = 'Role Management';
$string['heading_view']   = 'Role: {$a}';
$string['heading_audit']  = 'Audit Log';

// Index page UI.
$string['filter_archetype']     = 'Archetype';
$string['filter_archetype_all'] = 'All archetypes';
$string['filter_search']        = 'Search';
$string['filter_search_placeholder'] = 'Role name or shortname';
$string['btn_audit_log']        = 'Audit log';
$string['btn_export_csv']       = 'Export CSV';
$string['btn_view_role']        = 'View';
$string['btn_edit_caps']        = 'Edit capabilities';

// Index table columns.
$string['col_name']        = 'Name';
$string['col_shortname']   = 'Shortname';
$string['col_archetype']   = 'Archetype';
$string['col_caps']        = 'Capabilities';
$string['col_assignments'] = 'Assignments';
$string['col_sortorder']   = 'Sort order';
$string['col_actions']     = 'Actions';

// View page tabs.
$string['tab_overview']      = 'Overview';
$string['tab_capabilities']  = 'Capabilities';
$string['tab_assignments']   = 'Assignments';
$string['tab_audit']         = 'Audit';

// Overview tab.
$string['ov_id']               = 'Role ID';
$string['ov_shortname']        = 'Shortname';
$string['ov_archetype']        = 'Archetype';
$string['ov_archetype_custom'] = '(custom — no archetype)';
$string['ov_description']      = 'Description';
$string['ov_caps_total']       = 'Total capabilities';
$string['ov_caps_allow']       = 'Allow';
$string['ov_caps_prevent']     = 'Prevent';
$string['ov_caps_prohibit']    = 'Prohibit';
$string['ov_assignments']      = 'User assignments';
$string['ov_audit_entries']    = 'Audit entries (this role)';

// Capabilities tab.
$string['cap_filter_search']  = 'Filter capabilities';
$string['cap_filter_perm']    = 'Permission';
$string['cap_filter_perm_all'] = 'All';
$string['cap_perm_inherit']  = 'Inherit (not set)';
$string['cap_perm_allow']    = 'Allow';
$string['cap_perm_prevent']  = 'Prevent';
$string['cap_perm_prohibit'] = 'Prohibit';
$string['cap_col_name']      = 'Capability';
$string['cap_col_component'] = 'Component';
$string['cap_col_risks']     = 'Risks';
$string['cap_col_perm']      = 'Permission';
$string['cap_col_actions']   = 'Actions';
$string['cap_no_results']    = 'No capabilities match the current filter.';

// Edit capability modal.
$string['form_edit_cap']      = 'Edit capability';
$string['form_capability']    = 'Capability';
$string['form_permission']    = 'Permission';
$string['form_reason']        = 'Reason for change';
$string['form_reason_help']   = 'Optional. Stored in the audit log for compliance review.';
$string['form_save']          = 'Save change';
$string['form_cancel']        = 'Cancel';

// Audit log columns.
$string['audit_col_when']    = 'When';
$string['audit_col_who']     = 'Who';
$string['audit_col_role']    = 'Role';
$string['audit_col_action']  = 'Action';
$string['audit_col_cap']     = 'Capability';
$string['audit_col_change']  = 'Change';
$string['audit_col_reason']  = 'Reason';
$string['audit_no_entries']  = 'No audit entries yet.';
$string['audit_filter_role'] = 'Filter by role';
$string['audit_filter_role_all'] = 'All roles';
$string['audit_filter_action_all'] = 'All actions';
$string['audit_filter_action'] = 'Filter by action';
$string['audit_action_capability_set']   = 'Capability changed';
$string['audit_action_capability_unset'] = 'Capability reset';
$string['audit_action_role_assigned']    = 'Role assigned';
$string['audit_action_role_unassigned']  = 'Role unassigned';
$string['audit_action_role_created']     = 'Role created';
$string['audit_action_role_deleted']     = 'Role deleted';

// Errors.
$string['err_role_not_found']      = 'Role not found.';
$string['err_user_not_found']      = 'User not found.';
$string['err_capability_not_found'] = 'Capability "{$a}" is not registered in this Moodle.';
$string['err_invalid_permission']  = 'Permission must be one of: inherit, allow, prevent, prohibit.';
$string['err_cannot_modify_admin'] = 'Cannot modify capabilities on the site administrator role.';
$string['err_filterstoolong']      = 'Filter blob exceeds limit.';

// Notifications.
$string['cap_updated_success'] = 'Capability "{$a}" updated.';
