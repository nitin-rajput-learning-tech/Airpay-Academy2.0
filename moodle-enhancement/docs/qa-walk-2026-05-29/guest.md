# QA Walk — Persona: Guest (unauthenticated)

**Env:** local XAMPP (Moodle 5.1.3+, latest workspace code synced), dark mode active.
**Method:** chrome-devtools real browser (page 3) + same-origin `fetch()` probes for breadth.

## Surfaces visited

| # | Surface | URL | HTTP | Verdict |
|---|---------|-----|------|---------|
| 1 | Landing (marketing) | `/` | 200 | ✅ Rich LXP storefront — hero, stats (183+ courses / 670+ learners), feature cards, 6 featured courses, 3-pillars, testimonials, 8-Q FAQ, CTAs, footer |
| 2 | Login | `/login/index.php` | 200 | ✅ Renders (title "Log in to the site") |
| 3 | Signup | `/local/airpay_users/signup.php` | 200 | ✅ Verified clean (see below) |
| 4 | Forgot password | `/login/forgot_password.php` | 200 | ✅ Renders |
| 5 | Privacy | `/local/airpay_pages/index.php?page=privacy` | 200 | ✅ |
| 6 | Terms | `…?page=terms` | 200 | ✅ |
| 7 | Help | `…?page=help` | 200 | ✅ |
| 8 | Contact | `…?page=contact` | 200 | ✅ |
| 9 | Course detail | `/local/search/coursedetails.php?id=71` | 404 | ⚠️ **Env-gap** (G-2) — not a product bug |

## Findings

### G-1 (P1) — FIXED ✅
Navbar **"Register"** CTA (`frontpage.php:372`) and the OTP-login "register with us" form
(`otploginform.mustache:134`) pointed at the legacy `/local/users/signup.php` → **404** locally
(and a stale/inconsistent signup on prod). The hero CTA + footer + onboarding already use the
fork's canonical `/local/airpay_users/signup.php`. **Fix:** repointed both to the canonical URL.
Safe on production (airpay_users is deployed there; this only makes the navbar consistent with the
rest of the app). **Verified:** navbar Register now → `/local/airpay_users/signup.php` → HTTP 200.

### G-2 (env-gap, no code change) — DOCUMENTED
Featured-course **"Details"/"Enroll"** buttons (and the catalog) link to
`/local/search/coursedetails.php?id=N`, which **404s locally only**. `local_search` is a BizLMS
**production** plugin not mirrored to the workspace/XAMPP (confirmed: `local_search`/`local_users`/
`local_courses` absent from `config_plugins`; `local_custom_category` table absent; fork code guards
every BizLMS read with `table_exists()`; `category_manager.php` docblocks treat `coursedetails.php`
as an existing file being gradually refactored). The 6 source references are **correct for
production** — rewriting them would break prod course-detail navigation (backwards-compat =
non-negotiable). All 6 featured course ids (71/403/72/66/399/69) exist and are visible; only the
local link *target* is missing. **Action: none in code.** Verify course-detail/enrol flows on
staging/production.

## Verifications (no bug)
- **Signup form**: honeypot `#fitem_id_honeypot_url` is `display:none` ✅ (the prior class→id fix holds);
  all fields present — firstname/lastname/email/**passwordunmask**/country/language(×5: en,sw,mr,hi,kn)/
  ToS-consent/submit ✅; card top-aligned (no clip) ✅; dark mode renders ✅.
- **Password "Click to enter text"** is Moodle's native `passwordunmask` widget (`fieldtype:passwordunmask`,
  real `<input type=password class="d-none">` revealed on click) — **standard Moodle, not a bug.**
- **Required red marker** is the standard `core/new_req` icon image — not a broken glyph.

## Screenshots
- `guest-01-landing.png` (full page) · `guest-02-signup.png` · `guest-03-login.png`

## Env note
BizLMS plugin suite (`local_search`, `local_users`, `local_courses`, `local_request`, `local_programs`,
`local_classroom`, + `local_custom_category` table) is **not deployed to local XAMPP** — only the fork's
`local_airpay_*` / `local_sentientia_*` plugins are. Any guest/learner flow that lands on a BizLMS page
(course detail, legacy enrol) cannot be exercised locally; verify on staging/production.
