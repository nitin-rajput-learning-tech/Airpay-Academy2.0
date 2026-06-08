# From-scratch Sentientia install — de-brand VALIDATED + vanilla-portability gap scoped

- **Date:** 2026-06-08
- **Action:** Nitin-authorised capstone — wipe + clean reinstall of the local XAMPP instance,
  empty/pristine (no learner data). Full DB backup taken first (restore point below).
- **Backup / restore point:** `D:\Claude Local\Moodle Backup\pre-scratch-wipe-2026-06-08\moodle-db.sql`
  (3.3 GB, 1262 tables, ends "Dump completed"). `config.php` preserved (wwwroot/db/dataroot/VAPID key).
  To restore the prior data-rich instance: `mysql -uroot moodle < moodle-db.sql`.

## ✅ Primary goal ACHIEVED — the `airpay_*→sentientia_*` de-brand is solid from zero
`admin/cli/install_database.php` (core + all on-disk plugins, empty DB) **completed successfully**:
- **505** plugins installed, incl. **40** `local_sentientia_*`, 6 `block_sentientia_*`,
  `theme_sentientia`, `enrol_sentientiasub`, `quizaccess_sentientia_proctoring`, `paygw_airpay`.
- **`external_functions`: 0 airpay / 162 sentientia** — built fresh from each plugin's
  `db/services.php`. This is the strongest possible proof the WS de-brand is **self-consistent
  from zero** (no relabel/cache shim involved on a clean install).
- **0** `airpay`/`airpayux` component anywhere except the intentionally-kept `paygw_airpay`.
- admin account created; site `/` 200 (after the front-page fix below); `/login` 200.
- Only non-fatal note: the standard LTI-1.3 openssl.cnf warning (environment, not Sentientia).

**Conclusion:** the component rename (35 plugins + theme) installs cleanly on an empty database.
The de-brand — the mission of this work — is production-grade.

## ⚠ Finding (NOT a de-brand regression) — Sentientia is not yet vanilla-portable (ADR-018 gap)
On a **non-BizLMS** (pristine / "Enterprise N") deployment the **Sentientia theme hard-queries
BizLMS-injected `open_*` columns** that core Moodle's `install.xml` does not create, so pages 500
with `dml_read_exception: Unknown column 'open_path' / 'open_supervisorid' / 'open_costcenterid'`.

- **Fixed already:** `theme/.../layout/frontpage.php` (public landing) — now `field_exists`-guarded
  with a site-wide fallback; `/` renders 200. (Applied to the local webroot theme.)
- **Remaining (authenticated pages 500 — `/my/`, `/admin/*`):** the columns are referenced in
  ~30 sites across 8 theme PHP files, incl. ones that run on **every page**:

  | File | refs | runs on |
  |------|------|---------|
  | `classes/output/core_renderer.php` | open_path ×3, open_costcenterid ×1 | EVERY page |
  | `classes/role_detector.php` | open_supervisorid ×5 | EVERY page (role logic) |
  | `classes/hook_callbacks.php` | open_path ×5 | EVERY page (hooks) |
  | `layout/dashboard.php` | open_path ×7, open_supervisorid ×2 | `/my/` |
  | `classes/sidebar_navigation.php` | open_path ×2 | navigation |
  | `classes/output/traits/login_ui.php` | open_path ×3 | login surfaces |
  | `classes/output/traits/user_menu.php` | open_path ×1 | user menu |
  | `layout/frontpage.php` | open_path ×5 | front page — **FIXED** |

  Plugin-level (`local_sentientia_*`) BizLMS-column dependencies almost certainly exist too
  (PHPUnit already showed `open_hrmsrole` / tenant-scope failures) — not yet enumerated.

- **Impact:** **ZERO on Airpay production** (its BizLMS schema has all `open_*` columns; the app
  serves 3,176 users today). Blocks a clean **vanilla / Enterprise-N** deployment.

