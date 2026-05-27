# Wave D4 — `local_sentientia_live` 4 question types implemented

**Date:** 2026-05-24
**Branch:** `claude/admiring-albattani-tGAjg` on
`nitin-rajput-learning-tech/Airpay-Academy2.0`
**Scope:** Phases E.6–E.9 — full implementation of the 4 remaining
question-type stubs (open_ended, rating_scale, quiz, ranking). C1 + C2
(parallel chips) own multichoice + word_cloud.
**Plugin version:** `2026052403` / `0.2.0-alpha` (was `2026052402` / `0.1.2-alpha`)

---

## Visual evidence

Live screenshots require a running Moodle 5.1 with the
`live.enabled` + per-type `live.questiontype.*` feature flags ON, which
this remote chip container does not provide (no XAMPP, no DB bootstrap).
In its place — and matching the repo's established pattern of
`mockup-*.html` files under `docs/visual-evidence/` — this folder ships:

- **`mockup-four-types-side-by-side.html`** — a self-contained, openable
  HTML page rendering each type's **audience view (left)** beside its
  **trainer result view (right)**, one simulated session running all four
  back-to-back. Markup + CSS classes mirror the actual
  `qt_<type>_audience.mustache` / `qt_<type>_result.mustache` templates,
  so it doubles as a layout reference for QA sign-off.

When deploying to the local XAMPP Moodle, capture desktop + 590px-mobile
screenshots of each type's audience and trainer surfaces and drop them
here (filenames `desktop-<type>-audience.png` etc.) to complete the
visual-evidence record before merge to production.

---

## What shipped

### 4 question-type classes (stubs → full implementations)

| Type | render | persist_response | tally | validate_config | aria |
|------|--------|------------------|-------|-----------------|------|
| `open_ended` | textarea, 500-char counter | strips HTML, 500 ceiling | display-all, newest-first + `paginate()` | max_chars 10-500 + moderation bool | 4 keys |
| `rating_scale` | stars (1-5) OR NPS (1-10) | bound-checked via recorder | distribution + **mean + median** | scale_type + bounds + label-length | 3 keys |
| `quiz` | radio one-of-N | option-index | histogram + correct_count + **top-10 leaderboard** | options + **required correct_index** | 4 keys |
| `ranking` | drag-to-order + numeric a11y fallback | permutation, dup-rejecting | **Borda count** + average position | 2-20 string items | 3 keys |

### 8 Mustache templates

`qt_open_ended_audience` · `qt_open_ended_result` ·
`qt_rating_scale_audience` · `qt_rating_scale_result` ·
`qt_quiz_audience` · `qt_quiz_result` ·
`qt_ranking_audience` · `qt_ranking_result`

All pass the CI Mustache balance check; every UI string via `{{#str}}`;
`{{{ }}}` only on JSON tally payloads; sesskey hidden input on every form.

### Lang strings — Hindi parity 100%

EN 332 / HI 332 keys, byte-aligned (verified). +66 new pairs for D4
(audience legends, result headings, a11y announcements, validation
errors), and the `openended_max_chars_help` default updated 280 → 500.

### Registry + picker + version

- `question_type_registry::get_all()` already maps all 6 — verified by
  `question_type_registry_test`.
- Trainer `add_slide.php` picker is now **registry-driven**
  (`question_type_registry::list_slugs()`), behaviour-preserving since
  `list_slugs()` == `slide_manager::VALID_TYPES` (existing test enforces).
- `version.php` `0.1.2-alpha` → `0.2.0-alpha` (E.4–E.9 ship together).

### PHPUnit — 49 new test methods across 4 classes

`qt_open_ended_test` (12) · `qt_rating_scale_test` (12) ·
`qt_quiz_test` (13) · `qt_ranking_test` (12). Each covers 3 valid + 2-3
invalid configs, persist (valid + invalid payload), tally aggregation,
pure helpers (pagination / mean+median / scoring / Borda), aria, and
registry resolution. Far exceeds the ≥20 acceptance threshold.

---

## Type-specific notes

- **open_ended**: ceiling raised 280 → 500 (P3-R stub documented 280 —
  superseded). `slide_manager` clamp + `response_recorder` fallback +
  `slide_form` default all moved to 500 in lockstep. HTML stripped at
  persist via `clean_param(PARAM_TEXT)`. No aggregation — `tally()`
  returns the raw list newest-first; `paginate()` slices for display.
- **rating_scale**: `scale_type` (`stars`|`nps`) now persists through
  `slide_manager` so the config genuinely picks the scale. Mean + median
  are pure static helpers (`compute_mean` / `compute_median`) for easy
  unit testing. `_avg` alias kept for the existing `chart_updater`.
- **quiz**: `correct_index` is REQUIRED at config time. `score_response()`
  exposes per-response scoring (1/0) for the future session rollup.
  Leaderboard caps at 10, ordered by elapsed time then row id.
- **ranking**: dual aggregation — Borda count (higher = preferred, robust
  to strategic voting) AND average position (lower = preferred, intuitive).
  Drag-to-order in the audience template with an always-present numeric
  input fallback for screen-reader / no-JS clients.

---

## Manual smoke-test plan (run on XAMPP before production merge)

1. Admin → enable `live.enabled` + all 6 `live.questiontype.*` flags.
2. Trainer: create a session; the picker shows all 6 types.
3. Add one slide of each new type (open_ended, rating, quiz, ranking).
4. Start session; open audience in a second browser/incognito.
5. For each slide: submit a response → confirm trainer result panel
   updates (bar/histogram/leaderboard/ranking) and audience sees the
   confirmation. Quiz: verify right/wrong badge + leaderboard ordering.
6. Toggle locale to Hindi → confirm every label renders translated.
7. Screen-reader pass (NVDA): confirm aria-live announcements fire on
   response (see `docs/qa/NVDA-VERIFICATION-PROCEDURE.md`).
8. Mobile 590px: confirm forms + result panels reflow.
