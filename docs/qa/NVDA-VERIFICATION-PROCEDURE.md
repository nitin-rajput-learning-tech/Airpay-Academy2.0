# NVDA Screen-Reader Verification Procedure — `local_sentientia_live`

| Field             | Value                                                                                            |
| ----------------- | ------------------------------------------------------------------------------------------------ |
| **Document ID**   | QA-A11Y-NVDA-001                                                                                 |
| **Version**       | 1.0                                                                                              |
| **Owner**         | QA Lead — Airpay Academy / Sentientia LMS                                                        |
| **Author**        | Claude (P2 cutover-prep chip)                                                                    |
| **Created**       | 2026-05-24                                                                                       |
| **Status**        | Active — gating for Phase E.1+ ship                                                              |
| **Plugin**        | `local_sentientia_live` v0.1.1-alpha (Phase E.0 a11y additions)                                  |
| **WCAG criteria** | 4.1.3 Status Messages (AA), 1.3.1 Info & Relationships (A), 2.4.7 Focus Visible (AA)             |
| **Related ADR**   | (no a11y ADR yet — this procedure documents acceptance contract for the in-code aria-live work)  |

---

## 0. Reader's TL;DR

Phase E.0 of `local_sentientia_live` added **9 aria-live regions and 1 sr-only
tally summary** across the trainer, audience, and shared result-panel
surfaces. Before Phase E.1 (or any further plugin work) ships, a tester must
run the **12 scenarios** below against NVDA + Firefox **and** NVDA + Chrome,
log results in the sign-off table (§10), and attach evidence (§8). A single
**BLOCKING** failure stops the release.

This document is the QA acceptance contract. The plugin code already wires
the regions; this procedure verifies the announcements actually fire.

---

## 1. Why this procedure exists

Phase E.0 added screen-reader announcements to the live engagement plugin
so blind and low-vision users can participate in real-time polls / quizzes
alongside sighted users. The work introduced markup like:

- `role="status" aria-live="assertive" aria-atomic="true"` on response-saved
  confirmation (audience/play.php line 274-281)
- `role="status" aria-live="polite" aria-atomic="true"` on the trainer's
  live audience-count and response-count counters (trainer/run.php line 124-163)
- `<span class="sr-only" aria-live="polite" aria-atomic="true"
  data-live-tally-summary>` inside the result panel template, written to by
  `chart_updater.js` on every `sentientia-live:response_added` CustomEvent
- `role="region"` + `aria-label` landmarks on the result panel and current-question container
- `role="img"` + `aria-label` on the bar-chart partial

Markup alone does not guarantee announcement. Browsers / screen-reader pairings
differ on:

- Whether `aria-atomic="true"` re-reads the full region or just the changed node
- Whether `aria-live="assertive"` interrupts current speech or queues
- Timing thresholds (most SRs ignore changes within 50 ms of each other)
- Whether dynamically injected DOM is announced at all

NVDA + Firefox is the de-facto reference combination for ARIA conformance
testing in the Indian enterprise market; NVDA + Chrome is the practical
fallback for users on corporate-managed builds where Firefox is unavailable.
Both must work.

---

## 2. Required test environment

| Component               | Requirement                                                                  |
| ----------------------- | ---------------------------------------------------------------------------- |
| **Operating system**    | Windows 10 (build 19045+) or Windows 11. NVDA does not run on macOS / Linux. |
| **Screen reader**       | NVDA 2024.1 or later. Download from <https://www.nvaccess.org/download/>.    |
| **Browser A**           | Mozilla Firefox — current ESR **and** current stable. Test both.             |
| **Browser B**           | Google Chrome — current stable channel.                                      |
| **Audio**               | Speakers or headphones. Speech Viewer (text mirror) also required for evidence capture. |
| **Network**             | Localhost XAMPP Moodle 5.1.3+ at `http://localhost:8080/moodle/` (matches dev env from CLAUDE.md). Production smoke-test optional but encouraged. |
| **Viewport**            | Desktop 1366×768 primary; mobile 590×844 (Chrome devtools) for the §6 scenarios marked "mobile pass". |
| **Tenant**              | Airpay (costcenterid=1) primary; Public (77) and ZEEA (177) optional unless tenant-specific markup changes ship later. |
| **Test plugin version** | `local_sentientia_live` 0.1.1-alpha or later (verify in Site admin → Plugins). |
| **Feature flags**       | `live.enabled = true`, `live.realtime.enabled = true` (both default ON for testers). |

