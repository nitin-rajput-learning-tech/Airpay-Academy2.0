<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * Skills-gap engine.
 *
 * Compares the skills a user's role/designation REQUIRES (from
 * local_sentientia_role_skills, the existing competency matrix in
 * sentientia_skills) against the skills the user HOLDS (from
 * local_sentientia_user_skills) and emits a per-user gap feed into
 * local_sentientia_skai_gap.
 *
 * A gap row exists only when held_level < required_level. Each row carries
 * the max business-impact weight across the skill's impact mappings (when
 * the impact_map feature is on) so the feed can be ranked by business
 * priority rather than raw level deficit alone.
 *
 * The gap feed is consumed by the gap UI and by sentientia_recommendations
 * (which ranks remedial courses against the open gaps).
 *
 * @package local_sentientia_skillsai
 */
class gap_engine {

    public const GAP_TABLE     = 'local_sentientia_skai_gap';
    public const ROLE_TABLE    = 'local_sentientia_role_skills';
    public const USERSKILL_TABLE = 'local_sentientia_user_skills';
    public const SKILL_TABLE   = 'local_sentientia_skills';
    public const TAXONOMY_TABLE = 'local_sentientia_skai_taxonomy';
    public const IMPACT_TABLE  = 'local_sentientia_skai_impact';

    /**
     * Resolve the BizLMS tenant root from a user's open_path.
     *
     * @param \stdClass|null $user
     * @return int
     */
    public static function tenant_root_for(?\stdClass $user = null): int {
        global $USER;
        $u = $user ?? $USER;
        $path = isset($u->open_path) ? (string)$u->open_path : '';
        $parts = explode('/', trim($path, '/'));
        return (int)($parts[0] ?? 0);
    }

    /**
     * Resolve a user's designation. Production stores it on
     * user.open_designation. Falls back to '' when the column is absent
     * (unit-test sandbox).
     *
     * @param int $userid
     * @return string
     */
    public static function designation_for_user(int $userid): string {
        global $DB;
        $manager = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_designation');
        if (!$manager->field_exists($table, $field)) {
            return '';
        }
        $val = $DB->get_field('user', 'open_designation', ['id' => $userid]);
        return $val === false ? '' : (string)$val;
    }

