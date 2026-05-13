# local_airpay_pages

Static pages + homepage + QR-onboarding flow. The marketing-facing and
onboarding-facing layer of the platform.

| Field | Value |
|---|---|
| Component | `local_airpay_pages` |
| Version | beta |
| Depends on | `local_airpay_org` |

## What it does

- Homepage editor (replaces Moodle's default front page).
- Static-page rendering for terms-of-use, privacy-policy, contact-us etc.
- QR-onboarding flow: visit `/local/airpay_pages/qr.php?cohort=X` from a
  printed QR code to land on a self-onboarding form.
- Public-tenant landing page entry at `/`.

## Tables

None — pages are stored as Moodle blocks + HTML fragments.

## Front-page replacement

Commit `c77f96dfd` (5 April 2026) switched the front page from a
redirect to the custom Airpay homepage rendered by this plugin.

## Privacy / GDPR

No personal data stored. The QR-onboarding form writes to the standard
`mdl_user` table via the platform's standard user-creation flow.

## Open backlog

- Page editor UI is currently file-based; an in-platform CMS would be
  more accessible for non-technical L&D users.
- A/B test infrastructure on the homepage (out of scope for v2).
