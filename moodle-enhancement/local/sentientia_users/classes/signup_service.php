<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * W1-8 (2026-05-16) — Public-tenant self-registration service.
 *
 * Implements the BizLMS local_users signup flow replacement. The actual
 * user creation goes through Moodle's standard email-confirmation auth flow:
 *
 *   1. signup_service::register() validates the form payload
 *   2. user is created with auth='email' and confirmed=0
 *   3. Moodle's standard send_confirmation_email() fires a token link
 *   4. user clicks link → /login/confirm.php?p=<secret>&s=<username>
 *      sets confirmed=1 and lets them log in
 *
 * Tenant scope: registrations are pinned to the Public tenant by default
 * (configurable via local_sentientia_users/signup_tenant_path). Open registration
 * to Airpay or ZEEA tenants would be a security regression — those are
 * employee-only tenants managed via the HRMS import flow (W1-6).
 *
 * @package local_sentientia_users
 */
class signup_service {

    /** Open registration is disabled by default — admin must opt in. */
    public const SETTING_ENABLED = 'activeregistration';
    /** Tenant path string to assign new signups to (default '/77' = Public). */
    public const SETTING_TENANT_PATH = 'signup_tenant_path';
    /** Default tenant path if config is not set. */
    public const DEFAULT_TENANT_PATH = '/77';

    /**
     * Is self-registration currently enabled site-wide?
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_sentientia_users', self::SETTING_ENABLED);
    }

    /**
     * Validate a signup form payload. Returns an array of error messages
     * keyed by form field name; empty array means valid.
     *
     * @param object $data  Submitted form data (firstname, lastname, email,
     *                       password, password2, country, lang, agree_tos)
     */
    public static function validate(object $data): array {
        global $DB, $CFG;
        $errors = [];

        // Required fields.
        foreach (['firstname', 'lastname', 'email', 'password', 'password2'] as $field) {
            if (trim((string) ($data->$field ?? '')) === '') {
                $errors[$field] = get_string('required');
            }
        }
        if (!empty($errors)) {
            return $errors;
        }

        // Email format.
        $email = strtolower(trim($data->email));
        if (!validate_email($email)) {
            $errors['email'] = get_string('invalidemail');
            return $errors;
        }

        // Email uniqueness — check against any non-deleted user.
        if ($DB->record_exists_select('user',
            'LOWER(email) = :email AND deleted = 0',
            ['email' => $email])) {
            $errors['email'] = get_string('emailexists');
        }

        // Derived username from email — also must be unique.
        $username = self::derive_username($email);
        if ($DB->record_exists_select('user',
            'LOWER(username) = :u AND mnethostid = :h',
            ['u' => $username, 'h' => $CFG->mnet_localhost_id])) {
            $errors['email'] = get_string('emailexists');
        }

        // Password match.
        if ($data->password !== $data->password2) {
            $errors['password2'] = get_string('passwordsdiffer');
        }

        // Password policy (Moodle's built-in check).
        $err = '';
        if (!\check_password_policy($data->password, $err, null)) {
            $errors['password'] = $err;
        }

        // Terms-of-use acceptance — gates the whole flow per GDPR consent
        // requirement; without it we have no lawful basis to process the data.
        if (empty($data->agree_tos)) {
            $errors['agree_tos'] = get_string('signup_must_accept_tos',
                'local_sentientia_users');
        }

        return $errors;
    }

    /**
     * Create the user + dispatch the confirmation email. Returns the new
     * userid on success. Throws \moodle_exception on validation failure or
     * DB write failure.
     *
     * @param object $data  Validated form data (call validate() first).
     * @return int  New user.id
     */
    public static function register(object $data): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/authlib.php');

        if (!self::is_enabled()) {
            throw new \moodle_exception('signup_disabled', 'local_sentientia_users');
        }

