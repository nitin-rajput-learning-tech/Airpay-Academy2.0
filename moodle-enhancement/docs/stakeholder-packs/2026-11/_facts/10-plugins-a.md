# Plugin catalogue part A: local plugins `sentientia_ai` to `sentientia_live` (24 plugins)

Confidential: Airpay Payment Services internal. Not for external distribution.

Basis: canonical tree `moodle-enhancement/local/<plugin>/` (twins under `local/<plugin>/` not counted). Collected 20 Nov 2026 by static read of `version.php`, `db/*.php`, `classes/`, `lang/en`, `tests/`. No `airpay_*` plugin remains under `moodle-enhancement/local/`. 'a..l' is read as the part of the name after the `sentientia_` prefix (ai ... live). Part B covers m..z.

Method notes: tables from `db/install.xml` only (tables created solely in `db/upgrade.php` are not counted); web services = entries in `db/services.php`; capabilities = entries in `db/access.php`; flags = entries in `db/feature_flags.php`; tests = `tests/**/*_test.php` files (not test methods). Status per master prompt: UAT = commit af6e65c0e; later commits and the uncommitted 20 Nov tree = built (verified on development). Plugins with a master flag default OFF are labelled flag-off; whether a flag is on in UAT is to be confirmed by the Platform owner. Plugins labelled 'Built (behind a feature flag...)' are deployed in the UAT code tree but dark by default.

## 1. Summary table

| Plugin | Version | Release | Tables | Tasks | Observers | Events | WS | Caps | Flags | Privacy | Tests | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `sentientia_ai` | 2026111300 | 0.1.2-alpha | 1 | 0 | 0 | 0 | 0 | 2 | 2 | real | 3 | Built (behind a feature flag, off by default) |
| `sentientia_aiquiz` | 2026100800 | 0.3.1-alpha | 2 | 0 | 0 | 0 | 0 | 3 | 3 | real | 8 | Built (behind a feature flag, off by default) |
| `sentientia_analytics` | 2026111901 | 1.2.8-beta | 0 | 1 | 0 | 0 | 0 | 3 | 2 | null_provider | 4 | Available (UAT); 1.2.8 changes (YTD comparison, cache lifetimes) built (verified on development) |
| `sentientia_api` | 2026092501 | 1.3.2 | 9 | 2 | 3 | 0 | 7 | 6 | 5 | real | 11 | Built (behind a feature flag, off by default); observer change since UAT built (verified on development) |
| `sentientia_assistant` | 2026092201 | 1.2.2-alpha | 3 | 0 | 0 | 0 | 4 | 5 | 2 | real | 4 | Available (UAT): chat drawer, gated by platform flag `ai.assistant.enabled` (default ON) and plugin setting `enabled` (default on); live replies need a provider key, none configured by default; agentic copilot Built (behind a feature flag, off by default) |
| `sentientia_authoring` | 2026111800 | 0.3.2-alpha | 7 | 0 | 0 | 0 | 0 | 4 | 5 | real | 14 | Built (behind a feature flag, off by default) |
| `sentientia_calendar` | 2026100800 | 1.2.1-beta | 2 | 1 | 0 | 0 | 0 | 2 | 5 | real | 4 | Built (behind a feature flag, off by default) |
| `sentientia_cart` | 2026111200 | 1.2.2 | 8 | 1 | 0 | 2 | 9 | 6 | 4 | real | 22 | Available (UAT); imported-orders, imported-credits, receipts and GST tax invoices behind flags, off by default |
| `sentientia_catalog` | 2026111801 | 1.0.11-beta | 0 | 0 | 0 | 0 | 0 | 0 | 4 | null_provider | 13 | Available (UAT) |
| `sentientia_challenge` | 2026092501 | 1.1.6-alpha | 3 | 1 | 3 | 0 | 8 | 4 | 0 | real | 10 | Available (UAT) |
| `sentientia_classroom` | 2026112000 | 1.12.0 | 8 | 0 | 0 | 1 | 15 | 6 | 4 | real | 21 | Available (UAT); trainer own-scope and external-trainer restrictions built (verified on development), behind a flag |
| `sentientia_compliance_report` | 2026111902 | 1.0.12 | 4 | 1 | 0 | 0 | 0 | 1 | 0 | real | 8 | Available (UAT); pinning CLI and outsider clean-up option built (verified on development) |
| `sentientia_content_market` | 2026100901 | 1.0.3-beta | 3 | 1 | 0 | 0 | 0 | 4 | 6 | null_provider | 1 | Built (behind a feature flag, off by default) |
| `sentientia_core` | 2026093001 | 0.8.0-alpha | 5 | 1 | 0 | 0 | 0 | 2 | 1 | real | 9 | Available (UAT) |
| `sentientia_courses` | 2026112000 | 1.16.0 | 11 | 2 | 1 | 5 | 15 | 10 | 1 | real | 18 | Available (UAT); certificate issuer and flagged advanced actions built (verified on development) |
| `sentientia_emails` | 2026112000 | 1.4.3 | 4 | 2 | 2 | 0 | 5 | 6 | 5 | real | 18 | Available (UAT); rule-editor fix built (verified on development); sender flags off by default |
| `sentientia_evaluation` | 2026112000 | 1.18.0 | 6 | 2 | 3 | 0 | 8 | 2 | 2 | real | 17 | Available (UAT); anonymity minimum group size built (verified on development) |
| `sentientia_exams` | 2026100701 | 1.7.1 | 2 | 2 | 0 | 0 | 3 | 3 | 0 | real | 6 | Available (UAT) |
| `sentientia_gamification` | 2026092500 | 1.0.5-beta | 4 | 0 | 4 | 0 | 0 | 0 | 0 | real | 3 | Available (UAT) |
| `sentientia_integrations` | 2026080700 | 1.2.0-beta | 1 | 1 | 0 | 0 | 0 | 0 | 2 | null_provider | 3 | Built (behind a feature flag, off by default) |
| `sentientia_leaderboard` | 2026111800 | 0.2.3-alpha | 5 | 2 | 1 | 1 | 3 | 4 | 7 | real | 6 | Built (behind a feature flag, off by default) |
| `sentientia_learningpath` | 2026111901 | 1.9.4 | 5 | 1 | 1 | 0 | 12 | 6 | 2 | real | 15 | Available (UAT); imported-history and UI fixes built (verified on development); adaptive and learner-path flags off |
| `sentientia_lifecycle` | 2026080700 | 1.1.0-beta | 0 | 1 | 2 | 0 | 0 | 0 | 1 | null_provider | 1 | Available (UAT) (deadline task, no flag); auto-enrolment Built (behind a feature flag, off by default) |
| `sentientia_live` | 2026092500 | 0.2.4-alpha | 6 | 0 | 0 | 0 | 0 | 5 | 9 | real | 12 | Built (behind a feature flag, off by default) |
| **Total (24)** | | | 99 | 22 | 20 | 9 | 89 | 84 | 72 | real 19 / null 5 | 231 | |

