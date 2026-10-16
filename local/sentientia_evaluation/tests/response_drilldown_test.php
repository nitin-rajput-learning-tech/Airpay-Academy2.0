<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;

/**
 * The individual responses pages (response_list.php, response_detail.php) and their gate (EV-06).
 *
 * The two pages asked for local/sentientia_evaluation:view, which no plugin declares, so nobody could open them, site
 * administrators included. They now ask for :manage (the manager archetype: manager, tenant administrator, site
 * administrator) behind the default-OFF flag sentientia.evaluation.response_drilldown, and the ADR-031 tenant gate and
 * identity_protected() are unchanged. The pages themselves are scripts, so the gate lives in
 * evaluation_manager::require_response_drilldown_capability() (before anything is read) and
 * evaluation_manager::require_response_drilldown() (once the evaluation is loaded), and the tests call them as the
 * pages do, then check the pages call them in that order.
 *
 * Review round of 2026-10-07: the flag is read for the EVALUATION's tenant, not the viewer's, and an evaluation whose
 * respondents are protected offers no individual responses at all (a notice and the totals).
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

    /**
     * Set (or, with null, unset) the flag at a tenant. The writer is named, so the current user is left alone.
     *
     * @param int $tenant 0 = customer-wide / global
     * @param bool|null $value
     * @return void
     */
    private function set_flag(int $tenant, ?bool $value): void {
        feature_flags::set(evaluation_manager::FLAG_RESPONSE_DRILLDOWN, $tenant, $value, (int) get_admin()->id);
    }

    private function flag_on(): void {
        $this->set_flag(0, true);
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

    /**
     * One response row.
     *
     * @param \stdClass $evaluation
     * @param int $userid 0 = an anonymous answer
     * @return \stdClass
     */
    private function response(\stdClass $evaluation, int $userid): \stdClass {
        global $DB;
        $id = (int) $DB->insert_record('local_sentientia_evaluation_responses', (object) [
            'evaluationid' => $evaluation->id, 'userid' => $userid, 'response_data' => '{}',
            'timesubmitted' => time(),
        ]);
        return $DB->get_record('local_sentientia_evaluation_responses', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        feature_flags::invalidate_caches();
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia.evaluation.response_drilldown', $registry);
        $this->assertFalse($registry['sentientia.evaluation.response_drilldown']['default']);
        $this->assertSame('sentientia.evaluation.response_drilldown', evaluation_manager::FLAG_RESPONSE_DRILLDOWN);
        $this->assertNotSame('', trim($registry['sentientia.evaluation.response_drilldown']['description']));
        $this->assertFalse(evaluation_manager::response_drilldown_enabled());
        $this->assertFalse(evaluation_manager::response_drilldown_enabled($this->evaluation('Any', '/1')));
    }

    public function test_with_the_flag_off_even_a_tenant_admin_is_told_it_is_not_available(): void {
        $own = $this->evaluation('Own tenant', '/1');
        $this->setUser($this->tenant_admin('/1'));
        $this->assertFalse(evaluation_manager::response_drilldown_enabled($own));
        $this->assertSame('response_drilldown_unavailable',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown($own)));
        // Site administrators too: the flag, not the role, decides whether the pages exist.
        $this->setAdminUser();
        $this->assertSame('response_drilldown_unavailable',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown($own)));
        $this->assertTrue(get_string_manager()->string_exists('response_drilldown_unavailable',
            'local_sentientia_evaluation'));
    }

    public function test_with_the_flag_on_a_tenant_admin_gets_in_for_their_own_tenant_only(): void {
        $own = $this->evaluation('Own tenant', '/1');
        $foreign = $this->evaluation('Another tenant', '/177');
        $admin = $this->tenant_admin('/1');
        $this->flag_on();

        $this->setUser($admin);
        $this->assertTrue(evaluation_manager::response_drilldown_enabled($own));
        evaluation_manager::require_response_drilldown_capability();
        evaluation_manager::require_response_drilldown($own);
        evaluation_manager::require_evaluation_access($own);
        $this->assertSame('error_outoftenant',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown($foreign)),
            'ADR-031 still applies behind the flag, and it answers first');

        // The site administrator is cross-tenant: both.
        $this->setAdminUser();
        evaluation_manager::require_response_drilldown($own);
        evaluation_manager::require_response_drilldown($foreign);
    }

    /**
     * Review round of 2026-10-07: require_response_drilldown() read the flag for the VIEWER's customer and tenant. A
     * cross-tenant administrator whose own scope has it ON could open a tenant where it is OFF. It is read for the
     * evaluation's tenant now.
     */
    public function test_the_flag_is_read_for_the_evaluations_tenant_not_the_viewers(): void {
        $first = $this->evaluation('Tenant one', '/1');
        $other = $this->evaluation('Tenant one hundred seventy-seven', '/177');

        // ON everywhere, OFF for tenant 177: the site administrator's own scope (no tenant) says ON.
        $this->flag_on();
        $this->set_flag(177, false);
        $this->setAdminUser();
        $this->assertTrue(evaluation_manager::response_drilldown_enabled(), 'the viewer\'s own scope says ON');
        evaluation_manager::require_response_drilldown($first);
        $this->assertTrue(evaluation_manager::response_drilldown_enabled($first));
        $this->assertFalse(evaluation_manager::response_drilldown_enabled($other));
        $this->assertSame('response_drilldown_unavailable',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown($other)),
            'a cross-tenant admin cannot open a tenant where the flag is OFF');

        // The other way round: OFF by default, ON for tenant 177 only. The viewer's scope says OFF, the evaluation's ON.
        $this->set_flag(177, null);
        $this->set_flag(0, null);
        $this->set_flag(177, true);
        $this->assertFalse(evaluation_manager::response_drilldown_enabled(), 'the viewer\'s own scope says OFF');
        evaluation_manager::require_response_drilldown($other);
        $this->assertSame('response_drilldown_unavailable',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown($first)));

        // A tenant administrator is held to their own tenant first, and then to that tenant's flag.
        $admin177 = $this->tenant_admin('/177');
        $this->setUser($admin177);
        evaluation_manager::require_response_drilldown($other);
        $this->assertSame('error_outoftenant',
            $this->refusal(static fn() => evaluation_manager::require_response_drilldown($first)),
            'another tenant is refused as out of tenant, whatever its flag says');
    }

    public function test_the_flag_scope_of_an_evaluation_is_its_tenant(): void {
        $this->assertSame([\local_sentientia_platform\customer::AIRPAY, 1],
            evaluation_manager::flag_scope($this->evaluation('One', '/1')));
        $this->assertSame(177, evaluation_manager::flag_scope((object) ['open_path' => '/177/5/9', 'costcenterid' => 12])[1],
            'the root of the path, not the org the form is bound to');
        $this->assertSame(77, evaluation_manager::flag_scope((object) ['open_path' => null, 'costcenterid' => 77])[1],
            'tenant-bound but pathless: /<costcenterid>, as evaluation_engine reads it');
        $this->assertSame(0, evaluation_manager::flag_scope((object) ['open_path' => '', 'costcenterid' => 0])[1],
            'a global evaluation or an imported form no clue could place belongs to no tenant');
        $this->assertSame(0, evaluation_manager::flag_scope((object) ['open_path' => 'abc', 'costcenterid' => 0])[1]);
        $this->assertSame(0, evaluation_manager::flag_scope((object) [])[1]);
        $this->assertGreaterThan(0, evaluation_manager::flag_scope((object) [])[0], 'always a customer');
    }

    public function test_a_learner_and_a_trainer_are_refused_with_the_flag_on_or_off(): void {
        $own = $this->evaluation('Own tenant', '/1');
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
                foreach ([static fn() => evaluation_manager::require_response_drilldown_capability(),
                        static fn() => evaluation_manager::require_response_drilldown($own)] as $gate) {
                    try {
                        $gate();
                        $this->fail($who . ' should be refused (flag ' . ($on ? 'on' : 'off') . ')');
                    } catch (\required_capability_exception $e) {
                        // The capability is checked before the tenant and the flag, so a caller who may not be here
                        // learns nothing about whether the flag is on.
                        $this->assertSame('nopermissions', $e->errorcode, $who);
                    }
                }
            }
        }
    }

    public function test_the_two_pages_gate_in_order_and_no_longer_ask_for_the_undeclared_capability(): void {
        foreach (['response_list.php', 'response_detail.php'] as $page) {
            $source = (string) file_get_contents(__DIR__ . '/../' . $page);
            $this->assertStringNotContainsString('sentientia_evaluation:view', $source, $page);

            // 1. The capability, before the page reads anything.
            $capability = strpos($source, 'evaluation_manager::require_response_drilldown_capability()');
            $this->assertNotFalse($capability, $page . ' asks for the capability');
            $this->assertLessThan(strpos($source, 'required_param('), $capability, $page . ' gates before it reads anything');
            $this->assertLessThan(strpos($source, '$DB->get_record('), $capability, $page . ' gates before it queries');

            // 2. Tenant and flag, once the evaluation is loaded, and before the page says anything about it.
            $gate = strpos($source, 'evaluation_manager::require_response_drilldown($evaluation)');
            $this->assertNotFalse($gate, $page . ' calls the second step of the gate');
            $this->assertGreaterThan(strrpos(substr($source, 0, $gate), '$DB->get_record('), $gate,
                $page . ' loads the evaluation first, so the flag can be read for ITS tenant');
            $this->assertLessThan(strpos($source, '$OUTPUT->header()'), $gate, $page . ' gates before it renders');

            // 3. A protected evaluation gets the notice, before any response is read or shown.
            $protected = strpos($source, 'identity_protected($evaluation)');
            $this->assertNotFalse($protected, $page . ' checks whether respondents are protected');
            $this->assertGreaterThan($gate, $protected);
            $notice = strpos($source, 'individual_responses_protected_notice($evaluation)');
            $this->assertNotFalse($notice, $page . ' answers a protected evaluation with the notice');
            $this->assertGreaterThan($protected, $notice);
        }
        $list = (string) file_get_contents(__DIR__ . '/../response_list.php');
        $this->assertGreaterThan(strpos($list, 'individual_responses_protected_notice($evaluation)'),
            strpos($list, 'response_list_rows('), 'the list is read only after the notice has had its say');
        $detail = (string) file_get_contents(__DIR__ . '/../response_detail.php');
        $noticeat = strpos($detail, 'individual_responses_protected_notice($evaluation)');
        foreach (['require_submitted_response(', 'response_detail_respondent(', 'response_detail_rows('] as $call) {
            $this->assertGreaterThan($noticeat, strpos($detail, $call),
                "response_detail.php reads nothing of the response ({$call}) before the notice");
        }
        // And nothing in the plugin declares or asks for the capability the pages used to name.
        $access = (string) file_get_contents(__DIR__ . '/../db/access.php');
        $this->assertStringNotContainsString(':view', $access);
        $this->assertStringContainsString('local/sentientia_evaluation:manage', $access);
    }

    /**
     * Review round of 2026-10-07 (anonymity, before the flag is flipped): on a protected evaluation one response on its
     * own, with its day and the course, program or classroom it came from, can single out a person in a small group,
     * and the detail page prints that person's free-text answers beside it. Naming nobody is not enough, so neither
     * page is offered: a notice and the totals.
     */
    public function test_a_protected_evaluation_offers_no_individual_responses(): void {
        global $DB, $PAGE;
        $PAGE->set_url('/local/sentientia_evaluation/responses.php');
        $named = $this->evaluation('Named', '/1');
        $anonymousflag = $this->evaluation('Anonymous', '/1', 1);
        // Named today, but one answer was collected anonymously (userid 0): protected for good.
        $sticky = $this->evaluation('Once anonymous', '/1');
        $this->response($sticky, 0);
        // Named, with one anonymous question.
        $withquestion = $this->evaluation('Anonymous question', '/1');
        $DB->insert_record('local_sentientia_evaluation_questions', (object) [
            'evaluationid' => $withquestion->id, 'questiontype' => 'text', 'questiontext' => 'Say anything',
            'required' => 0, 'anonymous' => 1, 'sortorder' => 1, 'timecreated' => time(),
        ]);

        $this->flag_on();
        $this->assertTrue(evaluation_manager::individual_responses_offered($named));
        foreach (['the anonymous flag' => $anonymousflag, 'an anonymous answer on record' => $sticky,
                'an anonymous question' => $withquestion] as $why => $evaluation) {
            $this->assertTrue(evaluation_manager::identity_protected($evaluation), $why);
            $this->assertTrue(evaluation_manager::response_drilldown_enabled($evaluation), 'the flag is ON for it');
            $this->assertFalse(evaluation_manager::individual_responses_offered($evaluation), $why);
        }

        // The notice says why and leads back to the totals; it carries nothing of any response.
        $html = evaluation_manager::individual_responses_protected_notice($anonymousflag);
        $this->assertStringContainsString(get_string('response_list_protected', 'local_sentientia_evaluation'), $html);
        $this->assertStringContainsString(get_string('response_list_back_to_totals', 'local_sentientia_evaluation'), $html);
        $this->assertStringContainsString('responses.php?id=' . $anonymousflag->id, $html);
        $this->assertStringContainsString('data-action="back-to-totals"', $html);
        $this->assertStringNotContainsString('response_detail.php', $html);
        $this->assertStringNotContainsString('response_list.php', $html);
        foreach (['response_list_protected', 'response_list_back_to_totals', 'responses_individual_protected_note'] as $key) {
            $this->assertTrue(get_string_manager()->string_exists($key, 'local_sentientia_evaluation'), $key);
            $this->assertNotSame('', get_string($key, 'local_sentientia_evaluation'));
        }
    }

    /**
     * Review round of 2026-10-07 (the link test used to set has_list_link by hand): responses.php takes the link from
     * evaluation_manager::individual_responses_link(), and this test reads what that returns and renders the template
     * with it, so a page that hard-coded the link, or a helper that ignored the flag or the protection, fails here.
     */
    public function test_the_individual_responses_link_is_built_from_the_flag_and_the_form(): void {
        global $CFG, $OUTPUT, $PAGE;

        // The page builds nothing of its own: it asks the manager, and holds no link key of its own.
        $source = (string) file_get_contents(__DIR__ . '/../responses.php');
        $this->assertStringContainsString('evaluation_manager::individual_responses_link($evaluation)', $source);
        foreach (['has_list_link', 'list_url', 'list_protected_note', 'response_drilldown_enabled'] as $key) {
            $this->assertStringNotContainsString($key, $source, "responses.php must not build '{$key}' itself");
        }

        $named = $this->evaluation('Named', '/1');
        $protected = $this->evaluation('Anonymous', '/1', 1);
        $url = $CFG->wwwroot . '/local/sentientia_evaluation/response_list.php?id=' . $named->id;

        $PAGE->set_url('/local/sentientia_evaluation/responses.php');
        $render = function (\stdClass $evaluation) use ($OUTPUT): string {
            return $OUTPUT->render_from_template('local_sentientia_evaluation/responses',
                evaluation_manager::individual_responses_link($evaluation) + [
                    'name' => 'Survey', 'description' => '', 'is_anonymous' => false, 'kirkpatrick_label' => '',
                    'evaluationid' => (int) $evaluation->id, 'total_responses' => 0, 'has_responses' => false,
                    'questions' => [], 'has_questions' => false, 'backurl' => '/b', 'export_url' => '/e',
                    'reset_url' => '/r', 'filter_action_url' => '/f', 'filter_date_from' => '', 'filter_date_to' => '',
                    'has_filter' => false,
                ]);
        };
        $link = get_string('responses_individual_link', 'local_sentientia_evaluation');
        $note = get_string('responses_individual_protected_note', 'local_sentientia_evaluation');

        // Flag OFF: no link and no note, for a named and for a protected evaluation alike.
        foreach ([$named, $protected] as $evaluation) {
            $context = evaluation_manager::individual_responses_link($evaluation);
            $this->assertFalse($context['has_list_link']);
            $this->assertSame('', $context['list_protected_note']);
            $html = $render($evaluation);
            $this->assertStringNotContainsString('response_list.php', $html);
            $this->assertStringNotContainsString($note, $html);
        }

        // Flag ON: a named evaluation draws the link, and where it leads is the helper's url.
        $this->flag_on();
        $context = evaluation_manager::individual_responses_link($named);
        $this->assertTrue($context['has_list_link']);
        $this->assertSame($url, $context['list_url']);
        $this->assertSame('', $context['list_protected_note']);
        $html = $render($named);
        $this->assertStringContainsString('response_list.php?id=' . $named->id, $html);
        $this->assertStringContainsString($link, $html);
        $this->assertStringNotContainsString($note, $html);

        // Flag ON, protected: no link, and the page says why.
        $context = evaluation_manager::individual_responses_link($protected);
        $this->assertFalse($context['has_list_link']);
        $this->assertSame($note, $context['list_protected_note']);
        $html = $render($protected);
        $this->assertStringNotContainsString('response_list.php', $html);
        $this->assertStringContainsString($note, $html);

        // The link follows the EVALUATION's tenant: OFF for tenant 177, the other tenant still draws it.
        $other = $this->evaluation('Elsewhere', '/177');
        $this->set_flag(177, false);
        $this->assertFalse(evaluation_manager::individual_responses_link($other)['has_list_link']);
        $this->assertTrue(evaluation_manager::individual_responses_link($named)['has_list_link']);
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
        $response = $this->response($evaluation, (int) $person->id);

        $named = evaluation_manager::response_detail_respondent($response, false);
        $this->assertSame(fullname($DB->get_record('user', ['id' => $person->id], '*', MUST_EXIST)), $named['user_name']);
        $this->assertNotSame('Sue Supervisor', $named['user_name'], 'the site\'s name format applies, not first + last');
        $this->assertSame($person->email, $named['user_email']);
        $this->assertSame('E-1042', $named['employee_id']);

        // The same person, named the same way, by the response list.
        $rows = evaluation_manager::response_list_rows($evaluation, false, false);
        $this->assertSame($named['user_name'], $rows[0]['user_name']);
        $this->assertSame('E-1042', $rows[0]['employee_id']);

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

    /**
     * Review round of 2026-10-07: open_employeeid belongs to the BizLMS schema. A customer schema without it must not
     * fail with a database error on either page: the respondent is shown without an employee id.
     */
    public function test_the_respondent_pages_do_not_fail_on_a_schema_without_the_bizlms_employee_id(): void {
        global $DB;
        $person = $this->getDataGenerator()->create_user(['firstname' => 'Una', 'lastname' => 'Customer']);
        $evaluation = $this->evaluation('Named', '/1');
        $response = $this->response($evaluation, (int) $person->id);

        $dbman = $DB->get_manager();
        $field = new \xmldb_field('open_employeeid');
        $this->assertTrue($dbman->field_exists('user', $field), 'the fixture added the BizLMS column');
        $dbman->drop_field(new \xmldb_table('user'), $field);
        try {
            $this->assertFalse($dbman->field_exists('user', $field));
            $named = evaluation_manager::response_detail_respondent($response, false);
            $this->assertSame(fullname($DB->get_record('user', ['id' => $person->id], '*', MUST_EXIST)), $named['user_name']);
            $this->assertSame($person->email, $named['user_email']);
            $this->assertSame('', $named['employee_id'], 'no column, no employee id');

            $rows = evaluation_manager::response_list_rows($evaluation, false, false);
            $this->assertCount(1, $rows);
            $this->assertSame($named['user_name'], $rows[0]['user_name']);
            $this->assertSame('', $rows[0]['employee_id']);
        } finally {
            // The column is a fixture of this test database, not part of core: put it back for the tests after this one.
            $this->ensure_bizlms_schema();
        }
        $this->assertTrue($dbman->field_exists('user', $field));
    }
}
