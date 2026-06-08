<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// Phase G.1 (2026-05-08) — evaluation TEMPLATE import.
//
// Admin uploads a JSON file produced by export_template.php → creates
// a new evaluation in DRAFT state with the same questions.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();
$context = context_system::instance();
require_capability('local/sentientia_evaluation:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/import_template.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Import evaluation template');
$PAGE->set_heading('Import evaluation template');
$PAGE->navbar->add('Evaluations',
    new moodle_url('/local/sentientia_evaluation/index.php'));
$PAGE->navbar->add('Import template');

class local_sentientia_evaluation_import_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'jsonfile', 'Template JSON', null, [
            'accepted_types' => ['.json'],
            'maxfiles'       => 1,
        ]);
        $mform->addRule('jsonfile', null, 'required', null, 'client');
        $mform->addElement('static', 'help', '',
            '<div class="alert alert-info small">'
            . 'Upload a JSON file produced by the <em>Export template</em> '
            . 'action on another evaluation. The new evaluation is created '
            . 'in <strong style="color:#0a3d62;">Draft</strong> state — '
            . 'review and activate it from the Evaluations index.'
            . '</div>');
        $this->add_action_buttons(false, 'Import');
    }
}

$form = new local_sentientia_evaluation_import_form();
$result = null;

if ($data = $form->get_data()) {
    require_sesskey();
    $content = $form->get_file_content('jsonfile');
    if ($content === false || $content === '') {
        \core\notification::error('Empty or unreadable JSON file.');
    } else {
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            \core\notification::error('Invalid JSON: '
                . json_last_error_msg());
        } else {
            try {
                // Default tenant: caller's costcenterid (top of open_path).
                $parts = explode('/', trim($USER->open_path ?? '', '/'));
                $costcenter = isset($parts[0]) && ctype_digit($parts[0])
                    ? (int) $parts[0] : 0;
                $result = \local_sentientia_evaluation\evaluation_manager::import_template(
                    $payload, $costcenter);
                \core\notification::success(
                    "Imported '<strong>" . s($result['name']) . "</strong>' "
                    . "with " . $result['question_count'] . ' question(s). '
                    . '<a href="/local/sentientia_evaluation/questions.php?id='
                    . (int) $result['id'] . '" class="alert-link">'
                    . 'Open it now</a>.');
            } catch (\Throwable $e) {
                \core\notification::error('Import failed: ' . s($e->getMessage()));
            }
        }
    }
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
