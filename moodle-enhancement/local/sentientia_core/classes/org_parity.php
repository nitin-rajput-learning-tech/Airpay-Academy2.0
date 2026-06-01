<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Org-model parity comparator — ADR-020 Wave 3.3. The objective cutover gate.
 *
 * For each in-scope user, compares the (backfilled) Sentientia org model against
 * the legacy BizLMS graph, dogfooding the exact seam a cutover flips to:
 *   1. {@see org::manager_via_model()} == legacy `open_supervisorid` (manager edge)
 *   2. the user's model unit's idnumber == the open_path leaf segment (membership)
 *
 * Read-only. Extracted from the CLI so it is unit-testable with a synthetic
 * {@see org_source} on a vanilla DB. `org_legacy` must NOT be flipped until
 * {@see is_in_parity()} is true for every tenant being cut over.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_parity {

    /** @var org_source The legacy (or synthetic) graph to compare the model against. */
    private org_source $source;

    /**
     * @param org_source $source
     */
    public function __construct(org_source $source) {
        $this->source = $source;
    }

    /**
     * Compare model vs legacy for every in-scope user.
     *
     * @param int[]|null $allowedroots Only check users whose tenant root is in
     *   this set; null = every root. Mirror the backfill's scope.
     * @param int $samplelimit Max mismatch sample lines to collect (0 = none).
     * @return \stdClass {checked, skipped, mgrmismatch, memmismatch, samples[]}
     */
    public function check(?array $allowedroots = null, int $samplelimit = 20): \stdClass {
        global $DB;
        $rootset = $allowedroots === null
            ? null
            : array_fill_keys(array_map('intval', $allowedroots), true);
        // Preload unit id -> idnumber (a few hundred rows) to avoid a per-user lookup.
        $unitidnumber = $DB->get_records_menu('local_sentientia_org_unit', null, '', 'id, idnumber');

        $r = (object) [
            'checked'     => 0,
            'skipped'     => 0,
            'mgrmismatch' => 0,
            'memmismatch' => 0,
            'samples'     => [],
        ];

        foreach ($this->source->users() as $rec) {
            $segments = tenant_identity::segments_for_user((object) ['open_path' => $rec->openpath]);
            if (empty($segments)) {
                $r->skipped++;
                continue;
            }
            $root = $segments[0];
            if ($rootset !== null && !isset($rootset[$root])) {
                $r->skipped++;
                continue;
            }

            $uid = (int) $rec->userid;
            $leafcc = (string) end($segments);

            // 1. Manager-edge parity (call the seam the cutover will use).
            $modelmgr = org::manager_via_model($uid);
            $legacymgr = (int) $rec->supervisorid;
            $mgrok = ($modelmgr === $legacymgr);

            // 2. Membership parity: the user's model unit idnumbers must be exactly [leaf].
            $idnums = [];
            foreach (org::units_of($uid) as $unitid) {
                if (isset($unitidnumber[$unitid])) {
                    $idnums[] = (string) $unitidnumber[$unitid];
                }
            }
            $memok = (count($idnums) === 1 && $idnums[0] === $leafcc);

            if (!$mgrok) {
                $r->mgrmismatch++;
            }
            if (!$memok) {
                $r->memmismatch++;
            }
            if ((!$mgrok || !$memok) && count($r->samples) < $samplelimit) {
                $r->samples[] = sprintf('  user %d: mgr model=%d legacy=%d%s | leaf=%s modelunits=[%s]%s',
                    $uid, $modelmgr, $legacymgr, $mgrok ? '' : ' <-MISMATCH',
                    $leafcc, implode(',', $idnums), $memok ? '' : ' <-MISMATCH');
            }
            $r->checked++;
        }

        return $r;
    }

    /**
     * Is the model in 100% parity with legacy for the given scope?
     * False if nothing was checked (an empty model is not "in parity").
     *
     * @param int[]|null $allowedroots
     * @return bool
     */
    public function is_in_parity(?array $allowedroots = null): bool {
        $r = $this->check($allowedroots, 0);
        return $r->checked > 0 && $r->mgrmismatch === 0 && $r->memmismatch === 0;
    }
}
