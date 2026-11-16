<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\local;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\context_aware_provider;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\contextlist_base;
use core_privacy\local\request\contextlist_collection;
use core_privacy\local\request\core_user_data_provider;
use core_privacy\local\request\helper;

/**
 * Recovers what stock tool_dataprivacy erasure (and export) misses because of two core defects, with no core
 * edit (ADR-035 "Option A", approved by Nitin 2026-11-13; ADR-033 rules out core edits): the contexts the stock
 * approval drops, and the course completions core never discovers. The erasure is then complete as ADR-035
 * defines it (what stock providers would delete without the two defects). It does not make "Deleted" literally
 * true: rows in contexts that no longer exist, relateduserid/realuserid log rows, subjects without a user row,
 * the user row itself and the user-list path stay (ADR-035 "What still remains").
 *
 * THE TWO DEFECTS (evidence: moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md and
 * DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md; Moodle 5.1.3+, byte-identical in 5.3):
 *
 * 1. Truncation (core bug). contextlist_base::set_contextids() is array_unique() without a reindex
 *    (privacy/classes/local/request/contextlist_base.php:66) and valid() is isset() on the iterator position
 *    (:170). tool_dataprivacy's api::get_approved_contextlist_collection_for_collection() walks each list with
 *    foreach (api.php ~1203-1235), so every context after the first key hole is never approved and never
 *    deleted (or exported). Only the LAST add_from_sql of a list can leave a hole (array_merge renumbers first,
 *    contextlist.php:80). Sources: logstore_standard (one row per log entry, no DISTINCT), report_stats, core_course,
 *    and for every mod_* the manager's own shared activity-completion add (manager.php:237-248 ->
 *    helper::add_shared_contexts_to_contextlist_for), which is where the mod_quiz and mod_scorm holes come from.
 *    The user-expiry path (expired_contexts_manager.php:517-528) passes the raw holey lists on as well.
 * 2. Completion gap (core gap). core_course discovers course contexts only through an INNER JOIN on
 *    course_completion_criteria, so course_completions rows in courses without criteria, or with criteria the
 *    user never touched, are never found and never erased. core_completion has no list of its own (it is a
 *    subsystem\plugin_provider); core_course's delete calls delete_completion($user, $courseid).
 *
 * THE REPAIR. local_sentientia_privacy's provider adds the dropped contexts to its OWN contextlist (one
 * add_from_sql, so it has no hole itself). The stock approval then judges those contexts like any other: on
 * the request path with the same per-context protection filter (api.php:1213-1222). At delete time this class
 * hands each dropped context back to the component that owns it, in a dense approved_contextlist, and for
 * mod_* also runs the manager's shared completion cleanup, exactly as manager.php:411-419 would have done.
 * It never deletes anything a bug-free core would not delete for the same subject.
 *
 * This class is FLAG-INDEPENDENT. Only \local_sentientia_privacy\privacy\provider reads the two feature
 * flags, so a CLI dry run can call plan() without flipping anything.
 *
 * Rules kept throughout:
 *   - every id array handed to new approved_contextlist() goes through dense() (array_intersect / array_diff
 *     keep keys and would recreate the very holes this repairs);
 *   - every component call sits in its own try/catch and the loop always finishes; failures are collected
 *     and returned (component, phase, exception class: never a message, never an id);
 *   - plan() never throws and uses only the int userid (229 A4 subjects have no user row);
 *   - nothing here instantiates \core_privacy\manager or a tool_dataprivacy manager_observer (that would
 *     recurse into the provider, or message the DPOs behind the caller's own observer).
 *
 * @package    local_sentientia_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class erasure_completeness {

    /** The host component, skipped in every component loop. */
    public const SELF = 'local_sentientia_privacy';

    /** The component runs before local_sentientia_privacy in the privacy manager's order. */
    public const BEFORE = 'before';

    /** The component runs after local_sentientia_privacy in the privacy manager's order. */
    public const AFTER = 'after';

    /** Erasure reached through a tool_dataprivacy DELETE request (process_data_request_task). */
    public const PATH_REQUEST = 'request';

    /** Erasure reached through the user-expiry path (expired_contexts_manager). */
    public const PATH_EXPIRY = 'expiry';

    /** Erasure driven by a CLI tool (residue sweep, rehearsal). */
    public const PATH_CLI = 'cli';

    /** Failure phases. */
    public const PHASE_DISCOVERY = 'discovery';
    /** A component's own delete_data_for_user() failed. */
    public const PHASE_DELETE = 'delete';
    /** The manager-equivalent shared completion cleanup (helper::delete_data_for_user) failed. */
    public const PHASE_DELETE_SHARED = 'delete_shared';
    /** A component's export failed. */
    public const PHASE_EXPORT = 'export';
    /** The data registry protection check failed for a context (the context is then kept). */
    public const PHASE_REGISTRY = 'registry';

    /**
     * The per-process store (plans, self-check answer, test seam). Reset per PHPUnit test with the DI container.
     *
     * @return erasure_plan_store
     */
    private static function store(): erasure_plan_store {
        return \core\di::get(erasure_plan_store::class);
    }

    /**
     * Current time from \core\clock, so PHPUnit can move it.
     *
     * @return int
     */
    private static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Unique ints as a dense 0..n-1 list: the only shape that survives core's set_contextids() without a hole.
     *
     * @param array $ids Context ids, any keys, duplicates allowed.
     * @return int[]
     */
    public static function dense(array $ids): array {
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The privacy manager's component list, in the manager's order: a copy of manager::get_component_list()
     * (privacy/classes/manager.php ~505-513), which is protected. Plugin types first (local last among them),
     * then the core subsystems, then 'core'.
     *
     * @return string[]
     */
    public static function component_list(): array {
        $components = array_keys(array_reduce(\core_component::get_component_list(), function($carry, $item) {
            return array_merge($carry, $item);
        }, []));
        $components[] = 'core';
        return $components;
    }

    /**
     * PHPUnit-only seam: wrap every component call this class makes.
     *
     * The callable gets ($component, $method, $params, $real) and returns what the call should return;
     * calling $real() performs the genuine call. Used to inject a failing component, or a list with a known
     * hole, without depending on database row order. The seam lives in the DI store, so it is cleared after
     * every test.
     *
     * @param callable|null $callback The wrapper, or null to remove it.
     * @return void
     * @throws \coding_exception outside PHPUnit.
     */
    public static function set_test_callback(?callable $callback): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('erasure_completeness::set_test_callback() is for PHPUnit only.');
        }
        self::store()->testcallback = $callback;
    }

    /**
     * Call one component's provider method the way the manager does (\core_privacy\manager::
     * component_class_callback(): null when the provider is missing or does not implement $interface).
     * Exceptions propagate: every caller catches them per component.
     *
     * @param string $component Frankenstyle component.
     * @param string $interface Interface the provider must implement.
     * @param string $method Method name.
     * @param array $params Arguments.
     * @return mixed
     */
    private static function call(string $component, string $interface, string $method, array $params) {
        $real = static function() use ($component, $interface, $method, $params) {
            return \core_privacy\manager::component_class_callback($component, $interface, $method, $params);
        };
        $seam = self::store()->testcallback;
        if ($seam !== null && defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return $seam($component, $method, $params, $real);
        }
        return $real();
    }

    /**
     * Whether a component's provider implements an interface (the manager's component_implements(), which is
     * protected).
     *
     * @param string $component Frankenstyle component.
     * @param string $interface Interface name.
     * @return bool
     */
    private static function provider_implements(string $component, string $interface): bool {
        $classname = \core_privacy\manager::get_provider_classname_for_component($component);
        return class_exists($classname) && is_subclass_of($classname, $interface);
    }

    /**
     * Discover one component's list exactly as manager::get_contexts_for_userid() does (manager.php:237-248):
     * the provider's own list (null or not a contextlist -> an empty one), set_component(), then the shared
     * activity-completion contexts for mod_*. That last add is the hole source for mod_quiz and mod_scorm, so
     * calling the provider alone would not reproduce what the stock approval sees.
     *
     * @param int $userid The subject.
     * @param string $component Frankenstyle component.
     * @return contextlist
     */
    public static function discover(int $userid, string $component): contextlist {
        $contextlist = self::call($component, core_user_data_provider::class, 'get_contexts_for_userid', [$userid]);
        if (!$contextlist instanceof contextlist) {
            $contextlist = new contextlist();
        }
        $contextlist->set_component($component);
        return helper::add_shared_contexts_to_contextlist_for($userid, $contextlist);
    }

    /**
     * What core's iterator does with a list, computed from its KEYS with no side effects: no context is loaded
     * and nothing is unset (core's current() unsets missing contexts). Equivalent to the stock foreach for a
     * fresh discovery list, because add_from_sql joins {context}, so every id exists.
     *
     * @param contextlist_base $contextlist Any core contextlist.
     * @return array{full: int[], iterated: int[], missed: int[]}
     */
    public static function analyse(contextlist_base $contextlist): array {
        $ids = $contextlist->get_contextids();
        $iterated = [];
        $i = 0;
        while (isset($ids[$i])) {
            $iterated[] = (int) $ids[$i++];
        }
        $full = array_values(array_map('intval', $ids));
        return [
            'full' => $full,
            'iterated' => $iterated,
            'missed' => array_values(array_diff($full, $iterated)),
        ];
    }

    /**
     * Course contexts holding course_completions rows for the subject, whatever the course's criteria.
     *
     * @param int $userid The subject.
     * @return int[]
     */
    public static function completion_course_contextids(int $userid): array {
        global $DB;
        $sql = "SELECT DISTINCT ctx.id
                  FROM {course_completions} cc
                  JOIN {context} ctx ON ctx.instanceid = cc.course AND ctx.contextlevel = :courselevel
                 WHERE cc.userid = :userid";
        return self::dense($DB->get_fieldset_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]));
    }

    /**
     * Plan the supplementary erasure for one subject. Never throws: a failing component is recorded in the
     * plan's failures (with debugging()) and skipped.
     *
     * @param int $userid The subject. Only the int is used: the subject may have no user row.
     * @param bool $truncation Plan the truncation part (every component's missed ids).
     * @param bool $completion Plan the completion-gap part.
     * @return erasure_plan
     */
    public static function plan(int $userid, bool $truncation, bool $completion): erasure_plan {
        $plan = new erasure_plan($userid, $truncation, $completion, self::now());
        if (!$truncation && !$completion) {
            return $plan;
        }

        try {
            $components = self::component_list();
        } catch (\Throwable $e) {
            debugging('erasure_completeness: cannot read the component list: ' . get_class($e), DEBUG_DEVELOPER);
            $plan->add_failure('core', self::PHASE_DISCOVERY, $e);
            return $plan;
        }
        $selfindex = array_search(self::SELF, $components, true);

        $coursefull = null;
        foreach ($components as $index => $component) {
            if ($component === self::SELF) {
                continue;
            }
            // The completion part needs core_course's list only.
            if (!$truncation && $component !== 'core_course') {
                continue;
            }
            try {
                $analysis = self::analyse(self::discover($userid, $component));
            } catch (\Throwable $e) {
                debugging("erasure_completeness: discovery failed for {$component}: " . get_class($e), DEBUG_DEVELOPER);
                $plan->add_failure($component, self::PHASE_DISCOVERY, $e);
                continue;
            }
            if ($component === 'core_course') {
                $coursefull = $analysis['full'];
            }
            if ($truncation && $analysis['full']) {
                $plan->components[$component] = [
                    'position' => ($selfindex !== false && $index < $selfindex) ? self::BEFORE : self::AFTER,
                ] + $analysis;
            }
        }

        if ($completion) {
            if ($coursefull === null && !in_array('core_course', $components, true)) {
                $coursefull = [];
            }
            // A null here means core_course's discovery failed (already recorded): the gap is then unknown,
            // and the delete reports that failure rather than guessing.
            if ($coursefull !== null) {
                try {
                    $plan->completiongap = self::dense(
                        array_diff(self::completion_course_contextids($userid), $coursefull));
                } catch (\Throwable $e) {
                    debugging('erasure_completeness: completion gap query failed: ' . get_class($e), DEBUG_DEVELOPER);
                    $plan->add_failure('core_completion', self::PHASE_DISCOVERY, $e);
                }
            }
        }
        return $plan;
    }

    /**
     * Keep a plan for the delete (or export) that follows its discovery in the same process. Overwrites the
     * subject's previous plan and drops plans older than the TTL.
     *
     * @param erasure_plan $plan The plan.
     * @return void
     */
    public static function remember(erasure_plan $plan): void {
        $store = self::store();
        $now = self::now();
        foreach ($store->plans as $userid => $old) {
            if ($now - $old->createdat > erasure_plan_store::TTL || $now < $old->createdat) {
                unset($store->plans[$userid]);
            }
        }
        $store->plans[$plan->userid] = $plan;
    }

    /**
     * Drop the subject's plan, if any.
     *
     * @param int $userid The subject.
     * @return void
     */
    public static function forget(int $userid): void {
        unset(self::store()->plans[$userid]);
    }

    /**
     * Take (consume and clear) the subject's plan if it is still fresh.
     *
     * @param int $userid The subject.
     * @return erasure_plan|null
     */
    private static function take(int $userid): ?erasure_plan {
        $store = self::store();
        $plan = $store->plans[$userid] ?? null;
        unset($store->plans[$userid]);
        if ($plan === null) {
            return null;
        }
        $age = self::now() - $plan->createdat;
        return ($age >= 0 && $age <= erasure_plan_store::TTL) ? $plan : null;
    }

    /**
     * Does the stock approval still truncate holey lists? Cached per process (per test in PHPUnit).
     *
     * Builds a list with a forced hole, [SYSCONTEXTID, SYSCONTEXTID, front-page course context], and asks
     * tool_dataprivacy to approve it as an EXPORT (no protection filter applies to exports). When the second
     * context comes back approved, core has been fixed and the truncation recovery must become a no-op.
     * When core can no longer even store a hole (set_contextids() reindexes), it cannot truncate either.
     * Any error: assume it still truncates (the safe answer for an erasure duty).
     *
     * @return bool
     */
    public static function stock_approval_truncates(): bool {
        $store = self::store();
        if ($store->stocktruncates !== null) {
            return $store->stocktruncates;
        }
        try {
            $other = (int) \context_course::instance(SITEID)->id;
            $probe = new forced_contextlist();
            $probe->set_component(self::SELF);
            $probe->force([SYSCONTEXTID, SYSCONTEXTID, $other]);
            $stored = $probe->get_contextids();
            if (isset($stored[1]) || !isset($stored[2])) {
                // set_contextids() no longer leaves a key hole: nothing can be truncated.
                $answer = false;
            } else {
                $collection = new contextlist_collection((int) get_admin()->id);
                $collection->add_contextlist($probe);
                $approved = \tool_dataprivacy\api::get_approved_contextlist_collection_for_collection(
                    $collection, get_admin(), \tool_dataprivacy\api::DATAREQUEST_TYPE_EXPORT);
                $approvedids = [];
                foreach ($approved->get_contextlists() as $list) {
                    $approvedids = array_merge($approvedids, array_map('intval', $list->get_contextids()));
                }
                $answer = !in_array($other, $approvedids, true);
            }
        } catch (\Throwable $e) {
            debugging('erasure_completeness: stock approval self-check failed, assuming it truncates: '
                . get_class($e), DEBUG_DEVELOPER);
            $answer = true;
        }
        $store->stocktruncates = $answer;
        return $answer;
    }

    /**
     * The tool_dataprivacy DELETE request currently being processed for the subject, if any.
     *
     * @param int $userid The subject.
     * @return int|null The request id.
     */
    public static function find_processing_request(int $userid): ?int {
        global $DB;
        try {
            $id = $DB->get_field_sql(
                "SELECT MAX(id) FROM {tool_dataprivacy_request}
                  WHERE userid = :userid AND type = :type AND status = :status",
                ['userid' => $userid, 'type' => \tool_dataprivacy\api::DATAREQUEST_TYPE_DELETE,
                 'status' => \tool_dataprivacy\api::DATAREQUEST_STATUS_PROCESSING]);
        } catch (\Throwable $e) {
            return null;
        }
        return $id ? (int) $id : null;
    }

    /**
     * Whether the data registry defaults are set (the stock protection filter is active).
     *
     * @return bool
     */
    private static function registry_configured(): bool {
        return class_exists(\tool_dataprivacy\data_registry::class)
            && \tool_dataprivacy\data_registry::defaults_set();
    }

    /**
     * Keep only the contexts the data registry lets us delete for this user: repeats the request path's filter
     * (api.php:1213-1222) and protects the user-expiry path, which has none. A context that is gone, or whose
     * check fails, is kept (not deleted).
     *
     * @param int[] $ids Candidate context ids (dense).
     * @param \stdClass $user The subject.
     * @param array $verdicts Per-context cache, shared across components of one call.
     * @param string|null $failed Set to the exception class when a check threw (null otherwise).
     * @return array{0: int[], 1: int} The allowed ids (dense) and how many were dropped.
     */
    private static function registry_filter(array $ids, \stdClass $user, array &$verdicts, ?string &$failed): array {
        $kept = [];
        $dropped = 0;
        foreach ($ids as $id) {
            if (!array_key_exists($id, $verdicts)) {
                try {
                    $context = \context::instance_by_id($id, IGNORE_MISSING);
                    $verdicts[$id] = $context
                        && \tool_dataprivacy\expired_contexts_manager::is_context_expired_or_unprotected_for_user(
                            $context, $user);
                } catch (\Throwable $e) {
                    debugging('erasure_completeness: data registry check failed: ' . get_class($e), DEBUG_DEVELOPER);
                    $verdicts[$id] = false;
                    $failed = get_class($e);
                }
            }
            if ($verdicts[$id]) {
                $kept[] = $id;
            } else {
                $dropped++;
            }
        }
        return [self::dense($kept), $dropped];
    }

    /**
     * The plan to act on: the remembered one when it is fresh and covers the requested parts, else a new one.
     *
     * @param int $userid The subject.
     * @param bool $truncation The truncation part is wanted.
     * @param bool $completion The completion part is wanted.
     * @return array{0: erasure_plan, 1: bool} The plan and whether it came from discovery (cached).
     */
    private static function plan_for(int $userid, bool $truncation, bool $completion): array {
        $plan = self::take($userid);
        if ($plan !== null && (!$truncation || $plan->truncation) && (!$completion || $plan->completion)) {
            return [$plan, true];
        }
        return [self::plan($userid, $truncation, $completion), false];
    }

    /**
     * An empty result for apply_delete() / apply_export().
     *
     * @param string $path PATH_* value.
     * @param int|null $requestid The tool_dataprivacy request, if known.
     * @param bool $truncation The truncation part was requested.
     * @param bool $completion The completion part was requested.
     * @return array
     */
    private static function new_result(string $path, ?int $requestid, bool $truncation, bool $completion): array {
        return [
            'path' => $path,
            'requestid' => $requestid,
            'truncation' => $truncation,
            'completion' => $completion,
            'plan' => 'none',
            'components' => [],
            'completion_courses' => 0,
            'completion_dropped_by_registry' => 0,
            'completion_failed' => 0,
            'failures' => [],
        ];
    }

    /**
     * Record a failure in a result, with debugging() (class name only).
     *
     * @param array $result The result being built.
     * @param string $component Frankenstyle component.
     * @param string $phase PHASE_* value.
     * @param \Throwable $e The failure.
     * @return void
     */
    private static function fail(array &$result, string $component, string $phase, \Throwable $e): void {
        debugging("erasure_completeness: {$phase} failed for {$component}: " . get_class($e), DEBUG_DEVELOPER);
        $result['failures'][] = ['component' => $component, 'phase' => $phase, 'exception' => get_class($e)];
        if (isset($result['components'][$component])) {
            $result['components'][$component]['failed'] = 1;
        }
    }

    /**
     * Delete what the stock approval dropped, for one subject.
     *
     * Hit sets (ids the supplement acts on, per component C):
     *   - plan from this process's discovery (cache hit): missed_C ∩ approved;
     *   - otherwise (cache miss) a fresh plan: components BEFORE self have already deleted their iterated prefix
     *     in this run, so full_now_C ∩ approved; components AFTER self have not run yet, so missed_now_C ∩ approved;
     *   - completion: gap ∩ approved, dispatched through core_course (delete_completion per course).
     * With data registry defaults set, every candidate context goes through the stock protection check first.
     *
     * The manager's shared completion cleanup (helper::delete_data_for_user(), manager.php:418-419) on
     * local_sentientia_privacy's OWN approved list: on PATH_REQUEST and PATH_EXPIRY the privacy manager runs it
     * itself right after this provider's delete, so this method does not. On PATH_CLI there is no manager, so this
     * method runs it on the approved ids (registry-filtered when defaults are set): activity completion and views
     * of the subject in every approved MODULE context, also those missed only by a non-module component such as
     * tool_log. A CLI caller must not run it again.
     *
     * Each component call is caught on its own and the loop always finishes. Failures (including the plan's
     * discovery failures) are returned, not thrown: the provider turns them into ONE exception so the manager
     * reports it to its observer once (the DPO notification any stock provider failure gets).
     *
     * @param \stdClass $user The subject (the approved list's user).
     * @param int[] $approvedids The ids approved for local_sentientia_privacy.
     * @param string|null $path PATH_* value; null = request when a DELETE request is processing, else expiry.
     * @param bool $truncation Apply the truncation part.
     * @param bool $completion Apply the completion-gap part.
     * @param int|null $requestid The tool_dataprivacy request id, when the caller knows it (CLI).
     * @return array Counts per component {contexts (handed to the component), dropped_by_registry, failed (1 when
     *     any call for it threw: its contexts were attempted, not confirmed erased)}, completion_courses (gap
     *     courses handed to core_course), completion_failed (1 when that call threw), completion_dropped_by_registry,
     *     failures [{component, phase, exception}], path, requestid, plan.
     */
    public static function apply_delete(\stdClass $user, array $approvedids, ?string $path = null,
            bool $truncation = true, bool $completion = true, ?int $requestid = null): array {
        $userid = (int) $user->id;
        if ($requestid === null && $path !== self::PATH_EXPIRY) {
            $requestid = self::find_processing_request($userid);
        }
        if ($path === null) {
            $path = $requestid ? self::PATH_REQUEST : self::PATH_EXPIRY;
        }
        $result = self::new_result($path, $requestid, $truncation, $completion);
        if (!$truncation && !$completion) {
            self::forget($userid);
            return $result;
        }

        $approved = self::dense($approvedids);
        [$plan, $cached] = self::plan_for($userid, $truncation, $completion);
        $result['plan'] = $cached ? 'cached' : 'recomputed';
        $result['failures'] = $plan->failures;

        // Hit sets.
        $hits = [];
        if ($truncation) {
            foreach ($plan->components as $component => $entry) {
                $base = (!$cached && $entry['position'] === self::BEFORE) ? $entry['full'] : $entry['missed'];
                $hit = self::dense(array_intersect($base, $approved));
                if ($hit) {
                    $hits[$component] = $hit;
                }
            }
        }
        $hitcc = $completion ? self::dense(array_intersect($plan->completiongap, $approved)) : [];

        // Data registry protection.
        $filter = self::registry_configured();
        $verdicts = [];
        foreach ($hits as $component => $ids) {
            $dropped = 0;
            if ($filter) {
                $failed = null;
                [$ids, $dropped] = self::registry_filter($ids, $user, $verdicts, $failed);
                if ($failed !== null) {
                    $result['failures'][] = ['component' => $component, 'phase' => self::PHASE_REGISTRY,
                        'exception' => $failed];
                }
            }
            $hits[$component] = $ids;
            $result['components'][$component] = ['contexts' => count($ids), 'dropped_by_registry' => $dropped,
                'failed' => 0];
        }
        if ($filter && $hitcc) {
            $failed = null;
            [$hitcc, $result['completion_dropped_by_registry']] = self::registry_filter($hitcc, $user, $verdicts,
                $failed);
            if ($failed !== null) {
                $result['failures'][] = ['component' => 'core_course', 'phase' => self::PHASE_REGISTRY,
                    'exception' => $failed];
            }
        }
        $result['completion_courses'] = count($hitcc);

        // Dispatch, one component at a time, mirroring manager::delete_data_for_user() (manager.php:411-419).
        foreach ($hits as $component => $ids) {
            if (!$ids) {
                continue;
            }
            $list = new approved_contextlist($user, $component, self::dense($ids));
            try {
                self::call($component, core_user_data_provider::class, 'delete_data_for_user', [$list]);
            } catch (\Throwable $e) {
                self::fail($result, $component, self::PHASE_DELETE, $e);
            }
            if (strpos($component, 'mod_') === 0) {
                try {
                    helper::delete_data_for_user($list);
                } catch (\Throwable $e) {
                    self::fail($result, $component, self::PHASE_DELETE_SHARED, $e);
                }
            }
        }
        if ($hitcc) {
            $list = new approved_contextlist($user, 'core_course', self::dense($hitcc));
            try {
                self::call('core_course', core_user_data_provider::class, 'delete_data_for_user', [$list]);
            } catch (\Throwable $e) {
                debugging('erasure_completeness: completion delete failed: ' . get_class($e), DEBUG_DEVELOPER);
                $result['failures'][] = ['component' => 'core_course', 'phase' => self::PHASE_DELETE,
                    'exception' => get_class($e)];
                $result['completion_failed'] = 1;
            }
        }

        // No manager on the CLI path: run the shared cleanup it would run on our own approved list (see above).
        if ($path === self::PATH_CLI && $approved) {
            $shared = $approved;
            if ($filter) {
                $failed = null;
                [$shared, ] = self::registry_filter($shared, $user, $verdicts, $failed);
                if ($failed !== null) {
                    $result['failures'][] = ['component' => self::SELF, 'phase' => self::PHASE_REGISTRY,
                        'exception' => $failed];
                }
            }
            if ($shared) {
                try {
                    helper::delete_data_for_user(new approved_contextlist($user, self::SELF, self::dense($shared)));
                } catch (\Throwable $e) {
                    self::fail($result, self::SELF, self::PHASE_DELETE_SHARED, $e);
                }
            }
        }

        self::log_event($result);
        return $result;
    }

    /**
     * Export what the stock approval dropped, for one subject.
     *
     * The truncation part is skipped when tool_dataprivacy|allowfiltering is set: that flow stores the FULL
     * lists (api.php:1396) and rebuilds dense ones from the database, so nothing is truncated there and each
     * owning component already exports its whole list. The completion part still runs then: core_course never
     * discovers the gap courses, so only this plugin's stored list carries them. Otherwise
     * hit_C = missed_C ∩ approved (export deletes nothing, so 'missed' is stable and a fresh plan gives the
     * same answer), exported the way manager::export_user_data() exports one list (manager.php:321-345), plus
     * the completion gap through core_course's export.
     *
     * @param approved_contextlist $approved local_sentientia_privacy's approved list.
     * @param bool $truncation Apply the truncation part.
     * @param bool $completion Apply the completion-gap part.
     * @return array Same shape as apply_delete() (path 'export'); dropped_by_registry is always 0.
     */
    public static function apply_export(approved_contextlist $approved, bool $truncation = true,
            bool $completion = true): array {
        $user = $approved->get_user();
        $userid = (int) $user->id;
        if (get_config('tool_dataprivacy', 'allowfiltering')) {
            $truncation = false;
        }
        $result = self::new_result('export', null, $truncation, $completion);
        if (!$truncation && !$completion) {
            self::forget($userid);
            return $result;
        }

        $approvedids = self::dense($approved->get_contextids());
        [$plan, $cached] = self::plan_for($userid, $truncation, $completion);
        $result['plan'] = $cached ? 'cached' : 'recomputed';
        $result['failures'] = $plan->failures;

        if ($truncation) {
            foreach ($plan->components as $component => $entry) {
                $hit = self::dense(array_intersect($entry['missed'], $approvedids));
                if (!$hit) {
                    continue;
                }
                $result['components'][$component] = ['contexts' => count($hit), 'dropped_by_registry' => 0,
                    'failed' => 0];
                $list = new approved_contextlist($user, $component, $hit);
                try {
                    if (self::provider_implements($component, core_user_data_provider::class)) {
                        self::call($component, core_user_data_provider::class, 'export_user_data', [$list]);
                    } else if (!self::provider_implements($component, context_aware_provider::class)) {
                        helper::export_data_for_null_provider($list);
                    }
                } catch (\Throwable $e) {
                    self::fail($result, $component, self::PHASE_EXPORT, $e);
                }
            }
        }
        if ($completion) {
            $hitcc = self::dense(array_intersect($plan->completiongap, $approvedids));
            $result['completion_courses'] = count($hitcc);
            if ($hitcc) {
                try {
                    self::call('core_course', core_user_data_provider::class, 'export_user_data',
                        [new approved_contextlist($user, 'core_course', $hitcc)]);
                } catch (\Throwable $e) {
                    debugging('erasure_completeness: completion export failed: ' . get_class($e), DEBUG_DEVELOPER);
                    $result['failures'][] = ['component' => 'core_course', 'phase' => self::PHASE_EXPORT,
                        'exception' => get_class($e)];
                    $result['completion_failed'] = 1;
                }
            }
        }
        return $result;
    }

    /**
     * Fire erasure_supplemented and, in CLI/cron, print one summary line. Never throws: logging must not be
     * able to fail an erasure.
     *
     * @param array $result apply_delete()'s result.
     * @return void
     */
    private static function log_event(array $result): void {
        try {
            \local_sentientia_privacy\event\erasure_supplemented::create_from_result($result)->trigger();
        } catch (\Throwable $e) {
            debugging('erasure_completeness: could not log erasure_supplemented: ' . get_class($e), DEBUG_DEVELOPER);
        }
        if (CLI_SCRIPT && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            // Erased = handed to a component whose calls all succeeded; a failed component's contexts were only
            // attempted.
            $contexts = 0;
            $components = 0;
            $failedcontexts = 0;
            $dropped = (int) $result['completion_dropped_by_registry'];
            foreach ($result['components'] as $counts) {
                $dropped += (int) $counts['dropped_by_registry'];
                if (!empty($counts['failed'])) {
                    $failedcontexts += (int) $counts['contexts'];
                } else if ((int) $counts['contexts'] > 0) {
                    $contexts += (int) $counts['contexts'];
                    $components++;
                }
            }
            $courses = empty($result['completion_failed']) ? (int) $result['completion_courses'] : 0;
            $failedcourses = empty($result['completion_failed']) ? 0 : (int) $result['completion_courses'];
            mtrace('local_sentientia_privacy: supplementary erasure (' . $result['path'] . ', plan '
                . $result['plan'] . '): erased ' . $contexts . ' context(s) in ' . $components . ' component(s) and '
                . $courses . ' completion course(s); attempted but failed ' . $failedcontexts . ' context(s) and '
                . $failedcourses . ' completion course(s); ' . $dropped . ' kept by the data registry; '
                . count($result['failures']) . ' failure(s).');
        }
    }
}
