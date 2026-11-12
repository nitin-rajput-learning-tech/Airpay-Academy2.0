# Frontend Rules — sentientia Theme (Moodle 5.1 local, 5.3 LTS target, UAT on 5.3)
# ALWAYS LOADED when editing theme files, SCSS, Mustache, or layout PHP.

---

## Identity — Read Before Every Theme Edit

```
Theme name:   sentientia (theme_sentientia), the former airpayux fork of epsilon
Type:         STANDALONE theme
Inheritance:  $THEME->parents = [] — we own every file
XAMPP path:   C:\xampp\htdocs\moodle5\public\theme\sentientia\
Working path: D:\Claude Local\airpay-ld-os\theme\sentientia\   (the theme lives only here,
              not under moodle-enhancement\)
Local site:   http://localhost:8080/   (wwwroot has no /moodle)
Brand source: moodle-enhancement\docs\audits\brand-revamp-2026-06\REVISED-BRAND-BOOK-2026-06.md
Tokens:       theme\sentientia\scss\moodle\_tokens.scss (+ _tokens-dark.scss)
Visual target: D:\Claude Local\Moodle Backup\03-prototypes\preview\ (22 approved prototypes)
```

**NEVER EDIT `theme/epsilon/` or `theme/airpayux/` (retired, not served) — you are in `theme/sentientia/` and own every file.**

---

## Design System (Copy-Paste Ready)

```scss
/* ==============================
   AIRPAY DESIGN TOKENS
   Add to top of custom_changes.scss
   ============================== */

// Brand colours
$ap-primary:        #0066A7;  // Airpay blue — CTAs, links, active nav
$ap-primary-light:  #e8f2f9;  // Hover backgrounds, tinted sections
$ap-primary-dark:   #004d80;  // Pressed states, hover on primary
$ap-accent:         #1985DD;  // Brand bright-blue ("for decks") — secondary actions, tags, active. (Revised Brand Book 2026-06: teal #0f7a73 RETIRED — no teal in the book.)
$ap-accent-light:   #e8f4fd;  // Light blue bg tint (was teal #e5f4f3)
$ap-accent-deep:    #0d5da1;  // Deep blue — gradient ends / pressed
$ap-highlight:      #ed692b;  // Brand orange — sparing CTA/emphasis only
$ap-tertiary:       #6d58a5;  // Brand purple — sparing tertiary
$ap-bg:             #F2F4FB;  // Page background (every page)
$ap-surface:        #ffffff;  // Card/panel backgrounds
$ap-surface-2:      #f8f9fc;  // Nested card backgrounds, zebra rows
$ap-border:         #e2e6ef;  // Card borders, dividers
$ap-text-primary:   #1a1a2e;  // Headlines, body text
$ap-text-secondary: #5a6070;  // Labels, captions, muted text
$ap-text-disabled:  #a0a9b8;  // Disabled inputs, placeholders

// Semantic colours
$ap-success:   #16a34a;
$ap-warning:   #d97706;
$ap-error:     #dc2626;
$ap-info:      $ap-primary;

// Typography
$ap-font:        'Montserrat', -apple-system, sans-serif;
$ap-font-mono:   'Courier New', monospace;
$ap-weight-reg:  400;
$ap-weight-med:  500;
$ap-weight-semi: 600;
$ap-weight-bold: 700;
$ap-weight-xtra: 800;

// Type scale (rem)
$ap-text-xs:  0.75rem;   //  12px — captions, badges
$ap-text-sm:  0.875rem;  //  14px — helper text, table cells
$ap-text-md:  1rem;      //  16px — body copy
$ap-text-lg:  1.125rem;  //  18px — card titles, nav items
$ap-text-xl:  1.25rem;   //  20px — section headings
$ap-text-2xl: 1.5rem;    //  24px — page headings
$ap-text-3xl: 1.875rem;  //  30px — hero/banner text

// Spacing — 8px base grid
$ap-space-1:  8px;
$ap-space-2:  16px;
$ap-space-3:  24px;
$ap-space-4:  32px;
$ap-space-6:  48px;
$ap-space-8:  64px;

// Border radius
$ap-radius-sm: 8px;    // inputs, small cards
$ap-radius-md: 12px;   // modals, panels
$ap-radius-lg: 16px;   // hero sections, feature cards
$ap-radius-xl: 20px;   // pill buttons, badges
$ap-radius-full: 50%;  // avatars, circular buttons

// Shadows
$ap-shadow-sm: 0 1px 4px rgba(0,0,0,0.06);
$ap-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
$ap-shadow-lg: 0 8px 24px rgba(0,0,0,0.12);

// Transitions
$ap-transition: 0.2s ease;
$ap-transition-slow: 0.35s ease;
```

