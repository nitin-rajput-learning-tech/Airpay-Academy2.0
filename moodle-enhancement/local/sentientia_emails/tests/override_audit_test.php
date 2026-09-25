<?php
// This file is part of Sentientia LMS.

/**
 * ADR-031 follow-up: the 2026092501 upgrade step switches off tenant template
 * overrides whose author was not entitled to that tenant.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_emails/db/upgradelib.php');

/**
 * Until 2026-09-25 tenant overrides (tenant_id > 0) were never delivered -
 * email_renderer only ever resolved tenant 0 - and any manager-archetype
 * tenant admin could write one for any tenant. ADR-031 makes the renderer
 * deliver them, so the upgrade must switch off the ones a tenant admin wrote
 * for somebody else's tenant before they reach that tenant's learners. These
 * tests pin: a tenant-177 override written by a /1 admin is switched off (and
 * the 177 learner falls back to what they received before), one written by a
 * /177 admin or a site admin stays active, and global overrides are reported
 * but not changed. The report outlives the upgrade output: it is kept in the
 * config changes log and in the adr031_override_audit plugin setting.
 *
 * @covers ::local_sentientia_emails_deactivate_unentitled_overrides
 * @covers ::local_sentientia_emails_global_overrides_for_review
 * @covers ::local_sentientia_emails_override_author_entitled
 * @covers ::local_sentientia_emails_run_override_audit
 * @covers ::local_sentientia_emails_record_override_audit
 * @group tenant_isolation
 */
