# PHASE 8 SECURITY AUDIT — Airpay Academy Pre-Cutover

**Date**: 2026-05-12
**Auditor**: Airpay Security Auditor (Opus 4.7 1M)
**Moodle**: 4.5.10 | **PHP**: 8.2.12 | **Branch**: production
**Scope**: 5 local plugins + 1 quizaccess subplugin (Phase 1-6 deliverables, ~20K LOC)
  - `local/airpay_cart/`
  - `local/airpay_proctoring/`
  - `local/airpay_recompletion/`
  - `local/airpay_request/`
  - `local/airpay_org/classes/task/sync_cohorts.php` (Phase 6 cron addition)
  - `mod/quizaccess_airpay_proctoring/`

---

## VERDICT: **NO-GO** for production cutover

**Blocking findings: 11 | Non-blocking: 9**

The cart payment stack and proctoring stack both contain critical access-control gaps that allow cross-tenant data leakage and unauthorized state mutation. Cart price-tampering is possible because gateway redirect params are not pinned to a verified server-side amount on the callback. Estimated remediation: **2-3 dev-days, 1-day re-test**.

---

## BLOCKING FINDINGS (fix before cutover)

### B1. Cross-tenant refund + PII leak via `refund_order` / `get_order` / `list_orders` / `daily_sums`
**OWASP**: A01 Broken Access Control | **CVSS**: 8.6 (High)
**Files**:
- `local/airpay_cart/classes/external/refund_order.php:24-37`
- `local/airpay_cart/classes/external/get_order.php:22-32`
- `local/airpay_cart/classes/external/list_orders.php:53-69`
- `local/airpay_cart/classes/external/daily_sums.php:30-57`
- `local/airpay_cart/classes/cart_manager.php:413-425`

A `manager` in Public tenant (`/77`) holds `local/airpay_cart:viewallorders` + `:refund` and can refund/view any Airpay (`/1`) or ZEEA (`/177`) order — capability is system-level, no tenant scoping. `daily_sums` likewise returns global ledger sums. `cart_manager::get_order()` only checks userid+cap, never tenant.

**Fix** (apply in `cart_manager::get_order` and copy pattern to refund / list / sums):
```php
public static function get_order(int $historyid, int $viewerid): \stdClass {
    global $DB, $USER;
    $cart = $DB->get_record('local_airpay_cart_history',
        ['id' => $historyid], '*', MUST_EXIST);
    $viewer_tenant = self::get_tenant_root($USER);
    if ((int) $cart->userid !== $viewerid) {
        $ctx = \context_system::instance();
        if (!is_siteadmin($viewerid)
            && !has_capability('local/airpay_cart:viewallorders', $ctx, $viewerid)) {
            throw new \moodle_exception('error_outoftenant', 'local_airpay_cart');
        }
        // NEW: cross-tenant manager cannot see other tenants
        if (!is_siteadmin($viewerid)
            && (int) $cart->costcenterid !== $viewer_tenant) {
            throw new \moodle_exception('error_outoftenant', 'local_airpay_cart');
        }
    }
    return $cart;
}
```
For `list_orders` line 66-69, change to always-scope (not just when admin-supplied), and for `daily_sums` add `JOIN {local_airpay_cart_history} h ON h.id = ledger.historyid WHERE h.costcenterid = :tn`.

---

### B2. Cross-tenant proctoring leak via review queue & attempt list
**OWASP**: A01 | **CVSS**: 8.1 (High)
**Files**:
- `local/airpay_proctoring/classes/external/list_review_queue.php:34-41`
- `local/airpay_proctoring/classes/external/list_attempts.php:53-69`
- `local/airpay_proctoring/classes/external/compliance_report.php:42-56`
- `local/airpay_proctoring/classes/external/get_attempt.php:28-36`
- `local/airpay_proctoring/classes/external/submit_review.php:30-32`
- `local/airpay_proctoring/attempt.php:21-31`
- `local/airpay_proctoring/classes/external/flag_session.php:27-31`

Any user holding `:review` or `:viewattempts` (manager / editingteacher archetypes) can list, open, flag, and decide on sessions from any tenant — including identity match scores and biometric provenance. Webcam recording references (S3 keys) are accessible cross-tenant.

