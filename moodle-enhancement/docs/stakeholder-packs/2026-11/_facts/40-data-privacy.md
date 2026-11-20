# Fact base 40: data model and privacy

Confidential: Airpay Payment Services internal. Not for external distribution.

| Field | Value |
|---|---|
| Purpose | Input for the DPDP pack and the security pack. Facts only, each with a source path. |
| Date | 2026-11-20 |
| Repository | `moodle-enhancement/` (canonical tree; each plugin has a byte-identical twin outside it, counted once here) |
| Status basis | Commit `af6e65c0e` is what runs on UAT. Later commits and the uncommitted working tree of 20 Nov were verified on the development environment. |
| Data used | Plugin source, ADRs, state cards, the A4 rehearsal report, and read-only counts from the local development database. Local data is a test import of a production backup; no personal data appears in this file. |

## 1. Key numbers

| Item | Value | Source |
|---|---|---|
| Tables created by Sentientia plugins in `db/install.xml` | 185, in 46 `install.xml` files | `local/*/db/install.xml`, `enrol/sentientiasub`, `payment/gateway/airpay`, `mod/quiz/accessrule/sentientia_proctoring` |
| Tables with direct identifiers (e-mail, phone, address, IP, user agent, username) | 13 | Section 2 |
| Tables with a user-id column (linked to a person) | 98 | Section 2 |
| Tables holding secrets or credential hashes | 5 | Section 2 |
| Tables with free text and no user column | 19 | Section 2 |
| Tables with no personal data by column name | 50 | Section 2 |
| Plugins with a privacy provider class | 56 of 56 in the canonical tree (47 `local_`, 1 `enrol_`, 1 `paygw_`, 1 `quizaccess_`, 6 `block_`); the theme (`theme/sentientia`, repository root) also has a real metadata provider, so 57 of 57 product components | Section 3; `theme/sentientia/classes/privacy/provider.php` |
| Real metadata providers | 42 | Section 3 |
| `null_provider` plugins | 14 (6 blocks, 1 access rule, 1 payment gateway, 6 `local_` plugins) | Section 3 |
| `null_provider` plugins that own tables | 4: `paygw_airpay` (3 tables with user columns), `local_sentientia_integrations` (1 table with a raw payload column), `local_sentientia_content_market` (3 tables, no user data), `quizaccess_sentientia_proctoring` (1 table, quiz settings) | Section 3.2 |
| DPDP feature flags | 2, both default OFF | `local/sentientia_privacy/db/feature_flags.php` |
| Supplementary-erasure tests | 16 tests, 134 assertions, passing on the 5.1.3+ development site, 2026-11-17 | ADR-035 "Proof" |
| A4 rehearsal | 515 automatic delete requests on a local copy: 286 processed, 229 not processable, 0 failed deletes, 22,944 rows left behind by stock Moodle | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Scheduled retention tasks in Sentientia plugins | 6 purge tasks (api, calendar, leaderboard, proctoring, pwa, xapi) plus 2 expiry tasks (external trainers, evaluation assignments) | Section 8 |
| Findings for the reviewers | 28 (F-1 to F-13 here; F-14 to F-28 in pack 01a section 16) | Section 10 |

## 2. Data model: tables created by Sentientia plugins

### 2.1 How to read this section

- The list is generated from every `db/install.xml` in the working tree: 185 tables.
- "Personal-data class" is a classification by column name, not by reading data. Order of strength: Direct identifiers, User-linked, Credential or secret, Free text with no user column, None.
- "Personal-data fields" shows the matching columns. Token counts (`tokens_in`, `tokens_out`) are not treated as personal data.
- Two tables are new in the working tree of 20 Nov (Built, verified on development): `local_sentientia_courses_certmap` (certificate-to-course map with `issueid`, `userid`, `courseid`) and `local_sentientia_external_trainer_profile` (organisation, inviting admin, expiry, status; behind the external-trainer flag, ADR-036 (external-trainer decision)).
- Some plugins create tables at runtime or only in `db/upgrade.php`. These are not in this list. `local_sentientia_platform` guards this with a structural test (`local/sentientia_platform/tests/privacy_coverage_test.php`; since 2026-09-24 it also reads `classes/schema/*::TABLES` constants). Tables outside both mechanisms are found by audit only.
- The classification by column name misses personal data in generic columns. Section 2.3 lists the cases found by reading the code.

### 2.2 Inventory by plugin

