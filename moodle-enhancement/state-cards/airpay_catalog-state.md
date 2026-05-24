# State Card — `local_airpay_catalog`

**Component:** `local_airpay_catalog`
**Version:** `2026050601` / `1.0.0-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. Public + learner course catalog surface.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

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

None registered directly. Consumes:
- `ai.recommendations.enabled` (toggles the "For You" recommended feed
  vs. "Trending this week" fallback)

## Key files

```
local/airpay_catalog/
├── version.php                                    2026050601 / 1.0.0-beta
├── README.md
├── lib.php
├── index.php                                       Authenticated learner catalog
├── public.php                                      Unauthenticated public catalog
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
│   └── (no install.xml — read-only plugin)
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
- [ ] Mobile catalog polish (Phase 6B Surface 6)
- [ ] Per-customer "Featured" curation (today: `local_airpay_courses_featured` is a flat list)
- [ ] Course-card visual evidence update — last 2026-04 prototypes

## State card created — 2026-05-24

Initial state card. Plugin has been live since 2026-05-06; created now
as part of the P1 state-card pass.
