# State Card — `local_sentientia_m365` (Sentientia LMS Microsoft 365 integration)

**Current phase:** C.1 — OAuth scaffold + Graph stubs + privacy + Hindi pack
**Version:** 0.2.0-alpha (2026052801)
**Status:** Scaffold; no live Graph calls reachable. Every `graph_client`
method throws `moodle_exception('confirm_required')`. Feature flag
`sentientia_m365_enabled` default OFF for every customer.
**Owner:** Nitin Rajput (PM) + Claude (engineering)
**Last updated:** 2026-05-28

---

## Mission

Workstream C / Tier 3 on the Sentientia LMS roadmap. Bridges the LMS to a
customer's Microsoft 365 tenant so SharePoint documents, Teams meeting
summaries, and Outlook calendar entries become first-class LMS content.
Two anchor use-cases:

- "Pull this SharePoint folder of SOPs into the SENTIENTIA pipeline once
  a week" → SCORM auto-generation feed.
- "When my manager schedules an enablement session in Outlook, auto-create
  the LMS classroom" → calendar sync.

Phase C.1 (this version) is the OAuth + Graph scaffolding ONLY. C.2+ unwire
the stubs.

## Architecture

See [ADR-013-calendar-sync](../docs/adr/ADR-013-calendar-sync.md) for the
adjacent calendar-sync ADR which informs M365's OAuth approach (PKCE,
encrypted token storage). M365 doesn't have its own ADR yet — the patterns
are inherited.

Highlights:

- **OAuth flow:** Public-client PKCE (no client secret in DB). If C.2 needs
  a confidential client, the secret moves to `config.php` not the
  settings table — per `.claude/rules/api.md`.
- **Token storage:** `\core\encryption::encrypt()`-wrapped, Sodium
  ciphertext, base64-encoded for plain-ASCII columns.
- **Decrypt path:** `msal_client::decrypt_token()` wraps `\core\encryption::decrypt()`
  and translates Sodium failures to a generic `moodle_exception` — never
  leaks why decryption failed.
- **Scoping:** Tokens stored per `(userid, customerid)`. A single Moodle
  user under a multi-customer Sentientia deployment can link two distinct
  M365 tenants without colliding rows.

## Database schema (1 table)

| Table | Rows per | Purpose |
|-------|----------|---------|
| `local_sentientia_m365_tokens` | one per `(userid, customerid)` | Encrypted OAuth access + refresh tokens, scopes granted, expiry timestamps, last-used timestamps |

Row count on local: **0** (no user has connected an M365 account).

## Admin surfaces

### `local/sentientia_m365/admin/index.php` (C15 — 2026-05-28)

Today's stabilization fix. Unified OAuth admin landing dashboard:

- 4-card stats: Tenant ID configured · Client ID configured · Feature flag · Connected users
- `msal_client::is_ready()` summary banner
- C.1–C.6 roadmap table with Done/Planned badges
- Quick-nav: Azure & OAuth settings · Privacy / data subject rights
- Two banners: feature-flag-off + Phase-C.1 confirm-required notice
- Zero live OAuth calls. Reads config only; counts token rows; never decrypts.

### `local/sentientia_m365/settings.php`

- `azure_tenant_id` (GUID of Azure / Microsoft Entra tenant)
- `azure_client_id` (GUID of app registration)
- `redirect_uri` (OAuth callback URL)
- `allowed_scopes` (multiselect — optional Graph scopes admin may grant)

Azure client SECRET is intentionally NOT a setting (would live in
`config.php` if Phase C.2 needs it).

## Capability

`local/sentientia_m365:use` — default `false` on every archetype. Must be
granted explicitly per the M365-enable-per-user contract.

## Feature flag

`sentientia_m365_enabled` — default OFF for every customer. Registered in
`local/sentientia_m365/db/feature_flags.php`. When ON, the UI surfaces
the "Connect Microsoft account" CTA; OFF state keeps the CTA hidden.

## Privacy provider

`classes/privacy/provider.php` declares all data subject categories:

- Token row (userid, encrypted access/refresh tokens, scopes, expiry)
- Microsoft Graph receives the user's access token whenever a Sentientia
  LMS feature reads M365 data on the user's behalf
- Token row is exported on `get_export_data_for_user` and erased on
  `delete_data_for_user`

The export / erase flows are tested but no live user has exercised them
yet (no rows in production).

## Roadmap

| Phase | What | Status |
|-------|------|--------|
| C.1 | OAuth scaffold + Graph stubs + privacy + Hindi pack + admin landing | ✅ Done (2026-05-24 + 2026-05-28 C15) |
| C.2 | Replace `confirm_required` stubs with real Graph calls behind per-call [CONFIRM]. `get_me`, `list_sites`, `calendar` baseline | ⏳ Planned |
| C.3 | SharePoint document ingestion → SENTIENTIA SOP parser hand-off | ⏳ Planned |
| C.4 | Outlook meeting → LMS classroom event sync | ⏳ Planned |
| C.5 | Teams attendance ingestion → completion record | ⏳ Planned |
| C.6 | Per-customer prompt + scope overrides + Hindi consent UI | ⏳ Planned |

## Maturity

`MATURITY_ALPHA` — correctly stamped per
`docs/audits/MATURITY-TRIAGE-2026-05-28.md`. Promotion path:
**first sync against a live M365 tenant → BETA.**

## Files (Phase C.1 + C.15 surface)

- `version.php` — 2026052801, 0.2.0-alpha
- `settings.php` — 4 settings + admin_externalpage registration
- `admin/index.php` — C15 OAuth landing dashboard
- `db/install.xml` — 1 table (`local_sentientia_m365_tokens`)
- `db/access.php` — 1 capability (`local/sentientia_m365:use`)
- `db/feature_flags.php` — 1 flag (`sentientia_m365_enabled`)
- `classes/msal_client.php` — OAuth PKCE flow, token storage/load/refresh
- `classes/graph_client.php` — Phase C.1 stubs that throw `confirm_required`
- `classes/privacy/provider.php` — full GDPR export + erasure
- `lang/en/local_sentientia_m365.php` — 60+ strings (settings, capabilities, OAuth flow, scopes, C15 admin landing)
- `lang/hi/local_sentientia_m365.php` — Hindi pack (100% parity per Wave-3 P0)

## Pending / open

- C.2 implementation (replace `confirm_required` stubs)
- ADR for M365 itself (currently inherits ADR-013 patterns implicitly)
- Production OAuth round-trip test (gated behind real Azure tenant)
- Hindi consent UI for SharePoint-ingestion authorisation (Phase C.6)

## Cross-reference

- Stabilization Audit Bucket C / C15: `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md`
- Maturity triage: `docs/audits/MATURITY-TRIAGE-2026-05-28.md`
- Renames policy: `docs/RENAMES.md`
- Bucket F closeout: `docs/audits/BUCKET-F-CLOSEOUT-2026-05-28.md` (F-053..F-056 — this card closes the m365 row of that list)
