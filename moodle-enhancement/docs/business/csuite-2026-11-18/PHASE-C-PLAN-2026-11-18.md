# Phase C implementation plan: night of 18-19 Nov 2026

The C-suite demo is on UAT on 19 Nov at 11:00. Code freezes at 07:00, and the UAT push is at 09:00 on Nitin's go.

This plan was written read-only on 2026-11-18 at about 21:35. It combines the six area plans with
`SURFACE-WALK-2026-11-18.md`, and every line it cites was checked against the current working tree, Phase B
included.

**Note on the input.** The area-plan JSON was cut off part-way through the last area (org, certificates and
admin), inside item V19-CERT-TEMPLATES. That item is completed here from the repo. If any area plan came after it,
it was not received; section 7 lists the surface-walk items that are therefore not planned.

---

## 0. Ground rules for every implementer

1. **Build on the current working tree.** Phase B is uncommitted and is being deployed and verified right now.
   - Never run `git checkout`, `reset`, `stash`, `commit` or `push`, and never delete a file.
   - Re-read each file from disk immediately before you edit it. Phase B's fixer and other groups are working at
     the same time.
2. **Edit only the files your group owns** (section 2). If you need a change in a file you do not own, put it in
   your return. Do not make it.
3. **Plugins exist twice.** Apply every edit byte-identically to `moodle-enhancement/<path>` and `<path>`. The
   theme exists once, under `theme/sentientia/`.
4. **Lang files belong to G9 only.** No code group edits a `lang/` file or `theme/sentientia/version.php`.
   - Call `get_string()` with the exact keys in section 4.
   - Report any extra key you need in your return. Until G9 runs, a missing string shows as `[[key]]`; that is
     expected.
5. **Version bumps.** Each group bumps the plugin `version.php` files it owns, as listed. The theme gets one bump
   in G9.
6. **What implementers must not run:** phpunit, `upgrade.php`, cache purges, a browser, or a copy into
   `C:/xampp`.
   - Do run `php -l` on every PHP file you touch.
   - Write or update the tests named in each item. They are run centrally after 02:30.
7. **Coding standards:**
   - `defined('MOODLE_INTERNAL') || die();`.
   - Output through `s()` or `format_string()`.
   - Use the `$DB` API with named placeholders and `{table}` names.
   - SCSS uses tokens only. `_surface-*.scss` may not contain literal transitions.
   - Bootstrap is 4.6: there are no `gap-*` utilities.
8. **Stop rules:**
   - If a spec does not match the file (the code differs materially, not just moved lines), skip the item and
     report it. Do not improvise.
   - The hard stop for code groups is **01:45**. Finish the current item, then report what is done and what is
     not.
   - Items marked P4 are the first to drop.
9. **No new user-visible feature ships without a flag.** Every DO item below is a fix or polish of an existing
   surface. V16 is listed as decision D3 so that Nitin can overrule that classification.

---

## 1. What changed from the area plans (critic findings)

| Item | Area verdict | Phase C verdict | Why |
|---|---|---|---|
| CAT-1 | DO | **DO, P0 (must fix)** | Confirmed: the self-enrol fallback is a new enrolment path with no flag (course.php:54-59, 108-116, 220-229). The storefront-learner cart button stays, because it is the same `addtocart` action that public.php's own card already uses for those courses. HEAD's button there was a dead `/enrol/index.php` link. |
| CI-ORG-STEP | DO | **DO, P0 (must fix)** | Gate output reproduced (1 finding, 2026061700 is not above 2026101502). Both trees were at 2026101502 at HEAD, and the top tree already had the step, so renumbering both to 2026111800 passes the gate. Line refs corrected: block 45-86, `if` at 54, savepoint at 85, `return true` at 100. |
| STALE-COMMENTS (theme) | DO | **DROP** | Its new comment text described `self_enrol()` as a way in. Once CAT-1 removes that path, the existing comments at core_renderer.php:1400-1401 and 1456 ("self-enrolment is not a way into a course here" / "stays closed") are accurate again. |
| ACTIVE-LIST | DO (med) | **DEFER** | Six queries plus team_manager change the compliance figures 2 h before a demo where "72%" is a talking point. There are 0 stale rows locally, so the change cannot be verified here. A read-only UAT check goes in the 09:00 pre-flight (D6). |
| MGR-EXPORTCSV | DO | **DEFER** | Lowest value in its area (45 min). |
| V11-EXPORT | DO | **DO, P3** | Kept. Low risk, and the export may be shown. |
| V25-row, V25-nav | DO | **DEFER** | The `learner_history` flag is OFF and vp_eval77 has no UAT twin, so neither is visible on UAT. |
| V25-dup | DO | **DO, P4** | A real integrity bug that is not flag-gated, but not demo-visible. First to drop in G6. |
| V15-requests | DO | **Trimmed to V15-titles** | Requests.php now redirects (B7), so the page is rarely seen. Only the page titles of index, allocations and requests are kept. |
| V15-breadcrumb-mobile | DO (profile area) | **DO, moved to G2** | `_layout-shell.scss` belongs to the theme-chrome group. |
| V19-TABLE-HEADER | DO (AMD build) | **DO, PHP route** | The AMD build needs grunt in the XAMPP dirroot while Phase B deploys into it. Instead, declare an `actions` column on the 5 pages. That is datatable.js's existing WF-023 path: same visual result, no build. |
| V8 (mycourses) | not planned | **NEW DO (G1B)** | `mycourses.mustache` is all English (All Courses, In Progress, Completed, Not Started, Done, Continue, Review, Start, empty state). That is the walk's top V8 example for uat_learner2_airpay. The card fallback also prints `{{{shortname}}}` raw (V5). |
| B4 team table | not planned | **NEW DO (G3)** | custom_media.scss:720-727 styles `.airpay-dash__team-table-wrapper`, but dashboard.mustache:586 never prints the wrapper, so the 390 px table is cut (B4, still open). One wrapper div fixes it. |
| LEADERBOARD caption | part of DECISION | **Caption DO (G3)**, masking stays D2 | "Your department" is wrong (the board is tenant-wide). Swapping to the existing `dash_your_organisation` string changes a label only. |
| V11-STICKY | (considered) | **DEFER** | Compliance dark mode uses `body.dark-mode` literals. A sticky first cell needs its own dark background and hover rules, and cannot be verified safely tonight. The working tree already wraps all four tables in scroll containers. |
| B1-PORT | DEFER | **DEFER** | 120 min: a new vendored subplugin, a core-mods record and a new plugin install on UAT. It does not fit. The spec is kept in section 6. |
| B6 | DECISION | **D1; G7 conditional** | The session auto-memory (`project_compliance_team_scope.md`, written 20:14 IST in this session) records Nitin's answer "B6: option 1". G7 runs only if the lead confirms that from Nitin's chat. |

Also verified while reviewing:
- `message_popup\api::count_unread_popup_notifications()` and `core_message\api::is_processor_enabled()` exist
  in core (5.3: message/output/popup/classes/api.php:98, message/classes/api.php:1697).
- `tenant::root_for_user()`, `tenant::path_descendant_filter($path, $alias, $column, $tag)`,
  `enrolment::public_tenant_id()` and `catalog_manager::course_poster()` exist with the signatures the specs use.
- Every CSS token used below exists in `_tokens.scss`. The spacing scale is 4 px (`--ap-space-4` = 16 px).
- No lang key planned below collides with an existing key.
- The cross-tree lang files are identical for catalog, users, manager, authoring and pages. Only
  `local_sentientia_courses` lang drifts, by baseline, so V23 uses core strings only.

---

## 2. Groups and file ownership

Every file has exactly one owner. "Both trees" means `moodle-enhancement/<path>` and `<path>`.

| Group | Owns | Items (priority order) | Est. (buffered) |
|---|---|---|---|
| **G1A** Catalog course page | both trees, `local/sentientia_catalog/`: `course.php`, `classes/enrolment.php`, `styles.css`, `tests/enrol_fallback_test.php`, `tests/request_offer_test.php`, `tests/enrolment_test.php`, `version.php` | CAT-1, CAT-3c, V5, V8-COURSE, CAT-2 (P3), CAT-4c (P4) | 135 (175) |
| **G1B** Catalog lists and cart | both trees, `local/sentientia_catalog/`: `classes/catalog_manager.php`, `cart.php`, `templates/mycourses.mustache`, `mycourses.php`, `tests/catalog_home_test.php` (new), `README.md`; also `moodle-enhancement/state-cards/sentientia_catalog-state.md` | CAT-3, V8-MYCOURSES, CAT-4b (P4), CAT-8a | 75 (95) |
| **G2** Theme chrome | `theme/sentientia/`: `classes/output/core_renderer.php`, `templates/drawers.mustache`, `scss/moodle/partials/_layout-shell.scss`, `_surface-footer.scss`, `_surface-message.scss`, `_legacy-admin.scss`, `layout/frontpage.php`, new `tests/shell_title_test.php`, `tests/topbar_notifications_test.php`, `tests/layout_footer_template_test.php` | V21-logo, V4-admin-footer, V3, V20-bell (renderer + SCSS), V15-breadcrumb-mobile, V21-fab (P3), V20-notifications-page (P3) | 140 (180) |
| **G3** Theme dashboard | `theme/sentientia/`: `layout/dashboard.php`, `templates/dashboard.mustache`, `templates/components/course_progress_card.mustache`, new `classes/local/teaching_courses.php`, new `tests/teaching_courses_test.php`, new `tests/dashboard_layout_source_test.php`, `tests/dashboard_chrome_template_test.php` | DASH-QUICKNAV, DASH-THROWABLE, B4-TEAMTABLE, LB-CAPTION, V20-bell (dashboard wiring), V5-DASH, V16; LB-MASK only if D2 is yes | 145 (190) |
| **G4A** Profile | both trees, `local/sentientia_users/`: `classes/user_manager.php`, `templates/profile.mustache`, `tests/profile_labels_test.php` (new), `version.php`; also `theme/sentientia/scss/moodle/partials/_surface-user.scss` | V6-buttons, V6-avatar, V6-badges, V6-na | 85 (110) |
| **G4B** Manager pages, Authoring, sidebar | both trees, `local/sentientia_manager/`: `performance.php`, `templates/performance.mustache`, `member.php`, `templates/member.mustache`, `index.php`, `allocations.php`, `requests.php`, `version.php`; both trees, `local/sentientia_authoring/`: `studio.php`, `index.php`, `version.php`; `theme/sentientia/classes/sidebar_navigation.php` and `theme/sentientia/tests/nav_chrome_test.php` | V17-copy, V17-model, V17-drafts, V15-perf, V15-member, V15-titles | 110 (145) |
| **G5** Admin, certificates, CI gate | both trees: `local/sentientia_org/db/upgrade.php` and `version.php`; `local/sentientia_learningpath/` (`classes/external/list_paths.php`, `templates/manage.mustache`, `index.php`, `tests/external/list_paths_test.php`, `version.php`); `index.php` of `local/sentientia_users`, `local/sentientia_courses`, `local/sentientia_classroom` and `local/sentientia_programs`; `local/sentientia_pages/` (`certificate_templates.php`, `version.php`); `local/sentientia_courses/` (`browse_airpay.php`, `templates/browse_airpay.mustache`, `version.php`); `local/sentientia_request/` (`classes/catalog_cta.php`, `README.md`) | CI-ORG-STEP, V19-LP-AMP, V19-LP-STATUS, V19-TABLE-HEADER, V19-CERT-TEMPLATES, V23, CAT-8b | 135 (175) |
| **G6** Compliance and analytics | both trees, `local/sentientia_compliance_report/`: `classes/compliance_engine.php`, `index.php`, `export.php`, `tests/compliance_engine_test.php`, `version.php`; both trees, `local/sentientia_analytics/`: `classes/analytics_manager.php`, `tests/analytics_manager_test.php`, `version.php`; `theme/sentientia/classes/local/dashboard_compliance.php` (docblock only); both trees, `local/sentientia_evaluation/`: `classes/evaluation_manager.php`, `tests/anonymous_resubmit_test.php` (new), `version.php` | KPI-CACHE, V12, V11-EXPORT, V25-dup (P4) | 120 (155) |
| **G7** B6 option 1 (**only if D1 is confirmed**). Starts after G4B, because it shares `sidebar_navigation.php` | both trees, `local/sentientia_compliance_report/`: `classes/viewer_scope.php`, `tests/team_scope_test.php`; `theme/sentientia/`: `classes/role_detector.php`, `classes/sidebar_navigation.php` (after G4B), `tests/role_detector_test.php`, `tests/dashboard_compliance_test.php`; both trees, `blocks/sentientia_compliance/`: `classes/audit.php`, `tests/audit_test.php`, `version.php` | B6-OPT1 | 75 (100) |
| **G9** Lang and theme version (final, sequential) | every `lang/en` and `lang/hi` file listed in section 4 (both trees for plugins); `theme/sentientia/lang/{en,hi}/theme_sentientia.php`; `theme/sentientia/version.php` | all strings in section 4, the single theme bump, the parity check | 45 (55) |

**Shared-file conflicts resolved:**
- `layout/dashboard.php`, `dashboard.mustache` and `dashboard_chrome_template_test.php`: V20's dashboard steps
  move to G3. G2 adds the renderer method, and G3 calls it behind `method_exists`, so the order does not matter.
