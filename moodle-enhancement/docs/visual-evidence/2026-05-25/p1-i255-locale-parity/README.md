# #255 / P1 — Locale parity restored to 178/178 (kn / mr / sw)

**Chip:** `lucid-dirac-kj3pj` · **Merge:** `66c794e71` · **Date:** 2026-05-24

## What changed

Closed the 13-key gap that chip-B's nav/footer i18n additions opened in
the non-EN/HI locales. EN + HI + KN + MR + SW now all sit at byte-aligned
**178 keys** with brand-name transliterations matched to the upstream
`choosereadme` convention (पेमेंट सर्विसेज, ಪೇಮೆಂಟ್, etc.).

### Keys added per locale

| Locale | Before | After | Net adds |
|--------|-------:|------:|---------:|
| kn (ಕನ್ನಡ)    | 165 | 178 | +13 |
| mr (मराठी)   | 165 | 178 | +13 |
| sw (Kiswahili) | 165 | 178 | +13 |

Most additions: `nav_*` (5 navbar labels), `footer_*` (4 footer links),
`a11y_search` / `a11y_usermenu` / `a11y_mobilemenu`, `footer_copyright`.

## Screenshots

| File | What to look for |
|------|------------------|
| `screenshot-desktop-five-locales.png` | Five cards side-by-side showing navbar / footer / copyright rendered in EN, HI, KN, MR, SW with green `178/178` parity badges |
| `screenshot-mobile-stacked.png` | Same five cards stacked vertically on 590px viewport — Kannada/Marathi diacritics legible at mobile size |

## What to look for

1. **All five locales show `178/178`.** Green parity badge on every card.
2. **Brand transliterations are correct.** "airpay payment services" →
   `एयरपे पेमेंट सर्विसेज` (hi), `ಏರ್‌ಪೇ ಪೇಮೆಂಟ್ ಸರ್ವಿಸಸ್` (kn),
   `एअरपे पेमेंट सर्व्हिसेस` (mr). Kiswahili keeps the latin brand-name
   per Tanzanian/Kenyan business-naming convention.
3. **Diacritics legible at mobile size.** Devanagari + Kannada glyphs
   should not be clipped or rendered as boxes at 590px.

## Acceptance

```bash
diff <(grep -oP "^\$string\['\K[^']+" lang/en/theme_airpayux.php | sort) \
     <(grep -oP "^\$string\['\K[^']+" lang/hi/theme_airpayux.php | sort)
# expected: empty
diff <(grep -oP "^\$string\['\K[^']+" lang/en/theme_airpayux.php | sort) \
     <(grep -oP "^\$string\['\K[^']+" lang/kn/theme_airpayux.php | sort)
# expected: empty
# (repeat for mr + sw)

grep -c "^\$string\[" lang/{en,hi,kn,mr,sw}/theme_airpayux.php
# expected: all = 178
```

## Refs

- Audit: `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` P0 #7 (Hindi parity) extended
- Predecessor: chip-B (navbar + footer i18n additions, opened the gap)
