<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 batch (2026-05-16) — tests for startdate/enddate + rich-text description
 * fields on learning paths.
 *
 * Locks in:
 *   - create() persists startdate, enddate, descriptionformat
 *   - update() can clear the dates back to NULL (passing 0 / empty)
 *   - update() preserves dates when not supplied
 *   - descriptionformat defaults to FORMAT_HTML when not supplied
 *   - enddate < startdate validation triggers in the form
 *
 * @package    local_sentientia_learningpath
 * @category   test
 */
final class enrolment_window_test extends \advanced_testcase {

    public function test_create_persists_dates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $start = strtotime('2026-06-01');
        $end   = strtotime('2026-12-31');
        $pid = path_manager::create((object) [
            'name'        => 'Compliance Q3 path',
            'description' => '<p>HTML <strong>content</strong></p>',
            'descriptionformat' => FORMAT_HTML,
            'startdate'   => $start,
            'enddate'     => $end,
        ]);

        $row = $DB->get_record('local_sentientia_learningpath', ['id' => $pid], '*', MUST_EXIST);
        $this->assertSame($start, (int) $row->startdate);
        $this->assertSame($end,   (int) $row->enddate);
        $this->assertSame(FORMAT_HTML, (int) $row->descriptionformat);
        $this->assertStringContainsString('<strong>content</strong>', $row->description);
    }

    public function test_create_without_dates_stores_null(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $pid = path_manager::create((object) [
            'name' => 'No-window path',
            // intentionally no startdate / enddate
        ]);

        $row = $DB->get_record('local_sentientia_learningpath', ['id' => $pid], '*', MUST_EXIST);
        $this->assertNull($row->startdate);
        $this->assertNull($row->enddate);
    }

    public function test_update_can_clear_dates(): void {
        // Empty input must NULL the column, not set it to 0 — otherwise
        // every "no enrolment window" path would falsely look bounded.
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $start = strtotime('2026-06-01');
        $pid = path_manager::create((object) [
            'name'      => 'Will clear dates',
            'startdate' => $start,
            'enddate'   => strtotime('2026-12-31'),
        ]);

        path_manager::update($pid, (object) [
            'startdate' => 0,
            'enddate'   => 0,
        ]);

        $row = $DB->get_record('local_sentientia_learningpath', ['id' => $pid], '*', MUST_EXIST);
        $this->assertNull($row->startdate,
            'Empty date input must store NULL, not 0');
        $this->assertNull($row->enddate);
    }

    public function test_update_preserves_dates_when_not_supplied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $start = strtotime('2026-06-01');
        $end   = strtotime('2026-12-31');
        $pid = path_manager::create((object) [
            'name'      => 'Preserve dates',
            'startdate' => $start,
            'enddate'   => $end,
        ]);

        // Update only the name — dates should survive.
        path_manager::update($pid, (object) ['name' => 'New name']);
        $row = $DB->get_record('local_sentientia_learningpath', ['id' => $pid], '*', MUST_EXIST);
        $this->assertSame($start, (int) $row->startdate);
        $this->assertSame($end,   (int) $row->enddate);
    }

    public function test_descriptionformat_defaults_to_html(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $pid = path_manager::create((object) [
            'name' => 'Default format',
        ]);

        $row = $DB->get_record('local_sentientia_learningpath', ['id' => $pid], '*', MUST_EXIST);
        $this->assertSame(FORMAT_HTML, (int) $row->descriptionformat);
    }

    public function test_form_validation_rejects_end_before_start(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new \local_sentientia_learningpath\form\edit_path();
        $errors = $form->validation([
            'pathid' => 0,
            'name' => 'Bad window',
            'startdate' => strtotime('2026-12-31'),
            'enddate'   => strtotime('2026-06-01'),  // before start
        ], []);
        $this->assertArrayHasKey('enddate', $errors);
    }

    public function test_form_validation_accepts_equal_start_and_end(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new \local_sentientia_learningpath\form\edit_path();
        $sameday = strtotime('2026-06-15');
        $errors = $form->validation([
            'pathid' => 0,
            'name' => 'Single-day window',
            'startdate' => $sameday,
            'enddate'   => $sameday,
        ], []);
        $this->assertArrayNotHasKey('enddate', $errors,
            'Same-day window should be allowed (single-day compliance event)');
    }
}
