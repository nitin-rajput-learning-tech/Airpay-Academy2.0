# Decisions waiting for Nitin (brief, 2026-10-14)

Everything engineering can do without you is done: Moodle 5.3 passed the post-import parity gate on the April production
copy, every PHPUnit failure on 5.1 and 5.3 is fixed, and the Stage B tools are merged. What is left needs your answer,
Airpay Finance's, or IT's. The full wording of Q1 to Q14 is in `OWNER-DECISIONS-2026-10-07.md` section 6; this page
orders them by what they unblock and gives a recommendation for each.

## 1. Answer now (each unblocks work this week)

| # | Question | Recommendation | What happens after |
|---|---|---|---|
| D1 | **FNS0001 survey.** Moodle 5.x no longer ships mod_survey, so the one survey on live (FNS0001, empty) disappears in the upgrade: `course_modules` 1540 -> 1539. Accept the loss, or ship a 5.x survey plugin? | **Accept the loss.** It holds no responses, and a third-party plugin means one more thing to maintain. | Recorded in the decisions file; the parity gate then expects 1539. |
| Q1 | **IDN-05.** Who, besides the site admins, holds the cross-tenant platform role at cutover? | **Nobody for now.** Members can be added by hand at any time; nobody can be "un-seen" later. | Nothing to do: the role is created with no members. |
| Q2 | **EV-18.** May an evidence session switch `sentientia.evaluation.learner_history` ON for one LOCAL test tenant, screenshot `my_evaluations.php` as a test learner, and switch it OFF again? | **Yes, locally.** Nothing on UAT changes. | The evidence for that reader flag is captured and put in front of you. |
| Q12 | **CRS-13.** Delete the dead `moodle-enhancement/local/airpay_ratings` directory (14 files, never packaged, restorable from git)? | **Yes.** | One `git rm` commit. |
| Q13 | **F-22, optional.** Purge the unreachable local commit 32a6a9dbc (a production user id and survey answers) from this machine's git store? | **Yes.** Also check in the GitHub UI whether a PR was ever opened from `claude/bizlms-import-evaluation` (only you can). | `git reflog expire` and `git gc` on this clone only. |
| Q14 | **Request comments.** If Stage B finds request comment rows, is writing `fold_reviewed` in the decisions file the way you say "I read them, folding is safe"? | **Yes.** On the April copy there are none. | Only matters if Stage B shows rows. |
| SH | **Screenshots.** Two pieces of work wait for screenshots: the 5.3 UI changes (`docs/visual-evidence/2026-10-08/moodle53/README.md` lists each one, desktop and 590 px) and the `claude/eval-followups` branch. | **Let Claude capture them on local XAMPP** with the seeded test personas (localhost only, test accounts from the project's seed files, never a real password). Or capture them yourself on UAT. | You review the screenshots, then the branch merges. |

After D1, Q1 and Q14 are answered, **sign the decisions file** again. Its hash is re-pinned: the post-import parity
gate (`--expect-decisions-hash`) proves the import ran with exactly the file you signed.

## 2. At the next UAT session

- **Q3 (LRN-06).** List the enabled all-tenant recompletion rules on UAT (ids and course ids only), confirm or disable
  each, then decide whether `sentientia.recompletion.run_rules` goes ON. **Recommendation: keep it OFF** until there is
  a native rule Airpay needs.
- This needs the **UAT tunnel** (VPN or SSH) open for the session.

## 3. For Airpay Finance (please forward; Finance has not been consulted)

- **Q4** credit balances (April copy: none), **Q5** the one INR 10 sale from January 2025 with no tax invoice,
  **Q6** the six points before Sentientia issues any GST tax invoice.
- **Recommendation until they answer:** native GST invoices stay on hold (`cart.native_tax_invoices = hold`), and the
  cart stays open only for the tenants already set (`enabled_tenants` = 77, 177).

## 4. For IT

- **A live backup that includes the full moodledata** (`filedir/` and the rest), with the SHA-256 of each archive
  computed on the live server before the copy. The Stage B kit refuses an archive without it.
- **The server for Moodle 5.3** (ADR-033: PHP 8.3 or later, MySQL 8.4). Stage B and cutover run there.

## 5. After the Stage B rehearsal (nothing to answer yet)

- **Q7** one written batch accepting the skipped-row counts. On the April copy these are
  `learningplan:orphan_course` 4, `ratings:orphan_item` 87 and `ratings:unknown_area` 195 (194 of them are
  vulnerability-scanner probe rows with no real user, F-41). Every skipped row stays in the BizLMS tables.
  **Recommendation: accept**, once Stage B confirms the same counts on the final backup.
- **Q8** only if Stage B shows programs or learning plans whose tenant was guessed from their creator.

## 6. Reader flags at cutover (after you have seen them working on UAT)

- **Q9** ratings: reviews stay OFF; reactions ON after the screenshots. **Q10** imported e-mail and request history ON,
  the imported admin-log report OFF. **Q11** the course-enrolment, learning-path-enrolment and manager
  course-completion e-mails ON for Airpay. **Recommendation: yes to all three as worded**, each after its UAT check.
- A flag switched on reaches cron and other long-running processes within 30 seconds (deploy-runbook section 3).

## Not needed

- Moving XAMPP from C: to D:: C: has 163 GB free again since the 2026-10-10 clean-up.

## Reply in one line

`D1 accept, Q1 none, Q2 yes, Q12 yes, Q13 yes, Q14 yes, SH claude` (or change any item), then sign the decisions file.
