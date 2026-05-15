<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Preference manager — read / write / audit user channel preferences.
 *
 * Phase A1 iter 1 (2026-05-15). No external API calls — this is
 * pure data persistence for opt-in state. The cadence engine in
 * iter 3+ reads from these tables to decide which channel to use
 * (with email as the always-available fallback).
 *
 * @package    local_airpay_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages per-user opt-in preferences for WhatsApp / SMS / email.
 */
class preference_manager {

    /** Default preference shape — returned when a user has no row yet. */
    public const DEFAULTS = [
        'mobile_number'    => '',
        'whatsapp_optin'   => 0,
        'sms_optin'        => 0,
        'email_optin'      => 1,   // Always-on baseline
        'prefer_channel'   => 'email',
        'dlt_consent_at'   => null,
        'dlt_consent_text' => null,
    ];

    /** Valid values for prefer_channel — used by both write + read paths. */
    public const VALID_CHANNELS = ['whatsapp', 'sms', 'email'];

    /**
     * Fetch a user's preferences. Returns the DEFAULTS shape (with userid +
     * id = null) if the user has no row yet — so callers never need to
     * handle a "first read" case specially.
     *
     * @param int $userid
     * @return \stdClass
     */
    public static function get(int $userid): \stdClass {
        global $DB;

        $row = $DB->get_record('local_airpay_user_channel_prefs',
            ['userid' => $userid]);

        if ($row) {
            return $row;
        }

        // No row yet — return a blank one with the defaults. Caller can
        // treat this as a no-op "user has never set preferences".
        $blank = (object) self::DEFAULTS;
        $blank->id = null;
        $blank->userid = $userid;
        $blank->timecreated = 0;
        $blank->timemodified = 0;
        return $blank;
    }

    /**
     * Validate a mobile number. Accepts +CC followed by 7-15 digits.
     * Whitespace is stripped before validation.
     *
     * Liberal accept-rule because India has 10-digit mobile numbers, US
     * has 10-digit, UK has 10-11, etc. The +country-code is the only
     * universal constant.
     *
     * @param string $number
     * @return bool
     */
    public static function is_valid_mobile(string $number): bool {
        $cleaned = preg_replace('/\s+/', '', $number);
        if ($cleaned === '') {
            return false;
        }
        return (bool) preg_match('/^\+\d{1,3}\d{6,14}$/', $cleaned);
    }

    /**
     * Normalise a mobile number to canonical form (strip spaces).
     *
     * @param string $number
     * @return string
     */
    public static function normalise_mobile(string $number): string {
        return preg_replace('/\s+/', '', $number);
    }

