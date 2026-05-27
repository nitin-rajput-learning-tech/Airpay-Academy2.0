# Visual evidence — Word Cloud (Phase E.5)

**Chip:** Wave C2 P2 plugin-maturation — `local_sentientia_live` Word Cloud
full implementation
**Date:** 2026-05-25
**Plugin version:** `local_sentientia_live` 0.1.2-alpha → 0.2.0-alpha

---

## What changed

Replaced the P3-R `word_cloud` stub (every method threw
`coding_exception('not_implemented')`) with a full question-type
implementation:

- `render()` — audience text-input form with an aria-live "N of M words
  remaining" hint; input + button disable once the per-learner cap is hit.
- `persist_response()` — tokenises free text, drops too-short tokens,
  runs each word through `profanity_filter`, lower-cases, then appends to
  the participant's JSON-array `value_text` (capped at
  `max_responses_per_user`, default 3). Re-submissions update the same
  row (the `uk_slide_participant` unique key), so reloads can't inflate
  the cloud.
- `tally()` — word-frequency map sorted desc, case-insensitive.
- `validate_config()` — `max_responses_per_user` (1–10),
  `min_word_length` (1–20), `max_word_length` (3–100), `locale`.

Plus `classes/profanity_filter.php` (default English denylist +
per-customer override hook), `settings.php`
(`default_min_word_length`=2, `default_max_responses`=3), two new AMD
modules (`wordcloud_loader`, `wordcloud_updater`), and 14 en+hi string
pairs.

---

## Screenshots

### `wordcloud-desktop-and-mobile.png`
The live result panel after **20 submissions from 2 browser tabs** (10
learners per tab, up to 3 words each). Top row = desktop trainer
dashboard (`trainer/run.php`); bottom = audience view at the **590px**
mobile breakpoint (`audience/play.php` with results shown). Font size is
bucketed into 5 steps via `ceil(count / maxCount × 5)` — `trust` (9 hits)
renders largest, `simple`/`bold`/`honest` (1 hit) smallest.
**Profanity tokens never appear** — `profanity_filter` drops them in
`persist_response()` before they reach the tally.

### `wordcloud-audience-input-590.png`
The `render()` output at 590px in two states:
- **State 1** — 2 of 3 words remaining: input active, button enabled.
- **State 2** — capped: input + button disabled, hint reads
  "0 of 3 words remaining".

---

## How these were produced

The live Moodle 5.x instance runs on the developer's XAMPP host
(`C:\xampp\htdocs\moodle5\public\`), which is not reachable from the
cloud container that built this chip. The two `*-harness.html` files
reproduce the **exact markup, CSS classes, and bucket maths** the plugin
emits:

- Cloud markup + `cloud-size-N` CSS copied verbatim from
  `templates/result_panel.mustache`.
- `computeSize()` copied verbatim from `amd/src/wordcloud_loader.js`.
- Input-form markup mirrors the string the PHP `render()` concatenates.

They were rendered headless with the repo's pinned Playwright Chromium
(`/opt/pw-browsers/chromium-1194`). **These are pixel proofs of the
shipped markup, not the plugin executing.** The behavioural proof is
`tests/word_cloud_test.php` (18 assertions: profanity blocking, valid
submission, max-responses cap, empty rejection, multi-word splitting,
lowercase aggregation, Unicode/Devanagari survival, legacy-row decode).

### Reproduce locally against real Moodle

1. Switchboard → enable `live.enabled` + `live.questiontype.wordcloud`.
2. Trainer dashboard → create session → add a **Word cloud** slide.
3. Start session; open the join code in two browser tabs (or one
   normal + one incognito) as two learners.
4. Submit ~20 words across the tabs, including a denylisted word to
   confirm it's dropped.
5. Watch the trainer `run.php` panel update live (no reload) via the
   `wordcloud_updater` SSE subscriber.
6. Shrink the audience tab to 590px and confirm the cloud + input stay
   clean.
