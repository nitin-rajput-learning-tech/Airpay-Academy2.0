<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Persona walk 2026-11-18 (V6): the profile page's labels.
 *
 * - user_manager::gamification_labels(): the enrichment cards printed "pts", "badges", "day streak" and "Rank #" as
 *   English literals in the template, and "1 badges" for a single badge. The labels now come from the language pack.
 * - The earned badges were unlabelled icons whose name lived only in a title attribute; each badge now prints its
 *   name, under a heading.
 * - user_manager::build_profile_context(): an empty HR field read "N/A" in every language; it is an em dash now, and
 *   an empty designation is '' (the header shows the briefcase line only when it is non-empty).
 *
 * The data is made here; nothing depends on a real account.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\user_manager::gamification_labels
 * @covers     \local_sentientia_users\user_manager::build_profile_context
 *
 * @group local_sentientia_users
 */
final class profile_labels_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A gamification summary shaped like local_sentientia_gamification_get_summary() output.
     *
     * @param array $overrides
     * @return array
     */
    private function summary(array $overrides = []): array {
        return array_merge([
            'total_points'   => '1,250',
            'today_points'   => 0,
            'level_name'     => 'Explorer',
            'level_number'   => 2,
            'level_color'    => '#0066A7',
            'level_progress' => 40,
            'points_to_next' => '250',
            'current_streak' => 0,
            'longest_streak' => 0,
            'badge_count'    => 0,
            'badges'         => [],
            'rank'           => 3,
            'has_badges'     => false,
            'has_streak'     => false,
        ], $overrides);
    }

    public function test_the_badge_count_label_is_singular_for_one_badge(): void {
        $label = function (int $count): string {
            return user_manager::gamification_labels($this->summary(['badge_count' => $count]))['badge_count_label'];
        };
        $this->assertSame('0 badges', $label(0));
        $this->assertSame('1 badge', $label(1));
        $this->assertSame('3 badges', $label(3));
    }

    public function test_the_streak_points_and_rank_labels(): void {
        $nostreak = user_manager::gamification_labels($this->summary());
        $this->assertSame('Start a streak!', $nostreak['streak_label']);
        $this->assertSame('1,250 pts', $nostreak['points_label']);
        $this->assertSame('Explorer · Rank #3', $nostreak['rank_label']);

        $streak = user_manager::gamification_labels($this->summary(['has_streak' => true, 'current_streak' => 5]));
        $this->assertSame('5-day streak', $streak['streak_label']);

        // The summary itself is handed on unchanged.
        $this->assertSame('#0066A7', $streak['level_color']);
        $this->assertSame(5, $streak['current_streak']);
    }

    public function test_earned_badges_print_their_names_under_a_heading(): void {
        global $OUTPUT;
        $gamification = user_manager::gamification_labels($this->summary([
            'badge_count' => 1,
            'has_badges'  => true,
            'badges'      => [['name' => 'Team Player', 'icon' => 'fa-users', 'description' => 'Helped a colleague']],
        ]));

        $html = $OUTPUT->render_from_template('local_sentientia_users/profile', [
            'userid' => 2, 'username' => 'Probe user', 'user_email' => 'probe@example.com',
            'ap_canmanage' => true, 'ap_isown' => true,
            'ap_editurl' => 'https://example.com/edit', 'ap_prefsurl' => 'https://example.com/prefs',
            'ap_passwordurl' => 'https://example.com/password',
            'ap_gamification' => $gamification,
        ]);

        // Text a sighted reader sees: everything outside the title attributes.
        $visible = preg_replace('/\stitle="[^"]*"/', '', $html);
        $this->assertStringContainsString('Team Player', $visible, 'The badge name is printed, not only a hover title.');
        $this->assertStringContainsString(get_string('profile_badges_heading', 'local_sentientia_users'), $visible);
        $this->assertStringContainsString('>1 badge<', $visible);
        $this->assertStringNotContainsString('1 badges', $visible);
        $this->assertStringContainsString('>1,250 pts<', $visible);

        // The account buttons are spaced by theme CSS: Bootstrap 4.6 has no gap-* utilities.
        $this->assertStringContainsString('class="ap-profile__account-actions"', $html);
        $this->assertStringNotContainsString('gap-2 mt-3', $html);
    }

    public function test_empty_hr_fields_read_an_em_dash_and_an_empty_designation_is_blank(): void {
        global $DB, $OUTPUT, $PAGE;
        $this->ensure_bizlms_schema();
        $PAGE->set_context(\context_system::instance());

        $user = $this->getDataGenerator()->create_user(['city' => '', 'phone1' => '', 'address' => '']);
        $DB->set_field('user', 'open_path', '/1', ['id' => $user->id]);
        $this->setAdminUser();

        $context = user_manager::build_profile_context((int) $user->id);

        $dash = "\u{2014}";
        $this->assertSame($dash, $context['empid']);
        $this->assertSame($dash, $context['location']);
        $this->assertSame($dash, $context['phnumber']);
        $this->assertSame($dash, $context['supervisorname']);
        $this->assertSame($dash, $context['joindate']);
        $this->assertSame($dash, $context['dateofbirth']);
        $this->assertSame('', $context['designation']);
        // Department and sub-department keep their "All" (an employee at the tenant root sees the whole tenant).
        $this->assertSame('All', $context['department']);

        $html = $OUTPUT->render_from_template('local_sentientia_users/profile', $context);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringContainsString(get_string('joindate', 'local_sentientia_users'), $html);
    }
}
