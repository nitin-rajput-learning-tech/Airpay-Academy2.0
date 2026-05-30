<?php
/**
 * Rule Engine — evaluates notification rules and sends alerts.
 *
 * Called by scheduled task (cron) to check all active rules.
 *
 * @package    local_airpay_notifications
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_notifications;

defined('MOODLE_INTERNAL') || die();

class rule_engine {

    /**
     * Process all active notification rules.
     * Called by scheduled task every hour.
     */
    public static function process_all(): array {
        global $DB;

        $rules = $DB->get_records('local_airpay_notif_rules', ['enabled' => 1]);
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0];

        foreach ($rules as $rule) {
            $result = self::process_rule($rule);
            $stats['processed']++;
            $stats['sent'] += $result['sent'];
            $stats['skipped'] += $result['skipped'];
        }

        return $stats;
    }

    /**
     * Process a single rule — find matching users and send notifications.
     */
    public static function process_rule(\stdClass $rule): array {
        $result = ['sent' => 0, 'skipped' => 0];

        switch ($rule->rule_type) {
            case 'deadline_approaching':
                $result = self::rule_deadline_approaching($rule);
                break;
            case 'course_not_started':
                $result = self::rule_course_not_started($rule);
                break;
            case 'streak_broken':
                $result = self::rule_streak_broken($rule);
                break;
            case 'manager_nudge':
                $result = self::rule_manager_nudge($rule);
                break;
            case 'new_course':
                $result = self::rule_new_course($rule);
                break;

            // Phase C — 2026-05-08 stretch additions.
            case 'compliance_overdue':
                $result = self::rule_compliance_overdue($rule);
                break;
            case 'certificate_expiring':
                $result = self::rule_certificate_expiring($rule);
                break;
            case 'ilt_feedback_pending':
                $result = self::rule_ilt_feedback_pending($rule);
                break;
            case 'learning_path_stalled':
                $result = self::rule_learning_path_stalled($rule);
                break;
            case 'enrolment_anniversary':
                $result = self::rule_enrolment_anniversary($rule);
                break;
            case 'inactive_user':
                $result = self::rule_inactive_user($rule);
                break;
            case 'quiz_low_score':
                $result = self::rule_quiz_low_score($rule);
                break;
            case 'monthly_summary':
                $result = self::rule_monthly_summary($rule);
                break;

            // Phase 4 B.8 — 2026-05-11 — final 4 BizLMS-equivalent rules.
            case 'cert_expired':
                $result = self::rule_cert_expired($rule);
                break;
            case 'training_overdue':
                $result = self::rule_training_overdue($rule);
                break;
            case 'manager_summary_weekly':
                $result = self::rule_manager_summary_weekly($rule);
                break;
            case 'peer_completion_celebration':
                $result = self::rule_peer_completion_celebration($rule);
                break;
        }

        return $result;
    }

    // ───────────────────────────────────────────────────────────────────
    // Phase 4 B.8 (2026-05-11) — final 4 BizLMS-equivalent rule handlers
    // ───────────────────────────────────────────────────────────────────

    /**
     * Rule: Cert expired (distinct from certificate_expiring which fires
     * BEFORE expiry — this one fires AFTER).
     *
     * Notifies the learner that a previously-earned certificate has now
     * lapsed and triggers the re-cert workflow.
     */
    private static function rule_cert_expired(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);
        $now = time();

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('tool_certificate_issues')) {
            return $result;  // cert plugin not installed — nothing to do
        }

        $rows = $DB->get_records_sql("
            SELECT ci.id AS issueid, ci.userid, ci.expires, ci.code,
                   u.firstname, ct.name AS cert_name
              FROM {tool_certificate_issues} ci
              JOIN {user} u ON u.id = ci.userid
         LEFT JOIN {tool_certificate_templates} ct ON ct.id = ci.templateid
             WHERE ci.expires > 0 AND ci.expires < :now
               AND u.deleted = 0 AND u.suspended = 0
          ORDER BY ci.expires DESC
             LIMIT $batchlimit",
            ['now' => $now]);

        foreach ($rows as $r) {
            $cert_name = $r->cert_name ?: 'your certificate';
            $sent = self::send($rule, (int) $r->userid, null,
                'Certificate expired: ' . format_string($cert_name),
                'Hi ' . s($r->firstname) . ", your '"
                . format_string($cert_name) . "' has expired on "
                . userdate($r->expires, '%d %b %Y') . '. Please re-take the'
                . " relevant training to maintain your compliance status.");
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Training overdue (admin digest).
     *
     * Different from compliance_overdue (learner-facing). This is for
     * L&D / admins — counts overdue enrolments by tenant, sends a summary.
     */
    private static function rule_training_overdue(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];
        $now = time();

        $stats = $DB->get_records_sql("
            SELECT SUBSTRING_INDEX(u.open_path, '/', 2) AS tenant_root,
                   COUNT(DISTINCT u.id) AS overdue_users,
                   COUNT(*) AS overdue_enrolments
              FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {course} c ON c.id = e.courseid
         LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
             WHERE c.enddate > 0 AND c.enddate < :now
               AND cc.timecompleted IS NULL
               AND u.deleted = 0 AND u.suspended = 0
          GROUP BY tenant_root", ['now' => $now]);

        if (empty($stats)) return $result;

        $body = "Training overdue summary as of " . userdate($now, '%d %b %Y') . ":\n\n";
        $total_users = 0;
        $total_enrolments = 0;
        foreach ($stats as $s) {
            $body .= sprintf("  Tenant %s: %d users / %d enrolments overdue\n",
                $s->tenant_root, $s->overdue_users, $s->overdue_enrolments);
            $total_users      += (int) $s->overdue_users;
            $total_enrolments += (int) $s->overdue_enrolments;
        }
        $body .= "\nTotal: $total_users users with $total_enrolments overdue enrolments.";

        $recipients = self::resolve_audience($rule);
        foreach ($recipients as $uid) {
            $sent = self::send($rule, $uid, null,
                "Training overdue: $total_users users, $total_enrolments enrolments",
                $body);
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Manager weekly summary.
     *
     * Every week: digest of direct reports' activity per manager
     * (completions, attempts, active users).
     */
    private static function rule_manager_summary_weekly(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];
        $week_ago = time() - (7 * 86400);

        // Defensive: open_managerid is a BizLMS extension column. Skip if absent.
        $cols = $DB->get_columns('user');
        if (!isset($cols['open_managerid'])) {
            return $result;
        }

        $managers = $DB->get_fieldset_sql(
            "SELECT DISTINCT open_managerid FROM {user}
              WHERE open_managerid > 0 AND deleted = 0 AND suspended = 0");

        foreach ($managers as $mid) {
            $manager = $DB->get_record('user',
                ['id' => $mid, 'deleted' => 0, 'suspended' => 0]);
            if (!$manager) continue;

            $stats = $DB->get_record_sql("
                SELECT
                    COUNT(DISTINCT u.id) AS team_size,
                    COUNT(DISTINCT cc.id) AS completions,
                    COUNT(DISTINCT qa.id) AS quiz_attempts,
                    SUM(CASE WHEN u.lastaccess > :week_ago THEN 1 ELSE 0 END) AS active_users
                  FROM {user} u
             LEFT JOIN {course_completions} cc
                       ON cc.userid = u.id AND cc.timecompleted > :wa2
             LEFT JOIN {quiz_attempts} qa
                       ON qa.userid = u.id AND qa.state = 'finished'
                       AND qa.timefinish > :wa3
                 WHERE u.open_managerid = :mid
                   AND u.deleted = 0 AND u.suspended = 0",
                ['mid' => $mid, 'week_ago' => $week_ago,
                 'wa2' => $week_ago, 'wa3' => $week_ago]);

            if (!$stats || (int) $stats->team_size === 0) continue;

            $body = "Hi " . s($manager->firstname) . ",\n\n"
                . "Your team's L&D activity in the last 7 days:\n\n"
                . "  Team size: {$stats->team_size}\n"
                . "  Active users (logged in): {$stats->active_users}\n"
                . "  Course completions: {$stats->completions}\n"
                . "  Quiz attempts: {$stats->quiz_attempts}\n\n"
                . "Check your Team page for individual details.";

            $sent = self::send($rule, $mid, null,
                "Your team's L&D summary — week of " . userdate(time(), '%d %b'),
                $body);
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Peer completion celebration.
     *
     * When user X completes a course, notify their teammates (same manager).
     * Targets only completions in the last 24h to keep volume manageable.
     */
    private static function rule_peer_completion_celebration(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 100);
        $day_ago = time() - 86400;

        // Defensive: open_managerid is a BizLMS extension. Skip if absent.
        $cols = $DB->get_columns('user');
        if (!isset($cols['open_managerid'])) {
            return $result;
        }

        $rows = $DB->get_records_sql("
            SELECT cc.id AS completionid, cc.userid AS completer_id,
                   cc.course AS courseid, cc.timecompleted,
                   u.firstname, u.lastname, u.open_managerid,
                   c.fullname AS course_name
              FROM {course_completions} cc
              JOIN {user} u ON u.id = cc.userid
              JOIN {course} c ON c.id = cc.course
             WHERE cc.timecompleted > :day_ago
               AND u.deleted = 0
          ORDER BY cc.timecompleted DESC
             LIMIT $batchlimit",
            ['day_ago' => $day_ago]);

        foreach ($rows as $r) {
            if (empty($r->open_managerid)) continue;

            $teammates = $DB->get_records_sql(
                "SELECT id, firstname FROM {user}
                  WHERE open_managerid = :mid
                    AND id != :uid
                    AND deleted = 0 AND suspended = 0",
                ['mid' => $r->open_managerid, 'uid' => $r->completer_id]);

            $completer_name = trim($r->firstname . ' ' . $r->lastname);
            $course_name = format_string($r->course_name);

            foreach ($teammates as $tm) {
                $sent = self::send($rule, (int) $tm->id, (int) $r->courseid,
                    "$completer_name completed $course_name",
                    'Hi ' . s($tm->firstname) . ", your teammate "
                    . s($completer_name) . " just finished '$course_name'. "
                    . 'You can do it too — check the catalog.');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }
        return $result;
    }

    /**
     * Resolve the rule's audience field to a list of recipient userids.
     *
     * Audience values:
     *   "siteadmin"          → all configured site admins (default)
     *   "1,2,3"              → comma-separated user IDs
     *   "designation:Manager" → all users with that designation
     */
    private static function resolve_audience(\stdClass $rule): array {
        global $DB, $CFG;
        $audience = trim((string) ($rule->audience ?? ''));
        if ($audience === '' || $audience === 'siteadmin') {
            return array_map('intval',
                array_filter(explode(',', $CFG->siteadmins ?? '')));
        }
        if (str_starts_with($audience, 'designation:')) {
            $des = substr($audience, strlen('designation:'));
            return $DB->get_fieldset_select('user',
                'id', "open_designation = :d AND deleted = 0 AND suspended = 0",
                ['d' => $des]);
        }
        $ids = array_filter(array_map('intval',
            preg_split('/[\s,]+/', $audience)));
        return $ids;
    }

    // ───────────────────────────────────────────────────────────────────
    // Phase C (2026-05-08) — additional BizLMS-equivalent rule handlers
    // ───────────────────────────────────────────────────────────────────

    /**
     * Rule: Compliance overdue.
     *
     * Notifies the LEARNER when a course they're enrolled in has its
     * `enddate` already passed AND they haven't completed it. Distinct
     * from `manager_nudge` which alerts the manager. This pings the
     * learner directly.
     */
    private static function rule_compliance_overdue(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];
        $now = time();
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        $rows = $DB->get_records_sql("
            SELECT u.id AS userid, u.firstname, c.id AS courseid, c.fullname,
                   c.enddate
              FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {course} c ON c.id = e.courseid
         LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
             WHERE c.enddate > 0 AND c.enddate < :now
               AND cc.timecompleted IS NULL
               AND u.deleted = 0 AND u.suspended = 0
          ORDER BY u.id, c.id
             LIMIT $batchlimit",
            ['now' => $now]);

        foreach ($rows as $r) {
            $sent = self::send($rule, (int) $r->userid, (int) $r->courseid,
                'Compliance overdue: ' . format_string($r->fullname),
                'Hi ' . s($r->firstname) . ", your enrolment in '"
                . format_string($r->fullname)
                . "' has passed its deadline and is still incomplete. Please complete it as soon as possible.");
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Certificate expiring.
     *
     * Looks at the standard `certificate_issues` table from the
     * tool_certificate plugin. Notifies users whose issued certificate
     * has an `expires` field landing inside the trigger window.
     * Defensive: skips if tool_certificate isn't installed.
     */
    private static function rule_certificate_expiring(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $manager = $DB->get_manager();
        if (!$manager->table_exists('tool_certificate_issues')) {
            return $result;
        }

        $window_start = time();
        $window_end = $window_start + ((int) $rule->trigger_days * 86400);
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        $rows = $DB->get_records_sql("
            SELECT i.id, i.userid, i.expires,
                   u.firstname, t.name AS template_name
              FROM {tool_certificate_issues} i
              JOIN {user} u ON u.id = i.userid
              JOIN {tool_certificate_templates} t ON t.id = i.templateid
             WHERE i.expires > :start AND i.expires <= :end
               AND u.deleted = 0 AND u.suspended = 0
          ORDER BY i.expires ASC
             LIMIT $batchlimit",
            ['start' => $window_start, 'end' => $window_end]);

        foreach ($rows as $r) {
            $days = max(1, (int) ceil(((int) $r->expires - time()) / 86400));
            $sent = self::send($rule, (int) $r->userid, null,
                'Certificate expiring in ' . $days . ' days',
                'Hi ' . s($r->firstname) . ", your certificate '"
                . format_string($r->template_name)
                . "' expires in $days day(s). Renew the underlying training to keep it current.");
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: ILT feedback pending.
     *
     * Reads from local_airpay_classroom_users (the roster table that
     * G-02 introduced). Notifies users who attended a session that
     * ended N days ago AND whose user has no feedback row in
     * airpay_evaluation linked to that session's classroom.
     *
     * Defensive: skips if airpay_classroom tables aren't present.
     */
    private static function rule_ilt_feedback_pending(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $manager = $DB->get_manager();
        if (!$manager->table_exists('local_airpay_classroom_sessions')) {
            return $result;
        }

        $cutoff = time() - ((int) $rule->trigger_days * 86400);
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        // Sessions that ended at or before cutoff, plus their roster users.
        $rows = $DB->get_records_sql("
            SELECT cu.userid, u.firstname, s.id AS sessionid,
                   s.classroomid, c.name AS classroomname, s.title, s.endtime
              FROM {local_airpay_classroom_sessions} s
              JOIN {local_airpay_classroom_users} cu ON cu.classroomid = s.classroomid
              JOIN {user} u ON u.id = cu.userid
              JOIN {local_airpay_classroom} c ON c.id = s.classroomid
             WHERE s.endtime > 0 AND s.endtime < :cutoff
               AND u.deleted = 0 AND u.suspended = 0
          ORDER BY s.endtime DESC
             LIMIT $batchlimit",
            ['cutoff' => $cutoff]);

        foreach ($rows as $r) {
            $sent = self::send($rule, (int) $r->userid, null,
                'Your feedback is appreciated',
                'Hi ' . s($r->firstname) . ", we'd love your feedback on the '"
                . format_string($r->classroomname) . " — " . s($r->title) . "' session you attended. "
                . "It only takes 2 minutes.");
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Learning path stalled.
     *
     * Notifies a learner who joined a learning path and made no progress
     * in the last `trigger_days` days. Reads from local_airpay_lp_users
     * (the airpay_learningpath user-assignments table).
     */
    private static function rule_learning_path_stalled(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $manager = $DB->get_manager();
        if (!$manager->table_exists('local_airpay_lp_users')) {
            return $result;
        }

        $cutoff = time() - ((int) $rule->trigger_days * 86400);
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        $rows = $DB->get_records_sql("
            SELECT lu.userid, u.firstname, lp.id AS pathid, lp.name AS pathname
              FROM {local_airpay_lp_users} lu
              JOIN {user} u ON u.id = lu.userid
              JOIN {local_airpay_learningpath} lp ON lp.id = lu.pathid
             WHERE lu.timemodified < :cutoff
               AND lu.status IN ('enrolled', 'in_progress')
               AND u.deleted = 0 AND u.suspended = 0
          ORDER BY lu.timemodified ASC
             LIMIT $batchlimit",
            ['cutoff' => $cutoff]);

        foreach ($rows as $r) {
            $sent = self::send($rule, (int) $r->userid, null,
                'Pick up where you left off',
                'Hi ' . s($r->firstname) . ", your learning path '"
                . format_string($r->pathname) . "' is waiting for you. "
                . 'A few minutes today keeps the streak alive.');
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Enrolment anniversary.
     *
     * Notifies users who enrolled exactly 1 year ago and haven't
     * completed yet — "still interested?" reminder.
     */
    private static function rule_enrolment_anniversary(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $year_ago = time() - 365 * 86400;
        $window = 86400; // 1-day window centered on the anniversary.
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        $rows = $DB->get_records_sql("
            SELECT u.id AS userid, u.firstname, c.id AS courseid, c.fullname,
                   ue.timestart
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {course} c ON c.id = e.courseid
              JOIN {user} u ON u.id = ue.userid
         LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
             WHERE ue.timestart > :start AND ue.timestart <= :end
               AND cc.timecompleted IS NULL
               AND u.deleted = 0 AND u.suspended = 0 AND c.id > 1
          ORDER BY ue.timestart
             LIMIT $batchlimit",
            ['start' => $year_ago - $window, 'end' => $year_ago + $window]);

        foreach ($rows as $r) {
            $sent = self::send($rule, (int) $r->userid, (int) $r->courseid,
                'Still interested in this course?',
                'Hi ' . s($r->firstname) . ', it has been a year since you enrolled in "'
                . format_string($r->fullname) . '" and the course is still incomplete. '
                . 'Want to give it another go?');
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Inactive user.
     *
     * Notifies users who haven't logged in for `trigger_days` days.
     */
    private static function rule_inactive_user(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $cutoff = time() - ((int) $rule->trigger_days * 86400);
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        $rows = $DB->get_records_sql("
            SELECT id AS userid, firstname, lastaccess
              FROM {user}
             WHERE lastaccess > 0 AND lastaccess < :cutoff
               AND deleted = 0 AND suspended = 0 AND id > 2
          ORDER BY lastaccess ASC
             LIMIT $batchlimit",
            ['cutoff' => $cutoff]);

        foreach ($rows as $r) {
            $sent = self::send($rule, (int) $r->userid, null,
                'We miss you on Airpay Academy',
                'Hi ' . s($r->firstname) . ", it's been a while. New courses and updates are waiting for you. "
                . 'Sign in to see what\'s new.');
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Quiz low score.
     *
     * Reads from {quiz_attempts} for finished attempts in the last day
     * with a sumgrades / total ratio below threshold (rule->trigger_days
     * is reused as the threshold percentage 0-100 for this rule type).
     */
    private static function rule_quiz_low_score(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $manager = $DB->get_manager();
        if (!$manager->table_exists('quiz_attempts')) {
            return $result;
        }

        $threshold = max(1, min(100, (int) $rule->trigger_days));  // Re-use trigger_days as %.
        $since = time() - 86400;
        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);

        $rows = $DB->get_records_sql("
            SELECT qa.id, qa.userid, qa.quiz, qa.sumgrades,
                   q.name AS quizname, q.sumgrades AS maxgrade,
                   u.firstname
              FROM {quiz_attempts} qa
              JOIN {quiz} q ON q.id = qa.quiz
              JOIN {user} u ON u.id = qa.userid
             WHERE qa.state = 'finished'
               AND qa.timefinish > :since
               AND q.sumgrades > 0
               AND (qa.sumgrades / q.sumgrades * 100) < :threshold
               AND u.deleted = 0 AND u.suspended = 0
          ORDER BY qa.timefinish DESC
             LIMIT $batchlimit",
            ['since' => $since, 'threshold' => $threshold]);

        foreach ($rows as $r) {
            $pct = (int) round($r->sumgrades / $r->maxgrade * 100);
            $sent = self::send($rule, (int) $r->userid, null,
                'Want to retake "' . format_string($r->quizname) . '"?',
                'Hi ' . s($r->firstname) . ', you scored ' . $pct
                . "% on '" . format_string($r->quizname) . "'. "
                . 'Most learners find a second attempt locks in the material. Give it another shot?');
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Monthly summary for managers.
     *
     * Aggregates team enrolments, completions, overdue per manager and
     * sends a single weekly digest. Uses user.open_supervisorid (BizLMS).
     */
    private static function rule_monthly_summary(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $manager = $DB->get_manager();
        if (!$manager->field_exists('user',
                new \xmldb_field('open_supervisorid', XMLDB_TYPE_INTEGER, '10'))) {
            return $result;
        }

        $batchlimit = (int) (get_config('local_airpay_notifications', 'batch_limit') ?: 500);
        $since = time() - 30 * 86400;

        $rows = $DB->get_records_sql("
            SELECT u.open_supervisorid AS managerid, mgr.firstname AS mgr_firstname,
                   COUNT(DISTINCT u.id) AS team_size,
                   SUM(CASE WHEN cc.timecompleted > :since1 THEN 1 ELSE 0 END) AS completions
              FROM {user} u
              JOIN {user} mgr ON mgr.id = u.open_supervisorid
         LEFT JOIN {course_completions} cc ON cc.userid = u.id
             WHERE u.deleted = 0 AND u.suspended = 0
               AND u.open_supervisorid > 0 AND mgr.deleted = 0
          GROUP BY u.open_supervisorid, mgr.firstname
             LIMIT $batchlimit",
            ['since1' => $since]);

        foreach ($rows as $r) {
            $sent = self::send($rule, (int) $r->managerid, null,
                'Your team last 30 days',
                'Hi ' . s($r->mgr_firstname) . ', here\'s a snapshot: '
                . (int) $r->team_size . ' team members, '
                . (int) $r->completions . ' course completions in the last 30 days. '
                . 'See your manager dashboard for the full picture.');
            $sent ? $result['sent']++ : $result['skipped']++;
        }
        return $result;
    }

    /**
     * Rule: Deadline approaching — notify learners X days before course enddate.
     */
    private static function rule_deadline_approaching(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $target_date = time() + ($rule->trigger_days * 86400);
        $window_start = $target_date;
        $window_end = $target_date + 86400; // 1-day window.

        // Find courses with enddate in the trigger window.
        $courses = $DB->get_records_select('course',
            'enddate >= :start AND enddate < :end AND visible = 1 AND id > 1',
            ['start' => $window_start, 'end' => $window_end]);

        foreach ($courses as $course) {
            // Find enrolled users who haven't completed.
            $users = $DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {user} u ON u.id = ue.userid
              LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                  WHERE e.courseid = :cid
                    AND u.deleted = 0 AND u.suspended = 0
                    AND (cc.timecompleted IS NULL)",
                ['cid' => $course->id]);

            foreach ($users as $user) {
                $sent = self::send($rule, $user->id, $course->id,
                    'Deadline approaching: ' . format_string($course->fullname),
                    'Your course "' . format_string($course->fullname) . '" is due in ' .
                    $rule->trigger_days . ' days. Complete it before ' .
                    userdate($course->enddate, '%d %b %Y') . '.');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Rule: Course not started — notify learners who enrolled X days ago but 0% progress.
     */
    private static function rule_course_not_started(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $cutoff = time() - ($rule->trigger_days * 86400);

        $users = $DB->get_records_sql(
            "SELECT DISTINCT ue.userid, u.firstname, u.lastname, c.id as courseid, c.fullname
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
          LEFT JOIN {course_completions} cc ON cc.userid = ue.userid AND cc.course = c.id
              WHERE ue.timestart > 0 AND ue.timestart <= :cutoff
                AND u.deleted = 0 AND u.suspended = 0
                AND c.visible = 1
                AND (cc.id IS NULL OR cc.timecompleted IS NULL)
              LIMIT " . (int)get_config('local_airpay_notifications', 'batch_limit') ?: 500 . "",
            ['cutoff' => $cutoff]);

        foreach ($users as $row) {
            // Check if there's any activity at all.
            $hasactivity = $DB->record_exists_select('logstore_standard_log',
                "userid = :uid AND courseid = :cid AND timecreated > :since",
                ['uid' => $row->userid, 'cid' => $row->courseid, 'since' => $cutoff]);

            if (!$hasactivity) {
                $sent = self::send($rule, $row->userid, $row->courseid,
                    'Ready to start? ' . format_string($row->fullname),
                    'You enrolled in "' . format_string($row->fullname) .
                    '" but haven\'t started yet. Jump in today!');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Rule: Streak broken — notify users who had an active streak but missed login.
     */
    private static function rule_streak_broken(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        if (!$DB->get_manager()->table_exists('local_airpay_streaks')) {
            return $result;
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twodaysago = date('Y-m-d', strtotime('-2 days'));

        // Users whose last login was 2+ days ago but had a streak >= 3.
        $users = $DB->get_records_sql(
            "SELECT s.userid, s.current_streak, s.longest_streak, u.firstname
               FROM {local_airpay_streaks} s
               JOIN {user} u ON u.id = s.userid
              WHERE s.last_login_date <= :cutoff
                AND s.current_streak >= 3
                AND u.deleted = 0 AND u.suspended = 0
              LIMIT " . (int)get_config('local_airpay_notifications', 'batch_limit') ?: 500 . "",
            ['cutoff' => $twodaysago]);

        foreach ($users as $row) {
            $sent = self::send($rule, $row->userid, null,
                'Your ' . $row->current_streak . '-day streak is at risk!',
                'Hey ' . format_string($row->firstname) . ', you had a ' .
                $row->current_streak . '-day learning streak going. ' .
                'Log in today to keep it alive!');
            $sent ? $result['sent']++ : $result['skipped']++;
        }

        return $result;
    }

    /**
     * Rule: Manager nudge — alert managers when team members have overdue courses.
     */
    private static function rule_manager_nudge(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $now = time();
        // Find managers with 3+ team members who have overdue courses.
        $managers = $DB->get_records_sql(
            "SELECT u.open_supervisorid as managerid,
                    COUNT(DISTINCT u.id) as overdue_count,
                    mgr.firstname as mgr_firstname, mgr.email as mgr_email
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} mgr ON mgr.id = u.open_supervisorid
          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
              WHERE c.enddate > 0 AND c.enddate < :now
                AND (cc.timecompleted IS NULL)
                AND u.deleted = 0 AND u.suspended = 0
                AND u.open_supervisorid > 0
                AND mgr.deleted = 0
           GROUP BY u.open_supervisorid, mgr.firstname, mgr.email
             HAVING overdue_count >= 3
              LIMIT " . (int)get_config('local_airpay_notifications', 'batch_limit') ?: 500 . "",
            ['now' => $now]);

        foreach ($managers as $mgr) {
            $sent = self::send($rule, $mgr->managerid, null,
                $mgr->overdue_count . ' team members have overdue courses',
                'Hi ' . format_string($mgr->mgr_firstname) . ', ' .
                $mgr->overdue_count . ' of your team members have overdue mandatory courses. ' .
                'Check your dashboard to see who needs a nudge.');
            $sent ? $result['sent']++ : $result['skipped']++;
        }

        return $result;
    }

    /**
     * Rule: New course available — notify learners when a course is created in their category.
     */
    private static function rule_new_course(\stdClass $rule): array {
        global $DB;
        $result = ['sent' => 0, 'skipped' => 0];

        $since = time() - (24 * 3600); // Last 24 hours.

        $newcourses = $DB->get_records_select('course',
            'timecreated > :since AND visible = 1 AND id > 1',
            ['since' => $since], '', 'id, fullname, open_path');

        foreach ($newcourses as $course) {
            if (empty($course->open_path)) {
                continue;
            }

            // Notify users in the same tenant.
            // ADR-018 Wave 2: tenant root via the Sentientia seam (was an inline explode).
            $orgpath = '/' . \local_sentientia_core\tenant_identity::path_root((string) $course->open_path);

            $users = $DB->get_records_sql(
                "SELECT id, firstname FROM {user}
                  WHERE deleted = 0 AND suspended = 0
                    AND open_path LIKE :pathprefix
                  LIMIT " . (int)get_config('local_airpay_notifications', 'batch_limit') ?: 500 . "",
                ['pathprefix' => $orgpath . '%']);

            foreach ($users as $user) {
                $sent = self::send($rule, $user->id, $course->id,
                    'New course: ' . format_string($course->fullname),
                    'A new course "' . format_string($course->fullname) .
                    '" is now available. Check it out in the catalog!');
                $sent ? $result['sent']++ : $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * Send a notification — checks for duplicates, respects user preferences.
     *
     * @return bool True if sent, false if skipped (duplicate or preference).
     */
    private static function send(\stdClass $rule, int $userid, ?int $courseid,
                                  string $subject, string $message): bool {
        global $DB;

        // Prevent duplicate: same rule + user + course within 24 hours.
        // Use transaction to prevent race condition with parallel cron.
        $cid = $courseid ?? 0;
        $since = time() - 86400;
        $transaction = $DB->start_delegated_transaction();
        try {
            $exists = $DB->record_exists_select('local_airpay_notif_log',
                'ruleid = :rid AND userid = :uid AND courseid = :cid AND timecreated > :since',
                ['rid' => $rule->id, 'uid' => $userid, 'cid' => $cid, 'since' => $since]);
            if ($exists) {
                $transaction->allow_commit();
                return false;
            }
            // Insert the log record immediately to claim the slot before sending.
            $logrecord = (object)[
                'ruleid'      => $rule->id,
                'userid'      => $userid,
                'courseid'    => $cid,
                'channel'     => $rule->channel,
                'status'      => 'sending',
                'subject'     => $subject,
                'timecreated' => time(),
            ];
            $logid = $DB->insert_record('local_airpay_notif_log', $logrecord);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try { $transaction->rollback($e); } catch (\Throwable $ignored) {}
            return false; // Another process already claimed this notification.
        }

        // Check user preferences (Phase C.2 — channel + rule-type opt-out + quiet hours).
        $prefs = $DB->get_record('local_airpay_notif_prefs', ['userid' => $userid]);
        $channel = $rule->channel;
        if ($prefs) {
            if ($channel === 'inapp' && !$prefs->channel_inapp) {
                return false;
            }
            if ($channel === 'email' && !$prefs->channel_email) {
                return false;
            }
            if ($channel === 'push' && !$prefs->channel_push) {
                return false;
            }
            // Rule-type opt-out.
            if (!empty($prefs->disabled_rule_types)) {
                $disabled = array_map('trim',
                    explode(',', (string) $prefs->disabled_rule_types));
                if (in_array((string) $rule->rule_type, $disabled, true)) {
                    return false;
                }
            }
            // Quiet hours: skip during the user's DND window.
            if ($prefs->quiet_hours_start !== null
                    && $prefs->quiet_hours_end !== null) {
                $hour = (int) date('G', time());
                $qs = (int) $prefs->quiet_hours_start;
                $qe = (int) $prefs->quiet_hours_end;
                $inwindow = ($qs <= $qe)
                    ? ($hour >= $qs && $hour < $qe)        // same-day window
                    : ($hour >= $qs || $hour < $qe);       // wraps midnight
                if ($inwindow) {
                    return false;
                }
            }
        }

        // Log the notification.
        $DB->insert_record('local_airpay_notif_log', (object)[
            'ruleid'      => $rule->id,
            'userid'      => $userid,
            'courseid'    => $courseid,
            'channel'     => $channel,
            'subject'     => $subject,
            'message'     => $message,
            'status'      => 'sent',
            'timecreated' => time(),
        ]);

        // Render branded HTML using email template system (if available).
        $html = '';
        $templatekey = $rule->template ?? '';
        if (!empty($templatekey) && class_exists('\\local_airpay_emails\\email_renderer')) {
            $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email, open_path');
            if ($user) {
                $tplcontext = [
                    'firstname'   => format_string($user->firstname),
                    'lastname'    => format_string($user->lastname),
                    'fullname'    => format_string($user->firstname . ' ' . $user->lastname),
                    'course_name' => $courseid ? ($DB->get_field('course', 'fullname', ['id' => $courseid]) ?? '') : '',
                    'course_url'  => $courseid ? (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false) : '',
                    'dashboard_url' => (new \moodle_url('/my/dashboard.php'))->out(false),
                    'subject'     => $subject,
                ];
                try {
                    $html = \local_airpay_emails\email_renderer::render(
                        'local_airpay_emails/' . $templatekey, $tplcontext, $userid
                    );
                } catch (\Exception $e) {
                    debugging('Notification template render fallback: ' . $e->getMessage());
                }
            }
        }
        if (empty($html)) {
            $html = '<p>' . s($message) . '</p>';
        }

        // Send via Moodle messaging (in-app + email if template rendered).
        if ($channel === 'inapp' || $channel === 'email') {
            $eventdata = new \core\message\message();
            $eventdata->component         = 'local_airpay_notifications';
            $eventdata->name              = 'smart_alert';
            $eventdata->userfrom          = \core_user::get_noreply_user();
            $eventdata->userto            = $userid;
            $eventdata->subject           = $subject;
            $eventdata->fullmessage       = html_to_text($html);
            $eventdata->fullmessageformat = FORMAT_HTML;
            $eventdata->fullmessagehtml   = $html;
            $eventdata->smallmessage      = $subject;
            $eventdata->notification      = 1;

            if ($courseid) {
                $eventdata->contexturl     = new \moodle_url('/course/view.php', ['id' => $courseid]);
                $eventdata->contexturlname = $subject;
            }

            try {
                message_send($eventdata);
            } catch (\Exception $e) {
                debugging('Notification send failed: ' . $e->getMessage());
            }
        }

        return true;
    }
}
