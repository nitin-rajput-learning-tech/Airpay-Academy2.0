<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider - GDPR / DPDP metadata, export and erasure.
 *
 * REPLACES A null_provider (2026-09-22).
 * -------------------------------------
 * This plugin previously declared `\core_privacy\local\metadata\
 * null_provider`, which is a positive assertion to Moodle's privacy registry
 * that it stores no personal data. That was not true: it owns the tables
 * listed below, each keyed on a user id. A subject access request returned
 * nothing from this plugin and an erasure request deleted nothing, in both
 * cases without any error - the registry simply reported the plugin as
 * holding no data.
 *
 * Tables this plugin owns:
 *   - local_sentientia_ratings
 *       subject rows deleted on erasure
 *   - local_sentientia_ratings_reviews (added 2026-09-30, ADR-032 BizLMS import)
 *       the free text a learner wrote: the most personal column this plugin holds;
 *       subject rows deleted on erasure
 *   - local_sentientia_ratings_reactions (added 2026-09-30, ADR-032 BizLMS import)
 *       subject rows deleted on erasure
 *
 * Erasure deletes the subject's ratings, so an item's average moves; that is the price of removing the
 * person, and no cache is kept that could preserve it.
 *
 * The BizLMS tables the import read (local_rating, local_comment, local_like) hold the same people's data in
 * the legacy archive. This provider cannot reach them; the separate legacy-table privacy deliverable of
 * ADR-032 does (it is recorded there as open).
 *
 * OWNER versus ACTOR columns
 * --------------------------
 * A column that identifies the DATA SUBJECT has its rows deleted on erasure.
 * A column where the subject merely ACTED on someone else's record - an
 * approver, a creator, a decider - is ANONYMISED to 0 instead, because
 * deleting the row would destroy a third party's record or a shared
 * configuration row. Both are exported, so the subject sees everything held
 * about them either way. Every userid in this plugin is an owner column.
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /** @var string[] Every table of this plugin that is keyed on a user id. */
    private const TABLES = [
        'local_sentientia_ratings',
        'local_sentientia_ratings_reviews',
        'local_sentientia_ratings_reactions',
    ];

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_sentientia_ratings',
            [
                'userid' => 'privacy:metadata:ratings:userid',
                'itemid' => 'privacy:metadata:ratings:itemid',
                'ratearea' => 'privacy:metadata:ratings:ratearea',
                'rating' => 'privacy:metadata:ratings:rating',
                'timecreated' => 'privacy:metadata:ratings:timecreated',
                'timemodified' => 'privacy:metadata:ratings:timemodified',
            ],
            'privacy:metadata:ratings'
        );

        $collection->add_database_table(
            'local_sentientia_ratings_reviews',
            [
                'userid' => 'privacy:metadata:reviews:userid',
                'itemid' => 'privacy:metadata:reviews:itemid',
                'ratearea' => 'privacy:metadata:reviews:ratearea',
                'review' => 'privacy:metadata:reviews:review',
                'timecreated' => 'privacy:metadata:reviews:timecreated',
                'timemodified' => 'privacy:metadata:reviews:timemodified',
            ],
            'privacy:metadata:reviews'
        );

        $collection->add_database_table(
            'local_sentientia_ratings_reactions',
            [
                'userid' => 'privacy:metadata:reactions:userid',
                'itemid' => 'privacy:metadata:reactions:itemid',
                'ratearea' => 'privacy:metadata:reactions:ratearea',
                'likestatus' => 'privacy:metadata:reactions:likestatus',
                'timecreated' => 'privacy:metadata:reactions:timecreated',
                'timemodified' => 'privacy:metadata:reactions:timemodified',
            ],
            'privacy:metadata:reactions'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        // All of this plugin's tables are site-level: they reference courses,
        // exams and orgs by id rather than living in a course context. The
        // system context is added only when the user actually appears, so a
        // user with no rows here is not offered an empty export section.
        $found = false;
        foreach (self::TABLES as $table) {
            $found = $found || $DB->record_exists($table, ['userid' => $userid]);
        }

        if ($found) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        foreach (self::TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE userid > 0", []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        $root = get_string('pluginname', 'local_sentientia_ratings');

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            // local_sentientia_ratings
            $ratingsgiven = $DB->get_records_sql(
                "SELECT id, userid, itemid, ratearea, rating, timecreated, timemodified
                   FROM {local_sentientia_ratings}
                  WHERE userid = :u0
               ORDER BY timecreated ASC",
                ['u0' => $userid]);

            if (!empty($ratingsgiven)) {
                $rows = [];
                foreach ($ratingsgiven as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'itemid' => $r->itemid,
                        'ratearea' => $r->ratearea,
                        'rating' => $r->rating,
                        'timecreated' => empty($r->timecreated) ? null : userdate((int) $r->timecreated),
                        'timemodified' => empty($r->timemodified) ? null : userdate((int) $r->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:ratings', 'local_sentientia_ratings')],
                    (object) ['ratingsgiven' => $rows]
                );
            }

            // local_sentientia_ratings_reviews
            $reviewsgiven = $DB->get_records_sql(
                "SELECT id, userid, itemid, ratearea, review, timecreated, timemodified
                   FROM {local_sentientia_ratings_reviews}
                  WHERE userid = :u0
               ORDER BY timecreated ASC, id ASC",
                ['u0' => $userid]);

            if (!empty($reviewsgiven)) {
                $rows = [];
                foreach ($reviewsgiven as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'itemid' => $r->itemid,
                        'ratearea' => $r->ratearea,
                        'review' => $r->review,
                        'timecreated' => empty($r->timecreated) ? null : userdate((int) $r->timecreated),
                        'timemodified' => empty($r->timemodified) ? null : userdate((int) $r->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:reviews', 'local_sentientia_ratings')],
                    (object) ['reviewsgiven' => $rows]
                );
            }

            // local_sentientia_ratings_reactions
            $reactionsgiven = $DB->get_records_sql(
                "SELECT id, userid, itemid, ratearea, likestatus, timecreated, timemodified
                   FROM {local_sentientia_ratings_reactions}
                  WHERE userid = :u0
               ORDER BY timecreated ASC, id ASC",
                ['u0' => $userid]);

            if (!empty($reactionsgiven)) {
                $rows = [];
                foreach ($reactionsgiven as $r) {
                    $rows[] = [
                        'userid' => $r->userid,
                        'itemid' => $r->itemid,
                        'ratearea' => $r->ratearea,
                        'likestatus' => $r->likestatus,
                        'timecreated' => empty($r->timecreated) ? null : userdate((int) $r->timecreated),
                        'timemodified' => empty($r->timemodified) ? null : userdate((int) $r->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    [$root, get_string('privacy:metadata:reactions', 'local_sentientia_ratings')],
                    (object) ['reactionsgiven' => $rows]
                );
            }

        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        foreach (self::TABLES as $table) {
            $DB->delete_records($table, []);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "userid $insql", $params);
        }
    }
}
