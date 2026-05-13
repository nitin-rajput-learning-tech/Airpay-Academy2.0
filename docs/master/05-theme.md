## Section 4 — Theme: `airpayux` (Forked Epsilon)

### 4.1 Why a standalone fork and not a child theme

A Moodle theme typically declares parents from which it inherits — for example `$THEME->parents = ['epsilon', 'boost']` — and only overrides the files that differ. This is the pattern most Moodle deployments follow and it works well when the parent theme is a stable, vendor-supported product whose updates the deploying organisation wants to ride.

Airpay chose the standalone-fork pattern instead. The `theme/airpayux/config.php` declares `$THEME->parents = []`. Six hundred and forty-two files now live inside `theme/airpayux/`, including every Mustache template Moodle's core renderer can produce, every SCSS file in the Epsilon design system, every icon and pixmap, and a two-thousand-three-hundred-and-thirty-nine line `core_renderer.php` class that overrides Moodle's default page assembly.

Three reasons:

1. **Customisation depth.** The per-tenant branding requirement (different logo, different accent colour, different footer text and different feature visibility depending on whether the visitor is on tenant `/1`, `/77` or `/177`) cannot be satisfied by CSS overrides alone. It needs PHP-level logic inside the renderer to switch image sources, navigation items and block visibility. Once that logic exists, the marginal cost of owning every template that wraps it is small.
2. **Upstream-instability hedge.** Epsilon is a commercial premium theme. The licence terms allow forking. Owning the fork end-to-end removes any future risk of an Epsilon update conflicting with the customisations.
3. **Audit clarity.** A standalone fork means every visual change on the platform is traceable to a file in `theme/airpayux/`. There is no question of "is this behaviour coming from the parent or from us" during compliance audits.

The cost of the choice is that Moodle core upgrades that introduce new core templates or new core renderer methods will require manual carrying-forward of those additions into the airpayux fork. The discipline for that carry-forward is documented in the project's `MOODLE5-UPGRADE-RUNBOOK.md`.

### 4.2 Design system

The design tokens are defined in SCSS at the top of `theme/airpayux/scss/moodle/custom_changes.scss` and are also documented in the project's frontend rules file at `.claude/rules/frontend.md`. The system uses an eight-pixel spacing grid, the Montserrat typeface in weights four hundred to eight hundred, and the following palette:

| Token | Hex | Use |
|---|---|---|
| Primary | `#0066A7` | Calls to action, links, active navigation item, brand fill |
| Primary light | `#E8F2F9` | Hover background tints |
| Primary dark | `#004D80` | Pressed states, hover on primary |
| Accent | `#0F7A73` | Secondary actions, tags, success states |
| Background | `#F2F4FB` | Page background on every page |
| Surface | `#FFFFFF` | Card and panel backgrounds |
| Text primary | `#1A1A2E` | Body text, headings |
| Text secondary | `#5A6070` | Labels, helper text |
| Success | `#16A34A` | Confirmation states |
| Warning | `#D97706` | Caution states |
| Error | `#DC2626` | Destructive confirmations |

Border radii follow a four-step scale (eight pixels for inputs and small cards, twelve for modals, sixteen for hero cards, twenty for pill buttons). Shadows follow a three-step scale with progressively larger opacity and offset. Transitions default to two hundred milliseconds with the ease curve.

### 4.3 File inventory

Six hundred and forty-two files. The high-level distribution:

| Sub-tree | File count (approximate) | Notes |
|---|---|---|
| `templates/` (Mustache) | 96 | Every Moodle core template Airpay has touched; includes the per-tenant navbar, footer, login form, course card, and dashboard layouts |
| `scss/moodle/` | 60 | Component-by-component overrides keyed by Moodle surface (admin, atto, blocks, buttons, calendar, course, drawer, forms, login, message, modal, etc.) |
| `scss/bootstrap/` | ~40 | Forked Bootstrap source the theme uses to compile its own utility classes |
| `scss/fontawesome/` | ~5 | Font Awesome icon font configuration |
| `pix/`, `pix_core/`, `pix_plugins/` | ~250 | Icon and pixmap assets |
| `classes/` (PHP) | ~10 | Custom output classes including the renderer, the maintenance-mode renderer, the auto-prefixer for vendor CSS, and the admin-settings tabs widget |
| `layout/` | 7 | Page layout PHP files for the embedded view, the login layout, the maintenance layout, the secure layout, the standard columns-one layout, the standard columns-two layout, and the dashboard |
| `amd/` (JavaScript) | ~30 (source + build) | AMD-compiled JavaScript modules for the dashboard, drawer, navbar, popovers and toasts |

