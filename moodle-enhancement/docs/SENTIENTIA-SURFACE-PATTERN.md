# Sentientia surface-restyle pattern

**Status:** Established 2026-05-22 → 2026-05-23 (Goal A.x audit).
**Owner:** theme_airpayux.
**Codified in:** `theme/airpayux/scss/moodle/partials/_surface-profile.scss`.

This document codifies the pattern used to convert a vanilla Moodle Boost
page into a Sentientia surface. Nine surfaces have been restyled this way
(listed below). When restyling the tenth, follow this pattern instead of
re-inventing — it has a verified working shape.

The general design tokens (colours, type scale, spacing, breakpoints)
live in `.claude/rules/frontend.md` and are auto-loaded when editing
theme files. This doc captures the *application* of those tokens to
the specific shape of "vanilla Moodle page → Sentientia surface."

---

## The nine surfaces (as of v4.1.0-goal-a-audit)

| Body selector | Surface | SCSS block in `_surface-profile.scss` |
|---|---|---|
| `body#page-user-profile` | `/user/profile.php` | "Goal A.x — `/user/profile.php`" |
| `body#page-badges-mybadges` | `/badges/mybadges.php` | "Goal A.x — `/badges/mybadges.php`" |
| `body#page-grade-report-overview-index` | `/grade/report/overview/` | "Goal A.x — `/grade/report/overview/`" |
| `body.path-admin` | `/admin/*` interior | "Goal A.x — `/admin/*` interior" |
| `body.path-course` AND not edit | `/course/view.php` | "Goal A.x — `/course/view.php`" |
| `body.path-grade-report-grader` | `/grade/report/grader/` | "Goal A.x — `/grade/report/grader/`" |
| `body#page-user-edit` | `/user/edit.php` | "Goal A.x — `/user/edit.php`" |
| `body#page-user-preferences` | `/user/preferences.php` | "Goal A.x — `/user/preferences.php`" |
| `body#page-calendar-view` | `/calendar/view.php` month | "Goal A.x — `/calendar/view.php`" |

---

## The pattern (canonical SCSS skeleton)

```scss
/* ═══════════════════════════════════════════════════════════════════════
   Goal A.x (YYYY-MM-DD) — Sentientia design on <URL path>
   ───────────────────────────────────────────────────────────────────────
   <2-3 sentence rationale: why this surface needs polish + what the
    visible improvement will be>

   Body scope: `<selector>` — chosen because <reason>. The selector is
   disjoint from <sibling surface> which uses <other selector>.
   ═══════════════════════════════════════════════════════════════════════ */
body<your-selector> {

    /* 1. Container */
    #region-main {
        max-width: 1080px;    // narrower for forms, 1280px for tables
        margin: 0 auto;
        padding: 16px 0 32px;
    }

    /* 2. Section heading — the signature Sentientia treatment */
    #region-main h3,
    #region-main fieldset h3,
    /* (whichever selector is the visible section-title in this page) */ {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--ap-color-text-secondary, #5a6070);
        margin: 0 0 16px;
        padding: 0 0 12px;
        position: relative;
        border-bottom: 1px solid var(--ap-color-border, #e5e7eb);
    }
    /* The 32px brand accent bar — the strongest Sentientia visual marker */
    #region-main h3::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 32px;
        height: 2px;
        background: var(--ap-color-primary, #0066A7);
        border-radius: 2px;
    }

    /* 3. Card chrome — wrap the content in a Sentientia surface */
    #region-main .<wrapper-class>,
    /* (whichever element wraps the content — varies per page) */ {
        background: var(--ap-color-bg-surface, #ffffff);
        border: 1px solid var(--ap-color-border, #e5e7eb);
        border-radius: 16px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        padding: 20px 24px;
        margin: 0 0 16px;
    }

    /* 4. Form inputs (if applicable) */
    #region-main input[type="text"],
    #region-main select,
    #region-main textarea {
        border: 1px solid var(--ap-color-border, #e5e7eb);
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 14px;
        background: var(--ap-color-bg-surface-alt, #f8f9fc);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    #region-main input:focus {
        outline: none;
        border-color: var(--ap-color-primary, #0066A7);
        box-shadow: 0 0 0 3px rgba(0, 102, 167, 0.12);
        background: var(--ap-color-bg-surface, #ffffff);
    }

    /* 5. Links — brand-blue with hover transform */
    #region-main a {
        color: var(--ap-color-primary, #0066A7);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s ease, transform 0.05s ease;
    }
    #region-main a:hover {
        color: var(--ap-color-primary-dark, #004d80);
        text-decoration: underline;
    }

    /* 6. Mobile breakpoint @ 768px — stack columns, drop padding */
    @media (max-width: 768px) {
        #region-main { padding: 12px 0 24px; }
        #region-main .<wrapper-class> { padding: 16px 18px; }
        /* If form: stack label-above-input */
        #region-main .col-md-3,
        #region-main .col-md-9 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
}
```

---

## The five rules (the WHY behind the pattern)

### Rule 1 — Pick the tightest body selector

Moodle generates two types of body selectors automatically:
  - `body#page-X-Y-Z` from the URL path (e.g.,
    `body#page-grade-report-overview-index` for
    `/grade/report/overview/index.php`)
  - `body.path-X` from the URL prefix (e.g., `body.path-grade-report`
    for any `/grade/report/*` page)

**Use `#page-X-Y-Z` when you want to scope to ONE specific page.**
**Use `.path-X` when you want to cover all pages under a path prefix.**

