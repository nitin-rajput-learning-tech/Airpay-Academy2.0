# Research 1: feature inventory of Academy 2.0 (Sentientia LMS), as built

**Purpose.** The source for the C-suite deck Nitin asked for: what Academy 2.0, built on our own Sentientia LMS,
adds over the current live airpay.academy, the full feature list, and the benefit of each.
**Prepared:** 17 November 2026, from the repository record only (paths below are relative to
`D:/Claude Local/airpay-ld-os/`). Nothing here comes from memory or from outside the repository.

**Read this before using it on a slide.**
- Academy 2.0 is **not live**. No real learner has used it. Everything on the test server and the development copy is
  test data. Describe what is **built**, never "in use", "serving" or "results". (Source: `moodle-enhancement/docs/business/DEMO-SCRIPT-2026-11-19.md` section 4.)
- No live AI call has ever been made. Every AI feature runs in sample (mock) mode. (Same source, caveat 3.)
- The "Source" and "Switch" notes are for checking only. They name files, switch keys and plugins; none of that goes
  on a slide (Nitin's rule: no plugin names, flag names, versions, ADR numbers, test counts, dates of engineering
  events, budgets, IT, UAT, cutover).
- Several older state cards still say "Live on airpay.academy" (written May 2026, before the record was corrected).
  That wording is wrong; do not reuse it. Cards affected include classroom, exams, programs, recompletion, reports,
  ratings, notifications, manager, catalog, cart, proctoring, pages, integrations, assistant, skills, theme,
  `paygw_airpay`, `quizaccess_airpay_proctoring`, `block_sentientia_trainer`. The master product guide (June 2026)
  also describes the old platform's 3,500+ users as "live". Correct statement: airpay.academy still runs the old
  BizLMS platform for its 3,500+ learners; Academy 2.0 replaces it later.
- Short paths of the form `local/sentientia_*/...` are under `moodle-enhancement/` (the top-level `local/` tree is
  its byte-identical twin). "MG" = `moodle-enhancement/docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md`; "Product
  guide" = `moodle-enhancement/docs/SENTIENTIA-PRODUCT-GUIDE.md`; "provision script" =
  `tools/uat/provision_test_users.php`; the decks are in `moodle-enhancement/docs/business/`.

---

## 0. How to read the status and "vs live" labels

**Status** (exact, from the record):

| Label | Meaning |
|---|---|
| **Built, on** | Part of the build and on by default (no switch, or a switch whose default is ON). |
| **Built, off** | Fully built, behind a switch that is OFF by default. Can be shown as a built capability; do not claim it is in use. |
| **Built, off (on in test env)** | Same, but the record shows the switch turned ON on the test server for the demo (`tools/uat/provision_test_users.php` lines 157-167, or a dated PROJECT-STATE entry). |
| **Groundwork** | Foundations built (screens, storage, security), but the core function is not built or deliberately inert. |
| **Planned** | Designed or proposed; not built. |
| **UNCONFIRMED** | The record does not settle it. |

**vs live** (compared with the current live airpay.academy, BizLMS on Moodle 4.1.2):

| Label | Meaning |
|---|---|
| **NEW** | Not on the live platform. |
| **REBUILT+** | Live has a basic version (a BizLMS plugin, named); Academy 2.0 rebuilt it and added to it. |
| **SAME BASE+** | The underlying Moodle or third-party feature exists on live; Academy 2.0 adds around it. |

---

## 1. The baseline: what the current live airpay.academy has

- **Platform:** BizLMS (a vendor-customised Moodle) on Moodle **4.1.2**, theme "epsilon". Live has not been touched
  ("replace it, do not hotfix it"). Sources: `DEMO-SCRIPT-2026-11-19.md` sections 4 and 7; `moodle-enhancement/docs/BIZLMS-MIGRATION-NARRATIVE.md` section 1.
- **What it runs today:** core LMS, compliance training, exams, three organisations (Airpay internal, Public, ZEEA
  Tanzania), HR-file sync, certificates, dashboards. Source: `moodle-enhancement/docs/SENTIENTIA-PRODUCT-GUIDE.md` section 8.
- **Its feature set (the vendor plugins on the April 2026 production copy):** 22 BizLMS plugins: classroom, request,
  evaluation, skill repository, courses, cost centre (organisation tree), program, forum, learning plan, cart,
  online exams, users, notifications, recompletion, role assignment, search, groups, tags, custom category, location,
  ratings, my team. 8 dashboard blocks: user dashboard, quick navigation, trainer dashboard, my skills, master info,
  trending modules, achievements, suggested courses. 3 enrolment methods: program, learning plan, classroom. Plus
  LearnerScript reports, the certificate tool and the Airpay payment gateway. Source:
  `moodle-enhancement/docs/cutover/STAGE-B-CAPABILITY-INVENTORY-2026-09-30.md` ("Counts"); payment gateway:
  `moodle-enhancement/docs/business/SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx` slide 11.
- **Inference (stated as such):** that inventory lists every plugin on the production copy whose code is not in the
  new build; it holds only BizLMS components. None of the Airpay/Sentientia plugins was ever installed on live.
- **What the old platform could not do** (August deck, slide 3, `SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx`):
  English-only in practice; dated, non-brand experience; stuck on an ageing Moodle line with upgrades dependent on
  the vendor; no AI, no mobile app strategy, no real-time engagement; single-company mindset; latent defects
  including a critical payment-verification flaw (later found and fixed in the new build).
- **Deadline reminders on live:** the record says "the BizLMS reminder never fired on live". Source:
  `moodle-enhancement/PROJECT-STATE.md`, entry 2026-11-16, bullet "local_sentientia_courses 2026111600".

---

## 2. Learner experience

- **Branded, role-aware home dashboard.** One screen with continue-learning, deadlines (overdue shown in red),
  progress, achievements, streak and a calendar; shaped for learner, manager, trainer or admin.
  Status: Built, on. vs live: REBUILT+ (live has the vendor user-dashboard block).
  Benefit: learners see what is due without hunting; one consistent screen for every role.
  Source: `moodle-enhancement/docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` (below "MG") section 3.1; `AIRPAY-ACADEMY-2.0-UAT-SHOWCASE-DECK-2026-09-21.pptx` (below "Sep deck") slide 5.