Source for every row: `moodle-enhancement/local/<plugin>/version.php`, `db/install.xml`, `db/tasks.php`, `db/events.php`, `db/services.php`, `db/access.php`, `db/feature_flags.php`, `classes/privacy/provider.php`, `tests/`.

## 2. Per-plugin detail

### `local_sentientia_ai`: Sentientia AI Gateway

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_ai` / 2026111300 / release 0.1.2-alpha / MATURITY_ALPHA |
| Purpose | One gateway for every Sentientia AI feature: owns provider key handling, a spend ledger, fail-closed quotas and mock-first routing; replaces per-plugin model clients (ADR-028 Phase 2.3). |
| User-facing features | None for learners (service plugin). |
| Admin features | Spend-ledger page (index.php); settings.php; capabilities viewledger, manage. Consumers routed through it: AI Quiz, Skills AI, Recommendations, Translate, Authoring, Assistant (per README and project record). Live calls need a spend cap and provider key, both pending approval. |
| DB tables (1) | `local_sentientia_ai_ledger` |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 2 |
| Feature flags (2) | `sentientia.ai.gateway.enabled` = false, `sentientia.ai.live_api.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 3 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_ai/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_ai.php`, `README.md` |

### `local_sentientia_aiquiz`: Sentientia LMS — AI Quiz Generation

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_aiquiz` / 2026100800 / release 0.3.1-alpha / MATURITY_ALPHA |
| Purpose | Course authors paste source material and the AI drafts a multiple-choice quiz; every draft passes a mandatory human review (approve/edit/reject per question) before approved questions are pushed to a mod_quiz activity (ADR-012). |
| User-facing features | Author flow: generate.php (source text, language incl. Hindi), review.php (per-question review), publish to quiz. |
| Admin features | settings.php; 3 CLI scripts; per-customer prompts; capabilities generate, review, manage_all; mock mode runs end to end without spend. |
| DB tables (2) | `local_sentientia_aiquiz_draft`, `local_sentientia_aiquiz_question` |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 3 |
| Feature flags (3) | `sentientia.aiquiz.enabled` = false, `sentientia.aiquiz.live_api` = false, `sentientia.aiquiz.auto_push` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 8 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_aiquiz/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_aiquiz.php`, `README.md` |

### `local_sentientia_analytics`: Sentientia Advanced Analytics

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_analytics` / 2026111901 / release 1.2.8-beta / MATURITY_BETA |
| Purpose | Operational analytics and KPI dashboard (replacement for BizLMS reporting overlays); read side over the standard log and plugin telemetry. |
| User-facing features | KPI cards (active learners, completions, average score, time on platform; year-to-date compared with the same span last year), drill-down by tenant/department/category/role, business-unit filter, CSV export, predictive views (at-risk, skill gap) and a training ROI card. |
| Admin features | settings.php; hourly cache pre-warm task (refresh_predictive_cache); capabilities view, viewallorgs, export; flags gate predictive and ROI. |
| DB tables (0) | none in install.xml |
| Scheduled tasks (1) | `refresh_predictive_cache` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 3 |
| Feature flags (2) | `sentientia.analytics.predictive.enabled` = false, `sentientia.analytics.roi.enabled` = false |
| Privacy provider | null_provider (claims no personal data stored) |
| Tests | 4 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT); 1.2.8 changes (YTD comparison, cache lifetimes) built (verified on development) |
| Sources | `moodle-enhancement/local/sentientia_analytics/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_analytics.php`, `README.md` |

