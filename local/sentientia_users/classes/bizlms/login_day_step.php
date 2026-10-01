<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_uniquelogins -> local_sentientia_users_logindays (ADR-032, mapping doc section 10): one row per web
 * login BizLMS counted.
 *
 * The target is one row per user per day (UNIQUE userid, logindate), and BizLMS wrote a row on every login (the
 * admin account got one each time). So the step is GROUPED on (userid, count_date): the lowest id of a group is
 * imported and every other row of it is merged into it (duplicate_login_day); the duplicates stay in the legacy
 * table. The group's map key is its lowest id.
 *
 * A row with no user or no day cannot form a key and is skipped (invalid_login_row, needs the owner's
 * acceptance). A user id with no account is kept (history; readers join {user}).
 *
 * The signed decision users.uniquelogins says whether to import at all; with "skip" every row is archived.
 *
 * Times: logindate is the source's count_date (BizLMS stored the midnight of the day); timecreated is the
 * source's timemodified, and the day itself when the row has none.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class login_day_step extends step {

    /** The only table the step writes. */
    public const TARGET = 'local_sentientia_users_logindays';

    public function key(): string {
        return 'users.logindays';
    }

    public function sourcetable(): string {
        return 'local_uniquelogins';
    }

    public function targettable(): string {
        return self::TARGET;
    }

    public function group_by(): array {
        return ['userid', 'count_date'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        if ($ctx->decision(users_importer::DECISION_LOGINS) === 'skip') {
            foreach ($rows as $row) {
                $out[] = outcome::archive((int) $row->id, users_importer::REASON_DECLINED);
            }
            return $out;
        }

        $winner = reset($rows);
        $userid = clean::id($winner->userid ?? null);
        $day = clean::time($winner->count_date ?? null);
        if ($userid === 0 || $day === 0) {
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, users_importer::REASON_INVALID_LOGIN_ROW,
                    $userid === 0 ? 'no_user' : 'no_day');
            }
            return $out;
        }

        $stamp = clean::time($winner->timemodified ?? null);
        [$source] = clean::utf8(trim((string) ($winner->type ?? '')));
        $insert = outcome::insert((int) $winner->id, self::TARGET, (object) [
            'userid' => $userid,
            'logindate' => $day,
            'source' => $source !== '' ? $ctx->text->fit($source, 20, 'source') : 'web',
            'timecreated' => $stamp > 0 ? $stamp : $day,
            'timemodified' => $stamp > 0 ? $stamp : $day,
        ]);
        if ($stamp === 0) {
            $insert->warn('derived_timestamp');
        }
        if (!$ctx->lookups->user_exists($userid)) {
            $insert->warn('user_not_found');
        }
        $out[] = $insert;
        foreach ($rows as $row) {
            if ((int) $row->id !== (int) $winner->id) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, users_importer::REASON_DUPLICATE_LOGIN_DAY);
            }
        }
        return $out;
    }
}
