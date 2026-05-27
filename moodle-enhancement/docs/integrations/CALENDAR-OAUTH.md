# Calendar Sync — Phase 2 OAuth Integration

**Status:** Phase 2.1 — LIVE OAuth wired (Wave C4, 2026-05-27). The
Authorization Code + PKCE flow now exchanges codes for tokens, refreshes
on expiry, and revokes at the provider + locally. Still gated behind the
master feature flag (default **OFF**) — live traffic requires the flag ON
for the customer AND no test mock registered.
**Plugin:** `local_sentientia_calendar` v1.2.0-beta (version
`2026052700`).
**ADR:** [ADR-013 — Calendar sync](../adr/ADR-013-calendar-sync.md) §
"Why we keep Path B as a future option".
**Feature flag:** `sentientia.calendar_sync.oauth.enabled` — default
**OFF**.

> History: Phase 2 (P3-N, 2026-05-24, v1.1.0-beta) shipped the
> scaffolding — the four lifecycle methods threw `oauth_not_live`. Phase
> 2.1 (this chip) replaced those throws with the live token-endpoint
> POST. CI exercises every branch through a mock HTTP handler
> (`oauth_base::set_http_handler_for_testing()`); no live provider
> traffic ever leaves CI.

---

## Scope of this document

This document describes the OAuth 2.0 Authorization Code with PKCE flow
that `local_sentientia_calendar` uses for bi-directional sync against
Microsoft 365 (Microsoft Graph) and Google Calendar.

In Phase 2.1 (this chip) **the live exchange is wired**, gated by two
independent kill switches:

- The DB table `local_sentientia_calendar_oauth` round-trips encrypted
  tokens, and the live flow now writes real rows once a user connects.
- The `oauth_base` / `m365_oauth` / `google_oauth` classes build the
  authorize URL, recover the PKCE verifier on callback, POST to the
  token endpoint, refresh on expiry, and revoke.
- Three public endpoints — `oauth/connect.php`, `oauth/callback.php`,
  `oauth/disconnect.php` — drive the browser-facing flow. connect +
  disconnect are sesskey-protected; callback validates the OAuth `state`.
- The settings page collects client IDs + secrets. They activate the
  moment the master feature flag is flipped ON for a customer.

**Two kill switches, both must clear for live traffic:**

1. **Feature flag** `sentientia.calendar_sync.oauth.enabled` (default
   OFF). Every lifecycle method calls `assert_feature_flag_enabled()`
   before any HTTP could leave the server.
2. **Test mock** — `oauth_base::set_http_handler_for_testing()`. When a
   test registers a handler, all outbound HTTP routes through it. The
   handler stays `null` in production. CI populates it in setUp(), so no
   live provider call ever runs in CI.

---

## Why Phase 2 at all (recap of ADR-013)

Phase 1 surfaced learner deadlines via a token-authenticated ICS
subscription URL. That feed is outbound-only: deletions in the user's
calendar app reappear on the next fetch, and the LMS cannot react to
events the user creates (e.g. accepting / declining a classroom
session) from inside their calendar app.

ADR-013 lists the cases where Path B (OAuth bi-directional) becomes
necessary:

1. A customer demands two-way sync as a procurement requirement.
2. We add user-created events (1-on-1 mentoring in Phase 3+).
3. Provider-side notification quality outpaces our own.

Phase 2 lays the foundation so that flipping a feature flag at the right
moment is the entire rollout — not "spend 3-4 sessions per provider
designing the schema from scratch".

---

## Flow diagram — Authorization Code with PKCE

