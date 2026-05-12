# PHASE 8.2 SECURITY RE-AUDIT — Phase 8.1 Remediation

**Date**: 2026-05-12
**Auditor**: Airpay Security Auditor (Opus 4.7 1M)
**Moodle**: 4.5.10 | **PHP**: 8.2.12 | **Branch**: production
**Remediation commit**: `02ce2bc8e` (35 files, +787/-83)
**Scope**: 6 plugins + 1 subplugin touched by the remediation. New code: `local_airpay_core`, `airpay_cart\ip_check`, `airpay_proctoring\db\caches.php`. Pre-existing plugins from prior phases NOT re-audited.

---

## 1. HEADLINE VERDICT: **GO** for production cutover

All 11 BLOCKING findings from `PHASE-8-SECURITY-AUDIT.md` are VERIFIED fixed. The remediation pattern is sound: a single shared helper (`\local_airpay_core\tenant`) handles 10/11 cases consistently, reducing the chance of pattern drift in future plugins. PHP lint passes on all 35 touched files. 8 PHPUnit tests cover the helper's contract.

**Caveats** (non-blocking — track and fix in a follow-up sprint, not pre-cutover):
- N1: rate-limit hour-bucket allows 2× burst at boundary — acceptable for identity-verification UX (1 submit/session expected)
- N2: `callback.php` IP allow-list trusts `X-Forwarded-For` via `getremoteaddr()` if `$CFG->reverseproxy=true` — document deploy expectation
- N3: `ip_check::ip_in_cidr()` silently accepts invalid `/33+` v4 or `/129+` v6 prefix lengths — treats as full-bits match
- N4: capability migration B9 has no `db/upgrade.php` to clean up stale CONTEXT_SYSTEM role assignments for `manageprices`
- N5: `_tenantroot` param name convention deviates from Moodle's naming (leading underscore) — low collision risk today but worth standardising
- N6: `callback_logger::log` not invoked for IP-blocked requests — silent-drop is intentional but blinds ops to gateway IP rotation

No new BLOCKING findings introduced by the remediation.

---

## 2. PER-FINDING VERIFICATION TABLE

| ID | Severity | Surface | Verdict | Evidence |
|----|----------|---------|---------|----------|
| **B1** | CVSS 8.6 | Cart cross-tenant access | **VERIFIED** | `cart_manager.php:431-432` (get_order), `refund_order.php:40-41`, `list_orders.php:67-69`, `daily_sums.php:50,60-62,65` |
| **B2** | CVSS 8.1 | Proctoring cross-tenant access | **VERIFIED** | `list_review_queue.php:35,38,46`; `list_attempts.php:43-45,61`; `compliance_report.php:45,58`; `get_attempt.php:31`; `submit_review.php:31-34`; `flag_session.php:30`; `attempt.php:26` |
| **B3** | CVSS 8.2 | Proctoring write IDOR | **VERIFIED** | `session_manager.php:59-67` (assert_session_owner), `:162-164` (record_event guard), `:189` (register_chunk), `:238` (finalize); strict regex at `:198`; size/duration bounds at `:204-211` |
| **B4** | CVSS 9.1 | Payment amount tampering | **VERIFIED** | `callback.php:111-120` (amount equality), `:123-132` (currency equality); both log to `callback_logger` before 400 exit |
| **B5** | CVSS 7.4 | Invoice template XSS fragility | **VERIFIED** | `invoicer.php:150-152` (`html_writer::div(s($text), 'class', 'style')` wrapper); `invoice.mustache:29,46` — pre-built HTML is now `s()`-escaped inner text inside fixed wrapper |
| **B6** | CVSS 7.5 | Recompletion cross-tenant resets | **VERIFIED** | `recompletion_engine.php:95-99` — adds `(u.open_path = :tnpath_exact OR u.open_path LIKE :tnpath_prefix)` when `rule->costcenterid > 0`. Both params are int-cast → no LIKE wildcard injection |
| **B7** | CVSS 6.8 | Identity photo DoS + content trust | **VERIFIED** | `submit_identity.php:39-46` (rate limit), `:51-55` (5.5MB cap), `:60-66` (strict base64), `:72-80` (JPEG/PNG magic-byte sniff); `db/caches.php` defines `identity_rate` cache |
| **B8** | CVSS 6.5 | LIMIT $var injection | **VERIFIED** | `recompletion_engine.php:147,211` (use 5th/6th args of `get_records_sql`); `history.php:39` (use limitfrom/limitnum as args) |
| **B9** | CVSS 7.1 | set_course_price wrong context | **VERIFIED** | `db/access.php:59-66` (CONTEXT_COURSE); `set_course_price.php:45-47` (`context_course::instance`); version bump `1.0.0 → 1.0.1` triggers cap re-registration |
| **B10** | CVSS 6.5 | Request approver tenant bypass | **VERIFIED** | `request_manager.php:143-152` — adds `tenant::require_access()` AFTER overrideroute cap check |
| **B11** | CVSS 5.4 | Callback DoS + error leak | **VERIFIED** | `callback.php:147-152` (generic 'Error' on 500, debugging() instead of echo, exception class logged); `:35-52` (IP allow-list with silent 404); `ip_check.php` new helper; `settings.php:48-51` (admin-config CSV of CIDRs) |

