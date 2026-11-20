# Fact base 30: APIs and integrations

Confidential: Airpay Payment Services internal. Not for external distribution.

Scope: every external web service function our plugins define, the REST/SCIM/webhook/LTI/xAPI surface, the mobile web-service audit, SSO, third-party integrations and the AI gateway API. Facts only; each row has a source path under `moodle-enhancement/` (canonical tree). Plugins are counted once. Git placement: commit `af6e65c0e` is what runs on UAT; later work is "Built (verified on development)". Date of extraction: 2026-11-20.

## 1. Key numbers

| Measure | Value | Source |
|---|---|---|
| External web service functions defined by our plugins | **174** (68 read, 106 write) | all `db/services.php` under `local/` and `payment/gateway/airpay/` |
| Components that define them | 24 (23 `local_sentientia_*` plus `paygw_airpay`) | same |
| Functions on UAT code (`af6e65c0e`) | 172 | `git show af6e65c0e:` of the proctoring file shows 12 of its 14 functions |
| Functions added after UAT code | 2 (`local_sentientia_proctoring_secure_report`, `..._integrity_report`), flagged | commit `76e8ec572` |
| Functions with `ajax => true` and `loginrequired => true` | 174 of 174 | `db/services.php` files |
| Functions declaring no capability in the definition | 5 | see 5.x notes below |
| Distinct capabilities gating the functions | 67 | `db/services.php` files |
| Pre-built Moodle external services defined | 2 (`Sentientia Public API v1`, `Airpay AI Assistant`) | `local/sentientia_api/db/services.php`, `local/sentientia_assistant/db/services.php` |
| Functions marked for the official Moodle mobile service | **0** | no `services` key lists `MOODLE_OFFICIAL_MOBILE_SERVICE` |
| Raw-HTTP (non-web-service) endpoints | 19 entry-point files across 10 components (18 until 21 Nov 2026, when `payment/gateway/airpay/error.php` was added): 9 local plugins plus `paygw_airpay` (section 8; corrected 20 Nov 2026 from 12 files across 6 plugins, which left out the leaderboard stream, the personal calendar feed, the cart payment callback and the three gateway pages) | per-file `NO_MOODLE_COOKIES` / `AJAX_SCRIPT` and plugin file listings |
| Public API v1 functions | 7 (6 read, 1 write) plus OpenAPI document | `local/sentientia_api/db/services.php` |
| Outbound webhook events | 3 | `local/sentientia_api/classes/webhooks/subscription.php` |
| AI gateway consumers routed through `client::complete()` | 6 | section 11 |

## 2. How to read the status labels in this file

"Available (UAT)" means the code is in the commit that runs on UAT. It does not say a feature flag is on. Flag state is per customer and tenant and is recorded in the flag inventory fact file. Anything that ships behind a flag is also marked "behind a feature flag, off by default" where the registry default is `false`.

## 3. Counts by category

| Category | Count | Notes |
|---|---|---|
| Read functions | 68 | |
| Write functions | 106 | write functions in `local_sentientia_api` are additionally gated by `sentientia.api.write.enabled` |
| May-2026 audit class MOBILE-READY (current names) | 33 | of 156 audited functions still present |
| May-2026 audit class DESKTOP-ONLY | 90 | |
| May-2026 audit class SENSITIVE-ADMIN | 33 | |
| Added after the audit, not classified | 18 | api 7, proctoring 2, pwa 3, leaderboard 3, assistant 2, `paygw_airpay` 1 |
| Raw-HTTP entry-point files | 18 | section 8 |
| Integration rows in the integrations table | 15 | section 10 |

## 4. Plugin-level inventory

| # | Component | Release | Functions | Read | Write | Audited MOBILE-READY | Audited DESKTOP-ONLY | Audited SENSITIVE-ADMIN | Not in May audit | Source |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `paygw_airpay` | 1.0.2 | 1 | 1 | 0 | 0 | 0 | 0 | 1 | `moodle-enhancement/payment/gateway/airpay/db/services.php` |
| 2 | `local_sentientia_api` | 1.3.2 | 7 | 6 | 1 | 0 | 0 | 0 | 7 | `moodle-enhancement/local/sentientia_api/db/services.php` |
| 3 | `local_sentientia_assistant` | 1.2.2-alpha | 4 | 1 | 3 | 2 | 0 | 0 | 2 | `moodle-enhancement/local/sentientia_assistant/db/services.php` |
| 4 | `local_sentientia_cart` | 1.2.2 | 9 | 4 | 5 | 5 | 2 | 2 | 0 | `moodle-enhancement/local/sentientia_cart/db/services.php` |
| 5 | `local_sentientia_challenge` | 1.1.6-alpha | 8 | 3 | 5 | 5 | 2 | 1 | 0 | `moodle-enhancement/local/sentientia_challenge/db/services.php` |
| 6 | `local_sentientia_classroom` | 1.12.0 | 15 | 6 | 9 | 4 | 8 | 3 | 0 | `moodle-enhancement/local/sentientia_classroom/db/services.php` |
| 7 | `local_sentientia_courses` | 1.16.0 | 15 | 3 | 12 | 1 | 10 | 4 | 0 | `moodle-enhancement/local/sentientia_courses/db/services.php` |
| 8 | `local_sentientia_emails` | 1.4.3 | 5 | 2 | 3 | 0 | 5 | 0 | 0 | `moodle-enhancement/local/sentientia_emails/db/services.php` |
| 9 | `local_sentientia_evaluation` | 1.18.0 | 8 | 2 | 6 | 1 | 4 | 3 | 0 | `moodle-enhancement/local/sentientia_evaluation/db/services.php` |
| 10 | `local_sentientia_exams` | 1.7.1 | 3 | 1 | 2 | 1 | 1 | 1 | 0 | `moodle-enhancement/local/sentientia_exams/db/services.php` |
| 11 | `local_sentientia_leaderboard` | 0.2.3-alpha | 3 | 2 | 1 | 0 | 0 | 0 | 3 | `moodle-enhancement/local/sentientia_leaderboard/db/services.php` |
| 12 | `local_sentientia_learningpath` | 1.9.4 | 12 | 4 | 8 | 2 | 8 | 2 | 0 | `moodle-enhancement/local/sentientia_learningpath/db/services.php` |
| 13 | `local_sentientia_manager` | 1.3.10 | 8 | 3 | 5 | 0 | 6 | 2 | 0 | `moodle-enhancement/local/sentientia_manager/db/services.php` |
| 14 | `local_sentientia_notifications` | 1.5.2 | 6 | 2 | 4 | 1 | 3 | 2 | 0 | `moodle-enhancement/local/sentientia_notifications/db/services.php` |
| 15 | `local_sentientia_org` | 1.6.2 | 3 | 1 | 2 | 0 | 2 | 1 | 0 | `moodle-enhancement/local/sentientia_org/db/services.php` |
| 16 | `local_sentientia_proctoring` | 1.2.1 | 14 | 5 | 9 | 0 | 12 | 0 | 2 | `moodle-enhancement/local/sentientia_proctoring/db/services.php` |
| 17 | `local_sentientia_programs` | 1.9.2 | 12 | 5 | 7 | 3 | 6 | 3 | 0 | `moodle-enhancement/local/sentientia_programs/db/services.php` |
| 18 | `local_sentientia_pwa` | 0.6.1-alpha | 3 | 1 | 2 | 0 | 0 | 0 | 3 | `moodle-enhancement/local/sentientia_pwa/db/services.php` |
| 19 | `local_sentientia_ratings` | 1.2.1 | 1 | 0 | 1 | 1 | 0 | 0 | 0 | `moodle-enhancement/local/sentientia_ratings/db/services.php` |
| 20 | `local_sentientia_reports` | 1.3.0 | 3 | 1 | 2 | 0 | 2 | 1 | 0 | `moodle-enhancement/local/sentientia_reports/db/services.php` |
| 21 | `local_sentientia_request` | 1.6.4 | 6 | 3 | 3 | 3 | 3 | 0 | 0 | `moodle-enhancement/local/sentientia_request/db/services.php` |
| 22 | `local_sentientia_roles` | 1.3.2-beta | 8 | 4 | 4 | 0 | 5 | 3 | 0 | `moodle-enhancement/local/sentientia_roles/db/services.php` |
| 23 | `local_sentientia_skills` | 1.7.2 | 14 | 5 | 9 | 4 | 8 | 2 | 0 | `moodle-enhancement/local/sentientia_skills/db/services.php` |
| 24 | `local_sentientia_users` | 2.10.0 | 6 | 3 | 3 | 0 | 3 | 3 | 0 | `moodle-enhancement/local/sentientia_users/db/services.php` |
| | **Total (24 components)** | | **174** | **68** | **106** | **33** | **90** | **33** | **18** | |

