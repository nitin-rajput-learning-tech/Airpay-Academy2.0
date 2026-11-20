# Sentientia LMS: Security Architecture and Threat Model

**Subtitle:** Architecture, trust boundaries, controls, STRIDE threat model, OWASP ASVS 4.0 level 2 mapping and proposed scope for the independent penetration test and code review

| Field | Value |
|---|---|
| Prepared for | Information Security team, Airpay Payment Services; SISA (external security assessor, under the engagement NDA) |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 21 November 2026 |
| Status | Draft for review |
| Classification | Confidential: Airpay Payment Services internal. Not for external distribution. Release to SISA is permitted under the engagement NDA, for the security review only. |

---

## 0. Document control

### 0.1 Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 21 November 2026 | Security architecture writer for the Sentientia LMS programme | First issue for Information Security and SISA review |

### 0.2 Reviewers to sign off

| Role | Organisation | What the sign-off covers |
|---|---|---|
| Head of Information Security | Airpay Payment Services | Architecture, threat model, risk ratings, scope for SISA |
| Information Security analyst (application security) | Airpay Payment Services | ASVS mapping, findings register, test accounts |
| DevOps lead | Airpay Payment Services | Environment facts, backups, logging, secrets handling, items marked "to be confirmed (owner: DevOps lead)" |
| Data Protection Officer | Airpay Payment Services | Personal-data items cross-referenced to the DPDP pack |
| Head of Learning & Development (product owner) | Airpay Payment Services | Feature status, flag posture for testing, test-data decision |
| Engagement lead | SISA | Scope, rules of engagement, test windows |

### 0.3 Related documents

| Document | Location |
|---|---|
| Engineering fact base of 20 November 2026 (numbers used in this document) | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` and domain files `20-flags-roles.md`, `30-apis.md`, `40-data-privacy.md`, `50-ai.md`, `60-eng-qa-infra.md`, `70-ui-surfaces.md` |
| UAT security posture review, 3 September 2026 | `moodle-enhancement/docs/security/UAT-SECURITY-POSTURE-2026-09-03.md` |
| Payment-response verification fix record | `moodle-enhancement/docs/security/2026-06-02-airpay-payment-verification-fix.md` |
| Enterprise identity pack (SSO, MFA, bot mitigation) | `moodle-enhancement/docs/security/ENTERPRISE-IDENTITY-PACK.md` |
| MFA enforcement runbook | `moodle-enhancement/MFA-ENFORCEMENT-RUNBOOK.md` |
| VAPT programme proposal | `moodle-enhancement/docs/security/VAPT-PROGRAM.md` |
| ISO/IEC 27001:2022 readiness self-assessment | `moodle-enhancement/docs/security/ISO-27001-2022-READINESS.md` |
| Cross-tenant authority ADR | `moodle-enhancement/docs/adr/ADR-031-cross-tenant-authority.md` |
| DPDP technical design and security review (same pack series) | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/` |

---

## 1. Executive summary

### 1.1 What this document is

This is the security architecture and threat model of Sentientia LMS, the multi-tenant learning platform that Airpay Payment Services is building on Moodle 5.3 LTS. Airpay Academy is its first deployment. The document gives the Information Security team and SISA one place to see how the system is built, where the trust boundaries are, which controls exist (with evidence paths in the repository), where the known gaps are, and what we propose SISA tests.