### `local_sentientia_api`: Sentientia Public API

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_api` / 2026092501 / release 1.3.2 / MATURITY_STABLE |
| Purpose | Versioned public REST API (/v1), LTI 1.3 provider scaffolding (OIDC login, RS256 JWT verification, nonce replay protection), outbound webhooks, SCIM user/group provisioning (ADR-030). |
| User-facing features | None directly (integrator-facing); admin pages index.php, webhooks.php, scim.php. |
| Admin features | Capabilities read, write, manage, lti, webhooks_manage, scim_manage; 7 web-service functions; tables for request log, rate limit, LTI registrations/nonces, webhook subscriptions/deliveries, SCIM clients/maps/events; cleanup and webhook_drain tasks; 3 observers feed webhooks. |
| DB tables (9) | `local_sentientia_api_log`, `local_sentientia_api_rate`, `local_sentientia_api_lti_reg`, `local_sentientia_api_lti_nonce`, `local_sentientia_api_whsub`, `local_sentientia_api_whdel`, `local_sentientia_api_scimcli`, `local_sentientia_api_scimmap`, `local_sentientia_api_scimevt` |
| Scheduled tasks (2) | `cleanup`, `webhook_drain` |
| Observers (3) | `course_completed`, `user_enrolment_created`, `certificate_issued` |
| Custom events (0) | none |
| Web services | 7 |
| Capabilities | 6 |
| Feature flags (5) | `sentientia.api.enabled` = false, `sentientia.api.lti.enabled` = false, `sentientia.api.write.enabled` = false, `sentientia.api.webhooks.enabled` = false, `sentientia.api.scim.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 11 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Built (behind a feature flag, off by default); observer change since UAT built (verified on development) |
| Sources | `moodle-enhancement/local/sentientia_api/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_api.php`, `README.md` |

### `local_sentientia_assistant`: Sentientia AI Learning Assistant

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_assistant` / 2026092201 / release 1.2.2-alpha / MATURITY_ALPHA |
| Purpose | AI learning assistant: chat widget over the Moodle 5 core AI subsystem plus an agentic mode whose tools can recommend content, enrol a learner and book an ILT session, with a full audit table. |
| User-facing features | Chat widget in the sidebar; agent.php (agentic assistant); ai_demo.php demo page. |
| Admin features | Tables chat_log, chat_cache, agent_audit; capabilities useagent, enrol, bookilt, recommend, manageall; 4 web-service functions; tool registry (agent/tool/*); flags gate agentic mode and live model calls. |
| DB tables (3) | `local_sentientia_chat_log`, `local_sentientia_chat_cache`, `local_sentientia_agent_audit` |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 4 |
| Capabilities | 5 |
| Feature flags (2) | `sentientia.assistant.agentic.enabled` = false, `sentientia.assistant.agentic.live_api` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 4 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT): chat drawer, gated by platform flag `ai.assistant.enabled` (default ON) and plugin setting `enabled` (default on); live replies need a provider key, none configured by default; agentic copilot Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_assistant/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_assistant.php`, `README.md` |

### `local_sentientia_authoring`: Sentientia GenAI Authoring Studio

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_authoring` / 2026111800 / release 0.3.2-alpha / MATURITY_ALPHA |
| Purpose | GenAI authoring studio: builds courses from a structured course document (SCD) with templates, drafts, cards, questions, localisation and optional voice-over (TTS). |
| User-facing features | Studio flows: studio.php, templates.php, review.php, voiceover.php, index.php (authors). |
| Admin features | SCD schema/validator/repository; course_builder and course_generator; localizer; tts_client; 1 CLI; capabilities generate, review, managetemplates, manage_all; 5 flags (studio, TTS, publish, live API, SCD). |
| DB tables (7) | `local_sentientia_auth_template`, `local_sentientia_auth_draft`, `local_sentientia_auth_card`, `local_sentientia_auth_question`, `local_sentientia_auth_voiceover`, `local_sentientia_scd`, `local_sentientia_scd_asset` |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 4 |
| Feature flags (5) | `sentientia.authoring.enabled` = false, `sentientia.authoring.tts` = false, `sentientia.authoring.publish.enabled` = false, `sentientia.authoring.live_api` = false, `sentientia.content.scd.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 14 test files |
| Hindi pack / README / settings | yes / no / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_authoring/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_authoring.php` |

### `local_sentientia_calendar`: Sentientia LMS — Calendar Sync

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_calendar` / 2026100800 / release 1.2.1-beta / MATURITY_BETA |
| Purpose | Outbound calendar sync: each user gets a personal ICS subscription URL carrying course deadlines, classroom sessions and exam close dates (ADR-013). |
| User-facing features | index.php (copy/regenerate personal URL), ics.php feed, regenerate.php. |
| Admin features | Token and OAuth tables (OAuth sync is a second phase behind a flag); purge_old_tokens task; per-source flags (courses, classroom, exams default ON once master enabled); capabilities subscribe, manage_all. |
| DB tables (2) | `local_sentientia_calendar_token`, `local_sentientia_calendar_oauth` |
| Scheduled tasks (1) | `purge_old_tokens` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 2 |
| Feature flags (5) | `sentientia.calendar_sync.enabled` = false, `sentientia.calendar_sync.events.courses` = true, `sentientia.calendar_sync.events.classroom` = true, `sentientia.calendar_sync.events.exams` = true, `sentientia.calendar_sync.oauth.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 4 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_calendar/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_calendar.php`, `README.md` |

