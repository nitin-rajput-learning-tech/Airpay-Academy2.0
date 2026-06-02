# ADR-020 — `local_sentientia_org` + the org-hierarchy migration (Independence Wave 3)

**Status:** Wave 3.1 seam **SHIPPED** (2026-05-30, additive — `local_sentientia_core\org`); Wave 3.2+ (schema / dual-write / migration) **Proposed — needs Nitin's go/no-go** (DB migration gate) · **Owner:** Nitin Rajput
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

## Decisions (resolved 2026-06-01 — Nitin: "I agree all recommendations")
1. **Soak duration** — **ZEEA 1 week → Public 1 week → Airpay 2 weeks.** Customer-zero
   (Airpay /1) cuts over last and gets the longest watch; ZEEA /177 (smallest) first.
2. **Dual-write trigger** — **periodic reconciliation cron (~15 min) for v1.** Legacy
   stays source of truth until cutover, so eventual consistency is acceptable and the
   dual-read parity check (not write latency) gates cutover. A DB observer is a later
   optimisation only if a real-time need appears.
3. **`idnumber` / external-HRMS keys** — **support both; default HRMS-authoritative for
   customer-zero.** Airpay org changes originate in HR, so Sentientia mirrors the HRMS
   feed; the model must also let a future customer WITHOUT an HRMS make Sentientia
   authoritative. `idnumber` is the join key either way.
4. **Cutover flag ownership** — **site-admin-only** for the `org_legacy` flip (one-time,
   high-stakes). Day-to-day org admin uses the new capability; per-tenant operator
   delegation is a later capability once the pattern is proven.
5. **Decommission timeline** — **read-only shim for 1 full release** after all-tenant
   cutover, **then schedule removal** in its own ADR. Catches any missed reader during
   the soak, then completes the decouple.

