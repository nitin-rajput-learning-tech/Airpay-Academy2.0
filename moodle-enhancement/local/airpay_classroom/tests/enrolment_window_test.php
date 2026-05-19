<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 batch (2026-05-16) — tests for the classroom enrolment-window dates.
 *
 * Mirrors `local_airpay_learningpath\enrolment_window_test` to make sure
 * both plugins share identical semantics:
 *   - empty/0 stored as NULL (so "no window" is distinguishable)
 *   - update preserves existing dates when not supplied
 *   - end-before-start rejected at form validation
 *   - same-day window allowed
 *
 * @package    local_airpay_classroom
 * @category   test
 */
final class enrolment_window_test extends \advanced_testcase {

    public function test_create_persists_dates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $start = strtotime('2026-07-01');
        $end   = strtotime('2026-07-31');
        $cid = session_manager::create((object) [
            'name'      => 'July compliance workshop',
            'startdate' => $start,
            'enddate'   => $end,
        ]);

        $row = $DB->get_record('local_airpay_classroom', ['id' => $cid], '*', MUST_EXIST);
        $this->assertSame($start, (int) $row->startdate);
        $this->assertSame($end,   (int) $row->enddate);
    }

    public function test_create_without_dates_stores_null(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $cid = session_manager::create((object) [
            'name' => 'Ongoing classroom',
        ]);

        $row = $DB->get_record('local_airpay_classroom', ['id' => $cid], '*', MUST_EXIST);
        $this->assertNull($row->startdate);
        $this->assertNull($row->enddate);
    }

    public function test_update_can_clear_dates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $cid = session_manager::create((object) [
            'name'      => 'Will clear',
            'startdate' => strtotime('2026-07-01'),
            'enddate'   => strtotime('2026-07-31'),
        ]);

        session_manager::update($cid, (object) [
            'startdate' => 0,
            'enddate'   => 0,
        ]);

        $row = $DB->get_record('local_airpay_classroom', ['id' => $cid], '*', MUST_EXIST);
        $this->assertNull($row->startdate);
        $this->assertNull($row->enddate);
    }

    public function test_update_preserves_dates_when_not_supplied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $start = strtotime('2026-07-01');
        $cid = session_manager::create((object) [
            'name'      => 'Preserve',
            'startdate' => $start,
            'enddate'   => strtotime('2026-07-31'),
        ]);

        session_manager::update($cid, (object) ['name' => 'Renamed']);
        $row = $DB->get_record('local_airpay_classroom', ['id' => $cid], '*', MUST_EXIST);
        $this->assertSame($start, (int) $row->startdate,
            'Unrelated update must not clear startdate');
    }

    public function test_form_validation_rejects_end_before_start(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new \local_airpay_classroom\form\edit_classroom();
        $errors = $form->validation([
            'classroomid' => 0,
            'name'      => 'Bad window',
            'startdate' => strtotime('2026-12-31'),
            'enddate'   => strtotime('2026-06-01'),
            'capacity'  => 30,
        ], []);
        $this->assertArrayHasKey('enddate', $errors);
    }

    public function test_form_validation_accepts_same_day_window(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new \local_airpay_classroom\form\edit_classroom();
        $sameday = strtotime('2026-08-15');
        $errors = $form->validation([
            'classroomid' => 0,
            'name'      => 'Single-day',
            'startdate' => $sameday,
            'enddate'   => $sameday,
            'capacity'  => 30,
        ], []);
        $this->assertArrayNotHasKey('enddate', $errors,
            'Same-day classroom window should be allowed');
    }
}
