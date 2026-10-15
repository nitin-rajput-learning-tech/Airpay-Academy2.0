<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_catalog;

use local_sentientia_platform\feature_flags;

defined('MOODLE_INTERNAL') || die();

/**
 * Visual-pass finding 7 (a), 2026-10-15: the catalogue offers "Request enrolment" where a request is the learner's
 * way in (local_sentientia_request, flag sentientia.request.catalog_button, default OFF).
 *
 * The rule for when a request is the way in is the request plugin's (catalog_cta, tested there). This locks in what
 * the catalogue adds and how it renders the answer:
 *
 *   1. enrolment::request_state() keeps the catalogue's own routes first: a paid course goes to the cart, and a
 *      free course the learner is offered one-click enrolment for is enrolled at once, so neither gets a request.
 *   2. With the flag OFF (the default) every state is 'none' and with_request_offers() hands the cards back
 *      exactly as they went in.
 *   3. with_request_offers() marks only the cards the viewer is not enrolled in, never changes the fields the
 *      cached lists carry, and gives the dialog the PLAIN course name (Mustache escapes it once, request_button.js
 *      escapes it for its dialog), so "A &amp; B" is not shown twice-escaped.
 *   4. It answers only for the logged-in viewer.
 *
 * @package    local_sentientia_catalog
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_catalog\enrolment::request_state
 * @covers     \local_sentientia_catalog\catalog_manager::with_request_offers
 * @group      tenant_isolation
 */
final class request_offer_test extends \advanced_testcase {

    // Provisions {user}.open_path + {course}.open_path on the test DB. The trait's setUp() is aliased because this
    // class has a setUp() of its own, which would otherwise replace it.
    use \local_sentientia_platform\phpunit\open_path_fixture_trait {
        setUp as fixtureSetUp;
    }

    protected function setUp(): void {
        $this->fixtureSetUp();
        if (!class_exists('\\local_sentientia_request\\catalog_cta')) {
            $this->markTestSkipped('local_sentientia_request is not installed.');
        }
        feature_flags::invalidate_caches();
    }

    protected function tearDown(): void {
        feature_flags::invalidate_caches();
        parent::tearDown();
    }

    private function flag(string $key, bool $on, int $tenant = 0): void {
        feature_flags::set($key, $tenant, $on, (int) get_admin()->id, 'test');
        feature_flags::invalidate_caches();
    }

