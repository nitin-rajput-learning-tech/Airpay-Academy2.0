# Sentientia pilot harness - canonical course JSON to SCORM 1.2, SCORM 2004 4th Edition and cmi5

Reference implementation for ADR-034 (D1-D3). It takes one canonical course document (SCD v0.1), fills the languages, makes narration audio and captions, compiles three standards from the same document and validates what shipped. **Mock mode is the default and never touches the network.** The live path exists, is gated, and has never been run (see `LIVE-RUN-CHECKLIST.md`).

Standard library only (Python 3.10+). Node.js is optional: it adds a JavaScript syntax check and a headless learner simulation of every package.

## Run it

```
cd moodle-enhancement/docs/research/2026-10-15-scorm-lxp/pilot
python -I run_pilot.py --selftest                                   # 55 tests, about 2 minutes
python -I run_pilot.py --mode mock --preview --out out/run-001      # all 13 languages, 3 standards, pseudo-localised
python -I run_pilot.py --mode mock --out out/run-002                # learner build: approved languages only (en)
python -I run_pilot.py --mode mock --preview --package-mode per_language --langs en,hi,ar --out out/run-003
python -I run_pilot.py --estimate                                   # pre-flight translation cost, no network, no files
```

Outputs per run: `scorm12/*.zip`, `scorm2004/*.zip`, `cmi5/*.zip`, `course.compiled.json`, `validation-report.md|json`, `audit/audit.jsonl`. Packages are byte-stable: the same inputs give the same sha256 (a test proves it), which is what the PHP compiler's contract tests will rely on.

## What is here

| Path | What it is |
|---|---|
| `schema/sentientia-course.schema.json` | the SCD v0.1 JSON Schema with twelve validation rules R01-R12 |
| `fixtures/course-upi-dispute.json`, `fixtures/sample-sop-upi-dispute.md` | a hand-authored BFSI course (four slides, nine-node branching scenario, five-question quiz across four question types, 13 languages declared, Hindi partly human-written) and the synthetic SOP it came from |
| `lib/schema_lite.py` | JSON Schema subset validator plus the R01-R12 rules (graph integrity, state budget, protected terms, media, review state, ids, PII status) |
| `lib/pii.py` | PII scanner: Aadhaar (Verhoeff), PAN, Indian mobile, email, IFSC, card (Luhn); values are masked, never stored |
| `lib/translate.py` | mock pseudo-localisation, live request builder with cost estimate and spend cap (never executed) |
| `lib/tts.py` | mock silent MP3 + WebVTT + word timestamps; `local-cmd` adapter for an owner-supplied engine |
| `lib/packager.py`, `lib/validate_package.py` | the three compilers and the post-build package checks (Z/S/P/M/B/J groups) |
| `lib/player/` | the static player: `core.js` (pure logic), three runtime adapters, `player.js` (DOM), `player.css`; no external requests, no `innerHTML` |
| `lib/pipeline.py`, `lib/audit.py`, `lib/report.py` | orchestration, hash-chained audit log, validation report |
| `tests/` | 55 unit and end-to-end tests; `smoke_package.js` is the headless learner simulation against a fake LMS and fake LRS |
| `tools/lms_stub.html` | a stub SCORM 1.2 / 2004 LMS for driving a package in a real browser (development only, not Moodle) |
| `evidence/` | screenshots, the canonical mock run report, audit log and packages, the live-refusal transcript (see `evidence/README.md`) |

## What the runtimes do (the Moodle hardening the report describes)

- **SCORM 1.2:** `window.API` discovery up the parent and opener chain; status `incomplete` then `passed`/`failed` against the mastery score the LMS supplies (`cmi.student_data.mastery_score`) with the course value as fallback; `cmi.core.exit = "suspend"` before `LMSFinish`; commits batched; finish on `pagehide`.
- **State:** `cmi.suspend_data` is a versioned compact token (`v1|lang|block|visited-bitmask|scenario edge ids|quiz answers`), deflate+base64url above 3,500 characters, a documented truncation ladder, a hard ceiling under the 4,096-character limit. The worst case for the fixture is 104 characters; rule R05 rejects a scenario whose worst case would not fit.
- **Interactions:** `cmi.interactions` carry ids, never display text, and stay under 255 characters (rule R11).
- **SCORM 2004 4th Ed.:** separate `completion_status` and `success_status`, scaled score, `cmi.exit`, `progress_measure`, `imsss` primary objective with `minNormalizedMeasure`.
- **cmi5:** launch-parameter handshake (fetch token, `LMS.LaunchData`), defined statements `initialized`, `completed`, `passed`/`failed`, `terminated` with the cmi5 context, `answered` per decision and question, `terminated` through `sendBeacon`, retry queue.

## What mock mode proves, and what it does not

Proves: the schema and rules; one document becoming three valid packages; the runtimes against fake LMS/LRS implementations that enforce the limits Moodle enforces; resume after reload; pass and fail at the mastery score; RTL direction per language; the PII gate; the audit chain; byte-stable builds; and that the live path refuses to start without its gates.

Does **not** prove: translation quality (mock text is `[xx] source`), voice quality (mock audio is silent), Moodle 5.3 behaviour (Stage 1 UAT work), LRS acceptance of the cmi5 statements, or SCORM Cloud / ADL conformance (Stage 3). The DOM player was exercised in a real browser against the stub LMS (SCORM 1.2 and 2004, see `evidence/`); cmi5 was exercised headlessly only.

## Defects the study's own testing found (kept as regression tests)

1. The three runtime adapters passed a bare `setTimeout` as a method (`{setTimeout: setTimeout}`), which throws "Illegal invocation" in browsers although every Node test passed. Found by the browser run; fixed; `test_player_runtimes_do_not_pass_a_bare_setTimeout_as_a_method` guards it.
2. The PII scanner missed `98765 43210` style mobile numbers (a space after the fifth digit). Fixed; covered by the PII tests.
3. The first player layout overflowed horizontally at 375 px and showed an empty caption bar; fixed (collapsible contents, `minmax(0,1fr)` grid, hidden empty captions) and re-checked at 375 px for every block.

## Known limits

Fonts are not bundled (Noto subsetting is Stage 1); cmi5 sends the first passed/failed result only; no SaaS TTS adapter is in the harness (the Stage 1 speech service owns the fallback adapter); XML parsing uses the standard library after refusing any DOCTYPE or entity declaration (the harness only reads packages it built itself; do not point it at untrusted ZIPs without defusedxml).
