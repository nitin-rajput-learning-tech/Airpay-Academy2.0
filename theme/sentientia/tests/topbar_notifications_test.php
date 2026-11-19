<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

use theme_sentientia\output\core_renderer;

defined('MOODLE_INTERNAL') || die();

/**
 * The shell topbar's Notifications bell and its unread badge.
 *
 * Surface walk 2026-11-18 (checklist V20): the bell was a plain link with no count. It now carries core's unread popup
 * count as a badge (99+ above 99), and the count is left out for guests and for users who must act before they may
 * use the site (\core_user::awaiting_action()).
 *
 * @package    theme_sentientia
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_sentientia\output\core_renderer::topbar_notifications_link
 * @covers     \theme_sentientia\output\core_renderer::topbar_badge_text
 */
final class topbar_notifications_test extends \advanced_testcase {

    /**
     * Give a user unread popup notifications, as core's popup processor stores them.
     *
     * @param int $userid recipient
     * @param int $count how many
     * @return int[] the {notifications} ids
     */
    private function add_unread(int $userid, int $count): array {
        global $DB;
        $sender = $this->getDataGenerator()->create_user();
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $id = $DB->insert_record('notifications', (object) [
                'useridfrom' => $sender->id,
                'useridto' => $userid,
                'subject' => 'Test notification ' . $i,
                'fullmessage' => 'Test notification body',
                'fullmessageformat' => FORMAT_PLAIN,
                'fullmessagehtml' => '',
                'smallmessage' => 'Test notification',
                'component' => 'moodle',
                'eventtype' => 'instantmessage',
                'timeread' => null,
                'timecreated' => time(),
            ]);
            $DB->insert_record('message_popup_notifications', (object) ['notificationid' => $id]);
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * A logged-in user who may use the site, as the current user.
     *
     * @return \stdClass
     */
    private function login_user(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(\core_user::awaiting_action(), 'precondition: the test user has nothing pending');
        return $user;
    }

    public function test_guest_and_logged_out_get_nothing(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->assertSame('', core_renderer::topbar_notifications_link());
        $this->setUser(0);
        $this->assertSame('', core_renderer::topbar_notifications_link());
    }

    public function test_user_without_notifications_gets_a_bell_without_a_badge(): void {
        $this->resetAfterTest();
        $this->login_user();
        $html = core_renderer::topbar_notifications_link();
        $this->assertStringContainsString('/message/output/popup/notifications.php', $html);
        $this->assertStringContainsString('fa-bell', $html);
        $this->assertStringNotContainsString('ap-topbar__badge', $html);
        $this->assertStringContainsString('aria-label="' . s(get_string('notifications')) . '"', $html);
    }

    public function test_unread_notification_shows_a_badge_until_it_is_read(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->login_user();
        $ids = $this->add_unread((int) $user->id, 1);

        $html = core_renderer::topbar_notifications_link();
        $this->assertStringContainsString('<span class="ap-topbar__badge" aria-hidden="true">1</span>', $html);
        $label = get_string('notifications') . ': ' . get_string('unreadnotifications', 'message', 1);
        $this->assertStringContainsString('aria-label="' . s($label) . '"', $html);

        $DB->set_field('notifications', 'timeread', time(), ['id' => reset($ids)]);
        $html = core_renderer::topbar_notifications_link();
        $this->assertStringNotContainsString('ap-topbar__badge', $html);
        $this->assertStringContainsString('fa-bell', $html);
    }

    public function test_user_who_must_change_password_gets_no_badge(): void {
        $this->resetAfterTest();
        $user = $this->login_user();
        $this->add_unread((int) $user->id, 3);
        set_user_preference('auth_forcepasswordchange', 1);
        $this->assertTrue(\core_user::awaiting_action());

        $html = core_renderer::topbar_notifications_link();
        $this->assertStringContainsString('fa-bell', $html, 'the bell itself stays');
        $this->assertStringNotContainsString('ap-topbar__badge', $html);
    }

    public function test_more_than_99_unread_shows_99_plus(): void {
        $this->resetAfterTest();
        $user = $this->login_user();
        $this->add_unread((int) $user->id, 100);

        $html = core_renderer::topbar_notifications_link();
        $this->assertStringContainsString('<span class="ap-topbar__badge" aria-hidden="true">99+</span>', $html);
    }

    public function test_badge_text(): void {
        $this->assertSame('1', core_renderer::topbar_badge_text(1));
        $this->assertSame('99', core_renderer::topbar_badge_text(99));
        $this->assertSame('99+', core_renderer::topbar_badge_text(100));
        $this->assertSame('99+', core_renderer::topbar_badge_text(1500));
    }

    public function test_the_shell_topbar_uses_the_bell_helper(): void {
        $source = (string) file_get_contents(__DIR__ . '/../classes/output/core_renderer.php');
        $this->assertStringContainsString('. self::topbar_notifications_link()', $source);
        $this->assertSame(1, substr_count($source, "'/message/output/popup/notifications.php'"),
            'the bell link is built in one place');
    }
}
