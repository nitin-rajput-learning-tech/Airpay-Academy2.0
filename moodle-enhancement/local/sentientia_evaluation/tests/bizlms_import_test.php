<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_evaluation\bizlms\importer;
use local_sentientia_evaluation\tests\bizlms\parent_stub_importer;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The evaluation feature of the BizLMS import (ADR-032, mapping doc section 18): local_evaluation ->
 * local_sentientia_evaluation.
 *
 * The importer contract (tests/bizlms of local_sentientia_platform, "Test approach" 4) runs against a seed shaped
 * like production. The tests after it check the column maps, the reasons, the anonymity rules and the tenant
 * rule.
 *
 * The world. Organisations /1, /1/5, /77 and /177. Nine forms:
 *
 *   form  what it is                                  tenant path (no dependency rows)    imported as
 *   1     named, self evaluation, two assignees dup   /1/5, exact                         archived, manual
 *   2     anonymous                                   /77, exact                          anonymous
 *   3     supervisor evaluation (SP), named           /1/5, exact                         subject kept
 *   4     trainer feedback of classroom 7             none (classroom not mapped here)    pathless
 *   5     soft deleted                                -                                   archived, deleted_form
 *   6     root only, in costcenterid 77               /77, fallback:costcenterid          archived, manual
 *   7     named now, one old anonymous answer         /1/5, exact                         anonymous (sticky)
 *   8     path /1/5/999 (no such organisation)        /1/5, walked_up                     archived, manual
 *   9     nothing to attribute it by                  none                                pathless
 *
 * Items, completions, values, assignments and templates follow the forms; seed_world() says what each is for.
 * The classroom and program features are stood in for by parent_stub_importer (they are separate deliverables),
 * so their map rows are not in the contract seed; the tests that need them call seed_parents().
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @covers     \local_sentientia_evaluation\bizlms\importer
 * @covers     \local_sentientia_evaluation\bizlms\form_step
 * @covers     \local_sentientia_evaluation\bizlms\template_step
 * @covers     \local_sentientia_evaluation\bizlms\question_step
 * @covers     \local_sentientia_evaluation\bizlms\dependency_step
 * @covers     \local_sentientia_evaluation\bizlms\assignment_step
 * @covers     \local_sentientia_evaluation\bizlms\response_step
 * @covers     \local_sentientia_evaluation\bizlms\value_step
 * @covers     \local_sentientia_evaluation\bizlms\form_facts
 *
 * @group local_sentientia_evaluation
 * @group bizlms_import
 * @group tenant_isolation
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** Timestamp base of the seed. */
    private const T0 = 1700000000;

    /** The four sentinel strings the importer writes. */
    private const NEVER = '__bizlms_never__';

    /** @var array<string, int> Named user ids of the seeded world. */
    private array $u = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/evaluation.install.xml'];
    }

    protected function contract_importer(): framework_importer {
        return new importer();
    }

    /**
     * The features the importer depends on are separate deliverables; their stand-ins let the registry accept it.
     *
     * @return framework_importer
     */
    protected function contract_begin(): framework_importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([
            new parent_stub_importer('org', 'local_costcenter'),
            new parent_stub_importer('classroom', 'local_classroom'),
            new parent_stub_importer('program', 'local_program'),
            $importer,
        ]);
        return $importer;
    }

    /** Every needs-owner reason the importer declares. */
    private const NEEDS_OWNER = [
        'evaluation:value_not_valid', 'evaluation:duplicate_value', 'evaluation:orphan_form',
        'evaluation:orphan_template', 'evaluation:orphan_item', 'evaluation:orphan_user',
        'evaluation:orphan_assignee', 'evaluation:orphan_completed', 'evaluation:no_timestamp',
        'evaluation:unmapped_enum', 'evaluation:foreign_item', 'evaluation:missing_item',
    ];

    /**
     * The nine choices the importer declares (evaluation.sticky_anonymity since EV-16,
     * evaluation.tenant_editor_fallback since EV-TENANT), with the given needs-owner reasons accepted.
     *
     * @param string[] $accepted
     * @return decisions
     */
    private function decisions_accepting(array $accepted): decisions {
        return decisions::from_array($this->declared_choices() + [
            'accepted_reasons' => array_values($accepted),
        ]);
    }

    /**
     * The nine choices the importer declares, each with the one value it implements.
     *
     * @return array<string, mixed>
     */
    private function declared_choices(): array {
        return [
            'tenant.unresolved.evaluation' => 'pathless',
            'evaluation.tenant_editor_fallback' => 'not_used',
            'evaluation.open_forms' => 'archived',
            'evaluation.multichoicerated' => 'multichoice',
            'evaluation.sp_anonymous_subject' => 'hidden',
            'evaluation.sticky_anonymity' => 'whole_form',
            'evaluation.legacy_anonymous_linkage' => 'untouched_pending_legacy_privacy_adr',
            'evaluation.trainer_feedback_form_names' => 'keep_bizlms_name',
            'evaluation.imported_forms_read_only' => true,
        ];
    }

    /**
     * The owner's choices: the nine the importer declares, and every needs-owner reason accepted, so a run that
     * loses nothing unexpectedly exits 0.
     *
     * @return decisions
     */
    protected function contract_decisions(): decisions {
        return $this->decisions_accepting(self::NEEDS_OWNER);
    }

    protected function contract_user_columns(): array {
        return [importer::T_RESPONSES => ['subject_userid']];
    }

    protected function contract_mutate_source(): void {
        // A new row changes the count and the max id on every engine, so detection does not depend on the CRC.
        $this->put_form(99, ['name' => 'Late arrival']);
    }

    protected function contract_collision(): ?array {
        // Somebody else's evaluation at the BizLMS id 1. No script ever copied a form header, so nothing at a
        // legacy id is an adoptable copy: the feature stops.
        return ['table' => importer::T_FORMS, 'row' => (object) [
            'id' => 1, 'name' => 'Somebody else', 'description' => null, 'kirkpatrick_level' => 1,
            'trigger_event' => 'manual', 'days_after' => 0, 'costcenterid' => 0, 'open_path' => null, 'status' => 0,
            'anonymous' => 0, 'timeopen' => 0, 'timeclose' => 0, 'multiple_submit' => 0,
            'notify_admin_on_response' => 0, 'timecreated' => 5, 'timemodified' => 5,
        ]];
    }

    // The seed.

    /**
     * Insert one local_evaluations row with BizLMS's own defaults for what the caller leaves out.
     *
     * @param int $id
     * @param array<string, mixed> $values
     * @return void
     */
    private function put_form(int $id, array $values = []): void {
        global $DB;
        $DB->import_record('local_evaluations', (object) ($values + [
            'id' => $id, 'course' => 0, 'name' => 'Form ' . $id, 'intro' => null, 'introformat' => 1, 'anonymous' => 2,
            'email_notification' => 1, 'multiple_submit' => 1, 'autonumbering' => 1, 'site_after_submit' => null,
            'page_after_submit' => null, 'page_after_submitformat' => 0, 'publish_stats' => 0, 'timeopen' => 0,
            'timeclose' => 0, 'timemodified' => self::T0 + $id * 100, 'usermodified' => 0, 'visible' => 1,
            'evaluationtype' => 0, 'costcenterid' => null, 'departmentid' => null, 'subdepartment' => null,
            'type' => 0, 'instance' => 0, 'plugin' => 'site', 'completionsubmit' => 0, 'evaluationmode' => 'SE',
            'open_path' => '0', 'deleted' => 0,
        ]));
    }

    /**
     * @param int $id
     * @param array<string, mixed> $values
     * @return void
     */
    private function put_item(int $id, array $values): void {
        global $DB;
        $DB->import_record('local_evaluation_item', (object) ($values + [
            'id' => $id, 'evaluation' => 0, 'template' => 0, 'name' => 'Item ' . $id, 'label' => '',
            'presentation' => '', 'typ' => 'textfield', 'hasvalue' => 1, 'position' => 0, 'required' => 0,
            'dependitem' => 0, 'dependvalue' => '', 'options' => '',
        ]));
    }

    /**
     * @param int $id
     * @param int $form
     * @param int $user
     * @param int $time
     * @param array<string, mixed> $values
     * @return void
     */
    private function put_completed(int $id, int $form, int $user, int $time, array $values = []): void {
        global $DB;
        $DB->import_record('local_evaluation_completed', (object) ($values + [
            'id' => $id, 'evaluation' => $form, 'userid' => $user, 'timemodified' => $time, 'random_response' => 0,
            'anonymous_response' => 0, 'courseid' => 0, 'evaluatedby' => 0,
        ]));
    }

    /**
     * @param int $id
     * @param int $completed
     * @param int $item
     * @param string $value
     * @param int $courseid Part of BizLMS's unique key, so the same item can hold two values.
     * @return void
     */
    private function put_value(int $id, int $completed, int $item, string $value, int $courseid = 0): void {
        global $DB;
        $DB->import_record('local_evaluation_value', (object) [
            'id' => $id, 'course_id' => $courseid, 'item' => $item, 'completed' => $completed, 'tmp_completed' => 0,
            'value' => $value,
        ]);
    }

    /**
     * @param int $id
     * @param int $form
     * @param int $user
     * @param int $creator
     * @param int $created
     * @return void
     */
    private function put_assignee(int $id, int $form, int $user, int $creator, int $created): void {
        global $DB;
        $DB->import_record('local_evaluation_users', (object) [
            'id' => $id, 'evaluationid' => $form, 'userid' => $user, 'creatorid' => $creator,
            'timemodified' => $created + 1, 'timecreated' => $created, 'status' => 0,
        ]);
    }

    /**
     * @param int $id
     * @param array<string, mixed> $values
     * @return void
     */
    private function put_template(int $id, array $values): void {
        global $DB;
        $DB->import_record('local_evaluation_template', (object) ($values + [
            'id' => $id, 'course' => 0, 'ispublic' => 0, 'name' => 'Template ' . $id, 'costcenterid' => null,
            'departmentid' => null, 'open_path' => null,
        ]));
    }

    /**
     * The organisations the tenant resolver validates against, the people, and everything BizLMS kept.
     *
     * Counts the tests rely on: 9 forms (5 is soft deleted), 32 items, 19 completions, 27 values, 10 assignee
     * rows, 3 templates.
     *
     * @return void
     */
    protected function contract_seed(): void {
        global $DB;
        $this->ensure_bizlms_schema();
        $t = self::T0;
        $gen = $this->getDataGenerator();

        foreach ([[1, 'Airpay', '/1', 0, 1], [5, 'Payments', '/1/5', 1, 2], [77, 'Public', '/77', 0, 1],
                [177, 'ZEEA', '/177', 0, 1]] as [$id, $name, $path, $parent, $depth]) {
            $DB->import_record('local_sentientia_org', (object) [
                'id' => $id, 'fullname' => $name, 'shortname' => strtolower($name), 'parentid' => $parent,
                'path' => $path, 'depth' => $depth, 'visible' => 1, 'sortorder' => $id * 10,
                'timecreated' => $t, 'timemodified' => $t,
            ]);
        }

        // People. The open_path is where the tenant rule reads a person's tenant from.
        $this->u = [];
        foreach (['u1' => '/1/5', 'u2' => '/77', 'sup' => '/1/5', 'susp' => '/1/5', 'del' => '/1/5',
                'adm1' => '/1', 'adm177' => '/177'] as $key => $path) {
            $user = $gen->create_user(['username' => 'evimp_' . $key]);
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
            $this->u[$key] = (int) $user->id;
        }
        $DB->set_field('user', 'suspended', 1, ['id' => $this->u['susp']]);
        $DB->set_field('user', 'deleted', 1, ['id' => $this->u['del']]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        foreach (['adm1', 'adm177'] as $key) {
            role_assign($managerid, $this->u[$key], \context_system::instance()->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
        [$u1, $u2, $sup] = [$this->u['u1'], $this->u['u2'], $this->u['sup']];

        // Forms.
        $this->put_form(1, ['open_path' => '/1/5']);
        $this->put_form(2, ['anonymous' => 1, 'open_path' => '/77', 'timeclose' => $t + 5000]);
        $this->put_form(3, ['evaluationmode' => 'SP', 'open_path' => ' /1/5/ ',
            'intro' => '<p>Please rate your team member @@PLUGINFILE@@/x.png</p>', 'introformat' => 1]);
        $this->put_form(4, ['plugin' => 'classroom', 'instance' => 7]);
        $this->put_form(5, ['deleted' => 1]);
        $this->put_form(6, ['costcenterid' => '77']);
        $this->put_form(7, ['open_path' => '/1/5']);
        $this->put_form(8, ['open_path' => '/1/5/999']);
        $this->put_form(9);

        // Items of form 1: every kind there is, and four conditional ones.
        $this->put_item(101, ['evaluation' => 1, 'typ' => 'multichoice', 'position' => 1, 'required' => 1,
            'name' => '<p>How was the <b>venue</b>?</p>', 'presentation' => 'r>>>>>Good|<b>B</b>|Bad<<<<<1']);
        $this->put_item(102, ['evaluation' => 1, 'typ' => 'multichoice', 'position' => 2,
            'presentation' => 'c>>>>>A|B|C<<<<<0']);
        $this->put_item(103, ['evaluation' => 1, 'typ' => 'multichoice', 'position' => 3, 'presentation' => 'd>>>>>X|Y']);
        $this->put_item(104, ['evaluation' => 1, 'typ' => 'multichoicerated', 'position' => 4,
            'presentation' => 'r>>>>>1####Poor|2####Fair|5####Great<<<<<1']);
        $this->put_item(105, ['evaluation' => 1, 'typ' => 'numeric', 'position' => 5, 'presentation' => '1.5|9.5']);
        $this->put_item(106, ['evaluation' => 1, 'typ' => 'textfield', 'position' => 6, 'presentation' => '40|255']);
        $this->put_item(107, ['evaluation' => 1, 'typ' => 'textarea', 'position' => 7]);
        foreach ([108 => 'info', 109 => 'label', 110 => 'pagebreak', 111 => 'captcha'] as $id => $typ) {
            $this->put_item($id, ['evaluation' => 1, 'typ' => $typ, 'position' => $id - 100, 'hasvalue' => 0]);
        }
        $this->put_item(112, ['evaluation' => 1, 'typ' => 'multichoice', 'position' => 12,
            'presentation' => 'r>>>>>P|Q<<<<<1', 'dependitem' => 101, 'dependvalue' => 'B']);
        $this->put_item(113, ['evaluation' => 1, 'typ' => 'multichoice', 'position' => 13,
            'presentation' => 'r>>>>>P|Q<<<<<1', 'dependitem' => 101, 'dependvalue' => '']);
        // Depends on an item with a HIGHER id, which the first pass cannot have written yet.
        $this->put_item(114, ['evaluation' => 1, 'typ' => 'textfield', 'position' => 14, 'dependitem' => 120,
            'dependvalue' => 'Yes']);
        // Depends on an item that does not exist.
        $this->put_item(115, ['evaluation' => 1, 'typ' => 'textfield', 'position' => 15, 'dependitem' => 9999,
            'dependvalue' => 'Z']);
        $this->put_item(120, ['evaluation' => 1, 'typ' => 'multichoice', 'position' => 16,
            'presentation' => 'r>>>>>Yes|No<<<<<1']);
        // One or two items for every other form.
        $this->put_item(201, ['evaluation' => 2, 'typ' => 'multichoice', 'position' => 1, 'presentation' => 'r>>>>>Yes|No<<<<<1']);
        $this->put_item(202, ['evaluation' => 2, 'typ' => 'textfield', 'position' => 2]);
        $this->put_item(301, ['evaluation' => 3, 'typ' => 'multichoice', 'position' => 1, 'presentation' => 'r>>>>>1|2|3<<<<<1']);
        $this->put_item(302, ['evaluation' => 3, 'typ' => 'numeric', 'position' => 2, 'presentation' => '0|10']);
        foreach ([401 => 4, 501 => 5, 601 => 6, 801 => 8, 901 => 9] as $id => $form) {
            $this->put_item($id, ['evaluation' => $form, 'typ' => 'textfield', 'position' => 1]);
        }
        $this->put_item(701, ['evaluation' => 7, 'typ' => 'multichoice', 'position' => 1, 'presentation' => 'r>>>>>A|B<<<<<1']);
        // An item of a form that does not exist, and an item that belongs to nothing.
        $this->put_item(950, ['evaluation' => 9999]);
        $this->put_item(951, []);
        // Template items: a choice, a text, a layout item, and one of a template that does not exist.
        $this->put_item(960, ['template' => 1, 'typ' => 'textfield', 'position' => 2, 'name' => 'T text']);
        $this->put_item(961, ['template' => 1, 'typ' => 'multichoice', 'position' => 1, 'name' => 'T choice',
            'presentation' => 'r>>>>>Y|N<<<<<1']);
        $this->put_item(962, ['template' => 1, 'typ' => 'info', 'position' => 3, 'hasvalue' => 0]);
        $this->put_item(963, ['template' => 99, 'typ' => 'textfield']);

        // Templates: one in an organisation, one by root only, one nobody can place.
        $this->put_template(1, ['ispublic' => 1, 'name' => 'Post-training', 'open_path' => '/1/5']);
        $this->put_template(2, ['name' => 'Private', 'open_path' => '0', 'costcenterid' => '77']);
        $this->put_template(3, ['name' => 'Nowhere', 'open_path' => '0']);

        // Who was asked to answer. (1, u1) is asked twice: the earlier row (id 2, by sup) wins.
        $this->put_assignee(1, 1, $u1, $this->u['adm1'], $t + 20);
        $this->put_assignee(2, 1, $u1, $sup, $t + 10);
        $this->put_assignee(3, 1, $this->u['susp'], $this->u['adm1'], $t + 30);
        $this->put_assignee(4, 1, $this->u['del'], $this->u['adm1'], $t + 40);
        $this->put_assignee(5, 1, 99999, $this->u['adm1'], $t + 45);
        $this->put_assignee(6, 2, $u2, $this->u['adm177'], $t + 50);
        $this->put_assignee(7, 3, $u1, $this->u['adm1'], $t + 60);
        $this->put_assignee(8, 4, $u1, $sup, $t + 70);
        $this->put_assignee(9, 5, $u1, $sup, $t + 80);
        $this->put_assignee(10, 9999, $u1, $sup, $t + 90);

        // Completions.
        $this->put_completed(1001, 1, $u1, $t + 1000, ['anonymous_response' => 2]);
        $this->put_completed(1002, 1, $u1, $t + 1100, ['anonymous_response' => 2]);
        $this->put_completed(1003, 1, $u2, $t + 1200);   // u2 was never assigned form 1.
        $this->put_completed(1004, 1, $u2, $t + 1300);   // ... and answered it twice.
        $this->put_completed(1005, 1, 88888, $t + 1400); // a user that is gone.
        $this->put_completed(1006, 1, $u1, 0);           // no time of its own.
        $this->put_completed(2001, 2, $u2, $t + 2000, ['anonymous_response' => 1]);
        $this->put_completed(2002, 2, $u2, $t + 2100);   // the form is anonymous, the row does not say so.
        $this->put_completed(2003, 2, 0, $t + 2200);     // a guest.
        $this->put_completed(3001, 3, $u1, $t + 3000, ['evaluatedby' => $sup, 'anonymous_response' => 2]);
        $this->put_completed(3002, 3, $u1, $t + 3100);   // before evaluatedby existed.
        $this->put_completed(4001, 4, $u1, $t + 4000);
        $this->put_completed(5001, 5, $u1, $t + 5000);
        $this->put_completed(6001, 6, $u2, $t + 6000);
        $this->put_completed(7001, 7, $u1, $t + 7000, ['anonymous_response' => 1]);
        $this->put_completed(7002, 7, $u2, $t + 7100, ['anonymous_response' => 2]); // named (2), but the form promised anonymity.
        $this->put_completed(8001, 8, $u1, $t + 8000);
        $this->put_completed(9001, 9, $u1, $t + 9000);
        $this->put_completed(9999, 9999, $u1, $t + 1);

        // Values.
        $this->put_value(1, 1001, 101, '2');
        $this->put_value(2, 1001, 102, '1|3');
        $this->put_value(3, 1001, 103, '2');
        $this->put_value(4, 1001, 104, '3');
        $this->put_value(5, 1001, 105, '7.25');
        $this->put_value(6, 1001, 106, 'Tom &amp; Jerry &#039;x&#039;');
        $this->put_value(7, 1001, 107, '');
        $this->put_value(8, 1002, 101, '9');             // there is no ninth option.
        $this->put_value(9, 1002, 112, '1');
        $this->put_value(10, 1002, 102, '2');
        $this->put_value(11, 1002, 102, '3', 5);         // a second value for the same item.
        $this->put_value(12, 1002, 108, 'x');            // a layout item.
        $this->put_value(13, 1002, 201, '1');            // an item of another form.
        $this->put_value(14, 1003, 101, '1');
        $this->put_value(15, 2001, 201, '1');
        $this->put_value(16, 2001, 202, 'Anon text');
        $this->put_value(17, 2002, 201, '2');
        $this->put_value(18, 2003, 201, '1');
        $this->put_value(19, 3001, 301, '2');
        $this->put_value(20, 3001, 302, '7');
        $this->put_value(21, 3002, 301, '1');
        $this->put_value(22, 4001, 401, 'hello');
        $this->put_value(23, 5001, 501, 'gone');
        $this->put_value(24, 7001, 701, '1');
        $this->put_value(25, 7002, 701, '2');
        $this->put_value(26, 77777, 101, '1');           // a completion that does not exist.
        $this->put_value(27, 1005, 101, '1');            // the completion of a user that is gone.

        // The two draft tables: never read, never copied.
        $DB->import_record('local_eval_completedtmp', (object) ['id' => 1, 'evaluation' => 1, 'userid' => $u1,
            'guestid' => 'sesskey-abc', 'timemodified' => $t, 'random_response' => 0, 'anonymous_response' => 0,
            'courseid' => 0]);
        $DB->import_record('local_eval_valuetmp', (object) ['id' => 1, 'course_id' => 0, 'item' => 101,
            'completed' => 1, 'tmp_completed' => 1, 'value' => '1']);
    }

    /**
     * What the classroom and program features would have written: map rows for classroom 7 and program 9 and the
     * classroom row whose path a trainer feedback form is scoped by.
     *
     * @return void
     */
    private function seed_parents(): void {
        global $DB;
        $DB->import_record('local_sentientia_classroom', (object) [
            'id' => 7, 'name' => 'Classroom 7', 'description' => null, 'costcenterid' => 5, 'open_path' => '/1/5',
            'capacity' => 30, 'status' => 1, 'visible' => 1, 'timecreated' => self::T0, 'timemodified' => self::T0,
        ]);
        foreach ([['classroom', 'local_classroom', 7, 'local_sentientia_classroom'],
                ['program', 'local_program', 9, 'local_sentientia_programs']] as [$feature, $source, $id, $table]) {
            $DB->insert_record(legacymap::TABLE, (object) [
                'feature' => $feature, 'sourcetable' => $source, 'sourceid' => $id, 'subkey' => '',
                'targettable' => $table, 'targetid' => $id, 'outcome' => 'imported', 'reason' => null,
                'detail' => null, 'runid' => 0, 'timecreated' => time(),
            ]);
        }
    }

    // Reading the result.

    /**
     * The Sentientia id a legacy row was written as.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @return int|null
     */
    private function target(string $sourcetable, int $sourceid, string $subkey = ''): ?int {
        global $DB;
        $id = $DB->get_field(legacymap::TABLE, 'targetid',
            ['sourcetable' => $sourcetable, 'sourceid' => $sourceid, 'subkey' => $subkey]);
        return ($id === false || $id === null) ? null : (int) $id;
    }

    /**
     * The map row of a legacy row.
     *
     * @param string $sourcetable
     * @param int $sourceid
     * @param string $subkey
     * @return \stdClass
     */
    private function entry(string $sourcetable, int $sourceid, string $subkey = ''): \stdClass {
        global $DB;
        return $DB->get_record(legacymap::TABLE,
            ['sourcetable' => $sourcetable, 'sourceid' => $sourceid, 'subkey' => $subkey], '*', MUST_EXIST);
    }

    /**
     * One imported form.
     *
     * @param int $id
     * @return \stdClass
     */
    private function form(int $id): \stdClass {
        global $DB;
        return $DB->get_record(importer::T_FORMS, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The decoded response_data of the response that imported a completion.
     *
     * @param int $completed
     * @return array<int, mixed> Sentientia question id => answer.
     */
    private function answers_of(int $completed): array {
        global $DB;
        $response = $DB->get_record(importer::T_RESPONSES,
            ['id' => $this->target('local_evaluation_completed', $completed)], '*', MUST_EXIST);
        $decoded = json_decode($response->response_data, true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    /**
     * The response that imported a completion.
     *
     * @param int $completed
     * @return \stdClass
     */
    private function response_of(int $completed): \stdClass {
        global $DB;
        return $DB->get_record(importer::T_RESPONSES,
            ['id' => $this->target('local_evaluation_completed', $completed)], '*', MUST_EXIST);
    }

    /**
     * The assignment a (form, person) pair was imported as.
     *
     * @param int $form
     * @param int $user
     * @return \stdClass
     */
    private function assignment(int $form, int $user): \stdClass {
        global $DB;
        return $DB->get_record(importer::T_ASSIGN, ['evaluationid' => $form, 'userid' => $user], '*', MUST_EXIST);
    }

    /**
     * The new id of an imported question.
     *
     * @param int $item
     * @return int
     */
    private function qid(int $item): int {
        $id = $this->target('local_evaluation_item', $item);
        $this->assertNotNull($id, "item {$item} became a question");
        return $id;
    }

    /**
     * Seed, run once with every decision made, and insist it finished.
     *
     * @param bool $parents Put the classroom and program rows in first.
     * @return report
     */
    private function run_world(bool $parents = false): report {
        $this->contract_begin();
        $this->contract_seed();
        if ($parents) {
            $this->seed_parents();
        }
        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        return $report;
    }

    /**
     * A section of the form step's report (warnings, tenant_methods, skipped_by_reason).
     *
     * @param report $report
     * @param string $step
     * @param string $section
     * @return array
     */
    private function section(report $report, string $step, string $section): array {
        return $report->to_array()['features']['evaluation']['steps'][$step][$section] ?? [];
    }

    // Forms.

    public function test_forms_keep_their_ids_and_arrive_archived_and_manual(): void {
        global $DB;
        $this->run_world();

        $this->assertEqualsCanonicalizing([1, 2, 3, 4, 6, 7, 8, 9],
            array_map('intval', array_keys($DB->get_records(importer::T_FORMS, null, '', 'id'))),
            'eight forms, at their BizLMS ids; the soft-deleted one is not imported');
        foreach ($DB->get_records(importer::T_FORMS) as $form) {
            $this->assertSame(2, (int) $form->status, "form {$form->id} is archived");
            $this->assertSame('manual', $form->trigger_event, "form {$form->id} has no trigger");
            $this->assertSame(0, (int) $form->days_after);
            $this->assertSame(0, (int) $form->notify_admin_on_response, 'no message to administrators per response');
            $this->assertSame(1, (int) $form->kirkpatrick_level);
        }
        $this->assertFalse($DB->record_exists(importer::T_FORMS, ['id' => 5]));
        $this->assertSame('archived', $this->entry('local_evaluations', 5)->outcome);
        $this->assertSame('deleted_form', $this->entry('local_evaluations', 5)->reason);

        $one = $this->form(1);
        $this->assertSame('Form 1', $one->name);
        $this->assertSame(0, (int) $one->anonymous);
        $this->assertSame(1, (int) $one->multiple_submit);
        $this->assertSame(self::T0 + 100, (int) $one->timemodified, 'the source modification time is kept');
        $this->assertSame(self::T0 + 10, (int) $one->timecreated,
            'BizLMS has no creation time: the earliest record of the form is its first assignment');
        $this->assertSame(0, (int) $this->form(2)->timeopen);
        $this->assertSame(self::T0 + 5000, (int) $this->form(2)->timeclose);

        $description = $this->form(3)->description;
        $this->assertStringContainsString('Please rate your team member', $description);
        $this->assertStringNotContainsString('PLUGINFILE', $description, 'intro files are not carried');

        // The legacy table is the archive and is left as it was.
        $this->assertSame(9, $DB->count_records('local_evaluations'));
    }

    public function test_the_form_sequence_is_reset_so_a_native_form_gets_a_fresh_id(): void {
        $this->run_world();
        $native = evaluation_manager::create((object) ['name' => 'Native']);
        $this->assertGreaterThan(9, $native, 'an id above every legacy id');
    }

    public function test_tenant_attribution(): void {
        $report = $this->run_world();
        $expected = [
            1 => ['/1/5', 5], 2 => ['/77', 77], 3 => ['/1/5', 5], 4 => [null, 0], 6 => ['/77', 77],
            7 => ['/1/5', 5], 8 => ['/1/5', 5], 9 => [null, 0],
        ];
        foreach ($expected as $id => [$path, $org]) {
            $form = $this->form($id);
            $this->assertSame($path, $form->open_path, "form {$id} path");
            $this->assertSame($org, (int) $form->costcenterid, "form {$id} organisation");
        }
        $methods = $this->section($report, 'evaluation.forms', 'tenant_methods');
        ksort($methods);
        $this->assertEquals(
            ['exact' => 3, 'fallback:costcenterid' => 1, 'normalised' => 1, 'unresolved' => 2, 'walked_up' => 1],
            $methods, 'form 3 has a padded path; forms 4 and 9 cannot be placed');
    }

    /**
     * EV-TENANT: a form that its path, its stored root and its classroom cannot place stays pathless. It used to be
     * filed under the tenant of the person who last edited it, a guess that would show the form's named answers to
     * administrators BizLMS never showed them to. Here that person works in /77 today.
     */
    public function test_a_form_with_no_clue_is_not_filed_under_the_tenant_of_the_user_who_last_edited_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // No path ('0', the BizLMS default), no stored root, no classroom; last edited by a /77 person.
        $this->put_form(13, ['usermodified' => $this->u['u2']]);
        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $thirteen = $this->form(13);
        $this->assertNull($thirteen->open_path, 'no path: the editor\'s tenant is not a clue');
        $this->assertSame(0, (int) $thirteen->costcenterid);
        $methods = $this->section($report, 'evaluation.forms', 'tenant_methods');
        $this->assertEquals(3, $methods['unresolved'], 'forms 4, 9 and 13 cannot be placed, and the report says so');
        $this->assertArrayNotHasKey('fallback:usermodified', $methods, 'the editor is not a candidate any more');

        // Only cross-tenant callers see it: not an administrator of the editor's own tenant.
        $admin77 = $this->getDataGenerator()->create_user(['username' => 'evimp_adm77']);
        $DB->set_field('user', 'open_path', '/77', ['id' => $admin77->id]);
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST), $admin77->id,
            \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($DB->get_record('user', ['id' => $admin77->id], '*', MUST_EXIST));
        $this->assertFalse(evaluation_manager::can_manage_evaluation($thirteen));
        $this->setAdminUser();
        $this->assertTrue(evaluation_manager::can_manage_evaluation($thirteen));
    }

    public function test_a_trainer_feedback_form_is_scoped_by_its_classroom_and_linked_to_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->seed_parents();
        // A program feedback form and its one answer.
        $this->put_form(10, ['plugin' => 'program', 'instance' => 9, 'open_path' => '/1/5']);
        $this->put_item(1001, ['evaluation' => 10, 'typ' => 'textfield', 'position' => 1]);
        $this->put_completed(10001, 10, $this->u['u1'], self::T0 + 10000);
        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $four = $this->form(4);
        $this->assertSame('/1/5', $four->open_path, 'the path of classroom 7');
        $this->assertSame(5, (int) $four->costcenterid);
        $this->assertEquals(1, $this->section($report, 'evaluation.forms', 'tenant_methods')['fallback:classroom']);

        $assignment = $this->assignment(4, $this->u['u1']);
        $this->assertSame('classroom_end', $assignment->trigger_event);
        $this->assertSame(7, (int) $assignment->source_id, 'the classroom, through the map');
        $this->assertSame(7, (int) $this->response_of(4001)->classroomid);
        $this->assertNull($this->response_of(4001)->programid);
        $this->assertSame(9, (int) $this->response_of(10001)->programid);
        $this->assertNull($this->response_of(10001)->classroomid);
        // The forms that name no classroom or program link to none.
        $this->assertNull($this->response_of(1001)->classroomid);
        $this->assertSame(0, $DB->count_records(importer::T_RESPONSES, ['programid' => 7]));
    }

    public function test_dependencies_are_declared_so_the_framework_lets_the_map_be_read(): void {
        $importer = $this->contract_importer();
        $this->assertEqualsCanonicalizing(['org', 'classroom', 'program'], $importer->depends());
        // Every map read the steps make goes through these: the framework throws undeclared_dependency otherwise.
        $this->assertSame(['local_classroom', 'local_program'],
            [bizlms\importer::SRC_CLASSROOM, bizlms\importer::SRC_PROGRAM]);
    }

    // Templates and questions.

    public function test_templates_carry_their_items_in_the_payload(): void {
        global $DB;
        $report = $this->run_world();
        $this->assertSame(3, $DB->count_records(importer::T_TEMPLATES));

        $one = $DB->get_record(importer::T_TEMPLATES, ['id' => $this->target('local_evaluation_template', 1)], '*', MUST_EXIST);
        $this->assertSame('Post-training', $one->name);
        $this->assertSame(1, (int) $one->ispublic);
        $this->assertSame(5, (int) $one->costcenterid);
        $payload = json_decode($one->payload, true);
        $this->assertSame(1, $payload['format']);
        $this->assertSame('Post-training', $payload['evaluation']['name']);
        $this->assertSame([
            ['questiontype' => 'multichoice', 'questiontext' => 'T choice', 'options' => ['Y', 'N'], 'required' => 0,
                'anonymous' => 0, 'sortorder' => 1],
            ['questiontype' => 'text', 'questiontext' => 'T text', 'options' => [], 'required' => 0,
                'anonymous' => 0, 'sortorder' => 2],
        ], $payload['questions'], 'in form order; the layout item is not a question');

        $two = $DB->get_record(importer::T_TEMPLATES, ['id' => $this->target('local_evaluation_template', 2)], '*', MUST_EXIST);
        $this->assertSame(77, (int) $two->costcenterid, 'by the root BizLMS kept in costcenterid');
        $this->assertSame(0, (int) $DB->get_field(importer::T_TEMPLATES, 'costcenterid',
            ['id' => $this->target('local_evaluation_template', 3)]), 'a template nobody can place is unscoped');

        // The template items are accounted for without a question of their own.
        $this->assertSame('folded', $this->entry('local_evaluation_item', 960)->outcome);
        $this->assertSame((int) $one->id, (int) $this->entry('local_evaluation_item', 960)->targetid);
        $this->assertSame('archived', $this->entry('local_evaluation_item', 962)->outcome);
        $this->assertSame('not_a_question', $this->entry('local_evaluation_item', 962)->reason);
        $this->assertSame('orphan_template', $this->entry('local_evaluation_item', 963)->reason);

        $methods = $this->section($report, 'evaluation.templates', 'tenant_methods');
        ksort($methods);
        $this->assertEquals(['exact' => 1, 'fallback:costcenterid' => 1, 'unresolved' => 1], $methods);
    }

    public function test_questions_take_their_type_options_and_text_from_the_item(): void {
        global $DB;
        $report = $this->run_world();
        $q = static function (int $id): \stdClass {
            global $DB;
            return $DB->get_record(importer::T_QUESTIONS, ['id' => $id], '*', MUST_EXIST);
        };

        $radio = $q($this->qid(101));
        $this->assertSame('multichoice', $radio->questiontype);
        $this->assertSame('How was the venue?', $radio->questiontext);
        $this->assertSame(['Good', 'B', 'Bad'], json_decode($radio->options, true), 'markup in an option is stripped');
        $this->assertSame(1, (int) $radio->required);
        $this->assertSame(1, (int) $radio->sortorder);
        $this->assertSame(0, (int) $radio->anonymous);
        $this->assertSame((int) $this->target('local_evaluations', 1), (int) $radio->evaluationid);
        $this->assertSame(self::T0 + 10, (int) $radio->timecreated, 'the form\'s creation time');

        $this->assertSame('multichoice_multi', $q($this->qid(102))->questiontype);
        $this->assertSame(['A', 'B', 'C'], json_decode($q($this->qid(102))->options, true));
        $this->assertSame(['X', 'Y'], json_decode($q($this->qid(103))->options, true));
        $rated = $q($this->qid(104));
        $this->assertSame('multichoice', $rated->questiontype, 'decision evaluation.multichoicerated');
        $this->assertSame(['Poor', 'Fair', 'Great'], json_decode($rated->options, true), 'the weights are dropped');
        $numeric = $q($this->qid(105));
        $this->assertSame('numeric', $numeric->questiontype);
        $this->assertSame(['min' => 1, 'max' => 9], json_decode($numeric->options, true));
        $this->assertEquals(1, $this->section($report, 'evaluation.questions', 'warnings')['truncated:numeric_bound'],
            'decimal bounds are truncated and the truncation is reported');
        foreach ([106, 107] as $item) {
            $this->assertSame('text', $q($this->qid($item))->questiontype);
            $this->assertNull($q($this->qid($item))->options);
        }
        $this->assertSame(21, $DB->count_records(importer::T_QUESTIONS));
    }

    public function test_layout_items_and_broken_items_stay_in_the_legacy_table(): void {
        $this->run_world();
        foreach ([108, 109, 110, 111] as $item) {
            $this->assertSame('archived', $this->entry('local_evaluation_item', $item)->outcome);
            $this->assertSame('not_a_question', $this->entry('local_evaluation_item', $item)->reason);
            $this->assertNull($this->target('local_evaluation_item', $item));
        }
        $this->assertSame('parent_deleted', $this->entry('local_evaluation_item', 501)->reason, 'item of a soft-deleted form');
        $this->assertSame('orphan_form', $this->entry('local_evaluation_item', 950)->reason);
        $this->assertSame('skipped', $this->entry('local_evaluation_item', 950)->outcome);
        $this->assertSame('orphan_item', $this->entry('local_evaluation_item', 951)->reason);
    }

    public function test_conditional_questions_get_their_parent_in_a_second_pass(): void {
        global $DB;
        $this->run_world();
        $question = static function (int $id): \stdClass {
            global $DB;
            return $DB->get_record(importer::T_QUESTIONS, ['id' => $id], '*', MUST_EXIST);
        };

        $normal = $question($this->qid(112));
        $this->assertSame($this->qid(101), (int) $normal->depends_on_qid);
        $this->assertSame('B', $normal->depends_on_value,
            'dependvalue B matches the option <b>B</b> once both are normalised');

        $empty = $question($this->qid(113));
        $this->assertSame($this->qid(101), (int) $empty->depends_on_qid);
        $this->assertSame(self::NEVER, $empty->depends_on_value,
            'an empty dependvalue matched nothing in BizLMS: the question stays hidden');

        $later = $question($this->qid(114));
        $this->assertSame($this->qid(120), (int) $later->depends_on_qid, 'a parent with a higher id is found by the second pass');
        $this->assertGreaterThan($this->qid(114), (int) $later->depends_on_qid);
        $this->assertSame('Yes', $later->depends_on_value);

        $missing = $question($this->qid(115));
        $this->assertNull($missing->depends_on_qid, 'the parent does not exist');
        $this->assertNull($missing->depends_on_value, 'a value with no parent means nothing');

        foreach ([101, 102, 103] as $item) {
            $this->assertNull($question($this->qid($item))->depends_on_qid);
            $this->assertNull($question($this->qid($item))->depends_on_value);
        }
        // The conditions still behave: a hidden question is hidden, a shown one is shown.
        $questions = evaluation_manager::get_questions($this->target('local_evaluations', 1));
        $visible = evaluation_manager::compute_visibility_map($questions, [$this->qid(101) => 'B']);
        $this->assertTrue($visible[$this->qid(112)]);
        $this->assertFalse($visible[$this->qid(113)]);
        $this->assertFalse($visible[$this->qid(114)], 'its parent is unanswered');
        $this->assertTrue($visible[$this->qid(115)]);
    }

    // Answers.

    public function test_answers_are_folded_into_response_data_under_the_new_question_ids(): void {
        $this->run_world();
        $data = $this->answers_of(1001);
        $this->assertSame([
            $this->qid(101) => 'B',
            $this->qid(102) => ['A', 'C'],
            $this->qid(103) => 'Y',
            $this->qid(104) => 'Great',
            $this->qid(105) => 7.25,
            $this->qid(106) => "Tom & Jerry 'x'",
            $this->qid(107) => null,
            $this->qid(112) => null,
            $this->qid(113) => null,
            $this->qid(114) => null,
            $this->qid(115) => null,
            $this->qid(120) => null,
        ], $data, 'every imported question has a key; BizLMS stored the option position, Sentientia the option text');

        $response = $this->response_of(1001);
        $this->assertSame($this->u['u1'], (int) $response->userid);
        $this->assertNull($response->subject_userid);
        $this->assertSame(self::T0 + 1000, (int) $response->timesubmitted);
        $this->assertSame((int) $this->target('local_evaluations', 1), (int) $response->evaluationid);

        // A second completion: a bad position, a duplicate, a layout item and another form's item are not answers.
        $second = $this->answers_of(1002);
        $this->assertNull($second[$this->qid(101)], 'option 9 does not exist');
        $this->assertSame('P', $second[$this->qid(112)]);
        $this->assertSame(['B'], $second[$this->qid(102)], 'the first value for an item wins');
    }

    public function test_every_value_row_is_accounted_for(): void {
        $this->run_world();
        $this->assertSame('folded', $this->entry('local_evaluation_value', 1)->outcome);
        $this->assertSame((int) $this->target('local_evaluation_completed', 1001), (int) $this->entry('local_evaluation_value', 1)->targetid);
        $this->assertSame('in_response_data', $this->entry('local_evaluation_value', 1)->reason);
        $this->assertSame('folded', $this->entry('local_evaluation_value', 7)->outcome, 'an empty answer is an answer of none');

        $expected = [
            8 => 'value_not_valid', 11 => 'duplicate_value', 12 => 'item_not_imported', 13 => 'foreign_item',
            23 => 'response_not_imported', 27 => 'response_not_imported',
        ];
        foreach ($expected as $value => $reason) {
            $this->assertSame('archived', $this->entry('local_evaluation_value', $value)->outcome, "value {$value}");
            $this->assertSame($reason, $this->entry('local_evaluation_value', $value)->reason, "value {$value}");
        }
        $this->assertSame('skipped', $this->entry('local_evaluation_value', 26)->outcome);
        $this->assertSame('orphan_completed', $this->entry('local_evaluation_value', 26)->reason);
    }

    // Who answered.

    public function test_anonymous_answers_stay_anonymous(): void {
        global $DB;
        $this->run_world();
        $u2 = $this->u['u2'];

        $this->assertSame(1, (int) $this->form(2)->anonymous);
        foreach ([2001, 2002, 2003] as $completed) {
            $response = $this->response_of($completed);
            $this->assertSame(0, (int) $response->userid, "completion {$completed}: no user");
            $this->assertNull($response->subject_userid);
        }
        // Form 7 was named, but once it held an anonymous answer it is anonymous, and so are all its answers.
        $this->assertSame(1, (int) $this->form(7)->anonymous, 'anonymity is sticky');
        foreach ([7001, 7002] as $completed) {
            $this->assertSame(0, (int) $this->response_of($completed)->userid);
        }
        // A guest on a form that is named now is stored with user id 0 as well.
        $this->assertSame(0, $DB->count_records(importer::T_RESPONSES, ['evaluationid' => 2, 'userid' => $u2]));
        $this->assertSame(0, $DB->count_records(importer::T_RESPONSES, ['evaluationid' => 7, 'userid' => $u2]));
        // The anonymous answers still carry their content.
        $this->assertSame('Anon text', $this->answers_of(2001)[$this->qid(202)]);
        $this->assertSame('Yes', $this->answers_of(2001)[$this->qid(201)]);
        $this->assertSame('No', $this->answers_of(2002)[$this->qid(201)]);
    }

    public function test_a_supervisor_form_keeps_the_person_it_is_about(): void {
        $this->run_world();
        $supervised = $this->response_of(3001);
        $this->assertSame($this->u['sup'], (int) $supervised->userid, 'the responder is the supervisor');
        $this->assertSame($this->u['u1'], (int) $supervised->subject_userid, 'the person evaluated');
        $this->assertSame('2', $this->answers_of(3001)[$this->qid(301)], 'option 2 of "1|2|3" is the text 2');
        $this->assertSame(7, $this->answers_of(3001)[$this->qid(302)]);

        $old = $this->response_of(3002);
        $this->assertSame($this->u['u1'], (int) $old->userid, 'before evaluatedby existed the user is the one row holds');
        $this->assertNull($old->subject_userid);
    }

    /**
     * EV-17: the form carries the mode BizLMS gave it, so Sentientia no longer guesses from the responses.
     */
    public function test_a_form_carries_the_evaluation_mode_bizlms_gave_it(): void {
        global $DB;
        $this->run_world();
        foreach ($DB->get_records(importer::T_FORMS) as $form) {
            $this->assertSame((int) $form->id === 3 ? 'SP' : 'SE', $form->evaluationmode,
                "form {$form->id}: only the seeded supervisor evaluation is SP");
        }
    }

    /**
     * EV-17: a supervisor completion from before BizLMS recorded who filled it in follows the map (the completion's
     * user answers, which is the person evaluated, and no subject is kept) and is counted, so the owner knows how
     * many responses name the person evaluated as the responder.
     */
    public function test_an_old_supervisor_completion_with_no_evaluator_is_counted(): void {
        $report = $this->run_world();
        // 3002 is a named completion of supervisor form 3 with evaluatedby 0; 3001 names its evaluator.
        $warnings = $this->section($report, 'evaluation.responses', 'warnings');
        $this->assertEquals(1, $warnings['sp_responder_unknown'], 'only completion 3002 has no evaluator');
        $this->assertSame($this->u['u1'], (int) $this->response_of(3002)->userid, 'still as the map says');
        $this->assertNull($this->response_of(3002)->subject_userid);
        $this->assertSame($this->u['sup'], (int) $this->response_of(3001)->userid);
    }

    /**
     * EV-17: an anonymous supervisor evaluation keeps no subject, and the import implies a "responded" assignment
     * for the person evaluated (no assignee row). Neither it nor the named supervisor form 3 reaches that person's
     * history, because the forms say they are supervisor evaluations; their self evaluations still do.
     */
    public function test_the_person_evaluated_does_not_see_an_imported_supervisor_form_as_responded(): void {
        $this->contract_begin();
        $this->contract_seed();
        $u1 = $this->u['u1'];
        $this->put_form(14, ['evaluationmode' => 'SP', 'anonymous' => 1, 'open_path' => '/1/5']);
        $this->put_item(1401, ['evaluation' => 14, 'typ' => 'textfield', 'position' => 1]);
        $this->put_completed(14001, 14, $u1, self::T0 + 14000, ['anonymous_response' => 1]);
        $this->put_value(41, 14001, 1401, 'Fine');
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $this->assertSame('SP', $this->form(14)->evaluationmode);
        $this->assertSame(0, (int) $this->response_of(14001)->userid, 'anonymous: no responder');
        $this->assertNull($this->response_of(14001)->subject_userid, 'and no subject: the old marker is gone');
        $this->assertSame('responded', $this->assignment(14, $u1)->status, 'the import still implies the assignment');

        $names = array_map(static fn(\stdClass $r): string => $r->name, learner_history::for_user($u1));
        $this->assertContains('Form 1', $names, 'a self evaluation they answered is listed');
        $this->assertNotContains('Form 3', $names, 'the named supervisor evaluation about them');
        $this->assertNotContains('Form 14', $names, 'the anonymous one, whose response names nobody');
    }

    /**
     * EV-17: verify names an imported form whose mode no longer matches BizLMS.
     */
    public function test_verify_names_a_form_whose_mode_no_longer_matches_bizlms(): void {
        global $DB;
        $this->run_world();
        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $clean = $runner->verify(['evaluation']);
        $this->assertSame(0, $clean['exit'], implode('; ', $clean['failures']['evaluation']));

        // The supervisor evaluation loses its marker, and a self evaluation gains one.
        $DB->set_field(importer::T_FORMS, 'evaluationmode', 'SE', ['id' => 3]);
        $DB->set_field(importer::T_FORMS, 'evaluationmode', 'SP', ['id' => 1]);
        $failures = $runner->verify(['evaluation']);
        $this->assertSame(1, $failures['exit']);
        $this->assertContains('imported_form_mode_mismatch:2', $failures['failures']['evaluation']);
    }

    public function test_responses_of_missing_people_and_missing_times_are_handled(): void {
        $report = $this->run_world();
        $this->assertSame('skipped', $this->entry('local_evaluation_completed', 1005)->outcome);
        $this->assertSame('orphan_user', $this->entry('local_evaluation_completed', 1005)->reason);
        $this->assertSame('orphan_form', $this->entry('local_evaluation_completed', 9999)->reason);
        $this->assertSame('parent_deleted', $this->entry('local_evaluation_completed', 5001)->reason);

        // A completion with no time takes the form's (Sentientia reads timesubmitted 0 as a queue shell).
        $this->assertSame(self::T0 + 100, (int) $this->response_of(1006)->timesubmitted);
        $warnings = $this->section($report, 'evaluation.responses', 'warnings');
        $this->assertEquals(1, $warnings['derived_timestamp']);
        $this->assertEquals(1, $warnings['rejected_values'], 'one imported completion (1002) held values that were not answers');
    }

    public function test_a_long_free_text_answer_is_kept_whole(): void {
        $this->contract_begin();
        $this->contract_seed();
        // BizLMS kept the answer in a LONGTEXT and the response is one LONGTEXT on MySQL: nothing to cut it to.
        $long = implode(' ', array_fill(0, 3000, 'word'));
        $this->assertGreaterThan(10000, strlen($long));
        $this->put_completed(1007, 1, $this->u['u1'], self::T0 + 1500, ['anonymous_response' => 2]);
        $this->put_value(40, 1007, 107, $long);

        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $this->assertSame($long, $this->answers_of(1007)[$this->qid(107)], 'not truncated');
        $this->assertArrayNotHasKey('truncated:answer', $this->section($report, 'evaluation.responses', 'warnings'));
        $this->assertSame('folded', $this->entry('local_evaluation_value', 40)->outcome);
    }

    public function test_a_self_evaluation_whose_filler_has_gone_is_kept_and_a_supervisor_one_is_not(): void {
        $this->contract_begin();
        $this->contract_seed();
        $u1 = $this->u['u1'];
        // evaluatedby names a user that does not exist (77777). On form 1 (self evaluation) the person evaluated is
        // the person who answered; on form 3 (supervisor evaluation) the completion's user is the person EVALUATED, so
        // making them the responder would show them as having answered their own review.
        $this->put_completed(1008, 1, $u1, self::T0 + 1600, ['anonymous_response' => 2, 'evaluatedby' => 77777]);
        $this->put_completed(3003, 3, $u1, self::T0 + 3200, ['anonymous_response' => 2, 'evaluatedby' => 77777]);

        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $kept = $this->response_of(1008);
        $this->assertSame($u1, (int) $kept->userid, 'the answers are kept, under the person evaluated');
        $this->assertNull($kept->subject_userid);
        $this->assertSame('imported', $this->entry('local_evaluation_completed', 1008)->outcome);
        $warnings = $this->section($report, 'evaluation.responses', 'warnings');
        $this->assertEquals(1, $warnings['responder_not_found']);

        $this->assertSame('skipped', $this->entry('local_evaluation_completed', 3003)->outcome);
        $this->assertSame('orphan_user', $this->entry('local_evaluation_completed', 3003)->reason);
    }

    // Assignments.

    public function test_assignments_one_per_form_and_person_with_the_earliest_row_winning(): void {
        global $DB;
        $this->run_world();
        $u1 = $this->u['u1'];

        $first = $this->assignment(1, $u1);
        $this->assertSame($this->target('local_evaluation_users', 2), (int) $first->id, 'the earlier row is the one imported');
        $this->assertSame('merged', $this->entry('local_evaluation_users', 1)->outcome);
        $this->assertSame((int) $first->id, (int) $this->entry('local_evaluation_users', 1)->targetid);
        $this->assertSame($this->u['sup'], (int) $first->assigned_by_userid, 'its creator is kept');
        $this->assertSame('responded', $first->status);
        $this->assertSame('manual', $first->trigger_event);
        $this->assertSame(0, (int) $first->source_id);
        $this->assertSame(self::T0 + 10, (int) $first->timecreated);
        $this->assertSame(self::T0 + 11, (int) $first->timemodified);
        $this->assertSame(self::T0 + 1100, (int) $first->responded_at, 'the latest completion of the pair');
        $this->assertNull($first->due_at);

        // Nobody answered: the form is archived, so the assignment is closed.
        $this->assertSame('expired', $this->assignment(1, $this->u['susp'])->status);
        $this->assertNull($this->assignment(1, $this->u['susp'])->responded_at);
        $this->assertSame('expired', $this->assignment(1, $this->u['del'])->status, 'a deleted user\'s history is kept');

        $this->assertSame('skipped', $this->entry('local_evaluation_users', 5)->outcome);
        $this->assertSame('orphan_assignee', $this->entry('local_evaluation_users', 5)->reason);
        $this->assertSame('parent_deleted', $this->entry('local_evaluation_users', 9)->reason);
        $this->assertSame('orphan_form', $this->entry('local_evaluation_users', 10)->reason);

        // The form's closing date is the assignment's due date.
        $this->assertSame(self::T0 + 5000, (int) $this->assignment(2, $this->u['u2'])->due_at);
        $this->assertSame(12, $DB->count_records(importer::T_ASSIGN), '6 from the assignee table, 6 implied by completions');
    }

    public function test_a_completion_with_no_assignment_implies_one_responded_assignment(): void {
        global $DB;
        $this->run_world();
        $u2 = $this->u['u2'];

        // u2 answered form 1 twice and was never assigned it: one assignment, from the first completion.
        $this->assertSame(1, $DB->count_records(importer::T_ASSIGN, ['evaluationid' => 1, 'userid' => $u2]));
        $implied = $this->assignment(1, $u2);
        $this->assertSame('responded', $implied->status);
        $this->assertSame('manual', $implied->trigger_event);
        $this->assertSame((int) $implied->id, $this->target('local_evaluation_completed', 1003, 'assign'));
        $this->assertNull($this->target('local_evaluation_completed', 1004, 'assign'), 'the second completion adds nothing');
        $this->assertNull($implied->assigned_by_userid);
        $this->assertSame(self::T0 + 1200, (int) $implied->timecreated);
        $this->assertSame(self::T0 + 1300, (int) $implied->responded_at);
        // The assignee table already covers u1 on form 1, so none of u1's three completions adds one.
        $this->assertNull($this->target('local_evaluation_completed', 1001, 'assign'));
        $this->assertSame(1, $DB->count_records(importer::T_ASSIGN, ['evaluationid' => 1, 'userid' => $this->u['u1']]));
    }

    public function test_the_implied_assignment_comes_from_the_first_imported_completion(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $gen = $this->getDataGenerator();
        $t = self::T0 + 20000;

        // (a) A named self-evaluation form that has no time of its own. The person has no assignee row and two
        // completions: the lower id (A) has no time either, so it is skipped (no_timestamp); B has one.
        $this->put_form(10, ['timemodified' => 0, 'open_path' => '/1/5']);
        $learner = $gen->create_user(['username' => 'evimp_first_imported']);
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $learner->id]);
        $this->put_completed(1101, 10, (int) $learner->id, 0, ['anonymous_response' => 2]);
        $this->put_completed(1102, 10, (int) $learner->id, $t, ['anonymous_response' => 2]);

        // (b) A supervisor form. The subject S has no assignee row. The lower completion (A) was filled in by a user
        // that does not exist, so it is skipped (orphan_user); B was filled in by an existing supervisor.
        $this->put_form(11, ['evaluationmode' => 'SP', 'open_path' => '/1/5']);
        $subject = $gen->create_user(['username' => 'evimp_subject']);
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $subject->id]);
        $this->put_completed(1103, 11, (int) $subject->id, $t + 100, ['anonymous_response' => 2, 'evaluatedby' => 88881]);
        $this->put_completed(1104, 11, (int) $subject->id, $t + 200,
            ['anonymous_response' => 2, 'evaluatedby' => $this->u['sup']]);

        // Every needs-owner reason is accepted by the contract's decisions, so the two skipped completions are fine.
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        // (a) The skipped completion has no response and no assignment; the imported one carries the assignment.
        $this->assertSame('skipped', $this->entry('local_evaluation_completed', 1101)->outcome);
        $this->assertSame('no_timestamp', $this->entry('local_evaluation_completed', 1101)->reason);
        $this->assertNull($this->target('local_evaluation_completed', 1101, 'assign'));
        $this->assertSame(1, $DB->count_records(importer::T_ASSIGN, ['evaluationid' => 10, 'userid' => $learner->id]));
        $assign = $this->assignment(10, (int) $learner->id);
        $this->assertSame((int) $assign->id, $this->target('local_evaluation_completed', 1102, 'assign'));
        $this->assertSame('responded', $assign->status);
        $this->assertSame($t, (int) $assign->responded_at);
        $this->assertSame($t, (int) $assign->timecreated);

        // (b) Skipped for its responder; the supervisor's completion carries the subject's assignment, with its time.
        $this->assertSame('skipped', $this->entry('local_evaluation_completed', 1103)->outcome);
        $this->assertSame('orphan_user', $this->entry('local_evaluation_completed', 1103)->reason);
        $this->assertNull($this->target('local_evaluation_completed', 1103, 'assign'));
        $this->assertSame(1, $DB->count_records(importer::T_ASSIGN, ['evaluationid' => 11, 'userid' => $subject->id]));
        $assign = $this->assignment(11, (int) $subject->id);
        $this->assertSame((int) $assign->id, $this->target('local_evaluation_completed', 1104, 'assign'));
        $this->assertSame($t + 200, (int) $assign->timecreated);
        $this->assertSame($t + 200, (int) $assign->responded_at, 'the latest imported completion, not a skipped one');
        // The response beside it is the supervisor's, about the subject.
        $response = $this->response_of(1104);
        $this->assertSame($this->u['sup'], (int) $response->userid);
        $this->assertSame((int) $subject->id, (int) $response->subject_userid);
    }

    /**
     * The test above has the skipped completion BEFORE the imported one, where the old "latest of every legacy
     * completion" time and the new "latest imported" time agree. Here the skipped completion is the LATER one: a
     * regression to the old time would stamp the person as having responded when a completion that was never
     * imported happened.
     */
    public function test_the_implied_assignment_time_ignores_a_later_skipped_completion(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $gen = $this->getDataGenerator();
        $t = self::T0 + 30000;

        // A supervisor form. The subject has no assignee row and two completions. The lower one (A, 1201) is imported
        // (its supervisor exists); the later one (B, 1202: higher id, later time) was filled in by a user that does
        // not exist, so it is skipped (orphan_user).
        $this->put_form(12, ['evaluationmode' => 'SP', 'open_path' => '/1/5']);
        $subject = $gen->create_user(['username' => 'evimp_subject_late']);
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $subject->id]);
        $this->put_completed(1201, 12, (int) $subject->id, $t + 200,
            ['anonymous_response' => 2, 'evaluatedby' => $this->u['sup']]);
        $this->put_completed(1202, 12, (int) $subject->id, $t + 900,
            ['anonymous_response' => 2, 'evaluatedby' => 88882]);

        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $this->assertSame('skipped', $this->entry('local_evaluation_completed', 1202)->outcome);
        $this->assertSame('orphan_user', $this->entry('local_evaluation_completed', 1202)->reason);
        $this->assertNull($this->target('local_evaluation_completed', 1202, 'assign'));

        $this->assertSame(1, $DB->count_records(importer::T_ASSIGN, ['evaluationid' => 12, 'userid' => $subject->id]));
        $assign = $this->assignment(12, (int) $subject->id);
        $this->assertSame((int) $assign->id, $this->target('local_evaluation_completed', 1201, 'assign'));
        $this->assertSame('responded', $assign->status);
        $this->assertSame($t + 200, (int) $assign->timecreated);
        $this->assertSame($t + 200, (int) $assign->responded_at,
            'the latest IMPORTED completion; the skipped one at t+900 is not a response the person gave');
    }

    public function test_assignment_times_on_a_protected_form_are_cut_to_the_day(): void {
        $this->run_world();
        $tz = \core_date::get_server_timezone_object();
        $day = static function (int $time) use ($tz): int {
            return (new \DateTimeImmutable('@' . $time))->setTimezone($tz)->setTime(0, 0, 0)->getTimestamp();
        };

        // Form 2 is anonymous and has an assignee row: only responded_at is cut.
        $two = $this->assignment(2, $this->u['u2']);
        $this->assertSame($day(self::T0 + 2100), (int) $two->responded_at);
        $this->assertSame(self::T0 + 50, (int) $two->timecreated, 'the time it was assigned is not a response time');

        // Form 7 became anonymous: its implied assignments are cut on every time.
        $seven = $this->assignment(7, $this->u['u1']);
        $this->assertSame($day(self::T0 + 7000), (int) $seven->timecreated);
        $this->assertSame($day(self::T0 + 7000), (int) $seven->timemodified);
        $this->assertSame($day(self::T0 + 7000), (int) $seven->responded_at);
        $this->assertLessThanOrEqual(86400 + 3600, abs((int) $seven->timecreated - (self::T0 + 7000)));
        $this->assertNotSame(self::T0 + 7000, (int) $seven->timecreated);

        // Form 1 is not protected: the minute is kept.
        $this->assertSame(self::T0 + 1200, (int) $this->assignment(1, $this->u['u2'])->timecreated);
    }

    // Counts, reasons, privacy and what must not happen.

    public function test_every_source_row_has_one_outcome_and_the_reasons_add_up(): void {
        global $DB;
        $this->run_world();
        $tally = [];
        foreach ($DB->get_records_sql(
                "SELECT reason, COUNT(1) AS n FROM {" . legacymap::TABLE . "}
                  WHERE feature = 'evaluation' AND subkey = '' AND reason IS NOT NULL GROUP BY reason") as $row) {
            $tally[$row->reason] = (int) $row->n;
        }
        ksort($tally);
        $expected = [
            'deleted_form' => 1, 'dup_assignment' => 1, 'duplicate_value' => 1, 'foreign_item' => 1,
            'in_response_data' => 20, 'in_template_payload' => 2, 'item_not_imported' => 1, 'not_a_question' => 5,
            'orphan_assignee' => 1,
            'orphan_completed' => 1, 'orphan_form' => 3, 'orphan_item' => 1, 'orphan_template' => 1, 'orphan_user' => 1,
            'parent_deleted' => 3, 'response_not_imported' => 2, 'value_not_valid' => 1,
        ];
        ksort($expected);
        $this->assertSame($expected, $tally);

        foreach (['local_evaluations' => 9, 'local_evaluation_template' => 3, 'local_evaluation_item' => 32,
                'local_evaluation_completed' => 19, 'local_evaluation_value' => 27, 'local_evaluation_users' => 10] as $table => $rows) {
            $this->assertSame($rows, $DB->count_records($table), "{$table} is untouched");
            $this->assertSame($rows, $DB->count_records(legacymap::TABLE, ['sourcetable' => $table, 'subkey' => '']),
                "{$table}: one primary map row per source row");
        }
    }

    public function test_the_drafts_and_the_legacy_tables_are_left_alone(): void {
        global $DB;
        $tables = ['local_evaluations', 'local_evaluation_template', 'local_evaluation_item',
            'local_evaluation_completed', 'local_evaluation_value', 'local_evaluation_users', 'local_eval_completedtmp',
            'local_eval_valuetmp'];
        $this->contract_begin();
        $this->contract_seed();
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = $DB->get_records($table);
        }
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        foreach ($tables as $table) {
            $this->assertEquals($before[$table], $DB->get_records($table), "{$table} is unchanged");
        }
        $this->assertSame(0, $DB->count_records_select(legacymap::TABLE, "sourcetable LIKE 'local_eval%tmp'"),
            'the drafts are declined, not mapped');
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_triggers'), 'no trigger is queued');
    }

    public function test_a_row_in_the_dead_sitecourse_map_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $DB->import_record('local_eval_sitecourse_map', (object) ['id' => 1, 'evaluationid' => 1, 'courseid' => 2]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('sitecourse_map_has_rows:1', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(importer::T_FORMS), 'nothing is written while a blocker stands');
    }

    public function test_an_item_type_nobody_mapped_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->put_item(990, ['evaluation' => 1, 'typ' => 'mystery']);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_evaluation_item.typ=mystery', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records(importer::T_QUESTIONS));
    }

    public function test_a_completion_flag_nobody_mapped_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // 0, 1 and 2 are the values BizLMS writes (the seed holds all three); another value is not one of them.
        $this->put_completed(9990, 1, $this->u['u1'], time() - 5, ['anonymous_response' => 3]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $blockers = implode(' ', $result['blockers']);
        $this->assertStringContainsString('unknown_enum:local_evaluation_completed.anonymous_response=3', $blockers);
        $this->assertStringNotContainsString('anonymous_response=2', $blockers, 'production stores 2 for a named answer');
        $this->assertSame(0, $DB->count_records(importer::T_RESPONSES));
    }

    public function test_a_declared_decision_with_another_value_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        // Only "archived" is implemented: an active form would reopen answering with no assignment check.
        [$result] = $this->contract_run(true, [
            'decisions' => decisions::from_array(['evaluation.open_forms' => 'active'] + $this->declared_choices()),
        ]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:evaluation.open_forms', implode(' ', $result['blockers']));
    }

    public function test_a_missing_decision_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => decisions::none()]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:evaluation.open_forms', implode(' ', $result['blockers']));
    }

    /**
     * EV-16: the sticky-anonymity choice is declared, so a decisions file signed before it existed cannot start the
     * import.
     */
    public function test_a_decisions_file_without_the_sticky_anonymity_choice_blocks_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $without = $this->declared_choices();
        unset($without['evaluation.sticky_anonymity']);
        [$result] = $this->contract_run(true, ['decisions' => decisions::from_array($without)]);
        $this->assertSame(1, $result['exit']);
        $blockers = implode(' ', $result['blockers']);
        $this->assertStringContainsString('missing_decision:evaluation.sticky_anonymity', $blockers);
        $this->assertStringNotContainsString('missing_decision:evaluation.open_forms', $blockers,
            'only the choice that is absent is named');
        $this->assertSame(0, $DB->count_records(importer::T_FORMS), 'nothing is written while a blocker stands');
    }

    /**
     * EV-16: a file that says the other thing (the named rows are kept) is refused too: only whole_form is implemented.
     */
    public function test_a_decisions_file_that_keeps_the_named_rows_blocks_the_feature(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, [
            'decisions' => decisions::from_array(['evaluation.sticky_anonymity' => 'named_rows_kept'] + $this->declared_choices()),
        ]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_value_not_allowed:evaluation.sticky_anonymity',
            implode(' ', $result['blockers']));
    }

    public function test_a_native_form_with_a_bad_path_is_reported_before_the_run(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // The framework's tenant check reads every row of the form table, this one too. An id the source does not
        // use, so it is a native form and not a collision with form 1.
        $DB->import_record(importer::T_FORMS, (object) [
            'id' => 500, 'name' => 'Native with a bad path', 'kirkpatrick_level' => 1, 'trigger_event' => 'manual',
            'days_after' => 0,
            'costcenterid' => 5, 'open_path' => '/1/5/', 'status' => 0, 'anonymous' => 0, 'timeopen' => 0,
            'timeclose' => 0, 'multiple_submit' => 0, 'notify_admin_on_response' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ]);
        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('native_form_invalid_path:1', implode(' ', $result['blockers']));
    }

    public function test_rows_left_at_a_legacy_form_id_block_the_feature(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // delete() used to leave assignment and trigger rows behind. Forms keep their BizLMS ids, so a row at the id
        // of legacy form 2 or 3 (neither exists in Sentientia yet) would attach to the imported form.
        $assign = static fn(int $form, int $user): \stdClass => (object) [
            'evaluationid' => $form, 'userid' => $user, 'trigger_event' => 'manual', 'source_id' => 0,
            'status' => 'assigned', 'assigned_by_userid' => null, 'due_at' => null, 'responded_at' => null,
            'timecreated' => 1, 'timemodified' => 1,
        ];
        $DB->insert_record(importer::T_ASSIGN, $assign(2, $this->u['u1']));
        $DB->insert_record(importer::T_ASSIGN, $assign(2, $this->u['u2']));
        $DB->insert_record(importer::T_ASSIGN, $assign(500, $this->u['u1']));   // 500 is no legacy form: not ours.
        $DB->insert_record(importer::T_TRIGGERS, (object) [
            'evaluationid' => 3, 'userid' => $this->u['u1'], 'itemid' => 0, 'trigger_event' => 'course_completion',
            'fire_after' => 1, 'status' => 0, 'timecreated' => 1,
        ]);

        [$result] = $this->contract_run(true);
        $this->assertSame(1, $result['exit']);
        $blockers = implode(' ', $result['blockers']);
        // The framework's check (form_step::target_children(), item 7): the step, the table and the count, never a row.
        $this->assertStringContainsString('leftover_rows_at_legacy_ids:evaluation.forms:' . importer::T_ASSIGN . ':2', $blockers);
        $this->assertStringContainsString('leftover_rows_at_legacy_ids:evaluation.forms:' . importer::T_TRIGGERS . ':1', $blockers);
        $this->assertStringNotContainsString(importer::T_RESPONSES, $blockers, 'a table with no stray row is not named');
        $this->assertSame(0, $DB->count_records(importer::T_FORMS), 'nothing is written while a blocker stands');
        $this->assertSame(3, $DB->count_records(importer::T_ASSIGN), 'and the stray rows are not touched');
    }

    public function test_a_second_stored_answer_for_one_question_is_left_to_the_owner(): void {
        $this->contract_begin();
        $this->contract_seed();
        $needs = [];
        foreach ((new importer())->reasons() as $reason) {
            $needs[$reason->code] = $reason->needsowner;
        }
        $this->assertTrue($needs['duplicate_value'], 'an answer the import drops is for the owner to look at');
        $this->assertTrue($needs['foreign_item'], 'so is a value of another form\'s item');
        $this->assertTrue($needs['missing_item'], 'and a value of an item that no longer exists');
        $this->assertFalse($needs['item_not_imported'], 'a value of a layout item is not');

        // The seed holds one such value (value 11). Until the owner accepts the reason, parity is unproven.
        $accepted = array_diff(self::NEEDS_OWNER, ['evaluation:duplicate_value']);
        [$result] = $this->contract_run(true, ['decisions' => $this->decisions_accepting($accepted)]);
        $this->assertSame(2, $result['exit']);
        $this->assertContains('evaluation:duplicate_value=1', $result['unproven']);
    }

    public function test_a_value_of_another_forms_item_or_of_no_item_is_left_to_the_owner(): void {
        $this->contract_begin();
        $this->contract_seed();
        // The seed already holds value 13, which names an item of ANOTHER form (item 201 belongs to form 2, the
        // completion to form 1). This one names an item that does not exist at all. Seeded here, not in the shared
        // world, so the tallies of the other tests do not move.
        $this->put_value(41, 1001, 999999, '1');

        // Strays the run will NOT archive as missing_item or foreign_item, because their completion is not imported,
        // so the run reports them under another reason and the preflight must not count them:
        $this->put_value(42, 1005, 888888, '1');        // no such item; completion 1005 is skipped (its user is gone)
        $this->put_value(43, 5001, 888889, '1');        // no such item; completion of form 5, which is deleted
        $this->put_value(44, 5001, 201, '1');           // item of form 2; the same deleted form
        $this->put_form(10, ['timemodified' => 0, 'open_path' => '/1/5']);
        $this->put_completed(1201, 10, $this->u['u1'], 0);   // no time of its own and none on its form: skipped
        $this->put_value(45, 1201, 201, '1');           // item of form 2; completion 1201 is skipped (no time)
        $this->put_value(46, 77777, 888890, '1');       // no such item; there is no completion 77777 at all

        // The owner has accepted every reason but missing_item.
        $accepted = array_diff(self::NEEDS_OWNER, ['evaluation:missing_item']);
        [$result, $report] = $this->contract_run(true, ['decisions' => $this->decisions_accepting($accepted)]);

        $this->assertSame(2, $result['exit'], 'an answer the import cannot carry is unproven until the owner accepts it');
        $this->assertContains('evaluation:missing_item=1', $result['unproven']);
        $this->assertNotContains('evaluation:foreign_item=1', $result['unproven'], 'accepted in this run');

        // Each is archived under its own reason, and says which of the two it is.
        $this->assertSame('archived', $this->entry('local_evaluation_value', 13)->outcome);
        $this->assertSame('foreign_item', $this->entry('local_evaluation_value', 13)->reason);
        $this->assertSame('archived', $this->entry('local_evaluation_value', 41)->outcome);
        $this->assertSame('missing_item', $this->entry('local_evaluation_value', 41)->reason);
        // A layout item of the form itself stays the harmless kind.
        $this->assertSame('item_not_imported', $this->entry('local_evaluation_value', 12)->reason);
        // A stray value of a completion that is not imported is reported under the completion's fate, as the run
        // has always done: it is not "an answer the import lost", the whole response is.
        foreach ([42, 43, 44, 45] as $valueid) {
            $this->assertSame('response_not_imported', $this->entry('local_evaluation_value', $valueid)->reason,
                "value {$valueid}");
        }
        $this->assertSame('orphan_completed', $this->entry('local_evaluation_value', 46)->reason);
        // The completion's real answers are unaffected, and no stray value becomes an answer.
        $this->assertSame('B', $this->answers_of(1001)[$this->qid(101)]);
        $this->assertCount(12, $this->answers_of(1001), 'one key per imported question of form 1, no more');

        // The preflight said so before the run, and said it of the values the run archives under each reason: one
        // each (values 41 and 13). Counting every stray row would have said four and three; the strays of completions
        // that are not imported (42 to 46) belong to the completion's own reason.
        $preflight = $report->to_array()['features']['evaluation']['preflight'];
        $this->assertSame(1, $preflight['counts']['values_missing_item']);
        $this->assertSame(1, $preflight['counts']['values_foreign_item']);
        $this->assertContains('values_missing_item:1', $preflight['warnings']);
        $this->assertContains('values_foreign_item:1', $preflight['warnings']);
        $this->assertSame([], $preflight['blockers'], 'a warning, not a blocker: the rows are reported and archived');
    }

    public function test_a_dry_run_without_its_parents_reports_the_classroom_as_deferred(): void {
        $this->contract_begin();
        $this->contract_seed();

        // The evaluation feature ALONE: the classroom feature is neither complete nor simulated, so the trainer
        // feedback form (form 4, classroom 7) cannot find its classroom in the map. That is the run's doing, not the
        // data's, and the report says so instead of calling the classroom unresolved.
        $report = new report();
        $runner = new runner([
            'apply' => false, 'decisions' => $this->contract_decisions(), 'report' => $report,
            'batch' => $this->contract_batch(), 'atomic_threshold' => 0,
        ]);
        $result = $runner->run(['evaluation']);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        foreach (['evaluation.forms', 'evaluation.assignments', 'evaluation.responses'] as $step) {
            $warnings = $this->section($report, $step, 'warnings');
            $this->assertEquals(1, $warnings['deferred:local_classroom'] ?? 0, $step . ': the one trainer feedback form');
            $this->assertArrayNotHasKey('classroom_unresolved', $warnings, $step);
        }

        // With every feature in the run the classroom is simulated; it is still not in the map, so now it IS
        // unresolved, as before.
        [$result, $report] = $this->contract_run(false);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $warnings = $this->section($report, 'evaluation.assignments', 'warnings');
        $this->assertEquals(1, $warnings['classroom_unresolved'] ?? 0);
        $this->assertArrayNotHasKey('deferred:local_classroom', $warnings);
        foreach (['evaluation.forms', 'evaluation.responses'] as $step) {
            $this->assertArrayNotHasKey('deferred:local_classroom', $this->section($report, $step, 'warnings'), $step);
        }
    }

    public function test_the_import_sends_nothing_and_queues_nothing(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $sink = $this->redirectMessages();
        $events = $this->redirectEvents();
        $emails = $this->redirectEmails();
        $adhoc = $DB->count_records('task_adhoc');
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit']);
        $this->assertSame(0, $sink->count());
        $this->assertSame(0, $events->count());
        $this->assertSame(0, $emails->count());
        $this->assertSame($adhoc, $DB->count_records('task_adhoc'), 'no adhoc task');
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_triggers'));
    }

    // Who can see what (ADR-031).

    public function test_a_tenant_admin_manages_only_the_imported_forms_of_their_own_tenant(): void {
        $this->run_world(true);
        $byid = static function (int $id): \stdClass {
            global $DB;
            return $DB->get_record(importer::T_FORMS, ['id' => $id], '*', MUST_EXIST);
        };

        $this->setUser($this->user_record('adm1'));
        foreach ([1, 3, 4, 7, 8] as $id) {
            $this->assertTrue(evaluation_manager::can_manage_evaluation($byid($id)), "a /1 admin manages form {$id}");
        }
        foreach ([2, 6, 9] as $id) {
            $this->assertFalse(evaluation_manager::can_manage_evaluation($byid($id)), "a /1 admin does not manage form {$id}");
        }

        $this->setUser($this->user_record('adm177'));
        foreach ([1, 2, 3, 4, 6, 7, 8, 9] as $id) {
            $this->assertFalse(evaluation_manager::can_manage_evaluation($byid($id)), "a /177 admin manages none: form {$id}");
        }

        $this->setAdminUser();
        foreach ([1, 2, 3, 4, 6, 7, 8, 9] as $id) {
            $this->assertTrue(evaluation_manager::can_manage_evaluation($byid($id)), "the site administrator manages form {$id}");
        }
    }

    public function test_a_form_nobody_can_place_is_for_cross_tenant_callers_only(): void {
        $this->run_world();
        $nine = $this->form(9);
        $this->assertSame(0, (int) $nine->costcenterid);
        $this->assertNull($nine->open_path);
        $this->setUser($this->user_record('adm1'));
        $this->assertFalse(evaluation_manager::can_manage_evaluation($nine));
        $this->setAdminUser();
        $this->assertTrue(evaluation_manager::can_manage_evaluation($nine));
    }

    /**
     * @param string $key A key of $this->u.
     * @return \stdClass The user row, open_path included.
     */
    private function user_record(string $key): \stdClass {
        global $DB;
        return $DB->get_record('user', ['id' => $this->u[$key]], '*', MUST_EXIST);
    }

    // The pure mapping, without a database.

    public function test_the_answer_mapper_reads_bizlms_storage(): void {
        $item = static fn(string $typ, string $presentation): \stdClass => (object) ['typ' => $typ, 'presentation' => $presentation];
        $mapper = bizlms\answer_mapper::class;

        $radio = $mapper::describe($item('multichoice', 'r>>>>>Good|<b>B</b>|Bad<<<<<1'));
        $this->assertSame('multichoice', $radio->type);
        $this->assertSame([true, 'B'], $mapper::map_value($radio, '2'));
        $this->assertSame([true, null], $mapper::map_value($radio, '0'));
        $this->assertSame([false, null], $mapper::map_value($radio, '4'));
        $this->assertSame([false, null], $mapper::map_value($radio, 'Good'));

        $boxes = $mapper::describe($item('multichoice', 'c>>>>>A|B|C<<<<<0'));
        $this->assertSame('multichoice_multi', $boxes->type);
        $this->assertSame([true, ['A', 'C']], $mapper::map_value($boxes, '1|3'));
        $this->assertSame([true, ['C', 'A']], $mapper::map_value($boxes, '3|3|1'));
        $this->assertSame([false, null], $mapper::map_value($boxes, '1|9'));

        $number = $mapper::describe($item('numeric', '1.5|9.5'));
        $this->assertSame([true, 7.25], $mapper::map_value($number, '7.25'));
        $this->assertSame([true, 7], $mapper::map_value($number, '7.0'));
        $this->assertSame([false, null], $mapper::map_value($number, 'seven'));
        $this->assertSame('{"min":1,"max":9}', $number->options_json());

        $text = $mapper::describe($item('textarea', ''));
        $this->assertSame([true, "Tom & Jerry 'x'"], $mapper::map_value($text, 'Tom &amp; Jerry &#039;x&#039;'));
        $this->assertSame([true, '&amp;'], $mapper::map_value($text, '&amp;amp;'), 'decoded once, no more');
        $this->assertSame([true, null], $mapper::map_value($text, '  '));

        $this->assertNull($mapper::describe($item('info', '')));
        $this->assertTrue($mapper::is_non_question_type('pagebreak'));
        $this->assertFalse($mapper::is_known_type('mystery'));
        $this->assertSame('One Two', $mapper::normalise_text('<p>One</p><p>Two</p>'));
    }
}
