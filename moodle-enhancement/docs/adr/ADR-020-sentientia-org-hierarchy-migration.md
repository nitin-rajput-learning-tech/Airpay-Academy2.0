# ADR-020 — `local_sentientia_org` + the org-hierarchy migration (Independence Wave 3)

**Status:** Proposed — **needs Nitin's go/no-go** (DB migration gate) · **Owner:** Nitin Rajput
**Parent:** ADR-018 (independence roadmap) Wave 3 · **Builds on:** ADR-019 (Wave 2, `tenant_identity` seam).
**Pairs with:** `docs/DEPRECATION-SCHEDULE.md` (rows 5, 8), `docs/BIZLMS-MIGRATION-NARRATIVE.md`.

> This is a **design/decision record only**. No schema is created and no data is
> migrated by writing this ADR. Execution is explicitly gated on Nitin's approval
> + a clone-DB rehearsal, per ADR-018's governing rule.

## Context

After Wave 2 gave us the `tenant_identity` seam for `open_path`, the next BizLMS
coupling on the critical path is the **org hierarchy**:

- **`local_costcenter`** — the BizLMS company/department tree. Tenants (Airpay /1,
  Public /77, ZEEA /177) and every sub-unit are nodes here; `open_path` strings
  (`/1/2/3`) are paths *through* this tree.
- **`open_supervisorid`** — the manager link on a user, used by manager dashboards,
  audience targeting, and escalation crons.

Per the ADR-018 discovery this coupling is **SOFT**: there is already an
`org_manager` fallback seam in places, so it is dual-targetable rather than
hard-wired everywhere. That makes a *seam-then-migrate* approach viable, exactly
like Wave 2 — but unlike Wave 2 it touches **real data** (the org tree + manager
links for 2,800+ users across 3 tenants), so it carries migration risk Wave 2 did not.

## Decision (proposed)

Introduce **`local_sentientia_org`** as the Sentientia-owned org model, and migrate
onto it in the same additive, reversible, default-legacy manner as Wave 2.

### 1. The seam first (additive, no data change)
A `local_sentientia_core\org` (or `local_sentientia_org\hierarchy`) service:
- `parent_of($unitid)`, `ancestors($unitid)`, `children($unitid)`, `manager_of($userid)`.
- Behind a **default-ON** `org_legacy` flag, every method delegates to the existing
  `local_costcenter` tables + `open_supervisorid` — byte-identical behaviour.
- Ships + is adopted by callers (incrementally) **before** any data moves. At this
  point nothing has migrated; we've only inserted the abstraction.

### 2. The schema (new tables — the gated step)
- `local_sentientia_org_unit` — `id, parentid, tenantrootid, name, path, idnumber,
  timecreated, timemodified` (+ index on `parentid`, `tenantrootid`, `path`).
- `local_sentientia_org_member` — `id, userid, unitid, role (member|manager),
  timecreated` (+ unique index `userid,unitid`).
- Both carry `tenantrootid` for the multi-tenant scoping rule
  (`.claude/rules/database.md`).

### 3. The migration (dual-write → backfill → dual-read → cutover)
1. **Dual-write:** when `local_costcenter` rows change, mirror into
   `local_sentientia_org_*` (observer). Legacy stays source of truth.
2. **Backfill CLI** (`admin/cli/`): one-shot copy of the existing tree + manager
   links into the new tables. Idempotent, resumable, `--dry-run` first.
3. **Dual-read parity check:** a CLI that walks every unit/user and asserts the org
   service returns identical results from legacy vs new. Must be 100% before cutover.
4. **Cutover, per tenant, ZEEA-first:** flip `org_legacy` OFF for tenant 177 (ZEEA —
   smallest, lowest-risk), soak, then 77, then 1 (Airpay, customer-zero, last).
5. **Decommission:** only after all tenants are stable on the new model for a soak
   period does `local_costcenter` become removable (its own later ADR).

### 4. Rehearsal + rollback (mandatory before any prod step)
- Every step is rehearsed on a **clone of the production DB** first (MySQL 8 RDS
  snapshot → throwaway instance), timed + verified, before touching live.
- Rollback at each stage = flip `org_legacy` back ON (instant; legacy is untouched
  until decommission). The backfill is additive (new tables), so rollback never
  loses data.

## Why this shape (not a big-bang migration)
- **Customer-zero never regresses:** Airpay (/1) cuts over LAST, after ZEEA + Public
  prove the path; the flag flips back instantly on any anomaly.
- **Reversible by construction:** legacy remains the source of truth through dual-write
  + dual-read; the new tables are additive until an explicit, soak-gated decommission.
- **Verifiable:** the dual-read parity check is an objective go/no-go gate — cutover
  only when legacy and new agree for 100% of units + users.

## Consequences
- **Positive:** the org model becomes Sentientia-owned + testable; `open_supervisorid`
  + `local_costcenter` reads route through one service; ZEEA-first proves the pattern
  on a small blast radius before Airpay.
- **Negative / accepted:** dual-write adds a transient write-amplification + a parity
  surface to maintain until cutover; the full migration is multi-week with soak periods.
- **Risk:** data migration on a live multi-tenant tree is the highest-risk wave so far
  — hence clone-DB rehearsal + per-tenant cutover + instant flag rollback are
  non-negotiable.

## Open questions for Nitin (resolve before execution)
1. **Soak duration** per tenant before advancing (suggest: ZEEA 1wk → Public 1wk → Airpay)?
2. **Dual-write trigger** — DB observer on `local_costcenter` writes, or a periodic
   reconciliation cron (simpler, eventually-consistent)? Trade-off: latency vs complexity.
3. **`idnumber` / external-HRMS keys** — does the org tree need to round-trip to the
   HRMS sync (`local_airpay_users`), or is Sentientia the new source of truth post-cutover?
4. **Who owns the cutover flag** — site admin only, or a per-tenant operator capability?
5. **Decommission timeline** for `local_costcenter` — keep as a read-only shim
   indefinitely (lower risk) or schedule removal (cleaner)?

## Next
On approval: Wave 3.1 ships the seam + service (additive, like ADR-019's scaffold);
3.2 the schema + dual-write; 3.3 the backfill + parity CLIs; 3.4 the ZEEA cutover —
each its own commit + clone-DB rehearsal. The tenant **registry** (replacing
`VALID_TENANTS`) is the separate Wave 4 (ADR-021).
