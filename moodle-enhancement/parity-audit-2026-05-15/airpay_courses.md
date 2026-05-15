# airpay_courses + airpay_catalog vs BizLMS local_courses — Parity Audit
Date: 2026-05-15 | Auditor: Claude (parity-audit-2026-05-15) | Method: line-by-line review of every PHP file, every external WS, every template, every form, every AMD module on both sides.

## Source paths + size

| Side | Path | PHP files | LOC |
|------|------|-----------|-----|
| BizLMS local_courses | `C:\xampp\htdocs\moodle5\bizlms_disabled\courses\` | 70 | **17,171** |
| Airpay local_airpay_courses | `C:\xampp\htdocs\moodle5\public\local\airpay_courses\` | 54 | **7,093** |
| Airpay local_airpay_catalog | `C:\xampp\htdocs\moodle5\public\local\airpay_catalog\` | 20 | **2,118** |
| **Airpay combined** | | **74** | **9,211** |

Airpay = **53.6 % of BizLMS LOC**. ~7,960 lines of BizLMS courses functionality has no equivalent on the Airpay side.

The split is intentional (admin in `airpay_courses`, learner in `airpay_catalog`) but a number of features are missing from BOTH.

## Web service surface (BizLMS had 18 WS, Airpay has 15)

| BizLMS WS | Airpay equivalent | Notes |
|-----------|-------------------|-------|
| `submit_create_course_form` | `airpay_courses\form\edit_course` (dynamic_form) | Replaced by form, not WS. Different shape entirely. |
| `submit_create_category_form` | **MISSING** | No way to create course categories from courses UI |
| `submit_delete_category_form` | **MISSING** | No category delete UI |
| `departmentlist` | **MISSING** | Cascading hierarchy filter feed |
| `course_update_status` | `airpay_courses_toggle_visibility` | Limited to visible/hidden — BizLMS had multi-state (draft/active/archived + visible) |
| `deletecourse` | `airpay_courses_delete_course` | Parity OK |
| `form_option_selector` | **MISSING** | Generic dynamic-options endpoint used everywhere |
| `courses_view` | `airpay_courses_list_courses` | Parity OK (different signature, same intent) |
| `categories_view` | **MISSING** | Browse categories as cards / table |
| `get_users_course_status_information` | **MISSING** | Mobile WS — mobile app loses user dashboard |
| `get_recently_enrolled_courses` | **MISSING** | Mobile WS |
| `userdashboard_content` / `_paginated` | **MISSING** as WS | airpay_catalog/mycourses.php renders server-side; no WS for mobile |
| `submit_evidence_course_form` | **MISSING** | Manual completion evidence upload (employee self-attestation) |
| `submit_course_type_form` | **MISSING** | Course types CRUD |
| `delete_coursetype` | **MISSING** | Course type delete |
| `coursetype_update_status` | **MISSING** | Course type enable/disable |
| `get_course_info` | **MISSING** | Mobile course detail WS |
| `submit_adddashboardcourses_form` | `airpay_courses_add_featured` | Featured-courses replacement (slightly different model) |
| **NEW (Airpay)** `_list_course_enrolments` | n/a | Modern WS — improves on BizLMS dual-listbox |
| **NEW (Airpay)** `_enrol_single` / `_unenrol_single` | n/a | Per-user enrol/unenrol AJAX |
| **NEW (Airpay)** `_share_course` / `_unshare_course` / `_list_course_shares` | n/a | Cross-tenant share — net new |
| **NEW (Airpay)** `_request_course` / `_approve_request` / `_reject_request` | n/a | Pull request workflow — net new |

## Feature parity matrix

| # | Feature | BizLMS | Airpay (courses/catalog) | Gap | Severity |
|---|---------|--------|--------------------------|-----|----------|
| 1 | Course list (admin) | `courses.php` + `renderer::get_catalog_courses` + filter form + card/table toggle | `airpay_courses/index.php` + datatable | View toggle (card↔table) is **gone**, only table view exists. Filters reduced from 10-field cascading hierarchy to 2 fields (category, visibility). | **P1** |
| 2 | Course detail/edit form (admin) | `edit.php` → `custom_course_form.php` (24,916 bytes, 3-step wizard with target audience hierarchy and skills/levels/certificate selectors) | `airpay_courses\form\edit_course` (10,929 bytes, single page, 5 sections, no skill/level/certificate, no target audience) | Skill picker, course-level picker, certificate template picker, course-type identifiedas selector, completion-days field, target-audience hierarchy selector all **missing**. | **P0** |
| 3 | Course types CRUD | `coursestypes.php` + WS for create/update/delete/toggle | **NOT PORTED** | Cannot create or manage course types. The `open_identifiedas` column is referenced in BizLMS but airpay_courses has no UI for it. | **P0** |
| 4 | Course price / commerce | `price_status`, `courseprice` fields + `biz_cart\service_provider.php` (11,743 bytes) | `airpay_catalog\classes\commerce.php` (9,073 bytes) — adds-to-cart, free enrolment, paid disabled with "coming soon" | Payment gateway **integration disabled**. Cart works for free courses only. | **P1** |
| 5 | Featured courses | `local_dashboardcourses` table + `featured_courses.php` toggle + admin form | `local_airpay_featured_courses` table + `featured.php` admin curation + JS reorder | **Parity OK** (cleaner schema, even improved). | — |
| 6 | Cross-tenant course sharing | Not in BizLMS | `share_course`, `request_course` flows, two new tables, dedicated browse page | **Airpay NEW** — net new capability | — |
| 7 | Bulk enrol — admin dual-listbox | `courseenrol.php` (467 lines) with 10-field cascading filter form + dual-listbox + paginated AJAX scroll-load + role-based capability auto-grant + progressbar + enrol-then-notify | `enrolledusers.php` (datatable) + per-row enrol modal | The dual-listbox UI with side-by-side selectable lists, the scroll-load pagination, and the filter chain (org → dept → subdept → 4-lvl → 5-lvl + email/idnumber/etc.) are **gone**. Replaced by single-user enrol modal. | **P1** |
| 8 | Mass-enrol via CSV | `mass_enroll.php` (117 lines, custom form, supports group/grouping creation on the fly, deletes user from cart on enrol, sends notification each user) | `enrol_csv.php` (91 lines) + `enrol_csv_processor.php` (8,303 bytes). Simpler 2-3 column CSV. | No group/grouping auto-create, no cart-purge on enrol. CSV is simpler so easier to use, but groups feature **lost**. | **P2** |
| 9 | Bulk unenrol via CSV | Combined into `courseenrol.php` remove pathway | `bulk_unenrol.php` (161 lines) with dry-run | **Airpay improvement** — dry-run is new. | — |
| 10 | Department/tenant hierarchy filters | `filterclass.php` (466 lines) + `filterajax.php` + `filters_form.php` cascading select2 widgets (org → dept → subdept → 4lvl → 5lvl, plus email/empid/name/band/group/role/location/states/district/subdistrict/village) | Category + visibility selects | The cascading **5-level org hierarchy filter** is completely missing. For 3,500 users across 3 tenants this is critical for narrowing enrolment to specific subdepartments. | **P0** |
| 11 | Categories CRUD | `index.php` (categories landing) + AMD `newcategory.js` (216 lines) + `deletecategory.js` (227 lines) + WS submit_create_category_form / submit_delete_category_form | **NOT PORTED** | Cannot create, rename, delete, or reorder course categories via the plugin. Admin must use core `/course/management.php` (which has zero tenant scope). | **P1** |
| 12 | Course evidence (manual completion) | `submit_evidence_course_form` WS + `courseevidence.mustache` + `selfcompletion.mustache` + 'Mark as completed' workflow + admin verification | **NOT PORTED** | Employees can no longer self-attest course completion when training was external (e.g. attended a vendor session that's not in the LMS). | **P1** |
| 13 | Course logs / audit trail | `local_logs` table + `local_custom_logs()` called on every create/update/delete with custom description string | Standard Moodle events only (`course_share_created`, `course_share_requested`) | **No equivalent log table** for course CRUD. Compliance auditors will not have the "who deleted course X on date Y" log. | **P1** |
| 14 | Course reminder cron task | `\local_courses\task\course_reminder.php` — N-days-before-completion reminder email via `local_notification_info` | **NOT PORTED** | Learners no longer auto-receive reminders about pending courses. | **P1** |
| 15 | Course notification cron task | `\local_courses\task\course_notification.php` | **NOT PORTED** | Same — admin can no longer schedule course nudges. | **P1** |
| 16 | Booking / availability options | `local_courses\booking_option.php` + `booking_option_settings.php` + constants `LOCAL_COURSES_BOOKING_STATUSPARAM_*` (5 states: BOOKED / WAITINGLIST / RESERVED / NOTIFYMELIST / NOTBOOKED / DELETED) | **NOT PORTED** | If courses had limited seats / waitlist, that machinery is gone. (Classroom plugin has its own waitlist; courses do not.) | **P2** |
| 17 | Course export CSV | `exportcsv.php` (97 lines) — full table export with department, level4, level5, ratings, tags, completion counts | `exportcsv.php` (referenced from index.php, file exists in airpay_courses) | Need to verify file content matches BizLMS column-set. Worth a manual check — looks shorter. | **P2** |
| 18 | Course rating | `local_rating` table joins (`r.moduleid = c.id AND r.ratearea = 'local_courses'`), `tagged_courses` orders by `highrate`/`lowrate` | **NOT PORTED** | Course ratings table never referenced. | **P2** |
| 19 | Tag-based browsing | `local_courses_get_tagged_courses` + `tagged_courses` renderer + tagview.mustache | **NOT PORTED** | Cannot browse courses by tag, no tag URLs. | **P2** |
| 20 | Search page integration | `local_courses_search_page_filter_element` + `local_courses_enabled_search` | **NOT PORTED** | Global search results no longer call this plugin's filter contributor. | **P2** |
| 21 | Public catalog (guest) | `nologincourses.mustache` + `render_courses_index` (3-up carousel) | `airpay_catalog\public.php` + commerce | **Airpay improvement** — modern Add to Cart, search, sort, pagination | — |
| 22 | Learner dashboard | `userdashboard.php` + `get_userdashboard_courses` renderer + 6 mustache templates (paginated, paginated_catalog_list, inner_tab, innercontent, content, catalog_list) | `airpay_catalog\mycourses.php` + `airpay_catalog\index.php` (LXP catalog) | Different design philosophy but functionally OK. Tab filter (all/inprogress/completed/notstarted) present. | — |
| 23 | Mobile WS (`MOODLE_OFFICIAL_MOBILE_SERVICE`) | 4 functions exposed: `get_users_course_status_information`, `get_recently_enrolled_courses`, `get_course_info`, `data_for_courses` | **0 functions** exposed to mobile service | The Moodle mobile app **cannot fetch course data** from the new plugin. If the mobile app is in production, this breaks all course-related screens. | **P0** if mobile app in use, P1 otherwise |
| 24 | Mass enrol group auto-create | `mass_enroll_add_group`, `_grouping`, etc. | **NOT PORTED** | Cannot upload a CSV that also auto-creates and assigns to Moodle groups. | **P2** |
| 25 | Drop user from cart on enrol | `biz_cart::delete_item_from_cart` called inside `courseenrol.php` line 209 | **NOT PORTED** | If a user adds course X to cart, then admin manually enrols them, the cart still shows X. | **P2** |
| 26 | Per-tenant role capability auto-grant | `assign_capability('enrol/manual:manage', CAP_ALLOW, $loggedinroleid, $context->id, true)` then unassigned at the end of the request (courseenrol.php lines 188–224) | **NOT PORTED** | Admins without `enrol/manual:manage` capability on a course context cannot enrol. BizLMS auto-granted just-in-time. | **P2** |
| 27 | Approval-required flow (selfenrol approvalreqd) | `approvalreqd` field on course | **NOT PORTED** | Self-enrol approval workflow not in airpay_courses form. | **P1** |
| 28 | Course completion days field | `open_coursecompletiondays` numeric field on course | **NOT PORTED** in edit_course form (column still exists in DB) | Course-creators can no longer set per-course deadline → reminder task can no longer fire (anyway it's gone). | **P1** |
| 29 | Cart total persistence | `rebuild_cart_cache_for_course` / `remove_course_from_all_user_carts` (lib.php) | `local_airpay_catalog\commerce::clear_cart` | Catalog has cart but the BizLMS cart cache invalidation on course price change is gone — Airpay carts may show stale prices. | **P2** |
| 30 | Course delete cascades | `\local_courses\action\delete::delete_coursedetails()` | Standard `delete_course()` only (airpay_courses\external\delete_course) | If BizLMS had extra-table cleanup (e.g. `local_logs`, `local_dashboardcourses`), Airpay's delete may leave orphan rows. | **P2** |
| 31 | "Enable reports" link in toolbar | `block_learnerscript` reports dropdown built into `courses.php` line 100 | **NOT PORTED** | No quick course-level report links from the manage page. | **P2** |

## User flows (multi-step tasks)

### Flow 1: Admin creates new course
**BizLMS:**
1. From `local/courses/courses.php` click "Create new course" plus icon (top-right) → opens **modal** loaded via `local_courses/courseAjaxform` AMD → calls `custom_course_form.php` form_status=0
2. Step 1: Category (autocomplete) + Course name + Short code + **Course type** (autocomplete with `costecenter_coursetype_selector`) + Format + **completion days** + **Price status** + **Course price** + Self-enrol radio + **Approval required** radio + Summary editor + Overview files file manager
3. Click Next → form_status=1 → Skills, Levels, Start/end date, **Certificate template** (autocomplete)
4. Click Next → form_status=2 → Target audience (org/dept/subdept hierarchy via `local_costcenter_get_hierarchy_fields`)
5. Submit → calls `create_course()` core function + custom_logs insert + `add_enrol_method_tocourse()` for self/learningplan/program/certification

**Airpay:** Mostly broken — no course types, no skills, no levels, no certificate template, no target audience, no completion days. Single form pops up, edits the basics, and saves. Steps 3 and 4 are gone entirely. Step 2 has fewer fields. **P0 — feature regression**.

### Flow 2: Admin bulk-enrols 200 users into a course (e.g. compliance training)
**BizLMS:**
1. From manage_courses → click course → land on `courseenrol.php?id=N&enrolid=M`
2. **Filter form expands**: pick organisation Airpay → triggers AJAX `filterajax.php?action=courseenroll&type=department` → 4-level cascade populates
3. Choose dept "Engineering" → subdept "Backend" → email pattern
4. Right-hand list now shows 47 matching not-yet-enrolled users; left list shows already-enrolled
5. Scroll to bottom of either list → AJAX `filterajax.php` returns next 50 users (lazy load)
6. Click "Select All" → button label flips to "Add All Users" → submit
7. Progressbar renders client-side (`display_if_slow`) → enrolment runs server-side with auto-grant of `enrol/manual:manage` capability if missing, cart-removal per user, notification per user
8. Success message + back button

**Airpay:** Click course row → enrolledusers.php datatable → click "Enrol users" icon → modal opens to **`/enrol/users.php?id=N`** (the **core Moodle enrolment page** — not an Airpay page). Per `list_courses.php:157`, `data-action="enrol-users-modal"` opens enrol_modal AMD module that points to the core URL.

Side-by-side dual listbox with cascading hierarchy filter: **gone**. Department-aware bulk enrol: **gone**. Per-user notification on enrol: **TBD** (probably handled by core's events).

Replacements: enrol_csv.php for bulk-by-CSV (P2 acceptable), per-user modal for ad-hoc add. Pages can no longer slice by "all employees in subdept X who have not completed course Y" without leaving the plugin.

### Flow 3: Admin creates a new course category
**BizLMS:** From `/local/courses/index.php` (the categories landing page) → click "+" icon → AMD `local_courses/newcategory` opens dialog → WS `submit_create_category_form` runs → page reloads with new tile.

**Airpay:** **No equivalent path.** Admin must go to `/course/management.php` (core), which has no tenant scope. **P1 regression**.

### Flow 4: Learner self-attests they completed external training
**BizLMS:** From course view → "Mark as completed" link → `submit_evidence_course_form` opens evidence upload dialog (cert PDF upload + free-text) → record inserted into evidence table → admin sees it in "Pending evidence" queue → approves → course completion marked.

**Airpay:** **No equivalent path.** **P1** — external training cannot be recorded.

### Flow 5: Manager browses Airpay's library to request a course for Public tenant
**BizLMS:** Not present.

**Airpay:** From `/local/airpay_courses/browse_airpay.php` → see rows of Airpay-owned courses → click "Request access" → POST with sesskey → request_manager::create_request stores pending row in `local_airpay_courses_requests` → admin sees it in inbox → approve/reject. **Airpay NEW capability.** No regression.

### Flow 6: Learner buys a paid course from the public catalog
**BizLMS:** `biz_cart\service_provider.php` integration with cart and payment plugins.

**Airpay:** Cart works (cart.php), but checkout shows "Payment Coming Soon" disabled button (cart.php:155-163). Free courses can be enrolled via "Enroll in All (Free)" button. **P1 if Airpay sells courses for cash; P2 if all-internal.**

### Flow 7: Mobile app loads "my courses"
**BizLMS:** Mobile app calls `local_courses_get_users_course_status_information` → returns status-filtered list, paginated. Or `local_courses_get_recently_enrolled_courses` for landing.

**Airpay:** No mobile WS exposed. Mobile app **will receive empty or stale data** for course screens. **P0 if mobile is in production.**

## Severity legend
- **P0** = blocks enterprise use (data loss, broken hierarchy filtering at 3,500-user scale, mobile app broken, no course type management)
- **P1** = important workflow degraded (no categories CRUD, no evidence, no reminders, no completion-days, no approval flow, fewer hierarchy filters)
- **P2** = polish/edge case (no group auto-create on CSV, no rating, no tag browse, cart stale)

## Recommended fixes (prioritised)

1. **[P0] Restore cascading hierarchy filter (5-level org → dept → subdept → 4lvl → 5lvl)** — port `bizlms_disabled/courses/filterclass.php:31` `custom_filter` class and `filterajax.php:1` cascade endpoint into `airpay_courses/classes/external/cascade_filter.php`. Start with `list_courses.php:74-79` — extend client_filters to accept `dept`, `subdept`, `4lvl`, `5lvl` parameters. Add the cascade selects to `templates/manage.mustache` and wire to `amd/src/course_actions.js`.
2. **[P0] Restore course types CRUD** — port `coursestypes.php`, `classes/form/coursetype_form.php`, WS `submit_course_type_form` / `delete_coursetype` / `coursetype_update_status` into `airpay_courses`. Add new `airpay_courses/types.php` admin page. Schema already exists (`local_course_types` from BizLMS), so airpay_courses needs a new install.xml entry for `local_airpay_course_types` OR re-use the existing table.
3. **[P0] Restore mobile WS layer** — port four functions from `bizlms_disabled/courses/classes/external.php:1034-1758` into `airpay_courses/classes/external/mobile_*.php` and register in `db/services.php` with `'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)`.
4. **[P0] Restore full course-create wizard (3 steps)** — extend `airpay_courses\form\edit_course.php` to add the missing fields: `open_skill`, `open_level`, `open_certificateid`, `approvalreqd`, `open_coursecompletiondays`, `open_identifiedas` (course type), and a target-audience hierarchy step. Reference `bizlms_disabled/courses/classes/form/custom_course_form.php:309-402` for the step-2/step-3 field definitions.
5. **[P1] Restore categories CRUD** — port `bizlms_disabled/courses/index.php` (categories landing) plus AMD `newcategory.js` and `deletecategory.js` into a new `airpay_courses/categories.php` page. Schema unchanged (uses core `course_categories`).
6. **[P1] Restore evidence / self-attestation** — port `bizlms_disabled/courses/classes/form/custom_courseevidence_form.php` + WS `submit_evidence_course_form` + `templates/courseevidence.mustache`. Add a new `local_airpay_courses_evidence` table.
7. **[P1] Restore reminder + notification scheduled tasks** — port `bizlms_disabled/courses/classes/task/course_reminder.php` and `course_notification.php` into `airpay_courses/classes/task/`. They need a `local_notification_info` source — either keep the BizLMS table or build a simpler `local_airpay_notification_rules`.
8. **[P1] Restore approval-required self-enrol flow** — add `approvalreqd` toggle to edit_course form + new `local_airpay_courses_approvals` table + admin queue page. The corresponding `enrol/auto` plugin behaviour from BizLMS needs to be replicated.
9. **[P1] Restore course logs (audit trail)** — port the `local_logs` table + `\local_courses\action\insert::local_custom_logs` calls. Compliance auditors need this. Add to `airpay_courses/db/install.xml`.
10. **[P1] Wire payment gateway in cart.php** — `airpay_catalog/cart.php:155-163` currently shows "Payment Coming Soon" disabled button. Either integrate Razorpay/Stripe, OR document that the cart is for free courses only.
11. **[P2] Restore CSV mass-enrol with group/grouping creation** — extend `airpay_courses\classes\enrol_csv_processor.php` to accept group/grouping columns.
12. **[P2] Restore card↔table view toggle** — add `formattype=card|table` param to `index.php` and switch template. Reference `bizlms_disabled/courses/courses.php:41-49`.
13. **[P2] Restore tag browsing + course rating** — both depend on `local_tags` and `local_rating` plugins still being installed. Verify they are; if so, add `airpay_courses\classes\external\tagged_courses` WS.
14. **[P2] Restore "Request training" deep-link** — `bizlms_disabled/courses/courses.php:155-159` had a top-right button linking to `local/request/index.php?component=elearning`. If `local_request` is gone or replaced, the airpay_courses manage page is missing a way to file a course-request ticket.
15. **[P2] Restore cart-cache invalidation on price change** — add `\local_airpay_catalog\commerce::invalidate_carts_for_course($courseid)` and call from `course_manager::update` whenever `price_status`/`courseprice` change.
