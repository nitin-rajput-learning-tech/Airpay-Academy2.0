# UAT Security Posture — academy2.airpay.ninja
Date: 2026-09-03 | Moodle: 5.2 (Sentientia package) | PHP: 8.3 | MySQL: 8.4 (RDS) | Apache: 2.4.58 (Ubuntu, behind AWS ALB + sslproxy)

Scope: cutover plan Phase 2.4 ("Security posture") from
`moodle-enhancement/docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md`. Covers (1) code-level
OWASP review of the internet-facing / unauthenticated / token-authenticated entry points,
(2) a read-only runtime probe of the public surface, (3) a configuration checklist against
`docs/security/ENTERPRISE-IDENTITY-PACK.md` §2 and standard Moodle hardening. No code was
modified; nothing was committed.

---

## Summary

| | Count |
|---|---|
| **BLOCKING (Critical)** | **2** |
| **BLOCKING (High)** | **4** |
| Non-blocking (Medium) | 5 |
| Non-blocking (Low) | 1 |

**Blocking items must close before this codebase is promoted to `production` /
`www.airpay.academy`.** Neither Critical finding is exploitable against real
money or real employee data *today* on UAT (fake data, sandbox payment
credentials per the Phase 3 checklist) — but both are exploitable exactly as
written the moment this code reaches production, so they block promotion, not
necessarily continued UAT testing.

### Multi-tenant isolation: **FAIL**
C2 below is a direct violation of the BizLMS tenant-isolation invariant this
codebase otherwise enforces carefully (`catalog_manager.php`'s
`sharing_manager::build_catalog_filter_sql()`). The public/guest commerce path
does not use it.

### GDPR / PII-to-external-API check: **PASS**
Reviewed `local_sentientia_integrations\keka_client.php`, the KeKa webhook, and
every entry point in scope: no employee names/emails/salary data are sent to
ElevenLabs, Gamma, or any non-HRMS third party. `onboarding.php` defaults
`consent_marketing` / `consent_leaderboard` to `0` (opt-in, DPDP §7(a)-compliant).
KeKa/webhook secret comparisons use `hash_equals()`; tokens are stored as
SHA-256 hashes, never plaintext.

### Verdict: **CONDITIONAL PASS for continued UAT testing / BLOCK for production promotion**
Required before production: **2 critical, 4 high**.

---

## Code findings

### Critical (BLOCK — production promotion)

