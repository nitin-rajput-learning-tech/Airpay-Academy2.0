# ADR-030: SCIM 2.0 provisioning + outbound webhooks (ADR-028 Phase 2.4)

**Status:** Proposed · **Date:** 2026-08-28 · **Owner:** Nitin Rajput
**Parent:** ADR-028 §Phase 2.4 ("SCIM 2.0 shim on `base::open_v1()` enforcement +
generic outbound webhook subscription: completion/enrolment/certificate events,
HMAC + retry"). Grounded in the 2026-08-28 architectural recon of
`sentientia_api`, `sentientia_integrations`, `sentientia_xapi`, `sentientia_users`.

---

## 1. Context

Enterprise buyers expect (a) **user provisioning from their IdP** — Entra/Okta
speak SCIM 2.0 — and (b) **event push into their systems** — webhooks with HMAC
signatures. We have neither. We DO have every building block: a hardened inbound
webhook (ADR-029), a canonical user-provisioning path (`keka_client::upsert_employee`
→ `user_create_user`/`user_update_user`), a bearer-token raw-HTTP endpoint with
real status codes (xAPI LRS), a user CRUD facade with the 17 `open_*` fields
(`user_manager`), tenant/customer scoping, rate limiting, and no-PII request
logging.

**Key constraint discovered in recon:** Moodle's web-service layer always answers
HTTP 200 with exception JSON — SCIM clients require real HTTP status codes
(201/204/404/409, `ETag`, `PATCH`). Therefore **SCIM cannot be a set of
`external_function`s**; it must be a raw-HTTP router modeled on
`sentientia_xapi/lrs/statements.php` (`NO_MOODLE_COOKIES` + `AJAX_SCRIPT`
bootstrap, bearer auth, JSON responses with genuine status codes).

## 2. Decision

Ship Phase 2.4 as **three waves inside `local_sentientia_api`** (no new plugin —
API surface consolidation), all default-OFF sub-flags under `sentientia.api.enabled`:

### Wave A — outbound webhooks (build first: highest reuse, self-contained)
- **Subscription registry** `local_sentientia_api_whsub`:
  `{id, customerid, tenantroot, url, secret, events (csv), enabled, lastsuccess,
  timecreated, timemodified}` — per-subscription HMAC secret (fixes the
  ADR-029 single-global-secret shape).
- **Event capture**: observers on `\core\event\course_completed`,
  `user_enrolment_created`, and the certificate-issued event (mirror
  `sentientia_xapi/db/events.php` wiring; observers no-op when flag OFF).
- **Delivery queue** `local_sentientia_api_whdel`:
  `{id, subid, eventname, payload, status (queued|sent|failed|dead), attempts,
  nextattempt, lasterror, timecreated, timeupdated}` — the whatsapp `send_log`
  shape PLUS the missing `nextattempt` backoff column; `idx_status_nextattempt`.
- **Drain task** (scheduled, every minute, `'disabled' => 1` registered — the
  ADR-029 triple-opt-in): sends due rows, exponential backoff (1m→5m→30m→2h→dead
  after 5 attempts), dead-letter visible on an admin page.
- **Signing**: `X-Sentientia-Signature: t=<unix>,v1=<hmac_sha256(t "." body, secret)>`
  + documented 5-minute replay window (consumer-side check).
- **SSRF guard**: deliveries via `\core\http_client` (curl security helper honours
  `blocked_hosts`/`allowed_ports`); subscription URLs validated https-only at save.
- Admin UI: subscriptions CRUD (capability `local/sentientia_api:webhooks_manage`,
  RISK_CONFIG) + delivery log viewer. No payload PII beyond event basics
  (userid, courseid, timestamps — same minimalism as `request_log`).
- Flag: `sentientia.api.webhooks.enabled` (requires master `sentientia.api.enabled`).

### Wave B — SCIM 2.0 Users
- **Router** `local/sentientia_api/scim/v2.php` (raw-HTTP): `/ServiceProviderConfig`,
  `/ResourceTypes`, `/Schemas`, `/Users` (GET list + POST), `/Users/{id}`
  (GET/PUT/PATCH/DELETE). Real status codes + SCIM error objects
  (`urn:ietf:params:scim:api:messages:2.0:Error`).
- **Auth**: bearer tokens per IdP client in `local_sentientia_api_scimcli`
  `{id, customerid, tenantroot, name, token_hash (sha256), enabled, lastseen,
  timecreated}` — the xAPI `authenticator` pattern. Token → (customer, tenantroot);
  every operation tenant-scoped via `tenant::path_filter`.
- **Gate extraction**: pull `base::open_v1()`'s 4-gate sequence (flag → authz →
  rate limit → tenant) into a transport-neutral `api_gate` helper used by both
  the WS base class and the SCIM router (rate-limit key widened to
  `(clientid|userid, window)`).
