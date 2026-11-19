# Phase C addendum: items the merged plan did not receive (night of 18-19 Nov 2026)

The merged plan (`PHASE-C-PLAN-2026-11-18.md`) was written from area-plan input that was cut off inside V19-CERT-TEMPLATES. This addendum carries the DO items that were lost, verbatim from the area plans (read-only planning workflow wf_4b565735-5b2), plus the two items that follow from Nitin's decisions.

## Decisions (Nitin, 18 Nov ~22:10, in chat)

Nitin: "for the decisions, I want you build based on recommendations as approved, do not wait for my input". So every decision in section 6 of the plan takes its recommended answer:

- D1 B6: option 1 tonight (G7). D2 V9: **A**, LB-MASK runs. D3 V16: **bug fix, no flag** (the flag added by G3 is removed again, item V16-UNFLAG). D4 keep the notice for the demo. D5 leave the cart for the demo. D6 ACTIVE-LIST after the demo. D7 run `refresh_snapshot` once on UAT after the 09:00 deploy if a compliance page says "Run the snapshot task first". D8 Stage B runbook step. D9 expired = closed, after the demo. D10 ZEEA language switcher stays OFF. D11 check only. D12 B1-PORT after the demo, before Stage B.

- Area-plan decisions with no section 6 entry: CERT-TWO-LISTS (a) gallery canonical, no change; V19-LP-ZERO-COURSES check the seeded UAT path, no data action; V19-CERT-EDIT-SCOPE (c) leave as is for the demo (access change, no recommendation to act); V8-K Moodle core hi pack, no change (course pages are demoed in English); V22-a/b stay as they are (D10).


## Ground rules

Section 0 of the plan applies unchanged. In particular: plugins in both trees byte-identical; **no lang file edits and no version.php edits in these items** (template-only changes need no bump; the caches are purged at deploy). Report every string key with en + hi text; the strings step (G9b) adds them after the main G9 step.


## V8-F (W1; area i18n-zeea; DO, risk low, 10 min)

**Mobile bottom nav labels hard-coded English (Home, My Learning, Search, Me)**


**reason:**

4 visible labels on every phone page. Text only.


**files:**

- theme/sentientia/templates/mobile_bottom_nav.mustache
- theme/sentientia/lang/en/theme_sentientia.php
- theme/sentientia/lang/hi/theme_sentientia.php


**spec:**

