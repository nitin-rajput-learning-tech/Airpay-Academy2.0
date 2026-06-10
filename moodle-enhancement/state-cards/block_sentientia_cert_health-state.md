# block_airpay_cert_health — STATE CARD

**Component:** `block_airpay_cert_health`
**Current version:** `2026051300`  (1.0.0)
**Maturity:** STABLE — Sprint B compliance dashboard widget
**Created:** 2026-05-13
**Last refreshed:** 2026-05-24 (P1 state-card pass — no drift)
**Owner:** Head of L&D

---

## What it does

Surfaces certificate-email delivery health for site administrators.
Reads `local_airpay_email_log` filtered to rows that carried a
certificate PDF (`attachment_filename` or `certificate_issue_id`
non-null) and presents three KPI cards over a 7-day rolling window:

| KPI | Severity rule |
|-----|---------------|
| **Certificates emailed (7d)** | OK by default; **Warning** when sent=0 AND failed>0 (pipeline broken) |
| **Failed sends (7d)** | OK if zero; **Critical** at any non-zero value (compliance reports rely on the cert email reaching the recipient) |
| **Suppressed sends (7d)** | OK if zero; **Warning** at non-zero (informational — user opt-out or noemailever) |

Footer link drills into `/local/airpay_emails/manage.php?tab=logs`
for the full audit table.

## Accessibility

Same WCAG 2.1 AA pattern as `block_airpay_cron_health`:

- `<section role="region" aria-label>` wraps the whole widget for
  landmark navigation.
- Each KPI card has `role="group"` with an `aria-label` bundling
  number + label + severity word. Child elements `aria-hidden="true"`.
- Severity conveyed THREE ways (number colour + text badge +
  aria-label severity word) — WCAG 1.4.1 use-of-colour compliance.
- Small-text contrast palette (#15803d / #b45309 / #b91c1c) exceeds
  WCAG AA 4.5:1 against the #f8f9fc card surface.

## Visibility

- **Site admins** — block renders.
- **Other users** — `get_content()` returns null silently.

## Adding to dashboards

Site admins navigate to `/my/`, click "Customise this page",
"Add a block", and pick "Airpay Certificate Health".

## Verification

| Test | Command | Latest result |
|------|---------|---------------|
| PHPUnit | `phpunit blocks/airpay_cert_health/tests/block_test.php` | 6/6 pass, 15 assertions |
| axe-core a11y | `node moodle-enhancement/audit/playwright/a11y_block_cert_health.mjs` | 16 passes, 0 violations |
| Pre-deploy Gate 6 | runs both a11y suites (cron_health + cert_health) | green |

## Dependencies

- `local_airpay_emails` >= `2026051302` — the email plugin owns
  the `local_airpay_email_log` table that this block queries.
  Hard dependency declared in `version.php`.

## Defensive guards

`get_content()` checks `local_airpay_email_log` table existence
via `$DB->get_manager()->table_exists()` before querying. If the
`local_airpay_emails` plugin is somehow uninstalled or disabled
without the dependency check catching it, the block returns null
rather than crashing the dashboard render.

## Files

| File | Purpose |
|------|---------|
| `block_airpay_cert_health.php` | Block class (`init`, `get_content`, `kpi_card`) |
| `db/access.php` | `:addinstance` + `:myaddinstance` caps |
| `lang/en/block_airpay_cert_health.php` | Strings (KPI labels, severity badges, aria labels) |
| `tests/block_test.php` | 6 PHPUnit cases (silent-hide, KPI labels, region landmark, count accuracy, non-cert-row exclusion) |
| `version.php` | Plugin version, depends on local_airpay_emails |
| `README.md` | Plugin user-facing documentation |

---

## State card refresh — 2026-05-24

P1 state-card pass: confirmed plugin still at `2026051300` / `1.0.0`,
MATURITY_STABLE. PHPUnit `tests/block_test.php` still has 6 methods.
No code drift since ship; only touched by the Moodle 5.2 web-smoke
merge (Phase B.3) which exercised it for regression coverage. Card
remains accurate as-is.

## Feature flags

None — this is a read-only dashboard widget. Visibility is gated by
the site-admin check inside `get_content()` rather than a flag.