### `local_sentientia_cart`: Sentientia Cart

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_cart` / 2026111200 / release 1.2.2 / MATURITY_STABLE |
| Purpose | E-commerce stack for the external Public and ZEEA tenants: cart, GST-split pricing, checkout to the payment gateway, order history, credits wallet, receipts, GST tax invoices, credit notes and refunds. |
| User-facing features | checkout.php, history.php, receipt.php, invoice.php, credits.php, return/callback handlers. |
| Admin features | admin_orders.php, set_price.php, creditnote.php, daily_sums(.csv), einvoice.php (Finance records the IRN; no portal connection), issue_due_documents task, 8 tables, 9 web-service functions, 16 templates, 1 CLI; capabilities view, purchase, viewallorders, refund, manageprices, manageinvoices; events order_paid, refund_processed. |
| DB tables (8) | `local_sentientia_cart_id`, `local_sentientia_cart_history`, `local_sentientia_cart_ledger`, `local_sentientia_cart_credits`, `local_sentientia_cart_invoices`, `local_sentientia_cart_creditnotes`, `local_sentientia_cart_docseq`, `local_sentientia_cart_credit_txn` |
| Scheduled tasks (1) | `issue_due_documents` |
| Observers (0) | none |
| Custom events (2) | `order_paid`, `refund_processed` |
| Web services | 9 |
| Capabilities | 6 |
| Feature flags (4) | `sentientia.cart.imported_orders.enabled` = false, `sentientia.cart.imported_credits.enabled` = false, `sentientia.cart.purchase_receipts` = false, `sentientia.cart.gst_tax_invoices` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 22 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT); imported-orders, imported-credits, receipts and GST tax invoices behind flags, off by default |
| Sources | `moodle-enhancement/local/sentientia_cart/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_cart.php`, `README.md` |

### `local_sentientia_catalog`: Sentientia Course Catalog

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_catalog` / 2026111801 / release 1.0.11-beta / MATURITY_BETA |
| Purpose | Learner-facing course catalogue: tile view, search/category/tag filters, tenant-scoped visibility, public (unauthenticated) storefront entry and course detail. |
| User-facing features | index.php, course.php, mycourses.php, public.php (no login), cart.php; optional one-click free enrolment and storefront checkout. |
| Admin features | No tables, no capabilities file (relies on core and other plugins' capabilities), no settings.php; 4 flags (public LXP default ON). |
| DB tables (0) | none in install.xml |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 0 |
| Feature flags (4) | `sentientia.catalog.public_lxp.enabled` = true, `sentientia.catalog.free_oneclick_enrol.enabled` = false, `sentientia.catalog.storefront_checkout.enabled` = false, `sentientia.catalog.course_type_labels.enabled` = false |
| Privacy provider | null_provider (claims no personal data stored) |
| Tests | 13 test files |
| Hindi pack / README / settings | yes / yes / no |
| Status | Available (UAT) |
| Sources | `moodle-enhancement/local/sentientia_catalog/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_catalog.php`, `README.md` |

### `local_sentientia_challenge`: Sentientia Gamification Challenges

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_challenge` / 2026092501 / release 1.1.6-alpha / MATURITY_ALPHA |
| Purpose | Gamification challenges: challenge definitions, per-user attempts and a leaderboard recomputed by a task; awards driven by completion, login and quiz events. |
| User-facing features | index.php, view.php, leaderboard.php. |
| Admin features | 3 tables; recompute_leaderboard task; 3 observers; capabilities view, participate, manage, viewall; 8 web-service functions. No feature-flag file is registered (see section 3). |
| DB tables (3) | `local_sentientia_challenge_challenges`, `local_sentientia_challenge_attempts`, `local_sentientia_challenge_leaderboard` |
| Scheduled tasks (1) | `recompute_leaderboard` |
| Observers (3) | `course_completed`, `user_loggedin`, `attempt_submitted` |
| Custom events (0) | none |
| Web services | 8 |
| Capabilities | 4 |
| Feature flags (0) | none registered (no db/feature_flags.php) |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 10 test files |
| Hindi pack / README / settings | yes / yes / no |
| Status | Available (UAT) |
| Sources | `moodle-enhancement/local/sentientia_challenge/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_challenge.php`, `README.md` |

### `local_sentientia_classroom`: Sentientia Classroom Training

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_classroom` / 2026112000 / release 1.12.0 / MATURITY_STABLE |
| Purpose | Instructor-led training: classrooms, sessions, rosters, attendance, waiting list with auto-promotion, trainer assignments, locations and calendar files (replacement for BizLMS local_classroom). |
| User-facing features | index.php, my.php (my sessions), view.php, attendance.php, ics.php; waiting-list join; QR attendance (flag). |
| Admin features | 8 tables, 15 web-service functions, 2 CLI; capabilities manage, view, attendance, create, update, delete; event classroom_completed; BizLMS importer; trainer own-scope and external-trainer restrictions (ADR-036) added 20 Nov behind sentientia.classroom.trainer_own_scope.enabled. |
| DB tables (8) | `local_sentientia_classroom`, `local_sentientia_classroom_trainers`, `local_sentientia_classroom_courses`, `local_sentientia_classroom_sessions`, `local_sentientia_classroom_users`, `local_sentientia_classroom_attendance`, `local_sentientia_classroom_waitlist`, `local_sentientia_locations` |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (1) | `classroom_completed` |
| Web services | 15 |
| Capabilities | 6 |
| Feature flags (4) | `sentientia.classroom.qr_attendance` = false, `sentientia.classroom.bulk_enrol_audience` = false, `sentientia.classroom.import_history` = false, `sentientia.classroom.trainer_own_scope.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 21 test files |
| Hindi pack / README / settings | yes / yes / no |
| Status | Available (UAT); trainer own-scope and external-trainer restrictions built (verified on development), behind a flag |
| Sources | `moodle-enhancement/local/sentientia_classroom/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_classroom.php`, `README.md` |

### `local_sentientia_compliance_report`: Sentientia Compliance Report

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_compliance_report` / 2026111902 / release 1.0.12 / MATURITY_STABLE |
| Purpose | Compliance dashboard with a status engine per user and course, for statutory training coverage. Stored states (`classes/compliance_engine.php` constants): completed, in_progress, overdue, not_started, not_enrolled, exempted; plus not_applicable for matrix display only. There is no "expiring" or "expired" state (corrected 2026-11-20; the README wording is stale). Expiry is handled by recertification in `local_sentientia_recompletion` (reset after a period, "due soon" message before it). |
| User-facing features | index.php dashboard, export.php (CSV for statutory returns). |
| Admin features | 4 tables (local_compliance_* naming), refresh_snapshot task, settings.php, 1 CLI (pin compliance courses; --unenrol-outsiders option, dry run by default); capability export; no feature flags. |
| DB tables (4) | `local_compliance_courses`, `local_compliance_snapshot`, `local_compliance_exemptions`, `local_compliance_email_log` |
| Scheduled tasks (1) | `refresh_snapshot` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 1 |
| Feature flags (0) | none registered (no db/feature_flags.php) |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 8 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT); pinning CLI and outsider clean-up option built (verified on development) |
| Sources | `moodle-enhancement/local/sentientia_compliance_report/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_compliance_report.php`, `README.md` |

