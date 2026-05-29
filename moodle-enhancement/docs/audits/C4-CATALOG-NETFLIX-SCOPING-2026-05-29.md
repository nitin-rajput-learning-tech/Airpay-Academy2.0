# C4 — Catalog Netflix-UX: scoping finding

**Date:** 2026-05-29
**Audit ref:** `PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` Bucket C / C4 / F-004
**Origin:** User feedback during the original audit — "course catalog
should look like netflix of learning" + dissatisfaction that the
consumer/public learner's catalog felt irrelevant.

---

## TL;DR — C4 is ~80% already done

The **logged-in** LXP catalog is already a full Netflix experience.
The gap is the **guest/public** storefront. C4 is therefore a
*targeted restyle of one page* (`public.php`) to match an LXP
treatment that already exists in the same plugin — **not** a
greenfield "build a Netflix catalog" task, and **not** taste-
ambiguous (the target visual language is already decided).

## What exists today (`local_airpay_catalog`, v1.0.0-beta)

Two distinct catalog entry points:

| Page | Audience | Style today | Verdict |
|------|----------|-------------|---------|
| `index.php` + `catalog.mustache` | Logged-in learners | **Full Netflix-LXP**: scroll-snap carousels, continue-learning rail with SVG progress rings, trending rail, new-courses rail, autocomplete search, bookmark hearts, lazy loading, arrow nav. BEM classes, 1,082-line `styles.css`. | ✅ Already "Netflix of learning" |
| `public.php` | Guests / public learners (no login), with pricing + cart | **Plain inline-styled grid** — `style="max-width:1200px..."`, flat card grid, commerce-focused. No carousels, no rails, no hover treatment. | ⚠️ This is the C4 gap |

The dissatisfaction the user voiced maps precisely to `public.php`:
the *consumer's first impression* catalog is the plain grid, while the
polished Netflix experience is locked behind login.

## The actual C4 task (now precise)

Bring `public.php` up to the `index.php` visual language:

1. **Reuse the card + carousel components.** `catalog.mustache` +
   `partials/` already define `airpay-catalog__card`,
   `airpay-catalog__carousel` (scroll-snap + arrow nav). `public.php`
   should render through these instead of its inline-styled grid.
2. **Guest-appropriate rails.** No "continue learning" (no user), but
   *Featured / Most popular / Newest* rails work for guests, plus the
   category-filtered grid below.
3. **Preserve commerce.** Pricing badges, "Add to cart", cart-count
   pill must survive the restyle — `commerce::get_public_catalog()` +
   `commerce::get_cart_count()` stay the data source.
4. **Responsive + visual evidence.** 590px mobile pass + screenshots in
   `docs/visual-evidence/` per CLAUDE.md.
5. **Feature-flag** the new storefront look (default OFF → current
   plain grid) until signed off, per CLAUDE.md §13.

Estimated effort: **M** (~1 focused session). It's a contained
single-page refactor against existing components, not a design
exploration.

## Why this wasn't built in this session

This is a **consumer-facing production storefront** (the public
course-shopping page with live pricing + cart). A visual overhaul of
the storefront is a product/brand decision that warrants the owner's
greenlight before it ships — even though the target style is already
decided internally. The high-value move this session was to *pinpoint*
the gap (here) so the build, when greenlit, is crisp and low-risk.

## Recommendation

Greenlight the `public.php` → LXP restyle as a single focused chip:
reuse `catalog.mustache` components, guest rails (Featured/Popular/New)
+ filtered grid, preserve commerce, feature-flagged, with mobile +
visual evidence. No new design needed — it's "make the storefront look
like the member catalog we already shipped."

## Cross-reference

- Member catalog (the target style): `local/airpay_catalog/index.php` + `templates/catalog.mustache`
- Guest storefront (the gap): `local/airpay_catalog/public.php`
- Commerce data layer (preserve): `local/airpay_catalog/classes/commerce.php`
- Audit row: Bucket C / C4 / F-004
