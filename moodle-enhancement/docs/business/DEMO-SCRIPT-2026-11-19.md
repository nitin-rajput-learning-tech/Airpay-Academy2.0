# Demo script: Academy 2.0 on Sentientia LMS, C-suite, Thursday 19 November 2026

**Presenter:** Nitin Rajput, Head of L&D. **Audience:** MD and Founder, CTO, CHRO and peers.
**Where:** the UAT server, https://academy2.airpay.ninja (Moodle 5.3), from Nitin's laptop.
**Length:** 35 minutes. A 30-minute cut is in section 2.
**Status:** draft, written 16 November 2026 from the project record. Anything marked **(to confirm on Wednesday)**
could not be confirmed from the files. Section 8 collects them.

**Passwords** are never on screen and never in this file. They are in
`moodle-enhancement/docs/cutover/uat-credentials/UAT-TEST-ACCOUNTS.filled.md` (gitignored). Keep that file open on a
second device, not on the projected screen.

**Open with this line:** "What you will see is Academy 2.0 running on our test server. It is not live. Every person
and course on it is test data. Today we show it, and we ask for three decisions."

---

## 1. Goal, the three asks, and the go-live path

### Goal of the meeting

Leave the room with three decisions and an owner for each, so the go-live path can start. Show just enough of the
product for the decisions to make sense: a learner's day, a manager's view, the L&D admin's control, compliance
evidence, the public storefront, tenant isolation and Hindi.

### Ask 1. Budgets (MD and Founder)

Draft for the room: `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` (draft, not yet approved).
All rupee figures in it are estimates or recorded list prices, not quotes.

| Item | What we ask | Figures |
|---|---|---|
| Security programme | Approve the envelope. Release each line only on vendor quotes, at least two per line. | Envelope ₹80 to 120 lakh. Lines: penetration test (VAPT) by a CERT-In empanelled vendor ₹8-18 lakh; ISO 27001 gap closure ₹8-15 lakh; ISO 27001 certification ₹4-8 lakh plus ₹3-6 lakh tooling; SOC 2 Type I ₹20-40 lakh; 25,000-user load test ₹4-10 lakh; security operations ₹3-8 lakh; reserve ₹5-10 lakh. Total ₹55 to 115 lakh. |
| | Two decisions with it | SOC 2 Type I or Type II. New dates for the milestones that have passed (VAPT was planned for September, the load test for November). |
| AI spend cap | A monthly ceiling for every AI call | Ceiling ₹50,000 a month. First month ₹10,000: AI quiz generation first, plus the content-engine pilot. Raise within the ceiling only from the spend ledger's real figures. At planning volumes, use is about ₹11,000 a month. |
| Live AI key | The Anthropic API key | It goes only into the platform's central AI gateway, which records every call and blocks at the cap. Before the key: the gateway's price fix is deployed, and the learner chat assistant is switched off so that only quiz generation goes live. |

Only if asked, also in the annex: a costed evaluation of a self-hosted AI model in India (no hardware now; the case
is data residency, not saving money), and the content-engine speech line (about ₹10,000 to 20,000 a year for GPU
time, ₹5,000 to 15,000 per language for listening panels).

### Ask 2. IT (CTO)

Draft for the room: `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` (not sent).

1. **The Moodle 5.3 production server.** PHP 8.3 (exactly 8.3: the first upgrade step refuses 8.4, and 5.3 needs 8.3
   or later). Apache 2.4 with mod_rewrite, PHP-FPM, MySQL 8.4 on RDS. UAT's 2 GB servers are for smoke tests only;
   the plan asks for `t3a.medium` and `db.t3.medium` or larger, and IT proposes the sizes. A temporary MySQL 8.0
   database may be needed if Moodle 4.5 (the first upgrade step) refuses MySQL 8.4.
2. **A fresh full live backup.** The database dump and the full moodledata, with the SHA-256 of each archive computed
   on the live server before the copy, sent by a secure channel.
3. **Where the go-live rehearsal runs.** Recommendation: on the new 5.3 server itself, before the production database
   is put there. Its timings set the cutover window, so they must come from the real hardware; MySQL 8.0 to 8.4
   problems show up in the rehearsal, not at cutover; and there is one server to build and secure, not two.

The ask in the room: dates for all three.

### Ask 3. Finance (whoever owns GST)

