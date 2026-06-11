# Core mod — `lib/setuplib.php`: function_exists guard on `ini_get_bool()`

| | |
|---|---|
| **Date** | 2026-06-11 |
| **File** | `public/lib/setuplib.php` (Moodle 5.2 instance tree) |
| **Line** | ~530 (the `ini_get_bool()` declaration) |
| **Finding** | WF-015 (WORKFLOW-TEST-MATRIX.md) |
| **Applies to** | Moodle 5.2 instances served via php-cgi/FastCGI. NOT applied to the 5.1 mod_php instance (bug unreachable there). |
| **Upstream-report candidate** | Yes — see "Upstream" below. |

## Problem

Moodle 5.2 under php-cgi (Apache `mod_proxy_fcgi` → `php-cgi.exe`) fatals
on two classes of minimal-bootstrap scripts:

1. **Pure `ABORT_AFTER_CONFIG` scripts** (`lib/javascript.php`,
   `theme/styles.php`, `theme/yui_combo.php`): at request shutdown,
   `core\shutdown_manager::request_shutdown()` (line ~245) calls
   `ini_get_bool()` — declared only in `lib/setuplib.php`, which the
   minimal bootstrap never loads. Fatal: *call to undefined function*.
   → Fixed by a **polyfill in the instance `config.php`**, conditional on
   `defined('ABORT_AFTER_CONFIG')` (recipe in
   `moodle-enhancement/deploy/apache-sentientia52-vhost.conf.template`).

2. **Cancel-abort scripts** (`r.php` — the 5.2 ESM loader — and
   `theme/font.php`): they define `ABORT_AFTER_CONFIG`, require
   `config.php` (which arms the polyfill), then on cache miss define
   `ABORT_AFTER_CONFIG_CANCEL` and re-enter **full** setup — which loads
   `lib/setuplib.php`, whose declaration is **unguarded**. Fatal:
   *Cannot redeclare function ini_get_bool()*.
   → Fixed by **this core mod**: wrap the core declaration in
   `if (!function_exists('ini_get_bool'))`.

Both halves are required. The polyfill alone breaks class 2; the guard
alone leaves class 1 fataling at shutdown. Symptom in production terms:
dashboard loads but Font Awesome glyphs and the React ESM autoinit 500,
which the Playwright render-smoke gate catches as console errors on
every persona.

## Change (before → after)

```php
// BEFORE (vanilla 5.2):
function ini_get_bool($ini_get_arg) {
    $temp = ini_get($ini_get_arg);

    if ($temp == '1' or strtolower($temp) == 'on') {
        return true;
    }
    return false;
}

// AFTER:
// SENTIENTIA-CORE-MOD: function_exists guard. Minimal-bootstrap scripts that
// cancel ABORT_AFTER_CONFIG (r.php, theme/font.php) re-enter full setup after
// the instance config.php polyfilled this fn for php-cgi shutdown (WF-015).
// Without the guard the re-entry fatals with a redeclare error under FastCGI.
if (!function_exists('ini_get_bool')) {
function ini_get_bool($ini_get_arg) {
    $temp = ini_get($ini_get_arg);

    if ($temp == '1' or strtolower($temp) == 'on') {
        return true;
    }
    return false;
}
}
```

The function body is byte-identical to core — the polyfill and the core
declaration can never diverge in behaviour, only in which one wins.

## Upgrade-safety

- Grep anchor: `SENTIENTIA-CORE-MOD` in `lib/setuplib.php`.
- On upstream Moodle pulls, the hunk re-applies cleanly unless upstream
  moves/renames `ini_get_bool()`. If upstream guards the declaration
  themselves (the proper fix), drop this mod AND keep the config.php
  polyfill (still needed for class-1 scripts) — or drop both if upstream
  also fixes `shutdown_manager`.
- If the mod is lost on upgrade, the failure is loud and immediate
  (fonts + r.php 500 on every page; render-smoke gate goes red), not
  silent.

## Upstream

Root cause is arguably two core bugs: (a) `core\shutdown_manager`
assumes `lib/setuplib.php` is loaded, which `ABORT_AFTER_CONFIG`
bootstraps violate under FastCGI SAPIs; (b) `ini_get_bool()` is
declared unguarded, unlike several other setuplib polyfill-friendly
helpers. Worth an MDL tracker report with this doc as repro notes
(php-cgi 8.4 + Apache 2.4 mod_proxy_fcgi on Windows; any
javascript.php URL 500s at shutdown).

## Verification (2026-06-11)

| URL class | Before | After |
|---|---|---|
| `lib/javascript.php/...` | 500 (undefined fn at shutdown) | 200 |
| `theme/styles.php/...` | 500 | 200 |
| `r.php/core/esm/...react_autoinit` | 500 (redeclare) | 200 |
| `theme/font.php/...woff2` | 500 (redeclare) | 200 (`font/woff2`) |
| Full pages (`/login/index.php`, `/my/`) | 200 (unaffected) | 200 |
