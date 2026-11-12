# DRAFT: AI operating budget and security spend, annex for C-suite approval

**Status:** draft for Nitin to review and take to the C-suite himself. Nothing has been sent or approved.
**Why:** decision C1 of 12 November 2026 (`NITIN-DECISIONS-2026-11-12.md`): the security spend, the AI spend cap and the
live Anthropic API key are pending C-suite approval.
**Drafted:** 13 November 2026, from the engineering record only. Each fact carries its source file in square brackets; the
list is at the end.

**Every rupee figure here is an estimate or a recorded list price, not a quote.** Model prices are the list prices
recorded in the repository as of 6 October 2026, converted at a planning rate of ₹87 per US dollar [C1]. Haiku 4.5, which
that list does not carry, is priced at the gateway's own estimate (US$ 1 / 5), not a recorded list price [G3]. The GPU
rows in B4 keep the speech study's rate of ₹95 per US dollar [R3]. Usage volumes are planning
assumptions, marked as such, to be replaced by the spend ledger's real counts after the first month. Security figures are
the indicative market ranges of the August annex, to be replaced by vendor quotes [S1].

---

## Summary: what is asked

| # | Ask | Recommendation |
|---|---|---|
| 1 | **AI monthly spend ceiling** and the live Anthropic API key (Addendum A of the 4 August memo, still blank [M1]) | Approve a **ceiling of ₹50,000 a month** for every call through the AI gateway (the six features and the content engine). Start at **₹10,000 for the first month** (AI quiz generation first, plus the content-engine pilot), and raise within the ceiling only from the ledger's actual figures. The content engine's production volume is not in the ₹50,000 and comes back for approval before it goes live. Anything above ₹50,000 comes back for approval. |
| 2 | **Self-hosted AI option** (an open model run on our own server in India) | Do not buy hardware now. Approve a **costed evaluation** first: it is a residency and personal-data decision, not a saving, at our volumes (section B). |
| 3 | **Security programme** (₹80 to 120 lakh envelope; August annex lines total ₹55 to 115 lakh (it printed 52 to 109)) | Approve the envelope and release line by line on vendor quotes, as the August annex proposed; re-date its milestones, which have passed [S1][P1]. |
| 4 | **Speech line of the content engine** (separate ask of the 15 October brief) | As in that brief: on-demand India GPU about ₹10,000 to 20,000 a year, listening panels about ₹5,000 to 15,000 per language [B1]. |

---

## A. The AI spend cap

### A1. What the cap controls

`local_sentientia_ai` is the single gateway every Sentientia AI feature calls [G1][G2]:
- **One central API key**, one place to rotate it [G2].
- **A spend ledger:** every call routed through the gateway, live or mock, writes one row (feature, purpose, tokens,
  estimated cost). Prompt and response text is never stored [G1][G2].
- **Fail-closed limits:** tokens per day across all customers, tokens per day per customer, and estimated US dollars per
  month. A limit that is zero or empty blocks live calls; it never means unlimited [G2][G3].
- **Mock first:** live calls need both gateway flags ON (`sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled`).
  Otherwise the features run a mock at zero cost, except the chat assistant, which calls Anthropic directly if a key
  sits in its own setting (A5). Both gateway flags are OFF today, and no live call has ever been made [G1][A1].
- Quiz generation, skills extraction, recommendations, translation and authoring keep their own flags, their per-action
  confirmation screens and their input limits on top. The chat assistant has only its master switch and its rate limit;
  the agent has its own two flags (both OFF) but no confirmation before its paid call [G2][G6][G11].

Today's install defaults are deliberately modest placeholders: 200,000 tokens a day in total, 100,000 a day per customer,
and US$ 25 a month [G4].

### A2. The six features behind it, and what drives their use

All six default to the model `claude-sonnet-4-6`; the chat assistant uses the smaller `claude-haiku-4-5-20251001`
(Haiku 4.5) for simple questions [G5][G6].

