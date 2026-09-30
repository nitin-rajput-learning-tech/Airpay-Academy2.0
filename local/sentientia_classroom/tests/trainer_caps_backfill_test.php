<?php
// This file is part of Sentientia LMS.

/**
 * T-01 back-fill: the Sentientia `trainer` role (archetype teacher) can view classrooms and take
 * attendance (2026-09-30 review, decision 5).
 *
 * Until upgrade step 2026093001 the two capabilities were granted to the manager and
 * editingteacher archetypes only, so the users the QR attendance feature is for could not open
 * attendance.php or the QR page. db/access.php lists `teacher` now and
 * local_sentientia_classroom_backfill_teacher_caps() gives existing roles the same.
 *
 * NOTE for the lead: this file needs no database change of its own, but the plugin version was
 * bumped with it (2026093001), so PHPUnit must be re-initialised before it runs.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_classroom/db/upgradelib.php');

/**
 * @covers ::local_sentientia_classroom_backfill_teacher_caps
 */
final class trainer_caps_backfill_test extends \advanced_testcase {

    private const VIEW = 'local/sentientia_classroom:view';
    private const ATTENDANCE = 'local/sentientia_classroom:attendance';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** A role of the given archetype with NO setting for either capability, as an old site has it. */
    private function role_without_the_caps(string $archetype, string $shortname): int {
        $roleid = create_role('Test ' . $shortname, $shortname, '', $archetype);
        $syscontext = \context_system::instance();
        unassign_capability(self::VIEW, $roleid, $syscontext->id);
        unassign_capability(self::ATTENDANCE, $roleid, $syscontext->id);
        return $roleid;
    }

    /** @return int|null the stored permission at system context, or null when there is no row */
    private function permission(int $roleid, string $cap): ?int {
        global $DB;
        $value = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid, 'capability' => $cap, 'contextid' => \context_system::instance()->id,
        ]);
        return $value === false ? null : (int) $value;
    }

    public function test_a_teacher_archetype_role_gets_view_and_attendance(): void {
        $roleid = $this->role_without_the_caps('teacher', 'qrtrainer');
        $this->assertNull($this->permission($roleid, self::VIEW));
        $this->assertNull($this->permission($roleid, self::ATTENDANCE));

        $granted = local_sentientia_classroom_backfill_teacher_caps();

        $this->assertGreaterThanOrEqual(2, $granted);
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::VIEW));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::ATTENDANCE));
    }

    public function test_an_editingteacher_archetype_role_missing_them_gets_them_too(): void {
        $roleid = $this->role_without_the_caps('editingteacher', 'qreditor');

        local_sentientia_classroom_backfill_teacher_caps();

        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::VIEW));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::ATTENDANCE));
    }

    public function test_it_is_idempotent(): void {
        global $DB;
        $roleid = $this->role_without_the_caps('teacher', 'qrtrainer');
        local_sentientia_classroom_backfill_teacher_caps();
        $rows = $DB->count_records('role_capabilities');

        $this->assertSame(0, local_sentientia_classroom_backfill_teacher_caps(), 'A second run has nothing to do.');
        $this->assertSame($rows, $DB->count_records('role_capabilities'));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::ATTENDANCE));
    }

    public function test_an_explicit_prohibit_or_prevent_is_never_downgraded_or_overridden(): void {
        $syscontext = \context_system::instance();
        $prohibited = $this->role_without_the_caps('teacher', 'qrprohibit');
        assign_capability(self::ATTENDANCE, CAP_PROHIBIT, $prohibited, $syscontext->id, true);
        assign_capability(self::VIEW, CAP_PREVENT, $prohibited, $syscontext->id, true);

        local_sentientia_classroom_backfill_teacher_caps();

        $this->assertSame(CAP_PROHIBIT, $this->permission($prohibited, self::ATTENDANCE),
            'An administrator\'s PROHIBIT stands.');
        $this->assertSame(CAP_PREVENT, $this->permission($prohibited, self::VIEW),
            'An administrator\'s PREVENT stands.');
    }

    public function test_roles_of_other_archetypes_and_other_capabilities_are_left_alone(): void {
        $student = $this->role_without_the_caps('student', 'qrstudent');
        $plain = $this->role_without_the_caps('', 'qrplain');
        $trainer = $this->role_without_the_caps('teacher', 'qrtrainer');

        local_sentientia_classroom_backfill_teacher_caps();

        foreach ([$student, $plain] as $roleid) {
            $this->assertNull($this->permission($roleid, self::VIEW));
            $this->assertNull($this->permission($roleid, self::ATTENDANCE));
        }
        // Least privilege: the trainer does not become a classroom manager.
        foreach (['manage', 'create', 'update', 'delete'] as $cap) {
            $this->assertNull($this->permission($trainer, 'local/sentientia_classroom:' . $cap),
                "The trainer must not receive :$cap.");
        }
    }

    public function test_a_user_with_the_backfilled_role_can_take_attendance(): void {
        $roleid = $this->role_without_the_caps('teacher', 'qrtrainer');
        $trainer = $this->getDataGenerator()->create_user();
        role_assign($roleid, $trainer->id, \context_system::instance()->id);
        $this->setUser($trainer);
        $syscontext = \context_system::instance();

        $this->assertFalse(has_capability(self::ATTENDANCE, $syscontext), 'Before: cannot take attendance.');
        $this->assertFalse(has_capability(self::VIEW, $syscontext));

        local_sentientia_classroom_backfill_teacher_caps();
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(has_capability(self::ATTENDANCE, $syscontext), 'After: can take attendance.');
        $this->assertTrue(has_capability(self::VIEW, $syscontext));
        $this->assertFalse(has_capability('local/sentientia_classroom:create', $syscontext));
        $this->assertFalse(has_capability('local/sentientia_classroom:delete', $syscontext));
    }

    public function test_access_php_lists_the_teacher_archetype_for_a_fresh_install(): void {
        global $CFG;
        $capabilities = [];
        include($CFG->dirroot . '/local/sentientia_classroom/db/access.php');

        foreach ([self::VIEW, self::ATTENDANCE] as $cap) {
            $this->assertSame(CAP_ALLOW, $capabilities[$cap]['archetypes']['teacher'] ?? null,
                "$cap must list the teacher archetype.");
            $this->assertSame(CAP_ALLOW, $capabilities[$cap]['archetypes']['editingteacher'] ?? null);
            $this->assertSame(CAP_ALLOW, $capabilities[$cap]['archetypes']['manager'] ?? null);
        }
        foreach (['manage', 'create', 'update', 'delete'] as $cap) {
            $this->assertArrayNotHasKey('teacher',
                $capabilities['local/sentientia_classroom:' . $cap]['archetypes'] ?? [],
                ":$cap must not go to the teacher archetype.");
        }
    }

    public function test_the_upgrade_step_and_version_are_wired(): void {
        global $CFG;
        $upgrade = file_get_contents($CFG->dirroot . '/local/sentientia_classroom/db/upgrade.php');
        $this->assertStringContainsString('if ($oldversion < 2026093001)', $upgrade);
        $this->assertStringContainsString('local_sentientia_classroom_backfill_teacher_caps()', $upgrade);
        $this->assertStringContainsString("upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_classroom')",
            $upgrade);

        $plugin = new \stdClass();
        include($CFG->dirroot . '/local/sentientia_classroom/version.php');
        $this->assertGreaterThanOrEqual(2026093001, $plugin->version);
    }
}
