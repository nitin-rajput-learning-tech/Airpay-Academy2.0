<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_trainer;

defined('MOODLE_INTERNAL') || die();

/**
 * The trainer dashboard lists every classroom the user trains, not only the ones they are the primary trainer of
 * (ADR-032, classroom code fix 10: BizLMS let a classroom have several trainers, and the import keeps them all
 * in local_sentientia_classroom_trainers). The BizLMS {local_classroom} fallback is gone.
 *
 * @package    block_sentientia_trainer
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \block_sentientia_trainer
 * @group local_sentientia_classroom
 */
final class trainer_block_test extends \advanced_testcase {

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('local_sentientia_classroom_trainers')) {
            $this->markTestSkipped('local_sentientia_classroom is not installed at the ADR-032 schema');
        }
    }

    private function classroom(string $name, ?int $trainerid, int $status = 1): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_classroom', (object) [
            'name' => $name, 'trainerid' => $trainerid, 'status' => $status, 'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private function also_trainer(int $classroomid, int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_sentientia_classroom_trainers', (object) [
            'classroomid' => $classroomid, 'trainerid' => $userid, 'timecreated' => $now, 'timemodified' => $now]);
    }

    private function content_for(\stdClass $user): string {
        $this->setUser($user);
        $block = block_instance('sentientia_trainer');
        return (string) $block->get_content()->text;
    }

    public function test_the_primary_trainer_and_the_other_trainers_both_see_the_classroom(): void {
        $primary = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $shared = $this->classroom('Shared workshop', (int) $primary->id);
        $this->also_trainer($shared, (int) $primary->id);
        $this->also_trainer($shared, (int) $second->id);
        $this->classroom('Somebody else\'s workshop', (int) $stranger->id);

        $this->assertStringContainsString('Shared workshop', $this->content_for($primary));
        $this->assertStringContainsString('Shared workshop', $this->content_for($second));
        $this->assertStringNotContainsString('Somebody else', $this->content_for($second));
        $this->assertStringNotContainsString('Shared workshop', $this->content_for($stranger));
    }

    public function test_only_active_classrooms_are_listed_and_a_classroom_is_listed_once(): void {
        $trainer = $this->getDataGenerator()->create_user();
        $active = $this->classroom('Active workshop', (int) $trainer->id, 1);
        $this->also_trainer($active, (int) $trainer->id);
        $this->classroom('Completed workshop', (int) $trainer->id, 2);
        $this->classroom('Draft workshop', (int) $trainer->id, 5);

        $html = $this->content_for($trainer);
        $this->assertSame(1, substr_count($html, 'Active workshop'), 'listed once although both columns name the user');
        $this->assertStringNotContainsString('Completed workshop', $html);
        $this->assertStringNotContainsString('Draft workshop', $html);
    }

    public function test_a_user_who_trains_nothing_sees_the_empty_message(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertStringContainsString(get_string('notrainings', 'block_sentientia_trainer'), $this->content_for($user));
    }
}
