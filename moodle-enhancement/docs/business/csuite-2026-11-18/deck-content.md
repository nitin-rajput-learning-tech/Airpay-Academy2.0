# Deck content spec: Academy 2.0, what we built over today's airpay.academy

**Status:** content spec v2 for the C-suite deck, 18 November 2026. It replaces the v1 "three asks" deck
(`deck-content-v1-asks.md`, `SENTIENTIA-CSUITE-DECK-2026-11-18-v1-asks-REJECTED.pptx`), which Nitin rejected.
No .pptx yet; a later step builds it on the airpay template.
**Audience:** MD and Founder, CTO, CHRO and peers. **Length:** 18 slides, about 25 to 30 minutes live.
**Purpose (Nitin's words):** "tell them what we built over current live airpay.academy: all features list, how it can
benefit, remove all operational details".
**Written:** 17 November 2026, from the repository record only. Each slide carries an HTML comment naming the files its
facts come from.

---

## How to use this spec

**Path roots in the source comments.** `ME/` = `D:/Claude Local/airpay-ld-os/moodle-enhancement/`; `ROOT/` =
`D:/Claude Local/airpay-ld-os/`; `BZ/` = `D:/Claude Local/Moodle Backup/01-production-codebase/html/` (the production
code snapshot of today's site). `RF` = `ME/docs/business/csuite-2026-11-18/research-features.md` (feature inventory);
`RB` = `ME/docs/business/csuite-2026-11-18/research-baseline.md` (what today's site has). `MG` =
`ME/docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md`; `DS` = `ME/docs/business/DEMO-SCRIPT-2026-11-19.md`.

**What stays off every slide and every speaker note.** Budgets, rupee figures and asks; IT, servers, backups,
rehearsal, go-live steps, the test server and runbooks; the GST open points; test counts, CI and tooling; version
numbers, decision-record numbers, plugin names and switch names; dates of engineering events. Plain words only:
"each organisation's data kept apart", never "tenant isolation" (substitutes: RF section 22).

**Honest status, said the same way everywhere.**
- Academy 2.0 is **built, not yet live**. Today's airpay.academy keeps running until Academy 2.0 replaces it. No
  learner has used Academy 2.0. Never say "in use", "serving" or "results".
- A feature that is built but switched off is "ready, switched on when we choose". In the catalogue it carries a
  hollow circle.
- AI features run in **sample mode**: no live AI call has been made. Say so on the AI slide.
- "New" means today's site has nothing like it. Where today's site has a basic version, say "rebuilt and extended",
  never "new". Where the record is unsure, the spec takes the cautious reading (see the catalogue legend).

**Template.** `airpay-template-0.1.pptx` in this folder (16:9, 12192000 x 6858000 EMU). Layouts used:

| Layout | Slides |
|---|---|
| 1_Title Slide | 1 |
| Title Only (visual drawn as shapes) | 2, 3, 5, 7, 10, 12, 14, 15, 16, 17 |
| Comparison | 4, 6, 13 |
| Title and Content (table) | 8 |
| Two Content | 9, 11 |
| End/last Slide | 18 |

**Look.** Airpay Brand Book 2026-06 colours, applied on shapes explicitly (the template theme is the Office default):
primary #0066A7 (dominant: numbers, icon circles, headings), accent #1985DD (secondary), page tint #F2F4FB, light
blue tint #E8F2F9 for benefit boxes, text #1A1A2E and #5A6070, orange #ED692B for at most one emphasis per slide.
Every run names Calibri (the template layouts' font; Aptos is not on a plain Windows laptop).
- **Motif:** a simple line icon, white, in a #0066A7 circle. Reuse it on every content slide.
- **"Today" vs "Academy 2.0" columns:** "Today on airpay.academy" sits in a #F2F4FB panel, #5A6070 text, with no icons.
  "Academy 2.0" sits in a white card with a soft shadow, a #0066A7 heading and a small check icon on each line.
- **Benefit line:** one sentence in a rounded #E8F2F9 box at the foot of the slide, starting with a small bold
  "Benefit" label.
- **Switched-off lines on area slides (4 to 13):** the same hollow circle ○ as the catalogue marks a line that is
  built but switched off, and such a slide carries the legend "○ ready, switched on when we choose" in 12pt #5A6070
  at the foot. On-slide word counts in this spec are what the audience reads; labels such as "Benefit box" and
  "Left heading" are instructions, not text.
- **Avoid** (pptx skill): underlines below titles, colour bars and edge stripes, centred body text, text-only slides.
  Titles sit in the same place at the same size on every content slide.
- **Sizes:** titles 32 to 40pt, body 16 to 20pt, stat numbers 54 to 66pt, captions 12pt. Catalogue slides: 12pt items,
  14pt group headings, never below 12pt.
- **Footer on content slides:** "Academy 2.0 · C-suite review, 18 Nov 2026 · Confidential". No "test server" tag.

**Images.** None in this version. The existing captures show test-data counts (for example "3 active users") that a
C-suite reader could take as results, and development-copy captures can show real employees' names. Icon cards
carry the visuals instead.

**Catalogue markers (slides 14 to 16), counted from this spec.** 112 lines in 16 groups:
- unmarked (71 lines): new, today's site has nothing like it;
- trailing **↑** (41 lines): today's site has a basic version; Academy 2.0 rebuilds and extends it;
- leading **○** (48 lines): built, ready, switched on when we choose. The other 64 are part of the standard build.
Draw ↑ and ○ as small shapes, or use a font that has them (Segoe UI Symbol), so they render on any laptop. Where the
record is unsure whether today's site has something, the line carries ↑ (accessibility, the native-app interfaces,
manager allocations, the compliance matrix): the cautious reading.

---

## Slide 1. Cover

- **Layout:** 1_Title Slide
- **Title:** Academy 2.0
- **Subtitle:** What we built over today's airpay.academy
- **Small line under the subtitle:** C-suite review, 18 November 2026 · Learning & Development
- **Visual:** the template cover as is. No screenshot.
- **Speaker notes:** Today is one simple story: what Academy 2.0 gives us over the airpay.academy our people use
  today. Academy 2.0 runs on Sentientia LMS, a learning platform Airpay owns. It is built. It is not yet live: today's
  site keeps running until Academy 2.0 replaces it, and no learner has used the new platform yet. Where a feature is
  built but kept switched off until we decide to use it, I will say so. The AI features run in sample mode for now.
  Every fact in this deck comes from our own project record. First the headline, then what changes for each group,
  then ten areas, the full list, and why owning the platform matters.

<!-- Sources: not live, no learner has used it, today's site untouched: DS section 4 caveats 1-2; RF lines 8-11.
AI sample mode: DS section 4 caveat 3. "Our own platform": ROOT/CLAUDE.md "MISSION"; ME/docs/adr/ADR-024-absorb-and-debrand-bizlms.md
lines 11-28 (Airpay purchased the vendor source and owns it outright). Meeting date: the task brief. -->

---

## Slide 2. From today's academy to Academy 2.0

- **Layout:** Title Only
- **Title:** From today's academy to Academy 2.0
- **On-slide text:**
  - Four stat callouts:
    - **112** capabilities built (71 new)
    - **5** ways to reach a learner (today: mainly e-mail)
    - **2** English and Hindi on every screen
    - **3** organisations, each kept apart
  - Four shifts, "from" then "to":
    - Vendor platform → our own product
    - Deadlines tracked → deadlines chased
    - One screen for all → a home per role
    - No AI → AI with a person in charge
  - Small line at the foot: Built, not yet live.
- **Visual:** top half, four stat callouts on white cards over the #F2F4FB page tint: the number at 60pt in #0066A7,
  the label at 14pt below. Bottom half, four rows: the "from" phrase in #5A6070, an arrow, the "to" phrase in bold
  #1A1A2E. Orange only on the arrow of "Deadlines tracked → deadlines chased".
- **Speaker notes:** The whole story on one slide. We have built 112 capabilities, counted line by line in the list at
  the back: 71 have nothing like them on today's site, and 41 rebuild something today's site does in a basic way.
  Learners can be reached on five channels instead of mainly e-mail; phone notifications, WhatsApp and SMS are built
  and switched on when we choose. Every screen is complete in English and Hindi, with three more languages prepared. Academy 2.0 is
  built to carry Airpay, Public and ZEEA on one platform, each kept apart. The four shifts are the message: our own
  product, deadlines that chase themselves, a home for every role, and AI with a person in charge.

<!-- Sources: 112 / 71 / 41 / 48: counted from the catalogue on slides 14-16 of this spec (built lines only; planned and
groundwork items left out: RF sections 10, 11, 18, 19 "Planned"/"Groundwork"). Five channels (e-mail, in-app, phone
push, WhatsApp, SMS): MG section 4.5; RF section 2 "Notification preferences". Today mainly e-mail: RB 1.6
(ME/parity-audit-2026-05-15/airpay_notifications.md architecture items 2, 6, 7: templates target e-mail, though
Moodle messaging can also show a pop-up, so the label says "mainly"). Languages "on every screen", not "in full":
e-mail templates are English only (RB 1.4). Languages: DS section 3.8 and caveat 5;
RB 1.4; today "English-only in practice": ME/docs/business/SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx slide 3.
Three organisations kept apart: ME/docs/adr/ADR-031-cross-tenant-authority.md (Decision); DS section 3.7.
Vendor platform: ADR-024 lines 11-28; RB section 0. Deadlines chased: MG line 223 (7/3/1 reminders, 1/7/14
escalations). Home per role: RB 1.3; MG section 3.1. AI with a person in charge: Aug deck slide 7; DS section 3.4. -->

---

## Slide 3. What changes for each group

- **Layout:** Title Only
- **Title:** What changes for each group
- **On-slide text (five rows):**
  - **Learners:** what is due, at a glance, in Hindi or English, on any phone
  - **Managers:** the whole team's progress, approvals and assignments in one place
  - **L&D and admins:** HR uploads previewed first; every access change logged
  - **Compliance:** audit-ready status and a trail of every reminder
  - **The business:** our own platform, upgraded on our schedule
- **Visual:** five icon-in-circle rows (person, team, gear, shield with tick, building), group name bold in #0066A7,
  one line of benefit in #1A1A2E. Even spacing, left-aligned.
- **Speaker notes:** Before the detail, the benefit by audience. Learners get one clear home that shows what is due
  and lets them carry on where they stopped, in Hindi or English, on a phone. Managers see their whole team and act on
  it themselves: approve, assign, export. L&D sees every HR upload before it runs, turns each feature on per
  organisation, and has a record of every access change. Compliance gets a red, amber and green view of every
  mandatory course and a record of every reminder sent. And the business owns the platform and upgrades it on its own
  schedule, not a vendor's. The next ten slides take each area in turn, each with a line on what today's site
  does.

<!-- Sources: learner home and Hindi: MG section 3.1; DS section 3.8. Managers: DS section 3.2; RF section 13.
L&D: HR upload preview RB 1.19 (today already has a 24-column HR upload and an hourly sync, so the row claims only the
preview); permission log RB 1.24; switchboard DS section 3.8. Compliance: DS sections 3.3, 3.5; RF section 15.
The business: owned source, vendor-dependent upgrades today: ADR-024 lines 11-28; RB 1.25. Today's site has no per-seat
licence (bought source plus an annual maintenance contract: ROOT/docs/email-amc-agreement-request.md), so slide 3 does
not claim one as a saving. -->

---

## Slide 4. A clear home for every learner

- **Layout:** Comparison
- **Title:** A clear home for every learner
- **On-slide text:**
  - Left heading **Today on airpay.academy:**
    - Vendor theme, tabbed dashboard
    - Catalogue search, trending and suggested blocks
    - Certificates in a block and a plain list
  - Right heading **Academy 2.0:**
    - A home per role: due, overdue, continue
    - One navigation on every page
    - Catalogue with search, filters and trending
    - Every certificate on one wall
    - First-day onboarding; accessibility built in
  - Benefit box: Learners see what is due without hunting.
- **Visual:** comparison panels as set in "Look"; a small home icon in a #0066A7 circle beside the right heading.
- **Speaker notes:** Today learners land on a vendor dashboard of tabs and blocks. In Academy 2.0 every role has its
  own home: what is due, overdue items in red, continue where you stopped, achievements and a calendar. The navigation
  is the same on every page, and people with two roles, say a manager who is also a learner, switch without logging
  out. The course player counts down to the deadline. Certificates sit on one wall, and anyone can still check one
  online by its code, as today. New joiners get a guided first day. The design follows accessibility standards, and
  dark mode is built and ready. The benefit we expect: fewer missed deadlines and fewer "where do I find" questions.

<!-- Sources: today: RB 1.1 (epsilon theme), RB 1.3 (dashboard tabs, trending, suggested, catalog search), RB 1.11
(certificates, My Achievements block; BZ/blocks/achievements). Academy 2.0: RF section 2 (home dashboard, app shell and
role switcher, course player countdown, catalogue, certificates wall, onboarding wizard, accessibility, dark mode);
MG sections 3.1, 5.1, 5.4; verification ID: MG line 142. "For You" falls back to trending while AI picks are off: MG
line 503 (the personalised picks are on slide 15, marked off). Today already verifies certificates by code and has a
"My certificates" page: BZ/admin/tool/certificate/index.php lines 18-63, lang strings verifycertificates, mycertificates.
Dark mode absent on today's theme: BZ/theme/epsilon has no
dark-mode code (searched for this deck). -->

---

## Slide 5. In their language, on their phone

- **Layout:** Title Only
- **Title:** In their language, on their phone
- **On-slide text (three columns, each a headline, two lines and a "Today" line):**
  - **Language:** Hindi on every screen. ○ Marathi, Kannada and Swahili prepared. *Today: English in practice.*
  - **Phone:** Designed for the phone first. ○ Installable app with lock-screen alerts. *Today: no app or phone
    alerts of our own.*
  - **Channels:** E-mail and in-app; ○ phone, WhatsApp and SMS. Learners pick channels and quiet hours.
    *Today: mainly e-mail.*
  - Benefit box: Reminders land where people actually look.
  - Legend: ○ ready, switched on when we choose
- **Visual:** three columns, each topped by a large icon in a #0066A7 circle (speech bubble, phone, bell). "Today"
  lines in italic 14pt #5A6070 at the foot of each column, as on the other slides.
- **Speaker notes:** Three ways Academy 2.0 reaches people that today's site cannot. Language: every screen is
  complete in Hindi as well as English, and Marathi and Kannada, and Swahili for our Tanzanian learners at ZEEA, are
  prepared and need a native speaker's review first. Phone: every screen is designed for the phone; the installable
  app with lock-screen notifications is built and ready, and it runs on our own notification service with no
  per-message fees. Channels: learners choose where reminders reach them and set quiet hours. WhatsApp and SMS go only
  to people who opt in, under India's rules for business messages, and SMS reaches staff without mobile data.

<!-- Sources: Hindi complete, other three prepared, not reviewed by native speakers: DS section 3.8 and caveat 5; RF
section 3. Today English in practice: Aug deck slide 3; RB 1.4. Phone-first design: MG section 5.5. Installable app and
push built, switched off: RF section 3; DS caveat 5. No per-message fees: RB 1.5; DS section 7 "What does it cost?"
(push uses no paid outside service). Today no app or push channel of Airpay's own: RB 1.5 (ME/docs/MOBILE-PUSH-SETUP.md
section 1; whether anyone uses the generic Moodle app with today's site is unconfirmed). "Every screen", not "the whole
experience": e-mail templates are English only (RB 1.4). Channels and consent, DLT rules, SMS without data: MG section
4.5 "WhatsApp / SMS Bridge"; RF section 14.
Quiet hours: ME/local/sentientia_notifications/lang/en/local_sentientia_notifications.php lines 73, 80-81. Today
mainly e-mail: RB 1.6 (see slide 2). -->

---

## Slide 6. Compliance training that chases itself

- **Layout:** Comparison
- **Title:** Compliance training that chases itself
- **On-slide text:**
  - Left heading **Today on airpay.academy:**
    - A deadline reminder that has never fired
    - No escalation to managers
    - No compliance-officer role
  - Right heading **Academy 2.0:**
    - Red, amber, green matrix per organisation
    - ○ Reminders 7, 3 and 1 day before
    - ○ Manager escalation after the deadline
    - Every nudge logged
    - Exemptions with a reason; auditor export
  - Benefit box: Audit-ready any time; overdue people chased automatically.
  - Legend: ○ ready, switched on when we choose
- **Visual:** comparison panels; inside the right panel, a small drawn grid of red, amber, green and grey dots
  suggesting the matrix (illustration only, no data). The one orange emphasis: "has never fired" in the left panel.
- **Speaker notes:** This is the biggest practical change. Today's platform has a course-deadline reminder, but the
  record shows it has never sent an e-mail on the live site, no add-on escalates overdue training to a manager, and
  there is no compliance-officer role. Academy 2.0 shows each organisation's mandatory courses as a red, amber and
  green matrix you can drill into. Learners are reminded 7, 3 and 1 day before a deadline; managers are alerted 1, 7
  and 14 days after it. Every nudge is logged, so "who was reminded, and when" is a lookup. Exemptions carry a reason
  and an expiry. The reminders and escalations are built and ready, switched on when we choose.

<!-- Sources: today's reminder never sent (filters on a column that does not exist; 0 log rows in the April copy):
ME/docs/visual-evidence/2026-11-16/course-reminder-enrol-date/README.md lines 26-29, 150-152; ME/PROJECT-STATE.md
2026-11-16 entry. No escalation, no compliance-officer role: RB 1.7 (ME/state-cards/sentientia_compliance_report-state.md
line 420; BZ/local search). Matrix, audit trail, exemptions, auditor export: DS sections 3.3, 3.5; MG section 4.6
"Compliance Reporting"; RF section 15. 7/3/1 and 1/7/14: MG line 223. Reminders and escalations built and off: RF
section 15 (their timing waits on an owner decision, PROJECT-STATE 2026-11-17 decision 5; the notes say only "switched on
when we choose", because an open decision is operational detail). -->

---

## Slide 7. Managers see and act on their team

- **Layout:** Title Only
- **Title:** Managers see and act on their team
- **On-slide text (2 x 3 cards, heading and one line each):**
  - **My Team:** progress, compliance, last activity
  - **Wider team:** everyone below them in the reporting line
  - **Approvals inbox:** approve, reject with a reason, delegate
  - **Assign learning:** to many people at once, with due dates
  - **Team export:** a performance summary as a spreadsheet
  - **Digests and nudges:** ○ a monthly summary; an alert at 3+ overdue
  - Line under the grid: *Today: direct reports only; requests approved one at a time.*
  - Benefit box: Managers act on their own team without waiting for an L&D report.
  - Legend: ○ ready, switched on when we choose
- **Visual:** 2 x 3 white cards on the page tint, each with an icon in a #0066A7 circle (people, org chart, inbox,
  arrow-to-person, spreadsheet, bell). The "Today" line in italic #5A6070 beneath, then the benefit box.
- **Speaker notes:** Today a manager sees direct reports only and approves course requests one at a time. In Academy
  2.0 the My Team page shows each person's progress, compliance and last activity, with a drill-down. Managers also
  see their wider team: everyone below them in the reporting line, and nobody else. Requests arrive in one inbox, with
  a reminder if they are left waiting, and can be delegated. As today, managers assign learning themselves; now they
  can assign to many people at once, with due dates, and export a team summary. The monthly digest and the overdue
  alert are built and ready. The benefit: managers act on their own, without asking L&D for a report.

<!-- Sources: today: RB 1.8 (ME/parity-audit-2026-05-15/airpay_manager.md rows 8-10, 18-23, line 146), RB 1.9.
My Team, team performance export: MG section 4.6 "Manager Workspace". Wider team (direct and extended, nobody else):
ME/state-cards/sentientia_compliance_report-state.md line 337 ("Product rule"). Approvals inbox: MG section 3.3.
Allocations: today managers already assign courses, classrooms and programs to direct reports
(ME/parity-audit-2026-05-15/airpay_manager.md row 2); new are bulk allocation and due dates (rows 8, 10, "Net add");
ME/state-cards/sentientia_manager-state.md (allocation sections). Digest and 3+ overdue nudge: MG section 4.5
"Notification Rules Engine" (default state unconfirmed, so shown with the hollow circle). -->

---

## Slide 8. Today's training formats, kept and strengthened

- **Layout:** Title and Content (a two-column table in the content placeholder)
- **Title:** Today's training formats, kept and strengthened
- **On-slide text (table, header row "Today" | "Academy 2.0 adds"):**
  - Learning plans | ○ Paths that branch on quiz scores
  - Programs | ○ A "My programs" page for each learner
  - Classroom | Calendar invites; ○ QR attendance
  - Online exams | Proctoring with human review
  - Feedback forms | NPS scoring; per-question anonymity
  - Recertification | ○ Renewal rules set centrally; every reset logged
  - Benefit box: Every format in use today is kept, and each does more.
  - Legend: ○ ready, switched on when we choose
- **Visual:** a clean table: header row #0066A7 with white text, rows alternating white and #F2F4FB, the left column in
  #5A6070, the right column in bold #1A1A2E with a small plus icon. Benefit box below.
- **Speaker notes:** A reassurance first: every training format in use today is kept. Learning plans, programs,
  classroom training, online exams, feedback forms and annual recertification are all rebuilt, and each is built to
  bring its past records across. Each now does more. A learning path can branch: extra support for someone who struggles, a fast track for
  someone ahead. Classroom sessions now send calendar invites, and learners can mark attendance by scanning a QR code.
  Any exam can be proctored, with a consent and identity step and a person reviewing flags, without an outside vendor.
  Adaptive paths, QR attendance and the central renewal rules are built and ready. One honest gap: today's own forum add-on
  gives way to standard forums.

<!-- Sources: today has all six: RB 1.23 (ME/parity-audit-2026-05-15/airpay_learningpath.md, airpay_programs.md,
airpay_classroom.md, airpay_evaluation.md, airpay_recompletion.md); online exams: RB 1.10. History carried over: DS
section 7 "What happens to BizLMS?" (feature data comes across). Adaptive paths: ME/state-cards/sentientia_adaptive-state.md;
RF section 5. Today's programs already have levels and per-level completion rules
(ME/parity-audit-2026-05-15/airpay_programs.md table rows local_program_levels, local_bcl_cmplt_criteria; RB 1.23),
and today's classroom already promotes the head of the waiting list (airpay_classroom.md row 12, Flow 8), so neither
is listed as an addition. "My programs" page, built and off: RF section 2. Classroom invites: MG section 4.1; RB 1.23.
QR attendance off: RF section 5. Proctoring (consent, identity step, focus and face-count capture, human
reviewer): MG section 4.6 "Proctoring"; ME/state-cards/sentientia_proctoring-state.md line 14. Evaluations: MG section 4.1
"Evaluations"; RF section 6. NPS scoring: today only imitates an NPS question with a 0-10 scale
(ME/parity-audit-2026-05-15/airpay_evaluation.md row 4). Per-question anonymity: same file row 13;
ME/local/sentientia_evaluation/classes/evaluation_manager.php line 838. Today's forms already store anonymous answers
with no user ID (row 13), so the row does not claim anonymity as such; the "sticky" anonymity work fixed a gap in
Academy 2.0's own earlier build (ME/state-cards/sentientia_evaluation-state.md, 2026-09-25). Recertification task off: RF section 7; today's site already re-opens courses and e-mails on a schedule, so the row
claims only rules set centrally and the reset log (ME/parity-audit-2026-05-15/airpay_recompletion.md verdict, Flow 1).
Benefit says "kept", not "nothing is lost": the forum gap below. Forum gap: RB section 2 (ME/parity-audit-2026-05-15/unmapped.md U1). -->

---

## Slide 9. Learning people want to come back to

- **Layout:** Two Content
- **Title:** Learning people want to come back to
- **On-slide text:**
  - Left, heading **Motivation:**
    - Points, levels, badges, daily streaks
    - ○ Live leaderboards with a "hide me" switch
    - ○ Challenges and course ratings
  - Right, heading **○ Live polls in the room:**
    - Polls, quizzes, word clouds, Q&A
    - The audience answers on their phones
    - Results update live on screen
    - No outside subscription
  - Line under both: *Today: points, badges and ratings; no streaks, leaderboards or live polls.*
  - Benefit box: Reasons to come back, and livelier sessions with no paid polling tool.
  - Legend: ○ ready, switched on when we choose
- **Visual:** two white panels; left topped by a trophy icon, right by a phone-and-screen icon, both in #0066A7
  circles. In the right panel a small drawn bar-chart shape suggesting live results (illustration, no data).
- **Speaker notes:** Two kinds of engagement. Motivation: points, levels, badges and daily streaks are part of the
  standard build. Leaderboards update on screen as people finish, and anyone can hide themselves with one click.
  Challenges and course ratings are built and ready. In the room: the Mentimeter or Slido idea, inside our own
  platform. The trainer shows a join code, people answer on their phones and results appear live on the projector, in
  six question types, from quizzes with a top-ten to moderated Q&A. Results stay with the learning record. Live polls
  and leaderboards are built and switched on when we choose. The benefit: reasons to come back, and livelier town halls and
  classrooms with no paid polling tool.

<!-- Sources: today: RB 1.13 (BZ/blocks/achievements/lang/en/block_achievements.php; ME/parity-audit-2026-05-15/airpay_ratings.md;
airpay_notifications.md rows 26-32) and RB 1.14 (no live polling on today's site). Points, levels, streaks on by
default: RF section 8. Leaderboards and "hide me": MG lines 432-437; RF section 8. Challenges, ratings: RF section 8.
Live polls (six question types, phones, live results, export, off by default): MG section 6; DS section 3.4; RF
section 9; ROOT/CLAUDE.md workstream E. No paid service: DS section 7 "What does it cost?". -->

---

## Slide 10. AI drafts, a person approves, spend capped

- **Layout:** Title Only
- **Title:** AI drafts, a person approves, spend capped
- **On-slide text:**
  - Six AI helpers (2 x 3 icon cards), each marked ○:
    - ○ Course drafts from a topic or document
    - ○ Quizzes in English and Hindi
    - ○ Procedure document to e-learning
    - ○ "For You" picks, each with a reason
    - ○ Translation that keeps key terms
    - ○ A copilot that acts only on "yes"
  - Three guardrail chips under the grid: A person approves every draft · Hard spend caps · Course content
    de-identified
  - Status line: *Today: no AI. Academy 2.0: built, in sample mode for now.*
  - Benefit box: Faster first drafts, with a person deciding what learners see.
  - Legend: ○ ready, switched on when we choose
- **Visual:** 2 x 3 compact cards, each with an icon in a #0066A7 circle (pen, question mark, document-to-play,
  star, globe, chat). Below, three rounded chips in #E8F2F9 with a shield icon. Status line in italic #5A6070.
- **Speaker notes:** Today's platform has no AI. Six of Academy 2.0's AI helpers are on this slide; the full list follows. Authors get draft courses and quiz
  questions, Hindi included; a procedure document can become an e-learning course; learners get course picks with a
  one-line reason; content can be translated while terms such as UPI, RBI and KYC stay intact; and the copilot can
  enrol someone or book a seat only after the learner confirms. The guardrails matter most: a person approves every
  draft before a learner sees it, spending has hard daily and monthly caps, and with no cap set the AI stays off.
  Honest status: no live AI call has been made yet; everything runs in sample mode.

<!-- Sources: today no AI: RB 1.15 (BZ plugin listing; Aug deck slide 3). Six helpers: ROOT/CLAUDE.md workstream G
("all 6 consumers routed"); RB 1.15. Authoring studio: ME/state-cards/sentientia_authoring-state.md. Quiz generation in
English and Hindi: MG section 4.4 "AI Quiz Generation". SOP-to-course, sample mode only: MG section 8; RF section 10.
Recommendations with "why this": MG section 4.4 "AI Recommendations". Translation protecting brand and regulatory
terms: MG section 4.4 "AI Content Translation". Copilot acts only on confirmation: ME/state-cards/sentientia_copilot-state.md.
Human review: Aug deck slide 7; DS section 3.4. Caps, unset cap = blocked, never unlimited:
ME/state-cards/sentientia_ai-state.md line 12. De-identified course content: DS section 7 "AI and personal data" (course content only: the learner chat
assistant sends the learner's own details by design, so the chip says "Course content").
Sample mode, no live call: DS section 4 caveat 3. -->

---

## Slide 11. See skill gaps and risks early

- **Layout:** Two Content
- **Title:** See skill gaps and risks early
- **On-slide text:**
  - Left, heading **Skills and talent:**
    - Skills each role needs, and the gap
    - Managers validate skills
    - ○ AI proposes skills from course content
    - ○ Career paths, succession, internal jobs
  - Right, heading **Insight for L&D:**
    - Completion and learning-funnel dashboard
    - ○ At-risk learners flagged, with reasons
    - ○ Training ROI with visible assumptions
    - Today's standard reports kept
  - Line under both: *Today: a skills list without gap analysis; vendor reports.*
  - Benefit box: Training aimed at real skill gaps, and an early warning on learners falling behind.
  - Legend: ○ ready, switched on when we choose
- **Visual:** left panel with a simple radar (spider) shape drawn as an illustration; right panel with a funnel shape
  of four steps (enrolled, started, completed, certified) as an illustration, no numbers.
- **Speaker notes:** Today we keep a skills list in English, with no gap analysis. Academy 2.0 links skills to roles
  and courses: each employee rates themselves against their role and sees the gap, managers endorse or ask for
  evidence, and finishing a course can raise a skill level. AI proposes skills from course content for a reviewer to
  approve and ranks gaps by business impact. HR gets career paths, succession readiness and an internal job board on
  the same data. L&D gets a dashboard from enrolment to certificate, each organisation seeing only its own figures, an
  at-risk flag built on visible signals, and an ROI view. The AI skills, talent, at-risk and ROI views are built and
  ready. Today's reports stay.

<!-- Sources: today: RB 1.12 (ME/parity-audit-2026-05-15/airpay_skills.md; BZ/blocks/myskills), RB 1.17 (LearnerScript
reports). Skills framework, manager validation: MG sections 4.1 "Skills", 3.3. Skills AI and gap ranking:
ME/state-cards/sentientia_skillsai-state.md. Talent mobility: ME/state-cards/sentientia_talent-state.md. Analytics
dashboard and funnel, per-organisation figures: MG section 4.4 "Analytics"; PROJECT-STATE 2026-11-17. At-risk (four
visible signals) and ROI: ME/state-cards/sentientia_analytics_predictive-state.md. Vendor reports kept:
ROOT/tools/packaging/build-standalone.sh lines 65-68 (via RB 1.17). On/off states: RF sections 12, 16. -->

---

## Slide 12. Less admin: the academy follows HR data

- **Layout:** Title Only
- **Title:** Less admin: the academy follows HR data
- **On-slide text:**
  - Four-step flow: **HR file (○ or live Keka sync)** → **Accounts created, moved, closed** → **○ Joiners get
    mandatory courses** → **Reminders and reports**
  - Three cards under the flow:
    - **Switchboard:** each feature on per organisation; every change recorded
    - **Permission log:** who changed what, and when
    - **○ Plugs into our systems:** open interface, automatic account set-up, notices to other systems
  - Line at the foot: *Today: HR file upload; no permission log, no feature switches.*
  - Benefit box: Less manual admin, and a record of every access change.
  - Legend: ○ ready, switched on when we choose
- **Visual:** four rounded process boxes joined by arrows (process flow), #0066A7 numbers 1 to 4; three white cards
  below with icons (toggle, clipboard, plug).
- **Speaker notes:** For L&D the gain is less manual work. The same HR file we use today still works, now with a
  preview before anything changes. Joiners get an account and a welcome e-mail, leavers
  are closed, and reporting lines follow HR. Real-time sync with Keka and automatic enrolment of joiners into mandatory
  courses are built and ready. The switchboard turns each feature on per organisation, with an instant way back.
  Every permission change is logged, so an access review becomes a download. Other systems can connect through an
  open interface, automatic account set-up and automatic notices when someone completes; these are ready too.

<!-- Sources: today: RB 1.19 (ME/parity-audit-2026-05-15/airpay_users.md rows 2-4, 21; whether today's hourly sync runs is
unconfirmed, so the slide says only "HR file upload"), RB 1.24 (no permission audit log). HR import with dry run,
joiner/leaver handling: MG section 4.6 "Users & HRMS Sync"; RF section 14 (today's upload already has error and
statistics pages, RB 1.19, so the notes claim only the preview). Keka real-time sync (the vendor's own spelling, as
on the product's settings screen; the repository mostly writes "KeKa") and auto-enrolment of
joiners, both off: RF section 14 (ME/local/sentientia_integrations/db/feature_flags.php;
ME/local/sentientia_lifecycle/db/feature_flags.php). Switchboard: MG section 7.3; DS section 3.8. Permission log: MG
section 4.6 "Roles & Permissions". Open API, account set-up, signed notices, all off: RF section 19
(ME/state-cards/sentientia_api-state.md). -->

---

## Slide 13. Each organisation kept apart, every action checked

- **Layout:** Comparison
- **Title:** Each organisation kept apart, every action checked
- **On-slide text:**
  - Left heading **Today on airpay.academy:**
    - Access checks missing in add-ons
    - Payments not verified
    - No multi-factor sign-in
    - Most add-ons outside data rights
  - Right heading **Academy 2.0:**
    - Every action checks role and organisation
    - ○ Payments checked before access
    - ○ Single sign-on and multi-factor login
    - Data requests and consent in one hub
    - Audit logs throughout
  - Benefit box: Built to host partners safely, and for India's data-protection law.
  - Legend: ○ ready, switched on when we choose
- **Visual:** comparison panels with a shield icon in a #0066A7 circle on the right heading. No orange on this slide.
- **Speaker notes:** Airpay, Public and ZEEA already share today's site, but our own review of it found access checks
  missing in its add-ons, an incomplete payment check, no multi-factor sign-in, and most add-ons outside data export
  and deletion requests. Academy 2.0 checks the role and the organisation on every action, and anything unclear means
  no access. Payment responses are now checked before access is given, and anything that fails the check is
  refused; payments stay switched off until we choose to sell. Single sign-on with multi-factor login is prepared. Data rights and consent sit in one hub; a few
  older modules are still being completed.

<!-- Sources: today: ME/docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md lines 13-25 ("Result", "What this means";
our own read-only review, not an independent one); payment: ME/docs/security/2026-06-02-airpay-payment-verification-fix.md
and BZ/payment/gateway/airpay/process.php line 95 (via RB section 3); no MFA: RB 1.2
(ME/docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md line 27); 20 of 22 add-ons: RB 1.22 (BZ privacy providers).
Academy 2.0: ME/docs/adr/ADR-031-cross-tenant-authority.md (Decision: anything unclear defaults to nothing); DS section
3.7; payment fail-closed: MG section 4.3 "Payment Gateway", DS caveat 4; SSO/MFA prepared:
ME/docs/security/ENTERPRISE-IDENTITY-PACK.md; privacy hub and "a few gaps being closed": DS section 7 "Is it secure?",
MG section 4.6 "Privacy Hub"; audit logs: RF section 18. Payments built and off until a gateway sandbox test passes:
RF lines 179-182 (so the line carries the hollow circle, as on slide 16). "Checked", not "verified" or
"confirmed": only the payment response's hash is checked, an integrity check rather than a strong authenticity
control; the server-side Order Confirmation (Verify) call is a follow-up, not built
(ME/payment/gateway/airpay/classes/airpay_helper.php lines 243-247; process.php lines 103-106;
ME/docs/security/2026-06-02-airpay-payment-verification-fix.md "Recommended hardening"). Data line claims the hub,
not complete deletion, which is switched off (slide 16 line; DEMO-SCRIPT-2026-11-19.md section 7). Wording rule (plain, nothing exploitable, no
audit counts in the notes, which travel with the file): RB section 3. -->

---

## Slide 14. Everything we built: learners and reach

- **Layout:** Title Only
- **Title:** Everything we built: learners and reach
- **On-slide text (four columns, 34 lines):**
  - **Learner experience**
    - Home screen for each role ↑
    - One navigation everywhere, role switcher
    - My Courses grouped by status ↑
    - Course player with deadline countdown ↑
    - Catalogue with search, filters and trending ↑
    - ○ One-click enrolment in free courses
    - Request a course; manager decides ↑
    - Certificates wall, verifiable by code ↑
    - ○ Certificate templates per organisation
    - First-day onboarding wizard
    - ○ My learning paths and programs pages ↑
    - ○ My feedback-form history ↑
    - ○ Dark mode
    - Accessibility built in ↑
  - **Languages and phones**
    - Hindi on every screen
    - ○ Marathi, Kannada, Swahili prepared*
    - ○ Language switcher in the sidebar
    - Phone-first design ↑
    - ○ Installable phone app
    - ○ Lock-screen phone notifications
    - Interfaces ready for a native app ↑
  - **Reaching learners**
    - Learner picks channels and quiet hours
    - ○ WhatsApp and SMS, with consent
    - Branded e-mails per organisation ↑
    - Weekday learning digest
    - ○ Smart nudges for idle learners
    - ○ Calendar feed: Outlook, Google, Apple
    - Delivery log for every message ↑
  - **Engagement**
    - Points and badges ↑
    - Levels, daily streaks, celebrations
    - ○ Live leaderboards with "hide me"
    - ○ Challenges
    - ○ Course ratings and reviews ↑
    - ○ Live polls, quizzes, word clouds, Q&A
  - Legend at the foot (12pt, #5A6070): ✓ standard build · ○ ready, switched on when we choose · ↑ rebuilds what
    today's site has; no arrow: new to airpay.academy · * native-speaker review first
- **Visual:** four columns on the page tint; group headings 14pt bold #0066A7 with a small icon in a circle; items
  12pt #1A1A2E; ○ items in #5A6070. Columns 1 and 2 may hold two groups each if the builder balances heights.
- **Speaker notes:** This slide and the next two are the complete list: 112 capabilities, all built. Nothing on them
  is only planned. Every line has a tick or a hollow circle: a tick is part of the standard build, a hollow circle
  is built and ready, switched on when we choose. An arrow means today's site has a basic version that we rebuilt
  and extended; a line without an arrow is new to airpay.academy. This first page is the
  learner's side: the home screen, the player, the catalogue and certificates; Hindi on every screen with three more
  languages prepared; a phone-first design and an installable app; reminders on the channel each learner picks; and
  the engagement features. I will not read it out; it is here so nothing is missed.

<!-- Sources: every line: RF sections 2, 3, 8, 14 (status and "vs live" per entry). Markers counted in this spec (script
over the lines on slides 14-16): 112 lines, 71 without an arrow, 41 ↑, 48 ○. Marathi, Kannada, Swahili carry ○: the plugin
packs exist, but the core packs are not installed and no native speaker has read them (DS lines 307-308).
Delivery log ↑: today has an e-mail status page per user and template
(ME/parity-audit-2026-05-15/airpay_notifications.md architecture item 8). Cautious ↑ where the record is unsure:
accessibility (RB 1.1 UNCONFIRMED), native-app interfaces (RB 1.5: today exposes course functions to the Moodle app).
Points and badges ↑: RB 1.13. Quiet hours: ME/local/sentientia_notifications/lang/en/local_sentientia_notifications.php
lines 80-81. Groundwork and planned items left out: native app scaffold, subscriptions (RF sections 3, 4). -->

---

## Slide 15. Everything we built: learning, AI and insight

- **Layout:** Title Only
- **Title:** Everything we built: learning, AI and insight
- **On-slide text (four columns, 38 lines):**
  - **Training formats**
    - Learning paths with audience rules ↑
    - ○ Adaptive paths that branch on scores
    - Multi-level certification programs ↑
    - Classroom: waitlists, invites ↑
    - ○ QR-code attendance
    - ○ Classroom enrolment by audience ↑
    - Trainer dashboard ↑
    - Online exams with windows and invites ↑
    - Proctored exams with human review
    - Feedback forms with NPS analysis ↑
    - ○ Recertification cycles ↑
  - **AI, with people in charge**
    - ○ AI course authoring studio
    - ○ AI quiz writing, English and Hindi
    - ○ Procedure document to e-learning course
    - ○ "For You" picks, each with a reason
    - ○ Translation that keeps key terms
    - ○ Learner copilot, acts only on "yes"
    - ○ Help assistant on every page
    - ○ Hard AI spend caps; every call recorded
    - A person approves every AI draft
    - Course content de-identified for AI
  - **Skills and talent**
    - Skills framework by role ↑
    - My Skills: self-rating, gap chart ↑
    - Manager skill validation
    - ○ AI skills intelligence, gaps ranked
    - ○ Skills-first home recommendations
    - ○ Career paths, succession, job board
  - **Insight**
    - L&D dashboard: KPIs and funnel ↑
    - ○ At-risk learners, with the reasons
    - ○ Training ROI dashboard
    - Saved report builder ↑
    - Today's standard reports kept and repaired ↑
  - **Managers**
    - My Team: progress and compliance ↑
    - Extended-team compliance view
    - Approvals inbox with delegation ↑
    - Assign courses, paths and seats ↑
    - Team performance export
    - ○ Manager digests and nudges
  - Legend at the foot, as on slide 14 (without the asterisk note).
- **Visual:** as slide 14. Suggested columns: (1) Training formats; (2) AI; (3) Skills and talent, then Insight;
  (4) Managers.
- **Speaker notes:** The second page covers how people learn and what we learn from them. The training formats keep
  everything today's site does and add adaptive paths, QR attendance and proctoring. The AI column is mostly hollow
  circles on purpose: all of it is built, and it runs in sample mode until we switch live AI on; the help assistant
  gives sample answers until then. Two lines are always on, because they are rules: a person approves every AI draft,
  and course content is de-identified before it goes to AI. Skills, talent and insight share one set of data, so a
  skill gap, an at-risk flag and a manager's team view tell one story.

<!-- Sources: RF sections 5, 6, 10, 11, 12, 13, 16 (status and "vs live" per entry). Help assistant on by default with
sample answers, marked ○ because its AI answers wait for live AI: RF section 2. AI spend ledger is part of the AI
gateway: RF section 16; ME/state-cards/sentientia_ai-state.md. Cautious ↑ on allocations (unconfirmed
on today's "my team"): RF section 13 and section 23; RB 1.8. No arrow on team export: today has none
(ME/parity-audit-2026-05-15/airpay_manager.md rows 20, 32, "Net add"). No arrow on QR attendance: today's classroom
has none (BZ/local/classroom; RF's "REBUILT+" refers to Sentientia's own earlier tables). Classroom enrolment by
audience ↑: today's classroom enrols by organisation and department and has target audiences
(BZ/local/classroom/enrollusers.php lines 85-111; classes/classroom.php line 1698). Delivery log ↑ on slide 14
balances these, so the counts stay 71 new / 41 rebuilt. Proctoring face matching (groundwork) left out:
ME/state-cards/sentientia_proctoring-state.md line 88. Planned content engine left out: RF section 10. -->

---

## Slide 16. Everything we built: control, trust and platform

- **Layout:** Title Only
- **Title:** Everything we built: control, trust and platform
- **On-slide text (four columns, 40 lines):**
  - **Compliance**
    - Compliance matrix: red, amber, green ↑
    - ○ Reminders before, escalations after
    - Audit trail of every nudge
    - Exemptions with reason and expiry
    - Auditor export, by permission
    - Daily overdue scan and widget
  - **Public storefront**
    - Course shop for public learners ↑
    - Self sign-up with bot protection ↑
    - Cart, orders, refunds, credits ↑
    - ○ GST invoices, credit notes, receipts
  - **L&D administration**
    - HR file import with a preview ↑
    - ○ Mandatory courses for every joiner
    - ○ Real-time HR sync with Keka
    - Organisation structure ↑
    - Course management and deadlines ↑
    - Permission change log
    - Switchboard: features per organisation
    - System health widgets
    - Guides for eight roles
  - **Organisations and brand**
    - Three organisations, kept apart ↑
    - Course sharing between organisations
    - Per-customer white label
    - Employee, public and partner accounts
  - **Trust and privacy**
    - Data-rights and consent hub ↑
    - ○ Deletion that leaves nothing behind
    - Audit logs across the platform
    - ○ Single sign-on and multi-factor login
    - Hardened sign-in and sign-up
    - ○ Payments checked before access ↑
  - **Integrations**
    - ○ Open API for other systems
    - ○ Accounts set up from identity system
    - ○ Automatic notices to other systems
    - ○ Two-way calendar sync
    - ○ Learning-record standards
    - ○ External course library connectors
    - ○ Microsoft Teams announcements
  - **Platform**
    - Current long-term Moodle release
    - Owned design on the Airpay brand book
    - Every new feature switchable
    - Owned, documented code
  - Legend at the foot, as on slide 14 (without the asterisk note).
- **Visual:** as slide 14. Suggested columns: (1) Compliance, then Public storefront; (2) L&D administration, then
  Organisations and brand; (3) Trust and privacy, then Platform; (4) Integrations.
- **Speaker notes:** The last page is about control and trust. Compliance and the storefront sit on the left: the
  storefront and cart are built, and payments and GST invoices are ready for whenever we choose to sell. L&D
  administration runs from the HR file, with a switch per organisation for every feature. Each organisation's data is
  kept apart, courses can be shared between organisations with approval, and another company could carry its own
  brand. Trust and privacy cover data rights, audit logs and sign-in. The integrations are all built and ready, so
  Airpay's other systems can plug in when needed. Underneath is a current Moodle base, our own design and code we own.

<!-- Sources: RF sections 4, 14, 15, 17, 18, 19, 20 (status and "vs live" per entry). Cautious ↑ on the compliance matrix
(today has a compliance report template): RB 1.7. GST invoices new (today issues none): RB 1.18. Payment check
↑ (today has the gateway, unverified): RF section 4; RB section 3; "checked", not "confirmed": only the
response hash is checked, and Airpay's Order Confirmation (Verify) call is not built (slide 13 sources). Course sharing: MG line 224. Eight role guides: MG
section 3; ME/docs/user-guides/. Left out as groundwork: LTI, Microsoft 365 automation, customer registry (RF sections
17, 19); left out as planned: penetration test and certifications (RF section 18). -->

---

## Slide 17. Why owning the platform matters

- **Layout:** Title Only
- **Title:** Why owning the platform matters
- **On-slide text (2 x 3 cards, heading and one line each):**
  - **Ours:** our own code and design; no vendor needed to upgrade it
  - **Current:** the long-term Moodle release, supported to 2029
  - **Open source:** no paid polling or push service
  - **One platform:** every organisation, each kept apart, each branded
  - **Sellable:** another company's brand by configuration, not code
  - **Controlled:** AI spend capped; every new feature behind a switch
- **Visual:** 2 x 3 cards, each with an icon in a #0066A7 circle (key, shield-clock, tag, buildings,
  handshake, dial). One orange emphasis: the word "Ours".
- **Speaker notes:** Why this matters beyond features. Today's academy is a bought-in vendor platform on an older
  Moodle release, two major upgrades behind, with upgrades dependent on the vendor. Academy 2.0 is our own
  product, Sentientia LMS: we own the code, every decision is written down, and it sits on the current long-term
  Moodle release, supported to October 2029. As today, there is no per-seat licence, and live polls and phone
  alerts need no paid outside service. Academy 2.0 is built to carry Airpay,
  Public and ZEEA on one platform, each under its own brand. The same design can carry another company's brand, sold
  as a service rather than seats; that part is early and waits for a real second customer. And AI cannot overspend: no
  cap, no AI.

<!-- Sources: today: RB section 0 (vendor platform, two major upgrades: ME/docs/adr/ADR-033-target-moodle-5.3-lts.md lines
15-25, 99); upgrades dependent on the vendor: ADR-024 lines 11-28; RB 1.25. The maintenance contract is left out:
the record gives only a term "renewing September 2026" (ROOT/docs/email-amc-agreement-request.md line 13), not
whether it was renewed, and contract terms are commercial detail. Owned code: ADR-024 lines 11-28 (Airpay already owns today's bought source, so the card claims our own code and
no vendor dependency, not ownership as such);
ME/docs/SENTIENTIA-PRODUCT-GUIDE.md section 2. Supported to October 2029: DS section 7 "What is the Moodle upgrade path?".
Open source, push and polls need no paid service: DS section 7 "What does it cost?" (licence sentence only).
Today's site has no per-seat licence either (bought source plus a maintenance contract, slide 3 sources), so the
card and notes do not claim one as a saving; not "no paid add-ons": the AI is a paid service.
One platform, own brands: ADR-031; MG sections 5.2, 7.4. Sellable as a service, early stage, waits for a second
customer: DS section 7 "Can we sell it to others?"; ME/state-cards/sentientia_core-state.md. AI caps (no cap = off):
ME/state-cards/sentientia_ai-state.md line 12. Every feature behind a switch: MG section 7.3. -->

---

## Slide 18. Thank you

- **Layout:** End/last Slide
- **Title:** Thank you
- **Visual:** the template end slide as is (its confidentiality notice and www.airpay.co.in stay).
- **Speaker notes:** To close, the story in one breath. Academy 2.0 gives every learner a clear home in their own
  language and on their phone. It gives managers their whole team. It makes compliance training chase itself, with a
  record of every reminder. It puts AI to work with a person in charge and a hard spending cap. And it gives Airpay a
  learning platform it owns, with each organisation kept apart. It is built and not yet live, and the features shown
  with a hollow circle are ready to switch on when we choose. I am happy to take questions on any area.

<!-- Sources: summary of slides 2-17; status wording: DS section 4 caveats 1-3. -->
