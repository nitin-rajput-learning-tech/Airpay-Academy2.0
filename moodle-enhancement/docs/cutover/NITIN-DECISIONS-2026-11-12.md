# Open decisions, 2026-11-12: approved as recommended

Nitin asked for every open decision as a question with a recommendation. He approved all the recommendations in
advance (chat, 2026-11-12 08:57 IST: "consider all recommendations approved to go").

This page replaces the open parts of `NITIN-DECISION-BRIEF-2026-10-14.md`. Items closed since then are not repeated:
- the dashboard blocks drawer;
- `course-pre`;
- the UAT backup folder and the old 5.2 zip;
- airpay_ratings;
- D1, Q1-Q3 and Q12-Q14;
- the cart Refund button.

Some actions are permanent, so each is held to the least destructive form that does the job:
- files are removed with `git rm` (restorable from git);
- privacy requests are cancelled, not deleted.

## A. Engineering acts on these now

| # | Question | Recommendation (approved) |
|---|---|---|
| A1 | **ADR-034.** Accept the content-pipeline architecture? It covers one canonical course document, SCORM 1.2, SCORM 2004 and cmi5 outputs, and self-hosted speech. | **Accept.** Start Stage 1: schema, `local_sentientia_scd*` tables and the PHP compiler, local only, AI in mock mode, flags OFF. Self-hosted speech (GPU) and live AI calls still wait for the budget approval (C1) and a [CONFIRM] per paid call. |
| A2 | **Course edit mode.** The left drawer opens empty because the theme already shows the course index inside the page. Fill it or drop it? | **Drop it.** Remove the empty drawer and its toggle in edit mode; the in-page course index stays. This is a cosmetic theme fix. |
| A3 | **Footer.** On standard-layout pages the 109 px footer stays fixed at the bottom of the window. It has been pinned since the 2026-05-29 app-shell scroll fix, and on a 665 px tall window it takes a sixth of the screen. Keep it pinned? | **Let it scroll with the content** and appear at the end of the page, as on most sites. The sidebar and topbar stay pinned. |
| A4 | **Privacy requests.** All 515 data-privacy requests in the local copy are for deleted users and "awaiting approval". They came with the production import, so live most likely has the same queue. Delete them? | ~~Cancel them.~~ **WITHDRAWN 2026-11-12, NOT EXECUTED.** A check before acting showed all 515 are *automatic deletion requests* (type 2, creation method 1). BizLMS created them when users were deleted, from 2023-06-21 to 2026-05-29; 229 subjects have no user row and 286 are marked deleted. Approving a request is what purges the remaining personal data, so cancelling would keep it indefinitely. This is a DPDP erasure question; a new recommendation goes back to Nitin. |
| A5 | **Audit log.** Its list of sensitive events names four events that no plugin creates: cart `refund_processed` and `order_paid`, proctoring `session_flagged` and `review_submitted`. So refunds, payments and proctoring flags never reach the audit report. Build them or drop the names? | **Build them.** Add the four events and fire them where refunds, payments, proctoring flags and reviews happen, in both trees, with tests and en/hi strings. |
| A6 | **Stale files.** `moodle-enhancement/my_dashboard_redirect.php`, `theme/sentientia/templates/nav-drawer.mustache` and `flat_navigation.mustache` have been unreferenced since 10-08. Remove them? | **Remove them** with `git rm`, after a last check that nothing references them. |
| A7 | **CLAUDE.md.** It still names theme airpayux, the old paths and Moodle 5.1.3 as the platform. Update it? | **Update** sections 2 and 7 to the current facts: theme `sentientia`, Moodle 5.3 LTS target (ADR-033), local wwwroot `http://localhost:8080`. |
| A8 | **Resume watchdog.** It wakes up every couple of hours even while the work is "waiting for Nitin". Should it stand down in that state? | **Yes.** Add one line to the scheduled task: stop when the state is `waiting-for-nitin` or `paused-by-nitin`. |

## B. Cutover decisions, approved now and applied at cutover

| # | Question | Recommendation (approved) |
|---|---|---|
| B1 | **Q7.** Accept the skipped-row counts: `learningplan:orphan_course` 4, `ratings:orphan_item` 87, `ratings:unknown_area` 195 (194 are scanner probes)? | **Accept**, if Stage B shows the same counts on the final backup. If any count differs, it comes back to Nitin. |
| B2 | **Q8.** If Stage B finds programs or learning plans whose tenant could only be guessed from their creator, keep the creator's tenant or import them with no tenant? | **Import them with no tenant**, so only cross-tenant admins see them. That matches BizLMS, which showed them only to site admins, and no learner sees anything new. The ids go into the Stage B report. |
| B3 | **Q9-Q11.** Reader flags at cutover. Q9: reviews OFF, reactions ON. Q10: imported e-mail and request history ON, imported admin-log report OFF. Q11: course, learning-path and manager completion e-mails ON for Airpay. | **Yes to all three as worded**, each after its UAT check. |
| B4 | **kn/mr/sw packs.** They are reviewed by AI bilingual reviewers but not yet by a human native speaker. Hold them? | **Keep them.** Ask ZEEA (Tanzania) to have a Swahili speaker read the sw pack before ZEEA learners use it; install the Swahili core language pack on UAT for that read at the next tunnel session. Get a kn/mr read only when a Kannada or Marathi tenant is planned. |

## C. Needs someone else (Claude drafts; Nitin sends)

| # | Question | Recommendation (approved) |
|---|---|---|
| C1 | **Budgets.** The security spend, the AI spend cap and the live `ANTHROPIC_API_KEY` are pending C-suite approval. | Claude drafts the spend-plan annex, including a self-hosted (Ollama-class) AI option and its costs, for Nitin to take to the C-suite. |
| C2 | **Finance (GST).** Q4 credit balances and Q6, the spec section 10 points: GSTIN, SAC, invoice series, IRN, B2C QR, retention and signature. Native GST invoices stay on hold until then. | Claude drafts the e-mail to Airpay Finance; Nitin sends it. |
| C3 | **IT.** A live backup with the full moodledata and SHA-256 of each archive; the Moodle 5.3 server (PHP 8.3+, MySQL 8.4); where Stage B runs. | Claude drafts the IT request; Nitin sends it. Recommendation: run Stage B on the new 5.3 server itself. |
| C4 | **UAT learner check.** The 10-21 logged-in check also asked for a learner view. The admin view (Meera) is done. | Nitin logs a UAT test learner in once in Chrome; Claude runs the same read-only check. |
