# 2026-09-08 — UAT visual check: course author gets the authoring nav (T-01)

**Environment:** https://academy2.airpay.ninja (UAT) · **Build under test:** commit `1dc599466` (theme_sentientia 2026090701, local_sentientia_authoring + local_sentientia_skillsai 2026090700), deployed 2026-09-08 via `tools/uat/deploy_to_uat.sh`.
**Persona:** `uat_author_airpay` — Sneha Kulkarni (id 9; `sentientiaauthor` + `employee` @ system, `coursecreator` @ category AirPay), reached via **Log in as** from the L&D admin account (`uat_ldadmin_airpay`, which holds `moodle/user:loginas`).
**Viewer:** Claude in Chrome on Nitin's laptop, window resized to 1500×900 (viewport 1280×665). No mobile pass this session — the change is a sidebar group that collapses into the existing hamburger drawer at < 992 px; no new CSS shipped.

## What changed (why this evidence exists)

Before the fix the author landed on a plain learner shell with no authoring UI (see `docs/cutover/UAT-DEMO-READINESS-2026-09-04.md`, "Author persona finding"). The T-01 fix seeds the author role on both install and upgrade paths and adds a capability-gated **Authoring Studio / AI Quiz / Skills AI** group to the sidebar. This check confirms the group renders for the author on UAT and that each target page loads for that role.

## What was seen on screen

| # | Surface | Result |
|---|---------|--------|
| 1 | `/course/loginas.php` notice ("You are logged in as Sneha Kulkarni") | Sidebar already shows the new group under a divider: **Authoring Studio · AI Quiz · Skills AI**, between *My Skills* and *Certificates*. Role switcher shows `Employee` / `- coursecreator`. |
| 2 | `/my/` — author dashboard | "Welcome back, Sneha!" learner dashboard (5 enrolled / 0 in progress / 0 completed / 0 certificates, 0 % overall, Beginner · 20 pts) with the same sidebar. Tiering unchanged: the author is still a learner-tier user, the authoring group is additive. |
| 3 | `/local/sentientia_authoring/studio.php` (sidebar → Authoring Studio) | Loads for the author: "Generate a microlearning module", **MOCK MODE** banner ("No Anthropic call is made and nothing is charged"), Design templates button, "Tokens used today: 0 of 500000", module-title + template form. |
| 4 | `/local/sentientia_aiquiz/generate.php` (sidebar → AI Quiz) | Loads: "Generate a quiz draft from course content", **MOCK MODE** banner ("set sentientia.aiquiz.live_api = ON for real generation"), draft title + course selector ("Site-wide / not yet assigned"). |
| 5 | `/local/sentientia_skillsai/index.php` (sidebar → Skills AI) | Loads: "Skills extraction queue" with *Extract skills* / *Skills taxonomy* tabs and the empty-state notice "No extraction jobs yet". Confirms the `skillsai:review` gate on the link matches the page. |

Sidebar link targets read from the accessibility tree on the dashboard (author session):

```
/my/
/local/sentientia_catalog/mycourses.php
/local/sentientia_catalog/public.php
/local/sentientia_skills/index.php
/local/sentientia_authoring/studio.php      ← new
/local/sentientia_aiquiz/generate.php       ← new
/local/sentientia_skillsai/index.php        ← new
/local/sentientia_pages/certificates.php
/local/sentientia_users/profile.php
```

No `[[missing-string]]` placeholders, no permission errors, no PHP notices on any of the five pages.

## Notes / observations (not defects of this change)

- **Flags:** the three master flags (`sentientia.authoring.enabled`, `sentientia.aiquiz.enabled`, `sentientia.skillsai.enabled`) were already ON globally on UAT from the 3 Sept provisioning. Nitin decided 2026-09-08 to leave them on for the demo. Live generation stays off (mock banners above).
- **Log in as worked from the L&D admin account**, not only from `admin` — useful for future persona walks (the browser's password manager currently offers `uat_ldadmin_airpay` first on the login page).
- A **PWA "Install / Not now" banner** appeared briefly at the top of the author dashboard (screen 2). Checked on the box the same day: `sentientia.pwa.install.enabled` is ON globally, set in the same 3 Sept 12:44 provisioning batch as the other 31 global mock-mode flags (`tools/uat/provision_test_users.php`, `$FLAGS_GLOBAL`). Expected on UAT; the CLAUDE.md "flag OFF" statement describes the shipped default, not UAT. Not touched.
- **Screenshots were viewed live, not saved:** the Chrome extension's `save_to_disk` option returned no file path on this extension build, so this README is the record (screen IDs in the session transcript: ss_3089x62aa, ss_3375zs5se, ss_95755ghak, ss_4350eeua5, ss_58852rrhz). Playwright/PDF capture was not available on the box this session.

## Verdict

**PASS.** The author persona can now demo Authoring Studio, AI Quiz and Skills AI from the sidebar in mock mode, closing the "Author persona finding" from 2026-09-07. Tenant-admin *Manage Courses* is still (correctly) not offered to the author.
