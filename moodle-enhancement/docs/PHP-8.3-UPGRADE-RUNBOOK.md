# PHP 8.3 upgrade runbook — XAMPP local + Moodle 5.2 prerequisite

**Status:** Code audit complete (2026-05-23). XAMPP file-swap not
yet executed — operational step requires user oversight.
**Per:** ADR-010 P3 #37.

---

## Why upgrade

Moodle 5.2 (May 2026) requires PHP 8.3+. Our XAMPP local runs
PHP 8.2.12. The wholesale Moodle 5.2 upgrade cannot land until
both XAMPP local AND AWS RDS production PHP are upgraded.

This runbook covers **XAMPP local only**. Production AWS RDS PHP
upgrade is separate IT-team work.

---

## Code-readiness audit (complete)

Grep-based scan of `moodle-enhancement/local/airpay_*` +
`moodle-enhancement/theme/airpayux/` against the documented
PHP 8.2 → 8.3 breaking changes:

| PHP 8.3 breaking change | Hits in our code |
|--------------------------|-----------------|
| `trigger_error(..., E_USER_ERROR)` deprecated | 0 (false positives from grep — local `test_assert()` funcs) |
| Legacy `assert()` string-eval behavior | 0 |
| `ReflectionClass::getStaticProperties()` returns null | 0 |
| `INI_SCANNER_RAW` mode return changed | 0 |
| `Random\Randomizer::getBytesFromString` empty string | 0 |
| Static method called without `static` keyword warning | Not measurable via grep; verify post-upgrade |
| `Date` constants strict type checking | 0 (we don't use Date::ATOM etc.) |
| `mt_rand()` algorithm — already changed in 8.1 | N/A |

**Verdict: code is PHP 8.3-ready by static analysis.** Runtime
verification still needed post-upgrade — there are corner cases
(class autoloader order, type juggling, deprecated callable
signatures) that static checks can miss.

---

## Pre-upgrade backup checklist

Run these **before** touching XAMPP:

```powershell
# 1. Backup current XAMPP php folder
Compress-Archive -Path 'C:\xampp\php' `
                 -DestinationPath "$HOME\Desktop\xampp-php-8.2.12-backup-$(Get-Date -Format yyyy-MM-dd).zip"

# 2. Backup php.ini explicitly (we'll need to merge custom settings)
Copy-Item 'C:\xampp\php\php.ini' "$HOME\Desktop\xampp-php-ini-backup.ini"

# 3. Note Moodle's current PHPUnit state
cd C:\xampp\htdocs\moodle5
php public/admin/tool/phpunit/cli/util.php --diag

# 4. Snapshot the local Moodle DB (in case the upgrade requires re-init)
& 'C:\xampp\mysql\bin\mysqldump.exe' -u root moodle > "$HOME\Desktop\moodle-db-backup-$(Get-Date -Format yyyy-MM-dd).sql"

# 5. Note current Moodle test DB
& 'C:\xampp\mysql\bin\mysqldump.exe' -u root moodletest > "$HOME\Desktop\moodletest-db-backup-$(Get-Date -Format yyyy-MM-dd).sql"

# 6. Save Apache vhost/.htaccess config (in case PHP module name changes)
Copy-Item 'C:\xampp\apache\conf\httpd.conf' "$HOME\Desktop\httpd.conf.backup"
```

---

## Upgrade options (pick one)

### Option A — Full XAMPP reinstall (simplest, recommended)

1. Download latest XAMPP with PHP 8.3.x from
   https://www.apachefriends.org/download.html
   (look for the build labelled "PHP 8.3.x")
2. **STOP all running XAMPP services** (Apache, MySQL, FileZilla,
   Mercury) via the XAMPP Control Panel
3. Rename existing folder: `Move-Item C:\xampp C:\xampp-old-8.2`
4. Install new XAMPP to `C:\xampp` (default path — keeps all our
   Apache aliases working)
5. Copy `htdocs\moodle5` from old to new XAMPP (or symlink)
6. Copy `mysql\data` from old to new XAMPP to preserve DBs
7. Re-merge `php\php.ini` custom settings (see "Custom php.ini" below)
8. Re-merge `apache\conf\httpd.conf` aliases (see "Apache aliases")
9. Start services from new XAMPP Control Panel
10. Run verification (see below)

### Option B — In-place PHP swap (faster but more error-prone)

1. Download standalone PHP 8.3 ZTS x64 from https://windows.php.net/download/
2. Stop Apache + MySQL
3. Rename: `Move-Item C:\xampp\php C:\xampp\php-8.2-old`
4. Extract PHP 8.3 ZIP to `C:\xampp\php`
5. Copy `php.ini-development` to `php.ini` and re-apply our customisations
6. Copy our extensions (`ext/`) into the new PHP if any custom DLLs
7. Restart Apache + MySQL
8. Verification (see below)

---

## Custom php.ini settings to preserve

Critical settings our environment depends on:

```ini
; Moodle 5.2 requirement
max_input_vars = 5000

; Moodle DB session storage
session.save_handler = files
session.save_path = "C:\xampp\tmp"

; Memory + upload (Moodle SCORM uploads)
memory_limit = 512M
post_max_size = 200M
upload_max_filesize = 200M
max_execution_time = 300

; Extensions we use
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=soap
extension=sodium       ; NEW in Moodle 5.2 requirements
extension=zip
extension=xsl

; OpenSSL config (Moodle 5.2 push notifications use sodium)
openssl.cafile = "C:\xampp\php\extras\ssl\cacert.pem"

; Date
date.timezone = Asia/Kolkata
```

---

## Apache aliases to preserve

In `C:\xampp\apache\conf\httpd.conf` we have:

```apache
# Moodle alias — /moodle → moodle5/public
Alias /moodle "C:/xampp/htdocs/moodle5/public"
<Directory "C:/xampp/htdocs/moodle5/public">
    AllowOverride All
    Require all granted
</Directory>

# Listen on port 8080 (not default 80)
Listen 8080
```

---

## Verification after upgrade

```powershell
# 1. Confirm PHP 8.3
php --version
# Expected: PHP 8.3.x

# 2. Run our PHP lint on every airpay plugin
cd 'D:\Claude Local\airpay-ld-os\moodle-enhancement'
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName } | Select-String 'Parse error'
# Expected: no output (no parse errors)

