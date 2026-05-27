# Chip-P — prefers-reduced-motion stylelint rule

**Chip:** `happy-carson-LxfFQ` · **Merge:** `e01a17df6` · **Date:** 2026-05-24

## What changed

New stylelint `declaration-property-value-disallowed-list` rule in
`theme/airpayux/.stylelintrc.json` scoped to
`scss/moodle/partials/_surface-*.scss`. CI gate catches any inline
transition timing that would bypass the `prefers-reduced-motion` cascade
(WCAG 2.3.3 AAA).

### Rule body

```json
"overrides": [{
  "files": ["scss/moodle/partials/_surface-*.scss"],
  "rules": {
    "declaration-property-value-disallowed-list": {
      "transition-duration": ["/^(?!var\\().+/"],
      "transition":          ["/[0-9]+\\.?[0-9]*(s|ms)/"]
    }
  }
}]
```

### Companion doc

114-line addition to `.claude/rules/frontend.md` documenting:

- The token cascade
- Correct + anti-pattern code samples
- The per-line opt-out (`stylelint-disable-next-line` + WHY comment)
- WCAG 2.3.3 reference link

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-light.png` | Rule body code block + before/after sample cards. The "Active rule applies" card has subtle hover transform; "Reduced-motion media query" card is identical DOM but with 0ms duration. |
| `screenshot-desktop-dark.png`  | Same surface, dark mode — code blocks still legible |
| `screenshot-mobile-light.png`  | 590px viewport — code blocks scroll horizontally |

## What to look for

1. **The rule body.** `transition-duration` only accepts values starting
   with `var(` (i.e., `var(--ap-duration-*)`). `transition` shorthand
   rejects any value containing a numeric+unit pair.
2. **Two-card sample.** The left card uses `var(--ap-transition-quick)`
   and shows the cubic-bezier name. The right card is the "after reduced
   motion" state — same DOM, zero animation time.
3. **Per-line opt-out documented.** For unavoidable inline values
   (polyfill animations the cascade can't reach), the
   `stylelint-disable-next-line declaration-property-value-disallowed-list`
   marker scopes the exception to one declaration.

## Acceptance

```bash
npx stylelint --config theme/airpayux/.stylelintrc.json \
              "theme/airpayux/scss/moodle/partials/_surface-*.scss"
# expected: zero violations after chip-D migrated 54 inline timings
```

- ✓ Rule file parses (`npx stylelint --print-config`)
- ✓ Rule fires on a synthetic violation (CI-tested via fixture)
- ✓ Rule scope limited to `_surface-*.scss` — does not break vendor SCSS
- ✓ Companion doc cross-links the WCAG 2.3.3 reference

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` §2.7 + P2 #19
- Doc: `.claude/rules/frontend.md` — Motion & `prefers-reduced-motion` section
- WCAG: https://www.w3.org/WAI/WCAG21/Understanding/animation-from-interactions.html
- Predecessor: chip-D (`#256 inline-timing → tokens`) — the migration this rule enforces
