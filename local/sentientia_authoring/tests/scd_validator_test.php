<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_authoring\scd\document;
use local_sentientia_authoring\scd\schema;
use local_sentientia_authoring\scd\validation_result;
use local_sentientia_authoring\scd\validator;

/**
 * ADR-034 Stage 1 slice 1: the PHP validator of the Sentientia Canonical
 * Course Document agrees with the reference harness.
 *
 * The fixture is the harness's own (docs/research/2026-10-15-scorm-lxp/pilot/
 * fixtures/course-upi-dispute.json, copied byte for byte into
 * tests/fixtures/scd/), and every rule test below mirrors a harness test in
 * pilot/tests/test_harness.py (class FixtureValidation) with the same
 * mutation and the same expected rule status. Tests the harness does not
 * have (canonical hash, Python truthiness, R02, R11, R12, coverage) are marked.
 *
 * Pure PHP: no database, no network, no flag.
 *
 * @package    local_sentientia_authoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_authoring\scd\validator
 * @covers     \local_sentientia_authoring\scd\validation_result
 * @covers     \local_sentientia_authoring\scd\document
 * @covers     \local_sentientia_authoring\scd\schema
 */
final class scd_validator_test extends \basic_testcase {

    /**
     * sha256 of schema/sentientia-course.schema.json with LF line endings:
     * the harness's pilot/schema/sentientia-course.schema.json at 2026-11-12.
     * A change here means the two copies may have drifted: re-copy the
     * harness file, re-run its 55 tests, then update this pin.
     */
    private const SCHEMA_SHA256 = '093280dcb5bb79ce2fa5aa438090635b2593f6dffb5a8a3b06bd340499a1dc51';

    /**
     * sha256 of the fixture's canonical JSON as the harness computes it
     * (pilot/lib/common.py canonical_json(): sort_keys, ensure_ascii, no
     * whitespace), 26,661 characters.
     */
    private const FIXTURE_CANONICAL_SHA256 = 'f64b9f5517b35eed51c570ae0969f6365d017f18ada7762f274bb8dd36a7d963';

    /**
     * A fresh decoded copy of the harness fixture.
     *
     * @return \stdClass
     */
    private static function fixture(): \stdClass {
        $doc = document::decode((string) file_get_contents(__DIR__ . '/fixtures/scd/course-upi-dispute.json'));
        self::assertInstanceOf(\stdClass::class, $doc);
        return $doc;
    }

    /**
     * The first block of a type.
     *
     * @param \stdClass $doc
     * @param string    $type
     * @return \stdClass
     */
    private static function block(\stdClass $doc, string $type): \stdClass {
        foreach ($doc->blocks as $block) {
            if ($block->type === $type) {
                return $block;
            }
        }
        throw new \coding_exception("fixture has no {$type} block");
    }

    /**
     * A scenario node by id.
     *
     * @param \stdClass $scenario
     * @param string    $id
     * @return \stdClass
     */
    private static function node(\stdClass $scenario, string $id): \stdClass {
        foreach ($scenario->nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }
        throw new \coding_exception("scenario has no node {$id}");
    }

    /**
     * Validate as the harness's tests do: preview on, compile context.
     *
     * @param \stdClass $doc
     * @param array     $options
     * @return validation_result
     */
    private static function check(\stdClass $doc, array $options = []): validation_result {
        return validator::validate($doc, $options + ['context' => validator::CONTEXT_COMPILE, 'preview' => true]);
    }

    /**
     * A decision edge, shaped like the fixture's.
     *
     * @param string $id
     * @param string $to
     * @param bool   $correct
     * @return \stdClass
     */
    private static function edge(string $id, string $to, bool $correct): \stdClass {
        return (object) ['id' => $id, 'text' => (object) ['en' => $correct ? 'A' : 'B'], 'to' => $to,
            'score_delta' => $correct ? 1 : 0, 'outcome' => $correct ? 'acceptable' : 'recoverable',
            'correct' => $correct];
    }

    // ---------------------------------------------------------------------
    // The schema copy and the canonical form (not in the harness).
    // ---------------------------------------------------------------------

    public function test_the_schema_is_the_harness_copy_and_declares_twelve_rules(): void {
        $this->assertSame(self::SCHEMA_SHA256, schema::sha256(),
            'schema/sentientia-course.schema.json drifted from the harness copy.');
        $this->assertSame(validator::RULE_IDS, array_keys(schema::rules()));
        $this->assertSame(schema::VERSION, self::fixture()->scd_version);
    }

