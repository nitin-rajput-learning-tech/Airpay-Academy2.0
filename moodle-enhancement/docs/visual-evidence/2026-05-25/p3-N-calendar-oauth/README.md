# P3-N — Calendar Sync Phase 2 OAuth scaffolding (Tier 2.6)

**Chip:** P3-N / `practical-brahmagupta-tluHX` · **Merge:** `d05de927e` · **Date:** 2026-05-24

## What changed

Adds OAuth scaffolding to `local_sentientia_calendar` for two-way sync
with Microsoft 365 + Google Calendar. Pure-scaffold chip — no live
OAuth calls.

### New classes

```
local/sentientia_calendar/classes/oauth/
├── oauth_base.php       — abstract base, PKCE flow, token persistence
├── m365_oauth.php       — Microsoft 365 / Entra concrete impl
├── google_oauth.php     — Google Workspace concrete impl
└── token_vault.php      — \core\encryption wrapper for access + refresh tokens
```

### DB tables

- `local_sentientia_calendar_oauth_tokens` — encrypted token storage
  per `userid × provider × tenantid`
- `local_sentientia_calendar_sync_log` — audit row per sync attempt

### Feature flag

`sentientia_calendar_oauth` — default OFF. With flag OFF, the connect
buttons render but POST handlers return `feature_disabled` exception.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-connect-page.png` | Connect-your-calendar page with two provider cards (Microsoft 365 — Not connected; Google — Connected). Sync log table below. |
| `screenshot-desktop-dark.png`         | Same surface, dark mode — provider logos retain brand colours; surface + text tokens flip |
| `screenshot-mobile-connect-page.png`  | 590px viewport — provider cards stack |

## What to look for

1. **Encrypted-token banner.** Footer banner notes that access + refresh
   tokens are stored via `\core\encryption::encrypt()` — plaintext never
   touches the DB.
2. **Provider isolation.** Each provider has its own card, its own
   button, its own sync timestamp. Disconnecting Google does not affect
   M365.
3. **Sync log shows direction.** "Pull" vs "Push" rows; status badge.
   Last 24h only — older rows expire to audit table.
4. **`disconnect` button styled as `--danger`.** Reduces accidental
   token revocation.

## Acceptance

- ✓ Plugin upgrades cleanly via `upgrade.php` (new tables registered)
- ✓ Tokens encrypt + decrypt round-trip via `token_vault::test_roundtrip()`
- ✓ OAuth flow stubs return `confirm_required` exception until flag flips
- ✓ Privacy provider lists `oauth_tokens` table in user export

## Refs

- ADR: `docs/adr/ADR-013-calendar-oauth-architecture.md`
- State card: `state-cards/local_sentientia_calendar-state.md`
- Predecessor: Tier 2.6 Phase 1 — `local_sentientia_calendar` outbound ICS feed (chip `claude/nice-fermi-4GtFo`, merged 2026-05-24)
