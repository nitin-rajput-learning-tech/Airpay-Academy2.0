## 12. Cost model: per course, per language, per tier

**Unit.** One 12-minute course: ≈ 1,500 narration words, ≈ 700 words of slide/scenario/quiz text (≈ 2,200 source words, ≈ 13,000 characters per language), one nine-node scenario, a five-question quiz. Prices: Claude API list prices as of 2026-10-06 (Sonnet 5.5 $2/$10, Sonnet 4.6 $3/$15, Opus 5.5 $4/$20, Haiku 5.5 $0.10/$0.50 per million tokens; cache reads cheaper); USD/INR planning rate ₹87; speech and GPU figures from `sources/R3-tts-asr-infra.md` (list prices captured 2026-10-15). Human rates are planning assumptions for the Indian market and must be replaced by quotes.

### 12.1 Model spend (the small line)

| Step | Input tokens | Output tokens (incl. thinking) | Sonnet 5.5 | Opus 5.5 | Notes |
|---|---|---|---|---|---|
| Generate the SCD from a 2,000-word SOP (slides + scenario + quiz, strict JSON) | ~5k | ~10k | $0.11 ≈ ₹10 | $0.22 ≈ ₹19 | ×3 for two "refine" passes → ₹30–60 |
| Translate one language (segments + protected terms → strict JSON; Indic scripts tokenise ~3× English) | ~4k | ~13k | $0.14 ≈ ₹12 | $0.28 ≈ ₹24 | ×1.5 for QA re-runs → ₹18–36 |
| Translate 13 languages | | | ≈ ₹160 | ≈ ₹320 | ×1.5 → ₹240–480 |
| Scenario alternative branch, question regeneration, feedback polish (per course) | ~3k | ~4k | ≈ ₹4 | ≈ ₹8 | |
| Rubric scoring of one live roleplay session (10 turns, Stage 3) | ~8k | ~2k | $0.04 ≈ ₹3 | $0.07 ≈ ₹6 | plus the roleplay turns themselves ≈ ₹3–6 → **₹6–12 per session** |
| **Model spend per course, 13 languages, Sonnet 5.5** | | | **≈ ₹300–550** | | **≈ ₹25–45 per language** |

Haiku 5.5 is suitable for QA passes (placeholder parity explanations, script-sanity checks) at ≈ 1/20th of the cost; not for translation of compliance content. Prompt caching of the system prompt and glossary reduces input cost further at volume. The gateway's ledger records every call; the per-customer daily caps already exist.

### 12.2 Speech spend (the engineering line)

| Option | Marginal cost per course per language (10 min audio) | Fixed cost | Residency | Source |
|---|---|---|---|---|
| Self-hosted open engine on a shared cloud GPU in Mumbai/Central India | ≈ ₹3–15 (GPU-seconds: 10 min audio at real-time factor 0.2–0.5 ≈ 2–5 GPU-min) | {{R3_GPU_MONTHLY}} per instance-month | in-IN | R3 Part C |
| Self-hosted on an on-prem GPU workstation (RTX-class) | ≈ ₹1–5 (electricity + amortisation) | {{R3_ONPREM_CAPEX}} capex, amortised | on-prem | R3 Part C |
| Self-hosted on CPU only (pilot volume) | ≈ ₹5–20 (slow; fine for < 20 courses/month) | existing servers | on-prem/in-IN | R3 Part A (RTF on CPU) |
| SaaS fallback, India region (Azure/Google/AWS neural voices) | ≈ ₹13–30 (13k chars at ~$15–25 per 1M chars) | none | in-IN region where offered | R3 Part D |
| ElevenLabs (incumbent pilot vendor) | ≈ ₹200–300 (13k chars at ~$0.15–0.30 per 1k chars, plan-dependent) | subscription | not India | R3 Part D |
| ASR round-trip QA + forced alignment (self-hosted Whisper-class) | ≈ ₹2–8 | shares the GPU | in-IN | R3 Part B |

{{R3_COST_MATHS}}

At 50 courses/month across 13 languages (650 narrations/month ≈ 110 audio-hours), the GPU instance cost spread per narration is **≈ ₹40–70** on a reserved cloud instance and **≈ ₹10–20** on-prem; below ~15 courses/month CPU inference or the India-region SaaS fallback is cheaper than a dedicated GPU.

