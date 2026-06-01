# State Card — local_sentientia_core

| Field | Value |
|-------|-------|
| **Component** | `local_sentientia_core` |
| **Role** | The "Sentientia layer" — the product's tenancy/org abstraction seams that sit ABOVE the BizLMS (`local_airpay_core` / `local_costcenter`) heritage. The decoupling foundation for ADR-018 independence. |
| **Version** | `2026060104` / `0.6.0-alpha` (MATURITY_ALPHA) |
| **Owner** | Nitin Rajput |
| **Status** | Seams shipped + default-legacy (dormant). Wave 4 registry + Wave 3.2a org model + Wave 3.2b dual-write reconciler + Wave 3.3 backfill/parity CLIs built + locally rehearsed (2,883 users, **100% parity**); live cutover + dual-write enable gated on Nitin's deploy. |
| **Standalone?** | Yes — every delegation to `local_airpay_core` is `class_exists()`-guarded with an inline fallback, so the plugin ships for Enterprise N with no airpay_core present. |

## Purpose

One plugin, three seams — each the single sanctioned API the rest of Sentientia
calls instead of reading a BizLMS coupling directly, each behind a **default-ON
`*_legacy` flag** so production behaviour is byte-identical until an operator
deliberately (and reversibly) flips the source.

| Seam | Class | Replaces (BizLMS coupling) | ADR / Wave | Flag (default ON) |
|------|-------|----------------------------|------------|-------------------|
| Tenant identity | `tenant_identity` | `$USER->open_path` reads | ADR-019 / W2 | `tenant_identity_legacy` |
| Org hierarchy | `org` | `local_costcenter` + `open_supervisorid` | ADR-020 / W3.1+3.2a/3.2b/3.3 | `org_legacy` (+ `org_dualwrite_enabled`, default **OFF**) |
| Tenant registry | `tenant_registry` | `local_airpay_core\tenant::VALID_TENANTS=[1,77,177]` | ADR-021 / W4 | `tenant_registry_legacy` |

## File inventory

```
version.php                         2026060104 / 0.6.0-alpha
settings.php                        3 default-ON legacy flags + 1 default-OFF org_dualwrite_enabled + managetenants admin_externalpage
index.php                           admin signpost (library plugin, no learner UI)
db/install.xml                      customer + tenant (W4) + org_unit + org_member (W3.2a)
db/upgrade.php                      W4 savepoint 2026060100 + W3.2a savepoints 2026060101/2026060102 (managerid)
db/access.php                       local/sentientia_core:managetenants (site-admin v1)
db/tasks.php                        W3.2b — reconcile_org scheduled task (every 4h; self-gates on org_dualwrite_enabled)
classes/tenant_identity.php         W2 seam (root/segments/dept/path/access/sql_filter)
classes/org.php                     W3.1+3.2a seam (manager_id_of + model read API) + use_dualwrite() (W3.2b)
classes/org_source.php              W3.2b — injectable source interface (users() + unit_name())
classes/org_legacy_source.php       W3.2b — BizLMS-backed source (open_path/open_supervisorid + local_costcenter names)
classes/org_reconciler.php          W3.2b — idempotent legacy→model upsert engine
classes/org_parity.php              W3.3 — model-vs-legacy parity comparator (the cutover gate)
classes/task/reconcile_org.php      W3.2b — scheduled task; no-ops unless org_dualwrite_enabled is ON
classes/tenant_registry.php         W4 seam (valid_roots/is_valid/assert_valid/customer_of/roots_for_customer)
classes/form/customer_form.php      W4 admin UI — add/edit customer (shortname-unique)
classes/form/tenant_form.php        W4 admin UI — add/edit tenant (rootid positive+unique)
manage_tenants.php                  W4 admin UI — list + status-toggle + add (managetenants-gated)
cli/seed_tenants.php                W4 — idempotent seed from legacy allow-list (--dry-run)
cli/parity_check_tenants.php        W4 — registry == legacy parity gate (exit-coded)
cli/backfill_org.php                W3.3 — run reconciler over all users (--dry-run default, --execute, --tenant=)
cli/parity_check_org.php            W3.3 — org model == legacy parity gate (exit-coded; thin wrapper over org_parity)
lang/en/local_sentientia_core.php   all strings (no learner-facing → en only for now)
tests/tenant_identity_test.php      W2 seam tests
tests/org_test.php                  W3.1+3.2a — 14 cases (flag + manager-id + OFF-uses-model + tree/membership/reverse)
tests/org_reconciler_test.php       W3.2b — 8 cases (tree build, manager edge, idempotency, manager-change, tenant scope, bad-path skip, name fallback, flag default)
tests/org_parity_test.php           W3.3 — 6 cases (full parity, manager drift, membership drift, unbackfilled, scope skip, empty-model)
tests/tenant_registry_test.php      W4 — 11 cases (legacy + OFF-reads-table + parity + legacy-ignores-table)
```

