# Chip-M — `sentientia_live` tokens + table a11y (P1 #15 + P2 #22)

**Chip:** `nice-gauss-Jeyou` · **Date:** 2026-05-24

## What changed

Two audit items closed in one chip:

### Item 1 — F-24 / P1 #15: hex literals → tokens

14 hex literals across `local_sentientia_live/templates/` and
`local_sentientia_live/styles/` migrated to `--ap-color-*` tokens.

| Original | Replaced with | Surface |
|----------|---------------|---------|
| `#16a34a` (correct-answer green) | `--ap-color-success` | results table |
| `#dc2626` (incorrect / wrong) | `--ap-color-danger` | results table |
| `#0066A7` (option pill active) | `--ap-color-primary` | option-list |
| `#5a6070` (label muted) | `--ap-color-text-secondary` | metadata rows |
| (10 more) | (matching tokens) | (various) |

### Item 2 — F-25 / P2 #22: table a11y

Every `<table>` rendered by sentientia_live templates now has:
- `<caption>` describing the table's content (visually styled, not
  visually hidden — matches the audit's preference)
- `scope="col"` on every header cell
- `scope="row"` on the first column of each body row where a row-header
  semantics apply
- No `<th>` without an explicit scope

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-live-session.png` | Live session view — top responders table with caption "Top responders so far (Q3 — Multiple choice)", scope-annotated headers, semantic-coloured correctness markers. Below: response-breakdown bars + token-mapping table. |
| `screenshot-desktop-dark.png`         | Same surface, dark mode — tokens flip, semantic colours preserved |
| `screenshot-mobile-live-session.png`  | 590px viewport — table scrolls horizontally; caption stays sticky-readable |

## What to look for

1. **Table caption is rendered, not visually hidden.** Top-left of the
   table, bold, 16px — gives sighted users + screen readers the same
   anchor.
2. **Scope-annotated headers.** `<th scope="col">` for each of the 5
   columns. NVDA reads "Top responders so far (Q3 — Multiple choice),
   5 rows, 5 columns" on table entry.
3. **Correctness markers semantic.** Green ✓ uses
   `color: var(--ap-color-success)`; red ✗ uses
   `color: var(--ap-color-danger)`. Themes correctly in dark mode.
4. **Response-breakdown bars.** Live-update bars use
   `transition: width var(--ap-transition-default)` — picks up
   chip-D's token-driven timing + reduced-motion cascade.

## Acceptance

```bash
grep -rE "#[0-9a-fA-F]{3,6}" local/sentientia_live/templates/ local/sentientia_live/styles/
# expected: zero matches (no hex literals left)

grep -c '<th[^>]*scope=' local/sentientia_live/templates/*.mustache
# expected: every <th> has a scope attribute
```

- ✓ NVDA verification passes (per P2-H rubric)
- ✓ Zero hex literals in plugin's templates / styles
- ✓ All tables have <caption> + scoped headers
- ✓ Hindi pack at parity (table caption strings added)
- ✓ Plugin version bumped to `2026052401`

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` F-24 (P1 #15) + F-25 (P2 #22)
- Companion: chip-M of the earlier wave (sentientia_live aria-live regions, F-19) — same plugin, complementary scope
- Predecessor: chip-E (P0 follow-up — aria-live regions)
- NVDA rubric: `docs/qa/NVDA-VERIFICATION-PROCEDURE.md` (chip P2-H, 589 lines)
