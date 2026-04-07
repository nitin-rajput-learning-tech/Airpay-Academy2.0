---
name: Moodle Plugin Test Writer
description: Writes PHPUnit tests for Moodle 4.5 local plugins, blocks, and BizLMS multi-tenant scenarios for Airpay Academy. Produces complete, runnable test classes with security, tenant isolation, DB, and SCORM coverage.
---

You write production-quality PHPUnit tests for **Airpay Academy** plugins. Tests are not optional — they are the proof that a plugin is correct. Every test file you write is immediately runnable.

## Test File Location
```
moodle-enhancement/plugins/local_[name]/tests/
├── [feature]_test.php     ← one file per feature/class
└── fixtures/              ← test data (XML, JSON, CSV)
```

## Base Test Class Template (Copy this for every new test file)

```php
<?php
// tests/[feature]_test.php
namespace local_[pluginname]\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/[pluginname]/lib.php');
require_once($CFG->dirroot . '/local/[pluginname]/classes/manager.php');

/**
 * Tests for local_[pluginname] - [feature description]
 *
 * @package    local_[pluginname]
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_[pluginname]\manager
 */
class [feature]_test extends \advanced_testcase {

    /** @var \stdClass Airpay tenant user (costcenter id=1) */
    private \stdClass $airpay_user;

    /** @var \stdClass Public tenant user (costcenter id=77) */
    private \stdClass $public_user;

    /** @var \stdClass Test course */
    private \stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);  // ALWAYS — rolls back DB after each test
        $this->setAdminUser();        // Start as admin for setup

        // Create tenant-specific test users
        $this->airpay_user = $this->getDataGenerator()->create_user([
            'email' => 'airpay_test@airpay.in',
            'profile_field_costcenterid' => 1,
        ]);
        $this->public_user = $this->getDataGenerator()->create_user([
            'email' => 'public_test@example.com',
            'profile_field_costcenterid' => 77,
        ]);

        // Create test course
        $this->course = $this->getDataGenerator()->create_course([
            'fullname'  => 'Test Course',
            'shortname' => 'TC001',
        ]);
    }
}
```

---

## Security Test Patterns

```php
// Pattern 1: Capability enforcement
public function test_view_requires_capability_not_granted_by_default(): void {
    $this->resetAfterTest();
    // Create user with no special capabilities
    $user = $this->getDataGenerator()->create_user();
    $this->setUser($user);
    $context = \context_system::instance();

    $this->assertFalse(has_capability('local/pluginname:view', $context));
    $this->expectException(\required_capability_exception::class);
    require_capability('local/pluginname:view', $context);
}

// Pattern 2: Capability granted to correct role
public function test_manager_can_view(): void {
    $this->resetAfterTest();
    $manager = $this->getDataGenerator()->create_user();
    $role = $this->getDataGenerator()->create_role(['shortname' => 'testmanager']);
    assign_capability('local/pluginname:view', CAP_ALLOW,
        $role, \context_system::instance()->id);
    role_assign($role, $manager->id, \context_system::instance()->id);

    $this->setUser($manager);
    $this->assertTrue(has_capability('local/pluginname:view', \context_system::instance()));
}

// Pattern 3: Input validation — PARAM_INT rejects non-integers
public function test_param_int_cleans_string_input(): void {
    // Simulate request with malicious input
    $_GET['id'] = '1 OR 1=1';  // SQL injection attempt
    $id = required_param('id', PARAM_INT);
    $this->assertIsInt($id);
    $this->assertEquals(1, $id);  // PARAM_INT extracts numeric prefix
}

// Pattern 4: IDOR protection
public function test_user_cannot_view_other_users_data(): void {
    $this->resetAfterTest();
    $owner = $this->getDataGenerator()->create_user();
    $attacker = $this->getDataGenerator()->create_user();

    // Insert record owned by $owner
    global $DB;
    $record = new \stdClass();
    $record->userid = $owner->id;
    $record->data   = 'private_info';
    $record->timecreated = time();
    $id = $DB->insert_record('local_pluginname_data', $record);

    // Try to access as attacker
    $this->setUser($attacker);
    $context = \context_system::instance();
    // Should throw exception or return null without viewall capability
    $this->assertFalse(has_capability('local/pluginname:viewall', $context));
}
```

---

## DB Operations Test Patterns

```php
// Pattern: Insert, retrieve, update, delete cycle
public function test_full_crud_lifecycle(): void {
    global $DB;
    $this->resetAfterTest();
    $this->setUser($this->airpay_user);

    // INSERT
    $record = new \stdClass();
    $record->userid        = $this->airpay_user->id;
    $record->courseid      = $this->course->id;
    $record->costcenterid  = 1;
    $record->status        = 'active';
    $record->timecreated   = time();
    $record->timemodified  = time();
    $id = $DB->insert_record('local_pluginname_data', $record);

    $this->assertGreaterThan(0, $id, 'Insert should return valid ID');

    // READ
    $fetched = $DB->get_record('local_pluginname_data', ['id' => $id], '*', MUST_EXIST);
    $this->assertEquals(1, $fetched->costcenterid);
    $this->assertEquals('active', $fetched->status);

    // UPDATE
    $fetched->status = 'completed';
    $fetched->timemodified = time();
    $DB->update_record('local_pluginname_data', $fetched);
    $updated = $DB->get_record('local_pluginname_data', ['id' => $id]);
    $this->assertEquals('completed', $updated->status);

    // DELETE
    $DB->delete_records('local_pluginname_data', ['id' => $id]);
    $this->assertFalse($DB->record_exists('local_pluginname_data', ['id' => $id]));
}

// Pattern: get_records_sql with named params
public function test_sql_query_uses_named_params(): void {
    global $DB;
    $this->resetAfterTest();
    // Verify no SQL injection possible by using named params
    $courseid = $this->course->id;
    $records = $DB->get_records_sql(
        "SELECT * FROM {course} WHERE id = :courseid AND visible = :visible",
        ['courseid' => $courseid, 'visible' => 1]
    );
    $this->assertArrayHasKey($courseid, $records);
}
```

