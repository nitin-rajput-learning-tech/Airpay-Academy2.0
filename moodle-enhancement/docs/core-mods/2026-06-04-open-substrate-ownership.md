# Core-mod record: first-party `open_*` tenant substrate

- **Date:** 2026-06-04
- **Change:** `local_sentientia_core` now adds the BizLMS-compatible `open_*`
  columns to core tables `mdl_user` (37 cols) and `mdl_course` (18 cols)
  automatically on plugin install/upgrade.
- **Where:**
  - `local/sentientia_core/classes/substrate.php` — single source of truth
    (column definitions + `ensure_table()` / `ensure_all()`).
  - `local/sentientia_core/db/upgrade.php` — savepoint `2026060400` calls
    `\local_sentientia_core\substrate::ensure_all(false)`.
  - `local/sentientia_core/cli/bootstrap_substrate.php` — explicit/manual re-run
    + `--dry-run` preview (same class).
- **Tag at site:** raw `ALTER TABLE {user|course} ADD COLUMN open_* ...` via
  `$DB->change_database_structure()` (intentional: reproduces the external
  eAbyas column types verbatim; the xmldb generator would impose Moodle's own
  types).

## Justification (ADR-024 Wave 2)

The multi-tenant substrate Sentientia reads at runtime (`$USER->open_path` and
siblings) was historically provided by the external eAbyas/BizLMS plugin suite,
which is NOT in this repository. Without those columns a from-scratch Sentientia
install on vanilla Moodle cannot resolve tenants. Owning the column schema
first-party is the ADR-024 Wave 2 deliverable ("recreate every dependency as
Sentientia's own").

## Upgrade-safety / before-after

- **Additive + idempotent.** `ensure_table()` adds ONLY columns absent from
  `$DB->get_columns()`; it never drops or alters an existing column.
- **No-op on existing deployments.** A database that already carries the
  columns (e.g. an Airpay production DB inherited from the eAbyas distribution)
  sees zero changes — verified locally (dry-run: 0 missing).
- **Reversible.** The columns are additive; removing them is a separate,
  deliberate down-migration (not performed automatically).
- **Future upstream Moodle pulls:** these are *added* columns on core tables,
  not edits to core source files, so a Moodle core upgrade does not conflict.
  If Moodle ever ships a colliding `open_*` column name (unlikely — the prefix
  is eAbyas-specific), `field_exists` guards make the add a no-op.

## Addendum 2026-09-03 — fresh-install gap closed (UAT Stage A finding)

The 2026-06-04 wiring lived in `db/upgrade.php` only. Moodle runs `upgrade.php`
solely when an already-installed plugin's version is bumped, and `db/install.php`
solely on a from-scratch install — so every site that had the plugin got the
columns, and the first genuinely fresh Sentientia install (UAT-Sentientia-LMS,
Moodle 5.2 / MySQL 8.4, 2026-09-03) came up **without** them. Symptoms: the
`refresh_predictive_cache` task failing with `Unknown column 'open_path'`, the
theme's guest landing page falling back to site-wide counts, org tree / audience
enrolment / lifecycle queries all broken. Fix: `local/sentientia_core/db/install.php`
(`xmldb_local_sentientia_core_install()` → `substrate::ensure_all(false)`),
version `2026090301`; UAT itself was repaired with the existing
`cli/bootstrap_substrate.php` (55 columns added, dry-run first). The same
install-vs-upgrade trap hit the adaptive learning-path tables in June —
PHPUnit `init.php` on a dropped test DB is the fresh-install gate that catches
both.

## Endgame

ADR-024 Waves 4–5 migrate the live read-path off `open_*` onto the first-party
`local_sentientia_tenant` / `org_unit` / `org_member` tables, after which this
substrate (and this core-mod) is retired behind a flag. Until then this is the
supported way Sentientia stands up its tenant layer.
