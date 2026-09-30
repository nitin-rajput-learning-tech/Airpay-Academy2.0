<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Sentientia Pages';
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

// ── QR attendance: what a learner sees after scanning (qr_scan.php) ──
$string['qr_scan_title'] = 'Attendance Confirmation';
$string['qr_scan_heading'] = 'Attendance';
$string['qr_back_dashboard'] = 'Back to Dashboard';
$string['qr_success_title'] = 'Attendance Marked!';
$string['qr_success_body'] = 'Your attendance has been successfully recorded at {$a}.';
$string['qr_already_title'] = 'Already Marked';
$string['qr_already_body'] = 'Your attendance for this session has already been marked, so this scan did not change it. If you think your mark is wrong, please ask your trainer.';
$string['qr_expired_title'] = 'QR Code Expired';
$string['qr_expired_body'] = 'This QR code has expired. Please scan the current QR code displayed by your trainer.';
$string['qr_nosession_title'] = 'Session Not Found';
$string['qr_nosession_body'] = 'This session no longer exists. Please ask your trainer for a new QR code.';
$string['qr_notenrolled_title'] = 'Not Enrolled';
$string['qr_notenrolled_body'] = 'You are not enrolled in this classroom, so your attendance was not recorded. Please contact your trainer.';
$string['qr_cancelled_title'] = 'Classroom Cancelled';
$string['qr_cancelled_body'] = 'This classroom has been cancelled, so your attendance was not recorded. Please contact your trainer.';
$string['qr_tooearly_title'] = 'Attendance Not Open Yet';
$string['qr_tooearly_body'] = 'Attendance for this session opens at {$a}, so your attendance was not recorded. Please scan the QR code again from then.';
$string['qr_toolate_title'] = 'Attendance Closed';
$string['qr_toolate_body'] = 'Attendance for this session closed at {$a}, so your attendance was not recorded. Please contact your trainer.';
$string['qr_notime_body'] = 'This session has no start time, so attendance cannot be taken by QR code. Please contact your trainer.';
$string['qr_error_title'] = 'Error';
$string['qr_error_unavailable'] = 'Classroom attendance is not available on this site.';
$string['qr_error_otherorg'] = 'This session belongs to a different organisation, so your attendance was not recorded.';
$string['qr_error_generic'] = 'Could not record attendance. Please contact your trainer.';

// ── QR attendance: the trainer's projector page (qr_attendance.php) ──
$string['qr_show_title'] = 'QR Attendance';
$string['qr_show_heading'] = 'Scan to Mark Attendance';
$string['qr_show_alt'] = 'QR Code';
$string['qr_show_nogd'] = 'The QR code could not be generated. Ask your administrator to check that the PHP GD extension is enabled.';
$string['qr_show_refreshes'] = 'Refreshes in {$a} minutes';
$string['qr_show_fullscreen'] = 'Fullscreen';
$string['qr_show_fullscreen_hint'] = 'Fullscreen for projector';
$string['qr_show_refresh'] = 'Refresh Now';
$string['qr_show_meta'] = 'Session ID: {$a->id} · Generated: {$a->time}';

// Privacy API (null provider).
$string['privacy:metadata'] = 'The Sentientia pages plugin does not store any personal data. QR attendance scans are recorded in the classroom plugin\'s tables and are described by that plugin\'s privacy provider.';