#### sentientia_ai

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_ai_ledger` | 13 | User-linked | `userid` | One row per gateway call (mock, live, failed, denied). The spend/quota source of truth. |

#### sentientia_aiquiz

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_aiquiz_draft` | 21 | User-linked | `ownerid`, `sourcetext` | Phase G.0 (2026-05-24) — One row per AI-generation request. Lifecycle: pending (created, API ca... |
| `local_sentientia_aiquiz_question` | 12 | Free text, no user link | `qoptions_json`, `reviewer_note` | Phase G.0 (2026-05-24) — One row per generated question. Cascade-delete with parent draft. `qty... |

#### sentientia_api

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_api_log` | 8 | User-linked | `userid` | Append-only audit of every public-API request (no response bodies, no PII). |
| `local_sentientia_api_rate` | 5 | User-linked | `userid` | Fixed-window rate-limit counters per (userid, window-start). |
| `local_sentientia_api_lti_reg` | 14 | Credential or secret | `authtokenurl` | LTI 1.3 platform/tool registrations (provider + consumer). Secrets are stored hashed/PEM here, ne... |
| `local_sentientia_api_lti_nonce` | 6 | None | - | One-time OIDC login nonces / state for LTI launches — replay protection. |
| `local_sentientia_api_whsub` | 12 | Credential or secret | `secret` | Outbound webhook subscriptions (ADR-030 Wave A). One HMAC secret per subscription; tenant-scoped ... |
| `local_sentientia_api_whdel` | 12 | User-linked | `userid`, `payload` | Outbound webhook delivery queue + audit (ADR-030 Wave A). Retried with exponential backoff; dead-... |
| `local_sentientia_api_scimcli` | 13 | Credential or secret | `tokenhash` | SCIM 2.0 provisioning clients (ADR-030 Wave B): one bearer token per IdP integration, hashed at r... |
| `local_sentientia_api_scimmap` | 6 | User-linked | `userid` | SCIM externalId to Moodle user mapping, per client (ADR-030 Wave B). externalid kept at 191 chars... |
| `local_sentientia_api_scimevt` | 7 | User-linked | `userid` | SCIM provisioning attestation log (ADR-030 Wave C): every IdP-initiated create / reactivate / dea... |

#### sentientia_assistant

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_chat_log` | 8 | User-linked | `userid`, `message` | Chat conversation log for AI assistant |
| `local_sentientia_chat_cache` | 7 | Free text, no user link (by column name; may hold other learners' personal data, see 2.3) | `query`, `response` | Cached responses for common queries |
| `local_sentientia_agent_audit` | 10 | User-linked | `userid`, `args_json` | P1.3 agentic copilot - immutable audit of every tool proposal, authorisation outcome and executio... |

#### sentientia_authoring

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_auth_template` | 12 | User-linked | `ownerid`, `body`, `structure_json` | P0.3 (2026-06-16) — Editable instructional-design templates. A template defines the structure +... |
| `local_sentientia_auth_draft` | 24 | User-linked | `ownerid`, `sourcetext` | P0.3 (2026-06-16) — One row per course-generation request. Lifecycle: pending (created, AI call... |
| `local_sentientia_auth_card` | 12 | Free text, no user link | `body`, `reviewer_note` | P0.3 (2026-06-16) — One row per interactive lesson card within a draft. Cascade-delete with par... |
| `local_sentientia_auth_question` | 15 | Free text, no user link | `qoptions_json`, `reviewer_note` | P0.3 (2026-06-16) — One row per generated question. Cascade-delete with parent draft. Expands b... |
| `local_sentientia_auth_voiceover` | 14 | None | - | P0.3 (2026-06-16) — One row per TTS voiceover job for a card's narration. MOCK by default (no l... |
| `local_sentientia_scd` | 13 | Free text, no user link | `scd_json` | ADR-034 Stage 1 slice 1 (2026-11-12) — one row per stored version of a Sentientia Canonical Cou... |
| `local_sentientia_scd_asset` | 18 | None | - | ADR-034 Stage 1 slice 1 (2026-11-12) — the media file registry of a course document: narration ... |

#### sentientia_calendar

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_calendar_token` | 11 | Direct identifiers + secret | `last_used_ip`, `userid`, `token` | Tier 2.6 Phase 1 (2026-05-24) — One row per user per active ICS subscription token. The token i... |
| `local_sentientia_calendar_oauth` | 10 | User-linked | `userid`, `access_token_enc`, `refresh_token_enc` | Tier 2.6 Phase 2 (2026-05-24) — Encrypted-at-rest OAuth 2.0 token storage for Microsoft 365 (Mi... |

#### sentientia_cart

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_cart_id` | 3 | User-linked | `userid` | Order number sequence |
| `local_sentientia_cart_history` | 26 | Direct identifiers | `billing_email`, `billing_phone`, `billing_address`, `userid`, `items_json`, `notes` | Cart + order history |
| `local_sentientia_cart_ledger` | 12 | Free text, no user link | `reason`, `payload_json` | Append-only payment ledger |
| `local_sentientia_cart_credits` | 7 | User-linked | `userid` | User credit balances |
| `local_sentientia_cart_invoices` | 30 | Direct identifiers | `billing_email`, `billing_phone`, `billing_address`, `userid`, `line_items_json`, `supplier_json` | Issued invoices (GST-compliant) |
| `local_sentientia_cart_creditnotes` | 30 | Direct identifiers | `billing_email`, `billing_phone`, `billing_address`, `userid`, `reason`, `supplier_json` | GST credit notes issued on refunds |
| `local_sentientia_cart_docseq` | 4 | None | - | Gap-free document number counters, one row per series |
| `local_sentientia_cart_credit_txn` | 14 | User-linked | `userid`, `reason` | Credit transactions imported from BizLMS (frozen history) |

#### sentientia_challenge

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_challenge_challenges` | 18 | User-linked | `createdby` | Challenge definitions (course-completion-based in Phase 1) |
| `local_sentientia_challenge_attempts` | 11 | User-linked | `userid` | Per-user enrolment + progress on a challenge. One row per (challengeid, userid) pair. |
| `local_sentientia_challenge_leaderboard` | 8 | User-linked | `userid` | Pre-computed leaderboard snapshot. Refreshed by scheduled task every 15 minutes; refreshed on-eve... |

#### sentientia_classroom

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_classroom` | 21 | User-linked | `trainerid`, `createdby` | Classroom/ILT sessions (fork of local_classroom) |
| `local_sentientia_classroom_trainers` | 5 | User-linked | `trainerid` | ADR-032: every trainer of a classroom (BizLMS local_classroom_trainers). The primary trainer is a... |
| `local_sentientia_classroom_courses` | 5 | None | - | ADR-032: the courses linked to a classroom (BizLMS local_classroom_courses). Created by upgrade s... |
| `local_sentientia_classroom_sessions` | 14 | User-linked | `trainerid`, `notes` | Session instances (dates/times) |
| `local_sentientia_classroom_users` | 9 | User-linked | `userid` | Roster: users enrolled in a classroom (precondition for attendance) |
| `local_sentientia_classroom_attendance` | 8 | User-linked | `userid`, `notes` | Attendance records per session |
| `local_sentientia_classroom_waitlist` | 10 | User-linked | `userid`, `reason` | Waiting list for a full classroom. Created by upgrade step 2026051130; declared here 2026-09-24 s... |
| `local_sentientia_locations` | 15 | Direct identifiers | `address` | Phase 5 A.5 (ENTERPRISE-GRADE-PLAN.md): training venues for classroom sessions. Created by upgrad... |

#### sentientia_compliance_report

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_compliance_courses` | 11 | User-linked | `createdby` | Admin-configured mandatory compliance courses |
| `local_compliance_snapshot` | 13 | User-linked | `userid` | Denormalized compliance status per employee per course - cron refreshed |
| `local_compliance_exemptions` | 8 | User-linked | `userid`, `reason` | Admin-managed exemptions for specific users from specific courses |
| `local_compliance_email_log` | 6 | User-linked | `userid` | Log of compliance emails sent - prevents duplicates |

#### sentientia_content_market

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_cm_item` | 18 | None | - | Normalised catalog item imported from any third-party provider (Go1, Udemy Business, Coursera, Sk... |
| `local_sentientia_cm_skill_map` | 7 | None | - | Many-to-many mapping between content market items and skills taxonomy terms from local_sentientia... |
| `local_sentientia_cm_sync_log` | 10 | Free text, no user link | `errormsg` | Append-only audit log of every provider sync run. One row per provider per run. |

#### sentientia_core

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_customer` | 6 | None | - | Top-level Sentientia customer. Airpay = customer-zero (id 1). One customer owns >=1 tenant root. |
| `local_sentientia_tenant` | 8 | None | - | Tenant registry — replaces the hardcoded VALID_TENANTS=[1,77,177]. rootid is today's BizLMS cos... |
| `local_sentientia_org_unit` | 9 | None | - | Sentientia org-hierarchy node (ADR-020 Wave 3.2). Replaces direct BizLMS cost-center reads. Dorma... |
| `local_sentientia_org_member` | 7 | User-linked | `userid`, `managerid` | User-to-org-unit membership with role (ADR-020 Wave 3.2). One row per (userid, unitid). |
| `local_sentientia_admin_log` | 11 | User-linked | `userid` | Imported BizLMS admin log (course insert, update and delete) and bulk course upload errors. Read-... |

#### sentientia_courses

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_featured_courses` | 6 | None | - | Admin-curated list of courses to feature on the learner dashboard |
| `local_sentientia_courses_requests` | 9 | User-linked | `requester_userid` | Sprint D — pull/request flow. A receiving-tenant manager (e.g. Public or ZEEA) requests that a ... |
| `local_sentientia_course_type` | 10 | None | - | Course types (the 'identified as' labels such as E-learning or Classroom). ADR-032 course_lookups... |
| `local_sentientia_course_category` | 11 | None | - | Custom course categories, a tree separate from Moodle's course_categories. ADR-032 course_lookups... |
| `local_sentientia_courses_detailfill` | 6 | None | - | ADR-032 course_lookups import trail: one row per course whose open_* columns the import filled fr... |
| `local_sentientia_courses_tenant_share` | 7 | None | - | Sprint C — many-to-many map (course × tenant) for cross-tenant course sharing. A course appear... |
| `local_sentientia_courses_remind_sent` | 6 | User-linked | `userid` | P1 #28: tracks every deadline-reminder notification fired by the course_reminder scheduled task. ... |
| `local_sentientia_courses_enrolmove` | 8 | None | - | ADR-032 enrolments import ledger (gap G6): one row per orphaned BizLMS enrolment that the import ... |
| `local_sentientia_courses_tagmove` | 4 | None | - | ADR-032 course_tags import trail: one row per tag_instance row that the BizLMS import moved in pl... |
| `local_sentientia_courses_enroloff` | 7 | None | - | ADR-032 enrolments import trail (gap G6, owner decision CRS-01): one row per BizLMS enrol instanc... |
| `local_sentientia_courses_certmap` | 6 | User-linked | `userid` | The course a course-completion certificate was issued for (added 2026-11-20). |

#### sentientia_emails

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_email_overrides` | 10 | Free text, no user link | `body_text` | Per-tenant email template overrides. DB rows take precedence over Mustache files. |
| `local_sentientia_email_rules` | 18 | Free text, no user link | `conditions_json`, `cadence_days_json` | Smart notification rules with trigger conditions and channel config. |
| `local_sentientia_email_log` | 18 | User-linked | `userid`, `sender_userid` | Unified delivery log for all Airpay-originated notifications. |
| `local_sentientia_email_prefs` | 8 | User-linked | `userid` | Per-user notification channel preferences. |

#### sentientia_evaluation

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_evaluation` | 17 | None | - | Evaluation forms (containers) |
| `local_sentientia_evaluation_questions` | 11 | None | - | Questions within an evaluation form |
| `local_sentientia_evaluation_responses` | 9 | User-linked | `userid`, `subject_userid` | Submitted evaluation responses |
| `local_sentientia_evaluation_triggers` | 9 | User-linked | `userid` | W1-5: queued evaluation triggers awaiting their fire_after delay. |
| `local_sentientia_evaluation_template` | 9 | User-linked | `createdby_userid`, `payload` | P1 #41 (2026-05-20): DB-backed reusable templates. Same JSON payload as import/export. ispublic=1... |
| `local_sentientia_evaluation_assign` | 11 | User-linked | `userid`, `assigned_by_userid` | P1 #37: assignments — tracks WHO was meant to respond to an evaluation. Populated by the W1-5 t... |

#### sentientia_exams

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_exams` | 13 | None | - | Online exam records (fork of local_onlinetests) |
| `local_sentientia_exams_remind_sent` | 6 | User-linked | `userid` | P1 #33: tracks every deadline-reminder notification fired by the exam_reminder scheduled task. Sc... |

#### sentientia_gamification

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_points_log` | 7 | User-linked | `userid` | Point awards log — every point transaction |
| `local_sentientia_badges` | 8 | None | - | Badge definitions |
| `local_sentientia_user_badges` | 4 | User-linked | `userid` | Badges earned by users |
| `local_sentientia_streaks` | 7 | User-linked | `userid` | Daily login streak tracking |

#### sentientia_integrations

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_integration_log` | 7 | Free text, no user link | `payload`, `errormsg` | Audit log of every inbound webhook + outbound sync event from KeKa, Teams, M365, and FCM. Append-... |

#### sentientia_leaderboard

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_lb_boards` | 18 | User-linked | `ownerid`, `skill_ids_json`, `settings_json` | A leaderboard definition. One row per leaderboard a trainer or admin creates. Three types: - quiz... |
| `local_sentientia_lb_entries` | 8 | User-linked | `userid` | Precomputed ranking snapshot, one row per (board, user). The ranking_engine recomputes wholesale:... |
| `local_sentientia_lb_optouts` | 4 | User-linked | `userid` | Per-user opt-out from being publicly LISTED on any leaderboard. Opted-out users still accrue poin... |
| `local_sentientia_lb_events` | 5 | Free text, no user link | `payload_json` | Append-only event journal that drives the SSE stream. Same shape as local_sentientia_live_events ... |
| `local_sentientia_lb_notify_log` | 10 | User-linked | `userid` | Phase L.1 — rank-change notification throttle. One row per (boardid, userid, customerid). last_... |

#### sentientia_learningpath

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_learningpath` | 29 | None | - | Learning paths (fork of local_learningplan) |
| `local_sentientia_learningpath_courses` | 12 | None | - | Courses within a learning path (ordered) |
| `local_sentientia_learningpath_users` | 8 | User-linked | `userid` | User enrolments in learning paths |
| `local_sentientia_lp_course_status` | 12 | User-linked | `userid` | ADR-032 (2026093001): per-course status rows of the BizLMS local_plan_course_status table. No rea... |
| `local_sentientia_lp_adaptive_log` | 14 | User-linked | `userid`, `skills_gap_json`, `decision_notes` | P0.2 (2026-06-16): adaptive journey pivot-decision log |

#### sentientia_live

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_live_sessions` | 13 | User-linked | `ownerid`, `settings_json` | A live engagement session — one row per Mentimeter-style event a trainer creates. Lifecycle: dr... |
| `local_sentientia_live_slides` | 8 | Free text, no user link | `settings_json` | Slides within a live session — questions, polls, prompts. Ordered by position (1, 2, 3, ...). T... |
| `local_sentientia_live_participants` | 7 | User-linked | `userid`, `join_token` | Audience members in a live session. One row per (session, audience-member) pair. For anonymous jo... |
| `local_sentientia_live_responses` | 6 | User-linked | `participantid`, `value_text` | Individual audience responses to one slide. Stored per-response (not per-slide-aggregate) so we c... |
| `local_sentientia_live_events` | 5 | Free text, no user link | `payload_json` | Append-only event journal that drives the SSE stream. The /stream.php endpoint polls SELECT * WHE... |
| `local_sentientia_live_sse` | 6 | User-linked | `userid` | Open Server-Sent-Events connection registry. See UAT-SECURITY-POSTURE-2026-09-03.md finding H4: s... |

#### sentientia_m365

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_m365_tokens` | 9 | User-linked | `userid`, `access_token_enc`, `refresh_token_enc` | Phase C.1 (2026-05-24) — One row per (userid, customerid). Holds the encrypted access + refresh... |

#### sentientia_manager

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_mgr_requests` | 11 | User-linked | `userid`, `managerid`, `reason` | Enrolment requests from learners awaiting manager approval |
| `local_sentientia_mgr_allocations` | 11 | User-linked | `managerid`, `userid`, `note` | Manager-driven allocations to direct reports — course, classroom, program, or learning path (W1... |

#### sentientia_notifications

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_notif_rules` | 10 | None | - | Notification rule definitions |
| `local_sentientia_notif_log` | 10 | User-linked | `userid`, `message` | Sent notification log — prevents duplicates + audit trail |
| `local_sentientia_notif_prefs` | 10 | User-linked | `userid` | User notification preferences |

#### sentientia_org

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_org` | 25 | Direct identifiers | `email_from_addr`, `support_email`, `footer_text` | Organization hierarchy (fork of local_costcenter) |
| `local_sentientia_cohort_scope` | 6 | None | - | Tenant scope of a core cohort: the satellite row BizLMS kept in local_groups. One row per cohort.... |

#### sentientia_platform

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_feature_flags` | 8 | None | - | Phase A0 (2026-05-14) — Feature flag overrides set via the Switchboard UI. A row exists only wh... |
| `local_sentientia_feature_flag_audit` | 9 | Free text, no user link | `reason` | Phase A0 (2026-05-14) — Every toggle on the Switchboard creates a row here. Retains the full hi... |
| `local_sentientia_customer_brand` | 14 | None | - | Per-customer branding bundle (ADR-008, 2026-05-21). One row per customer id. Read on every page (... |
| `local_sentientia_user_type` | 7 | User-linked | `userid` | ADR-017 — polymorphic user-type classification, 1:1 with mdl_user. Written once at provisioning... |
| `local_sentientia_employee_profile` | 10 | User-linked | `userid`, `manager_userid` | ADR-017 — employee-only profile shape (Airpay-customer staff). Fresh-install parity 2026-06-11;... |
| `local_sentientia_consumer_profile` | 10 | User-linked | `userid`, `interests_json` | ADR-017 — consumer-only profile shape (public-signup learners). consent_leaderboard defaults OF... |
| `local_sentientia_partner_employee_profile` | 11 | User-linked | `userid`, `partner_manager_userid` | ADR-017 — B2B partner-organisation staff profile shape. Fresh-install parity 2026-06-11; mirror... |
| `local_sentientia_operator_profile` | 7 | Direct identifiers | `contact_phone`, `userid` | ADR-017 — platform-operator profile shape (Site Admins, Sentientia-side staff). Fresh-install p... |
| `local_sentientia_legacymap` | 12 | Free text, no user link | `reason` | ADR-032 - one primary row per BizLMS source row (subkey empty) plus one row per fan-out sub-row. ... |
| `local_sentientia_legacyrun` | 12 | Credential or secret | `decisionshash`, `codehash` | ADR-032 - one row per import run (apply or purge). No operator user id is stored. |
| `local_sentientia_legacystep` | 22 | None | - | ADR-032 - progress and accounting of one step of one run. The error column holds a message only, ... |

#### sentientia_privacy

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_privacy_requests` | 11 | User-linked | `userid`, `processed_by`, `reason`, `admin_notes` | User data access and deletion requests (DPDP Act 2023) |
| `local_privacy_consent_log` | 7 | Direct identifiers | `ip_address`, `user_agent`, `userid` | DPDP consent tracking - when users agreed to data processing |

#### sentientia_proctoring

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_proctor_sessions` | 21 | User-linked | `userid` | One proctored attempt |
| `local_sentientia_proctor_identity` | 9 | User-linked | `userid` | Identity verification results |
| `local_sentientia_proctor_events` | 6 | Free text, no user link | `payload_json` | Append-only attempt events |
| `local_sentientia_proctor_recordings` | 10 | None | - | Pointers to recording chunks in S3 |
| `local_sentientia_proctor_reviews` | 6 | User-linked | `reviewer_userid`, `note` | Human reviewer decisions |

#### sentientia_programs

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_programs` | 13 | None | - | Certification programs (replaces BizLMS local_program) |
| `local_sentientia_programs_levels` | 8 | None | - | Levels within a program (sequential certification tiers) |
| `local_sentientia_programs_courses` | 6 | None | - | Courses assigned to a level |
| `local_sentientia_programs_users` | 9 | User-linked | `userid` | User enrolments in programs |
| `local_sentientia_programs_lvlcomp` | 10 | User-linked | `userid` | Stored level completions per learner (ADR-032: BizLMS local_bc_level_completions, completed rows ... |
| `local_sentientia_programs_trainers` | 8 | User-linked | `userid` | Trainers assigned to a program (ADR-032: BizLMS local_program_trainers, imported only when that t... |
| `local_sentientia_programs_trainerfb` | 8 | User-linked | `programtrainerid`, `trainerid`, `userid` | Feedback given on a program trainer (ADR-032: BizLMS local_program_trainerfb, imported only when ... |

#### sentientia_pwa

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_push_subs` | 13 | Direct identifiers + secret | `user_agent`, `userid`, `endpoint_hash`, `auth_secret` | Phase B.2 (2026-05-20) — One row per (user, browser) push subscription. Created when the user c... |
| `local_sentientia_push_log` | 12 | User-linked | `userid` | Phase B.3.c (2026-05-21) — One row per push delivery attempt. Written by push_sender::deliver_o... |

#### sentientia_ratings

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_ratings` | 7 | User-linked | `userid` | Individual user ratings |
| `local_sentientia_ratings_reviews` | 7 | User-linked | `userid` | Free-text reviews a learner left on an item (ADR-032 BizLMS import, ratings). A learner may leave... |
| `local_sentientia_ratings_reactions` | 7 | User-linked | `userid` | Like and dislike reactions (ADR-032 BizLMS import, ratings). One row per learner and item. |

#### sentientia_recommendations

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_rec_log` | 18 | User-linked | `userid` | Phase H.0 (2026-05-25) — One row per generated course recommendation. A single 'batch' of N rec... |

#### sentientia_recompletion

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_recompletion_rules` | 15 | None | - | Recompletion rule definitions |
| `local_sentientia_recompletion_history` | 13 | User-linked | `userid`, `reset_by_userid`, `reason` | Append-only reset audit log |
| `local_sentientia_recompletion_archive` | 14 | User-linked | `userid`, `payload` | Archived evidence of the cycle a reset ended |

#### sentientia_reports

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_reports` | 13 | None | - | Saved report definitions |

#### sentientia_request

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_request` | 18 | User-linked | `userid`, `approver_userid`, `decided_by_userid`, `reason`, `decision_note` | Self-service enrolment requests — course, learning path, classroom, program (polymorphic) |

#### sentientia_roles

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_roles_auditlog` | 13 | User-linked | `targetuserid`, `reason` | Append-only audit trail of role + capability mutations made through the sentientia_roles UI. Pers... |

#### sentientia_skills

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_skill_cats` | 9 | None | - | Skill categories (Compliance, Technical, Leadership, etc.) |
| `local_sentientia_skills` | 9 | None | - | Individual skills within categories |
| `local_sentientia_role_skills` | 5 | None | - | Required skills per role/designation |
| `local_sentientia_course_skills` | 5 | None | - | Which skills a course teaches |
| `local_sentientia_skill_levels` | 6 | None | - | Per-skill level definitions (e.g. Python L1='Hello world', L5='Architect Python infra'). One row ... |
| `local_sentientia_user_skills` | 8 | User-linked | `userid` | User's current skill levels (earned through courses/assessments) |
| `local_sentientia_user_skill_hist` | 9 | User-linked | `userid`, `changed_by_userid` | P1 #22: append-only audit log of every skill-level change. Lets HR answer 'when did Alice's Pytho... |
| `local_sentientia_course_levels` | 8 | None | - | Course difficulty levels (course.open_level points here). Imported from BizLMS with the legacy id... |
| `local_sentientia_skill_interest` | 5 | User-linked | `userid` | Skills a learner said they are interested in. Imported from BizLMS interested_skills; read by the... |

#### sentientia_skillsai

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_skai_job` | 20 | User-linked | `ownerid`, `sourcetext` | P0.1.0 (2026-06-16) — One row per AI skill-extraction request. Lifecycle: pending (created, cal... |
| `local_sentientia_skai_cand` | 15 | Free text, no user link | `reviewer_note` | P0.1.0 (2026-06-16) — One row per AI-proposed candidate skill. Cascade with parent job. This is... |
| `local_sentientia_skai_taxonomy` | 13 | None | - | P0.1.0 (2026-06-16) — Per-tenant CANONICAL skills taxonomy node. A row exists here ONLY after a... |
| `local_sentientia_skai_impact` | 10 | User-linked | `createdby` | P0.1.0 (2026-06-16) — Skills -> business-impact mapping surface. One row per (taxonomy node, bu... |
| `local_sentientia_skai_gap` | 14 | User-linked | `userid` | P0.1.0 (2026-06-16) — Per-user skills-gap feed. One row per (user, skill) where the user's held... |

#### sentientia_talent

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_talent_path` | 11 | None | - | Career-path definition: a named role-to-role progression (e.g. 'Support Agent → Team Lead → M... |
| `local_sentientia_talent_succ` | 10 | Free text, no user link | `notes` | Succession plan: nominates a candidate user as a successor for a key designation, with readiness ... |
| `local_sentientia_talent_opp` | 12 | None | - | Internal mobility opportunity: an open internal role/gig surfaced to employees. Skills-matched ag... |
| `local_sentientia_talent_int` | 8 | User-linked | `userid`, `message` | Expression of interest: an employee registers interest in an internal opportunity. Read/write for... |
| `local_sentientia_talent_audit` | 9 | User-linked | `targetuserid` | Append-only audit trail of every HR-sensitive talent mutation (succession nominations, opportunit... |

#### sentientia_translate

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_tr_log` | 21 | User-linked | `ownerid`, `sourcehash`, `sourcetext` | Phase T.0 (2026-05-25) — One row per translation request. Lifecycle: pending (created, API call... |
| `local_sentientia_tr_brand` | 7 | None | - | Phase T.0 (2026-05-25) — Per-customer, per-target-language brand-name substitution map. When tr... |

#### sentientia_users

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_users_sync_runs` | 16 | None | - | One row per HRMS bulk-import event. Source: web upload or cron pull. |
| `local_sentientia_users_sync_errors` | 13 | Direct identifiers | `email`, `username`, `firstname`, `lastname` | One row per CSV row that failed validation or upsert during an HRMS sync. |
| `local_sentientia_users_transcript` | 24 | User-linked | `userid` | Earlier training records imported from the BizLMS transcript history (ADR-032, users). History on... |
| `local_sentientia_users_logindays` | 6 | User-linked | `userid` | One row per user per day with a web login, imported from the BizLMS uniquelogins table (ADR-032, ... |
| `local_sentientia_users_position` | 8 | None | - | Position lookup imported from the BizLMS positions table with the ids kept, because user.open_pos... |
| `local_sentientia_users_domain` | 6 | None | - | Domain lookup imported from the BizLMS domains table with the ids kept, because user.open_domaini... |
| `local_sentientia_external_trainer_profile` | 11 | User-linked | `userid`, `invitedby` | ADR-036, external-trainer decision (2026-11-20): one row per external-trainer account. A row makes the account... |

#### sentientia_whatsapp

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_user_channel_prefs` | 11 | Direct identifiers | `mobile_number`, `userid`, `dlt_consent_text` | Per-user opt-in preferences for WhatsApp / SMS / email channels. One row per user. Phase A1 iter 1. |
| `local_sentientia_user_channel_audit` | 9 | Direct identifiers | `ip_address`, `userid`, `reason` | Append-only audit trail of every preference change. DPDP requires demonstrable consent provenance. |
| `local_sentientia_dlt_templates` | 15 | Free text, no user link | `body`, `variables_json` | DLT-approved message template registry (iter 2). Templates must be DLT-registered with the operat... |
| `local_sentientia_send_log` | 13 | User-linked | `userid` | Per-attempt log of every WhatsApp/SMS/email send (iter 3+). Used for retry, analytics, and bounce... |

#### sentientia_xapi

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `local_sentientia_xapi_stmts` | 23 | User-linked | `actorid` | xAPI 1.0.3 statement store. One row per statement. Indexed for actor / verb / object / tenant loo... |
| `local_sentientia_xapi_cmi5` | 18 | User-linked | `userid`, `launchtoken` | cmi5 Assignable Unit (AU) session records. Tracks the lifecycle of a cmi5 launch: initialized →... |
| `local_sentientia_xapi_clients` | 10 | Credential or secret | `token_hash`, `basic_pass_hash` | Registered xAPI client credentials (per-tenant). Supports Bearer token and HTTP Basic auth patter... |
| `local_sentientia_xapi_lrs_rate` | 5 | None | - | Fixed-window rate-limit counters for the /lrs/statements endpoint, one row per (clientid, window-... |

#### sentientiasub

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `enrol_sentientiasub_subscription` | 18 | User-linked | `userid` | One row per user subscription against an enrol instance. Lifecycle state machine + Airpay mandate... |

#### sentientia_proctoring

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `quizaccess_sentientia_proctor` | 10 | None | - | Per-quiz proctoring settings. Replaces the get_config/set_config keyed-by-quizid pattern that pol... |

#### airpay

| Table | Cols | Personal-data class | Personal-data fields (by name) | Purpose (from install.xml) |
|---|---|---|---|---|
| `paygw_airpay` | 12 | User-linked | `userid` | Stores airpay related information |
| `paygw_airpay_errorlog` | 9 | User-linked | `userid` | Stores airpay payment error log |
| `paygw_course_enrolmentlog` | 10 | Direct identifiers | `username`, `userid` | Stores courses enroled after payment |


### 2.3 Higher-risk tables found by reading the code

| Table | Plugin | What it holds | Why it matters | Source |
|---|---|---|---|---|
| `local_sentientia_chat_cache` | `local_sentientia_assistant` | Assistant answers to questions that match four cacheable patterns, generated from the asking learner's name, designation, organisation, courses, deadlines, points and team size; keyed on the question text only, kept 3,600 s, never purged | Served to any learner who asks the same question, in any tenant; the quick-action chips send fixed questions, so the first learner's answer reaches the others (pack 01a F-28) | `local/sentientia_assistant/classes/ai_client.php` lines 74 to 95, 387 to 397; `classes/hook_callbacks.php` lines 132 and 140 |
| `local_sentientia_send_log` | `local_sentientia_whatsapp` | One row per WhatsApp, SMS or e-mail send. Column `recipient` holds the destination number or address; `failure_reason` is provider text. | Direct identifier in a generic column name. Erased through the provider (state card, 2026-09-24). | `local/sentientia_whatsapp/db/install.xml` |
| `local_sentientia_user_channel_prefs`, `local_sentientia_user_channel_audit` | `local_sentientia_whatsapp` | Mobile number, opt-in flags per channel, DLT consent text; append-only audit of each preference change with IP address. | Consent provenance for messaging. The audit table is evidence of consent, so it conflicts with an erasure request. | same file |
| `local_privacy_consent_log` | `local_sentientia_privacy` | User id, consent type, granted or withdrawn, IP address, user agent, time. | Proof-of-consent ledger. Kept on erasure by design (record of processing). See Section 4.3 for who writes it. | `local/sentientia_privacy/db/install.xml` |
| `local_sentientia_proctor_sessions`, `_identity`, `_events`, `_recordings`, `_reviews` | `local_sentientia_proctoring` | Per-attempt session with consent timestamp and risk score; identity-check result (provider, match score, pass or fail); event payloads; pointers to recording chunks in object storage (`s3_key`, `retain_until`, `deleted_at`); reviewer notes. | Biometric-adjacent and behavioural data. Proctoring features B1 and B2 are behind flags, off by default (ADR-038). Recording bytes are not in the database. | `local/sentientia_proctoring/db/install.xml` |
| `local_sentientia_chat_log` | `local_sentientia_assistant` | Learner and assistant message text per turn, model name. | Free text typed by the user. The provider formerly declared `null_provider`; it now exports and erases. | `local/sentientia_assistant/db/install.xml` |
| `local_sentientia_ai_ledger` | `local_sentientia_ai` | One row per AI gateway call: component, purpose, user id, customer, tenant, model, token counts, estimated cost, mode. | Links a person to AI use. No prompt text. | `local/sentientia_ai/db/install.xml` |
| `local_sentientia_aiquiz_draft`, `local_sentientia_auth_draft`, `local_sentientia_skai_job`, `local_sentientia_tr_log`, `local_sentientia_rec_log` | AI plugins | Source text or prompts sent to the model, with owner id. | Source text is trainer-pasted content; the UI warns against pasting personal data. The gateway is mock by default (CLAUDE.md workstream G). | respective `install.xml` |
| `local_sentientia_integration_log` | `local_sentientia_integrations` | Column `payload` stores the raw body of every HRMS (KeKa) webhook; reconcile runs store counts only. | The webhook body is employee-event data; its content depends on the HRMS vendor and has not been confirmed. The plugin declares `null_provider`, and no retention task purges the table. See F-3. | `local/sentientia_integrations/webhook.php` (insert of `$input`), `classes/task/keka_reconcile.php`, `classes/privacy/provider.php` |
| `local_sentientia_cart_history`, `_invoices`, `_creditnotes` | `local_sentientia_cart` | Billing e-mail, phone and address; line items; GST invoice fields. | Financial records with statutory retention; erasure needs a legal retention call. | `local/sentientia_cart/db/install.xml` |
| `local_sentientia_calendar_token`, `local_sentientia_calendar_oauth` | `local_sentientia_calendar` | Calendar-feed token (last-used IP); OAuth access and refresh tokens in `*_enc` columns. | Credentials. OAuth tokens are encrypted with Moodle core `\core\encryption::encrypt()`. | `local/sentientia_calendar/classes/oauth/token_vault.php` |
| `local_sentientia_m365_tokens` | `local_sentientia_m365` | Per-user Microsoft 365 access and refresh tokens in `*_enc` columns. | Same mechanism: `\core\encryption::encrypt()`. | `local/sentientia_m365/classes/msal_client.php` |
| `local_sentientia_push_subs` and the push log | `local_sentientia_pwa` | Push subscription (endpoint hash, auth secret, user agent). Log titles and bodies are hashed unless an opt-in setting stores readable copies. | Credential-like data. The private VAPID key is stored with AES-256-GCM. | `local/sentientia_pwa/classes/vapid_key_manager.php`, `settings.php` |
| `local_sentientia_users_sync_errors` | `local_sentientia_users` | E-mail, username, first and last name of each HRMS CSV row that failed validation. | Direct identifiers of people who may not yet have an account. | `local/sentientia_users/db/install.xml` |
| `paygw_course_enrolmentlog`, `paygw_airpay`, `paygw_airpay_errorlog` | `paygw_airpay` | Username, user id, payment and error records. | See F-1: the provider is a `null_provider`. | `payment/gateway/airpay/db/install.xml` |

### 2.4 Personal data outside Sentientia-owned tables

| Store | What | Source |
|---|---|---|
| Moodle `user` table plus BizLMS profile columns | Name, e-mail, username, phone numbers, address, plus 37 `open_*` columns that `local_sentientia_core` creates (`substrate::user_fields()`): HR (employee id, prefix, date of birth, join date, designation, job title, job function, grade, band, HRMS role, employment type, position, supervisor id and employee id, company, client, team, group, field work), education (qualification, education level), location (country id, state, states, region, zone, district, sub-district, village, location), financial (payment information), legacy acceptance flags (privacy policy, terms and conditions), tenant and audit (`open_path`, domain, organisation active, user modified). Other legacy BizLMS columns arrive with the in-place upgrade, for example `gender` (full list to be confirmed on the Stage B rehearsal). No provider declares these columns; the DPDP route clears 8 of the 37; the stock route clears none (pack 01a F-23). | `local/sentientia_core/classes/substrate.php`; `local/sentientia_platform/cli/source_baseline.php` line 437; `privacy_manager::process_deletion()` Step 1; A4 report section 3 |
| `logstore_standard_log` | Every logged event with user id, IP and context. 2,596,772 rows on the local copy. | read-only count, local development database |
| Course, grade, completion, quiz, SCORM and certificate tables | Learning records. | A4 report section 2 |
| Vendored third-party plugins | `admin/tool/certificate` (re-vendored to 5.0.12 in the working tree, ADR-041) and `blocks/learnerscript`. Their tables and providers belong to upstream; the development run shows `block_learnerscript` fails the privacy table-coverage test. | ADR-035 "Proof"; `docs/adr/ADR-041-patching-vendored-plugins.md` |
| Files in `moodledata` | Uploaded files, certificate PDFs, and DPDP export JSON files (Section 4.2). | `local/sentientia_privacy/classes/privacy_manager.php` (`process_download`) |
| Object storage | Proctoring recording chunks, in a bucket named in plugin settings. The delete code defaults the region to `ap-south-1`. | `local/sentientia_proctoring/classes/task/purge_old_recordings.php` |

## 3. Privacy providers

### 3.1 Coverage by plugin

Columns: tables in `install.xml`; tables with a column that links to a user (by name); tables the provider declares through `add_database_table`; external links declared through `add_external_location_link`; whether the provider offers `anonymise_data_for_user()` (personal data out, record kept); how many of the six request-provider methods it has (`get_contexts_for_userid`, `get_users_in_context`, `export_user_data`, `delete_data_for_all_users_in_context`, `delete_data_for_user`, `delete_data_for_users`). Some providers declare tables through shared helper arrays, so a declared count below the user-table count is a lead for review, not a proven gap.

| Plugin path | Release | Provider type | Tables in install.xml | Tables with a user column | Tables declared in provider | External links declared | Offers anonymise_data_for_user | Request-provider methods present |
|---|---|---|---|---|---|---|---|---|
| `local/sentientia_ai` | 0.1.2-alpha | metadata provider | 1 | 1 | 1 | 1 | no | 6 of 6 |
| `local/sentientia_aiquiz` | 0.3.1-alpha | metadata provider | 2 | 1 | 2 | 1 | no | 6 of 6 |
| `local/sentientia_analytics` | 1.2.8-beta | null_provider | 0 | 0 | 0 | 0 | no | none |
| `local/sentientia_api` | 1.3.2 | metadata provider | 9 | 5 | 5 | 1 | no | 6 of 6 |
| `local/sentientia_assistant` | 1.2.2-alpha | metadata provider | 3 | 2 | 2 | 1 | no | 6 of 6 |
| `local/sentientia_authoring` | 0.3.2-alpha | metadata provider | 7 | 2 | 6 | 2 | no | 6 of 6 |
| `local/sentientia_calendar` | 1.2.1-beta | metadata provider | 2 | 2 | 2 | 2 | no | 6 of 6 |
| `local/sentientia_cart` | 1.2.2 | metadata provider | 8 | 6 | 7 | 1 | no | 6 of 6 |
| `local/sentientia_catalog` | 1.0.11-beta | null_provider | 0 | 0 | 0 | 0 | no | none |
| `local/sentientia_challenge` | 1.1.6-alpha | metadata provider | 3 | 3 | 3 | 0 | no (anonymises `createdby` inside its delete; see 3.3) | 6 of 6 |
| `local/sentientia_classroom` | 1.12.0 | metadata provider | 8 | 4 | 6 | 0 | yes | 6 of 6 |
| `local/sentientia_compliance_report` | 1.0.12 | metadata provider | 4 | 4 | 4 | 0 | yes | 6 of 6 |
| `local/sentientia_content_market` | 1.0.3-beta | null_provider | 3 | 0 | 0 | 0 | no | none |
| `local/sentientia_core` | 0.8.0-alpha | metadata provider | 5 | 2 | 3 | 0 | no | 6 of 6 |
| `local/sentientia_courses` | 1.16.0 | metadata provider | 11 | 3 | 5 | 0 | yes | 6 of 6 |
| `local/sentientia_emails` | 1.4.3 | metadata provider | 4 | 2 | 4 | 0 | no | 6 of 6 |
| `local/sentientia_evaluation` | 1.18.0 | metadata provider | 6 | 4 | 3 | 0 | no | 6 of 6 |
| `local/sentientia_exams` | 1.7.1 | metadata provider | 2 | 1 | 1 | 0 | no | 6 of 6 |
| `local/sentientia_gamification` | 1.0.5-beta | metadata provider | 4 | 3 | 3 | 0 | no | 6 of 6 |
| `local/sentientia_integrations` | 1.2.0-beta | null_provider | 1 | 0 | 0 | 0 | no | none |
| `local/sentientia_leaderboard` | 0.2.3-alpha | metadata provider | 5 | 4 | 4 | 0 | no | 6 of 6 |
| `local/sentientia_learningpath` | 1.9.4 | metadata provider | 5 | 3 | 5 | 0 | yes | 6 of 6 |
| `local/sentientia_lifecycle` | 1.1.0-beta | null_provider | 0 | 0 | 0 | 0 | no | none |
| `local/sentientia_live` | 0.2.4-alpha | metadata provider | 6 | 3 | 6 | 0 | no | 6 of 6 |
| `local/sentientia_m365` | 0.2.0-alpha | metadata provider | 1 | 1 | 1 | 1 | no | 6 of 6 |
| `local/sentientia_manager` | 1.3.10 | metadata provider | 2 | 2 | 2 | 0 | no | 6 of 6 |
| `local/sentientia_notifications` | 1.5.2 | metadata provider | 3 | 2 | 2 | 0 | no | 6 of 6 |
| `local/sentientia_org` | 1.6.2 | metadata provider | 2 | 0 | 1 | 0 | yes | 6 of 6 |
| `local/sentientia_pages` | 1.2.2 | null_provider | 0 | 0 | 0 | 0 | no | none |
| `local/sentientia_platform` | 1.13.1 | metadata provider | 11 | 5 | 7 | 0 | no | 6 of 6 |
| `local/sentientia_privacy` | 1.1.0 | metadata provider | 2 | 2 | 2 | 0 | no | 6 of 6 |
| `local/sentientia_proctoring` | 1.2.1 | metadata provider | 5 | 3 | 5 | 1 | yes | 6 of 6 |
| `local/sentientia_programs` | 1.9.2 | metadata provider | 7 | 4 | 4 | 0 | yes | 6 of 6 |
| `local/sentientia_pwa` | 0.6.1-alpha | metadata provider | 2 | 2 | 2 | 0 | no | 6 of 6 |
| `local/sentientia_ratings` | 1.2.1 | metadata provider | 3 | 3 | 3 | 0 | no | 6 of 6 |
| `local/sentientia_recommendations` | 0.1.2-alpha | metadata provider | 1 | 1 | 1 | 1 | no | 6 of 6 |
| `local/sentientia_recompletion` | 1.2.2 | metadata provider | 3 | 2 | 3 | 0 | yes | 6 of 6 |
| `local/sentientia_reports` | 1.3.0 | metadata provider | 1 | 0 | 1 | 0 | no | 6 of 6 |
| `local/sentientia_request` | 1.6.4 | metadata provider | 1 | 1 | 1 | 0 | no | 6 of 6 |
| `local/sentientia_roles` | 1.3.2-beta | metadata provider | 1 | 1 | 1 | 0 | no | 6 of 6 |
| `local/sentientia_skills` | 1.7.2 | metadata provider | 9 | 3 | 3 | 0 | no | 6 of 6 |
| `local/sentientia_skillsai` | 0.1.5-alpha | metadata provider | 5 | 3 | 4 | 1 | no | 6 of 6 |
| `local/sentientia_talent` | 1.0.0-beta | metadata provider | 5 | 2 | 5 | 0 | no | 6 of 6 |
| `local/sentientia_translate` | 0.2.2-alpha | metadata provider | 2 | 1 | 1 | 1 | no | 6 of 6 |
| `local/sentientia_users` | 2.10.0 | metadata provider | 7 | 3 | 5 | 0 | no | 6 of 6 |
| `local/sentientia_whatsapp` | 0.4.1-alpha | metadata provider | 4 | 3 | 3 | 0 | no | 6 of 6 |
| `local/sentientia_xapi` | 1.0.2 | metadata provider | 4 | 2 | 2 | 0 | yes | 6 of 6 |
| `enrol/sentientiasub` | 0.2.0-alpha | metadata provider | 1 | 1 | 1 | 0 | no | 6 of 6 |
| `payment/gateway/airpay` | 1.0.2 | null_provider | 3 | 3 | 0 | 0 | no | none |
| `mod/quiz/accessrule/sentientia_proctoring` | 1.3.1 | null_provider | 1 | 0 | 0 | 0 | no | none |
| `blocks/sentientia_cert_health` | 1.0.1 | null_provider | 0 | 0 | 0 | 0 | no | none |
| `blocks/sentientia_compliance` | 1.0.2-beta | null_provider | 0 | 0 | 0 | 0 | no | none |
| `blocks/sentientia_cron_health` | 1.0.0 | null_provider | 0 | 0 | 0 | 0 | no | none |
| `blocks/sentientia_leaderboard` | 0.1.2-alpha | null_provider | 0 | 0 | 0 | 0 | no | none |
| `blocks/sentientia_recommendations` | 0.1.0-alpha | null_provider | 0 | 0 | 0 | 0 | no | none |
| `blocks/sentientia_trainer` | 1.0.0 | null_provider | 0 | 0 | 0 | 0 | no | none |


### 3.2 Findings from the provider review

| Ref | Finding | Evidence |
|---|---|---|
| F-1 | `paygw_airpay` declares `null_provider` (a positive claim that the plugin holds no personal data) while owning three tables with user columns, one holding the username. It implements only `paygw_provider`. The platform guard test walks `local/*` and does not reach `payment/gateway/*`. The development run of core `provider_test.php` also reports a table-coverage failure for this plugin. Code status: Available (UAT). | `payment/gateway/airpay/classes/privacy/provider.php`, `payment/gateway/airpay/db/install.xml`, ADR-035 "Proof" |
| F-2 | `local_sentientia_content_market` declares `null_provider` correctly (3 tables, no user column), but its reason string `privacy:metadata` is missing from the language pack. Core `tool_dataprivacy` `metadata_registry_test` errors three times, and `provider_test` once, because of it. | ADR-035 "Proof"; `local/sentientia_content_market/classes/privacy/provider.php` |
| F-3 | `local_sentientia_integrations` declares `null_provider` while `local_sentientia_integration_log.payload` stores the raw HRMS webhook body. No retention task purges the table. Content of the payload to be confirmed by the HRMS integration owner. | Section 2.3 |
| F-4 | `local_sentientia_evaluation_template.createdby_userid` is a user column in a table the provider does not declare. Low impact: template authorship. | `local/sentientia_evaluation/db/install.xml`, `classes/privacy/provider.php` |
| F-5 | Providers that wrongly claimed `null_provider` were corrected: courses, ratings, exams and compliance report (2026-09-22), org (2026-09-30), users (2026-10-01), and assistant. A structural test now fails the build when a plugin with a user column declares `null_provider` or has no provider. | provider file headers; `local/sentientia_platform/tests/privacy_coverage_test.php` |
| F-6 | `analytics`, `catalog`, `lifecycle`, `pages` and six blocks own no tables and declare `null_provider`. `sentientia_lifecycle` acts on core user records through an observer, and `sentientia_analytics` refreshes a predictive cache hourly. Confirm neither stores personal data in a cache store or config. | `local/sentientia_lifecycle`, `local/sentientia_analytics/db/tasks.php` |

### 3.3 How the providers are used

- **Stock path (`tool_dataprivacy`).** Core asks each component for its context list, approves it, and calls `delete_data_for_user()`. Sentientia providers take part like any other component.
- **DPDP route (`privacy_manager::process_deletion()`).** It calls every `local_sentientia_*` provider except its own, passing exactly the contexts the provider reports. A provider that offers `anonymise_data_for_user()` (9 do: classroom, compliance_report, courses, learningpath, org, proctoring, programs, recompletion, xapi) is asked to remove personal data but keep the learning, attendance or compliance record against the anonymised user row. Other providers delete. `local_sentientia_challenge` deliberately has no anonymise hook (its provider, lines 30 to 53): attempts and leaderboard rows are erased, and only `createdby` (with the creator's `open_path`) on challenges the user authored is anonymised. A provider that throws makes the request `partial`, not `completed`. Core providers are deliberately not called on this route.

## 4. DPDP functionality

Plugin: `local_sentientia_privacy` 1.1.0 (`2026111300`); requires Moodle 4.5+ and `local_sentientia_platform`. Status: Available (UAT) for the request hub and the 2026-09-24 erasure fixes. The ADR-035 supplementary erasure (commit `bee62721a`, 2026-11-17, an ancestor of the UAT commit) is Available (UAT) with both flags OFF, which is Built (behind a feature flag, off by default).

### 4.1 Request hub (data principal self-service and administration)

| Capability | Behaviour | Source |
|---|---|---|
| Self-service page | `/local/sentientia_privacy/index.php`: "Download My Data" and "Delete My Account" for external and public-tenant users. Internal employees (tenant 1) see a policy notice only and cannot lodge either request from this page. | `README.md`, `classes/privacy_manager.php` (`is_public_tenant`), `templates/self_service.mustache` |
| Request records | `local_privacy_requests`: user, type (`data_download` or `account_delete`), status, reason, admin notes, processor, download path, created, processed, expires. | `db/install.xml` |
| Admin panel | Approve (= erase) and Reject, on pending requests only. Needs `local/sentientia_privacy:manage` and cross-tenant authority (ADR-031). | `index.php`, `db/access.php`, `tests/admin_scope_test.php` (5 tests) |
| Notification | Admins are notified of a new request; the user is notified when an export is ready (message provider `privacy_request`, popup and e-mail on by default). The confirmation text promises review within 3 to 5 business days. | `db/messages.php`, `lang/en/local_sentientia_privacy.php` |
| Languages | English and Hindi (47 and 48 lines). Kannada, Marathi and Swahili are 12-line stubs. | `lang/*/local_sentientia_privacy.php` |
| Capabilities | `local/sentientia_privacy:view` (archetype user), `:manage` (no archetype). | `db/access.php` |

### 4.2 Right of access (export)

- `privacy_manager::collect_user_data()` builds one JSON file: personal information (name, e-mail, username, phone, city, country, employee id, designation, department path, location, join date, last login), enrolments, completions, certificates, the last 50 quiz attempts, activity log, gamification, consent history, and a DPDP notice string.
- The file is written to `moodledata/privacy_exports/` as `user_data_<id>_<timestamp>.json`, unencrypted. The request records an expiry of 72 hours.
- No task deletes expired export files. The state card lists "Auto-expire stale data-export request files" as an open item. Until it is built, files stay on disk after the stated expiry (F-7).
- The stock `tool_dataprivacy` export is a second, separate route. `privacyrequestexpiry` is 604,800 seconds (7 days) on the local copy.

### 4.3 Consent and notices

| Control | What the code does | Gap |
|---|---|---|
| Consent ledger | `local_privacy_consent_log` is append-only. In the privacy, platform, users and core plugins the only writer is Step 5 of `process_deletion()`, which records a withdrawal (`data_processing`, `consented = 0`) without IP or user agent. | No grant event is written by those plugins, so the table holds withdrawals only. It has 0 rows on the local copy. The state card lists a per-customer consent banner as open. A scan of the remaining plugins for other writers was not completed: to be confirmed. |
| Signup consent flags | Self-registration sets `consent_marketing = 0` and `consent_leaderboard = 0` by default (opt-in). The profile page shows both. | Stored as profile data, not in the ledger. Self-registration is admin opt-in. Source: `local/sentientia_users/classes/signup_service.php`, `templates/profile.mustache`. |
| Messaging consent | WhatsApp, SMS and e-mail opt-in per channel with DLT consent text; every preference change is appended to `local_sentientia_user_channel_audit` with IP address. | Plugin release 0.4.1-alpha; send mode per its state card. |
| Privacy notice | The self-service page shows the title "DPDP Act 2023 Notice" and a policy notice for employees. No Sentientia template holds a full notice text with purposes, retention and grievance contact. | Notice content to be authored and approved by the DPO. Moodle's `tool_policy` is not wired: `sitepolicyhandler` is empty on the local copy. |
| Cookie banner | No cookie-consent banner found in `theme/sentientia/templates`, `layout`, `classes`, or in the users, pwa and privacy plugins. Moodle sets its session cookie. | To be confirmed: whether analytics or third-party scripts set non-essential cookies on the public landing page. Owners: Marketing and DPO. |

### 4.4 Right to erasure on the DPDP route

Steps in `privacy_manager::process_deletion()`:

1. Step 0: ask every `local_sentientia_*` provider (except this plugin) to delete or anonymise. A failing provider is recorded.
2. Step 1: anonymise the user row: name to "Deleted User"; e-mail and username to `deleted_<id>_<time>`; clear phone, address, city, description, image alt and the BizLMS columns (employee id, designation, location, team, band, supervisor, date of birth, join date); suspend.
3. Step 2: delete eight named extended tables (points log, badges, streaks, chat log, notification log and preferences, self-assessed skills, skill history). A table that cannot be found is recorded as missing, never skipped silently.
4. Step 3: mark the Moodle user deleted (soft delete).
5. Step 4: status `completed` only if nothing was missed; otherwise `partial` with a note listing what remains.
6. Step 5: write a withdrawal row to the consent ledger.

Anonymised learning records (grades, completions, attempts) are kept for audit. This promise is stated in code comments and covers the DPDP route only. Source: `classes/privacy_manager.php`. Tests: `privacy_manager_test` (3) and `erasure_scope_test` (8), passing 2026-11-17.

Limits of this route: it does not touch `tool_dataprivacy` data (logs, grade history, course completions); a subject whose user row is gone is skipped (`erasurenotapplicable`).

### 4.5 Data principal rights mapped to the code

Section numbers refer to the DPDP Act 2023 and are for the DPO to confirm against the Act and the 2025 Rules.

| Right or duty | Support in Sentientia | Status |
|---|---|---|
| Access to a summary of personal data (s.11) | Export JSON (Section 4.2) and the stock `tool_dataprivacy` export | Available (UAT) |
| Correction and updating (s.12) | Moodle profile editing and HRMS sync; no dedicated correction-request workflow | Not built as a workflow |
| Erasure (s.12) | DPDP route (4.4) and stock route with the ADR-035 supplement | Available (UAT); supplement flags OFF |
| Grievance redressal (s.13) | No grievance-officer contact or ticket flow. The request hub is the only channel. | Planned; owner: DPO |
| Nominate another person (s.14) | Not built | No plan in the repository documents |
| Withdraw consent (s.6) | A withdrawal row on erasure only; no stand-alone control | Partial |
| Breach notification (s.8(6)) | No workflow in code. The incident-response plan is a named gap in `docs/security/ISO-27001-2022-READINESS.md` and `TRUST-TRACK-README.md`. | Planned |
| Children's data (s.9) | No age gate in the repository; public-tenant registration does not collect a date of birth | To be confirmed by the DPO |

## 5. `tool_dataprivacy` integration and ADR-035

### 5.1 Stock configuration on the local copy

| Setting | Value | Meaning |
|---|---|---|
| `automaticdeletionrequests` | 1 | Every user deletion creates a DELETE request. |
| `contactdataprotectionofficer` | 0 | No DPO contact is shown to users. |
| `dporoles` | unset | The privacy officers are the four site administrators. |
| `requireapproval` | unset | Moodle default. |
| `allowfiltering` | 0 | Export is not filtered by the data registry. |
| `privacyrequestexpiry` | 604800 | Request expiry, 7 days. |
| Data registry | 0 purposes, 0 categories, 0 context levels, no site defaults | The stock retention filter is skipped, so every iterated context is deleted. |
| Requests | 515: 286 Deleted (status 10), 229 awaiting approval (status 2) | State after the A4 rehearsal. |

Source: read-only counts from the local development database (Moodle 5.1.3+, MariaDB). UAT values are to be confirmed by IT.

### 5.2 ADR-035 (`docs/adr/ADR-035-privacy-erasure-completeness.md`)

Decision (accepted 2026-11-13): add a supplementary privacy provider inside `local_sentientia_privacy`, with no core edit (ADR-033 decision 4 stands). It recovers two classes of data that stock erasure misses.

| Item | Detail |
|---|---|
| Defect 1 (core bug) | `contextlist_base::set_contextids()` stores `array_unique()` without renumbering, so a duplicate leaves a key hole and the iterator stops at it. The approval loop in `tool_dataprivacy` therefore never approves later contexts. Providers affected in practice: `tool_log`, `report_stats`, `mod_quiz`, `mod_scorm`, `core_course` (favourites). The request still ends "Deleted", with no error. |
| Defect 2 (core gap) | `core_course` discovery finds `course_completions` rows only where the user touched a completion criterion. Rows in courses without criteria, or with untouched criteria, are never found. |
| Flags | `sentientia.privacy.erasure_completeness.enabled` (defect 1) and `sentientia.privacy.completion_gap.enabled` (defect 2). Both default OFF; read at global scope only (customer 0, tenant 0), because a DELETE task runs as an admin and an EXPORT task as the subject. Built (behind a feature flag, off by default); deployed to UAT with both OFF. |
| Self-check | `stock_approval_truncates()`: the truncation recovery turns itself off when core is fixed. |
| Audit event | `\local_sentientia_privacy\event\erasure_supplemented`: counts only, no personal data. |
| Failure handling | A failing component is reported to the DPO after every other component has run. A DELETE still completes and is never reprocessed, so that subject needs the residue sweep. |
| Performance | About 0.9 s discovery per subject; worst case about 60 s for one `tool_log` pass over the heaviest account (165,710 log rows). |
| Upgrade safety | The 5.3 code for every relied-on file is byte-identical to 5.1; only the purpose and category getters in `api.php` differ. |
| Tests | `tests/erasure_completeness_test.php`: 16 tests, 134 assertions. Core `core_privacy` suite without `provider_test.php`: 391 tests OK. `tool_dataprivacy` suite: 323 tests with 3 errors, all from the `content_market` reason string (F-2). Not yet run on the local 5.3 runtime. |

Retention decision required before either flag is turned on (ADR-035 "Retention"):

- (a) Configure the data registry: a system purpose and category (system-level login logs then stay), plus protected purposes for compliance-training course categories; or
- (b) DPO-signed acceptance that authentication logs, audit-trail rows and learning records of erased subjects are deleted, with the privacy notice updated.
- Legal to confirm log-retention duties (CERT-In 2022 directions, DPDP Rules 2025). This has not been verified against the law. The decision is open.

What still remains with both flags ON (ADR-035 "What still remains"): the user row and BizLMS profile columns; log rows where the subject is only `relateduserid` or `realuserid`; 5,727 rows in contexts that no longer exist; 713 log rows of 229 subjects with no user row; the completion gap on partial course-context expiry. The DPDP route is separate.

Cutover gate (blocking, `docs/cutover/SENTIENTIA-CUTOVER-MASTER.md`): no DELETE request is approved on production until (1) the retention decision is recorded, (2) both flags are ON site-wide, and (3) the post-erasure residue check is green on UAT. The residue sweep CLI and the UAT residue check are not built.

## 6. A4 erasure rehearsal results

Source: `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md`. Environment: local copy of a production backup (test data), Moodle 5.1.3+. Ids and counts only.

| Item | Result |
|---|---|
| Requests | 515 automatic delete requests (created by BizLMS when users were deleted, 2023-06 to 2026-05) |
| Processed | 286, all ended "Deleted". 229 subjects had no user row; the stock task throws a `TypeError` for them and would retry for about 34 hours, then notify every admin (about 916 messages on live). They were left awaiting approval. |
| Run time | 23 minutes of processing for 286 requests |
| Notifications | None sent. Message providers off, outbound HTTP and mail disabled, 127 event observers replaced by a recorder, maintenance mode on, cron off. Counts of notifications, messages and queued tasks were equal before and after. |
| Components that deleted | 32. 14 Sentientia plugins ran their delete for all 286 subjects; 3 more for subjects with data. |
| Largest deletions | Quiz question steps 23,396; step data 23,177; SCORM values 9,751; question attempts 7,972; grade history 3,373; log entries 2,346; activity completion 931; certificates 367 |
| Other people's data | Unchanged in all 56 tables checked (for example certificates 11,048 before and after) |
| Verification | `a4_verify.php`: 0 violations, 0 failed deletes |
| Left behind by stock Moodle | 22,944 rows: 15,129 from the truncation bug; 1,713 course completions never discovered; 5,584 in contexts already gone; 518 from the first eight canary requests |
| User row | Still holds username, hashed e-mail, names (275 rows), employee id (272), tenant path (283), phone numbers (150) and other BizLMS fields |
| DPO list page | Likely to throw for any page containing one of the 229 requests, or the six whose requester is user 0 |

Read-only supplement dry run, 2026-11-17 (`tools/privacy-erasure-rehearsal/a4_supplement_plan.php`, inside a rolled-back transaction): of 16,844 leftover rows in scope, 704 would go with a plain stock re-run, 16,140 only through the supplement, and 0 are uncovered; 0 plan failures; plan time per subject 0.46 s minimum, 0.61 s median, 7.8 s maximum. The replay driver `a4_sweep.php --execute` has never run.

## 7. Core contextlist truncation issue

| Item | Detail |
|---|---|
| What | Moodle privacy API: `contextlist_base` iteration stops at the first duplicate context id, so data-deletion and export requests silently skip contexts. |
| Versions | 4.x to 5.1, 5.3 and `main` (code unchanged). |
| Found | 2026-11-13, A4 rehearsal; confirmed by two independent read-only investigations and a deterministic reproduction. |
| Upstream report | Draft only, not filed: `docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md`. It contains no Sentientia or Airpay data. Filing is an owner decision. |
| Mitigation | The ADR-035 supplement (Section 5.2). No core edit. |
| Residual risk | While the flags are OFF every erasure needs a residue check afterwards. Requests already at "Deleted" keep their residue, because stock never reprocesses status 10. |

## 8. Retention settings and tasks

| Data | Control | Default | Source |
|---|---|---|---|
| xAPI statements | Task `purge_old_statements` (02:30 daily), setting `retention_days`; 0 keeps everything | 730 days | `local/sentientia_xapi/settings.php`, `classes/task/purge_old_statements.php` |
| Push log | Task `push_log_retention` (02:00), setting `log_retention_days`; 0 disables | 90 days | `local/sentientia_pwa/settings.php` |
| REST API request log | Task `cleanup` (03:17), setting `log_retention_days` | 90 days | `local/sentientia_api/settings.php` |
| Revoked calendar tokens | Task `purge_old_tokens` (03:17), constant `RETENTION_DAYS` | 90 days | `local/sentientia_calendar/classes/token_manager.php` |
| Live leaderboard SSE events | Task `purge_old_events` (03:00) | 7 days | `local/sentientia_leaderboard/classes/task/purge_old_events.php` |
| Proctoring recordings | Task `purge_old_recordings` (02:23), setting `retention_days`; deletes the stored object with a signed request, then marks the row | 90 days | `local/sentientia_proctoring/settings.php`, `classes/task/purge_old_recordings.php` |
| External-trainer accounts | Task `external_trainer_expiry` (01:15): expiry is last session end plus 30 days, then the account is suspended. Runs whatever the flag says. Built (behind a feature flag, off by default). | 30 days after last session | `local/sentientia_users/classes/task/external_trainer_expiry.php` |
| Evaluation assignments | Task `expire_assignments` (01:00) | per assignment | `local/sentientia_evaluation/db/tasks.php` |
| Moodle logs | `loglifetime` | 0 (never delete) on the local copy; UAT and production values to be confirmed | read-only config query |
| Moodle sessions | `sessiontimeout` | 28,800 seconds (8 hours) on the local copy | read-only config query |
| DPDP export files | None. The stated 72-hour expiry is not enforced by a task. | not enforced | Section 4.2, F-7 |
| Consent ledger and request records | Kept by design on erasure | indefinite | `local/sentientia_privacy/classes/privacy/provider.php` |
| Send log, AI ledger, chat log, notification log, integration log, compliance e-mail log, cart records | No purge task found in the `db/tasks.php` files | none | to be confirmed with a retention schedule from the DPO |

## 9. Security facts relevant to data protection

### 9.1 Encryption and credentials

| Control | Fact | Status | Source |
|---|---|---|---|
| TLS in transit (UAT) | TLS 1.3; certificate issued by Amazon (ACM), terminating on a load balancer. A legacy-protocol (TLS 1.0 and 1.1) test was inconclusive on 2026-09-03. | Available (UAT) | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md` |
| Header hardening | `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and HSTS (1 year, `includeSubDomains`) set by the web-server template on UAT, 2026-09-04. Content-Security-Policy deferred. | Available (UAT) | same file, remediation table; `deploy/moodle-htaccess.template` |
| Third-party tokens at rest | Calendar and Microsoft 365 OAuth tokens encrypted by Moodle core `\core\encryption::encrypt()` | Available (UAT) | Section 2.3 |
| API credentials | SCIM bearer tokens stored as SHA-256 hashes; webhook payloads signed with HMAC-SHA256; xAPI client token and Basic password stored as hashes (columns `token_hash`, `basic_pass_hash`; algorithm not checked here) | Available (UAT) | `local/sentientia_api/classes/scim/client.php`, `classes/webhooks/signer.php`, `local/sentientia_xapi/db/install.xml` |
| Web push | RFC 8291 payload encryption; VAPID private key stored with AES-256-GCM under a master key. The 2026-05-21 audit found the key stored unencrypted; the current code encrypts it. | Built | `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md`, `local/sentientia_pwa/classes/vapid_key_manager.php` |
| Database encryption at rest | Production (BizLMS) is described as AWS RDS with KMS-encrypted storage and snapshots. This repository holds no infrastructure code to verify it. The readiness document lists key management as a gap. | To be confirmed by IT | `docs/security/ISO-27001-2022-READINESS.md`, rows A.9.1.1 and A.9.1.2 |
| Export files and `moodledata` | No application-level encryption of DPDP export files or uploads; depends on volume encryption. | To be confirmed by IT | Section 4.2 |
| UAT database credentials | The posture review recorded that the UAT database user was the RDS superuser and that the password had been sent by e-mail in plain text; rotation to a least-privilege user was deferred until after install. Current state to be confirmed. | To be confirmed by IT | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md`, configuration checklist |

### 9.2 Logging of personal data

- Moodle's standard log store keeps user id, IP and context for every event.
- The event `erasure_supplemented` carries counts only.
- Web push log titles and bodies are hashed unless an opt-in setting stores readable copies.
- The AI gateway ledger records metadata, not prompts. AI draft tables keep the source text sent to the model.
- Credentials are not logged: a pre-commit check enforces this (CLAUDE.md section 5; `.claude/hooks/pre-commit.sh`).
- `local_sentientia_integration_log.payload` stores raw HRMS webhook bodies (F-3).
- The payment error log table keeps payment errors per user (F-1).

### 9.3 Data residency and hosting

| Fact | Detail | Source |
|---|---|---|
| UAT | `https://academy2.airpay.ninja`, Moodle 5.3, reached through an SSH tunnel; behind an AWS Application Load Balancer; Apache on Ubuntu; database recorded as MySQL 8.4 on RDS in the 2026-09-03 posture document (written while UAT ran 5.2). The AWS region is not recorded in the repository. | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md`; region to be confirmed by IT |
| Production (current) | `https://www.airpay.academy`, BizLMS on Moodle 4.1.2, MySQL 8.0.44 on AWS RDS. Sentientia has not replaced it. | `CLAUDE.md`; `docs/security/ISO-27001-2022-READINESS.md` |
| Development | Local XAMPP and a local 5.3 runtime on a developer machine, holding a test import of a production backup that includes real e-mail addresses. | A4 report; `docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` |
| Proctoring recordings | Bucket and region are plugin settings; the delete code defaults the region to `ap-south-1`. Not in use while the flag is OFF. | `local/sentientia_proctoring/classes/task/purge_old_recordings.php` |
| AI processing | The gateway is mock by default. A live call sends prompt text to the model vendor. A self-hosted model option is under discussion for the AI pack. | `local/sentientia_ai`; CLAUDE.md workstream G |
| External processors | HRMS (KeKa) webhook and API; Microsoft 365; WhatsApp Business API provider; ElevenLabs and Gamma (content pipeline; no personal data by rule). Residency of each to be confirmed by the vendor-management owner. | `local/sentientia_integrations`, `local/sentientia_m365`, `local/sentientia_whatsapp`; CLAUDE.md sections 9 and 11 |
| Cross-border transfer | Not assessed in the repository. DPDP s.16 to be assessed by the DPO. | to be confirmed |

### 9.4 Backups

| Fact | Detail | Source |
|---|---|---|
| Production | AWS RDS automated backups, default 7-day retention (as stated). The readiness document names backup schedule, retention, tested restore and backup encryption as a gap (control A.11.3.1), with a target of daily snapshots, 30-day retention and a monthly test restore. | `docs/security/ISO-27001-2022-READINESS.md` |
| UAT | Backup procedure is not recorded in the repository. To be confirmed by IT. | n/a |
| Local copy | A pre-rehearsal SQL dump of all 717 `mdl_` tables, taken 2026-11-13 and kept outside the repository. A 48-hour backup gate applies before any execute run; the gate expired on 2026-11-15, so a new dump is required. | A4 report; ADR-035 "Proof" |
| Erasure and backups | Erasure does not reach backups. No documented rule on how long backups holding erased subjects are kept. | to be confirmed by IT and the DPO |
| Readiness claim to verify | The readiness document states a 7-year audit-trail retention "per RBI guidelines". No code or setting enforces a 7-year rule, and `loglifetime` is 0 locally. Treat as unverified. | `docs/security/ISO-27001-2022-READINESS.md`, row A.8.1.4 |

## 10. Findings and gaps for the security and privacy reviewers

| Ref | Item | Severity (proposed) | Class | Proposed owner and next step |
|---|---|---|---|---|
| F-1 | `paygw_airpay` declares `null_provider` over 3 user tables | High (access and erasure requests return nothing) | CWE-359; DPDP s.11, s.12 | Engineering: write a metadata provider with export and delete; extend the guard test to `payment/gateway` |
| F-2 | `content_market` reason string missing | Low | Test failure, not a data risk | Engineering: add the string |
| F-3 | Raw HRMS webhook body stored; no retention; `null_provider` | Medium to High (content to be confirmed) | CWE-532; DPDP s.8(7) | Engineering and HRMS integration owner: classify the payload, add a purge task and a provider |
| F-4 | `evaluation_template.createdby_userid` undeclared | Low | Provider coverage | Engineering |
| F-5 | ADR-035 flags OFF: stock erasure leaves 22,944 rows for 286 subjects; retention decision open | High until decided | DPDP s.8(7), s.12 | DPO: choose retention option (a) or (b); product owner: turn flags on after the UAT residue check |
| F-6 | Stock Moodle erases none of the BizLMS profile columns | Medium | DPDP s.12 | Product owner and DPO: decide anonymise-on-erasure for the profile row |
| F-7 | Export files are neither expired nor encrypted on disk | Medium | CWE-312, CWE-459; DPDP s.8(5) | Engineering: expiry task; IT: volume encryption |
| F-8 | Consent ledger holds withdrawals only; no banner; `tool_policy` not configured | Medium | DPDP s.6 | DPO and product owner: define the consent journey; Engineering: write grant events with IP and user agent |
| F-9 | 229 stuck requests: the stock task fails on subjects without a user row and would notify every admin | Medium (operational) | Availability | DPO: cancel or reject with a recorded reason; set `dporoles` before cutover |
| F-10 | No retention task for send log, chat log, AI ledger, notification log, integration log, cart records | Medium | DPDP s.8(7) | DPO: retention schedule; Engineering: tasks |
| F-11 | No grievance, nominee or breach-notification workflow in code | Medium | DPDP s.8(6), s.13, s.14 | DPO and Security: define; Engineering: build after approval |
| F-12 | Database credential and key-management state on UAT and production unverified | To be confirmed | CWE-798; ISO/IEC 27001 A.9.1.2 | IT |
| F-13 | Residue sweep CLI and UAT residue check not built | High for cutover | Cutover gate | Engineering |
| F-14 to F-28 | Added in pack 01a section 16: download link not served (F-14); unchecked view capability (F-15); policy tool that writes acceptances (F-16); unevidenced notice claims (F-17); DPDP route skips core `delete_user()` (F-18); self-service limited to tenant 77 (F-19); unbounded prefix in an unused method (F-20); no processor contracts and the AI keys that enable a transfer (F-21); AI gateway bypass by the assistant chat, agent and authoring (F-22); core `user` columns outside every provider (F-23); DPDP route skips non-local providers (F-24); rights view keyed on a capability (F-25); undeclared `tenant_share.shared_by` (F-26); public certificate verification shows the holder's name (F-27); shared assistant response cache across users and tenants (F-28) | F-22, F-23, F-28 proposed High | See pack 01a | See pack 01a section 16 |

## 11. Source index

| Topic | Path |
|---|---|
| DPDP plugin | `moodle-enhancement/local/sentientia_privacy/` (README, `classes/privacy_manager.php`, `classes/local/erasure_completeness.php`, `db/install.xml`, `db/feature_flags.php`, `db/access.php`) |
| State card | `moodle-enhancement/state-cards/sentientia_privacy-state.md` |
| Erasure ADR | `moodle-enhancement/docs/adr/ADR-035-privacy-erasure-completeness.md` |
| Related ADRs | ADR-031 (cross-tenant authority), ADR-033 (5.3 target, no core edits), ADR-036 (training programs, external trainers), ADR-038 (assessment integrity), ADR-041 (vendored plugins) |
| A4 results | `moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Core bug draft report | `moodle-enhancement/docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md` |
| Cutover gate | `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` |
| Rehearsal tooling | `moodle-enhancement/tools/privacy-erasure-rehearsal/` |
| Structural guard | `moodle-enhancement/local/sentientia_platform/tests/privacy_coverage_test.php` |
| UAT security posture | `moodle-enhancement/docs/security/UAT-SECURITY-POSTURE-2026-09-03.md` |
| ISO and trust readiness | `moodle-enhancement/docs/security/ISO-27001-2022-READINESS.md`, `TRUST-TRACK-README.md` |
| Crypto audit | `moodle-enhancement/docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` |
| Table inventory | every `db/install.xml` listed in Section 2 |

## Corrections (fact-base cross-check, 20 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | `local_sentientia_org` release is 1.6.2 (second `$plugin->release` assignment in `version.php`). | `moodle-enhancement/local/sentientia_org/version.php` line 76 |
| 2 | Provider coverage extended to the theme: `theme_sentientia` has a real metadata provider, so all 57 product components have one (43 real, 14 `null_provider`). | `theme/sentientia/classes/privacy/provider.php` |
| 3 | Re-verified unchanged: 185 tables in 46 `install.xml` files; 14 `null_provider` classes by `implements` clause. | scripted count, 20 Nov 2026 |

- Wording: short decision references (ADR section codes and a working-folder name) replaced by ADR names, per master prompt rule 3.2 (2 places).
