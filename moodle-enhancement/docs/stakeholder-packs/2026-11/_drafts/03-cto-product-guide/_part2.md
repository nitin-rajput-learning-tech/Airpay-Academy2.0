## 5. Feature catalogue, part A: learning operations

Sections 5 and 6 describe every feature domain of Sentientia LMS. Section 5 covers what learners, managers, trainers and Learning and Development (L&D) administrators use to run training day to day. Section 6 covers skills, engagement, communications, analytics, content creation, assessment integrity and external trainers. Appendix A lists every plugin and Appendix B every feature flag; this catalogue explains what the plugins do together.

### 5.1 How to read the catalogue

#### 5.1.1 Status labels

Every feature carries one of the six status labels of this pack. The labels are applied with one rule, taken from the fact base (`_facts/00-FACTS-INDEX.md` section 1):

| Situation in the repository | Label |
|---|---|
| Code is in commit `af6e65c0e` (the code on UAT) and no default-OFF master switch hides it | **Available (UAT)** |
| Code is in that commit, but its master switch defaults OFF | **Built (behind a feature flag, off by default)** |
| Code changed after that commit (commits `4a78b8f82`, `76e8ec572` and the uncommitted work of 20 November 2026) | **Built (verified on development)**, plus "behind a feature flag, off by default" where a flag gates it |
| Code started, not complete | **In progress** |
| ADR, plan, or a flag that is registered but read by no code | **Planned** |
| Considered and rejected | **Not planned** (with the reason) |

Which flags are switched ON on UAT is a runtime setting held in the UAT database, not in the repository. It is **to be confirmed** from the UAT Switchboard (owner: Head of Learning & Development). Where this catalogue says "behind a flag", it describes the registered default, not the UAT state. Sentientia is not live; every feature below runs on UAT or on development machines with test data imported from a production backup.

#### 5.1.2 Personas

Personas follow the persona matrix in `_facts/20-flags-roles.md` section 5.2: Learner (Airpay, Public or ZEEA tenant), Line manager, Internal trainer, External trainer, Course author, L&D administrator (tenant administrator), Compliance officer, Site administrator and Platform L&D (cross-tenant). "Guest" means a visitor who has not signed in. A tenant administrator always works inside one tenant; only a site administrator or a holder of `local/sentientia_platform:crosstenant` works across tenants (ADR-031).

#### 5.1.3 Layout of each domain

Each domain has the same parts: a feature table (feature, persona, what it does, status); "How it works" (plugins, classes, tables, tasks and events); "Configuration and flags"; a scenario; and "Gaps and full potential". Plugin names are shortened: `courses` means `local_sentientia_courses` unless another prefix is given. Paths are relative to `moodle-enhancement/` unless they start with `theme/`, `admin/` or `scripts/`.

### 5.2 Learning delivery and course experience

The learner's day-to-day experience: the app shell, the dashboard, the course page, the activities inside a course, deadlines, exams and personal tools. The base is stock Moodle 5.3 LTS course delivery; Sentientia adds the shell, the dashboard, deadline handling and the surrounding services.

#### 5.2.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | App shell and role-aware navigation | All signed-in personas | One sidebar, top bar and content frame for every page. The sidebar shows different items per persona (for example Manage Users, Classrooms and Compliance for an L&D administrator; My Courses, Catalog, My Skills and Certificates for a learner). Phones get a bottom bar: Home, My Learning, Search, Me | Available (UAT); the Programs link in the L&D sidebar (theme 1.0.73-beta) is Built (verified on development) |
| 2 | Command palette | Power users | Keyboard command palette (Cmd+K) for navigation | Planned. The flag `ux.commandPalette.enabled` is registered with default ON, but no code reads it and no palette exists yet |
| 3 | Learner dashboard (`/my/`) | Learner, Line manager, Internal trainer | Course cards with progress, a completion ring, a compliance overview widget, and a "Recommended for you" rail. Courses the user teaches are listed separately under "Courses you teach" and kept out of the learner figures | Available (UAT) |
| 4 | Administrator dashboard | L&D administrator, Site administrator | Enrolment trend chart, course distribution chart, system health and user analytics sections on the same `/my/` page | Available (UAT) |
| 5 | Course page and deadline banner | Learner | Course layout in the shell; the banner shows "Due in N days" or "Overdue by N days" when the course has a completion period counted from enrolment | Available (UAT) |
| 6 | Learning activities | Learner, Internal trainer | Stock Moodle 5.3 activity modules, for example SCORM packages, quizzes, books and assignments. Sentientia builds no activity module of its own; it adds one quiz access rule (section 6.6) | Available (UAT) (base Moodle 5.3) |
| 7 | My courses | Learner | A list of the learner's courses (`local/sentientia_catalog/mycourses.php`) | Available (UAT) |
| 8 | Deadline reminders and overdue escalation | Learner, Line manager | A daily task e-mails learners before a course deadline (for example 7, 3 and 1 days before). A second daily task e-mails the learner's supervisor after the deadline passes (1, 7 and 14 days after) | Available (UAT) |
| 9 | Online exams | L&D administrator, Learner | Exam management over the Moodle quiz: exam code, attempt window, mastery score, roster and enrolment, reminders and overdue notices; overview, attempts, roster and analytics tabs | Available (UAT) |
| 10 | Course ratings and reviews | Learner, L&D administrator | Star ratings on courses; an item reviews page for administrators | Available (UAT) for display; interactive star widget, review list and like/dislike counts are Built (behind a feature flag, off by default) |
| 11 | Calendar sync | Learner | A personal calendar subscription address (ICS feed, the iCalendar file format) carrying course deadlines, classroom sessions and exam close dates; the learner can regenerate the address | Built (behind a feature flag, off by default) |
| 12 | AI learning assistant (chat drawer) | Learner | A chat drawer that answers questions about the learner's courses, deadlines and progress, limited to 20 questions per user per day | Available (UAT) as a drawer. With no provider key configured (the shipped state) it replies that it is not configured; live model answers need the budget and key (see Part 4, section 13) |
| 13 | Agentic copilot | Learner | An assistant mode that proposes actions (recommend content, enrol, book a classroom seat). The platform checks, executes and audits each action; the model never executes one | Built (behind a feature flag, off by default) |
| 14 | AI course recommendations | Learner, L&D administrator | Ranks courses for a learner from a candidate list, with one line of reasoning each; shown in a dashboard block | Built (behind a feature flag, off by default); mock output by default |
| 15 | Languages | All | Five language packs: English, Hindi, Kannada, Marathi, Swahili. Hindi plugin strings are at parity with English; Kannada, Marathi and Swahili cover 81% of plugin strings | Available (UAT); the in-shell language switcher is Built (behind a feature flag, off by default) |
| 16 | Dark mode | All | Dark colour scheme for the shell | Built (behind a feature flag, off by default) |
| 17 | Progressive Web App (PWA) | Learner | Installable app shell with a service worker, an offline page and web push reminders | Built (behind a feature flag, off by default) |
| 18 | xAPI statements and learning record store | Integrator, L&D administrator | Emits Experience API (xAPI) statements for course completion, quiz submission, module views and logins; an internal statements endpoint; cmi5 session tracking | Built (behind a feature flag, off by default) |
| 19 | Native mobile app and learner mobile API | Learner | Purpose-built learner API and native app | Planned (ADR-040, Proposed) |

#### 5.2.2 How it works

