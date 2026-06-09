# Platform Loading Fixes — Dashboard AMD / Charts / Prefs / PWA meta

**Date:** 2026-06-09
**Author:** Claude (autonomous `/loop`, code+DOM+loading inspection)
**Trigger:** Nitin — *"only visual screenshot inspection in headless browser will
not work, you will have to check what is in the code and what is visible, and
check if it is loading as expected. fix all."*
**Instance:** local XAMPP, Moodle 5.1.3+, active theme `theme_sentientia`
(webroot `C:\xampp\htdocs\moodle5\public\theme\sentientia\`).

> ⚠️ **Preservation note.** `theme_sentientia` is the de-branded webroot fork and
> is **NOT git-tracked** anywhere in the workspace (`moodle-enhancement/theme/` is
> empty; `wt-debrand/theme/` carries the older branded `theme_airpayux`). All file
> edits below were made directly in the webroot and will be **lost on any clean
> redeploy** unless reconciled into git. This document is the canonical record of
> what changed and why, and is the spec for that reconciliation. See
> *§6 Git reconciliation*.

---

## TL;DR

The dashboard (and, it turned out, **every** AMD-driven feature on the platform)
was silently broken by two fork-rename artifacts. Screenshot-only QA could never
see it — the pages *rendered*, they just had dead JavaScript. Code + DOM + console
inspection found it in minutes.

| # | Symptom (visible) | Root cause (in code) | Status |
|---|-------------------|----------------------|--------|
| 1 | Dashboard "Enrolment Trend" + "Course Distribution" charts blank | `dashboard.mustache` never emitted `{{{ output.standard_end_of_body_html }}}`, so RequireJS/AMD never bootstrapped on the dashboard | ✅ fixed + verified |
| 2 | All AMD features dead platform-wide (`require` resolved to empty modules) | 34 built AMD modules still declared `define("theme_airpayux/…")` after the airpayux→sentientia fork; callers request `theme_sentientia/…` → name mismatch, factory never runs | ✅ fixed + verified |
| 3 | 2× console 404 on sidebar collapse | `fetch('/lib/ajax/setuserpref.php')` — endpoint removed in Moodle 5.1 | ✅ fixed + verified (200 OK round-trip) |
| 4 | Raw text `…js_call_amd() … regressed. }}` leaking at page bottom | My own fix-1 Mustache comment contained `{{#js}}`; `{{! }}` closes at first `}}` | ✅ fixed + verified |
| 5 | 1 console warning on every page | only `apple-mobile-web-app-capable` meta emitted; Chrome wants standard `mobile-web-app-capable` | ✅ fixed + verified |

**Final state:** dashboard + 18 other authenticated surfaces — **0 console errors,
0 warnings**. Both dashboard charts paint (23,279 / 12,498 non-transparent px).

---

## 1. Missing `standard_end_of_body_html` on the dashboard layout

`standard_end_of_body_html` is the output that flushes Moodle's RequireJS/AMD
bootstrap, the `$PAGE->requires->js_call_amd()` queue, and any `{{#js}}` inline
AMD. **Every** sibling layout template emits it (`columns1`, `columns2` via the
footer partial, `drawers`, `embedded`, `login`, `secure`, `shell`, `maintenance`);
`frontpage.php` emits it in PHP. Only `dashboard.mustache` had regressed and
omitted it — so on `/my/`:

- `window.require` was `undefined` → no AMD module ran;
- the `{{#js}} require(['theme_sentientia/chart_loader'], …) {{/js}}` chart block
  never executed → **blank charts**;
- the theme's own `{{#js}} require(['theme_sentientia/loader','…/drawer'], …)`
  never ran;
- the sidebar-collapse handler fell back to a raw `fetch()` (see §3).

**File:** `theme/sentientia/templates/dashboard.mustache` (the active `{{#use_shell}}`
branch), just before its `</body>`:

```mustache
})();
</script>

{{!
     2026-06-09 fix - emit the end-of-body output. Without this line the
     RequireJS/AMD bootstrap, the trailing js-helper require(loader,drawer)
     block below, and every PAGE-requires js_call_amd call (the dashboard
     charts) never flush ... NB keep this comment free of curly-brace pairs
     so Mustache does not terminate it early.
}}
{{{ output.standard_end_of_body_html }}}
</body>
{{/use_shell}}
```

**Verify:** `typeof window.require === 'function'` on `/my/` (was `"undefined"`).

---

## 2. AMD module name mismatch — `theme_airpayux/*` → `theme_sentientia/*`

The hand-minified build files (grunt unavailable in this chain) kept the OLD
branded module name baked into their `define("theme_airpayux/X", …)` after the
theme was forked/renamed to `theme_sentientia`. RequireJS maps a requested module
to a file **by path** (`theme_sentientia/X` → `theme/sentientia/amd/build/X.min.js`)
but the file registers itself under `theme_airpayux/X`. The requested name never
gets an anonymous/matching define, so:

- the callback fires with the dependency resolved to `undefined`, and
- the real factory (which, for `chart_loader`, sets `window.Chart`) **never runs**
  because nothing requires `theme_airpayux/X`.

Result for charts: inline `new Chart(...)` threw `ReferenceError: Chart is not
defined`. For every other module (`cart_badge`, `user_status_badge`, `datatable`,
`quickactions`, `loader`, `drawer`, …) the `init()` silently no-op'd.

**Fix (literal rename across all built AMD files — self-names + inter-module
absolute refs; relative `./` deps untouched):**

```powershell
# Run from anywhere. Preserves UTF-8 (no BOM). 34 files changed.
$root = "C:\xampp\htdocs\moodle5\public\theme\sentientia\amd"
Get-ChildItem -Path $root -Recurse -Filter *.js -File | ForEach-Object {
  $c = [System.IO.File]::ReadAllText($_.FullName)
  if ($c.Contains('theme_airpayux')) {
    [System.IO.File]::WriteAllText($_.FullName,
      $c.Replace('theme_airpayux','theme_sentientia'),
      (New-Object System.Text.UTF8Encoding($false)))
  }
}
```

34 build files renamed (announcement, aria, carousel, cart_badge, chart_loader,
datatable, deprecated, drawer, drawers, footer-popover, form-display-errors,
index, loader, mobile_nav_highlight, org_cascade, page_title, pending, popover,
quickactions, toast, user_status_badge + 12 `bootstrap/*`). `amd/src/*` were
already clean (anonymous defines). Six `*.min.js.map` sourcemaps still reference
the old name (dev-only, never executed — cosmetic, left as-is).

**Safe because:** zero callers requested the old name — grep of all theme
`*.php`/`*.mustache` for `theme_airpayux/` returned nothing; every `js_call_amd`
/ `require` uses `theme_sentientia/…`. Renaming the registered name to match the
path can only fix, not break.

**Verify (active require of each module by new name on `/admin/user.php`):**
`user_status_badge`, `datatable`, `cart_badge`, `quickactions`, `loader`,
`drawer` → all `OK:object`.

---

## 3. `setuserpref.php` 404 → Moodle 5.1 preference API

`/lib/ajax/setuserpref.php` was removed in Moodle 5.1. Three sites used a raw
`fetch()` to it for the sidebar-collapse preference; `M.util.set_user_preference`
is a deprecated stub that throws. Replaced with the modern
`core_user/repository` AMD module (`setUserPreference`), which POSTs to the REST
router `…/user/current/preferences/{name}` and validates against the prefs
registered by the component's `*_user_preferences()` callback.

**Files:**
- `theme/sentientia/lib.php` — **new** `theme_sentientia_user_preferences()`
  registering `theme_sentientia_sidebar_collapsed` (PARAM_BOOL,
  `permissioncallback => [\core_user::class, 'is_current_user']`), mirroring
  core `theme_boost_user_preferences()`. **This registration is the unlock** —
  without it the REST write is rejected.
- `theme/sentientia/templates/dashboard.mustache` (~L810)
- `theme/sentientia/templates/shell.mustache` (~L51)
- `theme/sentientia/classes/output/core_renderer.php` (~L416, inside the
  `<<<'JS'` nowdoc)

All three now do:

```js
if (typeof require !== 'undefined') {
    require(['core_user/repository'], function(UserRepo) {
        UserRepo.setUserPreference('theme_sentientia_sidebar_collapsed', collapsed ? '0' : '1');
    });
}
```

**Verify:** clicking the collapse toggle →
`POST /r.php/api/rest/v2/user/current/preferences/theme_sentientia_sidebar_collapsed → 200 OK`;
on reload the server-rendered `data-collapsed` reflects the saved value
(write→store→read round-trip confirmed).

---

## 4. Mustache comment leak (regression I introduced, then fixed)

The first draft of the §1 comment contained `{{#js}}`. Mustache closes a
`{{! … }}` comment at the **first** `}}`, so the `}}` inside `{{#js}}` terminated
the comment early and dumped the remainder as literal page text. Caught by the
verification screenshot (not by the console — it was clean). Rewrote the comment
free of any curly-brace pairs. Lesson: **never put `{{` / `}}` inside a Mustache
comment.**

---

## 5. PWA capability meta deprecation warning

`theme/sentientia/templates/head.mustache:133` emitted only
`<meta name="apple-mobile-web-app-capable">`, which Chrome flags as deprecated on
every page. Added the standard `<meta name="mobile-web-app-capable" content="yes">`
alongside it (kept the Apple variant for older iOS Safari). Console warning count
on `/my/`: 1 → **0**.

---

## 6. Git reconciliation (OPEN — for Nitin)

These edits exist **only** in the webroot `theme/sentientia/`, which is not in
git. To preserve them:

1. Decide the canonical home for the de-branded theme source (it is currently
   untracked — the long-standing "documented divergence").
2. Files hand-edited this session (mirror these exactly):
   - `templates/dashboard.mustache` (end-of-body output + pref API + comment)
   - `templates/shell.mustache` (pref API)
   - `templates/head.mustache` (PWA meta pair)
   - `classes/output/core_renderer.php` (pref API)
   - `lib.php` (new `theme_sentientia_user_preferences()`)
3. The 34 `amd/build/*.js` renames are reproducible verbatim via the PowerShell
   one-liner in §2 — no need to copy each file.
4. **Bonus:** the older git-tracked `theme_airpayux` likely shares bug §1
   (missing `standard_end_of_body_html` on its dashboard layout) and bug §3
   (legacy `setuserpref.php`). If `theme_airpayux` is ever revived, apply the
   same two fixes there (its AMD names are already self-consistent, so §2 does
   NOT apply to it).

---

## Verification matrix (this session, live Chrome via Playwright)

| Surface | Console |
|---------|---------|
| `/my/` (dashboard, Site Admin) | 0 err / 0 warn; both charts painted |
| `/admin/user.php` | 0 err |
| sentientia_users, _courses, _learningpath, _evaluation, _analytics, _reports, _skills, _programs, _classroom, _exams, _notifications, _compliance_report, _org, _emails, _privacy | 0 err each |

Evidence screenshot: `docs/visual-evidence/2026-06-09/siteadmin/dashboard-charts-fixed.jpg`.

---

## 7. Relationship to ADR-025 follow-up (c) — the *plugin* stale-bundle gap

PROJECT-STATE (2026-06-08) records a sibling gap: the airpayux→sentientia rename
"skipped the compiled `amd/build/*.min.js`", leaving ~30 committed
`local/*/amd/build/*.min.js` bundles calling renamed-away `local_airpay_*` **WS
function names** → broken AJAX. That is the **plugin-side** sibling of bug §2 (which
was the **theme-side** stale *module-define names*). Same root cause: the rename
never rebuilt compiled bundles.

**Confirmed this session — the live webroot is NOT affected:** a grep of
`C:\xampp\htdocs\moodle5\public\local\**\amd\build\*.min.js` for `local_airpay_`
returns **0** occurrences, while `local_sentientia_` is present (144 occ in the
first 15 bundles). The clean local reinstall (PROJECT-STATE task "Wipe + clean
reinstall local XAMPP from scratch") deployed correctly-named plugin bundles. So
ADR-025 follow-up (c) remains a **git-repo hygiene** item (stale *committed*
bundles would break a future deploy-from-git) but is **not** a live-instance
loading bug. The `grunt amd` rebuild (or re-`sed`) on the git tree, excluding the
legitimately-un-renamed `airpay_ratings`, is still the durable fix there.
