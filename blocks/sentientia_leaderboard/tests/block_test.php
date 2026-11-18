<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/sentientia_leaderboard/block_sentientia_leaderboard.php');

/**
 * PHPUnit tests for block_sentientia_leaderboard: the master-flag gate.
 *
 * Surface walk 2026-11-18: with sentientia.leaderboards.enabled OFF the
 * block showed the flag key to every viewer. Only moodle/site:config
 * holders get that hint now; everyone else gets empty content, which
 * block_base::is_empty() turns into "block not rendered" outside editing
 * mode.
 *
 * @package    block_sentientia_leaderboard
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_sentientia_leaderboard
 */
final class block_test extends \advanced_testcase {

    /** Master flag the block gates on. */
    private const FLAG = 'sentientia.leaderboards.enabled';

    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform feature_flags not available');
        }
        $this->resetAfterTest();
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    /**
     * Build a fresh block and return its content for the current user.
     */
    private function render_content(): \stdClass {
        global $PAGE;
        $PAGE->set_url('/my/index.php');
        $block = new \block_sentientia_leaderboard();
        $block->init();
        return $block->get_content();
    }

    public function test_flag_off_learner_gets_empty_content(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $content = $this->render_content();

        // Empty text AND footer: block_base::is_empty() is true, so the
        // block is not rendered for this viewer.
        $this->assertSame('', trim($content->text));
        $this->assertSame('', trim($content->footer));
    }

    public function test_flag_off_manager_without_site_config_gets_empty_content(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $user->id, \context_system::instance()->id);
        $this->setUser($user);
        $this->assertFalse(has_capability('moodle/site:config', \context_system::instance()));

        $content = $this->render_content();

        $this->assertSame('', trim($content->text));
        $this->assertSame('', trim($content->footer));
    }

    public function test_flag_off_site_admin_sees_flag_hint(): void {
        $this->setAdminUser();

        $content = $this->render_content();

        $this->assertStringContainsString(self::FLAG, $content->text);
    }

    public function test_flag_on_admin_does_not_see_flag_hint(): void {
        $this->setAdminUser();
        // Signature: set($key, $tenant_id, $value, $by_userid, $reason, $customer_id).
        \local_sentientia_platform\feature_flags::set(self::FLAG, 0, true, null, 'phpunit', 0);
        \local_sentientia_platform\feature_flags::invalidate_caches();

        $content = $this->render_content();

        $this->assertStringNotContainsString(self::FLAG, $content->text);
    }
}