### 2.1 NVDA configuration baseline

Open NVDA → Preferences → Settings, confirm:

- **General → Log level:** `info` (so SR events land in `%APPDATA%\nvda\nvda.log` for evidence)
- **Speech → Synthesizer:** eSpeak NG (default — keeps tests reproducible across machines)
- **Speech → Rate:** 50 % (default — too fast hides ordering bugs)
- **Browse mode → Use screen layout when supported:** ON
- **Browse mode → Automatic say-all on page load:** OFF (so we can hear region announcements deliberately)
- **Document formatting → Report landmarks and regions:** ON
- **Document formatting → Report headings:** ON

Open NVDA → Tools → **Speech viewer** (or `NVDA+N → Tools → Speech viewer`)
and pin the window — every announcement appears as a line of text, which is
how we capture evidence (§8).

### 2.2 NVDA keystroke cheat sheet (used in this procedure)

| Action                                       | Keystroke                |
| -------------------------------------------- | ------------------------ |
| Toggle browse / focus mode                   | `NVDA+SPACE`             |
| Move to next region / landmark               | `D`                      |
| Move to next heading                         | `H`                      |
| Move to next form field                      | `F`                      |
| Move to next button                          | `B`                      |
| Read current line                            | `NVDA+UP`                |
| Read from cursor to end of document          | `NVDA+DOWN`              |
| Open Speech Viewer                           | `NVDA+N → Tools → Speech viewer` |
| Open NVDA log viewer                         | `NVDA+F1`                |

`NVDA` modifier is **Insert** by default, or `CapsLock` on laptop layout.

---

## 3. Browse mode vs focus mode primer

NVDA exposes two modes for web content:

- **Browse mode** (default on page load): NVDA reads the page like a document.
  Single-letter keys jump between regions / headings / forms. Live-region
  changes are announced spontaneously.
- **Focus mode** (auto-engages when focus enters editable controls): NVDA
  passes keystrokes through to the form field; the SR no longer reads the
  page autonomously. Live-region changes **are still announced** in focus
  mode — that's the whole point of `aria-live`.

Toggle: `NVDA+SPACE`. NVDA chimes when the mode switches.

**Why this matters for our scenarios:**

- §6 scenarios 1, 2, 5 (trainer counters + bar-chart label) are read in
  browse mode while the trainer reads the page passively.
- §6 scenarios 3, 6-10 (audience response + state changes) trigger while
  focus is on a form field (radio, textarea); they MUST still announce in
  focus mode. If they don't, that's a `BLOCKING` failure.
- §6 scenarios 4, 11-12 (region / bar-chart landmarks) are exercised in
  browse mode using `D` (next region) navigation.

---

## 4. Pre-test data setup (one-time)

1. Sign in to Moodle 5 (`http://localhost:8080/moodle/`) as a user with the
   capability `local/sentientia_live:create` (default: `Trainer` role).
2. Visit `/local/sentientia_live/trainer/index.php` → "New session".
3. Title: `NVDA QA Test {YYYY-MM-DD}`. Save.
4. Add 5 slides (one of each type so every result-panel branch is exercised):
   - Slide 1 — Multiple choice — 4 options
   - Slide 2 — Quiz — 4 options, mark option 2 correct
   - Slide 3 — Rating — scale 1-5
   - Slide 4 — Word cloud — max length 30
   - Slide 5 — Open-ended — max chars 280
5. From the trainer dashboard, click **Start live session**. Capture the
   6-digit join code.
6. In a **second browser profile** (or a second machine — recommended so
   NVDA is only running in one window), open
   `/local/sentientia_live/audience/join.php`. Enter the join code and a
   display name `QA-Audience-01`. Submit → arrives at `audience/play.php`.
