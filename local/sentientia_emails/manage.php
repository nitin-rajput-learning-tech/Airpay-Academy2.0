<?php
/**
 * Unified Notification Management Panel — admin page with 5 tabs.
 *
 * URL: /local/sentientia_emails/manage.php?tab=dashboard&tenant=1
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

// Permission check: siteadmin OR L&D admin capability.
if (!is_siteadmin() && !has_capability('local/sentientia_emails:manage', $context)) {
    throw new \moodle_exception('nopermissions', 'error', '', 'manage notifications');
}

$tab      = optional_param('tab', 'dashboard', PARAM_ALPHA);
$page     = optional_param('page', 0, PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);

// ADR-031: the capability says WHAT; tenant::is_cross_tenant() alone says
// WHERE. A cross-tenant caller may pick any tenant (0 = all). Everyone else
// is pinned to their own tenant root whatever ?tenant= says, and refused
// when it does not resolve. (This used to honour any ?tenant= value, and an
// empty open_path became 0 = "All Tenants".)
$tenantid = \local_sentientia_emails\tenant_scope::resolve(optional_param('tenant', 0, PARAM_INT));

// Handle actions (toggle rule, export CSV, CRUD rules).
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'toggle':
            $ruleid = required_param('ruleid', PARAM_INT);
            $enabled = required_param('enabled', PARAM_INT);
            // ADR-031: global rules and other tenants' rules are cross-tenant writes.
            \local_sentientia_emails\tenant_scope::modifiable_rule($ruleid);
            \local_sentientia_emails\rule_manager::toggle_rule($ruleid, (bool)$enabled);
            redirect(new moodle_url('/local/sentientia_emails/manage.php', ['tab' => 'rules', 'tenant' => $tenantid]),
                'Rule ' . ($enabled ? 'enabled' : 'disabled'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'saverule':
            $editid = optional_param('ruleid', 0, PARAM_INT);
            // ADR-031: the rule being edited must be one the caller may change.
            $storedrule = $editid > 0 ? \local_sentientia_emails\tenant_scope::modifiable_rule($editid) : null;
            // D-12 (2026-11-20): an edit saves back every stored value it did not change. A field the
            // request does not carry is null here and keeps the stored value (rule_manager::rule_from_form()).
            // trigger_event and conditions_json are not read from the request at all: the form does not edit
            // them, and they used to be reset to 'cron' and '' on every edit. The choice fields and the numbers
            // are read raw-trimmed and checked by exact match or strict format there, so a cleaning PARAM type
            // cannot turn a crafted value into an accepted one (the reason G1 reads template_key this way).
            $ruleform = [
                'rule_name'               => required_param('rule_name', PARAM_TEXT),
                'rule_type'               => optional_param('rule_type', null, PARAM_RAW_TRIMMED),
                'trigger_days'            => optional_param('trigger_days', null, PARAM_RAW_TRIMMED),
                'channel'                 => optional_param('channel', null, PARAM_RAW_TRIMMED),
                'audience'                => optional_param('audience', null, PARAM_RAW_TRIMMED),
                'template_key'            => optional_param('template_key', null, PARAM_RAW_TRIMMED),
                'tenant_id'               => optional_param('rule_tenant', null, PARAM_RAW_TRIMMED),
                // The form posts a hidden 0 before each box, so an unticked box arrives as 0. The save used to
                // default a missing rule_enabled to 1, so the Enabled box could never switch a rule off.
                'enabled'                 => optional_param('rule_enabled', null, PARAM_INT),
                'priority'                => optional_param('priority', null, PARAM_RAW_TRIMMED),
                'cadence_days_json'       => optional_param('cadence_days_json', null, PARAM_RAW_TRIMMED),
                'max_reminders_per_user'  => optional_param('max_reminders_per_user', null, PARAM_RAW_TRIMMED),
                'auto_stop_on_completion' => optional_param('auto_stop_on_completion', null, PARAM_INT),
            ];
            try {
                // G1: only "None" or a template from the registry; D-12: the choice fields only from their lists.
                $ruledata = \local_sentientia_emails\rule_manager::rule_from_form($ruleform, $storedrule);
            } catch (\moodle_exception $e) {
                // Back to the form with the reason; nothing was saved. s(): a notification is shown as HTML,
                // and a cadence error quotes the submitted value.
                $formparams = ['tab' => 'rules', 'tenant' => $tenantid];
                $formparams += $editid > 0 ? ['edit' => $editid] : ['new' => 1];
                redirect(new moodle_url('/local/sentientia_emails/manage.php', $formparams),
                    s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
            }
            // ADR-031: the scope the caller chose is the scope saved. A scoped
            // caller's form offers their own tenant only (and pre-selects it),
            // so global (0) or another tenant from them is refused here -
            // no longer quietly rewritten to their own tenant. An edit that
            // keeps its scope is checked against the stored one.
            \local_sentientia_emails\tenant_scope::require_can_write_tenant(
                (int) ($ruledata->tenant_id ?? $storedrule->tenant_id));
            \local_sentientia_emails\rule_manager::save_rule($ruledata);
            $msg = $editid ? 'Rule updated.' : 'Rule created.';
            redirect(new moodle_url('/local/sentientia_emails/manage.php', ['tab' => 'rules', 'tenant' => $tenantid]),
                $msg, null, \core\output\notification::NOTIFY_SUCCESS);
            break;
        case 'deleterule':
            $ruleid = required_param('ruleid', PARAM_INT);
            \local_sentientia_emails\tenant_scope::modifiable_rule($ruleid);
            \local_sentientia_emails\rule_manager::delete_rule($ruleid);
            redirect(new moodle_url('/local/sentientia_emails/manage.php', ['tab' => 'rules', 'tenant' => $tenantid]),
                'Rule deleted.', null, \core\output\notification::NOTIFY_WARNING);
            break;
        case 'export':
            // $tenantid is always > 0 for a scoped caller; delivery_log also
            // forces the caller's tenant itself (ADR-031 defence in depth).
            $filters = $tenantid > 0 ? ['tenant_id' => $tenantid] : [];
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="notification-log-' . date('Y-m-d') . '.csv"');
            // Streamed a page at a time, so the export is the whole log and not its first 10 000 rows
            // (mapping doc section 11, code fix 4).
            \local_sentientia_emails\delivery_log::stream_csv($filters, static function (string $line): void {
                echo $line;
            });
            die();
    }
}

// Check if we're editing a specific rule.
$editruleid = optional_param('edit', 0, PARAM_INT);
$editrule = null;
if ($editruleid > 0 && $tab === 'rules') {
    $editrule = \local_sentientia_emails\rule_manager::get_rule($editruleid);
    if ($editrule) {
        // ADR-031: another tenant's rule is not readable; a global one is
        // (it fires for this tenant too) but saving it is refused above.
        \local_sentientia_emails\tenant_scope::require_can_view_rule($editrule);
    }
}

$PAGE->set_url(new moodle_url('/local/sentientia_emails/manage.php', ['tab' => $tab, 'tenant' => $tenantid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_sentientia_emails') . ' — Management');
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_emails'));
$PAGE->set_pagelayout('standard');

// Build tab navigation data.
// Audit fix M1 (2026-05-15): replaced FA4 'fa-envelope-o' with FA5/6
// 'fa-envelope' since Moodle 5.1.3 ships the newer Font Awesome and
// dropped the -o (regular) suffix.
$tabs = [
    ['key' => 'dashboard',  'label' => 'Dashboard',  'icon' => 'fa-tachometer',  'active' => ($tab === 'dashboard')],
    ['key' => 'templates',  'label' => 'Templates',  'icon' => 'fa-envelope',    'active' => ($tab === 'templates')],
    ['key' => 'rules',      'label' => 'Rules',      'icon' => 'fa-bolt',        'active' => ($tab === 'rules')],
    ['key' => 'logs',       'label' => 'Logs',       'icon' => 'fa-list-alt',    'active' => ($tab === 'logs')],
    ['key' => 'settings',   'label' => 'Settings',   'icon' => 'fa-cog',         'active' => ($tab === 'settings')],
];
foreach ($tabs as &$t) {
    $t['url'] = (new moodle_url('/local/sentientia_emails/manage.php', ['tab' => $t['key'], 'tenant' => $tenantid]))->out(false);
}
unset($t);

// Tenant selector data. ADR-031: a scoped caller is offered their own tenant only.
$tenants = \local_sentientia_emails\manage_controller::tenant_selector_options($tenantid);
$crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();

// Prepare tab-specific data.
$tabdata = [];
switch ($tab) {
    case 'dashboard':
        $tabdata = \local_sentientia_emails\manage_controller::get_dashboard_data($tenantid);
        break;
    case 'templates':
        $tabdata = \local_sentientia_emails\manage_controller::get_templates_data($tenantid);
        break;
    case 'rules':
        $tabdata = \local_sentientia_emails\manage_controller::get_rules_data($tenantid);
        break;
    case 'logs':
        $filters = [];
        if ($tenantid > 0) {
            $filters['tenant_id'] = $tenantid;
        }
        $tabdata = \local_sentientia_emails\manage_controller::get_logs_data($filters, $page);
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
    'manage_url'  => (new moodle_url('/local/sentientia_emails/manage.php'))->out(false),
    'preview_url' => (new moodle_url('/local/sentientia_emails/preview.php'))->out(false),
    'tabdata'     => $tabdata,
    'sesskey'     => sesskey(),
    // Tab-specific flags for conditional rendering.
    'is_dashboard' => ($tab === 'dashboard'),
    'is_templates' => ($tab === 'templates'),
    'is_rules'     => ($tab === 'rules'),
    'is_logs'      => ($tab === 'logs'),
    'is_settings'  => ($tab === 'settings'),
    // Rule editor data (editrule, has_editrule and the rule type / channel / audience option lists) is
    // merged in below from manage_controller::rule_form_context() (D-12, 2026-11-20).
    // ADR-031: scoped callers see a read-only global rule (no save) and a
    // Tenant Scope select holding their own tenant only.
    'is_crosstenant'     => $crosstenant,
    'editrule_readonly'  => $editrule && !$crosstenant
        && ((int) $editrule->tenant_id <= 0
            || (int) $editrule->tenant_id !== \local_sentientia_platform\tenant::root_for_current_user()),
    'rule_scope_options' => \local_sentientia_emails\manage_controller::rule_scope_options(
        $tenantid, $editrule ? (int) $editrule->tenant_id : null),
    'show_form'    => ($tab === 'rules' && (optional_param('new', 0, PARAM_INT) || $editruleid > 0)),
    // Available templates for the rule form dropdown: the template registry, with the edited
    // rule's template pre-selected (G1, 2026-11-19: it pre-selected nothing, so an edit saved "None").
    'template_options' => \local_sentientia_emails\manage_controller::rule_template_options(
        $editrule ? (string) ($editrule->template_key ?? '') : ''),
];
// D-12 (2026-11-20): every stored value of the edited rule is loaded into the form (it used to pre-select
// nothing in Rule Type, Channel and Audience, show a NULL Trigger Days as 0 and hide the cadence), so a
// re-save keeps the rule as it was unless the admin changed a field.
$pagecontext += \local_sentientia_emails\manage_controller::rule_form_context($editrule);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_emails/manage/page', $pagecontext);
echo $OUTPUT->footer();