The grader vs overview restyle uses both: overview is scoped to
`body#page-grade-report-overview-index` (one page), grader is scoped to
`body.path-grade-report-grader` (covers grader-index + any nested
grader views). The two scopings are **disjoint** — `path-grade-report`
is shared by both pages, but only the more specific selectors apply.

### Rule 2 — Verify disjointness via DOM inspection

After deploying a new surface restyle, navigate to a SIBLING page that
shares the broader path-class with your new surface and confirm
`getComputedStyle()` shows the OLD styling, not the new. This is the
single most important regression-prevention step.

```javascript
// Run in browser console after deploy
const heading = document.querySelector('#region-main h3');
const cs = getComputedStyle(heading);
console.log({
  fontSize: cs.fontSize,         // should match the surface you ARE on
  textTransform: cs.textTransform,
  bodyClass: document.body.className
});
```

This step caught zero regressions in 9 surface restyles — because we
checked. Without it, the body-scope strategy would have accumulated
silent regressions.

### Rule 3 — The signature is the 32px brand accent bar

Every Sentientia section heading has the same `::after` accent bar:
32px wide, 2px tall, brand-primary, 2px radius, anchored to the
bottom-left of the heading. This is the strongest **single visual
marker** that distinguishes Sentientia from vanilla Moodle. When in
doubt, add the bar.

### Rule 4 — Uppercase letter-spaced 13px → secondary text

Section h3 titles use exactly:
  - `font-size: 13px` (matches table thead pattern)
  - `font-weight: 700`
  - `text-transform: uppercase`
  - `letter-spacing: 0.6px`
  - `color: var(--ap-color-text-secondary, #5a6070)` (NOT primary text)

The secondary colour is intentional — it keeps the heading from
shouting. Combined with the brand accent bar, the result reads as
"label" not "title."

### Rule 5 — Always include the mobile breakpoint

Every surface restyle must include a `@media (max-width: 768px)`
override that:
  - Drops `#region-main` padding to `12px 0 24px`
  - Drops wrapper padding to `16px 18px`
  - Stacks `col-md-3 + col-md-9` to full-width (`flex: 0 0 100%`) for
    form layouts

The 768px breakpoint matches the Bootstrap "tablet-down" boundary.
Verification at 590×800 in Chrome DevTools is the standard
mobile-responsive sanity check.

---

## The seven-step shipment workflow

This is the cadence that produced 9 successful restyles in this audit
cycle. Each step is verified by a concrete artifact — no skipping.

1. **Inspect** — navigate to the target page in the browser, run a
   `getComputedStyle()` + `Array.from(document.body.classList)` probe
   to identify the body selector, the wrapper class, and the section-
   heading element. Take a "before" screenshot.

2. **Write SCSS** — add a `body<selector> { ... }` block to
   `theme/airpayux/scss/moodle/partials/_surface-profile.scss`. Follow
   the canonical skeleton above. 30-60 lines is typical; >100 lines
   means you're probably restyling the wrong scope.

3. **Bump version** — increment `theme/airpayux/version.php`'s
   `$plugin->version` (YYYYMMDDNN) and `$plugin->release`. The version
   bump is what triggers SCSS recompilation on the next request.

4. **Deploy** — copy `_surface-profile.scss` and `version.php` to the
   XAMPP path, `rm -rf moodledata/localcache/theme/airpayux/`, run
   `php admin/cli/upgrade.php --non-interactive`.

5. **Verify** — reload the page (Ctrl+Shift+R), run
   `getComputedStyle()` on the targeted element to confirm the
   styling is applied. **Walk the sibling page** (Rule 2) and confirm
   no regression.

6. **Screenshot** — save `<surface>-after.png` to
   `docs/visual-evidence/YYYY-MM-DD/`. Also verify at 590×800 mobile
   viewport (`Ctrl+Shift+M` or the resize_page MCP call).

7. **Commit + push** — single commit with a `feat(theme): Sentientia
   ... (Goal A.x)` subject, full multi-paragraph body explaining what
   was scoped + verified.

---

## When to NOT follow this pattern

Some Moodle surfaces don't benefit from the Sentientia card chrome:

  - **Custom plugin pages** (`/local/airpay_*/...`) — these are
    already Sentientia by construction. Don't add another card on top
    of their existing chrome.
  - **The login page** (`/login/index.php`) — has its own full-bleed
    layout via `layout/login.php` and `templates/core/loginform.mustache`.
  - **Embedded contexts** (`/mod/scorm/view.php` content frame, lightbox
    modals, BigBlueButton iframes) — these are inside-out and adding
    chrome breaks them.
  - **Pages with the file picker** (`/user/files.php`) — the file
    picker widget is too complex and brittle to restyle reliably.

When the page truly is "vanilla Moodle inside an enterprise shell" and
the user spends real time there, restyle it. When it's a transient or
embedded context, don't.

---

## See also

  - `.claude/rules/frontend.md` — design tokens (colours, spacing,
    type scale, breakpoints) — auto-loaded when editing theme files
  - `theme/airpayux/scss/moodle/partials/_surface-profile.scss` — the
    9 surface blocks (read them as canonical examples)
  - `docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md`
    — the structural patterns that make the bug class extinct
  - `theme/airpayux/tests/README.md` — PHPUnit runbook for the
    invariant suites that back this design system
  - `docs/visual-evidence/YYYY-MM-DD/` — the screenshots and READMEs
    from each surface restyle session