        // Re-validate inside the service so the WS/API path is safe even
        // when callers skip the form layer.
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \moodle_exception('signup_validation_failed',
                'local_sentientia_users', '', implode('; ', $errors));
        }

        $email = strtolower(trim($data->email));
        $username = self::derive_username($email);

        $tenant_path = self::get_tenant_path();
        $costcenterid = self::derive_costcenterid_from_path($tenant_path);

        $user = (object) [
            'auth'              => 'email',
            'mnethostid'        => $CFG->mnet_localhost_id,
            'confirmed'         => 0,           // email confirmation pending
            'suspended'         => 0,
            'deleted'           => 0,
            'username'          => $username,
            'email'             => $email,
            'firstname'         => trim((string) $data->firstname),
            'lastname'          => trim((string) $data->lastname),
            'country'           => self::normalize_country((string) ($data->country ?? '')),
            'lang'              => self::normalize_lang((string) ($data->lang ?? '')),
            'open_path'         => $tenant_path,
            'open_costcenterid' => $costcenterid,
            'password'          => hash_internal_user_password((string) $data->password),
            'timecreated'       => time(),
            'timemodified'      => time(),
            'firstaccess'       => 0,
            'lastaccess'        => 0,
            'lastlogin'         => 0,
            'currentlogin'      => 0,
            'lastip'            => '',
            'secret'            => random_string(15),
            'description'       => '',
            'mailformat'        => 1,
            'maildigest'        => 0,
            'maildisplay'       => 2,
            'autosubscribe'     => 1,
            'trackforums'       => 0,
            'timezone'          => '99',
            'calendartype'      => $CFG->calendartype,
        ];

        $userid = \user_create_user($user, false, false);

        // ── ADR-017 / C1.6 (2026-05-28) ─────────────────────────────────
        // Every Public-signup user is a consumer per the §Resolution rule
        // (Q1 ruling: types are immutable per account; a consumer who is
        // later hired gets a NEW account, not a promotion).
        //
        // Write the user_type row immediately so first-login dashboard /
        // sidebar / profile all see the right shape.
        if (class_exists('\\local_airpay_core\\user_type_factory')) {
            try {
                $now = time();
                $DB->insert_record('local_airpay_user_type', (object) [
                    'userid'              => (int) $userid,
                    'user_type'           => 'consumer',
                    'provisioning_source' => 'signup_public',
                    'provisioned_at'      => $now,
                    'timecreated'         => $now,
                    'timemodified'        => $now,
                ]);

                // Seed an empty consumer_profile row. Onboarding (C1.5
                // change) populates interests + weekly_goal + consents
                // on first login.
                $DB->insert_record('local_airpay_consumer_profile', (object) [
                    'userid'              => (int) $userid,
                    'interests_json'      => null,
                    'weekly_goal'         => null,
                    'referral_source'     => $data->referral_source ?? null,
                    'consent_marketing'   => 0,  // default OFF (DPDP §7(a))
                    'consent_leaderboard' => 0,  // default OFF
                    'payment_history_url' => null,
                    'timecreated'         => $now,
                    'timemodified'        => $now,
                ]);
            } catch (\Throwable $e) {
                // Defensive: don't break signup if user_type write fails.
                // The classification CLI can backfill later. The user
                // can still log in + complete onboarding; profile will
                // default to employee badge until row is repaired.
                debugging('Signup: user_type row write failed for userid='
                    . $userid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        // Send Moodle's standard email-confirmation token.
        // Re-read the user so we have a row that includes the auto-set
        // user.id + the secret we generated above.
        $newuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        self::send_confirmation_email($newuser);

        return (int) $userid;
    }

    /**
     * Confirm a user account via their stored secret + username (matches
     * Moodle 5's `auth/email` plugin behaviour, but scoped to Airpay-signed-up
     * users).
     *
     * Returns one of the standard Moodle constants:
     *   AUTH_CONFIRM_OK      — confirmation just succeeded
     *   AUTH_CONFIRM_ALREADY — user was already confirmed (idempotent)
     *   AUTH_CONFIRM_ERROR   — bad secret, wrong username, or deleted user
     *
     * (Moodle 5 renamed the old USER_CONFIRM_* constants to AUTH_CONFIRM_*;
     * see /lib/authlib.php.)
     */
    public static function confirm(string $secret, string $username): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/authlib.php');

        $user = $DB->get_record('user',
            ['username' => strtolower($username), 'mnethostid' => 1, 'deleted' => 0]);
        if (!$user) {
            return \AUTH_CONFIRM_ERROR;
        }
        if ((int) $user->confirmed === 1) {
            return \AUTH_CONFIRM_ALREADY;
        }
        if ((string) $user->secret !== $secret) {
            return \AUTH_CONFIRM_ERROR;
        }
        $DB->set_field('user', 'confirmed', 1, ['id' => $user->id]);
        // Clear the secret to prevent re-use of the confirmation token.
        $DB->set_field('user', 'secret', '', ['id' => $user->id]);
        return \AUTH_CONFIRM_OK;
    }

    // ────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Username = email's local part + suffix-on-collision. e.g.
     *   alice@gmail.com → alice (or alice2 if alice taken).
     * Moodle requires lowercase + a small charset.
     */
    private static function derive_username(string $email): string {
        global $DB, $CFG;
        $base = strtolower($email);
        // Normalise non-username chars: keep letters, digits, ._@-
        $base = preg_replace('/[^a-z0-9._@\-]/', '', $base);
        if ($base === '') {
            $base = 'user_' . substr(md5($email), 0, 8);
        }
        // Find a free username.
        $candidate = $base;
        $suffix = 2;
        while ($DB->record_exists('user',
            ['username' => $candidate, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $candidate = $base . $suffix;
            $suffix++;
            if ($suffix > 1000) {
                // Defensive — append a random tail rather than loop forever.
                $candidate = $base . '_' . substr(md5(uniqid('', true)), 0, 6);
                break;
            }
        }
        return $candidate;
    }

    /**
     * Return the tenant path to assign new signups to (e.g. '/77').
     * Falls back to Public tenant if config is unset.
     */
    private static function get_tenant_path(): string {
        $raw = (string) get_config('local_sentientia_users', self::SETTING_TENANT_PATH);
        $raw = trim($raw);
        if ($raw === '') {
            return self::DEFAULT_TENANT_PATH;
        }
        // Normalise: must start with /, no trailing slash.
        if ($raw[0] !== '/') {
            $raw = '/' . $raw;
        }
        return rtrim($raw, '/');
    }

    /**
     * Top-level costcenter id is the first numeric segment of the path.
     */
    private static function derive_costcenterid_from_path(string $path): int {
        $parts = explode('/', trim($path, '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    private static function normalize_country(string $raw): string {
        $val = strtoupper(trim($raw));
        // ISO 3166-1 alpha-2 must be exactly 2 chars.
        if (preg_match('/^[A-Z]{2}$/', $val)) {
            return $val;
        }
        return 'IN';  // Airpay default
    }

    private static function normalize_lang(string $raw): string {
        $val = strtolower(trim($raw));
        $known = ['en', 'hi', 'kn', 'mr', 'sw', 'es', 'fr', 'de'];
        return in_array($val, $known, true) ? $val : 'en';
    }

    /**
     * Send the Moodle-standard email-confirmation token link.
     * Mirrors auth_email::user_signup() so a user signed up here can use
     * the SAME /login/confirm.php endpoint as a Moodle-native signup.
     */
    private static function send_confirmation_email(\stdClass $user): bool {
        global $CFG;
        require_once($CFG->dirroot . '/lib/moodlelib.php');
        // send_confirmation_email() lives in moodlelib.php and handles:
        //   - link generation: /login/confirm.php?data=<secret>/<username>
        //   - subject + body strings (auth_emailconfirmation)
        //   - fall through to email_to_user() with the noreply user
        return send_confirmation_email($user);
    }
}
