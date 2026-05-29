# QA Walk — Trainer Persona
**Date:** 2026-05-29
**Persona:** qa_trainer (id 3419, tenant /1, username `qa_trainer`)
**Tester:** Automated QA sub-agent via Chrome DevTools MCP

---

## 1. Login

- Login via `login/index.php` → credentials submitted → redirected to `local/airpay_pages/onboarding.php` (first-login onboarding screen).
- Clicked "Skip for now" → landed on `http://localhost:8080/moodle/my/`.
- **LOGIN: OK**

---

## 2. Detected Shell / Role

```
Role assignments:
  employee  archetype=student  contextid=1
  trainer   archetype=teacher  contextid=1
```

Dashboard heading: "Team overview and compliance status" — the theme's `user_type_provider` detected this user as a **Manager/Trainer** shell (not pure Learner), surfacing leaderboard, "My Team" sidebar link, and compliance sidebar link.

No trainer-specific redirect occurred. The dashboard loads the generic Manager shell, not a dedicated trainer dashboard.

---

## 3. /my/dashboard.php Redirect

- Navigated to `http://localhost:8080/moodle/my/dashboard.php`
- **Final URL: `http://localhost:8080/moodle/my/`** (standard Moodle redirect to `/my/`)
- Did **NOT** redirect to `/blocks/airpay_trainer/dashboard.php`
- The dashboard.php in CLAUDE.md notes the theme's core_renderer "redirects for users with the trainer cap" — this redirect is not active for qa_trainer because `block/airpay_trainer:viewtrainerslist` evaluates to **NO** (see below).

---

## 4. Sidebar Dead-link Check

Sidebar links for qa_trainer:

| Link | URL | Status | Result |
|------|-----|--------|--------|
| Dashboard | `/my/` | 200 | OK |
| My Team | `/local/airpay_manager/index.php` | HTTP 500 | **BROKEN** — `nopermission`: "You have no direct reports. This dashboard is for managers with team members." |
| Compliance | `/local/airpay_compliance_report/index.php` | 200 | OK (renders "Compliance Report" heading) |
| My Courses | `/local/airpay_catalog/mycourses.php` | 200 | OK |
| Catalog | `/local/airpay_catalog/public.php` | 200 | OK |
| Certificates | `/local/airpay_pages/certificates.php` | 200 | OK |
| Profile | `/local/airpay_users/profile.php` | 200 | OK |

**Dead link found: "My Team" sidebar link → 500 nopermission for trainers with no direct reports.**

The `airpay_manager/index.php` throws an exception rather than a graceful empty state when the user has no direct reports. A trainer role is given the "My Team" sidebar item by the same Manager-shell detection, but the underlying manager plugin requires actual report relationships.

---

## 5. OA-08 Verdict: Sentientia Live

### Evidence collected

**`/local/sentientia_live/index.php`** — page title: "Sentientia LMS — Live engagement", content:

> "Phase E.0 — Foundation. The Live engagement feature is being built incrementally. Phase E.0 ships the database schema and capability framework. Trainer + audience UIs land in Phases E.1 and E.2."

This is a **Phase E.0 placeholder page, intentionally static**.

**`/local/sentientia_live/trainer/index.php`** — the real Phase E.1 trainer dashboard, requires `local/sentientia_live:create`. qa_trainer gets:

```
Has live:create = NO
Has live:run   = NO
```

### Root cause

`access.php` grants `local/sentientia_live:create` only to archetypes `editingteacher` and `manager`. The BizLMS `trainer` role has `archetype=teacher` (NOT `editingteacher`). No explicit role-capability override for the `trainer` role exists in the DB. Therefore qa_trainer cannot reach the real trainer dashboard even though it is fully implemented at `trainer/index.php`.

The `feature_flags::is_enabled('live.enabled')` flag is **ON**, so the feature is live — the barrier is purely the capability archetype mismatch.

### Verdict: **REAL BUG (OA-08 confirmed)**

`index.php` IS a stale E.0 placeholder — the real trainer UI lives at `trainer/index.php` (Phase E.1+, fully shipped). The sidebar does not link to `trainer/index.php` at all. Even if it did, qa_trainer cannot enter because `trainer` role (archetype=`teacher`) is not in the `local/sentientia_live:create` archetype grant list. Two problems compound:

