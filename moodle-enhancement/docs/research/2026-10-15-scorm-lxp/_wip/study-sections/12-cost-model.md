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

| Option | Marginal cost per course per language (10 min narration ≈ 8,000 chars) | Fixed cost | Residency | Source |
|---|---|---|---|---|
| Self-hosted open engine, on-demand cloud GPU batch jobs in Mumbai/Central India (E2E L4, Azure A10, GCP L4, AWS T4/L4) | ≈ ₹5–11 including the ASR QA pass | {{R3_GPU_MONTHLY}} | in-IN | R3 Part C |
| Self-hosted on an on-prem GPU workstation (RTX 4090 class) | ≈ ₹1 power + amortisation (₹71 each at 125/month, ₹9 at 1,000/month) | {{R3_ONPREM_CAPEX}} | on-prem | R3 Part C |
| Self-hosted on CPU only (small non-autoregressive models) | ≈ ₹16–27 on a cloud CPU box; near zero on existing servers but slow | existing servers | on-prem/in-IN | R3 Part A, C |
| SaaS fallback, India-resident: Sarvam Bulbul v2 / v3 (Indic) | ₹12 / ₹24 | none | India (vendor marketing) | R3 Part D |
| SaaS fallback, India region: Azure AI Speech (Central India), Google Neural2, Amazon Polly neural | ≈ ₹11–13 (≈ $15–16 per 1M chars; Polly/Google Mumbai availability unverified) | none | in-IN region where offered | R3 Part D |
| ElevenLabs (incumbent pilot vendor) | ≈ ₹63–160 depending on plan and model; India residency Enterprise-only | subscription | not India unless Enterprise | R3 Part D |
| ASR round-trip QA + forced alignment (self-hosted Whisper / IndicConformer) | ≈ ₹2–4 (inside the GPU figure above); Sarvam STT ₹5 as backup | shares the GPU | in-IN | R3 Part B, D |

{{R3_COST_MATHS}}

At 100 courses a year across 15 languages (1,500 course-languages) the entire self-hosted GPU bill is ≈ ₹7.5–17k on on-demand batch jobs, and even an all-SaaS fallback is ≈ ₹18–32k (≈ ₹1.9 lakh if everything went through ElevenLabs). Compute is not the reason to self-host; residency, unlimited regeneration during review, voice and pronunciation consistency across languages, and control are — and engineering time dominates the real cost.

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
| **T1 Lean** | Sonnet draft, self-hosted TTS on on-demand GPU, single SME review | ₹25 | ₹5–11 | ₹1,400–3,000 | **≈ ₹1,450–3,050** | ≈ ₹19–40k | internal non-regulatory content, product tutorials |
| **T2 Standard (compliance)** | Sonnet draft, self-hosted TTS + ASR QA, two-stage review, audit export | ₹35 | ₹8–15 | ₹2,750–5,800 | **≈ ₹2,800–5,850** | ≈ ₹36–76k | POSH, AML, IT-security, customer-support SOPs — the Airpay default |
| **T3 Premium / fallback** | Opus draft, SaaS voice (Sarvam / Azure India region, or ElevenLabs Enterprise), professional translation for named languages, two-stage review | ₹60 | ₹12–160 | ₹5,000–12,000 | **≈ ₹5,100–12,200** | ≈ ₹66–160k | regulator-facing content, Urdu/Arabic until the self-hosted gate passes, languages where the golden set fails |

Reading the table: the model is 1–2% of the cost, speech 1–5%, people 90%+. That is why the plan spends engineering on translation memory, QA automation, reviewer tooling and capacity dashboards, and why languages are enabled by demand. Compared with the SaaS pattern the report describes (per-seat platform + per-character voice + agency translation), the self-hosted stack removes the per-character line and the per-seat AI tax but does not remove the reviewer; no honest competitor does either.

### 12.5 Fixed and one-off costs

| Item | Planning figure | Notes |
|---|---|---|
| GPU compute (cloud, India) | on-demand batch: ≈ ₹7.5–17k per year at 1,500 course-languages; standing T4 ≈ ₹40k/month only if real-time ASR is needed (Stage 3) | multi-provider (E2E, Azure, GCP, AWS Mumbai); no long commitment at pilot volume |
| On-prem GPU workstation | ≈ ₹3.0–3.5 lakh (RTX 4090 class) | only above ~1,000 course-languages/month; consumer-driver EULA caveat; DR = cloud burst |
| Speech evaluation gate per language | ₹5–15k | 5 native listeners × 1 h honorarium + SME time |
| Golden sets (translation + speech) per language | 4–6 h SME | one-off, reused on every model change |
| Conformance testing (Stage 3) | {{R4_SCORM_CLOUD_PRICE}} | optional; ADL test suites are free |
| Build effort | ~72 Claude sessions across the three stages (§11) | no cash cost beyond the Claude subscription; developer unlocks in §11.2 |

### 12.6 Pilot budget request (for Addendum A)

For the pilot in §13 — one SOP, up to 13 languages, mock then live: model spend ≈ ₹300–550 at Sonnet 5.5 (≈ ₹1,000 with retries and a second SOP); self-hosted GPU batch ≈ ₹100–150 for 13 languages, or a SaaS TTS batch ≈ ₹150–300 on Sarvam/Azure India region, ≈ ₹800–2,100 on ElevenLabs; human review for Hindi (+ Marathi/Tamil) ≈ 15–20 h. **Recommended cap: ₹10,000 for the pilot month**, which also seeds the Addendum-A figure the memo left blank.

---
