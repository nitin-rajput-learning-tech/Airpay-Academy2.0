<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * db/upgrade.php runs its steps in ascending version order (2026-09-25 review).
 *
 * The 2026052104 step (push_log table) sat after 2026052105 and 2026052106.
 * A site at 2026052103 or older ran those two savepoints first and then called
 * upgrade_plugin_savepoint(2026052104) with the stored version already at
 * 2026052106, which throws downgrade_exception and aborts the whole CLI
 * upgrade, leaving every plugin after this one un-upgraded - including the
 * ADR-031 capability revokes that ship in the same release.
 *
 * @package    local_sentientia_pwa
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_local_sentientia_pwa_upgrade
 * @group      tenant_isolation
 */
final class upgrade_order_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/local/sentientia_pwa/db/upgrade.php');
    }

    /** @return int[] the step versions, in the order db/upgrade.php runs them */
    private function step_versions(): array {
        global $CFG;
        $src = file_get_contents($CFG->dirroot . '/local/sentientia_pwa/db/upgrade.php');
        preg_match_all('/\$oldversion\s*<\s*(\d{10})/', $src, $m);
        return array_map('intval', $m[1]);
    }

    public function test_every_step_comes_after_the_one_before_it(): void {
        $versions = $this->step_versions();
        $this->assertNotEmpty($versions);
        $sorted = $versions;
        sort($sorted);
        $this->assertSame($sorted, $versions, 'Upgrade steps must be in ascending version order.');
        $this->assertSame(count($versions), count(array_unique($versions)), 'No step appears twice.');
    }

    public function test_a_site_at_2026052103_upgrades_to_the_end_without_a_downgrade_exception(): void {
        global $DB;
        // The push_log table already exists here (install.xml), so the
        // 2026052104 step's create is skipped, exactly as on a site that
        // got the table some other way; its savepoint is what used to throw.
        $this->assertTrue($DB->get_manager()->table_exists('local_sentientia_push_log'));
        set_config('version', 2026052103, 'local_sentientia_pwa');

        $this->assertTrue(xmldb_local_sentientia_pwa_upgrade(2026052103));

        $this->assertEquals(max($this->step_versions()), get_config('local_sentientia_pwa', 'version'),
            'Every savepoint through the last step must have been reached.');
    }
}
