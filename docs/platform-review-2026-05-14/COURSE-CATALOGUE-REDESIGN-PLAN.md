# Course Catalogue — Redesign Plan

> **STATUS (2026-05-14):** Iter 1 ships the tokens migration. Iters 2–6 sequenced below.

**Surface:** `/local/airpay_catalog/index.php`
**Status:** EXISTS · **Priority:** P0 (#2 on the 7-redesign list) · **Effort:** M (3-6 sessions)
**Template:** `moodle-enhancement/local/airpay_catalog/templates/catalog.mustache` (290 lines)
**Course card partial:** `templates/course_card.mustache` (61 lines — distinct from the dashboard's `course_progress_card`)
**CSS:** `local/airpay_catalog/styles.css` (490 lines, 87 hex literals)
**Data layer:** `classes/catalog_manager.php`

---

## 0. Why this is #2

The Course Catalogue is the **discovery surface** — the first interaction every learner has before they're enrolled in anything. SURFACE-ROADMAP §1.3 called it out as P0 because:

- 87 hex literals across 490 lines of CSS — tenant branding doesn't propagate, dark mode is manually overridden per rule
- Mobile responsiveness is partial (one `@media (max-width: 590px)` block at the bottom — most layouts don't adapt)
- Search is keyword-only (no facets, no recent searches, no "did you mean")
- Three carousels (In Progress / Trending / New) stack vertically with no end-of-carousel-scroll affordance
- Filter chips ("Active category") use a separate hex-literal pill style
- No mobile-native bottom sheet for filters
- Course card has hover-only metadata (the summary appears on hover, invisible on touch devices)

### What's already-correct (don't break)

- The IA already has semantic clustering (In Progress / Trending / New / All) — the SURFACE-ROADMAP claim that the catalogue was "a long flat list" is outdated
- Tenant scoping via `open_path` works correctly (Sprint C proved this end-to-end)
- Provenance badges for cross-tenant shared courses are wired (Sprint D)
- The bookmark-heart button has a working data-courseid binding (verified via JS hook elsewhere)
- Pagination at 12 per page is functional

---

## 1. Redesign principles (from UI-UX-MANIFESTO)

1. **Mobile is the canonical surface** (§1.1) — the carousel sections work on mobile via horizontal scroll, but the filter bar above the "All Courses" grid currently doesn't collapse into a bottom sheet. Iter 4 will fix this.
2. **Touch-first** (§1.2) — every chip + card needs ≥44pt tap target. The current sort tabs are 14px×40px (too short).
3. **Content is the interface** (§1.3) — the hover overlay with "View details" CTA is invisible on touch. Replace with a persistent CTA on the card body (the data is already there).
4. **Motion is meaning** (§1.4) — current transitions use `0.25s` raw values. Migrate to `--ap-transition-*` tokens so reduced-motion auto-respects.
5. **Empty states are scenes** (§1.5) — the catalog has zero empty-state handling when search returns nothing. "No courses found for 'compliance'" should be an inviting scene, not a blank section.

---

## 2. The iterations

### Iter 1 — tokens migration (this commit)

- `styles.css`: 87 hex literals → 0. Replace every hex/rgb literal with the matching semantic token. Same visual treatment for now — no design changes.
- Inline `<style>` block in `mycourses.mustache`: deferred to iter 2 (separate template file = separate review surface)
- A11y improvements to `catalog.mustache`: search form gets `role="search"`, sort tabs get `aria-pressed`, filter chip removal gets a proper aria-label
- Plan doc (this file)

Risk: low. Zero visual change — just swapping `#0066A7` for `var(--ap-color-primary)`.

### Iter 2 — mycourses inline-style extraction

- Move the inline `<style>` block at end of `mycourses.mustache` (lines 101+) into a proper plugin CSS file
- Migrate to tokens at the same time
- Rename `.ap-mycourses__*` → `.airpay-mycourses__*` for BEM consistency (optional, keep alias)
- Audit whether the mycourses card can adopt `course_progress_card` (deferred from sweep batch 4 — the rich variant with progress ring might not fit cleanly)

### Iter 3 — search bar + filter chip reusables

- Extract `templates/components/search_bar.mustache` from catalog.mustache lines 5-25
- Extract `templates/components/filter_chip.mustache` from the "Active category" pill pattern
- Add focus-visible + 44pt tap targets

### Iter 4 — mobile filter bottom sheet

- The current filter bar inline-stacks on mobile, taking up half the viewport before the user sees any courses
- Build a `templates/components/filter_sheet.mustache` that's a bottom sheet on mobile, a sidebar on tablet+, a top bar on desktop
- Manifesto §4.2 spec — sheet replaces modal on mobile

### Iter 5 — card hover/touch parity

- The current `.airpay-catalog__card-overlay` shows summary + CTA on `:hover` only — invisible on touch devices
- Replace with: persistent micro-summary line on the card body + tap-anywhere navigation
- The hover state can keep the "lift" but not the content reveal

### Iter 6 — empty states + skeleton loading

- Add empty states for:
  - Search returns nothing
  - All filters return nothing
  - First-visit learner (no in-progress / no enrollment history)
- Add skeleton loaders for the carousels while data fetches (currently shows nothing then pops in)

---

## 3. What we're NOT changing

- The IA / sections (In Progress / Trending / New / All) — already correct
- The data layer (`catalog_manager.php`) — proven over multiple sprints, tenant scoping is solid
- The bookmark-heart functionality — separate plugin concern
- The pagination + filter state encoding in URL — the existing `?q= &category= &sort= &page=` works
- The provenance badge for cross-tenant courses — Sprint D contract

---

## 4. Files (current state)

```
local/airpay_catalog/
  index.php                     [data → template]
  styles.css                    [490 lines, 87 hex] ← iter 1 target
  templates/
    catalog.mustache            [290 lines — main page]
    course_card.mustache        [61 lines — rich card with hover overlay]
    mycourses.mustache          [210 lines with INLINE <style>] ← iter 2 target
```

---

## 5. References

- `docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md` — design principles
- `docs/platform-review-2026-05-14/SURFACE-ROADMAP.md` §1.3 — surface spec
- `docs/platform-review-2026-05-14/LEARNER-DASHBOARD-REDESIGN-PLAN.md` — the proven pattern this plan follows
- `moodle-enhancement/theme/airpayux/scss/moodle/_tokens.scss` — token source
- `moodle-enhancement/local/airpay_core/admin/styleguide.php` — live token reference
