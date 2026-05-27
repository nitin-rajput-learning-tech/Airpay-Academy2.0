<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live;

use local_sentientia_live\question_types\word_cloud;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for the word_cloud question type — Phase E.5
 * (2026-05-25).
 *
 * Coverage targets called out in the chip spec:
 *   1. Profanity filter blocks denylisted words.
 *   2. Valid single-word submission lands in the tally.
 *   3. max_responses_per_user cap rejects further submissions.
 *   4. Empty / whitespace-only submission is rejected.
 *   5. Multi-word submissions are tokenised + counted per word.
 *
 * Run via:
 *   cd /path/to/moodle/public
 *   vendor/bin/phpunit local/sentientia_live/tests/word_cloud_test.php
 *
 * @package    local_sentientia_live
 * @covers     \local_sentientia_live\question_types\word_cloud
 * @covers     \local_sentientia_live\profanity_filter
 */
final class word_cloud_test extends \advanced_testcase {

    /** @var int Test user ID used as session owner. */
    private int $ownerid;

    /** @var int Session ID created in setUp. */
    private int $sessionid;

    /** @var int Word-cloud slide ID created in setUp. */
    private int $slideid;

    /** @var int Participant ID created in setUp. */
    private int $participantid;

    /** @var int Second participant for multi-tab tests. */
    private int $participant2id;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        profanity_filter::reset_cache();

        $owner = $this->getDataGenerator()->create_user();
        $this->ownerid = (int) $owner->id;

