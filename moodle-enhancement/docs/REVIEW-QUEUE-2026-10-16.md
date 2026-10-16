# Review queue for Nitin, 2026-10-16

**Answered 2026-10-16 08:27: "ok 1 to 7".** All seven branches were merged into `claude/gap-integration` after Nitin's restart
(35c6d72c4 to fff796df2), and the April skip caps followed (b40a0fd36). See PROJECT-STATE, 2026-10-16. The evidence folders
named below are captured on the merged tree under `docs/visual-evidence/2026-10-16/<area>/`.

Everything here is built, reviewed by a separate read-only check, and tested on local XAMPP Moodle 5.1. **Nothing is
merged into `claude/gap-integration` until you say "ok" for that branch.** Every new feature is behind a default-OFF
flag. Compare links show the full diff on GitHub.

Test pass of 2026-10-16 (one serialized run with all six fix branches deployed together): **11 suites, about 2,160
tests, 0 failures**.

| Suite | Tests |
|---|---|
| cart | 348 |
| evaluation | 189 |
| platform | 599 |
| emails | 217 |
| theme | 68 |
| catalog | 109 |
| request | 110 |
| org | 117 |
| skills | 75 |
| users | 242 |

Log: `D:/Claude Local/kit-scratch/fixpass/progress.txt`.

| # | Branch | What it changes | Evidence | Compare |
|---|---|---|---|---|
| 1 | `claude/gst-invoices` | **GST tax invoices, credit notes, purchase receipts** (cart 1.2.x). Invoices are issued after the payment and never inside it. Gap-free 16-character numbers. Place of supply from the buyer's state. B2B waits for the IRN. Refund credit notes. Receipt PDF. Retention-aware erasure. **UI fixes:** a Refund button on admin orders only; order history shows the payment date, statuses in words, and ₹. Flags `sentientia.cart.gst_tax_invoices` and `.purchase_receipts` stay OFF until Finance answers spec section 10. | `docs/visual-evidence/2026-10-14/gst/` and `2026-10-16/cart/` on the branch | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/gst-invoices) |
| 2 | `claude/eval-followups` | Evaluation follow-ups EV-17 to EV-36 (reviewed "ship" on 10-07), brought up to date with gap-integration on 10-15. The import framework moves to platform 2026101500. **UI fix:** the My evaluations title, and a Date column that says which date it is. | `2026-10-07/eval-followups/` and `2026-10-16/evaluation/` on the branch | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/eval-followups) |
| 3 | `claude/ui-fix-emails` | The e-mail template editor's live preview renders on first load (it was cut at the first double quote), and the editor no longer overflows its container. | `2026-10-16/emails/` | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/ui-fix-emails) |
| 4 | `claude/ui-fix-theme` | Dropdowns written for Bootstrap 5 open in our Bootstrap 4.6 theme, with no double toggle. The edit-mode switch is styled. **For course authors, edit mode now survives a reload and the course editor works:** three theme causes, one of which reset `$USER->editing` for every editingteacher. | `2026-10-16/theme/` | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/ui-fix-theme) |
| 5 | `claude/ui-fix-request` | Learners see "Request enrolment" where a request is the way in; open or keyed self-enrolment now offers it too. The line manager gets `:approve`, with an upgrade back-fill, so routed approvers can act. | `2026-10-16/request/` | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/ui-fix-request) |
| 6 | `claude/ui-fix-skills-org` | The skill self-rate dialog warns when no level is picked. Skill category and org names no longer show double-escaped `&amp;amp;`. The profile's "Suggested next courses" names are plain text. | `2026-10-16/skills-org/` | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/ui-fix-skills-org) |
| 7 | `claude/research-scorm-lxp` | **The SCORM/LXP study** (your 10 answers of 10-15):<br>- STUDY.md;<br>- a matrix of 51 criteria x 20 vendors;<br>- ADR-034 (content pipeline);<br>- the C-suite brief (md + docx) and an 18-slide deck;<br>- 6 clickable prototypes;<br>- a mock pilot harness (55 tests).<br>Docs and prototypes only; no plugin code. | `docs/research/2026-10-15-scorm-lxp/` | [diff](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/compare/claude/gap-integration...claude/research-scorm-lxp) |

## How to answer

One line is enough, for example: `ok 1 2 3 4 5 6 7`, or `ok 3 4 6, hold 1 (question ...)`. Each "ok" branch is
merged with `--no-ff`. The tree-drift and siteid checks are run, and `production` on GitHub is fast-forwarded.

## Also waiting on you

- **Monday 10-19 morning: the UAT tunnel.** The upgrade follows `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md`. It
  was rehearsed locally on 10-15 and passed. You chose no DB backup: the code and moodledata are archived, and the
  site is in maintenance mode during the upgrade.
- **GST (Airpay Finance):** spec section 10, seven points (GSTIN, SAC, series, IRN, B2C QR, retention, signature).
- **Research pilot:**
  - the AI cap (Addendum A) and `ANTHROPIC_API_KEY`;
  - a CONFIRM per paid call;
  - a choice of speech engine;
  - named Hindi reviewers.
- **Q8-Q11** (reader flags at cutover), after the UAT screenshots.
