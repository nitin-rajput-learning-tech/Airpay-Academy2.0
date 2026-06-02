# eAbyas / epsilon independence audit — 2026-06-02

**Goal:** "move away from Epsilon/eAbyas completely." **Finding:** mostly done in the repo, but
`theme_airpayux` still **imperatively loads epsilon at runtime** in 3 places — so epsilon cannot
be removed yet. The remaining work is **visual-gated** (needs a logged-in browser to confirm
logos/SCSS still render) and must **preserve GPLv3 copyright notices**. This doc is the handoff.

## State
- `theme/epsilon` — **removed from the repo** ✅ (independence done there). Still present in the
  local **deployed** tree (a deploy artifact; irrelevant to the repo, but see the coupling below
  — it is why the deployed theme still works).

## The 3 epsilon couplings (must all be cut before epsilon can be removed)

| # | Site | What | Fix | Risk → gate |
|---|------|------|-----|-------------|
| 1 | `theme/airpayux/lib.php:100` | `theme_airpayux_pluginfile()` serves airpayux's OWN logo/backgroundimage/loginlogo/carousellogo/slider1-5/favicon by `theme_config::load('epsilon')->setting_file_serve(...)` | `load('airpayux')` | **HIGH / visual.** Theme setting files may be stored under the `theme_epsilon` component (legacy, because this code loaded epsilon). Switching to `airpayux` looks up files under `theme_airpayux` — existing uploads could 404 until re-saved. Must verify every logo/slider/favicon still serves, and migrate the file-area rows if needed. |
| 2 | `theme/airpayux/settings.php:61` | `admin_setting_configthemepreset(..., 'epsilon')` — preset fallback uses epsilon's preset list | `'airpayux'` (airpayux must ship its own preset files) | **MED / visual.** SCSS preset resolution; verify the theme compiles + renders with airpayux presets. |
| 3 | `theme/airpayux/tests/scss_test.php:39` | `\theme_config::load('epsilon')->get_css_content_debug(...)` — test compiles epsilon's SCSS | `load('airpayux')` | **LOW / CI.** A test; the fix is obviously correct (test airpayux, not epsilon). Verify via PHPUnit/CI. |

> Cutting #3 alone does not free epsilon (#1 + #2 still load it). Do all three together in a
> browser-equipped session, verifying after each: (a) every theme file serves (logo, 5 sliders,
> favicon, login logo, background), (b) the theme compiles + renders (desktop + 590px mobile),
> (c) the SCSS test passes. Then `theme/epsilon` can be removed from deployed/prod.

## eAbyas references — 14 theme files

`config.php, lang/en/theme_airpayux.php, layout/dashboard.php, lib.php, settings.php, version.php,
scss/moodle/partials/_bizlms-{admin,dark,modern,overrides}.scss, templates/slider.mustache,
templates/socialicons.mustache, amd/src/quickactions.js (+ build)`.

**Split before touching any of these:**
- **GPLv3 `@copyright eAbyas ...` notices → PRESERVE (legal).** airpayux is a GPL fork of
  epsilon (eAbyas); GPLv3 §5 requires retaining the original author's copyright + license. These
  are NOT branding to strip — removing them is a license violation. Prior sessions deliberately
  preserved them.
- **Functional `eabyas` identifiers** (config keys, class/setting names, strings) → rename
  candidates to `airpayux`/`sentientia`. Each is in a theme file → visual-gated (verify the
  setting/template still binds + renders).

## Recommendation
The epsilon decoupling (3 sites) + functional-identifier rename are a coherent **theme** unit that
needs a **logged-in browser** for visual verification (logos serve, SCSS renders, presets apply) —
a fresh, browser-equipped session. Preserve all GPLv3 `@copyright` notices throughout. After the
decoupling verifies clean, remove `theme/epsilon` from the deployed/prod tree (a `[CONFIRM]` delete).
