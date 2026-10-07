<?php
/**
 * Read-only adapter for BizLMS notification tables.
 *
 * CRITICAL: This class NEVER writes to BizLMS tables. All operations are SELECT-only.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class legacy_bridge {

    /**
     * The column names of local_notification_info.
     *
     * The table has no install file of its own and no two BizLMS databases agree on its shape: the April 2026
     * production copy has open_path and NO costcenterid (writers since 2022 set open_path only), while databases
     * that went through the notifications upgrade step carry both, and older ones costcenterid alone.
     *
     * @return string[]
     */
    private static function template_columns(): array {
        global $DB;
        return array_keys($DB->get_columns('local_notification_info'));
    }

    /**
     * The tenant filter over BizLMS notification templates, built from the columns the table has.
     *
     * ADR-032 / mapping doc section 11, code fix 6: BizLMS writers since 2022 set local_notification_info.open_path
     * and never costcenterid, so the costcenterid filter alone hid those templates from every tenant admin. The
     * template is in a tenant when its open_path is /N or /N/..., where N is the caller's root. The costcenterid
     * match stays as an alternative where the column exists (rows written before 2022 set it), so nothing a caller
     * could see before is lost. A table without costcenterid (the production shape) is filtered by open_path
     * alone: naming a column the table lacks makes the query throw, which used to leave the Templates tab empty
     * for everybody.
     *
     * The importer's preflight reports how many templates have an open_path without its leading slash, which
     * this filter would not match (template_open_path_without_leading_slash).
     *
     * @param string $alias Alias of local_notification_info in the query.
     * @return array{0: string, 1: array} [sql, params]
     */
    private static function template_tenant_filter(string $alias): array {
        $columns = self::template_columns();
        return self::tenant_filter_for_columns($alias, in_array('open_path', $columns, true),
            in_array('costcenterid', $columns, true));
    }

    /**
     * The tenant filter for a given shape of local_notification_info (split out so every shape can be tested).
     *
     * @internal Use template_tenant_filter(); public only for the unit tests.
     * @param string $alias Alias of local_notification_info in the query.
     * @param bool $haspath The table has open_path.
     * @param bool $hascost The table has costcenterid.
     * @return array{0: string, 1: array} [sql, params]
     */
    public static function tenant_filter_for_columns(string $alias, bool $haspath, bool $hascost): array {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return ['1=1', []];
        }
        $parts = [];
        $args = [];
        if ($haspath) {
            [$pathsql, $pathargs] = \local_sentientia_platform\tenant::path_filter($alias, 'open_path');
            $parts[] = $pathsql;
            $args += $pathargs;
        }
        if ($hascost) {
            [$costsql, $costargs] = \local_sentientia_platform\tenant::sql_filter($alias);
            $parts[] = $costsql;
            $args += $costargs;
        }
        if (!$parts) {
            // Nothing to scope by: fail closed, as the tenant helpers do for a caller without a tenant.
            return ['1=0', []];
        }
        return ['(' . implode(' OR ', $parts) . ')', $args];
    }

    /**
     * The costcenter a template belongs to, for its label: costcenterid where the table has it, else the root of
     * the template's open_path (0 when there is none).
     *
     * @param \stdClass $record A template row; costcenterid and open_path are read when present.
     * @return int
     */
    private static function template_costcenter(\stdClass $record): int {
        if (isset($record->costcenterid)) {
            return (int) $record->costcenterid;
        }
        $path = trim((string) ($record->open_path ?? ''));
        if ($path === '') {
            return 0;
        }
        $segments = array_values(array_filter(explode('/', $path), static fn($segment) => $segment !== ''));
        return ($segments && ctype_digit($segments[0])) ? (int) $segments[0] : 0;
    }

    /**
     * Get the BizLMS notification templates the current user may see.
     *
     * ADR-031: a cross-tenant caller sees every costcenter's templates; anyone
     * else only their own tenant root's (ni.costcenterid = root), and a caller
     * whose tenant does not resolve sees none. This used to list every
     * costcenter's subjects and body previews to every tenant admin on the
     * manage.php templates tab.
     *
     * @return array [{id, type_name, type_shortname, subject, has_body, costcenterid, active}]
     */
    public static function get_bizlms_templates(): array {
        global $DB;

        $templates = [];

        // Check if BizLMS notification tables exist.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_notification_type') ||
            !$dbman->table_exists('local_notification_info')) {
            return [];
        }

        [$tnsql, $tnargs] = self::template_tenant_filter('ni');
        $columns = self::template_columns();
        // costcenterid is not on every database's table (see template_columns()); the label falls back to open_path.
        $tenantcol = in_array('costcenterid', $columns, true) ? 'ni.costcenterid'
            : (in_array('open_path', $columns, true) ? 'ni.open_path' : '0 AS costcenterid');
        $order = in_array('costcenterid', $columns, true) ? 'nt.name ASC, ni.costcenterid ASC, ni.id ASC'
            : 'nt.name ASC, ni.id ASC';

        try {
            $records = $DB->get_records_sql(
                "SELECT ni.id, nt.name AS type_name, nt.shortname AS type_shortname,
                        ni.subject, ni.body,
                        $tenantcol, ni.active,
                        ni.timemodified
                   FROM {local_notification_info} ni
                   JOIN {local_notification_type} nt ON nt.id = ni.notificationid
                  WHERE $tnsql
               ORDER BY $order",
                $tnargs
            );

            foreach ($records as $r) {
                $costcenter = self::template_costcenter($r);
                $templates[] = [
                    'key'          => 'bizlms/' . $r->type_shortname . '_' . $r->id,
                    'label'        => format_string($r->type_name) .
                                     ($costcenter ? ' (Tenant ' . $costcenter . ')' : ''),
                    'category'     => 'BizLMS',
                    'catkey'       => 'bizlms',
                    'has_override' => false,
                    'override_id'  => 0,
                    'source'       => 'bizlms',
                    'is_bizlms'    => true,
                    'bizlms_id'    => $r->id,
                    'subject'      => format_string($r->subject ?? ''),
                    'body_preview' => shorten_text(strip_tags($r->body ?? ''), 100),
                    'active'       => (bool)$r->active,
                    'timemodified' => $r->timemodified,
                ];
            }
        } catch (\Exception $e) {
            debugging('Legacy bridge: ' . $e->getMessage());
        }

        return $templates;
    }

    /**
     * Get a single BizLMS template by its notification_info ID.
     * Returns full subject and body for preview.
     *
     * ADR-031: same tenant scope as get_bizlms_templates() - another
     * costcenter's template reads as not found.
     *
     * @param int $id local_notification_info.id
     * @return object|null {subject, body, type_name, type_shortname, costcenterid}
     */
    public static function get_bizlms_template(int $id): ?object {
        global $DB;

        [$tnsql, $tnargs] = self::template_tenant_filter('ni');
        $columns = self::template_columns();
        $tenantcol = in_array('costcenterid', $columns, true) ? 'ni.costcenterid'
            : (in_array('open_path', $columns, true) ? 'ni.open_path' : '0 AS costcenterid');

        try {
            $record = $DB->get_record_sql(
                "SELECT ni.id, ni.subject, ni.body, ni.adminbody,
                        nt.name AS type_name, nt.shortname AS type_shortname,
                        $tenantcol, ni.active, ni.completiondays, ni.reminderdays,
                        ni.timecreated, ni.timemodified
                   FROM {local_notification_info} ni
                   JOIN {local_notification_type} nt ON nt.id = ni.notificationid
                  WHERE ni.id = :id AND $tnsql",
                ['id' => $id] + $tnargs
            );
            if (!$record) {
                return null;
            }
            // The documented result carries costcenterid whichever column it came from.
            $record->costcenterid = self::template_costcenter($record);
            unset($record->open_path);
            return $record;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get BizLMS email log statistics.
     *
     * @return object {total, sent, pending}
     */
    public static function get_email_stats(): object {
        global $DB;

        try {
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('local_emaillogs')) {
                return (object)['total' => 0, 'sent' => 0, 'pending' => 0];
            }

            $total = $DB->count_records('local_emaillogs');
            $sent = $DB->count_records('local_emaillogs', ['status' => 1]);
            return (object)[
                'total'   => $total,
                'sent'    => $sent,
                'pending' => $total - $sent,
            ];
        } catch (\Exception $e) {
            return (object)['total' => 0, 'sent' => 0, 'pending' => 0];
        }
    }
}
