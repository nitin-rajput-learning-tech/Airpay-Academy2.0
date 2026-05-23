# Phase B.3 — Hook migration: theme_airpayux + local_sentientia_pwa

**Date:** 2026-05-23
**Status:** Migrated 2 legacy function-name callbacks to Moodle 5.2's new hook system. Same codebase still works on Moodle 5.1.

---

## What this fixes

Phase B.3 web smoke surfaced these deprecation notices on every request:

```
PHP Notice: Callback before_standard_top_of_body_html in theme_airpayux
component should be migrated to new hook callback for
core\hook\output\before_standard_top_of_body_html_generation

PHP Notice: Callback before_standard_top_of_body_html in local_sentientia_pwa
component should be migrated to new hook callback for
core\hook\output\before_standard_top_of_body_html_generation
```

Cosmetic — `process_legacy_callbacks()` was still firing the old function
names, so functionality wasn't broken. But every page load logged 2
deprecation lines into `error_log`, polluting the smoke output.

---

## The migration pattern (template for the other ~9 deprecated hooks)

For each plugin with a legacy `<plugin>_before_standard_top_of_body_html`:

1. **Move the function body into a hook class** — `classes/hook_callbacks.php`
   with a static method that takes the hook object and a separate
   helper that returns the HTML string. Splitting the two lets us share
   the implementation between the 5.2 hook entry point AND the 5.1
   legacy callback.

2. **Register the hook** — `db/hooks.php` returns a `$callbacks` array
   binding the hook class to the core hook namespace.

3. **CONDITIONALLY DEFINE the legacy function in lib.php** — wrap the
   `function <plugin>_<hook>()` declaration in `if (!class_exists(
   '\\core\\hook\\output\\<hook>_generation'))`. On 5.2 the class
   exists → the function never gets declared → Moodle's
   `process_legacy_callbacks()` scanner doesn't find it → no
   deprecation notice. On 5.1 the class doesn't exist → the function
   IS declared → legacy dispatch works as before. The function body
   delegates to the hook class helper so there's a single source of
   truth.

4. **Bump version.php** — Moodle re-scans `db/hooks.php` only when the
   plugin version increases.

5. **Deploy + upgrade.php + purge_caches.php + reload browser.**

### Gotcha — why "return ''" is NOT enough

The first iteration of this migration kept the legacy function defined
but had it bail with `return ''` on 5.2. This was WRONG: Moodle's
`process_legacy_callbacks()` invokes the function via
`get_plugins_with_function('before_standard_top_of_body_html', 'lib.php')`,
which scans lib.php for ANY function matching that name. Once found,
the scanner fires the deprecation notice via `debugging()` —
INDEPENDENT of whether a new-style hook subscription exists, and
INDEPENDENT of what the function returns.

The fix is to conditionally compile out the entire function declaration
on 5.2. Verified by counting `should be migrated` lines in `docker logs
moodle52web` before and after the patch — went from 2 per page load
(theme + pwa) to 0.

---

## Files touched

### theme_airpayux (theme version 2026052323 → 2026052324, release 1.0.24-beta)

| File | Change |
|------|--------|
| `theme/airpayux/classes/hook_callbacks.php` | NEW — `\theme_airpayux\hook_callbacks` with `before_standard_top_of_body_html_generation()` + `build_user_status_html()` helper |
| `theme/airpayux/db/hooks.php` | NEW — registers the hook subscription |
| `theme/airpayux/lib.php` | Old `theme_airpayux_before_standard_top_of_body_html()` reduced to a 5.1 shim that delegates to `hook_callbacks::build_user_status_html()` |
| `theme/airpayux/version.php` | Version bump + comment |

### local_sentientia_pwa (plugin version 2026052202 → 2026052301, release 0.5.3-alpha)

| File | Change |
|------|--------|
| `local/sentientia_pwa/classes/hook_callbacks.php` | NEW — `\local_sentientia_pwa\hook_callbacks` with `before_standard_top_of_body_html_generation()` + `build_install_cta_html()` helper |
| `local/sentientia_pwa/db/hooks.php` | NEW — registers the hook subscription |
| `local/sentientia_pwa/lib.php` | Old `local_sentientia_pwa_before_standard_top_of_body_html()` reduced to a 5.1 shim that delegates to `hook_callbacks::build_install_cta_html()` |
| `local/sentientia_pwa/version.php` | Version bump + comment |

---

## Why the legacy shim instead of just deleting the function

We still deploy this codebase to Moodle 5.1 (production) as well as
Moodle 5.2 (the Phase B target). 5.1's hook namespace
(`\core\hook\output\...`) doesn't exist, so a pure deletion would silently
disable the feature on 5.1.

The two-mode shim is small (≈10 lines) and keeps the migration
forward-only — once production is on 5.2, the shim and the legacy
function can both be deleted in a single later commit.

---

## Verification

```
# 1. Run upgrade.php inside the 5.2 container so Moodle rescans hooks.
docker exec moodle52web php /var/www/moodle/admin/cli/upgrade.php --non-interactive

# 2. Purge caches.
docker exec moodle52web php /var/www/moodle/admin/cli/purge_caches.php

# 3. Tail error_log while hitting the homepage.
curl -s -o /dev/null -w "%{http_code} %{size_download}\n" http://localhost:8081/

# 4. Grep for the deprecation strings — should find NOTHING for these 2 plugins.
docker exec moodle52web sh -c 'grep -c "theme_airpayux.*should be migrated" /tmp/php_errors.log 2>/dev/null || echo 0'
docker exec moodle52web sh -c 'grep -c "local_sentientia_pwa.*should be migrated" /tmp/php_errors.log 2>/dev/null || echo 0'
```

Both `grep -c` invocations should print `0` after the migration.

---

## Remaining hook migrations (Phase B.3.a-f)

After this leg, the deprecation sweep is partial. Other function-name
callbacks across our plugins that 5.2 wants migrated (catalogued during
Phase A.5 deprecation sweep) — fold each into its own session like this:

- `local_airpay_*_extend_navigation_*` (multiple plugins)
- `*_render_navbar_output` (theme_airpayux)
- `*_get_fontawesome_icon_map` (theme_airpayux)

Each migration is mechanical once the pattern is established. Estimated
30 min per legacy callback.

---

## ADR cross-ref

- ADR-011 — Phase B.3 (web smoke + iterative polish)
- PHASE-B3-WEB-SMOKE-PASS.md — established the deprecation baseline
- This file — first hook migration leg

---

## Headline for the changelog

> Migrated `before_standard_top_of_body_html` callbacks in theme_airpayux
> and local_sentientia_pwa from the legacy function-name convention to
> Moodle 5.2's new `\core\hook\output\before_standard_top_of_body_html_generation`
> hook. Two-mode shim preserved for 5.1 backward compat. 2 deprecation
> notices removed from every page load.
