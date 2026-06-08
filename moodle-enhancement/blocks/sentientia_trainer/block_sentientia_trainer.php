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

        // Get sessions where current user is trainer.
        $sessions = [];
        $table = 'local_sentientia_classroom';
        $dbman = $DB->get_manager();

        if ($dbman->table_exists($table)) {
            $sessions = $DB->get_records($table, [
                'trainerid' => $USER->id,
                'status'    => 1,
            ], 'timecreated DESC', '*', 0, 10);
        } else if ($dbman->table_exists('local_classroom')) {
            // BizLMS fallback.
            $sessions = $DB->get_records_sql(
                "SELECT * FROM {local_classroom}
                  WHERE trainerid = :tid AND visible = 1
               ORDER BY timecreated DESC",
                ['tid' => $USER->id], 0, 10
            );
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