### `local_sentientia_content_market`: Content Marketplace

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_content_market` / 2026100901 / release 1.0.3-beta / MATURITY_BETA |
| Purpose | Content marketplace aggregator: provider adapters (Go1, Udemy Business, Coursera, Skillsoft, plus a mock) sync third-party catalogue items and map them to skills. |
| User-facing features | index.php (marketplace browse). |
| Admin features | 3 tables (item, skill map, sync log); sync_providers task; settings.php; capabilities view, syncproviders, manageproviders, mapskills; 6 flags (master plus one per provider and skills mapping). |
| DB tables (3) | `local_sentientia_cm_item`, `local_sentientia_cm_skill_map`, `local_sentientia_cm_sync_log` |
| Scheduled tasks (1) | `sync_providers` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 4 |
| Feature flags (6) | `sentientia.content_market.enabled` = false, `sentientia.content_market.go1.enabled` = false, `sentientia.content_market.udemy_business.enabled` = false, `sentientia.content_market.coursera.enabled` = false, `sentientia.content_market.skillsoft.enabled` = false, `sentientia.content_market.skills_mapping.enabled` = false |
| Privacy provider | null_provider (claims no personal data stored) |
| Tests | 1 test files |
| Hindi pack / README / settings | yes / no / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_content_market/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_content_market.php` |

### `local_sentientia_core`: Sentientia Core

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_core` / 2026093001 / release 0.8.0-alpha / MATURITY_ALPHA |
| Purpose | Sentientia core layer above BizLMS heritage: customer, tenant and org-unit model, the tenant-identity seam, an admin audit log and BizLMS log/org importers (ADR-018, ADR-019). |
| User-facing features | None for learners. |
| Admin features | manage_tenants.php, admin_log.php, index.php; 5 tables; reconcile_org task; 7 CLI scripts (seed tenants, bootstrap substrate, parity checks, backfill, relabel); capabilities managetenants, viewadminlog; flag for the legacy-logs report. |
| DB tables (5) | `local_sentientia_customer`, `local_sentientia_tenant`, `local_sentientia_org_unit`, `local_sentientia_org_member`, `local_sentientia_admin_log` |
| Scheduled tasks (1) | `reconcile_org` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 2 |
| Feature flags (1) | `sentientia.legacy_logs.report.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 9 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT) |
| Sources | `moodle-enhancement/local/sentientia_core/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_core.php`, `README.md` |

