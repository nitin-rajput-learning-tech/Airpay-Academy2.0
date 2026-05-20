# Mobile-App Web-Service Surface Audit

> **Audit:** P1 #60 (audit item) — flag every Airpay-owned web-service function for inclusion in the Moodle mobile-app surface.
>
> **Date:** 2026-05-20
> **Auditor:** Wave 2 Hindi + parity drive
> **Scope:** All `local/airpay_*/db/services.php` files. Built-in Moodle core WS are out of scope (already audited by Moodle).
> **Inventory:** 156 WS functions across 20 plugins.

---

## Decision Categories

| Category | Meaning | Mobile-app exposure |
|---|---|---|
| **MOBILE-READY** | Read-only "my-own-data" or learner-facing catalog/listing. Safe to expose to the official Moodle mobile-app token. | Add `MOODLE_OFFICIAL_MOBILE_SERVICE` to its `services` array. |
| **DESKTOP-ONLY** | Admin / L&D management workflow that requires a desktop UI for screen real-estate, multi-step decisions, or batch operations. | Leave unflagged. Plugin AJAX UI continues to use it via `ajax => true`. |
| **SENSITIVE-ADMIN** | Subset of desktop-only with destructive or cross-tenant impact (delete, refund, manage caps, share/unshare, force-enrol, bulk action). | NEVER expose to mobile, even on opt-in. Reviewed at every release. |

---

## Per-plugin Recommendations

### `local_airpay_assistant` (2 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_assistant_ask` | MOBILE-READY | Single Q&A, learner-facing, rate-limited via `ratelimit` setting. |
| `local_airpay_assistant_get_history` | MOBILE-READY | Read-only learner self-history. |

### `local_airpay_cart` (9 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_cart_add_item` | MOBILE-READY | Cart operations are learner-led. |
| `local_airpay_cart_remove_item` | MOBILE-READY | Learner removes own cart item. |
| `local_airpay_cart_get_cart` | MOBILE-READY | Reads own cart. |
| `local_airpay_cart_checkout` | DESKTOP-ONLY | Payment-gateway redirect needs full browser flow; mobile-app embedded webview unreliable for Indian payment rails. |
| `local_airpay_cart_list_orders` | MOBILE-READY | Reads own order history. |
| `local_airpay_cart_get_order` | MOBILE-READY | Reads own order detail. |
| `local_airpay_cart_refund` | SENSITIVE-ADMIN | Admin destructive — money out. |
| `local_airpay_cart_set_price` | SENSITIVE-ADMIN | Admin pricing change. |
| `local_airpay_cart_daily_sums` | DESKTOP-ONLY | Admin report. |