Draft for the room: `moodle-enhancement/docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md` (not sent).

Sentientia has GST tax invoices, credit notes and receipts built in, switched off. They stay off until Finance
confirms nine points: (1) GSTIN and supplier details; (2) the SAC code and the 18% rate; (3) the invoice and
credit-note number series (16-character limit, prefixes `AIRPAY` and `AIRCN`, calendar or financial year);
(4) e-invoicing (IRN) for B2B; (5) the B2C dynamic QR; (6) the retention period (default 8 years); (7) the signature
on the PDF; (8) credit balances held on the old platform; (9) buyers outside India (ZEEA, Tanzania).

Context: the old shop had five orders in fifteen months (April 2026 copy); the one paid order was Nitin's own ₹10 test.
The ask in the room: name the person who answers.

### The go-live path

Each step is Nitin's call, and each waits for the one before.

1. **UAT sign-off.** The persona walk passes on screen, including the learner check still open since the 5.3
   upgrade.
2. **Rehearsal on a fresh live backup.** Restore it on a separate server, upgrade in two steps (Moodle 4.1.2 to 4.5,
   then 4.5 to 5.3), import the BizLMS data, and prove nothing was lost. To pass: 100% data parity, a per-user
   comparison, a real login with a known password, the mail backlog drained, and a measured upgrade time that fits
   the agreed window. Nothing in the rehearsal touches live.
3. **Cutover with all data.** Same domain: the new server goes live by a DNS switch. The old platform stays frozen
   and untouched as the way back until the first real user writes to the new one.

No date is promised. The rehearsal waits on IT (the server and the backup), not on the budgets.

---

## 2. Run of show (35 minutes)

| Start | Min | Block | Screen / account |
|---|---|---|---|
| 0:00 | 3 | Opening: the goal, the "not live, test data" line, the three asks in one sentence each | UAT landing page, logged out |
| 0:03 | 2 | Where we are: UAT on Moodle 5.3 since 21 October; the go-live path in three steps | Verbal, or deck slide 3 |
| 0:05 | 4 | 3.1 Learner | Priya, `uat_learner_airpay` |
| 0:09 | 3 | 3.2 Manager | Vikram, `uat_manager_airpay` |
| 0:12 | 3 | 3.3 L&D admin | Meera Iyer, `uat_ldadmin_airpay` |
| 0:15 | 4 | 3.4 Trainer (live poll, 2 min) and author (AI authoring in mock mode, 2 min) | Arjun, `uat_trainer_airpay`; Sneha Kulkarni, `uat_author_airpay` |
| 0:19 | 2 | 3.5 Compliance officer | Joseph, `uat_compliance_airpay` |
| 0:21 | 2 | 3.6 Public storefront | Guest, then Deepa, `uat_learner_public` |
| 0:23 | 2 | 3.7 Second tenant: ZEEA isolation | Juma Mwakalinga, `uat_admin_zeea` |
| 0:25 | 1 | 3.8 Hindi switch | Priya's or Meera's dashboard |
| 0:26 | 6 | The asks: budgets, IT, Finance (section 1) | Deck slide 12, or this page |
| 0:32 | 3 | Questions, then close: read back each decision, its owner and its date | |

Take clarifying questions during the walk. Park the rest for 0:32.

