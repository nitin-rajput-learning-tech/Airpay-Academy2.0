<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * {@see org_source} backed by the live BizLMS columns — ADR-020 Wave 3.2b.
 *
 * Reads `user.open_path` + `user.open_supervisorid` (BizLMS-extended mdl_user
 * columns) for the per-user facts, and `local_costcenter` for unit display
 * names. Only ever instantiated by the reconcile cron / backfill CLI on a real
 * BizLMS deployment; unit tests use a synthetic in-memory source instead, so
 * this class is never exercised on a vanilla Moodle DB (which lacks the columns).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_legacy_source implements org_source {

    /** @var array<int,string|null> Request cache: cost-center id => resolved name. */
    private array $namecache = [];

    /** @var bool|null Whether local_costcenter.name is queryable here (resolved once). */
    private ?bool $namecolumn = null;

    /**
     * Stream non-deleted users that carry a BizLMS open_path.
     *
     * Yields every candidate regardless of tenant; the reconciler applies the
     * tenant allow-list. Users whose open_path is empty / non-numeric-rooted are
     * still yielded — the reconciler skips them via tenant_identity parsing, so
     * the "skipped" count stays observable.
     *
     * @return iterable<\stdClass>
     */
    public function users(): iterable {
        global $DB;
        // open_path / open_supervisorid are BizLMS-extended columns on mdl_user;
        // this method only runs where they exist.
        $rs = $DB->get_recordset_select(
            'user',
            'deleted = 0 AND open_path IS NOT NULL',
            null,
            'id ASC',
            'id, open_path, open_supervisorid'
        );
        foreach ($rs as $u) {
            yield (object) [
                'userid'       => (int) $u->id,
                'openpath'     => (string) $u->open_path,
                'supervisorid' => (int) ($u->open_supervisorid ?? 0),
            ];
        }
        $rs->close();
    }

    /**
     * @param int $costcenterid
     * @return string|null
     */
    public function unit_name(int $costcenterid): ?string {
        global $DB;
        if ($costcenterid <= 0) {
            return null;
        }
        if (array_key_exists($costcenterid, $this->namecache)) {
            return $this->namecache[$costcenterid];
        }
        $name = null;
        if ($this->name_column_available()) {
            $val = $DB->get_field('local_costcenter', 'name', ['id' => $costcenterid], IGNORE_MISSING);
            if (is_string($val) && trim($val) !== '') {
                $name = $val;
            }
        }
        return $this->namecache[$costcenterid] = $name;
    }

    /**
     * Is local_costcenter.name queryable on this deployment? Resolved once.
     *
     * An explicit capability check (not a swallowed exception) so the source is
     * portable to an Enterprise-N deployment with a differently-shaped — or
     * absent — cost-center table, in which case names fall back to "Unit <id>".
     *
     * @return bool
     */
    private function name_column_available(): bool {
        global $DB;
        if ($this->namecolumn === null) {
            $dbman = $DB->get_manager();
            $this->namecolumn = $dbman->table_exists('local_costcenter')
                && array_key_exists('name', $DB->get_columns('local_costcenter'));
        }
        return $this->namecolumn;
    }
}
