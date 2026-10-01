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

## F-12 double-HTML-escaping fixed (2026-09-04)
Course/activity titles containing `& < > ' "` rendered with the entity shown
literally ("AML & KYC Essentials" → "AML &amp; KYC Essentials"). Root cause: a
`format_string()`-produced (already entity-safe) value fed into a Mustache `{{ }}`
auto-escaping field or a PHP `s()`, escaping twice. Found live on the UAT UI/UX pass.

Fixed in the catalog: `templates/mycourses.mustache` (card title/shortname/category
→ `{{{ }}}`), `templates/course_card.mustache` + `templates/catalog.mustache`
(index-view visible title/shortname/summary/category → `{{{ }}}`; aria-labels left
as `{{ }}` — attribute-safe), and `cart.php` (dropped redundant `s()` on
`commerce.php`'s `format_string()`'d values). Companion theme fixes shipped in the
same commit (`theme/sentientia` course-player header/drawer + str-helper params).
`public.php` was already correct (PHP echo, single escape) and is untouched.

Both duplicate catalog trees (`local/` + `moodle-enhancement/local/`) fixed
identically. v1.0.3-beta → **1.0.4-beta / 2026090400**. No XSS (every `{{{ }}}` value
is `format_string()`/`strip_tags` output). Commit 1f8dc0eaf. Deploy alongside the
queued dark-mode opt-in + btn-close fixes. **Not yet visually re-verified on UAT**
(pending deploy).


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031: tenant resolution fails closed

`catalog_manager::viewer_tenant_root()` returned 0 - the site admin's "no filter" value - for an
empty or malformed open_path and for anonymous visitors. A tenantless logged-in learner therefore
browsed every tenant's catalogue and could self-enrol into any tenant's free course through the
cart's "Enrol in all (free)"; a guest could open any tenant's course detail by id.

Now: `TENANT_ALL` (0) only for `tenant::is_cross_tenant()`; a guest or not-logged-in visitor is the
Public tenant (`enrolment::public_tenant_id()`, as `commerce::get_public_catalog()`); anyone else
with no resolvable tenant is `TENANT_UNRESOLVED` (-1) and sees, carts and enrols in nothing
(`assert_course_visible_to_viewer()` refuses; `build_catalog_filter_sql()` gives `1=0`; trending,
new and categories return empty). `enrolment::enrol_now()` never enrols the guest account. cart.php
needs no change: its enrol path goes through the same gate (gating it on `should_offer_oneclick()`
would break the Public /77 free funnel). The categories cache key `cat_t0` is now cross-tenant
viewers only - purge local_sentientia_catalog caches on deploy.

Tests: `tests/tenant_gate_test.php` (`@group tenant_isolation`). 1.0.5-beta / 2026092500 (now
depends on local_sentientia_platform 2026092500). Both trees.

## 2026-09-25 - ADR-031 follow-up: no code change needed

The wave-1 review listed no fix for this plugin. Its items for this group were in
sentientia_courses and block_sentientia_compliance (see those cards). These behaviour changes from
wave 1 still need to be carried to UAT:
- Tenantless logged-in learners, including users created through core signup rather than
  local_sentientia_users/signup.php, see an empty catalogue.
- Guests are limited to /77.
- Purge the local_sentientia_catalog caches on deploy, because the `cat_t0` key used to be shared
  with tenantless viewers.

The run of `tests/tenant_gate_test.php` in the tenant_isolation group on MariaDB and PostgreSQL
has not happened yet.

## 2026-09-30 - Persona pass: catalog mobile fixes (D8, D10, D12)

Bundle "Catalog mobile" from `docs/visual-evidence/2026-09-30/personas/TRIAGE.md`. Fixes to broken
behaviour, so no feature flag and no schema, capability or lang change. 1.0.6-beta / 2026093001.
Both trees.

- D8: `templates/catalog.mustache`. The "Filters & Sort" `<details>` (now `id="ap-catalog-filter-details"`)
  still ships `open`, because on desktop the summary is `display:none` and a closed disclosure would
  hide the filters. At 590px and below an open `<details>` is a fixed bottom sheet, so it covered the
  bottom of the phone on load. An inline script straight after the element sets `open = !matches` for
  `(max-width: 590px)` before first paint and follows the breakpoint on resize and rotation. It must not
  move into the `DOMContentLoaded` block (the sheet would paint open first).
- D12: `templates/course_card.mustache` + `styles.css`. The NEW and completed badges take
  `airpay-catalog__badge--beside-heart`, which offsets them left of the bookmark heart by one heart width
  (`--airpay-catalog-heart-size`, 30px, shared by the heart and the offset). The public storefront badge
  in `public.php` has no heart and keeps the corner.
