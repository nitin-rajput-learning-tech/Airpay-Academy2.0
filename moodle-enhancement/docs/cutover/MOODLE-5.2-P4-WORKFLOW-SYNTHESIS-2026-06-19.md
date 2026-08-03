# Moodle 5.2 Candidate — P4 Static Validation (2026-06-19)

**VERDICT: PASS-WITH-NOTES** — 0 confirmed blocker/high findings. All 7 adversarially-verified judgment findings were dismissed as false positives (the bare `external_*` / `externallib.php` patterns resolve via core's still-active class_alias shim; the BS4 badge classes render via the theme's bundled CSS). Real residual work is build-parity (AMD min.js gaps) and packaging/forward-compat hygiene, none of which break on 5.2 / PHP 8.3.

## Per-dimension results

| Dimension | Items checked | Result |
|---|---|---|
| PHP lint (php -l, PHP 8.2.12) | 1567 files | PASS — 0 syntax errors |
| DB schema + version integrity (fresh-install / MariaDB utf8mb4) | 45 install.xml / 54 version.php | PASS — 0 malformed XML, 0 missing component/version, 0 utf8mb4 DDL hits (worst index 1220B vs 3072B; no CHAR>1333) |
| Moodle 5.2 + PHP 8.3 deprecated/removed API scan | ~1840 files | PASS — 0 blockers; all flagged highs dismissed; print_error/each/create_function/utf8_*/dynamic-prop/curly-interp clean |
| AMD build parity + de-brand integrity | 133 (55 plugins + AMD/theme builds) | PASS-WITH-NOTES — de-brand clean (0 airpayux survivors, 0 component mismatches); 10 src JS files lack min.js builds |

## Confirmed findings

### Build parity — AMD min.js gaps (3 findings)
These are real and shipped (production serves `/amd/build/*.min.js`; unbuilt modules 404 / fail to load). Severity is **HIGH per the AMD-parity dimension's own grading**, but none is a 5.2 / PHP 8.3 break — they are missing build artifacts.

| Severity | Plugin / path | Missing builds | Fix |
|---|---|---|---|
| High | `local/sentientia_cart/amd/src/` (no `amd/build/` dir) | add_to_cart, admin_orders, cart, history, set_price `.min.js` | Run Grunt/AMD build for the plugin to generate all 5 `.min.js`; commit `amd/build/`. |
| High | `local/sentientia_emails/amd/src/` (`amd/build/` empty) | delivery_log, rule_manager, template_editor `.min.js` | Build the 3 modules; commit `.min.js` + `.map`. Admin email rule/template editor JS currently won't load. |
| High | `local/sentientia_proctoring/amd/src/` (no `amd/build/` dir) | consent, proctor `.min.js` | Build both; commit. Proctoring consent gate + monitor JS currently won't load. (Distinct from the `quizaccess_sentientia_proctoring` rule, which has no `amd/`.) |

### Packaging hazard (1 finding)

| Severity | File | Fix |
|---|---|---|
| Low | `local/sentientia_live/sentientia_live/` — nested duplicate plugin tree (own version.php 2026052503 + db/install.xml redefining the same 5 tables, same component `local_sentientia_live`) | Delete the nested tree before shipping the standalone package. Not loaded on fresh install (Moodle scans `local/*` one level deep), so no fresh-install break — but it is dead, conflicting DDL. |

### Forward-compat hygiene (downgraded from claimed high/medium — not 5.2 breaks)
Recorded for awareness only; the verifier confirmed all resolve and run on the 5.2 candidate.

- **Low** — `local/sentientia_emails/classes/external/{rule_api,template_api}.php`, `local/sentientia_leaderboard/classes/external/{get_board,list_boards,set_optout}.php`: bare `use external_api;` (etc.) + `require_once($CFG->libdir.'/externallib.php')`. Resolve today via core's still-active `class_alias` shim (deprecation explicitly delayed, MDL-76583). Migrate to `core_external\*` imports and drop the `externallib.php` include before the shim is removed (targeted Moodle 6.0). 6 include hits total (the 5 WS classes + `theme/sentientia/classes/output/traits/course_view.php:97`, which needs it for the global `external_format_text()`).
- **Info** — BS4 badge colour classes (`badge-success/-danger/-warning/-info/-primary/-secondary`, ~45 hits across 6 mustache + ~38 PHP locations). Render correctly: theme_sentientia is a standalone fork bundling its own Bootstrap 4 source, compiling working `.badge-*` rules into `style/moodle.css`; also covered by the active `_bs5-compat.scss` shim. Migrate to `text-bg-*` before Moodle 6.0.

## False positives dismissed

**8** judgment/deterministic items dismissed as not-real for 5.2/PHP 8.3:
- 7 adversarially-verified `is_real:false` findings (1 utf8mb4 index-budget all-clear; 5 bare-`external_*` Pattern-2 highs; 1 `externallib.php` Pattern-3 high — all masked by core's active class_alias shim) + the BS4-badge medium (CSS, not a removed API).
- The Pattern-3 and BS4-badge items survive as Low/Info forward-compat notes above (same files, recharacterized), not as breaks.
