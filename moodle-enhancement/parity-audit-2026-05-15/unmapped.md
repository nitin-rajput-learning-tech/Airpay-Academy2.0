# Unmapped BizLMS Plugins — Parity Audit
Generated: 2026-05-15 | Auditor: feature-parity cluster 4 | Stakes: HIGH

Six BizLMS plugins have **no direct `airpay_*` rewrite**:
`forum`, `groups`, `location`, `search`, `tags`, `custom_category`.

For each, identify (a) what BizLMS provided, (b) which Moodle-core or alternative module substitutes, (c) whether each feature is preserved or LOST.

| BizLMS plugin | LOC | PHP files | Status |
|---|---|---|---|
| `local_tags` | 6,849 | 24 | **LOST — partial Moodle-core substitute** |
| `local_forum` | 5,936 | 18 | **LOST — Moodle-core `mod_forum` is a different beast** |
| `local_groups` | 3,574 | 12 | **LOST — Moodle-core `cohort` is partial substitute** |
| `local_search` | 3,216 | 14 | **PARTIALLY REPLACED by `local_airpay_catalog`** |
| `local_location` | 1,516 | 8 | **LOST — small loss, can rebuild** |
| `local_custom_category` | 859 | 6 | **LOST — Moodle-core course categories partial substitute** |

---

## U1. `local_forum` — Discussion / Q&A facility

### What BizLMS provided
- A **separate forum system tied to courses, classrooms, programs, certifications** with subscription, per-tenant scoping (`open_path`), enrol-to-forum workflow.
- 4 message-provider templates wired into local_notifications: `forum_subscription`, `forum_unsubscription`, `forum_reply`, `forum_post` (each with token vocabulary `[forum_name], [forum_url]`).
- `forumdetails.php`, `forumenrol.php`, `userdashboard.php` — a learner could enrol into a forum and follow it like enrolling in a course.
- Capabilities: `view`, `manage`, plus the special workflow where a forum is treated like a learning artifact (not just a Moodle activity within a course).
- Card-vs-table view toggle, filter form via `filters_form.php`, search.
- AMD: `forumAjaxform` for inline add/edit modals.
- Linked to costcenter hierarchy: forums are per-tenant, with depth-1-to-5 organisation drill-down.

### Moodle-core / Airpay equivalent
- **Moodle-core `mod_forum`** is available — it's the standard Moodle forum activity, scoped to a course, with threaded discussions, subscriptions, ratings, attachments, tracking. Significantly more mature than BizLMS forum.
- The architectural difference is fundamental:
  - **BizLMS forum** = top-level entity (discoverable in /local/forum/index.php as a list)
  - **Moodle-core forum** = nested inside a course
- No `local_airpay_forum` plugin exists.
- No discussion/Q&A facility surfaces in the airpayux theme prototypes.

### Gap analysis

| Feature | BizLMS had | Moodle core mod_forum | Loss/Gain | Severity |
|---------|-----------|-----------------------|-----------|----------|
| Threaded discussions | yes | yes — much more mature | net **GAIN** | — |
| Email/RSS subscription | yes | yes | parity | — |
| Per-post rating | NO direct | yes | gain | — |
| Standalone forum (not inside a course) | yes | NO | **LOST** | **P1** |
| Cross-tenant forum visibility control | yes via `open_path` | partial via category context | weaker | P1 |
| Standalone "Forums" navbar entry | yes | NO direct — only per-course | **LOST** | **P1** for L&D community |
| Forum enrolment workflow (request to join private forum) | yes | NO direct (cohort enrol → course → forum is the workaround) | LOST | P1 |
| Notification templates (4 types) | yes | yes (different template system) | parity | — |

### Recommended

Two paths — Nitin's call:

**Path A (cheap):** Embrace Moodle-core. Create one "Community" course per tenant with mod_forum activities. Add a "Community" link to the airpayux navbar that lands on that course. Users self-enrol. Lose: standalone forum browse page, custom tenant scoping.

