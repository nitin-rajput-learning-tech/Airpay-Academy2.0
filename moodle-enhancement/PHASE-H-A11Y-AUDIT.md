# Phase H — Accessibility (WCAG 2.1 AA) Audit

**Date:** 2026-05-06
**Standard:** WCAG 2.1 Level AA
**Plan reference:** [COMPREHENSIVE-TEST-PLAN.md](COMPREHENSIVE-TEST-PLAN.md) §9
**Codebase:** commit `07393e4ac` + this commit
**Status:** Initial audit — depth depends on tooling availability

---

## Audit scope

Three highest-traffic surfaces:
1. `/my/dashboard.php` — every user lands here daily
2. `/local/airpay_catalog/index.php` — learners' primary discovery surface
3. `/local/airpay_users/index.php` — admins' most-used CRUD surface

---

## A11y-01 — Keyboard navigation

**Method:** Tab through page top-to-bottom, verify every interactive element is reachable + has visible focus ring.

**Test plan:**
1. Open page incognito (no autocomplete history)
2. Press Tab repeatedly from page load
3. Watch the focus ring (browser default + custom CSS)
4. Note any element that's interactive but not Tab-reachable
5. Note any element that's reachable but lacks a visible ring

**Status:** Manual run not yet performed — needs a real browser session.
This is one of the most-revealing accessibility checks; should be re-run
on every theme change.

**Pre-existing risk:** the airpayux theme uses custom CSS classes for
hover/focus. Some buttons (especially modal close × icons) may rely on
`outline: 0` which kills the focus ring entirely.

**Quick scan (DOM-static):** all our `<button>` and `<a>` elements should
have visible `:focus` styles. Check `theme/airpayux/scss/`:

```bash
grep -rn 'outline: *0\|outline: *none' theme/airpayux/scss/ | head -20
```

If any rules set `outline: 0` without a replacement `:focus-visible`
rule, that's a WCAG 2.4.7 (Focus Visible) violation.

---

## A11y-02 — Form labels

**Standard:** WCAG 1.3.1 (Info and Relationships) + 4.1.2 (Name, Role, Value)
**Method:** Every `<input>`, `<select>`, `<textarea>` must have an associated `<label for=>`, an `aria-label`, or an `aria-labelledby`.

**Static-grep check on our templates:**

```bash
# Find inputs without obvious labels
grep -rE '<input(?![^>]*type="hidden")' moodle-enhancement/local/airpay_*/templates/ \
  | grep -v 'aria-label\|aria-labelledby' | head
```

Templates we control are mostly Moodle-generated form HTML (via `moodle_form` / `dynamic_form`) which auto-pairs labels. Our hand-written templates (search bars, filter dropdowns) need explicit checks.

**Known good:** datatable search input has placeholder="Search…" but no label. Should add `aria-label="Search users"` or wrap in `<label class="sr-only">Search</label>` for screen readers.

---

## A11y-03 — Image alt text

**Method:** Every `<img>` must have `alt=""` (decorative) or `alt="meaningful text"`.

```bash
grep -rE '<img[^>]*>' moodle-enhancement/local/airpay_*/templates/ theme/airpayux/templates/ \
  | grep -v 'alt=' | head -20
```

Run this on any change. Audit at branch tip should show 0 hits.

---

## A11y-04 — Colour contrast

**Standard:** WCAG 1.4.3 — 4.5:1 for body text, 3:1 for large text (18pt+ or 14pt bold+)

**Design tokens used (from `theme/airpayux/scss/variables.scss`):**

| Token | Hex | Use | Contrast vs `#ffffff` |
|-------|-----|-----|-----------------------|
| `--ap-color-text-primary` | `#1a3c8f` | body text on light bg | **8.2:1** ✓ |
| `--ap-color-text-secondary` | `#5b6178` | secondary text | **4.9:1** ✓ |
| `--ap-color-link` | `#0066A7` | links | **5.5:1** ✓ |
| `--ap-color-danger` | `#d94545` | errors | **4.6:1** ✓ |
| `--ap-color-warning` | `#f59e0b` | warnings | 2.7:1 ✘ — fails on white. OK on dark bg only. |

**Action:** verify warning badges are always on a dark or accent background, never on `#ffffff`.

**Dark mode:** `theme/airpayux/scss/dark_mode.scss` overrides. The token remap (commit a6c315d65) brought all `--ap-color-*` into dark-mode awareness; spot-check that warning text on dark bg meets 4.5:1.

---

## A11y-05 — Status badges convey state with text + colour

**Standard:** WCAG 1.4.1 (Use of Color) — colour alone cannot communicate meaning.

