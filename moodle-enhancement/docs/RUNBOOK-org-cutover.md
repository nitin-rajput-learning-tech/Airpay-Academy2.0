# RUNBOOK — Sentientia org-hierarchy cutover (ADR-020 Wave 3.4)

**Status:** PREP ONLY — authored 2026-06-02. **Nothing here has been executed. No flag
has been flipped; no production write has occurred.** This runbook is the gated plan for
Nitin to review and drive; the flip itself is human-gated (ADR-018 governing rule).

**Builds on:** ADR-020 (org migration), W3.1 seam, W3.2a schema + read API, W3.2a.1 manager
direct-edge, **W3.2b dual-write reconciler**, **W3.3 backfill + parity CLIs**.

---

## 0. Current state (what is already shipped + dormant)

| Piece | State |
|-------|-------|
| `local_sentientia_org_unit` / `_member` | Shipped, **empty on live**; populated on demand by the reconciler / backfill. |
| `org_dualwrite_enabled` flag | Shipped, **default OFF** — the `reconcile_org` cron no-ops until ON. |
| `org_legacy` flag | Shipped, **default ON** — manager/org reads come from BizLMS `open_supervisorid`. |
| Read seam `local_sentientia_core\org` | `manager_id_of()` (forward) has a legacy fallback + reads the model when `org_legacy` OFF. Reverse (`direct_reports`/`is_manager`) + tree walks read the **model only**. |
| Backfill / parity CLIs | `cli/backfill_org.php`, `cli/parity_check_org.php` (+ `org_parity` comparator). |

**Local rehearsal (2026-06-02, prod-data clone, 2,883 users):** backfill `--execute` →
160 units + 2,883 members; `parity_check_org` → **100% (0 manager / 0 membership
mismatches)**; re-run + `reconcile_org` task idempotent (0/0). Live was untouched.

---

## 1. Preconditions (ALL must hold before any flip)

1. W3.2b + W3.3 are on the production branch (done) and deployed to the target env.
2. On the target env: `org_dualwrite_enabled` ON and soaking, model backfilled, and
   `parity_check_org.php` reports **100%** for the tenant(s) in scope.
3. A **clone-DB rehearsal** of the whole sequence below has been completed and timed.
4. The §3 design decisions have been made (they govern *how complete* the cutover is).

---

## 2. Cutover sequence — ZEEA-first

Order: **177 (ZEEA, smallest) → 77 (Public) → 1 (Airpay, customer-zero, LAST).**

For each tenant root `R`:

1. **Clone** the production DB to a throwaway instance (RDS snapshot → temp instance).
2. On the clone: `org_dualwrite_enabled=1`; `php …/cli/backfill_org.php --tenant=R --execute`;
   `php …/cli/parity_check_org.php --tenant=R` → **MUST exit 0 (100%)**.
3. **On live:** enable `org_dualwrite_enabled` (the cron keeps the model warm). Soak for the
   agreed window (open question §3.5); re-run `parity_check_org.php --tenant=R` periodically
   → must stay 100%.
4. **Flip** `org_legacy` OFF (see §3.1 — currently a *global* flag). Verify manager-resolution
   surfaces for tenant R (manager dashboards, escalation targeting, "my manager" displays).
5. Soak + monitor. On **any** anomaly → flip `org_legacy` back ON (instant, lossless rollback).
6. Advance to the next tenant.

---

## 3. KNOWN GAPS / DECISIONS FOR NITIN

Surfaced during W3.4 prep (2026-06-02). The flip is **safe for forward manager-resolution
today**; a *complete* reader cutover needs these decisions. None blocks the W3.2b/W3.3 work
already shipped — they gate the reader migration + the shape of the flip.

### 3.1 `org_legacy` is GLOBAL, not per-tenant
The flag is a single site config (`get_config('local_sentientia_core','org_legacy')`). True
per-tenant "ZEEA-first" flipping needs a per-tenant override (e.g. a `org_legacy_off_roots`
list or `local_airpay_core\feature_flags` per-tenant override consumed by
`org::use_legacy_costcenter()`).
**DECISION:** build a per-tenant override (enables true ZEEA-first), **or** accept a single
global flip *after* confirming 100% parity for **all** tenants individually.

