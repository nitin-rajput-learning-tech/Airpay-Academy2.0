<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * DLT template registry — Phase A1 iter 2.
 *
 * Tracks pre-approved WhatsApp + SMS templates registered with the DLT
 * portal (per Indian TRAI TCCCPR-2018 regulations). The cadence engine
 * refuses to send any template not in `approved` state.
 *
 * Lifecycle:
 *   pending    — drafted internally, not yet submitted to DLT
 *   submitted  — sent to the DLT portal, awaiting operator decision
 *   approved   — operator approved; safe to send
 *   rejected   — operator rejected (reason captured for resubmission)
 *
 * @package    local_airpay_whatsapp
 */

namespace local_airpay_whatsapp;

defined('MOODLE_INTERNAL') || die();

class dlt_template_registry {

    public const VALID_CHANNELS   = ['whatsapp', 'sms'];
    public const VALID_CATEGORIES = ['transactional', 'promotional'];
    public const VALID_STATUSES   = ['pending', 'submitted', 'approved', 'rejected'];

    /**
     * Fetch a single template by its (key, channel, language) triple.
     *
     * @param string $template_key
     * @param string $channel
     * @param string $language
     * @return \stdClass|null
     */
    public static function get(string $template_key, string $channel,
            string $language = 'en'): ?\stdClass {
        global $DB;
        $row = $DB->get_record('local_airpay_dlt_templates', [
            'template_key' => $template_key,
            'channel'      => $channel,
            'language'     => $language,
        ]);
        return $row ?: null;
    }

    /**
     * The send-time check the cadence engine uses. Returns the row only
     * if status is `approved`, otherwise null. This is the gate that
     * keeps unapproved templates from reaching the provider.
     *
     * @param string $template_key
     * @param string $channel
     * @param string $language
     * @return \stdClass|null
     */
    public static function get_approved(string $template_key, string $channel,
            string $language = 'en'): ?\stdClass {
        $row = self::get($template_key, $channel, $language);
        if (!$row || $row->status !== 'approved') {
            return null;
        }
        return $row;
    }

    /**
     * Insert or update a template.
     *
     * @param array $values  Fields to set. template_key + channel are required.
     * @return int  ID of the inserted or updated row
     */
    public static function upsert(array $values): int {
        global $DB;

        if (empty($values['template_key']) || empty($values['channel'])) {
            throw new \moodle_exception('missingparam', '', '',
                'template_key + channel are required');
        }
        if (!in_array($values['channel'], self::VALID_CHANNELS, true)) {
            throw new \moodle_exception('invalidchoice', 'core',
                '', $values['channel']);
        }
        if (isset($values['category'])
                && !in_array($values['category'], self::VALID_CATEGORIES, true)) {
            throw new \moodle_exception('invalidchoice', 'core',
                '', $values['category']);
        }
        if (isset($values['status'])
                && !in_array($values['status'], self::VALID_STATUSES, true)) {
            throw new \moodle_exception('invalidchoice', 'core',
                '', $values['status']);
        }

        // Auto-extract {{variable}} placeholders from the body.
        if (isset($values['body'])) {
            $vars = self::extract_variables($values['body']);
            $values['variables_json'] = json_encode($vars);
        }

        $now = time();
        $language = $values['language'] ?? 'en';

        $existing = $DB->get_record('local_airpay_dlt_templates', [
            'template_key' => $values['template_key'],
            'channel'      => $values['channel'],
            'language'     => $language,
        ]);

        if ($existing) {
            foreach ($values as $k => $v) {
                $existing->{$k} = $v;
            }
            $existing->timemodified = $now;
            $DB->update_record('local_airpay_dlt_templates', $existing);
            return (int) $existing->id;
        }

        $row = (object) array_merge([
            'category'    => 'transactional',
            'status'      => 'pending',
            'language'    => 'en',
            'timecreated' => $now,
            'timemodified' => $now,
        ], $values);

        return (int) $DB->insert_record('local_airpay_dlt_templates', $row);
    }

    /**
     * Extract {{variable}} placeholders from a template body.
     *
     * @param string $body
     * @return string[]  unique variable names in first-seen order
     */
    public static function extract_variables(string $body): array {
        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/', $body, $matches);
        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Substitute {{variable}} placeholders with values. Returns the
     * rendered body. Missing variables remain as `{{name}}` (so they're
     * visible in QA) rather than throwing.
     *
     * @param string $body
     * @param array $vars  associative array of {name => value}
     * @return string
     */
    public static function render(string $body, array $vars): string {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            function ($m) use ($vars) {
                $key = $m[1];
                return array_key_exists($key, $vars) ? (string) $vars[$key] : $m[0];
            },
            $body
        );
    }

    /**
     * Update a template's DLT status. Used by the nightly sync cron
     * (iter 2 ships the data model; the actual portal-API client comes
     * with iter 3's [CONFIRM] gate flip).
     *
     * @param int $id
     * @param string $new_status
     * @param string|null $reason  Set when new_status === 'rejected'
     */
    public static function transition_status(int $id, string $new_status,
            ?string $reason = null): void {
        global $DB;

        if (!in_array($new_status, self::VALID_STATUSES, true)) {
            throw new \moodle_exception('invalidchoice', 'core', '', $new_status);
        }

        $row = $DB->get_record('local_airpay_dlt_templates', ['id' => $id],
            '*', MUST_EXIST);
        $row->status = $new_status;
        $row->timemodified = time();
        $row->last_synced_at = time();

        if ($new_status === 'submitted' && empty($row->submitted_at)) {
            $row->submitted_at = time();
        }
        if ($new_status === 'approved') {
            $row->approved_at = time();
            $row->rejection_reason = null;
        }
        if ($new_status === 'rejected') {
            $row->rejection_reason = $reason;
        }

        $DB->update_record('local_airpay_dlt_templates', $row);
    }

    /**
     * List templates for the admin UI, optionally filtered by channel + status.
     *
     * @param string|null $channel
     * @param string|null $status
     * @return array
     */
    public static function list_all(?string $channel = null,
            ?string $status = null): array {
        global $DB;
        $where = [];
        $params = [];
        if ($channel) {
            $where[] = 'channel = :channel';
            $params['channel'] = $channel;
        }
        if ($status) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        $sql = "SELECT * FROM {local_airpay_dlt_templates}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY template_key ASC, channel ASC, language ASC";
        return $DB->get_records_sql($sql, $params);
    }
}