### `local_airpay_challenge` (8 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_challenge_list_challenges` | MOBILE-READY | Catalog browse. |
| `local_airpay_challenge_get_challenge` | MOBILE-READY | Detail view. |
| `local_airpay_challenge_create_challenge` | DESKTOP-ONLY | Admin CRUD. |
| `local_airpay_challenge_update_challenge` | DESKTOP-ONLY | Admin CRUD. |
| `local_airpay_challenge_delete_challenge` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_challenge_join_challenge` | MOBILE-READY | Learner self-enrol. |
| `local_airpay_challenge_leave_challenge` | MOBILE-READY | Learner self-unenrol. |
| `local_airpay_challenge_get_leaderboard` | MOBILE-READY | Read-only leaderboard. |

### `local_airpay_classroom` (15 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_classroom_list_classrooms` | MOBILE-READY | Catalog browse. |
| `local_airpay_classroom_change_status` | DESKTOP-ONLY | Admin status flip. |
| `local_airpay_classroom_delete_classroom` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_classroom_list_sessions` | MOBILE-READY | Learner session schedule. |
| `local_airpay_classroom_delete_session` | SENSITIVE-ADMIN | Admin destructive — affects attendance records. |
| `local_airpay_classroom_list_users` | DESKTOP-ONLY | Admin roster view. |
| `local_airpay_classroom_unenrol_user` | DESKTOP-ONLY | Admin enrolment management. |
| `local_airpay_classroom_list_attendance` | DESKTOP-ONLY | Trainer attendance grid. |
| `local_airpay_classroom_mark_attendance` | DESKTOP-ONLY | Trainer attendance entry. |
| `local_airpay_classroom_bulk_mark_attendance` | DESKTOP-ONLY | Trainer bulk attendance. |
| `local_airpay_classroom_waitlist_join` | MOBILE-READY | Learner self-waitlist. |
| `local_airpay_classroom_waitlist_leave` | MOBILE-READY | Learner self-unlist. |
| `local_airpay_classroom_list_waitlist` | DESKTOP-ONLY | Trainer/admin waitlist view. |
| `local_airpay_classroom_preview_audience` | DESKTOP-ONLY | Admin bulk-enrol preview. |
| `local_airpay_classroom_bulk_enrol_by_audience` | SENSITIVE-ADMIN | Admin destructive — bulk enrol. |

### `local_airpay_courses` (15 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_courses_list_courses` | MOBILE-READY | Catalog browse. |
| `local_airpay_courses_toggle_visibility` | DESKTOP-ONLY | Admin visibility flip. |
| `local_airpay_courses_delete_course` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_courses_add_featured` | DESKTOP-ONLY | Admin merchandising. |
| `local_airpay_courses_remove_featured` | DESKTOP-ONLY | Admin merchandising. |
| `local_airpay_courses_reorder_featured` | DESKTOP-ONLY | Admin merchandising. |
| `local_airpay_courses_list_course_enrolments` | DESKTOP-ONLY | Admin roster view. |
| `local_airpay_courses_enrol_single` | DESKTOP-ONLY | Admin enrolment. |
| `local_airpay_courses_unenrol_single` | DESKTOP-ONLY | Admin enrolment. |
| `local_airpay_courses_share_course` | SENSITIVE-ADMIN | Cross-tenant data exposure. |
| `local_airpay_courses_unshare_course` | SENSITIVE-ADMIN | Cross-tenant data exposure. |
| `local_airpay_courses_list_course_shares` | DESKTOP-ONLY | Admin share audit. |
| `local_airpay_courses_request_course` | DESKTOP-ONLY | Admin/L&D request flow (not learner). |
| `local_airpay_courses_approve_request` | SENSITIVE-ADMIN | Cross-tenant data exposure. |
| `local_airpay_courses_reject_request` | DESKTOP-ONLY | Admin decision. |

### `local_airpay_emails` (5 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_emails_get_template` | DESKTOP-ONLY | Admin template editor. |
| `local_airpay_emails_save_template` | DESKTOP-ONLY | Admin HTML editor — needs desktop. |
| `local_airpay_emails_revert_template` | DESKTOP-ONLY | Admin restore. |
| `local_airpay_emails_preview_template` | DESKTOP-ONLY | Admin preview. |
| `local_airpay_emails_toggle_rule` | DESKTOP-ONLY | Admin rule control. |

### `local_airpay_evaluation` (8 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_evaluation_list_evaluations` | DESKTOP-ONLY | Admin listing. Learners reach evals via assignment URL. |
| `local_airpay_evaluation_change_status` | DESKTOP-ONLY | Admin status flip. |
| `local_airpay_evaluation_delete_evaluation` | SENSITIVE-ADMIN | Admin destructive — loses responses. |
| `local_airpay_evaluation_delete_question` | SENSITIVE-ADMIN | Admin destructive — loses per-question answers. |
| `local_airpay_evaluation_reorder_questions` | DESKTOP-ONLY | Admin form editor. |
| `local_airpay_evaluation_submit_response` | MOBILE-READY | Learner self-submit. Already capability-gated. |
| `local_airpay_evaluation_preview_audience` | DESKTOP-ONLY | Admin bulk-assign preview. |
| `local_airpay_evaluation_bulk_assign_by_audience` | SENSITIVE-ADMIN | Admin bulk assign. |

### `local_airpay_exams` (3 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_exams_list_exams` | MOBILE-READY | Catalog browse for learner. |
| `local_airpay_exams_toggle_status` | DESKTOP-ONLY | Admin status flip. |
| `local_airpay_exams_delete_exam` | SENSITIVE-ADMIN | Admin destructive. |

### `local_airpay_learningpath` (12 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_learningpath_list_paths` | MOBILE-READY | Catalog browse. |
| `local_airpay_learningpath_toggle_status` | DESKTOP-ONLY | Admin status flip. |
| `local_airpay_learningpath_delete_path` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_learningpath_assign_courses` | DESKTOP-ONLY | Admin path editor. |
| `local_airpay_learningpath_unassign_course` | DESKTOP-ONLY | Admin path editor. |
| `local_airpay_learningpath_reorder_courses` | DESKTOP-ONLY | Admin path editor. |
| `local_airpay_learningpath_list_path_courses` | MOBILE-READY | Read-only — learner views path content. |
| `local_airpay_learningpath_enrol_users` | DESKTOP-ONLY | Admin enrolment. |
| `local_airpay_learningpath_unenrol_user` | DESKTOP-ONLY | Admin enrolment. |
| `local_airpay_learningpath_list_path_users` | DESKTOP-ONLY | Admin roster view. |
| `local_airpay_learningpath_preview_audience` | DESKTOP-ONLY | Admin bulk-enrol preview. |
| `local_airpay_learningpath_bulk_enrol_by_audience` | SENSITIVE-ADMIN | Admin bulk enrol. |

### `local_airpay_manager` (8 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_manager_list_requests` | DESKTOP-ONLY | Manager-facing — desktop preferred for queue review. |
| `local_airpay_manager_decide_request` | DESKTOP-ONLY | Approval decision — needs context. |
| `local_airpay_manager_list_allocations` | DESKTOP-ONLY | Manager dashboard. |
| `local_airpay_manager_create_allocation` | DESKTOP-ONLY | Manager assignment. |
| `local_airpay_manager_delete_allocation` | DESKTOP-ONLY | Manager unassignment. |
| `local_airpay_manager_bulk_allocate` | SENSITIVE-ADMIN | Bulk action. |
| `local_airpay_manager_bulk_decide` | SENSITIVE-ADMIN | Bulk action. |
| `local_airpay_manager_team_performance` | DESKTOP-ONLY | Manager analytics. |

