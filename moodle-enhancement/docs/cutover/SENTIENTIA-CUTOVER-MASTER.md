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

**Config check: `logstore_standard | logguests` must stay `1` (decision A5 audit trail).**
`local/sentientia_cart/callback.php` runs with no logged-in user and calls `cart_manager::mark_paid()`,
which triggers the `order_paid` audit event. logstore_standard drops events from a not-logged-in or
guest user when `logguests` is 0 (`is_event_ignored()`), and the audit log reads only that store, so
paid orders would never reach it. Read-only check, on UAT and again on live, as the web server user
(`<dirroot>` is the folder that holds `admin/` and `public/`). `cfg.php` reads through `get_config()`,
so it also shows a value forced in `config.php`, which a database query would miss:
```
php <dirroot>/admin/cli/cfg.php --component=logstore_standard --name=logguests
php <dirroot>/admin/cli/cfg.php --component=tool_log --name=enabled_stores
```
Pass: the first prints `1`, or exits 3 with "No such configuration variable found." (nothing stored;
the store then uses 1), and the second prints a list that includes `logstore_standard`. Stop and fix
the setting before any payment is taken if `logguests` is `0`, or if `enabled_stores` does not list
`logstore_standard` or exits 3 (no store loaded, no audit rows). Not yet checked on UAT or live
(2026-11-13).

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

## BLOCKING — Erasure gate: before any DPO approves a DELETE request on production  (ADR-035)

Stock Moodle (5.1 and 5.3) reports a data-deletion request as **Deleted** while data stays behind:
the approval stops at the first duplicate context (core bug), and course completions without touched
criteria are never found (core gap). The A4 rehearsal on the local copy left 15,129 log and stats rows,
1,718 course completions and 1 activity-completion + 1 activity-view row of 286 subjects after
"Deleted" (`docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md`). The fix is a supplementary
provider in `local_sentientia_privacy` (2026111300), deployed with Gate A behind two flags, both OFF:
`sentientia.privacy.erasure_completeness.enabled` (truncation) and `sentientia.privacy.completion_gap.enabled`
(course completions). The flags are read site-wide only; tenant or customer overrides have no effect.

**No DPO approves a DELETE request on production until all three hold:**
1. **The DPO retention decision is recorded** (ADR-035 "Retention"): either (a) the data registry is
   configured (system purpose + category; protected purposes for compliance-training courses), or (b) a
   DPO-signed acceptance that auth logs, audit-trail rows and learning records of erased subjects are
   deleted, with the privacy notice updated. Legal confirms the log-retention duties (CERT-In 2022,
   180 days; DPDP Rules 2025) first.
2. **Both flags are ON site-wide** (Nitin's call), set as the web server user:
   ```
   php set_flag.php --config=<dirroot>/config.php --flag=sentientia.privacy.erasure_completeness.enabled --value=on --reason="<who, date, ADR-035>"
   php set_flag.php --config=<dirroot>/config.php --flag=sentientia.privacy.completion_gap.enabled --value=on --reason="<who, date, ADR-035>"
   ```
   (`tools/uat/set_flag.php`, copied to the server; it sets the site-wide value and prints it before and after.)
3. **The post-erasure residue check is green on UAT:** after one DELETE request for a UAT test account
   with data in several courses and activities, no row of the subject remains in a context the approval
   approved for deletion: no `logstore_standard_log` row with the subject as `userid`, and no
   `course_completions`, `course_modules_completion`, `course_modules_viewed` or `stats_user_*` row.
   This is the `failure` bucket of `tools/privacy-erasure-rehearsal/a4_verify.php`. Under retention
   option (a) the contexts the data registry keeps (the system and category contexts, so system-level
   login logs; protected, unexpired courses) are not approved for deletion and do not count; under
   option (b) every existing context of the subject is approved. Rows where the subject is only
   `relateduserid`/`realuserid` stay by core design and do not count.

**Requests completed while the flags were OFF keep their residue.** Stock never reprocesses a request
at status 10 (Deleted). Every such request needs the **residue sweep** (DPO decision + Nitin's call),
including any BizLMS-era requests completed on live. The production backup on the local copy had none
(all 515 were at status 2 before A4); check again on the Stage B copy.

**Not built yet (2026-11-13; still so 2026-11-17):** the residue sweep CLI for UAT and live, and the UAT
residue check. A local rehearsal driver exists since 2026-11-17 (`tools/privacy-erasure-rehearsal/a4_sweep.php`,
local XAMPP only, dry runs only). The DPO decision is not taken. The flags are OFF everywhere.
**Rollback:** the same commands with `--value=off` (seconds). The deletions already made are permanent.

---

## Recommended order & dependencies

1. **Gate A** (deploy) — prerequisite for everything; dormant.
2. **Gate B** (registry) — independent, low-risk; good first live flip to build confidence.
3. **Gate C** (org) — independent of B; per-tenant, the highest learner-visible impact.
4. **Gate D** (rename) — last; orthogonal to A–C but the heaviest. Can be deferred indefinitely
   (the platform runs fine on the `airpay_*` component names — the rename is cosmetic/product-naming).
5. **Erasure gate** (ADR-035) — after Gate A, independent of B–D. **BLOCKING** for one thing: no DPO
   approves a DELETE request on production until it is green.

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
| Erasure (ADR-035) | `set_flag.php … --value=off` for both flags (deletions already made stay made) | seconds |

---

## What is NOT in this guide (deliberately)

- **`enrol_sentientiasub` (subscriptions/payments)** — design-only (ADR-023), no charging code,
  gated separately on a product decision + Airpay sandbox. Do **not** enable as part of the
  independence cutover.
- Any live payment configuration — out of scope here.
