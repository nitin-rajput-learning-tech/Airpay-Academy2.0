# E-01 deploy & rollout checklist — one-click free self-enrol

**Change:** `local_airpay_catalog` `2026052901` → `2026052902` — one-click free
self-enrolment for internal-tenant employees, behind feature flag
`sentientia.catalog.free_oneclick_enrol.enabled` (default **OFF**).
**PR branch:** `fix/qa-walk-e01-free-self-enrol`.
**Risk:** Low. Additive (new class + new flag + additive edits). No DB schema
change — the version bump triggers a no-op plugin upgrade. **OFF reproduces
today's behaviour exactly**, so the flag is also the kill-switch.

---

## 0. Pre-flight
- [ ] PR `fix/qa-walk-e01-free-self-enrol` reviewed + merged to `production`.
- [ ] Confirm no `paygw_airpay` / paid-course code is touched (it isn't — free path only).
- [ ] Take the usual production file-level backup before copying.

## 1. Deploy files
Copy the merged `local/airpay_catalog/` tree to the server (the usual file-copy
deploy). Files in this change:
```
local/airpay_catalog/version.php                 (2026052902 / 1.0.2-beta)
local/airpay_catalog/classes/enrolment.php       (NEW)
local/airpay_catalog/db/feature_flags.php        (+ free_oneclick_enrol flag)
local/airpay_catalog/course.php                  (action=enrolnow + detail CTA)
local/airpay_catalog/public.php                  (grid button routing)
local/airpay_catalog/cart.php                    (enrollfree silent-failure fix)
local/airpay_catalog/lang/{en,hi,kn,mr,sw}/local_airpay_catalog.php
local/airpay_catalog/tests/enrolment_test.php    (NEW, CI)
tools/enrol-diag.php, tools/enrol-verify.php      (optional — diagnostics)
```
> `public.php` also carries the render-side of the in-progress Netflix
> card-thumbnail feature — LXP-flag-OFF, harmless.

## 2. Upgrade + purge (on the server, cwd = Moodle `public/`)
```
php ../admin/cli/upgrade.php --non-interactive
php ../admin/cli/purge_caches.php
```
- [ ] Upgrade reports `++ 2026052902: Success ++` for `local_airpay_catalog`.
- [ ] No errors in the Apache/PHP log after first page load.

## 3. Enable the flag — per internal tenant (the step that activates it)
Site admin only. Open the **Switchboard** once per tenant and toggle
`sentientia.catalog.free_oneclick_enrol.enabled` **ON**, with a reason
(every change is audit-logged):

- [ ] Airpay (/1): `…/local/airpay_core/admin/switchboard.php?tenant=1` → toggle ON → Save
- [ ] ZEEA (/177): `…/local/airpay_core/admin/switchboard.php?tenant=177` → toggle ON → Save
- [ ] **Do NOT enable for tenant 0 (all) or 77 (Public)** — Public keeps the cart by design.

**CLI fallback** (if the UI isn't convenient), as an admin script on the server:
```php
// php ../admin/cli/<oneoff>.php   — define('CLI_SCRIPT',true); require config.php;
\local_airpay_core\feature_flags::set(
    'sentientia.catalog.free_oneclick_enrol.enabled', 1,  true, null, 'E-01 rollout'); // Airpay /1
\local_airpay_core\feature_flags::set(
    'sentientia.catalog.free_oneclick_enrol.enabled', 177, true, null, 'E-01 rollout'); // ZEEA /177
```

## 4. Smoke test (as a real internal-tenant learner)
- [ ] Log in as an Airpay (/1) employee (non-admin).
- [ ] `/local/airpay_catalog/public.php` → a **Free** course card shows **"Enrol now — free"** (not "Enroll"/"Add to cart"); the link is `…/course.php?id=N&action=enrolnow&sesskey=…`.
- [ ] Click it → lands in `/course/view.php?id=N`, enrolled (no cart step).
- [ ] `/local/airpay_catalog/mycourses.php` → the course is listed.
- [ ] A **paid** course still shows "Add to Cart" (cart path unchanged).
- [ ] Log in as a Public (/77) learner → free courses still show the cart CTA (unchanged).

> Server-side diagnostic (read-only) if anything looks off:
> `php tools/enrol-diag.php <courseid> <userid>` (set `MOODLE_PUBLIC` if not the default path).

## 5. Rollback (instant, no redeploy)
The flag is the kill-switch. In the Switchboard, toggle
`sentientia.catalog.free_oneclick_enrol.enabled` **OFF** for the affected
tenant(s) — every free-course button reverts to the cart immediately. No file
revert or cache purge needed (override is read live).
> Note: learners enrolled while the flag was ON stay enrolled (a `manual`
> enrolment). That's correct — disabling only stops *new* one-click enrols.

## Behaviour notes for support
- One-click enrol creates a **manual** enrolment as `student` — it shows in the
  gradebook/reports like any enrolment, and **bypasses any self-enrol enrolment
  key** (intended: catalog tenant-visibility is the gate for internal staff).
- It's **idempotent** — re-clicking never double-enrols.
- Guests and the Public /77 storefront are unaffected (cart preserved).
