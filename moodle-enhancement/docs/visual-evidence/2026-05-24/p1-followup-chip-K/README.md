# P1 #11 / F-10 — `_surface-login.scss` `!important` refactor

**Chip:** K
**Date:** 2026-05-24
**Branch:** `claude/admiring-knuth-Szn4E` (this branch — local repo on the cloud chip; merge target is `production` once Nitin signs off)
**Audit finding:** `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` lines 481–491 (F-10), P1 row #11 of the §4 punch-list (line 839).

---

## What changed

A single SCSS partial was refactored across 3 commits, one per logical scope:

| Commit | Scope | Hash |
|---|---|---|
| 1 | Section 1 — login-index page (`body#page-login-index` wrap) | `94bbaa43` |
| 2 | Section 2 — forgot-password + signup (`#page-X` scope already in place; drop overrides, preserve defensive ones) | `314f7e43` |
| 3 | Section 3 — dark-mode specificity match + selector bug-fix + version bump + this doc | (this commit) |

The 3 commits land on the chip branch atomically; reviewers can read them as one PR or three.

## Counts (before / after / delta)

`grep -c '!important' moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-login.scss`

| Stage | Lines containing `!important` | CSS occurrences in compiled output |
|---|---|---|
| Baseline (pre-refactor) | **66** | 74 |
| After commit 1 (section 1) | 62 | 65 |
| After commit 2 (section 2) | 31 | 33 |
| After commit 3 (section 3) | **11** | 11 |
| **Reduction** | **−55 lines (83.3%)** | **−63 occurrences (85.1%)** |

Audit target was 66 → <15. Final 11 hits 83% reduction. The remaining 11 are listed below with their justification.

## Preserved declarations (11 lines, each commented inline)

All 11 carry an inline `/* preserved: … */` comment explaining what the override is fighting. Two categories:

### Defensive against Moodle's `#region-main` ID specificity (6 lines)

The white-card style on `#page-login-forgot_password .airpay-login-region` and `#page-signup .airpay-login-region` is at specificity (1,1,0). Moodle's `<section id="region-main">` element wraps the card content, and theme parents / boost may apply `body#page-X #region-main` rules at (2,0,0) that beat our class-only selector. The 4 declarations on the base rule (`background`, `border-radius`, `padding`, `box-shadow`) and 2 on the `@media (max-width: 700px)` responsive override keep the flag defensively. If post-deploy visual evidence shows the cascade resolves correctly without them, a follow-up chip can strip the remaining 6.

### Fighting Bootstrap utility classes that themselves carry `important` (5 lines)

- `display: none !important` on `#page-login-forgot_password #page-header, …` (1 line) — Moodle may add `.d-block` or `.d-flex` to chrome wrappers; Bootstrap utility classes use built-in `important` so only `important` outranks `important`.
- `color: #94a3b8 !important` on `.fdescription` (1 line) and on `.fdescription abbr` (1 line) — the required-field `<abbr>` carries `class="text-danger"`, a Bootstrap colour utility that sets `color: red !important`. Without our own flag the asterisk renders red instead of muted gray.
- `background: #1e293b !important` and `box-shadow: 0 20px 60px rgba(0,0,0,0.5) !important` in dark-mode (2 lines) — these mirror the preserved light-mode card declarations so dark-mode can win the cascade against light-mode's preserved `important`.

## Strategy that allowed the other 55 lines to drop

1. **Section 1 (login-index)**: every selector now lives inside `body#page-login-index { … }`. The compiled CSS prefixes every rule with `body#page-login-index`, giving specificity (1,1,1) — beats any plain class (0,1,0) or ID-only (1,0,0) selector by class count.
2. **Section 2 (forgot-password + signup)**: the existing `#page-login-forgot_password .X` and `#page-signup .X` selectors already give (1,1,0). Combined with `.signup_form--simple` for the form-layout rules, those reach (1,2,0). Both tiers beat Moodle's `.mform .fitem` (0,2,0) and Bootstrap grid (0,1,0 without their own `important`).
3. **Section 3 (dark mode)**: dark variants now chain the dark-mode class to the body-id selector (`body.dark-mode#page-login-index .X` for section 3a, `body.dark-mode#page-X .X` for section 3b). This adds a class tier on top of the body-id tier so dark beats light without flags.

