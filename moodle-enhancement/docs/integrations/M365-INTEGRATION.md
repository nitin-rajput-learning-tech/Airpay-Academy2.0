# Microsoft 365 Integration — Sentientia LMS

**Workstream C — Knowledge Automation, Phase C.1 (scaffold).**
Last updated: 2026-05-24 · Plugin: `local_sentientia_m365` · Status: ALPHA

Pull SharePoint documents, Teams meeting context, and Outlook calendar
entries into Sentientia LMS as first-class learning material. Phase C.1
ships the OAuth + Graph scaffolding; Phase C.2+ wires the live calls.

---

## 1. Where this fits

Workstream C in CLAUDE.md is the Microsoft 365 bridge. The phased plan:

| Phase | Deliverable | Status |
|-------|-------------|--------|
| **C.1** | OAuth scaffold + Graph stubs + privacy + Hindi pack | **Done (2026-05-24)** |
| C.2  | Live Graph calls behind a per-customer feature flag + `[CONFIRM]` UI | Planned |
| C.3  | SharePoint folder → SENTIENTIA SOP parser ingest | Planned |
| C.4  | Outlook meeting → LMS classroom event sync | Planned |
| C.5  | Teams attendance → completion record | Planned |
| C.6  | Per-customer prompt + scope overrides + Hindi consent UI | Planned |

The OAuth flow Phase C.1 wires is reusable: every later phase reads its
access token from the same `local_sentientia_m365_tokens` row, decrypts
through the same `msal_client::decrypt_token()` helper, and re-uses the
same admin settings + privacy provider.

---

## 2. Auth flow (Authorization Code grant + PKCE)

Phase C.1 implements the **public-client** variant of OAuth2
Authorization Code with **PKCE (RFC 7636)**. No client secret is
stored. PKCE defeats the auth-code interception attack that would
otherwise leak the Microsoft access token to anyone who can read the
intermediate redirect URL.

```
┌──────────┐                ┌──────────────────────────┐               ┌──────────────────┐
│ Learner  │                │ Sentientia LMS           │               │ Microsoft Entra  │
│ Browser  │                │ /local/sentientia_m365/  │               │ login.microsoft. │
└────┬─────┘                └─────┬────────────────────┘               └─────┬────────────┘
     │                            │                                          │
     │   GET /connect.php         │                                          │
     ├───────────────────────────►│                                          │
     │                            │  generate_pkce_pair()                    │
     │                            │  session['state']    = csrf_random()     │
     │                            │  session['verifier'] = pkce.verifier     │
     │                            │  build_authorize_url(state, challenge)   │
     │  302 → authorize_url       │                                          │
     │◄───────────────────────────┤                                          │
     │                                                                       │
     │   GET /authorize?client_id=...&response_type=code&code_challenge=...  │
     ├──────────────────────────────────────────────────────────────────────►│
     │                                                                       │
     │   User signs in + consents to scopes                                  │
     │   302 → redirect_uri?code=AUTH_CODE&state=...                         │
     │◄──────────────────────────────────────────────────────────────────────┤
     │                            │                                          │
     │   GET /callback.php?code=  │                                          │
     ├───────────────────────────►│                                          │
     │                            │  Verify state == session['state']        │
     │                            │  exchange_code(code, verifier, userid)   │
     │                            │  POST /oauth2/v2.0/token (Phase C.2)     │
     │                            ├─────────────────────────────────────────►│
     │                            │  ← {access_token, refresh_token, ...}    │
     │                            │◄─────────────────────────────────────────┤
     │                            │  encryption::encrypt(access)             │
     │                            │  encryption::encrypt(refresh)            │
     │                            │  store_tokens()                          │
     │  302 → /local/sentientia_  │                                          │
     │        m365/index.php      │                                          │
     │◄───────────────────────────┤                                          │
```

In Phase C.1, the `exchange_code()` call short-circuits at the first
guard (master flag OFF by default) or throws `confirm_required` (flag
ON, but no live-API flag exists yet). The `callback.php` page is
deferred to Phase C.2.

### State + PKCE storage

| Value | Where it lives | Lifetime |
|-------|----------------|----------|
| `state` (CSRF token) | `$SESSION` (server-side, not cookies) | Single round trip |
| PKCE `verifier` | `$SESSION` (NOT the DB — verifier never persists) | Single round trip |
| PKCE `challenge` | URL query string (visible) | Single round trip |
| `access_token` | DB column `access_token_enc`, Sodium-encrypted | Until `expires` |
| `refresh_token` | DB column `refresh_token_enc`, Sodium-encrypted | ≤ 90 days (Microsoft default) |

---

