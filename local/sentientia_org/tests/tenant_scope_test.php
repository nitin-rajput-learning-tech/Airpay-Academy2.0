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
 *  - admin.php loaded every tenant's tree and headcounts for any :view holder;
 *  - edit_org scope-checked the RAW posted parentid, but a value not on the
 *    parent select was exported as null and created a new top-level tenant.
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
 * @covers \local_sentientia_org\form\edit_org
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

    /**
     * Build an edit_org submission as the current user, the way the modal posts it.
     *
     * @param array $formdata overrides for a new-org submission
     */
    private function org_form(array $formdata): form\edit_org {
        $data = form\edit_org::mock_ajax_submit($formdata + [
            'orgid' => 0, 'fullname' => 'New node', 'shortname' => '', 'description' => '',
            'visible' => 1, 'sortorder' => 0,
        ]);
        $form = new form\edit_org(null, null, 'post', '', null, true, $data, true);
        $form->set_data_for_dynamic_submission();
        return $form;
    }

    public function test_a_new_org_must_hang_under_a_parent_in_the_callers_scope(): void {
        global $DB;
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        // :manage has no default; even a deliberate grant stays inside the tenant.
        assign_capability('local/sentientia_org:manage', CAP_ALLOW, $managerroleid,
            \context_system::instance()->id, true);
        // A depth-5 node of tenant A's own. The parent select offers depth <= 4
        // only, so posting it was exported as null - and created a new TENANT.
        $d3 = $this->make_org('TSA_D3', $this->orgachild);
        $d4 = $this->make_org('TSA_D4', $d3);
        $d5 = $this->make_org('TSA_D5', $d4);
        $this->assertSame(5, (int) $d5->depth, 'Precondition: a depth-5 org.');
        $this->setUser($this->tenant_admin($this->orga->path));
        $roots = $DB->count_records('local_sentientia_org', ['parentid' => 0]);

        foreach ([(int) $d5->id => 'a depth-5 org of their own (not on offer)',
                0 => 'no parent at all (a new top-level tenant)',
                (int) $this->orgbchild->id => 'another tenant\'s org',
                999999 => 'a missing org'] as $parentid => $what) {
            // Either refusal is correct: the access check (run by the dynamic
            // form constructor) throws error_outoftenant for a parent outside
            // the caller's tenant, a missing one or none; validation() refuses
            // an in-tenant parent the select does not offer.
            try {
                $form = $this->org_form(['parentid' => $parentid, 'fullname' => 'Sneaky ' . $parentid]);
                $this->assertFalse($form->is_validated(), "Creating under {$what} must be refused.");
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode, "Creating under {$what} must be refused.");
            }
        }
        $this->assertSame($roots, $DB->count_records('local_sentientia_org', ['parentid' => 0]),
            'A scoped :manage holder must never create a top-level tenant.');
        $this->assertFalse($DB->record_exists_select('local_sentientia_org',
            $DB->sql_like('fullname', ':name'), ['name' => 'Sneaky%']));

        // The in-scope function survives: an offered parent in their own tenant.
        $form = $this->org_form(['parentid' => (int) $this->orgachild->id, 'fullname' => 'Legit dept']);
        $this->assertTrue($form->is_validated());
        $newid = (int) $form->process_dynamic_submission()['orgid'];
        $new = $DB->get_record('local_sentientia_org', ['id' => $newid], '*', MUST_EXIST);
        $this->assertSame((int) $this->orgachild->id, (int) $new->parentid);
        $this->assertSame($this->orgachild->path . '/' . $newid, $new->path);

        // A cross-tenant caller may still create a new top-level tenant.
        $this->setAdminUser();
        $form = $this->org_form(['parentid' => 0, 'fullname' => 'Brand new tenant']);
        $this->assertTrue($form->is_validated());
        $tenantid = (int) $form->process_dynamic_submission()['orgid'];
        $tenant = $DB->get_record('local_sentientia_org', ['id' => $tenantid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $tenant->parentid);
        $this->assertSame('/' . $tenantid, $tenant->path);
        // ...but not by posting a parent the select does not offer.
        $form = $this->org_form(['parentid' => (int) $d5->id, 'fullname' => 'Too deep']);
        $this->assertFalse($form->is_validated(),
            'An unoffered parent is an error for everyone, not a silent new tenant.');
    }

    /** An org at a literal path, as UAT has them: Airpay /1, Public /77, ZEEA /177. */
    private function org_at(string $path, int $parentid = 0): \stdClass {
        global $DB;
        $id = (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Org ' . $path, 'shortname' => 'ORG' . str_replace('/', '_', $path),
            'parentid' => $parentid, 'path' => $path,
            'depth' => substr_count($path, '/'), 'visible' => 1,
            'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('local_sentientia_org', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_the_airpay_tenant_admin_is_held_to_slash_1_not_zeea_177(): void {
        global $DB;
        // The literal UAT tenants, without setUp's synthetic ones (whose
        // id-derived paths could collide with /1).
        $DB->delete_records('local_sentientia_org');
        $airpay = $this->org_at('/1');
        $airpaydept = $this->org_at('/1/2', (int) $airpay->id);
        $zeea = $this->org_at('/177');
        $zeeadept = $this->org_at('/177/178', (int) $zeea->id);
        $trap = $this->org_at('/10');   // '/1' . '%' used to match it.

        $this->setUser($this->tenant_admin('/1'));
        $ids = array_map('intval', array_keys(org_manager::get_all_in_scope()));
        sort($ids);
        $this->assertSame([(int) $airpay->id, (int) $airpaydept->id], $ids,
            'The Airpay admin\'s tree is /1 only: never ZEEA /177 or the /10 prefix trap.');
        $this->assertSame([(int) $airpay->id], $this->child_ids(0));
        $this->assertSame([], $this->child_ids((int) $zeea->id));
        foreach ([$zeea, $zeeadept, $trap] as $org) {
            $this->assertFalse(org_manager::path_in_scope($org->path), "{$org->path} is not /1's.");
            $this->assertSame(['1=0', []], org_manager::cascade_where_sql(['org_l1' => (int) $org->id], 'p'));
        }
        $this->assertTrue(org_manager::path_in_scope($airpaydept->path));

        $this->setAdminUser();
        $all = org_manager::get_all_in_scope();
        foreach ([$airpay, $airpaydept, $zeea, $zeeadept, $trap] as $org) {
            $this->assertArrayHasKey((int) $org->id, $all, 'The site admin still sees every tenant.');
        }
    }
}
