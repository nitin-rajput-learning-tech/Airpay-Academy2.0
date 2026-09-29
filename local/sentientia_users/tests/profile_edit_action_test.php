<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * ADR-031 (2026-09-29): the profile header's edit pencil never sends a tenant
 * admin to core /user/editadvanced.php.
 *
 * tools/uat/adr031_role9_core_caps.php PROHIBITs moodle/user:update (and the
 * rest of the core user and role powers) for the tenant-admin role. The pencil
 * on the Sentientia profile linked every local/sentientia_users:edit holder
 * to /user/editadvanced.php, which needs moodle/user:update, so for a tenant
 * admin it became a dead button. That page also has no tenant check, so even
 * before the PROHIBIT it was the wrong place to send a tenant admin.
 *
 * These tests lock in user_manager::profile_edit_action():
 *   - a tenant admin whose role has moodle/user:update PROHIBITed gets the
 *     Sentientia edit modal (form\edit_user), and that modal really opens for
 *     them;
 *   - no non-site-admin :edit holder is ever given the core link, whether or
 *     not they still hold moodle/user:update;
 *   - the pencil (and the camera) is shown exactly when the modal would open:
 *     own profile and same-tenant colleagues yes; another tenant, a look-alike
 *     tenant, a site admin or a cross-tenant account in their own tenant no;
 *   - site admins keep the core editor; a learner gets no pencil.
 *
 * @package    local_sentientia_users
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_users\user_manager
 *
 * @group tenant_isolation
 */
