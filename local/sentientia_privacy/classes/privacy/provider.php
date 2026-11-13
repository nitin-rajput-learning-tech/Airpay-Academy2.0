<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_privacy\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the DSR plugin's own tables, plus the ADR-035 erasure
 * supplement for three kinds of core data that core's own providers miss.
 *
 * OWN TABLES (deletion is deliberately a no-op):
 *   - local_privacy_requests    : DSR requests lodged by (or for) a user,
 *                                 plus the admin who processed them
 *   - local_privacy_consent_log : per-user consent grant/withdraw events
 *                                 with IP address and user agent
 * Both are the organisation's record of processing (GDPR Art. 30) and proof
 * of consent (DPDP Act 2023 s.6). Erasing them on a user's erasure request
 * would destroy the evidence that the request was honoured. Moodle core's
 * tool_dataprivacy provider takes the same stance.
 *
 * CORE SUPPLEMENT (ADR-035, accepted 2026-11-13, no feature flag):
 * two stock Moodle defects leave data behind on a 'Deleted' request:
 *   1. contextlist_base::set_contextids() is array_unique() without a reindex
 *      and valid() is isset() on the position, so iterating a context list
 *      stops at the first duplicate. tool_dataprivacy builds the approved list
 *      by iterating, so logstore_standard and report_stats (whose discovery
 *      returns one row per data row) are cut short.
 *   2. core_course finds course_completions only through completion criteria,
 *      so a completion in a course without criteria (or whose criteria the
 *      user never touched) is never found.
 * This provider discovers those contexts itself, in ONE add_from_sql() over a
 * UNION (so its own list has no duplicates and cannot be cut short), and then
 * re-runs core's OWN delete and export code with the complete list:
 *   - logs:        \logstore_standard\privacy\provider (deletes the rows the
 *                  subject performed, userid; related/real-user rows stay,
 *                  exactly as core intends)
 *   - statistics:  \report_stats\privacy\provider
 *   - completions: \core_completion\privacy\provider::delete_completion()
 * Every delete is DELETE ... WHERE, so running after (or before) the core
 * providers is a no-op for rows they already removed. Contexts reach this
 * provider through the same tool_dataprivacy approval filter as core's, so it
 * never deletes in a protected, unexpired context.
 *
 * The Sentientia DPDP panel (privacy_manager::process_deletion()) never calls
 * this provider: erasure_providers() skips local_sentientia_privacy. That
 * flow promises to keep anonymised learning records, and this supplement
 * would erase completions. Only core's tool_dataprivacy route reaches it.
 *
 * @package local_sentientia_privacy
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /** @var int Context ids per call into a core helper (keeps IN lists well below placeholder limits). */
    private const CHUNK = 5000;

    /** @var string[] Per-user statistics tables written by report_stats. */
    private const STATS_TABLES = ['stats_user_daily', 'stats_user_weekly', 'stats_user_monthly'];

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

        // ADR-035 supplement: the core data this provider also finds, exports
        // and erases when core's own providers miss it.
        $collection->add_plugintype_link('logstore', [], 'privacy:metadata:supplement:logstore');
        $collection->add_subsystem_link('core_completion', [], 'privacy:metadata:supplement:completion');
        foreach (self::STATS_TABLES as $table) {
            $collection->add_database_table(
                $table,
                [
                    'userid'      => 'privacy:metadata:supplement:stats:userid',
                    'courseid'    => 'privacy:metadata:supplement:stats:courseid',
                    'statsreads'  => 'privacy:metadata:supplement:stats:statsreads',
                    'statswrites' => 'privacy:metadata:supplement:stats:statswrites',
                ],
                'privacy:metadata:supplement:stats'
            );
        }

        return $collection;
    }

    /**
     * The system context (own tables, as before) plus every context that holds
     * the user's logs, per-user statistics or course completions.
     *
     * ONE add_from_sql() call over a UNION: UNION removes duplicates in the
     * database and nothing is merged afterwards, so this list never has the
     * hole that cuts core's iteration short (ADR-035 defect 1).
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $branches = [
            // Own tables: DSR requests and consent events are system-context.
            "SELECT sc.id AS contextid
               FROM {context} sc
              WHERE sc.contextlevel = :syslevel",
        ];
        $params = ['syslevel' => CONTEXT_SYSTEM];

        if (self::logstore_available()) {
            // Three single-column selects, each on its foreign-key index; the
            // same three columns core's logstore_standard discovery reads.
            foreach (['userid' => 'lu', 'relateduserid' => 'lr', 'realuserid' => 'lx'] as $column => $p) {
                $branches[] = "SELECT l.contextid
                                 FROM {logstore_standard_log} l
                                WHERE l.{$column} = :{$p}";
                $params[$p] = $userid;
            }
        }

        // Every course completion, with or without completion criteria.
        $branches[] = "SELECT cctx.id
                         FROM {course_completions} cc
                         JOIN {context} cctx ON cctx.instanceid = cc.course AND cctx.contextlevel = :cclevel
                        WHERE cc.userid = :ccuser";
        $params['cclevel'] = CONTEXT_COURSE;
        $params['ccuser'] = $userid;

        foreach (self::STATS_TABLES as $i => $table) {
            $branches[] = "SELECT sctx{$i}.id
                             FROM {{$table}} s{$i}
                             JOIN {context} sctx{$i} ON sctx{$i}.instanceid = s{$i}.courseid
                                  AND sctx{$i}.contextlevel = :stlevel{$i}
                            WHERE s{$i}.userid = :stuser{$i}";
            $params["stlevel{$i}"] = CONTEXT_COURSE;
            $params["stuser{$i}"] = $userid;
        }

        // A derived table, so add_from_sql() joins on sub.contextid rather
        // than falling back to WHERE ctx.id IN (...UNION...).
        $sql = "SELECT sub.contextid FROM (" . implode("\n UNION \n", $branches) . ") sub";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof \context_system) {
            $userlist->add_from_sql('userid',
                "SELECT userid FROM {local_privacy_requests}", []);
            $userlist->add_from_sql('processed_by',
                "SELECT processed_by FROM {local_privacy_requests}
                  WHERE processed_by IS NOT NULL", []);
            $userlist->add_from_sql('userid',
                "SELECT userid FROM {local_privacy_consent_log}", []);
        }

        // Supplement. userlist_base re-indexes after de-duplication, so
        // several add_from_sql() calls are safe here (unlike contextlist).
        if (self::logstore_available()) {
            \logstore_standard\privacy\provider::add_userids_for_context($userlist);
        }
        if ($context instanceof \context_course) {
            \report_stats\privacy\provider::get_users_in_context($userlist);
            $userlist->add_from_sql('userid',
                "SELECT userid FROM {course_completions} WHERE course = :courseid",
                ['courseid' => $context->instanceid]);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
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
        }

        self::export_supplement($contextlist);
    }

    // Own tables: deletion no-ops - see the class doc block. DSR request rows
    // and consent events are records of processing / proof of consent and are
    // retained when a user's other data is erased (same stance as
    // tool_dataprivacy). Each method then runs the ADR-035 supplement.

    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Core's providers already do exactly this for the context; this is a
        // second, normally empty pass with core's own helpers.
        if (self::logstore_available()) {
            \logstore_standard\privacy\provider::delete_data_for_all_users_in_context($context);
        }
        if ($context instanceof \context_course) {
            \report_stats\privacy\provider::delete_data_for_all_users_in_context($context);
            \core_completion\privacy\provider::delete_completion(null, (int) $context->instanceid);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $user = $contextlist->get_user();
        // get_contextids(), never foreach over the list: an approved list built
        // from holes would be cut short again (ADR-035 defect 1).
        $contextids = array_values(array_map('intval', $contextlist->get_contextids()));
        if (empty($contextids)) {
            return;
        }

        if (self::logstore_available()) {
            foreach (array_chunk($contextids, self::CHUNK) as $chunk) {
                // Deletes userid = subject AND contextid IN (...): the rows the
                // subject performed. Rows naming them only as related or real
                // user are the actor's record and stay, as in core.
                \logstore_standard\privacy\provider::delete_data_for_user(
                    new approved_contextlist($user, 'logstore_standard', $chunk));
            }
        }

        $courses = self::course_contexts($contextids);
        if (empty($courses)) {
            return;
        }
        foreach (array_chunk(array_keys($courses), self::CHUNK) as $chunk) {
            // report_stats ignores a list that carries any other component name.
            \report_stats\privacy\provider::delete_data_for_user(
                new approved_contextlist($user, 'report_stats', $chunk));
        }
        foreach ($courses as $courseid) {
            // What core_course runs for the courses it does find.
            \core_completion\privacy\provider::delete_completion($user, $courseid);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (empty($userlist->get_userids())) {
            return;
        }
        $context = $userlist->get_context();

        if (self::logstore_available()) {
            \logstore_standard\privacy\provider::delete_data_for_userlist($userlist);
        }
        if ($context instanceof \context_course) {
            \report_stats\privacy\provider::delete_data_for_users($userlist);
            \core_completion\privacy\provider::delete_completion_by_approved_userlist(
                $userlist, (int) $context->instanceid);
        }
    }

    /**
     * Export the supplement with core's own export code (the same paths core
     * writes, so an overlap rewrites identical content), plus the raw course
     * completion row, which core's derived completion export does not show.
     */
    private static function export_supplement(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        $contextids = array_values(array_map('intval', $contextlist->get_contextids()));
        if (empty($contextids)) {
            return;
        }

        if (self::logstore_available()) {
            foreach (array_chunk($contextids, self::CHUNK) as $chunk) {
                \logstore_standard\privacy\provider::export_user_data(
                    new approved_contextlist($user, 'logstore_standard', $chunk));
            }
        }

        $courses = self::course_contexts($contextids);
        if (empty($courses)) {
            return;
        }
        foreach (array_chunk(array_keys($courses), self::CHUNK) as $chunk) {
            \report_stats\privacy\provider::export_user_data(
                new approved_contextlist($user, 'report_stats', $chunk));
        }

        $path = [get_string('pluginname', 'local_sentientia_privacy'),
                 get_string('privacy:path:coursecompletion', 'local_sentientia_privacy')];
        foreach (array_chunk($courses, self::CHUNK, true) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal(array_values($chunk), SQL_PARAMS_NAMED, 'cid');
            $params['userid'] = $user->id;
            $rows = $DB->get_records_select('course_completions',
                "userid = :userid AND course {$insql}", $params, 'course ASC');
            $byid = array_flip($chunk);
            foreach ($rows as $row) {
                $contextid = $byid[(int) $row->course];
                writer::with_context(\context::instance_by_id($contextid))->export_data($path, (object) [
                    'timeenrolled'  => $row->timeenrolled ? transform::datetime($row->timeenrolled) : null,
                    'timestarted'   => $row->timestarted ? transform::datetime($row->timestarted) : null,
                    'timecompleted' => $row->timecompleted ? transform::datetime($row->timecompleted) : null,
                ]);
            }
        }
    }

    /**
     * The course contexts among the given context ids.
     *
     * @param int[] $contextids
     * @return array<int,int> context id => course id
     */
    private static function course_contexts(array $contextids): array {
        global $DB;
        $courses = [];
        foreach (array_chunk($contextids, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'ctx');
            $params['courselevel'] = CONTEXT_COURSE;
            $courses += array_map('intval', $DB->get_records_select_menu('context',
                "id {$insql} AND contextlevel = :courselevel", $params, '', 'id, instanceid'));
        }
        return $courses;
    }

    /**
     * logstore_standard is a standard plugin, but a site can uninstall it.
     */
    private static function logstore_available(): bool {
        global $DB;
        return class_exists(\logstore_standard\privacy\provider::class)
            && $DB->get_manager()->table_exists('logstore_standard_log');
    }
}
