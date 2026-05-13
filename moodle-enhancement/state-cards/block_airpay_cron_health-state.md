# block_airpay_cron_health — STATE CARD

**Component:** `block_airpay_cron_health`
**Current version:** `2026051300`  (1.0.0)
**Maturity:** STABLE
**Last touched:** 2026-05-13 (PHPUnit test added)
**Owner:** Head of L&D

---

## What it does

Surfaces the output of `\local_airpay_core\cron_health::summary()`
as a three-card dashboard widget for site administrators:

| KPI | Severity rule |
|-----|---------------|
| **Airpay tasks stuck** | OK at 0; **Critical** at any non-zero (the airpay platform's cron is broken) |
| **Other tasks stuck**  | OK at 0; **Warning** at non-zero (Moodle core or other plugins; informational only) |
| **In failure backoff** | OK at 0; **Warning** at non-zero (Moodle's exponential-backoff retry kicked in) |

Drills down into a `<ul>` of overdue task names with their human-
formatted overdue duration ("overdue 5h 23m") and a separate list
of tasks in `faildelay` exponential-backoff. Footer link goes to
`/admin/tasklogs.php`.

## Accessibility (Engineering 21 fixed in 2026-05)

Same WCAG 2.1 AA pattern as `block_airpay_cert_health`:

- `<section role="region" aria-label>` wraps the whole widget for
  landmark navigation.
- Each KPI card has `role="group"` with an `aria-label` bundling
  number + label + severity word. Child elements `aria-hidden="true"`.
- Severity is conveyed THREE ways (number colour + text badge +
  aria-label severity word) — WCAG 1.4.1 use-of-colour compliance.
- Small-text contrast palette (#15803d / #b45309 / #b91c1c) exceeds
  WCAG AA 4.5:1 against the #f8f9fc card surface.
- The sub-section headings are `<h3>` (Engineering 21 fixed h2→h5
  → h2→h3 heading-order violation).

## Visibility

- **Site admins** — block renders.
- **Other users** — `get_content()` returns null. The block hides
  itself silently rather than showing an empty placeholder.

## Adding to dashboards

Site admins navigate to `/my/`, click "Customise this page",
"Add a block", and pick "Airpay Cron Health".

## Verification

| Test | Command | Latest result |
|------|---------|---------------|
| PHPUnit | `phpunit blocks/airpay_cron_health/tests/block_test.php` | 5/5 pass, 12 assertions |
| axe-core a11y | `node moodle-enhancement/audit/playwright/a11y_block_cron_health.mjs` | 18 passes, 0 violations |
| Pre-deploy Gate 6 | runs both a11y suites (cron_health + cert_health) | green |

## Dependencies

- `\local_airpay_core\cron_health` — the helper class that does
  the `task_scheduled` SQL queries. Block reads but never writes.

## Defensive guards

`get_content()` doesn't check for the cron_health class existence
because `local_airpay_core` is a hard dependency declared in
`version.php`. If the helper class is missing the upgrade would
have refused to install the block.

## Files

| File | Purpose |
|------|---------|
| `block_airpay_cron_health.php` | Block class (`init`, `get_content`, `kpi_card`) |
| `db/access.php` | `:addinstance` + `:myaddinstance` caps |
| `lang/en/block_airpay_cron_health.php` | Strings (KPI labels, severity badges, aria labels) |
| `tests/block_test.php` | 5 PHPUnit cases |
| `version.php` | Plugin version, depends on local_airpay_core |