    public function test_canonical_json_hashes_like_the_harness(): void {
        $doc = self::fixture();
        $this->assertSame(self::FIXTURE_CANONICAL_SHA256, document::sha256($doc));
        $this->assertSame(26661, strlen(document::canonical($doc)));
        // Re-decoding the canonical text gives the same canonical text.
        $this->assertSame(document::canonical($doc), document::canonical(document::decode(document::canonical($doc))));
    }

    public function test_canonical_json_rules(): void {
        $value = document::decode('{"b":[1,{}],"a":"हि/é","c":{"0":"x"},"d":1.0,"e":[],"f":"' . "\x7f" . '"}');
        $this->assertSame('{"a":"\u0939\u093f/\u00e9","b":[1,{}],"c":{"0":"x"},"d":1.0,"e":[],"f":"\u007f"}',
            document::canonical($value));
    }

    // ---------------------------------------------------------------------
    // Harness parity (pilot/tests/test_harness.py, FixtureValidation).
    // ---------------------------------------------------------------------

    public function test_fixture_passes_all_rules(): void {
        $res = self::check(self::fixture());
        $this->assertTrue($res->ok(), $res->summary(20));
        foreach (validator::RULE_IDS as $rule) {
            $this->assertNotSame('fail', $res->status($rule), $rule);
        }
        $this->assertSame('pass', $res->status('schema'));
        // The store context (what repository::create() uses) passes too.
        $this->assertTrue(validator::validate(self::fixture())->ok());
        // And the JSON text form.
        $this->assertTrue(validator::validate(document::canonical(self::fixture()))->ok());
    }

    public function test_dangling_edge_is_r04(): void {
        $doc = self::fixture();
        self::block($doc, 'scenario')->nodes[0]->edges[0]->to = 'does_not_exist';
        $this->assertSame('fail', self::check($doc)->status('R04'));
    }

    public function test_unreachable_node_is_r04(): void {
        $doc = self::fixture();
        self::block($doc, 'scenario')->nodes[] = (object) ['id' => 'orphan', 'kind' => 'terminal',
            'terminal_outcome' => 'failure', 'prompt' => (object) ['en' => 'Nobody reaches this.']];
        $this->assertSame('fail', self::check($doc)->status('R04'));
    }

    public function test_cycle_is_rejected_unless_allowed(): void {
        $doc = self::fixture();
        $scenario = self::block($doc, 'scenario');
        self::node($scenario, 'n3')->edges[] = (object) ['id' => 'e3c', 'text' => (object) ['en' => 'Start over.'],
            'to' => 'n1', 'score_delta' => 0, 'outcome' => 'recoverable', 'correct' => false];
        $this->assertSame('fail', self::check($doc)->status('R04'));
        $scenario->allow_cycles = true;
        $this->assertNotSame('fail', self::check($doc)->status('R04'));
        $this->assertSame('warn', self::check($doc)->status('R04'));
    }

    /**
     * Two decision nodes that only lead to each other (not in the harness's
     * tests): reachable, cycle allowed, but no path from them ends, so R04
     * fails on "paths terminate" alone.
     */
    public function test_a_loop_that_never_reaches_a_terminal_is_r04(): void {
        $doc = self::fixture();
        $scenario = self::block($doc, 'scenario');
        $scenario->allow_cycles = true;
        self::node($scenario, 'n1')->edges[] = self::edge('e1d', 'loop_a', false);
        $scenario->nodes[] = (object) ['id' => 'loop_a', 'kind' => 'decision', 'prompt' => (object) ['en' => 'Again?'],
            'edges' => [self::edge('la1', 'loop_b', true), self::edge('la2', 'loop_b', false)]];
        $scenario->nodes[] = (object) ['id' => 'loop_b', 'kind' => 'decision', 'prompt' => (object) ['en' => 'Again?'],
            'edges' => [self::edge('lb1', 'loop_a', true), self::edge('lb2', 'loop_a', false)]];

        $res = self::check($doc);
        $this->assertSame('fail', $res->status('R04'));
        $errors = array_column(array_filter($res->by_rule('R04'), static function (array $i): bool {
            return $i['level'] === 'error';
        }), 'message');
        $this->assertContains("no path from node 'loop_a' reaches a terminal node", $errors);
        $this->assertContains("no path from node 'loop_b' reaches a terminal node", $errors);
        $this->assertCount(2, $errors, 'Only the two looping nodes fail: everything else is reachable and ends. '
            . implode(' | ', $errors));
    }

