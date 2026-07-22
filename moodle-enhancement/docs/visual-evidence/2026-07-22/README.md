# Visual evidence — 2026-07-22 — Signup page UI repair

**Page:** `/local/sentientia_users/signup.php` (public self-registration)
**Reported:** labels wrapping mid-word ("First / name"), required markers rendering as a
blurry red flower, password fields showing "Click to enter text", one white field on the
dark card, stray collapse chevron on the heading.

## Root causes → fixes

| # | Defect | Root cause | Fix |
|---|--------|-----------|-----|
| 1 | Labels wrap mid-word | The stacked-layout CSS (`.signup_form--simple`, `_surface-login.scss:497`) existed but the form never applied the class — narrow 640px card kept the desktop `col-md-3/9` split (116px label col) | `signup_form.php` now adds `signup_form--simple` to the form (`updateAttributes`) — stacked labels engage |
| 2 | Red flower required icon | Theme-wide `core_form/element-template.mustache` hardcoded `{{#pix}}new_req{{/pix}}` → 8×12 legacy BizLMS GIF upscaled | Template now uses core `req` → FontAwesome `fa-circle-exclamation text-danger` (**fixes every mform sitewide**) |
| 3 | "Click to enter text" password | Form used `passwordunmask` (admin-settings widget) | Plain `password` elements + `autocomplete="new-password"` |
| 4 | White email field on dark card | Chrome autofill UA background | `:-webkit-autofill` inset-shadow repaint in dark mode (signup + forgot-password) |
| 5 | Collapse chevron on heading | Collapsible mform header | `setDisableShortforms(true)` |
| 6 | Label text leaking into `class`/`id` attrs | `element-template.mustache` line 46: `class="col-md-3 {{label}}" id="{{label}}"` | Removed (invalid HTML / duplicate ids, sitewide) |
| 7 | Ragged field widths at 576–767px | text inputs honour `size=25` while passwords stretch | Uniform full-width rule in the stacked block |
| 8 | Selects stayed white if only `body` carries `dark-mode` | Dark rule had `select` only under the `html.dark-mode` variant | Added `body.dark-mode#page-signup` variants for text/password/select |

Also fixed in passing: `float-sm-rights` class typo in the element template.

## Files changed

- `local/sentientia_users/classes/form/signup_form.php` (+ `version.php` → 2026072200 / 2.7.2; both duplicate trees synced)
- `theme/sentientia/templates/core_form/element-template.mustache`
- `theme/sentientia/scss/moodle/partials/_surface-login.scss`
- `theme/sentientia/version.php` → 2026072200 / 1.0.47-beta

## Screenshots (Playwright, full-page)

| File | Viewport | Mode |
|------|----------|------|
| `signup-fixed-desktop-1440-light.png` | 1440×1000 | light |
| `signup-fixed-desktop-1440-dark-final.png` | 1440×1000 | dark (html+body class, as production toggle sets) |
| `signup-fixed-mobile-590-light.png` | 590×900 | light |
| `signup-fixed-mobile-590-dark-final.png` | 590×900 | dark |

Verified: browser console 0 errors; honeypot still hidden; stacked layout engaged
(`form.mform.signup_form--simple`, label col full-width); required icon renders as
FontAwesome; passwords are real `input[type=password]`; all fields dark in dark mode
including Country/Language selects. Deployed to local XAMPP (upgrade + purge run).
