# ADR-012 — AI Quiz Generation architecture for `local_sentientia_aiquiz`

- **Status:** Accepted
- **Date:** 2026-05-24
- **Decider:** Nitin Rajput (deferred to Claude under continuous-build mandate)
- **Stream:** G — AI Quiz Generation (Tier 1 #4)
- **Phase:** G.0 — MVP scaffold

---

## Context

Tier 1 priority #4 on the Sentientia LMS roadmap is "AI quiz generation
(Anthropic Claude API + trainer-in-the-loop review)". Course Authors should
be able to paste source material (SCORM transcripts, narration text, SOP
excerpts) and receive a draft of multichoice quiz questions in seconds —
then approve, edit or reject each question before any of them reach
learners.

Several architectural questions need locking in before a line of MVP code
ships:

1. **Which model?** — Sonnet vs Haiku vs Opus.
2. **Where does the prompt live?** — Hard-coded in PHP, in a settings
   table, or in a versioned file.
3. **How do we gate cost?** — Free-fire vs per-call confirmation vs admin
   quota vs all three.
4. **How do we prevent abuse?** — A 4,000-word source x 30 questions x
   1,000 trainers x Sonnet pricing adds up fast.
5. **How do we keep the MVP demoable without spending money?** — Critical
   for the Day-0 build cadence where Anthropic budgets aren't yet allocated.
6. **What's the contract between Claude and the parser?** — Free-form vs
   strict JSON.
7. **How does it integrate with the existing feature-flag + multi-tenant
   plumbing?**
8. **How do we keep tests free of network dependencies?**

---

## Decisions

### D1 — Model: `claude-sonnet-4-6` as default, configurable

Sonnet hits the right tradeoff for quiz authoring:
- Smart enough to read several pages of compliance text and produce
  factually grounded distractors.
- Fast enough that a trainer doesn't perceive a stall (~5-10 sec for 10
  questions).
- ~5x cheaper than Opus for output that does not visibly improve on Sonnet
  for this task.

Haiku produces noticeably worse distractors and occasionally invents facts
not in the source. We reject it for the default but keep the model field
configurable (per draft + as an admin default) so Haiku can be selected
for short / cheap exploratory runs by trainers themselves.

### D2 — Prompts: versioned in code (`prompt_builder::VERSION`)

Two options were considered:

- **A: Prompts in a DB table.** Lets admins tweak the prompt without
  shipping code. Rejected because (a) wording the system prompt is a
  full-time prompt-engineering exercise that requires off-platform A/B
  evaluation, and (b) versioning prompts in code means every prompt change
  is recorded in git history with the exact commit that introduced it.

- **B: Prompts in a versioned PHP class.** Chosen. Each prompt revision
  bumps `prompt_builder::VERSION` (currently `v1`). Every draft row carries
  its `prompt_version` so a reviewer can tell which prompt produced which
  questions. Phase G.1 introduces `v2-hindi` for Hindi quiz generation as
  the second supported version; both will coexist (different versions
  routed by user language preference).

### D3 — Cost gating: 4-layer defence

Each layer blocks a different abuse vector. All four ship in Phase G.0.

1. **Master feature flag.** `sentientia.aiquiz.enabled` defaults OFF. No
   route loads while this is OFF. Per CLAUDE.md §13 mandate.

2. **Live-API feature flag.** `sentientia.aiquiz.live_api` defaults OFF.
   Even with master flag ON, the actual Anthropic POST is gated behind
   this second flag. When OFF, `anthropic_client::call_mock()` runs and
   returns a deterministic 10-question fake response. This means the MVP
   is end-to-end demo-able with the master flag ON and the live-API flag
   OFF — zero spend, but every code path (UI, parser, DB persistence,
   review queue, status lifecycle) is exercised.

3. **Per-call [CONFIRM] gate.** A checkbox in the generate form titled "I
   confirm I want to call Anthropic with this content". Submission rejects
   if unticked. This satisfies the CLAUDE.md §3 absolute rule "ALWAYS
   confirm first ([CONFIRM]) ... POST to ElevenLabs / Gamma / Anthropic
   (costs money)". The gate is at the UI layer because it's a per-action
   decision by a human; the client-layer code (`anthropic_client::call_live`)
   doesn't second-guess it.

4. **Per-user daily token cap.** `daily_token_cap` admin setting
   (default 500,000 tokens / day). `draft_manager::tokens_used_today()`
   sums tokens_in + tokens_out across all drafts the user created since
   00:00 local; generate.php refuses to even create the pending row if
   the user is over cap. Soft cap = predictable monthly bill.