1. The sidebar has no "Live Sessions" link pointing at `trainer/index.php`.
2. The `trainer` archetype is not granted `local/sentientia_live:create` — needs an explicit capability override or the access.php must add `'teacher' => CAP_ALLOW`.

---

## 6. Sentientia Live Deep-Dive (Authoring)

Because qa_trainer cannot reach `trainer/index.php` (nopermissions), the full authoring flow could not be exercised from this persona:

| Step | Result |
|------|--------|
| Trainer dashboard (`trainer/index.php`) | Blocked — nopermissions (live:create not granted to teacher archetype) |
| Create session form | Not reached |
| Slide editor | Not reached |
| Run page | Not reached |

- **Session created: NO** (capability blocker)
- **Slide added: NO**
- **Run page rendered: NO**

The feature is fully implemented (live.enabled flag is ON, trainer/index.php, trainer/create.php, trainer/run.php all exist) but is inaccessible to the `trainer` BizLMS role due to the archetype mismatch.

---

## 7. Breadth Probe Table

| URL | HTTP | Title | Errors |
|-----|------|-------|--------|
| `/local/sentientia_live/index.php` | 200 | "Sentientia LMS — Live engagement" | Placeholder (E.0 static page — not a trainer dashboard) |
| `/local/airpay_manager/index.php` | 500 | "Error" | `nopermission`: no direct reports |
| `/local/airpay_compliance_report/index.php` | 200 | "Compliance Report" | None |
| `/local/airpay_catalog/mycourses.php` | 200 | "My courses" | None |
| `/local/airpay_catalog/public.php` | 200 | "Course Catalog — airpay academy" | None |
| `/local/sentientia_leaderboard/index.php` | 200 | "Sentientia LMS — Real-time Leaderboards" | None |
| `/blocks/airpay_trainer/dashboard.php` | 500 | "Airpay Trainer Dashboard" | `Class "block_airpay_trainer" not found` — block class missing |
| `/local/airpay_users/profile.php` | 200 | "QA Trainer - Profile" | None |

---

## 8. Console Errors

No JavaScript console errors on the main dashboard (`/my/`).

---

## 9. Candidate Bugs

| ID | Sev | Description |
|----|-----|-------------|
| **T-01** | P1 | `local/sentientia_live:create` not granted to `trainer` archetype (`teacher`) — the trainer role cannot reach `trainer/index.php`; fix: add `'teacher' => CAP_ALLOW` to `access.php` create+run capabilities |
| **T-02** | P1 | No sidebar link to `local/sentientia_live/trainer/index.php` — trainer persona has no navigation path to Live Sessions from the dashboard |
| **T-03** | P2 | `local/airpay_manager/index.php` throws an exception (nopermission) instead of a graceful empty state when a Manager-shell user has zero direct reports — "My Team" sidebar link crashes for any trainer provisioned without team members |
| **T-04** | P2 | `blocks/airpay_trainer/dashboard.php` throws `Class "block_airpay_trainer" not found` — the block plugin class is missing or not deployed; this page is referenced by the CLAUDE.md redirect logic |
| **T-05** | P3 | `/my/dashboard.php` does not redirect to the trainer-specific dashboard (expected `/blocks/airpay_trainer/dashboard.php`) because `block/airpay_trainer:viewtrainerslist` evaluates to NO — coupled with T-04 above |

---

## 10. Screenshots Saved

- `trainer-01-dashboard.png` — trainer dashboard at `/my/`
- `trainer-live-index-placeholder.png` — `sentientia_live/index.php` E.0 placeholder

---

## 11. Summary

The trainer persona logs in cleanly. The dashboard renders a Manager shell (team-overview heading, leaderboard, compliance sidebar). The distinctive trainer surfaces are all blocked or missing:

- **Sentientia Live** is fully implemented (`trainer/index.php` through `trainer/run.php`) but unreachable because the BizLMS `trainer` role (archetype=`teacher`) is not listed in `local/sentientia_live:create` archetypes. The root `index.php` is an intentional E.0 placeholder — the real UI is one directory deeper and unlinked.
- **My Team** crashes on first load (no direct-reports exception).
- **block_airpay_trainer** class is missing — the trainer block dashboard is broken.

The feature flag (`live.enabled`) is ON, so once T-01 and T-02 are fixed, the full Live authoring flow should be accessible to any trainer-role user.

