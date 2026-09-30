# Persona pass triage - 2026-09-30

Input: `results.json` (PASS 82, CHECK 22, FAIL 21, SKIP 11 = 54 non-PASS rows). Run logs, Apache error log, served code (`C:/xampp/htdocs/moodle5/public`) and repo (`moodle-enhancement/local/sentientia_*`, `theme/sentientia`) were read. Six read-only DB probes were run (no writes, no passwords printed). Nothing was changed and nothing was committed.

Paths below are repo-relative. Served code and repo agree for every file cited, except `local/sentientia_classroom` (see D3).

## 1. Counts

Non-PASS rows by class:

| Class | Rows | Notes |
|---|---|---|
| PRODUCT DEFECT | 12 | 8 unique causes |
| BY DESIGN | 8 | |
| HARNESS | 15 | 12 refusal/console/search, 3 debug-overflow |
| ENVIRONMENT | 15 | 6 causes; 9 are zeea-admin SKIPs that follow one failed login |
| SEED gap | 4 | |

Unique product defects (14 in total, 6 of them found in logs/screenshots rather than in a failing row):

| Severity | Count | IDs |
|---|---|---|
| P0 | 3 | D1, D2, D3 |
| P1 | 6 | D4, D5, D6, D7, D8, D9 |
| P2 | 5 | D10, D11, D12, D13, D14 |

## 2. Things that shape the whole reading

- Every "Failed to load resource ... 404" console error on a refusal page is the page itself, not a missing file. Moodle core sends `404 Not Found` for every uncaught exception (`lib/classes/output/core_renderer.php:2616`, `fatal_error()`). `badResources` is empty on all 54 rows, so no sub-resource 404s exist. This is one harness issue (H1), not many product issues.
- Local PHP kills any request at 120 s (`Maximum execution time of 120 seconds exceeded`). Local Apache also restarted 6 times during the pass (13:15, 13:21, 13:43, 17:23, 18:27, 18:37). These explain every ENVIRONMENT row.
- `$CFG->debug` is DEVELOPER locally, so refusal pages print a "Debug info" and "Stack trace" block. Production prints only the message. That block causes the overflow on refusal pages (H2).
- Another agent works in branch `claude/fixes-0930` (worktree `.claude/worktrees/wf_3b722337-ff6-1`). Its commit `77e7fd0a9` already fixes D3. The served classroom plugin was overwritten from it at 17:57, after the trainer step ran. Its DB back-fill (upgrade 2026093001) has not been applied locally: the trainer still lacks `local/sentientia_classroom:view`.
- Tenant isolation was not disproved by any row. I called the same list services the datatables call, as each tenant admin (`local_sentientia_users\external\list_users`, `local_sentientia_courses\external\list_courses`): `vp_admin1` searching "Learner" sees 3 users, none from /177. `vp_admin177` sees only `VP Learner /177` and 21 courses, none /1-only. The page-level and CSV checks for /177 admin still have to be re-run (H4, E1).

## 3. Findings table

Step numbers are the `#` column in `results.md`. Duplicates are grouped.

