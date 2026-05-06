<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD tests for user_manager (create / update / delete / suspend).
 *
 * The dynamic_form (edit_user.php) calls these methods on submission, so
 * testing the manager directly covers the create+edit modal flows without
 * having to render the form.
 *
 * Locks in:
 * - create() validates required fields, rejects duplicate username + email
 * - create() applies open_* custom fields including open_path
 * - update() persists changes, rejects duplicate-email-on-update
 * - update() throws if userid doesn't exist
 * - delete() blocks self-delete and system-user delete
 * - delete() actually marks deleted=1
 * - suspend() flips state, persists to DB
 *
 * @package    local_airpay_users
 * @category   test
 */
final class user_manager_test extends \advanced_testcase {

    /**
     * Build a minimal valid create-data object.
     */
    private function valid_create_data(): \stdClass {
        $unique = uniqid();
        return (object) [
            'username'  => 'testu_' . $unique,
            'email'     => 'testu_' . $unique . '@airpay.test',
            'firstname' => 'Test',
            'lastname'  => 'User',
            'auth'      => 'manual',
            'password'  => 'Airpay@Test2026!',
        ];
    }

    /**
     * create() with valid data returns positive userid and persists record.
     */
    public function test_create_with_valid_data_succeeds(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $data = $this->valid_create_data();
        $userid = user_manager::create($data);

        $this->assertGreaterThan(2, $userid);
        $this->assertTrue($DB->record_exists('user', ['id' => $userid, 'deleted' => 0]));

        $created = $DB->get_record('user', ['id' => $userid]);
        $this->assertSame($data->firstname, $created->firstname);
        $this->assertSame($data->email, $created->email);
        $this->assertSame(strtolower($data->username), $created->username);
        $this->assertEquals(1, (int) $created->confirmed);
    }

    /**
     * create() with missing username throws missingrequiredfields.
     */
    public function test_create_missing_username_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->valid_create_data();
        $data->username = '';

