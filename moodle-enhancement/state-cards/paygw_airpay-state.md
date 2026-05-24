# State Card — `paygw_airpay`

**Component:** `paygw_airpay`
**Version:** `2024100700.10` / `1.0.1`
**Maturity:** `MATURITY_STABLE`
**Status:** Live payment gateway on airpay.academy
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Moodle's `core_payment` payment-gateway plugin for the Airpay payment
service. Implements the standard `payment_gateway` interface so any
plugin that uses `core_payment` (course-enrol, mod_data, mod_paypal,
etc.) can charge via Airpay's payment APIs.

A 2026-05-24 security follow-up landed (PROJECT-STATE: "Session
2026-05-24 — paygw_airpay security follow-up") — see PROJECT-STATE.md
for the audit findings + remediation history.

## DB tables (3)

| Table | Purpose |
|-------|---------|
| `paygw_airpay` | Per-payment Airpay-side details (token / order id) |
| `paygw_airpay_errorlog` | Payment errors for support diagnostics |
| `paygw_course_enrolmentlog` | Audit of enrolments triggered by successful payments |

## Capabilities

None declared. Surface gating relies on core `payment` caps
(`moodle/payment:viewallpayments` etc.).

## Feature flags

None registered. Per-tenant configuration lives in the
`payment_account` plugin-config table (Moodle core).

## Key files

```
payment/gateway/airpay/
├── version.php                                   2024100700.10 / 1.0.1
├── settings.php                                  Admin settings
├── pay.php                                       Initiates payment flow
├── process.php                                   Server callback handler
├── process_old.php                               Legacy callback (kept for refund replay)
├── checksum.php                                  Public checksum endpoint
├── callback.php (implied)                        Webhook from Airpay
├── error.php                                     User-facing error display
├── style.css + styles.css                        Gateway-specific CSS
├── amd/
│   ├── src/repository.js                          AMD repository client
│   ├── src/gateways_modal.js                      Modal launcher
│   └── src/form_submit.js                         Form submission helpers
├── classes/
│   ├── gateway.php                                core_payment gateway impl
│   ├── airpay_helper.php                          API client wrapper
│   ├── checksum.php                               Hash + verify
│   ├── external/get_form.php                      WS: returns the payment form
│   └── privacy/provider.php                       GDPR/DPDP
├── db/
│   ├── install.xml                                3 tables
│   ├── install.php                                Post-install setup
│   ├── upgrade.php
│   └── services.php                               WS function registry
├── templates/
│   ├── airpay_button_placeholder.mustache
│   └── payment_form.mustache
├── pix/img.svg                                    Gateway logo
├── lang/en/paygw_airpay.php
└── tests/
    ├── gateway_test.php                           8 methods (core_payment interface)
    ├── checksum_test.php                          10 methods (hash + verify)
    ├── airpay_helper_test.php                     6 methods (API client)
    └── privacy_provider_test.php                  4 methods (28 total)
```

## Tests

4 PHPUnit classes, 28 methods. Highest-coverage area is the
checksum implementation (security-critical).

## Open items

- [ ] Hindi `lang/hi/paygw_airpay.php` (parity drive sweeps every plugin)
- [ ] Phase B — refund flow audit + idempotency tests
- [ ] Remove `process_old.php` once a quarter passes with no replay traffic
- [ ] Behat coverage for the modal launcher (currently PHPUnit only)
- [ ] WhatsApp / SMS payment receipt callback (Phase C.1 integration with
      `local_airpay_whatsapp` + `local_airpay_notifications`)

## State card created — 2026-05-24

Initial state card. Plugin has been live since 2024-10; the 2026-05-24
security follow-up was the first audited touch in this state-card era.
Created now as part of the P1 state-card pass.