### `local_sentientia_courses`: Sentientia Course Engine

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_courses` / 2026112000 / release 1.16.0 / MATURITY_STABLE |
| Purpose | Admin-side course management (replacement for BizLMS local_courses): course tables and forms, enrolment, requests, cross-tenant sharing, featured courses, course types and categories, reminders and certificate issue on completion. |
| User-facing features | Course requests (my_requests.php), request access to shared courses, featured widget. |
| Admin features | index.php, enrol_csv.php, bulk_unenrol.php, exportcsv.php, enrolledusers.php, manage_requests.php, share.php, featured.php; 11 tables, 15 web-service functions, 5 custom share events, 1 observer (course_completed), course_reminder and course_overdue tasks, 5 CLI; 10 capabilities; 1.16.0 adds a course certificate issuer on completion (no flag). |
| DB tables (11) | `local_sentientia_featured_courses`, `local_sentientia_courses_requests`, `local_sentientia_course_type`, `local_sentientia_course_category`, `local_sentientia_courses_detailfill`, `local_sentientia_courses_tenant_share`, `local_sentientia_courses_remind_sent`, `local_sentientia_courses_enrolmove`, `local_sentientia_courses_tagmove`, `local_sentientia_courses_enroloff`, `local_sentientia_courses_certmap` |
| Scheduled tasks (2) | `course_reminder`, `course_overdue` |
| Observers (1) | `course_completed` |
| Custom events (5) | `course_share_created`, `course_share_requested`, `course_share_request_approved`, `course_share_request_rejected`, `course_share_withdrawn` |
| Web services | 15 |
| Capabilities | 10 |
| Feature flags (1) | `sentientia.courses.advanced_actions.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 18 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT); certificate issuer and flagged advanced actions built (verified on development) |
| Sources | `moodle-enhancement/local/sentientia_courses/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_courses.php`, `README.md` |

### `local_sentientia_emails`: Sentientia Email Templates

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_emails` / 2026112000 / release 1.4.3 / MATURITY_STABLE |
| Purpose | Email template engine and rule-driven dispatcher with delivery log, per-user preferences and per-customer overrides; multi-language templates. |
| User-facing features | Notification preferences; emails received. |
| Admin features | manage.php (templates, rules, logs, settings tabs), editor.php, preview.php, email_detail.php; 4 tables; process_rules and send_path_enrolments tasks; 2 observers; 5 web-service functions; 31 Mustache templates; capabilities preview, manage, manage_templates, manage_rules, view_logs, manage_settings; 1.4.3 rule-editor fix. |
| DB tables (4) | `local_sentientia_email_overrides`, `local_sentientia_email_rules`, `local_sentientia_email_log`, `local_sentientia_email_prefs` |
| Scheduled tasks (2) | `process_rules`, `send_path_enrolments` |
| Observers (2) | `course_completed`, `user_enrolment_created` |
| Custom events (0) | none |
| Web services | 5 |
| Capabilities | 6 |
| Feature flags (5) | `sentientia.emails.imported_history.enabled` = false, `sentientia.emails.imported_body_detail.enabled` = false, `sentientia.emails.send_course_enrolment.enabled` = false, `sentientia.emails.send_learning_path_enrolment.enabled` = false, `sentientia.emails.send_manager_completion_copy.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 18 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT); rule-editor fix built (verified on development); sender flags off by default |
| Sources | `moodle-enhancement/local/sentientia_emails/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_emails.php`, `README.md` |

### `local_sentientia_evaluation`: Sentientia Evaluations

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_evaluation` / 2026112000 / release 1.18.0 / MATURITY_STABLE |
| Purpose | Feedback and evaluation forms: form builder, triggers on completion events, assignments, anonymous responses, analysis and CSV export (replacement for BizLMS local_evaluation). |
| User-facing features | my_evaluations.php, respond.php. |
| Admin features | questions.php, responses.php, response_list/detail, analysis.php, non_respondents.php, template import/export, exportcsv.php; 6 tables; process_triggers and expire_assignments tasks; 3 observers; 8 web-service functions; anonymity minimum group size (1.18.0); capabilities manage, respond. |
| DB tables (6) | `local_sentientia_evaluation`, `local_sentientia_evaluation_questions`, `local_sentientia_evaluation_responses`, `local_sentientia_evaluation_triggers`, `local_sentientia_evaluation_template`, `local_sentientia_evaluation_assign` |
| Scheduled tasks (2) | `process_triggers`, `expire_assignments` |
| Observers (3) | `course_completed`, `program_completed`, `classroom_completed` |
| Custom events (0) | none |
| Web services | 8 |
| Capabilities | 2 |
| Feature flags (2) | `sentientia.evaluation.learner_history` = false, `sentientia.evaluation.response_drilldown` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 17 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT); anonymity minimum group size built (verified on development) |
| Sources | `moodle-enhancement/local/sentientia_evaluation/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_evaluation.php`, `README.md` |

### `local_sentientia_exams`: Sentientia Online Exams

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_exams` / 2026100701 / release 1.7.1 / MATURITY_STABLE |
| Purpose | Examination management over mod_quiz: exam code, attempt window, mastery score, roster and enrolment, reminders. The plugin has no proctoring integration (proctoring is a separate plugin). |
| User-facing features | index.php, view.php (overview, attempts, roster, analytics tabs). |
| Admin features | 2 tables; exam_reminder and exam_overdue tasks; 3 web-service functions; 2 CLI; BizLMS exams importer; capabilities manage, view, enrol; no feature flags. |
| DB tables (2) | `local_sentientia_exams`, `local_sentientia_exams_remind_sent` |
| Scheduled tasks (2) | `exam_reminder`, `exam_overdue` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 3 |
| Capabilities | 3 |
| Feature flags (0) | none registered (no db/feature_flags.php) |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 6 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT) |
| Sources | `moodle-enhancement/local/sentientia_exams/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_exams.php`, `README.md` |