**30-minute cut:** drop 3.5 (Meera's compliance report already shows the matrix), learner to 3 minutes, trainer and
author to 3 minutes (live poll only if phones can join, otherwise the author only), opening to 2 minutes.

---

## 3. Persona walk

**Set-up (suggested).**
- One browser window per persona, signed in before the meeting, in walk order. Separate Chrome profiles keep the
  sessions apart. How long a UAT session stays signed in: (to confirm on Wednesday).
- "Log in as" is for site admins only since 30 September; Meera and Juma no longer have it. If a window has signed
  out, sign that persona in from the handout on the second device, or use the site admin's "Log in as".
- Stay at desktop width. Do not show a course page at phone width unless Wednesday's UAT deploy carried theme
  2026111600 (the fix, section 4).
- Read numbers off the screen. Do not quote figures from earlier checks: test data changes.

### 3.1 Learner: Priya, `uat_learner_airpay` (4 min)

Priya is an Area Sales Manager who reports to Vikram.

1. `/my/`: the learner dashboard with progress, points and the "First Step" badge.
2. Sidebar, **My Courses**: six courses. POSH and Airpay Product Suite Fundamentals complete, AML & KYC in progress.
3. Open **Airpay Product Suite Fundamentals**, then the **SOP walkthrough (SCORM)** activity. Let the first slide play
   for a few seconds, then go back.
4. **Certificates** (`/local/sentientia_pages/certificates.php`): two certificates. Open one PDF.
5. **Catalog** (`/local/sentientia_catalog/index.php`): point at the one-click **Enrol** button on a course she is not
   enrolled in.

**Say:** "This is what an employee sees: every course, deadline and certificate in one place, in Airpay's brand.
SCORM packages, the format of our e-learning, play inside the platform."

**Do not click:** the AML & KYC knowledge check (the quiz-to-certificate flow has not been checked on screen on UAT,
and a completion changes the test data for any later run); the Enrol button; the leaderboard opt-out; a course page
at phone width.

### 3.2 Manager: Vikram, `uat_manager_airpay` (3 min)

Vikram is a Cluster Head. Priya, Rahul and Sneha report to him. He has no admin rights.

1. `/my/`: the manager dashboard with team tiles (3 reports).
2. **My Team** (`/local/sentientia_manager/index.php`): Priya, Rahul and Sneha with their progress.
3. The team compliance view: Rahul is overdue on Information Security.
4. **Approvals** (`/local/sentientia_request/approvals.php`): Priya's pending course request. Approve it only if the
   approval worked in Wednesday's walk and a new request was left pending afterwards.

**Say:** "Managers see their own team without asking L&D. Overdue mandatory training shows here. Reminders and
escalations are logged; the e-mails switch on at go-live."

**Do not click:** Allocate (it adds an enrolment); a report's skills page by URL; Team performance, unless it passed
Wednesday's walk (it failed in the 30 September local run and was fixed locally later; on UAT: to confirm on
Wednesday).

### 3.3 L&D admin: Meera Iyer, `uat_ldadmin_airpay` (3 min)

Meera runs learning for the Airpay tenant.

1. `/my/`: Airpay-only tiles and the Compliance Overview widget. There is no System Health widget: that is for site
   admins only.
2. **Manage Users** (`/local/sentientia_users/index.php`): Airpay accounts only. Type a full name, for example
   "rahul deshmukh": one result (checked on UAT on 11 November).
3. **Manage Courses** (`/local/sentientia_courses/index.php`): the six Airpay courses.
4. **Compliance** (`/local/sentientia_compliance_report/index.php`): the three Airpay mandatory courses as columns,
   red, amber and green cells, Rahul overdue. Point at Export; exporting is optional.
5. **Analytics** (`/local/sentientia_analytics/index.php`).

**Say:** "Meera sees Airpay's people and courses, nothing from Public or ZEEA. The compliance matrix exports for an
auditor."

**Do not click:** the Config tab of the compliance report; the user edit pencil or Create user; organisation or
tenant settings; the Switchboard (site admin only; Meera gets "no permission", by design).

### 3.4 Trainer and author (4 min)

**Trainer: Arjun, `uat_trainer_airpay` (2 min).** Arjun is a Sales Trainer Manager.

1. Sidebar, **Live Sessions** (`/local/sentientia_live/trainer/index.php`): "UAT Town-hall poll" is running.
2. Open it and show the join code. The room joins on their phones as guests and answers one multiple-choice slide and
   one word-cloud slide. Answers appear live.

Do step 2 with the room only if Wednesday's check shows a phone can open the join page. Otherwise show the trainer
screen and advance one slide yourself.

**Say:** "Polls, quizzes, word clouds and Q&A are built into the platform. No outside subscription."

**Do not click:** anything that ends, edits or deletes the session.

**Author: Sneha Kulkarni, `uat_author_airpay` (2 min).** Sneha is an Instructional Designer.

1. Sidebar: Authoring Studio, AI Quiz and Skills AI, under a divider.
2. **Authoring Studio** (`/local/sentientia_authoring/index.php`): point at the MOCK MODE banner. Create a draft from
   a topic. Sample content appears at once. Show the review step.
3. **AI Quiz** (`/local/sentientia_aiquiz/generate.php`): generate a mock quiz and show the review screen.

