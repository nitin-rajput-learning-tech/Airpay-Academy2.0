# local_sentientia_ai — the Sentientia AI Gateway

**One model gateway for every Sentientia AI feature** (ADR-028 Phase 2.3 /
hard call #4, signed 2026-08-04). Replaces six near-identical per-plugin
`anthropic_client` classes with a single entry point owning key management,
a spend ledger, fail-closed quotas, and mock-first routing.

## Why

Before the gateway: aiquiz, skillsai, recommendations, translate, authoring
and assistant each carried their own Anthropic client and admin-setting API
key — six keys to rotate, no org-level spend view, no cross-plugin quota,
and "flip live" risk unquantified. The signed Addendum-A decision approved a
multi-feature live budget **conditional on this gateway existing first**.

## Contract

```php
$result = \local_sentientia_ai\client::complete([
    'component'  => 'local_sentientia_aiquiz',   // required frankenstyle
    'purpose'    => 'quiz_generation',           // required slug (ledger analytics)
    'usertext'   => $usermessage,                // required
    'system'     => $systemprompt,               // optional
    'model'      => 'claude-sonnet-4-6',         // optional (falls back to setting)
    'max_tokens' => 4096,                        // optional, hard-capped at 8192
    'mock'       => fn($req) => $body,           // optional component mock
    'legacy_component' => 'local_sentientia_aiquiz', // optional key fallback
]);
// => ['body', 'tokens_in', 'tokens_out',
//     'mode' => 'mock'|'live'|'failed'|'denied', 'error', 'ledgerid']
```

Routing: both flags (`sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled`)
must be ON for live; otherwise the component's mock (or a generic
deterministic mock) runs at zero cost. On a live-intent path a missing key
→ `failed`, exhausted quota → `denied` — **never a silent mock**, so fake
content can't impersonate a real generation. Every call, whatever the
mode, writes one ledger row (`local_sentientia_ai_ledger`; admin view at
`/local/sentientia_ai/index.php`, Reports ▸ AI spend ledger).

Quotas (settings, all fail-closed — 0/empty = live blocked, never
unlimited): `daily_tokens_global`, `daily_tokens_customer`,
`monthly_cost_cap_usd` (pricing-map **estimates**, conservative default
tier for unknown models).

## What stays in the consumer

The gateway is **layer 0**. Component-level gates stay where they are:
per-feature flags (`sentientia.aiquiz.enabled`, `.live_api`), the per-action
[CONFIRM] UI, per-plugin input caps and prompt building (ADR-012 layers).
Prompt/response text is never stored in the ledger — only accounting.

## Migration recipe (reference: local_sentientia_aiquiz 2026080402)

1. In the plugin's client dispatcher, delegate to `client::complete()` —
   passing your existing mock as the `'mock'` callable and your plugin
   name as `'legacy_component'` — **only when BOTH** `\local_sentientia_ai\client`
   exists **and** `sentientia.ai.gateway.enabled` is ON (the routing
   switch). While it's OFF your plugin must behave byte- and
   side-effect-identically to its pre-gateway build (local mock, no
   ledger writes) — this keeps your existing test suite meaningful and
   makes the migration dormant + reversible.
2. Keep the standalone fallback path so the plugin still works on a
   deployment without the gateway installed at all.
3. Map the result: the array shape is a superset of the historical one —
   treat `'denied'` like `'failed'` (persist, surface, don't retry-loop).
4. Bump the plugin version; note the migration in its state card.

All six consumers are migrated (2026-08-05): aiquiz (reference,
2026080402), skillsai, recommendations, translate, authoring, assistant —
each at 2026080500. The assistant's core_ai bridge stays as an alternative
backend behind its provider toggle. Caveat: authoring's legacy key setting
is `anthropic_api_key`, which the `legacy_component` fallback (reads
`api_key`) does not cover — the central key applies on its gateway path.

## Eval harness

`tests/gateway_test.php` — routing, fail-closed quotas, ledger arithmetic,
pricing (no HTTP anywhere). `tests/golden_test.php` +
`tests/fixtures/golden/*.json` — byte-stability of mock output; when the
live pilot starts, fixtures grow quality rubrics for prompt changes.
