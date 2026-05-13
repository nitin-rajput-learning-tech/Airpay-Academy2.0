## Section 5 — Custom Plugins (Full Inventory)

Thirty Airpay-owned local plugins plus one quiz access subplugin. Section is structured in three layers: a one-page summary inventory, then a feature-coverage matrix, then detailed profiles for the eight most consequential plugins. Lower-touch plugins follow with compact profiles. All version codes are taken directly from each plugin's `version.php` at HEAD `6ce016150`.

### 5.0 Inventory at a glance

| # | Component | Release | Domain |
|---|---|---|---|
| 1 | `local_airpay_analytics` | 1.0.0-beta | Operational analytics, KPIs, drill-down, export |
| 2 | `local_airpay_assistant` | 1.0.0-beta | AI assistant (chat-bot interface to platform context) |
| 3 | `local_airpay_cart` | 1.0.1 | E-commerce stack for external tenants |
| 4 | `local_airpay_catalog` | 1.0.0-beta | Public-facing course catalogue and detail pages |
| 5 | `local_airpay_challenge` | 1.1.1-beta | Gamification challenges (streaks, quizzes, badges) |
| 6 | `local_airpay_classroom` | 1.6.0 | Instructor-led training sessions, attendance, locations |
| 7 | `local_airpay_compliance_report` | 1.0.0 | Compliance dashboard, 6-state status engine |
| 8 | `local_airpay_core` | 1.0.0 | Shared tenant-scoping helper (foundation layer) |
| 9 | `local_airpay_courses` | 1.6.0 | Admin course management — replaces BizLMS `local_courses` |
| 10 | `local_airpay_emails` | 1.0 | Email template engine, nineteen templates, rule-driven |
| 11 | `local_airpay_evaluation` | 1.6.0 | Post-course feedback and evaluation forms |
| 12 | `local_airpay_exams` | 1.3.0 | Examination management on top of Moodle quiz |
| 13 | `local_airpay_gamification` | 1.0.0-beta | Points, badges, streaks, leaderboard |
| 14 | `local_airpay_integrations` | 1.1.0-beta | External system integration — KeKa HRMS sync |
| 15 | `local_airpay_learningpath` | 1.3.0 | Sequenced learning paths, prerequisite enforcement |
| 16 | `local_airpay_lifecycle` | (no version.php found at HEAD) | Joiner/Mover/Leaver automation |
| 17 | `local_airpay_manager` | 1.2.1 | Manager workflows — approvals, team dashboard |
| 18 | `local_airpay_notifications` | 1.4.0 | Notification rule engine, daily digest, per-user prefs |
| 19 | `local_airpay_org` | 1.3.0 | Organisation hierarchy, cohort sync, tenant accesslib |
| 20 | `local_airpay_pages` | (no version.php found at HEAD) | Static pages, homepage, QR onboarding |
| 21 | `local_airpay_privacy` | 1.0.0 | Data Privacy self-service (Digital Personal Data Protection Act) |
| 22 | `local_airpay_proctoring` | 1.0.1 | Proctored examinations — identity, recording, AI flagging |
| 23 | `local_airpay_programs` | 1.4.0 | Multi-level certification programs |
| 24 | `local_airpay_ratings` | 1.0.0 | Course ratings and reviews |
| 25 | `local_airpay_recompletion` | 1.0.1 | Annual compliance reset engine |
| 26 | `local_airpay_reports` | 1.1.0 | Reporting overlay on LearnerScript block |
| 27 | `local_airpay_request` | 1.0.1 | Course-request approval workflow with auto-escalation |
| 28 | `local_airpay_roles` | 1.1.1-beta | Custom role definitions and role-compare UI |
| 29 | `local_airpay_skills` | 1.4.0 | Skills taxonomy, gap analysis, radar chart |
| 30 | `local_airpay_users` | 1.8.0 | User admin — replaces BizLMS `local_users` |
| 31 | `quizaccess_airpay_proctoring` | 1.0.0 | Quiz attempt gate for proctored quizzes (subplugin of `mod_quiz`) |

### 5.0.1 Feature coverage matrix

`DB` = own database tables. `CAPS` = registers capabilities. `WS` = exposes web services. `PRIV` = ships a privacy provider. `CRON` = registers scheduled tasks. `MSG` = ships message providers. `RM` = ships a README.

