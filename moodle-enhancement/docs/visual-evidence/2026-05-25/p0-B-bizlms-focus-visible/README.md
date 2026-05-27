# P0-B — `_bizlms-admin.scss` :focus-visible siblings

**Chip:** P0-B / `loving-noether-KKixA` · **Merge:** `79f2de14` · **Date:** 2026-05-24

## What changed

41 new lines on `theme/airpayux/scss/moodle/partials/_bizlms-admin.scss` —
7 new `:focus-visible` rule blocks covering 13 interactive selectors
that previously had only `:hover` declarations:

1. `.ap-static-page-nav__link`
2. `.ap-sp-qlink`
3. `.ap-static-toc__link`
4. `#region-main .nav-tabs .nav-link`
5. `#region-main .tab-pane.active .col-sm-3 ul a, .col-sm-6 ul a`
6. `#region-main .form-control, input, textarea, select` (byte-identical
   sibling to existing `:focus` rule — mirrors Chip H pattern; preserves
   form-control focus behaviour verbatim)
7. `#region-main button.btn-primary, input[type=submit].btn-primary, .btn-primary`

Rules 1–5 + 7 use the WCAG 2.4.7 outline pattern:
```scss
outline: 2px solid var(--ap-color-primary, #0066A7);
outline-offset: 2px;
border-radius: var(--ap-radius-sm, 8px);
```

Pure additive change — zero existing rule declarations modified. SCSS
brace balance: 339 open == 339 close.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-default.png` | No focus rings — mouse-only navigation, focus suppressed (Tab key not yet pressed) |
| `screenshot-desktop-tab-focus.png` | Side-nav "Compliance Reports" link wrapped in a 2px Airpay-blue outline — keyboard Tab landed on it |
| `screenshot-desktop-input-focus.png` | Form-control "Course code" input shows the byte-identical `:focus` sibling (border-color + box-shadow + outline:none) |
| `screenshot-mobile-default.png` | Mobile 590px viewport — same surface, navbar hamburger active, layout stacks |

## What to look for

1. **No focus on mouse click** — desktop-default should show clean cards with
   zero outline halos. `:focus-visible` (vs `:focus`) suppresses focus on
   pointer interactions.
2. **Strong outline on keyboard focus** — desktop-tab-focus shows the WCAG
   2.4.7 2px outline + 2px offset. Outline colour is `var(--ap-color-primary)`
   (#0066A7) so it inherits tenant branding overrides automatically.
3. **Byte-identical input sibling** — desktop-input-focus uses the existing
   `:focus` rule's border-color + box-shadow (not the 2px outline) because
   form controls already had a token-driven focus state that doesn't need
   the outline pattern.

## Acceptance

- ✓ ≥4 new :focus-visible rule blocks → 7 shipped
- ✓ Mustache balance 0 unbalanced → 0 .mustache files modified
- ✓ No non-focus regression → pure insertions; brace-balanced
- ✓ php -l on changed PHP → version.php clean

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.6 (WCAG 2.4.7) + F-17
- Tokens consumed: `--ap-color-primary` (`_tokens.scss:81`), `--ap-radius-sm`
  (`_tokens.scss:157`)
- Predecessor: Chip H — `_surface-courses.scss` :focus-visible pattern
