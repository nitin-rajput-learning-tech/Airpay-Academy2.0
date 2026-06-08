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

        // Flip the master flag ON. The guard MUST still fire because
        // Phase C.1 has no live-API flag — graph traffic stays gated.
        global $DB;
        $row = (object)[
            'flag_key'    => 'sentientia_m365_enabled',
            'tenant_id'   => 0,
            'enabled'     => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $columns = $DB->get_columns('local_sentientia_feature_flags');
        if (isset($columns['customer_id'])) {
            $row->customer_id = 0;
        }
        $where = ['flag_key' => 'sentientia_m365_enabled', 'tenant_id' => 0];
        if (isset($columns['customer_id'])) {
            $where['customer_id'] = 0;
        }
        $DB->delete_records('local_sentientia_feature_flags', $where);
        $DB->insert_record('local_sentientia_feature_flags', $row);

        $user = $this->getDataGenerator()->create_user();

        // Master flag ON, but graph traffic still throws.
        $this->expectException(\moodle_exception::class);
        graph_client::get_me($user->id, 1);
    }
}
