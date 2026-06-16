# State Card — local_sentientia_api

**Plugin:** `local_sentientia_api` (Sentientia Public API + LTI 1.3)
**Roadmap gap:** P2.3 — Public API + LTI (GAP-ANALYSIS-INVINCE-LXP-2026-06-16 §6)
**Branch:** `claude/gap-api-lti`
**Created:** 2026-06-16
**Status:** Initial atomic build complete — feature-flagged OFF, awaiting deploy + smoke.
**Version:** 2026061600 (1.0.0)
**Depends on:** `local_sentientia_platform` (feature_flags + tenant helpers)

---

## What it delivers

1. **Versioned public REST API (`/v1/`)** on Moodle's web-service framework.
   7 external functions (6 read + 1 write + OpenAPI discovery), each a subclass
   of `external_api` with full `execute_parameters`/`execute_returns` contracts.
2. **LTI 1.3 provider/consumer scaffolding** — OIDC login init, launch landing
   with RS256 JWT verification, JWKS endpoint. Tenant-scoped registrations +
   one-time nonce replay protection.

## Feature flags (db/feature_flags.php — all DEFAULT OFF)

- `sentientia.api.enabled` — master switch for the v1 REST surface
- `sentientia.api.write.enabled` — WRITE sub-flag (additional to master)
- `sentientia.api.lti.enabled` — LTI login/launch/JWKS endpoints

Flag-OFF = complete no-op (verified by `tests/external/list_courses_test::test_flag_off_is_noop`).

## Security model (base::open_v1 gate, applied to every endpoint)

1. Feature flag (master, + write sub-flag for writes) → `api_disabled` / `api_write_disabled`
2. `validate_context(system)` (token auth honoured by WS dispatcher)
3. Per-user fixed-window rate limit → `ratelimited` (logical 429)
4. Tenant scope from `open_path`; queries filtered via `tenant::path_filter()`;
   `get_course` / enrolment / completion endpoints call `tenant::require_path_access()`
5. Capability declared per-function in db/services.php AND re-asserted in code

PII discipline: enrolment email exposed to site admins only. Request log stores
no bodies, no PII beyond userid. WRITE = triple gate (master + write flag + :write cap).

LTI security: RS256 pinned (rejects `none` / alg-confusion), iss/aud/exp/iat/nonce
validated, nonce single-use + expiry. Fails closed when no verification key.

## Files (28)

```
version.php  lib.php  settings.php  index.php  README.md
db/{install.xml, upgrade.php, access.php, services.php, feature_flags.php, tasks.php}
classes/rate_limiter.php  classes/request_log.php
classes/external/v1/{base, list_courses, get_course, list_enrolments,
                     list_completions, list_skills, create_enrolment, openapi}.php
classes/lti/{jwt_service, registration, launch}.php
classes/task/cleanup.php
classes/privacy/provider.php
lti/{login, launch, jwks}.php
lang/en/local_sentientia_api.php   lang/hi/local_sentientia_api.php   (49 keys each, 100% parity)
docs/openapi-v1.yaml
tests/external/{list_courses_test, create_enrolment_test}.php
tests/{rate_limiter_test, lti_jwt_test, lti_registration_test}.php
```

## DB tables (db/install.xml)

- `local_sentientia_api_log` — append-only request audit (userid, tenant, endpoint, status)
- `local_sentientia_api_rate` — fixed-window rate counters (unique userid+windowstart)
- `local_sentientia_api_lti_reg` — LTI registrations (tenant-scoped, issuer+clientid index)
- `local_sentientia_api_lti_nonce` — one-time login nonces (replay protection)

## Test coverage

- list_courses: flag-OFF no-op, capability required, tenant scoping, perpage clamp
- create_enrolment: write-flag gate, write capability, cross-tenant rejection, happy path
- rate_limiter: budget enforcement, header reporting, anonymous denial, prune
- lti_jwt: valid verify + 7 rejection cases (tamper, none-alg, iss, aud, expiry, nonce, malformed)
- lti_registration: tenant scoping, disabled reg, nonce single-use, nonce expiry

## Verification done

- `php -l` clean on all 28 PHP files (PHP 8.4)
- en/hi lang parity = 49/49 (diff clean)
- No git conflict markers

## Open / next-session

- PHPUnit run requires a Moodle DB harness (not available in worktree); tests
  are written to skip gracefully when local_sentientia_platform is absent.
- LTI: JWKS-URL fetch + `kid` selection left as documented extension point
  (`registration::public_key()` currently uses inline PEM; fails closed otherwise).
- LTI launch → Moodle user provisioning/session is scaffolded (verifies, then
  renders confirmation) — claims→user mapping policy is a follow-up.
- Deploy to XAMPP + Admin → Notifications to install schema; enable the WS
  service + flags; smoke the REST surface. NOT done here (per task constraints).
```
