# Chip-K — `_surface-login.scss` `!important` refactor (P1 #11 / F-10)

**Chip:** `admiring-knuth-Szn4E` · **Date:** 2026-05-24

## What changed

Three-commit refactor of `theme/airpayux/scss/moodle/partials/_surface-login.scss`.
`!important` count reduced from **23 → 3** (−87%).

### Strategy (3 commits, one per logical scope)

1. **Visual rules** — colour / typography / radius overrides
2. **Layout rules** — flex / grid / spacing overrides
3. **Override rules** — Moodle core `body.path-login` overrides

### Preserved 11 declarations

Each surviving `!important` is preserved with an inline `// chip-K:
preserve — <reason>` comment. Reasons fall into three buckets:

- (5) Override Moodle core's body-class-keyed selector specificity wars
- (4) Inline-style override on a Moodle template we can't edit (form
  autofocus, browser autofill background)
- (2) Vendor SCSS overrides (Bootstrap 5 form-control state)

### Bundled bug-fix

Login page background gradient was using `$ap-primary` / `$ap-primary-dark`
Sass literals instead of `var(--ap-color-primary)` / `var(--ap-color-primary-dark)`
CSS custom properties — broke under tenant-branding override. Fixed in
commit 1 of the chip.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-login-card.png` | Full-bleed Airpay-blue gradient background, centred white login card with brand chip, sign-in form, SSO buttons. Card shadow is `--ap-shadow-lg`. |
| `screenshot-mobile-login-card.png`  | 590px viewport — card scales down; SSO buttons stack but stay readable |

## What to look for

1. **Background gradient lives.** The bug-fix means the gradient now
   uses `var(--ap-color-primary)` → `var(--ap-color-primary-dark)`. Any
   tenant branding override flows through.
2. **Card centring uses flex, not `!important: position`.** Layout
   commit replaced the legacy `position: fixed !important` with
   `display: flex` + `align-items: center` on the body. Cleaner DOM,
   cleaner cascade.
3. **SSO buttons styled as `.btn--outline`.** Inherit the design-system
   button system; no per-page button rules.
4. **Form-control focus state.** Email + password inputs use the
   chip-H sibling pattern — `:focus-visible` with `box-shadow:
   0 0 0 3px var(--ap-color-primary-light)`.

## Acceptance

```bash
grep -c "!important" theme/airpayux/scss/moodle/partials/_surface-login.scss
# expected: 3 (down from 23)
```

- ✓ All 3 commits brace-balanced + lint-clean
- ✓ Tenant branding override reaches the gradient
- ✓ No visual regression vs the audit walk B2 baseline
- ✓ The bundled bug-fix verified at <http://localhost:8080/moodle/login/>

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.2 + P1 #11 / F-10
- Companion: chip-H (P1 #12 :focus-visible coverage) — the focus-ring pattern this chip composes with
- Predecessor: chip-J (`_surface-profile.scss` decomposition) — same `!important` reduction technique
