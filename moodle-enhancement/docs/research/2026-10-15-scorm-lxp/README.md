# SCORM / LXP study - Sentientia Content Engine (2026-10-15)

Nitin's brief (2026-10-15): study the report *"Architectural Deep Dive: Automated Multilingual E-Learning Pipelines, Interactive Scenario Engines, and End-to-End SCORM Compilation for Enterprise LMS"*, compare it and the market with Sentientia as it exists in code, produce a staged build plan, and study modern enterprise UI/UX with clickable prototypes. His ten scope answers are recorded in `STUDY.md`.

**Start with `STUDY.md` (the ten findings, then section 11 for the plan) or, for leadership, `../../business/SENTIENTIA-CONTENT-ENGINE-CSUITE-BRIEF-2026-10-15.docx`.**

## What is here

| Path | What it is | How to use it |
|---|---|---|
| `STUDY.md` | The full study: ten findings, report critique, market landscape (20 platforms), scored matrix, Sentientia position from code, architecture, localisation, speech, compliance, UX, staged plan, cost model, pilot, risks, sources | built from `study-src/` (`python -I tools/assemble_study.py study-src/study-sections study-src/snippets STUDY.md`) so figures can be refreshed when measured costs replace planning values |
| `matrix/capability-matrix.csv`, `matrix/scoring-method.md` | 51 criteria x 20 vendors plus Sentientia (today, after Stage 1, after Stage 3), a note on every cell | open in Excel; `matrix/vendor-scores/` holds each researcher's raw scores and confidence |
| `sources/R1` to `R6` | Seven cited research notes (vendors in three notes, TTS/ASR and India GPU costs, standards and compliance, UX patterns, learning science) | every market claim in `STUDY.md` points here as `[R1:16]` |
| `prototypes/index.html` | Six clickable prototypes on the real design tokens: learner home, course player, authoring studio, localisation review, admin analytics, scenario editor; light/dark, LTR/RTL, 1440 and 375 px; screenshots of every view | open in Chrome or Edge from the file system; `prototypes/README.md` has what was checked and a self-assessment |
| `pilot/` | The pilot harness: canonical course schema v0.1, a BFSI fixture course, a stdlib-only compiler to SCORM 1.2 / SCORM 2004 4th Ed. / cmi5 with hardened runtimes, package validation, a headless learner simulation, a stub LMS, mock-mode runs, 55 tests and the live-run checklist | `python -I run_pilot.py --selftest`, then `--mode mock --preview`; see `pilot/README.md`, `pilot/LIVE-RUN-CHECKLIST.md` and `pilot/evidence/` |
| `../../adr/ADR-034-content-pipeline-architecture.md` | The architecture decision the plan implements (Proposed) | accept, amend or reject |
| `../../business/SENTIENTIA-CONTENT-ENGINE-CSUITE-BRIEF-2026-10-15.{md,docx}` | C-suite brief in the Product Guide format | for leadership |
| `../../business/SENTIENTIA-CONTENT-ENGINE-DECK-2026-10-15.pptx` | Presentation deck, built locally | for leadership |
| `tools/` | extraction, score merging, matrix building, snippet and study assembly | stdlib Python |

## Rules the study followed

No paid API call was made (Anthropic, ElevenLabs, Gamma, any TTS SaaS); the pilot ran in mock mode and its live path was only shown refusing to start. No Moodle plugin code was changed; nothing was deployed; C:\xampp, databases, UAT and production were not touched. Every market claim is cited and vendor marketing is labelled. Sentientia is described from code; disagreements with the written record are listed in `STUDY.md` section 5.5. Matrix scores for the vendors are the researchers' reading of public pages, scored low where only marketing exists; the Sentientia "after Stage 1 / Stage 3" columns are targets.

## Workspace

The research workspace (source report extraction, agent notes, build staging) lives outside the repository at `D:/Claude Local/research/scorm-lxp-2026-10-15/`; the report itself is not committed (it is Nitin's document). The extraction script is `tools/extract.py`.