PRIORITY 3. The demo avoids phone width, but this shows on every page at 390 px.
- line 33 aria -> {{#str}}a11y_mobilenav, theme_sentientia{{/str}}
- line 38 -> {{#str}}nav_home, theme_sentientia{{/str}} (existing key, en 'Home', hi 'होम')
- line 44 mobilenav_mylearning
- line 50 mobilenav_search
- line 56 mobilenav_me

The theme lang files are modified in the WT by other jobs, so this goes in the last step.


**strings:**

a11y_mobilenav = 'Primary mobile navigation' | hi 'मुख्य मोबाइल नेविगेशन'; mobilenav_mylearning = 'My Learning' | hi 'मेरी लर्निंग'; mobilenav_search = 'Search' | hi 'खोजें'; mobilenav_me = 'Me' | hi 'मैं'


**version_bump:**

Theme is 2026111801 in the WT, and UAT has 2026111800: ride it. If the theme has been committed at 2026111801, use 2026111802.


**tests:**

Run theme_sentientia nav_chrome_test and dashboard_chrome_template_test (they check chrome templates). Run check-lang-parity.


**visual_checks:**

vp_learner1 /local/sentientia_catalog/mycourses.php?lang=hi at 390 px: bottom nav in Hindi, still on one line per item. Then ?lang=en.


## V8-E (W1; area i18n-zeea; DO, risk low, 15 min)

**Featured-for-you widget on the L&D admin dashboard is hard-coded English (Meera's dashboard is a Hindi demo surface)**


**reason:**

It is the only English block left on a dashboard the demo shows in Hindi. 4 strings.


**files:**

- moodle-enhancement/local/sentientia_courses/templates/featured_widget.mustache
- local/sentientia_courses/templates/featured_widget.mustache
- moodle-enhancement/local/sentientia_courses/lang/en/local_sentientia_courses.php
- local/sentientia_courses/lang/en/local_sentientia_courses.php
- moodle-enhancement/local/sentientia_courses/lang/hi/local_sentientia_courses.php
- local/sentientia_courses/lang/hi/local_sentientia_courses.php
- moodle-enhancement/local/sentientia_courses/version.php
- local/sentientia_courses/version.php


**spec:**

PRIORITY 1. Demo script 3.8 switches Meera's /my/ to Hindi. Her dashboard renders local_sentientia_courses_render_featured_widget() (lib.php:36; the manage capability gate means admins and curators only). The template is identical in both trees. Replace: line 16 'Featured for you' -> {{#str}}featured_widget_heading, local_sentientia_courses{{/str}}; line 19 'Browse all courses' -> {{#str}}featured_widget_browseall, local_sentientia_courses{{/str}}; line 54 'Enrol' -> {{#str}}featured_widget_enrol, local_sentientia_courses{{/str}}; line 60 'Preview' -> {{#str}}featured_widget_preview, local_sentientia_courses{{/str}}. The 'Featured demo' badge is DB data (the label field), so leave it. The lang/en and lang/hi files of this plugin are in tools/tree-drift-baseline.txt (CONTENT drift). APPEND the same 4 lines at the end of each of the 4 files and do not reconcile the other drift: the baseline line stays valid. English output stays byte-identical.


**strings:**

featured_widget_heading = 'Featured for you' | hi 'आपके लिए चुने गए कोर्स'; featured_widget_browseall = 'Browse all courses' | hi 'सभी कोर्स ब्राउज़ करें'; featured_widget_enrol = 'Enrol' | hi 'नामांकन करें'; featured_widget_preview = 'Preview' | hi 'पूर्वावलोकन'


**version_bump:**

Recommended: courses 2026111600 -> 2026111800, release 1.14.3 ('featured widget strings en+hi'), in both trees. 2026111600 is already on UAT, and a template or lang change needs a cache purge, which the upgrade gives.


**tests:**

No test asserts these texts. Run php tools/check-lang-parity.php, php tools/check-tree-drift.php, and php -l on all 4 lang files.


**visual_checks:**

vp_admin1 /my/?lang=hi at 1440 px: heading, link and both buttons in Hindi. Then /my/?lang=en: unchanged English. Reset the session with ?lang=en.


## V8-D (W1; area i18n-zeea; DO, risk low, 45 min)

**First-login onboarding wizard is entirely hard-coded English (Rahul has a Hindi UI on UAT)**


**reason:**

A Hindi-UI learner's first screen would be fully English. Text-only change; form and POST behaviour are unchanged.


**files:**

- moodle-enhancement/local/sentientia_pages/templates/onboarding.mustache
- local/sentientia_pages/templates/onboarding.mustache
- moodle-enhancement/local/sentientia_pages/onboarding.php
- local/sentientia_pages/onboarding.php
- moodle-enhancement/local/sentientia_pages/lang/en/local_sentientia_pages.php
- local/sentientia_pages/lang/en/local_sentientia_pages.php
- moodle-enhancement/local/sentientia_pages/lang/hi/local_sentientia_pages.php
- local/sentientia_pages/lang/hi/local_sentientia_pages.php
- tools/tree-drift-baseline.txt


**spec:**

PRIORITY 1 if Nitin may show Rahul (demo script 3.8 optional: uat_learner2_airpay was set up to meet this wizard in Hindi). theme layout/dashboard.php:83-85 redirects a learner here. Today every word of it is English.

The two trees DRIFT (both files are in tools/tree-drift-baseline.txt). The ME copy, which UAT runs, hard-codes 'Airpay Academy'. The top copy uses customername in the template and get_site()->fullname in the PHP. Reconcile both trees to ONE new content, then delete the two baseline lines 'CONTENT sentientia_pages/onboarding.php' and 'CONTENT sentientia_pages/templates/onboarding.mustache'. The gate fails if a baselined path has been reconciled.

onboarding.php: add $brand = get_string('customername', 'theme_sentientia') (en 'Airpay Academy', so ME English stays byte-identical). Line 101 set_title -> get_string('onboard_pagetitle', 'local_sentientia_pages', $brand). Line 89 redirect message -> get_string('onboard_done_notice', 'local_sentientia_pages', $brand). Add 'brandname' => $brand to $data (after line 205).

Template:
- line 7 alt -> {{brandname}}
- 12 -> {{#str}}onboard_welcome, local_sentientia_pages, {{firstname}}{{/str}}
- 13 onboard_welcome_sub
- 15 onboard_letsgo
- 20 onboard_skip
- 26 onboard_interests_title
- 27 onboard_interests_sub
- 35 -> {{#str}}onboard_ncourses, local_sentientia_pages, {{count}}{{/str}}
- 42 and 70 'Back' -> {{#str}}back{{/str}} (core)
- 45 and 73 'Continue' -> {{#str}}continue{{/str}} (core)
- 52 onboard_goal_title
- 53 onboard_goal_sub
- 57, 61 and 65: keep the emoji and the <br>, replace the words with onboard_goal_casual, onboard_goal_regular and onboard_goal_committed, and '2 days/week' with {{#str}}onboard_goal_days, local_sentientia_pages, 2{{/str}} (same for 3 and 5)
- 80 onboard_recs_title
- 81 onboard_recs_sub
- 89 -> {{#str}}onboard_n_enrolled, local_sentientia_pages, {{enrolled}}{{/str}}
- 124 onboard_privacy_heading
- 127 and 131: keep the emoji, use onboard_consent_marketing and onboard_consent_leaderboard
- 134-135 -> {{#str}}onboard_consent_hint, local_sentientia_pages, {{brandname}}{{/str}}
- 141 onboard_finish

Do not touch the form field names, the hidden inputs or the inline nextStep() JS (it has no English).


**strings:**

en | hi:
- onboard_pagetitle 'Welcome to {$a}' | '{$a} में आपका स्वागत है'
- onboard_welcome 'Welcome, {$a}!' | 'स्वागत है, {$a}!'
- onboard_welcome_sub 'Let\'s set up your learning experience in 30 seconds.' | 'आइए 30 सेकंड में आपका लर्निंग अनुभव सेट करें।'
- onboard_letsgo 'Let\'s Go' | 'चलिए शुरू करें'
- onboard_skip 'Skip for now' | 'अभी छोड़ें'
- onboard_interests_title 'What do you want to learn?' | 'आप क्या सीखना चाहते हैं?'
- onboard_interests_sub 'Pick topics that interest you. We\'ll personalize your experience.' | 'अपनी रुचि के विषय चुनें। हम आपका अनुभव उसी के अनुसार बनाएँगे।'
- onboard_ncourses '{$a} courses' | '{$a} कोर्स'
- onboard_goal_title 'Set your weekly goal' | 'अपना साप्ताहिक लक्ष्य तय करें'
- onboard_goal_sub 'How many days per week do you want to learn?' | 'आप हफ़्ते में कितने दिन सीखना चाहते हैं?'
- onboard_goal_casual 'Casual' | 'आराम से'
- onboard_goal_regular 'Regular' | 'नियमित'
- onboard_goal_committed 'Committed' | 'पूरी लगन से'
- onboard_goal_days '{$a} days/week' | 'हफ़्ते में {$a} दिन'
- onboard_recs_title 'Start with these popular courses' | 'इन लोकप्रिय कोर्स से शुरुआत करें'
- onboard_recs_sub 'Recommended for you based on what others in your role are learning.' | 'आपकी भूमिका के दूसरे लोग जो सीख रहे हैं, उसके आधार पर आपके लिए सुझाए गए।'
- onboard_n_enrolled '{$a} enrolled' | '{$a} नामांकित'
- onboard_privacy_heading 'Privacy choices' | 'प्राइवेसी विकल्प'
- onboard_consent_marketing 'Send me course recommendations + product updates by email' | 'मुझे ईमेल से कोर्स सुझाव और प्रोडक्ट अपडेट भेजें'
- onboard_consent_leaderboard 'Show me on the public learner leaderboard (you can opt out anytime)' | 'मुझे सार्वजनिक लर्नर लीडरबोर्ड पर दिखाएँ (आप कभी भी ऑप्ट-आउट कर सकते हैं)'
- onboard_consent_hint 'You can change these anytime in your profile. Neither is required to use {$a}.' | 'आप इन्हें कभी भी अपनी प्रोफ़ाइल में बदल सकते हैं। {$a} का उपयोग करने के लिए इनमें से कोई भी ज़रूरी नहीं है।'
- onboard_finish 'Go to My Dashboard' | 'मेरे डैशबोर्ड पर जाएँ'
- onboard_done_notice 'Welcome to {$a}! Your dashboard is ready.' | '{$a} में आपका स्वागत है! आपका डैशबोर्ड तैयार है।'


**version_bump:**

Pages is already 2026111800 in the WT (not yet on UAT): ride that bump and extend the version.php comment. If pages has been committed at 2026111800 by then, use 2026111801, release 1.2.2. Same in both trees.


**tests:**

None assert these texts. Run php tools/check-lang-parity.php; php tools/check-tree-drift.php (it must report the two reconciled paths gone and nothing new); php -l on both onboarding.php copies and the 4 lang files.


**visual_checks:**

vp_report1b has airpay_onboarding_complete unset locally (read-only DB check). Open /local/sentientia_pages/onboarding.php?lang=hi at 1440 and 390 px. Step through with 'Let's Go' and 'Continue' (client-side only). Do NOT press 'Skip for now' or 'Go to My Dashboard': both POST and set the preference. Repeat with ?lang=en to confirm unchanged English, then reset with ?lang=en.


## V19-ORG-CARD (W1; area org-cert-admin; DO, risk low, 25 min)

**Organisation page: tenant card meta row reads '9 units⁘2207 users' and the kebab collides with the Active chip**


**reason:**

Visible on the org page; template and CSS only.


**files:**

- moodle-enhancement/local/sentientia_org/templates/manage.mustache:5-67 (style), 95-150 (tenant card)
- local/sentientia_org/templates/manage.mustache (identical)
- moodle-enhancement/local/sentientia_org/templates/org_node.mustache:14
- local/sentientia_org/templates/org_node.mustache:14


**spec:**

Two causes, both in markup. (a) The whole tenant row is <a class='tenant-row' data-toggle='collapse'>, and the kebab dropdown with its <a class='dropdown-item'> links sits INSIDE that link (lines 116-145). A nested <a> makes the HTML parser close the outer link and move the meta row out of the flex row into a cloned link. That is why the meta row renders under the title, left-aligned. (b) The theme compiles Bootstrap 4.6.0 (scss/bootstrap/bootstrap.scss), which has no gap-* utilities, so 'd-flex align-items-center gap-3' adds no spacing. 59 plugin templates use gap-*; see GAP-SHIM. Fix in both trees. manage.mustache: wrap lines 96-149 in <div class='tenant-head'>. Keep the <a class='tenant-row ...' data-toggle='collapse' href='#org-{{id}}-children' role='button' aria-expanded='false' aria-controls=...> containing .org-meta (icon and name) and a <div class='org-actions'> with the units span, the users span, the status badge and the chevron <i class='fa fa-chevron-right toggle-arrow'>. The chevron stays inside the link, so the '[aria-expanded=true] .toggle-arrow' rotation still works. Move the {{#can_manage}}<span class='dropdown'>...</span>{{/can_manage}} block OUT of the <a>, as the next sibling inside .tenant-head, and drop onclick='event.stopPropagation();'. Style block: add [data-region='airpay-org'] .tenant-head { display: flex; align-items: center; padding-right: 18px; } [data-region='airpay-org'] .tenant-head > .tenant-row { flex: 1 1 auto; min-width: 0; } [data-region='airpay-org'] .org-tenant-card .org-meta { display: flex; align-items: center; gap: 12px; min-width: 0; } [data-region='airpay-org'] .org-actions { display: flex; align-items: center; flex-wrap: wrap; } [data-region='airpay-org'] .org-actions > * + * { margin-left: 16px; }. org_node.mustache line 14: change class 'd-flex align-items-center gap-3' to 'org-actions'. The org_actions.js click handler is delegated on [data-region='airpay-org'] (amd/src/org_actions.js:84-88), so the moved menu items still work. Keep data-toggle only, never data-bs-toggle (FX-20).


**strings:**

none


**version_bump:**

none extra: ride the sentientia_org 2026111800 bump from CI-ORG-STEP, and add 'org card markup: no nested link, spacing without BS5 gap utilities' to its comment. Templates are refreshed by the deploy's purge.


**tests:**

none (template only). Optional: a PHPUnit render of local_sentientia_org/manage that asserts no '<a' inside the tenant-row markup.


**visual_checks:**

/local/sentientia_org/admin.php as vp_admin1 at 1440 and 390: the card is one row (icon and name left; '9 units', '2207 users', Active chip, chevron, kebab right) with even gaps. Clicking the row expands the departments and turns the chevron. The kebab opens the menu without toggling the row. Edit Settings opens the modal. Console: 0 errors.


## V19-USERS-BLANK-ROW (W1; area org-cert-admin; DO, risk low, 15 min)

**Users list: a blank-name account is always the first row (Name sort)**


**reason:**

The first row of the first admin table looks broken. Ordering change only.


**files:**

- moodle-enhancement/local/sentientia_users/classes/external/list_users.php:56
- local/sentientia_users/classes/external/list_users.php:56 (identical)
- both trees: tests/external/list_users_test.php


**spec:**

Locally 1 non-deleted account has empty firstname and lastname, so the default sort (lastname ASC) puts it first. 26 accounts have lastname '.' (left as data). Line 56: if $sort is 'lastname' or 'firstname', $orderby = "CASE WHEN TRIM(u.firstname) = '' AND TRIM(u.lastname) = '' THEN 1 ELSE 0 END, u.{$sort} {$sortdir}, " . ($sort === 'lastname' ? "u.firstname {$sortdir}, " : '') . 'u.id ASC'. Otherwise keep the current $orderby. $sort is already allow-listed (line 51), so there is no injection. Paging stays deterministic (u.id tiebreak). No filtering, so the account is still listed, only last. list_users.php is not touched by the other job in the working tree (only user_manager.php, signup_service.php and profile.mustache are).


**strings:**

none


**version_bump:**

local_sentientia_users: the working tree already has 2026111800 (other job). Add to its comment, or bump to 2026111801 if that one is already deployed locally.


**tests:**

In tests/external/list_users_test.php (both trees): create one user with empty firstname and lastname and two named users at the caller's tenant path. Call with sort 'lastname' asc and assert the blank one is the last row, not the first.


**visual_checks:**

/local/sentientia_users/index.php as vp_admin1 at 1440: the first row is a named user. Sorting by Name in both directions keeps the blank row last.


## LINKEDIN-ORG (W1; area org-cert-admin; DO, risk low, 10 min)

**Certificate gallery: LinkedIn share hard-codes organizationName=Airpay Academy**


**reason:**

Removes the hard-code with no visible change today. Can be dropped if time is short.


**files:**

- moodle-enhancement/local/sentientia_pages/certificates.php:92-94
- local/sentientia_pages/certificates.php:92-94


**spec:**

Before the foreach (about line 71), in both trees: $orgname = 'Airpay Academy'; if (class_exists('\\local_sentientia_platform\\customer')) { try { $brand = \local_sentientia_platform\customer::branding(); if (!empty($brand['name'])) { $orgname = (string) $brand['name']; } } catch (\Throwable $e) { /* keep default */ } }. Lines 92-94: replace '&organizationName=Airpay%20Academy' with '&organizationName=' . rawurlencode($orgname). Today the output is unchanged: the tenant registry is dormant, so every viewer resolves to customer 1, whose brand row name (or the default_brand() fallback) is the Academy name. Check the local_sentientia_customer_brand name for customer 1 once the local upgrade finishes. Local bootstrap is refused right now: 'Site is being upgraded'. What a ZEEA learner should see is a product decision (see the notes).


**strings:**

none


**version_bump:**

rides local_sentientia_pages 2026111801 (V19-CERT-TEMPLATES)


**tests:**

optional: none (string plumbing)


**visual_checks:**

/local/sentientia_pages/certificates.php as vp_learner1 (if the LinkedIn setting is on): hover 'Add to LinkedIn'; the URL carries organizationName equal to the customer brand name.


## V8-B (W2-after-G1B; area i18n-zeea; DO, risk low, 30 min)

**Airpay catalogue page: E-LEARNING chip, 'N सभी कोर्स' category count, 'Filters & Sort', 'Showing x-y of z', NEW badge in English**


**reason:**

Visible on a page the demo opens. Change is limited to string lookups plus one PHP label array.


**files:**

- moodle-enhancement/local/sentientia_catalog/templates/catalog.mustache
- local/sentientia_catalog/templates/catalog.mustache
- moodle-enhancement/local/sentientia_catalog/templates/course_card.mustache
- local/sentientia_catalog/templates/course_card.mustache
- moodle-enhancement/local/sentientia_catalog/classes/catalog_manager.php
- local/sentientia_catalog/classes/catalog_manager.php
- moodle-enhancement/local/sentientia_catalog/lang/en/local_sentientia_catalog.php
- local/sentientia_catalog/lang/en/local_sentientia_catalog.php
- moodle-enhancement/local/sentientia_catalog/lang/hi/local_sentientia_catalog.php
- local/sentientia_catalog/lang/hi/local_sentientia_catalog.php


**spec:**

PRIORITY 2. This is demo step 3.1.5 (/local/sentientia_catalog/index.php); the hi screenshot shows these in English. Do it in the same edit as V8-A (same lang files).

catalog_manager.php lines 642-653: before $types add $elearning = get_string('coursetype_elearning', 'local_sentientia_catalog'). Use $elearning for keys 0 and 1, for the match default (line 650) and for the fallback (line 653). Use get_string('coursetype_classroom', 'local_sentientia_catalog') for key 2 and the existing coursetype_exam for key 3. No logic compares these labels (checked). The tests assert 'E-Learning' under en, so they still pass.

catalog.mustache:
- line 51 '% complete' -> {{#str}}mycourses_pctcomplete, local_sentientia_catalog, {{progress}}{{/str}}
- line 117 '{{course_count}} {{#str}}allcourses...' -> {{#str}}category_coursecount, local_sentientia_catalog, {{course_count}}{{/str}}. This also fixes English, which today reads '184 All Courses'.
- line 153 filters_sort
- line 155 aria -> {{#str}}filters_active_aria, local_sentientia_catalog, {{filter_count}}{{/str}}
- line 158 aria filters_group_aria
- line 188 -> {{#str}}showing_range, local_sentientia_catalog, { "from": {{showing_from}}, "to": {{showing_to}}, "total": {{total}} }{{/str}}
- lines 19, 58, 70, 78, 90 and 98 aria -> the existing public_scrollleft / public_scrollright keys (identical English)

course_card.mustache:
- line 27 'NEW' -> badge_new, with its aria-label -> badge_new_aria
- line 31 aria 'Completed' -> mycourses_completed

Optional, screen-reader only, if time: line 14 card_viewdetails_aria ({$a} = {{fullname}}) and line 81 card_enrolled_aria ({$a} = {{enrolled_count}}). Do not use the JSON form with fullname: quotes in course names would break it.

Out of scope (DEFER): index.php:143-181 empty states and the 'Remove category filter:' aria; they are not normally visible.


**strings:**

en | hi:
- coursetype_elearning 'E-Learning' | 'ई-लर्निंग'
- coursetype_classroom 'Classroom' | 'क्लासरूम'
- category_coursecount '{$a} courses' | '{$a} कोर्स'
- filters_sort 'Filters & Sort' | 'फ़िल्टर और क्रम'
- filters_active_aria '{$a} active' | '{$a} सक्रिय'
- filters_group_aria 'Active filters and sort' | 'सक्रिय फ़िल्टर और क्रम'
- badge_new 'NEW' | 'नया'
- badge_new_aria 'New course' | 'नया कोर्स'
- optional: card_viewdetails_aria 'View details for {$a}' | '{$a} का विवरण देखें'
- optional: card_enrolled_aria '{$a} learners enrolled' | '{$a} लर्नर नामांकित'

Reuses showing_range and mycourses_pctcomplete from V8-A.


**version_bump:**

Rides the catalog WT bump with V8-A.


**tests:**

Run the local_sentientia_catalog PHPUnit (course_lookups_readers_test, pseudo_course_labels_test, catalog_mobile_layout_test) and check-lang-parity.


**visual_checks:**

vp_learner1 /local/sentientia_catalog/index.php?lang=hi at 1440 and 390 px: type chip, category counts, filter summary, 'Showing' line, NEW badge. Then ?lang=en: chip still 'E-LEARNING' (CSS uppercase), and the count reads '184 courses'.


## V8-G (W2-after-G4A; area i18n-zeea; DO, risk low, 30 min)

**Profile page first screen: stat cards ('pts', 'badges', 'day streak', 'skill readiness') and detail-grid labels hard-coded English**


**reason:**

V8 lists the profile stat cards. Low risk, but off the demo path: optional.


**files:**

- moodle-enhancement/local/sentientia_users/templates/profile.mustache
- local/sentientia_users/templates/profile.mustache
- moodle-enhancement/local/sentientia_users/lang/en/local_sentientia_users.php
- local/sentientia_users/lang/en/local_sentientia_users.php
- moodle-enhancement/local/sentientia_users/lang/hi/local_sentientia_users.php
- local/sentientia_users/lang/hi/local_sentientia_users.php


**spec:**

PRIORITY 3. Do this only after P1 and P2 are done; the profile is not on the Hindi demo path. The users job has edited this template in the WT, so do it last.
- line 8 title -> profile_changephoto
- line 83 -> {{#str}}profile_n_pts, local_sentientia_users, {{total_points}}{{/str}}
- line 84 'Rank #{{rank}}' -> {{#str}}profile_rank_n, local_sentientia_users, {{rank}}{{/str}} (keep '{{level_name}} · ')
- line 92 -> profile_n_badges ({$a} = badge_count)
- line 93 -> profile_n_day_streak ({$a} = current_streak) and profile_start_streak
- line 103 -> profile_skill_readiness ({$a} = percentage)
- line 104 -> {{#str}}profile_skills_met, local_sentientia_users, { "met": {{met}}, "total": {{total}} }{{/str}}

Detail grids, using EXISTING users keys with identical English: Organisation -> organisation; Department -> department; Employee ID -> employeeid; Location -> location; Phone -> phone; Email -> email. Lines 136, 137, 140, 144, 145, 157, 158, 204, 205, 206, 214, 215, 218, 219 and 220.

New keys for 'Sub-department' (lines 138 and 216) and 'Join Date' (lines 146, 207 and 221).

DEFER the skills and grades panels below line 226 (about 15 strings).


**strings:**

en | hi:
- profile_changephoto 'Change photo' | 'फ़ोटो बदलें'
- profile_n_pts '{$a} pts' | '{$a} अंक'
- profile_rank_n 'Rank #{$a}' | 'रैंक #{$a}'
- profile_n_badges '{$a} badges' | '{$a} बैज'
- profile_n_day_streak '{$a} day streak' | '{$a} दिन की स्ट्रीक'
- profile_start_streak 'Start a streak!' | 'स्ट्रीक शुरू करें!'
- profile_skill_readiness '{$a}% skill readiness' | '{$a}% स्किल रेडीनेस'
- profile_skills_met '{$a->met}/{$a->total} skills met' | '{$a->total} में से {$a->met} स्किल पूरी'
- profile_subdepartment 'Sub-department' | 'उप-विभाग'
- profile_joindate 'Join Date' | 'जॉइनिंग की तिथि'


**version_bump:**

Users is already 2026111800 / 2.9.3 in the WT: ride it. If committed, use 2026111801.


**tests:**

Run the users PHPUnit group, check-lang-parity and check-tree-drift.


**visual_checks:**

vp_learner1 /local/sentientia_users/profile.php?lang=hi at 1440 and 390 px. Then ?lang=en: English unchanged (the '1 badges' plural is V6's, not this item's).


## V19-COURSE-HEADER (W2-after-G2; area org-cert-admin; DO, risk low, 20 min)

**Course header for admins: two identical download icons, a duplicate enrolled-users icon and a dead edit pencil**


**reason:**

Visible clutter and a dead control on the admin course page, which may be shown.


**files:**

- theme/sentientia/templates/course_context_header.mustache:28-32, 42, 77-83, 85-91
- theme/sentientia/classes/output/core_renderer.php:1121-1127
- theme/sentientia/version.php


**spec:**

For Meera (course 256) the header shows 9 icons plus the kebab. Changes: (1) Lines 28-32: delete the 'User enrolments' <li> (fa-user-plus, hard-coded English title). It links to the same enrolledusers.php?id= as the next <li> (lines 33-37, 'Enrolled users', core string). (2) Line 42, course backup: change 'fa fa-download' to 'fa fa-archive' (the FA6 alias exists: scss/fontawesome/_variables.scss:2681), so it no longer matches the certificate download glyph. (3) Lines 77-83: wrap the '{{^ certificate_download }}' <li> (the javascript:void 'unable to download' icon) in {{^ manage }}...{{/ manage }}. Managers no longer get a dead second download icon; learners keep it. (4) Lines 85-91, the pencil: it calls require('local_courses/courseAjaxform'), a BizLMS module that is not installed (no local/courses on XAMPP), so a click does nothing. Replace the <a> with <a title='{{# str }} editcoursesettings {{/ str}}' class='course_extended_menu_itemlink' href='{{config.wwwroot}}/course/edit.php?id={{courseid}}'><i class='icon fa fa-pencil' aria-hidden='true'></i></a>. Drop the onclick, data-action, data-value and the createcoursemodal and courseactions classes; no JS or SCSS hooks on them. In core_renderer.php, after the block at lines 1121-1127, add: $is_courseedit_icon = $is_courseedit_icon && has_capability('moodle/course:update', $systemcontext); ($systemcontext is the course context here, line 1090), so nobody gets a link that core refuses. Result for Meera: 7 icons plus the kebab, each distinct.


**strings:**

none (removes one hard-coded English title)


**version_bump:**

theme -> 2026111802 (shared with the other theme items; coordinate with the theme owner)


**tests:**

Run theme/sentientia/tests/course_editing_mode_test.php (it renders course_context_header_settings_menu) and keep it green. No new test.


**visual_checks:**

/course/view.php?id=256 and ?id=383 as vp_admin1 at 1440 and 390: no duplicate glyphs; hover tooltips are correct; the pencil opens Edit course settings; Bulk enrolments and Enrolled users work. As vp_learner1 on a completed course: the Download certificate icon is still present and opens the PDF.


## V19-COMPLETION-PILL (W2-after-G2; area org-cert-admin; DO, risk low, 15 min)

**Course page: COMPLETION pills sit on top of activity descriptions (POSH, Phishing, IT security, AML)**


**reason:**

Visible on every flagship course page, including learner demo pages. Small CSS change.


**files:**

- theme/sentientia/scss/moodle/partials/_legacy-admin.scss:1861-1869
- theme/sentientia/version.php


**spec:**

The rule .course-section li.activity [data-region='activity-information'] { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); } centres the pill vertically. On an activity with a long description, the pill lands on the description text. The title has padding-right 200px (lines 1919-1922), but the description has none. Fix: anchor it to the top line and leave everything else alone. Change 'top: 50%;' to 'top: 12px;' and 'transform: translateY(-50%);' to 'transform: none;'. Tune 12-16px so the pill lines up with the first content row (the activity icon row) in the screenshot layout. The <=768px block (lines 1926-1936) is already static, so it is unaffected. Note: _surface-profile.scss:1544 holds a dead copy of these rules (not imported); do not edit it. Learners see the same pills, so this fixes Priya's POSH page in the demo too.


**strings:**

none


**version_bump:**

theme -> 2026111802 (shared)


**tests:**

none (CSS). Purge caches after the deploy.


**visual_checks:**

/course/view.php?id=256, 383, 161, 41 as vp_admin1 and vp_learner1 at 1440 and 1024: the pill is at the top right and no description line runs under it. Activities without a description still look aligned. At 390 there is no change (static).


## LB-MASK (W2-after-G3; decision D2 = A; 15 min)

Exactly as the plan's G3 section "LB-MASK" (after the leaderboard data is built in `theme/sentientia/layout/dashboard.php`): every entry except the viewer's own shows the first name and the last initial ("Priya S."). The initials avatar is unchanged. Test: extend `theme/sentientia/tests/dashboard_layout_source_test.php` (or a new small test) so the masking block is asserted. Visual: vp_learner1 and vp_public77 /my/ at 1440: other people show "First L.", the viewer's own row shows the full name.

## V16-UNFLAG (W2-after-G3; decision D3 = bug fix, no flag; 10 min)

G3 built V16 ("Courses you teach") behind `sentientia.dashboard.teaching.enabled`. Nitin approved the recommendation "bug fix, no flag" (it only regroups courses already on the dashboard). Remove the flag gate in `layout/dashboard.php` so the regrouping always runs (keep the `class_exists` guard and a `try/catch (\Throwable)` that falls back to `[]`), remove the key from `theme/sentientia/db/feature_flags.php` (the file must still parse and keep its other entries), and update the tests that asserted the gate (they now assert the regrouping runs unconditionally). The theme changelog line written by G9 that says "behind a default-off flag" must be reworded by the G9b strings step. Visual: vp_author1 and vp_courseauthor1 /my/ show "Courses you teach" with no "Not started 0%" card for the taught course and no "0 of 1" ring; vp_learner1 unchanged.

---

# Phase B leftover defects (found by Phase B verification, 18 Nov 23:10-01:10)

Phase B (local verification of the A+B walk fixes) was stopped at 01:20 after 4 of 10 personas (about 2 h per persona on this box);
its remaining checks moved into the Phase C final verification. These defects were found and are not covered by any Phase C item.
Evidence: `moodle-enhancement/docs/visual-evidence/2026-11-18/walk-fixes/<persona>/`.

## PB-1 Profile consumer grid: e-mail overflows the card (users; low; 10 min)

vp_public77, `/local/sentientia_users/profile.php` at 1440 and 1000: the "Email address" value in the consumer details grid does not wrap and
ends ~30 px past its grid cell and ~9 px past the card's right border. Fix in the inline `<style>` of
`local/sentientia_users/templates/profile.mustache` (both trees): `.ap-profile__detail { min-width: 0; }` and
`.ap-profile__detail span { overflow-wrap: anywhere; }` (check the real class names in the file; keep the change scoped to the details grid).
Visual: vp_public77 profile at 1440/1000/390: the e-mail wraps or fits inside its cell.

## PB-2 Compliance report: filter select overflows at 390 (compliance_report; low; 15 min)

vp_compliance1, every tab at 390: the "All Business Units" `<select>` is 384 px wide from x=53 and ends 47 px past the viewport; the shell
clips it. Cause: `templates/dashboard.mustache` prints the filter bar and selects with inline styles and no class, so the existing
`.airpay-compliance-rpt__filter-bar` / `__filter-select` rules in `styles.css` (590 px column layout, dark mode) never apply. Fix (both trees):
put those classes on the filter bar and the selects, drop the conflicting inline widths, and make sure `styles.css` gives the select
`max-width: 100%` (and `width: 100%` at max-width 590px). No behaviour change. Visual: vp_compliance1 `?tab=matrix|defaulters|scorecard|manager`
at 390: select inside the card, page overflow 0; at 1440 unchanged.

## PB-3 Analytics: literal "&amp;" in the Skill Gap Projection card (analytics; low; 10 min)

vp_compliance1, `/local/sentientia_analytics/index.php`: 6 literal "&amp;" (e.g. "Human Resources &amp; Admin") in
`span.airpay-analytics__skillgap-team`. Cause: `classes/predictive_engine.php` (~line 470) passes team_name through format_string() and
`templates/predictive_skillgap.mustache` (~line 40) escapes it again with `{{team_name}}`; skill_name (~465 / template ~62) has the same
pattern. Fix (both trees): keep the template's `{{ }}` escaping and call `format_string($x, true, ['context' => $ctx, 'escape' => false])`
(use the context already used there) so the text is formatted once and escaped once. Test: extend analytics tests if a predictive test
exists, else a small source assertion. Visual: no "&amp;" text anywhere on the analytics page.

## PB-4 Analytics: Hindi users label cut in the department heatmap (analytics; low; 10 min)

vp_compliance1, `/local/sentientia_analytics/index.php?lang=hi` at 1440: `heatmap_users` ("{$a} उपयोगकर्ता") is cut with an ellipsis
("89 उपयोगक…"). Fix the column in the analytics stylesheet/template (both trees): let the users cell size to its content
(`white-space: nowrap` without the fixed narrow width / ellipsis, or a min-width that fits "999 उपयोगकर्ता"). English must stay as it is.

## PB-5 Sidebar "Dark Mode" label English under ?lang=hi (theme; low; 5 min)

vp_public77, `?lang=hi`: the sidebar "Dark Mode" toggle label is English. Find where it is printed (theme sidebar template or
sidebar_navigation.php); if it is a hard-coded literal, switch to a theme string (reuse an existing key if one exists, else report a new key
in extraStrings). If it already uses a string whose hi value is English, report the key so the strings step translates it.
