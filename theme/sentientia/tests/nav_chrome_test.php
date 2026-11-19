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
 *   - Post-demo plan 2.4 (2026-11-19): Programs was in the site-admin sidebar only. The L&D
 *     admin sidebar now has it too, after Learning Paths, when the user holds
 *     local/sentientia_programs:view at system level (what the Programs page requires).
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

    // ── sidebar "Programs" item for L&D admins (post-demo plan 2.4) ──────

    /**
     * Skip unless the capabilities these tests grant are registered.
     *
     * @param string[] $caps
     */
    private function require_caps(array $caps): void {
        foreach ($caps as $cap) {
            if (!get_capability_info($cap)) {
                $this->markTestSkipped("{$cap} is not installed");
            }
        }
    }

    /**
     * An Airpay-tenant L&D admin: holds local/sentientia_courses:manage at system level (what
     * role_detector reads as L&D admin) plus the given capabilities, through one test role.
     *
     * @param string[] $caps extra system-level capabilities
     * @return \stdClass
     */
    private function ld_admin(array $caps): \stdClass {
        $this->require_caps(array_merge(['local/sentientia_courses:manage', 'local/sentientia_programs:view'], $caps));
        $syscontext = \context_system::instance();
        $user = $this->user_in('/1');
        $roleid = $this->getDataGenerator()->create_role();
        foreach (array_merge(['local/sentientia_courses:manage'], $caps) as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id);
        }
        role_assign($roleid, $user->id, $syscontext->id);
        return $user;
    }

    /**
     * The sidebar items the current user sees on a page.
     *
     * @param string $path
     * @return array
     */
    private function nav_items_on(string $path): array {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url($path);
        return (new \ReflectionMethod(sidebar_navigation::class, 'get_nav_items'))
            ->invoke(new sidebar_navigation($page));
    }

    /**
     * The sidebar "Programs" items among the given ones.
     *
     * @param array $items
     * @return array
     */
    private function programs_items(array $items): array {
        $label = get_string('nav_programs', 'theme_sentientia');
        return array_values(array_filter($items, static function(array $item) use ($label): bool {
            return ($item['label'] ?? '') === $label;
        }));
    }

    public function test_an_ld_admin_who_may_open_programs_gets_the_item_after_learning_paths(): void {
        $this->setUser($this->ld_admin(['local/sentientia_learningpath:view', 'local/sentientia_programs:view']));
        $this->assertTrue(role_detector::detect()['isldadmin'], 'the fixture is an L&D admin');

        $items = $this->nav_items_on('/my/');
        $programs = $this->programs_items($items);
        $this->assertCount(1, $programs, 'exactly one Programs item');
        $this->assertSame((new \moodle_url(sidebar_navigation::PROGRAMS_PAGE))->out(false), $programs[0]['url']);
        $this->assertSame('fa-trophy', $programs[0]['icon'], 'the site-admin item\'s icon (an FA6 name)');
        $this->assertFalse($programs[0]['active'], 'not active on the dashboard');

        // Same place as in the site-admin sidebar: right after Learning Paths.
        $labels = array_column($items, 'label');
        $learningpaths = array_search(get_string('nav_learningpaths', 'theme_sentientia'), $labels, true);
        $this->assertNotFalse($learningpaths, 'the fixture sees Learning Paths');
        $this->assertSame(get_string('nav_programs', 'theme_sentientia'), $labels[$learningpaths + 1]);
    }

    public function test_the_programs_item_is_active_on_the_programs_page(): void {
        $this->setUser($this->ld_admin(['local/sentientia_programs:view']));

        $programs = $this->programs_items($this->nav_items_on(sidebar_navigation::PROGRAMS_PAGE));
        $this->assertCount(1, $programs);
        $this->assertTrue($programs[0]['active']);
    }

    public function test_an_ld_admin_without_the_programs_capability_gets_no_item(): void {
        // The Programs page would refuse this user (require_capability), so no link to it.
        $this->setUser($this->ld_admin([]));
        $this->assertTrue(role_detector::detect()['isldadmin'], 'the fixture is an L&D admin');

        $this->assertSame([], $this->programs_items($this->nav_items_on('/my/')));
    }

    public function test_a_category_level_programs_grant_gives_no_item(): void {
        // The page checks the system context: a grant held only in a category (the compliance
        // officer's category-level administrator role) does not open it, so it shows no link.
        $user = $this->ld_admin([]);
        $category = $this->getDataGenerator()->create_category();
        $catroleid = $this->getDataGenerator()->create_role();
        $catcontext = \context_coursecat::instance((int) $category->id);
        assign_capability('local/sentientia_programs:view', CAP_ALLOW, $catroleid, $catcontext->id);
        role_assign($catroleid, $user->id, $catcontext->id);
        $this->setUser($user);

        $this->assertTrue(has_capability('local/sentientia_programs:view', $catcontext), 'granted in the category');
        $this->assertFalse(has_capability('local/sentientia_programs:view', \context_system::instance()));
        $this->assertSame([], $this->programs_items($this->nav_items_on('/my/')));
    }

    public function test_a_learner_holding_the_programs_capability_gets_no_item(): void {
        // Scope of plan 2.4: the L&D admin sidebar. A trainer (editingteacher archetype holds the
        // capability) lands in the Learner shell, which is unchanged.
        $this->require_caps(['local/sentientia_programs:view']);
        $syscontext = \context_system::instance();
        $user = $this->user_in('/1');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/sentientia_programs:view', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $user->id, $syscontext->id);
        $this->setUser($user);
        $this->assertTrue(role_detector::detect()['islearner'], 'the fixture is in the Learner shell');

        $this->assertSame([], $this->programs_items($this->nav_items_on('/my/')));
    }

    public function test_the_site_admin_sidebar_keeps_one_programs_item(): void {
        $this->require_caps(['local/sentientia_programs:view']);
        $this->setAdminUser();

        $programs = $this->programs_items($this->nav_items_on('/my/'));
        $this->assertCount(1, $programs);
        $this->assertSame((new \moodle_url(sidebar_navigation::PROGRAMS_PAGE))->out(false), $programs[0]['url']);
    }
}