- D10: `styles.css`. Root cause of the 400px-at-390px overflow: the category tile is a grid item with a
  `nowrap` name, so `min-width:auto` made the single mobile column as wide as the longest category name
  ("AIRPAY PAYMENT SERVICES PRIVATE LIMITED", 338px of text, visible only to the /1 learner). The shell's
  16px gutter at <=768px plus that 372px tile is 400px, and catalog pages set `.ap-shell__content` to
  `overflow: visible`, so nothing clips it. Fix: `min-width:0` on the tile, `minmax(0, 1fr)` category
  tracks at 768px and 590px, and the name wraps at 590px and below (ellipsis stays on desktop).
  Reproduced and cleared in a fixture; the in-app confirmation is the persona re-run of the learner
  `catalog` step at 390px.

Tests: `tests/catalog_mobile_layout_test.php` (7; renders the real templates, reads the real CSS).
Not run under PHPUnit in this pass. Purge the plugin CSS and template caches on deploy (the version
bump does it on upgrade). Evidence: `docs/visual-evidence/2026-09-30/persona-fix-catalog/`.

## 2026-09-30 - persona pass D2: storefront basket hands its paid lines to the order cart (1.0.6-beta / 2026093001)

Persona pass finding D2 (P0): the storefront session basket (`cart.php`) ended every paid course in a
disabled "Payment Coming Soon" button; nothing carried its lines to the order cart
(`local_sentientia_cart`), where billing, the gateway, the invoice and the enrolment on payment live.

- New `classes/checkout_bridge.php`, behind the new default-OFF flag
  `sentientia.catalog.storefront_checkout.enabled` (registered in `db/feature_flags.php`; per tenant or
  customer-wide). `can_hand_off($user)`: real login (never the guest), flag ON for the user's tenant, the
  order cart installed, `local/sentientia_cart:purchase`, and `cart_manager::is_enabled_for_user()`.
  `hand_off($userid)`: each PAID basket line goes through `cart_manager::add_item()`, so the ADR-031
  purchase gate still refuses a course the buyer's catalogue does not show, a course with no enabled
  `enrol_fee` instance is refused rather than priced, and an already-enrolled course is dropped. Added and
  already-enrolled lines leave the basket; refused lines stay. Free lines are never touched.
- `cart.php`: a `checkout` action (sesskey-checked, ignored unless `can_hand_off()`) and a "Proceed to
  checkout" branch that replaces the disabled button only when `can_hand_off()`; the buyer lands on
  `/local/sentientia_cart/checkout.php` with notifications for what moved, what stayed and what was dropped.
  Flag OFF, a guest, a buyer without `:purchase`, or a tenant the cart is off for: `cart.php` renders
  exactly as before (the flag-off path adds one flag lookup for a logged-in buyer with a paid line).
- Two price sources, unchanged: the basket DISPLAYS `course_price_<id>` (catalog config) while the order
  cart CHARGES the `enrol_fee` instance. The buyer sees the order cart's price on the checkout page before
  paying; a course priced only on the storefront is refused. Worth unifying when commerce goes live.
- +6 lang strings (`storefront_checkout_*`, en + hi). README section added. Owner decision (Nitin,
  2026-09-30): bridge built now, stays OFF until the payment gateway sandbox is verified.
- New `tests/storefront_checkout_test.php` (`@group tenant_isolation`, 21 tests): flag registered and
  default OFF, OFF means nobody can hand off, per-tenant scope, capability and `enabled_tenants` gates,
  guest refused, paid lines move and free lines stay, another tenant's course (/1, /177) still refused
  from a /77 basket, a guest-built /77 basket refused for a /1 learner, storefront-only price refused,
  already-enrolled dropped, idempotent, empty/all-free basket, the handed-over cart checks out,
  `next_url()`, `notify()`, `cart.php` wiring, en/hi strings, version. NOT RUN (low-CPU mode); PHPUnit
  needs a re-init first because the plugin version changed.
- Visual evidence NOT captured: the served tree was not touched (low-CPU mode). The flag-ON basket
  (button, hint, and the notifications on the checkout page) needs desktop + 590 px screenshots to
  `docs/visual-evidence/<date>/` before the flag is ever flipped; flag OFF is unchanged.
- Deploy: purge caches (string cache and the feature-flag registry). Both trees.

### 2026-09-30 - persona pass D2 review round 1 (1.0.6-beta / 2026093001, version unchanged)

Adversarial review of the D1/D2 bundle returned fix-then-ship. Closed here:

