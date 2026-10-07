<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * The individual responses pages (response_list.php, response_detail.php) and their gate (EV-06).
 *
 * The two pages asked for local/sentientia_evaluation:view, which no plugin declares, so nobody could open them,
 * site administrators included. They now ask for :manage (the manager archetype: manager, tenant administrator, site
 * administrator) behind the default-OFF flag sentientia.evaluation.response_drilldown, and the ADR-031 tenant gate
 * and identity_protected() are unchanged. The pages themselves are scripts, so the gate lives in
 * evaluation_manager::require_response_drilldown() and the tests call it as the pages do, then check the pages
 * call it first.
 *
 * @package    local_sentientia_evaluation
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_evaluation\evaluation_manager
 *
 * @group local_sentientia_evaluation
 * @group tenant_isolation
 */
final class response_drilldown_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
        $this->seed_org('/1');
        $this->seed_org('/177');
    }

    protected function tearDown(): void {
        try {
            feature_flags::set(evaluation_manager::FLAG_RESPONSE_DRILLDOWN, 0, null);
        } catch (\Throwable $e) {
            // The database is reset after the test anyway; a flag left behind cannot outlive it.
            debugging('could not unset the test flag: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        parent::tearDown();
    }

    private function seed_org(string $path): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('local_sentientia_org', (object) [
            'fullname' => 'Org ' . $path, 'shortname' => 'org' . str_replace('/', '_', $path), 'parentid' => 0,
            'path' => $path, 'depth' => substr_count($path, '/'), 'visible' => 1, 'sortorder' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * An evaluation bound to the organisation at a path.
     *
     * @param string $name
     * @param string $path
     * @param int $anonymous
     * @return \stdClass
     */
    private function evaluation(string $name, string $path, int $anonymous = 0): \stdClass {
        global $DB;
        $org = $DB->get_record('local_sentientia_org', ['path' => $path], '*', MUST_EXIST);
        $now = time();
        $id = (int) $DB->insert_record('local_sentientia_evaluation', (object) [
            'name' => $name, 'description' => '', 'kirkpatrick_level' => 1, 'trigger_event' => 'manual',
            'days_after' => 0, 'costcenterid' => (int) $org->id, 'open_path' => $path,
            'status' => evaluation_manager::STATUS_ACTIVE, 'anonymous' => $anonymous,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $DB->get_record('local_sentientia_evaluation', ['id' => $id], '*', MUST_EXIST);
    }

    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * @param \stdClass $user
     * @param string $shortname Stock role.
     * @return void
     */
    private function give_role(\stdClass $user, string $shortname): void {
        global $DB;
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /** A tenant admin: the stock manager role at system context. */
    private function tenant_admin(string $path): \stdClass {
        $u = $this->user_at($path);
        $this->give_role($u, 'manager');
        return $u;
    }

    /** A trainer: a role built on the teacher archetype, at system context, with that archetype's own defaults. */
    private function trainer(string $path): \stdClass {
        $u = $this->user_at($path);
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'evtrainer', 'archetype' => 'teacher']);
        reset_role_capabilities($roleid);
        role_assign($roleid, $u->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $u;
    }

    private function flag_on(): void {
        $this->setAdminUser();
        feature_flags::set(evaluation_manager::FLAG_RESPONSE_DRILLDOWN, 0, true);
    }

    /**
     * @param callable $gate
     * @return string The error code of the moodle_exception it throws, or '' when it throws none.
     */
    private function refusal(callable $gate): string {
        try {
            $gate();
            return '';
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        }
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        feature_flags::invalidate_caches();
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia.evaluation.response_drilldown', $registry);
        $this->assertFalse($registry['sentientia.evaluation.response_drilldown']['default']);
        $this->assertSame('sentientia.evaluation.response_drilldown', evaluation_manager::FLAG_RESPONSE_DRILLDOWN);
        $this->assertNotSame('', trim($registry['sentientia.evaluation.response_drilldown']['description']));
        $this->assertFalse(evaluation_manager::response_drilldown_enabled());
    }

    public function test_with_the_flag_off_even_a_tenant_admin_is_told_it_is_not_available(): void {
        $this->setUser($this->tenant_admin('/1'));
        $this->assertFalse(evaluation_manager::response_drilldown_enabled());
        $this->assertSame('response_drilldown_unavailable',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown()));
        // Site administrators too: the flag, not the role, decides whether the pages exist.
        $this->setAdminUser();
        $this->assertSame('response_drilldown_unavailable',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown()));
        $this->assertTrue(get_string_manager()->string_exists('response_drilldown_unavailable',
            'local_sentientia_evaluation'));
    }

    public function test_with_the_flag_on_a_tenant_admin_gets_in_for_their_own_tenant_only(): void {
        $own = $this->evaluation('Own tenant', '/1');
        $foreign = $this->evaluation('Another tenant', '/177');
        $admin = $this->tenant_admin('/1');
        $this->flag_on();

        $this->setUser($admin);
        $this->assertTrue(evaluation_manager::response_drilldown_enabled());
        evaluation_manager::require_response_drilldown();
        evaluation_manager::require_evaluation_access($own);
        $this->assertSame('error_outoftenant',
            $this->refusal(static fn() => evaluation_manager::require_evaluation_access($foreign)),
            'ADR-031 still applies behind the flag');

        // The site administrator is cross-tenant: both.
        $this->setAdminUser();
        evaluation_manager::require_response_drilldown();
        evaluation_manager::require_evaluation_access($foreign);
    }

    public function test_a_learner_and_a_trainer_are_refused_with_the_flag_on_or_off(): void {
        $learner = $this->user_at('/1');
        $this->give_role($learner, 'student');
        $trainer = $this->trainer('/1');
        $nobody = $this->user_at('/1');

        // The learner holds :respond (the student archetype) and nothing else here.
        $this->setUser($learner);
        $this->assertTrue(has_capability('local/sentientia_evaluation:respond', \context_system::instance()));

        foreach ([false, true] as $on) {
            if ($on) {
                $this->flag_on();
            }
            foreach (['a :respond-only learner' => $learner, 'a trainer (teacher archetype)' => $trainer,
                    'a user with no role' => $nobody] as $who => $user) {
                $this->setUser($user);
                $this->assertFalse(has_capability('local/sentientia_evaluation:manage', \context_system::instance()), $who);
                try {
                    evaluation_manager::require_response_drilldown();
                    $this->fail($who . ' should be refused (flag ' . ($on ? 'on' : 'off') . ')');
                } catch (\required_capability_exception $e) {
                    // The capability is checked before the flag, so a caller who may not be here learns nothing
                    // about whether the flag is on.
                    $this->assertSame('nopermissions', $e->errorcode, $who);
                }
            }
        }
    }

    public function test_the_two_pages_call_the_gate_first_and_no_longer_ask_for_the_undeclared_capability(): void {
        foreach (['response_list.php', 'response_detail.php'] as $page) {
            $source = (string) file_get_contents(__DIR__ . '/../' . $page);
            $this->assertStringNotContainsString('sentientia_evaluation:view', $source, $page);
            $gate = strpos($source, 'evaluation_manager::require_response_drilldown()');
            $this->assertNotFalse($gate, $page . ' calls the gate');
            $this->assertLessThan(strpos($source, 'required_param('), $gate, $page . ' gates before it reads anything');
            $this->assertLessThan(strpos($source, '$DB->get_record('), $gate, $page . ' gates before it queries');
            $this->assertGreaterThan($gate, strpos($source, 'require_evaluation_access('), $page . ' keeps the ADR-031 gate');
        }
        // And nothing in the plugin declares or asks for it.
        $access = (string) file_get_contents(__DIR__ . '/../db/access.php');
        $this->assertStringNotContainsString(':view', $access);
        $this->assertStringContainsString('local/sentientia_evaluation:manage', $access);
    }

    public function test_the_individual_responses_link_follows_the_flag(): void {
        // responses.php builds has_list_link from response_drilldown_enabled(); with the flag OFF nothing points at
        // the pages, and with it ON the template draws the link.
        global $OUTPUT, $PAGE;
        $PAGE->set_url('/local/sentientia_evaluation/responses.php');
        $render = fn(bool $link): string => $OUTPUT->render_from_template('local_sentientia_evaluation/responses', [
            'name' => 'Survey', 'description' => '', 'is_anonymous' => false, 'kirkpatrick_label' => '',
            'evaluationid' => 7, 'total_responses' => 0, 'has_responses' => false, 'questions' => [],
            'has_questions' => false, 'backurl' => '/b', 'export_url' => '/e', 'reset_url' => '/r',
            'filter_action_url' => '/f', 'filter_date_from' => '', 'filter_date_to' => '', 'has_filter' => false,
            'has_list_link' => $link, 'list_url' => 'https://example.invalid/response_list.php?id=7',
        ]);
        $this->assertStringNotContainsString('response_list.php', $render(false));
        $html = $render(true);
        $this->assertStringContainsString('response_list.php?id=7', $html);
        $this->assertStringContainsString(get_string('responses_individual_link', 'local_sentientia_evaluation'), $html);

        $this->assertFalse(evaluation_manager::response_drilldown_enabled(), 'the page reads the flag, which is OFF');
        $this->flag_on();
        $this->assertTrue(evaluation_manager::response_drilldown_enabled());
    }

    /**
     * response_detail.php names the respondent through fullname(), as the response list and the CSV do, so the
     * site's name format applies to all three; a protected evaluation names nobody.
     */
    public function test_the_detail_page_names_the_respondent_the_way_the_list_and_the_csv_do(): void {
        global $DB;
        set_config('fullnamedisplay', 'lastname, firstname');
        $person = $this->getDataGenerator()->create_user(['firstname' => 'Sue', 'lastname' => 'Supervisor']);
        $DB->set_field('user', 'open_employeeid', 'E-1042', ['id' => $person->id]);
        $evaluation = $this->evaluation('Named', '/1');
        $responseid = (int) $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $evaluation->id, 'userid' => $person->id, 'response_data' => '{}',
            'timesubmitted' => time(),
        ]);
        $response = $DB->get_record('local_sentientia_evaluation_responses', ['id' => $responseid], '*', MUST_EXIST);

        $named = evaluation_manager::response_detail_respondent($response, false);
        $this->assertSame(fullname($DB->get_record('user', ['id' => $person->id], '*', MUST_EXIST)), $named['user_name']);
        $this->assertNotSame('Sue Supervisor', $named['user_name'], 'the site\'s name format applies, not first + last');
        $this->assertSame($person->email, $named['user_email']);
        $this->assertSame('E-1042', $named['employee_id']);

        // The same person, named the same way, by the response list.
        $rows = evaluation_manager::response_list_rows($evaluation, false, false);
        $this->assertSame($named['user_name'], $rows[0]['user_name']);

        // A protected evaluation names nobody, whatever the row holds.
        $anonymous = get_string('eval_response_responder_anonymous', 'local_sentientia_evaluation');
        $this->assertSame(['user_name' => $anonymous, 'user_email' => '', 'employee_id' => ''],
            evaluation_manager::response_detail_respondent($response, true));
        $protected = $this->evaluation('Anonymous', '/1', 1);
        $this->assertTrue(evaluation_manager::identity_protected($protected));
        // A response with no user, or whose account is gone, shows the same label as before.
        $this->assertSame($anonymous, evaluation_manager::response_detail_respondent(
            (object) ['userid' => 0], false)['user_name']);
        $this->assertSame($anonymous, evaluation_manager::response_detail_respondent(
            (object) ['userid' => 987654321], false)['user_name']);
    }
}
