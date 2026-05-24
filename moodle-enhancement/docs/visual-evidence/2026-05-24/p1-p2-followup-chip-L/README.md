# P1 #14 + P2 #21 follow-up — chip L (2026-05-24)

**Chip:** `claude/determined-feynman-rcJw1`
**Auditor:** Claude Opus 4.7 (1M context)
**Closes:** F-07 (P1 #14) + F-09 (P2 #21) from
`docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
**Scope owner:** Footer mobile responsiveness + template comment hygiene
**Conflict avoidance:** Chip B (`claude/festive-sagan-wvDSO`) concurrently
edits the same two files (P0 #3, #4, #6, #9). The two chips were
designed to compose: chip B adds new BEM classes
(`.airpay-footer__product-attribution{-brand,-sep,-licence}`) and i18n
`{{#str}}` helpers; chip L (this) adds the mobile breakpoint that
targets those classes and removes the 2026-05-15 fact-of-history
comment block. Both branches reach 1.0.3?-beta independently; the
higher version wins on integration.

---

## Commits

```
f2926457a  feat(scss): footer mobile breakpoint at 590px (P1 #14)
a6a0da86b  chore(template): delete removed-badge comment block in footer.mustache (P2 #21)
????????   chore(theme): bump theme_airpayux to 1.0.33-beta + P1 #14 / P2 #21 evidence
```

---

## Item 1 — `_surface-footer.scss`: 590px breakpoint (F-07 / P1 #14)

### What changed

Added one new `@media (max-width: 590px)` block after the existing
`@media (max-width: 768px)` block. The 768px block remains untouched —
it provides the intermediate stacking step. The 590px block tightens
spacing further for Galaxy-S / iPhone SE sized viewports and adds the
Sentientia attribution band rules that depend on the BEM class Chip B
introduced in commit `68bc10536` (still on `festive-sagan-wvDSO`).

### Rules added (8 declarations across 5 selectors)

| Selector | Property | Value | Rationale |
| --- | --- | --- | --- |
| `.airpay-footer__compact` | `flex-direction` | `column` | redeclared from 768px block — defensive |
| `.airpay-footer__compact` | `align-items` | `flex-start` | redeclared |
| `.airpay-footer__compact` | `flex-wrap` | `wrap` | satisfies audit literal requirement |
| `.airpay-footer__compact` | `gap` | `var(--ap-space-3, 12px)` | tighter than 768px's 12px (token) |
| `.airpay-footer__compact` | `padding` | `var(--ap-space-4, 16px) var(--ap-space-5, 20px)` | trims from 768px's 20px |
| `.airpay-footer__logo img` | `height` | `28px` | down from base 32px so brand doesn't dominate |
| `.airpay-footer__links` | `margin-left` | `0` | already in 768px; defensive |
| `.airpay-footer__links` | `flex-wrap` | `wrap` | already in 768px; defensive |
| `.airpay-footer__links` | `gap` | `var(--ap-space-3, 12px)` | tighter than 768px's 14px |
| `.airpay-footer__copy` | `order` | `99` | renders last in column stack |
| `.airpay-footer__copy` | `white-space` | `normal` | clears base rule's `nowrap` |
| `.airpay-footer__copy` | `line-height` | `1.4` | comfortable wrap density |
| `.airpay-footer__product-attribution` | `padding` | `var(--ap-space-2, 8px) var(--ap-space-3, 12px)` | down from desktop 8px/16px |
| `.airpay-footer__product-attribution` | `font-size` | `var(--ap-text-xs, 0.75rem)` | same as desktop; explicit |
| `.airpay-footer__product-attribution` | `line-height` | `1.5` | accommodates wrap |
| `.airpay-footer__product-attribution` | `word-wrap` | `break-word` | future white-label brand-name safety |

Tokens are CSS custom properties referenced via `var(--ap-name,
fallback)` so the rule still produces sensible output even if
`_tokens.scss` is loaded after this partial in the SCSS chain.

### Why 590px (not 768px / 480px / 380px)?

Per `.claude/rules/frontend.md` §Responsive Breakpoints:
- 590px is **the primary mobile target** — the breakpoint Nitin tests
  against in Chrome DevTools after every theme deploy.
- 480px and 380px are smaller-mobile-only refinements.
- 768px is tablet-portrait.

The audit (F-07) explicitly named 590px as the missing breakpoint and
the chip prompt re-iterated it. By landing rules at 590px we cover
both 590px and 480px / 380px (which inherit via the cascade) and we
respect the existing 768px tablet rules above it.

---

## Item 2 — `footer.mustache`: delete removed-badge comment (F-09 / P2 #21)

### What changed

Deleted lines 33-42 (a 10-line Mustache comment block) explaining the
2026-05-15 removal of the "Made in India" badge from the admin footer.

### Why it goes

Per audit verdict (`F-09`): the comment documents **history of a
change**, not the **rationale of a current line of code**. Future
template readers don't need to know why a badge was removed last year
to understand the current footer. The git log of `footer.mustache`
preserves the original removal commit (visible via `git log --follow
moodle-enhancement/theme/airpayux/templates/footer.mustache`), so no
information is lost — only template noise is reduced.

### Lines removed (verbatim)

```mustache
        {{!
            2026-05-15: removed the "Made in India" badge from the admin
            footer. Original placement collided visually with adjacent dark
            page content (e.g. table headers) on /local/airpay_users/,
            Manage Courses, and other admin lists — the badge image carries
            its own light pill background that read as a foreign element
            inside the enterprise admin chrome. The badge still ships on
            the public homepage (theme/airpayux/layout/frontpage.php:671)
            where it has dedicated space.
        }}
```

---

## Test procedure for Nitin (5 minutes)

These steps don't run in the chip's container (Linux, no XAMPP). Nitin
runs them on the dev workstation.

### Pre-flight

```powershell
# 1. Pull the chip-L branch
git fetch origin claude/determined-feynman-rcJw1
git checkout claude/determined-feynman-rcJw1

# 2. Deploy SCSS + template to XAMPP
Copy-Item "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\scss\moodle\partials\_surface-footer.scss" `
          "C:\xampp\htdocs\moodle5\public\theme\airpayux\scss\moodle\partials\_surface-footer.scss" -Force
Copy-Item "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\templates\footer.mustache" `
          "C:\xampp\htdocs\moodle5\public\theme\airpayux\templates\footer.mustache" -Force
Copy-Item "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\version.php" `
          "C:\xampp\htdocs\moodle5\public\theme\airpayux\version.php" -Force

# 3. Purge caches (forces SCSS recompile)
php "C:\xampp\htdocs\moodle5\public\admin\cli\purge_caches.php"

# 4. Hard refresh in the browser (Ctrl+Shift+R) — clears compiled CSS
```

### Test matrix — Chrome DevTools device toolbar

Open Chrome DevTools (`F12`) → Toggle device toolbar (`Ctrl+Shift+M`)
→ pick "Responsive" mode and walk the breakpoints.

| Viewport | Expected behaviour | Pass / Fail |
| --- | --- | --- |
| **1200px** | Compact row: logo left, links right, copy at far right, all inline. Attribution band centered single line. | ☐ |
| **992px** | Same as 1200px (no breakpoint between). | ☐ |
| **768px** | Compact row collapses to column: logo on top, links below, copy below. Attribution band still centered single line. | ☐ |
| **590px** | Column stack persists. Logo size drops to 28px tall. Copy line allowed to wrap if needed (no horizontal scrollbar). Attribution band padding tighter (8px/12px instead of 8px/16px). | ☐ |
| **480px** | Cascade from 590px. Copy + attribution band render cleanly without horizontal overflow. | ☐ |
| **380px (Galaxy S)** | Cascade from 590px. Brand "Sentientia LMS" + separator + "Licensed under GPL v3" still fit; if not, the spans wrap onto separate lines (word-wrap: break-word). | ☐ |

### Visual-comparison checklist

- [ ] At 590px, the **copyright line wraps** if width permits — no horizontal
      scrollbar at any point in the range 590px → 320px.
- [ ] The Sentientia **attribution band's three spans** (brand · separator ·
      licence) stay readable; if they wrap, they stack neatly with no overlap.
- [ ] The 2026-05-15 **"Made in India badge removal" comment** is gone from
      View Source of the rendered footer (`<!-- ... 2026-05-15 ... -->`
      should NOT appear anywhere on /my/dashboard.php or /).
- [ ] Browser console: **zero JS errors**, **zero CSS warnings**.

### Conflict-check with Chip B

Chip B (`festive-sagan-wvDSO`) replaces the inline-style Sentientia
band with BEM classes (`.airpay-footer__product-attribution-brand`
etc.) and adds the band's base SCSS rules to the same partial. After
Nitin merges **both** chips to `production`:

- [ ] At desktop (1200px), the attribution band still renders the
      light pill (`background: var(--ap-surface-alt)`) with primary-blue
      brand text — Chip B's tokens applied.
- [ ] At 590px, the attribution band padding tightens to chip L's
      values (8px / 12px) — chip L's media query overrides chip B's
      base rule, as designed.

---

## Spec citations

- `.claude/rules/frontend.md` §Responsive Breakpoints — primary mobile target 590px
- `.claude/rules/frontend.md` §Anti-patterns — hardcoded comments that bloat templates
- `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` F-07 (P1 #14), F-09 (P2 #21)
- `docs/CONTRIBUTING-PARALLEL-SESSIONS.md` §1 — one-touch ownership per chip
- `docs/CONTRIBUTING-PARALLEL-SESSIONS.md` §3 — PROJECT-STATE.md append at end

---

## Files touched

| File | Change | Reason |
| --- | --- | --- |
| `theme/airpayux/scss/moodle/partials/_surface-footer.scss` | +50 lines (new 590px media block) | P1 #14 / F-07 |
| `theme/airpayux/templates/footer.mustache` | -10 lines (delete comment block) | P2 #21 / F-09 |
| `theme/airpayux/version.php` | +18 lines (version bump rationale comment) | trigger SCSS recompile |
| `docs/visual-evidence/2026-05-24/p1-p2-followup-chip-L/README.md` | new file | this document |
| `moodle-enhancement/PROJECT-STATE.md` | +1 H2 section (appended at end) | session log per §3 of CONTRIBUTING |

No PHP code touched → no `php -l` blocker. `version.php` was the only
PHP file and it lints clean (verified in container).

---

## Screenshot status

**No screenshots in this evidence dir.** The chip runs in a Linux
container without XAMPP / Chrome; visual capture is Nitin's task using
the test procedure above. When captures are taken, drop them in this
directory as:

- `footer-590px-before-chip-L.png` — at 590px BEFORE applying chip L
- `footer-590px-after-chip-L.png` — at 590px AFTER applying chip L
- `footer-380px-after-chip-L.png` — at 380px AFTER applying chip L
- `footer-1200px-after-chip-L.png` — desktop sanity check (should be unchanged)

These four shots close the visual-evidence loop. Until they land,
this README is the audit trail.