| ID | File:Line | OWASP | Vulnerability | Evidence | Exact fix |
|----|-----------|-------|----------------|----------|-----------|
| **C1** | `payment/gateway/airpay/process.php:91` + `payment/gateway/airpay/classes/airpay_helper.php:253-278` + `payment/gateway/airpay/pay.php:151` | A08 (Software/Data Integrity Failures) + A01 | The "secure hash" that gates paid-course fulfilment is a **keyless CRC32** — `crc32(TRANSACTIONID:APTRANSACTIONID:AMOUNT:TRANSACTIONSTATUS:MESSAGE:MID:USERNAME)` — over fields that are either attacker-supplied in the same forged POST (`TRANSACTIONID`, `AMOUNT`, `TRANSACTIONSTATUS`, `MESSAGE`) or **not secret**: `mercid` (`MID`) is echoed in plaintext as a hidden form field on `pay.php:151` (`<input type="hidden" name="mercid" value="...">` — visible via View Source to anyone who starts a checkout), and `username` is a low-entropy merchant config string, not a cryptographic key. `airpay_helper::check_payment()` (the real server-to-server Order Confirmation/Verify API call) is entirely commented out and hardcoded to `return false;` — it is never invoked. The code's own comment already documents this: *"a determined attacker who knows the field values can still forge a matching CRC32."* `order_id` (`ap_orderid`) is also predictable — generated as `time() . '_' . $USER->id` (`airpay_helper.php:92`) — so an attacker can start their own real (cheap or free) checkout, know their own `ap_orderid` without it ever being echoed back to them, then POST a forged callback directly to `process.php` with `TRANSACTIONSTATUS=200` and a locally-computed CRC32, causing `paygw_airpay` to mark the order **paid** and enrol them via the `fee`/`manual` enrol plugin — free enrolment into any paid course, at a payments company, with zero money moved. | Implement the Order Confirmation / Verify API call server-side (`docs.airpay.co.in/v4/payments/order-confirmation/`) inside `process.php` before any `enrol_user()`/`status = 2` write, and require it to independently confirm `status==200` for **this exact orderid AND amount**. Treat `verify_secure_hash()` as a cheap pre-filter only, never as sufficient proof of payment. Also stop deriving `ap_orderid` from `time().'_'.$USER->id` — use `random_bytes()`-backed order ids so they cannot be pre-computed by the payer. |
| **C2** | `local/sentientia_catalog/course.php:23` + `local/sentientia_catalog/classes/commerce.php` (`add_to_cart`, `get_course_price`) + `local/sentientia_catalog/classes/enrolment.php:127-176` (`enrol_now`) | A01 (Broken Access Control) — BizLMS multi-tenant isolation | The guest/member commerce path (course detail, add-to-cart, one-click/"enrol all free") resolves a course with **only** `['id' => $id, 'visible' => 1]` — no tenant / `open_path` scoping. Contrast with `classes/catalog_manager.php`, which correctly gates every browse query through `\local_sentientia_courses\sharing_manager::build_catalog_filter_sql($alias, $viewer_tenant)`. Because `commerce::get_course_price()` treats "no admin-configured price" as **free** by default, and `enrolment::enrol_now()` only checks `visible=1` + free + not-already-enrolled before calling the `manual` enrol plugin, a self-registered **Public-tenant** learner (created via `signup.php`, `open_path='/77'`) can browse to `/local/sentientia_catalog/course.php?id=<Airpay-or-ZEEA internal course id>` (any visible internal course, guessable by sequential id), click "Add to cart" / "Enrol now", and self-enrol into Airpay (id=1) or ZEEA (id=177) internal training — completely bypassing the tenant-sharing model the rest of the plugin suite enforces. This is the exact "IDOR into another tenant's course data" scenario called out in the threat model. | Route `course.php`'s course lookup, `commerce::add_to_cart()`, `commerce::get_course_price()`'s public-catalog callers, and `enrolment::enrol_now()` through the same `sharing_manager` gate used by `catalog_manager.php` — e.g. add a `sharing_manager::is_visible_to_viewer(int $courseid, int $viewertenant): bool` and call it before `MUST_EXIST` in `course.php`, before pushing to session cart in `commerce::add_to_cart()`, and as a hard precondition inside `enrolment::enrol_now()` (never rely on the UI hiding the button — the exploit here is a direct URL/POST, bypassing the UI entirely). |

### High (must fix before production)

