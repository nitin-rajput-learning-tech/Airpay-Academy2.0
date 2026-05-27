# NVDA Verification Evidence — 2026-05-25 (Attempt #1 — SKIPPED)

| Field                | Value                                                                          |
| -------------------- | ------------------------------------------------------------------------------ |
| **Plugin**           | `local_sentientia_live` v0.1.1-alpha (Phase E.0 a11y additions, P0 #8)         |
| **Procedure**        | [`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md) v1.0 (2026-05-24) |
| **Attempt #**        | 1 (first execution of the procedure)                                           |
| **Outcome**          | **SKIPPED — environmental gap**                                                |
| **Chip**             | Wave D2 P3 — `claude/zen-albattani-LWHr8`                                      |
| **Tester (AI)**      | Claude (Opus 4.7, 1M context) acting on Nitin Rajput's instruction             |
| **Test machine**     | Remote Linux container (Ubuntu 24.04.4 LTS, kernel 6.18.5)                     |
| **NVDA installed**   | **No — NVDA is Windows-only and cannot run in this environment**               |
| **Firefox installed**| **No — no browsers present in the container**                                  |
| **Chrome installed** | **No — no browsers present in the container**                                  |
| **Audio devices**    | **None — `/dev/snd` empty, no PulseAudio / ALSA**                              |
| **Date**             | 2026-05-25                                                                     |

---

## 1. TL;DR

The 12-scenario NVDA verification procedure could not be executed in this
session. The remote execution environment is a headless Linux container with
no Windows OS, no NVDA installation, no Firefox / Chrome browsers, and no
audio subsystem. NVDA (NonVisual Desktop Access) is a Windows-only product
from NV Access — there is no Linux build, no headless / API mode, and no
practical way to capture Speech Viewer transcripts without a live Windows
desktop.

Per the [task instructions](#3-task-context) §1 ("If NVDA is not available,
document the gap and skip with a warning"), this attempt is recorded as
**SKIPPED** with a fully populated sign-off table (all cells = SKIPPED) so
the procedure's release-gating audit trail still has a row for this date.

The **static analysis** that CAN run on Linux (ARIA contract verification —
markup vs lang strings vs procedure-expected text) is captured in
[STATIC-ANALYSIS.md](./STATIC-ANALYSIS.md) and surfaces **2 minor
doc-vs-lang mismatches** in the procedure document that the next human
tester should be aware of, plus confirmation that all 9 aria-live regions
+ 1 sr-only tally span are wired correctly in the plugin code.

The **environmental gap report** (what a human tester needs to actually
run the procedure) is captured in [ENVIRONMENT-GAP.md](./ENVIRONMENT-GAP.md).

---

## 2. Why this attempt skipped

NVDA verification is fundamentally a **human-in-the-loop, Windows-desktop**
test. It requires:

| Requirement                          | This container | Why critical                                 |
| ------------------------------------ | -------------- | -------------------------------------------- |
| Windows 10 / 11                      | ❌ Linux only  | NVDA is Win32; no Linux / WSL2 / Wine build  |
| NVDA 2024.x+                         | ❌             | Required for ARIA conformance baseline       |
| Firefox (ESR + stable)               | ❌             | Browser parity scenario reference            |
| Google Chrome (current)              | ❌             | Browser parity scenario reference            |
| Audio output (speakers / headphones) | ❌ `/dev/snd` empty | Tester must hear interruption ordering  |
| NVDA Speech Viewer                   | ❌             | Evidence-capture mechanism                   |
| Localhost XAMPP Moodle 5.1.3+        | ❌             | The plugin needs a running Moodle to render  |
| Human tester at keyboard             | ❌             | Live multi-window session interaction        |

**This is not a "can be worked around" gap.** Even with the most permissive
sandbox, an AI agent cannot substitute for a human listening to a screen
reader on a real Windows desktop — the entire point of WCAG 4.1.3
verification is that a real assistive-technology user-agent pairing emits
audible announcements at the right moment.

Static analysis (markup / ARIA-attribute / lang-string checks) is a
**different and complementary** type of verification. It can confirm that
the plugin code wires the regions correctly, but it cannot confirm that
NVDA + Firefox / Chrome actually announces them. The procedure exists
precisely because markup ≠ announcement.

See [ENVIRONMENT-GAP.md](./ENVIRONMENT-GAP.md) for the human-tester runbook
needed to clear this gap.

---

## 3. Task context

Source: Wave D2 P3 testing chip (`claude/zen-albattani-LWHr8`), instructed
by Nitin Rajput on 2026-05-25. Verbatim acceptance criteria:

> **DO:**
> 1. Verify NVDA 2024.x+ is available on the test machine. If not, document
>    the gap and skip with a warning. (NVDA is free; install from
>    <https://www.nvaccess.org/download/>)
> 2. Set up the test fixture per the rubric §2: 1 trainer account + 1 live
>    session with 5 slides.
> 3. Run all 12 scenarios in Firefox + Chrome.
> 4. Save evidence to `docs/visual-evidence/2026-05-25/nvda-verification/...`.
> 5. Fill out the sign-off table with pass/fail per scenario per browser.
> 6. For any FAIL: file a follow-up task.
> 7. PROJECT-STATE.md H2 with summary.
>
> **ACCEPTANCE:** Sign-off table fully populated. Evidence captured.
> BLOCKING defects (if any) are filed as separate spawn tasks. CI green.

Step 1's "if not, document the gap and skip with a warning" clause is the
governing instruction for this attempt.

---

## 4. Sign-off table — Attempt #1 (2026-05-25)

This is the canonical sign-off record for this attempt, populated per
NVDA-VERIFICATION-PROCEDURE.md §10. All cells are **SKIPPED** with the same
root cause; the per-scenario rows are kept so future attempts can paste their
PASS/FAIL into a row of the same shape.

| #  | Scenario name                            | Severity     | Firefox result | Firefox version  | Chrome result | Chrome version | Evidence file (FF) | Evidence file (CR) | Tester initials | Date       | Notes |
|----|------------------------------------------|--------------|----------------|-------------------|----------------|------------------|--------------------|--------------------|------------------|------------|-------|
| 1  | Trainer audience-count region            | BLOCKING     | SKIPPED        | n/a (no browser)  | SKIPPED        | n/a (no browser) | scenario-01/firefox/ (empty) | scenario-01/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.1). |
| 2  | Trainer response-count region            | BLOCKING     | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-02/firefox/ (empty) | scenario-02/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.2). |
| 3  | Result-panel sr-only tally summary       | BLOCKING     | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-03/firefox/ (empty) | scenario-03/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.3). XSS-safe textContent path verified. |
| 4  | Result-panel region landmark             | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-04/firefox/ (empty) | scenario-04/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.4). |
| 5  | Bar-chart accessible name                | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-05/firefox/ (empty) | scenario-05/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.5). |
| 6  | Audience session-ended (assertive)       | BLOCKING     | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-06/firefox/ (empty) | scenario-06/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS WITH DOC-NOTE: procedure §6 "Expected" line omits the actual `audience_session_ended_body` prefix "Thanks for participating." (see STATIC-ANALYSIS.md §3.6, F-1). |
| 7  | Audience waiting-for-question (polite)   | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-07/firefox/ (empty) | scenario-07/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.7). |
| 8  | Audience current-question landmark       | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-08/firefox/ (empty) | scenario-08/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.8). |
| 9  | Audience response-saved (assertive)      | BLOCKING     | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-09/firefox/ (empty) | scenario-09/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS (see STATIC-ANALYSIS.md §3.9). |
| 10 | Audience already-responded (polite)      | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-10/firefox/ (empty) | scenario-10/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review PASS WITH DOC-NOTE: procedure says "this question" but lang string `audience_already_responded` says "this slide" (see STATIC-ANALYSIS.md §3.10, F-2). |
| 11 | Result panel on audience side (regress)  | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-11/firefox/ (empty) | scenario-11/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; regression of S4 — same template, no separate static finding. |
| 12 | sr-only tally at high SSE frequency      | NON-BLOCKING | SKIPPED        | n/a               | SKIPPED        | n/a              | scenario-12/firefox/ (empty) | scenario-12/chrome/ (empty) | CL (AI)          | 2026-05-25 | Env gap — see §2; static review N/A (stress-test scenario, requires runtime load gen). |

**Stress-test legend:**
- `SKIPPED` per row = attempt did not execute. Not a defect against the plugin.
- The 12 empty `scenario-NN/{firefox,chrome}/` sub-folders are created as
  placeholders so the next attempt has the expected layout ready.

### 4.1 Final sign-off (Attempt #1)

```
PHASE E.0 a11y VERIFICATION — SIGN-OFF
======================================
Plugin version tested:   local_sentientia_live 0.1.1-alpha
NVDA version:            n/a — NVDA not installed (Linux container)
Firefox version:         n/a — Firefox not installed
Chrome version:          n/a — Chrome not installed
Test date:               2026-05-25
Tester (QA):             Claude (Opus 4.7) — AI agent, Wave D2 P3 chip
PM acknowledgement of NON-BLOCKING fails:
                         n/a — no scenarios ran; nothing to ack
Cleared to ship Phase E.1+ ?   NO — verification did not run.
                                  Manual NVDA pass on Windows still required.
```

---

## 5. BLOCKING defects filed this attempt

**None.** No scenarios ran, so no announcements could be observed.
Static analysis (see [STATIC-ANALYSIS.md](./STATIC-ANALYSIS.md)) found **two
NON-BLOCKING doc-clarity issues** in the procedure document itself, not in
the plugin code:

- **F-1** — Procedure §6 Scenario 6 expected text omits the lang-string
  prefix `Thanks for participating. ` (header `audience_session_ended_body`
  in `lang/en/local_sentientia_live.php:315`). The actual announced text
  will include this prefix; the rubric's exact-match expectation should
  either be relaxed or updated. NON-BLOCKING — under §9 rubric variance
  tolerance ("punctuation / number-format variance NVDA verbosity
  tolerated") an extra sentence prefix is arguably within spec, but the
  next tester will benefit from a precise expected string.
- **F-2** — Procedure §6 Scenario 10 expected text reads "this question"
  but lang string `audience_already_responded` (line 317) says "this
  slide". The actual announcement will say "slide"; the procedure expects
  "question". NON-BLOCKING — same variance rubric, but better to fix the
  procedure (or the string) before the next tester hits the discrepancy.

Both findings are doc-only and do not block Phase E.1+ ship. They are
listed as **follow-up doc-edit tasks**, not separate spawned chips:

- **TODO-1** (post-attempt-2 if confirmed): align procedure §6 Scenario 6
  "Expected" verbatim with the actual `audience_session_ended_body`
  string, OR add a `Thanks for participating.` prefix to the variance
  rubric in §9.
- **TODO-2** (post-attempt-2 if confirmed): same alignment exercise for
  Scenario 10 — either change procedure text to "slide" or change the
  string to "question". Lang-string change has Hindi-parity downstream
  cost; doc edit is cheaper.

These TODOs are intentionally **not** filed as separate GitHub-style
chips because (a) they are pure doc edits, (b) the human tester running
attempt #2 will hit the same observation and is the right party to
confirm whether to fix the doc or the string, and (c) attempt #2 may
discover larger real defects that should be batched with these
NON-BLOCKING items.

---

## 6. What this attempt DID deliver

- ✅ **Environmental-gap report** ([ENVIRONMENT-GAP.md](./ENVIRONMENT-GAP.md))
  — runbook for the next human tester. Lists every component they need
  installed, the per-scenario evidence file paths, and where the
  Windows-side test fixture should live.
- ✅ **Static analysis** ([STATIC-ANALYSIS.md](./STATIC-ANALYSIS.md)) —
  read of the 5 plugin source files referenced in the procedure (`amd/src/chart_updater.js`,
  `templates/result_panel.mustache`, `templates/result_bar_chart.mustache`,
  `audience/play.php`, `trainer/run.php`) confirming all 9 aria-live
  regions + 1 sr-only tally span are wired correctly per the procedure
  contract.
- ✅ **Sign-off table populated** with SKIPPED rows so the audit trail has
  a documented attempt for 2026-05-25.
- ✅ **12 empty scenario folders** prepared with `{firefox,chrome}/`
  sub-folders so the next attempt has zero filesystem setup.
- ✅ **Doc-clarity findings F-1 and F-2** flagged for attempt #2 reviewer.

## 7. What this attempt did NOT deliver (gap left for human tester)

- ❌ Live NVDA Speech Viewer transcripts per scenario per browser.
- ❌ Browser screenshots at announcement moment.
- ❌ Audio recordings for BLOCKING scenarios (6, 9, 1, 2, 3).
- ❌ Firefox ↔ Chrome parity confirmation.
- ❌ Browse-mode vs focus-mode behaviour verification.
- ❌ Scenario 12 stress test under realistic SSE load.

A complete NVDA pass on a Windows test machine is **still required** before
Phase E.1+ ships. This attempt is a **gap-documentation event**, not a
verification event.

---

## 8. Files in this folder

| File                      | Purpose                                                                        |
| ------------------------- | ------------------------------------------------------------------------------ |
| `README.md`               | This file — canonical sign-off record for Attempt #1.                          |
| `ENVIRONMENT-GAP.md`      | Runbook for the next human tester: what they need installed, where to put files. |
| `STATIC-ANALYSIS.md`      | Per-scenario static review of plugin code (markup vs procedure-expected text). |
| `scenario-NN/firefox/`    | Empty placeholder — next attempt drops `.png` + `.txt` + optional `.mp4` here. |
| `scenario-NN/chrome/`     | Empty placeholder — next attempt drops `.png` + `.txt` + optional `.mp4` here. |

---

## 9. References

- Procedure source: [`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md) v1.0
- Plugin source root: `moodle-enhancement/local/sentientia_live/`
- Plugin version: `local_sentientia_live` v0.1.1-alpha (Phase E.0 P0 #8 a11y additions)
- Predecessor PROJECT-STATE H2 (procedure-doc ship):
  `moodle-enhancement/PROJECT-STATE.md` § "♿ P2 cutover-prep — NVDA verification procedure for `local_sentientia_live` (2026-05-24)"
- WCAG mapping: 4.1.3 Status Messages (AA), 1.3.1 Info & Relationships (A),
  2.4.7 Focus Visible (AA)
- NVDA homepage: <https://www.nvaccess.org/>
- NVDA download: <https://www.nvaccess.org/download/>