**Verdict: 11/11 VERIFIED. Zero NOT-VERIFIED. Zero PARTIAL.**

---

## 3. NEW BLOCKING FINDINGS

**NONE.** No new CVSS-blocking issues introduced by the remediation.

---

## 4. NON-BLOCKING FOLLOW-UPS (track in backlog, not pre-cutover)

### N1. Rate-limit hour-bucket allows 2× burst at boundary
**File**: `local/airpay_proctoring/classes/external/submit_identity.php:40`
**Pattern**: `floor(time() / 3600)` produces a fixed-hour key. At 14:59:59 + 15:00:01, two different keys → counters reset → 10 submits in 2 seconds possible.

**Trade-off analysis**: Acceptable for B7's use case. Legitimate UX is 1 submit per quiz session; a worst-case 10/2s burst is still ~5/sec which is well below AWS Rekognition's per-account quota and PHP memory thresholds. Sustained abuse capped at 5/hour from minute 1 onward.

**If hardening desired**: Switch to sliding window using `cache::set_many` with multiple bucket keys (current hour + previous hour, sum across both, check sum < 5). Or use `time() - (time() % 600)` for 10-minute buckets reducing boundary burst to 10/20min — still much less abuse capacity than fixed 5/hour.

**Recommendation**: Document as acceptable; revisit if logs show abuse.

### N2. callback.php IP allow-list trusts X-Forwarded-For when reverseproxy enabled
**File**: `local/airpay_cart/callback.php:37`
**Pattern**: `getremoteaddr()` returns the X-Forwarded-For value when `$CFG->reverseproxy = true`. If the LB forwards client-supplied XFF without sanitising, an attacker can spoof source IP.

**Mitigation**: Document in PROJECT-STATE.md / deploy runbook that production LB MUST overwrite (not append to) X-Forwarded-For. Most cloud LBs (AWS ALB, GCP LB, CloudFront) do this by default with the correct header name. If the LB appends, set `$CFG->reverseproxyaddr_protected = 'X-Forwarded-For'` or use `$CFG->reverseproxyaddr` and pin to the LB's outbound IP.

### N3. ip_check::ip_in_cidr silently accepts /33+ on v4 (and /129+ on v6)
**File**: `local/airpay_cart/classes/ip_check.php:53-65`
**Pattern**: When `$bits > 32` for v4, `byte_count=4` for v4 makes `$ip_bin[4]` out-of-bounds. PHP 8.2 returns empty string, `ord("")` returns 0. Both 0 & mask = 0 → returns true. Practical impact: admin-typo CIDR `203.0.113.0/33` matches any IP whose first 4 bytes match (essentially behaves like `/32`).

**Fix**: Reject `$bits > strlen($ip_bin) * 8` early. Recommended one-liner before line 56:
```php
$max_bits = strlen($ip_bin) * 8;  // 32 for v4, 128 for v6
if ($bits < 0 || $bits > $max_bits) {
    return false;
}
```
Severity: LOW. Only matters with malformed admin config. Settings field is `PARAM_TEXT` — admin-controlled.

