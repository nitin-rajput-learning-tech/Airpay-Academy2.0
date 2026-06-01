# State Card — `local_airpay_catalog`

**Component:** `local_airpay_catalog`
**Version:** `2026052902` / `1.0.2-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. Public + learner course catalog surface.
**Last refreshed:** 2026-05-29 (E-01 — one-click free self-enrol for internal tenants; course-card poster thumbnails — `catalog_manager::course_poster()` real-image/gradient-fallback fed into `format_course()`, `commerce::get_public_catalog()`, `course_card.mustache` + `catalog.mustache` + `styles.css`; dark-mode regression-walk fix — Enrol/Continue anchor-buttons re-pinned white)

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
the upstream `local_airpay_courses` cap layer. `enrolment::enrol_now()`
enrols via the core **`manual`** enrol plugin (no new capability), which
is what lets it bypass a self-enrol enrolment key.

## Feature flags

Registered (in `db/feature_flags.php`):
- `sentientia.catalog.public_lxp.enabled` (default **OFF**) — C4 / F-004.
  When OFF, `public.php` renders the legacy plain card grid (today's
  production look, byte-for-byte). When ON, the guest storefront uses
  the member catalog's `airpay-catalog__*` card + carousel language
  ("Popular picks" scroll-snap rail above a searchable/sortable grid).
  Commerce (price, add-to-cart, cart pill) preserved in both modes.
- `sentientia.catalog.free_oneclick_enrol.enabled` (default **OFF**) — E-01.
  When OFF, every free-course "Enroll" button routes through the cart
  (today's behaviour). When ON, a logged-in INTERNAL-tenant user (any
  tenant that is not the Public storefront tenant /77) clicking a FREE
  course is enrolled immediately via the manual plugin (key bypassed),
  no cart step. Public /77 + guests keep the cart; paid always carts.
  **Enable per internal tenant (Airpay /1, ZEEA /177) to activate.**

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

- `tests/enrolment_test.php` (8 cases) — E-01 one-click free self-enrol:
  policy (`should_offer_oneclick`) across internal/Public/guest/paid/flag-off,
  and mechanism (`enrol_now` key bypass, idempotency, paid refusal, manual
  instance self-provision). Uses `local_airpay_core\phpunit\open_path_fixture_trait`.
  (Runs in CI — local XAMPP has no `vendor/bin/phpunit`.)
- `catalog_manager` query correctness is still exercised indirectly by the
  `local_airpay_courses` PHPUnit suite (where the underlying queries live).

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

## E-01 — one-click free self-enrol for internal tenants (2026-05-29)

QA-walk P1 (`docs/qa-walk-2026-05-29/BUG-LOG.md` E-01): Airpay employees
could not self-enrol in "Free" courses.

**Root cause (verified — corrected the bug-log's "no self-enrol / auto-enrol"
guess).** The "Enroll" button routed free courses to `course.php?action=addtocart`
(session cart) and never enrolled. Course 71 *does* have an enabled self-enrol
instance, but with an **enrolment key**, so the cart's `enrollfree` called core
`enrol_self()` which silently no-ops on key-gated courses (`enrol/self/lib.php:171-175`)
yet still reported success. No cross-tenant access hook exists.

**Fix.** NEW `classes/enrolment.php`:
- `should_offer_oneclick($user, $pricing)` — policy: flag ON for the user's
  tenant **and** logged-in non-guest **and** free **and** internal tenant
  (`root > 0 && root !== public_tenant_id`). User-centric (viewer's tenant).
- `enrol_now($courseid, $userid)` — idempotent **manual** enrol that bypasses
  the self-enrol key (self-provisions a manual instance if missing, refuses
  paid courses), mirroring `local_airpay_cart\cart_manager::enrol_user_in_course()`.

Wired into `course.php` (new `action=enrolnow` handler + one-click CTA branch on
the detail page; old `/enrol/index.php` path kept as the non-internal fallback),
`public.php` (grid button → `enrolnow` for internal viewers, both legacy + LXP
paths), and `cart.php` (`enrollfree` rerouted through `enrol_now()` — fixes the
silent-success lie). Behind `sentientia.catalog.free_oneclick_enrol.enabled`
(default OFF). +4 lang strings (`enrol_now_free`, `enrolled_welcome`,
`enrolled_count`, `enrolled_none`) × 5 languages (en/hi/kn/mr/sw).

version 2026052901 → 2026052902, release 1.0.1-beta → 1.0.2-beta.

**Verified.** CLI + real-browser (qa_employee one-click-enrolled courses 71 + 403,
"My Courses" now shows 2 — was the empty-page symptom). 8-case PHPUnit suite +
3 screenshots in `docs/visual-evidence/2026-05-29/enrol-fix-*`. New diagnostics
`tools/enrol-diag.php` (read-only) + `tools/enrol-verify.php` (local-dev-guarded).

**PROD rollout:** deploy files + upgrade + purge, then **enable the flag per
internal tenant** (Airpay /1, ZEEA /177) via the Switchboard.

## LXP storefront ON by default (2026-06-01)
`db/feature_flags.php`: `sentientia.catalog.public_lxp.enabled` default false→true — the
public guest storefront (public.php) now renders the LXP/Netflix card grid + "Popular picks"
rail by DEFAULT (matching the dashboard "Featured for you" poster style), instead of the plain
legacy grid. Reversible + per-tenant overridable via the Switchboard. v1.0.2→1.0.3-beta.