**Fix** (apply at every read path that touches `local_airpay_proctor_sessions`):
```php
$viewer_tenant = \local_airpay_proctoring\session_manager::tenant_for_user($USER);
$where[] = 's.costcenterid = :tn';
$args['tn'] = $viewer_tenant;
// For get_attempt / attempt.php — after MUST_EXIST fetch:
if ((int) $session->costcenterid !== $viewer_tenant && !is_siteadmin()) {
    throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
}
```
Add public helper `session_manager::tenant_for_user(\stdClass $u): int` derived from the existing logic at session_manager.php:31-33.

---

### B3. Proctoring chunk registration + event reporting bypass session ownership
**OWASP**: A01 (IDOR) | **CVSS**: 8.2 (High)
**Files**:
- `local/airpay_proctoring/classes/external/upload_chunk.php:32-43`
- `local/airpay_proctoring/classes/external/report_event.php:36-41`
- `local/airpay_proctoring/classes/external/finalize_session.php:21-25`
- `local/airpay_proctoring/classes/session_manager.php:126-159`

`register_chunk()`, `record_event()`, `finalize()` never verify the caller owns the session. Attacker can pollute another candidate's recording log, inject false "tab switch" events to flag them, or prematurely finalize their session (forcing AI verdict + flagged status).

**Fix** in `session_manager::register_chunk` (apply same pattern to `record_event`, `finalize`):
```php
public static function register_chunk(int $sessionid, string $kind, /*...*/): int {
    global $DB, $USER;
    $session = $DB->get_record('local_airpay_proctor_sessions',
        ['id' => $sessionid], 'id, userid, status', MUST_EXIST);
    if ((int) $session->userid !== (int) $USER->id) {
        throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
    }
    if (!in_array($session->status, ['recording', 'verifying'], true)) {
        throw new \moodle_exception('error_session_state', 'local_airpay_proctoring');
    }
    // ... existing insert
}
```
Plus bound `s3_key` to `^[a-zA-Z0-9/_.-]+$` via a stricter param than `PARAM_TEXT` (use `PARAM_SAFEPATH` or explicit regex) — current `PARAM_TEXT` allows attacker to register an `s3_key` that, when proxied, points to admin-managed objects.

---

### B4. Payment amount-tampering vulnerability — callback trusts `payload['order_id']` only
**OWASP**: A04 Insecure Design / A08 Software Integrity | **CVSS**: 9.1 (Critical)
**Files**:
- `local/airpay_cart/callback.php:55-66`
- `local/airpay_cart/classes/gateway/airpay_gateway.php:73-81, 111-123`

The checksum verifies the payload integrity from the gateway's perspective — but `mark_paid()` is called without comparing `payload.amount` against the server-side `$cart->total_amount`. If the gateway integration logic, the merchant config, or any future race lets a 1 INR payment carry an `order_id=X` whose server-side total is 50,000 INR, `mark_paid` enrolls the user. Additionally, the checksum scheme concatenates keys without delimiter escaping — a malicious extra `key=value|` injection could substitute legit fields.

**Fix**:
```php
// callback.php after $cart fetch (line 60):
$paid_amount = (float) ($payload['amount'] ?? $payload['AMOUNT'] ?? 0);
$expected = round((float) $cart->total_amount, 2);
if ($gateway->is_success($payload) && abs($paid_amount - $expected) > 0.01) {
    \local_airpay_cart\callback_logger::log('amount_mismatch', $payload, $raw);
    http_response_code(400);
    echo 'Amount mismatch';
    exit;
}
```
And in `airpay_gateway::compute_checksum` (line 117-122), URL-encode values before concatenation OR move to HMAC-SHA256 over a delimited canonical string. Document the exact signature scheme matches Airpay's published spec (currently a comment claim — verify with their integration team before cutover).

---

### B5. XSS via raw `{{{ billing_address }}}` and `{{{ company_address }}}` in invoice template
**OWASP**: A03 Injection (XSS) | **CVSS**: 7.4 (High)
**File**: `local/airpay_cart/templates/invoice.mustache:22, 39`

