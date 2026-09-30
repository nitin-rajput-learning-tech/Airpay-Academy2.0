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
 * 2026-09-30: the "Log in as" link on the Sentientia profile.
 *
 * user_manager::build_profile_context() offered the link to every holder of
 * moodle/user:loginas at system context. It is now built by
 * user_manager::profile_loginas_url(), which also hides it when:
 *   - the target is a site admin (course/loginas.php refuses them, even for
 *     another site admin);
 *   - the target is the viewer;
 *   - the target is deleted, suspended or missing;
 *   - the viewer may not act on the target (require_can_act_on() semantics:
 *     another tenant, a look-alike tenant, or a cross-tenant account, unless
 *     the viewer is a site admin or cross-tenant).
 *
 * @package    local_sentientia_users
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_users\user_manager::profile_loginas_url
 *
 * @group tenant_isolation
 */
final class profile_loginas_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

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
     * Make $user a site admin.
     *
     * @param \stdClass $user
     */
    private function make_site_admin(\stdClass $user): void {
        global $CFG;
        set_config('siteadmins', $CFG->siteadmins . ',' . $user->id);
    }

    /**
     * The profile header as the template renders it.
     *
     * @param int $targetid
     * @param \moodle_url|false $loginasurl
     * @return string
     */
    private function render_header(int $targetid, $loginasurl): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('local_sentientia_users/profile', [
            'userid' => $targetid, 'username' => 'Probe user', 'user_email' => 'probe@example.com',
            'loginasurl' => $loginasurl,
        ]);
    }

    public function test_the_link_points_at_core_loginas_for_a_permitted_target(): void {
        $target = $this->user_at('/1/2/3');
        $this->setAdminUser();

        $url = user_manager::profile_loginas_url((int) $target->id);

        $this->assertInstanceOf(\moodle_url::class, $url);
        // get_path() carries the wwwroot's own path when Moodle is not served from /.
        $this->assertStringEndsWith('/course/loginas.php', $url->get_path());
        $this->assertSame((string) SITEID, $url->get_param('id'));
        $this->assertSame((string) $target->id, $url->get_param('user'));
        $this->assertSame(sesskey(), $url->get_param('sesskey'));

        $html = $this->render_header((int) $target->id, $url);
        $this->assertStringContainsString('loginas.php', $html);
    }

    public function test_the_header_renders_no_link_when_there_is_none(): void {
        $target = $this->user_at('/1/2/3');
        $this->assertStringNotContainsString('loginas.php', $this->render_header((int) $target->id, false));
    }

    public function test_a_site_admin_gets_no_link_for_another_site_admin(): void {
        $other = $this->user_at('/1');
        $this->make_site_admin($other);
        $this->setAdminUser();

        $this->assertFalse(user_manager::profile_loginas_url((int) $other->id));
    }

    public function test_nobody_gets_a_link_for_themselves(): void {
        $tenantadmin = $this->holder_at('/1', ['moodle/user:loginas' => 'allow']);
        $this->setUser($tenantadmin);
        $this->assertFalse(user_manager::profile_loginas_url((int) $tenantadmin->id), 'tenant admin, own profile');

        $this->setAdminUser();
        $this->assertFalse(user_manager::profile_loginas_url((int) get_admin()->id), 'site admin, own profile');
    }

    public function test_a_deleted_suspended_or_missing_target_gets_no_link(): void {
        global $DB;
        $suspended = $this->user_at('/1/2');
        $DB->set_field('user', 'suspended', 1, ['id' => $suspended->id]);
        $deleted = $this->user_at('/1/2');
        $DB->set_field('user', 'deleted', 1, ['id' => $deleted->id]);
        $live = $this->user_at('/1/2');

        foreach (['site admin' => null, 'tenant admin' => $this->holder_at('/1', ['moodle/user:loginas' => 'allow'])]
                as $label => $viewer) {
            $viewer ? $this->setUser($viewer) : $this->setAdminUser();
            $this->assertFalse(user_manager::profile_loginas_url((int) $suspended->id), "{$label}: suspended");
            $this->assertFalse(user_manager::profile_loginas_url((int) $deleted->id), "{$label}: deleted");
            $this->assertFalse(user_manager::profile_loginas_url(987654), "{$label}: missing");
            $this->assertFalse(user_manager::profile_loginas_url(0), "{$label}: zero id");
            $this->assertInstanceOf(\moodle_url::class, user_manager::profile_loginas_url((int) $live->id),
                "{$label}: a live same-tenant account still gets the link");
        }
    }

    public function test_a_viewer_without_the_loginas_capability_gets_no_link(): void {
        $target = $this->user_at('/1/2/3');
        $learner = $this->user_at('/1');
        $editor = $this->holder_at('/1', ['local/sentientia_users:edit' => 'allow']);

        foreach (['learner' => $learner, ':edit alone' => $editor] as $label => $viewer) {
            $this->setUser($viewer);
            $this->assertFalse(user_manager::profile_loginas_url((int) $target->id), $label);
        }
    }

    public function test_a_tenant_admin_with_loginas_prohibited_gets_no_link(): void {
        $target = $this->user_at('/1/2/3');
        // As tools/uat/adr031_role9_core_caps.php leaves the tenant-admin role.
        $admin = $this->holder_at('/1', ['archetype' => 'manager', 'moodle/user:loginas' => 'prohibit']);
        $this->setUser($admin);

        $this->assertFalse(user_manager::profile_loginas_url((int) $target->id));
    }

    public function test_a_tenant_admin_with_the_capability_is_held_to_their_own_tenant(): void {
        $admin = $this->holder_at('/1', ['moodle/user:loginas' => 'allow']);
        $siteadmin = $this->user_at('/1');
        $this->make_site_admin($siteadmin);
        $crosstenant = $this->holder_at('/1', ['local/sentientia_platform:crosstenant' => 'allow']);
        $this->setUser($admin);

        $this->assertInstanceOf(\moodle_url::class, user_manager::profile_loginas_url((int) $this->user_at('/1/2/3')->id),
            'same-tenant colleague');
        $this->assertInstanceOf(\moodle_url::class, user_manager::profile_loginas_url((int) $this->user_at('/1')->id),
            'tenant-root colleague');
        $this->assertFalse(user_manager::profile_loginas_url((int) $this->user_at('/177')->id), 'other tenant /177');
        $this->assertFalse(user_manager::profile_loginas_url((int) $this->user_at('/177/178')->id),
            'other tenant department');
        $this->assertFalse(user_manager::profile_loginas_url((int) $this->user_at('/10')->id), 'look-alike tenant /10');
        $this->assertFalse(user_manager::profile_loginas_url((int) $siteadmin->id), 'site admin in the same tenant');
        $this->assertFalse(user_manager::profile_loginas_url((int) $crosstenant->id),
            'cross-tenant account in the same tenant');
    }

    public function test_a_cross_tenant_holder_may_reach_other_tenants_but_not_site_admins(): void {
        $viewer = $this->holder_at('/1', [
            'moodle/user:loginas' => 'allow',
            'local/sentientia_platform:crosstenant' => 'allow',
        ]);
        $siteadmin = $this->user_at('/177');
        $this->make_site_admin($siteadmin);
        $this->setUser($viewer);

        $this->assertInstanceOf(\moodle_url::class, user_manager::profile_loginas_url((int) $this->user_at('/177')->id),
            'other tenant');
        $this->assertFalse(user_manager::profile_loginas_url((int) $siteadmin->id), 'site admin');
    }

    public function test_the_profile_context_carries_the_same_decision(): void {
        $colleague = $this->user_at('/1/2/3');
        $siteadmin = $this->user_at('/1');
        $this->make_site_admin($siteadmin);
        $this->setAdminUser();

        $this->assertNotEmpty(user_manager::build_profile_context((int) $colleague->id)['loginasurl']);
        $this->assertEmpty(user_manager::build_profile_context((int) $siteadmin->id)['loginasurl']);
    }
}