# 3. Run the standalone ws_contract gate
php tools/ci-ws-contract-check.php
# Expected: "RESULT: ALL PASS" + exit 0

# 4. Re-init PHPUnit (mandatory after version change)
cd C:\xampp\htdocs\moodle5
php public/admin/tool/phpunit/cli/init.php
# Expected: "PHPUnit test environment setup complete."

# 5. Run our two ADR-009 PHPUnit gates
vendor/bin/phpunit.bat --group ws_contract,role_detector `
    public/theme/airpayux/tests/role_detector_test.php `
    public/theme/airpayux/tests/ws_contract_test.php
# Expected: "OK" + 17 assertions across 9 tests

# 6. Browser smoke test — log in + visit each of 10 Sentientia surfaces
# (already automated as moodle-enhancement/audit/playwright/tests/surfaces.spec.mjs)
cd 'D:\Claude Local\airpay-ld-os\moodle-enhancement\audit\playwright'
npx playwright test --project=firefox-desktop tests/surfaces.spec.mjs

# 7. Walk the cert-template page (regression check for #192 fix)
# Browser → localhost:8080/moodle/admin/tool/certificate/template.php?id=12
# Expected: page loads with broken-image placeholder, no TypeError
```

---

## Rollback procedure

If verification fails:

```powershell
# 1. Stop services
# (XAMPP Control Panel → Stop Apache, Stop MySQL)

# 2. Restore PHP folder
Remove-Item -Recurse C:\xampp\php
Expand-Archive -Path "$HOME\Desktop\xampp-php-8.2.12-backup-*.zip" `
               -DestinationPath C:\xampp\

# 3. Restore php.ini
Copy-Item "$HOME\Desktop\xampp-php-ini-backup.ini" C:\xampp\php\php.ini

# 4. Restart services + re-init PHPUnit (DB schema unchanged so this is fast)
cd C:\xampp\htdocs\moodle5
php public/admin/tool/phpunit/cli/util.php --buildconfig
```

---

## Known issues to watch for

1. **PHPUnit test DB will need re-init.** The `util.php --diag` will
   tell you. Cost: ~5 min on first re-init.

2. **scssphp may warn differently.** PHP 8.3 has stricter implicit
   conversions; we already see "Array to string conversion" on
   line 927 of scssphp — that's pre-existing in 8.2 and may
   amplify or change in 8.3. Not a blocker; just noisy logs.

3. **`SplDoublyLinkedList`, `SplStack`, `SplQueue`, `SplPriorityQueue`,
   `SplObjectStorage` constructors are now expected to receive
   arguments.** We don't use these.

4. **`get_class()` with no arguments is deprecated outside of class
   scope.** Grep-clean (no hits in our code).

5. **`Database\Cursor` and similar reflection metadata changes.**
   We don't use raw PDO, only Moodle's `$DB` abstraction.

---

## Sequencing with the Moodle 5.2 upgrade

PHP 8.3 is a **prerequisite** for the wholesale 5.2 upgrade but
the two are separate operations:

```
Step 1: PHP 8.3 on XAMPP local      ← this runbook
Step 2: Verify our v4.x stack on PHP 8.3
Step 3: Schedule MySQL 8.4 on production
Step 4: Schedule PHP 8.3 on AWS RDS production
Step 5: Pull Moodle 5.2 codebase (separate ADR)
Step 6: Re-apply our 30 plugins + theme + core-mods
Step 7: Run full E2E + Goal A.y audit on the new stack
Step 8: Production deploy of v5.0.0-sentientia
```

Steps 1+2 can run as ONE session if PHP 8.3 install goes smoothly.
Steps 5-8 are a multi-week migration project tracked separately.

---

## Decision needed from Nitin

1. **Pick Option A (full XAMPP reinstall) or Option B (in-place
   PHP swap)?** A is safer, B is faster.
2. **When to schedule the file-system swap?** It needs ~30 min
   of downtime on this laptop where Apache+MySQL stop.
3. **Should we time it with the airpay_live deploy?** The plugin
   exists in source tree but isn't deployed to XAMPP — perfect
   moment to land both at once.
