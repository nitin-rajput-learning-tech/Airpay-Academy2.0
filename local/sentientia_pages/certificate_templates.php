<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Tenant-scoped certificate template browser.
 *
 * C10 P1 / Gap 3 (2026-05-29) from the Certificate Stack investigation
 * (docs/audits/C10-CERTIFICATE-STACK-INVESTIGATION-2026-05-28.md).
 *
 * Problem: all 9 tool_certificate templates live at SYSTEM context, so
 * Moodle treats them as global — every tenant's L&D admin sees every
 * tenant's templates (e.g. a Public-tenant admin sees "AISECT Course
 * Completion"). That's a UX leak, not a security hole (editing is still
 * capability-gated), but it's noise.
 *
 * Fix (upgrade-safe, zero vendored mutation): a Sentientia-native JSON
 * map (admin setting local_sentientia_pages | cert_template_tenant_map)
 * assigns each templateid to a tenant. This page reads the
 * tool_certificate_* tables READ-ONLY and renders a tenant-aware view:
 *   - Site admins: every template + its assigned tenant column.
 *   - Tenant admins (flag ON): only GLOBAL templates + their own
 *     tenant's templates.
 *
 * Gated behind sentientia.certificate.tenant_scope.enabled (default
 * OFF). When OFF the page shows ALL templates to everyone — matching
 * today's production behaviour exactly.
 *
 * @package    local_sentientia_pages
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
$context = context_system::instance();

// Gate: site admins, or holders of the certificate-manage capability.
$can_view = is_siteadmin()
    || has_capability('moodle/site:config', $context)
    || has_capability('tool/certificate:manage', $context);
if (!$can_view) {
    throw new \required_capability_exception($context,
        'tool/certificate:manage', 'nopermissions', '');
}

admin_externalpage_setup('local_sentientia_pages_cert_templates',
    '', null, '', ['pagelayout' => 'admin']);

global $DB, $OUTPUT, $PAGE, $USER, $CFG;
$PAGE->set_url('/local/sentientia_pages/certificate_templates.php');
$PAGE->set_title(get_string('cert_templates_title', 'local_sentientia_pages'));
$PAGE->set_heading(get_string('cert_templates_title', 'local_sentientia_pages'));

// ── Known tenants (BizLMS roots) ───────────────────────────────────
$tenant_labels = [
    0   => get_string('cert_tenant_global', 'local_sentientia_pages'),
    1   => 'Airpay',
    77  => 'Public',
    177 => 'ZEEA',
];

// ── Resolve viewer's tenant from open_path (prod-compatible) ───────
$parts = explode('/', (string) ($USER->open_path ?? ''));
$viewer_tenant = (int) ($parts[1] ?? 0);
$is_admin = is_siteadmin();

// ── Feature flag ───────────────────────────────────────────────────
$scope_on = false;
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    $scope_on = \local_sentientia_platform\feature_flags::is_enabled(
        'sentientia.certificate.tenant_scope.enabled');
}

// ── Decode the tenant map (defensive) ──────────────────────────────
$map_raw = (string) get_config('local_sentientia_pages', 'cert_template_tenant_map');
$map = [];
if (trim($map_raw) !== '') {
    $decoded = json_decode($map_raw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $tplid => $tenantid) {
            $map[(int) $tplid] = (int) $tenantid;
        }
    }
}

// ── Read templates + live issue counts (READ-ONLY) ─────────────────
$templates = $DB->get_records_sql(
    "SELECT t.id, t.name,
            COUNT(i.id) AS issued
       FROM {tool_certificate_templates} t
  LEFT JOIN {tool_certificate_issues} i
            ON i.templateid = t.id AND i.archived = 0
   GROUP BY t.id, t.name
   ORDER BY t.name ASC");

echo $OUTPUT->header();

// Intro + flag-state banner.
echo html_writer::tag('p',
    get_string('cert_templates_intro', 'local_sentientia_pages'),
    ['class' => 'lead']);

if (!$scope_on) {
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-info-circle me-2',
            'aria-hidden' => 'true'])
        . get_string('cert_scope_off_notice', 'local_sentientia_pages'),
        'alert alert-info');
} else if (!$is_admin) {
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-filter me-2',
            'aria-hidden' => 'true'])
        . get_string('cert_scope_filtered_notice', 'local_sentientia_pages',
            $tenant_labels[$viewer_tenant] ?? (string) $viewer_tenant),
        'alert alert-success');
}

// ── Build the table ────────────────────────────────────────────────
$table = new \html_table();
$table->attributes['class'] = 'generaltable ap-cert-tpl-table';
$table->head = [
    get_string('cert_col_template', 'local_sentientia_pages'),
    get_string('cert_col_scope', 'local_sentientia_pages'),
    get_string('cert_col_issued', 'local_sentientia_pages'),
    get_string('cert_col_actions', 'local_sentientia_pages'),
];

$shown = 0;
$hidden = 0;
foreach ($templates as $t) {
    $assigned = $map[(int) $t->id] ?? 0;   // 0 = global

    // Tenant-scoped filtering: non-admins only see global + own tenant.
    if ($scope_on && !$is_admin) {
        if ($assigned !== 0 && $assigned !== $viewer_tenant) {
            $hidden++;
            continue;
        }
    }
    $shown++;

    $scope_label = $tenant_labels[$assigned] ?? (string) $assigned;
    $badge_class = $assigned === 0 ? 'bg-secondary' : 'bg-primary';

    // Edit link → vendored template editor (capability-gated there too).
    $edit_url = new moodle_url('/admin/tool/certificate/template.php',
        ['id' => $t->id]);

    $table->data[] = [
        format_string($t->name),
        html_writer::tag('span', s($scope_label),
            ['class' => 'badge ' . $badge_class]),
        number_format((int) $t->issued),
        html_writer::link($edit_url,
            get_string('cert_action_edit', 'local_sentientia_pages'),
            ['class' => 'btn btn-sm btn-outline-primary']),
    ];
}

if ($shown === 0) {
    echo html_writer::tag('p',
        get_string('cert_templates_empty', 'local_sentientia_pages'),
        ['class' => 'text-muted fst-italic']);
} else {
    echo html_writer::table($table);
}

// For site admins, surface the mapping-edit hint + any hidden count.
if ($is_admin) {
    $settings_url = new moodle_url('/admin/settings.php',
        ['section' => 'local_sentientia_pages']);
    echo html_writer::div(
        get_string('cert_map_edit_hint', 'local_sentientia_pages')
        . ' ' . html_writer::link($settings_url,
            get_string('cert_map_edit_link', 'local_sentientia_pages')),
        'text-muted small mt-3');
} else if ($scope_on && $hidden > 0) {
    echo html_writer::tag('p',
        get_string('cert_hidden_count', 'local_sentientia_pages', $hidden),
        ['class' => 'text-muted small mt-3']);
}

echo $OUTPUT->footer();