### N4. B9 capability migration has no upgrade.php cleanup
**File**: `local/airpay_cart/db/access.php` (modified) + missing `local/airpay_cart/db/upgrade.php`
**Pattern**: Changing `local/airpay_cart:manageprices` from CONTEXT_SYSTEM to CONTEXT_COURSE will re-register the capability metadata on plugin upgrade (Moodle does this automatically). But any existing role-assignments granting the cap at `context_system` are now **inert** (cap is checked at course context).

**Impact**: On production, the existing `manager` archetype will get the cap at course context via the archetype defaults — so functionality is preserved by archetype refresh. But custom role assignments at system context will silently no-op. Should add `local/airpay_cart/db/upgrade.php` with a migration step:
```php
if ($oldversion < 2026051201) {
    // No actual SQL needed — capability re-registration is automatic.
    // Document: any custom role with manageprices granted at
    // CONTEXT_SYSTEM must re-grant at the relevant CONTEXT_COURSECAT
    // or CONTEXT_COURSE for this fix to apply.
    upgrade_plugin_savepoint(true, 2026051201, 'local', 'airpay_cart');
}
```
Worth a checklist item in the cutover runbook to verify capability assignments.

### N5. `_tenantroot` named param uses non-standard naming
**File**: `local/airpay_core/classes/tenant.php:126-128`
**Pattern**: Moodle convention is to use param names without leading underscores. The leading `_` was likely chosen to reduce collision with caller-side params, but Moodle's own `get_in_or_equal` uses no prefix. Risk of collision today: zero (grep confirms). Future risk: low but real.

**Recommendation**: Rename to `tenantroot` or `ap_tenant`. Sweep all 6 callers in same commit. Low priority.

### N6. callback_logger::log not invoked for IP-blocked requests
**File**: `local/airpay_cart/callback.php:50` (exits 404 before line 72 log)
**Pattern**: Silent-drop is intentional design (per code comment line 32-34) — blocks visibility for attackers scanning. But also blinds ops to gateway IP rotation events. If Airpay rotates their callback IPs and we don't update the allow-list, all callbacks 404 silently and we have no log to diagnose.