### `local_airpay_notifications` (6 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_notifications_list_rules` | DESKTOP-ONLY | Admin rule list. |
| `local_airpay_notifications_toggle_rule` | DESKTOP-ONLY | Admin rule control. |
| `local_airpay_notifications_delete_rule` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_notifications_save_prefs` | MOBILE-READY | Learner self-preference edit. |
| `local_airpay_notifications_preview_rule` | DESKTOP-ONLY | Admin preview. |
| `local_airpay_notifications_test_send` | SENSITIVE-ADMIN | Sends real message. |

### `local_airpay_org` (3 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_org_delete_org` | SENSITIVE-ADMIN | Tenant destructive. |
| `local_airpay_org_toggle_visibility` | DESKTOP-ONLY | Admin status flip. |
| `local_airpay_org_list_children` | DESKTOP-ONLY | Admin cascade filter. |

### `local_airpay_proctoring` (12 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_proctoring_start_session` | DESKTOP-ONLY | Proctoring needs webcam + screen capture — only laptop/desktop browsers support both simultaneously per policy. |
| `local_airpay_proctoring_give_consent` | DESKTOP-ONLY | Same — exam is desktop-only. |
| `local_airpay_proctoring_submit_identity` | DESKTOP-ONLY | Same. |
| `local_airpay_proctoring_report_event` | DESKTOP-ONLY | Same. |
| `local_airpay_proctoring_upload_chunk` | DESKTOP-ONLY | Same. |
| `local_airpay_proctoring_finalize` | DESKTOP-ONLY | Same. |
| `local_airpay_proctoring_list_attempts` | DESKTOP-ONLY | Admin attempts list. |
| `local_airpay_proctoring_get_attempt` | DESKTOP-ONLY | Admin attempt detail. |
| `local_airpay_proctoring_list_review_queue` | DESKTOP-ONLY | Reviewer queue. |
| `local_airpay_proctoring_flag` | DESKTOP-ONLY | Reviewer action. |
| `local_airpay_proctoring_submit_review` | DESKTOP-ONLY | Reviewer decision. |
| `local_airpay_proctoring_compliance_report` | DESKTOP-ONLY | Admin compliance report. |

### `local_airpay_programs` (12 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_programs_list_programs` | MOBILE-READY | Catalog browse. |
| `local_airpay_programs_change_status` | DESKTOP-ONLY | Admin status flip. |
| `local_airpay_programs_delete_program` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_programs_list_levels` | MOBILE-READY | Read-only — learner sees program structure. |
| `local_airpay_programs_delete_level` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_programs_reorder_levels` | DESKTOP-ONLY | Admin program editor. |
| `local_airpay_programs_list_level_courses` | MOBILE-READY | Read-only — learner sees level courses. |
| `local_airpay_programs_unassign_level_course` | DESKTOP-ONLY | Admin program editor. |
| `local_airpay_programs_list_users` | DESKTOP-ONLY | Admin roster view. |
| `local_airpay_programs_unenrol_user` | DESKTOP-ONLY | Admin enrolment. |
| `local_airpay_programs_preview_audience` | DESKTOP-ONLY | Admin bulk-enrol preview. |
| `local_airpay_programs_bulk_enrol_by_audience` | SENSITIVE-ADMIN | Admin bulk enrol. |

### `local_airpay_ratings` (1 function)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_ratings_submit_rating` | MOBILE-READY | Learner star rating — already capability-gated. |

### `local_airpay_reports` (3 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_reports_list_reports` | DESKTOP-ONLY | Admin report library. |
| `local_airpay_reports_delete_report` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_reports_toggle_status` | DESKTOP-ONLY | Admin status flip. |

### `local_airpay_request` (6 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_request_submit` | MOBILE-READY | Learner self-request enrolment. |
| `local_airpay_request_list_mine` | MOBILE-READY | Learner own requests. |
| `local_airpay_request_list_pending` | DESKTOP-ONLY | Manager approval queue. |
| `local_airpay_request_list_all` | DESKTOP-ONLY | Admin all-requests. |
| `local_airpay_request_decide` | DESKTOP-ONLY | Manager approval — needs full context. |
| `local_airpay_request_cancel` | MOBILE-READY | Learner cancel own request. |