| id | Persona / step | Class | Sev | Root cause (file:line) | Fix | Visible? | Bump? |
|---|---|---|---|---|---|---|---|
| D1 | public77 #81 cart-db-add, #82 checkout, #83 cart-cleanup | PRODUCT | P0 | `local_sentientia_cart:purchase` is held only by roles `employee` and `administrator` in the DB (probe 1). A public learner has only the Authenticated user role. `db/access.php:26-34` and README say `user` gets it, but the grant never landed (archetype list changed, no back-fill). `add_item.php:29`, `checkout.php:29`, `remove_item.php:28` all `require_capability(purchase)`. Real data: 681 of 683 real /77 users and all 6 real /177 users hold no system role (probe 4). | `db/upgrade.php` step: `assign_capability('local/sentientia_cart:purchase', CAP_ALLOW, <role user>, system ctx, true)`. Add `'user' => [view, purchase]` to the `$rolemap` in `db/install.php:25` so fresh installs match. Tenant gating stays in `cart_manager::is_enabled_for_user()`. Decision needed (section 6). | yes (cart, checkout) | yes (upgrade step) |
| D2 | public77 #80 cart-page-ui | PRODUCT | P0 | Storefront session cart is a dead end. `local/sentientia_catalog/cart.php:161-171` shows a disabled "Payment Coming Soon" button for any paid course. Nothing copies session-cart lines into `local_sentientia_cart` (the order cart). The soft check "Proceed to\|Checkout" misses for this reason. | Behind a new flag `sentientia.catalog.storefront_checkout.enabled` (default OFF, register in `local/sentientia_catalog/db/feature_flags.php`): when the user has `purchase`, replace the disabled button with a POST that calls `cart_manager::add_item()` for each paid line, clears the session cart, and redirects to `/local/sentientia_cart/checkout.php`. Gateway must be in sandbox first. | yes | yes (flag + lang) |
| D3 | trainer #33 classroom | PRODUCT (+ ENV) | P0 | `vp_author1` (role `trainer`, archetype `teacher`) has no `local/sentientia_classroom:view` (probe 1). Repo `moodle-enhancement/local/sentientia_classroom/db/access.php:14` lists only manager + editingteacher. `index.php:12` refuses (Apache log 13:07:12). The FAIL text is "ERR_HTTP_RESPONSE_CODE_FAILURE" because both navigation attempts also hit the 120 s limit (13:04:10, 13:05:11), which hid the refusal. | Merge `77e7fd0a9` from `claude/fixes-0930` (adds `teacher` archetype to `:view` and `:attendance`, back-fill in `db/upgradelib.php`, version 2026093001). Run the upgrade locally, then re-run the trainer steps. Same class as memory "T-01 persona caps". | yes | yes (already in the commit) |
| D4 | manager #23 team-performance | PRODUCT | P1 | The page loads (HTTP 200, `performance.php:23` uses `team_manager::require_manage()`), but its AJAX service refuses: `classes/external/team_performance.php:42` uses `require_capability('local/sentientia_manager:view')`. `vp_manager1` is a supervisor without that cap (probe 1: `can_manage()` is true, cap is n). The 2026-05-22 fix covered `list_requests.php:50` and `list_allocations.php:45` but missed this one. Even with the gate fixed, `:53-71` reads `u.open_managerid`, a column that does not exist (probe 5), so the service would return "team detection unavailable". | Replace line 42 with `\local_sentientia_manager\team_manager::require_manage()`. Replace the `open_managerid` query with `\local_sentientia_core\org::direct_reports($target_mid)` and `get_in_or_equal()`. Keep the "own team only unless site admin" rule at `:45-49`. | yes | no |
| D5 | trainer #37 gradebook | PRODUCT | P1 | (a) JS error `Cannot read properties of null (reading 'offsetHeight')`: `grade/report/grader/amd/src/stickycolspan.js:94` does `document.querySelector('.stickyfooter').offsetHeight`. The theme is standalone (`config.php:178` `parents = []`) and has no `templates/core/sticky_footer.mustache`. Core `lib/templates/sticky_footer.mustache` renders `<div id="sticky-footer">` without the `stickyfooter` class; only Boost's override adds it. (b) 415 px at 390: core `theme/sentientia/scss/moodle/grade.scss:34-48` sets `#region-main {min-width:100%; width:auto; display:flex; overflow-x:visible}`; the wide table pushes the card past the viewport. `partials/_surface-grade-report.scss:139` never neutralises it (the old fix `custom_changes_MONOLITH_BACKUP.scss:1819` was lost in the split). `.gradeparent {overflow-x:auto}` at `:233-240` cannot help. | (a) Add `theme/sentientia/templates/core/sticky_footer.mustache` = core markup with `class="stickyfooter ..."`, same `core/sticky-footer` AMD call. (b) In `_surface-grade-report.scss` under `body.path-grade-report-grader`: `#region-main { min-width:0; width:100%; display:block; }`. | yes | yes (theme) |
| D6 | admin1 #63 certificates-admin | PRODUCT | P1 | The page gate intends `tool/certificate:manage` holders (`local/sentientia_pages/certificate_templates.php:14-22`), and `vp_admin1` has it (probe 1). Then `:49` calls `admin_externalpage_setup()`. The external page is registered only inside `if ($hassiteconfig)` (`settings.php:20`) with cap `moodle/site:config` (`settings.php:50`), so core throws `accessdenied` (`adminlib.php:8832`, Apache log 17:28:31). The harness recorded it as "access refused", which hid the defect. | In `certificate_templates.php` replace `admin_externalpage_setup(...)` with `$PAGE->set_context($context); $PAGE->set_pagelayout('admin');` after the existing gate. | yes | no |
| D7 | (finding) no EN/HI switcher in the main layouts | PRODUCT | P1 | `theme/sentientia/classes/output/core_renderer.php:619` `custom_language_menu()` has no caller. Layouts pass `'langmenu' => $primarymenu['lang']` (`layout/dashboard.php:1093`, `drawers.php:118`, `columns2.php:109`, `course.php:153`) but no template prints it. The shell sidebar (`core_renderer.php:276`) and topbar string (`:289+`) have no language control. Only `templates/navbar-secure.mustache:39` has one. Core `language_menu.php:65` also returns nothing when `$CFG->langmenu` is 0, and it is 0 locally. The product guide promises "Language switching is per-user and immediate" (`docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md:1131`); the 5.2 merge map lists "language switcher" as a re-apply item (`docs/5.2-merge/PHASE-A4B-CONFLICT-MAP.md:148`). `?lang=xx` works (harness lang steps prove it). | Add a data-only `get_language_switch_options()` beside `get_role_switch_options()` (`classes/output/traits/user_menu.php:473`), independent of `$CFG->langmenu`. Add `$context['langswitch']` at `core_renderer.php:276`. Render it in `templates/sidebar.mustache` above Dark Mode, and in the guest/login template. Persist to `$USER->lang` through a sesskey-checked endpoint. CLAUDE.md flag rule: register `sentientia.ux.language_switcher.enabled`, default OFF. | yes | yes (theme + lang) |
| D8 | learner (screenshot #4 mobile) | PRODUCT | P1 | `local/sentientia_catalog/templates/catalog.mustache:142` hard-codes `<details class="airpay-catalog__filter-details" open>`. At <=590 px the CSS (`styles.css:800-817`) turns an open `<details>` into a fixed bottom sheet. So the sheet is open on load and covers the bottom quarter of the screen. The comment at `:136-140` says it should start closed on mobile. | Remove `open` from the markup; add a 4-line inline AMD that adds `open` when `matchMedia('(min-width: 591px)')` matches. | yes | yes (cache) |
| D9 | (log) retired capability names | PRODUCT | P1 | `local/courses:manage` / `local/courses:enrol` / `local/classroom:takesessionattendance` are undefined since ADR-025. `has_capability` returns false and logs a notice. Sites: `sentientia_courses/classes/course_manager.php:278,292` (called by `theme/.../core_renderer.php:1034,1044` on every course view), `sentientia_manager/index.php:22` (a tenant admin holding only the new cap is not treated as admin, cannot pick a manager's team), `sentientia_skills/index.php:27`, `sentientia_notifications/nudge.php:35`, `sentientia_pages/qr_attendance.php:41` (covered by `fixes-0930`). | Delete the old-name branch, or guard it like `sentientia_compliance_report/classes/viewer_scope.php:119` (`get_capability_info()` first). | yes (My Team for tenant admin) | no |
| D10 | learner #4, #16, #17 catalog overflow 400 px at 390 | PRODUCT | P2 | Only the learner catalog overflows (public/guest storefront and the /77 catalog do not); 590 px is clean, so the cause is a 10 px element that is not a scroll container. `results.json` lists no offender because `measureOverflow()` only reports scroll containers. Carousel rules (`styles.css:97-116, 754-760`) are inside `overflow-x:auto` and are not the cause. I could not pin the element from static reading. | Re-run only `catalog` at 390 with H3 in place (it names the element), then fix in `local/sentientia_catalog/styles.css`. | yes | yes (cache) |
| D11 | siteadmin #120 switchboard | PRODUCT | P2 | Flags without a dot in their key fall in category `other`; `live.enabled` gives `live` (`classes/feature_flags.php:243`). `lang/en` and `lang/hi` have no `flag_category_live` / `flag_category_other`. The fallback at `admin/switchboard.php:134` never runs: `get_string(..., null, true)` returns a lazy `lang_string` object, which is truthy, so `?: ucfirst($cat)` is dead. Page shows `[[FLAG_CATEGORY_LIVE]]`. | Add both strings to `lang/en/local_sentientia_platform.php` and `lang/hi/...` (Hindi parity gate). Use `get_string_manager()->string_exists()` at `:134`. | yes (2 headings) | no (purge caches) |
| D12 | (screenshot #4) NEW badge under bookmark heart | PRODUCT | P2 | `local/sentientia_catalog/styles.css:204-208` (`.airpay-catalog__badge`) and `:414-418` (`.airpay-catalog__bookmark`) both use `position:absolute; top:8px; right:8px`. The heart sits on the "NEW" pill on Trending cards. | Move the badge to `left`, or offset it by the heart width. | yes | yes (cache) |
| D13 | (log) `core_renderer.php:1030-1035` "Undefined array key 0" on every course view | PRODUCT | P2 | `theme/sentientia/templates/course.mustache` opens `<body>` (line 61) but never emits `{{{ output.standard_end_of_body_html }}}`, `</body>` or `</html>` (compare `dashboard.mustache:948` and its `</body></html>`). Moodle's `footer()` regex finds no `</body>`, logs 4 warnings per view and leaves `$CFG->closingtags` empty. | Close the document at the end of `course.mustache` as `dashboard.mustache` does. Check the page's AMD still boots. | no (regression shot only) | yes (theme) |
| D14 | (log) developer notices | PRODUCT | P2 | `sentientia_users/classes/user_manager.php:83-90` selects `id, firstname, lastname, open_employeeid` then calls `fullname()` (missing phonetic/middle/alt name fields; every profile view with a supervisor). `sentientia_manager/member.php:27` calls `get_member_detail()` (which runs `format_string`) before `set_context()` at `:30`. | Add the four name fields to the select (or use `\core_user\fields::for_name()`). Move `$PAGE->set_context()` above line 27. | no | no |
| B1 | trainer #35 evaluations | BY DESIGN | - | `sentientia_evaluation/db/access.php:6` gives `:manage` to the manager archetype only; `index.php:12` requires it. No trainer-facing cap exists (`:respond` is for students, `:view` gates `response_*.php`). | Journey: set `access: refused`. Decision needed on a trainer results view (section 6). | - | - |
| B2 | admin1 #62 switchboard | BY DESIGN | - | `sentientia_platform/admin/switchboard.php:18,27` says "site admin only (moodle/site:config)". The plan lists the switchboard for the tenant admin; the plan is wrong. | Journey: `access: refused` for admin1; correct the UAT plan row. Site admin step #120 covers it. | - | - |
| B3 | admin1 #67 deny-environment | BY DESIGN | - | Expected refusal (`admin/environment.php:43`). CHECK only because of H1. | None. | - | - |
| B4 | admin1 #55, siteadmin #118, #119, #123 (`.table-responsive`) | BY DESIGN | - | Bootstrap tables scrolling inside their own wrapper (821, 903, 1428, 816 px). The page itself does not scroll. | H3: stop reporting `.table-responsive`. | - | - |
| B5 | compliance #69 | BY DESIGN | - | Compliance matrix has one column per mandatory course; `sentientia_compliance_report/styles.css:21` gives the wrapper `overflow-x:auto`. Optional P2: card layout at <=590. | H3. | - | - |
| B6 | siteadmin #117 environment (scroll part) | counted under E2 | - | Core admin tables scroll in a wrapper (by design). The row is CHECK mainly for E2. | H3, H7. | - | - |
| H-a | learner/manager/trainer/author/compliance/zeea-learner deny steps #18, #19, #29, #41, #52, #53, #73, #103 | HARNESS | - | Correct refusals. CHECK only because the main document status is 404 and Chrome logs it as a console error. Sub-resources are clean. | H1. | - | - |
| H-b | author #47 authoring-review | HARNESS | - | `local/sentientia_authoring/review.php:40` needs `?draftid=`. The queue lives on `index.php:58` (links carry the id). Journey opens the bare URL, so Moodle answers `missingparam` (log 13:36:03). | H6. | - | - |
| H-c | admin1 #56 users-isolation | HARNESS | - | `theme/sentientia/templates/dashboard.mustache:908-914` hides the datatable's own search box (`display:none`) and mirrors the topbar search into it. Playwright `fill()` waits for a visible element and burns 240 s. Also the term `'VP '` returns 0 users (probe 6), so even a working fill would pass vacuously. | H4. | - | - |
| H-d | public77 #76, guest #88 storefront | HARNESS | - | `commerce.php:209-250` lists /77 courses 12 per page, "popular" = enrolment count. `VP Journey Public Priced` has 0 enrolments; 110 of 184 /77 courses have some, so it sits from about page 10. Seed, price and open_path are all correct (probe 1: course 450, price 500, /77). | H5. | - | - |
| H-e | public77 #85, guest #93, zeea-learner #102 deny-internal-course | HARNESS (+ ENV) | - | The refusal is correct (`catalog_manager.php:176`). The 585/605/569 px width is the developer stack-trace `<li>` (`.../catalog_manager::assert_course_visible_to_viewe...` unbroken). It does not exist with debug off. | H2. | - | - |
| E1 | zeea-admin login + #107-#115 (9 SKIPs) | ENVIRONMENT (unresolved) | - | Moodle accepted the password twice: `user_loggedin` events for `vp_admin177` at 18:47:01 and 18:49:09 (probe 2), and `sessions` rows exist. The next request found no session data, which Moodle reports as "Your session has timed out" (`lib/classes/session/manager.php:555`, `login/index.php:302`). No password change in that window (only 12:53 and 17:08). No Apache restart at 18:46-18:50. Not a bad credential or account. Cause of the lost session not found. The /177 admin isolation checks never ran. | H8, then re-run `--persona zeea-admin` on an idle box. | - | - |
| E2 | public77 #75 dashboard, zeea-learner #99 enrol, siteadmin #117 environment | ENVIRONMENT | - | First navigation hit the 120 s limit (log 17:46:29, 18:29:35, 18:54:42). The retry succeeded, but the first attempt's console error stayed on the record and turned PASS into FAIL/CHECK. | H7 (harness), E4 (box). | - | - |
| E3 | trainer #38 authoring | ENVIRONMENT | - | `ERR_CONNECTION_RESET` and `Script error for "core/first"` while Apache restarted at 13:15:14. Access was granted. | Re-run. | - | - |
| E4 | siteadmin #125 lang-hi | ENVIRONMENT | - | Both attempts at `/my/dashboard.php?lang=hi` hit 120 s (log 19:10:24, 19:12:52). First request in a language after a cache purge rebuilds the string cache. `lang-en` then took 243 s. | Warm each language after a purge. Raising `max_execution_time` needs an Apache restart: Nitin's call. | - | - |
| S1 | learner #8 page-activity | SEED | - | The Page row has `content` NULL (probe 2). `seed_journey_content.php:107` sets `$mi->page = [...]`, but `mod/page/lib.php:112` reads `->page` only when a form object is passed. | H9. | - | - |
| S2 | siteadmin #121 privacy-requests | SEED (data artifact) | P2 | `tool_dataprivacy_request` rows point at users 3, 5-13, which are not in `mdl_user` (probe 1). Core `data_request_exporter.php:137` throws `invaliduser`. Left over from the prod import. | Delete or repoint those rows locally (a delete: ask first). Add "every data request has a user" to the Stage B rehearsal checks. | - | - |
| S3 | author #48, #49 | SEED (flag off) | - | `sentientia.aiquiz.enabled` is off for /1. Flag flips are Nitin's call. | Nitin flips locally, or accepts SKIP. | - | - |

Also a seed defect with no row yet: `seed_journey_content.php:83` takes the fee instance role from `shortname='student'`, which does not exist here (roles: `employee`, `trainer`, ...). The fee instance is stored with `roleid` 0. Use `employee`.

## 4. Public77 cart flow: can a real public learner buy a course?

No. Two independent blockers, both product:

1. Capability (D1). A real /77 user holds only the Authenticated user role. That role has `cart:view` but not `cart:purchase`. Sidebar "My Cart", the cart page and order history open (view). Add, remove and checkout are refused (`nopermissions`, Apache log 17:56:56 for checkout). `cart_manager::is_enabled_for_user()` returns true for /77, so the page looks enabled and then fails. The same is true for the 6 real /177 users. `employee`-role users (e.g. `vp_learner177`) can buy, which is why the ZEEA cart step passed.
2. UI (D2). The storefront "Add to Cart" fills a session cart (`catalog/cart.php`). For paid courses it ends in a disabled "Payment Coming Soon" button. The order cart is never filled from it. Add to Cart steps #78-#79 pass because the session cart works.

The storefront listing miss (#76, #88) is harness only (H-d).

## 5. Prioritised fix bundles (each can be built alone)

| Order | Bundle | Scope | Covers | Needs Nitin? |
|---|---|---|---|---|
| 1 | Trainer classroom | Merge `77e7fd0a9` from `claude/fixes-0930`; run the upgrade locally | D3, part of D9 (`qr_attendance.php:41`) | no |
| 2 | Public commerce | `local_sentientia_cart` upgrade step + install rolemap; `local_sentientia_catalog/cart.php` bridge behind a new flag | D1, D2 | yes (section 6, items 1-2) |
| 3 | Manager performance | `local_sentientia_manager` `team_performance.php`, `member.php` | D4, part of D14 | no |
| 4 | Theme shell | `theme_sentientia`: language switcher, grader report SCSS + sticky footer template, `course.mustache` closing tags | D7, D5, D13 | yes (D7 flag default) |
| 5 | Admin gates | `local_sentientia_pages/certificate_templates.php`; retired-capability sweep in courses, manager/index.php, skills, notifications | D6, D9 | no |
| 6 | Catalog mobile | `local_sentientia_catalog`: `catalog.mustache` details, `styles.css` badge/heart, and the 390 px overflow once located | D8, D12, D10 | no |
| 7 | Switchboard strings | `local_sentientia_platform` lang en + hi, `switchboard.php:134` | D11 | no |
| 8 | Notice cleanup | `local_sentientia_users/classes/user_manager.php:83` | D14 | no |

Bundles 3, 5, 7, 8 are PHP or lang only; 1, 2 need a version bump with an upgrade step; 4 and 6 need a theme/plugin version bump and screenshots in `docs/visual-evidence/<date>/`. Every user-visible item needs desktop + 590 px screenshots and a README (CLAUDE.md rule). Bundles 2 and 6 both touch `local_sentientia_catalog`: merge them in order to avoid a `version.php` conflict.

## 6. Decisions for Nitin

1. Public learners: grant `purchase` to the Authenticated user role (matches the README), or create a dedicated public-learner role assigned at signup?
2. Wire the storefront cart to the order cart now? It needs the payment gateway in sandbox mode. Until then "Payment Coming Soon" is the honest state.
3. Trainer evaluations: should a trainer see results for their own sessions? That is a new capability, not a defect fix.
4. UAT plan wording: the tenant admin does not get the feature switchboard (site admin only). Correct the plan or change the page.
5. Language switcher: CLAUDE.md requires a default-OFF flag for every new visible feature. This one restores a documented feature. Flag it and flip ON for UAT, or ship ON?
6. What is `$CFG->langmenu` on UAT and production? Locally it is 0.
7. Local flags (aiquiz) and any locally deleted rows (S2) are yours to flip or delete.

## 7. Harness fixes

Files: `tools/visual-pass/persona_journeys.mjs` (`.mjs`), `journeys.json`, `seed_journey_content.php`.

- H1 (`.mjs:758-762`): on steps with `access` refused or either, drop the console error `Failed to load resource ... status of 404` when the main document status is 404 (`rec.http`). Removes 12 CHECK rows.
- H2 (`.mjs:679-684`): skip the horizontal-scroll check on a page that was judged a refusal (`refused` is computed in `checkPage`). Or add `overflow-wrap:anywhere` to `li, pre` before measuring.
- H3 (`.mjs:251-264`): for a page overflow, list the elements whose `getBoundingClientRect().right > innerWidth` and that have no `overflow-x` scroller ancestor (top 3, with selector). Stop reporting `.table-responsive`, `.gradeparent`, `[class*="table-wrap"]` and `.airpay-datatable` scroll containers as CHECK; write them as a note.
- H4 (`.mjs:447-459`, `journeys.json:145-147, 268-270`): type into the topbar search (`.ap-topbar__search-input`), because the datatable box is hidden by design. Fallback: set the hidden input's value and dispatch `input`. Change the term from `'VP '` to `'Learner'`. Make the own-tenant row a hard `text`, not `softText`, so an empty table cannot pass.
- H5 (`journeys.json:190, 218`): open `public.php?q=VP+Journey+Public` (or `?sort=newest`).
- H6 (`journeys.json:124-125`): open `index.php`, click the first "Review" link; SKIP with "no draft seeded" if none. Seed one mock draft.
- H7 (`.mjs:330-359`): when the retry succeeds, clear `rec.consoleErrors` and `rec.badResources` gathered during the failed first attempt.
- H8 (`.mjs:769-816`): on "session has timed out", retry in a fresh browser context (no cookies); after login, load `/my/` and check it does not bounce to `/login/`. Refuse to start if `.personas.local.json` is newer than the last login.
- H9 (`seed_journey_content.php:107`): also set `$mi->content` and `$mi->contentformat = FORMAT_HTML`; for an existing page with NULL content, `update_record`. Change `'student'` to `'employee'` at `:83`.
- H10 (`journeys.json:99, 158`): trainer `evaluations` and admin1 `switchboard` to `access: refused` (B1, B2). After D6 is fixed, set admin1 `certificates-admin` to `ok`.

Re-run order after fixes, one persona at a time on a quiet box: `zeea-admin`, `admin1 --steps users-isolation`, `trainer`, `public77`, `learner --steps catalog,page-activity`, `manager --steps team-performance`, `siteadmin --steps switchboard`.

## 8. Other log leads (not persona rows)

- One `Array to string conversion` in `lib/scssphp/src/Compiler.php:927` at 15:13:45 during a theme CSS compile. Not repeated. Run `admin/cli/build_theme_css.php --themes=sentientia` and read the warnings.
- `getimagesize(...filedir...)`: missing files, the known empty-`filedir` clone artifact.
- `qr_scan.php:28`, `qr_attendance.php:76` "Undefined property passwordsaltmain": QR work, fixed in `claude/fixes-0930` (`c7b6cecb4`).