**Path B (build):** New `local_airpay_community` plugin (~1,500 LOC) that wraps mod_forum behind a top-level UI — list of forums on the navbar, click → land in the underlying course's forum. Restore the enrol-to-forum-only request workflow (uses airpay_request for join requests). **Start at:** new directory `public/local/airpay_community/`. Provide an index page that queries `mdl_forum` with category-context tenant filter and renders a list.

**Severity: P1.** Community/Q&A is a known engagement driver for LMS; losing it removes a soft-skills retention loop.

---

## U2. `local_groups` — Cohort-style user grouping with tenant scoping

### What BizLMS provided
- Wrapper around Moodle-core `cohort` adding `costcenterid`, `departmentid`, `open_path` columns via the `local_groups` mapping table.
- UI to create groups within a tenant + department, mass-enrol users (`mass_enroll.php`), assign cohort to courses (`assign.php`).
- Capabilities: typical Moodle cohort caps (`moodle/cohort:manage`, `moodle/cohort:view`) reused, with the tenant filter applied at query time.
- Card-vs-table view toggle.
- AMD: `newgroup`, `newsubgroup` for nested groups.

### Moodle-core / Airpay equivalent
- **Moodle-core `cohort`** (in core admin, not as a local plugin) — already supports system-wide and category-scoped cohorts, cohort-sync enrolment plugin, cohort-bulk-add users.
- **Airpay's `local_airpay_users`** plugin — handles user management.
- **Airpay's `local_airpay_org`** — handles costcenter/department hierarchy.
- No direct replacement for the "create a group of users within this tenant + assign to N courses" UI in one place.

### Gap analysis

| Feature | BizLMS | Moodle core cohort + airpay_users/org | Loss/Gain | Severity |
|---------|--------|---------------------------------------|-----------|----------|
| Create a named group of users | yes | yes (cohort) | parity | — |
| Tenant-scoped groups (costcenterid + departmentid) | yes | partial — category-scoped only | **LOST** | **P1** |
| Mass-enrol users into a group | yes — `mass_enroll.php` (CSV/email/idnumber pasting) | partial — admin uploads CSV via `admin/tool/uploadusers` | weaker | P2 |
| Assign group → course (bulk enrol) | yes | yes via cohort-sync enrolment plugin | parity | — |
| Sub-groups | yes (nested via `newsubgroup`) | NO direct | LOST | P2 |
| Group visibility per department | yes | NO | LOST | P2 |
| Browse "all groups in my tenant" page | yes (`index.php`) | NO direct equivalent | **LOST** | P1 |

### Recommended

**Path A (lean):** Use Moodle-core cohorts at the category context (one category per tenant). Set the `category` field when creating cohorts. Lose: department subgrouping, mass-enroll-by-email-list UI.

**Path B (build):** New `local_airpay_groups` plugin (~1,000 LOC) bridging cohorts with airpay_org tenancy. **Start at:** new directory `public/local/airpay_groups/`. Has a list page filtered by current user's `open_path`, a create form that auto-sets cohort.category to tenant's category id, and a `mass_enroll.php` page taking CSV/email pasted text.

**Severity: P1** for tenant-based admin operations. Without it, every cohort operation needs site-admin elevation OR exposes cross-tenant data.

---

## U3. `local_location` — Physical training venues + rooms

### What BizLMS provided
- Two tables: `local_location_institutes` (venues — fullname, shortname, address, type, tenant) and `local_location_room` (rooms within a venue — name, building, capacity, description).
- UI to manage venues + rooms (`index.php` + `room.php`), with capability `local/location:manageinstitute`, `local/location:manageroom`, `local/location:viewinstitute`, `local/location:viewroom`.
- Used by `local_classroom` and `local_certification` to populate the "where will this session run?" dropdown.
- Renders address on event invitations, ILT reminders, etc.
- Per-tenant scoping via `costcenter` column.

