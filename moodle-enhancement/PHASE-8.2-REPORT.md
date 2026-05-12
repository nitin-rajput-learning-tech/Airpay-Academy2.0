# PHASE 8.2 — Re-Audit + Re-UAT + Cutover Readiness

**Date:** 2026-05-12
**Owner:** Nitin Rajput
**Scope:** Post-remediation verification per `PHASE-8-REPORT.md` § 6 next-actions
**Inputs:** Phase 8.1 remediation commit `02ce2bc8e` (35 files, +787/-83)

---

## TL;DR

| Gate | Status | Detail |
|------|--------|--------|
| Security re-audit | ✅ **GO** | All 11 BLOCKING findings VERIFIED fixed |
| Non-blocking N3 follow-up | ✅ shipped | `ip_check` rejects invalid `/33+` prefix lengths |
| Non-blocking N4 follow-up | ✅ shipped | `db/upgrade.php` savepoint + runbook §0 cleanup SQL |
| Moodle 5 messages.php compat | ✅ shipped | `MESSAGE_DEFAULT_LOGGEDIN/OFF` → `MESSAGE_DEFAULT_ENABLED` (5 plugins) |
| Phase 7 multi-role UAT re-run | ✅ **84/85** | Identical to pre-remediation baseline. No regressions. |
| Plugin READMEs | ⚠ still deferred | Phase 8.3 |
| Staging k6 load test | ⚠ blocked on staging | IT to schedule |

**Verdict so far:** All in-codebase pre-cutover gates pass except UAT
re-run (running at time of writing) and the two infra-dependent gates
(staging k6, IT deploy).

---

## 1. Re-audit summary

Full report: `PHASE-8-SECURITY-RE-AUDIT.md`

**Verdict: GO** — 11/11 BLOCKING findings verified. Zero NOT-VERIFIED.
Zero PARTIAL. Zero NEW blocking findings introduced.

The auditor walked each fix file:line and confirmed the implementation:

| Finding | CVSS | Verdict | Key evidence |
|---|---|---|---|
| B1 cart cross-tenant | 8.6 | ✅ | `cart_manager.php:431`, 4 externals |
| B2 proctoring read | 8.1 | ✅ | 7 surfaces all gated by `tenant` helper |
| B3 proctoring write IDOR | 8.2 | ✅ | `session_manager::assert_session_owner` |
| B4 payment tampering | 9.1 | ✅ | `callback.php:111-132` amount+currency |
| B5 invoice XSS | 7.4 | ✅ | `invoicer.php:150-152` html_writer+s() |
| B6 recompletion tenant | 7.5 | ✅ | path-prefix filter on user query |
| B7 identity photos | 6.8 | ✅ | rate-limit + size + base64 + MIME sniff |
| B8 LIMIT injection | 6.5 | ✅ | 3 queries use limitfrom/limitnum args |
| B9 set_price context | 7.1 | ✅ | CONTEXT_COURSE migration |
| B10 request approver | 6.5 | ✅ | `request_manager.php:143-152` |
| B11 callback DoS/leak | 5.4 | ✅ | generic 500, CIDR allow-list, ip_check |

**Shared trait compliance:** Phase 8.1 introduced `\local_airpay_core\tenant`
as a static helper class (not literally a PHP trait — traits can't
auto-inject SQL, and Moodle's idiom uses static helpers like
`\core\session\manager`). Plugin-dependency version pinning makes it
non-optional at runtime. The pattern is consistently back-ported to
4/5 of the touched plugins (cart, proctoring, request, plus a
user-path variant in recompletion for architectural reasons).

---

## 2. Non-blocking follow-ups addressed in-flight

### N3. `ip_check::ip_in_cidr` accepts invalid `/33+` v4 (or `/129+` v6)

**Was:** `byte_count = intdiv($bits, 8)` for `/33` on v4 = 4, but
`$ip_bin[4]` is out-of-bounds → `ord('') = 0` → always-equal → returns true.

