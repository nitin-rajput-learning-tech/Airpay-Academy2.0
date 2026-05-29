# Bucket F — verify/investigate close-out (2026-05-29)

**Audit ref:** `PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` Bucket F
**Session:** resumed Opus 4.8 wave — "continue remaining" after the
F-024 / C10 P1 / C4 sequence closed.
**Scope:** the four Bucket F items that are *verifiable locally* with no
live POST, production deploy, or paid-API call — F-030, F-031, F-032,
F-033. Each was a "verify the claim / inventory the gap" item, not a
build. No production behaviour was changed by this pass.

---

## TL;DR

| Finding | Sev | Claim under audit | Verdict |
|---------|-----|-------------------|---------|
| **F-032** | 🟠 | paygw_airpay security (MD5→SHA-256, no file-scope `require_login()`, sandbox/live URL clarity) | ✅ **Confirmed on the live path.** One residual: an orphaned, insecure, *unreferenced* top-level `checksum.php` — recommend `[CONFIRM]`-gated removal. |
| **F-031** | 🟡 | course-share request state machine (pending/approved/rejected/already_shared) "looks complete" | ✅ **Verified complete + correct.** Idempotent transitions, properly gated, XSS-safe. No fix needed. |
| **F-033** | 🟠 | E2E framework "only has Site Admin scripts" | ✅ **Confirmed.** 5 Playwright specs = login-surface ×4 + 1 admin dashboard smoke. Zero learner/manager/auditor/author journeys. Build stays **PENDING NITIN**. |
| **F-030** | 🟡 | Challenges "multiple pendings" | ✅ **State-card accurate, schema matches.** 5 pendings are enhancements on existing schema; plugin is ALPHA/unexercised so deferral is correct. |

Net: **3 of 4 close with no code change** (verified-good). **F-032** spawns
one follow-up chip (remove orphaned `checksum.php`) gated on `[CONFIRM]`
because it is both a delete and a financial-gateway change.

---

## F-032 — paygw_airpay security follow-up (🟠 financial gateway)

**The audited fixes are correctly applied on the live payment path.**

The production flow is `pay.php` → `classes/airpay_helper.php` →
`classes/checksum.php`:

- **MD5 → SHA-256.** `classes/checksum.php::verifyChecksum()` calls
  `calculateChecksumSha256()` (`hash('SHA256', …)`) and compares with
  `hash_equals()` — timing-safe. The legacy `calculateChecksum()` (MD5)
  is `@deprecated`, emits a `debugging(DEBUG_DEVELOPER)` warning, and has
  **zero internal callers** (grep-confirmed). It is retained only for
  binary back-compat with any hypothetical external caller.
- **No `require_login()` at file scope.** `classes/checksum.php` and
  `classes/airpay_helper.php` both open with
  `defined('MOODLE_INTERNAL') || die();` — no file-scope auth side-effect,
  so they are unit-testable (and `tests/checksum_test.php` exercises
  them).
- **Sandbox/live URL clarity.** `airpay_helper::get_url()` returns the
  single documented Airpay endpoint and carries a docblock stating that
  sandbox-vs-live is determined by the merchant credentials (`mercid`),
  not the URL — with a note to confirm with Airpay before introducing a
  separate sandbox host. The intent is now explicit in code.
- `pay.php:65` uses `new \checksum` (the global class from
  `classes/checksum.php`), and `outputForm()` there escapes with `s()`.

### Residual: orphaned top-level `checksum.php`

`payment/gateway/airpay/checksum.php` (the **top-level** file, distinct
from `classes/checksum.php`) is **dead code**:

- Declares `namespace paygw_airpay\checksum; Class Checksum` — a
  *different* class from the global `\checksum` the gateway actually uses.
- **Nothing requires or instantiates it** (grep across the whole plugin:
  the only `require` of a checksum file is `airpay_helper.php` pulling in
  `classes/checksum.php`; the only `new \checksum` is `pay.php` using the
  global class).
