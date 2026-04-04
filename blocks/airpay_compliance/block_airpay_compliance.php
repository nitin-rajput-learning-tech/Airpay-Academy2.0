<?php
/**
 * Airpay Compliance Dashboard block.
 * Shows mandatory course completion status across departments.
 * RAG (Red/Amber/Green) status per user per mandatory course.
 *
 * @package    block_airpay_compliance
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_airpay_compliance extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_airpay_compliance');
    }

    public function applicable_formats() {
        return ['my' => true, 'site-index' => true];
    }

    public function get_content() {
        global $DB, $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $systemcontext = \context_system::instance();
        $isadmin = is_siteadmin() || has_capability('local/courses:manage', $systemcontext);
        $ismanager = has_capability('moodle/site:viewreports', $systemcontext);

        if (!$isadmin && !$ismanager) {
            // Regular users see their own compliance status.
            $this->content->text = $this->get_learner_compliance($USER->id);
            return $this->content;
        }

        // Admin/Manager: show org-wide compliance matrix.
        $this->content->text = $this->get_admin_compliance();
        return $this->content;
    }

    /**
     * Get learner's own compliance status.
     */
    private function get_learner_compliance(int $userid): string {
        global $DB;

        $now = time();
        $courses = enrol_get_all_users_courses($userid, true);
        $mandatory = [];

        foreach ($courses as $course) {
            // Courses with enddate are considered mandatory (compliance deadline).
            if ($course->enddate > 0) {
                $cc = $DB->get_record('course_completions', [
                    'userid' => $userid,
                    'course' => $course->id,
                ]);
                $completed = ($cc && $cc->timecompleted);
                $overdue = (!$completed && $course->enddate < $now);
                $daysremaining = $completed ? 0 : max(0, round(($course->enddate - $now) / 86400));

                $status = 'completed';
                $statusclass = 'success';
                if (!$completed && $overdue) {
                    $status = 'overdue';
                    $statusclass = 'danger';
                } else if (!$completed && $daysremaining <= 7) {
                    $status = 'due_soon';
                    $statusclass = 'warning';
                } else if (!$completed) {
                    $status = 'on_track';
                    $statusclass = 'info';
                }

                $mandatory[] = [
                    'coursename' => format_string($course->fullname),
                    'deadline' => userdate($course->enddate, '%d %b %Y'),
                    'status' => $status,
                    'statusclass' => $statusclass,
                    'statuslabel' => ucfirst(str_replace('_', ' ', $status)),
                    'daysremaining' => $daysremaining,
                    'completed' => $completed,
                    'overdue' => $overdue,
                ];
            }
        }

        if (empty($mandatory)) {
            return '<p class="text-muted">No mandatory courses assigned.</p>';
        }

        // Sort: overdue first, then by days remaining.
        usort($mandatory, function($a, $b) {
            if ($a['overdue'] && !$b['overdue']) return -1;
            if (!$a['overdue'] && $b['overdue']) return 1;
            return $a['daysremaining'] - $b['daysremaining'];
        });

        $html = '<div class="airpay-compliance">';
        foreach ($mandatory as $item) {
            $html .= '<div class="airpay-compliance__item airpay-compliance__item--' . $item['statusclass'] . '">';
            $html .= '<div class="airpay-compliance__course">' . s($item['coursename']) . '</div>';
            $html .= '<div class="airpay-compliance__meta">';
            $html .= '<span class="badge badge-' . $item['statusclass'] . '">' . s($item['statuslabel']) . '</span>';
            if (!$item['completed']) {
                $html .= ' <small>Due: ' . s($item['deadline']) . '</small>';
                if ($item['daysremaining'] > 0) {
                    $html .= ' <small class="text-muted">(' . $item['daysremaining'] . ' days)</small>';
                }
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Get organization-wide compliance matrix for admins/managers.
     */
    private function get_admin_compliance(): string {
        global $DB;

        $now = time();

        // Get all courses with deadlines (mandatory courses).
        $mandatorycourses = $DB->get_records_select('course',
            'enddate > 0 AND visible = 1 AND id > 1',
            [], 'fullname ASC', 'id,shortname,fullname,enddate');

        if (empty($mandatorycourses)) {
            return '<p class="text-muted">No mandatory courses with deadlines configured.</p>';
        }

        // Get all non-admin, non-guest users.
        $users = $DB->get_records_select('user',
            'deleted = 0 AND suspended = 0 AND id > 1 AND username != :admin',
            ['admin' => 'superadmin'],
            'lastname ASC', 'id,firstname,lastname,username');

        // Build compliance matrix.
        $totalusers = count($users);
        $totalcourses = count($mandatorycourses);
        $totalcompleted = 0;
        $totaloverdue = 0;
        $totalenrolled = 0;

        $coursestats = [];
        foreach ($mandatorycourses as $course) {
            $enrolled = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT ue.userid)
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = :cid",
                ['cid' => $course->id]);

            $completed = $DB->count_records_sql(
                "SELECT COUNT(cc.id)
                   FROM {course_completions} cc
                  WHERE cc.course = :cid AND cc.timecompleted IS NOT NULL",
                ['cid' => $course->id]);

            $overdue = ($course->enddate < $now) ? ($enrolled - $completed) : 0;
            $rate = ($enrolled > 0) ? round(($completed / $enrolled) * 100) : 0;

            $rag = 'green';
            if ($overdue > 0) {
                $rag = 'red';
            } else if ($rate < 80) {
                $rag = 'amber';
            }

            $coursestats[] = [
                'name' => format_string($course->shortname),
                'fullname' => format_string($course->fullname),
                'deadline' => userdate($course->enddate, '%d %b %Y'),
                'enrolled' => $enrolled,
                'completed' => $completed,
                'overdue' => max(0, $overdue),
                'rate' => $rate,
                'rag' => $rag,
                'isoverdue' => ($course->enddate < $now),
            ];

            $totalenrolled += $enrolled;
            $totalcompleted += $completed;
            $totaloverdue += max(0, $overdue);
        }

        $overallrate = ($totalenrolled > 0) ? round(($totalcompleted / $totalenrolled) * 100) : 0;

        // Build HTML.
        $html = '<div class="airpay-compliance-admin">';

        // Summary stats.
        $html .= '<div class="airpay-compliance-admin__summary">';
        $html .= '<div class="airpay-compliance-admin__stat">';
        $html .= '<strong>' . $totalcourses . '</strong><small>Mandatory Courses</small></div>';
        $html .= '<div class="airpay-compliance-admin__stat">';
        $html .= '<strong>' . $overallrate . '%</strong><small>Completion Rate</small></div>';
        $html .= '<div class="airpay-compliance-admin__stat airpay-compliance-admin__stat--' . ($totaloverdue > 0 ? 'danger' : 'success') . '">';
        $html .= '<strong>' . $totaloverdue . '</strong><small>Overdue</small></div>';
        $html .= '</div>';

        // Course table.
        $html .= '<table class="generaltable airpay-compliance-admin__table">';
        $html .= '<thead><tr>';
        $html .= '<th>Course</th><th>Deadline</th><th>Enrolled</th><th>Completed</th><th>Overdue</th><th>Rate</th><th>Status</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($coursestats as $cs) {
            $ragclass = $cs['rag'] === 'red' ? 'danger' : ($cs['rag'] === 'amber' ? 'warning' : 'success');
            $html .= '<tr>';
            $html .= '<td title="' . s($cs['fullname']) . '">' . s($cs['name']) . '</td>';
            $html .= '<td>' . s($cs['deadline']) . '</td>';
            $html .= '<td>' . $cs['enrolled'] . '</td>';
            $html .= '<td>' . $cs['completed'] . '</td>';
            $html .= '<td class="text-' . $ragclass . ' font-weight-bold">' . $cs['overdue'] . '</td>';
            $html .= '<td>' . $cs['rate'] . '%</td>';
            $html .= '<td><span class="badge badge-' . $ragclass . '">' . strtoupper($cs['rag']) . '</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return false;
    }
}
