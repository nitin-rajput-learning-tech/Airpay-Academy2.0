# ADR-021 — `tenant_registry` + the multi-customer tenant table (Independence Wave 4)

**Status:** **Accepted — executing** (2026-06-01, Nitin's "do not defer anything" go). Wave 4.1 (seam + schema + seed/parity CLIs + `:managetenants` admin UI) BUILT additive + default-legacy + locally rehearsed (PHPUnit). The live cutover (flipping `tenant_registry_legacy` OFF on production) remains gated on Nitin's deploy + clone-DB rehearsal. · **Owner:** Nitin Rajput
**Parent:** ADR-018 (independence roadmap) Wave 4 · **Builds on:** ADR-019 (Wave 2, `tenant_identity` seam), ADR-020 (Wave 3, `local_sentientia_org`).
**Pairs with:** `docs/DEPRECATION-SCHEDULE.md` (row 9), `docs/BIZLMS-MIGRATION-NARRATIVE.md` (Wave 4). **Relates to:** ADR-002 (customer-level feature flags), ADR-008 (customer brand schema).

> This is a **design/decision record only**. No schema is created and no data is
> migrated by writing this ADR. Execution is explicitly gated on Nitin's approval
> + a clone-DB rehearsal, per ADR-018's governing rule. It is the last tenancy
> coupling, so it is also the one with the most product leverage — it is what turns
> "Airpay's 3 cost-centers" into "Sentientia's customer registry".

## Context

After Wave 2 moved every `open_path` *read* onto `tenant_identity`, exactly one
BizLMS tenancy coupling remains hardcoded:

```php
// local/airpay_core/classes/tenant.php
public const VALID_TENANTS = [1, 77, 177];          // line 25 — Airpay / Public / ZEEA
...
if (!in_array($tenantid, self::VALID_TENANTS, true)) // line 58 — assert_valid()
    throw new \moodle_exception('error_invalidtenant', 'local_airpay_core');
```

This array is the **tenant allow-list**: the single source of truth for "which
tenant roots exist". `BIZLMS-MIGRATION-NARRATIVE.md` rates it **SOFT** (small,
self-contained — but hardcoded), and `DEPRECATION-SCHEDULE.md` row 9 schedules it
for a **DB-backed `tenant_registry`** in Wave 4, flagged 🟠 needs-human (DB + capability).

It blocks the product directly. The end-state decision (Q2) is **"shared instance,
each customer = a top-level tenant tree."** You cannot onboard Enterprise N onto a
shared instance while the list of valid tenants is a constant three integers in an
Airpay-namespaced PHP file. The registry is what makes the multi-customer model in
`CLAUDE.md` ("each customer = top-level tenant tree") actually exist as data.

## Decision (proposed)

Introduce a **`tenant_registry`** service in `local_sentientia_core`, backed by new
Sentientia-owned tables, migrated onto in the same additive, reversible,
default-legacy manner as Waves 2–3.

### 1. The seam first (additive, no data change)
A `local_sentientia_core\tenant_registry` service:
- `valid_roots(): int[]` — replaces reads of `VALID_TENANTS`.
- `is_valid(int $root): bool` — replaces the `in_array(..., VALID_TENANTS, true)` test in `assert_valid()`.
- `customer_of(int $root): int` / `roots_for_customer(int $customerid): int[]` — the multi-customer mapping (no caller needs these yet; they exist for Wave 4+).
- Behind a **default-ON** `tenant_registry_legacy` flag, every method returns the
  hardcoded `[1, 77, 177]` (delegating to `local_airpay_core\tenant` when present,
  `class_exists`-guarded with an inline `[1,77,177]` fallback) — byte-identical to today.
- When OFF (reserved until the table is seeded + parity-checked), it reads the table.
  Until seeded, OFF falls back to legacy with a `DEBUG_DEVELOPER` note — a mis-flip
  cannot lock anyone out of their tenant.

`local_airpay_core\tenant::assert_valid()` / `VALID_TENANTS` then delegate to the
seam (keeping the old API working), exactly as Wave 2 left the open_path parser in place.

### 2. The schema (new tables — the gated step)
- **`local_sentientia_tenant`** — `id, rootid, customerid, name, idnumber,
  status (active|suspended|archived), timecreated, timemodified`
  (+ **unique** index `rootid`, index `customerid`, index `status`).
  `rootid` is today's cost-center root (1 / 77 / 177); `customerid` is the
  forward-looking owner link.
- **`local_sentientia_customer`** — `id, name, shortname, status, timecreated,
  timemodified` — the top-level customer (Airpay = customer 1). One customer owns
  ≥1 tenant root. **This must reconcile with any customer entity already implied by
  ADR-002 (feature flags) + ADR-008 (brand) — see open question 1**; we do not want
  two "customer" tables.
- Both carry the multi-tenant scoping discipline of `.claude/rules/database.md`
  (`timecreated`/`timemodified` always; tenant-scoped reads stay scoped).

