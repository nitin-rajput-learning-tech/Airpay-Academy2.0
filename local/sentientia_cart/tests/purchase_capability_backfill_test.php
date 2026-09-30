<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/sentientia_cart/db/upgradelib.php');
// Deliberately NO require_once of lib.php: cart_manager::add_item() (and so the web service) must
// price a line without it. Moodle loads a plugin's lib.php only for that plugin's callbacks, so a
// test that pre-included it hid a fatal ("Call to undefined function") that the web service hit.

/**
 * D1 (persona pass 2026-09-30): a real public (/77) or ZEEA (/177) learner can buy.
 *
 * Such a learner holds no system role except Authenticated user. db/access.php
 * has listed the `user` archetype for local/sentientia_cart:purchase since the
 * plugin was written, so the list did not change; the role still lacked the row
 * (most likely the capability was first registered outside update_capabilities(),
 * the only thing that applies archetype defaults). The role held :view (the cart
 * page and order history opened) and not :purchase (add-to-cart, remove and
 * checkout were all refused with nopermissions). Upgrade step 2026093001 and the
 * install hook call local_sentientia_cart_backfill_user_purchase().
 *
 * Owner decision 2026-09-30: buying stays gated by cart_manager::is_enabled_for_user()
 * (enabled_tenants) and the ADR-031 catalogue purchase gate
 * (cart_manager::can_buy_course()). The last tests hold that: the capability lets a
 * learner buy their own tenant's course and still refuses another tenant's.
 *
 * NOTE for the lead: the plugin version was bumped with this (2026093001, then 2026093002 in
 * review round 1), so PHPUnit must be re-initialised before it runs.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_sentientia_cart_backfill_user_purchase
 * @covers     \local_sentientia_cart\external\add_item
 * @covers     \local_sentientia_cart\cart_manager::can_buy_course
 * @covers     \local_sentientia_cart\cart_manager::get_course_price
 * @group      tenant_isolation
 */
