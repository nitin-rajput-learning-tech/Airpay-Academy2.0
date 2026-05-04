<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

namespace local_airpay_notifications;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule manager — CRUD for notification rule definitions.
 *
 * Pairs with rule_engine which runs the rules at cron time. This class
 * handles administrative create/edit/toggle/delete on the rules themselves.
 *
 * @package    local_airpay_notifications
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_manager {

    private const TABLE = 'local_airpay_notif_rules';

    /** Valid rule_type values mapped to display labels. */
    public const RULE_TYPES = [
        'deadline_approaching' => 'Deadline Approaching',
        'course_not_started'   => 'Course Not Started',
        'streak_broken'        => 'Streak Broken',
        'manager_nudge'        => 'Manager Nudge',
        'achievement_earned'   => 'Achievement Earned',
        'new_course'           => 'New Course Available',
    ];

    /** Valid channel values. */
    public const CHANNELS = [
        'inapp'    => 'In-app',
        'email'    => 'Email',
        'push'     => 'Push notification',
        'whatsapp' => 'WhatsApp',
    ];

    /** Valid audience values. */
    public const AUDIENCES = [
        'learner' => 'Learner',
        'manager' => 'Manager',
        'admin'   => 'L&D Admin',
        'all'     => 'All users',
    ];

    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    public static function count_rules(?bool $enabled = null): int {
        global $DB;
        if ($enabled === null) {
            return $DB->count_records(self::TABLE);
        }
        return $DB->count_records(self::TABLE, ['enabled' => $enabled ? 1 : 0]);
    }

    /**
     * Create a new notification rule.
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name) || empty($data->rule_type)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_notifications');
        }

        // Validate enums.
        if (!array_key_exists($data->rule_type, self::RULE_TYPES)) {
            throw new \moodle_exception('invalidruletype', 'local_airpay_notifications');
        }
        $channel = $data->channel ?? 'inapp';
        if (!array_key_exists($channel, self::CHANNELS)) {
            throw new \moodle_exception('invalidchannel', 'local_airpay_notifications');
        }
        $audience = $data->audience ?? 'learner';
        if (!array_key_exists($audience, self::AUDIENCES)) {
            throw new \moodle_exception('invalidaudience', 'local_airpay_notifications');
        }

        $record = (object) [
            'name'         => trim($data->name),
            'rule_type'    => $data->rule_type,
            'channel'      => $channel,
            'trigger_days' => max(0, (int) ($data->trigger_days ?? 3)),
            'audience'     => $audience,
            'enabled'      => isset($data->enabled) ? (int) $data->enabled : 1,
            'template'     => $data->template ?? '',
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing rule.
     */
    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id, 'timemodified' => time()];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->rule_type)) {
            if (!array_key_exists($data->rule_type, self::RULE_TYPES)) {
                throw new \moodle_exception('invalidruletype', 'local_airpay_notifications');
            }
            $record->rule_type = $data->rule_type;
        }
        if (isset($data->channel)) {
            if (!array_key_exists($data->channel, self::CHANNELS)) {
                throw new \moodle_exception('invalidchannel', 'local_airpay_notifications');
            }
            $record->channel = $data->channel;
        }
        if (isset($data->audience)) {
            if (!array_key_exists($data->audience, self::AUDIENCES)) {
                throw new \moodle_exception('invalidaudience', 'local_airpay_notifications');
            }
            $record->audience = $data->audience;
        }
        if (isset($data->trigger_days)) $record->trigger_days = max(0, (int) $data->trigger_days);
        if (isset($data->enabled))      $record->enabled = (int) $data->enabled;
        if (isset($data->template))     $record->template = $data->template;

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Toggle rule enabled state.
     */
    public static function toggle_enabled(int $id, ?bool $enabled = null): bool {
        global $DB;
        $existing = $DB->get_record(self::TABLE, ['id' => $id], 'id, enabled', MUST_EXIST);
        $newstate = $enabled ?? !((bool) $existing->enabled);
        $DB->update_record(self::TABLE, (object) [
            'id' => $id,
            'enabled' => $newstate ? 1 : 0,
            'timemodified' => time(),
        ]);
        return $newstate;
    }

    /**
     * Delete a rule. (Logs are preserved for audit.)
     */
    public static function delete(int $id): bool {
        global $DB;
        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        return true;
    }
}
