<?php
// This file is part of Sentientia LMS.

/**
 * The "Show QR for this session" entry point on the attendance page (2026-09-30 review,
 * decision 6): a default-OFF feature flag, sentientia.classroom.qr_attendance, registered in
 * db/feature_flags.php. With it off the attendance page is exactly what it was.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * @covers \local_sentientia_platform\feature_flags
 */
final class qr_entry_point_test extends \advanced_testcase {

    private const FLAG = 'sentientia.classroom.qr_attendance';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        feature_flags::invalidate_caches();
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(self::FLAG, $registry, 'db/feature_flags.php registers the flag.');
        $this->assertFalse($registry[self::FLAG]['default']);
        $this->assertNotSame('', trim($registry[self::FLAG]['description']));

        $this->setAdminUser();
        $this->assertFalse(feature_flags::is_enabled(self::FLAG), 'Off until somebody turns it on.');
    }

    public function test_the_flag_can_be_turned_on_and_back_off(): void {
        $this->setAdminUser();
        feature_flags::set(self::FLAG, 0, true);
        $this->assertTrue(feature_flags::is_enabled(self::FLAG));

        feature_flags::set(self::FLAG, 0, null);
        $this->assertFalse(feature_flags::is_enabled(self::FLAG), 'Unsetting returns to the default, OFF.');
    }

    /** Render the attendance template with the given show_qr value. */
    private function render(bool $showqr, bool $canattend = true): string {
        global $OUTPUT, $PAGE;
        $PAGE->set_url('/local/sentientia_classroom/attendance.php', ['sessionid' => 7]);
        return $OUTPUT->render_from_template('local_sentientia_classroom/attendance', [
            'sessionid' => 7, 'loadedat' => time(), 'classroomid' => 3,
            'classroom_name' => 'C', 'session_title' => 'S', 'page_heading' => 'H', 'session_time' => 'T',
            'rows' => [], 'has_rows' => false, 'roster_size' => 0,
            'count_present' => 0, 'count_late' => 0, 'count_excused' => 0, 'count_absent' => 0,
            'can_attend' => $canattend, 'show_qr' => $showqr,
            'qr_url' => 'http://example.test/local/sentientia_pages/qr_attendance.php?sessionid=7',
            'back_url' => '/x',
        ]);
    }

    public function test_the_attendance_page_links_to_the_qr_page_only_when_the_flag_says_so(): void {
        $on = $this->render(true);
        $this->assertStringContainsString('data-region="show-qr"', $on);
        $this->assertStringContainsString('qr_attendance.php?sessionid=7', $on);
        $this->assertStringContainsString(get_string('attendance_show_qr', 'local_sentientia_classroom'), $on);

        $off = $this->render(false);
        $this->assertStringNotContainsString('show-qr', $off);
        $this->assertStringNotContainsString('qr_attendance.php', $off, 'Flag off: nothing links to the QR page.');
    }

    public function test_the_link_and_the_hint_have_english_and_hindi_text(): void {
        global $CFG;
        $keys = ['attendance_show_qr', 'attendance_untouched_hint', 'attendance_nothing_to_save'];
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include($CFG->dirroot . '/local/sentientia_classroom/lang/' . $lang . '/local_sentientia_classroom.php');
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $string, "$key is missing from the $lang pack.");
                $this->assertNotSame('', trim($string[$key]));
            }
        }
        $this->assertSame('Show QR for this session', get_string('attendance_show_qr', 'local_sentientia_classroom'));
    }
}
