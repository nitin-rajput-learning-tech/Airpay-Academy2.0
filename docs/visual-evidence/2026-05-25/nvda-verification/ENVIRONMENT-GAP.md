# Environment Gap Report — NVDA Verification Attempt #1 (2026-05-25)

| Field        | Value                                                                          |
| ------------ | ------------------------------------------------------------------------------ |
| Procedure    | [`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md) v1.0 |
| Plugin       | `local_sentientia_live` v0.1.1-alpha                                           |
| Attempt date | 2026-05-25                                                                     |
| Gap status   | **OPEN** — no Windows / NVDA / browser / audio in the cloud execution container |
| Chip         | Wave D2 P3 — `claude/zen-albattani-LWHr8`                                      |

---

## 1. What this document is

A concise runbook telling the next human tester exactly what they need
installed, configured, and open on their Windows machine to clear the
environmental gap that caused Attempt #1 to be skipped. Pair this with
the full procedure ([`NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md))
when running Attempt #2.

---

## 2. Gap summary

The Wave D2 P3 testing chip ran inside a remote cloud execution container
(Ubuntu 24.04.4 LTS, kernel 6.18.5). NVDA verification is **inherently
Windows-only and human-in-the-loop**:

| Component                                | Present in container | Required for procedure |
| ---------------------------------------- | -------------------- | ----------------------- |
| Windows 10 (build 19045+) or Windows 11  | ❌ Ubuntu 24.04 LTS  | ✅ Required (NVDA is Win32) |
| NVDA 2024.1+                             | ❌ Not installable on Linux | ✅ Required          |
| Mozilla Firefox (ESR + stable)           | ❌ No browser binary | ✅ Required (both ESR + stable) |
| Google Chrome (current stable)           | ❌ No browser binary | ✅ Required             |
| Audio output device                      | ❌ `/dev/snd` empty  | ✅ Speakers or headphones |
| NVDA Speech Viewer                       | ❌ N/A (no NVDA)     | ✅ Required (evidence capture) |
| Screen / GUI                             | ❌ Headless          | ✅ Required (sighted assistant for audience window) |
| Localhost Moodle 5.1.3+                  | ❌ No PHP / Apache / MariaDB | ✅ Required for plugin to render |
| Human at keyboard                        | ❌ Headless cloud session | ✅ Required for multi-window orchestration |

Even with Wine / WSL2 hypothetically available, **none of those approaches
substitute for a human listener confirming that NVDA speaks the right
words at the right moment**. WCAG 4.1.3 verification is, by design, a
sense-the-output activity.

---

## 3. Human-tester runbook for Attempt #2

### 3.1 Pre-test machine setup (one-time, ~30 min)

| Step | Action                                                                                                 | Verify                                                                |
| ---- | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------- |
| 1    | Boot Windows 10 (build 19045+) or Windows 11 machine. Native install, not VM (VMs sometimes drop audio). | `winver` shows correct build.                                         |
| 2    | Install NVDA 2024.x+ from <https://www.nvaccess.org/download/>. Accept defaults; let it start on boot. | NVDA icon in system tray; "NVDA started" announced on launch.        |
| 3    | Install Firefox ESR (latest) AND Firefox Stable (latest). Keep both in `Program Files`.               | Both launch; about:profiles shows two distinct profiles.             |
| 4    | Install Google Chrome (current stable channel).                                                       | `chrome://version` reports a 2026 build (or newest available).       |
| 5    | Pair audio output. Plug in headphones (recommended) or use built-in speakers. Test with NVDA tray menu → Help → "About NVDA" — you should hear it announced. | Audible voice.                                                       |
| 6    | Optional but recommended: install OBS Studio (free). Pre-configure a 10-second clip preset for BLOCKING scenarios (audience-side scenarios 6, 9 + trainer 1, 2, 3). | Test capture: a 10-second clip lands in your Videos folder.          |
| 7    | Install XAMPP 8.2+ if not already present. Path expected: `C:\xampp\htdocs\moodle5\public\` per CLAUDE.md. | <http://localhost:8080/moodle/> loads the Airpay Academy login page. |
| 8    | Clone or sync the repo to `D:\Claude Local\airpay-ld-os\` and copy `local/sentientia_live/` into `C:\xampp\htdocs\moodle5\public\local\sentientia_live\`. Run `admin/cli/upgrade.php` and `admin/cli/purge_caches.php`. | Site admin → Plugins → Local plugins shows `local_sentientia_live` v0.1.1-alpha or later. |
| 9    | Confirm feature flags ON. Site admin → Plugins → Local plugins → Airpay Core → Feature flags: `live.enabled = 1`, `live.realtime.enabled = 1` (or skip if site default is ON). | Visiting `/local/sentientia_live/trainer/index.php` as a `Trainer`-role user shows "New session" button. |

### 3.2 NVDA configuration (one-time, ~5 min)

Open NVDA → Preferences → Settings (`NVDA+Ctrl+G`) and confirm:

- **General → Log level:** `info`
- **Speech → Synthesizer:** eSpeak NG (default)
- **Speech → Rate:** 50%
- **Browse mode → Use screen layout when supported:** ON
- **Browse mode → Automatic say-all on page load:** **OFF**
- **Document formatting → Report landmarks and regions:** ON
- **Document formatting → Report headings:** ON

Then open **Tools → Speech viewer** and pin the window in a corner of the
desktop. Every announcement appears as a new line. You will copy lines
from this window for evidence (§3.5 below).

### 3.3 Test data fixture (per procedure §4)

1. Sign in to `<http://localhost:8080/moodle/>` as a user with capability
   `local/sentientia_live:create` (default `Trainer` role).