- **Mapping**: `externalId` ↔ user via new `local_sentientia_api_scimmap`
  `{id, cliid, externalid (191), userid, timecreated}` (⚠ utf8mb4 index-length
  trap: keep externalid ≤191 chars, unique `(cliid, externalid)`).
- **CRUD delegation**: `user_manager::create/update/suspend` — SCIM
  `active:false` → suspend + `destroy_user_sessions` (the ADR-029 leaver flow);
  DELETE = suspend (soft), never hard-delete from an IdP call.
- **Filter/pagination**: MVP filter `userName eq "x"` and `externalId eq "x"`
  only (what Entra actually sends); `startIndex` 1-based + `count`;
  `ListResponse` envelope. Reject unsupported filters with 501 + scimType.
- Flag: `sentientia.api.scim.enabled`; capability `local/sentientia_api:scim`.

### Wave C — SCIM Groups + attestation
- `/Groups` mapped to `local_sentientia_org` (read) + cohort membership (write),
  PATCH add/remove members.
- **Deprovisioning-attestation report** (ADR-028 explicit ask): admin report of
  IdP-initiated suspensions with timestamps — the audit artefact buyers ask for.

## 3. Non-goals (this phase)
- SCIM bulk operations, sorting, complex filters (`and`/`or`) — 501 responses.
- Webhook payload templating/transformation — fixed JSON schema v1.
- Inbound SCIM from multiple IdPs per tenant — one client row each, no federation logic.

## 4. Security posture
Fail-closed everywhere (flag absent/platform missing = 403); hashed tokens only;
per-subscription secrets; constant-time compares; https-only + SSRF-guarded
egress; no payload bodies in logs; deliveries and SCIM requests logged to the
existing no-PII `request_log`/new delivery log with retention pruning added to
the nightly `task\cleanup`.

## 5. Test plan (PHPUnit, per wave)
Wave A: subscription CRUD caps; observer no-op with flag OFF; queue insert on
event; drain sends + backoff schedule + dead-letter; signature vector test
(known key/body → known HMAC); SSRF rejection (http://, private IP).
Wave B: auth (bad/absent/disabled token → 401); tenant isolation (client A cannot
read tenant B's users — the bizlms_fixture pattern); POST creates via
user_manager with open_* fields; PATCH active:false suspends + kills sessions;
filter eq + pagination; error-object shapes.
Wave C: org read mapping; membership PATCH; attestation rows.

## 6. Consequences
- `local_sentientia_api` grows 4 tables + 2 raw-HTTP entry points; version bumps
  per wave; en+hi strings per wave (parity gate).
- The `open_v1()` gate refactor touches the 7 existing v1 endpoints — must stay
  behaviour-identical (existing tests are the harness).
- Buyers get the two loudest enterprise-integration checkboxes; the KeKa CSV/webhook
  path remains the airpay-internal JML route (SCIM is for external customers'
  IdPs — both delegate to the same provisioning primitives).
