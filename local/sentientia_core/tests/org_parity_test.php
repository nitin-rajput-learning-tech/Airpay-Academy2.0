<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the ADR-020 Wave-3.3 org parity comparator.
 *
 * Uses a synthetic {@see org_source} to backfill the model (via org_reconciler)
 * and to drive the comparator, so it runs on a vanilla Moodle PHPUnit DB.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\org_parity
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class org_parity_test extends \advanced_testcase {

    /**
     * Synthetic source from [userid, openpath, supervisorid] rows.
     *
     * @param array $users
     * @return org_source
     */
    private function source(array $users): org_source {
        return new class($users) implements org_source {
            /** @param array $users */
            public function __construct(private array $users) {
            }
            public function users(): iterable {
                foreach ($this->users as $u) {
                    yield (object) ['userid' => $u[0], 'openpath' => $u[1], 'supervisorid' => $u[2]];
                }
            }
            public function unit_name(int $costcenterid): ?string {
                return null;
            }
        };
    }

    public function test_backfilled_model_is_in_full_parity(): void {
        $this->resetAfterTest();
        $users = [
            [101, '/1/2/3', 102],
            [102, '/1/2', 0],
            [103, '/77/9', 102],
        ];
        (new org_reconciler($this->source($users)))->reconcile();
        $r = (new org_parity($this->source($users)))->check();

        $this->assertSame(3, $r->checked);
        $this->assertSame(0, $r->skipped);
        $this->assertSame(0, $r->mgrmismatch);
        $this->assertSame(0, $r->memmismatch);
        $this->assertTrue((new org_parity($this->source($users)))->is_in_parity());
    }

    public function test_detects_manager_edge_drift(): void {
        $this->resetAfterTest();
        // Model backfilled with supervisor 500...
        (new org_reconciler($this->source([[201, '/1/2', 500]])))->reconcile();
        // ...but legacy now says 600 -> manager mismatch.
        $r = (new org_parity($this->source([[201, '/1/2', 600]])))->check();
        $this->assertSame(1, $r->checked);
        $this->assertSame(1, $r->mgrmismatch);
        $this->assertSame(0, $r->memmismatch);
        $this->assertNotEmpty($r->samples);
        $this->assertFalse((new org_parity($this->source([[201, '/1/2', 600]])))->is_in_parity());
    }

    public function test_detects_membership_drift(): void {
        $this->resetAfterTest();
        // Model backfilled with leaf /1/2 ...
        (new org_reconciler($this->source([[301, '/1/2', 0]])))->reconcile();
        // ...but legacy open_path leaf is now /1/9 -> the model unit idnumber (2) != leaf (9).
        $r = (new org_parity($this->source([[301, '/1/9', 0]])))->check();
        $this->assertSame(1, $r->checked);
        $this->assertSame(0, $r->mgrmismatch);
        $this->assertSame(1, $r->memmismatch);
    }

    public function test_unbackfilled_user_is_membership_mismatch(): void {
        $this->resetAfterTest();
        // Nothing backfilled; a user with a legacy path has no model membership.
        $r = (new org_parity($this->source([[401, '/1/2', 0]])))->check();
        $this->assertSame(1, $r->checked);
        $this->assertSame(1, $r->memmismatch, 'No model unit -> membership mismatch.');
        $this->assertFalse((new org_parity($this->source([[401, '/1/2', 0]])))->is_in_parity());
    }

    public function test_skips_unusable_and_out_of_scope_paths(): void {
        $this->resetAfterTest();
        (new org_reconciler($this->source([[501, '/1/2', 0]])))->reconcile([1]);
        $r = (new org_parity($this->source([
            [501, '/1/2', 0],     // In scope, backfilled.
            [502, '', 0],          // Unusable path -> skipped.
            [503, '/999/3', 0],    // Out of scope -> skipped.
        ])))->check([1]);
        $this->assertSame(1, $r->checked);
        $this->assertSame(2, $r->skipped);
        $this->assertSame(0, $r->mgrmismatch);
        $this->assertSame(0, $r->memmismatch);
    }

    public function test_is_in_parity_false_on_empty_model(): void {
        $this->resetAfterTest();
        // Source has a user but the model is empty -> not in parity.
        $this->assertFalse((new org_parity($this->source([[601, '/1/2', 0]])))->is_in_parity());
    }
}