| Plugin | DB | CAPS | WS | PRIV | CRON | MSG | RM |
|---|---|---|---|---|---|---|---|
| analytics | – | – | – | Y | – | – | – |
| assistant | Y | – | Y | Y | – | – | – |
| **cart** | Y | Y | Y | Y | – | Y | **Y** |
| catalog | – | – | – | Y | – | – | – |
| challenge | Y | Y | Y | Y | Y | – | – |
| classroom | Y | Y | Y | Y | – | Y | – |
| compliance_report | Y | – | – | Y | Y | Y | – |
| **core** | – | – | – | – | – | – | **Y** |
| courses | Y | Y | Y | Y | – | – | – |
| emails | Y | Y | Y | Y | Y | Y | – |
| evaluation | Y | Y | Y | Y | – | – | – |
| exams | Y | Y | Y | Y | – | – | – |
| gamification | Y | – | – | – | – | – | – |
| integrations | Y | – | – | Y | Y | – | – |
| learningpath | Y | Y | Y | Y | – | – | – |
| lifecycle | – | – | – | Y | – | – | – |
| manager | Y | Y | Y | Y | – | Y | – |
| notifications | Y | Y | Y | Y | Y | Y | – |
| org | Y | Y | Y | Y | Y | – | – |
| pages | – | – | – | – | – | – | – |
| privacy | Y | Y | – | – | – | Y | – |
| **proctoring** | Y | Y | Y | Y | Y | Y | **Y** |
| programs | Y | Y | Y | Y | – | – | – |
| ratings | Y | – | – | – | – | – | – |
| **recompletion** | Y | Y | – | Y | Y | Y | **Y** |
| reports | Y | Y | Y | – | – | – | – |
| **request** | Y | Y | Y | Y | Y | Y | **Y** |
| roles | Y | Y | Y | Y | – | – | – |
| skills | Y | Y | Y | Y | – | – | – |
| users | – | Y | Y | Y | – | – | – |

Plugins shown in bold ship a full README following the template defined in Phase 8.3 (12 May 2026). The remaining twenty-four plugins are documented through their respective state cards in `moodle-enhancement/state-cards/` and their inline PHPDoc; full README authoring is queued in the Section 12 backlog.

### 5.1 Deep profile — `local_airpay_core`

**Component:** `local_airpay_core` | **Version:** `2026051200` (1.0.0) | **Type:** local plugin | **Requires:** Moodle 4.0+ | **Created:** 12 May 2026 (Phase 8.1)

**Problem it solves.** Ten of the eleven blocking findings from the 12 May 2026 pre-cutover security audit had the same shape — capability checks at `CONTEXT_SYSTEM` without an additional tenant-equality check. The fix was structural: introduce a shared helper class that every sibling plugin calls, so that the second check is a one-liner rather than an act of discipline. This plugin is the foundation layer.

**Public surface.** A single static class `\local_airpay_core\tenant` with six methods:
- `root_for_user(\stdClass $u): int` derives the tenant root from `$u->open_path`.
- `root_for_current_user(): int` does the same for the current `$USER`.
- `assert_valid(int $tenantid): void` throws if the id is not one of the three known production tenants.
- `viewer_can_access(int $resource_tenant, ?int $viewerid = null): bool` — site admins always pass; tenant users pass only on tenant equality.
- `require_access(int $resource_tenant, ?int $viewerid = null): void` — the throw-on-mismatch variant.
- `sql_filter(string $alias = ''): array` returns a WHERE-clause fragment and named parameters for use in `$DB->get_records_sql()`.

**Tables, capabilities, web services, scheduled tasks, message providers.** None. The plugin is pure code.

