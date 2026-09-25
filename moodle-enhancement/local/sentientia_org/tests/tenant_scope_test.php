<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031: the org tree is bounded to the caller's tenant, and fails closed.
 *
 *  - cascade_where_sql() turned a client-supplied org id into a fragment that
 *    six list_* web services use INSTEAD of their tenant filter, without
 *    checking the org was in the caller's tenant;
 *  - list_children matched every org path for a caller whose open_path was
 *    empty ('' . '/' prefixes everything);
 *  - admin.php loaded every tenant's tree and headcounts for any :view holder.
 *
 * @package    local_sentientia_org
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_org\org_manager
 * @covers \local_sentientia_org\external\list_children
 * @covers \local_sentientia_org\external\toggle_visibility
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** @var \stdClass tenant A root (the caller's tenant) */
    private $orga;
    /** @var \stdClass a department under tenant A */
    private $orgachild;
    /** @var \stdClass tenant B root (someone else's tenant) */
    private $orgb;
    /** @var \stdClass a department under tenant B */
    private $orgbchild;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->orga = $this->make_org('TSA');
        $this->orgachild = $this->make_org('TSA_DEPT', $this->orga);
        $this->orgb = $this->make_org('TSB');
        $this->orgbchild = $this->make_org('TSB_DEPT', $this->orgb);
    }

    /** Insert an org node; its path is built from real ids, as in production. */
    private function make_org(string $shortname, ?\stdClass $parent = null): \stdClass {
        global $DB;
        $id = (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => $shortname . ' name', 'shortname' => $shortname,
            'parentid' => $parent ? (int) $parent->id : 0, 'path' => '',
            'depth' => $parent ? (int) $parent->depth + 1 : 1, 'visible' => 1,
            'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $path = ($parent ? $parent->path : '') . '/' . $id;
        $DB->set_field('local_sentientia_org', 'path', $path, ['id' => $id]);
        return $DB->get_record('local_sentientia_org', ['id' => $id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: a manager-archetype role at system context. */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $u->id, \context_system::instance()->id);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** Ids of list_children rows, sorted. */
    private function child_ids(int $parentid): array {
        $ids = array_map('intval', array_column(external\list_children::execute($parentid)['rows'], 'id'));
        sort($ids);
        return $ids;
    }

    public function test_the_cascade_never_leaves_a_scoped_callers_tenant(): void {
        $this->setUser($this->tenant_admin($this->orga->path));
        $this->assertSame(['1=0', []], org_manager::cascade_where_sql(['org_l1' => (int) $this->orgb->id], 'p'),
            'Another tenant\'s root must match nothing, not replace the tenant filter.');
        $this->assertSame(['1=0', []], org_manager::cascade_where_sql(['org_l2' => (int) $this->orgbchild->id], 'p'));
        [$sql, $args] = org_manager::cascade_where_sql(['org_l2' => (int) $this->orgachild->id], 'p');
        $this->assertNotSame('1=0', $sql, 'An org in the caller\'s own tenant still narrows the list.');
        $this->assertContains($this->orgachild->path, $args);
        $this->assertSame(['', []], org_manager::cascade_where_sql([], 'p'),
            'No cascade: the caller\'s own tenant filter applies.');

        $this->setUser($this->tenant_admin(''));
        $this->assertSame(['1=0', []], org_manager::cascade_where_sql(['org_l1' => (int) $this->orga->id], 'p'),
            'A caller with no tenant cascades into nothing.');

        $this->setAdminUser();
        [$sql, $args] = org_manager::cascade_where_sql(['org_l1' => (int) $this->orgb->id], 'p');
        $this->assertNotSame('1=0', $sql, 'The site admin may cascade into any tenant.');
        $this->assertContains($this->orgb->path, $args);
    }

    public function test_list_children_is_bounded_and_fails_closed(): void {
        global $DB;
        // A legacy root with no path belongs to no tenant.
        $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Pathless', 'shortname' => 'TSNOPATH', 'parentid' => 0, 'path' => '',
            'depth' => 1, 'visible' => 1, 'sortorder' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ]);

        $this->setUser($this->tenant_admin($this->orga->path));
        $this->assertSame([(int) $this->orga->id], $this->child_ids(0), 'Only the caller\'s own tenant root.');
        $this->assertSame([(int) $this->orgachild->id], $this->child_ids((int) $this->orga->id));
        $this->assertSame([], $this->child_ids((int) $this->orgb->id), 'Not another tenant\'s departments.');

        foreach (['', 'garbage'] as $path) {
            $this->setUser($this->tenant_admin($path));
            $this->assertSame([], $this->child_ids(0), "open_path '{$path}' must not list any tenant.");
        }

        $this->setAdminUser();
        $roots = $this->child_ids(0);
        $this->assertContains((int) $this->orga->id, $roots);
        $this->assertContains((int) $this->orgb->id, $roots);
    }

    public function test_the_admin_tree_holds_only_the_callers_tenant(): void {
        $this->setUser($this->tenant_admin($this->orga->path));
        $ids = array_map('intval', array_keys(org_manager::get_all_in_scope()));
        sort($ids);
        $this->assertSame([(int) $this->orga->id, (int) $this->orgachild->id], $ids);

        $this->setUser($this->tenant_admin(''));
        $this->assertSame([], org_manager::get_all_in_scope());

        $this->setAdminUser();
        $all = org_manager::get_all_in_scope();
        $this->assertArrayHasKey((int) $this->orgb->id, $all);
        $this->assertArrayHasKey((int) $this->orgbchild->id, $all);
    }

    public function test_path_scope_is_slash_bounded(): void {
        global $DB;
        $caller = $this->tenant_admin('/1');
        $this->setUser($caller);
        $this->assertTrue(org_manager::path_in_scope('/1'));
        $this->assertTrue(org_manager::path_in_scope('/1/5'));
        $this->assertFalse(org_manager::path_in_scope('/10'), '/1 never admits /10.');
        $this->assertFalse(org_manager::path_in_scope('/177'));
        $this->assertFalse(org_manager::path_in_scope(''), 'A path-less row belongs to no tenant.');
    }

    public function test_org_writes_check_the_target_tenant(): void {
        global $DB;
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        // :manage has no default; even a deliberate grant stays inside the tenant.
        assign_capability('local/sentientia_org:manage', CAP_ALLOW, $managerroleid,
            \context_system::instance()->id, true);
        $pathless = (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Pathless leaf', 'shortname' => 'TSNOPATH2', 'parentid' => (int) $this->orgb->id,
            'path' => '', 'depth' => 2, 'visible' => 1, 'sortorder' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $this->setUser($this->tenant_admin($this->orga->path));

        foreach ([(int) $this->orgbchild->id => 'another tenant\'s org', $pathless => 'a path-less org'] as $id => $what) {
            try {
                external\toggle_visibility::execute($id);
                $this->fail("Toggling {$what} must be refused.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        }
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_org', 'visible', ['id' => $this->orgbchild->id]));
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_org', 'visible', ['id' => $pathless]));

        // The in-tenant function survives.
        $this->assertFalse(external\toggle_visibility::execute((int) $this->orgachild->id)['visible']);

        $this->setAdminUser();
        $this->assertFalse(external\toggle_visibility::execute((int) $this->orgbchild->id)['visible']);
    }
}
