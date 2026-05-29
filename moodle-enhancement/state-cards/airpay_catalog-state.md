# State Card — `local_airpay_catalog`

**Component:** `local_airpay_catalog`
**Version:** `2026052900` / `1.0.1-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. Public + learner course catalog surface.
**Last refreshed:** 2026-05-29 (course-card poster thumbnails — `catalog_manager::course_poster()` real-image/gradient-fallback fed into `format_course()`, `commerce::get_public_catalog()`, `course_card.mustache` + `catalog.mustache` + `styles.css`; dark-mode regression-walk fix — Enrol/Continue anchor-buttons re-pinned white)

---

## Mission

Course catalog — the public + learner-facing browse surface. Reads from
core Moodle courses + categories, layered with:
- `local_airpay_courses`'s tenant-share filter (cross-tenant catalog
  appearance — Sprint C/D)
- `local_airpay_cart`'s commerce hooks (price + add-to-cart on
  paid courses)

The catalog is the home of the "For You" feed (recommendations,
trending, new arrivals) for both authenticated learners and the public
landing page.

## DB tables

None — catalog is a read layer over `mdl_course`, `mdl_course_categories`,
and `local_airpay_courses_tenant_share`.

## Capabilities

None declared. Surface gating relies on core `moodle/course:view` +
the upstream `local_airpay_courses` cap layer.

## Feature flags

Registered (in `db/feature_flags.php`):
- `sentientia.catalog.public_lxp.enabled` (default **OFF**) — C4 / F-004.
  When OFF, `public.php` renders the legacy plain card grid (today's
  production look, byte-for-byte). When ON, the guest storefront uses
  the member catalog's `airpay-catalog__*` card + carousel language
  ("Popular picks" scroll-snap rail above a searchable/sortable grid).
  Commerce (price, add-to-cart, cart pill) preserved in both modes.

Consumes:
- `ai.recommendations.enabled` (toggles the "For You" recommended feed
  vs. "Trending this week" fallback)

## Key files

```
local/airpay_catalog/
├── version.php                                    2026052900 / 1.0.1-beta
├── README.md
├── lib.php
├── index.php                                       Authenticated learner catalog (full LXP)
├── public.php                                      Unauth public storefront — flag-branched:
│                                                     OFF=legacy grid, ON=LXP (C4/F-004)
├── mycourses.php                                   My-courses surface
├── course.php                                      Course detail entry point
├── cart.php                                        Cart redirect helper
├── classes/
│   ├── catalog_manager.php                         Search + filter + pagination
│   ├── category_manager.php                        Category tree + tenant filtering
│   ├── commerce.php                                Price + add-to-cart helpers
│   ├── hook_callbacks.php                          Moodle 5.x hook callbacks
│   └── privacy/                                    GDPR / DPDP
├── db/
│   └── feature_flags.php                           sentientia.catalog.public_lxp.enabled (OFF)
├── templates/
└── lang/
    ├── en/local_airpay_catalog.php
    └── hi/local_airpay_catalog.php
```

## Tests

`tests/` directory exists but no `*_test.php` files yet — query
correctness is exercised indirectly by `local_airpay_courses` PHPUnit
suite (which is where the underlying queries live).

## Open items

- [ ] PHPUnit smoke for `catalog_manager::search()` — pagination +
      tenant filter edge cases (P1)
- [ ] "Save for later" — wishlist tile distinct from cart
- [ ] Faceted search — instructor / category / language / duration
      filter chips (today: keyword + category only)
- [~] Mobile catalog polish (Phase 6B Surface 6) — guest storefront
      `public.php` LXP path verified at 590px (C4); member `index.php`
      already responsive
- [ ] Per-customer "Featured" curation (today: `local_airpay_courses_featured` is a flat list)
- [x] Course-card visual evidence update — refreshed 2026-05-29
      (`docs/visual-evidence/2026-05-29/c4-public-storefront-*.png`)

## State card created — 2026-05-24

Initial state card. Plugin has been live since 2026-05-06; created now
as part of the P1 state-card pass.

## C4 / F-004 — public storefront LXP restyle (2026-05-29)

`public.php` brought up to the member catalog's LXP/Netflix visual
language, behind `sentientia.catalog.public_lxp.enabled` (default OFF).
- NEW `db/feature_flags.php` (the plugin's first registered flag).
- `public.php` flag-branched: legacy plain grid (OFF, byte-for-byte
  production parity) vs. LXP storefront (ON — "Popular picks" scroll-
  snap rail + searchable/sortable grid, reusing `airpay-catalog__*`
  card + carousel components; inline carousel-arrow AMD via
  `$PAGE->requires->js_amd_inline()`).
- +16 `public_*` lang strings (en).
- Latent-bug fix in the LXP path only: add-to-cart URL was malformed
  (`course.php?id=N?action=…`, double `?`) — now built via
  `moodle_url()`. Legacy OFF path keeps the quirk for production parity.
- version 2026050601→2026052900, release 1.0.0-beta→1.0.1-beta.
- Visual evidence (ON desktop + ON mobile 590 + OFF legacy) +
  README in `docs/visual-evidence/2026-05-29/`. Flag reverted to
  default OFF after capture.
- Scoping rationale: `docs/audits/C4-CATALOG-NETFLIX-SCOPING-2026-05-29.md`.
