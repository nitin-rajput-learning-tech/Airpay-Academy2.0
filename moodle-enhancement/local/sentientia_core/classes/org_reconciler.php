<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Dual-write reconciler — mirrors the legacy BizLMS org graph into the
 * Sentientia org model (local_sentientia_org_unit / _member). ADR-020 Wave 3.2b.
 *
 * Idempotent: a re-run over an unchanged {@see org_source} is a no-op (zero
 * creates, zero updates). The legacy graph stays the source of truth; this only
 * keeps the otherwise-empty Sentientia tables warm so an eventual flag flip
 * (org_legacy OFF, post clone-DB rehearsal + 100% parity) reads a populated
 * model. Default-OFF — nothing invokes the cron until org_dualwrite_enabled is
 * turned on, so deploying this wave changes nothing.
 *
 * Mapping (per ADR-020 §2 + the 2026-06-01 manager-edge modelling decision):
 *  - Each DISTINCT open_path segment (a cost-center id) becomes one org_unit,
 *    keyed by idnumber = that cost-center id (BizLMS cost-center ids are globally
 *    unique), tenant-scoped by tenantrootid = segment[0]. parentid chains from
 *    the previous segment; path mirrors the cost-center-id prefix (e.g. '/1/2/3').
 *  - Each user becomes one org_member in their LEAF unit, with managerid =
 *    open_supervisorid — the direct reporting edge, NOT the unit 'role' (which is
 *    reserved for a future 'unit lead' concept).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_reconciler {

    /** @var org_source The legacy (or synthetic) graph being mirrored. */
    private org_source $source;

    /** @var array<int,int> Per-run cache: cost-center id => org_unit.id. */
    private array $unitidbycc = [];

    /**
     * @param org_source $source
     */
    public function __construct(org_source $source) {
        $this->source = $source;
    }

    /**
     * Run one reconciliation pass.
     *
     * @param int[]|null $allowedroots Only process users whose tenant root
     *   (open_path segment[0]) is in this set; null = every root. Pass
     *   {@see tenant_registry::valid_roots()} to stay tenant-scoped.
     * @return \stdClass Counts: unitscreated, unitsupdated, memberscreated,
     *   membersupdated, usersprocessed, usersskipped.
     */
    public function reconcile(?array $allowedroots = null): \stdClass {
        $this->unitidbycc = [];
        $counts = (object) [
            'unitscreated'   => 0,
            'unitsupdated'   => 0,
            'memberscreated' => 0,
            'membersupdated' => 0,
            'usersprocessed' => 0,
            'usersskipped'   => 0,
        ];
        $rootset = $allowedroots === null
            ? null
            : array_fill_keys(array_map('intval', $allowedroots), true);

        foreach ($this->source->users() as $rec) {
            // Decompose the open_path with the same rule the rest of Sentientia
            // uses, so the unit tree stays consistent with tenant resolution.
            $segments = tenant_identity::segments_for_user((object) ['open_path' => $rec->openpath]);
            if (empty($segments)) {
                $counts->usersskipped++;
                continue;
            }
            $root = $segments[0];
            if ($rootset !== null && !isset($rootset[$root])) {
                $counts->usersskipped++;
                continue;
            }

            // Build / refresh the unit chain for this path; remember the leaf.
            $parentid = 0;
            $path = '';
            $leafunitid = 0;
            foreach ($segments as $cc) {
                $path .= '/' . $cc;
                $leafunitid = $this->ensure_unit($cc, $parentid, $root, $path, $counts);
                $parentid = $leafunitid;
            }

            // Membership in the leaf unit, carrying the direct manager edge.
            $this->ensure_member((int) $rec->userid, $leafunitid, (int) $rec->supervisorid, $counts);
            $counts->usersprocessed++;
        }

        return $counts;
    }

    /**
     * Upsert the org_unit for a cost-center id; return its id. Idempotent.
     *
     * @param int       $cc       Cost-center id (becomes idnumber).
     * @param int       $parentid Parent org_unit id (0 = tenant root).
     * @param int       $root     Tenant root (open_path segment[0]).
     * @param string    $path     Cost-center-id materialised path, e.g. '/1/2'.
     * @param \stdClass $counts   Mutated in place.
     * @return int org_unit id.
     */
    private function ensure_unit(int $cc, int $parentid, int $root, string $path, \stdClass $counts): int {
        global $DB;
        if (isset($this->unitidbycc[$cc])) {
            return $this->unitidbycc[$cc];
        }

        $desiredname = $this->source->unit_name($cc);
        if ($desiredname === null || $desiredname === '') {
            $desiredname = 'Unit ' . $cc;
        }

        $existing = $DB->get_record('local_sentientia_org_unit', ['idnumber' => (string) $cc]);
        if ($existing) {
            $update = [];
            if ((int) $existing->parentid !== $parentid)   { $update['parentid'] = $parentid; }
            if ((int) $existing->tenantrootid !== $root)    { $update['tenantrootid'] = $root; }
            if ((string) $existing->name !== $desiredname)  { $update['name'] = $desiredname; }
            if ((string) $existing->path !== $path)         { $update['path'] = $path; }
            if (!empty($update)) {
                $update['id'] = $existing->id;
                $update['timemodified'] = time();
                $DB->update_record('local_sentientia_org_unit', (object) $update);
                $counts->unitsupdated++;
            }
            return $this->unitidbycc[$cc] = (int) $existing->id;
        }

        $now = time();
        $id = (int) $DB->insert_record('local_sentientia_org_unit', (object) [
            'parentid'     => $parentid,
            'tenantrootid' => $root,
            'name'         => $desiredname,
            'idnumber'     => (string) $cc,
            'path'         => $path,
            'status'       => 'active',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $counts->unitscreated++;
        return $this->unitidbycc[$cc] = $id;
    }

    /**
     * Upsert the org_member row for (userid, unitid) with the manager edge.
     * Idempotent — only writes when the row is missing or managerid changed.
     *
     * @param int       $userid
     * @param int       $unitid
     * @param int       $managerid Direct manager user id (0 = none).
     * @param \stdClass $counts    Mutated in place.
     */
    private function ensure_member(int $userid, int $unitid, int $managerid, \stdClass $counts): void {
        global $DB;
        if ($userid <= 0 || $unitid <= 0) {
            return;
        }
        $existing = $DB->get_record('local_sentientia_org_member',
            ['userid' => $userid, 'unitid' => $unitid]);
        if ($existing) {
            if ((int) $existing->managerid !== $managerid) {
                $DB->update_record('local_sentientia_org_member', (object) [
                    'id'           => $existing->id,
                    'managerid'    => $managerid,
                    'timemodified' => time(),
                ]);
                $counts->membersupdated++;
            }
            return;
        }
        $now = time();
        $DB->insert_record('local_sentientia_org_member', (object) [
            'userid'       => $userid,
            'unitid'       => $unitid,
            'role'         => 'member',
            'managerid'    => $managerid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $counts->memberscreated++;
    }
}
