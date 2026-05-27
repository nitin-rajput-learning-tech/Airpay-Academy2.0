# AI Quiz — live Anthropic API runbook (local_sentientia_aiquiz)

**Owner:** Site Admin (Nitin Rajput).
**Status:** Wiring complete + mock-validated (2026-05-27). The live path
has **not** been exercised — every prior run used mock mode (no spend).

This runbook is the `[CONFIRM]` hand-off: the steps to make the first
**real, paid** Anthropic call. Per CLAUDE.md §3 the agent will not POST to
Anthropic without an explicit in-chat `[CONFIRM]`; an operator runs this.

---

## What's already built + verified

- `classes/anthropic_client.php` — `generate()` dispatches mock vs live on
  the `sentientia.aiquiz.live_api` flag. `call_live()` is a complete curl
  POST to `https://api.anthropic.com/v1/messages` (x-api-key header,
  `anthropic-version: 2023-06-01`, model `claude-sonnet-4-6`, max 4096
  out-tokens, 60s timeout). Errors return a `mode:'failed'` result (never
  throws, never logs the key).
- `prompt_builder.php` — v1 (English) + v2-hindi system/user prompts, plus
  a per-customer template override.
- `response_parser.php` — strict parse + drops malformed questions + PII
  scan (Aadhaar/PAN).
- `draft_manager.php` — persists a **draft** (never auto-publishes to
  learners); `review.php` is the human review/approve step.
- `generate.php` — the `[CONFIRM]` gate: `require_capability` +
  `require_sesskey()` + a mandatory **confirm checkbox** (server-validated
  `err_confirm_required`).
- **Mock smoke:** `php local/sentientia_aiquiz/cli/mock_smoke.php` →
  "End-to-end mock pipeline: PASS".

## 4-layer cost defence (already enforced)

1. `sentientia.aiquiz.enabled` master flag (default OFF).
2. `sentientia.aiquiz.live_api` flag (default OFF) — when OFF, `generate()`
   always returns the mock payload; no socket is opened.
3. `local_sentientia_aiquiz | api_key` admin setting — empty ⇒
   `call_live()` returns `failed: api_key_not_set` before any HTTP.
4. Per-action UI `[CONFIRM]` checkbox — unticked ⇒ generation refused.

All four must align for a single paid call. One generation = one call
(no chaining).

---

## To run the first LIVE generation

1. **Set the API key** (never commit it):
   Site administration → Plugins → Local plugins → Sentientia AI Quiz →
   `api_key` = your Anthropic key. (Or `php admin/cli/cfg.php --component=local_sentientia_aiquiz --name=api_key --set=...`.)
2. **Flip both flags ON** (Switchboard or DB), scoped as narrowly as you
   want (global / per-tenant / per-customer):
   - `sentientia.aiquiz.enabled`
   - `sentientia.aiquiz.live_api`
   Verify: `anthropic_client::is_live_ready()` returns true.
3. **Generate:** open `/local/sentientia_aiquiz/generate.php`, paste a
   SMALL source (a paragraph), set count = 3, pick language (English or
   Hindi), **tick the confirm checkbox**, submit.
   - Expected cost: a few US cents for ~3 questions.
   - Result: a **draft** with `mode: 'live'` + token counts recorded.
4. **Review:** `/local/sentientia_aiquiz/review.php` — inspect the
   generated questions, edit, then approve/publish. Nothing reaches
   learners until you approve.
5. **Revert (recommended after the test):** flip `live_api` OFF again so
   routine use stays in mock mode until you're ready for production.

## If it fails

`call_live()` returns a `mode:'failed'` draft with a redacted `error`:
- `api_key_not_set` — step 1 not done.
- `http_401` / `http_403` — bad/expired key.
- `http_429` — rate limited; retry later.
- `curl_error: ...` — network/egress blocked from the host.
- `empty_response_body` — model returned no text; retry or shorten source.

The failed draft is persisted for audit/retry — no learner impact.
