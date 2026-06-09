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

namespace local_sentientia_courses;

defined('MOODLE_INTERNAL') || die();

/**
 * Course field definitions — maps the 11 BizLMS open_* course fields.
 *
 * These columns exist on mdl_course (added by BizLMS ALTER TABLE).
 * We own them now — column names stay for backward compatibility.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_fields {

    /**
     * Fields used in access control queries.
     */
    public const ACCESS_FIELDS = [
        'open_costcenterid',       // Root org — tenant access control
        'open_departmentid',       // Department — dept-head access control
    ];

    /**
     * Fields used in catalog/display logic.
     */
    public const METADATA_FIELDS = [
        'open_categoryid',         // Custom category (links to local_custom_category)
        'open_level',              // Difficulty level (links to local_course_levels)
        'open_coursetype',         // E-learning, classroom, blended
        'open_skill',              // Associated skill ID (links to local_skill)
        'open_certificateid',      // Certificate template ID
        'open_coursecompletiondays',// Days allowed for completion
        'open_points',             // Credit points awarded on completion
        'open_identifiedas',       // Course type identifier (links to local_course_types)
        'open_path',               // Org path for notification scoping
    ];

    /**
     * All course open_* fields.
     *
     * @return array
     */
    public static function all(): array {
        return array_merge(self::ACCESS_FIELDS, self::METADATA_FIELDS);
    }

    /**
     * SQL fragment for selecting course open_* fields.
     *
     * @param string $alias  Table alias (default 'c')
     * @return string
     */
    public static function select_sql(string $alias = 'c'): string {
        return implode(', ', array_map(function ($f) use ($alias) {
            return $alias . '.' . $f;
        }, self::all()));
    }
}
