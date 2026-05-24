# Learner user guide — Sentientia LMS / Airpay Academy

**Audience:** Learner (most users — does courses, takes quizzes, earns badges).
**Status:** v1 draft (2026-05-24).
**Cross-references:** `manager.md`, `course-author.md`.

---

## 1. First login

When you log in for the first time:

1. You'll see a PWA install hint (top of the page) if your browser
   supports it (Chrome, Edge, Samsung Internet on Android; Safari on
   iOS via "Add to Home Screen"). Tap "Install" to put a Sentientia
   Academy app icon on your home screen — same experience as a native app.
2. Pick your language: top-right user menu → Preferences → Language.
   Hindi (`hi`) and English (`en`) are both 100% supported. Toggle
   anytime from any page.
3. Check your profile is correct: top-right photo → "My profile" →
   "Edit profile". Fix anything wrong (this comes from HRMS sync but
   you can correct mistakes).

---

## 2. Catalogue + My Courses

### Browse the catalogue

`/local/airpay_catalog/index.php` — every course visible to your
audience (your tenant, your designation, your cohorts). Filters on
the left: by category, by skill, by language. Search box top-right.

### My Courses

`/my/dashboard.php` (your home) or `/local/airpay_catalog/mycourses.php`
shows JUST the courses you're enrolled in:

- **In progress** — started but not complete
- **Pending** — enrolled but not started
- **Overdue** — past deadline (red banner)
- **Completed** — done; badges + certificate available
- **Sort options** — start date (newest), end date (soonest), alphabetical

---

## 3. Taking a course

### SCORM

Click "Enter" → the SCORM player opens in a modal or full-screen. Use
the SCORM controls (next/back/menu) — Sentientia tracks your progress
automatically. Closing the modal saves your position; you can resume
from where you left off.

### Quiz / Evaluation

Quizzes have time limits (shown top-right). Click "Attempt quiz now",
answer each question, click "Next" to move forward, "Submit all" at
the end. Some quizzes are proctored — you'll see a consent screen + ID
verification flow before the quiz opens.

### Feedback

Feedback forms are usually anonymous. Don't worry — your responses
are tied to a session ID, not your username.

### Resource files

PDFs, videos, slides — click to download or view inline. Mobile-friendly
viewers for most formats.

---

## 4. Badges + certificates

When you complete a course:

- **Badge** — visible on your profile + `/badges/mybadges.php`. Shareable
  to LinkedIn / Twitter.
- **Certificate** — PDF download from the course page. Auto-generated
  with your name, date, course title, and unique verification ID.
  Verifiable at `/admin/tool/certificate/verify.php?code=<id>`.

---

## 5. Skill self-rating

`/local/airpay_skills/self_rate.php`

Your role has a skill catalogue (~10-30 skills). Rate yourself 1-5 on
each skill. Your manager sees this + can endorse or override.

Submit a rating at the END of relevant courses too — the system
auto-prompts ("You just completed Course X — did this improve your
'SQL' skill?").

---

## 6. Calendar + deadlines

`/calendar/view.php?view=month`

Color-coded:
- Course deadlines (red dots)
- Exam dates (orange)
- Live training sessions (blue) — `local_airpay_classroom` instructor-led events
- Personal events you create (grey)

You can subscribe via iCal: `/calendar/export.php` → generate URL → paste
into Outlook/Google Calendar.

---

## 7. Messaging

`/message/index.php` — Moodle's built-in messaging. You can:

- Message your manager
- Message Course Authors / instructors
- Message learners in shared cohorts (if your tenant allows it)

NOT a general chat — keep it learning-related. Tenant Admin can
disable per-tenant.

---

## 8. Notifications preferences

`/user/preferences/notification_preferences.php`

For each notification type (course deadline, certificate issued,
new course assigned, message received), pick a channel:

- Email (default)
- In-app notification (always on)
- WhatsApp (opt-in; turn ON in this page)
- Push notification (PWA install required first)

Hindi notifications: select your language to `hi` and all notification
text uses the Hindi pack automatically.

---

## 9. Mobile experience (590px)

Sentientia LMS is fully responsive. Tested breakpoints: 1400, 1200,
992, 768, **590 (primary mobile)**, 480, 380.

At 590px:

- Top nav collapses to a hamburger menu
- Dashboard tiles stack vertically (1 column)
- Tables get horizontal scroll inside their card
- SCORM player switches to full-screen mode
- Forms expand to 100% width

If something looks broken at your screen size, screenshot + send to
your Tenant Admin — Sentientia treats mobile bugs as P1.

---

## 10. Hindi UI toggle

Top-right user menu → Preferences → Language → "हिन्दी (hi)".

Saves immediately. All Sentientia UI re-renders in Hindi on next page
load. Course content that has Hindi versions (via the `{mlang}` tag)
also flips. Activities (SCORM, quizzes) that have Hindi tracks
auto-route to the Hindi track.

---

## 11. Help + escalation

| You need... | Go to... |
|-------------|----------|
| Password reset | Login page → "Forgotten your password?" |
| Profile fix (wrong department, etc.) | Edit profile yourself OR ask your Tenant Admin |
| Course access | Use "Request access" button on the catalogue (goes to your manager) |
| Tech problem (page broken, can't load video) | Tenant Admin → escalates to Site Admin if needed |
| Compliance training I'm overdue on | Just open it and start; the cron reminder emails always include the direct link |

---

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold |
