# Static Analysis — NVDA Verification Attempt #1 (2026-05-25)

| Field             | Value                                                                          |
| ----------------- | ------------------------------------------------------------------------------ |
| Procedure         | [`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md) v1.0 |
| Plugin            | `local_sentientia_live` v0.1.1-alpha                                           |
| Attempt date      | 2026-05-25                                                                     |
| Reviewer          | Claude (Opus 4.7) — Wave D2 P3 chip                                            |
| Type              | **Static** — markup + lang-string + JS-DOM-mutation review only                |
| Note              | Static review is **not** a substitute for runtime NVDA verification. It can confirm the regions are wired correctly per the procedure contract, but it cannot confirm the screen reader actually announces them. The latter remains an open gap (see [ENVIRONMENT-GAP.md](./ENVIRONMENT-GAP.md)). |

---

## 1. Scope

This report walks each of the 12 procedure scenarios and checks three
things against the source code:

1. **Markup contract** — does the rendered HTML match the procedure's
   `ARIA` row (role / aria-live / aria-atomic / aria-label)?
2. **Lang-string contract** — does the value of every `aria-label` /
   announcement string in the lang file match the procedure's
   `Expected` row?
3. **JS mutation safety** — for scenarios involving DOM mutation by
   `chart_updater.js`, does the JS use `textContent` (XSS-safe) and not
   `innerHTML`?

Files reviewed:
- `moodle-enhancement/local/sentientia_live/audience/play.php`
- `moodle-enhancement/local/sentientia_live/trainer/run.php`
- `moodle-enhancement/local/sentientia_live/templates/result_panel.mustache`
- `moodle-enhancement/local/sentientia_live/templates/result_bar_chart.mustache`
- `moodle-enhancement/local/sentientia_live/amd/src/chart_updater.js`
- `moodle-enhancement/local/sentientia_live/lang/en/local_sentientia_live.php`

---

## 2. Summary

| Scenario | Markup | Lang | JS  | Static verdict        | Notes                                                                                                             |
| -------- | ------ | ---- | --- | --------------------- | ----------------------------------------------------------------------------------------------------------------- |
| S1       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S2       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S3       | ✅     | ✅   | ✅  | **PASS (static)**     | `chart_updater.js` updates via `textContent` only; XSS-safe.                                                      |
| S4       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S5       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S6       | ✅     | ⚠️   | n/a | **PASS WITH F-1**     | Procedure §6 "Expected" omits the `Thanks for participating. ` prefix actually present in `audience_session_ended_body`. NON-BLOCKING. |
| S7       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S8       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S9       | ✅     | ✅   | n/a | **PASS (static)**     |                                                                                                                   |
| S10      | ✅     | ⚠️   | n/a | **PASS WITH F-2**     | Procedure §6 expects "this question" but lang `audience_already_responded` = "this slide". NON-BLOCKING.          |
| S11      | ✅     | ✅   | n/a | **PASS (static)**     | Same template as S4; regression check only.                                                                       |
| S12      | n/a    | n/a  | ✅  | **NOT TESTABLE STATICALLY** | Stress-test scenario; requires runtime SSE load.                                                            |

Out of 12 scenarios, **10 pass cleanly** under static review, **2 have
NON-BLOCKING doc-clarity issues** (F-1 + F-2), and **1 is not statically
testable** (S12 stress test). Zero BLOCKING defects against the plugin
code.

---

## 3. Per-scenario static review

### 3.1 Scenario 1 — Trainer audience-count region

**Procedure expects:**
```
role="status" aria-live="polite" aria-atomic="true"
aria-label="Live audience count"
```

**Source — `trainer/run.php:124-131`:**
```php
echo \html_writer::start_tag('div', [
    'class' => 'alert alert-info d-flex justify-content-between align-items-center',
    'role' => 'status',
    'aria-live' => 'polite',
    'aria-atomic' => 'true',
    'aria-label' => get_string('a11y_audience_count_region',
        'local_sentientia_live'),
]);
```

**Lang — `lang/en/local_sentientia_live.php:334`:**
```php
$string['a11y_audience_count_region']    = 'Live audience count';
```

✅ All three attributes match. ✅ aria-label resolves to "Live audience count".

The inner span at `run.php:134` has `id="sentientia-audience-count"` —
the trainer SSE JS mutates the `textContent` of that span; the parent
region's `aria-atomic="true"` should trigger re-read on mutation per
WAI-ARIA Authoring Practices.

**Static verdict: PASS.** Runtime check still needed to confirm NVDA
announces "Live audience count Audience : 1 online now Total slides : 5"
within the 2-second budget per §9.

---

### 3.2 Scenario 2 — Trainer response-count region

**Procedure expects:**
```
role="status" aria-live="polite" aria-atomic="true"
aria-label="Live response count"
```

**Source — `trainer/run.php:150-157`:**
```php
echo \html_writer::start_tag('div', [
    'class' => 'alert alert-secondary d-flex justify-content-between align-items-center',
    'role' => 'status',
    'aria-live' => 'polite',
    'aria-atomic' => 'true',
    'aria-label' => get_string('a11y_response_count_region',
        'local_sentientia_live'),
]);
```

**Lang — `lang/en/local_sentientia_live.php:335`:**
```php
$string['a11y_response_count_region']    = 'Live response count';
```

✅ All three attributes match. ✅ aria-label resolves correctly.

Inner span `id="sentientia-response-count"` at `run.php:161` is the
mutation target for `trainer_sse.js` (per the in-code comment on
`run.php:144-146`).

**Static verdict: PASS.**

---

### 3.3 Scenario 3 — Result-panel sr-only tally summary (BLOCKING)

**Procedure expects:**
```
aria-live="polite" aria-atomic="true" (no role)
Span lives inside .sentientia-results-panel which carries role="region".
Announcement: "<count> <suffix>" — e.g. "1 responses"
Critically: textContent only, never innerHTML.
```

**Source — `templates/result_panel.mustache:29-32`:**
```mustache
<span class="sr-only"
      aria-live="polite"
      aria-atomic="true"
      data-live-tally-summary></span>
```

**Source — `amd/src/chart_updater.js:82-92`:**
```js
const updateSrOnlyTallySummary = (panel, countNow) => {
    if (!panel || typeof countNow !== 'number') {
        return;
    }
    const summaryEl = panel.querySelector('[data-live-tally-summary]');
    if (!summaryEl) {
        return;
    }
    const suffix = panel.dataset.a11yTallySuffix || '';
    summaryEl.textContent = String(countNow) + (suffix ? ' ' + suffix : '');
};
```

**Lang — `lang/en/local_sentientia_live.php:168`:**
```php
$string['live_results_total_suffix']   = 'responses';
```

✅ aria-live="polite" + aria-atomic="true" present.
✅ The suffix `responses` flows from the lang string via the
   `data-a11y-tally-suffix` attribute on the parent `.sentientia-results-panel`
   (template line 21) → read in JS at `chart_updater.js:90`.
✅ **XSS-safe**: JS uses `textContent` exclusively. No `innerHTML`,
   no `insertAdjacentHTML`, no `outerHTML` anywhere in the function.
✅ The `String(countNow)` cast on JS line 91 means a non-numeric payload
   would result in `"undefined responses"` rather than executing markup.

**Static verdict: PASS.** This is the most security-sensitive of the 12
scenarios (per procedure §6 S3 "Important — verify the textContent
path") and the code already meets the bar. Runtime check still needed
to confirm NVDA reads the announcement before the next response
arrives (see S12 stress-test caveat).

---

### 3.4 Scenario 4 — Result-panel region landmark

**Procedure expects:**
```
role="region" aria-label="Live results"
Navigable via D key in browse mode.
```

**Source — `templates/result_panel.mustache:16-23`:**
```mustache
<div class="sentientia-results-panel my-4"
     data-slideid="{{slideid}}"
     ...
     role="region"
     aria-label="{{#str}}a11y_results_region_label, local_sentientia_live{{/str}}">
```

**Lang — `lang/en/local_sentientia_live.php:330`:**
```php
$string['a11y_results_region_label']     = 'Live results';
```

✅ Match.

**Static verdict: PASS.**

---

### 3.5 Scenario 5 — Bar-chart accessible name

**Procedure expects:**
```
role="img" aria-label="Live results bar chart"
```

**Source — `templates/result_bar_chart.mustache:17-19`:**
```mustache
<div class="d-flex flex-column gap-2"
     role="img"
     aria-label="{{#str}}a11y_results_bar_chart_label, local_sentientia_live{{/str}}">
```

**Lang — `lang/en/local_sentientia_live.php:331`:**
```php
$string['a11y_results_bar_chart_label']  = 'Live results bar chart';
```

✅ Match.

Procedure §6 S5 notes that NVDA + Chrome may treat the image as opaque
(reads label only) while NVDA + Firefox reads label + descendants —
both are within spec and the **label** is the pass/fail anchor. The
label is present and correct.

**Static verdict: PASS.**

---

### 3.6 Scenario 6 — Audience session-ended (BLOCKING) — F-1

**Procedure expects (`NVDA-VERIFICATION-PROCEDURE.md:273-275`):**
```
role="status" aria-live="assertive" aria-atomic="true"
aria-label="Session ended. Your responses have been recorded."
Speech Viewer line: "Session ended. Your responses have been recorded.
                     Session ended Session ended . Your responses have been recorded ."
```

**Source — `audience/play.php:179-193`:**
```php
if ($sess->state === \local_sentientia_live\session_manager::STATE_ENDED) {
    echo \html_writer::start_tag('div', [
        'class' => 'text-center my-5',
        'role' => 'status',
        'aria-live' => 'assertive',
        'aria-atomic' => 'true',
        'aria-label' => get_string('a11y_session_ended_announce',
            'local_sentientia_live'),
    ]);
    echo \html_writer::tag('h2',
        get_string('audience_session_ended_heading', 'local_sentientia_live'));
    echo \html_writer::tag('p',
        get_string('audience_session_ended_body', 'local_sentientia_live'),
        ['class' => 'text-muted']);
    echo \html_writer::end_tag('div');
    ...
}
```

**Lang — `lang/en/local_sentientia_live.php:314-315, 333`:**
```php
$string['audience_session_ended_heading'] = 'Session ended';
$string['audience_session_ended_body']    = 'Thanks for participating. Your responses have been recorded.';
$string['a11y_session_ended_announce']    = 'Session ended. Your responses have been recorded.';
```

✅ Markup contract: role="status", aria-live="assertive", aria-atomic="true" all present.
⚠️ **Finding F-1 — Procedure expected-text drift.** The procedure's
   verbatim Expected line predicts `"Session ended . Your responses have
   been recorded ."` for the body paragraph. The actual rendered body
   is **"Thanks for participating. Your responses have been recorded."**
   — the lang string has a `Thanks for participating. ` prefix that the
   procedure's expected line omits.

   The aria-label itself ("Session ended. Your responses have been
   recorded.") matches the procedure correctly. So NVDA will read:
   1. (assertive interrupt) The aria-label: "Session ended. Your responses have been recorded."
   2. The H2: "Session ended"
   3. The P body: "Thanks for participating. Your responses have been recorded."

   The procedure's predicted Speech Viewer line under-reports step 3.

**Severity: NON-BLOCKING.** Under §9 variance rubric, the actual
announcement is still semantically correct (session ended; responses
saved). But the literal string match a tester might apply would fail.

**Remediation options** (post Attempt #2 confirmation):
- Option A: update procedure §6 S6 Expected line to include `"Thanks for participating. "` prefix.
- Option B: add the prefix to the §9 variance examples that PASS.
- Option C: simplify the lang string by removing the prefix.

Recommend Option A — least disruptive; keeps the existing UX copy.

**Static verdict: PASS WITH F-1.**

---

### 3.7 Scenario 7 — Audience waiting-for-question (polite)

**Procedure expects:**
```
role="status" aria-live="polite"
aria-label="Waiting for the next question"
```

**Source — `audience/play.php:201-208`:**
```php
if (!$sess->current_slide_id) {
    echo \html_writer::start_tag('div', [
        'class' => 'text-center my-5',
        'role' => 'status',
        'aria-live' => 'polite',
        'aria-label' => get_string('a11y_waiting_for_question',
            'local_sentientia_live'),
    ]);
    ...
}
```

**Lang — `lang/en/local_sentientia_live.php:337`:**
```php
$string['a11y_waiting_for_question']     = 'Waiting for the next question';
```

✅ Match.

**Note:** Procedure §6 S7 expects `aria-atomic` but does **not** include
it in the contract row (vs. S1 / S2 / S3 / S6 / S9 / S10 which all do).
The source also omits `aria-atomic` for S7 — they agree. Since the
waiting message is static text and the region content never mutates
client-side, `aria-atomic` is not required (the region is announced
once when it appears).

**Static verdict: PASS.**

---

### 3.8 Scenario 8 — Audience current-question region landmark

**Procedure expects:**
```
role="region" aria-label="Current question"
```

**Source — `audience/play.php:240-246`:**
```php
echo \html_writer::start_tag('div', [
    'class' => 'sentientia-audience-slide my-4 mx-auto',
    'style' => 'max-width: 720px;',
    'role' => 'region',
    'aria-label' => get_string('a11y_current_question_region',
        'local_sentientia_live'),
]);
```

**Lang — `lang/en/local_sentientia_live.php:336`:**
```php
$string['a11y_current_question_region']  = 'Current question';
```

✅ Match.

**Static verdict: PASS.**

---

### 3.9 Scenario 9 — Audience response-saved (BLOCKING)

**Procedure expects:**
```
role="status" aria-live="assertive" aria-atomic="true"
(No aria-label — content acts as accessible name.)
Speech Viewer line: "Response received — thanks!"
```

**Source — `audience/play.php:270-280`:**
```php
if ($response_saved) {
    echo \html_writer::tag('div',
        '<i class="fa fa-check-circle fa-2x text-success me-2" aria-hidden="true"></i>' .
            get_string('audience_response_saved', 'local_sentientia_live'),
        ['class' => 'alert alert-success text-center',
         'role' => 'status',
         'aria-live' => 'assertive',
         'aria-atomic' => 'true']);
    ...
}
```

**Lang — `lang/en/local_sentientia_live.php:316`:**
```php
$string['audience_response_saved']      = 'Response received — thanks!';
```

✅ All four contract elements match.
✅ The `<i>` icon has `aria-hidden="true"` so NVDA will not announce
   "image" or "check circle"; the visible glyph is decorative.
✅ The content "Response received — thanks!" is what gets read.

**Static verdict: PASS.** Runtime check critical here — this is the
single most-frequent assertive announcement in the audience flow. If
NVDA fails to interrupt on this scenario, the user can keep voting
without ever knowing their last vote landed.

---

### 3.10 Scenario 10 — Audience already-responded (polite) — F-2

**Procedure expects (`NVDA-VERIFICATION-PROCEDURE.md:332-333`):**
```
role="status" aria-live="polite" aria-atomic="true"
Speech Viewer line: "You have already responded to this question"
                    (string audience_already_responded).
```

**Source — `audience/play.php:295-301` (show-results branch) and
`307-312` (no-results branch):**
```php
echo \html_writer::tag('div',
    get_string('audience_already_responded', 'local_sentientia_live'),
    ['class' => 'alert alert-info text-center',
     'role' => 'status',
     'aria-live' => 'polite',
     'aria-atomic' => 'true']);
```

**Lang — `lang/en/local_sentientia_live.php:317`:**
```php
$string['audience_already_responded']   = 'You have already responded to this slide.';
```

✅ All three contract elements (role, aria-live, aria-atomic) match.
⚠️ **Finding F-2 — Lang-vs-procedure string mismatch.** Procedure's
   Expected line predicts `"You have already responded to this question"`
   but the actual lang-string value is `"You have already responded to
   this slide."` — different noun ("question" vs "slide") and trailing
   full-stop present in the string but absent in the procedure expected.

   The procedure correctly identifies the lang **key** as
   `audience_already_responded`. The mismatch is purely in the
   **predicted English text**.

**Severity: NON-BLOCKING.** §9 variance rubric tolerates punctuation
differences, but "question" vs "slide" is a noun change, not a
punctuation issue. A literal-match tester would fail this scenario
when the actual UI is in fact working correctly.

**Remediation options** (post Attempt #2 confirmation):
- Option A: update procedure §6 S10 Expected line to read `"You have already responded to this slide."` (with full-stop).
- Option B: change the lang string to `"You have already responded to this question."`.
- Option C: leave both and document the variance in §9.

Recommend Option A — the procedure is the newer artefact (one day old;
the lang string predates Phase E.0). Doc edit is cheaper than a lang
change that propagates through Hindi parity, the 5-locale propagation
H2 (Day 0), and downstream translation review.

**Static verdict: PASS WITH F-2.**

---

### 3.11 Scenario 11 — Result panel on audience side (regression)

Same `result_panel.mustache` template as S4. Renders via
`audience/play.php:284-286` and `302-305` when `show_results_to_audience`
is on. Markup identical → contract identical → expected behaviour
identical.

The procedure note (§6 S11 "only verifies that NVDA's region-discovery
still works inside the `pagelayout=login` chrome the audience page
uses") is a runtime observation that this static review cannot replicate.

**Static verdict: PASS** (inherits S4).

---

### 3.12 Scenario 12 — sr-only tally at high SSE frequency

Stress-test scenario. Requires:
- A running Moodle + SSE channel
- 20 simulated responses landing within 5 seconds
- NVDA actively reading

None of these are reproducible by static analysis. The procedure §6 S12
itself acknowledges this is a runtime-only check ("what matters is that
the announcement does not stall NVDA, freeze the page, or drop the
final count").

**Static verdict: NOT TESTABLE STATICALLY.** Defer to Attempt #2.

---

## 4. Cross-cutting observations

### 4.1 The 9 aria-live regions enumerated

For audit-trail completeness, the 9 regions covered by Phase E.0 (per
procedure §1) map to these source locations:

| # | Region                                              | Source                                                | aria-live   | aria-atomic | role     |
| - | --------------------------------------------------- | ----------------------------------------------------- | ----------- | ----------- | -------- |
| 1 | Trainer audience-count                              | `trainer/run.php:124-131`                             | polite      | true        | status   |
| 2 | Trainer response-count                              | `trainer/run.php:150-157`                             | polite      | true        | status   |
| 3 | Audience session-ended                              | `audience/play.php:179-193`                           | assertive   | true        | status   |
| 4 | Audience waiting-for-question                       | `audience/play.php:201-208`                           | polite      | (n/a)       | status   |
| 5 | Audience current-question landmark                  | `audience/play.php:240-246`                           | (n/a)       | (n/a)       | region   |
| 6 | Audience response-saved                             | `audience/play.php:270-280`                           | assertive   | true        | status   |
| 7 | Audience already-responded (show-results branch)    | `audience/play.php:295-301`                           | polite      | true        | status   |
| 8 | Audience already-responded (no-results branch)      | `audience/play.php:307-312`                           | polite      | true        | status   |
| 9 | Result-panel region landmark (trainer + audience)   | `templates/result_panel.mustache:16-23`               | (n/a)       | (n/a)       | region   |

Plus 1 sr-only tally span at `templates/result_panel.mustache:29-32`
(polite, atomic, no role) and 1 role="img" at
`templates/result_bar_chart.mustache:17-19` for a total of 11
ARIA-instrumented elements — matches procedure §1's count.

### 4.2 String-key audit

All 9 `a11y_*` keys in `lang/en/local_sentientia_live.php:330-338` are
exercised by the 12 scenarios. None are dead code:

- `a11y_results_region_label` → S4 + S11
- `a11y_results_bar_chart_label` → S5
- `a11y_response_recorded` — used by `chart_updater.js`? **Not referenced
  by any of the 5 reviewed files.** Possible dead-code candidate; would
  benefit from a grep on production. Not in scope for this attempt.
- `a11y_session_ended_announce` → S6
- `a11y_audience_count_region` → S1
- `a11y_response_count_region` → S2
- `a11y_current_question_region` → S8
- `a11y_waiting_for_question` → S7
- `a11y_already_responded` — also defined at line 338 but procedure
  uses `audience_already_responded` (line 317) for the Expected text.
  Possible duplication; flagged for triage but **not part of this
  static review**.

Two potential dead-code / duplicate-key candidates flagged — not in
scope for this chip, but worth a follow-up Wave D grep.

### 4.3 XSS posture of the sr-only tally

`chart_updater.js` is the only client-side mutator of any aria-live
region. The relevant write paths:

| Function (file:line)                       | Target                            | Method                      |
| ------------------------------------------ | --------------------------------- | --------------------------- |
| `updateSrOnlyTallySummary` (chart_updater.js:82-92) | `[data-live-tally-summary]`     | `textContent = String(...)` |
| `updateBarChart` (chart_updater.js:133-162) | `.sentientia-bar-count`, `.sentientia-bar-percent` | `textContent = String(...)` |
| `updateQuizSummary` (chart_updater.js:100-131) | `.sentientia-quiz-correct-count`, `-total`, `-percent-correct` | `textContent = String(...)` |
| `updateRatingChart` (chart_updater.js:164-201) | `.sentientia-bar-count`, `.sentientia-results-avg`, `.sentientia-results-count` | `textContent = String(...)` |

✅ All four mutator functions use `textContent` exclusively. No
`innerHTML`, no `outerHTML`, no `insertAdjacentHTML`. Even if the SSE
server payload were tampered with, the worst-case is a wrong number
appearing on screen — not script execution. Phase E.0 a11y work shipped
without regressing the XSS posture.

---

## 5. Findings registry

| ID  | Severity     | Surface       | Description                                                                                                     | Recommended fix             |
| --- | ------------ | ------------- | --------------------------------------------------------------------------------------------------------------- | --------------------------- |
| F-1 | NON-BLOCKING | Procedure §6 S6 | "Expected" line omits the `Thanks for participating. ` prefix actually rendered by `audience_session_ended_body`. | Update procedure §6 S6 Expected verbatim — keep lang string. |
| F-2 | NON-BLOCKING | Procedure §6 S10 | "Expected" line says "this question" but lang `audience_already_responded` says "this slide".                  | Update procedure §6 S10 Expected to say "slide" — cheaper than i18n cost of changing the string. |

Out-of-scope flags (not findings; future-triage candidates):
- Dead-key candidate: `a11y_response_recorded` (lang line 332). Not
  referenced by the 5 reviewed files. Grep on production codebase needed.
- Possible-duplicate-key: `a11y_already_responded` (line 338) vs
  `audience_already_responded` (line 317). May be redundant; needs
  triage.

---

## 6. Out of scope for this attempt

- Runtime NVDA + Firefox + Chrome behaviour (the procedure's whole point).
- Hindi-language parity check (Appendix B of the procedure; backlog Phase E.12).
- Mobile screen-reader (TalkBack / VoiceOver) verification.
- Production smoke test (procedure §2 says "production smoke-test optional but encouraged").
- The two flagged out-of-scope items in §4.2.

---

## 7. References

- Procedure: [`docs/qa/NVDA-VERIFICATION-PROCEDURE.md`](../../../qa/NVDA-VERIFICATION-PROCEDURE.md)
- Plugin source:
  - `moodle-enhancement/local/sentientia_live/audience/play.php`
  - `moodle-enhancement/local/sentientia_live/trainer/run.php`
  - `moodle-enhancement/local/sentientia_live/templates/result_panel.mustache`
  - `moodle-enhancement/local/sentientia_live/templates/result_bar_chart.mustache`
  - `moodle-enhancement/local/sentientia_live/amd/src/chart_updater.js`
- Lang: `moodle-enhancement/local/sentientia_live/lang/en/local_sentientia_live.php`
- Sign-off record: [`README.md`](./README.md)
- Environmental gap: [`ENVIRONMENT-GAP.md`](./ENVIRONMENT-GAP.md)
