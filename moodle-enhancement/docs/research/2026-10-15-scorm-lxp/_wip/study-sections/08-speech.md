## 8. Speech: building our own TTS and ASR for Indian languages

Nitin chose a full build, against the recommendation to start with a vendor. This section plans it properly: what the service is, which engines are candidates and under which licences, what it costs to host in India, how quality is gated before any learner hears it, and what the fallback is.

### 8.1 Why self-host, in one paragraph

At thirteen languages the per-character SaaS line scales linearly and never ends; the data (training scripts derived from internal SOPs) should stay in India for the DPDP/RBI story; and a bank-grade buyer asks where voice synthesis runs. Open-weight Indic speech models matured in 2024–2026 (AI4Bharat's releases and several commercially licensed multilingual models — `sources/R3`), so a self-hosted engine is credible, provided we are honest that quality must be proven per language and that a fallback stays wired.

### 8.2 The service: `sentientia-speech`

- **Shape:** one container, REST API (`POST /tts`, `POST /asr`, `POST /align`, `GET /voices`, `GET /health`, Prometheus metrics), stateless, queue-backed for batch jobs, engine-pluggable behind one interface (`synthesize(text, lang, voice, lexicon) → audio + word timings`, `transcribe(audio, lang) → text + timings`, `align(audio, text, lang) → word timings`).
- **Pipeline per narration segment:** text normalisation (numerals, currency "₹12,400", dates, abbreviations per language) → lexicon substitution for protected terms (Airpay, UPI, RBI, OTP, UTR, Aadhaar; per-language phonetic renderings) → synthesis → ASR round-trip (independent engine) → WER check against the source text (flag > 10%) → forced alignment → WebVTT cues (1.0–7.0 s, 83 ms gap) and word-timestamp JSON → cache by (text hash, lang, voice, engine version) so re-compiles never re-synthesise unchanged segments.
- **Integration:** `local_sentientia_authoring\tts_client` calls the service instead of ElevenLabs; every job is ledgered (engine, seconds, region, cost estimate) in the gateway's ledger conventions; the SCD `media_ref` records engine, voice, region and the timestamps file.
- **Operations:** model weights pinned by hash in the image; no runtime downloads; GPU or CPU auto-detected; horizontal scale by adding workers; a nightly golden-set synthesis compares spectrogram/ASR-WER drift after any update.

### 8.3 Engine candidates and licences

{{R3_TTS_TABLE}}

Rules applied to the table: production engines must permit commercial use in their licence text (CC-BY-NC and CPML-licensed models are research-only for us); every target language needs at least one candidate that passes the gate or stays on the fallback; Urdu and Arabic are the likeliest to need the fallback longest.

ASR and alignment candidates:

{{R3_ASR_TABLE}}

### 8.4 Hosting options and sizing

{{R3_INFRA_TABLE}}

Sizing method: minutes of narration per month × real-time factor of the engine on the chosen hardware = GPU-minutes; add 30% for ASR round-trip and alignment; a shared single-GPU instance handles several hundred narrations a day at RTF ≤ 0.5. For the pilot and Stage 1, CPU inference on an existing server is acceptable if the engine's CPU RTF ≤ 2.

### 8.5 The evaluation gate (per language, before any learner hears the engine)

| Check | Method | Pass threshold |
|---|---|---|
| Naturalness | Blind MOS panel: ≥ 5 native listeners, 20 narration samples (incl. amounts, dates, protected terms, one code-mixed line), self-hosted vs fallback SaaS sample | mean MOS ≥ 3.8/5 and within 0.5 of the fallback |
| Intelligibility | ASR round-trip with an independent engine; WER on scripted narration | ≤ 10% (≤ 15% for Urdu/Arabic in the first gate) |
| Terminology | Protected-term pronunciation checklist after lexicon tuning | 100% |
| Numerals and currency | 30 amounts/dates read back correctly by two listeners | 100% |
| Speed | Real-time factor on the target host | ≤ 0.5 GPU, ≤ 2.0 CPU |
| Stability | 1,000-segment soak, no crashes, memory stable | pass |
| Licence | Commercial use permitted, attribution requirements met, written confirmation filed | pass |
| Cost | Marginal cost per course per language below the fallback's | pass |

Scorecard stored with the language's gate flag (`sentientia.speech.engine.<lang>`); re-run on every engine or model update (golden-set regression, as the AI gateway does for prompts). A language that fails stays on the fallback; the decision is per language, not per platform.

### 8.6 Fallback and hybrid operation

A SaaS neural TTS with India-region processing (Azure Central India, Google asia-south1 or AWS Mumbai; Sarvam for Indic voices) sits behind the same interface, [CONFIRM]-gated and quota-limited through the ledger. ElevenLabs remains the incumbent pilot voice only until the first gate concludes (memo Q4). Hybrid mode (self-hosted for drafts and review, fallback for the final render of a language that failed) is a per-language switch.

### 8.7 Risks specific to the build

{{R3_RISKS}}

### 8.8 Build steps

Stage 1.4: service skeleton, one engine adapter, fallback adapter, evaluation harness, lexicon, VTT, integration with `tts_client`, Hindi gate. Stage 2.4: ASR forced alignment and karaoke captions, three more language gates, caching and batch queue, metrics. Stage 3.2: streaming ASR for learner input in roleplay; DR for weights; VAPT scope. Developer unlock: the service's container, GPU host and monitoring are the first things a developer takes over (§11.2).

---
