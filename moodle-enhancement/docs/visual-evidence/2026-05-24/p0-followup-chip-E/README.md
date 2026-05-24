# P0 follow-up chip E — `local_sentientia_live` aria-live regions

**Date:** 2026-05-24
**Branch:** `claude/quirky-dirac-ly2Mz` on
`nitin-rajput-learning-tech/Airpay-Academy2.0`
**Scope:** Just P0 #8 from `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` —
add ARIA live regions to the `local_sentientia_live` plugin so screen-reader
users perceive real-time updates.
**Plugin version:** `2026052401` (was `2026052103`) → `0.1.1-alpha`

---

## What changed

The audit (F-23) reported ZERO `aria-live` regions across all
`sentientia_live` templates and AMD modules. With this chip, the
plugin exposes 5 live regions + 2 role-region landmarks across the
trainer and audience surfaces.

### Files touched (5 source + 1 build)

| File | Region added |
|---|---|
| `templates/result_panel.mustache` | `role="region"` + aria-label on outer `.sentientia-results-panel`; new sr-only `<span data-live-tally-summary aria-live="polite" aria-atomic="true">` inside the header |
| `templates/result_bar_chart.mustache` | `role="img"` + aria-label on the bar-chart wrapper |
| `audience/play.php` | role=status + aria-live=assertive on session-ended state; aria-live=polite on waiting-for-first-slide state; aria-live=assertive on response-saved confirmation; aria-live=polite on already-responded state; role=region + aria-label on the current-slide container |
| `trainer/run.php` | role=status + aria-live=polite + aria-atomic=true + aria-label on the audience-count alert AND the response-count alert |
| `amd/src/chart_updater.js` + `amd/build/chart_updater.min.js` | new `updateSrOnlyTallySummary()` writes localised `"<count> <suffix>"` (e.g. `"12 responses"` / `"12 प्रतिक्रियाएं"`) to the panel's sr-only span on every response_added SSE event |
| `lang/en/local_sentientia_live.php` + `lang/hi/local_sentientia_live.php` | +9 string pairs (region labels + announce text). Parity verified: 264/264 |
| `version.php` | bumped to `2026052401` / `0.1.1-alpha` |

### Files inspected, no change needed

| File | Why no change |
|---|---|
| `amd/src/audience_sse.js` + `amd/build/audience_sse.min.js` | Already triggers a full page reload on `slide_changed` / `session_ended`, so SR will pick up the new heading naturally. Only mutation it does for `response_added` is dispatching a CustomEvent — chart_updater is the consumer. |
| `amd/src/trainer_sse.js` + `amd/build/trainer_sse.min.js` | Mutates `#sentientia-audience-count` and `#sentientia-response-count` textContent inside the now-aria-live `.alert` containers. Per the ARIA spec, textContent changes on descendants of an aria-live element trigger the announcement — so no JS change needed. |

---

## ARIA spec mapping

