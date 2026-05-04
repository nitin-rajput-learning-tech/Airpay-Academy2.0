<?php
// Airpay Notification Rules — admin management.
//
// @package    local_airpay_notifications
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_notifications:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_notifications/index.php'));
$PAGE->set_title('Notification Rules');
$PAGE->set_heading('Notification Rules');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_manage = is_siteadmin() || has_capability('local/airpay_notifications:manage', $context);

$dbman = $DB->get_manager();
$rules_data = [];
$total = 0;
$active = 0;

if ($dbman->table_exists('local_airpay_notif_rules')) {
    $total  = \local_airpay_notifications\rule_manager::count_rules();
    $active = \local_airpay_notifications\rule_manager::count_rules(true);

    $records = $DB->get_records('local_airpay_notif_rules', null, 'id ASC');

    $type_labels    = \local_airpay_notifications\rule_manager::RULE_TYPES;
    $channel_labels = \local_airpay_notifications\rule_manager::CHANNELS;
    $audience_labels = \local_airpay_notifications\rule_manager::AUDIENCES;

    foreach ($records as $r) {
        $enabled = (bool) ($r->enabled ?? 1);
        $rules_data[] = [
            'id'            => $r->id,
            'name'          => format_string($r->name),
            'type'          => $type_labels[$r->rule_type] ?? ucwords(str_replace('_', ' ', $r->rule_type ?? 'custom')),
            'channellabel'  => $channel_labels[$r->channel ?? 'inapp'] ?? ($r->channel ?? 'In-app'),
            'audiencelabel' => $audience_labels[$r->audience ?? 'learner'] ?? ($r->audience ?? 'Learner'),
            'triggerlabel'  => isset($r->trigger_days) ? ($r->trigger_days . ' day' . ($r->trigger_days == 1 ? '' : 's')) : '—',
            'enabled'       => $enabled,
            'statuslabel'   => $enabled ? 'Active' : 'Disabled',
            'statuscss'     => $enabled ? 'badge-success' : 'badge-secondary',
        ];
    }
}

$data = [
    'total'      => $total,
    'active'     => $active,
    'inactive'   => $total - $active,
    'rules'      => $rules_data,
    'has_rules'  => !empty($rules_data),
    'can_manage' => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_notifications/manage', $data);
echo $OUTPUT->footer();
