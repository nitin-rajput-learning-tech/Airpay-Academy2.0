<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_authoring/db/install.php');
require_once($CFG->dirroot . '/local/sentientia_authoring/db/upgradelib.php');

/**
 * The Sentientia Author role gets this plugin's :generate, :review and
 * :managetemplates from this plugin's own install hook, and the 2026111600
 * back-fill never overrides an administrator.
 *
 * Defect (CI review, 2026-11-16): Moodle runs a plugin's db/install.php BEFORE
 * it registers the plugin's capabilities (lib/upgradelib.php upgrade_plugins():
 * install.php, then upgrade_component_updated() -> update_capabilities()).
 * db/install.php called author_role::ensure(), which grants only registered
 * capabilities, so the role was created without these three caps. They arrived
 * only when local_sentientia_skillsai installed later in the same run; a site
 * without skillsai, or with skillsai installed first, kept the gap. The hook
 * now calls update_capabilities('local_sentientia_authoring') first, and
 * upgrade step 2026111600 back-fills sites installed with the gap. The gap
 * leaves ALL THREE caps without a system-context row at once, so the back-fill
 * grants only then; a row for any one of them (any permission) is an
 * administrator's and stops it.
 *
 * The first test reads the role exactly as the real install of every plugin
 * left it on the phpu_ site, repairing nothing: the end-to-end check (in CI
 * it runs only in the advisory full suite, not a blocking group). The
 * second recreates the moment Moodle calls this plugin's install hook: the
 * three capabilities are not in {capabilities} and no role holds them. Against
 * the old hooks both fail; against the fixed ones both pass.
 *
 * The plugin version was bumped to 2026111600, so PHPUnit must be
 * re-initialised before this runs.
 *
 * @package    local_sentientia_authoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_local_sentientia_authoring_install')]
#[\PHPUnit\Framework\Attributes\CoversFunction('local_sentientia_authoring_backfill_author_caps')]
final class install_caps_test extends \advanced_testcase {

    /** The three capabilities this plugin contributes to the Sentientia Author role. */
    private const CAPS = [
        'local/sentientia_authoring:generate',
        'local/sentientia_authoring:review',
        'local/sentientia_authoring:managetemplates',
    ];

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
     * The phpu_ site as its real install left it: the role holds every registered author cap.
     *
     * PHPUnit initialisation builds the phpu_ site with a real install of every
     * plugin in the code tree (install.xml, db/install.php, then Moodle's own
     * update_capabilities()), and every test starts from that snapshot. This
     * test only reads it: no author_role::ensure(), no back-fill, no
     * resetAfterTest(), so PHPUnit would also report any write it made as an
     * unexpected database modification.
     *
     * On a site initialised with the install hooks committed before 2026111600
     * (HEAD on 2026-11-16) this fails. Moodle installs local plugins
     * alphabetically and runs each db/install.php BEFORE it registers that
     * plugin's capabilities. local_sentientia_aiquiz (no install hook) comes
     * first, so its caps are registered. authoring's hook then ran ensure()
     * with its own three caps unregistered, and skillsai's hook ran ensure()
     * with its own two unregistered (it did fill in authoring's three, which
     * were registered by then). Nothing runs ensure() after that, so the role
     * ended up without local/sentientia_skillsai:extract and :review, and this
     * test names those two. On a site without skillsai, authoring's three
     * are missing instead. With the fixed hooks, each registers its own
     * capabilities before ensure(), and nothing is missing.
     */
    public function test_real_fresh_install_left_the_author_role_with_every_registered_cap(): void {
        global $DB;

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'sentientiaauthor']);
        $this->assertNotSame(0, $roleid,
            'the real install of local_sentientia_authoring must create the Sentientia Author role');

        $missing = [];
        $checked = 0;
        foreach (author_role::caps() as $cap) {
            if (!$DB->record_exists('capabilities', ['name' => $cap])) {
                // The owning plugin is not installed on this site.
                continue;
            }
            $checked++;
            $permission = $this->permission($roleid, $cap);
            if ($permission !== CAP_ALLOW) {
                $missing[] = $cap . ' => ' . ($permission === null ? 'no row' : (string) $permission);
            }
        }

        // This plugin is installed, so at least its own three caps were checked.
        $this->assertGreaterThanOrEqual(count(self::CAPS), $checked);
        $this->assertSame([], $missing,
            'a fresh install must leave the Sentientia Author role with CAP_ALLOW at system context '
            . 'for every registered author cap');
    }

    /**
     * Fresh-install order: the hook must register the caps itself, then grant them.
     */
    public function test_fresh_install_grants_own_caps_to_the_author_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = author_role::ensure();

        // The state Moodle leaves when it calls the hook: install.xml applied,
        // db/access.php not loaded yet. None of the three is registered, so no
        // role holds any of them.
        $DB->delete_records_list('role_capabilities', 'capability', self::CAPS);
        $DB->delete_records_list('capabilities', 'name', self::CAPS);
        \cache::make('core', 'capabilities')->delete('core_capabilities');
        foreach (self::CAPS as $cap) {
            $this->assertFalse($DB->record_exists('capabilities', ['name' => $cap]));
            $this->assertNull($this->permission($roleid, $cap));
        }

        $this->assertTrue(xmldb_local_sentientia_authoring_install());

        foreach (self::CAPS as $cap) {
            $this->assertTrue($DB->record_exists('capabilities', ['name' => $cap]),
                "the install hook must register {$cap} before granting it");
            $this->assertSame(CAP_ALLOW, $this->permission($roleid, $cap),
                "a fresh install must give the Sentientia Author role {$cap}");
        }
    }

    /**
     * The back-fill grants only this plugin's three caps, and only to the author role.
     */
    public function test_backfill_grants_only_the_missing_caps_only_to_the_author_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = author_role::ensure();
        $syscontext = \context_system::instance();

        // The gap a fresh install without skillsai left: no row for these three.
        foreach (self::CAPS as $cap) {
            unassign_capability($cap, $roleid, $syscontext->id);
        }
        // Out of scope, so it must stay as it is: another role that lacks one
        // of these caps.
        $teacherid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        unassign_capability(self::CAPS[0], $teacherid, $syscontext->id);
        // And, where skillsai is installed, another author cap the role lacks.
        $othercap = 'local/sentientia_skillsai:extract';
        $hasothercap = $DB->record_exists('capabilities', ['name' => $othercap]);
        if ($hasothercap) {
            unassign_capability($othercap, $roleid, $syscontext->id);
        }

        $this->assertSame(3, local_sentientia_authoring_backfill_author_caps());

        foreach (self::CAPS as $cap) {
            $this->assertSame(CAP_ALLOW, $this->permission($roleid, $cap), "{$cap} must be back-filled");
        }
        $this->assertNull($this->permission($teacherid, self::CAPS[0]),
            'only the Sentientia Author role is back-filled');
        if ($hasothercap) {
            $this->assertNull($this->permission($roleid, $othercap),
                'only this plugin\'s three caps are back-filled');
        }

        // Running it again (a replayed upgrade) changes nothing.
        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
    }

    /**
     * An administrator's PROHIBIT or PREVENT on one cap means nothing is granted.
     */
    public function test_backfill_keeps_an_administrators_prohibit_and_prevent(): void {
        $this->resetAfterTest();
        $roleid = author_role::ensure();
        $syscontext = \context_system::instance();
        [$generate, $review, $managetemplates] = self::CAPS;

        // Generation prohibited for authors on purpose, the other two left
        // "Not set". Not the gap (the gap leaves all three without a row):
        // nothing is granted.
        assign_capability($generate, CAP_PROHIBIT, $roleid, $syscontext->id, true);
        unassign_capability($review, $roleid, $syscontext->id);
        unassign_capability($managetemplates, $roleid, $syscontext->id);

        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertSame(CAP_PROHIBIT, $this->permission($roleid, $generate),
            'the back-fill must never overwrite a PROHIBIT');
        $this->assertNull($this->permission($roleid, $review),
            'a PROHIBIT on one cap is configuration, so the others are not granted');
        $this->assertNull($this->permission($roleid, $managetemplates));

        // A PREVENT is kept the same way, alone or next to the PROHIBIT.
        unassign_capability($generate, $roleid, $syscontext->id);
        assign_capability($review, CAP_PREVENT, $roleid, $syscontext->id, true);
        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $generate),
            'a PREVENT on one cap is configuration, so the others are not granted');
        $this->assertSame(CAP_PREVENT, $this->permission($roleid, $review),
            'the back-fill must never overwrite a PREVENT');
        $this->assertNull($this->permission($roleid, $managetemplates));

        assign_capability($generate, CAP_PROHIBIT, $roleid, $syscontext->id, true);
        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertSame(CAP_PROHIBIT, $this->permission($roleid, $generate));
        $this->assertSame(CAP_PREVENT, $this->permission($roleid, $review));
        $this->assertNull($this->permission($roleid, $managetemplates));
    }

    /**
     * One cap cleared to "Not set" while the others still have rows: nothing is granted.
     */
    public function test_backfill_leaves_a_cap_an_administrator_cleared(): void {
        $this->resetAfterTest();
        $roleid = author_role::ensure();
        $syscontext = \context_system::instance();
        [$generate, $review, $managetemplates] = self::CAPS;

        // "Not set" in the role editor deletes the row. With :generate and
        // :review still ALLOW, the missing :managetemplates row is an
        // administrator's choice: the gap never leaves one of the three set.
        unassign_capability($managetemplates, $roleid, $syscontext->id);
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $generate));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $review));

        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $managetemplates),
            'a cap an administrator cleared must stay cleared');
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $generate));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $review));

        // Two cleared, one still set: still configuration, still nothing.
        unassign_capability($review, $roleid, $syscontext->id);
        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $review));
        $this->assertNull($this->permission($roleid, $managetemplates));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, $generate));
    }

    /**
     * If any of the three caps is not registered, the back-fill writes nothing.
     */
    public function test_backfill_does_nothing_when_a_cap_is_not_registered(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = author_role::ensure();
        $syscontext = \context_system::instance();
        [$generate, $review, $managetemplates] = self::CAPS;

        // The gap (no row for any of the three), but :managetemplates is not
        // in {capabilities}.
        foreach (self::CAPS as $cap) {
            unassign_capability($cap, $roleid, $syscontext->id);
        }
        $DB->delete_records('role_capabilities', ['capability' => $managetemplates]);
        $DB->delete_records('capabilities', ['name' => $managetemplates]);
        \cache::make('core', 'capabilities')->delete('core_capabilities');
        $before = $DB->count_records('role_capabilities');

        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertNull($this->permission($roleid, $generate));
        $this->assertNull($this->permission($roleid, $review));
        $this->assertSame($before, $DB->count_records('role_capabilities'));
    }

    /**
     * Without the author role the back-fill writes nothing.
     */
    public function test_backfill_does_nothing_without_the_author_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => author_role::SHORTNAME]);
        if ($roleid) {
            delete_role($roleid);
        }
        $before = $DB->count_records('role_capabilities');

        $this->assertSame(0, local_sentientia_authoring_backfill_author_caps());
        $this->assertSame($before, $DB->count_records('role_capabilities'));
    }
}
