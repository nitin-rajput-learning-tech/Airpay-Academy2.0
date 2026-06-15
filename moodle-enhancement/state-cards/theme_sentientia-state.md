# State Card — `theme_airpayux`

**Component:** `theme_airpayux`
**Version:** `2026060200` / `1.0.46-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live theme on airpay.academy. Standalone fork (`$THEME->parents = []`).
**Last refreshed:** 2026-06-02 (Epsilon/eAbyas de-brand — epsilonnavbar → airpayux_navbar)

> **2026-05-29 signup-flow `_surface-login.scss` fixes (→1.0.40-beta):**
> (B) `#page-signup` wrapper switched to `align-items:flex-start` (+40px
> padding) — the shared `align-items:center` clipped the tall signup
> card's top above the scroll origin on laptop viewports (users had to
> zoom to ~75%). (E) login/index.php NOTICE pages (e.g. "You need to
> confirm your account") now get a branded centred card via
> `:not(:has(.airpay-login))` — they previously rendered flush-left +
> unstyled because Section 1 resets `#page-login-index` region padding to
> 0 (it expects the split-screen login markup). Light + dark variants;
> padding kept in a separate 2-id rule so the dark-mode bg override still
> wins. The real split-screen login is excluded by the `:has()` guard
> (verified untouched). Evidence: `docs/visual-evidence/2026-05-29/signup-*.png`.

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
├── version.php                                  2026060200 / 1.0.46-beta
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
│   ├── airpayux_navbar.php                       Breadcrumb navbar (Moodle boostnavbar-derived; de-branded 2026-06-02)
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
    ├── airpayux_navbar_test.php                  3 methods
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

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## Catalog storefront footer-overlap fix (2026-06-01)
`scss/moodle/partials/_layout-shell.scss`: scoped `body.path-local-airpay_catalog`
OUT of the locked app-shell cockpit (body 100vh+overflow:hidden + internal
`.ap-shell__content` scroller + footer pinned by `flex:0 0 auto`) so catalog pages
document-scroll with the footer at the END of content. Fixes the pinned 3-row footer
crowding/clipping the 180+ card grid (reported overlap). Fixed sidebar + sticky topbar
unaffected; higher-specificity selectors, no `!important`. Dashboard + other app-shell
pages untouched. v1.0.46→1.0.47-beta (2026060100). Verified live in Chrome (cards whole,
footer at content end).

## Footer clears the fixed sidebar (2026-06-01)
_surface-footer.scss: the full-width footer's left-aligned content (standard_footer_html
footnote) tunneled under the position:fixed .ap-sidebar. Added padding-left =
--ap-sidebar-width (260) / --ap-sidebar-collapsed-width (72) to #page-footer on
body:has(.ap-shell) at >=769px, mirroring .ap-shell__main's margin-left; mobile keeps
full-width. v1.0.47→1.0.48-beta. Verified: footer content clears the sidebar.

## 2026-06-02 — Epsilon/eAbyas de-brand → Sentientia (chip: debrand-navbar)

Per Nitin's "move away from Epsilon/eAbyas completely" directive (decisions:
rebuild remaining eAbyas to 100% Sentientia; rename epsilon* identifiers; remove
dead theme/epsilon).

- **`epsilonnavbar` → `airpayux_navbar`** — the live breadcrumb class (instantiated by
  `traits/page_helpers.php::navbar()`) renamed. The class + its 600-line test +
  the instantiation were all repointed; zero `epsilonnavbar` refs remain. The class
  is Moodle-core `boostnavbar`-derived (`@copyright 2021 Adrian Greeve`), so per GPL
  the **core copyright is RETAINED** — only the identifier + the "epsilon" comments
  were de-branded. (Falsely claiming Moodle's code as Sentientia would itself be a
  GPL violation; the de-brand targets the eAbyas/epsilon *branding layer*, not the
  Moodle-core layer underneath.)
