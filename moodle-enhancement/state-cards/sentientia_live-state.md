# State Card — `local_sentientia_live` (Sentientia LMS Live engagement)

**Current phase:** E.9 — all 6 question types implemented (Wave D4)  
**Version:** 0.2.0-alpha (2026052403)  
**Status:** Trainer + audience UI live; 6 question types fully implemented (D4: open_ended, rating_scale, quiz, ranking; C1/C2: multichoice, word_cloud); feature flags default OFF; a11y pass complete  
**Owner:** Nitin Rajput (PM) + Claude (engineering)  
**Last updated:** 2026-05-24

> **Wave D4 (2026-05-24):** open_ended (500-char, paginated, moderation),
> rating_scale (stars/NPS + mean/median), quiz (correct_index + scoring +
> top-10 leaderboard), ranking (Borda count + avg position) all moved from
> stub → full impl. +8 Mustache templates, +66 en/hi string pairs (parity
> 100%), +49 PHPUnit methods. Registry-driven trainer picker. See the
> PROJECT-STATE.md Wave D4 H2 and
> `docs/visual-evidence/2026-05-24/D4-question-types/`.

---

## Mission

A Mentimeter clone built into Sentientia LMS. Trainers run live polls,
quizzes, word clouds, Q&A; audiences join via a 6-digit code and respond
in real time. Tier 1 priority #3 on the Sentientia LMS roadmap; estimated
8-12 sessions of work to v1.0.

## Architecture decision

- **Real-time mechanism:** SSE (server-sent events) for server → client
  push; regular POST for client → server response submission. See
  [ADR-004](../docs/adr/ADR-004-realtime-mechanism-for-sentientia-live.md).
- **Question types:** 6 — multichoice, wordcloud, openended, rating,
  quiz, ranking. Each gated on its own feature flag for phased rollout.

## Database schema (5 tables — locked in Phase E.0)

| Table | Rows per | Purpose |
|-------|----------|---------|
| `local_sentientia_live_sessions` | one per session | Owner, code, state lifecycle (draft → live → ended), title, settings_json |
| `local_sentientia_live_slides` | one per question within a session | type, position, title, type-specific settings_json |
| `local_sentientia_live_participants` | one per (session, audience-member) | Bearer join_token for anonymous joins; presence via timelastseen |
| `local_sentientia_live_responses` | one per (slide, participant) | value_int for numeric, value_text for free text. Unique on (slide, participant) — re-submission updates |
| `local_sentientia_live_events` | event journal | Drives the SSE stream loop; purged 24h after session ends |

## Capability matrix

| Capability | role allowed | Notes |
|------------|--------------|-------|
| `local/sentientia_live:create` | editingteacher+, manager | Create a session |
| `local/sentientia_live:run` | editingteacher+, manager | Start/advance/end a session you own |
| `local/sentientia_live:join` | user+ | Join by code (anonymous joins gated on per-session `allow_anonymous`) |
| `local/sentientia_live:respond` | user+ | Submit a response |
| `local/sentientia_live:manage_all` | manager | Admin: see + manage every session across tenants |

## Feature flags

| Flag | Default | Purpose |
|------|---------|---------|
| `live.enabled` | OFF | Master kill switch. When OFF, `/local/sentientia_live/` returns 403. |
| `live.realtime.enabled` | ON | Use SSE. OFF → fall back to 3s short polling. |
| `live.questiontype.multichoice` | OFF | Phase E.4 |
| `live.questiontype.wordcloud` | OFF | Phase E.5 |
| `live.questiontype.openended` | OFF | Phase E.6 |
| `live.questiontype.rating` | OFF | Phase E.7 |
| `live.questiontype.quiz` | OFF | Phase E.8 |
| `live.questiontype.ranking` | OFF | Phase E.9 |
| `live.allow_anonymous` | OFF | Allow non-authenticated audience joins. Default OFF for compliance. |

## Phase ladder

| Phase | Scope | Status |
|-------|-------|--------|
| **E.0** | Foundation — schema + capabilities + privacy + ADR | ✅ shipped |
| E.1 | Trainer UI — create session, manage slides, copy join code | ✅ shipped (`trainer/`) |
| E.2 | Audience UI — join by code, show current slide, submit response | ✅ shipped (`audience/`) |
| E.3 | SSE realtime — slide_changed + response_added + session_ended events | ✅ shipped (`stream.php` + 3 AMD clients) |
| E.4 | Multichoice slide type — bar chart | ✅ shipped (`chart_updater.js`) |
| E.5 | Word cloud slide type — tag cloud | partial |
| E.6 | Open-ended slide type — scrolling response list | partial |
| E.7 | Rating slide type — 1-5 or 0-10 NPS | pending |
| E.8 | Quiz slide type — right/wrong + leaderboard | pending |
| E.9 | Ranking slide type — drag-to-order | pending |
| E.10 | Per-tenant + per-customer settings; full privacy export | pending |
| E.11 | Mobile responsiveness pass | pending |
| E.12 | Analytics + export | pending |

## Open questions for Nitin

- **Default question type set for Airpay's first live session.** Do we
  prioritise multichoice + quiz (compliance / training-test feel) or
  add open-ended + word cloud (workshop / brainstorm feel) early?
- **Anonymous audience access.** Per Sentientia's enterprise positioning,
  default OFF makes sense. But Airpay-internal sessions might prefer ON
  (no friction). Should the customer-level flag override come per-customer
  or per-tenant?