**Say:** "This is mock mode. The screens, the review step and the approvals are real; the text is sample output.
Nothing goes to an AI service until the cap and the key are approved. A person reviews every AI draft before
learners see it."

**Do not click:** Publish (it creates a course on UAT); pushing the quiz into a course. Do not read the sample text
as if it were real AI output.

### 3.5 Compliance officer: Joseph, `uat_compliance_airpay` (2 min)

Joseph is a Compliance Manager.

1. `/my/`: his team list is empty. That is expected: he has no reports.
2. **Compliance** (`/local/sentientia_compliance_report/index.php`): the matrix for the whole Airpay tenant. Rahul
   overdue, Sneha marked exempt.
3. Notification log (`/local/sentientia_notifications/logs.php`): reminder and escalation entries are logged even
   though no e-mail leaves UAT.

**Say:** "For an audit: who was assigned, who is overdue, who was reminded, and when."

**Do not click:** the Config tab.

### 3.6 Public storefront: guest, then Deepa, `uat_learner_public` (2 min)

Deepa is an external learner in the Public tenant.

1. Logged-out window: `https://academy2.airpay.ninja/`. The landing page. **Browse the course catalog**: four Public
   courses, Fintech 101 at ₹499.
2. Deepa's window: the consumer dashboard with **My Cart**. Add Fintech 101 to the cart
   (`/local/sentientia_cart/index.php`) and go to checkout. It stops at a disabled **Payment Coming Soon**.
3. Optional: **Certificates**, her Digital Payments certificate.

**Say:** "The same platform runs a public storefront for external learners, in its own tenant. Payments are not
switched on, on purpose. They open only after the payment fix passes a gateway sandbox test and Finance answers the
GST points."

**Do not click:** any payment button; self-signup (confirmation e-mails are not sent from UAT).

### 3.7 Second tenant, ZEEA: Juma Mwakalinga, `uat_admin_zeea` (2 min)

Juma is the Country L&D Lead for ZEEA in Tanzania.

1. `/my/`: a ZEEA-only dashboard (on 16 September: 2 users, 4 courses, 5 enrolments; read the screen).
2. **Manage Users**: only Fatma and Juma.
3. **Manage Courses**: only the four ZEEA courses.
4. **Compliance**: only Workplace Conduct (Tanzania).
5. Optional: **Browse Airpay Library** (`/local/sentientia_courses/browse_airpay.php`): ZEEA can see Airpay's library
   and request a course.

**Say:** "Same platform, separate organisation. Juma sees two people and four courses, and nothing of Airpay's. The
same design would carry a second customer later; today the isolation we have proven is between our three tenants."