### D4 — Abuse prevention: PII heuristic + word cap + ownership scope

- **PII heuristic.** `prompt_builder::contains_pii_pattern()` rejects any
  source containing an Aadhaar pattern (12 contiguous digits, optionally
  with spaces) or a PAN pattern (5+4+1 alphanumeric). Not a substitute for
  a real DLP scan, but catches the two highest-volume India-specific PII
  leaks. Trainers paste from internal docs all the time; this is the
  difference between a 60-second mistake and a GDPR/DPDP incident.

- **Word cap.** Default 4,000 words per source (~8 pages); admin
  configurable. Hard-coded ceiling protects against a runaway
  "Generate quiz from the entire employee handbook" submission.

- **Ownership scope.** `draft_manager::load_for_actor()` refuses to return
  a draft owned by a different tenant unless the caller has the
  `:manage_all` capability. So a Public-tenant trainer can't review (or
  approve, or push) an Airpay-tenant draft via URL guessing.

### D5 — Demo-able without money: deterministic mock client

`anthropic_client::call_mock()` returns a fully-shaped Anthropic-style
response with N questions every time. The mock embeds the first 80 chars
of the source in the question stem so the reviewer can see the input was
read; every stem starts with "[MOCK Qn]" so a reviewer cannot mistake the
mock output for real content even if they push past the review gate.

When `sentientia.aiquiz.live_api` is OFF, the entire generate → parse →
persist → review pipeline runs against the mock. This is what makes the
MVP end-to-end demoable on day 1 without an Anthropic budget approval.

### D6 — Claude/parser contract: strict JSON, parser drops malformed

The system prompt instructs Claude to return ONLY a JSON object with one
top-level `questions` key. Each question item has 5 fields. The parser:

- Strips ```json fences if Claude adds them despite the instruction.
- Strictly decodes (no JSON5).
- Validates each item against the schema (4 distinct options, valid answer
  index, qtype = "multichoice", non-empty stem).
- Silently drops malformed items (`debugging()` logs the reason).

The parser NEVER tries to repair output. A malformed question is a
defective question — better to drop it than ship a half-broken quiz
question to a trainer who might rubber-stamp it.

If the parser returns ZERO usable questions, the draft is marked
`status=failed` with `error_detail='parser_no_questions'`. The trainer
sees this on the review page and can re-generate.

### D7 — Multi-tenant + multi-customer plumbing: reuse Sentientia LMS conventions

- Every draft carries `customerid` (hardcoded 1 = Airpay in Phase 1; ready
  for the multi-customer layer per ADR-002).
- Every draft carries `costcenterid` derived from the creator's
  `open_path` at creation time (NOT `open_costcenterid` — per CLAUDE.md
  §2 production-compat note).
- The Switchboard feature-flag UI auto-picks up the three new flags
  (`sentientia.aiquiz.enabled`, `sentientia.aiquiz.live_api`,
  `sentientia.aiquiz.auto_push`) because `local_airpay_core\feature_flags`
  walks every plugin's `db/feature_flags.php` on registry build.

### D8 — Tests: mock-only, network-free

PHPUnit tests cover `prompt_builder`, `response_parser`,
`draft_manager`, and `anthropic_client::call_mock()`. The live-API
branch is only tested for its no-API-key fast-fail; a real `curl_exec` is
out of scope for unit tests. Integration smoke for the live path is left
to a separate runbook (manual one-shot with the [CONFIRM] gate ticked).

---

## Consequences

### Positive

- MVP is demoable on day-1 without spending Anthropic money — the entire
  pipeline runs through `call_mock()`.
- Cost is bounded by four independent layers; an attacker bypassing one
  still hits the others.
- Prompt history is reproducible — every draft records the exact
  `prompt_version` that produced it.
- Multi-tenant isolation matches the rest of Sentientia LMS — no special
  case.
- The 4-key contract with Claude (qtype, qtext, qoptions, qanswer_index)
  is the same shape used by the existing `local_airpay_evaluation`
  multichoice question type — push-to-mod_quiz in Phase G.4 lines up
  naturally.

### Negative

- Mock mode produces obviously fake questions. Trainers running the MVP
  in mock mode get to see the UX but cannot evaluate prompt quality —
  that requires flipping `sentientia.aiquiz.live_api` to ON with real
  budget approval.
- PII heuristic catches Aadhaar + PAN only. Names, emails, employee IDs
  inside source text are NOT blocked — the trainer is responsible for
  redacting these. Phase G.2 (PDF upload) will need a real DLP scan.
- Push-to-mod_quiz is stubbed in G.0. The "Push approved questions to
  course quiz" button is wired but creates `pushed_quizid=0` until G.4
  ships the actual mod_quiz creation pipeline.
- Single-language prompts in G.0 — Hindi quiz generation lands in G.1
  with `prompt_version='v2-hindi'`.

### Reversibility

**High.** Anthropic is swappable for another LLM provider in one file
(`anthropic_client.php`). The prompt builder + parser stay unchanged
because every modern LLM API accepts a system prompt + messages array
and returns text. The DB schema is provider-agnostic (records `model`
as a free-text string).

---

## Implementation notes

### File layout

```
local/sentientia_aiquiz/
├── version.php
├── lib.php                 — navigation hook
├── settings.php            — admin settings (api_key, default_model, caps)
├── generate.php            — Course Author form + [CONFIRM] gate
├── review.php              — Reviewer queue + per-question approve/edit/reject
├── db/
│   ├── install.xml         — 2 tables: draft + question
│   ├── access.php          — 3 caps: generate, review, manage_all
│   └── feature_flags.php   — 3 flags: enabled, live_api, auto_push
├── classes/
│   ├── anthropic_client.php   — call_mock + call_live dispatchers
│   ├── prompt_builder.php     — versioned system prompt + validation
│   ├── response_parser.php    — strict JSON parser, drops malformed
│   ├── draft_manager.php      — persistence + status lifecycle
│   └── privacy/provider.php   — GDPR/DPDP metadata + export + delete
├── lang/
│   ├── en/local_sentientia_aiquiz.php   — 80+ keys
│   └── hi/local_sentientia_aiquiz.php   — same 80+ keys (100% parity)
└── tests/
    ├── prompt_builder_test.php
    ├── response_parser_test.php
    ├── draft_manager_test.php
    └── anthropic_client_test.php
