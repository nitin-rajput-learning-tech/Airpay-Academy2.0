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
 * The class_exists() guards make this idempotent (a second call is a
 * no-op) and let a real local_costcenter win if it ever reappears. They
 * DO autoload: after_config runs before anything could have loaded a
 * real local_costcenter class, so a class_exists(..., false) check would
 * always be false and the alias would shadow the real class. With no
 * local_costcenter code on the tree (every Sentientia tree today) the
 * autoload is one class-map miss.
 *
 * The callback only registers aliases: no DB, config, session or output,
 * so it is safe on every request type, during install and upgrade, and
 * on a site without blocks/learnerscript (an unused alias costs only the
 * loading of the two target class files).
 *
 * Known contract gaps of the fork (recorded 2026-11-18, not changed
 * here): get_module_context() always returns the system
 * context (BizLMS returned the role-switch category context for
 * non-admins); get_costcenter_info($id, 'path') ignores the second
 * argument and returns the record; get_costcenter_path_field_concatsql()
 * with an explicit path returns named placeholders without their params,
 * and with no path it returns '' (no tenant filter) for EVERY non-admin,
 * because nothing in Sentientia sets the BizLMS role-switch contextinfo
 * it reads: the learnerscript / trainerdashboard queries that use it are
 * not tenant-scoped for a non-admin holder of
 * block/learnerscript:viewreports (decision open, see the state card).
 * The menu callers block_learnerscript_leftmenunode() and
 * block_learnerscript_masterinfo() use only get_module_context().
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
        if (!class_exists('local_costcenter\lib\accesslib')) {
            class_alias(\local_sentientia_org\accesslib::class,
                'local_costcenter\lib\accesslib');
        }
        if (!class_exists('local_costcenter\lib')) {
            class_alias(\local_sentientia_org\compat\bizlms_lib::class,
                'local_costcenter\lib');
        }
    }
}
