<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — Gap P0.1.0.
 *
 * Personal data:
 *   skai_job — ownerid + reviewed_by (who ran/reviewed an extraction)
 *   skai_gap — userid (whose skills gap this is)
 *
 * The taxonomy + candidate + impact tables hold skill metadata, not
 * learner personal data, so they are not exported per-user; the
 * extraction job's ownership and the per-user gap feed are.
 *
 * Also declares the external Anthropic API as a subsystem we send data
 * to (only when the live-API flag is ON, and only the source TEXT — never
 * employee PII, which is screened at paste time).
 *
 * @package local_sentientia_skillsai
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_sentientia_skai_job',
            [
                'ownerid'      => 'privacy:metadata:job:ownerid',
                'reviewed_by'  => 'privacy:metadata:job:reviewed_by',
                'title'        => 'privacy:metadata:job:title',
                'status'       => 'privacy:metadata:job:status',
                'timecreated'  => 'privacy:metadata:job:timecreated',
            ],
            'privacy:metadata:job'
        );

        $collection->add_database_table(
            'local_sentientia_skai_gap',
            [
                'userid'         => 'privacy:metadata:gap:userid',
                'skillid'        => 'privacy:metadata:gap:skillid',
                'required_level' => 'privacy:metadata:gap:required_level',
                'held_level'     => 'privacy:metadata:gap:held_level',
                'timecreated'    => 'privacy:metadata:gap:timecreated',
            ],
            'privacy:metadata:gap'
        );

        // External subsystem — Anthropic Claude. Only source learning text
        // is sent, screened for PII at paste time.
        $collection->add_external_location_link(
            'anthropic_api',
            [
                'sourcetext' => 'privacy:metadata:anthropic:sourcetext',
                'model'      => 'privacy:metadata:anthropic:model',
            ],
            'privacy:metadata:anthropic'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('ownerid',
            "SELECT ownerid FROM {local_sentientia_skai_job}", []);
        $userlist->add_from_sql('reviewed_by',
            "SELECT reviewed_by FROM {local_sentientia_skai_job} WHERE reviewed_by IS NOT NULL", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_skai_gap}", []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();

        $jobs = $DB->get_records('local_sentientia_skai_job', ['ownerid' => $userid],
            'timecreated DESC');
        if ($jobs) {
            $jobdata = [];
            foreach ($jobs as $j) {
                $jobdata[] = [
                    'id'            => $j->id,
                    'title'         => $j->title,
                    'sourcekind'    => $j->sourcekind,
                    'status'        => $j->status,
                    'num_extracted' => $j->num_extracted,
                    'timecreated'   => $j->timecreated,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_skillsai'),
                 get_string('privacy:export:jobs', 'local_sentientia_skillsai')],
                (object)['jobs' => $jobdata]
            );
        }

        $gaps = $DB->get_records('local_sentientia_skai_gap', ['userid' => $userid]);
        if ($gaps) {
            $gapdata = [];
            foreach ($gaps as $g) {
                $gapdata[] = [
                    'skillid'        => $g->skillid,
                    'designation'    => $g->designation,
                    'required_level' => $g->required_level,
                    'held_level'     => $g->held_level,
                    'gap_size'       => $g->gap_size,
                    'timecreated'    => $g->timecreated,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_sentientia_skillsai'),
                 get_string('privacy:export:gaps', 'local_sentientia_skillsai')],
                (object)['gaps' => $gapdata]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if (!$context instanceof \context_system) {
            return;
        }
        global $DB;
        $DB->delete_records('local_sentientia_skai_gap');
        // Jobs are kept (extraction audit trail), but de-associate the owner
        // is not possible without breaking the schema; the gap feed is the
        // per-user personal data and is removed.
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_sentientia_skai_gap', ['userid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!($userlist->get_context() instanceof \context_system)) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select('local_sentientia_skai_gap', "userid $insql", $params);
    }
}
