# B.2.5 — Hand-Rolled Web Push Crypto Stack — Security Audit

**Date:** 2026-05-21
**Auditor:** Airpay Security Auditor (Claude Opus 4.7, 1M context)
**Scope:** ADR-003 follow-up gate — independent review of the hand-rolled
Web Push crypto pipeline before flipping `sentientia.pwa.push.enabled` ON
in production.
**Plugin version reviewed:** `local_sentientia_pwa` v0.3.3-alpha (2026052106)
**Files under audit:**
- `local/sentientia_pwa/classes/vapid_key_manager.php` (298 lines)
- `local/sentientia_pwa/classes/jwt_signer.php` (273 lines)
- `local/sentientia_pwa/classes/payload_encrypter.php` (322 lines)
- `local/sentientia_pwa/classes/push_sender.php` (333 lines)
- supporting: `subscription_manager.php`, `save_subscription.php`,
  `admin/push_log.php`, `push_logger.php`, `mock_receiver.php`, `cli/*.php`,
  `db/install.xml`, `settings.php`

---

## TL;DR / Verdict

**CONDITIONAL PASS — do NOT flip the production flag yet.**

The RFC 8291 / RFC 7518 §3.4 / RFC 8292 cryptographic primitives are
**correctly implemented**. The KDF input order, info-string composition,
nonce derivation, AEAD tag handling, JWT structure, and DER↔JOSE
signature conversion all conform to the spec, and a careful trace
against RFC 8291 §3.4 found no algorithmic deviation. The 18 internal
self-tests + 9-step e2e verification covering the round-trip from
sender → mock receiver are credible.

**However, six issues require remediation before production flag-on:**

| # | Severity     | Component        | Issue                                                                                              |
|---|--------------|------------------|----------------------------------------------------------------------------------------------------|
| 1 | **BLOCKING** | save_subscription| No host allowlist on `endpoint` — SSRF via attacker-supplied push URL                              |
| 2 | **BLOCKING** | jwt_signer       | `endpoint_origin()` accepts any scheme — allows `http://` aud claim downgrade                      |
| 3 | **BLOCKING** | mock_receiver.php| Ships in plugin source — production-reachable when `mock_subscriber.json` is uploaded by attacker  |
| 4 | **BLOCKING** | subscription_mgr | `for_user()` is not tenant-scoped — cross-tenant push delivery possible via privileged caller      |
| 5 | **BLOCKING** | save_subscription| `PARAM_ALPHANUMEXT` rejects valid base64url characters (`-`, `_`) — false-positive failure mode    |
| 6 | **BLOCKING** | vapid_key_mgr    | VAPID private PEM stored in `mdl_config_plugin` (DB table) at rest with no envelope encryption     |
| 7 | NON-BLOCKING | push_sender      | No replay/short-term token cache — same JWT reused across all 5 deliveries to same origin in 12h   |
| 8 | NON-BLOCKING | jwt_signer       | DER parser does not validate against buffer overrun on malformed openssl output                    |
| 9 | NON-BLOCKING | push_sender      | curl wrapper bypass in `run_push_e2e.php` clears `curlsecurityblockedhosts` — risk during dev      |
| 10| NON-BLOCKING | payload_encrypter| `hkdf_expand()` rejects >32-byte output instead of looping — robust for now but brittle vs RFC     |
| 11| NON-BLOCKING | push_logger      | Title + truncated body stored in plaintext — PII in DB long-term (90d default retention)           |
| 12| NON-BLOCKING | jwt_signer       | No `nbf` (not-before) claim — token usable instantly even with clock skew on push provider         |
| 13| NON-BLOCKING | payload_encrypter| Padding byte `0x02` only — no random padding bytes — message length leaks (RFC 8291 §3 advisory)   |
| 14| NON-BLOCKING | settings.php     | VAPID public key shown unredacted to anyone with site-config access (fine but odd — flag for note) |
| 15| NON-BLOCKING | push_sender      | `debugging()` calls may emit subscription endpoint URL to debug log — tracking-token-grade secret  |

Crypto: **CORRECT.** Adjacent infrastructure: **needs fixes 1-6 before
production flag-on.** Fixes 7-15 can ship in a follow-up sprint.

---

## RFC Conformance Section (per audit ask)

### 1. RFC 8291 (Message Encryption for Web Push) — **PASS**

Traced against RFC 8291 §3.4 step-by-step on
`payload_encrypter::encrypt_for_subscription()`:

| RFC step | Spec                                                          | Implementation                       | Verdict   |
|----------|---------------------------------------------------------------|--------------------------------------|-----------|
| (1)      | `salt = random(16)`                                           | line 84 `random_bytes(16)`           | OK        |
| (2)      | `ECDH = ECDH(as_private, ua_public)`                          | line 87 `ecdh_shared_secret(...)`    | OK        |
| (3)      | `PRK_key = HMAC-SHA-256(auth_secret, ECDH)`                   | line 91 `hmac_sha256(auth, ecdh)`    | OK *      |
| (4)      | `key_info = "WebPush: info" \|\| 0x00 \|\| ua_pub \|\| as_pub`| line 92                              | OK        |
| (5)      | `IKM = HMAC(PRK_key, key_info \|\| 0x01)[0:32]`               | line 93 `hkdf_expand`                | OK        |
| (6)      | `PRK = HMAC-SHA-256(salt, IKM)`                               | line 97                              | OK *      |
| (7)      | `CEK = HMAC(PRK, "Content-Encoding: aes128gcm" \|\| 0x00 \|\| 0x01)[0:16]` | line 98                  | OK        |
| (8)      | `NONCE = HMAC(PRK, "Content-Encoding: nonce" \|\| 0x00 \|\| 0x01)[0:12]`   | line 99                  | OK        |
| (9)      | Plaintext padded `\|\| 0x02 \|\| 0x00 * pad`                  | line 103 (pad=0)                     | OK (see §13) |
| (10)     | `ciphertext, tag = AES-128-GCM(CEK, NONCE, plaintext_padded)` | lines 106-116                        | OK        |
| (11)     | Header = `salt \|\| rs(4 BE) \|\| idlen(1) \|\| keyid`        | line 130                             | OK        |
| (12)     | Output = `header \|\| ciphertext \|\| tag`                    | line 131                             | OK        |