## Bundled bug-fix

Prior dark-mode rules used `body.dark-mode #page-login-forgot_password .X` with a **descendant combinator** (a space between `body.dark-mode` and `#page-X`). That selector reads as "an element with id `#page-X` *inside* a `body.dark-mode`" — but since `#page-X` IS the body, no such inner element exists, so the rules silently never fired.

Replaced with `body.dark-mode#page-X .X` (chained, no space) — body with BOTH the dark-mode class AND the page-X id. Now matches correctly. This had been latent for the entire dark-mode lifetime of the partial; the fix means dark mode for forgot-password and signup pages will visually activate where it previously never had.

The audit found this latent bug while tracing the refactor; reviewers should expect the dark-mode-on forgot-password / signup screenshots **to look different from production** in a good way (they were already supposed to look like this but the selector was wrong).

## Coordination with chip H

Chip H is concurrently adding `:focus-visible` siblings to 7 (actually 6) `:focus` rules in this same file (audit F-11). All 6 `:focus` selectors are preserved unchanged in this refactor; their containing rules just acquired the `body#page-login-index` prefix when wrapped (section 1) or kept their `#page-X` scope (section 2). Chip H can add their additions either:

- **If chip K merges first** (this chip): chip H rebases their `:focus-visible` siblings onto the new structure — `body#page-login-index .airpay-login__input:focus { … }` already lives inside the wrapper, so chip H just adds `body#page-login-index .airpay-login__input:focus-visible { … }` adjacent to it (or uses SCSS `&:focus-visible` nesting if chip H prefers).
- **If chip H merges first**: my Section 1 wrap will need to absorb chip H's `:focus-visible` additions when the merge happens. Trivial conflict — both branches touch the same lines but the resolution is purely additive.

Coordination signal lives in `PROJECT-STATE.md` — see the H2 appended in commit 3.

## Visual evidence — for Nitin to validate

The chip ran in a cloud session without browser / XAMPP access, so static screenshots are not produced here. Compile-time evidence is documented; **runtime regression check is Nitin's**.

### Compile-time evidence

- `npx sass --no-source-map --style=expanded moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-login.scss /tmp/post.css` succeeds with no errors and no warnings.
- Output line count went from 874 (baseline) to 897 (post-refactor). The increase is the SCSS nesting prefix added to every rule in section 1 — substantively the same rule set, just longer selectors. No declarations were lost or added (apart from the dark-mode selector bug-fix that activated previously-dead rules).
- Every `!important` in the post-refactor compiled CSS appears alongside a `/* preserved: … */` comment.
- Every dark-mode rule that previously used `body.dark-mode #page-X` (descendant) now compiles as `body.dark-mode#page-X` (chained). No descendant-combinator dark-mode selectors remain.

### Runtime checklist (Nitin to tick on cutover-day XAMPP build)

Visit each URL **logged out** so the actual login surface renders. Hard-refresh (Ctrl+Shift+R) after `php admin/cli/purge_caches.php`.

#### Light mode — default surface (`/login/index.php` → `#page-login-index`)

- [ ] Split-screen layout: gradient hero left (45% width), white form panel right.
- [ ] Hero gradient is the dark blue → teal blend (`#003d66 → #0066A7 → #0f7a73`).
- [ ] Hero hosts the logo (white via `filter: brightness(0) invert(1)`), title, subtitle, feature blocks, social row.
- [ ] Form title and subtitle render in `#1a1a2e` / `#607286`.
- [ ] Username + password inputs have rounded corners (10px radius), 1.5px border in `#e3eaf3`, white background.
- [ ] Input :focus shows the blue border `#0066A7` + 4px halo `rgba(0,102,167,0.1)`.
- [ ] Submit button is the gradient CTA, hovers up 2px with shadow.
- [ ] Divider line between submit and SSO buttons renders correctly (`::before` shows the line, `span` shows "or" text on white background).
- [ ] Guest button (if shown) shows hover state (border + text turn primary).
- [ ] Page background is `#F2F4FB`.

#### Light mode — forgot-password (`/login/forgot_password.php` → `#page-login-forgot_password`)

