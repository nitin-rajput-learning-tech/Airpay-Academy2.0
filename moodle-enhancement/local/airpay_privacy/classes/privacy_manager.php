<?php
/**
 * Privacy Manager — DPDP Act 2023 compliance.
 *
 * Handles: data download, account deletion requests, consent tracking.
 * Public tenant users have self-service access to all their data.
 *
 * @package    local_airpay_privacy
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_privacy;

defined('MOODLE_INTERNAL') || die();

class privacy_manager {

    /**
     * Create a data download request.
     * Generates a JSON file with all user data within 72 hours.
     */
    public static function request_data_download(int $userid): int {
        global $DB;

        // Check for existing pending request.
        $existing = $DB->get_record_select('local_privacy_requests',
            "userid = :uid AND request_type = 'data_download' AND status IN ('pending', 'processing')",
            ['uid' => $userid]);
        if ($existing) {
            return $existing->id;
        }

        return $DB->insert_record('local_privacy_requests', (object)[
            'userid'       => $userid,
            'request_type' => 'data_download',
            'status'       => 'pending',
            'timecreated'  => time(),
        ]);
    }

    /**
     * Create an account deletion request.
     * Requires admin approval before processing.
     */
    public static function request_account_deletion(int $userid, string $reason = ''): int {
        global $DB;

        // Check for existing pending request.
        $existing = $DB->get_record_select('local_privacy_requests',
            "userid = :uid AND request_type = 'account_delete' AND status IN ('pending', 'processing')",
            ['uid' => $userid]);
        if ($existing) {
            return $existing->id;
        }

        $requestid = $DB->insert_record('local_privacy_requests', (object)[
            'userid'       => $userid,
            'request_type' => 'account_delete',
            'status'       => 'pending',
            'reason'       => $reason,
            'timecreated'  => time(),
        ]);

        // Notify admins.
        self::notify_admin_of_request($userid, 'account_delete', $requestid);

        return $requestid;
    }

    /**
     * Process a data download request — generate the export file.
     */
    public static function process_download(int $requestid): bool {
        global $DB, $CFG;

        $request = $DB->get_record('local_privacy_requests', ['id' => $requestid], '*', MUST_EXIST);
        if ($request->request_type !== 'data_download' || $request->status === 'completed') {
            return false;
        }

        $DB->set_field('local_privacy_requests', 'status', 'processing', ['id' => $requestid]);

        $userid = $request->userid;
        $data = self::collect_user_data($userid);

        // Write to file.
        $filename = 'user_data_' . $userid . '_' . date('Ymd_His') . '.json';
        $filepath = $CFG->dataroot . '/privacy_exports/' . $filename;
        @mkdir(dirname($filepath), 0770, true);
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Update request.
        $DB->update_record('local_privacy_requests', (object)[
            'id'            => $requestid,
            'status'        => 'completed',
            'download_url'  => $filepath,
            'timeprocessed' => time(),
            'timeexpires'   => time() + (72 * 3600), // 72 hours.
        ]);

        // Notify user.
        self::notify_user($userid, 'Your data export is ready for download. It will be available for 72 hours.');

        return true;
    }

    /**
     * Process an account deletion — anonymize and suspend.
     * DPDP requires data erasure, but we preserve anonymized learning records for audit.
     */
    public static function process_deletion(int $requestid, int $adminid, string $notes = ''): bool {
        global $DB;

        $request = $DB->get_record('local_privacy_requests', ['id' => $requestid], '*', MUST_EXIST);
        if ($request->request_type !== 'account_delete') {
            return false;
        }

        $userid = $request->userid;
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return false;
        }

        // Step 1: Anonymize personal data.
        $anon_suffix = '_deleted_' . $userid . '_' . time();
        $DB->update_record('user', (object)[
            'id'            => $userid,
            'firstname'     => 'Deleted',
            'lastname'      => 'User',
            'email'         => 'deleted' . $anon_suffix . '@removed.local',
            'username'      => 'deleted' . $anon_suffix,
            'phone1'        => '',
            'phone2'        => '',
            'address'       => '',
            'city'          => '',
            'description'   => '',
            'imagealt'      => '',
            'suspended'     => 1,
            'open_employeeid'    => '',
            'open_designation'   => '',
            'open_location'      => '',
            'open_team'          => '',
            'open_band'          => '',
            'open_supervisorempid' => '',
            'open_dateofbirth'   => '',
            'open_joindate'      => '',
            'timemodified'  => time(),
        ]);

        // Step 2: Delete personal data from extended tables.
        // Gamification points log.
        if ($DB->get_manager()->table_exists('local_sentientia_points_log')) {
            $DB->delete_records('local_sentientia_points_log', ['userid' => $userid]);
        }
        // User badges.
        if ($DB->get_manager()->table_exists('local_sentientia_user_badges')) {
            $DB->delete_records('local_sentientia_user_badges', ['userid' => $userid]);
        }
        // Streaks.
        if ($DB->get_manager()->table_exists('local_sentientia_streaks')) {
            $DB->delete_records('local_sentientia_streaks', ['userid' => $userid]);
        }
        // Chat log.
        if ($DB->get_manager()->table_exists('local_sentientia_chat_log')) {
            $DB->delete_records('local_sentientia_chat_log', ['userid' => $userid]);
        }
        // Notification log.
        if ($DB->get_manager()->table_exists('local_airpay_notif_log')) {
            $DB->delete_records('local_airpay_notif_log', ['userid' => $userid]);
        }
        // Notification preferences.
        if ($DB->get_manager()->table_exists('local_airpay_notif_prefs')) {
            $DB->delete_records('local_airpay_notif_prefs', ['userid' => $userid]);
        }
        // User skills.
        if ($DB->get_manager()->table_exists('local_airpay_user_skills')) {
            $DB->delete_records('local_airpay_user_skills', ['userid' => $userid]);
        }

        // Step 3: Mark Moodle user as deleted (soft delete).
        $DB->set_field('user', 'deleted', 1, ['id' => $userid]);

        // Step 4: Update request.
        $DB->update_record('local_privacy_requests', (object)[
            'id'            => $requestid,
            'status'        => 'completed',
            'admin_notes'   => $notes,
            'processed_by'  => $adminid,
            'timeprocessed' => time(),
        ]);

        // Step 5: Log consent withdrawal.
        $DB->insert_record('local_privacy_consent_log', (object)[
            'userid'       => $userid,
            'consent_type' => 'data_processing',
            'consented'    => 0, // Withdrawal.
            'timecreated'  => time(),
        ]);

        return true;
    }

    /**
     * Collect ALL user data for export (DPDP Right of Access).
     */
    public static function collect_user_data(int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return ['error' => 'User not found'];
        }

        $data = [
            'export_date'   => date('Y-m-d H:i:s'),
            'dpdp_notice'   => 'This data export is provided under the Digital Personal Data Protection Act, 2023 (India). You have the right to access, correct, and request deletion of your personal data.',

            'personal_info' => [
                'name'         => $user->firstname . ' ' . $user->lastname,
                'email'        => $user->email,
                'username'     => $user->username,
                'phone'        => $user->phone1,
                'city'         => $user->city,
                'country'      => $user->country,
                'employee_id'  => $user->open_employeeid ?? '',
                'designation'  => $user->open_designation ?? '',
                'department'   => $user->open_path ?? '',
                'location'     => $user->open_location ?? '',
                'join_date'    => $user->open_joindate ?? '',
                'account_created' => userdate($user->timecreated),
                'last_login'   => $user->lastlogin ? userdate($user->lastlogin) : 'Never',
            ],

            'enrolments' => [],
            'completions' => [],
            'certificates' => [],
            'quiz_attempts' => [],
            'activity_log' => [],
            'gamification' => [],
            'consent_history' => [],
        ];

        // Enrolments.
        $enrolments = $DB->get_records_sql(
            "SELECT c.fullname, c.shortname, ue.timestart, ue.timeend
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
              WHERE ue.userid = :uid ORDER BY ue.timestart DESC",
            ['uid' => $userid]);
        foreach ($enrolments as $e) {
            $data['enrolments'][] = [
                'course' => $e->fullname,
                'shortname' => $e->shortname,
                'enrolled_date' => $e->timestart ? userdate($e->timestart) : '',
            ];
        }

        // Completions.
        $completions = $DB->get_records_sql(
            "SELECT c.fullname, cc.timecompleted
               FROM {course_completions} cc
               JOIN {course} c ON c.id = cc.course
              WHERE cc.userid = :uid AND cc.timecompleted IS NOT NULL
           ORDER BY cc.timecompleted DESC",
            ['uid' => $userid]);
        foreach ($completions as $cc) {
            $data['completions'][] = [
                'course' => $cc->fullname,
                'completed_date' => userdate($cc->timecompleted),
            ];
        }

        // Certificates.
        $certs = $DB->get_records_sql(
            "SELECT ci.code, ci.timecreated, t.name as template_name
               FROM {tool_certificate_issues} ci
               JOIN {tool_certificate_templates} t ON t.id = ci.templateid
              WHERE ci.userid = :uid ORDER BY ci.timecreated DESC",
            ['uid' => $userid]);
        foreach ($certs as $cert) {
            $data['certificates'][] = [
                'template' => $cert->template_name,
                'code' => $cert->code,
                'issued_date' => userdate($cert->timecreated),
            ];
        }

        // Quiz attempts (last 50).
        $attempts = $DB->get_records_sql(
            "SELECT q.name, qa.sumgrades, qa.timefinish, qa.state
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE qa.userid = :uid ORDER BY qa.timefinish DESC",
            ['uid' => $userid], 0, 50);
        foreach ($attempts as $a) {
            $data['quiz_attempts'][] = [
                'quiz' => $a->name,
                'grade' => $a->sumgrades,
                'state' => $a->state,
                'date' => $a->timefinish ? userdate($a->timefinish) : '',
            ];
        }

        // Activity log (last 100).
        $logs = $DB->get_records_sql(
            "SELECT eventname, action, target, timecreated
               FROM {logstore_standard_log}
              WHERE userid = :uid ORDER BY timecreated DESC",
            ['uid' => $userid], 0, 100);
        foreach ($logs as $l) {
            $data['activity_log'][] = [
                'event' => $l->eventname,
                'action' => $l->action,
                'target' => $l->target,
                'date' => userdate($l->timecreated),
            ];
        }

        // Gamification data.
        if ($DB->get_manager()->table_exists('local_sentientia_points_log')) {
            $points = $DB->get_records('local_sentientia_points_log', ['userid' => $userid], 'timecreated DESC', '*', 0, 100);
            foreach ($points as $p) {
                $data['gamification'][] = [
                    'action' => $p->action,
                    'points' => $p->points,
                    'date' => userdate($p->timecreated),
                ];
            }
        }

        // Consent history.
        $consents = $DB->get_records('local_privacy_consent_log', ['userid' => $userid], 'timecreated DESC');
        foreach ($consents as $c) {
            $data['consent_history'][] = [
                'type' => $c->consent_type,
                'consented' => $c->consented ? 'Yes' : 'Withdrawn',
                'date' => userdate($c->timecreated),
            ];
        }

        return $data;
    }

    /**
     * Get user's privacy requests.
     */
    public static function get_user_requests(int $userid): array {
        global $DB;
        return array_values($DB->get_records('local_privacy_requests',
            ['userid' => $userid], 'timecreated DESC'));
    }

    /**
     * Get all pending requests for admin review.
     */
    public static function get_pending_requests(): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT pr.*, u.firstname, u.lastname, u.email
               FROM {local_privacy_requests} pr
               JOIN {user} u ON u.id = pr.userid
              WHERE pr.status = 'pending'
           ORDER BY pr.timecreated ASC"));
    }

    /**
     * Check if user is on Public tenant (DPDP self-service applies).
     */
    public static function is_public_tenant(int $userid): bool {
        global $DB;
        $path = $DB->get_field('user', 'open_path', ['id' => $userid]);
        return !empty($path) && str_starts_with(trim($path, '/'), '77');
    }

    /**
     * Notify admin of a new deletion request.
     */
    private static function notify_admin_of_request(int $userid, string $type, int $requestid): void {
        global $DB, $CFG;

        $user = $DB->get_record('user', ['id' => $userid]);
        $admins = get_admins();
        $subject = 'DPDP Request: ' . ucfirst(str_replace('_', ' ', $type)) .
                   ' from ' . $user->firstname . ' ' . $user->lastname;

        // Render branded template.
        $html = '';
        if (class_exists('\\local_airpay_emails\\email_renderer')) {
            try {
                $html = \local_airpay_emails\email_renderer::render(
                    'local_airpay_emails/privacy/deletion_request_admin', [
                        'firstname'        => format_string($user->firstname),
                        'lastname'         => format_string($user->lastname),
                        'fullname'         => format_string($user->firstname . ' ' . $user->lastname),
                        'email'            => $user->email,
                        'request_type'     => ucfirst(str_replace('_', ' ', $type)),
                        'request_date'     => userdate(time(), '%d %B %Y, %I:%M %p'),
                        'admin_url'        => (new \moodle_url('/local/airpay_privacy/index.php'))->out(false),
                        'response_deadline' => userdate(time() + (30 * 86400), '%d %B %Y'),
                        'subject'          => $subject,
                    ]);
            } catch (\Exception $e) {
                debugging('Privacy admin template fallback: ' . $e->getMessage());
            }
        }
        if (empty($html)) {
            $html = '<p>A user has submitted a ' . s($type) . ' request under DPDP Act 2023.</p>';
        }

        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component         = 'local_airpay_privacy';
            $message->name              = 'privacy_request';
            $message->userfrom          = \core_user::get_noreply_user();
            $message->userto            = $admin->id;
            $message->subject           = $subject;
            $message->fullmessage       = html_to_text($html);
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml   = $html;
            $message->smallmessage      = $subject;
            $message->notification      = 1;
            $message->contexturl        = new \moodle_url('/local/airpay_privacy/index.php');
            $message->contexturlname    = 'Review requests';

            try {
                message_send($message);
            } catch (\Exception $e) {
                // Non-blocking.
            }
        }
    }

    /**
     * Notify user that their request has been processed.
     */
    private static function notify_user(int $userid, string $text): void {
        global $DB;

        // Render branded template for data export ready.
        $html = '';
        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname');
        if ($user && class_exists('\\local_airpay_emails\\email_renderer')) {
            try {
                $html = \local_airpay_emails\email_renderer::render(
                    'local_airpay_emails/privacy/data_export_ready', [
                        'firstname'        => format_string($user->firstname),
                        'download_url'     => (new \moodle_url('/local/airpay_privacy/index.php'))->out(false),
                        'download_expiry'  => '72 hours',
                        'file_size'        => 'varies',
                        'subject'          => 'Your data request has been processed',
                        'data_categories'  => [
                            ['category' => 'Profile Information'],
                            ['category' => 'Course Enrollments & Progress'],
                            ['category' => 'Assessment Scores'],
                            ['category' => 'Certificates'],
                            ['category' => 'Login History'],
                        ],
                    ], $userid);
            } catch (\Exception $e) {
                debugging('Privacy user template fallback: ' . $e->getMessage());
            }
        }
        if (empty($html)) {
            $html = '<p>' . s($text) . '</p>';
        }

        $message = new \core\message\message();
        $message->component         = 'local_airpay_privacy';
        $message->name              = 'privacy_request';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $userid;
        $message->subject           = 'Your data request has been processed';
        $message->fullmessage       = html_to_text($html);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = $html;
        $message->smallmessage      = $message->subject;
        $message->notification      = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            // Non-blocking.
        }
    }
}