```
┌─────────┐        ┌───────────────────┐         ┌──────────────────────┐
│ Learner │        │ Sentientia LMS    │         │ Microsoft / Google   │
│ browser │        │ (this plugin)     │         │ identity provider    │
└────┬────┘        └─────────┬─────────┘         └──────────┬───────────┘
     │                       │                              │
     │  ① click              │                              │
     │  "Connect Outlook"    │                              │
     ├──────────────────────▶│                              │
     │                       │                              │
     │                       │  ② generate                  │
     │                       │     PKCE verifier (random)   │
     │                       │     PKCE challenge = S256(v) │
     │                       │     state token (CSRF)       │
     │                       │     store {state→verifier}   │
     │                       │     in $SESSION (TTL 10 min) │
     │                       │                              │
     │  ③ 302 to provider's  │                              │
     │  /authorize with      │                              │
     │  state+challenge      │                              │
     │◀──────────────────────┤                              │
     │                       │                              │
     │  ④ HTTP GET /authorize?client_id&redirect_uri&       │
     │     scope&state&code_challenge&code_challenge_method │
     ├──────────────────────────────────────────────────────▶
     │                       │                              │
     │                       │                              │  ⑤ user logs in
     │                       │                              │  + consents to scopes
     │                       │                              │
     │  ⑥ 302 to             │                              │
     │     redirect_uri?     │                              │
     │     code=…&state=…    │                              │
     │◀──────────────────────────────────────────────────────┤
     │                       │                              │
     │  ⑦ HTTP GET           │                              │
     │  /oauth/callback.php  │                              │
     │  ?provider&code&state │                              │
     ├──────────────────────▶│                              │
     │                       │                              │
     │                       │  ⑧ consume {state} from      │
     │                       │     $SESSION; recover        │
     │                       │     stored verifier; match   │
     │                       │     CSRF state via           │
     │                       │     hash_equals()            │
     │                       │                              │
     │                       │  ⑨ POST to provider's        │
     │                       │     /token with code +       │
     │                       │     code_verifier            │
     │                       ├──────────────────────────────▶
     │                       │                              │
     │                       │  ⑩ provider responds with    │
     │                       │     access_token (1h),       │
     │                       │     refresh_token (months),  │
     │                       │     expires_in, granted scope│
     │                       │◀─────────────────────────────┤
     │                       │                              │
     │                       │  ⑪ \core\encryption::        │
     │                       │     encrypt both tokens;     │
     │                       │     INSERT/UPDATE row in     │
     │                       │     local_sentientia_calendar│
     │                       │       _oauth                 │
     │                       │                              │
     │  ⑫ redirect back to   │                              │
     │     /index.php with   │                              │
     │     "Connected!" flash│                              │
     │◀──────────────────────┤                              │
```

This chip implements the full flow ① – ⑫. Steps ⑨ – ⑪ (the token POST
and encrypted store) run live when the feature flag is ON, and route
through the test mock handler under PHPUnit / Behat.

Step ⑫ is the regular Moodle-redirect-with-notification UX; no
provider involvement.

---

## Scopes requested

### Microsoft 365 (Microsoft Graph)

```
openid
profile
offline_access
https://graph.microsoft.com/Calendars.ReadWrite
```

- `openid` + `profile` — basic identity claims; needed so the provider
  knows which user it's authenticating.
- `offline_access` — REQUIRED to mint a refresh token. Without this the
  user's access expires in 1 h and they have to re-consent.
- `Calendars.ReadWrite` — narrowest scope that lets us both READ events
  (to detect deletes the user made in their calendar app) and WRITE
  events (to push new LMS deadlines / classroom sessions / exam closes).
  We do NOT request `Calendars.ReadWrite.Shared` (the wider variant
  that also touches calendars the user is delegated to). For Phase 2
  we touch only the user's own primary calendar.

### Google Calendar

```
https://www.googleapis.com/auth/calendar.events.owned
```

- `calendar.events.owned` — narrowest scope that lets us read+write
  events the app itself creates in the user's primary calendar. This
  scope keeps Google's consent screen text honest: "Sentientia LMS will
  see events it has added to your calendar" — true and constrained.