### 12.3 Human review (the big line)

| Activity (per course per language) | Hours | Rate (planning) | Cost |
|---|---|---|---|
| Stage 1 post-editing by a translator (machine draft → translated) | 2–3 | ₹500–900/h (≈ ₹0.8–1.5 per word post-editing) | ₹1,000–2,700 |
| Stage 2 SME review in the player | 1.5–2 | ₹700–1,200/h (internal SME, loaded) | ₹1,050–2,400 |
| Admin sign-off, QA triage, audio spot-listen | 1 | ₹700/h | ₹700 |
| **Two-stage total** | **5–6** | | **₹2,750–5,800** |
| Single-stage (SME only, low-stakes content, per-customer flag) | 2–2.5 | | ₹1,400–3,000 |
| Professional human translation instead of post-editing (regulated text, low-resource language) | | ₹2–5 per word | ₹4,400–11,000 (+ review) |
| TM reuse from the second course in a language (regulatory boilerplate repeats) | −30 to −50% of Stage 1 | | |

### 12.4 Tiers — cost per course per language, all-in

| Tier | What it is | Model | Speech | Review | **Per language** | **13 languages** | When to use |
|---|---|---|---|---|---|---|---|
| **T0 Mock / demo** | Pipeline runs with mock model, silent audio, pseudo-localisation | ₹0 | ₹0 | optional | **₹0** | ₹0 | demos, CI, harness runs (what this study ran) |
| **T1 Lean** | Sonnet draft, self-hosted TTS on shared GPU, single SME review | ₹25 | ₹15–70 | ₹1,400–3,000 | **≈ ₹1,500–3,100** | ≈ ₹20–40k | internal non-regulatory content, product tutorials |
| **T2 Standard (compliance)** | Sonnet draft, self-hosted TTS + ASR QA, two-stage review, audit export | ₹35 | ₹20–80 | ₹2,750–5,800 | **≈ ₹2,800–5,900** | ≈ ₹37–77k | POSH, AML, IT-security, customer-support SOPs — the Airpay default |
| **T3 Premium / fallback** | Opus draft, SaaS premium voice (India region or ElevenLabs), professional translation for named languages, two-stage review | ₹60 | ₹30–300 | ₹5,000–12,000 | **≈ ₹5,100–12,400** | ≈ ₹66–160k | regulator-facing content, Urdu/Arabic until the self-hosted gate passes, languages where the golden set fails |

Reading the table: the model is 1–2% of the cost, speech 1–5%, people 90%+. That is why the plan spends engineering on translation memory, QA automation, reviewer tooling and capacity dashboards, and why languages are enabled by demand. Compared with the SaaS pattern the report describes (per-seat platform + per-character voice + agency translation), the self-hosted stack removes the per-character line and the per-seat AI tax but does not remove the reviewer; no honest competitor does either.

### 12.5 Fixed and one-off costs

| Item | Planning figure | Notes |
|---|---|---|
| GPU instance (cloud, India) | {{R3_GPU_MONTHLY}} / month | one shared instance covers Stage 1–2 volume; reserved pricing −30–40% |
| On-prem GPU workstation | {{R3_ONPREM_CAPEX}} | alternative to cloud; DR = a second box or cloud burst |
| Speech evaluation gate per language | ₹5–15k | 5 native listeners × 1 h honorarium + SME time |
| Golden sets (translation + speech) per language | 4–6 h SME | one-off, reused on every model change |
| Conformance testing (Stage 3) | {{R4_SCORM_CLOUD_PRICE}} | optional; ADL test suites are free |
| Build effort | ~72 Claude sessions across the three stages (§11) | no cash cost beyond the Claude subscription; developer unlocks in §11.2 |

### 12.6 Pilot budget request (for Addendum A)

For the pilot in §13 — one SOP, up to 13 languages, mock then live: model spend ≈ ₹300–550 at Sonnet 5.5 (≈ ₹1,000 with retries and a second SOP); SaaS TTS fallback batch, if chosen, ≈ ₹200–400 for 13 languages on an India-region neural voice or ≈ ₹3,000 on ElevenLabs; human review for Hindi (+ Marathi/Tamil) ≈ 15–20 h. **Recommended cap: ₹10,000 for the pilot month**, which also seeds the Addendum-A figure the memo left blank.

---
