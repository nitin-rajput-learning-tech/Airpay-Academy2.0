# local_sentientia_privacy

Digital Personal Data Protection Act (DPDP, 2023) request hub for Sentientia LMS, plus (since 1.1.0) the
supplementary privacy provider for Moodle's stock `tool_dataprivacy` erasure and export. It recovers the contexts the
stock approval drops and the course completions core never discovers, so an erasure is complete as ADR-035 defines
it (what stock providers would delete without the two core defects). It does not make "Deleted" literally true: see
"What still remains" below.

| Field | Value |
|---|---|
| Component | `local_sentientia_privacy` (was `local_airpay_privacy`) |
| Version | `2026111300` (1.1.0) |
| Requires | Moodle 4.5+ (`2024100700`); runs on 5.1 locally, 5.3 is the target (ADR-033) |
| Depends on | `local_sentientia_platform` (tenant checks, feature flags) |
| Feature flags | 2, both default OFF (see below) |
| State card | `moodle-enhancement/state-cards/sentientia_privacy-state.md` |

## What it does

### 1. The DPDP request hub (`index.php`, `classes/privacy_manager.php`)

- Self-service page (`/local/sentientia_privacy/index.php`): a data download and an account-deletion request for
  external / public tenant users; a policy notice only for internal employees (tenant 1).
- Admin panel on the same page, for a site admin or a `:manage` holder who is also cross-tenant (ADR-031): every
  request, Approve (= erase) and Reject.
- Approve runs `privacy_manager::process_deletion()`: it asks every `local_sentientia_*` privacy provider to erase
  (or anonymise) the subject's data, keeps anonymised learning records for audit, and marks the request
  `partial` (with a note) when anything could not be erased. Core providers are deliberately not called on this
  route.

### 2. Supplementary erasure for stock `tool_dataprivacy` (1.1.0, `classes/local/erasure_completeness.php`)

Moodle core has two defects that make a `tool_dataprivacy` erasure silently incomplete (found in the A4 rehearsal,
2026-11-13: about 17,000 rows of erased subjects left behind):

1. **Truncation.** A component's context list with duplicate ids gets a key hole, and core's iterator stops there,
   so the data-request approval never approves the contexts after it. Affects tool_log, report_stats, mod_quiz,
   mod_scorm and core_course, on erasure, export and the user-expiry path.
2. **Completion gap.** core_course never discovers `course_completions` rows in courses without completion
   criteria, or with criteria the user never touched.

With a flag ON, this plugin's own privacy provider adds those contexts to its (hole-free) context list. The stock
approval judges them like any other context (DPO approval, data registry protection), and this plugin then
erases or exports each one through the component that owns it. No core file is edited (ADR-033). The truncation
part turns itself off when core is fixed (`stock_approval_truncates()` self-check). Every supplementary erasure
fires `\local_sentientia_privacy\event\erasure_supplemented` (counts only, no personal data; a failed component's
contexts are reported as attempted, not erased). A failing component is reported to the DPO the same way as any
stock provider failure: one exception, after every other component has run. Its message says what happens next: a
DELETE still completes and is never reprocessed, so the subject needs the ADR-035 residue sweep; an EXPORT file is
missing that data, so a new export request is the remedy. With `tool_dataprivacy | allowfiltering` set, export
skips the truncation part (the stored lists are full) but still exports the completion gap.

A CLI caller of `erasure_completeness::apply_delete(..., 'cli')` gets the manager's shared activity-completion
cleanup on the approved list from `apply_delete()` itself; on the request and expiry paths the privacy manager
runs it.

### What still remains (ADR-035 "What still remains")

Even with both flags ON:
- rows in contexts that no longer exist (5,727 for the A4 subjects): no provider can reach them;
- log rows where the subject is only `relateduserid` or `realuserid` (kept by core design);
- the data of subjects without a user row (713 log rows of 229 A4 subjects): the stock task fails before deleting;
- the user row and the BizLMS profile columns (a separate decision);
- `course_completions` rows in courses without criteria on the user-list path (partial course-context expiry);
- the DPDP route (section 1) is a separate flow and does not call core providers.

## Feature flags (`db/feature_flags.php`)

| Key | Default | Effect |
|---|---|---|
| `sentientia.privacy.erasure_completeness.enabled` | OFF | Recover the contexts core's approval drops |
| `sentientia.privacy.completion_gap.enabled` | OFF | Erase/export `course_completions` core never finds |

Both are read at global scope only (customer 0, tenant 0): a DELETE task runs as the admin and an EXPORT task as
the subject, so per-tenant values could make one subject's erasure and export disagree. With both OFF the plugin
behaves exactly as 1.0.3. Turning either ON is Nitin's call and needs a recorded DPO retention decision first: it
erases data (security and audit log rows, learning records) that survives today only because of the core defects.

## Capabilities (2)

| Capability | Default | Purpose |
|---|---|---|
| `local/sentientia_privacy:view` | user | Self-service page |
| `local/sentientia_privacy:manage` | none | Admin panel (also needs `local/sentientia_platform:crosstenant`) |

## Tables (2)

| Table | Purpose |
|---|---|
| `local_privacy_requests` | DPDP requests (export / account deletion), status, notes, who processed them |
| `local_privacy_consent_log` | Append-only consent ledger (proof of consent) |

Both are records of processing: the plugin's own privacy provider exports them but never deletes them.

## Message providers (1)

`privacy_request`: request notifications (popup and e-mail enabled by default).

## Tests

| File | Covers |
|---|---|
| `tests/privacy_manager_test.php` | DPDP erasure asks every Sentientia provider; partial erasures are reported |
| `tests/erasure_scope_test.php` | DPDP erasure never touches another person's rows |
| `tests/admin_scope_test.php` | Admin panel needs a cross-tenant caller (`@group tenant_isolation`) |
| `tests/erasure_completeness_test.php` | Supplementary erasure, through the real stock path (`@group privacy_erasure`) |

Run from `C:\xampp\htdocs\moodle5`: `php vendor/bin/phpunit public/local/sentientia_privacy/tests/<file>`.
