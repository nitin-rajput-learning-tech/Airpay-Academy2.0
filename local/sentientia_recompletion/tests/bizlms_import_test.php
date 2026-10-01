<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as platform_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_recompletion\bizlms\importer;
use local_sentientia_recompletion\bizlms\sources;

/**
 * The BizLMS recompletion import (ADR-032, mapping doc section 12), end to end.
 *
 * The seed is the map's fixture: a completion-enabled course with a quiz, a SCORM package and an LTI tool, one
 * learner with two archived cycles whose resets are in the log, one who reset their own completion, one whose
 * reset is missing from the log, a deleted learner, a learner with no tenant, questionnaire answers, old-format
 * rows with course 0, a teacher's preview attempt, and the orphans. The tests below prove what the map says:
 * every rule imported disabled and global, every reset in the log a history row at its real time, every archived
 * row attached to the reset that ended its cycle, an inferred reset capped at the next cycle's first evidence and
 * upgraded (not duplicated) when its log row turns up later, the payload equal to the source row, and nothing
 * written outside the three target tables.
 *
 * It includes the importer contract (framework trait), so the run is also proven idempotent, resumable, free of
 * side effects and reconciled.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recompletion\bizlms\importer
 * @covers     \local_sentientia_recompletion\bizlms\evidence
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;

    /** Id of a user row that does not exist. */
    private const NOBODY = 99999;

    /** @var array<string, array{0: string, 1: int}> Seed key => [legacy table, legacy id]. */
    private array $src = [];

    /** @var array<string, int> Ids of the things the seed created (users, courses, modules). */
    private array $ids = [];

    /** @var array<string, mixed>|null Decisions that differ from the signed ones (a test's own). */
    private ?array $decisionoverride = null;

    /** @var string[] Accepted needs-owner reasons of the decisions the test runs with. */
    private array $accepted = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/local_recompletion.install.xml'];
    }

    protected function contract_importer(): platform_importer {
        return new importer();
    }

    protected function contract_decisions(): decisions {
        $data = [
            'recompletion.rule_tenant' => 'global',
            'recompletion.enable_imported_rules' => false,
            'recompletion.preview_attempts' => 'skip',
            'recompletion.archive_shape' => 'generic_json_payload',
        ];
        if ($this->accepted) {
            $data['accepted_reasons'] = $this->accepted;
        }
        return decisions::from_array(($this->decisionoverride ?? []) + $data);
    }

    protected function contract_mutate_source(): void {
        global $DB;
        // A new setting changes the count and the highest id of the first step's source on every engine.
        $DB->insert_record('local_recompletion_config',
            (object) ['course' => $this->ids['c2'], 'name' => 'extra', 'value' => '1']);
    }

    protected function contract_user_columns(): array {
        return ['local_sentientia_recompletion_archive' => ['userid']];
    }

    /**
     * The contract test drops every claimed table, and the standard log is one of them while a legacy table
     * exists. It is a core table, not a fixture table: drop only the sixteen legacy ones (which is what makes the
     * feature not applicable, and stops the importer claiming the log at all).
     */
    public function test_contract_not_applicable_without_tables(): void {
        global $DB;
        $importer = $this->contract_begin();
        foreach (sources::legacy_tables() as $table) {
            self::drop_legacy_table($table);
        }
        $this->assertArrayNotHasKey(sources::LOG, $importer->sources(),
            'the log is claimed only while a legacy recompletion table exists');
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], 'nothing applicable is not a failure');
        $this->assertSame('not_applicable', $result['features'][$importer->feature()]);
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertFalse(legacymap::feature_complete($importer->feature()));
        $this->assertTrue($DB->get_manager()->table_exists(sources::LOG), 'the standard log is untouched');
    }

    // Seed.

    /**
     * A timestamp from a date, at noon UTC.
     *
     * @param string $date Y-m-d
     * @return int
     */
    private static function t(string $date): int {
        return (int) (new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('UTC')))->format('U');
    }

    /**
     * Insert a row into a legacy table and remember its id under a key.
     *
     * @param string $key
     * @param string $table
     * @param array $row
     * @return int
     */
    private function legacy(string $key, string $table, array $row): int {
        global $DB;
        $id = (int) $DB->insert_record($table, (object) $row);
        $this->src[$key] = [$table, $id];
        return $id;
    }

    /**
     * Insert a row into a core table, filling every NOT NULL column that has no default with a neutral value, so the
     * seed does not break when a Moodle release adds a column.
     *
     * @param string $table
     * @param array $values
     * @return int
     */
    private function core_row(string $table, array $values): int {
        global $DB;
        $row = new \stdClass();
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name === 'id') {
                continue;
            }
            if (array_key_exists($name, $values)) {
                $row->$name = $values[$name];
            } else if ($column->not_null && !$column->has_default) {
                $row->$name = in_array($column->meta_type, ['I', 'R', 'N', 'F'], true) ? 0 : '';
            }
        }
        return (int) $DB->insert_record($table, $row);
    }

    /**
     * A row of the standard log.
     *
     * @param string $event Event name.
     * @param int $related Relateduserid.
     * @param int $course
     * @param int $time
     * @param string $origin cli or web
     * @param int $actor Userid of the log row.
     * @return int
     */
    private function log(string $event, int $related, int $course, int $time, string $origin = 'cli', int $actor = 0): int {
        return $this->core_row(sources::LOG, [
            'eventname' => $event, 'component' => 'local_recompletion', 'action' => 'reset', 'target' => 'course',
            'crud' => 'u', 'edulevel' => 0, 'contextid' => \context_course::instance($course)->id,
            'contextlevel' => CONTEXT_COURSE, 'contextinstanceid' => $course, 'userid' => $actor,
            'courseid' => $course, 'relateduserid' => $related, 'anonymous' => 0, 'timecreated' => $time,
            'origin' => $origin, 'ip' => '127.0.0.1',
        ]);
    }

    protected function contract_seed(): void {
        global $DB;
        $this->setAdminUser();
        $admin = get_admin();
        $g = $this->getDataGenerator();
        $t = [self::class, 't'];

        foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'] as $name) {
            $this->ids[$name] = (int) $g->create_user(['firstname' => strtoupper($name), 'lastname' => 'Learner'])->id;
        }
        $DB->set_field('user', 'deleted', 1, ['id' => $this->ids['e']]);

        $c1 = $g->create_course(['shortname' => 'AML-1', 'fullname' => 'Anti money laundering', 'enablecompletion' => 1]);
        $c2 = $g->create_course(['shortname' => 'KYC-2', 'enablecompletion' => 1]);
        $c3 = $g->create_course(['shortname' => 'POSH-3', 'enablecompletion' => 1]);
        $c4 = $g->create_course(['shortname' => str_repeat('L', 250), 'enablecompletion' => 1]);
        $this->ids += ['c1' => (int) $c1->id, 'c2' => (int) $c2->id, 'c3' => (int) $c3->id, 'c4' => (int) $c4->id];
        $page = $g->create_module('page', ['course' => $c1->id]);
        $quiz = $g->create_module('quiz', ['course' => $c1->id, 'grade' => 10]);
        $DB->set_field('quiz', 'sumgrades', 20, ['id' => $quiz->id]);
        $DB->set_field('quiz', 'grade', 10, ['id' => $quiz->id]);
        $scorm = $g->create_module('scorm', ['course' => $c1->id]);
        $this->ids += ['page' => (int) $page->id, 'cm_page' => (int) $page->cmid, 'quiz' => (int) $quiz->id,
            'cm_quiz' => (int) $quiz->cmid, 'scorm' => (int) $scorm->id, 'admin' => (int) $admin->id];
        $this->ids['tool'] = 4242;
        if ($DB->get_manager()->table_exists('enrol_lti_tools')) {
            $this->ids['tool'] = $this->core_row('enrol_lti_tools',
                ['contextid' => \context_course::instance($c1->id)->id]);
        }

        [$a, $b, $c, $d, $e] = [$this->ids['a'], $this->ids['b'], $this->ids['c'], $this->ids['d'], $this->ids['e']];
        [$uf, $ug, $uh] = [$this->ids['f'], $this->ids['g'], $this->ids['h']];
        [$ui, $uj] = [$this->ids['i'], $this->ids['j']];
        $course = $this->ids['c1'];
        $reset = sources::RESET_EVENT;

        // Rules: course 1 fully configured (BizLMS defaults of the map's fixture), course 2 with only enable 0,
        // course 3 with a zero duration, course 4 with a name that has to be shortened, and two courses that
        // cannot have a rule (a deleted one, the front page).
        $config = [
            'enable' => '1', 'recompletionduration' => '31536000', 'deletegradedata' => '1',
            'archivecompletiondata' => '1', 'quiz' => '1', 'archivequiz' => '1', 'scorm' => '1',
            'archivescorm' => '1', 'assign' => '2', 'recompletionemailsubject' => 'Renewal of {$a->coursename}',
            'recompletionemailbody' => 'Please renew', 'deletescormdata' => '1',
        ];
        foreach ($config as $name => $value) {
            $this->legacy('config_c1_' . $name, 'local_recompletion_config',
                ['course' => $course, 'name' => $name, 'value' => $value]);
        }
        $this->legacy('config_c2', 'local_recompletion_config', ['course' => $this->ids['c2'], 'name' => 'enable', 'value' => '0']);
        $this->legacy('config_c3a', 'local_recompletion_config', ['course' => $this->ids['c3'], 'name' => 'enable', 'value' => '1']);
        $this->legacy('config_c3b', 'local_recompletion_config',
            ['course' => $this->ids['c3'], 'name' => 'recompletionduration', 'value' => '0']);
        $this->legacy('config_c4a', 'local_recompletion_config', ['course' => $this->ids['c4'], 'name' => 'enable', 'value' => '1']);
        $this->legacy('config_c4b', 'local_recompletion_config',
            ['course' => $this->ids['c4'], 'name' => 'recompletionduration', 'value' => '86400']);
        $this->legacy('config_gone', 'local_recompletion_config', ['course' => self::NOBODY, 'name' => 'enable', 'value' => '1']);
        $this->legacy('config_site', 'local_recompletion_config', ['course' => SITEID, 'name' => 'enable', 'value' => '1']);

        // Learner A: two cycles, both resets in the log.
        $this->legacy('cc_a1', 'local_recompletion_cc', ['userid' => $a, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->legacy('cc_a2', 'local_recompletion_cc', ['userid' => $a, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2024-03-03'), 'timecompleted' => $t('2024-03-05'),
            'reaggregate' => 0]);
        $this->src['log_ra1'] = [sources::EVENT_UNIT, $this->log($reset, $a, $course, $t('2024-03-02'))];
        $this->src['log_ra2'] = [sources::EVENT_UNIT, $this->log($reset, $a, $course, $t('2025-03-06'))];
        $this->legacy('cc_cc_a1', 'local_recompletion_cc_cc', ['userid' => $a, 'course' => $course, 'criteriaid' => 11,
            'gradefinal' => '80.5', 'timecompleted' => $t('2023-03-01')]);
        $this->legacy('cc_cc_a2', 'local_recompletion_cc_cc', ['userid' => $a, 'course' => $course, 'criteriaid' => 11,
            'gradefinal' => '91', 'timecompleted' => $t('2024-03-05')]);
        $cm = $this->ids['cm_page'];
        $this->legacy('cmc_a1', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => $a, 'completionstate' => 1,
            'viewed' => 1, 'overrideby' => $this->ids['admin'], 'timemodified' => $t('2023-02-20'), 'course' => $course]);
        $this->legacy('cmc_a_old', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => $a, 'completionstate' => 2,
            'viewed' => null, 'overrideby' => null, 'timemodified' => $t('2023-02-21'), 'course' => 0]);
        $this->legacy('cmc_a2', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => $a, 'completionstate' => 1,
            'viewed' => 1, 'overrideby' => null, 'timemodified' => $t('2024-03-04'), 'course' => $course]);
        $q = $this->ids['quiz'];
        $attempt = static fn(int $user, int $n, int $uniqueid, int $time, string $marks, int $courseid, int $preview = 0): array => [
            'quiz' => $q, 'userid' => $user, 'attempt' => $n, 'uniqueid' => $uniqueid, 'layout' => '1,2,0',
            'currentpage' => 0, 'preview' => $preview, 'state' => 'finished', 'timestart' => $time - 600,
            'timefinish' => $time, 'timemodified' => $time, 'timecheckstate' => null, 'sumgrades' => $marks,
            'course' => $courseid,
        ];
        $this->legacy('qa_a1', 'local_recompletion_qa', $attempt($a, 1, 7001, $t('2023-02-25'), '15', $course));
        $this->legacy('qa_a_old', 'local_recompletion_qa', $attempt($a, 2, 7002, $t('2023-02-26'), '12', 0));
        $this->legacy('qa_a2', 'local_recompletion_qa', $attempt($a, 3, 7003, $t('2024-03-04'), '18', $course));
        $this->legacy('qa_preview', 'local_recompletion_qa', $attempt($this->ids['admin'], 1, 7004, $t('2023-02-24'), '20', $course, 1));
        $this->legacy('qg_a1', 'local_recompletion_qg', ['quiz' => $q, 'userid' => $a, 'grade' => '7.5',
            'timemodified' => $t('2023-02-25'), 'course' => $course]);
        $this->legacy('qg_a_old', 'local_recompletion_qg', ['quiz' => $q, 'userid' => $a, 'grade' => '6',
            'timemodified' => $t('2023-02-26'), 'course' => 0]);
        $this->legacy('qg_a2', 'local_recompletion_qg', ['quiz' => $q, 'userid' => $a, 'grade' => '9',
            'timemodified' => $t('2024-03-04'), 'course' => $course]);
        $track = fn(string $key, string $element, string $value, string $date, int $courseid) => $this->legacy($key,
            'local_recompletion_sst', ['userid' => $a, 'scormid' => $this->ids['scorm'], 'scoid' => 1, 'attempt' => 1,
                'element' => $element, 'value' => $value, 'timemodified' => $t($date), 'course' => $courseid]);
        $track('sst_1', 'cmi.core.lesson_status', 'completed', '2023-02-27', $course);
        $track('sst_2', 'cmi.core.score.raw', '95', '2023-02-27', $course);
        $track('sst_old', 'cmi.completion_status', 'completed', '2023-02-28', 0);
        $track('sst_4', 'cmi.core.lesson_status', 'passed', '2024-03-04', $course);
        $track('sst_5', 'cmi.core.score.raw', 'n/a', '2024-03-04', $course);
        $this->legacy('ltia_a', 'local_recompletion_ltia', ['toolid' => $this->ids['tool'], 'userid' => $a,
            'lastgrade' => '88', 'lastaccess' => $t('2024-02-01'), 'timecreated' => $t('2023-02-01')]);
        $response = fn(string $key, int $original, ?int $user, string $date) => $this->legacy($key,
            'local_recompletion_qr', ['originalresponseid' => $original, 'questionnaireid' => 7, 'submitted' => $t($date),
                'complete' => 'y', 'grade' => 5, 'userid' => $user, 'course' => $course]);
        $response('qr_1', 501, $a, '2023-02-28');
        $response('qr_anon', 502, null, '2023-02-28');
        $response('qr_orphan_user', 503, self::NOBODY, '2023-02-28');
        $this->legacy('qr_bool', 'local_recompletion_qr_bool', ['response_id' => 501, 'question_id' => 71, 'choice_id' => 'y']);
        $this->legacy('qr_text', 'local_recompletion_qr_text',
            ['response_id' => 501, 'question_id' => 70, 'response' => 'Free text answer']);
        $this->legacy('qr_date', 'local_recompletion_qr_date', ['response_id' => 501, 'question_id' => 73, 'response' => '2023-02-28']);
        $this->legacy('qr_m', 'local_recompletion_qr_m', ['response_id' => 501, 'question_id' => 74, 'choice_id' => 5]);
        $this->legacy('qr_other', 'local_recompletion_qr_other',
            ['response_id' => 501, 'question_id' => 75, 'choice_id' => 9, 'response' => 'other text']);
        $this->legacy('qr_rank', 'local_recompletion_qr_rank',
            ['response_id' => 501, 'question_id' => 72, 'choice_id' => 3, 'rankvalue' => 2]);
        $this->legacy('qr_single', 'local_recompletion_qr_single', ['response_id' => 501, 'question_id' => 76, 'choice_id' => 4]);
        $this->legacy('qr_single_orphan', 'local_recompletion_qr_single',
            ['response_id' => 999, 'question_id' => 76, 'choice_id' => 4]);

        // Learner B reset their own completion; no archived completion, but the log saw it complete.
        $this->log('\\core\\event\\course_completed', $b, $course, $t('2025-01-20'), 'web');
        $this->legacy('qa_b', 'local_recompletion_qa', $attempt($b, 1, 7005, $t('2025-01-25'), '10', $course));
        $this->src['log_rb'] = [sources::EVENT_UNIT, $this->log($reset, $b, $course, $t('2025-02-10'), 'web', $b)];

        // Learner D (no tenant): reset from a browser by an administrator, which the log cannot tell from the cron.
        $this->src['log_rd'] = [sources::EVENT_UNIT, $this->log($reset, $d, $course, $t('2025-04-01'), 'web', $this->ids['admin'])];

        // Learner E (deleted): a completed cycle and its reset.
        $this->legacy('cc_e', 'local_recompletion_cc', ['userid' => $e, 'course' => $course, 'timeenrolled' => $t('2022-01-01'),
            'timestarted' => $t('2022-01-02'), 'timecompleted' => $t('2022-05-01'), 'reaggregate' => 0]);
        $this->src['log_re'] = [sources::EVENT_UNIT, $this->log($reset, $e, $course, $t('2022-06-01'))];

        // Learner C: a completed cycle with NO reset in the log, and evidence of the next cycle after it.
        $this->legacy('cc_c1', 'local_recompletion_cc', ['userid' => $c, 'course' => $course, 'timeenrolled' => $t('2025-06-01'),
            'timestarted' => $t('2025-06-05'), 'timecompleted' => $t('2025-12-01'), 'reaggregate' => 0]);
        $this->legacy('cmc_c1', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => $c, 'completionstate' => 1,
            'viewed' => 1, 'overrideby' => null, 'timemodified' => $t('2025-11-15'), 'course' => $course]);
        $this->legacy('cmc_c_next', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => $c, 'completionstate' => 1,
            'viewed' => 1, 'overrideby' => null, 'timemodified' => $t('2026-01-20'), 'course' => $course]);

        // Learner F: a second cycle that was reset without ever being started. Core recreates the completion row
        // after a reset with the ORIGINAL enrolment date and timestarted 0, so by its own dates this cycle ran from
        // before the first one completed. Both resets are in the log (the second from a browser, by an administrator).
        $this->legacy('cc_f1', 'local_recompletion_cc', ['userid' => $uf, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->legacy('cc_f2', 'local_recompletion_cc', ['userid' => $uf, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => 0, 'timecompleted' => 0, 'reaggregate' => 0]);
        $this->src['log_rf1'] = [sources::EVENT_UNIT, $this->log($reset, $uf, $course, $t('2024-03-02'))];
        $this->src['log_rf2'] = [sources::EVENT_UNIT, $this->log($reset, $uf, $course, $t('2024-06-01'), 'web', $this->ids['admin'])];

        // Learner G: one archived cycle and TWO resets in the log (the second has no archived completion of its own,
        // as when archiving was off). The log saw the learner complete once, before the first reset.
        $this->legacy('cc_g1', 'local_recompletion_cc', ['userid' => $ug, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->log('\\core\\event\\course_completed', $ug, $course, $t('2023-03-01'), 'cli');
        $this->src['log_rg1'] = [sources::EVENT_UNIT, $this->log($reset, $ug, $course, $t('2024-03-02'))];
        $this->src['log_rg2'] = [sources::EVENT_UNIT, $this->log($reset, $ug, $course, $t('2024-06-01'))];

        // Learner H: two archived cycles and NO reset in the log, the second never started. Each gets an inferred
        // reset, and the second cannot be dated before the first one ended.
        $this->legacy('cc_h1', 'local_recompletion_cc', ['userid' => $uh, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->legacy('cc_h2', 'local_recompletion_cc', ['userid' => $uh, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => 0, 'timecompleted' => 0, 'reaggregate' => 0]);
        $this->legacy('cmc_h1', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => $uh, 'completionstate' => 1,
            'viewed' => 1, 'overrideby' => null, 'timemodified' => $t('2023-02-20'), 'course' => $course]);

        // Learner I: the second cycle's completion was BACKDATED. After the first reset core's cron re-marked a
        // criterion that carries a kept grade complete with its OLD time (2023-06-01) and completed the course at
        // that time, while the rebuilt row has no start of its own. By its dates the second cycle completed before
        // the first one was reset; both resets are in the log, a day apart (a bulk reset by the cron).
        $this->legacy('cc_i1', 'local_recompletion_cc', ['userid' => $ui, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->legacy('cc_i2', 'local_recompletion_cc', ['userid' => $ui, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => 0, 'timecompleted' => $t('2023-06-01'), 'reaggregate' => 0]);
        $this->src['log_ri1'] = [sources::EVENT_UNIT, $this->log($reset, $ui, $course, $t('2024-03-02'))];
        $this->src['log_ri2'] = [sources::EVENT_UNIT, $this->log($reset, $ui, $course, $t('2024-03-03'))];

        // Learner J: the same, but the cron also stamped the START with the criterion's old time
        // (completion_criteria_completion::mark_complete hands it to mark_inprogress), so the second cycle "started"
        // months before the first one was reset, and a start later than the first cycle's completion looks real.
        $this->legacy('cc_j1', 'local_recompletion_cc', ['userid' => $uj, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->legacy('cc_j2', 'local_recompletion_cc', ['userid' => $uj, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-06-01'), 'timecompleted' => $t('2023-06-01'),
            'reaggregate' => 0]);
        $this->src['log_rj1'] = [sources::EVENT_UNIT, $this->log($reset, $uj, $course, $t('2024-03-02'))];
        $this->src['log_rj2'] = [sources::EVENT_UNIT, $this->log($reset, $uj, $course, $t('2024-03-03'))];

        // Orphans: a person the restored database has no row for, and a reset that names nobody.
        $this->legacy('cc_orphan', 'local_recompletion_cc', ['userid' => self::NOBODY, 'course' => $course,
            'timeenrolled' => $t('2023-01-10'), 'timestarted' => $t('2023-01-15'), 'timecompleted' => $t('2023-03-01'),
            'reaggregate' => 0]);
        $this->legacy('cmc_orphan', 'local_recompletion_cmc', ['coursemoduleid' => $cm, 'userid' => self::NOBODY,
            'completionstate' => 1, 'viewed' => 1, 'overrideby' => null, 'timemodified' => $t('2023-02-20'), 'course' => $course]);
        $this->src['log_incomplete'] = [sources::EVENT_UNIT, $this->log($reset, 0, $course, $t('2025-05-05'))];
        $this->src['log_orphan'] = [sources::EVENT_UNIT, $this->log($reset, self::NOBODY - 1, $course, $t('2025-05-06'))];
        // Not a reset: the filter must leave it alone.
        $this->log('\\core\\event\\course_viewed', $a, $course, $t('2025-01-01'), 'web', $a);
    }

    // Helpers for the assertions.

    /**
     * @param string $table Source table (or derived unit).
     * @param int $id
     * @param string $subkey
     * @return \stdClass The primary (or sub) map row.
     */
    private function mapped(string $table, int $id, string $subkey = ''): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE, ['sourcetable' => $table, 'sourceid' => $id, 'subkey' => $subkey],
            '*', MUST_EXIST);
    }

    /**
     * The target row a seeded source row became.
     *
     * @param string $key Seed key.
     * @param string $subkey
     * @return \stdClass
     */
    private function target(string $key, string $subkey = ''): \stdClass {
        global $DB;
        $map = $this->mapped($this->src[$key][0], $this->src[$key][1], $subkey);
        return $DB->get_record($map->targettable, ['id' => $map->targetid], '*', MUST_EXIST);
    }

    /**
     * @param string $course Name of the course in $this->ids.
     * @return \stdClass The imported rule of the course.
     */
    private function rule_of(string $course): \stdClass {
        global $DB;
        $map = $this->mapped(sources::RULE_UNIT, $this->ids[$course]);
        return $DB->get_record(sources::RULES, ['id' => $map->targetid], '*', MUST_EXIST);
    }

    /**
     * @param int $userid
     * @return \stdClass[] Imported history of a learner, oldest first.
     */
    private function history_of(int $userid): array {
        global $DB;
        return array_values($DB->get_records(sources::HISTORY, ['userid' => $userid], 'timecreated ASC, id ASC'));
    }

    /**
     * Seed, import and return the result.
     *
     * @return array The runner's result.
     */
    private function imported(): array {
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        return $result;
    }

    // Rules.

    public function test_every_rule_is_imported_disabled_global_and_with_its_legacy_settings(): void {
        global $DB;
        $this->imported();

        $this->assertSame(4, $DB->count_records(sources::RULES), 'four courses can have a rule');
        $lastreset = (int) $DB->get_field_sql('SELECT MAX(timecreated) FROM {' . sources::LOG . '} WHERE eventname = :e AND courseid = :c',
            ['e' => sources::RESET_EVENT, 'c' => $this->ids['c1']]);

        $r1 = $this->rule_of('c1');
        $this->assertEquals(0, $r1->enabled, 'the engine runs every enabled rule; an imported one starts nothing');
        $this->assertEquals(0, $r1->costcenterid, 'legacy semantics: every completer, any tenant');
        $this->assertEquals($this->ids['c1'], $r1->courseid);
        $this->assertEquals(365, $r1->period_days, '31536000 seconds');
        $this->assertSame('completion', $r1->trigger_type);
        $this->assertNull($r1->fixed_date);
        $this->assertEquals(1, $r1->reset_grades);
        $this->assertEquals(1, $r1->reset_attempts, 'quiz = delete');
        $this->assertSame('Legacy recompletion: AML-1', $r1->name);
        $this->assertEquals($lastreset, $r1->last_run_at, 'the last reset the log saw in the course');
        $this->assertNull($r1->last_run_resets);
        $this->assertEquals([
            'enable' => '1', 'recompletionduration' => '31536000', 'deletegradedata' => '1',
            'archivecompletiondata' => '1', 'quiz' => '1', 'archivequiz' => '1', 'scorm' => '1', 'archivescorm' => '1',
            'assign' => '2', 'recompletionemailsubject' => 'Renewal of {$a->coursename}',
            'recompletionemailbody' => 'Please renew', 'deletescormdata' => '1',
        ], json_decode($r1->legacy_config, true), 'every setting is kept, the ones the rule cannot express included');

        $r2 = $this->rule_of('c2');
        $this->assertEquals(0, $r2->enabled);
        $this->assertEquals(365, $r2->period_days, 'no duration: the year the rule form defaults to');
        $this->assertEquals(0, $r2->reset_grades);
        $this->assertEquals(0, $r2->reset_attempts);
        $this->assertNull($r2->last_run_at);
        $this->assertSame(['enable' => '0'], json_decode($r2->legacy_config, true));

        $r3 = $this->rule_of('c3');
        $this->assertEquals(0, $r3->enabled);
        $this->assertEquals(365, $r3->period_days, 'a zero duration is not a period');
        $this->assertSame('0', json_decode($r3->legacy_config, true)['recompletionduration']);

        $r4 = $this->rule_of('c4');
        $this->assertEquals(1, $r4->period_days, 'one day of seconds');
        $this->assertSame(200, \core_text::strlen($r4->name), 'the name column is char(200)');
        $this->assertStringStartsWith('Legacy recompletion: LLLL', $r4->name);

        foreach ([self::NOBODY, SITEID] as $course) {
            $map = $this->mapped(sources::RULE_UNIT, $course);
            $this->assertSame('skipped', $map->outcome);
            $this->assertSame('orphan_course', $map->reason, 'the front page and a deleted course have no rule');
            $this->assertNull($map->targetid);
        }
        $this->assertSame(0, $DB->count_records(sources::RULES, ['enabled' => 1]));
    }

    public function test_a_course_without_a_duration_takes_the_legacy_site_default(): void {
        set_config('duration', 7 * DAYSECS, 'local_recompletion');
        $this->imported();
        $this->assertEquals(7, $this->rule_of('c2')->period_days, 'the BizLMS plugin read this default too');
        $this->assertEquals(7, $this->rule_of('c3')->period_days);
        $this->assertEquals(365, $this->rule_of('c1')->period_days, 'a course with its own duration keeps it');
    }

    // History.

    public function test_every_reset_in_the_log_becomes_one_history_row_at_its_real_time(): void {
        global $DB;
        $this->imported();
        $rule = $this->rule_of('c1');

        [$first, $second] = $this->history_of($this->ids['a']);
        $this->assertEquals(self::t('2024-03-02'), $first->timecreated, 'the time of the reset, not of the import');
        $this->assertEquals(self::t('2025-03-06'), $second->timecreated);
        $this->assertSame('cron', $first->reason);
        $this->assertNull($first->reset_by_userid, 'the scheduled task is nobody');
        $this->assertEquals(self::t('2023-03-01'), $first->previous_timecompleted, 'the completion this reset ended');
        $this->assertEquals(self::t('2024-03-05'), $second->previous_timecompleted);
        $this->assertEquals($rule->id, $first->ruleid);
        $this->assertEquals(1, $first->reset_grades);
        $this->assertEquals(1, $first->reset_attempts, 'the learner attempted the quiz in the cycle');
        $this->assertEquals(0, $first->dryrun);
        $this->assertSame('legacy', $first->source);
        $this->assertEquals(0, $first->time_inferred);

        [$own] = $this->history_of($this->ids['b']);
        $this->assertSame('manual', $own->reason, 'a web reset by the learner themself');
        $this->assertEquals($this->ids['b'], $own->reset_by_userid);
        $this->assertEquals(self::t('2025-01-20'), $own->previous_timecompleted, 'no archived completion: the log saw it complete');

        [$browser] = $this->history_of($this->ids['d']);
        $this->assertSame('legacy', $browser->reason, 'the log cannot tell the reset page from a cron run in a browser');
        $this->assertNull($browser->reset_by_userid, 'nobody is named on a guess');
        $this->assertNull($browser->previous_timecompleted, 'no completion anywhere: shown as a dash');
        $this->assertEquals(1, $browser->reset_attempts, 'the course\'s own quiz setting when no attempt was archived');

        [$deleted] = $this->history_of($this->ids['e']);
        $this->assertSame('cron', $deleted->reason, 'the history of a deleted user is kept (readers filter)');
        $this->assertEquals(self::t('2022-05-01'), $deleted->previous_timecompleted);

        $this->assertSame(16, $DB->count_records(sources::HISTORY),
            'thirteen resets in the log (A 2, B, D, E, F 2, G 2, I 2, J 2) and three inferred (C, H 2)');
        $this->assertSame(0, $DB->count_records(sources::HISTORY, ['source' => 'engine']));

        foreach (['log_incomplete' => 'incomplete_event', 'log_orphan' => 'orphan_user'] as $key => $reason) {
            $map = $this->mapped(sources::EVENT_UNIT, $this->src[$key][1]);
            $this->assertSame('skipped', $map->outcome, $key);
            $this->assertSame($reason, $map->reason, $key);
        }
        $this->assertSame(15, $DB->count_records(legacymap::TABLE, ['sourcetable' => sources::EVENT_UNIT, 'subkey' => '']),
            'every reset row of the log has exactly one map row, and nothing else of the log has');
    }

    public function test_a_cycle_with_no_logged_reset_gets_an_inferred_reset_capped_at_the_next_evidence(): void {
        global $DB;
        $this->imported();
        $inferred = $this->target('cc_c1', 'history');
        $rule = $this->rule_of('c1');

        $this->assertEquals(1, $inferred->time_inferred);
        $this->assertSame('legacy', $inferred->reason);
        $this->assertNull($inferred->reset_by_userid);
        $this->assertSame('legacy', $inferred->source);
        $this->assertEquals($this->ids['c'], $inferred->userid);
        $this->assertEquals($rule->id, $inferred->ruleid);
        $this->assertEquals(self::t('2025-12-01'), $inferred->previous_timecompleted);
        $this->assertEquals(self::t('2026-01-20'), $inferred->timecreated,
            'the next cycle\'s first evidence, not a year after the completion (2026-12-01) and not the import time');
        $this->assertLessThanOrEqual(time(), (int) $inferred->timecreated, 'never in the future');

        // The evidence of the next cycle is not attached to the reset that ended this one.
        $this->assertEquals($inferred->id, $this->target('cmc_c1')->historyid);
        $this->assertEquals($inferred->id, $this->target('cc_c1')->historyid);
        $this->assertEquals(0, $this->target('cmc_c_next')->historyid,
            'nothing ended the next cycle yet: ambiguous rows keep 0 rather than guess');
        $this->assertSame(1, $DB->count_records(sources::HISTORY, ['time_inferred' => 1, 'userid' => $this->ids['c']]));
    }

    public function test_the_log_row_that_turns_up_later_upgrades_the_inferred_reset_and_adds_no_second_one(): void {
        global $DB;
        $this->imported();
        $inferred = $this->target('cc_c1', 'history');
        $before = $DB->count_records(sources::HISTORY);

        $late = $this->log(sources::RESET_EVENT, $this->ids['c'], $this->ids['c1'], self::t('2026-01-18'));
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $this->assertSame($before, $DB->count_records(sources::HISTORY), 'upgraded, not duplicated');
        $upgraded = $DB->get_record(sources::HISTORY, ['id' => $inferred->id], '*', MUST_EXIST);
        $this->assertEquals(0, $upgraded->time_inferred);
        $this->assertEquals(self::t('2026-01-18'), $upgraded->timecreated, 'the real time');
        $this->assertSame('cron', $upgraded->reason);
        $this->assertNull($upgraded->reset_by_userid);
        $this->assertEquals(self::t('2025-12-01'), $upgraded->previous_timecompleted);

        $folded = $this->mapped(sources::EVENT_UNIT, $late);
        $this->assertSame('folded', $folded->outcome);
        $this->assertSame('matched_inferred', $folded->reason);
        $this->assertEquals($inferred->id, $folded->targetid);

        // The evidence follows the new time.
        $this->assertEquals(self::t('2026-01-18'), $this->target('cc_c1')->timecreated, 'archived at the reset time');
        $this->assertEquals($inferred->id, $this->target('cmc_c1')->historyid);
        $this->assertEquals(0, $this->target('cmc_c_next')->historyid, 'still after every reset');
    }

    public function test_a_cycle_reset_without_ever_being_started_is_paired_with_its_own_reset(): void {
        global $DB;
        $this->imported();
        $f = $this->ids['f'];
        [$first, $second] = $this->history_of($f);

        $this->assertEquals(self::t('2024-03-02'), $first->timecreated);
        $this->assertEquals(self::t('2024-06-01'), $second->timecreated);
        $this->assertSame(0, $DB->count_records(sources::HISTORY, ['userid' => $f, 'time_inferred' => 1]),
            'both resets are in the log: none is invented');
        $this->assertSame(2, $DB->count_records(sources::HISTORY, ['userid' => $f]));
        $this->assertFalse($DB->record_exists(legacymap::TABLE, ['sourcetable' => 'local_recompletion_cc',
            'sourceid' => $this->src['cc_f2'][1], 'subkey' => 'history']), 'the second cycle has a reset of its own');

        $this->assertEquals(self::t('2023-03-01'), $first->previous_timecompleted);
        $this->assertNull($second->previous_timecompleted, 'the second cycle was never completed');
        $this->assertSame('cron', $first->reason);
        $this->assertSame('legacy', $second->reason, 'a browser reset by somebody else: the log cannot say which kind');

        // Each archived completion hangs off its own reset, not the earliest one after its (original) enrolment.
        $this->assertEquals($first->id, $this->target('cc_f1')->historyid);
        $this->assertEquals($second->id, $this->target('cc_f2')->historyid);
        $this->assertEquals($second->timecreated, $this->target('cc_f2')->timecreated, 'archived at its reset');
    }

    public function test_a_backdated_completion_does_not_cost_the_cycle_before_it_its_reset(): void {
        global $DB;
        $this->imported();
        // I: the second cycle completed (by the cron's backdating) on a date after the first one completed, with no
        // start. J: the same with the start backdated to that date as well. Both resets are in the log.
        foreach (['i' => 'cc_i', 'j' => 'cc_j'] as $name => $prefix) {
            $user = $this->ids[$name];
            [$first, $second] = $this->history_of($user);

            $this->assertSame(2, $DB->count_records(sources::HISTORY, ['userid' => $user]), $name);
            $this->assertSame(0, $DB->count_records(sources::HISTORY, ['userid' => $user, 'time_inferred' => 1]),
                $name . ': both resets are in the log, none is invented');
            foreach ([1, 2] as $n) {
                $this->assertFalse($DB->record_exists(legacymap::TABLE, ['sourcetable' => sources::CC,
                    'sourceid' => $this->src[$prefix . $n][1], 'subkey' => 'history']),
                    $name . ': cycle ' . $n . ' has a reset of its own and needs no inferred one');
            }

            $this->assertEquals(self::t('2024-03-02'), $first->timecreated, $name);
            $this->assertEquals(self::t('2024-03-03'), $second->timecreated, $name);
            $this->assertSame('cron', $first->reason, $name);
            $this->assertSame('cron', $second->reason, $name);
            $this->assertEquals(self::t('2023-03-01'), $first->previous_timecompleted, $name);
            $this->assertEquals(self::t('2023-06-01'), $second->previous_timecompleted,
                $name . ': the completion of the cycle the second reset ended, not the first cycle\'s');

            // Each archived completion hangs off its own reset.
            $this->assertEquals($first->id, $this->target($prefix . '1')->historyid, $name);
            $this->assertEquals($second->id, $this->target($prefix . '2')->historyid, $name);
            $this->assertEquals($first->timecreated, $this->target($prefix . '1')->timecreated, $name . ': archived at its reset');
            $this->assertEquals($second->timecreated, $this->target($prefix . '2')->timecreated, $name . ': archived at its reset');
        }
    }

    public function test_a_reset_with_no_archived_cycle_does_not_borrow_an_earlier_cycles_completion(): void {
        $this->imported();
        [$first, $second] = $this->history_of($this->ids['g']);
        $this->assertEquals(self::t('2023-03-01'), $first->previous_timecompleted, 'its own archived completion');
        $this->assertNull($second->previous_timecompleted,
            'the logged completion of 2023-03-01 belongs to the cycle the first reset ended');
        $this->assertEquals($first->id, $this->target('cc_g1')->historyid);
    }

    public function test_an_inferred_reset_is_never_dated_before_the_cycle_ahead_of_it_ended(): void {
        $this->imported();
        [$first, $second] = $this->history_of($this->ids['h']);
        $this->assertEquals(1, $first->time_inferred);
        $this->assertEquals(1, $second->time_inferred);
        $this->assertEquals($first->id, $this->target('cc_h1', 'history')->id);
        $this->assertEquals($second->id, $this->target('cc_h2', 'history')->id);

        // The first cycle: completion plus the one-year rule period. The second never started: its enrolment is the
        // ORIGINAL one (2023-01-10), which is evidence of nothing; it can only be dated after the first cycle ended.
        $this->assertEquals(self::t('2023-03-01') + 31536000, $first->timecreated);
        $this->assertGreaterThanOrEqual((int) $first->timecreated, (int) $second->timecreated);
        $this->assertLessThanOrEqual(time(), (int) $second->timecreated, 'never in the future');
        $this->assertNull($second->previous_timecompleted);

        $this->assertEquals($first->id, $this->target('cc_h1')->historyid);
        $this->assertEquals($second->id, $this->target('cc_h2')->historyid);
        $this->assertEquals($first->id, $this->target('cmc_h1')->historyid, 'its own cycle\'s activity');
    }

    // Archive.

    public function test_every_archived_row_is_attached_to_the_reset_that_ended_its_cycle(): void {
        $this->imported();
        [$r1, $r2] = $this->history_of($this->ids['a']);

        $first = ['cc_a1', 'cc_cc_a1', 'cmc_a1', 'cmc_a_old', 'qa_a1', 'qa_a_old', 'qg_a1', 'qg_a_old', 'sst_1', 'sst_2',
            'sst_old', 'qr_1', 'qr_bool', 'qr_text', 'qr_date', 'qr_m', 'qr_other', 'qr_rank', 'qr_single'];
        if ((int) $this->target('ltia_a')->courseid > 0) {
            // An LTI row has no course column; without the enrolment LTI tables its course is unknown and so is its cycle.
            $first[] = 'ltia_a';
        }
        foreach ($first as $key) {
            $this->assertEquals($r1->id, $this->target($key)->historyid, $key . ' belongs to the first cycle');
        }
        foreach (['cc_a2', 'cc_cc_a2', 'cmc_a2', 'qa_a2', 'qg_a2', 'sst_4', 'sst_5'] as $key) {
            $this->assertEquals($r2->id, $this->target($key)->historyid, $key . ' belongs to the second cycle');
        }
        $this->assertEquals($r1->timecreated, $this->target('cmc_a1')->timecreated, 'archived at the reset time');
        $this->assertEquals($r2->timecreated, $this->target('qa_a2')->timecreated);

        [$own] = $this->history_of($this->ids['b']);
        $this->assertEquals($own->id, $this->target('qa_b')->historyid);
        $this->assertEquals(0, $this->target('qr_anon')->historyid, 'an anonymous response has no learner to tie to a reset');
    }

    public function test_old_format_rows_take_their_course_from_the_activity_the_quiz_or_the_scorm(): void {
        global $DB;
        $this->imported();
        $course = $this->ids['c1'];
        foreach (['cmc_a_old', 'qa_a_old', 'qg_a_old', 'sst_old'] as $key) {
            $this->assertEquals($course, $this->target($key)->courseid, $key . ' was archived with course 0');
        }
        $this->assertEquals($this->ids['cm_quiz'], $this->target('qa_a1')->cmid, 'the quiz\'s course module');
        $this->assertEquals($this->ids['quiz'], $this->target('qa_a1')->instanceid);
        if ($DB->get_manager()->table_exists('enrol_lti_tools')) {
            $this->assertEquals($course, $this->target('ltia_a')->courseid, 'an LTI row has no course column: its tool\'s context');
        }
    }

    public function test_the_promoted_columns_of_each_item_type(): void {
        $this->imported();

        $cc = $this->target('cc_a1');
        $this->assertSame('course_completion', $cc->itemtype);
        $this->assertSame('complete', $cc->state);
        $this->assertEquals(self::t('2023-03-01'), $cc->timeevent);

        $crit = $this->target('cc_cc_a1');
        $this->assertSame('criteria_completion', $crit->itemtype);
        $this->assertEquals(11, $crit->instanceid);
        $this->assertEquals(80.5, (float) $crit->grade);

        $act = $this->target('cmc_a1');
        $this->assertSame('activity_completion', $act->itemtype);
        $this->assertEquals($this->ids['cm_page'], $act->cmid);
        $this->assertSame('complete', $act->state);
        $this->assertSame('complete_pass', $this->target('cmc_a_old')->state);
        $this->assertEquals($this->ids['admin'], json_decode($act->payload, true)['overrideby'], 'the overrider stays in the payload');

        $qa = $this->target('qa_a1');
        $this->assertSame('quiz_attempt', $qa->itemtype);
        $this->assertSame('finished', $qa->state);
        $this->assertEquals(15.0, (float) $qa->grade, 'raw marks: a reader scales them by the quiz');
        $this->assertEquals(7001, json_decode($qa->payload, true)['uniqueid'], 'the question usage link is kept');

        $this->assertEquals(7.5, (float) $this->target('qg_a1')->grade);

        $status = $this->target('sst_1');
        $this->assertSame('scorm_track', $status->itemtype);
        $this->assertSame('cmi.core.lesson_status', $status->itemkey);
        $this->assertSame('completed', $status->state);
        $this->assertNull($status->grade);
        $this->assertEquals(95.0, (float) $this->target('sst_2')->grade, 'a numeric raw score');
        $this->assertNull($this->target('sst_5')->grade, 'a raw score that is not a number');
        $this->assertSame('n/a', json_decode($this->target('sst_5')->payload, true)['value'], 'the raw value stays in the payload');

        $lti = $this->target('ltia_a');
        $this->assertSame('lti_grade', $lti->itemtype);
        $this->assertEquals(88.0, (float) $lti->grade);
        $this->assertEquals(self::t('2024-02-01'), $lti->timeevent);
    }

    public function test_answers_hang_off_their_response_and_an_orphan_is_reported_not_dropped(): void {
        global $DB;
        $this->imported();
        $response = $this->target('qr_1');
        $this->assertSame('questionnaire_response', $response->itemtype);
        $this->assertSame('complete', $response->state);
        $this->assertEquals(7, $response->instanceid);
        $this->assertEquals(501, json_decode($response->payload, true)['originalresponseid'], 'the join key is kept');

        foreach (['qr_bool', 'qr_text', 'qr_date', 'qr_m', 'qr_other', 'qr_rank', 'qr_single'] as $key) {
            $answer = $this->target($key);
            $this->assertSame('questionnaire_answer', $answer->itemtype, $key);
            $this->assertEquals($response->id, $answer->parentid, $key . ' points at its response row');
            $this->assertEquals($this->ids['a'], $answer->userid, $key . ' takes the learner from its response');
            $this->assertEquals($this->ids['c1'], $answer->courseid, $key);
        }
        $this->assertSame('70', $this->target('qr_text')->itemkey);
        $this->assertSame('Free text answer', json_decode($this->target('qr_text')->payload, true)['response']);
        $this->assertSame('y', $this->target('qr_bool')->state);
        $this->assertSame('3', $this->target('qr_rank')->state);
        $this->assertEquals(2.0, (float) $this->target('qr_rank')->grade, 'the rank value');

        $orphan = $this->mapped('local_recompletion_qr_single', $this->src['qr_single_orphan'][1]);
        $this->assertSame('skipped', $orphan->outcome);
        $this->assertSame('orphan_response', $orphan->reason);

        $anon = $this->target('qr_anon');
        $this->assertEquals(0, $anon->userid, 'an anonymous response');
        $this->assertSame('skipped', $this->mapped('local_recompletion_qr', $this->src['qr_orphan_user'][1])->outcome);

        $this->assertSame(0, $DB->count_records_select(sources::ARCHIVE,
            'parentid IS NOT NULL AND parentid NOT IN (SELECT id FROM {' . sources::ARCHIVE . '} WHERE parentid IS NULL)'));
    }

    public function test_the_payload_is_the_source_row_exactly_and_every_row_has_an_outcome(): void {
        global $DB;
        $this->imported();
        $compared = 0;
        foreach ($this->src as $key => [$table, $id]) {
            if (strpos($table, 'local_recompletion_') !== 0 || $table === 'local_recompletion_config') {
                continue;
            }
            $map = $this->mapped($table, $id);
            if ($map->outcome !== 'imported') {
                continue;
            }
            $archive = $DB->get_record(sources::ARCHIVE, ['id' => $map->targetid], '*', MUST_EXIST);
            $source = (array) $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
            $this->assertEquals($source, json_decode($archive->payload, true), $key . ': the payload is the row, every column');
            $compared++;
        }
        $this->assertGreaterThan(25, $compared);

        // Every source row of every legacy table has exactly one primary map row.
        foreach (sources::legacy_tables() as $table) {
            if ($table === sources::CONFIG) {
                continue;
            }
            $this->assertSame($DB->count_records($table),
                $DB->count_records(legacymap::TABLE, ['sourcetable' => $table, 'subkey' => '']), $table);
        }
        $this->assertSame(43, $DB->count_records(sources::ARCHIVE), 'the archive is complete');
    }

    public function test_a_teachers_preview_attempt_stays_in_the_legacy_table_unless_the_owner_says_otherwise(): void {
        global $DB;
        $this->imported();
        $map = $this->mapped('local_recompletion_qa', $this->src['qa_preview'][1]);
        $this->assertSame('archived', $map->outcome);
        $this->assertSame('preview_attempt', $map->reason);
        $this->assertSame(0, $DB->count_records(sources::ARCHIVE, ['itemtype' => 'quiz_attempt', 'userid' => $this->ids['admin']]));
        $this->assertSame(43, $DB->count_records(sources::ARCHIVE));
    }

    public function test_the_owner_can_choose_to_import_preview_attempts(): void {
        global $DB;
        $this->decisionoverride = ['recompletion.preview_attempts' => 'import'];
        $this->imported();
        $this->assertSame('imported', $this->mapped('local_recompletion_qa', $this->src['qa_preview'][1])->outcome);
        $this->assertSame(44, $DB->count_records(sources::ARCHIVE));
    }

    // Owner decisions and reasons.

    public function test_the_signed_decisions_file_answers_every_decision_the_importer_asks_for(): void {
        $path = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        $signed = decisions::load($path);
        foreach ((new importer())->decisions() as $decision) {
            $this->assertTrue($signed->has($decision->key), $decision->key . ' is accepted in the signed file');
            $this->assertContains($signed->get($decision->key), $decision->allowed, $decision->key . ' has a value the importer implements');
        }
        $this->assertFalse($signed->get('recompletion.enable_imported_rules'), 'no rule starts on its own at cutover');
    }

    public function test_a_decision_the_importer_does_not_implement_blocks_the_feature(): void {
        $this->decisionoverride = ['recompletion.enable_imported_rules' => true];
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:recompletion.enable_imported_rules',
            implode(' ', $result['blockers']));
    }

    public function test_rows_nobody_can_be_blamed_for_are_reported_until_the_owner_accepts_them(): void {
        $result = $this->imported();
        $this->assertSame(2, $result['exit'], 'parity exits 2 until the owner accepts what was skipped');
        $this->assertSame(['recompletion:incomplete_event=1', 'recompletion:orphan_response=1',
            'recompletion:orphan_user=4'], $result['unproven']);

        $this->contract_clear_import($this->contract_importer());
        $this->accepted = ['recompletion:incomplete_event', 'recompletion:orphan_response', 'recompletion:orphan_user'];
        [$again] = $this->contract_run(true);
        $this->assertSame(0, $again['exit']);
        $this->assertSame([], $again['unproven']);
    }

    // What the import must not do.

    public function test_nothing_outside_the_three_targets_changes_and_the_engine_finds_nothing_to_run(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $core = ['course_completions', 'course_completion_crit_compl', 'course_modules_completion', 'quiz_attempts',
            'quiz_grades', 'grade_grades', 'scorm_attempt', 'scorm_scoes_value', 'user_enrolments', 'role_assignments'];
        $legacy = sources::legacy_tables();
        $count = static function (array $tables) use ($DB): array {
            $out = [];
            foreach ($tables as $table) {
                $out[$table] = $DB->get_manager()->table_exists($table) ? $DB->count_records($table) : -1;
            }
            return $out;
        };
        $coreBefore = $count($core);
        $legacyBefore = $count($legacy);
        $logBefore = $DB->count_records(sources::LOG);

        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);

        $this->assertSame($coreBefore, $count($core), 'archived rows are never written back into live core tables');
        $this->assertSame($legacyBefore, $count($legacy), 'the legacy tables are the archive and stay as they were');
        $this->assertSame($logBefore, $DB->count_records(sources::LOG), 'the import fires no event');

        // The engine runs every enabled rule; there is none, so a night\'s run resets nobody.
        $history = $DB->count_records(sources::HISTORY);
        $totals = recompletion_engine::run_all(false);
        $this->assertSame(0, $totals['rules_run']);
        $this->assertSame(0, $totals['reset']);
        $this->assertSame($history, $DB->count_records(sources::HISTORY));
        $this->assertSame($coreBefore, $count($core));
    }

    public function test_the_imported_history_cannot_trigger_a_reset_of_the_learner(): void {
        global $DB;
        $this->imported();
        // The engine skips a learner with a history row from the last 24 hours. The import must not stamp the
        // import time on any row, or it would silence a real reset the night after cutover.
        $this->assertSame(0, $DB->count_records_select(sources::HISTORY, 'timecreated > :recent AND source = :legacy',
            ['recent' => time() - DAYSECS, 'legacy' => sources::LEGACY]));
        $this->assertSame(0, $DB->count_records_select(sources::HISTORY, 'dryrun <> 0'));
    }

    // Verify.

    public function test_verify_passes_on_a_finished_import_and_names_what_is_broken(): void {
        global $DB;
        $this->imported();
        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $clean = $runner->verify([]);
        $this->assertSame(0, $clean['exit'], implode('; ', $clean['failures']['recompletion'] ?? []));
        $this->assertSame([], $clean['failures']['recompletion']);

        // The log's own cleanup deletes old rows once the site runs: fewer reset events than were imported is not a
        // failure (an event the import never accounted for is: see the next test).
        $DB->delete_records(sources::LOG, ['id' => $this->src['log_ra1'][1]]);
        $purged = (new runner(['decisions' => $this->contract_decisions()]))->verify([]);
        $this->assertSame([], $purged['failures']['recompletion'], 'a purged log row is expected');

        // An answer whose response row has gone.
        $DB->delete_records(sources::ARCHIVE, ['id' => $this->target('qr_1')->id]);
        // An imported reset dated in the future.
        $DB->set_field(sources::HISTORY, 'timecreated', time() + DAYSECS, ['id' => $this->target('log_rb')->id]);
        // A reset that was recorded as a dry run.
        $DB->set_field(sources::HISTORY, 'dryrun', 1, ['id' => $this->target('log_rd')->id]);

        $broken = (new runner(['decisions' => $this->contract_decisions()]))->verify([]);
        $this->assertSame(1, $broken['exit']);
        $lines = implode(' ', $broken['failures']['recompletion']);
        $this->assertStringContainsString('archive_answers_without_a_response:', $lines);
        $this->assertStringContainsString('imported_history_in_the_future_or_dry_run:2', $lines);
    }

    public function test_verify_names_a_reset_event_the_import_never_accounted_for(): void {
        global $DB;
        $this->imported();
        // A reset event the log holds and the import has no map row for: forget the map row of one imported reset.
        $this->assertSame(1, $DB->count_records(legacymap::TABLE, ['sourcetable' => sources::EVENT_UNIT,
            'sourceid' => $this->src['log_rd'][1], 'subkey' => '']));
        $DB->delete_records(legacymap::TABLE, ['sourcetable' => sources::EVENT_UNIT,
            'sourceid' => $this->src['log_rd'][1], 'subkey' => '']);

        $broken = (new runner(['decisions' => $this->contract_decisions()]))->verify([]);
        $this->assertSame(1, $broken['exit']);
        $this->assertStringContainsString('accounting:' . sources::EVENT_UNIT . ':',
            implode(' ', $broken['failures']['recompletion']), 'more reset events in the log than were accounted for');
    }

    // Reading what was imported.

    public function test_a_dry_run_of_a_full_seed_reports_what_an_apply_will_do_without_writing(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $writes = $DB->perf_get_writes();
        [$result, $report] = $this->contract_run(false);
        $this->assertContains($result['exit'], [0, 2]);
        $this->assertSame($writes, $DB->perf_get_writes());
        $this->assertSame(0, $DB->count_records(sources::ARCHIVE));
        $features = $report->to_array()['features'];
        $this->assertSame('simulated', $features['recompletion']['status']);
    }
}
