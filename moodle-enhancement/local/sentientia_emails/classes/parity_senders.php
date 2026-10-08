<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * The e-mails BizLMS sends today that Sentientia had no sender for (decision COMMS-N7, 2026-10-07; mapping doc section 21,
 * gap G10; decisions file key gaps.notification_sender_parity = build_flagged_off).
 *
 * On the April 2026 production copy BizLMS's e-mail log holds four kinds of e-mail in use: course_complete (5,783, of which
 * 1,921 are copies to a manager), course_enrol (5,316), learningplan_enrol (2,264) and users_welcome_email (839). Sentientia
 * already sends the learner's course-completion e-mail (observer::course_completed) and the welcome e-mail
 * (local_sentientia_users). Once BizLMS stops sending at cutover, three would go silent, which breaks what Airpay users see
 * today. This class sends them:
 *
 *   course enrolment               a learner is enrolled in a course (core \core\event\user_enrolment_created)
 *   learning-path enrolment        a learner is enrolled in a learning path (see send_pending_path_enrolments())
 *   manager copy of a completion   the learner's supervisor is told the learner finished a course
 *
 * Every one is behind its OWN feature flag, default OFF, checked for the RECIPIENT's customer and tenant (a flag can be ON
 * for one tenant only). The import never flips a flag, and neither does this class: turning a sender ON for Airpay at
 * cutover is Nitin's call, after he has seen them work on UAT (decisions file framework.reader_flags_airpay_at_cutover, OWNER
 * DECISIONS Q11). Every sender goes through notification_sender, so it honours $CFG->noemailever (the row is logged as
 * suppressed on every non-production copy) and the recipient's channel preferences, and writes a delivery-log row with its
 * template_key. It never sends a password: none of these e-mails carries one.
 *
 * Which rule decides. A rule of the sender's rule_type in local_sentientia_email_rules (the recipient's tenant first, then
 * the global one, enabled) sets the channel and template, and the rule manager can disable it. When NO rule row of that
 * type exists at all, a built-in default is used, so a fresh install (which seeds no rules: db/ has no install.php) sends
 * the same e-mail as an upgraded one. A rule row that exists but is disabled switches the sender off for that scope.
 *
 * Nothing here ever throws into the caller: the three entry points are called from event observers and a cron task that
 * must not fail because an e-mail could not be built.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class parity_senders {

    /** Flag: a learner enrolled in a course gets an e-mail (BizLMS course_enrol). */
    public const FLAG_COURSE_ENROLMENT = 'sentientia.emails.send_course_enrolment.enabled';

    /** Flag: a learner enrolled in a learning path gets an e-mail (BizLMS learningplan_enrol). */
    public const FLAG_PATH_ENROLMENT = 'sentientia.emails.send_learning_path_enrolment.enabled';

    /** Flag: the supervisor of a learner who completes a course gets a copy (BizLMS course_complete to a manager). */
    public const FLAG_MANAGER_COPY = 'sentientia.emails.send_manager_completion_copy.enabled';

    /** rule_type of the course-enrolment e-mail. */
    public const RULE_COURSE_ENROLLED = 'course_enrolled';

    /** rule_type of the learning-path enrolment e-mail. */
    public const RULE_PATH_ENROLLED = 'learning_path_enrolled';

    /** rule_type of the manager's copy of a course completion. */
    public const RULE_MANAGER_COPY = 'manager_course_completed';

    /** Plugin setting that holds the id of the last learning-path enrolment the poller has looked at. */
    public const CONFIG_PATH_WATERMARK = 'path_enrolment_watermark';

    /** A path enrolment older than this many seconds is never e-mailed by the poller (two days). */
    public const PATH_ROW_MAX_AGE = 172800;

    /** The same e-mail to the same learner about the same course within this many seconds is sent once. */
    public const DUPLICATE_WINDOW = 300;

    /** The built-in default of each rule type, used while no rule row of that type exists. */
    private const DEFAULTS = [
        self::RULE_COURSE_ENROLLED => [
            'name' => 'Course enrolment: you have been enrolled',
            'event' => '\\core\\event\\user_enrolment_created',
            'audience' => 'learner',
            'template' => 'enrollment/course_enrolled',
            'priority' => 70,
        ],
        self::RULE_PATH_ENROLLED => [
            'name' => 'Learning path enrolment: you have been enrolled',
            'event' => 'cron',
            'audience' => 'learner',
            'template' => 'enrollment/learning_path_enrolled',
            'priority' => 70,
        ],
        self::RULE_MANAGER_COPY => [
            'name' => 'Course completed: copy to the learner\'s manager',
            'event' => '\\core\\event\\course_completed',
            'audience' => 'manager',
            'template' => 'enrollment/manager_course_completed',
            'priority' => 60,
        ],
    ];

    /**
     * Is the flag ON for at least one tenant (or for none in particular)? A cheap answer from the flag cache, so the
     * observers can stop before they read a single user while every sender is OFF, which is how they ship.
     *
     * @param string $flag One of the FLAG_ constants.
     * @return bool
     */
    public static function enabled_anywhere(string $flag): bool {
        try {
            $roots = [0];
            if (class_exists('\\local_sentientia_core\\tenant_registry')) {
                foreach (\local_sentientia_core\tenant_registry::valid_roots() as $root) {
                    $roots[] = (int) $root;
                }
            } else {
                $roots = array_merge($roots, \local_sentientia_platform\tenant::VALID_TENANTS);
            }
            foreach (array_unique($roots) as $root) {
                if (\local_sentientia_platform\feature_flags::is_enabled_for($flag, self::customer_for_root($root), $root)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * Is the flag ON for this recipient's customer and tenant?
     *
     * @param string $flag One of the FLAG_ constants.
     * @param \stdClass $user The recipient (open_path decides the tenant).
     * @return bool
     */
    public static function flag_enabled(string $flag, \stdClass $user): bool {
        try {
            $root = \local_sentientia_platform\tenant::root_for_user($user);
            return \local_sentientia_platform\feature_flags::is_enabled_for($flag, self::customer_for_root($root), $root);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The customer a tenant root belongs to: the registry's answer when the tenant registry is live, else Airpay.
     *
     * @param int $root
     * @return int
     */
    private static function customer_for_root(int $root): int {
        if ($root > 0 && class_exists('\\local_sentientia_core\\tenant_registry')
                && !\local_sentientia_core\tenant_registry::use_legacy_registry()) {
            $customer = (int) \local_sentientia_core\tenant_registry::customer_of($root);
            if ($customer > 0) {
                return $customer;
            }
        }
        return \local_sentientia_platform\customer::AIRPAY;
    }

    /**
     * The rule that governs a sender for this recipient, or null when it is switched off.
     *
     * The recipient's tenant rule wins over the global one, as in observer::find_rule_for_user(). With no enabled rule: a
     * rule row of the type that exists but is disabled means an administrator switched the e-mail off (null); no rule row
     * at all means the built-in default.
     *
     * @param string $ruletype One of the RULE_ constants.
     * @param \stdClass $user The recipient.
     * @return \stdClass|null
     */
    public static function rule_for(string $ruletype, \stdClass $user): ?\stdClass {
        global $DB;

        $root = \local_sentientia_platform\tenant::root_for_user($user);
        $rows = $DB->get_records_select('local_sentientia_email_rules',
            'rule_type = :t AND (tenant_id = :tid OR tenant_id = 0)', ['t' => $ruletype, 'tid' => $root], 'tenant_id DESC, id ASC');
        foreach ($rows as $row) {
            if ((int) $row->enabled === 1) {
                if (empty($row->template_key)) {
                    $row->template_key = self::DEFAULTS[$ruletype]['template'];
                }
                return $row;
            }
        }
        if ($rows) {
            return null;
        }
        $default = self::DEFAULTS[$ruletype];
        return (object) [
            'id' => null,
            'rule_name' => $default['name'],
            'rule_type' => $ruletype,
            'trigger_event' => $default['event'],
            'trigger_days' => null,
            'channel' => 'email',
            'audience' => $default['audience'],
            'template_key' => $default['template'],
            'tenant_id' => 0,
            'enabled' => 1,
            'priority' => $default['priority'],
        ];
    }

    /**
     * A learner was enrolled in a course: send the course-enrolment e-mail.
     *
     * Skipped, without a trace beyond a debugging line, for a site course, a hidden course (the link would not open), a
     * suspended enrolment, a guest, a deleted or suspended user, a flag that is OFF for the recipient, a rule an
     * administrator disabled, and an e-mail already sent to this learner about this course a moment ago (bulk operations
     * can fire the event twice).
     *
     * @param int $userid The enrolled user.
     * @param int $courseid
     * @param int $actorid Who enrolled them (0 or the user's own id when nobody else did).
     * @param int $enrolmentid user_enrolments.id, 0 when unknown.
     * @return bool True when an e-mail was handed to the sender (delivered, or logged as suppressed).
     */
    public static function course_enrolled(int $userid, int $courseid, int $actorid = 0, int $enrolmentid = 0): bool {
        global $DB;
        try {
            if ($courseid <= 0 || $courseid === SITEID || !self::enabled_anywhere(self::FLAG_COURSE_ENROLMENT)) {
                return false;
            }
            $user = self::recipient($userid);
            if ($user === null || !self::flag_enabled(self::FLAG_COURSE_ENROLMENT, $user)) {
                return false;
            }
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, visible');
            if (!$course || !(int) $course->visible) {
                return false;
            }
            if ($enrolmentid > 0 && (int) $DB->get_field('user_enrolments', 'status', ['id' => $enrolmentid]) !== 0) {
                return false;
            }
            $rule = self::rule_for(self::RULE_COURSE_ENROLLED, $user);
            if ($rule === null || self::sent_recently((int) $user->id, $courseid, (string) $rule->template_key)) {
                return false;
            }

            $name = format_string($course->fullname, true, ['escape' => false]);
            $context = [
                'firstname' => format_string($user->firstname, true, ['escape' => false]),
                'course_name' => $name,
                'course_url' => (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
                'enrolled_by' => self::enrolled_by($user, $actorid),
                'start_date' => userdate(time(), '%d %B %Y'),
                'subject' => self::string('parity_subject_course_enrolled', $user, $name),
            ];
            notification_sender::send($rule, $user, $context, $courseid);
            return true;
        } catch (\Throwable $e) {
            debugging('local_sentientia_emails course-enrolment e-mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * A learner was enrolled in a learning path: send the learning-path enrolment e-mail.
     *
     * The learning-path plugin fires no event when it enrols a learner, so send_pending_path_enrolments() calls this for
     * every new row of its enrolment table. It is public so that a direct call from the path manager could replace the
     * poller one day without changing the e-mail.
     *
     * @param int $userid The enrolled user.
     * @param int $pathid local_sentientia_learningpath.id
     * @param int $enrolledby Who enrolled them (0 or the user's own id when nobody else did).
     * @return bool True when an e-mail was handed to the sender.
     */
    public static function learning_path_enrolled(int $userid, int $pathid, int $enrolledby = 0): bool {
        global $DB;
        try {
            if ($pathid <= 0 || !self::enabled_anywhere(self::FLAG_PATH_ENROLMENT)) {
                return false;
            }
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('local_sentientia_learningpath') || !$dbman->table_exists('local_sentientia_learningpath_courses')) {
                return false;
            }
            $user = self::recipient($userid);
            if ($user === null || !self::flag_enabled(self::FLAG_PATH_ENROLMENT, $user)) {
                return false;
            }
            $path = $DB->get_record('local_sentientia_learningpath', ['id' => $pathid], 'id, name, status, visible, enddate');
            if (!$path || (int) $path->status !== 1 || !(int) $path->visible) {
                return false;
            }
            $rule = self::rule_for(self::RULE_PATH_ENROLLED, $user);
            if ($rule === null) {
                return false;
            }

            $courses = [];
            $records = $DB->get_records_sql(
                'SELECT c.id, c.fullname
                   FROM {local_sentientia_learningpath_courses} lpc
                   JOIN {course} c ON c.id = lpc.courseid
                  WHERE lpc.pathid = :pid
               ORDER BY lpc.sortorder ASC, lpc.id ASC', ['pid' => $pathid], 0, 50);
            foreach ($records as $record) {
                $courses[] = ['name' => format_string($record->fullname, true, ['escape' => false])];
            }
            $name = format_string($path->name, true, ['escape' => false]);
            $context = [
                'firstname' => format_string($user->firstname, true, ['escape' => false]),
                'path_name' => $name,
                'path_url' => (new \moodle_url('/local/sentientia_learningpath/view.php', ['id' => $pathid]))->out(false),
                'course_count' => count($courses),
                'courses' => $courses,
                'subject' => self::string('parity_subject_path_enrolled', $user, $name),
            ];
            // A path closes on its end date; without one, or once it has passed, the template shows no deadline.
            $end = (int) ($path->enddate ?? 0);
            if ($end > time()) {
                $context['deadline_date'] = userdate($end, '%d %B %Y');
                $context['deadline_days'] = (int) ceil(($end - time()) / DAYSECS);
            }
            notification_sender::send($rule, $user, $context, 0);
            return true;
        } catch (\Throwable $e) {
            debugging('local_sentientia_emails learning-path e-mail failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Send the learning-path enrolment e-mail for every enrolment made since the last run (the cron task calls this).
     *
     * The first run only records where the table stands, so the back catalogue is never e-mailed. After that each run
     * takes the rows above the saved id, oldest first, and moves the saved id past every row it has looked at, whatever
     * became of it: a row that was skipped (flag OFF for that tenant, rule disabled, user gone) is not looked at again,
     * which is also what stops an old enrolment from being e-mailed the day a flag is switched ON. A row that is older
     * than PATH_ROW_MAX_AGE is not e-mailed, and a row the BizLMS import wrote (it is in the legacy map) never is.
     *
     * While the flag is OFF for every tenant (how it ships) a run reads no row at all: it moves the saved id to the
     * highest id in one write. Otherwise the saved id is written once per e-mail handed to the sender (so a run that stops
     * half way cannot send the same e-mail twice) and once at the end of the run, never once per row looked at.
     *
     * @param int $limit Rows per run; the rest wait for the next run.
     * @return int E-mails handed to the sender.
     */
    public static function send_pending_path_enrolments(int $limit = 200): int {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_sentientia_learningpath_users')) {
            return 0;
        }
        $saved = get_config('local_sentientia_emails', self::CONFIG_PATH_WATERMARK);
        if ($saved === false || $saved === null || $saved === '') {
            $max = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_sentientia_learningpath_users}');
            set_config(self::CONFIG_PATH_WATERMARK, (string) $max, 'local_sentientia_emails');
            return 0;
        }

        if (!self::enabled_anywhere(self::FLAG_PATH_ENROLMENT)) {
            // Nothing can be sent, and what is enrolled now must never be e-mailed after a later flip: move past it.
            $max = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_sentientia_learningpath_users}');
            if ($max > (int) $saved) {
                set_config(self::CONFIG_PATH_WATERMARK, (string) $max, 'local_sentientia_emails');
            }
            return 0;
        }

        $params = ['after' => (int) $saved];
        $imported = '';
        if ($dbman->table_exists('local_sentientia_legacymap')) {
            $imported = ' AND NOT EXISTS (SELECT 1 FROM {local_sentientia_legacymap} m
                                           WHERE m.targettable = :maptable AND m.targetid = lpu.id)';
            $params['maptable'] = 'local_sentientia_learningpath_users';
        }
        $rows = $DB->get_records_sql(
            'SELECT lpu.id, lpu.pathid, lpu.userid, lpu.enrolledby, lpu.timecreated
               FROM {local_sentientia_learningpath_users} lpu
              WHERE lpu.id > :after' . $imported . '
           ORDER BY lpu.id ASC', $params, 0, max(1, $limit));

        $sent = 0;
        $earliest = time() - self::PATH_ROW_MAX_AGE;
        $written = (int) $saved;
        $last = $written;
        foreach ($rows as $row) {
            $last = (int) $row->id;
            if ((int) $row->timecreated >= $earliest
                    && self::learning_path_enrolled((int) $row->userid, (int) $row->pathid, (int) $row->enrolledby)) {
                $sent++;
                // An e-mail is a side effect that cannot be taken back: record that this row is done before the next.
                set_config(self::CONFIG_PATH_WATERMARK, (string) $last, 'local_sentientia_emails');
                $written = $last;
            }
        }
        if ($last > $written) {
            set_config(self::CONFIG_PATH_WATERMARK, (string) $last, 'local_sentientia_emails');
        }
        return $sent;
    }

    /**
     * A learner completed a course: send a copy to the learner's supervisor.
     *
     * Only to a LIVE supervisor in the SAME tenant as the learner (ADR-031: a person's name and progress are not sent
     * across a tenant boundary, and a supervisor whose tenant does not resolve gets nothing).
     *
     * The e-mail names the learner (it is the point of the copy); the delivery-log row written for the manager carries
     * a neutral subject ("A team member has completed ...") and never the learner's name, so the log holds no personal
     * data of the learner that their own erasure could not reach.
     *
     * @param \stdClass $learner The user record of the learner (it carries open_supervisorid where the site has it).
     * @param \stdClass $course The course record.
     * @return bool True when an e-mail was handed to the sender.
     */
    public static function manager_completion_copy(\stdClass $learner, \stdClass $course): bool {
        global $DB;
        try {
            if (!self::enabled_anywhere(self::FLAG_MANAGER_COPY)) {
                return false;
            }
            $managerid = self::supervisor_of($learner);
            $manager = $managerid > 0 ? self::recipient($managerid) : null;
            if ($manager === null) {
                return false;
            }
            $learnerroot = \local_sentientia_platform\tenant::root_for_user($learner);
            if ($learnerroot <= 0 || $learnerroot !== \local_sentientia_platform\tenant::root_for_user($manager)) {
                return false;
            }
            if (!self::flag_enabled(self::FLAG_MANAGER_COPY, $manager)) {
                return false;
            }
            $rule = self::rule_for(self::RULE_MANAGER_COPY, $manager);
            if ($rule === null) {
                return false;
            }

            $completed = (int) $DB->get_field_select('course_completions', 'timecompleted', 'userid = :u AND course = :c',
                ['u' => (int) $learner->id, 'c' => (int) $course->id]);
            $member = trim(fullname($learner));
            $name = format_string($course->fullname, true, ['escape' => false]);
            $context = [
                'firstname' => format_string($manager->firstname, true, ['escape' => false]),
                'member_name' => $member,
                'course_name' => $name,
                'course_url' => (new \moodle_url('/course/view.php', ['id' => (int) $course->id]))->out(false),
                'completion_date' => userdate($completed > 0 ? $completed : time(), '%d %B %Y'),
                'team_url' => (new \moodle_url('/my/'))->out(false),
                'subject' => self::string('parity_subject_manager_completion', $manager, (object) [
                    'member' => $member,
                    'course' => $name,
                ]),
            ];
            // The e-mail names the learner; the delivery-log row does not. The row is the MANAGER's (userid), and the
            // privacy provider deletes or anonymises a row by the person it belongs to, so a name written into it could
            // never be reached by the learner's own erasure (the same reason the import withholds the body and the
            // member's name of an imported manager copy, COMMS-N2).
            notification_sender::send($rule, $manager, $context, (int) $course->id, [
                'log_subject' => self::string('parity_log_subject_manager_completion', $manager, $name),
            ]);
            return true;
        } catch (\Throwable $e) {
            debugging('local_sentientia_emails manager copy failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * A user's live direct supervisor (BizLMS convention: open_supervisorid, then open_managerid where a site has it).
     *
     * The same rule as local_sentientia_request's approver_routing::supervisor_of(), kept here so this plugin needs no
     * dependency on the request plugin.
     *
     * @param \stdClass $user
     * @return int User id, 0 when there is none that is live.
     */
    public static function supervisor_of(\stdClass $user): int {
        global $DB;
        foreach (['open_supervisorid', 'open_managerid'] as $field) {
            $id = (int) ($user->{$field} ?? 0);
            if ($id <= 0 || $id === (int) $user->id) {
                continue;
            }
            if ($DB->record_exists('user', ['id' => $id, 'deleted' => 0, 'suspended' => 0])) {
                return $id;
            }
        }
        return 0;
    }

    /**
     * A user who can receive e-mail: a real, live account.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    private static function recipient(int $userid): ?\stdClass {
        global $DB;
        if ($userid <= 1) {
            return null;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        return ($user && !isguestuser($user)) ? $user : null;
    }

    /**
     * Was this e-mail logged for this learner and course a moment ago?
     *
     * @param int $userid
     * @param int $courseid
     * @param string $templatekey
     * @return bool
     */
    private static function sent_recently(int $userid, int $courseid, string $templatekey): bool {
        global $DB;
        return $DB->record_exists_select(delivery_log::TABLE,
            'userid = :u AND courseid = :c AND template_key = :t AND legacy_source IS NULL AND timecreated > :since',
            ['u' => $userid, 'c' => $courseid, 't' => $templatekey, 'since' => time() - self::DUPLICATE_WINDOW]);
    }

    /**
     * "Enrolled by" for the course e-mail.
     *
     * @param \stdClass $user The enrolled user.
     * @param int $actorid
     * @return string
     */
    private static function enrolled_by(\stdClass $user, int $actorid): string {
        global $DB;
        if ($actorid === (int) $user->id) {
            return self::string('parity_enrolled_by_self', $user);
        }
        $actor = $actorid > 1 ? $DB->get_record('user', ['id' => $actorid, 'deleted' => 0]) : false;
        return $actor ? trim(fullname($actor)) : self::string('parity_enrolled_by_system', $user);
    }

    /**
     * A plugin string in the recipient's own language.
     *
     * @param string $identifier
     * @param \stdClass $user
     * @param mixed $a
     * @return string
     */
    private static function string(string $identifier, \stdClass $user, mixed $a = null): string {
        $lang = !empty($user->lang) ? (string) $user->lang : null;
        return get_string_manager()->get_string($identifier, 'local_sentientia_emails', $a, $lang);
    }
}
