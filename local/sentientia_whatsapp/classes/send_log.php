<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Send log — Phase A1 iter 3+. Every WhatsApp/SMS/email attempt gets a
 * row here. The cadence engine + provider clients write to it; the
 * analytics class reads from it.
 *
 * @package local_sentientia_whatsapp
 */

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

class send_log {

    public const STATUS_QUEUED    = 'queued';
    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_BOUNCED   = 'bounced';
    public const STATUS_OPTED_OUT = 'opted_out';
    public const STATUS_MOCKED    = 'mocked';

    /**
     * Record an attempt. Returns the new row's ID for later status updates.
     *
     * @param array $values  Must include userid, channel, template_key.
     *                       Other fields default sensibly.
     * @return int
     */
    public static function record(array $values): int {
        global $DB;
        $now = time();
        $row = (object) array_merge([
            'status'      => self::STATUS_QUEUED,
            'attempts'    => 1,
            'mock_mode'   => 0,
            'timecreated' => $now,
            'timeupdated' => $now,
        ], $values);
        return (int) $DB->insert_record('local_sentientia_send_log', $row);
    }

    /**
     * Update status — typically called from provider webhook handlers
     * when a delivery receipt arrives.
     *
     * @param int $id
     * @param string $status
     * @param array $extra  e.g. ['provider_id' => '...', 'cost_paise' => 55]
     */
    public static function update_status(int $id, string $status,
            array $extra = []): void {
        global $DB;
        $row = $DB->get_record('local_sentientia_send_log', ['id' => $id],
            '*', MUST_EXIST);
        $row->status = $status;
        $row->timeupdated = time();
        foreach ($extra as $k => $v) {
            $row->{$k} = $v;
        }
        $DB->update_record('local_sentientia_send_log', $row);
    }

    /**
     * Get recent log entries for one user. Used by the preferences page
     * to show "your last 10 messages."
     */
    public static function recent_for_user(int $userid, int $limit = 20): array {
        global $DB;
        return $DB->get_records('local_sentientia_send_log',
            ['userid' => $userid],
            'timecreated DESC, id DESC',
            '*', 0, $limit);
    }
}