### Moodle-core / Airpay equivalent
- **NO Moodle-core equivalent.** Moodle's calendar/events don't have a "venue" entity.
- **`local_airpay_classroom`** has a `location` field (likely string or FK) — but if airpay_classroom uses a free-text venue field rather than referencing a venue table, the structured-venue benefits are lost.
- No airpay_location plugin.

### Gap analysis

| Feature | BizLMS | Airpay | Loss/Gain | Severity |
|---------|--------|--------|-----------|----------|
| Manage list of training venues | yes | NO | **LOST** | P1 |
| Manage rooms within a venue | yes | NO | **LOST** | P1 |
| Capacity tracking per room | yes (informs over-booking warnings) | NO | LOST | P1 |
| Render full address on ILT invitations | yes (`institute.address`) | partial — depends on airpay_classroom storing the address | weaker | P1 |
| Tenant-scoped venues | yes | n/a | LOST | P1 |
| Reusable venue across multiple classrooms | yes (FK relationship) | NO if airpay_classroom uses free-text | **LOST** | P1 |

### Recommended

**New `local_airpay_location` plugin** (~800 LOC) with the same two-table schema (`local_airpay_venue`, `local_airpay_room`), per-tenant scoping, simple CRUD UI. Wire airpay_classroom session form to use this dropdown instead of free-text location field.
**Start at:** new directory `public/local/airpay_location/` with `db/install.xml` mirroring the BizLMS schema, plus `index.php` + `room.php` + lib.php + 2 mustache templates.

**Severity: P1.** Without structured venues, every ILT invitation shows free-text addresses that can have typos, can't be reused, and offer no capacity warnings.

---

## U4. `local_search` — Course catalog + global search

### What BizLMS provided
- The user-facing course catalog at `/local/search/allcourses.php` — card/table grid of all visible courses, filterable by category, type, search query.
- Course-detail page `coursedetails.php` (the canonical course landing page for non-enrolled users), with enrol/request button.
- "Global search" entry (`g_search` param) — single search box that searches across courses, classrooms, programs, certifications, onlinetests.
- AngularJS-driven UI (`angular.min.js`, `dirPagination.js`).
- Capabilities: `local/search:viewcatalog`.
- Heavy integration with local_courses, local_classroom, local_program, local_certification.

### Moodle-core / Airpay equivalent
- **`local_airpay_catalog`** — exists. It's the airpay-branded course catalog rewrite. PROJECT-STATE Phase 6B Sprint 3 covers "Course Catalog" + "Course Detail" surfaces.
- `course-catalogue-mobile-bottomsheet-filter` and similar recent commits confirm active iteration on the catalog.
- **Moodle-core `tool_searches`** and `core_search` provide global search (Solr/MySQL fulltext) — but BizLMS's search was custom, not relying on core_search.
- No equivalent for the bundle search-across-modules query (one box, results from courses+classrooms+programs+certifications).

### Gap analysis

| Feature | BizLMS | Airpay catalog | Loss/Gain | Severity |
|---------|--------|----------------|-----------|----------|
| Browse all visible courses | yes | yes | parity | — |
| Filter by category, type, tenant | yes | yes (recent prototype iter 4 mobile filter sheet) | parity | — |
| Card/table toggle | yes | partial — recent prototypes show card layout, no table | minor | P2 |
| Course-detail landing page | yes | yes — included in Phase 6B Sprint 3 | parity | — |
| Enrol/request button from catalog | yes | yes (airpay_request handles request side) | parity | — |
| Global search across ALL learning artifacts (courses + ILT + programs + certs + tests) | yes | partial — search likely course-only | **LOST** | **P1** |
| Mobile-responsive | partial | yes (recent dedicated iterations) | **GAIN** | — |

### Recommended

For global multi-module search: add a unified endpoint `local/airpay_catalog/search.php?q=X` that UNIONs `mdl_course`, `mdl_local_airpay_classroom`, `mdl_local_airpay_programs`, `mdl_tool_certificate_templates` filtered by fulltext. Return results as a grouped list with type badges.
**Start at:** new file `public/local/airpay_catalog/search.php` + `classes/external/unified_search.php`.

