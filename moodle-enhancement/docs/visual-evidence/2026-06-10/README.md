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

---

## P1-3 / in-course activity surfaces — structural coverage  (2026-06-10)

**Question:** do in-course activity pages (quiz / scorm / assign / forum *view*) need
individual Sentientia restyling?

**Answer: no — they inherit the already-upgraded `course.php` layout.**

Evidence (`theme/sentientia/config.php`):
- `incourse` → `course.php` (config.php:70-74) — the default page layout for any page that
  passes `$cm` to `require_login()`, i.e. **every activity-module `view.php`**.
- `course` → `course.php` (config.php:52-57) — the main course page.

Both resolve to the same layout file and the same `theme_sentientia/course` Mustache template
(`course.php:250`). `course.php` is a purpose-built Sentientia layout: it injects the sticky
course-progress bar, breadcrumb, next-activity CTA, and a completion-aware course-player sidebar
module tree (`course.php:135-244`). The main course page was design-validated under task #163
(Goal A.x — "Sentientia design on /course/view.php"); in-course activity views share that exact
chrome by construction.

**Payment / cart surfaces** use the `standard` layout → `columns2.php` → `theme_sentientia/columns2`,
whose app-shell sidebar is emitted by `core_renderer::airpay_shell_start()` (columns2.php:112,
columns2.mustache:84). These were restyled under tasks #324 (Netflix storefront), #341-346 (poster
cards), #371 (footer-overlap fix) and P-02 (cart badge).

**Conclusion:** P1-3 needs no per-activity surface work. The one remaining unverified item is a
*fresh visual screenshot* of an in-course quiz/scorm page — blocked only by the local
screenshot-tooling limitation above (slow XAMPP + post-purge SCSS recompile) and the Chrome-extension
`localhost:8080` permission grant, **not by any product gap**. CI `render-smoke` (Gate 1) exercises
the course surface on Linux.
