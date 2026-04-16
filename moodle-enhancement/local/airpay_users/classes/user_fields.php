<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_airpay_users;

defined('MOODLE_INTERNAL') || die();

/**
 * User field definitions — maps the 17 actually-used BizLMS open_* fields.
 *
 * These columns exist on mdl_user (added by BizLMS ALTER TABLE).
 * We own them now — column names stay for backward compatibility.
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_fields {

    /**
     * Fields used in queries/logic (WHERE, JOIN, GROUP BY).
     * These are the ones that actually drive application behaviour.
     */
    public const QUERY_FIELDS = [
        'open_path',           // Org hierarchy path — tenant scoping
        'open_supervisorid',   // Manager user ID — team queries
        'open_costcenterid',   // Root costcenter — access control
        'open_departmentid',   // Department — access control
        'open_employeeid',     // HR employee ID — compliance/export
        'open_designation',    // Job title — profile/skills
    ];

    /**
     * Fields used in profile display only.
     * Read from user record and shown in UI, never used in query logic.
     */
    public const DISPLAY_FIELDS = [
        'open_prefix',         // Mr/Mrs/Ms (enum: 1=Mr, 2=Mrs, 3=Ms)
        'open_client',         // Client affiliation
        'open_team',           // Team assignment
        'open_grade',          // Grade level
        'open_hrmsrole',       // HRIS role
        'open_zone',           // Geographic zone
        'open_region',         // Geographic region
        'open_employmenttype', // Employment type string
        'open_joindate',       // Hire date (unix timestamp)
        'open_dateofbirth',    // Birth date (unix timestamp)
        'open_positionid',     // Position matrix ID (skills)
        'open_domainid',       // Domain matrix ID (skills)
    ];

    /**
     * All fields we own (query + display).
     *
     * @return array
     */
    public static function all(): array {
        return array_merge(self::QUERY_FIELDS, self::DISPLAY_FIELDS);
    }

    /**
     * SQL fragment for selecting all open_* fields.
     *
     * @param string $alias  Table alias (default 'u')
     * @return string  e.g. "u.open_path, u.open_supervisorid, ..."
     */
    public static function select_sql(string $alias = 'u'): string {
        return implode(', ', array_map(function ($f) use ($alias) {
            return $alias . '.' . $f;
        }, self::all()));
    }

    /**
     * SQL fragment for selecting query-critical fields only.
     *
     * @param string $alias
     * @return string
     */
    public static function select_query_sql(string $alias = 'u'): string {
        return implode(', ', array_map(function ($f) use ($alias) {
            return $alias . '.' . $f;
        }, self::QUERY_FIELDS));
    }

    /**
     * Get prefix label from enum value.
     *
     * @param int|null $value
     * @return string
     */
    public static function prefix_label(?int $value): string {
        return match ($value) {
            1 => 'Mr.',
            2 => 'Mrs.',
            3 => 'Ms.',
            default => '',
        };
    }

    /**
     * Format a unix timestamp field to display date.
     *
     * @param int|null $timestamp
     * @param string   $format
     * @return string
     */
    public static function format_date(?int $timestamp, string $format = 'd-M-Y'): string {
        if (empty($timestamp) || $timestamp <= 0) {
            return 'N/A';
        }
        return date($format, $timestamp);
    }
}