---

## Motion & `prefers-reduced-motion` (WCAG 2.3.3)

The Sass `$ap-transition*` literals above are kept for legacy SCSS that still
needs Sass values. **New code MUST use the CSS-custom-property tokens** defined
in `scss/moodle/_tokens.scss` (motion block, about lines 240–310), because those tokens collapse to `0ms`
under `@media (prefers-reduced-motion: reduce)`. Anything that hardcodes
inline timing (e.g. `transition: all 0.2s ease`) bypasses the cascade and
animates regardless of OS preference — a vestibular-accessibility violation.

### Token cascade

```scss
/* From _tokens.scss — read, don't redefine. */
:root {
    --ap-duration-instant:    0ms;     /* button press, checkbox tick */
    --ap-duration-quick:    150ms;     /* small state changes */
    --ap-duration-default:  250ms;     /* most transitions */
    --ap-duration-slow:     400ms;     /* layout-affecting transitions */
    --ap-duration-deliberate: 700ms;   /* celebration moments only */

    /* Composite shortcuts — duration + ease pre-paired: */
    --ap-transition-quick:   var(--ap-duration-quick)   var(--ap-ease-out);
    --ap-transition-default: var(--ap-duration-default) var(--ap-ease-out);
    --ap-transition-slow:    var(--ap-duration-slow)    var(--ap-ease-in-out);
    --ap-transition-emphatic: var(--ap-duration-default) var(--ap-ease-spring);
}

@media (prefers-reduced-motion: reduce) {
    :root {
        --ap-duration-quick:      0ms;
        --ap-duration-default:    0ms;
        --ap-duration-slow:       0ms;
        --ap-duration-deliberate: 0ms;
    }
}
```

### How to write motion (✅ correct)

```scss
.airpay-card {
    transition: transform var(--ap-transition-quick),
                box-shadow var(--ap-transition-quick);
}
.airpay-card:hover {
    transform: translateY(-2px);   /* nulls automatically under reduced motion */
}

/* Or, when you need to set duration separately: */
.airpay-modal {
    transition-property: opacity, transform;
    transition-duration: var(--ap-duration-default);
    transition-timing-function: var(--ap-ease-out);
}
```

### Anti-pattern (❌ caught by the lint rule)

```scss
.foo { transition: all 0.2s ease; }              /* ❌ inline shorthand value */
.bar { transition-duration: 150ms; }              /* ❌ literal duration */
.baz { transition: color 0.15s ease, bg 0.15s; }  /* ❌ literal inside shorthand */
```

### Lint rule (enforced)

`theme/sentientia/.stylelintrc.json` ships a `declaration-property-value-disallowed-list`
rule scoped via `overrides` to `scss/moodle/partials/_surface-*.scss`:

- `transition-duration` accepts only values that start with `var(` — anything
  else fires.
- `transition` (shorthand) fires if ANY numeric+unit pair (`0.2s`, `200ms`,
  `1s`) appears in the value. Forces the shorthand to consume
  `var(--ap-transition-*)` composite tokens.

Run locally (stylelint is already a root devDependency at `^15.11.0`; run
`npm install` once if `node_modules/` is empty):

