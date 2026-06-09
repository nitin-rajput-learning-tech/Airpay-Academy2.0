<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

/**
 * Phase-A unit tests for skills_manager — skill-level definitions
 * + designation-skill matrix.
 *
 * @package    local_sentientia_skills
 * @category   test
 */
final class skills_manager_phase_a_test extends \advanced_testcase {

    /** Helper: create one category + one skill at max_level=5. */
    private function seed_skill(int $maxlevel = 5, string $name = 'Python'): int {
        global $DB;
        $catid = $DB->insert_record('local_sentientia_skill_cats', (object) [
            'name' => 'Tech', 'icon' => 'fa-cogs', 'color' => '#0066A7',
            'sort_order' => 1, 'timecreated' => time(),
        ]);
        return (int) $DB->insert_record('local_sentientia_skills', (object) [
            'categoryid' => $catid, 'name' => $name, 'description' => '',
            'max_level' => $maxlevel, 'sort_order' => 1, 'timecreated' => time(),
        ]);
    }

    public function test_get_skill_levels_returns_one_entry_per_level(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);
        $levels = skills_manager::get_skill_levels($skillid);
        $this->assertCount(5, $levels);
        // Each entry has the structural fields, all unsaved by default.
        foreach ($levels as $i => $level) {
            $this->assertSame($i + 1, $level['level']);
            $this->assertFalse($level['saved'],
                'all levels must be unsaved before any save_skill_level call');
            $this->assertNotEmpty($level['label'],
                'unsaved levels still have a default label');
        }
    }

    public function test_get_skill_levels_respects_max_level(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(3);
        $levels = skills_manager::get_skill_levels($skillid);
        $this->assertCount(3, $levels);
    }

    public function test_save_skill_level_inserts_then_updates(): void {
        global $DB;
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);

        // First save: insert.
        $id1 = skills_manager::save_skill_level($skillid, 1, 'Hello world',
            'Can write print("hi")');
        $this->assertGreaterThan(0, $id1);

        // Reading back returns saved=true with the persisted values.
        $levels = skills_manager::get_skill_levels($skillid);
        $this->assertTrue($levels[0]['saved']);
        $this->assertSame('Hello world', $levels[0]['label']);

        // Second save with same (skillid, level): update — same row ID.
        $id2 = skills_manager::save_skill_level($skillid, 1, 'Beginner', 'Updated description');
        $this->assertSame($id1, $id2,
            'second save with same (skillid, level) must update, not insert');

        $row = $DB->get_record('local_sentientia_skill_levels', ['id' => $id1], '*', MUST_EXIST);
        $this->assertSame('Beginner', $row->label);
    }

    public function test_save_skill_level_rejects_out_of_range_level(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(3);
        $this->expectException(\invalid_parameter_exception::class);
        skills_manager::save_skill_level($skillid, 4, 'Should fail');
    }

    public function test_save_skill_level_rejects_empty_label(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);
        $this->expectException(\invalid_parameter_exception::class);
        skills_manager::save_skill_level($skillid, 2, '   ');
    }

    public function test_get_designation_skills_empty_for_unknown_designation(): void {
        $this->resetAfterTest();
        $rows = skills_manager::get_designation_skills('Janitor');
        $this->assertSame([], $rows);
    }

    public function test_save_designation_skill_inserts_then_updates(): void {
        global $DB;
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);

        $id1 = skills_manager::save_designation_skill('Senior Engineer', $skillid, 4);
        $this->assertGreaterThan(0, $id1);

        // Update: same (designation, skillid) → same row.
        $id2 = skills_manager::save_designation_skill('Senior Engineer', $skillid, 5);
        $this->assertSame($id1, $id2);

        $rows = skills_manager::get_designation_skills('Senior Engineer');
        $this->assertCount(1, $rows);
        $this->assertSame(5, $rows[0]['required_level']);
    }

    public function test_save_designation_skill_rejects_level_above_max(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(3);
        $this->expectException(\invalid_parameter_exception::class);
        skills_manager::save_designation_skill('Eng', $skillid, 5);
    }

    public function test_delete_designation_skill_removes_row(): void {
        global $DB;
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);
        $id = skills_manager::save_designation_skill('Eng', $skillid, 3);
        $this->assertTrue($DB->record_exists('local_sentientia_role_skills', ['id' => $id]));

        skills_manager::delete_designation_skill($id);
        $this->assertFalse($DB->record_exists('local_sentientia_role_skills', ['id' => $id]));
    }

    public function test_copy_designation_copies_all_rows(): void {
        $this->resetAfterTest();
        $s1 = $this->seed_skill(5, 'Python');
        $s2 = $this->seed_skill(5, 'SQL');
        $s3 = $this->seed_skill(5, 'AWS');
        skills_manager::save_designation_skill('Source Role', $s1, 4);
        skills_manager::save_designation_skill('Source Role', $s2, 3);
        skills_manager::save_designation_skill('Source Role', $s3, 2);

        $copied = skills_manager::copy_designation('Source Role', 'Target Role');
        $this->assertSame(3, $copied);

        $targetrows = skills_manager::get_designation_skills('Target Role');
        $this->assertCount(3, $targetrows);
        // Levels carry over.
        $byskill = array_column($targetrows, 'required_level', 'skillid');
        $this->assertSame(4, $byskill[$s1]);
        $this->assertSame(3, $byskill[$s2]);
        $this->assertSame(2, $byskill[$s3]);
    }

    public function test_copy_designation_skips_existing_pairs(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);
        skills_manager::save_designation_skill('From', $skillid, 4);
        skills_manager::save_designation_skill('To', $skillid, 2);  // pre-existing target

        $copied = skills_manager::copy_designation('From', 'To');
        $this->assertSame(0, $copied,
            'should skip pairs that already exist on the target designation');

        $rows = skills_manager::get_designation_skills('To');
        $this->assertSame(2, $rows[0]['required_level'],
            'pre-existing target level must NOT be overwritten by copy');
    }

    public function test_copy_designation_rejects_self_target(): void {
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);
        skills_manager::save_designation_skill('Self', $skillid, 4);
        $this->assertSame(0, skills_manager::copy_designation('Self', 'Self'));
    }

    public function test_delete_skill_cascades_to_levels(): void {
        global $DB;
        $this->resetAfterTest();
        $skillid = $this->seed_skill(5);
        skills_manager::save_skill_level($skillid, 1, 'Beginner');
        skills_manager::save_skill_level($skillid, 5, 'Master');

        $this->assertTrue($DB->record_exists('local_sentientia_skill_levels',
            ['skillid' => $skillid]));

        skills_manager::delete_skill($skillid);

        $this->assertFalse($DB->record_exists('local_sentientia_skill_levels',
            ['skillid' => $skillid]),
            'deleting a skill must cascade to its level definitions');
    }
}