- **New app shell and navigation.** Fixed sidebar and top bar on every page, a role switcher for people with more
  than one role (e.g. a manager who is also a learner), breadcrumb trail.
  Status: Built, on. vs live: NEW. Benefit: one navigation everywhere instead of two different worlds; multi-role
  staff switch views without logging out.
  Source: MG section 5.1; `SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx` (below "Aug deck") slide 6; PROJECT-STATE 2026-11-02 heading.
- **My Courses**, grouped as in progress, pending, overdue and completed.
  Status: Built, on. vs live: REBUILT+. Benefit: a learner knows at a glance what to do next.
  Source: MG section 3.1; `DEMO-SCRIPT-2026-11-19.md` (below "Demo script") section 3.1.
- **One in-course player.** Progress bar, course contents, next-activity flow; SCORM e-learning, quizzes and
  assignments in one branded player that remembers where the learner stopped; the course header counts down to
  the completion deadline; on a phone the contents list folds away behind a button.
  Status: Built, on. vs live: SAME BASE+ (Moodle plays SCORM on live too). Benefit: fewer drop-offs mid-course;
  learners always know how long they have.
  Source: Aug deck slide 6; Sep deck slide 5; PROJECT-STATE 2026-11-16 (theme course-header countdown, phone course index).
- **Course catalogue with poster cards.** Search, filters (category, language, price), "For You", trending and new
  rails, course detail pages, "Provided by" badge for courses shared from another organisation.
  Status: Built, on. vs live: REBUILT+ (live has search and trending/suggested blocks). Benefit: learners find
  relevant courses themselves; L&D gets a shop window for its content.
  Source: MG section 4.3 "Catalog"; `moodle-enhancement/state-cards/sentientia_catalog-state.md`.
- **One-click enrolment in free courses for employees.** An employee clicks Enrol on a free course and is in; no
  cart, no key. Status: Built, off (switch per organisation; the demo script points at the one-click Enrol button on
  the test server, so it appears on there: UNCONFIRMED which switch state UAT holds). vs live: NEW.
  Benefit: removes the "how do I get into this course" ticket. Source: MG section 4.3; `moodle-enhancement/local/sentientia_catalog/db/feature_flags.php`; Demo script 3.1.
- **Request a course** outside one's assigned audience, with a reason; the manager approves or rejects with a
  reason the learner sees. Status: Built, on (the "Request enrolment" button on catalogue cards is Built, off).
  vs live: REBUILT+ (BizLMS request). Benefit: self-service access with manager control.
  Source: MG section 4.6 "Course Requests"; `moodle-enhancement/local/sentientia_request/db/feature_flags.php`.
- **Certificates wall** with downloadable PDF certificates that anyone can verify by code; on completion the
  learner gets a congratulations e-mail with the certificate PDF attached.
  Status: Built, on (e-mail sending depends on mail being connected). vs live: SAME BASE+ (live issues certificates
  with the same certificate tool; the wall and the attached-PDF e-mail are new).
  Benefit: proof of training in the learner's hands; fewer "send me my certificate" requests.
  Source: MG sections 3.1, 4.5 "Email Engine"; `moodle-enhancement/local/sentientia_pages/certificates.php`.
- **First-login onboarding wizard** for new joiners (shown in Hindi for a Hindi user) and a new-employee onboarding
  page. Status: Built, on. vs live: NEW. Benefit: a guided first day instead of a blank LMS.
  Source: Sep deck slide 5; `moodle-enhancement/local/sentientia_pages/onboarding.php`; Demo script 3.8.
- **My Skills**: self-rating against the skills expected for one's role, with a gap chart.
  Status: Built, on. vs live: REBUILT+ (BizLMS skill repository and "my skills" block).
  Benefit: employees see what to learn next for their role. Source: MG section 4.1 "Skills"; Sep deck slide 5.
- **"My learning paths" and "My programs" pages** for the learner. Status: Built, off. vs live: REBUILT+.
  Benefit: long journeys visible in one list. Source: `local/sentientia_learningpath/db/feature_flags.php`, `local/sentientia_programs/db/feature_flags.php` (both under `moodle-enhancement/`).
- **Own evaluation history** (feedback forms the learner has answered or still owes). Status: Built, off.
  vs live: REBUILT+. Source: `moodle-enhancement/local/sentientia_evaluation/db/feature_flags.php`.
- **Notification preferences**: each learner chooses the channels (in-app, e-mail, phone push, WhatsApp, SMS) where
  enabled. Status: Built, on (the channels themselves are off, see section 13). vs live: NEW.
  Benefit: reminders reach people where they actually look. Source: MG section 4.5.
- **Calendar feed**: a personal link that puts course deadlines, classroom sessions and exam dates into Outlook,
  Google or Apple Calendar. Status: Built, off (on in test env). vs live: NEW.
  Benefit: deadlines live in the calendar people already use. Source: MG section 4.7 "Calendar Sync"; `moodle-enhancement/local/sentientia_calendar/db/feature_flags.php`; `tools/uat/provision_test_users.php` line 164.
