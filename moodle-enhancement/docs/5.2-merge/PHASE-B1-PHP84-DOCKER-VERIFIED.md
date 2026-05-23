# Phase B.1 — PHP 8.4 via Docker, verified working

ADR-011 Phase B.1 deliverable. Closes the PHP 8.4 prerequisite for the
Moodle 5.2 wholesale merge.

---

## What works

```
Image:    moodle-5.2-cli:latest (960 MB)
Build:    docker build -t moodle-5.2-cli -f tools/Dockerfile.moodle-5.2 tools/
Runtime:  PHP 8.4.21 (cli) (NTS, May 19 2026 build)
Zend:     v4.4.21 with Zend OPcache v8.4.21
```

Extensions verified loaded (all Moodle 5.2 required):

```
gd, intl, mbstring, mysqli, pdo, pdo_mysql, xml, zip,
opcache, curl, soap, exif, ldap, sodium, bcmath, simplexml, xsl
```

(opcache loads under "Zend Modules" — `php -v` confirms it's active.)

---

## PHP 8.4 lint baseline against our fork

```
PHP 8.4 lint baseline: 0 failures across 902 files
```

Same result as the Phase A.3 PHP 8.2 baseline. Our 30 `local_airpay_*`
plugins (902 PHP files) all pass syntax checks under PHP 8.4 without
modification.

This confirms:
- No new PHP 8.3/8.4 keyword conflicts
- No removed-syntax landmines (anonymous-class re-instantiation etc.)
- No reserved-word collisions (8.3 added `readonly` class keyword)

What `php -l` doesn't catch (runtime-only, comes out during Phase B.2+):
- Deprecation warnings (E_DEPRECATED) — those need actual execution.
  We'll capture them in Phase B.2 when we first run `upgrade.php`
  through the container.
- Behaviour changes (date handling, DateTime exception types, JSON
  encoding edge cases) — those need PHPUnit runs.

---

## Wrapper script: tools/php-docker.sh

```bash
tools/php-docker.sh -v
# Equivalent to running php --version inside the container with our
# XAMPP /public mounted as /var/www/html.

tools/php-docker.sh -l local/airpay_core/classes/cm_navigation.php
# Lints one of our P0 #9 files under PHP 8.4.

tools/php-docker.sh ../admin/cli/upgrade.php --non-interactive
# Runs Moodle's upgrade.php — but pointed at the host XAMPP DB via
# host.docker.internal:3306 (configured in the container).
```

Mounts:
- `C:\xampp\htdocs\moodle5\public` -> `/var/www/html` (rw)
- `C:\xampp\htdocs\moodle5` -> `/var/www/moodle` (rw)
- `C:\xampp\moodledata` -> `/var/moodledata` (rw)

Network: `--add-host=host.docker.internal:host-gateway` exposes the
host's MariaDB on port 3306 to the container.

---

## Phase B.2 unblocked — next steps

1. **Drop XAMPP's PHP 8.2 association** in `php-docker.sh` (the
   container has its own PHP — only relevant when invoking `php` CLI;
   Apache continues on PHP 8.2 for the Sentientia visual layer).
2. **Run `upgrade.php` through the wrapper** against the live XAMPP
   DB to confirm Moodle bootstraps under PHP 8.4 — likely surfaces a
   handful of deprecation notices we can catalogue.
3. **Run a single PHPUnit suite** through the wrapper as a smoke test:
   ```
   tools/php-docker.sh /var/www/html/vendor/bin/phpunit \
       --testsuite=local_airpay_core_testsuite
   ```
4. **First merge commit** — pull Moodle 5.2 upstream into the
   `5.2-merge-baseline` branch and start working through Phase A.4b's
   conflict map.

---

## CI implication (free win)

Phase B.x runs through Docker locally. The same image will run
identically in GitHub Actions:

```yaml
# .github/workflows/moodle-5.2-ci.yml — placeholder for Phase B.12
- uses: docker/build-push-action@v5
  with:
    file: moodle-enhancement/tools/Dockerfile.moodle-5.2
    push: false
    load: true
- run: docker run --rm moodle-5.2-cli php -l local/airpay_core/classes/cm_navigation.php
```

The WDAC pivot to Docker turned out to be a strict improvement for
build reproducibility. Local dev image = CI image.

---

## Caveats / known limits

1. **Apache + mod_php 8.4** — not yet attempted. XAMPP Apache stays on
   PHP 8.2 for now. For the final Phase D production cutover we'll
   need either:
   - XAMPP PHP swap (still WDAC-blocked)
   - `php:8.4-apache` container fronting our Moodle public/ tree
     (separate stack on port 8081)
2. **First-run latency** — `tools/php-docker.sh` adds ~250-500ms
   overhead per invocation vs native php. Per-file lint is ~10x
   slower. Doesn't matter for one-shot upgrade.php / PHPUnit runs.
3. **Image size** — 960 MB is big but well within budget. The base
   `php:8.4-cli-bookworm` is 450 MB; our extensions add ~500 MB
   (libldap, libgd, libsodium dev headers and friends).

---

## Verified artifacts in git

| Path | Purpose |
|------|---------|
| `tools/Dockerfile.moodle-5.2` | Image recipe |
| `tools/php-docker.sh` | Wrapper script |
| `docs/5.2-merge/PHP-8.4-INSTALL-WDAC-PIVOT.md` | WDAC discovery + pivot rationale |
| `docs/5.2-merge/PHASE-B1-PHP84-DOCKER-VERIFIED.md` | This doc — confirms B.1 done |

---

## ADR-011 §"Open questions" updates

1. ~~**PHP 8.3 install — who and when?**~~ **RESOLVED 2026-05-23** —
   solved via Docker (PHP 8.4.21 in `moodle-5.2-cli` image). No IT
   ticket needed for Phase B work. Native install can wait or skip.
2. **Maintenance window for production cutover** — still open.
3. **Communication plan to learners** — still open.

---

## Related

- ADR-011 — Phase B.1 (this doc closes it)
- `docs/5.2-merge/PHP-8.4-INSTALL-WDAC-PIVOT.md` — why we went Docker
- `docs/5.2-merge/PHASE-A3-PHP83-LINT-REPORT.md` — PHP 8.2 baseline
  (we matched it under PHP 8.4)
- `docs/5.2-merge/PHASE-A4B-CONFLICT-MAP.md` — Phase B.2+ work breakdown
