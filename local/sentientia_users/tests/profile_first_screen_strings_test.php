<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase C addendum V8-G (2026-11-19): the profile page's first screen in the user's language.
 *
 * The skill readiness card printed "% skill readiness" and "skills met" as English literals, and the camera button's
 * hover text read "Change photo" in every language. All three come from the language pack now. (The points, rank,
 * badge and streak labels and the detail-grid labels are covered by profile_labels_test.)
 *
 * The data is made here; nothing depends on a real account.
 *
 * @package    local_sentientia_users
 * @category   test
 * @coversNothing
 *
 * @group local_sentientia_users
 */
final class profile_first_screen_strings_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_the_skill_readiness_card_and_photo_button_read_from_the_language_pack(): void {
        global $OUTPUT;
        $component = 'local_sentientia_users';

        $html = $OUTPUT->render_from_template('local_sentientia_users/profile', [
            'userid' => 2, 'username' => 'Probe user', 'user_email' => 'probe@example.com',
            'capabilityedit' => true, 'photo_url' => 'https://example.com/photo',
            'ap_skills_summary' => ['total' => 6, 'met' => 4, 'gaps' => 2, 'percentage' => 67],
        ]);

        $this->assertStringContainsString('>' . get_string('profile_skill_readiness', $component, 67) . '<', $html);
        $this->assertStringContainsString(
            '>' . get_string('profile_skills_met', $component, (object) ['met' => 4, 'total' => 6]) . '<', $html);
        $this->assertStringContainsString('title="' . s(get_string('profile_changephoto', $component)) . '"', $html);
        // Every key resolved (a missing string renders as [[key]]), and the JSON argument reached get_string intact.
        $this->assertStringNotContainsString('[[profile_', $html);
        $this->assertStringNotContainsString('{$a', $html);
    }

    public function test_the_template_has_no_english_literals_left_on_the_first_screen(): void {
        $source = file_get_contents(__DIR__ . '/../templates/profile.mustache');
        $this->assertStringNotContainsString('title="Change photo"', $source);
        $this->assertStringNotContainsString('% skill readiness', $source);
        $this->assertStringNotContainsString('skills met</small>', $source);
    }
}
