<?php
/**
 * Unified Notification Management Panel — admin page with 5 tabs.
 *
 * URL: /local/airpay_emails/manage.php?tab=dashboard&tenant=1
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

// Permission check: siteadmin OR L&D admin capability.
if (!is_siteadmin() && !has_capability('local/airpay_emails:manage', $context)) {
    throw new \moodle_exception('nopermissions', 'error', '', 'manage notifications');
}

$tab      = optional_param('tab', 'dashboard', PARAM_ALPHA);
$tenantid = optional_param('tenant', 0, PARAM_INT);
$page     = optional_param('page', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);

// Determine user's tenant if not specified.
if ($tenantid === 0 && !is_siteadmin()) {
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $tenantid = (int)($parts[0] ?? 1);
}

// Handle actions (toggle rule, export CSV).
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'toggle':
            $ruleid = required_param('ruleid', PARAM_INT);
            $enabled = required_param('enabled', PARAM_INT);
            \local_airpay_emails\rule_manager::toggle_rule($ruleid, (bool)$enabled);
            redirect(new moodle_url('/local/airpay_emails/manage.php', ['tab' => 'rules', 'tenant' => $tenantid]),
                'Rule ' . ($enabled ? 'enabled' : 'disabled'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'export':
            $filters = $tenantid > 0 ? ['tenant_id' => $tenantid] : [];
            $csv = \local_airpay_emails\delivery_log::export_csv($filters);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="notification-log-' . date('Y-m-d') . '.csv"');
            echo $csv;
            die();
    }
}

$PAGE->set_url(new moodle_url('/local/airpay_emails/manage.php', ['tab' => $tab, 'tenant' => $tenantid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_airpay_emails') . ' — Management');
$PAGE->set_heading(get_string('pluginname', 'local_airpay_emails'));
$PAGE->set_pagelayout('standard');

// Build tab navigation data.
$tabs = [
    ['key' => 'dashboard',  'label' => 'Dashboard',  'icon' => 'fa-tachometer',  'active' => ($tab === 'dashboard')],
    ['key' => 'templates',  'label' => 'Templates',  'icon' => 'fa-envelope-o',  'active' => ($tab === 'templates')],
    ['key' => 'rules',      'label' => 'Rules',      'icon' => 'fa-bolt',        'active' => ($tab === 'rules')],
    ['key' => 'logs',       'label' => 'Logs',       'icon' => 'fa-list-alt',    'active' => ($tab === 'logs')],
    ['key' => 'settings',   'label' => 'Settings',   'icon' => 'fa-cog',         'active' => ($tab === 'settings')],
];
foreach ($tabs as &$t) {
    $t['url'] = (new moodle_url('/local/airpay_emails/manage.php', ['tab' => $t['key'], 'tenant' => $tenantid]))->out(false);
}
unset($t);

// Tenant selector data.
$tenants = [
    ['id' => 0,   'name' => 'All Tenants',  'selected' => ($tenantid == 0)],
    ['id' => 1,   'name' => 'Airpay',       'selected' => ($tenantid == 1)],
    ['id' => 77,  'name' => 'Public',        'selected' => ($tenantid == 77)],
    ['id' => 177, 'name' => 'ZEEA',          'selected' => ($tenantid == 177)],
];

// Prepare tab-specific data.
$tabdata = [];
switch ($tab) {
    case 'dashboard':
        $tabdata = \local_airpay_emails\manage_controller::get_dashboard_data();
        break;
    case 'templates':
        $tabdata = \local_airpay_emails\manage_controller::get_templates_data($tenantid);
        break;
    case 'rules':
        $tabdata = \local_airpay_emails\manage_controller::get_rules_data($tenantid);
        break;
    case 'logs':
        $filters = [];
        if ($tenantid > 0) {
            $filters['tenant_id'] = $tenantid;
        }
        $tabdata = \local_airpay_emails\manage_controller::get_logs_data($filters, $page);
        break;
    case 'settings':
        $tabdata = [
            'noemailever'     => !empty($CFG->noemailever),
            'noreplyaddress'  => get_config('moodle', 'noreplyaddress'),
            'smtphosts'       => get_config('moodle', 'smtphosts'),
            'is_siteadmin'    => is_siteadmin(),
        ];
        break;
}

// Context for the page template.
$pagecontext = [
    'tabs'        => $tabs,
    'tenants'     => $tenants,
    'tenant_id'   => $tenantid,
    'current_tab' => $tab,
    'is_siteadmin' => is_siteadmin(),
    'manage_url'  => (new moodle_url('/local/airpay_emails/manage.php'))->out(false),
    'preview_url' => (new moodle_url('/local/airpay_emails/preview.php'))->out(false),
    'tabdata'     => $tabdata,
    'sesskey'     => sesskey(),
    // Tab-specific flags for conditional rendering.
    'is_dashboard' => ($tab === 'dashboard'),
    'is_templates' => ($tab === 'templates'),
    'is_rules'     => ($tab === 'rules'),
    'is_logs'      => ($tab === 'logs'),
    'is_settings'  => ($tab === 'settings'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_emails/manage/page', $pagecontext);
echo $OUTPUT->footer();
