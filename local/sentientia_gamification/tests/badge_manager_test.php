<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_gamification;

defined('MOODLE_INTERNAL') || die();

/**
 * course_completed -> points_manager::award() -> badge_manager::check_badges().
 *
 * Regression (2026-09-02): the seeded "Compliance Champion"
 * (compliance_complete) and "Team Player" (leaderboard_top10) badges read
 * the BizLMS-injected {user}.open_path column. A vanilla Moodle schema —
 * the PHPUnit DB built by init.php, or any Sentientia customer without
 * BizLMS — has no such column, so the SELECT threw dml_read_exception out
 * of the course_completed observer. core\event\manager swallows observer
 * exceptions into debugging(), which surfaced as an "Unexpected
 * debugging() call detected" PHP notice in *other* plugins' tests
 * (local_sentientia_api webhooks_test) and silently aborted the award
 * chain: no badge check, no first_course bonus.
 *
 * @package    local_sentientia_gamification
 * @category   test
 */
#[\PHPUnit\Framework\Attributes\CoversClass(badge_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(points_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class badge_manager_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../lib.php');
        local_sentientia_gamification_seed_badges();
        if (class_exists('\local_sentientia_platform\feature_flags')) {
            $ff = '\local_sentientia_platform\feature_flags';
            $ff::set('engagement.gamification.enabled', 0, true, null, 'phpunit');
            $ff::invalidate_caches();
        }
    }

    /** Enrol $user in $course, record a completion and fire course_completed. */
    private function complete_course(\stdClass $user, \stdClass $course): void {
        global $DB;
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $now = time();
        $completion = (object) [
            'userid' => $user->id, 'course' => $course->id, 'timeenrolled' => $now,
            'timestarted' => $now, 'timecompleted' => $now, 'reaggregate' => 0,
        ];
        $completion->id = $DB->insert_record('course_completions', $completion);
        \core\event\course_completed::create_from_completion($completion)->trigger();
    }

    /** Sorted criteria_type list of the badges a user has earned. */
    private function earned_types(int $userid): array {
        $types = array_column(badge_manager::get_user_badges($userid), 'criteria_type');
        sort($types);
        return $types;
    }

    /**
     * Assert this plugin's observer neither threw nor debugged.
     *
     * core\event\manager reports an observer exception as "Exception
     * encountered in event observer '<callable>'", so the plugin name is in
     * the message. Other plugins' course_completed observers are not this
     * test's subject (local_sentientia_evaluation has the same open_path
     * dependence on a vanilla schema), so their messages are drained rather
     * than re-raised as a notice by the harness.
     */
    private function assert_gamification_observer_clean(): void {
        $messages = array_map(static fn($m) => $m->message, $this->getDebuggingMessages());
        $ours = array_values(array_filter($messages,
            static fn(string $m) => str_contains($m, 'local_sentientia_gamification')));
        $this->resetDebugging();
        $this->assertSame([], $ours, 'gamification course_completed observer must not throw');
    }

    /**
     * Drop {user}.open_path if an earlier test's fixture left it behind.
     * DDL survives resetAfterTest(), so without this the vanilla-schema
     * path would only be exercised on a freshly initialised PHPUnit DB.
     *
     * @return bool whether the column existed (and must be restored)
     */
    private function drop_bizlms_user_path(): bool {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255');
        if (!$dbman->field_exists($table, $field)) {
            return false;
        }
        $dbman->drop_field($table, $field);
        return true;
    }

    public function test_course_completed_awards_points_and_badges_on_vanilla_schema(): void {
        global $DB;
        // Precondition: the badge that reads open_path is in the catalogue.
        $this->assertTrue($DB->record_exists('local_sentientia_badges', ['criteria_type' => 'compliance_complete']));

        $restore = $this->drop_bizlms_user_path();
        try {
            $gen = $this->getDataGenerator();
            $course = $gen->create_course(['enablecompletion' => 1]);
            $user = $gen->create_user();
            $this->complete_course($user, $course);

            $this->assert_gamification_observer_clean();

            // 100 (course_completed) + 150 (first_course bonus — skipped when check_badges throws).
            $actions = $DB->get_fieldset_select('local_sentientia_points_log', 'action',
                'userid = :uid ORDER BY id', ['uid' => $user->id]);
            $this->assertSame(['course_completed', 'first_course'], $actions);
            $this->assertSame(250, points_manager::get_total((int) $user->id));
            $this->assertSame(250, (int) $DB->get_field('local_sentientia_streaks', 'total_points',
                ['userid' => $user->id]));

            // Both tenant-reading criteria were evaluated site-wide: first_course
            // earned, leaderboard_top10 earned (sole scorer), compliance_complete
            // not (no mandatory courses exist).
            $this->assertSame(['first_course', 'leaderboard_top10'], $this->earned_types((int) $user->id));
        } finally {
            if ($restore) {
                $this->ensure_bizlms_schema();
            }
        }
    }

    public function test_tenant_scoped_criteria_still_apply_with_bizlms_schema(): void {
        global $DB;
        $this->ensure_bizlms_schema();

        $gen = $this->getDataGenerator();
        $inayear = time() + YEARSECS;
        // Mandatory (enddate > 0) course in tenant 1 — the one the user completes.
        $course = $gen->create_course(['enablecompletion' => 1, 'enddate' => $inayear]);
        $DB->set_field('course', 'open_path', '/1', ['id' => $course->id]);
        // Mandatory course in tenant 77 — must NOT count against a tenant-1 user.
        $other = $gen->create_course(['enddate' => $inayear]);
        $DB->set_field('course', 'open_path', '/77', ['id' => $other->id]);

        $user = $gen->create_user();
        $DB->set_field('user', 'open_path', '/1/5', ['id' => $user->id]);
        $this->complete_course($user, $course);

        $this->assert_gamification_observer_clean();
        $this->assertSame(250, points_manager::get_total((int) $user->id));
        // compliance_complete only passes when the tenant filter excluded the /77 course.
        $this->assertSame(['compliance_complete', 'first_course', 'leaderboard_top10'],
            $this->earned_types((int) $user->id));
    }
}
