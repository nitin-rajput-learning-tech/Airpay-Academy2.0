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

/**
 * A two dashboard layout for the epsilon theme.
 *
 * @package   theme_airpayux
 * @copyright 2018 eAbyas Info Solutons Pvt Ltd, India
 * @author    eAbyas  <info@eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Guests should not see the dashboard — send them to the login page.
if (!isloggedin() || isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}

// First-login onboarding — redirect new learners to the onboarding wizard.
// Skip for users who shouldn't see the learner-style "what do you want to learn?"
// flow:
//   - Site admins
//   - L&D admins (administrator role at category context)
//   - Users with course-management capability
//   - Managers (anyone who has at least one direct report) — they'll see a
//     manager-specific dashboard; learner onboarding doesn't fit their role.
//
// UX rationale: managers/supervisors aren't enrolling for learning paths
// themselves — they're tracking their team's progress. Forcing them through
// "Pick interests / Set weekly goal" is wrong-shaped for their workflow.
// Filed as the "manager onboarding UX" finding from P0 audit; resolved here.
global $DB;
$is_supervisor = $DB->record_exists_select(
    'user',
    'open_supervisorid = :uid AND deleted = 0',
    ['uid' => $USER->id]);
$has_any_admin_role = is_siteadmin()
    || has_capability('local/courses:manage', context_system::instance())
    || $DB->record_exists_sql(
        "SELECT 1 FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ra.userid = :uid AND ra.roleid = 9 AND ctx.contextlevel = 40",
        ['uid' => $USER->id]);
if (!$has_any_admin_role && !$is_supervisor) {
    $onboarded = get_user_preferences('airpay_onboarding_complete', 0, $USER->id);
    if (!$onboarded) {
        redirect(new moodle_url('/local/airpay_pages/onboarding.php'));
    }
}

// Removed: user_preference_allow_ajax_update() — deprecated in Moodle 5.1
require_once($CFG->libdir . '/behat/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

$extraclasses = [];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
$PAGE->set_secondary_navigation(false);
$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions()  && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$layerone_detail_full = $OUTPUT->blocks('layerone_full', 'col-md-12');
$layerone_detail_one = $OUTPUT->blocks('layerone_one', 'col-md-7 float-left');
$layerone_detail_two = $OUTPUT->blocks('layerone_two', 'col-md-5 float-left');

$layertwo_detail_one = $OUTPUT->blocks('layertwo_one', 'col-md-12');
$layertwo_detail_two = $OUTPUT->blocks('layertwo_two', 'col-md-12');
$layertwo_detail_three = $OUTPUT->blocks('layertwo_three', 'col-md-6 float-left');
$layertwo_detail_four = $OUTPUT->blocks('layertwo_four', 'col-md-6 float-left');

$layertwo_three_one = $OUTPUT->blocks('layerthree_one', 'col-md-12');
$layertwo_three_two = $OUTPUT->blocks('layerthree_two', 'col-md-12');

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);
$OUTPUT->seteditswtich_display(true);

// ═══════════════════════════════════════════════════════════
// Airpay Academy UX — Dashboard data injection (Sprint 3)
// ═══════════════════════════════════════════════════════════

$airpay_dashboard = [];
if (isloggedin() && !isguestuser()) {
    global $DB, $USER;

    // Get user's first name for greeting.
    $airpay_dashboard['firstname'] = $USER->firstname ?? 'Learner';

    // --- Role detection (4 tiers) ---
    // Siteadmin: is_siteadmin() — sees everything including System Health
    // L&D Admin: has local/courses:manage but is NOT siteadmin — sees admin dashboard without System Health
    // Manager: has moodle/site:viewreports but NOT local/courses:manage — sees team + learner
    // Learner: everyone else — sees learner dashboard only
    $systemcontext = context_system::instance();
    $issiteadmin = is_siteadmin();

    // ═══ BizLMS Role Switch Detection ═══
    // If user has switched to a lower role (e.g., admin → employee), respect it.
    // BizLMS stores active role in $USER->useraccess['currentroleinfo'] or $SESSION.
    $switched_to_employee = false;
    $employee_role_id = (int)$DB->get_field('role', 'id', ['shortname' => 'employee']);

    // Check our session-based switch (from /my/switchrole.php).
    if (!empty($SESSION->airpay_switchrole->roleid)) {
        $switched_roleid = (int)$SESSION->airpay_switchrole->roleid;
        if ($switched_roleid === $employee_role_id) {
            $switched_to_employee = true;
        }
    }

    // Check BizLMS $USER->useraccess role switch (set during BizLMS login/switch flow).
    if (!$switched_to_employee && !empty($USER->useraccess['currentroleinfo']['contextinfo'])) {
        $firstrole = current($USER->useraccess['currentroleinfo']['contextinfo']);
        $active_roleid = (int)($firstrole['roleid'] ?? 0);
        if ($active_roleid === $employee_role_id) {
            $switched_to_employee = true;
        }
    }

    // L&D Admin detection — SKIP if user has switched to employee role.
    // BizLMS assigns 'administrator' role at category context (level 40), not system (level 10).
    $isldadmin = false;
    if (!$issiteadmin && !$switched_to_employee) {
        $isldadmin = has_capability('local/courses:manage', $systemcontext);
        if (!$isldadmin) {
            // BizLMS fallback: check if user has administrator role (id=9) at any category context.
            $hasbizlmsadmin = $DB->record_exists_sql(
                "SELECT 1 FROM {role_assignments} ra
                 JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :uid AND ra.roleid = 9 AND ctx.contextlevel = 40",
                ['uid' => $USER->id]
            );
            $isldadmin = $hasbizlmsadmin;
        }
    }
    $isadmin = $issiteadmin || $isldadmin; // Both get admin dashboard
    // Manager detection: capability OR has direct reports via open_supervisorid (BizLMS pattern)
    $ismanager = !$isadmin && has_capability('moodle/site:viewreports', $systemcontext);
    if (!$ismanager && !$isadmin) {
        $directreports = $DB->count_records_select('user',
            'open_supervisorid = :uid AND deleted = 0 AND suspended = 0',
            ['uid' => $USER->id]);
        if ($directreports > 0) {
            $ismanager = true;
        }
    }
    $islearner = !$isadmin && !$ismanager;

    $airpay_dashboard['issiteadmin'] = $issiteadmin; // System Health only
    $airpay_dashboard['isadmin'] = $isadmin;          // Admin dashboard (KPIs, charts, quick nav)
    $airpay_dashboard['isldadmin'] = $isldadmin;      // L&D admin (admin dash without system health)
    $airpay_dashboard['ismanager'] = $ismanager;       // Team + learner
    $airpay_dashboard['islearner'] = !$isadmin;        // Managers + learners see learner sections
    $airpay_dashboard['team_url'] = (new moodle_url('/local/airpay_manager/index.php'))->out(false);

    // ═══════════════════════════════════════════════════════════
    // Gamification data — all non-admin users see points, badges, streaks
    // ═══════════════════════════════════════════════════════════
    if (!$isadmin && file_exists($CFG->dirroot . '/local/airpay_gamification/lib.php')) {
        require_once($CFG->dirroot . '/local/airpay_gamification/lib.php');
        try {
            $gamification = local_airpay_gamification_get_summary($USER->id);
            $airpay_dashboard['gamification'] = $gamification;
            $airpay_dashboard['hasgamification'] = true;

            // Leaderboard data.
            $leaderboard = \local_airpay_gamification\leaderboard::get_template_data($USER->id, 'department', 5);
            $airpay_dashboard['leaderboard'] = $leaderboard;
            $airpay_dashboard['hasleaderboard'] = $leaderboard['has_entries'] ?? false;

            // Streak calendar (last 7 days).
            $streakdays = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $label = ($i === 0) ? 'Today' : date('D', strtotime("-$i days"));
                // Check if user logged in on this date.
                $daystart = strtotime($date);
                $dayend = $daystart + 86400;
                $active = $DB->record_exists_select('local_airpay_points_log',
                    "userid = :uid AND action = 'daily_login' AND timecreated >= :start AND timecreated < :end",
                    ['uid' => $USER->id, 'start' => $daystart, 'end' => $dayend]);
                $streakdays[] = [
                    'date'     => $date,
                    'label'    => $label,
                    'active'   => $active,
                    'is_today' => ($i === 0),
                ];
            }
            $streak = $DB->get_record('local_airpay_streaks', ['userid' => $USER->id]);
            $airpay_dashboard['streak_calendar'] = [
                'days'           => $streakdays,
                'current_streak' => $streak->current_streak ?? 0,
                'longest_streak' => $streak->longest_streak ?? 0,
            ];
            $airpay_dashboard['hasstreakcalendar'] = true;
        } catch (\Exception $e) {
            $airpay_dashboard['hasgamification'] = false;
        }
    }

    if ($isadmin) {
        // ═══════════════════════════════════════════════════════════
        // ADMIN DASHBOARD — KPIs, charts, quick nav, activity feed
        // Siteadmin: global data. L&D admin: scoped to their tenant.
        // ═══════════════════════════════════════════════════════════
        try {
            // Tenant scoping: L&D admins see only their org's data
            $tenantfilter_user = '';
            $tenantfilter_course = '';
            $tenantparams = [];
            if ($isldadmin && !empty($USER->open_path)) {
                $parts = explode('/', $USER->open_path);
                $toporg = '/' . ($parts[1] ?? '');
                $tenantfilter_user = " AND open_path LIKE :upath";
                $tenantfilter_course = " AND open_path LIKE :cpath";
                $tenantparams['upath'] = $toporg . '%';
                $tenantparams['cpath'] = $toporg . '%';
            }

            $totalusers = $DB->count_records_select('user',
                'deleted = 0 AND suspended = 0 AND id > 1' . $tenantfilter_user,
                array_intersect_key($tenantparams, ['upath' => 1]));
            $activeusers = $DB->count_records_select('user',
                'deleted = 0 AND suspended = 0 AND lastaccess > :cutoff' . $tenantfilter_user,
                array_merge(['cutoff' => time() - (30 * 86400)], array_intersect_key($tenantparams, ['upath' => 1])));
            $totalcourses = $DB->count_records_select('course',
                'visible = 1 AND id > 1' . $tenantfilter_course,
                array_intersect_key($tenantparams, ['cpath' => 1]));
            // Tenant-scoped enrolments and completions.
            if (!empty($tenantfilter_user)) {
                $totalenrolments = $DB->count_records_sql(
                    "SELECT COUNT(ue.id) FROM {user_enrolments} ue
                     JOIN {user} u ON u.id = ue.userid
                     WHERE u.deleted = 0 AND u.open_path LIKE :upath",
                    ['upath' => ($tenantparams['upath'] ?? '%')]);
                $totalcompleted = $DB->count_records_sql(
                    "SELECT COUNT(cc.id) FROM {course_completions} cc
                     JOIN {user} u ON u.id = cc.userid
                     WHERE cc.timecompleted IS NOT NULL AND u.deleted = 0 AND u.open_path LIKE :upath",
                    ['upath' => ($tenantparams['upath'] ?? '%')]);
            } else {
                $totalenrolments = $DB->count_records_select('user_enrolments', '1=1');
                $totalcompleted = $DB->count_records_select('course_completions', 'timecompleted IS NOT NULL');
            }
            $completionrate = ($totalenrolments > 0) ? round(($totalcompleted / $totalenrolments) * 100, 1) : 0;

            // Month-over-month trends.
            $lastmonth = time() - (30 * 86400);
            $prevmonth = time() - (60 * 86400);
            $newusersthismonth = $DB->count_records_select('user',
                'timecreated > :since AND deleted = 0' . $tenantfilter_user,
                array_merge(['since' => $lastmonth], array_intersect_key($tenantparams, ['upath' => 1])));
            if (!empty($tenantfilter_user)) {
                $newenrolmentsthisweek = $DB->count_records_sql(
                    "SELECT COUNT(ue.id) FROM {user_enrolments} ue
                     JOIN {user} u ON u.id = ue.userid
                     WHERE ue.timestart > :since AND u.open_path LIKE :upath",
                    ['since' => time() - (7 * 86400), 'upath' => ($tenantparams['upath'] ?? '%')]);
            } else {
                $newenrolmentsthisweek = $DB->count_records_select('user_enrolments',
                    'timestart > :since', ['since' => time() - (7 * 86400)]);
            }

            // Show tenant scope label for L&D admins
            if ($isldadmin && !empty($toporg)) {
                $tenantname = \local_airpay_org\org_manager::get_name_by_path($toporg);
                $airpay_dashboard['tenant_scope'] = $tenantname ?: 'Your Organization';
            }

            // Better KPIs — active users instead of total, overdue instead of completion %
            $airpay_dashboard['admin_kpis'] = [
                ['label' => 'Active Users', 'value' => number_format($activeusers), 'trend' => number_format($totalusers) . ' total', 'icon' => 'users', 'color' => 'primary'],
                ['label' => 'Courses', 'value' => number_format($totalcourses), 'trend' => '+' . $newenrolmentsthisweek . ' enrolments this week', 'icon' => 'book', 'color' => 'accent'],
                ['label' => 'Completions', 'value' => number_format($totalcompleted), 'trend' => $completionrate . '% completion rate', 'icon' => 'check-circle', 'color' => 'success'],
                ['label' => 'Enrolments', 'value' => number_format($totalenrolments), 'trend' => '+' . $newusersthismonth . ' new users this month', 'icon' => 'line-chart', 'color' => 'gold'],
            ];
            $airpay_dashboard['hasadminkpis'] = true;

            // ── Compliance Summary ──
            try {
                $dbman = $DB->get_manager();
                if ($dbman->table_exists('local_airpay_compl_courses')) {
                    $mandatorycount = $DB->count_records('local_airpay_compl_courses');
                    $overduecount = $DB->count_records_select('local_airpay_compl_snapshot',
                        "status IN ('overdue','critical','escalated')");
                    $compliantcount = $DB->count_records_select('local_airpay_compl_snapshot',
                        "status = 'completed'");
                    $totalassigned = $DB->count_records('local_airpay_compl_snapshot');
                    $compliancepct = $totalassigned > 0 ? round(($compliantcount / $totalassigned) * 100) : 0;
                    $airpay_dashboard['compliance'] = [
                        'mandatory'   => $mandatorycount,
                        'overdue'     => $overduecount,
                        'compliant'   => $compliancepct . '%',
                        'assigned'    => $totalassigned,
                    ];
                    $airpay_dashboard['hascompliance'] = true;
                }
            } catch (\Throwable $e) {
                // Compliance tables may not exist.
            }

            // ── Recent Activity (last 10 events) ──
            try {
                $recentactivity = [];

                // Recent completions.
                $completions = $DB->get_records_sql(
                    "SELECT cc.id, u.firstname, u.lastname, c.fullname AS coursename, cc.timecompleted
                       FROM {course_completions} cc
                       JOIN {user} u ON u.id = cc.userid
                       JOIN {course} c ON c.id = cc.course
                      WHERE cc.timecompleted IS NOT NULL AND cc.timecompleted > 0
                   ORDER BY cc.timecompleted DESC", [], 0, 5);
                foreach ($completions as $comp) {
                    $recentactivity[] = [
                        'icon'  => 'check-circle',
                        'color' => '#16a34a',
                        'text'  => fullname($comp) . ' completed ' . format_string($comp->coursename),
                        'time'  => userdate($comp->timecompleted, '%d %b, %I:%M %p'),
                        'ts'    => $comp->timecompleted,
                    ];
                }

                // Recent enrolments.
                $enrolments = $DB->get_records_sql(
                    "SELECT ue.id, u.firstname, u.lastname, c.fullname AS coursename, ue.timecreated
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                       JOIN {user} u ON u.id = ue.userid
                       JOIN {course} c ON c.id = e.courseid
                      WHERE ue.timecreated > 0
                   ORDER BY ue.timecreated DESC", [], 0, 5);
                foreach ($enrolments as $enr) {
                    $recentactivity[] = [
                        'icon'  => 'plus-circle',
                        'color' => '#0066A7',
                        'text'  => fullname($enr) . ' enrolled in ' . format_string($enr->coursename),
                        'time'  => userdate($enr->timecreated, '%d %b, %I:%M %p'),
                        'ts'    => $enr->timecreated,
                    ];
                }

                // Sort by timestamp descending, take top 8.
                usort($recentactivity, function($a, $b) { return $b['ts'] - $a['ts']; });
                $airpay_dashboard['recentactivity'] = array_slice($recentactivity, 0, 8);
                $airpay_dashboard['hasrecentactivity'] = !empty($airpay_dashboard['recentactivity']);
            } catch (\Throwable $e) {
                // Silently skip if queries fail.
            }

            // ── Top Courses (most active this month) ──
            try {
                $topcourses = $DB->get_records_sql(
                    "SELECT c.id, c.fullname, COUNT(ue.id) AS enrolcount,
                            (SELECT COUNT(cc2.id) FROM {course_completions} cc2
                             WHERE cc2.course = c.id AND cc2.timecompleted IS NOT NULL) AS completions
                       FROM {course} c
                       JOIN {enrol} e ON e.courseid = c.id
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                      WHERE c.visible = 1 AND c.id > 1
                   GROUP BY c.id, c.fullname
                   ORDER BY enrolcount DESC", [], 0, 5);
                $airpay_dashboard['topcourses'] = [];
                foreach ($topcourses as $tc) {
                    $airpay_dashboard['topcourses'][] = [
                        'name'        => format_string($tc->fullname),
                        'enrolled'    => number_format($tc->enrolcount),
                        'completions' => number_format($tc->completions),
                        'url'         => (new moodle_url('/course/view.php', ['id' => $tc->id]))->out(false),
                    ];
                }
                $airpay_dashboard['hastopcourses'] = !empty($airpay_dashboard['topcourses']);
            } catch (\Throwable $e) {
                // Silently skip.
            }

            // Enrollment data by month (last 6 months) for Chart.js.
            $chartdata = [];
            $chartlabels = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthstart = strtotime("-$i months", strtotime('first day of this month'));
                $monthend = strtotime("+1 month", $monthstart);
                $monthname = date('M', $monthstart);
                $count = $DB->count_records_select('user_enrolments',
                    'timestart >= :start AND timestart < :end',
                    ['start' => $monthstart, 'end' => $monthend]);
                $chartlabels[] = $monthname;
                $chartdata[] = $count;
            }
            $airpay_dashboard['chart_labels'] = json_encode($chartlabels);
            $airpay_dashboard['chart_enrolments'] = json_encode($chartdata);

            // Course distribution by category for pie chart.
            $catdist = $DB->get_records_sql(
                "SELECT cc.name, COUNT(c.id) as cnt
                   FROM {course} c
                   JOIN {course_categories} cc ON cc.id = c.category
                  WHERE c.visible = 1 AND c.id > 1
               GROUP BY cc.name
               ORDER BY cnt DESC", [], 0, 5);
            $pieLabels = [];
            $pieData = [];
            foreach ($catdist as $cd) {
                $pieLabels[] = format_string($cd->name);
                $pieData[] = (int)$cd->cnt;
            }
            $airpay_dashboard['chart_pie_labels'] = json_encode($pieLabels);
            $airpay_dashboard['chart_pie_data'] = json_encode($pieData);
            $airpay_dashboard['hascharts'] = true;

            // Quick navigation links with tenant-scoped live stats.
            // $activeusers already computed above with tenant filter — don't overwrite.
            $inactiveusers = max(0, $totalusers - $activeusers);
            $classroomcount = 0;
            $examcount = 0;
            try {
                if (!empty($tenantfilter_course)) {
                    $classroomcount = $DB->count_records_sql(
                        "SELECT COUNT(*) FROM {local_classroom} WHERE open_path LIKE :p",
                        ['p' => ($tenantparams['cpath'] ?? '%')]);
                } else {
                    $classroomcount = $DB->count_records_select('local_classroom', '1=1');
                }
            } catch (Exception $e) {}
            try { $examcount = $DB->count_records('local_onlineexams'); } catch (Exception $e) {}

            $airpay_dashboard['admin_quicknav'] = [
                ['label' => 'Manage Users', 'icon' => 'users', 'url' => (new moodle_url('/local/users/index.php'))->out(false), 'color' => '#0066A7',
                 'hasstats' => true, 'stats' => [
                    ['statval' => $totalusers, 'statlabel' => 'Total'],
                    ['statval' => $activeusers, 'statlabel' => 'Active'],
                    ['statval' => $inactiveusers, 'statlabel' => 'Inactive'],
                ]],
                ['label' => 'Manage Courses', 'icon' => 'book', 'url' => (new moodle_url('/local/courses/courses.php'))->out(false), 'color' => '#0f7a73',
                 'hasstats' => true, 'stats' => [
                    ['statval' => $totalcourses, 'statlabel' => 'Total'],
                    ['statval' => number_format($totalenrolments), 'statlabel' => 'Enrolments'],
                    ['statval' => number_format($totalcompleted), 'statlabel' => 'Completions'],
                ]],
                ['label' => 'Reports', 'icon' => 'bar-chart', 'url' => (new moodle_url('/blocks/learnerscript/managereport.php'))->out(false), 'color' => '#7c3aed'],
                ['label' => 'Online Exams', 'icon' => 'pencil-square-o', 'url' => (new moodle_url('/local/onlineexams/index.php'))->out(false), 'color' => '#d97706'],
                ['label' => 'Classrooms', 'icon' => 'calendar', 'url' => (new moodle_url('/local/classroom/index.php'))->out(false), 'color' => '#dc2626',
                 'hasstats' => ($classroomcount > 0), 'stats' => [
                    ['statval' => $classroomcount, 'statlabel' => 'Total'],
                ]],
                ['label' => 'Compliance', 'icon' => 'shield', 'url' => (new moodle_url('/local/airpay_compliance_report/index.php'))->out(false), 'color' => '#16a34a',
                 'hasstats' => true, 'stats' => (function() use ($DB) {
                    try {
                        $mandatory = $DB->count_records('local_airpay_compl_courses');
                        $overdue = $DB->count_records_select('local_airpay_compl_snapshot', "status IN ('overdue','critical','escalated')");
                        return [
                            ['statval' => $mandatory, 'statlabel' => 'Mandatory'],
                            ['statval' => $overdue, 'statlabel' => 'Overdue'],
                        ];
                    } catch (Exception $e) { return []; }
                 })()],
                ['label' => 'Privacy (DPDP)', 'icon' => 'lock', 'url' => (new moodle_url('/local/airpay_privacy/index.php'))->out(false), 'color' => '#7c3aed',
                 'hasstats' => true, 'stats' => (function() use ($DB) {
                    try {
                        $pending = $DB->count_records('local_privacy_requests', ['status' => 'pending']);
                        return [['statval' => $pending, 'statlabel' => 'Pending']];
                    } catch (Exception $e) { return []; }
                 })()],
                ['label' => 'Site Settings', 'icon' => 'cog', 'url' => (new moodle_url('/admin/index.php'))->out(false), 'color' => '#6b7280'],
            ];
            $airpay_dashboard['hasquicknav'] = true;

            // --- System Health ---
            $cronlast = $DB->get_field_sql("SELECT MAX(lastruntime) FROM {task_scheduled} WHERE lastruntime > 0");
            $pendingupgrades = $DB->count_records_select('config_plugins', "name = 'version'") > 0;
            $moodledatapath = $CFG->dataroot;
            $diskfree = @disk_free_space($moodledatapath);
            $disktotal = @disk_total_space($moodledatapath);
            $diskpercent = ($disktotal > 0) ? round((1 - $diskfree / $disktotal) * 100) : 0;

            $airpay_dashboard['systemhealth'] = [
                ['label' => 'Cron Last Run', 'value' => $cronlast ? userdate($cronlast, '%d %b, %I:%M %p') : 'Never',
                 'icon' => 'clock-o', 'status' => ($cronlast && (time() - $cronlast) < 3600) ? 'ok' : 'warning'],
                ['label' => 'Moodle Version', 'value' => $CFG->release ?? 'Unknown',
                 'icon' => 'info-circle', 'status' => 'ok'],
                ['label' => 'Disk Usage', 'value' => $diskpercent . '% used',
                 'icon' => 'database', 'status' => ($diskpercent < 80) ? 'ok' : 'warning'],
                ['label' => 'PHP Version', 'value' => phpversion(),
                 'icon' => 'code', 'status' => 'ok'],
            ];
            $airpay_dashboard['hassystemhealth'] = true;

            // --- User Analytics ---
            $loginstoday = $DB->count_records_select('logstore_standard_log',
                "eventname = '\\\\core\\\\event\\\\user_loggedin' AND timecreated > :today",
                ['today' => strtotime('today')]);
            $loginsweek = $DB->count_records_select('logstore_standard_log',
                "eventname = '\\\\core\\\\event\\\\user_loggedin' AND timecreated > :week",
                ['week' => time() - (7 * 86400)]);
            $newusersweek = $DB->count_records_select('user',
                'timecreated > :week AND deleted = 0', ['week' => time() - (7 * 86400)]);
            $neverloggedin = $DB->count_records_select('user',
                'lastlogin = 0 AND deleted = 0 AND suspended = 0 AND id > 1');
            $inactive30 = $DB->count_records_select('user',
                'lastaccess > 0 AND lastaccess < :cutoff AND deleted = 0 AND suspended = 0',
                ['cutoff' => time() - (30 * 86400)]);

            $airpay_dashboard['useranalytics'] = [
                ['label' => 'Logins Today', 'value' => $loginstoday, 'icon' => 'sign-in', 'color' => '#0066A7'],
                ['label' => 'Logins This Week', 'value' => $loginsweek, 'icon' => 'calendar-check-o', 'color' => '#0f7a73'],
                ['label' => 'New Users (7d)', 'value' => $newusersweek, 'icon' => 'user-plus', 'color' => '#16a34a'],
                ['label' => 'Never Logged In', 'value' => $neverloggedin, 'icon' => 'user-times', 'color' => ($neverloggedin > 0) ? '#d97706' : '#6b7280'],
                ['label' => 'Inactive (30d+)', 'value' => $inactive30, 'icon' => 'hourglass-end', 'color' => ($inactive30 > 0) ? '#dc2626' : '#6b7280'],
            ];
            $airpay_dashboard['hasuseranalytics'] = true;

        } catch (Exception $e) {
            $airpay_dashboard['hasadminkpis'] = false;
            $airpay_dashboard['hascharts'] = false;
            $airpay_dashboard['hasquicknav'] = false;
            $airpay_dashboard['hasactivityfeed'] = false;
        }
    } else {
    // ═══════════════════════════════════════════════════════════
    // LEARNER DASHBOARD — courses, progress, deadlines, achievements
    // ═══════════════════════════════════════════════════════════

    // Get enrolled courses with progress
    try {
        $enrolledcourses = enrol_get_all_users_courses($USER->id, true);
        $completed = 0;
        $inprogress = 0;
        $notstarted = 0;
        $continuecourses = [];

        foreach ($enrolledcourses as $course) {
            $completion = new completion_info($course);
            $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);

            if ($progress !== null && $progress >= 100) {
                $completed++;
            } else if ($progress !== null && $progress > 0) {
                $inprogress++;
                // Status — Phase B0 iter 3: feeds course_progress_card's badge.
                // "overdue" if the course end-date has passed and progress<100.
                $is_overdue = !empty($course->enddate)
                    && (int) $course->enddate > 0
                    && (int) $course->enddate < time();
                $continuecourses[] = [
                    'id' => $course->id,
                    'fullname' => format_string($course->fullname),
                    'shortname' => format_string($course->shortname),
                    'progress' => round($progress),
                    'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'status' => $is_overdue ? 'overdue' : 'in_progress',
                    'statuslabel' => $is_overdue ? 'Overdue' : 'In progress',
                ];
            } else {
                $notstarted++;
                // Also show recently enrolled not-started courses
                if (count($continuecourses) < 6) {
                    $continuecourses[] = [
                        'id' => $course->id,
                        'fullname' => format_string($course->fullname),
                        'shortname' => format_string($course->shortname),
                        'progress' => 0,
                        'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                        'status' => 'not_started',
                        'statuslabel' => 'Not started',
                    ];
                }
            }
        }

        // Limit to 6 courses for the dashboard
        $airpay_dashboard['continuecourses'] = array_slice($continuecourses, 0, 6);
        $airpay_dashboard['hascontinuecourses'] = count($continuecourses) > 0;
        $airpay_dashboard['stats'] = [
            'enrolled' => count($enrolledcourses),
            'inprogress' => $inprogress,
            'completed' => $completed,
            'notstarted' => $notstarted,
        ];
        $airpay_dashboard['hasstats'] = true;

        // Progress ring data for completion percentage.
        $totalenrolled = count($enrolledcourses);
        $completionpct = ($totalenrolled > 0) ? round(($completed / $totalenrolled) * 100) : 0;
        // SVG circle: radius=36, circumference=2*pi*36=226.19
        $circumference = 226.19;
        $offset = $circumference - ($circumference * $completionpct / 100);
        $ringcolor = ($completionpct >= 80) ? 'success' : (($completionpct >= 40) ? 'accent' : 'warning');
        $airpay_dashboard['progress_ring'] = [
            'percent'       => $completionpct,
            'circumference' => $circumference,
            'offset'        => round($offset, 2),
            'color_class'   => $ringcolor,
        ];
        $airpay_dashboard['has_progress_ring'] = true;

    } catch (Exception $e) {
        // Silently fail — dashboard still renders without our additions
        $airpay_dashboard['hasstats'] = false;
        $airpay_dashboard['hascontinuecourses'] = false;
    }

    // Get certificate count (if available)
    try {
        $certcount = $DB->count_records('tool_certificate_issues', ['userid' => $USER->id]);
        $airpay_dashboard['stats']['certificates'] = $certcount;
    } catch (Exception $e) {
        $airpay_dashboard['stats']['certificates'] = 0;
    }

    // Phase B0 iter 2 (2026-05-14) — learner KPI tiles as a data array so
    // the dashboard template can iterate the stat_card partial instead of
    // inlining four near-identical <div> blocks. Mirrors admin_kpis shape.
    $airpay_dashboard['learner_kpis'] = [
        [
            'label' => 'Enrolled',
            'value' => (int) ($airpay_dashboard['stats']['enrolled'] ?? 0),
            'icon'  => 'book',
            'color' => 'primary',
        ],
        [
            'label' => 'In Progress',
            'value' => (int) ($airpay_dashboard['stats']['inprogress'] ?? 0),
            'icon'  => 'spinner',
            'color' => 'accent',
        ],
        [
            'label' => 'Completed',
            'value' => (int) ($airpay_dashboard['stats']['completed'] ?? 0),
            'icon'  => 'check-circle',
            'color' => 'success',
        ],
        [
            'label' => 'Certificates',
            'value' => (int) ($airpay_dashboard['stats']['certificates'] ?? 0),
            'icon'  => 'certificate',
            'color' => 'warning',
        ],
    ];
    $airpay_dashboard['haslearnerkpis'] = !empty($airpay_dashboard['hasstats']);

    // --- Section: Manager Team Overview (only for managers) ---
    // Uses local_airpay_manager\team_manager — batches 4 aggregate queries
    // instead of running N+1 progress calculations per team member.
    if ($ismanager) {
        try {
            $teammembers = \local_airpay_manager\team_manager::get_team((int) $USER->id);
            $teamsummary = \local_airpay_manager\team_manager::summarize_team($teammembers);

            $teamcompliancelist = [];
            $teamenrolled = 0;
            $teamcompleted = 0;
            $teamoverdue = 0;

            foreach ($teamsummary as $row) {
                $teamenrolled  += $row['enrolled'];
                $teamcompleted += $row['completed'];
                $teamoverdue   += $row['overdue'];
                $teamcompliancelist[] = [
                    'name'         => $row['fullname'],
                    'enrolled'     => $row['enrolled'],
                    'completed'    => $row['completed'],
                    'pending'      => max(0, $row['enrolled'] - $row['completed']),
                    'haspending'   => max(0, $row['enrolled'] - $row['completed']) > 0,
                    'overdue'      => $row['overdue'],
                    'has_overdue'  => $row['has_overdue'],
                    'rate'         => $row['rate'],
                    'rate_class'   => $row['rate_class'],
                    'lastaccess'   => $row['lastlogin'],
                    'is_inactive'  => $row['is_inactive'],
                    'drilldown_url' => (new moodle_url('/local/airpay_manager/member.php', ['id' => $row['id']]))->out(false),
                ];
            }

            $teamrate = ($teamenrolled > 0) ? min(100, round(($teamcompleted / $teamenrolled) * 100, 1)) : 0;
            $airpay_dashboard['manager_kpis'] = [
                ['label' => 'Team Members',    'value' => count($teammembers), 'icon' => 'users', 'color' => 'primary'],
                ['label' => 'Team Enrolments', 'value' => $teamenrolled,        'icon' => 'book', 'color' => 'accent'],
                ['label' => 'Completions',     'value' => $teamcompleted,       'icon' => 'check-circle', 'color' => 'success'],
                ['label' => 'Completion Rate', 'value' => $teamrate . '%',      'icon' => 'line-chart', 'color' => 'gold'],
            ];
            $airpay_dashboard['team_overdue']  = $teamoverdue;
            $airpay_dashboard['hasmanagerkpis']  = count($teammembers) > 0;
            $airpay_dashboard['teamcompliance']  = $teamcompliancelist;
            $airpay_dashboard['hasteamcompliance'] = count($teamcompliancelist) > 0;
        } catch (Exception $e) {
            $airpay_dashboard['hasmanagerkpis']    = false;
            $airpay_dashboard['hasteamcompliance'] = false;
        }
    }

    // --- Section: Upcoming Deadlines ---
    try {
        $deadlines = [];
        $now = time();
        $enrolledids = array_keys($enrolledcourses ?? []);
        if (!empty($enrolledids)) {
            [$insql, $params] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'cid');
            $params['uid'] = $USER->id;
            $params['now'] = $now;
            $deadlinerecords = $DB->get_records_sql(
                "SELECT c.id, c.fullname, c.shortname, c.enddate
                   FROM {course} c
                  WHERE c.id $insql
                    AND c.enddate > :now
                    AND c.id NOT IN (
                        SELECT cc.course FROM {course_completions} cc
                         WHERE cc.userid = :uid AND cc.timecompleted IS NOT NULL
                    )
               ORDER BY c.enddate ASC",
                $params, 0, 5
            );
            foreach ($deadlinerecords as $dl) {
                $deadlines[] = [
                    'coursename' => format_string($dl->fullname),
                    'duedate' => userdate($dl->enddate, '%d %B %Y'),
                    'duetimestamp' => $dl->enddate,
                    'urgent' => ($dl->enddate - $now) < (7 * 86400),
                    'viewurl' => (new moodle_url('/course/view.php', ['id' => $dl->id]))->out(false),
                ];
            }
        }
        $airpay_dashboard['deadlines'] = $deadlines;
        $airpay_dashboard['hasdeadlines'] = count($deadlines) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hasdeadlines'] = false;
    }

    // --- Section: Recent Achievements (certificates only — badges not configured) ---
    try {
        $achievements = [];
        // Certificates
        $certs = $DB->get_records_sql(
            "SELECT ci.id, ci.timecreated, ci.code, ct.name as templatename, c.fullname as coursename
               FROM {tool_certificate_issues} ci
          LEFT JOIN {tool_certificate_templates} ct ON ct.id = ci.templateid
          LEFT JOIN {course} c ON c.id = ci.courseid
              WHERE ci.userid = :uid AND ci.archived = 0
           ORDER BY ci.timecreated DESC",
            ['uid' => $USER->id], 0, 5
        );
        foreach ($certs as $cert) {
            $achievements[] = [
                'title' => format_string($cert->coursename ?? $cert->templatename ?? 'Certificate'),
                'description' => 'Certificate earned — Code: ' . s($cert->code),
                'date' => userdate($cert->timecreated, '%d %B %Y'),
                'timestamp' => $cert->timecreated,
                'type' => 'certificate',
                'icon' => 'certificate',
            ];
        }
        // Sort by timestamp descending
        usort($achievements, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
        $airpay_dashboard['achievements'] = array_slice($achievements, 0, 5);
        $airpay_dashboard['hasachievements'] = count($achievements) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hasachievements'] = false;
    }

    // --- Section: Activity Timeline ---
    try {
        $timeline = [];
        $logs = $DB->get_records_sql(
            "SELECT id, eventname, timecreated, other, courseid
               FROM {logstore_standard_log}
              WHERE userid = :uid
                AND eventname IN (
                    '\\\\core\\\\event\\\\course_completed',
                    '\\\\core\\\\event\\\\user_enrolment_created',
                    '\\\\core\\\\event\\\\badge_awarded',
                    '\\\\mod_quiz\\\\event\\\\attempt_submitted'
                )
           ORDER BY timecreated DESC",
            ['uid' => $USER->id], 0, 15
        );
        foreach ($logs as $log) {
            $coursename = '';
            if ($log->courseid > 1) {
                $coursename = $DB->get_field('course', 'fullname', ['id' => $log->courseid]);
            }
            $label = '';
            switch ($log->eventname) {
                case '\\core\\event\\course_completed':
                    $label = 'Completed ' . format_string($coursename);
                    break;
                case '\\core\\event\\user_enrolment_created':
                    $label = 'Enrolled in ' . format_string($coursename);
                    break;
                case '\\core\\event\\badge_awarded':
                    $label = 'Earned a badge';
                    break;
                case '\\mod_quiz\\event\\attempt_submitted':
                    $label = 'Submitted quiz in ' . format_string($coursename);
                    break;
                default:
                    $label = 'Activity recorded';
            }
            $timeline[] = [
                'label' => $label,
                'date' => userdate($log->timecreated, '%b %d'),
                'fulldate' => userdate($log->timecreated, '%d %B %Y'),
                'istoday' => (date('Ymd', $log->timecreated) === date('Ymd')),
            ];
        }
        $airpay_dashboard['timeline'] = $timeline;
        $airpay_dashboard['hastimeline'] = count($timeline) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hastimeline'] = false;
    }

    // --- Section: Recommended for You ---
    try {
        $recommendations = [];
        $enrolledids = array_keys($enrolledcourses ?? []);
        if (!empty($enrolledids)) {
            // Get categories of enrolled courses
            $categories = $DB->get_fieldset_sql(
                "SELECT DISTINCT category FROM {course} WHERE id IN (" .
                implode(',', array_map('intval', $enrolledids)) . ")"
            );
            if (!empty($categories)) {
                [$catsql, $catparams] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
                [$exsql, $exparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
                $params = array_merge($catparams, $exparams);
                $recs = $DB->get_records_sql(
                    "SELECT c.id, c.fullname, c.summary, c.category
                       FROM {course} c
                      WHERE c.category $catsql
                        AND c.id $exsql
                        AND c.visible = 1 AND c.id > 1
                   ORDER BY c.timecreated DESC",
                    $params, 0, 3
                );
                foreach ($recs as $rec) {
                    $catname = $DB->get_field('course_categories', 'name', ['id' => $rec->category]);
                    $recommendations[] = [
                        'id' => $rec->id,
                        'fullname' => format_string($rec->fullname),
                        'summary' => shorten_text(strip_tags(format_string($rec->summary)), 80),
                        'category' => format_string($catname),
                        'viewurl' => (new moodle_url('/course/view.php', ['id' => $rec->id]))->out(false),
                    ];
                }
            }
        }
        $airpay_dashboard['recommendations'] = $recommendations;
        $airpay_dashboard['hasrecommendations'] = count($recommendations) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hasrecommendations'] = false;
    }
    } // end else (learner branch)
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'layerone_detail_full' => $layerone_detail_full,
    'layerone_detail_one' => $layerone_detail_one,
    'layerone_detail_two' => $layerone_detail_two,
    'layertwo_detail_one' => $layertwo_detail_one,
    'layertwo_detail_two' => $layertwo_detail_two,
    'layertwo_detail_three' => $layertwo_detail_three,
    'layertwo_detail_four' => $layertwo_detail_four,
    'layerone_bottom_one' => $layertwo_three_one,
    'layerone_bottom_two' => $layertwo_three_two,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'headercontent' => $headercontent,
    'overflow' => $overflow,
    'isloggedin' => isloggedin(),
    'addblockbutton' => $addblockbutton,
    // Airpay UX dashboard data
    'airpay' => $airpay_dashboard ?? [],
];

// Inject sidebar navigation context.
$sidebarnav = new \theme_airpayux\sidebar_navigation($PAGE);
$templatecontext['sidebar'] = $sidebarnav->get_context();
$templatecontext['use_shell'] = true;

// Phase F.2 (2026-05-08) — featured-courses widget for the learner dashboard.
// Empty string when no curated courses are available; dashboard template
// can render `{{{featured_widget_html}}}` unconditionally.
//
// Bug-fix 2026-05-09 (UAT-T1.1): Moodle's setup.php does NOT auto-load
// /local/*/lib.php for theme layout files (only locallib + PSR-4
// classes). Without the explicit require_once, function_exists() always
// returned false and the widget never rendered. UAT caught it on day 2.
$templatecontext['featured_widget_html'] = '';
$airpay_courses_lib = $CFG->dirroot . '/local/airpay_courses/lib.php';
if (file_exists($airpay_courses_lib)) {
    require_once($airpay_courses_lib);
    if (function_exists('local_airpay_courses_render_featured_widget')) {
        $templatecontext['featured_widget_html'] =
            local_airpay_courses_render_featured_widget((int) $USER->id, 6);
    }
}

// Topbar context.
$templatecontext['topbar'] = [
    'wwwroot' => $CFG->wwwroot,
    'breadcrumbs' => $OUTPUT->navbar(),
    'notificationhtml' => '',
    'usermenu' => $OUTPUT->user_menu($USER, ''),
];

echo $OUTPUT->render_from_template('theme_airpayux/dashboard', $templatecontext);