7. Return to the trainer window, NVDA running. You are now on
   `/local/sentientia_live/trainer/run.php?id=X`. Begin §6 scenarios.

> **Note:** If you cannot run two NVDA-aware browsers simultaneously, use a
> sighted assistant to drive the audience window. Announcements occur on the
> NVDA window only.

---

## 5. Scenario format

Each §6 scenario uses this layout:

```
### Scenario N — <name>
| Surface       | <URL or file>                                  |
| Element       | <CSS / accessible-name target>                 |
| ARIA          | role / aria-live / aria-atomic / aria-label    |
| Action        | <what the tester does to trigger>              |
| Expected      | "<verbatim expected NVDA Speech Viewer line>"  |
| WCAG          | <criterion number>                             |
| Mode          | browse / focus / either                        |
| Browser parity| Firefox + Chrome (both must pass)              |
| Severity      | BLOCKING / NON-BLOCKING                        |
```

The **Expected** field shows the announcement as Speech Viewer renders it.
Capitalisation and punctuation in Speech Viewer may differ slightly from
the visual page (e.g. NVDA may say "five" for "5", depending on
verbosity); the rubric in §9 covers acceptable variance.

---

## 6. Scenarios

### Scenario 1 — Trainer audience-count region

| Surface        | `/local/sentientia_live/trainer/run.php?id=<sessionid>`                                                          |
| Element        | `.alert.alert-info[role=status]` containing `#sentientia-audience-count`                                          |
| ARIA           | `role="status"` `aria-live="polite"` `aria-atomic="true"` `aria-label="Live audience count"`                      |
| Action         | While trainer page is open and NVDA is reading, have a fresh audience tab join the session via `audience/join.php`. |
| Expected       | Speech Viewer line: `Live audience count Audience : 1 online now Total slides : 5` (full region re-read because `aria-atomic="true"`). |
| WCAG           | 4.1.3 Status Messages                                                                                              |
| Mode           | browse                                                                                                             |
| Browser parity | Firefox + Chrome                                                                                                   |
| Severity       | BLOCKING                                                                                                           |

**Variant:** A second audience joins → expected announcement updates to
`Audience : 2 online now`. Then close the second audience tab → after the
heartbeat TTL (≈ 30 s) the count drops back to 1 and announces again.

### Scenario 2 — Trainer response-count region

| Surface        | `/local/sentientia_live/trainer/run.php?id=<sessionid>` (slide 1 active)                                            |
| Element        | `.alert.alert-secondary[role=status]` containing `#sentientia-response-count`                                       |
| ARIA           | `role="status"` `aria-live="polite"` `aria-atomic="true"` `aria-label="Live response count"`                        |
| Action         | Have audience tab submit a vote on the current multichoice slide.                                                   |
| Expected       | Speech Viewer line: `Live response count Responses received : 1`                                                    |
| WCAG           | 4.1.3 Status Messages                                                                                               |
| Mode           | browse                                                                                                              |
| Browser parity | Firefox + Chrome                                                                                                    |
| Severity       | BLOCKING                                                                                                            |

**Variant:** Second audience submits → announcement updates to `Responses received : 2`.

### Scenario 3 — Result-panel sr-only tally summary

| Surface        | `/local/sentientia_live/trainer/run.php` OR audience view with `show_results_to_audience=true`                      |
| Element        | `[data-live-tally-summary]` (visually hidden span inside `.sentientia-results-panel`)                               |
| ARIA           | `aria-live="polite"` `aria-atomic="true"` (no `role`; the parent `.sentientia-results-panel` carries `role="region"`) |
| Action         | Audience submits a response → `chart_updater.js` writes `<count> <suffix>` into the span via `textContent` only.    |
| Expected       | Speech Viewer line: `1 responses` (then `2 responses`, `3 responses`, …). Suffix string is `live_results_total_suffix` (English default). |
| WCAG           | 4.1.3 Status Messages                                                                                               |
| Mode           | either                                                                                                              |
| Browser parity | Firefox + Chrome                                                                                                    |
| Severity       | BLOCKING                                                                                                            |

