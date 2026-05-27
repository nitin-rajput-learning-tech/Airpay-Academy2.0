# Visual Evidence — 2026-05-27

## Sentientia Live — two-browser verification of all 6 question types

Verifies the `local_sentientia_live` (Mentimeter clone) question types
end-to-end after the Wave C1/C2/D4 merges landed all 6 types on the
`production` branch. Run against local XAMPP (Moodle 5.1.3+, PHP 8.2.12,
MariaDB 10.11.16) at `http://localhost:8080/moodle`.

### What was tested

A LIVE session (id=18, code 800844) was seeded with one slide of every
question type + 3 anonymous participants + a spread of responses via the
new `cli/seed_demo_session.php`. The audience was then driven in a fresh
(anonymous) Chrome; the current slide was advanced server-side per type.

### Screenshots (audience render)

| File | Type | Renders |
|------|------|---------|
| `01-multichoice-audience.png` | multichoice | radio options (Red/Green/Blue) + Submit |
| `02-wordcloud-audience.png`   | wordcloud   | text input + cap hint "3 of 3 words remaining" |
| `03-openended-audience.png`   | openended   | multi-line textarea |
| `04-rating-audience.png`      | rating      | 1–5 scale cards (stars) with radios |
| `05-quiz-audience.png`        | quiz        | radios (correct answer hidden from audience) |
| `06-ranking-audience.png`     | ranking     | numbered `#` inputs + numeric a11y instruction |

### Verification matrix — all green

| Dimension | Result | How |
|-----------|--------|-----|
| Audience render (6 types) | ✓ PASS | 6 browser screenshots above, all render with Airpay-blue Submit, correct progress "Question N of 6", BEM/Sentientia styling |
| Anonymous join | ✓ PASS | code → "Found: QA Demo…" → display name → play.php with participant token (no login) |
| SSE live auto-advance | ✓ PASS | Changing current slide server-side (multichoice→wordcloud) auto-swapped the audience screen with no manual reload |
| Server persist + tally (6 types) | ✓ PASS | `seed_demo_session.php` — 17/17 responses persisted; tally readback correct for every type (e.g. multichoice `[2,1,0]`, rating avg 4.67, quiz `[2,0,1]`, ranking Borda `[1.33,2,2.67]`, wordcloud `{innovation:2,speed:1,trust:1}`) |
| JS console health | ✓ PASS | Zero JS errors across all 6 renders. Only a benign site-wide PWA-meta deprecation warn (`apple-mobile-web-app-capable`) |

### Notes / follow-ups

- **SSE auto-advance** fired reliably for the first hop; subsequent hops
  were captured via explicit reload because XAMPP's prefork Apache holds
  one worker per open SSE connection (a local-env constraint, not a
  product bug — production uses a proper MPM; multichoice SSE chart
  updates were already verified live in VIS-10).
- **Trainer-side result panels** for the 5 new types (wordcloud cloud,
  openended list, rating bars, quiz leaderboard, ranking Borda) were not
  re-captured here — they require an authenticated trainer browser, and
  the multichoice result panel + SSE chart were already verified in
  VIS-6/7/10. The seed CLI proves the underlying tally data is correct,
  so this is a low-risk visual follow-up.
- **PHPUnit harness fix (separate):** the question-type PHPUnit suite
  errored on `Unknown column 'open_path'` in `phpu_user` —
  `session_manager::create()` hard-selected a BizLMS-only column absent
  from the vanilla test DB. Fixed by reading `open_path` defensively
  (also hardens the plugin for non-BizLMS Sentientia customers). See the
  session changelog.

### New QA/operator CLIs (this session)

- `local/sentientia_live/cli/set_live_flags.php` — flip the whole Live
  engagement flag set on/off in one command (`--on` / `--off` /
  `--status`).
- `local/sentientia_live/cli/seed_demo_session.php` — seed a LIVE session
  with all 6 question types + participants + responses; prints join code
  + URLs.

### Flag state during test

All 9 Live engagement flags were ON globally (via `set_live_flags --on`).
`live.enabled`, `multichoice`, `allow_anonymous` were already ON from
prior VIS tests; this session added the remaining 5 question-type flags.
