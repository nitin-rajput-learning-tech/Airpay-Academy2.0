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

        // Inject skills summary + radar + per-skill detail rows
        // (Phase E.1, 2026-05-08 — full skills tab on profile).
        if (file_exists($CFG->dirroot . '/local/airpay_skills/classes/skills_manager.php')) {
            try {
                $analysis = \local_airpay_skills\skills_manager::get_gap_analysis($userid);
                if ($analysis['has_data'] ?? false) {
                    $context['ap_skills_summary'] = $analysis['summary'];
                    $context['ap_skills_designation'] = $analysis['designation'] ?? '';
                    $context['ap_skills_rows'] = $analysis['skills'] ?? [];
                    $context['ap_has_skills'] = !empty($analysis['skills']);

                    // Radar chart data for visual at-a-glance.
                    $radar = \local_airpay_skills\skills_manager::get_radar_data($userid);
                    if ($radar['has_radar'] ?? false) {
                        $context['ap_skills_radar'] = $radar;
                        $context['ap_has_radar'] = true;
                    }
                }
                // Recommended courses to fill gaps.
                $gap_courses = \local_airpay_skills\skills_manager::get_gap_courses($userid, 5);
                if (!empty($gap_courses)) {
                    $context['ap_skills_gap_courses'] = $gap_courses;
                    $context['ap_has_gap_courses'] = true;
                }
            } catch (\Throwable $e) {
                // Skills not available.
                debugging('Skills enrichment failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        // Phase E.2 (2026-05-08) — grades widget: completed courses + course-level grade.
        try {
            $grades = self::get_grades_summary($userid, 6);
            if (!empty($grades['courses'])) {
                $context['ap_grades_summary'] = $grades['summary'];
                $context['ap_grades_recent']  = $grades['courses'];
                $context['ap_has_grades']     = true;
            }
        } catch (\Throwable $e) {
            debugging('Grades enrichment failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
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
     * Phase E.2 (2026-05-08) — grades summary for profile widget.
     *
     * Returns recent course completions + their final grade as a percentage.
     * Reads from {course_completions} for the completion timestamp and
     * {grade_grades} joined to the course's grade_item (itemtype='course')
     * for the percentage.
     *
     * @param int $userid
     * @param int $limit  Max recent courses to return
     * @return array{summary: array{completed:int, average_pct:?int,
     *                              has_grade_count:int},
     *                courses: list<array{courseid:int, fullname:string,
     *                                    shortname:string, viewurl:string,
     *                                    grade_pct:?int, completed_at:int,
     *                                    completed_label:string}>}
     */
    public static function get_grades_summary(int $userid, int $limit = 6): array {
        global $DB;

        $rows = $DB->get_records_sql("
            SELECT cc.id, cc.course, cc.timecompleted,
                   c.fullname, c.shortname,
                   gi.id AS grade_item_id, gi.grademax, gi.grademin,
                   gg.finalgrade
              FROM {course_completions} cc
              JOIN {course} c ON c.id = cc.course
         LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
         LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = cc.userid
             WHERE cc.userid = :uid AND cc.timecompleted > 0
               AND c.id <> :siteid
          ORDER BY cc.timecompleted DESC",
            ['uid' => $userid, 'siteid' => SITEID], 0, $limit);

        $courses = [];
        $total_pct = 0; $with_grade = 0;
        foreach ($rows as $r) {
            $pct = null;
            if ($r->finalgrade !== null && $r->grademax > $r->grademin) {
                $pct = (int) round(
                    (((float) $r->finalgrade - (float) $r->grademin)
                        / ((float) $r->grademax - (float) $r->grademin)) * 100);
                $pct = max(0, min(100, $pct));
                $total_pct += $pct;
                $with_grade++;
            }
            $courses[] = [
                'courseid'        => (int) $r->course,
                'fullname'        => format_string($r->fullname),
                'shortname'       => format_string($r->shortname),
                'viewurl'         => (new \moodle_url('/course/view.php',
                    ['id' => $r->course]))->out(false),
                'grade_pct'       => $pct,
                'has_grade'       => $pct !== null,
                'completed_at'    => (int) $r->timecompleted,
                'completed_label' => userdate((int) $r->timecompleted,
                    get_string('strftimedatemonthabbr', 'core_langconfig')),
            ];
        }

        $total_completed = $DB->count_records_select('course_completions',
            'userid = :uid AND timecompleted > 0',
            ['uid' => $userid]);

        return [
            'summary' => [
                'completed'       => $total_completed,
                'has_grade_count' => $with_grade,
                'average_pct'     => $with_grade > 0
                    ? (int) round($total_pct / $with_grade) : null,
            ],
            'courses' => $courses,
        ];
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

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Custom open_* fields that we own.
     * Used when copying form data to user record before insert/update.
     */
    private const CUSTOM_FIELDS = [
        'open_employeeid', 'open_designation', 'open_supervisorid',
        'open_path', 'open_costcenterid', 'open_departmentid',
        'open_location', 'open_team', 'open_grade', 'open_zone',
        'open_region', 'open_employmenttype', 'open_joindate',
        'open_dateofbirth', 'open_prefix', 'open_client',
        'open_hrmsrole',
    ];

    /**
     * Create a new user.
     *
     * Wraps Moodle's user_create_user() to ensure all events fire and
     * filearea is set up correctly. After creation, applies custom open_*
     * fields directly to the user record (since user_create_user() doesn't
     * know about them).
     *
     * @param object $data  Form data with: username, email, firstname, lastname,
     *                      auth, password, plus optional open_* fields
     * @return int  New user ID
     * @throws \moodle_exception  On validation failure
     */
    public static function create(object $data): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/moodlelib.php');

        // Validate required fields.
        if (empty($data->username) || empty($data->email) ||
            empty($data->firstname) || empty($data->lastname)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_users');
        }

        // Check uniqueness.
        if ($DB->record_exists('user', ['username' => strtolower($data->username),
                                         'mnethostid' => $CFG->mnet_localhost_id])) {
            throw new \moodle_exception('usernametaken', 'local_airpay_users');
        }
        if ($DB->record_exists('user', ['email' => $data->email, 'deleted' => 0])) {
            throw new \moodle_exception('emailtaken', 'local_airpay_users');
        }

        // Build user record for core API.
        $user = new \stdClass();
        $user->username   = strtolower(trim($data->username));
        $user->email      = trim($data->email);
        $user->firstname  = trim($data->firstname);
        $user->lastname   = trim($data->lastname);
        $user->auth       = $data->auth ?? 'manual';
        $user->confirmed  = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->lang       = $data->lang ?? $CFG->lang;
        $user->timezone   = $data->timezone ?? '99';
        $user->city       = $data->city ?? '';
        $user->country    = $data->country ?? '';
        $user->phone1     = $data->phone1 ?? '';
        $user->department = $data->department ?? '';

        // Password (only for manual auth).
        $password = '';
        if ($user->auth === 'manual' && !empty($data->password)) {
            $password = $data->password;
        }

        // Create via core API (fires events, sets up filearea).
        $userid = user_create_user($user, false, true);

        // Set password separately so it gets hashed properly.
        if ($password) {
            $user->id = $userid;
            update_internal_user_password($user, $password);
        }

        // Apply custom open_* fields.
        self::apply_custom_fields($userid, $data);

        // Email welcome (if requested and password set).
        if (!empty($data->emailwelcome) && $password) {
            $user->id = $userid;
            setnew_password_and_mail($user);
            unset_user_preference('create_password', $user);
            set_user_preference('auth_forcepasswordchange', 1, $user);
        }

        return $userid;
    }

    /**
     * Update an existing user.
     *
     * @param int $userid
     * @param object $data  Form data
     * @return bool  Success
     * @throws \moodle_exception
     */
    public static function update(int $userid, object $data): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $existing = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        // Build update record. Only include fields that changed/were sent.
        $user = new \stdClass();
        $user->id = $userid;

        // Standard fields.
        $stdfields = ['email', 'firstname', 'lastname', 'city', 'country',
                      'phone1', 'department', 'timezone', 'lang'];
        foreach ($stdfields as $field) {
            if (isset($data->$field)) {
                $user->$field = trim((string) $data->$field);
            }
        }

        // Email uniqueness (if changed).
        if (isset($user->email) && $user->email !== $existing->email) {
            if ($DB->record_exists_select('user',
                'email = :email AND deleted = 0 AND id != :uid',
                ['email' => $user->email, 'uid' => $userid])) {
                throw new \moodle_exception('emailtaken', 'local_airpay_users');
            }
        }

        // Update via core API (fires events).
        user_update_user($user, false, true);

        // Apply custom open_* fields.
        self::apply_custom_fields($userid, $data);

        // Password change (if provided).
        if (!empty($data->newpassword)) {
            $userobj = $DB->get_record('user', ['id' => $userid]);
            update_internal_user_password($userobj, $data->newpassword);
        }

        return true;
    }

    /**
     * Apply custom open_* fields directly to user record.
     *
     * @param int $userid
     * @param object $data  Form data
     */
    private static function apply_custom_fields(int $userid, object $data): void {
        global $DB;

        $update = ['id' => $userid];
        foreach (self::CUSTOM_FIELDS as $field) {
            if (property_exists($data, $field) && $data->$field !== null) {
                $value = $data->$field;
                // Date fields → unix timestamp.
                if (in_array($field, ['open_joindate', 'open_dateofbirth'], true)) {
                    if (is_array($value) && !empty($value)) {
                        $value = mktime(0, 0, 0, $value['mon'] ?? 1, $value['day'] ?? 1, $value['year'] ?? 2000);
                    } else if (is_string($value) && !empty($value)) {
                        $value = strtotime($value);
                    }
                }
                $update[$field] = $value;
            }
        }

        // Auto-derive open_path from open_costcenterid if path not set.
        if (!isset($update['open_path']) && !empty($update['open_costcenterid'])) {
            $org = $DB->get_record('local_airpay_org', ['id' => $update['open_costcenterid']]);
            if ($org) {
                $update['open_path'] = $org->path;
            }
        }

        if (count($update) > 1) {
            $DB->update_record('user', (object) $update);
        }
    }

    /**
     * Toggle suspended status of a user.
     *
     * @param int $userid
     * @param bool|null $suspended  null = toggle current state
     * @return bool  New suspended state
     * @throws \moodle_exception
     */
    public static function suspend(int $userid, ?bool $suspended = null): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, suspended', MUST_EXIST);

        $newstate = $suspended ?? !((bool) $user->suspended);
        $update = (object) ['id' => $userid, 'suspended' => $newstate ? 1 : 0];
        user_update_user($update, false, true);

        // Kill active sessions if suspending.
        if ($newstate) {
            \core\session\manager::kill_user_sessions($userid);
        }

        return $newstate;
    }

    /**
     * Delete a user (soft delete).
     *
     * @param int $userid
     * @return bool
     * @throws \moodle_exception
     */
    public static function delete(int $userid): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/moodlelib.php');

        // Block deleting yourself or admin.
        global $USER;
        if ($userid == $USER->id) {
            throw new \moodle_exception('cannotdeleteself', 'local_airpay_users');
        }
        if ($userid <= 2) {
            throw new \moodle_exception('cannotdeletesystemuser', 'local_airpay_users');
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
        return delete_user($user);
    }
}
