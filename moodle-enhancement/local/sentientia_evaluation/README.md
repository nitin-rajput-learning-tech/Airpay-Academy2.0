# local_sentientia_evaluation

Feedback + evaluation forms. Replacement for BizLMS `local_evaluation`.
Used by `local_sentientia_classroom` for post-session feedback and by L&D
for course-level evaluation surveys.

| Field | Value |
|---|---|
| Component | `local_sentientia_evaluation` |
| Version | 1.17.0 (`2026100701`) |
| Depends on | `local_sentientia_org` |

## What it does

- Form-builder: questions with multiple types (single choice, multi
  choice, free text, Likert).
- Per-question anonymous toggle.
- Response collection with per-respondent submission lock.
- Analysis dashboard with response-rate and per-question breakdown.
- Filtered-responses view (e.g. responses for one classroom session).
- CSV export.

## Tables (3)

- `local_sentientia_evaluation` — form containers.
- `local_sentientia_evaluation_questions` — questions within a form.
- `local_sentientia_evaluation_responses` — submitted responses.

## Capabilities (2)

`:manage`, `:respond`.

The individual responses pages (`response_list.php`, `response_detail.php`) need `:manage` (the manager archetype: manager,
tenant administrator, site administrator) and the default-OFF flag `sentientia.evaluation.response_drilldown`; with the
flag OFF they answer "not available" and nothing links to them. They used to ask for a capability nobody declares.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_evaluation/cli/smoke_template_io.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_evaluation/cli/smoke_anonymous_question.php"
```

## Phase 5 work shipped

G-05: analysis dashboard + filtered responses + CSV export.

## BizLMS import (ADR-032)

`db/bizlms_import.php` registers the `evaluation` importer (`classes/bizlms/`), which
brings the history in the BizLMS `local_evaluation` tables over at cutover:
forms (ids kept, always archived and manual), their questions, templates,
assignments and answers. Run it only through
`local/sentientia_platform/cli/import_bizlms.php`; the map is
`docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 18.

- **A form that ever held an anonymous answer imports every answer anonymous**, including the ones BizLMS stamped
  named (decision `evaluation.sticky_anonymity` = `whole_form`).
- **Anonymous answers stay anonymous in their own row**: stored with user id 0
  and no subject. The legacy tables still link the answer to the person, and the
  import's map ties the anonymous response and that person's implied assignment
  to the same completion id (decision `evaluation.legacy_anonymous_linkage`,
  pending the legacy-table privacy ADR, which has to cover the map and the
  assignment rows as well as the legacy tables).
- **Supervisor forms** keep the person evaluated in
  `responses.subject_userid` (the responder stays in `userid`) and are marked on the form itself:
  `evaluationmode` is `SP` (every other form is `SE`), so the person evaluated is never listed as having responded,
  even on an anonymous supervisor form or an old completion with no evaluator.
- **A form no clue can place** (its path, its stored root and its classroom) imports pathless, for cross-tenant callers
  only. It is never filed under the tenant of the user who last edited it (decision `evaluation.tenant_editor_fallback`).
- **Imported forms are read-only**: `evaluation_manager` refuses to edit,
  re-status, reorder, delete or assign on a form the import created. To run the
  same questions again, export it as a template and create a new evaluation.
- **Learner history**: `my_evaluations.php`, behind the default-OFF flag
  `sentientia.evaluation.learner_history`.

## Privacy / GDPR

Privacy provider handles the anonymous-question subtlety: anonymous
responses are NOT exported even on a DSR for the responding user (they
cannot be linked back to a userid because they were never stored with
one). A supervisor's response is also exported to the person it is about
(`subject_userid`), without naming the supervisor; erasing that person
keeps the response and removes the link.

## Open backlog

- Standalone "assign N users" UI for bulk evaluation respondent assignment.
- Detailed response view drill-down per question per respondent.