        $this->sessionid = session_manager::create($this->ownerid,
            'Word cloud test session');
        $this->slideid = slide_manager::add(
            $this->sessionid, 'wordcloud',
            'What three words describe Airpay culture?',
            [
                'max_word_length' => 50,
                'max_responses_per_user' => 3,
                'min_word_length' => 2,
            ]);
        // session_manager::start needs the session to be in draft and
        // have at least one slide — both true.
        session_manager::start_session($this->sessionid);
        session_manager::set_current_slide($this->sessionid, $this->slideid);

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $p1 = participant_manager::join_or_resume(
            $this->sessionid, (int) $u1->id, 'Alice');
        $p2 = participant_manager::join_or_resume(
            $this->sessionid, (int) $u2->id, 'Bob');
        $this->participantid  = (int) $p1->id;
        $this->participant2id = (int) $p2->id;
    }

    // ── 1. Profanity filter ─────────────────────────────────────────

    public function test_profanity_filter_blocks_default_denylist(): void {
        $wc = new word_cloud();

        // 'shit' is in DEFAULT_DENYLIST_EN — submission should reject.
        $this->expectException(\moodle_exception::class);
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'shit',
        ]);
    }

    public function test_profanity_filter_blocks_mixed_clean_and_dirty(): void {
        global $DB;
        $wc = new word_cloud();

        // Submission has one clean token ('innovation') and one dirty
        // token ('fuck') — the dirty one is silently dropped, the
        // clean one persists.
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'innovation fuck',
        ]);

        $row = $DB->get_record('local_sentientia_live_responses', [
            'slideid' => $this->slideid,
            'participantid' => $this->participantid,
        ]);
        $this->assertNotEmpty($row);
        $words = word_cloud::decode_words($row->value_text);
        $this->assertSame(['innovation'], $words,
            'Dirty token must be filtered; clean token must persist.');
    }

    public function test_profanity_filter_blocks_substring_matches(): void {
        $wc = new word_cloud();
        // 'fucking' contains the denied substring 'fuck' — should fail.
        $this->expectException(\moodle_exception::class);
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'fucking',
        ]);
    }

    public function test_customer_override_replaces_default_list(): void {
        // Customer 42 ships a denylist that includes "boring" but NOT
        // anything from the default. Inject directly into the cache;
        // this skips the local_airpay_core round-trip.
        profanity_filter::override_for_tests(42, ['boring']);

        $this->assertTrue(profanity_filter::is_denied('boring', 42));
        $this->assertFalse(profanity_filter::is_denied('shit', 42),
            'Default denylist must be replaced, not appended to.');
        $this->assertTrue(profanity_filter::is_denied('shit', 0),
            'Customer 0 still uses the default denylist.');
    }

    // ── 2. Valid single-word submission ─────────────────────────────

    public function test_valid_single_word_submission_persists(): void {
        $wc = new word_cloud();
        $response_id = $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust',
        ]);
        $this->assertGreaterThan(0, $response_id);

        $tally = $wc->tally($this->sessionid, $this->slideid);
        $this->assertSame(['trust' => 1], $tally);
    }

    public function test_resubmission_appends_to_existing_words(): void {
        $wc = new word_cloud();
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust',
        ]);
        // Second submission from same participant — appends, not replaces.
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'innovation',
        ]);

        $tally = $wc->tally($this->sessionid, $this->slideid);
        $this->assertSame(['trust' => 1, 'innovation' => 1], $tally);
    }

    public function test_lowercase_aggregation(): void {
        $wc = new word_cloud();
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'Trust',
        ]);
        $wc->persist_response($this->participant2id, [
            'slideid'    => $this->slideid,
            'value_text' => 'TRUST',
        ]);

        $tally = $wc->tally($this->sessionid, $this->slideid);
        $this->assertSame(['trust' => 2], $tally,
            'Case differences must collapse into one tally bucket.');
    }

    // ── 3. Max responses cap ────────────────────────────────────────

    public function test_max_responses_per_user_cap_blocks_overflow(): void {
        $wc = new word_cloud();
        // Fill the participant's word list to the cap in one go.
        // List becomes ["one","two","three"] = at cap (default 3).
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'one two three',
        ]);

        // Fourth word — participant already at cap — must reject.
        $this->expectException(\moodle_exception::class);
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'four',
        ]);
    }

    public function test_max_responses_caps_multi_word_at_boundary(): void {
        $wc = new word_cloud();
        // Submit 5 words in one go; cap should trim to first 3.
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'one two three four five',
        ]);
        $tally = $wc->tally($this->sessionid, $this->slideid);
        $this->assertCount(3, $tally);
        $this->assertArrayHasKey('one', $tally);
        $this->assertArrayHasKey('two', $tally);
        $this->assertArrayHasKey('three', $tally);
        $this->assertArrayNotHasKey('four', $tally,
            'Words beyond max_responses must be dropped.');
    }

    // ── 4. Empty / whitespace submission ────────────────────────────

    public function test_empty_submission_rejected(): void {
        $wc = new word_cloud();
        $this->expectException(\moodle_exception::class);
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => '',
        ]);
    }

    public function test_whitespace_only_submission_rejected(): void {
        $wc = new word_cloud();
        $this->expectException(\moodle_exception::class);
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => "   \t\n  ",
        ]);
    }

    public function test_too_short_tokens_filtered_to_nothing_rejected(): void {
        // min_word_length = 2 ⇒ "a" and "i" both drop ⇒ nothing left
        // ⇒ rejection.
        $wc = new word_cloud();
        $this->expectException(\moodle_exception::class);
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'a i',
        ]);
    }

    // ── 5. Multi-word splitting ────────────────────────────────────

    public function test_multi_word_submission_tokenises_correctly(): void {
        $wc = new word_cloud();
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust innovation speed',
        ]);
        $tally = $wc->tally($this->sessionid, $this->slideid);
        $this->assertSame(
            ['trust' => 1, 'innovation' => 1, 'speed' => 1],
            $tally);
    }

    public function test_punctuation_splits_tokens(): void {
        $wc = new word_cloud();
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust, innovation; speed!',
        ]);
        $tally = $wc->tally($this->sessionid, $this->slideid);
        $this->assertSame(
            ['trust' => 1, 'innovation' => 1, 'speed' => 1],
            $tally);
    }

    public function test_tally_sorted_desc_by_count(): void {
        $wc = new word_cloud();
        // Three participants each submit "trust"; one submits "speed".
        $wc->persist_response($this->participantid, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust',
        ]);
        $wc->persist_response($this->participant2id, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust speed',
        ]);
        $u3 = $this->getDataGenerator()->create_user();
        $p3 = participant_manager::join_or_resume(
            $this->sessionid, (int) $u3->id, 'Carla');
        $wc->persist_response((int) $p3->id, [
            'slideid'    => $this->slideid,
            'value_text' => 'trust',
        ]);

        $tally = $wc->tally($this->sessionid, $this->slideid);
        // 'trust' has 3 hits; 'speed' has 1 — order matters.
        $keys = array_keys($tally);
        $this->assertSame('trust', $keys[0]);
        $this->assertSame('speed', $keys[1]);
        $this->assertSame(3, $tally['trust']);
        $this->assertSame(1, $tally['speed']);
    }

    // ── 6. validate_config / interface contract ────────────────────

    public function test_validate_config_rejects_out_of_range_max_responses(): void {
        $wc = new word_cloud();
        $errors = $wc->validate_config(['max_responses_per_user' => 0]);
        $this->assertArrayHasKey('max_responses_per_user', $errors);

        $errors = $wc->validate_config(['max_responses_per_user' => 99]);
        $this->assertArrayHasKey('max_responses_per_user', $errors);

        $errors = $wc->validate_config(['max_responses_per_user' => 3]);
        $this->assertSame([], $errors);
    }

    public function test_validate_config_min_must_not_exceed_max(): void {
        $wc = new word_cloud();
        $errors = $wc->validate_config([
            'min_word_length' => 10,
            'max_word_length' => 5,
        ]);
        $this->assertArrayHasKey('min_word_length', $errors);
    }

    public function test_tokenise_keeps_unicode_letters(): void {
        // Hindi parity check — Devanagari survives tokenisation.
        $words = word_cloud::tokenise('विश्वास नवाचार');
        $this->assertSame(['विश्वास', 'नवाचार'], $words);
    }

    public function test_decode_words_back_compat_with_legacy_plain_text(): void {
        // Pre-Phase-E.5 rows stored value_text as a plain string.
        // decode_words must tokenise on whitespace in that case.
        $words = word_cloud::decode_words('trust innovation');
        $this->assertSame(['trust', 'innovation'], $words);
    }

    public function test_decode_words_handles_json_array_shape(): void {
        $words = word_cloud::decode_words('["trust","innovation"]');
        $this->assertSame(['trust', 'innovation'], $words);
    }
}
