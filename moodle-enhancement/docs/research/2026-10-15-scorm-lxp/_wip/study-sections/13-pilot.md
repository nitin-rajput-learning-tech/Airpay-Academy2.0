## 13. The pilot: one SOP, thirteen languages, zero spend today, live when Nitin says so

### 13.1 What the pilot proves

The pilot harness (`pilot/`) is the reference implementation of ADR-034's compiler. From the hand-authored canonical course "Handling a disputed UPI transaction" (SOP-CS-014: four narrated slides, a nine-node branching scenario with acceptable/recoverable/irrecoverable consequences, a five-question mastery quiz across all four question types, thirteen declared languages, Hindi partly human-translated) it produces, in **mock mode with no network access**: pseudo-localised strings for every missing language (clearly marked, protected terms preserved), silent narration audio and WebVTT captions per language per block, SCORM 1.2, SCORM 2004 4th Edition and cmi5 packages (multilingual, and per-language on request), a validation report (schema rules, graph integrity, state budget, i18n completeness, PII scan, package checks) and an append-only, hash-chained audit log. The runtime adapters implement the Moodle hardening the report calls for: compact suspend-data tokens with a truncation ladder under 4,096 characters, `exit="suspend"`, id-only interaction logging under 255 characters, batched commits, finish on `pagehide`.

What mock mode does **not** prove: translation quality, voice quality, Moodle runtime behaviour, LRS acceptance. Those are the live run and the Stage 1 UAT upload.

### 13.2 How to run it (mock)

```
cd moodle-enhancement/docs/research/2026-10-15-scorm-lxp/pilot
python -I run_pilot.py --selftest
python -I run_pilot.py --mode mock --out out/run-mock-001
python -I run_pilot.py --mode mock --package-mode per_language --langs en,hi,ar --out out/run-mock-002
```

Outputs: `out/<run>/scorm12/*.zip`, `scorm2004/*.zip`, `cmi5/*.zip`, `validation-report.md|json`, `audit/*.jsonl`. {{PILOT_RUN_SUMMARY}}

### 13.3 Live run — exactly what Nitin must set and [CONFIRM]

| # | Prerequisite | Who | Where |
|---|---|---|---|
| 1 | **Addendum-A monthly AI cap figure** (the signed memo approved a multi-feature budget; the figure is still blank). Recommendation for the pilot: ₹10,000 ceiling. Expected model spend is small — ≈ ₹300–550 for generation plus all 13 translations at Sonnet 5.5 prices, ≈ ₹1,000 with retries; the ceiling leaves room for a SaaS TTS fallback batch (≈ ₹200–400 on an India-region neural voice, ≈ ₹3,000 on ElevenLabs) and a second SOP (§12.6). | Nitin | `docs/business/DECISION-MEMO-2026-08-04.md` Addendum A; gateway settings `monthly_cost_cap_usd` |
| 2 | **`ANTHROPIC_API_KEY`** provisioned (never in git): `.env` for the harness; `local_sentientia_ai` central `api_key` setting for in-product runs | Nitin / IT | `.env`, Site admin → Sentientia AI Gateway |
| 3 | **Model choice** for translation and generation: `ANTHROPIC_MODEL` (recommend the current Sonnet generation after the gateway golden-set eval; default remains `claude-sonnet-4-6`) and the price inputs for the harness's pre-flight estimate (`ANTHROPIC_INPUT_USD_PER_MTOK`, `ANTHROPIC_OUTPUT_USD_PER_MTOK`, `USD_INR`) | Nitin | `.env` |
| 4 | **Harness spend guard**: `PILOT_SPEND_CAP_INR` set; the harness refuses any live call whose pre-flight estimate exceeds it, and prints the per-call [CONFIRM] prompt | Nitin | `.env` |
| 5 | **Per-call [CONFIRM]** typed at the prompt for each translation batch and each TTS batch (CLAUDE.md §3); the harness logs who confirmed and the estimate vs actual | Nitin | terminal |
| 6 | **TTS engine decision for the live run**: (a) self-hosted engine installed on a GPU box or CPU host in India (`--tts local-cmd` with the service command), or (b) a fallback SaaS key with India-region processing ([CONFIRM] per batch, cost estimated at list price), or (c) silent audio only. ElevenLabs is allowed as incumbent pilot vendor only with its own [CONFIRM] and cost estimate | Nitin | `.env`, `--tts` option |
| 7 | **Reviewers named** for Hindi (translator + SME) so the two-stage loop is exercised on real content; Marathi/Tamil optional | Nitin / L&D | localisation workspace (Stage 1) or XLIFF round-trip in the interim |
| 8 | **UAT upload window** on Moodle 5.3 for the SCORM 1.2 and 2004 packages (mod_scorm) and an LRS target for the cmi5 package (`local_sentientia_xapi` on UAT with `lrs_endpoint_enabled`) | Nitin / IT | UAT |
| 9 | **Flags** if the run goes through the product rather than the harness: `sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled`, `sentientia.translate.enabled`, `sentientia.translate.live_api` on the pilot tenant only | Nitin | Switchboard |
| 10 | **Success criteria agreed**: resume after tab close works; pass/fail at 70 correct; interactions visible; Hindi approved by reviewers; measured cost per course per language within tier; speech gate scorecard produced | Nitin | this document |

Nothing above has been done in this study: no key exists in the workspace, no paid call was made, and the harness's live path has never executed.

### 13.4 Disk and downloads

No model was downloaded. Disk at the time of the study: D: 454 GB free, C: 169 GB free (`df -h`, 2026-10-15). A self-hosted engine evaluation on this workstation would need under 20 GB on D: for a CPU-capable Indic model plus Python dependencies; anything larger is asked for through the report first, per Nitin's instruction.

---
