# theme_airpayux — PHPUnit test suite runbook

Two structural-invariant test suites land here (per ADR-009):

  - **`role_detector_test`** — covers the shared role-detection helper
    (`\theme_airpayux\role_detector`). Codifies the 5-tier matrix that
    `layout/dashboard.php` and `classes/sidebar_navigation.php` both
    consume. Bug #11 happened because two duplicated detection paths
    drifted; this test prevents that recurrence.
  - **`ws_contract_test`** — walks every mustache template containing
    `data-region="airpay-datatable"`, looks up the named external
    function via `db/services.php`, and asserts it declares the full
    client contract `{search, sort, sortdir, page, perpage, filters}`.
    Bug #6 + #10 were 5 sibling endpoints missing `search`; this test
    means a new datatable consumer that forgets a key fails CI.

## Running locally (XAMPP — Moodle 5.1.3+)

### One-time PHPUnit init

```powershell
cd C:\xampp\htdocs\moodle5
php public/admin/tool/phpunit/cli/init.php
```

Init takes ~5 min the first time (creates a separate test DB,
installs every plugin, populates the test data dictionary). Re-run
when the Moodle codebase changes version (the `util.php --diag` error
"environment was initialised for different version" will tell you).

### Running the test suites

```powershell
cd C:\xampp\htdocs\moodle5

# role_detector — 8 tests, ~9 sec on local hardware
vendor/bin/phpunit.bat --group role_detector ^
    public/theme/airpayux/tests/role_detector_test.php

# ws_contract — 1 test (walks every datatable consumer), ~1 sec
vendor/bin/phpunit.bat --group ws_contract ^
    public/theme/airpayux/tests/ws_contract_test.php

# Both at once
vendor/bin/phpunit.bat --group role_detector,ws_contract ^
    public/theme/airpayux/tests/role_detector_test.php ^
    public/theme/airpayux/tests/ws_contract_test.php
```

### Expected results

  - `role_detector_test`: **8 tests, 17 assertions, 4 skipped**.
    Skips are intentional — they cover paths that require BizLMS
    schema (e.g., `local/airpay_courses:manage` capability,
    `open_supervisorid` column, `employee` role) which a vanilla
    Moodle test environment doesn't have. On a BizLMS-installed
    Moodle they all run.
  - `ws_contract_test`: **1 test, 2 assertions**. Walks every
    consumer once; passes if all declare the full contract; fails
    with the exact endpoint + missing keys if not.

### CLI audit (separate from CI — for forensic investigation)

```powershell
cd C:\xampp\htdocs\moodle5
php public/theme/airpayux/cli/ws_contract_audit.php          # human-readable
php public/theme/airpayux/cli/ws_contract_audit.php --json   # CI-pipeable
php public/theme/airpayux/cli/ws_contract_audit.php --help   # usage
```

The CLI consumes the same `\theme_airpayux\ws_contract_scanner` utility
as the PHPUnit suite, so it cannot drift from the CI gate.

## Adding new tests

  - Tests use `\advanced_testcase` (Moodle's base class with
    `resetAfterTest()` and `getDataGenerator()`).
  - Use `@group theme_airpayux` plus a specific group annotation
    (`role_detector`, `ws_contract`) so suites can be run selectively.
  - Skip tests that need BizLMS-specific schema with
    `markTestSkipped(...)` — DO NOT just `assertTrue(true)` because
    Moodle's `beStrictAboutTestsThatDoNotTestAnything` will fail.
  - Test fixtures are created per method (resetAfterTest) so the
    suite doesn't depend on production data.

## Production-deploy runbook for IT

These PHPUnit suites are **local-dev / CI tests**, not production
artifacts. They never run on the live `airpay.academy` server. The
adoption decision for IT is:

  1. Add a Jenkins / GitHub Actions job that runs:
     `cd $MOODLE_ROOT && vendor/bin/phpunit --group role_detector,ws_contract <test-paths>`
     after every push to `production` branch.
  2. Fail the build (and block merge) on non-zero exit.
  3. Optional: run the full `theme_airpayux` group nightly to catch
     deeper regressions.

The CI command is identical to the local command above — no
production-specific config required.