- **`version.php` header** de-branded to the Airpay/Sentientia attribution (dropped
  the "forked from eAbyas epsilon" prose; the file's own copyright was already Airpay).
- version `…/1.0.45-beta` → `2026060200 / 1.0.46-beta`.

**Follow-up batches (tracked):** the broader eAbyas-copyright-header sweep across the
remaining theme files (re-attribute airpayux-authored files to Sentientia; retain
Moodle-core copyright on core-derived files), removal of the dead `theme/epsilon/`
parent dir (airpayux is standalone — structurally safe per `config.php` `$THEME->parents=[]`),
and behat/heritage-doc residue. Generated files (`*.min.js.map`, `style/moodle.css`)
and `production-data/*.json` dumps are excluded (regenerated / data, not source).

## 2026-06-02 — Repo cleanup: dead artefacts removed

Removed clearly-dead, unreferenced files (git history retains them; verified no SCSS
`@import` / code reference before removal):
- `_archive/custom_changes_MONOLITH_BACKUP.scss` (288K pre-decomposition monolith,
  superseded by the 55-partial split) — the `_archive/` dir is now gone.
- `scss/fontawesome4_OLD/` (15 files, 102K) — superseded by the FA6 iconsystem; not
  imported by any top-level scss.
- `pix/Biz_logo_OLD.png` — superseded logo, unreferenced.

## WF-025 (2026-06-15, foolproof A5) — role-switch force-pin removed (P0)

`classes/output/traits/user_menu.php:161-163` unconditionally force-pinned
`$USER->access['rsw']['/1'] = <employee/student role id>` on EVERY render
whenever any role-switch entry already existed. Because `/1` (system) is an
ancestor of every context, core's bottom-up rsw walk demoted any multi-role
user (an org-admin/manager who is ALSO a course editingteacher) to the student
role SITE-WIDE — stripping `moodle/course:manageactivities` so
`course/modedit.php` (add activity/quiz) threw `required_capability_exception`,
with no in-course "return to my normal role" banner (the switch lived at the
parent `/1`, so `is_role_switched(course)` is false). A CLI `has_capability`
probe granted the cap (no session → no rsw), which masked it from non-browser
tests. Removed the force-pin (the switcher menu + explicit `/my/switchrole.php`
click path are untouched). Verified: after a fresh login qa_orgadmin
(editingteacher) reaches the quiz-create mform and sees the full org-admin nav.
Version 2026060400 → 2026061500. Deeper redesign (auto-switch-on-page-load +
a working return-to-normal escape hatch) flagged for Nitin — see the workflow
synthesis + WORKFLOW-TEST-MATRIX A5.

## WF-025b (2026-06-15) — decouple role-view scoping from the capability switch

Follow-up to WF-025 (Nitin: "fix the auto-switch-on-load — switch only on
explicit click"). `roleswitch()` / `role_switch_basedon_userroles()` gained an
`$applyrsw` flag; the first-visit auto-call (`user_menu.php`) now passes `false`,
so it establishes the role-view scoping context (`$USER->useraccess['currentroleinfo']`,
consumed by the org-scoped learnerscript reports + dashboards) WITHOUT writing
`$USER->access['rsw']` (which replaces effective capabilities). Net: navigating to
the dashboard never reduces a multi-role user's capabilities; a real capability
switch happens only on an explicit user action. **Verified** on 5.2 (fresh
qa_orgadmin login): `rsw = []` (was `{"/1/3":9}`), `currentroleinfo` + scoping
paths `["/1"]` UNCHANGED (so report org-scoping is byte-identical — no
tenant-isolation regression), `has_capability(manageactivities)`=YES, and the
quiz-create mform renders. Version 2026061500 → 2026061501. Held for the
Phase-2 sandbox cutover (no live deploy). Follow-up to verify in the sandbox
(real multi-org data): an org-head's learnerscript reports still scope to their
own org. Remaining design option NOT taken (Nitin's call): a working
return-to-normal escape hatch / replacing the fork with core role_switch().
