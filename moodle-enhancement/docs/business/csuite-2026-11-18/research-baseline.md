# Research 2: baseline. What live airpay.academy offers today, and what Academy 2.0 adds

Prepared 2026-11-17 for the rebuilt C-suite deck (folder `csuite-2026-11-18`). Purpose: give the deck a defensible
"today" column. Facts come only from the repository record and from the production code snapshot that the record
itself cites. Nothing was run against live, UAT or any database.

Path roots used below:

- **ME** = `D:/Claude Local/airpay-ld-os/moodle-enhancement/`
- **BZ** = `D:/Claude Local/Moodle Backup/01-production-codebase/html/` (the production code snapshot; the record
  verifies live against it: ME `docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` section 0). Where a line says
  "BZ listing", the fact was read from the snapshot's folders for this note.

Rules carried into this note:

- Academy 2.0 (Sentientia) is **not live**. "Built" never means "in use". Built-but-switched-off items are marked OFF.
- "Live today" means the BizLMS platform at airpay.academy. Two older documents describe Sentientia features as if they
  run on live today: ME `docs/SENTIENTIA-PRODUCT-GUIDE.md` section 3 ("These are running capabilities") and ME
  `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` ("automated learner reminders, and automatic manager escalations
  run daily for 3,500+ learners"). The migration plan section 0 says live is "Moodle 4.1.2 + the eAbyas BizLMS plugins,
  theme epsilon, **no Sentientia plugin**". This note follows the migration plan. Do not reuse those two passages.
- The "Academy 2.0" lines are cross-references only. The other research agent owns the feature list.

---

## 0. The live platform in five lines

| Fact | Source |
|---|---|
| Live is BizLMS, a product of the vendor eAbyas, on Moodle 4.1.2+ (a build dated April 2023), theme "epsilon". It has 22 BizLMS add-on plugins, BizLMS dashboard blocks and 3 BizLMS enrolment methods. No Sentientia code is on it. | ME `docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` section 0; ME `docs/cutover/STAGE-B-CAPABILITY-INVENTORY-2026-09-30.md` (component list); BZ listing of `local/`, `blocks/`, `theme/` |
| It serves 3,500+ learners in three organisations: Airpay internal, Public, ZEEA Mafunzo (Tanzania). The April 2026 copy holds 2,871 accounts and 411 courses. | ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 4, caveat 1; ME `docs/SENTIENTIA-PRODUCT-GUIDE.md` section 4 |
| Airpay bought the BizLMS source code (GPLv3). Until now the vendor maintained it under an annual maintenance contract. | ME `docs/adr/ADR-024-absorb-and-debrand-bizlms.md` lines 11-28; ME `docs/email-amc-agreement-request.md` (AMC, "renewing September 2026"); ME `docs/business/SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx` slide 3 |
| The standing decision is to replace live, not patch it. It gets no hotfixes. At cutover all of its data comes across, including the BizLMS feature data. | ME `PROJECT-STATE.md` 2026-09-29 entry ("Direction"); ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 7 ("What happens to BizLMS?") |
| Platform age: live must take two major upgrades (4.1.2 to 4.5, then 4.5 to 5.3) to reach the Academy 2.0 base. Moodle 5.3 LTS has security support until 1 October 2029. **UNCONFIRMED:** the record never states 4.1's own end-of-support date. | ME `docs/adr/ADR-033-target-moodle-5.3-lts.md` lines 15-25 and 99; ME migration plan section 0 |

Suggested plain line: *"Today's academy runs on a bought-in vendor platform, two major versions behind. Academy 2.0
is our own platform on the current long-term Moodle release, supported to October 2029."*

---

## 1. Feature areas: live today, then the Academy 2.0 improvement

Each area has one "live today" line with sources, then a cross-reference to what Academy 2.0 brings.

### 1.1 Look, feel and navigation
- **Live today:** the vendor theme "epsilon", with a logo and one of 7 colour schemes per organisation. Airpay owns
  none of its design. Sources: ME `parity-audit-2026-05-15/airpay_org.md` row 6 (7 schemes); BZ `theme/epsilon`;
  ME `docs/audits/EABYAS-EPSILON-INDEPENDENCE-AUDIT-2026-06-02.md`. **UNCONFIRMED:** whether live has dark mode, and
  how accessible it is (the record has no accessibility review of live).
- **Academy 2.0:** its own design system to the 2026 brand book. It adds a role-aware app shell, dark mode and
  accessibility checks. The record lists it as on by default at go-live. Sources: ME
  `docs/business/SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx` slides 5 and 19; ME `docs/SENTIENTIA-PRODUCT-GUIDE.md`
  section 4.

### 1.2 Sign-in and identity
- **Live today:** username and password plus a vendor one-time-password form. No multi-factor sign-in: the record says
  MFA is "not currently enabled on Airpay Academy production", and the live code has no MFA tool. Sources: ME
  `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` line 27; ME `docs/security/SOC-2-TYPE-II-READINESS.md` row CC6.6;
  ME `docs/audits/EABYAS-EPSILON-INDEPENDENCE-AUDIT-2026-06-02.md` ("custom OTP form"); BZ listing of `admin/tool`
  (no `mfa`).
- **Academy 2.0:** the Microsoft single sign-on and MFA configuration is ready. It waits for IT's Entra app
  registration, so it is NOT yet on. Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 4, caveat 5; ME
  `docs/security/ENTERPRISE-IDENTITY-PACK.md`.

### 1.3 Learner home and course discovery
- **Live today:** a vendor dashboard block with tabs (e-learning, classrooms, programs, learning plans,
  certifications, online tests, feedback). It also has "trending", "suggested courses" and "my achievements" blocks, a
  course catalog with a single search box across course types, and course detail pages. Sources: ME
  `parity-audit-2026-05-15/blocks.md` B1-B2; ME `parity-audit-2026-05-15/unmapped.md` U4; BZ `blocks/achievements`
  lang file. **UNCONFIRMED:** how "suggested courses" chooses courses.
- **Academy 2.0:** one dashboard per role (learner, manager, L&D admin, site admin). It shows deadlines,
  continue-learning, a Netflix-style catalog and a redesigned course player. Sources: ME
  `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` line 66; ME `docs/SENTIENTIA-PRODUCT-GUIDE.md` section 5.

### 1.4 Languages
- **Live today:** the BizLMS add-ons carry English and Spanish text only. The cart also has German, and the skills
  add-on has English only. The August deck called live "English-only in practice". Sources: ME
  `parity-audit-2026-05-15/airpay_roles.md` row 18; `airpay_notifications.md` row 65; `airpay_cart.md`
  ("Multi-language"); `airpay_skills.md` ("Languages: en only"); August deck slide 3. **UNCONFIRMED:** whether a Hindi
  core language pack is installed on live.
- **Academy 2.0:** Hindi is kept at full parity with English. Kannada, Marathi and Swahili are partial: 12 of 47
  plugins, and UAT has no core packs for them. The language menu sits behind a switch that is off by default. The
  e-mail templates are English only today. Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.8; ME
  `docs/cutover/PENDING-TASK-PLAN-2026-09-21.md` item 4.12; ME `docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md` line 543.

### 1.5 Mobile
- **Live today:** works in a phone browser. The production database has Moodle's mobile web service ON and mobile
  push OFF (checked on the production DB, 2026-05-11). BizLMS exposes 4 course functions to the Moodle app. No
  installable app or push channel of Airpay's own. Sources: ME `docs/MOBILE-PUSH-SETUP.md` section 1; ME
  `parity-audit-2026-05-15/airpay_courses.md` row 23. **UNCONFIRMED:** whether anyone uses the generic Moodle App
  with live.
- **Academy 2.0:** an installable web app (PWA) with push notifications that runs on Airpay's own push service, with
  no per-message fees. It is built and OFF by owner decision. A native app is funded and only scaffolded. Sources: ME
  `docs/business/DEMO-SCRIPT-2026-11-19.md` section 7 ("Does it work on mobile?"); ME `PROJECT-STATE.md` 2026-09-29
  entry.

### 1.6 Notifications and reach
- **Live today:** **e-mail only**, triggered by events. About 76 types of admin-written templates cover enrolment,
  classroom, programs, exams and requests, with manager copy and certificate attachment. Users cannot opt out or
  choose a channel. There is no SMS, WhatsApp or push. Source: ME `parity-audit-2026-05-15/airpay_notifications.md`
  (architecture summary items 2, 6 and 7; rows 43-49).
- **Academy 2.0:** e-mail, in-app, web push, WhatsApp and SMS, with India's DLT templates and consent capture.
  Learners get channel preferences and quiet hours, and there are nudge rules (inactive learner, manager digest).
  WhatsApp, SMS and e-mail are switched off on UAT, and no live WhatsApp send has been approved. Sources: ME
  `docs/SENTIENTIA-PRODUCT-GUIDE.md` sections 3 and 5 ("Reach"); ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 4,
  caveat 5.
- **Watch-out (do not claim full parity):** three BizLMS e-mails have no Academy 2.0 rule yet: course enrolment,
  learning-path enrolment, and the manager copy of course completion. Source: ME
  `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 11 (COMMS-N7, gap G10).

### 1.7 Mandatory (compliance) training: deadlines, reminders, escalation
- **Live today:** BizLMS has a course-deadline reminder task, but **it has never sent an e-mail on live**. It filters
  on a column that does not exist, and the April copy holds 0 log rows for it. No add-on on live escalates overdue
  training to managers: the word "escalat" appears in none of them. BizLMS has no compliance-officer role. Its only
  compliance view is a report template in the vendor reporting tool. Sources: ME
  `docs/visual-evidence/2026-11-16/course-reminder-enrol-date/README.md` lines 26-29 and 150-152; ME
  `state-cards/sentientia_compliance_report-state.md` line 420; BZ `blocks/learnerscript/backup/Compliance
  certifications.xml`; BZ search of `local/`.
- **Academy 2.0:** a compliance matrix per organisation (red, amber, green; exempt; Excel export for auditors) and a
  view for compliance officers. Reminders go out before the deadline and escalations to the manager after it, and
  every nudge is logged for audit. The reminder and escalation tasks are built and **OFF**: their timing and mail
  volume wait for the owner's decision. Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md` sections 3.3 and 3.5;
  ME `PROJECT-STATE.md` 2026-11-16 entry (local_sentientia_courses 2026111600).
- Suggested plain line: *"Today, deadline reminders exist on paper but have never fired. Academy 2.0 shows every
  overdue person, reminds them, escalates to their manager and keeps the audit trail."*

### 1.8 Managers and teams
- **Live today:** "My Team" covers direct reports only. Course requests are approved one at a time. There is no team
  CSV export, no team-performance page and no inactive-learner flag. Source: ME
  `parity-audit-2026-05-15/airpay_manager.md` rows 8-10, 18-23 and line 146.
- **Academy 2.0:** managers see their wider team as well as direct reports (L2 managers see direct and extended
  teams). They get team progress and compliance, bulk allocation with due dates, bulk approvals, exports and nudges.
  Sources: same audit ("Net add" rows); ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.2; ME
  `PROJECT-STATE.md` line 1612; ME `state-cards/sentientia_compliance_report-state.md` line 337.

### 1.9 Course requests and approvals
- **Live today:** approve or deny, with no deadline (SLA), no escalation, no auto-expiry and no requester cancel.
  Source: ME `parity-audit-2026-05-15/airpay_request.md` rows 12-14, 28, 34-37.
- **Academy 2.0:** an approval queue with SLA nudges, escalation, auto-expiry, cancel, bulk decide and delegation.
  Sources: same audit ("NEW" rows); ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` section 3.3.

### 1.10 Exams and proctoring
- **Live today:** "online exams" are BizLMS courses holding a Moodle quiz. Only Moodle's standard quiz rules apply
  (password, IP, time limit, Safe Exam Browser). There is no proctoring or identity check. Sources: ME
  `parity-audit-2026-05-15/airpay_exams.md` row 2; BZ listing of `mod/quiz/accessrule`.
- **Academy 2.0:** proctoring-capable exams with a consent and identity step, plus open and close windows. Sources:
  ME `docs/SENTIENTIA-PRODUCT-GUIDE.md` sections 3 and 5; ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md`
  section 3.1, step 4.

### 1.11 Certificates
- **Live today:** certificates are issued with Moodle's certificate tool. The production data holds 11,415 issues on
  9 templates. Learners see a "My Achievements" block. Sources: ME `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md`
  line 138; ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` section 2 ("clone evidence"); BZ
  `blocks/achievements`.
- **Academy 2.0:** a certificates wall and a certificate health monitor, with a verification ID anyone can check
  online. Annual renewal cycles stay. Sources: August deck slide 19; ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md`
  section 3.1, step 5. *(Live already issues certificates: present this as an improvement, not a new feature.)*

### 1.12 Skills
- **Live today:** a skills list (skills, categories, levels, a matrix, "interested skills") and a "My Skills" block,
  English only. There is no gap analysis. Source: ME `parity-audit-2026-05-15/airpay_skills.md` (verdict and source
  paths); BZ `blocks/myskills`.
- **Academy 2.0:** gap analysis against the role, a radar chart, team heat maps, course suggestions from real gaps
  and manager endorsement. Skills AI runs in mock mode. Sources: same audit verdict; ME
  `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` line 83; ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md`
  section 3.3.

### 1.13 Engagement and gamification
- **Live today:** badges, points and certificates appear in a "My Achievements" block. Courses take ratings,
  comments and likes, and a "trending" block exists. There are no streaks, levels or leaderboards. Sources: BZ
  `blocks/achievements/lang/en/block_achievements.php`; ME `parity-audit-2026-05-15/airpay_ratings.md` (verdict); ME
  `parity-audit-2026-05-15/airpay_notifications.md` rows 26-32 (streaks etc. "NEW").
- **Academy 2.0:** points, levels, streaks, real-time leaderboards with opt-out, challenges and "we miss you"
  campaigns. Sources: ME `docs/SENTIENTIA-PRODUCT-GUIDE.md` section 5 ("Engage & retain").

### 1.14 Live classroom engagement (polls, quizzes, Q&A)
- **Live today:** no live-polling tool. Live has only Moodle's standard activities, and none of its add-ons does
  real-time polling. The August deck says "no real-time engagement". Sources: BZ listing of `mod/` and `local/`;
  August deck slide 3.
- **Academy 2.0:** built-in polls, quizzes, word clouds, ratings and Q&A. The audience answers on their phones and
  results show live. No outside subscription. Switched off by default. Sources: ME
  `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.4; root `CLAUDE.md` workstream E.

### 1.15 AI
- **Live today:** none. Live's plugin list has no AI component, and the August deck says "No AI". Sources: BZ listing;
  August deck slide 3.
- **Academy 2.0:** AI quiz generation, course authoring, skills intelligence, recommendations, translation and a
  learner assistant. Every call goes through one gateway with spending caps, and a person reviews every draft. It
  runs in **mock mode**: no live AI call has ever been made. Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md`
  section 3.4 and section 4, caveat 3; August deck slide 7.

### 1.16 Turning documents into courses
- **Live today:** none recorded. **UNCONFIRMED** beyond the absence of any such add-on in the BZ listing.
- **Academy 2.0:** a pipeline turns an SOP document into a narrated SCORM course. It is built and tested in mock mode
  on one sample SOP, and the voice-over stays off until its spend is approved. Source: ME
  `docs/SENTIENTIA-PRODUCT-GUIDE.md` section 5 ("Create content faster").

### 1.17 Reporting and analytics
- **Live today:** a vendor reporting tool (LearnerScript, a 2019 version patched locally) with report dashboards and
  tiles. Sources: ME `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` lines 98 and 136; BZ `blocks/learnerscript`,
  `blocks/reportdashboard`, `blocks/reporttiles`.
- **Academy 2.0:** it **keeps** those vendor reports, with fixes, and ships them by default. It adds the compliance
  matrix, an analytics dashboard, manager team views and CSV/Excel exports. The predictive analytics are OFF (mock).
  Sources: `tools/packaging/build-standalone.sh` lines 65-68 (report blocks ship by default); ME
  `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.3. *(Do not say the old reports are gone.)*

### 1.18 Public storefront and payments
- **Live today:** a shop (BizLMS cart) with the Airpay payment gateway. It was hardly used: five orders in fifteen
  months, all by Public learners. The one paid order was an internal ₹10 test. No GST was charged and no GST tax
  invoice is issued. Sources: ME `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` lines 1744-1750; ME
  `docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md` lines 18-21; BZ `payment/gateway/airpay`.
- **Academy 2.0:** a Netflix-style public catalog, a cart and checkout through the Airpay gateway with payment checked
  by the server, and GST tax invoices, credit notes and receipts. Payments and GST invoices are OFF until a sandbox
  test passes and Finance confirms. Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.6 and Ask 3.
  *(Deck: no rupee figures. Say "today's shop is hardly used".)*

### 1.19 HR system sync and user lifecycle
- **Live today:** a 24-column HR upload and an hourly HR sync, with error and statistics pages and reporting managers
  matched by employee ID. Source: ME `parity-audit-2026-05-15/airpay_users.md` rows 2-4 and 21. **UNCONFIRMED:**
  whether the hourly sync is configured and running on live.
- **Academy 2.0:** the same 24-column importer with a dry-run preview, plus joiner, mover and leaver sync with KeKa and
  the SCIM standard for identity systems. Sources: ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` line 820; ME
  `docs/adr/ADR-029-keka-jml-hardening.md`; ME `docs/adr/ADR-030-scim-and-outbound-webhooks.md`. *(HR sync exists
  today: present it as an upgrade.)*

### 1.20 Several organisations on one platform: keeping data apart
- **Live today:** the three organisations share one site. A read-only code review of live found **90 confirmed
  issues** (51 rated most severe). In live's add-ons, **any signed-in account** can reach pages and services that
  edit or delete organisation, course, classroom, program, group and user records. Some lists can show every
  organisation's data to users who lack one internal record (1,560 of about 2,871 users have that record). Source: ME
  `docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md` ("Result" and "What this means").
- **Academy 2.0:** every action checks both the permission and the organisation. Each organisation's admins see only
  their own people and courses. Automatic checks that one organisation cannot see another's data run on every
  change. Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.7; ME
  `docs/adr/ADR-031-cross-tenant-authority.md`.
- Suggested plain line (for leadership; see section 3): *"Each organisation's data is kept apart, and every action
  is checked."*

### 1.21 Per-organisation branding
- **Live today:** a logo and one of 7 colour schemes per organisation. There is no per-organisation favicon,
  "e-mail from" name, support e-mail, footer or login message. Source: ME `parity-audit-2026-05-15/airpay_org.md`
  rows 6 and 22.
- **Academy 2.0:** per-organisation and per-customer branding: logo, colours, favicon, sign-in page, sender name and
  footer. The product renames itself from configuration. Sources: same audit row 22; ME `docs/SENTIENTIA-PRODUCT-GUIDE.md`
  section 6.

### 1.22 Privacy and data rights (DPDP Act)
- **Live today:** Moodle's standard privacy and policy tools are present. But **20 of the 22 BizLMS add-ons declare
  no personal data to them**: only the cart and recompletion add-ons do. So a data export or erasure request cannot
  cover what those 20 hold. Sources: BZ listing (`local/*/classes/privacy/provider.php` exists only for `biz_cart` and
  `recompletion`); ME `parity-audit-2026-05-15/airpay_notifications.md` row 66 (notifications: no privacy provider).
- **Academy 2.0:** a privacy declaration in every plugin, and self-service DPDP data download and deletion with admin
  approval and consent tracking. Erasure completeness is built behind two switches that are OFF. Sources: ME
  `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` line 41; ME `docs/adr/ADR-035-privacy-erasure-completeness.md`;
  commit `bee62721a`.

### 1.23 Learning paths, programs, classrooms, evaluations, recertification (already on live)
- **Live today:** all present. Learning plans are sequenced, gated, approval-based and issue certificates. There are
  multi-level programs; classroom training with sessions, attendance, waitlists and trainer feedback; feedback and
  evaluation forms; and annual recompletion. Sources: ME `parity-audit-2026-05-15/airpay_learningpath.md` (verdict),
  `airpay_programs.md`, `airpay_classroom.md`, `airpay_evaluation.md`, `airpay_recompletion.md`.
- **Academy 2.0:** keeps them and brings their history across at cutover through the importers. It adds calendar
  invites, white-labelled e-mails and an adaptive layer (OFF). Sources: ME `docs/business/DEMO-SCRIPT-2026-11-19.md`
  section 7 ("What happens to BizLMS?"); ME `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` section 3.4. *(Present
  this as "nothing lost, history carried over", not as new.)*

### 1.24 Roles and permissions administration
- **Live today:** no audit log of permission changes. Admins change permissions on Moodle's core pages. Source: ME
  `parity-audit-2026-05-15/airpay_roles.md` rows 7-13 and line 96.
- **Academy 2.0:** a permission change log (who, what, when and why), role comparison and exports. Also a switchboard
  that turns every feature on or off per organisation, off by default. Sources: same audit; ME
  `docs/business/DEMO-SCRIPT-2026-11-19.md` section 3.8 (Switchboard).

### 1.25 Ownership and pace of change
- **Live today:** a vendor product. The vendor maintained it under an annual maintenance contract, and the August
  deck says "upgrades depended on the vendor". Sources: ME `docs/email-amc-agreement-request.md`; August deck slide 3.
- **Academy 2.0:** Airpay-owned code, with every component and decision documented. Any future customer can get it by
  configuration. Sources: ME `docs/adr/ADR-024-absorb-and-debrand-bizlms.md`; ME `docs/SENTIENTIA-PRODUCT-GUIDE.md`
  section 6.

---

## 2. Already on live: present as "kept and improved", never as "new"

Course catalog; learner dashboard; certificates; badges and points display; ratings and comments; skills list; HR
upload and sync; manager team view; course requests; classroom training; programs; learning plans; online exams;
feedback forms; recompletion; e-mail notifications (event-driven, about 76 types); the vendor reports; the shop with
the Airpay gateway; logo and colour scheme per organisation; three organisations on one site. Sources: section 1
lines above.

Areas where the record says live has **more** than Academy 2.0 (do not claim parity):

- **The forum system.** Live has its own forum add-on; Academy 2.0 uses core forums only. Sources: ME
  `parity-audit-2026-05-15/unmapped.md` U1; ME `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` line 66
  ("core forums only").
- **Three enrolment e-mails** (section 1.6 watch-out).
- **Cashier features in the shop** (pay at the desk, cash reconciliation). They were missing in May
  (`airpay_cart.md` rows 14-16). **UNCONFIRMED** whether they have been rebuilt since.

---

## 3. Security and privacy baseline (sensitive: wording for the deck)

The four facts below are about the platform that is live today. Put them in plain, non-technical terms, and give no
detail of how they could be exploited. Live gets no patches by decision; the fix is the replacement.

1. **Access checks:** 90 confirmed issues in live's add-ons. Any signed-in account can edit or delete records it
   should not reach. (ME `docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md`)
2. **Payment confirmation:** the live payment callback does not verify the payment response, because its check is
   commented out. Sentientia's fix verifies the payment on the server before granting access. (ME
   `docs/security/2026-06-02-airpay-payment-verification-fix.md`; confirmed in BZ
   `payment/gateway/airpay/process.php` line 95: `//if($error_msg)`)
3. **Sign-in:** no multi-factor sign-in on live (section 1.2).
4. **Privacy:** 20 of 22 BizLMS add-ons are not covered by data-rights requests (section 1.22).

Suggested deck wording: *"An independent code review of today's platform found that access checks are missing in its
add-ons. Academy 2.0 checks every action, keeps each organisation's data apart, verifies every payment on the server,
and covers every module in data-protection requests."*

Do **not** claim that Academy 2.0 stops welcome e-mails from carrying a first-login password. Live's welcome e-mail
does (839 April rows, `BIZLMS-IMPORT-MAPPING` line 1238), and Sentientia's still sends one; it only stops storing a
copy (ME `state-cards/sentientia_users-state.md` 2026-09-25 entry).

---

## 4. Earlier deck claims that do not hold for live (do not reuse)

The August executive deck (`SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx`) has a "before" list on slides 3 and 6. Some
of its items describe the in-development build, not live:

- **"Two different navigation worlds confused learners mid-course."** This was a finding about Sentientia's own build
  (ME `docs/audits/UI-NAV-AUDIT-2026-08-03.md` N-01), not about live.
- **"'Recommended' courses were not actually personalised."** This described Sentientia's own earlier recommendation
  logic (ME `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` line 66), not live's.
- **Slide 5: "Zero of it existed in the purchased product."** This is wrong for the public storefront with cart and
  the Airpay gateway (live has a shop: section 1.18), for the HR upload (1.19) and for certificates (1.11).
- **"Single-tenant mindset."** Live does host three organisations (BizLMS costcenters). The accurate point is that
  live does not keep them reliably apart (1.20) and was not built to serve another company.
- **"100+ defects fixed in the purchased code."** No count in the record was found to back it. Leave it out.
- **The product guide section 3 and the master guide "customer-zero proof" passages.** They describe Sentientia
  features as running on live (see the rules at the top).

---

## 5. UNCONFIRMED (the record is silent)

- When support ends for Moodle 4.1 (the release live runs). The record states only the 5.2, 5.3 and 4.5 dates.
- Whether a Hindi (or other non-English) core language pack is installed on live.
- Dark mode and accessibility on live (the epsilon theme).
- How live's "suggested courses" block chooses courses.
- Whether anyone uses the generic Moodle App with live.
- Whether live's hourly HR sync is configured and running.
- Any document-to-course or AI capability on live beyond "none in the plugin list".
- Whether the shop's cashier features have been rebuilt in Academy 2.0.
- Live user satisfaction, usage or complaint data: there is none in the record. Do not cite any.

---

## 6. Source index

- Migration plan, live identity: ME `docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` section 0
- Live plugin inventory: ME `docs/cutover/STAGE-B-CAPABILITY-INVENTORY-2026-09-30.md`; ME
  `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 1
- Feature parity (live versus rebuilt), May 2026: ME `parity-audit-2026-05-15/*.md`. These describe live's BizLMS
  features; their "Airpay has" column is the May state of the rebuild, not today's Academy 2.0.
- Live security review: ME `docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md`
- Payment callback: ME `docs/security/2026-06-02-airpay-payment-verification-fix.md`
- Reminder that never fired: ME `docs/visual-evidence/2026-11-16/course-reminder-enrol-date/README.md`
- Shop usage: ME `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` lines 1744-1750; ME
  `docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md`
- Platform support dates: ME `docs/adr/ADR-033-target-moodle-5.3-lts.md`
- Academy 2.0 status and caveats: ME `docs/business/DEMO-SCRIPT-2026-11-19.md`; ME `PROJECT-STATE.md`
- Earlier decks (text extracted with the pptx `slide_text.py`): `SENTIENTIA-EXECUTIVE-DECK-2026-08-21.pptx`,
  `AIRPAY-ACADEMY-2.0-UAT-SHOWCASE-DECK-2026-09-21.pptx`, `csuite-2026-11-18/SENTIENTIA-CSUITE-DECK-2026-11-18-v1-asks-REJECTED.pptx`
- Production code snapshot (read only): BZ `local/`, `blocks/`, `theme/`, `admin/tool/`, `mod/quiz/accessrule/`,
  `payment/gateway/airpay/process.php`