### 4.4 `core_renderer.php` — function-by-function summary

Two thousand three hundred and thirty-nine lines. The major sections:

1. **Per-tenant branding helpers.** Methods that resolve the visitor's current tenant from `$USER->open_path` and return tenant-specific assets — the logo image, the footer text, the accent colour override, and the homepage hero copy. The Public tenant (`/77`) receives a purple accent override visible at every surface; the Airpay tenant retains the default blue.
2. **Navbar assembly.** Override of `navbar()` that produces the Airpay-specific top navigation rather than Moodle's default flat navigation. Includes the search box, the language switcher, the cart icon (visible only on cart-enabled tenants), the user dropdown and the notification bell.
3. **Sidebar assembly.** The left-hand sidebar that drives the application's primary navigation. Eight items by default for logged-in employees, more for managers, more again for site administrators. Items are filtered against the visitor's capability set so users only see what they can act on.
4. **Footer assembly.** Override of `footer()` that produces the Airpay corporate footer rather than Moodle's default. Includes the legal copy, the privacy policy link, and the build identifier for support escalation.
5. **Login form overrides.** Override of the login form Mustache context to remove the default Moodle illustration and inject the Airpay login-page artwork plus the Single Sign-On entry points (when configured).
6. **Course card overrides.** Custom course card markup used on the catalogue, the dashboard and the search results. Pulls the course thumbnail, the badge for the tenant the course belongs to, the completion percentage if the visitor is enrolled, and the price tag if the course is in the cart catalogue.
7. **Block region helpers.** Methods that drive the dashboard's block-region rendering — left column, right column, hero region.
8. **Maintenance mode rendering.** A dedicated method that produces a branded maintenance banner when the platform is under upgrade rather than Moodle's default plain page.

### 4.5 Per-tenant branding logic

Tenant detection inside the renderer follows the same `\local_airpay_core\tenant::root_for_current_user()` helper used everywhere else on the platform. The renderer caches the resolved tenant id in a request-scoped property so that the helper is called at most once per page load. The cached id then drives every tenant-conditional decision: which logo to emit, which palette tokens to inject into the inline tenant CSS block at the head of every page, which navigation items to include in the sidebar, and which footer text to show.

The inline tenant CSS block is an interesting implementation detail. Rather than producing a separate compiled stylesheet per tenant — which would multiply the SCSS build time by three — the renderer emits a single `<style id="airpay-tenant-css">` block at the head of every page that overrides a small number of design tokens for the current tenant. The Public tenant's purple override is approximately one hundred and ninety-one characters of CSS injected at this layer.

### 4.6 Responsive strategy

The breakpoints used throughout the SCSS:

| Breakpoint | Threshold | Intent |
|---|---|---|
| 1400px and below | Wide-desktop crop | Reduce hero image and prevent over-stretch |
| 1200px and below | Laptop / large tablet landscape | Sidebar collapses to icon-only |
| 992px and below | Tablet landscape to portrait | Navbar simplifies, drawer becomes the primary navigation |
| 768px and below | Tablet portrait to mobile | Two-column layouts collapse to one |
| 590px and below | **Primary mobile breakpoint** | The breakpoint at which every page is regression-tested |
| 480px and below | Small mobile | Edge-case tightening |
| 380px and below | Very small mobile | Galaxy S edge tightening |

Every visual change ships with a verification check at the five hundred and ninety pixel breakpoint. The Phase 7 multi-role User Acceptance Test harness includes a dedicated case (`I.1 Mobile 590px renders`) that confirms each persona's pages render correctly at that width.

### 4.7 Known limitations and upgrade risk

| Item | Risk | Mitigation |
|---|---|---|
| Moodle core renderer methods added in Moodle 5.x that airpayux has not yet overridden | Medium — the new methods will render in Moodle's default style, breaking visual consistency on the surfaces they drive | The local development environment runs Moodle 5.1.3 already; any rendering mismatches discovered there are queued for theme work. |
| Vendor Mustache template changes upstream | Low — the airpayux fork has its own copies of all templates and won't accidentally inherit upstream changes | Manual carry-forward only when a new template introduces functionality Airpay actually wants. |
| The 2,339-line `core_renderer.php` is monolithic | Medium-term maintainability concern — the file should be decomposed into smaller renderer traits | Tracked as a Phase 9 refactoring backlog item. |
| Inline tenant CSS block injection size | Low — currently 191 characters for the largest override. Could grow if more tenants are added. | The architecture supports compiled per-tenant stylesheets if the inline block exceeds approximately five kilobytes. |
