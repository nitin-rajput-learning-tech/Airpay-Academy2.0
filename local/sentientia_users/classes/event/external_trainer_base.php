<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared shape of the five external-trainer events (ADR-036 D9, session 5): invited, extended,
 * reactivated, expired, revoked.
 *
 * System context; objectid = the profile row ({local_sentientia_external_trainer_profile}.id);
 * relateduserid = the external trainer; userid = whoever acted (the admin, or the cron user for an
 * expiry). `other` carries at most the new expiry time: no name, e-mail or organisation, so the log
 * holds no more personal data than core's own user events.
 *
 * They land in the standard log. Adding them to local_sentientia_platform\audit_log::SENSITIVE_EVENTS
 * (the audit pages' allow-list) is the platform part of session 5, specified separately.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class external_trainer_base extends \core\event\base {

    /** @var string The CRUD letter of the concrete event. */
    protected const CRUD = 'u';

    protected function init() {
        $this->data['crud'] = static::CRUD;
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_external_trainer_profile';
    }

    /**
     * Build the event for a profile row.
     *
     * @param \stdClass $profile a row of the profile table (id, userid)
     * @param array $other e.g. ['expires_at' => int]
     * @return static
     */
    public static function create_from_profile(\stdClass $profile, array $other = []) {
        return static::create([
            'context'       => \context_system::instance(),
            'objectid'      => (int) $profile->id,
            'relateduserid' => (int) $profile->userid,
            'other'         => $other,
        ]);
    }

    public function get_url() {
        return new \moodle_url('/local/sentientia_users/external_trainers.php');
    }

    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => 'local_sentientia_external_trainer_profile', 'restore' => \core\event\base::NOT_MAPPED];
    }

    public static function get_other_mapping() {
        return false;
    }
}
