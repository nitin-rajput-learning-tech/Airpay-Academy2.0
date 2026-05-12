<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Bulk unenrol via CSV upload — symmetric to enrol_csv.php.
 *
 * CSV columns: email,courseshortname
 * Removes manual enrolments only; other methods preserved.
 *
 * @package local_airpay_courses
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('local/airpay_courses:enrol', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_courses/bulk_unenrol.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Bulk unenrol via CSV');
$PAGE->set_heading('Bulk unenrol via CSV');
$PAGE->navbar->add('Manage courses',
    new moodle_url('/local/airpay_courses/index.php'));

class local_airpay_courses_bulk_unenrol_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('static', 'help', '',
            '<div class="alert alert-info">'
            . '<strong>CSV format:</strong> 2 columns: <code>email,courseshortname</code><br>'
            . '<strong>Header row required.</strong> Removes <strong>manual</strong> enrolments only — '
            . 'cohort/self/fee enrolments are preserved.</div>');
        $mform->addElement('filepicker', 'csvfile', 'CSV file', null, [
            'accepted_types' => ['.csv'], 'maxbytes' => 1024 * 1024 * 5,
        ]);
        $mform->addRule('csvfile', 'CSV file is required', 'required', null, 'server');
        $mform->addElement('checkbox', 'dryrun', 'Dry run (preview only — do not actually unenrol)');
        $this->add_action_buttons(true, 'Process CSV');
    }
}

$form = new local_airpay_courses_bulk_unenrol_form();
$summary = null;

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/airpay_courses/index.php'));
}

if ($data = $form->get_data()) {
    require_sesskey();
    $dryrun = !empty($data->dryrun);

    // Pull uploaded CSV.
    $usercontext = context_user::instance($USER->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft',
        (int) $data->csvfile, 'id', false);
    if (empty($files)) {
        \core\notification::error('No file uploaded.');
    } else {
        $file = reset($files);
        $content = $file->get_content();
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $header = array_map('trim', str_getcsv(array_shift($lines)));
        if ($header[0] !== 'email' || $header[1] !== 'courseshortname') {
            \core\notification::error(
                'CSV must start with header: email,courseshortname (got: '
                . implode(',', $header) . ')');
        } else {
            $processed = 0; $skipped = 0; $failed = 0;
            $report = [];

            $manual = enrol_get_plugin('manual');
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $row = str_getcsv($line);
                $email = trim($row[0] ?? '');
                $shortname = trim($row[1] ?? '');
                if ($email === '' || $shortname === '') {
                    $skipped++;
                    $report[] = ['line' => $line, 'status' => 'skipped',
                                 'msg' => 'Missing email or shortname'];
                    continue;
                }
                $user = $DB->get_record('user',
                    ['email' => $email, 'deleted' => 0]);
                $course = $DB->get_record('course', ['shortname' => $shortname]);
                if (!$user || !$course) {
                    $failed++;
                    $report[] = ['line' => $line, 'status' => 'failed',
                                 'msg' => !$user ? "User not found: $email"
                                                  : "Course not found: $shortname"];
                    continue;
                }
                $context = context_course::instance($course->id);
                if (!is_enrolled($context, $user->id)) {
                    $skipped++;
                    $report[] = ['line' => $line, 'status' => 'skipped',
                                 'msg' => 'Not enrolled'];
                    continue;
                }
                $instance = $DB->get_record('enrol',
                    ['courseid' => $course->id, 'enrol' => 'manual', 'status' => 0]);
                if (!$instance) {
                    $skipped++;
                    $report[] = ['line' => $line, 'status' => 'skipped',
                                 'msg' => 'Not enrolled via manual method'];
                    continue;
                }
                if (!$dryrun) {
                    $manual->unenrol_user($instance, $user->id);
                }
                $processed++;
                $report[] = ['line' => $line, 'status' => 'ok',
                             'msg' => $dryrun ? 'Would unenrol' : 'Unenrolled'];
            }

            // Decorate each report row with a Bootstrap badge class.
            $status_to_css = [
                'ok'      => 'bg-success',
                'failed'  => 'bg-danger',
                'skipped' => 'bg-secondary',
            ];
            $visible = array_slice($report, 0, 50);
            foreach ($visible as &$r) {
                $r['status_css'] = $status_to_css[$r['status']] ?? 'bg-secondary';
            }
            unset($r);

            $summary = [
                'processed' => $processed,
                'skipped'   => $skipped,
                'failed'    => $failed,
                'dryrun'    => $dryrun,
                'report'    => $visible,
                'report_truncated' => count($report) > 50,
            ];
        }
    }
}

echo $OUTPUT->header();

if ($summary !== null) {
    echo $OUTPUT->render_from_template('local_airpay_courses/bulk_unenrol_summary',
        $summary);
} else {
    echo '<div class="alert alert-warning small">'
        . '<strong>Note:</strong> This is a destructive action. '
        . 'Use <strong>Dry run</strong> first to preview which rows would be unenrolled.'
        . '</div>';
    $form->display();
}

echo $OUTPUT->footer();
