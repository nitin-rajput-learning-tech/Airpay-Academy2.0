# PHASE 8 — Production Hardening Report

**Date:** 2026-05-12
**Owner:** Nitin Rajput
**Scope:** Final pre-cutover gate per `ENTERPRISE-GRADE-PLAN.md` § 8.8
**Output of:** automated security audit + load test scripts + deployment runbook

---

## TL;DR

| Gate | Status | Detail |
|------|--------|--------|
| Security audit | ❌ **NO-GO** | 11 blocking findings — see `PHASE-8-SECURITY-AUDIT.md` |
| Load test script (k6) | ✅ ready | `audit/load/load_test.k6.js` — runnable in staging |
| Load baseline (local) | ⚠ informational | Local XAMPP saturates at 10-15 VUs — not predictive of prod |
| Deployment runbook | ✅ shipped | `PHASE-8-DEPLOYMENT-RUNBOOK.md` |
| Plugin READMEs | ⚠ deferred | Doc-writer agent stalled; queued for Phase 8.1 |

**Verdict:** Cutover is gated on closing the 11 BLOCKING audit findings.
Estimated 2-3 dev days + 1 day re-test before re-running this gate.

---

## 1. Security audit summary

Full report: `PHASE-8-SECURITY-AUDIT.md`

**Scope:** 5 new plugins + 1 quizaccess subplugin shipped during Phases 1-6
(~20K LOC total): `airpay_cart`, `airpay_proctoring`, `airpay_recompletion`,
`airpay_request`, `airpay_org` (Phase 6 sync_cohorts.php), and
`mod/quizaccess_airpay_proctoring/`.

**Methodology:** Static analysis by `Airpay Security Auditor` agent
(Opus 4.7 1M ctx). OWASP Top 10 mapped, Moodle-specific patterns checked,
multi-tenant data isolation traced per query, GDPR provider coverage verified.

### Blocking findings (must fix before cutover)

| # | Title | OWASP | CVSS |
|---|-------|-------|------|
| B1 | Cross-tenant refund + PII leak via cart externals | A01 | 8.6 |
| B2 | Cross-tenant proctoring leak via review queue | A01 | 8.1 |
| B3 | Proctoring chunk/event IDOR (no session-owner check) | A01 | 8.2 |
| **B4** | **Payment amount-tampering on gateway callback** | **A04/A08** | **9.1** |
| B5 | XSS-fragility in invoice template (defense-in-depth) | A03 | 7.4 |
| B6 | Recompletion engine resets across ALL tenants | A01 | 7.5 |
| B7 | PARAM_RAW on identity photos → memory DoS + AWS quota burn | A04/A05 | 6.8 |
| B8 | LIMIT $var interpolation in recompletion SQL | A03 | 6.5 |
| B9 | `set_course_price` cap is system-context not course-context | A01 | 7.1 |
| B10 | Approver routing bypass — `:overrideroute` is system-wide | A01 | 6.5 |
| B11 | Webhook callback leaks PHP error + no rate limit | A05/A09 | 5.4 |

### Non-blocking findings (track + ship)

9 lower-severity items (CSV injection, S3 retention stub, race
conditions on approval, AWS retry policy, etc.). See full report.

### Architectural pattern behind the blockers

10 of 11 blocking findings share a root cause: **`require_capability()`
checks at `CONTEXT_SYSTEM` without an additional tenant-equality check.**
Moodle's capability system was designed for site-wide vs. course-scope —
multi-tenancy via `costcenterid` is a layer above that, and our new
plugins missed the second check. The auditor recommends a shared trait
`\local_airpay_core\tenant_scoped_external` that backs the WS layer and
makes the check mandatory.

### Remediation order

1. B4 + B11 (callback hardening — same file, ship together)
2. B1 + B2 + B10 (cross-tenant access — same pattern, factor once)
3. B3 (proctoring IDOR — single helper)
4. B6 (recompletion tenant scoping)
5. B5 + B7 (XSS + DoS hardening)
6. B8 (LIMIT injection refactor)
7. B9 (set_price context-level migration)

Each finding includes exact file paths, line numbers, and corrected code
snippets in the audit report. The auditor estimates 2 dev days + 1 test
day. Re-run this audit against the diff to verify GO before cutover.

---

## 2. Load testing artifacts

### `audit/load/load_test.k6.js`

The real cutover gate. k6 script with two profiles:

- **local** — 50 VU ramp for sanity-checking after a code change
- **prod** — 10,000 VU ramping profile that mirrors the annual compliance
  reset spike (5-min hold at 10K VUs)

SLA thresholds (prod tier):

| Surface | p95 | p99 |
|---------|-----|-----|
| Dashboard | < 2000 ms | < 5000 ms |
| Catalog | < 2000 ms | < 5000 ms |
| Cart | < 2500 ms | < 6000 ms |
| Quiz | < 2500 ms | — |
| Failed rate | < 1% | — |

Run against staging with prod-sized RDS clone before cutover:

