<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\external;

defined('MOODLE_INTERNAL') || die();

/**
 * G-06 — Verify the enrolment deep-link in the exams list datatable.
 *
 * Locks in:
 * - The link goes to the Sentientia enrolled-users page
 *   (/local/sentientia_courses/enrolledusers.php) for the parent course of the
 *   wrapping quiz (quiz.course), not for the exam id. Phase 3 B.3 (2026-05-11)
 *   replaced the old core /enrol/users.php deep-link (which opened in a new tab)
 *   with this page; the test still asserted the old URL and new-tab attributes,
 *   so it failed on every Moodle version once it ran.
 * - Link is gated on local/sentientia_exams:enrol
 * - When the exam has no bound quiz (or quizid points to nothing), no link
 *   is emitted (you can't enrol into a course that doesn't exist)
 *
 * @package    local_sentientia_exams
 * @category   test
 */
final class enrol_deeplink_test extends \advanced_testcase {

    private function seed_exam(string $name, int $quizid, string $path = '/1'): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_exams')) {
            $this->markTestSkipped('local_sentientia_exams table not present.');
        }
        $now = time();
        return (int) $DB->insert_record('local_sentientia_exams', (object) [
            'name'         => $name,
            'description'  => '',
            'quizid'       => $quizid,
            'duration'     => 1800,
            'passinggrade' => 70.0,
            'status'       => 1,
            'costcenterid' => 0,
            'open_path'    => $path,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_enrol_link_uses_quiz_parent_courseid(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course + a quiz inside it. The exam wraps that quiz.
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz',
            ['course' => $course->id, 'name' => 'Test Quiz']);
        $examid = $this->seed_exam('My Exam', (int) $quiz->id);

        $result = list_exams::execute('', 'name', 'asc', 0, 25, '{}');
        $this->assertGreaterThanOrEqual(1, $result['total']);

        $row = $result['rows'][0];
        $this->assertSame($examid, $row['id']);
        $this->assertStringContainsString('/local/sentientia_courses/enrolledusers.php', $row['actions'],
            'enrol deep-link should be present for siteadmin');
        // Critical: the link carries the QUIZ'S course id, not the exam id. The whole href is compared so that
        // 'id=1' cannot match 'id=10', and the page name is part of it.
        $expected = (new \moodle_url('/local/sentientia_courses/enrolledusers.php', ['id' => (int) $course->id]))->out(false);
        $this->assertStringContainsString('href="' . s($expected) . '"', $row['actions'],
            'enrol link should target the quiz\'s parent course');
        if ((int) $course->id !== $examid) {
            $this->assertStringNotContainsString('enrolledusers.php?id=' . $examid . '"', $row['actions'],
                'the exam id must not be used as a course id');
        }
        // The old core page is no longer linked.
        $this->assertStringNotContainsString('/enrol/users.php', $row['actions']);
    }

    public function test_no_enrol_link_when_quiz_missing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Exam pointing at a non-existent quizid → quiz_courseid will be null
        // after the LEFT JOIN, so no enrol link should be emitted.
        $this->seed_exam('Orphan Exam', 99999);

        $result = list_exams::execute('', 'name', 'asc', 0, 25, '{}');
        $row = $result['rows'][0];

        $this->assertStringNotContainsString('enrolledusers.php', $row['actions'],
            'orphan exam (no parent quiz) must not get an enrol deep-link');
        $this->assertStringNotContainsString('/enrol/users.php', $row['actions']);
    }
}
