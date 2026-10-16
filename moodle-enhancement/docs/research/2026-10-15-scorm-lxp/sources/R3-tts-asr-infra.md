# R3 — Self-hosted TTS + ASR stack for Sentientia LMS: cited technical dossier

**Decision under review:** build and self-host open-weight text-to-speech (narration) and speech recognition / forced alignment (captions, QA, future roleplay) for 15 target languages — hi, mr, kn, ta, te, bn, gu, ml, pa, or, as, ur (RTL), ar (RTL), en-IN, sw — with India-resident processing (DPDP Act) and an on-premise option, instead of paying ElevenLabs or another SaaS.
**Prepared:** 2026-10-15 (all sources accessed 2026-10-15). **Status:** research input for an ADR; not a decision.

**Method and limits (read first).** All facts come from web search result excerpts of the cited pages. Direct page fetching (WebFetch) was blocked at the network layer for every domain in this session, so several Hugging Face model cards and vendor pricing pages could not be opened verbatim; where a figure rests on a third-party excerpt or an aggregator rather than the primary page it is marked **unverified**. Vendor-authored pages are marked **(vendor marketing)**. No files, models or PDFs were downloaded; no paid APIs were called. No page content addressed to an AI was observed; every page was treated as data, not instruction. Currency: INR figures are converted at **₹95/USD** (working assumption; E2E Networks' own H100 listing implies ₹96/USD [90], and a 2026 India GPU survey used ₹95.4/USD [137]). Change the rate and every INR figure scales linearly.

---

## 0. Executive summary

- **Licence, not quality, is the first filter.** Of the ~22 models reviewed, only a handful are both commercially licensed *and* cover Indic languages: **AI4Bharat Indic Parler-TTS (Apache-2.0, 21 languages incl. Urdu)** [1], **Resemble Chatterbox Multilingual (MIT; Hindi, Arabic, Swahili, English)** [26][28], **AI4Bharat Indic-TTS FastPitch/HiFi-GAN (MIT, 13 Indian languages)** [8], **Veena (Apache-2.0, Hindi/English)** [39] and **Kokoro-82M (Apache-2.0; Hindi among 8 languages)** [22]. Meta MMS-TTS (CC-BY-NC) [14], Coqui XTTS-v2 (CPML) [17], F5-TTS weights (CC-BY-NC) [23], Fish/OpenAudio S1 (CC-BY-NC-SA) [31] and Rumik-OSS-1 (CC-BY-NC) [52] are excluded for commercial use.
- **Biggest licence trap:** permissively *tagged* Indic models whose training lineage is non-commercial — IndicF5 is listed MIT [4] but is a fine-tune in the F5-TTS family whose base checkpoints are CC-BY-NC because of the Emilia dataset [23][24], and a derivative card argues the restriction carries through [5]; snorTTS-Indic (Apache-2.0 tag) sits on an Orpheus *research release* base [51]; Praxy Voice is built on Chatterbox **and IndicF5** [49]; the MMS forced-aligner weights used by `ctc-forced-aligner` are CC-BY-NC [72]. Treat these as "legal review required", not "open".
- **Compute is cheap; people are not.** A 10-minute narration in one language costs roughly **₹5–15 of GPU time** on an India-region cloud GPU (Part C), versus **₹12–24 on Sarvam Bulbul** [43], **₹11–23 on Azure/Google/Polly neural tiers** (Part D) and **₹60–160 on ElevenLabs plans** [105][110]. At 100 courses × 15 languages a year the *entire* SaaS fallback bill is on the order of ₹20–35k. The self-host case therefore rests on data residency, voice consistency, unlimited regeneration and control — and must budget for engineering time, which dominates.
- **Cheapest credible infra:** on-demand India-region GPU batch jobs — E2E Networks L4 (₹50–70/hr guide pricing, vendor marketing) [91] or Azure NV6ads_A10_v5 Central India (~$0.636/hr) [85] — at **≈₹5–8 per course-language** including an ASR QA pass; AWS g4dn.xlarge Mumbai (T4, $0.579/hr) [79] is ≈₹11. A 24×7 reserved box is *not* justified at this volume; an on-prem RTX 4090 workstation (~₹3–3.5 lakh) only beats cloud above ~1,000 course-languages a month (C.4).
- **ASR shortlist:** OpenAI Whisper large-v3 / turbo (all 15 languages) [65][66] plus AI4Bharat IndicConformer-600M (MIT per AI4Bharat's listing, 22 Indian languages) [62][63][64]; NVIDIA Parakeet/Canary are European-language-only and therefore *not* candidates [68][69]. MMS-ASR is CC-BY-NC [67].
- **Gate:** a per-language MOS panel (ITU-T P.800 ACR) with pass ≥4.0 and CMOS within −0.3 of the SaaS reference, ASR round-trip WER ≤1.2× the human-recording baseline, ≥95% correct on a brand-term/numeral lexicon, RTF ≤0.5 on the target GPU (Part E.2). Languages that fail fall back to an India-resident SaaS (Sarvam for Indic; Azure Central India / ElevenLabs Enterprise India residency for ar, sw, en) (E.3).

---

## Part A — Open-source / open-weight TTS candidates

### A.1 Coverage of the 15 target languages (official support claims only)

| Model | hi | mr | kn | ta | te | bn | gu | ml | pa | or | as | ur | ar | en | sw | Count /15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Indic Parler-TTS [1] | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | (unofficial) | ✔ | ✔ | ✔ | – | ✔ | – | 12 (+1) |
| IndicF5 [7] | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | – | – | – | – | 11 |
| Indic-TTS FastPitch [8] | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | – | ✔ | ✔ | – | – | – | – | ≈10 (13 Indian langs listed; exact set unverified) |
| Chatterbox Multilingual [26][28] | ✔ | – | – | – | – | – | – | – | – | – | – | – | ✔ | ✔ | ✔ | 4 |
| Kokoro-82M [22] | ✔ | – | – | – | – | – | – | – | – | – | – | – | – | ✔ | – | 2 |
| Veena [39] | ✔ | – | – | – | – | – | – | – | – | – | – | – | – | ✔ | – | 2 |
| XTTS-v2 (non-commercial) [17] | ✔ | – | – | – | – | – | – | – | – | – | – | – | ✔ | ✔ | – | 3 |
| MMS-TTS (non-commercial) [14][15] | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | 15 (1,100+ langs; per-language quality varies) |
| Piper (GPL engine; per-voice licences) [19][20][21] | ? | – | – | – | – | – | – | – | – | – | – | – | ✔ (ar_JO) | ✔ | ✔ (sw_CD) | 3 (+hi unverified) |
| Orpheus multilingual research release [32] | ✔ | – | – | – | – | – | – | – | – | – | – | – | – | ✔ | – | 2 |
| OpenAudio S1-mini (non-commercial) [31] | – | – | – | – | – | – | – | – | – | – | – | – | ✔ | ✔ | – | 2 |
| Praxy Voice [48][49] | ✔ | – | – | ✔ | ✔ | – | – | – | – | – | – | – | – | ✔ | – | 4 |
| snorTTS-Indic-v0 [51] | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | – | – | – | – | – | – | 9 |
| Rumik-OSS-1 (non-commercial) [52] | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | – | ✔ | – | 13 (22 Indic + en claimed) |

**No single commercially licensed model covers all 15.** The realistic commercial stack is Indic Parler-TTS (12 Indic + en + ur) + Chatterbox Multilingual (ar, sw, en, hi alternate), with Indic-TTS FastPitch as a CPU-cheap second opinion.

### A.2 Model-by-model

| Model (org) | Licence — commercial OK? | Size / hardware | Speed (RTF) | Quality evidence | Cloning / control / timestamps | Maintenance |
|---|---|---|---|---|---|---|
| **Indic Parler-TTS** (AI4Bharat) | Apache-2.0 per HF card; repo is gated (request access) [1][3]. **Yes.** | Fine-tune of Parler-TTS Mini (878M params) [2]; ~2 GB VRAM FP16 (third-party estimate, unverified) [3]; CPU feasible but slow (no measured figure). | No reliable published RTF; one aggregator claims ~0.3× (unreliable) [3]. Autoregressive — plan RTF 0.5–1.0 on T4. | Trained on 1,806 h; 69 named voices; 21 languages (20 Indic + English) + 3 unofficial (Chhattisgarhi, Kashmiri, Punjabi) [1]. A 2026 benchmark blog rates AI4Bharat "comparable to ElevenLabs on Telugu" but behind Sarvam Bulbul overall (secondary) [53]. | No zero-shot cloning; voice controlled by natural-language description + named speakers [1]; no SSML; no native word timestamps. | Card ~2 years old per HF metadata [1]; no 2026 release found. |
| **IndicF5** (AI4Bharat) | Listed **MIT** on AIKosh [4] and on derivative cards [7]; **but** F5-TTS base checkpoints are CC-BY-NC-4.0 because of Emilia [23][24] and a derivative card argues NC carries through [5]. **Legal review required.** | ~0.35–0.4B params (mirrors; unverified) [7]; F5-class DiT, GPU recommended. | F5-TTS paper RTF 0.15; 32-NFE baseline 0.123 on RTX 3090, 0.030 with 7-step EPSS [24][25]. | "Near-human polyglot" trained on 1,417 h (Rasa, IndicTTS, LIMMITS, IndicVoices-R); 11 languages; paper reports Indian-data-only fine-tuning best, with cross-lingual speaker transfer [6][7]. | Reference-audio cloning required (needs prompt audio + transcript) [7]; card imposes "clone only with permission" condition [7]; no SSML; no timestamps. | Paper 2025 [6]; ONNX/MNN community ports exist [4]. |
| **Indic-TTS FastPitch + HiFi-GAN** (AI4Bharat) | **MIT** [8]. **Yes** (check IITM data terms, A.4). | Small (FastPitch-class, ~tens of M params; unverified); runs on CPU. | Non-autoregressive; plan RTF ≤0.1 GPU / ≤1.0 CPU (class estimate; no published figure). | Paper "TTS for the Next Billion Users" [9]; 13 Indian languages [8]. Older acoustic quality than LLM-codec models (judgement). | No cloning; phoneme-level control possible via pipeline; no timestamps natively (FastPitch exposes durations — unverified). | Repo last release date unverified. |
| **Meta MMS-TTS** | **CC-BY-NC 4.0** on every checkpoint card [14][15]. **No.** | VITS per language (small); CPU-capable. | Fast (VITS). | 1,100+ languages; quality uneven. | None. | 2023 release. |
| **Coqui XTTS-v2** (+ Idiap fork) | Weights **CPML non-commercial**; toolkit MPL-2.0 [17]. **No** for weights. | ~0.5B-class; GPU. | — | 17 languages incl. Hindi (added to fork tooling Apr 2024) and Arabic [17][18]. | Zero-shot cloning. | Original repo dead (no commits since Aug 2024); Idiap fork `coqui-tts` v0.27.5 (Jan 2026) [17][16]. |
| **Piper** (OHF-Voice/rhasspy) | Engine **GPL-3.0** (embeds espeak-ng, GPL); docs say "personal use and TTS research"; each voice has its own MODEL_CARD licence [19][20]. Swahili voice repo tagged MIT [21]. **Conditional** (GPL engine; per-voice check). | Tiny; real-time on a Raspberry Pi 4 (aggregator claim) [20]. | ~9× faster than real time on CPU (secondary, unmeasured) [155]. | Arabic (ar_JO) and Swahili (sw_CD) voices exist [21]; Hindi voice not found (unverified) [140]. | No cloning; espeak-ng phoneme control. | v1.7.0 release (2026) [19]. |
| **Kokoro-82M** (hexgrad) | **Apache-2.0** [22]. **Yes.** | 82M params; StyleTTS2+ISTFTNet; CPU-feasible [22]. | RTF 0.03 on GPU (secondary) [58]; ~real-time on CPU in a WASM build, 2–6× real time with WebGPU (secondary) [155]. | 8 languages incl. Hindi; ~1,200 h training (aggregator) [22]; Artificial-Analysis arena ≈ rank 48, Elo 1,055 (Jul 2026, secondary) [56]. | No cloning; fixed voicepacks. | 2025 release; v1.0 (unverified date). |
| **F5-TTS** (SWivid) | Code **MIT**; released checkpoints **CC-BY-NC-4.0** (Emilia) [23][24]. **No** for weights. | ~0.33B; GPU. | RTF 0.15 (paper) [24]; 0.03 with EPSS [25]. | English/Chinese base; Indic only via IndicF5. | Cloning. | Active. |
| **Chatterbox / Chatterbox Multilingual V3** (Resemble AI) | **MIT** on HF [26]; NVIDIA-hosted build is under the NVIDIA Open Model Agreement instead [27]. **Yes.** | 0.5B; ~3.5 GB VRAM multilingual TTS-only (NVIDIA doc) [29]; GPU strongly recommended. | "30 s of audio in 2 s on RTX 4090" ≈ RTF 0.07 (blog, unverified) [30]. | 23 languages incl. Arabic, Hindi, Swahili; V3 adds single-language fine-tunes incl. **Hindi** [26][28]; vendor claims preference over ElevenLabs (vendor marketing) [26]. | Zero-shot cloning; emotion/exaggeration control; Perth watermark (unverified this session); no SSML; no timestamps. | Active 2025–26 (Turbo, V3) [26]. |
| **Fish Speech / OpenAudio S1-mini** | **CC-BY-NC-SA 4.0**, gated "non-commercial only" [31]. **No.** | — | — | 13 languages incl. Arabic; no Hindi [31]. | Cloning. | Active. |
| **Orpheus TTS** (Canopy Labs) | Main English repo Apache-2.0 (secondary) [33]; **multilingual research release incl. Hindi — licence unverified** [32]. | 3B Llama backbone; GPU. | — | Hindi checkpoint (beta) [32]. | Cloning, emotion tags. | 2025. |
| **Veena** (Maya Research) | **Apache-2.0** [39]. **Yes.** | 3B Llama + SNAC 24 kHz; 4-bit ≈2.3 GB VRAM (third-party estimate) [39][41]. | "Sub-80 ms latency on H100" (vendor claim) [39]. | Hindi + English incl. code-mixed; 4 voices; training data proprietary [39][40]. | No zero-shot cloning; no SSML; no timestamps. | 2025. |
| **Sarvam Bulbul** | **API-only; no open weights found** [42][45]; data stays in India (vendor marketing) [44]. | n/a | n/a | v2: 11 Indian languages; v3 (beta): 10 Indian + English [42][45]. | Pitch/pace/loudness; pre-TTS number/date/currency normalisation for 12 Indian languages [46]. | Active. |
| **Vakyansh / Bhashini** (Open-Speech-EkStep) | Repo ships a LICENSE.md (contents **unverified**); Glow-TTS + HiFi-GAN scripts [47]. | Small. | Fast. | Older (2021–22) Indic voices; checkpoints not located in this session. | None. | Dormant (judgement). |
| **Praxy Voice** (Praxel, Apr 2026) | Weights `Praxel/praxy-voice-r6` reported Apache-2.0 (one search summary; **unverified**) [48]; **built on Chatterbox + IndicF5** → inherits the IndicF5 question [49]. | Chatterbox-class. | — | hi, te, ta, en with code-mix and cloning; BUPS phoneme-mapping + LoRA on the text encoder; no proprietary TTS data [49][50]. | Cloning. | New (2026). |
| **snorTTS-Indic-v0** (snorbyte) | HF tag Apache-2.0, but base is `canopylabs/3b-hi-pretrain-research_release` (LLaMA-3.2-3B) [51]. **Conditional.** | 3B. | — | 9 Indic languages [51]. | — | 2025–26. |
| **Rumik-OSS-1** (Sep 2026) | **CC-BY-NC 4.0** [52]. **No.** | 3B. | — | 22 Indic languages, native + romanised, emotion (self-reported) [52]. | — | New. |

### A.3 English-centric (not candidates)

Dia 1.6B (Nari Labs) — English only, Apache-2.0 per secondary sources [34][35]; Sesame CSM-1B — Apache-2.0, English output, gated [36]; StyleTTS2 — MIT code, English base, weight-use conditions, dormant since Mar 2024 [38]; MeloTTS — MIT, EN/ES/FR/ZH/JA/KO only (English has an "Indian" accent variant but no Indic language) [37]; Parakeet/Canary are ASR, see Part B. OpenAudio S1 has Arabic but no Hindi [31].

### A.4 Training-data lineage (where licence tags can mislead)

- **F5-TTS → IndicF5 → Praxy:** F5-TTS repo changed its checkpoint licence to CC-BY-NC-4.0 on 2024-10-14 "to follow the training set Emilia" [23][24]. IndicF5's paper starts from the English F5-TTS model [6]. Whether AI4Bharat retrained from scratch or fine-tuned Emilia-initialised weights determines whether "MIT" [4] is defensible; **unverified — ask AI4Bharat in writing before commercial use.**
- **IIT Madras Indic TTS corpus (used by Indic-TTS, IndicF5, Parler):** the IITM EULA excerpt grants a "perpetual, non-exclusive, worldwide, transferable, sub-licensable, royalty-free" licence under Indian law [10][11]; Phase-3 voices are listed CC BY 4.0 on AIKosh [12] with consent obtained for CC BY 4.0 release [13]. No non-commercial clause was found, but the EULA excerpt was truncated — **read the full PDF before relying on it.**
- **Orpheus "research_release" bases** (snorTTS) [51][32] and **MMS-FA aligner weights** (CC-BY-NC) [72] — restrictive upstreams under permissive-looking tags.

---

## Part B — Open-source ASR and forced alignment

### B.1 ASR candidates

| Model | Licence | Languages (target coverage) | Size / hardware | WER evidence for Indian languages | Timestamps |
|---|---|---|---|---|---|
| **Whisper large-v3** (OpenAI) | MIT per most sources; one guide says Apache-2.0 — **licence wording unverified this session** [65][66] | 99 languages → all 15 targets | 1.55B; int8 via faster-whisper ≈1.5 GB VRAM [66]; CPU possible (slow) | FLEURS-Tamil 48.6% WER vs IndicConformer 25.5% (community table, unverified) [76]; studio-bias on spontaneous Indic speech documented [77] | Segment-level native; word-level via WhisperX/alignment [70] |
| **Whisper large-v3-turbo** | as above | 99 (no translation task) [66][138] | 809M (4 decoder layers); ~4× faster, +1–2 WER points (secondary) [66] | as above | as above |
| **faster-whisper / whisper.cpp** | MIT (unverified this session) | same models | CTranslate2 int8; "4× GPU, 6× CPU" speed-ups are secondary claims [139][66] | — | — |
| **IndicWhisper** (AI4Bharat, Vistaar) | Licence field empty on AI4Bharat listing — **unverified** [61] | 12 Indian languages [59][60] | Whisper-size | Lowest WER on 39/59 Vistaar benchmarks; avg −4.1 WER; overall avg 24.6% (Kathbath 22.3, FLEURS 22.5); repo table: Google STT 23.9, IndicWav2vec 21, Azure STT 20 (self-reported) [59][60] | via Whisper tooling |
| **IndicConformer-600M multilingual** (AI4Bharat) | **MIT** per AI4Bharat listing and catalog entries (HF card not opened) [63][64] | 22 scheduled Indian languages → 11 targets (not ar, sw, en; ur status unverified) [62] | 600M hybrid CTC+RNNT; needs AI4Bharat's NeMo fork [63] | FLEURS-Tamil 25.5% (community, unverified) [76]; 2026 study evaluates it across MUCS/Kathbath/FLEURS/Vaani/RESPIN (tables not seen) [75] | CTC → word timestamps derivable |
| **Meta MMS-ASR (mms-1b-all)** | **CC-BY-NC 4.0** [67] — **No** | 1,162 languages | ~1B wav2vec2 + adapters | — | CTC |
| **NVIDIA Parakeet-TDT-0.6B-v3 / Canary-1B-v2** | CC-BY-4.0, "commercial use" [68][69] | **25 European languages only** — none of the 15 targets except en [68][69] → **not candidates** | 0.6B / 1B | n/a | Word + segment timestamps [68][69] |
| **Vakyansh ASR** | unverified | Indic | wav2vec2 | included as baseline in the 2026 study [75] | — |
| **Sarvam Saarika / Saaras** (API) | n/a | Indian languages (list unverified) | n/a | no public WER found [75] | — |

**Benchmarks to use:** Vistaar (59 benchmarks, 12 languages) [59], LAHAJA (Hindi accents; compares MMS, Whisper v3, Azure, Google Chirp) [78], Vividh-ASR (spontaneous speech) [77], and the Voice-of-India 15-language benchmark (536 h) with orthographic-variation-aware scoring [159]. Absolute WERs of 20–25% on real Indic benchmarks [59] mean an ASR *round-trip* gate must be **relative** (synthetic vs human recording) not absolute.

### B.2 Forced alignment (word timestamps for captions)

| Tool | Licence | Mechanism | Target-language fit |
|---|---|---|---|
| **WhisperX** | BSD-2-Clause (secondary) [71] | Whisper + per-language wav2vec2 CTC aligner; defaults for en/fr/de/es/it via torchaudio, others via HF [70] | hi/te/ur/ar availability lives in `DEFAULT_ALIGN_MODELS_HF`; **unverified** — must test per language [70] |
| **ctc-forced-aligner** (MMS-FA) | aligner code licence not found; **MMS-300m-1130 FA weights CC-BY-NC-4.0** [72] → **not commercial** | multilingual CTC alignment | 1,130 languages (romanised text) |
| **Montreal Forced Aligner** | MIT [73] | HMM-GMM acoustic models + pronunciation dictionaries | Hindi/Indic models and their licences **unverified** [73]; a 2026 review of MFA and alignment practice exists [156] |
| **aeneas** | AGPL-3.0 [74] | DTW on espeak synthetic audio | broad via espeak; AGPL network clause if hosted |
| **Native timestamps** | — | Kokoro/Piper expose phoneme/token timing (unverified); ElevenLabs API returns character timestamps (unverified) | — |

Practical route: generate narration → run Whisper large-v3 (or IndicConformer for Indic) → WhisperX-style CTC alignment against the *known script* (alignment, not recognition, so WER matters less) → VTT. A comparison of modern ASR methods for forced alignment exists [136].

---

## Part C — Infrastructure cost in India (October 2026 list prices)

### C.1 Cloud GPU list prices (per hour, on-demand unless stated)

| Provider / SKU | GPU | Price | Source & caveat |
|---|---|---|---|
| AWS ap-south-1 g4dn.xlarge | 1×T4 16 GB | **$0.579** (≈₹55); spot from $0.209; ~$423/mo at 730 h | third-party trackers [79]; "Reserved 1Y" $0.400 (payment option unspecified, **unverified**) [82] |
| AWS ap-south-1 g5.xlarge | 1×A10G 24 GB | **$1.208** (≈₹115) | [80] |
| AWS ap-south-1 g6.xlarge | 1×L4 24 GB | **$0.9664** (≈₹92) | [81] |
| AWS ap-south-1 c6i.4xlarge (CPU-only, 16 vCPU) | none | **$0.68** (≈₹65) | [83] |
| Azure Central India NC4as_T4_v3 | 1×T4 | **$0.579** (one table) / $0.763 (second table) — **conflicting** | [84] |
| Azure Central India NV6ads_A10_v5 | 1/6 A10 (fractional) | **$0.636** (≈₹60), ~$464/mo | [85] (Jan 2026 calculator) |
| GCP asia-south1 g2-standard-4 | 1×L4 | **$0.7358** (≈₹70); spot $0.293 | [86] |
| GCP asia-south1 n1-standard-4 + T4 | 1×T4 | ≈$0.228 VM + $0.35 GPU ≈ **$0.58** (GPU rate's Mumbai applicability **unverified**) | [87][88] |
| E2E Networks (Delhi NCR / Chennai, INR ex-GST) | L4 | **₹50–70** | guide pages on e2enetworks.com (vendor marketing) [91]; older USD table $0.57 [147] |
|  | L40S | **₹120–150** (guide) / $1.00 (older USD table) | [92][147] |
|  | A100 40/80 GB | ₹150–180 / ₹180–250 | [91] |
|  | H100 | **₹362 ($3.77)** product page; guide ₹350–400 | [90][91]; headline "from ₹49/hr" on the pricing page is unexplained [89] |
|  | T4 | ₹11,067/mo (old listing, likely stale) | [148] |
| Yotta Shakti Cloud | L40S (4-GPU bare metal) | **$2.30/GPU-hr** on-demand; $1.59 at 48-month | [93] (vendor marketing) |
|  | H100 (8-GPU HGX) | $4.99 on-demand; $4.59 monthly; $3.40 at 48-month; GenAI workstation ₹70,000/mo | [93] |
| Tata Communications Vayu | 4×L40S node | **₹1,174/hr** on-demand (≈₹293/GPU-hr); ₹810 at 4-year | rate PDF (vendor marketing; date not shown) [94] |
|  | 8×H100 SXM node | ₹3,905/hr | [94] |
| Cyfuture | L40S single-GPU instance | **₹102.24/hr** on-demand; ₹50.24 reserved (12-mo); promos ₹57 | [95] (vendor marketing; figures vary across its own pages) |
| NxtGen (SpeedCloud) | L40S etc. | **quote-only** via partner | [96] |

### C.2 On-prem capex (indicative, India, Oct 2026)

| Item | Indicative INR | Source / caveat |
|---|---|---|
| RTX 4090 24 GB (new) | **≈₹1.7–1.8 lakh** (₹1,76,000 est. ex-duty [97]; ₹1.6–2.05L vendor blogs [99]); a July-2026 tracker shows ₹3,49,999 "lowest listing" — **conflicting** [98] | consumer card; no ECC; EULA forbids data-centre deployment of GeForce drivers (unverified this session) |
| RTX 5090 32 GB | ₹4.3–4.8 lakh street; FE MSRP ₹2.09L rarely available; 12-mo median ₹3.0L [104] | volatile |
| RTX 6000 Ada 48 GB | **no India retail listing found** [100][101]; US low $7,499 (≈₹7.1L before duty) [100] | quote from distributor |
| L40S 48 GB | ₹6–9 lakh (careful breakdown) to ₹12–15 lakh (vendor blogs) — **conflicting** [102] | server part; needs a server chassis |
| Complete AI workstation | ₹2.5–4 lakh (RTX 5080/5090 class), ₹5L+ for heavy ML (vendor guide) [103] | RTX 4090 build ≈ ₹3.0–3.5L (my estimate: GPU ₹1.75L + platform ₹1.3–1.8L) |
| CPU-only inference server | cloud equivalent $0.68/hr [83]; Hetzner dedicated €40–300/mo tiers (secondary; Hetzner raised prices on 15 Jun 2026) [157][158] | only Class-A models (below) are CPU-practical |

### C.3 GPU-seconds per course: method

**Assumptions:** 10 minutes of narration per course per language = **600 s audio**; at 130 wpm (SENTIENTIA narration rule) ≈1,300 words ≈ **8,000 characters** including spaces (used in Part D). GPU-seconds = 600 × RTF. An ASR QA pass is added. Real-time factor (RTF) = processing time ÷ audio duration (lower is faster). Published RTFs are mostly on RTX 3090/4090; T4 is assumed ~3–4× slower, L4/A10/L40S ~2× slower (engineering assumptions, not measurements — **the evaluation must measure them**).

| Class | Models | Cited RTF | Planning RTF T4 | Planning RTF L4/A10/L40S | GPU-s on T4 | GPU-s on L4-class | GPU-s on RTX 4090 |
|---|---|---|---|---|---|---|---|
| A — small non-autoregressive | Kokoro, Piper, Indic-TTS FastPitch | Kokoro 0.03 GPU [58]; Piper ~real-time on Pi 4 [20] | 0.10 | 0.05 | 60 | 30 | 18 |
| B — flow-matching | IndicF5 / F5-TTS (32 NFE) | 0.15 paper; 0.123 on RTX 3090; 0.03 with EPSS [24][25] | 0.50 | 0.25 | 300 | 150 | 90 |
| C — autoregressive LLM-codec / Parler | Indic Parler-TTS, Chatterbox, Veena, Orpheus, XTTS | Chatterbox ≈0.07 on 4090 (blog) [30]; Parler none reliable [3] | 1.00 | 0.50 | 600 | 300 | 60 |
| ASR QA | Whisper large-v3 int8 (faster-whisper) | not verified | 0.20 | 0.10 | 120 | 60 | 30 |

Planning case = **Class C + ASR**: 720 GPU-s (0.20 h) on T4; 360 GPU-s (0.10 h) on L4-class; ~90 GPU-s (0.025 h) on RTX 4090.

### C.4 Marginal cost per course per language (Class C + ASR)

cost = GPU-hours × hourly rate; INR at ₹95/USD; E2E/Cyfuture/Tata add 18% GST.

| Infra option | Rate | GPU-h | USD | **INR** |
|---|---|---|---|---|
| AWS g4dn.xlarge (T4) on-demand [79] | $0.579 | 0.20 | $0.116 | **₹11.0** |
| AWS g4dn.xlarge "reserved 1Y" [82] (unverified) | $0.400 | 0.20 | $0.080 | ₹7.6 |
| AWS g6.xlarge (L4) [81] | $0.966 | 0.10 | $0.097 | ₹9.2 |
| AWS g5.xlarge (A10G) [80] | $1.208 | 0.10 | $0.121 | ₹11.5 |
| Azure NC4as_T4_v3 Central India [84] | $0.579–0.763 | 0.20 | $0.116–0.153 | ₹11.0–14.5 |
| Azure NV6ads_A10_v5 Central India [85] | $0.636 | 0.10 | $0.064 | **₹6.0** |
| GCP g2-standard-4 (L4) asia-south1 [86] | $0.736 | 0.10 | $0.074 | ₹7.0 |
| GCP n1-standard-4 + T4 [87][88] | ≈$0.58 | 0.20 | $0.116 | ₹11.0 |
| E2E L4 [91] (vendor marketing) | ₹50–70 | 0.10 | — | **₹5.0–7.0** (+GST ₹5.9–8.3) |
| E2E L40S [92] (planning 0.07 h: faster than L4) | ₹120–150 | 0.07 | — | ₹8.4–10.5 (+GST) |
| Yotta L40S [93] | $2.30 | 0.07 | $0.161 | ₹15.3 |
| Tata Vayu 4×L40S (per GPU) [94] | ₹293.5 | 0.07 | — | ₹20.5 (+GST) |
| Cyfuture L40S [95] on-demand / reserved | ₹102.24 / ₹50.24 | 0.07 | — | ₹7.2 / ₹3.5 (+GST) |
| On-prem RTX 4090 workstation (C.2) | capex ≈₹3.2L over 36 months = ₹8.9k/mo; power ≈0.6 kW × ₹8/kWh (assumption) | 0.025 | — | **₹0.1 power + amortisation share**: ₹71 each at 125/month (1,500/yr); ₹8.9 each at 1,000/month |
| CPU-only c6i.4xlarge [83] (Class A only; CPU RTF 0.5 TTS, 1–2 ASR assumed) | $0.68 | 0.25–0.42 | $0.17–0.28 | ₹16–27 |

**Scale check (100 courses × 15 languages = 1,500 course-languages per year):** AWS g4dn on-demand ≈ 300 GPU-h ≈ $174 ≈ **₹16.5k/yr**; AWS g6 ≈ 150 h ≈ ₹13.8k; Azure A10 ≈ ₹9k; E2E L4 ≈ ₹7.5–10.5k + GST; on-prem RTX 4090 ≈ ₹1.07 lakh/yr amortisation. A 24×7 g4dn.xlarge is ≈₹40k/month [79] — eight times the annual on-demand batch bill — so **spin-up/spin-down batch jobs, not a standing GPU**, unless roleplay/real-time ASR later requires an always-on endpoint.

---

## Part D — SaaS comparators (list prices, October 2026)

### D.1 Text-to-speech

Per-course cost uses 8,000 characters (C.3). Prices marked † could not be read from the vendor page in this session and rest on third-party excerpts.

| Vendor / tier | List price | Per course-language | Target languages | India processing region | Customer data used for training? |
|---|---|---|---|---|---|
| **ElevenLabs** Starter $6 (30k credits), Creator $22 (121k), Pro $99 (600k), Scale $299 (1.8M, 3 seats), Business $990 (6M, 10 seats); annual = 10 months [105] (vendor marketing); PAYG top-ups from $5 at plan-specific rates [154] | Multilingual v2 ≈1 credit/char; Flash/Turbo ≈0.5 (secondary) [111]; ≈$0.17/extra minute on Pro+ (secondary) [110] | **≈$1.32 ≈ ₹125** at full plan use (8k credits); ≈₹63 on Flash; ≈₹160 at $0.17/min overage | Multilingual v2: 29 languages incl. ar, hi, ta — no mr/kn/te/bn/gu/ml/pa/or/as/ur/sw [106]; Eleven v3: 74 languages incl. ar, hi (full list not seen; sw unverified) [153] | **India data residency — Enterprise only** (isolated India environment, Jul 2025) [107][149] | Data-use toggle: once disabled, new data not used for training [109]; privacy policy permits training on voice data subject to settings (third-party analysis) [151]; Zero Retention Mode — Enterprise, API only [108] |
| **Azure AI Speech** Neural TTS ≈$15/1M chars†, Custom Neural Voice ≈$24/1M† + training ≈$52/compute-h† [113]; official page renders "$-" placeholders; Neural vs NeuralHD priced separately; free 0.5M chars/mo [112] | | **≈$0.12 ≈ ₹11.4** (neural†) | Prebuilt neural voices exist for most targets (per-language voice list unverified) | **Central India supported** (`centralindia.tts.speech.microsoft.com`) [114]; data processed only in the resource's region [115] | Prebuilt-voice training-use statement **unverified**; CNV training data goes to model training; voice signatures used only for verification [115] |
| **Google Cloud TTS** Standard $4/1M; WaveNet $4/1M (official page; third parties say $16 — conflict); Neural2 $16/1M; Chirp 3 HD $30/1M; Studio $160/1M; Instant custom voice $60/1M; Gemini-TTS token-priced [116] | | Neural2 **$0.128 ≈ ₹12.2**; Chirp 3 HD ₹22.8; Studio ₹122; Standard ₹3.0 | broad (per-language voice tiers unverified) | asia-south1 endpoint availability for TTS **unverified** | No TTS-specific data-usage FAQ found; Google's Speech-to-Text FAQ states content is not used for any purpose except providing the service unless the customer opts into data logging [143]; a community post says TTS does not store submitted text (unverified) [144] |
| **Amazon Polly** Standard $4/1M; Neural $16/1M ($19.20 GovCloud); Long-form $100/1M; Generative $30/1M [117][118] | | Neural **₹12.2**; Generative ₹22.8; Long-form ₹76; Standard ₹3.0 | hi, ar, en-IN (others unverified) | Mumbai availability of neural/generative voices **unverified** | AWS states its AI services "may use and store customer content for service improvement … model training" and may store it in another Region; Polly and Transcribe are covered; an AWS Organizations AI-services opt-out policy must be attached to prevent this [141][142] |
| **OpenAI** tts-1 $15/1M chars; tts-1-hd $30/1M; gpt-4o-mini-tts $0.60/1M text tokens + $12/1M audio tokens ≈ $0.015/min (secondary) [119][120] | | tts-1 ₹11.4; hd ₹22.8; mini-tts ≈ ₹14 | multilingual (no language list seen) | **no India region** | per API data policy (unverified) |
| **Sarvam Bulbul** v2 ₹15/10k chars; v3 (beta) ₹30/10k chars [43] | | **₹12 (v2) / ₹24 (v3)** | v2: 11 Indian languages (as, bn, gu, hi, kn, ml, mr, or, pa, ta, te); v3: 10 Indian + en [42][45] — no ur, ar, sw | **India** — "data stays in India" (vendor marketing) [44] | **unverified** |
| **Murf** Free $0 (10 min, no commercial rights); Creator $29/mo ($19 annual); Business $99 ($66 annual); allowance disputed (2 h/mo vs 24 h/yr) [121] | | ≈$2.4 ≈ **₹230** at 2 h/mo | 20+ languages incl. hi (others unverified) | unverified | unverified |
| **WellSaid** Starter $19 (20 min), Pro $49 (180 min), Business $160/user/mo annual [122][145] | | ≈$2.7 ≈ **₹260** (Pro) | English-centric (Indic unverified) | US (unverified) | unverified |
| **Play.ht** Creator $39, Unlimited $99, Enterprise $499 (lineups changed mid-2026; free tier non-commercial) [123] | | unverified allowance | multilingual (unverified) | unverified | unverified |

### D.2 Speech-to-text

| Vendor | List price | Per 10-min course | India region / training |
|---|---|---|---|
| Azure STT standard real-time $1.00/h (one source says $1.60); batch $0.18/h [124][127] | | ₹15.8 (real-time) / ₹2.9 (batch) | Central India supported [114]; training terms unverified |
| Google STT v2 $0.016/min (first 500k min); dynamic batch $0.003/min [125][127] | | ₹15.2 / ₹2.9 | Region unverified; content not used beyond service delivery unless opted into data logging [143] |
| AWS Transcribe $0.024/min tier 1 (15-s minimum) [126][127] | | ₹22.8 | Mumbai availability unverified; content used for service improvement unless the Organizations opt-out policy is applied [141][142] |
| Sarvam STT ₹30/h; ₹45/h with diarization; billed per second [43] | | **₹5.0** | India (vendor marketing) [44] |

**Reading:** per course-language, the hyperscaler neural tiers (≈₹11–13), Sarvam v2 (₹12) and self-hosted GPU time (₹5–11) are all in the same band; ElevenLabs is ~10× dearer; Murf/WellSaid ~20×. The cost argument for self-hosting is therefore weak at pilot volumes and only becomes material for unlimited regeneration (draft/iterate loops), voice/pronunciation consistency across 15 languages, and residency/control.

---

## Part E — Recommendation inputs

### E.1 Shortlist

**TTS (commercially licensed):**
1. **Indic Parler-TTS** (Apache-2.0) — widest Indic coverage (12 targets incl. Urdu), 69 voices, description-controlled style, pure AI4Bharat/Parler lineage [1][2]. Risks: slow autoregressive decoding (unmeasured), no cloning, no timestamps, gated download, two-year-old card.
2. **Chatterbox Multilingual V3** (MIT) — the only permissive model with Arabic + Swahili + Hindi fine-tune, zero-shot cloning, active maintenance, ~3.5 GB VRAM [26][28][29]. Risks: no Indic beyond Hindi (Praxy shows it can be extended, but via IndicF5 [49]); NVIDIA-hosted build has a different licence [27].
3. **AI4Bharat Indic-TTS FastPitch/HiFi-GAN** (MIT) — CPU-cheap, 13 Indian languages, deterministic, the safe baseline [8][9]. Risk: older acoustic quality.
   *Conditional bench:* **IndicF5** (best Indic quality claims, cloning) **only after** written confirmation of its training lineage [4][5][6][23]; **Veena** for Hindi/Hinglish [39]; **Kokoro-82M** for en-IN/hi at near-zero cost [22].

**ASR (2):**
1. **Whisper large-v3 (+ turbo for speed)** via faster-whisper — all 15 languages, timestamps via WhisperX [65][66][70]. Licence wording to be confirmed on the repo.
2. **AI4Bharat IndicConformer-600M** (MIT per AI4Bharat) — materially lower Indic WER than Whisper on FLEURS-Tamil (25.5 vs 48.6, community table) [62][63][76]; needs the NeMo fork.
   Excluded: MMS-ASR (CC-BY-NC) [67]; Parakeet/Canary (European-only) [68][69].

### E.2 Evaluation protocol and pass thresholds

| Gate | Method | Pass threshold | Basis |
|---|---|---|---|
| Naturalness MOS | ITU-T P.800 ACR, 5-point (5 Excellent … 1 Bad); ≥20 native raters × 30 sentences per language, randomised with a hidden SaaS reference and a human recording | **MOS ≥ 4.0** ("Good") and **CMOS ≥ −0.3 vs the SaaS reference**; human anchor must score ≥4.3 or the panel is invalid | P.800 scale [128]; modern neural TTS typically 4.0–4.5, studio human 4.5–4.8 (vendor blog) [129]; cross-lab MOS is not comparable, so only within-panel comparisons count [130] |
| Intelligibility (round-trip) | Same ASR (IndicConformer for Indic, Whisper for ar/sw/en) on synthetic vs human recording of identical scripts | **WER_synth ≤ 1.2 × WER_human** per language; CER reported alongside WER for abugida scripts | Indic benchmark WERs are 20–25% even for the best models [59], so absolute thresholds would be meaningless |
| Brand-term pronunciation | Lexicon of 60 items ("Airpay", "UPI", "RBI", "Aadhaar", "NPCI", "IMPS", "NEFT", "KYC", "OTP", "PAN", merchant names) × 15 languages, 3 raters | **≥95% correct**; must have a per-term override mechanism (description prompt / phoneme input / pre-transliteration as Praxy does [49]) | industry practice; no citable standard |
| Numerals, dates, currency, code-mixing | 50-item normalisation set per language (₹1,23,456.00; 12/03/2026; 9.5%; Hinglish sentences; Roman-script Hindi) | ≥95% correct spoken form; Hinglish requires a normalisation front-end (hinglishNorm baseline 15.6 WER) [131]; Urdu/Arabic require diacritisation (Arabic neural DER 2.9% vs 13.8% rule-based) [132][133][134] | — |
| Speed / cost | Measure RTF on the target GPU; batch of 15 languages for one course | RTF ≤ 0.5 (T4) / ≤ 0.25 (L4); ≤ 15 min wall-clock per course; marginal cost ≤ the Sarvam/Azure fallback (₹12–24) | C.3–C.4 |
| Caption timing | Word onsets vs 100 hand-aligned words per language | median |Δ| ≤ 100 ms, 95th percentile ≤ 300 ms (engineering target); caption *text* accuracy ≥ 99% (industry convention, not regulation — the FCC's 47 CFR 79.1(j)(2) sets only qualitative "accurate, synchronous, complete, placed" standards) | [136][146][150][152] |
| Licence | Written lineage confirmation per model; SBOM of weights + data | no CC-BY-NC / CPML / research-only upstream | A.4 |

Run the gate per language; a language passes or fails individually.

### E.3 Fallback design and cost

**Design — hybrid, India-resident:** open models generate every draft and every regeneration (unlimited, ₹5–11 each); the per-language gate decides whether the *final* is produced by the open model or routed to SaaS. Routing table: Indic languages that fail → **Sarvam Bulbul v2/v3** (India-resident, ₹12–24) [43][44]; ar/sw/en that fail → **Azure AI Speech Central India** (≈₹11.4†) [113][114][115] or **ElevenLabs Enterprise with India data residency + ZRM** (≈₹125+; Enterprise contract) [107][108]; captions for everything via self-hosted Whisper/IndicConformer (no SaaS STT needed; Sarvam STT ₹5 as backup [43]).

**Cost at 100 courses × 15 languages/yr (1,500 finals):** worst case (every final via SaaS): 1,200 Indic × ₹12–24 = ₹14.4–28.8k + 300 ar/sw/en × ₹11.4 = ₹3.4k → **≈₹18–32k/yr**, plus self-host drafts ≈₹8–17k. Hybrid where 50% of languages pass: ≈₹9–16k SaaS + ₹8–17k GPU. Even an all-ElevenLabs fallback is ≈₹1.9 lakh/yr at plan rates [105] — small next to one engineer-month. The fallback is therefore always affordable; the decision is about residency and control, not rupees.

### E.4 Top 8 risks of the self-hosted build

1. **Quality below SaaS on low-resource languages** (as, or, pa, ur, sw): no public MOS exists for Indic Parler-TTS; the one 2026 Indic benchmark places Sarvam ahead of AI4Bharat overall [53]. Mitigation: per-language gate + routing.
2. **Licence lineage** (IndicF5/Emilia, Orpheus research bases, MMS-FA, GeForce driver EULA for on-prem 4090) — tags say MIT/Apache; upstreams say NC [5][23][51][72]. Mitigation: written confirmations, weight SBOM, exclude until cleared.
3. **Maintenance / abandonment**: Coqui died [16]; StyleTTS2 dormant [38]; Indic Parler-TTS card is ~2 years old [1]. Mitigation: pin versions, keep a second model per language.
4. **GPU availability and price volatility in India**: consumer-card prices swung from ₹1.7L to ₹3.5L listings within 2026 [97][98][104]; Indian clouds publish conflicting rate cards [89][90][95]. Mitigation: on-demand multi-provider batch, no long commitments at pilot volume.
5. **Prosody and pronunciation for Indic scripts** (schwa deletion, sandhi, loanwords, brand names): no SSML/lexicon in Parler/Chatterbox/Veena; only description prompts or pre-transliteration [1][26][49]. Mitigation: build the lexicon front-end; FastPitch/espeak path for phoneme control.
6. **Numerals, dates, currency, Hinglish code-mixing**: even SaaS vendors ship explicit pre-TTS normalisers [46][135]; open models have none. Mitigation: shared normaliser service (lakh/crore, ₹, dates) before any TTS.
7. **Urdu / Arabic script handling (RTL, unwritten vowels)**: TTS must diacritise or guess vowels [132][133][134]; Chatterbox Arabic quality for Indian-Arabic learner contexts untested; no permissive Urdu TTS beyond Parler's Urdu [1]. Mitigation: diacritiser stage + SaaS fallback for ar.
8. **Staffing and operations**: NeMo forks [63], gated repos [1], CUDA/driver drift, alignment per language [70] — this is a speech-ML maintenance surface, not a plugin. Mitigation: time-box the evaluation (6 weeks), decide on the gate, and keep the SaaS router live permanently.

---

## Sources (all accessed 2026-10-15)

[1] https://huggingface.co/ai4bharat/indic-parler-tts — Indic Parler-TTS model card (Apache-2.0; 21 languages; 1,806 h; 69 voices; gated).
[2] https://huggingface.co/parler-tts/parler-tts-mini-v1 — Parler-TTS Mini v1 (878M params).
[3] https://www.spheron.network/tools/gpu-recommender/ai4bharat/indic-parler-tts/ — third-party VRAM/parameter estimate for Indic Parler-TTS (unverified).
[4] https://aikosh.indiaai.gov.in/home/models/details/aibharat_indicf5.html — AIKosh listing of IndicF5 (licence: MIT).
[5] https://huggingface.co/dheeyantra/dhee-indic-f5 — derivative model card arguing non-commercial inheritance from F5-TTS/Emilia and IITM data.
[6] https://arxiv.org/html/2505.20693v1 — IndicF5 paper (fine-tuning from English F5-TTS; Indian-data-only fine-tuning best).
[7] https://huggingface.co/raajain/IndicF5 — mirror of the IndicF5 card (1,417 h; 11 languages; reference-audio cloning; "clone only with permission").
[8] https://github.com/AI4Bharat/Indic-TTS — AI4Bharat Indic-TTS repository (MIT; 13 Indian languages; FastPitch + HiFi-GAN).
[9] https://arxiv.org/pdf/2211.09536 — "Towards Building Text-To-Speech Systems for the Next Billion Users".
[10] https://www.iitm.ac.in/donlab/indictts/database — IIT Madras Indic TTS database download page.
[11] https://www.iitm.ac.in/donlab/indictts/downloads/license.pdf — IITM Indic TTS EULA (perpetual, royalty-free, sub-licensable; excerpt truncated).
[12] https://aikosh.indiaai.gov.in/home/datasets/details/hindi_female_mono_indictts_phase3.html — Indic TTS Phase-3 Hindi dataset (CC BY 4.0).
[13] https://arxiv.org/pdf/2410.14197 — unified framework for 22-language TTS datasets (consent for CC BY 4.0 release).
[14] https://huggingface.co/facebook/mms-tts-tir — MMS-TTS checkpoint card (CC-BY-NC 4.0).
[15] https://huggingface.co/facebook/mms-tts-swh — MMS-TTS Swahili checkpoint (cc-by-nc-4.0 tag).
[16] https://github.com/coqui-ai/TTS/discussions/4145 — Coqui shutdown / XTTS status discussion.
[17] https://www.promptquorum.com/power-local-llm/coqui-tts-review — review: MPL-2.0 toolkit, CPML non-commercial XTTS-v2 weights, Idiap fork coqui-tts v0.27.5 (Jan 2026), 17 languages incl. Hindi (secondary).
[18] https://github.com/idiap/coqui-ai-TTS/pull/4/files — Idiap fork PR adding Hindi sentence-splitting/fine-tuning support.
[19] https://github.com/OHF-Voice/piper1-gpl/releases — Piper (GPL) releases, v1.7.0.
[20] https://www.promptquorum.com/power-local-llm/piper-tts-review — Piper review: GPL-3.0, espeak-ng, per-voice MODEL_CARD licences, "personal use and research" wording (secondary).
[21] https://huggingface.co/rhasspy/piper-voices/tree/952f41d04e72535c242ae181b2ee1ed13d357d12/sw/sw_CD — Piper Swahili (sw_CD lanfrica) voice; repo-level MIT tag.
[22] https://tts.ai/voices/kokoro/?lang=pa — Kokoro-82M aggregator page: Apache 2.0, 82M, 8 languages incl. Hindi, no cloning (secondary; HF card not opened).
[23] https://huggingface.co/SWivid/F5-TTS/commit/346ef6cee7235a7d1039b30dc7d3edc77d59bd45 — F5-TTS checkpoint licence change to CC-BY-NC-4.0 (Emilia), 2024-10-14.
[24] https://huggingface.co/spaces/mrfakename/E2-F5-TTS/raw/27cee60c0890d22dab124730a73d5453fc8359a5/README_REPO.md — F5-TTS README mirror (MIT code; CC-BY-NC models; RTF 0.15).
[25] https://arxiv.org/abs/2505.19931v2 — EPSS / Fast F5-TTS (RTF 0.123 → 0.030 on RTX 3090).
[26] https://huggingface.co/ResembleAI/chatterbox/blob/main/README.md — Chatterbox README (MIT; Multilingual V3, 23 languages, Hindi fine-tune; vendor benchmark claims).
[27] https://build.nvidia.com/resembleai/chatterbox-multilingual-tts/modelcard — NVIDIA-hosted Chatterbox (NVIDIA Open Model Agreement; base MIT).
[28] https://deepinfra.com/ResembleAI/chatterbox-multilingual — Chatterbox Multilingual: 500M params; language list incl. Arabic, Hindi, Swahili.
[29] https://docs.nvidia.com/ace-for-games/chatterbox-tts/nvigi-chatterbox-model-card.html — NVIDIA NVIGI Chatterbox model card (VRAM ~2.3 GB Turbo, ~3.5 GB Multilingual).
[30] https://codersera.com/blog/chatterbox-turbo-run-and-install-locally-free-elevenlabs-alternative-2026/amp/ — Chatterbox Turbo speed claim (30 s audio in 2 s on RTX 4090) (secondary).
[31] https://huggingface.co/fishaudio/s1-mini/blob/main/README.md — OpenAudio S1-mini card (CC-BY-NC-SA 4.0; gated non-commercial; 13 languages incl. Arabic).
[32] https://huggingface.co/collections/canopylabs/orpheus-multilingual-research-release — Orpheus multilingual research release (Hindi, Korean, Chinese, Spanish/Italian).
[33] https://dev.co/ai/frameworks/orpheus-tts — Orpheus TTS directory entry (Apache-2.0) (secondary).
[34] https://www.speechtechmag.com/Articles/News/Speech-Technology-News/Nari-Labs-Launches-Dia-TTS-Model-169240.aspx — Dia launch (Apache 2.0) (secondary).
[35] https://github.com/anyopensource/dia — Dia mirror (1.6B; English only).
[36] https://osai-index.eu/model/conversational%20speech%20model — Sesame CSM-1B (Apache 2.0; English output; gated).
[37] https://github.com/myshell-ai/MeloTTS — MeloTTS (MIT; EN/ES/FR/ZH/JA/KO).
[38] https://www.promptquorum.com/power-local-llm/styletts-2-review — StyleTTS2 review (MIT code, weight conditions, English, dormant) (secondary).
[39] https://huggingface.co/maya-research/Veena — Veena model card (Apache 2.0; 3B Llama; SNAC 24 kHz; Hindi/English; 4 voices; H100 latency claim).
[40] https://www.nimdzi.com/language-technology-radar/maya/ — Maya Research profile (Veena open weights).
[41] https://www.madebyagents.com/models/veena/hardware — Veena 4-bit VRAM estimate ≈2.3 GB (secondary).
[42] https://docs.sarvam.ai/api-reference-docs/text-to-speech/models/bulbul — Sarvam Bulbul model docs (API; v2 11 Indian languages; v3 10 + English).
[43] https://docs.sarvam.ai/api/getting-started/pricing — Sarvam pricing (Bulbul v2 ₹15/10k chars; v3 ₹30/10k; STT ₹30/h, ₹45/h with diarization).
[44] https://www.sarvam.ai/try/text-to-speech-api — Sarvam TTS page ("data stays in India") (vendor marketing).
[45] https://www.analyticsvidhya.com/blog/2025/05/bulbul-v2-by-sarvam/ — Bulbul-v2 language list (secondary).
[46] https://docs.sarvam.ai/api/api-guides-tutorials/voice-cloning/supported-languages — Sarvam pre-TTS normalisation of numbers/currencies/dates for 12 Indian languages.
[47] https://huggingface.co/spaces/Harveenchadha/Vakyansh-Hindi-TTS/commit/8b0ab3ce6e07449a56f8f439642a379f919c8fb3 — copy of vakyansh-tts (Glow-TTS + HiFi-GAN scripts; LICENSE.md present, contents not seen).
[48] https://github.com/praxelhq/praxy — Praxy Voice repository (Hindi/Telugu/Tamil/English; weights Praxel/praxy-voice-r6; licence per search summary Apache-2.0, unverified).
[49] https://praxel-praxy-voice-demo.hf.space/ — Praxy demo ("built on Chatterbox and IndicF5"; transliteration preprocessor).
[50] https://www.emergentmind.com/papers/2604.25441 — Praxy paper summary (BUPS phoneme mapping; LoRA on text encoder; no proprietary TTS data).
[51] https://huggingface.co/snorbyte/snorTTS-Indic-v0/blob/main/README.md — snorTTS-Indic-v0 (Apache-2.0 tag; base canopylabs/3b-hi-pretrain-research_release; 9 languages).
[52] https://explainx.ai/blog/rumik-oss-1-indic-tts-open-source-emotion-2026 — Rumik-OSS-1 (3B; 22 Indic languages; CC-BY-NC 4.0) (secondary).
[53] https://caller.digital/blog/indic-tts-benchmark-bulbul-elevenlabs-sarvam-google-ai4bharat-2026 — 2026 Indic TTS benchmark blog (secondary).
[54] https://www.mindlink-ai.com/blog/free-text-to-speech-indian-languages — "7 free/open Indic TTS tools tested" (secondary).
[55] https://tts-agi-tts-arena-v2.hf.space/about — TTS Arena V2 (fresh Elo leaderboard; English-only prompts; Apache 2.0).
[56] https://offlinetts.com/blog/tts-arena-leaderboard-2026/ — third-party arena ranking summaries incl. Kokoro Elo 1,055 / rank 48 on Artificial Analysis (unreliable, approximate).
[57] https://www.orcarouter.ai/blog/breeze-tts-2-tops-open-weights-speech-arena — Breeze TTS 2 top open-weights on Artificial Analysis (Aug 2026) (vendor-adjacent).
[58] https://gigagpu.com/?p=1841 — Kokoro GPU RTF 0.03 (secondary).
[59] https://github.com/AI4Bharat/vistaar — Vistaar repo (59 benchmarks; IndicWhisper results table).
[60] https://arxiv.org/abs/2305.15386 — Vistaar paper (Interspeech 2023).
[61] https://admin.models.ai4bharat.org/models/ — AI4Bharat model listing (empty licence field for IndicWhisper).
[62] https://aikosh.indiaai.gov.in/home/models/details/aibharat_indicconformer_600m_multi.html — IndicConformer-600M listing (22 languages).
[63] https://github.com/AI4Bharat/IndicConformerASR — IndicConformer repo (MIT per AI4Bharat listing; requires AI4Bharat NeMo fork).
[64] https://mixpeek.com/model/ai4bharat/indic-conformer-600m-multilingual — catalog entry (license: mit) (secondary).
[65] https://ucstrategies.com/news/whisper-v3-large-open-source-speech-to-text-guide-99-languages/ — Whisper large-v3 guide (99 languages; 1.55B) (secondary).
[66] https://vexascribe.com/whisper-large-v3-vs-turbo — large-v3 vs turbo (809M; 4 decoder layers; int8 ≈1.5 GB; licence statements conflict) (secondary).
[67] https://huggingface.co/facebook/mms-1b-all/blob/913e9470563b886b0952a9d075ab152c0e352f39/README.md — MMS-1b-all README (CC-BY-NC 4.0; 1,162 languages).
[68] https://huggingface.co/nvidia/canary-1b-v2 — Canary-1B-v2 card (25 languages; CC-BY-4.0; word/segment timestamps).
[69] https://catalog.ngc.nvidia.com/orgs/nvidia/-/collections/parakeet-tdt-0.6b/ — Parakeet-TDT-0.6B v3 (25 European languages; commercial use).
[70] https://pypi.org/project/whisperx — WhisperX (wav2vec2 alignment; DEFAULT_ALIGN_MODELS_HF).
[71] https://bestllmfor.com/guide/whisperx-transcription-alignee/ — WhisperX BSD-2-Clause (secondary).
[72] https://huggingface.co/tatsu020/mms-300m-fa-onnx — MMS-300m forced-aligner conversion (CC-BY-NC-4.0 inherited; non-commercial).
[73] https://awesome.ecosyste.ms/projects/github.com%2Fmontrealcorpustools%2Fmontreal-forced-aligner — Montreal Forced Aligner (MIT).
[74] https://pypi.org/project/aeneas/1.5.0.3/ — aeneas (AGPL v3).
[75] https://arxiv.org/pdf/2606.09335 — "Factors affecting ASR performance ... in Indic languages" (2026; evaluates IndicConformer, Vakyansh, Vaani Whisper, Voxtral, Whisper large-v3).
[76] https://friendli.ai/models/osmapi/tamil-asr-qwen3 — FLEURS-Tamil WER table (IndicConformer-600M 25.49%; Whisper-large-v3 48.58%) (community; unverified).
[77] https://huggingface.co/blog/adalat-ai/vividh-benchmark — Vividh-ASR (studio bias in Indic ASR).
[78] https://arxiv.org/pdf/2408.11440 — LAHAJA Hindi multi-accent benchmark.
[79] https://www.doit.com/compute/spot/ap-south-1/g4dn.xlarge — AWS g4dn.xlarge Mumbai pricing (third-party tracker).
[80] https://www.doit.com/compute/spot/ap-south-1/g5.xlarge — AWS g5.xlarge Mumbai pricing.
[81] https://www.doit.com/compute/spot/ap-south-1/g6.xlarge — AWS g6.xlarge Mumbai pricing.
[82] https://www.devzero.io/instances/aws/g4dn.xlarge — g4dn.xlarge regional table incl. "Reserved 1Y" $0.400 for ap-south-1 (unverified payment option).
[83] https://www.doit.com/compute/spot/ap-south-1/c6i.4xlarge — AWS c6i.4xlarge Mumbai $0.68/h.
[84] https://pcr.cloud-mercato.com/providers/azure/flavors/standard_nc4as_t4_v3 — Azure NC4as_T4_v3 Central India (conflicting $0.579 / $0.763).
[85] https://calculator.holori.com/azure/vm/standard%20nv6ads%20v5/centralindia — Azure NV6ads_A10_v5 Central India $0.636/h (Jan 2026).
[86] https://calculator.holori.com/gcp/vm/g2-standard-4/asia-south1 — GCP g2-standard-4 asia-south1 $0.7358/h.
[87] https://cloud.google.com/products/compute/gpus-pricing — GCP GPU pricing (T4 $0.35/h; Mumbai applicability unverified).
[88] https://www.devzero.io/instances/gcp/n1-standard-4 — n1-standard-4 asia-south1 $0.228/h.
[89] https://www.e2enetworks.com/pricing — E2E Networks pricing page ("from ₹49/hr"; INR ex-GST; Delhi NCR/Chennai) (vendor marketing).
[90] https://www.e2enetworks.com/gpus/nvidia-h100.md — E2E H100 ₹362/h ($3.77) (vendor marketing).
[91] https://www.e2enetworks.com/cloud-terms/gpu/gpu-cloud-india — E2E guide: L4 ₹50–70/h, A100 ₹150–250/h, H100 ₹350–400/h, spot 65–70% off (vendor marketing).
[92] https://www.e2enetworks.com/cloud-terms/gpu/gpu-rental-india — E2E guide: L40S ₹120–150/h (vendor marketing).
[93] https://shakticloud.ai/pricing.html — Yotta Shakti Cloud pricing (H100 $4.99/$4.59/$3.40; L40S $2.30/$1.59; GenAI workstation ₹70,000/mo) (vendor marketing).
[94] https://www.tatacommunications.com/hubfs/library/documents/Tata%20Communications%20Vayu%20AI%20Cloud%20GPU%20as%20a%20Service.pdf — Tata Vayu GPUaaS rate list (4×L40S ₹1,174/h; 8×H100 ₹3,905/h) (vendor marketing; not downloaded — figures from search excerpt).
[95] https://cyfuture.cloud/kb/gpu/nvidia-l40s-price-india-complete-specification-and-cost-breakdown — Cyfuture L40S rates (₹102.24/h on-demand; ₹50.24/h reserved) (vendor marketing).
[96] https://precisiontech.in/cloud/nxtgen-cloud/nxtgen-gpu-cloud/nxtgen-gpu-cloud-in-noida/ — NxtGen partner page (quote-only pricing).
[97] https://www.local-llm.net/local-ai/in/ — RTX 4090 India estimate ≈₹1,76,000 ex-duty.
[98] https://gpuprix.com/de/india/gpus/geforce-rtx-4090 — RTX 4090 India tracker (₹3,49,999 lowest listing, Jul 2026) (conflicting).
[99] https://cyfuture.cloud/kb/gpu/nvidia-rtx-4090-price-in-india-2025--latest-cost--features — RTX 4090 ₹1.6–2.05L (vendor blog).
[100] https://gpuprix.com/india/gpus/rtx-6000-ada-generation — RTX 6000 Ada: no India listings; US low $7,499.
[101] https://gpuperhour.com/gpus/rtx-6000-ada/in — RTX 6000 Ada India: rental only.
[102] https://cyfuture.cloud/kb/gpu/nvidia-l40s-price-india-and-server-rental-options-for-ai-and-ml — L40S India purchase ≈₹6–9 lakh (vendor blog; other pages ₹12–15 lakh).
[103] https://digibuggy.com/blog-details/ai-pc-build-india-2026-run-local-llms-stable-diffusion-ml-at-home — India AI workstation build tiers (vendor guide).
[104] https://gpuprix.com/de/india/gpus/geforce-rtx-5090 — RTX 5090 India tracker (low ₹4,65,789; median ₹3,01,079).
[105] https://elevenlabs.io/pricing — ElevenLabs pricing (Starter $6/30k, Creator $22/121k, Pro $99/600k, Scale $299/1.8M, Business $990/6M) (vendor marketing).
[106] https://elevenlabs.io/docs/overview/models — ElevenLabs models (Multilingual v2: 29 languages; Eleven v3: 74).
[107] https://elevenlabs.io/blog/introducing-india-data-residency — ElevenLabs India data residency (Enterprise) (vendor marketing).
[108] https://elevenlabs.io/docs/developers/resources/zero-retention-mode — Zero Retention Mode (Enterprise; API only).
[109] https://elevenlabs.io/docs/help-center/legal/is-my-data-used-to-improve-eleven-labs-ai-models — ElevenLabs data-use toggle.
[110] https://www.cloudzero.com/blog/elevenlabs-pricing/ — ElevenLabs per-minute cost estimates (secondary).
[111] https://www.happyrobot.ai/hub/elevenlabs-pricing — credit-per-character ratios (secondary).
[112] https://azure.microsoft.com/pricing/details/cognitive-services/speech-services — Azure AI Speech pricing page (structure; values not rendered in excerpt).
[113] https://speechactors.com/article/?p=3863 — Azure TTS $15/1M neural, $24/1M custom, $52/compute-h training (secondary; unverified).
[114] https://learn.microsoft.com/azure/ai-services/speech-service/regions — Azure Speech regions (Central India; centralindia.tts endpoint).
[115] https://learn.microsoft.com/en-us/legal/cognitive-services/speech-service/custom-neural-voice/data-privacy-security-custom-neural-voice — Azure TTS data, privacy and security (in-region processing; CNV data handling).
[116] https://cloud.google.com/text-to-speech/pricing — Google Cloud TTS pricing (Standard/WaveNet $4; Neural2 $16; Chirp 3 HD $30; Studio $160; Instant custom voice $60 per 1M chars).
[117] https://aws.amazon.com/polly/pricing/ — Amazon Polly pricing (Long-form $100; Generative $30; Neural $19.20 GovCloud excerpt).
[118] https://www.costbench.com/compare/amazon-polly-vs-google-cloud-text-to-speech/ — Polly Standard $4 / Neural $16 (secondary).
[119] https://www.llmreference.com/model/tts-1-hd/openai-api — OpenAI tts-1-hd $30/1M chars; tts-1 $15 (secondary).
[120] https://www.getmaxim.ai/bifrost/llm-cost-calculator/provider/openai/model/gpt-4o-mini-tts — gpt-4o-mini-tts token pricing ≈$0.015/min (secondary).
[121] https://smallest.ai/blog/murf-ai-pricing-plans-cost-what-you-get-in-2026 — Murf plans (secondary).
[122] https://usagepricing.com/blueprint/wellsaid — WellSaid plans (Starter $19/20 min; Pro $49/180 min) (secondary).
[123] https://costbench.com/software/ai-voice-tools/play-ht/ — Play.ht plans (secondary).
[124] https://soniox.com/compare/stt/pricing — STT pricing comparison (secondary).
[125] https://cloud.google.com/speech-to-text/pricing/ — Google STT pricing (dynamic batch $0.003/min).
[126] https://aws.amazon.com/transcribe/pricing/ — Amazon Transcribe pricing.
[127] https://diyai.io/ai-tools/speech-to-text/speech-to-text-api-pricing/ — STT pricing guide (Azure $1/h; Google v2 $0.016/min; AWS $0.024/min) (secondary).
[128] https://zilliz.com/ai-faq/how-is-mean-opinion-score-mos-used-in-tts-evaluation — MOS / ITU-T P.800 ACR scale (secondary).
[129] https://picovoice.ai/blog/measuring-tts-quality/ — neural TTS 4.0–4.5 vs studio human 4.5–4.8 (vendor blog).
[130] https://arxiv.org/pdf/2405.09768 — TTS evaluation paper noting MOS reports omit scale details, limiting comparability (attribution via search excerpt).
[131] https://preview.aclanthology.org/setup/2020.coling-industry.13 — hinglishNorm corpus (13,494 segments; baseline 15.55 WER).
[132] https://www.cle.org.pk/clt10/papers/Automatic%20Diacritization%20for%20Urdu.pdf — Urdu automatic diacritization.
[133] https://huggingface.co/papers/1905.01965 — Arabic text diacritization with DNNs (Shakkala 2.88% DER vs Mishkal 13.78%).
[134] https://arxiv.org/pdf/2504.21635 — SadeedDiac-25 Arabic diacritization benchmark.
[135] https://docs.smallest.ai/models/changelog/lightning-v-3-1/2026/7/10 — separate number-language control in a commercial Indic TTS (vendor).
[136] https://arxiv.org/pdf/2406.19363 — "Tradition or Innovation: comparison of modern ASR methods for forced alignment".
[137] https://www.spheron.network/blog/gpu-cloud-india-2026/ — India GPU cloud survey 2026 (E2E H100 from $1.80/h; ₹95.4/USD reference rate) (secondary).
[138] https://whispernotes.app/en/blog/introducing-whisper-large-v3-turbo — Whisper turbo (no translation task) (secondary).
[139] https://www.promptlayer.com/models/faster-whisper-large-v3 — faster-whisper large-v3 listing (secondary).
[140] https://tts.ai/voices/piper/?lang=hi — Piper aggregator Hindi page (existence of an official Hindi voice unverified).
[145] https://costbench.com/software/ai-voice-tools/wellsaid-labs — WellSaid Business $160/user/mo (secondary).
[147] https://www.e2enetworks.com/pricing.md — older E2E USD table (A100 40GB $2.55; L40S $1.00; L4 $0.57 per GPU-h) (vendor; date unknown).
[148] https://www.whtop.com/plans/e2enetworks.com/91721 — E2E GPU.T4 ₹11,067/mo listing (stale).
[149] https://india.entrepreneur.com/?p=84015 — ElevenLabs India data residency launch report (30 Jul 2025).
[151] https://conductatlas.com/platform/elevenlabs/elevenlabs-privacy-policy/voice-data-use-for-ai-model-training/ — third-party analysis of ElevenLabs privacy policy (voice data may be used for training subject to settings).
[153] https://elevenlabs.io/docs/help-center/other/what-languages-do-you-support — ElevenLabs supported languages (Eleven v3: 74).
[154] https://elevenlabs.io/docs/overview/administration/pay-as-you-go — ElevenLabs pay-as-you-go top-ups.

[141] https://docs.aws.amazon.com/organizations/latest/userguide/orgs_manage_policies_ai-opt-out_all.html — AWS Organizations: AI services opt-out policy ("may use and store customer content for service improvement … model training"; possible cross-Region storage; opt-out deletes historical content).
[142] https://aws.amazon.com/about-aws/whats-new/2020/07/create-opt-out-policy-ai-services-across-accounts-aws-organizations/ — AWS announcement naming Amazon Polly and Amazon Transcribe among the covered services.
[143] https://docs.cloud.google.com/speech-to-text/docs/data-usage-faq — Google Speech-to-Text data-usage FAQ (no use beyond providing the service unless opted into data logging; async transcripts retained ~5 days).
[144] https://discuss.google.dev/t/cloud-text-to-speech-data-privacy/144643 — community post on Cloud Text-to-Speech data privacy (unverified; no official TTS data-usage FAQ found).
[146] https://www.govinfo.gov/content/pkg/FR-2014-03-31/html/2014-06754.htm — FCC 2014 order adopting closed-captioning quality standards (47 CFR 79.1(j)(2): accurate, synchronous, complete, appropriately placed; non-quantitative).
[150] https://www.3playmedia.com/blog/caption-quality/ — "99% accuracy" as the industry convention for captions (vendor blog).
[152] https://itaccessibility.illinois.edu/node/390 — University of Illinois summary of the DCMP Captioning Key (99% accuracy statement; secondary, not confirmed against DCMP's text).
[155] https://voicechanger.live/it/text-to-speech/compare/kokoro-vs-piper — Kokoro vs Piper comparison (CPU/WASM speed claims) (secondary).
[156] https://arxiv.org/pdf/2606.18466 — "Montreal Forced Aligner and the state of speech-to-text alignment in 2026".
[157] https://www.websnp.com/blog/hetzner-dedicated-server-review — Hetzner dedicated-server price tiers (secondary).
[158] https://heise.de/-11333037 — Hetzner price increase for newly ordered servers, June 2026.
[159] https://voiceofindiablogs.ai.joshtalks.com/ — Voice of India 15-language ASR benchmark (536 h; orthographic-variation-aware scoring).

*Not verified in this session despite searching: the licence of the Canopy Labs Orpheus Hindi research-release checkpoint [32]; the exact licence wording on the openai/whisper repository [65][66]; the Hugging Face card text of ai4bharat/IndicF5 [4][7] and ai4bharat/indic-conformer-600m-multilingual [63][64]; the vakyansh-tts LICENSE.md contents [47]; Azure AI Speech list prices [112][113]; Amazon Polly and Google TTS availability in Mumbai; 1-year reserved pricing for AWS Mumbai GPU instances [82]; India retail prices for RTX 6000 Ada and L40S [100][102].*
