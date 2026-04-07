---
name: airpayux Frontend Design
description: Guides frontend implementation for the airpayux Moodle theme. Provides complete component code, Mustache context variables, SCSS tokens, and deploy sequences for Sprint 1 (Navbar, Footer, Login) and all 10 surfaces of Phase 6B.
metadata:
  priority: 9
  pathPatterns:
    - '*/theme/airpayux/**'
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

# airpayux Frontend Design Skill — Phase 6B

You are implementing the airpayux theme for Airpay Academy. Every surface must match the 22 C-suite approved prototypes at `D:\Claude Local\Moodle Backup\03-prototypes\preview\`.

**Reference prototype directory first before writing any component.**

---

## Current Sprint: Sprint 1 — Navbar, Footer, Login

**Status:** Starting now
**Files to create/edit:**
1. `templates/navbar.mustache`
2. `templates/footer.mustache`
3. `templates/core/loginform.mustache`
4. `layout/login.php`
5. `scss/moodle/custom_changes.scss` (component additions)

---

## Complete Design Token Reference

```scss
/* === COLOURS === */
$ap-primary:        #0066A7;   /* Airpay blue */
$ap-primary-light:  #e8f2f9;   /* Tinted hover bg */
$ap-primary-dark:   #004d80;   /* Pressed/focus */
$ap-accent:         #0f7a73;   /* Teal */
$ap-accent-light:   #e5f4f3;
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

## Surface 1: Navbar — Complete Implementation

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

## Surface 2: Footer — Complete Implementation

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

## Surface 3: Login Page — Complete Implementation

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

## Deploy Sequence (Sprint 1)

```powershell
$AIRPAY_SRC = "D:\Claude Local\airpay-ld-os\moodle-enhancement\theme\airpayux"
$AIRPAY_DST = "C:\xampp\htdocs\moodle\theme\airpayux"

# 1. PHP lint
php -l "$AIRPAY_DST\layout\login.php"

# 2. Copy changed Sprint 1 files
@(
    "templates\navbar.mustache",
    "templates\footer.mustache",
    "templates\core\loginform.mustache",
    "layout\login.php",
    "scss\moodle\custom_changes.scss"
) | ForEach-Object {
    Copy-Item "$AIRPAY_SRC\$_" "$AIRPAY_DST\$_" -Force
    Write-Host "Copied: $_"
}

# 3. Purge caches
php "C:\xampp\htdocs\moodle\admin\cli\purge_caches.php"

# 4. Test URLs
Write-Host ""
Write-Host "Test these URLs (Ctrl+Shift+R first):"
Write-Host "  Login:     http://localhost:8080/moodle/"
Write-Host "  Dashboard: http://localhost:8080/moodle/my/dashboard.php"
Write-Host "  Mobile:    590px viewport in Chrome devtools"
```
