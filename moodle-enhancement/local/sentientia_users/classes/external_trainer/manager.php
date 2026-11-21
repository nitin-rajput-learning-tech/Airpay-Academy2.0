<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external_trainer;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * External trainer accounts (ADR-036 D9, plan C6, session 5, 2026-11-20).
 *
 * Before this, an external trainer was added by hand as an ordinary user with the Trainer role at system
 * context: they saw every classroom and roster in the tenant (with e-mails and employee ids) and their
 * account never expired. This class gives them a type of their own:
 *
 *  - A row in {local_sentientia_external_trainer_profile} makes an account an external trainer. The
 *    classroom plugin asks is_external_trainer() and then ALWAYS limits them to their own classrooms
 *    and shows them names only, whatever the internal-trainer flag says.
 *  - Invite only: by a holder of local/sentientia_users:inviteexternaltrainer (the tenant administrator
 *    role; site admins), into the inviter's own tenant (ADR-031). A cross-tenant caller picks a tenant.
 *  - A manual-login account with a random password that is never sent or shown, and a forced password
 *    change. The invite e-mail carries a core password-reset link instead (core's set-password page
 *    then clears the forced change), never a password (unlike welcome_mailer).
 *  - The restricted role (role.php): classroom view and attendance only.
 *  - Expiry at max(end of the last non-cancelled session they train, validfrom) + 30 days, where
 *    validfrom is the invite time or the last reactivation/extension. The daily task recomputes it,
 *    reminds the admins 7 days before, and suspends the account when it is reached. It never deletes
 *    an account: attendance (markedby) and feedback (subject_userid) refer to it. An ended (expired or
 *    revoked) account comes back only through reactivate(): the Manage Users toggle and bulk/CSV
 *    activate refuse it (ended_userids()), and the daily task suspends it again if anything else
 *    unsuspended it (review fix 2026-11-20).
 *  - Every invite, extension, reactivation, expiry and revoke is a logged event
 *    (local_sentientia_users\event\external_trainer_*); the role assignment and the suspension fire
 *    core's role_assigned and user_updated as well.
 *
 * Behind sentientia.users.external_trainer.enabled (default OFF): with it OFF nothing here can be
 * started by a person (no page, no invite, no admin action). The expiry task still runs: it touches only
 * rows that exist, and a row exists only because the flag was ON when the invite was sent.
 *
 * The user-type registry (ADR-017) lives in local_sentientia_platform. The provider class is ready
 * (local_sentientia_users\user_type\external_trainer_provider) but the factory does not list the type
 * yet; until it does, no local_sentientia_user_type row is written for these accounts (the factory
 * would throw on an unknown type). Once it does, invite() writes the row and the daily task back-fills
 * it for accounts invited earlier (sync_user_type_rows()).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string Feature flag. */
    public const FLAG = 'sentientia.users.external_trainer.enabled';
    /** @var string Profile table. */
    public const TABLE = 'local_sentientia_external_trainer_profile';
    /** @var string Who may invite and manage external trainers. */
    public const CAPABILITY = 'local/sentientia_users:inviteexternaltrainer';
    /** @var string The ADR-017 type id. */
    public const TYPE = 'external_trainer';
    /** @var string The ADR-017 provisioning source. */
    public const PROVISIONING_SOURCE = 'trainer_invite';

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    /** @var int Access lasts this long after the last session (or after validfrom). */
    public const GRACE = 30 * DAYSECS;
    /** @var int The admins are reminded this long before the expiry. */
    public const REMINDER_LEAD = 7 * DAYSECS;

    // ─────────────────────────────────────────────────────────────────────
    // Lookups
    // ─────────────────────────────────────────────────────────────────────

    /** Is the feature switched on for the current user's tenant? */
    public static function enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * Is the feature switched on for the tenant a trainer would be placed in? enabled() answers for the
     * INVITER's tenant; a cross-tenant inviter whose own tenant has the flag ON must not create accounts
     * in a tenant where it is OFF (per-tenant rollout, review fix 2026-11-20). A path with no tenant root
     * is never enabled.
     *
     * @param string $path a tenant path ('/1', '/1/5')
     * @return bool
     */
    public static function enabled_for_path(string $path): bool {
        $root = tenant::root_for_user((object) ['open_path' => $path]);
        return $root > 0 && \local_sentientia_platform\feature_flags::is_enabled_for_tenant(self::FLAG, $root);
    }

    /** Does the profile table exist yet (it does once upgrade step 2026112000 ran)? */
    public static function table_ready(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::TABLE);
    }

    /**
     * Is this account an external trainer? True for every status: an expired or revoked trainer keeps the
     * restrictions (and is suspended anyway).
     *
     * @param int $userid
     * @return bool
     */
    public static function is_external_trainer(int $userid): bool {
        global $DB;
        if ($userid <= 0 || !self::table_ready()) {
            return false;
        }
        return $DB->record_exists(self::TABLE, ['userid' => $userid]);
    }

    /**
     * @param int $userid
     * @return \stdClass|null the profile row
     */
    public static function get_profile(int $userid): ?\stdClass {
        global $DB;
        if ($userid <= 0 || !self::table_ready()) {
            return null;
        }
        return $DB->get_record(self::TABLE, ['userid' => $userid]) ?: null;
    }

    /**
     * May the user (default: current) use the external-trainer admin page? Flag ON and the capability.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function can_manage(?int $userid = null): bool {
        return self::table_ready() && self::enabled()
            && has_capability(self::CAPABILITY, \context_system::instance(), $userid);
    }

    /**
     * The page and action guard: refuse unless the flag is ON and the caller holds the capability.
     *
     * @return void
     * @throws \moodle_exception exttrainer_disabled
     * @throws \required_capability_exception
     */
    public static function require_can_manage(): void {
        if (!self::table_ready() || !self::enabled()) {
            throw new \moodle_exception('exttrainer_disabled', 'local_sentientia_users');
        }
        require_capability(self::CAPABILITY, \context_system::instance());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tenant placement (ADR-031)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The tenants a CROSS-TENANT inviter may place a trainer in: tenant root path => name, only those
     * where the flag is ON (enabled_for_path()). Empty for a scoped caller, who always invites into their
     * own tenant. The invite form offers exactly these.
     *
     * @return array<string, string>
     */
    public static function tenant_options(): array {
        return array_filter(self::all_tenant_options(), fn($name, $path) => self::enabled_for_path((string) $path),
            ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Every tenant a cross-tenant caller may pick, whatever the flag says there: tenant root path => name.
     * Empty for a scoped caller.
     *
     * @return array<string, string>
     */
    private static function all_tenant_options(): array {
        if (!tenant::is_cross_tenant()) {
            return [];
        }
        $options = [];
        if (class_exists('\\local_sentientia_org\\org_manager')) {
            foreach (\local_sentientia_org\org_manager::get_tenants() as $org) {
                $path = '/' . trim((string) ($org->path ?? ''), '/');
                if (preg_match('~^/\d+$~', $path)) {
                    $options[$path] = format_string((string) ($org->fullname ?? $path), true,
                        ['context' => \context_system::instance(), 'escape' => false]);
                }
            }
        }
        if (!$options) {
            foreach (tenant::VALID_TENANTS as $root) {
                $options['/' . (int) $root] = '/' . (int) $root;
            }
        }
        return $options;
    }

    /**
     * The tenant path an invite by the current user lands in. A scoped caller: their own tenant, whatever
     * was asked. A cross-tenant caller: the tenant they picked, which must be a tenant they may pick
     * (whether the flag is ON there is invite()'s check, so a picked tenant with the flag OFF gets
     * exttrainer_disabled, not a "choose a tenant" error). A caller with no tenant: refused (fail closed).
     *
     * @param string $requested the picked tenant root ('/1'); ignored for a scoped caller
     * @return string
     * @throws \moodle_exception
     */
    public static function invite_path_for_caller(string $requested = ''): string {
        $scope = tenant::scope_path();
        if ($scope === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        if ($scope !== '') {
            return $scope;
        }
        $requested = '/' . trim($requested, '/');
        if (!array_key_exists($requested, self::all_tenant_options())) {
            throw new \moodle_exception('exttrainer_error_tenant', 'local_sentientia_users');
        }
        return $requested;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Invite
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Why an invite for this e-mail address cannot be sent, as a lang string id, or null when it can.
     * Shared by the form's validation and invite().
     *
     * @param string $email
     * @return string|null
     */
    public static function email_problem(string $email): ?string {
        global $CFG, $DB;
        $email = trim($email);
        if ($email === '' || !validate_email($email)) {
            return 'exttrainer_error_invalidemail';
        }
        $select = $DB->sql_equal('email', ':email', false) . ' AND deleted = 0';
        if ($DB->record_exists_select('user', $select, ['email' => $email])) {
            return 'exttrainer_error_emailtaken';
        }
        $username = self::username_for($email);
        if ($username === '' || $DB->record_exists('user', ['username' => $username,
                'mnethostid' => $CFG->mnet_localhost_id])) {
            return 'exttrainer_error_usernametaken';
        }
        return null;
    }

    /**
     * The username an invite gives: the e-mail address, lower case, cleaned the way core cleans a username
     * (core refuses a username that cleaning would change, e.g. one with a '+').
     *
     * @param string $email
     * @return string
     */
    public static function username_for(string $email): string {
        return (string) clean_param(\core_text::strtolower(trim($email)), PARAM_USERNAME);
    }

    /**
     * Invite an external trainer: create the account in the caller's tenant, give it the restricted role,
     * write the profile row, log the invite and send the invite e-mail.
     *
     * @param \stdClass $data firstname, lastname, email, organisation (optional), tenantpath (cross-tenant
     *                        callers only)
     * @param bool $sendemail false only for callers that send the invite themselves (tests use a sink)
     * @return int the new user id
     * @throws \moodle_exception
     */
    public static function invite(\stdClass $data, bool $sendemail = true): int {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/moodlelib.php');

        self::require_can_manage();

        $firstname = trim(clean_param((string) ($data->firstname ?? ''), PARAM_NOTAGS));
        $lastname = trim(clean_param((string) ($data->lastname ?? ''), PARAM_NOTAGS));
        $email = trim((string) ($data->email ?? ''));
        $organisation = \core_text::substr(trim(clean_param((string) ($data->organisation ?? ''), PARAM_TEXT)), 0, 255);
        if ($firstname === '' || $lastname === '') {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_users');
        }
        if ($problem = self::email_problem($email)) {
            throw new \moodle_exception($problem, 'local_sentientia_users');
        }
        $path = self::invite_path_for_caller((string) ($data->tenantpath ?? ''));
        // The flag is per tenant: require_can_manage() checked the inviter's tenant, this checks the one the
        // trainer lands in (a cross-tenant inviter may pick another). Review fix 2026-11-20.
        if (!self::enabled_for_path($path)) {
            throw new \moodle_exception('exttrainer_disabled', 'local_sentientia_users');
        }

        $now = time();
        $actorid = (int) ($USER->id ?? 0);

        $transaction = $DB->start_delegated_transaction();
        try {
            $user = (object) [
                'auth'       => 'manual',
                'confirmed'  => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username'   => self::username_for($email),
                'email'      => $email,
                'firstname'  => $firstname,
                'lastname'   => $lastname,
                'lang'       => $CFG->lang,
                'timezone'   => '99',
                // Their address is shown to nobody (learners and colleagues see their name only).
                'maildisplay' => 0,
            ];
            // No password through the create call (that would run it through the site's password policy,
            // which a random string can fail); a random one is stored just after, as user_manager does.
            $userid = \local_sentientia_platform\compat\user_api::create($user, false, true);
            // Never sent and never shown: the trainer sets their own password from the invite link.
            update_internal_user_password($DB->get_record('user', ['id' => $userid], '*', MUST_EXIST),
                random_string(40));

            // ADR-031: the account belongs to the inviter's tenant; a user with no tenant sees nothing.
            if (isset($DB->get_columns('user')['open_path'])) {
                $DB->set_field('user', 'open_path', $path, ['id' => $userid]);
            }
            set_user_preference('auth_forcepasswordchange', 1, $userid);

            $profileid = (int) $DB->insert_record(self::TABLE, (object) [
                'userid'       => $userid,
                'organisation' => $organisation,
                'invitedby'    => $actorid,
                'open_path'    => $path,
                'validfrom'    => $now,
                'expires_at'   => $now + self::GRACE,
                'status'       => self::STATUS_ACTIVE,
                'remindedat'   => 0,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);

            role::assign($userid);
            self::write_user_type_row($userid, $now);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        $profile = $DB->get_record(self::TABLE, ['id' => $profileid], '*', MUST_EXIST);
        \local_sentientia_users\event\external_trainer_invited::create_from_profile($profile, [
            'expires_at' => (int) $profile->expires_at,
        ])->trigger();

        if ($sendemail) {
            invite_mailer::send($userid);
        }
        return $userid;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Expiry
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The end of the last non-cancelled session the user trains (as the session's trainer, the classroom's
     * primary trainer or a co-trainer), from the classroom plugin; null when they train none or the
     * classroom plugin is not installed.
     *
     * @param int $userid
     * @return int|null
     */
    public static function last_session_end(int $userid): ?int {
        $class = '\\local_sentientia_classroom\\session_manager';
        if (!class_exists($class) || !method_exists($class, 'trainer_last_session_end')) {
            return null;
        }
        return $class::trainer_last_session_end($userid);
    }

    /**
     * When this external trainer's access ends: max(last session end, validfrom) + 30 days.
     *
     * @param \stdClass $profile a profile row
     * @return int
     */
    public static function compute_expiry(\stdClass $profile): int {
        $base = max((int) $profile->validfrom, (int) (self::last_session_end((int) $profile->userid) ?? 0));
        return $base + self::GRACE;
    }

    /**
     * Store a new expires_at when it changed. A later expiry clears remindedat, so the admins are reminded
     * again before the new date.
     *
     * @param \stdClass $profile a profile row (updated in place)
     * @return bool whether it changed
     */
    private static function store_expiry(\stdClass $profile): bool {
        global $DB;
        $new = self::compute_expiry($profile);
        if ($new === (int) $profile->expires_at) {
            return false;
        }
        $update = (object) ['id' => (int) $profile->id, 'expires_at' => $new, 'timemodified' => time()];
        if ($new > (int) $profile->expires_at) {
            $update->remindedat = 0;
            $profile->remindedat = 0;
        }
        $DB->update_record(self::TABLE, $update);
        $profile->expires_at = $new;
        return true;
    }

    /**
     * Recompute the expiry of every external trainer among these users (anyone else is ignored). The
     * classroom plugin calls it when a classroom or session changes; the status is not touched (an expired
     * trainer who is assigned again stays expired until an admin reactivates them).
     *
     * @param int[] $userids
     * @return int how many expiries changed
     */
    public static function recalculate_for_users(array $userids): int {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), fn($id) => $id > 0)));
        if (!$userids || !self::table_ready()) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'etu');
        $changed = 0;
        foreach ($DB->get_records_select(self::TABLE, "userid $insql", $params) as $profile) {
            if (self::store_expiry($profile)) {
                $changed++;
            }
        }
        return $changed;
    }

    /**
     * The daily task body: recompute every active trainer's expiry, remind the admins of those that end
     * within REMINDER_LEAD, suspend those that ended, suspend again every expired or revoked trainer whose
     * account is live, and back-fill the ADR-017 type rows when the type is registered.
     *
     * The second suspension pass (review fix 2026-11-20) is the backstop for every way an account can be
     * unsuspended without reactivate(): core's user admin, the HRMS sync, or a path that forgot the
     * activation guard (activation_blocked()). Without it such an account stayed live for good, because
     * the first pass reads active rows only. Core's user_updated event logs each re-suspension.
     *
     * @param int|null $now
     * @return array{recomputed: int, reminded: int, expired: int, resuspended: int, typed: int}
     */
    public static function run_expiry(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $out = ['recomputed' => 0, 'reminded' => 0, 'expired' => 0, 'resuspended' => 0, 'typed' => 0];
        if (!self::table_ready()) {
            return $out;
        }

        $active = $DB->get_records(self::TABLE, ['status' => self::STATUS_ACTIVE], 'id ASC');
        foreach ($active as $profile) {
            try {
                if (self::store_expiry($profile)) {
                    $out['recomputed']++;
                }
                if ((int) $profile->expires_at <= $now) {
                    self::expire($profile, $now);
                    $out['expired']++;
                } else if ((int) $profile->expires_at <= $now + self::REMINDER_LEAD
                        && (int) $profile->remindedat === 0) {
                    self::remind($profile, $now);
                    $out['reminded']++;
                }
            } catch (\Throwable $e) {
                // One account must not stop the others from expiring.
                debugging('external_trainer expiry failed for profile ' . (int) $profile->id . ': '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Ended (expired or revoked) but live again: suspend once more. Only reactivate() gives access back.
        $live = $DB->get_records_sql(
            "SELECT p.*
               FROM {" . self::TABLE . "} p
               JOIN {user} u ON u.id = p.userid
              WHERE p.status <> :active AND u.deleted = 0 AND u.suspended = 0
           ORDER BY p.id ASC", ['active' => self::STATUS_ACTIVE]);
        foreach ($live as $profile) {
            try {
                if (self::suspend((int) $profile->userid)) {
                    $out['resuspended']++;
                }
            } catch (\Throwable $e) {
                debugging('external_trainer re-suspension failed for profile ' . (int) $profile->id . ': '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $out['typed'] = self::sync_user_type_rows();
        return $out;
    }

    /**
     * Expire one account: the user suspended and logged out, then status expired, the expiry logged.
     *
     * Suspension FIRST (review fix 2026-11-20): if it throws, the row is still active and the next run
     * tries again. The other order left an expired row on a live account when suspend() failed, and the
     * first pass of run_expiry() never revisits a row that is not active.
     *
     * @param \stdClass $profile
     * @param int $now
     * @return void
     */
    private static function expire(\stdClass $profile, int $now): void {
        global $DB;
        self::suspend((int) $profile->userid);
        $DB->update_record(self::TABLE, (object) [
            'id' => (int) $profile->id, 'status' => self::STATUS_EXPIRED, 'timemodified' => $now,
        ]);
        $profile->status = self::STATUS_EXPIRED;
        \local_sentientia_users\event\external_trainer_expired::create_from_profile($profile, [
            'expires_at' => (int) $profile->expires_at,
        ])->trigger();
    }

    /**
     * Tell the inviter and the tenant's admins that this trainer's access ends soon, once per expiry date.
     *
     * @param \stdClass $profile
     * @param int $now
     * @return int how many people were told
     */
    private static function remind(\stdClass $profile, int $now): int {
        global $DB;
        $trainer = \core_user::get_user((int) $profile->userid);
        $sent = 0;
        if ($trainer && empty($trainer->deleted)) {
            $url = (new \moodle_url('/local/sentientia_users/external_trainers.php'))->out(false);
            $site = format_string(get_site()->fullname, true, ['context' => \context_system::instance()]);
            foreach (self::reminder_recipients($profile) as $recipient) {
                $sm = get_string_manager();
                $a = (object) [
                    'name' => fullname($trainer),
                    'date' => userdate((int) $profile->expires_at,
                        $sm->get_string('strftimedate', 'langconfig', null, $recipient->lang)),
                    'site' => $site,
                    'url'  => $url,
                    'days' => (int) (self::GRACE / DAYSECS),
                ];
                $message = new \core\message\message();
                $message->component = 'local_sentientia_users';
                $message->name = 'exttrainer_expiring';
                $message->courseid = (int) SITEID;
                $message->userfrom = \core_user::get_noreply_user();
                $message->userto = $recipient;
                $message->subject = $sm->get_string('exttrainer_expiring_subject', 'local_sentientia_users', $a,
                    $recipient->lang);
                $message->fullmessage = $sm->get_string('exttrainer_expiring_body', 'local_sentientia_users', $a,
                    $recipient->lang);
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = nl2br(s($message->fullmessage));
                $message->smallmessage = $message->subject;
                $message->notification = 1;
                $message->contexturl = $url;
                $message->contexturlname = $sm->get_string('exttrainer_page_title', 'local_sentientia_users', null,
                    $recipient->lang);
                if (message_send($message)) {
                    $sent++;
                }
            }
        }
        $DB->update_record(self::TABLE, (object) [
            'id' => (int) $profile->id, 'remindedat' => $now, 'timemodified' => $now,
        ]);
        $profile->remindedat = $now;
        return $sent;
    }

    /**
     * Who hears that a trainer's access ends: the inviter (while their account is live), and every live
     * holder of the invite capability in the trainer's tenant. Site-wide administrators are not added,
     * except as the inviter: they would hear about every tenant.
     *
     * @param \stdClass $profile
     * @return \stdClass[] user records keyed by id
     */
    public static function reminder_recipients(\stdClass $profile): array {
        $root = tenant::root_for_user((object) ['open_path' => (string) ($profile->open_path ?? '')]);
        $out = [];
        $inviter = (int) $profile->invitedby > 0 ? \core_user::get_user((int) $profile->invitedby) : false;
        if ($inviter && empty($inviter->deleted) && empty($inviter->suspended)) {
            $out[(int) $inviter->id] = $inviter;
        }
        if ($root > 0) {
            $holders = get_users_by_capability(\context_system::instance(), self::CAPABILITY,
                'u.id, u.open_path, u.suspended, u.deleted', 'u.id ASC');
            foreach ($holders as $holder) {
                if (isset($out[(int) $holder->id]) || !empty($holder->suspended) || !empty($holder->deleted)) {
                    continue;
                }
                if (tenant::root_for_user($holder) === $root && (int) $holder->id !== (int) $profile->userid) {
                    $user = \core_user::get_user((int) $holder->id);
                    if ($user) {
                        $out[(int) $user->id] = $user;
                    }
                }
            }
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin actions (flag + capability + same tenant)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Load an external trainer the current user may act on.
     *
     * @param int $userid
     * @return \stdClass the profile row
     * @throws \moodle_exception
     */
    private static function require_target(int $userid): \stdClass {
        self::require_can_manage();
        $profile = self::get_profile($userid);
        if (!$profile) {
            throw new \moodle_exception('exttrainer_error_notexternal', 'local_sentientia_users');
        }
        tenant::require_same_tenant_user($userid);
        return $profile;
    }

    /**
     * Extend an active trainer's access: validfrom moves to now, so access lasts at least 30 more days (or
     * until 30 days after their last session, if that is later).
     *
     * @param int $userid
     * @return \stdClass the updated profile
     */
    public static function extend(int $userid): \stdClass {
        global $DB;
        $profile = self::require_target($userid);
        if ($profile->status !== self::STATUS_ACTIVE) {
            throw new \moodle_exception('exttrainer_error_status', 'local_sentientia_users', '',
                get_string('exttrainer_status_' . $profile->status, 'local_sentientia_users'));
        }
        $now = time();
        $DB->update_record(self::TABLE, (object) ['id' => (int) $profile->id, 'validfrom' => $now,
            'timemodified' => $now]);
        $profile->validfrom = $now;
        self::store_expiry($profile);
        \local_sentientia_users\event\external_trainer_extended::create_from_profile($profile, [
            'expires_at' => (int) $profile->expires_at,
        ])->trigger();
        return $profile;
    }

    /**
     * Reactivate an expired or revoked trainer (an explicit admin action, ADR-036 open question 2): status
     * active, validfrom now, the account unsuspended.
     *
     * @param int $userid
     * @return \stdClass the updated profile
     */
    public static function reactivate(int $userid): \stdClass {
        global $DB;
        $profile = self::require_target($userid);
        if ($profile->status === self::STATUS_ACTIVE) {
            throw new \moodle_exception('exttrainer_error_status', 'local_sentientia_users', '',
                get_string('exttrainer_status_active', 'local_sentientia_users'));
        }
        $now = time();
        $profile->validfrom = $now;
        $profile->status = self::STATUS_ACTIVE;
        $profile->remindedat = 0;
        $DB->update_record(self::TABLE, (object) ['id' => (int) $profile->id, 'validfrom' => $now,
            'status' => self::STATUS_ACTIVE, 'remindedat' => 0, 'timemodified' => $now]);
        self::store_expiry($profile);
        \local_sentientia_platform\compat\user_api::update((object) ['id' => $userid, 'suspended' => 0], false, true);
        \local_sentientia_users\event\external_trainer_reactivated::create_from_profile($profile, [
            'expires_at' => (int) $profile->expires_at,
        ])->trigger();
        return $profile;
    }

    /**
     * Revoke an active trainer at once: status revoked, the account suspended and logged out.
     *
     * @param int $userid
     * @return \stdClass the updated profile
     */
    public static function revoke(int $userid): \stdClass {
        global $DB;
        $profile = self::require_target($userid);
        if ($profile->status !== self::STATUS_ACTIVE) {
            throw new \moodle_exception('exttrainer_error_status', 'local_sentientia_users', '',
                get_string('exttrainer_status_' . $profile->status, 'local_sentientia_users'));
        }
        $now = time();
        // Suspension first, as in expire(): a failure leaves the row active (and the admin sees the error).
        self::suspend($userid);
        $DB->update_record(self::TABLE, (object) ['id' => (int) $profile->id,
            'status' => self::STATUS_REVOKED, 'timemodified' => $now]);
        $profile->status = self::STATUS_REVOKED;
        \local_sentientia_users\event\external_trainer_revoked::create_from_profile($profile)->trigger();
        return $profile;
    }

    /**
     * Suspend an account and end its login sessions. Never a site admin (an external trainer is never one;
     * this is a guard).
     *
     * @param int $userid
     * @return bool false when refused (a site admin)
     */
    private static function suspend(int $userid): bool {
        if (is_siteadmin($userid)) {
            debugging('external_trainer: refusing to suspend site admin ' . $userid, DEBUG_DEVELOPER);
            return false;
        }
        \local_sentientia_platform\compat\user_api::update((object) ['id' => $userid, 'suspended' => 1], false, true);
        if (method_exists(\core\session\manager::class, 'destroy_user_sessions')) {
            \core\session\manager::destroy_user_sessions($userid);
        } else {
            \core\session\manager::kill_user_sessions($userid);
        }
        return true;
    }

    /**
     * Which of these accounts are external trainers whose access ended (expired or revoked)? Their account
     * may not be unsuspended by the ordinary paths (the Manage Users toggle, bulk and CSV activate): only
     * reactivate() on external_trainers.php gives access back, so the profile and the account agree and
     * the expiry is recomputed. Review fix 2026-11-20. Works whatever the flag says: the rows exist only
     * because it was ON once, and run_expiry() suspends such an account again anyway.
     *
     * @param int[] $userids
     * @return int[] the ended external trainers among them
     */
    public static function ended_userids(array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), fn($id) => $id > 0)));
        if (!$userids || !self::table_ready()) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'etx');
        $params['active'] = self::STATUS_ACTIVE;
        return array_map('intval', $DB->get_fieldset_select(self::TABLE, 'userid',
            "userid $insql AND status <> :active", $params));
    }

    /**
     * May this account NOT be unsuspended by an ordinary path? See ended_userids().
     *
     * @param int $userid
     * @return bool
     */
    public static function activation_blocked(int $userid): bool {
        return self::ended_userids([$userid]) !== [];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Listing (the admin page)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The external trainers of the caller's tenant (every tenant for a cross-tenant caller; none for a
     * caller with no tenant), active first, then by expiry.
     *
     * @return \stdClass[] profile rows with the trainer's name fields and username
     */
    public static function list_for_caller(): array {
        global $DB;
        if (!self::table_ready()) {
            return [];
        }
        [$tsql, $targs] = tenant::path_filter('u');
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $statusorder = "CASE p.status WHEN 'active' THEN 0 WHEN 'expired' THEN 1 ELSE 2 END";
        return $DB->get_records_sql(
            "SELECT p.*, u.username, u.suspended, {$namefields}
               FROM {" . self::TABLE . "} p
               JOIN {user} u ON u.id = p.userid
              WHERE u.deleted = 0 AND {$tsql}
           ORDER BY {$statusorder}, p.expires_at ASC, p.id ASC", $targs);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ADR-017 user type (registry in local_sentientia_platform)
    // ─────────────────────────────────────────────────────────────────────

    /** Does the platform's user-type factory know the external_trainer type yet? */
    public static function type_registered(): bool {
        $factory = '\\local_sentientia_platform\\user_type_factory';
        return class_exists($factory) && in_array(self::TYPE, $factory::all_types(), true);
    }

    /**
     * Write the account's ADR-017 type row, only when the type is registered and the account has no row
     * (a type is immutable per account, ADR-017 Q1: an existing row is never changed).
     *
     * @param int $userid
     * @param int $now
     * @return bool whether a row was written
     */
    private static function write_user_type_row(int $userid, int $now): bool {
        global $DB;
        if (!self::type_registered() || !$DB->get_manager()->table_exists('local_sentientia_user_type')
                || $DB->record_exists('local_sentientia_user_type', ['userid' => $userid])) {
            return false;
        }
        $DB->insert_record('local_sentientia_user_type', (object) [
            'userid'              => $userid,
            'user_type'           => self::TYPE,
            'provisioning_source' => self::PROVISIONING_SOURCE,
            'provisioned_at'      => $now,
            'timecreated'         => $now,
            'timemodified'        => $now,
        ]);
        if (class_exists('\\local_sentientia_platform\\user_type_factory')) {
            \local_sentientia_platform\user_type_factory::clear_cache();
        }
        return true;
    }

    /**
     * Back-fill the type row for external trainers that have none (invited before the type was
     * registered). A no-op until it is.
     *
     * @return int rows written
     */
    public static function sync_user_type_rows(): int {
        global $DB;
        if (!self::type_registered() || !self::table_ready()
                || !$DB->get_manager()->table_exists('local_sentientia_user_type')) {
            return 0;
        }
        $missing = $DB->get_fieldset_sql(
            "SELECT p.userid
               FROM {" . self::TABLE . "} p
          LEFT JOIN {local_sentientia_user_type} t ON t.userid = p.userid
              WHERE t.id IS NULL");
        $written = 0;
        foreach ($missing as $userid) {
            if (self::write_user_type_row((int) $userid, time())) {
                $written++;
            }
        }
        return $written;
    }
}
