# Persona fix - catalog mobile (D8, D10, D12) - 2026-09-30

Branch `claude/persona-fix-catalog`. Plugin `local_sentientia_catalog` 1.0.6-beta (2026093001).
Source of the findings: `../personas/TRIAGE.md` rows D8, D10, D12.

## What changed

| Id | Defect (learner catalog) | Fix |
|---|---|---|
| D8 | The "Filters & Sort" `<details>` was rendered open on every viewport. At 590 px and below an open `<details>` is a fixed bottom sheet, so the sheet covered the bottom quarter of the phone on load. | `templates/catalog.mustache`: the `<details>` gets an id and keeps `open` (desktop needs it), and a small inline script directly after it closes it when `(max-width: 590px)` matches, before first paint, and re-syncs on resize or rotation. Desktop is unchanged. |
| D12 | The "NEW" / completed badge and the bookmark heart both sat at top 8 px / right 8 px of the card thumbnail, so the heart covered the pill. | `templates/course_card.mustache` + `styles.css`: the badge takes `airpay-catalog__badge--beside-heart`, which moves it left of the heart by one heart width plus a gap, centred on the heart. Heart size is one shared custom property so they cannot drift. The public storefront badge (no heart) keeps its corner. |
| D10 | The learner catalog was 400 px wide at a 390 px viewport (10 px horizontal page scroll). | `styles.css`: the category tile is a grid item with a `nowrap` name, so its automatic minimum width made the single mobile column as wide as the longest name ("AIRPAY PAYMENT SERVICES PRIVATE LIMITED", 338 px of text). `min-width: 0` on the tile, `minmax(0, 1fr)` tracks at 768 px and 590 px, and the name wraps at 590 px and below. |

Root cause of D10, confirmed: `body.path-local-sentientia_catalog .ap-shell__content { overflow: visible }`
(`theme/sentientia/scss/moodle/partials/_layout-shell.scss:681`) is deliberate for the storefront, so it no longer
clips a wide child, and the shell gutter at 768 px and below is 16 px (`:644`). 16 px + a 372 px tile = 400 px.
Only the /1 learner sees that category (it holds 189 courses), which is why /77 and the guest storefront were clean.

## How these were captured

The served copy under `C:/xampp/htdocs/moodle5/public` was not touched (deploying is the lead's step), so these are not
screenshots of the live app. Each page is the real `catalog.mustache` (with its partials and `course_card.mustache`)
rendered by Moodle's bundled Mustache engine, styled by the real `styles.css` and the compiled `_tokens.scss`, in
headless Chrome at a true 390 px viewport (an iframe, because headless Chrome will not go below 500 px). Data is the
real local /1 learner set (category names and counts, course names). The app shell (sidebar, topbar) is not
rendered; a 16 px side gutter stands in for `.ap-shell__content` at 390 px and 48 px for desktop. "Before" is the same
fixture built from `HEAD` of `claude/gap-integration`.
The lead should re-run `--persona learner --steps catalog` for the in-app confirmation.

## Files

| File | Shows |
|---|---|
| `01-mobile-390-load-before.png` | Sheet open on load, covering the bottom of the screen. NEW badge under the heart. |
| `02-mobile-390-load-after.png` | Same load, no sheet. |
| `03-mobile-390-badge-heart-before.png` / `04-...-after.png` | Trending card thumbnail, badge vs heart. |
| `05-mobile-390-categories-before.png` / `06-...-after.png` | Category tiles running off the right edge vs fitting and wrapping. |
| `07-mobile-390-filters-closed-after.png` | The "Filters & Sort" pill above the grid, sheet closed. |
| `08-mobile-390-filters-tapped-open-after.png` | After tapping the pill, the sheet opens. |
| `09-desktop-1280-before.png` / `10-...-after.png` | Desktop: filters (sort tabs) still visible, same geometry. Badge sits beside the heart. |

## Measurements (headless Chrome, fixture)

| Check | Before | After |
|---|---|---|
| Document width at a 390 px viewport, 16 px gutter | 400 px (10 px overflow, offender `.airpay-catalog__category-card`) | 390 px (no overflow) |
| Same at a 24 px gutter | 408 px | 390 px |
| `<details>` open on load at 390 px | true | false |
| `<details>` open on load at 1280 px | true | true |
| Desktop layout rectangles (search, carousels, categories, grid, header) | reference | identical |

Viewport resize (Chrome DevTools protocol, `Emulation.setDeviceMetricsOverride`, page loaded at 390):

| Width | `<details>` open |
|---|---|
| 390 (load) | false |
| 1000 | true |
| 590 | false |
| 591 | true |
| 390, then tapping the summary | true |

Tests: `local/sentientia_catalog/tests/catalog_mobile_layout_test.php` (7 tests). PHPUnit was not run in this pass (the lead
runs every suite after the bundles merge). The same test class was run against a small PHPUnit shim with the real
template and stylesheet: 7 of 7 pass on the fixed code and 6 of 7 fail on the pre-change code (the seventh guards
that the public storefront badge keeps its corner).
