<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\legacy_reader;

/**
 * What the cohort_scope importer needs to know about a cohort that local_groups does not say:
 * where the cohort lives (its context), when the core row was made and changed, and which
 * organisation the course category of that context belongs to (local_costcenter.category).
 *
 * Everything is read through the import context's bounded reader and loaded once, by keyset
 * paging, into integer-keyed arrays, so a step never runs a per-row SELECT (ADR-032, "Reading
 * and performance"). Nothing here calls the context or category APIs: those can create rows
 * (context paths) and the import must write nothing but its own target.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cohort_context {

    /** @var array<int, array{contextid: int, timecreated: int, timemodified: int}>|null Cohort id => core row facts. */
    private ?array $cohorts = null;

    /** @var array<int, array{0: int, 1: int}> Context id => [context level, instance id]. */
    private array $contexts = [];

    /** @var array<int, string> Course category id => its path (/3/7). */
    private array $categories = [];

    /** @var array<int, string> Course category id => path of the local_costcenter row that owns it. */
    private array $orgbycategory = [];

    /**
     * The core cohort row's facts.
     *
     * @param context $ctx
     * @param int $cohortid
     * @return array{contextid: int, timecreated: int, timemodified: int}|null Null when no such cohort exists.
     */
    public function cohort(context $ctx, int $cohortid): ?array {
        $this->load($ctx);
        return $this->cohorts[$cohortid] ?? null;
    }

    /**
     * The BizLMS organisation path a cohort's context points at: its course category, or the nearest
     * ancestor category, that a local_costcenter row owns. A cohort in the system context has none.
     *
     * The path is the legacy value as stored; the tenant resolver normalises and validates it.
     *
     * @param context $ctx
     * @param int $cohortid
     * @return string|null
     */
    public function org_path(context $ctx, int $cohortid): ?string {
        $this->load($ctx);
        $info = $this->cohorts[$cohortid] ?? null;
        if ($info === null) {
            return null;
        }
        [$level, $instance] = $this->contexts[$info['contextid']] ?? [0, 0];
        if ($level !== CONTEXT_COURSECAT || $instance <= 0) {
            return null;
        }
        // Deepest category first: the category itself, then its parents up to the top.
        $chain = [$instance];
        $path = $this->categories[$instance] ?? '';
        if ($path !== '') {
            $ids = array_values(array_filter(array_map('intval', explode('/', $path))));
            if ($ids) {
                $chain = array_reverse($ids);
            }
        }
        foreach ($chain as $categoryid) {
            if (isset($this->orgbycategory[$categoryid])) {
                return $this->orgbycategory[$categoryid];
            }
        }
        return null;
    }

    /**
     * Load the cohort, context, category and organisation facts, once.
     *
     * @param context $ctx
     * @return void
     */
    private function load(context $ctx): void {
        if ($this->cohorts !== null) {
            return;
        }
        $reader = $ctx->legacy;
        $this->cohorts = [];

        $after = 0;
        do {
            $page = $reader->page('cohort', $after, legacy_reader::MAX_PAGE, ['contextid', 'timecreated', 'timemodified']);
            foreach ($page as $id => $row) {
                $this->cohorts[$id] = [
                    'contextid' => (int) $row->contextid,
                    'timecreated' => (int) $row->timecreated,
                    'timemodified' => (int) $row->timemodified,
                ];
                $after = $id;
            }
        } while (count($page) === legacy_reader::MAX_PAGE);

        $contextids = array_values(array_unique(array_column($this->cohorts, 'contextid')));
        $categoryids = [];
        foreach ($reader->fetch('context', $contextids, ['contextlevel', 'instanceid']) as $id => $row) {
            $this->contexts[$id] = [(int) $row->contextlevel, (int) $row->instanceid];
            if ((int) $row->contextlevel === CONTEXT_COURSECAT) {
                $categoryids[] = (int) $row->instanceid;
            }
        }
        foreach ($reader->fetch('course_categories', $categoryids, ['path']) as $id => $row) {
            $this->categories[$id] = (string) $row->path;
        }

        // BizLMS gave every organisation a course category; the link is local_costcenter.category. The table
        // belongs to the org feature and is only read here.
        if ($reader->exists('local_costcenter') && $reader->has_column('local_costcenter', 'category')) {
            $after = 0;
            do {
                $page = $reader->page('local_costcenter', $after, legacy_reader::MAX_PAGE, ['category', 'path']);
                foreach ($page as $id => $row) {
                    $category = (int) $row->category;
                    // The lowest organisation id keeps a category two rows claim.
                    if ($category > 0 && !isset($this->orgbycategory[$category]) && trim((string) $row->path) !== '') {
                        $this->orgbycategory[$category] = (string) $row->path;
                    }
                    $after = $id;
                }
            } while (count($page) === legacy_reader::MAX_PAGE);
        }
    }
}
