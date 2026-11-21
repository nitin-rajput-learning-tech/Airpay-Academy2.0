<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Logged when an admin invites an external trainer (the account, its restricted role and its profile row were created).
 *
 * ADR-036 D9 (session 5). Shape: see external_trainer_base.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_trainer_invited extends external_trainer_base {

    /** @var string */
    protected const CRUD = 'c';

    public static function get_name() {
        return get_string('event_exttrainer_invited', 'local_sentientia_users');
    }

    public function get_description() {
        return "The user with id '{$this->userid}' invited the external trainer with id '{$this->relateduserid}'.";
    }
}
