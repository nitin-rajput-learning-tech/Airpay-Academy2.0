# QA Walk — External Public Learner
**Date:** 2026-05-29  
**Persona:** qa_public / `Qa@Airpay#26` (userid 3422, tenant `/77 Public`)  
**Shell:** Learner  
**Walked by:** QA sub-agent via Chrome DevTools MCP  

---

## Step 1 — Login

**Result: OK.**  
Login succeeded on first attempt. Redirected to `/local/airpay_pages/onboarding.php` (first-login onboarding interceptor). Onboarding skipped via "Skip for now" button → redirected to `/my/` dashboard. `M.cfg.userid = 3422` confirmed in page JS context.

---

## Step 2 — Dashboard + Sidebar

**Sidebar items observed (learner shape):**

| # | Label | URL |
|---|-------|-----|
| 1 | Dashboard | `/my/` |
| 2 | My Courses | `/local/airpay_catalog/mycourses.php` |
| 3 | Catalog | `/local/airpay_catalog/public.php` |
| 4 | **My Cart** | `/local/airpay_cart/index.php` |
| 5 | Certificates | `/local/airpay_pages/certificates.php` |
| 6 | Profile | `/local/airpay_users/profile.php` |

**Cart present: YES** — "My Cart" link visible in sidebar with shopping-cart icon. This confirms the Public-tenant cart config is active.  
**Learner shape confirmed:** No admin, manager, trainer, or compliance links. Correct for role.  
**Dead sidebar links: None.** All 5 `/local/airpay*` links returned HTTP 200.

Screenshot: `public-01-dashboard.png`

---

## Step 3 — Multi-Tenant Isolation

### Catalog content (`/local/airpay_catalog/public.php`)
- 183 courses shown in the Public catalog.
- Courses in category `78 "Public"` (top-level category, parent=0 — i.e. the Public-tenant root).
- "Aptitude Test Advanced" (course id=71) appears in the catalog. **DB check confirms** this course is in category 78 "Public" — it is a Public-tenant course, not an Airpay /1 internal course. No isolation leak here.
- No Airpay-internal marker text (`costcenter 1`, `employee only`, `internal`) found in catalog page.

### Direct Moodle course access (`/course/view.php?id=71`)
- Navigating directly via Moodle's standard course view redirected to `/my/` (dashboard).
- The redirect behavior indicates Moodle core enforced an enrolment/access check before allowing course view.
- This is expected and correct — the user is not enrolled, so Moodle blocks direct access and redirects to dashboard rather than showing an error. **Not a leak.**

### Catalog wrapper access (`/local/airpay_catalog/course.php?id=71`)
- Renders the course detail page for "Aptitude Test Advanced" with an Enroll button.
- This is the designed Public-facing catalog page — showing a Public-category course to a Public-tenant user is correct.

**Tenant isolation verdict: HOLDS.** No Airpay /1 employee-only courses observed in catalog. All visible courses belong to category 78 "Public". No BizLMS `open_costcenter_course` table exists on this instance (BizLMS uses the category hierarchy for tenant scoping). Moodle-core access control correctly blocks unenrolled direct course access.

Screenshot: `public-02-catalog.png`

---

## Step 4 — Cart-Based Enrol Flow

### Add-to-cart test
- Triggered: `/local/airpay_catalog/course.php?id=403&action=addtocart&sesskey=<sesskey>` (POSH Training, a Public-tenant course).
- Redirected to: `?added=1` — page shows **"POSH Training added to your cart. View Cart (1)"** notification.
- **Cart add: WORKS.** The `addtocart` action accepts the request.

### View cart
- "View Cart (1)" link on the confirmation page points to `/local/airpay_catalog/cart.php` (NOT `/local/airpay_cart/index.php`).
- `/local/airpay_catalog/cart.php` shows: **"Your Cart (1 items) — POSH Training — Free — Enroll in All (Free)"**.
- Cart renders correctly with item, price (Free), and checkout button.