> Status note (2026-06-01): per Nitin's "do not defer anything" directive, Wave 4
> (ADR-021, the lower-risk registry) is being BUILT first (additive, default-legacy,
> locally rehearsed); Wave 3.2+ (this ADR's schema / dual-write / migration) executes
> next on the same build → rehearse → gated-cutover model.

## Next
Wave 3.1 (the org seam) is **SHIPPED** — `local_sentientia_core\org::manager_id_of()`
+ `manager_id_for_current_user()` behind the default-ON `org_legacy` flag (manager-id
accessor only; reverse lookups + unit-tree walks arrive in 3.2 with the schema).
On approval: 3.2 the schema + dual-write; 3.3 the backfill + parity CLIs; 3.4 the
ZEEA cutover — each its own commit + clone-DB rehearsal. The tenant **registry** (replacing
`VALID_TENANTS`) is the separate Wave 4 (ADR-021).

## Progress log

- **2026-06-01 — Wave 3.2a SHIPPED** (additive, default-legacy, dormant).
  Schema: `local_sentientia_org_unit` (id, parentid, tenantrootid, name, idnumber,
  path, status, time*) + `local_sentientia_org_member` (id, userid, unitid, role,
  time*; unique (userid,unitid)), created by an idempotent upgrade step. Org-model
  read API added to `local_sentientia_core\org`: `model_available()`,
  `manager_via_model()`, `parent_of()`, `ancestors()`, `children()`, `units_of()`,
  `members_of()`, `is_manager()`, `direct_reports()`. The `org_legacy`-OFF path of
  `manager_id_of()` now resolves via the model, falling back to legacy
  `open_supervisorid` + `DEBUG` for any user not yet mapped — so a premature flip
  cannot break manager resolution. 14 PHPUnit tests (7 legacy/flag + 7 model). The
  tables ship empty; nothing reads them until 3.2b dual-write + 3.3 backfill seed
  them. `local_sentientia_core` → version 2026060101 / 0.4.0-alpha.
  Next: 3.2b dual-write reconciliation cron; 3.3 backfill + parity CLIs; 3.4
  ZEEA-first cutover (site-admin-gated flag flip).
- **2026-06-01 — Wave 3.2a.1 (manager-edge correction).** Building 3.2b surfaced that
  BizLMS keeps cost-center membership and the reporting line independent (two peers in one
  unit can report to different managers), so the 3.2a unit-role manager was lossy. Per the
  2026-06-01 modelling decision, the manager relationship is now the DIRECT EDGE: added
  `org_member.managerid` (mirrors open_supervisorid); `manager_via_model`, `direct_reports`,
  and `is_manager` read it; the unit `role` column is reserved for a future unit-lead
  concept. Additive column on the empty table (savepoint 2026060102), 14 PHPUnit tests
  green. version 2026060101 to 2026060102 / 0.4.1-alpha.
- **2026-06-02 — Wave 3.2b SHIPPED** (additive, default-OFF, dormant). The dual-write
  reconciliation cron that mirrors the legacy org graph into the (still-empty) `org_*`
  tables — resolving OQ#2 toward a periodic reconciler (NOT a DB observer). New:
  `org_source` interface + `org_legacy_source` (reads `user.open_path` /
  `open_supervisorid` + `local_costcenter` names) + `org_reconciler` (idempotent upsert)
  + `task\reconcile_org` (scheduled every 4h; self-gates on the new
  `org_dualwrite_enabled` flag, default OFF — so deploying changes nothing). Mapping:
  each open_path segment -> one `org_unit` (idnumber = cost-center id, tenantrootid =
  segment[0], parentid chained, path = cost-center prefix); each user -> one `org_member`
  in their LEAF unit with `managerid = open_supervisorid` (the direct edge). Tenant-scoped
  to `tenant_registry::valid_roots()`. Unit-testable on a vanilla DB via an injectable
  synthetic source: 8 PHPUnit tests (tree build, manager edge, idempotency, manager-change
  update, tenant scope, unusable-path skip, name fallback, flag default).
  `local_sentientia_core` -> version 2026060103 / 0.5.0-alpha.
  Next: 3.3 backfill + parity CLIs (`cli/backfill_org.php`, `cli/parity_check_org.php`);
  3.4 ZEEA-first cutover (site-admin-gated flag flip; 100% parity required first).
- **2026-06-02 — Wave 3.3 SHIPPED** (additive, default-OFF, dormant). The backfill +
  parity CLIs that make the org cutover gateable. New: `cli/backfill_org.php`
  (`--dry-run` default, `--execute`, `--tenant=`) runs the reconciler once over all
  users; `cli/parity_check_org.php` (exit-coded) + the extracted `org_parity`
  comparator — for every in-scope user it asserts `org::manager_via_model` == legacy
  `open_supervisorid` AND the model unit idnumber == the open_path leaf. **Rehearsed on
  the local prod-data DB (2,883 users):** backfill `--execute` → 160 units + 2,883
  members; parity **100% (0 manager / 0 membership mismatches)**; re-run idempotent
  (0/0); the `reconcile_org` task with `org_dualwrite_enabled` ON also idempotent
  (0.48s / 3046 queries), flag restored OFF. 6 PHPUnit tests (`org_parity_test`).
  version 2026060103 → 2026060104 / 0.6.0-alpha.
  Next: 3.4 cutover PREP — migrate remaining direct `open_supervisorid` readers onto the
  seam + author `docs/RUNBOOK-org-cutover.md`. The flip itself stays human-gated.
- **2026-06-02 — Wave 3.4 cutover PREP (runbook authored; NO flip).**
  `docs/RUNBOOK-org-cutover.md` — the ZEEA-first checklist (clone-DB rehearsal → backfill →
  100% parity → enable dual-write soak → flip `org_legacy` OFF → soak → next tenant →
  decommission ADR) with instant `org_legacy`-ON rollback. The reader-migration grep
  (155 hits / 53 files) found the surface is dominated by REVERSE lookups
  (`team_manager::get_team`/`can_manage`, `role_detector`) and aggregate
  `GROUP BY open_supervisorid` JOINs (`rule_engine` digests) — neither a clean drop-in for
  the current `org::` API. Surfaced as gated decisions for Nitin (runbook §3): (1) `org_legacy`
  is GLOBAL — a per-tenant override is needed for true ZEEA-first; (2) reverse readers need a
  legacy fallback on `direct_reports`/`is_manager` (or post-cutover migration) — but the
  `open_supervisorid` column stays live through cutover, so they keep working meanwhile;
  (3) aggregate readers need a new team-aggregate seam method. Forward readers
  (`manager_id_of`) are safe drop-ins, recommended as batch 1. No production reader migrated
  in this autonomous pass (behaviour-neutral until flip + user-facing/security-sensitive →
  reviewed batches per the runbook). Docs-only; no version change.
- **2026-06-02 — Wave 3.4 reverse seam SHIPPED** (additive contract extension; Nitin's
  "build reverse seam too" decision). `org::is_manager` / `direct_reports` gained a
  FLAG-AWARE legacy fallback (org_legacy ON → `open_supervisorid` reverse lookup, guarded on
  the column's existence; OFF → model `managerid` edge with a legacy fallback for the
  pre-backfill gap), plus a new `reports_by_manager()` aggregate primitive (manager→reports
  map) for digest-style group-by-manager readers — symmetric with `manager_id_of()`, so all
  three are correct drop-ins the cutover auto-switches. The unit-tree methods +
  `manager_via_model` stay model-only (no legacy equivalent). Validated on prod data
  (manager 772: is_manager=true, direct_reports==raw open_supervisorid count=2). 3 new/updated
  PHPUnit; 30/30 org green (existing reverse tests pinned to the model path with org_legacy
  OFF). version 2026060104 → 2026060105 / 0.6.1-alpha. Next: migrate the raw reverse readers
  (team_manager::get_team/can_manage, rule_engine digests) onto the seam.
