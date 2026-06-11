<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_sentientia_org.
 *
 * WF-016: registers class aliases that map the retired BizLMS
 * `local_costcenter` namespace onto its Sentientia successors, so the
 * kept vendor blocks (learnerscript, userdashboard, reportdashboard,
 * achievements, my_event_calendar, masterinfo, quick_navigation) keep
 * working without per-file patches:
 *
 *   \local_costcenter\lib\accesslib  →  \local_sentientia_org\accesslib
 *       (accesslib was forked into this plugin as a namespace-change-only
 *        copy — get_module_context / get_costcenter_info /
 *        get_costcenter_path_field_concatsql / role helpers all present)
 *   \local_costcenter\lib            →  \local_sentientia_org\compat\bizlms_lib
 *       (get_userdate port; see that class's doc block)
 *
 * Registered on \core\hook\after_config so the aliases exist on every
 * web, CLI, cron and WS request before any vendor code can run.
 * class_exists(..., false) guards make this idempotent and a no-op if a
 * real local_costcenter ever reappears (its autoloaded classes would
 * win the class_exists check before the alias registers).
 *
 * NOT aliased (recorded follow-up in WORKFLOW-TEST-MATRIX WF-016): the
 * plain function local_costcenter_get_costcenter_path() used by 2
 * learnerscript AJAX filter paths — it depends on retired BizLMS
 * session machinery ($USER->useraccess) with no Sentientia equivalent.
 *
 * @package local_sentientia_org
 */
class hook_callbacks {

    /**
     * Register BizLMS compatibility class aliases (WF-016).
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        if (!class_exists('local_costcenter\lib\accesslib', false)) {
            class_alias(\local_sentientia_org\accesslib::class,
                'local_costcenter\lib\accesslib');
        }
        if (!class_exists('local_costcenter\lib', false)) {
            class_alias(\local_sentientia_org\compat\bizlms_lib::class,
                'local_costcenter\lib');
        }
    }
}