---

## 12. Resolution — T-01 + T-02 (2026-05-29)

Both bugs fixed in the same session. `local_sentientia_live` `2026052504` → `2026052900`; `theme_airpayux\sidebar_navigation` updated.

### T-01 — capability (page-layer access)
- **`db/access.php`** — added `'teacher' => CAP_ALLOW` to `local/sentientia_live:create` and `:run`. This is the permanent declaration that fresh installs, future Sentientia customers, and "Reset role to defaults" read.
- **`db/upgrade.php`** — new step (savepoint `2026052900`) back-fills the new default onto every existing `archetype=teacher` role via `assign_capability(..., overwrite=false)`. Required because Moodle only applies archetype defaults on a capability's **first** install — confirmed in `lib/accesslib.php::update_capabilities()` (the `assign_legacy_capabilities()` call lives inside the `$newcaps` loop), so editing `access.php` alone never reaches a cap already in `{capabilities}`. `overwrite=false` respects any deliberate admin `CAP_PREVENT`.

**Verification** (qa_trainer, id 3419, role `trainer`/archetype `teacher` @ system):

| Capability | Before | After |
|------------|:------:|:-----:|
| `local/sentientia_live:create` | NO | **YES** |
| `local/sentientia_live:run` | NO | **YES** |

The owner's exact one-liner now prints `YES`. Upgrade ran clean (`++ 2026052900: Success ++`) and purged caches.

### T-02 — navigation (sidebar link)
- **`sidebar_navigation.php`** — new `can_create_live_session()` helper (`live.enabled` flag **AND** `has_capability('local/sentientia_live:create')`, both safe-failing to `false`), and a "Live Sessions" → `/local/sentientia_live/trainer/index.php` item added to **both** the Manager and Learner shells. Surfacing it in both (not just Manager) covers pure-trainer-role users who land in the Learner shell — mirrors the existing `iscomplianceuser` pattern. Capability-gating mirrors the OA-GRAN dead-link fix.

**Verification** — rendered qa_trainer's actual sidebar (Manager shell):
```
Dashboard · My Team · Compliance · Live Sessions · ──── · My Courses · Catalog · Certificates · Profile
                                   ^^^^^^^^^^^^^^ -> /local/sentientia_live/trainer/index.php
```

### Visual evidence
No live browser screenshot captured this session — the QA-walk `chrome-devtools` MCP isn't connected, and driving the user's own Chrome would swap the `MoodleSession` cookie and disrupt their session (same call documented for C-002 v2). Verification is deterministic (capability one-liner + programmatic sidebar render via `tools/_qa_t01_live_capcheck.php` + `tools/_qa_t02_navdump.php`). A live PNG can be captured on request before the production deploy.

### T-04 — block dashboard fatal (FIXED 2026-05-29)
`/blocks/airpay_trainer/dashboard.php` did `new block_airpay_trainer()` without loading the class. core_component does not autoload legacy `block_<name>` classes (confirmed: `class_exists('block_airpay_trainer', autoload=true)` → FALSE), so the page fatalled with "Class not found" for *anyone* who reached it — including the editingteacher/manager users the redirect still fires for. Fixed by `require_once`-ing `blocks/moodleblock.class.php` (block_base) + `block_airpay_trainer.php` before instantiating. Verified via `tools/_qa_t04_verify.php`: class loads, `get_content()` returns the graceful "No training sessions assigned." empty state for qa_trainer, no fatal. Pure page-logic fix — no version bump (no DB/cap/install change).

### T-05 — trainer redirect never fires (owner decision pending)
Root cause confirmed (same shape as T-01): the redirect gate `block/airpay_trainer:viewtrainerslist` (`core_renderer.php:1254-1265`) grants `editingteacher`+`manager`, not `teacher`; the BizLMS fallback cap `block/trainerdashboard:viewtrainerslist` isn't registered locally. **Recommendation: leave by-design.** With T-01/T-02, trainers now land on the rich `/my/` shell + Live Sessions link; granting the cap would force-redirect them to the minimal classroom-sessions block page — a regression. Awaiting owner call: (a) by-design [recommended], (b) grant the `teacher` archetype the cap so the legacy redirect fires, or (c) retarget the redirect to Sentientia Live `trainer/index.php`.
