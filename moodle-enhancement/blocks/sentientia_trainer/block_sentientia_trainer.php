<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Trainer dashboard block — replaces BizLMS block_trainerdashboard.
 *
 * Shows upcoming classroom sessions assigned to the current trainer.
 *
 * @package    block_sentientia_trainer
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_sentientia_trainer extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_sentientia_trainer');
    }

    public function applicable_formats() {
        return ['my' => true, 'site-index' => true];
    }

    public function get_content() {
        global $USER, $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get the classrooms where the current user is a trainer: the primary trainer of the classroom, or
        // any trainer listed in local_sentientia_classroom_trainers (ADR-032: BizLMS let a classroom have
        // several). The BizLMS {local_classroom} fallback is gone (classroom code fix 10): the import moves
        // that history into the Sentientia tables.
        $sessions = [];
        $table = 'local_sentientia_classroom';
        $trainers = 'local_sentientia_classroom_trainers';
        $dbman = $DB->get_manager();

        if ($dbman->table_exists($table)) {
            $select = 'status = :status AND (trainerid = :tid1';
            $params = ['status' => 1, 'tid1' => $USER->id];
            if ($dbman->table_exists($trainers)) {
                $select .= " OR EXISTS (SELECT 1 FROM {{$trainers}} ct
                                         WHERE ct.classroomid = {{$table}}.id AND ct.trainerid = :tid2)";
                $params['tid2'] = $USER->id;
            }
            $select .= ')';
            $sessions = $DB->get_records_select($table, $select, $params, 'timecreated DESC, id DESC', '*', 0, 10);
        }

        if (empty($sessions)) {
            $this->content->text = \html_writer::tag('p',
                get_string('notrainings', 'block_sentientia_trainer'),
                ['class' => 'text-muted']);
            return $this->content;
        }

        $html = \html_writer::start_tag('ul', ['class' => 'list-unstyled']);
        foreach ($sessions as $s) {
            $html .= \html_writer::tag('li',
                \html_writer::tag('strong', format_string($s->name))
                . \html_writer::tag('br', '')
                . \html_writer::tag('small', userdate($s->timecreated), ['class' => 'text-muted']),
                ['class' => 'mb-2']
            );
        }
        $html .= \html_writer::end_tag('ul');
        $this->content->text = $html;

        return $this->content;
    }
}
