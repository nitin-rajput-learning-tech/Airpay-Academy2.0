<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_classroom\external\bulk_enrol_by_audience;
use local_sentientia_classroom\external\preview_audience;
use local_sentientia_platform\feature_flags;

/**
 * Bulk enrolment of a classroom by target audience works again (owner decision XC-CLS-ENROL, 2026-10-07).
 *
 * The page, the form and both web services gated on local/sentientia_classroom:enrol, which no db/access.php
 * declares, so the surface refused everyone, site admins included. They gate on :manage now, behind the default-OFF
 * flag sentientia.classroom.bulk_enrol_audience (a dead page coming back is a new surface), and a filter that names
 * no criterion is refused instead of enrolling the whole tenant (the rule the evaluation bulk assign got in EV-36).
 * The ADR-031 tenant bound is unchanged.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_classroom\classroom_audience_enroller
 * @covers     \local_sentientia_classroom\external\bulk_enrol_by_audience
 * @covers     \local_sentientia_classroom\external\preview_audience
 * @covers     \local_sentientia_classroom\form\bulk_enrol_audience_form
 * @group local_sentientia_classroom
 * @group tenant_isolation
 */
final class bulk_enrol_audience_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        feature_flags::invalidate_caches();
    }

    private function user_at(?string $path, string $designation = 'auddesig'): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $DB->set_field('user', 'open_designation', $designation, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A manager-archetype role at system context: holds :manage, as a tenant admin does. */
    private function tenant_admin(?string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path, 'admin');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function classroom(?string $path): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => 'Classroom ' . $path, 'description' => '', 'costcenterid' => 0, 'open_path' => $path,
            'location' => 'Room', 'capacity' => 100, 'status' => session_manager::STATUS_ACTIVE,
            'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    private function switch_on(): void {
        $this->setAdminUser();
        feature_flags::set(classroom_audience_enroller::FLAG, 0, true);
    }

    private function roster(int $classroomid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('local_sentientia_classroom_users', 'userid',
            'classroomid = :c', ['c' => $classroomid], 'userid ASC'));
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia.classroom.bulk_enrol_audience', $registry);
        $this->assertSame('sentientia.classroom.bulk_enrol_audience', classroom_audience_enroller::FLAG);
        $this->assertFalse($registry[classroom_audience_enroller::FLAG]['default']);
        $this->setAdminUser();
        $this->assertFalse(classroom_audience_enroller::enabled());
    }

    public function test_with_the_flag_off_the_services_and_the_form_refuse_even_a_site_admin(): void {
        $classroom = $this->classroom('/1');
        $learner = $this->user_at('/1/5');
        $filters = json_encode(['designation' => 'auddesig']);

        foreach ([$this->tenant_admin('/1'), get_admin()] as $caller) {
            $this->setUser($caller);
            $refused = [
                'preview' => fn() => preview_audience::execute($filters),
                'enrol' => fn() => bulk_enrol_by_audience::execute($classroom, $filters),
                'form' => fn() => new form\bulk_enrol_audience_form(null, null, 'post', '', [], true,
                    ['classroomid' => $classroom], true),
            ];
            foreach ($refused as $what => $call) {
                try {
                    $call();
                    $this->fail($what . ' ran with the flag off');
                } catch (\moodle_exception $e) {
                    $this->assertSame('audience_not_enabled', $e->errorcode, $what);
                }
            }
        }
        $this->assertSame([], $this->roster($classroom), 'nothing was enrolled');
        $this->assertNotNull($learner);
    }

    public function test_with_the_flag_on_a_manager_enrols_the_matching_users_of_their_own_tenant_only(): void {
        $mine = $this->classroom('/1');
        $inside = [$this->user_at('/1/5'), $this->user_at('/1/6')];
        $other = $this->user_at('/177/9');
        $differentdesignation = $this->user_at('/1/5', 'someoneelse');
        $this->switch_on();
        $admin = $this->tenant_admin('/1');
        $this->setUser($admin);

        $preview = preview_audience::execute(json_encode(['designation' => 'auddesig']));
        $this->assertSame(2, (int) $preview['count'], 'the preview is inside the caller\'s tenant');

        $result = bulk_enrol_by_audience::execute($mine, json_encode(['designation' => 'auddesig']));
        $this->assertSame(2, (int) $result['matched']);
        $this->assertSame(2, (int) $result['enrolled']);
        $expected = array_map(static fn(\stdClass $u): int => (int) $u->id, $inside);
        sort($expected);
        $this->assertSame($expected, $this->roster($mine));
        $this->assertNotContains((int) $other->id, $this->roster($mine), 'another tenant\'s learner is never enrolled');
        $this->assertNotContains((int) $differentdesignation->id, $this->roster($mine));

        // Running it again enrols nobody new.
        $again = bulk_enrol_by_audience::execute($mine, json_encode(['designation' => 'auddesig']));
        $this->assertSame(0, (int) $again['enrolled']);
    }

    public function test_the_form_opens_for_a_manager_with_the_flag_on(): void {
        $mine = $this->classroom('/1');
        $this->switch_on();
        $this->setUser($this->tenant_admin('/1'));

        $form = new form\bulk_enrol_audience_form(null, null, 'post', '', [], true, ['classroomid' => $mine], true);
        $this->assertInstanceOf(form\bulk_enrol_audience_form::class, $form);
    }

    public function test_a_filter_that_names_no_criterion_is_refused_and_enrols_nobody(): void {
        $mine = $this->classroom('/1');
        $this->user_at('/1/5');
        $this->switch_on();
        $this->setUser($this->tenant_admin('/1'));

        foreach (['{}', json_encode(['designation' => '']), json_encode(['designation' => '  ', 'cohortid' => 0, 'region' => '']),
                'not json', json_encode(['unknownkey' => 'x'])] as $filters) {
            try {
                bulk_enrol_by_audience::execute($mine, $filters);
                $this->fail('an empty filter would enrol the whole tenant: ' . $filters);
            } catch (\moodle_exception $e) {
                $this->assertSame('audience_pick_at_least_one', $e->errorcode, $filters);
            }
        }
        $this->assertSame([], $this->roster($mine));

        // An explicit whole-tenant enrolment names the tenant's org_path.
        $result = bulk_enrol_by_audience::execute($mine, json_encode(['org_path' => '/1']));
        $this->assertGreaterThanOrEqual(1, (int) $result['enrolled']);
    }

    public function test_the_pure_empty_filter_rule(): void {
        $this->assertTrue(classroom_audience_enroller::is_empty_filter([]));
        $this->assertTrue(classroom_audience_enroller::is_empty_filter(['designation' => '', 'cohortid' => 0, 'org_path' => ' ']));
        $this->assertTrue(classroom_audience_enroller::is_empty_filter(['cohortid' => '0', 'bogus' => 'x']));
        $this->assertFalse(classroom_audience_enroller::is_empty_filter(['designation' => 'x']));
        $this->assertFalse(classroom_audience_enroller::is_empty_filter(['org_path' => '/1']));
        $this->assertFalse(classroom_audience_enroller::is_empty_filter(['cohortid' => 3]));
        $this->assertFalse(classroom_audience_enroller::is_empty_filter(['grade' => 'G5']));
    }

    public function test_another_tenants_classroom_and_a_caller_without_manage_are_refused_with_the_flag_on(): void {
        $theirs = $this->classroom('/177');
        $this->user_at('/1/5');
        $this->switch_on();

        $this->setUser($this->tenant_admin('/1'));
        try {
            bulk_enrol_by_audience::execute($theirs, json_encode(['designation' => 'auddesig']));
            $this->fail('another tenant\'s classroom was enrolled into');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode);
        }
        $this->assertSame([], $this->roster($theirs));

        // A user who holds no capability of this plugin at all.
        $learner = $this->user_at('/1/5', 'learner');
        $this->setUser($learner);
        $this->expectException(\required_capability_exception::class);
        bulk_enrol_by_audience::execute($this->classroom('/1'), json_encode(['designation' => 'auddesig']));
    }
}