### 3. The migration (seed → parity → cutover)
1. **Seed CLI** (`admin/cli/`): one-shot insert of the 3 known roots into
   `local_sentientia_tenant` (customer 1 = Airpay owns all 3 for customer-zero).
   Idempotent, resumable, `--dry-run` first.
2. **Dual-read parity check** (CLI): assert `tenant_registry::valid_roots()` (table)
   == `VALID_TENANTS` (hardcode) and `is_valid()` agrees for `{0,1,77,177,999}`.
   Must be 100% before cutover.
3. **Cutover:** flip `tenant_registry_legacy` OFF. The allow-list is read-only at
   runtime (validation only), so unlike the Wave-3 org tree there is **no per-tenant
   staged cutover** — it is a single flag flip, instantly reversible.
4. **Admin UI + capability:** `local/sentientia_core:managetenants` gates a small
   admin page to add/suspend tenants + customers (this is what onboards Enterprise N).
5. **Decommission:** the `VALID_TENANTS` constant becomes a last-resort fallback,
   removed in a later ADR after a soak (see open question 5).

### 4. Rehearsal + rollback (mandatory before any prod step)
- Seed + parity rehearsed on a **clone of the production DB** (MySQL 8 RDS snapshot →
  throwaway) first, verified, before touching live.
- Rollback = flip `tenant_registry_legacy` back ON (instant; the hardcode is untouched).
  The seed is additive (new tables), so rollback never loses data.

## Why this shape (not a config setting, not a big-bang)
- **A table, not a setting:** a config array can't carry per-tenant metadata
  (customer ownership, status, brand FK) and can't grow at runtime — the table is
  what makes "customer = top-level tenant" queryable + onboardable.
- **Customer-zero never regresses:** default-ON flag = today's exact three roots;
  a single reversible flip changes the source, after an objective parity gate.
- **Lowest-risk wave of the three:** the allow-list is validation-only + tiny
  (3 rows), so unlike Wave 3 there is no live-tree dual-write and no staged cutover —
  but it still goes through clone-DB rehearsal because it is a schema + capability change.

## Consequences
- **Positive:** the tenant allow-list becomes Sentientia-owned data; new customers
  are an admin action, not a code change + redeploy; the `airpay_core` namespace
  loses its last tenancy hardcode; the registry is the natural home for the
  customer↔tenant↔brand↔flags joins ADR-002/008 already need.
- **Negative / accepted:** introduces a `customer` entity that must be reconciled
  with ADR-002/008 (open question 1) before the schema is final; adds a tiny
  validation read that must stay cached (it is hit on every `assert_valid`).
- **Risk:** lower than Wave 3 (no live-data tree, no per-tenant cutover), but it is
  still a DB + capability change on a multi-tenant prod system — hence the gate.

## Open questions for Nitin (resolve before execution)
1. **Customer entity** — reuse/extend the customer concept already implied by ADR-002
   (customer-level feature flags) + ADR-008 (customer brand), or is "customer" simply
   the top-level tenant root for now (`customerid == rootid`)? *(Recommend: one
   canonical `local_sentientia_customer`, with ADR-002/008 FK-ing to it — avoid a
   second customer table later.)*
2. **Seed source** — seed the 3 roots from the `VALID_TENANTS` hardcode (authoritative,
   tiny), or discover top-level nodes from `local_costcenter`? *(Recommend: seed from
   `VALID_TENANTS`; cross-check against costcenter top-level nodes as a parity assert.)*
3. **Who manages the registry** — site admin only, or a per-customer operator
   capability? *(Recommend: `:managetenants` = site-admin-only for v1; per-customer
   delegation is a later capability — mirrors ADR-020 Q4.)*
4. **New-customer onboarding path** — when Enterprise N arrives, does an admin create
   the customer + tenant via the UI, a provisioning CLI, or eventual self-serve signup?
   *(Recommend: admin UI + CLI for v1; self-serve is far-future and out of this wave.)*
5. **Decommission timeline for `VALID_TENANTS`** — keep the constant as a defence-in-depth
   fallback indefinitely (lower risk) or remove after a soak (cleaner)? *(Recommend:
   keep one release as last-resort fallback, then remove — mirrors ADR-020 Q5.)*

## Next
On approval: 4.1 the `tenant_registry` seam + PHPUnit (additive, default-ON — ships
like the Wave-2/3 seams, exercised by the CI `phpunit-52` gate); 4.2 the schema +
seed + parity CLIs; 4.3 the `:managetenants` admin UI; 4.4 the cutover flag flip
(clone-DB rehearsal first). Each its own commit + rehearsal. With Wave 4 cut over,
all four tenancy/org couplings (open_path reads, org tree, manager links, tenant
allow-list) are Sentientia-owned — the remaining independence work is the
`airpay_* → sentientia_*` namespace rename (Wave 5) + epsilon/eAbyas artefact
removal (Wave 6), both their own ADRs.
