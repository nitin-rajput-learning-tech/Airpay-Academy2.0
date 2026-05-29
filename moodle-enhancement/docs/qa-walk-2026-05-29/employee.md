# QA Walk — Employee / Learner Persona
**Date:** 2026-05-29  
**Persona:** qa_employee (id 3421, tenant /1 Airpay, shell: Learner)  
**Credentials:** qa_employee / Qa@Airpay#26  
**Tester:** QA Sub-agent (Claude)

---

## 1. Login

**Result: OK**

Login succeeded. System redirected to `/local/airpay_pages/onboarding.php` — expected first-run behaviour for a new account. The onboarding wizard rendered correctly with "Let's Go", "Continue", "Back" and "Skip for now" buttons. Clicking "Skip for now" navigated to `/my/` (Dashboard). Title confirmed: `Dashboard | airpay`.

---

## 2. Sidebar Shape — Learner-Correct?

**Result: LEARNER-CORRECT. No admin-link leak. No dead links.**

Sidebar links extracted:

| Link text | Href | Status |
|-----------|------|--------|
| airpay academy (logo) | /my/ | 200 |
| Dashboard | /my/ | 200 |
| My Courses | /local/airpay_catalog/mycourses.php | 200 |
| Catalog | /local/airpay_catalog/public.php | 200 |
| Certificates | /local/airpay_pages/certificates.php | 200 |
| Profile | /local/airpay_users/profile.php | 200 |
| User avatar → Profile & Settings | /local/airpay_users/profile.php | 200 |
| Logout | /login/logout.php?sesskey=... | — |

Admin-keyword scan: **0 admin links detected** (no "Manage Users", "Manage Courses", "admin/", "course/management", etc.).

All 5 navigable sidebar hrefs returned HTTP 200 with correct titles.

---

## 3. Learner Journey

### 3.1 Catalog (`/local/airpay_catalog/public.php`)

**Result: RENDERS — courses visible, search present**

- Page title: "Course Catalog — airpay academy | airpay"
- Course cards visible (confirmed via course links — Aptitude Test Advanced, POSH Training, AI/ML course, etc.)
- Search input present (`hasSearch: true`)
- `cardCount` via `.ap-course-card` selector = 0 (CSS class variant differs); broader selector found 14 card-type elements and course links confirmed real courses are listed
- Each catalog card has "Details" and "Enroll" actions

**Note on Enroll button:** The catalog list view points the "Enroll" link to `?action=addtocart&sesskey=...` — this passes through the payment gateway layer (`paygw_airpay`) even for Airpay-tenant employees. On the course detail page, the same course correctly shows "Enroll Now — Free" pointing to `/enrol/index.php?id=71`. The cart flow appears to resolve to free for Airpay employees, but the label "addtocart" in the list view is potentially confusing and was flagged as candidate bug E-01.

Screenshot: `employee-02-catalog.png`

### 3.2 Enrolment Flow

**Result: BROKEN — employee cannot self-enrol via catalog or course detail**

Steps taken:
1. Clicked "Enroll" on catalog card (via fetch) → redirected to `course.php?id=71&added=1` with `enrolled` signal in response
2. Navigated to `enrol/index.php?id=71` → silently redirected to `/my/` (dashboard) with no enrolment persisted
3. Navigated to `course/view.php?id=71` → redirected to `/my/` (unenrolled)
4. Confirmed via `mycourses.php`: "No courses found" — user has zero enrolled courses after attempting enrolment

**Root cause hypothesis:** `paygw_airpay` processes the free enrolment through a cart-checkout flow that requires a payment confirmation step or webhook, even when price = 0. The `enrol/index.php` redirect without enrolling suggests the self-enrol enrolment method is not enabled (or is gated behind the payment plugin) for course 71. The employee cannot start learning without admin pre-enrolment.

**Severity: P1 — Core learning journey is broken for self-service enrolment. Airpay employees cannot enrol in free courses without admin intervention.**

### 3.3 Course View

**Result: NOT REACHED (blocked by enrolment issue)**

`/course/view.php?id=71` redirects to dashboard for unenrolled user — expected Moodle behaviour. Could not test course content, activity rendering, or activity attemptability due to enrolment being broken.

Screenshot: `employee-03-course.png` (course detail page from catalog — pre-enrolment view, showing "Enroll Now — Free" button)

### 3.4 Activity / Evaluation

**Result: NOT TESTED (blocked by enrolment issue)**

Could not reach any course activity without enrolment. Evaluation plugin learner-facing pages were not accessible as a standalone path.

### 3.5 Profile (`/local/airpay_users/profile.php`)

**Result: RENDERS CORRECTLY**

Profile rendered with:
- Name: QA Employee
- Email: qa_employee@qa.local
- Role: Employee
- Points: 10 pts / Beginner · Rank #10
- Badges: 1
- Streak: 1 day
- Organisation: AIRPAY PAYMENT SERVICES PRIVATE LIMITED
- Department: All
- Reporting To: QA Manager
- Employee ID, Location, Phone, Join Date: N/A (not set)

