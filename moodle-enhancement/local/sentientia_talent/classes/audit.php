<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_talent;

defined('MOODLE_INTERNAL') || die();

/**
 * Append-only audit log for HR-sensitive talent mutations.
 *
 * Succession nominations, opportunity edits, and career-path changes are
 * exactly the kind of action a compliance reviewer must be able to trace
 * ("who nominated whom for the CFO role, and when?"). Every write in
 * talent_manager funnels through {@see audit::record()}.
 *
 * The `detail` column stores an id-only JSON snapshot — NEVER raw PII
 * names, only foreign-key ids — so the audit table itself doesn't become
 * a second PII store outside the privacy provider's reach.
 *
 * @package local_sentientia_talent
 */
class audit {

    /** Audit table name. */
    public const TABLE = 'local_sentientia_talent_audit';

    /**
     * Record one audit event.
     *
     * @param int      $costcenterid Tenant of the affected resource
     * @param string   $action       One of the documented action verbs
     * @param string   $objecttable  Affected table name
     * @param int      $objectid     Affected row id
     * @param int|null $targetuserid Subject user (candidate/applicant) if any
     * @param array    $detail       Id-only snapshot (json-encoded). No names.
     * @param int|null $changedby    Actor; defaults to $USER->id
     * @return int new audit row id
     */
    public static function record(int $costcenterid, string $action,
                                   string $objecttable, int $objectid,
                                   ?int $targetuserid = null,
                                   array $detail = [],
                                   ?int $changedby = null): int {
        global $DB, $USER;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'costcenterid' => $costcenterid,
            'action'       => $action,
            'objecttable'  => $objecttable,
            'objectid'     => $objectid,
            'targetuserid' => $targetuserid,
            'detail'       => $detail === [] ? null : json_encode($detail),
            'changedby'    => $changedby ?? (int) $USER->id,
            'timecreated'  => time(),
        ]);
    }

    /**
     * Recent audit rows for the viewer's tenant (or all, for site admins).
     * Joins the actor + target user for display.
     *
     * @param int $limit
     * @return array
     */
    public static function recent(int $limit = 200): array {
        global $DB;
        [$tnsql, $tnparams] = \local_sentientia_platform\tenant::sql_filter('a');
        return $DB->get_records_sql(
            "SELECT a.id, a.costcenterid, a.action, a.objecttable, a.objectid,
                    a.targetuserid, a.detail, a.changedby, a.timecreated,
                    u.firstname AS actor_first, u.lastname AS actor_last,
                    t.firstname AS target_first, t.lastname AS target_last
               FROM {" . self::TABLE . "} a
          LEFT JOIN {user} u ON u.id = a.changedby
          LEFT JOIN {user} t ON t.id = a.targetuserid
              WHERE $tnsql
           ORDER BY a.timecreated DESC, a.id DESC",
            $tnparams, 0, $limit);
    }
}
