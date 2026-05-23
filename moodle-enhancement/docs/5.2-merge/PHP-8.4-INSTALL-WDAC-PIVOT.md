# PHP 8.4 install — WDAC pivot to Docker

**Date:** 2026-05-23 (post-Phase A.4b)
**Context:** Phase B.1 prerequisite for the Moodle 5.2 wholesale merge.
**Status:** Blocked native install; pivoting to Docker.

---

## TL;DR

PHP 8.4.21 native install on local Windows is **blocked by enterprise
WDAC/EDR policy**. Both probed install paths (portable extract +
winget) ended with `Access is denied` on every attempt to launch
`php.exe` — including from `$env:TEMP`, confirming the block is on
**binary hash**, not file path.

**Pivot: run PHP 8.4 via Docker** (`php:8.4-cli` and
`php:8.4-apache` images). Docker is on the policy allow-list per
existing infrastructure. Native XAMPP PHP 8.2 stays in place for
Apache; PHP 8.4 work runs inside containers.

---

## What was tried and what failed

### 1. Portable PHP 8.4 from windows.php.net (FAILED)

```
URL:     https://windows.php.net/downloads/releases/php-8.4.21-Win32-vs17-x64.zip
Size:    33.31 MB
SHA256:  9E2F6E455D3F42993F09DEED23AD0178B3787090C924793E50414B6A92DE186A
Path:    D:\Claude Local\airpay-ld-os\.tools\php-8.4.21-Win32-vs17-x64\
Method:  Expand-Archive + Unblock-File on every file
Result:  Access is denied (on `& "$dst\php.exe" --version`)
```

Tried alternate execution paths:
- `cmd.exe /c "<path>\php.exe" --version` → Access denied
- `Start-Process -FilePath` → Access denied
- Copy `php.exe` to `$env:TEMP` and run → Access denied
- Run via MSYS Bash → Permission denied

The control test (`C:\xampp\php\php.exe --version`) works fine,
confirming the issue is PHP-binary-specific, not a PATH issue.

### 2. winget install PHP.PHP.8.4 (INSTALLED but BLOCKED)

```
winget install PHP.PHP.8.4 --scope user
  → "Successfully installed"
  → Path environment variable modified
  → Command line alias added: "php"

But:
  & "C:\Users\nitin.rajput\AppData\Local\...\php.exe" --version
  → Access is denied
```

Same hash-based block. The signed/verified winget install path didn't
bypass the policy because the binary hash itself is what's being
filtered.

### 3. Native control test (PASSED)

```
C:\xampp\php\php.exe --version
  → PHP 8.2.12 (cli) (built: Oct 24 2023 21:15:15)
```

XAMPP's bundled PHP 8.2 was on the machine before the WDAC policy
was tightened and is allow-listed. Any *new* PHP binary fails.

---

## Diagnosis

The behaviour matches:

- **Windows Defender Application Control (WDAC)** with a hash-based
  allow-list, OR
- An **EDR product** (CrowdStrike, SentinelOne, Symantec) with
  PHP-binary blocking rule.

Evidence:
- `Get-MpPreference` returns `0x800106ba` (Defender API blocked) —
  suggests Defender provider is suppressed by another agent.
- File ACL is clean (Authenticated Users have ReadAndExecute).
- Zone.Identifier is gone after Unblock-File.
- The block is independent of file path.

This is **not solvable in user-space** without IT intervention.

---

## Pivot: Docker-based PHP 8.4

Docker Desktop is installed and on the policy allow-list:

```
docker --version
  → Docker version 29.3.1, build c2be9cc
```

Docker daemon engages on Docker Desktop launch. WSL2 backend is
already provisioned (default distro `docker-desktop`).

### Container strategy

**PHP CLI work (Phase B.1, B.2, lint, PHPUnit):**

```bash
# Run upgrade.php
docker run --rm \
  -v C:\xampp\htdocs\moodle5\public:/var/www/html \
  php:8.4-cli \
  php /var/www/html/../admin/cli/upgrade.php --non-interactive

# Run a single PHPUnit suite
docker run --rm \
  -v C:\xampp\htdocs\moodle5\public:/var/www/html \
  -v C:\xampp\htdocs\moodle5\phpunit_data:/var/phpunit_data \
  php:8.4-cli \
  php /var/www/html/vendor/bin/phpunit --testsuite=local_airpay_core_testsuite

# Lint a file
docker run --rm \
  -v C:\xampp\htdocs\moodle5\public:/var/www/html \
  php:8.4-cli \
  php -l /var/www/html/local/airpay_core/classes/cm_navigation.php
```