No edit link visible on the custom profile page (edit is via `/user/edit.php` which is accessible from the user menu). No errors.

Screenshot: `employee-04-profile.png`

### 3.6 Certificates (`/local/airpay_pages/certificates.php`)

**Result: RENDERS CORRECTLY (empty state)**

Page rendered with correct empty state: "0 certificates earned — No certificates yet. Complete courses with certificates enabled to earn your credentials." Browse Courses CTA present. No errors.

### 3.7 Cart Presence Check

**Result: CORRECTLY ABSENT**

No cart link, cart badge, or cart-region element found in the navbar or sidebar for the Airpay-tenant employee. Cart is correctly suppressed.

---

## 4. Breadth Probe

| URL | Status | Title | Error |
|-----|--------|-------|-------|
| /local/airpay_catalog/public.php | 200 | Course Catalog — airpay academy | none |
| /local/airpay_catalog/mycourses.php | 200 | My courses | none |
| /local/airpay_users/profile.php | 200 | QA Employee - Profile | none |
| /local/airpay_pages/certificates.php | 200 | My Certificates | none |
| /local/airpay_skills/index.php?userid=3421 | **404** | Error | nopermission |
| /local/sentientia_leaderboard/index.php | 200 | Sentientia LMS — Real-time Leaderboards | none |
| /badges/mybadges.php | 200 | Badges | none |
| /grade/report/overview/index.php | 200 | Grades - QA Employee | none |

Note: `/local/airpay_skills/index.php?userid=3421` returns 404 with `nopermission` error code. This indicates the skills index page requires a capability the learner role does not have. The learner has no way to browse their skills profile via this direct URL. Flagged as E-02.

---

## 5. Console Errors

One console error class observed (on certificates page):

- `Failed to load resource: the server responded with a status of 404 (Not Found)` — URL not captured by CDP (likely a sub-resource: a missing image or AMD module). Observed 2 times. Not critical — page rendered correctly.

No JS exceptions, no overlay errors, no Moodle error dialogs on any visited page.

---

## 6. Candidate Bugs

### E-01 — P1 — Self-enrol broken for Airpay employee (free courses)
- Catalog "Enroll" → `?action=addtocart` → payment gateway layer → no enrolment persisted
- `enrol/index.php?id=71` silently redirects to dashboard without enrolling
- Employee is left at zero enrolled courses after trying to enrol
- Core learner journey is blocked: cannot access course content, activities, or earn completions/certificates
- Affects all Airpay-tenant employees trying to self-service enrol in "Free" courses
- **Root cause needs investigation**: either self-enrol method is disabled, or paygw_airpay requires checkout confirmation even for £0 price

### E-02 — P2 — `/local/airpay_skills/index.php?userid=3421` returns 404/nopermission for learner
- Learner cannot access their own skills page via direct URL
- May be intentional (skill browsing requires a different URL path) but no sidebar/profile link surfaces it either
- Side effect: leaderboard/gamification may link to skills pages that learners cannot reach

### E-03 — P3 — Catalog list "Enroll" button label uses `addtocart` action even for free courses
- Minor UX inconsistency: catalog list passes through payment layer before showing free enrol
- Course detail page correctly shows "Enroll Now — Free" → `/enrol/index.php`
- Cart is correctly absent from navbar, but the catalog CTA route is confusing
- If E-01 is fixed (self-enrol enabled), this may resolve automatically

### E-04 — P3 — Onboarding shown on every fresh login for this test account
- Expected for first run; may be an issue if onboarding re-triggers after "Skip for now" was already clicked
- Could not confirm without a second login test (out of scope for this run)

---

## 7. Screenshots Saved

| File | Description |
|------|-------------|
| `employee-00-onboarding.png` | First-run onboarding wizard |
| `employee-01-dashboard.png` | Learner dashboard after skip |
| `employee-02-catalog.png` | Course catalog (public.php) |
| `employee-03-course.png` | Course detail page — "Enroll Now — Free" CTA |
| `employee-04-profile.png` | Learner profile (airpay_users/profile.php) |

---

## 8. Summary

| Check | Result |
|-------|--------|
| Login | OK (redirects to onboarding first-run, expected) |
| Sidebar learner-correct? | YES — Dashboard/MyCourses/Catalog/Certificates/Profile only, no admin links |
| Admin-link leak | NONE |
| Dead sidebar links | NONE (all 5 return 200) |
| Catalog lists courses | YES |
| Self-enrol worked | NO — P1 bug (E-01): employee cannot enrol in free courses |
| Course opened | NO — blocked by enrolment failure |
| Activity attemptable | NOT TESTED — blocked by enrolment failure |
| Profile renders | YES |
| Certificates renders | YES (empty state, correct) |
| Cart absent for Airpay employee | YES — correctly absent |
| Console errors | 1 low-severity 404 resource (non-blocking) |
