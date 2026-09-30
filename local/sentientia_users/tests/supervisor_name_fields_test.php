<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

/**
 * Name-field notice on supervisor lookups (persona pass 2026-09-30, D14).
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * user_manager::get_supervisor() selected only `id, firstname, lastname,
 * open_employeeid` and then called fullname() on the result. fullname() reads
 * all six name fields (phonetic, middle and alternate names as well) and, with
 * developer debugging on, prints "The following name fields are missing from
 * the user object". That notice appeared on every profile view that shows a
 * supervisor. It was also a latent wrong-output bug: a site whose
 * fullnamedisplay template includes middlename/alternatename would print the
 * supervisor's name without them.
 *
 * sync_runs.php had the same shape (it joins {user} for the person who started
 * a run and calls fullname() on the row) and is fixed the same way.
 *
 * @package    local_sentientia_users
 * @category   test
 */
final class supervisor_name_fields_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /**
     * Make fullname() report missing name fields, as a developer-debug site does.
     */
    private function enable_developer_debugging(): void {
        global $CFG;
        $CFG->debug = DEBUG_DEVELOPER;
        $CFG->debugdeveloper = true;
    }

    /**
     * get_supervisor() raises no name-field notice and returns the usual keys.
     */
    public function test_get_supervisor_raises_no_name_field_notice(): void {
        global $DB;
        $this->enable_developer_debugging();

        $mgr = $this->getDataGenerator()->create_user([
            'firstname' => 'Asha',
            'lastname'  => 'Verma',
        ]);
        $DB->set_field('user', 'open_employeeid', 'EMP-9001', ['id' => $mgr->id]);

        $result = user_manager::get_supervisor((int) $mgr->id);

        $this->assertNotNull($result);
        $this->assertSame((int) $mgr->id, (int) $result->id);
        $this->assertSame('Asha Verma', $result->fullname);
        $this->assertSame('EMP-9001', $result->employeeid);
        $this->assertSame('Asha', $result->firstname);
        $this->assertSame('Verma', $result->lastname);
        $this->assertDebuggingNotCalled();
    }

    /**
     * The middle, alternate and phonetic name fields are really loaded: a
     * fullnamedisplay template that uses them must render them.
     */
    public function test_get_supervisor_loads_middle_and_alternate_names(): void {
        global $CFG;
        $this->enable_developer_debugging();
        $CFG->fullnamedisplay = 'firstname middlename alternatename lastname';

        $mgr = $this->getDataGenerator()->create_user([
            'firstname'          => 'Asha',
            'middlename'         => 'Kumari',
            'alternatename'      => 'Ash',
            'lastname'           => 'Verma',
            'firstnamephonetic'  => 'Aasha',
            'lastnamephonetic'   => 'Vermaa',
        ]);

        $result = user_manager::get_supervisor((int) $mgr->id);

        $this->assertNotNull($result);
        $this->assertSame('Asha Kumari Ash Verma', $result->fullname);
        $this->assertSame('Kumari', $result->middlename);
        $this->assertSame('Ash', $result->alternatename);
        $this->assertSame('Aasha', $result->firstnamephonetic);
        $this->assertSame('Vermaa', $result->lastnamephonetic);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Every name field fullname() knows about is part of the returned object.
     */
    public function test_get_supervisor_returns_all_core_name_fields(): void {
        $mgr = $this->getDataGenerator()->create_user();
        $result = user_manager::get_supervisor((int) $mgr->id);

        $this->assertNotNull($result);
        foreach (\core_user\fields::get_name_fields() as $field) {
            $this->assertTrue(property_exists($result, $field),
                "get_supervisor() result is missing name field '$field'.");
        }
    }

    /**
     * A zero, unknown or deleted supervisor id still yields null, unchanged.
     */
    public function test_get_supervisor_still_returns_null_for_nobody(): void {
        $this->assertNull(user_manager::get_supervisor(0));
        $this->assertNull(user_manager::get_supervisor(99999999));

        global $DB;
        $gone = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $this->assertNull(user_manager::get_supervisor((int) $gone->id));
    }

    /**
     * sync_runs.php joins {user} and calls fullname() on the row, so it has to
     * select every name field too. It is a page script (no callable seam), so
     * guard its source: it must build the select from the core name-field list
     * and must not go back to the two-field select.
     */
    public function test_sync_runs_selects_every_name_field(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/local/sentientia_users/sync_runs.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('\core_user\fields::get_name_fields()', $source);
        $this->assertStringNotContainsString('u.firstname, u.lastname,', $source,
            'sync_runs.php must not select only firstname+lastname for fullname().');
    }
}