- MUST FIX, cross-plugin: `hand_off()` reaches `cart_manager::add_item()`, which fataled on an undefined
  function when `local_sentientia_cart/lib.php` was not loaded (only a test pre-include loaded it). Fixed in
  `local_sentientia_cart` (price lookup is now `cart_manager::get_course_price()`; see that card). The
  pre-include is removed from `tests/storefront_checkout_test.php` and a source-level wiring test holds it.
- MUST FIX, test: `test_the_flag_is_per_tenant` asserted `can_hand_off()` for a /1 buyer, but the PHPUnit
  install applies the cart's `enabled_tenants` default ('77,177'), so the cart is off for /1 and the assertion
  failed on the wrong gate. It now pins `enabled_tenants` and uses /177 (flag on) against /77 (flag off).
- MUST FIX, test: `test_next_url_is_checkout_...` compared `moodle_url::get_path()`, which carries the
  wwwroot path (`/moodle/...` under PHPUnit). It now compares `out_as_local_url(false)`.
- `hand_off()`: any `\Throwable` (not only `moodle_exception`) from `add_item()` now refuses THAT line, is
  logged with `debugging()` and the loop carries on, so lines already moved never leave the buyer without a
  message. Test seam: optional `?callable $adder` (defaults to `cart_manager::add_item`); two tests (a
  `RuntimeException` on the middle line, an `\Error`).
- Two price sources (the open item in the D2 entry above): `hand_off()` now compares, for each line it moves, the
  price the basket showed with the price the order cart holds, and reports the difference as
  `pricediffers`; `notify()` adds a warning telling the buyer to check the amounts on the checkout page. The
  line is still moved (the order cart charges its `enrol_fee` price, which the checkout page shows). The
  result array gained that fourth key. NOT unified: which source is authoritative is still an open decision
  to close before the flag is turned on.
- Mixed basket with the flag ON: the free lines stay in the basket and the buyer is sent to the order cart
  checkout page. `notify()` now adds an info notice with the number of free lines left ("open your basket to
  enrol in them"), because "Enroll in All (Free)" is on the basket page. +2 lang strings
  (`storefront_checkout_pricediffers`, `storefront_checkout_freeleft`; en + hi; 8 in total for the bridge).
- Harness: `moodle-enhancement/tools/visual-pass/journeys.json` step #80 (`cart-page-ui`, public77) soft
  check now accepts "Payment Coming Soon" as well as "Proceed to|Checkout", so it passes while the flag is OFF.
- Still open before the flag is ever turned on: desktop + 590 px screenshots of the flag-ON basket (button,
  hint) and of the notifications on the order cart checkout page (CLAUDE.md UI rule); the price-source
  decision above.
- Merge order: this plugin's version 2026093001 collides with the Catalog mobile bundle (D8/D12/D10). The
  merged value must be strictly greater than both.
- New tests: `test_a_price_that_differs_...`, `test_a_throwable_on_one_line_refuses_only_that_line`,
  `test_an_error_on_one_line_is_also_refused_not_fatal`, `test_notify_warns_when_a_price_differs`,
  `test_notify_says_where_the_free_lines_went`, `test_notify_does_not_mention_free_lines_when_...`,
  `test_the_order_cart_prices_a_line_without_lib_php_being_loaded`. NOT RUN (low-CPU mode); the lead re-inits
  PHPUnit once after the bundles merge. Both trees.

## 2026-09-30 - ADR-032 exams code fix 3: the catalog lists ordinary courses only (1.0.7-beta, 2026100100)

`catalog_manager::get_courses()`, `get_trending()`, `get_new()` and `get_categories()` skip courses with
`open_coursetype = 1`. BizLMS stored its online exams and its forums as courses with that value and listed only 0 or
NULL in its own catalog; after the exams import a restored database holds them, and without the condition each would
be offered as a course to enrol in (decision `exams.forum_pseudocourses` = `exclude_from_catalog`). A course with no
`open_coursetype` is ordinary. One constant, `ORDINARY_COURSES_ONLY`.

- A parity fix of what a restored database would otherwise show, so no flag; no schema, capability or string change.
  The queries are the only change, so no screenshots were taken (no template or CSS touched).
- NOT changed, on purpose: `get_in_progress()` (a learner's own enrolments, including an exam they are sitting) and
  `commerce::get_public_catalog()` (its query has no `open_coursetype` reference today, and the column is absent on a
  vanilla schema; guarding it is a separate change). The Public tenant's exam courses would still show on the guest
  storefront, so this is an open item for the storefront.
- Test: `tests/pseudo_course_exclusion_test.php`. NOT RUN (low-CPU mode); the lead re-inits PHPUnit once. Both trees.
- Purge `local_sentientia_catalog` caches on deploy (trending, new_courses and categories are cached).
