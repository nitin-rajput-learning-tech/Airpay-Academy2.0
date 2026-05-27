# ADR-015 — AI Course Recommendations architecture for `local_sentientia_recommendations`

- **Status:** Accepted
- **Date:** 2026-05-25
- **Decider:** Nitin Rajput (deferred to Claude under continuous-build mandate)
- **Stream:** G — AI Features (Tier 1 AI #5)
- **Phase:** H.0 — MVP scaffold

> **ADR numbering note.** The Wave-E3-P4 chip brief referred to this as
> "ADR-014". ADR-014 was already allocated to real-time leaderboards
> (`ADR-014-real-time-leaderboards-realtime-mechanism.md`) earlier in the
> same build wave, so recommendations took the next free number (015) and
> translation took 016. The pairing is otherwise exactly as briefed.

---

## Context

The next AI feature after quiz generation (ADR-012) is a course
recommendations engine. A learner finishing a course should see the
top 3-5 courses that are the best next step for *them* — based on their
completion history, current skills, role, and per-tenant learning
patterns — each with a short, plain-English rationale.

The same architectural questions ADR-012 locked in for quiz generation
recur here, plus a few recommendation-specific ones:

1. **Which model?** — Sonnet vs Haiku vs Opus.
2. **How do we gate cost?** — recommendations are generated for *many*
   learners, not one trainer-initiated action.
3. **How do we keep the MVP demoable without spending money?**
4. **What's the contract between Claude and the parser?**
5. **How do we stop Claude inventing courses that don't exist?**
6. **What data leaves the platform, and is it PII-safe?**
7. **How does it integrate with the feature-flag + multi-tenant plumbing?**

---

## Decisions

### D1 — Model: `claude-sonnet-4-6` as default, configurable

Identical reasoning to ADR-012 §D1. Sonnet reads a learner profile +
candidate catalogue and produces well-reasoned, grounded suggestions at a
fraction of Opus cost. The `model` is recorded per row and configurable as
an admin default. Haiku stays available for cheap exploratory batches.

### D2 — Prompts: versioned in code (`prompt_builder::VERSION`)

Same decision as ADR-012 §D2. `v1` ships in H.0. Phase H.1 introduces
`v2-cohort` (adds cohort-completion patterns) and Hindi reasoning strings.
Every row records its `prompt_version` for reproducibility + A/B testing.

### D3 — Cost gating: 4-layer defence, with a PER-CUSTOMER daily cap

Recommendations differ from quiz generation: they fan out across many
learners, so a per-*user* cap (ADR-012 §D3.4) is the wrong unit. The four
layers are:

1. **Master feature flag** `sentientia.recommendations.enabled` (default
   OFF) — no route loads while OFF; the dashboard block renders nothing.

2. **Live-API feature flag** `sentientia.recommendations.live_api`
   (default OFF) — when OFF, `anthropic_client::call_mock()` returns a
   deterministic recommendation list (first N non-completed candidates,
   descending score, "[MOCK]" reasoning). The whole pipeline (profile →
   prompt → parse → persist → block render) runs against the mock, so the
   MVP demos end-to-end at zero spend.

3. **Per-call [CONFIRM] gate** — the generate UI requires "I confirm I
   want to call Anthropic with this learner profile" before a batch is
   created. The future cron task (gated separately by
   `sentientia.recommendations.auto_cron`, default OFF) carries the same
   gate as an explicit admin opt-in, never silent.

4. **Per-customer daily token cap** (`daily_cost_cap_tokens`, default
   2,000,000) — `recommendation_engine::tokens_used_today_for_customer()`
   sums tokens across *all* learners in the customer since 00:00.
   generate.php refuses to start a batch over cap. This is the right unit
   for a fan-out feature: it bounds the *customer's* daily bill regardless
   of how many learners are processed.

### D4 — Anti-abuse: catalogue-bounded output + PII heuristic + ownership

- **Catalogue-bounded output.** `response_parser::parse()` takes the list
  of candidate course IDs and drops any `course_id` Claude returns that is
  not in that list. The model physically cannot recommend a course that
  doesn't exist or that the learner can't see — this is the single most
  important correctness guard for a recommender.

- **Already-completed filter.** `build_candidate_list()` removes courses
  the learner already completed before the catalogue is even sent, and the
  system prompt restates the rule. A learner is never recommended
  something they've finished.

- **PII heuristic.** `prompt_builder::profile_contains_pii_pattern()`
  scans the profile (skills, role) for Aadhaar / PAN patterns before any
  call. The profile sent to Anthropic is deliberately thin — role, tenant,
  completed course IDs, skill tags — never names, emails, or employee IDs.

- **Ownership scope.** The dashboard block only ever calls
  `latest_for_user($USER->id)`. `update_status()` enforces an ownership
  check before flipping a row, so a learner can't dismiss another's
  recommendation by guessing a row id.

### D5 — Demo-able without money: deterministic mock client

`anthropic_client::call_mock()` returns the first N non-completed
candidates with a deterministic descending score and a "[MOCK]" reasoning
string. The block renders these exactly as it would render live output, so
the entire UX is demoable with the master flag ON and the live-API flag
OFF.

### D6 — Claude/parser contract: strict JSON, parser drops malformed

The system prompt instructs Claude to return ONLY a JSON object with one
`recommendations` key. Each item is `{course_id, score, reasoning}`. The
parser strips ```json fences, decodes strictly, clamps `score` to 0..100,
drops items with no/invalid `course_id` or a `course_id` outside the
candidate set, and de-dupes. It never repairs output.

### D7 — Batch model + lifecycle

Recommendations are grouped into a **batch** (a random 32-hex `batchid`).
Persisting a new batch marks the learner's prior active batch `expired` in
the same transaction, so a learner always sees exactly one current set.
Per-row status (`active → dismissed | enrolled | expired`) lets the block
hide dismissed rows and lets a future cron mark a row `enrolled` when the
learner enrols.

### D8 — Multi-tenant + multi-customer plumbing

Identical to ADR-012 §D7. Every row carries `customerid` (hardcoded 1 in
Phase 1) + `costcenterid` (from the learner's `open_path`). The three new
flags auto-register via `local_airpay_core\feature_flags`' registry walk.

### D9 — Tests: mock-only, network-free

PHPUnit covers `prompt_builder`, `response_parser`, `recommendation_engine`
(persist/expire/retrieve/ownership/cost), and `anthropic_client::call_mock`.
The live-API branch is only tested for its no-API-key fast-fail. The
dashboard block has its own render test (guest → empty, no-batch → empty,
active-batch → rendered). A CLI smoke runs the pipeline with no DB.

---

## Consequences

### Positive

- Demoable day-1 with zero Anthropic spend.
- Cost bounded by four independent layers; the per-customer cap is the
  correct unit for a fan-out feature.
- Claude cannot recommend a non-existent or already-completed course —
  the catalogue filter makes hallucinated course IDs structurally
  impossible to persist.
- The dashboard block is a thin, read-only consumer — it adds no new write
  path and renders nothing when the flag is OFF, preserving production
  behaviour exactly.

### Negative

- Mock mode produces obviously fake reasoning. Evaluating recommendation
  *quality* requires flipping `live_api` ON with budget approval.
- The Phase-H.0 profile is thin (role + tenant + completed IDs + skill
  tags). Richer signals (time-on-task, quiz scores, manager goals) land in
  H.1/H.2.
- No cron in H.0 — batches are manager-initiated. `auto_cron` flag is
  registered but the task itself ships in H.3 once the cost pattern is
  proven on staging.

### Reversibility

**High.** Anthropic is swappable in one file (`anthropic_client.php`). The
prompt/parser/engine are provider-agnostic. The schema records `model` as
free text. The recommendation engine could even be swapped for a
non-LLM collaborative-filtering implementation behind the same
`recommendation_engine` API without touching the block.

---

## Implementation notes

### File layout

```
local/sentientia_recommendations/
├── version.php                 (2026052500, 0.1.0-alpha)
├── README.md
├── lib.php                     (navigation hook)
├── settings.php                (api_key, model, caps, per-customer cost cap)
├── generate.php                (manager form + [CONFIRM] gate)
├── cli/mock_smoke.php          (deterministic end-to-end, no DB/network)
├── db/
│   ├── install.xml             (1 table: rec_log)
│   ├── access.php              (3 caps: view, generate, manage_all)
│   └── feature_flags.php       (3 flags: enabled, live_api, auto_cron)
├── classes/
│   ├── anthropic_client.php    (call_mock + call_live)
│   ├── prompt_builder.php      (v1 system prompt + validation)
│   ├── response_parser.php     (strict JSON, catalogue-bounded)
│   ├── recommendation_engine.php (profile build + persist + retrieve)
│   └── privacy/provider.php    (GDPR/DPDP metadata + export + delete)
├── lang/{en,hi}/local_sentientia_recommendations.php   (79 keys, 100% parity)
└── tests/
    ├── prompt_builder_test.php
    ├── response_parser_test.php
    ├── recommendation_engine_test.php
    └── anthropic_client_test.php

blocks/sentientia_recommendations/
├── version.php                 (depends on local_sentientia_recommendations)
├── block_sentientia_recommendations.php
├── db/access.php               (add/myadd instance caps)
├── templates/recommendations.mustache
├── lang/{en,hi}/block_sentientia_recommendations.php   (4 keys, 100% parity)
└── tests/block_render_test.php
```

### Status state machine — recommendation row

```
   persist_batch()
        │
        ▼
   ┌──────────┐   learner dismisses   ┌──────────────┐
   │  active  │ ────────────────────▶ │  dismissed   │
   │          │   learner enrols      ├──────────────┤
   │          │ ────────────────────▶ │  enrolled    │
   └────┬─────┘                       └──────────────┘
        │  newer batch persisted
        ▼
   ┌──────────┐
   │ expired  │
   └──────────┘
```

### Phase H.0 deferrals

- Background cron refresh (H.3, gated by `auto_cron`).
- Cohort-based recommendations + Hindi reasoning (H.1, `v2-cohort`).
- Cost analytics dashboard (H.3).
- Richer profile signals (quiz scores, time-on-task, manager goals).
- A live Anthropic call (requires budget + key).

---

## Verification gates

1. ✅ `php admin/cli/upgrade.php` installs cleanly (XML validated).
2. ✅ PHPUnit test files pass (mock-only).
3. ✅ Mock-mode end-to-end demo: generate → block renders top-N.
4. ✅ Hindi parity verified (`array_diff_key` empty, 79 keys).
5. ⏳ Live-API end-to-end smoke — manual, one-shot, [CONFIRM] gate.
6. ⏳ Switchboard shows the three new flags.

---

## Links

- ADR-012 — AI Quiz Generation (sibling pattern)
- ADR-016 — AI Content Translation (sibling chip)
- CLAUDE.md §3 [CONFIRM] gate, §13 feature-flag mandate
- Plugin: `moodle-enhancement/local/sentientia_recommendations/`
- Block: `moodle-enhancement/blocks/sentientia_recommendations/`
- State card: `moodle-enhancement/state-cards/local_sentientia_recommendations-state.md`
