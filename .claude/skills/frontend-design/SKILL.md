---
name: sentientia Frontend Design
description: Guides frontend work on the sentientia Moodle theme (theme/sentientia, the former airpayux fork). Points to the Brand Book 2026-06 tokens in _tokens.scss, the theme file map and the local deploy cycle. The Sprint 1 (Navbar, Footer, Login) component code below is historical reference only.
metadata:
  priority: 9
  pathPatterns:
    - '*/theme/sentientia/**'
    - '*/templates/*.mustache'
    - '*/templates/**/*.mustache'
    - '*/scss/**/*.scss'
    - '*/layout/*.php'
    - '*custom_changes.scss'
    - '*custom_media.scss'
    - '*navbar.mustache'
    - '*footer.mustache'
    - '*loginform.mustache'
    - '*dashboard.php'
  promptSignals:
    phrases:
      - "theme"
      - "sentientia theme"
      - "airpayux"
      - "navbar"
      - "footer"
      - "login page"
      - "dashboard"
      - "sprint 1"
      - "sprint 2"
      - "surface"
      - "mustache"
      - "scss"
      - "design system"
      - "prototype"
      - "core_renderer"
---

# sentientia Frontend Design Skill

You are working on the **sentientia** theme: `theme/sentientia/`, standalone (`$THEME->parents = []`), the former airpayux fork and the Sentientia LMS design-system base. Airpay Academy is customer zero. `theme/airpayux/` and `theme/epsilon/` are retired and not served: never edit them.

**`.claude/rules/frontend.md` wins on any conflict with this skill.**

---

## Current facts (updated 2026-11-12; read these first)

| Item | Value |
|------|-------|
| Working path | `D:\Claude Local\airpay-ld-os\theme\sentientia\` (the theme lives only here, not under `moodle-enhancement\`) |
| Local XAMPP | `C:\xampp\htdocs\moodle5\public\theme\sentientia\`; site at http://localhost:8080/ (no `/moodle`) |
| Brand source | Brand Book 2026-06: `moodle-enhancement/docs/audits/brand-revamp-2026-06/REVISED-BRAND-BOOK-2026-06.md` |
| Tokens | `scss/moodle/_tokens.scss` (CSS custom properties); dark mode in `_tokens-dark.scss` |
| Motion | `--ap-duration-*` / `--ap-transition-*` in `_tokens.scss`; they collapse to 0 ms under `prefers-reduced-motion` |
| Lint | `theme/sentientia/.stylelintrc.json` (motion rule on `scss/moodle/partials/_surface-*.scss`) |
| App shell | `templates/shell.mustache` + `scss/moodle/partials/_layout-shell.scss`, built in `classes/output/core_renderer.php` |
| Per-customer branding | `\local_sentientia_platform\customer::branding()`, consumed by `core_renderer` |
| Visual reference | 22 C-suite prototypes at `D:\Claude Local\Moodle Backup\03-prototypes\preview\` |
| Status | `moodle-enhancement/PROJECT-STATE.md` |

### Brand palette (Brand Book 2026-06)

| Role | Hex | Token |
|------|-----|-------|
| Primary, Airpay Blue | `#0066A7` | `--ap-color-primary` (`--ap-blue-600`) |
| Accent, bright blue ("for decks") | `#1985DD` | `--ap-color-accent` (`--ap-blue-bright`) |
| Deep blue (pressed, gradient end) | `#0d5da1` | `--ap-blue-deep` |
| Sky tint (light fills) | `#9cdbf4` | `--ap-blue-sky` |
| Orange, sparing CTA or emphasis only | `#ed692b` | `--ap-color-highlight` (`--ap-orange-500`) |
| Purple, sparing tertiary | `#6d58a5` | `--ap-color-tertiary` (`--ap-purple-500`) |
| Page background | `#F2F4FB` | `--ap-neutral-50` |

