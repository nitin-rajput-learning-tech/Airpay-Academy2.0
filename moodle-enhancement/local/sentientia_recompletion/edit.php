<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Create / edit a recompletion rule — Phase 5 A.3.
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id = optional_param('id', 0, PARAM_INT);
$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_recompletion/edit.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title($id ? 'Edit recompletion rule' : 'New recompletion rule');
$PAGE->set_heading($id ? 'Edit recompletion rule' : 'New recompletion rule');
require_capability('local/sentientia_recompletion:manage', $ctx);

// ADR-031: :manage says WHAT, not WHERE. A scoped caller may only open their
// own tenant's rules (never a global one), and a caller with no tenant
// nothing. Until 2026-09-25 any rule id loaded here for any holder.
if ($id) {
    $rule = \local_sentientia_recompletion\rule_access::require_rule($id);
} else {
    \local_sentientia_recompletion\rule_access::caller_root();
    $rule = (object) ['id' => 0];
}

class local_sentientia_recompletion_edit_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('rule_name', 'local_sentientia_recompletion'),
            ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');

        // Course selector — courses with completion enabled OR 0 = all.
        // ADR-031: only courses the caller's tenant may use. course_options()
        // runs the aliased query: the get_records_select('course', ...) that
        // stood here gave course_filter('c') no alias to qualify, so the form
        // failed with "Unknown column 'c.open_path'" for every scoped caller
        // (2026-09-25).
        $courses = \local_sentientia_recompletion\rule_access::course_options();
        $opts = [0 => '— All courses with completion enabled —'];
        foreach ($courses as $c) {
            $opts[$c->id] = format_string($c->fullname) . ' (' . $c->shortname . ')';
        }
        $mform->addElement('select', 'courseid',
            get_string('rule_courseid', 'local_sentientia_recompletion'), $opts);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'period_days',
            get_string('rule_period_days', 'local_sentientia_recompletion'), ['size' => 6]);
        $mform->setType('period_days', PARAM_INT);
        $mform->setDefault('period_days', 365);

        $mform->addElement('select', 'trigger_type',
            get_string('rule_trigger', 'local_sentientia_recompletion'), [
                'completion' => get_string('rule_trigger_completion', 'local_sentientia_recompletion'),
                'enrolment'  => get_string('rule_trigger_enrolment', 'local_sentientia_recompletion'),
                'fixed'      => get_string('rule_trigger_fixed', 'local_sentientia_recompletion'),
            ]);

        $mform->addElement('date_selector', 'fixed_date',
            get_string('rule_fixed_date', 'local_sentientia_recompletion'),
            ['optional' => true]);

        $mform->addElement('checkbox', 'reset_grades',
            get_string('rule_reset_grades', 'local_sentientia_recompletion'));
        $mform->setDefault('reset_grades', 1);

        $mform->addElement('checkbox', 'reset_attempts',
            get_string('rule_reset_attempts', 'local_sentientia_recompletion'));
        $mform->setDefault('reset_attempts', 1);

        $mform->addElement('checkbox', 'enabled',
            get_string('rule_enabled', 'local_sentientia_recompletion'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons(true, $this->_customdata['id']
            ? 'Save changes' : 'Create rule');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // ADR-031: the course must be one the caller's tenant may use. Check
        // the RAW submitted value: the select drops an unlisted id, so
        // $data['courseid'] arrives as null, which used to be saved as 0 =
        // all courses - a tampered request silently widened the rule.
        try {
            \local_sentientia_recompletion\rule_access::require_course_option(
                $this->_form->getSubmitValue('courseid'));
        } catch (\moodle_exception $e) {
            $errors['courseid'] = get_string('error_outoftenant', 'local_sentientia_platform');
        }
        return $errors;
    }
}

$form = new local_sentientia_recompletion_edit_form(null, ['id' => $rule->id]);
if ($rule->id) $form->set_data($rule);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/sentientia_recompletion/index.php'));
}

if ($data = $form->get_data()) {
    require_sesskey();
    // ADR-031: refuse, never coerce, a course that is not one of the options
    // (validation() already did; this keeps the save honest on its own).
    $courseid = \local_sentientia_recompletion\rule_access::require_course_option($data->courseid ?? null);
    $rec = (object) [
        'name'           => $data->name,
        'courseid'       => $courseid,
        'period_days'    => (int) $data->period_days,
        'trigger_type'   => $data->trigger_type,
        'fixed_date'     => $data->fixed_date ?: null,
        'reset_grades'   => !empty($data->reset_grades) ? 1 : 0,
        'reset_attempts' => !empty($data->reset_attempts) ? 1 : 0,
        'enabled'        => !empty($data->enabled) ? 1 : 0,
        'timemodified'   => time(),
    ];
    // ADR-031: a scoped caller's rule always carries their tenant - the
    // engine reads costcenterid 0 as EVERY tenant. Updates keep the stored
    // value; cross-tenant callers keep the old behaviour.
    $costcenterid = \local_sentientia_recompletion\rule_access::costcenterid_for_save($rule->id ? $rule : null);
    if ($costcenterid !== null) {
        $rec->costcenterid = $costcenterid;
    }
    if ($rule->id) {
        $rec->id = $rule->id;
        $DB->update_record('local_sentientia_recompletion_rules', $rec);
    } else {
        $rec->timecreated = time();
        $DB->insert_record('local_sentientia_recompletion_rules', $rec);
    }
    redirect(new moodle_url('/local/sentientia_recompletion/index.php'),
        'Rule saved.', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