## DB (additive — every table carries timecreated/timemodified)

Wave 4:
- **`local_sentientia_customer`** — `id, name, shortname (uq), status, time*`. Top-level
  customer. Airpay = customer-zero. One customer owns ≥1 tenant root.
- **`local_sentientia_tenant`** — `id, rootid (uq), customerid (fk→customer), name,
  idnumber (nullable HRMS key), status, time*`. Only `status='active'` rows are in the
  allow-list. `rootid` = today's BizLMS cost-center root (1/77/177).

Wave 3.2a (ship empty; seeded by 3.2b dual-write + 3.3 backfill):
- **`local_sentientia_org_unit`** — `id, parentid, tenantrootid, name, idnumber (HRMS
  key), path (materialised), status, time*`. The org-tree node.
- **`local_sentientia_org_member`** — `id, userid, unitid (fk→org_unit), role
  (member|manager — reserved for unit-lead), managerid (the direct-manager edge,
  mirrors open_supervisorid; 0 = none), time*`; unique `(userid, unitid)`. The
  user↔unit membership. manager_via_model/direct_reports/is_manager read managerid.

## Dual-write + backfill + parity (W3.2b/3.3 — ADR-020, default-OFF)

Mirror the legacy org graph into the (else-empty) `org_*` tables so the model stays
warm ahead of a gated cutover. Resolves ADR-020 OQ#2 toward a **periodic reconciler**
(not a DB observer). **Nothing runs automatically until `org_dualwrite_enabled` is ON.**

- `org_source` (interface) → `org_legacy_source` (live BizLMS) or a synthetic test source.
- `org_reconciler::reconcile(?array $allowedroots)` — idempotent upsert; re-run = no-op.
  Mapping: each `open_path` segment → one `org_unit` (idnumber = cost-center id,
  tenantrootid = segment[0], parent chained, path = cost-center prefix); each user →
  one `org_member` in the leaf unit with `managerid = open_supervisorid`.
- `task\reconcile_org` runs every 4h, tenant-scoped to `tenant_registry::valid_roots()`,
  no-ops while the flag is OFF.
- **W3.3 CLIs:** `cli/backfill_org.php` (one-shot reconcile; `--dry-run` default via a
  rolled-back transaction, `--execute`, `--tenant=`) and `cli/parity_check_org.php` —
  a thin wrapper over `org_parity`, which for each in-scope user asserts
  `org::manager_via_model` == legacy `open_supervisorid` AND the model unit idnumber ==
  the open_path leaf, exiting non-zero on any mismatch. **100% parity is the cutover gate.**

## Activation / cutover (per ADR-021 + ADR-020 decisions 2026-06-01)