**Tests.** `tests/tenant_test.php` ships nine PHPUnit cases covering the contract. On a vanilla Moodle PHPUnit fixture (without BizLMS's `user.open_path` column) six cases pass and three skip themselves cleanly. On production, all nine would pass.

**Status.** Production. Hard runtime dependency for `airpay_cart`, `airpay_proctoring`, `airpay_request`.

### 5.2 Deep profile — `local_airpay_org`

**Component:** `local_airpay_org` | **Version:** `2026051170` (1.3.0) | **Type:** local plugin | **Created:** April 2026

**Problem it solves.** The replacement for BizLMS `local_costcenter`. Owns the organisation hierarchy, tenant management, the access library that drives capability inheritance through the category tree, and the cohort synchronisation that bridges the org tree to Moodle's native cohort feature.

**Database tables.** Custom organisation node table, role-mapping table, branding configuration per tenant.

**Capabilities.** View, manage, branding-configure at category context. Site administrators have implicit access.

**Web services.** Org-tree fetch, branding-get, branding-set, cohort-sync trigger.

**Scheduled tasks.** `\local_airpay_org\task\sync_cohorts` — daily cron that mirrors the organisation tree into Moodle's cohort table (one cohort per designation node).

**Status.** Production. The plugin's accesslib carries some of the load that `local_costcenter` used to carry; the long-term plan in `FORK-PLAN.md` is to absorb the rest of `local_costcenter`'s responsibilities here.

### 5.3 Deep profile — `local_airpay_cart`

**Component:** `local_airpay_cart` | **Version:** `2026051201` (1.0.1) | **Type:** local plugin | **Created:** 11 May 2026

**Problem it solves.** The Public tenant (`/77`) needed a way to sell course access. Airpay-tenant employees consume training as a benefit; Public-tenant learners pay. This plugin is the e-commerce stack.

**Database tables (5).**
- `local_airpay_cart_history` — one row per cart/order through the lifecycle `open → pending → paid/failed/refunded`.
- `local_airpay_cart_id` — sequential order-id reservation table.
- `local_airpay_cart_ledger` — immutable insert-only payment events.
- `local_airpay_cart_invoices` — GST-compliant invoices with per-year sequential numbering.
- `local_airpay_cart_credits` — customer wallet balance.

**Capabilities.** Five: `view`, `purchase`, `viewallorders`, `refund`, `manageprices` (the last migrated from `CONTEXT_SYSTEM` to `CONTEXT_COURSE` on 12 May 2026 per Phase 8.1 finding B9).

**Web services (12).** `add_item`, `remove_item`, `get_cart`, `checkout`, `get_order`, `list_orders`, `refund_order`, `daily_sums`, `set_course_price`, `get_course_price`, `my_invoices`, `get_invoice`.

**Settings.** Cart-enabled tenant list, currency, gateway endpoint + merchant id + secret, callback IP allow-list, GST rate, company name + address + GSTN for invoices, invoice prefix.

**Phase 8.1 hardening.** Five distinct findings closed: B1 cross-tenant access on get/list/refund/sums, B4 payment-amount-tampering on the gateway callback, B5 invoice-template XSS fragility, B9 capability context-level migration, B11 callback DoS plus error-message leak with optional CIDR allow-list.

**Status.** Production-ready pending IT staging deploy and Nitin sign-off. Smoke test passes 26 of 26 cases.

### 5.4 Deep profile — `local_airpay_proctoring`

**Component:** `local_airpay_proctoring` | **Version:** `2026051201` (1.0.1) | **Type:** local plugin | **Created:** 11 May 2026

**Problem it solves.** Hiring assessments and skill-evaluation quizzes were previously run on the honour system using Moodle's built-in Safe Exam Browser support. That layer prevents the candidate from switching browsers but does not verify identity, does not record the session, and cannot flag suspicious behaviour. This plugin adds the missing layers.

**Architecture.** Three independent layers: identity verification using AWS Rekognition CompareFaces with a configurable minimum match score (default 0.85), live recording via the browser's MediaRecorder API uploading direct to S3 via presigned URLs (no bytes transit our server), and post-attempt risk analysis using a configurable analyser that scores events such as face-lost, multiple-faces, tab-switch and microphone-noise.

**Database tables (5).** Sessions, identity-results (score only; photos never persisted), per-attempt event log, recording chunk metadata (S3 keys + retention dates), reviewer decisions.

**Web services (12).** Cover the full lifecycle from session start through identity submission, chunk registration, event reporting, finalisation, listing of attempts and review queue, individual attempt detail, and reviewer decision capture.

**Phase 8.1 hardening.** Three distinct findings closed: B2 cross-tenant access on seven read paths, B3 session-owner verification on register-chunk, record-event and finalize, B7 identity-photo handling with rate limit, size cap, base64 strict-mode and MIME magic-byte sniff.

**Status.** Production-ready pending the same gates as cart. Smoke test passes 22 of 22 cases. Requires AWS credentials configured in production settings before live use.

### 5.5 Deep profile — `local_airpay_recompletion`

**Component:** `local_airpay_recompletion` | **Version:** `2026051201` (1.0.1) | **Type:** local plugin | **Created:** 12 May 2026

**Problem it solves.** POSH, AML, KYC, Data Privacy and other compliance courses expire annually. Manually re-enrolling two thousand seven hundred and seventy users every year would be wholly impractical. This plugin automates the reset.

**Database tables (2).** Rule definitions and reset history.

**Per-rule configuration.** Trigger type (completion / enrolment / fixed date), period in days, target tenant (or all-tenants for site-admin rules), course (or all completion-enabled courses), whether to reset grades, whether to reset quiz attempts.

**Scheduled tasks.** `\local_airpay_recompletion\task\run_rules` runs daily at 02:47 IST. Evaluates every enabled rule, finds users past expiry, resets completion atomically inside a DB transaction, and writes an audit row.

**Phase 8.1 hardening.** Two distinct findings closed: B6 cross-tenant resets fixed by adding a tenant-path filter to the candidate query, B8 LIMIT-injection in two queries fixed by switching to the parameterised limitfrom/limitnum arguments of `get_records_sql()`.

**Status.** Production. Smoke test passes 13 of 13 cases.

### 5.6 Deep profile — `local_airpay_request`

**Component:** `local_airpay_request` | **Version:** `2026051201` (1.0.1) | **Type:** local plugin | **Created:** 11 May 2026

**Problem it solves.** Hiring assessments and premium courses sometimes need an approval step before enrolment. This plugin provides the workflow: learner self-requests enrolment, request routes to direct manager (or course owner, or default admin), 48-hour SLA, auto-escalation if pending past the SLA.

**Database tables (1).** Single request row tracking userid, courseid, reason, status, route, approver, decision-note, timestamps.

**Scheduled tasks.** `escalate_overdue` every fifteen minutes, `auto_expire` daily.

**Phase 8.1 hardening.** One finding closed: B10 — `request_manager::decide` adds tenant-equality after the override-route capability check.

**Status.** Production. Smoke test passes 23 of 23 cases.

### 5.7 Deep profile — `local_airpay_users`

**Component:** `local_airpay_users` | **Version:** `2026050904` (1.8.0) | **Type:** local plugin | **Created:** April 2026

**Problem it solves.** The replacement for BizLMS `local_users`. Owns the user administration interface — list, create, edit, suspend, delete users; handle bulk CSV import and export; manage the thirty-nine custom user profile fields the platform uses for tenant scoping, manager hierarchy, designation, gamification points and skill profile.

**Capabilities.** Create, edit, delete, manage, bulkstatuschange at category context.

**Web services.** Six covering the CRUD operations and the bulk-import workflow.

**Notable features shipped in this plugin's history.** Native single-user enrolment modal (Phase F.5), photo upload with server-side GD resize (Phase E.5), bulk import CSV with dry-run preview, skill-profile standalone page.

**Status.** Production.

### 5.8 Deep profile — `local_airpay_notifications`

**Component:** `local_airpay_notifications` | **Version:** `2026050900` (1.4.0) | **Type:** local plugin

**Problem it solves.** Replacement for BizLMS `local_notifications`. Provides the rule engine that converts platform events (course completion, certificate expiry, manager summary, training overdue) into messages routed through Moodle's standard message subsystem.

**Tables.** Rule definitions, message log, per-user preference override, deduplication cache.

**Scheduled tasks.** Dispatcher every five minutes.

**Capabilities and message providers.** Both populated.

**Status.** Production. Carries fifteen active rule types as of HEAD.

### 5.9 Compact profiles — remaining plugins

For brevity, the remaining twenty-two plugins are summarised by their core capability. Detailed state cards exist at `moodle-enhancement/state-cards/` for the most-recently-touched plugins (challenge, courses, org, roles, users) and inline PHPDoc covers the rest. Full README authoring is queued in the Section 12 backlog.

| Plugin | Core capability | Notable feature |
|---|---|---|
| `airpay_analytics` | KPI dashboard | Drill-down, CSV export, business-unit filter |
| `airpay_assistant` | AI chat-bot | Bridges to Moodle 5's `core_ai` subsystem; three actions (`generate_text`, `summarise`, `translate`) |
| `airpay_catalog` | Public-facing catalog | Course tiles with tenant badge, search and filter, public-tenant route |
| `airpay_challenge` | Gamification challenges | Streak, quiz-score, multi-step challenges |
| `airpay_classroom` | Instructor-led training | Sessions, attendance, location management, waiting list |
| `airpay_compliance_report` | Compliance dashboard | Six-state engine, statutory-training coverage report |
| `airpay_courses` | Admin course management | Datatable, filters, CSV import/export, single-user enrol modal |
| `airpay_emails` | Email templating | Nineteen templates, four languages, dispatcher |
| `airpay_evaluation` | Feedback forms | Per-question anonymous toggle, analysis dashboard |
| `airpay_exams` | Exam management | Quiz wrapper with examination metadata, deep-links into proctoring |
| `airpay_gamification` | Points, badges, streaks | Leaderboard, badge-issuance hooks |
| `airpay_integrations` | KeKa HRMS sync | Joiner/Mover/Leaver event consumer, custom-field mapper |
| `airpay_learningpath` | Learning paths | Sequenced courses, prerequisite enforcement, CSV export |
| `airpay_lifecycle` | JML automation | Listens for HRMS events, executes onboarding/offboarding workflows |
| `airpay_manager` | Manager workflows | Approval queue, team dashboard, perf metrics |
| `airpay_pages` | Static pages | Homepage editor, QR-onboarding flow |
| `airpay_privacy` | DPDP self-service | Data export, data delete, consent dashboard |
| `airpay_programs` | Certification programmes | Multi-level, prerequisite, cohort-enrol |
| `airpay_ratings` | Course ratings | Five-star with comment, public-tenant gated |
| `airpay_reports` | Reporting | Overlay on LearnerScript block, org-filter, list-reports WS |
| `airpay_roles` | Role management | Side-by-side compare, capability matrix, YAML import |
| `airpay_skills` | Skills taxonomy | Gap-analysis radar, course-skill mapping admin |
