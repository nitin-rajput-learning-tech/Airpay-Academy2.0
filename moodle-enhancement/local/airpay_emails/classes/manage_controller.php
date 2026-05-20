<?php
/**
 * Management panel controller — prepares data for each tab.
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

class manage_controller {

    /**
     * Get dashboard tab data.
     *
     * @return array context for tab_dashboard.mustache
     */
    public static function get_dashboard_data(): array {
        $templatelist = email_renderer::get_template_list();
        $templatecount = 0;
        foreach ($templatelist as $cat) {
            $templatecount += count($cat['templates']);
        }

        $rulestats = rule_manager::get_stats();

        // Delivery log stats (graceful if table empty).
        try {
            $logstats = delivery_log::get_stats();
        } catch (\Exception $e) {
            $logstats = (object)['total' => 0, 'sent_today' => 0, 'sent_week' => 0, 'failed' => 0, 'suppressed' => 0];
        }

        // BizLMS stats (read-only).
        $bizlmsstats = legacy_bridge::get_email_stats();

        // Phase B0+ — stat_card-compatible tile array. Six tiles cover
        // the essential email-system metrics. Legacy flat fields preserved
        // for the existing template + any consumer that reads them.
        $kpi_tiles = [
            [
                'label' => 'Email Templates',
                'value' => number_format($templatecount),
                // Audit fix M1 (2026-05-15): Moodle 5.1.3+ ships FontAwesome
                // 5/6 which removed the -o (regular outline) variants.
                // 'envelope-o' rendered as a blank box. 'envelope' is the
                // current spelling that resolves in both FA4 and FA5+.
                'icon'  => 'envelope',
                'color' => 'primary',
            ],
            [
                'label' => 'Active Rules',
                'value' => number_format($rulestats->enabled) . ' / ' . number_format($rulestats->total),
                'icon'  => 'bolt',
                'color' => 'accent',
            ],
            [
                'label' => 'Sent Today',
                'value' => number_format($logstats->sent_today),
                'icon'  => 'paper-plane',
                'color' => 'success',
            ],
            [
                'label' => 'Sent This Week',
                'value' => number_format($logstats->sent_week),
                // Audit fix M1 (2026-05-15): FA4 'calendar-check-o' renamed
                // to 'calendar-check' in FA5/6.
                'icon'  => 'calendar-check',
                'color' => 'info',
            ],
            [
                'label' => 'Failed',
                'value' => number_format($logstats->failed),
                'icon'  => 'exclamation-triangle',
                // Failed flips to danger when > 0 — a single failure is
                // still actionable (probably an SMTP misconfig).
                'color' => $logstats->failed > 0 ? 'danger' : 'primary',
            ],
            [
                'label' => 'Suppressed',
                'value' => number_format($logstats->suppressed),
                'icon'  => 'ban',
                'color' => 'warning',
            ],
        ];

        return [
            'template_count'  => $templatecount,
            'rule_total'      => $rulestats->total,
            'rule_enabled'    => $rulestats->enabled,
            'rule_disabled'   => $rulestats->disabled,
            'log_total'       => $logstats->total,
            'log_sent_today'  => $logstats->sent_today,
            'log_sent_week'   => $logstats->sent_week,
            'log_failed'      => $logstats->failed,
            'log_suppressed'  => $logstats->suppressed,
            'kpi_tiles'       => $kpi_tiles,
            'has_kpi_tiles'   => !empty($kpi_tiles),
            'bizlms_total'    => $bizlmsstats->total,
            'bizlms_sent'     => $bizlmsstats->sent,
            'bizlms_pending'  => $bizlmsstats->pending,
            'noemailever'     => !empty($GLOBALS['CFG']->noemailever),
        ];
    }

    /**
     * Get templates tab data.
     *
     * @param int $tenantid
     * @return array context for tab_templates.mustache
     */
    public static function get_templates_data(int $tenantid = 0): array {
        // Airpay templates with override status.
        $airpaytemplates = template_manager::get_templates_with_status($tenantid);

        // BizLMS templates (read-only).
        $bizlmstemplates = legacy_bridge::get_bizlms_templates();

        // Merge into categorized structure.
        $categories = [];
        $catmap = [];
        foreach ($airpaytemplates as $tpl) {
            $catkey = $tpl['catkey'];
            if (!isset($catmap[$catkey])) {
                $catmap[$catkey] = count($categories);
                $categories[] = ['category' => $tpl['category'], 'catkey' => $catkey, 'templates' => []];
            }
            $categories[$catmap[$catkey]]['templates'][] = $tpl;
        }

        // Add BizLMS as separate category.
        if (!empty($bizlmstemplates)) {
            $categories[] = [
                'category'  => 'BizLMS (Legacy)',
                'catkey'    => 'bizlms',
                'templates' => $bizlmstemplates,
            ];
        }

        return [
            'categories'     => $categories,
            'tenant_id'      => $tenantid,
            'total_airpay'   => count($airpaytemplates),
            'total_bizlms'   => count($bizlmstemplates),
            'total_overrides' => count(array_filter($airpaytemplates, fn($t) => $t['has_override'])),
        ];
    }

    /**
     * Get rules tab data.
     *
     * @param int $tenantid
     * @return array context for tab_rules.mustache
     */
    public static function get_rules_data(int $tenantid = 0): array {
        $rules = rule_manager::get_rules($tenantid);

        // Enrich rules with readable labels.
        $enriched = [];
        foreach ($rules as $rule) {
            $enriched[] = [
                'id'            => $rule->id,
                'rule_name'     => format_string($rule->rule_name),
                'rule_type'     => $rule->rule_type,
                'channel'       => $rule->channel,
                'audience'      => ucfirst($rule->audience),
                'trigger_days'  => $rule->trigger_days,
                'template_key'  => $rule->template_key,
                'tenant_id'     => $rule->tenant_id,
                'enabled'       => (bool)$rule->enabled,
                'priority'      => $rule->priority,
                'is_global'     => ($rule->tenant_id == 0),
                'timemodified'  => userdate($rule->timemodified),
            ];
        }

        return ['rules' => $enriched, 'rule_count' => count($enriched)];
    }

    /**
     * Get logs tab data.
     *
     * @param array $filters
     * @param int $page
     * @param int $perpage
     * @return array context for tab_logs.mustache
     */
    public static function get_logs_data(array $filters = [], int $page = 0, int $perpage = 50): array {
        $result = delivery_log::get_logs($filters, $page, $perpage);

        $logs = [];
        foreach ($result->records as $r) {
            $logs[] = [
                'id'            => $r->id,
                'date'          => userdate($r->timecreated, '%d %b %Y %I:%M %p'),
                'user_name'     => format_string(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
                'user_email'    => s($r->email ?? ''),
                'channel'       => $r->channel,
                'subject'       => format_string($r->subject),
                'template_key'  => $r->template_key,
                'status'        => $r->status,
                'status_sent'   => ($r->status === 'sent'),
                'status_failed' => ($r->status === 'failed'),
                'status_suppressed' => ($r->status === 'suppressed'),
                'error'         => s($r->error_message ?? ''),
            ];
        }

        return [
            'logs'     => $logs,
            'total'    => $result->total,
            'page'     => $page,
            'perpage'  => $perpage,
            'haspages' => ($result->total > $perpage),
            'pages'    => self::build_pagination($result->total, $page, $perpage),
        ];
    }

    /**
     * Build simple pagination array.
     */
    private static function build_pagination(int $total, int $current, int $perpage): array {
        $totalpages = ceil($total / $perpage);
        $pages = [];
        for ($i = 0; $i < $totalpages && $i < 10; $i++) {
            $pages[] = ['page' => $i, 'label' => $i + 1, 'active' => ($i === $current)];
        }
        return $pages;
    }
}
