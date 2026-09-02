<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\webhooks\dispatcher;
use local_sentientia_api\webhooks\queue;
use local_sentientia_api\webhooks\sender;
use local_sentientia_api\webhooks\signer;
use local_sentientia_api\webhooks\subscription;

/**
 * Outbound webhooks (ADR-030 Wave A): signing, subscription validation,
 * flag gating, queue drain / backoff / dead-letter, observer wiring, privacy.
 *
 * @package    local_sentientia_api
 * @category   test
 * @covers     \local_sentientia_api\webhooks\signer
 * @covers     \local_sentientia_api\webhooks\subscription
 * @covers     \local_sentientia_api\webhooks\dispatcher
 * @covers     \local_sentientia_api\webhooks\queue
 * @covers     \local_sentientia_api\webhooks\sender
 * @covers     \local_sentientia_api\observer
 */
final class webhooks_test extends \advanced_testcase {

    /** @var string A public (TEST-NET-3) address: never in the default blocked ranges, no DNS needed. */
    private const OK_URL = 'https://203.0.113.10/hooks/sentientia';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        sender::$transport = null;
        if (class_exists('\local_sentientia_platform\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
    }

    protected function tearDown(): void {
        sender::$transport = null;
        parent::tearDown();
    }

    private function require_platform(): void {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            $this->markTestSkipped('local_sentientia_platform not installed');
        }
    }

    private function flags_on(): void {
        $ff = '\local_sentientia_platform\feature_flags';
        $ff::set(dispatcher::FLAG_MASTER, 0, true, null, 'phpunit');
        $ff::set(dispatcher::FLAG_WEBHOOKS, 0, true, null, 'phpunit');
        $ff::invalidate_caches();
    }

    private function make_sub(array $events = ['course.completed'], int $tenant = 0): \stdClass {
        $id = subscription::create((object) [
            'name' => 'Test hook', 'url' => self::OK_URL, 'events' => $events, 'costcenterid' => $tenant,
        ]);
        return subscription::get($id);
    }

    // ── Signing ──────────────────────────────────────────────────────────

    public function test_signature_roundtrip_tamper_and_replay(): void {
        $body = '{"event":"course.completed","data":{"userid":5}}';
        $secret = subscription::generate_secret();
        $ts = 1_700_000_000;

        $header = signer::sign($body, $secret, $ts);
        $this->assertMatchesRegularExpression('/^t=1700000000,v1=[0-9a-f]{64}$/', $header);

        $this->assertTrue(signer::verify($body, $secret, $header, 300, $ts + 10));
        $this->assertFalse(signer::verify($body . ' ', $secret, $header, 300, $ts + 10), 'tampered body');
        $this->assertFalse(signer::verify($body, 'wrong', $header, 300, $ts + 10), 'wrong secret');
        $this->assertFalse(signer::verify($body, $secret, $header, 300, $ts + 301), 'outside replay window');
        $this->assertFalse(signer::verify($body, $secret, 'garbage', 300, $ts), 'malformed header');
    }

    // ── Subscription validation ─────────────────────────────────────────

    public function test_url_must_be_https(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/https/');
        subscription::validate_url('http://203.0.113.10/hook');
    }

    public function test_url_private_address_is_blocked(): void {
        set_config('curlsecurityblockedhosts', "10.0.0.0/8\n127.0.0.1\n192.168.0.0/16");
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/blocked/i');
        subscription::validate_url('https://10.1.2.3/internal');
    }

    public function test_create_normalises_events_and_generates_secret(): void {
        $sub = $this->make_sub(['course.completed', 'bogus.event', 'enrolment.created']);
        $this->assertSame('course.completed,enrolment.created', $sub->events);
        $this->assertSame(64, strlen($sub->secret));
        $this->assertSame(1, (int) $sub->enabled);

        $this->assertCount(1, subscription::matching('course.completed', 0));
        $this->assertCount(1, subscription::matching('course.completed', 77), 'tenant-0 sub listens to every tenant');
        $this->assertCount(0, subscription::matching('certificate.issued', 0));
    }

    public function test_create_requires_events(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/event/i');
        subscription::create((object) ['name' => 'x', 'url' => self::OK_URL, 'events' => ['nope']]);
    }

    // ── Flag gating ─────────────────────────────────────────────────────

    public function test_enqueue_is_noop_when_flags_off(): void {
        global $DB;
        $this->require_platform();
        $this->make_sub();
        $this->assertSame(0, dispatcher::enqueue('course.completed', 0, 1, ['userid' => 1]));
        $this->assertSame(0, $DB->count_records(queue::TABLE));
    }

    public function test_enqueue_queues_one_row_per_matching_subscription(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $this->make_sub(['course.completed']);
        $this->make_sub(['course.completed', 'enrolment.created']);
        $this->make_sub(['enrolment.created']);

        $this->assertSame(2, dispatcher::enqueue('course.completed', 0, 7, ['userid' => 7, 'courseid' => 3]));
        $rows = $DB->get_records(queue::TABLE);
        $this->assertCount(2, $rows);
        $row = reset($rows);
        $this->assertSame('queued', $row->status);
        $this->assertSame(7, (int) $row->userid);
        $payload = json_decode($row->payload, true);
        $this->assertSame('course.completed', $payload['event']);
        $this->assertSame(3, $payload['data']['courseid']);
        $this->assertArrayNotHasKey('email', $payload['data']);
    }

    // ── Drain / backoff / dead-letter ────────────────────────────────────