| ID | File:Line | OWASP | Vulnerability | Evidence | Exact fix |
|----|-----------|-------|----------------|----------|-----------|
| **H1** | `local/sentientia_users/classes/signup_service.php:72-85` | A07 (Identification & Auth Failures) / CWE-203 | `validate()` returns a distinct `emailexists` error for both the email-uniqueness check and the derived-username check, on the **public**, unauthenticated `/local/sentientia_users/signup.php`. This is a user-enumeration oracle — anyone can test whether an email address already has an Airpay Academy account. | Return a generic error ("If this looks wrong, contact support") for the email-exists case, or accept the submission and email the *existing* address a "someone tried to sign up with your email" notice instead of surfacing the state synchronously in the form. |
| **H2** | `local/sentientia_users/signup.php`, `classes/signup_service.php::register()` | A07 / OWASP API4 (Unrestricted Resource Consumption) | No CAPTCHA and no rate limiting anywhere on the public self-registration path (contrast with `local_sentientia_api`'s SCIM endpoint, which calls `client::rate_check()`). Environment brief confirms reCAPTCHA keys are **not set**. Combined with H1, this endpoint is enumeration- and bulk-signup-friendly today. | Do not enable `activeregistration` in production until reCAPTCHA v2 keys are configured (Phase 3 checklist item, identity pack §3) AND add a per-IP/per-hour throttle inside `signup_service::register()` (mirror `local_sentientia_api\rate_limiter`'s fixed-window pattern). |
| **H3** | `local/sentientia_xapi/lrs/statements.php` (whole file) + `classes/lrs/authenticator.php` | A04 (Insecure Design) / API4 | The xAPI LRS is an internet-reachable, Bearer/Basic-authenticated **write** endpoint (arbitrary JSON statement bodies, persisted to DB) with **no rate limiting** and **no failed-auth throttling**, unlike the SCIM endpoint in the same plugin family (`scim/handler.php` calls `client::rate_check($client)` on every request). A leaked/weak client credential — or simple credential-stuffing against `check_bearer()`/`check_basic()` — has no cost attached. | Add a `rate_limiter`-style gate keyed by the authenticated client id before processing `POST`/`PUT` in `statements.php` (budget + window, same pattern as `local_sentientia_api\rate_limiter`), and add exponential backoff / an ALB WAF rate rule on repeated 401s from the same source IP. |
| **H4** | `local/sentientia_live/stream.php:159-227` | A04 / Availability | SSE connections are held open per audience member for up to `$max_duration_seconds = 300`, each pinning one Apache worker/process for the connection's lifetime (`sleep(1)` polling loop). When `live.allow_anonymous` is on, this endpoint is reachable pre-auth (token-gated, but `join_token` is high-entropy so the practical risk is *volumetric* flooding, not guessing). A modest number of concurrent connections can exhaust the Apache worker pool behind the ALB. | Cap concurrent SSE connections per IP at the ALB / Apache layer (mod_qos, or an ALB WAF rate-based rule), and confirm the Apache MPM worker count budget accounts for `max_duration_seconds=300 × expected concurrent audience size` before Stage B / production scale testing. |

### Medium (fix next sprint)

| ID | File:Line | OWASP | Issue | Fix |
|----|-----------|-------|-------|-----|
| **M1** | `local/sentientia_integrations/classes/keka_client.php:253-254` (`get_employee`) | A03-adjacent (Injection hygiene) | Webhook-supplied `employeeId` is interpolated unescaped into an outbound URL path (`"/v1/hris/employees/{$employee_id}"`) sent to the fixed KeKa base host. Not full SSRF (host is fixed), but a crafted id could alter the request path/query against KeKa's API. | `rawurlencode($employee_id)` before interpolation; validate against KeKa's documented id format first. |
| **M2** | `theme/sentientia/layout/frontpage.php:38-48` | A01 (latent) | The public landing page's own comment says "PUBLIC TENANT ONLY... no all-tenant fallback," but code falls back to **site-wide** course/user counts when the BizLMS `open_path` column is absent. Dormant on UAT (column present) but contradicts the stated design intent and would leak aggregate Airpay/ZEEA counts on a future non-BizLMS Sentientia deployment. | Remove the site-wide fallback branch, or fail closed (render `0`/hide the stat) when `open_path` is absent, matching the file's own documented intent. |
| **M3** | Runtime (see probe below) | A05 (Security Misconfiguration) | `/lib/upgrade.txt` and `/admin/environment.xml` are served publicly (200 OK), letting an unauthenticated visitor fingerprint the exact Moodle branch/patch level. `/composer.json`, `/README.txt`, `/CHANGES.md` are correctly blocked (404). | Add `Require all denied` (Apache) or an ALB path rule blocking `/lib/upgrade.txt`, `/admin/environment.xml`, and `/version.php` from external access. |
| **M4** | Runtime (see probe below) | A05 | No `Strict-Transport-Security`, `X-Content-Type-Options`, `Content-Security-Policy`, `Referrer-Policy`, or `Permissions-Policy` headers observed on any probed path. `X-Frame-Options: sameorigin` is present. HSTS is already tracked in the Phase 2.4/UAT-ASKS list; the others are not yet tracked. | Add all five headers at the Apache/ALB layer (cannot edit Moodle core per CLAUDE.md §H): `Strict-Transport-Security: max-age=31536000; includeSubDomains`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, a baseline `Content-Security-Policy` (start report-only), `Permissions-Policy: geolocation=(), camera=(), microphone=()`. |
| **M5** | `payment/gateway/airpay/process.php:27` | A01 (design note) | `require_login()` is commented out by design (gateway redirect landing page) — fulfilment is entirely order/hash-driven with no session binding to `$order->userid`. Not independently exploitable beyond C1, but worth recording: once C1's Verify-API fix lands, confirm the Verify API response is also matched against the order's original `userid`/`amount`, not just `orderid`. | Covered by the C1 fix — include `userid` + `amount` cross-check against the Verify API response, not just `orderid`. |

