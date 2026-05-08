<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Bulk CSV status change page (Phase E.3).
// Admin uploads a CSV with header `email,action`; we apply suspend or
// activate to each user and show a summary table.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_users:bulkstatuschange', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_users/bulk_csv.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Bulk CSV — Status Change');
$PAGE->set_heading('Bulk CSV — Status Change');
$PAGE->navbar->add('Manage users',
    new moodle_url('/local/airpay_users/index.php'));
$PAGE->navbar->add('Bulk CSV');

class local_airpay_users_bulkcsv_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csvfile', 'CSV file', null, [
            'accepted_types' => ['.csv'],
            'maxfiles'       => 1,
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('static', 'csvhelp', '',
            '<div class="alert alert-info small">'
            . 'CSV format (header row required):<br>'
            // Inline color override — default `code` color #e83e8c on the
            // alert-info background fails WCAG AA (3.36:1).
            . '<code style="color:#0a3d62;">email,action</code><br>'
            . '<code style="color:#0a3d62;">alice@airpay.in,suspend</code><br>'
            . '<code style="color:#0a3d62;">bob@airpay.in,activate</code>'
            . '</div>');
        $this->add_action_buttons(false, 'Process CSV');
    }
}

$form = new local_airpay_users_bulkcsv_form();
$summary = null;

if ($data = $form->get_data()) {
    require_sesskey();
    $content = $form->get_file_content('csvfile');
    if ($content === false || $content === '') {
        \core\notification::error('Empty or unreadable CSV file.');
    } else {
        try {
            $summary = \local_airpay_users\bulk_csv_processor::process(
                (string) $content, (int) $USER->id);
            \core\notification::success(
                "Processed {$summary['total']} rows: "
                . count($summary['succeeded']) . ' succeeded, '
                . count($summary['skipped']) . ' skipped, '
                . count($summary['failed']) . ' failed.');
        } catch (\Throwable $e) {
            \core\notification::error('Bulk CSV failed: ' . $e->getMessage());
        }
    }
}

echo $OUTPUT->header();
$form->display();

if ($summary) {
    $table_data = [
        'has_summary'  => true,
        'total'        => $summary['total'],
        'succeeded_count' => count($summary['succeeded']),
        'skipped_count'   => count($summary['skipped']),
        'failed_count'    => count($summary['failed']),
        'has_succeeded'   => !empty($summary['succeeded']),
        'has_skipped'     => !empty($summary['skipped']),
        'has_failed'      => !empty($summary['failed']),
        'succeeded'       => $summary['succeeded'],
        'skipped'         => $summary['skipped'],
        'failed'          => $summary['failed'],
    ];
    echo $OUTPUT->render_from_template('local_airpay_users/bulk_csv_summary',
        $table_data);
}

echo $OUTPUT->footer();
