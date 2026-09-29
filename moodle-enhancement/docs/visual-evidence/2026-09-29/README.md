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