- It still carries the exact anti-patterns F-032 was about:
  `calculateChecksum()` uses **MD5**; `verifyChecksum()` compares with
  `==` (not timing-safe); `outputForm()` echoes `$_POST` **unescaped**
  (XSS); `require('../../../config.php'); require_login();` runs at
  **file scope** (so it is a directly web-reachable standalone script);
  placeholder copyright ("YOUR NAME <your@email.com>").

It is **not a live vulnerability** (unreferenced; hitting it directly
only `require_login()`s then defines a class and exits with no output),
but it is insecure dead code sitting in a financial gateway — the right
move is to delete it.

**Action: ✅ RESOLVED 2026-05-29** (`[CONFIRM]`-gated chip; delete +
payment-gateway change → confirmed by Nitin in chat per CLAUDE.md
§3/§12). The orphaned top-level `checksum.php` was **deleted from both
trees** (`moodle-enhancement/payment/gateway/airpay/` workspace +
`payment/gateway/airpay/` served). A fresh pre-delete grep re-confirmed
**zero** references to `paygw_airpay\checksum` / the `Checksum` class /
the file path (workspace + served), and the post-delete gate
`grep -r "paygw_airpay\checksum"` now returns **nothing** — clean
removal. The live path (`pay.php:65 new \checksum` → `airpay_helper.php
require_once classes/checksum.php`) is unaffected.

While confirming the safety argument, a **deploy-drift finding** surfaced
that this doc's earlier description didn't capture: the *served* tree's
`classes/checksum.php` was still the **pre-F-032** version (MD5, `==`,
unescaped echo, file-scope `require_login()`, version `.09`) — the F-032
hardening had only ever landed in the `moodle-enhancement/` workspace.
Per Nitin's `[CONFIRM]`, the **full F-032 hardening was promoted
workspace → served** in the same chip: hardened `classes/checksum.php`
(SHA-256 + `hash_equals()` + `s()`), guard-first `classes/airpay_helper.php`,
the `int`→`float` `db/upgrade.php` fix (the old `int` hint truncated the
decimal version and would have thrown `downgrade_exception` on the
`.09`→`.10` bump), `version.php` `.09`→`.10`/`1.0.1`, and the 4 PHPUnit
test files. The two gateway trees are now byte-identical; all 19 PHP
files lint clean. **`purge_caches` + paid-course browser checkout were
NOT runnable in the cloud session** (no `config.php`/DB/runtime) — those
remain a manual Nitin step in the live Moodle env, as does removing the
XAMPP-deployed copy at `C:\xampp\htdocs\moodle5\public\…`.

---

## F-031 — course-share request state machine (🟡) — VERIFIED

`local/airpay_courses/manage_requests.php` (Super Admin pending-requests
inbox) + `classes/request_manager.php`:

- **Gating:** `require_login()` + `require_capability(
  'local/airpay_courses:approve_request', context_system)` +
  feature-flag gate (`commerce.crossTenantRequest.enabled`).
- **CSRF + input:** POST handler calls `require_sesskey()`, reads
  `required_param('action', PARAM_ALPHA)` +
  `required_param('requestid', PARAM_INT)`.
- **State machine** (`request_manager`): constants `STATUS_PENDING` /
  `STATUS_APPROVED` / `STATUS_REJECTED`; `approve_request()` and
  `reject_request()` are **idempotent** (early-return if already in the
  target state); rejected rows are retained (status='rejected', not
  deleted). The 4th state `already_shared` is **derived** from the
  current sharing state (takes precedence over pending/rejected) rather
  than stored — clean design.
- **Output:** every user-facing field rendered through `format_string()`
  — XSS-safe — into a mustache template.

**Verdict:** complete and correct; matches the state-card's "looks
complete" claim. No fix. (A full in-browser runtime walk would require
the cross-tenant flag ON + a seeded pending request + a super-admin
login — disproportionate for a 🟡 item whose code is demonstrably
correct and properly gated.)

---

## F-033 — E2E persona coverage (🟠) — INVENTORY CONFIRMS THE GAP

The repo uses **Playwright** (not Cypress). `tests/playwright/` contains
5 specs:

| Spec | Authenticates as | Journey |
|------|------------------|---------|
| `login.spec.ts` | anonymous | login form renders + CSRF token |
| `navbar.spec.ts` | anonymous | airpayux navbar renders on login page |
| `dark-mode.spec.ts` | anonymous | `prefers-color-scheme: dark` on login page |
| `mobile-590.spec.ts` | anonymous | login page, no horizontal overflow at 590px |
| `dashboard.spec.ts` | **admin** | post-login `/my/` landing smoke |

So: **4 specs hit the anonymous login surface; exactly 1 authenticates,
and only as `admin`.** There are **no authenticated journeys** for the
Learner, Manager/Supervisor, Compliance/Auditor, or Course Author
personas — confirming F-033 precisely. There is no E2E regression net
for 3-4 of the 4 persona journeys.

By design the specs are "happy-path + functional, <50 lines, no
`toHaveScreenshot()` until the gate goes blocking" (per
`tests/playwright/README.md` + `docs/ci/PLAYWRIGHT-GATE.md`).

**Recommended persona-journey backlog** (the build — **PENDING NITIN**,
L-effort; needs seeded per-persona test accounts):

1. **Learner:** login → catalog → enrol (free) → launch activity →
   completion reflected on `/my/`.
2. **Manager/Supervisor:** login → team view → approve a pending
   request (`local/airpay_request` or course-share) → state flips.
3. **Compliance/Auditor:** login → compliance dashboard reachable from
   sidebar (regression guard for Bug #11) → report renders.
4. **Course Author:** login → create course → add an activity → it
   appears in the catalog.

Each needs a dedicated low-privilege account provisioned in CI (env-var
credentials, mirroring `PLAYWRIGHT_ADMIN_USER/PASS`). Until then the
gate's authenticated coverage is admin-only.

---

## F-030 — Challenges pendings (🟡) — STATE-CARD ACCURATE, NO DRIFT

`local_airpay_challenge` state-card vs `db/install.xml`:

- **3 tables** as documented (`_challenges`, `_attempts`,
  `_leaderboard`) — exact match.
- The "pending" enhancements have their **schema already in place**:
  `challenges.cohortid` (the cohort-gating UI is the only missing piece —
  schema + join-time membership check exist) and `challenges.badge`
  ("Phase 2: tool_certificate template name" — the cert-badge wiring on
  completion is what's pending, not the column).
- `type` enum (`course_completion|streak|quiz_score|custom`) and
  `attempts.status` enum (incl. `expired`) confirm the Phase-2 features
  the state-card marks ✅ (streak, quiz-score, auto-expiry) ride on the
  existing schema with no migration — exactly as the schema was designed.

The 5 remaining pendings are all genuine **enhancements**, not broken
core: (1) tool_certificate badge on completion, (2) FCM peer-overtake
push (blocked on `airpay_integrations` cleanup), (3) dashboard-mountable
leaderboard widget (partially covered by `local_sentientia_leaderboard`
Phase L.0), (4) cohort-gating admin UI, (5) cross-tenant + per-cohort
leaderboard combinations.

**Verdict:** accurate, no schema/code drift. Deferral is correct — the
plugin is ALPHA/unexercised (Bucket D / F-096: attempt tables empty on
local + prod), so building enhancements for an unused feature is rightly
low priority. No action.

---

## What this pass did NOT touch (still `[CONFIRM]`/decision-gated)

- **F-032 residual** — remove orphaned `checksum.php` (delete +
  financial gateway → `[CONFIRM]`). Spawned as a follow-up chip.
- **F-033 build** — learner/manager/auditor/author E2E specs (PENDING
  NITIN; meaningful build + CI test accounts).
- The broader deferred set unchanged: **C8** (live Anthropic POST),
  **C9/F-025** (Calendar OAuth Phase 2), **C12** (5.2 prod cutover),
  **C13** (PWA prod), **B5** (`authloginviaemail` on prod), and the **21
  §5 v2-locked** items (incl. F-026–F-029 install verifications).

---

## Bucket F status after this pass

Of the 10 Bucket-F investigate items, the runtime-verifiable ones are
now closed: **F-024** (prior session), **F-030 / F-031 / F-032 / F-033**
(this pass). Remaining Bucket-F/§5 items are either `[CONFIRM]`-gated,
PENDING-NITIN builds, or v2-locked — none are silent unknowns anymore.