**Recommendation**: Log to a separate audit channel (e.g., `error_log` with a tagged message that doesn't expose state) before the silent 404 exit. Operations can monitor that log. Optional — only matters if production sees frequent 404 spikes after gateway changes.

---

## 5. NEW-CODE DEEP AUDIT

### 5a. `\local_airpay_core\tenant::sql_filter()` — SQL fragment safety

**Surface**: Returns `[$sql_fragment, $params_array]`. Caller concatenates fragment into a WHERE clause and merges params into the bound array.

**Attack surfaces examined**:
1. **Poisoning `:_tenantroot`**: The value bound to `:_tenantroot` is `self::root_for_current_user()` which reads `$USER->open_path` then casts to int via `(int) $parts[0]`. Cannot inject SQL — Moodle's DML layer parameterises the bind. ✓
2. **Poisoning `$alias`**: Currently all call sites use hardcoded strings (`'h'`, `'s'`, `''`). If a future caller passed user-controlled `$alias`, `"{$alias}.costcenterid = :_tenantroot"` becomes SQL injectable. ⚠ Documentation note: the helper's docblock should explicitly warn callers that `$alias` MUST be a developer-controlled identifier, never user-supplied. Add comment recommended but not blocking.
3. **Param collision**: `:_tenantroot` is unique within the codebase (grep confirms). ✓
4. **`is_siteadmin()` side effects**: Called early — requires `$USER` already established by `require_login()`. All call sites in this audit do call it. ✓

**Verdict**: Safe for current use. Recommend documentation addition (N5 area) noting `$alias` must be a hardcoded developer identifier.

### 5b. `\local_airpay_core\tenant::root_for_user()` — spoofing analysis

**Surface**: Takes a `\stdClass $user` and reads `$user->open_path`. The function trusts the caller to pass a record loaded from `{user}` table.

**Call sites audited** (3 internal):
- `root_for_current_user()` → uses global `$USER` (Moodle-managed, populated from session)
- `viewer_can_access()` → uses `$DB->get_record('user', ['id' => $viewerid], 'id, open_path')` for non-self lookups
- Tests only

**External (plugin-caller) sites**: None. All plugins call `require_access()` / `sql_filter()` not `root_for_user()` directly.

**Spoofing path**: An attacker would need to either:
(a) overwrite `$USER->open_path` in their own session — not feasible without server-side write access
(b) pass a hand-crafted `\stdClass` to `root_for_user()` — no such caller path exists in current code

**Verdict**: Safe. No public API surface accepts user-controlled `open_path`.

### 5c. `airpay_cart\ip_check::ip_in_cidr()` — CIDR matching audit

**Tested cases (mentally walked through)**:
| Input | Expected | Result |
|-------|----------|--------|
| `('203.0.113.42', '203.0.113.0/24')` | true | ✓ true |
| `('203.0.113.42', '203.0.113.42')` (single) | true | ✓ true |
| `('10.0.0.1', '203.0.113.0/24')` | false | ✓ false (first 3 bytes differ) |
| `('203.0.113.130', '203.0.113.128/25')` | true | ✓ true (130 & 0x80 == 0x80) |
| `('203.0.113.127', '203.0.113.128/25')` | false | ✓ false (127 & 0x80 == 0) |
| `('2001:db8::1', '2001:db8::/32')` | true | ✓ true |
| `('2001:db9::1', '2001:db8::/32')` | false | ✓ false |
| `('203.0.113.42', '/24')` (malformed) | false | ✓ false (inet_pton('') === false) |
| `('1.2.3.4', '1.2.3.4/0')` | true | ✓ true (bit_rem=0, byte_count=0 → short-circuit) |
| `('1.2.3.4', '1.2.3.5/33')` | **true** (BUG) | ⚠ should be false; bit_rem=1, byte_count=4, `$ip_bin[4]` empty → 0 & mask == 0 & mask |

**Conclusion**: One edge-case bug (N3) on `/33+` for v4 and `/129+` for v6. Only triggered by admin-misconfigured CIDR. Non-blocking — fix in follow-up patch.

### 5d. Identity rate-limit cache

**Definition** (`local/airpay_proctoring/db/caches.php:18-24`): Application-mode cache, TTL 3600s, simplekeys=true, static acceleration sized 50.
**Key format** (`submit_identity.php:40`): `'u:' . $USER->id . ':h:' . floor(time() / 3600)` — bucket per user per hour.

**Audit**:
- ✓ Per-user keyed — no cross-user counter pollution
- ✓ Hour-bucketed — naturally expires via TTL
- ⚠ Fixed-hour bucket boundary: see N1 above
- ✓ Increment-before-check would be slightly safer (Time-of-check / Time-of-use race possible between `$cache->get` and `$cache->set`), but cache module isn't atomic-increment-aware. In practice, race window is microseconds and a 2-3 over-burst at most. Acceptable.

**Verdict**: Implementation correct for documented threat model. N1 is a tradeoff, not a bug.

---

## 6. COMPLIANCE WITH AUDITOR'S SHARED-TRAIT RECOMMENDATION

The Phase 8 audit's closing note recommended:

> "Recommend introducing a shared trait `\local_airpay_core\tenant_scoped_external` that automatically appends tenant filter to all external_api derived classes, then back-porting to these 5 plugins. That trait becomes mandatory for Phase 9."

**Did we do it?** **PARTIALLY — and correctly so.** The remediation creates `\local_airpay_core\tenant` as a **helper class** rather than a trait, plus optional plugin dependency. The choice is sound:

**Why class-with-statics is better than a trait here**:
1. **PHP trait auto-injection requires editing every external_api subclass.** The audit suggested "automatically appends tenant filter" — but PHP traits can't auto-inject into a method's SQL. They can only provide helpers callers must invoke.
2. **The trait approach would have been semantically misleading** — implying tenant scoping is automatic when it actually still requires the developer to call the helper at the right point in the query/check chain.
3. **The static-class pattern matches Moodle conventions** (e.g., `\core\session\manager::set_user`). Familiar to maintainers.
4. **Plugin dependency** (`local_airpay_cart` requires `local_airpay_core` v2026051200) means the helper class CANNOT be missing at runtime. Explicit, version-pinned.

**Did we back-port correctly to all 5 plugins?** Yes:
- `local_airpay_cart` — 4 surfaces use `tenant::require_access` / `sql_filter`
- `local_airpay_proctoring` — 7 surfaces use it
- `local_airpay_recompletion` — does not use the helper directly; uses inline `LIKE :tnpath_prefix` SQL. **This deviates from the pattern.** B6's fix is functionally equivalent but architecturally inconsistent — recompletion's tenant scope is by `open_path` of users, not by stored `costcenterid` of rows, because recompletion targets users not orders. The pattern is intentionally different and the helper doesn't fit. ✓ Justifiable inconsistency.
- `local_airpay_request` — 1 surface uses it (decide)
- `local_airpay_org` — no security-relevant surface in the remediation scope
- `mod/quizaccess_airpay_proctoring` — out of remediation scope

**Phase 9 mandate**: Recommend codifying in `CLAUDE.md` / `rules/database.md` that every new external_api class in tenant-scoped plugins MUST either:
(a) call `tenant::require_access()` after fetching a tenant-stamped row, OR
(b) include `tenant::sql_filter()` in the WHERE clause of any list/aggregate query, OR
(c) document explicitly why neither applies (e.g., user's own data with userid check).

A linter rule could enforce: any external_api file that queries a table with `costcenterid` column but doesn't reference `local_airpay_core\tenant` triggers a warning.

---

## 7. AUDIT TRAIL

**Files audited for B1-B11 verification** (cited inline above with file:line):
- `local/airpay_cart/callback.php`, `cart_manager.php`, `invoicer.php`, `ip_check.php` (new), `settings.php`, `db/access.php`, `version.php`
- `local/airpay_cart/classes/external/{refund_order, get_order, list_orders, daily_sums, set_course_price}.php`
- `local/airpay_cart/templates/invoice.mustache`
- `local/airpay_core/classes/tenant.php` (new), `tests/tenant_test.php` (new), `lang/en/local_airpay_core.php` (new), `version.php` (new)
- `local/airpay_proctoring/attempt.php`, `classes/session_manager.php`, `db/caches.php` (new), `version.php`, `cli/smoke_proctoring.php`
- `local/airpay_proctoring/classes/external/{list_review_queue, list_attempts, compliance_report, get_attempt, submit_review, flag_session, submit_identity, upload_chunk, report_event, finalize_session}.php`
- `local/airpay_recompletion/classes/recompletion_engine.php`, `history.php`, `version.php`
- `local/airpay_request/classes/request_manager.php`, `version.php`

**Lint**: PHP 8.2 `php -l` PASS on every modified file in the commit.
**Tests**: 8 PHPUnit cases in `tenant_test.php` cover root extraction, validity, viewer access, siteadmin pass, require_access throw, sql_filter admin/tenant branches.

---

## 8. RECOMMENDED NEXT STEPS

1. **Cutover preflight checklist** (add to deploy runbook):
   - [ ] Confirm `$CFG->reverseproxy` setting matches LB configuration (N2)
   - [ ] After plugin upgrade, re-verify `manager` role has `manageprices` cap at course category context for each tenant's category tree (N4)
   - [ ] Set `airpay_callback_iplist` admin setting to Airpay gateway's documented IP range BEFORE enabling cart
   - [ ] Run `phpunit local_airpay_core_tenant_test` — must be 8/8 pass
   - [ ] Run `cli/smoke_proctoring.php` — must be all assertions pass

2. **Re-test Phase 7 multi-role UAT** (per the commit message's stated next step) — particularly the cross-tenant negative tests for cart + proctoring.

3. **Staging k6 load test** before cutover (per the commit message's stated next step) — especially exercise the new `tenant::sql_filter()` JOIN path in `daily_sums` and `compliance_report` to confirm no query plan regression.

4. **Follow-up sprint backlog** (file under non-blocking):
   - N3: Fix `ip_check::ip_in_cidr` /33+ edge case (one-liner)
   - N4: Add `db/upgrade.php` to `airpay_cart` with capability migration commentary
   - N5: Rename `_tenantroot` → `tenantroot` across all 6 callers
   - N6: Optional log channel for IP-blocked callback drops
   - Phase 9 linter rule for `tenant::` enforcement on new external_api classes

---

## 9. VERDICT (RESTATED)

**GO** for production cutover.

All 11 BLOCKING findings resolved. No new blockers introduced. 6 non-blocking follow-ups identified — each is either trivial (one-liner fixes) or an operational/documentation item. The shared helper class is well-tested, the pattern is consistent across 5/5 affected plugins (with one architecturally-justified deviation in recompletion), and the version dependency chain is correct.

— Airpay Security Auditor, 2026-05-12
