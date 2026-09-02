# State Card — local_sentientia_api

**Plugin:** `local_sentientia_api` (Sentientia Public API + LTI 1.3)
**Roadmap gap:** P2.3 — Public API + LTI (GAP-ANALYSIS-INVINCE-LXP-2026-06-16 §6)
**Branch:** `claude/gap-api-lti`
**Created:** 2026-06-16
**Status:** 1.1.0 — ADR-030 Wave A (outbound webhooks) built 2026-08-28, feature-flagged OFF.
**Version:** 2026082800 (1.1.0)
**Depends on:** `local_sentientia_platform` (feature_flags + tenant helpers)

---

## ADR-030 Wave A — outbound webhooks (2026-08-28)

- **Flag:** `sentientia.api.webhooks.enabled` (sub-flag; requires `sentientia.api.enabled`). Both
  resolved per (customer, tenant) with server-to-server semantics via
  `webhooks\dispatcher::enabled_for()`; observers are complete no-ops when OFF.
- **Capability:** `local/sentientia_api:webhooks_manage` (RISK_CONFIG) → admin page
  `webhooks.php` (registered as admin_externalpage `local_sentientia_api_webhooks`).
- **Tables:** `local_sentientia_api_whsub` (subscriptions, per-row HMAC secret) +
  `local_sentientia_api_whdel` (delivery queue/audit: status queued|sent|failed|dead,
  attempts, nextattempt, userid for privacy). Upgrade step 2026082800.
- **Events (v1 vocabulary):** `course.completed`, `enrolment.created`, `certificate.issued`
  (observers in `db/events.php`, internal=false, fail-safe; payloads are ids+timestamps only).
- **Delivery:** `task\webhook_drain` every minute, registered DISABLED (triple opt-in);
  `webhooks\queue::drain()` → `sender::deliver()` via `\core\http_client` (curl security
  policy enforced at send time, redirects not followed) with `X-Sentientia-Signature:
  t=<ts>,v1=<hmac_sha256("t.body")>`; backoff 60s→300s→1800s→7200s, dead after 5 attempts;
  disabled subscription or flag-OFF tenant ⇒ dead-lettered without sending.
- **URL policy:** https-only + `curl_security_helper::url_is_blocked()` at save time.
- **Privacy:** provider declares `whdel` + external location; export/delete wired.
- **Tests:** `tests/webhooks_test.php` — 14 cases (signature roundtrip/tamper/replay, https +
  blocked-host validation, events normalisation, flag gating, per-sub fan-out, signed send,
  backoff→dead, disabled-sub + flag-off dead-letter, retry+prune, course_completed observer,
  observer no-op OFF, privacy delete). `sender::$transport` injects a fake transport.
- **ADR-030 complete:** Waves A (webhooks), B (SCIM Users) and C (SCIM Groups + attestation) all shipped, flag-OFF.

## ADR-030 Wave C — SCIM Groups + attestation (2026-09-02, version 2026090200 / 1.3.0)

- **Groups = organisation tree** (`classes/scim/group_resource.php`): `Group.id` = `local_sentientia_org.id`,
  `displayName` = fullname, `externalId` = shortname, `members` = users whose `open_path` equals the org
  path (direct placement). Structure is READ-ONLY via SCIM (POST/PUT/DELETE → 501 `mutability`);
  membership PATCH is writable: `add` places the user in the org (`open_path`/`open_costcenterid`
  via `user_manager::update`), `remove` (incl. `members[value eq "id"]`) returns them to the tenant
  root. Everything tenant-scoped through the client's root; foreign users → 400, foreign orgs → 404.
  Without the BizLMS columns membership ops answer 501 (`scim_groups_unavailable`).
- **Attestation log** `local_sentientia_api_scimevt` (`classes/scim/attestation.php`): created /
  reactivated / deactivated / updated / moved per client + user + externalId (+ short non-PII detail);
  written by the handler on every provisioning change; admin table on `scim.php` (last 100) +
  sesskey-guarded CSV export (`?export=csv`); pruned by the nightly cleanup after log retention;
  privacy provider declares/export/deletes it. This is the deprovisioning evidence ADR-028 asked for.
