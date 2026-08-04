# Enterprise Identity Pack — SSO + MFA + bot mitigation (ADR-028 Phase 1.2)

**Status:** Runbook + policy READY — production execution is a deployment action
(Nitin-gated, tied to the trust sprint). Nothing here is enabled on live yet.
**Date:** 2026-08-04 | **Owner:** Nitin Rajput | **Implements:** ADR-028 Phase 1.2
(signed memo Q3=b funds the surrounding trust track; Q6=a+b targets BFSI buyers
whose security questionnaires open with exactly these three items).

**Why this exists:** the 2026-08-04 maturity audit rated identity the weakest
enterprise pillar — login is username/password + the vendor-inherited OTP form;
core Moodle ships everything needed (`auth/oauth2`, `admin/tool/mfa`,
reCAPTCHA-aware signup) but nothing at the product layer configures, documents,
or hardens it. This pack is deliberately **configuration + policy + evidence — no
new auth code.**

---

## 1. SSO — Microsoft Entra ID via core `auth/oauth2`

The login template already renders identity-provider buttons when an issuer is
configured (`theme/sentientia/templates/core/loginform.mustache`
`{{#hasidentityproviders}}` block, incl. the branded "or sign in with" divider,
P0-borrow #5). Azure app-registration slots already exist in `.env`
(`AZURE_CLIENT_ID` / `AZURE_CLIENT_SECRET` / `AZURE_TENANT_ID` — CLAUDE.md §11).

### Configuration runbook (per environment)

1. **Azure side** (IT/DevOps): App registration → Web platform → redirect URI
   `https://<site>/admin/oauth2callback.php`; add optional claim `email`;
   grant `openid profile email User.Read`; note client id/secret + tenant id.
2. **Moodle side:** Site administration → Server → OAuth 2 services →
   *Create new Microsoft service* → paste client id/secret; base URL
   `https://login.microsoftonline.com/<AZURE_TENANT_ID>/v2.0`; enable
   *Show on login page*; connect a system account only if Graph features are
   needed later (not required for SSO).
3. Plugins → Authentication → enable **OAuth 2**; keep Manual auth enabled for
   break-glass admin accounts.
4. Field mapping: email = primary match. Existing HRMS-provisioned accounts
   link automatically when the Entra UPN/email equals the Moodle email (the
   HRMS CSV importer already keys on corporate email). Set
   `auth_oauth2 | allowaccountsmatchedbyemail = 1` for the linking window; review
   after cutover.
5. **Provisioning stays HRMS-owned** (joiner/mover/leaver via
   `local_sentientia_lifecycle` + KeKa webhook). SSO is authentication only —
   do NOT enable OAuth2 self-registration (`preventaccountcreation = 1` per tenant
   policy) so no side-door around HRMS onboarding exists. SCIM lands in
   ADR-028 Phase 2.4.

### Verification checklist

- [ ] Entra test user signs in via the login-page button; account links by email.
- [ ] Suspended-in-HRMS user CANNOT sign in via SSO (suspension honoured).
- [ ] Manual (break-glass) admin login still works with MFA (below).
- [ ] Logout → IdP session honoured per `auth_oauth2` logout setting choice.

## 2. MFA — core `admin/tool/mfa`

### Policy (the 1-page answer for the security questionnaire)

| Population | Requirement | Factors |
|---|---|---|
| Site administrators + Moodle `manager`/`administrator` role holders | **MFA mandatory** | TOTP (primary), WebAuthn/passkey (preferred where hardware allows) |
| L&D admins / trainers (elevated caps, non-admin) | MFA mandatory within 30 days of rollout (grace factor) | TOTP |
| Learners (SSO population) | MFA delegated to **Entra ID Conditional Access** (the IdP enforces org MFA) — Moodle does not double-challenge SSO sessions | IdP-side |
| Break-glass accounts | MFA mandatory, TOTP enrolled at creation, credentials in the ops vault | TOTP |

Recovery: TOTP re-enrolment via a second admin (never self-service email reset
for admin accounts). Lockout: 3 failed factor attempts → standard Moodle account
lockout policy applies.

### Configuration sequence (run in this order — grace factor FIRST prevents lockout)

```bash
php admin/cli/cfg.php --component=tool_mfa --name=enabled --set=1
php admin/cli/cfg.php --component=factor_grace --name=enabled --set=1
php admin/cli/cfg.php --component=factor_grace --name=graceperiod --set=2592000   # 30 days
php admin/cli/cfg.php --component=factor_totp --name=enabled --set=1
php admin/cli/cfg.php --component=factor_totp --name=weight --set=100
# Optional, once admin hardware is confirmed:
# php admin/cli/cfg.php --component=factor_webauthn --name=enabled --set=1
php admin/cli/purge_caches.php
```

**Deliberately NOT enabled on local dev** — the QA persona harness
(`tools/gap-test/`, demo walkthroughs, automated browser verification) logs in
programmatically; MFA would break it after the grace window. Local stays
MFA-off; the ninja rehearsal is the first environment where this sequence runs
(add it to the cutover runbook's post-upgrade steps), production second.

### Verification checklist

- [ ] Admin account prompted to enrol TOTP on next login (grace banner shows).
- [ ] Enrolled admin: login requires code; wrong code ×3 → lockout path works.
- [ ] SSO learner: no Moodle MFA prompt (IdP handles it) — confirm no double-challenge.
- [ ] Grace expiry behaviour tested by shortening graceperiod on the ninja.

## 3. Bot mitigation — reCAPTCHA (CONFIG, not code)

The 2026-08-04 audit line "reCAPTCHA absent from the sentientia login template"
was **imprecise**: no theme ever carried a login CAPTCHA (core Moodle doesn't
have one), and the public signup form ALREADY implements defense-in-depth —
honeypot field (always on) + **reCAPTCHA v2, auto-shown when keys are
configured** (`local/sentientia_users/classes/form/signup_form.php` P1 #59,
verification mirroring `auth/email`). The actual gap is that
`$CFG->recaptchapublickey` / `$CFG->recaptchaprivatekey` are unset everywhere.

**Action (production/ninja):** Site administration → Security → Site security
settings → set the reCAPTCHA v2 key pair (Google console, domain-scoped).
The signup form picks it up with zero code change. Keys never in git — env/ops
vault only. Local dev stays keyless by design (the form degrades to
honeypot-only so offline dev keeps working).

## 4. Rollout order (ties into the cutover runbook)

1. Ninja rehearsal: run §2 config sequence + §3 keys + §1 with a test Entra app → full verification checklists.
2. Production (Nitin-gated, with the 5.2 cutover or immediately after): same sequence; comms to admin population before the grace window starts.
3. Evidence for the procurement pack: screenshot the enrolment flow + export the MFA policy table above into the security-questionnaire answers (trust-track pack).

## 5. Explicitly out of scope here

SAML/other IdPs (Entra covers the beachhead; SAML on demand), SCIM (Phase 2.4),
session-policy hardening + IP allowlisting for admin URLs (trust-sprint follow-up),
`local_sentientia_m365` Graph features (Workstream C — unrelated to SSO).