- `_layout-shell.scss`: V15-breadcrumb moves to G2.
- `sidebar_navigation.php`: owned by G4B (V17-drafts). G7 runs after G4B. V25-nav is cut.
- `nav_chrome_test.php`: owned by G4B. G2 writes new test files instead.
- `local_sentientia_users`: G4A owns the profile files and `version.php`. G5 owns only `index.php`, which needs no
  bump.
- `local_sentientia_courses`: G5 owns both `index.php` and `browse_airpay.php`.
- `local_sentientia_compliance_report`: G6 owns `version.php`, and its 2026111801 bump also covers G7's code.
  G7 does not edit that `version.php`.
- `theme/sentientia/version.php`: G9 only, with one bump.
- Every lang file: G9 only.

---

## 3. Item specifications

### G1A: catalog course page (both trees, byte-identical; course.php is identical in both trees today)

**CAT-1. MUST FIX: remove the unflagged self-enrol fallback.** DO, low risk, P0, 40 min.
- **(a)** course.php: delete lines 54-59, the comment plus the `if (enrolment::self_enrol($id)) { redirect(...); }`
  block inside the `enrolnow` handler.
- **(b)** Delete lines 108-116: the comment and the `$selfenrol = false; if (...) { $selfenrol = ...self_enrol_instance($id) !== null; }`
  block.
- **(c)** After line 69 (`$is_loggedin = ...`), and before the inactive-enrolment block, add:
  ```php
  // Persona walk 2026-11-18: a learner of the Public storefront tenant (enrolment::public_tenant_id(), the tenant
  // public.php sells) is a consumer: no L&D team, and the cart is the way into a free course (decision 2026-05-29).
  $storefrontviewer = $is_loggedin
      && \local_sentientia_platform\tenant::root_for_user($USER) === \local_sentientia_catalog\enrolment::public_tenant_id();
  // A course public.php itself lists (owned by the storefront tenant): its card there adds it to the basket.
  $storefrontcourse = \local_sentientia_platform\tenant::root_for_user((object) ['open_path' => (string) ($course->open_path ?? '')])
      === \local_sentientia_catalog\enrolment::public_tenant_id();
  ```
- **(d)** Lines 90-94, the `match`:
  - `'ended' => get_string($storefrontviewer ? 'enrolment_ended_public' : 'enrolment_ended', 'local_sentientia_catalog', $when)`;
  - `default => get_string($storefrontviewer ? 'enrolment_suspended_public' : 'enrolment_suspended', 'local_sentientia_catalog')`.
- **(e)** In the `elseif ($pricing['is_free'] && $is_loggedin)` branch, keep the `$canview` "Open course" branch
  first. Replace the `elseif ($selfenrol):` form (lines 220-229) with `elseif ($storefrontviewer && $storefrontcourse):`.
  - The new branch is a POST form to course.php with `sesskey` and `action=addtocart`, styled exactly like the
    paid add-to-cart form (lines 256-263).
  - Its label is `<i class="fa fa-cart-plus" aria-hidden="true"></i> <?php echo s(get_string('public_addtocart', 'local_sentientia_catalog')); ?>`.
  - Put this comment before it: "The same action as public.php's card for this course: the basket, whose Enrol in
    all (free) enrols (decision 2026-05-29: the Public tenant keeps the cart)".
  - In the `else` notice (line 237), use `get_string($storefrontviewer ? 'enrolment_managed_public' : 'enrolment_managed', 'local_sentientia_catalog')`.
- **(f)** enrolment.php:
  - Delete `bizlms_allows_self_enrol()` (lines 155-174), `self_enrol_instance()` (176-226) and `self_enrol()`
    (228-270).
  - In `request_state()`, delete `global $USER;` (line 139) and lines 148-151, the `self_enrol_instance` check.
  - Restore its docblock (lines 114-127) to "the two things only the catalogue knows", with only the PAID and
    one-click bullets. Change the parenthesis to "(..., a course submit() would accept; the theme never renders
    /enrol/index.php, so self-enrolment is not a way in)".
  - In the `is_priced()` docblock (325-329), change "shared by enrol_now() and self_enrol() since the persona walk
    of 2026-11-18" to "used by enrol_now() (extracted 2026-11-18)".
  - Keep `inactive_enrolment()` and `is_priced()`.
  - Check: `grep -n self_enrol` over both catalog trees then hits only comments or docblocks.
- **(g)** tests/enrol_fallback_test.php: keep the file and `learner()`, `course()`, `enable_self()` and
  `test_inactive_enrolment_names_the_reason_core_agrees_with`. Delete:
  - `add_fee()` (79-88);
  - the tests at 90-102, 106-128, 130-149, 151-159, 161-170, 172-185 and 187-198.

  Rewrite the class docblock (9-35): it now covers course.php's inactive-enrolment notice, with
  `@covers \local_sentientia_catalog\enrolment::inactive_enrolment` only. Add the sentence "The self-enrol
  fallback was added and withdrawn on 2026-11-18: a new enrolment path with no flag".
- **(h)** tests/request_offer_test.php: delete header lines 20-21. Replace the test at 132-157 with
  `test_a_free_course_with_an_open_self_enrolment_still_offers_the_request`:
  - same setup: the course's self instance has status ENABLED, `customint6 = 1` and `password = ''`;
  - assert `'request' === enrolment::request_state($learner, $course->id)`;
  - comment: "self-enrolment is not a way in on this theme (catalog_cta)".
- **Strings (G9):** `enrolment_managed_public`, `enrolment_suspended_public`, `enrolment_ended_public`.
- **Version:** see CAT-VERSION below.
- **Tests:** php -l. Centrally: enrol_fallback_test, request_offer_test, enrolment_test, price_source_test,
  tenant_gate_test and storefront_checkout_test.
- **Visual, 1440 and 390:**
  - vp_public77, course.php?id=71: "Add to cart" lands on `?added=1` and the item is in the sidebar cart.
  - vp_learner1, course.php?id=448 and 456: the managed notice and no Enrol button, unless one-click is ON.
  - vp_admin1, course.php?id=383: "Open course" or "Continue Learning".
  - vp_learner177, course.php?id=449: the employee notice.
  - No "Enrol now — free" self-enrol button anywhere.

**CAT-3c. Course page uses the tenant catalogue home.** DO, low risk, P1, 5 min. G1B adds the helper; both land
in the same deploy.
- course.php line 16: `redirect(\local_sentientia_catalog\catalog_manager::catalog_home_url($USER));`.
- Line 278, the back link href: `\local_sentientia_catalog\catalog_manager::catalog_home_url($USER)->out()`.

**V5. Course detail layout.** DO, low risk, P2, 35 min.
- Move the layout declarations from inline styles into classes (inline styles beat classes):
  - Line 147 hero: add `class="ap-public-course__hero"` and remove `padding:40px 32px;` from its style.
  - Wrap the hero content in `<div class="ap-public-course__hero-inner"><div class="ap-public-course__hero-text">…chip/h1/meta…</div>`.
  - If `$poster['imageurl'] !== ''`, add `<img class="ap-public-course__poster" src="<?php echo s($poster['imageurl']); ?>" alt="" aria-hidden="true" loading="lazy">`, then close the inner `</div>`.
  - Compute `$poster = \local_sentientia_catalog\catalog_manager::course_poster($id);` near line 128.
  - Line 151 h1: `class="ap-public-course__title"`, and move font-size into the class.
  - Line 152 meta: `class="ap-public-course__meta"`, and drop the inline display and gap.
  - Line 159 body: `class="ap-public-course__body"`, and drop the inline padding.
  - Line 162 pricing row: `class="ap-public-course__cta-row"`. Keep margin-bottom, padding, background and
    border-radius inline; drop display, justify and align.
  - Line 171: `class="ap-public-course__actions"`, and drop the inline style.
- About section (268-274): render it only when
  `$hassummary = trim(strip_tags($summary, '<img>')) !== ''`.
- Price block (163-170): show it only when `!$pricing['is_free'] || !$is_loggedin || $storefrontviewer`.
  Employees are not buyers.
- Append to styles.css, before the final "── Mobile ──" block (line 1178):
  ```css
  /* ── Course detail (course.php), persona walk 2026-11-18 V5 ── */
  .ap-public-course__hero { padding: 40px 32px; }
  .ap-public-course__hero-inner { display: flex; align-items: center; gap: var(--ap-space-6); }
  .ap-public-course__hero-text { flex: 1 1 auto; min-width: 0; }
  .ap-public-course__title { font-size: 28px; overflow-wrap: anywhere; }
  .ap-public-course__poster { flex: 0 0 220px; width: 220px; aspect-ratio: 16 / 9; object-fit: cover; border-radius: var(--ap-radius-md); }
  .ap-public-course__meta { display: flex; flex-wrap: wrap; gap: var(--ap-space-2) var(--ap-space-4); }
  .ap-public-course__body { padding: 28px 32px; }
  .ap-public-course__cta-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: var(--ap-space-3) var(--ap-space-4); }
  .ap-public-course__actions { display: flex; flex-wrap: wrap; gap: var(--ap-space-2); }
  @media (max-width: 590px) {
      .ap-public-course__hero { padding: var(--ap-space-6) var(--ap-space-4); }
      .ap-public-course__hero-inner { flex-direction: column-reverse; align-items: stretch; }
      .ap-public-course__poster { flex: none; width: 100%; }
      .ap-public-course__title { font-size: 22px; }
      .ap-public-course__body { padding: var(--ap-space-5) var(--ap-space-4); }
      .ap-public-course__actions { width: 100%; }
      .ap-public-course__actions > * { flex: 1 1 100%; text-align: center; }
      .ap-public-course__actions > form > button { width: 100%; }
  }
  ```
- **Visual:**
  - vp_learner1, course.php?id=448 at 1440, 590 and 390: the button sits on its own full-width line under the
    price, with no overlap.
  - vp_public77, id=71: the price shows.
  - If a course has an overview image, the poster sits on the right on desktop and above the title on mobile.
  - An empty summary shows no "About" heading.
  - Dark mode once at 1440.

**V8-COURSE. Hard-coded English on course.php.** DO, low risk, P2, 25 min. Do it in the same pass as CAT-1 and V5.
Replace the literals with `get_string(..., 'local_sentientia_catalog')`, through `s()` unless noted:

| Line | Text | String |
|---|---|---|
| 38 and 64 | 'Added to cart!' | `course_addedtocart_short` |
| 139 | added notice | `get_string('course_addedtocart', ..., format_string($course->fullname))`, echoed without `s()` |
| 140 | view-cart link | `course_viewcart` with `$cart_count` |
| 153 | "N enrolled" | `course_enrolledcount` |
| 154 | "Self-paced" | `course_selfpaced` |
| 168 | "one-time" | `course_onetime` |
| 176 | "Continue Learning" | existing `continuelearning` |
| 246 | "Add to Cart" | existing `public_addtocart` |
| 253 | "Login to Access" | `course_login` |
| 261 | "Add to Cart — price" | `course_addtocart_price` with `$pricing['display']`, echoed raw as today |
| 270 | "About This Course" | `course_about` |
| 280 | "Back to Course Catalog" | `course_backtocatalog` |

- Visual: course.php?id=448 with `?lang=hi`, at 1440 and 390.

**CAT-2. Enrol now on an enrolment already held.** DO, low risk, P3, 20 min.
- **(1)** course.php, right after `require_sesskey();` in the `enrolnow` handler (line 46), add:
  ```php
  // Persona walk 2026-11-18: an enrolment already held is never enrolled again or reported as new. Active: open the
  // course. Not active (suspended, ended, not started): back to this page, which says why (inactive_enrolment()).
  if (isloggedin() && !isguestuser()) {
      $heldcontext = context_course::instance($id);
      if (is_enrolled($heldcontext, $USER)) {
          redirect(is_enrolled($heldcontext, $USER, '', true)
              ? new moodle_url('/course/view.php', ['id' => $id])
              : new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]));
      }
  }
  ```
- **(2)** enrolment.php `enrol_now()`, lines 397-401: inside `if (is_enrolled($context, $userid))`, replace
  `return true;` with `return is_enrolled($context, $userid, '', true);`.
  - Add the comment: "an inactive enrolment is refused and left untouched: manual enrol_user() would re-activate a
    suspended enrolment, which is the L&D team's call".
  - Docblock (line 346): "Idempotent: true with nothing written when the user already holds an ACTIVE enrolment;
    false with nothing written when they hold one that is not active."
- **Test:** add `test_enrol_now_refuses_and_leaves_a_suspended_enrolment` to enrolment_test.php.
  - Setup: `make_keyed_free_course(1)`, `make_user(1)`, and
    `enrol_user($user->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED)`.
  - Assert `enrol_now()` is false, the active check is still false, there is still 1 user_enrolments row, and its
    status is still SUSPENDED.
  - `test_enrol_now_is_idempotent` must still pass unchanged.

**CAT-4c. Return after "Log in to access".** P4 (drop first), 10 min.
- After the `addtocart` handler (after line 39), add:
  ```php
  // Persona walk 2026-11-18: "Log in" brings the visitor back to this course. Core login/index.php reads ?wantsurl=
  // only on Behat sites, so set the session's wantsurl (what require_login() does), and only on this click.
  if ($action === 'login') {
      if (!isloggedin() || isguestuser()) {
          $SESSION->wantsurl = (new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]))->out(false);
          redirect(get_login_url());
      }
      redirect(new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id]));
  }
  ```
