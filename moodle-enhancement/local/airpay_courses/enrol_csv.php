<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase F.4 (2026-05-08) — mass-enrol users into courses via CSV.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_courses:enrol', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_courses/enrol_csv.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Mass-enrol via CSV');
$PAGE->set_heading('Mass-enrol via CSV');
$PAGE->navbar->add('Manage courses',
    new moodle_url('/local/airpay_courses/index.php'));
$PAGE->navbar->add('Mass enrol');

class local_airpay_courses_enrolcsv_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csvfile', 'Enrolment CSV', null, [
            'accepted_types' => ['.csv'],
            'maxfiles'       => 1,
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement('static', 'help', '',
            '<div class="alert alert-info small">'
            . '<strong style="color:#0a3d62;">CSV format:</strong> '
            . '<code style="color:#0a3d62;">email,courseshortname[,role]</code><br>'
            . 'Example:<br>'
            . '<code style="color:#0a3d62;">alice@airpay.in,COMPLIANCE-01,student</code><br>'
            . '<code style="color:#0a3d62;">bob@airpay.in,COMPLIANCE-01</code><br>'
            . 'Default role is <strong>student</strong> if not specified. '
            . 'Already-enrolled users are skipped.'
            . '</div>');
        $this->add_action_buttons(false, 'Enrol users');
    }
}

$form = new local_airpay_courses_enrolcsv_form();
$summary = null;

if ($data = $form->get_data()) {
    require_sesskey();
    $content = $form->get_file_content('csvfile');
    if ($content === false || $content === '') {
        \core\notification::error('Empty or unreadable CSV file.');
    } else {
        try {
            $summary = \local_airpay_courses\enrol_csv_processor::process(
                (string) $content, (int) $USER->id);
            \core\notification::success(
                "Processed {$summary['total']} rows: "
                . count($summary['succeeded']) . ' enrolled, '
                . count($summary['skipped']) . ' skipped, '
                . count($summary['failed']) . ' failed.');
        } catch (\Throwable $e) {
            \core\notification::error('Mass enrol failed: '
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
        'local_airpay_courses/enrol_csv_summary', $context_data);
}

echo $OUTPUT->footer();
