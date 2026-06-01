# Visual evidence — 2026-06-01

## Catalog storefront footer-overlap fix (theme_airpayux/_layout-shell.scss)

**Before:** `/local/airpay_catalog/public.php` rendered inside the locked app-shell
"cockpit" — `body:has(.ap-shell)` locked to 100vh + overflow:hidden, content trapped in a
~440px internal `.ap-shell__content` scroller, and the 159px three-row footer pinned
(`flex:0 0 auto`) at the viewport bottom. The footer crowded + clipped the last visible
course-card row (enrol buttons hidden) and the admin footnote tagline spilled below — read
as "footer overlapping the cards". Footer painted at y≈506 mid-content.

**Fix:** scoped `body.path-local-airpay_catalog` out of the locked cockpit (height:auto +
overflow:visible across body / #page-wrapper / .ap-shell / .ap-shell__main /
.ap-shell__content) so catalog pages scroll as a normal document and the footer falls at the
END of the content (y≈1923, after all cards). Fixed sidebar + sticky topbar unaffected.
Higher-specificity selectors out-rank the app-shell rules — no !important. Dashboard + other
app-shell pages keep the cockpit model.

**Verified (Chrome MCP, 1440×900):** cards render complete (Free · Details · Enrol all
visible), a second row flows below, zero footer overlap; live geometry probe confirmed the
footer moved from y506 (pinned mid-content) to y1923 (content end), document scrollHeight 2082.
Before/after screenshots captured in-session.
