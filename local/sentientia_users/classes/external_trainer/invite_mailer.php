<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external_trainer;

defined('MOODLE_INTERNAL') || die();

/**
 * The external-trainer invite e-mail (ADR-036 D9, session 5).
 *
 * It carries the username and a one-time set-password link made with core's own reset token
 * (core_login_generate_password_reset(), the same record /login/forgot_password.php creates), never a
 * password. Core's set-password page checks the token, applies the site's password policy, clears the
 * forced password change and logs the trainer in. The token lasts $CFG->pwresettime (core default 30
 * minutes); the e-mail says how long, and what to do once it has run out (the ordinary "Forgotten your
 * password?" page sends a fresh link to the same address).
 *
 * Sent with email_to_user(), not message_send(): a notification row would keep the token in
 * {notifications}. email_to_user() honours $CFG->noemailever (set on every local copy), and PHPUnit
 * catches it in an e-mail sink, so no test or local check sends a real message.
 *
 * Written in the trainer's language (their account language, the site default at invite time).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invite_mailer {

    /**
     * Send the invite. Never throws: a failed send leaves the account in place (the admin page says
     * how the trainer can still get in).
     *
     * @param int $userid the external trainer
     * @return bool whether email_to_user() accepted the message
     */
    public static function send(int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/login/lib.php');

        try {
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
            $reset = core_login_generate_password_reset($user);
            $pwresettime = isset($CFG->pwresettime) ? (int) $CFG->pwresettime : 1800;

            $a = (object) [
                'firstname' => $user->firstname,
                'site'      => format_string(get_site()->fullname, true,
                    ['context' => \context_system::instance(), 'escape' => false]),
                'username'  => $user->username,
                'link'      => (new \moodle_url('/login/forgot_password.php', ['token' => $reset->token]))->out(false),
                'minutes'   => (int) floor($pwresettime / MINSECS),
                'forgoturl' => (new \moodle_url('/login/forgot_password.php'))->out(false),
                'loginurl'  => (new \moodle_url('/login/index.php'))->out(false),
                'days'      => (int) (manager::GRACE / DAYSECS),
            ];
            $sm = get_string_manager();
            $lang = (string) ($user->lang ?? '');
            $subject = $sm->get_string('exttrainer_invite_subject', 'local_sentientia_users', $a, $lang);
            $body = $sm->get_string('exttrainer_invite_body', 'local_sentientia_users', $a, $lang);

            return (bool) email_to_user($user, \core_user::get_noreply_user(), $subject, $body, nl2br(s($body)));
        } catch (\Throwable $e) {
            debugging('local_sentientia_users invite_mailer failed for user ' . $userid . ': '
                . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}