```powershell
$env:BASE_URL = 'https://staging.airpay.academy/moodle'
$env:LOAD_TIER = 'prod'
$env:AUTH_COOKIE = 'MoodleSession=...'  # for authed surface mix
k6 run audit/load/load_test.k6.js
```

### `audit/load/load_baseline.mjs`

Node-native script (zero dependencies) for local sanity checking. Not a
substitute for staging k6, but useful in dev to catch slow-path regressions.

### Local baseline numbers (2026-05-12, XAMPP, dev box)

| Concurrency | Duration | Result |
|-------------|----------|--------|
| 3 VUs | 20s | 0% err, p95 ≈ 6-7s per page (cold caches) |
| 20 VUs | 30s | 100% err (all 30s timeouts) — XAMPP MaxClients saturated |

**Interpretation:** local XAMPP saturates ~10-15 uncached concurrent
requests. Production (RDS-backed, dedicated DB host, MPM-tuned Apache)
will not have this shape. **The cutover gate stays: k6 against staging
with a prod-sized RDS clone.** Local numbers are not predictive.

---

## 3. Deployment runbook

Full: `PHASE-8-DEPLOYMENT-RUNBOOK.md`

10 sections covering pre-flight checks, maintenance-mode toggle, code
deploy via rsync, DB upgrade, cache purge, smoke test (with the Phase 7
multi-role UAT harness as the automated component), maintenance-mode off,
rollback procedure, 24-hour post-cutover monitoring, and comms templates.

**Top of the runbook is a hard gate**: do not start until security audit
re-run returns GO.

---

## 4. Plugin documentation refresh — DEFERRED to Phase 8.1

The doc-writer agent dispatched in this session stalled at 600 seconds with
no progress and zero files written. The 5 plugin READMEs + standalone
deployment runbook were planned outputs. The runbook is delivered (written
manually). Plugin READMEs are deferred to a focused Phase 8.1 session.

Each plugin's existing state card under `state-cards/` covers component
metadata, capabilities, tables, web services, and verification steps in the
interim. Cutover doesn't depend on README files; they are L&D-facing.

---

## 5. Phase 8 deliverables — actual

| Artifact | Path | Status |
|----------|------|--------|
| Security audit report | `PHASE-8-SECURITY-AUDIT.md` | ✅ |
| k6 load test script | `audit/load/load_test.k6.js` | ✅ |
| Node-native load baseline | `audit/load/load_baseline.mjs` | ✅ |
| Load README | `audit/load/README.md` | ✅ |
| Deployment runbook | `PHASE-8-DEPLOYMENT-RUNBOOK.md` | ✅ |
| Phase 8 master report (this file) | `PHASE-8-REPORT.md` | ✅ |
| 5 plugin READMEs | — | ⚠ deferred to Phase 8.1 |

---

## 6. Recommended next actions

### Phase 8.1 — Remediation (NEXT SESSION)

1. **Fix B1-B11** in this order: B4 → B11 → B1 → B2 → B10 → B3 → B6 → B5 → B7 → B8 → B9
2. **Introduce shared trait** `\local_airpay_core\tenant_scoped_external` to
   prevent regression (auditor's structural recommendation)
3. **Re-run security audit** against the diff — must return GO
4. **PHPUnit augmentation**: add tenant-cross test cases for each fixed external
5. **Re-run Phase 7 multi-role UAT** against patched code — must remain 84/85+

### Phase 8.2 — Docs + final gate

1. Plugin READMEs (5 files, ~200 lines each)
2. State card refresh (final state cards for cutover packet)
3. PROJECT-STATE.md final EOD entry

### Phase 8.3 — Staging k6 + pen-test

1. Deploy to staging with prod-sized RDS clone
2. Run `k6 load_test.k6.js` with `LOAD_TIER=prod`
3. Manual pen-test against staging (try B1-B11 exploits to confirm fixed)
4. Cutover sign-off from Nitin

### Phase 9 — Cutover

Execute `PHASE-8-DEPLOYMENT-RUNBOOK.md` end-to-end.

---

## 7. Risk + decision register

| Risk | Likelihood | Impact | Decision |
|------|-----------|--------|----------|
| Cutover before fixing B4 | LOW (audit catches) | CATASTROPHIC (financial fraud) | **HARD GATE** — must fix |
| Cutover before fixing B1/B2 | LOW (audit catches) | HIGH (cross-tenant PII leak) | **HARD GATE** — must fix |
| Cutover with N-series open | MODERATE | LOW-MEDIUM | track + ship; close in Phase 9.1 |
| Doc-writer stall recurs | MEDIUM (token budgets) | LOW (docs are L&D not gate) | use focused per-plugin prompts, retry |
| Load test on staging fails SLA | MEDIUM | HIGH (cutover blocked) | early k6 run on staging during Phase 8.1 |
| Phase 7 UAT regression after B-fix | MODERATE | HIGH (rollback risk) | re-run Phase 7 after every B-batch |

---

**Phase 8 verdict: NO-GO for production cutover. Engineering work remains.**
**Phase 8.1 (remediation) is the immediate next session.**
