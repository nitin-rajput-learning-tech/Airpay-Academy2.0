<?php
/**
 * Template CRUD + override resolution chain.
 *
 * Resolution order:
 * 1. DB: tenant-specific override (tenant_id = current, is_active = 1)
 * 2. DB: global override (tenant_id = 0, is_active = 1)
 * 3. Mustache file fallback (templates/{key}.mustache)
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

class template_manager {

    /** Table name for overrides. */
    const TABLE = 'local_airpay_email_overrides';

    /**
     * Get the effective template content for a given key and tenant.
     *
     * Returns DB override if present, or null to indicate Mustache fallback.
     *
     * @param string $templatekey e.g. 'compliance/deadline_warning'
     * @param int $tenantid costcenter ID (0 = global)
     * @return object|null {subject, body_html, body_text, source} or null for file fallback
     */
    public static function get_override(string $templatekey, int $tenantid = 0): ?object {
        global $DB;

        // Priority 1: tenant-specific override.
        if ($tenantid > 0) {
            $override = $DB->get_record(self::TABLE, [
                'template_key' => $templatekey,
                'tenant_id'    => $tenantid,
                'is_active'    => 1,
            ]);
            if ($override) {
                $override->source = 'tenant_override';
                return $override;
            }
        }

        // Priority 2: global override.
        $override = $DB->get_record(self::TABLE, [
            'template_key' => $templatekey,
            'tenant_id'    => 0,
            'is_active'    => 1,
        ]);
        if ($override) {
            $override->source = 'global_override';
            return $override;
        }

        return null; // Mustache file fallback.
    }

    /**
     * Save or update a template override.
     *
     * @param string $templatekey
     * @param int    $tenantid 0 = global
     * @param string $subject
     * @param string $bodyhtml
     * @param string $bodytext
     * @return int record ID
     */
    public static function save_override(string $templatekey, int $tenantid,
                                          string $subject, string $bodyhtml, string $bodytext = ''): int {
        global $DB, $USER;

        $existing = $DB->get_record(self::TABLE, [
            'template_key' => $templatekey,
            'tenant_id'    => $tenantid,
        ]);

        if ($existing) {
            $existing->subject      = $subject;
            $existing->body_html    = $bodyhtml;
            $existing->body_text    = $bodytext ?: html_to_text($bodyhtml);
            $existing->is_active    = 1;
            $existing->usermodified = $USER->id;
            $existing->timemodified = time();
            $DB->update_record(self::TABLE, $existing);
            return $existing->id;
        }

        $record = new \stdClass();
        $record->tenant_id    = $tenantid;
        $record->template_key = $templatekey;
        $record->subject      = $subject;
        $record->body_html    = $bodyhtml;
        $record->body_text    = $bodytext ?: html_to_text($bodyhtml);
        $record->is_active    = 1;
        $record->usermodified = $USER->id;
        $record->timecreated  = time();
        $record->timemodified = time();
        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a template override (restores Mustache file fallback).
     *
     * @param int $id record ID
     */
    public static function delete_override(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Disable a template override without deleting it.
     *
     * @param int $id record ID
     */
    public static function disable_override(int $id): void {
        global $DB;
        $DB->set_field(self::TABLE, 'is_active', 0, ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Get all overrides for a specific tenant (or global).
     *
     * @param int $tenantid 0 = global overrides only
     * @return array of override objects
     */
    public static function get_all_overrides(int $tenantid = 0): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['tenant_id' => $tenantid], 'template_key ASC');
    }

    /**
     * Get the full template list with override status for each tenant.
     *
     * @param int $tenantid
     * @return array [{key, label, category, has_override, override_id, source}]
     */
    public static function get_templates_with_status(int $tenantid = 0): array {
        global $DB;

        $categories = email_renderer::get_template_list();
        $overrides = $DB->get_records(self::TABLE, ['tenant_id' => $tenantid]);
        $globaloverrides = $DB->get_records(self::TABLE, ['tenant_id' => 0]);

        // Index overrides by template_key.
        $overridemap = [];
        foreach ($globaloverrides as $o) {
            $overridemap[$o->template_key] = ['id' => $o->id, 'source' => 'global', 'active' => $o->is_active];
        }
        foreach ($overrides as $o) {
            $overridemap[$o->template_key] = ['id' => $o->id, 'source' => 'tenant', 'active' => $o->is_active];
        }

        $result = [];
        foreach ($categories as $cat) {
            foreach ($cat['templates'] as $tpl) {
                $info = $overridemap[$tpl['key']] ?? null;
                $result[] = [
                    'key'          => $tpl['key'],
                    'label'        => $tpl['label'],
                    'category'     => $cat['category'],
                    'catkey'       => $cat['catkey'],
                    'has_override' => !empty($info) && $info['active'],
                    'override_id'  => $info['id'] ?? 0,
                    'source'       => $info ? $info['source'] : 'file',
                    'is_bizlms'    => false,
                ];
            }
        }
        return $result;
    }
}