    /**
     * A scenario with no terminal node at all (not in the harness's tests).
     */
    public function test_a_scenario_without_a_terminal_node_is_r04(): void {
        $doc = self::fixture();
        $scenario = self::block($doc, 'scenario');
        $scenario->allow_cycles = true;
        $scenario->start = 'loop_a';
        $scenario->nodes = [
            (object) ['id' => 'loop_a', 'kind' => 'decision', 'prompt' => (object) ['en' => 'Again?'],
                'edges' => [self::edge('la1', 'loop_b', true), self::edge('la2', 'loop_b', false)]],
            (object) ['id' => 'loop_b', 'kind' => 'decision', 'prompt' => (object) ['en' => 'Again?'],
                'edges' => [self::edge('lb1', 'loop_a', true), self::edge('lb2', 'loop_a', false)]],
        ];

        $res = self::check($doc);
        $this->assertSame('fail', $res->status('R04'));
        $messages = array_column($res->by_rule('R04'), 'message');
        $this->assertContains('scenario has no terminal node', $messages);
        $this->assertContains("no path from node 'loop_a' reaches a terminal node", $messages);
    }

    public function test_missing_base_language_is_r01(): void {
        $doc = self::fixture();
        unset($doc->blocks[0]->title->en);
        $this->assertSame('fail', self::check($doc)->status('R01'));
    }

    public function test_duplicate_ids_are_r10(): void {
        $doc = self::fixture();
        $doc->blocks[1]->id = 'b01';
        $this->assertSame('fail', self::check($doc)->status('R10'));
    }

    public function test_multichoice_needs_exactly_one_correct_option_r06(): void {
        $doc = self::fixture();
        foreach (self::block($doc, 'quiz')->questions[0]->options as $option) {
            $option->correct = true;
        }
        $this->assertSame('fail', self::check($doc)->status('R06'));
    }

    public function test_missing_protected_term_is_r07(): void {
        $doc = self::fixture();
        $doc->blocks[1]->title->hi = 'बिना गोपनीय जानकारी माँगे सत्यापन';
        // Drops UPI and OTP from a bullet whose English text has both.
        $doc->blocks[1]->points[1]->hi = 'PIN, कार्ड नंबर या CVV कभी न माँगें';
        $res = self::check($doc);
        $this->assertSame('fail', $res->status('R07'));
        $pointers = array_column($res->by_rule('R07'), 'pointer');
        $this->assertContains('/blocks/1/points/1/hi', $pointers);
    }

    public function test_unapproved_document_needs_preview_r09(): void {
        $doc = self::fixture();
        $doc->provenance->review->state = 'in_review';
        $this->assertSame('fail', self::check($doc, ['preview' => false])->status('R09'));
        $this->assertNotSame('fail', self::check($doc, ['preview' => true])->status('R09'));
        // Storing a document under review is normal (not in the harness).
        $this->assertSame('pass', validator::validate($doc)->status('R09'));
        $doc->provenance->review->state = 'rejected';
        $this->assertSame('fail', self::check($doc)->status('R09'));
    }

    /**
     * The harness's chain of 120 decision nodes fails R05 there through the
     * state-token budget and here through the 60-node SCORM 1.2 cap (the
     * part of R05 this slice checks; validator::coverage()).
     */
    public function test_state_budget_blows_up_for_a_huge_scenario_r05(): void {
        $doc = self::fixture();
        $scenario = self::block($doc, 'scenario');
        for ($i = 0; $i < 120; $i++) {
            $next = $i < 119 ? sprintf('chain_%03d', $i + 1) : 't_success';
            $scenario->nodes[] = (object) ['id' => sprintf('chain_%03d', $i), 'kind' => 'decision',
                'prompt' => (object) ['en' => 'Next?'], 'edges' => [
                    self::edge(sprintf('edge_with_a_long_identifier_%03d_a', $i), $next, true),
                    self::edge(sprintf('edge_with_a_long_identifier_%03d_b', $i), $next, false),
                ]];
        }
        self::node($scenario, 'n3')->edges[0]->to = 'chain_000';
        $res = self::check($doc);
        $this->assertSame('fail', $res->status('R05'));
        $this->assertSame('pass', $res->status('R04'), 'The chain itself is a valid graph.');
        // Without SCORM 1.2 among the targets the cap is only a warning.
        $this->assertSame('warn', self::check($doc, ['standards' => ['scorm2004', 'cmi5']])->status('R05'));
    }

    public function test_media_path_traversal_is_r08(): void {
        $doc = self::fixture();
        $doc->blocks[0]->audio = (object) ['en' => (object) ['path' => '../../etc/passwd']];
        $this->assertSame('fail', self::check($doc)->status('R08'));
    }

    // ---------------------------------------------------------------------
    // Beyond the harness's own tests.
    // ---------------------------------------------------------------------