    /**
     * Compute the gap rows for a single user WITHOUT persisting them.
     *
     * Pure-ish: deterministic given the DB state. Used by both
     * rebuild_for_user() and the tests so the gap maths can be asserted
     * directly.
     *
     * @param int    $userid
     * @param string $designation Pass explicitly so tests don't need the
     *                            production open_designation column. When
     *                            '' the method resolves it itself.
     * @return \stdClass[] Each: {skillid, taxonomyid, designation,
     *                     required_level, held_level, gap_size, impact_weight}
     */
    public static function compute_for_user(int $userid, string $designation = ''): array {
        global $DB;

        if ($designation === '') {
            $designation = self::designation_for_user($userid);
        }
        if ($designation === '') {
            return [];
        }

        // Required skills for this designation, LEFT JOINed to what the
        // user holds. One row per required skill; held_level defaults 0.
        $sql = "SELECT rs.skillid AS skillid,
                       rs.required_level AS required_level,
                       COALESCE(us.current_level, 0) AS held_level
                  FROM {" . self::ROLE_TABLE . "} rs
             LEFT JOIN {" . self::USERSKILL_TABLE . "} us
                    ON us.skillid = rs.skillid AND us.userid = :uid
                 WHERE rs.designation = :designation";
        $rows = $DB->get_records_sql($sql, [
            'uid'         => $userid,
            'designation' => $designation,
        ]);

        $tenant = self::tenant_root_for($DB->get_record('user', ['id' => $userid], 'id, open_path'));

        // Pre-load taxonomy linkage (skillid -> taxonomyid) and impact
        // weights (taxonomyid -> max weight) for this tenant in two
        // queries, then resolve per row — avoids N+1.
        $taxbyskill = [];
        $impactbytax = [];
        if (\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.impact_map')) {
            $taxnodes = $DB->get_records(self::TAXONOMY_TABLE, ['costcenterid' => $tenant],
                '', 'id, linked_skillid');
            foreach ($taxnodes as $t) {
                if (!empty($t->linked_skillid)) {
                    $taxbyskill[(int)$t->linked_skillid] = (int)$t->id;
                }
            }
            if (!empty($taxbyskill)) {
                [$insql, $params] = $DB->get_in_or_equal(array_values($taxbyskill), SQL_PARAMS_NAMED, 'tx');
                $impacts = $DB->get_records_sql(
                    "SELECT taxonomyid, MAX(weight) AS maxweight
                       FROM {" . self::IMPACT_TABLE . "}
                      WHERE taxonomyid $insql
                   GROUP BY taxonomyid", $params);
                foreach ($impacts as $im) {
                    $impactbytax[(int)$im->taxonomyid] = (int)$im->maxweight;
                }
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $required = (int)$r->required_level;
            $held     = (int)$r->held_level;
            if ($held >= $required) {
                continue; // no gap
            }
            $skillid = (int)$r->skillid;
            $taxid = $taxbyskill[$skillid] ?? null;
            $weight = ($taxid !== null && isset($impactbytax[$taxid])) ? $impactbytax[$taxid] : 0;

            $gap = new \stdClass();
            $gap->skillid        = $skillid;
            $gap->taxonomyid     = $taxid;
            $gap->designation    = $designation;
            $gap->required_level = $required;
            $gap->held_level     = $held;
            $gap->gap_size       = $required - $held;
            $gap->impact_weight  = $weight;
            $out[] = $gap;
        }

        // Sort by business priority: impact weight desc, then gap size desc.
        usort($out, function ($a, $b) {
            return [$b->impact_weight, $b->gap_size] <=> [$a->impact_weight, $a->gap_size];
        });

        return $out;
    }

    /**
     * Rebuild + persist the gap feed for one user. Replaces that user's
     * existing gap rows atomically.
     *
     * @param int    $userid
     * @param string $designation Optional explicit designation (see compute_for_user)
     * @return int Number of gap rows written
     */
    public static function rebuild_for_user(int $userid, string $designation = ''): int {
        global $DB;

        $gaps = self::compute_for_user($userid, $designation);

        $user = $DB->get_record('user', ['id' => $userid], 'id, open_path', IGNORE_MISSING);
        $tenant = $user ? self::tenant_root_for($user) : 0;
        $batchid = substr(md5($userid . '|' . microtime(true)), 0, 32);
        $now = time();

        $txn = $DB->start_delegated_transaction();
        try {
            // Replace this user's feed atomically.
            $DB->delete_records(self::GAP_TABLE, ['userid' => $userid]);

            foreach ($gaps as $g) {
                $row = new \stdClass();
                $row->userid         = $userid;
                $row->customerid     = 1;
                $row->costcenterid   = $tenant;
                $row->skillid        = $g->skillid;
                $row->taxonomyid     = $g->taxonomyid;
                $row->designation    = $g->designation;
                $row->required_level = $g->required_level;
                $row->held_level     = $g->held_level;
                $row->gap_size       = $g->gap_size;
                $row->impact_weight  = $g->impact_weight;
                $row->batchid        = $batchid;
                $row->timecreated    = $now;
                $row->timemodified   = $now;
                $DB->insert_record(self::GAP_TABLE, $row);
            }

            $txn->allow_commit();
        } catch (\Throwable $e) {
            $txn->rollback($e);
        }

        return count($gaps);
    }

    /**
     * Read the persisted gap feed for a user, joined to skill names, sorted
     * by business priority then gap size.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function feed_for_user(int $userid): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT g.*, s.name AS skillname
               FROM {" . self::GAP_TABLE . "} g
          LEFT JOIN {" . self::SKILL_TABLE . "} s ON s.id = g.skillid
              WHERE g.userid = :uid
           ORDER BY g.impact_weight DESC, g.gap_size DESC, s.name ASC",
            ['uid' => $userid]
        ));
    }

    /**
     * Tenant-wide gap feed for managers, scoped to the manager's tenant
     * (site admins see all). Returns aggregate rows: one per (skill) with
     * the count of users who have a gap on it, ordered by business
     * priority.
     *
     * @param int $costcenterid Tenant root (0 = all, admin only)
     * @param int $limit
     * @return \stdClass[]
     */
    public static function tenant_summary(int $costcenterid, int $limit = 100): array {
        global $DB;

        $where = '';
        $params = [];
        if ($costcenterid > 0) {
            $where = 'WHERE g.costcenterid = :cid';
            $params['cid'] = $costcenterid;
        }

        return array_values($DB->get_records_sql(
            "SELECT g.skillid,
                    s.name AS skillname,
                    COUNT(g.id) AS affected_users,
                    MAX(g.impact_weight) AS impact_weight,
                    MAX(g.gap_size) AS max_gap
               FROM {" . self::GAP_TABLE . "} g
          LEFT JOIN {" . self::SKILL_TABLE . "} s ON s.id = g.skillid
               $where
           GROUP BY g.skillid, s.name
           ORDER BY impact_weight DESC, affected_users DESC, max_gap DESC",
            $params, 0, $limit
        ));
    }
}
