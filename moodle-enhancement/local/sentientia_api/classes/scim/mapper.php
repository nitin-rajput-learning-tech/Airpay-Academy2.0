<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\scim;

defined('MOODLE_INTERNAL') || die();

/**
 * externalId <-> Moodle user mapping, scoped per SCIM client (ADR-030 Wave B).
 *
 * @package local_sentientia_api
 */
class mapper {

    /** @var string */
    public const TABLE = 'local_sentientia_api_scimmap';

    /**
     * @param int    $cliid
     * @param string $externalid
     * @return int|null
     */
    public static function userid_for(int $cliid, string $externalid): ?int {
        global $DB;
        if ($externalid === '') {
            return null;
        }
        $id = $DB->get_field(self::TABLE, 'userid', ['cliid' => $cliid, 'externalid' => $externalid]);
        return $id ? (int) $id : null;
    }

    /**
     * @param int $cliid
     * @param int $userid
     * @return string|null
     */
    public static function externalid_for(int $cliid, int $userid): ?string {
        global $DB;
        $v = $DB->get_field(self::TABLE, 'externalid', ['cliid' => $cliid, 'userid' => $userid]);
        return $v === false ? null : (string) $v;
    }

    /**
     * Bulk lookup for list responses.
     *
     * @param int   $cliid
     * @param int[] $userids
     * @return array<int,string> userid => externalid
     */
    public static function externalids_for(int $cliid, array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['cli'] = $cliid;
        $rows = $DB->get_records_select(self::TABLE, "cliid = :cli AND userid $insql", $params, '', 'userid, externalid');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->userid] = (string) $r->externalid;
        }
        return $out;
    }

    /**
     * Upsert the mapping; an empty externalId removes it.
     *
     * @param int    $cliid
     * @param int    $userid
     * @param string $externalid
     * @return void
     * @throws scim_exception 409 when the externalId already maps to another user
     */
    public static function set(int $cliid, int $userid, string $externalid): void {
        global $DB;
        $externalid = \core_text::substr(trim($externalid), 0, 191);
        if ($externalid === '') {
            self::unmap_user($cliid, $userid);
            return;
        }
        $other = self::userid_for($cliid, $externalid);
        if ($other !== null && $other !== $userid) {
            throw new scim_exception(409, 'externalId is already assigned to another user.', 'uniqueness');
        }
        $now = time();
        $existing = $DB->get_record(self::TABLE, ['cliid' => $cliid, 'userid' => $userid]);
        if ($existing) {
            if ($existing->externalid !== $externalid) {
                $existing->externalid = $externalid;
                $existing->timemodified = $now;
                $DB->update_record(self::TABLE, $existing);
            }
            return;
        }
        $DB->insert_record(self::TABLE, (object) [
            'cliid' => $cliid, 'externalid' => $externalid, 'userid' => $userid,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * @param int $cliid
     * @param int $userid
     * @return void
     */
    public static function unmap_user(int $cliid, int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cliid' => $cliid, 'userid' => $userid]);
    }

    /**
     * @param int $cliid
     * @return int
     */
    public static function count_for_client(int $cliid): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['cliid' => $cliid]);
    }
}
