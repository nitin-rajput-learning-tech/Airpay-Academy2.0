# local_airpay_privacy

Digital Personal Data Protection Act (DPDP, 2023) self-service. The
Indian equivalent of GDPR; this plugin gives every employee a one-page
view of what personal data the platform holds about them plus the
ability to export it or request deletion.

| Field | Value |
|---|---|
| Component | `local_airpay_privacy` |
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
workflow. The audit log helper (`local_airpay_core\audit_log`) is the
provenance source for the consent-trail view.

## Regulatory alignment

The plugin implements the DPDP Act's right-to-access (s.11), right-to-
correction (s.12) and right-to-erasure (s.12) provisions. Audit trail
is durable: even after a user record is deleted, the redacted audit row
remains for the seven-year statutory hold required by RBI guidelines
on payment-service-provider records.