- The login link (line 249) becomes `(new moodle_url('/local/sentientia_catalog/course.php', ['id' => $id, 'action' => 'login']))->out()`.

**CAT-VERSION.** local_sentientia_catalog `2026111800` → `2026111801` in both trees; the release stays
`'1.0.11-beta'`.
- Rewrite the comment at line 72 to describe the final state.
- Add: "self-enrol button added and withdrawn the same day: new enrolment path without a flag, against the
  2026-05-29 decision that the Public tenant keeps the cart; course page layout, strings, tenant catalogue
  back-link".
- G1B adds no bump of its own.

### G1B: catalog lists and cart (both trees, byte-identical)

**CAT-3. One catalogue-home rule in the plugin.** DO, low risk, P1, 25 min.
- Add to catalog_manager.php, after `assert_course_visible_to_viewer()` (it starts at line 180; insert after its
  closing brace):
  ```php
  /**
   * The catalogue list page for this viewer. Persona walk 2026-11-18: the same rule as the theme's sidebar
   * (theme_sentientia\sidebar_navigation::catalog_path()); keep the two in step. The tenant catalogue (index.php)
   * for a logged-in viewer of any resolved tenant other than the storefront tenant (enrolment::public_tenant_id());
   * the Public storefront (public.php) for that tenant, a guest, nobody, or a tenant that does not resolve.
   */
  public static function catalog_home_url(\stdClass $user): \moodle_url {
      if (empty($user->id) || isguestuser($user)) {
          return new \moodle_url('/local/sentientia_catalog/public.php');
      }
      $root = \local_sentientia_platform\tenant::root_for_user($user);
      if ($root <= 0 || $root === enrolment::public_tenant_id()) {
          return new \moodle_url('/local/sentientia_catalog/public.php');
      }
      return new \moodle_url('/local/sentientia_catalog/index.php');
  }
  ```
- cart.php line 108 ("Browse Courses") and line 209 ("Continue Browsing") use
  `\local_sentientia_catalog\catalog_manager::catalog_home_url($USER)`.
- **New tests/catalog_home_test.php** (both trees, `advanced_testcase`, uses
  `\local_sentientia_platform\phpunit\open_path_fixture_trait`). Expected results:
  - /1/3 → index.php;
  - /177 → index.php;
  - /77/5 → public.php;
  - empty open_path → public.php;
  - the guest → public.php;
  - `(object)['id' => 0]` → public.php;
  - after `set_config('public_tenant_id', 177, 'local_sentientia_pages')`: /177 → public.php and /77 → index.php.

  Compare with `->out_as_local_url(false)`.
- **Visual:**
  - vp_learner1: the course.php?id=448 back link goes to index.php.
  - vp_public77: the course.php?id=71 back link goes to public.php.
  - vp_learner177: the empty-cart "Browse Courses" goes to index.php.

**V8-MYCOURSES (new). My Courses in Hindi, and no raw shortname art.** DO, low risk, P2, 25 min.
templates/mycourses.mustache:
- Line 3: `aria-label="{{#str}}mycourses_filter_label, local_sentientia_catalog{{/str}}"`.
- Lines 8, 14, 20 and 26: `{{#str}}mycourses_all / mycourses_inprogress / mycourses_completed / mycourses_notstarted, local_sentientia_catalog{{/str}}`.
- Lines 39-41, the fallback: replace `<span>{{{shortname}}}</span>` with `<span><i class="fa fa-graduation-cap fa-2x" aria-hidden="true"></i></span>`.
  The title is printed right below the card art.
- Line 44 "Done": `mycourses_done`.
- Line 49, the aria-label: `{{#str}}mycourses_percentcomplete, local_sentientia_catalog, {{progress}}{{/str}}`.
  Line 70 text: the same string.
- Lines 76-78: `mycourses_review`, `mycourses_continue` and `mycourses_start`.
- Line 88, the aria-label: `mycourses_pagination`.
- Line 89: `{{showing_label}}`.
- Lines 104-108 (empty state): `mycourses_empty_title`, `mycourses_empty_message` and `mycourses_browse`. The CTA
  href becomes `{{{browseurl}}}`.

mycourses.php:
- Line 107: `'Never'` becomes `get_string('never')`.
- In the template context, add `'showing_label' => get_string('mycourses_showing', 'local_sentientia_catalog', (object)['from' => <showing_from>, 'to' => <showing_to>, 'total' => <total_filtered>])`.
  Use the same values the page already passes.
- Also add `'browseurl' => \local_sentientia_catalog\catalog_manager::catalog_home_url($USER)->out(false)`.

Strings go to G9. Visual: vp_learner1 /local/sentientia_catalog/mycourses.php in en and `?lang=hi`, at 1440 and
390. No English is left except course data, and a course without an image shows the icon.

**CAT-4b. Return to the cart after login.** P4, 5 min.
- Add the same `action=login` handler to cart.php after line 37, with
  `$SESSION->wantsurl = (new moodle_url('/local/sentientia_catalog/cart.php'))->out(false);`.
- The link at line 156 becomes `new moodle_url('/local/sentientia_catalog/cart.php', ['action' => 'login'])`.

**CAT-8a. Docs.** DO, P4, 15 min.
- Catalog README lines 65-67 (both trees): "the theme never renders /enrol/index.php (since 2026-11-18 it sends
  it to the catalogue course page, before that to /my)".
- State card `moodle-enhancement/state-cards/sentientia_catalog-state.md`:
  - Header lines 1-7: `local_sentientia_catalog`, version `2026111801` / `1.0.11-beta`, status "Not live: on
    UAT (Moodle 5.3); Sentientia replaces BizLMS at cutover", refreshed 2026-11-19.
  - Line 89 `local_airpay_core\phpunit\...` → `local_sentientia_platform\phpunit\open_path_fixture_trait`.
  - Line 60 `local/airpay_catalog/` → `local/sentientia_catalog/`.
  - Lines 15-18: `local_airpay_courses` / `local_airpay_cart` → `local_sentientia_*`.
  - Append a section "2026-11-18/19 persona walk: honest course-page CTAs": what changed, the self-enrol path
    withdrawn and why, the tests and the strings.

### G2: theme chrome

**V21-logo.** DO, low risk, P1, 10 min.
- `_surface-footer.scss`: `.airpay-footer__logo img { height: 32px; filter: brightness(10); }` (lines 87-90)
  becomes `height: 40px; filter: brightness(0) invert(1);`. Comment: surface walk V21, Brand Book (no teal or
  cyan), and the logo PNG is a transparent single-colour image.
- In the 590 px block (156-159), change `height: 28px` to `height: 32px`.
- `layout/frontpage.php` line 342: `.ap-footer__logo img { height: 32px; filter: brightness(0) invert(1); }`.
- `_legacy-admin.scss` line 431: update the stale comment to name the new filter.
- Tests: scss_test, and php -l frontpage.php.
- Visual: any footer at 1440 and 390: a white, readable logo, in both light and dark.

**V4-admin-footer.** DO, low-to-medium risk (every admin-layout page), P1, 20 min.
- drawers.mustache: after the closing `</div>` of `#page` (line 84) and before `{{{ output.airpay_shell_end }}}`
  (line 86), insert:
  - a Mustache comment: "11-18 surface walk V4: admin-layout pages had no footer; as in columns2, the last element
    of the content scroller; the partial prints standard_footer_html, standard_end_of_body_html and
    debug_footer_html";
  - then `{{> theme_sentientia/footer }}`.
- **Delete line 89** `{{{ output.standard_end_of_body_html }}}`. Otherwise the page JS loads twice.
- New tests/layout_footer_template_test.php. Strip comments as dashboard_chrome_template_test::template() does,
  then:
  - for drawers, columns2, course and course_editing, `{{> theme_sentientia/footer }}` appears exactly once;
  - `standard_end_of_body_html` does not appear outside comments;
  - in drawers, the footer comes before `{{{ output.airpay_shell_end }}}`.
- Also run `php moodle-enhancement/tools/scan_missing_end_of_body.php theme/sentientia/templates/drawers.mustache`
  (expect no output) and `php tools/check-theme-layout-regions.php`.
- Visual, with the console open on every page and 0 errors:
  - vp_learner1, /message/output/popup/notifications.php and /user/preferences.php, at 1440 and 390;
  - vp_manager1, /local/sentientia_manager/performance.php (it becomes standard layout through V15-perf anyway);
  - vp_siteadmin, /admin/search.php;
  - check that dropdowns and the user-tour reset still work.

**V3. Visible page title on shell pages that print none.** DO, low risk, P1, 35 min.
- core_renderer.php, after `topbar_messages_link()` (ends at line 296):
  - Add `public const SHELL_TITLE_BODYCLASS = 'ap-page-title';`.
  - Add the page map:
    ```php
    private const SHELL_TITLE_PAGES = [
        'local-sentientia_catalog-mycourses' => ['nav_courses', 'theme_sentientia'],
        'local-sentientia_catalog-index' => ['nav_catalog', 'theme_sentientia'],
        'local-sentientia_skills-index' => ['nav_myskills', 'theme_sentientia'],
        'local-sentientia_manager-index' => ['leftmenu_tm_dashboard', 'theme_sentientia'],
        'local-sentientia_compliance_report-index' => ['nav_compliance', 'theme_sentientia'],
    ];
    ```
  - Add `public static function shell_visible_title(\moodle_page $page): string`:
    - if the pagetype is mapped, return `get_string()` for its entry;
    - elseif `ap-page-title` is in `explode(' ', (string)$page->bodyclasses)`, return `self::sr_heading_text($page->heading)`;
    - else return `''`.
  - Its docblock lists the excluded pages, which print their own title: analytics, certificates, profile, the
    public storefront, catalog course.php, skills admin and the dashboard.
- In `airpay_shell_start()`, keep line 398 **byte-identical**: `sr_heading_text_test` pins it.
- Replace only line 399:
  ```php
  $visibletitle = self::shell_visible_title($this->page);
  $h1 = $visibletitle !== '' ? '<h1 class="ap-shell__title">' . s($visibletitle) . '</h1>' : '<h1 class="sr-only">' . s($page_title) . '</h1>';
  ```
  Add a one-line comment citing surface walk V3.
- `_layout-shell.scss`, after the breadcrumb block (about line 830, before the "Phase D2" topbar block):
  ```scss
  .ap-shell__title { margin: 0 0 var(--ap-space-4); font-family: var(--ap-font-family); font-size: var(--ap-text-2xl); font-weight: var(--ap-weight-bold); line-height: var(--ap-leading-tight); color: var(--ap-color-text-primary); }
  @media (max-width: 590px) { .ap-shell__title { font-size: var(--ap-text-xl); margin-bottom: var(--ap-space-3); } }
  ```
- New tests/shell_title_test.php:
  - each of the 5 pagetypes returns its nav string (new `moodle_page` plus `set_pagetype()`);
  - analytics-index, pages-certificates, users-profile and catalog-public return `''`;
  - the body-class opt-in with `set_heading('Risk &amp; Policy')` returns `'Risk & Policy'`;
  - the body class without a heading returns `''`;
  - the renderer source contains both `ap-shell__title` and `sr-only`.
  - `sr_heading_text_test` must stay green unchanged.
- No plugin template is touched. No other group adds a visible heading to these 5 pages; V15-title is deferred
  because V3 covers it.
- Visual:
  - vp_learner1, at 1440 and 390: mycourses, skills, and catalog index.php (the title above the search bar).
  - vp_manager1: manager index.php ("My Team") and the compliance report.
  - Negative checks: analytics, certificates and profile each show exactly one title.
  - `?lang=hi` shows "मेरे कोर्स".
  - Dark mode once.

**V20-bell (renderer + SCSS).** DO, low risk, P2, 30 min.
- core_renderer.php: add `public static function topbar_notifications_link(): string`.
  - Return `''` when `!isloggedin() || isguestuser()`.
  - `$count = 0;`. Unless `\core_user::awaiting_action()`, inside `try`:
    `if (\core_message\api::is_processor_enabled('popup')) { $count = (int) \message_popup\api::count_unread_popup_notifications((int) $USER->id); }`.
    On `\Throwable`, call `debugging(..., DEBUG_DEVELOPER)` and leave `$count` at 0.
  - No MUC cache and no static memo.
  - `$label = get_string('notifications');`. If `$count > 0`:
    - `$label .= ': ' . get_string('unreadnotifications', 'message', $count);`
    - `$badge = '<span class="ap-topbar__badge" aria-hidden="true">' . ($count > 99 ? '99+' : (string) $count) . '</span>'`.
  - Return the `<a class="ap-topbar__iconlink" href="…/message/output/popup/notifications.php" title aria-label>`
    with `<i class="fa fa-bell" aria-hidden="true"></i>` plus the badge.
  - Keep the literal `'/message/output/popup/notifications.php'`: a G3 test asserts it.
