<?php
// Airpay Notification Rules — admin management.
//
// @package    local_airpay_notifications
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_notifications/index.php'));
$PAGE->set_title('Notification Rules');
$PAGE->set_heading('Notification Rules');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$dbman = $DB->get_manager();
$rules = [];
$total = 0;
$active = 0;

// Try Airpay table first, then BizLMS legacy.
$table = null;
if ($dbman->table_exists('local_airpay_notif_rules') && $DB->count_records('local_airpay_notif_rules') > 0) {
    $table = 'local_airpay_notif_rules';
} else if ($dbman->table_exists('local_notification_type')) {
    $table = 'local_notification_type';
}

if ($table) {
    $total = $DB->count_records($table);
    try { $active = $DB->count_records($table, ['enabled' => 1]); } catch (\Throwable $e) {
        try { $active = $DB->count_records($table, ['status' => 1]); } catch (\Throwable $e2) { $active = $total; }
    }
    $records = $DB->get_records($table, null, 'id ASC');

    // Map rule types from known patterns.
    $type_labels = [
        'deadline' => 'Deadline Reminder', 'enrollment' => 'Enrolment', 'completion' => 'Completion',
        'streak' => 'Engagement', 'overdue' => 'Overdue Alert', 'welcome' => 'Welcome',
    ];

    foreach ($records as $r) {
        $name = format_string($r->name ?? $r->shortname ?? 'Rule #' . $r->id);
        // Infer type from name if type field is empty.
        $type = $r->type ?? '';
        if (empty($type)) {
            $name_lower = strtolower($name);
            foreach ($type_labels as $key => $label) {
                if (strpos($name_lower, $key) !== false || strpos($name_lower, substr($key, 0, 4)) !== false) {
                    $type = $label;
                    break;
                }
            }
            if (empty($type)) {
                $type = 'Custom';
            }
        }

        $enabled = (bool) ($r->enabled ?? $r->status ?? 1);

        $rules[] = [
            'id'         => $r->id,
            'name'       => $name,
            'type'       => $type,
            'enabled'    => $enabled,
            'statuslabel' => $enabled ? 'Active' : 'Disabled',
            'statuscss'  => $enabled ? 'badge-success' : 'badge-secondary',
        ];
    }
}

$data = [
    'total'      => $total,
    'active'     => $active,
    'inactive'   => $total - $active,
    'rules'      => $rules,
    'has_rules'  => !empty($rules),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_notifications/manage', $data);
echo $OUTPUT->footer();