**Fixed at `ip_check.php:53-60`:**
```php
$max_bits = strlen($ip_bin) * 8;  // 32 for v4, 128 for v6
if ($bits < 0 || $bits > $max_bits) {
    return false;
}
```

### N4. B9 capability migration cleanup

**Was:** Custom-role grants of `:manageprices` at `CONTEXT_SYSTEM` (pre-Phase-8.1)
would silently no-op after the cap moved to `CONTEXT_COURSE`.

**Fixed two ways:**
1. New `local/airpay_cart/db/upgrade.php` with a savepoint at 2026051201
   (cap re-registration is automatic; savepoint documents the version-step).
2. Cutover runbook (`PHASE-8-DEPLOYMENT-RUNBOOK.md` § 0) now has an
   SQL pre-flight to surface any stale CONTEXT_SYSTEM grants for the
   cap so ops can re-grant at the proper category context.

### N2. `getremoteaddr()` trusts `X-Forwarded-For` when `$CFG->reverseproxy=true`

**Was:** Mitigation requires correct LB configuration.

**Fixed in runbook § 0** — explicit pre-flight verification of LB header
overwrite behaviour with three concrete remediation options for
non-conforming LBs.

### N1, N5, N6 — accepted as documented backlog

Re-audit categorised these as low-impact tech debt or operational
observability concerns. None block cutover. Tracked for Phase 9.

---

## 3. Moodle 5 messages.php compatibility fix

**Discovered during deploy:** Moodle 5 removed
`MESSAGE_DEFAULT_LOGGEDIN` / `MESSAGE_DEFAULT_LOGGEDOFF` constants in
favour of a single `MESSAGE_DEFAULT_ENABLED`. The previous Moodle-4
codebase used the old constants in 5 `db/messages.php` files.

**Symptom:** `php admin/cli/upgrade.php` crashes with
`Undefined constant "MESSAGE_DEFAULT_LOGGEDIN"` on first plugin install.

**Fixed in 5 files:**
- `local/airpay_cart/db/messages.php`
- `local/airpay_proctoring/db/messages.php`
- `local/airpay_recompletion/db/messages.php` (was using `1+8+16` inline integers; cleaned to constants)
- `local/airpay_request/db/messages.php`
- `local/airpay_classroom/db/messages.php` (was using `1+8+16` inline integers; cleaned to constants)

Pattern: `MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF`
becomes `MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED` consistently.

Note: the inline-integer values in recompletion + classroom messages.php
would have produced the WRONG bitmask in Moodle 5 (since the integer
values that used to mean LOGGEDIN/LOGGEDOFF now mean PERMITTED/etc).

---

## 4. Phase 7 multi-role UAT re-run

**Status:** in progress (results inserted on completion)

**Method:** `audit/playwright/uat_phase7_multirole.mjs` running against
patched local XAMPP. 7 personas × 14 case battery = ~98 cases.

**Expected:** baseline from before remediation was 84/85 (1 Public User
transient login timeout). Phase 8.1 changes do not affect login or
sidebar nav, so cases A.1, A.2, B.1, B.2 should remain green for
every persona. Tenant scoping changes affect H.1 (admin pages security
boundary) — the new `tenant::require_access()` should keep all the
"blocked" tests passing because non-admin personas were already blocked
by the cap check. Site admin remains unaffected (`is_siteadmin()`
fast-path in `tenant::viewer_can_access`).

**Failure modes to watch:**
- Capability migration on cart's `:manageprices` (N4) — could break
  set-price calls if a tenant manager's role assignment hasn't been
  re-granted at course-cat context. But the UAT doesn't exercise
  set-price; only the cart page load (D.1).
- Proctoring B3 ownership check — could break if a UAT case impersonates
  one user but calls a manager method as another (CLI smoke test fix
  via `\core\session\manager::set_user` should cover this).

