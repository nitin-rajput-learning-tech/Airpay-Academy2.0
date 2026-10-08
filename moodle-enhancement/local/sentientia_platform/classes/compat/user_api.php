<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\compat;

defined('MOODLE_INTERNAL') || die();

/**
 * One place that creates and updates users, whichever Moodle is underneath.
 *
 * Moodle 5.3 (MDL-82650) moved the user functions of user/lib.php to \core\user
 * and turned the old globals into wrappers in lib/deprecatedlib.php that emit
 * DEBUG_DEVELOPER on every call. A debugging message is harmless in production,
 * but PHPUnit fails any test that reaches one ("unexpected debugging"), and the
 * whole point of the 5.3 gate is a clean test run.
 *
 * The replacements exist only on 5.3, while the local box and the UAT run 5.1
 * and 5.2 today, so a plain rename would break them. Callers therefore go
 * through this class:
 *
 *   - \core\user::create_user() / ::update_user() when they exist (5.3 and later);
 *   - the legacy user_create_user() / user_update_user() from user/lib.php
 *     otherwise (5.1, 5.2). Behaviour is identical: the 5.3 wrappers forward to
 *     the same code.
 *
 * Both Moodle versions accept the same arguments. Callers may pass an array or
 * an object; it is cast to stdClass here, exactly as the legacy functions do.
 *
 * The theme must NOT call this class: a theme cannot depend on a local plugin.
 * theme_sentientia uses inline method_exists() ternaries for its few calls.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_api {
    /**
     * Whether this Moodle has the 5.3 \core\user write API.
     *
     * @return bool
     */
    public static function has_core_user_api(): bool {
        return method_exists(\core\user::class, 'create_user') && method_exists(\core\user::class, 'update_user');
    }

    /**
     * Create a user (fires user_created unless told not to).
     *
     * @param \stdClass|array $user the new user's fields (username, password, email, ...)
     * @param bool $updatepassword true if the authentication plugin should also store the password
     * @param bool $triggerevent false to suppress the user_created event
     * @return int id of the new user
     * @throws \moodle_exception on an invalid username or a duplicate
     */
    public static function create($user, bool $updatepassword = true, bool $triggerevent = true): int {
        global $CFG;
        $user = (object) $user;
        if (self::has_core_user_api()) {
            return \core\user::create_user($user, $updatepassword, $triggerevent);
        }
        require_once($CFG->dirroot . '/user/lib.php');
        return (int) \user_create_user($user, $updatepassword, $triggerevent);
    }

    /**
     * Update a user, matched on $user->id (fires user_updated unless told not to).
     *
     * @param \stdClass|array $user the fields to change; must include id
     * @param bool $updatepassword true if the authentication plugin should also store a new password
     * @param bool $triggerevent false to suppress the user_updated event
     * @return void
     * @throws \moodle_exception on an invalid username or a duplicate
     */
    public static function update($user, bool $updatepassword = true, bool $triggerevent = true): void {
        global $CFG;
        $user = (object) $user;
        if (self::has_core_user_api()) {
            \core\user::update_user($user, $updatepassword, $triggerevent);
            return;
        }
        require_once($CFG->dirroot . '/user/lib.php');
        \user_update_user($user, $updatepassword, $triggerevent);
    }
}
