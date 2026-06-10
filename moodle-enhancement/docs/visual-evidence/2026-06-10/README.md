# Visual evidence — 2026-06-10

## P1-3 / course-view de-brand  (commit `76d1f1965`)

**Surface:** `/course/view.php` hero header (`theme/sentientia/templates/course_full_header.mustache`).

**Change**
- Overlay de-branded: BizLMS blue `#305D94` → Sentientia **primary → primary-dark → accent**
  gradient (`var(--ap-*)` tokens with hex fallback), opacity `0.92`. White text stays readable.
- Hardcoded English → `{{#str}}` (CLAUDE.md forbids hardcoded English in `.mustache`); fixed the
  "Enrollements" typo. New `theme_sentientia` keys: `course_userenrolments`, `course_usercompletion`,
  `course_start`; reuses core `description`.

**Verification — browser-free (served-CSS inspection).** Confirmed the change compiled into the
*served* stylesheet (`GET /theme/styles.php/sentientia/<rev>/all`):

```
.courseheader::before{background:linear-gradient(135deg,var(--ap-primary,#0066A7) 0%,
    var(--ap-primary-dark,#004d80) 55%,var(--ap-accent,#0f7a73) 100%);content:"";top:0;
    position:absolute;width:100%;height:100%;right:0;opacity:.92}
```
- `#305d94` (old BizLMS blue): **0 occurrences** in the entire served CSS.
- i18n keys present in the deployed lang pack; `{{#str}}` resolves them (no `[[missing]]` markers).

**Live review:** http://localhost:8080/course/view.php?id=302 (warm load).

### Note — local screenshot tooling unavailable
Playwright / headless-Chromium screenshots time out on this XAMPP dev box: heavy themed pages plus
the runtime SCSS recompile after a cache purge exceed the wait (≥90s page loads). Verification for
this change is therefore the served-CSS inspection above, plus CI `render-smoke` on Linux. This is
a **local environment limitation, not a product defect** — the page serves HTTP 200 and the CSS is
correct.
