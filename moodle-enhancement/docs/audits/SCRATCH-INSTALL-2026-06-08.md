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

## Update — authenticated-500 ACTUAL root cause found + FIXED (2026-06-09)

The "Moodle-5.x `main_content` custom-layout compat" diagnosis above is **WITHDRAWN — it was wrong.**
Every layout template on **both** sides already emits the placeholder:
`theme/sentientia/templates/dashboard.mustache:782` + `drawers.mustache:72`, and git
`theme/airpayux/templates/dashboard.mustache:480` + `drawers.mustache:158`. The placeholder was never
the blocker.

**The real, reproduced cause** of the `/my/` + `/admin/*` 500 (captured with a curl cookie-jar login as
`academy@airpay.co.in`, `disablelogintoken=on`):

```
Exception - Class "theme_sentientia\sentientia_navbar" not found
  theme/sentientia/classes/output/traits/page_helpers.php:121  (new \theme_sentientia\sentientia_navbar)
  ← theme/sentientia/layout/dashboard.php:1105  $OUTPUT->navbar()
  ← lib/classes/output/core_renderer.php:875     render_page_layout()
  ← my/index.php:185                             $OUTPUT->header()
```

This is a **de-brand half-rename regression**, NOT a Moodle-5.x compat issue. The theme de-brand renamed
the breadcrumb **class** `epsilonnavbar → sentientia_navbar` (see `theme/sentientia/version.php:354`)
but left the **file** named `classes/airpayux_navbar.php` (and its test `tests/airpayux_navbar_test.php`).
Moodle's autoloader maps `\theme_sentientia\sentientia_navbar` → `classes/sentientia_navbar.php`, which
did not exist → fatal `class not found`. Because `$OUTPUT->navbar()` runs *inside* `render_page_layout()`,
it throws **before** `header()` ever reaches the `main_content` token check — which is exactly why the
earlier session mis-attributed the symptom (and why the placeholder edits it made never resolved it).

The `"0 airpayux anywhere"` claim from the theme de-brand (PROJECT-STATE 2026-06-08) was scoped to
config / DB rows / served CSS — it did **not** catch class **filenames** under `classes/`.

**Fix (webroot `C:\xampp\htdocs\moodle5\public\theme\sentientia\`, local instance only):**
- `classes/airpayux_navbar.php` → `classes/sentientia_navbar.php`
- `tests/airpayux_navbar_test.php` → `tests/sentientia_navbar_test.php`

No content edits needed — both files already declared the `sentientia_navbar` / `sentientia_navbar_test`
identifiers and reference `\theme_sentientia\sentientia_navbar` correctly; only the filenames lagged the
rename. `php -l` clean on both; `admin/cli/purge_caches.php` run to rebuild `core_component`'s class map.

**Verification (post-fix, curl cookie-jar):**

| Page | Admin (`academy@…`, siteadmin) | Learner (loginas) |
|------|-------------------------------|-------------------|
| `/my/` | **200** | onboarded learner (Rasika 3113): **200**, full `airpay-dash` + `ap-shell__content` render, 57 KB, 0 token leak |
| `/admin/search.php` | **200** | — |
| `/admin/user.php` | **200** | — |
| `/my/courses.php` | **200** | **200** |
| `/local/sentientia_pages/onboarding.php` | — | non-onboarded learner (Beatrice 2058) `/my/`→**303**→onboarding **200** (correct gating, not an error) |

Dashboard renders correctly for both roles — no duplicated/broken content, no visible
`[MAIN CONTENT GOES HERE …]` token leak.

**git side — no change required, and a redundant `main_content` edit would be HARMFUL.** git
`theme/airpayux/` is fully internally consistent: `classes/epsilonnavbar.php` ↔ `class epsilonnavbar`,
`core_renderer.php:114` → `\theme_airpayux\epsilonnavbar`, `tests/epsilonnavbar_test.php` matches — so it
carries **no navbar-autoload bug**. Its templates already contain `{{{ output.main_content }}}`
(dashboard.mustache:480, drawers.mustache:158); adding a second placeholder would split the page at the
*first* token and dump the literal second token into the body. The bug lives **only** in the local
webroot `theme/sentientia/` rename, which is **not tracked in git** (confirmed). The durable git-side
fix belongs in whatever process produces `theme/sentientia` from `theme/airpayux` (the de-brand
class-rename pass): it must rename `*navbar*` source **files**, not just in-file identifiers. The overlay
script `moodle-enhancement/tools/overlay-airpay-customs.ps1` only *copies* `theme → theme/sentientia`;
the class-rename pass that introduced the half-rename is upstream of it and should grow a filename-rename
step (or a class↔filename consistency lint) so a re-generated `theme/sentientia` is correct from the
start.

**Net:** authenticated pages (admin + learner) are **green** on the local instance. The de-brand remains
validated; this was the last naming-completeness gap (a filename the rename pass missed), now closed
locally. Remaining open item is unchanged: the **ADR-018 vanilla-portability** `open_*`-column decouple
(architectural, human-gated).
