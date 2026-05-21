# ADR-003 — Hand-rolled Web Push crypto (Phase B.2.5)

- **Status:** Accepted, pending security review
- **Date:** 2026-05-21
- **Decider:** Nitin Rajput (deferred to Claude under continuous-build mandate)
- **Stream:** B — Sentientia LMS PWA

---

## Context

Phase B.2 (yesterday) shipped the push subscription backend with a stub
sender — the `push_sender::send()` interface exists, but `deliver_one()`
just logs the payload instead of actually POST-ing to the push provider.

Phase B.2.5 (today) needed to land the real delivery: ES256 VAPID JWT
signing + RFC 8291 `aes128gcm` payload encryption + HTTP POST to the
push service endpoint.

Two options were available:

### Option A — Vendor `minishlink/web-push`

Industry-standard PHP library, ~1.6M downloads on Packagist, MIT
licensed, actively maintained. Handles every quirk of every push
provider (FCM, Mozilla autopush, Apple Push, Windows WNS).

**Pros:**
- Battle-tested in production by thousands of deployments
- Author actively maintains; CVEs get patched quickly
- Less code we own
- Familiar to PHP devs who've shipped push before

**Cons:**
- ~8 transitive dependencies (`spomky-labs/base64url`, `web-token/jwt-library`,
  `phpseclib/phpseclib`, `paragonie/constant_time_encoding`, `psr/log`,
  `symfony/options-resolver`, etc.)
- Composer is not installed on the local dev XAMPP and may not be on the
  production AWS host. Adds an IT coordination task.
- Each transitive dep is a separate CVE surface.
- Vendoring without Composer (manual extract + autoload bridge) is brittle
  and error-prone across 8 nested package trees.

### Option B — Hand-roll the minimal implementation

Write ~400 lines of PHP using only `openssl` extension primitives:
`openssl_pkey_new`, `openssl_pkey_derive`, `openssl_sign`,
`openssl_encrypt`, `hash_hmac`. Implement the three RFCs directly:
- RFC 7515 — JWS Compact Serialization
- RFC 7518 §3.4 — ECDSA ES256
- RFC 8291 — Message Encryption for Web Push
- RFC 8188 — `aes128gcm` content coding

**Pros:**
- Zero supply-chain risk — we own every byte
- Single CVE surface (`openssl` extension itself, maintained by core PHP)
- No deployment changes for IT (no composer install on the prod host)
- Code is small enough (~400 LOC) for in-house security review
- Validates against RFC test vectors during dev
- Auditors prefer "small, in-house, reviewed code" over "tree of deps"
  per Nitin's note that "Airpay has auditors"

**Cons:**
- Hand-rolled crypto is famously error-prone; the literature is full of
  "don't roll your own crypto"
- We carry the maintenance burden — if RFC 8291 ever revs (unlikely),
  we have to track the change ourselves
- Bugs are subtle and may not surface during testing — only when a
  specific push provider rejects a malformed JWT

---

## Decision

**Hand-roll the implementation** (Option B), behind explicit safeguards:

1. **Maturity downgrade.** Plugin maturity dropped from `MATURITY_BETA`
   (Phase B.2) to `MATURITY_ALPHA` (Phase B.2.5). Beta promotion blocks
   on a security review pass.

2. **Default-OFF feature flag.** `sentientia.pwa.push.enabled` remains
   default OFF. The flag must be flipped via Switchboard before any
   delivery happens. The flag is the kill-switch.

3. **Self-consistency tests.** `cli/test_crypto.php` runs 18 assertions
   covering JWT sign+verify roundtrip, DER↔JOSE signature conversion, and
   aes128gcm encrypt+decrypt roundtrip (with an independent decrypter
   implementation in the test, not just our own). MUST be green before
   any deploy.

4. **Real-world smoke test.** `cli/test_push.php` sends a single push to
   a chosen userid via the actual push provider. Required before we
   declare Phase B.3 ready.

5. **Security review gate.** Before flipping the production push flag ON,
   the trio (`jwt_signer.php`, `payload_encrypter.php`, `push_sender.php`)
   must be reviewed by either:
   - Nitin personally
   - An external crypto-aware reviewer (informal — we don't need a formal
     audit, but a second pair of eyes)
   - **Or** swapped for `minishlink/web-push` if the review surfaces issues
     we can't quickly fix.

---

## Consequences

### Positive
- No deployment changes needed (no composer install on prod)
- Small (~400 LOC) review surface
- Self-contained — nothing to break when an underlying lib publishes a
  breaking change
- Educational: the team now understands every step of the Web Push protocol

### Negative
- We are now on the hook for tracking RFC changes (unlikely but possible)
- Hand-rolled crypto bugs may surface only on specific push providers
- Pre-flight checklist before production enablement is longer

### Reversibility
- **High.** If the review surfaces issues, swap `push_sender` to wrap
  `minishlink/web-push` instead. The `subscription_manager` + WS + AMD
  module stay the same — only the delivery pipeline changes. Estimated
  half-day swap effort.

---

## Files

- `local/sentientia_pwa/classes/jwt_signer.php` (236 LOC)
- `local/sentientia_pwa/classes/payload_encrypter.php` (256 LOC)
- `local/sentientia_pwa/classes/push_sender.php` (rewrote stub → real)
- `local/sentientia_pwa/cli/test_crypto.php` (self-consistency tests)
- `local/sentientia_pwa/cli/test_push.php` (real-world smoke tester)

---

## References

- [RFC 7515 — JSON Web Signature](https://datatracker.ietf.org/doc/html/rfc7515)
- [RFC 7518 §3.4 — ECDSA P-256 + SHA-256](https://datatracker.ietf.org/doc/html/rfc7518#section-3.4)
- [RFC 8030 — Web Push Protocol](https://datatracker.ietf.org/doc/html/rfc8030)
- [RFC 8188 — Encrypted Content-Encoding for HTTP](https://datatracker.ietf.org/doc/html/rfc8188)
- [RFC 8291 — Message Encryption for Web Push](https://datatracker.ietf.org/doc/html/rfc8291)
- [RFC 8292 — Voluntary Application Server Identification (VAPID)](https://datatracker.ietf.org/doc/html/rfc8292)

---

## Audit Trail

- 2026-05-21 07:30 — Drafted by Claude during Phase B.2.5 build.
- 2026-05-21 07:35 — Self-consistency tests (`test_crypto.php`) pass
  18/18 on local XAMPP (PHP 8.2.12, OpenSSL 3.x).
- _Pending_: real-world push delivery test via `test_push.php` once a
  subscriber row exists.
- _Pending_: security review by Nitin or external reviewer.
- _Pending_: promotion to `MATURITY_BETA` once review is green.
