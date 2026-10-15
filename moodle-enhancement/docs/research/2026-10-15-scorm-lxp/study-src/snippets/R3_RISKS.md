| # | Risk (from `sources/R3` E.4) | Mitigation |
|---|---|---|
| 1 | Quality below SaaS on low-resource languages (as, or, pa, ur, sw); no public MOS for Indic Parler-TTS; one 2026 benchmark puts Sarvam ahead of AI4Bharat overall | per-language gate; SaaS routing per language |
| 2 | Licence lineage traps: IndicF5 (F5-TTS/Emilia CC-BY-NC), Orpheus research bases, MMS forced-aligner weights, GeForce driver EULA for on-prem consumer cards | written confirmations, a weights-and-data SBOM, exclusion until cleared |
| 3 | Maintenance and abandonment (Coqui dead, StyleTTS2 dormant, Parler card two years old) | pin versions; keep a second engine per language |
| 4 | Indian GPU price volatility and conflicting rate cards | on-demand multi-provider batch; no long commitments at pilot volume |
| 5 | Prosody and pronunciation for Indic scripts (schwa deletion, sandhi, loanwords, brand names) with no SSML/lexicon in the permissive models | build the lexicon and normaliser front-end; FastPitch/espeak path for phoneme control |
| 6 | Numerals, dates, currency (lakh/crore, ₹), Hinglish code-mixing — even SaaS vendors ship explicit normalisers | shared normaliser service before any TTS |
| 7 | Urdu/Arabic script handling (RTL, unwritten vowels): diacritisation needed; Arabic quality untested for Indian learner contexts | diacritiser stage; SaaS fallback for ar until the gate passes |
| 8 | Staffing and operations: NeMo forks, gated repos, CUDA drift, per-language aligners — a speech-ML surface, not a plugin | 6-week time-boxed evaluation; the SaaS router stays live permanently; first thing a developer takes over |