final class profile_edit_action_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /** The PART 1 capabilities adr031_role9_core_caps.php PROHIBITs for the tenant-admin role. */
    private const ROLE9_PROHIBITED = [
        'moodle/role:manage', 'moodle/role:override', 'moodle/user:create', 'moodle/user:update',
        'moodle/user:delete', 'moodle/user:loginas', 'moodle/user:editprofile', 'moodle/site:uploadusers',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /**
     * A live user whose open_path is exactly $path.
     *
     * @param string $path
     * @return \stdClass
     */
    private function user_at(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $user->open_path = $path;
        return $user;
    }

    /**
     * A user at $path holding, at system context, a role built from $record
     * (the data generator's create_role() record: archetype, capability => permission).
     *
     * @param string $path
     * @param array $record
     * @return \stdClass
     */
    private function holder_at(string $path, array $record): \stdClass {
        $user = $this->user_at($path);
        $roleid = $this->getDataGenerator()->create_role($record);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        return $user;
    }

    /**
     * A tenant admin as UAT has them AFTER adr031_role9_core_caps.php --apply:
     * a manager-archetype role at system context with the PART 1 PROHIBITs.
     *
     * @param string $path
     * @return \stdClass
     */
    private function role9_after_prohibit(string $path): \stdClass {
        return $this->holder_at($path, ['archetype' => 'manager']
            + array_fill_keys(self::ROLE9_PROHIBITED, 'prohibit'));
    }

    /**
     * Does the Sentientia edit modal open for the current user on $targetid?
     * Builds form\edit_user the way core_form\external\dynamic_form does, which
     * runs check_access_for_dynamic_submission(), then loads the record.
     *
     * @param int $targetid
     * @return bool
     */
    private function modal_opens(int $targetid): bool {
        try {
            $form = new form\edit_user(null, null, 'post', '', [], true, ['userid' => $targetid], true);
            $form->set_data_for_dynamic_submission();
            return true;
        } catch (\moodle_exception $e) {
            return false;
        }
    }

    /**
     * The profile header as the template renders it for $targetid.
     *
     * @param int $targetid
     * @param array $action profile_edit_action() result
     * @return string
     */
    private function render_header(int $targetid, array $action): string {
        global $OUTPUT;
        $context = ['userid' => $targetid, 'username' => 'Probe user', 'user_email' => 'probe@example.com'] + $action;
        return $OUTPUT->render_from_template('local_sentientia_users/profile', $context);
    }

    public function test_tenant_admin_with_core_update_prohibited_gets_the_sentientia_modal(): void {
        $admin = $this->role9_after_prohibit('/1');
        $colleague = $this->user_at('/1/2/3');
        $this->setUser($admin);

        $sys = \context_system::instance();
        $this->assertTrue(has_capability('local/sentientia_users:edit', $sys),
            'Precondition: the manager archetype gives the tenant-admin role :edit');
        $this->assertFalse(has_capability('moodle/user:update', $sys),
            'Precondition: moodle/user:update is PROHIBITed, as after the role-9 script');

        $action = user_manager::profile_edit_action((int) $colleague->id);
        $this->assertSame(1, $action['capabilityedit'], 'The tenant admin keeps a pencil');
        $this->assertTrue($action['editmodal'], 'The pencil opens the Sentientia edit modal');
        $this->assertFalse($action['editprofile'], 'No core editor link');
        $this->assertTrue($this->modal_opens((int) $colleague->id),
            'The modal the pencil opens must actually work for the tenant admin');

        $html = $this->render_header((int) $colleague->id, $action);
        $this->assertStringNotContainsString('editadvanced.php', $html);
        $this->assertStringContainsString('data-action="edit-user"', $html);
        $this->assertStringContainsString('data-userid="' . (int) $colleague->id . '"', $html);
    }

    public function test_no_non_site_admin_editor_is_ever_linked_to_core_editadvanced(): void {
        global $CFG;

        $viewers = [
            'tenant admin after the PROHIBITs' => $this->role9_after_prohibit('/1'),
            'tenant admin before the PROHIBITs (moodle/user:update still ALLOW)'
                => $this->holder_at('/1', ['archetype' => 'manager']),
            ':edit alone' => $this->holder_at('/1', ['local/sentientia_users:edit' => 'allow']),
        ];
        $siteadmin = $this->user_at('/1');
        set_config('siteadmins', $CFG->siteadmins . ',' . $siteadmin->id);
        $targets = [
            'same-tenant colleague' => [$this->user_at('/1/2/3'), true],
            'other tenant /177' => [$this->user_at('/177'), false],
            'look-alike tenant /10' => [$this->user_at('/10'), false],
            'site admin in the same tenant' => [$siteadmin, false],
            'cross-tenant account in the same tenant' => [$this->holder_at('/1',
                ['local/sentientia_platform:crosstenant' => 'allow']), false],
        ];

        foreach ($viewers as $vlabel => $viewer) {
            $this->setUser($viewer);
            $cases = ['own profile' => [$viewer, true]] + $targets;
            foreach ($cases as $tlabel => [$target, $expected]) {
                $case = "{$vlabel}, {$tlabel}";
                $action = user_manager::profile_edit_action((int) $target->id);

                $this->assertFalse($action['editprofile'], "{$case}: never the core editor link");
                $this->assertStringNotContainsString('editadvanced.php',
                    $this->render_header((int) $target->id, $action), "{$case}: rendered header");
                $this->assertSame($expected, $action['editmodal'], "{$case}: modal pencil");
                $this->assertSame($expected ? 1 : 0, $action['capabilityedit'], "{$case}: camera and pencil shown");
                $this->assertSame($action['editmodal'], $this->modal_opens((int) $target->id),
                    "{$case}: the pencil is shown exactly when the modal opens");
            }
        }
    }

    public function test_site_admin_keeps_the_core_editor(): void {
        $target = $this->user_at('/1');
        $this->setAdminUser();

        $action = user_manager::profile_edit_action((int) $target->id);
        $this->assertSame(1, $action['capabilityedit']);
        $this->assertFalse($action['editmodal']);
        $this->assertInstanceOf(\moodle_url::class, $action['editprofile']);
        $this->assertStringContainsString('/user/editadvanced.php', $action['editprofile']->out(false));

        $html = $this->render_header((int) $target->id, $action);
        $this->assertStringContainsString('editadvanced.php', $html);
        $this->assertStringNotContainsString('data-action="edit-user"', $html);
    }

    public function test_no_pencil_without_the_edit_capability(): void {
        $learner = $this->user_at('/1');
        $colleague = $this->user_at('/1/2/3');
        $this->setUser($learner);

        foreach (['own profile' => $learner, 'same-tenant colleague' => $colleague] as $label => $target) {
            $action = user_manager::profile_edit_action((int) $target->id);
            $this->assertSame(['capabilityedit' => 0, 'editprofile' => false, 'editmodal' => false], $action, $label);
            $this->assertStringNotContainsString('fa-pencil', $this->render_header((int) $target->id, $action), $label);
        }
    }
}