```powershell
# From repo root:
npx stylelint --config theme/sentientia/.stylelintrc.json `
              "theme/sentientia/scss/moodle/partials/_surface-*.scss"

# Or from the theme dir (auto-discovers the config):
Set-Location theme\sentientia
npx stylelint "scss/moodle/partials/_surface-*.scss"
```

### Per-line opt-out (use sparingly + comment why)

For an unavoidable inline value (e.g. a polyfill animation that the cascade
cannot reach), disable the rule for just that declaration:

```scss
// stylelint-disable-next-line declaration-property-value-disallowed-list
transition: all 0.2s ease;   /* explain WHY in this comment */
```

Per-file disables are discouraged — keep the opt-out scoped to the violating
declaration. If you find yourself reaching for an opt-out more than once in
the same file, that's a signal to add a missing token to `_tokens.scss`
instead.

### Refs

- Token cascade source: `theme/sentientia/scss/moodle/_tokens.scss` (motion block, about lines 240–310).
- Lint rule config: `theme/sentientia/.stylelintrc.json`.
- Audit motivation: `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
  §2.7 + P2 #19.
- WCAG 2.3.3 — Animation from Interactions (Level AAA),
  https://www.w3.org/WAI/WCAG21/Understanding/animation-from-interactions.html.

---

## Responsive Breakpoints (from custom_media.scss)

```scss
// Use these EXACT breakpoints — matches existing 590 lines of media queries
@media (max-width: 1400px) { ... }  // Wide desktop crop
@media (max-width: 1200px) { ... }  // Laptop / large tablet landscape
@media (max-width: 992px)  { ... }  // Tablet landscape → portrait
@media (max-width: 768px)  { ... }  // Tablet portrait → mobile
@media (max-width: 590px)  { ... }  // ← PRIMARY MOBILE BREAKPOINT (test this)
@media (max-width: 480px)  { ... }  // Small mobile (SE, 12 mini)
@media (max-width: 380px)  { ... }  // Very small (Galaxy S series)

// Mobile-first alternative (use for new components):
@media (min-width: 591px) { ... }   // Tablet and up
@media (min-width: 993px) { ... }   // Desktop and up
```

---

## Key Template Files & Their Context Variables

### `templates/navbar.mustache`
Available context (from `classes/output/core_renderer.php`):
```mustache
{{homeurl}}              ← Moodle home URL
{{sitename}}             ← Site name (format_string'd)
{{logourl}}              ← Logo URL (tenant-specific via core_renderer)
{{primarynavigation}}    ← object with .items array [{text, url, isactive}]
{{usermenu}}             ← logged-in user dropdown context
{{loggedin}}             ← boolean
{{userpicture}}          ← user avatar URL
{{fullname}}             ← user's full name (format_string'd)
{{sesskey}}              ← CSRF token (needed on any form)
```

### `templates/footer.mustache`
```mustache
{{footnote}}             ← Admin-configured footer text
{{helplink}}             ← Help URL
{{loginurl}}             ← Login page URL
{{homeurl}}              ← Site home
{{output.standard_footer_html}}  ← Moodle required footer HTML (always include)
{{output.standard_end_of_body_html}} ← JS includes (always include, before </body>)
```

### `templates/core/loginform.mustache`
```mustache
{{logintoken}}           ← CSRF token (MUST include in form)
{{loginurl}}             ← Form action URL
{{username}}             ← Pre-filled username (from cookie)
{{errorformatted}}       ← Error message HTML
{{canresetpassword}}     ← boolean — show "forgot password" link?
{{forgotpasswordurl}}    ← forgot password URL
{{hasidentityproviders}} ← boolean — show SSO options?
{{identityproviders}}    ← array [{name, url, iconurl}]
{{autofocuscontrol}}     ← 'username' or 'password'
{{cookieshelpiconformatted}} ← cookies explanation link
```