**Severity:** P1 if learners use the search box from navbar to find non-course content. Mitigated if the navbar search box already only points to course catalog.

---

## U5. `local_tags` — Custom tagging with tenant scoping

### What BizLMS provided
- Two tables: `local_tags` (extension of Moodle core `tag` with tenant scoping via `open_costcenterid`, `open_departmentid`) and `local_tag_mapping` (links tags to learning artifacts).
- A full tag-management UI (`index.php`, `edit.php`, `manage.php`) with capability `view`/`manage`/`edit`.
- Tags applied to courses, classrooms, programs, onlinetests, certifications, feedback — making them discoverable via tag-based filtering.
- AMD: `taginstance` for inline tag editing.
- Used by `local_search` to filter results, by `block_suggested_courses` to match user's `local_interested_skills` against tag-related courses.

### Moodle-core / Airpay equivalent
- **Moodle-core `tag` system** (in core, all Moodle activities use it) — supports tag clouds, tag pages, tag search, official/non-official tags, tag collections.
- **`local_airpay_skills`** plugin exists — likely covers the user-interest matching.
- No `local_airpay_tags`.

### Gap analysis

| Feature | BizLMS | Moodle-core tags + airpay_skills | Loss/Gain | Severity |
|---------|--------|----------------------------------|-----------|----------|
| Tag a course | yes | yes (built into course edit form) | parity | — |
| Tag a classroom | yes | partial — Moodle core supports tags on most areas, but airpay_classroom may not register tag_area | **LOST** if not wired | P1 |
| Tag a program / cert / test | yes | partial — depends on airpay plugins registering tag areas | **LOST** if not wired | P1 |
| Tag management UI per tenant | yes | NO — Moodle-core tags are global | **LOST** | **P1** |
| Per-tenant tag collections | yes | NO direct (single global tag collection) | **LOST** | P1 |
| Tag-based course filtering | yes | partial via Moodle search | weaker | P1 |
| Skill ↔ tag bridge for recommendations | yes (via `local_interested_skills` + `local_tag_mapping`) | partial — airpay_skills covers user side, but no bridge to course tags | **LOST** | P1 |

### Recommended

**Wire airpay_classroom/programs/learningpath into Moodle core tag system** by registering tag areas in each plugin's `db/tag.php`. This is a 30-line change per plugin.
**Start at:** new `public/local/airpay_classroom/db/tag.php` (and similar for programs, learningpath).

For per-tenant tag collections: Moodle supports `tag_collection` — create one tag collection per tenant (id=1 for Airpay, 77 for Public, 177 for ZEEA) and route plugin tag areas to the appropriate collection based on current user's `open_path`.
**Start at:** new helper function `local_airpay_core::get_tenant_tag_collection_id()` and use it in tag-area definitions.

**Severity: P1.** Tag-driven discovery is foundational for personalised recommendations.

---

## U6. `local_custom_category` — Per-tenant course-category overrides

### What BizLMS provided
- A second category hierarchy (`local_custom_category`) layered on top of Moodle's core `course_categories` with tenant scoping via `costcenterid` and depth-tracking (`path`, `depth`).
- Allowed each tenant to define its own category tree (e.g. Airpay tenant has "Compliance / Sales / Engineering", Public tenant has "Banking / Insurance / Retail").
- UI: `index.php` for browsing, create/edit modal.
- Capabilities: `view`, `create`, `manage`, `delete`.
- Used by `local_courses` to assign courses to a tenant-specific category in addition to the Moodle native one.

### Moodle-core / Airpay equivalent
- **Moodle-core `course_categories`** — already supports nested categories with capability-context (each category gets its own CONTEXT_COURSECAT).
- **`local_airpay_org`** plugin — likely handles costcenter hierarchy (was previously `local_costcenter` in BizLMS).
- **`local_airpay_courses`** — handles course management.
- No direct `local_airpay_custom_category`.

