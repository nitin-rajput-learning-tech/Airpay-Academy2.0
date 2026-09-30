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

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the app-shell language switcher (persona-pass fix D7, 2026-09-30).
 *
 * The switcher is a new user-visible feature behind the default-OFF platform
 * flag ux.languageSwitcher.enabled. These tests pin:
 *
 *   - the flag is registered and OFF by default, and while it is OFF nothing renders;
 *   - guests and logged-out visitors never get the control;
 *   - with the flag ON the offered languages are listed, the language in use is
 *     a non-clickable marker and every other option carries a sesskey-bearing
 *     url to the switchlang.php endpoint plus a return url without lang;
 *   - a course that forces its language, and a single-language site, hide it;
 *   - switch_to() refuses unknown languages, applies the choice to the session at
 *     once and saves it to the profile, except when the site has taken
 *     moodle/user:editownprofile away from the user;
 *   - the flag resolves per tenant (tenant isolation): a user of tenant /77 sees
 *     the switcher while a user of tenant /1 does not.
 *
 * The languages list is injected, and setUp() also installs a stub language
 * pack (just a langconfig.php) for every non-English code in LANGS. The stubs
 * are needed because the parts of core that switch_to() and the html lang
 * attribute go through check the INSTALLED packs, not the injected list:
 * core_user validates the profile 'lang' against get_list_of_translations(),
 * and get_html_lang_attribute_value() cleans its argument with PARAM_LANG.
 * A fresh PHPUnit dataroot has only 'en', so without the stubs the profile
 * save was cleaned back to the default with a debugging notice, and the html
 * lang value came back 'en'. PHPUnit wipes the dataroot between tests.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @group theme_sentientia
 * @group language_switcher
 * @covers \theme_sentientia\language_switcher
 */
final class language_switcher_test extends \advanced_testcase {