### `local_sentientia_gamification`: Sentientia Gamification

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_gamification` / 2026092500 / release 1.0.5-beta / MATURITY_BETA |
| Purpose | Points, badges and streaks foundation consumed by challenges and leaderboards. |
| User-facing features | Points, badges and streaks surfaced through consuming plugins (no page of its own). |
| Admin features | 4 tables; 4 observers (course_completed, attempt_submitted, user_loggedin, course_module_viewed); no capabilities file, no settings, no feature flags. |
| DB tables (4) | `local_sentientia_points_log`, `local_sentientia_badges`, `local_sentientia_user_badges`, `local_sentientia_streaks` |
| Scheduled tasks (0) | none |
| Observers (4) | `course_completed`, `attempt_submitted`, `user_loggedin`, `course_module_viewed` |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 0 |
| Feature flags (0) | none registered (no db/feature_flags.php) |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 3 test files |
| Hindi pack / README / settings | yes / yes / no |
| Status | Available (UAT) |
| Sources | `moodle-enhancement/local/sentientia_gamification/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_gamification.php`, `README.md` |

### `local_sentientia_integrations`: Sentientia Integrations Hub

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_integrations` / 2026080700 / release 1.2.0-beta / MATURITY_BETA |
| Purpose | External-system adapter hub: KeKa HRMS webhook and reconcile, Microsoft Teams notifier, web push, AI recommender stub. |
| User-facing features | None directly. |
| Admin features | webhook.php endpoint; integration_log table (source, event_type, payload, status); keka_reconcile task; settings.php; 2 flags (webhook, reconcile). |
| DB tables (1) | `local_sentientia_integration_log` |
| Scheduled tasks (1) | `keka_reconcile` |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 0 |
| Feature flags (2) | `sentientia.hrms.webhook.enabled` = false, `sentientia.hrms.reconcile.enabled` = false |
| Privacy provider | null_provider (claims no personal data stored) |
| Tests | 3 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_integrations/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_integrations.php`, `README.md` |

### `local_sentientia_leaderboard`: Sentientia LMS — Real-time Leaderboards

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_leaderboard` / 2026111800 / release 0.2.3-alpha / MATURITY_ALPHA |
| Purpose | Leaderboards (quiz, completion, skill) with server-sent-event push, per-user opt-out and notifications. |
| User-facing features | index.php, view.php, stream.php (SSE), preferences.php; hide-me preference. |
| Admin features | 5 tables; recompute_due_boards and purge_old_events tasks; custom rankings_updated event and observer; 3 web-service functions; capabilities view, manageboard, promoteboard, viewall; 7 flags. |
| DB tables (5) | `local_sentientia_lb_boards`, `local_sentientia_lb_entries`, `local_sentientia_lb_optouts`, `local_sentientia_lb_events`, `local_sentientia_lb_notify_log` |
| Scheduled tasks (2) | `recompute_due_boards`, `purge_old_events` |
| Observers (1) | `rankings_updated` |
| Custom events (1) | `rankings_updated` |
| Web services | 3 |
| Capabilities | 4 |
| Feature flags (7) | `sentientia.leaderboards.enabled` = false, `sentientia.leaderboards.realtime.enabled` = true, `sentientia.leaderboards.type.quiz` = false, `sentientia.leaderboards.type.completion` = false, `sentientia.leaderboards.type.skill` = false, `sentientia.leaderboards.optout.enabled` = true, `sentientia.leaderboards.notifications.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 6 test files |
| Hindi pack / README / settings | yes / no / no |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_leaderboard/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_leaderboard.php` |

