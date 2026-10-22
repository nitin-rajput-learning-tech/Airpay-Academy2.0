# Visual evidence: SCORM packager runtime fix in Moodle's real player (2026-10-22)

Research item 2 (`scripts/agents/agent5_scorm_packager.py`, commit 946e90b9f), checked end to end on local XAMPP
(Moodle 5.1) in Moodle's own SCORM 1.2 player and API, not a test double.

**Setup (local only):** `SAMPLE-SOP` (10 slides) was packaged by the fixed packager and added as the SCORM activity
"E2E resume test (2026-10-22)" (cmid 1885) to the test course `vp_journey_free`. The learner is the test persona
`vp_learner1`.

**Run:** open the activity (Slide 1 of 10), press Next twice (Slide 3 of 10), leave for the dashboard, then open the
activity again.

| Check | Result |
|---|---|
| The relaunch resumes where the learner left | "Slide 3 of 10" (shot `01-learner-reopened-resumes-at-slide-3`) |
| `cmi.core.lesson_location` stored by Moodle | `2` (the slide index) |
| `cmi.core.exit` | `suspend` |
| `cmi.core.lesson_status` | **`incomplete`**: leaving no longer records the course as completed (before the fix, `finish()` called `complete()`) |

`cmi.core.session_time` did not reach Moodle in this run. Browsers drop requests sent while a page unloads, which is
also why the place is committed on every slide change rather than only at exit. The unit tests (Node run of the driver)
cover the session time value.
