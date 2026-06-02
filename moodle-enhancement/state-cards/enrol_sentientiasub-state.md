# State card — enrol_sentientiasub

**Plugin:** `enrol_sentientiasub` (recurring-subscription enrolment)
**ADR:** ADR-023 · **Status:** v0.1.0-alpha — **increment 2 (no-sandbox skeleton)** · **2026-06-02**
**Flag:** `sentientia.subscriptions.enabled` (default **OFF**) — platform unchanged until flipped.

## What it is
A Moodle enrol plugin that grants course access while a recurring Airpay subscription is
active, suspends on a failed charge, and revokes on cancellation. One plugin, per-instance
`scope` = `allaccess | category | course` (ADR-023 "all three" decision).

## Built in increment 2 (this skeleton)
- **Data model:** `enrol_sentientiasub_subscription` (status, scope/scopeid, billingperiod,
  amount/currency, ap_mandate_id/ap_subscription_id, next/last/started/cancelled ts,
  costcenterid). Installs cleanly.
- **Lifecycle state machine** (`classes/subscription_manager.php`): create → activate →
  suspend ↔ record_cycle → cancel; `is_active`, `get`, `get_by_user_enrol`. Enrolment
  grant/revoke implemented for **scope=course**; category/allaccess record state + log a
  TODO (increment 5).
- enrol_plugin contract (`lib.php`) gated on the flag (`can_add_instance` false when OFF).
- Capabilities (4), settings (default role), feature flag, EN + HI lang (100% parity),
  GDPR privacy provider.
- **Tests:** `tests/subscription_manager_test.php` (8 cases incl. course-scope enrolment
  side-effects) for CI; locally validated 17/17 via a rolled-back-transaction smoke
  (install artifacts + state machine).

## NOT built (gated — increment 3+)
- The Airpay **mandate / `sb_*` checkout** (`enrol_page_hook`) — increment 3, **sandbox**.
- The **subscription-callback** handler + per-cycle Verify/status-check — increment 4,
  **sandbox** (inherits the 2026-06-02 payment-verification lesson).
- **scope=category / allaccess** enrolment grant (cohort/category sync) — increment 5.
- Standard add/edit instance form (`use_standard_editing_ui()` is false until increment 3).

## Validation
- 12 files, all `php -l` clean. Installed via `admin/cli/upgrade.php` (caps + setting + flag
  registered). Smoke 17/17. Pre-commit 12/12.

## Next
Increments 3-5 require the Airpay sandbox + the payment-verification fix merged. Product
decisions still open (ADR-023): billing period/pricing, charge-failure policy, target tenants.