Sentientia is **not live**. The production site, www.airpay.academy, still runs the legacy BizLMS platform on Moodle 4.1.2. Sentientia runs on the UAT environment, academy2.airpay.ninja (Moodle 5.3, PHP 8.3.6, MySQL 8.4.9), and on development machines. UAT is a fresh install that holds synthetic test personas and seeded demo data only; it holds no production-derived data (`docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row B3; `docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md`). The development environment holds a test import of a production backup. That backup contains real personal data, such as e-mail addresses, so development workstations still need data-protection controls. Cutover happens in stages, after UAT sign-off and a rehearsal against a fresh live backup.

### 1.2 Key facts

| Fact | Value | Source |
|---|---|---|
| Product components built by us | 57 (47 local plugins, 6 blocks, 1 enrolment plugin, 1 payment gateway, 1 quiz access rule, 1 theme) | `_facts/00-FACTS-INDEX.md` section 2.1 |
| Tenants | 3 (Airpay (internal employees), Public (consumer storefront), ZEEA Mafunzo (Tanzania)); 1 customer | `local/sentientia_platform/classes/tenant.php` (`VALID_TENANTS = [1, 77, 177]`) |
| External web-service functions defined by us | 174 (68 read, 106 write) in 24 components; all require login; 67 distinct capabilities gate them; 5 declare no capability | `_facts/30-apis.md` |
| Raw-HTTP and unauthenticated entry points outside the web-service layer | 19 files in 10 components by our code scan: 15 in 9 local plugins (SCIM, LTI 1.3, xAPI, HRMS webhook, PWA, live-session stream, leaderboard stream, calendar ICS feed, cart payment callback, e-mail template preview) and 4 payment-gateway pages reachable without login. The fact base also counts 19 files in 10 components; the lists differ by one login-protected file each (section 8.1) | Code scan of the canonical tree; `_facts/30-apis.md` section 8 |
| Logged-out (public) surfaces | 11 | `_facts/70-ui-surfaces.md` section 3 |
| Capabilities declared | 181; 2 roles created by our code | `_facts/20-flags-roles.md` |
| Feature flags | 142 registered (141 in plugin registries plus 1 in the theme registry), 13 default ON, 129 default OFF | `_facts/00-FACTS-INDEX.md` sections 2.3 and 7 |
| Tables created by our plugins | 185; 111 hold personal data by column name; 5 hold secrets or credential hashes | `_facts/40-data-privacy.md` |
| Moodle core files edited on 5.3 | 1 (`lib/form/form.js`, ADR-044), plus 2 additive overlay files; 13 records in the core-modifications ledger | `_facts/00-FACTS-INDEX.md` section 2.12 |
| Automated security-relevant gates | 20 pre-commit checks; 15 CI jobs; 2 blocking PHPUnit groups (`tenant_isolation` on 135 test files, `privacy_erasure`) | `.claude/hooks/pre-commit.sh`; `.github/workflows/ci.yml` |
| Independent penetration test | **Not performed.** The UAT review of 3 September 2026 was an internal code review plus a read-only probe | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md` |

### 1.3 Security position in one paragraph

The application layer has a deliberate tenant-isolation model (ADR-031): one function decides cross-tenant authority, queries fail closed when a tenant cannot be resolved, writes check the target's tenant, and a CI gate blocks the unbounded tenant-path filters that caused repeated defects in September 2026. Input, output and CSRF handling follow Moodle's APIs, and pre-commit checks reject raw superglobal access and hard-coded credentials. The weakest areas are payments, AI data flows, identity hardening and operations. The code holds two separate Airpay payment integrations. The native order cart (`local_sentientia_cart`) posts to Airpay and accepts an unauthenticated server-to-server callback that must carry a custom secret-suffixed SHA-256 `checksum` field. That scheme has not been checked against Airpay's contract or sandbox, and it does not match the response hash Airpay documents (the CRC32 `ap_SecureHash` cited in the gateway code), so genuine Airpay callbacks would probably all be rejected. The callback also writes every payload to the server log before verifying it. The Moodle payment gateway (`paygw_airpay`) uses a keyless CRC32 response checksum, has no server-side order confirmation, and its payment page has no login or CSRF check and writes profile values into HTML unescaped. Which path is active on UAT, and which survives cutover, is to be confirmed. The AI assistant chat, ON by default, calls the AI provider directly with the learner's name, designation, organisation and course list whenever its own key is set, outside the AI gateway's ledger and spend cap; on UAT that context belongs to synthetic personas, but after cutover it would be real personal data. The proctoring chunk-registration web service on UAT is gated only by a capability every authenticated user holds. Identity hardening is incomplete (MFA not enforced, no CAPTCHA or rate limit on self-registration), operations lack alerting, backup RPO/RTO, a real-volume restore test and a database backup path from the UAT shell, and no independent test has been done.

### 1.4 Decisions and actions asked of the reader

The same numbered list appears in section 22, with the form each answer should take.

| # | Ask | Of whom | By when |
|---|---|---|---|
| A1 | Review this document; accept or re-rate the threat ratings (section 14) and the open findings (section 19) | Head of Information Security | 11 December 2026 |
| A2 | Agree the SISA scope in section 20 (in-scope URLs, components, flag passes, exclusions, windows) | Head of Information Security with SISA | 4 December 2026 |
| A3 | Confirm the test dataset: SISA tests against the synthetic UAT dataset (test personas and seeded demo data from the fresh install), and no production-derived data is loaded onto UAT before or during the test windows (the Stage B rehearsal stays on its own target) | Data Protection Officer with Head of Information Security | 4 December 2026 |
| A4 | State which Airpay payment path is active on UAT and which survives cutover (`local_sentientia_cart` or `paygw_airpay`), and decide whether S-01 and S-18 are fixed before the test or tested as they are | Head of Learning & Development with the Airpay payments team | 4 December 2026 |
| A5 | Provide infrastructure facts: WAF, region, encryption at rest, backup policy, log retention, database account pattern and credential delivery for the production target, renewal of the UAT certificate (expires 12 December 2026), `ServerTokens`, VAPID master key, whether an S3 bucket and credentials are configured for proctoring on UAT, Moodle security settings (lockout, password policy, session timeout, cron CLI only, web-service protocols and token creation, mobile service) | DevOps lead | 11 December 2026 |
| A6 | Agree the findings register format and the remediation time limits (section 21) | Head of Information Security with SISA | Before test start |
| A7 | Export the UAT Switchboard (all flags and overrides), and confirm whether the AI assistant's own provider key is set on UAT (remove it for the test window if it is) | Head of Learning & Development | Before test start |
| A8 | Issue test accounts and machine clients (section 20.4) | Head of Learning & Development with IT | Before test start |
| A9 | Snapshot the UAT database before and after each test window | DevOps lead | Each window |

### 1.5 Top risks

| # | Risk | Rating (proposed) | Section |
|---|---|---|---|
| 1 | Payment integrity on both Airpay paths. `paygw_airpay`: keyless CRC32 response checksum, no server-to-server confirmation, predictable order ids, the response amount is stored, and a payment page without login, CSRF check or output escaping. `local_sentientia_cart`: an unauthenticated callback whose checksum field does not match the response hash Airpay documents, so genuine callbacks would probably fail, and which logs every payload before verifying it. Which path is active is to be confirmed | Critical for `paygw_airpay` if reachable; High for the cart path until validated (both block cutover with real payments) | 3.4, 19 (S-01, S-18, S-19) |
| 2 | Account takeover of an administrator: MFA is installed but not enforced; break-glass access is password-only | High | 4.4, S-04 |
| 3 | Unknown resilience: no alerting, no defined RPO/RTO, and the UAT 5.3 upgrade ran without a database backup | High | 12, 13 |
| 4 | Personal data sent to an external AI provider outside the AI gateway: the default-ON assistant chat calls the provider directly whenever its own key is set (key state on UAT to be confirmed; UAT holds synthetic personas only) | High at cutover if the direct path and a key remain; Low on UAT today | 8.3, S-22 |
| 5 | Public self-registration has no CAPTCHA and no rate limit (enabled state on UAT to be confirmed) | Medium | 4.2, S-02 |
| 6 | No independent test has been done; all assurance so far is self-assessment | High (assurance gap) | 18 |

---

## 2. Scope, method and status labels

### 2.1 Scope

In scope: the Sentientia product layer (57 components), the Moodle 5.3 core it runs on (as a dependency), vendored third-party plugins we ship, the UAT environment, the development and CI pipeline, and integrations coded in the product.

Out of scope for this document: the legacy production site (BizLMS on Moodle 4.1.2), which Sentientia replaces and which is not patched in place; corporate IT controls (endpoint security, identity provider configuration, AWS account governance) except where they appear as dependencies.

### 2.2 Method

1. Facts were taken from the shared fact base of 20 November 2026 and from the repository at the head of the work branch plus the uncommitted working tree of that day.
2. More than 40 claims were checked by reading the code. Examples: `tenant.php` helpers and constants (including `path_descendant_filter()` on an empty path); both payment paths (`payment/gateway/airpay/process.php`, `pay.php`, `classes/external/get_form.php`, `classes/airpay_helper.php`; `local/sentientia_cart/checkout.php`, `callback.php`, `return.php`, `classes/gateway/airpay_gateway.php`, `classes/callback_logger.php`); SCIM token hashing in `local/sentientia_api/classes/scim/client.php`; webhook signing in `classes/webhooks/signer.php`; the HRMS webhook secret comparison; xAPI credential checks in `local/sentientia_xapi/classes/lrs/authenticator.php`; the calendar feed `local/sentientia_calendar/ics.php`; VAPID key storage in `local/sentientia_pwa/classes/vapid_key_manager.php`; the assistant's `classes/external.php` and `classes/ai_client.php`; the proctoring `upload_chunk` web service at the UAT commit and now; upload handling in `local/sentientia_users/photo.php`, `bulk_import.php` and `bulk_csv.php`; the web-server template; the production deploy workflow.
3. Raw-HTTP entry points were recounted by scanning the canonical tree for `NO_MOODLE_COOKIES`, `AJAX_SCRIPT`, `NO_DEBUG_DISPLAY` and `READ_ONLY_SESSION`, and by reading every top-level file in `local/sentientia_api/lti/` and `payment/gateway/airpay/`. The payment-form web service (`paygw_airpay_get_form`) was read, not executed; it cannot run (section 8.2).
4. No code was executed against UAT for this document. Behaviour on UAT is stated only where an earlier dated probe or test recorded it.
5. Framework references (OWASP ASVS 4.0.3, OWASP Top 10 2021, STRIDE, CWE, Moodle security guidance) are cited in Appendix B. We write "mapped against", never "compliant with".

### 2.3 Status labels used

| Label | Meaning in this document |
|---|---|
| **Available (UAT)** | In the code deployed on UAT (commit `af6e65c0e`) |
| **Built (verified on development)** | Code changed after the UAT commit; verified on the development environment only |
| **Built (behind a feature flag, off by default)** | Shipped dark; whether a flag is ON on UAT is to be confirmed from the UAT Switchboard (owner: Head of Learning & Development) |
| **In progress** | Code started, not complete |
| **Planned** | In the roadmap; no code yet |
| **Not planned** | Considered and rejected; the reason is stated |

A **control** carries one of the labels above when it is part of the product code. Three further labels describe the state of controls that are not product features:

| Control label | Meaning in this document |
|---|---|
| **In place (repository or process)** | A control that lives in the repository, the CI service or an engineer's workstation (for example a pre-commit check, a `.gitignore` rule or a CI secret). It has no UAT state |
| **To be confirmed** | The control depends on a server or administrator setting that the repository does not record; the owner role is named |
| **Gap** | The control does not exist; it is tracked as a finding in section 19 |

---

## 3. System context and architecture

### 3.1 Components and hosting (UAT)

| Layer | Component | Facts | Status |
|---|---|---|---|
| Client | Browser (desktop and mobile web) | Learners, managers, trainers, authors, administrators; guest visitors on the public storefront | Available (UAT) |
| Client | Progressive Web App shell and Web Push | Service worker, manifest, push subscriptions (`local_sentientia_pwa`) | Built (behind a feature flag, off by default) |
| Client | Native mobile app | ADR-040 (Proposed); learner mobile API planned | Planned |
| Edge | AWS Application Load Balancer, TLS termination | Test of 24 September 2026 with a client that offers legacy protocols: TLS 1.0 and 1.1 refused by the server; TLS 1.2 accepted with ECDHE-GCM suites only; TLS 1.3 accepted (`docs/visual-evidence/2026-09-24/README.md`). Amazon-issued certificate, valid chain, expires 12 December 2026; automatic renewal to be confirmed (owner: DevOps lead; R15) | Available (UAT) |
| Edge | Web application firewall, CDN | None recorded in the repository | To be confirmed (owner: DevOps lead) |
| Web | Apache 2.4 with PHP-FPM (PHP 8.3.6) on one application server (2 vCPU, 2 GB RAM) | Security headers and file-disclosure blocks set by `moodle-enhancement/deploy/moodle-htaccess.template` (since 4 September 2026) | Available (UAT) |
| Application | Moodle 5.3 core plus 57 Sentientia components plus vendored plugins | Standalone theme `theme_sentientia`; `tool_certificate`; learnerscript blocks | Available (UAT) |
| Data | Managed MySQL 8.4.9 database | Credentials in `config.php` on the server, outside the repository | Available (UAT) |
| Data | `moodledata` (files, caches, DPDP export files) | Outside the web root | Available (UAT) |
| Data | Object storage for proctoring recordings | Recording belongs to a later proctoring stage of the ADR-038 plan; nothing records today. Bucket and region settings (`aws_s3_bucket`) and a chunk-registration web service (`upload_chunk`) exist in code. At the UAT commit the proctoring plugin registers no feature flag, and the web service checks only a capability that every authenticated user holds; see S-27 | Recording: Planned. Registration endpoint: Available (UAT), gated by a capability held by all authenticated users. Whether a bucket and credentials are configured on UAT is to be confirmed (owner: DevOps lead) |
| Background | Moodle cron (`www-data` crontab, every minute); 34 scheduled tasks declared, 7 disabled by default | `db/tasks.php` across the canonical tree | Available (UAT) |
| Operations | Daily log-scan script (`moodle-enhancement/tools/ops/sentientia-logscan.sh`) | Only monitoring artefact in the repository | Available (UAT) |
| Source and CI | GitHub repository and GitHub Actions (3 workflows) | `deploy-production.yml` holds SSH secrets for the legacy live host. Its deploy job uses a `production` environment; the protection rules of that environment are a repository setting the code does not show, to be confirmed (owner: DevOps lead). The workflow interpolates its inputs directly into shell scripts (section 14.4, T42) | In place (repository or process) |
| Administration access | VPN, then a bastion with time-based one-time password, then SSH | `docs/operations/UAT-REMOTE-DEV-WORKFLOW.md` | Available (UAT) |

### 3.2 External services coded in the product

| Service | Plugin | Direction and auth | Status |
|---|---|---|---|
| Airpay payment page, path A: native order cart | `local_sentientia_cart` (`checkout.php`, `classes/gateway/airpay_gateway.php`, `callback.php`, `return.php`) | Browser auto-submit form post out with a SHA-256 checksum over sorted `key=value` pairs and the merchant secret; unauthenticated server-to-server callback in (`callback.php`, no session) that requires a `checksum` field computed the same way, an amount check against the stored order, a currency check only when the payload carries a currency field, and an optional IP allow-list. Airpay's documented response hash is a different field (the CRC32 `ap_SecureHash`), so genuine callbacks would probably fail verification (S-18). The storefront hands baskets to this cart only when `sentientia.catalog.storefront_checkout.enabled` is ON (default OFF); the cart's own pages need `local/sentientia_cart:purchase` and the `enabled_tenants` setting (empty means all tenants). The callback URL is reachable whatever the flags; it fails closed while the merchant secret is empty | Available (UAT); checksum contract does not match Airpay's documented response hash and has not been validated with Airpay (S-18) |
| Airpay payment page, path B: Moodle payment subsystem | `paygw_airpay` (`pay.php`, `process.php`) | Reached from a course's fee enrolment (`enrol_fee`) when a payment account with the Airpay gateway exists. Browser GET to `pay.php`, auto-submit form to the provider, browser-relayed response to `process.php` with a keyless CRC32 checksum. Depends on legacy `local_biz_cart` code that is not in the repository. `pay.php` hard-codes the provider's production payment URL as the form action | Available (UAT); whether a payment account is configured on UAT, and whether the path runs at all, is to be confirmed (owner: platform engineer); see S-01, S-19, S-21 |
| AI model provider (Anthropic Messages API), through the gateway | `local_sentientia_ai` gateway | Server-to-server HTTPS with an API key; mock by default; ledger and spend caps | Built (behind a feature flag, off by default) |
| AI model provider (Anthropic Messages API), direct from the assistant chat | `local_sentientia_assistant` (`classes/ai_client.php::call_claude()`) | Server-to-server HTTPS with the plugin's own `api_key`, used whenever `sentientia.ai.gateway.enabled` is OFF (the default) and that key is set; no ledger and no spend cap; the system prompt carries the user's name, designation, organisation and course list | Available (UAT) (`ai.assistant.enabled` default ON); key state on UAT to be confirmed (owner: Head of Learning & Development); see S-22 |
| HRMS (KeKa) | `local_sentientia_integrations` | Inbound webhook with a shared secret header; outbound OAuth client | Built (behind a feature flag, off by default) |
| Microsoft 365 / Entra ID | `local_sentientia_m365`; Moodle core OAuth 2 | Authorisation-code flow with PKCE; tokens encrypted at rest | Built (behind a feature flag, off by default); SSO issuer on UAT to be confirmed |
| Calendar providers and calendar clients | `local_sentientia_calendar` | OAuth tokens encrypted at rest; outbound ICS feed (`ics.php`) authenticated by a random token in the query string (S-26) | Built (behind a feature flag, off by default) |
| Browser push services | `local_sentientia_pwa` | VAPID-signed Web Push (RFC 8292), payload encryption (RFC 8291) | Built (behind a feature flag, off by default) |
| Customer systems (SCIM, webhooks, LTI 1.3, xAPI) | `local_sentientia_api`, `local_sentientia_xapi` | Bearer tokens (hashed), HMAC-signed outbound webhooks, signed JWT | Built (behind a feature flag, off by default) |
| Teams incoming webhook | `local_sentientia_integrations` (`classes/teams_notifier.php`, unchanged since the UAT commit) | Outbound post to a configured URL; gated by the `teams_enable` setting and the webhook URL, not by a feature flag | Available (UAT); off unless the setting and a URL are set (UAT values to be confirmed, owner: Head of Learning & Development) |
| WhatsApp and SMS providers | `local_sentientia_whatsapp` | Live send not implemented; mock only | Built (behind a feature flag, off by default); live send not built |
| E-mail (SMTP) | Moodle core | Configured per site | Available (UAT) |
| Text-to-speech and slide services (content pipeline) | `scripts/agents/` (command line, not the web application) | API keys from `.env`; mock by default; live calls need an explicit confirm switch | Built (verified on development) |

### 3.3 Trust boundaries

| ID | Boundary | Crosses from | Crosses to | Main controls |
|---|---|---|---|---|
| TB1 | Internet to edge | Any client | Load balancer | TLS 1.2 (ECDHE-GCM only) and TLS 1.3, legacy protocols refused; HSTS; WAF to be confirmed |
| TB2 | Edge to web server | Load balancer | Apache/PHP-FPM | Forwarded-HTTPS handling; file-disclosure deny rules; security headers |
| TB3 | Unauthenticated to authenticated | Guest (11 public surfaces, raw-HTTP endpoints) | Session-bound application | Moodle login, sessions, `require_login()`, token checks on raw endpoints |
| TB4 | Tenant to tenant | User of one tenant | Data of another tenant | ADR-031 helpers, fail-closed filters, `tenant_isolation` tests |
| TB5 | Role to role (privilege) | Learner, manager, tenant admin | Higher privilege | Moodle capabilities; ADR-031 escalation rules; tenant-admin core capabilities prohibited on UAT |
| TB6 | Application to database and file store | PHP | MySQL, `moodledata`, object storage | Moodle DB API with bound parameters; credentials in `config.php` |
| TB7 | Application to external services | PHP (web and cron) | Payment, AI, HRMS, M365, push, e-mail | Keys in plugin settings or `.env`; encrypted token vaults; outbound HTTP through Moodle's client with its blocked-host rules (webhooks). Exception: the assistant chat's direct provider call uses raw PHP cURL with the plugin's own key (S-22) |
| TB8 | External services to application | Payment callbacks (`local/sentientia_cart/callback.php`, server to server; `payment/gateway/airpay/process.php`, browser-relayed), HRMS webhook, SCIM/LTI/xAPI clients, calendar clients | Application | Cart: secret-suffixed SHA-256 `checksum` field (fail closed when the secret is empty; not the hash Airpay documents), amount check, currency check only when the payload carries a currency, optional IP allow-list; payload logged before verification (S-18, T45). Gateway: keyless CRC32 (S-01). Shared secret with constant-time compare (HRMS); hashed bearer tokens (SCIM, xAPI); JWT verification (LTI); query-string token (ICS, S-26); rate limits |
| TB9 | Operator to server | Engineer or IT | UAT server and database | VPN, bastion with TOTP, SSH; passwordless sudo for the deploy user |
| TB10 | Source to deployment | Developer workstation, CI | UAT; legacy live host (manual workflow) | Pre-commit hook, CI gates, checksum-verified deploy script (dry run by default), manual production workflow with typed confirmation, using a `production` environment whose protection rules (required reviewers, branch rules) are to be confirmed (owner: DevOps lead; T42) |

### 3.4 Main data flows

1. **Browse and sign in.** Browser to `/`, `/login/index.php`, public catalogue. Moodle session cookie issued (`Secure`, `HttpOnly`, `SameSite=Lax` observed on UAT, 3 September 2026).
2. **Learning.** Authenticated pages and AJAX calls to `lib/ajax/service.php`, which runs our external functions after login and session-key checks (Moodle core behaviour).
3. **Purchase.** Two paths exist in code; which one is active on UAT is to be confirmed (owner: Head of Learning & Development with the Airpay payments team).
   - **Path A, order cart.** Storefront basket (hand-off only with `sentientia.catalog.storefront_checkout.enabled` ON) or cart page, then `local/sentientia_cart/checkout.php` (login, `local/sentientia_cart:purchase`, session key), then `airpay_gateway::initiate_payment()` builds a signed auto-submit form to the provider. The provider calls `local/sentientia_cart/callback.php` server to server; after the checksum, amount, currency (only when the payload carries one) and optional IP checks, `cart_manager::mark_paid()` records payment and enrols. The buyer returns to `return.php`, which only displays the order to its owner.
   - **Path B, Moodle payment subsystem.** A course fee enrolment opens the gateway modal, whose JavaScript sends the browser to `payment/gateway/airpay/pay.php` (no login check), which builds an order and an auto-submit form to the provider. The provider relays the response through the browser to `payment/gateway/airpay/process.php`, which checks the CRC32 hash, updates the order and enrols the user. The web service `paygw_airpay_get_form` is not on this path and cannot run (section 8.2).
4. **Integration in.** SCIM, LTI, xAPI and HRMS webhook requests, and calendar clients fetching the ICS feed, reach raw-HTTP endpoints; each is flag-gated and authenticated (section 8.1).
5. **Integration out.** Signed webhooks, AI gateway calls, HRMS pulls, push messages, e-mail; most run from cron.
6. **Administration.** Site administrators use Moodle admin pages and the Switchboard (`/local/sentientia_platform/admin/switchboard.php`, needs `moodle/site:config`). Tenant administrators use Sentientia admin surfaces confined to their tenant.
7. **Deployment.** Developer commit (pre-commit checks), push, CI gates, then a deploy script over SSH to UAT. Production deployment is a manual workflow that needs a typed confirmation string; it is not the cutover path.

### 3.5 Environments

| Environment | Purpose | Data | Security notes |
|---|---|---|---|
| Development (workstation) | Build and local tests; Moodle 5.1.3+ and a private Moodle 5.3 runtime | Test import of a production backup (contains real personal data, such as e-mail addresses) | Local only; no admin rights on the machine; endpoint protection present; no masked dataset yet (S-17) |
| CI (GitHub-hosted runners) | Lint, gates, PHPUnit on Moodle 5.2 stable, Playwright on a 4.5 snapshot | Synthetic fixtures and seeded personas | Repository secrets are names only in workflow files |
| UAT (academy2.airpay.ninja) | Acceptance testing; proposed SISA target | Fresh install with synthetic test personas and seeded demo data; no production-derived data (`docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row B3; `docs/visual-evidence/2026-09-24/README.md`; `docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md`) | 2 GB server: no load or volumetric testing. Production-derived data must not be loaded here; the Stage B rehearsal runs on its own target (A3) |
| Staging | None between UAT and production today | n/a | Gap (section 13) |
| Production (www.airpay.academy) | Legacy BizLMS, not Sentientia | Live | Out of scope |

---

## 4. Authentication

### 4.1 Mechanisms

| Mechanism | How it works | Status |
|---|---|---|
| Manual accounts (username and password) | Moodle core `auth_manual`; password hashing by Moodle core; password policy is a site setting | Available (UAT); policy values on UAT to be confirmed (owner: DevOps lead) |
| Accounts provisioned from the human resources management system (HRMS) | CSV import (`local_sentientia_users/bulk_import.php`, needs `local/sentientia_users:create`; `bulk_csv.php`, needs `local/sentientia_users:bulkstatuschange`) and the KeKa joiner-mover-leaver webhook | CSV import Available (UAT); webhook Built (behind a feature flag, off by default) |
| Public self-registration | `local_sentientia_users/signup.php`; assigns the Public tenant; e-mail confirmation; Terms and Privacy tick box. Since 3 September 2026 it no longer reveals whether an e-mail address is already registered | Available (UAT); enabled state on UAT to be confirmed |
| OAuth 2 / OpenID Connect SSO (Microsoft Entra ID recommended; Google optional for Public) | Moodle core `auth_oauth2`; login template shows provider buttons when an issuer exists; runbook in `docs/security/ENTERPRISE-IDENTITY-PACK.md` and `SSO-SETUP-GUIDE.md` | Available (UAT) as a Moodle core plugin; issuer configuration on UAT to be confirmed (owner: IT infrastructure) |
| SAML 2.0, LDAP | Documented as options only | Planned |
| Legacy OTP login form | Template kept from BizLMS (`theme/sentientia/templates/core/otploginform.mustache`); Moodle 5.2 and later no longer dispatch OTP login, and the OTP auth plugin is not part of the product | Not planned for Sentientia; removal recommended (S-15) |
| Web-service tokens | Moodle core tokens; the Public API service `sentientia_api_v1` is `restrictedusers = 1` and `enabled = 0` by default | Built (behind a feature flag, off by default) |
| SCIM bearer tokens | Per-client token stored as a SHA-256 hash (`local_sentientia_api_scimcli`) | Built (behind a feature flag, off by default) |
| xAPI client credentials | Bearer or HTTP Basic. Per-client bearer tokens and Basic passwords are stored as unsalted SHA-256 hashes (`token_hash`, `basic_pass_hash`) and compared with `hash_equals()`. A site-wide bearer token and a site-wide Basic user and password can also be set in plugin configuration, stored as entered; they authenticate with tenant id 0 (`classes/lrs/authenticator.php`). See S-25 | Built (behind a feature flag, off by default) |
| Calendar ICS feed tokens | 64-character alphanumeric token drawn from `random_bytes()`, sent as `?token=` in the feed URL, stored as entered and looked up directly; regeneration and revoke-all functions exist; invalid tokens return 404 (`local/sentientia_calendar/ics.php`, `classes/token_manager.php`). See S-26 | Built (behind a feature flag, off by default): `sentientia.calendar_sync.enabled` |
| LTI 1.3 launches | OIDC login, signed JWT verification against the platform key set, nonce replay protection | Built (behind a feature flag, off by default) |
| Live-session audience join | High-entropy join token; anonymous joining only when `live.allow_anonymous` is ON | Built (behind a feature flag, off by default) |
| Mobile app tokens | A purpose-built learner API and native app are proposed (ADR-040); no function is marked for Moodle's official mobile service | Planned |

### 4.2 Brute force and bot controls

| Control | Status |
|---|---|
| Moodle core account lockout after failed logins | Available (UAT) as a Moodle core feature; threshold on UAT to be confirmed (owner: DevOps lead) |
| reCAPTCHA on self-registration | Gap: keys not provided (posture review finding H2); S-02 |
| Rate limit on self-registration | Gap: not built; S-02 |
| Rate limits on machine endpoints (Public API 600 requests per 60 s, configurable; SCIM per-client rate check; xAPI 600 per 60 s per client with HTTP 429; live stream global and per-actor connection caps) | Built (behind a feature flag, off by default) for each endpoint |

### 4.3 Session management

| Item | Fact | Status |
|---|---|---|
| Session handling | Moodle core sessions (`MoodleSession` cookie) | Available (UAT) |
| Cookie flags | `Secure`, `HttpOnly`, `SameSite=Lax` (UAT probe, 3 September 2026) | Available (UAT) |
| Session timeout | 8 hours (28,800 s) on the development copy; UAT value to be confirmed | To be confirmed (owner: DevOps lead) |
| Login session regeneration, logout | Moodle core behaviour | Available (UAT) |
| Role switching | A defect where the role switcher wrote a real core role switch and demoted multi-role administrators was found during browser testing and fixed (15 June 2026); persona walks now cover it | Available (UAT) |
| "Log in as" | Restricted to site administrators: on UAT the tenant-administrator role has `moodle/user:loginas` and other user and role management capabilities prohibited (30 September 2026) | Available (UAT) |
| Forced login | `forcelogin = 0` by design (public landing page and storefront) | Available (UAT) |

### 4.4 Multi-factor authentication

| Item | Fact | Status |
|---|---|---|
| MFA framework | Moodle core `tool_mfa` is bundled and installed | Available (UAT) |
| Enforcement | Optional by default; no role is forced to enrol a factor. The runbook defines three tiers: site administrators within 30 days of cutover, tenant administrators and managers within 90 days, learners through the identity provider's conditional access | Planned (`MFA-ENFORCEMENT-RUNBOOK.md`; identity pack section 2) |
| Break-glass site-administrator account | Shared between Learning & Development and IT; password-only today | Gap (S-04) |
| Configuration on UAT | The identity pack names UAT as the first environment to run the MFA sequence; execution is not recorded | To be confirmed (owner: Head of Learning & Development with IT) |

---

## 5. Authorisation and tenant isolation

### 5.1 Capability model

| Item | Fact | Source |
|---|---|---|
| Model | Moodle roles, contexts and capabilities; pages and external functions check a capability or an explicit rule (169 of 174 functions declare a capability; the 5 others are reviewed in section 8.2) | Moodle core; plugin `db/access.php` files |
| Capabilities declared by us | 181 (local 164, block 13, enrol 4) in 48 `db/access.php` files | `_facts/20-flags-roles.md` |
| Archetype defaults | 128 to `manager`, 51 to `editingteacher`, 21 to `teacher`, 21 to `student`, 28 to `user`; 47 with no archetype | same |
| Site-administrator-only capabilities | 39 held by no role on the development database (for example `local/sentientia_platform:crosstenant`, `local/sentientia_roles:manage`, `local/sentientia_privacy:manage`, `local/sentientia_api:scim_manage`) | same, section 5.4 |
| Roles created by our code | `sentientiaauthor` (7 capabilities, authoring only), Available (UAT). `sentientia_externaltrainer` (2 capabilities: view classroom, mark attendance), Built (verified on development), behind a feature flag (`sentientia.users.external_trainer.enabled`), off by default: this code is not in the UAT commit, and the user-type factory does not yet list the type | `local/sentientia_authoring/classes/author_role.php`; `local/sentientia_users/classes/external_trainer/role.php`; `_facts/20-flags-roles.md` |
| Roles inherited from the BizLMS data | `administrator` (tenant administrator, manager archetype), `employee` (learner), `trainer`; looked up by shortname, never by numeric id | `_facts/20-flags-roles.md` section 4.2 |
| Line manager | Not a role: the supervisor field on the user record; checks in `local/sentientia_manager/classes/team_manager.php` | same, section 4.3 |

### 5.2 Personas (for test accounts)

| Persona | Mechanism | Reach |
|---|---|---|
| Guest / unauthenticated | none | 11 public surfaces; raw-HTTP endpoints with their own auth |
| Learner (Airpay, Public or ZEEA tenant) | Role `employee` plus authenticated user | Own account; own tenant catalogue and shared content |
| Public self-registered learner | Self-registration into the Public tenant | Own account; Public catalogue |
| Line manager | Supervisor relationship | Direct and indirect reports in own tenant |
| Internal trainer | Role `trainer` | Own classrooms (when `sentientia.classroom.trainer_own_scope.enabled` is ON); tenant guard always |
| External trainer | Role `sentientia_externaltrainer`: Built (verified on development), behind a feature flag, off by default; not on UAT | Assigned sessions only; account expires 30 days after the last session |
| Course author | Role `sentientiaauthor` plus category-level course creator | Authoring tools only |
| Tenant administrator (L&D) | Role `administrator` at the organisation's category or system context | Own tenant only (ADR-031) |
| Compliance officer | Role `complianceofficer` on UAT (not created by repository code) or holder of `local/sentientia_compliance_report:export` | Own tenant |
| Site administrator | Moodle site admin | All tenants |
| Platform L&D (cross-tenant) | Holder of `local/sentientia_platform:crosstenant`; on UAT a role `sentientiaplatform` exists with only this capability and is assigned to nobody (30 September 2026) | All tenants, limited by other capabilities |

### 5.3 Tenant isolation model (ADR-031)

Tenant identity is the first segment of the user's `open_path` (for example `/1/2/3` is tenant 1). Customer identity derives from the tenant root. ADR-031 (Accepted, 25 September 2026) followed a sweep that found 73 cross-tenant authority issues, 70 of them confirmed, in about 30 plugins. Plugins had treated holding a `manage` or `view` capability as licence to skip the tenant filter, and an unresolved tenant was read as "every tenant".

| ADR-031 decision | Mechanism in code | Status |
|---|---|---|
| One authority: site administrator or holder of `local/sentientia_platform:crosstenant` | `tenant::is_cross_tenant()` (`local/sentientia_platform/classes/tenant.php`) | Available (UAT) |
| A capability says what, never where | Tenant scope applies unless `is_cross_tenant()` | Available (UAT) |
| Fail closed | `scope_path()` returns `null` when no tenant resolves; `sql_filter()` and `path_filter()` return `1=0` | Available (UAT) |
| Writes check the target | `require_same_tenant_user()`, `require_access()`, `require_path_access()` | Available (UAT) |
| Escalation closed | `roles:manage` and `roles:assign` limited to cross-tenant callers; a scoped assigner may assign only a role they hold, to users in their tenant; user creation refuses rows matching a user in another tenant | Available (UAT) |
| Revoke archetype grants | Upgrade steps call `unassign_capability()` | Available (UAT) |
| Tests | Every fix ships `@group tenant_isolation` tests; the group blocks CI (135 tagged test files; floor 40) | Available (UAT) |

### 5.4 Bounded tenant-path filtering and its gate

| Item | Fact |
|---|---|
| Defect class | Hand-written filters such as `LIKE '/1%'` also matched tenant `/10` or `/177`, and `%/id/%` forms missed leaf nodes. The class shipped 4 times between 7 and 22 September 2026 and produced silently wrong numbers (one case counted 749 users instead of 743) |
| Fix | `tenant::path_descendant_filter($path, ...)` builds `(col = :exact OR col LIKE :prefix)` with prefix `path/%`, so `/1` matches `/1` and `/1/2` but never `/10` |
| Guards | `tools/check-path-boundary.php` finds unbounded literals, concatenations and prefix patterns; pre-commit CHECK 18 and the CI job `path-boundary-check` block them; an inline `path-boundary-ok:` marker with a reason allows a reviewed exception |
| Caller contract | `tenant::scope_path()` returns `''` for a cross-tenant caller (no restriction) and `null` for a caller with no resolvable tenant. Callers must turn `null` into `1=0` before calling the filter. `path_descendant_filter()` itself returns `1=1` (no restriction) for an empty path, and for `/`, which it trims to empty. A caller that casts a `null` scope to `''` (for example with `(string)` or `?? ''`) would therefore fail open |
| Callers checked | 74 non-test call sites in 43 files under `moodle-enhancement/local/` (count of 20 November 2026). The two sampled (`sentientia_core/classes/admin_log.php`, `sentientia_recompletion/classes/rule_access.php`) handle `null` correctly. No automated check enforces the contract today; see S-28 |

### 5.5 Other authorisation facts

- The public storefront originally let a Public-tenant learner open and self-enrol into another tenant's course by id (posture review finding C2). Fixed on 3 September 2026: `catalog_manager::assert_course_visible_to_viewer()` gates the course page, add-to-cart and enrol-now; the cart also refuses lines the catalogue does not show the buyer. Regression check on UAT returned 404 for other tenants' courses. Available (UAT).
- The compliance report has three scope levels (site, tenant, team) in `local/sentientia_compliance_report/classes/viewer_scope.php`; `moodle/site:viewreports` alone no longer admits a user to the tenant matrix (18 November 2026). Available (UAT).
- The DPDP admin panel needs `local/sentientia_privacy:manage` and cross-tenant authority. Available (UAT).
- The Switchboard (feature flags) needs `moodle/site:config`; every change writes an audit row. Available (UAT).
- Tenant-registry mode: with the legacy setting ON, tenants are validated against the hard-coded list `[1, 77, 177]`. Onboarding a second customer needs the table-driven registry (ADR-021). UAT setting to be confirmed.

---

## 6. Input validation, output encoding and CSRF

| Practice | How it is applied | Enforcement | Status |
|---|---|---|---|
| Parameter input | `required_param()` and `optional_param()` with `PARAM_*` types; web services use `validate_parameters()` against declared structures | Pre-commit CHECK 3 rejects staged raw `$_GET`, `$_POST`, `$_REQUEST` access. Known exceptions: the SCIM router reads `PATH_INFO` and `REQUEST_METHOD`; the HRMS webhook reads its secret header; the cart payment callback reads raw `$_POST` and `php://input` (JSON or form) by design (S-18); two legacy files in the payment gateway (`process_old.php`, `error.php`) read raw input without a Moodle bootstrap (S-15) | Available (UAT) |
| SQL | Moodle DB API with `{table}` names and named parameters; `get_in_or_equal()` for lists; `sql_like()` with `sql_like_escape()` | Database coding standard applied in code review (a written convention, not an automated gate); the enforced check is the MySQL 8.4 reserved-word scan before each push | Available (UAT) |
| Output encoding in PHP | `s()` for attributes and text, `format_string()` and `format_text()` for user content (the assistant's answer passes through `format_text()`). Known exception: `payment/gateway/airpay/pay.php` echoes the user's first name, last name, city, state, e-mail and phone into HTML `value="..."` attributes without escaping (S-19) | Code review only; no automated gate | Available (UAT), with the exception in S-19 |
| Output encoding in templates | Mustache `{{ }}` escapes; `{{{ }}}` is raw and allowed only for HTML already cleaned on the server | Coding standard applied in code review (not an automated gate); the triple-brace occurrences are a code-review target (section 20.3) | Available (UAT) |
| Template integrity | The quality-gate scanners of ADR-027 (the first of which shipped on 9 June 2026) block Mustache comment leaks, stale AMD names and layouts that never print the end-of-body JavaScript | Pre-commit CHECKs 13 to 15 and CI `php-lint` | Available (UAT) |
| CSRF | Moodle session key: `moodleform` checks it automatically; page scripts that change state call `require_sesskey()` (for example the Switchboard, privacy request hub, bulk user import, photo upload, live-session trainer actions, calendar connect and disconnect); AJAX web services through `service.php` require it (Moodle core). Known exception: `payment/gateway/airpay/pay.php` creates orders on a plain GET with no session key (S-19) | Code review; no automated gate checks that every state-changing page calls it | Available (UAT), with the exception in S-19 |
| Security headers | `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (1 year, `includeSubDomains`) and `ServerSignature Off` come from `deploy/moodle-htaccess.template`. `X-Frame-Options: sameorigin` comes from Moodle core (observed in the 3 September 2026 probe, before the template existed). `ServerTokens Prod` belongs in the main Apache configuration, not the template | `deploy/moodle-htaccess.template`; Moodle core | Available (UAT); `ServerTokens` to be confirmed (owner: DevOps lead) |
| Content Security Policy | Deferred: Moodle prints inline scripts and needs a nonce strategy | Gap (S-03) | Planned |
| Server-side request forgery | Outbound webhooks accept https URLs only and go through Moodle's HTTP client with its blocked-host and port rules; the HRMS client URL-encodes the employee id before building the path (fixed 4 September 2026) | Code | Built (behind a feature flag, off by default) |

---

## 7. File uploads and stored files

| Upload path | Controls | Status |
|---|---|---|
| Moodle core file areas (course files, SCORM packages, assignments) | Moodle file API, file pickers with accepted types, `maxbytes`, capability checks; served through `pluginfile.php` with access callbacks | Available (UAT) |
| Profile photo (`local/sentientia_users/photo.php`) | `require_login()`, `require_sesskey()`, accepted types `web_image`, server-side resize by Moodle; error path for unreadable images | Available (UAT) |
| User bulk import (`local/sentientia_users/bulk_import.php`, `bulk_csv.php`) | `bulk_import.php` needs `local/sentientia_users:create`; `bulk_csv.php` needs `local/sentientia_users:bulkstatuschange`; `require_sesskey()`, `.csv` only, Moodle CSV reader; rejected rows recorded in `local_sentientia_users_sync_errors` (contains direct identifiers) | Available (UAT) |
| Evaluation template import | Capability and `require_sesskey()` (`local/sentientia_evaluation/import_template.php`) | Available (UAT) |
| Proctoring recording chunks | Recording (browser upload to object storage, server-side chunk registration, 90-day retention purge) belongs to a later proctoring stage of the ADR-038 plan; nothing records today. The registration web service `local_sentientia_proctoring_upload_chunk` exists in code. At the UAT commit the proctoring plugin has no `db/feature_flags.php`, and no flag registry mentions proctoring. `start_session` and `upload_chunk` check only `local/sentientia_proctoring:attempt`, which `db/access.php` grants to the `user` and `student` archetypes, and `session_manager::start_session()` accepts any quiz id. Any authenticated user can therefore open a session, move it to a state that accepts chunks (submitting identity photos sets it to `verifying`, which `register_chunk()` accepts) and attach an object key of their choice; the retention purge and an erasure request would then delete that object (S-27). On development (20 November 2026) the master flag `sentientia.proctoring.enabled` exists and `upload_chunk` refuses every call while `sentientia.proctoring.recording.enabled` is OFF | Recording: Planned. Registration endpoint: Available (UAT), gated by a capability held by all authenticated users. Master flag and refusal guard: Built (verified on development) |
| DPDP data exports | JSON written to `moodledata/privacy_exports/`, unencrypted; stated 72-hour expiry not enforced by a task | Available (UAT); gap S-10 |
| PHP limits on UAT | `post_max_size` and `upload_max_filesize` at least 100 MB; `max_input_vars` 5,000 | Available (UAT) |
| Malware scanning of uploads | Moodle supports antivirus plugins; none configured in the repository | To be confirmed (owner: DevOps lead) |

---

## 8. Web services and APIs

### 8.1 Inventory

| Surface | Count and auth | Gate | Status |
|---|---|---|---|
| External functions (AJAX and token web services) | 174 functions in 24 components; all `ajax => true` and `loginrequired => true`; 67 capabilities; 5 functions declare no capability | Plugin flags where relevant | 172 Available (UAT); 2 proctoring report functions Built (verified on development), flagged |
| Pre-built external services | `Sentientia Public API v1` (`restrictedusers = 1`, `enabled = 0`); `Airpay AI Assistant` (`restrictedusers = 0` and `enabled = 1` in its definition, `local/sentientia_assistant/db/services.php`), so any user able to create a token could call its four functions over the token web service | Site web-service settings | Available (UAT) as definitions; whether token issuance is possible depends on the web-service protocol and token-creation settings on UAT, to be confirmed (owner: DevOps lead) |
| Public REST API v1 | 7 functions (6 read, 1 write), OpenAPI 3.0.3 document; gate order: flag, authorisation, rate limit, tenant scope; request log without personal data | `sentientia.api.enabled`, `sentientia.api.write.enabled` | Built (behind a feature flag, off by default) |
| SCIM 2.0 (`local/sentientia_api/scim/v2.php`) | Users and Groups; hashed per-client bearer token; filters limited to `userName eq` and `externalId eq`; DELETE is a soft suspend; cross-tenant principals hidden from scoped clients | `sentientia.api.scim.enabled` | Built (behind a feature flag, off by default); returned 401 to an unauthenticated probe on UAT |
| Outbound webhooks | 3 events; HMAC-SHA256 per subscription; header `X-Sentientia-Signature: t=<unix>,v1=<hmac>`; 5-minute replay window; retries then dead-letter; https only | `sentientia.api.webhooks.enabled` | Built (behind a feature flag, off by default) |
| LTI 1.3 provider (`lti/login.php`, `launch.php`, `jwks.php`) | OIDC, JWT verification, nonce consumption | `sentientia.api.lti.enabled` | Built (behind a feature flag, off by default) |
| xAPI statements (`local/sentientia_xapi/lrs/statements.php`) | Bearer or Basic; per-client rate limit; 429 with `Retry-After` before parsing | `sentientia.xapi.enabled`, `sentientia.xapi.lrs_endpoint_enabled` | Built (behind a feature flag, off by default); returned 503 (disabled) on UAT |
| HRMS webhook (`local/sentientia_integrations/webhook.php`) | `X-Webhook-Secret` header only, `hash_equals()` comparison; raw body stored (S-09) | `sentientia.hrms.webhook.enabled` plus setting | Built (behind a feature flag, off by default) |
| Live-session stream (`local/sentientia_live/stream.php`) | Moodle session or join token; global cap and per-actor cap of 2; 60-second stream lifetime; 503 when full | `live.enabled`, `live.realtime.enabled` | Built (behind a feature flag, off by default) |
| Leaderboard stream (`local/sentientia_leaderboard/stream.php`) | Server-Sent Events; `require_login()`, `local/sentientia_leaderboard:view`, tenant check; 403 while the plugin flag is OFF | `sentientia.leaderboards.enabled` (OFF); `sentientia.leaderboards.realtime.enabled` (ON) | Built (behind a feature flag, off by default) |
| PWA files (`manifest.php`, `sw.php`, `dismiss_install.php`, `mock_receiver.php`) | Static assets without a session; `dismiss_install.php` needs login and session key; `mock_receiver.php` (no session) answers 404 unless developer debugging is on | `sentientia.pwa.enabled` | Built (behind a feature flag, off by default); with the flag OFF `sw.php` serves a worker that unregisters itself |
| Calendar ICS feed (`local/sentientia_calendar/ics.php`) | No session (`NO_MOODLE_COOKIES`); bearer token in the query string (`?token=`), stored as entered; 404 for an unknown or revoked token; marks last use with the client IP | `sentientia.calendar_sync.enabled` | Built (behind a feature flag, off by default); see S-26 |
| E-mail template preview (`local/sentientia_emails/preview_ajax.php`) | `AJAX_SCRIPT`; `require_login()`, `require_sesskey()`, site admin or `local/sentientia_emails:manage_templates`; renders a submitted Mustache body against sample data | none | Available (UAT) |
| Cart payment callback (`local/sentientia_cart/callback.php`) | No session (`NO_MOODLE_COOKIES`); reads raw `$_POST` or a JSON body; optional IP allow-list (`airpay_callback_iplist`, empty by default); then every payload that passes the IP check is written to the PHP error log, before the checksum is verified, with no size cap and only five keys redacted; then a secret-suffixed SHA-256 `checksum` field is required (fail closed when the secret is empty); the amount is compared with the stored order, and the currency only when the payload carries a currency field | none (reachable whatever the flags) | Available (UAT); see S-18 and T45 |
| Cart checkout and return (`local/sentientia_cart/checkout.php`, `return.php`) | `require_login()`; checkout needs `local/sentientia_cart:purchase` and the session key; return shows the order only to its owner | `enabled_tenants` setting; storefront hand-off flag | Available (UAT) |
| Payment gateway pages (`payment/gateway/airpay/pay.php`, `process.php`, `process_old.php`, `error.php`) | No login on any of the four. `pay.php` builds an order and an auto-submit form (S-19); `process.php` is the browser-relayed provider response with a CRC32 check (S-01); `process_old.php` and `error.php` read raw input without a Moodle bootstrap (S-15). `checksum.php` requires login | Payment account configuration | Available (UAT); reachability on UAT to be confirmed |

**Count of raw-HTTP and unauthenticated entry points.** A scan of the canonical tree on 20 November 2026 finds 15 files in 9 local plugins that run outside the Moodle web-service layer: SCIM (1), LTI (3), xAPI (1), HRMS webhook (1), PWA (4), live stream (1), leaderboard stream (1), calendar ICS feed (1), cart callback (1) and e-mail preview (1). With the 4 payment-gateway pages that need no login (`pay.php`, `process.php`, `process_old.php`, `error.php`), the total is 19 files in 10 components. All are in the UAT commit. The fact base (`_facts/30-apis.md` section 8, as corrected on 21 November 2026) also lists 19 files in 10 components, now including `error.php`. The two lists differ by one file each. The fact base includes the classroom calendar export (`local/sentientia_classroom/ics.php`), which is a normal page behind `require_login()`. It leaves out `local/sentientia_emails/preview_ajax.php` (an `AJAX_SCRIPT` behind `require_login()` and the session key), which this document lists. Both differing files are login-protected, so the figure of 19 is agreed; SISA should treat the union of the two lists (20 files) as the test scope.

### 8.2 Functions that declare no capability

| Function | What the code does | Assessment |
|---|---|---|
| `local_sentientia_leaderboard_set_optout` | Calls `validate_context()` on the caller's user context; changes only the caller's preference | Acceptable; confirm in code review |
| `local_sentientia_notifications_save_prefs` | Calls `validate_context()`; saves the caller's preferences | Acceptable; confirm in code review |
| `local_sentientia_assistant_ask` | Acts on the caller's own id; calls `validate_parameters()`; no `validate_context()`. `ai_client::ask()` checks `ai.assistant.enabled` and the limit of 20 questions per user per day on every call, before any provider call | Low (S-07): add `validate_context()` and an explicit capability |
| `local_sentientia_assistant_get_history` | Returns the caller's own chat log; does not call `validate_parameters()` or `validate_context()`; the `limit` argument has no upper bound | Low (S-07): add `validate_parameters()`, `validate_context()`, an upper bound on `limit` and an explicit capability |
| `paygw_airpay_get_form` | Cannot run. It calls `airpay_helper::get_payment_script()`, which does not exist, and calls `get_unprocessed_order()` with 3 arguments (4 required) and `create_order()` with 4 (5 required), so every call throws before any order is built. The gateway's JavaScript does not use it; it sends the browser to `pay.php` | Dead code (S-20): remove the function, or rewrite it with the checks `pay.php` lacks |

### 8.3 Rate limiting status

| Surface | Limit | Status |
|---|---|---|
| Public API v1 | 600 requests per 60 s per client, configurable (`local_sentientia_api_rate`) | Built (behind a feature flag, off by default) |
| SCIM | Per-client rate check | Built (behind a feature flag, off by default) |
| xAPI | 600 per 60 s per client, configurable (`local_sentientia_xapi_lrs_rate`) | Built (behind a feature flag, off by default) |
| Live stream | Global connection cap and per-actor cap of 2 | Built (behind a feature flag, off by default) |
| AI assistant chat | 20 questions per user per day, checked in `ai_client::ask()`. With the defaults (`ai.assistant.enabled` ON, `sentientia.ai.gateway.enabled` OFF) the chat does not use the gateway: if the assistant's own `api_key` is set, it calls the provider directly, with no ledger and no spend cap; if no key is set, it answers "not configured". The gateway's mock mode applies only when routing is ON | Available (UAT); key state on UAT to be confirmed (owner: Head of Learning & Development); see S-22 |
| AI gateway | 200,000 tokens per day global, 100,000 per customer, USD 25 per month cost cap; applies only to calls routed through the gateway | Built (behind a feature flag, off by default) |
| Moodle AJAX web services, login, self-registration | No product-level rate limit; Moodle lockout for login only | Gap (S-02; S-11 for edge rate rules) |

---

## 9. Cryptography and secrets management

### 9.1 Secrets handling

| Secret | Where it lives | Handling | Status |
|---|---|---|---|
| Database credentials | `config.php` on the server | `config.php` is excluded from git (`.gitignore`); the packaging script fails the build if an archive contains a root `config.php` or a `public/config.php` with database settings (an earlier package had leaked a development `config.php`) | Available (UAT) |
| UAT database account | Managed database | The posture review of 3 September 2026 recorded the managed database's superuser as the application account, with its password once sent by e-mail. Remediated on UAT the same day: the application now connects with an application-scoped account that has schema-only grants and no SUPER privilege, and the e-mailed superuser password was rotated; the old one is rejected (`docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row E5). Open: credential delivery through a vault or secrets manager, and the same pattern on the production target | Available (UAT) (remediated 3 September 2026); production target to be confirmed (owner: DevOps lead); S-05 |
| Pipeline and integration API keys (AI, text-to-speech, slides, Azure, WhatsApp) | `.env` on the operator's machine, never committed | `.env` in `.gitignore`; pre-commit CHECK 5 blocks a staged `.env`; CHECK 4 blocks hard-coded credential patterns | In place (repository or process) |
| Plugin secrets (payment merchant settings for both payment paths, AI gateway key, assistant `api_key`, HRMS client, Teams webhook URL) | Moodle plugin configuration in the database, stored as entered | Admin-only settings pages; masked fields where the settings use a password type (for example the cart's `airpay_secret`); AI keys never logged | Available (UAT) |
| xAPI site-wide bearer token and Basic password | Plugin configuration, stored as entered | Optional fallback credentials; per-client credentials are stored as unsalted SHA-256 hashes (S-25) | Built (behind a feature flag, off by default) |
| VAPID key pair for Web Push | Plugin configuration | The raw private key (`vapid_private_b64url`) is stored in plaintext. Only the PEM copy is wrapped with AES-256-GCM, and only when a master key is configured (`SENTIENTIA_VAPID_MASTER_KEY` environment variable or `$CFG->sentientia_vapid_master_key`); without one, or if encryption fails, the PEM is also stored in plaintext (`local/sentientia_pwa/classes/vapid_key_manager.php`). Whether a master key is set on UAT is to be confirmed (owner: DevOps lead); see S-24 | Built (behind a feature flag, off by default) |
| Third-party OAuth tokens (calendar, Microsoft 365) | Database, `*_enc` columns | Moodle core `\core\encryption::encrypt()` | Built (behind a feature flag, off by default) |
| Machine-client tokens (SCIM, xAPI) | Database | Stored as unsalted SHA-256 hashes (SCIM tokens; xAPI tokens and Basic passwords) | Built (behind a feature flag, off by default) |
| Calendar ICS feed tokens | Database | Stored as entered (not hashed) | Built (behind a feature flag, off by default); S-26 |
| Webhook signing secrets | Database, per subscription | HMAC-SHA256 | Built (behind a feature flag, off by default) |
| CI secrets | GitHub repository secrets for the legacy live host (`PRODUCTION_SSH_HOST`, `_USER`, `_KEY`, `_PORT`, `PRODUCTION_MOODLE_PATH`, optional messaging webhook) | Used only by the manual production workflow, which has a typed-confirmation input and a deploy job bound to a `production` environment; that environment's protection rules (required reviewers, branch rules) and whether the secrets are still populated are to be confirmed (owner: DevOps lead); see T42 | In place (repository or process) |
| Persona test credentials | Generated into a git-ignored local file | Never committed | In place (repository or process) |
| Secrets manager | None | The posture review recommends one for credential delivery | Planned |

### 9.2 Cryptographic mechanisms

| Mechanism | Use | Assessment |
|---|---|---|
| TLS 1.2 (ECDHE-GCM suites only) and TLS 1.3 at the load balancer | Transport | Standard. TLS 1.0 and 1.1, CBC, 3DES, RC4 and non-forward-secret suites refused (test of 24 September 2026, `docs/visual-evidence/2026-09-24/README.md`). Certificate expires 12 December 2026; renewal to be confirmed (R15) |
| HMAC-SHA256 | Outbound webhooks | Standard |
| SHA-256 token hashes | SCIM tokens; xAPI tokens | Adequate for high-entropy random tokens |
| Unsalted SHA-256 | xAPI HTTP Basic passwords | Weak for human-chosen passwords (CWE-916); S-25 |
| AES-256-GCM | PEM copy of the VAPID private key, only when a master key is configured | Standard algorithm; incomplete coverage, because the raw private key is in plaintext (S-24) |
| SHA-256 over the sorted `key=value` pairs, joined with a vertical-bar separator, with `secret=<merchant secret>` appended | Cart payment request and callback (`airpay_gateway::compute_checksum()`) | A custom secret-suffix construction rather than HMAC. With the secret at the end it is not open to length extension, so the algorithm is not the main concern. The evidenced risk is a contract mismatch (CWE-345): Airpay's documented response hash is the CRC32 `ap_SecureHash` (cited in `payment/gateway/airpay/classes/airpay_helper.php`, lines 236 to 250), while `verify_callback()` requires a `checksum` field, so genuine Airpay callbacks would probably all fail. The class comment in `airpay_gateway.php` (line 22) also describes a different formula from `compute_checksum()` (S-18) |
| Hand-written Web Push encryption (RFC 8291) and VAPID signing (ES256) | Push messages | ADR-003 accepted it pending a security review that is not recorded; code-review target (S-13) |
| CRC32 checksum without a secret | `paygw_airpay` payment response integrity (vendor scheme) | Not a cryptographic control (S-01) |
| SHA-256 (MD5 helper also present) | `paygw_airpay` payment request checksum and private key (vendor scheme, `classes/checksum.php`; `pay.php` uses the SHA-256 helpers) | Vendor-defined; confirm the scheme is current |
| Encryption at rest for database and volumes | Infrastructure | To be confirmed (owner: DevOps lead) |

---

## 10. Third-party code, core modifications and dependencies

### 10.1 Vendored third-party code

| Component | Version | Our patches | Status |
|---|---|---|---|
| `tool_certificate` | 5.0.12 in the repository (re-vendored 20 November 2026); the UAT server runs 5.0.12 with two patched files from the 19 November deploys | 3 active: image guard, verification on arrival, escaping of verification results; 2 reverted as fixed upstream | Available (UAT) as 5.0.12 with two patched files; the re-vendored tree is Built (verified on development) |
| `block_learnerscript`, `block_reportdashboard`, `block_reporttiles` | 2019052008.6 / 2019052001.3 / 2019052001 | 1 (modal factory port for Moodle 5.2 and later) | Available (UAT) |
| Legacy BizLMS blocks (userdashboard, trainerdashboard, masterinfo) | as in tree | none; provenance and licence to be confirmed | To be confirmed (owner: Head of Learning & Development) |
| Bootstrap 4.6.0 (SCSS and AMD modules) | Declared in `theme/sentientia/thirdpartylibs.xml` | none recorded | Available (UAT) |
| Moodle core third-party libraries | Declared in Moodle's own `thirdpartylibs.xml`; updated by Moodle releases | none | Available (UAT) |

The learnerscript plugins date from 2019 and receive no updates from us beyond the compatibility patch. They are a code-review target.

### 10.2 Core modifications and their governance

| Item | Fact |
|---|---|
| Ledger | 13 records in `moodle-enhancement/docs/core-mods/`, each with file, line, before and after, justification and upgrade note |
| Core files edited on 5.3 | 1: `lib/form/form.js` (skip dependency set-up for a form no longer in the page; ADR-044, Accepted). Kept as a full file per Moodle version with a pinned upstream SHA-256; hunks may only add lines; applied by `tools/check-core-patches.sh` at package time. Built (verified on development) |
| Additive overlays | 2 new files: `my/dashboard.php` (redirect shim) and `my/switchrole.php` |
| Vendored-plugin patches | Governed by ADR-041 (Accepted): patch only when nothing outside the plugin can reach the defect; bug fixes and compatibility only, no new behaviour; smallest hunk; opening and closing markers `SENTIENTIA-CORE-MOD`; a record and a test per patch; an import procedure for vendor releases |
| Enforcement | Pre-commit CHECK 6 (core file edit needs a record and a marker); CI workflow `core-mods.yml` on every pull request and push to `production` (blocks pull requests; on push it reports cumulatively and cannot block); `tools/check-core-patches.sh check` |
| Known coverage gap | The core-mods gate covers `admin/` and therefore `admin/tool/certificate/`; it does not cover `blocks/`, so learnerscript patches rely on the record and review only (ADR-041 context) |

### 10.3 Dependency management

| Item | Fact | Status |
|---|---|---|
| Moodle core | Target Moodle 5.3 LTS; security support to 1 October 2029 (ADR-033). Exact UAT build number to be confirmed | Available (UAT) |
| PHP plugins | No Composer dependencies in our plugins | In place (repository or process) |
| Python content pipeline | `requirements.txt` with bounded version ranges (pdfplumber, requests, pytest, reportlab) | Built (verified on development) |
| JavaScript build tooling | Root `package.json` dev dependencies (Moodle's Grunt and Babel toolchain, stylelint); no lock file at the root; used on development machines only, never deployed | In place (repository or process) |
| Automated dependency alerts | No Dependabot or equivalent configuration in `.github/` | Gap (S-16) |
| Process to absorb Moodle security releases | Moodle point releases are taken by re-packaging (`tools/packaging/build-standalone.sh --target 5.3`) from a SHA-256-verified vanilla base; no written patch-cadence target | Gap (S-16) |

---

## 11. Feature-flag posture as a security control

All new user-visible features ship behind a flag, default OFF. Flags are resolved in this order: tenant within customer, customer, legacy tenant override, global override, registered default, then `false` for an unknown key. The first two (customer-level) layers apply only when `sentientia.customer_level_flags.enabled` is ON, which it is not by default (ADR-002); with it OFF, resolution starts at the legacy tenant override. Overrides refresh within 30 seconds. Every change writes an audit row with old value, new value, actor and reason.

Security consequences:

1. Most new attack surface (Public API, SCIM, LTI, xAPI, webhooks, HRMS webhook, PWA, live sessions, AI live mode) is OFF by default. A tester must know which flags are ON on UAT, and that state is not in the repository (to be confirmed from the UAT Switchboard). Exception: at the UAT commit the proctoring plugin registers no flag, so its web services (including `upload_chunk`) are reachable by any authenticated user (S-27). The master flag `sentientia.proctoring.enabled` exists only on development (Built, verified on development) and protects UAT once deployed (R14).
2. One flag is inconsistent: `sentientia.catalog.public_lxp.enabled` defaults to `true` while its description says "default OFF". It controls a public catalogue rail.
3. Flag flips on UAT are decided by the Head of Learning & Development. Testing flagged features needs an agreed window (section 20.5).

---

## 12. Logging, monitoring and audit trail

### 12.1 Logs and audit tables

| Source | What it records | Status |
|---|---|---|
| Moodle standard log store | Every event with user id, IP and context (2,596,772 rows on the development copy, test data) | Available (UAT); `loglifetime` is 0 (never delete) on the development copy; UAT value to be confirmed |
| `logguests` setting | Must stay 1, or guest-path order events are not audited (cutover guide) | To be confirmed on UAT |
| Feature-flag audit | `local_sentientia_feature_flag_audit` | Available (UAT) |
| Role and capability changes through Sentientia | `local_sentientia_roles_auditlog` (append-only) | Available (UAT) |
| Agentic assistant actions | `local_sentientia_agent_audit` (every proposal, authorisation outcome and execution) | Available (UAT); agentic mode behind flags |
| HR-sensitive talent changes | `local_sentientia_talent_audit` | Built (behind a feature flag, off by default) |
| Recompletion resets, skill-level history | Append-only history tables | Available (UAT) |
| Public API requests | `local_sentientia_api_log`, no personal data in the log, 90-day retention task | Built (behind a feature flag, off by default) |
| AI gateway calls | `local_sentientia_ai_ledger`: metadata and cost, no prompt text; covers only calls routed through the gateway | Built (behind a feature flag, off by default) |
| Assistant chat | `local_sentientia_chat_log` stores every question and answer with the user id and model; `local_sentientia_chat_cache` stores the text of cacheable questions and answers for 1 hour without a user id (S-23) | Available (UAT) |
| Cart payment callbacks | `callback_logger` writes every callback payload that passes the IP check (no allow-list by default) to the PHP error log as JSON, before the checksum is verified and with no size cap (T45); only `secret`, `checksum`, `password`, `card_number` and `cvv` are redacted, so buyer and transaction fields are kept. A failed payment also stores the full payload in the order's notes column. The log's retention follows the server's log policy (to be confirmed) | Available (UAT); see S-18 |
| Consent and messaging preference changes | `local_privacy_consent_log`; `local_sentientia_user_channel_audit` (with IP) | Available (UAT) |
| Privacy erasure supplement | Event `erasure_supplemented` with counts only | Built (behind a feature flag, off by default) |
| Credentials in logs | Not logged by rule; pre-commit CHECK 4 blocks hard-coded credentials; the cart callback log redacts the secret and checksum but keeps buyer and transaction fields (S-18) | Available (UAT) |

### 12.2 Monitoring

| Item | Fact | Status |
|---|---|---|
| Log scan | `sentientia-logscan.sh`: daily root cron at 06:15 writes resources, Apache PHP errors, 5xx counts and failing tasks to a dated file kept 30 days | Available (UAT) |
| Alerting, uptime checks, application performance monitoring, central log shipping | None in the repository | Gap (S-11) |
| Load-balancer access logs and their retention | Not recorded | To be confirmed (owner: DevOps lead) |
| Security event monitoring (failed logins, admin actions) | Moodle logs only; no detection rules | Gap (S-11) |
| Incident response plan | Listed as a target in the trust-track documents; not finalised in the repository | Planned |

---

## 13. Backup and recovery

| Item | Fact | Status |
|---|---|---|
| UAT database backup | The SSH deploy user cannot read `config.php`, so no database backup can be taken from the shell; IT can snapshot the managed database. The UAT upgrade to Moodle 5.3 (21 October 2026) ran without a database backup; after the first new row, only a forward fix is possible | Gap (S-12) |
| UAT file backup | The deploy script backs up the files it replaces into a dated archive before extracting | Available (UAT) |
| Production (legacy) | Managed-database automated backups with a 7-day retention are stated in the ISO readiness self-assessment, which itself names backup schedule, encryption and tested restore as a gap | To be confirmed (owner: DevOps lead) |
| RPO and RTO | Not defined | Gap (S-12) |
| Restore testing | One small drill on UAT on 3 September 2026: a database dump of about 1 MB (650 tables), restored into a scratch database, recovery time 44 s at UAT volume (`docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md`, row 2.2). Its limits are recorded in `docs/cutover/GAP-CLOSURE-PLAN-2026-09-22.md`: the drill was not scripted, no RPO was set and no integrity check was run; Stage B volumes will be 100 to 1,000 times larger. There is no real-volume restore test. The Stage B rehearsal (restore a fresh backup of the live legacy site, upgrade it in two hops from Moodle 4.1.2 through 4.5 to 5.3, and prove 100% data parity before cutover) has a built and locally rehearsed kit; the run on the target server waits for IT | Gap (S-12); real-volume restore Planned (Stage B) |
| Rehearsal kit safety | `tools/rehearsal/` refuses databases not on an allow-list or with names containing `prod` or `uat`; dry run by default | Built (verified on development) |
| Erasure and backups | Erasure does not reach backups; no rule for how long backups holding erased subjects are kept | To be confirmed (owner: DevOps lead with DPO) |

---

## 14. STRIDE threat model

STRIDE: **S**poofing, **T**ampering, **R**epudiation, **I**nformation disclosure, **D**enial of service, **E**levation of privilege. Residual risk is our proposed rating (High, Medium, Low) after existing controls, for the Information Security team to confirm. Threat IDs are stable identifiers, not an order: T36 to T47 sit in the table of the component they concern.

A threat and its paired finding in section 19 carry the same rating, except where the finding covers more than the threat; the difference is then stated in the threat row (for example T30 and S-11). Some findings have no threat row of their own: S-06 is part of T17; S-24 is covered by T46; S-07, S-15, S-20 and S-21 are hygiene or integrity items (missing checks on own-data functions, legacy files, dead code, a missing dependency) with no separate threat.

### 14.1 Edge, web server and session

| # | STRIDE | Threat | Component | Existing control | Residual risk | Recommendation |
|---|---|---|---|---|---|---|
| T01 | I | Traffic interception or downgrade | Edge (TB1) | TLS 1.2 (ECDHE-GCM only) and TLS 1.3; TLS 1.0 and 1.1 refused (verified 24 September 2026); HSTS 1 year | Low | Confirm the certificate (expires 12 December 2026) renews automatically before the test windows (R15); consider HSTS `preload` |
| T02 | D | Volumetric or slow-request flood exhausts the application server: 2 vCPU, PHP-FPM pool of 5 workers (posture review, 4 September 2026; to be re-confirmed by the DevOps lead) | Edge, web server | Live-stream caps; API and xAPI limits | High (UAT size) | Confirm or add WAF rate rules; size production per the draft IT request; never load-test UAT |
| T03 | I | Version fingerprinting through shipped text files and server banners | Web server | Deny rules for `upgrade.txt`, `environment.xml`, change logs, manifests (UAT returns 404); `ServerSignature Off` in the template | Low | Confirm `ServerTokens Prod` in the main Apache configuration (it cannot be set in the template) |
| T04 | S | Session hijack through stolen cookie | Session | `Secure`, `HttpOnly`, `SameSite=Lax`; HTTPS only | Low | Confirm session timeout and administrator session policy |
| T05 | T | Cross-site scripting injects script into pages | Application | Mustache escaping, `s()`, `format_string()`, `format_text()`; `nosniff`. Known exception: `paygw_airpay/pay.php` (T40) | Medium (no CSP) | Introduce CSP in report-only mode, then enforce; review every `{{{ }}}` and every `echo` of HTML in plugin pages |
| T06 | T | Cross-site request forgery on state-changing pages | Application | `sesskey` in forms, AJAX and page scripts. Known exception: `paygw_airpay/pay.php` (T40) | Low | Add a static gate that flags state-changing page scripts without `require_sesskey()` |
| T07 | I | Clickjacking | Application | `X-Frame-Options: sameorigin` (Moodle core) | Low | Add `frame-ancestors` when CSP lands |

### 14.2 Identity and access

| # | STRIDE | Threat | Component | Existing control | Residual risk | Recommendation |
|---|---|---|---|---|---|---|
| T08 | S | Credential stuffing or password guessing against administrators | Authentication | Moodle lockout (threshold to be confirmed); password policy (to be confirmed) | High | Enforce MFA for site and tenant administrators before cutover; TOTP on the break-glass account |
| T09 | S | Account enumeration on sign-up or password reset | Self-registration | Sign-up no longer reveals existing accounts (fixed 3 September 2026); core reset behaviour | Low | Include in SISA test |
| T10 | D / S | Bulk fake registrations | Self-registration | Opt-in setting; e-mail confirmation | Medium | reCAPTCHA keys and a per-IP throttle before self-registration is enabled at cutover |
| T11 | E | Tenant administrator reads or writes another tenant's data | Tenant isolation (TB4) | ADR-031 helpers, fail closed, 135 tagged test files blocking in CI, path-boundary gate | Medium (large surface, 174 functions, 179 entry points) | Priority test area for SISA with one tenant-admin account per tenant and two learners in the Airpay tenant (section 20.4) |
| T12 | E | Role escalation through Sentientia role management | Roles plugin | `roles:manage` and `roles:assign` limited; scoped assign only to held roles; audit log; core role and user capabilities prohibited for tenant admins on UAT | Low | Test assignment paths across tenants |
| T13 | E | "Log in as" or profile edit used to take over accounts | Moodle core user management | Prohibited for tenant administrators on UAT | Low | Confirm the same prohibits are applied at cutover (tracked script) |
| T14 | E | Unresolved tenant treated as "all tenants" | Tenant isolation | `scope_path()` returns `null`; callers map it to `1=0`. `path_descendant_filter()` returns `1=1` for an empty path, so a caller that turns `null` into `''` fails open (section 5.4) | Low | Test users with empty or malformed `open_path`; add a fail-closed variant or a static check for the caller contract (S-28); review the 74 call sites |
| T15 | S | Forged LTI launch or replayed id token | LTI provider | JWT signature, nonce consumption | Low (flag OFF) | Test when flag is ON |
| T16 | S | Stolen or guessed SCIM or xAPI client credential | Machine endpoints | SCIM and xAPI tokens and xAPI Basic passwords stored as unsalted SHA-256; optional site-wide xAPI token and Basic password in plugin configuration, stored as entered, not bound to a tenant; rate limits; flags OFF | Medium when ON | Token rotation procedure; IP allow-listing per client; generated high-entropy secrets only, or `password_hash()` for Basic passwords; remove the site-wide credentials (S-25) |
| T43 | I | ICS feed token leaks through web-server and proxy logs, browser history, referrers or a shared calendar URL, giving lasting read access to a user's schedule | Calendar ICS feed | 64-character random token; 404 for unknown or revoked tokens; regeneration and revoke-all; flag OFF. Token stored as entered | Low to Medium when ON | Document rotation for users; hash tokens at rest; expire unused tokens; keep the feed payload minimal (S-26) |

### 14.3 Business logic and integrations

| # | STRIDE | Threat | Component | Existing control | Residual risk | Recommendation |
|---|---|---|---|---|---|---|
| T17 | T / E | Forged payment response marks an order paid and enrols the user, or sets a lower amount (path B) | `paygw_airpay/process.php` (TB8) | Verification guard enforced since June 2026; required fields; keyless CRC32 compared with `hash_equals()` | **Critical** if path B is reachable on UAT or at cutover; to be re-rated once the active path is known (A4) | Implement the provider's server-to-server order confirmation and check order id, amount and user against the stored order; random order ids; never trust the response amount; or retire path B |
| T36 | S / T / D | Callback integrity rests on a scheme that does not match the provider's contract (path A): the cart requires a custom `checksum` field, while Airpay's documented response hash is the CRC32 `ap_SecureHash`. Genuine callbacks would probably all fail, so paid orders are never confirmed (integrity and availability); and because the scheme is not the provider's, its strength against forgery cannot be judged from the provider's documentation (CWE-345) | `local_sentientia_cart/callback.php`, `classes/gateway/airpay_gateway.php` (TB8) | Secret-suffixed SHA-256 over sorted fields (not open to length extension); fail closed while the secret is empty; amount equal to the stored order; currency compared only when the payload carries one; `hash_equals()`; generic error responses. Not checked against Airpay's documentation or a sandbox | **High** until validated (blocks cutover with real payments); to be re-rated once the active path is known (A4) | Align `verify_callback()` with Airpay's documented response fields and hash and validate in the provider sandbox; confirm the payment with the provider's status API before `mark_paid()`; require the currency field (S-18) |
| T37 | T | Replay of a captured genuine callback | Cart callback | Checksum carries no timestamp or nonce; `mark_paid()` is idempotent for a paid order but also accepts a `failed` order; order numbers are sequential | Low to Medium | Store and refuse reused transaction references; accept success only for `pending` orders or after a provider status check (S-18) |
| T38 | S | Callbacks accepted from any source address | Cart callback | Optional IP allow-list `airpay_callback_iplist`, empty by default; drops are logged once per IP per hour | Medium | Set the allow-list to Airpay's published callback addresses before real payments; UAT value to be confirmed (owner: platform engineer) (S-18) |
| T39 | I | Buyer and transaction data kept in logs | Cart callback logging | `callback_logger` writes the whole payload to the PHP error log, redacting five keys; failed payments store the full payload in the order notes | Medium | Log an allow-list of fields only; set log retention; cross-reference the privacy pack (S-18) |
| T45 | D / T | Unauthenticated log flooding and log injection: any client that passes the IP check (no allow-list by default) can post payloads of any size, which `callback_logger::log()` writes to the PHP error log before the checksum is verified; the UAT server has a 40 GB disk (CWE-117, CWE-400) | Cart callback logging | IP allow-list (empty by default); five keys redacted | Medium | Log only after verification; cap the logged size; strip control characters; set the IP allow-list before real payments (S-18) |
| T40 | S / T / I | Payment page reached without login; order creation by cross-site request; stored cross-site scripting through the user's own profile fields echoed unescaped into the auto-submit form | `paygw_airpay/pay.php` | none: no `require_login()`, no `require_sesskey()`, no `s()` | Medium; High if path B is active | Add `require_login()`, `require_sesskey()` (or a POST with a session key), escape every value or render through a template; or retire path B (S-19) |
| T18 | R | Buyer disputes a purchase; incomplete payment audit | Payment, cart | Order and error tables; Moodle log; callback log (T39) | Medium | Keep immutable payment event records; confirm `logguests = 1` |
| T19 | T | Forged HRMS event creates, moves or suspends employees | HRMS webhook | Shared secret header, constant-time compare, flag OFF | Medium when ON | Prefer signed payloads (HMAC) if the vendor supports them; IP allow-list |
| T20 | I | Raw HRMS payloads kept without retention | Integrations log | none; flag OFF | Medium; High if the payloads carry HR-sensitive fields (to be confirmed with the HRMS integration owner), the same rating as S-09 | Classify payload, purge task, privacy provider (S-09) |
| T21 | T | Server-side request forgery through webhook target URLs | Outbound webhooks | https only; Moodle HTTP client blocked hosts and ports | Low | Include in SISA test when flag is ON |
| T22 | I | Personal data sent to the AI provider | Assistant chat; AI gateway | Gateway path: mock by default, live needs two flags, a key and quota, ledger without prompts. Assistant chat (default ON): with the gateway routing flag OFF (the default) it calls the provider directly whenever the assistant's own `api_key` is set, putting the user's first and last name, designation, organisation and course list into the system prompt; no ledger and no spend cap, only 20 questions per user per day. The user interface (UI) warns against pasting personal data | High at cutover if the direct path remains and a key is set (real personal data). Low on UAT today: UAT holds synthetic personas only. Key state on UAT to be confirmed (owner: Head of Learning & Development) | Route the chat only through the gateway, or remove the standalone key; minimise the context sent; data processing terms and residency decision; DPO review before any live call (S-22) |
| T41 | I | One user's personalised answer shown to another user, including a user of another tenant (TB4) | Assistant response cache | Answers to questions containing phrases such as "my deadlines" or "compliance status" are cached for 1 hour, keyed by the SHA-256 of the question text alone: no user and no tenant (`ai_client.php`, about line 75), although the answer was built from the first asker's context. The cache is read before the routing branch, so a cached live answer is served whatever the current mode | Medium when live answers are cached | Remove the shared cache for personalised questions, or key it by user and tenant (S-23) |
| T23 | E | Prompt injection makes the agentic assistant take an unintended action | Assistant (agentic) | Confirmation step before write actions; audit table; flags OFF | Medium when ON | Red-team prompts in code review; keep write actions behind confirmation |
| T24 | I | Certificate verification page shows the holder name to anyone with a code | Vendored `tool_certificate` | `noindex`; capability can be revoked | Low | Confirm code entropy; rate-limit verification at the edge |
| T25 | D | Long-lived event-stream connections exhaust PHP workers | Live sessions; leaderboard stream | Live: global and per-actor caps, 60 s lifetime, 503 when full. Leaderboard stream: login, capability and tenant checks; plugin flag OFF | Low when ON | Keep the live cap below the PHP-FPM pool of 5 on UAT (the posture review records a cap of 3 there); review the leaderboard stream's connection limits before its flag is turned ON |
| T44 | T / D | Any authenticated user opens a proctoring session for any quiz id and registers an object key of their choice against it; the retention purge or an erasure request then deletes that object from storage | Proctoring `upload_chunk` web service | UAT commit: capability `local/sentientia_proctoring:attempt` (granted to the `user` and `student` archetypes), key format check; no proctoring flag exists at that commit. Development: master flag `sentientia.proctoring.enabled`, and refusal while `sentientia.proctoring.recording.enabled` is OFF | On UAT: Medium if an S3 bucket and credentials (`aws_s3_bucket`) are configured, Low if not (to be confirmed, owner: DevOps lead). Low once the development guard is deployed | Deploy the master flag and guard to UAT (R14); when recording is built, bind keys to a server-generated per-session prefix and check the quiz id (S-27) |
| T46 | I / T | A flaw in the hand-written Web Push payload encryption (RFC 8291) or VAPID signing exposes push payloads or lets a third party send pushes as the site; the raw VAPID private key is stored in plaintext | `local_sentientia_pwa` (ADR-003) | Standard algorithms (ECDH, AES-128-GCM, ES256); PEM copy wrapped with AES-256-GCM only when a master key is set; flag OFF | Medium when the PWA flag is ON | Code review of the cryptography or replacement with a maintained library; encrypt both key forms and fail closed (S-13, S-24) |

### 14.4 Data, files, background jobs and operations

| # | STRIDE | Threat | Component | Existing control | Residual risk | Recommendation |
|---|---|---|---|---|---|---|
| T26 | T / I | SQL injection | Database | Moodle DB API with bound parameters; `get_in_or_equal()`; escaped `LIKE` | Low | SISA code review of dynamic SQL builders (report and audience filters) |
| T27 | I | Database credential misuse | Database | Credentials outside git and package. On UAT since 3 September 2026: application-scoped account with schema-only grants and no SUPER privilege; the e-mailed superuser password rotated (Stage A verification matrix, row E5) | Low | Apply the same least-privilege pattern on the production target (to be confirmed, owner: DevOps lead); deliver credentials through a vault or secrets manager (S-05) |
| T28 | I | Unencrypted DPDP export files remain on disk | File store | Export only to the requesting user | Medium | Expiry task; volume encryption (S-10) |
| T47 | I | Payment-gateway tables hold buyers' personal data that access and erasure requests do not reach, because the gateway declares `null_provider` and the structural privacy guard does not cover `payment/gateway` | `paygw_airpay` tables | none | High (privacy), the same rating as S-08 | Real privacy provider; extend the privacy guard to `payment/gateway` (S-08) |
| T29 | T | Malicious upload (script, polyglot, oversized archive) | File store | Accepted types, Moodle file serving, capability checks | Low to Medium | Confirm antivirus plugin and limits; test SCORM upload paths |
| T30 | R | Administrator actions not attributable | Logs | Moodle log, flag audit, role audit | Medium (attribution only; S-11 is rated High because it also covers alerting, uptime and detection) | Central log shipping and retention policy (S-11) |
| T31 | D | Data loss without recovery | Database, files | Deploy-time file backup only on UAT | High | Defined RPO/RTO; managed snapshots before every UAT change; tested restore (S-12) |
| T32 | E | Cron task acts outside its tenant | Scheduled tasks | Tenant helpers used in engines | Low | Include cron-driven features in code review |
| T33 | T | Unreviewed code reaches UAT | Deployment pipeline | Pre-commit hook (per clone), CI gates, checksum-verified deploy, dry run by default, drift abort | Medium | Record what is deployed (the deploy script writes no marker); require pull-request review |
| T34 | E | Malicious or vulnerable vendored code | Vendored plugins | ADR-041 process; records and tests per patch | Medium | Code review of learnerscript; dependency alerts (S-16) |
| T35 | I | Personal data from the test import of a production backup held on development workstations (UAT holds synthetic data only) | Development | Machine endpoint protection; test data not in git | Medium | Masked dataset for development workstations; keep production-derived data off UAT (S-17, A3) |
| T42 | E / T | A stolen maintainer token or compromised account dispatches the production deploy workflow, or changes it, and reaches the legacy live host with the stored SSH key. Separately, the workflow interpolates `${{ github.event.inputs.reason }}` (line 67) and `${{ github.event.inputs.confirm }}` (line 85) directly into `run:` shell scripts: GitHub Actions expression injection (CWE-78), reachable only by someone with write access who can dispatch the workflow | CI/CD (GitHub Actions, `deploy-production.yml`; TB10) | `workflow_dispatch` only; typed confirmation string; secrets not printed; the deploy job uses a `production` environment (lines 96 and 97) whose protection rules are to be confirmed (owner: DevOps lead) | Medium (token misuse); Low (expression injection, write access needed) | Confirm or set required reviewers on the `production` environment; enable branch protection on `production`; pass workflow inputs through environment variables rather than inline expressions; confirm whether the `PRODUCTION_*` secrets are still populated and remove them if unused (owner: DevOps lead) (S-29) |

---

## 15. OWASP ASVS 4.0 level 2: mapping summary by chapter

This is a chapter-level mapping against ASVS 4.0.3 level 2, prepared by the product team. It is a self-assessment for SISA to verify, not a claim of conformance. For each chapter we name the key level 2 requirements that matter for this system and give each a state: **Met** (control exists, with evidence), **Partial** (control exists with known exceptions), **Gap** (no control), **TBC** (depends on a setting or a test not yet confirmed). The selection is not the full requirement list; SISA should extend it. Requirement numbers follow ASVS 4.0.3 (version 5.0.0 renumbers them).

Chapter status follows one counting rule, applied in this order to the requirements listed for the chapter:

1. **Gap**: more than half of the listed requirements are Partial or Gap.
2. **Largely addressed**: more than half are Met, none is Gap or TBC, and no Partial is linked to a finding rated High or Critical (a conditional rating such as "High if path B is active" counts as High until the condition is settled).
3. **Partly addressed**: every other chapter.
4. **Not assessed**: no requirement listed.

| Chapter | Status | Key L2 requirements and state | Met / Partial / Gap / TBC | Evidence | Main gaps |
|---|---|---|---|---|---|
| V1 Architecture, design and threat modelling | Partly addressed | V1.1.2 threat modelling for design changes: Partial. V1.4.1 trusted server-side enforcement points: Met. V1.6.1 cryptographic key management policy: Gap. V1.10.1 source control with change records: Met | 2 / 1 / 1 / 0 | 42 ADR files including ADR-031 (tenancy), ADR-030 (machine interfaces), ADR-041 and ADR-044 (third-party and core code); this threat model; flag registry | Threat model not maintained per release; no key management policy (S-24, S-25) |
| V2 Authentication | Gap | V2.1.1 minimum password length: TBC. V2.2.1 anti-automation (lockout, throttling): Partial. Multi-factor authentication at level 2 (the V2 chapter requires it for level 2 applications; section V2.8 holds the one-time verifier requirements, provided here by `tool_mfa`): Gap, not enforced. V2.4.1 salted password storage: Partial (Moodle core Met; xAPI Basic passwords unsalted SHA-256). V2.10.4 service secrets kept out of source code: Met | 1 / 2 / 1 / 1 | Moodle core password hashing and lockout; hashed machine tokens; JWT for LTI; identity pack and MFA runbook | MFA not enforced (S-04); no CAPTCHA or rate limit on sign-up (S-02); break-glass account password-only; xAPI hashing (S-25); policy values to be confirmed |
| V3 Session management | Partly addressed | V3.2.1 new session token at login: Met. V3.3.1 logout invalidates the session: Met. V3.3.2 idle and absolute re-authentication: TBC (8 hours on development; UAT unknown). V3.4.1 to V3.4.3 `Secure`, `HttpOnly`, `SameSite`: Met. V3.7.1 re-authentication before sensitive actions: TBC | 5 / 0 / 0 / 2 | Moodle core sessions; cookie flags observed on UAT; "Log in as" limited to site administrators | Session timeouts and re-authentication for administrator actions to be confirmed |
| V4 Access control | Gap | V4.1.1 server-side enforcement: Met. V4.1.3 least privilege: Partial (39 capabilities usable only by site administrators; operational roles undecided; proctoring web services open to every authenticated user at the UAT commit, S-27). V4.1.5 fail securely: Partial (fail-closed tenant filters; empty-path caller contract, S-28). V4.2.1 protection against insecure direct object references: Partial (ADR-031 fixes; `upload_chunk` object key and any quiz id at the UAT commit, S-27; no independent test). V4.2.2 cross-site request forgery: Partial (`pay.php`, S-19). V4.3.1 multi-factor authentication for administrative interfaces: Gap | 1 / 4 / 1 / 0 | Capabilities on 169 of 174 functions; ADR-031; blocking `tenant_isolation` tests; escalation rules | S-04, S-07, S-19, S-27, S-28; no independent isolation test |
| V5 Validation, sanitisation and encoding | Partly addressed | V5.1.3 positive input validation: Met. V5.2.6 server-side request forgery protection: Met (https only, Moodle blocked hosts). V5.3.1 output encoding for the context: Partial. V5.3.3 context-aware escaping against cross-site scripting: Partial (`pay.php` writes profile values unescaped, S-19; triple-brace review open). V5.3.4 parameterised queries: Met | 3 / 2 / 0 / 0 | `required_param`/`optional_param`, `validate_parameters`, DB API, Mustache escaping, `format_text`; pre-commit CHECK 3 | S-19 (rated High if path B is active, so the chapter is not "Largely addressed"); raw input in legacy gateway files (S-15); `get_history` lacks `validate_parameters()` (S-07) |
| V6 Stored cryptography | Gap | V6.1.1 regulated private data encrypted at rest: TBC (infrastructure). V6.2.1 cryptographic modules fail securely: Partial (VAPID PEM wrapping falls back to plaintext). V6.2.2 proven algorithms and constructions: Partial (custom secret-suffix SHA-256 in the cart, keyless CRC32 in the gateway, hand-written Web Push code). V6.4.1 key management solution: Gap. V6.4.2 key material isolated from the application: Gap (keys in plugin configuration; VAPID raw private key in plaintext) | 0 / 2 / 2 / 1 | Core encryption for OAuth tokens; SHA-256 token hashes; HMAC-SHA256 webhooks | S-01, S-13, S-18, S-24, S-25; encryption at rest to be confirmed |
| V7 Error handling and logging | Partly addressed | V7.1.1 no credentials or payment details in logs: Partial (callback log redacts five keys only). V7.1.2 no other sensitive data in logs: Partial (full callback payloads; chat log). V7.1.3 security events logged: Met. V7.3.3 logs protected from unauthorised access and change: TBC. V7.4.1 generic error messages: Met | 2 / 2 / 0 / 1 | Moodle log store; append-only audit tables; generic 404 with no stack trace on UAT; generic 500 in the cart callback | No alerting, central logging or retention policy (S-11); callback logging (S-18); `loglifetime` to be confirmed |
| V8 Data protection | Gap | V8.1.1 sensitive data protected from server-side caching: Partial (shared assistant answer cache keyed without user or tenant, S-23). V8.3.1 no sensitive data in query strings: Gap (ICS feed token, S-26). V8.3.4 sensitive data identified with a handling policy: Partial. V8.3.8 retention of sensitive data: Partial (exports, HRMS payloads, callback logs) | 0 / 3 / 1 / 0 | Privacy providers in all 57 components; DPDP request hub; erasure supplement (flags OFF); no-store caching headers | S-08, S-09, S-10, S-22, S-23, S-26 (cross-reference privacy pack) |
| V9 Communication | Largely addressed | V9.1.1 TLS for all client connections: Met. V9.1.3 only current TLS versions: Met (TLS 1.0 and 1.1 refused, TLS 1.2 with ECDHE-GCM suites only, TLS 1.3 accepted; test of 24 September 2026). V9.2.2 TLS to internal and external services: Partial (provider calls over HTTPS; the load balancer to server leg is plain HTTP inside the virtual private cloud, to be confirmed) | 2 / 1 / 0 / 0 | TLS 1.2 and 1.3 at the edge (`docs/visual-evidence/2026-09-24/README.md`); HSTS; https-only webhook targets | S-14 (`ServerTokens`); certificate renewal before 12 December 2026 (R15) |
| V10 Malicious code | Gap | V10.2.1 no unauthorised data collection or phone-home: Partial (no hidden admin paths found in review; assistant chat's direct provider call, S-22; legacy blocks of unconfirmed provenance). V10.3.2 integrity of code and updates: Partial (checksum-verified deploy; no deployed-commit record) | 0 / 2 / 0 / 0 | Pre-commit and CI gates; vendored code governance (ADR-041) | No software composition analysis or dependency alerts (S-16) |
| V11 Business logic | Gap | V11.1.1 sequential flow enforced: Partial (cart flow enforced server-side; gateway path trusts the response). V11.1.4 anti-automation: Partial (machine endpoints rate-limited; none on sign-up or AJAX). V11.1.5 business limits and validation: Partial (the cart compares amount and currency; the gateway stores the response amount) | 0 / 3 / 0 / 0 | Cart gate (buyer can buy only what the catalogue shows); rate limits on machine endpoints; AI quotas | S-01, S-02, S-18, S-19 |
| V12 Files and resources | Partly addressed | V12.1.1 file size limits: Met. V12.3.1 user-supplied names not used as storage paths: Partial (`upload_chunk` object key at the UAT commit, S-27). V12.4.1 files stored outside the web root: Met. V12.4.2 antivirus scanning: TBC. V12.5.1 web tier serves only expected file types: Met (deny rules) | 3 / 1 / 0 / 1 | Moodle file API; accepted types; files outside web root; deny rules for fingerprint files | Antivirus to be confirmed; export file handling (S-10); S-27 |
| V13 API and web service | Partly addressed | V13.1.3 no keys or tokens in API URLs: Gap (ICS token, S-26). V13.1.4 authorisation at URI and resource level: Partial (5 functions without a declared capability). V13.2.3 CSRF protection for cookie-authenticated APIs: Met (`service.php` session key). V13.2.5 content-type validation on raw endpoints: TBC. Most flag-gated APIs have never been tested with their flags ON | 1 / 1 / 1 / 1 | Capability-gated functions; tenant-scoped Public API with rate limit and OpenAPI; SCIM fail-closed; signed webhooks; xAPI rate limit | S-07, S-18, S-20, S-25, S-26; flag-window test (section 20.5) |
| V14 Configuration | Partly addressed | V14.2.1 components up to date: Partial (no alerts). V14.2.2 unneeded features and samples removed: Gap (`process_old.php`, `error.php`, `paygw_airpay_get_form`, OTP template). V14.2.4 third-party components from trusted sources: Partial (legacy BizLMS blocks). V14.3.3 headers do not expose versions: TBC (`ServerTokens`). V14.4.3 Content Security Policy: Gap. V14.4.4 to V14.4.7 `nosniff`, HSTS, `Referrer-Policy`, `X-Frame-Options`: Met | 4 / 2 / 2 / 1 | Security headers; disclosure blocks; packaging gates for `config.php`; flags default OFF | S-03, S-15, S-16, S-20; no secrets manager; UAT hardening values (lockout, timeout, cron CLI only) to be confirmed |

Across the 14 chapters: 67 key requirements listed, 24 Met, 26 Partial, 9 Gap and 8 TBC. V3.4.1 to V3.4.3 count as three requirements and V14.4.4 to V14.4.7 as four; the level 2 multi-factor requirement in V2 counts as one. Chapter statuses under the rule above: 1 Largely addressed (V9), 7 Partly addressed (V1, V3, V5, V7, V12, V13, V14) and 6 Gap (V2, V4, V6, V8, V10, V11).

### 15.1 OWASP Top 10 (2021) cross-reference

| Category | Where addressed | Open items |
|---|---|---|
| A01 Broken access control | Sections 5, 14.2 | S-07, S-19 (CSRF), S-27, S-28; independent isolation test |
| A02 Cryptographic failures | Section 9 | S-01, S-10, S-13, S-24, S-25, S-26; S-05 remediated on UAT |
| A03 Injection | Section 6 | S-19 (cross-site scripting); code review of dynamic SQL and raw template output |
| A04 Insecure design | Sections 3, 11, 14 | S-01, S-02, S-18, S-22, S-23 |
| A05 Security misconfiguration | Sections 6, 9, 13 | S-03, S-14, S-15, S-20, S-29; S-05 for the production target |
| A06 Vulnerable and outdated components | Section 10 | S-16, S-21 |
| A07 Identification and authentication failures | Section 4 | S-02, S-04, S-19 (missing login), S-25 |
| A08 Software and data integrity failures | Sections 10, 14.3, 14.4 | S-01, S-18, S-29; deploy record (T33) |
| A09 Security logging and monitoring failures | Section 12 | S-11; callback payload logging and log flooding (S-18, T45) |
| A10 Server-side request forgery | Section 6 | Test webhook targets when flag is ON |

---

## 16. Known defect classes and the automated guards built for them

Seventeen recurring defect classes now have a guard. The security-relevant ones:

| Defect class | What happened | Guard | Where it runs |
|---|---|---|---|
| Unbounded tenant path prefix | `LIKE '/1%'` filters counted neighbouring tenants; shipped 4 times | `tenant::path_descendant_filter()`; `tools/check-path-boundary.php` | Pre-commit CHECK 18; CI `path-boundary-check` (blocking) |
| Cross-tenant authority by capability | Tenant admins could read and write other tenants (73 hits, 70 confirmed) | ADR-031 helpers; `@group tenant_isolation` | CI `phpunit-52` step 1 (blocking, floor 40 files) |
| False privacy "no personal data" claim | Plugins declared `null_provider` while holding user tables; access and erasure requests returned nothing | `privacy_coverage_test` walks every local plugin's schema | PHPUnit (does not reach `payment/gateway`: S-08) |
| Erasure completeness | Stock privacy approval truncates context lists; approved deletions leave data | Supplementary provider (flags OFF); canary test | CI `phpunit-52` step 2 (blocking) |
| Site-id type comparison | `(int)$id !== SITEID` always true; the site-course guard did nothing; shipped 5 times | `tools/check-siteid-compare.php` | Pre-commit CHECK 20; CI (blocking) |
| Cross-tree drift | A fix landed in one of two deployed copies only | `tools/check-tree-drift.php` with a baseline; deploy script aborts on drift | Pre-commit CHECK 19 (warning); CI (blocking for new drift) |
| Raw superglobals and hard-coded credentials | Historical BizLMS code | Pattern checks | Pre-commit CHECKs 3 and 4 |
| Committed `.env` | Risk of key leakage | File check | Pre-commit CHECK 5 |
| Core file edited without a record | Untracked core divergence | Record and marker check | Pre-commit CHECK 6; CI `core-mods.yml` |
| Template comment leak, dead AMD, missing end-of-body | Pages rendered wrong or without their JavaScript | Quality-gate scanners (ADR-027) | Pre-commit CHECKs 13 to 15; CI |
| Upgrade step below shipped version | Silently skipped schema or permission steps | `tools/check-upgrade-step-versions.php` | CI (blocking) |
| Capabilities not reaching real roles | Archetype grants missed BizLMS roles | Archetype grants plus upgrade back-fill; persona walks | Manual and Playwright |
| MySQL 8 reserved words | Names valid on MariaDB failed on MySQL 8 | `tools/uat/mysql84_reserved_scan.py` | Before each UAT push |
| Role-switch force-pin | Multi-role administrators demoted site-wide | Browser persona walks | Manual |

Limits: the pre-commit hook is per clone and can be bypassed; CI is the binding layer. The AMD build gate checks existence only, so a stale built file passes. The full PHPUnit suite is advisory; only the two groups block, and CI runs PHPUnit on Moodle 5.2, not 5.3.

---

## 17. Test assets relevant to security

| Asset | Count | Notes |
|---|---|---|
| PHPUnit test methods (static count) | 4,861 in 464 files | Data-provider cases are extra |
| `tenant_isolation` tagged test files | 135 | Blocking in CI |
| `privacy_erasure` tagged test files | 1 | Blocking in CI |
| Public API tests | 11 files: 3 under `tests/external/` (create enrolment, list courses, v1 tenant scope) and 8 others (LTI JWT, LTI registration, rate limiter, SCIM, SCIM groups, SCIM cross-tenant principal, tenant scope, webhooks) | `local/sentientia_api/tests/`; `_facts/00-FACTS-INDEX.md` section 7 |
| Playwright | 23 test declarations in 12 specs, including an axe smoke test | CI lane advisory |
| Persona journeys | 115 steps across 11 personas (development) | Local only |
| Recorded runs | Moodle 5.3 platform suite 586 OK; 5.1 flag-writing set 785 OK | The 20 November development changes had no finished PHPUnit run |

---

## 18. Security testing performed so far, and not performed

### 18.1 Performed

| Date | Activity | Scope | Result | Evidence |
|---|---|---|---|---|
| 21 May 2026 | Internal cryptography audit | Web Push and stored keys | VAPID key found unencrypted. The fix wraps only the PEM copy with AES-256-GCM, and only when a master key is configured; the raw private key is still stored in plaintext (S-24) | `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`; `local/sentientia_pwa/classes/vapid_key_manager.php` |
| 2 June 2026 | Payment-response verification fix | `paygw_airpay` callback | Guard enforced; 5 unit tests (forged status, tampered amount, missing field, missing config); provider sandbox round trip not done | `docs/security/2026-06-02-airpay-payment-verification-fix.md` |
| 3 September 2026 | UAT backup and restore drill, and capacity baseline | UAT (then Moodle 5.2), fresh install with synthetic data | Drill: database dump of about 1 MB (650 tables) restored into a scratch database, recovery time 44 s; not scripted, no RPO, no integrity check. Capacity: 20 virtual users for 120 s, 0 errors, 95th-percentile response time about 1 s | `docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md` rows 2.2 and 2.3; limits in `docs/cutover/GAP-CLOSURE-PLAN-2026-09-22.md` |
| 3 September 2026 | UAT database account hygiene | UAT database | Application moved to an application-scoped account (schema-only grants, no SUPER); e-mailed superuser password rotated, old one rejected | `docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row E5 |
| 3 to 4 September 2026 | Internal UAT security posture review: code review of internet-facing entry points, read-only probe, configuration checklist | Unauthenticated and token endpoints on UAT (then Moodle 5.2) | 2 Critical, 4 High, 5 Medium, 1 Low. Fixed: C2, H1, H3, H4, M1, M3, M4 (CSP deferred); M2 clarified; open: C1 residual, H2, M5 | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md` |
| 24 September 2026 | TLS and header check of UAT from a client that offers legacy protocols | UAT edge | TLS 1.0 and 1.1 refused; TLS 1.2 with ECDHE-GCM suites only; TLS 1.3 accepted; HSTS, X-Frame-Options, nosniff and cookie flags present; certificate expires 12 December 2026 | `docs/visual-evidence/2026-09-24/README.md` |
| 25 September 2026 | Cross-tenant authority sweep | About 30 plugins | 73 hits, 70 confirmed, fixed under ADR-031 | `docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md` |
| 30 September 2026 | Web-service smoke after tenant-admin capability prohibits on UAT | 53 read functions by 4 personas | 0 errors; refusals as expected | `moodle-enhancement/PROJECT-STATE.md` (entry of 29 to 30 September 2026); `docs/operations/ROLE9-CORE-CAPS-2026-09-26.md` |
| 13 November 2026 | Privacy erasure rehearsal | 515 requests on a local copy (test data) | 286 processed, 0 failed deletes, 0 notifications sent; core truncation bug found | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| 20 November 2026 | Internal code reading for this document: both payment paths, raw-HTTP entry points, assistant chat, PWA key storage, xAPI credentials, calendar feed, proctoring chunk registration, production deploy workflow | Code at the UAT commit and on development | New findings S-18 to S-29; raw-HTTP inventory of 19 files, reconciled with the fact base's 18 in section 8.1 | This document, sections 8 and 19 |
| Continuous | Automated gates (section 16) and PHPUnit groups | Every push and pull request | Blocking where stated | `.github/workflows/ci.yml` |

### 18.2 Not performed

| Activity | Status |
|---|---|
| Independent penetration test | Not performed. A VAPT programme was proposed in June 2026 with dates that lapsed; this document proposes SISA's scope |
| Independent source code review | Not performed |
| Authenticated dynamic scanning (DAST) | Not performed |
| Static application security testing tool (SAST) or Moodle code-checker in CI | Not performed (phpcs not in CI) |
| Software composition analysis or dependency vulnerability scan | Not performed |
| TLS configuration scan by a third-party service | Not performed. The protocol and cipher check of 24 September 2026 (section 18.1) settles legacy-protocol refusal without one |
| Load, soak and denial-of-service resilience on a production-class environment | Not run |
| Payment provider sandbox round trip of either callback (`paygw_airpay/process.php` or `local_sentientia_cart/callback.php`) | Not done; neither checksum scheme has been validated against the provider |
| Infrastructure review (network rules, IAM, database parameters, CIS benchmarks) | Not done by the product team; owned by IT |
| Restore test at real volume, with an RPO and an integrity check | Not done. Only the small, unscripted UAT drill of 3 September 2026 exists (section 18.1) |
| Tests of flag-gated surfaces with their flags ON on UAT | Not done: SCIM and xAPI were probed only in their OFF or unauthenticated state; calendar feed, proctoring, PWA, HRMS webhook and live sessions not probed |
| Execution of the payment gateway pages on UAT | Not done: whether `pay.php` and `process.php` run at all without the legacy `local_biz_cart` code is unknown (S-21) |

---

## 19. Known open security findings (pre-populated register)

Severity is our proposal for the Information Security team to confirm. Status label of the affected code is given. These enter SISA's register as "known before test" so SISA can confirm, re-rate or close them.

| ID | Title | Component | CWE | Severity (proposed) | Code status | Recommendation | Owner (role) |
|---|---|---|---|---|---|---|---|
| S-01 | Payment path B (`paygw_airpay`): the browser-relayed payment response can be forged. Keyless CRC32 over fields the attacker controls or can see; no server-to-server order confirmation (`check_payment()` returns `false` and is unused on the callback path); order id is time plus user id; the response amount overwrites the stored order cost | `paygw_airpay` (`process.php`, `classes/airpay_helper.php`) | CWE-345, CWE-328, CWE-330 | Critical if path B is reachable on UAT or at cutover; Low (a removal item) if path B is retired. Re-rate after decision A4 | Available (UAT); guard enforced since June 2026; reachability on UAT to be confirmed | Server-side order confirmation; compare amount, order id and user with the stored order; random order ids; provider sandbox validation; or retire path B together with S-19, S-20 and S-21 | Head of Learning & Development with the Airpay payments team |
| S-02 | Self-registration has no CAPTCHA and no rate limit | `local_sentientia_users` sign-up | CWE-799 | Medium | Available (UAT) | reCAPTCHA keys per site; per-IP throttle; keep the setting off until both exist | IT (keys); engineering (throttle) |
| S-03 | No Content Security Policy | Web server configuration | CWE-1021, CWE-79 (defence in depth) | Medium | Available (UAT) | Report-only CSP with nonces, then enforce | Engineering with DevOps |
| S-04 | MFA not enforced; break-glass administrator password-only | Moodle `tool_mfa` configuration | CWE-308 | High | Available (UAT) | Run the MFA sequence on UAT; enforce for administrators before cutover | Head of Learning & Development with IT |
| S-05 | Database account hygiene. Remediated on UAT on 3 September 2026: the posture review had found the application using the managed database's superuser with an e-mailed password; the application now uses an application-scoped account (schema-only grants, no SUPER privilege) and the e-mailed password was rotated, the old one rejected (`docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row E5). Open: credentials are not delivered through a vault or secrets manager, and the same pattern on the production target is not yet confirmed | UAT and production-target database | CWE-250, CWE-522 | Low (re-rated from High after remediation on UAT) | Available (UAT) (remediated); production target to be confirmed (owner: DevOps lead) | Apply the same least-privilege account on the production target; deliver credentials through a vault or secrets manager | DevOps lead |
| S-06 | Payment gateway hidden form carries merchant identifiers in the browser (vendor scheme) | `paygw_airpay/pay.php` | CWE-200 | Low on its own; contributes to S-01 | Available (UAT) | Resolved by S-01 server-side confirmation, or by retiring path B | Engineering |
| S-07 | Five web-service functions declare no capability (leaderboard opt-out, notification preferences, assistant `ask` and `get_history`, `paygw_airpay_get_form`). The assistant functions do not call `validate_context()`; `get_history` does not call `validate_parameters()` and its `limit` has no upper bound. `paygw_airpay_get_form` is tracked as S-20 | assistant, leaderboard, notifications, payment gateway | CWE-862, CWE-20 | Low | Available (UAT) | Add `validate_context()`, explicit capabilities, `validate_parameters()` in `get_history` and an upper bound on `limit` | Engineering |
| S-08 | Payment gateway declares `null_provider` while holding three user tables; the structural privacy guard does not reach `payment/gateway` | `paygw_airpay` | CWE-359 | High (privacy) | Available (UAT) | Real provider; extend guard | Engineering with DPO |
| S-09 | HRMS webhook stores raw request bodies with no retention; plugin declares `null_provider` | `local_sentientia_integrations` | CWE-532 | Medium; High if the payloads carry HR-sensitive fields (to be confirmed with the HRMS integration owner) | Built (behind a feature flag, off by default) | Classify, purge, provider | Engineering with HRMS integration owner |
| S-10 | DPDP export files unencrypted and not expired | `local_sentientia_privacy` | CWE-312, CWE-459 | Medium | Available (UAT) | Expiry task; volume encryption | Engineering; DevOps lead |
| S-11 | No alerting, uptime checks, central logs or security monitoring | Operations | CWE-778 | High (before cutover) | Gap | Monitoring and alerting plan before cutover | DevOps lead |
| S-12 | No database backup path from the UAT shell; no RPO/RTO; no real-volume or integrity-checked restore test. The only evidence is one unscripted UAT drill of 3 September 2026 (about 1 MB dump, restore into a scratch database, recovery time 44 s; `docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md`) | Operations | none assigned (resilience) | High (before cutover) | Gap | Snapshot before each UAT change; define RPO/RTO; script the drill with an integrity check; test restore at real volume in Stage B | DevOps lead |
| S-13 | Hand-written Web Push cryptography accepted pending a security review that is not recorded | `local_sentientia_pwa` (ADR-003) | CWE-327 (to be confirmed) | Medium | Built (behind a feature flag, off by default) | Include in SISA code review or replace with a maintained library | Engineering |
| S-14 | `ServerTokens Prod` not confirmed in the main Apache configuration (`ServerSignature Off` is already set by the web-server template). Legacy TLS refusal was verified on 24 September 2026 and is no longer part of this finding | Web server | CWE-200 | Low | Available (UAT) | `ServerTokens Prod` in the main Apache configuration | DevOps lead |
| S-15 | Legacy files in the product tree: `process_old.php` (raw `$_POST`, no Moodle bootstrap, old enrolment logic), `error.php` (raw `$_REQUEST`), the BizLMS OTP login template | `paygw_airpay`; theme | CWE-1164 | Low | Available (UAT) | Remove after confirming no route uses them | Engineering |
| S-16 | No automated dependency alerting or written patch cadence for Moodle security releases and vendored plugins | Repository and process | CWE-1104 | Medium | Gap | Dependency alerts; monthly Moodle security release check; vendored plugin watch list | Engineering with DevOps lead |
| S-17 | Development workstations hold a test import of a production backup with real personal data; no masked dataset exists. UAT is not affected: it is a fresh install with synthetic personas and seeded demo data | Development workstations | CWE-359 | Medium | Gap | Masked dataset for development; keep production-derived data off UAT before and during the test windows (A3); the Stage B rehearsal stays on its own target | DPO with Head of Learning & Development |
| S-18 | Payment path A (`local_sentientia_cart`): the callback verification does not match Airpay's contract. Airpay's documented response hash is the CRC32 `ap_SecureHash` (cited in `payment/gateway/airpay/classes/airpay_helper.php`, lines 236 to 250), while `verify_callback()` requires a `checksum` field computed by a custom scheme (SHA-256 over sorted fields with the secret appended), so genuine Airpay callbacks would probably all fail and paid orders would never be confirmed. The class comment (`airpay_gateway.php` line 22) describes yet another formula. The scheme has not been checked against Airpay's documentation or a sandbox. Further weaknesses: no provider status check before `mark_paid()`; the currency is compared only when the payload carries a currency field; the checksum has no timestamp or nonce and a `failed` order can still be marked paid; the IP allow-list is empty by default; every payload that passes the IP check is written to the PHP error log before verification, with no size cap and five keys redacted (T45), and failed payments store the full payload in the order notes. Controls present: fail closed while the secret is empty; amount equal to the stored order; constant-time compare; generic errors. The secret-suffix construction is not open to length extension, so the algorithm itself is not the main weakness | `local_sentientia_cart` (`callback.php`, `classes/gateway/airpay_gateway.php`, `classes/callback_logger.php`, `classes/cart_manager.php`) | CWE-345 (primary); CWE-294, CWE-532, CWE-117, CWE-400 | High (blocks cutover with real payments, on integrity and availability grounds, until aligned and validated); re-rate after decision A4 | Available (UAT) | Align verification with Airpay's documented response fields and hash and validate it in the sandbox; confirm payment with the provider's status API; require the currency field; accept success only for `pending` orders and refuse reused transaction references; set the IP allow-list; log only after verification, an allow-list of fields, with a size cap | Head of Learning & Development with the Airpay payments team; Engineering |
| S-19 | `pay.php` has no `require_login()` and no `require_sesskey()`, creates orders for the current user on a plain GET, and writes the user's first name, last name, city, state, e-mail and phone into HTML `value="..."` attributes without escaping: stored cross-site scripting through the user's own profile, request forgery and missing authentication | `paygw_airpay/pay.php` | CWE-306, CWE-352, CWE-79 | Medium; High if path B is active | Available (UAT) | Require login and a session key; escape every value or render through a Mustache template; or retire path B | Engineering |
| S-20 | `paygw_airpay_get_form` cannot run: it calls `airpay_helper::get_payment_script()`, which does not exist, and calls `get_unprocessed_order()` and `create_order()` with too few arguments. The gateway's JavaScript does not use it | `paygw_airpay/classes/external/get_form.php` | CWE-561 | Low | Available (UAT) | Remove the function and its service definition, or rewrite it with the checks `pay.php` lacks | Engineering |
| S-21 | `pay.php`, `process.php` and `classes/airpay_helper.php` depend on legacy `local_biz_cart` code (the `payment_added` event, the `biz_cart` class, the `checkout_success` and `checkout_failed` templates, the `local_biz_cart_history` table) that is not in the repository. Whether path B runs at all on UAT is unknown | `paygw_airpay` | n/a (integrity of a payment path) | Medium | Available (UAT); whether it runs on UAT to be confirmed (owner: platform engineer) | Decide which payment path survives cutover (A4); if path B stays, remove the legacy dependencies and test it end to end | Platform engineer with Head of Learning & Development |
| S-22 | The assistant chat (`ai.assistant.enabled`, default ON) calls the AI provider directly, outside the AI gateway, whenever the gateway routing flag is OFF (the default) and the assistant's own `api_key` is set. The system prompt carries the user's first and last name, designation, organisation and course list; there is no ledger and no spend cap; questions and answers are stored in `local_sentientia_chat_log` | `local_sentientia_assistant` (`classes/ai_client.php`) | CWE-359 | High at cutover if the direct path remains and a key is set (real personal data would leave the platform); Low on UAT today, which holds synthetic personas only | Available (UAT); key state on UAT to be confirmed (owner: Head of Learning & Development) | Route the chat only through the gateway, or remove the standalone key setting; minimise the context sent; DPO review before any live call; cross-reference the privacy pack | Head of Learning & Development with DPO; Engineering |
| S-23 | The assistant caches answers to questions containing phrases such as "my deadlines" or "compliance status" for 1 hour, keyed by the SHA-256 of the question text alone, with no user and no tenant (`ai_client.php`, about line 75). A later user asking the same question, in any tenant, receives an answer built from the first user's context, which crosses the tenant boundary (TB4). The cache is read before the routing branch, so a cached live answer is served whatever the current mode | `local_sentientia_assistant` (`classes/ai_client.php`, `local_sentientia_chat_cache`) | CWE-524 | Medium once live answers are cached; none while no live answer has been generated | Available (UAT) | Remove the shared cache for personalised questions, or key it by user and tenant | Engineering |
| S-24 | The VAPID raw private key (`vapid_private_b64url`) is stored in plaintext in plugin configuration; only the PEM copy is encrypted, and only when a master key is configured; without one, or when encryption fails, the PEM is also stored in plaintext. Master key state on UAT to be confirmed (owner: DevOps lead) | `local_sentientia_pwa` (`classes/vapid_key_manager.php`) | CWE-312, CWE-320 | Medium when the PWA flag is ON | Built (behind a feature flag, off by default) | Encrypt both key forms or keep only one; require a master key before key generation; fail closed when encryption fails | Engineering; DevOps lead |
| S-25 | xAPI per-client Basic passwords are hashed with unsalted SHA-256; a site-wide bearer token and a site-wide Basic user and password can be set in plugin configuration, stored as entered, and authenticate without a tenant binding (tenant id 0) | `local_sentientia_xapi` (`classes/lrs/authenticator.php`) | CWE-916, CWE-256 | Medium when ON | Built (behind a feature flag, off by default) | Generated high-entropy secrets only, or `password_hash()` for Basic passwords; remove or tenant-bind the site-wide credentials | Engineering |
| S-26 | The calendar ICS feed authenticates with a bearer token in the query string; the token is stored as entered and can leak through server and proxy logs, browser history and shared calendar links | `local_sentientia_calendar` (`ics.php`, `classes/token_manager.php`) | CWE-598, CWE-312 | Low to Medium when ON | Built (behind a feature flag, off by default) | Hash tokens at rest; expire unused tokens; tell users how to regenerate; keep the feed content minimal | Engineering |
| S-27 | At the UAT commit the proctoring plugin registers no feature flag. `start_session` and `upload_chunk` check only `local/sentientia_proctoring:attempt`, which `db/access.php` grants to the `user` and `student` archetypes, and `session_manager::start_session()` accepts any quiz id. Any authenticated user can therefore open a session, bring it to a state that accepts chunks and register an object key of their choice; the retention purge and an erasure request then delete that object from storage | `local_sentientia_proctoring` (`classes/external/start_session.php`, `upload_chunk.php`, `classes/session_manager.php`, `db/access.php`) | CWE-639, CWE-73, CWE-862 | On UAT: Medium if an S3 bucket and credentials (`aws_s3_bucket`) are configured, Low if not (to be confirmed, owner: DevOps lead). Low once the development guard is deployed | Available (UAT), gated by a capability held by all authenticated users. Mitigation (master flag `sentientia.proctoring.enabled`, refusal while `sentientia.proctoring.recording.enabled` is OFF): Built (verified on development) | Deploy the master flag and guard to UAT before the test window (R14); check that the quiz id belongs to a quiz that requires proctoring; when recording is built, bind keys to a server-generated per-session prefix | Engineering; DevOps lead (bucket state) |
| S-28 | `tenant::path_descendant_filter()` returns `1=1` (no restriction) for an empty path or `/`; the caller contract (`null` scope means `1=0`, `''` means cross-tenant) is documented only in code comments and not checked automatically; 74 non-test call sites | `local_sentientia_platform` (`classes/tenant.php`) and callers | CWE-636 | Low | Available (UAT) | Add a fail-closed variant that needs an explicit cross-tenant argument, or a static check for casts of `scope_path()`; review the call sites | Engineering |
| S-29 | The production deploy workflow holds SSH secrets for the legacy live host. Its deploy job uses a `production` environment (lines 96 and 97), but whether that environment has required reviewers or branch rules is a repository setting the code does not show. The workflow also interpolates `${{ github.event.inputs.reason }}` (line 67) and `${{ github.event.inputs.confirm }}` (line 85) directly into `run:` shell scripts (expression injection; exploitable only by someone with write access who can dispatch the workflow) | `.github/workflows/deploy-production.yml` | CWE-284, CWE-78 | Medium | In place (repository or process); environment protection rules and whether the secrets are still populated to be confirmed (owner: DevOps lead) | Confirm or set required reviewers on the `production` environment; branch protection on `production`; pass workflow inputs through environment variables rather than inline expressions; remove the secrets if unused | DevOps lead |

---

## 20. Proposed scope for SISA: penetration test and code review

### 20.1 Objectives

1. Find exploitable vulnerabilities in the Sentientia application and its Moodle 5.3 base before cutover.
2. Test tenant isolation between Airpay, Public and ZEEA as a first-class objective.
3. Confirm, re-rate or close the known findings in section 19.
4. Review security-critical source code (section 20.3).

### 20.2 Environment and in-scope URLs

| Item | Proposal |
|---|---|
| Environment | UAT, `https://academy2.airpay.ninja`, Moodle 5.3, code at the UAT commit plus any fixes deployed before the test window (the exact commit is stated in the rules of engagement) |
| Production | Out of scope (legacy BizLMS, being replaced) |
| Test windows | Agreed in advance; the UAT server is 2 vCPU / 2 GB, so automated scanning must be throttled, and no denial-of-service or load testing |
| Pre-test safety | IT takes a managed-database snapshot before the window and after it (S-12). The UAT certificate expires on 12 December 2026; the DevOps lead confirms automatic renewal before the first window (R15) |
| Data | The synthetic UAT dataset: test personas and seeded demo data from the fresh install. No production-derived data is loaded onto UAT before or during the window (decision A3); the Stage B rehearsal runs on its own target |
| Payment testing | Sandbox merchant credentials only; no real money; the provider must not be attacked. Both payment paths are tested unless decision A4 retires one; forged-callback tests against `local/sentientia_cart/callback.php` need the cart's sandbox secret configured on UAT for the window. Rules of engagement note: path B (`payment/gateway/airpay/pay.php`) hard-codes the provider's production payment URL as its form action, so path B tests with sandbox credentials reach the provider's live host; path A posts to the URL in the cart's `airpay_endpoint` setting, whose default is the same production payment URL (UAT value to be confirmed). Before the window the Airpay payments team names the provider endpoint each path uses and confirms that the sandbox merchant credentials are valid there |

In-scope URL groups (paths on UAT):

| Group | Paths |
|---|---|
| Public surfaces | `/`, `/login/index.php`, `/local/sentientia_pages/homepage.php`, `/local/sentientia_catalog/public.php`, `/local/sentientia_catalog/course.php`, `/local/sentientia_users/signup.php`, `/local/sentientia_users/privacypolicy.php`, `/local/sentientia_users/termscondition.php`, `/local/sentientia_pages/index.php`, `/admin/tool/certificate/index.php`, `/local/sentientia_pwa/` files, Moodle password reset |
| Authenticated application | `/my/`, all `/local/sentientia_*` pages (179 top-level entry points, including the classroom calendar export `/local/sentientia_classroom/ics.php` and the e-mail template preview `/local/sentientia_emails/preview_ajax.php`), `/blocks/sentientia_*`, course, quiz, SCORM and certificate pages |
| AJAX and token web services | `/lib/ajax/service.php` and `/webservice/rest/server.php` for the 174 Sentientia functions plus core functions reachable by the test roles. In pass 1 this includes the proctoring web services (`local_sentientia_proctoring_start_session`, `submit_identity`, `upload_chunk` and the others), which no flag gates at the UAT commit (S-27), and the `Airpay AI Assistant` service (`restrictedusers = 0`) if token creation is enabled |
| Payment, path A (order cart) | `/local/sentientia_cart/checkout.php`, `/local/sentientia_cart/callback.php` (server-to-server, no session), `/local/sentientia_cart/return.php`, cart and order pages, with sandbox credentials |
| Payment, path B (Moodle payment subsystem) | `/payment/gateway/airpay/` (`pay.php`, `process.php`, `process_old.php`, `error.php`, `checksum.php`) with sandbox credentials |
| Machine interfaces (flag window) | `/local/sentientia_api/scim/v2.php`, `/local/sentientia_api/lti/*`, `/local/sentientia_xapi/lrs/statements.php`, `/local/sentientia_integrations/webhook.php`, outbound webhook targets controlled by SISA, `/local/sentientia_live/stream.php`, `/local/sentientia_leaderboard/stream.php`, `/local/sentientia_calendar/ics.php` (token in the query string), `/local/sentientia_pwa/` files; the proctoring web services again with the development master flag ON, if it is deployed before the window (R14) |
| Administration | Moodle site administration, the Switchboard, privacy admin panel, role management, user bulk import |

### 20.3 Code review scope (white-box)

| Priority | Area | Paths |
|---|---|---|
| 1 | Payment, path A | `local/sentientia_cart/callback.php`, `checkout.php`, `return.php`, `classes/gateway/`, `classes/callback_logger.php`, `classes/cart_manager.php` (`checkout()`, `mark_paid()`, `mark_failed()`) |
| 1 | Payment, path B | `moodle-enhancement/payment/gateway/airpay/` (`pay.php`, `process.php`, `classes/airpay_helper.php`, `classes/external/get_form.php`) |
| 1 | Tenant isolation helpers and their callers | `local/sentientia_platform/classes/tenant.php`, `local/sentientia_core/classes/tenant_registry.php`, `local/sentientia_courses/classes/sharing_manager.php`, `local/sentientia_catalog/classes/`; the 74 call sites of `path_descendant_filter()`, checked for the `null` scope contract (S-28) |
| 1 | Machine-interface authentication | `local/sentientia_api/` (SCIM, LTI, webhooks, rate limiter), `local/sentientia_xapi/lrs/` and `classes/lrs/authenticator.php`, `local/sentientia_integrations/webhook.php`, `local/sentientia_calendar/ics.php` and `classes/token_manager.php` |
| 1 | AI assistant chat and its direct provider path | `local/sentientia_assistant/classes/ai_client.php`, `classes/external.php`, `db/services.php` (S-22, S-23) |
| 1 | Proctoring web services (reachable by every authenticated user at the UAT commit) | `local/sentientia_proctoring/classes/external/`, `classes/session_manager.php`, `db/access.php`, retention task (S-27) |
| 2 | Role and user management | `local/sentientia_roles/`, `local/sentientia_users/` (sign-up, bulk import; external trainers once deployed to UAT) |
| 2 | Cryptography | `local/sentientia_pwa/` (Web Push, VAPID), `local/sentientia_calendar/classes/oauth/`, `local/sentientia_m365/` |
| 2 | Privacy and erasure | `local/sentientia_privacy/` |
| 2 | AI gateway and agentic assistant | `local/sentientia_ai/`, `local/sentientia_assistant/` |
| 3 | Theme renderers and templates with raw output | `theme/sentientia/classes/output/`, `theme/sentientia/templates/`, plugin templates using `{{{ }}}` |
| 3 | Dynamic SQL builders (reports, audience filters, analytics) | `local/sentientia_reports/`, `local/sentientia_analytics/`, audience preview functions |
| 3 | Vendored patches and old vendored plugins | `admin/tool/certificate/` hunks marked `SENTIENTIA-CORE-MOD`, `blocks/learnerscript/` |
| 3 | Core patch | `moodle-enhancement/core-patches/` (`lib/form/form.js`) |

Code access: a read-only export of the repository at the tested commit, delivered through a channel approved by Information Security. Moodle core itself is out of scope for code review, except the patched file.

### 20.4 Test accounts by role

Credentials are issued out of band by the Head of Learning & Development with IT, one account per row, and disabled after the test. They are never written in this document or in reports.

| # | Persona | Tenant | Purpose |
|---|---|---|---|
| 1 | Unauthenticated visitor | n/a | Public surfaces, sign-up, password reset, machine endpoints |
| 2 | Learner A | Airpay | Learner flows; horizontal access to learner B's data |
| 3 | Learner B | Airpay | Second principal of the same role and tenant, for horizontal (same-tenant) access tests in both directions |
| 4 | Learner | Public (self-registered by SISA during the test, if self-registration is enabled for the window) | Storefront, cart, payment |
| 5 | Second learner (optional) | Public | Horizontal tests on orders, cart and payment records between two Public buyers |
| 6 | Learner | ZEEA | Cross-tenant reach from the smallest tenant |
| 7 | Line manager with two direct reports | Airpay | Team scope, approvals |
| 8 | Internal trainer | Airpay | Classrooms, attendance, live sessions |
| 9 | External trainer (flag window), only if the external-trainer work (Built, verified on development) is deployed to UAT before the window, after its own PHPUnit run (R10) | Airpay | Minimal-privilege role |
| 10 | Course author | Airpay | Authoring and AI tools (mock mode) |
| 11 | Tenant administrator | Airpay | Isolation from Public and ZEEA; escalation attempts |
| 12 | Tenant administrator | Public | Isolation from Airpay and ZEEA |
| 13 | Tenant administrator | ZEEA | Isolation checks from the ZEEA side, towards Airpay and Public |
| 14 | Compliance officer | Airpay | Report scope |
| 15 | Platform L&D (cross-tenant) | all | Only if the role is assigned for the test; otherwise excluded |
| 16 | Site administrator (test account, not the break-glass account) | all | Configuration review; authorised-only scope |
| 17 | Machine clients (flag window) | per tenant | One SCIM client, one xAPI client, one LTI registration, one webhook subscription, one HRMS webhook secret |

### 20.5 Flag posture during the test

| Pass | Flags | Purpose |
|---|---|---|
| Pass 1 (default posture) | As configured on UAT at the start of the window (exported from the Switchboard and attached to the rules of engagement). Includes the proctoring web services, which no flag gates at the UAT commit (S-27) | Test what users would get at cutover |
| Pass 2 (feature window) | API, SCIM, LTI, xAPI, webhooks, HRMS webhook, PWA, live sessions, leaderboards, calendar sync, storefront checkout hand-off, agentic assistant (gateway in mock mode): ON for a named tenant for an agreed period, then restored and re-exported. The proctoring master flag and secure assessment mode only if the development changes are deployed to UAT first (R14). External trainers only if deployed to UAT first (account 9) | Test surfaces that ship dark |

Flag changes on UAT are made by the Head of Learning & Development (or a delegate) and logged in the flag audit table.

### 20.6 Out of scope

- Production (www.airpay.academy) and its infrastructure.
- Denial-of-service, load and volumetric testing; social engineering; physical testing.
- Attacks on third-party services (payment provider, AI provider, HRMS, Microsoft 365, push services, e-mail providers); only our integration code and our endpoints are in scope.
- Live AI calls. The AI gateway stays in mock mode. The assistant chat calls the provider directly whenever its own key is set (S-22); before the window the Head of Learning & Development confirms that no assistant key is set on UAT, or removes it (ask A7). Code review of that path stays in scope.
- Live WhatsApp and SMS sending (not built).
- The development workstations and the CI service, except a review of workflow files if SISA requests it.
- Destructive actions on shared test data beyond what the rules of engagement allow (for example bulk deletion of users or courses).

### 20.7 Deliverables requested from SISA

1. Findings in the register format of section 21, with CWE, OWASP Top 10 category, ASVS 4.0.3 requirement and CVSS 3.1 vector.
2. An executive summary for management and a technical report for engineering.
3. A retest letter after remediation of Critical and High findings.

---

## 21. Findings register template

### 21.1 Fields

| Field | Description |
|---|---|
| Finding ID | `SISA-2026-NNN` (SISA findings) or `S-NN` (pre-populated in section 19) |
| Title | One line |
| Component | Plugin, file or infrastructure element |
| URL or endpoint | Path on UAT (no full query strings containing personal data) |
| Environment and commit | UAT and the tested commit |
| Role and preconditions | Persona from section 20.4; flags required |
| Description | What is wrong |
| Steps to reproduce | Numbered steps |
| Evidence | Request and response excerpts with tokens, cookies and personal data redacted |
| CWE | CWE identifier |
| OWASP Top 10 (2021) | Category |
| ASVS 4.0.3 requirement | Requirement id |
| CVSS 3.1 | Base score and vector |
| Severity | Critical, High, Medium, Low, Informational |
| Business impact | Tenancy, payments, personal data, availability |
| Recommendation | Fix and any interim mitigation |
| Owner (role) | Engineering, DevOps lead, IT, DPO, Head of Learning & Development |
| Target date | From the time limits below |
| Status | Open, In remediation, Fixed (awaiting retest), Closed, Risk accepted (with approver role and expiry) |
| Retest result | Date, result, evidence |
| Linked records | ADR, core-mods record, commit, test name |

### 21.2 Proposed remediation time limits

These follow the proposal in `docs/security/VAPT-PROGRAM.md` and need agreement by the Head of Information Security.

| Severity | Fix by | Notes |
|---|---|---|
| Critical | 7 calendar days, and before cutover in any case | Feature may be switched off by flag as an interim mitigation |
| High | 30 calendar days, and before cutover | |
| Medium | 60 calendar days | |
| Low | 90 calendar days | |
| Informational | Best effort | |

### 21.3 Register table (blank row)

| Finding ID | Title | Component | Endpoint | Role | CWE | OWASP | ASVS | CVSS 3.1 | Severity | Owner | Target date | Status | Retest |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| SISA-2026-001 | | | | | | | | | | | | Open | |

---

## 22. Asks and next steps

These are the asks of section 1.4, with the form each answer should take.

| # | Ask | From | By | Form |
|---|---|---|---|---|
| A1 | Review this document; accept or re-rate the threat ratings (section 14) and the open findings (section 19) | Head of Information Security | 11 December 2026 | Marked-up copy or comments |
| A2 | Agree the SISA scope in section 20 (in-scope URLs, components, flag passes, exclusions, windows) | Head of Information Security with SISA | 4 December 2026 | Signed rules of engagement |
| A3 | Confirm the test dataset: SISA tests against the synthetic UAT dataset (test personas and seeded demo data from the fresh install), and no production-derived data is loaded onto UAT before or during the test windows (the Stage B rehearsal stays on its own target) | Data Protection Officer with Head of Information Security | 4 December 2026 | Written confirmation |
| A4 | State which Airpay payment path is active on UAT and which survives cutover (`local_sentientia_cart` or `paygw_airpay`), and decide whether S-01 and S-18 are fixed before the test or tested as they are | Head of Learning & Development with the Airpay payments team | 4 December 2026 | Decision recorded in the findings register; S-01 and S-18 re-rated |
| A5 | Provide infrastructure facts: WAF, region, encryption at rest, backup policy, log retention, database account pattern and credential delivery for the production target, renewal of the UAT certificate (expires 12 December 2026), `ServerTokens`, VAPID master key, whether an S3 bucket and credentials are configured for proctoring on UAT, Moodle security settings (lockout, password policy, session timeout, cron CLI only, web-service protocols and token creation, mobile service) | DevOps lead | 11 December 2026 | Completed checklist |
| A6 | Agree the findings register format and the remediation time limits (section 21) | Head of Information Security with SISA | Before test start | Signed rules of engagement |
| A7 | Export the UAT Switchboard (all flags and overrides), and confirm whether the AI assistant's own provider key is set on UAT (remove it for the test window if it is) | Head of Learning & Development | Before test start | File attached to the rules of engagement; written confirmation of the key state |
| A8 | Issue test accounts and machine clients (section 20.4) | Head of Learning & Development with IT | Before test start | Out-of-band credential delivery |
| A9 | Snapshot the UAT database before and after each test window | DevOps lead | Each window | Snapshot ids recorded in the test log |

---

## 23. Risks and open questions

| # | Item | Owner (role) | Proposed resolution |
|---|---|---|---|
| R1 | UAT flag state is not in the repository; testers may miss surfaces or test the wrong posture | Head of Learning & Development | Switchboard export before and after each window |
| R2 | Two Airpay payment integrations with different response contracts (keyless CRC32 in `paygw_airpay`; a custom secret-suffixed SHA-256 `checksum` field in the cart, which does not match the CRC32 `ap_SecureHash` that Airpay documents, so genuine cart callbacks would probably fail), neither validated in the provider sandbox; the active path on UAT is unknown, and path B depends on legacy code that is not in the repository | Head of Learning & Development with the Airpay payments team | Decide the surviving path (A4); validate it end to end in the provider sandbox, with server-side payment confirmation; retire the other path; keep the storefront checkout hand-off flag OFF until done |
| R3 | No staging environment; UAT is small (2 vCPU, 2 GB) and holds synthetic data only, so production-scale and production-data behaviour can only be tested elsewhere; development workstations hold the test import of a production backup (S-17) | DevOps lead with DPO | Provision a production-class environment for the Stage B rehearsal and later tests, kept separate from UAT; keep production-derived data off UAT; masked data for development |
| R4 | Infrastructure controls (WAF, encryption at rest, region, backups, IAM) are not evidenced in the repository | DevOps lead | Provide evidence or add infrastructure as code |
| R5 | `complianceofficer` role on UAT is not created by repository code | Platform engineer | Document its capabilities or add a seeder with an explicit allow-list |
| R6 | 39 capabilities are usable only by site administrators; operational roles (privacy officer, platform L&D) not yet decided | Head of Learning & Development | Decide role design per customer before cutover |
| R7 | Privacy findings overlap security findings (S-08, S-09, S-10, S-18 callback logging, S-22, S-23) | DPO | Track once, in the DPDP pack (`01-dpdp-security-review/`), with a cross-reference here |
| R8 | CI tests PHPUnit on Moodle 5.2, not 5.3; full suite advisory | Engineering | Add a 5.3 job and make the full suite blocking once green |
| R9 | The deploy script writes no record of what is deployed on UAT | DevOps lead | Write a deployed-commit marker and a hash list on every deploy |
| R10 | The 20 November development changes have no finished PHPUnit run | Engineering | Complete the run before deploying them to UAT |
| R11 | Pre-commit hook is per clone and can be bypassed | Engineering | Treat CI as the binding gate; require pull requests to `production` |
| R12 | Fact-base entries that disagree with the code or the record, which other packs may inherit: (a) `_facts/50-ai.md` section 2, consumer-routing row (line 43), says "Live calls refuse unless routing is ON", which is not true for the assistant chat (S-22). (b) `_facts/40-data-privacy.md` section 9.1, "UAT database credentials" row (line 741), still describes the superuser account as open; it was remediated on 3 September 2026 (S-05). (c) `_facts/70-ui-surfaces.md` row C7 and `_facts/60-eng-qa-infra.md` (UAT environment row) say UAT holds imported data; UAT is a fresh install with synthetic personas and seeded demo data. (d) The raw-HTTP list: `_facts/30-apis.md` section 8 had 18 files in 10 components and left out `payment/gateway/airpay/error.php`; corrected on 21 November 2026 to 19, the figure this document uses (section 8.1) | Head of Learning & Development (fact-base owner) | (d) closed on 21 November 2026; (a) is corrected in the fact-base index (section 8 of `00-FACTS-INDEX.md`); correct (b) and (c) in the domain files; then re-check every pack that quotes these entries |
| R13 | The assistant's own provider key state on UAT is unknown. If a key is set, each question sends the asking user's name, designation, organisation and course list to the provider outside the gateway. On UAT those are synthetic personas; after cutover they would be real personal data | Head of Learning & Development | Confirm the key state (A7); decide whether the standalone key setting is removed before cutover |
| R14 | Security fixes made on development after the UAT commit (for example the proctoring master flag `sentientia.proctoring.enabled` and the `upload_chunk` refusal guard, S-27) are not on UAT; a test on the UAT commit will report them as open | Engineering with DevOps lead | Deploy the reviewed fixes before the window, or list them in the rules of engagement as known and fixed on development |
| R15 | The UAT certificate expires on 12 December 2026, inside the period proposed for the SISA windows; automatic renewal of the Amazon-issued certificate is not confirmed (`docs/visual-evidence/2026-09-24/README.md`) | DevOps lead | Confirm automatic renewal, or renew, before the first window |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record in `moodle-enhancement/docs/adr/` |
| AES-256-GCM | Advanced Encryption Standard with a 256-bit key in Galois/Counter Mode (authenticated encryption) |
| AI | Artificial intelligence; here, large language model services reached through the AI gateway or the assistant chat |
| AJAX | Asynchronous browser call to the server; in Moodle, `lib/ajax/service.php` |
| ALB | Application Load Balancer (AWS) |
| AMD | Asynchronous Module Definition, the JavaScript module format Moodle uses |
| API | Application programming interface |
| ASVS | OWASP Application Security Verification Standard |
| AWS | Amazon Web Services |
| BizLMS | Legacy platform on Moodle 4.1.2 that runs www.airpay.academy today |
| Break-glass account | Emergency administrator account kept for recovery |
| Capability | Moodle permission string checked in code, granted to roles in contexts |
| CAPTCHA | Challenge that tells humans and automated clients apart |
| CDN | Content delivery network |
| CI | Continuous integration (GitHub Actions workflows) |
| CI/CD | Continuous integration and continuous delivery (deployment) |
| CIS | Center for Internet Security, publisher of configuration benchmarks |
| CLI | Command-line interface |
| CRC32 | A 32-bit cyclic redundancy check: an error-detecting checksum with no secret, not a cryptographic control |
| CSP | Content Security Policy, an HTTP header that limits where scripts and other resources load from |
| CSRF | Cross-site request forgery |
| CSV | Comma-separated values file |
| Cutover | The staged replacement of the legacy BizLMS site with Sentientia, carrying all data |
| CVSS | Common Vulnerability Scoring System |
| CWE | Common Weakness Enumeration |
| CWE-78 expression injection | Untrusted workflow input placed directly in a shell command of a CI workflow |
| DAST / SAST | Dynamic / static application security testing |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| DPO | Data Protection Officer |
| ECDHE | Elliptic-curve Diffie-Hellman key exchange with ephemeral keys (gives forward secrecy in TLS) |
| ES256 | Elliptic-curve digital signature with the P-256 curve and SHA-256 |
| Feature flag | Registered on/off switch with a default, overridable globally, per customer or per tenant |
| HMAC | Hash-based message authentication code |
| HR | Human resources |
| HRMS | Human resources management system (KeKa at Airpay) |
| HSTS | HTTP Strict Transport Security |
| HTML / HTTP / HTTPS | HyperText Markup Language / HyperText Transfer Protocol / HTTP over TLS |
| IAM | Identity and access management (cloud account permissions) |
| ICS | iCalendar file format used by calendar feeds |
| IP | Internet Protocol; an IP address identifies a client or server on the network |
| ISO/IEC | International Organization for Standardization / International Electrotechnical Commission |
| IT | Information technology (the corporate IT team) |
| JSON | JavaScript Object Notation |
| JWT | JSON Web Token |
| L2 | Level 2 of OWASP ASVS (the level this document maps against) |
| LDAP | Lightweight Directory Access Protocol |
| LMS | Learning management system |
| LTI | Learning Tools Interoperability (1.3) |
| LTS | Long-term support release |
| M365 | Microsoft 365 |
| MD5 | Message Digest 5, an obsolete hash function |
| MFA | Multi-factor authentication |
| NDA | Non-disclosure agreement |
| `null_provider` | Privacy provider that declares a plugin holds no personal data |
| OIDC | OpenID Connect |
| `open_path` | User column holding the organisation path; first segment is the tenant |
| OTP | One-time password |
| OWASP | Open Worldwide Application Security Project |
| Path A / Path B | The two Airpay payment integrations in the code: A is the native order cart (`local_sentientia_cart`); B is the Moodle payment-subsystem gateway (`paygw_airpay`) |
| PEM | Privacy-Enhanced Mail: a text encoding for keys and certificates |
| PHP | The server-side programming language Moodle is written in |
| PHP-FPM | PHP FastCGI Process Manager: the pool of PHP worker processes behind Apache |
| PKCE | Proof Key for Code Exchange (OAuth 2 extension) |
| PWA | Progressive Web App |
| Quality gate | An automated check in the pre-commit hook or CI that blocks a known defect class (ADR-027) |
| RAM | Random-access memory |
| REST | Representational State Transfer, a style of HTTP API |
| RFC | Request for Comments, an Internet standard document |
| RPO / RTO | Recovery point objective / recovery time objective |
| S-NN / T-NN / TB-N / R-NN | Identifiers in this document: open finding, STRIDE threat, trust boundary, risk or open question |
| S3 | Amazon Simple Storage Service (object storage) |
| SAML | Security Assertion Markup Language, a single sign-on standard |
| SCIM | System for Cross-domain Identity Management |
| SCORM | Sharable Content Object Reference Model, an e-learning packaging standard |
| SCSS | Sassy CSS, the stylesheet language the theme is written in (CSS: Cascading Style Sheets) |
| `sesskey` | Moodle per-session anti-CSRF token |
| SHA-256 | Secure Hash Algorithm with a 256-bit output |
| SISA | External security assessor engaged by Airpay Payment Services |
| SMS | Short Message Service (text messages) |
| SMTP | Simple Mail Transfer Protocol |
| SQL | Structured Query Language |
| SSE | Server-Sent Events (one-way stream from server to browser) |
| SSH | Secure Shell, encrypted remote login |
| SSO | Single sign-on |
| Stage B | Rehearsal of the cutover: restore a fresh backup of the live legacy site, upgrade it from Moodle 4.1.2 through 4.5 to 5.3, and prove 100% data parity |
| STRIDE | Threat categories: spoofing, tampering, repudiation, information disclosure, denial of service, elevation of privilege |
| Switchboard | Site-administrator page that shows and sets feature flags |
| TBC | To be confirmed (used in the ASVS table) |
| Tenant | Top-level organisation tree inside a customer (Airpay 1, Public 77, ZEEA 177) |
| TLS | Transport Layer Security |
| TOTP | Time-based one-time password |
| UAT | User acceptance test environment, academy2.airpay.ninja |
| UI | User interface |
| URL / URI | Uniform resource locator / identifier |
| USD | United States dollar |
| VAPID | Voluntary Application Server Identification for Web Push |
| VAPT | Vulnerability assessment and penetration testing |
| VPN | Virtual private network |
| WAF | Web application firewall |
| xAPI | Experience API (learning statements) |

## Appendix B. References

### B.1 Repository

All paths are relative to the repository root unless they start with `local/`, `docs/` or `payment/`, which are under `moodle-enhancement/`.

| Topic | Path |
|---|---|
| Fact base | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/` |
| Tenant helpers | `local/sentientia_platform/classes/tenant.php` |
| Flag resolver and Switchboard | `local/sentientia_platform/classes/feature_flags.php`; `local/sentientia_platform/admin/switchboard.php` |
| Tenant registry | `local/sentientia_core/classes/tenant_registry.php` |
| Path-boundary gate | `tools/check-path-boundary.php` |
| Site-id gate | `tools/check-siteid-compare.php` |
| Drift gate | `tools/check-tree-drift.php` |
| Core patch tool and manifest | `tools/check-core-patches.sh`; `moodle-enhancement/core-patches/MANIFEST` |
| Pre-commit hook | `.claude/hooks/pre-commit.sh` |
| CI workflows | `.github/workflows/ci.yml`, `core-mods.yml`, `deploy-production.yml` |
| Security headers and deny rules | `moodle-enhancement/deploy/moodle-htaccess.template` |
| Payment, path A (order cart) | `local/sentientia_cart/checkout.php`, `callback.php`, `return.php`, `classes/gateway/airpay_gateway.php`, `classes/gateway/gateway_factory.php`, `classes/callback_logger.php`, `classes/cart_manager.php`; storefront hand-off `local/sentientia_catalog/classes/checkout_bridge.php` |
| Payment, path B (gateway) | `payment/gateway/airpay/process.php`, `pay.php`, `classes/airpay_helper.php`, `classes/checksum.php`, `classes/external/get_form.php`, `amd/src/repository.js` |
| Public API, SCIM, LTI, webhooks | `local/sentientia_api/` (`scim/v2.php`, `lti/`, `classes/scim/client.php`, `classes/webhooks/signer.php`, `docs/openapi-v1.yaml`) |
| xAPI endpoint | `local/sentientia_xapi/lrs/statements.php` |
| HRMS webhook | `local/sentientia_integrations/webhook.php` |
| Live stream | `local/sentientia_live/stream.php` |
| AI gateway | `local/sentientia_ai/classes/gateway.php`, `settings.php` |
| Assistant web services and provider client | `local/sentientia_assistant/classes/external.php`, `classes/ai_client.php` |
| PWA key storage | `local/sentientia_pwa/classes/vapid_key_manager.php` |
| xAPI credentials | `local/sentientia_xapi/classes/lrs/authenticator.php` |
| Calendar feed | `local/sentientia_calendar/ics.php`, `classes/token_manager.php` |
| Proctoring chunk registration | `local/sentientia_proctoring/classes/external/upload_chunk.php` |
| Privacy | `local/sentientia_privacy/`; `local/sentientia_platform/tests/privacy_coverage_test.php` |
| Uploads | `local/sentientia_users/photo.php`, `bulk_import.php`, `bulk_csv.php` |
| Log scan | `moodle-enhancement/tools/ops/sentientia-logscan.sh` |
| Deploy tooling | `tools/uat/deploy_to_uat.sh`, `tools/uat/flag_snapshot.php` |
| Packaging | `tools/packaging/build-standalone.sh` |
| Rehearsal kit | `tools/rehearsal/` |
| Security documents | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md`, `2026-06-02-airpay-payment-verification-fix.md`, `ENTERPRISE-IDENTITY-PACK.md`, `VAPT-PROGRAM.md`, `ISO-27001-2022-READINESS.md` |
| Audits | `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`, `docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md`, `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` (superseded by ADR-040; its endpoint counts are stale and must not be quoted) |
| Erasure rehearsal | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| MFA runbook, SSO guide | `moodle-enhancement/MFA-ENFORCEMENT-RUNBOOK.md`, `moodle-enhancement/SSO-SETUP-GUIDE.md` |
| Core-modifications ledger | `docs/core-mods/` |

### B.2 Architecture Decision Records cited

| ADR | Title | Status |
|---|---|---|
| ADR-002 | Customer-level feature flags | Accepted |
| ADR-003 | Hand-rolled Web Push crypto | Accepted, pending security review |
| ADR-009 | Detection consistency and web-service contract invariants | Accepted |
| ADR-021 | Sentientia tenant registry | Accepted (rollout in progress) |
| ADR-027 | Quality gate system | Accepted |
| ADR-029 | KeKa joiner-mover-leaver hardening | Accepted |
| ADR-030 | SCIM and outbound webhooks | Implemented (flags off) |
| ADR-031 | Cross-tenant authority | Accepted |
| ADR-033 | Target Moodle 5.3 LTS | Accepted subject to a compatibility gate; Moodle 5.2 remains the fallback target until the gate passes |
| ADR-035 | Privacy erasure completeness | Accepted |
| ADR-038 | Assessment integrity levels | Proposed |
| ADR-040 | Native mobile app | Proposed |
| ADR-041 | Patching vendored plugins | Accepted |
| ADR-044 | Modal form dependency guard (core patch) | Accepted |

### B.3 External sources (accessed 20 November 2026 unless stated)

| Source | URL |
|---|---|
| OWASP Application Security Verification Standard project (4.0.3 used here; 5.0.0 renumbers the chapters) | https://owasp.org/www-project-application-security-verification-standard/ |
| ASVS release notes, version 4.0.3 (resolves; checked 21 November 2026) | https://github.com/OWASP/ASVS/wiki/What-is-new-in-version-4.0.3 |
| OWASP Cheat Sheet Series, ASVS index. Note: this index now maps cheat sheets to ASVS 5.0.x, not to the 4.0.3 numbering used in section 15 (checked 21 November 2026) | https://cheatsheetseries.owasp.org/IndexASVS.html |
| OWASP Developer Guide, ASVS (resolves; checked 21 November 2026) | https://devguide.owasp.org/en/06-verification/01-guides/03-asvs/ |
| OWASP Top 10 (2021) | https://owasp.org/Top10/ |
| CWE, MITRE | https://cwe.mitre.org/ |
| Moodle developer security guidance (unauthenticated access) | https://moodledev.io/general/development/policies/security/unauthenticated-access |
| Moodle developer documentation, Security (older reference, still describes `sesskey`, `require_login`, output functions) | https://docs.moodle.org/dev/Security |
| Moodle developer documentation, sesskey | https://docs.moodle.org/dev/sesskey |
| Moodle security procedures (reporting) | https://moodledev.io/general/development/process/security |
| STRIDE threat model (Microsoft Threat Modeling Tool documentation) | https://learn.microsoft.com/azure/security/develop/threat-modeling-tool-threats |
| CVSS v3.1 specification (FIRST) | https://www.first.org/cvss/v3.1/specification-document |

## Appendix C. Web-service functions by component

Source: `_facts/30-apis.md` section 4; proctoring note from the code at the UAT commit. All functions require login. "Flag" means the plugin's master flag defaults OFF.

| Component | Functions | Read | Write | Notes |
|---|---|---|---|---|
| `paygw_airpay` | 1 | 1 | 0 | No declared capability; cannot run (S-20) |
| `local_sentientia_api` | 7 | 6 | 1 | Flag; write needs a second flag |
| `local_sentientia_assistant` | 4 | 1 | 3 | 2 without declared capability; agentic functions behind flags |
| `local_sentientia_cart` | 9 | 4 | 5 | Refund and price functions admin-only |
| `local_sentientia_challenge` | 8 | 3 | 5 | |
| `local_sentientia_classroom` | 15 | 6 | 9 | Bulk enrol by audience |
| `local_sentientia_courses` | 15 | 3 | 12 | Cross-tenant sharing functions site-admin-only by default |
| `local_sentientia_emails` | 5 | 2 | 3 | |
| `local_sentientia_evaluation` | 8 | 2 | 6 | Bulk assign by audience |
| `local_sentientia_exams` | 3 | 1 | 2 | |
| `local_sentientia_leaderboard` | 3 | 2 | 1 | 1 without declared capability (opt-out) |
| `local_sentientia_learningpath` | 12 | 4 | 8 | |
| `local_sentientia_manager` | 8 | 3 | 5 | |
| `local_sentientia_notifications` | 6 | 2 | 4 | 1 without declared capability (own preferences) |
| `local_sentientia_org` | 3 | 1 | 2 | |
| `local_sentientia_proctoring` | 14 | 5 | 9 | No flag at the UAT commit: the 12 functions there are gated only by capabilities, `start_session` and `upload_chunk` by one that every authenticated user holds (S-27). The master flag `sentientia.proctoring.enabled` and 2 report functions were added on development after the UAT commit |
| `local_sentientia_programs` | 12 | 5 | 7 | |
| `local_sentientia_pwa` | 3 | 1 | 2 | Flag |
| `local_sentientia_ratings` | 1 | 0 | 1 | |
| `local_sentientia_reports` | 3 | 1 | 2 | |
| `local_sentientia_request` | 6 | 3 | 3 | |
| `local_sentientia_roles` | 8 | 4 | 4 | Assign and manage limited to cross-tenant callers |
| `local_sentientia_skills` | 14 | 5 | 9 | |
| `local_sentientia_users` | 6 | 3 | 3 | Suspend, delete (soft), bulk action |
| **Total** | **174** | **68** | **106** | |

## Appendix D. Remediated findings from the 3 September 2026 internal review

| ID | Finding | Fix | Date | Status |
|---|---|---|---|---|
| C2 | Public-tenant learner could self-enrol into another tenant's course by id | Catalogue visibility gate on course page, add-to-cart and enrol-now; regression tests | 3 September 2026 | Available (UAT) |
| H1 | Sign-up revealed whether an e-mail address was registered | Uniform response; existing account owner notified | 3 September 2026 | Available (UAT) |
| H3 | xAPI endpoint had no rate limit | Per-client fixed window, HTTP 429 | 3 September 2026 | Built (behind a feature flag, off by default) |
| H4 | Event-stream connections could exhaust PHP workers | Global and per-actor caps, shorter lifetime, 503 when full | 4 September 2026 | Built (behind a feature flag, off by default) |
| M1 | HRMS employee id placed unencoded in an outbound URL path | URL-encoding | 4 September 2026 | Built (behind a feature flag, off by default) |
| M2 | Landing page fallback to site-wide counts | Clarified: fallback applies only on a single-tenant schema | 3 September 2026 | Available (UAT) |
| M3 | Moodle version files served publicly | Deny rules in the web-server template; UAT returns 404 | 4 September 2026 | Available (UAT) |
| M4 | Security headers missing | `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS added; CSP deferred (S-03) | 4 September 2026 | Available (UAT) |
| C1 (part) | Payment guard computed but not enforced | Guard enforced; residual weakness tracked as S-01 | 2 June 2026 | Available (UAT) |
| Checklist (database credentials) | Application connected as the managed database's superuser; password once sent by e-mail | Application-scoped account with schema-only grants and no SUPER privilege; e-mailed password rotated, old one rejected (`docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row E5). Credential delivery through a secrets manager and the production target remain open (S-05, Low) | 3 September 2026 | Available (UAT) |
