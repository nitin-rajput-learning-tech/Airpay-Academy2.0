# ADR-022 batch-1 rename rehearsal — `local_airpay_ratings` → `local_sentientia_ratings`

**Status:** REHEARSAL SCOPED (pre-state baseline captured 2026-06-02). **Execution deferred to a
fresh session** — the clone-DB capability/table migration is the highest-risk step (ADR-022:
"a botched capability migration silently strips access") and must not run at the tail of a long
session. This doc is the executable handoff.
**Parent:** ADR-022 (component rename). **Target:** the smallest leaf plugin, chosen as the proof.

## Why this plugin first

Smallest viable leaf: 1 table, 1 capability, 1 WS, 0 files, only 2 cross-referencing files.
If the two-track procedure (codemod + guarded `db/upgrade.php` hand-over) works here, it
generalises to the other leaves.

## Pre-state baseline (2026-06-02 local prod-import — the parity-smoke MUST match post-migration)

| Surface | Pre-state value |
|---------|-----------------|
| Installed version | `2026052001` |
| Table | `mdl_local_airpay_ratings` (0 rows locally — **on prod it has rows; row-count parity is mandatory there**) |
| `config_plugins` | 1 row (`version`) |
| Capability | `local/airpay_ratings:rate` — **7 `role_capabilities` assignments** |
| `files` (component) | 0 |
| Web service | `local_airpay_ratings_submit_rating` → `\local_airpay_ratings\external\submit_rating` |

> The **7 role-capability assignments** are the crown jewels — losing them silently revokes the
> rate permission. The parity-smoke's pass condition is: post-migration
> `local/sentientia_ratings:rate` has **exactly 7** assignments to the **same** roles/contexts.

## Track 1 — source codemod (A-list, deterministic)

Rewrite, for `airpay_ratings` and its 2 cross-refs, all 53 occurrences:

1. `git mv moodle-enhancement/local/airpay_ratings → .../local/sentientia_ratings` (13 files).
2. `version.php`: `$plugin->component = 'local_sentientia_ratings';`.
3. Namespace + `use`: `\local_airpay_ratings\…` → `\local_sentientia_ratings\…` (incl. `external\submit_rating`).
4. Lang: `lang/en/local_airpay_ratings.php` → `…/local_sentientia_ratings.php`; every `get_string(...,'local_airpay_ratings')`.
5. Capability **string**: `local/airpay_ratings:rate` → `local/sentientia_ratings:rate` in `db/access.php`, `db/services.php` (`capabilities` key), any `require_capability`/`has_capability`, lang cap key.
6. Table name: `{local_airpay_ratings}` → `{local_sentientia_ratings}` in code + `db/install.xml` + `db/upgrade.php`.
7. WS: `db/services.php` function name `local_airpay_ratings_submit_rating` → `local_sentientia_ratings_submit_rating` + classname.
8. **Cross-refs (CRITICAL — easy to miss):**
   - `moodle-enhancement/local/airpay_catalog/classes/catalog_manager.php`
   - `moodle-enhancement/theme/airpayux/classes/output/core_renderer.php`
9. **Guard:** after codemod, `grep -rn 'airpay_ratings' moodle-enhancement/` returns only intentional back-compat shims, else FAIL.

## Track 2 — DB hand-over (B-list), shipped in the renamed plugin's `db/upgrade.php`

Guarded on the OLD plugin's presence so Moodle never runs the old uninstall:

```php
// in xmldb_local_sentientia_ratings_upgrade(), earliest savepoint, guarded:
if ($oldplugin_present) {                      // detect config_plugins plugin='local_airpay_ratings'
  // 1. rename the table (preserves rows)
  $dbman->rename_table(new xmldb_table('local_airpay_ratings'), 'local_sentientia_ratings');
  // 2. hand over config rows
  $DB->set_field('config_plugins', 'plugin', 'local_sentientia_ratings', ['plugin' => 'local_airpay_ratings']);
  // 3. capability migration — define new cap (db/access.php), then re-point assignments:
  $DB->set_field('capabilities', 'name', 'local/sentientia_ratings:rate', ['name' => 'local/airpay_ratings:rate']);
  $DB->set_field('role_capabilities', 'capability', 'local/sentientia_ratings:rate', ['capability' => 'local/airpay_ratings:rate']);
  //    (+ any context overrides in the same table — covered by the same UPDATE)
  // 4. files: none for this plugin (component=0), but include for the template:
  $DB->set_field('files', 'component', 'local_sentientia_ratings', ['component' => 'local_airpay_ratings']);
  // 5. de-register the old version row LAST (so the guard above stays true until the end)
  $DB->delete_records('config_plugins', ['plugin' => 'local_airpay_ratings', 'name' => 'version']);
}
```

WS back-compat: register a one-release alias `local_airpay_ratings_submit_rating` → new class, OR
confirm via Open-Q3 that no mobile/API client calls it by name (the AMD `repository.js` calls the
WS — check whether any EXTERNAL token client does).

## Rehearsal execution (fresh session — on a CLONE DB only)

1. **Clone** the DB (or a throwaway copy of the local prod-import). Never rehearse on the served DB.
2. Snapshot pre-state (re-run `tools/_ratings_inventory.php` against the clone → save output).
3. Apply Track-1 codemod on a throwaway branch; run the grep guard.
4. Deploy renamed plugin to the clone's moodle; run `admin/cli/upgrade.php` → Track-2 hand-over fires.
5. **Parity-smoke (pass = all match pre-state):**
   - `mdl_local_sentientia_ratings` exists with the **same row count**; `mdl_local_airpay_ratings` gone.
   - `config_plugins` has `local_sentientia_ratings` version; no `local_airpay_ratings` rows.
   - `local/sentientia_ratings:rate` has **7** role assignments to the **same** roles/contexts.
   - WS `local_sentientia_ratings_submit_rating` registered; alias resolves (if shimmed).
   - Submit-a-rating smoke works end-to-end as a learner.
6. Document results; only THEN propose execution on a real maintenance window (still Nitin-gated).

## Decision still open (ADR-022 Open-Q1)

ADR-022 recommends **weighing "brand-the-surface + defer"** first — the rename is high-risk for
invisible-to-learner benefit. This rehearsal proves the *mechanism* on the safest plugin; whether
to roll it across all 31 (esp. `local_airpay_core`, the flag day) remains the strategic fork.