- In `airpay_shell_start()`, replace the three bell lines (365-367) with `. self::topbar_notifications_link()`.
- SCSS (`_layout-shell.scss`):
  - `.ap-topbar__iconlink` (838-856) gets `position: relative;`.
  - New rule:
    ```scss
    .ap-topbar__badge { position: absolute; top: 2px; right: 0; min-width: 18px; height: 18px; padding: 0 var(--ap-space-1); border-radius: var(--ap-radius-pill); background: var(--ap-color-danger); color: var(--ap-color-bg-surface); font-size: 0.6875rem; font-weight: var(--ap-weight-bold); line-height: 18px; text-align: center; box-shadow: 0 0 0 2px var(--ap-color-bg-surface); pointer-events: none; }
    ```
- New tests/topbar_notifications_test.php:
  - a guest gets `''`;
  - a user with no notifications gets a bell with no badge;
  - insert a `{notifications}` row (useridto = user, timeread NULL) plus a `{message_popup_notifications}` row:
    the badge shows "1" and the aria-label holds the unread text;
  - after setting timeread there is no badge;
  - a user with `auth_forcepasswordchange` gets no badge;
  - 100 rows give "99+". A small public static helper for the badge text is fine.
- Visual: vp_public77 (10 unread) and vp_learner1 (5), at 1440 and 390, on a shell page and on /my/; dark mode.

**V15-breadcrumb-mobile.** DO, low risk, P3, 10 min.
- `_layout-shell.scss`, after the `.ap-shell__breadcrumbs` rules (about line 818):
  ```scss
  @media (max-width: 575.98px) { .ap-shell__breadcrumbs .breadcrumb:not(:empty) .breadcrumb-item { flex-grow: 0; min-width: 0; } }
  ```
  core.scss:2700-2713 gives each crumb `flex-grow: 1`.
- Visual: vp_manager1 member.php?id=3430 at 390: the crumbs sit left-aligned.

**V21-fab.** P3, 10 min.
- Inside the existing `@media (max-width: 590px)` FAB block of `_surface-footer.scss` (284-293), add:
  ```scss
  body:has(#airpay-assistant) .airpay-footer { padding-bottom: calc(var(--ap-footer-fab-size) + var(--ap-space-3) + var(--ap-space-2)); }
  ```
  Add a comment.
- It only matters if `ai.assistant.enabled` is ON on UAT (pre-flight check P5).

**V20-notifications-page.** P3, 25 min. CSS only.
- Append a top-level block `body#page-message-output-popup-notifications { … }` to `_surface-message.scss`, after
  line 253:
  - `#page-header .page-context-header { display: none; }`
  - `#region-main > h2 { margin: 0 0 var(--ap-space-4); font-size: var(--ap-text-2xl); font-weight: var(--ap-weight-bold); color: var(--ap-color-text-primary); }`
  - `.notification-area { background: var(--ap-color-bg-surface); border: 1px solid var(--ap-color-border); border-radius: var(--ap-radius-lg); box-shadow: var(--ap-shadow-sm); overflow: hidden; }`
  - `.notification-area .control-area { background: var(--ap-color-bg-surface-alt); border-right-color: var(--ap-color-border); }`
  - `.notification-area .content-area .header { border-bottom-color: var(--ap-color-border); }`
  - `.notification-area .content-item-container { padding: var(--ap-space-3) var(--ap-space-4); border-bottom-color: var(--ap-color-border); color: var(--ap-color-text-primary); }`
  - Inside that rule: `&:hover, &.selected { background: var(--ap-color-primary-light); color: var(--ap-color-text-primary); border-color: var(--ap-color-border); }`
    and `&.unread { box-shadow: inset 3px 0 0 var(--ap-color-primary); }`.
  - `.content-item-container .notification-message { font-size: var(--ap-text-sm); }`
  - The timestamp, on the plain, `:hover` and `.selected` variants of the container:
    `font-size: var(--ap-text-xs); line-height: var(--ap-leading-normal); color: var(--ap-color-text-muted);`
  - `.content-item-container .view-more { color: var(--ap-color-primary); }`
  - `.notification-area .empty-text { color: var(--ap-color-text-muted); }`
  - `@media (max-width: 590px) { .notification-area { border-radius: var(--ap-radius-md); } }`
- Do not touch the fixed heights or the 979 px slide logic.
- Run stylelint on `_surface-*.scss`.
- Visual: vp_learner1 and vp_public77 at 1440 and 390, the list/detail toggle at 390, and dark mode once.

### G3: theme dashboard (layout/dashboard.php and templates; line numbers as of now)

**DASH-QUICKNAV.** DO, low risk, P2, 10 min.
- Delete layout/dashboard.php lines 516-579: from `// Quick navigation links with tenant-scoped live stats.`
  through `$airpay_dashboard['hasquicknav'] = true;`.
- Put a one-line comment in its place: "Quick navigation removed 2026-11-18: the template stopped printing it when
  the sidebar took over, but its tiles were still counted on every admin /my/ load".
- Delete `$airpay_dashboard['hasquicknav'] = false;` (line 666).
- Keep `$scope`, `$totalusers`, `$activeusers` and all lang strings. Verified: only comments mention quicknav in
  templates.

**DASH-THROWABLE.** DO, low risk, P2, 15 min. Do it after QUICKNAV.
- At the remaining 8 sites, the original lines 255, 663, 747, 757, 848, 915, 946 and 1011, change
  `catch (\Exception $e)` or `catch (Exception $e)` to `catch (\Throwable $e)`.
- The first statement in each catch becomes `debugging('Dashboard <section> skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);`.
  Sections, in order: gamification, admin widgets, learner courses, certificate count, team overview, deadlines,
  achievements, activity timeline.
- Keep the existing flag resets.
- New tests/dashboard_layout_source_test.php (source-pinning, CRLF-normalised):
  - no `catch\s*\(\s*\\?Exception\b` remains;
  - `catch (\Throwable` appears at least 10 times;
  - `admin_quicknav` and `hasquicknav` are absent from dashboard.php and from dashboard.mustache (comments
    stripped).

**B4-TEAMTABLE (new).** DO, low risk, P2, 5 min.
- dashboard.mustache: wrap the `<table class="generaltable airpay-dash__team-table">…</table>` (it opens at
  line 586) in `<div class="airpay-dash__team-table-wrapper">…</div>`.
- custom_media.scss:720-727 already gives the wrapper `overflow-x: auto` and the table `min-width: 600px` at
  590 px.
- Visual: vp_manager1 /my/ at 390: the table scrolls inside the card and the page does not scroll sideways.

**LB-CAPTION.** DO, low risk, P2, 2 min.
- dashboard.mustache line 712: `dash_your_department` → `dash_your_organisation` (an existing en and hi string).
  The board is tenant-wide.

**V20-bell, dashboard wiring.** DO, low risk, P2, 15 min.
- layout/dashboard.php, at the topbar context (1177-1190): set the existing key
  `'notificationhtml' => method_exists(\theme_sentientia\output\core_renderer::class, 'topbar_notifications_link') ? \theme_sentientia\output\core_renderer::topbar_notifications_link() : ''`,
  and update the comment.
- dashboard.mustache line 187: replace the hard-coded bell with `{{{ topbar.notificationhtml }}}`, and update the
  comment at 183-186.
- dashboard_chrome_template_test.php (77-106):
  - `{{{ topbar.notificationhtml }}}` appears once in `.ap-topbar__right`;
  - `fa fa-bell` and the notifications URL no longer appear in the template;
  - the renderer source still holds `/message/output/popup/notifications.php`;
  - extend the layout test to assert `core_renderer::topbar_notifications_link()` and the `notificationhtml` key.

**V5-DASH.** DO, low risk, P2, 25 min.
- dashboard.php: delete `'shortname' => format_string($course->shortname),` at lines 698 and 711.
- After line 722, add:
  ```php
  foreach ($airpay_dashboard['continuecourses'] as &$card) {
      $card['thumburl'] = '';
      try {
          $img = \core_course\external\course_summary_exporter::get_course_image($enrolledcourses[$card['id']]);
          $card['thumburl'] = $img ?: '';
      } catch (\Throwable $e) {
          debugging('Dashboard course image skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
      }
  }
  unset($card);
  ```
- components/course_progress_card.mustache:
  - Line 41: `style="background-image: url('{{thumburl}}'), var(--ap-gradient-cta);"`.
  - Line 43: `{{#shortname}}{{shortname}}{{/shortname}}{{^shortname}}<i class="fa fa-graduation-cap fa-2x" aria-hidden="true"></i>{{/shortname}}`.
  - Doc comment (12-14): shortname is optional.
- Test: render the partial `['viewurl'=>'#','fullname'=>'X','progress'=>0]`: it contains `fa-graduation-cap` and
  not `>X</span>` in the thumb. With `thumburl` set, it contains `var(--ap-gradient-cta)`.
- Visual: vp_learner1 /my/ at 1440 and 390: no "VP_JOURNEY_FREE" or "API001".

**V16. Trainer and author dashboards: taught courses grouped separately.** DO, medium risk, P1 (Arjun and Sneha
on UAT are enrolled only as editingteacher), 75 min. No flag; see D3.
- **New `theme_sentientia\local\teaching_courses`** (final class, GPL header, MOODLE_INTERNAL), with one method:
  `public static function course_ids(int $userid, array $courseids): array`.
  - Return `[]` for empty input.
  - `[$insql, $params] = $DB->get_in_or_equal(array_map('intval', $courseids), SQL_PARAMS_NAMED, 'tc');`
  - Run `get_recordset_sql("SELECT ra.id, ctx.instanceid AS courseid, r.archetype FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :courselevel JOIN {role} r ON r.id = ra.roleid WHERE ra.userid = :uid AND ctx.instanceid $insql", $params + ['uid' => $userid, 'courselevel' => CONTEXT_COURSE])`.
  - Teach = archetype `editingteacher` or `teacher`. Learn = `student`. Close the recordset.
  - Return `array_map('intval', array_keys(array_diff_key($teach, $learn)))`.
  - Course-context roles only. Capabilities cannot be used: the `trainer` role at system has grade:viewall
    everywhere, and UAT trainers hold `employee` at system.
- **dashboard.php, learner branch:**
  - After line 676:
    ```php
    $teachingids = class_exists('\\theme_sentientia\\local\\teaching_courses') ? \theme_sentientia\local\teaching_courses::course_ids((int)$USER->id, array_keys($enrolledcourses)) : [];
    $learningcourses = $enrolledcourses;
    $teachingcourses = [];
    foreach ($teachingids as $tid) {
        if (count($teachingcourses) < 6) {
            $teachingcourses[] = ['name' => format_string($enrolledcourses[$tid]->fullname), 'url' => (new moodle_url('/course/view.php', ['id' => $tid]))->out(false)];
        }
        unset($learningcourses[$tid]);
    }
    ```
  - Line 682: loop over `$learningcourses`.
  - Line 725: `'enrolled' => count($learningcourses)`. Line 733: `$totalenrolled = count($learningcourses);`.
  - After line 745:
    - `$teachingonly = empty($learningcourses) && !empty($teachingcourses);`
    - `$airpay_dashboard['has_progress_ring'] = !$teachingonly;`
    - `$airpay_dashboard['teachingcourses'] = $teachingcourses;`
    - `$airpay_dashboard['hasteachingcourses'] = !empty($teachingcourses);`
  - Line 802: `$airpay_dashboard['haslearnerkpis'] = !empty($airpay_dashboard['hasstats']) && empty($teachingonly);`.
  - Line 858: `$enrolledids = array_keys($learningcourses ?? ($enrolledcourses ?? []));`.
  - Leave line 1048 (recommendations) on `$enrolledcourses`.
  - After line 852: `$airpay_dashboard['showmanagerheader'] = $ismanager && !empty($airpay_dashboard['hasmanagerkpis']);`.
- **dashboard.mustache:**
  - Lines 265-272:
    - `{{#ismanager}}{{^isadmin}}…{{/isadmin}}{{/ismanager}}` → `{{#showmanagerheader}}…{{/showmanagerheader}}`;
    - `{{^isadmin}}{{^ismanager}}…{{/ismanager}}{{/isadmin}}` → `{{^isadmin}}{{^showmanagerheader}}…{{/showmanagerheader}}{{/isadmin}}`.
  - The ring is already gated: `{{#has_progress_ring}}` at template line 633, set to true at dashboard.php:745.
    The override after line 745 is enough, and the template needs no change for the ring.
  - Insert inside `{{#islearner}}`, before the Continue Learning section (line 734):
    ```mustache
    {{#hasteachingcourses}}<div class="airpay-dash__section"><h3 class="airpay-dash__section-title"><i class="fa fa-book" aria-hidden="true"></i> {{#str}}dash_sec_teaching, theme_sentientia{{/str}}</h3><div class="airpay-dash__top-courses">{{#teachingcourses}}<a href="{{{url}}}" class="airpay-dash__top-courses__row"><span class="airpay-dash__top-courses__name">{{{name}}}</span><span class="airpay-dash__top-courses__stats"><i class="fa fa-arrow-right" aria-hidden="true"></i></span></a>{{/teachingcourses}}</div></div>{{/hasteachingcourses}}
    ```
- **String (G9):** `dash_sec_teaching`.
- **Tests:** new tests/teaching_courses_test.php. Expected results:
  - editingteacher → returned;
  - student → not returned;
  - teacher and student in the same course → not returned;
  - `teacher` at system plus a student enrolment → not returned;
  - a custom role with archetype `teacher` in the course → returned;
  - `[]` → `[]`;
  - only ids from the passed list are returned.

  Plus template asserts in dashboard_layout_source_test.
