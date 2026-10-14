# Visual evidence: EV-18, the learner's own evaluation history (2026-10-14)

Page `/local/sentientia_evaluation/my_evaluations.php` (plugin `local_sentientia_evaluation`, flag
`sentientia.evaluation.learner_history`, ADR-032). Taken by Claude on the LOCAL XAMPP site (http://localhost:8080,
Moodle 5.1.3+, code of `claude/gap-integration`), headless Chrome through Playwright, 1440 px and 590 px wide, whole
page. Nitin approved a local evidence session on 2026-10-14 (EV-18). Nothing touched UAT, production or an outside host.

## Flag

| Flag | Tenant | During the pass | Now |
|------|--------|-----------------|-----|
| `sentientia.evaluation.learner_history` | /77 (Public) | ON | OFF (override row removed; read back from the database) |

## Test learner and data

The page needs `local/sentientia_evaluation:respond`. `vp_public77` (the existing /77 persona) holds no system role and
lacks it, so `seed_eval_evidence.php` created ONE NEW local test account, **vp_eval77** ("VP Learner (evaluations) /77"):
tenant /77, role `employee` at system context (as a ZEEA learner has it). No existing persona or role was changed. The
learner has no real name or data: the forms are four test forms made through `evaluation_manager` (create, questions,
`ensure_assignment`, `submit_response`), all native forms of tenant /77:

| Form | Kind | History row |
|------|------|-------------|
| VP Course reaction survey: Compliance Essentials | named, answered | Responded, date and minute |
| VP Anonymous workplace pulse | anonymous, answered | Responded, date only, "Anonymous evaluation: your answers are not linked to you." |
| VP Manager feedback form | named, assigned, due in 7 days | Waiting for you |
| VP Training follow-up (30 days after) | named, assigned, due 3 days ago | Closed without a response (the assignment was set to `expired` directly: only the evaluation task expires one and it was not run) |

## Screenshots

| File stem | Page | Persona | What it shows |
|-----------|------|---------|---------------|
| `01-my-evaluations-en` | `my_evaluations.php` | vp_eval77 | The four rows above, English |
| `02-my-evaluations-hi` | `my_evaluations.php?lang=hi` | vp_eval77 | The same page in Hindi: column titles, status badges, the anonymous note, Hindi month names |
| `03-my-evaluations-flag-off` | `my_evaluations.php` | vp_eval77 | Flag OFF (the default): "Your evaluation history is not available yet." (HTTP 404; the Debug info and Stack trace boxes are this box's developer-debug setting, not a product screen) |

`results.json` and `results-off.json` hold the machine record (HTTP status, console errors, overflow at both widths: 0,
page text).

## What this does not show

- **The "imported" badge** ("From the previous system"): it needs rows the BizLMS importer writes into its map table, which
  this box does not have, and no plugin API creates one. No row was faked.
- **The EV-20-NOTE wording** ("your name is not shown with your answers in its results") and the EV-17 supervisor-form
  hiding: they are on `claude/eval-followups`, not on this code. The anonymous note here is the older text.

## Things that look wrong or odd (for Nitin; none was changed)

1. **The page has no visible title.** `set_heading()` is called with "My evaluations" and the text is in the page, but the
   theme hides the page heading on the `standard` layout and the template has no heading of its own, so the page starts with
   the intro sentence (`01`, `02`). Checkout and the order history do not have the problem because their templates carry
   their own `h2`.
2. **The Date column means different things.** For a form the learner answered it is when they answered (the day only for an
   anonymous form); for "Waiting for you" it is the DEADLINE (21 Oct 2026 21:18) and for "Closed without a response" it is
   the deadline that passed (11 Oct 2026 21:18). Nothing says "due"; a learner reads 21 Oct as a date something happened.
3. The times show to the minute (21:18) on named forms and the day on the anonymous one, as the code comment says it should.

Checked and fine: four rows in newest-first order by their date, Hindi strings present for every label (no missing-string
marker), no horizontal overflow at 590 px, no console errors, nothing about another person on the page.

## How to reproduce (local only, cwd = `C:/xampp/htdocs/moodle5/public` for the PHP scripts)

```
php seed_eval_evidence.php                       (creates vp_eval77, writes its credential into .personas.local.json, seeds the forms)
php gst_evidence_flags.php on evaluation 77
node eval_checks.mjs --phase on
php gst_evidence_flags.php off evaluation 77     (removes the override row)
node eval_checks.mjs --phase off
```

Scripts: `moodle-enhancement/tools/visual-pass/` (`seed_eval_evidence.php`, `eval_checks.mjs`, `gst_evidence_flags.php`,
`evidence_lib.mjs`). Each PHP script refuses unless `wwwroot` is localhost and `noemailever` is set.