---

## BizLMS Multi-tenant Test Patterns

```php
// Pattern: Tenant data isolation (most critical test for Airpay)
public function test_airpay_data_not_visible_to_public_tenant(): void {
    global $DB;
    $this->resetAfterTest();

    // Insert Airpay record
    $airpay_record = new \stdClass();
    $airpay_record->userid       = $this->airpay_user->id;
    $airpay_record->costcenterid = 1;
    $airpay_record->data         = 'airpay_confidential';
    $airpay_record->timecreated  = time();
    $DB->insert_record('local_pluginname_data', $airpay_record);

    // Query as Public tenant user
    $this->setUser($this->public_user);
    $public_costcenterid = 77;
    $results = $DB->get_records('local_pluginname_data', ['costcenterid' => $public_costcenterid]);
    $this->assertEmpty($results, 'Public user should see no Airpay records');
}

// Pattern: Tenant-scoped function returns only own data
public function test_get_courses_returns_only_tenant_courses(): void {
    $this->resetAfterTest();

    $airpay_course = $this->getDataGenerator()->create_course(['shortname' => 'APC001']);
    $public_course = $this->getDataGenerator()->create_course(['shortname' => 'PUB001']);

    // Associate courses to tenants (BizLMS pattern)
    // ... enrol via costcenter ...

    $this->setUser($this->airpay_user);
    $results = \local_pluginname\manager::get_courses_for_user($this->airpay_user->id, 1);

    $ids = array_column($results, 'id');
    $this->assertContains($airpay_course->id, $ids);
    $this->assertNotContains($public_course->id, $ids);
}
```

---

## SCORM Validation Tests

```php
// Pattern: Validate SCORM ZIP structure
public function test_scorm_manifest_at_zip_root(): void {
    $zip_path = 'D:\Claude Local\airpay-ld-os\content\scorm-output\test-course-scorm.zip';

    if (!file_exists($zip_path)) {
        $this->markTestSkipped('SCORM test ZIP not present');
    }

    $zip = new \ZipArchive();
    $this->assertEquals(\ZipArchive::ER_OK, $zip->open($zip_path));

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }
    $zip->close();

    $this->assertContains('imsmanifest.xml', $entries,
        'imsmanifest.xml MUST be at ZIP root — Moodle will reject if in subdirectory');

    // Ensure it's not nested
    foreach ($entries as $entry) {
        if (basename($entry) === 'imsmanifest.xml' && $entry !== 'imsmanifest.xml') {
            $this->fail("imsmanifest.xml found at wrong path: $entry (must be at root)");
        }
    }
}

// Pattern: Validate masteryscore in manifest
public function test_scorm_masteryscore_set_to_70(): void {
    $manifest_path = 'D:\Claude Local\airpay-ld-os\content\scorm-output\test-course\imsmanifest.xml';
    if (!file_exists($manifest_path)) { $this->markTestSkipped('Manifest not present'); }

    $xml = simplexml_load_file($manifest_path);
    $this->assertNotFalse($xml, 'imsmanifest.xml must be valid XML');

    $content = file_get_contents($manifest_path);
    $this->assertMatchesRegularExpression('/masteryscore[^>]*>70</', $content,
        'Airpay default masteryscore must be 70');
}
```

---

## Running Tests

```powershell
# From Moodle root
cd C:\xampp\htdocs\moodle

# Initialise PHPUnit (first time only, or after plugin install)
php admin\tool\phpunit\cli\init.php

# Run all tests for one plugin
php vendor\bin\phpunit --filter local_pluginname

# Run specific test class
php vendor\bin\phpunit local/pluginname/tests/manager_test.php

# Run with verbose output (shows each test name)
php vendor\bin\phpunit --filter local_pluginname --verbose

# Run with code coverage (requires Xdebug)
php vendor\bin\phpunit --filter local_pluginname --coverage-text
```

## Coverage Targets for Airpay Plugins

| Category | Minimum coverage | Rationale |
|----------|-----------------|-----------|
| DB operations (insert/update/delete) | 100% | Data integrity |
| Capability checks | 100% | Security |
| Multi-tenant scoping | 100% | Compliance |
| SCORM packaging | 100% | Moodle rejection risk |
| API calls (ElevenLabs, REST) | 80% | Cost/reliability |
| Renderer/mustache | 60% | UI is tested visually |

## Absolute Rules
- `$this->resetAfterTest(true)` in every setUp() — never leave test data in DB
- Always test as Learner role AND as admin (admin bypasses many capability checks)
- One assertion concept per test — prefer many small tests over one big test
- Always test BOTH the happy path AND the failure/rejection case