### Low (tech debt)

| ID | Issue | Fix |
|----|-------|-----|
| **L1** | `OPTIONS /` returns `200 OK` with a full HTML body instead of `204`/`405` + an `Allow` header. Cosmetic method-handling laxity; not independently exploitable. | Low priority — align with standard REST method semantics if/when the ALB's method routing is revisited. |

---

## Runtime probe results

Read-only `GET`/`HEAD`/`OPTIONS` only. No login attempts, no form submissions, no payloads, no brute force. Run 2026-09-03 against `https://academy2.airpay.ninja`.

### `GET /`
```
HTTP/1.1 200 OK
Server: Apache/2.4.58 (Ubuntu)
Content-Type: text/html; charset=utf-8
Content-Language: en
X-UA-Compatible: IE=edge
Cache-Control: no-store, no-cache, must-revalidate, no-transform
Pragma: no-cache
Expires: Mon, 20 Aug 1969 09:23:00 GMT
Accept-Ranges: none
X-Frame-Options: sameorigin
Set-Cookie: MoodleSession=rla64n38grtmu969dh5gsbi8r5; path=/; secure; HttpOnly; SameSite=Lax
Vary: Accept-Encoding
```
No `Strict-Transport-Security`, `X-Content-Type-Options`, `Content-Security-Policy`,
`Referrer-Policy`, or `Permissions-Policy`. `MoodleSession` cookie correctly carries
`secure`, `HttpOnly`, `SameSite=Lax`.

### `OPTIONS /`
Returns `200 OK` with the same headers/body as `GET` (see L1) — no `Allow` header, method not restricted.

### `GET /login/index.php`
Same header profile as `/`; no additional caching hardening beyond the standard `no-store` set. Session cookie flags identical (good).

### `GET /local/sentientia_catalog/public.php`
```
HTTP/1.1 200 OK
Cache-Control: private, pre-check=0, post-check=0, max-age=0, no-transform
X-Frame-Options: sameorigin
Set-Cookie: MoodleSession=...; path=/; secure; HttpOnly; SameSite=Lax
```
Confirms the endpoint is reachable pre-auth as designed (guest catalog).

### `GET /local/sentientia_api/scim/v2.php`
```
HTTP/1.1 401 Unauthorized
Content-Type: application/scim+json; charset=utf-8
WWW-Authenticate: Bearer realm="Sentientia SCIM"

{"schemas":["urn:ietf:params:scim:api:messages:2.0:Error"],"status":"401","detail":"A valid bearer token is required."}
```
Correct fail-closed behaviour, matches code review (handler gates bearer → flags → rate limit → route).

### `GET /local/sentientia_xapi/lrs/statements.php`
```
HTTP/1.1 503 Service Unavailable
Content-Type: application/json; charset=utf-8
X-Experience-API-Version: 1.0.3

{"error":"The xAPI LRS is currently disabled. Contact your administrator."}
```
Confirms `sentientia.xapi.lrs_endpoint_enabled` flag is OFF on UAT, as expected per the Phase 3 checklist (integrations land one at a time).

### `GET /r.php/not/a/valid/request`
```
HTTP/1.1 404 Not Found
Content-Type: text/html;charset=UTF-8
```
Generic minimal 404 body (no stack trace, no debug output, no Moodle version string). Note: the response template does not match Moodle's own themed error page — possibly served by the ALB/edge layer rather than reaching PHP; not a security issue, just an observation worth confirming with DevOps.

