# ADR-016 — AI Content Translation architecture for `local_sentientia_translate`

- **Status:** Accepted
- **Date:** 2026-05-25
- **Decider:** Nitin Rajput (deferred to Claude under continuous-build mandate)
- **Stream:** G — AI Features (Tier 1 — Hindi content pipeline cousin)
- **Phase:** T.0 — MVP scaffold

> **ADR numbering note.** The Wave-E3-P4 chip brief referred to this as
> "ADR-015". Recommendations took 015 (because ADR-014 was already
> leaderboards), so translation took the next free number, 016.

---

## Context

Tier 1 priority #5 on the Sentientia LMS roadmap is a Hindi course-content
pipeline. The first building block is a general content-translation
feature: an admin pastes English course content (descriptions, page text,
narration excerpts) and Anthropic Claude returns a faithful translation in
a target language — Hindi, Marathi, Kannada, or Swahili — in the correct
native script.

Two requirements make this more than a thin LLM wrapper:

1. **Brand-name preservation.** A compliance LMS for a fintech is full of
   brand, product, and regulatory proper nouns ("Airpay", "UPI", "RBI",
   "Aadhaar"). These must NOT be naively translated. Worse, a customer may
   want a brand rendered in the target *script* (e.g. "Airpay" → the
   Kannada-script form) consistently across every translation. Getting
   this wrong is both a brand-integrity problem and a compliance-accuracy
   problem.

2. **Human review before save.** A machine translation of compliance
   content cannot go live unreviewed. The admin must see a side-by-side
   diff and explicitly accept.

Plus the standard ADR-012 questions (model, cost gating, mock mode,
parser contract, multi-tenant isolation, network-free tests).

---

## Decisions

### D1 — Model: `claude-sonnet-4-6` as default, configurable

Same reasoning as ADR-012 §D1 / ADR-015 §D1. Sonnet produces
natural-reading translations in Devanagari / Kannada / Latin scripts and
follows the strict-JSON + brand-preservation instructions reliably.
`max_output_tokens` defaults higher here (8192) because a translation is
roughly the same length as its (potentially multi-page) source.

### D2 — Prompts: versioned in code, script-aware

