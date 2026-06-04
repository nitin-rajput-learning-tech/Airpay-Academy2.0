# local_sentientia_catalog

Learner-facing course catalogue. The Netflix-style tile view with
search, filter, recommended-for-you, and the public-tenant entry point
for non-logged-in browsing.

| Field | Value |
|---|---|
| Component | `local_sentientia_catalog` |
| Version | beta 1.0.0 |
| Depends on | `local_airpay_org` |

## What it does

- Tiled course view at `/local/sentientia_catalog/index.php`.
- Search + category filter + tag filter.
- Tenant-scoped: each visitor sees only the courses their tenant has
  access to.
- Public-tenant variant at `/local/sentientia_catalog/public.php` accessible
  unauthenticated (the marketing-facing entry point).
- Featured carousel pulling from `local_airpay_courses`'s featured list.
- Commerce overlay: courses with a price tag show a "Add to cart"
  button when viewed by a cart-enabled tenant.

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
