# local_airpay_users

User administration — replacement for BizLMS `local_users`. Manages
the user list, CRUD, bulk CSV import/export, the 39 BizLMS-introduced
custom user profile fields, the skill profile standalone page, and
the photo upload pipeline.

| Field | Value |
|---|---|
| Component | `local_airpay_users` |
| Version | `2026050904` (1.8.0) |
| Requires | Moodle 4.5+ |
| Depends on | `local_airpay_org`, `local_airpay_privacy` |

## What it does

- List, create, edit, suspend, delete users.
- Bulk CSV import with dry-run preview + per-row validation.
- Profile page with the 25+ custom fields (designation, manager,
  department, joining-date, etc.).
- Skill profile standalone page (separate from profile.php).
- Photo upload with server-side GD resize to 400×400, JPEG re-encode.
- Grades widget — Moodle gradebook iframe embedded on profile.

## Capabilities (7)

| Capability | Context | Default archetype |
|---|---|---|
| `:create`, `:edit`, `:delete`, `:manage`, `:suspend`, `:bulkstatuschange` | `CONTEXT_COURSECAT` | manager |
| `:bulkimport` | `CONTEXT_SYSTEM` | siteadmin |

## Web services (6)

CRUD + bulk import + bulk status change.

## Tables

No own tables — uses Moodle's `user` and `user_info_data` plus the
BizLMS custom user columns (`open_path`, `open_managerid`, etc.).

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/airpay_users/cli/smoke_bulk_csv.php"
php "C:/xampp/htdocs/moodle5/public/local/airpay_users/cli/smoke_photo_upload.php"
php "C:/xampp/htdocs/moodle5/public/local/airpay_users/cli/smoke_profile_skills.php"
```

## Privacy / GDPR

Full provider — DSR export bundles every profile field + custom column,
DSR delete redacts to the GDPR-mandated minimum (email + idnumber
preserved for audit, all PII fields nulled).

## Open backlog

- Photo upload doesn't yet pass through the airpay_proctoring identity
  pipeline for hiring assessment use cases.
- The custom user fields rely on the BizLMS schema extensions; FORK-PLAN
  sequences in-housing of these columns through the airpay_org accesslib
  rather than direct column dependencies.
