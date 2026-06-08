# block_airpay_cert_health

Dashboard widget for Airpay Academy site administrators — surfaces
certificate-email delivery health at-a-glance.

| Field | Value |
|---|---|
| Component | `block_airpay_cert_health` |
| Version | `2026051300` (1.0.0) |
| Depends on | `local_sentientia_emails` >= 2026051302 |
| Maturity | STABLE |

## What it does

Reads `local_airpay_email_log` (filtered to rows that carried a
certificate PDF — `attachment_filename` or `certificate_issue_id`
non-null) and presents three KPI cards over a 7-day rolling window:

| KPI | Severity rule |
|-----|---------------|
| **Certificates emailed (7d)** — Sent count | OK by default; Warning when sent=0 AND failed>0 (pipeline broken) |
| **Failed sends (7d)** — Failed count | OK if zero; Critical at any non-zero value |
| **Suppressed sends (7d)** — User-opt-out + noemailever count | OK if zero; Warning at non-zero (informational) |

Footer link drills into `/local/sentientia_emails/manage.php?tab=logs`
for the full audit table.

## Accessibility

Same WCAG 2.1 AA pattern as `block_airpay_cron_health`:

- `<section role="region" aria-label>` wraps the whole widget so
  screen-reader users can jump to it via landmark navigation.
- Each KPI card has `role="group"` with an `aria-label` bundling
  number + label + severity word ("1 Failed sends (7d), severity
  Critical"). Child elements are `aria-hidden="true"` so the
  screen reader announces the card as one unit.
- Severity is conveyed THREE ways (number colour + text badge +
  aria-label severity word) so colour-blind sighted users and
  screen reader users all get the signal.
- Small-text contrast palette (#15803d / #b45309 / #b91c1c)
  exceeds WCAG AA 4.5:1 against the #f8f9fc card surface.

## Visibility

- **Site admins** — block renders.
- **Other users** — `get_content()` returns null, so the block
  silently hides itself rather than showing an empty placeholder.

## Adding to dashboards

Two surfaces it can sit on:
- `/my/` (the user dashboard) — Site admin needs to add it via
  "Customise this page → Add a block → Airpay Certificate Health".
- `/admin/index.php` (admin dashboard) — same flow.

## a11y verification

```
node moodle-enhancement/audit/playwright/a11y_block_cert_health.mjs
```

Last verified clean: 2026-05-13 — 16 passes, 0 violations.
Also wired into pre_deploy_validate.sh Gate 6 alongside
`a11y_block_cron_health`.

## Defensive guards

`get_content()` checks `local_airpay_email_log` table existence
before querying. If the `local_sentientia_emails` plugin is uninstalled
or disabled, the block silently hides (returns null) rather than
throwing.
