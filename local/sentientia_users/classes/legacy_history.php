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
 * exception: a row is only a (user, day) pair, and its unique key (userid, logindate) cannot survive losing the
 * user, so it is deleted, the way core's own log store deletes a user's log rows. That exception is NOT covered
 * by users.erasure_treatment (which names the transcript and sync-error rows); it waits for the owner's written
 * decision users.logindays_erasure, and deleting is the builder's default until then.
 *
 * WHICH ROWS ARE A PERSON'S. A row that carries no account id (an error line, an unmatched transcript row) is a
 * person's when it names them by e-mail, username, or an employee code. An e-mail or username names one account.
 * An employee code can name more than one (open_employeeid of one person, idnumber of another), and the importer
 * left exactly those rows unmatched; claiming them for every holder would export one person's records to
 * another and let one person's erasure cut the other's link. So a code claims a row only when no OTHER live
 * account holds it ({@see self::claimable_codes()}); for a request that names several people, when every live
 * holder is one of them.
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
     * 'codes' is every code they hold (used to scrub messages); 'claimcodes' is the subset that may CLAIM a row
     * (see the class description): codes no other live account holds.
     *
     * @param int[] $userids
     * @return array{emails: string[], codes: string[], usernames: string[], names: string[], claimcodes: string[]}
     */
    public static function identity(array $userids): array {
        global $DB;
        $out = ['emails' => [], 'codes' => [], 'usernames' => [], 'names' => [], 'claimcodes' => []];
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
        $out['claimcodes'] = self::claimable_codes($userids, $out['codes']);
        return $out;
    }

    /**
     * The codes (lower-cased) that no live account OUTSIDE this set holds, in either idnumber or open_employeeid.
     *
     * @param int[] $userids The people the request is about.
     * @param string[] $codes Their codes, lower-cased and trimmed.
     * @return string[]
     */
    private static function claimable_codes(array $userids, array $codes): array {
        global $DB;
        if (!$codes || !$userids) {
            return [];
        }
        $hasemployee = array_key_exists('open_employeeid', $DB->get_columns('user'));
        $fields = 'id, idnumber' . ($hasemployee ? ', open_employeeid' : '');
        [$notin, $notparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'clx', false);
        $held = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'cla');
            $match = "LOWER(idnumber) {$in}";
            if ($hasemployee) {
                [$in2, $params2] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'clb');
                $match = "({$match} OR LOWER(open_employeeid) {$in2})";
                $params += $params2;
            }
            $others = $DB->get_records_select('user', "deleted = 0 AND id {$notin} AND {$match}",
                $params + $notparams, '', $fields);
            foreach ($others as $row) {
                self::collect($held, $row->idnumber, true);
                if ($hasemployee) {
                    self::collect($held, $row->open_employeeid, true);
                }
            }
        }
        $held = array_flip($held);
        return array_values(array_filter($codes, static fn(string $code): bool => !isset($held[$code])));
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
     * @param array{emails: string[], codes: string[], usernames: string[], names: string[], claimcodes: string[]} $identity
     * @return array{0: string, 1: array} ['' when the identity names nothing]
     */
    private static function error_identity_sql(array $identity): array {
        global $DB;
        $conditions = [];
        $params = [];
        $n = 0;
        // Only a code no other live account holds may claim a line (see the class description).
        foreach (['emails' => 'email', 'claimcodes' => 'employee_code', 'usernames' => 'username'] as $kind => $column) {
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
        return self::transcript_by_code_exists($identity['claimcodes']);
    }

    /**
     * Is there an unmatched transcript row carrying one of these (claiming) codes?
     *
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
        foreach (self::transcript_rows_for($userid, $identity['claimcodes']) as $row) {
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

        // Rows about somebody else that this person entered or last changed: they are told the fact, not given the
        // rows (the same rule as for an uploader's error rows).
        $entered = [
            'records_you_created' => $DB->count_records_select(self::TRANSCRIPT, 'usercreated = :c AND userid <> :u',
                ['c' => $userid, 'u' => $userid]),
            'records_you_last_changed' => $DB->count_records_select(self::TRANSCRIPT, 'usermodified = :m AND userid <> :v',
                ['m' => $userid, 'v' => $userid]),
        ];
        if (array_sum($entered) > 0) {
            $out['earlier_training_records_you_entered'] = $entered;
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
     * - login days: deleted (see the class description: the owner's decision users.logindays_erasure is pending).
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
        // An unmatched row is theirs only through a code nobody else holds (see the class description).
        foreach ($identity['claimcodes'] as $code) {
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
     * @param array{emails: string[], codes: string[], usernames: string[], names: string[], claimcodes: string[]} $identity
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
     * Queries that return the ids of the people these tables hold data about, for the privacy user list.
     *
     * Together they select exactly the people holds_data_for_user() is true for, by the same two routes: the id a
     * row carries, and the identity a row names (e-mail, username, a claiming employee code; see the class
     * description). Each is wrapped by core as "JOIN (sql) target ON u.id = target.userid", so each selects one
     * column named userid, and its aliases are local to it. No query reuses a named parameter.
     *
     * Portable SQL only: moodle_database has no sql_lower(); case-insensitive equality is sql_equal(), which
     * accepts a column as the comparand.
     *
     * @return array<string, array{0: string, 1: array}> Key => [SQL returning userid, named parameters].
     */
    public static function user_list_sql(): array {
        global $DB;
        $list = [];
        foreach ([
            'runs' => 'SELECT usercreated AS userid FROM {' . self::RUNS . '} WHERE usercreated > 0',
            'uploader' => 'SELECT modified_by AS userid FROM {' . self::ERRORS . '} WHERE modified_by > 0',
            'transcript' => 'SELECT userid AS userid FROM {' . self::TRANSCRIPT . '} WHERE userid > 0',
            'transcript_creator' => 'SELECT usercreated AS userid FROM {' . self::TRANSCRIPT . '} WHERE usercreated > 0',
            'transcript_modifier' => 'SELECT usermodified AS userid FROM {' . self::TRANSCRIPT . '} WHERE usermodified > 0',
            'logins' => 'SELECT userid AS userid FROM {' . self::LOGINDAYS . '} WHERE userid > 0',
        ] as $key => $sql) {
            $list[$key] = [$sql, []];
        }

        $errors = '{' . self::ERRORS . '}';
        $transcript = '{' . self::TRANSCRIPT . '}';

        // A rejected CSV line names a person by e-mail address or username, not by id.
        $list['error_email'] = [
            "SELECT u.id AS userid FROM {user} u JOIN {$errors} e ON " . $DB->sql_equal('e.email', 'u.email', false)
            . ' WHERE u.deleted = 0 AND u.email <> :eem1 AND u.email <> :eem2',
            ['eem1' => '', 'eem2' => self::PLACEHOLDER]];
        $list['error_username'] = [
            "SELECT u.id AS userid FROM {user} u JOIN {$errors} e ON " . $DB->sql_equal('e.username', 'u.username', false)
            . ' WHERE u.deleted = 0 AND u.username <> :eus1 AND u.username <> :eus2',
            ['eus1' => '', 'eus2' => self::PLACEHOLDER]];

        // ...or by employee code, which claims a line only when no other live account holds the code.
        [$match, $unshared] = self::claiming_code_sql('e.employee_code');
        $list['error_code'] = [
            "SELECT u.id AS userid FROM {user} u JOIN {$errors} e ON e.employee_code <> :eco1 AND e.employee_code <> :eco2"
            . " AND {$match} WHERE u.deleted = 0 AND {$unshared}",
            ['eco1' => '', 'eco2' => self::PLACEHOLDER]];

        // A transcript row with no account id is the person's whose (sole) employee code it carries.
        [$match, $unshared] = self::claiming_code_sql('t.employee_id');
        $list['transcript_code'] = [
            "SELECT u.id AS userid FROM {user} u JOIN {$transcript} t ON t.userid = :tuz AND t.employee_id <> :tec1"
            . " AND t.employee_id <> :tec2 AND {$match} WHERE u.deleted = 0 AND {$unshared}",
            ['tuz' => 0, 'tec1' => '', 'tec2' => self::PLACEHOLDER]];
        return $list;
    }

    /**
     * SQL fragments for "the live account u holds this code, and no other live account does".
     *
     * @param string $codecolumn Qualified column holding the code on the row, for example t.employee_id.
     * @return array{0: string, 1: string} [condition on u (for a JOIN ... ON), NOT EXISTS on every other account]
     */
    private static function claiming_code_sql(string $codecolumn): array {
        global $DB;
        $hasemployee = array_key_exists('open_employeeid', $DB->get_columns('user'));
        $holds = static function (string $alias) use ($DB, $hasemployee, $codecolumn): string {
            $sql = $DB->sql_equal("{$alias}.idnumber", $codecolumn, false);
            if ($hasemployee) {
                $sql .= ' OR ' . $DB->sql_equal("{$alias}.open_employeeid", $codecolumn, false);
            }
            return '(' . $sql . ')';
        };
        return [
            $holds('u'),
            'NOT EXISTS (SELECT 1 FROM {user} o WHERE o.deleted = 0 AND o.id <> u.id AND ' . $holds('o') . ')',
        ];
    }
}
