# PHPUnit Gate — `.github/workflows/ci.yml` job `phpunit-5.2`

**Status:** Active. Blocks merge on red.
**Owner:** Platform team (Nitin Rajput).
**Introduced:** 2026-05-24 (P2 cutover-prep chip).

The PHPUnit gate spins up Moodle 5.2 (`MOODLE_502_STABLE` from
`github.com/moodle/moodle`) on every PR, installs every airpay /
sentientia plugin into it, and runs the full test suite of each plugin
that has a `tests/` directory. If a single test fails, the gate goes
red and the PR cannot merge.

This document explains:

1. [How the gate works](#1-how-the-gate-works)
2. [How to read a failure](#2-how-to-read-a-failure)
3. [Common failure modes + the fix for each](#3-common-failure-modes--the-fix-for-each)
4. [How to skip a flaky test correctly](#4-how-to-skip-a-flaky-test-correctly)
5. [How to reproduce the CI environment locally](#5-how-to-reproduce-the-ci-environment-locally)
6. [What this gate does NOT check](#6-what-this-gate-does-not-check)

---

## 1. How the gate works

The job runs on every PR touching `moodle-enhancement/**`, every push
to `production`, and every workflow change. It takes ~10-15 min cold
and ~5-7 min with a warm composer cache.

Sequence (mirrors the `phpunit-5.2` job in `ci.yml`):

```
1. Boot a Postgres 14 service container.
2. Setup PHP 8.2 with the extensions Moodle 5.x mandates.
3. Clone Moodle 5.2 stable (MOODLE_502_STABLE branch) into ./moodle.
4. Restore composer cache (keyed on Moodle commit + PHP version).
5. composer install — installs PHPUnit ~11.x and the rest of Moodle's
   dev dependencies.
6. Copy every airpay / sentientia plugin from moodle-enhancement/ into
   moodle/local/, moodle/blocks/, moodle/mod/quiz/accessrule/,
   moodle/theme/airpayux/, and moodle/payment/gateway/airpay/.
7. Write a minimal config.php pointing at the Postgres service and
   declaring the phpu_* prefix + phpunit_dataroot.
8. php admin/cli/install_database.php — installs mdl_* tables.
9. php admin/tool/phpunit/cli/init.php — installs phpu_* tables and
   builds phpunit.xml with one <testsuite> per plugin that has tests/.
10. Walk the plugin tree, assemble the list of `<frankenstyle>_testsuite`
    names, and run `vendor/bin/phpunit --testsuite <comma-separated-list>`.
11. JUnit XML is written to tests/junit/phpunit-results.xml and uploaded
    as the `phpunit-5.2-results` artifact (retention: 30 days).
```

Two pieces of behaviour worth knowing:

- **Composer cache** is keyed on `composer.lock` from the cloned
  Moodle (`composer-moodle-MOODLE_502_STABLE-php8.2-<hash>`). When
  Moodle bumps a dependency the cache misses and the job takes the
  full 10-15 min. Subsequent runs against the same Moodle commit hit
  the cache and finish in ~5 min.
- **Plugins without `version.php`** (e.g. `airpay_lifecycle`,
  `airpay_pages`) are skipped — they're partial plugins in progress
  and the copy step only takes directories that look like real
  installable plugins.

---

## 2. How to read a failure

When the gate goes red:

1. Open the failed run on GitHub Actions.
2. Click the **phpunit-5.2** job, scroll to the **Run PHPUnit** step.
   The phpunit output prints the failing test class, method, file,
   and line.
3. The step's tail looks like:

   ```
   1) local_airpay_users\external\list_users_test::test_default_tenant_scope
   Failed asserting that 8001 matches expected 8000.
   /home/runner/work/.../local/airpay_users/tests/external/list_users_test.php:142
   ```

4. For deep dives — download the **phpunit-5.2-results** artifact
   from the run's **Artifacts** panel. It's a JUnit XML you can open
   in any IDE (VS Code, PhpStorm) or pipe through `xmllint` /
   `grep '<failure'`.

---

## 3. Common failure modes + the fix for each

### 3.1 "Test environment not initialised" / fatal during init.php

**Symptom:** `php admin/tool/phpunit/cli/init.php` exits non-zero
before any tests run. Usually a plugin's `db/install.xml` failed to
parse or a `db/upgrade.php` threw.

**Fix:**
- Run the same step locally (see §5) and watch the exact error.
- If it's an XMLDB parse error, validate `db/install.xml` against
  `lib/xmldb/xmldb.xsd`:
  ```bash
  xmllint --noout --schema lib/xmldb/xmldb.xsd \
    local/<plugin>/db/install.xml
  ```
- If it's an upgrade.php error, the most common root cause is a
  missing `if (!$dbman->table_exists($table))` guard. See
  `.claude/rules/database.md` "db/upgrade.php — Complete Template".

### 3.2 "Class not found: local_airpay_users\external\list_users"

**Symptom:** A test imports a class via `use` or `new` that PHP
can't autoload.

**Cause:** Classes outside `classes/` need to be `require_once`-d
explicitly. Or the test is in the wrong namespace.

**Fix:**
- Confirm the class lives at `classes/<subdir>/<file>.php` with the
  matching namespace `local_<plugin>\<subdir>`.
- If it lives outside `classes/`, add `require_once` at the top
  of the test (the standard `tests/external/*_test.php` files do
  this).
- Bump `version.php` so the classloader cache invalidates on the
  next init run.

### 3.3 "table mdl_local_airpay_xxx does not exist"

**Symptom:** A test queries a table that does not exist in the test DB.

**Cause:** The table was added via `db/upgrade.php` but never seeded
into `db/install.xml`. New plugin installs (and PHPUnit init) only
read `install.xml`.

**Fix:** Mirror the new column / table from upgrade.php into
install.xml. Bump `version.php`.

### 3.4 "Generator does not exist for tenant"

**Symptom:** A test calls
`$this->getDataGenerator()->get_plugin_generator('local_airpay_org')`
and PHPUnit complains.

**Cause:** The plugin has no `tests/generator/lib.php`. CI does not
auto-create one.

**Fix:** Add `tests/generator/lib.php` with the minimum:

```php
<?php
defined('MOODLE_INTERNAL') || die();

class local_airpay_org_generator extends component_generator_base {
    public function create_org(array $params = []): stdClass { ... }
}
```

### 3.5 PHP deprecation noise causes red

**Symptom:** Test passes the assertions but PHPUnit converts an
`E_DEPRECATED` (e.g. `Implicit conversion from float to int`) into
an exception because Moodle's phpunit.xml has
`convertNoticesToExceptions="true"`.

**Cause:** Moodle 5.2 enforces stricter PHP than the local XAMPP
5.1.3+ install. New deprecations show up here first.

**Fix:**
- Open the offending file at the line PHPUnit reports.
- Cast explicitly: `(int) $value` instead of letting PHP do it.
- For string concatenation with possibly-null vars: use `(string) $x ?? ''`.
- See `docs/PHP-8.3-UPGRADE-RUNBOOK.md` for the common patterns we've
  already mapped.

### 3.6 Postgres-specific failure ("operator does not exist: integer = text")

**Symptom:** Test passes on local XAMPP MariaDB but red on CI.

**Cause:** MariaDB is permissive about implicit type coercion; Postgres
is strict. Common culprit: a `WHERE id = :uid` with `$params['uid']`
as a string instead of an int.

**Fix:** Cast all params at the call site:

```php
$DB->get_record('user', ['id' => (int) $userid]);
```

Not at the SQL level — the $DB API does not strip param types.

### 3.7 Random test order failures (Pass alone, fail in suite)

**Symptom:** Running the failing test class on its own passes;
running it as part of the full suite fails.

**Cause:** Missing `resetAfterTest()` in a sibling test, or a
static cache leaking between tests.

**Fix:** Add `$this->resetAfterTest()` as the first line of every
test method that touches DB or globals. If a static cache is at
fault, expose a `::reset_cache()` static and call it in `setUp()`.

---

## 4. How to skip a flaky test correctly

**Flaky** here means a test that's failed intermittently in CI without
a code change in its target — usually a race condition, a time-zone
boundary, or an external dependency.

**Default:** Do NOT skip. Fix the flake at root.

If the root cause is genuinely environmental (e.g. test needs a clock
mock you haven't written yet) and the gate is blocking an urgent
hotfix, follow this protocol:

1. **Use `markTestSkipped()`, NOT `@group skip`**. The latter looks
   like a feature flag and PHPUnit may still run it.

   ```php
   public function test_audit_log_write_with_concurrent_writers(): void {
       $this->markTestSkipped(
           'Flaky: race on local_airpay_audit_log timecreated. ' .
           'Tracked in NIGHT-RUN-PLAYBOOK F-23. Re-enable when '.
           'clock-mock harness lands.'
       );
       // … original test body kept intact below this line …
   }
   ```

2. **Include the tracking reference** (a NIGHT-RUN ticket, an audit
   row, or a PROJECT-STATE.md anchor). A bare `markTestSkipped()`
   without a reason is a code-review block.

3. **Open an issue** in the same PR labelled `flaky-test` describing
   the symptom, the run URL where it failed, and the suspected race.

4. **Set a calendar item** to re-enable in ≤ 14 days. Flake suppression
   that outlives the fortnight gets ratchet-treated by the next
   weekly Goal C review.

What NOT to do:

- ❌ Comment out the test (silent — won't show in coverage).
- ❌ Add `@runInSeparateProcess` to "make it pass" — usually masks
  the real bug.
- ❌ Wrap in `try/catch` and call it "tolerant" — the failure is
  still real, you've just hidden it.

---

## 5. How to reproduce the CI environment locally

The local XAMPP setup (Moodle 5.1.3+ on MariaDB) is NOT a faithful
copy of CI. To reproduce CI failures, pull Moodle 5.2 separately:

```powershell
# One-time: clone Moodle 5.2 into a sibling directory.
cd D:\Claude Local\
git clone --depth 1 --branch MOODLE_502_STABLE `
  https://github.com/moodle/moodle.git moodle-5.2-ci

cd moodle-5.2-ci
composer install --no-progress

# Copy every airpay plugin in (run from D:\Claude Local\moodle-5.2-ci\)
$src = "D:\Claude Local\airpay-ld-os\moodle-enhancement"
Get-ChildItem "$src\local\airpay_*","$src\local\sentientia_*" -Directory | Where-Object {
    Test-Path "$($_.FullName)\version.php"
} | ForEach-Object {
    $dest = "local\$($_.Name)"
    Remove-Item -Recurse -Force $dest -ErrorAction SilentlyContinue
    Copy-Item -Recurse $_.FullName $dest
}
# (Repeat the loop for blocks/, mod/quiz/accessrule/, theme/airpayux,
# payment/gateway/airpay — see ci.yml "Copy airpay/sentientia plugins"
# step for the canonical list.)

# Mint a minimal config.php (mirrors the CI heredoc — adjust for your
# local Postgres / MariaDB credentials).
# See ci.yml "Write Moodle config.php for PHPUnit" step for the template.

php admin\cli\install_database.php --agree-license --fullname=CI `
  --shortname=ci --adminuser=admin --adminpass='ci-Password1!' `
  --adminemail=ci@localhost --non-interactive --allow-unstable

php admin\tool\phpunit\cli\init.php

# Run the same suite list CI runs:
vendor\bin\phpunit --testsuite `
  local_airpay_users_testsuite,local_airpay_org_testsuite,...
```

For one-off triage, run just the failing test class:

```powershell
vendor\bin\phpunit local\airpay_users\tests\external\list_users_test.php
```

---

## 6. What this gate does NOT check

The gate verifies PHP-side behaviour against Moodle 5.2's APIs. It
does NOT check:

- **Behat / browser flows** — Phase B.12 NVDA verification is still
  manual.
- **Mobile app surfaces** — `local_sentientia_pwa` UI is tested by
  Lighthouse audit, not PHPUnit (see `docs/audits/PHASE-H-A11Y-*`).
- **SCORM player runtime** — the SCORM packager has unit tests but
  in-browser playback parity is a separate Playwright suite (local
  invocation only).
- **WS contract drift** — covered by the standalone `ws-contract-gate`
  job (ADR-009).
- **Static config files** — covered by `static-checks` (JSON +
  Mustache balance) and `php-lint`.
- **Production data migrations** — those run only on the cutover-day
  runbook (`docs/5.2-merge/PHASE-B12-*`), not in CI.

Each of these has its own gate or its own runbook; the PHPUnit gate
is one node in the layered defence, not the whole net.

---

## References

- Workflow: `.github/workflows/ci.yml` (job `phpunit-5.2`)
- Local PHPUnit runbook: `moodle-enhancement/PHPUNIT-RUNBOOK.md`
- 5.2 staging plan: `moodle-enhancement/docs/adr/ADR-011-moodle-5.2-wholesale-upgrade-staging.md`
- Plugin DB rules: `.claude/rules/database.md`
- Borrow inventory (P0 closures): `moodle-enhancement/docs/adr/ADR-010-moodle-5.2-borrow-inventory.md`
