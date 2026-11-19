<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\form;

use local_sentientia_courses\course_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Post-demo plan 2.4 (2026-11-19): the Manage Courses pop-up offers "Number of sections" on create only.
 *
 * course_manager::create() hands numsections to create_course(), which builds that many sections, so the field
 * stays on the create form. course_manager::update() never read it: on edit the field always showed the default
 * 5 and saving ignored whatever was typed, so it is no longer shown there. The rest of the edit form is unchanged.
 *
 * @package    local_sentientia_courses
 * @category   test
 * @covers     \local_sentientia_courses\form\edit_course
 * @covers     \local_sentientia_courses\course_manager::create
 * @covers     \local_sentientia_courses\course_manager::update
 * @group      local_sentientia_courses
 */
final class edit_course_numsections_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
    }

    /** The QuickForm behind a dynamic edit_course built as the pop-up builds it. */
    private function quickform(int $courseid): \MoodleQuickForm {
        $form = new edit_course(null, null, 'post', '', null, true, ['courseid' => $courseid], true);
        $property = new \ReflectionProperty(\moodleform::class, '_form');
        return $property->getValue($form);
    }

    /** Rows in {course_sections} for the course (section 0 included). */
    private function section_count(int $courseid): int {
        global $DB;
        return $DB->count_records('course_sections', ['course' => $courseid]);
    }

    public function test_create_form_offers_number_of_sections(): void {
        $mform = $this->quickform(0);
        $this->assertTrue($mform->elementExists('numsections'));
    }

    public function test_edit_form_does_not_offer_number_of_sections(): void {
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $mform = $this->quickform((int) $course->id);

        $this->assertFalse($mform->elementExists('numsections'),
            'course_manager::update() ignores numsections, so the edit form must not ask for it');
        // Everything else the edit pop-up had is still there.
        foreach (['fullname', 'shortname', 'idnumber', 'category', 'open_costcenterid', 'summary_editor',
                  'format', 'visible', 'startdate', 'enddate', 'open_coursecompletiondays'] as $name) {
            $this->assertTrue($mform->elementExists($name), "edit form lost '$name'");
        }
    }

    public function test_create_still_builds_the_requested_number_of_sections(): void {
        $courseid = course_manager::create((object) [
            'fullname'   => 'Numsections create',
            'shortname'  => 'NSCREATE1',
            'category'   => (int) \core_course_category::get_default()->id,
            'format'     => 'topics',
            'numsections' => 3,
        ]);
        $this->assertSame(4, $this->section_count($courseid), 'section 0 plus the 3 requested');
    }

    public function test_update_leaves_the_sections_alone(): void {
        $course = $this->getDataGenerator()->create_course(['format' => 'topics', 'numsections' => 3]);

        // Even if a caller still sends numsections, update() does not act on it (why the field was dead on edit).
        course_manager::update((int) $course->id, (object) ['fullname' => 'Renamed', 'numsections' => 8]);

        $this->assertSame(4, $this->section_count((int) $course->id), 'section 0 plus the 3 it was created with');
        $this->assertSame('Renamed', get_course((int) $course->id)->fullname);
    }
}
