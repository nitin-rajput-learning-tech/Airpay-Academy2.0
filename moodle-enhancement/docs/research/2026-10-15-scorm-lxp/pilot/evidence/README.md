# Pilot evidence - 2026-10-15

Everything here was produced offline. No paid API was called; the live path was only shown refusing to start.

## Runs

| Folder | Command | Result |
|---|---|---|
| `run-mock-001/` | `run_pilot.py --mode mock --preview` | 3 packages (SCORM 1.2, SCORM 2004 4th Ed., cmi5), 13 languages each, 21/21 and 19/19 package checks, runtime smoke PASS, 14 audit entries, chain valid. Contains the SCORM 1.2 and SCORM 2004 ZIPs (mock, preview: silent audio, pseudo-localised text, **not for learners**), the report, `course.compiled.json` and the audit log. The cmi5 ZIP is not committed: the repository's pre-commit CHECK 8 only understands `imsmanifest.xml` and aborts on a cmi5 package (finding for the hook owner); regenerate it with the command above, its sha256 is in the report |
| `run-mock-002-per-language/` | `--preview --package-mode per_language --langs en,hi,ar` | 9 packages (3 standards x 3 languages), all checks pass, smoke PASS |
| `run-mock-003-learner/` | `--mode mock --standards scorm12,scorm2004` | learner build: only the approved language (`en`) is carried; `hi` (in review) and the machine languages are excluded, with the reason in the report |
| `estimate.txt` | `--estimate` | pre-flight translation estimate for the fixture: 90 units x 12 languages, about INR 143 at USD 3/15 per million tokens |
| `live-refusal.txt` | `--mode live` | refusal listing all three missing gates; nothing written |

The test suite (`python -I run_pilot.py --selftest`) reported `Ran 55 tests ... OK` on 2026-10-15.

## Screenshots (real browser, the compiled SCORM 1.2 package)

| File | What it shows |
|---|---|
| `01-player-slide-desktop-light.png` | 1440 px, light: contents with per-block status, bullets, audio and captions panel |
| `02-player-scenario-consequence-desktop-dark.png` | dark: a decision, its consequence reveal and the running scenario score |
| `03-player-quiz-graded-desktop-light.png` | graded quiz: per-option feedback, partial credit on the match question, failed attempt with Try again |
| `04-player-rtl-arabic-pseudo-desktop-light.png` | Arabic selected: `dir="rtl"` on the page, mirrored layout (text is pseudo-localised in mock mode) |
| `05-player-mobile-hindi-375-light.png` | 375 px, Hindi, human-written strings, contents collapsed, no horizontal scroll |
| `06-player-mobile-scenario-375-dark.png` | 375 px dark scenario with consequence and breadcrumb |
| `07-stub-lms-scorm12-passed.png` | the stub LMS next to the player: the API call log shows id-only interaction records, score 100, status `passed` and a compact `suspend_data` (the `exit = suspend` and `LMSFinish` calls are written when the SCO unloads, so they appear after the reload and are asserted in the headless smoke) |

## Browser session results (stub LMS, `tools/lms_stub.html`)

| Standard | Final LMS status | Score | Interactions | `suspend_data` | After reloading the SCO |
|---|---|---|---|---|---|
| SCORM 1.2 | `passed` | 100 | 8 (3 decisions + 5 questions) | 102 chars (limit 4,096) | resumed at the summary with all 6 blocks visited, scenario path `e1a,e2b,e3a`, quiz submitted |
| SCORM 2004 | `completed` / `passed`, scaled 1 | 100 | 8 | 102 chars | identical resume |

cmi5 was exercised headlessly only (fake LRS: statements `initialized`, `answered` x 8, `completed`, `passed`, `terminated` via `sendBeacon`; resume document in the LRS state API).

No console errors other than the missing `favicon.ico` of the local test server.
