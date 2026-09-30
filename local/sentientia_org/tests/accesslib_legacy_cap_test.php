<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * accesslib::legacy_cap() and the can_* helpers that use it.
 *
 * Persona pass 2026-09-30, D9 sweep. The org/classroom helpers kept a fallback
 * to the pre-rename BizLMS capabilities (local/costcenter:*, local/classroom:*).
 * Those names are declared by no db/access.php on a Sentientia install, so a
 * bare has_capability() on them is false plus a debugging() notice on every
 * navigation render. legacy_cap() asks get_capability_info() first, so the
 * fallback stays available where BizLMS is still installed and is silent where
 * it is not. The moodle-enhancement copy of accesslib.php had this since
 * 2026-06-18; the top-level copy did not (and theme_sentientia already calls
 * legacy_cap(), which fatals on a tree without it).
 *
 * PHPUnit fails a test on any unexpected debugging() call, so a plain call on a
 * user without the capability is the regression check.
 *
 * @package    local_sentientia_org
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_org\accesslib
 */
final class accesslib_legacy_cap_test extends \advanced_testcase {

    /**
     * A user whose only system-level role allows $caps.
     *
     * @param string[] $caps
     * @return \stdClass
     */
    private function user_with(array $caps): \stdClass {
        $syscontext = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($caps as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
        }
        role_assign($roleid, $user->id, $syscontext->id);
        return $user;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_an_undeclared_legacy_capability_is_false_without_a_debugging_notice(): void {
        if (get_capability_info('local/costcenter:view')) {
            $this->markTestSkipped('BizLMS local_costcenter is installed here; the legacy name is declared.');
        }
        $this->setUser($this->user_with([]));

        $this->assertFalse(accesslib::legacy_cap('local/costcenter:view', \context_system::instance()));
        $this->assertDebuggingNotCalled();
    }

    public function test_a_declared_capability_behaves_like_has_capability(): void {
        $context = \context_system::instance();

        $this->setUser($this->user_with(['local/sentientia_org:view']));
        $this->assertTrue(accesslib::legacy_cap('local/sentientia_org:view', $context));

        $this->setUser($this->user_with([]));
        $this->assertFalse(accesslib::legacy_cap('local/sentientia_org:view', $context));
        $this->assertDebuggingNotCalled();
    }

    public function test_the_can_helpers_use_the_sentientia_capability_and_stay_quiet(): void {
        $this->setUser($this->user_with(['local/sentientia_org:view', 'local/sentientia_org:manage']));
        $this->assertTrue(accesslib::can_view());
        $this->assertTrue(accesslib::can_manage());
        $this->assertFalse(accesslib::can_manage_multi());
        $this->assertFalse(accesslib::can_manage_classroom());
        $this->assertDebuggingNotCalled();

        $this->setUser($this->user_with([]));
        $this->assertFalse(accesslib::can_view());
        $this->assertFalse(accesslib::can_manage());
        $this->assertFalse(accesslib::can_manage_multi());
        $this->assertFalse(accesslib::can_manage_classroom());
        $this->assertDebuggingNotCalled();
    }
}