    /** @var string[] Languages offered in the tests: code => label. */
    private const LANGS = [
        'en' => 'English (en)',
        'hi' => 'Hindi (hi)',
        'sw' => 'Swahili (sw)',
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->install_language_stubs();
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    /**
     * Install a stub language pack for every non-English code in LANGS, so that
     * every language the tests offer is also installed for real.
     */
    private function install_language_stubs(): void {
        global $CFG;
        foreach (array_keys(self::LANGS) as $code) {
            if ($code === 'en') {
                continue;
            }
            $dir = make_writable_directory($CFG->dataroot . '/lang/' . $code);
            $config = "<?php\n"
                . "defined('MOODLE_INTERNAL') || die();\n"
                . '$string[\'thislanguage\'] = \'Stub ' . $code . "';\n"
                . '$string[\'iso6391\'] = \'' . $code . "';\n";
            file_put_contents($dir . '/langconfig.php', $config);
        }
        get_string_manager()->reset_caches(true);
        // core_user caches the list of valid 'lang' values with the property definitions.
        \core_user::reset_caches();
    }

    /**
     * Turn the flag ON for a tenant (0 = every tenant).
     *
     * @param int $tenant tenant root, or 0.
     */
    private function enable(int $tenant = 0): void {
        \local_sentientia_platform\feature_flags::set(
            language_switcher::FLAG, $tenant, true, (int) get_admin()->id, 'phpunit');
    }

    /**
     * A page on a deep url that already carries a lang parameter and a sesskey.
     *
     * @return \moodle_page
     */
    private function page(): \moodle_page {
        $page = new \moodle_page();
        $page->set_url('/grade/report/grader/index.php', ['id' => 5, 'lang' => 'hi', 'sesskey' => 'abc123abc1']);
        $page->set_context(\context_system::instance());
        return $page;
    }

    /**
     * Create a normal user with a profile language and log them in.
     *
     * @param string $lang profile language.
     * @param string|null $openpath BizLMS tenant path, or null.
     * @return \stdClass
     */
    private function login_user(string $lang = 'en', ?string $openpath = null): \stdClass {
        $user = $this->getDataGenerator()->create_user(['lang' => $lang]);
        if ($openpath !== null) {
            // The BizLMS column may not exist in the PHPUnit schema; the flag
            // resolver only reads it from $USER, so set it in memory.
            $user->open_path = $openpath;
        }
        $this->setUser($user);
        return $user;
    }

    public function test_flag_is_registered_and_defaults_off(): void {
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        $this->assertArrayHasKey(language_switcher::FLAG, $registry);
        $this->assertFalse($registry[language_switcher::FLAG]['default']);

        $this->login_user();
        $this->assertFalse(language_switcher::is_enabled());
    }

    public function test_flag_off_renders_nothing(): void {
        $this->login_user();

        $ctx = language_switcher::get_context($this->page(), self::LANGS);

        $this->assertFalse($ctx['hasoptions']);
        $this->assertSame([], $ctx['options']);
    }

    public function test_guest_and_logged_out_get_nothing_even_when_enabled(): void {
        $this->enable();

        $this->setGuestUser();
        $this->assertFalse(language_switcher::get_context($this->page(), self::LANGS)['hasoptions']);

        $this->setUser(0);
        $this->assertFalse(language_switcher::get_context($this->page(), self::LANGS)['hasoptions']);
    }

    public function test_enabled_lists_languages_and_marks_the_current_one(): void {
        global $CFG;
        $this->enable();
        $this->login_user('en');

        $ctx = language_switcher::get_context($this->page(), self::LANGS);

        $this->assertTrue($ctx['hasoptions']);
        $this->assertSame('en', $ctx['currentcode']);
        $this->assertSame('English (en)', $ctx['currentlabel']);
        $this->assertSame(['en', 'hi', 'sw'], array_column($ctx['options'], 'code'));

        $byCode = array_column($ctx['options'], null, 'code');
        // The language in use is a marker, not a link.
        $this->assertTrue($byCode['en']['active']);
        $this->assertSame('', $byCode['en']['url']);
        $this->assertFalse($byCode['hi']['active']);

        // Every other option goes through the sesskey-checked endpoint.
        $url = new \moodle_url($byCode['hi']['url']);
        $this->assertSame($CFG->wwwroot . language_switcher::ENDPOINT, $url->out_omit_querystring());
        $this->assertSame('hi', $url->get_param('code'));
        // Never a 'lang' parameter: core applies any GET lang to the session while config.php
        // loads, before the endpoint can check the flag or the sesskey.
        $this->assertNull($url->get_param('lang'));
        $this->assertSame(sesskey(), $url->get_param('sesskey'));
        // The return url is local, keeps the page's own parameters and drops lang and sesskey
        // (returning after a switch must not replay a state-changing page with a valid key).
        $this->assertSame('/grade/report/grader/index.php?id=5', $url->get_param('returnurl'));
        // Each option carries the html lang attribute value for its own language.
        $this->assertSame('hi', $byCode['hi']['htmllang']);
    }

    public function test_session_language_is_the_active_one(): void {
        global $SESSION;
        $this->enable();
        $this->login_user('en');
        $SESSION->lang = 'hi';

        $ctx = language_switcher::get_context($this->page(), self::LANGS);

        $this->assertSame('hi', $ctx['currentcode']);
        $byCode = array_column($ctx['options'], null, 'code');
        $this->assertTrue($byCode['hi']['active']);
        $this->assertFalse($byCode['en']['active']);
        $this->assertNotSame('', $byCode['en']['url']);
    }

    public function test_single_language_site_has_no_choice(): void {
        $this->enable();
        $this->login_user();

        $ctx = language_switcher::get_context($this->page(), ['en' => 'English (en)']);

        $this->assertFalse($ctx['hasoptions']);
    }

    public function test_course_forced_language_hides_the_switcher(): void {
        $this->enable();
        $this->login_user();
        $course = $this->getDataGenerator()->create_course(['lang' => 'hi']);
        $page = $this->page();
        $page->set_course($course);

        $this->assertFalse(language_switcher::get_context($page, self::LANGS)['hasoptions']);
    }

    public function test_switch_to_rejects_a_language_that_is_not_offered(): void {
        global $SESSION, $DB, $USER;
        $this->enable();
        $user = $this->login_user('en');

        $this->assertFalse(language_switcher::switch_to('xx', self::LANGS));
        $this->assertFalse(language_switcher::switch_to('', self::LANGS));

        $this->assertEmpty($SESSION->lang ?? '');
        $this->assertSame('en', $DB->get_field('user', 'lang', ['id' => $user->id]));
        $this->assertSame('en', $USER->lang);
    }

    public function test_switch_to_applies_at_once_and_saves_to_the_profile(): void {
        global $SESSION, $DB, $USER;
        $this->enable();
        $user = $this->login_user('en');

        $this->assertTrue(language_switcher::switch_to('hi', self::LANGS));

        // Immediate: the very next request reads the session language.
        $this->assertSame('hi', $SESSION->lang);
        $this->assertSame('hi', current_language());
        // Per user: saved, so the next login keeps it.
        $this->assertSame('hi', $DB->get_field('user', 'lang', ['id' => $user->id]));
        $this->assertSame('hi', $USER->lang);
        // Only this user's profile changed.
        $other = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $this->assertSame('en', $DB->get_field('user', 'lang', ['id' => $other->id]));
    }

    public function test_switch_to_leaves_the_profile_alone_when_logged_in_as_someone_else(): void {
        global $DB;
        $this->enable();
        $user = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $this->setAdminUser();
        \core\session\manager::loginas($user->id, \context_system::instance());
        $this->assertTrue(\core\session\manager::is_loggedinas());

        $this->assertTrue(language_switcher::switch_to('hi', self::LANGS));

        // Read the globals afresh: loginas() swaps in a new $SESSION object.
        $this->assertSame('hi', $GLOBALS['SESSION']->lang);
        $this->assertSame('en', $DB->get_field('user', 'lang', ['id' => $user->id]),
            'the session is not the impersonated user\'s to change, so their profile stays as it was');
        $this->assertSame('en', $GLOBALS['USER']->lang);
    }

    public function test_switch_to_stays_in_the_session_when_profile_edit_is_taken_away(): void {
        global $CFG, $SESSION, $DB;
        $this->enable();
        assign_capability('moodle/user:editownprofile', CAP_PROHIBIT, $CFG->defaultuserroleid,
            \context_system::instance()->id, true);
        $user = $this->login_user('en');
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(language_switcher::switch_to('hi', self::LANGS));

        $this->assertSame('hi', $SESSION->lang);
        $this->assertSame('en', $DB->get_field('user', 'lang', ['id' => $user->id]),
            'the profile must be left alone when the site removed moodle/user:editownprofile');
    }

    /**
     * Tenant isolation: the flag is resolved for the viewer's own tenant, so
     * turning it on for tenant /77 must not show the switcher to tenant /1.
     *
     * @group tenant_isolation
     */
    public function test_flag_is_resolved_per_tenant(): void {
        $this->enable(77);

        $this->login_user('en', '/77');
        $this->assertTrue(language_switcher::is_enabled());
        $this->assertTrue(language_switcher::get_context($this->page(), self::LANGS)['hasoptions']);

        $this->login_user('en', '/1');
        $this->assertFalse(language_switcher::is_enabled());
        $this->assertFalse(language_switcher::get_context($this->page(), self::LANGS)['hasoptions']);

        // A descendant path of the enabled tenant still resolves to /77 (first segment).
        $this->login_user('en', '/77/5/9');
        $this->assertTrue(language_switcher::is_enabled());

        // A tenant whose id merely starts with 77 is a different tenant.
        $this->login_user('en', '/770');
        $this->assertFalse(language_switcher::is_enabled());
    }
}
