<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external_trainer;

defined('MOODLE_INTERNAL') || die();

/**
 * The restricted role every external trainer holds (ADR-036 D9, session 5).
 *
 * Shortname `sentientia_externaltrainer`, NO archetype: core's teacher and manager defaults (and every
 * plugin capability that lists an archetype) never reach it, so it holds only what this class gives it.
 * That is exactly two capabilities, both owned by local_sentientia_classroom:
 *
 *   local/sentientia_classroom:view        open the classroom pages
 *   local/sentientia_classroom:attendance  mark attendance
 *
 * Nothing to manage, create, update or delete, and no user, course or report capability. It is
 * assigned at system context; the classroom plugin then limits an external trainer to the classrooms
 * they train (session_manager::trainer_scope_applies(), always ON for this type) and shows them
 * rosters by name only (session_manager::roster_names_only()).
 *
 * Created on demand: by the first invite, and by upgrade step 2026112000 where the classroom plugin's
 * capabilities already exist. A capability that does not exist yet (the classroom plugin is not
 * installed) is skipped, never an error; the next ensure() adds it.
 *
 * ensure() only ADDS the two grants. It does not strip a capability an administrator added by hand
 * (that would be a surprising side effect of sending an invite); the role description says not to.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role {

    /** @var string Role shortname. */
    public const SHORTNAME = 'sentientia_externaltrainer';

    /** @var string[] The only capabilities the role is given. */
    public const CAPABILITIES = [
        'local/sentientia_classroom:view',
        'local/sentientia_classroom:attendance',
    ];

    /**
     * The role id, or 0 when the role has not been created yet.
     *
     * @return int
     */
    public static function id(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
    }

    /**
     * Create the role if it is missing and make sure it holds its two capabilities.
     *
     * @return int the role id
     */
    public static function ensure(): int {
        global $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $roleid = self::id();
        if ($roleid <= 0) {
            $roleid = (int) create_role(
                get_string('exttrainer_role_name', 'local_sentientia_users'),
                self::SHORTNAME,
                get_string('exttrainer_role_description', 'local_sentientia_users'),
                ''  // No archetype (ADR-036 D9).
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        }

        $systemid = \context_system::instance()->id;
        foreach (self::CAPABILITIES as $capability) {
            if (get_capability_info($capability)) {
                assign_capability($capability, CAP_ALLOW, $roleid, $systemid, true);
            }
        }
        return $roleid;
    }

    /**
     * Give a user the role at system context (idempotent: role_assign() keeps one assignment).
     *
     * @param int $userid
     * @return void
     */
    public static function assign(int $userid): void {
        role_assign(self::ensure(), $userid, \context_system::instance()->id);
    }
}