2. Visit `/local/sentientia_live/trainer/index.php` → "New session".
3. Title: `NVDA QA Test 2026-05-25` (or current date if attempt #2 lands later).
4. Add 5 slides, one of each type per the procedure §4:
   - Slide 1 — **Multiple choice** — 4 options
   - Slide 2 — **Quiz** — 4 options, mark option 2 correct
   - Slide 3 — **Rating** — scale 1-5
   - Slide 4 — **Word cloud** — max length 30
   - Slide 5 — **Open-ended** — max chars 280
5. Click **Start live session**. Capture the 6-digit join code.
6. Open a **second browser profile** (or a second machine) for the audience side.
   Hit `/local/sentientia_live/audience/join.php`, enter the join code and a
   display name `QA-Audience-01`. Submit → lands at `/audience/play.php`.
7. Return to the trainer window with NVDA + Speech viewer open. You are
   on `/local/sentientia_live/trainer/run.php?id=X`. Begin scenario 1.

### 3.4 Per-scenario workflow

For each of the 12 scenarios in procedure §6:

1. Trigger the action exactly as the **Action** row in the procedure
   prescribes (e.g. "have a fresh audience tab join" for S1, "audience
   submits a vote" for S2, etc.).
2. The instant NVDA emits an announcement, **screenshot the browser**
   (Win+Shift+S → window crop).
3. **Copy the Speech viewer line(s)** from the 5 seconds straddling the
   action into a text file.
4. Repeat steps 1–3 with the other browser (Firefox after Chrome, or
   vice-versa).
5. For BLOCKING scenarios (1, 2, 3, 6, 9): also start a 10-second OBS
   clip BEFORE triggering the action, so the audible announcement is
   captured.

### 3.5 Evidence file layout

The Attempt #1 skip created the empty layout. Drop your files in:

```
docs/visual-evidence/2026-05-25/nvda-verification/
├── README.md                           ← edit (or replace) for your attempt
├── ENVIRONMENT-GAP.md                  ← this file — leave unchanged
├── STATIC-ANALYSIS.md                  ← Attempt #1 doc-mismatch findings — read before §6
├── scenario-01/
│   ├── firefox/
│   │   ├── nvda-s01-firefox-pass.png   ← screenshot
│   │   ├── nvda-s01-firefox.txt        ← Speech viewer transcript
│   │   └── nvda-s01-firefox.mp4        ← BLOCKING — OBS audio clip
│   └── chrome/
│       ├── nvda-s01-chrome-pass.png
│       ├── nvda-s01-chrome.txt
│       └── nvda-s01-chrome.mp4
├── scenario-02/
│   ├── firefox/
│   └── chrome/
…
└── scenario-12/
    ├── firefox/
    └── chrome/
```

**Naming convention** matches procedure §8:
- `nvda-s{NN}-{browser}-{result}.png` for screenshots
- `nvda-s{NN}-{browser}.txt` for transcripts
- `nvda-s{NN}-{browser}.mp4` for OBS clips

If a scenario rolls up multiple sub-actions (e.g. S1 variant: 2nd audience
joins; S12: 20 simulated responses), capture one screenshot + transcript
per sub-action and append `-v1`, `-v2`, …  to the filename.

### 3.6 Sign-off

Update `README.md` in this folder (overwrite or version-bump the Attempt
#1 file). Populate the sign-off table from procedure §10 with
PASS/FAIL/BLOCKED per row, per browser, with version numbers and tester
initials. Replace the SKIPPED entries from Attempt #1.

For any FAIL:
- File a GitHub issue per procedure §11 (title `[a11y][live-engagement]
  Scenario S{NN} FAIL — <summary>`).
- Post in `#airpay-academy-eng` for BLOCKING failures.
- Pull request gating Phase E.1+ must link to this evidence folder + the
  filed issues.

### 3.7 Resolve the two doc-clarity findings from Attempt #1

[`STATIC-ANALYSIS.md`](./STATIC-ANALYSIS.md) flagged two minor doc-vs-lang
inconsistencies:

- **F-1** — Procedure §6 Scenario 6 "Expected" text omits the lang prefix
  `Thanks for participating. ` from `audience_session_ended_body`. When
  you run S6, capture the **actual** Speech viewer text verbatim. If it
  matches what F-1 predicts (full text with the prefix), file a follow-up
  doc edit task to either fix procedure §6 or relax §9 variance rubric.
- **F-2** — Procedure §6 Scenario 10 "Expected" text says `this question`
  but lang string `audience_already_responded` says `this slide`.
  Capture actual Speech viewer text verbatim; if "slide" is heard, file a
  doc edit task.

Treat these as expected discoveries, not surprise defects.

---

## 4. Why this gap is "OPEN" not "CLOSED"

A closed gap means the procedure has been executed end-to-end at least
once. Attempt #1 was the first attempt and it did not execute, so the
gap remains open. Acceptance condition for closure: a fully-populated
sign-off table where every cell is PASS / FAIL / BLOCKED (none =
SKIPPED), with linked evidence files, signed by a human tester.

A subsequent Attempt #1.5 from cloud-only Claude is **not viable** —
the same Linux-container constraint will apply to any cloud-side AI
agent. The next attempt must run on a Windows machine, either by:

- A human QA tester at the Mumbai office, OR
- A remote Windows VM with audio passthrough that a human is actively
  driving (NOT a headless RDP / Citrix session — most Citrix sessions
  drop audio), OR
- A Claude Code on the web session attached to a Windows desktop
  environment (not the default Linux container).

Recommended path: human QA tester. The 12 scenarios take ~90 minutes
including evidence capture, plus ~30 min for write-up. This is well
within a single QA session.

---

## 5. Cost / time estimate for Attempt #2

| Phase                                | Estimated time |
| ------------------------------------ | -------------- |
| Machine setup (§3.1)                 | 30 min one-time |
| NVDA configuration (§3.2)            | 5 min one-time  |
| Test data fixture (§3.3)             | 10 min          |
| 12 scenarios × 2 browsers (§3.4–3.5) | 60–90 min       |
| Evidence write-up + sign-off (§3.6)  | 20 min          |
| Defect filing if FAIL                | 15 min per FAIL |
| **Total — happy path**               | **~2–2.5 hr**   |

This is small enough that Phase E.1+ ship blocker can be cleared in a
single QA session.

---

## 6. References

- Full procedure: [`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md)
- Attempt #1 sign-off (this folder): [`README.md`](./README.md)
- Static-analysis findings from Attempt #1: [`STATIC-ANALYSIS.md`](./STATIC-ANALYSIS.md)
- NVDA download: <https://www.nvaccess.org/download/>
- NVDA User Guide (settings, modes, keystrokes): <https://www.nvaccess.org/files/nvda/documentation/userGuide.html>
- ARIA Authoring Practices — Live regions: <https://www.w3.org/WAI/ARIA/apg/practices/live-regions/>
