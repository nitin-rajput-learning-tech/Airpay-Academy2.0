<?php
// This file is part of Sentientia LMS.
//
// Sentientia LMS is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. Distributed WITHOUT ANY WARRANTY. See the GNU GPL for
// more details. <http://www.gnu.org/licenses/>.

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant-substrate owner: the BizLMS-compatible `open_*` columns.
 *
 * ADR-024 Wave 2 (own every dependency as Sentientia's own): the multi-tenant
 * substrate Sentientia reads (`$USER->open_path` + siblings) was historically
 * provided by the external eAbyas/BizLMS plugin suite. This class is the
 * first-party, single source of truth for that column schema, so Sentientia
 * stands up on vanilla Moodle with NO eAbyas dependency.
 *
 * It is consumed by both:
 *   - db/upgrade.php (automatic on plugin install/upgrade), and
 *   - cli/bootstrap_substrate.php (explicit/manual + --dry-run preview).
 *
 * Additive + idempotent: only columns that are ABSENT are added; nothing is
 * ever dropped or altered. A no-op on a database that already has the
 * substrate (e.g. an Airpay production DB carried over from the eAbyas
 * distribution). Column definitions captured verbatim from the
 * production-faithful schema on 2026-06-04 (37 user cols + 18 course cols).
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class substrate {

    /**
     * BizLMS-compatible open_* columns on {user}: name => MySQL/MariaDB definition.
     *
     * @return array<string,string>
     */
    public static function user_fields(): array {
        return [
            'open_path'            => 'VARCHAR(255) NULL',
            'open_supervisorid'    => 'BIGINT(20) NULL',
            'open_employeeid'      => 'VARCHAR(255) NULL',
            'open_usermodified'    => 'BIGINT(20) NULL',
            'open_designation'     => 'VARCHAR(255) NULL',
            'open_state'           => 'VARCHAR(200) NULL',
            'open_jobfunction'     => 'VARCHAR(200) NULL',
            'open_group'           => 'VARCHAR(200) NULL',
            'open_qualification'   => 'VARCHAR(200) NULL',
            'open_location'        => 'VARCHAR(200) NULL',
            'open_team'            => 'VARCHAR(200) NULL',
            'open_client'          => 'VARCHAR(200) NULL',
            'open_supervisorempid' => 'VARCHAR(200) NULL',
            'open_band'            => 'VARCHAR(200) NULL',
            'open_hrmsrole'        => 'VARCHAR(200) NULL',
            'open_zone'            => 'VARCHAR(200) NULL',
            'open_region'          => 'VARCHAR(200) NULL',
            'open_grade'           => 'VARCHAR(200) NULL',
            'open_positionid'      => 'VARCHAR(255) NULL',
            'open_domainid'        => 'VARCHAR(255) NULL',
            'open_states'          => 'VARCHAR(255) NULL',
            'open_district'        => 'VARCHAR(255) NULL',
            'open_subdistrict'     => 'VARCHAR(255) NULL',
            'open_village'         => 'VARCHAR(255) NULL',
            'open_joindate'        => 'VARCHAR(512) NULL',
            'open_dateofbirth'     => 'VARCHAR(512) NULL',
            'open_employmenttype'  => 'VARCHAR(512) NULL',
            'open_prefix'          => 'VARCHAR(512) NULL',
            'open_orgactive'       => 'TINYINT(1) NOT NULL DEFAULT 0',
            'open_educationlevel'  => "VARCHAR(225) NOT NULL DEFAULT '0'",
            'open_fieldwork'       => "VARCHAR(225) NOT NULL DEFAULT '0'",
            'open_jobtitle'        => "VARCHAR(225) NOT NULL DEFAULT '0'",
            'open_company'         => "VARCHAR(225) NOT NULL DEFAULT '0'",
            'open_paymentinfo'     => "VARCHAR(225) NOT NULL DEFAULT '0'",
            'open_privacypolicy'   => 'TINYINT(1) NULL DEFAULT 0',
            'open_termscondition'  => 'TINYINT(1) NULL DEFAULT 0',
            'open_countryid'       => "VARCHAR(100) NOT NULL DEFAULT '0'",
        ];
    }

    /**
     * BizLMS-compatible open_* columns on {course}: name => MySQL/MariaDB definition.
     *
     * @return array<string,string>
     */
    public static function course_fields(): array {
        return [
            'open_certificateid'        => 'BIGINT(20) NULL',
            'open_path'                 => 'VARCHAR(255) NULL',
            'open_categoryid'           => 'BIGINT(20) NULL DEFAULT 0',
            'open_identifiedas'         => 'VARCHAR(255) NULL',
            'open_points'               => 'BIGINT(20) NULL DEFAULT 0',
            'open_requestcourseid'      => 'BIGINT(20) NULL',
            'open_coursecreator'        => 'BIGINT(20) NULL',
            'open_coursecompletiondays' => 'BIGINT(20) NULL',
            'open_cost'                 => 'BIGINT(20) NULL',
            'open_skill'                => 'BIGINT(20) NULL',
            'open_level'                => 'BIGINT(20) NULL',
            'open_securecourse'         => 'TINYINT(4) NULL DEFAULT 0',
            'open_hrmsrole'             => 'VARCHAR(255) NULL',
            'open_location'             => 'VARCHAR(255) NULL',
            'open_module'               => 'VARCHAR(255) NULL',
            'open_coursetype'           => 'TINYINT(1) NULL DEFAULT 0',
            'open_group'                => 'VARCHAR(225) NULL',
            'open_designation'          => 'VARCHAR(225) NULL',
        ];
    }

    /**
     * Ensure every column in $fields exists on the given (unprefixed) table.
     *
     * @param string $table   Unprefixed table name (e.g. 'user').
     * @param array  $fields  Map of column name => MySQL column definition.
     * @param bool   $dryrun  When true, report only; make no schema change.
     * @return string[] Names of the columns added (or that would be added).
     */
    public static function ensure_table(string $table, array $fields, bool $dryrun = false): array {
        global $DB, $CFG;

        $existing = $DB->get_columns($table, false); // Fresh, no cache.
        $prefixed = $CFG->prefix . $table;
        $added = [];

        foreach ($fields as $name => $definition) {
            if (isset($existing[strtolower($name)])) {
                continue;
            }
            if (!$dryrun) {
                // Raw DDL is intentional: we reproduce the external (eAbyas)
                // column types verbatim; the xmldb generator would impose
                // Moodle's own type opinions. change_database_structure()
                // resets the DDL caches afterwards.
                $DB->change_database_structure("ALTER TABLE `{$prefixed}` ADD COLUMN `{$name}` {$definition}");
            }
            $added[] = $name;
        }

        return $added;
    }

    /**
     * Ensure the full open_* substrate on {user} and {course}.
     *
     * @param bool $dryrun When true, report only; make no schema change.
     * @return array{user: string[], course: string[]} Columns added per table.
     */
    public static function ensure_all(bool $dryrun = false): array {
        return [
            'user'   => self::ensure_table('user', self::user_fields(), $dryrun),
            'course' => self::ensure_table('course', self::course_fields(), $dryrun),
        ];
    }
}