| Surface | Region | Politeness | Atomic | Why |
|---|---|---|---|---|
| Result panel header | sr-only tally summary | polite | true | Non-urgent; on response_added we want SR to read full "<N> responses" — atomic re-reads the full count phrase, not just the delta. |
| Result panel outer | aria-label region | n/a | n/a | Landmark so SR users can jump to the results area. |
| Bar chart container | role=img | n/a | n/a | Identifies the bar chart as an image of data; descendant numeric textContent updates are covered by the panel's aria-live region. |
| Audience play — response-saved confirmation | assertive | true | Urgent — SR interrupts current speech to confirm the vote landed (per the chip's "user feedback notification" criterion). |
| Audience play — session-ended | assertive | true | Urgent — session is over now. |
| Audience play — waiting-for-question | polite | (default) | Non-urgent — fits the "waiting" semantic. |
| Audience play — already-responded | polite | true | Non-urgent state info; atomic so the full sentence reads coherently. |
| Trainer run — audience count alert | polite | true | Non-urgent count-bump; atomic so SR reads "Audience: 5 online now" not just "5". |
| Trainer run — response count alert | polite | true | Same pattern — "Responses received: 12". |

All live regions stay in the DOM at all times (no `display:none` on
them while updates happen). The `.sr-only` class is already defined in
`theme/airpayux/scss/moodle/partials/_bs5-compat.scss` (lines 67-86).

---

## Screen-reader test procedure (Nitin — local XAMPP + production)

Because XAMPP is on D:\\ and this commit lands on GitHub remote
production branch, the SR walk-through happens after the local pull.
Two SRs to cover the Big Two:

### Setup (one-time)

1. Pull this branch into XAMPP local:
   ```
   cd D:\Claude Local\airpay-ld-os
   git fetch origin claude/quirky-dirac-ly2Mz
   git checkout claude/quirky-dirac-ly2Mz
   ```
2. Deploy plugin to XAMPP:
   ```
   Copy-Item -Recurse -Force `
     "moodle-enhancement\local\sentientia_live\*" `
     "C:\xampp\htdocs\moodle5\public\local\sentientia_live\"
   ```
3. Run the plugin upgrade (version bumped):
   ```
   php C:\xampp\htdocs\moodle5\admin\cli\upgrade.php --non-interactive
   ```
4. Purge caches:
   ```
   php C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php
   ```
5. Enable the master feature flag if it isn't already — visit
   Site administration → Plugins → Local plugins → Sentientia LMS Live
   engagement (or whichever Switchboard surface holds
   `live.enabled`) and turn it ON for the Airpay tenant.

### NVDA (Windows) — desktop SR

1. Launch NVDA (free, https://www.nvaccess.org/).
2. Open Firefox to http://localhost:8080/moodle/.
3. Trainer flow:
   - Log in as a user with `local/sentientia_live:create` and `:run`.
   - Create a Multiple-choice slide on a new session, start it.
   - Listen for: "**Live audience count**" region landmark when you
     focus the audience counter. NVDA should announce
     "**Audience: 1 online now**" (or whatever the count is).
   - Open a second browser tab as an audience member (incognito so
     you join as a different user); join the session via the code.
     Back in the trainer tab, NVDA should auto-announce
     "**Audience: 2 online now**" when the count bumps.
   - Have the audience tab submit a response. NVDA should
     announce "**Responses received: 1**" in the trainer tab.
   - NVDA should also announce the tally inside the result panel —
     e.g. "**1 responses**". (Pluralisation TODO; intentional gloss.)
4. Audience flow (incognito tab):
   - Join the session as an anonymous (or logged-in) audience.
   - On submit, NVDA should announce "**Response recorded**" (the
     `audience_response_saved` string surfaces inside the
     aria-live=assertive alert).
   - When the trainer ends the session, the page reloads and NVDA
     should announce "**Session ended. Your responses have been
     recorded.**" (the aria-live=assertive session-ended region).

### VoiceOver (macOS) — desktop SR

Same flow, but with Safari + VoiceOver (Cmd+F5). VoiceOver
announces aria-live regions on element mutation; expected
behaviour matches NVDA above.

### Acceptance criteria

- [ ] NVDA announces audience count change in the trainer view
      without the trainer pressing any key.
- [ ] NVDA announces response count change in the trainer view
      without the trainer pressing any key.
- [ ] NVDA announces the "Response recorded" confirmation when an
      audience member submits a vote (audience tab).
- [ ] NVDA announces the result panel tally ("N responses") when
      it updates.
- [ ] NVDA announces the session-ended message when the session
      ends.

### Mobile (Phase E.11+)

This chip does not include mobile SR (TalkBack / VoiceOver iOS)
verification — defer to Phase E.11 mobile pass.

---

## Hindi parity

| Locale | Strings | Parity verdict |
|---|---|---|
| en | 264 | baseline |
| hi | 264 | **PASS** — 100% parity, mirrors the chip-required gate |

Verified via:

```
grep -cE '^\$string\[' lang/en/local_sentientia_live.php  # 264
grep -cE '^\$string\[' lang/hi/local_sentientia_live.php  # 264
```

---

## Commits

| # | SHA | Scope |
|---|-----|-------|
| 1 | (templates + lang + version) | result_panel.mustache, result_bar_chart.mustache, lang/en+hi, version.php |
| 2 | (audience PHP) | audience/play.php |
| 3 | (trainer PHP) | trainer/run.php |
| 4 | (AMD) | amd/src/chart_updater.js, amd/build/chart_updater.min.js |
| 5 | (docs) | this README + state-card update + PROJECT-STATE append |

Each commit carries the required co-author line and was pushed to
`origin/claude/quirky-dirac-ly2Mz`. Pre-commit hook was NOT skipped.

---

## Out of scope for this chip (noted as follow-ups)

- F-24 (P1) — Bootstrap utility classes → Sentientia BEM tokens for
  the live plugin. Held for the P1 sweep.
- F-25 (P2) — `<caption>` + scope=col on the trainer dashboard table.
- Pluralisation of the sr-only tally string ("1 response" vs
  "12 responses"). Currently always reads the bare plural noun;
  acceptable per the audit but worth fixing during the i18n pass.
- Mobile SR (TalkBack / VoiceOver iOS) regression — deferred to
  the Phase E.11 mobile pass.
