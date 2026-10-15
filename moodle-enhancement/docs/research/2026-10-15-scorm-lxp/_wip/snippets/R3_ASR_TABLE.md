| Engine | Licence | Languages | Indic accuracy evidence | Role in the pipeline |
|---|---|---|---|---|
| **Whisper large-v3 / turbo** (OpenAI, via faster-whisper) | MIT per most sources (repo wording to confirm) [R3:65,66] | all 15 targets | FLEURS-Tamil ≈ 49% WER vs IndicConformer ≈ 26% (community table, unverified) [R3:76]; studio bias on spontaneous Indic speech [R3:77] | round-trip QA for ar/sw/en; general fallback; segment timestamps |
| **IndicConformer-600M** (AI4Bharat) | MIT per AI4Bharat listing [R3:63,64] | 22 Indian languages (11 targets; not ar/sw/en) | lowest Indic WER in community tables; evaluated across Kathbath/FLEURS/Vaani in a 2026 study [R3:75,76] | round-trip QA for Indic languages; CTC word timings |
| **IndicWhisper** (AI4Bharat Vistaar) | licence field empty — unverified [R3:61] | 12 Indian languages | best on 39/59 Vistaar benchmarks, avg WER ≈ 24.6% [R3:59,60] | benchmark reference |
| **WhisperX** | BSD-2 (secondary) [R3:71] | per-language CTC aligners (hi/te/ur/ar availability to test) [R3:70] | — | word-level forced alignment for WebVTT and karaoke captions |
| **Montreal Forced Aligner** | MIT [R3:73] | dictionary-based; Indic models unverified | — | alternative aligner |
| Excluded | MMS-ASR and the MMS forced-aligner weights (CC-BY-NC) [R3:67,72]; NVIDIA Parakeet/Canary (European languages only) [R3:68,69]; aeneas (AGPL network clause) [R3:74] | | | |

Absolute WERs on real Indic benchmarks are 20–25% even for the best models [R3:59], so the intelligibility gate is relative (synthetic vs a human recording of the same script), never an absolute figure.
