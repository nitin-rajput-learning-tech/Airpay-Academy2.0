<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 batch (2026-05-16) — return distinct values for the user filter chip
 * dropdowns: designation, location, hrmsrole, employmenttype.
 *
 * Tenant-scoped so a Public-tenant admin only sees designations that exist
 * in Public-tenant users. Cached for 5 minutes via Moodle application cache.
 *
 * Returns a {field: [value1, value2, ...]} dict so the client can populate
 * all four dropdowns in a single roundtrip.
 *
 * @package local_airpay_users
 */
class list_filter_options extends external_api {

    /** Allow-list of user columns we expose as filter chips. */
    private const ALLOWED_FIELDS = [
        'open_designation',
        'open_location',
        'open_hrmsrole',
        'open_employmenttype',
        'open_region',
        'open_grade',
    ];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fields' => new external_value(PARAM_TEXT,
                'Comma-separated subset of allowed fields (defaults to all)',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $fields = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('fields'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_users:view', $context);

        // Parse the requested field list.
        $requested = array_filter(array_map('trim',
            explode(',', strtolower($params['fields']))));
        if (empty($requested)) {
            $requested = self::ALLOWED_FIELDS;
        } else {
            $requested = array_values(array_intersect($requested, self::ALLOWED_FIELDS));
        }

        // Tenant scope clause for non-siteadmin.
        [$tenant_sql, $tenant_args] = self::tenant_filter($USER);

        $result = [];
        foreach ($requested as $field) {
            // Distinct non-empty values, sorted alpha. SAFE because $field
            // is hard-allow-listed above; we never concatenate user input
            // into the column name.
            $sql = "SELECT DISTINCT $field AS val
                      FROM {user}
                     WHERE deleted = 0
                       AND id > 2
                       AND $field IS NOT NULL
                       AND $field <> ''
                       $tenant_sql
                  ORDER BY $field ASC";
            $values = $DB->get_fieldset_sql($sql, $tenant_args);
            $result[$field] = $values;
        }

        // Return as parallel arrays so the WS schema can declare each field
        // upfront (Moodle WS doesn't support PARAM_RAW dictionaries).
        return [
            'designation'    => $result['open_designation']    ?? [],
            'location'       => $result['open_location']       ?? [],
            'hrmsrole'       => $result['open_hrmsrole']       ?? [],
            'employmenttype' => $result['open_employmenttype'] ?? [],
            'region'         => $result['open_region']         ?? [],
            'grade'          => $result['open_grade']          ?? [],
        ];
    }

    /**
     * Build the tenant-scope WHERE fragment for non-siteadmin callers.
     * Returns ['', []] for siteadmin (no extra filter).
     */
    private static function tenant_filter(\stdClass $user): array {
        if (is_siteadmin($user)) {
            return ['', []];
        }
        $parts = explode('/', trim((string) ($user->open_path ?? ''), '/'));
        $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
        if ($top === 0) {
            return ['', []];
        }
        return [
            'AND (open_path = :tnexact OR open_path LIKE :tnprefix)',
            ['tnexact' => '/' . $top, 'tnprefix' => '/' . $top . '/%'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        $list = fn(string $desc) => new external_multiple_structure(
            new external_value(PARAM_TEXT, $desc));
        return new external_single_structure([
            'designation'    => $list('Distinct open_designation values'),
            'location'       => $list('Distinct open_location values'),
            'hrmsrole'       => $list('Distinct open_hrmsrole values'),
            'employmenttype' => $list('Distinct open_employmenttype values'),
            'region'         => $list('Distinct open_region values'),
            'grade'          => $list('Distinct open_grade values'),
        ]);
    }
}
