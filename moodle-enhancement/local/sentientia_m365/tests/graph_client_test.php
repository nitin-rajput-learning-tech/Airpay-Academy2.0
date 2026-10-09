<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_m365;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for graph_client — Phase C.1.
 *
 * The chip's contract is that every public method MUST refuse to run
 * in Phase C.1, regardless of feature-flag state, by throwing
 * \moodle_exception('confirm_required'). The guard runs as the first
 * statement of each method so even a misconfigured flag cannot bypass
 * it.
 *
 * These tests assert that contract. They do NOT assert response shape
 * — Phase C.2 will land the real bodies and their own tests.
 *
 * @package    local_sentientia_m365
 * @covers     \local_sentientia_m365\graph_client
 */
final class graph_client_test extends \advanced_testcase {

    /**
     * The platform flag resolver keeps raw PHP statics (registry and overrides) that survive resetAfterTest's
     * database reset: flush them so a flag a previous test switched ON cannot leak into this one.
     */
    protected function setUp(): void {
        parent::setUp();
        \local_sentientia_platform\feature_flags::invalidate_caches();
    }

    public function test_get_me_throws_confirm_required(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/.*confirm.*/i');
        graph_client::get_me($user->id, 1);
    }

    public function test_list_sharepoint_sites_throws_confirm_required(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/.*confirm.*/i');
        graph_client::list_sharepoint_sites($user->id, 1, '');
    }

    public function test_get_user_calendar_throws_confirm_required(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/.*confirm.*/i');
        graph_client::get_user_calendar($user->id, 1, time(), time() + 86400);
    }

    public function test_guard_fires_even_when_feature_flag_is_on(): void {
        $this->resetAfterTest();

        // Flip the master flag ON through the platform's own writer. A hand-built row used to put an 'enabled'
        // property in the insert; the column is is_enabled (NOT NULL, no default), so Moodle dropped the
        // unknown property and the insert failed in strict SQL mode (and in lax mode stored the flag as OFF,
        // which made this test pass without ever having the flag on). set() writes is_enabled and flushes the
        // resolver's static caches, which survive resetAfterTest.
        \local_sentientia_platform\feature_flags::set('sentientia_m365_enabled', 0, true, null, 'phpunit');
        $this->assertTrue(\local_sentientia_platform\feature_flags::is_enabled('sentientia_m365_enabled'),
            'Precondition: the master flag really is ON.');

        $user = $this->getDataGenerator()->create_user();

        // Master flag ON, but graph traffic still throws: Phase C.1 has no live-API flag, so the guard ignores it.
        $this->expectException(\moodle_exception::class);
        graph_client::get_me($user->id, 1);
    }
}
