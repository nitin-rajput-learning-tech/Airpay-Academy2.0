<?php
/**
 * Notification Rule CRUD + query methods.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class rule_manager {

    const TABLE = 'local_sentientia_email_rules';

    /**
     * Get all rules, optionally filtered by tenant.
     *
     * @param int $tenantid 0 = all tenants
     * @return array of rule objects
     */
    public static function get_rules(int $tenantid = 0): array {
        global $DB;
        $conditions = [];
        $params = [];
        if ($tenantid > 0) {
            $conditions[] = "(tenant_id = :tid OR tenant_id = 0)";
            $params['tid'] = $tenantid;
        }
        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
        return $DB->get_records_select(self::TABLE, $where, $params, 'priority DESC, rule_name ASC');
    }

    /**
     * Get a single rule by ID.
     */
    public static function get_rule(int $id): ?object {
        global $DB;
        $rule = $DB->get_record(self::TABLE, ['id' => $id]);
        return $rule ?: null;
    }

    /**
     * Save or update a rule.
     *
     * @param object $data rule data
     * @return int rule ID
     */
    public static function save_rule(object $data): int {
        global $DB, $USER;

        $data->usermodified = $USER->id;
        $data->timemodified = time();

        if (!empty($data->id)) {
            $DB->update_record(self::TABLE, $data);
            return $data->id;
        }

        $data->timecreated = time();
        return $DB->insert_record(self::TABLE, $data);
    }

    /**
     * Toggle a rule's enabled status.
     *
     * @param int $id rule ID
     * @param bool $enabled
     */
    public static function toggle_rule(int $id, bool $enabled): void {
        global $DB;
        $DB->set_field(self::TABLE, 'enabled', (int)$enabled, ['id' => $id]);
        $DB->set_field(self::TABLE, 'timemodified', time(), ['id' => $id]);
    }

    /**
     * Delete a rule.
     */
    public static function delete_rule(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Get enabled rules for a specific type (used by cron task).
     *
     * @param string $ruletype
     * @param int $tenantid 0 = all
     * @return array
     */
    public static function get_enabled_rules(string $ruletype, int $tenantid = 0): array {
        global $DB;
        $conditions = ['rule_type = :rtype', 'enabled = 1'];
        $params = ['rtype' => $ruletype];
        if ($tenantid > 0) {
            $conditions[] = "(tenant_id = :tid OR tenant_id = 0)";
            $params['tid'] = $tenantid;
        }
        return $DB->get_records_select(self::TABLE, implode(' AND ', $conditions),
            $params, 'priority DESC');
    }

    /**
     * Get rule statistics for dashboard.
     *
     * @return object {total, enabled, disabled, by_type: [{type, count}]}
     */
    public static function get_stats(): object {
        global $DB;
        $total = $DB->count_records(self::TABLE);
        $enabled = $DB->count_records(self::TABLE, ['enabled' => 1]);
        $bytype = $DB->get_records_sql(
            "SELECT rule_type, COUNT(*) AS cnt FROM {" . self::TABLE . "} GROUP BY rule_type ORDER BY cnt DESC"
        );
        return (object)[
            'total'    => $total,
            'enabled'  => $enabled,
            'disabled' => $total - $enabled,
            'by_type'  => array_values($bytype),
        ];
    }
}
