<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_core\bizlms\admin_log_step;
use local_sentientia_core\bizlms\legacy_logs_importer;
use local_sentientia_core\bizlms\log_step;
use local_sentientia_core\bizlms\upload_error_step;
use local_sentientia_core\tests\bizlms\stub_org_importer;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\importer as framework_importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\importer_contract;
use local_sentientia_platform\phpunit\legacy_schema_fixture;

/**
 * The legacy_logs feature of the BizLMS import (ADR-032, mapping doc section 8): local_logs (the BizLMS admin audit
 * trail) and local_courseerrors (the bulk course upload error log) become local_sentientia_admin_log.
 *
 * The importer contract (tests/bizlms of local_sentientia_platform, "Test approach" 4) runs against a seed that has
 * every kind of row the feature can meet: a course create, update and delete, a delete by a deleted user, an actor
 * with a padded path, with no path, with a root that is not a tenant, who has no user row and who is 0, a row with
 * no modified time and no item, and upload errors with and without an uploader. The tests after it check the column
 * map, the tenant attribution, the owner's decisions, the reasons, verify, preflight, the dry run, that the legacy
 * tables are never written, and that a tenant admin reads only their own tenant's rows.
 *
 * The org feature is registered as a stub (tests/classes/bizlms/stub_org_importer.php): the real org importer is
 * another plugin's, and the registry refuses a tenant importer that does not depend on it.
 *
 * @package    local_sentientia_core
 * @category   test
 * @covers     \local_sentientia_core\bizlms\legacy_logs_importer
 * @covers     \local_sentientia_core\bizlms\log_step
 * @covers     \local_sentientia_core\bizlms\admin_log_step
 * @covers     \local_sentientia_core\bizlms\upload_error_step
 *
 * @group local_sentientia_core
 * @group bizlms_import
 */
