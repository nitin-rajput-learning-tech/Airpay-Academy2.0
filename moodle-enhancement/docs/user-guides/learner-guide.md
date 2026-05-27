# Learner User Guide

**Persona:** Learner — both the internal Airpay employee AND the external Public-tenant learner
**Platform:** Sentientia LMS / Airpay Academy — Theme `airpayux` v1.0.37-beta
**Audience:** Every end-user who takes courses, quizzes, and earns certificates (the largest population)
**Status:** v1.0 (2026-05-25) — supersedes the v1-draft skeleton at `learner.md`
**Test accounts referenced:**
- **Airpay employee (tenant id=1):** `jitendra.mane@airpay.co.in` — HRMS-synced internal learner
- **Public learner (tenant id=77):** `academyexadmin@airpay.co.in` (External Admin account, also exercises the Public learner surface) — and `vimal.koothattu` as a fresh-account reference
- Local password (XAMPP only): `AcademyAudit2026!`
- Local URL: `http://localhost:8080/moodle/`

> **Two tenants, one guide.** Where the experience differs between Airpay
> (internal employee) and Public (external learner), the difference is called
> out in a 🟦 **Airpay** / 🟩 **Public** callout. Everything else is shared.

> **Sibling guides:** [`tenant-admin-guide.md`](tenant-admin-guide.md) · [`course-author-guide.md`](course-author-guide.md) · [`compliance-officer-guide.md`](compliance-officer-guide.md) · [`public-learner.md`](public-learner.md)

---

## Table of contents