    public function test_r08_checks_existence_only_against_registered_assets(): void {
        $doc = self::fixture();
        $doc->blocks[0]->audio = (object) ['en' => (object) ['path' => 'audio/en/b01.mp3']];
        $this->assertSame('pass', self::check($doc)->status('R08'), 'Without media_known existence waits for the compiler.');
        $this->assertSame('fail', self::check($doc, ['media_known' => []])->status('R08'));
        $this->assertSame('pass', self::check($doc, ['media_known' => ['audio/en/b01.mp3']])->status('R08'));
        foreach (['/abs/b01.mp3', 'C:/b01.mp3', 'audio\\b01.mp3'] as $bad) {
            $doc->blocks[0]->audio->en->path = $bad;
            $this->assertSame('fail', self::check($doc)->status('R08'), $bad);
        }
    }

    public function test_r02_base_language_declared_once(): void {
        $doc = self::fixture();
        $doc->course->langs = array_values(array_diff($doc->course->langs, ['en']));
        $this->assertSame('fail', self::check($doc)->status('R02'));
        $doc = self::fixture();
        $doc->course->langs[] = 'hi';
        $res = self::check($doc);
        $this->assertSame('fail', $res->status('R02'));
        $this->assertSame('fail', $res->status('schema'), 'uniqueItems on course.langs reports it too.');
    }

    public function test_r11_long_option_ids_overflow_student_response(): void {
        $doc = self::fixture();
        foreach (self::block($doc, 'quiz')->questions as $question) {
            if ($question->type === 'multiresponse') {
                foreach ($question->options as $i => $option) {
                    $option->id = str_repeat((string) $i, 64);
                }
            }
        }
        $this->assertSame('fail', self::check($doc)->status('R11'));
    }

    public function test_r12_needs_a_clean_or_redacted_pii_scan(): void {
        foreach (['blocked' => 'fail', 'redacted' => 'pass', 'clean' => 'pass'] as $status => $expected) {
            $doc = self::fixture();
            $doc->course->source->pii_scan->status = $status;
            $this->assertSame($expected, self::check($doc)->status('R12'), $status);
        }
        $doc = self::fixture();
        unset($doc->course->source->pii_scan);
        $this->assertSame('fail', self::check($doc)->status('R12'));
    }

    /**
     * Python truthiness: "0" is a valid id (URL-safe, R10) and the harness
     * treats it as present; a PHP (bool) cast would call an info node whose
     * next is "0" a node without a successor.
     */
    public function test_an_id_of_zero_is_a_real_id(): void {
        $doc = self::fixture();
        $scenario = self::block($doc, 'scenario');
        foreach ($scenario->nodes as $node) {
            if ($node->id === 't_success') {
                $node->id = '0';
            }
            foreach ($node->edges ?? [] as $edge) {
                if ($edge->to === 't_success') {
                    $edge->to = '0';
                }
            }
            if (($node->next ?? null) === 't_success') {
                $node->next = '0';
            }
        }
        $res = self::check($doc);
        $this->assertSame('pass', $res->status('R04'), $res->summary());
        $this->assertSame('pass', $res->status('R10'));
    }

    public function test_structural_errors_are_reported_never_thrown(): void {
        $doc = self::fixture();
        $doc->blocks[0]->type = 'hologram';
        $res = self::check($doc);
        $this->assertSame('fail', $res->status('schema'));
        $this->assertStringContainsString('unknown type', $res->summary());

        $doc = self::fixture();
        unset($doc->blocks);
        $res = self::check($doc);
        $this->assertFalse($res->ok());
        $this->assertSame('fail', $res->status('schema'));
        $this->assertSame('fail', $res->status('R04'), 'A rule that cannot read the document fails, it does not crash.');

        $res = validator::validate([1, 2, 3]);
        $this->assertSame('fail', $res->status('schema'));
        $this->assertSame('not_run', $res->status('R01'));
    }

    public function test_invalid_json_text_is_an_exception(): void {
        $this->expectException(\moodle_exception::class);
        validator::validate('{"scd_version": ');
    }

    public function test_coverage_lists_every_rule_and_what_waits_for_the_compiler(): void {
        $coverage = validator::coverage();
        $this->assertSame(array_merge(['schema'], validator::RULE_IDS), array_keys($coverage));
        $partial = array_keys(array_filter($coverage, static function (array $c): bool {
            return $c['level'] === 'partial';
        }));
        $this->assertSame(['R05', 'R08'], $partial);
        foreach ($coverage as $rule => $c) {
            $this->assertNotSame('', $c['checks'], $rule);
            if ($c['level'] === 'partial') {
                $this->assertNotSame('', $c['waits'], $rule);
            }
        }
    }
}