**Important — verify the textContent path:** Open browser devtools while
testing → inspect the `[data-live-tally-summary]` node → confirm
`innerHTML` only ever contains text nodes, never markup. This is an XSS
defence; the chip's `chart_updater.js` writes via `textContent` only.
Markup in the span would be both an a11y bug (SR announces tags) and a
security bug.

### Scenario 4 — Result-panel region landmark

| Surface        | `/local/sentientia_live/trainer/run.php` OR audience view with results shown                                        |
| Element        | `.sentientia-results-panel`                                                                                          |
| ARIA           | `role="region"` `aria-label="Live results"`                                                                          |
| Action         | In browse mode, press `D` to jump to the next region.                                                                |
| Expected       | Speech Viewer line: `Live results region` — the landmark is announced, focus lands at the region's first child.     |
| WCAG           | 1.3.1 Info & Relationships, 2.4.1 Bypass Blocks                                                                      |
| Mode           | browse                                                                                                               |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | NON-BLOCKING                                                                                                          |

### Scenario 5 — Bar-chart accessible name

| Surface        | Result panel (slide 1, multichoice, after at least one response submitted)                                          |
| Element        | `div[role="img"]` inside `result_bar_chart.mustache`                                                                  |
| ARIA           | `role="img"` `aria-label="Live results bar chart"`                                                                  |
| Action         | In browse mode, navigate (Tab or arrow keys) until NVDA reads the image element.                                    |
| Expected       | Speech Viewer line: `Live results bar chart graphic`                                                                |
| WCAG           | 1.1.1 Non-text Content, 1.3.1 Info & Relationships                                                                  |
| Mode           | browse                                                                                                               |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | NON-BLOCKING                                                                                                          |

**Notes:** Each `.sentientia-bar-row` exposes its label / count / percent as
plain text descendants of the image. NVDA Firefox reads them after the
`role=img` label; NVDA Chrome may treat the image as opaque (depending on
NVDA version). Both behaviours are within spec — the **label** is what
matters for the pass / fail call.

### Scenario 6 — Audience session-ended announcement (assertive)

| Surface        | `/local/sentientia_live/audience/play.php?sessionid=<id>` (NVDA on the audience window)                              |
| Element        | `div[role=status][aria-live=assertive][aria-atomic=true]` rendered when `$sess->state === STATE_ENDED`              |
| ARIA           | `role="status"` `aria-live="assertive"` `aria-atomic="true"` `aria-label="Session ended. Your responses have been recorded."` |
| Action         | From trainer window, click **End session** → trainer/end.php → audience `audience_sse` fires `session_ended` → audience page reloads → renders ended state. |
| Expected       | NVDA **interrupts current speech** (assertive); Speech Viewer line: `Session ended. Your responses have been recorded. Session ended Session ended . Your responses have been recorded .` (label + heading + body). |
| WCAG           | 4.1.3 Status Messages                                                                                                |
| Mode           | either — full page reload puts NVDA back in browse mode                                                              |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | BLOCKING                                                                                                              |

**Key check:** NVDA must **interrupt** whatever it was speaking when the
reload's first paint shows the ended-state region. If it queues behind a
long previous announcement, that's a FAIL — the user can finish a session
without ever knowing it ended.

### Scenario 7 — Audience waiting-for-question (polite)

| Surface        | `/local/sentientia_live/audience/play.php?sessionid=<id>` immediately after join, before trainer activates a slide  |
| Element        | `div[role=status][aria-live=polite]` rendered when `!$sess->current_slide_id`                                       |
| ARIA           | `role="status"` `aria-live="polite"` `aria-label="Waiting for the next question"`                                  |
| Action         | Join the session. Trainer has no slide active yet.                                                                  |
| Expected       | After page load + any prior NVDA speech finishes, Speech Viewer line: `Waiting for the next question Waiting for the next question…` |
| WCAG           | 4.1.3 Status Messages                                                                                                |
| Mode           | browse                                                                                                               |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | NON-BLOCKING                                                                                                          |

### Scenario 8 — Audience current-question region landmark

