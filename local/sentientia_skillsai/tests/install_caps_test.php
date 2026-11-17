<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_skillsai/db/install.php');
require_once($CFG->dirroot . '/local/sentientia_skillsai/db/upgradelib.php');

/**
 * The Sentientia Author role gets :extract and :review on a fresh install, and
 * the 2026111600 back-fill never overrides an administrator.
 *
 * Defect (CI review, 2026-11-16): Moodle runs a plugin's db/install.php BEFORE
 * it registers the plugin's capabilities (lib/upgradelib.php upgrade_plugins():
 * install.php, then upgrade_component_updated() -> update_capabilities()).
 * db/install.php called author_role::ensure(), which grants only registered
 * capabilities, so every fresh install (CI, PHPUnit sites, a new customer, the
 * Stage B cutover) created the role without these two caps. The hook now calls
 * update_capabilities('local_sentientia_skillsai') first, and upgrade step
 * 2026111600 back-fills sites installed with the gap. The gap leaves BOTH caps
 * without a system-context row at once, so the back-fill grants only then; a
 * row for either one (any permission) is an administrator's and stops it.
 *
 * The first test recreates the moment Moodle calls the install hook: the two
 * capabilities are not in {capabilities} and no role holds them. Against the
 * old hook it fails; against the fixed one it passes.
 *
 * Needs local_sentientia_authoring (owner of the role); skipped without it.
 * The plugin version was bumped to 2026111600, so PHPUnit must be
 * re-initialised before this runs.
 *
 * @package    local_sentientia_skillsai
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_local_sentientia_skillsai_install')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_sentientia_skillsai_backfill_author_caps')]
final class install_caps_test extends \advanced_testcase {

    /** The two capabilities this plugin contributes to the Sentientia Author role. */
    private const CAPS = [
        'local/sentientia_skillsai:extract',
        'local/sentientia_skillsai:review',
    ];

    /**
     * The Sentientia Author role id, provisioned the way an install provisions it.
     *
     * @return int
     */
    private function author_roleid(): int {
        if (!class_exists(\local_sentientia_authoring\author_role::class)) {
            $this->markTestSkipped('local_sentientia_authoring (owner of the Sentientia Author role) is not installed.');
        }
        return \local_sentientia_authoring\author_role::ensure();
    }

    /**
     * The role's stored permission for a capability at system context.
     *
     * @param int $roleid
     * @param string $cap
     * @return int|null null when the role has no row for it
     */
    private function permission(int $roleid, string $cap): ?int {
        global $DB;
        $perm = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $roleid, 'capability' => $cap, 'contextid' => \context_system::instance()->id]);
        return $perm === false ? null : (int) $perm;
    }

    /**
     * Fresh-install order: the hook must register the caps itself, then grant them.
     */
    public function test_fresh_install_grants_own_caps_to_the_author_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->author_roleid();

        // The state Moodle leaves when it calls the hook: install.xml applied,
        // db/access.php not loaded yet. Neither capability is registered, so no
        // role holds either.
        $DB->delete_records_list('role_capabilities', 'capability', self::CAPS);
        $DB->delete_records_list('capabilities', 'name', self::CAPS);
        \cache::make('core', 'capabilities')->delete('core_capabilities');
        foreach (self::CAPS as $cap) {
            $this->assertFalse($DB->record_exists('capabilities', ['name' => $cap]));
            $this->assertNull($this->permission($roleid, $cap));
        }

        $this->assertTrue(xmldb_local_sentientia_skillsai_install());

        foreach (self::CAPS as $cap) {
            $this->assertTrue($DB->record_exists('capabilities', ['name' => $cap]),
                "the install hook must register {$cap} before granting it");
            $this->assertSame(CAP_ALLOW, $this->permission($roleid, $cap),
                "a fresh install must give the Sentientia Author role {$cap}");
        }
    }

    /**
     * The back-fill grants only this plugin's two caps, and only to the author role.
     */
    public function test_backfill_grants_only_the_missing_caps_only_to_the_author_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->author_roleid();
        $syscontext = \context_system::instance();

        // The gap a fresh install left: the role holds the other author caps
        // but has no row for these two.
        foreach (self::CAPS as $cap) {
            unassign_capability($cap, $roleid, $syscontext->id);
        }
        // Out of scope, so they must stay as they are: another author cap the
        // role lacks, and another role that lacks one of these caps.
        $othercap = 'local/sentientia_authoring:generate';
        unassign_capability($othercap, $roleid, $syscontext->id);
        $teacherid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        unassign_capability(self::CAPS[0], $teacherid, $syscontext->id);

        $this->assertSame(2, local_sentientia_skillsai_backfill_author_caps());

        foreach (self::CAPS as $cap) {
            $this->assertSame(CAP_ALLOW, $this->permission($roleid, $cap), "{$cap} must be back-filled");
        }
        $this->assertNull($this->permission($roleid, $othercap),
            'only this plugin\'s two caps are back-filled');
        $this->assertNull($this->permission($teacherid, self::CAPS[0]),
            'only the Sentientia Author role is back-filled');

        // Running it again (a replayed upgrade) changes nothing.
        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
    }

    /**
     * An administrator's PROHIBIT or PREVENT on one cap means nothing is granted.
     */
    public function test_backfill_keeps_an_administrators_prohibit_and_prevent(): void {
        $this->resetAfterTest();
        $roleid = $this->author_roleid();
        $syscontext = \context_system::instance();
        [$extract, $review] = self::CAPS;

        // Extraction prohibited for authors on purpose, review left "Not set".
        // Not the gap (the gap leaves both without a row): nothing is granted.
        assign_capability($extract, CAP_PROHIBIT, $roleid, $syscontext->id, true);
        unassign_capability($review, $roleid, $syscontext->id);

        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
        $this->assertSame(CAP_PROHIBIT, $this->permission($roleid, $extract),
            'the back-fill must never overwrite a PROHIBIT');
        $this->assertNull($this->permission($roleid, $review),
            'a PROHIBIT on one cap is configuration, so the other is not granted');

        // A PREVENT is kept the same way, alone or next to the PROHIBIT.
        unassign_capability($extract, $roleid, $syscontext->id);
        assign_capability($review, CAP_PREVENT, $roleid, $syscontext->id, true);
        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $extract),
            'a PREVENT on one cap is configuration, so the other is not granted');
        $this->assertSame(CAP_PREVENT, $this->permission($roleid, $review),
            'the back-fill must never overwrite a PREVENT');

        assign_capability($extract, CAP_PROHIBIT, $roleid, $syscontext->id, true);
        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
        $this->assertSame(CAP_PROHIBIT, $this->permission($roleid, $extract));
        $this->assertSame(CAP_PREVENT, $this->permission($roleid, $review));
    }

    /**
     * One cap cleared to "Not set" while the other still has a row: nothing is granted.
     */
    public function test_backfill_leaves_a_cap_an_administrator_cleared(): void {
        $this->resetAfterTest();
        $roleid = $this->author_roleid();
        $syscontext = \context_system::instance();
        [$extract, $review] = self::CAPS;

        // "Not set" in the role editor deletes the row. With :review still
        // ALLOW, the missing :extract row is an administrator's choice: the
        // gap never leaves one of the two set.
        unassign_capability($extract, $roleid, $syscontext->id);
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $review));

        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $extract),
            'a cap an administrator cleared must stay cleared');
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $review));
    }

    /**
     * If either cap is not registered, the back-fill writes nothing.
     */
    public function test_backfill_does_nothing_when_a_cap_is_not_registered(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = $this->author_roleid();
        $syscontext = \context_system::instance();
        [$extract, $review] = self::CAPS;

        // The gap (no row for either), but :review is not in {capabilities}.
        foreach (self::CAPS as $cap) {
            unassign_capability($cap, $roleid, $syscontext->id);
        }
        $DB->delete_records('role_capabilities', ['capability' => $review]);
        $DB->delete_records('capabilities', ['name' => $review]);
        \cache::make('core', 'capabilities')->delete('core_capabilities');
        $before = $DB->count_records('role_capabilities');

        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $extract));
        $this->assertSame($before, $DB->count_records('role_capabilities'));
    }

    /**
     * Without the author role (authoring never installed) the back-fill writes nothing.
     */
    public function test_backfill_does_nothing_without_the_author_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'sentientiaauthor']);
        if ($roleid) {
            delete_role($roleid);
        }
        $before = $DB->count_records('role_capabilities');

        $this->assertSame(0, local_sentientia_skillsai_backfill_author_caps());
        $this->assertSame($before, $DB->count_records('role_capabilities'));
    }
}
