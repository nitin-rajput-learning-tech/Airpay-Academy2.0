# RUNBOOK — `local_airpay_*` → `local_sentientia_*` component rename (ADR-020/ADR-022 Wave 5)

**Status:** PREP ONLY — authored 2026-06-02. **No rename has been executed.** Per the hard
guardrail, **component rename execution is human-gated** (capability re-registration affects
role assignments / access). This runbook is the operational how-to + a fully worked **batch-1**
example for Nitin to review and run in a maintenance window. **Builds on ADR-022** (the design)
and mirrors `RUNBOOK-org-cutover.md`'s shape.

---

## 0. The core problem (why this is a runbook, not a `git mv`)

Moodle has **no native plugin rename**: a directory's name *is* its component. Renaming
`local/airpay_x` → `local/sentientia_x` makes Moodle see the old plugin as *missing* and the
new one as *fresh*. A naïve move therefore risks data loss + orphaned role assignments + broken
web-service tokens. So a rename is a **deliberate, two-track migration**, run **one plugin per
maintenance window**, each **rehearsed on a clone DB**, leaf-plugins first, `local_airpay_core`
**last**. (Full taxonomy + sequencing: ADR-022.)

Key timing fact this runbook relies on: **`admin/cli/upgrade.php` does NOT auto-uninstall a
missing plugin** — it flags it on the "missing plugins" admin page. So the legacy table +
config survive the upgrade, which is what lets the NEW plugin's install hook hand the data over.

---

## 1. Two-track procedure (per plugin)

### Track 1 — Source codemod (deterministic, reviewable, dry-run first)
For plugin `x` (and every cross-plugin reference to it), rewrite:
1. directory `local/x/` → `local/sentientia_<base>/` (+ lang dir/file).
2. `version.php` `$plugin->component`; any `$plugin->dependencies`.
3. namespace + `use`: `\local_airpay_<base>\…` → `\local_sentientia_<base>\…`.
4. capability strings `local/airpay_<base>:…`; `db/access.php` keys; `require_capability`/`has_capability`/lang keys.
5. table names in code + `db/install.xml`/`db/upgrade.php`: `{local_airpay_<base>…}`.
6. WS function names `local_airpay_<base>_*` in `db/services.php`.
7. `get_string`/`get_config` component args; `db/tasks.php` / `db/events.php` classnames; mustache `local_airpay_<base>/…` refs.

**Guard (must pass before staging):** `grep -rn 'airpay_<base>' moodle-enhancement/` returns
only intentional back-compat shims (else fail). Run the codemod with `--dry-run` and review the
diff before applying.

### Track 2 — Data hand-over (the crux), in the NEW plugin's `db/install.php`
Because `install.xml` creates an EMPTY new table on fresh install, the hand-over **copies**
data from the surviving legacy table, migrates config/files, then renames the legacy table to a
`_premigrate` backup (reversible; dropped after soak). The **capability re-point is the
human-gated step** (see §3).

---

## 2. Worked example — batch 1: `airpay_ratings` → `sentientia_ratings`

Chosen first because it is a near-leaf: only **2 cross-plugin code refs**
(`local_airpay_catalog\catalog_manager` ×1, `theme_airpayux\core_renderer` ×4), **1 table**,
**1 archetype-based capability**, **1 ajax WS**.

### 2.1 Scope (2026-06-02 grep)
| Kind | Item |
|------|------|
| Directory | `local/airpay_ratings/` → `local/sentientia_ratings/` |
| Table | `local_airpay_ratings` → `local_sentientia_ratings` (cols id,itemid,ratearea,userid,rating,time*; unique idx userid+itemid+ratearea) |
| Capability | `local/airpay_ratings:rate` → `local/sentientia_ratings:rate` (archetypes user/student/teacher/editingteacher/manager) |
| WS function | `local_airpay_ratings_submit_rating` → `local_sentientia_ratings_submit_rating` (ajax, loginrequired) |
| Namespace | `\local_airpay_ratings\…` (rating_manager, external\submit_rating) |
| Lang | `lang/{en,hi}/local_airpay_ratings.php` + `get_string(…, 'local_airpay_ratings')` |
| Cross-refs | `local_airpay_catalog\classes\catalog_manager.php` (×1), `theme_airpayux\classes\output\core_renderer.php` (×4) |

> **`ratearea` data is NOT renamed by this batch.** The column stores the *rated* area
> (`local_airpay_courses`, `local_airpay_classroom`, …) — those reference OTHER plugins and
> stay until each of those plugins is itself renamed. Renaming `airpay_ratings` touches the
> ratings plugin's own component, not the areas it rates.