| Feature (plugin) | What one call does | Who triggers it | Limits per call |
|---|---|---|---|
| AI quiz generation (`aiquiz`) | Drafts up to 30 questions from a source text | A trainer or manager [G10] | source up to 4,000 words; answer up to 4,096 tokens [G5][G7] |
| Skills extraction (`skillsai`) | Reads a course transcript or SOP and lists up to 25 skills | A trainer or manager [G10] | source up to 6,000 words; answer up to 4,096 tokens [G5][G7] |
| Course recommendations (`recommendations`) | Ranks up to 100 candidate courses against up to 50 completed courses from the learner's history, returns up to 10 | A manager, per batch, with confirmation; no scheduled job is built [G8] | answer up to 2,048 tokens [G5][G7] |
| Content translation (`translate`) | Translates one text into one language | A manager [G10] | source up to 4,000 words; answer up to 8,192 tokens [G5][G7] |
| Authoring studio (`authoring`) | Generates a course draft (up to 12 cards, 20 questions) from a document | A trainer or manager [G10] | source up to 4,000 words; answer up to 8,192 tokens [G5][G7] |
| Assistant (`assistant`) | Chat: answers a learner's question. Agent: proposes an action (enrol, book a session, recommend) | Any learner | chat answer up to 1,024 tokens, **at most 20 questions per learner per day**, common answers cached for an hour; agent answer up to 1,024 tokens, no daily limit of its own [G5][G6][G11] |

The biggest driver is the assistant: it is the only feature every learner can trigger, and the platform has about 2,888
active users across its three tenants [P2].

### A3. Prices and the formula

Recorded list prices per million tokens, input / output [C1]: Sonnet 4.6 US$ 3 / 15; Sonnet 5.5 US$ 2 / 10; Opus 5.5
US$ 4 / 20; Haiku 5.5 US$ 0.10 / 0.50. The gateway's own estimate table holds Sonnet 4.6 at 3 / 15 and Haiku 4.5 at
1 / 5, and prices any model it does not know at the top tier, US$ 15 / 75 [G3]. The repository records no list price
for Haiku 4.5, so this annex uses the gateway's estimate for it.

**A mismatch to fix first.** The gateway looks a model up by its exact name, and the assistant asks for
`claude-haiku-4-5-20251001`, which is not in the table [G3][G6]. So today every simple chat would be costed at US$ 15 / 75:
about ₹8.64 a message instead of ₹0.58, or about ₹7.26 on the 80/20 mix of A4 instead of ₹0.81. At the expected volume of
A4, chat alone would count about ₹36,300 against the cap and the total about ₹43,000, and the ₹10,000 month-1 setting
would trip early. This changes what the ledger counts against the cap, not what Anthropic bills. The figures below assume
the table is corrected first (A5).

**Cost of one call (₹) = (input tokens × input price + output tokens × output price) ÷ 1,000,000 × 87.**

Each call below is priced at its **ceiling**: the longest source the feature accepts and the longest answer it allows.
Real calls will usually cost less. Input tokens are taken as 2.5 per source word, the ratio of the repository's cost model
(a 2,000-word SOP is about 5,000 input tokens) [C1].

| Call | Model | Input tokens | Output tokens | Ceiling per call |
|---|---|---|---|---|
| Quiz generation | Sonnet 4.6 | 10,000 | 4,096 | ₹7.96 |
| Skills extraction | Sonnet 4.6 | 15,000 | 4,096 | ₹9.26 |
| Recommendations | Sonnet 4.6 | 7,000 *(assumption)* | 2,048 | ₹4.50 |
| Translation | Sonnet 4.6 | 10,000 | 8,192 | ₹13.30 |
| Course generation | Sonnet 4.6 | 10,000 | 8,192 | ₹13.30 |
| Assistant chat, simple | Haiku 4.5 | 1,500 *(assumption)* | 1,024 | ₹0.58 |
| Assistant chat, complex | Sonnet 4.6 | 1,500 *(assumption)* | 1,024 | ₹1.73 |
| Assistant agent | Sonnet 4.6 | 3,000 *(assumption)* | 1,024 | ₹2.12 |

### A4. Monthly estimate per feature

**Volumes are planning assumptions** for the airpay.academy deployment (three tenants), not measurements. Chat is taken as 80% simple and 20% complex
(₹0.81 a message on average).

| Feature | Calls a month: low / expected / high | ₹ a month: low | expected | high |
|---|---|---|---|---|
| Quiz generation | 20 / 60 / 200 | 160 | 480 | 1,590 |
| Skills extraction | 10 / 40 / 150 | 90 | 370 | 1,390 |
| Recommendations | 100 / 500 / 2,900 (one batch per active user) | 450 | 2,250 | 13,050 |
| Translation | 20 / 100 / 400 | 270 | 1,330 | 5,320 |
| Course generation | 10 / 30 / 100 | 130 | 400 | 1,330 |
| Assistant chat | 1,000 / 5,000 / 20,000 messages | 810 | 4,030 | 16,130 |
| Assistant agent | 200 / 1,000 / 4,000 | 420 | 2,120 | 8,480 |
| **Total** | | **≈ ₹2,300** | **≈ ₹11,000** | **≈ ₹47,300** |