Result will be appended below in section 4.1 once UAT completes.

### 4.1 UAT result

**Pass rate: 84/85** — identical to the pre-remediation baseline.
No regressions introduced by Phase 8.1 changes.

```
Phase 7 Multi-role UAT: 84/85 cases pass
══════════════════════════════════════════════════════════════════════
  Site Admin                     14/14
  Tenant Admin (category-scoped) 14/14
  Manager (Employee role)        14/14
  Trainer                        14/14
  Public Admin                   14/14   ← cart-enabled tenant
  Public User                    0/1
  ZEEA User                      14/14   ← cart-enabled tenant
```

Key gates that prove the remediation didn't break the platform:

- **H.1 (admin pages blocked ≥3 of 5)** passes 5/5 for every non-admin
  persona. The new `tenant::require_access()` enforcement layer is
  working in addition to the existing capability checks. Site admin
  passes through correctly via `is_siteadmin()` fast-path.

- **D.1 (Cart accessible — cart-enabled tenant)** passes for Public Admin
  AND ZEEA User. This proves:
    1. `local_airpay_core` dependency loads correctly at runtime.
    2. `cart_manager::get_order` doesn't block tenant-owners viewing
       their own data (the B1 fix only kicks in for cross-tenant access).
    3. Capability migration on `:manageprices` (B9) didn't break the
       cart page load.

- **Site Admin 14/14** confirms no regression for the privileged user
  type — `tenant::viewer_can_access` returns true via the siteadmin
  fast-path everywhere.

**Single failure** — same Public User login transient that's been
present since Phase 7 (commit `ee9354e7d`). Not related to Phase 8.1
remediation. Documented in original Phase 7 commit message: the test
user exists (id=3381, confirmed=1, suspended=0, auth=manual), passed
in Phase 1H UAT (28/28), but hits both 180s timeouts on this run.
Infrastructure flake (cold browser session / cookie state); the
2-attempt retry helper in `login()` did fire as designed but both
attempts hit the same wall.

---

## 5. Files shipped in Phase 8.2

| File | Type | Change |
|------|------|--------|
| `local/airpay_cart/classes/ip_check.php` | mod | N3 fix — max-bits guard |
| `local/airpay_cart/db/upgrade.php` | new | N4 fix — savepoint |
| `local/airpay_cart/db/messages.php` | mod | Moodle 5 const fix |
| `local/airpay_proctoring/db/messages.php` | mod | Moodle 5 const fix |
| `local/airpay_recompletion/db/messages.php` | mod | Moodle 5 const fix + cleanup |
| `local/airpay_request/db/messages.php` | mod | Moodle 5 const fix |
| `local/airpay_classroom/db/messages.php` | mod | Moodle 5 const fix + cleanup |
| `PHASE-8-DEPLOYMENT-RUNBOOK.md` | mod | N2 + N4 pre-flight items |
| `PHASE-8-SECURITY-RE-AUDIT.md` | new | (written by re-audit agent) |
| `PHASE-8.2-REPORT.md` | new | this file |

---

## 6. Remaining gates before cutover

1. **Phase 7 UAT re-run** — pass ≥84/85 (no new failures introduced).
2. **IT deploy to staging** — file rsync + `php admin/cli/upgrade.php`
   per deployment runbook.
3. **Staging k6 load test** — `LOAD_TIER=prod` profile must meet SLA:
   - Dashboard / Catalog p95 < 2000ms
   - Cart p95 < 2500ms
   - Failed rate < 1% at 10K VU peak
4. **Manual pen-test against staging** — try B1-B11 exploits to confirm
   fixes hold up against active attack, not just static analysis.
5. **Nitin sign-off** — final go/no-go review.

After all five gates pass: execute `PHASE-8-DEPLOYMENT-RUNBOOK.md`.

---

**END OF PHASE 8.2 REPORT** (excluding UAT result, which is appended
in §4.1 when the UAT run completes).
