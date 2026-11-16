<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_privacy\local\erasure_completeness;

/**
 * Privacy provider — GDPR / DPDP metadata + export for the DSR plugin itself.
 *
 * Tables that carry user data:
 *   - local_privacy_requests    : DSR requests lodged by (or for) a user,
 *                                 plus the admin who processed them
 *   - local_privacy_consent_log : per-user consent grant/withdraw events
 *                                 with IP address and user agent
 *
 * DELETION IS INTENTIONALLY A NO-OP. Both tables are the organisation's
 * record of processing (GDPR Art. 30) and proof of consent (DPDP Act 2023
 * s.6) — erasing them on a user's erasure request would destroy the very
 * evidence that the request was honoured. This mirrors Moodle core's
 * tool_dataprivacy provider, whose delete methods are also empty.
 *
 * ERASURE COMPLETENESS (1.1.0, 2026-11-13, "Option A", no core edit; ADR-035).
 * Two feature flags, both default OFF, recover what stock tool_dataprivacy
 * erasure and export miss because of two core defects (logic in
 * \local_sentientia_privacy\local\erasure_completeness). That makes them
 * complete as ADR-035 defines it, not literally: see ADR-035 "What still
 * remains" (vanished contexts, relateduserid/realuserid log rows, subjects
 * without a user row, the user row, the user-list path).
 *   - FLAG_TRUNCATION: core's contextlist iterator stops at the first key
 *     hole, so the stock approval drops contexts of tool_log, report_stats,
 *     mod_quiz, mod_scorm, core_course ... and never deletes them. This
 *     provider adds the dropped contexts to its own (hole-free) list, so the
 *     stock approval judges them like any other, then hands each one back to
 *     the component that owns it at delete/export time.
 *   - FLAG_COMPLETION: core_course never discovers course_completions rows in
 *     courses without (or with untouched) completion criteria; this provider
 *     adds those course contexts and erases them through core_course.
 * Both flags are read at GLOBAL scope only (is_enabled_for(key, 0, 0)): DELETE
 * tasks run as the admin and EXPORT tasks as the subject, so a per-user read
 * would let one subject's export and erasure disagree. Customer and tenant
 * overrides are unsupported by design: an erasure duty is not tenant-optional.
 * With both flags OFF this provider behaves exactly as 1.0.3 did.
 *
 * @package local_sentientia_privacy
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /** Feature flag: recover the contexts core's truncated approval drops (registry: db/feature_flags.php). */
    public const FLAG_TRUNCATION = 'sentientia.privacy.erasure_completeness.enabled';

    /** Feature flag: erase course_completions rows core_course never discovers. */
    public const FLAG_COMPLETION = 'sentientia.privacy.completion_gap.enabled';

    /**
     * @var bool Re-entrancy guard. Nothing in core calls a provider from inside another provider's call, but
     *     if anything ever re-enters this provider while it is running the supplement, the inner call gets the
     *     own-only (1.0.3) behaviour instead of recursing.
     */
    private static bool $running = false;

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_privacy_requests',
            [
                'userid'        => 'privacy:metadata:privacy_requests:userid',
                'request_type'  => 'privacy:metadata:privacy_requests:request_type',
                'status'        => 'privacy:metadata:privacy_requests:status',
                'reason'        => 'privacy:metadata:privacy_requests:reason',
                'admin_notes'   => 'privacy:metadata:privacy_requests:admin_notes',
                'processed_by'  => 'privacy:metadata:privacy_requests:processed_by',
                'download_url'  => 'privacy:metadata:privacy_requests:download_url',
                'timecreated'   => 'privacy:metadata:privacy_requests:timecreated',
                'timeprocessed' => 'privacy:metadata:privacy_requests:timeprocessed',
            ],
            'privacy:metadata:privacy_requests'
        );

        $collection->add_database_table(
            'local_privacy_consent_log',
            [
                'userid'       => 'privacy:metadata:consent_log:userid',
                'consent_type' => 'privacy:metadata:consent_log:consent_type',
                'consented'    => 'privacy:metadata:consent_log:consented',
                'ip_address'   => 'privacy:metadata:consent_log:ip_address',
                'user_agent'   => 'privacy:metadata:consent_log:user_agent',
                'timecreated'  => 'privacy:metadata:consent_log:timecreated',
            ],
            'privacy:metadata:consent_log'
        );

        // Cross-component role: with the erasure-completeness flags on, this
        // plugin's context list also carries contexts whose data belongs to
        // other components, so the DPO's plugin registry must say why.
        $collection->link_subsystem('core_privacy', 'privacy:metadata:erasurecompleteness');

        return $collection;
    }

    /**
     * Read one of this plugin's flags at global scope. A flag-store fault reads as OFF (with debugging()), so
     * it can never become a component failure, and a DPO notice, on every data request.
     *
     * @param string $key FLAG_TRUNCATION or FLAG_COMPLETION.
     * @return bool
     */
    public static function flag_enabled(string $key): bool {
        try {
            if (!class_exists(\local_sentientia_platform\feature_flags::class)) {
                return false;
            }
            return \local_sentientia_platform\feature_flags::is_enabled_for($key, 0, 0);
        } catch (\Throwable $e) {
            debugging("local_sentientia_privacy: cannot read flag {$key}, treating it as OFF: " . get_class($e),
                DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Which supplementary parts are on: [truncation, completion]. The truncation part also needs the stock
     * approval to still truncate (it becomes a no-op the day core is fixed).
     *
     * @return bool[]
     */
    private static function supplement_parts(): array {
        $truncation = self::flag_enabled(self::FLAG_TRUNCATION);
        if ($truncation && !erasure_completeness::stock_approval_truncates()) {
            $truncation = false;
        }
        return [$truncation, self::flag_enabled(self::FLAG_COMPLETION)];
    }

    /**
     * The 1.0.3 list: the system context only, in one add_from_sql.
     *
     * @return contextlist
     */
    private static function own_contextlist(): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * One aggregate exception naming the failed components (no ids, no user data). Thrown out of
     * delete/export so the privacy manager reports it to its observer once, as for any stock provider failure.
     * The observer mails its message to every DPO, so the wording matches what happens next: a DELETE still
     * completes (the request is marked Deleted and never reprocessed: the subject needs the ADR-035 residue
     * sweep); an EXPORT file is missing data (a new export request is the remedy).
     *
     * @param array $failures [{component, phase, exception}]
     * @param string $type 'delete' or 'export'.
     * @return \moodle_exception
     */
    private static function aggregate_failure(array $failures, string $type): \moodle_exception {
        $components = array_values(array_unique(array_map(fn($f) => (string) $f['component'], $failures)));
        sort($components);
        $errorcode = $type === 'export' ? 'erasuresupplementfailed_export' : 'erasuresupplementfailed_delete';
        return new \moodle_exception($errorcode, 'local_sentientia_privacy', '', implode(', ', $components));
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        // Both tables are system-context — DSR requests and consent events
        // aren't bound to a course context.
        if (self::$running) {
            return self::own_contextlist();
        }
        self::$running = true;
        try {
            [$truncation, $completion] = self::supplement_parts();
            if (!$truncation && !$completion) {
                return self::own_contextlist();
            }
            $plan = erasure_completeness::plan($userid, $truncation, $completion);
            erasure_completeness::remember($plan);
            $supplementary = $plan->supplementary_contextids();
            if (!$supplementary) {
                return self::own_contextlist();
            }
            // ONE add_from_sql for own + supplementary ids: SYSCONTEXTID is
            // often supplementary too (tool_log), and a second add returning
            // it again would leave a key hole in this very list.
            $ids = erasure_completeness::dense(array_merge([SYSCONTEXTID], $supplementary));
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'sctx');
            $contextlist = new contextlist();
            $contextlist->add_from_sql("SELECT id FROM {context} WHERE id $insql", $params);
            return $contextlist;
        } catch (\Throwable $e) {
            // Never throw from discovery: the manager would replace this whole
            // list (own system context included) with an empty one.
            debugging('local_sentientia_privacy: supplementary discovery failed, own contexts only: '
                . get_class($e), DEBUG_DEVELOPER);
            return self::own_contextlist();
        } finally {
            self::$running = false;
        }
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_privacy_requests}", []);
        $userlist->add_from_sql('processed_by',
            "SELECT processed_by FROM {local_privacy_requests}
              WHERE processed_by IS NOT NULL", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_privacy_consent_log}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        $failures = [];
        $ownfailure = null;
        try {
            self::export_own_data($contextlist);
        } catch (\Throwable $e) {
            $ownfailure = $e;
            $failures[] = ['component' => erasure_completeness::SELF, 'phase' => erasure_completeness::PHASE_EXPORT,
                'exception' => get_class($e)];
        }

        if (!self::$running) {
            [$truncation, $completion] = self::supplement_parts();
            if ($truncation || $completion) {
                self::$running = true;
                try {
                    $result = erasure_completeness::apply_export($contextlist, $truncation, $completion);
                    $failures = array_merge($failures, $result['failures']);
                } catch (\Throwable $e) {
                    $failures[] = ['component' => erasure_completeness::SELF,
                        'phase' => erasure_completeness::PHASE_EXPORT, 'exception' => get_class($e)];
                } finally {
                    self::$running = false;
                }
            } else {
                erasure_completeness::forget((int) $contextlist->get_user()->id);
            }
        }

        if ($ownfailure !== null && count($failures) === 1) {
            // Only our own export failed: rethrow it unchanged (1.0.3 behaviour).
            throw $ownfailure;
        }
        if ($failures) {
            throw self::aggregate_failure($failures, 'export');
        }
    }

    /**
     * Export this plugin's own data (system context only).
     *
     * Walks the ids rather than get_contexts(): core's get_contexts() unsets
     * the wrong key for a context that has gone (contextlist_base.php:94),
     * which would punch a hole into a list this plugin depends on.
     *
     * @param approved_contextlist $contextlist The approved list.
     * @return void
     */
    private static function export_own_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach (array_values($contextlist->get_contextids()) as $contextid) {
            $context = \context::instance_by_id((int) $contextid, IGNORE_MISSING);
            if (!$context instanceof \context_system) {
                continue;
            }

            // DSR requests the user is the subject of.
            $requests = $DB->get_records('local_privacy_requests',
                ['userid' => $userid], 'timecreated ASC');
            $request_data = [];
            foreach ($requests as $r) {
                $request_data[] = [
                    'request_type'  => $r->request_type,
                    'status'        => $r->status,
                    'reason'        => $r->reason,
                    'admin_notes'   => $r->admin_notes,
                    'processed_by'  => (int) $r->processed_by,
                    'download_url'  => $r->download_url,
                    'timecreated'   => userdate((int) $r->timecreated),
                    'timeprocessed' => $r->timeprocessed
                        ? userdate((int) $r->timeprocessed) : null,
                ];
            }
            if (!empty($request_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_privacy'),
                     'requests'],
                    (object) ['requests' => $request_data]
                );
            }

            // Requests this user processed as an admin. The subject's
            // userid is deliberately omitted — it is the subject's
            // personal data, not the processing admin's.
            $processed = $DB->get_records('local_privacy_requests',
                ['processed_by' => $userid], 'timeprocessed ASC');
            $processed_data = [];
            foreach ($processed as $p) {
                $processed_data[] = [
                    'request_type'  => $p->request_type,
                    'status'        => $p->status,
                    'timeprocessed' => $p->timeprocessed
                        ? userdate((int) $p->timeprocessed) : null,
                ];
            }
            if (!empty($processed_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_privacy'),
                     'requests_processed_as_admin'],
                    (object) ['requests_processed' => $processed_data]
                );
            }

            // Consent events.
            $consents = $DB->get_records('local_privacy_consent_log',
                ['userid' => $userid], 'timecreated ASC');
            $consent_data = [];
            foreach ($consents as $c) {
                $consent_data[] = [
                    'consent_type' => $c->consent_type,
                    'consented'    => (bool) $c->consented,
                    'ip_address'   => $c->ip_address,
                    'user_agent'   => $c->user_agent,
                    'timecreated'  => userdate((int) $c->timecreated),
                ];
            }
            if (!empty($consent_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_sentientia_privacy'),
                     'consent_log'],
                    (object) ['consents' => $consent_data]
                );
            }
            // One system context; the other ids in the list belong to other
            // components (erasure completeness).
            break;
        }
    }

    // Deletion of OWN data is a no-op — see class doc block. DSR request rows
    // and consent events are records of processing / proof of consent and are
    // retained when a user's other data is erased (same stance as
    // tool_dataprivacy). The userlist side is unchanged: userlist_base
    // reindexes, so it has no truncation. Known residual (recorded, not
    // built): partial course-context expiry runs through userlists, and
    // core_completion's get_users_in_context needs criteria joins
    // (completion/classes/privacy/provider.php:116-148), so course_completions
    // rows in courses without criteria survive that path.

    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    /**
     * Own data: nothing (see above). With an erasure-completeness flag on,
     * erase what the stock approval dropped, then report every failed
     * component in ONE exception: the manager hands it to its observer once
     * (the DPO is notified, as for any stock provider failure) and still runs
     * the shared completion cleanup on this list.
     *
     * @param approved_contextlist $contextlist The approved list.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (self::$running) {
            return;
        }
        $user = $contextlist->get_user();
        [$truncation, $completion] = self::supplement_parts();
        if (!$truncation && !$completion) {
            erasure_completeness::forget((int) $user->id);
            return;
        }
        self::$running = true;
        try {
            $requestid = erasure_completeness::find_processing_request((int) $user->id);
            $result = erasure_completeness::apply_delete($user, array_values($contextlist->get_contextids()),
                $requestid ? erasure_completeness::PATH_REQUEST : erasure_completeness::PATH_EXPIRY,
                $truncation, $completion, $requestid);
        } finally {
            self::$running = false;
        }
        if ($result['failures']) {
            throw self::aggregate_failure($result['failures'], 'delete');
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
    }
}
