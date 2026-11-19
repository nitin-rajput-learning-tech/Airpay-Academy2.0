<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Navigation chrome fixes from the persona walk of 2026-11-18.
 *
 *   - The sidebar "Catalog" item opened the Public tenant's paid storefront (public.php) for every
 *     user. An Airpay or ZEEA user could open none of its cards ("no permission (view this
 *     course)"). sidebar_navigation::catalog_path() now names the tenant catalogue (index.php) for
 *     every resolved tenant except the Public storefront tenant.
 *   - /enrol/index.php always redirected to /my, so a click on a course the user was not enrolled
 *     in landed on the dashboard. core_renderer::enrol_redirect_url() now names the catalogue
 *     detail page when, and only when, that page would open for the viewer.
 *   - The topbar Messages link drew no icon (FA4 fa-comment-o) and opened an error page with
 *     messaging off. core_renderer::topbar_messages_link() gates it and uses the FA6 name.
 *   - V17 (Phase C): the sidebar "Authoring Studio" item opened the generate form, so an author
 *     had no way back to earlier drafts. It now opens the drafts list (index.php) and stays
 *     active on every studio page.
 *
 * @covers \theme_sentientia\sidebar_navigation::catalog_path
 * @covers \theme_sentientia\output\core_renderer::enrol_redirect_url
 * @covers \theme_sentientia\output\core_renderer::topbar_messages_link
 *
 * @package    theme_sentientia
 * @category   test
 * @group      tenant_isolation
 */
final class nav_chrome_test extends \advanced_testcase {

