# Visual evidence — P1 follow-up chip H

**Date:** 2026-05-24
**Chip:** `claude/inspiring-mayer-kWs9O`
**Scope:** Audit §2.6 — `:focus-visible` coverage across surface partials
**Audit refs:** F-03 (navbar), F-11 (login), F-17 (profile), F-19 (course)
**Commits:** `4552b85d` `f8d4ba28` `c4787fa0` `c49e6396` `7ffbafb5` + version bump

---

## What changed

Added `:focus-visible` sibling rules adjacent to every bare `:focus`
rule across the five surface partials. The legacy `:focus` rule is
retained as a fallback. 22 new `:focus-visible` selectors / 10 rules
added in total.

| Partial | Rules | Selectors |
|---------|-------|-----------|
| `_surface-navbar.scss` | 1 | 1 |
| `_surface-dashboard.scss` | 1 | 2 |
| `_surface-login.scss` | 2 | 6 |
| `_surface-course.scss` | 3 | 3 |
| `_surface-profile.scss` | 3 | 10 |

---

## Why it matters

`:focus` matches on **every** focus event — keyboard tab navigation
AND mouse click. `:focus-visible` only matches when the browser
decides the focus is the result of keyboard interaction (the heuristic
factors in input modality + element type — buttons get no ring on
mouse-down, text inputs always do, etc.).

Two WCAG criteria are at stake:
- **WCAG 2.1.1 — Keyboard:** users navigating by keyboard MUST get a
  visually obvious focus indicator on every interactive element.
- **WCAG 2.4.7 — Focus visible:** the indicator must be obvious to
  someone using the keyboard, but should NOT trigger phantom flashes
  on mouse click for sighted-mouse users.

Bare `:focus` violates 2.4.7 (phantom ring on click). Bare
`:focus-visible` would risk 2.1.1 on older browsers. Belt-and-braces
(`:focus` + `:focus-visible` with the same declarations) gets us both
modern correctness and legacy fallback in one swoop.

---

## Test procedure (manual)

These are the test steps a reviewer should run on a local Moodle
running the airpayux theme with version `2026052402+`. The cache
purge after deploying the new theme is mandatory — without it the
compiled CSS is stale.

```powershell
# Deploy + purge (run before testing)
Copy-Item "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux\*" `
          "C:\xampp\htdocs\moodle5\public\theme\airpayux\" -Recurse -Force
php "C:\xampp\htdocs\moodle5\public\admin\cli\purge_caches.php"
```

Then for **each surface** below, run BOTH the mouse path and the
keyboard path. Pass = keyboard path shows the brand-light ring; mouse
path does NOT.

### 1. Navbar — quick-search input (F-03)

URL: any logged-in page (dashboard / catalog / course view).

| Action | Expected |
|--------|---------|
| Click the search input in the navbar with the mouse | NO outline ring; only the border + box-shadow brand-light state |
| Tab through navbar (Tab from page top) until search input has focus | Brand-light ring + border + box-shadow visible |
| Type a character, then mouse-click outside | Ring vanishes |
| Tab back to it after mouse-clicking out | Ring reappears |

### 2. Dashboard — local search input

URL: `/local/search/index.php`

| Action | Expected |
|--------|---------|
| Mouse-click `input#search` in the local-search header | No ring, only border + shadow |
| Tab into `input#search` from the page header | Ring visible |

### 3. Login — primary + signup + forgot-password forms (F-11)

URLs: `/login/index.php`, `/login/signup.php`, `/login/forgot_password.php`

| Action | Expected |
|--------|---------|
| Click into login username field with mouse | No ring |
| Tab into username field from page top | Ring visible |
| Repeat on password field, signup form fields, forgot-password fields | Same pattern: ring on keyboard, none on click |

### 4. Course — catalog filter inputs + grader-report selects (F-19)

URLs: `/local/courses/index.php` (catalog), `/grade/report/grader/index.php`

| Action | Expected |
|--------|---------|
| Click into catalog search text input | No ring |
| Tab into catalog search text input | Ring visible |
| Click department/cost-center filter `<select>` | No ring on the select chrome (browser-native focus indicator may show on the open menu — that's the OS, not us) |
| Tab into the same `<select>` | Brand-light border visible |
| Mouse-click grader-report `<select.form-control>` | No ring |
| Tab into the same select | Ring visible |

### 5. Profile / admin — region-main form fields + tertiary-nav + mform (F-17)

URLs: `/user/preferences.php`, `/admin/category.php`, `/user/profile.php`, `/badges/mybadges.php`

| Action | Expected |
|--------|---------|
| Click any text input under `#region-main` (e.g. preferences search) | No ring |
| Tab into the same input | Ring visible |
| Click the tertiary-navigation search input (admin sub-nav) | No ring |
| Tab into the same | Ring visible |
| Click into any mform `<input>` / `<select>` / `<textarea>` in the user profile edit form | No ring |
| Tab into the same | Ring visible |

---

## Screen-reader spot-check

For NVDA / JAWS / VoiceOver users, the `:focus-visible` change is
inert — screen readers announce focus regardless of the visual ring,
so we don't expect any regression. Spot-check on at least one surface
with NVDA to confirm Tab announcement still says the input label
(this would only break if we accidentally removed something from
`outline: none` declarations, which we didn't).

---

## Browsers tested

For each browser, run the four checks above on the navbar and login
surfaces.

| Browser | Version | Pass? | Notes |
|---------|---------|-------|-------|
| Chrome | 120+ | (fill in) | Modern :focus-visible support |
| Firefox | 121+ | (fill in) | Modern :focus-visible support |
| Edge | 120+ | (fill in) | Chromium-based |
| Safari | 17+ | (fill in) | Modern :focus-visible support |
| Edge Legacy | 18 | (fill in) | Falls back to :focus rule; ring shows on click — expected |

Edge Legacy is the only fallback case; we expect it to look like the
pre-change behaviour (ring on click + on Tab), which is the WCAG 2.1.1
baseline.

---

## What was NOT changed

Per chip scope, the following were left untouched:

- Any `.mustache` template
- Any `.php` file (layout or otherwise)
- Any lang file
- Non-surface partials (`_bizlms-*.scss`, `_components-*.scss`,
  `_moodle-overrides.scss`, `dark_mode.scss`, `_tokens.scss`,
  `_ui-polish.scss`)
- Any plugin code

Those will be addressed in a follow-up chip after Chip J's profile
split lands and the partial layout is stable.
