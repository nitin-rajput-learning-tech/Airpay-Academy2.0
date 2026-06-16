<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit tests for impact_manager — the skill -> business-impact mapping
 * surface, including weight clamping and tenant-scoped listing.
 *
 * @package    local_sentientia_skillsai
 * @covers     \local_sentientia_skillsai\impact_manager
 */
final class impact_manager_test extends \advanced_testcase {

    use \local_sentientia_platform\phpunit\open_path_fixture_trait;

    /**
     * Seed a canonical taxonomy node directly for a tenant.
     *
     * @return int taxonomy node id
     */
    private function seed_node(int $tenant, string $name, int $approverid): int {
        global $DB;
        $now = time();
        return $DB->insert_record(taxonomy_manager::TAXONOMY_TABLE, (object)[
            'customerid' => 1, 'costcenterid' => $tenant, 'name' => $name,
            'description' => 'desc', 'category' => 'Compliance', 'max_level' => 5,
            'origin_candidateid' => null, 'approved_by' => $approverid,
            'linked_skillid' => null, 'status' => taxonomy_manager::TAX_ACTIVE,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    public function test_create_inherits_tenant_from_node(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $taxid = $this->seed_node(77, 'KYC', (int)$user->id);

        $impactid = impact_manager::create($taxid, 'Onboarding pass rate',
            'KYC gaps slow onboarding', 5, (int)$user->id);
        $row = $DB->get_record(impact_manager::IMPACT_TABLE, ['id' => $impactid], '*', MUST_EXIST);
        $this->assertSame(77, (int)$row->costcenterid);
        $this->assertSame(1, (int)$row->customerid);
        $this->assertSame(5, (int)$row->weight);
        $this->assertSame('Onboarding pass rate', $row->metric_name);
        $this->assertGreaterThan(0, (int)$row->timecreated);
    }

    public function test_create_clamps_weight(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $taxid = $this->seed_node(1, 'AML', (int)$user->id);

        $hi = impact_manager::create($taxid, 'Chargebacks', '', 99, (int)$user->id);
        $lo = impact_manager::create($taxid, 'NPS', '', -3, (int)$user->id);
        $this->assertSame(5, (int)$DB->get_field(impact_manager::IMPACT_TABLE, 'weight', ['id' => $hi]));
        $this->assertSame(1, (int)$DB->get_field(impact_manager::IMPACT_TABLE, 'weight', ['id' => $lo]));
    }

    public function test_create_rejects_missing_node(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\dml_exception::class);
        impact_manager::create(999999, 'Metric', '', 3, (int)$user->id);
    }

    public function test_update_changes_fields_and_clamps(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $taxid = $this->seed_node(1, 'Disputes', (int)$user->id);
        $id = impact_manager::create($taxid, 'Old metric', 'old', 2, (int)$user->id);

        impact_manager::update($id, ['metric_name' => 'New metric', 'weight' => 50]);
        $row = $DB->get_record(impact_manager::IMPACT_TABLE, ['id' => $id]);
        $this->assertSame('New metric', $row->metric_name);
        $this->assertSame(5, (int)$row->weight);
    }

    public function test_list_for_tenant_scopes_and_orders(): void {
        $user = $this->getDataGenerator()->create_user();
        $n1 = $this->seed_node(1, 'Skill A', (int)$user->id);
        $n77 = $this->seed_node(77, 'Skill B', (int)$user->id);

        impact_manager::create($n1, 'Low', '', 2, (int)$user->id);
        impact_manager::create($n1, 'High', '', 5, (int)$user->id);
        impact_manager::create($n77, 'OtherTenant', '', 4, (int)$user->id);

        $tenant1 = impact_manager::list_for_tenant(1);
        $this->assertCount(2, $tenant1);
        // Ordered by weight DESC — High (5) first.
        $this->assertSame('High', $tenant1[0]->metric_name);

        $tenant77 = impact_manager::list_for_tenant(77);
        $this->assertCount(1, $tenant77);
        $this->assertSame('OtherTenant', $tenant77[0]->metric_name);
    }

    public function test_delete_removes_mapping(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $taxid = $this->seed_node(1, 'X', (int)$user->id);
        $id = impact_manager::create($taxid, 'M', '', 3, (int)$user->id);
        impact_manager::delete($id);
        $this->assertFalse($DB->record_exists(impact_manager::IMPACT_TABLE, ['id' => $id]));
    }
}