### Gap analysis

| Feature | BizLMS | Moodle core + airpay_org/courses | Loss/Gain | Severity |
|---------|--------|----------------------------------|-----------|----------|
| Per-tenant category hierarchy | yes | partial — Moodle has one global tree; convention is to nest tenants under separate parents | **LOST** structurally | P1 |
| Distinct category names per tenant | yes | NO (single global tree) | **LOST** | P2 |
| Sub-category drill-down per tenant | yes | partial — works with global tree | weaker | P2 |
| Capability scoping at category level | yes (CONTEXT_COURSECAT) | yes — Moodle core supports this | parity | — |

### Recommended

This is the **smallest gap** of the six. Moodle's native category tree CAN support tenant separation if used consistently:
- Top-level categories: `Airpay`, `Public Learning`, `ZEEA`
- Each tenant's courses live under its top-level category
- Capability assignments at CONTEXT_COURSECAT effectively scope managers to one tenant

If airpay_courses already enforces this convention (PROJECT-STATE suggests it does — "costcenterid resolved dynamically by BizLMS at runtime") then **no new plugin is needed**.

**Severity: P2.** Confirm by visiting `/course/index.php` and verifying the top-level structure matches tenants. If yes, done. If no, run a one-time DB migration to reparent each course under its tenant's top-level category.

---

## Summary verdict for stakeholder

**Status: SIGNIFICANT LOST FUNCTIONALITY in supporting plugins; some Moodle-core or airpay-plugin substitutes available but require deliberate wiring.**

### Net assessment per plugin

| BizLMS plugin | Recommended action | Effort | Severity |
|---|---|---|---|
| `forum` | Path A (use mod_forum + Community course per tenant) OR Path B (build airpay_community wrapping mod_forum) | A: 1 day, B: 1 week | P1 |
| `groups` | Build `local_airpay_groups` bridging cohorts to tenancy | 1 week | **P1** |
| `location` | Build `local_airpay_location` (venues + rooms) | 3 days | **P1** |
| `search` | Add unified search endpoint to airpay_catalog | 2 days | P1 |
| `tags` | Wire tag areas in airpay_classroom/programs/learningpath; consider per-tenant tag collections | 2 days | **P1** |
| `custom_category` | Verify Moodle-core categories cover tenant separation via top-level structure | 1 day audit | P2 |

**Total recovery effort: ~3 weeks of focused dev** to restore meaningful parity for these unmapped plugins.

### Highest-priority items

1. **`local_airpay_location` (3 days, P1)** — Without it, every ILT invitation has free-text venue, no room capacity, no reuse. This affects every classroom session run in Airpay's office network.

2. **`local_airpay_groups` (1 week, P1)** — Without it, admins cannot create tenant-scoped user groups; cohort operations require site-admin elevation, which leaks cross-tenant data.

3. **Tag area wiring (2 days, P1)** — Wire airpay_classroom/programs/learningpath into Moodle tag system. Restores tag-driven discovery without writing a full local_tags rewrite.

### Items that may not need rebuilding

- **`local_custom_category` (P2)** — Likely already covered by Moodle-core categories + tenant convention. Verify and close.
- **`local_search` (P1)** — Mostly covered by `local_airpay_catalog`; only the global-multi-module-search box is missing. Optional.

### Items where Moodle-core is the better answer

- **`local_forum`** — Moodle-core `mod_forum` is more mature. Wrap with airpay_community if you want a top-level entry; otherwise embed forums inside per-tenant Community courses.

### Items where the gap is substantive

- **`local_groups` + `local_location` + `local_tags` wiring** — these together represent ~3 weeks of work and are needed for parity. None has a free Moodle-core substitute.

**Recommendation to Nitin:** prioritise `local_airpay_location` first (smallest scope, highest visibility on every ILT invitation), then `local_airpay_groups`, then tag-area wiring across existing airpay plugins. Defer `local_airpay_community` and `local_airpay_custom_category` until learner-engagement metrics show demand.
