# local_sentientia_ai — state card

**Component:** `local_sentientia_ai` | **Created:** 2026-08-04 | **Owner:** Nitin Rajput
**Version:** 2026080401 / 0.1.0-alpha (MATURITY_ALPHA) | **Depends:** local_sentientia_platform

## What it is

The Sentientia AI Gateway (ADR-028 Phase 2.3 / hard call #4, executed the day the
memo was signed): the single entry point every Sentientia AI feature calls instead
of carrying its own Anthropic client. Owns central key management, the spend
ledger (`local_sentientia_ai_ledger`), fail-closed daily/monthly quotas
(global + per-customer + monthly-cost; 0/empty = live BLOCKED, never unlimited),
and mock-first routing. Prompt/response text is never stored — accounting only.

## Surfaces

- `\local_sentientia_ai\client::complete([...])` — the consumer API (see README
  for the contract; result shape is a superset of the historical per-plugin
  clients: body / tokens_in / tokens_out / mode(mock|live|failed|denied) /
  error / ledgerid).
- `/local/sentientia_ai/index.php` — spend-ledger admin page (Reports ▸ AI spend
  ledger; `:viewledger` cap, manager archetype).
- Settings: central `api_key` (passwordunmask), `default_model`, 3 quota caps.

## Flags (both default OFF)

- `sentientia.ai.gateway.enabled` — master. OFF = everything mocks (still ledgered).
- `sentientia.ai.live_api.enabled` — the org live-spend gate the signed
  Addendum-A budget governs. ⚠ Blocked on Nitin: cap figure + ANTHROPIC key.

## Consumers

- ✅ `local_sentientia_aiquiz` 0.2.2-alpha/2026080402 — reference migration.
  Routing is OPT-IN: the dispatcher delegates only when the gateway exists AND
  `sentientia.ai.gateway.enabled` is ON (default OFF = dormant + reversible,
  byte- and side-effect-identical legacy path; own ADR-012 layers untouched;
  mock fidelity incl. v2-hindi verified byte-faithful through the gateway).
- ⏳ skillsai, recommendations, translate, authoring, assistant — follow-up
  migrations per README recipe.

## Verification (2026-08-04, local)

Fresh install clean (table + 5 settings + caps + 2 flags registered). CLI smoke:
generic mock ledgered; aiquiz mock + v2-hindi through the gateway byte-faithful;
quota aggregates exclude mock/denied rows. Ledger page renders as siteadmin
(aggregates + roll-up + recent, 0 broken keys); anonymous → 303 login.
PHPUnit: 11-test gateway suite GREEN (routing, fail-closed quotas, ledger
arithmetic, pricing, structural no-spend guard, golden fixtures ×2; 35
assertions). The first run exposed two real defects, both fixed: tests could
reach the real API (install-applied setting defaults gave quota headroom ->
structural PHPUNIT/BEHAT guard now inside call_live()), and the platform flag
resolver's PHP statics leak across test classes (setUp() invalidation). aiquiz
regression suite result recorded in PROJECT-STATE.

## Real privacy provider

Ledger is user-attributed → full metadata/export/delete provider (not null),
incl. the Anthropic external-location declaration.
