<?php
/**
 * Management panel controller — prepares data for each tab.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class manage_controller {

    /**
     * Get dashboard tab data.
     *
     * @param int $tenantid the page's resolved tenant (tenant_scope::resolve); 0 = all, cross-tenant only
     * @return array context for tab_dashboard.mustache
     */
    public static function get_dashboard_data(int $tenantid = 0): array {
        $templatelist = email_renderer::get_template_list();
        $templatecount = 0;
        foreach ($templatelist as $cat) {
            $templatecount += count($cat['templates']);
        }

        // ADR-031: the rule and delivery counts are the tenant's own.
        $rulestats = rule_manager::get_stats($tenantid);

        // Delivery log stats (graceful if table empty).
        try {
            $logstats = delivery_log::get_stats($tenantid);
        } catch (\Exception $e) {
            $logstats = (object)['total' => 0, 'sent_today' => 0, 'sent_week' => 0, 'failed' => 0, 'suppressed' => 0];
        }

        // BizLMS stats (read-only). local_emaillogs has no tenant column, so
        // its totals are site-wide: only a cross-tenant caller sees them
        // (2026-09-29 visual pass: a ZEEA tenant admin saw Airpay's queue totals).
        //
        // ADR-032: once the BizLMS history is imported AND shown in the log (imported_history flag ON), the same
        // emails are in the tiles above, so the separate BizLMS queue card is hidden rather than counting them
        // twice. While the flag is OFF the imported rows are not in the tiles and the card stays.
        $importedshown = imported_history::history_enabled() && imported_history::exist();
        $showlegacy = \local_sentientia_platform\tenant::is_cross_tenant() && !$importedshown;
        $bizlmsstats = $showlegacy ? legacy_bridge::get_email_stats()
            : (object) ['total' => 0, 'sent' => 0, 'pending' => 0];

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
            'show_legacy'     => $showlegacy,
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

        // ADR-031: a scoped caller may see a global rule (it fires for their
        // tenant too) but not toggle, edit or delete it - the server refuses
        // that (tenant_scope::require_can_modify_rule), so do not offer it.
        $crosstenant = \local_sentientia_platform\tenant::is_cross_tenant();
        $ownroot = \local_sentientia_platform\tenant::root_for_current_user();

        // Enrich rules with readable labels.
        $enriched = [];
        foreach ($rules as $rule) {
            $ruletenant = (int) $rule->tenant_id;
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
                'can_modify'    => $crosstenant || ($ruletenant > 0 && $ruletenant === $ownroot),
                'timemodified'  => userdate($rule->timemodified),
            ];
        }

        return ['rules' => $enriched, 'rule_count' => count($enriched)];
    }

    /**
     * Options for the page's tenant selector.
     *
     * ADR-031: a scoped caller is pinned to their own tenant whatever ?tenant=
     * says (tenant_scope::resolve), so offering "All Tenants" or another
     * tenant only led to a page that silently showed their own again.
     *
     * Labels come from the tenant registry (see tenant_name()) and the
     * language pack, never from a hardcoded customer name.
     *
     * @param int $tenantid the page's resolved tenant (tenant_scope::resolve)
     * @return array [{id, name, selected}]
     */
    public static function tenant_selector_options(int $tenantid): array {
        return self::scope_options(
            get_string('tenant_all', 'local_sentientia_emails'),
            fn(int $root): string => self::tenant_name($root),
            $tenantid);
    }

    /**
     * Options for the rule form's "Tenant Scope" select.
     *
     * ADR-031: only a cross-tenant caller may create a global rule or one for
     * another tenant (the save is refused otherwise), so a scoped caller is
     * offered their own tenant only. The option matching the rule being
     * edited - or, for a new rule, the page's tenant - is pre-selected: the
     * form used to pre-select nothing, so re-saving a tenant rule quietly
     * turned it into "All Tenants (Global)", firing for every tenant.
     *
     * A cross-tenant caller on manage.php?tenant=N gets tenant N pre-selected
     * for a new rule (it used to be Global for everyone).
     *
     * D-12 review (2026-11-20): a cross-tenant caller is offered Global and the
     * registered (active) tenant roots only. A rule whose tenant_id is not among
     * them (a suspended or retired tenant, a legacy id) had no option selected,
     * so the browser posted the first one, Global, and an unchanged re-save
     * turned it into a global rule that sends to every tenant's users. Its
     * tenant is now added as a "saved value" option, selected, as
     * rule_form_context() does for the other selects. This is for the edited
     * rule only (not a new rule, not tenant_selector_options()); the save still
     * goes through tenant_scope::require_can_write_tenant().
     *
     * @param int $pagetenant the page's resolved tenant (0 = all, cross-tenant only)
     * @param int|null $ruletenant tenant_id of the rule being edited; null for a new rule
     * @return array [{id, name, selected}]
     */
    public static function rule_scope_options(int $pagetenant, ?int $ruletenant = null): array {
        $label = fn(int $root): string => get_string('rule_scope_tenant', 'local_sentientia_emails',
            self::tenant_name($root));
        $options = self::scope_options(
            get_string('rule_scope_global', 'local_sentientia_emails'),
            $label,
            $ruletenant ?? $pagetenant);
        if ($ruletenant !== null && $ruletenant > 0 && \local_sentientia_platform\tenant::is_cross_tenant()
                && !in_array($ruletenant, array_column($options, 'id'), true)) {
            $options[] = [
                'id'       => $ruletenant,
                'name'     => get_string('rule_option_saved', 'local_sentientia_emails', $label($ruletenant)),
                'selected' => true,
            ];
        }
        return $options;
    }

    /**
     * Options for the rule form's "Email Template" select: the template registry, grouped by category.
     *
     * G1 (2026-11-19): the same registry the save accepts (rule_manager::rule_template_key()), and the
     * rule's own template pre-selected. The form used to pre-select nothing, so re-saving a rule to
     * change, say, its priority quietly stored "None (plain text)" over its template. A stored key in
     * the legacy spelling ("_" for "/") selects its registry key; a stored key that is not in the
     * registry selects nothing (the save would refuse it).
     *
     * @param string $storedkey template_key of the rule being edited; '' for a new rule
     * @return array [{category, templates: [{key, label, selected}]}]
     */
    public static function rule_template_options(string $storedkey = ''): array {
        $selected = rule_manager::rule_template_key($storedkey) ?? '';
        $options = [];
        foreach (email_renderer::get_template_list() as $cat) {
            $templates = [];
            foreach ($cat['templates'] as $tpl) {
                $templates[] = [
                    'key'      => $tpl['key'],
                    'label'    => $tpl['label'],
                    'selected' => ($selected !== '' && $tpl['key'] === $selected),
                ];
            }
            $options[] = ['category' => $cat['category'], 'templates' => $templates];
        }
        return $options;
    }

    /**
     * The rule form's values and option lists (D-12, 2026-11-20).
     *
     * Editing a saved rule used to pre-select nothing in Rule Type, Channel and Audience and to show a NULL
     * Trigger Days as 0, so a re-save stored the first option of each and 0. Now every stored value is loaded:
     * each select has the rule's value selected (a stored value the list does not offer is added as a "saved
     * value" option, so it survives a re-save), Trigger Days and the cadence show what is stored (blank for
     * NULL), and the trigger event and conditions, which the form does not edit, are shown read-only.
     * rule_manager::rule_from_form() accepts exactly these values back.
     *
     * The rule name is the stored text, not format_string()'d: the template escapes it once, and a name with
     * "&" used to come back as "&amp;" and grow on every save.
     *
     * @param \stdClass|null $editrule the rule being edited; null for a new rule
     * @return array {editrule|null, has_editrule, rule_type_options, channel_options, audience_options};
     *               each option list is [{value, label, selected}]
     */
    public static function rule_form_context(?\stdClass $editrule = null): array {
        $editcontext = null;
        if ($editrule !== null) {
            $conditions = trim((string) ($editrule->conditions_json ?? ''));
            $editcontext = [
                'id'                      => (int) $editrule->id,
                'rule_name'               => (string) $editrule->rule_name,
                'rule_type'               => (string) $editrule->rule_type,
                'trigger_event'           => (string) ($editrule->trigger_event ?? ''),
                'has_trigger_event'       => trim((string) ($editrule->trigger_event ?? '')) !== '',
                'trigger_days'            => $editrule->trigger_days === null ? '' : (string) (int) $editrule->trigger_days,
                'channel'                 => (string) $editrule->channel,
                'audience'                => (string) $editrule->audience,
                'template_key'            => (string) ($editrule->template_key ?? ''),
                'tenant_id'               => (int) $editrule->tenant_id,
                'enabled'                 => (bool) $editrule->enabled,
                'priority'                => (int) $editrule->priority,
                'conditions_json'         => $conditions,
                'has_conditions'          => $conditions !== '',
                'cadence_days_json'       => (string) ($editrule->cadence_days_json ?? ''),
                'max_reminders_per_user'  => (int) ($editrule->max_reminders_per_user ?? 0),
                'auto_stop_on_completion' => (bool) ($editrule->auto_stop_on_completion ?? 1),
            ];
        }

        return [
            'editrule'          => $editcontext,
            'has_editrule'      => $editcontext !== null,
            'rule_type_options' => self::rule_choice_options('rule_type', $editrule, null),
            // A new rule starts as the form always did: Email + Popup, to learners.
            'channel_options'   => self::rule_choice_options('channel', $editrule, 'email,popup'),
            'audience_options'  => self::rule_choice_options('audience', $editrule, 'learner'),
        ];
    }

    /**
     * One select of the rule form: the values rule_manager::choices() accepts, labelled, with one selected.
     *
     * @param string $field 'rule_type', 'channel' or 'audience'
     * @param \stdClass|null $editrule the rule being edited; null for a new rule
     * @param string|null $newdefault the value selected for a new rule (null = none, the placeholder shows)
     * @return array [{value, label, selected}]
     */
    private static function rule_choice_options(string $field, ?\stdClass $editrule, ?string $newdefault): array {
        $selected = $editrule !== null ? trim((string) ($editrule->$field ?? '')) : $newdefault;
        $prefix = ['rule_type' => 'rule_type_', 'channel' => 'rule_channel_', 'audience' => 'rule_audience_'][$field];
        $sm = get_string_manager();
        $options = [];
        foreach (rule_manager::choices($field, $editrule) as $value) {
            $stringid = $prefix . str_replace(',', '_', $value);
            $label = preg_match('/^[a-z_,]+$/', $value) && $sm->string_exists($stringid, 'local_sentientia_emails')
                ? get_string($stringid, 'local_sentientia_emails')
                : get_string('rule_option_saved', 'local_sentientia_emails', $value);
            $options[] = ['value' => $value, 'label' => $label, 'selected' => ($selected !== null && $value === $selected)];
        }
        return $options;
    }

    /**
     * The display name of a tenant root, from the org/tenant registry.
     *
     * White-label: the name is the tenant's own org record (path '/N' in
     * local_sentientia_org, or the legacy costcenter it falls back to), so a
     * customer sees its own tenant names. It used to be hardcoded as
     * "Airpay" / "Airpay Only" for every deployment. When the registry has no
     * name for the root, a neutral "Tenant N" is used.
     *
     * @param int $root tenant root id
     * @return string plain text (Mustache escapes it)
     */
    public static function tenant_name(int $root): string {
        $name = '';
        if ($root > 0 && class_exists('\local_sentientia_org\org_manager')) {
            $name = trim((string) \local_sentientia_org\org_manager::get_name_by_path('/' . $root));
        }
        if ($name === '') {
            return get_string('tenant_n', 'local_sentientia_emails', $root);
        }
        return format_string($name, true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * The tenant roots a cross-tenant caller may choose from: the tenant
     * registry's (ADR-021), which falls back to the legacy allow-list.
     *
     * @return int[]
     */
    private static function tenant_roots(): array {
        if (class_exists('\local_sentientia_core\tenant_registry')) {
            return \local_sentientia_core\tenant_registry::valid_roots();
        }
        return \local_sentientia_platform\tenant::VALID_TENANTS;
    }

    /**
     * Build a tenant option list the current user may choose from, and mark
     * the selected one. Cross-tenant callers get the "all/global" option and
     * every registered tenant; anyone else gets their own tenant root only
     * (nothing when it does not resolve), selected whatever was asked for.
     *
     * @param string $allname label of the id-0 option (all tenants / global)
     * @param callable $label fn(int $root): string, label of a tenant option
     * @param int $selected
     * @return array [{id, name, selected}]
     */
    private static function scope_options(string $allname, callable $label, int $selected): array {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            $options = [['id' => 0, 'name' => $allname]];
            foreach (self::tenant_roots() as $root) {
                $options[] = ['id' => (int) $root, 'name' => $label((int) $root)];
            }
        } else {
            $own = \local_sentientia_platform\tenant::root_for_current_user();
            $options = $own > 0 ? [['id' => $own, 'name' => $label($own)]] : [];
            $selected = $own;
        }
        foreach ($options as &$option) {
            $option['selected'] = ($option['id'] === $selected);
        }
        unset($option);
        return $options;
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

        // ADR-032: the imported BizLMS columns and the View link appear only while their flags are ON. The
        // records below only contain imported rows when the first one is.
        $imported = imported_history::history_enabled();
        $bodyenabled = imported_history::body_enabled();

        $logs = [];
        foreach ($result->records as $r) {
            $isimported = $imported && !empty($r->legacy_source);
            $templatekey = (string) ($r->template_key ?? '');
            $sendername = trim(($r->sender_firstname ?? '') . ' ' . ($r->sender_lastname ?? ''));
            $status = (string) $r->status;
            $logs[] = [
                'id'            => $r->id,
                'date'          => userdate($r->timecreated, '%d %b %Y %I:%M %p'),
                'user_name'     => format_string(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
                'user_email'    => s($r->email ?? ''),
                'channel'       => $r->channel,
                // The template escapes {{ }} once; a value that is escaped here as well shows "&amp;" for "&".
                'subject'       => format_string($r->subject, true, ['escape' => false]),
                'template_key'  => $r->template_key,
                // The native template key; for an imported row without one, the BizLMS notification type.
                'template_label' => $templatekey !== '' ? $templatekey : ($isimported ? (string) ($r->legacy_type ?? '') : ''),
                'status'        => $status,
                'status_sent'   => ($status === 'sent'),
                'status_failed' => ($status === 'failed'),
                'status_suppressed' => ($status === 'suppressed'),
                // Every other status gets a badge too (not_sent, suppressed_completion, bounced, ...).
                'status_other'  => !in_array($status, ['sent', 'failed', 'suppressed'], true),
                'status_notsent' => ($status === 'not_sent'),
                'error'         => (string) ($r->error_message ?? ''),
                'imported'      => $isimported,
                'sender_name'   => $isimported && $sendername !== ''
                    ? format_string($sendername, true, ['escape' => false]) : '',
                'sent_on'       => $isimported && !empty($r->timesent)
                    ? userdate((int) $r->timesent, '%d %b %Y %I:%M %p') : '',
                'can_view'      => $isimported && $bodyenabled,
                'has_body'      => !empty($r->has_body),
                'view_url'      => ($isimported && $bodyenabled)
                    ? (new \moodle_url('/local/sentientia_emails/email_detail.php', ['id' => $r->id]))->out(false) : '',
            ];
        }

        return [
            'logs'     => $logs,
            'total'    => $result->total,
            'page'     => $page,
            'perpage'  => $perpage,
            'haspages' => ($result->total > $perpage),
            'pages'    => self::build_pagination($result->total, $page, $perpage),
            'show_imported' => $imported,
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