- **Shell and navigation.** `theme/sentientia/` is a standalone theme (no parent theme). `classes/output/core_renderer.php` builds the shell from `templates/shell.mustache`, `sidebar.mustache`, `topbar.mustache` and `mobile_bottom_nav.mustache`. `classes/sidebar_navigation.php` decides the sidebar items per persona from capabilities and the user's tenant.
- **Dashboard.** `layout/dashboard.php` and `templates/dashboard.mustache`. Helper classes in `theme/sentientia/classes/local/`: `teaching_courses.php` separates taught courses from learning; `dashboard_compliance.php` feeds the compliance widget; `course_recommendations.php` builds the fallback rail (newest courses in the categories of the learner's courses, filtered by tenant). The compliance widget appears only for user types that carry compliance duties (user-type model, ADR-017).
- **Deadline banner.** `classes/local/enrolment_countdown.php` reads the course's completion period (the BizLMS course column `open_coursecompletiondays`) and the learner's latest active enrolment date.
- **Reminders.** `local_sentientia_courses\task\course_reminder` and `\course_overdue` (daily). The overdue task writes to the learner's supervisor (`user.open_supervisorid`), not to the learner.
- **Exams.** `local_sentientia_exams` (2 tables, `exam_reminder` and `exam_overdue` tasks, 3 web-service functions, a BizLMS exams importer). The plugin wraps `mod_quiz`; it has no integrity setting of its own today.
- **Assistant.** `local_sentientia_assistant`: `classes/ai_client.php` (chat), `classes/agent/` (copilot loop and tools `enrol_course`, `book_ilt_session`, `recommend_content`), tables `local_sentientia_chat_log`, `_chat_cache`, `_agent_audit`. Model calls go through the AI gateway `local_sentientia_ai` when gateway routing is ON (Part 4, section 12.5.3).
- **Calendar.** `local_sentientia_calendar`: per-user token table, `ics.php` feed, `purge_old_tokens` task (ADR-013, Accepted).
- **PWA.** `local_sentientia_pwa`: `manifest.php`, `sw.php`, `offline.html`, VAPID-signed web push (RFC 8292). With the flag OFF, `sw.php` serves a worker that unregisters itself.
- **xAPI.** `local_sentientia_xapi`: observers on `course_completed`, `attempt_submitted`, `course_module_viewed` and `user_loggedin`; 4 tables; endpoint `lrs/statements.php`.

#### 5.2.3 Configuration and flags

| Flag or setting | Default | Effect |
|---|---|---|
| `ux.commandPalette.enabled` | ON | Reserved: no code reads it (command palette Planned) |
| `ux.languageSwitcher.enabled` | OFF | Language switcher in the sidebar |
| `ux.darkMode.enabled` | OFF | Dark mode available |
| `sentientia.dashboard.skillsrecs.enabled` | OFF | Skills-first recommendations on the dashboard (otherwise the fallback rail) |
| `sentientia.ratings.widget`, `.reviews`, `.reactions` | OFF | Interactive stars, review list, like/dislike counts |
| `sentientia.calendar_sync.enabled` | OFF | Master switch for the ICS feed; the three source flags (courses, classroom, exams) default ON but have no effect while the master is OFF |
| `sentientia.calendar_sync.oauth.enabled` | OFF | Two-way calendar sync with Microsoft 365 and Google (second phase of ADR-013) |
| `ai.assistant.enabled` | ON | Chat drawer; plugin setting `enabled` also defaults on |
| `sentientia.assistant.agentic.enabled`, `.agentic.live_api` | OFF | Copilot and its live model calls |
| `sentientia.recommendations.enabled`, `.live_api`, `.auto_cron` | OFF | Recommendations; `auto_cron` has no scheduled task behind it yet. The platform flag `ai.recommendations.enabled` (OFF) is registered but no code reads it |
| `sentientia.pwa.enabled`, `.install.enabled`, `.push.enabled`, `.push.reminders`, `.push.overdue` | OFF | PWA, install prompt, push channel and push types |
| `sentientia.xapi.enabled`, `.emit_login`, `.emit_module_view`, `.lrs_endpoint_enabled`, `.cmi5_enabled` | OFF | xAPI subsystem |
| Course field "completion days" | per course | Drives the banner, reminders and overdue escalation |

#### 5.2.4 Scenario

A learner in the Airpay tenant signs in on a phone. The bottom bar opens "My Learning". The dashboard shows two mandatory courses, one with "Due in 3 days" on its banner; that morning the learner also received the 3-day reminder e-mail. The learner opens the SCORM course, completes it, and the completion ring updates. Had the deadline passed, the learner's supervisor would have received the overdue e-mail one day later.

#### 5.2.5 Gaps and full potential

- Live AI answers in the assistant wait for the AI budget and a provider key; until then the drawer is visible but cannot answer (owner: Head of Learning & Development with Finance).
- The assistant's chat client and the copilot carry their own HTTP code in addition to the gateway path; consolidating them behind the gateway is a prerequisite for the offline-model work (Part 4, section 12.5.3).
- Recommendations have no scheduled refresh; the `auto_cron` flag is reserved until a task exists.
- Kannada, Marathi and Swahili strings were reviewed by AI back-translation only, with no native-speaker review.
- Full potential: the PWA, push reminders, calendar sync and xAPI are code-complete and can be switched on per tenant without a deploy once tested on UAT.

### 5.3 Catalogue, storefront, cart and payments

How courses are found and bought. Internal employees browse a tenant catalogue; the Public and ZEEA tenants also buy courses through a cart and the Airpay payment gateway, with Goods and Services Tax (GST) documents.

#### 5.3.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Tenant catalogue | Learner | Course tiles with search, category and tag filters; shows only the courses the learner's tenant may see. A signed-in user whose tenant cannot be resolved sees nothing (fail closed, ADR-031) | Available (UAT) |
| 2 | Course detail page | Guest, Learner | Course page reachable without sign-in; offers add to cart, enrol, or request enrolment as applicable | Available (UAT) |
| 3 | Public storefront | Guest | Guest course search with sort (Popular, Newest, A to Z), 12 courses per page, price or "Free", details and add-to-cart; a "Popular picks" rail | Available (UAT); the storefront restyle flag `sentientia.catalog.public_lxp.enabled` is registered default ON while its description says OFF, a registry inconsistency to be corrected (owner: platform engineer) |
| 4 | Featured courses | L&D administrator, Learner | Administrators pick featured courses; a featured widget shows them | Available (UAT) |
| 5 | Course type labels | Learner | Shows the course type on the card from the imported course-type list | Built (behind a feature flag, off by default) |
| 6 | Cross-tenant library: share and request | L&D administrator, Site administrator, Learner | A tenant administrator can share (push) a course to another tenant, or browse the Airpay library and request (pull) a course. Approval of a share request is a site-administrator capability | Available (UAT); both flags default ON |
| 7 | Free one-click enrolment | Learner (internal tenant) | One-click self-enrolment in free courses for employees | Built (behind a feature flag, off by default) |
| 8 | Cart and checkout | Learner (Public or ZEEA tenant) | Cart, billing details, GST-inclusive pricing, payment on the Airpay hosted payment page, return and callback handling, order history | Available (UAT). Merchant configuration on UAT is to be confirmed (owner: DevOps lead with the Airpay payments team) |
| 9 | Storefront basket hand-off | Guest, Learner | Hands the storefront basket to the order cart | Built (behind a feature flag, off by default) |
| 10 | Moodle payment gateway `paygw_airpay` | Learner, L&D administrator | Airpay as a gateway for Moodle's own payment subsystem (payment accounts, fee-based enrolment) | Available (UAT) |
| 11 | Payment receipts | Learner | A receipt page and PDF for every paid order, linked from the order history | Built (behind a feature flag, off by default) |
| 12 | GST tax invoices and credit notes | Learner, Finance (via L&D administrator) | Numbered tax invoices and credit notes with the Central, State or Integrated GST split (CGST, SGST, IGST), company GST number and service code (SAC); a task issues due documents | Built (behind a feature flag, off by default) |
| 13 | E-invoice reference | Finance (via L&D administrator) | A page where Finance records the Invoice Reference Number (IRN) obtained from the government portal. There is no portal connection | Built (behind a feature flag, off by default) (part of the GST document set) |
| 14 | Order administration | L&D administrator | All orders, refunds, price setting, daily sums with CSV export | Available (UAT) |
| 15 | Imported BizLMS orders and credits | L&D administrator, Finance | Read-only views of the order history and credit bookings imported from BizLMS (ADR-032). Sentientia does not pay out, honour or write off imported credit balances; that finance decision is open | Built (behind a feature flag, off by default) |
| 16 | Subscriptions | Learner, L&D administrator | Subscription enrolment with a pending, active, suspended and cancelled life cycle; course scope implemented, category and all-access scopes record state only | Built (behind a feature flag, off by default). Recurring billing through Airpay is Planned: `paygw_airpay` has no subscription code, and ADR-023 is Proposed |
| 17 | Third-party content marketplace | L&D administrator, Learner | Adapters for Go1, Udemy Business, Coursera and Skillsoft (plus a mock) sync provider catalogues and map items to skills | Built (behind a feature flag, off by default). Provider contracts and credentials are to be confirmed (owner: Head of Learning & Development) |

#### 5.3.2 How it works

- **Catalogue.** `local_sentientia_catalog` (no tables): `classes/catalog_manager.php` (queries, filters, tenant scoping), `category_manager.php`, `enrolment.php`, `checkout_bridge.php`, `commerce.php`. Pages: `index.php`, `course.php`, `public.php` (no sign-in), `mycourses.php`, `cart.php`.
- **Cross-tenant library.** `local_sentientia_courses`: `share.php`, `browse_airpay.php`, table `local_sentientia_courses_tenant_share`, events `course_share_created`, `_requested`, `_request_approved`, `_request_rejected`, `_withdrawn`. Capability `local/sentientia_courses:approve_request` has no archetype default, so only a site administrator approves until a role is given it.
- **Cart.** `local_sentientia_cart` 1.2.2: 8 tables (order header, history, ledger, credits, invoices, credit notes, document sequence, credit journal), 9 web-service functions, events `order_paid` and `refund_processed`, task `issue_due_documents`.
  - `classes/gateway/gateway_factory.php` resolves `airpay` or `manual` gateways behind one interface, so further gateways can be added.
  - `classes/gateway/airpay_gateway.php`: the buyer pays on the Airpay hosted page; Airpay calls `callback.php`; the callback is checked against an address allow-list (`ip_check.php`, IPv4 and IPv6 ranges) and a signature before the order is marked paid or failed.
  - `classes/gst.php` works in paise (integers) and never recomputes tax from today's rate: every figure comes from what the order recorded when the buyer was charged. `gst_states.php` decides intra-state (CGST plus SGST) or inter-state (IGST) from the supplier state code.
  - `docseq.php` numbers invoices and credit notes per series; `pdf_renderer.php` renders documents; `dev_mask.php` blanks personal data in the money tables when a production snapshot becomes a development database.
- **Moodle payment gateway.** `payment/gateway/airpay/` (`gateway.php`, `airpay_helper.php`, `checksum.php`, one web-service function `paygw_airpay_get_form`). The request and response hash scheme follows the vendor's published scheme and is listed for security review (Part 4, section 13.4).
- **Subscriptions.** `enrol/sentientiasub/classes/subscription_manager.php` is a state machine decoupled from payment; the payment layer would only call `activate()`, `suspend()` and `record_cycle()`.
- **Marketplace.** `local_sentientia_content_market`: 3 tables (items, skill map, sync log) and the `sync_providers` task.

#### 5.3.3 Configuration and flags

| Flag or setting | Default | Effect |
|---|---|---|
| `local_sentientia_cart/enabled_tenants` | `77,177` | Tenants that see the cart (Public and ZEEA); Airpay employees do not buy |
| `local_sentientia_cart/gst_rate` | 18 | GST percentage |
| Merchant id, endpoint, secret, callback address list, company name and address, GST number, supplier state code, SAC code, invoice and credit-note prefixes, series period, retention years | site settings | Payment and tax-document set-up. Secret values are stored as Moodle settings and are never shown in this guide |
| `sentientia.cart.purchase_receipts` | OFF | Receipts |
| `sentientia.cart.gst_tax_invoices` | OFF | Tax invoices and credit notes |
| `sentientia.cart.imported_orders.enabled`, `.imported_credits.enabled` | OFF | Imported BizLMS order and credit views |
| `sentientia.catalog.public_lxp.enabled` | ON | Storefront restyle and "Popular picks" rail |
| `sentientia.catalog.free_oneclick_enrol.enabled` | OFF | One-click free enrolment |
| `sentientia.catalog.storefront_checkout.enabled` | OFF | Storefront basket hand-off |
| `sentientia.catalog.course_type_labels.enabled` | OFF | Course type on cards |
| `commerce.crossTenantShare.enabled`, `commerce.crossTenantRequest.enabled` | ON | Share button and "Browse Airpay Library" |
| `commerce.publicMarketplace.enabled` | OFF | Reserved: no code reads it, and its description names a retired path (to be cleaned up, owner: platform engineer). The public storefront does not depend on it |
| `sentientia.subscriptions.enabled` | OFF | Subscription enrolment |
| `sentientia.content_market.enabled` plus one flag per provider and `.skills_mapping.enabled` | OFF | Marketplace and each adapter |

#### 5.3.4 Scenario

A guest finds a course on the public storefront, signs up through the Public-tenant sign-up form, adds the course to the cart and pays on the Airpay hosted page. Airpay calls back; the callback passes the address and signature checks; the order is marked paid, `order_paid` fires and the learner is enrolled. With the GST flags ON for that tenant, the order history also shows a receipt and a tax invoice.

#### 5.3.5 Gaps and full potential

- Recurring billing, the category and all-access subscription scopes, and the subscription commercial model are open (ADR-023 Proposed; owner: Head of Learning & Development with Finance).
- The e-invoice step is manual by design: Finance records the IRN. A portal integration is not planned in the code.
- What Sentientia does with imported credit balances is a finance decision not yet taken (owner: Finance).
- Full potential: the gateway interface and per-tenant cart enablement let a second customer sell its own courses with its own merchant account and GST settings, once per-customer settings are separated (Part 4, section 12.5.1).

### 5.4 Enrolment, requests and approvals

How people get into courses: administrator enrolment, bulk and audience rules, learner requests with manager approval, and automated provisioning.

#### 5.4.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Course enrolment administration | L&D administrator | Enrolled-users view, CSV enrolment, bulk unenrolment, CSV export per course | Available (UAT) |
| 2 | User administration and import | L&D administrator | Manage Users, bulk CSV and bulk import, import from the HR system (HRMS), sync-run history, profiles, sample files and export | Available (UAT); imported HRMS sync history is behind a default-OFF flag |
| 3 | Public self-registration | Guest | Public-tenant sign-up with Terms and Privacy acceptance and e-mail confirmation, controlled by a plugin setting | Available (UAT); whether sign-up is enabled on UAT is to be confirmed (owner: Head of Learning & Development) |
| 4 | Enrolment requests | Learner, Line manager, L&D administrator | A learner requests a course or a learning path; the request goes to an approver; the approver approves or rejects with a reason; on approval the learner is enrolled. Pages: My requests, Pending approvals, All requests | Available (UAT) |
| 5 | Approval routing | System | Route 1: the requester's current direct supervisor. Route 2: the course owner (custom course field). Route 3: the default approver from settings. Holders of the override capability can re-route | Available (UAT) |
| 6 | Escalation and expiry | System | Every 15 minutes a task escalates overdue pending requests to the next approver tier and expires very old ones | Available (UAT) |
| 7 | "Request enrolment" button in the catalogue | Learner | Shows the request button (or "pending") on catalogue cards | Built (behind a feature flag, off by default) |
| 8 | Manager approvals and training allocation | Line manager | My Team dashboard with pending approvals, allocation of training to direct reports, member view and team export | Available (UAT) |
| 9 | Audience-based bulk enrolment | L&D administrator | Enrol everyone matching filters (designation, region, location, employment type, grade, HRMS role) into a learning path or program, inside the administrator's tenant, with a maximum audience size | Available (UAT) for paths and programs; classroom audience enrolment is Built (behind a feature flag, off by default) |
| 10 | Joiner auto-enrolment | System | Enrols new joiners into mandatory courses on user creation or update | Built (behind a feature flag, off by default) |
| 11 | HRMS joiner-mover-leaver feed | System | KeKa HRMS inbound webhook and scheduled reconciliation (ADR-029) | Built (behind a feature flag, off by default) |
| 12 | SCIM provisioning | Identity provider (integrator) | SCIM 2.0 Users and Groups for enterprise identity providers; delete is a soft suspend | Built (behind a feature flag, off by default) |
| 13 | Course audience and applicability model | L&D administrator | Two switches per course, one platform resolver and standing assignment | Planned (ADR-037, Proposed) |

#### 5.4.2 How it works

- **Requests.** `local_sentientia_request` 1.6.4: `classes/request_manager.php` (submit, decide, notify; item types course and path), `classes/approver_routing.php` (read-only routing rules, shared with the BizLMS importer so imported pending requests route the same way), `classes/task/escalate_overdue.php` (every 15 minutes), `classes/catalog_cta.php` (button states none, request, pending), 6 web-service functions.
- **Line manager identity.** A line manager needs no Moodle role. `local_sentientia_manager\team_manager::can_manage()` admits a site administrator, a holder of `local/sentientia_manager:view`, or anyone with at least one direct report (BizLMS field `open_supervisorid`, read through the organisation seam of ADR-020). `approval_manager.php` holds the approval and allocation life cycle.
- **Audience enrolment.** `local_sentientia_learningpath\path_audience_enroller` and `local_sentientia_programs\program_audience_enroller` share one filter shape and tenant rules.
- **Users.** `local_sentientia_users` 2.10.0: pages `index.php`, `bulk_csv.php`, `bulk_import.php`, `bulk_hrms.php`, `sync_runs.php`, `signup.php`; task `hrms_sync` (registered disabled). Every write that names another user checks the target's tenant (`tenant::require_same_tenant_user()`, ADR-031).
- **Lifecycle.** `local_sentientia_lifecycle`: observers on `user_created` and `user_updated`, task `compliance_check`.

#### 5.4.3 Configuration and flags

| Flag or setting | Default | Effect |
|---|---|---|
| Request plugin default approver and routing settings | site settings | Route 3 target |
| `sentientia.request.catalog_button` | OFF | Request button in the catalogue |
| `sentientia.request.imported_history` | OFF | Imported BizLMS requests in the request pages and approver badge |
| `sentientia.classroom.bulk_enrol_audience` | OFF | Audience enrolment for classrooms |
| `sentientia.lifecycle.autoenrol.enabled` | OFF | Joiner auto-enrolment |
| `sentientia.hrms.webhook.enabled`, `sentientia.hrms.reconcile.enabled` | OFF | KeKa webhook and reconciliation |
| `sentientia.api.enabled`, `sentientia.api.scim.enabled` | OFF | Public API and SCIM |
| `sentientia.users.imported_sync_history` | OFF | Imported HRMS sync history |

#### 5.4.4 Scenario

A learner requests a data-protection course from the catalogue. The routing rules find the learner's direct supervisor, who sees the request on the My Team dashboard and approves it with a comment; the learner is enrolled and notified. Had the supervisor not acted, the 15-minute escalation task would have moved the request to the next approver tier when it became overdue.

#### 5.4.5 Gaps and full potential

- Several imported BizLMS path settings (approval required, self-enrolment) are stored but not enforced (section 5.6).
- The audience model of ADR-037 would replace today's per-feature audience filters with one resolver used by courses, compliance, paths, programs and classrooms (Planned).
- Full potential: with SCIM and the HRMS feed switched on, joiners, movers and leavers flow in without CSV files, and joiner auto-enrolment closes the loop to mandatory training.

### 5.5 Compliance (mandatory courses, snapshot, reports, team scope)

Mandatory and statutory training: which courses are mandatory, who must complete them, who is late, and evidence for statutory returns.

#### 5.5.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Compliance course list | L&D administrator | Mark courses as compliance courses with a completion deadline in days (default 30); exclude or include individual users with a reason | Available (UAT) |
| 2 | Compliance snapshot | System | An hourly task rebuilds a per-user, per-course status table. Stored statuses: completed, in progress, overdue, not started, not enrolled, exempted | Available (UAT) |
| 3 | "Not applicable" display state | Compliance officer, L&D administrator | The matrix shows "not applicable" where a course does not apply to a person (another tenant's course, or a cohort the person is not in); never stored | Built (verified on development) |
| 4 | Auto-enrolment and escalation | System | The snapshot task can enrol people into compliance courses that apply to them and e-mail managers about overdue staff | Available (UAT) |
| 5 | Compliance dashboard | Compliance officer, Line manager, L&D administrator | KPI cards, a user-by-course matrix, a department scorecard, a defaulters list, a manager report, drill-down through the organisation tree, and CSV export for statutory returns | Available (UAT) |
| 6 | Viewer scope | All of the above | Site administrator: every tenant. Compliance administrator or holder of the export capability: own tenant. Line manager: own reporting tree (direct and indirect reports). Anyone else, or a user with no resolvable tenant: nothing | Available (UAT) |
| 7 | Compliance block and dashboard widget | Learner, L&D administrator | Compliance summary on dashboards | Available (UAT) |
| 8 | Compliance e-mails | Learner, Line manager | Enrolment welcome, start and halfway reminders, deadline warning, overdue alert and weekly escalation templates, driven by e-mail rules (section 6.3) | Available (UAT) |
| 9 | Deadline lines in the daily digest | Learner | Adds up to two "Due in N days" lines for incomplete compliance courses to the daily learning digest | Built (behind a feature flag, off by default) |
| 10 | Compliance course pinning and clean-up tool | Site administrator | Command-line tool that pins named compliance courses to a tenant, restricts one to a cohort and, optionally, removes enrolments the old auto-enrolment made for people outside the course's tenants. Dry run by default; it prints counts, never names | Built (verified on development) |
| 11 | Recertification cycles | L&D administrator | Periodic reset of completions for recertification (section 5.6) | Available (UAT); the daily rules task is behind a default-OFF flag |

#### 5.5.2 How it works

- **Engine.** `local_sentientia_compliance_report\compliance_engine` (`rebuild_snapshot()`, `get_compliance_matrix()`, `get_department_scorecard()`, `get_defaulters()`, `get_summary_kpis()`, `get_manager_report()`, `get_reporting_tree()`). Tables: `local_compliance_courses`, `local_compliance_snapshot`, `local_compliance_exemptions`, `local_compliance_email_log` (this plugin keeps the older `local_compliance_*` prefix).
- **Applicability.** `course_audiences()`, `cohort_members()`, `course_reaches_tenant()` and `row_applies()` decide whether a course applies to a person: the course's own tenant, tenants it is shared to, and an optional cohort restriction.
- **Task.** `refresh_snapshot` runs at minute 0 of every hour. The KPI cache is purged on every rebuild and every configuration change.
- **Scope.** `classes/viewer_scope.php` (levels site, tenant, team, none) and `classes/permission.php` (the tenant administrator role is looked up by shortname, never by numeric id). Since 18 November 2026, `moodle/site:viewreports` alone no longer opens the tenant matrix.
- **Joiners.** `local_sentientia_lifecycle` (flag OFF) can enrol joiners into mandatory courses on account creation.

#### 5.5.3 Configuration and flags

| Setting or flag | Default | Effect |
|---|---|---|
| `local_sentientia_compliance_report/auto_enrol` | ON | Snapshot task enrols applicable people into compliance courses |
| `local_sentientia_compliance_report/manager_escalation` | ON | Escalation e-mails to managers |
| `local_sentientia_compliance_report/reminder_days` | 7 | Days before the deadline for the reminder |
| `local_sentientia_compliance_report/weekly_report` | OFF | Weekly report e-mail |
| Deadline days per compliance course | 30 | Completion window |
| `sentientia.notifications.digest_deadlines.enabled` | OFF | Deadline lines in the digest |
| No feature flag | n/a | The compliance plugin replaces a BizLMS feature at parity and has no master flag; the exemption from the flag rule is to be confirmed (owner: Engineering lead) |

#### 5.5.4 Scenario

A compliance officer for the Airpay tenant opens the dashboard at month end. The KPI cards show completion against the anti-money-laundering and information-security courses; the department scorecard shows one department below target. The officer drills down to that department, sees the defaulters, and exports the matrix as CSV for the statutory return. A line manager opening the same page sees only their own reporting tree; a ZEEA administrator sees only ZEEA.

#### 5.5.5 Gaps and full potential

- The old auto-enrolment set deadline-based enrolment end dates and enrolled some people outside a course's tenants; the clean-up tool exists (Built, verified on development) and its run on UAT is an owner decision (owner: Head of Learning & Development).
- The `complianceofficer` role exists on UAT but no repository code creates it; its origin and capability list are to be recorded (owner: platform engineer).
- Full potential: with the ADR-037 audience model (Planned), compliance applicability, enrolment and reporting would share one resolver with paths and programs.

### 5.6 Learning paths, certification programs and recertification

Structured journeys: ordered sets of courses (learning paths), multi-level certification programs, and recertification cycles that reset completion after a period.

#### 5.6.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Learning paths | L&D administrator | Create a path of courses with a sort order and a mandatory flag per course; assign and enrol users; export; a path completes when all its mandatory courses are complete | Available (UAT); imported-history and interface fixes after the UAT commit are Built (verified on development) |
| 2 | My learning paths | Learner | Learner page listing the learner's paths and progress | Built (behind a feature flag, off by default) |
| 3 | Adaptive learning journeys | Learner, L&D administrator | For paths in adaptive mode, the engine reacts to quiz scores, completion velocity and skills-gap signals: skip mastered content, accelerate or add remediation; every decision is logged | Built (behind a feature flag, off by default) |
| 4 | Certification programs | L&D administrator | Multi-level programs: levels, courses per level, a completion rule per level (every mandatory course, or any one mandatory course), trainers, enrolment, roster | Available (UAT) |
| 5 | My programs | Learner | Learner page with active programs, level progress, completion dates and program logo | Built (behind a feature flag, off by default) |
| 6 | Imported program history | L&D administrator | "Completed on" column and program logo from the BizLMS import | Built (behind a feature flag, off by default) |
| 7 | Recertification rules | L&D administrator | Per-course rules that reset a learner's completion after a period, with history, evidence archive and history detail pages | Available (UAT) for rule management; the daily run (03:15) and the evidence view are Built (behind a feature flag, off by default) |
| 8 | Learning-path revisions | L&D administrator | Change a live path without losing learner history | Planned (ADR-039, Proposed) |
| 9 | Training programs with ordered stages | L&D administrator, Internal trainer | A classroom with ordered stages and a companion course | Planned (ADR-036, Proposed) |

#### 5.6.2 How it works

- **Paths.** `local_sentientia_learningpath` 1.9.4: `classes/path_manager.php`, 5 tables (`local_sentientia_learningpath`, `_courses`, `_users`, `local_sentientia_lp_course_status`, `local_sentientia_lp_adaptive_log`), 12 web-service functions, BizLMS importer.
- **Adaptive engine.** `classes/adaptive/journey_engine.php` is the single entry point. It runs from the observer on `mod_quiz\event\attempt_submitted` and from the daily `adaptive_sweep` task; it reads `quiz_signal_reader.php`, `velocity_calculator.php` and `skills_gap_feed.php`. Every public method first checks `sentientia.learningpath.adaptive.enabled` and returns at once when it is OFF.
- **Programs.** `local_sentientia_programs` 1.9.2: `program_manager.php`, `learner_view.php`, 7 tables (programs, levels, level courses, users, level completions, trainers, trainer feedback), 12 web-service functions, an observer on `course_completed` that advances levels.
- **Recertification.** `local_sentientia_recompletion` 1.2.2 (derived from the community `local_recompletion` plugin): `recompletion_engine.php`, `evidence_archiver.php`, `history_reader.php`, 3 tables (rules, history, archive), task `run_rules` at 03:15. A new completion after a reset earns a new certificate (section 5.9).

#### 5.6.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.learningpath.learner_paths.enabled` | OFF | My learning paths page |
| `sentientia.learningpath.adaptive.enabled` | OFF | Adaptive engine |
| `sentientia.programs.learner.enabled` | OFF | My programs page |
| `sentientia.programs.history.enabled` | OFF | Imported program history |
| `sentientia.recompletion.run_rules` | OFF | Daily recertification task |
| `sentientia.recompletion.evidence_view` | OFF | Evidence view |

#### 5.6.4 Scenario

L&D builds a three-level "Merchant onboarding" certification program. Level 1 completes when every mandatory course is done; level 2 completes on any one of two elective courses. An administrator enrols all sales staff in one region with the audience filter. As each course completes, the program observer records level completions; the learner sees progress on My programs once that page is switched on.

#### 5.6.5 Gaps and full potential

- Three settings imported from BizLMS paths (sequential order, approval required, self-enrolment) are stored but not enforced (`db/install.xml` comments). Enforcing them is a product decision (owner: Head of Learning & Development).
- The learner pages for paths and programs are built but OFF; until they are ON, learners reach paths and programs through their courses.
- Full potential: adaptive journeys combined with the skills-gap feed (section 6.1) give each learner a path that skips mastered content.

### 5.7 Classroom and live sessions

Instructor-led training (ILT): classrooms with sessions, rosters and attendance; and live engagement (polls and quizzes) during sessions.

#### 5.7.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Classrooms and sessions | L&D administrator, Internal trainer | Classrooms with linked courses, trainers, locations, capacity and dated sessions; roster management | Available (UAT) |
| 2 | Attendance | Internal trainer, External trainer | Mark attendance per session; attendance drives classroom completion and the `classroom_completed` event | Available (UAT) |
| 3 | Waiting list with auto-promotion | Learner, L&D administrator | When a classroom is full, learners join a queue; when a seat frees, the first in the queue is enrolled and notified | Available (UAT) |
| 4 | My sessions and calendar file | Learner | The learner's sessions with a downloadable calendar (ICS) file per classroom | Available (UAT) |
| 5 | QR attendance | Internal trainer, Learner | A projector page shows a QR code; learners scan it to mark attendance | Built (behind a feature flag, off by default) |
| 6 | Online meeting links | Internal trainer | Store a validated http(s) meeting link and recording link per session (for example Teams or Zoom) | Available (UAT). Creating meetings and importing attendance through the Teams or Zoom APIs is not built (Planned) |
| 7 | Trainer own-classroom scope | Internal trainer | Internal trainers see only the classrooms they train | Built (verified on development), behind a flag, off by default |
| 8 | Trainer dashboard block | Internal trainer | Upcoming sessions and classrooms the trainer is listed on | Available (UAT) |
| 9 | Imported classroom history | L&D administrator | Readers for classroom history imported from BizLMS | Built (behind a feature flag, off by default) |
| 10 | Live engagement | Internal trainer, Learner | Trainer-run live sessions with slides and six question types (multiple choice, word cloud, open ended, rating scale, quiz with live leaderboard, ranking); audience join; real-time results; profanity filter; optional anonymous join | Built (behind a feature flag, off by default) |

#### 5.7.2 How it works

- **Classroom.** `local_sentientia_classroom` 1.12.0: `session_manager.php`, `waitlist_manager.php` (statuses waiting, promoted, removed), `ics_builder.php`, `url_rule.php`, `classroom_audience_enroller.php`, BizLMS importer; 8 tables; 15 web-service functions; event `classroom_completed`, which the evaluation plugin listens to (section 5.8).
- **QR attendance.** Pages `qr_attendance.php` (projector) and `qr_scan.php` in `local_sentientia_pages`.
- **External trainers.** When an account is an external trainer (section 6.7), the classroom plugin always limits it to its own classrooms and shows names only, whatever the internal-trainer flag says.
- **Live.** `local_sentientia_live` 0.2.4-alpha: 6 tables (sessions, slides, participants, responses, events, server-sent-event queue), `stream.php` delivering Server-Sent Events (SSE) to audience and trainer screens (ADR-004, Accepted), 5 capabilities (create, run, join, respond, manage all).

#### 5.7.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.classroom.qr_attendance` | OFF | QR attendance entry point |
| `sentientia.classroom.bulk_enrol_audience` | OFF | Audience enrolment |
| `sentientia.classroom.import_history` | OFF | Imported history readers |
| `sentientia.classroom.trainer_own_scope.enabled` | OFF | Own-classroom scope for internal trainers |
| `live.enabled` | OFF | Live engagement master switch |
| `live.realtime.enabled` | ON | SSE push (no effect while `live.enabled` is OFF) |
| `live.questiontype.multichoice`, `.wordcloud`, `.openended`, `.rating`, `.quiz`, `.ranking` | OFF | One per question type |
| `live.allow_anonymous` | OFF | Join without signing in |

#### 5.7.4 Scenario

A two-day branch-operations workshop has 30 seats and 34 nominations; four learners go on the waiting list. One nominee cancels and the first waiting learner is enrolled and notified. On the day, the trainer projects the QR code, learners scan it, and attendance is recorded. Mid-session the trainer runs a live word-cloud question. When the classroom completes, the evaluation plugin sends the reaction survey (section 5.8).

#### 5.7.5 Gaps and full potential

- Meeting creation and attendance import for Teams and Zoom are not built; only links are stored (ADR-036 lists them as later work).
- Live engagement is alpha and OFF by default; it has been exercised on development only.
- Full potential: ordered training stages (ADR-036, Planned) would join classroom, companion course and stage-bound feedback into one program.

### 5.8 Evaluations and surveys

Feedback forms tied to training events, with analysis by the Kirkpatrick model (level 1 reaction to level 4 results).

#### 5.8.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Form builder and templates | L&D administrator | Build evaluations from questions; import and export templates | Available (UAT) |
| 2 | Triggers | System | Assign an evaluation automatically when a course, program or classroom completes; a queue task runs every 15 minutes | Available (UAT) |
| 3 | Assignments and expiry | Learner, L&D administrator | Learners see their evaluations under My evaluations; unanswered assignments expire by a daily task | Available (UAT) |
| 4 | Anonymous responses | Learner | Anonymous evaluations store no respondent | Available (UAT) |
| 5 | Analysis and export | L&D administrator | Response pages, cross-evaluation analysis by Kirkpatrick level, non-respondent list, CSV export | Available (UAT) |
| 6 | Anonymity minimum group size | L&D administrator | Filtered views and CSV breakdowns of protected evaluations are withheld when a group is smaller than the minimum (default 5, floor 3), including protection against working out a small group by subtraction | Built (verified on development) |
| 7 | Learner evaluation history | Learner | A learner's own evaluation history | Built (behind a feature flag, off by default) |
| 8 | Individual response drill-down | L&D administrator | Individual responses pages | Built (behind a feature flag, off by default) |
| 9 | Survey reach: external form links, public no-login links, file uploads | L&D administrator | Wider survey distribution | Planned (ADR-043, Proposed) |

#### 5.8.2 How it works

`local_sentientia_evaluation` 1.18.0: `evaluation_engine.php`, `evaluation_manager.php`, `evaluation_audience_assigner.php`, `anonymity.php`; 6 tables (evaluations, questions, responses, triggers, templates, assignments); tasks `process_triggers` (every 15 minutes) and `expire_assignments` (01:00 daily); observers on `course_completed`, `program_completed` and `classroom_completed`; 8 web-service functions. Each evaluation carries a Kirkpatrick level (1 to 4); the language strings advise a 30 to 90 day delay for levels 3 and 4.

#### 5.8.3 Configuration and flags

| Setting or flag | Default | Effect |
|---|---|---|
| Anonymity minimum group size | 5 (never below 3) | Smallest group shown in a filtered view |
| `sentientia.evaluation.learner_history` | OFF | Learner history page |
| `sentientia.evaluation.response_drilldown` | OFF | Individual responses pages |

#### 5.8.4 Scenario

A classroom of eight completes. The trigger assigns a level 1 reaction survey to all eight; six respond anonymously. An administrator filters results by that classroom: six is at least the minimum of five, so the results show. A filter that would leave three responses is withheld.

#### 5.8.5 Gaps and full potential

- Public no-login surveys and file uploads are Planned (ADR-043).
- Full potential: level 3 and 4 evaluations, linked to the ROI model v2 (section 6.4), would let L&D connect training to business results with traceable figures.

### 5.9 Certificates and verification

Certificate design, issue on completion, a learner gallery and public verification.

#### 5.9.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Certificate templates and issuing | L&D administrator | Template designer, issue and revoke, issue lists, QR codes and verification codes, through the vendored `tool_certificate` plugin | Available (UAT) (UAT runs `tool_certificate` 5.0.12 per the re-vendor record); the repository re-vendor to 5.0.12 is Built (verified on development) |
| 2 | Course certificate on completion | Learner | When a learner completes a course mapped to a template, a certificate is issued automatically, one per completion cycle; a re-completion after a recertification reset earns a new certificate and the old one keeps verifying | Built (verified on development). No feature flag by design: it restores BizLMS behaviour, and a default-OFF flag would cause a regression at cutover |
| 3 | Certificate gallery | Learner | The learner's certificates with Verify and LinkedIn actions; the gallery names the course for course and imported certificates | Available (UAT); the course-naming change is Built (verified on development) |
| 4 | Tenant-scoped template browser | L&D administrator | Template browser limited to the administrator's tenant | Built (behind a feature flag, off by default) |
| 5 | Public verification page | Guest | Anyone with a code (printed QR code, gallery Verify button or LinkedIn link) sees "Verified" or "Not verified" with holder name and certificate name on arrival. The page is excluded from search engines and escapes names | Available (UAT) |
| 6 | Certificate e-mails and health | Learner, Site administrator | Certificate e-mail with the PDF; a dashboard block counts sent, failed and suppressed certificate e-mails over 7 days | Available (UAT) |
| 7 | Expiry notices | Learner | Notification rules "certificate expiring" (before) and "certificate expired" (after) | Available (UAT) |
| 8 | Certificate events outward | Integrator | `certificate.issued` webhook; WhatsApp notification on issue | Built (behind a feature flag, off by default) |

#### 5.9.2 How it works

- **Vendored plugin.** `admin/tool/certificate/` 5.0.12 (supports Moodle 5.0 to 5.3) with three recorded patches: an image-element guard, verification result on arrival, and escaping of names in the verification result (ADR-041 governs vendor patches; records in `docs/core-mods/`). The verification capability `tool/certificate:verify` is allowed to guests by default; a site may revoke it.
- **Issuer.** `local_sentientia_courses\certificate_issuer` (with an observer on `course_completed`). The issue is stored with an empty course id and the course is kept in `local_sentientia_courses_certmap`, so certificates and QR codes survive course deletion and "restore with delete". A core lock per user and course prevents duplicate issues.
- **Gallery and templates.** `local_sentientia_pages/certificates.php` and `certificate_templates.php`.
- **E-mail.** `local_sentientia_emails\certificate_helper`; block `block_sentientia_cert_health`.

#### 5.9.3 Configuration and flags

| Flag or setting | Default | Effect |
|---|---|---|
| Course-to-template mapping | per course | Which template the issuer uses |
| `sentientia.certificate.tenant_scope.enabled` | OFF | Tenant-scoped template browser |
| `tool/certificate:verify` for guests | allowed | Public verification |
| `sentientia.api.webhooks.enabled`, `engagement.whatsapp.enabled` | OFF | Outbound certificate events |

#### 5.9.4 Scenario

A learner completes a mapped course. The issuer issues a certificate with a code and QR code and e-mails it. The learner adds it to LinkedIn from the gallery. A recruiter follows the link and sees "Verified" with the holder and certificate name. A year later a recertification rule resets the course; on re-completion a new certificate is issued and the first one still verifies.

#### 5.9.5 Gaps and full potential

- The Hindi language pack for `tool_certificate` is staged and needs 14 more strings before activation.
- Full potential: the `certificate.issued` webhook lets HR or credential wallets receive certificates as they are issued.

## 6. Feature catalogue, part B: capability, engagement, insight, content and integrity

### 6.1 Skills, skills AI and competency

A skills framework that links roles, courses and people, with AI-assisted extraction and a gap engine, plus talent mobility on top.

#### 6.1.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Skills framework | L&D administrator | Skill catalogue with categories and proficiency level definitions; skills required per role (designation); designation matrix and level definition pages | Available (UAT) |
| 2 | Course-to-skill mapping | L&D administrator | Tag a course as developing a skill at a level | Available (UAT) |
| 3 | My skills | Learner | Self-assessment, skill profile and radar chart, gap list for a target role with courses that close it, skill interests | Available (UAT) |
| 4 | Skill update on completion | System | On course completion the learner's skill records are updated from the course's skill mapping; changes go to a history table | Available (UAT) |
| 5 | Held skills (imported) | Learner | Skills held in BizLMS shown on My skills | Built (behind a feature flag, off by default) |
| 6 | Skills-first dashboard recommendations | Learner | Dashboard recommendations driven by skill gaps | Built (behind a feature flag, off by default) |
| 7 | AI skill extraction with review | Course author, L&D administrator | Paste a role or course description (up to 6,000 words); the model proposes skills with level and evidence; a reviewer accepts them into the taxonomy | Built (behind a feature flag, off by default); mock output by default |
| 8 | Skills-gap engine | L&D administrator, Line manager | A nightly SQL task (02:30) rebuilds a gap feed per person; a gaps page shows it | Built (behind a feature flag, off by default); the latest change to the feed task is Built (verified on development) |
| 9 | Skill-to-business-impact map | L&D administrator | Maps skills to business impact | Built (behind a feature flag, off by default) |
| 10 | Talent mobility | Learner, L&D administrator | Career paths, succession planning, an internal opportunity board and expressions of interest, with an audit table | Built (behind a feature flag, off by default) |
| 11 | Manager-assigned skill levels and peer endorsement | Line manager | Listed as open backlog in the plugin README | Planned |

#### 6.1.2 How it works

- `local_sentientia_skills` 1.7.2: `skills_manager.php`, observer on `course_completed`, 9 tables (`local_sentientia_skill_cats`, `_skills`, `_role_skills`, `_course_skills`, `_skill_levels`, `_user_skills`, `_user_skill_hist`, `_course_levels`, `_skill_interest`), 14 web-service functions.
- `local_sentientia_skillsai` 0.1.5-alpha: `taxonomy_manager.php`, `gap_engine.php`, `impact_manager.php`, `prompt_builder.php` (English and Hindi prompts), `response_parser.php`, 5 tables (`local_sentientia_skai_job`, `_cand`, `_taxonomy`, `_impact`, `_gap`), task `rebuild_gap_feed`. Model calls go through the AI gateway; the gap engine makes no model call.
- `local_sentientia_talent` 1.0.0-beta: 5 tables (`local_sentientia_talent_path`, `_succ`, `_opp`, `_int`, `_audit`).
- The Sentientia Author role (`sentientiaauthor`) holds the extraction and review capabilities.

#### 6.1.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.skills.heldskills.enabled` | OFF | Held skills |
| `sentientia.dashboard.skillsrecs.enabled` | OFF | Skills-first recommendations |
| `sentientia.skillsai.enabled`, `.live_api`, `.gap_engine`, `.impact_map` | OFF | Skills AI master, live model calls, gap engine, impact map |
| `sentientia.talent.enabled`, `.opportunities` | OFF | Talent mobility and the opportunity board |
| `sentientia.content_market.skills_mapping.enabled` | OFF | Map marketplace items to skills |

#### 6.1.4 Scenario

L&D pastes the job description of a merchant-support role into Skills AI and reviews the proposed skills. Accepted skills enter the taxonomy and the role's requirements. Overnight the gap engine computes each support agent's gaps; agents see the courses that close them on My skills, and completing one raises their recorded level.

#### 6.1.5 Gaps and full potential

- Skill levels are self-assessed or course-derived; there is no manager validation yet.
- Full potential: one skills graph feeding adaptive paths, recommendations, the content marketplace, the skill leaderboard and talent mobility.

### 6.2 Gamification and leaderboards

Points, badges, streaks, levels, challenges and leaderboards.

#### 6.2.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Points | Learner | Points for actions: course completed 100, quiz passed 50, perfect quiz 100, daily login 10, 7-day streak 50, 30-day streak 200, first course 150, learning path completed 500 | Available (UAT) |
| 2 | Levels | Learner | Five levels by points: Beginner (0 to 499), Learner (500 to 1,499), Achiever (1,500 to 3,999), Expert (4,000 to 9,999), Master (10,000 and above) | Available (UAT) |
| 3 | Badges and streaks | Learner | Badge awards and daily streak tracking | Available (UAT) |
| 4 | Celebration animation | Learner | Confetti animation on the certificate celebration page | Available (UAT). The flag `engagement.gamification.confetti` (default ON) is registered but no code reads it, so the animation cannot be switched off by flag today |
| 5 | Challenges | Learner, L&D administrator | Time-boxed challenges of four types (course completion, streak, quiz score, custom) with attempts and a challenge leaderboard recomputed by a task | Available (UAT) |
| 6 | Real-time leaderboards | Learner | Quiz, completion and skill-points leaderboards with SSE push and rank-change notifications | Built (behind a feature flag, off by default) |
| 7 | Leaderboard opt-out | Learner | "Hide me from public leaderboards" preference | Built (behind a feature flag, off by default) (the opt-out sub-flag defaults ON but the leaderboard master defaults OFF) |
| 8 | Leaderboard block | Learner | Dashboard block | Available (UAT) as code; it renders only when the leaderboard master flag is ON |

#### 6.2.2 How it works

- `local_sentientia_gamification` 1.0.5-beta: `points_manager.php` (the points table above is the `POINTS` constant), `badge_manager.php`, `leaderboard.php`; 4 tables (points log, badges, user badges, streaks); observers on `course_completed`, `attempt_submitted`, `user_loggedin` and `course_module_viewed`.
- `local_sentientia_challenge` 1.1.6-alpha: `challenge_engine.php`; 3 tables; task `recompute_leaderboard`; 3 observers; 8 web-service functions.
- `local_sentientia_leaderboard` 0.2.3-alpha: 5 tables (boards, entries, opt-outs, events, notification log); tasks `recompute_due_boards` and `purge_old_events`; event and observer `rankings_updated`; `stream.php` (SSE).

#### 6.2.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `engagement.gamification.enabled` | ON | Points, badges, streaks |
| `engagement.gamification.confetti` | ON | Reserved: no code reads it |
| `sentientia.leaderboards.enabled` | OFF | Leaderboards master |
| `sentientia.leaderboards.type.quiz`, `.type.completion`, `.type.skill` | OFF | Board types |
| `sentientia.leaderboards.realtime.enabled`, `.optout.enabled` | ON | SSE and opt-out (effective only with the master ON) |
| `sentientia.leaderboards.notifications.enabled` | OFF | Rank-change notifications |

#### 6.2.4 Scenario

L&D runs a 30-day "Fraud awareness" challenge of type course completion. Learners who finish the three courses within the window climb the challenge leaderboard; each completion adds 100 points and moves some learners from Learner to Achiever.

#### 6.2.5 Gaps and full potential

- The challenge and gamification plugins have no flag registry; they replace BizLMS features at parity, and the exemption from the flag rule is to be confirmed (owner: Engineering lead).
- Point values are code constants, not settings; per-customer point rules are a product-design item (owner: Head of Learning & Development).

### 6.3 Communications: e-mail templates and rules, notifications, WhatsApp and SMS

Every message the platform sends: rule-driven e-mail, in-app notifications and digests, web push, and (in mock mode) WhatsApp and SMS.

#### 6.3.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | E-mail template library | L&D administrator | 20 message templates (account, compliance, enrolment, notification and privacy messages) with shared partials; an editor with preview; per-customer overrides; multi-language | Available (UAT) |
| 2 | E-mail rules | L&D administrator | Rules of 15 types (compliance enrolled, compliance reminder, compliance overdue, deadline approaching, course not started, course incomplete, course completed, course enrolled, learning path enrolled, manager course completed, streak broken, manager nudge, weekly escalation, new course, custom). Each rule has a channel (e-mail, pop-up or both), an audience (learner, manager, administrator, all) and a reminder cadence of up to 10 entries. Event-driven types fire on the event; others run hourly | Available (UAT); the rule-editor fix of 20 November is Built (verified on development) |
| 3 | Delivery log and preferences | L&D administrator, Learner | A log of every e-mail with detail view; per-user notification preferences | Available (UAT) |
| 4 | BizLMS-parity senders | Learner, Line manager | Course enrolment e-mail, learning-path enrolment e-mail, and a completion copy to the learner's supervisor | Built (behind a feature flag, off by default) |
| 5 | Imported e-mail history | L&D administrator | E-mail history imported from BizLMS, with an optional body view | Built (behind a feature flag, off by default) |
| 6 | Smart notification rules | L&D administrator | A second rule engine with 17 rule types, among them deadline approaching, manager nudge, compliance overdue, certificate expiring and expired, classroom feedback pending, learning path stalled, inactive user, low quiz score, monthly summary, training overdue, weekly manager summary and peer completion celebration | Available (UAT); three types (course not started, streak broken, new course) send only with a default-OFF flag |
| 7 | Daily learning digest | Learner | A weekday digest; compliance deadline lines behind a flag | Available (UAT) |
| 8 | Manager nudges | Line manager | Nudge a team member from the notifications pages | Available (UAT) |
| 9 | Web push | Learner, Line manager | Deadline reminders and overdue escalations through the browser | Built (behind a feature flag, off by default) |
| 10 | WhatsApp and SMS | Learner, Line manager | Template messages through a channel router, preferences, send log, and a registry of templates approved under India's Distributed Ledger Technology (DLT) rules for commercial messages | Built (behind a feature flag, off by default) in mock mode only. Live sending is not built: live mode falls back to mock and logs "mocked" |
| 11 | Microsoft Teams channel alerts | L&D administrator | Adaptive card to a Teams channel through an incoming webhook | Built (verified on development); setting-gated, no registry flag (to be confirmed, owner: platform engineer) |

#### 6.3.2 How it works

- `local_sentientia_emails` 1.4.3: `template_manager.php`, `rule_manager.php`, `notification_sender.php`, `email_renderer.php`, `delivery_log.php`, `parity_senders.php`, `tenant_config.php`, `tenant_scope.php`; 4 tables (overrides, rules, log, preferences); tasks `process_rules` (hourly) and `send_path_enrolments` (every 5 minutes); observers on `course_completed` and `user_enrolment_created`; 31 Mustache files (20 messages, 3 partials, 8 management screens).
- `local_sentientia_notifications` 1.5.2: `rule_engine.php` (17 types), `rule_manager.php`, `prefs_manager.php`; tasks `process_rules` and `daily_digest`; 6 web-service functions; Moodle messaging as transport.
- `local_sentientia_whatsapp` 0.4.1-alpha: `channel_router.php`, `whatsapp_client.php`, `sms_client.php`, `dlt_template_registry.php`, `preference_manager.php`, `send_log.php`, `notification_bridge.php`; observers on `course_updated`, `course_completed` and `certificate_issued`. The provider calls are commented-out references, not live code.
- E-mail transport is Moodle core mail with the site's outgoing mail settings.

#### 6.3.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.emails.send_course_enrolment.enabled`, `.send_learning_path_enrolment.enabled`, `.send_manager_completion_copy.enabled` | OFF | Parity senders |
| `sentientia.emails.imported_history.enabled`, `.imported_body_detail.enabled` | OFF | Imported history |
| `sentientia.notifications.smart_rules.enabled` | OFF | Three smart rule types |
| `sentientia.notifications.digest_deadlines.enabled` | OFF | Digest deadline lines |
| `engagement.whatsapp.enabled`, `.reminders`, `.overdue`, `sentientia_whatsapp_content_notifications` | OFF | WhatsApp channel and message types |
| `engagement.sms.enabled` | OFF | SMS channel |
| `sentientia.pwa.push.enabled`, `.push.reminders`, `.push.overdue` | OFF | Web push |

#### 6.3.4 Scenario

L&D creates a "compliance reminder" rule for learners with a cadence of 14, 7 and 1 days, channel e-mail plus pop-up. The hourly task finds learners whose compliance deadline matches a cadence entry and sends the "deadline warning" template in the learner's language; every send appears in the delivery log.

#### 6.3.5 Gaps and full potential

- Two rule engines (e-mail rules and smart notification rules) overlap in rule types; consolidation is a design item (owner: platform engineer).
- WhatsApp and SMS live sending needs a provider choice and DLT template approval before the live branch is built (owner: Head of Learning & Development with Legal).
- Full potential: one router across e-mail, pop-up, push, WhatsApp and SMS with per-user channel preferences, already present in the WhatsApp plugin's channel router.

### 6.4 Analytics: dashboards, ROI, skill gap and predictive indicators

What the platform measures. The analytics are SQL queries and transparent heuristics; there is no machine-learning model.

#### 6.4.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Analytics dashboard | L&D administrator, Line manager | Key performance indicator (KPI) cards for active learners, completions, average score and time on platform; drill-down by tenant, department, category and role; business-unit filter; CSV export | Available (UAT); the year-to-date comparison with the same span last year and the cache lifetimes are Built (verified on development) |
| 2 | Built-in reports | L&D administrator | Four report types: course completion (by user, course and tenant), compliance overview, user activity (logins and access), enrolment trend; saved reports | Available (UAT) |
| 3 | Earlier training records and login days | L&D administrator | Imported BizLMS training transcript report and login-days column | Built (behind a feature flag, off by default) |
| 4 | Custom report builder | L&D administrator | The vendored LearnerScript report builder with report dashboards and tiles | Available (UAT) (vendored, one recorded patch) |
| 5 | Manager team analytics | Line manager | Team dashboard, member view, team performance and export | Available (UAT) |
| 6 | Compliance analytics | Compliance officer | See section 5.5 | Available (UAT) |
| 7 | At-risk indicators | L&D administrator, Line manager | A 0 to 100 risk score per learner from four named, weighted signals: days since last access (0.30), completion-rate gap (0.25), overdue courses (0.25), drop in login frequency (0.20); each score lists its signals | Built (behind a feature flag, off by default). Rule-based, no machine-learning model |
| 8 | Team skill-gap projection | L&D administrator, Line manager | Share of the skills a designation needs that the person's completed courses cover; uses the skills-gap provider when present, otherwise a category proxy | Built (behind a feature flag, off by default) |
| 9 | Training ROI card (v1) | L&D administrator | ROI% = (benefits minus costs) divided by costs. Benefits: completions times hours saved times hourly rate, plus on-time completions times penalty avoided. Costs: course development hours, a flat platform cost and learner hours. Every assumption is a site setting | Built (behind a feature flag, off by default). The flag stays OFF until ROI model v2 is built and accepted |
| 10 | ROI model v2 | L&D administrator, Finance | A two-basis cost ledger, frozen monthly facts, one allocation key and traceable figures | Planned (ADR-042, Proposed) |
| 11 | Operations health blocks | Site administrator | Scheduled-task health and certificate e-mail health | Available (UAT) |

#### 6.4.2 How it works

- `local_sentientia_analytics` 1.2.8-beta: `analytics_manager.php`, `predictive_engine.php`, `roi_calculator.php`, `permission.php`; task `refresh_predictive_cache` (hourly pre-warm); capabilities view, view all organisations (site administrator only) and export. It stores no tables of its own.
- `local_sentientia_reports` 1.3.0: `report_manager.php` with one runner per report type; 3 web-service functions.
- `local_sentientia_manager` 1.3.10: `team_manager.php`; 8 web-service functions.
- Vendored `block_learnerscript`, `block_reportdashboard`, `block_reporttiles` (`blocks/`), with the modal-factory port recorded in `docs/core-mods/`.
- Every query is tenant-scoped through `local_sentientia_platform\tenant` helpers; tenant paths are bounded with `tenant::path_descendant_filter()`.

#### 6.4.3 Configuration and flags

| Flag or setting | Default | Effect |
|---|---|---|
| `sentientia.analytics.predictive.enabled` | OFF | At-risk and skill-gap views |
| `sentientia.analytics.roi.enabled` | OFF | ROI card |
| ROI assumptions (`roi_<key>` settings: hours saved, hourly rate, penalty avoided, development hours, platform cost, cost per learner hour) | site settings | ROI inputs |
| `sentientia.reports.training_transcript`, `.login_days` | OFF | Imported report data |

#### 6.4.4 Scenario

An L&D administrator opens the analytics dashboard, filters to one business unit and compares completions year to date with the same period last year. With the predictive flag ON, the at-risk list shows learners with a high score; opening one shows which signals drove it (for example 40 days since last access and two overdue courses), and the manager nudges them.

#### 6.4.5 Gaps and full potential

- The analytics plugin declares that it holds no personal data (`null_provider`) although its predictive caches may hold per-user lists; this is under privacy review (owner: Security architect).
- ROI v1 counts the flat platform cost in every period and every visible course's development cost whatever the period; ADR-042 replaces it. ROI figures must not be shown to stakeholders until v2 is accepted.
- Full potential: with v2's monthly fact tables, ROI, evaluation level 3 and 4 results and compliance risk can be reported on one traceable basis.

### 6.5 Content authoring studio and the SOP-to-SCORM content pipeline

Two ways to create content with AI help: an in-product authoring studio, and a command-line pipeline that turns a standard operating procedure (SOP) into a SCORM package.

#### 6.5.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Authoring studio | Course author | Paste source text (up to 4,000 words); the studio drafts a course of content cards (default 8, maximum 12) with narration text, flip cards and quiz questions (multiple choice, multiple response, matching; default 10, maximum 20); templates; review and edit per card and question; English and Hindi prompts | Built (behind a feature flag, off by default); mock output by default |
| 2 | Publish to a Moodle course | Course author | Builds a hidden course: a book with one chapter per approved card, and a mastery quiz with the approved questions (pass mark from the mastery score, default 70%), in one transaction | Built (behind a feature flag, off by default) |
| 3 | Voice-over | Course author | Text-to-speech (TTS) narration per card through ElevenLabs, with a confirmation tick per call | Built (behind a feature flag, off by default); mock by default |
| 4 | AI quiz generation | Course author, Internal trainer | Paste up to 4,000 words; the model drafts up to 30 multiple-choice questions; every question is approved, edited or rejected by a person before approved questions are pushed into a Moodle quiz (ADR-012, Accepted) | Built (behind a feature flag, off by default) |
| 5 | AI content translation | L&D administrator | Translates English course text into Hindi, Marathi, Kannada or Swahili with protected brand terms; strict structured output (ADR-016, Accepted) | Built (behind a feature flag, off by default) |
| 6 | Sentientia Author role | Course author | A role with exactly seven authoring, quiz-AI and skills-AI capabilities and no administration rights; course creation comes from a category-level course-creator assignment | Available (UAT) |
| 7 | Canonical course document (SCD) store | Course author (no interface yet) | A JSON course document schema, a validator for twelve rules and a tenant-scoped, versioned store with a hash per version (ADR-034 Stage 1, slice 1) | Built (behind a feature flag, off by default); no page or menu item |
| 8 | Compiler to SCORM 1.2, SCORM 2004 4th Edition, cmi5 and Moodle course, with a static player | Course author | One document compiled to several standards | Planned (ADR-034, Accepted; slice 2) |
| 9 | Localisation pipeline, self-hosted speech, live AI roleplay, AI avatar video | Course author, L&D administrator | Translation memory and XLIFF review, India-hosted speech, roleplay and avatar video | Planned (ADR-034) |
| 10 | SOP-to-SCORM pipeline (command line) | L&D content team | Six agents run one at a time: 1 parse the SOP PDF (maximum 2,000 words); 2 write narration (sentences of at most 25 words); 3 build slide JSON (at most 5 bullets of 8 words); 4 generate voice (ElevenLabs); 5 package SCORM 1.2 with validation; 6 upload to Moodle | Built (verified on development); validated on one sample SOP |

#### 6.5.2 How it works

- **Studio.** `local_sentientia_authoring` 0.3.2-alpha: `course_generator.php`, `prompt_builder.php`, `response_parser.php`, `draft_manager.php`, `template_manager.php`, `course_builder.php` (book chapters plus a quiz through the GIFT import, the same route as the quiz-AI publisher), `localizer.php`, `tts_client.php`, `author_role.php`, and `classes/scd/` (schema, validator, repository); 7 tables. Model calls go through the AI gateway when routing is ON; the authoring path needs the central gateway key.
- **Quiz AI.** `local_sentientia_aiquiz` 0.3.1-alpha (draft and question tables; publisher into the course's shared question bank).
- **Translation.** `local_sentientia_translate` 0.2.2-alpha (`translate.php`, `brands.php` for protected terms). No chunking above 4,000 words per request.
- **Pipeline.** `scripts/agents/agent1_sop_parser.py` to `agent6_moodle_upload.py`, run one at a time, each reading the previous agent's output from disk (`content/parsed/`, `content/narrations/`, `content/slides/`, `content/voice/`, `content/scorm-output/`). Only Agent 2 calls a language model (directly, not through the gateway); Agent 4 calls text-to-speech. Agents 2, 4 and 6 run in mock mode unless `--confirm` is given. Agent 5 writes `imsmanifest.xml` at the archive root, declares the mastery score (default 70, configurable per customer), checks that every manifest file exists, and refuses archives over 50 MB. 125 Python tests cover the agents.

#### 6.5.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.authoring.enabled` | OFF | Studio |
| `sentientia.authoring.live_api` | OFF | Live model and voice calls (mock when OFF) |
| `sentientia.authoring.tts` | OFF | Voice-over |
| `sentientia.authoring.publish.enabled` | OFF | Publish to a course |
| `sentientia.content.scd.enabled` | OFF | SCD store |
| `sentientia.aiquiz.enabled`, `.live_api`, `.auto_push` | OFF | Quiz AI, live calls, push to quiz |
| `sentientia.translate.enabled`, `.live_api` | OFF | Translation |
| `ai.sentientia.enabled` | OFF | Reserved for an in-product SOP-to-SCORM pipeline; no code reads it today (the pipeline runs from the command line) |
| `sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled` | OFF | Gateway routing and organisation-level live spend |
| Pipeline `--confirm` | absent | Paid or live calls in Agents 2, 4 and 6 |

#### 6.5.4 Scenario

A course author pastes a 3,000-word dispute-handling procedure into the studio. The draft has eight cards and ten questions. The author edits two cards, rejects one question and approves the rest. Publishing creates a hidden course with a book and a 70% mastery quiz; L&D reviews it and makes it visible. With the live flags OFF the draft text is a clearly marked mock; with the budget approved and the flags ON, the gateway routes the call, applies the quotas and writes one ledger row.

#### 6.5.5 Gaps and full potential

- Agent 6 can stage a package in Moodle's draft area, but full activity creation calls a web service under a retired name that no plugin in the repository declares; creating the SCORM activity from the pipeline does not work today (owner: platform engineer).
- Pipeline Agent 2, the assistant and the copilot call the model provider directly rather than through the gateway.
- Every AI feature is mock by default; live use waits on the signed AI spend cap and a provider key (owner: Finance with Head of Learning & Development).
- Full potential: ADR-034 turns the studio and pipeline into one product: one course document, compiled to SCORM 1.2, SCORM 2004 and cmi5, in 10 or more Indian languages plus Arabic and Urdu, with India-hosted speech (Part 4, section 12.5.2).

### 6.6 Assessment integrity and proctoring

Three integrity levels per quiz, chosen from the assessment's purpose (ADR-038, Proposed). The work is staged B1 to B5.

#### 6.6.1 Integrity levels

| Level | Default for purpose | Adds | Status |
|---|---|---|---|
| Standard | Compliance | Browser hardening on the quiz page | Built (behind a feature flag, off by default) |
| Secure | Skill assessment | Standard plus a Safe Exam Browser (SEB) kiosk preset; camera off | Browser-hardening part Built (behind a feature flag, off by default); SEB preset Planned (B3) |
| Proctored | Certification, hiring | Secure plus webcam record-and-review; camera on | Consent and identity confirmation Built (behind a feature flag, off by default); recording Planned (B4) |

An editor may raise a quiz above its purpose's minimum; lowering it below the minimum is refused unless the editor holds `local/sentientia_proctoring:manage`. A quiz without a level behaves as stock Moodle.

#### 6.6.2 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Proctoring foundation (B1) | Learner, L&D administrator | Consent and identity confirmation in the quiz's own pre-start check; the server links the session to the attempt and closes it when the attempt is submitted or abandoned; the browser cannot choose or close the session. The cloud face-matching provider is no longer returned by the code | Built (behind a feature flag, off by default) |
| 2 | Secure assessment mode (B2) | Learner, L&D administrator | Security level, purpose and violation limit in quiz settings. On the attempt page: copy, paste, print and shortcut attempts blocked; leaving fullscreen or losing focus counted as violations with server time; a heartbeat every 15 seconds, with a gap over 45 seconds flagged; an address change between pages listed | Built (behind a feature flag, off by default) |
| 3 | Violation policy | Learner | Employees are warned and the attempt is flagged for review; it is never submitted for them. A hiring assessment is submitted automatically when violations reach the quiz limit (default 3, maximum 20). Auto-submit ends an attempt; a reviewer decides the outcome | Built (behind a feature flag, off by default) |
| 4 | Integrity review and reports | L&D administrator | Review page, integrity report and two report web-service functions | Built (behind a feature flag, off by default) |
| 5 | Safe Exam Browser preset (B3) | L&D administrator | Kiosk preset for Secure and Proctored quizzes | Planned (flag reserved, no code reads it) |
| 6 | Webcam record-and-review (B4) | L&D administrator | Recording kept in India for 30 days (candidates) or 90 days (employees), per ADR-038 | Planned (flag reserved, no code reads it) |
| 7 | Hiring candidate accounts (B5) | HR, Candidate | A fifth user type for candidates | Planned (flag reserved, no code reads it) |
| 8 | Earlier proctoring code on UAT | n/a | The proctoring code in the UAT commit predates B1. ADR-038 records that it locked learners out of proctored quizzes and recorded nothing; do not use proctored quizzes on UAT until B1 is deployed | Available (UAT) as code; not fit for use |

#### 6.6.3 How it works

- `local_sentientia_proctoring` 1.2.1: `quiz_integration.php` (B1 life cycle), `secure_mode.php` (levels, purposes, limits, de-duplication of 2 seconds, a 10-second grace for a lost focus that ends quickly, rate limit of 40 events per 60 seconds), `session_manager.php`, `analyzer/risk_analyzer.php`, `amd/src/secure_mode.js`, `consent.js`, `proctor.js`; 5 tables; 14 web-service functions.
- `quizaccess_sentientia_proctoring` 1.3.1 (`mod/quiz/accessrule/sentientia_proctoring/rule.php`): adds the level, purpose and limit to quiz settings and runs the pre-start check. Secure and Proctored levels are refused to web-service clients (desktop required).
- The layout `theme/sentientia/layout/secure.php` provides the reduced quiz page.
- No core file or vendor patch is needed: the access-rule hooks and the quiz attempt API are stock Moodle 5.3.

#### 6.6.4 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.proctoring.enabled` | OFF | B1 foundation |
| `sentientia.assessment.secure_mode.enabled` | OFF | B2 secure assessment mode |
| `sentientia.assessment.seb_preset.enabled` | OFF | Reserved for B3 |
| `sentientia.proctoring.recording.enabled` | OFF | Reserved for B4 |
| `sentientia.assessment.candidates.enabled` | OFF | Reserved for B5 |

#### 6.6.5 Scenario

L&D sets a skill assessment to purpose "skill", which requires at least Secure. With secure mode ON, a learner accepts the rules in the pre-start check and starts the attempt. The learner switches to another window twice; both are counted with server time and the attempt is flagged for review, not submitted. The reviewer sees the event list, including a heartbeat gap, and decides.

#### 6.6.6 Gaps and full potential

- What the browser cannot detect is stated in the product strings: a second device such as a phone, another person, or a screenshot taken with the computer's own tools. A modified browser can switch the page script off, which leaves only the heartbeat gap as evidence.
- The lawful basis, notice and retention for recording need the Data Protection Officer and Legal before B4 (owner: Head of Learning & Development).
- The Exams plugin has no integrity field yet; ADR-038 adds it in B3.
- Proctoring has no AI model, no face detection and no recording in the built code; it must not be described as "AI proctoring".

### 6.7 External trainers

Restricted, expiring accounts for trainers who are not employees (ADR-036, Proposed; the external-trainer part is built).

#### 6.7.1 Features

| # | Feature | Persona | What it does | Status |
|---|---|---|---|---|
| 1 | Invite-only accounts | L&D administrator | A tenant administrator invites an external trainer into their own tenant. The account gets a random password that is never shown or sent and a forced password change; the invite e-mail carries a password-set link | Built (verified on development), behind a flag, off by default |
| 2 | Restricted role | External trainer | The `sentientia_externaltrainer` role holds two capabilities only: view classrooms and mark attendance | Built (verified on development), behind a flag, off by default |
| 3 | Own classrooms, names only | External trainer | The classroom plugin limits an external trainer to the classrooms they train and shows learners' names only (no e-mail or employee id), regardless of the internal-trainer flag | Built (verified on development), behind a flag, off by default |
| 4 | Automatic expiry | System | Access ends 30 days after the later of the trainer's last non-cancelled session or the invite or extension date. A daily task (01:15) recomputes the date, reminds administrators 7 days before, and suspends the account; it never deletes it, because attendance and feedback refer to it | Built (verified on development) |
| 5 | Extend, reactivate, revoke | L&D administrator | An expired or revoked account returns only through reactivation; other activation routes refuse it, and the daily task suspends it again if anything else unsuspends it | Built (verified on development), behind a flag, off by default |
| 6 | Audit events | Site administrator | Invite, extension, reactivation, expiry and revocation are logged events, alongside core role and user events | Built (verified on development) |

#### 6.7.2 How it works

`local_sentientia_users\external_trainer\manager` (state constants active, expired, revoked; `GRACE` of 30 days), `role.php`, `invite_mailer.php`, `privacy.php`; table `local_sentientia_external_trainer_profile`; task `external_trainer_expiry`; page `external_trainers.php`; capability `local/sentientia_users:inviteexternaltrainer` (granted to the tenant administrator role by an upgrade step, no archetype default). The classroom plugin calls `is_external_trainer()`.

#### 6.7.3 Configuration and flags

| Flag | Default | Effect |
|---|---|---|
| `sentientia.users.external_trainer.enabled` | OFF | With it OFF, no person can start anything: the page refuses everyone and the invite button is hidden. The expiry task still runs, but only on accounts that exist |
| `sentientia.classroom.trainer_own_scope.enabled` | OFF | Own-classroom scope for internal trainers (external trainers are always limited) |

#### 6.7.4 Scenario

A tenant administrator invites a vendor trainer for a two-session workshop ending on 10 December. The trainer sets a password from the invite link, sees only that workshop's roster by name, and marks attendance. On 2 January the administrators get a 7-day reminder; on 9 January the daily task suspends the account. The attendance records keep pointing to it.

#### 6.7.5 Gaps and full potential

- The external-trainer user type is not yet listed by the user-type factory, so no user-type row is written for these accounts; it must be added in the release that turns the flag ON (owner: platform engineer).
- Full potential: the same pattern (invite only, restricted role, tenant-bound, automatic expiry) serves hiring candidates in B5 (section 6.6).

### 6.8 Domain status summary

| Domain | Section | Available (UAT) | Built (behind a feature flag, off by default) | Built (verified on development) | Planned |
|---|---|---|---|---|---|
| Learning delivery and course experience | 5.2 | Shell, dashboard, course page, activities, reminders, exams, assistant drawer (no key) | Calendar sync, copilot, recommendations, PWA, xAPI, dark mode, language switcher | Programs sidebar link | Native app and learner API; command palette |
| Catalogue, storefront, cart, payments | 5.3 | Catalogue, storefront, cross-tenant library, cart, both Airpay payment paths, order administration | Receipts, GST documents, imported orders and credits, subscriptions, marketplace | n/a | Recurring billing |
| Enrolment, requests, approvals | 5.4 | Admin enrolment, user import, requests, routing, escalation, manager approvals, audience enrolment | Catalogue request button, joiner auto-enrolment, HRMS feed, SCIM | n/a | Audience model (ADR-037) |
| Compliance | 5.5 | List, snapshot, dashboard, scope, block, e-mails | Digest deadline lines | Not-applicable state, pinning and clean-up tool | n/a |
| Paths, programs, recertification | 5.6 | Paths, programs, recertification rules | Learner pages, adaptive journeys, daily recertification run | Path fixes | Path revisions, staged training programs |
| Classroom and live | 5.7 | Classrooms, attendance, waiting list, ICS, meeting links, trainer block | QR attendance, audience enrolment, live engagement | Trainer own scope | Teams and Zoom meeting creation |
| Evaluations | 5.8 | Builder, triggers, assignments, anonymity, analysis | Learner history, drill-down | Anonymity minimum group size | Survey reach (ADR-043) |
| Certificates | 5.9 | Templates, gallery, verification, e-mails | Tenant template browser, outbound events | Course certificate issuer, 5.0.12 re-vendor | n/a |
| Skills | 6.1 | Framework, mapping, My skills | Skills AI, gap engine, impact map, talent mobility | Gap feed change | Manager-validated levels |
| Gamification | 6.2 | Points, levels, badges, streaks, challenges | Leaderboards | n/a | n/a |
| Communications | 6.3 | E-mail templates and rules, smart rules, digest, nudges | Parity senders, web push, WhatsApp and SMS (mock) | Rule-editor fix, Teams alerts | WhatsApp and SMS live send |
| Analytics | 6.4 | Dashboard, reports, report builder, manager analytics | At-risk, skill-gap projection, ROI v1 | Year-to-date comparison | ROI v2 (ADR-042) |
| Content | 6.5 | Author role | Studio, voice-over, quiz AI, translation, SCD store | SOP-to-SCORM pipeline | Compilers, localisation, self-hosted speech |
| Assessment integrity | 6.6 | n/a (earlier code not fit for use) | B1 foundation, B2 secure mode | n/a | B3 SEB, B4 recording, B5 candidates |
| External trainers | 6.7 | n/a | All features (flag OFF) | All features | n/a |

Source for every row: the domain sections above; `_facts/00-FACTS-INDEX.md` sections 1 and 2.14; `_facts/20-flags-roles.md` section 3.6.