### Additional file-disclosure spot checks
| Path | Result |
|---|---|
| `/version.php` | 200, 0 bytes (expected — no output, not a leak) |
| `/composer.json` | 404 (correctly blocked) |
| `/README.txt` | 404 (correctly blocked) |
| `/CHANGES.md` | 404 (correctly blocked) |
| `/admin/environment.xml` | **200, ~194 KB** — see M3 |
| `/lib/upgrade.txt` | **200, ~184 KB** — see M3 |

### TLS (`openssl s_client -connect academy2.airpay.ninja:443 -brief`)
```
Protocol version: TLSv1.3
Ciphersuite: TLS_AES_128_GCM_SHA256
Peer certificate: CN=*.airpay.ninja
Verification: OK
Peer Temp Key: X25519, 253 bits
```
Certificate: `CN=*.airpay.ninja`, issued by Amazon RSA 2048 M04, valid
2025-11-13 → 2026-12-12 (ACM-managed, on the ALB — expected).

**TLS 1.0 / 1.1 downgrade test: inconclusive.** The local probe host's OpenSSL
3.5.5 refuses to even attempt a TLS 1.0/1.1 handshake (`no protocols available`
at the library level) — this is a client-side limitation, **not** evidence the
server rejects legacy protocols. Recommend an independent check with `nmap
--script ssl-enum-ciphers` or Qualys SSL Labs from a host/library still
capable of attempting TLS 1.0/1.1, to get an authoritative answer.

---

## Configuration checklist