### `local_sentientia_learningpath`: Sentientia Learning Paths

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_learningpath` / 2026111901 / release 1.9.4 / MATURITY_STABLE |
| Purpose | Ordered learning paths (course order and the BizLMS `sequential` setting are stored but not enforced: no code locks a course until the previous one is complete; the README claim of prerequisite enforcement is stale, corrected 20 Nov 2026), adaptive journeys behind a flag and a learner page (replacement for BizLMS local_learningplan). |
| User-facing features | mypaths.php (learner paths), view.php. |
| Admin features | index.php, assign/enrol UI, exportcsv.php; 5 tables; adaptive_sweep task; 1 observer; 12 web-service functions; capabilities manage, view, enrol, create, update, delete; BizLMS importer. |
| DB tables (5) | `local_sentientia_learningpath`, `local_sentientia_learningpath_courses`, `local_sentientia_learningpath_users`, `local_sentientia_lp_course_status`, `local_sentientia_lp_adaptive_log` |
| Scheduled tasks (1) | `adaptive_sweep` |
| Observers (1) | `attempt_submitted` |
| Custom events (0) | none |
| Web services | 12 |
| Capabilities | 6 |
| Feature flags (2) | `sentientia.learningpath.learner_paths.enabled` = false, `sentientia.learningpath.adaptive.enabled` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 15 test files |
| Hindi pack / README / settings | yes / yes / no |
| Status | Available (UAT); imported-history and UI fixes built (verified on development); adaptive and learner-path flags off |
| Sources | `moodle-enhancement/local/sentientia_learningpath/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_learningpath.php`, `README.md` |

### `local_sentientia_lifecycle`: Sentientia Employee Lifecycle

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_lifecycle` / 2026080700 / release 1.1.0-beta / MATURITY_BETA |
| Purpose | Joiner/mover/leaver automation reacting to user create/update events and a compliance check task. |
| User-facing features | None directly. |
| Admin features | No tables, capabilities or pages; 2 observers; compliance_check task; settings.php; flag sentientia.lifecycle.autoenrol.enabled. |
| DB tables (0) | none in install.xml |
| Scheduled tasks (1) | `compliance_check` |
| Observers (2) | `user_created`, `user_updated` |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 0 |
| Feature flags (1) | `sentientia.lifecycle.autoenrol.enabled` = false |
| Privacy provider | null_provider (claims no personal data stored) |
| Tests | 1 test files |
| Hindi pack / README / settings | yes / yes / yes |
| Status | Available (UAT) for the `compliance_check` task: registered enabled (`disabled => 0`, weekdays 07:00), reads no flag, and messages every enrolled learner who has not completed a visible course whose end date is within 7 days, plus their supervisor, across all tenants (`classes/task/compliance_check.php`). Joiner auto-enrolment: Built (behind a feature flag, off by default), `sentientia.lifecycle.autoenrol.enabled`. Corrected 20 Nov 2026 (was labelled flag-OFF as a whole) |
| Sources | `moodle-enhancement/local/sentientia_lifecycle/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_lifecycle.php`, `README.md` |

### `local_sentientia_live`: Sentientia LMS — Live engagement

| Item | Detail |
|---|---|
| Component / version | `local_sentientia_live` / 2026092500 / release 0.2.4-alpha / MATURITY_ALPHA |
| Purpose | Live engagement (Mentimeter-style): trainer-run sessions with slides, audience join, SSE real-time push and six question types (ADR-004). |
| User-facing features | Audience join and respond; result panel. |
| Admin features | index.php, stream.php; 6 tables; trainer dashboard; profanity filter; 13 templates; 2 CLI; capabilities create, run, join, respond, manage_all; 9 flags (master, realtime, one per question type, anonymous). |
| DB tables (6) | `local_sentientia_live_sessions`, `local_sentientia_live_slides`, `local_sentientia_live_participants`, `local_sentientia_live_responses`, `local_sentientia_live_events`, `local_sentientia_live_sse` |
| Scheduled tasks (0) | none |
| Observers (0) | none |
| Custom events (0) | none |
| Web services | 0 |
| Capabilities | 5 |
| Feature flags (9) | `live.enabled` = false, `live.realtime.enabled` = true, `live.questiontype.multichoice` = false, `live.questiontype.wordcloud` = false, `live.questiontype.openended` = false, `live.questiontype.rating` = false, `live.questiontype.quiz` = false, `live.questiontype.ranking` = false, `live.allow_anonymous` = false |
| Privacy provider | real (metadata, plugin and userlist providers) |
| Tests | 12 test files |
| Hindi pack / README / settings | yes / no / yes |
| Status | Built (behind a feature flag, off by default) |
| Sources | `moodle-enhancement/local/sentientia_live/version.php`, `db/`, `classes/`, `lang/en/local_sentientia_live.php` |

## 3. Observations for reviewers

- Plugins with no registered feature-flag file: challenge, compliance_report, exams, gamification. The project rule requires a default-OFF flag for new user-visible features; these replace BizLMS features at parity (to be confirmed by Engineering lead).
- null_provider is a positive claim that a plugin stores no personal data. To be confirmed by Security for: analytics (predictive caches may hold per-user at-risk lists), integrations (integration_log stores webhook payloads), content_market, catalog, lifecycle.
- Plugins without a capabilities file: catalog, gamification, integrations, lifecycle (they depend on core or other plugins' capabilities).
- compliance_report tables use the local_compliance_* prefix, not local_sentientia_*.
- Plugin READMEs lag version.php in places (for example cart, courses, classroom, learningpath); version.php figures are used here.
- Test counts are test files, not test methods; tests were not executed for this catalogue.

## Corrections (fact-base cross-check, 20 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | `sentientia_assistant` status changed from "Built (behind a feature flag, off by default)" to "Available (UAT)" for the chat drawer. The drawer is gated by `ai.assistant.enabled`, which defaults ON, and by the plugin setting `enabled`, which defaults on. Only the agentic copilot sits behind default-OFF flags. Plugin-level split for a..l is therefore 14 Available (UAT) and 10 Built (behind a feature flag, off by default). | `local/sentientia_platform/db/feature_flags.php`; `local/sentientia_assistant/classes/hook_callbacks.php`; `local/sentientia_assistant/settings.php` line 12 |
| 2 | `sentientia_exams` changed after the UAT commit in its README only; no code change, so it stays Available (UAT). | `git diff --stat af6e65c0e -- moodle-enhancement/local/sentientia_exams` |
| 3 | Re-verified unchanged: test files a..l = 231; tables 99; web services 89; capabilities 84; flags 72. | scripted count, 20 Nov 2026 |