| Surface        | `/local/sentientia_live/audience/play.php?sessionid=<id>` once trainer activates a slide                            |
| Element        | `div[role=region][aria-label=Current question]` wrapping the slide title + response form                            |
| ARIA           | `role="region"` `aria-label="Current question"`                                                                     |
| Action         | In browse mode after slide activation, press `D` to jump to the next region.                                        |
| Expected       | Speech Viewer line: `Current question region` — NVDA enters the region, reads the heading + form.                    |
| WCAG           | 1.3.1 Info & Relationships, 2.4.1 Bypass Blocks                                                                      |
| Mode           | browse                                                                                                               |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | NON-BLOCKING                                                                                                          |

### Scenario 9 — Audience response-saved confirmation (assertive)

| Surface        | `/local/sentientia_live/audience/play.php` after POSTing a vote                                                     |
| Element        | `div.alert.alert-success[role=status][aria-live=assertive][aria-atomic=true]`                                       |
| ARIA           | `role="status"` `aria-live="assertive"` `aria-atomic="true"` (no `aria-label` — content acts as accessible name)    |
| Action         | Select a multichoice option → click **Submit response**. Form POSTs back to play.php; `$response_saved = true`.     |
| Expected       | NVDA **interrupts** whatever was being read; Speech Viewer line: `Response received — thanks!`                       |
| WCAG           | 4.1.3 Status Messages                                                                                                |
| Mode           | focus → switches to browse after submit (form was the focus owner)                                                  |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | BLOCKING                                                                                                              |

**Variant:** With `show_results_to_audience=true`, the response-saved
confirmation is followed by a rendered result panel. Scenarios 3, 4, 5
then apply on the audience side as well.

### Scenario 10 — Audience already-responded polite alert

| Surface        | `/local/sentientia_live/audience/play.php` reloaded after a successful response (e.g. browser back / SSE replay)    |
| Element        | `div.alert.alert-info[role=status][aria-live=polite][aria-atomic=true]`                                              |
| ARIA           | `role="status"` `aria-live="polite"` `aria-atomic="true"`                                                            |
| Action         | After scenario 9, manually reload the audience page (`F5`). With slide unchanged, `$has_responded = true` branch renders. |
| Expected       | Speech Viewer line: `You have already responded to this question` (string `audience_already_responded`).             |
| WCAG           | 4.1.3 Status Messages                                                                                                |
| Mode           | browse                                                                                                               |
| Browser parity | Firefox + Chrome                                                                                                     |
| Severity       | NON-BLOCKING                                                                                                          |

### Scenario 11 — Result-panel landmark on audience side (regression check)

Same as Scenario 4 but accessed from `audience/play.php` (when
`show_results_to_audience` is on). Both surfaces share the same Mustache
template so the markup is identical; this scenario only verifies that
NVDA's region-discovery still works inside the `pagelayout=login` chrome
the audience page uses.

| Severity | NON-BLOCKING (regression check only) |

### Scenario 12 — sr-only tally summary at high SSE frequency

| Surface        | Result panel during a stress test                                                                                  |
| Element        | `[data-live-tally-summary]` as in scenario 3                                                                       |
| ARIA           | as scenario 3                                                                                                       |
| Action         | Simulate 20 responses landing within 5 seconds (use the QA helper `php cli/seed_load.php --slide=<id> --count=20` if available, else fire 20 manual audience tabs). |
| Expected       | NVDA announces the **final** count once per de-dup window (NVDA collapses live-region updates within ≈ 200 ms). Worst-case: only the final `20 responses` is heard. Best-case: a steady tick-tock of `5 responses … 10 responses … 15 responses … 20 responses`. **Either is acceptable** — what matters is that the announcement does not stall NVDA, freeze the page, or drop the final count. |
| WCAG           | 4.1.3 Status Messages (with caveat re. update-rate guidance)                                                       |
| Mode           | either                                                                                                              |
| Browser parity | Firefox + Chrome                                                                                                    |
| Severity       | NON-BLOCKING                                                                                                         |

**Why this scenario exists:** `aria-live` is not designed for sub-second
update rates. If we ship live polls into 100-person rooms, the tally span
will update faster than NVDA can read. This scenario establishes the
known-good behaviour so QA does not file false-positive bugs against
realistic event rates.

