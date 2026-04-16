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
 * User manager — queries, lookups, and field helpers for user records.
 *
 * Replaces scattered $DB queries against the user table found in
 * renderer.php, dashboard.php, compliance_engine, and 8+ plugins.
 *
 * @package    local_airpay_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_manager {

    /**
     * Get a user record with all open_* fields included.
     *
     * @param int $userid
     * @return object|false
     */
    public static function get(int $userid) {
        global $DB;
        return $DB->get_record('user', ['id' => $userid]);
    }

    /**
     * Get org hierarchy names for a user (parsed from open_path).
     *
     * Replaces the BizLMS pattern in renderer.php lines 61-69:
     *   array_map(get_costcenter_info, explode('/', open_path))
     *
     * @param object $user  User record (must have open_path)
     * @return object  {org, department, subdepartment, cu, territory}
     */
    public static function get_org_hierarchy(object $user): object {
        $path = $user->open_path ?? '';
        $orgids = array_values(array_filter(explode('/', $path)));

        $names = array_map(function ($orgid) {
            return \local_airpay_org\org_manager::get_name((int) $orgid);
        }, $orgids);

        return (object) [
            'org'           => $names[0] ?? '',
            'department'    => $names[1] ?? '',
            'subdepartment' => $names[2] ?? '',
            'cu'            => $names[3] ?? '',
            'territory'     => $names[4] ?? '',
        ];
    }

    /**
     * Get reporting manager info for a user.
     *
     * @param int $supervisorid  The open_supervisorid value
     * @return object|null  {id, firstname, lastname, fullname, employeeid}
     */
    public static function get_supervisor(int $supervisorid): ?object {
        global $DB;

        if (empty($supervisorid)) {
            return null;
        }

        $mgr = $DB->get_record('user', ['id' => $supervisorid, 'deleted' => 0],
            'id, firstname, lastname, open_employeeid');

        if (!$mgr) {
            return null;
        }

        $mgr->fullname = fullname($mgr);
        $mgr->employeeid = $mgr->open_employeeid ?? '';

        return $mgr;
    }

    /**
     * Get roles for a user at system or category level.
     *
     * @param int $userid
     * @return string  Comma-separated role names, or "Employee"
     */
    public static function get_role_names(int $userid): string {
        $context = \local_airpay_org\accesslib::get_module_context();
        $userroles = get_user_roles($context, $userid);

        if (!empty($userroles)) {
            $names = array_map(function ($r) {
                return ucfirst($r->name);
            }, $userroles);
            return implode(', ', $names);
        }

        return get_string('employee', 'local_airpay_users');
    }

    /**
     * Count badges issued to a user.
     *
     * @param int $userid
     * @return int
     */
    public static function count_badges(int $userid): int {
        global $DB;
        return $DB->count_records('badge_issued', ['userid' => $userid]);
    }

    /**
     * Build profile context array for template rendering.
     *
     * Centralizes the 40+ field context that was in renderer.php.
     *
     * @param int $userid
     * @return array  Template context
     */
    public static function build_profile_context(int $userid): array {
        global $OUTPUT, $CFG;

        $user = self::get($userid);
        if (!$user) {
            return [];
        }

        $hierarchy = self::get_org_hierarchy($user);
        $supervisor = self::get_supervisor((int) ($user->open_supervisorid ?? 0));
        $roleinfo = self::get_role_names($userid);
        $badgecount = self::count_badges($userid);

        $userimage = $OUTPUT->user_picture($user, ['size' => 35, 'link' => false]);

        $context = [
            'userid'          => $user->id,
            'username'        => fullname($user),
            'userimage'       => $userimage,
            'rolename'        => $roleinfo,
            'empid'           => $user->open_employeeid ?: 'N/A',
            'user_email'      => $user->email,
            'organisation'    => $hierarchy->org ?: 'N/A',
            'department'      => $hierarchy->department ?: 'All',
            'subdepartment'   => $hierarchy->subdepartment ?: 'All',
            'usercu'          => $hierarchy->cu ?: 'All',
            'userterritory'   => $hierarchy->territory ?: 'All',
            'location'        => $user->city ?: 'N/A',
            'timezone'        => \core_date::get_user_timezone($user->timezone),
            'address'         => $user->address ?: 'N/A',
            'designation'     => $user->open_designation ?: 'N/A',
            'client'          => trim($user->open_client ?? '') ?: 'N/A',
            'team'            => trim($user->open_team ?? '') ?: 'N/A',
            'grade'           => trim($user->open_grade ?? '') ?: 'N/A',
            'hrmrole'         => trim($user->open_hrmsrole ?? '') ?: 'N/A',
            'zone'            => trim($user->open_zone ?? '') ?: 'N/A',
            'region'          => trim($user->open_region ?? '') ?: 'N/A',
            'employment_type' => trim($user->open_employmenttype ?? '') ?: 'N/A',
            'phnumber'        => $user->phone1 ?: 'N/A',
            'supervisorname'  => $supervisor ? $supervisor->fullname : 'N/A',
            'badgescount'     => $badgecount,
            'prefix'          => user_fields::prefix_label((int) ($user->open_prefix ?? 0)),
            'joindate'        => user_fields::format_date((int) ($user->open_joindate ?? 0)),
            'dateofbirth'     => user_fields::format_date((int) ($user->open_dateofbirth ?? 0)),
            'editprofile'     => new \moodle_url('/user/editadvanced.php',
                                    ['id' => $user->id, 'returnto' => 'profile']),
        ];

        // Capability checks.
        $syscontext = \context_system::instance();
        $context['capabilityedit'] = (is_siteadmin() ||
            has_capability('local/airpay_users:edit', $syscontext) ||
            has_capability('local/users:edit', $syscontext)) ? 1 : 0;
        $context['loginasurl'] = has_capability('moodle/user:loginas', $syscontext)
            ? new \moodle_url('/course/loginas.php', ['id' => 1, 'user' => $user->id, 'sesskey' => sesskey()])
            : false;

        // Inject gamification data.
        if (file_exists($CFG->dirroot . '/local/airpay_gamification/lib.php')) {
            try {
                require_once($CFG->dirroot . '/local/airpay_gamification/lib.php');
                $context['ap_gamification'] = local_airpay_gamification_get_summary($userid);
            } catch (\Throwable $e) {
                $context['ap_gamification'] = null;
            }
        }

        // Inject skills summary.
        if (file_exists($CFG->dirroot . '/local/airpay_skills/classes/skills_manager.php')) {
            try {
                $analysis = \local_airpay_skills\skills_manager::get_gap_analysis($userid);
                if ($analysis['has_data'] ?? false) {
                    $context['ap_skills_summary'] = $analysis['summary'];
                }
            } catch (\Throwable $e) {
                // Skills not available.
            }
        }

        // Collect plugin profile tabs.
        $pluginlist = \core_component::get_plugin_list('local');
        $usercontent = [];
        $existingplugin = [];
        foreach ($pluginlist as $pluginname => $pluginurl) {
            $userclass = '\local_' . $pluginname . '\local\user';
            if (class_exists($userclass) && method_exists($userclass, 'user_profile_content')) {
                $plugindata = (new $userclass)->user_profile_content($userid, true);
                $usercontent[] = $plugindata;
                if ($pluginname !== 'users' && $pluginname !== 'airpay_users') {
                    $existingplugin[$plugindata->sequence ?? 0] = [
                        'userenrolledcount' => $plugindata->count ?? 0,
                        'string'            => $plugindata->string ?? '',
                    ];
                }
            }
        }
        ksort($existingplugin);
        $context['usercontent'] = $usercontent;
        $context['existingplugin'] = array_values($existingplugin);

        return $context;
    }

    /**
     * Get tenant-scoped user count (active, non-deleted).
     *
     * @param string $pathfilter  e.g. "/1/%" — empty = all users
     * @return int
     */
    public static function count_active_users(string $pathfilter = ''): int {
        global $DB;

        $sql = "SELECT COUNT(id) FROM {user} WHERE deleted = 0 AND suspended = 0";
        $params = [];

        if (!empty($pathfilter)) {
            $sql .= " AND open_path LIKE :upath";
            $params['upath'] = $pathfilter;
        }

        return (int) $DB->count_records_sql($sql, $params);
    }
}