- **Visual, at 1440 and 390:**
  - vp_author1 and vp_courseauthor1 /my/: "Courses you teach" lists 448, with no "Not started 0%" card for it and
    no "0 of 1" ring.
  - Regression: vp_learner1 and vp_manager1 are unchanged.

**LB-MASK. Only if D2 is answered "A".** 15 min. After line 227 of dashboard.php, add:
```php
// V9 (Nitin, 2026-11-19): first name and last initial on the dashboard mini-board.
if (!empty($leaderboard['entries']) && is_array($leaderboard['entries'])) {
    foreach ($leaderboard['entries'] as &$lbentry) {
        if (empty($lbentry['is_current_user']) && isset($lbentry['lastname']) && trim((string) $lbentry['lastname']) !== '') {
            $lbentry['lastname'] = \core_text::substr(trim((string) $lbentry['lastname']), 0, 1) . '.';
        }
    }
    unset($lbentry);
    $airpay_dashboard['leaderboard'] = $leaderboard;
}
```
The initials avatar is unchanged.

### G4A: profile (both trees, plus theme `_surface-user.scss`)

**V6-buttons.** DO, low risk, P1, 15 min.
- profile.mustache line 60: the class becomes `ap-profile__account-actions` (drop `d-flex flex-wrap gap-2 mt-3 mb-2`;
  BS 4.6 has no `gap-*`).
- `_surface-user.scss`, after line 157:
  ```scss
  .ap-profile__account-actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--ap-space-2); margin: 0 0 var(--ap-space-4); }
  .ap-profile__account-actions .btn { display: inline-flex; align-items: center; gap: var(--ap-space-2); margin: 0; min-height: 40px; padding: 8px 16px; font-size: var(--ap-text-sm); font-weight: 600; line-height: 1.2; border-radius: 8px; }
  @media (max-width: 590px) { .ap-profile__account-actions { justify-content: center; } }
  ```
- Do not edit `_surface-profile.scss`, which is not imported.

**V6-avatar.** DO, low risk, P2, 10 min. Same insert point:
```scss
.ap-profile__avatar .userinitials { width: 72px; height: 72px; margin: 0; font-size: 1.5rem; font-weight: 700; background: var(--ap-color-primary-light); color: var(--ap-color-primary); }
```

**V6-badges.** DO, low risk, P1, 35 min.
- user_manager.php: add `public static function gamification_labels(array $s): array`. It returns `$s` plus:
  - `badge_count_label`: `get_string(((int)$s['badge_count'] === 1) ? 'profile_badges_one' : 'profile_badges_many', 'local_sentientia_users', (int)$s['badge_count'])`;
  - `points_label`: `get_string('profile_points', …, $s['total_points'])`. total_points is already
    number_format'd;
  - `rank_label`: `get_string('profile_level_rank', …, (object)['level' => $s['level_name'], 'rank' => $s['rank']])`;
  - `streak_label`: `!empty($s['has_streak']) ? get_string('profile_streak_days', …, (int)$s['current_streak']) : get_string('profile_streak_start', …)`.
- At line 338, inside the existing try:
  `$context['ap_gamification'] = self::gamification_labels(local_sentientia_gamification_get_summary($userid));`.
- profile.mustache:
  - Line 83: `<strong>{{points_label}}</strong>`. Line 84: `<small>{{rank_label}}</small>`.
  - Line 92: `<strong>{{badge_count_label}}</strong>`. Line 93: `<small>{{streak_label}}</small>`.
  - Replace lines 110-119 with:
    ```mustache
    {{#ap_gamification}}{{#has_badges}}<section class="ap-profile__earned" aria-labelledby="ap-profile-earned-title"><h2 class="ap-profile__earned-title" id="ap-profile-earned-title">{{#str}}profile_badges_heading, local_sentientia_users{{/str}}</h2><ul class="ap-profile__earned-list">{{#badges}}<li class="ap-profile__earned-item" title="{{description}}"><span class="ap-profile__earned-icon"><i class="fa {{icon}}" aria-hidden="true"></i></span><span class="ap-profile__earned-name">{{name}}</span></li>{{/badges}}</ul></section>{{/has_badges}}{{/ap_gamification}}
    ```
- SCSS:
  ```scss
  .ap-profile__earned { margin: var(--ap-space-4) 0 var(--ap-space-6); }
  .ap-profile__earned-title { font-size: var(--ap-text-xs); font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ap-color-text-secondary); margin: 0 0 var(--ap-space-2); }
  .ap-profile__earned-list { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: var(--ap-space-2); }
  .ap-profile__earned-item { display: inline-flex; align-items: center; gap: var(--ap-space-2); padding: 4px 12px 4px 4px; border: 1px solid var(--ap-color-border); border-radius: 999px; background: var(--ap-color-bg-surface); color: var(--ap-color-text-primary); font-size: var(--ap-text-sm); font-weight: 600; }
  .ap-profile__earned-icon { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--ap-color-primary); color: var(--ap-color-text-inverse); font-size: 12px; }
  ```
- **New tests/profile_labels_test.php** (both trees):
  - badge_count 0, 1 and 3 give "0 badges", "1 badge" and "3 badges";
  - streak labels;
  - render the profile template with one badge `{name:'Team Player', icon:'fa-users'}`: the name and heading
    text appear outside a title attribute.

  Re-run profile_header_chip_test, profile_skills_names_plain_test and profile_loginas_test.

**V6-na.** DO, low risk, P2, 25 min.
- user_manager.php `build_profile_context()`, lines 231-250: the `'N/A'` fallbacks become `'—'` (U+2014) for
  empid, organisation, location, address, client, team, grade, hrmrole, zone, region, employment_type, phnumber
  and supervisorname.
- Line 241: `'designation' => $user->open_designation ?: ''`.
- Lines 253-254: `'joindate' => !empty($user->open_joindate) ? user_fields::format_date((int)$user->open_joindate) : '—'`,
  and the same for dateofbirth.
- Do not change `user_fields::format_date()`. Keep department and subdepartment as "All".
- profile.mustache, employee grid (136-146), fallback grid (214-221) and operator grid (204-207): replace the
  literal labels with `{{#str}}organisation|department|employeeid|location|phone, local_sentientia_users{{/str}}`
  (existing keys).
  - New keys: `subdepartment` and `joindate`.
  - Operator "Email" becomes `{{#str}}email{{/str}}`.
- Test, in profile_labels_test: a user with no HR fields gets `'—'` for empid, location, phnumber, supervisorname
  and joindate, and `''` for designation.

**Version:** local_sentientia_users `2026111800` → `2026111801`, release `2.9.4`, both trees.
- Visual: profile.php as vp_learner1, vp_admin1, vp_manager1 and vp_public77, at 1440 and 390.
- Also `?lang=hi`, and dark mode once.
- Check: before editing, use DevTools on local to find where the light-mode `.ap-profile__enrich-card` styles come
  from (the area planner found no repo source). If they come from a local-only file, report it.

### G4B: manager pages, authoring, sidebar

**V17-copy.** DO, low risk, P1, 15 min. The strings are in G9.
- studio.php, lines 189-199: set `$livemode = false;` before the badge chain, and `$livemode = true;` in the final
  `else` (the live-with-key branch).
- Lines 232-233: print the token line only `if ($livemode)`, with `number_format()` on both numbers.
- Line 341: Cancel goes to `new moodle_url('/local/sentientia_authoring/index.php')`.
- Keep the confirm checkbox and its server check.

**V17-model.** DO, low risk, P2, 10 min.
- After line 67: `$canchoosemodel = has_capability('moodle/site:config', $context);`.
- Line 98: `$prefill['model'] = $canchoosemodel ? trim(optional_param('model', $defaultmodel, PARAM_TEXT)) : $defaultmodel; if ($prefill['model'] === '') { $prefill['model'] = $defaultmodel; }`.
- Wrap the Model block (319-325) in `if ($canchoosemodel) { … }`.

**V17-drafts.** DO, low risk, P2, 25 min.
- sidebar_navigation.php lines 543-544:
  `$this->item(get_string('nav_authoringstudio', 'theme_sentientia'), 'fa-magic', '/local/sentientia_authoring/index.php', $currenturl, null, ['/local/sentientia_authoring/'])`.
- studio.php lines 227-230: two buttons, `html_writer::link(new moodle_url('/local/sentientia_authoring/index.php'), get_string('nav_drafts', 'local_sentientia_authoring'), ['class' => 'btn btn-outline-secondary btn-sm me-2'])`
  followed by the existing templates link.
- authoring index.php:
  - Line 39: the button label `nav_studio` → `nav_generate`.
  - Line 63: `$key = 'draft_status_' . $d->status; $label = get_string_manager()->string_exists($key, 'local_sentientia_authoring') ? get_string($key, 'local_sentientia_authoring') : $d->status;`,
    rendered as `html_writer::tag('span', s($label), ['class' => 'badge bg-secondary'])`.
  - The statuses are confirmed in draft_manager.php:37-42: pending, generated, approved, published, rejected,
    failed.
- If nav_chrome_test covers the authoring group, update the expected URL. No test references studio.php today.

**Authoring version:** `2026111600` → `2026111800`, release `0.3.2-alpha`, both trees.
- Visual: studio.php and index.php as vp_courseauthor1 and vp_author1 (and as admin for the model field), at 1440
  and 390, plus `?lang=hi`.

**V15-perf.** DO, low risk, P1, 25 min.
- performance.php:
  - Line 18: `set_pagelayout('standard')`.
  - Lines 19-20: `get_string('teamperformance', 'local_sentientia_manager')`.
  - Lines 35-37: `'label' => get_string('perioddays', 'local_sentientia_manager', 7|30|90)`.
- performance.mustache:
  - Line 5: add `flex-wrap`.
  - Line 6: `<h2 class="mb-2">{{#str}}teamperformance, local_sentientia_manager{{/str}}</h2>`.
  - Line 9: add `mb-1` to the pill.
  - Lines 58, 62, 67 and 71: `col-6 col-md-3 mb-2`.
  - In `{{#js}}`, add `function esc(v){var d=document.createElement('div');d.textContent=(v===null||v===undefined)?'':String(v);return d.innerHTML;}`.
  - Line 77: `'<div class="table-responsive"><table class="table table-hover mb-0">'`. Line 107: `'</tbody></table></div>'`.
  - Lines 88-89: `'<td><strong>' + esc(m.fullname) + '</strong><div class="small text-muted text-break">' + esc(m.email) + '</div></td>'`.
    This drops the pink `<code>`.
  - Line 90: `esc(m.designation || '—')`. Line 103: `esc(m.last_access)`.

**V15-member.** DO, low risk, P1, 25 min.
- member.php:
  - Lines 42-43: `get_string('teammember_title', 'local_sentientia_manager', fullname($user))`.
  - Line 46: `get_string('myteam', 'local_sentientia_manager')`.
  - Line 73: `get_string('never')`.
  - Line 78: `'email' => $user->email`. Drop `s()`: the template escapes it.
  - `'employeeid' => trim((string)($user->open_employeeid ?? '')) ?: '—'`.
  - `'designation' => format_string(trim((string)($user->open_designation ?? '')) ?: '—')`.
  - `'department' => format_string(trim((string)($user->department ?? '')) ?: '—')`.
  - Add `'first_letter' => core_text::strtoupper(core_text::substr(trim(fullname($user)), 0, 1))`.
- member.mustache:
  - In `<style>` after line 52: `[data-region="airpay-manager-member"] .ap-fact-grid > div { min-width:0; }` and
    `[data-region="airpay-manager-member"] .ap-fact-grid .value { overflow-wrap:anywhere; word-break:break-word; }`.
  - Line 98: `backtomyteam`. Line 111: `fullprofile`.
  - Lines 117-121: `label_email`, `label_employeeid`, `label_department`, `label_organisation`, `label_lastactive`.
  - Line 216: `<code>{{code}}</code>` → `<span class="text-monospace">{{code}}</span>`.

**V15-titles.** DO, low risk, P3, 10 min.
- manager index.php lines 18-19: `get_string('myteam', 'local_sentientia_manager')`. This replaces the hard-coded
  "My Team — Learning Dashboard"; the V3 visible title is the theme string.
- allocations.php lines 15-16: `get_string('courseallocations', ...)`.
- requests.php lines 17-18: `get_string('enrolmentrequests', ...)`.

**Manager version:** `2026111800` → `2026111801`, release `1.3.10`, both trees.
- Visual: vp_manager1 on performance.php, member.php?id=3430 and index.php, at 1440 and 390, plus `?lang=hi`.
- Run the team_manager_test suite centrally.

### G5: admin, certificates, CI gate (both trees, byte-identical)

**CI-ORG-STEP. MUST FIX: the CI gate is red.** DO, low risk, P0, 15 min.
- In both `local/sentientia_org/db/upgrade.php` (identical today), cut the violet-repaint block: from the comment
  `// ── 2026061700: Revised airpay Brand Book 2026-06 — repaint retired` (line 45) through the closing `}` after
  `upgrade_plugin_savepoint(true, 2026061700, 'local', 'sentientia_org');` (lines 85-86).
