<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the admin dashboard's tenant scoping.
 *
 * @covers \theme_sentientia\local\tenant_scope
 *
 * This logic used to be a closure declared inline in a 1,185-line layout file,
 * where roughly thirty widget queries depend on it: the user tiles, the course
 * counts, the completion rates, the activity feed. If it is wrong, every number
 * an L&D admin sees is wrong and nothing errors.
 *
 * The closure's boundary was already correct - '/'-terminated with an exact-root
 * companion, unlike the fourteen sites the 2026-09-22 path sweep had to fix -
 * but it had no tests, and its FALLBACK was wrong: an L&D admin whose open_path
 * was null, empty or '/' fell through to the unscoped branch and saw the whole
 * site. See tenant_scope's class docblock.
 *
 * @package    theme_sentientia
 * @category   test
 *
 * @group tenant_isolation
 */
final class tenant_scope_test extends \advanced_testcase {

    /**
     * A user object carrying the given open_path.
     *
     * @param string|null $path
     * @return \stdClass
     */
    private function user_with(?string $path): \stdClass {
        $u = new \stdClass();
        $u->id = 42;
        if ($path !== null) {
            $u->open_path = $path;
        }
        return $u;
    }

    // ── the boundary ─────────────────────────────────────────────────────

    public function test_a_tenant_root_is_read_from_the_open_path(): void {
        foreach (['/1' => '/1',
                  '/1/2' => '/1',
                  '/1/183/45' => '/1',
                  '/77' => '/77',
                  '/177' => '/177',
                  '/177/178/9' => '/177',
                  '1/2' => '/1'] as $openpath => $expected) {
            $scope = tenant_scope::for_user($this->user_with($openpath), false);
            $this->assertSame($expected, $scope->root(),
                "open_path {$openpath} must resolve to {$expected}");
        }
    }

    public function test_the_fragment_matches_the_root_and_its_descendants_only(): void {
        $scope = tenant_scope::for_user($this->user_with('/1'), false);
        [$sql, $params] = $scope->fragment('u', 'tu');

        $this->assertStringStartsWith(' AND ', $sql);
        $this->assertStringContainsString('u.open_path', $sql);

        // The exact root must be matched as well as its children. A prefix
        // alone would miss every user sitting at the tenant root itself.
        $this->assertStringContainsString(':tuexact', $sql);
        $this->assertStringContainsString(':tuprefix', $sql);
        $this->assertSame('/1', $params['tuexact']);
        $this->assertSame('/1/%', $params['tuprefix'],
            "the prefix must be '/'-terminated: '/1%' also matches /177");
    }

    public function test_two_fragments_in_one_query_do_not_collide(): void {
        $scope = tenant_scope::for_user($this->user_with('/1'), false);
        [, $a] = $scope->fragment('', 'tu');
        [, $b] = $scope->fragment('u', 'ju');

        $this->assertSame([], array_intersect(array_keys($a), array_keys($b)),
            'the dashboard puts tu/ju/tc/jc fragments in the same query; '
            . 'shared parameter names would silently overwrite each other');
    }

    public function test_an_unaliased_fragment_names_the_bare_column(): void {
        $scope = tenant_scope::for_user($this->user_with('/1'), false);
        [$sql] = $scope->fragment('', 'tu');

        $this->assertStringContainsString('open_path =', $sql);
        $this->assertStringNotContainsString('.open_path', $sql);
    }

    // ── the defect: failing open ─────────────────────────────────────────

    public function test_an_unresolvable_path_fails_closed(): void {
        foreach ([null, '', '/', '//', '/abc', 'nonsense'] as $bad) {
            $scope = tenant_scope::for_user($this->user_with($bad), false);

            $this->assertTrue($scope->is_unresolved(),
                var_export($bad, true) . ' must not resolve to a tenant');

            [$sql, $params] = $scope->fragment('u', 'tu');
            $this->assertSame(' AND 1 = 0', $sql,
                'the closure returned NO FILTER here, so an L&D admin with a '
                . 'broken open_path saw every tenant. Matching nothing is the '
                . 'safe reading of "we do not know who this is".');
            $this->assertSame([], $params);
        }
    }

    public function test_a_null_user_fails_closed(): void {
        $scope = tenant_scope::for_user(null, false);
        $this->assertTrue($scope->is_unresolved());
        $this->assertSame(' AND 1 = 0', $scope->fragment('u', 'tu')[0]);
    }

    // ── the unrestricted viewer ──────────────────────────────────────────

    public function test_an_unrestricted_viewer_gets_no_filter(): void {
        $scope = tenant_scope::for_user($this->user_with('/1'), true);

        $this->assertTrue($scope->is_unrestricted());
        $this->assertFalse($scope->is_unresolved());
        $this->assertSame(['', []], $scope->fragment('u', 'tu'),
            'a site admin sees every tenant, so the fragment must be empty '
            . 'rather than a filter that happens to match everything');
    }

    public function test_an_unrestricted_viewer_with_no_path_is_still_unrestricted(): void {
        // The site admin on the production import has a null open_path. That
        // must not be mistaken for "unresolvable".
        $scope = tenant_scope::for_user($this->user_with(null), true);

        $this->assertTrue($scope->is_unrestricted());
        $this->assertFalse($scope->is_unresolved());
        $this->assertSame(['', []], $scope->fragment('', 'tu'));
    }

    // ── against the database ─────────────────────────────────────────────

    public function test_the_emitted_sql_selects_the_right_users(): void {
        $this->resetAfterTest();
        global $DB;

        $this->ensure_open_path_column();

        $labels = [];
        foreach (['/1' => 'root', '/1/2' => 'child', '/1/2/3' => 'grandchild',
                  '/1/20' => 'sibling_digit', '/10' => 'other_ten',
                  '/177' => 'zeea', '/1x' => 'not_a_path'] as $path => $label) {
            $u = $this->getDataGenerator()->create_user();
            $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
            $labels[(int) $u->id] = $label;
        }

        $scope = tenant_scope::for_user($this->user_with('/1'), false);
        [$sql, $params] = $scope->fragment('u', 'tu');

        $rows = $DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE u.deleted = 0 {$sql}", $params);

        $found = [];
        foreach ($rows as $r) {
            if (isset($labels[(int) $r->id])) {
                $found[] = $labels[(int) $r->id];
            }
        }
        sort($found);

        $this->assertSame(['child', 'grandchild', 'root'], $found,
            "scope '/1' must include itself and its descendants and nothing "
            . "else. '/1/20', '/10', '/177' and '/1x' all start with the same "
            . 'characters and are all different tenants or departments.');
    }

    public function test_a_failed_closed_scope_selects_nobody(): void {
        $this->resetAfterTest();
        global $DB;

        $this->ensure_open_path_column();
        $this->getDataGenerator()->create_user();

        $scope = tenant_scope::for_user($this->user_with(null), false);
        [$sql, $params] = $scope->fragment('u', 'tu');

        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user} u WHERE u.deleted = 0 {$sql}", $params),
            'failing closed must actually return nothing, not merely look safe');
    }

    /**
     * Add {user}.open_path when this site does not have the BizLMS columns.
     *
     * @return void
     */
    private function ensure_open_path_column(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_path', XMLDB_TYPE_CHAR, '255', null,
            null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }
}
