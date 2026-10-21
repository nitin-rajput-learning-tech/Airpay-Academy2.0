# Visual evidence: My evaluations (branch `claude/eval-followups`, merged 2026-10-16 as 35c6d72c4)

Local XAMPP (Moodle 5.1, the merged code plus the 2026-10-19 fix below), desktop 1440 px and mobile 590 px. Captured
2026-10-19 with `tools/visual-pass/eval_checks.mjs` as `vp_eval77`, a test learner in test tenant /77, on the history
that `seed_eval_evidence.php` gave that persona on 2026-10-14.

**What changed (EV-18 findings 1 and 2)**
1. `my_evaluations.php` prints its own title, "My evaluations", with a one-line description. The theme does not show
   the `$PAGE` heading.
2. The Date column says which date each cell is: "Due by", "Submitted" or "Assigned".

| Shot | Shows |
|---|---|
| `01-my-evaluations-en` | Flag ON for /77. Title and description. Four forms: one waiting (Due by), two answered (Submitted, one of them anonymous, with its note), one closed without a response (Due by). |
| `02-my-evaluations-hi` | The same page in Hindi (`?lang=hi`). |
| `03-my-evaluations-flag-off` | Flag OFF: "Your evaluation history is not available yet." The debug panel under it is the local `DEBUG_DEVELOPER` setting; UAT runs with debug off. |

**Flag:** `sentientia.evaluation.learner_history` (owner decision EV-18, Q2 of 2026-10-14: ON for one local test tenant
for the shots, then OFF). Before: no override, resolved false. Switched ON for tenant 77 only with
`gst_evidence_flags.php on evaluation 77`, then `off`. After: no override, resolved false for tenants 1, 77 and 177.

**Defect found and fixed during this capture (2026-10-19).** With the flag ON, the page first failed with
"Unknown column 'e.evaluationmode'". The merge had left the EV-17 upgrade steps (2026100701, 2026100702) below the
2026100802 that gap-integration already had, so this local site skipped them. The fix is commit 1b6a00698: evaluation
2026101900 / 1.17.4, a guarded repeat step. UAT (2026092500) and fresh installs were never affected. See the
evaluation state card, "2026-10-19 - EV-17 steps below a shipped version". Shots 01 and 02 were taken after that
upgrade.

**Privacy:** the page lists only the persona's own forms. No other person appears.
