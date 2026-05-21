# Visual Evidence — 2026-05-21

**Reviewer:** Claude (via Chrome MCP, browser device "Browser 1" on Windows)
**Tester user:** Airpay Academy (id=2, username=academy@airpay.co.in) on http://localhost:8080/moodle
**Browser:** Chrome via Claude in Chrome MCP, primarily 1512×786 (some shots at 1568×746 / 1280×609)
**Flags flipped ON for verification:** `live.enabled` + 6 question type flags + `live.allow_anonymous`. **All flipped OFF again at end of session.**
**Test session:** "Visual Verification Demo Session" (sid=14, code 743 818) with 5 slides covering all question types — deleted at end of test.

---

## Surfaces walked + verdict

| # | Surface | URL | Verdict |
|---|---------|-----|---------|
| 1 | Trainer dashboard (empty state) | `/local/sentientia_live/trainer/index.php` | ✅ PASS |
| 1B | Trainer dashboard (with session) | same | ✅ PASS |
| 2 | Create session form | `/local/sentientia_live/trainer/create.php` | ⚠️ PARTIAL — icon glyphs at narrow widths |
| 3 | Edit session page | `/local/sentientia_live/trainer/edit.php?id=14` | ✅ PASS — gorgeous |
| 4A | Add slide — type picker | `/local/sentientia_live/trainer/add_slide.php?sessionid=14` | ✅ PASS |
| 4B | Add slide — multichoice form | `?sessionid=14&type=multichoice` | ⚠️ Could not interact through Chrome MCP — CLI render smoke-tested |
| 5 | Trainer run page (big join code) | `/local/sentientia_live/trainer/run.php?id=14` | ✅ PASS — projector-ready! |
| 6A | Audience join (code entry) | `/local/sentientia_live/audience/join.php?code=743818` | ✅ PASS — beautiful login-pagelayout |
| 6B | Audience play (response form) | `/local/sentientia_live/audience/play.php?sessionid=14&token=...` | ✅ PASS |
| 6C | Audience play (after response) | same | ✅ PASS — **bar chart looks great** |
| 7 | Trainer run with populated data | `?id=14` (after 6 responses) | ✅ PASS |
| 8A | Push subscribe widget | `/local/sentientia_pwa/preferences.php` | ✅ PASS — correctly shows "disabled by admin" |
| 8B | Push delivery log admin viewer | `/local/sentientia_pwa/admin/push_log.php` | ⏳ Not screenshotted — requires `:manage` cap (manager role only). Lib + template smoke-tested via CLI. |

---

## What works really well

