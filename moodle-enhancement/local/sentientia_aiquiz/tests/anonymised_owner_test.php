<?php
// This file is part of Sentientia LMS.

/**
 * An erased author's drafts stay with their tenant and belong to nobody.
 *
 * Since 2026-09-24 a DPDP erasure keeps the author's AI quiz drafts and sets
 * ownerid to 0 instead of deleting them. 0 must then mean "nobody": a caller
 * whose own id is 0 (CLI, not logged in) must not become the owner of every
 * erased author's drafts in every tenant.
 *
 * @package    local_sentientia_aiquiz
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_aiquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_aiquiz\draft_manager
 * @covers \local_sentientia_aiquiz\privacy\provider
 */
final class anonymised_owner_test extends \advanced_testcase {

    private function erased_draft(int $costcenterid): int {
        global $DB;
        $record = ['ownerid' => 0, 'costcenterid' => $costcenterid, 'title' => 'Erased author draft',
                   'sourcetext' => 'x', 'timecreated' => time(), 'timemodified' => time()];
        foreach ($DB->get_columns(draft_manager::DRAFT_TABLE) as $name => $col) {
            if ($name === 'id' || isset($record[$name]) || !$col->not_null || $col->has_default) {
                continue;
            }
            $record[$name] = in_array($col->meta_type, ['I', 'N', 'R', 'F'], true) ? 1 : 'x';
        }
        return $DB->insert_record(draft_manager::DRAFT_TABLE, (object) $record);
    }

    public function test_an_id_zero_caller_owns_nothing(): void {
        $this->resetAfterTest();
        $draftid = $this->erased_draft(1);
        // A caller with id 0 in ANOTHER tenant: before the fix, ownerid 0 matched.
        $nobody = (object) ['id' => 0, 'open_path' => '/177'];

        $this->assertNull(draft_manager::load_for_actor($draftid, $nobody, false));
        $this->assertSame([], draft_manager::list_for_actor($nobody, false));
    }

    public function test_the_tenant_still_sees_an_erased_authors_draft(): void {
        $this->resetAfterTest();
        $draftid = $this->erased_draft(1);
        $colleague = (object) ['id' => 12345, 'open_path' => '/1/2'];

        $this->assertNotNull(draft_manager::load_for_actor($draftid, $colleague, false));
        $this->assertCount(1, draft_manager::list_for_actor($colleague, false));
    }

    public function test_contexts_only_for_people_with_rows(): void {
        $this->resetAfterTest();
        $this->erased_draft(1);
        $stranger = $this->getDataGenerator()->create_user();

        $this->assertSame([], privacy\provider::get_contexts_for_userid((int) $stranger->id)->get_contextids());
        $this->assertSame([], privacy\provider::get_contexts_for_userid(0)->get_contextids());
    }
}
