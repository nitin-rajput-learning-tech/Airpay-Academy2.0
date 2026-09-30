# Visual evidence - 2026-09-29 (ADR-031 screen-check pass, Playwright, local)

**What:** the persona screen-check list from `../2026-09-25/README.md`, captured automatically with
Playwright on local XAMPP (`http://localhost:8080`), which runs the same merged ADR-031 plugin code that
was deployed to UAT on 2026-09-26 (the delegated-decision fixes still in progress are NOT in it), on
the local import of production data (2,871 users, 411 courses, 3 tenants).

**Why local, not UAT:** logging a script into UAT would mean entering real credentials into a remote
site (and reading the credentials folder), which Claude does not do. Locally, eight dedicated test
personas were created (`vp_*`, new accounts - no imported user was changed) with generated test
passwords stored only in the gitignored `moodle-enhancement/tools/visual-pass/.personas.local.json`.

**How to re-run:** `php moodle-enhancement/tools/visual-pass/provision_local_personas.php` (cwd =
moodle5/public; refuses a non-localhost wwwroot), then `node visual_pass.mjs` in that folder
(installed Chrome, headless, one page at a time). Output: `NN-<persona>-<page>-desktop.png` (1440 px)
and `-mobile.png` (590 px, the theme's primary breakpoint), `results.md` / `results.json`.

## Result

**58 pages x 2 viewports. 51 PASS. The other 7 are refusals, all verified by their exact message:**

| Page (persona) | Message (it should refuse) | Verdict |
|---|---|---|
| courses `share.php?id=4` (tenant admin /1) | "...permissions to do that (Share a course to other tenants)" | correct |
| courses `manage_requests.php` (tenant admin /1) | "...(Approve / reject share-requests from other tenants)" | correct |
| skills `admin.php` (tenant admin /1) | "...(Manage skill categories and definitions)" | correct |
| notifications `index.php` (tenant admin /1) | "...(Manage notification rules)" | correct |
| AI ledger `ai/index.php` (tenant admin /1) | "...(View the AI spend ledger)" | correct |
| users `index.php` (learner /1) | "...(View user profiles)" | correct |
| evaluation `index.php` (trainer /1) | "...(Manage evaluation forms)" | environment: the local trainer role lacks `evaluation:manage`, which UAT's provisioning grants - not a regression |

They are reported as "FAIL (error)" in `results.md` only because local debugging shows a Debug info /
stack trace block on Moodle's refusal page; the shot of `share.php` in the main run lacked the `id`
parameter and shows "missing parameter" - the probe above re-checked it with a real /1 course.

**Spot-checked against the checklist (screenshots viewed):**
- Tenant admin /1 - Manage Courses: 209 courses = the 207 /1 courses + the 2 legacy no-path ones; no
  Share icon. Roles: view + audit actions only, no Edit / Assign.
- Tenant admin /177 - Organisation Structure: 1 tenant (ZEEA, /177), 8 users - nothing of Airpay.
- Line manager /1 - My Team: exactly the 2 direct reports.
- Learner /1 - catalogue: 204 courses (own tenant), mobile layout renders.
- No JS console errors on any page that loaded normally.

## Found by this pass

1. **FIXED (this commit): emails dashboard showed site-wide legacy-queue totals to every tenant admin.**
   The "Legacy queue" card (BizLMS `local_emaillogs`: 14,202 logged / 14,197 sent) was identical for
   the Airpay and the ZEEA admin - that table has no tenant column. It is now shown to cross-tenant
   callers only (`manage_controller` + `tab_dashboard.mustache`, both trees); re-checked in
   `recheck-emails/` (ZEEA admin: card gone; 15/15 pages PASS).
2. **Note for Nitin:** the PWA service worker (`sentientia.pwa.enabled`, registry default TRUE since
   the PWA scaffold 47df08ff1 - CLAUDE.md still says "flag OFF") took over navigations in headless
   Chrome, and the post-login `/my/` request it made lost the session (login loop). The pass blocks
   service workers. Real Chrome logins on UAT have worked for weeks, so this may be automation-only -
   worth one manual check (log in, reload twice, open a course) with DevTools > Application > Service
   workers showing sw.php active.
3. **White-label question:** ZEEA's tenant admin sidebar shows "Browse Airpay Library" (the
   cross-tenant course library). Deliberate for customer-zero, but a per-customer label for Customer N.
