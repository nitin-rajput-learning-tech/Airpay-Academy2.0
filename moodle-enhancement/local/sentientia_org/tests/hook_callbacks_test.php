<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org;

defined('MOODLE_INTERNAL') || die();

/**
 * WF-016: the retired BizLMS local_costcenter classes resolve to their Sentientia successors after config.
 *
 * UAT 2026-11-18: the site admin's course page failed with Class "local_costcenter\lib\accesslib" not found.
 * db/hooks.php, hook_callbacks.php and compat/bizlms_lib.php existed only in the top-level copy of this plugin,
 * not in the moodle-enhancement copy that UAT (and CI) run. These tests pin the hook registration and the alias
 * contract that the kept vendor blocks rely on, so a copy without the hook fails here.
 *
 * @package    local_sentientia_org
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_org\hook_callbacks
 * @covers     \local_sentientia_org\compat\bizlms_lib
 */
final class hook_callbacks_test extends \advanced_testcase {

    public function test_after_config_callback_is_registered(): void {
        $callbacks = \core\di::get(\core\hook\manager::class)
            ->get_callbacks_for_hook(\core\hook\after_config::class);
        $ours = array_values(array_filter($callbacks,
            fn(array $c): bool => $c['component'] === 'local_sentientia_org'));

        $this->assertCount(1, $ours, 'db/hooks.php must register exactly one after_config callback');
        $this->assertSame('local_sentientia_org\hook_callbacks::after_config', $ours[0]['callback']);
        $this->assertFalse($ours[0]['disabled']);
    }

    public function test_aliases_resolve_to_the_sentientia_classes(): void {
        // setup.php dispatched after_config before any test ran, so both names already resolve.
        $this->assertSame(accesslib::class, (new \ReflectionClass('local_costcenter\lib\accesslib'))->getName());
        $this->assertSame(compat\bizlms_lib::class, (new \ReflectionClass('local_costcenter\lib'))->getName());
    }

    public function test_after_config_is_idempotent(): void {
        // A second dispatch (or a direct call) must not try to re-declare the aliases.
        hook_callbacks::after_config(new \core\hook\after_config());
        hook_callbacks::after_config(new \core\hook\after_config());
        $this->assertSame(accesslib::class, (new \ReflectionClass('local_costcenter\lib\accesslib'))->getName());
        $this->assertSame(compat\bizlms_lib::class, (new \ReflectionClass('local_costcenter\lib'))->getName());
    }

    public function test_the_learnerscript_menu_call_shape_works(): void {
        // blocks/learnerscript/lib.php block_learnerscript_leftmenunode() and block_learnerscript_masterinfo()
        // both do exactly this: instantiate the old class, then call get_module_context() statically.
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $this->assertInstanceOf(\context_system::class, $context);
        $this->assertSame((int) \context_system::instance()->id, (int) $context->id);
    }

    public function test_get_userdate_translates_a_date_style_format(): void {
        // 172800 = 1970-01-03 00:00 UTC. 'd-m-Y' becomes '%d-%m-%Y' for core userdate().
        $this->assertSame('03-01-1970', \local_costcenter\lib::get_userdate('d-m-Y', 172800, 'UTC'));
    }

    public function test_learnerscript_left_menu_node_renders_for_the_site_admin(): void {
        global $CFG;

        $dir = \core_component::get_plugin_directory('block', 'learnerscript');
        if (!$dir || !file_exists($dir . '/lib.php')
                || !get_capability_info('block/learnerscript:viewreports')) {
            $this->markTestSkipped('block_learnerscript is not installed on this site');
        }
        require_once($dir . '/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        // The exact call that failed on UAT on 2026-11-18.
        $nodes = \block_learnerscript_leftmenunode();
        $this->assertArrayHasKey('24', $nodes);
        $this->assertStringContainsString('id_leftmenu_learnerscript', $nodes['24']);
        $this->assertStringContainsString($CFG->wwwroot . '/blocks/reportdashboard/dashboard.php', $nodes['24']);
    }
}
