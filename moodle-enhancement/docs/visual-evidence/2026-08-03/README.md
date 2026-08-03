# Visual evidence — 2026-08-03 — UI-NAV-AUDIT execution (all phases)

Executes the full plan from `docs/audits/UI-NAV-AUDIT-2026-08-03.md`.

## Phase A — course experience re-shelled (N-01, N-02)
`phaseA-course-shell-light.png` — the money shot: /course/view.php?id=403 as
qa_employee now renders inside the SAME app shell as the rest of the platform
(persona sidebar, ap-topbar with search, compact banner, sticky progress bar
pinned flush, single "Course Content" TOC, platform footer). Implementation:
`course.mustache` rebuilt on the `airpay_shell_start/end` pattern (like
columns2/drawers); the previous boost-drawers markup preserved byte-for-byte
as `course_editing.mustache` and rendered only when `$PAGE->user_is_editing()`
— edit mode verified regression-free BOTH directions (switch on → drawers
world with course-index drawer + edit controls; off → shell). Extra fixes en
route: `core_course_drawer()` now only called in edit mode (killed 2
"Reactive components needs a main DOM element" console errors), TOC builder
includes section 0 (courses keeping activities in "General" previously got NO
TOC), core `edit_switch()` surfaced in the shell (the boost navbar that
carried it is gone from viewing), sticky-bar geometry adapted to the shell
scroller (`.ap-shell__content` padding moved to `#page` so the bar pins flush).

## Phase B — breadcrumbs (N-03)
Shell breadcrumb strip rendered by `airpay_shell_start()` on nested pages,
suppressed on dashboard + single-crumb trails. Verified: quiz page shows
"POSH Training > POSH Training Test"; course root + top-level admin pages
correctly suppressed.

## Phase C — dark-mode tokenization (N-04)
305 hardcoded hexes across 11 partials replaced with semantic `--ap-color-*`
tokens (87% of all true literals; 47 justified leave-alones documented in the
agent report — status colors on the `[data-theme]`-only remap, brand gradient
stops, a11y focus yellows). Flagship symptom fixed: the near-WHITE quiz/
activity description card in dark mode (root cause `modules.scss`
`.path-mod .activity-header { background-color: $gray-100 }` with no dark
override) — verified live: bg rgb(26,29,39), text rgb(232,234,237).

## Phase D — nav correctness
- D2 (N-06): notifications + messages icon links added to the shell topbar
  right slot (verified on learner + orgadmin).
- D1 (N-05) re-examined → works-as-designed (Cancel-left/primary-right,
  correct styling); audit amended.
- D3 (N-07) re-examined → deliberate (`moodle/site:viewreports` gate mirrors
  page-layer auth per Goal A Bug #11); audit amended.

## Phase E — titles (N-08)
- "My Certificates | | airpay" → "My Certificates | airpay" (root cause:
  `get_config('moodle','shortname')` is not a real key — returned empty).
- "Airpay User Engine" → "Manage Users" (new string, en+hi).
- "Airpay Skills Matrix" → "My Skills" (new string, en+hi).
- Shell sr-only h1 now prefers the clean page heading (was announcing
  "My Skills | airpay" to screen readers). Verified: h1 = "Manage Users".

## Phase F — verification
Fingerprint re-walk (in-app browser): learner course/quiz (shell, TOC,
breadcrumbs, flush bar, dark card), certificates + skills titles, orgadmin
manage-users (shell, icons, title, sr-h1), siteadmin edit-mode round-trip.
Console on the shell course page: 1 pre-existing error (course_default image
404 — tracked separately), the 2 reactive-component errors eliminated.

Versions: theme 2026080300/1.0.50-beta; sentientia_users 2026080300/2.7.4;
sentientia_skills + sentientia_pages 2026080300. Duplicate local/ tree synced.
