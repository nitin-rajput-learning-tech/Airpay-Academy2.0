<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

use local_sentientia_platform\compat\user_api;

defined('MOODLE_INTERNAL') || die();

/**
 * The user create/update shim (Moodle 5.3 compat FX-12).
 *
 * Plugins create and update users through user_api so the 5.3 deprecated user_*() globals (which make PHPUnit fail
 * with "unexpected debugging") are never reached, while 5.1 and 5.2 keep using user/lib.php. These tests run on
 * whichever Moodle is underneath, so on 5.3 they prove the \core\user path and on 5.1/5.2 the legacy path, with
 * the same assertions. A deprecation notice would fail them through advanced_testcase's debugging check.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\compat\user_api
 */
final class user_api_test extends \advanced_testcase {

    /**
     * A minimal user record that both Moodle paths accept.
     *
     * @param string $username
     * @return \stdClass
     */
    private function new_user_record(string $username): \stdClass {
        return (object) [
            'username'   => $username,
            'password'   => hash_internal_user_password('Unused-' . $username . '!1'),
            'email'      => $username . '@example.com',
            'firstname'  => 'Priya',
            'lastname'   => 'Test',
            'auth'       => 'manual',
            'confirmed'  => 1,
            'mnethostid' => get_config('core', 'mnet_localhost_id') ?: 1,
        ];
    }

    public function test_create_returns_the_new_id_and_stores_the_user(): void {
        global $DB;
        $this->resetAfterTest();

        $id = user_api::create($this->new_user_record('userapi1'), false, false);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('userapi1@example.com', $DB->get_field('user', 'email', ['id' => $id]));
    }

    public function test_create_accepts_an_array(): void {
        global $DB;
        $this->resetAfterTest();

        $id = user_api::create((array) $this->new_user_record('userapi2'), false, false);

        $this->assertSame('userapi2', $DB->get_field('user', 'username', ['id' => $id]));
    }

    public function test_create_fires_user_created_when_asked_and_not_otherwise(): void {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();
        user_api::create($this->new_user_record('userapi3'), false, true);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\user_created);
        $this->assertCount(1, $events);
        $sink->close();

        $sink = $this->redirectEvents();
        user_api::create($this->new_user_record('userapi4'), false, false);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\user_created);
        $this->assertCount(0, $events);
        $sink->close();
    }

    public function test_update_changes_only_the_given_fields_and_fires_user_updated(): void {
        global $DB;
        $this->resetAfterTest();

        $id = user_api::create($this->new_user_record('userapi5'), false, false);

        $sink = $this->redirectEvents();
        user_api::update((object) ['id' => $id, 'city' => 'Mumbai'], false, true);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\user_updated);
        $sink->close();

        $this->assertCount(1, $events);
        $record = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Mumbai', $record->city);
        $this->assertSame('userapi5@example.com', $record->email);
    }

    public function test_update_accepts_an_array(): void {
        global $DB;
        $this->resetAfterTest();

        $id = user_api::create($this->new_user_record('userapi6'), false, false);
        user_api::update(['id' => $id, 'suspended' => 1], false, false);

        $this->assertSame('1', (string) $DB->get_field('user', 'suspended', ['id' => $id]));
    }

    public function test_has_core_user_api_matches_the_running_moodle(): void {
        $this->assertSame(
            method_exists(\core\user::class, 'create_user') && method_exists(\core\user::class, 'update_user'),
            user_api::has_core_user_api()
        );
    }
}