- **Teal `#0f7a73` is retired.** The book has no teal; never bring it back.
- `#1985DD` is about 3.9:1 on white (3.86:1): it fails WCAG AA for body text (4.5:1) and passes only for large text and UI parts (3:1). Use the primary for text. (The Brand Book's "~3.4:1" understates it; the verdict is the same.)
- Font: Montserrat 400-800. Spacing: 8 px grid. Radius: 8-20 px.
- New code uses the CSS custom properties (`var(--ap-color-primary)`) and motion tokens (`var(--ap-transition-quick)`), not hex literals.

---

## HISTORICAL REFERENCE: Sprint 1 (Navbar, Footer, Login), Phase 6B

Everything below was written for the airpayux theme in Phase 6B and is kept for reference only. Sprint 1 finished long ago. The live templates in `theme/sentientia/templates/` differ (app shell, sidebar, topbar), so read the real file before copying any snippet. Translate `theme_airpayux` strings to `theme_sentientia`, the teal accent to `#1985DD`, and inline timings to motion tokens.

### Sass token reference (Phase 6B, historical; accent corrected to the Brand Book)

```scss
/* === COLOURS === */
$ap-primary:        #0066A7;   /* Airpay blue */
$ap-primary-light:  #e8f2f9;   /* Tinted hover bg */
$ap-primary-dark:   #004d80;   /* Pressed/focus */
$ap-accent:         #1985DD;   /* Brand bright blue (was teal #0f7a73, retired 2026-06) */
$ap-accent-light:   #e8f4fd;   /* Light blue tint (was teal #e5f4f3) */
$ap-bg:             #F2F4FB;   /* Page background */
$ap-surface:        #ffffff;   /* Card surface */
$ap-surface-2:      #f8f9fc;   /* Nested surfaces */
$ap-border:         #e2e6ef;
$ap-text-primary:   #1a1a2e;
$ap-text-secondary: #5a6070;
$ap-text-muted:     #a0a9b8;
$ap-success:        #16a34a;
$ap-warning:        #d97706;
$ap-error:          #dc2626;

/* === TYPOGRAPHY === */
$ap-font:           'Montserrat', -apple-system, sans-serif;
$ap-text-xs:        0.75rem;   /* 12px */
$ap-text-sm:        0.875rem;  /* 14px */
$ap-text-md:        1rem;      /* 16px body */
$ap-text-lg:        1.125rem;  /* 18px */
$ap-text-xl:        1.25rem;   /* 20px */
$ap-text-2xl:       1.5rem;    /* 24px */
$ap-text-3xl:       1.875rem;  /* 30px */

/* === SPACING (8px grid) === */
$ap-1: 8px; $ap-2: 16px; $ap-3: 24px;
$ap-4: 32px; $ap-6: 48px; $ap-8: 64px;

/* === RADIUS === */
$ap-r-sm: 8px; $ap-r-md: 12px; $ap-r-lg: 16px;
$ap-r-xl: 20px; $ap-r-full: 50%;

/* === SHADOWS === */
$ap-shadow-sm: 0 1px 4px rgba(0,0,0,0.06);
$ap-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
$ap-shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
```

---

## Surface 1 (historical): Navbar — Sprint 1 implementation

**Context variables available in `templates/navbar.mustache`:**
```
{{homeurl}}             Site home URL
{{sitename}}            Site name (pre-escaped)
{{logourl}}             Logo URL (tenant-aware via core_renderer.php)
{{primarynavigation}}   Object — .items array with [{text, url, isactive, icon}]
{{usermenu}}            Object — logged-in user dropdown
{{loggedin}}            Boolean
{{userpicture}}         User avatar URL
{{fullname}}            User full name
{{sesskey}}             CSRF token
{{output.custom_menu}}  Custom menu HTML
```

```mustache
{{! templates/navbar.mustache }}
<nav class="airpay-navbar" role="navigation" aria-label="{{# str }}mainnavigation, moodle{{/ str }}">
    <div class="airpay-navbar__container">

        {{! Brand / Logo }}
        <a href="{{homeurl}}" class="airpay-navbar__brand" aria-label="{{sitename}}">
            {{# logourl }}
            <img src="{{logourl}}" alt="{{sitename}}" class="airpay-navbar__logo"
                 width="140" height="40"/>
            {{/ logourl }}
            {{^ logourl }}
            <span class="airpay-navbar__site-name">{{sitename}}</span>
            {{/ logourl }}
        </a>

        {{! Primary Navigation }}
        <div class="airpay-navbar__nav" role="menubar">
            {{# primarynavigation.items }}
            <a href="{{url}}" class="airpay-navbar__link{{# isactive }} airpay-navbar__link--active{{/ isactive }}"
               role="menuitem" {{# isactive }}aria-current="page"{{/ isactive }}>
                {{text}}
            </a>
            {{/ primarynavigation.items }}
        </div>

        {{! User Area }}
        <div class="airpay-navbar__user">
            {{# loggedin }}
            <div class="airpay-navbar__avatar-wrap" data-user-menu>
                <img src="{{userpicture}}" alt="{{fullname}}"
                     class="airpay-navbar__avatar" width="36" height="36"/>
                <span class="airpay-navbar__user-name">{{fullname}}</span>
            </div>
            {{{ usermenu }}}
            {{/ loggedin }}
            {{^ loggedin }}
            <a href="{{loginurl}}" class="airpay-btn airpay-btn--primary airpay-btn--sm">
                {{# str }}login, core{{/ str }}
            </a>
            {{/ loggedin }}
        </div>

        {{! Mobile toggle }}
        <button class="airpay-navbar__toggle" aria-label="{{# str }}togglenavigation, core{{/ str }}"
                aria-expanded="false" aria-controls="airpay-mobile-menu">
            <span class="airpay-navbar__toggle-bar"></span>
            <span class="airpay-navbar__toggle-bar"></span>
            <span class="airpay-navbar__toggle-bar"></span>
        </button>
    </div>

    {{! Mobile Drawer }}
    <div class="airpay-navbar__mobile-menu" id="airpay-mobile-menu" aria-hidden="true">
        {{# primarynavigation.items }}
        <a href="{{url}}" class="airpay-navbar__mobile-link{{# isactive }} active{{/ isactive }}">
            {{text}}
        </a>
        {{/ primarynavigation.items }}
    </div>
</nav>
```

```scss
/* Navbar SCSS — add to custom_changes.scss under /* === NAVBAR === */
.airpay-navbar {
    background: $ap-surface;
    border-bottom: 1px solid $ap-border;
    box-shadow: $ap-shadow-sm;
    position: sticky;
    top: 0;
    z-index: 1000;

    &__container {
        display: flex;
        align-items: center;
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 $ap-3;
        height: 64px;
        gap: $ap-2;
    }

    &__brand { display: flex; align-items: center; text-decoration: none; flex-shrink: 0; }
    &__logo  { height: 40px; width: auto; }
    &__site-name { font-family: $ap-font; font-weight: $ap-weight-bold; color: $ap-primary; font-size: $ap-text-xl; }

    &__nav { display: flex; align-items: center; gap: $ap-1; flex: 1; padding: 0 $ap-2; }

    &__link {
        font-family: $ap-font;
        font-weight: $ap-weight-med;
        font-size: $ap-text-sm;
        color: $ap-text-secondary;
        text-decoration: none;
        padding: 6px $ap-2;
        border-radius: $ap-r-sm;
        transition: all 0.2s ease;
        white-space: nowrap;

        &:hover { color: $ap-primary; background: $ap-primary-light; }
        &--active { color: $ap-primary; background: $ap-primary-light; font-weight: $ap-weight-semi; }
    }

    &__user { display: flex; align-items: center; gap: $ap-1; flex-shrink: 0; }

    &__avatar-wrap {
        display: flex; align-items: center; gap: $ap-1;
        cursor: pointer; padding: 4px 8px;
        border-radius: $ap-r-sm;
        &:hover { background: $ap-primary-light; }
    }

    &__avatar {
        width: 36px; height: 36px;
        border-radius: $ap-r-full;
        border: 2px solid $ap-primary;
        object-fit: cover;
    }

    &__user-name {
        font-family: $ap-font; font-size: $ap-text-sm;
        font-weight: $ap-weight-med; color: $ap-text-primary;
    }

    &__toggle {
        display: none; flex-direction: column; gap: 5px;
        background: none; border: none; cursor: pointer; padding: $ap-1;
        &-bar { display: block; width: 22px; height: 2px; background: $ap-text-primary; border-radius: 2px; }
    }

    &__mobile-menu {
        display: none; flex-direction: column;
        background: $ap-surface; border-top: 1px solid $ap-border;
        padding: $ap-2 $ap-3;
    }

    &__mobile-link {
        font-family: $ap-font; font-weight: $ap-weight-med; color: $ap-text-secondary;
        text-decoration: none; padding: $ap-1 0; border-bottom: 1px solid $ap-border;
        &.active { color: $ap-primary; }
        &:last-child { border-bottom: none; }
    }

    /* Mobile (590px) */
    @media (max-width: 590px) {
        &__nav    { display: none; }
        &__user-name { display: none; }
        &__toggle { display: flex; }
        &__mobile-menu.is-open { display: flex; }
    }
}
```

---

## Surface 2 (historical): Footer — Sprint 1 implementation

```mustache
{{! templates/footer.mustache }}
<footer class="airpay-footer" role="contentinfo">
    <div class="airpay-footer__container">
        <div class="airpay-footer__brand">
            <a href="{{homeurl}}" class="airpay-footer__logo-link">
                <img src="{{logourl}}" alt="{{sitename}}" class="airpay-footer__logo" height="32"/>
            </a>
            <p class="airpay-footer__tagline">
                {{# str }}footer_tagline, theme_airpayux{{/ str }}
            </p>
        </div>
        {{# footnote }}
        <div class="airpay-footer__note">{{{ footnote }}}</div>
        {{/ footnote }}
        <div class="airpay-footer__links">
            {{# helplink }}<a href="{{helplink}}" class="airpay-footer__link">{{# str }}help, core{{/ str }}</a>{{/ helplink }}
        </div>
    </div>
    {{! Required Moodle footer HTML — NEVER omit }}
    {{{ output.standard_footer_html }}}
    {{{ output.standard_end_of_body_html }}}
</footer>
```

---

## Surface 3 (historical): Login Page — Sprint 1 implementation

```mustache
{{! templates/core/loginform.mustache }}
<div class="airpay-login">
    <div class="airpay-login__split">
        {{! Left panel — branding }}
        <div class="airpay-login__panel">
            <img src="{{logourl}}" alt="Airpay Academy" class="airpay-login__panel-logo"/>
            <h2 class="airpay-login__panel-title">{{# str }}login_panel_title, theme_airpayux{{/ str }}</h2>
            <p class="airpay-login__panel-sub">{{# str }}login_panel_sub, theme_airpayux{{/ str }}</p>
        </div>

        {{! Right panel — form }}
        <div class="airpay-login__form-wrap">
            <div class="airpay-login__card">
                <h1 class="airpay-login__title">{{# str }}login, core{{/ str }}</h1>

                {{# errorformatted }}
                <div class="airpay-login__error" role="alert">
                    {{{ errorformatted }}}
                </div>
                {{/ errorformatted }}

                <form action="{{loginurl}}" method="post" class="airpay-login__form"
                      autocomplete="on">
                    <input type="hidden" name="logintoken" value="{{logintoken}}"/>
                    <input type="hidden" name="anchor" value=""/>

                    <div class="airpay-login__field">
                        <label for="username" class="airpay-login__label">
                            {{# str }}username, core{{/ str }}
                        </label>
                        <input type="text" id="username" name="username"
                               value="{{username}}" class="airpay-login__input"
                               autocomplete="username" spellcheck="false"
                               autofocus="{{# autofocuscontrol}}{{#username}}1{{/username}}{{/ autofocuscontrol}}"/>
                    </div>

                    <div class="airpay-login__field">
                        <label for="password" class="airpay-login__label">
                            {{# str }}password, core{{/ str }}
                        </label>
                        <div class="airpay-login__input-wrap">
                            <input type="password" id="password" name="password"
                                   class="airpay-login__input" autocomplete="current-password"/>
                            <button type="button" class="airpay-login__toggle-pw"
                                    aria-label="{{# str }}showpassword, core{{/ str }}">
                                <span class="icon">👁</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="airpay-btn airpay-btn--primary airpay-btn--full">
                        {{# str }}login, core{{/ str }}
                    </button>

                    {{# canresetpassword }}
                    <a href="{{forgotpasswordurl}}" class="airpay-login__forgot">
                        {{# str }}forgotten, core{{/ str }}
                    </a>
                    {{/ canresetpassword }}
                </form>

                {{# hasidentityproviders }}
                <div class="airpay-login__sso">
                    <span class="airpay-login__sso-divider">{{# str }}or, core{{/ str }}</span>
                    {{# identityproviders }}
                    <a href="{{url}}" class="airpay-login__sso-btn">
                        {{# iconurl }}<img src="{{iconurl}}" alt="" width="20" height="20"/>{{/ iconurl }}
                        {{name}}
                    </a>
                    {{/ identityproviders }}
                </div>
                {{/ hasidentityproviders }}
            </div>
        </div>
    </div>
</div>
```

---

## Deploy Sequence (current paths; the file list is an example)

```powershell
$THEME_SRC = "D:\Claude Local\airpay-ld-os\theme\sentientia"      # $env:AIRPAY_THEME_SRC
$THEME_DST = "C:\xampp\htdocs\moodle5\public\theme\sentientia"    # $env:AIRPAY_THEME_DST

# 1. PHP lint (source file)
php -l "$THEME_SRC\layout\login.php"

# 2. Copy only the files you changed
@(
    "templates\navbar.mustache",
    "layout\login.php",
    "scss\moodle\custom_changes.scss"
) | ForEach-Object {
    Copy-Item "$THEME_SRC\$_" "$THEME_DST\$_" -Force
    Write-Host "Copied: $_"
}

# 3. Purge caches (admin\cli sits one level above public\; run with cwd = public\)
Push-Location "C:\xampp\htdocs\moodle5\public"
php ..\admin\cli\purge_caches.php
Pop-Location

# 4. Test URLs
Write-Host ""
Write-Host "Test these URLs (Ctrl+Shift+R first):"
Write-Host "  Login:     http://localhost:8080/login/index.php"
Write-Host "  Dashboard: http://localhost:8080/my/   (/my/dashboard.php is a BizLMS shim that redirects here)"
Write-Host "  Mobile:    590px viewport in Chrome devtools"
```

A version bump in `version.php` needs `php ..\admin\cli\upgrade.php --non-interactive` before the purge. UAT deploys go through `tools/uat/deploy_to_uat.sh` and only on Nitin's call.