### Cart URL discrepancy (P-01 below)
- Sidebar "My Cart" link → `/local/airpay_cart/index.php` → shows **empty cart**.
- Course "View Cart" notification link → `/local/airpay_catalog/cart.php` → shows **1 item**.
- These are two different pages. The sidebar link points to the wrong cart URL.

### Stopped before payment: CONFIRMED.  
Observed "Enroll in All (Free)" button — did not click it. No payment step reached.

**Cart-enrol flow verdict:**  
Add-to-cart works for Public tenant. The cart itself (`catalog/cart.php`) renders items correctly.  
**E-01 comparison:** The employee walk found `action=addtocart` → redirect to dashboard, nothing enrolled. For Public, the add-to-cart succeeds and lands on `?added=1` with a confirmation message. **E-01 is specific to the Airpay free-enrol path (self-enrol method), not to the cart layer itself.** The Public cart path is functional at the add + view stage.

Screenshot: `public-03-cart.png`

---

## Step 5 — Breadth Probe

| URL | Status | Final URL | Title | Notes |
|-----|--------|-----------|-------|-------|
| `/local/airpay_catalog/public.php` | 200 | same | Course Catalog | OK — 183 courses |
| `/local/airpay_catalog/mycourses.php` | 200 | same | My courses | OK — empty (not enrolled) |
| `/local/airpay_cart/index.php` | 200 | same | My Cart | Shows empty (P-01: wrong URL) |
| `/local/airpay_catalog/cart.php` | 200 | same | Your Cart | Shows item correctly |
| `/local/airpay_users/profile.php` | 200 | same | QA Public - Profile | OK |
| `/local/airpay_pages/certificates.php` | 200 | same | My Certificates | OK — empty |
| `/course/view.php?id=71` | 200 | `/my/` | Dashboard | Moodle core redirect — expected |

No 404s, no 403s, no exception errors. All plugin pages render. No env-gap issues.

---

## Step 6 — Console Errors

**Zero console errors** across all pages visited.  
Stalled resources: YUI combo, theme CSS, Google Fonts, PWA register.js, polyfill.js — all normal Moodle lazy-load patterns, not errors.

---

## Candidate Bugs

| ID | Sev | Description |
|----|-----|-------------|
| **P-01** | P2 | **Sidebar "My Cart" links to wrong URL.** Sidebar → `/local/airpay_cart/index.php` (empty). Correct cart is `/local/airpay_catalog/cart.php`. After adding a course the notification correctly links to `catalog/cart.php`, but the persistent sidebar nav link is stale. User who navigates via sidebar sees an empty cart even after adding items. |
| **P-02** | P3 | **Cart badge in navbar does not update after add-to-cart.** After adding POSH Training, no badge count appeared on the cart icon in the top nav/sidebar. The AMD `cart_badge` wiring may not be firing the increment event on `course.php` confirmation. (Related to Chip B-fix — may not be wired on the catalog layout.) |

---

## E-01 Analysis (informs employee bug)

Public cart path: `addtocart` → `?added=1` (success) → `catalog/cart.php` shows item → "Enroll in All (Free)" present.  
Employee enrol path (from prior walk): catalog Enroll → `addtocart` → redirect to `/my/` (dashboard), nothing in cart.

**Conclusion:** E-01 is not a global cart breakage. The cart layer works for Public. For the Airpay employee persona, the `addtocart` action likely falls through to a different code path (possibly self-enrol via `enrol/index.php` rather than the cart), and that path fails silently with a redirect to dashboard. The fix should target the Airpay-tenant enrol method selection in `course.php`, not the cart itself.

---

## Screenshots

- `public-01-dashboard.png` — Dashboard with sidebar (My Cart present)
- `public-02-catalog.png` — Public catalog (183 courses, Public-tenant content)
- `public-03-cart.png` — Cart showing POSH Training (add-to-cart worked, stopped before enroll)

All screenshots saved to: `moodle-enhancement/docs/qa-walk-2026-05-29/`