- [ ] Background is the navy gradient (`linear-gradient(135deg, #003052 0%, #0a4a42 100%)`).
- [ ] White card centered, max-width 640px, 32px padding (16px on mobile).
- [ ] Heading "Forgot Password" reads in Montserrat 700, dark slate (`#0f172a`).
- [ ] Subtitle paragraph in muted gray.
- [ ] Email input renders with 2px border `#e2e8f0`, 10px radius, light-gray bg.
- [ ] Email input :focus turns border blue + bg white.
- [ ] Submit button is gradient `linear-gradient(135deg, #0066A7, #0a5c50)`, white text.
- [ ] Submit button hover dims to 0.9 opacity.
- [ ] "Back to login" secondary button is link-styled (transparent, no border).
- [ ] Moodle chrome (page header, navbar, breadcrumb) is **hidden**.

#### Light mode — signup (`/login/signup.php` → `#page-signup`)

- [ ] Same navy gradient background as forgot-password.
- [ ] White card identical sizing.
- [ ] Form labels stack ABOVE their inputs (single-column flow, not the two-column Moodle default).
- [ ] All inputs (text, email, password, select) full-width.
- [ ] `.fdescription` for required-field markers renders centered, small (0.8rem), in muted gray (`#94a3b8`) — **NOT red**.
- [ ] Required `<abbr>` markers (the asterisk inside `.fdescription`) ALSO muted gray, not red — this is the preserved Bootstrap-`.text-danger` override.
- [ ] Submit button identical gradient + hover.
- [ ] Cancel button has 2px gray border + blue text, full-width, 44px tall.
- [ ] Moodle chrome hidden.

#### Dark mode — toggle dark mode, then revisit all three URLs

- [ ] **Login-index dark**: form panel turns `#1a1d27`, title/subtitle/label colours light-on-dark, inputs have dark-slate bg + slate border + light text, divider line `#334155`, guest button dark with light text.
- [ ] **Forgot-password dark**: white card → `#1e293b`, shadow deepens. Headings + labels light. Inputs dark. p tag muted gray.
- [ ] **Signup dark**: same card-darkens behaviour; labels light. `#id_cancel` button has slate border + blue-200 text.
- [ ] **Critical — dark mode on forgot-password / signup now actually works**. Previously a selector typo meant `body.dark-mode #page-login-forgot_password` (with space) never matched, so dark mode silently fell back to light-mode styling on these pages. This refactor's bundled bug-fix is what makes the dark-mode card variant actually render on these surfaces — if Nitin sees no change between pre- and post-refactor dark mode here, the bug-fix didn't land; investigate the chained selector before signing off.

#### Mobile viewport (590 / 480 / 380 breakpoints — primary mobile is 590)

- [ ] **Login-index mobile**: hero stacks above form panel (column layout). Hero hides circles + social + features at < 991px. Form panel padding shrinks.
- [ ] **Forgot-password mobile**: card padding shrinks to 32×20, border-radius to 12px at < 700px.
- [ ] **Signup mobile**: same card responsive shrink.
- [ ] No horizontal scroll on any of the three surfaces at any breakpoint.

If any of these items fails, halt — likely cause is a Moodle / Bootstrap rule that has higher specificity than expected and needs either a slight selector bump or the `important` flag restored for that specific declaration.

---

## Sass compile verification

```bash
$ cd /home/user/Airpay-Academy2.0
$ npx sass --no-source-map --style=expanded \
    moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-login.scss \
    /tmp/post.css
$ grep -c '!important' /tmp/post.css
11
$ grep -c '!important' moodle-enhancement/theme/airpayux/scss/moodle/partials/_surface-login.scss
11
```

No warnings, no errors. The 11-occurrence floor matches the 11 preserved declarations enumerated above.

## Next steps for the wider audit

- **F-11 / P1 #12** (Chip H, concurrent): `:focus-visible` siblings on the 6 `:focus` selectors that survived this refactor. Coordination noted above.
- **F-16 / P1 #13** (separate chip): same refactor strategy can be applied to `dark_mode.scss` (253 `!important`) — wrap top-level rules under their parent body-id / route-path selectors and most should drop. Larger surface; reserve a separate chip.
- **F-08 / P1 #10** (separate chip): same strategy for `_surface-profile.scss` (164 `!important`, 2,507 lines). Bigger surface; needs decomposition AND scope-wrap.