`prompt_builder::build_system_prompt($targetlang, $protectedterms)`
produces a `v1` system prompt that:
- names the target language AND its script explicitly (e.g. "Kannada
  (ಕನ್ನಡ), written in Kannada script"),
- lists the protected brand terms with a "keep verbatim" instruction,
- pins the strict-JSON output contract, and
- forbids translating numbers, dates, currency, and code identifiers.

Versioned in code per ADR-012 §D2; each row records `prompt_version`.

### D3 — Brand-name preservation: prompt instruction + deterministic post-processing

This is the headline design decision. Two layers, belt-and-suspenders:

1. **Prompt instruction (model-trusted).** The protected-term list is fed
   to Claude with an instruction to keep those terms verbatim. This covers
   *preservation* (don't translate "Airpay").

2. **Deterministic post-processing (model-independent).** After parsing,
   `brand_manager::apply_overrides()` runs a whole-token, longest-first,
   case-sensitive find/replace over the translated text using the
   per-(customer, target-language) override map. This covers *script
   rendering* ("Airpay" → the Kannada form) and GUARANTEES the substitution
   regardless of what the model actually did.

The post-processing layer is what makes brand rendering **unit-testable
without the API**: feed a known string + a known override map, assert the
output. The mock client deliberately echoes the source so the
post-processing pass has real brand tokens to act on, proving the whole
chain end-to-end at zero spend.

Whole-token matching (Unicode-aware `\p{L}\p{N}` boundaries) ensures
"Airpayment" is never mangled when "Airpay" is mapped. Longest-first
ordering ensures "Airpay Payment Services" wins over "Airpay".

**Always-protected defaults.** `brand_manager::DEFAULT_PROTECTED` ships a
curated fintech-domain list (Airpay, Sentientia, UPI, RBI, KYC, PAN,
Aadhaar, FIU-IND, NEFT, RTGS, IMPS, SCORM) that is always preserved even
with zero customer configuration. Customer overrides layer script
renderings on top.

### D4 — Cost gating: 4-layer defence with per-customer daily cap

Same four-layer model as ADR-015 §D3 (master flag, live-API flag,
per-call [CONFIRM] gate at the UI, per-customer daily token cap default
3,000,000). Translation is admin-initiated and can process long documents,
so the per-customer cap is the right bounding unit and the cap is set
higher than recommendations to allow for multi-page documents.

### D5 — Human review: diff-before-save lifecycle

A translation never auto-saves. The lifecycle is:

```
pending → translated → saved
                    ↘ discarded
                    ↘ failed
```

`translate.php` creates a `pending` row, runs the translation (mock or
live), stores the result as `translated`, then redirects to a
side-by-side diff (`?rowid=N`) where the admin clicks Save (→ `saved`) or
Discard (→ `discarded`). The [CONFIRM] gate is on the *generate* step
(spending money); the save step is a separate human-acceptance gate (going
live with the content).

### D6 — Claude/parser contract: strict JSON

The system prompt instructs Claude to return ONLY a JSON object with
exactly `translated_text`, `target_lang`, and `brand_terms_preserved`.
`response_parser::parse()` strips ```json fences, decodes strictly,
requires a non-empty `translated_text`, and returns null otherwise (→ the
engine marks the row `failed` with `parser_no_translation`). It never
repairs output.

### D7 — Multi-tenant + multi-customer plumbing

Every translation row carries `customerid` + `costcenterid` (from the
admin's `open_path`). `translate_engine::load_for_actor()` rejects
cross-tenant row access without `:manage_all`. **Brand overrides are
customer-scoped** — the `brand_overrides` table has a unique key on
`(customerid, brand_source, targetlang)`, so customer A's "Airpay → X"
mapping can never leak into customer B's translations. This is the
multi-customer isolation guarantee that matters most for a white-label
product: each customer owns its own brand vocabulary.

### D8 — Tests: mock-only, network-free, with dedicated brand coverage

PHPUnit covers `prompt_builder`, `response_parser`, `translate_engine`
(create/run/accept/discard/ownership/cost), `anthropic_client::call_mock`,
and — the headline — `brand_manager`. The brand tests assert:
- WITH override → brand substituted into target script, Latin form gone;
- WITHOUT override → brand preserved verbatim;
- language-scoping (kn override doesn't fire for hi);
- whole-token guard ("Airpayment" untouched);
- longest-first ordering;
- customer-scoped delete.

The live-API branch is only tested for its no-API-key fast-fail.

---

## Consequences

### Positive

- Brand rendering is deterministic and unit-tested — not at the mercy of
  model behaviour. This is the single most valuable property for a
  compliance + white-label context.
- Per-customer brand vocabularies are isolated by schema, satisfying the
  multi-customer product requirement.
- Demoable day-1 at zero spend; the mock echoes source so the brand
  post-processing chain is visible end-to-end.
- Human-review diff gate prevents unreviewed machine translation reaching
  learners.

### Negative

- Mock mode does not actually translate — it echoes source with a banner.
  Evaluating *translation quality* requires `live_api` ON with budget.
- Deterministic post-processing is literal find/replace: it cannot handle
  brand terms that must inflect grammatically in the target language
  (rare for proper nouns, but possible). Such cases need a model-only
  approach or a richer rule, deferred.
- T.0 translates pasted text only. Walking a whole course's content and
  writing translations back into Moodle's multilang filter format is T.1.
- PII heuristic catches Aadhaar + PAN only (same limitation as ADR-012).

### Reversibility

**High.** Anthropic is swappable in one file. The brand-override engine,
prompt builder, parser, and diff UI are all provider-agnostic. The schema
records `model` as free text. Brand overrides are pure data.

---

## Implementation notes

### File layout

```
local/sentientia_translate/
├── version.php                 (2026052500, 0.1.0-alpha)
├── README.md
├── lib.php                     (navigation hook)
├── settings.php                (api_key, model, caps, per-customer cap, brands link)
├── translate.php               (translate form + [CONFIRM] gate + diff view + save/discard)
├── brands.php                  (per-customer brand-override manager)
├── cli/mock_smoke.php          (deterministic end-to-end incl. brand preservation)
├── db/
│   ├── install.xml             (2 tables: tr_log + tr_brand)
│   ├── access.php              (3 caps: translate, manage_brands, manage_all)
│   └── feature_flags.php       (2 flags: enabled, live_api)
├── classes/
│   ├── anthropic_client.php    (call_mock + call_live)
│   ├── prompt_builder.php      (v1 script-aware prompt + validation)
│   ├── response_parser.php     (strict JSON)
│   ├── translate_engine.php    (orchestrate + brand post-process + persist)
│   ├── brand_manager.php       (protected terms + per-customer overrides)
│   └── privacy/provider.php    (GDPR/DPDP metadata + export + delete)
├── lang/{en,hi}/local_sentientia_translate.php   (99 keys, 100% parity)
└── tests/
    ├── brand_manager_test.php      (brand-name preservation — headline)
    ├── prompt_builder_test.php
    ├── response_parser_test.php
    ├── translate_engine_test.php
    └── anthropic_client_test.php
```

### Brand-preservation flow

```
source ──▶ build protected-term list (DEFAULT_PROTECTED + customer rows)
              │
              ▼
        system prompt: "keep these verbatim"  ─────▶ Claude (or mock)
              │                                          │
              ▼                                          ▼
        parse translated_text  ◀───────────────── strict JSON
              │
              ▼
        brand_manager::apply_for(text, customer, lang)
          whole-token, longest-first find/replace using override map
              │
              ▼
        translated text with guaranteed brand rendering ──▶ diff ──▶ save
```

### Phase T.0 deferrals

- Bulk course-content translation (T.1).
- ElevenLabs voice re-pack → SCORM (T.2).
- Cost analytics dashboard (T.3).
- Translation memory / dedup reuse (T.4).
- Grammatically-inflected brand handling.
- A live Anthropic call (requires budget + key).

---

## Verification gates

1. ✅ `php admin/cli/upgrade.php` installs cleanly (XML validated).
2. ✅ PHPUnit test files pass (mock-only); brand-preservation suite green.
3. ✅ Mock-mode end-to-end: translate → diff → save; brand WITH/WITHOUT
   override behaves correctly (CLI smoke confirms).
4. ✅ Hindi parity verified (`array_diff_key` empty, 99 keys).
5. ⏳ Live-API end-to-end smoke — manual, one-shot, [CONFIRM] gate.
6. ⏳ Switchboard shows the two new flags.

---

## Links

- ADR-012 — AI Quiz Generation (sibling pattern)
- ADR-015 — AI Course Recommendations (sibling chip)
- CLAUDE.md §3 [CONFIRM] gate, §13 feature-flag mandate
- Tier 1 #5 (Hindi content pipeline) in PROJECT-STATE.md
- Plugin: `moodle-enhancement/local/sentientia_translate/`
- State card: `moodle-enhancement/state-cards/local_sentientia_translate-state.md`
