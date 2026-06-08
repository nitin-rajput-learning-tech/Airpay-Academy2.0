<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase E.4 (2026-05-08) — bulk-import new users from CSV.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_users:create', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_users/bulk_import.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Bulk import users (CSV)');
$PAGE->set_heading('Bulk import users (CSV)');
$PAGE->navbar->add('Manage users',
    new moodle_url('/local/sentientia_users/index.php'));
$PAGE->navbar->add('Import CSV');

class local_sentientia_users_bulkimport_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csvfile', 'New users CSV', null, [
            'accepted_types' => ['.csv'],
            'maxfiles'       => 1,
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('static', 'csvhelp', '',
            '<div class="alert alert-info small">'
            . '<strong style="color:#0a3d62;">Required columns</strong> '
            . '(header row): '
            . '<code style="color:#0a3d62;">email,firstname,lastname,username</code>'
            . '<br><strong style="color:#0a3d62;">Optional columns:</strong> '
            . '<code style="color:#0a3d62;">employeeid, designation, department, '
            . 'team, grade, zone, region, location, employmenttype, client</code>'
            . '<br>Existing users (matching email or username) are skipped, '
            . 'not overwritten. New users get a random password — direct '
            . 'them through the &ldquo;Forgot password&rdquo; flow.'
            . '</div>');
        $this->add_action_buttons(false, 'Import users');
    }
}

$form = new local_sentientia_users_bulkimport_form();
$summary = null;

if ($data = $form->get_data()) {
    require_sesskey();
    $content = $form->get_file_content('csvfile');
    if ($content === false || $content === '') {
        \core\notification::error('Empty or unreadable CSV file.');
    } else {
        try {
            $summary = \local_sentientia_users\bulk_import_processor::process(
                (string) $content, (int) $USER->id);
            \core\notification::success(
                "Processed {$summary['total']} rows: "
                . count($summary['succeeded']) . ' created, '
                . count($summary['skipped']) . ' skipped, '
                . count($summary['failed']) . ' failed.');
        } catch (\Throwable $e) {
            \core\notification::error('Bulk import failed: '
                . s($e->getMessage()));
        }
    }
}

echo $OUTPUT->header();
$form->display();

if ($summary) {
    $context_data = [
        'has_summary'     => true,
        'total'           => $summary['total'],
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
    echo $OUTPUT->render_from_template(
        'local_sentientia_users/bulk_import_summary', $context_data);
}

echo $OUTPUT->footer();