## 3. Scopes

Sentientia LMS asks Microsoft for the **minimum** scope set needed for
each Phase C.X capability. Default scopes are always requested at
connect time; optional scopes are surfaced based on the admin's
multiselect in `Site administration → Plugins → Local plugins →
Sentientia LMS — Microsoft 365 → Allowed OAuth scopes`.

### Default scopes (always granted)

| Scope | What it allows | Required by |
|-------|----------------|-------------|
| `openid` | Issue an ID token (proves identity) | OAuth handshake itself |
| `profile` | Read basic profile fields (display name, given name, etc.) | `get_me()` |
| `offline_access` | Refresh token issuance | All long-running features |
| `User.Read` | Read the signed-in user's profile | `get_me()`, account verification |

### Optional scopes (admin-selectable)

| Scope | What it allows | Phase |
|-------|----------------|-------|
| `Sites.Read.All` | Enumerate every SharePoint site the user can see | C.3 |
| `Files.Read.All` | Read every file the user can see | C.3 |
| `Calendars.Read` | Read the signed-in user's calendars | C.4 |
| `Calendars.ReadWrite` | Read + write the user's calendars | C.4 |
| `TeamMember.Read.All` | List the user's Teams memberships | C.5 |
| `Mail.Read` | Read the user's mailbox | C.6 (very rare) |

**Scope-policy rule:** Sentientia LMS never requests **application
permissions** (the ones that act as the org rather than the user).
Every Graph call runs **as the signed-in user** under **delegated
permissions** only. That keeps the blast radius of a leaked token
identical to the blast radius of a leaked Microsoft password — no
worse, and limited by the user's own RBAC inside Microsoft 365.

---

## 4. Token rotation

### Refresh contract

`msal_client::needs_refresh(\stdClass $row): bool` returns true when:
- `expires` is 0 (treat missing as expired — fail-safe), OR
- `expires` is in the past, OR
- `expires - time() ≤ REFRESH_WINDOW_SECONDS` (60 s safety margin).

Phase C.2 will add a `refresh()` method that calls
`/oauth2/v2.0/token?grant_type=refresh_token` and re-runs
`store_tokens()` with the new pair.

### Maximum refresh-token lifetime

Per Microsoft Entra defaults:
- **Single-page-app (SPA) configuration**: 24 hours, non-renewable
  (we are not using this).
- **Confidential client / public client with PKCE**: 90-day refresh
  token, rolling (every successful refresh extends the validity).
  This is what Sentientia LMS uses.

Sentientia LMS additionally enforces
`msal_client::REFRESH_TOKEN_MAX_AGE_DAYS = 60` — refresh tokens older
than 60 days from the row's `timecreated` are treated as expired even
if Microsoft would still honour them. Forces re-consent before the
90-day Microsoft expiry surprises a customer.

### Revocation

Phase C.1 — `msal_client::revoke()` deletes the local row only.

Phase C.2 will additionally POST to Microsoft's revocation endpoint so
the refresh token cannot be replayed after revocation. Until then,
admins should treat local-row delete as "the LMS forgets about you";
the refresh token will still work against Microsoft until it expires
naturally (≤ 90 days).

---

## 5. Encryption at rest

Every column ending in `_enc` is encrypted with `\core\encryption`
(Sodium `crypto_secretbox`) before it touches the database. The
ciphertext is method-tagged and base64-encoded so the column stays
plain ASCII even though it holds binary.

```php
// Encrypt:
$row->access_token_enc = \core\encryption::encrypt($plaintext);

// Decrypt:
$plaintext = \local_sentientia_m365\msal_client::decrypt_token($row->access_token_enc);
```

The Sodium key lives at `$CFG->dataroot/secret/encryption-key.txt`
with `0400` permissions (owner-read-only). A DB-only attacker
**cannot** recover the plaintext tokens without also stealing the file
from the application server. The msal_client wrapper translates any
Sodium failure into a generic `moodle_exception('error_token_decrypt')`
so we never leak the reason (integrity-check vs. wrong-method vs.
truncated ciphertext) to a caller.

**Operational rule:** rotate the Sodium key only as part of a planned
re-encryption job — flipping the file invalidates every existing
encrypted column simultaneously. Phase C.6 will ship a re-encrypt
cron task that the admin can run before key rotation.

---

## 6. GDPR / DPDP notes

The privacy provider (`\local_sentientia_m365\privacy\provider`)
declares:

1. **`local_sentientia_m365_tokens`** as a table of personal data.
   Even though the access + refresh columns hold ciphertext, the
   ciphertext is still PII because it can be decrypted with the server
   key to recover a credential that grants access to the user's
   Microsoft account.