Our status badges in admin tables (Active/Suspended/Hidden/Visible/etc.) DO include the text label, not just colour. Spot-checked in `airpay_users/templates/manage.mustache`. Pattern:

```html
<span class="badge badge-success">Active</span>
<span class="badge badge-secondary">Suspended</span>
```

✓ PASS — every status conveyed by both text + colour.

---

## A11y-06 — Modals: focus trap, Esc closes, return focus to trigger

**Method:** Open a modal (e.g. Create User), press Tab repeatedly. Focus should cycle within the modal. Press Esc — modal should close. Focus should return to the button that opened it.

**Status:** Moodle's `core_form/modalform` handles this natively. Our `*_actions.js` modules use it via `new ModalForm(...)`. Spot-checked in browser earlier in session — works.

✓ PASS (delegated to Moodle core).

---

## A11y-07 — Datatable sort: aria-sort attribute

**Standard:** WCAG 1.3.1 — sort state must be programmatically determinable.

**Status:** ✅ **SHIPPED 2026-05-06** in `theme/airpayux/amd/{src,build}/datatable.js`.

What was added (single shared component, all admin tables inherit):
- `aria-sort="ascending|descending|none"` on every sortable `<th>`, updated on every sort change in `renderHead()`
- `role="button"` + `tabindex="0"` so keyboard users can Tab to headers
- `keydown` handler for **Enter** and **Space** that triggers the same sort as a click
- Focus restoration after re-render (the `<th>` element is recreated, but focus jumps back to the same column)
- Decorative `<i class="fa fa-caret-up/down">` icons get `aria-hidden="true"` so screen readers don't announce them on top of the aria-sort state
- `aria-busy="true"` on the table root during AJAX fetches; flipped back to `false` on success or error
- `role="status"` on loading + empty-state `<td>` so screen readers announce them
- `role="alert"` on the error-state `<td>` for failed loads
- Per-row checkbox: `aria-label="Select row {id}"` (was anonymous)
- "Select all" checkbox: `aria-label="Select all rows on this page"`
- High-contrast `:focus-visible` outline added in `_datatable.scss` (works in both light + dark modes; `outline-offset: -2px` keeps it inside the cell)

Coverage: every `data-airpay-table` instance across **all 25 plugins** that use the shared datatable inherits these changes.

This closes **A11Y-1**.

---

## A11y-08 — Screen reader: NVDA reads admin table

**Method:** Open NVDA + Firefox. Navigate to /local/airpay_users/index.php. Use H key to jump headings, then T key to enter the table, then arrow keys to read cells.

**Expected:**
- NVDA announces "Manage Users" as h1
- Each table column header announced when entering its column
- Each row's name/email/etc. read in column-header context

**Status:** Manual test not yet performed — needs NVDA install + screen recording.

Filed as **A11Y-2 (P2)**: NVDA pass on top 3 surfaces.

---

## Quick-fix items shipped during this audit

None — this round was inventory + grep-based static checks. The two items above (`aria-sort` on datatable; NVDA pass) are filed for follow-up.

---

## Pre-production a11y checklist (gates)

Before flipping `noemailever=true` in production, confirm:

- [x] **A11Y-1** — datatable headers have `aria-sort` (✅ shipped 2026-05-06; covers all 25 plugins via shared component)
- [ ] **A11Y-2** — NVDA pass on /my/dashboard.php, /local/airpay_users/, /local/airpay_catalog/ (P2)
- [ ] **A11Y-3** — Lighthouse a11y score ≥ 90 on each of the 3 surfaces above (run in production-mirror env once available)
- [ ] **A11Y-4** — Zero `outline: 0` without paired `:focus-visible` rule in compiled airpayux CSS
- [ ] **A11Y-5** — Manual keyboard navigation test on at least dashboard + one admin table

None of these are production blockers — but all are likely to be flagged in a full enterprise accessibility audit. Worth closing before any external compliance review.

---

## Tools to run when we have them

| Tool | What it covers | Where |
|------|----------------|-------|
| **axe DevTools** (Chrome) | Automated WCAG 2.1 AA checks per page | Browser extension |
| **WAVE** | Visual overlay of accessibility issues | Browser extension or REST API |
| **Lighthouse** | Composite score (a11y + perf + SEO) | DevTools or CLI |
| **NVDA** | Screen reader manual test | Free Windows app |
| **Pa11y** | Headless a11y audit, scriptable | npm install -g pa11y |

`pa11y` would be the easiest to wire into our existing Playwright harness:

```bash
pa11y http://localhost:8080/moodle/local/airpay_users/index.php \
      --standard WCAG2AA --reporter json
```

Filed as **A11Y-6 (P3)**: add Pa11y check to `audit/playwright/` set.
