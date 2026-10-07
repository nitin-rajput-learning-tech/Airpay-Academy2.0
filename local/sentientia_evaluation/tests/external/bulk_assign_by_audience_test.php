<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_evaluation\evaluation_manager;

/**
 * The bulk-assign web service refuses an audience rule that narrows nothing (EV-36).
 *
 * '{}' used to mean "everyone in my tenant" (up to 2,000 people), while the bulk-assign form already refused it
 * ("pick at least one filter criterion"). The web service now matches the form: every way of sending no filter is
 * refused before anything is written, and a rule that names something still assigns.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\external\bulk_assign_by_audience
 *
 * @group local_sentientia_evaluation
 */
final class bulk_assign_by_audience_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->setAdminUser();
        // The web service checks the session key itself.
        $_POST['sesskey'] = sesskey();
    }

    /**
     * @param string $designation
     * @return int
     */
    private function user_with_designation(string $designation): int {
        global $DB;
        $id = (int) $this->getDataGenerator()->create_user()->id;
        $DB->set_field('user', 'open_designation', $designation, ['id' => $id]);
        return $id;
    }

    public function test_every_way_of_sending_no_filter_is_refused_and_writes_nothing(): void {
        global $DB;
        $evaluationid = evaluation_manager::create((object) ['name' => 'Nobody by default']);
        $this->user_with_designation('Present');

        foreach ([
            'an empty object' => '{}',
            'an empty string' => '',
            'blank values' => json_encode(['designation' => '', 'region' => '  ', 'cohortid' => 0]),
            'a key nobody supports' => json_encode(['nonsense' => 'x']),
            'malformed JSON' => '{"designation": ',
            'a list, not a map' => '["Present"]',
        ] as $what => $filters) {
            try {
                bulk_assign_by_audience::execute($evaluationid, $filters);
                $this->fail("{$what} should be refused");
            } catch (\moodle_exception $e) {
                $this->assertSame('bulk_assign_pick_at_least_one', $e->errorcode, $what);
            }
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_evaluation_assign', ['evaluationid' => $evaluationid]));
    }

    public function test_a_rule_that_names_something_still_assigns(): void {
        global $DB;
        $evaluationid = evaluation_manager::create((object) ['name' => 'Auditors']);
        $a = $this->user_with_designation('Auditor');
        $b = $this->user_with_designation('Auditor');
        $this->user_with_designation('Somebody else');

        $result = bulk_assign_by_audience::execute($evaluationid, json_encode(['designation' => 'Auditor']));
        $this->assertSame(['matched' => 2, 'assigned' => 2, 'capped' => false], $result);
        $rows = $DB->get_records('local_sentientia_evaluation_assign', ['evaluationid' => $evaluationid], 'userid ASC');
        $this->assertSame([$a, $b], array_map('intval', array_column($rows, 'userid')));

        // A blank value beside a real one is fine: the real one narrows the audience.
        $again = bulk_assign_by_audience::execute($evaluationid,
            json_encode(['designation' => 'Auditor', 'region' => '']));
        $this->assertSame(['matched' => 2, 'assigned' => 0, 'capped' => false], $again);
    }
}