- We do NOT request `calendar` (the user's entire calendar) or
  `calendar.events` (every event, regardless of creator). Over-broad
  scopes are rejected by Google's review process for OAuth apps and
  alarm enterprise admins reviewing the app registration.

Plus two extra authorize-time parameters Google requires for
refresh tokens:

- `access_type=offline` — required to get a refresh_token in the
  callback (Google omits it otherwise).
- `prompt=consent` — forces the consent screen on every authorize.
  Without this, re-running the flow for a previously-consented user
  returns ONLY an access_token — no new refresh_token — leaving us
  with no way to re-mint when the access_token expires.

---

## Token storage

```
{local_sentientia_calendar_oauth}
─────────────────────────────────────────────────────────────────────
id                INT      PK, auto-inc
userid            INT      FK → mdl_user.id
customerid        INT      Sentientia LMS customer (1 = Airpay)
provider          CHAR(20) 'm365' | 'google'
access_token_enc  TEXT     \core\encryption::encrypt(access_token)
refresh_token_enc TEXT     \core\encryption::encrypt(refresh_token) — may be NULL
expires           INT      Unix ts when access_token expires
scopes            TEXT     Space-separated scope string granted at consent
timecreated       INT
timemodified      INT

UNIQUE (userid, provider)        — one row per provider per user
INDEX  (provider, expires)       — background refresh job lookups
INDEX  (customerid, provider)    — per-customer subscription stats
```

The encryption pipeline is Moodle's `\core\encryption::encrypt()` /
`::decrypt()` which uses Sodium's `secretbox` (XSalsa20-Poly1305) under
the hood. The encryption key lives at
`$CFG->dataroot/secret/key/sodium.key` (chmod 0400 by Moodle on install).

**Recovering a token requires BOTH the DB row AND the key file.** Moving
a DB dump to a different Moodle instance without copying the key file
leaves the tokens unrecoverable — a deliberate property for breach
containment.

The plaintext tokens are NEVER:
- Written to a log file (the Moodle DB query log redacts `_enc` columns
  by virtue of the row body not being logged at all in production
  configurations).
- Returned by a public API endpoint (`describe_for_user()` emits the
  provider + expiry + scopes but never the encrypted blob).
- Included in a privacy export archive (the export writer writes
  `[REDACTED — encrypted credential not exported]` in place of the
  encrypted bodies).

---

## Token rotation

### Access tokens

Access tokens are typically valid for **1 hour** (Microsoft Graph) or
**1 hour** (Google Calendar). Callers MUST compare `time() >= expires`
before using a stored access token and call `refresh_token()` first
when it's due.

A scheduled task (Phase 2.1) will batch-refresh tokens that expire in
the next 30 minutes to amortise the latency cost over background time
rather than a learner's interactive request.

### Refresh tokens

Refresh tokens are long-lived. Microsoft's are valid for **90 days of
inactivity** (sliding window); Google's are valid until the user
revokes consent at myaccount.google.com, with rare provider-driven
rotations on suspicious activity.

The refresh flow is:

```
POST <token endpoint>
  client_id=<...>
  client_secret=<...>      (confidential client only)
  refresh_token=<stored>
  grant_type=refresh_token
  scope=<original scopes>  (Google requires this)
```

The provider responds with a new access_token, a new `expires_in`,
**and possibly a new refresh_token** (rotation). We store whichever
refresh_token was most recently returned, overwriting the prior value.

If the refresh fails with `invalid_grant` (the user revoked from the
provider side, or the refresh_token expired) we DROP the row and
prompt the user to reconnect on next interactive use.

---

## Revocation

The user has **three independent revocation paths**:

1. **In Sentientia LMS** — the "Disconnect" button on
   `/local/sentientia_calendar/index.php` (Phase 2.1 UI) calls
   `oauth_base::revoke($userid)`. This drops our local DB row but does
   NOT notify the provider. The provider still believes consent is
   valid until the refresh_token naturally expires.
2. **At the provider** — the user revokes from
   `account.microsoft.com/privacy/app-access` or
   `myaccount.google.com/permissions`. Our next refresh attempt
   fails with `invalid_grant` and we drop the row.
3. **Admin force-revoke** — a future admin tool (Phase 2.2) will let
   managers revoke tokens for any user under their tenant via the
   `local/sentientia_calendar:manage_all` capability.

`oauth_base::revoke()` (Phase 2.1) POSTs the refresh_token to the
provider's revoke endpoint (`https://oauth2.googleapis.com/revoke`;
Microsoft has no standalone revoke endpoint — they treat consent removal
as the only revocation path) BEFORE dropping the local row, so
revocation is end-to-end for Google. The provider call is best-effort:
if it fails, the local row is still dropped so the user gets a clean
local state.

---

## Feature-flag enforcement

The master flag `sentientia.calendar_sync.oauth.enabled` is consulted in
**four** places:

1. `oauth_base::assert_feature_flag_enabled()` is called by
   `build_authorize_url()`, `handle_callback()`, `refresh_token()`, and
   `get_valid_access_token()`.