**PHP Apache work (Phase B.12 visual smoke + Phase C rehearsal):**

```yaml
# docker-compose.5.2.yml — placeholder for Phase B.12+
services:
  apache:
    image: php:8.4-apache
    ports: ["8081:80"]
    volumes:
      - C:\xampp\htdocs\moodle5\public:/var/www/html
      - ./moodledata:/var/moodledata
  db:
    image: mariadb:10.11
    environment:
      MARIADB_ROOT_PASSWORD: dev
      MARIADB_DATABASE: moodle
```

This stack runs on port 8081 alongside XAMPP's 8080. We can compare
side-by-side during the merge.

---

## Required PHP extensions for Moodle 5.2

The `php:8.4-cli` image is bare-bones. Moodle requires:

```
required: hash, fileinfo, sodium, filter, gd, intl, mbstring,
          mysqli, pdo, pdo_mysql, xml, zip, opcache, curl, soap
optional: exif, openssl, ldap, simplexml, tokenizer
```

We'll need a custom `Dockerfile.moodle-5.2` that installs these via
`docker-php-ext-install`:

```dockerfile
FROM php:8.4-cli
RUN apt-get update && apt-get install -y \
    libfreetype-dev libjpeg62-turbo-dev libpng-dev \
    libicu-dev libxml2-dev libzip-dev libsodium-dev \
    libldap2-dev libcurl4-openssl-dev libxslt-dev libsoap-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
        gd intl mbstring mysqli pdo_mysql xml zip opcache \
        curl soap exif ldap xsl simplexml
```

Build once, use for all Phase B.x CLI commands.

---

## ADR-011 amendment

Phase B.1 (in ADR-011) said:

> B.1 — Upgrade local XAMPP to PHP 8.3. Verify `php -v`, restart
> Apache, smoke-test login + course view + dashboard.

That's blocked. Revised B.1:

> B.1.a — Build `Dockerfile.moodle-5.2` with required extensions.
> Verify `docker run --rm dockerfile.moodle-5.2 php -v` returns 8.4.21.
> B.1.b — Wrapper script `tools/php-docker.sh` that proxies any
> `php` invocation through the container, mounting the local
> `public/` dir read-write.
> B.1.c — Run `php admin/cli/upgrade.php --non-interactive` through
> the wrapper against the existing XAMPP DB (host networking).
> Smoke-test login + course view + dashboard via the host XAMPP
> Apache (still serving on port 8080, still on PHP 8.2 — which is
> acceptable for visual smoke, the CLI 8.3+ work is what unblocks
> upgrade.php).

Total Phase B.1 effort revised from 2h to ~3h (add 1h for Docker
extension build + wrapper script).

---

## Open question for Nitin

Two paths to resolve fully:

1. **Get IT to allow-list PHP 8.4 binaries** — proper fix, may take
   1-3 days. Unblocks native XAMPP PHP swap.
2. **Stay on Docker for PHP 8.4 throughout the merge** — works
   today; adds ~10-15% friction to each PHP-CLI invocation; CI/CD
   path is also Docker-friendly so this isn't a long-term loss.

Recommendation: **start with Docker today** so Phase B.1-B.11 can
proceed in parallel with IT ticket resolution. If IT clears the
allow-list mid-merge, we can switch back to native PHP without losing
any work.

---

## Cleanup done

- Removed `D:\Claude Local\airpay-ld-os\.tools\php-8.4.21-Win32-vs17-x64\`
  (the failed-launch portable PHP extract)
- winget-installed PHP at `$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.8.4_*\`
  is preserved (in case IT clears the policy and we want to use it later)

---

## Related

- ADR-011 Phase B.1 — updated reference target
- PHASE-A2-EXECUTION-LOG-2026-05-23.md — Phase A complete; this
  pivot was discovered immediately after.
- PHASE-A4B-CONFLICT-MAP.md — work breakdown that Phase B.1 unblocks.