- Paste it after the closing `}` of the `if ($oldversion < 2026093002)` block (about line 98), directly before
  `return true;` (line 100).
  - It must be the **last** block: `upgrade_plugin_savepoint()` throws `downgrade_exception` when the stored
    version is already at or above the savepoint (moodle53 lib/upgradelib.php:427).
- In the moved block:
  - `if ($oldversion < 2026111800)` and `upgrade_plugin_savepoint(true, 2026111800, 'local', 'sentientia_org');`;
  - prepend to the header comment: "2026111800 (first written as 2026061700; renumbered 2026-11-18 so sites
    already past 2026061700, UAT at 2026101502 among them, run it once; idempotent: it rewrites only rows that
    still hold the retired violet)";
  - the body stays byte-identical.
- version.php (both trees): keep `2026111800`. In its comment, replace "(it does not re-run on sites already past
  2026061700)" with "(renumbered to 2026111800 so UAT, at 2026101502, runs it once; idempotent)". Leave the
  pre-existing double `$plugin->release` alone.
- **Verify:**
  - `php tools/check-upgrade-step-versions.php` gives 0 findings;
  - `php tools/check-upgrade-step-versions-selftest.php`;
  - `cmp` of the two upgrade.php files;
  - php -l.
- **UAT note for Nitin:** the step may repaint the Public tenant's brand colour from violet to #0066A7. That is
  intended. Locally it is a no-op (0 rows).

**V19-LP-AMP.** DO, low risk, P2, 10 min.
- list_paths.php line 104: `'name' => format_string($lp->name, true, ['escape' => false]),`. datatable.js escapes
  the cell itself, and the WS returns PARAM_TEXT.
- Leave the `data-name` attributes unchanged.

**V19-LP-STATUS.** DO, low risk, P2, 15 min.
- manage.mustache, lines 38-44:
  - label `{{#str}}status, local_sentientia_learningpath{{/str}}`;
  - buttons All (`all`, core `{{#str}}all{{/str}}`), Active (`1`, `status_active`) and Archived (`0`,
    `status_archived`);
  - **delete** the "Completed" button (`data-status="2"`).
- list_paths.php:
  - in rows, add `'statuslabel_class' => $cssmap[(int) $lp->status] ?? 'badge-secondary'`;
  - in `execute_returns`, add `'statuslabel_class' => new external_value(PARAM_ALPHANUMEXT, 'badge css class')`;
  - keep `statuscss`.
- Test: `list_paths_test::test_name_is_text_and_status_has_badge_class`:
  - seed "Products & Features" (status 1) and "Old & archived" (status 0);
  - the names come back with a single `&`;
  - the classes are `badge-success` and `badge-secondary`.
- **Version** learningpath: `2026110300` → `2026111800`, release `1.9.3`, both trees.

**V19-TABLE-HEADER (PHP route, no AMD build).** DO, low risk, P3, 25 min.
- Before editing a page, check two things:
  - (a) the page does not already pass row-actions HTML (`grep -n "row_actions\|rowactions\|data-row-actions"` in
    the page and its template);
  - (b) its WS rows carry `'actions'` (`grep -n "'actions'"` in the plugin's `classes/external/`).

  Skip any page that fails either check.
- In each passing `index.php` of users, courses, classroom, learningpath and programs (both trees), append to
  `$columns`, as the last entry:
  `['key' => 'actions', 'label' => get_string('actions'), 'sortable' => false, 'format' => 'html'],`
- Add the comment "Surface walk 2026-11-18 V19: the rows carry 'actions'; declaring the column gives it a header
  cell (theme datatable.js WF-023 path), so the blue header spans the table".
- No version bump (page-only PHP).
- Visual: the 5 pages as vp_admin1 at 1440: the header spans the table, the icons sit under "Actions", sort still
  works, edit and delete still open their dialogs (learning paths), and the console shows 0 errors.

**V19-CERT-TEMPLATES.** DO, low risk, P3, 20 min.
- certificate_templates.php, SQL at lines 93-100:
  ```sql
  SELECT t.id, t.name,
         (SELECT COUNT(1) FROM {tool_certificate_issues} i WHERE i.templateid = t.id AND i.archived = 0) AS issued,
         (SELECT COUNT(1) FROM {tool_certificate_pages} p JOIN {tool_certificate_elements} e ON e.pageid = p.id WHERE p.templateid = t.id) AS elementcount
    FROM {tool_certificate_templates} t
   ORDER BY t.name ASC, t.id ASC
  ```
  There is no GROUP BY.
- Name cell (line 157): `format_string($t->name) . ' ' . html_writer::span(s(get_string('cert_tpl_id', 'local_sentientia_pages', (int) $t->id)), 'text-muted small')`.
  When `(int) $t->elementcount === 0`, add `' ' . html_writer::span(s(get_string('cert_tpl_empty', 'local_sentientia_pages')), 'badge bg-warning text-dark')`.
- The banner logic (109-114) is unchanged; only its string changes (G9).
- **Version** pages: `2026111800` → `2026111801`, release `1.2.2`, both trees. Comment: "certificate templates
  page: plain-language banner, ID and empty-template badge".
- Visual: vp_admin1 at 1440 and 390: no flag key in the banner, "ID n" on each row, and template 1 shows the amber
  "Empty template" badge.

**V23. Browse Airpay Library: search plus 25 rows a page.** DO, low risk, P3, 40 min. Uses core strings only,
because the courses lang files drift between trees.
- browse_airpay.php:
  1. After the POST handler: `$search = optional_param('q', '', PARAM_TEXT); $page = optional_param('page', 0, PARAM_INT); $perpage = 25;`.
     Read `q` and `page` inside the POST handler too, and redirect to
     `new moodle_url('/local/sentientia_courses/browse_airpay.php', array_filter(['q' => $search, 'page' => $page]))`.
  2. Replace lines 68-80 with:
     ```php
     [$pathsql, $params] = \local_sentientia_platform\tenant::path_descendant_filter('/1', 'c', 'open_path', 'airpay');
     $where = "c.visible = 1 AND c.id > 1 AND $pathsql";
     if ($search !== '') {
         $where .= ' AND (' . $DB->sql_like('c.fullname', ':q1', false) . ' OR ' . $DB->sql_like('c.shortname', ':q2', false) . ')';
         $params['q1'] = $params['q2'] = '%' . $DB->sql_like_escape($search) . '%';
     }
     $total = $DB->count_records_sql("SELECT COUNT(1) FROM {course} c WHERE $where", $params);
     $courses = $DB->get_records_sql("SELECT c.id, c.fullname, c.shortname, c.summary, c.timecreated, c.open_path, cc.name AS categoryname FROM {course} c JOIN {course_categories} cc ON cc.id = c.category WHERE $where ORDER BY c.timecreated DESC, c.id DESC", $params, $page * $perpage, $perpage);
     ```
     Update the "We don't paginate" comment.
  3. Line 142: `$baseurl = new moodle_url('/local/sentientia_courses/browse_airpay.php', $search !== '' ? ['q' => $search] : []); $PAGE->set_url($baseurl);`.
  4. Add to `$data`:
     - `'search' => $search`;
     - `'has_search' => ($search !== '')`;
     - `'searchurl' => (new moodle_url('/local/sentientia_courses/browse_airpay.php'))->out(false)`;
     - `'page' => $page`;
     - when a search found nothing: `'empty_text' => get_string('nocoursesfound', 'moodle', s($search))`.

     After `echo $OUTPUT->header();`, add `$data['pagingbar'] = $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);`.
- Template:
  - After line 29, add a GET search form (`role="search"`) with:
    - a label `sr-only visually-hidden` with `{{#str}}searchcourses, moodle{{/str}}`;
    - `<input type="search" name="q" value="{{search}}" class="form-control" style="max-width:360px">`;
    - a submit button with `{{#str}}search, moodle{{/str}}`;
    - `{{#has_search}}<a href="{{{searchurl}}}" class="btn btn-outline-secondary">{{#str}}clear, moodle{{/str}}</a>{{/has_search}}`.
  - Add hidden `q` and `page` inputs to the POST form.
  - Add `{{{pagingbar}}}` after the card (line 75).
  - Update the context documentation.
- **Version** courses: `2026111600` → `2026111800`, release `1.14.3`, both trees.
- Visual: vp_admin177 at 1440 and 390:
  - 25 rows and a paging bar;
  - `?q=posh` filters, `?q=zzzz` shows the empty text;
  - requesting on page 2 returns to page 2;
  - once with `?lang=sw`.

**CAT-8b. Request plugin comments.** DO, P4, 10 min. Both trees; comments and docs only; no bump.
- catalog_cta.php line 16: "which the theme then redirected to /my."
- Lines 29-35 become: "Self-enrolment is deliberately NOT a way in here (fix 2026-10-15, review of 7a).
  theme/sentientia never renders /enrol/index.php: core_renderer::custom_secured_redirection() sends it to the
  catalogue's course page when the viewer may open that course, and to /my otherwise (persona walk 2026-11-18;
  before that, always /my). The routes before a request are the catalogue's own: a paid course goes to the cart,
  and a free course offered one-click enrolment is enrolled at once. Count self-enrolment as a way in again only
  once a reachable self-enrol route exists behind its own flag (a course.php self-enrol button was tried and
  withdrawn on 2026-11-18: no flag)."
- Request README lines 78-82: the same correction as the catalog README.

### G6: compliance and analytics (both trees, byte-identical)

**KPI-CACHE. The KPI cache is never invalidated (ad-hoc definition, no TTL).** DO, low risk, P2, 30 min.
- compliance_engine.php, after the STATUS constants (line 24):
  ```php
  public const KPI_CACHE_AREA = 'dashboard';
  public static function kpi_cache(): \cache { return \cache::make_from_params(\cache_store::MODE_APPLICATION, 'local_sentientia_compliance_report', self::KPI_CACHE_AREA); }
  public static function purge_kpi_cache(): void { try { self::kpi_cache()->purge(); } catch (\Throwable $e) { debugging('Compliance KPI cache purge failed: ' . $e->getMessage(), DEBUG_DEVELOPER); } }
  ```
  Docblock: index.php and theme_sentientia\local\dashboard_compliance read and write this cache, keyed by
  `viewer_scope::kpi_cache_key()`.
- Call `self::purge_kpi_cache()`:
  - in `rebuild_snapshot()` before **both** returns (lines 42 and 182);
  - in `add_compliance_course()` after insert_record;
  - in `remove_compliance_course()` after the two set_field calls;
  - in `exclude_user()` after insert_record;
  - in `include_user()` after set_field.
- index.php:153 becomes `$cache = $engine::kpi_cache();`. Remove the comment at 160 ("TTL managed by Moodle cache
  definition").
- Theme dashboard_compliance.php, docblock lines 23-25 only: the cached row is served until the snapshot task or
  a Configure-tab change purges it.
- **Test:** `test_kpi_cache_is_purged_by_the_snapshot_and_by_configuration`:
  - set a key, call `rebuild_snapshot()` with no mandatory courses: the key is gone;
  - repeat for add, remove, exclude and include.

**V12. The funnel's Certified bar exceeds Completed.** DO, low risk, P2, 25 min.
- analytics_manager.php, lines 199-201, the certified query becomes:
  `SELECT COUNT(DISTINCT ci.userid) FROM {tool_certificate_issues} ci JOIN {user} u ON u.id = ci.userid WHERE u.deleted = 0 $orgfilter AND EXISTS (SELECT 1 FROM {course_completions} cc WHERE cc.userid = ci.userid AND cc.timecompleted IS NOT NULL)`.
  Add a comment: imported certificates carry courseid 0.
- Line 170: the cache key `'funnel_v2_'` → `'funnel_v3_'`.
- Docblock line 161: "Enrolled → Started → Completed → Certified (certified = holds a certificate AND completed at
  least one course)".
- **Version** analytics: `2026111800` → `2026111801`, release `1.2.6-beta`, both trees.
- **Test:** `test_funnel_certified_never_exceeds_completed`:
  - skip if `tool_certificate_issues` is absent;
  - purge the `funnel` definition;
  - give u1 and u2 a certificate each, and only u2 a completion;
  - certified is 1 and is not above completed.

  Fill NOT NULL columns via `$DB->get_columns()`.
- Visual: vp_admin1 /local/sentientia_analytics/index.php: the bars descend (local /1: Completed 1,504, Certified
  1,473).

**V11-EXPORT. Export Department shows the raw org path; rate differs from the page.** DO, low risk, P3, 30 min.
- compliance_engine.php:
  - `department_names()` (line 532) returns the raw name. Update its docblock (492-500).
  - In the matrix row (446-457), compute `$deptraw = (string) ($deptnames[(string) ($user->open_path ?? '')] ?? '');` and set:
    - `'department_name' => $deptraw === '' ? '' : format_string($deptraw)` (the page output is unchanged);
    - `'department_name_plain' => $deptraw === '' ? '' : format_string($deptraw, true, ['escape' => false])`;
    - `'fullname_plain' => format_string($user->firstname . ' ' . $user->lastname, true, ['escape' => false])`;
    - `'designation_plain' => format_string($user->open_designation ?? '', true, ['escape' => false])`.
  - Keep `'department'`.
