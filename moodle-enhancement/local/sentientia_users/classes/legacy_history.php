<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads, exports and anonymises the history the BizLMS import (ADR-032, users) put in this plugin's tables.
 *
 * Four tables hold it:
 *   - local_sentientia_users_sync_runs / _sync_errors: HRMS uploads and their failed lines. An error row names a
 *     PROSPECTIVE person (the e-mail, employee code, username and name on the rejected CSV line), who may or may
 *     not be an account. Rows are tied to an account by identity (see {@see self::identity()}) as well as by the
 *     uploader's id.
 *   - local_sentientia_users_transcript: earlier training records, with the learner's id and employee id.
 *   - local_sentientia_users_logindays: one row per user per login day.
 *
 * The signed decision users.erasure_treatment = anonymise: on an erasure request the imported rows are KEPT and
 * the person is removed from them (ids set to 0, identifying text blanked or scrubbed). Login days are the one
 * exception: a row is only a (user, day) pair, and its unique key cannot survive losing the user, so it is
 * deleted, the way core's own log store deletes a user's log rows.
 *
 * This is also the reader of the transcript and of the position and domain labels the profile shows. Both
 * readers are behind default-OFF flags (db/feature_flags.php).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_history {

    /** HRMS upload runs. */
    public const RUNS = 'local_sentientia_users_sync_runs';

    /** Failed lines of the uploads. */
    public const ERRORS = 'local_sentientia_users_sync_errors';

    /** Earlier training records. */
    public const TRANSCRIPT = 'local_sentientia_users_transcript';

    /** Login days. */
    public const LOGINDAYS = 'local_sentientia_users_logindays';

    /** Position lookup. */
    public const POSITION = 'local_sentientia_users_position';

    /** Domain lookup. */
    public const DOMAIN = 'local_sentientia_users_domain';

    /** Flag: the earlier training records section of the profile. */
    public const FLAG_TRANSCRIPT = 'sentientia.users.legacy_transcript';

    /** Flag: the position and domain lines of the profile. */
    public const FLAG_POSITION_LABELS = 'sentientia.users.position_labels';

    /** What an anonymised e-mail, employee code or username becomes (the value BizLMS wrote for "none"). */
    public const PLACEHOLDER = '-';

    /** What replaces an identifier inside an error message. */
    public const REMOVED = '[removed]';

    /** Identifiers shorter than this are not scrubbed out of a message: "a" would blank half of it. */
    private const MIN_SCRUB_LENGTH = 3;

    /** Statuses the profile has a label for. */
    public const STATUSES = ['completed', 'inprogress', 'failed', 'notstarted', 'cancelled', 'unknown'];

    /** @var array<string, string> Memo of lookup labels. */
    private static array $labels = [];

    // Flags.

    /**
     * Is the earlier-training-records section on?
     *
     * @return bool
     */
    public static function transcript_enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG_TRANSCRIPT);
    }

    /**
     * Are the position and domain lines on?
     *
     * @return bool
     */
    public static function position_labels_enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG_POSITION_LABELS);
    }

    // Readers.

    /**
     * The earlier training records of one learner, newest completion first, for the profile.
     *
     * Only rows matched to the learner (userid), and only while the account is live. The caller has already
     * decided the viewer may open this profile (profile_access); the rows add nothing the profile did not show.
     *
     * @param int $userid
     * @param int $limit
     * @return array<int, array<string, mixed>> Template rows. Text is raw: Mustache escapes it.
     */
    public static function transcript_for_user(int $userid, int $limit = 100): array {
        global $DB;
        if ($userid <= 0 || !$DB->get_manager()->table_exists(self::TRANSCRIPT)) {
            return [];
        }
        $rows = $DB->get_records_sql(
            'SELECT t.*
               FROM {' . self::TRANSCRIPT . '} t
               JOIN {user} u ON u.id = t.userid AND u.deleted = 0
              WHERE t.userid = :uid
           ORDER BY CASE WHEN t.timecompleted IS NULL THEN 1 ELSE 0 END, t.timecompleted DESC, t.id DESC',
            ['uid' => $userid], 0, max(1, $limit));
        $out = [];
        foreach ($rows as $row) {
            $status = in_array($row->status, self::STATUSES, true) ? $row->status : 'unknown';
            $out[] = [
                'title' => $row->title,
                'type' => $row->training_type,
                'location' => $row->location,
                'completed' => $row->timecompleted !== null
                    ? userdate((int) $row->timecompleted, get_string('strftimedatemonthabbr', 'core_langconfig'))
                    : ($row->completion_date_raw !== '' ? $row->completion_date_raw : '-'),
                'status' => get_string('transcript_status_' . $status, 'local_sentientia_users'),
                'statusraw' => $row->status_raw,
                'score' => $row->score !== null ? format_float((float) $row->score, 2) : '',
                'hours' => $row->hours !== null ? format_float((float) $row->hours, 2) : '',
            ];
        }
        return $out;
    }

    /**
     * The name of a position, from the imported lookup; '' when there is none.
     *
     * @param int $id user.open_positionid
     * @return string
     */
    public static function position_label(int $id): string {
        return self::label(self::POSITION, $id);
    }

    /**
     * The name of a domain, from the imported lookup; '' when there is none.
     *
     * @param int $id user.open_domainid
     * @return string
     */
    public static function domain_label(int $id): string {
        return self::label(self::DOMAIN, $id);
    }

    /**
     * @param string $table
     * @param int $id
     * @return string
     */
    private static function label(string $table, int $id): string {
        global $DB;
        if ($id <= 0) {
            return '';
        }
        $key = $table . '#' . $id;
        if (!array_key_exists($key, self::$labels)) {
            self::$labels[$key] = $DB->get_manager()->table_exists($table)
                ? (string) $DB->get_field($table, 'name', ['id' => $id], IGNORE_MISSING)
                : '';
        }
        return self::$labels[$key];
    }

    /**
     * Forget the memoised labels (tests that change the lookups).
     *
     * @return void
     */
    public static function reset_labels(): void {
        self::$labels = [];
    }

    // Privacy: identity.

    /**
     * What identifies these people on a rejected CSV line or a transcript row: their e-mail address, employee
     * codes (open_employeeid and idnumber), username and name. Placeholders and very short values are left out.
     *
     * @param int[] $userids
     * @return array{emails: string[], codes: string[], usernames: string[], names: string[]}
     */
    public static function identity(array $userids): array {
        global $DB;
        $out = ['emails' => [], 'codes' => [], 'usernames' => [], 'names' => []];
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), static fn(int $id): bool => $id > 0)));
        if (!$userids) {
            return $out;
        }
        $hasemployee = array_key_exists('open_employeeid', $DB->get_columns('user'));
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'idn');
        $rows = $DB->get_records_select('user', "id {$insql}", $params, '',
            'id, email, username, idnumber, firstname, lastname' . ($hasemployee ? ', open_employeeid' : ''));
        foreach ($rows as $row) {
            self::collect($out['emails'], $row->email, true);
            self::collect($out['usernames'], $row->username, true);
            self::collect($out['codes'], $row->idnumber, true);
            if ($hasemployee) {
                self::collect($out['codes'], $row->open_employeeid, true);
            }
            self::collect($out['names'], $row->firstname, false);
            self::collect($out['names'], $row->lastname, false);
        }
        foreach ($out as $kind => $values) {
            $out[$kind] = array_values(array_unique($values));
        }
        return $out;
    }

    /**
     * @param string[] $into
     * @param mixed $value
     * @param bool $lowercase
     * @return void
     */
    private static function collect(array &$into, mixed $value, bool $lowercase): void {
        $text = trim((string) $value);
        if ($text === '' || $text === self::PLACEHOLDER) {
            return;
        }
        $into[] = $lowercase ? \core_text::strtolower($text) : $text;
    }

    /**
     * WHERE fragment matching the sync-error rows an identity names.
     *
     * @param array{emails: string[], codes: string[], usernames: string[], names: string[]} $identity
     * @return array{0: string, 1: array} ['' when the identity names nothing]
     */
    private static function error_identity_sql(array $identity): array {
        global $DB;
        $conditions = [];
        $params = [];
        $n = 0;
        foreach (['emails' => 'email', 'codes' => 'employee_code', 'usernames' => 'username'] as $kind => $column) {
            foreach ($identity[$kind] as $value) {
                $name = 'blmid' . $n++;
                $conditions[] = $DB->sql_equal($column, ':' . $name, false);
                $params[$name] = $value;
            }
        }
        return $conditions ? ['(' . implode(' OR ', $conditions) . ')', $params] : ['', []];
    }

    // Privacy: does the user appear?

    /**
     * Does any imported or native row of these tables name the user?
     *
     * @param int $userid
     * @return bool
     */
    public static function holds_data_for_user(int $userid): bool {
        global $DB;
        if ($userid <= 0) {
            return false;
        }
        if ($DB->record_exists(self::RUNS, ['usercreated' => $userid])
                || $DB->record_exists(self::ERRORS, ['modified_by' => $userid])
                || $DB->record_exists(self::LOGINDAYS, ['userid' => $userid])) {
            return true;
        }
        $identity = self::identity([$userid]);
        [$sql, $params] = self::error_identity_sql($identity);
        if ($sql !== '' && $DB->record_exists_select(self::ERRORS, $sql, $params)) {
            return true;
        }
        if ($DB->record_exists_select(self::TRANSCRIPT, 'userid = :u OR usercreated = :c OR usermodified = :m',
                ['u' => $userid, 'c' => $userid, 'm' => $userid])) {
            return true;
        }
        return self::transcript_by_code_exists($identity['codes']);
    }

    /**
     * @param string[] $codes
     * @return bool
     */
    private static function transcript_by_code_exists(array $codes): bool {
        global $DB;
        foreach ($codes as $code) {
            if ($DB->record_exists_select(self::TRANSCRIPT, 'userid = 0 AND ' . $DB->sql_equal('employee_id', ':code', false),
                    ['code' => $code])) {
                return true;
            }
        }
        return false;
    }

    // Privacy: export.

    /**
     * Everything the tables hold about a user, as plain arrays for the privacy writer.
     *
     * An uploader is given the fact that their uploads produced error rows, not the rows: those name other
     * people. A person a rejected line was about is given the line.
     *
     * @param int $userid
     * @return array<string, array> Section name => rows; empty sections are left out.
     */
    public static function export_data(int $userid): array {
        global $DB;
        $out = [];

        $runs = [];
        foreach ($DB->get_records(self::RUNS, ['usercreated' => $userid], 'timecreated ASC, id ASC') as $run) {
            $runs[] = [
                'id' => (int) $run->id,
                'filename' => $run->filename,
                'source' => $run->source,
                'status' => $run->status,
                'inserted' => (int) $run->insertedcount,
                'updated' => (int) $run->updatedcount,
                'errors' => (int) $run->errorcount,
                'warnings' => (int) $run->warningcount,
                'time' => userdate((int) $run->timecreated),
            ];
        }
        if ($runs) {
            $out['hrms_uploads'] = $runs;
        }

        $uploaded = $DB->count_records(self::ERRORS, ['modified_by' => $userid]);
        if ($uploaded > 0) {
            $out['hrms_upload_error_rows'] = ['rows_from_your_uploads' => $uploaded];
        }

        $identity = self::identity([$userid]);
        [$sql, $params] = self::error_identity_sql($identity);
        if ($sql !== '') {
            $about = [];
            foreach ($DB->get_records_select(self::ERRORS, $sql, $params, 'id ASC') as $row) {
                $about[] = [
                    'run' => (int) $row->runid,
                    'email' => $row->email,
                    'employee_code' => $row->employee_code,
                    'username' => $row->username,
                    'name' => trim($row->firstname . ' ' . $row->lastname),
                    'message' => $row->error_message,
                    'severity' => $row->severity,
                    'time' => userdate((int) $row->timecreated),
                ];
            }
            if ($about) {
                $out['hrms_rejected_lines_about_you'] = $about;
            }
        }

        $transcript = [];
        foreach (self::transcript_rows_for($userid, $identity['codes']) as $row) {
            $transcript[] = [
                'employee_id' => $row->employee_id,
                'name' => $row->learner_name,
                'title' => $row->title,
                'type' => $row->training_type,
                'location' => $row->location,
                'course' => (int) $row->courseid,
                'status' => $row->status,
                'status_as_loaded' => $row->status_raw,
                'completed' => $row->timecompleted !== null ? userdate((int) $row->timecompleted) : $row->completion_date_raw,
                'score' => $row->score,
                'hours' => $row->hours,
                'imported_from' => $row->source,
            ];
        }
        if ($transcript) {
            $out['earlier_training_records'] = $transcript;
        }

        $days = $DB->get_records(self::LOGINDAYS, ['userid' => $userid], 'logindate ASC', 'id, logindate');
        if ($days) {
            $out['login_days'] = array_map(
                static fn(\stdClass $d): string => userdate((int) $d->logindate, get_string('strftimedate', 'core_langconfig')),
                array_values($days));
        }
        return $out;
    }

    /**
     * Transcript rows of a user: matched by id, or an unmatched row (userid 0) carrying the user's employee code.
     *
     * @param int $userid
     * @param string[] $codes
     * @return \stdClass[]
     */
    private static function transcript_rows_for(int $userid, array $codes): array {
        global $DB;
        $rows = $DB->get_records(self::TRANSCRIPT, ['userid' => $userid], 'id ASC');
        foreach ($codes as $code) {
            $more = $DB->get_records_select(self::TRANSCRIPT, 'userid = 0 AND ' . $DB->sql_equal('employee_id', ':code', false),
                ['code' => $code], 'id ASC');
            foreach ($more as $id => $row) {
                $rows[$id] = $row;
            }
        }
        ksort($rows);
        return $rows;
    }

    // Privacy: erasure.

    /**
     * Remove these people from the imported rows, keeping the rows (signed decision users.erasure_treatment).
     *
     * - runs they uploaded: usercreated 0 (the counts stay);
     * - error rows they uploaded: modified_by 0;
     * - error rows about them (matched by e-mail, employee code or username): the identity columns are blanked and
     *   every identifier is scrubbed out of the message;
     * - transcript rows: userid, employee id and name cleared, and any creator or modifier of theirs set to 0;
     * - login days: deleted (see the class description).
     *
     * @param int[] $userids
     * @return void
     */
    public static function anonymise_users(array $userids): void {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), static fn(int $id): bool => $id > 0)));
        if (!$userids) {
            return;
        }
        // Identity first: it is read from the user rows the request is about, and the later steps do not change them.
        $identity = self::identity($userids);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'anu');
        $DB->set_field_select(self::RUNS, 'usercreated', 0, "usercreated {$insql}", $params);
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'anm');
        $DB->set_field_select(self::ERRORS, 'modified_by', 0, "modified_by {$insql}", $params);

        self::anonymise_error_rows($identity);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ant');
        $DB->execute('UPDATE {' . self::TRANSCRIPT . "} SET userid = 0, employee_id = '', learner_name = ''"
            . " WHERE userid {$insql}", $params);
        foreach ($identity['codes'] as $i => $code) {
            $DB->execute('UPDATE {' . self::TRANSCRIPT . "} SET employee_id = '', learner_name = ''"
                . ' WHERE userid = 0 AND ' . $DB->sql_equal('employee_id', ':code', false), ['code' => $code]);
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'anc');
        $DB->set_field_select(self::TRANSCRIPT, 'usercreated', 0, "usercreated {$insql}", $params);
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'ano');
        $DB->set_field_select(self::TRANSCRIPT, 'usermodified', 0, "usermodified {$insql}", $params);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'and');
        $DB->delete_records_select(self::LOGINDAYS, "userid {$insql}", $params);
    }

    /**
     * Anonymise every row (the whole-context erasure): the same treatment as for each person, without needing to
     * know who they are. Error messages are replaced, since they cannot be scrubbed without an identity.
     *
     * @return void
     */
    public static function anonymise_all(): void {
        global $DB;
        $DB->execute('UPDATE {' . self::RUNS . '} SET usercreated = 0');
        $DB->execute('UPDATE {' . self::ERRORS . '} SET modified_by = 0, email = :p1, employee_code = :p2, username = :p3,'
            . " firstname = '', lastname = '', error_message = :msg",
            ['p1' => self::PLACEHOLDER, 'p2' => self::PLACEHOLDER, 'p3' => self::PLACEHOLDER, 'msg' => self::REMOVED]);
        $DB->execute('UPDATE {' . self::TRANSCRIPT . "} SET userid = 0, employee_id = '', learner_name = '',"
            . ' usercreated = 0, usermodified = 0');
        $DB->delete_records(self::LOGINDAYS);
    }

    /**
     * Blank the identity columns of the error rows an identity names, and scrub its identifiers out of the message.
     *
     * @param array{emails: string[], codes: string[], usernames: string[], names: string[]} $identity
     * @return void
     */
    private static function anonymise_error_rows(array $identity): void {
        global $DB;
        [$sql, $params] = self::error_identity_sql($identity);
        if ($sql === '') {
            return;
        }
        $needles = array_merge($identity['emails'], $identity['codes'], $identity['usernames'], $identity['names']);
        foreach ($DB->get_records_select(self::ERRORS, $sql, $params, 'id ASC', 'id, error_message') as $row) {
            $DB->update_record(self::ERRORS, (object) [
                'id' => $row->id,
                'email' => self::PLACEHOLDER,
                'employee_code' => self::PLACEHOLDER,
                'username' => self::PLACEHOLDER,
                'firstname' => '',
                'lastname' => '',
                'error_message' => self::scrub((string) $row->error_message, $needles),
            ]);
        }
    }

    /**
     * Replace every identifier in a text (case-insensitively) by a marker.
     *
     * @param string $text
     * @param string[] $needles
     * @return string
     */
    public static function scrub(string $text, array $needles): string {
        foreach ($needles as $needle) {
            if (\core_text::strlen($needle) >= self::MIN_SCRUB_LENGTH) {
                $text = str_ireplace($needle, self::REMOVED, $text);
            }
        }
        return $text;
    }

    /**
     * User ids that appear in these tables, for the privacy user list.
     *
     * @return array<string, string> Column alias => SQL returning userid.
     */
    public static function user_list_sql(): array {
        return [
            'runs' => 'SELECT usercreated AS userid FROM {' . self::RUNS . '} WHERE usercreated > 0',
            'uploader' => 'SELECT modified_by AS userid FROM {' . self::ERRORS . '} WHERE modified_by > 0',
            'transcript' => 'SELECT userid AS userid FROM {' . self::TRANSCRIPT . '} WHERE userid > 0',
            'transcript_creator' => 'SELECT usercreated AS userid FROM {' . self::TRANSCRIPT . '} WHERE usercreated > 0',
            'transcript_modifier' => 'SELECT usermodified AS userid FROM {' . self::TRANSCRIPT . '} WHERE usermodified > 0',
            'logins' => 'SELECT userid AS userid FROM {' . self::LOGINDAYS . '} WHERE userid > 0',
        ];
    }
}
