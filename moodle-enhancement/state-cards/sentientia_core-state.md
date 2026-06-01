# State Card — local_sentientia_core

| Field | Value |
|-------|-------|
| **Component** | `local_sentientia_core` |
| **Role** | The "Sentientia layer" — the product's tenancy/org abstraction seams that sit ABOVE the BizLMS (`local_airpay_core` / `local_costcenter`) heritage. The decoupling foundation for ADR-018 independence. |
| **Version** | `2026060101` / `0.4.0-alpha` (MATURITY_ALPHA) |
| **Owner** | Nitin Rajput |
| **Status** | Seams shipped + default-legacy (dormant). Wave 4 registry + Wave 3.2a org model built + locally rehearsed; live cutover gated on Nitin's deploy. |
| **Standalone?** | Yes — every delegation to `local_airpay_core` is `class_exists()`-guarded with an inline fallback, so the plugin ships for Enterprise N with no airpay_core present. |

## Purpose

One plugin, three seams — each the single sanctioned API the rest of Sentientia
calls instead of reading a BizLMS coupling directly, each behind a **default-ON
`*_legacy` flag** so production behaviour is byte-identical until an operator
deliberately (and reversibly) flips the source.

| Seam | Class | Replaces (BizLMS coupling) | ADR / Wave | Flag (default ON) |
|------|-------|----------------------------|------------|-------------------|
| Tenant identity | `tenant_identity` | `$USER->open_path` reads | ADR-019 / W2 | `tenant_identity_legacy` |
| Org hierarchy | `org` | `local_costcenter` + `open_supervisorid` | ADR-020 / W3.1+3.2a | `org_legacy` |
| Tenant registry | `tenant_registry` | `local_airpay_core\tenant::VALID_TENANTS=[1,77,177]` | ADR-021 / W4 | `tenant_registry_legacy` |

## File inventory

```
version.php                         2026060101 / 0.4.0-alpha
settings.php                        3 default-ON legacy flags + managetenants admin_externalpage
index.php                           admin signpost (library plugin, no learner UI)
db/install.xml                      customer + tenant (W4) + org_unit + org_member (W3.2a)
db/upgrade.php                      W4 savepoint 2026060100 + W3.2a savepoint 2026060101 (4 tables)
db/access.php                       local/sentientia_core:managetenants (site-admin v1)
classes/tenant_identity.php         W2 seam (root/segments/dept/path/access/sql_filter)
classes/org.php                     W3.1+3.2a seam (manager_id_of + model read API: tree walk + reverse lookups)
classes/tenant_registry.php         W4 seam (valid_roots/is_valid/assert_valid/customer_of/roots_for_customer)
classes/form/customer_form.php      W4 admin UI — add/edit customer (shortname-unique)
classes/form/tenant_form.php        W4 admin UI — add/edit tenant (rootid positive+unique)
manage_tenants.php                  W4 admin UI — list + status-toggle + add (managetenants-gated)
cli/seed_tenants.php                W4 — idempotent seed from legacy allow-list (--dry-run)
cli/parity_check_tenants.php        W4 — registry == legacy parity gate (exit-coded)
lang/en/local_sentientia_core.php   all strings (no learner-facing → en only for now)
tests/tenant_identity_test.php      W2 seam tests
tests/org_test.php                  W3.1+3.2a — 14 cases (flag + manager-id + OFF-uses-model + tree/membership/reverse)
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
  (member|manager), time*`; unique `(userid, unitid)`. The user↔unit membership.

## Activation / cutover (per ADR-021 + ADR-020 decisions 2026-06-01)

1. Deploy plugin → run `admin/cli/upgrade.php` (creates the tables).
2. `php local/sentientia_core/cli/seed_tenants.php --dry-run` then without `--dry-run`.
3. `php local/sentientia_core/cli/parity_check_tenants.php` — MUST report 100% parity.
4. Rehearse on a **clone of prod DB** first, then flip `tenant_registry_legacy` OFF
   (site-admin, reversible — flip back ON instantly on any anomaly).

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