4. Known, recorded by the reviews: course categories are not tenant-scoped, so a /1 learner's catalogue
   lists category names such as "Airpay Tanzania" (names only; the courses shown are the learner's own).

`trial-runs/` holds the two smoke runs made while fixing the login step; ignore them.

---

## Cart (ADR-031 decision 3) - screen checks still to capture

### What changed (user-visible)

Branch `claude/adr031-cart-gate`, plugin `local_sentientia_cart` (both trees). See
`moodle-enhancement/state-cards/sentientia_cart-state.md`, 2026-09-29.

1. `/local/sentientia_cart/admin_orders.php`: a new "Staff notes" column (`history.notes`), showing
   gateway failure reasons and the ADR-031 "Refund due" line for a paid order whose enrolment was
   withheld. Visible to `:viewallorders` holders only.
2. Buyer notification `payment_received`: lists only the courses the buyer was enrolled in; a
   withheld course is not listed, and a line says it cannot be accessed and will be refunded.
3. Site-admin notification `admin_new_order`: subject ends " - Refund due", body names the order and
   the withheld course id(s), when anything was withheld.
4. `/local/sentientia_cart/checkout.php`: a gateway error after the order went to 'pending' is shown
   on the checkout page again (it was being replaced by a redirect to an empty cart).

### Screen-check list (persona -> page -> expected) - captured 2026-09-29, `cart/`

Data: `tools/visual-pass/seed_cart_evidence.php` (local-only; refuses without localhost + `noemailever`)
ran the real code path. vp_learner1 (/1) had a pending order for "VP Cart In-Tenant" (/1) and "VP Cart
Other Tenant" (/177, course 447) - an order from before the gate - and `cart_manager::mark_paid()`
settled it as order #923430. A second order, #933430, went through `mark_failed()` with a gateway
payload containing markup. Capture: `tools/visual-pass/cart_checks.mjs`.

1. [x] **PASS** [Site admin] `01-siteadmin-admin-orders-*`: columns #, Placed, User, Total, Gateway,
   Status, Staff notes. They are all filled now (they were empty before 5b43cb4e0). #923430 paid reads
   "ADR-031: order #923430: payment recorded, enrolment withheld for course id(s) 447 - not purchasable
   by this buyer at payment time. Refund due."
2. [x] **PASS** [Tenant admin /1] `02-admin1-admin-orders-*`: "1-2 of 2" - only the two /1 orders (the
   site admin sees 13). The failed order's note shows `<br>` as text: escaped, and the list no longer
   breaks on a raw gateway payload (the PARAM_RAW fix; the same call through the web service class
   returns both notes unchanged).
3. [x] **PASS** [Learner /1] `03-learner1-notifications-*` lists "Your order has been placed
   successfully." The body, read from `mdl_notifications`, lists only "VP Cart In-Tenant" and says "This
   order also included 1 course(s) that are no longer available to you. You cannot access them and you
   have not been enrolled in them. They will be refunded to you." Enrolled in the /1 course: yes; in
   course 447: no.
4. [x] **PASS** [Site admin] `04-siteadmin-notifications-*`: "New order #923430 - Refund due". The body
   says "Refund due: order #923430 was paid, but the buyer was NOT enrolled in course id(s) 447 ...".
   All 4 local site admins got it.
5. [ ] **Not captured** [Learner] checkout.php with the gateway misconfigured. The narrowed catch
   block is covered by the cart PHPUnit suite (37/37 pass); a screenshot needs a broken gateway
   config on this box.

### Found while capturing (fixed, separate commit)

The first `mark_paid()` run threw `coding_exception` ("Could not load preference ...") and rolled back.
On this copy, 28 of the 30 Sentientia message providers had no default preferences. The ADR-025
relabel renamed `message_providers.component` but not the `config_plugins` keys that carry the
component in their name. See `local/sentientia_platform/classes/message_pref_repair.php` and the
PROJECT-STATE 2026-09-29 entry. A fresh install (UAT) and the production install path do not have it.

### Profile pencil (ADR-031 role 9, checks 49-50) - `profile-pencil/`

`tools/visual-pass/profile_checks.mjs`, local, before the role-9 cap script (UAT-only) has run:

- 49 **PASS**: as a tenant admin /1, on a colleague the pencil is `data-action="edit-user"`, never
  `editadvanced.php`. The Sentientia modal loads **19 form fields**, with no console or AJAX error. The
  check now waits for fields; the first capture passed on an empty modal before the AJAX form arrived.
- 49b **PASS**: a tenant admin /1 viewing a site admin sees no pencil and no camera.
- 50 **PASS**: the site admin's pencil still links to `/user/editadvanced.php`.

The "Log in as" link still shows for the tenant admin locally, because it follows
`moodle/user:loginas`, which the role-9 script removes on UAT. It is also offered on a site admin's
profile, where core refuses to log in as an admin anyway (a tidy-up for later).

Filenames `NN-<persona>-<page>-{desktop,mobile}.png`, light mode.
