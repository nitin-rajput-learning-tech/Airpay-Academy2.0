# local_airpay_evaluation

Feedback + evaluation forms. Replacement for BizLMS `local_evaluation`.
Used by `local_airpay_classroom` for post-session feedback and by L&D
for course-level evaluation surveys.

| Field | Value |
|---|---|
| Component | `local_airpay_evaluation` |
| Version | 1.6.0 |
| Depends on | `local_airpay_org` |

## What it does

- Form-builder: questions with multiple types (single choice, multi
  choice, free text, Likert).
- Per-question anonymous toggle.
- Response collection with per-respondent submission lock.
- Analysis dashboard with response-rate and per-question breakdown.
- Filtered-responses view (e.g. responses for one classroom session).
- CSV export.

## Tables (3)

- `local_airpay_evaluation` — form containers.
- `local_airpay_evaluation_questions` — questions within a form.
- `local_airpay_evaluation_responses` — submitted responses.

## Capabilities (2)

`:manage`, `:respond`.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/airpay_evaluation/cli/smoke_template_io.php"
php "C:/xampp/htdocs/moodle5/public/local/airpay_evaluation/cli/smoke_anonymous_question.php"
```

## Phase 5 work shipped

G-05: analysis dashboard + filtered responses + CSV export.

## Privacy / GDPR

Privacy provider handles the anonymous-question subtlety: anonymous
responses are NOT exported even on a DSR for the responding user (they
cannot be linked back to a userid because they were never stored with
one).

## Open backlog

- Standalone "assign N users" UI for bulk evaluation respondent assignment.
- Detailed response view drill-down per question per respondent.