final class bizlms_import_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use importer_contract;
    use \local_sentientia_org\test\bizlms_fixture;

    /** @var int Timestamp base of the seed. */
    private const T0 = 1600000000;

    /** @var int A user id that has no row in {user}. */
    private const GHOST = 987654;

    /** @var array<string, int> The seed's actors by name, filled by contract_seed(). */
    private array $uid = [];

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/fixtures/bizlms/courses.install.xml'];
    }

    protected function contract_importer(): framework_importer {
        return new legacy_logs_importer();
    }

    /**
     * The importer depends on org, which is not this plugin's, so the org feature is registered as a stub next to it.
     * A class method overrides the trait's, and every contract test calls it through $this.
     *
     * @return framework_importer
     */
    protected function contract_begin(): framework_importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([new stub_org_importer(), $importer]);
        return $importer;
    }

    /**
     * The owner's three choices, as the signed file records them (status accepted).
     *
     * @param array<string, mixed> $override Replaces a value, for the tests of a different choice.
     * @return decisions
     */
    protected function contract_decisions(array $override = []): decisions {
        return decisions::from_array($override + [
            'tenant.unresolved.legacy_logs' => 'pathless',
            'legacy_logs.retention' => 'keep_no_purge',
            'legacy_logs.description_erasure' => 'keep_row_scrub_name',
        ]);
    }

    protected function contract_user_columns(): array {
        return ['local_sentientia_admin_log' => ['userid', 'usermodified', 'description']];
    }

    /**
     * A user, with the open_path the test gives them.
     *
     * @param string|null $path
     * @param bool $deleted
     * @return int
     */
    private function make_user(?string $path, bool $deleted = false): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        if ($path !== null) {
            $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        }
        if ($deleted) {
            $DB->set_field('user', 'deleted', 1, ['id' => $user->id]);
        }
        return (int) $user->id;
    }

    /**
     * One local_logs row, with BizLMS's own defaults for what the caller leaves out.
     *
     * @param int $id
     * @param array<string, mixed> $values
     * @return void
     */
    private function put_log(int $id, array $values): void {
        global $DB;
        $DB->import_record('local_logs', (object) ($values + [
            'id' => $id, 'event' => 'insert', 'module' => 'course', 'description' => 'x', 'type' => null,
            'timecreated' => self::T0 + $id, 'timemodified' => self::T0 + $id, 'usercreated' => 0, 'usermodified' => 0,
        ]));
    }

    /**
     * One local_courseerrors row.
     *
     * @param int $id
     * @param array<string, mixed> $values
     * @return void
     */
    private function put_error(int $id, array $values): void {
        global $DB;
        $DB->import_record('local_courseerrors', (object) ($values + [
            'id' => $id, 'reason' => 'r', 'userid' => 0, 'time' => self::T0 + $id,
        ]));
    }

    /**
     * Twelve source rows: eight of local_logs and four of local_courseerrors.
     *
     *   local_logs
     *     1   course created by an Airpay user (path /1/5)
     *     2   course updated by the same user, modified later than created
     *     4   course deleted by a user whose path is padded (' 77/ ')
     *     5   forum delete (BizLMS reused the log) by a DELETED user whose path is /1
     *     9   course updated by a user with no path; no item, no modified time, no usermodified
     *     10  course created by an actor that has no user row
     *     11  course deleted by a user whose path root (999) is not a tenant
     *     12  a row with no actor at all (usercreated 0)
     *   local_courseerrors
     *     1   an upload error by the Airpay user
     *     2   no reason, no uploader, no time
     *     3   uploader 0
     *     7   an upload error by the padded-path user
     *
     * Tenants: /1/5 and /1 give /1/5 and /1, the padded path gives /77, and the other four log rows and two error
     * rows have no tenant.
     *
     * @return void
     */
    protected function contract_seed(): void {
        $this->ensure_bizlms_schema();
        $this->uid = [
            'airpay' => $this->make_user('/1/5'),
            'padded' => $this->make_user(' 77/ '),
            'nopath' => $this->make_user(null),
            'badroot' => $this->make_user('/999/1'),
            'deleted' => $this->make_user('/1', true),
            'ghost' => self::GHOST,
        ];
        $u = $this->uid;
        $t = self::T0;

        $this->put_log(1, ['event' => 'insert', 'type' => '101', 'usercreated' => $u['airpay'], 'usermodified' => $u['airpay'],
            'description' => 'User with Username "Asha"  created the course  "Safety 101"']);
        $this->put_log(2, ['event' => 'update', 'type' => '101', 'usercreated' => $u['airpay'], 'usermodified' => $u['airpay'],
            'timemodified' => $t + 25, 'description' => 'User with Username "Asha" has updated the course  "Safety 101"']);
        $this->put_log(4, ['event' => 'delete', 'type' => '102', 'usercreated' => $u['padded'], 'usermodified' => $u['padded'],
            'description' => 'User with Username "Ravi" has deleted the course with courseid  "102"']);
        $this->put_log(5, ['event' => 'delete', 'type' => '9', 'usercreated' => $u['deleted'], 'usermodified' => $u['deleted'],
            'description' => 'User with Username "Dev" has deleted the forum with forumid  "9"']);
        $this->put_log(9, ['event' => 'update', 'type' => null, 'usercreated' => $u['nopath'], 'usermodified' => null,
            'timemodified' => null, 'description' => 'User with Username "Meera" has updated the course  "No item"']);
        $this->put_log(10, ['event' => 'insert', 'type' => '103', 'usercreated' => $u['ghost'], 'usermodified' => $u['ghost'],
            'description' => 'User with Username "Ghost"  created the course  "Orphan actor"']);
        $this->put_log(11, ['event' => 'delete', 'type' => '104', 'usercreated' => $u['badroot'], 'usermodified' => $u['badroot'],
            'description' => 'User with Username "Zed" has deleted the course with courseid  "104"']);
        $this->put_log(12, ['event' => 'insert', 'type' => null, 'usercreated' => 0, 'usermodified' => 0,
            'description' => 'Nightly sync']);

        $this->put_error(1, ['reason' => 'Course shortname "SAF101" already exists', 'userid' => $u['airpay'], 'time' => $t + 200]);
        $this->put_error(2, ['reason' => null, 'userid' => null, 'time' => null]);
        $this->put_error(3, ['reason' => 'Category "Missing" not found', 'userid' => 0, 'time' => $t + 202]);
        $this->put_error(7, ['reason' => 'Invalid date', 'userid' => $u['padded'], 'time' => $t + 203]);
    }

    protected function contract_mutate_source(): void {
        $this->put_log(500, ['event' => 'insert', 'description' => 'Late arrival']);
    }

    /**
     * The row the importer made for a source row.
     *
     * @param string $source local_logs or local_courseerrors.
     * @param int $sourceid
     * @return \stdClass
     */
    private function target_of(string $source, int $sourceid): \stdClass {
        global $DB;
        $map = $DB->get_record(legacymap::TABLE, ['sourcetable' => $source, 'sourceid' => $sourceid, 'subkey' => ''],
            '*', MUST_EXIST);
        return $DB->get_record('local_sentientia_admin_log', ['id' => $map->targetid], '*', MUST_EXIST);
    }

    /**
     * One section of a step in a report.
     *
     * @param report $report
     * @param string $step Step key.
     * @param string $section warnings, skipped_by_reason, tenant_methods or counters.
     * @return array
     */
    private function step_section(report $report, string $step, string $section): array {
        return $report->to_array()['features']['legacy_logs']['steps'][$step][$section] ?? [];
    }

    // The column map.

    public function test_local_logs_columns_are_mapped_and_the_source_times_are_kept(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $this->assertSame(12, $DB->count_records('local_sentientia_admin_log'), 'every source row becomes one row');

        $created = $this->target_of('local_logs', 1);
        $this->assertSame('local_logs', $created->source);
        $this->assertSame('insert', $created->event);
        $this->assertSame('course', $created->module);
        $this->assertSame('User with Username "Asha"  created the course  "Safety 101"', $created->description,
            'the description is copied verbatim, double space and all');
        $this->assertSame('101', $created->itemref, 'local_logs.type is the course id');
        $this->assertSame($this->uid['airpay'], (int) $created->userid);
        $this->assertSame($this->uid['airpay'], (int) $created->usermodified);
        $this->assertSame(self::T0 + 1, (int) $created->timecreated);
        $this->assertSame(self::T0 + 1, (int) $created->timemodified);

        $updated = $this->target_of('local_logs', 2);
        $this->assertSame('update', $updated->event);
        $this->assertSame(self::T0 + 2, (int) $updated->timecreated);
        $this->assertSame(self::T0 + 25, (int) $updated->timemodified, 'a real modified time is kept, not replaced by the created time');

        $this->assertSame('delete', $this->target_of('local_logs', 4)->event);
        $this->assertSame('102', $this->target_of('local_logs', 4)->itemref);

        $noitem = $this->target_of('local_logs', 9);
        $this->assertNull($noitem->itemref, 'no course id: NULL, not an empty string');
        $this->assertSame(0, (int) $noitem->usermodified, 'a NULL usermodified becomes 0');
        $this->assertSame(self::T0 + 9, (int) $noitem->timemodified, 'no modified time: the created time');
    }

    public function test_upload_errors_become_rows_with_fixed_event_and_module(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);

        $error = $this->target_of('local_courseerrors', 1);
        $this->assertSame('local_courseerrors', $error->source);
        $this->assertSame('upload_error', $error->event);
        $this->assertSame('course', $error->module);
        $this->assertSame('Course shortname "SAF101" already exists', $error->description, 'the reason is the description');
        $this->assertNull($error->itemref);
        $this->assertSame($this->uid['airpay'], (int) $error->userid);
        $this->assertSame(0, (int) $error->usermodified, 'the error table has no modifier');
        $this->assertSame(self::T0 + 200, (int) $error->timecreated);
        $this->assertSame(self::T0 + 200, (int) $error->timemodified, 'the one time column feeds both');

        $empty = $this->target_of('local_courseerrors', 2);
        $this->assertSame('', $empty->description, 'a NULL reason is an empty description');
        $this->assertSame(0, (int) $empty->userid, 'a NULL uploader is 0');
        $this->assertSame(0, (int) $empty->timecreated, 'a NULL time stays 0; nothing invents one');
        $this->assertSame(0, (int) $empty->timemodified);
    }

    public function test_the_log_keeps_the_actor_columns_as_the_source_had_them(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        // An actor with no user row is kept as the id BizLMS recorded; the report page shows "unknown".
        $this->assertSame(self::GHOST, (int) $this->target_of('local_logs', 10)->userid);
        $this->assertSame(0, (int) $this->target_of('local_logs', 12)->userid);
        $this->assertSame($this->uid['deleted'], (int) $this->target_of('local_logs', 5)->userid, 'a deleted actor keeps the row');
    }

    // Tenant attribution.

    public function test_the_tenant_path_is_the_actors_open_path(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->contract_run(true);

        $this->assertSame('/1/5', $this->target_of('local_logs', 1)->actor_path);
        $this->assertSame('/1/5', $this->target_of('local_logs', 2)->actor_path);
        $this->assertSame('/77', $this->target_of('local_logs', 4)->actor_path, 'a padded path is stored normalised');
        $this->assertSame('/1', $this->target_of('local_logs', 5)->actor_path, 'a deleted actor still places the row');
        $this->assertSame('/1/5', $this->target_of('local_courseerrors', 1)->actor_path);
        $this->assertSame('/77', $this->target_of('local_courseerrors', 7)->actor_path);

        // No tenant can be worked out: imported with no path (the signed decision), visible to cross-tenant callers only.
        foreach ([['local_logs', 9], ['local_logs', 10], ['local_logs', 11], ['local_logs', 12],
                ['local_courseerrors', 2], ['local_courseerrors', 3]] as [$source, $id]) {
            $this->assertNull($this->target_of($source, $id)->actor_path, "{$source} {$id} has no tenant");
        }

        $this->assertEquals(['exact' => 3, 'normalised' => 1, 'unresolved' => 4],
            $this->step_section($report, 'legacy_logs.admin_log', 'tenant_methods'));
        $this->assertEquals(['exact' => 1, 'normalised' => 1, 'unresolved' => 2],
            $this->step_section($report, 'legacy_logs.upload_errors', 'tenant_methods'));
    }

    public function test_with_organisations_imported_a_path_resolves_to_the_nearest_one(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        // What the org importer leaves behind: the Airpay tenant and one department. Nothing at all for /77.
        foreach ([[1, 'Airpay', 'airpay', 0, '/1', 1], [5, 'Technology', 'airpay_tech', 1, '/1/5', 2]] as [$id, $name, $short, $parent, $path, $depth]) {
            $DB->import_record('local_sentientia_org', (object) ['id' => $id, 'fullname' => $name, 'shortname' => $short,
                'parentid' => $parent, 'path' => $path, 'depth' => $depth, 'visible' => 1, 'sortorder' => 0,
                'timecreated' => self::T0, 'timemodified' => self::T0]);
        }
        // A department below /1/5 that has no organisation of its own.
        $deep = $this->make_user('/1/5/12');
        $this->put_log(20, ['event' => 'update', 'type' => '105', 'usercreated' => $deep, 'usermodified' => $deep,
            'description' => 'User with Username "Kabir" has updated the course  "Deep"']);

        [$result, $report] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));

        $this->assertSame('/1/5', $this->target_of('local_logs', 1)->actor_path, 'an organisation at the actor\'s path');
        $this->assertSame('/1', $this->target_of('local_logs', 5)->actor_path);
        $this->assertSame('/1/5', $this->target_of('local_logs', 20)->actor_path, 'no organisation at /1/5/12: the nearest one above it');
        $this->assertNull($this->target_of('local_logs', 4)->actor_path, 'a tenant whose organisations are not imported yet has no path');
        $this->assertEquals(['exact' => 3, 'walked_up' => 1, 'unresolved' => 5],
            $this->step_section($report, 'legacy_logs.admin_log', 'tenant_methods'));
    }

    public function test_odd_rows_are_reported_not_hidden(): void {
        $this->contract_begin();
        $this->contract_seed();
        [, $report] = $this->contract_run(true);

        $logs = $this->step_section($report, 'legacy_logs.admin_log', 'warnings');
        $this->assertSame(1, $logs['derived_timestamp'] ?? 0, 'log 9 has no modified time');
        $this->assertSame(1, $logs['no_actor'] ?? 0, 'log 12 has no actor');
        $this->assertSame(1, $logs['actor_not_found'] ?? 0, 'log 10 names a user that has no row');

        $errors = $this->step_section($report, 'legacy_logs.upload_errors', 'warnings');
        $this->assertSame(2, $errors['no_actor'] ?? 0, 'error rows 2 and 3 have no uploader');
        $this->assertArrayNotHasKey('derived_timestamp', $errors, 'the error table has one time column, nothing is derived');
    }

    public function test_every_source_row_has_one_imported_map_row(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $this->assertSame(8, $DB->count_records(legacymap::TABLE,
            ['feature' => 'legacy_logs', 'sourcetable' => 'local_logs', 'subkey' => '', 'outcome' => 'imported']));
        $this->assertSame(4, $DB->count_records(legacymap::TABLE,
            ['feature' => 'legacy_logs', 'sourcetable' => 'local_courseerrors', 'subkey' => '', 'outcome' => 'imported']));
        $this->assertSame(12, $DB->count_records(legacymap::TABLE, ['feature' => 'legacy_logs']), 'no sub-rows, no other outcome');
    }

    // The owner's decisions and the reason.

    public function test_a_skip_decision_skips_the_rows_with_no_tenant_and_leaves_the_run_unproven(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $decisions = $this->contract_decisions(['tenant.unresolved.legacy_logs' => 'skip']);
        [$result, $report] = $this->contract_run(true, ['decisions' => $decisions]);

        $this->assertSame(2, $result['exit'], 'done, but rows wait for the owner');
        $this->assertSame(['legacy_logs:tenant_unresolved=6'], $result['unproven']);
        $this->assertSame('complete', $result['features']['legacy_logs']);

        $this->assertSame(6, $DB->count_records('local_sentientia_admin_log'));
        $this->assertFalse($DB->record_exists('local_sentientia_admin_log', ['actor_path' => null]), 'nothing is left with no tenant');
        $skipped = $DB->get_record(legacymap::TABLE, ['sourcetable' => 'local_logs', 'sourceid' => 11, 'subkey' => ''],
            '*', MUST_EXIST);
        $this->assertSame('skipped', $skipped->outcome);
        $this->assertSame('tenant_unresolved', $skipped->reason);
        $this->assertSame('no_tenant', $skipped->detail);
        $this->assertSame('', $skipped->targettable);
        $this->assertSame(4, $this->step_section($report, 'legacy_logs.admin_log', 'skipped_by_reason')['tenant_unresolved'] ?? 0);
        $this->assertSame(2, $this->step_section($report, 'legacy_logs.upload_errors', 'skipped_by_reason')['tenant_unresolved'] ?? 0);
        $this->assertSame(12, $DB->count_records(legacymap::TABLE, ['feature' => 'legacy_logs']), 'a skipped row still has its map row');
    }

    public function test_the_skip_reason_is_accepted_by_the_owner_and_then_the_run_exits_clean(): void {
        $this->contract_begin();
        $this->contract_seed();
        $decisions = $this->contract_decisions(['tenant.unresolved.legacy_logs' => 'skip',
            'accepted_reasons' => ['legacy_logs:tenant_unresolved']]);
        [$result] = $this->contract_run(true, ['decisions' => $decisions]);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
    }

    public function test_the_reason_vocabulary_is_one_retryable_code_that_needs_the_owner(): void {
        $reasons = $this->contract_importer()->reasons();
        $this->assertCount(1, $reasons);
        $this->assertSame('tenant_unresolved', $reasons[0]->code);
        $this->assertTrue($reasons[0]->retryable, 'a re-approved decision lets --retry-skipped take the rows');
        $this->assertTrue($reasons[0]->needsowner, 'skipping rows is never silent');
    }

    public function test_the_three_decisions_are_required_and_constrained(): void {
        $this->contract_begin();
        $this->contract_seed();

        [$result] = $this->contract_run(false, ['decisions' => decisions::none()]);
        $this->assertSame(1, $result['exit']);
        $blockers = implode(' ', $result['blockers']);
        $this->assertStringContainsString('missing_decision:tenant.unresolved.legacy_logs', $blockers);
        $this->assertStringContainsString('missing_decision:legacy_logs.retention', $blockers);
        $this->assertStringContainsString('missing_decision:legacy_logs.description_erasure', $blockers);

        foreach ([['tenant.unresolved.legacy_logs', 'global'], ['legacy_logs.retention', 'purge_after_a_year'],
                ['legacy_logs.description_erasure', 'delete_row']] as [$key, $value]) {
            [$result] = $this->contract_run(false, ['decisions' => $this->contract_decisions([$key => $value])]);
            $this->assertSame(1, $result['exit'], "{$key}={$value} is not a value the owner signed");
            $this->assertStringContainsString('decision_value_not_allowed:' . $key, implode(' ', $result['blockers']));
        }

        $unfinished = decisions::from_array([
            'tenant.unresolved.legacy_logs' => 'pathless',
            'legacy_logs.retention' => 'keep_no_purge',
            'legacy_logs.description_erasure' => 'keep_row_scrub_name',
        ], ['legacy_logs.description_erasure' => 'open']);
        [$result] = $this->contract_run(false, ['decisions' => $unfinished]);
        $this->assertSame(1, $result['exit'], 'a decision that is not accepted is not a decision');
        $this->assertStringContainsString('decision_not_accepted:legacy_logs.description_erasure', implode(' ', $result['blockers']));
    }

    public function test_the_signed_decisions_file_drives_the_importer(): void {
        $file = \core_component::get_component_directory('local_sentientia_platform')
            . '/tests/fixtures/bizlms/bizlms-import-decisions.copy.json';
        if (!is_readable($file)) {
            $this->markTestSkipped('the platform fixture copy of the decisions file is not here');
        }
        $this->contract_begin();
        $this->contract_seed();
        [$result] = $this->contract_run(true, ['decisions' => decisions::load($file)]);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $this->assertSame('complete', $result['features']['legacy_logs']);
    }

    // The legacy tables are the archive.

    public function test_the_legacy_tables_are_never_written(): void {
        $this->contract_begin();
        $this->contract_seed();
        $logs = fingerprint::table('local_logs');
        $errors = fingerprint::table('local_courseerrors');
        $this->contract_run(true);
        $this->contract_run(true);
        $this->assertSame($logs, fingerprint::table('local_logs'), 'count, max id, columns and CRC are unchanged');
        $this->assertSame($errors, fingerprint::table('local_courseerrors'));
    }

    // A dry run, preflight, registration.

    public function test_a_dry_run_counts_what_an_apply_would_do(): void {
        $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(false);
        $this->assertSame(0, $result['exit'], implode('; ', array_merge($result['blockers'], $result['unproven'])));
        $this->assertSame('simulated', $result['features']['legacy_logs']);

        $logs = $this->step_section($report, 'legacy_logs.admin_log', 'counters');
        $this->assertSame(8, $logs['processed']);
        $this->assertSame(8, $logs['imported']);
        $this->assertSame(0, $logs['skipped']);
        $errors = $this->step_section($report, 'legacy_logs.upload_errors', 'counters');
        $this->assertSame(4, $errors['processed']);
        $this->assertSame(4, $errors['imported']);
    }

    public function test_preflight_counts_the_rows_that_have_no_actor(): void {
        $this->contract_begin();
        $this->contract_seed();
        $runner = new runner(['decisions' => $this->contract_decisions(), 'report' => new report()]);
        $pf = $runner->preflight(['legacy_logs'])['preflights']['legacy_logs']->to_array();
        $this->assertSame([], $pf['blockers'], 'odd rows are reported, they are not blockers: the legacy table cannot be corrected');
        $this->assertSame(1, $pf['counts']['no_actor:local_logs']);
        $this->assertSame(2, $pf['counts']['no_actor:local_courseerrors'], 'a NULL uploader and a 0 uploader');
    }

    public function test_the_importer_is_registered_and_depends_on_org(): void {
        $this->contract_begin();
        $loaded = registry::load();
        $this->assertArrayHasKey('legacy_logs', $loaded);
        $this->assertSame(['org'], $loaded['legacy_logs']->depends(), 'tenant resolution reads what the org importer fills');
        $this->assertSame(['org', 'legacy_logs'], registry::sorted($loaded), 'org runs first');
        $this->assertSame('local_sentientia_core', $loaded['legacy_logs']->component());
        $this->assertSame(['local_sentientia_admin_log'], $loaded['legacy_logs']->target_tables());
        $this->assertSame(['local_sentientia_admin_log' => 'actor_path'], $loaded['legacy_logs']->tenant_columns());
        $this->assertSame([], $loaded['legacy_logs']->core_writes(), 'the feature writes no core table');
        $this->assertSame(['local_logs', 'local_courseerrors'], array_keys($loaded['legacy_logs']->sources()));

        $imports = [];
        include(__DIR__ . '/../db/bizlms_import.php');
        $this->assertSame(legacy_logs_importer::class, $imports['legacy_logs'], 'db/bizlms_import.php declares it');
    }

    public function test_the_target_is_not_a_framework_or_legacy_table(): void {
        // The registry refuses a target that starts with local_sentientia_legacy: the map's first name for this
        // table, local_sentientia_legacy_log, would have been refused, which is why it is called admin_log.
        $this->assertStringStartsNotWith('local_sentientia_legacy', legacy_logs_importer::TARGET);
        $this->contract_begin();
        $this->assertArrayHasKey('legacy_logs', registry::load());
    }

    // Verify.

    public function test_verify_passes_after_an_import_and_names_damage_to_it(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);

        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $this->assertSame(['exit' => 0, 'failures' => ['legacy_logs' => []]], $runner->verify(['legacy_logs']));

        $id = (int) $DB->get_field('local_sentientia_admin_log', 'id', ['source' => 'local_logs'], IGNORE_MULTIPLE);
        $DB->delete_records('local_sentientia_admin_log', ['id' => $id]);
        $failures = $runner->verify(['legacy_logs']);
        $this->assertSame(1, $failures['exit']);
        $this->assertContains('target_rows_differ:local_logs: imported=8 target=7', $failures['failures']['legacy_logs']);
    }

    public function test_verify_names_a_row_written_by_somebody_else(): void {
        global $DB;
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $DB->insert_record('local_sentientia_admin_log', (object) [
            'source' => 'local_elsewhere', 'event' => 'insert', 'module' => 'course', 'description' => 'x',
            'timecreated' => 1, 'timemodified' => 1,
        ]);
        $runner = new runner(['decisions' => $this->contract_decisions()]);
        $failures = $runner->verify(['legacy_logs']);
        $this->assertSame(1, $failures['exit']);
        $this->assertContains('unexpected_source_values: rows=1', $failures['failures']['legacy_logs']);
    }

    // The pure helpers of the steps.

    public function test_text_that_is_not_utf8_is_repaired_and_reported(): void {
        [$clean, $repaired] = log_step::clean_text("abc\xC3\x28 end");
        $this->assertTrue($repaired);
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'), 'the writer refuses anything else');
        $this->assertStringStartsWith('abc', $clean);
        $this->assertStringEndsWith(' end', $clean);

        $this->assertSame(['plain', false], log_step::clean_text('plain'));
        $this->assertSame(['', false], log_step::clean_text(null));
        $this->assertSame(['नमस्ते', false], log_step::clean_text('नमस्ते'), 'valid UTF-8 is never touched');
    }

    public function test_a_missing_modified_time_falls_back_to_the_created_time_only(): void {
        $this->assertSame([100, 200, false], log_step::timestamps(100, 200));
        $this->assertSame([100, 100, true], log_step::timestamps(100, 0));
        $this->assertSame([100, 100, true], log_step::timestamps('100', null));
        $this->assertSame([0, 0, true], log_step::timestamps(null, null), 'nothing invents a time');
    }

    public function test_the_steps_are_named_and_ordered(): void {
        $steps = $this->contract_importer()->steps();
        $this->assertSame(['legacy_logs.admin_log', 'legacy_logs.upload_errors'], array_map(fn($s) => $s->key(), $steps));
        $this->assertSame([admin_log_step::SOURCE, upload_error_step::SOURCE], array_map(fn($s) => $s->sourcetable(), $steps));
        foreach ($steps as $step) {
            $this->assertSame(idpolicy::MAP, $step->idpolicy(), 'nothing outside the table stores a legacy id');
            $this->assertSame(legacy_logs_importer::TARGET, $step->targettable());
        }
    }

    // Tenant isolation.

    /**
     * A tenant admin as UAT has them: a manager-archetype role at system context.
     *
     * @param string $path
     * @return \stdClass
     */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $user->id]);
        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
    }

    /**
     * @group tenant_isolation
     */
    public function test_a_tenant_admin_reads_only_their_tenants_imported_rows(): void {
        $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);

        $this->setUser($this->tenant_admin('/1'));
        $this->assertSame(4, admin_log::count(), 'logs 1, 2 and 5 and upload error 1: Airpay\'s own actors');
        $this->assertEquals(['local_courseerrors' => 1, 'local_logs' => 3],
            $this->counts_by_source(admin_log::page([], 0, 50)));

        $this->setUser($this->tenant_admin('/77'));
        $this->assertSame(2, admin_log::count(), 'log 4 and upload error 7');

        $this->setUser($this->tenant_admin('/177'));
        $this->assertSame(0, admin_log::count(), 'no row of this tenant');

        // The path boundary: /7 is not /77.
        $this->setUser($this->tenant_admin('/7'));
        $this->assertSame(0, admin_log::count(), '/7 must not match /77');
        $this->assertSame([], admin_log::page([], 0, 50));

        $this->setUser($this->tenant_admin(''));
        $this->assertSame(0, admin_log::count(), 'no tenant, no rows');
        $this->assertNull(admin_log::scope(), 'the page tells this caller they have no tenant');

        $this->setAdminUser();
        $this->assertSame(12, admin_log::count(), 'a site admin reads every tenant, and the rows with no tenant');
    }

    /**
     * @param \stdClass[] $rows
     * @return array<string, int>
     */
    private function counts_by_source(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            $out[$row->source] = ($out[$row->source] ?? 0) + 1;
        }
        ksort($out);
        return $out;
    }
}
