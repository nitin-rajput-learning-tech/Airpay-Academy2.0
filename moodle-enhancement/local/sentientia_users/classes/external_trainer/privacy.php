<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external_trainer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy for {local_sentientia_external_trainer_profile} (ADR-036 D9; ADR-035 completeness).
 *
 * A row names two people: the external trainer (userid) and the admin who invited them (invitedby).
 *
 * Export: the trainer gets their own row (organisation, tenant, status, access dates); the inviter gets
 * when they invited someone and that account's status, without the trainer's name.
 *
 * Erasure keeps the row and removes the person from it, the plugin's signed treatment for history
 * (users.erasure_treatment = anonymise; the DPDP design: actor columns anonymised, records kept):
 *  - for the trainer, the organisation is blanked. The row itself stays because it is what marks the
 *    account as an external trainer: deleting it would lift the own-classroom limit, the names-only
 *    rosters and the expiry from an account that may still exist;
 *  - for the inviter, invitedby becomes 0.
 * Moodle's own user deletion removes the account itself; this table then only holds the trainer's id.
 *
 * Everything is held at system context.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy {

    /** @return array<string, string> column => lang string id, for the metadata collection */
    public static function metadata_fields(): array {
        return [
            'userid'       => 'privacy:metadata:field:userid',
            'organisation' => 'privacy:metadata:field:organisation',
            'invitedby'    => 'privacy:metadata:field:actor',
            'open_path'    => 'privacy:metadata:field:exttrainer_tenant',
            'validfrom'    => 'privacy:metadata:field:exttrainer_access',
            'expires_at'   => 'privacy:metadata:field:exttrainer_access',
            'status'       => 'privacy:metadata:field:exttrainer_access',
            'remindedat'   => 'privacy:metadata:field:exttrainer_access',
            'timecreated'  => 'privacy:metadata:field:time',
            'timemodified' => 'privacy:metadata:field:time',
        ];
    }

    /**
     * @param int $userid
     * @return bool whether a row names the user, as the trainer or as the inviter
     */
    public static function holds_data_for_user(int $userid): bool {
        global $DB;
        if ($userid <= 0 || !manager::table_ready()) {
            return false;
        }
        return $DB->record_exists_select(manager::TABLE, 'userid = :u1 OR invitedby = :u2',
            ['u1' => $userid, 'u2' => $userid]);
    }

    /**
     * SQL selecting a `userid` column, one statement per route (trainer, inviter).
     *
     * @return array<int, array{0: string, 1: array}>
     */
    public static function user_list_sql(): array {
        if (!manager::table_ready()) {
            return [];
        }
        return [
            ['SELECT userid FROM {' . manager::TABLE . '}', []],
            ['SELECT invitedby AS userid FROM {' . manager::TABLE . '} WHERE invitedby > 0', []],
        ];
    }

    /**
     * @param int $userid
     * @return array{own: array, invited: array}
     */
    public static function export_data(int $userid): array {
        global $DB;
        $out = ['own' => [], 'invited' => []];
        if ($userid <= 0 || !manager::table_ready()) {
            return $out;
        }
        foreach ($DB->get_records(manager::TABLE, ['userid' => $userid], 'id ASC') as $row) {
            $out['own'][] = [
                'organisation' => (string) $row->organisation,
                'tenant'       => (string) $row->open_path,
                'status'       => (string) $row->status,
                'valid_from'   => \core_privacy\local\request\transform::datetime((int) $row->validfrom),
                'access_ends'  => \core_privacy\local\request\transform::datetime((int) $row->expires_at),
                'invited_on'   => \core_privacy\local\request\transform::datetime((int) $row->timecreated),
            ];
        }
        foreach ($DB->get_records(manager::TABLE, ['invitedby' => $userid], 'id ASC') as $row) {
            $out['invited'][] = [
                'invited_on' => \core_privacy\local\request\transform::datetime((int) $row->timecreated),
                'status'     => (string) $row->status,
            ];
        }
        return $out;
    }

    /**
     * Remove these people from the table (see the class comment).
     *
     * @param int[] $userids
     * @return void
     */
    public static function anonymise_users(array $userids): void {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), fn($id) => $id > 0)));
        if (!$userids || !manager::table_ready()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'etp');
        $DB->set_field_select(manager::TABLE, 'organisation', '', "userid $insql", $params);
        $DB->set_field_select(manager::TABLE, 'invitedby', 0, "invitedby $insql", $params);
    }

    /** Remove everybody from the table (rows kept). */
    public static function anonymise_all(): void {
        global $DB;
        if (!manager::table_ready()) {
            return;
        }
        $DB->set_field_select(manager::TABLE, 'organisation', '', '1=1');
        $DB->set_field_select(manager::TABLE, 'invitedby', 0, '1=1');
    }
}