final class override_audit_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const TPL_A = 'compliance/overdue_alert';
    private const TPL_B = 'compliance/deadline_warning';
    private const TPL_C = 'compliance/reminder_start';
    private const TPL_D = 'compliance/reminder_halfway';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** A user at $path holding the manager role at system context (a tenant admin). */
    private function tenant_admin(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** One override row; (tenant_id, template_key) is a unique index. */
    private function override(int $tenant, string $key, int $authorid, string $body, int $active = 1): int {
        global $DB;
        return (int) $DB->insert_record('local_sentientia_email_overrides', (object) [
            'tenant_id'    => $tenant,
            'template_key' => $key,
            'subject'      => 'S',
            'body_html'    => $body,
            'is_active'    => $active,
            'usermodified' => $authorid,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    private function is_active(int $id): int {
        global $DB;
        return (int) $DB->get_field('local_sentientia_email_overrides', 'is_active', ['id' => $id], MUST_EXIST);
    }

    public function test_override_written_for_another_tenant_is_switched_off(): void {
        $airpayadmin = $this->tenant_admin('/1');
        $zeeaadmin = $this->tenant_admin('/177/178');
        $siteadmin = get_admin();

        $injected = $this->override(177, self::TPL_A, (int) $airpayadmin->id,
            '<a href="https://phish.example">Verify your account</a>');
        $zeeaown = $this->override(177, self::TPL_B, (int) $zeeaadmin->id, '<p>ZEEA own</p>');
        $byadmin = $this->override(177, self::TPL_C, (int) $siteadmin->id, '<p>By site admin</p>');
        $airpayown = $this->override(1, self::TPL_A, (int) $airpayadmin->id, '<p>Airpay own</p>');
        $noauthor = $this->override(77, self::TPL_B, 0, '<p>Unknown author</p>');
        $alreadyoff = $this->override(177, self::TPL_D, (int) $airpayadmin->id, '<p>Already off</p>', 0);
        $global = $this->override(0, self::TPL_A, (int) $airpayadmin->id, '<p>GLOBAL</p>');

        $switchedoff = local_sentientia_emails_deactivate_unentitled_overrides();

        $this->assertSame([$injected, $noauthor], array_map(fn($r) => (int) $r->id, $switchedoff),
            'Only the rows whose author was not entitled to that tenant are switched off.');
        $this->assertSame(1, (int) $switchedoff[0]->author_root, 'The trace names the author\'s own tenant.');
        $this->assertSame(0, $this->is_active($injected),
            'A /1 admin\'s tenant-177 override must not go live for ZEEA learners.');
        $this->assertSame(0, $this->is_active($noauthor), 'An unattributable override cannot be shown to be entitled.');
        $this->assertSame(1, $this->is_active($zeeaown), 'A /177 admin\'s own-tenant override stays active.');
        $this->assertSame(1, $this->is_active($byadmin), 'A site admin may write any tenant; it stays active.');
        $this->assertSame(1, $this->is_active($airpayown), 'A /1 admin\'s own-tenant override stays active.');
        $this->assertSame(0, $this->is_active($alreadyoff));
        $this->assertSame(1, $this->is_active($global),
            'Global overrides were already delivered before ADR-031: left active, reported instead.');

        // A ZEEA learner now gets what they got before this release: the global override.
        $this->assertSame('<p>GLOBAL</p>', template_manager::get_override(self::TPL_A, 177)->body_html);
        $this->assertSame('<p>ZEEA own</p>', template_manager::get_override(self::TPL_B, 177)->body_html);

        // Idempotent.
        $this->assertSame([], local_sentientia_emails_deactivate_unentitled_overrides());
    }

    public function test_global_override_by_a_scoped_author_is_reported_not_changed(): void {
        $airpayadmin = $this->tenant_admin('/1');
        $scoped = $this->override(0, self::TPL_A, (int) $airpayadmin->id, '<p>By a tenant admin</p>');
        $this->override(0, self::TPL_B, (int) get_admin()->id, '<p>By a site admin</p>');

        $review = local_sentientia_emails_global_overrides_for_review();

        $this->assertSame([$scoped], array_map(fn($r) => (int) $r->id, $review));
        $this->assertSame(1, $this->is_active($scoped));
    }

    public function test_entitlement_rule_matches_the_write_rule(): void {
        $airpayadmin = $this->tenant_admin('/1/5');
        $notenant = $this->tenant_admin('');

        $this->assertTrue(local_sentientia_emails_override_author_entitled((int) $airpayadmin->id, 1));
        $this->assertFalse(local_sentientia_emails_override_author_entitled((int) $airpayadmin->id, 177));
        $this->assertFalse(local_sentientia_emails_override_author_entitled((int) $airpayadmin->id, 0),
            'The manager archetype alone does not make a tenant admin cross-tenant.');
        $this->assertFalse(local_sentientia_emails_override_author_entitled((int) $notenant->id, 1));
        $this->assertTrue(local_sentientia_emails_override_author_entitled((int) get_admin()->id, 177));
        $this->assertTrue(local_sentientia_emails_override_author_entitled((int) get_admin()->id, 0));
    }

    /** config_log rows this plugin's audit wrote under $name. */
    private function audit_log(string $name): array {
        global $DB;
        return array_values($DB->get_records('config_log',
            ['plugin' => 'local_sentientia_emails', 'name' => $name], 'id ASC'));
    }

    public function test_the_audit_outlives_the_upgrade_output(): void {
        $airpayadmin = $this->tenant_admin('/1');
        $injected = $this->override(177, self::TPL_A, (int) $airpayadmin->id, '<p>Injected</p>');
        $global = $this->override(0, self::TPL_B, (int) $airpayadmin->id, '<p>Global by a tenant admin</p>');
        $this->override(177, self::TPL_C, (int) get_admin()->id, '<p>Entitled</p>');
        // Both upgrade paths (web, admin/cli/upgrade.php) run as the admin.
        $this->setAdminUser();

        $lines = implode("\n", local_sentientia_emails_run_override_audit());

        // The console trace is what it was.
        $this->assertStringContainsString("DEACTIVATED tenant override id={$injected} template_key=" . self::TPL_A
            . ' tenant_id=177 usermodified=' . $airpayadmin->id, $lines);
        $this->assertStringContainsString("REVIEW (left active) global override id={$global} ", $lines);
        $this->assertSame(0, $this->is_active($injected));

        // Site administration > Reports > Config changes: one entry per row, plus a summary.
        $deactivated = $this->audit_log('adr031_override_deactivated');
        $this->assertCount(1, $deactivated, 'Only the switched-off row is logged as deactivated.');
        $this->assertStringContainsString("tenant override id={$injected} ", $deactivated[0]->value);
        $this->assertStringContainsString('tenant_id=177', $deactivated[0]->value);
        $this->assertSame('is_active=1', $deactivated[0]->oldvalue);
        $review = $this->audit_log('adr031_override_review');
        $this->assertCount(1, $review);
        $this->assertStringContainsString("global override id={$global} ", $review[0]->value);
        $summary = $this->audit_log('adr031_override_audit');
        $this->assertCount(1, $summary);
        $this->assertStringContainsString("[ids: {$injected}]", $summary[0]->value);
        $this->assertStringContainsString("[ids: {$global}]", $summary[0]->value);

        // And the plugin setting, readable with admin/cli/cfg.php. It names the
        // row and the author's tenant, not the author's user id (the override
        // row keeps usermodified).
        $audit = json_decode(get_config('local_sentientia_emails', 'adr031_override_audit'), true);
        $this->assertSame([['id' => $injected, 'template_key' => self::TPL_A, 'tenant_id' => 177, 'author_root' => 1]],
            $audit['deactivated']);
        $this->assertSame([['id' => $global, 'template_key' => self::TPL_B, 'tenant_id' => 0, 'author_root' => 1]],
            $audit['review']);
        $this->assertIsInt($audit['recorded']);
    }

    public function test_an_audit_that_finds_nothing_still_records_that_it_ran(): void {
        $this->setAdminUser();
        $this->override(0, self::TPL_A, (int) get_admin()->id, '<p>By a site admin</p>');

        $lines = local_sentientia_emails_run_override_audit();

        $this->assertContains('0 tenant override(s) deactivated for review.', $lines);
        $audit = json_decode(get_config('local_sentientia_emails', 'adr031_override_audit'), true);
        $this->assertSame([], $audit['deactivated']);
        $this->assertSame([], $audit['review']);
        $this->assertSame([], $this->audit_log('adr031_override_deactivated'));
        $this->assertSame([], $this->audit_log('adr031_override_review'));
        $this->assertCount(1, $this->audit_log('adr031_override_audit'),
            'An empty result is still recorded, so "nothing found" is distinguishable from "never ran".');
    }
}