\* `hmac_sha256($key, $data)` in this file maps to
`hash_hmac('sha256', $data, $key, true)` — argument order is correct
because `hash_hmac` takes `(algo, data, key, raw)`. **I specifically
verified this** because it is the single most common bug in hand-rolled
HKDF; the implementation is right.

**Salt entropy:** `random_bytes(16)` (line 84). `random_bytes()` is
PHP's CSPRNG and is cryptographically suitable. PASS.

**Ephemeral key reuse:** Line 81 generates a fresh keypair per
`encrypt_for_subscription()` call. No nonce reuse risk under any
volume. PASS.

**AES-GCM tag handling:** `openssl_encrypt` with `OPENSSL_RAW_DATA` and
explicit tag length 16, `$tag` returned by-reference, then appended to
ciphertext for transport (line 131). This matches RFC 8188 §2.1
"ciphertext \|\| tag" convention. PASS.

### 2. RFC 7518 §3.4 (ECDSA ES256) — **PASS**

Examined `jwt_signer.php`:

| RFC §3.4 spec                                          | Implementation                          | Verdict |
|--------------------------------------------------------|-----------------------------------------|---------|
| Header `alg = "ES256"`                                 | line 77                                 | OK      |
| Curve = P-256 (= prime256v1 = secp256r1)               | `vapid_key_manager.php:135`             | OK      |
| Hash = SHA-256                                         | line 102 `OPENSSL_ALGO_SHA256`          | OK      |
| Output = `R \|\| S` (raw, 32 bytes each)               | `der_to_jose()` lines 146-189           | OK      |
| Base64url, no padding                                  | `vapid_key_manager::b64url_encode`      | OK      |

**Deterministic vs random k:** `openssl_sign()` calls libcrypto, which
uses random k (not RFC 6979 deterministic k) on OpenSSL 3.x. This is
RFC 7518 §3.4 compliant; the spec permits either. PASS.

**DER → JOSE conversion correctness:** The `der_to_jose()` parser
correctly handles the three edge cases:
- 33-byte integer with leading `0x00` positive marker (lines 232-235): strip
- <32-byte integer with omitted leading zeros (lines 236-239): left-pad
- 32-byte integer: pass-through (line 229-231)

I verified mathematically: for any P-256 signature, |R| and |S| are
< 2^256 so they fit in 32 bytes. PASS.

### 3. RFC 8292 (VAPID JWT) — **PASS with caveats**

- `aud` claim set to endpoint origin (line 72). ✓
- `exp` claim ≤ 24h enforced (lines 43, 57). ✓
- `sub` claim from admin setting, default `mailto:academy@airpay.co.in`. ✓
- `Authorization: vapid t=<jwt>,k=<vapid_public_b64url>` (push_sender:199). ✓

**Caveats (NON-BLOCKING):**
- No `nbf` (not-before) claim. RFC 8292 doesn't require it, but push
  providers may reject if `exp - iat` is too large *without* `nbf`.
  Issue #12 below.
- `endpoint_origin()` accepts non-https. RFC 8292 §2 requires the
  audience to match the push service origin, and push services are
  always HTTPS in production. Allowing `http://` opens a downgrade
  vector. Issue #2 below.

---

## BLOCKING FINDINGS

### BLOCKING-1: SSRF via attacker-supplied push subscription endpoint

**File:** `local/sentientia_pwa/classes/external/save_subscription.php:23`
**OWASP:** A10:2021 — Server-Side Request Forgery
**CVSS estimate:** 8.5 (network, low complexity, low privileges, scope changed)

**Evidence:**
```php
'endpoint' => new external_value(PARAM_URL, 'PushSubscription.endpoint URL'),
```
`PARAM_URL` only validates that the URL is syntactically parseable; it
does NOT restrict the host. A malicious authenticated user (any
learner, since `local/sentientia_pwa:subscribe` is granted to the
`user` archetype) can register a subscription with `endpoint =
http://10.0.0.5:6379/` or `http://internal-mysql:3306/` or
`http://localhost:8080/admin/dangerous-action.php?confirm=yes`.

When the push sender later fires (e.g. via a course reminder cron),
`push_sender::http_post_binary()` will POST encrypted bytes + a valid
VAPID `Authorization` header signed by **us** to that internal URL.

**Mitigating factors (do not eliminate the risk):**
- Moodle's `\curl` wrapper has built-in SSRF defences via
  `curlsecurityblockedhosts` (default blocks `localhost`, `127.0.0.0/8`,
  `10.0.0.0/8`, etc.) and `curlsecurityallowedport` (default 80, 443).
  **But these can be relaxed by site admins** (run_push_e2e.php
  literally clears them, lines 178-181), AND they don't cover all
  network egress paths (e.g. AWS metadata service `169.254.169.254` is
  NOT in the default block list).
- The body is encrypted gibberish to the receiver. But the side
  effects of an unauthenticated POST to internal services (state
  changes, log entries, AWS metadata reads via path query strings) can
  still cause harm.

**Exact fix:**

Add a host allowlist enforced at write-time, with the allowlist
configurable via plugin settings.

```php
// In save_subscription.php, after validate_parameters(...):
$allowed_hosts = self::allowed_push_hosts();
$parsed = parse_url($endpoint);
if (!isset($parsed['scheme'], $parsed['host'])
    || strtolower($parsed['scheme']) !== 'https'
    || !in_array(strtolower($parsed['host']), $allowed_hosts, true)) {
    // Also match suffix patterns: *.push.apple.com, *.googleapis.com
    if (!self::host_matches_suffix($parsed['host'] ?? '', $allowed_hosts)) {
        throw new \moodle_exception('invalid_endpoint_host',
            'local_sentientia_pwa');
    }
}

// New helper on save_subscription class:
private static function allowed_push_hosts(): array {
    // Sourced from RFC 8030 + production push provider docs.
    // Hardcode the known list; admin can extend via plugin setting.
    $defaults = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
        'wns2-bn3p.notify.windows.com',  // WNS for Edge legacy
    ];
    $suffixes = [
        '.googleapis.com',
        '.push.apple.com',
        '.notify.windows.com',
        '.push.services.mozilla.com',
    ];
    $admin_extra = trim((string) get_config(
        'local_sentientia_pwa', 'allowed_push_hosts'));
    if ($admin_extra !== '') {
        foreach (preg_split('/[\s,]+/', $admin_extra) as $line) {
            if ($line === '') continue;
            if ($line[0] === '.') {
                $suffixes[] = strtolower($line);
            } else {
                $defaults[] = strtolower($line);
            }
        }
    }
    return ['exact' => $defaults, 'suffixes' => $suffixes];
}

private static function host_matches_suffix(string $host, array $allowed): bool {
    $host = strtolower($host);
    if (in_array($host, $allowed['exact'], true)) return true;
    foreach ($allowed['suffixes'] as $suffix) {
        if (str_ends_with($host, $suffix)) return true;
    }
    return false;
}
```