| Item | Current UAT state | Recommended | How to set | Also applies to production? |
|---|---|---|---|---|
| HSTS at the LB | Absent (confirmed by probe) | `Strict-Transport-Security: max-age=31536000; includeSubDomains` (add `preload` only after confirming all subdomains are HTTPS-only) | ALB listener rule / Apache `Header always set` on the origin | Yes — already tracked in `UAT-ASKS-2026-09-03.md` item 4 |
| `ServerTokens` / `ServerSignature` | `Server: Apache/2.4.58 (Ubuntu)` disclosed (confirmed by probe) | `ServerTokens Prod`, `ServerSignature Off` | Apache `httpd.conf` / `apache2.conf`, reload | Yes — already tracked (`UAT-ASKS` item 4) |
| MFA sequence | Not yet run on UAT ("ninja rehearsal is the first environment" per identity pack) | Run the §2 sequence in order: `tool_mfa` enabled → `factor_grace` enabled (30-day grace) → `factor_totp` enabled+weighted → purge caches. **Grace factor first, always**, to avoid locking out admins | `admin/cli/cfg.php` per commands in `ENTERPRISE-IDENTITY-PACK.md` §2 | Yes — production runs it second, after the ninja rehearsal succeeds |
| reCAPTCHA | Keys not set (per environment brief) | Configure reCAPTCHA v2 site/secret keys for `academy2.airpay.ninja`, scoped to the Public tenant registration form only | Site administration → Security → Site security settings | Yes — separate keys for `www.airpay.academy` |
| Session timeout | Not directly determinable from the repo/docs reviewed | Set an explicit, documented value (Moodle default is 8h `sessiontimeout`); shorter (e.g. 2-4h) for admin sessions if `tool_mfa`/session policy warrants it | Site administration → Security → HTTP security, or `admin/cli/cfg.php --component=core --name=sessiontimeout --set=<seconds>` | Yes |
| Upload limits | `post_max_size`/`upload_max_filesize ≥ 100M`, `memory_limit ≥ 512M`, `max_input_vars = 5000` (per `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md`) | Confirmed adequate for SCORM package uploads; re-verify after any PHP-FPM pool resize (Phase 2.3 capacity baseline) | `php.ini` | Yes |
| `cronclionly` | Not directly determinable | Set `1` (cron only runs via CLI, not a guessable web URL) if not already | `admin/cli/cfg.php --component=core --name=cronclionly --set=1` | Yes |
| Login lockout threshold | Not directly determinable | Confirm Moodle's built-in account-lockout is active (default: escalating delay after repeated failures); pair with H2's signup throttle recommendation | Site administration → Security → Site security settings ("Lockout" section) | Yes |
| Web services / mobile service exposure | Not scoped in this pass — SCIM/xAPI/LTI are the reviewed API surfaces, both correctly gated behind feature flags and auth | Confirm the core mobile web service (`local_mobile`/`moodle_mobile_app`) is disabled unless Phase X.1 mobile work is active, per `MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` | Site administration → Plugins → Web services | Yes |
| Guest access | `forcelogin=0` (intentional — public landing page + catalog by design) | Acceptable given the reviewed guest surfaces (`public.php`, `course.php`, `cart.php`) are read-mostly and gated by `sesskey` on writes — **contingent on C2 being fixed**, since guest→signup→cross-tenant-enrol is the actual risk, not guest browsing itself | N/A (working as designed) | Yes, once C2 is fixed |
| Self-registration | `activeregistration` gate exists in `signup_service::is_enabled()`, defaults to admin opt-in | Do not enable in production until H1 (enumeration) + H2 (no CAPTCHA/rate-limit) are fixed | Site administration → Plugins → Authentication, or the plugin's own setting | Yes |
| Privacy tool defaults | Not scoped in this pass | Confirm Moodle's privacy/GDPR tool (`tool_dataprivacy`) has a data retention policy set before Stage B (real user data migration) | Site administration → Users → Privacy and policies | Yes — more urgent for production (real employee data) |
| Log retention | Not directly determinable | Set an explicit Moodle log-store retention period aligned with Airpay's data-retention policy; confirm CloudWatch/ALB access-log retention separately (ties to Phase 2.5 observability item) | Site administration → Reports → Logging, `tool_log` settings | Yes |
| DB credentials | `db_user` = `rds_superuser` (app-scoped user + rotation planned but **deferred** by Nitin until after install); credentials were emailed in **plaintext** on 2026-08-20 (per `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md`) | Rotate to a least-privilege app DB user (not the RDS superuser) before Stage B; rotate the password that was emailed in plaintext regardless of channel used going forward; use a secrets manager (AWS Secrets Manager / SSM Parameter Store) for future credential delivery | RDS console + `config.php` `$CFG->dbuser`/`$CFG->dbpass` update, then purge caches | Yes — this is the higher-consequence version of the same finding for production |
| `config.php` perms / location | Confirmed outside docroot (per environment brief) | Good as-is; confirm file mode is `640` or stricter, owned by the web server user only | `chmod`/`chown` on the app host | Yes |

---

## Recommended remediation order

1. **C1 — Payment forgery** (blocks any production payment flow; also blocks the Phase 3 "sandbox purchase, verifier fail-closed path" checklist item from truly passing — right now the "fail-closed path" isn't actually enforced end-to-end).
2. **C2 — Cross-tenant self-enrolment** (blocks production; also the highest-value UAT regression test to add once fixed — attempt the exact exploit path as a persona test).
3. **H3 / H4 — Rate limiting on xAPI LRS and SSE stream** (cheap to add, closes an availability gap before Phase 2.3's capacity baseline and Phase 4's Stage B rehearsal put real load on these paths).
4. **H1 / H2 — Signup enumeration + CAPTCHA/rate-limit** (blocks safely enabling `activeregistration` in production; already partially tracked via the Phase 3 reCAPTCHA checklist item — extend it to cover H1's enumeration fix too).
5. **M3 / M4 — Info disclosure + missing security headers** (cheap, Apache/ALB-layer only, no code change; bundle with the already-planned HSTS + `ServerTokens Prod` work in Phase 2.4/UAT-ASKS item 4).
6. **M1 / M2 / M5 / L1** — next sprint, no urgency for UAT continuation.
7. Run the **MFA configuration sequence** (identity pack §2) on the ninja environment as planned — this audit did not find anything that would make the sequence unsafe to run now.
8. Re-run this audit's C1/C2 sections specifically (not a full re-audit) once fixes land, before Phase 4 (Stage B rehearsal) and before any production deploy.