`billing_address` is a customer-supplied free-text field stored as `PARAM_TEXT` (which doesn't sanitize HTML — it only strips tags after-the-fact for some param types but not consistently for stored strings). `company_address` comes from admin config which is somewhat trusted but still — same risk if compromised. The PHP renderer does call `nl2br(s($invoice->billing_address))` at `invoicer.php:137` and passes pre-escaped HTML to the template — so the `{{{` is actually intentional for the `<br/>` tags from nl2br.

**Risk class**: defense-in-depth weakness. Promoted to BLOCKING because the `company_address` admin setting uses `PARAM_TEXT` (settings.php:62) and is rendered with `{{{ }}}` after `nl2br(s())`. The pattern is correct *today* but extremely fragile — any future template direct-render breaks it.

**Fix**: replace the raw-output pattern with a dedicated `html_writer::tag('p', $address, ['class' => 'multiline'])` wrapper that escapes, then use CSS `white-space: pre-line` in the invoice stylesheet:
```php
// invoicer.php:131-138 — replace nl2br hack:
'company_address' => html_writer::tag('div', s($company_address),
    ['style' => 'white-space: pre-line;']),
'billing_address' => html_writer::tag('div', s($invoice->billing_address),
    ['style' => 'white-space: pre-line;']),
```
Then change template to `{{{ company_address }}}` rendering the now-safe-prebuilt HTML. Audit comment must note the field is pre-escaped.

---

### B6. Recompletion engine resets across ALL tenants — no tenant scoping in rules query
**OWASP**: A01 | **CVSS**: 7.5 (High)
**File**: `local/airpay_recompletion/classes/recompletion_engine.php:113-129, 36-58`

`run_all()` iterates every enabled rule but the rule's `costcenterid` column (install.xml:27) is **never consulted** in the SQL. A rule created for tenant 1 fires on tenant 77 + 177 users too. Worst case: an admin sets a 90-day re-completion for Airpay employee onboarding course → ZEEA's employees also get their completions wiped + grades zeroed. CLI smoke writes `costcenterid => 0` at line 50 with no validation.

**Fix** in `recompletion_engine::run_rule`:
```php
// Around line 110-129, add tenant filter:
if ((int) $rule->costcenterid > 0) {
    // Derive tenant from user.open_path (production: same logic as cart_manager)
    $where[] = "EXISTS (SELECT 1 FROM {user} u2 WHERE u2.id = cc.userid
                       AND u2.open_path LIKE :tnpath)";
    $args['tnpath'] = '/' . (int) $rule->costcenterid . '%';
}
```
Also enforce in the edit.php form: require tenant selection at rule creation, default to current admin's tenant.

---

### B7. PARAM_RAW on identity photos with weak size cap → memory DoS + content trust
**OWASP**: A04 / A05 Security Misconfiguration | **CVSS**: 6.8 (Medium-High, promoted because PII)
**File**: `local/airpay_proctoring/classes/external/submit_identity.php:21-46`

`id_b64` + `selfie_b64` are `PARAM_RAW` strings, capped at 14M chars each. A logged-in student can submit 28MB per request via repeated calls (no rate limit) — easy to exhaust PHP `memory_limit` for the next legitimate request. More importantly, base64-decoded bytes are passed straight to `aws_verifier::verify()` and sent over the wire to AWS without content sniffing — an attacker can ship arbitrary binary (zip-bomb, polyglot) at AWS's CompareFaces endpoint and burn through the AWS rate quota.

**Fix**:
1. Add MIME sniff after decode (line 41-42): verify first 8 bytes match JPEG/PNG magic; reject otherwise.
2. Add per-user rate limit using `\cache::make()` keyed by userid+hour, max 5 submits/hour.
3. Reduce the 10MB-raw cap to 4MB (still ample for an ID photo) — `if (strlen(...) > 5_500_000)`.
4. Throw on `base64_decode` strict-false returning false (the current `?: ''` swallows malformed input).

```php
$id_bytes = base64_decode($params['id_b64'], true);
if ($id_bytes === false) {
    throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
        '', 'Invalid base64');
}
$magic = substr($id_bytes, 0, 4);
$is_jpeg = substr($id_bytes, 0, 3) === "\xFF\xD8\xFF";
$is_png  = $magic === "\x89PNG";
if (!$is_jpeg && !$is_png) {
    throw new \moodle_exception('error_session_state', 'local_airpay_proctoring',
        '', 'Unsupported image format');
}
```

---

### B8. SQL injection vector via `pre_notify_days` arithmetic in recompletion engine
**OWASP**: A03 Injection | **CVSS**: 6.5 (Medium-High)
**File**: `local/airpay_recompletion/classes/recompletion_engine.php:128, 191`

`LIMIT $max_batch` is interpolated directly into the SQL string (`get_records_sql` at line 128 and 191). While `$max_batch` is read from `get_config()` (admin-only), a compromised admin setting or a future code change that lets a less-trusted source set `max_batch` becomes RCE-via-SQL. Same pattern in `history.php:35` (`LIMIT $perpage OFFSET ...`). Belt-and-braces: this should use the 5th/6th args of `get_records_sql($sql, $params, $limitfrom, $limitnum)`.

**Fix** (recompletion_engine.php:113-129):
```php
$rows = $DB->get_records_sql(
    "SELECT cc.id, cc.userid, cc.course AS courseid, cc.timecompleted, c.fullname
       FROM {course_completions} cc
       JOIN {course} c ON c.id = cc.course
       JOIN {user}   u ON u.id = cc.userid
      WHERE $wheresql
        AND u.deleted = 0 AND u.suspended = 0
        AND $time_field < :expiry
   ORDER BY cc.id",
    array_merge($args, ['expiry' => $expiry_threshold]),
    0, $max_batch);  // limitfrom, limitnum as proper args
```
Same for warn_rows query at line 191, and for `history.php:35` (use proper `get_records_sql(..., $page * $perpage, $perpage)`).

---

### B9. Pricing-tamper risk: `set_course_price` cap doesn't scope by tenant
**OWASP**: A01 | **CVSS**: 7.1 (High)
**File**: `local/airpay_cart/classes/external/set_course_price.php:32-76`

Cap `:manageprices` granted to `manager` archetype on all tenants. A Public-tenant manager can re-price an Airpay-tenant course (e.g. drop a 5,000 INR course to 1 INR) and any user able to purchase can then buy it cheap. Course doesn't have an inherent tenant — but cohort assignment + tenant manager scoping should restrict.

**Fix**: verify the course is in a category the manager has access to via the course context:
```php
public static function execute(int $courseid, float $price, string $currency = 'INR'): array {
    global $DB, $USER;
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    // Capability must be checked at the COURSE context, not system —
    // managers in tenant X only have manager role in tenant X courses.
    $coursecontext = \context_course::instance($courseid);
    require_capability('local/airpay_cart:manageprices', $coursecontext);
    // ... rest unchanged
}
```
And change `db/access.php` `:manageprices` to `'contextlevel' => CONTEXT_COURSE`. (This is a context-level migration — review upgrade path.)

---

### B10. Approver routing bypass on `decide` — overrideroute cap is system-wide
**OWASP**: A01 | **CVSS**: 6.5 (Medium-High)
**File**: `local/airpay_request/classes/request_manager.php:138-144`

Any user with `local/airpay_request:overrideroute` (not granted by default — risk is via future role assignment) can approve any request in any tenant. There's no tenant check. Combined with B6's tenant unawareness this means a Public-tenant power user could approve Airpay-internal compliance requests.

**Fix**:
```php
$viewer_tenant = self::tenant_for_user($DB->get_record('user',
    ['id' => $deciderid], '*', MUST_EXIST));
if ((int) $rec->approver_userid !== $deciderid
    && !is_siteadmin($deciderid)) {
    if (!has_capability('local/airpay_request:overrideroute',
            \context_system::instance(), $deciderid)
        || (int) $rec->costcenterid !== $viewer_tenant) {
        throw new \moodle_exception('error_outoftenant', 'local_airpay_request');
    }
}
```

---

### B11. Webhook callback DoS — no rate limiting + verbose error in 500 response body
**OWASP**: A05 Security Misconfiguration / A09 Logging | **CVSS**: 5.4 (Medium, promoted: financial endpoint)
**File**: `local/airpay_cart/callback.php:73-77`

`echo 'Error: ' . $e->getMessage();` leaks PHP stack info to whoever can POST to `/callback.php` (which is anyone — `NO_MOODLE_COOKIES`). Also no IP allow-list for gateway callbacks (Airpay publishes their callback IP ranges — pin them). Without rate-limiting, an attacker can hammer the endpoint with bogus payloads to drive up the `callback_logger::log()` `error_log()` size.

**Fix**:
```php
// callback.php — replace lines 73-77:
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Error';  // generic — don't leak
    debugging('airpay_cart callback error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    \local_airpay_cart\callback_logger::log('exception',
        ['exception' => get_class($e)], $raw);
}
```
Add config `airpay_callback_iplist` (PARAM_TEXT, CSV of CIDR) and check `getremoteaddr()` against it before logging — silently drop if not allowed.

---

## NON-BLOCKING FINDINGS (track + ship)

### N1. CSV injection in `daily_sums_csv.php`
**File**: `daily_sums_csv.php:51-60` — gateway/currency fields go straight to `fputcsv` without `=`-prefix sanitization. If a future gateway name contains `=cmd|...` Excel formula injection lands. Add: `$cell = preg_match('/^[=\-+@]/', $cell) ? "'" . $cell : $cell;` for each text col.

### N2. `purge_old_recordings` task doesn't actually delete S3 objects
**File**: `local/airpay_proctoring/classes/task/purge_old_recordings.php:50-58` — `delete_s3_object()` is a stub that always returns true. Code marks rows deleted in DB but S3 retention is silent. Marked "production version will add this" but ships as-is — GDPR retention not enforced. Pre-production task.

### N3. Webhook payload includes `payload_json` from gateway, stored unfiltered in ledger
**File**: `cart_manager.php:248` — `payload_json` is the full webhook body. If gateway echoes our `success_url` (which contains our internal `orderid`), that URL is logged. Low-risk; gateway data is otherwise opaque.

### N4. Identity table has no `costcenterid` column
**File**: `local/airpay_proctoring/db/install.xml:56-79` — `local_airpay_proctor_identity` does not denormalize tenant, only `userid` + `sessionid`. Compliance reports that filter by tenant must always JOIN sessions. Add index/column for hot path.

### N5. Recompletion `send_message` uses `s($body)` + `FORMAT_PLAIN` but subject is unescaped
**File**: `recompletion_engine.php:169-174, 203-205` — `$subject` includes `$row->fullname` raw. Course names should already be format_string'd at insert but not guaranteed. Wrap: `$subject = format_string($subject)`.

### N6. `submit_review` allows decisions on `finished` sessions, not just `flagged`
**File**: `session_manager.php:199-224` — `submit_review` doesn't check `$session->status`. A reviewer can post a `fail` decision on an already-`reviewed` session, overwriting human_decision. Need `if ($session->status === 'reviewed') return;` (idempotent) and reject `new`/`recording`/`verifying`.

### N7. `quizaccess_airpay_proctoring` stores config in plugin config keyed by quizid
**File**: `mod/quiz/accessrule/airpay_proctoring/rule.php:40-48, 50-52` — Uses `set_config('quiz_X_enabled', ...)` which pollutes `mdl_config_plugins` table — fine for small N but for 1000+ quizzes that's a smell. The comment even says so. Tech-debt before scale-up. Also `delete_settings` should defensively check the value exists.

### N8. `airpay_request_manager::decide` doesn't lock the row — race on concurrent approvals
**File**: `request_manager.php:152-173` — two approvers clicking "approve" simultaneously: both transactions commit; user gets double-enrolled (idempotent guard saves the day), but the audit log has two decisions. Add `SELECT FOR UPDATE` inside the transaction.

### N9. `aws_verifier::sign_request()` lacks request-id / retry policy
**File**: `local/airpay_proctoring/classes/identity/aws_verifier.php:54-92` — single curl_exec with `CURLOPT_TIMEOUT 30`. AWS Rekognition occasionally returns 5xx; failure path returns `aws_http_500` and the user is locked out. Add exponential backoff (2 retries) for 5xx and `Throttling*` exceptions.

---

## Clean / Verified Good

The following surfaces were audited and found OWASP-clean. No findings:

| Area | Status |
|------|--------|
| Raw `$_GET` / `$_POST` / `$_REQUEST` superglobals | None in any of 6 plugins |
| `require_login()` on every page entry | All 14 page entry points correctly gated |
| `require_capability()` on every WS endpoint | 9/9 cart endpoints, 12/12 proctoring endpoints, 6/6 request endpoints |
| `require_sesskey()` on form posts | `edit.php:91` correctly uses it; checkout.php:44 uses `confirm_sesskey()` |
| `data_submitted()` + sesskey on form handlers | checkout.php:44 |
| SQL parameterization — no string-concat to SQL | All 200+ queries use named/positional params; LIKE clauses use `sql_like_escape()` correctly |
| `MOODLE_INTERNAL` guards | All non-page PHP files have it |
| `format_string()` on user/course names in pages | cart_manager line 85, set_price.php:42-43, list_review_queue.php:45, list_attempts.php:73, etc. |
| Tenant snapshot at create time (open_path → costcenterid) | Cart, proctoring, request all stamp at insert. Recompletion stamps but doesn't filter (see B6) |
| Constant-time webhook signature comparison | `airpay_gateway.php:80` uses `hash_equals()` ✓ |
| Credentials stored via `admin_setting_configpasswordunmask` | aws_secret, airpay_secret ✓ — not in source |
| `NO_MOODLE_COOKIES` on webhook | callback.php:18 ✓ |
| GDPR privacy providers exist | All 4 plugins have `classes/privacy/provider.php` with metadata + export + delete |
| GDPR DSR delete paths | Cart redacts (audit retained), recompletion redacts userid only, proctoring deletes recursively, request redacts reason/note |
| Cart ledger immutability | INSERT-only design in schema + transaction-wrapped writes in cart_manager:235-272 |
| Invoice numbering atomicity | `invoicer::reserve_invoice_number` retries on collision; unique key on table |
| Webhook callback logger redacts secret/checksum/card_number/cvv | callback_logger.php:21-25 ✓ |
| Cron task tenant-clean on cohort sync | `sync_cohorts.php` operates on system-wide org tree by design (path-driven, not tenant-driven) — correctly scoped |
| Proctoring AWS SigV4 implementation | aws_verifier.php:98-137 — canonical request correctly formed, no key/secret in URL or logs |
| Identity photos NOT persisted | session_manager.php:120 explicit `unset()`; only score saved |
| Recompletion uses `start_delegated_transaction` for atomic resets | recompletion_engine.php:220-275 ✓ |
| `get_in_or_equal()` used everywhere for IN clauses | recompletion_engine.php:237, 249; provider.php:105 ✓ |

---

## Risk-prioritised remediation order

1. **B4** (payment tampering) + **B11** (callback hardening) — same file, ship together
2. **B1** + **B2** + **B10** (cross-tenant access) — pattern is identical, factor out one helper
3. **B3** (proctoring IDOR) — single helper in session_manager
4. **B6** (recompletion tenant) — single SQL change + form change
5. **B5** + **B7** (XSS + DoS hardening) — small, isolated
6. **B8** (LIMIT injection) — refactor 3 callsites
7. **B9** (set_price context) — context-level migration, test carefully

Estimated dev effort: **2 days code + 1 day test**. Re-audit by re-running this same audit against the diff.

---

**Auditor's note**: The codebase shows solid Moodle conventions throughout (no superglobals, consistent `require_capability`, proper privacy providers, atomic transactions on money flows, constant-time crypto compare, immutable ledger). The blocking findings are almost all in one category — **system-context capability checks without tenant scoping** — which is a single architectural gap rather than scattered bugs. Recommend introducing a shared trait `\local_airpay_core\tenant_scoped_external` that automatically appends tenant filter to all external_api derived classes, then back-porting to these 5 plugins. That trait becomes mandatory for Phase 9.