2. The user-facing connect buttons on
   `/local/sentientia_calendar/index.php` are hidden when the flag is
   OFF — `local_sentientia_calendar_oauth_section_context()` returns
   `oauth_section_visible = false` so the page doesn't render a
   "Connect Outlook" button to click.
3. The `/local/sentientia_calendar/oauth/callback.php` endpoint throws
   `error_flag_off` when the flag is OFF, denying any oracle that might
   let an attacker infer the feature is being trialled.
4. The settings page renders the credential fields regardless (so
   admins can pre-stage configuration), with a banner explaining that
   the flag must be ON for the surfaces to activate.

When the flag is OFF, **no row can be written to
`local_sentientia_calendar_oauth`** by any live code path in this
plugin, and **no outbound HTTP** reaches Microsoft or Google.

---

## GDPR / DPDP notes

The privacy provider (`classes/privacy/provider.php`) declares:

- **One internal database table** — `local_sentientia_calendar_oauth`
  — with field-level metadata.
- **Two external destinations** — `microsoft_graph` and
  `google_calendar` — to disclose that when the user opts in, calendar
  events are read from and written to those providers.

On a **right-to-erasure** request the provider drops:

- Every row in `local_sentientia_calendar_token` for the user (Phase 1).
- Every row in `local_sentientia_calendar_oauth` for the user (Phase 2).

The user must independently revoke consent at the provider side; we
cannot delete the provider's record on their behalf. This is documented
in the privacy export ("you have an OAuth row stored — to also revoke
at the provider go to …").

On a **data-portability export** the provider emits:

- Phase 1 token metadata (existence + last-used IP + use count).
- Phase 2 OAuth metadata (provider name + expires + scopes + timestamps).
- **NEVER** the plaintext tokens themselves. The encrypted bodies are
  replaced with the literal string
  `[REDACTED — encrypted credential not exported]` so a future archive
  reader doesn't accidentally treat the encrypted blob as a real token.

---

## Security-review checklist (gate for live rollout)

Before flipping `sentientia.calendar_sync.oauth.enabled` ON for any
customer, this checklist must be ticked:

- [ ] PHPUnit suite green — `tests/token_vault_test.php` round-trip +
      feature-flag toggle + privacy export, and `tests/oauth_flow_test.php`
      covering callback, refresh, rotation, invalid_grant, revoke, and
      expiry-triggered refresh, all pass against the mock handler.
- [ ] `\core\encryption::key_exists()` returns true on the target
      Moodle instance. The Sodium key file is chmod 0400 and backed up.
- [ ] Azure / Google app registration uses the SECRET (not the public
      client / mobile app type). Confidential-client flow is required.
- [ ] Redirect URI on both app registrations matches
      `$CFG->wwwroot . '/local/sentientia_calendar/oauth/callback.php'`
      verbatim — no trailing slash drift.
- [ ] Client secrets are stored via `admin_setting_configpasswordunmask`
      (this plugin) — not in `.env`, not in source control.
- [ ] Audit log entry for the flag flip — manager who flipped + reason
      + timestamp via `feature_flags::set()`'s audit-row mechanism.
- [ ] DPDP / GDPR sign-off — the user-consent screen on first connect
      links to the customer's privacy policy and explains the scopes
      requested in plain English.

---

## References

- ADR-013 — [Calendar sync: token-URL ICS feed vs OAuth bi-directional](../adr/ADR-013-calendar-sync.md)
- ADR-002 — [Customer-Level Feature Flags](../adr/ADR-002-customer-level-feature-flags.md)
- Phase 1 plugin docs — `state-cards/local_sentientia_calendar-state.md`
- Moodle encryption helper — `lib/classes/encryption.php`
- Moodle privacy framework — `privacy/classes/local/`
- RFC 7636 — [Proof Key for Code Exchange (PKCE)](https://datatracker.ietf.org/doc/html/rfc7636)
- RFC 6749 — [OAuth 2.0 Authorization Framework](https://datatracker.ietf.org/doc/html/rfc6749)
- Microsoft Graph identity docs (consult before Phase 2.1 wire-up)
- Google Identity OAuth docs (consult before Phase 2.1 wire-up)
