# Cross-Persona Logical Walkthrough (L axis)

**Date:** 2026-05-08

This document seeds the L axis of `ENTERPRISE-GRADE-GAPS.md` for the
4 plugins shipped during the 2026-05-07 / 05-08 stretches. Each
walkthrough is a scripted journey that validates the full-loop user
experience from a specific persona's view.

**How to use:** for each plugin, walk all 5 persona scripts. A
plugin reaches L✅ only when every script completes without dead-ends,
hidden state, or "where do I go next?" confusion.

**Test users (local XAMPP):** all use password `Airpay@Test2026!`

```
TEST_SITEADMIN       academy@airpay.co.in           id=2
TEST_LEARNER_AIRPAY  rasika.thakare@airpay.co.in    id=3113
TEST_LEARNER_PUBLIC  demoairpayacademy@gmail.com    id=1830
TEST_LEARNER_ZEEA    raya.ahmada@zeeasmz.go.tz      id=1730
TEST_MANAGER         kunal@airpay.co.in             id=237
TEST_TRAINER         asif.ansari@airpay.co.in       id=2304
TEST_LDADMIN         joseph.mandapati@airpay.co.in  id=627
```

---

## 1. `local_airpay_manager` (request approval + course allocation)

### As LEARNER

1. Navigate to a course detail page in the catalog.
2. Click "Request enrolment" (if surface exists; otherwise drop into
   the course directly per legacy enrol flow).
3. Fill reason: "Need this for Q3 OKR."
4. Submit.
5. **Expected:** in-app notification arrives **after** manager decides.
   ✅ V1.2.0 close: `request_decided` message provider fires.

### As MANAGER

1. Login → /local/airpay_manager/index.php (Team dashboard).
2. **Expected:** banner shows "N pending requests" (sourced from
   `pending_request_count`).
3. Click "Requests" tab → see learner's request with reason visible.
4. Click "Approve" → modal opens with the summary card.
5. Type a decision note: "Approved — aligns with Q3 OKR."
6. Submit.
7. **Expected:** request row status changes to "approved"; learner is
   auto-enrolled in the course; learner gets a Moodle message.
   ✅ V1.2.0 close: notification path complete.
8. Switch to "Allocations" tab.
9. Click "Bulk assign" button.
10. Select 3 direct reports from the multi-select; pick a course; set
    a due date 2 weeks out; note "Q3 priority push."
11. Submit.
12. **Expected:** toast shows "Allocated to 3 user(s)"; rows appear in
    the table; each user gets an `allocation_assigned` notification.
13. Click "Export decisions CSV" → CSV downloads with both requests + allocations.

### As ADMIN

1. Login → can see ALL managers' decisions via siteadmin bypass.
2. Walk through requests + allocations to confirm tenant scoping
   (managers see their own; admin sees all).

### As AUDITOR (compliance review)

1. Open the CSV export.
2. **Expected:** every decision has timestamp, user, course, status,
   manager note, and decided_by ID.
3. Verify there are no orphan requests (every approved request has a
   matching enrolment in `mdl_user_enrolments`).
4. Verify manager allocations show due dates where set.

### As LD ADMIN (cross-tenant)

1. View dashboards across multiple managers.
2. Verify request + allocation history per manager.

### Dead-end checklist (must NOT happen)

- ❌ Manager approves request, learner has no idea (FIXED in V1.2.0)
- ❌ Manager allocates, learner has no idea (FIXED in V1.2.0)
- ❌ CSV export missing key fields
- ❌ Bulk allocate returns 200 but no rows actually inserted
- ❌ Approving a duplicate request creates duplicate enrolments

---

## 2. `local_airpay_roles` (capability management UI)

### As ADMIN

1. /local/airpay_roles/index.php → 7+ stock roles in sortable table.
2. Click row → /view.php?id=N&tab=overview → 4 stat cards.
3. Switch to "Capabilities" tab → search, filter, see badge per cap.
4. Click pencil icon on a cap → modal opens, change perm to "allow",
   add reason "Phase X cohort needs this", save.
5. Switch to "Audit" tab → see the row you just created.
6. Back to /index.php → click "Audit log" button → see the same row
   in the global audit feed.
7. Click pencil to bulk-edit a cap (Phase 2: "Bulk apply" button — in
   the **next** stretch after wired into UI).
8. /audit.php → filter by role → see only that role's audit history.
9. Click "Export CSV" → audit log downloads with UTF-8 BOM.

### As COMPLIANCE AUDITOR (`:audit` cap only)

1. Login → can see /audit.php but the action buttons are absent
   (no `:manage` cap means no edit or reset buttons).
2. Verify CSV export works for audit-only callers.

### As MANAGER

1. Login → can view /index.php but cap-mutation buttons are gated.
2. Cannot navigate to /audit.php (`:audit` cap missing).

### As LEARNER

1. /index.php returns 403 (`:view` cap on manager archetype only).

### Dead-end checklist

- ❌ Empty audit log when changes have been made (event observer
   miss → INDEED A REAL BUG IF SEEN)