### `layout/dashboard.php`
Available PHP context:
```php
$costcenterid     // BizLMS tenant ID (1=Airpay, 77=Public, 177=ZEEA)
$USER             // logged-in user object
$PAGE->blocks     // block manager
$CFG->wwwroot     // site root URL
// Dashboard URL: /my/ (/my/index.php). /my/dashboard.php is a BizLMS shim that redirects there; keep it, the theme still links to it
```

---

## BEM Naming Convention (All New Classes)

```scss
// Pattern: .airpay-[block]__[element]--[modifier]

.airpay-navbar { }                    // block
.airpay-navbar__brand { }             // element
.airpay-navbar__link { }              // element
.airpay-navbar__link--active { }      // modifier (active state)
.airpay-navbar__link--disabled { }    // modifier

.airpay-card { }
.airpay-card__header { }
.airpay-card__body { }
.airpay-card--featured { }            // modifier (featured variant)

.airpay-btn { }
.airpay-btn--primary { }
.airpay-btn--outline { }
.airpay-btn--sm { }
.airpay-btn--lg { }
```

---

## SCSS Editing Protocol (Strict Order)

**Never break this sequence. Never do full rewrites.**

```
1. VARIABLES      — define new tokens in :root or $variable declarations
2. COLOURS        — colour overrides using variables only (no hex literals in rules)
3. RESET/BASE     — html, body, * baseline
4. LAYOUT         — containers, grid, page structure
5. COMPONENTS     — navbar, cards, buttons, forms, modals (one at a time)
6. UTILITIES      — helper classes (.mt-2, .text-primary, etc.)
7. RESPONSIVE     — @media queries (mobile-last preferred for overrides)
```

**Find-and-replace workflow (for existing code debt):**
```
Select-String -Path "scss\moodle\custom_changes.scss" -Pattern "#0066A7" |
  ForEach-Object { Write-Host $_.LineNumber ": " $_.Line }
# Then use Edit tool for targeted replacements
```

---

## Multi-tenant Rendering Rules

```php
// In core_renderer.php — ALWAYS preserve tenant checks
$costcenterid = (int)($this->page->theme->settings->costcenterid ?? 0);

// Airpay (id=1) and Public (id=77) get different:
//   - Logo (logo-airpay.png vs logo-public.png)
//   - Colour accent in some components
//   - Footer content
//   - Some block visibility

// Pattern for tenant-conditional output (illustrative: the logo file names
// below are examples, not files in theme/sentientia/pix/):
if ($costcenterid === 1) {
    return html_writer::img('/theme/sentientia/pix/logo-airpay.png', 'Airpay Academy');
} elseif ($costcenterid === 77) {
    return html_writer::img('/theme/sentientia/pix/logo-public.png', 'Airpay Learning');
} else {
    return html_writer::img('/theme/sentientia/pix/logo-default.png', get_config('moodle', 'sitename'));
}

// Per-CUSTOMER branding (logo, colours, favicon) comes from
// \local_sentientia_platform\customer::branding(); core_renderer injects it as
// CSS custom properties (--ap-color-primary, --ap-color-bg) before the tenant layer.
```

---

## Mustache Correctness Rules

```mustache
{{! ESCAPING }}
{{ variable }}       ← auto HTML-escaped ✅ use for all user text
{{{ html_content }}} ← RAW — only for pre-sanitised HTML (flag every instance)

{{! STRINGS — never hardcode English }}
{{# str }}pluginname, local_myplugin{{/ str }}   ← plugin string
{{# str }}save, core{{/ str }}                   ← core string

{{! CONDITIONALS }}
{{# condition }}...{{/ condition }}   ← truthy check (works for bool AND non-empty array)
{{^ condition }}...{{/ condition }}   ← falsy check (not condition)

{{! LOOPS }}
{{# items }}
  <li>{{ name }}</li>   ← each item becomes context
{{/ items }}

{{! PARTIALS }}
{{> local_pluginname/partial_name }}  ← include another template

{{! REQUIRED: always include at end of layout templates }}
{{{ output.standard_footer_html }}}
{{{ output.standard_end_of_body_html }}}
```

