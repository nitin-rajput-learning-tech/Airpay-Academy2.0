# sentientia theme — core_renderer trait decomposition

This directory holds the trait files extracted from
`classes/output/core_renderer.php` during Phase 9.5 engineering item 3.

The motivation is documented in `docs/SUPP-A-RISK-REGISTER-FULL-2026-05-12.md`
row **A3** ("`core_renderer.php` (2,339 lines) too large to maintain
confidently"). The decomposition moves logically-grouped methods into
small, single-responsibility trait files. Each trait is `use`d by
`core_renderer.php`. Behaviour is preserved verbatim.

## Decomposition plan

| Trait | Status | Methods | Approx LOC |
|---|---|---|---|
| `branding_buttons` | ✅ shipped Phase 9.5 pass 1 | helpbtn, aboutbtn, contactbtn, get_copyright_text, secure_login_info, footer_social_icons | ~70 |
| `login_ui` | ✅ shipped Phase 9.5 pass 2 | loginslider, welcometext, captiontext, login_stat_users, login_stat_courses, login_stat_certs, login_stat_completion, get_public_tenant_path | ~200 |
| `login_render` | queued | render_login, render_otplogin (Moodle login-form renderer overrides — separate from stats helpers) | ~80 |
| `branding_assets` | ✅ shipped Phase 9.5 pass 3 | should_display_navbar_logo, get_custom_logo, carousellogo, loginlogo, logintext, loginordering, get_primarycolor, get_secondarycolor, get_hovercolor, getsitecolors_link | ~150 |
| `navbar_navigation` | queued | navbar, custom_language_menu, left_navigation_quick_links, quickaccess_links, custom_secured_redirection | ~340 |
| `context_header` | queued | context_header, render_context_header, course_context_header_settings_menu, full_header | ~390 |
| `course_view` | queued | courseformat_drawer_content, courseviewmenu_hidden, course_bannerimage, course_summary_data, hasrmaincontenthidden, activityurl_get_course | ~110 |
| `user_menu` | queued | user_menu, is_admin_or_manager, is_siteadmin_only, loggedin_username, role_capability_assignments | ~370 |
| `quick_links_button` | queued | get_quickLinks | ~30 |

After all eight traits are extracted, `core_renderer.php` becomes a
thin orchestration class that `use`s all eight traits and only contains
the lifecycle methods (`standard_head_html`, `standard_footer_html`,
`airpay_shell_start`, `airpay_shell_end`) that genuinely require the
class-level integration.

Projected end-state line count: ~250 lines in `core_renderer.php` plus
~1,700 lines across the eight trait files. Same total logic, cleanly
partitioned.

## Pattern per trait extraction

1. Identify a logically-cohesive group of methods (3-8 methods).
2. Create `traits/<trait_name>.php` with:
   - `namespace theme_sentientia\output\traits;`
   - `defined('MOODLE_INTERNAL') || die();`
   - `trait <trait_name> { ... }`
3. Copy the method bodies into the trait — preserving them verbatim
   except for defensive `?? ''` null-coalescing additions where appropriate.
4. In `core_renderer.php`:
   - Add `use \theme_sentientia\output\traits\<trait_name>;` at the
     top of the class.
   - Remove the method bodies (or replace with a comment pointing
     to the trait).
5. Run `php -l` on both files.
6. Deploy to local XAMPP and walk the Phase 7 multi-role UAT to
   confirm rendering is unchanged.

## Verification

After each trait extraction, run:

```powershell
# Lint:
php -l moodle-enhancement/theme/sentientia/classes/output/core_renderer.php
php -l moodle-enhancement/theme/sentientia/classes/output/traits/<trait>.php

# Deploy + cache purge:
Copy-Item -Recurse moodle-enhancement/theme/sentientia/classes/output/* `
    C:/xampp/htdocs/moodle5/public/theme/sentientia/classes/output/
php C:/xampp/htdocs/moodle5/admin/cli/purge_caches.php

# UAT (5-10 min):
cd moodle-enhancement/audit/playwright
node uat_phase7_multirole.mjs
```

Expected: 84/85 pass (matches the pre-decomposition baseline). Any new
failure indicates that the trait extraction broke something — revert
the trait and investigate before continuing.

## Why traits, not interfaces or composition

PHP traits are compile-time copy-paste of method bodies into the
consuming class. Three properties matter here:

1. **No runtime overhead.** A method called via a trait costs the same
   as the same method written inline.
2. **`$this` works.** The methods retain access to the consuming class's
   `$this` context exactly as if they were defined inline.
3. **Inheritance plays nicely.** Methods from `parent::` (the Moodle
   core `\core_renderer`) are still callable from the trait via
   `$this->parent_method()` because the compile-time expansion happens
   in the consuming class's class table.

The alternative — composition with delegate objects — would require
threading `$page`, `$page->theme`, and other renderer state into each
delegate, which is a bigger refactor for no measurable benefit.

## Anti-patterns to avoid during decomposition

- Do NOT change method signatures during the extraction. Behaviour
  preservation is the contract; signatures are observable from outside
  the class.
- Do NOT introduce new dependencies (other plugins, libraries) inside
  trait files. Each trait should be standalone with only Moodle core
  + theme settings as its dependency footprint.
- Do NOT add new methods to traits during the extraction commit. Add
  them in a follow-up commit so the diff stays purely structural.
- Do NOT decompose by file size. Decompose by domain coherence. A
  cohesive 400-line trait is fine; an incoherent 80-line trait is not.