    private function learner(int $root = 1): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', '/' . $root . '/3', ['id' => $u->id]);
        $learner = $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
        $this->setUser($learner);
        return $learner;
    }

    /** A visible course in tenant $root with nothing a learner can enrol through (manual + disabled self only). */
    private function restricted_course(int $root = 1, string $fullname = 'Restricted course'): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['visible' => 1, 'fullname' => $fullname]);
        $DB->set_field('course', 'open_path', '/' . $root . '/5', ['id' => $course->id]);
        return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
    }

    private function add_fee(int $courseid, string $cost): void {
        global $DB;
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $courseid, 'sortorder' => 9,
            'cost' => $cost, 'currency' => 'INR',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /** A card as format_course() shapes the fields this cares about. */
    private function card(\stdClass $course, bool $enrolled = false): array {
        return [
            'id'          => (int) $course->id,
            'fullname'    => format_string($course->fullname),
            'is_enrolled' => $enrolled,
            'detailurl'   => 'https://example.test/course/view.php?id=' . $course->id,
        ];
    }

    // 1. The catalogue's own routes come first.

    public function test_a_free_restricted_course_offers_the_request(): void {
        $learner = $this->learner();
        $this->flag('sentientia.request.catalog_button', true);
        $course = $this->restricted_course();
        $this->assertSame('request', enrolment::request_state($learner, (int) $course->id));
    }

    public function test_a_paid_course_goes_to_the_cart_not_to_a_request(): void {
        $learner = $this->learner();
        $this->flag('sentientia.request.catalog_button', true);
        $course = $this->restricted_course();
        $this->add_fee((int) $course->id, '499.00');
        $this->assertSame('none', enrolment::request_state($learner, (int) $course->id));
        // The caller may pass the price it already has: the same answer.
        $this->assertSame('none', enrolment::request_state($learner, (int) $course->id,
            commerce::get_course_price((int) $course->id)));
    }

    public function test_a_free_course_the_learner_is_offered_one_click_for_is_enrolled_at_once_not_requested(): void {
        $learner = $this->learner(1);
        $this->flag('sentientia.request.catalog_button', true);
        $course = $this->restricted_course(1);
        $this->assertSame('request', enrolment::request_state($learner, (int) $course->id));

        // The one-click flag ON for Airpay: the learner is enrolled by the click, so nothing to request.
        $this->flag(enrolment::FLAG, true, 1);
        $this->assertTrue(enrolment::should_offer_oneclick($learner, commerce::get_course_price((int) $course->id)));
        $this->assertSame('none', enrolment::request_state($learner, (int) $course->id));
    }

    // 2. Flag OFF: nothing changes.

    public function test_with_the_flag_off_the_state_is_none_and_the_cards_come_back_untouched(): void {
        $learner = $this->learner();
        $course = $this->restricted_course();
        $this->assertSame('none', enrolment::request_state($learner, (int) $course->id));

        $cards = [$this->card($course)];
        $this->assertSame($cards, catalog_manager::with_request_offers($cards, (int) $learner->id));
    }

    // 3. Marking the cards.

    public function test_a_card_is_marked_and_an_enrolled_card_is_not(): void {
        $learner = $this->learner();
        $this->flag('sentientia.request.catalog_button', true);
        $open = $this->restricted_course();
        $mine = $this->restricted_course();
        $cards = catalog_manager::with_request_offers(
            [$this->card($open), $this->card($mine, true)], (int) $learner->id);

        $this->assertTrue($cards[0]['can_request']);
        $this->assertFalse($cards[0]['request_pending']);
        $this->assertSame('Restricted course', $cards[0]['request_coursename']);
        $this->assertArrayNotHasKey('can_request', $cards[1], 'An enrolled card keeps its Continue button.');
        $this->assertEquals($open->id, $cards[0]['id']);
    }

    public function test_a_pending_request_marks_the_card_pending_not_requestable(): void {
        global $DB;
        $learner = $this->learner();
        $this->flag('sentientia.request.catalog_button', true);
        $course = $this->restricted_course();
        $DB->insert_record('local_sentientia_request', (object) [
            'userid' => $learner->id, 'item_type' => 'course', 'itemid' => $course->id, 'courseid' => $course->id,
            'costcenterid' => 1, 'reason' => 'I need this course for my onboarding plan this month.',
            'status' => 'pending', 'route' => 'admin', 'approver_userid' => (int) get_admin()->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $cards = catalog_manager::with_request_offers([$this->card($course)], (int) $learner->id);
        $this->assertFalse($cards[0]['can_request']);
        $this->assertTrue($cards[0]['request_pending']);
    }

    public function test_the_dialog_gets_the_plain_name_not_a_twice_escaped_one(): void {
        $learner = $this->learner();
        $this->flag('sentientia.request.catalog_button', true);
        $course = $this->restricted_course(1, 'Fraud &amp; Risk <b>101</b>');
        $card = $this->card($course);
        $this->assertStringContainsString('&amp;', $card['fullname'], 'The card name is HTML-safe already.');

        $cards = catalog_manager::with_request_offers([$card], (int) $learner->id);
        $this->assertSame('Fraud & Risk 101', $cards[0]['request_coursename']);
        $this->assertSame($card['fullname'], $cards[0]['fullname'], 'The visible name is not touched.');
    }

    // 4. The viewer only.

    public function test_it_marks_nothing_for_anyone_but_the_logged_in_viewer(): void {
        $learner = $this->learner();
        $other = $this->getDataGenerator()->create_user();
        $this->flag('sentientia.request.catalog_button', true);
        $course = $this->restricted_course();
        $cards = [$this->card($course)];
        $this->assertSame($cards, catalog_manager::with_request_offers($cards, (int) $other->id));
        $this->assertSame([], catalog_manager::with_request_offers([], (int) $learner->id));
    }
}