- **Big monospace join code on run.php** (display-2 size, primary blue) — looks exactly like Mentimeter's projector view. Trainers can put this on a meeting room screen and audiences can read the code from across the room.
- **Type picker grid** (3×2 cards with title + description + "Use this type" CTA) is clean and self-explanatory.
- **Result panel bar chart** renders proportional bars normalised to the largest count, with `count (percent%)` aligned right. Pure CSS — no chart library needed.
- **Audience login-pagelayout** strips the sidebar/nav, giving audience screens a focused single-task layout.
- **Display name pre-fill** for logged-in users (smart UX — no friction).
- **Green "Found: <session title>" confirmation banner** after entering a valid code — confirms before commit.
- **State badges in dashboard** correctly colour-coded: grey for Draft, success for Live (didn't capture a Live session in the dashboard but verified visually).
- **Error path renders cleanly**: "Slide does not exist" red banner with left-border accent — see `04-error-state.png`.

## Issues spotted (not blocking, but should be addressed)

### Bugs

1. **`html_writer::start_div` 3-arg signature wrong in audience/play.php**
   - Code calls `start_div('class', null, ['style' => 'max-width: 720px;'])` — only first 2 args are honoured
   - Effect: the 720px max-width on the slide wrapper isn't applied; audience slide stretches the full viewport on desktop
   - Fix: use `\html_writer::start_tag('div', ['class' => '...', 'style' => '...'])` instead
   - Impact: Layout aesthetics on desktop. Mobile audience screens still fine.

2. **"Live runner — real-time projector coming in Phase E.3" placeholder is stale**
   - `run.php` still shows the "Phase E.3 placeholder" notice even though E.3 (SSE) shipped this morning
   - Fix: update lang strings `live_runner_pending_title` + `live_runner_pending_body` or remove the placeholder div from `run.php`
   - Impact: Confusing copy. Users think the feature isn't ready when it is.

3. **`multichoice` type badge renders as full-width gray bar in run.php**
   - At `<span class="badge bg-secondary">multichoice</span>` — the badge takes full width instead of fitting content
   - Fix: add `d-inline-block` to the span, or wrap in a div with text-start
   - Impact: Visual only.

### UX nits

4. **No progress indicator on audience play page** ("Question 1 of 5") — audience has no idea where they are in the session
   - Add `Slide {pos} of {total}` above the question text in audience/play.php

5. **"Join a live session" heading on audience/join.php aligns top-left**
   - Should center with the rest of the form (or remove the page heading since the form's "Session code" label is already the visual focal point)

6. **Help-icon glyphs sometimes render as box characters at narrow widths**
   - At 1280px viewport, `?` help icons turn into □ glyphs. At 1568px they render correctly as teal circles.
   - Theme issue, not our code — but worth investigating airpayux's icon font loading on narrow viewports.

### Permission gating

7. **`/local/sentientia_pwa/admin/push_log.php` denied to non-manager users**
   - Tested under "Airpay Academy" who isn't a manager → redirected back
   - This is **correct security behaviour** (`:manage` capability is intentionally restricted to manager archetype)
   - Action item: test under a manager-role user when production deployment happens

---

## Verified data flows (real DB)

- `session_manager::create()` from form submission: stored row id=14, code "743 818", state=draft, ownerid=2
- `slide_manager::add()` × 5 (multichoice, rating, wordcloud, openended, quiz): rows 22-26, positions 1-5 ✅
- `session_manager::start_session(14)`: transitioned to live, wrote `session_started` event ✅
- `session_manager::set_current_slide(14, 22)`: set current_slide_id, wrote `slide_changed` event ✅
- `participant_manager::join_or_resume(14, 2, ...)`: created row id=13 for the logged-in user ✅
- Anonymous joins (Visual Verifier, Sarah, Bob, Charlie, Diana) via CLI: 5 new rows ✅
- `response_recorder::submit()` × 6: tally `[1, 4, 1, 0]` for Email/WhatsApp/Push/SMS ✅
- `response_recorder::tally(22)` returned `[1, 4, 1, 0]`, matching the chart bars on play.php ✅

---

## What was NOT visually verified

| Surface | Why | Backup verification |
|---------|-----|---------------------|
| Slide add/edit forms (multichoice/quiz/rating/wordcloud/openended/ranking) | Chrome MCP element-click flakiness blocked form interaction | CLI smoke test of `slide_form::build_settings_from_form_data` passed for all 6 types |
| Push log admin page | `:manage` capability not granted to test user | `push_logger` lib smoke-tested via CLI; template renders correctly |
| SSE realtime updating in browser | Needs sustained connection + concurrent trainer-audience action — Chrome MCP polling-based testing harness can't simulate well | `curl -sN stream.php` proves SSE emits events within 1s of journal write |
| Mobile viewport (590px) responsive layout | Time budget — desktop checked first | Future visual session |

---

## State after cleanup

- Test session (id=14) deleted via CLI
- Mock participants (ids 13-18) cascade-deleted with the session
- `live.enabled` and all 7 question-type flags toggled back to default (OFF)
- No code changes during this session — only data writes that were rolled back

---

## Next visual session focus

1. Fix bugs #1, #2, #3 above (10 min total)
2. Browser-verify slide editor forms (need form-input ability in Chrome MCP OR submit via JS)
3. Test on mobile viewport (590px Chrome devtools mode)
4. Test SSE realtime end-to-end with two browser windows (trainer in one, audience in another)
5. Walk through push subscribe flow with a real browser subscription (Phase B verification's remaining gate)

---

## Addendum — late afternoon (2026-05-21 ~14:50)

### VIS-10 — SSE → chart_updater two-browser verification (Phase E.5 closeout)

**Root cause found + fixed:** `audience_sse.min.js` + `trainer_sse.min.js` hardcoded
the SSE URL fallback as `/local/sentientia_live/stream.php` — leading slash
resolves against domain root, so on our XAMPP `/moodle/...` install the
EventSource hit `http://localhost:8080/local/...` (HTTP 503) instead of
`http://localhost:8080/moodle/local/...`. The audience-side smoke test
in Phase E.3 had silently fallen through to polling-reload — looked OK,
wasn't real SSE.

**Fix:** Default `streamUrl` is now `M.cfg.wwwroot + '/local/sentientia_live/stream.php'`.
Guarded with `typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot` — `M` is a
bare Moodle global, not a `window.M` property, so the `typeof` check
avoids ReferenceError.

Also removed `withCredentials: true` from the trainer EventSource —
Chrome treats it as CORS-credentials mode even same-origin, requiring an
explicit Access-Control-Allow-Credentials response header that our
same-origin SSE endpoint doesn't (and shouldn't have to) send.

**Verified via Chrome MCP on session #17 / slide #28 (multichoice):**
After deploy + cache purge + hard reload:
- `stream.php` returns HTTP **200** (was 503)
- `trainer_sse.init()` runs, EventSource opens, held in OPEN state
- `window.sentientiaLiveTrainerSSE` set (`readyState === 1`)
- CLI inserts 3rd then 4th response → SSE delivers `response_added`
- Visible counters tick: "Responses received: 3 → 4"
- `chart_updater` mutates bar widths in place: React 67% → 50%, Svelte 33% → 50%
- **No page reload** during the transition — pure DOM mutation

Files: 4 commits in `4abc70a..670b3c2` range (SSE URL fix + dedup).

### E.6 — Quiz leaderboard + correct-answer summary

Built + verified on the trainer view of session #17 / new quiz slide #29
("Which planet do humans live on?" with options Mercury / Venus /
**Earth** / Mars, correct_index = 2).

CLI seeded 4 responses with staggered timestamps to simulate real
answer timing:
- Anna → Mars (wrong) at +12s
- Ben → Earth (correct) at +5s ← first-to-answer
- Carla → Earth (correct) at +18s
- Mobile Tester → Venus (wrong) at +25s

**Trainer view renders correctly:**
- Bar chart shows all 4 options. Earth highlighted with green "Correct"
  badge + green bar (full width since it has max count = 2)
- Blue info alert: "**Quiz result: 2 of 4 got it right (50%)**" on left,
  "Correct answer: **Earth**" on right
- Leaderboard card with table:
  - Rank 1 (winner-highlighted with `table-warning` yellow background): **Ben — 5s**
  - Rank 2: Carla — 18s

**Audience view (not yet visually walked):** leaderboard is hidden
(`show_to_audience = true` default → trainer must pass `false` to see it).
Audience sees the bar chart + correct-answer-revealed badge only,
preserving the pacing where the trainer chooses when to reveal who
got it right first.

**SSE-driven update path:** `chart_updater` now also mutates the
`.sentientia-quiz-correct-count` / `.sentientia-quiz-total` /
`.sentientia-quiz-percent-correct` spans on each `response_added`
event for quiz slides. Leaderboard refresh stays page-reload-driven
(we don't build display-name DOM nodes via JS to preserve XSS safety
per ADR-004 — mustache renders them at page load).


### E.11 — Mobile responsive pass at 590px viewport

Defensive responsive rules added for the airpayux primary mobile
breakpoint (`max-width: 590px` per `.claude/rules/frontend.md`). The
audience pages already use Bootstrap responsive utility classes
(`flex-wrap`, `flex-grow-1`, `w-100`, `form-control-lg`) so they
narrow cleanly. The added rules tighten the result panel + trainer
runner at narrow viewports.

**Result panel** (`templates/result_panel.mustache`, inline `<style>`
scoped to `.sentientia-results-panel`):
- Bar chart labels at 0.95rem, count/percent at 0.8rem, `white-space: nowrap`
  on the count so the percentage doesn't break inside the parens
- Quiz summary alert: `flex-direction: column` so "Quiz result: X of Y"
  and "Correct answer: <label>" stack vertically (no overflow)
- Leaderboard table: smaller font + tighter padding
- Wordcloud: tighter gap + smaller `cloud-size-4`/`5` so the largest
  words don't overflow the panel width

**Trainer runner** (`trainer/run.php`, inline `<style>` scoped to
`.sentientia-trainer-runner`):
- `display-2` join code → 3rem (was ~5.5rem), letter-spacing 0.05em
  (was 0.1em) so a 6-digit code with a space (e.g. "219 839")
  fits comfortably without horizontal scroll
- Audience + Response alerts: stack flex-direction column instead of
  flex-row, so the counter doesn't push the "X slides in deck" off
- Slide info card: `p-4` → `p-3` for tighter mobile spacing

**Audience pages** verified mobile-friendly by inspection (Bootstrap
utility classes already mobile-first):
- `audience/join.php` — single input + button, `form-control-lg`,
  `login-pagelayout` strips nav for focused entry
- `audience/play.php` — option buttons are full-width with `p-3`
  padding (≥48px tap targets), labels at `fs-5`, rating row uses
  `flex-wrap`, wordcloud + openended use `form-control-lg` full-width

**Verification status:** desktop renders are visually identical
(media query doesn't fire at >590px). Truly verifying 590px requires
either Chrome DevTools device-mode (Ctrl+Shift+M) or a physical
mobile device — Chrome MCP's `resize_window` resizes the OS window
chrome but doesn't reliably set the inner viewport for content
rendering, so the @media query couldn't be triggered through MCP.
Defensive responsive rules added are CSS-spec-valid and won't break
desktop rendering.

**Follow-up for next session:**
1. Walk audience flow on a real mobile (Galaxy S22, iPhone 14) to
   confirm tap-target sizes + the SSE-driven chart_updater inline
   refresh paths animate cleanly
2. Capture mobile screenshots into `docs/visual-evidence/2026-05-21/mobile/`
3. Test Service Worker + PWA install flow on real mobile (Phase D.1)