    // Provisions {user}.open_path and {course}.open_path on the vanilla test schema
    // (and calls resetAfterTest(true) in setUp()).
    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * A user record whose tenant is the given open_path.
     *
     * @param string $path
     * @return \stdClass
     */
    private function user_in(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * A visible course owned by the given tenant root.
     *
     * @param int $root
     * @param int $visible
     * @return \stdClass
     */
    private function course_of(int $root, int $visible = 1): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => $visible]);
        $DB->set_field('course', 'open_path', '/' . $root, ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    /**
     * @return string the fallback the redirect has always used
     */
    private function dashboard_url(): string {
        return (new \moodle_url('/my'))->out(false);
    }

    /**
     * @param int $courseid
     * @return string the catalogue detail page of a course
     */
    private function detail_url(int $courseid): string {
        return (new \moodle_url('/local/sentientia_catalog/course.php', ['id' => $courseid]))->out(false);
    }

    // ── sidebar "Catalog" ────────────────────────────────────────────────

    public function test_internal_tenants_get_the_tenant_catalogue(): void {
        set_config('public_tenant_id', 77, 'local_sentientia_pages');
        foreach (['/1', '/1/183/45', '/177', '/177/178/9', '/250'] as $path) {
            $this->assertSame(sidebar_navigation::CATALOG_TENANT_PAGE,
                sidebar_navigation::catalog_path($this->user_in($path)),
                "a user at {$path} cannot open the Public storefront's cards; the sidebar must open index.php");
        }
    }

    public function test_the_public_tenant_keeps_the_storefront(): void {
        set_config('public_tenant_id', 77, 'local_sentientia_pages');
        foreach (['/77', '/77/80'] as $path) {
            $this->assertSame(sidebar_navigation::CATALOG_STOREFRONT_PAGE,
                sidebar_navigation::catalog_path($this->user_in($path)), "open_path {$path}");
        }
        // '/'-bounded: /770 is not the Public tenant.
        $this->assertSame(sidebar_navigation::CATALOG_TENANT_PAGE,
            sidebar_navigation::catalog_path($this->user_in('/770')));
    }

    public function test_the_storefront_tenant_is_the_configured_one(): void {
        // No tenant id is hardcoded: repoint the storefront and the answers follow it.
        set_config('public_tenant_id', 177, 'local_sentientia_pages');
        $this->assertSame(sidebar_navigation::CATALOG_STOREFRONT_PAGE,
            sidebar_navigation::catalog_path($this->user_in('/177')));
        $this->assertSame(sidebar_navigation::CATALOG_TENANT_PAGE,
            sidebar_navigation::catalog_path($this->user_in('/77')));
    }

    public function test_unresolved_guest_and_nobody_keep_the_storefront(): void {
        foreach (['', '/', 'abc', '/x/1'] as $path) {
            $this->assertSame(sidebar_navigation::CATALOG_STOREFRONT_PAGE,
                sidebar_navigation::catalog_path($this->user_in($path)),
                "open_path '{$path}' resolves no tenant: unchanged behaviour");
        }
        $this->assertSame(sidebar_navigation::CATALOG_STOREFRONT_PAGE,
            sidebar_navigation::catalog_path(guest_user()));
        $this->assertSame(sidebar_navigation::CATALOG_STOREFRONT_PAGE,
            sidebar_navigation::catalog_path((object) ['id' => 0, 'open_path' => '/1']));
    }

    // ── /enrol/index.php redirect ────────────────────────────────────────

    public function test_enrol_page_sends_a_tenant_member_to_the_course_detail_page(): void {
        $course = $this->course_of(1);
        $this->setUser($this->user_in('/1/183'));

        $this->assertSame($this->detail_url((int) $course->id),
            output\core_renderer::enrol_redirect_url((int) $course->id)->out(false));
    }

    public function test_enrol_page_keeps_the_dashboard_for_another_tenants_course(): void {
        // The redirect must not name a page course.php would refuse: no new way in.
        $course = $this->course_of(77);
        $this->setUser($this->user_in('/1'));

        $this->assertSame($this->dashboard_url(), output\core_renderer::enrol_redirect_url((int) $course->id)->out(false));
    }

    public function test_enrol_page_follows_an_active_share(): void {
        $course = $this->course_of(1);
        $this->setAdminUser();
        \local_sentientia_courses\sharing_manager::share_course((int) $course->id, [77]);
        $this->setUser($this->user_in('/77'));

        $this->assertSame($this->detail_url((int) $course->id),
            output\core_renderer::enrol_redirect_url((int) $course->id)->out(false));
    }

    public function test_enrol_page_keeps_the_dashboard_for_hidden_missing_and_site_ids(): void {
        $hidden = $this->course_of(1, 0);
        $this->setUser($this->user_in('/1'));

        foreach ([(int) $hidden->id, 0, -5, (int) SITEID, (int) $hidden->id + 100000] as $id) {
            $this->assertSame($this->dashboard_url(), output\core_renderer::enrol_redirect_url($id)->out(false),
                "course id {$id}");
        }
    }

    public function test_enrol_page_keeps_the_dashboard_for_an_unresolved_viewer(): void {
        $course = $this->course_of(1);
        $this->setUser($this->user_in(''));

        $this->assertSame($this->dashboard_url(), output\core_renderer::enrol_redirect_url((int) $course->id)->out(false));
    }

    // ── topbar Messages link ─────────────────────────────────────────────

    public function test_messages_link_is_absent_when_messaging_is_off(): void {
        $this->setUser($this->user_in('/1'));
        set_config('messaging', 0);

        $this->assertSame('', output\core_renderer::topbar_messages_link());
    }

    public function test_messages_link_uses_a_font_awesome_6_icon_when_messaging_is_on(): void {
        $this->setUser($this->user_in('/1'));
        set_config('messaging', 1);

        $html = output\core_renderer::topbar_messages_link();
        $this->assertStringContainsString('/message/index.php', $html);
        $this->assertStringContainsString('class="fa fa-comment"', $html);
        $this->assertStringNotContainsString('comment-o', $html,
            'fa-comment-o is a Font Awesome 4 name the theme does not load: it draws nothing');
    }

    public function test_messages_link_is_absent_for_a_guest_or_a_user_with_a_pending_password_change(): void {
        set_config('messaging', 1);

        $this->setGuestUser();
        $this->assertSame('', output\core_renderer::topbar_messages_link());

        $user = $this->user_in('/1');
        set_user_preference('auth_forcepasswordchange', 1, $user);
        $this->setUser($user);
        $this->assertSame('', output\core_renderer::topbar_messages_link());
    }

    // ── sidebar "Authoring Studio" item (V17) ────────────────────────────

    /**
     * A tenant user who may use the Authoring Studio: the studio's master flag is ON and the
     * user holds local/sentientia_authoring:generate at system level through a test role.
     *
     * @return \stdClass
     */
    private function studio_author(): \stdClass {
        if (!get_capability_info('local/sentientia_authoring:generate')) {
            $this->markTestSkipped('local_sentientia_authoring is not installed');
        }
        $syscontext = \context_system::instance();
        $user = $this->user_in('/1');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sentientia_authoring:generate', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $user->id, $syscontext->id);
        \local_sentientia_platform\feature_flags::set('sentientia.authoring.enabled', 0, true,
            (int) get_admin()->id, 'nav_chrome_test');
        return $user;
    }

    /**
     * The sidebar "Authoring Studio" item the current user sees on a page.
     *
     * @param string $path
     * @param array $params
     * @return array the item; fails the test when there is not exactly one
     */
    private function studio_item_on(string $path, array $params = []): array {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url($path, $params);
        $items = (new \ReflectionMethod(sidebar_navigation::class, 'get_nav_items'))
            ->invoke(new sidebar_navigation($page));

        $label = get_string('nav_authoringstudio', 'theme_sentientia');
        $matches = array_values(array_filter($items, static function(array $item) use ($label): bool {
            return ($item['label'] ?? '') === $label;
        }));
        $this->assertCount(1, $matches, 'exactly one Authoring Studio item');
        return $matches[0];
    }

    public function test_the_authoring_studio_item_opens_the_drafts_list(): void {
        $this->setUser($this->studio_author());

        $item = $this->studio_item_on('/my/');
        $this->assertSame((new \moodle_url('/local/sentientia_authoring/index.php'))->out(false), $item['url'],
            'the drafts list links on to the generate form; the generate form has no way back to the drafts');
        $this->assertFalse($item['active'], 'not active on the dashboard');
    }

    public function test_the_authoring_studio_item_stays_active_on_every_studio_page(): void {
        $this->setUser($this->studio_author());

        $pages = [
            ['/local/sentientia_authoring/index.php', []],
            ['/local/sentientia_authoring/studio.php', []],
            ['/local/sentientia_authoring/review.php', ['draftid' => 5]],
            ['/local/sentientia_authoring/templates.php', []],
        ];
        foreach ($pages as [$path, $params]) {
            $this->assertTrue($this->studio_item_on($path, $params)['active'], $path);
        }
    }

    public function test_no_authoring_studio_item_with_the_flag_off(): void {
        $user = $this->studio_author();
        \local_sentientia_platform\feature_flags::set('sentientia.authoring.enabled', 0, false,
            (int) get_admin()->id, 'nav_chrome_test');
        $this->setUser($user);

        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url('/my/');
        $items = (new \ReflectionMethod(sidebar_navigation::class, 'get_nav_items'))
            ->invoke(new sidebar_navigation($page));
        $label = get_string('nav_authoringstudio', 'theme_sentientia');
        foreach ($items as $item) {
            $this->assertNotSame($label, $item['label'] ?? '');
        }
    }
}