- **Max concurrent attendees per session.** Hard cap at 500 per
  ADR-004 to protect Apache worker pool. Is that enough for Airpay's
  largest planned sessions? If we need 1000+, we shift to the WebSocket
  daemon path (~2 sessions of work).

## Dependencies

- `local_airpay_core::feature_flags` — flag resolution (already shipped)
- `local_sentientia_pwa::push_sender` — optional, for "session starting
  in 5 minutes" push notifications. Soft-coupled via class_exists.

## Risks

- **Apache worker exhaustion** during SSE sessions. Mitigated by per-session
  cap (500) + fallback short-polling flag. Monitor via `apache2 status`.
- **Long-running PHP processes** during SSE — must call
  `\core\session\manager::write_close()` early or every connection
  blocks the user's PHP session lock.
- **Database write hotspot** in events table during a 500-attendee
  session — every slide transition writes one event, every response
  writes one event. 500 attendees × 5 slides × ~3 responses = 7,500 writes
  in 30 min = ~4 writes/sec average; well within MariaDB. Burst handling
  for "slide just changed, 500 audience submit within 5s" = ~100 writes/sec
  peak. Stress test before pilot.

## Where to start next session

**Phase E.1 — Trainer UI.** Files to create:
- `local/sentientia_live/trainer/index.php` — list of own sessions
- `local/sentientia_live/trainer/edit.php` — create/edit session, manage slides
- `local/sentientia_live/classes/forms/session_form.php` — create form
- `local/sentientia_live/classes/forms/slide_form.php` — per-type slide forms
- `local/sentientia_live/classes/session_manager.php` — CRUD layer
- Templates under `local/sentientia_live/templates/`

ADR-004 has implementation notes for the SSE endpoint when we get to E.3.

---

## Change log

### 2026-05-24 — a11y pass (P0 #8 from PLATFORM-VISUAL-AUDIT-2026-05-24)

Added ARIA live regions across the trainer + audience surfaces so
screen-reader users perceive real-time updates:

- `templates/result_panel.mustache` — outer `role="region"` +
  aria-label; new sr-only `[data-live-tally-summary]` with
  `aria-live="polite"` `aria-atomic="true"`.
- `templates/result_bar_chart.mustache` — `role="img"` + aria-label
  on the chart container.
- `audience/play.php` — `role="status"` `aria-live="assertive"` on
  the response-saved confirmation, session-ended panel; polite on
  waiting-for-question + already-responded; `role="region"` +
  aria-label on the current-slide container.
- `trainer/run.php` — `role="status"` `aria-live="polite"`
  `aria-atomic="true"` on the audience-count + response-count alerts
  with localised aria-label landmarks.
- `amd/src/chart_updater.js` + `amd/build/chart_updater.min.js` —
  new `updateSrOnlyTallySummary()` writes localised
  `"<count> <suffix>"` to the panel's sr-only span on every
  `response_added` SSE event.
- `lang/en/local_sentientia_live.php` + `lang/hi/local_sentientia_live.php`
  — 9 new a11y string pairs; 100% parity preserved (264/264).
- `version.php` — `2026052401` / `0.1.1-alpha`.

Branch: `claude/quirky-dirac-ly2Mz`.
Evidence: `docs/visual-evidence/2026-05-24/p0-followup-chip-E/README.md`.
Verified: PHP lint clean across all touched files; node --check clean
on both ES6 source and ES5 build; Hindi parity 264 = 264.

---

## Key files

```
local/sentientia_live/
├── version.php                                  2026052401 / 0.1.1-alpha
├── index.php                                    Plugin landing page
├── stream.php                                   SSE endpoint (long-lived HTTP)
├── trainer/                                     Trainer surface (Phase E.1)
├── audience/                                    Audience surface (Phase E.2)
├── classes/
│   ├── session_manager.php                      Session CRUD + lifecycle
│   ├── slide_manager.php                        Slide CRUD
│   ├── participant_manager.php                  Anonymous join token handling
│   ├── response_recorder.php                    Per-slide response writer
│   ├── event_journal.php                        SSE event journal helper
│   ├── output/                                  Renderer overrides
│   ├── forms/                                   Trainer + slide forms
│   └── privacy/                                 GDPR/DPDP provider
├── amd/src/
│   ├── audience_sse.js                          Audience SSE consumer
│   ├── trainer_sse.js                           Trainer SSE consumer
│   └── chart_updater.js                         Live bar-chart updater
├── templates/                                   Trainer + audience + result panel
├── db/
│   ├── install.xml                              5 tables
│   ├── access.php                               5 capabilities
│   └── feature_flags.php                        9 flags (1 master + 1 SSE + 6 question type + anonymous)
├── lang/
│   ├── en/local_sentientia_live.php             264 keys
│   └── hi/local_sentientia_live.php             264 keys (100% parity)
└── tests/
    ├── session_manager_test.php                 20 tests
    └── event_journal_test.php                   9 tests (29 total)
```

## State card refresh — 2026-05-24

P1 state-card pass: confirmed plugin still at `2026052401` /
`0.1.1-alpha` after the a11y follow-up. Re-counted PHPUnit methods:
session_manager 20 + event_journal 9 = 29 total. Confirmed 5 DB tables
(sessions, slides, participants, responses, events), 5 capabilities
(`:create`, `:run`, `:join`, `:respond`, `:manage_all`), 9 feature flags
(master + realtime + 6 question types + anonymous). Phase ladder
updated to reflect E.1-E.4 now shipped; E.5-E.6 partial; E.7-E.12
pending. Added full Key Files inventory. 45 files total in plugin tree.