**Not in this table: the content engine.** Its generation and translation calls go through the same gateway and count
against the same monthly cap [A3][B1]. Its pilot (model spend about ₹300 to 550) is funded inside the month-1 ₹10,000
[B1][C1]. Its production volume, at about ₹25 to 45 of model spend per course-language [B1][C1], comes back for approval
before it goes live.

For scale, without any limit: if every active user asked the assistant its daily maximum of 20 questions on 22 working days,
that is about 12.7 lakh messages, or about ₹10 lakh a month at ₹0.81 each. The cap exists to make that impossible.

### A5. Recommendation

1. **Approve a ceiling of ₹50,000 a month** for every call through the gateway (just above the high case of A4, which
   covers the six features; the content engine's production volume comes back separately, see A4). In the gateway's
   setting, which is in estimated US dollars: 575.
2. **Month 1: ₹10,000** (setting: 115). This is the figure the 15 October content-engine brief already proposed for its
   pilot month (one SOP, up to 13 languages; model spend about ₹300 to 550) [B1][C1]. It also covers AI quiz generation,
   the first feature to go live under the roadmap [R1].
3. **From month 2:** raise within the ceiling only from the ledger's actual figures, one feature at a time as each passes
   its gates [R1][M1].
4. **Conditions that go with the money** (engineering, no cost):
   - The key goes **only into the gateway's central setting**, and gateway routing is switched on first. The chat
     assistant has no live-API flag of its own: with routing off, a key placed in its own setting would call Anthropic
     directly, outside the ledger and the limits [G6].
   - **To start with quiz generation alone,** switch `ai.assistant.enabled` OFF (it defaults ON) before the gateway's
     live flags go ON; otherwise chat goes live for every learner at the same moment and its spend counts against the
     month-1 ₹10,000 [G6].
   - **Raise the daily token limits with the money.** At the expected volumes the six features use about 25 million tokens
     a month, about 1.1 million a working day; today's limit for one customer is 100,000 a day [G4]. The 13-language pilot
     alone uses about 3.8 lakh tokens [C1].
   - **Correct the gateway's price table before the key goes in.** The assistant asks for `claude-haiku-4-5-20251001`,
     which is not in the gateway's price table, so today every simple chat is costed at US$ 15 / 75, about 15 times the
     gateway's own Haiku estimate. Add that model id to the table before the key goes in, and keep the table in step
     with any model change (a move to Sonnet 5.5 would trip the cap about 7.5 times too early) [G3][G6][C1]. The code
     fix belongs to the `sentientia_ai` and `sentientia_assistant` track, in both plugin trees.
   - Every paid call of quiz generation, skills extraction, recommendations, translation and course generation still
     needs a person's confirmation [G2]. The assistant's chat and agent calls do not: any learner triggers them, limited
     by 20 chat questions a day, the one-hour cache and the gateway limits; the agent's paid call runs before the
     learner confirms anything, and that confirmation covers only the write action [G6][G11].

---

## B. Self-hosted option: an open model on our own server in India

### B1. What already exists

- Moodle's own AI layer can use several providers, Ollama among them [V1]. The Moodle 5.3 upgrade on UAT added Moodle's
  Anthropic provider and an AI-usage report [U1].
- The assistant already has a bridge to Moodle's AI layer, behind a provider switch [G1][A2]. Its chat could therefore be
  pointed at a self-hosted model mostly by configuration; the evaluation (B5) has to confirm that.
- The gateway calls one endpoint, Anthropic's [G3]. Serving the other five features from a self-hosted model needs a
  second backend in the gateway. **It is not built**; its size is not estimated in the repository: **[__ engineering days]**.

### B2. What it can and cannot do for these six features

This is engineering's assessment, to be proven by the gateway's golden-set tests before any learner sees the output, the
same rule the content engine applies before it changes model [G2][A3].

| Feature | Fit for a self-hosted model | Why |
|---|---|---|
| Assistant chat | **Good** | Short answers (1,024 tokens). It is the one feature that sends a learner's personal data by design: name, designation, organisation, enrolled courses with due dates, certificates, team size [G6]. Keeping it in India answers the residency question directly. |
| Course recommendations | **Good** | Ranks and explains; short output. Sends the learner's course history [G7]. |
| Skills extraction | Possible | Structured list from a text; needs testing. |
| Quiz generation | Possible, for drafts | A person reviews every draft before it is used [G9]. |
| Translation | **Weak** | 11 Indian languages plus Urdu and Arabic (13 in all), with English and Swahili, compliance content [A3]. The cost model rates even the vendor's small model "not for translation of compliance content" [C1]. Keep on Claude. |
| Course generation | **Weak** | Long, strictly structured output (8,192 tokens). Keep on Claude. |

A hybrid is the sensible shape: self-hosted for chat and recommendations, Claude for authoring and translation. The
content engine already plans the same pattern for speech [A3].

### B3. Trade-offs

- **For:** learner data stays in India; no per-token bill for the moved features; one fewer outside processor for them.
- **Against:** a fixed monthly cost whether used or not; a chat model must run around the clock, unlike the speech jobs,
  which run in batches; capacity limits how many learners can chat at once; we patch, monitor and update it ourselves;
  answer quality is unproven until tested; engineering time is the real cost, as the speech study found [C1][A3].

### B4. Cost structure

**Monthly cost = server + operations time + (one-off build ÷ months of use).**

The repository has no sizing for a language model, so the server line is a blank to be filled by IT or a vendor:
**[GPU class ___], [₹___ a month]**. For reference, the India GPU prices recorded for the speech study (list prices,
captured 15 October 2026, several marked unverified) [R3][A3]. These rows convert at the speech study's ₹95 per US
dollar; at this annex's ₹87 the two AWS rows are about ₹36,800 and ₹61,400 a month:

| Server, run 24×7 (730 hours a month) | Recorded price | Monthly |
|---|---|---|
| AWS Mumbai, 1 × T4 16 GB (g4dn.xlarge) | US$ 0.579 an hour, ≈ ₹55 | ≈ ₹40,000 [R3][C1] |
| AWS Mumbai, 1 × L4 24 GB (g6.xlarge) | US$ 0.9664 an hour, ≈ ₹92 | ≈ ₹67,000 |
| E2E Networks, 1 × L4 (vendor marketing) | ₹50 to 70 an hour, before GST | ≈ ₹36,500 to 51,100 + 18% GST |
| On-premises workstation, RTX 4090 class 24 GB | ≈ ₹3.0 to 3.5 lakh to buy (card prices conflict) | ≈ ₹8,900 a month over 36 months, plus power; consumer-driver licence caveat unverified |

Operations: **[__ hours a month × ₹__ an hour]**. Build: **[__ engineering days]**.

**Break-even on cost alone:** at the expected volumes, chat, recommendations and the agent cost about ₹8,400 a month on
Claude (section A4). A ₹40,000-a-month server (about ₹36,800 at ₹87) pays for itself only above about 45,000 to 50,000
chat messages a month (₹36,800 to 40,000 ÷ ₹0.81). So, as with speech, **the case for self-hosting is residency and control of personal data, not saving money** [C1].

### B5. Recommendation

Approve a time-boxed evaluation, not hardware: point the assistant's chat at a test model through Moodle's AI layer on a
local or UAT box with test accounts only, run the golden-set tests, and come back with the server size and a real monthly
figure. Fill the blanks above from that. If the speech line later needs a standing GPU (live roleplay), one server could
serve both [C1].

---

## C. Security programme: items pending approval

From the August annex (indicative ranges, each line released only on an approved vendor quote, at least two quotes per
line) [S1]:

| # | Item | ₹ lakh (indicative) | Produces |
|---|---|---|---|
| 1 | Penetration test (VAPT) by a CERT-In empanelled vendor, with one retest | 8 to 18 | VAPT report and closure certificate |
| 2a | ISO 27001:2022 gap closure (consultant) | 8 to 15 | Auditable ISMS, incident-response plan |
| 2b | ISO 27001:2022 certification | 4 to 8, plus 3 to 6 for ISMS tooling | Stage 1 report, then the certificate |
| 3 | SOC 2 Type I | 20 to 40 | SOC 2 Type I report |
| 4 | 25,000-user load test | 4 to 10 | Published scale proof |
| 5 | Security operations: WAF, log retention, monitoring (first year) | 3 to 8 | The controls the audits test |
| 6 | Contingency and retest reserve (about 10%) | 5 to 10 | |
| | **Total** | **55 to 115** (the August annex printed 52 to 109, which leaves out the ISMS tooling line), inside the ₹80 to 120 lakh envelope | |

Points to settle with this approval:
- **The dates have passed.** The annex planned the VAPT vendor for September and the load test for November 2026; the
  task plan already records the 30 September VAPT milestone as at risk and asks for the lapsed milestones to be re-dated
  [S1][P1].
- **SOC 2 Type I or Type II.** The annex budgets Type I and leaves Type II out for a later approval [S1]; the August memo
  records the full roadmap "including SOC 2 Type II" [M1]. The C-suite should say which. With every line at the top of
  its range the total is ₹115 lakh, so only about ₹5 lakh of the ₹120 lakh envelope is left for Type II; the annex's
  "headroom for SOC 2 Type II" rested on its 109 figure [S1].
- **No-cost items that go with it:** close the two named ISO 27001 gaps (key management, backup procedures) with one
  evidenced backup-restore including the file store and a written RPO/RTO, and name the Compliance Officer, CISO, DevOps
  owner and incident commander [P1]. The Stage B rehearsal, which restores a live backup with its file store and checks
  every file, could serve as that evidenced restore [N1][K1].
- **Outside this envelope:** the AI budget (this annex), server sizing, the native mobile app, and SOC 2 Type II [S1].

---

## D. What waits for approval, and what does not

| Waits for approval | Goes ahead without it |
|---|---|
| Any live AI call, for any of the six features (they stay in mock mode) [G1][N1] | The gateway, ledger, limits and golden-set tests, all at zero spend [G2] |
| The content-engine live pilot and its self-hosted speech jobs [N1][B1] | Content-engine Stage 1: schema, tables and compiler, local only, AI in mock mode, flags OFF [N1] |
| Any self-hosted AI server | The self-hosted evaluation on test accounts, if approved as a time-box (B5) |
| VAPT, ISO consultant and audits, SOC 2, the 25,000-user load test [S1] | Drafting the incident-response plan and ISMS policies in-house; the deterministic skills-gap features, which need no AI spend [M1] |
| | Stage B and the cutover: they wait for IT (backup and the 5.3 server), not for these budgets [N1] |

---

## Sources

| Ref | File |
|---|---|
| [A1] | `moodle-enhancement/docs/adr/ADR-034-content-pipeline-architecture.md` Context (no live call ever made; Addendum-A cap and key unset) |
| [A2] | `moodle-enhancement/docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` (assistant's `core_ai` bridge to Moodle's provider-agnostic AI layer) |
| [A3] | `moodle-enhancement/docs/adr/ADR-034-content-pipeline-architecture.md` D4 (languages; model changes only after the golden-set eval), D5 (self-hosted speech, hybrid with fallback, GPU sizing, engineering time is the real cost), D6 (residency) |
| [B1] | `moodle-enhancement/docs/business/SENTIENTIA-CONTENT-ENGINE-CSUITE-BRIEF-2026-10-15.md` (₹10,000 pilot month; speech line ₹10,000 to 20,000 a year; panels ₹5,000 to 15,000 per language) |
| [C1] | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/study-src/study-sections/12-cost-model.md` (list prices as of 2026-10-06; ₹87 planning rate; token counts per step; Haiku-class not for compliance translation; speech conclusion; standing T4 ≈ ₹40k a month; pilot cap ₹10,000) |
| [G1] | `moodle-enhancement/state-cards/sentientia_ai-state.md` (gateway, flags OFF, six consumers routed, privacy provider) |
| [G2] | `moodle-enhancement/local/sentientia_ai/README.md` (contract, fail-closed quotas, ledger, per-feature gates stay) |
| [G3] | `moodle-enhancement/local/sentientia_ai/classes/gateway.php` (`ENDPOINT`, `PRICING_PER_MTOK` with the key `claude-haiku-4-5` only, `estimate_cost()` exact-name lookup with the `default` 15 / 75 fallback, `check_quotas()` counting every gateway call through `ledger::cost_this_month()`, `HARD_MAX_TOKENS` 8192) |
| [G4] | `moodle-enhancement/local/sentientia_ai/settings.php` (defaults 200,000 / 100,000 tokens a day, US$ 25 a month, "modest placeholders"); `classes/ledger.php` (daily tokens = input + output) |
| [G5] | `DEFAULT_MODEL` and `MAX_OUTPUT_TOKENS` in `moodle-enhancement/local/sentientia_{aiquiz,skillsai,recommendations,translate}/classes/anthropic_client.php`, `sentientia_authoring/classes/course_generator.php`, `sentientia_assistant/classes/agent/agent_client.php` |
| [G6] | `moodle-enhancement/local/sentientia_assistant/classes/ai_client.php` (20 a day, 1-hour cache, `MODEL_FAST` `claude-haiku-4-5-20251001` / `MODEL_SMART` `claude-sonnet-4-6`, no confirmation step, `build_context()` contents, no own live flag, direct call with its own key when routing is off); `local/sentientia_platform/db/feature_flags.php` (`ai.assistant.enabled` default ON) |
| [G7] | `prompt_builder.php` limits in the same plugins (30 questions; 25 skills; 50 history items, 100 candidates, 10 picks; 12 cards, 20 questions) and `max_source_words` defaults in their `settings.php` (aiquiz, translate, authoring 4,000; skillsai 6,000) |
| [G8] | `moodle-enhancement/local/sentientia_recommendations/db/feature_flags.php` (manager-triggered, per-call confirmation; `auto_cron` flag) and the plugin's `db/` folder (no `tasks.php`) |
| [G9] | `moodle-enhancement/state-cards/sentientia_aiquiz-state.md` (draft statuses pending, generated, approved) |
| [G10] | `db/access.php` of `sentientia_aiquiz` (`:generate`), `sentientia_skillsai` (`:extract`), `sentientia_authoring` (`:generate`): editingteacher, teacher, manager; `sentientia_translate` (`:translate`): manager |
| [G11] | `moodle-enhancement/local/sentientia_assistant/classes/agent/agent_loop.php` (step 3 calls the model before step 6, where only a write action waits for the learner's confirmation); `classes/external_agent.php` (no daily limit; `useagent` capability, granted to all learners per `db/access.php`); `db/feature_flags.php` (`sentientia.assistant.agentic.enabled` and `.live_api`, default OFF) |
| [K1] | `tools/rehearsal/README.md` (step 01 restores the moodledata, the file store gate, the SHA-1 read of every `filedir/` file) |
| [M1] | `moodle-enhancement/docs/business/DECISION-MEMO-2026-08-04.md` (Q1 deterministic skills work needs no AI spend; Q3(b) full roadmap ₹80 to 120 lakh incl. SOC 2 Type II; Addendum A (b), cap TBD, key pending) |
| [N1] | `moodle-enhancement/docs/cutover/NITIN-DECISIONS-2026-11-12.md` (A1 Stage 1 local and mock; speech and live calls wait for C1; [CONFIRM] per paid call; C1; C3) |
| [P1] | `moodle-enhancement/docs/cutover/PENDING-TASK-PLAN-2026-09-21.md` 4.1 (VAPT milestone at risk, re-date, ISO gaps, owners) and line 132 (envelope approval) |
| [P2] | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` s.3.1 (2,888 active users in the rehearsal baseline) |
| [R1] | `moodle-enhancement/docs/adr/ADR-028-reconciled-product-roadmap.md` Phase 2.3 (gateway first, then feature by feature, aiquiz first) |
| [R3] | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/sources/R3-tts-asr-infra.md` Part C (India GPU list prices at ₹95/USD; on-prem capex; 24×7 g4dn ≈ ₹40k a month) |
| [S1] | `moodle-enhancement/docs/business/SECURITY-SPEND-PLAN-ANNEX-2026-08.md` |
| [U1] | `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` (new `aiprovider_anthropic`, `report_aiusage`) |
| [V1] | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/sources/R2a-vendors-deep-set.md` (Moodle's AI providers include Ollama) |

**Arithmetic, not repository statements:** every per-call and monthly rupee figure in A3, A4 and A5, including the
₹8.64, ₹7.26, ₹36,300 and ₹43,000 at US$ 15 / 75; the 12.7 lakh messages and ₹10 lakh in A4; the 2.5 tokens per word
ratio and the 3.8 lakh pilot tokens (both derived from [C1]); the 25 million and 1.1 million token figures; the 7.5 times
(US$ 15 / 75 against US$ 2 / 10) and the 15 times (against US$ 1 / 5); the monthly figures for the L4 and E2E rows (from
[R3] hourly prices × 730 hours) and the ₹87 equivalents in B4; the break-even; the ₹55 to 115 lakh security total (the sum
of the August annex's own lines) and the ₹5 lakh left at the top of the envelope. **Assumptions:** the call volumes in A4, the 80/20
chat split, and the input tokens marked *(assumption)* in A3. **Recommendations, not repository decisions:** the
₹50,000 ceiling, the month-2 rule, the fit ratings in B2 and the evaluation in B5.