2. **`microsoft_graph`** as an external location data subject to
   third-party processing. Every Phase C.2+ feature that reads M365
   data on the user's behalf flows the user's access token to
   `graph.microsoft.com`.

### Export contract

`provider::export_user_data()` exports each token row with:
- Encrypted columns **masked** as the literal string `[encrypted]`.
  We never put the ciphertext into the DSAR ZIP because the recipient
  could replay it against Microsoft until expiry.
- Metadata (scope list, customerid, timestamps) exported in full so
  the data subject sees **what they consented to**, **when**, and **for
  which customer**.

### Delete contract

`provider::delete_data_for_user()` removes the row entirely. No
soft-delete; retention of encrypted credentials after a right-to-erasure
request would violate Article 17 (GDPR) / §7 (India DPDP Act).

Phase C.2 will additionally POST to Microsoft's revocation endpoint
inside `delete_data_for_user()` so the refresh token cannot survive
the deletion and continue to grant Graph access until natural expiry.

### What we **do not** send to Microsoft

- The Moodle user's password (never enters this flow at all).
- Other learners' profile data.
- Course content (until C.3 explicitly opts in to upload).
- BizLMS tenant metadata.
- Anything related to other Sentientia LMS customers.

The only personal data that leaves the LMS during the OAuth handshake
is the **redirect URI** (advertises which LMS instance is making the
request) and the **access token** sent as a Bearer header on Graph
calls. Per Microsoft's documented privacy contract, Microsoft retains
access-token issuance logs but not the token bodies themselves.

---

## 7. Admin checklist (for Phase C.2 enablement)

When Phase C.2 is ready to flip the master flag ON:

1. Register an application in Azure Portal → Microsoft Entra ID →
   App registrations:
   - **Supported account types**: choose what the customer needs.
     Single-tenant is safest; multi-tenant only if the customer
     consciously wants any Microsoft work/school account to be able
     to connect.
   - **Redirect URI**: add `https://<your-lms>/local/sentientia_m365/callback.php`
     under "Single-page application" (NOT "Web" — that requires a
     client secret).
2. Copy the **Application (client) ID** and the **Directory (tenant) ID**
   into the Sentientia admin settings page.
3. Set the same redirect URI in the Sentientia admin settings page —
   it must match Azure exactly.
4. Tick the optional scopes the customer needs (Sites.Read.All for
   SharePoint, Calendars.Read for Outlook, etc.) under
   "Allowed OAuth scopes".
5. Flip the `sentientia_m365_enabled` feature flag ON via the
   Switchboard.
6. Grant `local/sentientia_m365:use` to the role(s) that should be
   allowed to connect (default false on every archetype — must be
   added to a custom role).

---

## 8. Configuration matrix

| Setting | Default | Required? | Notes |
|---------|---------|-----------|-------|
| `azure_tenant_id` | empty | Yes | Tenant GUID, or `common` for any Microsoft account |
| `azure_client_id` | empty | Yes | Application registration's client ID |
| `redirect_uri` | empty | Yes | Must match Azure registration byte-for-byte |
| `allowed_scopes` | empty (none) | No | Optional scopes available to the connect UI |
| Feature flag `sentientia_m365_enabled` | OFF | Yes (for C.2+) | Master switch; defaults OFF on every customer |
| Capability `local/sentientia_m365:use` | not assigned | Yes | Assign to a custom role; default false on archetypes |

---

## 9. Testing

```bash
# From Moodle repo root:
vendor/bin/phpunit --filter local_sentientia_m365
```

Phase C.1 ships three test classes (all under `tests/`):
- `msal_client_test.php` — encryption round-trip, PKCE shape,
  is_ready toggle, exchange_code feature-flag gating.
- `graph_client_test.php` — confirm_required guard fires on every
  public method, even with the master flag ON.
- `privacy_provider_test.php` — metadata declaration, ciphertext
  masking on export, per-user delete, bulk delete.

No test issues HTTP. No test reaches `login.microsoftonline.com` or
`graph.microsoft.com`. The live API plumbing is Phase C.2's problem.

---

## 10. References

- [Microsoft identity platform — OAuth 2.0 auth code grant](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow)
- [RFC 7636 — Proof Key for Code Exchange (PKCE)](https://datatracker.ietf.org/doc/html/rfc7636)
- [Microsoft Graph — Permissions reference](https://learn.microsoft.com/en-us/graph/permissions-reference)
- CLAUDE.md — Workstream C
- `.claude/rules/api.md` — Microsoft 365 / Azure section
- `lib/classes/encryption.php` — Moodle Sodium wrapper