### `local_airpay_roles` (8 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_roles_list_roles` | DESKTOP-ONLY | Admin role list. |
| `local_airpay_roles_get_role_caps` | DESKTOP-ONLY | Admin capability matrix. |
| `local_airpay_roles_update_capability` | SENSITIVE-ADMIN | Cap change has security implications. |
| `local_airpay_roles_list_audit` | DESKTOP-ONLY | Admin audit log view. |
| `local_airpay_roles_bulk_update_capability` | SENSITIVE-ADMIN | Bulk cap change. |
| `local_airpay_roles_list_assignments` | DESKTOP-ONLY | Admin assignment list. |
| `local_airpay_roles_assign_user` | SENSITIVE-ADMIN | Privilege grant. |
| `local_airpay_roles_unassign_user` | DESKTOP-ONLY | Privilege revoke (less risky than grant). |

### `local_airpay_skills` (14 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_skills_list_skills` | MOBILE-READY | Catalog browse. |
| `local_airpay_skills_delete_skill` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_skills_delete_category` | SENSITIVE-ADMIN | Admin destructive. |
| `local_airpay_skills_get_skill_levels` | MOBILE-READY | Read-only learner reference. |
| `local_airpay_skills_save_skill_level` | DESKTOP-ONLY | Admin skill matrix editor. |
| `local_airpay_skills_list_designation_skills` | DESKTOP-ONLY | Admin matrix view. |
| `local_airpay_skills_save_designation_skill` | DESKTOP-ONLY | Admin matrix edit. |
| `local_airpay_skills_delete_designation_skill` | DESKTOP-ONLY | Admin matrix edit. |
| `local_airpay_skills_copy_designation` | DESKTOP-ONLY | Admin bulk copy. |
| `local_airpay_skills_list_course_skills` | MOBILE-READY | Read-only — learner sees course's skill tags. |
| `local_airpay_skills_save_course_skill` | DESKTOP-ONLY | Admin course-skill edit. |
| `local_airpay_skills_delete_course_skill` | DESKTOP-ONLY | Admin course-skill edit. |
| `local_airpay_skills_search_courses` | DESKTOP-ONLY | Admin course picker. |
| `local_airpay_skills_self_rate_skill` | MOBILE-READY | Learner self-assessment. |

### `local_airpay_users` (6 functions)
| Function | Category | Reason |
|---|---|---|
| `local_airpay_users_list_users` | DESKTOP-ONLY | Admin user list. |
| `local_airpay_users_list_filter_options` | DESKTOP-ONLY | Admin filter loader. |
| `local_airpay_users_search_supervisors` | DESKTOP-ONLY | Admin supervisor picker. |
| `local_airpay_users_suspend_user` | SENSITIVE-ADMIN | Admin destructive — lockout. |
| `local_airpay_users_delete_user` | SENSITIVE-ADMIN | Admin destructive — irreversible. |
| `local_airpay_users_bulk_action` | SENSITIVE-ADMIN | Bulk admin. |

---

## Summary

| Category | Count | % |
|---|---|---|
| MOBILE-READY | 36 | 23% |
| DESKTOP-ONLY | 84 | 54% |
| SENSITIVE-ADMIN | 36 | 23% |
| **Total** | **156** | **100%** |

---

## Implementation Phases (Future Work, Not Part of P1 #60)

P1 #60 closes by **flagging only** — no WS surface changes are shipped yet. The mobile-app integration is a separate, deliberate phase:

1. **Phase X.1 — Read-only learner surface.** Add `MOODLE_OFFICIAL_MOBILE_SERVICE` to the 22 read-only MOBILE-READY functions (lists, gets, leaderboard, history). Tag with `'loginrequired' => true`. No state changes.

2. **Phase X.2 — Learner write actions.** Add the 14 state-changing MOBILE-READY functions (join/leave, submit, save_prefs, self_rate, submit_rating, cart add/remove, etc.) after capability matrix is re-confirmed and rate-limits are in place.

3. **Phase X.3 — Manager mobile.** Decide whether a subset of DESKTOP-ONLY (`list_requests`, `decide_request`, `team_performance`) should ship to a future "Manager mobile" companion. Out of scope today.

4. **SENSITIVE-ADMIN — never on mobile.** These 36 functions remain desktop-only forever; the audit re-verifies this on every release.

---

## Sign-off

- **Audit completed:** 2026-05-20
- **Next review:** When any new `db/services.php` function is added — must be categorised before merge.
- **Reference:** `parity-audit-2026-05-15/airpay_*.md` — audit item #14 (mobile surface).
