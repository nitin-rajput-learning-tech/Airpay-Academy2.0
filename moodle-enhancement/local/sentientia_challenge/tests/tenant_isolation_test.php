<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_challenge\external\get_challenge;
use local_sentientia_challenge\external\list_challenges;

/**
 * ADR-031: challenges are managed, read and joined inside the caller's tenant.
 *
 * :manage defaults to the manager archetype, which every tenant admin holds at
 * system context. Until 2026-09-25 update_challenge / delete_challenge acted on
 * any id (delete also wiped the challenge's attempts and leaderboard rows),
 * get_challenge / view.php / join() loaded any id, and create_challenge()
 * stamped a tenantless caller's challenge as GLOBAL (costcenterid 0), i.e.
 * published it to every tenant.
 *
 * Asserted three ways each: a scoped tenant admin (manager-archetype role at
 * system context, open_path /1) is refused on tenant 177 and on global rows; a
 * caller with no resolvable tenant gets nothing; the site admin still does
 * everything.
 *
 * @package    local_sentientia_challenge
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_challenge\challenge_engine
 * @covers     \local_sentientia_challenge\external\get_challenge
 * @covers     \local_sentientia_challenge\external\list_challenges
 * @group      tenant_isolation
 */
final class tenant_isolation_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A tenant admin as UAT has them: manager-archetype role at SYSTEM context. */
    private function tenant_admin_at(string $path): \stdClass {
        global $DB;
        $u = $this->user_at($path);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        return $u;
    }

    /**
     * An active challenge of tenant $cc (0 = global), created by the site admin.
     * Leaves the site admin logged in: call it before setUser().
     */
    private function challenge_of(int $cc, string $shortname): \stdClass {
        global $DB;
        $this->setAdminUser();
        $id = challenge_engine::create_challenge(['name' => "C {$shortname}", 'shortname' => $shortname,
            'status' => challenge_engine::STATUS_ACTIVE, 'costcenterid' => $cc]);
        return $DB->get_record('local_sentientia_challenge_challenges', ['id' => $id], '*', MUST_EXIST);
    }

    private function assert_out_of_tenant(callable $fn, string $why): void {
        try {
            $fn();
            $this->fail($why);
        } catch (\moodle_exception $e) {
            $this->assertSame('error_outoftenant', $e->errorcode, $why);
        }
    }

    // ── writes (P0) ──────────────────────────────────────────────────────

    public function test_a_tenant_admin_cannot_edit_or_delete_another_tenants_challenge(): void {
        global $DB;
        $theirs = $this->challenge_of(177, 'theirs');
        $learner = $this->user_at('/177/9');
        $this->setUser($learner);
        challenge_engine::join((int) $theirs->id, (int) $learner->id);

        $this->setUser($this->tenant_admin_at('/1'));
        $this->assert_out_of_tenant(fn() => challenge_engine::update_challenge((int) $theirs->id, ['name' => 'Hijacked']),
            'A /1 tenant admin must not edit a tenant 177 challenge.');
        $this->assert_out_of_tenant(fn() => challenge_engine::delete_challenge((int) $theirs->id),
            'A /1 tenant admin must not delete a tenant 177 challenge.');

        $this->assertSame('C theirs', $DB->get_field('local_sentientia_challenge_challenges', 'name', ['id' => $theirs->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_challenge_attempts', ['challengeid' => $theirs->id]),
            'Tenant 177 learners\' progress must survive a refused delete.');
    }

    public function test_a_tenant_admin_cannot_edit_or_delete_a_global_challenge(): void {
        $global = $this->challenge_of(0, 'global');
        $this->setUser($this->tenant_admin_at('/1'));
        $this->assert_out_of_tenant(fn() => challenge_engine::update_challenge((int) $global->id, ['name' => 'x']),
            'A global challenge reaches every tenant: only a cross-tenant user manages it.');
        $this->assert_out_of_tenant(fn() => challenge_engine::delete_challenge((int) $global->id),
            'Deleting a global challenge wiped every tenant\'s attempts.');
    }

    public function test_a_tenant_admin_still_manages_their_own_tenants_challenge(): void {
        global $DB;
        $mine = $this->challenge_of(1, 'mine');
        $this->setUser($this->tenant_admin_at('/1/3'));
        challenge_engine::update_challenge((int) $mine->id, ['name' => 'Renamed']);
        $this->assertSame('Renamed', $DB->get_field('local_sentientia_challenge_challenges', 'name', ['id' => $mine->id]));
        challenge_engine::delete_challenge((int) $mine->id);
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_challenges', ['id' => $mine->id]));
    }

    public function test_create_is_refused_without_a_tenant_and_for_another_tenant(): void {
        global $DB;
        foreach (['', 'garbage'] as $i => $path) {
            $this->setUser($this->tenant_admin_at($path));
            $this->assert_out_of_tenant(fn() => challenge_engine::create_challenge(['name' => 'N', 'shortname' => "n{$i}"]),
                "open_path '{$path}' used to publish a GLOBAL challenge to every tenant.");
        }

        $this->setUser($this->tenant_admin_at('/1'));
        $this->assert_out_of_tenant(
            fn() => challenge_engine::create_challenge(['name' => 'F', 'shortname' => 'f', 'costcenterid' => 177]),
            'A scoped manager must not create a challenge for another tenant.');
        $this->assert_out_of_tenant(
            fn() => challenge_engine::create_challenge(['name' => 'G', 'shortname' => 'g', 'costcenterid' => 0]),
            'A scoped manager must not create a global challenge.');

        $id = challenge_engine::create_challenge(['name' => 'Own', 'shortname' => 'own']);
        $this->assertSame(1, (int) $DB->get_field('local_sentientia_challenge_challenges', 'costcenterid', ['id' => $id]));
    }

    public function test_the_site_admin_still_manages_every_challenge(): void {
        global $DB;
        $theirs = $this->challenge_of(177, 'ta');
        $global = $this->challenge_of(0, 'ga');
        $this->setAdminUser();
        challenge_engine::update_challenge((int) $theirs->id, ['name' => 'By admin']);
        challenge_engine::delete_challenge((int) $global->id);
        $this->assertSame('By admin', $DB->get_field('local_sentientia_challenge_challenges', 'name', ['id' => $theirs->id]));
        $this->assertFalse($DB->record_exists('local_sentientia_challenge_challenges', ['id' => $global->id]));
    }

    // ── by-id reads and join ─────────────────────────────────────────────

    public function test_a_learner_reads_and_joins_only_global_and_own_tenant_challenges(): void {
        $foreign = $this->challenge_of(1, 'fa');
        $global = $this->challenge_of(0, 'gl');
        $learner = $this->user_at('/177/9');
        $this->setUser($learner);

        $this->assert_out_of_tenant(fn() => get_challenge::execute((int) $foreign->id),
            'A tenant 177 learner must not read a tenant 1 challenge by id.');
        $this->assert_out_of_tenant(fn() => challenge_engine::join((int) $foreign->id, (int) $learner->id),
            'A tenant 177 learner must not add attempts to a tenant 1 challenge.');

        $this->assertSame((int) $global->id, get_challenge::execute((int) $global->id)['id']);
        $this->assertGreaterThan(0, challenge_engine::join((int) $global->id, (int) $learner->id));
    }

    public function test_a_learner_with_no_tenant_reads_and_joins_nothing(): void {
        $global = $this->challenge_of(0, 'gn');
        $nobody = $this->user_at('');
        $this->setUser($nobody);
        $this->assert_out_of_tenant(fn() => get_challenge::execute((int) $global->id),
            'No tenant, no challenges - not even global ones (list_challenges already returns none).');
        $this->assert_out_of_tenant(fn() => challenge_engine::join((int) $global->id, (int) $nobody->id),
            'No tenant, no joining.');
    }

    public function test_the_site_admin_reads_any_challenge(): void {
        $foreign = $this->challenge_of(177, 'sa');
        $this->setAdminUser();
        $this->assertSame((int) $foreign->id, get_challenge::execute((int) $foreign->id)['id']);
    }

    // ── the list's Edit / Delete buttons ─────────────────────────────────

    public function test_edit_and_delete_are_offered_only_on_manageable_rows(): void {
        $mine = $this->challenge_of(1, 'lm');
        $global = $this->challenge_of(0, 'lg');
        $this->setUser($this->tenant_admin_at('/1'));

        $rows = list_challenges::execute('', 'active', 'timecreated', 'desc', 0, 25)['rows'];
        $actions = array_column($rows, 'actions', 'id');
        $this->assertStringContainsString('delete-challenge', $actions[(int) $mine->id]);
        $this->assertStringNotContainsString('delete-challenge', $actions[(int) $global->id],
            'A scoped manager used to get a working Delete on every global challenge.');
    }
}
