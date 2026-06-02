# Security fix — paygw_airpay payment-response verification

| | |
|---|---|
| **Date** | 2026-06-02 |
| **Severity** | **Critical** (payment bypass / unauthorized fulfilment) |
| **Component** | `paygw_airpay` (Airpay payment gateway, migrated from BizLMS) |
| **File** | `payment/gateway/airpay/process.php` (+ `classes/airpay_helper.php`) |
| **Branch** | `fix/airpay-payment-verification` — **NOT yet merged to `production`** |
| **Status** | Fixed + unit-proven locally. **BLOCKED on Airpay sandbox validation before deploy.** |

## The vulnerability

`process.php` is the public payment-response callback
(`/payment/gateway/airpay/process.php`). It computed the response integrity
check (required-field presence + the `ap_SecureHash` CRC32 comparison) into
`$error_msg`, **but the guard that should act on it was commented out**:

```php
//if($error_msg)                                            // <-- guard DISABLED
$order = $DB->get_record('paygw_airpay', ['ap_orderid' => $transactionid]);
if($transactionstatus == 200){                              // <-- trusts client POST alone
    ... enrol_user() ...
}
```

So fulfilment ran on the **client-supplied** `$_POST['TRANSACTIONSTATUS'] == 200`
alone. Any user who had started a checkout (so a pending `paygw_airpay` order
with a known `ap_orderid` exists) could POST a forged success to the public
callback and receive **free, unpaid course enrolment**.

Two compounding weaknesses:
1. The response signature itself — `ap_SecureHash = sprintf('%u', crc32(...))`
   over public fields with **no secret** (Airpay v4 design, see
   `docs.airpay.co.in/v4/payments/simple-transaction/`) — is non-cryptographic
   and forgeable even with the guard enforced.
2. The kit performs **no server-to-server Order Confirmation (Verify API)** call;
   `airpay_helper::check_payment()` (the server-side reconciliation) is fully
   commented out.

## The fix (this branch)

1. **Extracted + enforced verification.** New `airpay_helper::verify_secure_hash()`
   recomputes the documented CRC32 and compares with `hash_equals()`. It **fails
   closed**: any missing required field, or missing `mercid`/`username` config,
   returns `false` (never silently skips verification). The formula is preserved
   **byte-for-byte** from the original inline code, so a legitimate response that
   matched before still matches.
2. **Restored the guard.** `process.php` now enrols only when
   `empty($error_msg) && (int)$transactionstatus === 200 && $order`.
3. **Hardening** (minor, same file): null-guarded the error-log `airpay_id` (a
   forged unknown `orderid` no longer fatals on `$order->id`), and replaced the
   undefined `$request` passed to `render_from_template()` with `[]`.

## Proof

`tests/airpay_helper_test.php` + a live read-only harness assert (5/5 green
against the real configured `mercid`/`username`):

- valid response verifies; valid UPI (with `CUSTOMERVPA`) verifies;
- **forged `TRANSACTIONSTATUS=200` with a junk hash is rejected** (the regression);
- a tampered `AMOUNT` is rejected (the hash binds the amount);
- a missing required field is rejected; missing config fails closed.

## Residual risk — why this is NOT yet on `production`

The guard was *never enforced in production*, so we have **no evidence that real
Airpay production responses actually satisfy the documented CRC32 formula**
(real `AMOUNT` decimal formatting, the exact UPI `CUSTOMERVPA` trimming/casing).
The fix cannot newly break what the old formula matched, but if legitimate
production payments only ever "worked" *because* the guard was disabled, enabling
it would reject them. **This must be validated with an Airpay sandbox round-trip
(genuine pass / forged fail / UPI pass) before merging to `production` and
deploying.**

## Recommended hardening (follow-up, sandbox session)

Add a server-side **Order Confirmation (Verify API)** call in the success path —
`POST https://kraken.airpay.co.in/airpay/pay/v4/api/verify/?token=<oauth2_token>`
with `privatekey = sha256(secret.'@'.username.':|:'.password)` + encrypted payload
— and fulfil only if Airpay independently confirms `transaction_status == 200`
for that `orderid`/`amount`. This defeats forged callbacks regardless of the
CRC32 weakness. Requires the OAuth2 + encryption specs and is best built **with**
the sandbox so the encrypted request can be iterated until Verify succeeds.