- **Floating help assistant** on every page with role-aware quick actions ("Team status" for a manager, "My
  certificates" for a public learner). Status: Built, on by default; its answers are sample text until live AI is
  approved. vs live: NEW. Benefit: answers "where do I find X" without a ticket.
  Source: MG section 4.4 "AI Assistant"; `moodle-enhancement/local/sentientia_platform/db/feature_flags.php` (`ai.assistant.enabled` default true).
- **Dark mode**, chosen by each user once an administrator makes it available. Status: Built, off.
  vs live: NEW. Benefit: comfort and accessibility. Source: platform feature_flags.php (`ux.darkMode.enabled`); Demo script caveat 5.
- **Accessibility built in**: WCAG AA colour contrast, reduced motion for people sensitive to animation,
  screen-reader structure, status never shown by colour alone, large touch targets on phones.
  Status: Built, on. vs live: NEW (the August deck says accessibility was untested on the old platform).
  Benefit: usable by employees with disabilities; meets procurement checklists. Source: MG section 5.4; Aug deck slide 6.

## 3. Languages and mobile

- **Full Hindi experience**, kept at full parity with English by automatic checks on every change; one switch
  changes every screen. Status: Built, on. vs live: NEW (old platform English-only in practice).
  Benefit: inclusion for Hindi-first staff; fewer support calls. Source: Demo script 3.8; Aug deck slides 3, 5; MG section 5.7.
- **Marathi, Kannada, Swahili** language packs. Status: Built, not yet reviewed by native speakers and not installed
  on the test server. vs live: NEW. Benefit (once reviewed): regional and Tanzanian (ZEEA) learners in their own
  language. Source: Demo script caveat 5 and section 3.8.
- **In-app language switcher** in the sidebar. Status: Built, off (users can still set their language in the
  profile). vs live: NEW. Source: platform feature_flags.php (`ux.languageSwitcher.enabled`).
- **Works in a phone browser.** Every screen change is checked at desktop and phone width; nav collapses, tables
  scroll, forms go full width. Status: Built, on. vs live: REBUILT+ (Moodle is responsive on live; the new design is
  phone-first). Benefit: field and sales staff learn on their phones. Source: MG section 5.5; Demo script section 7 "mobile".
- **Installable phone app (PWA)** with home-screen icon, offline fallback page and **push notifications** to the
  lock screen, self-hosted (no per-message fees to a push vendor). Status: Built, off (switched off by decision on
  29 September; do not cite the date on a slide). vs live: NEW. Benefit: an app-like experience without app-store
  overhead; reminders that land on the phone.
  Source: MG section 4.5 "PWA & Web Push"; Demo script caveat 5; PROJECT-STATE heading 2026-09-29 ("PWA OFF").
- **Native iOS/Android app.** Status: Groundwork (funded; scaffold only). vs live: NEW.
  Source: Demo script caveat 5 and section 7; `moodle-enhancement/state-cards/sentientia_mobile-state.md`.
- **App-ready interfaces**: 22 read and 14 learner-write web services audited as ready for a mobile app; admin
  functions deliberately kept off mobile. Status: Built, on. vs live: NEW. Benefit: a native app can be built on
  what exists. Source: root `CLAUDE.md` section 10; Demo script section 7.

## 4. Catalogue, public storefront and selling courses

- **Public storefront** (Netflix-style, logged-out) for the Public organisation, with a "Popular picks" carousel.
  Status: Built, on (default ON). vs live: REBUILT+ (live has a public catalogue and cart).
  Benefit: a modern shop window to sell courses to external learners. Source: MG section 4.3; `local/sentientia_catalog/db/feature_flags.php` (`public_lxp` default true).
- **Self sign-up** with bot protection, privacy and terms consent, e-mail confirmation.
  Status: Built, on (reCAPTCHA needs keys; confirmation e-mails need mail). vs live: REBUILT+.
  Benefit: external learners register without admin work, safely. Source: MG section 4.6 "Users & HRMS Sync".
- **Cart, checkout, order history, refunds and credit balances**, with an admin order list and daily sales export.
  Status: Built, on; payments are not connected yet (checkout shows "Payment coming soon"). vs live: REBUILT+
  (BizLMS cart). Benefit: a clean buying journey and finance control over refunds and credits.
  Source: MG section 4.3 "Cart & Orders"; Demo script 3.6.
- **Airpay payment gateway with fail-closed verification**: a payment-verification bypass found in the inherited
  gateway code was fixed so that anything not positively verified is rejected. Status: Built; payments switched off
  until a gateway sandbox test passes. vs live: REBUILT+ (live carries the flawed original).
  Benefit: no free access through a forged payment; Airpay's own gateway in its own LMS.
  Source: MG section 4.3 "Payment Gateway"; Demo script caveat 4; Aug deck slide 11.
- **GST tax invoices, credit notes and purchase receipts** (PDF). Status: Built, off (waits for Finance's answers).
  vs live: NEW (UNCONFIRMED whether the old cart issued any invoice). Benefit: compliant paperwork for every sale.
  Source: PROJECT-STATE 2026-10-14/15 entry ("GST tax invoices"); `local/sentientia_cart/db/feature_flags.php`.
- **Storefront basket hands paid items to the order cart.** Status: Built, off. Source: catalog feature_flags.php.
- **Recurring subscriptions** (one course, a category, or all-access, suspended on a failed charge). Status:
  Groundwork (access rules and lifecycle built; the recurring charge itself is not built; off). vs live: NEW.
  Source: MG section 4.3 "Recurring Subscriptions".

## 5. Learning paths, programs, classroom training and attendance

- **Learning paths**: curated course sequences with mandatory and optional courses, enrolment windows, and bulk
  enrolment by audience rules (designation, department, group). Status: Built, on. vs live: REBUILT+ (BizLMS
  learning plan). Benefit: induction and role journeys assigned in one action. Source: MG section 4.1 "Learning Paths".
- **Adaptive learning journeys**: a path that branches on quiz scores and pace: extra remedial content for someone
  struggling, a fast track for someone ahead, with every decision logged. Status: Built, off (on in test env).
  vs live: NEW. Benefit: less time wasted for strong learners, more support for weak ones.
  Source: `moodle-enhancement/state-cards/sentientia_adaptive-state.md`; `tools/uat/provision_test_users.php` line 471.
- **Certification programs**: multi-level programs (e.g. Foundation, Practitioner, Expert) where finishing a level
  unlocks the next. Status: Built, on. vs live: REBUILT+ (BizLMS program). Source: MG section 4.1 "Programs".
- **Classroom (instructor-led) training**: sessions with clash detection, trainer assignment, waiting list with
  automatic promotion, attendance by the trainer, calendar invites. Status: Built, on. vs live: REBUILT+ (BizLMS
  classroom). Benefit: classroom and online learning in one record. Source: MG section 4.1 "Classroom / ILT".
- **QR-code attendance** (learners scan to mark presence; the trainer's mark wins). Status: Built, off.
  vs live: REBUILT+ (rebuilt on new tables). Source: `local/sentientia_classroom/db/feature_flags.php`; classroom state card (2026-09-30 QR sections).
- **Bulk enrolment of a classroom by audience.** Status: Built, off. Source: classroom feature_flags.php.
- **Trainer dashboard** with the trainer's sessions. Status: Built, on. vs live: REBUILT+ (BizLMS trainer dashboard
  block). Source: MG section 4.8.

## 6. Assessments, exams and proctoring

- **Online exams** on Moodle's quiz engine with open and close windows, eligibility, reminders and a direct invite
  link. Status: Built, on (reminder sending depends on mail). vs live: REBUILT+ (BizLMS online exams).
  Source: MG section 4.1 "Exams".
- **Exam proctoring** chosen per exam: consent and identity step before the attempt, capture of focus loss and
  face-count changes during it, and a human reviewer who clears or flags; a person makes the final call.
  Status: Built, on (per-exam choice). Automated face matching: Groundwork ("ready for cloud face-match", August
  deck; live face-detection feedback is an open item). vs live: NEW.
  Benefit: credible high-stakes exams (e.g. AML) without an outside proctoring vendor.
  Source: MG section 4.6 "Proctoring"; Aug deck slide 8; `moodle-enhancement/state-cards/sentientia_proctoring-state.md` open items.
- **Evaluations and feedback forms**: form builder with conditional questions, automatic send on course completion,
  reusable templates, NPS and score analysis, anonymity that cannot be undone once promised.
  Status: Built, on (individual-response drill-down is Built, off). vs live: REBUILT+ (BizLMS evaluation).
  Benefit: training quality measured, not guessed. Source: MG section 4.1 "Evaluations"; evaluation state card (2026-09-25 "anonymity is sticky").

## 7. Certificates and recertification

- **Recertification (recompletion) cycles**: mandatory courses re-open automatically after a set period (e.g. every
  12 or 6 months) and reminders re-arm; every reset is logged. Status: Built; the daily renewal task is switched off
  pending a decision. vs live: REBUILT+ (BizLMS recompletion). Benefit: annual compliance renewals run themselves
  once switched on. Source: MG section 4.1 "Recompletion"; `local/sentientia_recompletion/db/feature_flags.php`; PROJECT-STATE 2026-10-14/15 ("Q3: run_rules stays OFF").
- **Certificate-delivery health widget** for administrators (sent, failed, suppressed in the last 7 days).
  Status: Built, on. vs live: NEW. Source: MG section 4.8.
- **Certificate templates per organisation** (each organisation's admin sees only its own templates).
  Status: Built, off. vs live: NEW. Source: `local/sentientia_pages/db/feature_flags.php`.

## 8. Gamification, challenges, leaderboards, ratings

- **Points, levels, badges and daily streaks**, with an optional celebration on completion.
  Status: Built, on (default ON). vs live: NEW (the old platform has an "achievements" block; UNCONFIRMED what it
  shows; the August deck says none of this existed in the purchased product).
  Benefit: people come back; learning feels rewarding. Source: MG section 4.2 "Gamification"; platform feature_flags.php (`engagement.gamification.enabled` default true); Aug deck slide 5 notes.
- **Real-time leaderboards** (quiz scores, completion speed, skill growth) that update on screen without refresh,
  with a one-click "hide me" privacy opt-out (on by default). Status: Built, off (on in test env for quiz and
  completion boards). vs live: NEW. Benefit: friendly competition for campaigns, with privacy respected.
  Source: MG section 4.2 "Real-time Leaderboards"; `local/sentientia_leaderboard/db/feature_flags.php`; provision script lines 162-163.
- **Challenges**: time-boxed competitions ("complete 3 courses this month", login streaks, quiz-score targets),
  join-to-compete, fair rules (target fixed at join time). Status: Built (alpha; no separate switch recorded;
  UNCONFIRMED whether it is in the navigation). vs live: NEW. Source: MG section 4.2 "Challenges".
- **Course star ratings, reviews and like/dislike counts.** Status: Built, off. vs live: REBUILT+ (BizLMS
  ratings). Benefit: social proof in the catalogue and a quality signal for L&D.
  Source: `local/sentientia_ratings/db/feature_flags.php`; PROJECT-STATE 2026-10-14/15 ("Q12").

## 9. Live engagement in sessions (polls, quizzes, Q&A)

- **Built-in live engagement** (the Mentimeter/Slido idea, inside the LMS): the trainer shows a join code, the room
  answers on their phones, results update on the projector in real time. Six question types: multiple choice, word
  cloud, open-ended answers with moderation, rating/NPS, quiz with a top-10 leaderboard, ranking. Guests can join
  without an account if allowed. Results export to a spreadsheet. A per-organisation off switch.
  Status: Built, off (on in test env; a town-hall poll is set up there). vs live: NEW.
  Benefit: livelier town halls and classroom sessions; no separate paid polling subscription; results stay with the
  rest of the learning record. Source: MG section 6; Demo script 3.4; provision script lines 160-162.

## 10. Authoring and the AI content engine

- **AI authoring studio**: an author enters a topic or document; the platform drafts course content and questions;
  a person reviews and approves; publish creates a real course with a mastery check. Voice-over is a separate,
  switched-off step. Status: Built, off (on in test env, sample-text mode). vs live: NEW.
  Benefit: first drafts in minutes instead of weeks; humans stay in charge of what learners see.
  Source: `moodle-enhancement/state-cards/sentientia_authoring-state.md`; Demo script 3.4; Aug deck slide 7.
- **AI quiz generation** in English and Hindi from course text: draft questions reviewed one by one, then pushed into
  a real course quiz. Status: Built, off (on in test env; sample mode; pushing into a quiz is a further switch, off).
  vs live: NEW. Benefit: assessment writing time cut; Hindi quizzes natively.
  Source: MG section 4.4 "AI Quiz Generation"; `local/sentientia_aiquiz/db/feature_flags.php`.
- **SOP-to-course pipeline**: turns a procedure document (PDF) into a narrated, packaged e-learning course in six
  steps (parse, script, slides, voice, package with validation, upload). Status: Built in sample mode only,
  validated on one sample SOP; voice never run live. vs live: NEW.
  Benefit: the backlog of SOPs that should be training can become training.
  Source: MG section 8; `SENTIENTIA-CONTENT-ENGINE-CSUITE-BRIEF-2026-10-15.md` (below "CE brief") section 3; Product guide section 5.
- **Content engine (next generation)**: one master course document compiled to the three e-learning standards
  (SCORM 1.2, SCORM 2004, cmi5); translation with memory and two human review stages; thirteen languages including
  Urdu and Arabic; India-hosted voice; branching decision scenarios (AML, POSH, customer handling); spaced
  "daily review" practice; later AI role-play. Status: Planned (a sample-mode pilot and six clickable prototypes
  exist outside the platform; the first slice, the course-document format, is Built, off). vs live: NEW.
  Benefit: compliance-grade multilingual courses at a fraction of agency cost; most of the cost is human review,
  not AI. Source: CE brief sections 1, 4, 5, 8; `local/sentientia_authoring/db/feature_flags.php` (`sentientia.content.scd.enabled`); Demo script caveat 5.

## 11. AI features and spend control

- **Central AI gateway with spend control**: every AI feature goes through one gateway with central key
  management, a ledger of every call and its cost, and hard daily and monthly caps that block when reached (an
  unset cap means AI stays off, never unlimited); prompt and answer text are not stored. Status: Built, off (on in
  test env, sample mode). vs live: NEW. Benefit: AI cannot overspend by design; finance sees every rupee.
  Source: `moodle-enhancement/state-cards/sentientia_ai-state.md`; Aug deck slide 7.
- **Human review before learners see AI output**, on every AI feature. Status: Built, on (part of each feature).
  Source: Aug deck slide 7; Demo script 3.4.
- **Personalised course recommendations** ("For You", 3 to 5 courses with a one-line "why this"), only from real
  catalogue courses. Status: Built, off (on in test env). vs live: NEW (live's "suggested courses" were not
  personalised, Aug deck slide 6). Source: MG section 4.4 "AI Recommendations".
- **Skills-first home**: dashboard recommendations driven by each employee's real skill gaps.
  Status: Built, off (on in test env). vs live: NEW. Source: `local/sentientia_skills/db/feature_flags.php` (`sentientia.dashboard.skillsrecs.enabled`); Sep deck slide 10.
- **AI content translation** into Hindi, Marathi, Kannada and Swahili, protecting brand and regulatory terms
  (Airpay, UPI, RBI, KYC, PAN...), side-by-side review before saving. Status: Built, off (on in test env).
  vs live: NEW. Source: MG section 4.4 "AI Content Translation".
- **Learner copilot**: the assistant can recommend, enrol a learner or book a classroom seat, only after the learner
  confirms, only within the learner's own permissions and organisation, every step audited.
  Status: Built, off (on in test env, sample mode). vs live: NEW. Source: `moodle-enhancement/state-cards/sentientia_copilot-state.md`.
- **Live AI** for all of the above. Status: not switched on; waits for an approved monthly cap and the AI key
  (operational; do not put the ask on the deck). Source: Demo script caveat 3.

## 12. Skills and talent

- **Skills framework**: skills catalogue, which skills each role needs and at what level, which courses teach
  which skills; completing a course can raise a skill level; full history.
  Status: Built, on. vs live: REBUILT+ (BizLMS skill repository). Source: MG section 4.1 "Skills".
- **Manager skill validation**: endorse, override with a comment, or ask for evidence; every change logged.
  Status: Built, on. vs live: NEW. Source: MG section 3.3.
- **Skills intelligence (AI)**: reads course content and proposes skills; a reviewer approves them into the
  organisation's official taxonomy; a gap engine compares each person's skills with their role's; a mapping ranks
  gaps by business impact. Status: Built, off (on in test env, sample mode). vs live: NEW.
  Benefit: a skills taxonomy without a consulting project; training aimed at the gaps that matter.
  Source: `moodle-enhancement/state-cards/sentientia_skillsai-state.md`; provision script lines 158-159.
- **Talent mobility**: career paths (role to role), succession planning with readiness ratings (HR only), an
  internal opportunity board matched to skills, with expressions of interest. Status: Built, off. vs live: NEW.
  Benefit: HR can grow people internally and plan succession on the same skills data.
  Source: `moodle-enhancement/state-cards/sentientia_talent-state.md`.

## 13. Manager tools

- **My Team dashboard**: every direct report with progress, compliance status and last activity; drill into one
  person. Status: Built, on. vs live: REBUILT+ (BizLMS "my team"). Benefit: managers see their team without asking
  L&D. Source: MG section 4.6 "Manager Workspace"; Demo script 3.2.
- **Team compliance view** for direct and extended teams (everyone below the manager in the reporting line, nobody
  else). Status: Built, on. vs live: NEW. Source: `moodle-enhancement/state-cards/sentientia_compliance_report-state.md` ("Product rule (Nitin, 2026-09-24)").
- **Approvals inbox** for course requests (approve, reject with reason, delegate), with reminders if left waiting.
  Status: Built, on. vs live: REBUILT+. Source: MG section 3.3.
- **Allocations**: a manager assigns courses, learning paths or classroom seats to team members.
  Status: Built, on. vs live: NEW (UNCONFIRMED whether BizLMS "my team" had allocation).
  Source: `moodle-enhancement/state-cards/sentientia_manager-state.md` (2026-09-25 allocation sections).
- **Team performance summary** with spreadsheet export. Status: Built, on. vs live: REBUILT+.
  Source: MG section 4.6 "Manager Workspace".
- **Manager digests and nudges**: a monthly team summary and a nudge when a manager has 3+ overdue reports.
  Status: Built (UNCONFIRMED whether these rules are on by default). vs live: NEW. Source: MG section 4.5 "Notification Rules Engine".

## 14. L&D administration

- **User management**: add users or bulk-import them in the same 24-column format as the HR file; dry-run
  preview, per-row error report, scheduled sync; joiners get a welcome e-mail, leavers are deactivated, reporting
  lines follow the HR data; full-name search; bulk suspend. Status: Built, on. vs live: REBUILT+ (BizLMS users).
  Benefit: the workforce in the LMS maintains itself from HR data. Source: MG section 4.6 "Users & HRMS Sync"; PROJECT-STATE 2026-11-03 heading (full-name search).
- **Automatic enrolment of new joiners into mandatory courses.** Status: Built, off (on in test env for Airpay and
  ZEEA). vs live: NEW. Benefit: no new joiner misses POSH or AML. Source: `local/sentientia_lifecycle/db/feature_flags.php`; provision script line 167.
- **Real-time HR sync with KeKa** (joiner, mover, leaver events as they happen, plus a scheduled check).
  Status: Built, off. vs live: NEW. Source: `local/sentientia_integrations/db/feature_flags.php`; Aug deck slide 8.
- **Organisation structure**: organisation, division, department, each with its own logo and colours.
  Status: Built, on. vs live: REBUILT+ (BizLMS cost centre). Source: MG section 4.6 "Org Hierarchy".
- **Course management**: create, share, bulk-enrol, export; course deadlines that drive reminders.
  Status: Built, on. vs live: REBUILT+ (BizLMS courses). Source: MG section 4.1 "Courses".
- **Role management with an audit trail**: every permission change recorded (who, what, when) and exportable for
  quarterly access reviews. Status: Built, on. vs live: NEW. Benefit: access reviews become a download.
  Source: MG section 4.6 "Roles & Permissions".
- **Switchboard**: one console to turn each feature on or off per organisation (and per customer), every change
  recorded. Status: Built, on (site administrators). vs live: NEW. Benefit: roll features out deliberately,
  organisation by organisation, with an instant way back. Source: MG section 7.3; Demo script 3.8.
- **Branded e-mail templates** with placeholders and live preview, overridable per organisation; eleven rule types
  (not started, deadline approaching, compliance enrolled/reminder/overdue, weekly manager escalation, ramping
  reminders, new course, course completed). Status: Built, on; no e-mail leaves the test server; the deadline
  reminder and escalation tasks are switched off until a go-live decision; three BizLMS-style e-mails (course
  enrolment, path enrolment, completion copy to manager) are Built, off. vs live: REBUILT+ (BizLMS notifications).
  Source: MG section 4.5 "Email Engine"; `local/sentientia_emails/db/feature_flags.php`; PROJECT-STATE 2026-11-17 decision 5.
- **Notification rules engine** with a delivery log per channel. Status: Built, on. vs live: NEW.
  Benefit: "did she get the reminder?" is a lookup, not a hunt. Source: MG section 4.5.
- **Weekday learning digest** e-mail to learners. Status: Built, on (its compliance-deadline lines are Built, off).
  vs live: NEW. Source: `moodle-enhancement/state-cards/sentientia_notifications-state.md` (2026-11-16 section).
- **Smart nudges**: "course not started", "streak at risk", "new course". Status: Built, off. vs live: NEW.
  Source: `local/sentientia_notifications/db/feature_flags.php`.
- **WhatsApp and SMS reminders** with per-user opt-in consent (administrators cannot force it), India's DLT
  template rules, delivery analytics; SMS reaches staff without data. Status: Built, off (sample mode end to end;
  never sent). vs live: NEW. Benefit: India-first reach far beyond e-mail.
  Source: MG section 4.5 "WhatsApp / SMS Bridge"; Demo script caveat 5.
- **Microsoft Teams channel announcements.** Status: Built (UNCONFIRMED whether configured or on).
  vs live: NEW. Source: MG section 4.7 "Integrations Hub".
- **System health widgets** for administrators (scheduled jobs, certificate e-mails). Status: Built, on.
  vs live: NEW. Source: MG section 4.8.
- **Dashboard blocks drawer** (side panel for dashboard widgets). Status: Built, off (on in test env).
  Source: theme `theme/sentientia/db/feature_flags.php`; PROJECT-STATE line 445.
- **Written user guides for 8 roles** (learner, public learner, manager, trainer, author, compliance officer,
  L&D admin, site admin). Status: Built. vs live: NEW. Source: MG section 3; `moodle-enhancement/docs/user-guides/`.

## 15. Compliance

- **Compliance matrix**: per organisation, mandatory courses as columns and people as rows, red/amber/green/grey,
  drill into the reds. Status: Built, on. vs live: NEW (live runs compliance courses; UNCONFIRMED whether it has an
  equivalent matrix). Benefit: audit-ready coverage in one screen. Source: MG section 4.6 "Compliance Reporting"; Demo script 3.3, 3.5.
- **Deadline reminders (7, 3, 1 days before) and manager escalations (after the deadline)**, each one a logged row.
  Status: Built, off (the two tasks have never run; they are enabled for Airpay only after a decision on timing and
  volume). vs live: NEW in practice (the old platform's reminder never fired). Benefit: compliance chases itself.
  Source: MG section 4.1 "Courses"; PROJECT-STATE 2026-11-16 and 2026-11-17 decision 5.
- **Audit trail of every reminder and escalation**: "who was assigned, who is overdue, who was reminded, and when"
  in one view. Status: Built, on. vs live: NEW. Source: Demo script 3.5.
- **Exemptions with a reason and an expiry**, and an "excluded from all tracking" list. Status: Built, on.
  vs live: NEW. Benefit: justified exceptions are recorded, not hidden. Source: MG section 4.6; compliance report state card (2026-11-17).
- **Spreadsheet export for auditors**, limited to holders of a dedicated permission (line managers can view, not
  bulk-export personal data). Status: Built, on. vs live: NEW. Source: MG section 4.6 "Compliance Reporting".
- **Daily compliance scan** that flags overdue and due-soon mandatory training. Status: Built, on (sends via
  notifications). vs live: NEW. Source: MG section 4.1 "Lifecycle"; lifecycle state card.
- **Compliance dashboard widget** for learners, managers and compliance. Status: Built, on. vs live: NEW.
  Source: MG section 4.8; `moodle-enhancement/state-cards/block_sentientia_compliance-state.md`.
- **Recompletion evidence view.** Status: Built, off. Source: recompletion feature_flags.php.

## 16. Analytics and reports

- **L&D analytics dashboard**: KPI tiles (engagement, completion rate, time to complete, top courses), a learning
  funnel (enrolled to certified), drill-down, export; each organisation sees only its own figures.
  Status: Built, on. vs live: REBUILT+ (live has LearnerScript vendor reports). Source: MG section 4.4 "Analytics"; PROJECT-STATE 2026-11-17 (KPIs and funnel).
- **Predictive analytics**: at-risk learners flagged from four visible signals (days since last visit, completion
  gap, overdue courses, drop in logins), and team skill-gap projection. Status: Built, off (on in test env).
  vs live: NEW. Benefit: managers act before a deadline is missed, and can see why someone is flagged.
  Source: `moodle-enhancement/state-cards/sentientia_analytics_predictive-state.md`.
- **Training ROI dashboard** with its assumptions shown and adjustable. Status: Built, off (on in test env).
  vs live: NEW. Benefit: L&D can show value in business terms. Source: same card; provision script line 164.
- **Saved report builder**: define once, rerun, export; auditors can export without edit rights.
  Status: Built, on (a training-transcript report and an imported login-days column are Built, off).
  vs live: REBUILT+. Source: MG section 4.4 "Reports"; `local/sentientia_reports/db/feature_flags.php`.
- **AI spend ledger report** for administrators. Status: Built (part of the AI gateway). vs live: NEW.
  Source: AI gateway state card.

## 17. Several organisations on one platform; white-label

- **Three organisations kept apart**: Airpay internal, Public and ZEEA Tanzania; each organisation's admins see and
  change only their own people, courses and reports; a deliberately named platform L&D role can work across all
  three; anything unclear defaults to "nothing". Status: Built, on. vs live: REBUILT+ (live has the three
  organisations; the new build makes the separation strict and checked automatically).
  Benefit: one platform, no data leaking between organisations; safe to host partners such as ZEEA.
  Source: `moodle-enhancement/docs/adr/ADR-031-cross-tenant-authority.md` (Decision); Demo script 3.7; Sep deck slide 9.
- **Course sharing between organisations** with a request and approval step ("Browse Airpay Library"); completion
  data stays with the borrowing organisation. Status: Built, on. vs live: NEW.
  Benefit: ZEEA or Public reuse Airpay content without copying it. Source: MG section 4.1 "Courses"; Demo script 3.7.
- **Per-organisation branding** (logo and colours). Status: Built, on. vs live: REBUILT+. Source: MG section 5.2.
- **Per-customer white-label**: logo (light and dark), colours, font, favicon, app icons and the customer's name
  everywhere it appears (login, e-mails, calendar invites, WhatsApp consent text, push messages, five languages)
  from configuration, no code change; a private-and-confidential footer per customer. Status: Built, on.
  vs live: NEW. Source: MG sections 5.2, 7.4; Sep deck slide 9.
- **Customer registry** (a second company as data, not code). Status: Groundwork by design: built and dormant; a
  fictional second customer was demonstrated on the development copy; productisation waits for a real second
  customer. vs live: NEW. Benefit: Academy 2.0 can become a product sold to other enterprises.
  Source: `moodle-enhancement/state-cards/sentientia_core-state.md` (2026-08-20 section); Aug deck slide 9; Demo script section 7 "Can we sell it".
- **Account types**: employees, public consumers, partner employees treated as distinct kinds of user, not
  employees with blank fields. Status: Built, on. vs live: NEW. Source: MG section 7.1.

## 18. Privacy, security and audit

- **DPDP data-rights hub**: data export and deletion requests in one place; a consent ledger; a DPDP statement page;
  the modules declare the personal data they hold (a privacy test on 13 November found a few gaps in older modules,
  being closed: say "being completed", not "every module"). Status: Built, on. vs live: SAME BASE+ (Moodle's own privacy
  tool exists on live; the hub, ledger and declarations are new). Benefit: ready for India's data-protection law.
  Source: MG section 4.6 "Privacy Hub"; Demo script section 7 "DPDP".
- **Complete erasure**: a fix for a Moodle flaw where a deletion request can show "Deleted" while some data stays
  behind. Status: Built, off (until the DPO, with legal, records the retention decision). vs live: NEW.
  Benefit: a deletion request really deletes. Source: `moodle-enhancement/docs/adr/ADR-035-privacy-erasure-completeness.md`; Demo script section 7.
- **Personal data kept out of AI**: content sent for generation is de-identified; a personal-data scanner is part
  of the content-engine plan. Status: Built for course content (the chat assistant sends the learner's own name,
  role and courses by design). vs live: NEW. Source: Demo script section 7 ("AI and personal data"); CE brief section 7.
- **Audit logs**: permission changes, notification and e-mail deliveries, AI spend, copilot actions, talent changes,
  paid orders and refunds, proctoring flags. Status: Built, on (where the feature is on). vs live: NEW.
  Source: MG section 4.6; PROJECT-STATE line 4 ("A5 (audit events + tenant trail)"); copilot, talent and AI state cards.
- **Single sign-on (Microsoft Entra, Google, other) and multi-factor login**: configuration prepared using
  Moodle's own tools. Status: Built, off (waits for the identity app registration). vs live: NEW.
  Benefit: one login for staff; stronger protection for admins. Source: `moodle-enhancement/docs/security/ENTERPRISE-IDENTITY-PACK.md`; `moodle-enhancement/docs/OAUTH2-SSO-SETUP.md`; Demo script caveat 5.
- **Security hardening** found and fixed during testing: cross-organisation self-enrolment, account discovery at
  sign-up, rate limits, security headers, "Log in as" limited to site administrators. Status: Built, on.
  vs live: NEW (these are fixes the old platform does not have). Source: Demo script section 7 "Is it secure?"; Sep deck slide 7.
- **Independent penetration test and certifications (ISO 27001, SOC 2), 25,000-user load test.** Status: Planned
  (not done; budget item, keep off this deck). Source: Demo script section 7.

## 19. Integrations

- **Public REST API** (versioned, rate-limited, documented with OpenAPI; write access a separate switch).
  Status: Built, off. vs live: NEW. Benefit: other Airpay systems can read and write learning data safely.
  Source: `local/sentientia_api/db/feature_flags.php`; Sep deck slide 6.
- **Automatic account provisioning (SCIM 2.0)**: users and groups created, changed and removed from the identity
  system, with an access attestation export. Status: Built, off. vs live: NEW.
  Benefit: joiners and leavers handled by IT's identity system, not by hand. Source: `moodle-enhancement/state-cards/sentientia_api-state.md` (ADR-030 sections).
- **Outbound event notifications (signed webhooks)** for course completion, enrolment and certificate events.
  Status: Built, off. vs live: NEW. Benefit: HR or other systems learn instantly when someone completes.
  Source: api state card; api feature_flags.php.
- **LTI 1.3** (connect external learning tools, or offer our courses to other platforms). Status: Groundwork
  (provider and consumer built; some parts are extension points). vs live: NEW. Source: PROJECT-STATE 2026-06-16 gap build ("api LTI JWKS fetch + user provisioning are extension points").
- **Learning record store (xAPI) and cmi5**: captures learning activity in the standard many enterprise buyers ask
  for. Status: Built, off (activity capture on in test env; the external endpoint and cmi5 are off).
  vs live: NEW. Source: `moodle-enhancement/state-cards/sentientia_xapi-state.md`; provision script line 165.
- **Content marketplace connectors** (Go1, Udemy Business, Coursera, Skillsoft) into one searchable library mapped
  to skills. Status: Built, off; never connected to a real provider. vs live: NEW.
  Source: `moodle-enhancement/state-cards/sentientia_content_market-state.md`.
- **Two-way calendar sync** with Microsoft 365 and Google. Status: Built, off. vs live: NEW.
  Source: calendar feature_flags.php (`oauth.enabled`).
- **Microsoft 365 knowledge automation** (SharePoint SOPs into the content pipeline, Outlook sessions into
  classrooms, Teams attendance). Status: Groundwork (secure sign-in and storage built; every Microsoft call
  deliberately refuses to run). vs live: NEW. Source: MG section 4.7 "Microsoft 365".

## 20. Platform

- **Moodle 5.3 LTS base** (security support to October 2029) instead of the live 4.1.2. Status: Built (the test
  server runs it). vs live: NEW. Benefit: a current, supported base, upgraded on our own schedule, not the vendor's.
  Source: Demo script section 7 "Moodle upgrade path"; root `CLAUDE.md` section 2.
- **Owned design system** on the 2026 Airpay brand book (brand blue, Montserrat, consistent spacing), every screen
  and template owned, no vendor theme underneath. Status: Built, on. vs live: NEW.
  Source: MG section 5.1; `.claude/rules/frontend.md`; Aug deck slide 5.
- **Every new feature behind an off-by-default switch**, per customer and per organisation; installing an update
  changes nothing until someone decides. Status: Built, on. vs live: NEW. Benefit: no surprise changes for users.
  Source: MG section 7.3.
- **Fully owned code with a written decision record** (no vendor dependency or licence ambiguity; the core stays
  open source, no per-seat licence). Status: Built. vs live: NEW (the old platform depends on a vendor under an
  annual maintenance contract). Source: Product guide section 2; Aug deck slide 3; Demo script section 7 "What does it cost" (licence line only).

---

## 21. Short list for the deck (biggest additions over live, in plain words)

1. A modern, branded, phone-friendly experience for every role, in Hindi as well as English. (Sections 2, 3)
2. Managers see and act on their own team: progress, compliance, approvals, allocations. (Section 13)
3. Compliance that chases itself, with an audit trail of every reminder (built; switched on at go-live). (Section 15)
4. Built-in live polls and quizzes for town halls and classrooms. (Section 9)
5. AI that drafts courses, quizzes, translations and skills, with human approval and a hard spend cap. (Sections 10, 11, 12)
6. Skills, career paths and succession on one data set. (Section 12)
7. Analytics that predict who is at risk and show training ROI. (Section 16)
8. A proper public storefront with GST invoices, ready when payments are switched on. (Section 4)
9. Three organisations on one platform, each kept apart, and the option to serve other companies under their own
   brand. (Section 17)
10. Privacy (DPDP), single sign-on readiness, audit logs and integrations IT and HR systems can plug into. (Sections 18, 19)
11. A current, supported Moodle base we own and upgrade ourselves. (Section 20)

## 22. Plain-language substitutes for the deck

| Do not say | Say |
|---|---|
| tenant, tenant isolation | organisation; each organisation's data kept apart |
| feature flag, Switchboard flag | switch, turned on per organisation |
| mock mode | sample mode (no AI is called) |
| PWA, web push | installable phone app, phone notifications |
| SSE, real-time stream | updates live on screen |
| SCIM | automatic account set-up from the identity system |
| webhooks | automatic notices to other systems |
| xAPI, LRS, cmi5, LTI | learning-record and e-learning standards that large buyers require |
| HRMS CSV, JML | HR file, joiners, movers and leavers |
| RAG | red, amber, green |
| DPDP | India's Digital Personal Data Protection Act |

## 23. Not confirmed from the record

- Whether the one-click free enrolment switch is on for Airpay on the test server (the demo script shows the button).
- Whether the challenges feature is reachable from the navigation.
- Whether the manager monthly summary and manager nudge rules are on by default.
- Whether the Microsoft Teams announcement feature is configured.
- Whether the old cart issued invoices of any kind.
- Whether the old platform's "achievements" block shows points or badges.
- Whether the old platform has a compliance matrix equivalent.
- Whether BizLMS "my team" offered allocations.
- Automated face matching in proctoring: described as "ready for cloud face-match" (August deck); the state card
  lists live face detection as open. Treat as groundwork.