---

## 7. Cross-browser parity expectations

| Behaviour                                | NVDA + Firefox        | NVDA + Chrome         | Action if mismatch                                          |
| ---------------------------------------- | --------------------- | --------------------- | ----------------------------------------------------------- |
| `aria-live="polite"` polite announce     | Reliable              | Reliable              | If either misses, FAIL the scenario                          |
| `aria-live="assertive"` interrupts speech | Reliable              | Reliable (2024.x+)    | If Chrome queues instead of interrupting on scenarios 6 / 9, log as BLOCKING |
| `aria-atomic="true"` re-reads full region | Yes                   | Yes (2024.x+)         | If Chrome announces only the delta, log as NON-BLOCKING and check NVDA version |
| `role="region"` landmark navigation (`D`)| Yes                   | Yes                   | If Chrome skips the landmark, log as NON-BLOCKING            |
| `role="img"` accessible name             | Reads label, then descendants | Reads label only (treats image as opaque) | Both within spec — log NON-BLOCKING note, not a defect       |
| Live region in focus mode                | Announces             | Announces             | If either suppresses announcement while in focus mode, BLOCKING |

If a discrepancy appears between Firefox and Chrome on a `BLOCKING`
scenario, file a defect under `[a11y][live-engagement]` and stop the
release until resolved.

---

## 8. Evidence capture

For each scenario, capture three artefacts and store under
`docs/visual-evidence/YYYY-MM-DD/nvda-verification/`:

1. **Screenshot of the page state** at the moment the action triggers.
   - Window: Browser viewport only (not full desktop).
   - Filename: `nvda-s{NN}-{browser}-{result}.png` — example
     `nvda-s06-firefox-pass.png`, `nvda-s09-chrome-fail.png`.
2. **Speech Viewer transcript** — copy the relevant lines from NVDA's
   Speech Viewer window into a text file.
   - Filename: `nvda-s{NN}-{browser}.txt`.
   - First line: scenario number + name. Subsequent lines: verbatim Speech
     Viewer output for the 5 seconds straddling the action.
3. **Optional: audio recording** — for BLOCKING-severity scenarios only,
   capture a 10-second OBS / Windows Game Bar clip including system audio
   so the announcement is audible on playback.
   - Filename: `nvda-s{NN}-{browser}.mp4` (or `.mkv`).

Hub README (`docs/visual-evidence/YYYY-MM-DD/nvda-verification/README.md`)
should list every captured file with one-line context, and link back to
this procedure. Use the template at the bottom of this section.

### 8.1 Evidence folder README template

```markdown
# NVDA Verification Evidence — YYYY-MM-DD

Plugin: local_sentientia_live vX.Y.Z
Tester: Name <email@airpay.co.in>
NVDA: 2024.X
Firefox: 1XX.0
Chrome: 1XX.0.XXXX.XX

Procedure: ../../qa/NVDA-VERIFICATION-PROCEDURE.md

## Evidence index

| Scenario | Browser  | Result | Screenshot                      | Transcript                  | Audio (if BLOCKING)   |
|----------|----------|--------|---------------------------------|-----------------------------|-----------------------|
| S01      | Firefox  | PASS   | nvda-s01-firefox-pass.png       | nvda-s01-firefox.txt        | (n/a)                 |
| S01      | Chrome   | PASS   | nvda-s01-chrome-pass.png        | nvda-s01-chrome.txt         | (n/a)                 |
| …        | …        | …      | …                               | …                           | …                     |
```

---

## 9. Pass / Fail rubric

| Result      | Definition                                                                                                                                                                                       |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **PASS**    | Expected announcement appears in Speech Viewer within **2 seconds** of the trigger action. Punctuation / number-format variance (NVDA verbosity) tolerated. Both browsers must independently PASS. |
| **FAIL**    | No announcement, an announcement with wrong content (label / value mismatch), or > 2 s delay (excluding scenario 12 stress test).                                                                |
| **BLOCKED** | Action could not be triggered (test data unavailable, plugin disabled, NVDA crash). Not a defect against the plugin — record so the scenario gets retried.                                       |