**Do not click:** a cross-tenant request; Create user; Swahili (UAT has no Swahili core language pack yet, and the
Swahili pack still needs a native speaker's read). As the ZEEA learner (Fatma), do not open "Browse Airpay Library":
a learner gets "no permission", by design.

If Juma's first click on Compliance lands on the Airpay library page (seen once on 16 September, not reproduced),
click Compliance again.

### 3.8 Hindi switch (1 min)

1. On Priya's or Meera's dashboard, switch to Hindi: the language item in the user menu, or add `?lang=hi` to the
   address. The shell, headings and dashboard switch.
2. Switch back with `?lang=en`.

Optional: Rahul Deshmukh, `uat_learner2_airpay`, has a Hindi interface and was set up to meet the first-login
onboarding wizard in Hindi. Use him only if Wednesday's check shows the wizard still appears.

**Say:** "One switch changes every screen. Hindi is kept at full parity with English."

**Do not:** switch to Kannada, Marathi or Swahili (no core language packs on UAT). The language menu item was put
behind a switch that is off by default (29 September); whether it shows on UAT: to confirm on Wednesday. `?lang=hi`
worked in the September checks.

**If the CTO asks how features are controlled:** in the site admin window (`admin`, held by Nitin), open the
Switchboard (`/local/sentientia_platform/admin/switchboard.php`): every feature sits behind a switch, off by
default, per customer and per tenant. View only. Do not change a switch.

---

## 4. Caveats to say out loud

1. "This is not live. airpay.academy still runs the old platform, BizLMS on Moodle 4.1.2, for our 3,500+ learners.
   We have not touched it."
2. "Everything here is test data. UAT holds test personas and sample courses. Our scale and migration figures come
   from a development copy that holds a test import of a production backup. No real learner has used Sentientia."
3. "The AI features run in mock mode. No live AI call has ever been made. They go live only with the spend cap and
   the key."
4. "Payments show 'coming soon'. Selling stays off at go-live until the payment fix passes a sandbox test, and GST
   invoices stay off until Finance answers."
5. "What UAT does not show:"
   - **E-mail, SMS and WhatsApp:** switched off on UAT. Reminders and escalations are logged in the platform. Mail is
     connected at go-live.
   - **Single sign-on and MFA:** the configuration is ready; it waits for IT's Entra app registration. Today, manual
     login.
   - **Real load:** UAT servers are smoke-test size (2 GB). Only a 20-user baseline has run there. The 25,000-user
     load test is in the security budget.
   - **The BizLMS data import:** it runs in the rehearsal. So far it is proven on the development copy only (the April
     dry run was clean).
   - **Kannada, Marathi, Swahili:** the packs exist, are not installed on UAT and have not been read by a native
     speaker.
   - **Installable phone app (PWA) and push notifications:** built, switched off. **Native app:** funded, scaffold
     only.
   - **GST invoices:** built, switched off. **Dark mode:** off.
   - **The content engine** (SOP to multilingual course): a mock pilot and clickable prototypes, outside UAT.
6. Known rough edges, if they appear: some catalog cards show 0 or 1 enrolments (test data). Two layout defects
   (on a phone the course index covered most of the course page; at 1280 px the floating help button covered the end
   of the footer copyright line) are fixed in theme 2026111600 (development copy, 16 November). They are fixed on UAT
   only if Wednesday's deploy carries that theme version; check on Wednesday which one UAT runs.

---

## 5. Fallbacks

**VPN and tunnel.**
- The tunnel is only for server deploys. The browser demo does not use it.
- Every logged-in browser check of UAT on record, from 16 September to 11 November, was done with the VPN off.
  Whether UAT loads over the VPN in the meeting room: (to confirm on Wednesday).
- If the page does not load: switch the VPN off (or on) once and reload. If it still fails, go to the deck. Spend no
  more than one minute.

**If UAT is down: the deck.** `moodle-enhancement/docs/business/AIRPAY-ACADEMY-2.0-UAT-SHOWCASE-DECK-2026-09-21.pptx`
(slide 12: the asks; slide 13: the demo path). It is from September. Correct these out loud:
- UAT moved to Moodle 5.3 on 21 October; the deck says 5.2.
- The security annex lines total ₹55 to 115 lakh; the deck prints ₹52 to 109 lakh.
- The rehearsal is now recommended on the new 5.3 server, not on UAT.
- Production needs a new 5.3 server (PHP 8.3, MySQL 8.4), not an in-place upgrade of today's server.
- The three open security items on slide 7 are the September status: (to confirm on Wednesday).

**Screenshots from UAT** (test personas only, safe to show):
- `moodle-enhancement/docs/visual-evidence/2026-10-21/uat/`: login and front page on Moodle 5.3, desktop and phone.
- `moodle-enhancement/docs/visual-evidence/2026-10-30/uat-my-check/`: Meera's dashboard and the blocks drawer.
- `moodle-enhancement/docs/visual-evidence/2026-11-11/uat-fixes-check/`: Manage Users full-name search, the dashboard
  calendar, notification preferences.

**Screenshots from the development copy** (desktop and phone pairs, captured 29 and 30 September on Moodle 5.1,
before later fixes). Use only these, which passed:
- `moodle-enhancement/docs/visual-evidence/2026-09-30/personas/`: 003 learner dashboard, 009 my courses,
  010 certificates, 015 Hindi dashboard, 020 manager dashboard, 021 my team, 024 manager compliance, 034 trainer live
  sessions, 045 authoring studio, 054 L&D admin dashboard, 057 manage courses, 069 compliance report, 077 public
  catalog, 078 priced course, 095 ZEEA learner dashboard, 096 ZEEA catalog, 098 ZEEA search for an Airpay course
  (finds nothing).
- `moodle-enhancement/docs/visual-evidence/2026-09-29/`: 42 ZEEA admin dashboard, 37 ZEEA admin courses, 41 ZEEA
  compliance, 57 guest catalog, 58 guest login.
- `moodle-enhancement/docs/visual-evidence/2026-10-22/scorm-resume/`: a SCORM course resuming in the Moodle player.
- **Caution:** the development copy holds a test import of a production backup. Team lists, reports and the
  compliance matrix can show real employees' names. Look at each image before Thursday and do not project one that
  does.

**AI authoring, if asked for more:** `moodle-enhancement/docs/business/SENTIENTIA-CONTENT-ENGINE-DECK-2026-10-15.pptx`
and the clickable prototypes `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/prototypes/index.html` (open
in a browser, no network needed).

**If one page fails during the walk:** say "this page is not loading; this is what it shows", open the matching
screenshot, and move to the next persona. Do not troubleshoot in the room. Note the URL and the time.

**If phones cannot join the poll:** show the trainer screen and advance a slide yourself.

---

## 6. Wednesday 18 November: pre-demo checklist

### A. UAT deploy (Nitin's call)

What is waiting since the 11 November deploy:
- Theme 2026111200: no empty drawer in course edit mode; the footer scrolls with the page. Visible.
- Authoring 2026111200: the course-document schema, switched off. Not visible.
- Audit events: cart 1.2.2, proctoring 1.0.6, platform 2026111300. Not visible on the walk.
- AI gateway 2026111300: the price fix. Not visible. It must reach UAT before any live key.
- Anything committed 14 to 18 November: (to confirm on Wednesday; none in git on the morning of 16 November).
- Not included: the privacy erasure work, which is still in progress and not committed.

Nothing in this list is needed for the demo itself. Deploy only if you want UAT on the same code as the repository
before Thursday. If yes: deploy in the morning, dry run first, then do the whole walk (D) on the deployed code. Every
UAT deploy so far kept a backup on the server first. If the walk fails and the fix is not quick, go back to the
11 November code and demo that.

### B. Network

- Open UAT with the VPN setting you will use in the room.
- On a phone, on office Wi-Fi and on mobile data, open the live-poll join page as a guest. If IT has put an
  office-IP allowlist on UAT, phones on mobile data cannot reach it (to confirm on Wednesday).

### C. Demo data, per persona

Look, do not change. Note what you see.

| Persona | Check | If it is wrong |
|---|---|---|
| Priya | Six courses; POSH and Product Suite complete; AML & KYC in progress; two certificates; the SCORM activity opens and plays | Show certificates and skip SCORM; use the `2026-10-22/scorm-resume` screenshot |
| Vikram | Three reports; Rahul overdue on Information Security; one pending request from Priya | After the walk, as Priya, raise one request (`/local/sentientia_request/index.php`, reason of 20+ characters) and leave it pending |
| Meera | Airpay-only tiles; the Compliance Overview widget renders; Rahul overdue in the report | |
| Rahul overdue | The 16 September dashboard widget read "0 overdue" while the persona scripts expect Rahul overdue in the report. Check both. | If they differ, show only the report, or be ready to explain |
| Arjun | "UAT Town-hall poll" is running; a phone can join | Show the trainer screen only |
| Sneha | Authoring Studio, AI Quiz and Skills AI in the sidebar; MOCK MODE banner on each | |
| Joseph | Matrix renders; Rahul overdue; Sneha exempt; the notification log has entries | Drop 3.5 |
| Deepa | Fintech 101 (₹499) goes into the cart; checkout shows "Payment Coming Soon" | Stop at the catalog price |
| Juma | ZEEA-only dashboard; 2 users; 4 courses; one compliance course | Use the `2026-09-29` ZEEA screenshots |
| Rahul | Does the Hindi onboarding wizard still appear at login? Do not complete it. | Show Hindi on Priya's dashboard only |
| Hindi | `?lang=hi` on a dashboard; is the language menu item visible? | Use `?lang=hi` |

During the rehearsal walk, do not take Priya's AML & KYC quiz, do not approve the request, and do not check out.
Each one changes the data you need on Thursday.

### D. Persona walk

- Walk 3.1 to 3.8 in order, with a timer, signed in as each persona.
- Since the 5.3 upgrade on 21 October, only the L&D admin view (Meera) has been checked on screen. This walk is also
  the logged-in learner check still open since then (decision C4 of 12 November).
- Three flows were never confirmed on screen with interaction: the learner quiz-to-certificate flow, the manager
  approval, and the live poll with an audience. Try the last two once; then reset as in C.
- Anything broken: decide the same afternoon whether it is fixed overnight, the stop is skipped, or a screenshot is
  shown.

### E. Screenshots for the deck

- While walking, take desktop screenshots of each stop: each persona's dashboard, the compliance matrix, the MOCK MODE
  banner, the cart at "Payment Coming Soon", the ZEEA admin's user and course lists, the Hindi dashboard.
- Save them under `moodle-enhancement/docs/visual-evidence/2026-11-18/` with a short README: what, when, which
  account. UAT holds test personas only, so these are safe to project.
- Use them in the deck in place of the September captures (your call).

### F. Room

- Thursday morning: sign in each persona window; check that the session timeout leaves enough time.
- Handout on the second device. Notifications off. Browser zoom so the sidebar and the page fit the projector.

---

## 7. Likely questions, short answers

**What does it cost?**
Three lines are open, all estimates, not quotes. AI: a ceiling of ₹50,000 a month, starting at ₹10,000; about ₹11,000
a month at planning volumes. Security and certification: a ₹80 to 120 lakh envelope, released line by line on quotes
(lines total ₹55 to 115 lakh). Content-engine speech: about ₹10,000 to 20,000 a year, plus ₹5,000 to 15,000 per
language for listening panels. Server running costs: IT proposes the sizes; the figure is not in our record. There
is no per-seat licence: the Moodle core is open source (GPL v3), and push notifications and live polls use no paid
outside service.

**When does it go live?**
No date until the rehearsal on a live backup passes. The path is UAT sign-off, the rehearsal, then cutover. The
rehearsal waits on IT for the 5.3 server and the full backup, and its timings set the maintenance window. That is
why we ask IT for dates today.

**Is it secure? What about data privacy and DPDP?**
- The UAT security audit of 3 September gave a conditional pass for UAT and blocked production until three items
  close: the payment-hash fix (needs one gateway sandbox test), reCAPTCHA production keys (IT), and one item that
  closes with the payment fix. Their status today: (to confirm on Wednesday).
- Fixed and verified on UAT: cross-tenant self-enrolment, account enumeration, rate limits, security headers, an
  app-scoped database user.
- No independent penetration test or certification yet. That is the security budget, VAPT first.
- DPDP: plugins declare the personal data they hold and support data export and erasure. Consent for WhatsApp and SMS
  is logged. There is a DPDP statement page. A Moodle privacy test on 13 November found a few gaps in older plugins;
  they are being closed.
- Erasure: a rehearsal on the development copy on 13 November (nothing was sent) found a Moodle core bug: a deletion
  request can show "Deleted" while some data stays behind. Rule: no deletion request is approved on production until
  the fix is in place and the DPO's retention decision is recorded. The fix's status: (to confirm on Wednesday).
- AI and personal data: course content sent for generation is de-identified. The learner chat assistant sends the
  learner's own name, role and courses by design, which is why a self-hosted option in India is proposed for
  evaluation. Whether the AI provider can process data in India is not confirmed; it is the first question for
  counsel.

**Will it handle our 3,500 users?**
On data volume, the evidence is good: it was hardened against a full production copy (2,871 accounts, 411 courses,
3 tenants), and the local migration rehearsal matched 32,248 completions, 11,415 certificates and 8,687 quiz attempts
row for row. On concurrent load, it is not proven yet. UAT is smoke-test size; a 20-user baseline there had 0 errors
and pages under 1 second (p95). Production sizing is IT's to propose, and the 25,000-user load test is in the
security budget.

**Does it work on mobile?**
The web app works in a phone browser; UI changes are checked at desktop and phone width. The installable web app
(PWA) with push notifications is built and switched off (decision of 29 September). A native app is funded (August
memo) and only scaffolded. 36 web-service endpoints are ready for an app (22 read, 14 learner write); admin stays
desktop-only. The course index used to cover most of the course page on a phone; since theme 2026111600 it starts
closed behind a "Course Content" button.

**What is the Moodle upgrade path?**
The target is Moodle 5.3 LTS, with security support to October 2029. UAT has run 5.3 since 21 October; that upgrade
took about 3 minutes with 0 data rows lost. Live is on 4.1.2, so the rehearsal and the cutover go in two steps:
4.1.2 to 4.5, then 4.5 to 5.3. We cut over on the latest 5.3.x point release. Moodle 5.2 is the rehearsed fallback.
Later upgrades follow the same route: a checksummed package, a rehearsal, then the server.

**What happens to BizLMS?**
Nothing until cutover. It gets no patches (decision of 29 September: replace it, do not hotfix it). At cutover its
data comes across: core Moodle data through the upgrade, and the BizLMS feature data (classrooms, programs, online
exams, learning plans, evaluations, requests, skills, cart orders and more) through 19 importers, which ran clean in
a dry run on the April copy. After the DNS switch, the old server stays frozen and untouched as the way back until
the first real user writes to the new one. When it is switched off for good: (to confirm on Wednesday).

**Who supports it?**
A very small team built it. Every decision, runbook and component is written down (more than 30 architecture
decision records), so another engineer can pick it up. The August deck asked for a support-engineer hire; its status:
(to confirm on Wednesday).

**Can we sell it to others?**
The design supports more than one customer; a fictional second customer was shown on the development copy in August.
That layer is early-stage, and productisation waits for a real second customer. The model is service, not seats: the
core stays GPL v3, and what is sold is hosting, implementation and support.

---

## 8. To confirm on Wednesday

1. UAT loads over the VPN in the meeting room (all logged-in checks so far: VPN off).
2. Phones reach the live-poll join page on office Wi-Fi and mobile data; the town-hall poll is still running; whether
   an office-IP allowlist is on UAT.
3. The test data per persona (section 6C), including Rahul overdue in both the widget and the report.
4. A pending request from Priya for Vikram.
5. Rahul's Hindi onboarding wizard still appears.
6. The language menu item on UAT (otherwise `?lang=hi`).
7. The SCORM activity plays on UAT since the 5.3 upgrade.
8. Manager Team performance on UAT.
9. How long a UAT session stays signed in.
10. Status of the September security items (payment-hash fix, reCAPTCHA keys, the item that rides on the payment fix).
11. Status of the erasure fix, and whether anything from 14 to 18 November is committed for the deploy.
12. Whether UAT holds any imported production records. The UAT handout says UAT holds test data only and the
    migration plan calls UAT a fresh install, while the same plan's status note says all user data on UAT and local is
    a test import. Until checked, say only "test data".
13. Whether the IT and Finance e-mails go out before Thursday (both are unsent drafts).
14. Any date IT has already given for the 5.3 server or the backup.
15. When BizLMS is switched off for good after cutover; the support-engineer ask.

---

## Sources

- `moodle-enhancement/docs/cutover/UAT-DEMO-READINESS-2026-09-04.md` (personas, known rough edges, flows not yet
  confirmed, not-demonstrable areas)
- `moodle-enhancement/docs/cutover/uat-credentials/UAT-TEST-ACCOUNTS.md` and `.filled.md` (gitignored: persona scripts
  and click paths; passwords only in the `.filled` file)
- `moodle-enhancement/docs/SENTIENTIA-PRODUCT-GUIDE.md` (scale, languages, mobile endpoints, licence, 5.3 support date)
- `moodle-enhancement/docs/business/deck-src/build_uat_showcase_deck_2026-09-21.js` (slides and speaker notes of the
  September deck)
- `moodle-enhancement/docs/business/SENTIENTIA-CONTENT-ENGINE-CSUITE-BRIEF-2026-10-15.md` (content engine, residency)
- `moodle-enhancement/PROJECT-STATE.md`, entries 2026-09-29 to 2026-11-13 (5.3 upgrade, UAT checks, Log in as, PWA,
  open items, the 11-12/11-13 commits)
- `moodle-enhancement/docs/cutover/NITIN-DECISIONS-2026-11-12.md` (section C: C1 budgets, C2 Finance, C3 IT, C4
  learner check)
- `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md`
- `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md`
- `moodle-enhancement/docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md`
- `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` (erasure gate) and
  `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` (rehearsal pass criteria, two-step upgrade,
  DNS switch and the way back)
- `moodle-enhancement/docs/cutover/PENDING-TASK-PLAN-2026-09-21.md` (September security items)
- Visual evidence READMEs: `2026-09-16/uat-screen-check-2026090804`, `2026-09-29`, `2026-09-30/personas`,
  `2026-10-21/uat`, `2026-10-30/uat-my-check`, `2026-11-11/uat-fixes-check`
