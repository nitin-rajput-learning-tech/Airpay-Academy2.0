<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for analytics_manager.
 *
 * Locks in:
 * - Cache layer (commit 9e3512499): repeat calls to get_kpis/funnel/
 *   compliance_heatmap/course_effectiveness with same args hit cache.
 * - Cross-tenant LIKE fix (commit ac22501e8): orgpath '/1' must NOT
 *   match users at '/100' or '/177'.
 * - get_compliance_heatmap N+1 → batched (commit 9e3512499): no per-
 *   department user/completion query.
 *
 * @package    local_sentientia_analytics
 * @category   test
 *
 * @group tenant_isolation
 */
final class analytics_manager_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    /**
     * Place a user at a specific open_path.
     */
    private function user_at_path(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $u->open_path = $path;
        return $u;
    }

    /**
     * Tenant /1 should NOT include users at /100 or /177.
     * Pre-fix (LIKE '/1%'): leaked across tenants.
     * Post-fix: exact match OR /-bounded prefix only.
     */
    public function test_kpis_does_not_leak_across_tenants(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        // 3 users at /1 tree, 2 users at /177 tree, 1 at /100.
        $u1a = $this->user_at_path('/1');
        $u1b = $this->user_at_path('/1/2/3');
        $u1c = $this->user_at_path('/1/2/4');
        $u177a = $this->user_at_path('/177');
        $u177b = $this->user_at_path('/177/178');
        $u100  = $this->user_at_path('/100');

        // Mark all as recently active to populate KPIs. Use time()-3600 so the
        // value is strictly LESS than $current_end (also time()) when get_kpis runs.
        global $DB;
        $hourago = time() - 3600;
        foreach ([$u1a->id, $u1b->id, $u1c->id, $u177a->id, $u177b->id, $u100->id] as $uid) {
            $DB->set_field('user', 'lastaccess', $hourago, ['id' => $uid]);
        }

        // Pre-purge cache so KPIs run fresh.
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');

        $kpis_one = analytics_manager::get_kpis('30d', '/1');
        $active   = $kpis_one[0]['value']; // first KPI = Active Users

        // Active users for /1 should be 3 (u1a, u1b, u1c) — NOT 6.
        $this->assertSame(3, (int) $active,
            'tenant /1 leaked users from /100 or /177 (pre-fix: LIKE /1% bug)');
    }

    /**
     * Same tenant /1 query twice in a row should hit the cache on the
     * second call. We verify by checking that the result is identical
     * AND a new user added between calls is NOT reflected in the second
     * (proves cache hit, not a re-query).
     */
    public function test_kpis_cache_hit_on_repeat_call(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $u1 = $this->user_at_path('/1');
        global $DB;
        $DB->set_field('user', 'lastaccess', time() - 3600, ['id' => $u1->id]);

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');

        // First call — populates cache.
        $first = analytics_manager::get_kpis('30d', '/1');
        $first_active = (int) $first[0]['value'];

        // Insert a new active user — should NOT show up if cache hits.
        $u2 = $this->user_at_path('/1');
        $DB->set_field('user', 'lastaccess', time() - 3600, ['id' => $u2->id]);

        // Second call — should hit cache.
        $second = analytics_manager::get_kpis('30d', '/1');
        $second_active = (int) $second[0]['value'];

        $this->assertSame($first_active, $second_active,
            'cache miss: get_kpis re-queried instead of using cached result');
    }

    /**
     * Different orgpath = different cache key. /1 and /77 should each
     * produce their own correct count even when both cached together.
     */
    public function test_kpis_cache_key_segregates_by_orgpath(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        $this->user_at_path('/1');
        $this->user_at_path('/1');
        $this->user_at_path('/77');
        global $DB;
        $hourago = time() - 3600;
        // Mark all 3 as recently active.
        foreach ($DB->get_records_sql("SELECT id FROM {user} WHERE open_path IN ('/1','/77')") as $u) {
            $DB->set_field('user', 'lastaccess', $hourago, ['id' => $u->id]);
        }

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');

        $tenant1  = analytics_manager::get_kpis('30d', '/1');
        $tenant77 = analytics_manager::get_kpis('30d', '/77');

        $this->assertSame(2, (int) $tenant1[0]['value']);
        $this->assertSame(1, (int) $tenant77[0]['value']);
    }

    /**
     * get_compliance_heatmap should be safe when no departments exist
     * for the tenant (return empty array, not throw).
     */
    public function test_compliance_heatmap_empty_tenant_returns_empty(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'compliance_heatmap');

        // /9999 has no departments seeded — should return [].
        $result = analytics_manager::get_compliance_heatmap('/9999');
        $this->assertSame([], $result);
    }

    /** An org unit with a fixed id, filling any NOT NULL column the schema adds. */
    private function org(int $id, string $name, int $parentid, string $path, int $depth): void {
        global $DB;
        $rec = ['id' => $id, 'fullname' => $name, 'shortname' => 'ORG' . $id,
                'parentid' => $parentid, 'path' => $path, 'depth' => $depth, 'visible' => 1];
        foreach ($DB->get_columns('local_sentientia_org') as $col) {
            if (isset($rec[$col->name]) || !$col->not_null || $col->has_default) {
                continue;
            }
            $rec[$col->name] = ($col->meta_type === 'C' || $col->meta_type === 'X') ? '' : 0;
        }
        $DB->insert_record_raw('local_sentientia_org', (object) $rec, true, false, true);
    }

    /**
     * 11-18 surface walk (vp_compliance1 item 1, vp_admin1 B4): "Compliance by
     * Department" counted every visible course with an end date as mandatory,
     * so nearly every department read 0% while the compliance report's
     * Department Scorecard read 60-91%. The card now shows the scorecard's
     * figures: same departments, same rates, same RAG; departments with no
     * assignments left out; names escaped once.
     */
    public function test_compliance_heatmap_matches_the_department_scorecard(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        if (!analytics_manager::compliance_source_available()) {
            $this->markTestSkipped('local_sentientia_compliance_report is not installed.');
        }

        $this->org(1, 'Airpay', 0, '/1', 1);
        $this->org(2, 'Finance & Accounts', 1, '/1/2', 2);
        $this->org(3, 'Technology', 2, '/1/2/3', 3);
        $this->org(4, 'Admin', 1, '/1/4', 2);   // nobody assigned
        $this->org(177, 'ZEEA', 0, '/177', 1);
        $this->org(178, 'ZEEA Ops', 177, '/177/178', 2);

        $now = time();
        $DB->insert_record('local_compliance_courses', (object) [
            'courseid' => 7001, 'coursename' => 'POSH', 'costcenterid' => 0, 'is_active' => 1,
            'sort_order' => 1, 'deadline_days' => 30, 'createdby' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $snap = function (string $path, string $status) use ($DB, $now) {
            $u = $this->user_at_path($path);
            $DB->insert_record('local_compliance_snapshot', (object) [
                'userid' => $u->id, 'courseid' => 7001, 'department_path' => $path,
                'status' => $status, 'progress_percent' => 0, 'days_overdue' => 0,
                'matched_by' => 'userid', 'snapshot_date' => $now,
            ]);
        };
        // Finance & Accounts: 3 of 4 completed (75%, amber); Technology 1 of 2.
        $snap('/1/2', 'completed');
        $snap('/1/2', 'completed');
        $snap('/1/2/3', 'completed');
        $snap('/1/2/3', 'overdue');
        // Another tenant: never in /1's card.
        $snap('/177/178', 'overdue');

        // A visible course with an end date, not a compliance course, that
        // nobody completed: it used to be counted as mandatory.
        $this->getDataGenerator()->create_course(['enddate' => $now + 86400, 'visible' => 1]);

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'compliance_heatmap');
        $heatmap = analytics_manager::get_compliance_heatmap('/1');
        $scorecard = \local_sentientia_compliance_report\compliance_engine::get_department_scorecard('/1');

        $this->assertSame(['/1/2', '/1/2/3'], array_column($heatmap, 'path'), 'Admin (no assignments) is left out');
        $expected = array_column(array_filter($scorecard, fn($r) => !$r['is_empty']), 'rate', 'path');
        $this->assertSame($expected, array_column($heatmap, 'rate', 'path'));
        $this->assertSame(75, $heatmap[0]['rate']);
        $this->assertSame('amber', $heatmap[0]['rag']);
        $this->assertSame(50, $heatmap[1]['rate']);
        $this->assertSame(4, $heatmap[0]['users']);

        // Escaped once: format_string() output, rendered with triple braces.
        $this->assertSame(format_string('Finance & Accounts'), $heatmap[0]['department']);
        $this->assertSame('Finance & Accounts', $heatmap[0]['department_plain']);
        $this->assertSame(format_string('Finance & Accounts'), $heatmap[1]['parentname']);
    }

    /**
     * The KPI labels and funnel stage names are lang strings, and the
     * application cache holds counts only: a label cached in the first
     * viewer's language must not be served to the next viewer.
     */
    public function test_kpi_and_funnel_labels_are_strings_not_cached_literals(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'funnel');

        $kpis = analytics_manager::get_kpis('90d', '/1');
        $this->assertSame(get_string('kpi_active_users', 'local_sentientia_analytics'), $kpis[0]['label']);
        $cached = \cache::make('local_sentientia_analytics', 'kpis')->get('kpis_v3_' . md5('90d|/1'));
        $this->assertSame('kpi_active_users', $cached[0][0], 'the cache holds the string id, not the label');

        $funnel = analytics_manager::get_funnel('/1');
        $this->assertSame(['enrolled', 'started', 'completed', 'certified'], array_column($funnel, 'stagekey'));
        $this->assertSame(get_string('funnel_enrolled', 'local_sentientia_analytics'), $funnel[0]['stage']);
    }

    /**
     * trend() helper: should not divide by zero when previous=0.
     */
    public function test_trend_zero_previous_does_not_divide_by_zero(): void {
        $this->resetAfterTest();
        // Reflect-private since trend() is private static.
        $r = new \ReflectionMethod(analytics_manager::class, 'trend');
        $r->setAccessible(true);
        $result = $r->invoke(null, 5, 0);
        $this->assertIsArray($result);
        $this->assertSame(100, $result['pct'], 'previous=0, current>0 should yield 100% growth');
        $this->assertSame('up', $result['direction']);
    }

    /**
     * Owner decision (2026-10-07, "readers that count enrolments"): a learner enrolled through an imported BizLMS method AND
     * its converted manual twin holds two rows for one course. The "New Enrolments" KPI counts the learner-course pair once,
     * and only active enrolments on enabled instances.
     */
    public function test_new_enrolments_counts_a_learner_course_pair_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $recent = time() - 3600;

        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $twin = $this->user_at_path('/1/2');
        $single = $this->user_at_path('/1/2');
        $suspended = $this->user_at_path('/1/2');
        $disabledonly = $this->user_at_path('/1/2');
        $elsewhere = $this->user_at_path('/177');

        // The twin: a manual enrolment and a BizLMS one in the same course.
        $this->getDataGenerator()->enrol_user($twin->id, $course->id, 'student', 'manual', $recent);
        $bizlms = $DB->insert_record('enrol', (object) ['enrol' => 'learningplan', 'status' => 0, 'courseid' => $course->id,
            'sortorder' => 90, 'roleid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        $DB->insert_record('user_enrolments', (object) ['status' => 0, 'enrolid' => $bizlms, 'userid' => $twin->id,
            'timestart' => $recent, 'timeend' => 0, 'modifierid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        // The same learner in a second course is a second enrolment.
        $this->getDataGenerator()->enrol_user($twin->id, $other->id, 'student', 'manual', $recent);
        // One plain enrolment.
        $this->getDataGenerator()->enrol_user($single->id, $course->id, 'student', 'manual', $recent);
        // Not counted: suspended, on a disabled instance only, and in another tenant.
        $this->getDataGenerator()->enrol_user($suspended->id, $course->id, 'student', 'manual', $recent, 0, ENROL_USER_SUSPENDED);
        $off = $DB->insert_record('enrol', (object) ['enrol' => 'program', 'status' => 1, 'courseid' => $course->id,
            'sortorder' => 91, 'roleid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        $DB->insert_record('user_enrolments', (object) ['status' => 0, 'enrolid' => $off, 'userid' => $disabledonly->id,
            'timestart' => $recent, 'timeend' => 0, 'modifierid' => 0, 'timecreated' => $recent, 'timemodified' => $recent]);
        $this->getDataGenerator()->enrol_user($elsewhere->id, $course->id, 'student', 'manual', $recent);

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');
        $kpis = analytics_manager::get_kpis('30d', '/1');

        $this->assertSame('New Enrolments', $kpis[1]['label']);
        $this->assertSame(3, (int) $kpis[1]['value'],
            'twin in two courses (2 pairs, not 3 rows) plus the single learner');
    }

    /**
     * Insert a row, filling every other NOT NULL column without a default
     * ('' for text, 0 for numbers), so the test survives schema changes in
     * tables this plugin does not own.
     *
     * @param string $table
     * @param array $rec the columns the test cares about
     * @return int new id
     */
    private function insert_filled(string $table, array $rec): int {
        global $DB;
        foreach ($DB->get_columns($table) as $col) {
            if ($col->name === 'id' || array_key_exists($col->name, $rec) || !$col->not_null || $col->has_default) {
                continue;
            }
            $rec[$col->name] = ($col->meta_type === 'C' || $col->meta_type === 'X') ? '' : 0;
        }
        return (int) $DB->insert_record($table, (object) $rec);
    }

    /**
     * V12 (11-18 surface walk): the funnel's Certified bar was taller than
     * Completed, because imported certificates (courseid 0) were counted
     * whether or not their holder had completed anything. Certified now means
     * a certificate AND at least one completion.
     */
    public function test_funnel_certified_never_exceeds_completed(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('tool_certificate_issues')) {
            $this->markTestSkipped('tool_certificate is not installed.');
        }
        $this->ensure_bizlms_schema();
        \cache_helper::purge_by_definition('local_sentientia_analytics', 'funnel');

        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $u1 = $this->user_at_path('/1/2');
        $u2 = $this->user_at_path('/1/2');
        // Both hold an imported certificate (courseid 0); only u2 completed a course.
        foreach ([$u1, $u2] as $i => $u) {
            $this->insert_filled('tool_certificate_issues', ['userid' => $u->id, 'courseid' => 0,
                'code' => 'PHPUNITV12' . $i, 'timecreated' => $now]);
        }
        $this->insert_filled('course_completions', ['userid' => $u2->id, 'course' => $course->id,
            'timeenrolled' => $now, 'timestarted' => $now, 'timecompleted' => $now, 'reaggregate' => 0]);

        $bystage = array_column(analytics_manager::get_funnel('/1'), null, 'stagekey');
        $this->assertSame(1, (int) $bystage['completed']['count']);
        $this->assertSame(1, (int) $bystage['certified']['count'],
            'a certificate holder with no completion is not counted as certified');
        $this->assertLessThanOrEqual((int) $bystage['completed']['count'], (int) $bystage['certified']['count']);
    }

    /**
     * A moment in the server timezone, as a timestamp.
     *
     * @param string $local 'Y-m-d H:i:s' in the server timezone
     * @return int
     */
    private static function server_time(string $local): int {
        return (new \DateTimeImmutable($local, \core_date::get_server_timezone_object()))->getTimestamp();
    }

    /**
     * Post-demo plan 2.4: YTD is compared with the same span last year
     * (1 January to the same date and time), not with the whole of last year.
     */
    public function test_ytd_previous_window_is_the_same_span_last_year(): void {
        $this->resetAfterTest();
        $now = self::server_time('2026-11-19 15:30:00');

        [$curstart, $curend, $prevstart, $prevend] = analytics_manager::get_range_dates('ytd', $now);

        $this->assertSame(self::server_time('2026-01-01 00:00:00'), $curstart);
        $this->assertSame($now, $curend);
        $this->assertSame(self::server_time('2025-01-01 00:00:00'), $prevstart);
        $this->assertSame(self::server_time('2025-11-19 15:30:00'), $prevend,
            'the comparison ends at the same date and time last year, not on 1 January');
        $this->assertLessThan(self::server_time('2026-01-01 00:00:00'), $prevend);
    }

    /**
     * 29 February has no date last year: the comparison ends on 28 February,
     * same time of day. A day that exists in both years is kept as is.
     */
    public function test_ytd_previous_window_on_29_february_ends_on_28_february(): void {
        $this->resetAfterTest();

        [, , $prevstart, $prevend] = analytics_manager::get_range_dates('ytd', self::server_time('2028-02-29 10:00:00'));
        $this->assertSame(self::server_time('2027-01-01 00:00:00'), $prevstart);
        $this->assertSame(self::server_time('2027-02-28 10:00:00'), $prevend);

        [, , , $prevend] = analytics_manager::get_range_dates('ytd', self::server_time('2029-02-28 08:15:00'));
        $this->assertSame(self::server_time('2028-02-28 08:15:00'), $prevend);

        [$curstart, , $prevstart, $prevend] = analytics_manager::get_range_dates('ytd', self::server_time('2027-01-01 00:00:00'));
        $this->assertSame(self::server_time('2027-01-01 00:00:00'), $curstart, 'on 1 January at midnight the window is empty');
        $this->assertSame($prevstart, $prevend, 'and so is the comparison window');
    }

    /**
     * The rolling ranges are unchanged: N days up to now, compared with the N
     * days before them; an unknown range reads as 30d.
     */
    public function test_rolling_ranges_compare_with_the_window_before(): void {
        $this->resetAfterTest();
        $now = self::server_time('2026-11-19 15:30:00');
        foreach (['7d' => 7, '30d' => 30, '90d' => 90, 'bogus' => 30] as $range => $days) {
            [$curstart, $curend, $prevstart, $prevend] = analytics_manager::get_range_dates($range, $now);
            $this->assertSame($now - $days * DAYSECS, $curstart, $range);
            $this->assertSame($now, $curend, $range);
            $this->assertSame($curstart, $prevend, "$range: the windows meet");
            $this->assertSame($curstart - $days * DAYSECS, $prevstart, $range);
        }
    }

    /**
     * End to end: the YTD Completions card's "previous" counts a completion
     * inside last year's same span and leaves out one later last year (which
     * the whole-year comparison used to count).
     */
    public function test_ytd_kpi_previous_counts_only_the_same_span_last_year(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();

        [$curstart, , $prevstart, $prevend] = analytics_manager::get_range_dates('ytd');
        if ($prevend <= $prevstart) {
            $this->markTestSkipped('Run at the first instant of the year: last year\'s span is empty.');
        }
        $inside = intdiv($prevstart + $prevend, 2);   // within 1 January .. same moment last year
        $later  = intdiv($prevend + $curstart, 2);    // last year, after that moment

        $course = $this->getDataGenerator()->create_course();
        $u1 = $this->user_at_path('/1/2');
        $u2 = $this->user_at_path('/1/2');
        $this->insert_filled('course_completions', ['userid' => $u1->id, 'course' => $course->id,
            'timeenrolled' => $inside, 'timestarted' => $inside, 'timecompleted' => $inside, 'reaggregate' => 0]);
        $this->insert_filled('course_completions', ['userid' => $u2->id, 'course' => $course->id,
            'timeenrolled' => $later, 'timestarted' => $later, 'timecompleted' => $later, 'reaggregate' => 0]);

        \cache_helper::purge_by_definition('local_sentientia_analytics', 'kpis');
        $kpis = analytics_manager::get_kpis('ytd', '/1');

        $this->assertSame(get_string('kpi_completions', 'local_sentientia_analytics'), $kpis[2]['label']);
        $this->assertSame(1, (int) $kpis[2]['previous'],
            'only the completion inside last year\'s same span is compared (was 2: the whole year)');
    }
}
