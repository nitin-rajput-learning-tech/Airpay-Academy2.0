<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Upgrade helpers for local_sentientia_courses.
 *
 * These live outside upgrade.php so PHPUnit can call them directly
 * (tests/featured_rehome_test.php). A test cannot re-run an upgrade step.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The tenant a course belongs to, from the first segment of course.open_path.
 *
 * 0 when the course has no path (a legacy course that every tenant lists) or
 * when the first segment is not a known tenant (tenant::assert_valid()).
 *
 * @param string|null $openpath course.open_path
 * @return int tenant root, or 0
 */
function local_sentientia_courses_course_tenant_root(?string $openpath): int {
    $parts = explode('/', trim((string) $openpath, '/'));
    if (!isset($parts[0]) || !ctype_digit($parts[0]) || (int) $parts[0] <= 0) {
        return 0;
    }
    $root = (int) $parts[0];
    try {
        \local_sentientia_platform\tenant::assert_valid($root);
    } catch (\Throwable $e) {
        return 0;
    }
    return $root;
}

/**
 * ADR-031 follow-up: move tenant admins' "All tenants" featured rows onto
 * their course's own tenant list.
 *
 * Why this runs at deploy: until 2026-09-25, featured.php offered anyone who
 * was not a site admin only "All tenants" (costcenterid 0), and add_featured
 * stored it unchecked. So every course a tenant admin pinned is a
 * costcenterid 0 row. ADR-031 confines a tenant curator to their own list,
 * so they can no longer see, remove or reorder those rows. But
 * get_widget_for_user() still shows costcenterid 0 rows to every tenant's
 * learners. The result: an Airpay course pinned by an Airpay curator appeared
 * on ZEEA's and Public's dashboards, and the curator could not take it off.
 *
 * For each costcenterid 0 row:
 *   - rehomed: the course's open_path names tenant N (its first segment,
 *     checked by tenant::assert_valid()) and the course is not actively
 *     shared to another tenant. The row becomes costcenterid N. Tenant N's
 *     learners see nothing different, tenant N's curator can manage the row
 *     again, and other tenants stop seeing a course they do not own;
 *   - kept global (for review): the course has no open_path (legacy), its
 *     path names no known tenant, the course is actively shared (sharing makes
 *     it available to other tenants on purpose), tenant N's list already pins
 *     it (moving the row would duplicate the pair), or the course row is gone.
 *
 * The featured table records no creator, so a site admin's deliberate "All
 * tenants" pin of a tenant-owned, unshared course is rehomed too. The
 * config log entry says how to restore it: pin it again under "All tenants"
 * on featured.php.
 *
 * Idempotent: a second run finds only the kept rows and changes nothing.
 * Rows are re-tagged, never deleted.
 *
 * @return array{rehomed: stdClass[], kept: stdClass[]} each row carries id, courseid, root, reason
 */
function local_sentientia_courses_rehome_global_featured(): array {
    global $DB;
    $out = ['rehomed' => [], 'kept' => []];
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_sentientia_featured_courses')) {
        return $out;
    }
    $haspath = isset($DB->get_columns('course')['open_path']);
    $hasshare = $dbman->table_exists('local_sentientia_courses_tenant_share');
    $pathcol = $haspath ? 'c.open_path' : 'NULL';
    $rows = $DB->get_records_sql(
        "SELECT f.id, f.courseid, c.id AS realcourseid, {$pathcol} AS open_path
           FROM {local_sentientia_featured_courses} f
      LEFT JOIN {course} c ON c.id = f.courseid
          WHERE f.costcenterid = 0
       ORDER BY f.id ASC");

    foreach ($rows as $row) {
        $entry = (object) ['id' => (int) $row->id, 'courseid' => (int) $row->courseid,
            'root' => 0, 'reason' => ''];
        if (empty($row->realcourseid)) {
            $entry->reason = 'course no longer exists';
            $out['kept'][] = $entry;
            continue;
        }
        $path = trim((string) ($row->open_path ?? ''), '/');
        if ($path === '') {
            $entry->reason = 'legacy course with no open_path (every tenant lists it)';
            $out['kept'][] = $entry;
            continue;
        }
        $root = local_sentientia_courses_course_tenant_root($path);
        if ($root <= 0) {
            $entry->reason = 'open_path names no known tenant';
            $out['kept'][] = $entry;
            continue;
        }
        $entry->root = $root;
        if ($hasshare && $DB->record_exists('local_sentientia_courses_tenant_share',
                ['courseid' => (int) $row->courseid, 'status' => 'active'])) {
            $entry->reason = 'course is shared to other tenants';
            $out['kept'][] = $entry;
            continue;
        }
        if ($DB->record_exists('local_sentientia_featured_courses',
                ['courseid' => (int) $row->courseid, 'costcenterid' => $root])) {
            $entry->reason = "tenant {$root}'s list already pins this course";
            $out['kept'][] = $entry;
            continue;
        }
        $DB->set_field('local_sentientia_featured_courses', 'costcenterid', $root,
            ['id' => (int) $row->id]);
        $entry->reason = "course belongs to tenant {$root}";
        $out['rehomed'][] = $entry;
    }
    return $out;
}

/**
 * The 2026092501 step: rehome the featured rows, and record what was done in
 * the config changes log (Site administration > Reports > Config changes,
 * plugin local_sentientia_courses). There is one entry per row
 * ('adr031_featured_rehomed' or 'adr031_featured_review') and one
 * 'adr031_featured_audit' summary, so the record outlives the upgrade
 * output. Ids and tenant roots only: no names.
 *
 * @return string[] the lines for the upgrade step to mtrace()
 */
function local_sentientia_courses_run_featured_rehome(): array {
    $plugin = 'local_sentientia_courses';
    $result = local_sentientia_courses_rehome_global_featured();
    $lines = [];
    foreach ($result['rehomed'] as $r) {
        $lines[] = sprintf('REHOMED featured row id=%d courseid=%d: costcenterid 0 -> %d (%s).',
            $r->id, $r->courseid, $r->root, $r->reason);
        add_to_config_log('adr031_featured_rehomed', 'costcenterid=0',
            sprintf('costcenterid=%d: featured row id=%d courseid=%d (%s). To show it to every '
                . 'tenant again, pin it under "All tenants" on featured.php as a cross-tenant admin.',
                $r->root, $r->id, $r->courseid, $r->reason),
            $plugin);
    }
    foreach ($result['kept'] as $r) {
        $lines[] = sprintf('REVIEW (left on All tenants) featured row id=%d courseid=%d: %s.',
            $r->id, $r->courseid, $r->reason);
        add_to_config_log('adr031_featured_review', null,
            sprintf('left on All tenants: featured row id=%d courseid=%d (%s). Every tenant\'s '
                . 'learners see it; only a cross-tenant admin can change it.',
                $r->id, $r->courseid, $r->reason),
            $plugin);
    }
    $ids = fn(array $rows): string => implode(',', array_map(fn($r) => (int) $r->id, $rows)) ?: '-';
    $summary = sprintf('%d featured row(s) rehomed to their course\'s tenant [ids: %s]; '
        . '%d left on All tenants for review [ids: %s].',
        count($result['rehomed']), $ids($result['rehomed']), count($result['kept']), $ids($result['kept']));
    add_to_config_log('adr031_featured_audit', null, $summary, $plugin);
    $lines[] = $summary;
    return $lines;
}