---

## 10 Surfaces — Phase 6B map (historical sprint plan, current file paths)

The sprint column is the Phase 6B plan, kept for history; Sprint 1 is long done.
Current status lives in `moodle-enhancement/PROJECT-STATE.md`. Theme paths are
relative to `theme/sentientia/`; plugin paths exist twice
(`moodle-enhancement/local/<plugin>/` and `local/<plugin>/`, kept byte-identical).

| # | Surface | Key Files (current) | Sprint (6B plan) | Reference Prototype |
|---|---------|---------------------|------------------|---------------------|
| 1 | **Navbar / app shell** | `templates/navbar.mustache`, `templates/shell.mustache`, `templates/topbar.mustache`, `templates/sidebar.mustache` | 1 | navbar-*.html |
| 2 | **Footer** | `templates/footer.mustache` | 1 | footer-*.html |
| 3 | **Login** | `templates/core/loginform.mustache` + `layout/login.php` | 1 | login-*.html |
| 4 | Emp Dashboard | `layout/dashboard.php` + `templates/dashboard.mustache` | 2 | dashboard-employee-*.html |
| 5 | Admin Dashboard | `layout/dashboard.php` + blocks | 2 | dashboard-admin-*.html |
| 6 | Course Catalog | `local/sentientia_catalog/` (`index.php`, `templates/catalog.mustache`) | 3 | catalog-*.html |
| 7 | Course Detail | `local/sentientia_catalog/course.php` | 3 | course-detail-*.html |
| 8 | User Profile | `local/sentientia_users/profile.php` + `templates/profile.mustache` | 4 | profile-*.html |
| 9 | Mobile Layout | `scss/moodle/custom_media.scss`, `templates/mobile_bottom_nav.mustache` | 4 | mobile-*.html |
| 10 | Service Worker / PWA | `local/sentientia_pwa/` (`sw.php`, `manifest.php`), `templates/head.mustache` | 4 | — |

---

## Deploy & Test Cycle (Run After Every Change)

```powershell
# 1. PHP lint (if layout PHP changed)
php -l "D:\Claude Local\airpay-ld-os\theme\sentientia\layout\[file].php"

# 2. Copy changed files into the served tree (moodle5\public\)
Copy-Item "D:\Claude Local\airpay-ld-os\theme\sentientia\templates\navbar.mustache" `
          "C:\xampp\htdocs\moodle5\public\theme\sentientia\templates\navbar.mustache" -Force

# 3. Purge caches (admin\cli is one level above public\; run with cwd = public\)
Set-Location "C:\xampp\htdocs\moodle5\public"
php ../admin/cli/purge_caches.php
#    (after a version.php bump: php ../admin/cli/upgrade.php --non-interactive first)

# 4. Hard refresh browser
# Ctrl+Shift+R

# 5. Test checklist (every deploy):
#    □ Login page: http://localhost:8080/login/index.php
#    □ Dashboard: http://localhost:8080/my/  (/my/dashboard.php is a BizLMS shim that redirects here)
#    □ As Learner role (not admin)
#    □ Mobile viewport 590px (Chrome devtools → device toolbar)
#    □ Browser console — zero JS errors
#    □ Both tenants if tenant-specific code changed
```

---

## Anti-patterns (Never Do These)

```
❌ inline style="color: #0066A7"          → use CSS class
❌ hardcoded English in .mustache          → use {{# str }} helper
❌ {{{ user_input }}}                      → triple-brace on user input = XSS
❌ editing theme/epsilon/ or theme/airpayux/ → you are in theme/sentientia/
❌ teal #0f7a73 anywhere                   → retired by the Brand Book 2026-06; accent is #1985DD
❌ full SCSS file rewrite                  → component-by-component only
❌ testing only as admin                   → admin bypasses capability checks
❌ skipping Ctrl+Shift+R after purge       → browser cache hides your changes
❌ echo $USER->firstname                   → echo format_string($USER->firstname)
```
