<?php
// This file is part of Sentientia LMS.

/**
 * An erased author's translations stay with their tenant and belong to nobody.
 *
 * Since 2026-09-24 a DPDP erasure keeps the author's translation rows and sets
 * ownerid to 0 instead of deleting them. 0 must then mean "nobody": a caller
 * whose own id is 0 must not become the owner of every erased author's rows.
 *
 * @package    local_sentientia_translate
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_translate;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_sentientia_translate\translate_engine
 * @covers \local_sentientia_translate\privacy\provider
 */
final class anonymised_owner_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private function erased_row(int $costcenterid): int {
        global $DB;
        $record = ['ownerid' => 0, 'costcenterid' => $costcenterid, 'sourcetext' => 'x',
                   'timecreated' => time(), 'timemodified' => time()];
        foreach ($DB->get_columns(translate_engine::TABLE) as $name => $col) {
            if ($name === 'id' || isset($record[$name]) || !$col->not_null || $col->has_default) {
                continue;
            }
            $record[$name] = in_array($col->meta_type, ['I', 'N', 'R', 'F'], true) ? 1 : 'x';
        }
        return $DB->insert_record(translate_engine::TABLE, (object) $record);
    }

    public function test_an_id_zero_caller_owns_nothing(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $rowid = $this->erased_row(1);
        $nobody = (object) ['id' => 0, 'open_path' => '/177'];

        $this->assertNull(translate_engine::load_for_actor($rowid, $nobody, false));
        $this->assertSame([], translate_engine::list_for_actor($nobody, false));
    }

    public function test_the_tenant_still_sees_an_erased_authors_row(): void {
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $rowid = $this->erased_row(1);
        $colleague = (object) ['id' => 12345, 'open_path' => '/1/2'];

        $this->assertNotNull(translate_engine::load_for_actor($rowid, $colleague, false));
        $this->assertCount(1, translate_engine::list_for_actor($colleague, false));
    }

    public function test_contexts_only_for_people_with_rows(): void {
        $this->resetAfterTest();
        $this->erased_row(1);
        $stranger = $this->getDataGenerator()->create_user();

        $this->assertSame([], privacy\provider::get_contexts_for_userid((int) $stranger->id)->get_contextids());
        $this->assertSame([], privacy\provider::get_contexts_for_userid(0)->get_contextids());
    }
}
