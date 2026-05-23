<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_airpay_core\user_status
 *
 * Exercises the user-status helper that powers P0 borrow #10 — the
 * "Suspended"/"Deleted" badge on report-like surfaces. The helper is
 * called many times per request (one per user row in a report), so
 * the per-request cache is critical and tested explicitly.
 */
class user_status_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        user_status::reset_cache_for_phpunit();
    }

    public function test_active_user_returns_active(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame(user_status::ACTIVE, user_status::get($user->id));
        $this->assertFalse(user_status::is_suspended($user->id));
        $this->assertSame('', user_status::badge_for_user($user->id));
    }

    public function test_suspended_user_returns_suspended_and_renders_badge(): void {
        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->assertSame(user_status::SUSPENDED, user_status::get($user->id));
        $this->assertTrue(user_status::is_suspended($user->id));

        $html = user_status::badge_for_user($user->id);
        $this->assertStringContainsString('airpay-user-status-badge', $html);
        $this->assertStringContainsString('--suspended', $html);
        // Use the localised string, not the literal English word — covers
        // both en and hi packs.
        $this->assertStringContainsString(
            get_string('userstatus_suspended', 'local_airpay_core'),
            $html
        );
    }

    public function test_deleted_user_takes_precedence_over_suspended(): void {
        // A user can be both suspended (set first) and then deleted.
        // The helper must surface DELETED — the more severe state.
        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);
        delete_user($user);

        // delete_user() sets ->deleted = 1 and clears the username, but
        // leaves the row. Cache must miss the previous SUSPENDED reading.
        user_status::reset_cache_for_phpunit();
        $this->assertSame(user_status::DELETED, user_status::get($user->id));

        $html = user_status::badge_for_user($user->id);
        $this->assertStringContainsString('--deleted', $html);
    }

    public function test_get_many_returns_each_status_in_one_db_round(): void {
        $active = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);

        $statuses = user_status::get_many([$active->id, $suspended->id, $deleted->id]);

        $this->assertSame(user_status::ACTIVE,    $statuses[$active->id]);
        $this->assertSame(user_status::SUSPENDED, $statuses[$suspended->id]);
        $this->assertSame(user_status::DELETED,   $statuses[$deleted->id]);
    }

    public function test_get_many_handles_unknown_userids_gracefully(): void {
        $active = $this->getDataGenerator()->create_user();
        $statuses = user_status::get_many([$active->id, 9999999]);

        $this->assertSame(user_status::ACTIVE,  $statuses[$active->id]);
        $this->assertSame(user_status::DELETED, $statuses[9999999],
            'Unknown userid should fail-safe to DELETED so callers see the strikethrough.');
    }

    public function test_get_with_zero_or_negative_id_returns_active(): void {
        // Defensive contract — never throw on bogus input.
        $this->assertSame(user_status::ACTIVE, user_status::get(0));
        $this->assertSame(user_status::ACTIVE, user_status::get(-1));
    }

    public function test_from_record_populates_cache_without_db_query(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['suspended' => 1]);

        // Pre-populate via from_record — this is the renderer path.
        $rec = (object)['id' => $user->id, 'suspended' => 1, 'deleted' => 0];
        $this->assertSame(user_status::SUSPENDED, user_status::from_record($rec));

        // Now flip the DB row to active, but the cache still says SUSPENDED.
        // This proves from_record() seeds the cache without going to the DB
        // (which is the entire point — used in render-heavy code paths).
        $DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
        $this->assertSame(user_status::SUSPENDED, user_status::get($user->id));
    }

    public function test_badge_html_is_empty_for_active(): void {
        $this->assertSame('', user_status::badge_html(user_status::ACTIVE));
    }

    public function test_badge_html_escapes_the_label(): void {
        // The labels come from a lang pack and aren't user-supplied, but
        // assert escaping anyway — defence in depth against a future
        // lang-pack contributor adding HTML by accident.
        $html = user_status::badge_html(user_status::SUSPENDED);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringStartsWith('<span', $html);
        $this->assertStringEndsWith('</span>', trim($html));
    }
}
