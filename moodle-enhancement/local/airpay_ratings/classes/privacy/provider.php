<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_ratings\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — GDPR / DPDP metadata + export + delete.
 *
 * Table that carries user data:
 *   - local_airpay_ratings : star ratings a user has given
 *                            (item + area + 1-5 value)
 *
 * @package local_airpay_ratings
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'local_airpay_ratings',
            [
                'userid'       => 'privacy:metadata:ratings:userid',
                'itemid'       => 'privacy:metadata:ratings:itemid',
                'ratearea'     => 'privacy:metadata:ratings:ratearea',
                'rating'       => 'privacy:metadata:ratings:rating',
                'timecreated'  => 'privacy:metadata:ratings:timecreated',
                'timemodified' => 'privacy:metadata:ratings:timemodified',
            ],
            'privacy:metadata:ratings'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        // Ratings are system-context — items are referenced by id + area,
        // not bound to a course context.
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_airpay_ratings}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $ratings = $DB->get_records('local_airpay_ratings',
                ['userid' => $userid], 'timecreated ASC');
            $rating_data = [];
            foreach ($ratings as $r) {
                $rating_data[] = [
                    'itemid'       => (int) $r->itemid,
                    'ratearea'     => $r->ratearea,
                    'rating'       => (int) $r->rating,
                    'timecreated'  => userdate((int) $r->timecreated),
                    'timemodified' => userdate((int) $r->timemodified),
                ];
            }
            if (!empty($rating_data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_airpay_ratings'),
                     'ratings'],
                    (object) ['ratings' => $rating_data]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_airpay_ratings', []);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_airpay_ratings',
                ['userid' => $userid]);
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
        $DB->delete_records_select('local_airpay_ratings',
            "userid $insql", $params);
    }
}