    /**
     * Set / update a user's preferences. Writes the row and an audit
     * entry per changed field. Idempotent — if no fields differ from
     * the existing row, no audit entry is written.
     *
     * @param int $userid          The user whose prefs to update
     * @param array $values        Associative array of {field => value} —
     *                             only keys in DEFAULTS are honoured.
     * @param int|null $changed_by Who made the change. Defaults to current user.
     * @param string $reason       Optional context for the audit log.
     * @return \stdClass The updated record.
     * @throws \moodle_exception on validation failure
     */
    public static function set(
        int $userid,
        array $values,
        ?int $changed_by = null,
        string $reason = ''
    ): \stdClass {
        global $DB, $USER;

        if ($changed_by === null) {
            $changed_by = $USER->id ?? null;
        }

        // Validate mobile number if it's being changed and not empty.
        if (isset($values['mobile_number']) && $values['mobile_number'] !== '') {
            $values['mobile_number'] = self::normalise_mobile($values['mobile_number']);
            if (!self::is_valid_mobile($values['mobile_number'])) {
                throw new \moodle_exception('mobile_invalid', 'local_airpay_whatsapp');
            }
        }

        // Validate prefer_channel.
        if (isset($values['prefer_channel'])
                && !in_array($values['prefer_channel'], self::VALID_CHANNELS, true)) {
            throw new \moodle_exception('invalidchoice', 'core',
                '', $values['prefer_channel']);
        }

        // DLT consent: if either WhatsApp or SMS is being opted IN, ensure
        // consent_text is set. Iter 3+ will refuse to send without it.
        $opting_in = (!empty($values['whatsapp_optin']) || !empty($values['sms_optin']));
        if ($opting_in && empty($values['dlt_consent_text'])
                && empty(self::get($userid)->dlt_consent_text)) {
            throw new \moodle_exception('dlt_consent_required',
                'local_airpay_whatsapp');
        }

        // Auto-stamp consent_at when consent_text is being set.
        if (!empty($values['dlt_consent_text']) && empty($values['dlt_consent_at'])) {
            $values['dlt_consent_at'] = time();
        }

        $now = time();
        $existing = $DB->get_record('local_airpay_user_channel_prefs',
            ['userid' => $userid]);

        $transaction = $DB->start_delegated_transaction();
        try {
            if ($existing) {
                // UPDATE path — write audit entries for changed fields.
                $audit_pairs = [];
                foreach ($values as $field => $new) {
                    if (!array_key_exists($field, self::DEFAULTS)) {
                        continue;  // Ignore unknown fields
                    }
                    $old = $existing->{$field} ?? null;
                    // Loose comparison — DB returns INT(1) as PHP string,
                    // so 0 == '0'. We want true diff detection.
                    if ((string) $old !== (string) $new) {
                        $existing->{$field} = $new;
                        $audit_pairs[$field] = [$old, $new];
                    }
                }

                if (!empty($audit_pairs)) {
                    $existing->timemodified = $now;
                    $DB->update_record('local_airpay_user_channel_prefs', $existing);
                    self::write_audit($userid, $changed_by, $audit_pairs, $reason);
                }
                $result = $existing;
            } else {
                // INSERT path — first-time row. Every non-default field
                // gets an audit entry so we have provenance from row one.
                $row = (object) array_merge(self::DEFAULTS, $values, [
                    'userid'       => $userid,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
                $row->id = $DB->insert_record('local_airpay_user_channel_prefs', $row);

                $audit_pairs = [];
                foreach ($values as $field => $new) {
                    if (!array_key_exists($field, self::DEFAULTS)) {
                        continue;
                    }
                    $default = self::DEFAULTS[$field];
                    if ((string) $default !== (string) $new) {
                        $audit_pairs[$field] = [null, $new];  // null = "no prior value"
                    }
                }
                if (!empty($audit_pairs)) {
                    self::write_audit($userid, $changed_by, $audit_pairs, $reason);
                }
                $result = $row;
            }
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Append one row to the audit table for each (field, old, new) tuple.
     *
     * @param int $userid
     * @param int|null $changed_by
     * @param array $pairs  field_name => [old_value, new_value]
     * @param string $reason
     */
    private static function write_audit(
        int $userid,
        ?int $changed_by,
        array $pairs,
        string $reason
    ): void {
        global $DB;

        // Capture the IP — DPDP requires consent provenance.
        $ip = getremoteaddr() ?? null;
        $now = time();

        foreach ($pairs as $field => [$old, $new]) {
            $DB->insert_record('local_airpay_user_channel_audit', (object) [
                'userid'      => $userid,
                'changed_by'  => $changed_by,
                'field_name'  => $field,
                'old_value'   => $old === null ? null : (string) $old,
                'new_value'   => $new === null ? null : (string) $new,
                'reason'      => $reason !== '' ? $reason : null,
                'ip_address'  => $ip,
                'timecreated' => $now,
            ]);
        }
    }

    /**
     * Retrieve recent audit history for a user. Useful for the user-facing
     * page to show "you opted in to WhatsApp on 15 May 2026" and for
     * admin tools to investigate.
     *
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function recent_audit(int $userid, int $limit = 20): array {
        global $DB;

        return $DB->get_records(
            'local_airpay_user_channel_audit',
            ['userid' => $userid],
            'timecreated DESC, id DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Used by the cadence engine (iter 3+) to decide which channel to use
     * for a given user. Returns the resolved channel name, taking into
     * account: tenant feature flags, user opt-in state, mobile number
     * presence, and DLT consent.
     *
     * Fall-back order:
     *   1. User's prefer_channel if it's opted-in + feature-flag-enabled
     *      + (for whatsapp/sms) mobile + DLT consent are all present
     *   2. Email (always-available baseline)
     *
     * @param int $userid
     * @return string  'whatsapp' | 'sms' | 'email'
     */
    public static function resolve_channel(int $userid): string {
        $prefs = self::get($userid);
        $preferred = $prefs->prefer_channel ?? 'email';

        // Email is always available — short-circuit if that's the choice.
        if ($preferred === 'email') {
            return 'email';
        }

        // For WhatsApp / SMS, verify the full chain:
        //   1. Feature flag is on
        //   2. User opted in
        //   3. Mobile number is on file
        //   4. DLT consent was given
        $flag_on = false;
        if (class_exists('\\local_airpay_core\\feature_flags')) {
            $flag_key = 'engagement.' . $preferred . '.enabled';
            $flag_on = \local_airpay_core\feature_flags::is_enabled($flag_key);
        }

        $optin_field = $preferred . '_optin';
        $is_optedin = !empty($prefs->{$optin_field});

        $has_mobile = !empty($prefs->mobile_number);
        $has_consent = !empty($prefs->dlt_consent_at);

        if ($flag_on && $is_optedin && $has_mobile && $has_consent) {
            return $preferred;
        }

        // Any condition failed — fall through to email.
        return 'email';
    }

    /**
     * Delete a user's preferences entirely. Used by the privacy provider
     * when a user requests data deletion under DPDP / GDPR. Cascades
     * to the audit table.
     *
     * @param int $userid
     */
    public static function delete_user_data(int $userid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('local_airpay_user_channel_prefs',
                ['userid' => $userid]);
            $DB->delete_records('local_airpay_user_channel_audit',
                ['userid' => $userid]);
            $DB->delete_records('local_airpay_user_channel_audit',
                ['changed_by' => $userid]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }
}