```

### Status state machine — draft

```
              ┌────────────────┐
   POST       │  pending       │  Created by generate.php BEFORE API call
   submit ───▶│  (audit row,   │  so a crash mid-call leaves a trace.
              │   no questions)│
              └────┬───────────┘
                   │
        Claude responds + parser yields N ≥ 1
                   │
                   ▼
              ┌────────────────┐
              │  generated     │  Awaiting review
              └────┬───────────┘
                   │
       finalise_review() sees ≥ 1 approved/edited
                   │
                   ▼
              ┌────────────────┐                  ┌────────────────┐
              │  approved      │                  │  rejected      │
              │  (ready push)  │                  │  (all dropped) │
              └────┬───────────┘                  └────────────────┘
                   │
       sentientia.aiquiz.auto_push = ON
                   │ + push button clicked
                   ▼
              ┌────────────────┐
              │  pushed        │  pushed_quizid = mod_quiz.id
              └────────────────┘

       Anthropic call returns mode=failed at any point above
                   │
                   ▼
              ┌────────────────┐
              │  failed        │  error_detail describes the failure
              └────────────────┘
```

### Phase G.0 deferrals

- Real mod_quiz creation (status `pushed` currently sets `pushed_quizid=0`).
- PDF upload pipeline (G.2).
- Hindi quiz generation (G.1).
- Cost analytics dashboard (G.3).
- Two-person review enforcement.
- A/B prompt evaluation.

---

## Verification gates

Before any future "promote G.0 to production" decision:

1. ✅ `php admin/cli/upgrade.php` installs cleanly.
2. ✅ All 4 PHPUnit test files pass.
3. ✅ Mock-mode end-to-end demo: paste source → generate → see mock
   questions → approve/edit/reject → finalise → see status flip.
4. ✅ Hindi parity verified (every EN key has an HI key).
5. ⏳ Live-API end-to-end smoke — manual, one-shot, [CONFIRM] gate
   exercised, real budget consumed. Out of scope for G.0; gates G.1
   promotion.
6. ⏳ Switchboard UI shows the three new flags and toggles them
   correctly. Visual verification deferred to next Chrome MCP reconnect.

---

## Links

- CLAUDE.md §3 — [CONFIRM] gate mandate
- CLAUDE.md §13 — feature flag mandate
- ADR-001 — Sentientia LMS fork strategy
- ADR-002 — Customer-level feature flags
- Tier 1 #4 in PROJECT-STATE.md
- Plugin: `moodle-enhancement/local/sentientia_aiquiz/`
- State card: `moodle-enhancement/state-cards/local_sentientia_aiquiz-state.md`

---

## Addendum — Phase G.1 (2026-05-25): Hindi + per-customer prompt overrides

- **Status:** Accepted
- **Supersedes:** nothing — purely additive to the G.0 decisions above.
- **Scope:** Hindi quiz generation (`prompt_version='v2-hindi'`) and a
  per-customer prompt-template override. Plugin bumped to
  `0.2.0-alpha` (version `2026052500`). `local_airpay_core` bumped to
  `1.6.0` (version `2026052500`) for the new config registry.

### G.1-D1 — Prompt versioning: two baselines + a custom layer

Phase G.0's D2 decided prompts live versioned-in-code. G.1 realises the
`v2-hindi` version promised there and adds a customer-override layer on
top. The system now resolves a **(version, template)** pair for every
generation:

| Layer | Source | Wins when |
|-------|--------|-----------|
| Custom template | `customer::get_customer_config('aiquiz_prompt_template', $customerid)` | A non-empty admin-pasted template exists for the customer |
| `v2-hindi` baseline | `prompt_builder::system_prompt_v2_hindi()` | UI locale resolves to `hi` and no custom template |
| `v1` baseline | `prompt_builder::system_prompt_v1()` | otherwise (English / unknown locale) — the safe default |

Key design points:

- **Locale → version** is computed by `prompt_builder::version_for_locale()`
  (first two chars of the locale; `hi*` → `v2-hindi`, everything else →
  `v1`). Unknown locales fall back to English so a future locale code
  never produces an empty prompt.
- **The custom template replaces only the *system* prompt body.** The
  *user-message* wrapper (the "exactly N questions" sentence + the
  begin/end source markers) still follows the locale-derived version, so
  a Hindi run keeps Hindi framing around the source even when the
  customer pasted their own system prompt. This keeps the source-framing
  contract (what the parser depends on) stable regardless of customer
  edits.
- **The few-shot example.** `v2-hindi` embeds one worked Devanagari
  question as a JSON literal inside the system prompt, giving Claude a
  concrete shape to match. `v1` relies on the field-by-field spec alone
  (unchanged from G.0).
- **Technical proper nouns stay in Latin** inside the Hindi prompt
  (`JSON`, `qoptions`, `qanswer_index`, `multichoice`, `Aadhaar`, `PAN`,
  `Anthropic`, `SCORM`) — they are API field names and regulatory
  identifiers, not translatable terms. This matches the plugin's existing
  Hindi-pack convention.

### G.1-D2 — `prompt_version` recording: the `custom:` prefix

The draft row's `prompt_version` column (CHAR(32), defined in G.0)
records the **resolved** version so the review UI and any future
cost/quality analytics can tell which prompt produced which questions:

| Recorded value | Meaning |
|----------------|---------|
| `v1` | Stock English |
| `v2-hindi` | Stock Hindi |
| `custom:v1` | Admin template active, English user-message wrapper |
| `custom:v2-hindi` | Admin template active, Hindi user-message wrapper |

`prompt_builder::resolve_prompt_version($version, $customused)` is the
single source of truth for this string. All four forms fit the 32-char
column. The literal template body is *not* snapshotted onto the draft —
it is recoverable via `draft.customerid` + the customer-config value, on
the understanding that editing the template loses exact historical
reproducibility (acceptable for an alpha; a future phase can snapshot the
body if A/B rigour demands it).

### G.1-D3 — Per-customer config registry in `local_airpay_core`

Rather than store the prompt template in the aiquiz plugin's own config,
G.1 introduces a **shared per-customer config registry** on the
`customer` helper:

```php
\local_airpay_core\customer::get_customer_config(string $key, int $customer_id, $default = null);
\local_airpay_core\customer::set_customer_config(string $key, int $customer_id, ?string $value);
```

- **Why in `local_airpay_core`, not the aiquiz plugin?** The multi-customer
  layer is core platform infrastructure (ADR-002 put feature-flag
  customer-scope there). Per-customer *config* is the same class of
  concern; future plugins (per-customer email templates, per-customer
  SCORM defaults, …) reuse the same getter rather than each reinventing a
  storage convention. The signature is deliberately storage-agnostic so
  Phase 2's real customer-config table can swap in underneath without
  touching a single caller — the same forward-compat contract `customer::current()`
  already follows.
- **Storage today:** Moodle `config_plugins` under the `local_airpay_core`
  namespace, keyed `customer_<id>_<key>`. Empty / whitespace values
  resolve to the supplied default (treated as "no override").
- **Admin write path:** `settings.php` in the aiquiz plugin declares a
  `admin_setting_configtextarea` whose name is
  `local_airpay_core/customer_1_aiquiz_prompt_template`. Declaring the
  setting under the `local_airpay_core` namespace (from the aiquiz
  settings page) means the admin form writes to exactly the key the
  getter reads — no bespoke save handler, no drift. Phase 0/1 renders one
  textarea (Airpay = customer 1); when Phase 2 adds real customers,
  `settings.php` loops `customer::known_customers()` and the getter is
  already customer-id-agnostic.

### G.1-D4 — Devanagari correctness in the parser

`response_parser` validated text lengths with `strlen()` in G.0. A
Devanagari character is 3 UTF-8 bytes, so a 200-character Hindi stem is
~600 bytes — `strlen()` would mis-measure it and the 1000-cap truncation
(`substr`) could slice a multi-byte character in half, corrupting the
stored JSON. G.1 swaps every length check to `mb_strlen()` and the
defensive truncation to `mb_substr()`. `MAX_TEXT_LEN` is now a
*character* budget, not a byte budget — the same 1000 limit is now
language-fair. `prompt_builder::word_count()` already used the unicode
(`/u`) split, so word-cap validation was correct for Hindi from G.0; a
regression test now pins that.

`JSON_UNESCAPED_UNICODE` is used on every `json_encode` that may carry
Devanagari (mock body, persisted `qoptions_json`, the live-call payload)
so logs and stored rows hold readable Hindi rather than `\uXXXX` runs.
The parser's `json_decode` handles both raw-UTF-8 and `\uXXXX`-escaped
input, so a model that escapes its output still round-trips (pinned by
`test_parse_devanagari_escaped_unicode_also_decodes`).

### G.1-D5 — Mock mode speaks Hindi

Per G.0's D5, the MVP must be demoable without spend. G.1 extends
`anthropic_client::call_mock()` so that when the resolved version is
`v2-hindi` it emits Devanagari stems / options / explanations (the
`[MOCK` marker stays Latin so a reviewer always spots mock content
regardless of language). The source snippet echoed back into the stem is
sliced with `mb_substr()` so a multibyte source is reflected without
corruption. This makes "generate a quiz in Hindi via the UI" verifiable
end-to-end with `sentientia.aiquiz.live_api` still **OFF**.

### G.1 — [CONFIRM] gate unchanged

The per-call confirmation checkbox (G.0 D3 layer 3) is untouched. No
test in this phase calls `call_live()` past its no-API-key fast-fail; the
live Anthropic POST remains gated behind both the `live_api` flag (default
OFF) and the per-call [CONFIRM] tick. The language picker and prompt
preview are pure pre-submission UI — they never trigger a network call.

### G.1 consequences

**Positive**
- Hindi quiz authoring is demoable today (mock mode), live-ready when the
  flag + budget are approved — no further code needed for the language
  itself.
- The per-customer config registry is reusable platform infrastructure,
  not an aiquiz one-off.
- `prompt_version` audit trail now distinguishes language and customer
  customisation at a glance.

**Negative / deferred**
- The custom template body is not snapshotted per draft (see G.1-D2) —
  exact historical prompt reproduction is lost if an admin edits the
  template later. Acceptable for alpha.
- Only `en` + `hi` are wired. Adding a third language is a new
  `system_prompt_v3_xx()` + a `version_for_locale()` branch + a Hindi-style
  parity pass — not a structural change.
- Phase 0/1 still single-customer in the *admin UI* (one textarea); the
  read path is already multi-customer.

### G.1 verification gates

1. ✅ `php -l` clean on every touched file.
2. ✅ Standalone logic check: version resolution, Hindi system prompt
   (Devanagari + few-shot + Latin field names + PII rule), custom-template
   override + blank fallback, Hindi user-message wrapper, Devanagari word
   count + PII detection.
3. ✅ Standalone mock→parse pipeline: English + Hindi mock both parse;
   Devanagari survives `qoptions_json` round-trip; 400-char Devanagari
   stem accepted under the character-budget cap; unknown version falls
   back to English.
4. ✅ Hindi parity: 125/125 keys (`lang/en` == `lang/hi`).
5. ⏳ Full PHPUnit run on a Moodle 5.x checkout (the new tests assume the
   test DB bootstrap) — runs in CI's `phpunit-5.2` gate.
6. ⏳ Live-API Hindi smoke — manual, one-shot, [CONFIRM] ticked, real
   budget. Still gated; out of scope for this chip.
