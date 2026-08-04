<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Airpay Pages';
$string['privacy_policy'] = 'Privacy Policy';
$string['terms_of_use'] = 'Terms of Use';
$string['help_center'] = 'Help Center';
$string['contact_us'] = 'Contact Us';

// ── C10 P1 / Gap 3 — tenant-scoped certificate template browser ────
$string['cert_templates_title'] = 'Certificate templates (by tenant)';
$string['cert_templates_intro'] = 'Browse certificate templates with their tenant scope. Editing opens the standard certificate template editor.';
$string['cert_templates_empty'] = 'No certificate templates are visible to you.';
$string['cert_scope_heading'] = 'Certificate template tenant scoping';
$string['cert_scope_heading_desc'] = 'Optional mapping that scopes certificate templates to tenants. Only takes effect when the sentientia.certificate.tenant_scope.enabled feature flag is ON.';
$string['cert_template_tenant_map'] = 'Template → tenant map (JSON)';
$string['cert_template_tenant_map_desc'] = 'JSON object mapping certificate template id to a BizLMS tenant root: 1 = Airpay, 77 = Public, 177 = ZEEA. A template id that is absent, or mapped to 0, is treated as GLOBAL (visible to every tenant). Example: <code>{"5": 1, "8": 177, "11": 0}</code>. Malformed JSON is ignored (everything falls back to global).';
$string['cert_scope_off_notice'] = 'Tenant scoping is OFF. Every admin sees all templates — this matches current production behaviour. Enable the sentientia.certificate.tenant_scope.enabled flag to filter by tenant.';
$string['cert_scope_filtered_notice'] = 'Showing global templates plus templates assigned to your tenant ({$a}).';
$string['cert_col_template'] = 'Template';
$string['cert_col_scope'] = 'Tenant scope';
$string['cert_col_issued'] = 'Issued';
$string['cert_col_actions'] = 'Actions';
$string['cert_tenant_global'] = 'Global';
$string['cert_action_edit'] = 'Edit';
$string['cert_map_edit_hint'] = 'Template → tenant assignments are edited in the plugin settings.';
$string['cert_map_edit_link'] = 'Edit the tenant map';
$string['cert_hidden_count'] = '{$a} template(s) hidden by your tenant scope.';

// Privacy API (null provider).
$string['privacy:metadata'] = 'The Sentientia pages plugin does not store any personal data. QR attendance scans are recorded in the classroom plugin\'s tables and are described by that plugin\'s privacy provider.';
