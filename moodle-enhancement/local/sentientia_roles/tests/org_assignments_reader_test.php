<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_roles;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The role-holder list at organisation level (ADR-032 org_roles, mapping doc "Code fixes"): BizLMS org roles are
 * real role assignments at the course category of the organisation, and role_manager::list_role_assignments() used
 * to read the system context only, so every one of them was invisible in the Sentientia UI.
 *
 * The flag sentientia.roles.org_assignments (default OFF) adds them. OFF, the list is what it always was. ON, a
 * caller who is not cross-tenant sees only holders in their own tenant, at organisations inside their own tenant,
 * and every comparison of paths is bounded (an organisation at /10 is not inside /1).
 *
 * Fixture: three organisations as BizLMS local_costcenter rows (A at /1, B at /77, C at /10), each with a course
 * category; one role assigned in the system context and at the categories:
 *
 *   system  a1            A  a1, a2 (+ another role's holder, who must not show)
 *   B       a1, b1        C  a1
 *
 * where a1 and a2 belong to tenant 1 (/1/5 and /1/6) and b1 to tenant 77.
 *
 * @package    local_sentientia_roles
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers     \local_sentientia_roles\role_manager
 * @covers     \local_sentientia_roles\external\list_role_assignments
 *
 * @group local_sentientia_roles
 * @group tenant_isolation
 */
final class org_assignments_reader_test extends \advanced_testcase {
    use legacy_schema_fixture {
        setUp as protected legacy_fixture_setup;
    }
    use \local_sentientia_org\test\bizlms_fixture;

    /** The flag under test. */
    private const FLAG = 'sentientia.roles.org_assignments';

    /** @var int The role whose holders are listed. */
    private int $roleid = 0;

    /** @var int[] name => user id */
    private array $u = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/costcenter.install.xml', 'only' => ['local_costcenter']];
    }

    protected function setUp(): void {
        global $DB;
        $this->legacy_fixture_setup();
        $this->ensure_bizlms_schema();

        $gen = $this->getDataGenerator();
        $this->roleid = create_role('Org holder', 'orgholdertest', '');
        $otherrole = create_role('Other holder', 'otherholdertest', '');

        foreach (['a1' => '/1/5', 'a2' => '/1/6', 'b1' => '/77'] as $name => $path) {
            $user = $gen->create_user(['firstname' => strtoupper($name), 'lastname' => 'Holder']);
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
            $this->u[$name] = (int) $user->id;
        }

        $contexts = [];
        foreach ([1 => ['Org A', '/1'], 77 => ['Org B', '/77'], 10 => ['Org C', '/10']] as $id => [$name, $path]) {
            $category = $gen->create_category(['name' => $name]);
            $DB->import_record('local_costcenter', (object) ['id' => $id, 'fullname' => $name, 'shortname' => 'o' . $id,
                'path' => $path, 'category' => $category->id, 'timecreated' => 1700000000, 'timemodified' => 1700000000]);
            $contexts[$id] = \context_coursecat::instance($category->id)->id;
        }

        role_assign($this->roleid, $this->u['a1'], \context_system::instance()->id);
        role_assign($this->roleid, $this->u['a1'], $contexts[1]);
        role_assign($this->roleid, $this->u['a2'], $contexts[1]);
        role_assign($this->roleid, $this->u['a1'], $contexts[77]);
        role_assign($this->roleid, $this->u['b1'], $contexts[77]);
        role_assign($this->roleid, $this->u['a1'], $contexts[10]);
        role_assign($otherrole, $this->u['a2'], $contexts[1]);
    }

    /**
     * What a list shows, as user@scope strings, sorted.
     *
     * @return string[]
     */
    private function holders(): array {
        $out = [];
        foreach (role_manager::list_role_assignments($this->roleid)['rows'] as $row) {
            $out[] = array_search($row['userid'], $this->u, true) . '@' . ($row['scopename'] ?? '-');
        }
        sort($out);
        return $out;
    }

    /**
     * An expected list, sorted the way holders() sorts.
     *
     * @param string[] $expected
     * @return string[]
     */
    private function sorted(array $expected): array {
        sort($expected);
        return $expected;
    }

    private function enable_flag(): void {
        $this->setAdminUser();
        feature_flags::set(self::FLAG, 0, true);
    }

    /** A tenant admin as UAT has them: a manager-archetype role at system context and a tenant path. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $admin = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $admin->id]);
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST), $admin->id,
            \context_system::instance()->id);
        return $DB->get_record('user', ['id' => $admin->id], '*', MUST_EXIST);
    }

    public function test_the_flag_is_registered_and_defaults_off(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(self::FLAG, $registry);
        $this->assertFalse($registry[self::FLAG]['default']);
        $this->setAdminUser();
        $this->assertFalse(feature_flags::is_enabled(self::FLAG));
    }

    public function test_with_the_flag_off_only_the_system_context_is_listed_as_before(): void {
        $this->setAdminUser();
        $page = role_manager::list_role_assignments($this->roleid);
        $this->assertSame(1, $page['total']);
        $this->assertSame($this->u['a1'], $page['rows'][0]['userid']);
        $this->assertArrayNotHasKey('scope', $page['rows'][0], 'the row has the keys it always had');
        $this->assertArrayNotHasKey('scopename', $page['rows'][0]);
    }

    public function test_with_the_flag_on_a_cross_tenant_caller_sees_every_organisation(): void {
        $this->enable_flag();
        $system = get_string('assignment_scope_system', 'local_sentientia_roles');
        $this->assertSame($this->sorted(['a1@' . $system, 'a1@Org A', 'a1@Org B', 'a1@Org C', 'a2@Org A', 'b1@Org B']),
            $this->holders());

        $page = role_manager::list_role_assignments($this->roleid);
        $this->assertSame(6, $page['total']);
        $scopes = array_column($page['rows'], 'scope');
        sort($scopes);
        $this->assertSame(['org', 'org', 'org', 'org', 'org', 'system'], $scopes);
    }

    public function test_a_tenant_admin_sees_their_tenant_only_at_organisations_inside_it(): void {
        $this->enable_flag();
        $this->setUser($this->tenant_admin('/1'));
        $system = get_string('assignment_scope_system', 'local_sentientia_roles');
        // Not a1 at Org B (an organisation of another tenant), not a1 at Org C (/10 is not inside /1), not b1.
        $this->assertSame($this->sorted(['a1@' . $system, 'a1@Org A', 'a2@Org A']), $this->holders());
        $this->assertSame(3, role_manager::list_role_assignments($this->roleid)['total']);
    }

    public function test_the_other_tenants_admin_sees_theirs(): void {
        $this->enable_flag();
        $this->setUser($this->tenant_admin('/77'));
        $this->assertSame(['b1@Org B'], $this->holders());
    }

    public function test_a_caller_with_no_tenant_sees_nothing_even_with_the_flag_on(): void {
        $this->enable_flag();
        $this->setUser($this->tenant_admin(''));
        $this->assertSame(0, role_manager::list_role_assignments($this->roleid)['total']);
        $this->assertSame([], $this->holders());
    }

    public function test_the_flag_changes_nothing_without_the_organisation_table(): void {
        $this->enable_flag();
        self::drop_legacy_table('local_costcenter');
        $page = role_manager::list_role_assignments($this->roleid);
        $this->assertSame(1, $page['total'], 'no organisations to list, so the system rows only');
        $this->assertArrayNotHasKey('scope', $page['rows'][0]);
    }

    public function test_the_web_service_shows_organisation_rows_but_gives_them_no_unassign_button(): void {
        $this->enable_flag();
        $result = \local_sentientia_roles\external\list_role_assignments::execute($this->roleid);
        $result = \core_external\external_api::clean_returnvalue(
            \local_sentientia_roles\external\list_role_assignments::execute_returns(), $result);

        $this->assertSame(6, $result['total']);
        foreach ($result['rows'] as $row) {
            if ($row['scope'] === 'system') {
                $this->assertStringContainsString('data-action="unassign-user"', $row['actions']);
            } else {
                $this->assertSame('org', $row['scope']);
                $this->assertSame('', $row['actions'], 'unassigning works on the system context only');
            }
        }
    }

    public function test_the_web_service_is_unchanged_with_the_flag_off(): void {
        $this->setAdminUser();
        $result = \local_sentientia_roles\external\list_role_assignments::execute($this->roleid);
        $result = \core_external\external_api::clean_returnvalue(
            \local_sentientia_roles\external\list_role_assignments::execute_returns(), $result);
        $this->assertSame(1, $result['total']);
        $this->assertArrayNotHasKey('scope', $result['rows'][0]);
        $this->assertStringContainsString('data-action="unassign-user"', $result['rows'][0]['actions']);
    }
}