- Discovery: `/ResourceTypes` and `/Schemas` now include Group.
- **Tests:** `tests/scim_groups_test.php` — 5 cases (tenant-scoped list/get with members, read-only
  501s + ResourceTypes, PATCH add/remove moves + attestation + cross-tenant 400, full user lifecycle
  attestation sequence `created→updated→deactivated→reactivated→deactivated` + CSV shape, prune).
- **Final verification (2026-09-02):** full `local_sentientia_api_testsuite` on a fresh phpunit DB =
  **60/60 OK, 198 assertions** (8 skips = pre-existing LTI harness skips; deprecations = @covers metadata).

## ADR-030 Wave B — SCIM 2.0 Users (2026-08-29, version 2026082900 / 1.2.0)

- **Endpoint:** `scim/v2.php` — raw-HTTP router (NO_MOODLE_COOKIES + AJAX_SCRIPT, the xAPI-LRS
  pattern) because core web services cannot return real HTTP status codes. PATH_INFO routing
  with a `?path=` fallback. IdP Tenant URL = `https://<site>/local/sentientia_api/scim/v2.php`.
- **Handler:** `classes/scim/handler.php` is transport-neutral (`handle($method,$path,$query,
  $body,$authheader)` → `{status, body, headers}`) so the protocol is unit-tested without HTTP.
  Gate order: bearer client → flags `sentientia.api.enabled` + `sentientia.api.scim.enabled`
  resolved per (customer, tenant) → per-client rate window → route.
- **Resources:** `/ServiceProviderConfig`, `/ResourceTypes`, `/Schemas`, `/Users` (GET list with
  `filter` userName|externalId|id|emails.value `eq`, 1-based `startIndex`/`count`≤200; POST),
  `/Users/{id}` (GET/PUT/PATCH/DELETE). SCIM `id` = Moodle user id; `externalId` via
  `local_sentientia_api_scimmap` (per-client, 191-char, unique (cliid, externalid)).
- **Clients:** `local_sentientia_api_scimcli` — sha256 token hash (unique), tenant root
  (0 = site-level), auth plugin for created users (oauth2|oidc|saml2|ldap|manual|nologin),
  self-contained fixed-window rate counter on the row. Admin page `scim.php` (cap
  `:scim_manage`, RISK_CONFIG|RISK_PERSONAL) issues/rotates tokens (shown once).
- **Writes** delegate to `local_sentientia_users\user_manager` (create/update/suspend) — events
  fire, open_* discipline holds; tenant-bound clients place users at `open_path = /<root>`
  (only when the BizLMS column exists — vanilla schema stays clean). `active:false` / DELETE =
  suspend + session destroy (soft; history retained); re-POST of a deactivated externalId
  reactivates (200) instead of 409. Username/email collisions → 409 `uniqueness`.
- **Tenant scoping** rebuilt without `$USER` (`tenant_where()`), users outside scope are 404.
- **Privacy:** `scimmap` declared; export + delete wired. Requests logged to `request_log`
  as `apiversion='scim2'` with ids masked (`METHOD /Users/{id}`), no bodies.
- **Tests:** `tests/scim_test.php` — 16 cases / 76 assertions (401/503/disabled-client/429,
  discovery, 201 create + mapping + auth plugin, 409, inactive→reprovision 200, 400 JSON/required,
  list+filter+pagination, filter parser edge cases, PATCH active/name/email/userName + ignored
  paths, PUT, DELETE 204 soft, 404/405, tenant isolation [skips without bizlms_fixture]).
  Full plugin suite 55/55 green. Lessons: `fk_user` key + separate `idx_user` on the same field
  collides in XMLDB; CHAR NOT NULL fields must not carry `DEFAULT=""`; flag statics leak across
  test classes → invalidate in tearDown too.
- **Side fix:** `sentientia_users` 2.7.5 — `user_manager::suspend()` now calls
  `destroy_user_sessions()` (4.5 deprecation surfaced by SCIM usage).

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
