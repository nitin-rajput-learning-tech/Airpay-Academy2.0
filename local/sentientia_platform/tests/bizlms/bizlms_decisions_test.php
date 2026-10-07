<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\tests\bizlms\toy_importer;

/**
 * The decisions file (ADR-032 decision 9): the shape the loader reads, which entries count, and the checked-in
 * docs/cutover/bizlms-import-decisions.json itself.
 *
 * The loader was first written against a flat guess, so every owner choice in the real file was invisible to it:
 * a required decision blocked its feature, and a decision that declared a default silently used the importer's
 * default instead of the owner's value. These tests load the real shape, and the real file where it is reachable.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\decisions
 * @covers     \local_sentientia_platform\bizlms\context
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_decisions_test extends \advanced_testcase {

    protected function tearDown(): void {
        toy_importer::reset();
        parent::tearDown();
    }

    /**
     * A context whose importer declares these decisions.
     *
     * @param decisions $decisions
     * @param decision[] $declared
     * @return context
     */
    private function context_declaring(decisions $decisions, array $declared): context {
        toy_importer::reset();
        toy_importer::$extradecisions = $declared;
        return context::build(new toy_importer(), true, 0, $decisions);
    }

    /**
     * The owner-signed decisions file. The plugin is deployed on its own to a Moodle tree, where docs/ does not
     * exist, so the test reads the byte-identical copy under tests/fixtures/bizlms/ that ships with the plugin.
     * tools/check-bizlms-fixture-copies.php (CI, tree-drift-check) fails when the copy differs from the signed
     * file, and the last test below checks it again wherever docs/ is reachable. A path in
     * BIZLMS_DECISIONS_FILE overrides both.
     *
     * @return string
     */
    private function real_decisions_file(): string {
        $env = getenv('BIZLMS_DECISIONS_FILE') ?: '';
        if ($env !== '' && is_readable($env)) {
            return $env;
        }
        return __DIR__ . '/../fixtures/bizlms/bizlms-import-decisions.copy.json';
    }

    public function test_the_loader_reads_the_shape_of_the_real_file(): void {
        $decisions = decisions::load(__DIR__ . '/../fixtures/bizlms/decisions.sample.json');

        $this->assertTrue($decisions->has('framework.reader_flags_default'));
        $this->assertSame('off', $decisions->get('framework.reader_flags_default'));
        $this->assertSame('half_even', $decisions->get('toy.rounding'));
        $this->assertFalse($decisions->has('toy.nothere'));
        $this->assertSame('fallback', $decisions->get('toy.nothere', 'fallback'));
        $this->assertSame('Test Owner', $decisions->approval()['approved_by']);
        $this->assertSame(64, strlen($decisions->hash()));

        $this->assertTrue($decisions->accepts('toy', 'orphan_org'));
        $this->assertFalse($decisions->accepts('toy', 'orphan_user'));
        $this->assertSame(['z'], $decisions->mapped_enum_values('local_toy_item', 'kind'));
        $this->assertSame([], $decisions->mapped_enum_values('local_toy_item', 'other'));
    }

    public function test_only_an_accepted_entry_is_a_decision(): void {
        $decisions = decisions::load(__DIR__ . '/../fixtures/bizlms/decisions.sample.json');
        $this->assertSame('finance-confirm', $decisions->status('toy.credit'));
        $this->assertFalse($decisions->has('toy.credit'), 'the owner has not finished deciding this one');
        $this->assertSame('DEFAULT', $decisions->get('toy.credit', 'DEFAULT'));
        $this->assertSame(['toy.credit' => 'finance-confirm'], $decisions->not_accepted());
        $this->assertNull($decisions->status('toy.nothere'));
        $this->assertSame('accepted', $decisions->status('toy.rounding'));
    }

    public function test_the_owners_recorded_value_beats_the_importers_default(): void {
        $decisions = decisions::load(__DIR__ . '/../fixtures/bizlms/decisions.sample.json');
        // toy.rounding is declared with the default half_up; the file says half_even.
        $ctx = context::build(new toy_importer(), true, 0, $decisions);
        $this->assertSame('half_even', $ctx->decision('toy.rounding'));
    }

    public function test_a_decision_that_is_not_accepted_blocks_instead_of_using_a_default(): void {
        $decisions = decisions::load(__DIR__ . '/../fixtures/bizlms/decisions.sample.json');
        // With a default and without: neither may stand in for a choice the owner has left open.
        $ctx = $this->context_declaring($decisions, [
            new decision('toy.credit', 'How a legacy balance is honoured', true, 'write_off'),
        ]);
        try {
            $ctx->decision('toy.credit');
            $this->fail('the default replaced an open decision');
        } catch (blocked $e) {
            $this->assertSame('decision_not_accepted:toy.credit:finance-confirm', $e->getMessage());
        }
    }

    public function test_the_checked_in_file_loads_and_every_decision_in_it_is_accepted(): void {
        $path = $this->real_decisions_file();
        $this->assertFileExists($path, 'the plugin ships a copy of the signed file; a missing copy must not skip the test');
        $decisions = decisions::load($path);

        $this->assertGreaterThan(100, count($decisions->all()), 'the owner signed 109 decisions; an empty read proves nothing');
        $ctx = $this->context_declaring($decisions, [
            new decision('framework.reader_flags_default', 'Default of the reader flags', true, 'on'),
            new decision('cart.credit_balances', 'Legacy credit balances'),
            new decision('cart.erpnext_invoices_legal', 'Are the ERPNext invoices the legal ones'),
        ]);
        // The importer's default is "on"; the owner's recorded value is "off" and must win.
        $this->assertSame('off', $ctx->decision('framework.reader_flags_default'));

        // cart.finance_keys_status (owner, 2026-10-07, delegated; Airpay Finance not consulted): the two finance
        // keys are accepted, so nothing in the signed file is open any more and the cart importer, which declares
        // both, is not blocked. The finance-confirm BLOCKING mechanism is still held by decisions.sample.json
        // (toy.credit, above) and bizlms_runner_test::test_a_decision_the_owner_has_not_accepted_blocks_...
        $this->assertSame([], $decisions->not_accepted());
        $this->assertSame('frozen_pending_finance', $ctx->decision('cart.credit_balances'));
        $this->assertSame('reference_only_pending_finance', $ctx->decision('cart.erpnext_invoices_legal'));
        // The loader keeps no prose, so read the file for the reason: 'accepted' must never be mistakable for a
        // Finance sign-off, so each why says the answer was delegated and Finance was not consulted.
        $raw = json_decode((string) file_get_contents($path), true);
        foreach (['cart.credit_balances', 'cart.erpnext_invoices_legal'] as $key) {
            $this->assertSame(decisions::ACCEPTED, $decisions->status($key));
            $why = (string) ($raw['decisions'][$key]['why'] ?? '');
            $this->assertStringContainsString('delegated', strtolower($why), "{$key}: the reason says it was delegated");
            $this->assertStringContainsString('not consulted', strtolower($why),
                "{$key}: the reason says Airpay Finance was not consulted");
        }

        $lf = str_replace("\r\n", "\n", (string) file_get_contents($path));
        $this->assertSame(hash('sha256', $lf), $decisions->hash(), 'the pinned hash is over the LF bytes');
    }

    public function test_the_test_copy_is_the_signed_file_wherever_the_checkout_has_both(): void {
        $signed = __DIR__ . '/../../../../docs/cutover/bizlms-import-decisions.json';
        if (!is_readable($signed)) {
            $this->markTestSkipped('docs/ is not deployed with the plugin; tools/check-bizlms-fixture-copies.php checks this in CI');
        }
        $this->assertSame(
            str_replace("\r\n", "\n", (string) file_get_contents($signed)),
            str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../fixtures/bizlms/bizlms-import-decisions.copy.json')),
            'copy the signed file over tests/fixtures/bizlms/bizlms-import-decisions.copy.json in both trees'
        );
    }
}