- export.php:
  - Lines 72-74 and 126-128 write the three `_plain` fields.
  - After line 46: `$rate = ((int) ($kpis['total'] ?? 0)) > 0 ? (int) round(((int) $kpis['completed']) / ((int) $kpis['total']) * 100) : 0;`.
  - Lines 90 and 154 write `$rate . '%'`.
- **Test:** extend `test_matrix_department_name_is_the_org_unit` with assertions on `department_name_plain`
  ("Finance & Accounts" and `''`) and on `designation_plain`.
- **Version** compliance_report: `2026111800` → `2026111801`, release `1.0.10`, both trees. Comment: "KPI cache
  purged by snapshot rebuild and configuration changes; export Department is the org-unit name and the rate
  matches the page". This bump also covers G7's viewer_scope change if G7 runs.

**V25-dup. An anonymous evaluation can be answered twice.** DO, low risk, P4 (drop first), 35 min.
- `evaluation_manager::has_user_responded()` (1123-1137):
  ```php
  $eval = self::get($evaluationid);
  if (!$eval || $userid <= 0) { return false; }
  if ((int)($eval->multiple_submit ?? 0) === 1) { return false; }
  if ((int)$eval->anonymous === 1) {
      return $DB->record_exists(self::ASSIGN_TABLE, ['evaluationid' => $evaluationid, 'userid' => $userid, 'status' => 'responded']);
  }
  return $DB->record_exists_select(self::RESPONSES_TABLE, 'evaluationid = :eid AND userid = :uid AND timesubmitted > 0', ['eid' => $evaluationid, 'uid' => $userid]);
  ```
- In `submit_response()`, line 1170 becomes `if ($userid > 0) {`.
- Rewrite the docblock and document the limit: an anonymous form with no assignment row has no per-user marker.
- New tests/anonymous_resubmit_test.php with 4 cases:
  - the second submit throws `alreadyresponded`;
  - `multiple_submit` allows it;
  - with no assignment row, `has_user_responded()` is false;
  - userid 0 gives false.
- **Version** evaluation: `2026101900` → `2026111800`, release `1.17.5`, both trees.

### G7: B6 option 1 (conditional on D1; runs after G4B)

**B6-OPT1. `moodle/site:viewreports` no longer grants the tenant matrix or the manager shell.** DECISION →
DO once D1 is confirmed. Medium risk, 75 min.
1. **viewer_scope.php** (both trees):
   - Delete lines 135-137, the `if (has_capability('moodle/site:viewreports', …)) { return self::LEVEL_TENANT; }`
     check.
   - Leave the comment "B6 (Nitin, 2026-11-18): moodle/site:viewreports no longer admits to the tenant matrix:
     core grants it to every teacher-archetype role, so every trainer saw every employee."
   - Rewrite the class docblock (24-33): tenant = local/courses:manage, the org-admin role at a category, or the
     :export holder.
2. **role_detector.php**, lines 153-174:
   - remove `$ismanager = has_capability('moodle/site:viewreports', …);` and unwrap the `if (!$ismanager) {` block,
     so `$ismanager` comes from active direct reports only;
   - comment: "Manager: active direct reports only (B6, 2026-11-18; was also moodle/site:viewreports)";
   - fix the docblock at 55-58.
3. **sidebar_navigation.php** `detect_roles()`, lines 693-700: inside the existing
   `if (!$this->issiteadmin && !$this->isldadmin && !$this->ismanager)`, add `global $USER;` and:
   ```php
   try {
       $this->iscomplianceuser = class_exists('\local_sentientia_compliance_report\viewer_scope')
           && \local_sentientia_compliance_report\viewer_scope::level_for($USER)
               === \local_sentientia_compliance_report\viewer_scope::LEVEL_TENANT;
   } catch (\Throwable $e) {
       $this->iscomplianceuser = false;
   }
   ```
   Update the comments that mention viewreports (about lines 62-69, 204 and 346-351).
4. **blocks/sentientia_compliance/classes/audit.php** (both trees), lines 61-64: replace
   `|| has_capability('moodle/site:viewreports', $sys, $userid)` with:
   ```php
   || (class_exists('\local_sentientia_compliance_report\viewer_scope')
       ? \local_sentientia_compliance_report\viewer_scope::level_for($user) === \local_sentientia_compliance_report\viewer_scope::LEVEL_TENANT
       : has_capability('moodle/site:viewreports', $sys, $userid))
   ```
   `$user` is the method argument. Update docblock lines 21-30.
5. **Tests:**
   - team_scope_test.php (both trees):
     - 218-227 becomes `test_a_report_viewer_without_reports_is_refused`: `level_for` is null, `for_user` is
       null, and the refusal is `REFUSED_NO_ACCESS`;
     - 266-270: L2 with viewreports gets `LEVEL_TEAM` and its own tree. Then grant
       `permission::EXPORT_CAPABILITY` at system: `LEVEL_TENANT`;
     - **300-311**: `test_a_tenant_viewer_with_no_tenant_is_refused` grants `permission::EXPORT_CAPABILITY`
       instead of viewreports. The area plan missed this test; without the change it breaks;
     - update the docblock at 10-12.
   - role_detector_test.php 149-171 becomes `test_viewreports_alone_is_not_a_manager`.
   - dashboard_compliance_test.php line 126: `tenant_viewer()` grants
     `local/sentientia_compliance_report:export`.
   - audit_test.php (both trees): add an assertion that a viewreports-only role at /1 gets null or the team scope.
6. **Version:** block_sentientia_compliance `2026100800` → `2026111800`, release `1.0.2-beta`, both trees.
   compliance_report is covered by G6's bump. G9 adds "trainer compliance scope (B6)" to the theme changelog.
- **UAT effect, to tell Nitin:**
  - Arjun loses Compliance, My Team and the "Team overview" subtitle.
  - **Joseph** (complianceofficer: viewreports + :export) keeps the tenant report, through the sidebar
    Compliance link. But his shell and /my/ become the learner ones. The demo stop "Compliance: Joseph" must
    start from the sidebar link.
  - Meera and Juma are unchanged (category admin role). Vikram is unchanged (direct reports).
- **Visual:**
  - vp_author1 at 1440 and 390: no Compliance or My Team; the compliance URL shows "no access".
  - vp_compliance1: the report still opens.
  - vp_manager1: unchanged.
