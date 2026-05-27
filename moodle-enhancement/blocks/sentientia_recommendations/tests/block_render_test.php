<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once($CFG->dirroot . '/blocks/sentientia_recommendations/block_sentientia_recommendations.php');

/**
 * PHPUnit tests for block_sentientia_recommendations — Phase H.0.
 *
 * Verifies the block stays quiet for guests / when no batch exists, and
 * renders content once a batch is persisted. No live API calls.
 *
 * @package    block_sentientia_recommendations
 * @covers     \block_sentientia_recommendations
 */
final class block_render_test extends \advanced_testcase {

    public function test_block_empty_for_guest(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $block = new \block_sentientia_recommendations();
        $block->init();
        $content = $block->get_content();
        $this->assertSame('', trim($content->text));
    }

    public function test_block_empty_when_flag_off(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Advanced AML']);
        $this->setUser($user);

        // Persist a batch but leave the master flag at its default (OFF).
        $parsed = [(object)['course_id' => (int)$course->id, 'score' => 90, 'reasoning' => 'x']];
        \local_sentientia_recommendations\recommendation_engine::persist_batch(
            (int)$user->id, $parsed, 0, 0, 'mock');

        $block = new \block_sentientia_recommendations();
        $block->init();
        $content = $block->get_content();
        // Flag OFF — block renders nothing even though a batch exists.
        $this->assertSame('', trim($content->text));
    }

    public function test_block_empty_when_no_active_batch(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->enable_flag((int)$user->id);

        $block = new \block_sentientia_recommendations();
        $block->init();
        $content = $block->get_content();
        // Flag ON but no batch persisted — block should render nothing.
        $this->assertSame('', trim($content->text));
    }

    public function test_block_renders_active_recommendations(): void {
        global $PAGE;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Advanced AML']);
        $this->setUser($user);
        $this->enable_flag((int)$user->id);

        // Need a renderer context for render_from_template.
        $PAGE->set_url('/my/index.php');

        $parsed = [];
        $o = new \stdClass();
        $o->course_id = (int)$course->id;
        $o->score = 88;
        $o->reasoning = 'Builds on what you completed.';
        $parsed[] = $o;

        \local_sentientia_recommendations\recommendation_engine::persist_batch(
            (int)$user->id, $parsed, 0, 0, 'mock');

        $block = new \block_sentientia_recommendations();
        $block->init();
        $content = $block->get_content();

        $this->assertStringContainsString('Advanced AML', $content->text);
        $this->assertStringContainsString('88', $content->text);
    }

    /**
     * Enable the master flag (global scope). Skips the test gracefully if
     * the feature-flag registry isn't available in this sandbox.
     */
    private function enable_flag(int $byuserid): void {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            $this->markTestSkipped('local_airpay_core feature_flags not available');
        }
        // Signature: set($key, $tenant_id, $value, $by_userid, $reason, $customer_id)
        \local_airpay_core\feature_flags::set(
            'sentientia.recommendations.enabled', 0, true, $byuserid, 'phpunit', 0);
        \local_airpay_core\feature_flags::invalidate_caches();
    }
}
