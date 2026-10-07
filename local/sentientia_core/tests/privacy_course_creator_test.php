<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_core\privacy\provider;

/**
 * Owner decision, 2026-10-07 (courses cluster doc item "privacy", rule R9 of the BizLMS import): course.open_coursecreator,
 * the BizLMS column that names the user who created a course, is declared by the privacy provider of the plugin that adds the
 * column (the substrate), exported, and set to 0 when that person is erased. The course itself stays: it belongs to its
 * tenant (the signed users.erasure_treatment = anonymise design for actor columns).
 *
 * @package    local_sentientia_core
 * @category   test
 * @covers     \local_sentientia_core\privacy\provider
 *
 * @group local_sentientia_core
 */
final class privacy_course_creator_test extends \core_privacy\tests\provider_testcase {

    private const COMPONENT = 'local_sentientia_core';

    /** @var \stdClass Created courses a and b. */
    private \stdClass $creator;
    /** @var \stdClass Created course c. */
    private \stdClass $other;
    /** @var int[] Course ids by name. */
    private array $courses = [];

    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();
        // The substrate adds this column on a Sentientia site; the PHPUnit schema is vanilla.
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('course');
        $field = new \xmldb_field('open_coursecreator', XMLDB_TYPE_INTEGER, '18', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $this->creator = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        foreach (['a' => $this->creator, 'b' => $this->creator, 'c' => $this->other] as $name => $user) {
            $course = $this->getDataGenerator()->create_course(['shortname' => 'creator_' . $name]);
            $DB->set_field('course', 'open_coursecreator', $user->id, ['id' => $course->id]);
            $this->courses[$name] = (int) $course->id;
        }
        // A course with no creator (NULL, as BizLMS left most of them).
        $this->courses['none'] = (int) $this->getDataGenerator()->create_course(['shortname' => 'creator_none'])->id;
    }

    private function approved(\stdClass $user): approved_contextlist {
        return new approved_contextlist($user, self::COMPONENT, [\context_system::instance()->id]);
    }

    private function creator_of(int $courseid): ?int {
        global $DB;
        $value = $DB->get_field('course', 'open_coursecreator', ['id' => $courseid], MUST_EXIST);
        return $value === null ? null : (int) $value;
    }

    public function test_the_creator_column_is_declared_with_strings_in_both_languages(): void {
        $collection = provider::get_metadata(new collection(self::COMPONENT));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = $item->get_privacy_fields();
        }
        $this->assertArrayHasKey('course', $declared);
        $this->assertArrayHasKey('open_coursecreator', $declared['course']);
        foreach ($declared['course'] as $identifier) {
            $this->assertTrue(get_string_manager()->string_exists($identifier, self::COMPONENT), $identifier);
        }

        $string = [];
        include(__DIR__ . '/../lang/en/local_sentientia_core.php');
        $en = $string;
        $string = [];
        include(__DIR__ . '/../lang/hi/local_sentientia_core.php');
        $hi = $string;
        foreach (['privacy:metadata:course_creator', 'privacy:metadata:course_creator:open_coursecreator'] as $key) {
            $this->assertArrayHasKey($key, $en, $key);
            $this->assertArrayHasKey($key, $hi, "{$key} has a Hindi string");
        }
    }

    public function test_the_creator_exports_the_courses_they_created_and_nothing_else_about_them(): void {
        provider::export_user_data($this->approved($this->creator));

        $data = json_decode(json_encode(writer::with_context(\context_system::instance())
            ->get_data([get_string('pluginname', 'local_sentientia_core'), 'courses_created'])), true);
        $this->assertEqualsCanonicalizing([$this->courses['a'], $this->courses['b']],
            array_map('intval', array_column($data['courses'], 'courseid')));
        $this->assertEqualsCanonicalizing(['creator_a', 'creator_b'], array_column($data['courses'], 'shortname'));
    }

    public function test_the_user_list_names_the_creators_and_not_the_course_with_none(): void {
        $list = new userlist(\context_system::instance(), self::COMPONENT);
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        $this->assertContains((int) $this->creator->id, $ids);
        $this->assertContains((int) $this->other->id, $ids);
        $this->assertNotContains(0, $ids);
    }

    public function test_erasing_a_creator_keeps_the_courses_and_removes_the_person(): void {
        global $DB;
        provider::delete_data_for_user($this->approved($this->creator));

        $this->assertSame(0, $this->creator_of($this->courses['a']));
        $this->assertSame(0, $this->creator_of($this->courses['b']));
        $this->assertSame((int) $this->other->id, $this->creator_of($this->courses['c']), 'another creator is untouched');
        $this->assertNull($this->creator_of($this->courses['none']), 'a course with no creator stays as it was');
        foreach (['a', 'b', 'c', 'none'] as $name) {
            $this->assertTrue($DB->record_exists('course', ['id' => $this->courses[$name]]), "course {$name} stays");
        }
    }

    public function test_the_bulk_erasure_and_a_context_wipe_remove_creators_too(): void {
        $context = \context_system::instance();
        provider::delete_data_for_users(new approved_userlist($context, self::COMPONENT, [$this->creator->id]));
        $this->assertSame(0, $this->creator_of($this->courses['a']));
        $this->assertSame((int) $this->other->id, $this->creator_of($this->courses['c']));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $this->creator_of($this->courses['c']));
        $this->assertNull($this->creator_of($this->courses['none']), 'NULL stays NULL: nothing to remove');
    }
}
