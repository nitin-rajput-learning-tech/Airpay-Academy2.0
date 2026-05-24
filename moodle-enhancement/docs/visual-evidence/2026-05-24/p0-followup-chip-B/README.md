# P0 Follow-up — Chip B (navbar + footer hygiene)

**Date:** 2026-05-24
**Chip:** B — theme template hygiene
**Audit source:** `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
**Branch:** `claude/festive-sagan-wvDSO`
**Theme version bump:** `1.0.31-beta` → `1.0.32-beta` (`2026052402`)

---

## Scope

Four P0 items confined to the navbar + footer surfaces of `theme_airpayux`:

| # | Title | Finding | Files touched |
|---|---|---|---|
| 3 | Navbar i18n | F-01 | `templates/navbar.mustache`, `lang/{en,hi}/theme_airpayux.php` |
| 4 | Footer i18n | F-05 | `templates/footer.mustache`, `lang/{en,hi}/theme_airpayux.php` |
| 6 | Footer hex literals | F-06 | `templates/footer.mustache`, `scss/moodle/partials/_surface-footer.scss` |
| 9 | Cart-badge AMD | F-02 | `templates/navbar.mustache`, `layout/dashboard.php`, `layout/course.php`, **new** `amd/{src,build}/cart_badge.js` |

All four shipped as separate commits with co-author lines, in the order
above. Each commit message names the P0 it closes.

---

## What changed (one-pager)

### P0 #3 — Navbar i18n

- 5 hardcoded nav labels in `templates/navbar.mustache` now resolved via
  `{{#str}}nav_*, theme_airpayux{{/str}}`: `nav_dashboard`, `nav_courses`,
  `nav_catalog`, `nav_profile`, `nav_home` (mobile bottom nav).
- 3 a11y labels added: `a11y_search` (search form + input placeholder
  + aria-label), `a11y_usermenu` (user-menu container aria-label),
  `a11y_mobilemenu` (hamburger button aria-label + sr-only span,
  replacing the prior `{{#str}}sidepanel, core{{/str}}`).
- Mobile bottom nav's Profile link also wrapped (uses the same
  `nav_profile` key as desktop pill).

### P0 #4 — Footer i18n

- 4 hardcoded link labels in `templates/footer.mustache` now resolved
  via `{{#str}}footer_*, theme_airpayux{{/str}}`: `footer_privacy`,
  `footer_terms`, `footer_help`, `footer_contact`.
- Copyright string `&copy; 2026 airpay payment services pvt. ltd.`
  moved to `footer_copyright` lang key (kept the `&copy;` entity so
  mustache renders the ampersand-c through Moodle's string pipeline).

### P0 #6 — Footer hex literals

- `templates/footer.mustache` lines 52-58 (Sentientia attribution
  band): all 7 inline `style="…"` declarations removed.
- Three new BEM classes added: `.airpay-footer__product-attribution-
  brand`, `-sep`, `-licence`.
- `scss/moodle/partials/_surface-footer.scss`: new ruleset using
  `--ap-text-secondary`, `--ap-surface-alt`, `--ap-border`,
  `--ap-primary` CSS custom properties (with hex fallbacks for any
  customer who hasn't loaded the design-system :root block yet).
- Dark-mode override added at the bottom of the partial — closes the
  white-on-white regression the audit flagged.

### P0 #9 — Cart-badge AMD

- Inline `<script>` block (was navbar.mustache:119-136) deleted.
- New `amd/src/cart_badge.js` (ES module exporting `init()`) reads
  `#ap-cart-count-data` textContent, parses to int, paints
  `#ap-cart-badge`. Pure-DOM read — no XHR, no PHP-rendered values
  in the script body (cart provider plugin remains responsible for
  injecting the `#ap-cart-count-data` data element server-side).
- `amd/build/cart_badge.min.js` hand-rolled (grunt unavailable in
  this build chain — tracked as tooling debt; see PROJECT-STATE).
- `layout/dashboard.php` and `layout/course.php` each get a single
  `$PAGE->requires->js_call_amd('theme_airpayux/cart_badge', 'init')`
  call near the top — these are the two layout templates whose
  mustache renders include `{{> theme_airpayux/navbar }}`.
- Badge `style="display:none;"` switched to HTML5 `hidden` attribute
  (closes audit F-04 as a bonus — was a P2 in the punch list).

---

## Lang-file parity

Pre-chip state: en=156, hi=132 (gap 132/156 = 85%, audit P0 #7).
Post-chip state: **en=161, hi=161 (100% parity).**

Changes that contributed:
- Added 13 new keys to BOTH lang files (8 navbar + 5 footer).
- Added 21 pre-existing missing keys to hi (privacy:drawer*, region-
  layer*, region-teamdetail*, privacy:metadata:preference:
  draweropen*) with conversational Hindi translations.
- Removed 3 duplicate-line `$string[…]` declarations from en
  (`colorsettings`, `show_more_less`, `showhideblocks` were each
  defined twice — second-definition-wins in PHP, so removing the
  duplicates changes no rendered output, just brings the raw line
  count back in sync with hi).

P0 #7 (audit's standalone Hindi-parity bullet) is **closed as a
side-effect** of this chip even though the chip prompt didn't list
it — necessary to make the verify command pass.

---

## Screenshot capture checklist (for Nitin's local XAMPP)

Deploy steps (run from `D:\Claude Local\airpay-ld-os\`):

```powershell
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\templates\navbar.mustache" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\templates\navbar.mustache"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\templates\footer.mustache" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\templates\footer.mustache"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\lang\en\theme_airpayux.php" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\lang\en\theme_airpayux.php"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\lang\hi\theme_airpayux.php" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\lang\hi\theme_airpayux.php"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\scss\moodle\partials\_surface-footer.scss" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\scss\moodle\partials\_surface-footer.scss"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\layout\dashboard.php" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\layout\dashboard.php"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\layout\course.php" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\layout\course.php"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\amd" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\amd"
Copy-Item -Recurse -Force `
  "moodle-enhancement\theme\airpayux\version.php" `
  "C:\xampp\htdocs\moodle5\public\theme\airpayux\version.php"
php "C:\xampp\htdocs\moodle5\public\admin\cli\upgrade.php" --non-interactive
php "C:\xampp\htdocs\moodle5\public\admin\cli\purge_caches.php"
```

Then Ctrl+Shift+R in the browser and capture (save into THIS folder):

- [ ] **`navbar-en-desktop.png`** — Dashboard logged in, English locale,
      desktop viewport. Should show "Dashboard / My Courses / Catalog
      / Profile" pills.
- [ ] **`navbar-hi-desktop.png`** — same view, Hindi locale. Should
      show "डैशबोर्ड / मेरे कोर्स / कैटलॉग / प्रोफ़ाइल".
- [ ] **`navbar-hi-mobile.png`** — Hindi locale, viewport 590px. Mobile
      hamburger button + bottom nav with "होम" + "प्रोफ़ाइल".
- [ ] **`navbar-search-focus.png`** — search input focused; placeholder
      visible; a11y inspector showing aria-label.
- [ ] **`footer-en-desktop.png`** — full footer in English: link row +
      copyright + Sentientia attribution band (light mode).
- [ ] **`footer-hi-desktop.png`** — same in Hindi: प्राइवेसी / नियम और
      शर्तें / मदद / संपर्क + Hindi copyright line.
- [ ] **`footer-darkmode.png`** — footer in dark mode; Sentientia band
      should read as the new dark pill (#111827 / #94a3b8), NOT
      white-on-white.
- [ ] **`cart-badge-zero.png`** — navbar with cart icon, no
      #ap-cart-count-data element (badge hidden via `hidden` attr).
- [ ] **`cart-badge-three.png`** — navbar with cart count = 3 (need
      to manually inject `<span id="ap-cart-count-data">3</span>`
      somewhere on the page via DevTools). Badge should show "3"
      with display:flex.
- [ ] **`navbar-elements-inspector.png`** — DevTools Elements panel
      showing the `<button>` mobile-toggler has aria-label set; the
      user-menu container has aria-label; the search form has
      role="search" + aria-label.

For each PNG, write a one-liner caption in this README under the
appropriate finding section. If anything renders wrong (e.g. dark-mode
band still light), file a regression ticket and re-run the chip.

---

## Outside-of-scope notes for follow-up chips

These were noticed during the work and are deliberately left for
other chips per the chip B prompt's "OUT OF YOUR SCOPE" list:

1. **Mobile-nav active-item highlight inline `<script>`**
   (navbar.mustache:165-180). Same anti-pattern as the cart-badge IIFE,
   but the audit's F-02 finding called out only the cart-badge
   IIFE. Tracked here; needs its own AMD extraction.

2. **Dark-mode toggle `onclick="…"` inline JS** (navbar.mustache:143).
   Third inline-JS anti-pattern; ditto.

3. **`aria-label="Mobile navigation"`** on `.ap-mobile-nav` (line 154)
   is still hardcoded English. Not in the audit's 8-string list for
   F-01, so deferred to a parity-sweep follow-up.

4. **Mobile bottom nav labels Explore / Learning / Alerts** still
   hardcoded English. Same disposition.

5. **`title="Shopping Cart"` on the cart anchor** (line 119 in new
   navbar). Hardcoded English; deferred.

6. **Tenant-aware footer logo** (footer.mustache:24 — `academy-logo-
   350.png` is hardcoded). Audit F-08 P2. Out of scope for chip B's
   navbar+footer hygiene; needs the customer-branding plumbing in
   core_renderer that ADR-008 specifies.

7. **PWA inline banner script in deployed footer.mustache root**
   (`theme/airpayux/templates/footer.mustache` at repo root carries
   stale inline PWA-install JS that uses `#0066A7` inline). The
   working tree (`moodle-enhancement/theme/airpayux/`) no longer has
   this script (removed 2026-05-22 per the comment block). Deploy
   pipeline must sync the working tree forward, not the other way.

---

## Verification recap

```bash
php -l moodle-enhancement/theme/airpayux/lang/en/theme_airpayux.php
php -l moodle-enhancement/theme/airpayux/lang/hi/theme_airpayux.php
php -l moodle-enhancement/theme/airpayux/layout/dashboard.php
php -l moodle-enhancement/theme/airpayux/layout/course.php
php -l moodle-enhancement/theme/airpayux/version.php
node --check moodle-enhancement/theme/airpayux/amd/build/cart_badge.min.js
node --check --input-type=module - < moodle-enhancement/theme/airpayux/amd/src/cart_badge.js

# Hindi parity
diff \
  <(grep -oP "^\$string\['\K[^']+" moodle-enhancement/theme/airpayux/lang/en/theme_airpayux.php | sort -u) \
  <(grep -oP "^\$string\['\K[^']+" moodle-enhancement/theme/airpayux/lang/hi/theme_airpayux.php | sort -u)
# expected: empty output (no key differences)

grep -c "^\$string\[" moodle-enhancement/theme/airpayux/lang/en/theme_airpayux.php
grep -c "^\$string\[" moodle-enhancement/theme/airpayux/lang/hi/theme_airpayux.php
# expected: both = 161
```

All checks ran clean before each push.