        try {
            user_manager::create($data);
            $this->fail('Expected moodle_exception with errorcode missingrequiredfields');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingrequiredfields', $e->errorcode);
        }
    }

    /**
     * create() with missing email throws missingrequiredfields.
     */
    public function test_create_missing_email_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->valid_create_data();
        $data->email = '';

        try {
            user_manager::create($data);
            $this->fail('Expected moodle_exception with errorcode missingrequiredfields');
        } catch (\moodle_exception $e) {
            $this->assertSame('missingrequiredfields', $e->errorcode);
        }
    }

    /**
     * create() with duplicate username throws usernametaken.
     */
    public function test_create_duplicate_username_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->valid_create_data();
        user_manager::create($data);

        // Try to create another with same username (different email).
        $data2 = $this->valid_create_data();
        $data2->username = $data->username;

        try {
            user_manager::create($data2);
            $this->fail('Expected moodle_exception with errorcode usernametaken');
        } catch (\moodle_exception $e) {
            $this->assertSame('usernametaken', $e->errorcode);
        }
    }

    /**
     * create() with duplicate email throws emailtaken.
     */
    public function test_create_duplicate_email_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->valid_create_data();
        user_manager::create($data);

        // Different username, same email.
        $data2 = $this->valid_create_data();
        $data2->email = $data->email;

        try {
            user_manager::create($data2);
            $this->fail('Expected moodle_exception with errorcode emailtaken');
        } catch (\moodle_exception $e) {
            $this->assertSame('emailtaken', $e->errorcode);
        }
    }

    /**
     * create() with open_path applies the path to the user record.
     */
    public function test_create_applies_open_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $data = $this->valid_create_data();
        $data->open_path = '/1/2/3';
        $data->open_employeeid = 'EMP-1234';
        $data->open_designation = 'Engineer';

        $userid = user_manager::create($data);

        $u = $DB->get_record('user', ['id' => $userid]);
        $this->assertSame('/1/2/3',     $u->open_path);
        $this->assertSame('EMP-1234',   $u->open_employeeid);
        $this->assertSame('Engineer',   $u->open_designation);
    }

    /**
     * update() persists changed firstname.
     */
    public function test_update_persists_firstname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $u = $this->getDataGenerator()->create_user(['firstname' => 'Old']);

        $changes = (object) ['firstname' => 'New'];
        $ok = user_manager::update($u->id, $changes);

        $this->assertTrue($ok);
        $reloaded = $DB->get_record('user', ['id' => $u->id]);
        $this->assertSame('New', $reloaded->firstname);
    }

    /**
     * update() with email already taken by another user throws emailtaken.
     */
    public function test_update_duplicate_email_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $u1 = $this->getDataGenerator()->create_user(['email' => 'first@airpay.test']);
        $u2 = $this->getDataGenerator()->create_user(['email' => 'second@airpay.test']);

        $changes = (object) ['email' => 'first@airpay.test'];
        try {
            user_manager::update($u2->id, $changes);
            $this->fail('Expected moodle_exception with errorcode emailtaken');
        } catch (\moodle_exception $e) {
            $this->assertSame('emailtaken', $e->errorcode);
        }
    }

    /**
     * update() on a non-existent userid throws (MUST_EXIST in get_record).
     */
    public function test_update_nonexistent_userid_throws(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        user_manager::update(99999999, (object) ['firstname' => 'X']);
    }

    /**
     * delete() refuses to delete the calling user (cannotdeleteself).
     * SECURITY: prevents an admin from accidentally locking themselves out.
     */
    public function test_delete_self_throws(): void {
        $this->resetAfterTest();
        $u = $this->getDataGenerator()->create_user();
        $this->setUser($u);

        try {
            user_manager::delete($u->id);
            $this->fail('Expected moodle_exception with errorcode cannotdeleteself');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotdeleteself', $e->errorcode);
        }
    }

    /**
     * delete() refuses to delete system users (id ≤ 2: guest=1, admin=2).
     * SECURITY: prevents accidental admin deletion which would break the site.
     */
    public function test_delete_system_user_throws(): void {
        $this->resetAfterTest();
        // The user_manager::delete() checks self-delete BEFORE system-user delete,
        // so to reach the system-user-delete branch we have to be a non-system
        // user attempting to delete one. Use a fresh non-admin caller.
        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        try {
            user_manager::delete(1);   // guest user — id <= 2, NOT the caller
            $this->fail('Expected moodle_exception with errorcode cannotdeletesystemuser');
        } catch (\moodle_exception $e) {
            $this->assertSame('cannotdeletesystemuser', $e->errorcode);
        }
    }

    /**
     * delete() on a real user marks deleted=1 in DB.
     */
    public function test_delete_marks_deleted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $this->assertEquals(0, (int) $DB->get_field('user', 'deleted', ['id' => $u->id]));

        $ok = user_manager::delete($u->id);
        $this->assertTrue($ok);

        $this->assertEquals(1, (int) $DB->get_field('user', 'deleted', ['id' => $u->id]));
    }

    /**
     * suspend() flips suspended state when no explicit value passed.
     */
    public function test_suspend_toggles_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $u = $this->getDataGenerator()->create_user(['suspended' => 0]);

        $newstate = user_manager::suspend($u->id);
        $this->assertTrue($newstate);
        $this->assertEquals(1, (int) $DB->get_field('user', 'suspended', ['id' => $u->id]));

        $newstate2 = user_manager::suspend($u->id);
        $this->assertFalse($newstate2);
        $this->assertEquals(0, (int) $DB->get_field('user', 'suspended', ['id' => $u->id]));
    }

    /**
     * suspend() with explicit true suspends; explicit false activates.
     */
    public function test_suspend_explicit_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        global $DB;
        $u = $this->getDataGenerator()->create_user(['suspended' => 0]);

        user_manager::suspend($u->id, true);
        $this->assertEquals(1, (int) $DB->get_field('user', 'suspended', ['id' => $u->id]));

        user_manager::suspend($u->id, false);
        $this->assertEquals(0, (int) $DB->get_field('user', 'suspended', ['id' => $u->id]));
    }
}
