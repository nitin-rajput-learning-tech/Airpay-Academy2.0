# Live run checklist - what Nitin must set before any paid call

**Status 2026-10-15: nothing below has been done.** No key exists in the workspace, no paid API was called, the harness's live path has never executed, and `python -I run_pilot.py --mode live` refuses to start (transcript: `evidence/live-refusal.txt`).

CLAUDE.md section 3 and `.claude/rules/api.md` apply: every paid call is [CONFIRM]. The harness enforces that in code; this list is what you provide.

| # | Prerequisite | Who | Where | Notes |
|---|---|---|---|---|
| 1 | **Addendum-A monthly AI cap figure** | Nitin | `docs/business/DECISION-MEMO-2026-08-04.md` Addendum A; gateway setting `monthly_cost_cap_usd` | The signed memo approved a multi-feature budget but left the figure blank. Recommendation for the pilot month: **INR 10,000**. Expected model spend is small (STUDY section 12: about INR 300-550 for one 12-minute course in 13 languages at Sonnet 5.5 prices, about INR 1,000 with retries) |
| 2 | **`ANTHROPIC_API_KEY`** | Nitin / IT | `.env` for the harness (never committed); `local_sentientia_ai` central `api_key` for in-product runs | The harness reads it from the environment only and never prints it |
| 3 | **Model and prices** | Nitin | `.env`: `ANTHROPIC_MODEL`, `ANTHROPIC_INPUT_USD_PER_MTOK`, `ANTHROPIC_OUTPUT_USD_PER_MTOK`, `USD_INR` | Defaults are the gateway's `claude-sonnet-4-6`, USD 3 / 15 per million tokens, INR 87. The study recommends the current Sonnet generation after the gateway golden-set eval; set the real list prices before the run because the pre-flight estimate and the cap check use them |
| 4 | **`PILOT_SPEND_CAP_INR`** | Nitin | `.env` | The harness refuses any run whose pre-flight estimate exceeds it and aborts mid-run if actual spend does. No cap, no run |
| 5 | **A typed `CONFIRM` per request** | Nitin | terminal | `--confirm` acknowledges the run; each translation request then prints the language, units, characters and model and waits for the word `CONFIRM`. Anything else sends nothing. The confirmation is written to the audit log |
| 6 | **Speech engine decision** | Nitin | `--tts` option | (a) self-hosted engine installed on a CPU or GPU host in India and called through `--tts local-cmd --tts-cmd "<template with {text_file} {out_mp3} {lang} {voice}>"`; (b) silent audio only (default); (c) a SaaS voice batch is **not** in the harness: it belongs to the Stage 1 speech service, with its own [CONFIRM] and cost estimate. ElevenLabs stays the incumbent pilot vendor only until the first evaluation gate (memo Q4) |
| 7 | **Reviewers named** for Hindi (translator and subject expert) | Nitin / L&D | localisation workspace (Stage 1) or an XLIFF round trip in the interim | Without named reviewers the two-stage loop is not exercised on real content and Hindi stays `in_review` |
| 8 | **UAT upload window** on Moodle 5.3 and an LRS target | Nitin / IT | UAT | SCORM 1.2 and 2004 packages go to `mod_scorm`; the cmi5 package needs `local_sentientia_xapi` with its LRS endpoint enabled on UAT |
| 9 | **Flags** (only if the run goes through the product, not the harness) | Nitin | Switchboard, pilot tenant only | `sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled`, `sentientia.translate.enabled`, `sentientia.translate.live_api` |
| 10 | **Success criteria agreed** | Nitin | this list | resume after tab close works; pass/fail at 70 correct; interactions visible in the SCORM report; Hindi approved by named reviewers with audit entries; measured cost per course per language within the tier; a speech gate scorecard produced |

## The gates the harness enforces on its own

- `--mode live` must be given explicitly; the default is mock.
- Refuses unless `--confirm`, `ANTHROPIC_API_KEY`, `PILOT_SPEND_CAP_INR` are all present, the pre-flight estimate is within the cap, and the PII scan found nothing.
- Never sends a unit twice: units a human already translated are kept, only missing language pairs are requested.
- Re-scans generated text for personal identifiers and stops the run if any appear.
- Writes every step to a hash-chained audit log that cannot be overwritten.
- Prompts and responses are not stored; only hashes, counts, model, prompt version and the confirmed estimate are.

## How to run it when you are ready

```
cd moodle-enhancement/docs/research/2026-10-15-scorm-lxp/pilot
python -I run_pilot.py --estimate                      # look at the estimate first
# put the four variables in .env or the shell, then:
python -I run_pilot.py --mode live --confirm --preview --langs en,hi,mr,ta --out out/live-001
```

Start with four languages and one SOP; the audit log and the report give measured cost per course per language, which replaces the planning numbers in STUDY section 12.

## What a live run still would not prove

Voice quality (needs the listening-panel gate, STUDY section 8.5), Moodle runtime behaviour (UAT), and whether Hindi reviewers accept the machine draft (that is the point of the review loop). Live roleplay and avatar video are out of scope for this harness.
