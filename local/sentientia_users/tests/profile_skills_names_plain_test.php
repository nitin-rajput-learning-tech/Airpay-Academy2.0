<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * UI fix 2026-10-15 (visual pass 2026-10-14, review follow-up): the profile's Skills section is one block of
 * double-brace template, so every name in it must reach the template as plain text and be escaped once.
 *
 * local_sentientia_skills made the gap analysis and the radar plain-text by default, but the Suggested next
 * courses card still got get_gap_courses() in its HTML-escaped default form, so a course or skill name carrying an
 * ampersand printed it as the literal text amp-semicolon, while the Skills rows above it did not.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\user_manager::build_profile_context
 *
 * @group local_sentientia_users
 */
final class profile_skills_names_plain_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        if (!class_exists(\local_sentientia_skills\skills_manager::class)) {
            $this->markTestSkipped('local_sentientia_skills is not installed.');
        }
        // format_string() filters in a page context.
        $PAGE->set_context(\context_system::instance());
    }

    /** A category + skill + a "Risk & Compliance" requirement + a course teaching it + a learner holding that designation. */
    private function seed_learner_with_a_gap(): int {
        global $DB;
        $catid = (int) $DB->insert_record('local_sentientia_skill_cats', (object) [
            'name' => 'Compliance & Risk', 'description' => '', 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time(),
        ]);
        $skillid = (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => $catid, 'name' => 'Know Your "Customer" & AML', 'description' => '', 'max_level' => 5,
            'sort_order' => 1, 'timecreated' => time(),
        ]);
        $DB->insert_record('local_sentientia_role_skills', (object) [
            'designation' => 'Risk & Compliance', 'skillid' => $skillid, 'required_level' => 3, 'timecreated' => time(),
        ]);
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Fraud & AML Basics']);
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        $DB->insert_record('local_sentientia_course_skills', (object) [
            'courseid' => $course->id, 'skillid' => $skillid, 'teaches_level' => 3, 'timecreated' => time(),
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/1', ['id' => $learner->id]);
        $DB->set_field('user', 'open_designation', 'Risk & Compliance', ['id' => $learner->id]);
        $this->setUser($DB->get_record('user', ['id' => $learner->id], '*', MUST_EXIST));
        return (int) $learner->id;
    }

    public function test_the_skills_section_hands_the_template_text_not_html(): void {
        $userid = $this->seed_learner_with_a_gap();

        $context = user_manager::build_profile_context($userid);

        // The rows and the designation were already text.
        $this->assertSame('Risk & Compliance', $context['ap_skills_designation']);
        $this->assertSame('Compliance & Risk', $context['ap_skills_rows'][0]['category_name']);
        $this->assertSame('Know Your "Customer" & AML', $context['ap_skills_rows'][0]['skill_name']);

        // The Suggested next courses card must agree with them: text, so {{ }} escapes it once.
        $this->assertTrue($context['ap_has_gap_courses']);
        $this->assertSame('Fraud & AML Basics', $context['ap_skills_gap_courses'][0]['fullname']);
        $this->assertSame('Know Your "Customer" & AML', $context['ap_skills_gap_courses'][0]['skill_name']);
    }
}
