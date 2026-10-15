# Clickable prototypes - Sentientia content engine

Six screens on the real design tokens, one fixture course ("Handling a disputed UPI transaction", SOP-CS-014), seen from six seats. Open `index.html` in Chrome or Edge from the file system: no server, no build, no external scripts. Montserrat loads from Google Fonts only if you are online (add `?nofonts=1` to skip it); Noto script fonts are not bundled, system fonts are the fallback.

Every screen runs light and dark (header button, or `?theme=dark`), left-to-right and right-to-left (header toggle, or `?dir=rtl`), at 1440 px and 375 px, and has an "About" drawer listing what to click, the patterns demonstrated, what it maps to in the theme and what is not built. All data is synthetic. Nothing is saved or sent. Audio is silent and AI is mocked.

## The six screens

| # | File | Seat and task | What is clickable | Maps to in the real product |
|---|---|---|---|---|
| 1 | `01-learner-home.html` | Learner: what must I finish, and by when? | language preference changes course titles and "not available in this language" notes; Resume links to the player; three-question Daily Review with feedback and a 2 / 7 / 30 day schedule; Add to my plan | theme learner dashboard shell; Daily Review (ADR-028 Phase 3.7) |
| 2 | `02-course-player.html` | Learner: take the course | narration playback with word-by-word captions (simulated), speed, scrub, captions toggle, transcript; English, Hindi (human text from the fixture) and Arabic (illustrative draft, unreviewed) with RTL; scenario with consequence reveals; graded quiz; live saved-progress token | `pilot/lib/player` is the working version of this design |
| 3 | `03-authoring-studio.html` | Author: SOP to course | five gated steps; PII scan block; fidelity setting; outline edit, reorder with buttons, delete with undo; staged mock generation with cost and Cancel; AI refinement with diff, revert and verbatim lock; validation R01-R12; two approvals gate the publish button | `local_sentientia_authoring` studio + the compile step |
| 4 | `04-localisation-review.html` | Translator, subject expert, admin | 13 language tabs with review-state dots; segment grid with live QA (placeholders, numbers, protected terms, Latin leakage, punctuation); memory matches; glossary; comments; audio tab; three roles with their own actions; reassign with required feedback; learner preview that mirrors for RTL with bidi isolation | `local_sentientia_translate` review UI extended with XLIFF, TM and QA |
| 5 | `05-admin-analytics.html` | L&D admin, auditor | tenant, period and course filters recalculate every figure; definitions and freshness on each KPI; chart with a table view; decision-level scenario shares; language coverage matrix with letters as well as shades; review queue and capacity; spend with processing-region badges; sortable table and CSV export | `local_sentientia_analytics` + cmi.interactions / xAPI (Stage 2.5) |
| 6 | `06-scenario-editor.html` | Scenario designer | node-graph canvas: drag, or arrow keys; inspector edits prompts, targets, scores, outcomes and consequences; "Add choice" connects without dragging; live checks for dead ends, orphans, loops, missing correct answers and the state budget; undo/redo; Play mode; analytics overlay | scenario block in the course schema (rules R04, R05); Stage 2.1 editor |

## Token fidelity

`assets/tokens.css` is derived from `theme/sentientia/scss/moodle/_tokens.scss` and `_tokens-dark.scss`; token names and values are kept except where listed in its header: two deviations requested by the brief (content width 1440 px instead of 1400 px; focus ring 2 px instead of 3 px) and a set of tokens these prototypes propose and the theme does not have yet (chart sequential ramp, the seven review-state colours, scenario outcome colours, script-specific font stacks and line heights). `assets/proto.css` uses tokens only: no colour literal, durations come from `--ap-duration-*` and collapse to 0 ms under `prefers-reduced-motion`.

## How it was checked (2026-10-15, Chromium through Playwright, served from localhost)

- All seven pages in four views each (1440 light, 1440 dark, 375 light, 1440 RTL): zero JavaScript errors, zero horizontal overflow (a first pass found five overflowing views, all fixed: an absolutely positioned screen-reader span inside a scroll container, flex children without `min-width: 0`, and an inline two-column grid that did not collapse at 375 px).
- A scripted walk through the main interactions of every screen: Hindi titles on the home page and the review loop; narration highlight, Arabic switch to RTL, scenario terminal, failed quiz and token update in the player; PII block, reorder, delete and undo, generate, refine and revert, gated publish in the studio; four QA errors on the Arabic draft clearing after edits, then submit, expert approval, admin sign-off and a reassign with feedback; filter-driven figures, table view and sort in analytics; deleting a choice in the editor producing "decision needs at least 2 choices" and "not reachable", undo restoring "All checks pass", arrow-key movement and Play mode. One real defect was found and fixed (the player computed narration words for blocks without narration).
- Screenshots of every screen in the four views are in `screenshots/`.

Not done: no screen-reader or switch-device pass, no automated accessibility audit (axe or Lighthouse), no performance measurement, no Urdu content, no long-string stress test beyond the Hindi and Arabic samples. The rubric scores below are the author's own and reflect that.

## Self-assessment against the ten-criterion rubric (0 to 3; STUDY section 10.5)

Author's own scoring of the prototypes, not independent; "n/a" means the criterion does not apply to the screen; performance was not measured on any screen.

| Criterion | 1 Home | 2 Player | 3 Studio | 4 Localisation | 5 Analytics | 6 Scenario |
|---|---|---|---|---|---|---|
| Primary task clarity | 3 | 3 | 3 | 2 | 2 | 2 |
| Information hierarchy | 3 | 3 | 2 | 2 | 3 | 2 |
| Progressive disclosure | 2 | 3 | 3 | 2 | 2 | 2 |
| Feedback and status | 2 | 3 | 3 | 3 | 2 | 3 |
| Error prevention and recovery | n/a | 2 | 2 | 2 | n/a | 3 |
| Accessibility (keyboard paths, labels, 24 px targets, focus ring; not assistive-technology tested) | 2 | 2 | 2 | 2 | 2 | 2 |
| Responsive | 3 | 2 | 2 | 2 | 2 | 2 |
| Localisation readiness | 2 | 2 | 1 | 2 | 2 | 1 |
| Performance | not measured | not measured | not measured | not measured | not measured | not measured |
| AI transparency and control | n/a | n/a | 3 | 2 | 2 | n/a |

Weak points the scores admit: the studio and the scenario editor are English-only authoring surfaces; the localisation screen has no Urdu content and no recorded warning overrides; the editor canvas does not mirror in RTL and a node graph has limited assistive-technology semantics (the inspector is the accessible path).