## 5. Full external function inventory (174)

Columns: type and capability are read from `db/services.php`. "May-2026 audit class" is the category assigned in `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` (the audit used the earlier `local_airpay_*` names; names were mapped one-to-one). "Not audited" means the function was added after the audit. Every function declares `ajax => true` and `loginrequired => true`. No function lists `MOODLE_OFFICIAL_MOBILE_SERVICE` in its `services` array.

### 5.1 `paygw_airpay` (1 functions)

Source: `moodle-enhancement/payment/gateway/airpay/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `paygw_airpay_get_form` | read | `-` | Not audited | Available (UAT) | Returns the configuration settings to be used in js |

### 5.2 `local_sentientia_api` (7 functions)

Source: `moodle-enhancement/local/sentientia_api/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_api_v1_list_courses` | read | `local/sentientia_api:read` | Not audited | Built (behind a feature flag, off by default) | v1: List courses visible to the token user within their tenant. |
| `local_sentientia_api_v1_get_course` | read | `local/sentientia_api:read` | Not audited | Built (behind a feature flag, off by default) | v1: Fetch a single course by id, tenant-scoped. |
| `local_sentientia_api_v1_list_enrolments` | read | `local/sentientia_api:read` | Not audited | Built (behind a feature flag, off by default) | v1: List active enrolments in a course, tenant-scoped. |
| `local_sentientia_api_v1_list_completions` | read | `local/sentientia_api:read` | Not audited | Built (behind a feature flag, off by default) | v1: List course completions for a course, tenant-scoped. |
| `local_sentientia_api_v1_list_skills` | read | `local/sentientia_api:read` | Not audited | Built (behind a feature flag, off by default) | v1: List the skill catalogue (global definitions). |
| `local_sentientia_api_v1_create_enrolment` | write | `local/sentientia_api:write` | Not audited | Built (behind a feature flag, off by default) | v1: Enrol a user into a course (manual enrolment), tenant-scoped. WRITE. |
| `local_sentientia_api_v1_openapi` | read | `local/sentientia_api:read` | Not audited | Built (behind a feature flag, off by default) | v1: Return the OpenAPI 3.0 specification document describing the v1 surface. |

### 5.3 `local_sentientia_assistant` (4 functions)

Source: `moodle-enhancement/local/sentientia_assistant/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_assistant_ask` | write | `-` | MOBILE-READY | Available (UAT) | Ask the AI learning assistant a question |
| `local_sentientia_assistant_get_history` | read | `-` | MOBILE-READY | Available (UAT) | Get chat history |
| `local_sentientia_assistant_agent_turn` | write | `local/sentientia_assistant:useagent` | Not audited | Available (UAT) | Agentic copilot: propose (and run read-only) a turn |
| `local_sentientia_assistant_agent_confirm` | write | `local/sentientia_assistant:useagent` | Not audited | Available (UAT) | Agentic copilot: confirm + execute a proposed write action |

### 5.4 `local_sentientia_cart` (9 functions)

Source: `moodle-enhancement/local/sentientia_cart/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_cart_add_item` | write | `local/sentientia_cart:purchase` | MOBILE-READY | Available (UAT) | Add a course to the current user cart |
| `local_sentientia_cart_remove_item` | write | `local/sentientia_cart:purchase` | MOBILE-READY | Available (UAT) | Remove a course from the current user cart |
| `local_sentientia_cart_get_cart` | read | `local/sentientia_cart:view` | MOBILE-READY | Available (UAT) | Get current cart contents + totals |
| `local_sentientia_cart_checkout` | write | `local/sentientia_cart:purchase` | DESKTOP-ONLY | Available (UAT) | Convert cart to order, initiate payment |
| `local_sentientia_cart_list_orders` | read | `local/sentientia_cart:view` | MOBILE-READY | Available (UAT) | List own orders (or all orders for admins) |
| `local_sentientia_cart_get_order` | read | `local/sentientia_cart:view` | MOBILE-READY | Available (UAT) | Get order detail by order number |
| `local_sentientia_cart_refund` | write | `local/sentientia_cart:refund` | SENSITIVE-ADMIN | Available (UAT) | Issue a full or partial refund (admin) |
| `local_sentientia_cart_set_price` | write | `local/sentientia_cart:manageprices` | SENSITIVE-ADMIN | Available (UAT) | Set / unset price for a course (admin) |
| `local_sentientia_cart_daily_sums` | read | `local/sentientia_cart:viewallorders` | DESKTOP-ONLY | Available (UAT) | Daily payment sums report for finance |

### 5.5 `local_sentientia_challenge` (8 functions)

Source: `moodle-enhancement/local/sentientia_challenge/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_challenge_list_challenges` | read | `local/sentientia_challenge:view` | MOBILE-READY | Available (UAT) | Paginated list of challenges (active by default). |
| `local_sentientia_challenge_get_challenge` | read | `local/sentientia_challenge:view` | MOBILE-READY | Available (UAT) | Get one challenge with caller progress. |
| `local_sentientia_challenge_create_challenge` | write | `local/sentientia_challenge:manage` | DESKTOP-ONLY | Available (UAT) | Create a new challenge. |
| `local_sentientia_challenge_update_challenge` | write | `local/sentientia_challenge:manage` | DESKTOP-ONLY | Available (UAT) | Update an existing challenge. |
| `local_sentientia_challenge_delete_challenge` | write | `local/sentientia_challenge:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete a challenge + its attempts + leaderboard rows. |
| `local_sentientia_challenge_join_challenge` | write | `local/sentientia_challenge:participate` | MOBILE-READY | Available (UAT) | Join (enrol) the caller into a challenge. |
| `local_sentientia_challenge_leave_challenge` | write | `local/sentientia_challenge:participate` | MOBILE-READY | Available (UAT) | Leave a challenge (deletes the caller's attempt row). |
| `local_sentientia_challenge_get_leaderboard` | read | `local/sentientia_challenge:view` | MOBILE-READY | Available (UAT) | Top-N leaderboard (per challenge or aggregate, tenant-scoped). |

### 5.6 `local_sentientia_classroom` (15 functions)

Source: `moodle-enhancement/local/sentientia_classroom/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_classroom_list_classrooms` | read | `local/sentientia_classroom:view` | MOBILE-READY | Available (UAT) | List classrooms for the shared datatable |
| `local_sentientia_classroom_change_status` | write | `local/sentientia_classroom:update` | DESKTOP-ONLY | Available (UAT) | Change classroom status (active/cancelled/completed) |
| `local_sentientia_classroom_delete_classroom` | write | `local/sentientia_classroom:delete` | SENSITIVE-ADMIN | Available (UAT) | Delete a classroom and all its sessions |
| `local_sentientia_classroom_list_sessions` | read | `local/sentientia_classroom:view` | MOBILE-READY | Available (UAT) | List sessions for a classroom (Sessions tab datatable) |
| `local_sentientia_classroom_delete_session` | write | `local/sentientia_classroom:update` | SENSITIVE-ADMIN | Available (UAT) | Delete a session and its attendance records |
| `local_sentientia_classroom_list_users` | read | `local/sentientia_classroom:view` | DESKTOP-ONLY | Available (UAT) | List enrolled users for a classroom (Users tab datatable) |
| `local_sentientia_classroom_unenrol_user` | write | `local/sentientia_classroom:update` | DESKTOP-ONLY | Available (UAT) | Remove a user from a classroom roster (cascades attendance) |
| `local_sentientia_classroom_list_attendance` | read | `local/sentientia_classroom:view` | DESKTOP-ONLY | Available (UAT) | List attendance for a session (Attendance UI) |
| `local_sentientia_classroom_mark_attendance` | write | `local/sentientia_classroom:attendance` | DESKTOP-ONLY | Available (UAT) | Mark a single user attendance for a session |
| `local_sentientia_classroom_bulk_mark_attendance` | write | `local/sentientia_classroom:attendance` | DESKTOP-ONLY | Available (UAT) | Bulk-mark attendance for many users at once |
| `local_sentientia_classroom_waitlist_join` | write | `local/sentientia_classroom:view` | MOBILE-READY | Available (UAT) | Join the waiting list for a classroom |
| `local_sentientia_classroom_waitlist_leave` | write | `local/sentientia_classroom:view` | MOBILE-READY | Available (UAT) | Leave the waiting list |
| `local_sentientia_classroom_list_waitlist` | read | `local/sentientia_classroom:view` | DESKTOP-ONLY | Available (UAT) | List the waiting list for a classroom |
| `local_sentientia_classroom_preview_audience` | read | `local/sentientia_classroom:manage` | DESKTOP-ONLY | Available (UAT) | Preview users matching a target-audience filter before bulk-enrolling |
| `local_sentientia_classroom_bulk_enrol_by_audience` | write | `local/sentientia_classroom:manage` | SENSITIVE-ADMIN | Available (UAT) | Resolve a target-audience filter and bulk-enrol all matching users into a classroom |

### 5.7 `local_sentientia_courses` (15 functions)

Source: `moodle-enhancement/local/sentientia_courses/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_courses_list_courses` | read | `local/sentientia_courses:view` | MOBILE-READY | Available (UAT) | List courses for the shared datatable |
| `local_sentientia_courses_toggle_visibility` | write | `local/sentientia_courses:visibility` | DESKTOP-ONLY | Available (UAT) | Show or hide a course |
| `local_sentientia_courses_delete_course` | write | `local/sentientia_courses:delete` | SENSITIVE-ADMIN | Available (UAT) | Delete a course |
| `local_sentientia_courses_add_featured` | write | `local/sentientia_courses:manage` | DESKTOP-ONLY | Available (UAT) | Pin a course to the featured-courses widget |
| `local_sentientia_courses_remove_featured` | write | `local/sentientia_courses:manage` | DESKTOP-ONLY | Available (UAT) | Unpin a course from the featured-courses widget |
| `local_sentientia_courses_reorder_featured` | write | `local/sentientia_courses:manage` | DESKTOP-ONLY | Available (UAT) | Reorder featured-courses entries |
| `local_sentientia_courses_list_course_enrolments` | read | `local/sentientia_courses:view` | DESKTOP-ONLY | Available (UAT) | List users enrolled in a course |
| `local_sentientia_courses_enrol_single` | write | `local/sentientia_courses:enrol` | DESKTOP-ONLY | Available (UAT) | Enrol one user in a course by email/empid/username |
| `local_sentientia_courses_unenrol_single` | write | `local/sentientia_courses:enrol` | DESKTOP-ONLY | Available (UAT) | Unenrol one user from a course (manual instance) |
| `local_sentientia_courses_share_course` | write | `local/sentientia_courses:share_to_tenant` | SENSITIVE-ADMIN | Available (UAT) | Share a course to one or more tenants |
| `local_sentientia_courses_unshare_course` | write | `local/sentientia_courses:share_to_tenant` | SENSITIVE-ADMIN | Available (UAT) | Withdraw a course-share from a tenant |
| `local_sentientia_courses_list_course_shares` | read | `local/sentientia_courses:view` | DESKTOP-ONLY | Available (UAT) | Get current + historic shares for a course |
| `local_sentientia_courses_request_course` | write | `local/sentientia_courses:request_course` | DESKTOP-ONLY | Available (UAT) | Request an Airpay course be shared to my tenant |
| `local_sentientia_courses_approve_request` | write | `local/sentientia_courses:approve_request` | SENSITIVE-ADMIN | Available (UAT) | Approve a pending share-request (admin) |
| `local_sentientia_courses_reject_request` | write | `local/sentientia_courses:approve_request` | DESKTOP-ONLY | Available (UAT) | Reject a pending share-request (admin) |

### 5.8 `local_sentientia_emails` (5 functions)

Source: `moodle-enhancement/local/sentientia_emails/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_emails_get_template` | read | `local/sentientia_emails:manage_templates` | DESKTOP-ONLY | Available (UAT) | Get template content (DB override or file default) |
| `local_sentientia_emails_save_template` | write | `local/sentientia_emails:manage_templates` | DESKTOP-ONLY | Available (UAT) | Save a template override to DB |
| `local_sentientia_emails_revert_template` | write | `local/sentientia_emails:manage_templates` | DESKTOP-ONLY | Available (UAT) | Delete DB override, revert to file template |
| `local_sentientia_emails_preview_template` | read | `local/sentientia_emails:preview` | DESKTOP-ONLY | Available (UAT) | Render a template preview with sample data |
| `local_sentientia_emails_toggle_rule` | write | `local/sentientia_emails:manage_rules` | DESKTOP-ONLY | Available (UAT) | Toggle a notification rule enabled/disabled |

### 5.9 `local_sentientia_evaluation` (8 functions)

Source: `moodle-enhancement/local/sentientia_evaluation/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_evaluation_list_evaluations` | read | `local/sentientia_evaluation:manage` | DESKTOP-ONLY | Available (UAT) | List evaluations for shared datatable |
| `local_sentientia_evaluation_change_status` | write | `local/sentientia_evaluation:manage` | DESKTOP-ONLY | Available (UAT) | Change evaluation form status (draft/active/archived) |
| `local_sentientia_evaluation_delete_evaluation` | write | `local/sentientia_evaluation:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete an evaluation form (cascades through questions and responses) |
| `local_sentientia_evaluation_delete_question` | write | `local/sentientia_evaluation:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete a question from an evaluation form |
| `local_sentientia_evaluation_reorder_questions` | write | `local/sentientia_evaluation:manage` | DESKTOP-ONLY | Available (UAT) | Reorder questions within an evaluation form |
| `local_sentientia_evaluation_submit_response` | write | `local/sentientia_evaluation:respond` | MOBILE-READY | Available (UAT) | Submit a response to an evaluation form (learner) |
| `local_sentientia_evaluation_preview_audience` | read | `local/sentientia_evaluation:manage` | DESKTOP-ONLY | Available (UAT) | Preview the user count for a target-audience filter |
| `local_sentientia_evaluation_bulk_assign_by_audience` | write | `local/sentientia_evaluation:manage` | SENSITIVE-ADMIN | Available (UAT) | Assign an evaluation to every user matching a target-audience filter |

### 5.10 `local_sentientia_exams` (3 functions)

Source: `moodle-enhancement/local/sentientia_exams/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_exams_list_exams` | read | `local/sentientia_exams:view` | MOBILE-READY | Available (UAT) | List exams for shared datatable |
| `local_sentientia_exams_toggle_status` | write | `local/sentientia_exams:manage` | DESKTOP-ONLY | Available (UAT) | Activate or deactivate an exam |
| `local_sentientia_exams_delete_exam` | write | `local/sentientia_exams:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete an exam wrapper (does not affect underlying quiz) |

### 5.11 `local_sentientia_leaderboard` (3 functions)

Source: `moodle-enhancement/local/sentientia_leaderboard/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_leaderboard_get_board` | read | `local/sentientia_leaderboard:view` | Not audited | Available (UAT) | Get the top-N entries for a leaderboard, tenant-scoped, opt-outs filtered. |
| `local_sentientia_leaderboard_list_boards` | read | `local/sentientia_leaderboard:view` | Not audited | Available (UAT) | List active boards visible to the caller (tenant-scoped unless :viewall). |
| `local_sentientia_leaderboard_set_optout` | write | `-` | Not audited | Available (UAT) | Set the caller's opt-out preference. Privacy-mandated reversible toggle. |

### 5.12 `local_sentientia_learningpath` (12 functions)

Source: `moodle-enhancement/local/sentientia_learningpath/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_learningpath_list_paths` | read | `local/sentientia_learningpath:view` | MOBILE-READY | Available (UAT) | List learning paths for shared datatable |
| `local_sentientia_learningpath_toggle_status` | write | `local/sentientia_learningpath:update` | DESKTOP-ONLY | Available (UAT) | Activate or archive a learning path |
| `local_sentientia_learningpath_delete_path` | write | `local/sentientia_learningpath:delete` | SENSITIVE-ADMIN | Available (UAT) | Delete a learning path |
| `local_sentientia_learningpath_assign_courses` | write | `local/sentientia_learningpath:update` | DESKTOP-ONLY | Available (UAT) | Bulk-assign courses to a learning path |
| `local_sentientia_learningpath_unassign_course` | write | `local/sentientia_learningpath:update` | DESKTOP-ONLY | Available (UAT) | Remove a course from a learning path |
| `local_sentientia_learningpath_reorder_courses` | write | `local/sentientia_learningpath:update` | DESKTOP-ONLY | Available (UAT) | Reorder courses within a learning path |
| `local_sentientia_learningpath_list_path_courses` | read | `local/sentientia_learningpath:view` | MOBILE-READY | Available (UAT) | List courses assigned to a learning path |
| `local_sentientia_learningpath_enrol_users` | write | `local/sentientia_learningpath:enrol` | DESKTOP-ONLY | Available (UAT) | Bulk-enrol users in a learning path |
| `local_sentientia_learningpath_unenrol_user` | write | `local/sentientia_learningpath:enrol` | DESKTOP-ONLY | Available (UAT) | Unenrol a user from a learning path |
| `local_sentientia_learningpath_list_path_users` | read | `local/sentientia_learningpath:view` | DESKTOP-ONLY | Available (UAT) | List users enrolled in a learning path |
| `local_sentientia_learningpath_preview_audience` | read | `local/sentientia_learningpath:enrol` | DESKTOP-ONLY | Available (UAT) | Preview the user audience matching a filter map (count + sample) before bulk-enrolling |
| `local_sentientia_learningpath_bulk_enrol_by_audience` | write | `local/sentientia_learningpath:enrol` | SENSITIVE-ADMIN | Available (UAT) | Resolve a target-audience filter and bulk-enrol all matching users into a learning path |

### 5.13 `local_sentientia_manager` (8 functions)

Source: `moodle-enhancement/local/sentientia_manager/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_manager_list_requests` | read | `local/sentientia_manager:view` | DESKTOP-ONLY | Available (UAT) | List enrolment requests for the caller manager |
| `local_sentientia_manager_decide_request` | write | `local/sentientia_manager:approve` | DESKTOP-ONLY | Available (UAT) | Approve or reject an enrolment request |
| `local_sentientia_manager_list_allocations` | read | `local/sentientia_manager:view` | DESKTOP-ONLY | Available (UAT) | List course allocations the caller manager has made |
| `local_sentientia_manager_create_allocation` | write | `local/sentientia_manager:allocate` | DESKTOP-ONLY | Available (UAT) | Allocate a course to a direct report |
| `local_sentientia_manager_delete_allocation` | write | `local/sentientia_manager:allocate` | DESKTOP-ONLY | Available (UAT) | Remove a course allocation |
| `local_sentientia_manager_bulk_allocate` | write | `local/sentientia_manager:allocate` | SENSITIVE-ADMIN | Available (UAT) | Allocate one course to N direct reports in a single batch |
| `local_sentientia_manager_bulk_decide` | write | `local/sentientia_manager:approve` | SENSITIVE-ADMIN | Available (UAT) | Approve or reject many requests in one batch |
| `local_sentientia_manager_team_performance` | read | `local/sentientia_manager:view` | DESKTOP-ONLY | Available (UAT) | Aggregate per-direct-report metrics for the dashboard |

### 5.14 `local_sentientia_notifications` (6 functions)

Source: `moodle-enhancement/local/sentientia_notifications/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_notifications_list_rules` | read | `local/sentientia_notifications:manage` | DESKTOP-ONLY | Available (UAT) | List notification rules for shared datatable |
| `local_sentientia_notifications_toggle_rule` | write | `local/sentientia_notifications:manage` | DESKTOP-ONLY | Available (UAT) | Enable or disable a notification rule |
| `local_sentientia_notifications_delete_rule` | write | `local/sentientia_notifications:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete a notification rule |
| `local_sentientia_notifications_save_prefs` | write | `-` | MOBILE-READY | Available (UAT) | Save current user notification preferences |
| `local_sentientia_notifications_preview_rule` | read | `local/sentientia_notifications:manage` | DESKTOP-ONLY | Available (UAT) | Render a rule message body without sending |
| `local_sentientia_notifications_test_send` | write | `local/sentientia_notifications:manage` | SENSITIVE-ADMIN | Available (UAT) | Send a one-off test notification using a rule template |

### 5.15 `local_sentientia_org` (3 functions)

Source: `moodle-enhancement/local/sentientia_org/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_org_delete_org` | write | `local/sentientia_org:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete an organisation node (refuses if tenant, has descendants, or has users) |
| `local_sentientia_org_toggle_visibility` | write | `local/sentientia_org:manage` | DESKTOP-ONLY | Available (UAT) | Toggle an organisation between active and hidden |
| `local_sentientia_org_list_children` | read | `local/sentientia_users:view` | DESKTOP-ONLY | Available (UAT) | List child org nodes under a parent (cascade filter) |

### 5.16 `local_sentientia_proctoring` (14 functions)

Source: `moodle-enhancement/local/sentientia_proctoring/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_proctoring_start_session` | write | `local/sentientia_proctoring:attempt` | DESKTOP-ONLY | Available (UAT) | Open a proctoring session for a quiz attempt |
| `local_sentientia_proctoring_give_consent` | write | `local/sentientia_proctoring:attempt` | DESKTOP-ONLY | Available (UAT) | Record the candidate's consent for recording |
| `local_sentientia_proctoring_submit_identity` | write | `local/sentientia_proctoring:attempt` | DESKTOP-ONLY | Available (UAT) | Submit ID + selfie for identity verification |
| `local_sentientia_proctoring_report_event` | write | `local/sentientia_proctoring:attempt` | DESKTOP-ONLY | Available (UAT) | Report a behavioural event (face_lost, tab_switch, etc.) |
| `local_sentientia_proctoring_upload_chunk` | write | `local/sentientia_proctoring:attempt` | DESKTOP-ONLY | Available (UAT) | Register a recording chunk that was uploaded directly to S3 |
| `local_sentientia_proctoring_finalize` | write | `local/sentientia_proctoring:attempt` | DESKTOP-ONLY | Available (UAT) | Mark session finished and trigger AI analysis |
| `local_sentientia_proctoring_list_attempts` | read | `local/sentientia_proctoring:viewattempts` | DESKTOP-ONLY | Available (UAT) | List proctored attempts (admin) |
| `local_sentientia_proctoring_get_attempt` | read | `local/sentientia_proctoring:viewattempts` | DESKTOP-ONLY | Available (UAT) | Get full detail of a proctored attempt |
| `local_sentientia_proctoring_list_review_queue` | read | `local/sentientia_proctoring:review` | DESKTOP-ONLY | Available (UAT) | List sessions pending human review |
| `local_sentientia_proctoring_flag` | write | `local/sentientia_proctoring:review` | DESKTOP-ONLY | Available (UAT) | Mark a session for human review |
| `local_sentientia_proctoring_submit_review` | write | `local/sentientia_proctoring:review` | DESKTOP-ONLY | Available (UAT) | Reviewer submits a decision |
| `local_sentientia_proctoring_secure_report` | write | `local/sentientia_proctoring:attempt` | Not audited | Built (verified on development); flagged | Secure assessment mode: the attempt page reports its events and heartbeat; the server coun |
| `local_sentientia_proctoring_integrity_report` | read | `mod/quiz:viewreports` | Not audited | Built (verified on development); flagged | Secure assessment mode: integrity report (counts and timeline) of one quiz attempt |
| `local_sentientia_proctoring_compliance_report` | read | `local/sentientia_proctoring:review` | DESKTOP-ONLY | Available (UAT) | Compliance report: % clean vs flagged vs failed over time |

### 5.17 `local_sentientia_programs` (12 functions)

Source: `moodle-enhancement/local/sentientia_programs/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_programs_list_programs` | read | `local/sentientia_programs:view` | MOBILE-READY | Available (UAT) | List programs for shared datatable |
| `local_sentientia_programs_change_status` | write | `local/sentientia_programs:update` | DESKTOP-ONLY | Available (UAT) | Change program status (draft/active/archived) |
| `local_sentientia_programs_delete_program` | write | `local/sentientia_programs:delete` | SENSITIVE-ADMIN | Available (UAT) | Delete a certification program |
| `local_sentientia_programs_list_levels` | read | `local/sentientia_programs:view` | MOBILE-READY | Available (UAT) | List levels for a program (Levels tab datatable) |
| `local_sentientia_programs_delete_level` | write | `local/sentientia_programs:update` | SENSITIVE-ADMIN | Available (UAT) | Delete a level (cascades to course assignments) |
| `local_sentientia_programs_reorder_levels` | write | `local/sentientia_programs:update` | DESKTOP-ONLY | Available (UAT) | Reorder levels in a program |
| `local_sentientia_programs_list_level_courses` | read | `local/sentientia_programs:view` | MOBILE-READY | Available (UAT) | List courses assigned to a level |
| `local_sentientia_programs_unassign_level_course` | write | `local/sentientia_programs:update` | DESKTOP-ONLY | Available (UAT) | Remove a course from a level |
| `local_sentientia_programs_list_users` | read | `local/sentientia_programs:view` | DESKTOP-ONLY | Available (UAT) | List enrolled users for a program (Users tab datatable) |
| `local_sentientia_programs_unenrol_user` | write | `local/sentientia_programs:enrol` | DESKTOP-ONLY | Available (UAT) | Unenrol a user from a program |
| `local_sentientia_programs_preview_audience` | read | `local/sentientia_programs:enrol` | DESKTOP-ONLY | Available (UAT) | Preview users matching a target-audience filter before bulk-enrolling |
| `local_sentientia_programs_bulk_enrol_by_audience` | write | `local/sentientia_programs:enrol` | SENSITIVE-ADMIN | Available (UAT) | Resolve a target-audience filter and bulk-enrol all matching users into a program |

### 5.18 `local_sentientia_pwa` (3 functions)

Source: `moodle-enhancement/local/sentientia_pwa/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_pwa_save_subscription` | write | `local/sentientia_pwa:subscribe` | Not audited | Available (UAT) | Save (upsert) the current user's push subscription. Called from the subscribe button JS af |
| `local_sentientia_pwa_delete_subscription` | write | `local/sentientia_pwa:subscribe` | Not audited | Available (UAT) | Delete a push subscription. Called when the user clicks "Unsubscribe" or the browser revok |
| `local_sentientia_pwa_list_my_subscriptions` | read | `local/sentientia_pwa:subscribe` | Not audited | Available (UAT) | List the current user's push subscriptions for the "my devices" UI. Returns endpoint host |

### 5.19 `local_sentientia_ratings` (1 functions)

Source: `moodle-enhancement/local/sentientia_ratings/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_ratings_submit_rating` | write | `local/sentientia_ratings:rate` | MOBILE-READY | Available (UAT) | Submit (insert or update) a star rating for an item. |

### 5.20 `local_sentientia_reports` (3 functions)

Source: `moodle-enhancement/local/sentientia_reports/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_reports_list_reports` | read | `local/sentientia_reports:view` | DESKTOP-ONLY | Available (UAT) | List saved reports for shared datatable |
| `local_sentientia_reports_delete_report` | write | `local/sentientia_reports:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete a saved report definition |
| `local_sentientia_reports_toggle_status` | write | `local/sentientia_reports:manage` | DESKTOP-ONLY | Available (UAT) | Toggle a report between active and archived status |

### 5.21 `local_sentientia_request` (6 functions)

Source: `moodle-enhancement/local/sentientia_request/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_request_submit` | write | `local/sentientia_request:request` | MOBILE-READY | Available (UAT) | Submit a new course enrolment request |
| `local_sentientia_request_list_mine` | read | `local/sentientia_request:request` | MOBILE-READY | Available (UAT) | List the current user's own requests |
| `local_sentientia_request_list_pending` | read | `local/sentientia_request:approve` | DESKTOP-ONLY | Available (UAT) | List requests pending the current user's approval |
| `local_sentientia_request_list_all` | read | `local/sentientia_request:viewall` | DESKTOP-ONLY | Available (UAT) | List ALL requests across the tenant (admin) |
| `local_sentientia_request_decide` | write | `local/sentientia_request:approve` | DESKTOP-ONLY | Available (UAT) | Approve or reject a request |
| `local_sentientia_request_cancel` | write | `local/sentientia_request:request` | MOBILE-READY | Available (UAT) | Cancel one's own pending request |

### 5.22 `local_sentientia_roles` (8 functions)

Source: `moodle-enhancement/local/sentientia_roles/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_roles_list_roles` | read | `local/sentientia_roles:view` | DESKTOP-ONLY | Available (UAT) | List roles with capability counts, assignment counts, archetype. |
| `local_sentientia_roles_get_role_caps` | read | `local/sentientia_roles:view` | DESKTOP-ONLY | Available (UAT) | Get capability list for a role with current permission and inherited defaults. |
| `local_sentientia_roles_update_capability` | write | `local/sentientia_roles:manage` | SENSITIVE-ADMIN | Available (UAT) | Set a capability permission on a role; writes audit log entry. |
| `local_sentientia_roles_list_audit` | read | `local/sentientia_roles:audit` | DESKTOP-ONLY | Available (UAT) | List audit log entries with filters (role, capability, user, date). |
| `local_sentientia_roles_bulk_update_capability` | write | `local/sentientia_roles:manage` | SENSITIVE-ADMIN | Available (UAT) | Apply same capability + permission to N roles in one batch. |
| `local_sentientia_roles_list_assignments` | read | `local/sentientia_roles:view` | DESKTOP-ONLY | Available (UAT) | List user assignments for a role at the system context and, when the sentientia.roles.org_ |
| `local_sentientia_roles_assign_user` | write | `local/sentientia_roles:assign` | SENSITIVE-ADMIN | Available (UAT) | Assign a user to a role at system context (audited). |
| `local_sentientia_roles_unassign_user` | write | `local/sentientia_roles:assign` | DESKTOP-ONLY | Available (UAT) | Remove a user's role assignment (audited). |

### 5.23 `local_sentientia_skills` (14 functions)

Source: `moodle-enhancement/local/sentientia_skills/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_skills_list_skills` | read | `local/sentientia_skills:manage` | MOBILE-READY | Available (UAT) | List skills for shared datatable |
| `local_sentientia_skills_delete_skill` | write | `local/sentientia_skills:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete a skill (and all role/course/user mappings) |
| `local_sentientia_skills_delete_category` | write | `local/sentientia_skills:manage` | SENSITIVE-ADMIN | Available (UAT) | Delete a skill category (only if no skills reference it) |
| `local_sentientia_skills_get_skill_levels` | read | `local/sentientia_skills:manage` | MOBILE-READY | Available (UAT) | Get level-1..max_level definitions for one skill |
| `local_sentientia_skills_save_skill_level` | write | `local/sentientia_skills:manage` | DESKTOP-ONLY | Available (UAT) | Upsert one skill-level definition |
| `local_sentientia_skills_list_designation_skills` | read | `local/sentientia_skills:manage` | DESKTOP-ONLY | Available (UAT) | Required-skill rows for a given designation |
| `local_sentientia_skills_save_designation_skill` | write | `local/sentientia_skills:manage` | DESKTOP-ONLY | Available (UAT) | Upsert one designation-skill requirement |
| `local_sentientia_skills_delete_designation_skill` | write | `local/sentientia_skills:manage` | DESKTOP-ONLY | Available (UAT) | Remove one designation-skill row |
| `local_sentientia_skills_copy_designation` | write | `local/sentientia_skills:manage` | DESKTOP-ONLY | Available (UAT) | Copy all required-skill rows from one designation to another |
| `local_sentientia_skills_list_course_skills` | read | `local/sentientia_skills:mapcourses` | MOBILE-READY | Available (UAT) | Skills mapped to a course (with target level) |
| `local_sentientia_skills_save_course_skill` | write | `local/sentientia_skills:mapcourses` | DESKTOP-ONLY | Available (UAT) | Upsert one course-skill mapping |
| `local_sentientia_skills_delete_course_skill` | write | `local/sentientia_skills:mapcourses` | DESKTOP-ONLY | Available (UAT) | Delete one course-skill mapping |
| `local_sentientia_skills_search_courses` | read | `local/sentientia_skills:mapcourses` | DESKTOP-ONLY | Available (UAT) | Lookup courses by name/shortname for the picker |
| `local_sentientia_skills_self_rate_skill` | write | `local/sentientia_skills:self_rate` | MOBILE-READY | Available (UAT) | Learner self-attests a level (1..max_level) for a skill. |

### 5.24 `local_sentientia_users` (6 functions)

Source: `moodle-enhancement/local/sentientia_users/db/services.php`

| Function | Type | Capability | May-2026 audit class | Status | Purpose (from definition) |
|---|---|---|---|---|---|
| `local_sentientia_users_list_users` | read | `local/sentientia_users:view` | DESKTOP-ONLY | Available (UAT) | List users with server-side search, sort, and pagination |
| `local_sentientia_users_list_filter_options` | read | `local/sentientia_users:view` | DESKTOP-ONLY | Available (UAT) | Return distinct designation/location/hrmsrole/employmenttype/region/grade values for filte |
| `local_sentientia_users_search_supervisors` | read | `local/sentientia_users:view` | DESKTOP-ONLY | Available (UAT) | Tenant-scoped autocomplete for supervisor / reporting-manager picker |
| `local_sentientia_users_suspend_user` | write | `local/sentientia_users:edit` | SENSITIVE-ADMIN | Available (UAT) | Suspend or activate a user |
| `local_sentientia_users_delete_user` | write | `local/sentientia_users:delete` | SENSITIVE-ADMIN | Available (UAT) | Delete a user (soft delete) |
| `local_sentientia_users_bulk_action` | write | `local/sentientia_users:edit` | SENSITIVE-ADMIN | Available (UAT) | Bulk suspend or activate multiple users by ID |

## 6. Capability use across functions

67 distinct capabilities gate the 174 functions. The ten most used:

| Capability | Functions |
|---|---|
| `local/sentientia_skills:manage` | 9 |
| `local/sentientia_classroom:view` | 7 |
| `local/sentientia_evaluation:manage` | 7 |
| `local/sentientia_proctoring:attempt` | 7 |
| `local/sentientia_api:read` | 6 |
| `-` | 5 |
| `local/sentientia_notifications:manage` | 5 |
| `local/sentientia_learningpath:update` | 4 |
| `local/sentientia_learningpath:enrol` | 4 |
| `local/sentientia_users:view` | 4 |

Notes on sections 5 and 6:

- Five functions declare no capability in their definition: `local_sentientia_assistant_ask`, `local_sentientia_assistant_get_history`, `local_sentientia_leaderboard_set_optout`, `local_sentientia_notifications_save_prefs`, `paygw_airpay_get_form`. These are learner self-service calls. Whether each class performs its own access check is **to be confirmed** by the security review (owner: security architect).
- Two functions of `local_sentientia_assistant` (`..._agent_turn`, `..._agent_confirm`) belong to the agentic assistant, behind `sentientia.assistant.agentic.enabled` and `sentientia.assistant.agentic.live_api` (default off). Source: `local/sentientia_assistant/db/feature_flags.php`.
- The proctoring functions sit behind `sentientia.proctoring.enabled`; the two new report functions relate to `sentientia.assessment.secure_mode.enabled`. All registry defaults are `false`. Source: `local/sentientia_proctoring/db/feature_flags.php`.
- Tenant scoping is a design rule for these functions (ADR-009 contract invariants; ADR-031 cross-tenant authority). The CI script `tools/ci-ws-contract-check.php` checks client and server contracts. Source: `docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md`.

## 7. Mobile web-service surface audit (2026-05-20)

Source: `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`.

| Item | Value |
|---|---|
| Audit scope | all Airpay-owned `db/services.php` files, 20 plugins, 156 functions (core Moodle functions excluded) |
| Categories | MOBILE-READY (learner "my data", catalog), DESKTOP-ONLY (admin workflows), SENSITIVE-ADMIN (destructive or cross-tenant; never on mobile) |
| Summary table in the document | 36 MOBILE-READY (23%), 84 DESKTOP-ONLY (54%), 36 SENSITIVE-ADMIN (23%) |
| Row-level tally of the same document (counted by script) | 33 MOBILE-READY, 90 DESKTOP-ONLY, 33 SENSITIVE-ADMIN |
| Planned phases in the audit | X.1 read-only learner surface (22 functions), X.2 learner write actions (14), X.3 manager mobile (undecided), SENSITIVE-ADMIN never |
| Flags actually applied in code | none: `MOODLE_OFFICIAL_MOBILE_SERVICE` appears in no Sentientia `services.php` |
| Functions defined now vs audited | 174 vs 156: 18 functions are not yet classified (section 3) |
| Audit rule | every new function must be categorised before merge (audit sign-off section) |

Findings the reader needs:

1. The audit summary (36/84/36) does not match its own rows (33/90/33). The refresh must reconcile this. Owner: Head of Learning and Development with the QA Architect.
2. The native app decision supersedes the audit as a design basis. ADR-040 (status: Proposed) states that the "22 MOBILE-READY" functions are admin data-table endpoints, not a learner API, and that the native app needs a purpose-built learner API. Source: `docs/adr/ADR-040-native-mobile-app.md` (Context). Status of the learner API: **Planned**.
3. ADR-040 also records that native push cannot register today: `local_sentientia_pwa_save_subscription` accepts only browser Web Push subscriptions (endpoint, `p256dh`, `auth`). Source: `local/sentientia_pwa/classes/external/save_subscription.php`, as cited in ADR-040.
4. The mobile scaffold `mobile/sentientia-app` is a Capacitor project that was never built; ADR-040 supersedes it (Flutter or Kotlin Multiplatform, chosen by spike). Status: **Planned**.
5. Progressive Web App (PWA) endpoints (manifest, service worker, push subscription functions) exist in `local_sentientia_pwa` 0.6.1-alpha, flag `sentientia.pwa.enabled`. Status: **Built (behind a feature flag, off by default)**. Source: `local/sentientia_pwa/db/feature_flags.php`.

## 8. Raw-HTTP entry points (outside the Moodle web-service layer)

Moodle web services always answer HTTP 200 with a JSON exception on error. Standards that need real status codes (SCIM, xAPI, webhooks, LTI) therefore use plain endpoints. Source for the reasoning: `docs/adr/ADR-030-scim-and-outbound-webhooks.md` section 1.

| Endpoint file | Purpose | Auth | Gate (feature flag) | Status |
|---|---|---|---|---|
| `local/sentientia_api/scim/v2.php` | SCIM 2.0 server: Users (GET, POST, PUT, PATCH, DELETE), Groups (read; membership PATCH), discovery | per-client bearer token, stored hashed (`local_sentientia_api_scimcli`) | `sentientia.api.scim.enabled` under `sentientia.api.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_api/lti/login.php`, `launch.php`, `jwks.php` | LTI 1.3 tool provider: OIDC login, launch, key set | signed JWT (`id_token`), nonce replay protection | `sentientia.api.lti.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_xapi/lrs/statements.php` | xAPI 1.0.3 statements resource (GET, POST, PUT) | Bearer or HTTP Basic | `sentientia.xapi.enabled` and `sentientia.xapi.lrs_endpoint_enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_integrations/webhook.php` | inbound HRMS (KeKa) joiner-mover-leaver webhook | `X-Webhook-Secret` header only, constant-time compare; no query-string secret | `sentientia.hrms.webhook.enabled` plus `hrms_enable` setting | Built (behind a feature flag, off by default) |
| `local/sentientia_pwa/manifest.php`, `sw.php`, `dismiss_install.php`, `mock_receiver.php` | web app manifest, service worker, install-prompt dismissal, a mock push receiver | none for static assets; session for dismissal | `sentientia.pwa.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_live/stream.php` | Server-Sent Events stream for live sessions | Moodle session; capacity-limited (503 when full) | `live.enabled`, `live.realtime.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_classroom/ics.php` | calendar (ICS) invite for one classroom session | `require_login()`; site administrator, a roster member, or a holder of `local/sentientia_classroom:view` inside the caller's tenant (`assert_classroom_in_scope()`) and, under the trainer-own-scope rule, a classroom the caller trains (`require_trainer_scope()`) | none (classroom plugin) | Available (UAT) |
| `local/sentientia_leaderboard/stream.php` | Server-Sent Events stream for leaderboards | `require_login()`, `local/sentientia_leaderboard:view` and a tenant check; no token bearer | `sentientia.leaderboards.enabled` (403 when OFF) and `sentientia.leaderboards.realtime.enabled` (503 when OFF; clients poll) | Built (behind a feature flag, off by default) |
| `local/sentientia_calendar/ics.php` | personal calendar (ICS) feed | per-user token in the query string; no session (`NO_MOODLE_COOKIES`); unknown or revoked token returns 404; last use recorded | `sentientia.calendar_sync.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_cart/callback.php` | inbound server-to-server payment callback from the Airpay gateway | no session (`NO_MOODLE_COOKIES`); gateway checksum verified; amount compared with the server-side cart total before marking paid; optional source-address allow-list (setting `airpay_callback_iplist`), rejected callers silently dropped and logged | none | Available (UAT) |
| `payment/gateway/airpay/pay.php` | builds the checksum-signed payment form for a core payment-subsystem order and posts the buyer to the gateway | no explicit `require_login()` in the file; reads the current `$USER` (to be reviewed in the security pack) | none | Available (UAT) |
| `payment/gateway/airpay/process.php` | buyer return page from the gateway; enrols only after the response secure hash verifies (fails closed) | no login (`require_login()` commented out); response secure hash verified by `airpay_helper::verify_secure_hash()` | none | Available (UAT) |
| `payment/gateway/airpay/process_old.php` | legacy BizLMS callback kept "for refund replay" (`state-cards/paygw_airpay-state.md`) | does not bootstrap Moodle (no `config.php`); reads raw `$_POST` and compares a CRC32 hash; a direct request fails on the undefined database handle | none | Legacy file in the UAT build; removal proposed (owner: platform engineer) |
| `payment/gateway/airpay/error.php` | gateway error page | no login; does not bootstrap Moodle; reads raw `$_REQUEST` | none | Available (UAT) |

## 9. Public REST API v1, OpenAPI, SCIM, webhooks, LTI

Source: `local/sentientia_api/` (release 1.3.2, `README.md`, `db/*.php`, `docs/openapi-v1.yaml`); `docs/adr/ADR-030-scim-and-outbound-webhooks.md` (status: Implemented, all flags off).

| Facet | Fact |
|---|---|
| Transport | Moodle REST web-service server (`/webservice/rest/server.php`), token auth |
| Service | `Sentientia Public API v1`, shortname `sentientia_api_v1`, `restrictedusers = 1` (tokens only to authorised users), `enabled = 0` by default, no file download or upload |
| Versioning | function names carry `_v1_`; additive evolution; a v2 would add functions |
| Read functions | `list_courses`, `get_course`, `list_enrolments`, `list_completions`, `list_skills`, `openapi` (capability `local/sentientia_api:read`) |
| Write function | `create_enrolment` (capability `local/sentientia_api:write`; also needs `sentientia.api.write.enabled`) |
| Gate order | feature flag, authorisation, rate limit, tenant scoping (`base::open_v1()`; ADR-030 plans a transport-neutral gate shared with SCIM) |
| Rate limit | default 600 requests per 60-second window, configurable (`settings.php`); table `local_sentientia_api_rate` |
| Request log | `local_sentientia_api_log`, no personal data in the log (ADR-030 section 4) |
| OpenAPI | `docs/openapi-v1.yaml`, OpenAPI 3.0.3, document version 1.0.0; also served by function `local_sentientia_api_v1_openapi` |
| Capabilities | `local/sentientia_api:read`, `:write`, `:manage`, `:lti`, `:scim_manage`, `:webhooks_manage` (`db/access.php`) |
| Flags | `sentientia.api.enabled` (master), `.write.enabled`, `.webhooks.enabled`, `.scim.enabled`, `.lti.enabled`; all default off (`db/feature_flags.php`) |
| Tables (9) | `log`, `rate`, `lti_reg`, `lti_nonce`, `whsub`, `whdel`, `scimcli`, `scimmap`, `scimevt` (prefix `local_sentientia_api_`; `db/install.xml`) |
| Outbound webhooks | events `course.completed`, `enrolment.created`, `certificate.issued`; per-subscription HMAC-SHA256 secret; header `X-Sentientia-Signature: t=<unix>,v1=<hmac>`; 5-minute replay window; retries at 1 min, 5 min, 30 min and 2 h, then dead-letter (5 attempts); https-only URLs; delivery through Moodle's HTTP client with its blocked-host and port rules; drain task registered disabled (design per ADR-030) |
| SCIM | Users and Groups; filters limited to `userName eq` and `externalId eq` (others return 501); DELETE is a soft suspend, never a hard delete; deprovisioning attestation report (`scimevt`); cross-tenant principals hidden from scoped clients (ADR-031 fix-forward) |
| LTI | LTI 1.3 provider side with OIDC login, JWT verification, nonce consumption; consumer side is scaffolding |
| Tests | 11 PHPUnit `*_test.php` files in `local/sentientia_api/tests/`: `external/create_enrolment_test.php`, `external/list_courses_test.php`, `external/v1_tenant_scope_test.php`, `lti_jwt_test.php`, `lti_registration_test.php`, `rate_limiter_test.php`, `scim_test.php`, `scim_groups_test.php`, `scim_cross_tenant_principal_test.php`, `tenant_scope_test.php`, `webhooks_test.php` (corrected 20 Nov 2026; was 9) |
| Status | **Built (behind a feature flag, off by default)** |

Note on trees: `local/sentientia_api` exists only under `moodle-enhancement/local/`. The top-level `local/` tree has no `sentientia_api` directory, so the "twin" rule does not apply to it. Owner: DevOps lead to confirm which tree UAT deploys from.

## 10. Integrations

| Integration | Component and release | What it does (in code) | Provider and endpoint | Flag or setting | Status |
|---|---|---|---|---|---|
| WhatsApp | `local_sentientia_whatsapp` 0.4.1-alpha (`classes/whatsapp_client.php`) | template messages via a channel router, preferences, send log, DLT template registry | Karix WhatsApp API referenced in a commented-out block; **live send is not implemented**: live mode falls back to mock and writes a "mocked" log row | `engagement.whatsapp.enabled` (master), `sentientia_whatsapp_content_notifications`, `sentientia.customer_level_flags.enabled` | Built (behind a feature flag, off by default); live send **Planned** |
| SMS | same plugin (`classes/sms_client.php`) | DLT-registered template SMS | MSG91 flow API referenced in a comment; live send not implemented | same family | Built (behind a feature flag, off by default); live send **Planned** |
| E-mail | `local_sentientia_emails` 1.4.3 | template library, delivery log, imported-history view, legacy bridge, certificate e-mails | Moodle core mail (SMTP configured per site) | `sentientia.emails.send_course_enrolment.enabled`, `.send_learning_path_enrolment.enabled`, `.send_manager_completion_copy.enabled`, `.imported_history.enabled`, `.imported_body_detail.enabled` | Available (UAT); the 20 Nov rule-editor fix is Built (verified on development) |
| In-app and digest notifications | `local_sentientia_notifications` 1.5.2 | smart rules, daily digest with compliance deadlines | Moodle messaging | `sentientia.notifications.smart_rules.enabled`, `.digest_deadlines.enabled` | Available (UAT) |
| Web Push | `local_sentientia_pwa` 0.6.1-alpha | VAPID (RFC 8292) ES256-signed Web Push to browsers; reminders, overdue | browser push services | `sentientia.pwa.enabled`, `.push.enabled`, `.push.reminders`, `.push.overdue`, `.install.enabled` | Built (behind a feature flag, off by default) |
| Microsoft Teams alerts | `local_sentientia_integrations` 1.2.0-beta (`classes/teams_notifier.php`) | adaptive card to a Teams channel | Teams incoming webhook URL set in settings | `teams_enable` setting | Built (verified on development); setting-gated, no flag in the registry (to be confirmed) |
| Teams and Zoom meetings | `local_sentientia_classroom` 1.12.0 | stores a validated http(s) meeting and recording link per session (`classes/url_rule.php`); **no Graph or Zoom API call** | none | n/a | Link storage Available (UAT); API integration (create meeting, import attendance) **Planned** (ADR-036, listed as "later", item C8) |
| Microsoft 365 knowledge automation | `local_sentientia_m365` 0.2.0-alpha | MSAL authorisation-code flow with PKCE; Graph calls for profile, SharePoint sites, calendar (scopes `User.Read`, `Sites.Read.All`, `Calendars.Read`) | `login.microsoftonline.com`, `graph.microsoft.com/v1.0` | `sentientia_m365_enabled` | Built (behind a feature flag, off by default); scaffold level |
| KeKa HRMS | `local_sentientia_integrations` 1.2.0-beta (`keka_client.php`, `webhook.php`) | joiner-mover-leaver webhook, scheduled reconcile pull (task registered disabled), OAuth client to the KeKa API | KeKa API (base URL setting, default `https://api.keka.com`) | `sentientia.hrms.webhook.enabled`, `sentientia.hrms.reconcile.enabled` | Built (behind a feature flag, off by default); ADR-029 Accepted |
| Payment gateway | `paygw_airpay` 1.0.2 | Moodle payment gateway; form post to the Airpay payment page; response hash verification; one function `paygw_airpay_get_form` | Airpay payments (production endpoint set in `classes/airpay_helper.php`) | gateway enabled per payment account | Available (UAT) |
| SSO, OAuth 2.0 | Moodle core OAuth 2 issuers (no plugin) | Azure AD recommended for the Airpay tenant; Google optional for Public | Microsoft Entra ID, Google | admin configuration | Documented; issuer configuration on UAT **to be confirmed** (owner: IT infra) |
| SSO, SAML 2.0 and LDAP | Moodle plugins (SAML needs a plugin install) | alternatives in the SSO guide | customer identity provider | admin configuration | **Planned** (documented option only) |
| xAPI statements | `local_sentientia_xapi` 1.0.2 | observers emit statements for course completed, quiz submitted, module viewed, user logged in; internal LRS endpoint | internal | `sentientia.xapi.enabled`, `.emit_login`, `.emit_module_view`, `.lrs_endpoint_enabled` | Built (behind a feature flag, off by default) |
| SCIM, webhooks, LTI | `local_sentientia_api` 1.3.2 | see sections 8 and 9 | customer identity provider and systems | flags in section 9 | Built (behind a feature flag, off by default) |
| Live engagement | `local_sentientia_live` 0.2.4-alpha | SSE real-time polls and quizzes; 6 question types | same origin | `live.enabled`, `live.realtime.enabled`, `live.questiontype.*`, `live.allow_anonymous` | Built (behind a feature flag, off by default) |

Sources: plugin `version.php`, `db/feature_flags.php`, `classes/`, `settings.php` for each; `SSO-SETUP-GUIDE.md`; `docs/adr/ADR-029-keka-jml-hardening.md`; `docs/adr/ADR-036-training-programs.md`; `INTEGRATIONS-AUDIT.md` (dated 2026-05-07; superseded by ADR-029 for KeKa).

Gaps worth knowing: the SSO guide is a generic setup note with an unticked post-setup checklist; the multi-factor runbook is `MFA-ENFORCEMENT-RUNBOOK.md`. Microsoft 365 integration beyond SSO is scaffold level, not a working feature.

## 11. AI gateway API (`local_sentientia_ai`)

Source: `local/sentientia_ai/` (release 0.1.2-alpha, `README.md`, `classes/`, `db/feature_flags.php`, `settings.php`, `tests/`). Context: ADR-012, ADR-015, ADR-016.

| Facet | Fact |
|---|---|
| Entry point | PHP static call `\local_sentientia_ai\client::complete(array $options)`; no web-service function and no HTTP surface of its own |
| Request fields | `component` (required), `purpose` (required), `usertext` (required), `system`, `model`, `max_tokens` (default 4096, hard cap 8192), `mock` callable, `legacy_component` |
| Result fields | `body`, `tokens_in`, `tokens_out`, `mode` (`mock`, `live`, `failed`, `denied`), `error`, `ledgerid` |
| Routing | live only when both `sentientia.ai.gateway.enabled` and `sentientia.ai.live_api.enabled` are on, a key is configured and quota remains; otherwise a deterministic mock at zero cost |
| Fail-closed rules | a live attempt with no key returns `failed`; exhausted quota returns `denied`; never a silent mock on a live path; zero or empty quota means no live calls |
| Quotas (settings) | `daily_tokens_global`, `daily_tokens_customer`, `monthly_cost_cap_usd` (estimates from a pricing map) |
| Ledger | one row per call in `local_sentientia_ai_ledger`; accounting only, no prompt or response text; admin view `/local/sentientia_ai/index.php` |
| Provider | **one provider only**: Anthropic Messages API (`https://api.anthropic.com/v1/messages`, API version `2023-06-01`, 60-second timeout); no provider abstraction and no local or self-hosted model path in code |
| Priced models in the map | `claude-sonnet-4-6` 3.00/15.00, `claude-sonnet-4-5` 3.00/15.00, `claude-haiku-4-5` 1.00/5.00, `claude-opus-4-1` 15.00/75.00 US dollars per million tokens (input/output), default 15.00/75.00 (`classes/gateway.php`) |
| Consumers (6) | `local_sentientia_aiquiz` (`quiz_generation`), `local_sentientia_skillsai` (`skill_extraction`), `local_sentientia_recommendations` (`course_recommendations`), `local_sentientia_translate` (`content_translation`), `local_sentientia_authoring` (`course_generation`), `local_sentientia_assistant` (`agent_reasoning`, `assistant_chat`) |
| Tests | `gateway_test.php` (routing, quotas, ledger, pricing), `golden_test.php` (mock output stability), `tenant_scope_test.php`; no HTTP in tests |
| Status | **Built (behind a feature flag, off by default)**: both flags default `false`; live use waits on the spend approval and an API key |
| Offline LLM | **Planned**: the gateway would need a provider adapter (an OpenAI-compatible local endpoint is the usual shape) plus a per-purpose model mapping. Not in code today. |

Other AI-adjacent entry points: the assistant also exposes web services (`local_sentientia_assistant_ask`, `_get_history`, `_agent_turn`, `_agent_confirm`; service `Airpay AI Assistant`, `enabled = 1` in its service definition) and keeps a Moodle `core_ai` bridge as an alternative backend behind a provider toggle (plugin README).

## 12. Open items and discrepancies for the reader

| # | Item | Owner role | Proposed resolution |
|---|---|---|---|
| 1 | Mobile audit summary (36/84/36) disagrees with its rows (33/90/33); 18 functions unclassified | Head of Learning and Development with QA Architect | refresh the audit against the 174 current functions; reconcile counts |
| 2 | `MOODLE_OFFICIAL_MOBILE_SERVICE` applied to no function | Head of Learning and Development | keep as is; ADR-040 defines a purpose-built learner API instead |
| 3 | Five functions declare no capability in the definition | Security architect | confirm per-class checks; add explicit capabilities where missing |
| 4 | `local_sentientia_api` missing from the top-level `local/` tree | DevOps lead | confirm deployment source tree; align with the drift gate |
| 5 | WhatsApp and SMS live send not implemented | Head of Learning and Development with Legal | decide provider, finish DLT approval, then build the live branch |
| 6 | Teams and Zoom meeting creation and attendance import not built | Head of Learning and Development | schedule after the programs release (ADR-036 item C8) |
| 7 | AI gateway supports one external provider only | CTO office | specify the offline model API in the offline-LLM pack; add an adapter |
| 8 | `paygw_airpay` builds the request checksum with SHA-256 (`pay.php` calls `calculateChecksumSha256()`); a deprecated MD5 helper (`classes/checksum.php::calculateChecksum()`) is retained but unused. The return page `process.php` (no login) verifies the response with a CRC32 that carries no secret (integrity only, forgeable; `classes/airpay_helper.php`), has no server-side Verify API call (HARDENING TODO) and no amount check; legacy `process_old.php` is still in the tree | Security architect with Airpay payments team | build the Verify API call and an amount check; confirm the vendor scheme is current; remove the deprecated helper and the legacy file if unused |
| 9 | SSO issuer configuration state on UAT not recorded | IT infra | record issuer, scopes and test result |

## 13. Source index

| Topic | Path (under `moodle-enhancement/`) |
|---|---|
| Function definitions | `local/*/db/services.php`, `payment/gateway/airpay/db/services.php` |
| Mobile audit | `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` |
| Contract invariants | `docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md` |
| SCIM and webhooks | `docs/adr/ADR-030-scim-and-outbound-webhooks.md`, `local/sentientia_api/` |
| Cross-tenant authority | `docs/adr/ADR-031-cross-tenant-authority.md` |
| KeKa | `docs/adr/ADR-029-keka-jml-hardening.md`, `local/sentientia_integrations/` |
| Native app | `docs/adr/ADR-040-native-mobile-app.md`, `docs/adr/ADR-005-pwa-install-and-native-wrapper-decision.md` |
| Programs, Teams and Zoom | `docs/adr/ADR-036-training-programs.md` |
| AI gateway | `local/sentientia_ai/README.md`, `docs/adr/ADR-012-ai-quiz-generation.md`, `ADR-015`, `ADR-016` |
| SSO | `SSO-SETUP-GUIDE.md`, `MFA-ENFORCEMENT-RUNBOOK.md` |
| Integration audit (dated) | `INTEGRATIONS-AUDIT.md` |

## Corrections (fact-base cross-check, 20 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | `local_sentientia_org` release is 1.6.2: `version.php` assigns `$plugin->release` twice and the second assignment (1.6.2, line 76) wins; 1.5.0 (line 72) is history. | `moodle-enhancement/local/sentientia_org/version.php` |
| 2 | Re-verified unchanged: 174 functions (68 read, 106 write) in 24 `db/services.php` files; proctoring has 12 functions at the UAT commit and 14 now. | PHP include count; `git show af6e65c0e:` of the proctoring `services.php` |
| 3 | Open item 8 corrected: the request checksum is SHA-256, not MD5 (MD5 survives only in a deprecated, unused helper); the `process.php` response check is a secretless CRC32 with no Verify API call and no amount check. | `payment/gateway/airpay/pay.php` line 69; `classes/checksum.php`; `classes/airpay_helper.php` lines 243-247; `process.php` |