Severity escalation:

- **BLOCKING** scenarios (1, 2, 3, 6, 9) — any FAIL halts release of
  Phase E.1+ until fixed. File a P0 defect.
- **NON-BLOCKING** scenarios — FAIL is recorded, sign-off may still
  proceed with PM acknowledgement. File a P2 defect.

Number-format / verbosity variance examples that **PASS**:

- `Live response count Responses received : 1` ≡ `Live response count Responses received colon 1`
- `1 responses` ≡ `one responses` (NVDA may spell out small integers)
- `Session ended. Your responses have been recorded.` ≡ same content with extra full-stop pauses

Variance that **FAILS**:

- Wrong number (`Responses received : 2` when only one response submitted)
- Missing label (`1 responses` heard but the prefix `Live response count` is not)
- Localised string read in wrong language (English en page → Hindi announcement) — file a separate i18n defect

---

## 10. QA sign-off table

Copy this table into the evidence-folder README and fill in. Both rows per
scenario must be PASS (or NON-BLOCKING FAIL with PM ack) before sign-off.

### Attempt log

| Attempt # | Date       | Tester / chip                         | Outcome  | Evidence folder                                                                |
| --------- | ---------- | ------------------------------------- | -------- | ------------------------------------------------------------------------------ |
| 1         | 2026-05-25 | Claude (Opus 4.7) — `zen-albattani-LWHr8` | SKIPPED — Linux cloud container, no NVDA / browser / audio. Static analysis surfaced 2 NON-BLOCKING doc-clarity findings (F-1, F-2). | [`../visual-evidence/2026-05-25/nvda-verification/`](../visual-evidence/2026-05-25/nvda-verification/) |
| 2         | _(pending)_ | _(human QA at Windows workstation)_   | _(pending — runbook in evidence folder ENVIRONMENT-GAP.md)_ | _(append link when run)_                                                       |

| #  | Scenario name                            | Severity     | Firefox result | Firefox version | Chrome result | Chrome version | Evidence file (FF)        | Evidence file (CR)        | Tester initials | Date       | Notes |
|----|------------------------------------------|--------------|----------------|-----------------|----------------|-----------------|---------------------------|---------------------------|------------------|------------|-------|
| 1  | Trainer audience-count region            | BLOCKING     |                |                 |                |                 |                           |                           |                  |            |       |
| 2  | Trainer response-count region            | BLOCKING     |                |                 |                |                 |                           |                           |                  |            |       |
| 3  | Result-panel sr-only tally summary       | BLOCKING     |                |                 |                |                 |                           |                           |                  |            |       |
| 4  | Result-panel region landmark             | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |
| 5  | Bar-chart accessible name                | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |
| 6  | Audience session-ended (assertive)       | BLOCKING     |                |                 |                |                 |                           |                           |                  |            |       |
| 7  | Audience waiting-for-question (polite)   | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |
| 8  | Audience current-question landmark       | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |
| 9  | Audience response-saved (assertive)      | BLOCKING     |                |                 |                |                 |                           |                           |                  |            |       |
| 10 | Audience already-responded (polite)      | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |
| 11 | Result panel on audience side (regress)  | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |
| 12 | sr-only tally at high SSE frequency      | NON-BLOCKING |                |                 |                |                 |                           |                           |                  |            |       |

### 10.1 Final sign-off

```
PHASE E.0 a11y VERIFICATION — SIGN-OFF
======================================
Plugin version tested:   local_sentientia_live 0.1.1-alpha
NVDA version:            2024._.____
Firefox version:         _____ (ESR _____ )
Chrome version:          _____
Test date:               YYYY-MM-DD
Tester (QA):             ______________________________
PM acknowledgement of NON-BLOCKING fails:
                         ______________________________
Cleared to ship Phase E.1+ ?   YES  /  NO  (circle)
```

Sign-off must be filed alongside the evidence folder; PR / merge that
ships Phase E.1+ must link to it.

---

## 11. Defect reporting

When a scenario FAILS, file a GitHub issue with:

- **Title:** `[a11y][live-engagement] Scenario S{NN} FAIL — <one-line summary>`
- **Body:** scenario number, severity, browser + version, NVDA version,
  expected vs actual announcement, Speech Viewer transcript pasted in,
  link to evidence files.
- **Labels:** `a11y`, `live-engagement`, `wcag-4.1.3` (or relevant),
  `severity-blocking` / `severity-non-blocking`.
- **Assignee:** L&D engineering lead (rotates — check PROJECT-STATE.md
  current phase header for owner).

For BLOCKING defects, post a message in the engineering Slack channel
`#airpay-academy-eng` with the issue link. Phase E.1+ release is gated on
fix + retest.

---

## 12. References

- WCAG 2.1 — Status Messages (4.1.3): <https://www.w3.org/WAI/WCAG21/Understanding/status-messages.html>
- WCAG 2.1 — Info & Relationships (1.3.1): <https://www.w3.org/WAI/WCAG21/Understanding/info-and-relationships.html>
- WCAG 2.1 — Focus Visible (2.4.7): <https://www.w3.org/WAI/WCAG21/Understanding/focus-visible.html>
- NVDA user guide: <https://www.nvaccess.org/files/nvda/documentation/userGuide.html>
- ARIA Authoring Practices 1.2 — Live regions: <https://www.w3.org/WAI/ARIA/apg/practices/live-regions/>
- Plugin source — `moodle-enhancement/local/sentientia_live/`:
  - `amd/src/chart_updater.js` — sr-only tally writer
  - `templates/result_panel.mustache` — sr-only region + landmark
  - `templates/result_bar_chart.mustache` — image role + label
  - `audience/play.php` — assertive / polite alerts on play page
  - `trainer/run.php` — polite live counters on trainer page
  - `lang/en/local_sentientia_live.php` — `a11y_*` string keys

---

## Appendix A — Mode-by-mode mode-change checklist

While running scenarios, NVDA may switch between browse and focus mode
automatically. The table below maps each scenario to the expected starting
mode and any in-scenario mode changes, so testers can verify mode does not
break announcements.

| Scenario | Mode entry  | Mode on action          | Mode after action |
| -------- | ----------- | ----------------------- | ----------------- |
| 1        | browse      | browse                  | browse            |
| 2        | browse      | browse                  | browse            |
| 3        | browse      | browse                  | browse            |
| 4        | browse      | browse                  | browse            |
| 5        | browse      | browse                  | browse            |
| 6        | browse      | browse                  | browse (reload)   |
| 7        | browse      | browse                  | browse            |
| 8        | browse      | browse                  | browse            |
| 9        | focus (in form) | focus → submit → browse | browse           |
| 10       | browse      | browse                  | browse            |
| 11       | browse      | browse                  | browse            |
| 12       | either      | either                  | either            |

If scenario 9 fails to announce because focus mode swallows the live
region — **that is a BLOCKING defect**. Live regions must be announced in
all modes per ARIA spec.

---

## Appendix B — Localisation note (Hindi parity)

Phase E.0 ships 9 `a11y_*` string keys with 100 % Hindi parity (per
`lang/hi/local_sentientia_live.php`). A separate Hindi NVDA verification
pass is **out of scope** for this v1 of the procedure but is on the
backlog for Phase E.12 (analytics + export). When that runs:

1. Switch Moodle UI language to `hi` (`?lang=hi` query string suffix).
2. Re-run all 12 scenarios.
3. Confirm Speech Viewer emits Devanagari script for every announcement
   (NVDA + eSpeak NG supports Hindi out of the box; install no extra
   voice).
4. File defect under `[a11y][i18n]` if any string falls back to English.

Until then, Hindi-language audiences must rely on the visual UI alone;
this is an accepted gap for Phase E.0 ship.

---

## Appendix C — Version history

| Version | Date       | Author  | Change                                                                              |
| ------- | ---------- | ------- | ----------------------------------------------------------------------------------- |
| 1.0     | 2026-05-24 | Claude  | Initial release — 12 scenarios covering Phase E.0 aria-live regions + sr-only tally |
