<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

defined('MOODLE_INTERNAL') || die();

/**
 * One catalogue-home rule in the plugin (persona walk 2026-11-18, CAT-3).
 *
 * The cart's "Browse Courses" / "Continue Browsing" links sent every viewer to the Public storefront
 * (public.php), so an Airpay or ZEEA employee landed on a list of courses their own catalogue does not offer;
 * the course page's back link and My Courses' empty-state CTA each hardcoded one page as well. They now all
 * ask catalog_manager::catalog_home_url(), which is the
 * same rule as the theme sidebar's catalog_path(): the tenant catalogue (index.php) for a logged-in viewer of
 * any resolved tenant other than the storefront tenant; public.php for that tenant, a guest, nobody, or a
 * tenant that does not resolve. The storefront tenant is enrolment::public_tenant_id() (setting
 * local_sentientia_pages/public_tenant_id, default 77), never a hardcoded id.
 *
 * Users are generated here; no real data is read.
 *
 * @package    local_sentientia_catalog
 * @copyright  2026 Airpay Payment Services
 * @covers     \local_sentientia_catalog\catalog_manager::catalog_home_url
 */
final class catalog_home_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /** The tenant catalogue list page, as a site-relative URL. */
    private const TENANT_PAGE = '/local/sentientia_catalog/index.php';

    /** The Public storefront list page, as a site-relative URL. */
    private const STOREFRONT_PAGE = '/local/sentientia_catalog/public.php';

    /**
     * A generated user with the given open_path, read back from the DB as $USER would carry it.
     *
     * @param string $openpath
     * @return \stdClass
     */
    private function user_with_path(string $openpath): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $openpath, ['id' => $user->id]);
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * The catalogue home for a user, as a site-relative URL.
     *
     * @param \stdClass $user
     * @return string
     */
    private function home(\stdClass $user): string {
        return catalog_manager::catalog_home_url($user)->out_as_local_url(false);
    }

    public function test_an_airpay_employee_goes_to_the_tenant_catalogue(): void {
        $this->assertSame(self::TENANT_PAGE, $this->home($this->user_with_path('/1/3')));
    }

    public function test_a_zeea_employee_goes_to_the_tenant_catalogue(): void {
        $this->assertSame(self::TENANT_PAGE, $this->home($this->user_with_path('/177')));
    }

    public function test_a_storefront_learner_goes_to_the_storefront(): void {
        $this->assertSame(self::STOREFRONT_PAGE, $this->home($this->user_with_path('/77/5')));
    }

    public function test_a_user_whose_tenant_does_not_resolve_goes_to_the_storefront(): void {
        $this->assertSame(self::STOREFRONT_PAGE, $this->home($this->user_with_path('')));
    }

    public function test_the_guest_goes_to_the_storefront(): void {
        $this->assertSame(self::STOREFRONT_PAGE, $this->home(guest_user()));
    }

    public function test_nobody_goes_to_the_storefront(): void {
        $this->assertSame(self::STOREFRONT_PAGE, $this->home((object) ['id' => 0]));
    }

    public function test_the_storefront_tenant_comes_from_the_setting(): void {
        $zeea = $this->user_with_path('/177');
        $public = $this->user_with_path('/77');

        set_config('public_tenant_id', 177, 'local_sentientia_pages');

        $this->assertSame(self::STOREFRONT_PAGE, $this->home($zeea));
        $this->assertSame(self::TENANT_PAGE, $this->home($public));
    }
}
