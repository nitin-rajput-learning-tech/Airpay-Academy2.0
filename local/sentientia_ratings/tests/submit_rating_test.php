<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-3 (2026-05-15) — tests for the rating write endpoint.
 *
 * Locks in:
 *   - submit_rating() inserts a new row when the user has never rated
 *   - submit_rating() updates the existing row when the user rates again
 *   - submit_rating() rejects rating values outside 1..5
 *   - submit_rating() rejects guest/system users (userid <= 1)
 *   - submit_rating() rejects empty/oversized ratearea
 *   - submit_rating() rejects invalid itemid
 *   - get_average() reflects all submitted ratings after submit
 *   - UNIQUE constraint prevents duplicate (userid, itemid, ratearea) rows
 *
 * @package    local_sentientia_ratings
 * @category   test
 */
final class submit_rating_test extends \advanced_testcase {

    public function test_submit_rating_inserts_new_row(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();

        $id = rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 5);

        $this->assertGreaterThan(0, $id);
        $row = $DB->get_record('local_sentientia_ratings', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(42, (int) $row->itemid);
        $this->assertSame('local_sentientia_courses', $row->ratearea);
        $this->assertSame((int) $u->id, (int) $row->userid);
        $this->assertSame(5, (int) $row->rating);
        $this->assertGreaterThan(0, (int) $row->timecreated);
        $this->assertSame((int) $row->timecreated, (int) $row->timemodified);
    }

    public function test_submit_rating_updates_existing_row(): void {
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();

        $id1 = rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 3);
        $first_time = (int) $DB->get_field('local_sentientia_ratings', 'timecreated', ['id' => $id1]);
        // Sleep 1 sec so timemodified is verifiably different.
        sleep(1);
        $id2 = rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 4);

        $this->assertSame($id1, $id2,
            'submit_rating must return the existing row id, not create a duplicate');
        $row = $DB->get_record('local_sentientia_ratings', ['id' => $id2], '*', MUST_EXIST);
        $this->assertSame(4, (int) $row->rating);
        $this->assertSame($first_time, (int) $row->timecreated,
            'timecreated must be preserved on update');
        $this->assertGreaterThan($first_time, (int) $row->timemodified,
            'timemodified must advance on update');
    }

    public function test_submit_rating_rejects_zero(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 0);
    }

    public function test_submit_rating_rejects_six(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 6);
    }

    public function test_submit_rating_rejects_guest_userid(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(42, 'local_sentientia_courses', 1, 4);  // 1 = guest
    }

    public function test_submit_rating_rejects_system_userid(): void {
        $this->resetAfterTest();

        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(42, 'local_sentientia_courses', 0, 4);  // 0 = no user
    }

    public function test_submit_rating_rejects_invalid_itemid(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(0, 'local_sentientia_courses', (int) $u->id, 4);
    }

    public function test_submit_rating_rejects_empty_ratearea(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(42, '', (int) $u->id, 4);
    }

    public function test_submit_rating_rejects_oversized_ratearea(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();

        // Schema column is 100 chars — anything longer must reject.
        $this->expectException(\moodle_exception::class);
        rating_manager::submit_rating(42, str_repeat('a', 101), (int) $u->id, 4);
    }

    public function test_get_average_reflects_all_ratings(): void {
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();

        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u1->id, 5);
        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u2->id, 3);
        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u3->id, 4);

        $avg = rating_manager::get_average(42, 'local_sentientia_courses');
        $this->assertSame(3, $avg->count);
        $this->assertEqualsWithDelta(4.0, $avg->average, 0.01);
    }

    public function test_get_user_rating_returns_users_rating(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 4);

        $this->assertSame(4,
            rating_manager::get_user_rating(42, 'local_sentientia_courses', (int) $u->id));
    }

    public function test_get_user_rating_returns_zero_for_unrated(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        // Note: no rating submitted.

        $this->assertSame(0,
            rating_manager::get_user_rating(42, 'local_sentientia_courses', (int) $u->id));
    }

    public function test_unique_constraint_prevents_duplicate_rows(): void {
        // Sanity check that the schema enforces what we expect — if this
        // breaks, submit_rating's race-window handling would silently allow
        // duplicates.
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();

        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 5);

        // Direct INSERT attempt with the same key should throw.
        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_sentientia_ratings', (object) [
            'itemid'       => 42,
            'ratearea'     => 'local_sentientia_courses',
            'userid'       => (int) $u->id,
            'rating'       => 3,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_different_ratearea_allows_separate_row(): void {
        // User can rate the SAME itemid in DIFFERENT areas — items are
        // scoped by (itemid, ratearea), not itemid alone.
        $this->resetAfterTest();
        global $DB;
        $u = $this->getDataGenerator()->create_user();

        rating_manager::submit_rating(42, 'local_sentientia_courses', (int) $u->id, 5);
        rating_manager::submit_rating(42, 'local_sentientia_classroom', (int) $u->id, 3);

        $this->assertEquals(2, $DB->count_records('local_sentientia_ratings', [
            'itemid' => 42, 'userid' => (int) $u->id,
        ]));
    }
}