1. Deploy plugin → run `admin/cli/upgrade.php` (creates the tables).
2. `php local/sentientia_core/cli/seed_tenants.php --dry-run` then without `--dry-run`.
3. `php local/sentientia_core/cli/parity_check_tenants.php` — MUST report 100% parity.
4. (Org, W3.3+) enable `org_dualwrite_enabled` on a **clone of prod DB**, run
   `cli/backfill_org.php --execute` (or let `reconcile_org` populate the model), then
   `cli/parity_check_org.php` — MUST report 100% parity — then flip `org_legacy` OFF
   ZEEA-first (reversible — flip back ON instantly on any anomaly).

**Everything is dormant until its flag flips.** Default state changes nothing for
customer-zero.

## Dependencies

- **Soft** on `local_airpay_core` (`tenant` class) — delegated to when present, inline
  fallback otherwise. No hard `$plugin->dependencies`.
- Consumed by: the Wave-2-migrated `open_path` callers (see ADR-019 caller list) +
  `local_airpay_core\tenant::assert_valid()` (delegates to `tenant_registry` as of W4).

## ADR refs
- ADR-018 (independence roadmap), ADR-019 (W2 tenant_identity), ADR-020 (W3 org),
  ADR-021 (W4 tenant registry). DEPRECATION-SCHEDULE rows 7 (open_path), 5/8 (org),
  9 (VALID_TENANTS).

## Changelog
- **2026-06-02 (W3.3, ADR-020):** backfill + parity CLIs — `cli/backfill_org.php`
  (--dry-run default / --execute / --tenant=) + `cli/parity_check_org.php` + the
  extracted `org_parity` comparator (manager-edge + membership parity, exit-coded).
  Rehearsed on the local prod-data DB (2,883 users): backfill → 160 units + 2,883
  members; parity 100% (0/0); re-run + task idempotent; flag restored OFF. 6 PHPUnit
  tests (org_parity_test). v2026060103→2026060104 / 0.6.0-alpha.
- **2026-06-02 (W3.2b, ADR-020):** dual-write reconciler (default-OFF) — `org_source`
  interface + `org_legacy_source` + idempotent `org_reconciler` + `task\reconcile_org`
  (`db/tasks.php`, every 4h, self-gates on new `org_dualwrite_enabled` flag). Mirrors
  the legacy graph into `org_*` (open_path segment→unit, user→leaf-member with
  managerid=open_supervisorid); tenant-scoped to the registry. 8 PHPUnit tests
  (synthetic source — vanilla-DB testable). Deploy changes nothing (task no-ops).
  v2026060102→2026060103 / 0.5.0-alpha.
- **2026-06-01 (W3.2a.1, ADR-020):** manager relationship is now the DIRECT EDGE —
  added `org_member.managerid` (mirrors open_supervisorid); `manager_via_model` /
  `direct_reports` / `is_manager` rewired to it (the unit `role` is reserved for a
  future 'unit lead'). Per the 2026-06-01 modelling decision: in BizLMS, cost-center
  membership and the reporting line are independent. 14 PHPUnit tests still green.
  v2026060101→2026060102 / 0.4.1-alpha.
- **2026-06-01 (W3.2a, ADR-020):** additive org model — `local_sentientia_org_unit` +
  `local_sentientia_org_member` tables + the `org` read API (manager_via_model, tree walk,
  membership, reverse lookups), all `model_available()`-guarded; `manager_id_of` OFF-path
  resolves via the model with legacy+DEBUG fallback. 14 PHPUnit tests. Rehearsed on local
  XAMPP (test-DB init + main-DB upgrade green). v2026060100→2026060101.
- **2026-06-01 (W4, ADR-021):** tenant_registry seam + 2 tables + seed/parity CLIs +
  `:managetenants` admin UI; `airpay_core\tenant::assert_valid` delegates to the registry.
  Locally rehearsed via PHPUnit (isolated test DB). v2026053002→2026060100.
- **2026-05-30 (W3.1, ADR-020):** `org` seam (manager-id accessor) behind `org_legacy`.
- **2026-05-30 (W2, ADR-019):** `tenant_identity` seam + caller-migration surface behind
  `tenant_identity_legacy`; ~22 `open_path` call sites migrated across the plugin suite.