final class purchase_capability_backfill_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    private const PURCHASE = 'local/sentientia_cart:purchase';
    private const VIEW = 'local/sentientia_cart:view';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /** The Authenticated user role. */
    private function user_roleid(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
    }

    /** Put the site in the state the persona pass found: the user role has no :purchase row. */
    private function drop_user_purchase(): int {
        $roleid = $this->user_roleid();
        unassign_capability(self::PURCHASE, $roleid, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        return $roleid;
    }

    /** @return int|null the stored permission at system context, or null when there is no row */
    private function permission(int $roleid, string $cap): ?int {
        global $DB;
        $value = $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid, 'capability' => $cap, 'contextid' => \context_system::instance()->id,
        ]);
        return $value === false ? null : (int) $value;
    }

    /** A user with the given open_path and NO role but the implicit Authenticated user. */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /** A course for sale: an enabled enrol_fee instance with a cost. */
    private function priced_course_at(string $path, float $price = 500.00): \stdClass {
        global $DB;
        $c = $this->getDataGenerator()->create_course(['visible' => 1]);
        $DB->set_field('course', 'open_path', $path, ['id' => $c->id]);
        $DB->insert_record('enrol', (object) [
            'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $c->id,
            'sortorder' => 9, 'cost' => number_format($price, 2, '.', ''), 'currency' => 'INR',
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('course', ['id' => $c->id], '*', MUST_EXIST);
    }

    // ── The back-fill ────────────────────────────────────────────────────

    public function test_the_authenticated_user_role_gets_purchase(): void {
        $roleid = $this->drop_user_purchase();
        $this->assertNull($this->permission($roleid, self::PURCHASE), 'Precondition: the persona-pass state.');

        $granted = local_sentientia_cart_backfill_user_purchase();

        $this->assertGreaterThanOrEqual(1, $granted);
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::PURCHASE));
    }

    public function test_a_real_public_learner_can_purchase_after_the_backfill_and_not_before(): void {
        $this->drop_user_purchase();
        $learner = $this->user_at('/77');
        $this->setUser($learner);
        $syscontext = \context_system::instance();

        $this->assertTrue(has_capability(self::VIEW, $syscontext),
            'The cart page and order history open for the user role today.');
        $this->assertFalse(has_capability(self::PURCHASE, $syscontext),
            'Before: add-to-cart and checkout are refused.');

        local_sentientia_cart_backfill_user_purchase();
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertTrue(has_capability(self::PURCHASE, $syscontext), 'After: the learner may buy.');
    }

    public function test_it_is_idempotent(): void {
        global $DB;
        $roleid = $this->drop_user_purchase();
        local_sentientia_cart_backfill_user_purchase();
        $rows = $DB->count_records('role_capabilities');

        $this->assertSame(0, local_sentientia_cart_backfill_user_purchase(), 'A second run has nothing to do.');
        $this->assertSame($rows, $DB->count_records('role_capabilities'));
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::PURCHASE));
    }

    public function test_an_administrators_prevent_or_prohibit_is_never_overridden(): void {
        $syscontext = \context_system::instance();
        $roleid = $this->drop_user_purchase();

        assign_capability(self::PURCHASE, CAP_PROHIBIT, $roleid, $syscontext->id, true);
        local_sentientia_cart_backfill_user_purchase();
        $this->assertSame(CAP_PROHIBIT, $this->permission($roleid, self::PURCHASE), 'A PROHIBIT stands.');

        assign_capability(self::PURCHASE, CAP_PREVENT, $roleid, $syscontext->id, true);
        local_sentientia_cart_backfill_user_purchase();
        $this->assertSame(CAP_PREVENT, $this->permission($roleid, self::PURCHASE), 'A PREVENT stands.');

        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->user_at('/77'));
        $this->assertFalse(has_capability(self::PURCHASE, $syscontext),
            'A learner is still refused where the administrator said so.');
    }

    public function test_an_existing_allow_is_left_alone(): void {
        $roleid = $this->user_roleid();
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::PURCHASE),
            'Precondition: a fresh install gives the user archetype :purchase from db/access.php.');

        $this->assertSame(0, local_sentientia_cart_backfill_user_purchase());
        $this->assertSame(CAP_ALLOW, $this->permission($roleid, self::PURCHASE));
    }

    public function test_other_roles_and_the_other_cart_capabilities_are_left_alone(): void {
        $this->drop_user_purchase();
        $student = create_role('Cart test student', 'carttststudent', '', 'student');
        $plain = create_role('Cart test plain', 'carttstplain', '', '');
        $trainer = create_role('Cart test trainer', 'carttsttrainer', '', 'teacher');
        foreach ([$student, $plain, $trainer] as $roleid) {
            unassign_capability(self::PURCHASE, $roleid, \context_system::instance()->id);
        }

        local_sentientia_cart_backfill_user_purchase();

        foreach ([$student, $plain, $trainer] as $roleid) {
            $this->assertNull($this->permission($roleid, self::PURCHASE),
                'Only the authenticated-user role is back-filled (owner decision 2026-09-30).');
        }
        $userrole = $this->user_roleid();
        foreach (['viewallorders', 'refund', 'manageprices'] as $cap) {
            $this->assertNotSame(CAP_ALLOW, $this->permission($userrole, 'local/sentientia_cart:' . $cap),
                "The user role must not receive :$cap.");
        }
    }

    public function test_the_guest_role_gains_no_purchase_row(): void {
        global $DB;
        $this->drop_user_purchase();
        local_sentientia_cart_backfill_user_purchase();

        // Asserted on the role's own rows: has_capability() denies every write capability to the
        // guest user whatever the roles say, so a has_capability() check would pass even if the
        // back-fill had wrongly granted the guest role.
        $guestroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        $this->assertFalse($DB->record_exists('role_capabilities',
            ['roleid' => $guestroleid, 'capability' => self::PURCHASE]),
            'The guest role has no :purchase row after the back-fill, at any context.');
        $this->assertNull($this->permission($guestroleid, self::PURCHASE));
    }

    public function test_the_role_moodle_calls_the_default_user_role_is_back_filled_even_if_renamed(): void {
        // A restored BizLMS database can rename Authenticated user and clear its archetype; Moodle
        // still identifies it by $CFG->defaultuserroleid.
        $this->drop_user_purchase();
        $renamed = create_role('Renamed authenticated', 'renamedauth', '', '');
        $other = create_role('Some other custom role', 'someothercustom', '', '');
        $syscontextid = \context_system::instance()->id;
        unassign_capability(self::PURCHASE, $renamed, $syscontextid);
        unassign_capability(self::PURCHASE, $other, $syscontextid);
        set_config('defaultuserroleid', $renamed);

        local_sentientia_cart_backfill_user_purchase();

        $this->assertSame(CAP_ALLOW, $this->permission($renamed, self::PURCHASE),
            'The role the site treats as Authenticated user gets the grant.');
        $this->assertNull($this->permission($other, self::PURCHASE),
            'A custom role that is not the default user role is still left alone.');
    }

    public function test_an_administrators_prohibit_on_the_default_user_role_stands(): void {
        $renamed = create_role('Renamed authenticated', 'renamedauth', '', '');
        $syscontextid = \context_system::instance()->id;
        assign_capability(self::PURCHASE, CAP_PROHIBIT, $renamed, $syscontextid, true);
        set_config('defaultuserroleid', $renamed);

        local_sentientia_cart_backfill_user_purchase();

        $this->assertSame(CAP_PROHIBIT, $this->permission($renamed, self::PURCHASE));
    }

    // ── Buying is still gated, not widened ───────────────────────────────

    public function test_a_public_learner_can_add_their_own_tenants_course_through_the_web_service(): void {
        $this->drop_user_purchase();
        local_sentientia_cart_backfill_user_purchase();
        accesslib_clear_all_caches_for_unit_testing();
        $learner = $this->user_at('/77');
        $mine = $this->priced_course_at('/77');
        $this->setUser($learner);

        $result = external\add_item::execute((int) $mine->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['item_count']);
        $this->assertEqualsWithDelta(500.00, $result['subtotal'], 0.001);
    }

    public function test_a_public_learner_who_holds_purchase_is_still_refused_another_tenants_course(): void {
        $this->drop_user_purchase();
        local_sentientia_cart_backfill_user_purchase();
        accesslib_clear_all_caches_for_unit_testing();
        $learner = $this->user_at('/77');
        $internal = $this->priced_course_at('/1');
        $zeea = $this->priced_course_at('/177');
        $this->setUser($learner);

        foreach ([$internal, $zeea] as $theirs) {
            try {
                external\add_item::execute((int) $theirs->id);
                $this->fail('The capability must not let a /77 learner put another tenant\'s course in the cart.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_courseunavailable', $e->errorcode);
            }
        }
        $cart = cart_manager::get_or_open_cart((int) $learner->id);
        $this->assertSame([], json_decode($cart->items_json ?: '[]', true) ?: [],
            'Nothing from another tenant reached the cart.');
    }

    public function test_a_learner_in_a_tenant_the_cart_is_off_for_is_still_refused(): void {
        $this->drop_user_purchase();
        local_sentientia_cart_backfill_user_purchase();
        accesslib_clear_all_caches_for_unit_testing();
        set_config('enabled_tenants', '1,177', 'local_sentientia_cart');
        $learner = $this->user_at('/77');
        $mine = $this->priced_course_at('/77');
        $this->setUser($learner);

        $this->assertFalse(cart_manager::is_enabled_for_user($learner));
        try {
            external\add_item::execute((int) $mine->id);
            $this->fail('enabled_tenants still gates the cart per tenant.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_courseunavailable', $e->errorcode);
        }
    }

    public function test_without_the_backfill_the_learner_is_refused_by_capability(): void {
        $this->drop_user_purchase();
        $learner = $this->user_at('/77');
        $mine = $this->priced_course_at('/77');
        $this->setUser($learner);

        $this->expectException(\required_capability_exception::class);
        external\add_item::execute((int) $mine->id);
    }

    // ── Wiring ───────────────────────────────────────────────────────────

    public function test_add_item_prices_a_line_without_lib_php_being_loaded(): void {
        // The web service local_sentientia_cart_add_item reaches cart_manager through the autoloader
        // alone. A run inside one PHPUnit process cannot prove "lib.php not loaded" (any earlier
        // callback lookup includes it), so hold the source: add_item() must price through the class.
        $method = new \ReflectionMethod(cart_manager::class, 'add_item');
        $lines = file($method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringContainsString('self::get_course_price(', $body);
        $this->assertStringNotContainsString('local_sentientia_cart_get_course_price(', $body);
    }

    public function test_the_price_lookup_is_one_thing_through_the_class_and_the_lib_wrapper(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/sentientia_cart/lib.php');   // Here on purpose: it tests the wrapper.
        $priced = $this->priced_course_at('/77', 420.00);
        $free = $this->getDataGenerator()->create_course(['visible' => 1]);

        $this->assertEqualsWithDelta(420.00, cart_manager::get_course_price((int) $priced->id), 0.001);
        $this->assertEqualsWithDelta(420.00, \local_sentientia_cart_get_course_price((int) $priced->id), 0.001);
        $this->assertNull(cart_manager::get_course_price((int) $free->id), 'No enrol_fee instance: not for sale.');
        $this->assertNull(\local_sentientia_cart_get_course_price((int) $free->id));
    }

    public function test_a_disabled_fee_instance_or_a_zero_cost_is_not_a_price(): void {
        global $DB;
        $course = $this->priced_course_at('/77', 300.00);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['courseid' => $course->id, 'enrol' => 'fee']);
        $this->assertNull(cart_manager::get_course_price((int) $course->id), 'A disabled instance is not for sale.');

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'fee']);
        $DB->set_field('enrol', 'cost', '0.00', ['courseid' => $course->id, 'enrol' => 'fee']);
        $this->assertNull(cart_manager::get_course_price((int) $course->id), 'A zero cost is free.');
    }

    public function test_access_php_lists_the_user_archetype_for_a_fresh_install(): void {
        global $CFG;
        $capabilities = [];
        include($CFG->dirroot . '/local/sentientia_cart/db/access.php');

        $this->assertSame(CAP_ALLOW, $capabilities[self::PURCHASE]['archetypes']['user'] ?? null);
        foreach (['viewallorders', 'refund', 'manageprices'] as $cap) {
            $this->assertArrayNotHasKey('user',
                $capabilities['local/sentientia_cart:' . $cap]['archetypes'] ?? [],
                ":$cap must not go to the user archetype.");
        }
    }

    public function test_the_upgrade_step_the_install_hook_and_the_version_are_wired(): void {
        global $CFG;
        $upgrade = file_get_contents($CFG->dirroot . '/local/sentientia_cart/db/upgrade.php');
        $this->assertStringContainsString('if ($oldversion < 2026093001)', $upgrade);
        $this->assertStringContainsString('local_sentientia_cart_backfill_user_purchase()', $upgrade);
        $this->assertStringContainsString("upgrade_plugin_savepoint(true, 2026093001, 'local', 'sentientia_cart')",
            $upgrade);
        // Review round 1: the helper grew the default-user-role lookup, so it runs once more on any site
        // that already took 2026093001.
        $this->assertStringContainsString('if ($oldversion < 2026093002)', $upgrade);
        $this->assertStringContainsString("upgrade_plugin_savepoint(true, 2026093002, 'local', 'sentientia_cart')",
            $upgrade);

        $install = file_get_contents($CFG->dirroot . '/local/sentientia_cart/db/install.php');
        $this->assertStringContainsString('local_sentientia_cart_backfill_user_purchase()', $install);

        // access.php must not point at a function that does not exist.
        $access = file_get_contents($CFG->dirroot . '/local/sentientia_cart/db/access.php');
        $this->assertStringNotContainsString('local_sentientia_cart_after_install', $access);
        $this->assertStringContainsString('xmldb_local_sentientia_cart_install()', $access);
        $this->assertStringContainsString('function xmldb_local_sentientia_cart_install', $install);

        $plugin = new \stdClass();
        include($CFG->dirroot . '/local/sentientia_cart/version.php');
        $this->assertGreaterThanOrEqual(2026093002, $plugin->version);
    }
}
