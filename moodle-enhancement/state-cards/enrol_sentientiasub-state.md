# State card — enrol_sentientiasub

**Plugin:** `enrol_sentientiasub` (recurring-subscription enrolment)
**ADR:** ADR-023 · **Status:** v0.2.0-alpha — **increments 2 + 5 (skeleton + cohort grant)** · **2026-06-03**
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
  grant/revoke for **scope=course** (direct enrol) AND **scope=category / allaccess**
  (cohort-sync — increment 5 ✅ 2026-06-03). Suspend removes cohort membership; reactivate re-adds.
- enrol_plugin contract (`lib.php`) gated on the flag (`can_add_instance` false when OFF).
- Capabilities (4), settings (default role + **all-access cohort**), feature flag, EN + HI
  lang (100% parity), GDPR privacy provider.
- **Tests:** `tests/subscription_manager_test.php` (10 cases — course-scope enrolment +
  cohort-scope allaccess/category) for CI; locally validated via rolled-back-transaction
  smokes (17/17 state machine + **7/7 cohort lifecycle**, zero pollution).

## NOT built (gated — increment 3+)
- The Airpay **mandate / `sb_*` checkout** (`enrol_page_hook`) — increment 3, **sandbox**.
- The **subscription-callback** handler + per-cycle Verify/status-check — increment 4,
  **sandbox** (inherits the 2026-06-02 payment-verification lesson).
- Standard add/edit instance form (`use_standard_editing_ui()` is false until increment 3).

## Validation
- 12 files, all `php -l` clean. Installed via `admin/cli/upgrade.php` (caps + setting + flag
  registered). Smoke 17/17. Pre-commit 12/12.

## Next
Increments 3-5 require the Airpay sandbox + the payment-verification fix merged. Product
decisions still open (ADR-023): billing period/pricing, charge-failure policy, target tenants.
