# Sentientia LMS — Master Cutover Guide (turnkey)

**Audience:** Nitin (operator). **Purpose:** the single entry point for taking the
Sentientia independence work LIVE. Everything on the production branch is **additive +
dormant** (default-legacy / default-OFF); this guide is the ordered, command-by-command
sequence — with rollback at every step — to flip it on, one reversible gate at a time.

> **Nothing here has run.** Each gate is a deliberate, operator-driven, reversible step.
> Deploying the branch (Gate A) changes **nothing** for live users until you flip a flag.

**Current state (2026-06-03):** production branch tip `1a9096b86`. All seam flags default
to legacy/OFF (verified). Live site (airpay.academy) is **not** auto-deployed from the branch.

---

## Golden rules (every gate)

1. **Rehearse on a clone of the prod DB first.** Never first-run a flip on live.
2. **One change at a time.** Flip → soak → verify → next. Never batch gates.
3. **Parity gate is mandatory.** Where a parity CLI exists, it MUST report 100% before the flip.
4. **Rollback is always a flag flip back** (seconds), never a redeploy — unless noted.
5. Put the site in **maintenance mode** for Gate A (deploy) and Gate D (rename apply); Gates
   B/C flag-flips are online-safe but do them in a low-traffic window.

Flag flips use Moodle's config CLI (run with cwd = `public/`):
```
php admin/cli/cfg.php --component=local_sentientia_core --name=<FLAG> --set=<0|1>
```
or the admin UI: *Site administration → Plugins → Local plugins → Sentientia core*.

---

## Gate A — Deploy the code  (dormant after deploy)

The branch is additive; deploying it leaves every seam on its legacy path.

```
# 1. Maintenance mode ON
php admin/cli/maintenance.php --enable
# 2. Copy the updated files to the server web root (per DEPLOYMENT-RUNBOOK.md)
#    (file-copy of changed plugins/theme into .../moodle5/public/)
# 3. Run the upgrade (creates the dormant org tables + registry tables; no data touched)
php admin/cli/upgrade.php --non-interactive
# 4. Purge caches
php admin/cli/purge_caches.php
# 5. Maintenance mode OFF
php admin/cli/maintenance.php --disable
```
**Verify:** site loads; login + dashboard normal; `enrol_sentientiasub` shows **disabled**
under *Manage enrol plugins* (leave it disabled). **Rollback:** redeploy the previous release
(the upgrade only ADDED empty tables + dormant settings — safe to leave even on rollback).
Detailed deploy steps: `moodle-enhancement/DEPLOYMENT-RUNBOOK.md`.

---

## Gate B — Tenant registry cutover  (`tenant_registry_legacy`)

Replaces the hardcoded `VALID_TENANTS=[1,77,177]` with the DB registry.

```
# Rehearse on a prod-DB clone first, then on live:
php local/sentientia_core/cli/seed_tenants.php --dry-run     # preview
php local/sentientia_core/cli/seed_tenants.php               # seed the 3 roots under Airpay
php local/sentientia_core/cli/parity_check_tenants.php       # MUST exit 0 / 100% parity
# Flip:
php admin/cli/cfg.php --component=local_sentientia_core --name=tenant_registry_legacy --set=0
php admin/cli/purge_caches.php
```
**Verify:** all 3 tenants resolve; admin → Sentientia core → Manage tenants lists them.
**Rollback (instant):** `--name=tenant_registry_legacy --set=1` + purge.

---

## Gate C — Org model cutover  (`org_legacy`) — per tenant, ZEEA → Public → Airpay

Switches manager/org resolution from BizLMS `open_supervisorid` to the Sentientia org model.
**Full detail + soak windows: `moodle-enhancement/docs/RUNBOOK-org-cutover.md` (authoritative).**

```
# 1. Start the dual-write so the model stays warm (rehearse on clone first):
php admin/cli/cfg.php --component=local_sentientia_core --name=org_dualwrite_enabled --set=1
# 2. Backfill the org model from the legacy graph:
php local/sentientia_core/cli/backfill_org.php --dry-run
php local/sentientia_core/cli/backfill_org.php --execute
# 3. Parity gate — MUST be 100% per tenant before any flip:
php local/sentientia_core/cli/parity_check_org.php
# 4. Flip org_legacy OFF — ONE tenant at a time, with a soak between each:
#    ZEEA (177) first → soak per runbook → Public (77) → soak → Airpay (1).
#    (org_legacy is global today; per-tenant staging is operational — verify each tenant's
#     managers/reports after the flip before proceeding. See the runbook.)
php admin/cli/cfg.php --component=local_sentientia_core --name=org_legacy --set=0
php admin/cli/purge_caches.php
```
**Verify (each tenant):** manager dashboards show correct direct reports; spot-check
`org::manager_id_of` == legacy `open_supervisorid` for sampled users (parity CLI already
asserts this). **Rollback (instant):** `--name=org_legacy --set=1` + purge — the legacy path
is untouched and the dual-write keeps the model current for a retry.
**Decommission:** keep the legacy shim 1 full release after all-tenant cutover, then remove
in its own ADR (per ADR-020).

---

## Gate D — Component rename `--apply`  (`local_airpay_* → local_sentientia_*`)

The largest/riskiest step (capability re-registration + DB table renames). Rehearsed 9/9 on
`airpay_ratings` (batch-1) on a branch. **Full detail: `moodle-enhancement/docs/RUNBOOK-component-rename.md`
+ `docs/adr/ADR-022-component-rename.md` (authoritative).** Per batch, never all at once:

```
# Per plugin batch (start with airpay_ratings → sentientia_ratings):
# 1. Rehearse on a prod-DB clone:
php tools/rename/codemod.php --plugin=airpay_ratings --to=sentientia_ratings   # dry-run default
php tools/rename/codemod.php --plugin=airpay_ratings --to=sentientia_ratings --apply
#    Track-2 DB hand-over (rename_table + config + capability re-point) runs in the renamed
#    plugin's db/upgrade.php on the next admin/cli/upgrade.php.
# 2. On the clone: upgrade + parity-smoke (data + caps intact). Only if green, repeat on live
#    inside maintenance mode, then deploy + upgrade + purge.
```
**Rollback:** restore the pre-batch DB + code (this gate is NOT a simple flag flip — take a DB
snapshot before each batch). Do **one** plugin batch, verify in production for a soak period,
then the next.

---

## Recommended order & dependencies

1. **Gate A** (deploy) — prerequisite for everything; dormant.
2. **Gate B** (registry) — independent, low-risk; good first live flip to build confidence.
3. **Gate C** (org) — independent of B; per-tenant, the highest learner-visible impact.
4. **Gate D** (rename) — last; orthogonal to A–C but the heaviest. Can be deferred indefinitely
   (the platform runs fine on the `airpay_*` component names — the rename is cosmetic/product-naming).

Each gate is independent and individually reversible. There is **no deadline pressure**: the
branch is deploy-safe today, and you can flip gates weeks apart.

---

## Rollback summary

| Gate | Rollback | Speed |
|------|----------|-------|
| A — deploy | redeploy previous release (added tables are harmless to leave) | minutes |
| B — registry | `cfg.php … tenant_registry_legacy --set=1` + purge | seconds |
| C — org | `cfg.php … org_legacy --set=1` + purge | seconds |
| D — rename | restore pre-batch DB snapshot + code | minutes |

---

## What is NOT in this guide (deliberately)

- **`enrol_sentientiasub` (subscriptions/payments)** — design-only (ADR-023), no charging code,
  gated separately on a product decision + Airpay sandbox. Do **not** enable as part of the
  independence cutover.
- Any live payment configuration — out of scope here.