    public function test_drain_sends_signed_request_and_marks_sent(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $sub = $this->make_sub();
        dispatcher::enqueue('course.completed', 0, 7, ['userid' => 7]);

        $captured = [];
        sender::$transport = function (string $url, array $headers, string $body) use (&$captured) {
            $captured = ['url' => $url, 'headers' => $headers, 'body' => $body];
            return [200, ''];
        };

        $stats = queue::drain();
        $this->assertSame(1, $stats['sent']);
        $this->assertSame(self::OK_URL, $captured['url']);
        $this->assertSame('course.completed', $captured['headers']['X-Sentientia-Event']);
        $this->assertTrue(signer::verify($captured['body'], $sub->secret, $captured['headers'][signer::HEADER]));

        $row = $DB->get_record(queue::TABLE, []);
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertGreaterThan(0, (int) $DB->get_field(subscription::TABLE, 'lastsuccess', ['id' => $sub->id]));
    }

    public function test_drain_backoff_then_dead_letter(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $this->make_sub();
        dispatcher::enqueue('course.completed', 0, 7, ['userid' => 7]);
        sender::$transport = fn() => [503, 'HTTP 503'];

        $stats = queue::drain();
        $this->assertSame(1, $stats['failed']);
        $row = $DB->get_record(queue::TABLE, []);
        $this->assertSame('failed', $row->status);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertEqualsWithDelta(time() + 60, (int) $row->nextattempt, 5);
        $this->assertSame(503, (int) $row->httpstatus);

        // Not due yet -> nothing happens.
        $this->assertSame(0, array_sum(queue::drain()));

        // Force due and exhaust attempts.
        for ($i = 2; $i <= queue::MAX_ATTEMPTS; $i++) {
            $DB->set_field(queue::TABLE, 'nextattempt', time() - 1, ['id' => $row->id]);
            queue::drain();
        }
        $row = $DB->get_record(queue::TABLE, ['id' => $row->id]);
        $this->assertSame('dead', $row->status);
        $this->assertSame(queue::MAX_ATTEMPTS, (int) $row->attempts);
    }

    public function test_disabled_subscription_dead_letters_without_sending(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $sub = $this->make_sub();
        dispatcher::enqueue('course.completed', 0, 7, ['userid' => 7]);
        subscription::set_enabled($sub->id, false);

        $called = false;
        sender::$transport = function () use (&$called) {
            $called = true;
            return [200, ''];
        };
        $stats = queue::drain();
        $this->assertFalse($called, 'nothing must leave for a disabled subscription');
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame('dead', $DB->get_field(queue::TABLE, 'status', []));
    }

    public function test_flag_turned_off_after_enqueue_blocks_delivery(): void {
        $this->require_platform();
        $this->flags_on();
        $this->make_sub();
        dispatcher::enqueue('course.completed', 0, 7, ['userid' => 7]);

        $ff = '\local_sentientia_platform\feature_flags';
        $ff::set(dispatcher::FLAG_WEBHOOKS, 0, false, null, 'phpunit');
        $ff::invalidate_caches();

        $called = false;
        sender::$transport = function () use (&$called) {
            $called = true;
            return [200, ''];
        };
        $stats = queue::drain();
        $this->assertFalse($called, 'flag OFF means nothing leaves the platform');
        $this->assertSame(1, $stats['skipped']);
    }

    public function test_retry_and_prune(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $this->make_sub();
        dispatcher::enqueue('course.completed', 0, 7, ['userid' => 7]);
        $id = (int) $DB->get_field(queue::TABLE, 'id', []);

        $DB->update_record(queue::TABLE, (object) ['id' => $id, 'status' => 'dead', 'attempts' => 5]);
        queue::retry($id);
        $row = $DB->get_record(queue::TABLE, ['id' => $id]);
        $this->assertSame('queued', $row->status);
        $this->assertSame(0, (int) $row->attempts);

        set_config('log_retention_days', 30, 'local_sentientia_api');
        $DB->update_record(queue::TABLE, (object) ['id' => $id, 'status' => 'sent', 'timeupdated' => time() - 40 * DAYSECS]);
        $this->assertSame(1, queue::prune());
        $this->assertSame(0, $DB->count_records(queue::TABLE));
    }

    // ── Observer wiring ──────────────────────────────────────────────────

    public function test_course_completed_event_enqueues_delivery(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $this->make_sub(['course.completed']);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);

        $completion = (object) [
            'userid' => $user->id, 'course' => $course->id, 'timeenrolled' => time(),
            'timestarted' => time(), 'timecompleted' => time(), 'reaggregate' => 0,
        ];
        $completion->id = $DB->insert_record('course_completions', $completion);
        \core\event\course_completed::create_from_completion($completion)->trigger();

        $rows = $DB->get_records(queue::TABLE, ['eventkey' => 'course.completed']);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame((int) $user->id, (int) $row->userid);
        $payload = json_decode($row->payload, true);
        $this->assertSame((int) $course->id, $payload['data']['courseid']);
    }

    public function test_observer_is_noop_when_flags_off(): void {
        global $DB;
        $this->require_platform();
        $this->make_sub(['enrolment.created']);

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id);   // fires user_enrolment_created

        $this->assertSame(0, $DB->count_records(queue::TABLE));
    }

    // ── Privacy ──────────────────────────────────────────────────────────

    public function test_privacy_delete_for_user_removes_deliveries(): void {
        global $DB;
        $this->require_platform();
        $this->flags_on();
        $this->make_sub();
        $user = $this->getDataGenerator()->create_user();
        dispatcher::enqueue('course.completed', 0, (int) $user->id, ['userid' => (int) $user->id]);
        dispatcher::enqueue('course.completed', 0, 999999, ['userid' => 999999]);

        $contextlist = new \core_privacy\local\request\approved_contextlist($user, 'local_sentientia_api',
            [\context_system::instance()->id]);
        \local_sentientia_api\privacy\provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records(queue::TABLE, ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records(queue::TABLE, ['userid' => 999999]));
    }
}