- **Stop-gap if D1 is "not tonight" (option 2, data, Nitin's go on UAT):**
  - untick "View reports" on the UAT `trainer` role (Site admin > Users > Define roles > Trainer), then purge;
  - first check that the role carries it;
  - reverse it with `assign_capability(..., CAP_ALLOW, ...)`.

### G9: lang and theme version (final, sequential)

1. Add or change the strings in section 4 in **both trees**, byte-identical. Place each next to its block, as the
   specs say.
2. Bump theme/sentientia/version.php from `2026111801` to **`2026111802`**, release **`1.0.70-beta`**.
   - Prepend a comment in the house format, ending with "Was: // 2026-11-18 (surface-walk fixes)…". It covers:
     - shell pages: visible titles on 5 pages, footer on admin-layout pages, bell unread badge, notifications
       page styling;
     - footer: white footer logo, FAB clearance at phone width, breadcrumb crumbs at phone width;
     - dashboard: taught courses grouped apart, course-card icon art, team table scroller, quick-nav dead code
       removed, Throwable catches, leaderboard caption ("Your Organization");
     - the sidebar Authoring item opens the drafts list;
     - profile buttons, avatar and badge row;
     - plus B6 and LB-MASK if they ran.
   - Release note: "1.0.70: Phase C surface-walk fixes".
3. Run `php tools/check-lang-parity.php`. It must exit 0.

---

## 4. String table (G9: en and hi; both trees for plugins)

**local_sentientia_catalog.** Put the CAT-1 strings after `enrolment_managed` (en about line 73, hi about line 70).
Reword that block's comment to: "where nothing on the page can enrol the learner, it says so, worded for an
employee or for a storefront consumer".

| key | en | hi |
|---|---|---|
| enrolment_managed_public | This course is not open for online enrolment yet. | यह कोर्स अभी ऑनलाइन नामांकन के लिए खुला नहीं है। |
| enrolment_suspended_public | Your access to this course is on hold. Please contact us for help. | इस कोर्स तक आपकी पहुँच अभी रोकी गई है। सहायता के लिए कृपया हमसे संपर्क करें। |
| enrolment_ended_public | Your access to this course ended on {$a}. | इस कोर्स तक आपकी पहुँच {$a} को समाप्त हो गई। |
| course_addedtocart_short | Added to cart. | कार्ट में जोड़ा गया। |
| course_addedtocart | {$a} has been added to your cart. | {$a} आपके कार्ट में जोड़ा गया। |
| course_viewcart | View cart ({$a}) | कार्ट देखें ({$a}) |
| course_enrolledcount | {$a} enrolled | {$a} नामांकित |
| course_selfpaced | Self-paced | अपनी गति से |
| course_onetime | one-time | एकमुश्त |
| course_addtocart_price | Add to cart — {$a} | कार्ट में जोड़ें — {$a} |
| course_login | Log in to access | एक्सेस के लिए लॉग इन करें |
| course_about | About this course | इस कोर्स के बारे में |
| course_backtocatalog | Back to the course catalog | कोर्स कैटलॉग पर वापस जाएँ |
| mycourses_filter_label | Filter your courses | अपने कोर्स फ़िल्टर करें |
| mycourses_all | All courses | सभी कोर्स |
| mycourses_inprogress | In progress | प्रगति में |
| mycourses_completed | Completed | पूरे हुए |
| mycourses_notstarted | Not started | शुरू नहीं हुए |
| mycourses_done | Done | पूरा |
| mycourses_percentcomplete | {$a}% complete | {$a}% पूरा |
| mycourses_review | Review | दोबारा देखें |
| mycourses_continue | Continue | जारी रखें |
| mycourses_start | Start | शुरू करें |
| mycourses_pagination | My courses pages | मेरे कोर्स के पृष्ठ |
| mycourses_showing | Showing {$a->from}–{$a->to} of {$a->total} | {$a->total} में से {$a->from}–{$a->to} दिखाए जा रहे हैं |
| mycourses_empty_title | No courses found | कोई कोर्स नहीं मिला |
| mycourses_empty_message | Browse the catalog to discover courses. | कोर्स खोजने के लिए कैटलॉग देखें। |
| mycourses_browse | Browse the catalog | कैटलॉग देखें |

**theme_sentientia** (next to `dash_sec_my_team`; en line 439, hi line 363)

| key | en | hi |
|---|---|---|
| dash_sec_teaching | Courses you teach | आप जो कोर्स पढ़ाते हैं |

**local_sentientia_users** (after `profile_weekly_goal_value`; en about line 289, hi about line 276)

| key | en | hi |
|---|---|---|
| profile_points | {$a} pts | {$a} अंक |
| profile_level_rank | {$a->level} · Rank #{$a->rank} | {$a->level} · रैंक #{$a->rank} |
| profile_badges_one | {$a} badge | {$a} बैज |
| profile_badges_many | {$a} badges | {$a} बैज |
| profile_streak_days | {$a}-day streak | {$a} दिन की स्ट्रीक |
| profile_streak_start | Start a streak! | स्ट्रीक शुरू करें! |
| profile_badges_heading | Badges earned | अर्जित बैज |
| subdepartment | Sub-department | उप-विभाग |
| joindate | Join date | जॉइनिंग तिथि |

**local_sentientia_manager**

| key | en | hi |
|---|---|---|
| teamperformance | Team performance | टीम प्रदर्शन |
| perioddays | {$a} days | {$a} दिन |
| teammember_title | Team member: {$a} | टीम सदस्य: {$a} |
| backtomyteam | Back to My Team | मेरी टीम पर वापस जाएँ |
| fullprofile | Full profile | पूरी प्रोफ़ाइल |
| label_email | Email | ईमेल |
| label_employeeid | Employee ID | कर्मचारी आईडी |
| label_department | Department | विभाग |
| label_organisation | Organisation | संगठन |
| label_lastactive | Last active | अंतिम सक्रियता |
| enrolmentrequests | Enrolment requests | नामांकन अनुरोध |
| courseallocations | Course allocations | कोर्स आवंटन |

**local_sentientia_authoring.** CHANGED: en and hi for the first four keys, hi only for `studio_form_model`.
Leave kn, mr and sw as they are.

| key | en | hi |
|---|---|---|
| mode_mock_badge (changed) | Preview mode: the studio creates sample content so you can try the full workflow. No AI service is called and there is no cost. | पूर्वावलोकन मोड: स्टूडियो नमूना सामग्री बनाता है ताकि आप पूरा कार्यप्रवाह आज़मा सकें। कोई AI सेवा कॉल नहीं होती और कोई लागत नहीं आती। |
| studio_confirm_label (changed) | I confirm I want to generate this module. | मैं पुष्टि करता/करती हूँ कि मैं यह मॉड्यूल जनरेट करना चाहता/चाहती हूँ। |
| studio_confirm_help (changed) | In live mode each generation uses paid AI capacity, so every run asks for this confirmation, in preview mode too. | लाइव मोड में हर जनरेशन सशुल्क AI क्षमता का उपयोग करता है, इसलिए हर बार यह पुष्टि माँगी जाती है, पूर्वावलोकन मोड में भी। |
| tokens_used_today (changed) | AI usage today: {$a->used} of {$a->cap} tokens. | आज AI उपयोग: {$a->cap} में से {$a->used} टोकन। |
| studio_form_model (hi only) | (Model, unchanged) | AI मॉडल |
| nav_drafts | My drafts | मेरे ड्राफ़्ट |
| nav_generate | Generate a module | मॉड्यूल जनरेट करें |
| draft_status_pending | Generating | जनरेट हो रहा है |
| draft_status_generated | Ready for review | समीक्षा के लिए तैयार |
| draft_status_approved | Approved | स्वीकृत |
| draft_status_published | Published | प्रकाशित |
| draft_status_rejected | Rejected | अस्वीकृत |
| draft_status_failed | Failed | विफल |

**local_sentientia_pages**

| key | en | hi |
|---|---|---|
| cert_templates_title (changed) | Certificate templates | प्रमाणपत्र टेम्पलेट |
| cert_scope_off_notice (changed) | Every administrator sees all certificate templates. Filtering by tenant is not switched on. | हर व्यवस्थापक को सभी प्रमाणपत्र टेम्पलेट दिखाई देते हैं। टेनेंट के अनुसार फ़िल्टर करना चालू नहीं है। |
| cert_tpl_id | ID {$a} | आईडी {$a} |
| cert_tpl_empty | Empty template | खाली टेम्पलेट |

Plus any extra key a code group reports in its return.

---

## 5. Sequence and timeline (night of 18-19 Nov)

**Precondition.** Phase B's XAMPP copy has finished. If Phase B's verifier is still fixing files, it must say
which files, so the owning Phase C group re-reads them before editing.

| Time | Step |
|---|---|
| 21:50 | Plan published. Ask Nitin D1-D3 (section 6). Unanswered means: G7 does not run unless the lead confirms D1 from his chat; LB-MASK does not run; V16 ships with no flag. |
| **22:00** | **Wave 1, at once (file-disjoint):** G1A (CAT-1 first), G5 (CI-ORG-STEP first), G2, G3, G6, G4B. |
| 22:00 or the first free slot | **Wave 2:** G1B and G4A. They are small. If the harness allows 8 concurrent agents, start them at 22:00 as well. |
| about 00:30 (after G4B) | **G7**, only if D1 is confirmed. |
| **01:45** | Hard stop for every code group. Each group returns done, skipped and extra string keys. |
| 01:45-02:30 | **G9**: strings, the theme bump and the lang parity check. |
| 02:30-03:00 | **Static gates**, all must pass (list below). |
| 03:00-03:30 | **Deploy to local XAMPP** (one job): copy every changed ME-tree plugin file and the theme into `moodle5/public`; `php ../admin/cli/upgrade.php --non-interactive`; purge caches. |
| 03:30-05:15 | **PHPUnit** (one runner, sequential; re-init if the versions changed; runtime flush=2), **in parallel with visual verification** (at most 2 browsers; the box falls over under more). |
| 05:15-06:15 | **Fix or revert.** Each regression gets at most 15 min. Otherwise revert that item with Edit to its previous text (never `git checkout`), and redeploy that file. |
| 06:15-07:00 | Evidence README, state cards and PROJECT-STATE.md (the lead). Commit Phase B and Phase C as separate commits. Push. Run `tools/uat/deploy_to_uat.sh --range bfa2ab9d9..HEAD --prefer-me` as a **dry run only**, and save its plan for 09:00. |
| 09:00 | UAT deploy on Nitin's go, then the pre-flight additions P1-P6. |

**Static gates (02:30):**
- `php -l` on every touched PHP file, in both trees.
- `php tools/check-tree-drift.php`: "NEW (blocking): 0".
- `php tools/check-lang-parity.php`.
- `php tools/check-upgrade-step-versions.php` (0 findings) and its selftest.
- `php tools/check-path-boundary.php`.
- `php tools/check-theme-layout-regions.php`.
- `php moodle-enhancement/tools/scan_missing_end_of_body.php theme/sentientia/templates/drawers.mustache theme/sentientia/templates/dashboard.mustache`.
- `npx stylelint --config theme/sentientia/.stylelintrc.json "theme/sentientia/scss/moodle/partials/_surface-*.scss"`.
- `grep -rn "self_enrol" moodle-enhancement/local/sentientia_catalog local/sentientia_catalog` hits comments or
  docblocks only.
- The pre-commit hook runs at commit time.

**PHPUnit suites, sequential, from `moodle5\`:**
- theme_sentientia: shell_title_test, topbar_notifications_test, layout_footer_template_test,
  dashboard_layout_source_test, teaching_courses_test, dashboard_chrome_template_test, dashboard_compliance_test,
  sr_heading_text_test, nav_chrome_test, course_recommendations_test, scss_test, plus role_detector_test if G7
  ran.
- local_sentientia_catalog: enrol_fallback_test, request_offer_test, enrolment_test, catalog_home_test,
  price_source_test, tenant_gate_test, storefront_checkout_test.
- local_sentientia_compliance_report: compliance_engine_test, team_scope_test, global_exclusion_test,
  privacy_provider_test.
- local_sentientia_analytics: analytics_manager_test.
- local_sentientia_users: profile_labels_test, profile_header_chip_test, profile_skills_names_plain_test,
  profile_loginas_test.
- local_sentientia_manager: team_manager_test, approval_manager_test.
- local_sentientia_learningpath: list_paths_test.
- local_sentientia_authoring: course_generator_test, draft_manager_test.
- local_sentientia_evaluation (if V25-dup): anonymous_resubmit_test, observer_test, anonymity_sticky_test.
- block_sentientia_compliance: audit_test (if G7).

**Visual verification by persona** (1440 and 390; screenshots in `docs/visual-evidence/2026-11-19/phase-c/`,
with a README):

| Persona | Pages and what to check |
|---|---|
| vp_learner1 | **/my/:** bell badge, card icons, footer. **mycourses:** V3 title, `?lang=hi`. **course.php?id=448 and 456:** notice, layout, back link. **skills:** V3 title. **profile:** V6. **notifications:** footer and styling. **preferences:** footer. **certificates:** white footer logo. |
| vp_public77 | **course.php?id=71:** Add to cart. **cart.** **/my/:** badge shows 10. **390:** footer and FAB. |
| vp_manager1 | **My Team:** title. **member.php?id=3430:** breadcrumb at 390. **performance.php.** **compliance report.** **/my/:** team table at 390. Read a notification: the badge drops. |
| vp_admin1 | **/my/:** KPIs unchanged. **analytics:** funnel. **learning paths.** **5 admin tables.** **certificate templates.** **compliance export.** **profile:** avatar. |
| vp_author1 and vp_courseauthor1 | **/my/:** V16. **studio.php and index.php:** V17. **sidebar.** |
| vp_compliance1 | Compliance report tabs, /my/. |
| vp_admin177 | browse_airpay.php. |
| vp_siteadmin | /admin/search.php: footer, console. |
| If G7 ran | vp_author1 refused; vp_compliance1 allowed. |

**UAT pre-flight additions at 09:00 (read-only, for Nitin):**
- **P1.** Stale snapshot rows (for D6):
  `SELECT COUNT(*) FROM mdl_local_compliance_snapshot s WHERE NOT EXISTS (SELECT 1 FROM mdl_local_compliance_courses c WHERE c.courseid=s.courseid AND c.is_active=1 AND (c.costcenterid=0 OR c.costcenterid=s.costcenterid))`.
- **P2.** Does the UAT `trainer` role carry `moodle/site:viewreports`? This matters for B6 if G7 did not run.
- **P3.** Do the UAT-* demo courses have overview images? (V5)
- **P4.** The upgrade log line "sentientia_org: repainted N …" (CI-ORG-STEP).
- **P5.** Is `ai.assistant.enabled` ON? (V21-fab)
- **P6.** After the deploy and purge, warm the caches as each demo persona.

---

## 6. Decisions for Nitin (each with a recommended answer)

| # | Decision | Recommended answer |
|---|---|---|
| D1 | **B6**: does `site:viewreports` stop granting the tenant matrix and the manager shell? | **Option 1, tonight (G7).** Session memory records "B6: option 1" at 20:14; confirm it. Joseph's shell becomes the learner one (Compliance via the sidebar link). The fallback is option 2: untick viewreports on UAT's trainer role at 09:00. |
| D2 | V9: mask full names on the dashboard mini-leaderboard | **A**: first name plus last initial for everyone except the viewer (LB-MASK, 15 min). The caption fix ships regardless. |
| D3 | V16 "Courses you teach": bug fix, or a new feature that needs a flag? | **Bug fix, no flag.** It only regroups courses already on the dashboard. If he wants a flag, use `sentientia.dashboard.teaching.enabled`, default OFF, flipped ON on UAT at 09:00. |
| D4 | V21: the Airpay-only "Private & confidential" notice is shown to Public learners | **Keep it for the demo**, then make it a per-tenant string after the demo. |
| D5 | CAT-7: cart "Enrol in all (free)" ignores the one-click policy for internal tenants | **Leave it for the demo**, then do option (b) (storefront learners or one-click only) after cutover planning. |
| D6 | ACTIVE-LIST: compliance counts only courses on the active list (deferred) | **Ship after the demo.** If P1 shows more than 0 stale rows on UAT, quote the report's own figure in the demo. |
| D7 | B7: run `refresh_snapshot` once on UAT after the 09:00 deploy (data) | **Yes, if** Vikram's or Joseph's compliance shows "Run the snapshot task first". KPI-CACHE then keeps the report consistent. |
| D8 | B1 template data: template 1 has no elements, and template names are duplicated | **A Stage B runbook step.** Carry the stored PDFs (filedir), then rename the duplicates and retire template 1. Nothing tonight. |
| D9 | V25: may an expired assignment still be answered? | **No: treat it as closed.** After the demo. |
| D10 | V22: language switcher for ZEEA (/177) | **Keep it OFF.** Present ZEEA in English. |
| D11 | V10: test data visible on UAT | **Check only before the demo.** No data change before 11:00. |
| D12 | B1-PORT: the `certificateelement_modulename` port (120 min, core-mods) | **After the demo, before the Stage B rehearsal** (spec in the area plan, kept below). |

**B1-PORT spec, kept for after the demo:**
- A new vendored subplugin at `admin/tool/certificate/element/modulename/`, files:
  - `version.php` 2026111900 (above 2017111300), requires 2022041900;
  - `classes/element.php`, which extends `\tool_certificate\element` with `render`, `render_html` and
    `issue_name($issue)`. `issue_name` resolves in this order: moduletype 'course' or 'learningplan' via moduleid,
    then courseid, then `''`; the result goes through format_string;
  - a privacy null_provider;
  - lang en;
  - an icon copied from element/text;
  - tests: course name, moduletype course, learningplan, missing id, preview text, and that the stored row
    instantiates.
- The core-mods record `moodle-enhancement/docs/core-mods/<date>-certificateelement-modulename.md`, and a
  `// SENTIENTIA-CORE-MOD` tag on each file.
- List all the new paths explicitly for deploy_to_uat.sh.
- Known limit: 1,561 local legacy issues point at deleted courses.

---

## 7. Cut list and items not planned

**Cut or deferred tonight:**
- ACTIVE-LIST (D6).
- MGR-EXPORTCSV.
- V25-row and V25-nav (the flag is OFF).
- V25-expired (D9).
- V15-requests column and status i18n. Only the titles are kept.
- V15-title (covered by V3).
- V4-course-index-column (layout risk on the most-demoed page).
- V11-STICKY (dark-mode rules).
- B1-PORT (D12).
- B1-TEMPLATE-DATA (D8).
- STALE-COMMENTS (no longer needed after CAT-1).
- The AMD route for V19-TABLE-HEADER (replaced by the PHP route).
- The COMPLIANCE-TILE hardening.
- SIDEBAR-usertype (not reproducible).
- The My Courses "teaching" split (V16 follow-up).
- The standalone leaderboard page's full names.

**Drop first if behind, in this order:** V25-dup, CAT-4c/b, CAT-8a/b, CAT-2, V20-notifications-page, V21-fab,
V23, V19-CERT-TEMPLATES, V19-TABLE-HEADER.

**Already done or not reproducible:**
- V4-course-footer (on UAT).
- FA4-LAYOUTS.
- GUEST-BELL.
- V6-chip (working tree).
- V15-done ("999d", breadcrumbs, B7 redirect).
- COMPLIANCE-TILE-SCOPE.
- V25-stale-due (data).

**Not planned, because the input stopped after V19-CERT-TEMPLATES.** These are triaged here without specs, all
DEFER:
- B2: the LinkedIn organisation is hard-coded for ZEEA.
- B7: a count on the approvals sidebar item. Approvals is already reachable through the requests.php redirect.
- B8: anchoring the windowed metrics to the snapshot date, and hiding test personas in Recent Activity.
- B10: one cart. This is a product design change; use the demo workaround.
- V12: "1 users", shortnames in Course Effectiveness, and the System Health hole.
- V18: storefront curation (data).
- V19, other parts: the users list blank row, the Featured form, the org card glyph, the course header's 9
  icons, COMPLETION pills overlapping, admin tables at 390, and the admin bottom nav.
- V22: Swahili coverage.
- V24: re-time on UAT.
- The minor polish list.
