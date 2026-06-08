# local_sentientia_lifecycle

Joiner / Mover / Leaver (JML) automation. Listens for HRMS events from
`local_sentientia_integrations` and executes the appropriate workflow.

| Field | Value |
|---|---|
| Component | `local_sentientia_lifecycle` |
| Version | beta |
| Depends on | `local_sentientia_org`, `local_sentientia_integrations` |

## What it does

- **Joiner workflow:** create Moodle user, set `open_path` to tenant +
  department, enrol in onboarding learning path, dispatch welcome email.
- **Mover workflow:** update `open_path`, re-evaluate cohort memberships,
  un-enrol from old-department-specific courses if applicable.
- **Leaver workflow:** suspend account, schedule data redaction at the
  end of the statutory hold period.

## Tables

None directly — operates over `mdl_user` and the airpay org tree.

## Privacy / GDPR

Provider exists for the leaver-workflow audit trail (the redaction is
itself a personal-data processing event that must be auditable).

## Open backlog

- Configurable per-workflow templates (currently hardcoded).
- Manual override path for L&D admin (currently fully automatic).
