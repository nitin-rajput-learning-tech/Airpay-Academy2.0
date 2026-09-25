<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031: translations stay inside the caller's tenant.
 *
 * :manage_all defaulted to the manager archetype, which every tenant admin
 * holds at system context, and holding it made load_for_actor() skip the
 * tenant check - so translate.php?rowid=N showed any tenant's source and
 * translated course / compliance text, and accept() / discard() then updated
 * that row by bare id.
 *
 * @package    local_sentientia_translate
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_translate\translate_engine
 * @group      tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const CAP = 'local/sentientia_translate:manage_all';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them, here also granted :manage_all deliberately. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $sys = \context_system::instance();
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, $sys->id);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(self::CAP, CAP_ALLOW, $roleid, $sys->id);
        role_assign($roleid, $u->id, $sys->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, $sys, $u->id));
        return $u;
    }

    /** A row in 'translated' state, awaiting save/discard. */
    private function translated_row_by(\stdClass $owner): int {
        global $DB;
        $id = translate_engine::create_pending((int) $owner->id, 'Policy', 'Hello', 'hi', 'claude-sonnet-4-6');
        $DB->set_field(translate_engine::TABLE, 'status', translate_engine::STATUS_TRANSLATED, ['id' => $id]);
        return $id;
    }

    private function status(int $id): string {
        global $DB;
        return (string) $DB->get_field(translate_engine::TABLE, 'status', ['id' => $id], MUST_EXIST);
    }

    /** @return int[] */
    private function listed(\stdClass $actor, bool $manageall): array {
        return array_map(fn($r) => (int) $r->id, translate_engine::list_for_actor($actor, $manageall));
    }

    public function test_manage_all_has_no_default_grant(): void {
        global $DB;
        foreach ($DB->get_records('role', ['archetype' => 'manager']) as $role) {
            $this->assertFalse($DB->record_exists('role_capabilities',
                ['roleid' => $role->id, 'capability' => self::CAP]),
                "Role {$role->shortname} (manager archetype) must not hold :manage_all by default.");
        }
    }

    public function test_a_tenant_admin_cannot_read_or_change_another_tenants_row(): void {
        $admin = $this->tenant_admin_at('/1');
        $own = $this->translated_row_by($this->user_at('/1/2'));
        $foreign = $this->translated_row_by($this->user_at('/177/4'));

        $this->assertNotNull(translate_engine::load_for_actor($own, $admin, true));
        $this->assertNull(translate_engine::load_for_actor($foreign, $admin, true),
            ':manage_all says WHAT, not WHERE.');
        $ids = $this->listed($admin, true);
        $this->assertContains($own, $ids);
        $this->assertNotContains($foreign, $ids);

        $this->assertFalse(translate_engine::accept($foreign, (int) $admin->id, true));
        $this->assertFalse(translate_engine::discard($foreign, (int) $admin->id, true));
        $this->assertSame(translate_engine::STATUS_TRANSLATED, $this->status($foreign),
            'Save / discard no longer update another tenant\'s row by bare id.');

        $this->assertTrue(translate_engine::accept($own, (int) $admin->id, true),
            'Inside their own tenant the tenant admin still reviews translations.');
        $this->assertSame(translate_engine::STATUS_SAVED, $this->status($own));
    }

    public function test_a_caller_with_no_tenant_gets_only_their_own_rows(): void {
        $nobody = $this->tenant_admin_at('');
        $mine = $this->translated_row_by($nobody);
        $other = $this->translated_row_by($this->user_at(''));
        $tenanted = $this->translated_row_by($this->user_at('/1'));

        $this->assertSame([$mine], $this->listed($nobody, true),
            'Bucket 0 is nobody\'s tenant: no shared rows, never every tenant.');
        $this->assertNull(translate_engine::load_for_actor($other, $nobody, true));
        $this->assertNull(translate_engine::load_for_actor($tenanted, $nobody, true));
        $this->assertFalse(translate_engine::discard($other, (int) $nobody->id, true));
        $this->assertSame(translate_engine::STATUS_TRANSLATED, $this->status($other));

        $this->assertSame(['1=0', []], translate_engine::scope_sql((object) ['id' => 0, 'open_path' => ''], true),
            'No actor at all sees nothing.');
    }

    public function test_the_site_admin_still_reaches_every_tenant(): void {
        $admin = get_admin();
        $rows = [$this->translated_row_by($this->user_at('/1')),
            $this->translated_row_by($this->user_at('/177')),
            $this->translated_row_by($this->user_at(''))];
        $ids = $this->listed($admin, true);
        foreach ($rows as $id) {
            $this->assertContains($id, $ids);
            $this->assertNotNull(translate_engine::load_for_actor($id, $admin, true));
        }
        $this->assertTrue(translate_engine::accept($rows[1], (int) $admin->id, true));
        $this->assertSame(['1=1', []], translate_engine::scope_sql($admin, true));
    }

    public function test_the_upgrade_revoke_removes_existing_grants(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/sentientia_translate/db/upgradelib.php');
        // What an install before 2026-09-25 (or reset_role_capabilities()) left behind.
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        assign_capability(self::CAP, CAP_ALLOW, $managerid, \context_system::instance()->id);
        $holder = $this->user_at('/1');
        role_assign($managerid, $holder->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability(self::CAP, \context_system::instance(), $holder->id));

        $this->assertGreaterThanOrEqual(1, local_sentientia_translate_revoke_manage_all());

        $this->assertFalse($DB->record_exists('role_capabilities', ['capability' => self::CAP]));
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse(has_capability(self::CAP, \context_system::instance(), $holder->id));
    }
}
