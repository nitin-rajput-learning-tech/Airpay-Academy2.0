<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * The position and domain lookups (ADR-032, mapping doc section 10, gap G4).
 *
 * user.open_positionid and user.open_domainid hold the ids of these rows, and the restored {user} table is not
 * rewritten by the import, so the ids are KEPT (PRESERVE): a collision with an existing row blocks the whole
 * feature, and there is no "new id" fallback. The targets are new tables, so there is nothing to adopt: the
 * adopt signature is empty, which the framework reads as "no existing row is an identical copy".
 *
 * BizLMS has no install file for either table, so which columns exist is only known from the code that reads
 * them (name, code, costcenter, and domain and sortorder on positions). A column the production table lacks
 * gives that column's default: the audit times are the source's where it has them and 0 where it does not
 * (nothing invents a time). Preflight refuses a table that has no name column.
 *
 * The signed decision users.positions_domains_lookup_import says whether to import; with false every row is
 * archived.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class lookup_step extends step {

    /**
     * The step's own columns of the target row, from the source row.
     *
     * @param \stdClass $row
     * @param context $ctx
     * @return array<string, int|string> Everything but the time columns.
     */
    abstract protected function fields(\stdClass $row, context $ctx): array;

    public function idpolicy(): string {
        return idpolicy::PRESERVE;
    }

    /**
     * New, empty tables: no row is an adoptable copy of a legacy one.
     *
     * @return array
     */
    public function adopt_signature(): array {
        return [];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        $import = $ctx->decision(users_importer::DECISION_LOOKUPS) !== false;
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if (!$import) {
                $out[] = outcome::archive($id, users_importer::REASON_DECLINED);
                continue;
            }
            $created = clean::time($row->timecreated ?? null);
            $modified = clean::time($row->timemodified ?? null);
            $fields = $this->fields($row, $ctx) + [
                'timecreated' => $created,
                'timemodified' => $modified > 0 ? $modified : $created,
            ];
            $outcome = outcome::insert($id, $this->targettable(), (object) $fields);
            if ($created === 0) {
                $outcome->warn('no_source_time');
            }
            $out[] = $outcome;
        }
        return $out;
    }

    /**
     * A name or code, valid UTF-8 and fitted to its CHAR(255) column.
     *
     * @param context $ctx
     * @param mixed $value
     * @param string $column
     * @return string
     */
    protected function text(context $ctx, mixed $value, string $column): string {
        [$text] = clean::utf8($value === null ? null : (string) $value);
        return $ctx->text->fit(trim($text), 255, $column);
    }
}