### 2.2 The hand-over — `local/sentientia_ratings/db/install.php` (NEW file, runs post-install.xml)
```php
<?php
defined('MOODLE_INTERNAL') || die();

/**
 * One-time data hand-over from the pre-rename local_airpay_ratings.
 * Runs once, on the fresh install of local_sentientia_ratings, AFTER install.xml
 * has created the (empty) local_sentientia_ratings table.
 */
function xmldb_local_sentientia_ratings_install() {
    global $DB;
    $dbman = $DB->get_manager();
    $legacy = new xmldb_table('local_airpay_ratings');
    if (!$dbman->table_exists($legacy)) {
        return true;   // fresh Enterprise-N install, or hand-over already done.
    }

    // 1. Copy every rating row into the new (empty) table, ids preserved.
    $DB->execute("INSERT INTO {local_sentientia_ratings}
                      (id, itemid, ratearea, userid, rating, timecreated, timemodified)
                  SELECT id, itemid, ratearea, userid, rating, timecreated, timemodified
                    FROM {local_airpay_ratings}");

    // 2. Carry over plugin config + file ownership.
    $DB->execute("UPDATE {config_plugins} SET plugin = 'local_sentientia_ratings'
                   WHERE plugin = 'local_airpay_ratings'");
    $DB->execute("UPDATE {files} SET component = 'local_sentientia_ratings'
                   WHERE component = 'local_airpay_ratings'");

    // 3. Capability re-point — see §3. The NEW capability is auto-registered with its
    //    archetype defaults by Moodle on install, replicating the old defaults. Any CUSTOM
    //    role override on the OLD capability must be migrated by the gated step:
    //      UPDATE {role_capabilities} SET capability='local/sentientia_ratings:rate'
    //       WHERE capability='local/airpay_ratings:rate';
    //    (Left to the human-gated step so a human confirms there are no surprise overrides.)

    // 4. Keep the legacy table as a backup for reversibility; drop after soak (§4).
    $dbman->rename_table($legacy, 'local_airpay_ratings_premigrate');

    // 5. Drop the legacy plugin's leftover config so the "missing plugin" uninstall is a no-op.
    $DB->delete_records('config_plugins', ['plugin' => 'local_airpay_ratings']);

    return true;
}
```

### 2.3 Web-service back-compat
The old function name `local_airpay_ratings_submit_rating` disappears; the new
`local_sentientia_ratings_submit_rating` registers. This WS is `ajax=true` (the in-page rating
widget, which the codemod re-points), so **no external token risk** here. For any plugin whose
WS is consumed by the mobile app / external integrations, ship a one-release alias (ADR-022 OQ3)
before renaming.

---

## 3. The human-gated step (why execution is not automated)

Capability **re-registration** moves who-can-do-what. On install, Moodle gives the NEW
`local/sentientia_ratings:rate` its archetype defaults (user/student/teacher/editingteacher/
manager = ALLOW), which **replicate** the old behaviour for an archetype-based cap like this one.
The risk is a **custom `role_capabilities` override** on the OLD cap (a role granted/denied it
outside the archetype) — that override is orphaned unless explicitly migrated. The gated step:
1. `SELECT * FROM {role_capabilities} WHERE capability='local/airpay_ratings:rate'` — review.
2. If any non-default override exists, migrate it to `local/sentientia_ratings:rate`.
3. Confirm the new cap's effective assignments match the old, then proceed.

This is exactly why the marathon guardrail keeps rename execution human-gated.

---

## 4. Per-plugin checklist (run in a maintenance window, on a clone first)

1. **Clone** the production DB → throwaway instance.
2. Run the **codemod** (`--dry-run`, review the diff; then apply) → new dir + cross-refs rewritten.
3. Add the `db/install.php` hand-over (§2.2) + bump the new plugin's `version.php`.
4. **Guard:** `grep -rn 'airpay_ratings' moodle-enhancement/` → only intended residue.
5. `php admin/cli/upgrade.php --non-interactive` on the clone → installs `sentientia_ratings`,
   runs the hand-over (copies data, renames legacy → `_premigrate`).
6. **Verify:** row counts match (`local_sentientia_ratings` == old `local_airpay_ratings`); the
   rating widget renders + submits; the WS resolves; `local/sentientia_ratings:rate` assignments
   match. Run the plugin's PHPUnit.
7. Do the **human-gated capability review** (§3).
8. Time it; then schedule the production window. After soak, drop `local_airpay_ratings_premigrate`.

## 5. Rollback
Unlike the org cutover (an instant flag flip), a rename is **NOT instantly reversible** — code +
schema changed. Rollback options, in order: (a) within the window, restore the legacy table from
`_premigrate` + revert the code (the `_premigrate` backup is why we rename rather than drop); (b)
restore the clone/snapshot. Hence: clone-rehearse + keep the `_premigrate` table through soak.

## 6. Sequence (ADR-022)
Leaf plugins first (`airpay_ratings` ✓ worked here, then `airpay_recompletion`, …), mid-tier next,
**`local_airpay_core` LAST** (514+ refs concentrate on it; its rename is a flag day for the whole
suite). `theme_airpayux`, `block_airpay_*`, `paygw_airpay`, `quizaccess_airpay_proctoring` are
separate plugin types with their own quirks — each its own follow-up.

> **Reminder (ADR-022 §Alternatives):** weigh **brand-the-surface / defer** before committing to
> the full rename — component names are invisible to learners, so the rename is high-risk for low
> user-facing benefit. This runbook makes the *full-rename* path executable if that is the chosen
> direction.
