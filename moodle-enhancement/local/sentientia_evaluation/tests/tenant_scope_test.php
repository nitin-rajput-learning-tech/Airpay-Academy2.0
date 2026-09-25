<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: :manage says WHAT, never WHERE.
 *
 * Tenant admins hold a manager-archetype role at system context and with it
 * local/sentientia_evaluation:manage. Until 2026-09-25 that let any of them
 * read any tenant's respondents and answers by id, delete or re-status any
 * tenant's evaluation, list another tenant's evaluations through the org
 * cascade, and create a GLOBAL (costcenterid 0) evaluation - which
 * evaluation_engine sends to every tenant's learners - then export the
 * answers. A caller with no tenant got an unfiltered audience of every
 * tenant's users. These tests pin the fix.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\evaluation_manager
 * @covers     \local_sentientia_evaluation\evaluation_audience_assigner
 * @covers     \local_sentientia_evaluation\external\list_evaluations
 * @covers     \local_sentientia_evaluation\external\delete_evaluation
 * @covers     \local_sentientia_evaluation\external\change_status
 * @covers     \local_sentientia_evaluation\external\delete_question
 * @covers     \local_sentientia_evaluation\external\reorder_questions
 * @covers     \local_sentientia_evaluation\external\preview_audience
 * @covers     \local_sentientia_evaluation\external\submit_response
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int org ids */
    private int $org1;
    /** @var int */
    private int $org177;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->org1 = $this->seed_org('/1');
        $this->org177 = $this->seed_org('/177');
    }

    private function seed_org(string $path): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname'     => 'Org ' . $path,
            'shortname'    => 'org' . str_replace('/', '_', $path),
            'parentid'     => 0,
            'path'         => $path,
            'depth'        => substr_count($path, '/'),
            'visible'      => 1,
            'sortorder'    => 0,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /** An evaluation bound to $orgid at $path (0 / null = a global evaluation). */
    private function seed_evaluation(string $name, int $orgid, ?string $path): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_evaluation', (object) [
            'name'              => $name,
            'description'       => '',
            'kirkpatrick_level' => 1,
            'trigger_event'     => 'manual',
            'days_after'        => 0,
            'costcenterid'      => $orgid,
            'open_path'         => $path,
            'status'            => evaluation_manager::STATUS_ACTIVE,
            'anonymous'         => 0,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    private function seed_question(int $evaluationid): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_evaluation_questions', (object) [
            'evaluationid' => $evaluationid,
            'questiontype' => 'rating',
            'questiontext' => 'How was it?',
            'options'      => null,
            'required'     => 1,
            'sortorder'    => 0,
            'timecreated'  => time(),
        ]);
    }

    private function seed_response(int $evaluationid, int $questionid, int $userid): void {
        global $DB;
        $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid'  => $evaluationid,
            'userid'        => $userid,
            'response_data' => json_encode([$questionid => 5]),
            'timesubmitted' => time(),
        ]);
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin: the stock manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function assert_outoftenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    public function test_tenant_admin_cannot_touch_another_tenants_evaluation(): void {
        global $DB;
        $foreign = $this->seed_evaluation('ZEEA feedback', $this->org177, '/177');
        $qid = $this->seed_question($foreign);
        $this->setUser($this->tenant_admin('/1'));

        $record = $DB->get_record('local_sentientia_evaluation', ['id' => $foreign], '*', MUST_EXIST);
        $this->assertFalse(evaluation_manager::can_manage_evaluation($record));
        $this->assert_outoftenant(fn() => evaluation_manager::require_evaluation_access($record),
            'Page gate (exportcsv/responses/non_respondents/questions/export_template).');
        $this->assert_outoftenant(fn() => external\delete_evaluation::execute($foreign),
            'A /1 tenant admin must not delete a /177 evaluation and its responses.');
        $this->assert_outoftenant(fn() => external\change_status::execute($foreign, 2),
            'A /1 tenant admin must not archive a /177 evaluation.');
        $this->assert_outoftenant(fn() => external\delete_question::execute($qid),
            'A /1 tenant admin must not delete a /177 evaluation\'s question.');
        $this->assert_outoftenant(fn() => external\reorder_questions::execute($foreign, [$qid]),
            'A /1 tenant admin must not reorder a /177 evaluation.');
        $_POST['sesskey'] = sesskey();
        $this->assert_outoftenant(fn() => external\bulk_assign_by_audience::execute($foreign, '{}'),
            'A /1 tenant admin must not assign people to a /177 evaluation.');
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_assign',
            ['evaluationid' => $foreign]));

        $this->assertTrue($DB->record_exists('local_sentientia_evaluation', ['id' => $foreign]));
        $this->assertTrue($DB->record_exists('local_sentientia_evaluation_questions', ['id' => $qid]));
        $this->assertSame(evaluation_manager::STATUS_ACTIVE,
            (int) $DB->get_field('local_sentientia_evaluation', 'status', ['id' => $foreign]));
    }

    public function test_global_evaluation_is_cross_tenant_only(): void {
        global $DB;
        $global = $this->seed_evaluation('Everyone', 0, null);
        // Inconsistent row: global (costcenterid 0) but carrying a /1 path.
        // evaluation_engine still sends it to every tenant, so it is global.
        $mixed = $this->seed_evaluation('Mixed', 0, '/1');

        $this->setUser($this->tenant_admin('/1'));
        foreach ([$global, $mixed] as $id) {
            $this->assert_outoftenant(fn() => external\delete_evaluation::execute($id),
                'A tenant admin must not manage a global evaluation.');
        }
        $this->assertSame(0, (int) external\list_evaluations::execute()['total']);

        $this->setAdminUser();
        $this->assertSame(2, (int) external\list_evaluations::execute()['total']);
        external\delete_evaluation::execute($global);
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation', ['id' => $global]));
    }

    public function test_tenant_admin_keeps_their_own_tenant(): void {
        global $DB;
        $own = $this->seed_evaluation('Airpay POSH', $this->org1, '/1');
        $qid = $this->seed_question($own);
        $this->setUser($this->tenant_admin('/1'));

        $this->assertTrue(evaluation_manager::can_manage_evaluation(
            $DB->get_record('local_sentientia_evaluation', ['id' => $own])));
        external\change_status::execute($own, evaluation_manager::STATUS_ARCHIVED);
        $this->assertSame(evaluation_manager::STATUS_ARCHIVED,
            (int) $DB->get_field('local_sentientia_evaluation', 'status', ['id' => $own]));
        external\reorder_questions::execute($own, [$qid]);
        external\delete_question::execute($qid);
        external\delete_evaluation::execute($own);
        $this->assertFalse($DB->record_exists('local_sentientia_evaluation', ['id' => $own]));
    }

    public function test_cascade_and_aggregates_stay_in_tenant(): void {
        $own = $this->seed_evaluation('Airpay POSH', $this->org1, '/1');
        $foreign = $this->seed_evaluation('ZEEA feedback', $this->org177, '/177');
        $learner1 = $this->user_at('/1/5');
        $learner177 = $this->user_at('/177');
        $this->seed_response($own, $this->seed_question($own), (int) $learner1->id);
        $this->seed_response($foreign, $this->seed_question($foreign), (int) $learner177->id);

        $this->setUser($this->tenant_admin('/1'));
        $cascade = json_encode(['org_l1' => $this->org177]);
        $this->assertSame(0, (int) external\list_evaluations::execute('', 'name', 'asc', 0, 25, $cascade)['total'],
            'Another tenant\'s org id in the cascade must not list that tenant\'s evaluations.');
        $this->assertSame(1, (int) external\list_evaluations::execute()['total']);
        $this->assertSame(1, evaluation_manager::count_evaluations_scoped());
        $this->assertSame(1, evaluation_manager::count_responses_scoped());
        $summary = evaluation_manager::get_kirkpatrick_summary();
        $this->assertSame(1, $summary[1]['evaluation_count']);
        $this->assertSame(1, $summary[1]['response_count'], 'Analysis must not aggregate other tenants.');

        $this->setAdminUser();
        $this->assertSame(1, (int) external\list_evaluations::execute('', 'name', 'asc', 0, 25, $cascade)['total']);
        $this->assertSame(2, evaluation_manager::count_evaluations_scoped());
        $this->assertSame(2, evaluation_manager::count_responses_scoped());
        $this->assertSame(2, evaluation_manager::get_kirkpatrick_summary()[1]['response_count']);
    }

    public function test_authoring_stays_in_tenant(): void {
        global $DB;
        $this->setUser($this->tenant_admin('/1'));

        $this->assert_outoftenant(fn() => evaluation_manager::scoped_costcenterid($this->org177),
            'A /1 tenant admin must not bind an evaluation to a /177 org.');
        // "No specific organisation" becomes the caller's tenant root, never global.
        $this->assertSame('/1', $DB->get_field('local_sentientia_org', 'path',
            ['id' => evaluation_manager::scoped_costcenterid(0)]));
        $this->assertSame($this->org1, evaluation_manager::scoped_costcenterid($this->org1));

        $options = evaluation_manager::org_options();
        $this->assertArrayHasKey($this->org1, $options);
        $this->assertArrayNotHasKey($this->org177, $options);
        $this->assertArrayNotHasKey(0, $options, 'Only a cross-tenant caller may make a global evaluation.');

        $this->setAdminUser();
        $this->assertSame(0, evaluation_manager::scoped_costcenterid(0));
        $this->assertSame($this->org177, evaluation_manager::scoped_costcenterid($this->org177));
        $this->assertArrayHasKey(0, evaluation_manager::org_options());
    }

    public function test_audience_is_tenant_scoped_and_fails_closed(): void {
        $this->user_at('/1/5');
        $zeea = $this->user_at('/177');

        $admin1 = $this->tenant_admin('/1');
        $this->setUser($admin1);
        $preview = external\preview_audience::execute('{}');
        $ids = array_column($preview['sample'], 'id');
        $this->assertNotContains((int) $zeea->id, $ids);
        $this->assertSame(2, $preview['count'], 'The /1 admin and the /1/5 learner only.');
        $this->assertSame(0, external\preview_audience::execute(json_encode(['org_path' => '/177']))['count'],
            'An org_path filter must not reach outside the caller\'s tenant.');

        $nowhere = $this->tenant_admin('');
        $this->setUser($nowhere);
        $this->assertSame(0, external\preview_audience::execute('{}')['count'],
            'A caller with no tenant used to get every tenant\'s users.');
        $this->assertSame([], evaluation_audience_assigner::resolve_audience([], (int) $nowhere->id));

        $this->setAdminUser();
        $this->assertContains((int) $zeea->id,
            evaluation_audience_assigner::resolve_audience([], (int) get_admin()->id));
    }

    public function test_caller_with_no_tenant_gets_nothing(): void {
        global $DB;
        $own = $this->seed_evaluation('Airpay POSH', $this->org1, '/1');
        $this->setUser($this->tenant_admin(''));

        $this->assertSame(0, (int) external\list_evaluations::execute()['total']);
        $this->assertSame(0, (int) external\list_evaluations::execute('', 'name', 'asc', 0, 25,
            json_encode(['org_l1' => $this->org1]))['total']);
        $this->assertSame(0, evaluation_manager::count_evaluations_scoped());
        $this->assert_outoftenant(fn() => external\delete_evaluation::execute($own),
            'A caller with no tenant must not delete anything.');
        $this->assert_outoftenant(fn() => evaluation_manager::scoped_costcenterid(0),
            'A caller with no tenant must not create anything.');
        $this->assertSame([], evaluation_manager::org_options());
        $this->assertTrue($DB->record_exists('local_sentientia_evaluation', ['id' => $own]));
    }

    public function test_respondents_answer_only_their_tenant_or_global(): void {
        global $DB;
        $own = $DB->get_record('local_sentientia_evaluation',
            ['id' => $this->seed_evaluation('Airpay', $this->org1, '/1/2')]);
        $foreign = $DB->get_record('local_sentientia_evaluation',
            ['id' => $this->seed_evaluation('ZEEA', $this->org177, '/177')]);
        $global = $DB->get_record('local_sentientia_evaluation',
            ['id' => $this->seed_evaluation('Everyone', 0, null)]);
        $learner = $this->user_at('/1/7');
        $nowhere = $this->user_at('');

        $this->assertTrue(evaluation_manager::can_respond($own, $learner));
        $this->assertTrue(evaluation_manager::can_respond($global, $learner));
        $this->assertFalse(evaluation_manager::can_respond($foreign, $learner));
        $this->assertFalse(evaluation_manager::can_respond($own, $nowhere));
        $this->assertTrue(evaluation_manager::can_respond($global, $nowhere));

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sentientia_evaluation:respond', CAP_ALLOW, $roleid,
            \context_system::instance()->id);
        role_assign($roleid, $learner->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($learner);
        $this->assert_outoftenant(
            fn() => external\submit_response::execute((int) $foreign->id, json_encode([]), '{}'),
            'A /1 learner must not post answers into a /177 evaluation.');
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_responses',
            ['evaluationid' => $foreign->id]));
    }
}
