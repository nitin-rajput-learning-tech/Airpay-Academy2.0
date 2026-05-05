<?php
// Airpay Notification Rules — admin (datatable-driven).
//
// @package    local_airpay_notifications
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_notifications:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_notifications/index.php'));
$PAGE->set_title('Notification Rules');
$PAGE->set_heading('Notification Rules');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_manage = is_siteadmin() || has_capability('local/airpay_notifications:manage', $context);

$dbman = $DB->get_manager();
$total = $enabled = $disabled = 0;
if ($dbman->table_exists('local_airpay_notif_rules')) {
    $total    = (int) $DB->count_records('local_airpay_notif_rules');
    $enabled  = (int) $DB->count_records('local_airpay_notif_rules', ['enabled' => 1]);
    $disabled = $total - $enabled;
}

$columns = [
    ['key' => 'name',        'label' => 'Rule Name', 'sortable' => true,  'sortkey' => 'name'],
    ['key' => 'rule_type',   'label' => 'Type',      'sortable' => true,  'sortkey' => 'rule_type'],
    ['key' => 'channel',     'label' => 'Channel',   'sortable' => true,  'sortkey' => 'channel'],
    ['key' => 'audience',    'label' => 'Audience',  'sortable' => false],
    ['key' => 'trigger',     'label' => 'Trigger',   'sortable' => false],
    ['key' => 'modified',    'label' => 'Modified',  'sortable' => true,  'sortkey' => 'timemodified'],
    ['key' => 'statuslabel', 'label' => 'Status',    'sortable' => true,  'sortkey' => 'enabled', 'format' => 'badge'],
];

$data = [
    'total_count'    => number_format($total),
    'enabled_count'  => number_format($enabled),
    'disabled_count' => number_format($disabled),
    'can_manage'     => $can_manage,
    'columns_json'   => s(json_encode($columns)),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_notifications/manage', $data);
echo $OUTPUT->footer();
