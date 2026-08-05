<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * template_manager CRUD + tenant-scoping tests.
 *
 * @package    local_sentientia_authoring
 * @covers     \local_sentientia_authoring\template_manager
 */
final class template_manager_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * @param string $openpath
     * @return \stdClass
     */
    private function tenant_user(string $openpath): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $openpath, ['id' => $u->id]);
        $u->open_path = $openpath;
        return $u;
    }

    public function test_create_sets_scope_and_timestamps(): void {
        global $DB;
        $u = $this->tenant_user('/77');
        $id = template_manager::create((int) $u->id, 'My template', 'Structure body', 'desc');
        $row = $DB->get_record(template_manager::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('My template', $row->name);
        $this->assertSame(77, (int) $row->costcenterid);
        $this->assertSame(0, (int) $row->is_builtin);
        $this->assertGreaterThan(0, $row->timecreated);
    }

    public function test_create_requires_name_and_body(): void {
        $u = $this->tenant_user('/1');
        $this->expectException(\invalid_parameter_exception::class);
        template_manager::create((int) $u->id, '', 'body');
    }

    public function test_update_changes_fields(): void {
        global $DB;
        $u = $this->tenant_user('/1');
        $id = template_manager::create((int) $u->id, 'Name', 'Body');
        template_manager::update($id, ['name' => 'New name', 'body' => 'New body']);
        $row = $DB->get_record(template_manager::TABLE, ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('New name', $row->name);
        $this->assertSame('New body', $row->body);
    }

    public function test_archive_hides_from_list(): void {
        $u = $this->tenant_user('/1');
        $id = template_manager::create((int) $u->id, 'Name', 'Body');
        template_manager::archive($id);
        $list = template_manager::list_for_actor($u, false);
        $ids = array_map(fn($t) => (int) $t->id, $list);
        $this->assertNotContains($id, $ids);
    }

    public function test_seed_builtins_is_idempotent(): void {
        global $DB;
        template_manager::seed_builtins();
        $first = $DB->count_records(template_manager::TABLE, ['is_builtin' => 1]);
        $this->assertGreaterThan(0, $first);
        template_manager::seed_builtins();
        $second = $DB->count_records(template_manager::TABLE, ['is_builtin' => 1]);
        $this->assertSame($first, $second);
    }

    public function test_builtin_cannot_be_archived(): void {
        global $DB;
        template_manager::seed_builtins();
        $builtin = $DB->get_record(template_manager::TABLE, ['is_builtin' => 1], '*', IGNORE_MULTIPLE);
        $this->expectException(\moodle_exception::class);
        template_manager::archive((int) $builtin->id);
    }

    public function test_builtin_cannot_be_deleted(): void {
        global $DB;
        template_manager::seed_builtins();
        $builtin = $DB->get_record(template_manager::TABLE, ['is_builtin' => 1], '*', IGNORE_MULTIPLE);
        $this->expectException(\moodle_exception::class);
        template_manager::delete((int) $builtin->id);
    }

    public function test_tenant_isolation_on_load(): void {
        $owner = $this->tenant_user('/1');
        $other = $this->tenant_user('/77');
        $id = template_manager::create((int) $owner->id, 'Secret', 'Body');
        // Other tenant, non-manager, not owner → cannot load.
        $this->assertNull(template_manager::load_for_actor($id, $other, false));
        // Owner can.
        $this->assertNotNull(template_manager::load_for_actor($id, $owner, false));
    }

    public function test_shared_builtin_visible_across_tenants(): void {
        global $DB;
        template_manager::seed_builtins();
        $builtin = $DB->get_record(template_manager::TABLE, ['is_builtin' => 1], '*', IGNORE_MULTIPLE);
        $other = $this->tenant_user('/177');
        // Built-ins (costcenterid 0) are visible to every tenant.
        $this->assertNotNull(template_manager::load_for_actor((int) $builtin->id, $other, false));
    }
}