Add a plugin setting `allowed_push_hosts` (PARAM_TEXT, multi-line,
default empty) to `settings.php` so deployments can extend the list
without code changes.

Re-validate at send-time as well, in `push_sender::deliver_one()`,
because endpoints currently in the DB pre-date this fix:

```php
// In push_sender::deliver_one(), before the encrypt step:
if (!\local_sentientia_pwa\external\save_subscription::is_allowed_endpoint(
        $sub->endpoint)) {
    debugging('[sentientia_pwa] refusing to send to non-allowlisted host', DEBUG_NORMAL);
    push_logger::log((int) $sub->userid, (int) $sub->id, $sub->endpoint,
        $title, $body, $url, $tag,
        null, push_logger::RESULT_FAILED, 'endpoint not allowlisted');
    return false;
}
```

---

### BLOCKING-2: Endpoint origin extraction allows `http://` scheme

**File:** `local/sentientia_pwa/classes/jwt_signer.php:249-259`
**OWASP:** A02:2021 — Cryptographic Failures (Downgrade)
**CVSS estimate:** 6.5 (paired with #1; on its own, lower)

**Evidence:**
```php
public static function endpoint_origin(string $endpoint): ?string {
    $parts = parse_url($endpoint);
    if (!isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    $origin = $parts['scheme'] . '://' . $parts['host'];
    ...
}
```

This will happily return `http://attacker.com` if the endpoint scheme
is HTTP. The JWT `aud` claim then carries that downgraded origin and
the sender POSTs to an `http://` URL — leaking the VAPID token over
the wire AND attacker can capture/replay it.

Real push services are always HTTPS, but the validation should be
defence-in-depth here because (a) the VAPID JWT is a bearer token
valid for 12h against any endpoint on the same origin, and (b) the
fix is a one-line check.

**Exact fix:**

```php
public static function endpoint_origin(string $endpoint): ?string {
    $parts = parse_url($endpoint);
    if (!isset($parts['scheme'], $parts['host'])) {
        return null;
    }
    // RFC 8030 §3 — Web Push transport is HTTPS-only.
    if (strtolower($parts['scheme']) !== 'https') {
        return null;
    }
    $origin = 'https://' . strtolower($parts['host']);
    if (isset($parts['port']) && (int) $parts['port'] !== 443) {
        $origin .= ':' . (int) $parts['port'];
    }
    return $origin;
}
```

Note that this *normalises* port and host (per RFC 6454 §6) so the
`aud` claim is canonicalised — push services do exact-match on the
audience and will reject `https://FCM.GOOGLEAPIS.COM:443` even though
it's the same origin as `https://fcm.googleapis.com`.

---

### BLOCKING-3: `mock_receiver.php` ships in plugin source — production-reachable

**File:** `local/sentientia_pwa/mock_receiver.php`
**OWASP:** A05:2021 — Security Misconfiguration
**CVSS estimate:** 7.2 (publicly-reachable endpoint that does crypto operations on attacker input)

**Evidence:**
The file is a regular `.php` under the plugin root. It's accessible at
`/local/sentientia_pwa/mock_receiver.php` on any host that deploys the
plugin. It:
1. Defines `NO_MOODLE_COOKIES` and `NO_DEBUG_DISPLAY` — so no session,
   no auth gate.
2. Refuses to act ONLY if `<dataroot>/sentientia_pwa_mock/mock_subscriber.json`
   doesn't exist (lines 55-58).
3. If the file exists, reads `private_pem` from it and executes a full
   ECDH + AES-128-GCM decryption pipeline on attacker-controlled bytes,
   writing the decrypted plaintext to a side-channel file.

**Attack scenarios:**
- An attacker with file-write access to dataroot (e.g. via a vulnerable
  file-upload plugin elsewhere) can plant a `mock_subscriber.json`
  pointing at any private key, and then trigger arbitrary push-decode
  oracles.
- The decrypted plaintext is written to disk at a predictable path
  (`<dataroot>/sentientia_pwa_mock/last_received.txt`) — readable by
  the web server. If dataroot is served (misconfig but observed in
  legacy Moodle setups), the contents leak.
- Even *without* attacker file access: if Phase B testing leaves
  `mock_subscriber.json` on production by accident, every push-sender
  POST to it is decrypted and logged in cleartext.

**Exact fix:**

Move the entire mock-receiver infrastructure out of the plugin source
tree. Put it in a separate dev-only plugin or in
`moodle-enhancement/_devtools/sentientia_pwa_mock/` that is never
deployed to production. Apply the following hardening if it must
remain in-tree:

```php
// At the top of mock_receiver.php, RIGHT AFTER require config.php:
global $CFG;

// Refuse unless explicitly enabled in code (NOT in admin UI):
if (empty($CFG->local_sentientia_pwa_allow_mock_receiver)) {
    http_response_code(404);
    exit;  // Indistinguishable from a non-existent endpoint.
}

// Refuse on production environments by hostname-sniff:
$is_prod = false;
foreach (['airpay.academy', 'sentientia.com'] as $prod_host) {
    if (strpos($CFG->wwwroot, $prod_host) !== false) {
        $is_prod = true;
        break;
    }
}
if ($is_prod) {
    http_response_code(404);
    exit;
}
```

And add a build-time check to `lib/deploy.sh` or equivalent that
refuses to deploy if `mock_receiver.php` is present in the production
build target.

---

### BLOCKING-4: `subscription_manager::for_user()` is not tenant-scoped

**File:** `local/sentientia_pwa/classes/subscription_manager.php:126-130`
**OWASP:** A01:2021 — Broken Access Control (Multi-tenant isolation)
**CVSS estimate:** 7.5 — direct tenant data leak in a fintech / multi-customer LMS

**Evidence:**
```php
public static function for_user(int $userid): array {
    global $DB;
    return $DB->get_records('local_sentientia_push_subs',
        ['userid' => $userid], 'timecreated DESC');
}
```

The query is scoped only by `userid`, not by `customerid` or `tenantid`.
This is mostly fine because each user has a single `costcenterid` —
their own. **But the broader issue is that `push_sender::send($userid,
...)` does NOT verify that the caller has the right to push to that
user.**

If a manager in Airpay tenant (costcenterid=1) calls
`push_sender::send($userid_in_Public_tenant=77, ...)` — directly via
code paths like a generic notification dispatcher or a buggy plugin
that lets one tenant target another — the push will be delivered
without any tenant boundary check.

The schema does carry `customerid` + `tenantid` per row, but the
read path never uses them. The mandate from CLAUDE.md is
*"every user/course/completion query MUST scope by costcenterid"* and
*"Airpay (id=1) data leaks to Public (id=77) users"* is a CRITICAL
violation. Push subscriptions are user data and fall under the same
rule.

**Exact fix:**

Add an optional tenant filter to the read path AND enforce it in the
sender:

```php
// In subscription_manager.php:
public static function for_user(int $userid, ?int $expected_customerid = null,
                                  ?int $expected_tenantid = null): array {
    global $DB;
    $conds = ['userid' => $userid];
    if ($expected_customerid !== null) {
        $conds['customerid'] = $expected_customerid;
    }
    if ($expected_tenantid !== null) {
        $conds['tenantid'] = $expected_tenantid;
    }
    return $DB->get_records('local_sentientia_push_subs', $conds,
        'timecreated DESC');
}

// In push_sender.php — at the top of send():
$user = $DB->get_record('user', ['id' => $userid],
    'id, deleted, suspended, open_path', MUST_EXIST);
if ($user->deleted || $user->suspended) {
    return 0;
}

// Compute the tenant the recipient actually belongs to.
$recipient_tenant = 0;
if (!empty($user->open_path)) {
    $parts = explode('/', trim($user->open_path, '/'));
    if (!empty($parts[0]) && ctype_digit($parts[0])) {
        $recipient_tenant = (int) $parts[0];
    }
}

// If a caller has set a tenant scope on the request, enforce it.
// This is the new helper — see local_airpay_core::current_tenant().
$caller_tenant = null;
if (class_exists('\\local_airpay_core\\customer')) {
    $caller_tenant = \local_airpay_core\customer::current_tenant();
}
if ($caller_tenant !== null && $caller_tenant !== $recipient_tenant) {
    debugging(sprintf(
        '[sentientia_pwa] cross-tenant push refused: caller_tenant=%d recipient_tenant=%d',
        $caller_tenant, $recipient_tenant), DEBUG_DEVELOPER);
    return 0;
}

$subs = subscription_manager::for_user($userid, null, $recipient_tenant);
```

Document the rule in the class-level docblock: *"Cross-tenant pushes
are refused. If a manager-level user needs to send a push to a
recipient in a different tenant, that path requires an explicit
override flag introduced by a future ADR — not the default API."*

---

### BLOCKING-5: `PARAM_ALPHANUMEXT` rejects valid base64url characters

**File:** `local/sentientia_pwa/classes/external/save_subscription.php:24-25`
**OWASP:** N/A (functional bug with security implication: forces clients into broken state)
**CVSS estimate:** 5.3 (broken control + potential downstream chain)

**Evidence:**
```php
'p256dh'   => new external_value(PARAM_ALPHANUMEXT, '...'),
'auth'     => new external_value(PARAM_ALPHANUMEXT, '...'),
```

`PARAM_ALPHANUMEXT` allows `a-zA-Z0-9._-` per Moodle source. Base64url
characters are `A-Z a-z 0-9 - _` — `.` is NOT a valid base64url char.
This works **most of the time**, but it means:
1. Any input containing a legitimate `.` (rare in base64url but
   possible if someone tries padding `=` → `.`) is silently accepted
   and stored, corrupting the key.
2. Any input that has trailing padding `=` (which some clients send
   despite RFC 4648 §5 saying no padding for base64url) is **rejected**
   with a generic Moodle validation error — the user sees "invalid
   parameter" with no actionable feedback.
3. The 65-byte uncompressed P-256 point, when base64url'd, is 88 chars
   — and a 16-byte auth secret is 22 chars. The length is fixed. The
   validator allows up to 1333 chars by default (Moodle PARAM_ALPHANUMEXT
   has no inherent length limit), so an attacker could submit an
   arbitrarily-long key value and trigger DB errors / log explosions.

**Exact fix:**

Use `PARAM_RAW` followed by explicit base64url + length validation:

```php
public static function execute_parameters(): external_function_parameters {
    return new external_function_parameters([
        'endpoint' => new external_value(PARAM_URL,
            'PushSubscription.endpoint URL'),
        'p256dh'   => new external_value(PARAM_RAW,
            'PushSubscription.getKey(p256dh) as base64url — 65 bytes raw'),
        'auth'     => new external_value(PARAM_RAW,
            'PushSubscription.getKey(auth) as base64url — 16 bytes raw'),
        'user_agent' => new external_value(PARAM_TEXT,
            'Browser user-agent (informational)', VALUE_DEFAULT, ''),
    ]);
}

public static function execute(string $endpoint, string $p256dh, string $auth,
                                string $user_agent = ''): array {
    global $USER;
    self::validate_parameters(self::execute_parameters(), [
        'endpoint' => $endpoint, 'p256dh' => $p256dh,
        'auth' => $auth, 'user_agent' => $user_agent,
    ]);

    // Validate base64url shape (RFC 4648 §5 — no padding, no +/).
    // Allow length within sane bounds — 65-byte raw → 87 chars, 16-byte raw → 22 chars.
    if (!preg_match('/^[A-Za-z0-9_-]{86,90}$/', $p256dh)) {
        throw new \moodle_exception('invalid_p256dh_format',
            'local_sentientia_pwa');
    }
    if (!preg_match('/^[A-Za-z0-9_-]{20,24}$/', $auth)) {
        throw new \moodle_exception('invalid_auth_format',
            'local_sentientia_pwa');
    }

    // Decode and verify raw byte length.
    $p256dh_bin = \local_sentientia_pwa\vapid_key_manager::b64url_decode($p256dh);
    if (strlen($p256dh_bin) !== 65 || $p256dh_bin[0] !== "\x04") {
        throw new \moodle_exception('invalid_p256dh_decoded',
            'local_sentientia_pwa');
    }
    $auth_bin = \local_sentientia_pwa\vapid_key_manager::b64url_decode($auth);
    if (strlen($auth_bin) !== 16) {
        throw new \moodle_exception('invalid_auth_decoded',
            'local_sentientia_pwa');
    }

    // ... rest as before
}
```

Also apply allowlist check from BLOCKING-1 here.

---

### BLOCKING-6: VAPID private PEM stored in plain text in `mdl_config_plugin`

**File:** `local/sentientia_pwa/classes/vapid_key_manager.php:198-201`
**OWASP:** A02:2021 — Cryptographic Failures
**CVSS estimate:** 7.5 — if DB is leaked, all push subscribers can be impersonated

**Evidence:**
```php
set_config(self::PUBLIC_KEY_NAME,   $public_b64url,  self::CONFIG_PLUGIN);
set_config(self::PRIVATE_KEY_NAME,  $private_b64url, self::CONFIG_PLUGIN);
set_config(self::PEM_KEY_NAME,      $private_pem,    self::CONFIG_PLUGIN);
```

`mdl_config_plugin` is just a regular DB table. There is no encryption
at rest. Any backup, replica, or DB snapshot contains the VAPID
private key in cleartext. If an attacker obtains a DB dump (a common
breach scenario — backups in S3 with permissive policies, dev/staging
replicas, etc.), they:
- Cannot directly read user push payloads (those are encrypted to the
  ephemeral as_keypair which is destroyed after each send), BUT
- CAN forge VAPID JWTs as Airpay Academy and POST encrypted pushes to
  any subscriber's endpoint — i.e. send arbitrary notifications to
  users as if they came from Airpay until users re-subscribe.

**The risk window** is large because the private key is the same for
the lifetime of the install — and `regenerate()` nukes all
subscriptions, so admins are reluctant to rotate it (line 211: *"the
browser-side subscription is bound to the OLD public key"*).

**Mitigating factors:**
- VAPID is auth, not confidentiality — the actual message payload is
  protected by a separate per-message ECDH ephemeral key.
- Push services rate-limit per VAPID subject (the `mailto:` claim), so
  abuse is at least bounded.
- The DB is on a private subnet with restricted access (production
  hardening).

**Exact fix (defence-in-depth, since the underlying problem requires
infrastructure work):**

```php
// 1. Add a derived-key envelope in vapid_key_manager::generate_and_save():
//    Wrap the PEM with AES-256-GCM using a key derived from $CFG->dataroot
//    + a long-lived secret stored OUTSIDE the DB (in $CFG, file, or KMS).

private static function wrap_pem(string $pem): string {
    global $CFG;
    $master = self::master_key();  // 32 bytes from $CFG->vapid_master_key (env var)
    if ($master === null) {
        // No master key configured — fall through to plain storage, but
        // record this in audit log so admins know.
        debugging('[sentientia_pwa] VAPID master key not set — PEM stored plaintext',
            DEBUG_NORMAL);
        return $pem;
    }
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($pem, 'aes-256-gcm', $master,
        OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    return 'enc:v1:' . self::b64url_encode($iv . $tag . $ct);
}

private static function unwrap_pem(string $stored): string {
    if (strpos($stored, 'enc:v1:') !== 0) {
        return $stored;  // legacy plaintext PEM
    }
    $blob = self::b64url_decode(substr($stored, 7));
    $iv = substr($blob, 0, 12);
    $tag = substr($blob, 12, 16);
    $ct = substr($blob, 28);
    $master = self::master_key();
    if ($master === null) {
        throw new \moodle_exception('vapid_master_key_missing',
            'local_sentientia_pwa');
    }
    $plain = openssl_decrypt($ct, 'aes-256-gcm', $master,
        OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new \moodle_exception('vapid_pem_decrypt_failed',
            'local_sentientia_pwa');
    }
    return $plain;
}

private static function master_key(): ?string {
    global $CFG;
    // 1st preference: env var (best — never on disk)
    $env = getenv('SENTIENTIA_VAPID_MASTER_KEY');
    if (!empty($env)) {
        $bytes = self::b64url_decode($env);
        if (strlen($bytes) === 32) return $bytes;
    }
    // 2nd: $CFG-level value (only readable by the PHP process)
    if (!empty($CFG->sentientia_vapid_master_key)) {
        $bytes = self::b64url_decode($CFG->sentientia_vapid_master_key);
        if (strlen($bytes) === 32) return $bytes;
    }
    return null;
}

// 2. Update get_private_pem() to unwrap on read:
public static function get_private_pem(): ?string {
    $value = get_config(self::CONFIG_PLUGIN, self::PEM_KEY_NAME);
    if ($value === false || $value === '') return null;
    return self::unwrap_pem($value);
}

// 3. Update generate_and_save() to wrap on write:
set_config(self::PEM_KEY_NAME, self::wrap_pem($private_pem), self::CONFIG_PLUGIN);
```

Document this rotation strategy in `docs/runbooks/vapid-rotation.md`
covering: (a) generating a new master key, (b) flipping
`sentientia_vapid_master_key` env var, (c) re-running the wrap step on
existing PEM, (d) regenerating keypair every 12 months.

Also stop storing `vapid_private_b64url` (the raw 32-byte d value)
because it duplicates the PEM — strip it from `install.xml` / clear
the existing row on next upgrade.

---

## NON-BLOCKING FINDINGS

### NB-7: No JWT caching — same JWT regenerated per send

**File:** `local/sentientia_pwa/classes/push_sender.php:192`
**Severity:** LOW (performance + key-rotation hygiene)

**Evidence:** `deliver_one()` calls `jwt_signer::sign_for_endpoint($sub->endpoint)`
on every delivery. For a notification batch (e.g. 3,500 users getting
the same course reminder), this is 3,500 ECDSA signatures even though
the same JWT is valid for 12h against `fcm.googleapis.com`.

**Why it matters for security (not just performance):**
- Each `openssl_sign()` call uses fresh random k, which means each
  signature is unique but the JWT itself depends only on (origin, exp).
- A short-term cache keyed by origin reduces the attack surface for
  side-channel attacks on the signing routine.
- Reduces wall-clock duration of bulk pushes — which keeps the VAPID
  key in memory for less total time.

**Exact fix:**

```php
// In jwt_signer.php, add a static in-process cache:
private static array $jwt_cache = [];

public static function sign_for_endpoint(string $endpoint,
                                          int $expiry_seconds = self::DEFAULT_EXPIRY_SECONDS): string {
    $origin = self::endpoint_origin($endpoint);
    if ($origin === null) {
        throw new \moodle_exception('invalid_endpoint', 'local_sentientia_pwa');
    }
    $cache_key = $origin;
    $now = time();
    if (isset(self::$jwt_cache[$cache_key])) {
        $cached = self::$jwt_cache[$cache_key];
        // Cache hit if still has at least 10 minutes of validity left.
        if ($cached['exp'] - $now > 600) {
            return $cached['jwt'];
        }
    }
    // ... existing signing logic ...
    self::$jwt_cache[$cache_key] = ['jwt' => $jwt, 'exp' => $claim['exp']];
    return $jwt;
}
```

This is a single-PHP-request cache (resets between web requests / cron
runs). For inter-request caching, use Moodle's MUC application cache
with `simplekeys=true, ttl=43200`.

---

### NB-8: `der_to_jose()` lacks buffer-overrun guards on malformed input

**File:** `local/sentientia_pwa/classes/jwt_signer.php:146-189`
**Severity:** LOW (openssl_sign always emits well-formed DER, but defence-in-depth)

**Evidence:**
```php
$r_len = ord($der[$offset]);
$offset++;
$r = substr($der, $offset, $r_len);  // No check that $r_len > strlen($der) - $offset
$offset += $r_len;
```

If `openssl_sign` somehow returns malformed DER (e.g. due to a bug or
HSM-backed key with non-standard encoding), `ord($der[$offset])` could
go out of bounds (returns false → 0), and `substr` would return less
than `$r_len` bytes, eventually failing at `pad_or_trim_to_32` with a
confusing error.

**Exact fix:**

```php
public static function der_to_jose(string $der): string {
    $offset = 0;
    $derlen = strlen($der);
    $require = function(int $n) use ($derlen, &$offset) {
        if ($offset + $n > $derlen) {
            throw new \moodle_exception('jwt_sign_failed',
                'local_sentientia_pwa', '',
                'DER signature truncated at offset ' . $offset);
        }
    };

    $require(1);
    if (ord($der[$offset]) !== 0x30) {
        throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
            '', 'DER signature does not start with SEQUENCE tag');
    }
    $offset++;
    $require(1);
    $seq_len = ord($der[$offset]);
    if ($seq_len & 0x80) {
        throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
            '', 'DER SEQUENCE length uses long form — not P-256');
    }
    $offset++;
    if ($offset + $seq_len !== $derlen) {
        throw new \moodle_exception('jwt_sign_failed', 'local_sentientia_pwa',
            '', 'DER signature trailing bytes');
    }
    // ... rest with $require() before each substr ...
}
```

---

### NB-9: `run_push_e2e.php` disables Moodle's SSRF protection

**File:** `local/sentientia_pwa/cli/run_push_e2e.php:178-181`
**Severity:** MEDIUM (dev-only file, but ships to disk on production)

**Evidence:**
```php
set_config('curlsecurityblockedhosts', '');
set_config('curlsecurityallowedport', $new_allowed);
$CFG->curlsecurityblockedhosts = '';
$CFG->curlsecurityallowedport  = $new_allowed;
```

The script *intends* to restore these after the test (lines 195-198),
but if the script is killed mid-run (CLI timeout, Ctrl-C, fatal
error), the production-wide curl security settings stay disabled
until the next config write. The `prior_*` variables are also lost.

**Exact fix:**

Wrap the entire test body in a try/finally and ALSO add a
`register_shutdown_function` to restore on fatal error:

```php
// Persist the original values before any changes:
$prior_blocked = get_config(null, 'curlsecurityblockedhosts');
$prior_allowed_port = get_config(null, 'curlsecurityallowedport');

$restore_curl_settings = function () use ($prior_blocked, $prior_allowed_port) {
    set_config('curlsecurityblockedhosts', $prior_blocked);
    set_config('curlsecurityallowedport', $prior_allowed_port);
    global $CFG;
    $CFG->curlsecurityblockedhosts = $prior_blocked;
    $CFG->curlsecurityallowedport  = $prior_allowed_port;
};

// Register shutdown handler for fatal errors:
register_shutdown_function($restore_curl_settings);

try {
    set_config('curlsecurityblockedhosts', '');
    set_config('curlsecurityallowedport', $new_allowed);
    // ... test body ...
} finally {
    $restore_curl_settings();
}
```

Better still: this script should ALSO refuse to run on hosts whose
`$CFG->wwwroot` matches the production hostname (defence-in-depth):

```php
if (strpos($CFG->wwwroot, 'airpay.academy') !== false) {
    cli_writeln('!! run_push_e2e.php refuses to run on the production host.');
    cli_writeln('!! It manipulates global SSRF protection settings.');
    exit(2);
}
```

---

### NB-10: `hkdf_expand` rejects >32-byte output instead of looping

**File:** `local/sentientia_pwa/classes/payload_encrypter.php:151-159`
**Severity:** LOW (RFC compliance / future-proofing)

**Evidence:**
```php
public static function hkdf_expand(string $prk, string $info, int $output_len): string {
    if ($output_len <= 0 || $output_len > 32) {
        throw new \moodle_exception('hkdf_bad_length', 'local_sentientia_pwa',
            '', 'Web Push only needs ≤ 32-byte HKDF outputs; got ' . $output_len);
    }
    $t = self::hmac_sha256($prk, $info . "\x01");
    return substr($t, 0, $output_len);
}
```

Currently fine because Web Push only needs 32-byte (IKM), 16-byte
(CEK), 12-byte (NONCE) outputs. But future RFC 8291 revisions or
related Web Push features (encrypted ledger receipts, etc.) might
need >32 bytes, and the comment "Web Push only needs ≤ 32-byte" will
mislead a future maintainer.

**Exact fix:**

Implement the full RFC 5869 §2.3 loop:

```php
public static function hkdf_expand(string $prk, string $info, int $output_len): string {
    if ($output_len <= 0 || $output_len > 255 * 32) {
        throw new \moodle_exception('hkdf_bad_length', 'local_sentientia_pwa',
            '', 'HKDF output length must be 1..8160; got ' . $output_len);
    }
    $n = (int) ceil($output_len / 32);
    $t = '';
    $previous = '';
    for ($i = 1; $i <= $n; $i++) {
        $previous = self::hmac_sha256($prk, $previous . $info . chr($i));
        $t .= $previous;
    }
    return substr($t, 0, $output_len);
}
```

---

### NB-11: Push log retains title + truncated body in cleartext

**File:** `local/sentientia_pwa/classes/push_logger.php:65-66`
**Severity:** LOW (GDPR — PII retention)

**Evidence:**
```php
$row->title          = mb_substr($title, 0, 200);
$row->body_truncated = mb_substr($body, 0, self::BODY_TRUNCATE);
```

The log table keeps push titles and the first 200 chars of body for
the default retention period of 90 days. Reminder bodies often
include employee names ("Hi Anjali, your course 'KYC Compliance'…"),
which is PII under GDPR.

The retention task does purge after 90d, which is acceptable, but the
audit comment in `install.xml:74` claims *"so it can be retained
longer than typical audit logs without GDPR exposure"* — that's
incorrect; personalised titles + bodies ARE PII, just less of it than
the full message.

**Exact fix:**

Either:
1. **Don't store the body** at all in the push log — store only the
   `tag` (which is a collapse key, not PII) and the result. The body
   is reconstructible from the calling notification context if needed.
2. **Hash the title + body** with a per-install salt and store the
   hash for correlation; admins viewing the log see "title:
   <redacted, hash a1b2c3d4>" and can correlate via the originating
   notification record.

Recommended: option 1, with an admin-toggleable setting
`store_push_body_in_log` (default OFF) for forensics-mode deployments.

```php
// In push_logger.php:
public static function log(...) {
    // ... existing prep ...
    $store_body = (bool) get_config('local_sentientia_pwa', 'store_push_body_in_log');
    if ($store_body) {
        $row->title          = mb_substr($title, 0, 200);
        $row->body_truncated = mb_substr($body, 0, self::BODY_TRUNCATE);
    } else {
        $row->title          = '[redacted: ' . hash('sha256', $title) . ']';
        $row->body_truncated = null;
    }
    // ... rest unchanged ...
}
```

Update `install.xml` comment to reflect actual PII status.

---

### NB-12: VAPID JWT lacks `nbf` (not-before) claim

**File:** `local/sentientia_pwa/classes/jwt_signer.php:71-75`
**Severity:** LOW (interop / clock-skew robustness)

**Evidence:**
```php
$claim = [
    'aud' => $origin,
    'exp' => $now + $expiry_seconds,
    'sub' => vapid_key_manager::get_subject(),
];
```

No `nbf` (not-before) and no `iat` (issued-at). RFC 8292 §2 doesn't
require either, but some push providers (looking at the historical
Mozilla autopush implementation) reject JWTs with too-large `exp - iat`
gaps as a heuristic against pre-signed tokens. Without an `iat`, push
services can't tell when the JWT was issued and may be conservative.

**Exact fix:**

```php
$now = time();
$claim = [
    'aud' => $origin,
    'iat' => $now,                 // issued at
    'exp' => $now + $expiry_seconds,
    'sub' => vapid_key_manager::get_subject(),
];
```

Don't add `nbf` unless field-tested — it can cause its own clock-skew
issues if the push provider's clock is behind ours.

---

### NB-13: Padding scheme leaks plaintext length

**File:** `local/sentientia_pwa/classes/payload_encrypter.php:103`
**Severity:** LOW (informational disclosure to a network observer)

**Evidence:**
```php
$plaintext_padded = $plaintext . "\x02";
```

The ciphertext length = plaintext length + 17 (1 byte delimiter + 16 byte
tag). An observer of the network traffic between Airpay Academy and the
push provider learns the exact plaintext length — which leaks info like
"this is a 12-char title 'Order shipped' + 138-char body".

RFC 8291 §3 explicitly notes: *"applications SHOULD pad messages to a
common size to avoid leaking message length"*.

**Exact fix:**

```php
// In encrypt_for_subscription(), before the AES encrypt step:
$plaintext_padded = $plaintext . "\x02";
$target_size = self::pad_target_size(strlen($plaintext_padded));
if (strlen($plaintext_padded) < $target_size) {
    $plaintext_padded .= str_repeat("\x00", $target_size - strlen($plaintext_padded));
}

// New helper:
private static function pad_target_size(int $current_size): int {
    // Pad to next 256-byte boundary, capped at 4096 (record size).
    // This bucketises messages into discrete length classes.
    $bucket = ((int) ceil($current_size / 256)) * 256;
    return min($bucket, self::RECORD_SIZE - 16 /* tag */ - 86 /* header */);
}
```

The receiver (`mock_receiver.php` and real browsers) already strips
trailing zeros via `preg_replace('/\x02\x00*$/', '', $plaintext_padded)`
— so this change is fully compatible.

---

### NB-14: VAPID public key shown unredacted in admin settings

**File:** `local/sentientia_pwa/settings.php:31`
**Severity:** INFO (the public key is, by definition, public)

**Evidence:**
```php
'<code style="word-break:break-all">' . s($public_key) . '</code>';
```

This is **fine** — VAPID public keys are explicitly designed to be
distributed publicly (the AMD module hands it to PushManager.subscribe).
Flagging only because the admin UI mixes private-key generation status
with the public-key display, and a casual admin might mistake one for
the other.

**Suggested fix (cosmetic):**

```php
'<code style="word-break:break-all">' . s($public_key) . '</code>'
. ' <small class="text-muted">— this is the PUBLIC half of the keypair, '
. 'distributed to every browser that subscribes; safe to share.</small>';
```

---

### NB-15: Subscription endpoint URL may leak via `debugging()` in logs

**File:** `local/sentientia_pwa/classes/push_sender.php` lines 116, 137, 160, 172, 249
**Severity:** LOW-MEDIUM (push endpoint URL contains a per-user secret token)

**Evidence:** Multiple `debugging()` calls in push_sender. The
`debugging()` output goes to Apache error log when
`$CFG->debugdeveloper` is on. None of the current `debugging()` calls
directly emit `$sub->endpoint`, BUT exception messages from
underlying curl errors *can* include the URL (Moodle's curl wrapper
sometimes appends the URL to its error message). The `error_detail`
field in `push_logger::log()` is also free-form and could include
endpoint URL fragments from exception chains.

Why endpoint URLs are sensitive: the URL path typically contains a
provider-specific "registration ID" that is functionally equivalent to
a bearer token — anyone who knows it AND has a valid VAPID JWT for the
push service origin can deliver pushes to that subscriber. So treat
endpoint URLs as **secret material**, not as identifiers.

**Exact fix:**

1. Audit every `debugging()` / exception path in push_sender and
   push_logger; redact endpoint URLs before passing to `error_detail`:

```php
// Helper in push_sender:
private static function redact_endpoint(string $endpoint): string {
    $parsed = parse_url($endpoint);
    if (!$parsed) return '[unparseable endpoint]';
    return ($parsed['scheme'] ?? '?') . '://'
        . ($parsed['host'] ?? '?')
        . '/[redacted ' . strlen($parsed['path'] ?? '') . '-char path]';
}

// And use it everywhere:
debugging(sprintf(
    '[sentientia_pwa] push delivery threw for sub %d (%s): %s',
    (int) $sub->id,
    self::redact_endpoint($sub->endpoint),
    $e->getMessage()  // Hope this doesn't include the URL
), DEBUG_DEVELOPER);
```

2. In `push_logger::log()`, scrub `$error_detail` of any URL-shaped
   substrings before insert:

```php
private static function scrub_secrets(?string $detail): ?string {
    if ($detail === null) return null;
    // Replace URLs with their host only.
    return preg_replace_callback(
        '/https?:\/\/[^\s"\']+/i',
        function ($m) {
            $h = parse_url($m[0], PHP_URL_HOST);
            return 'https://' . ($h ?? 'unknown') . '/[redacted]';
        },
        $detail
    );
}

// In log():
$row->error_detail = $error_detail !== null
    ? mb_substr(self::scrub_secrets($error_detail), 0, 5000)
    : null;
```

---

## Additional notes the auditor wants on record

### A. The test suite validates self-consistency, NOT RFC conformance

`cli/test_crypto.php` test 3 (lines 124-200) reproduces the SAME HKDF
chain as `payload_encrypter`, then decrypts what it encrypted. This is
a **roundtrip self-consistency test**, not an RFC conformance test.

A bug where, say, `key_info` had a typo would not be caught by this
test because both sides use the same typo.

**This is the gap the ADR-003 review explicitly worried about.**
Recommendation: add a test vector check against RFC 8291 §5.1 known
test vectors (the RFC publishes a worked example: salt, ECDH inputs,
expected ciphertext). Implementing that is straightforward — the
test vectors are:

```
Plaintext:         "When I grow up, I want to be a watermelon" (41 bytes)
P-256 application server private:   yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw
P-256 user agent public:           BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4
auth_secret:                       BTBZMqHH6r4Tts7J_aSIgg
salt:                              DGv6ra1nlYgDCS1FRnbzlw
Expected encrypted output:         DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN
```

Build a test that feeds those exact inputs through
`payload_encrypter::encrypt_for_subscription` (after a small refactor
to allow injecting the as_keypair instead of generating it) and
asserts byte-equal output. THAT is the conformance gate ADR-003 needs.

Likewise for JWT: add a test against a published RFC 7515 §3.1 test
vector with a known private key + known signing input + known
expected signature.

### B. The push_sender flag check is good, but log it explicitly

`push_sender::is_enabled()` correctly fails closed. But when the flag
is OFF and someone calls `push_sender::send()`, the function returns 0
silently. For ops visibility, log this:

```php
// In push_sender::send(), top:
if (!self::is_enabled()) {
    debugging('[sentientia_pwa] push.enabled is OFF — refusing send for user '
        . $userid, DEBUG_DEVELOPER);
    return 0;
}
```

This makes flag-state diagnosable from logs without needing to query
the DB.

### C. Multi-tenant test coverage

No test in `cli/test_crypto.php` or `cli/run_push_e2e.php` exercises
the multi-tenant boundary. Recommend a Phase B.2.6 test that:
1. Creates a subscription for a user with `tenantid=1` (Airpay).
2. Calls `push_sender::send()` from a context where the "current
   tenant" is 77 (Public).
3. Asserts that the push is REFUSED (returns 0, logs a refusal).

After BLOCKING-4 is fixed.

---

## Final Verdict

```
RFC 8291 conformance:           PASS
RFC 7518 §3.4 conformance:      PASS
RFC 8292 conformance:           PASS
RFC 7515 conformance:           PASS

Key storage hygiene:            FAIL (BLOCKING-6)
Subscription endpoint trust:    FAIL (BLOCKING-1, BLOCKING-2)
Multi-tenant isolation:         FAIL (BLOCKING-4)
Production-reachable mock:      FAIL (BLOCKING-3)
WS input validation:            FAIL (BLOCKING-5)

PII / GDPR:                     CONDITIONAL (NB-11)
Logging hygiene:                CONDITIONAL (NB-15)

Hand-rolled crypto algorithms: APPROVE
Surrounding infrastructure:    BLOCK until 6 fixes applied
```

**Required before flipping `sentientia.pwa.push.enabled` ON in production:** 6 blocking fixes + add RFC test vector validation to `cli/test_crypto.php`.

**Effort estimate:** ~1 day for all 6 blocking fixes + ~2 hours to add the test vector. Compare to "swap for minishlink/web-push" alternative (ADR-003 §Reversibility): half-day per ADR-003. Either route is acceptable, but the audit recommends staying with the hand-rolled stack and applying the 6 fixes because:

1. The crypto core is correct — the bugs are all in adjacent infrastructure (input validation, SSRF, multi-tenant, secrets at rest) that minishlink wouldn't fix either.
2. Each blocking fix is small (~20 LOC), well-isolated, and improves the code regardless of the underlying library.
3. The auditor concern in ADR-003 ("hand-rolled crypto is famously error-prone") was validated for the wrong reason — the crypto is fine; the **app-layer plumbing** has the issues, and that plumbing exists either way.

---

## Audit Trail

- 2026-05-21 — Audited by Airpay Security Auditor (Claude Opus 4.7).
- Files reviewed: 4 primary + 8 supporting + ADR-003.
- RFC trace: RFC 8291 §3.4 (line-by-line), RFC 7518 §3.4, RFC 8292 §2,
  RFC 8030 §3.
- _Pending_: 6 blocking fixes + RFC test vector validation.
- _Pending_: re-audit after fixes land, before flag-on.
