<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

/**
 * UI fix 2026-10-15 (visual pass 2026-10-14, finding 9): a skill category name printed as
 * "Compliance &amp; Risk".
 *
 * Every row skills_manager hands to a Mustache template ({{ }}), a web service row the datatable renders, or
 * the radar was run through format_string() first, which HTML-escapes, and was then escaped AGAIN by the output
 * layer. The text form is skills_manager::plain(); the long-standing HTML-escaped form stays available (the
 * recommendation methods keep it as their default, because theme_sentientia writes it with {{{ }}}).
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_skills\skills_manager
 */
final class names_escaped_once_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        // format_string() filters in a page context.
        $PAGE->set_context(\context_system::instance());
    }

    /** A category + skill + a "Risk & Compliance" requirement + a learner holding that designation. */
    private function seed_gap(): array {
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
        return [(int) $learner->id, $skillid];
    }

    public function test_plain_is_text_not_html(): void {
        $this->assertSame('Compliance & Risk', skills_manager::plain('Compliance & Risk'));
        $this->assertSame('', skills_manager::plain(null));
        // The HTML form is what format_string() has always returned: one escape.
        $this->assertSame('Compliance &amp; Risk', format_string('Compliance & Risk'));
    }

    public function test_the_gap_analysis_is_text_by_default_and_escaped_on_request(): void {
        [$userid] = $this->seed_gap();

        $text = skills_manager::get_gap_analysis($userid);
        $this->assertSame('Compliance & Risk', $text['skills'][0]['category_name']);
        $this->assertSame('Know Your "Customer" & AML', $text['skills'][0]['skill_name']);
        $this->assertSame('Risk & Compliance', $text['designation']);

        $html = skills_manager::get_gap_analysis($userid, false);
        $this->assertSame('Compliance &amp; Risk', $html['skills'][0]['category_name']);
        $this->assertSame('Risk &amp; Compliance', $html['designation']);
    }

    public function test_recommended_courses_keep_the_escaped_default_for_the_theme_and_offer_text(): void {
        [$userid] = $this->seed_gap();

        // The theme dashboard rail writes these with {{{ }}}: the default must stay HTML-escaped.
        $html = skills_manager::get_recommended_courses($userid, 5);
        $this->assertSame('Fraud &amp; AML Basics', $html[0]['fullname']);
        $this->assertSame($html, skills_manager::get_gap_courses($userid, 5));

        // skills/index.php renders them with {{ }}: it asks for text.
        $text = skills_manager::get_recommended_courses($userid, 5, true);
        $this->assertSame('Fraud & AML Basics', $text[0]['fullname']);
        $this->assertSame('Know Your "Customer" & AML', $text[0]['skill_name']);
        $this->assertSame($text, skills_manager::get_gap_courses($userid, 5, true));
    }

    public function test_the_radar_labels_are_text_and_cannot_leave_the_script_they_are_written_into(): void {
        [$userid] = $this->seed_gap();

        $radar = skills_manager::get_radar_data($userid);
        $this->assertSame(['Know Your "Customer" & AML'], json_decode($radar['radar_labels'], true));
        // dashboard.mustache writes the JSON raw into a <script>: no raw < > & ' in it, and the only " are the
        // two that delimit the one label.
        $this->assertSame(2, substr_count($radar['radar_labels'], '"'));
        foreach (['<', '>', '&', "'"] as $char) {
            $this->assertStringNotContainsString($char, $radar['radar_labels']);
        }
        $this->assertStringContainsString('\u0026', $radar['radar_labels']);
        $this->assertSame('Risk & Compliance', $radar['designation']);
    }
}
