# local_sentientia_privacy

Digital Personal Data Protection Act (DPDP, 2023) self-service. The
Indian equivalent of GDPR; this plugin gives every employee a one-page
view of what personal data the platform holds about them plus the
ability to export it or request deletion.

| Field | Value |
|---|---|
| Component | `local_sentientia_privacy` |
| Version | `2026040100` (1.0.0) |
| Depends on | — |

## What it does

- Self-service DSR (Data Subject Request) page accessible to every
  logged-in user.
- "Export my data" — bundles every airpay_* plugin's privacy provider
  output into a single ZIP download.
- "Delete my data" — initiates a delete request that the Data Protection
  Officer reviews and approves (full delete cannot be self-service for
  audit reasons).
- Consent dashboard — tracks acceptance of platform terms, cookie
  policy, proctoring recording.
- DPO contact email (`academy@airpay.co.in`) is the canonical channel.

## Capabilities (2)

`:dsr_act` (write — user submits a request) and `:dsr_view` (read —
DPO reviews queue).

## Tables

DSR queue, consent log, audit trail of decisions.

## Message providers

DSR submitted (to DPO), DSR completed (to user), consent recorded
(audit-only).

## Phase 8.1 dependency

Every Phase 8.1 plugin (cart, proctoring, recompletion, request) ships
a `classes/privacy/provider.php` whose `_export_user_data` and
`_delete_data_for_user` methods are called through this plugin's DSR
workflow. The audit log helper (`local_sentientia_platform\audit_log`) is the
provenance source for the consent-trail view.

## Regulatory alignment

The plugin implements the DPDP Act's right-to-access (s.11), right-to-
correction (s.12) and right-to-erasure (s.12) provisions. Audit trail
is durable: even after a user record is deleted, the redacted audit row
remains for the seven-year statutory hold required by RBI guidelines
on payment-service-provider records.

## Core erasure supplement (ADR-035, 1.1.0, 2026-11-13)

Two stock Moodle defects leave personal data behind when core's
`tool_dataprivacy` processes a 'Deleted' (or export) request:

1. A context list stops iterating at its first duplicate context id
   (`contextlist_base::set_contextids()` is `array_unique()` without a
   reindex). `tool_dataprivacy` builds the approved list by iterating, so
   `logstore_standard` logs and `report_stats` statistics are cut short.
2. `core_course` finds course completions only through completion
   criteria, so a completion in a course without criteria is never found.

This plugin's privacy provider now also finds those contexts itself, in one
`UNION` query (so its own list cannot be cut short), and re-runs core's own
delete and export code on them: `logstore_standard` (the rows the subject
performed; rows naming them only as related or real user stay, as in core),
`report_stats`, and `core_completion::delete_completion()`. The plugin's own
tables (requests, consent log) are still kept as the record of processing.

- Only core's `tool_dataprivacy` route reaches the supplement. The DPDP
  admin panel (`privacy_manager::process_deletion()`) skips this plugin, so
  its promise to keep anonymised learning records holds.
- Deletes are idempotent, so running before or after the core providers
  ends in the same state.
- No schema change, no feature flag (Nitin, 2026-11-13).
- Tests: `tests/privacy_supplement_test.php`. Two tests skip, rather than
  fail, once Moodle fixes either defect upstream; that is the prompt to retire
  the matching part of the supplement.

