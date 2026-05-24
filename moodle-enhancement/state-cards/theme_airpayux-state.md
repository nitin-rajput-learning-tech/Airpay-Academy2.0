# State Card — `theme_airpayux`

**Component:** `theme_airpayux`
**Version:** `2026052404` / `1.0.35-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live theme on airpay.academy. Standalone fork (`$THEME->parents = []`).
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

The Airpay-branded Moodle theme — fork of upstream `epsilon`,
standalone (`$THEME->parents = []`), so all 514+ files are owned
here. The base layer for the Sentientia LMS design system; per-customer
branding (logo + colour + favicon + font) is driven via
`local_airpay_core::customer_brand` and consumed by `core_renderer`.

## Design tokens (in `scss/moodle/_tokens.scss` + `custom_changes.scss`)

```
Primary:        #0066A7  | Accent:    #0f7a73
BG:             #F2F4FB  | Surface:   #ffffff
Text primary:   #1a1a2e  | secondary: #5a6070
Font:           Montserrat 400-800
Spacing:        8px base | Radius:    8-20px
```

See `.claude/rules/frontend.md` for the full design-token reference.

## DB tables

None — themes don't own tables.

## Capabilities

None declared. Per-tenant + per-customer branding gates rely on
`local_airpay_core` capabilities.

## Feature flags

None registered directly. The theme reads:
- `engagement.gamification.confetti` (course-completion confetti)
- `sentientia.pwa.enabled` (manifest + service-worker link injection)
- `sentientia.pwa.install.enabled` (Add-to-Home-Screen prompt)
and gates the relevant render paths on the resolver state.

## Key files

```
theme/airpayux/
├── version.php                                  2026052404 / 1.0.35-beta
├── config.php                                   Theme config — $THEME->parents = []
├── lib.php                                       Theme callbacks
├── settings.php                                  Admin settings
├── readme_moodle.txt                             Upstream Moodle theme notes
├── thirdpartylibs.xml                            Third-party libs (Chart.js etc.)
├── upgrade.txt                                   Theme upgrade log
├── _archive/                                     Historical artefacts (not loaded)
├── amd/                                          AMD modules
├── classes/
│   ├── output/core_renderer.php                  Main renderer (2,129+ LOC)
│   ├── output/core_renderer_maintenance.php      Maintenance-mode renderer
│   ├── admin_settingspage_tabs.php               Tabbed settings page builder
│   ├── autoprefixer.php                          SCSS post-processor wiring
│   ├── epsilonnavbar.php                         Legacy navbar (still loaded for some pages)
│   ├── hook_callbacks.php                        Moodle 5.x hook callbacks
│   ├── role_detector.php                         Learner/manager/trainer detection helper
│   ├── sidebar_navigation.php                    Sidebar nav builder
│   ├── ws_contract_scanner.php                   WS-contract drift scanner (Bug #10 follow-up)
│   └── privacy/provider.php                      GDPR / DPDP provider
├── cli/                                          Operations CLIs
├── layout/                                       Page layouts (10)
│   ├── dashboard.php                              /my/dashboard.php
│   ├── columns1/columns2.php                     Standard layouts
│   ├── course.php                                 Course view
│   ├── drawers.php                                Drawer-style layout
│   ├── embedded.php                               Embedded views
│   ├── frontpage.php                              Site front page
│   ├── login.php                                  Login page
│   ├── maintenance.php                            Maintenance mode
│   └── secure.php                                 Secure mode
├── templates/                                    Mustache templates (~50+)
│   ├── core/                                      Core overrides (loginform etc.)
│   ├── core_courseformat/                        Course-format overrides
│   ├── core_form/                                Form element overrides
│   ├── components/                                Reusable components (button, card, badge, etc.)
│   ├── block_myoverview/                          My-overview block overrides
│   ├── navbar.mustache                            Site navbar
│   └── columns1/columns2 + course + admin_setting_tabs
├── scss/
│   └── moodle/                                    55+ partials (one per surface)
├── style/                                        Pre-built CSS
├── javascript/                                   Legacy JS
├── pix/                                          Brand pix
├── pix_core/                                     Core pix overrides
├── pix_plugins/                                  Plugin pix overrides
├── lang/                                         en + hi + kn + mr + sw
└── tests/
    ├── scss_test.php                             1 method (SCSS compile sanity)
    ├── epsilonnavbar_test.php                    3 methods
    ├── role_detector_test.php                    8 methods
    ├── ws_contract_test.php                      1 method (WS-contract drift)
    ├── behat/                                     Behat features (settings tabs, breadcrumb, tour filter)
    └── privacy/provider_test.php                 1 method
```

## Tests

4 PHPUnit classes (13 methods) + 4 Behat features.

## Recent work (2026-05-24 audit pass)

Per the platform visual audit, this theme absorbed:
- P0 #1+#2+#3+#4+#5+#6+#7+#9 — SCSS hygiene, template hygiene,
  dashboard inline-style cleanup, cart_badge AMD wiring fix
- P1 #10+#11+#12+#13+#14+#17 — `_surface-profile.scss` decomposition,
  login `!important` refactor, `:focus-visible` coverage + re-apply,
  dark-mode token cascade, footer mobile responsiveness, Chart.js vendoring
- P2 #18+#19+#20+#21+#23 — assorted polish, stylelint rule for
  `prefers-reduced-motion`, XSS sanitisation on `coursebannerimage`,
  comment cleanup, chart a11y
- F-13 — dashboard i18n + 5-locale propagation

## Open items

- [ ] Long-running refactor: split `core_renderer.php` (2,129+ LOC)
      into focused renderers (one per surface) — partial via per-surface
      SCSS decomposition, but PHP renderer is still monolithic
- [ ] Per-customer-brand admin UI (today: `verify_brand_resolver.php`
      CLI only)
- [ ] Sentientia design-system v1 — formal token re-base off the
      22 C-suite-approved prototypes
- [ ] PWA manifest + service worker hook polish (Phase B.3 follow-on)
- [ ] Customer-scoped favicon path (today: theme-wide)

## State card created — 2026-05-24

Initial state card for the theme. The theme has been live for many
phases but had no state card; created as part of the P1 state-card
pass after the merge wave. The platform-visual-audit-2026-05-24 work
is the most recent body of changes; details documented inline above
and cross-referenced in PROJECT-STATE.md.