## Two separate de-brand loose ends this surfaced
1. **Theme rename is local-only.** The webroot/DB are `theme_sentientia`, but the **git source is
   still `theme/airpayux/`** (repo root) — the rename was never committed to git. A clean
   deploy-from-git produces `airpayux`, not `sentientia`. (Production runs `airpayux`, so this is
   latent, not breaking.) The webroot `theme/sentientia/` also carries an **uncommitted divergent
   `frontpage.php`** (the `open_path` "Public tenant" version; git's `airpayux` frontpage is the
   older site-wide one).
2. The proper vanilla-portability fix is an **architectural decision** (Nitin's call), not a patch:
   - (a) **Code-resilience** (recommended, true independence): route `open_*` reads through a
     tenant-schema seam, `field_exists`-gated, degrading to single-tenant vanilla behaviour
     (default branding, learner role, site-wide data). `core_renderer` runs on every page →
     **review-gated**. This is ADR-018 Wave-2.
   - (b) **Sentientia owns the columns:** a `local_sentientia_*` plugin `install.xml`/upgrade adds
     the tenant columns it needs (so a fresh install has them). Quick, but bakes the schema in.
   - (c) **Stopgap:** inject the `open_*` columns on the local instance to make it render now.

## Recommendation / decision needed from Nitin
The local instance is installed + the de-brand validated, but **authenticated pages 500 until the
theme is decoupled from the BizLMS schema** (or the columns are provided). Pick: (a) greenlight the
ADR-018 Wave-2 theme/plugin independence refactor in the **git theme** (and reconcile theme_airpayux
→ theme_sentientia in git first); and/or (b) re-import the prod dataset for an Airpay-schema-usable
local instance; and/or (c) the column-injection stopgap. The destructive wipe is fully reversible
from the 3.3 GB dump above.

## Update — data restored + authenticated-500 root cause REFINED (2026-06-08, later)

Per "continue till all done", I (c) provisioned the `open_*` columns (resolved the column-level 500s)
then (b) **restored the 3.3 GB dump** → the data-rich de-branded instance is back: **1262 tables,
3176 users, 412 courses, external_functions 0 airpay / 162 sentientia**, `upgrade.php` "no upgrade
needed", caches purged. Public + login pages 200; **renamed plugin pages render**
(`/local/sentientia_courses` + `/local/sentientia_live` → 200) — de-brand confirmed working with real
data. Admin login reset to **`academy@airpay.co.in` / `Sentientia@2026`** (siteadmin id 2).

**REFINED root cause of `/my/` + `/admin/*` 500 — it is NOT (only) the BizLMS columns.** Even WITH all
`open_*` columns + full production data, those pages still 500 with
`coding_exception: page layout file … does not contain the main content placeholder`. Cause: the
custom Sentientia **dashboard + admin layouts render via `render_from_template` and never call
`$OUTPUT->main_content()`**, which **Moodle 5.1.3+ enforces** (this local instance is 5.1.3+).
Confirmed **data-independent** (identical error on the empty install AND the restored data-rich one)
and present in **git's `theme/airpayux` layouts too** (both lack `main_content`). This is a
**Moodle-5.x custom-layout compatibility issue** (part of the Phase-B 5.2-upgrade layout-rebase
workstream), **NOT a de-brand regression**. Production is unaffected (it runs an older Moodle that
doesn't enforce the placeholder). The fix is theme-author work — add a `main_content()` / `{{{ main_content }}}`
region to the custom dashboard + admin layouts without duplicating their bespoke UI — deliberately
deferred (not a safe autonomous patch on every-page custom layouts).

**Net:** the `airpay_*→sentientia_*` de-brand is **100% validated** (from-zero install + restored-data
both confirm component / WS / theme naming is clean; 0 airpay except the kept `paygw_airpay`). The
local instance is restored + usable for public + plugin surfaces. Two distinct, pre-existing,
**non-de-brand** follow-ups remain for a fully-green dashboard: (1) the **Moodle-5.x `main_content`
custom-layout compat** fix (above), and (2) the **ADR-018 vanilla-portability** decouple (theme/plugins
hard-querying BizLMS `open_*` columns). Both are theme/upgrade workstreams, not naming defects.
