# PHPUnit Runbook — Airpay Academy

How to run the security regression test suite on a fresh Moodle install.

## What's covered

The `tests/` directory under each plugin holds regression tests for every
security finding in the May 5 2026 audit. They lock in the contract so a
future refactor can't silently re-introduce one of the 6 fixes.

| Plugin | File | Locks in |
|--------|------|----------|
| airpay_users | `tests/external/list_users_test.php` | C2 LIKE escape, H1 orgid ownership, M2 JSON bounds, sort whitelist, default tenant scope |
| airpay_users | `tests/external/bulk_action_test.php` | C1 cross-tenant scope, M1 enumeration oracle, self/admin/guest protection, capability check |
| airpay_courses | `tests/external/list_courses_test.php` | M3 tenant scope, C2 LIKE escape, M2 JSON bounds |
| airpay_org | `tests/org_manager_test.php` | C2 in `count_users` / `count_descendants`, tenant deletion refusal, descendant + user blockers, transactional delete |
| airpay_org | `tests/external/delete_org_test.php` | H3 tenant scope on delete |
| airpay_reports | `tests/external/delete_report_test.php` | H3 tenant scope on delete; siteadmin-only "all orgs" reports cannot be deleted by managers |

## One-time setup (any machine that wants to run the suite)

### 1. Composer install (~5 min)

```powershell
# Install composer if not present.
# https://getcomposer.org/installer

cd C:\xampp\htdocs\moodle5
php composer.phar install --no-progress
```

This pulls `phpunit/phpunit ^11`, `vfsstream`, etc. into `vendor/`.

### 2. PHPUnit config (already in `config.php`)

The local config already has these lines added:

```php
$CFG->phpunit_dataroot = 'C:\\xampp\\moodledata_phpu';
$CFG->phpunit_prefix   = 'phpu_';
$CFG->phpunit_directorypermissions = 0777;
```

**Production config.php should NOT include these.** They tell Moodle to use a
separate dataroot and DB prefix for tests so the live data is never touched.

### 3. Bootstrap the PHPUnit DB

```powershell
# Make sure php is on PATH first — the init script shells out to `php`.
$env:Path += ";C:\xampp\php"

cd C:\xampp\htdocs\moodle5
php public\admin\tool\phpunit\cli\init.php
```

This creates the `phpu_` prefixed tables and seeds the test DB.
**Re-run this any time you add a new plugin or change `db/install.xml`.**

## Running the tests

```powershell
cd C:\xampp\htdocs\moodle5

# Run a single test class:
vendor\bin\phpunit public\local\airpay_users\tests\external\list_users_test.php

# Run all airpay tests:
vendor\bin\phpunit --testsuite local_airpay_users
vendor\bin\phpunit --testsuite local_airpay_org
vendor\bin\phpunit --testsuite local_airpay_courses
vendor\bin\phpunit --testsuite local_airpay_reports

# Or by directory pattern:
vendor\bin\phpunit public\local\airpay_*\tests\
```

## Expected outcome

```
PHPUnit ... by Sebastian Bergmann and contributors.

..................                       (18/18)

OK (18 tests, ~80 assertions)
```

Each test method exercises one specific aspect of the security contract.
A red test means a regression — the underlying fix has been removed or weakened.

## Troubleshooting

### "Test environment not initialised"
Re-run `php admin/tool/phpunit/cli/init.php`.

### "Class not found: local_airpay_users\external\list_users"
Run the upgrade so plugin classes are registered:
```powershell
php public\admin\cli\upgrade.php --non-interactive
```

### "'php' is not recognized"
The init script shells out to `php`. Add XAMPP's PHP to PATH:
```powershell
$env:Path += ";C:\xampp\php"
# Or permanently via System Properties → Environment Variables.
```

### Tests are slow
Each test resets the DB via `$this->resetAfterTest()`. This is by design —
each test must be isolated. If a single test class takes > 30s, look for
unnecessary user/course generation in setUp.

## CI integration (future)

The same suite can run in CI via:

```yaml
- name: Bootstrap PHPUnit
  run: |
    php composer.phar install --no-dev=false
    php public/admin/tool/phpunit/cli/init.php

- name: Run airpay test suites
  run: vendor/bin/phpunit --testsuite local_airpay_users,local_airpay_org,local_airpay_courses,local_airpay_reports
```

CLAUDE.md Phase 6B item: PHPUnit coverage on security paths. This runbook closes that gap.