1. [First login (both tenants)](#1-first-login)
2. [The learner dashboard tour](#2-dashboard)
3. [Navbar walkthrough](#3-navbar)
4. [Sidebar walkthrough (Airpay vs Public)](#4-sidebar)
5. [Browsing the catalogue](#5-catalogue)
6. [My Courses](#6-my-courses)
7. [Taking a course — SCORM](#7-scorm)
8. [Taking a course — Quiz](#8-quiz)
9. [Taking a course — Evaluation + Feedback](#9-evaluation)
10. [Proctored exams](#10-exams)
11. [Sentientia Live — joining as audience](#11-live)
12. [Requesting access (manager approval flow)](#12-request)
13. [Paid course purchase (Public tenant)](#13-purchase)
14. [Badges + certificates](#14-badges)
15. [Skill self-rating](#15-skills)
16. [Calendar + ICS subscription](#16-calendar)
17. [Leaderboards + gamification](#17-leaderboards)
18. [Messaging + notifications preferences](#18-notifications)
19. [PWA install + push notifications](#19-pwa)
20. [WhatsApp opt-in](#20-whatsapp)
21. [Profile + preferences](#21-profile)
22. [Hindi UI toggle](#22-hindi)
23. [Mobile (590px) walkthrough](#23-mobile)
24. [What's new in v1.0.37-beta — affecting Learners](#24-whats-new)
25. [Troubleshooting common issues](#25-troubleshooting)
26. [Help + escalation](#26-help)
27. [Screenshot capture sequence](#27-screenshot-sequence)
28. [References](#28-references)

---

## 1. First login (both tenants) <a id="1-first-login"></a>

### 🟦 Airpay employee (jitendra.mane@airpay.co.in)

Your account is created automatically by the nightly HRMS sync. You receive a
welcome email with your username + a login link. On first login:

1. Browse to `http://localhost:8080/moodle/login/index.php`.
2. Enter `jitendra.mane@airpay.co.in` / `AcademyAudit2026!`.
3. Click **Sign in**.

📸 **Screenshot 01:** `screenshots/learner/01-login-airpay.png`

### 🟩 Public learner (self-registration)

External learners self-register:

1. Browse to `/login/signup.php`.
2. Provide email, name, a password (12+ chars, mixed case + digit + symbol).
3. Accept the Privacy Policy + Terms & Conditions.
4. Confirm via the email link, then sign in at `/login/index.php`.

The `academyexadmin@airpay.co.in` account is pre-created for capture; a fresh
Public learner (e.g. `vimal.koothattu`) sees an **onboarding modal** on first
login ("Welcome, vimal!" + "Skip for now").

📸 **Screenshot 02:** `screenshots/learner/02-login-public.png`
📸 **Screenshot 03:** `screenshots/learner/03-onboarding-modal.png` — fresh
Public learner onboarding modal.

### First-login housekeeping (both)

1. **PWA install hint** — a top-of-page banner offers to install the app
   (Chrome / Edge / Samsung Internet on Android; iOS via "Add to Home Screen").
   See §19.
2. **Language** — top-right user menu → Preferences → Language. Hindi + English
   both 100% supported. See §22.
3. **Profile** — top-right photo → "My profile" → "Edit profile". Fix anything
   wrong (Airpay profiles come from HRMS but you can correct mistakes).

---

## 2. The learner dashboard tour <a id="2-dashboard"></a>

After login you land on `/my/dashboard.php`. The learner-shape dashboard:

```
+--------------------------------------------------------------+
| Welcome back, <firstname>!                                   |
| Continue where you left off and keep building your skills    |
+--------------------------------------------------------------+
| [ Continue Learning ]   ← in-progress course cards w/ progress |
+--------------------------------------------------------------+
| [ Deadlines ]  [ Upcoming ]   ← due-soon + scheduled events  |
+--------------------------------------------------------------+
| [ My Skills radar ]  [ Achievements / badges strip ]         |
+--------------------------------------------------------------+
| [ Popular courses ]   ← catalogue highlights                 |
+--------------------------------------------------------------+
```

📸 **Screenshot 04:** `screenshots/learner/04-dashboard-airpay.png` — jitendra's
dashboard with Continue Learning populated.
📸 **Screenshot 05:** `screenshots/learner/05-dashboard-public.png` —
academyexadmin Public dashboard.

### Empty state (fresh account)

A brand-new learner with zero enrolments sees a well-designed empty state:
"No courses in progress" headline + a "Browse Catalogue" CTA. (Confirmed in the
audit for vimal.koothattu's fresh Public account.)

📸 **Screenshot 06:** `screenshots/learner/06-dashboard-empty.png`

---

## 3. Navbar walkthrough <a id="3-navbar"></a>

```
+-----------------------------------------------------------------+
| [Logo]   [Dashboard] [My Courses] [Catalog] [Profile]          |
|                              [search] [🛒]* [🌗] [photo ▾]      |
+-----------------------------------------------------------------+
* 🛒 cart: Public tenant only
```

| Slot              | Routes to                                  | Notes                                          |
|-------------------|--------------------------------------------|------------------------------------------------|
| Logo              | `/my/dashboard.php`                        | Per-tenant logo.                               |
| Dashboard         | `/my/dashboard.php`                        | i18n since chip-B.                             |
| My Courses        | `/local/airpay_catalog/mycourses.php`      | i18n since chip-B.                             |
| Catalog           | `/local/airpay_catalog/index.php`          | i18n since chip-B.                             |
| Profile           | `/user/profile.php?id=<self>`              | i18n since chip-B.                             |
| Search            | site-wide search                           | Placeholder i18n since chip-B.                 |
| Cart 🛒           | `/local/airpay_cart/cart.php`              | 🟩 Public only — hidden for 🟦 Airpay.          |
| Dark toggle 🌗    | flips theme                                | Persists in localStorage.                      |
| User photo ▾      | Profile / Preferences / Language / Logout  | Shows your name + role.                        |

📸 **Screenshot 07:** `screenshots/learner/07-navbar.png`
📸 **Screenshot 08:** `screenshots/learner/08-user-dropdown.png`

---

## 4. Sidebar walkthrough (Airpay vs Public) <a id="4-sidebar"></a>

The learner sidebar is shorter than the admin sidebar. The `role_detector`
returns `tier=learner`.

### 🟦 Airpay employee sidebar (~6 items)

| # | Item            | URL                                         |
|---|-----------------|---------------------------------------------|
| 1 | Dashboard       | `/my/dashboard.php`                         |
| 2 | My Courses      | `/local/airpay_catalog/mycourses.php`       |
| 3 | Catalog         | `/local/airpay_catalog/index.php`           |
| 4 | My Skills       | `/local/airpay_skills/self_rate.php`        |
| 5 | Calendar        | `/calendar/view.php?view=month`             |
| 6 | Badges          | `/badges/mybadges.php`                      |

### 🟩 Public learner sidebar (~6 items, swaps in My Cart)

| # | Item            | URL                                         |
|---|-----------------|---------------------------------------------|
| 1 | Dashboard       | `/my/dashboard.php`                         |
| 2 | My Courses      | `/local/airpay_catalog/mycourses.php`       |
| 3 | Catalog         | `/local/airpay_catalog/index.php`           |
| 4 | **My Cart**     | `/local/airpay_cart/cart.php`               |
| 5 | Calendar        | `/calendar/view.php?view=month`             |
| 6 | Badges          | `/badges/mybadges.php`                      |

Public learners do not get "My Skills" (no manager to validate ratings) but DO
get "My Cart" (paid-course e-commerce).

📸 **Screenshot 09:** `screenshots/learner/09-sidebar-airpay.png`
📸 **Screenshot 10:** `screenshots/learner/10-sidebar-public.png`

---

## 5. Browsing the catalogue <a id="5-catalogue"></a>

`/local/airpay_catalog/index.php`

Every course visible to your audience (your tenant, your designation, your
cohorts). Grid of cards: thumbnail, title, category, short description, a CTA
("Enrol" / "Request access" / "Buy").

Filters (left rail): category, skill, language (en / hi), and — 🟩 Public only —
price (free / paid).

🟦 Airpay: you see internal compliance + skill courses. 🟩 Public: you see only
courses the Course Author marked "Public" (foundational, webinars, paid certs).

📸 **Screenshot 11:** `screenshots/learner/11-catalogue.png`
📸 **Screenshot 12:** `screenshots/learner/12-catalogue-filtered.png` — with a
category + language filter applied.

---

## 6. My Courses <a id="6-my-courses"></a>

`/local/airpay_catalog/mycourses.php` (or the dashboard's Continue Learning band)

Shows only the courses you are enrolled in:
- **In progress** — started, not complete
- **Pending** — enrolled, not started
- **Overdue** — past deadline (red banner)
- **Completed** — done; badge + certificate available

Sort options: start date (newest), end date (soonest), alphabetical. (Audit
Bug #4 fixed the filter; "sort by start date" was noted as a follow-up.)

📸 **Screenshot 13:** `screenshots/learner/13-my-courses.png`

---

## 7. Taking a course — SCORM <a id="7-scorm"></a>

Click "Enter" on a SCORM activity → the SCORM player opens (new window
recommended for SCORM 1.2 compatibility). Use the SCORM controls (next / back /
menu). Sentientia tracks your progress automatically; closing saves your
position so you can resume.

```
+----------------------------------------------+
|  [Menu]                          [X close]   |
|  +---------------------------------------+   |
|  |  Slide content + narration            |   |
|  |  (audio plays; captions if available) |   |
|  +---------------------------------------+   |
|  [◀ Back]   progress: ▓▓▓▓░░░░  [Next ▶]   |
+----------------------------------------------+
```

📸 **Screenshot 14:** `screenshots/learner/14-scorm-player.png`

Completion: the activity ticks complete when you reach the SCORM masteryscore
(Airpay default 70) or finish all SCOs, depending on how the Course Author
configured it.

---

## 8. Taking a course — Quiz <a id="8-quiz"></a>

Quizzes may have time limits (shown top-right). Flow:
1. Click "Attempt quiz now".
2. Answer each question; "Next" to advance.
3. "Submit all and finish" at the end → confirmation modal → confirm.
4. See your grade + per-question feedback (if the Course Author enabled it).

📸 **Screenshot 15:** `screenshots/learner/15-quiz-attempt.png`
📸 **Screenshot 16:** `screenshots/learner/16-quiz-results.png`

Some quizzes are proctored — see §10.

---

## 9. Taking a course — Evaluation + Feedback <a id="9-evaluation"></a>

**Evaluation** (`local_airpay_evaluation`) — structured assessments with
conditional questions. Answer in order; some questions reveal follow-ups based
on your answer. Submit when complete.

**Feedback** (`mod_feedback`) — usually anonymous post-course surveys. Your
responses are tied to a session ID, not your username, so be candid.

📸 **Screenshot 17:** `screenshots/learner/17-evaluation.png`

---

## 10. Proctored exams <a id="10-exams"></a>

Exams (`local_airpay_exams` + `quizaccess_airpay_proctoring`) are time-boxed
and often proctored. Before the timer starts you see:
1. A consent screen (webcam + face-detection consent)
2. An ID-verification step
3. The exam opens once consent + verification pass

During the exam your webcam may be sampled for proctoring. Do not switch tabs /
windows — the proctoring rule may flag it.

📸 **Screenshot 18:** `screenshots/learner/18-exam-consent.png`

---

## 11. Sentientia Live — joining as audience <a id="11-live"></a>

When a trainer runs a live session (Mentimeter-style), you join as audience:

1. Open `/local/sentientia_live/audience/join.php` (or scan the QR the trainer
   shows).
2. Enter the session code.
3. Watch slides as the trainer advances them; respond when prompted (poll,
   quiz, rating, wordcloud, open-ended, ranking).
4. See live results render in real time (bar chart / histogram / wordcloud).

📸 **Screenshot 19:** `screenshots/learner/19-live-join.png`
📸 **Screenshot 20:** `screenshots/learner/20-live-vote.png`

Accessibility: the live results announce updates to screen readers via
`aria-live` regions (chip-E + NVDA-verified in chip-P2-H).

---

## 12. Requesting access (manager approval flow) <a id="12-request"></a>

🟦 Airpay primarily. If a course is outside your default audience, the catalogue
card shows "Request access" instead of "Enrol". Clicking it:
1. Opens a request form (`local_airpay_request`).
2. You write a reason (minimum 20 characters — enforced).
3. Submit → your line manager gets the approval request (email + WhatsApp if
   opted-in).
4. Manager approves → you are enrolled; rejects → you see their reason.

SLA: 48h target; manager gets nudges at 24h and 47h.

📸 **Screenshot 21:** `screenshots/learner/21-request-access.png`

---

## 13. Paid course purchase (Public tenant) <a id="13-purchase"></a>

🟩 **Public only.** Some Public courses are paid. Flow:

1. Click "Enrol" / "Buy" on the catalogue card.
2. Redirect to `/local/airpay_cart/cart.php` — review your order.
3. "Proceed to checkout".
4. Pick payment method — Airpay payment gateway (`paygw_airpay`) supports UPI,
   card, net banking, wallet. Currencies: INR default, USD, EUR + ~22 others.
5. Complete payment on the Airpay-hosted page.
6. On success, you are redirected back; access is immediate.

```
Catalogue → [Buy] → Cart → Checkout → Airpay gateway → Success → Enrolled
```

📸 **Screenshot 22:** `screenshots/learner/22-cart.png`
📸 **Screenshot 23:** `screenshots/learner/23-checkout.png`

### Receipts + failed payments

- **Receipt:** invoice at `/local/airpay_cart/history.php`, with GST line items.
- **Failed payment:** you return to the cart with the error + cart preserved;
  retry with a different method.

> **Security note (paygw_airpay):** the gateway uses SHA-256 checksums
> (`calculateChecksumSha256`) with constant-time comparison; the legacy MD5 path
> was deprecated in the 2026-05-24 security follow-up. You will never see this —
> it is server-side — but it is why your payment is integrity-checked.

---

## 14. Badges + certificates <a id="14-badges"></a>

On course completion:

- **Badge** — appears on `/badges/mybadges.php` + your profile. Shareable to
  LinkedIn / Twitter.
- **Certificate** — PDF download from the course page or the badge. Auto-generated
  with your name, date, course title, and a unique verification ID.

### Verification

Anyone can verify a certificate at:

```
/admin/tool/certificate/verify.php?code=<id>
```

Add it to LinkedIn under "Licenses & certifications" with the verification URL.

📸 **Screenshot 24:** `screenshots/learner/24-mybadges.png`
📸 **Screenshot 25:** `screenshots/learner/25-certificate.png`

> Use your accurate legal name when signing up (🟩 Public) — that is what
> prints on the certificate.

---

## 15. Skill self-rating <a id="15-skills"></a>

🟦 **Airpay primarily** (Public learners have no manager to validate).

`/local/airpay_skills/self_rate.php` — your role has a skill catalogue (~10-30
skills). Rate yourself 1-5 per skill. Your manager sees this + can endorse or
override.

The system also auto-prompts at the end of relevant courses ("You just completed
Course X — did this improve your 'SQL' skill?").

📸 **Screenshot 26:** `screenshots/learner/26-skill-self-rate.png`

---

## 16. Calendar + ICS subscription <a id="16-calendar"></a>

`/calendar/view.php?view=month` — colour-coded:
- Course deadlines (red dots)
- Exam dates (orange)
- Live training sessions (blue)
- Personal events you create (grey)

### ICS subscription (Sentientia Calendar)

`/local/sentientia_calendar/index.php` (when `sentientia.calendar_sync.enabled`
is ON) gives you a personal feed URL to paste into Outlook / Google / Apple
Calendar. Your course deadlines, classroom sessions, and exam close-dates appear
automatically.

If you regenerate the token, the old URL stops working — re-paste the new one
into your calendar app.

📸 **Screenshot 27:** `screenshots/learner/27-calendar.png`
📸 **Screenshot 28:** `screenshots/learner/28-calendar-ics.png`

---

## 17. Leaderboards + gamification <a id="17-leaderboards"></a>

`block_sentientia_leaderboard` may appear on your dashboard (if the trainer /
admin enabled it for your cohort). Board types:
- **Quiz** — ranked by score + speed
- **Completion** — ranked by courses completed
- **Skill** — ranked by validated skill level

Live ranking via SSE. You can opt out (GDPR-compliant, ADR-014) from your
preferences — opting out removes you from the visible board but keeps your own
private stats.

📸 **Screenshot 29:** `screenshots/learner/29-leaderboard.png`

---

## 18. Messaging + notifications preferences <a id="18-notifications"></a>

### Messaging

`/message/index.php` — Moodle's built-in messaging. You can message your manager,
course instructors, and learners in shared cohorts (if your tenant allows). Not a
general chat; keep it learning-related.

### Notification preferences

`/user/preferences/notification_preferences.php` — per notification type
(deadline, certificate issued, new course assigned, message received), pick a
channel:
- Email (default)
- In-app (always on)
- WhatsApp (opt-in — see §20)
- Push (PWA install required — see §19)

📸 **Screenshot 30:** `screenshots/learner/30-notification-prefs.png`

---

## 19. PWA install + push notifications <a id="19-pwa"></a>

Sentientia LMS is an installable Progressive Web App (`local_sentientia_pwa`).

### Install

- **Android (Chrome / Edge / Samsung Internet):** tap the install hint banner,
  or browser menu → "Install app" / "Add to Home Screen".
- **iOS (Safari):** the OS does not fire the install event; a dismissible "Add
  to Home Screen" hint banner guides you (Share → Add to Home Screen).
- **Desktop (Chrome / Edge):** an install icon appears in the address bar.

Once installed, Sentientia Academy gets a home-screen icon and launches
full-screen like a native app, with offline-shell support.

📸 **Screenshot 31:** `screenshots/learner/31-pwa-install-banner.png`
📸 **Screenshot 32:** `screenshots/learner/32-pwa-installed.png`

### Push notifications

Enable at `/local/sentientia_pwa/preferences.php` → "Enable browser
notifications" → grant permission. You then receive push for deadlines,
completions, certificates (gated by `sentientia.pwa.push.*` flags — Site Admin
turns these ON post-rollout).

📸 **Screenshot 33:** `screenshots/learner/33-push-permission.png`

---

## 20. WhatsApp opt-in <a id="20-whatsapp"></a>

🟦 **Airpay primarily** (India-phone enforcement; 🟩 global Public learners are
not pushed WhatsApp).

Opt in at `/user/preferences.php` → toggle "WhatsApp notifications" ON. Default
is OFF (DPDP Act 2023 + Meta Business API ToS require explicit opt-in — no one
can force-enable you).

Once on, you get deadline / completion / certificate notifications on WhatsApp —
~90% open rate in India, the highest-engagement channel.

📸 **Screenshot 34:** `screenshots/learner/34-whatsapp-optin.png`

---

## 21. Profile + preferences <a id="21-profile"></a>

`/user/profile.php?id=<self>` → "Edit profile".

🟦 Airpay: most fields are HRMS-synced (name, designation, department, manager).
You can correct mistakes; the next sync may overwrite some fields, so report
persistent errors to your Tenant Admin.

🟩 Public: you own all your profile fields.

> **Audit note:** `/user/profile.php` is one of the six "Moodle leak" surfaces
> (vanilla 2-column key-value layout). It works correctly; it just looks more
> Moodle-stock than the rest. Goal A.x restyled it partially (chip-J split moved
> the profile SCSS into `_surface-user.scss`); full redesign is Phase 2.

📸 **Screenshot 35:** `screenshots/learner/35-profile.png`
📸 **Screenshot 36:** `screenshots/learner/36-edit-profile.png`

---

## 22. Hindi UI toggle <a id="22-hindi"></a>

Top-right user menu → Preferences → Language → "हिन्दी (hi)" → saves immediately.

All Sentientia UI re-renders in Hindi on next page load. Course content with
Hindi versions (via `{mlang}`) flips too; SCORM / quizzes with Hindi tracks
auto-route.

Post-chip-#255, the theme locale parity is 100% across en / hi / kn / mr / sw
(178/178 keys each).

📸 **Screenshot 37:** `screenshots/learner/37-hindi-dashboard.png`

---

## 23. Mobile (590px) walkthrough <a id="23-mobile"></a>

**Primary mobile breakpoint:** 590px. Tested: 1400 / 1200 / 992 / 768 / **590** /
480 / 380.

Replicate: Chrome DevTools → Toggle Device Toolbar (Ctrl+Shift+M) → width 590.

### 23.1 Navbar collapses to hamburger + bottom-nav

The top nav collapses to logo + hamburger + dark-toggle + photo. A **mobile
bottom-nav band** appears with the 4 primary pills (Home / Catalog / My Courses /
Profile), each with `aria-current="page"` for accessibility.

📸 **Screenshot 38:** `screenshots/learner/38-mobile-navbar.png`
📸 **Screenshot 39:** `screenshots/learner/39-mobile-bottom-nav.png`

### 23.2 Dashboard stacks to 1 column

KPI / Continue-Learning cards stack vertically. Skill radar compresses. Tables
get horizontal scroll inside their card.

📸 **Screenshot 40:** `screenshots/learner/40-mobile-dashboard.png`

### 23.3 Catalogue → single-column cards

Catalogue cards reflow to one column; filters collapse into a "Filters" drawer
trigger.

📸 **Screenshot 41:** `screenshots/learner/41-mobile-catalogue.png`

### 23.4 SCORM player full-screen

The SCORM player switches to full-screen mode on mobile (controls anchor to the
bottom edge).

📸 **Screenshot 42:** `screenshots/learner/42-mobile-scorm.png`

### 23.5 Quiz + forms expand to 100% width

Forms expand to full width; the quiz nav block becomes a bottom sheet.

📸 **Screenshot 43:** `screenshots/learner/43-mobile-quiz.png`

### 23.6 Cart + checkout (Public)

🟩 Cart reflows to a single column; the checkout CTA is a sticky bottom button.

📸 **Screenshot 44:** `screenshots/learner/44-mobile-cart.png`

### 23.7 What changed for mobile this release

- Chip-L — footer mobile breakpoint added (no more overflow on Galaxy S <400px).
- Chip-D / #19 — all transition timings token-driven; collapse to 0ms under
  `prefers-reduced-motion: reduce` (WCAG 2.3.3).
- Chip-#264 — `drawer.mustache` 5.2 backport with 5.1 guards.

> If something looks broken at your screen size, screenshot it + send to your
> Tenant Admin — Sentientia treats mobile bugs as P1.

---

## 24. What's new in v1.0.37-beta — affecting Learners <a id="24-whats-new"></a>

The Day-0 chip wave (21 merges, 2026-05-24):

| # | Chip | Surface affected | What you'll notice |
|---|------|------------------|--------------------|
| 1 | A — Orphan `Claude` SCSS removed | Theme build | Pages paint faster (98 KB less CSS). |
| 2 | B — `MONOLITH_BACKUP.scss` archived | Theme build | Smaller download. |
| 3 | B — Navbar i18n (8 strings) | Navbar | Nav pills (Dashboard / My Courses / Catalog / Profile) render in your language. |
| 4 | B — Footer i18n (4 strings) | Footer | Footer links (Privacy / Terms / Help / Contact) localised. |
| 5 | C — Dashboard inline-style cleanup | Dashboard | Dark mode now correct on every dashboard tile. |
| 6 | C — Footer attribution styled via SCSS | Footer | Footer readable in dark mode (no white-on-white). |
| 7 | #255 — Locale parity 178/178 (en/hi/kn/mr/sw) | Every UI string | Full Hindi / Kannada / Marathi / Swahili translation of all theme chrome. |
| 8 | E — Sentientia Live `aria-live` regions + sr-only tally | Live engagement | If you use a screen reader, live poll results are now announced. |
| 9 | F — Navbar cart-badge → AMD module | Navbar cart | 🟩 Cart badge works on strict-CSP networks. |
| 10 | J — `_surface-profile.scss` split into 4 partials | Profile / Badges / Grades / Calendar | Same look; faster load. |
| 11 | K — `_surface-login.scss` cleanup | Login | Smoother login styling. |
| 12 | P1 #12 + H — `:focus-visible` siblings | Every button / link | Keyboard focus ring shows correctly; mouse-click no longer flashes a phantom ring. |
| 13 | I — `dark_mode.scss` token cascade | Every dark surface | Dark mode is more consistent across pages. |
| 14 | L — Footer mobile breakpoint | Footer on mobile | Footer no longer overflows on small phones. |
| 15 | M — Sentientia Live BEM tokens | Live engagement | Live buttons / badges match the brand in light + dark. |
| 16 | G — Dashboard 11 i18n strings | Dashboard | KPI labels + chart titles translate. |
| 17 | N — Chart.js vendored + `{{#js}}` init | Dashboard charts | Charts work offline + on locked-down networks. |
| 18 | #18 — `_moodle-overrides.scss` `!important` trim | Site chrome | More consistent styling. |
| 19 | #19 + D — reduced-motion tokens | Animations | If you set "reduce motion" in your OS, animations now respect it. |
| 20 | Q — coursebannerimage CSS-url safety | Course banners | No visible change; banners are injection-safe. |
| 21 | O / #21 — Footer comment trim | Footer | No visible change. |

### Bonus highlighted for Learners

- **P3-O — Leaderboard rank-change notifications** — get notified (24h throttle)
  when you climb ±5 positions or break into the top-10 (flag default OFF; opt-in).
- **P3-N — Calendar Sync OAuth scaffold** — future: bi-directional Outlook /
  Google sync (flag OFF today; outbound ICS works now — see §16).
- **P2-L — Playwright Linux E2E gate** — your login / dashboard / navbar /
  dark-mode / mobile-590 flows are now smoke-tested on every push.

📸 **Screenshot 45:** `screenshots/learner/45-whats-new-diff.png`

---

## 25. Troubleshooting common issues <a id="25-troubleshooting"></a>

### "I forgot my password"

Login page → "Forgotten your password?" → enter email → follow the reset link.
🟦 Airpay: if you cannot access email, contact your Tenant Admin → Site Admin
runs the CLI reset.

### "A course won't open / video won't play"

| Cause                                   | Resolution                                                            |
|-----------------------------------------|-----------------------------------------------------------------------|
| SCORM in iframe blocked                 | Try "Open in new window" (Course Author setting).                     |
| Browser cache stale                     | Hard refresh (Ctrl+Shift+R).                                          |
| Pop-up blocker                          | Allow pop-ups for the site (SCORM new-window).                        |
| Restricted-access prerequisite          | Complete the prerequisite activity first.                            |
| Persistent broken page                  | Screenshot + report to Tenant Admin (P1 if many learners affected).  |

### "My progress didn't save"

SCORM saves on close. If you force-closed the tab mid-slide, reopen — it should
resume from your last committed SCO. If it resets, report the SCORM package to
the Course Author (likely a commit-on-unload bug in the content pack).

### "I completed the course but it shows in-progress"

Activity completion is async (processed by cron). Wait a few minutes. If still
stuck, the Course Author's completion condition may not match what you did —
ask them to check the activity's completion settings.

### "I'm not getting deadline reminders"

| Step                                                                       |
|----------------------------------------------------------------------------|
| Check `/user/preferences/notification_preferences.php` channels are on.    |
| 🟦 WhatsApp: opt in at `/user/preferences.php` (default OFF).               |
| Push: install the PWA + enable notifications (§19).                        |
| Confirm your manager + course deadline are set (Course Author owns these). |

### "Push notifications stopped working"

Likely you cleared your browser data or uninstalled the PWA. Re-subscribe at
`/local/sentientia_pwa/preferences.php`.

### "The cart is missing" (Public)

🟩 The cart only appears on the Public tenant for paid courses. 🟦 Airpay
employees do not see a cart (internal training is free) — this is expected, not
a bug.

### "Mobile layout looks broken"

Confirm you are at a tested breakpoint. If a real bug, screenshot + width + the
URL → send to Tenant Admin. Mobile bugs are P1.

### "Certificate name is wrong"

🟩 Public: the cert prints the name on your profile. Edit your profile, then ask
the Course Author to re-issue. 🟦 Airpay: report to Tenant Admin (HRMS-sourced).

---

## 26. Help + escalation <a id="26-help"></a>

| You need...                              | Go to...                                                          |
|------------------------------------------|-------------------------------------------------------------------|
| Password reset                           | Login → "Forgotten your password?"                                |
| Profile fix                              | Edit profile yourself; 🟦 persistent errors → Tenant Admin.        |
| Course access (outside your audience)    | "Request access" button on the catalogue → your manager.          |
| Tech problem (broken page, no video)     | Tenant Admin → escalates to Site Admin if needed.                 |
| Compliance training overdue              | Open it + start; reminder emails include a direct link.           |
| 🟩 Refund (within 7 days)                | `/local/airpay_cart/refund_request.php`.                          |
| 🟩 Account deletion                      | `/admin/tool/dataprivacy/myrequests.php`.                         |
| Certificate verification                 | `/admin/tool/certificate/verify.php?code=<id>` (anyone can use).  |

---

## 27. Screenshot capture sequence <a id="27-screenshot-sequence"></a>

```powershell
# 1. XAMPP up + caches purged
Set-Location C:\xampp\htdocs\moodle5\public
php ..\admin\cli\purge_caches.php

# 2. Open Chrome at canonical capture viewport
"C:\Program Files\Google\Chrome\Application\chrome.exe" `
    --user-data-dir="C:\tmp\chrome-airpay-capture-learner" `
    --window-size=1440,900 `
    http://localhost:8080/moodle/login/index.php

# 3a. AIRPAY pass — sign in as jitendra.mane@airpay.co.in / AcademyAudit2026!
#     Walk §2 → §22 capturing the 🟦 Airpay shots.
#     Save to moodle-enhancement/docs/user-guides/screenshots/learner/NN-*-airpay.png

# 3b. PUBLIC pass — sign out, sign in as academyexadmin@airpay.co.in
#     Walk catalogue → cart → checkout → badges capturing the 🟩 Public shots.
#     For the onboarding modal (Screenshot 03), use a fresh account (vimal.koothattu).
#     Save to .../screenshots/learner/NN-*-public.png

# 4. MOBILE pass (§23) — Ctrl+Shift+M → 590px → re-walk the key surfaces.
#    Save as 38-mobile-* … 44-mobile-*.png

# 5. DARK-MODE — click the moon icon top-right, recapture dashboard + a course.

# 6. HINDI — user menu → Preferences → Language → हिन्दी, recapture dashboard
#    (Screenshot 37).

# 7. Commit + push:
git add docs/user-guides/screenshots/learner/
git commit -m "docs(user-guides): learner screenshots capture (both tenants + mobile)"
git push -u origin claude/friendly-gates-10iUM
```

Total captures: ~45 across desktop (both tenants) + mobile + dark + Hindi.

---

## 28. References <a id="28-references"></a>

- [`public-learner.md`](public-learner.md) — deeper Public-tenant detail (signup, refund, deletion)
- [`tenant-admin-guide.md`](tenant-admin-guide.md) — who manages your tenant
- [`course-author-guide.md`](course-author-guide.md) — who builds your courses
- [`compliance-officer-guide.md`](compliance-officer-guide.md) — who tracks your mandatory training
- [`manager.md`](manager.md) — what your line manager sees
- [`README.md`](README.md) — guide index + chooser flowchart
- [`learner.md`](learner.md) — v1-draft scaffold (superseded by depth here)
- `state-cards/local_sentientia_pwa-state.md` — PWA + push
- `state-cards/local_sentientia_calendar-state.md` — ICS feed
- `state-cards/local_airpay_cart-state.md` — paid-course flow
- `docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md` — leaderboard opt-out
- `CLAUDE.md` (root) — operating rules

---

| Version | Date       | Author                       | Notes                                                       |
|---------|------------|------------------------------|-------------------------------------------------------------|
| v1.0    | 2026-05-25 | Wave D3 P3 testing-and-docs chip | Full ≥20-page guide; covers Airpay + Public tenants + mobile |
