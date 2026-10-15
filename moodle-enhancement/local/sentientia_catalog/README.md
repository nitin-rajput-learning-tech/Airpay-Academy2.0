# local_sentientia_catalog

Learner-facing course catalogue. The Netflix-style tile view with
search, filter, recommended-for-you, and the public-tenant entry point
for non-logged-in browsing.

| Field | Value |
|---|---|
| Component | `local_sentientia_catalog` |
| Version | beta 1.0.0 |
| Depends on | `local_sentientia_org` |

## What it does

- Tiled course view at `/local/sentientia_catalog/index.php`.
- Search + category filter + tag filter.
- Tenant-scoped: each visitor sees only the courses their tenant has
  access to.
- Public-tenant variant at `/local/sentientia_catalog/public.php` accessible
  unauthenticated (the marketing-facing entry point).
- Featured carousel pulling from `local_sentientia_courses`'s featured list.
- Commerce overlay: courses with a price tag show a "Add to cart"
  button when viewed by a cart-enabled tenant. A course's price is its enabled
  `enrol_fee` instance (cost and currency), the same cost the order cart charges
  (`commerce::get_course_price()`); the `course_price_<id>` setting is only a
  fallback for a course with no fee instance. A course priced by either is never
  enrolled through the free path (`enrolment::enrol_now()` refuses it, and also
  asks the order cart for its price when `local_sentientia_cart` is installed).

## Storefront basket to order cart (flag, default OFF)

The storefront basket (`cart.php`, kept in the session) holds courses before
anyone logs in. For a paid course it has always ended in a disabled "Payment
Coming Soon" button. With `sentientia.catalog.storefront_checkout.enabled` ON
(per tenant or customer-wide from the Switchboard), a logged-in buyer who holds
`local/sentientia_cart:purchase`, in a tenant the cart is enabled for
(`enabled_tenants`), sees "Proceed to checkout" instead.
`classes/checkout_bridge.php` hands each PAID line to
`\local_sentientia_cart\cart_manager::add_item()` and redirects to
`/local/sentientia_cart/checkout.php`:

- The order cart decides, not the storefront. `add_item()` applies the ADR-031
  catalogue purchase gate (a course the buyer's own catalogue does not show is
  refused), the price (the enabled `enrol_fee` instance; a course priced only by
  the `course_price_<id>` setting is refused, never given a price) and "not
  already enrolled". `checkout()` and `mark_paid()` repeat the purchase gate later.
- A refused line stays in the basket and the buyer is told how many. A line for a
  course the buyer is already enrolled in is dropped from the basket. Free lines
  are never touched (they enrol through "Enroll in All (Free)"); after a hand-off
  the buyer is told how many free lines are still in the basket. An unexpected
  error on one line (not only a `moodle_exception`) refuses just that line.
- One price source (owner decision `cart.price_source`, 2026-10-07): the basket
  shows the enabled `enrol_fee` cost, the same the order cart charges; the
  `course_price_<id>` setting is only a fallback for a course with no fee
  instance. If the fee changed after a line went into the basket, the buyer gets
  a warning to check the amounts on the checkout page.
- Flag OFF, a guest, a buyer without `:purchase`, or a tenant the cart is off
  for: `cart.php` renders exactly as before.
- Keep it OFF until the payment gateway has been verified in sandbox.

## Request enrolment (flag in `local_sentientia_request`, default OFF)

With `sentientia.request.catalog_button` ON, a course card (`course_card.mustache`) and `course.php` show
**Request enrolment** (or **Request pending**) in place of Enroll for a learner whose only way in is a request:
a free course the one-click route does not enrol at once, not enrolled, in their tenant scope. An enabled
self-enrolment instance (open or keyed) is not a way in: the theme sends `/enrol/index.php` to `/my`, so the learner
cannot reach it.
The rule lives in `\local_sentientia_request\catalog_cta`; this plugin adds its own routes first
(`enrolment::request_state()`: a paid course goes to the cart, a free course offered one-click enrolment is
enrolled at once) and marks the cards after the cached lists are read (`catalog_manager::with_request_offers()`,
called by `index.php`), so the flag and a just-submitted request show on the next load. The click is handled by
`local_sentientia_request/request_button`, loaded once per page and only when a card offers it. Optional: without
`local_sentientia_request`, or with the flag OFF, nothing changes. `public.php` (the guest storefront) is not touched.

## Tables

None of its own — reads from `mdl_course`, `mdl_tag`, plus the airpay
plugins.

## Verify after install

Navigate to `/local/sentientia_catalog/index.php`. Twelve to twenty course
tiles should render within two seconds on a warm cache.

## Phase 7 UAT alignment

Case C.2 in `uat_phase7_multirole.mjs` checks that twelve course tiles
render on this surface for every persona.

## Privacy / GDPR

Privacy provider exists but holds no user data; the catalogue is read-only
discovery.

## Open backlog

- Faceted search (currently single-term).
- Personalised "Recommended for you" — the row is hardcoded today.
