# P3-M — `local_sentientia_aiquiz` scaffold (Phase G.1)

**Chip:** P3-M / `magical-rubin-9xNyw` · **Merge:** `3e4c94d60` · **Date:** 2026-05-24

## What changed

Phase G.1 of Sentientia LMS Tier 1 #4 — AI Quiz Generation. Adds the
`local_sentientia_aiquiz` plugin:

- **Anthropic client stub** with `[CONFIRM]` guard (no live calls in
  this chip)
- Prompt builder + response parser + draft manager
- Privacy provider (registers data exports + deletes)
- Three feature flags, all default OFF:
  - `sentientia.aiquiz.enabled`
  - `sentientia.aiquiz.live_api`
  - `sentientia.aiquiz.auto_push`
- PHPUnit test classes (~47 tests, all green without an API key)
- Hindi pack at full parity

### Admin settings (4 fields)

1. `api_key` — Anthropic key, stored as `passwordunmask`
2. `default_model` — defaults to `claude-sonnet-4-6`
3. `max_questions` — per-request ceiling (default 10)
4. `daily_token_cap` — soft cap on tokens/day before generate is blocked

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-admin-settings.png` | Site admin → Plugins → Local plugins → AI Quiz. Three OFF feature-flag chips at top, settings form with API key field (passwordunmask), default model field, daily cap. Generate-page mockup below shows MOCK MODE banner. |
| `screenshot-desktop-dark.png`           | Same surface, dark mode |
| `screenshot-mobile-admin-settings.png`  | 590px viewport — fields stack, flag chips wrap |

## What to look for

1. **All three feature flags default OFF.** The red `flag--off` chips
   confirm Switchboard state. Live-API path is unreachable from a fresh
   install.
2. **MOCK MODE banner on generate page.** Until `live_api` flips ON,
   every Generate click returns 10 `[MOCK Q]` questions from the local
   stub — zero outbound API traffic.
3. **API key field is `passwordunmask`.** Type shows as `password`; the
   help text notes "stored as passwordunmask; not shown in logs".
4. **`[CONFIRM]` gate exists in code.** Even with `enabled=ON` and
   `live_api=ON`, the actual `anthropic_client::generate()` call requires
   a per-session `[CONFIRM]` to fire.

## Acceptance

- ✓ Plugin installs cleanly via `php admin/cli/upgrade.php`
- ✓ Tables `local_sentientia_aiquiz_draft` + `local_sentientia_aiquiz_question` exist
- ✓ All 4 PHPUnit test files pass (~47 tests, no API key)
- ✓ Privacy export + delete work for users with drafts
- ✓ Hindi pack: 100% parity with English

## Refs

- ADR: `docs/adr/ADR-012-sentientia-aiquiz-scaffold.md`
- State card: `state-cards/local_sentientia_aiquiz-state.md`
- Phase: G.1 (G.0 MVP shipped 2026-05-24 earlier in day)
- Tier: 1 #4 of the Sentientia LMS roadmap