- ❌ Bulk caps endpoint exists but no UI button (Phase 2 work — UI
   button TBD; WS endpoint shipped)
- ❌ Lockout: removing site:config from manager archetype (already
   blocked by `update_capability` defensive check)

---

## 3. `local_airpay_challenge` (gamification)

### As LEARNER (PARTICIPANT)

1. /local/airpay_challenge/index.php → see active challenges.
2. Pick a challenge → click "Join challenge".
3. **Expected:** join succeeds; row updates to show "Leave" button.
4. /local/airpay_challenge/leaderboard.php → see the leaderboard
   (initially empty if no completions).
5. Complete a course (separate flow). Wait for `course_completed`
   event to fire.
6. **Expected:** challenge attempt status updates to in_progress (or
   completed if target met); leaderboard refreshes after the 15-min
   recompute task or on next event.

### As LEARNER (STREAK)

1. Join a streak-typed challenge.
2. Login on consecutive days.
3. **Expected:** progress increases by 1 per consecutive day.
4. **Expected:** breaking the streak resets progress.

### As ADMIN

1. /local/airpay_challenge/index.php → "New challenge" button.
2. Modal opens → fill name, shortname, type=course_completion,
   targetcount=3, points=500, status=Active.
3. Submit → row appears.
4. /local/airpay_challenge/view.php?id=N&tab=overview → 4 stat cards.
5. Switch to participants tab → see who's joined.
6. Switch to leaderboard tab → see ranked list.

### As COMPLIANCE AUDITOR

1. View challenges + leaderboard.
2. Export... [no CSV export currently — Phase 2].

### Dead-end checklist

- ❌ Joined challenge but no progress tracking (FIXED — observer fires)
- ❌ Streak breaks but UI still shows old streak count (auto-recompute
   on next login fixes this)
- ❌ Past-end-date attempts stay forever (FIXED — `expire_overdue_attempts`
   in scheduled task)
- ❌ Quiz challenges missing handler (FIXED in Phase 2 — quiz_score type
   evaluates against threshold + targetcount filter)

---

## 4. `local_airpay_skills` (competency framework)

### As ADMIN

1. /local/airpay_skills/admin.php → see categories + skills tables.
2. Click "New Skill" → create "Python", category=Tech, max_level=5.
3. **Expected:** skill row has the new "Levels" link icon.
4. Click "Levels" link → /level_definitions.php?skillid=N.
5. **Expected:** 5 rows for Python L1-L5, all "Default" status.
6. Click pencil on L1 → modal opens, label="Hello world", description
   "Can write print('hi')", save.
7. **Expected:** L1 row shows "Saved" status, label updates.
8. Click "Designation matrix" → /designation_matrix.php.
9. Pick "Senior Engineer" from dropdown.
10. Click "Add required skill" → modal: skill=Python, level=4.
11. Submit.
12. **Expected:** row appears in matrix.
13. Click "Copy to another designation" → prompt for "Junior Engineer".
14. **Expected:** all required skills copy across with same levels.

### As LEARNER

1. /local/airpay_skills/index.php → personal skills dashboard with
   gap analysis, radar chart, recommended courses.

### Dead-end checklist

- ❌ Edit/delete buttons on existing skill/category rows did nothing
   (FIXED 2026-05-08 — data-id vs data-skillid bug)
- ❌ Course-skill mapping UI missing — admins can't tag courses with
   which skill they teach (DEFERRED, Phase 2)
- ❌ Skill credit on completion missing — completing a course doesn't
   automatically update the user's skill level (DEFERRED, Phase 2)

---

## 5. Cross-plugin integration walkthrough (the most-likely-broken seams)

This catches gaps where two plugins interact but neither owns the
contract.

### Manager → Challenge

1. As MANAGER: allocate a course to a direct report.
2. As LEARNER: complete the course.
3. **Expected:** if the course is part of an active challenge AND the
   learner joined that challenge, the learner's challenge progress
   updates.
4. Currently uncovered — the integration test would seed both a
   manager allocation AND a challenge join, then trigger course
   completion.

### Roles → Audit log → Privacy export

1. As ADMIN: change a cap on a role with reason "test reason".
2. As ADMIN: trigger a GDPR data export for the user who made the
   change.
3. **Expected:** the audit row appears in their export (validated by
   the new privacy provider PHPUnit tests).

### Skills → Course → User

1. (PHASE 2) As ADMIN: tag a course with skill "Python" → level 3.
2. As LEARNER: complete the course.
3. **Expected:** `local_airpay_user_skills` gets a row for that
   user/skill at level 3.
4. Currently NOT WIRED. This is one of the highest-value Phase 2
   completions.

---

## How L closure is signed off

For each plugin:
1. **Print this document.** Walk it as an actual user (no shortcuts).
2. Tick each step with the date + initials of the walker.
3. If a step fails, log a defect inline; create a ticket with the
   plugin, persona, and step number.
4. Plugin gets L✅ in `ENTERPRISE-GRADE-GAPS.md` only when ALL persona
   scripts pass for it.

This is the same standard a real enterprise user-acceptance-test
plan would set, just done in-house for production readiness.
