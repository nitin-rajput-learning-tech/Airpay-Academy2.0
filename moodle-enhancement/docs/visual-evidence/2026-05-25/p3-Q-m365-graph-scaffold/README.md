# P3-Q — `local_sentientia_m365` OAuth + Graph scaffold (Workstream C.1)

**Chip:** P3-Q / `loving-hamilton-oG4VQ` · **Merge:** `fcc456938` · **Date:** 2026-05-24

## What changed

Phase C.1 of Sentientia LMS Workstream C — Microsoft 365 / Entra
integration. New plugin `local_sentientia_m365`:

- MSAL OAuth scaffolding (PKCE public-client flow)
- Graph API client stub that throws `confirm_required` exception
- Encrypted token table via `\core\encryption`
- Capability `local/sentientia_m365:connect`
- Two feature flags, default OFF:
  - `sentientia.m365.enabled`
  - `sentientia.m365.live_graph`
- Privacy provider
- en + hi language packs at parity

### Admin settings (4 fields)

- `azure_tenant_id` — Entra tenant GUID
- `azure_client_id` — App registration GUID
- `redirect_uri` — must match Azure registration
- `allowed_scopes` — multiselect of optional OAuth scopes (Calendars.Read,
  Files.Read, Mail.Read, etc.)

**The Azure client secret is NOT a database setting.** Per
`.claude/rules/api.md`, confidential-client secrets must live in
`config.php`. Phase C.1 ships the public-client PKCE flow — no secret
needed.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-admin-settings.png` | Settings page with empty Azure tenant + client ID fields, redirect URI pre-filled with airpay.academy callback, allowed-scopes multiselect with Calendars.Read + Files.Read pre-selected. Both feature flags OFF. |
| `screenshot-desktop-dark.png`           | Same surface, dark mode |
| `screenshot-mobile-admin-settings.png`  | 590px viewport — fields stack |

## What to look for

1. **No client_secret field on the settings page.** PKCE flow only.
2. **Baseline scopes documented but not editable.** `openid, profile,
   offline_access, User.Read` — always requested, not configurable.
3. **Stub posture banner.** Footer banner shows the `confirm_required`
   exception text — calling Graph in C.1 is a no-op until C.2.
4. **Both feature flags OFF chips.** `sentientia.m365.enabled` AND
   `sentientia.m365.live_graph` need to flip for any Graph traffic.

## Acceptance

- ✓ Plugin installs cleanly via `upgrade.php` (token table registered)
- ✓ Calling `graph_client::get('/me')` raises `moodle_exception('confirm_required', 'local_sentientia_m365')`
- ✓ Capability `local/sentientia_m365:connect` gates the connect UI
- ✓ Privacy export lists the token table
- ✓ Hindi pack at 100% parity with English

## Refs

- ADR: `docs/adr/ADR-015-m365-integration-architecture.md`
- State card: `state-cards/local_sentientia_m365-state.md`
- Workstream: C.1 (M365 SSO + calendar + drive integration; future
  phases land in C.2–C.4)
- Companion: P3-N (calendar OAuth scaffold) — shares the encrypted-token-vault
  pattern
