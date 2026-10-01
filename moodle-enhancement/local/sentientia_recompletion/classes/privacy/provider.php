<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_sentientia_recompletion\archive_privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_sentientia_recompletion.
 *
 * Tables, and what each erasure does to them (2026-09-24):
 *
 *   local_sentientia_recompletion_rules
 *       Rule definitions. No user column. Untouched.
 *
 *   local_sentientia_recompletion_history
 *       The append-only reset audit log, and a COMPLIANCE record: a reset
 *       deletes the course_completions row (and, per rule, the grades and
 *       quiz attempts), so for every cycle before the current one this row
 *       (previous_timecompleted) is the only surviving evidence that the
 *       person completed the course - the annual AML / KYC / POSH
 *       attestations an auditor asks for.
 *       - Subject column `userid`:
 *           core erasure (delete_data_for_user) redacts it to 0, as it always
 *           has; the row survives, attributable to nobody.
 *           DPDP erasure (anonymise_data_for_user) KEEPS it: the flow promises
 *           to retain compliance records keyed to the user row it anonymises
 *           in place. Redacting to 0 there, which is what happened while this
 *           provider had no anonymise hook, detached every earlier cycle's
 *           completion from the anonymised person and pooled it with every
 *           other erased user's rows under 0.
 *       - Actor column `reset_by_userid` (the admin who pressed reset on
 *           somebody else's completion; NULL = the scheduled task):
 *           anonymised to 0 by both erasures. Never used to delete a row,
 *           because the row is the other person's record.
 *       No free-text column: `reason` is a cron|manual|bulk code.
 *
 *   local_sentientia_recompletion_archive  (ADR-032, 2026-09-30)
 *       What a reset deleted (course completion, criteria, activity
 *       completions, quiz attempts and grades, SCORM tracking, LTI grades,
 *       questionnaire answers), copied by the BizLMS import and by the
 *       engine, one row each with the whole source row as JSON in payload.
 *       It is the same kind of compliance record as the history row.
 *       - Subject column `userid`, and the payload's own userid:
 *           core erasure redacts the column to 0 and scrubs the payload
 *           (userid and the other people the row names to 0; what was
 *           written about the learner emptied: the free text of a
 *           questionnaire answer, the feedback and information note of a
 *           gradebook grade, the text typed into a SCORM package); the row
 *           survives, attributable to nobody.
 *           DPDP erasure KEEPS the subject's rows as they are, for the reason
 *           given for the history row.
 *       - Actor: an administrator who overrode SOMEBODY ELSE's activity
 *           completion is named in that row's payload (overrideby), and the
 *           grader who last changed a gradebook grade in `usermodified`
 *           (archive_privacy::ACTOR_KEYS). Both erasures change it to 0 and
 *           leave the row. An export hands the learner their rows without
 *           that id: it is the other person's data.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /** The reset audit log. */
    private const TABLE_HISTORY = 'local_sentientia_recompletion_history';

    /** The evidence the resets deleted. */
    private const TABLE_ARCHIVE = 'local_sentientia_recompletion_archive';

    /** Rows of a payload scan handled per pass. */
    private const SCAN_LIMIT = 500;

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_sentientia_recompletion_rules', [],
            'privacy:metadata:local_sentientia_recompletion_rules');
        $collection->add_database_table(self::TABLE_HISTORY, [
            'userid'   => 'privacy:metadata:local_sentientia_recompletion_history:userid',
            'courseid' => 'privacy:metadata:local_sentientia_recompletion_history:courseid',
            'reason'   => 'privacy:metadata:local_sentientia_recompletion_history:reason',
            'reset_by_userid'
                => 'privacy:metadata:local_sentientia_recompletion_history:reset_by_userid',
            'previous_timecompleted'
                => 'privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted',
            'timecreated'
                => 'privacy:metadata:local_sentientia_recompletion_history:timecreated',
            'source'
                => 'privacy:metadata:local_sentientia_recompletion_history:source',
        ], 'privacy:metadata:local_sentientia_recompletion_history');
        $collection->add_database_table(self::TABLE_ARCHIVE, [
            'userid'     => 'privacy:metadata:local_sentientia_recompletion_archive:userid',
            'courseid'   => 'privacy:metadata:local_sentientia_recompletion_archive:courseid',
            'itemtype'   => 'privacy:metadata:local_sentientia_recompletion_archive:itemtype',
            'cmid'       => 'privacy:metadata:local_sentientia_recompletion_archive:cmid',
            'instanceid' => 'privacy:metadata:local_sentientia_recompletion_archive:instanceid',
            'itemkey'    => 'privacy:metadata:local_sentientia_recompletion_archive:itemkey',
            'state'      => 'privacy:metadata:local_sentientia_recompletion_archive:state',
            'grade'      => 'privacy:metadata:local_sentientia_recompletion_archive:grade',
            'timeevent'  => 'privacy:metadata:local_sentientia_recompletion_archive:timeevent',
            'payload'    => 'privacy:metadata:local_sentientia_recompletion_archive:payload',
            'timecreated' => 'privacy:metadata:local_sentientia_recompletion_archive:timecreated',
        ], 'privacy:metadata:local_sentientia_recompletion_archive');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        // An admin who only ever reset other people's completions holds data
        // here too (the actor column), and must be reachable by an erasure.
        if ($DB->record_exists(self::TABLE_HISTORY, ['userid' => $userid])
                || $DB->record_exists(self::TABLE_HISTORY, ['reset_by_userid' => $userid])
                || $DB->record_exists(self::TABLE_ARCHIVE, ['userid' => $userid])
                || self::actor_rows_exist($userid)) {
            $list->add_system_context();
        }
        return $list;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        // userid 0 is a row an earlier erasure redacted, not a user.
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_recompletion_history} WHERE userid > 0", []);
        $userlist->add_from_sql('reset_by_userid',
            "SELECT reset_by_userid FROM {local_sentientia_recompletion_history}
              WHERE reset_by_userid > 0", []);
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_sentientia_recompletion_archive} WHERE userid > 0", []);
        // The administrators named in a payload (overrideby) are not in a column: read them out of the JSON.
        $userlist->add_users(self::all_actors());
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        $context = \context_system::instance();
        $root = get_string('pluginname', 'local_sentientia_recompletion');

        $rows = $DB->get_records(self::TABLE_HISTORY, ['userid' => $userid], 'timecreated DESC');
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'course_id'    => $r->courseid,
                'reason'       => $r->reason,
                'reset_at'     => userdate($r->timecreated),
                'previous_completion' => $r->previous_timecompleted
                    ? userdate($r->previous_timecompleted) : null,
                'dryrun'       => (bool) $r->dryrun,
            ];
        }
        if (!empty($out)) {
            writer::with_context($context)->export_data([$root],
                (object) ['recompletion_history' => $out]);
        }

        // Resets this person performed on other people's completions. The
        // other person's id is deliberately not exported: it is their data.
        $performed = $DB->get_records(self::TABLE_HISTORY, ['reset_by_userid' => $userid],
            'timecreated DESC', 'id, courseid, reason, timecreated');
        $acts = [];
        foreach ($performed as $r) {
            $acts[] = [
                'course_id' => $r->courseid,
                'reason'    => $r->reason,
                'reset_at'  => userdate($r->timecreated),
            ];
        }
        if (!empty($acts)) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:export:resets_performed', 'local_sentientia_recompletion')],
                (object) ['resets_performed' => $acts]);
        }

        // ADR-032: the evidence the resets deleted. The payload is the source row; it is exported decoded, as
        // the person's own data, exactly as archived (their own answers included) - except the id of another
        // person it names (the overriding administrator, the grader), which is that person's data.
        $evidence = [];
        $archive = $DB->get_recordset(self::TABLE_ARCHIVE, ['userid' => $userid], 'timecreated DESC, id DESC');
        foreach ($archive as $r) {
            $evidence[] = [
                'course_id'   => $r->courseid,
                'item_type'   => $r->itemtype,
                'state'       => $r->state,
                'grade'       => $r->grade,
                'happened_at' => $r->timeevent ? userdate($r->timeevent) : null,
                'archived_at' => userdate($r->timecreated),
                'data'        => archive_privacy::for_export((string) $r->payload, (string) $r->itemtype),
            ];
        }
        $archive->close();
        if (!empty($evidence)) {
            writer::with_context($context)->export_data(
                [$root, get_string('privacy:export:evidence', 'local_sentientia_recompletion')],
                (object) ['evidence' => $evidence]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        // Compliance: history is required audit. Redact only.
        $DB->set_field(self::TABLE_HISTORY, 'userid', 0, []);
        // NULL means "the scheduled task", which is nobody: leave it NULL.
        $DB->set_field_select(self::TABLE_HISTORY, 'reset_by_userid', 0,
            'reset_by_userid IS NOT NULL', []);
        // The evidence rows stay; nobody is named in them any more.
        self::scrub_archive('1 = 1', []);
        $DB->set_field(self::TABLE_ARCHIVE, 'userid', 0, []);
    }

    /**
     * Core's erasure (tool_dataprivacy). Redacts rather than deletes: see the
     * class comment.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::redact_for_user((int) $contextlist->get_user()->id);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $u) {
            self::redact_for_user((int) $u);
        }
    }

    /**
     * Sentientia DPDP erasure (local_sentientia_privacy\privacy_manager): erase
     * this user's personal data but KEEP the learning/compliance records that
     * flow promises to retain, still keyed to the user row it anonymises in
     * place. Called instead of delete_data_for_user() when present; core's
     * privacy API never calls it, so delete_data_for_user() stays the full
     * erasure.
     *
     * The subject's own reset history is kept exactly as it is (see the class
     * comment for why it is a compliance record); only the actor column, where
     * the subject reset someone else's completion, is anonymised.
     */
    public static function anonymise_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!self::has_system_context($contextlist)) {
            return;
        }
        $userid = (int) $contextlist->get_user()->id;
        $DB->set_field(self::TABLE_HISTORY, 'reset_by_userid', 0, ['reset_by_userid' => $userid]);
        // The subject's evidence rows are kept exactly as they are; only an administrator named in a payload
        // (overrideby) is anonymised.
        self::scrub_actor_rows($userid);
    }

    private static function redact_for_user(int $userid): void {
        global $DB;
        // Redact userid (= 0 = "(redacted)" on history.php), preserve courseid
        // + reason + reset_at for compliance retention.
        $DB->set_field(self::TABLE_HISTORY, 'userid', 0, ['userid' => $userid]);
        // Actor column: anonymise, never delete - the row is someone else's.
        $DB->set_field(self::TABLE_HISTORY, 'reset_by_userid', 0, ['reset_by_userid' => $userid]);
        // The evidence the person's resets deleted: the rows stay, unattributable, with the person and what they
        // typed taken out of the payload. Then the rows of other people that name this person as the actor.
        self::scrub_archive('userid = :pvuser', ['pvuser' => $userid]);
        $DB->set_field(self::TABLE_ARCHIVE, 'userid', 0, ['userid' => $userid]);
        self::scrub_actor_rows($userid);
    }

    /**
     * Take the person and the free text out of the payload of the archive rows a select matches.
     *
     * @param string $select WHERE fragment over the archive table.
     * @param array $params
     * @return void
     */
    private static function scrub_archive(string $select, array $params): void {
        global $DB;
        $after = 0;
        do {
            $rows = $DB->get_records_select(self::TABLE_ARCHIVE, "($select) AND id > :pvafter", $params + ['pvafter' => $after],
                'id ASC', 'id, itemtype, payload', 0, self::SCAN_LIMIT);
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $scrubbed = archive_privacy::scrub_subject((string) $row->payload, (string) $row->itemtype);
                if ($scrubbed !== (string) $row->payload) {
                    $DB->set_field(self::TABLE_ARCHIVE, 'payload', $scrubbed, ['id' => $row->id]);
                }
            }
        } while (count($rows) === self::SCAN_LIMIT);
    }

    /**
     * Change the overriding administrator of every activity completion this person overrode to 0.
     *
     * @param int $userid
     * @return void
     */
    private static function scrub_actor_rows(int $userid): void {
        global $DB;
        [$select, $params] = self::actor_select($userid);
        $after = 0;
        do {
            $rows = $DB->get_records_select(self::TABLE_ARCHIVE, "($select) AND id > :pvafter", $params + ['pvafter' => $after],
                'id ASC', 'id, itemtype, payload', 0, self::SCAN_LIMIT);
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $scrubbed = archive_privacy::scrub_actor((string) $row->payload, $userid, (string) $row->itemtype);
                if ($scrubbed !== null) {
                    $DB->set_field(self::TABLE_ARCHIVE, 'payload', $scrubbed, ['id' => $row->id]);
                }
            }
        } while (count($rows) === self::SCAN_LIMIT);
    }

    /**
     * Does any archive row name this person as the administrator who overrode it?
     *
     * @param int $userid
     * @return bool
     */
    private static function actor_rows_exist(int $userid): bool {
        global $DB;
        [$select, $params] = self::actor_select($userid);
        $after = 0;
        do {
            $rows = $DB->get_records_select(self::TABLE_ARCHIVE, "($select) AND id > :pvafter", $params + ['pvafter' => $after],
                'id ASC', 'id, itemtype, payload', 0, self::SCAN_LIMIT);
            foreach ($rows as $row) {
                $after = (int) $row->id;
                if (archive_privacy::scrub_actor((string) $row->payload, $userid, (string) $row->itemtype) !== null) {
                    return true;
                }
            }
        } while (count($rows) === self::SCAN_LIMIT);
        return false;
    }

    /**
     * Every other person an archive payload names as an actor: the administrator who overrode an activity
     * completion, the grader of a gradebook grade.
     *
     * @return int[]
     */
    private static function all_actors(): array {
        global $DB;
        $found = [];
        foreach (archive_privacy::ACTOR_KEYS as $type => $key) {
            $after = 0;
            do {
                $rows = $DB->get_records_select(self::TABLE_ARCHIVE,
                    'itemtype = :pvtype AND ' . $DB->sql_like('payload', ':pvlike', true) . ' AND id > :pvafter',
                    ['pvtype' => $type, 'pvlike' => '%"' . $key . '":%', 'pvafter' => $after],
                    'id ASC', 'id, payload', 0, self::SCAN_LIMIT);
                foreach ($rows as $row) {
                    $after = (int) $row->id;
                    foreach (archive_privacy::actors((string) $row->payload, $type) as $actor) {
                        $found[$actor] = $actor;
                    }
                }
            } while (count($rows) === self::SCAN_LIMIT);
        }
        return array_values($found);
    }

    /**
     * The WHERE fragment that selects the archive rows that might name a person as an actor (the overrider of an
     * activity completion, the grader of a gradebook grade). The rows it selects are decoded and compared
     * exactly; the patterns only keep the scan small.
     *
     * @param int $userid
     * @return array{0: string, 1: array}
     */
    private static function actor_select(int $userid): array {
        global $DB;
        $branches = [];
        $params = [];
        $n = 0;
        foreach (archive_privacy::ACTOR_KEYS as $type => $key) {
            $likes = [];
            foreach (archive_privacy::actor_patterns($userid, $key) as $pattern) {
                $likes[] = $DB->sql_like('payload', ':pvp' . $n, true);
                $params['pvp' . $n] = $pattern;
                $n++;
            }
            $params['pvt' . $n] = $type;
            $branches[] = '(itemtype = :pvt' . $n . ' AND (' . implode(' OR ', $likes) . '))';
            $n++;
        }
        return ['(' . implode(' OR ', $branches) . ')', $params];
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $c) {
            if ($c->contextlevel == CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }
}