### 3.2 Reverse-lookup readers have no legacy fallback
`org::direct_reports` / `is_manager` read the **model only** (the W3.2a contract: "new
capabilities, no legacy equivalent"). Production reverse readers
(`airpay_manager\team_manager::get_team` / `can_manage`; theme `role_detector`) read
`user.open_supervisorid` directly. **They are NOT migrated and do NOT need to be for the
flip** — the `open_supervisorid` column stays populated through cutover (decommission is a
separate later ADR, §5), so they keep working.
**DECISION (at decommission, not cutover):** add a legacy fallback to
`direct_reports`/`is_manager` (changes the W3.2a contract + the model-only unit tests), **or**
migrate them only once the model is the sole source (post-cutover + soak), when reading the
model is already correct.

### 3.3 Aggregate-JOIN readers
`airpay_notifications\rule_engine` manager digests/escalations use
`JOIN {user} mgr ON mgr.id = u.open_supervisorid` + `GROUP BY open_supervisorid` (group
users by manager). These are not expressible via the per-user seam without a perf regression.
**DECISION:** add a "team-aggregate / managers-with-reports" seam method (with a legacy
fallback) before migrating these, **or** leave them on the live column until decommission.

### 3.4 Forward readers — safe, recommended first migration batch
Forward "who is X's manager" reads (e.g. `team_manager::can_view_member` supervisor
chain-walk; any "notify the learner's manager" lookup that already has the user record) can
migrate to `org::manager_id_of($user)` **today** — behaviour is byte-identical under
`org_legacy` ON, and they auto-switch to the model at flip. Recommended as **batch 1** (its
own commit + PHPUnit), once Nitin approves touching the manager surface. (Deferred from this
autonomous pass because the change is behaviour-neutral until flip and these are
user-facing/security-sensitive surfaces — better reviewed.)

### 3.5 Soak duration (ADR-020 OQ#1, still open)
Per tenant before advancing — suggested ZEEA 1wk → Public 1wk → Airpay. Confirm.

---

## 4. Reader inventory (2026-06-02 grep, committable plugins only)

Excludes lang strings, `db/messages.php`, HRMS *writers* (`hrms_importer`, `user_manager`,
`edit_user`, seed/fix CLIs), tests, and the owner's `airpay_compliance_report` WIP.

| Plugin / file | Kind | Target | Status |
|---|---|---|---|
| `airpay_manager\team_manager::can_view_member` | forward chain-walk | `org::manager_id_of` | **safe — batch 1** |
| `airpay_manager\team_manager::get_team` / `can_manage` | reverse | `org::direct_reports` / `is_manager` | blocked (§3.2) |
| `airpay_manager` requests/performance/member/index/allocations/approval_manager/external/forms | reverse + display | per-reader review | mostly reverse — §3.2 |
| `airpay_notifications\rule_engine`, `nudge.php` | aggregate JOIN | new aggregate seam | blocked (§3.3) |
| `airpay_courses/exams/lifecycle` `*_overdue` / `compliance_check` tasks | mixed (aggregate digest) | per-reader review | review per batch (§3.3/3.4) |
| `theme_airpayux\role_detector` (+ `layout/dashboard.php`) | reverse (is-manager) | `org::is_manager` | blocked (§3.2) |
| `airpay_org\tenant_manager` | mixed | per-reader review | review |
| `airpay_skills`, `airpay_assistant\ai_client` | forward/display | `org::manager_id_of` | review (likely safe) |
| `airpay_core\user_type\*_provider` | forward/display | `org::manager_id_of` | review (likely safe) |

Full migration runs in **reviewed batches** (each its own commit + test) **after** the §3
decisions. This runbook is the inventory; the batches are the execution.

---

## 5. Decommission of `open_supervisorid` / `local_costcenter` (separate later ADR)

Only after **all** tenants are stable on the model for a soak period: migrate the reverse +
aggregate readers (§3.2/3.3 decisions), remove all direct `open_supervisorid` reads, then
drop the column. Its own ADR + clone-DB rehearsal. **Not** part of this cutover.

---

## 6. Rollback (every stage)

Flip `org_legacy` back **ON**. The legacy `open_supervisorid` column and `local_costcenter`
are untouched until decommission, so rollback is **instant and lossless**. The model is
additive (dual-write), so discarding it loses nothing.

---

## 7. Command reference

```
# Populate / refresh the model (dry-run by default):
php local/sentientia_core/cli/backfill_org.php                 # dry run, all roots
php local/sentientia_core/cli/backfill_org.php --tenant=177 --execute   # ZEEA, write

# Objective parity gate (exit 0 = 100%):
php local/sentientia_core/cli/parity_check_org.php --tenant=177

# Enable dual-write (warm the model) — Site admin → Plugins → Local → Sentientia Core,
# or:
php admin/cli/cfg.php --component=local_sentientia_core --name=org_dualwrite_enabled --set=1

# THE CUTOVER FLIP (human-gated — do NOT run until §1 + §3 are satisfied):
php admin/cli/cfg.php --component=local_sentientia_core --name=org_legacy --set=0
# Rollback:
php admin/cli/cfg.php --component=local_sentientia_core --name=org_legacy --set=1
```

---

## Rehearsal log — 2026-06-02 (local prod-data DB, org_legacy ON)

`cli/parity_check_org.php` run per tenant on the local 2,883-user prod-data clone (model
backfilled per W3.3, `org_legacy` still ON). **All tenants 100% parity — cutover is GO,
ZEEA-first:**

| Tenant | In-scope users | Manager mismatches | Membership mismatches | Result |
|--------|---------------:|-------------------:|----------------------:|--------|
| ZEEA (177)   | 6     | 0 | 0 | 100% (exit 0) |
| Public (77)  | 682   | 0 | 0 | 100% (exit 0) |
| Airpay (1)   | 2,195 | 0 | 0 | 100% (exit 0) |
| **All**      | **2,883** | **0** | **0** | **100% (exit 0)** |

The objective go/no-go gate. The live flip (`org_legacy` OFF) remains the human-gated step in a
maintenance window; readiness is proven on the prod-data clone. Recommended order matches the
table (ZEEA -> Public -> Airpay), smallest blast radius first.
