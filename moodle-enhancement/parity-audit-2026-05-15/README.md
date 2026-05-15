# BizLMS → Airpay Feature Parity Audit (2026-05-15)

## Why this exists

Airpay Academy is being rebuilt on top of an Airpay-owned plugin layer. The
original BizLMS codebase lives at `C:\xampp\htdocs\moodle5\bizlms_disabled\`
and has 23 plugins. Our rebuild (`local/airpay_*`) has 31. But not every
BizLMS feature has been carried over — some surfaces shipped as
shells, others lost workflows that were essential for enterprise use
(multi-level org filters, bulk actions, etc.).

The user's directive (2026-05-15): "stakes are high, you have to test each
feature, button, link, multistep tasks, as a human user would interact
with it, record all findings and then start fixing, upgrading."

## Audit framework

Each plugin gets one MD file: `[airpay_plugin].md`. Inside:

1. **BizLMS source path** + line counts
2. **Airpay source path** + line counts
3. **Feature parity matrix**:
   - Columns: Feature name | BizLMS had | Airpay has | Gap severity
   - Severity:
     - **P0** = blocks enterprise use (admin can't do their job)
     - **P1** = important workflow degraded but workaround exists
     - **P2** = polish / nice-to-have
4. **End-to-end user flows** — list the multi-step tasks a real user does,
   note which steps work and which fail.
5. **Recommended fix order** with effort estimate.

## Plugin coverage (23 BizLMS → 31 Airpay)

| BizLMS | Airpay equivalent | Audit file |
|--------|-------------------|-----------|
| users | airpay_users | airpay_users.md |
| costcenter | airpay_org | airpay_org.md |
| courses | airpay_courses + airpay_catalog | airpay_courses.md |
| classroom | airpay_classroom | airpay_classroom.md |
| onlineexams | airpay_exams | airpay_exams.md |
| program | airpay_programs | airpay_programs.md |
| learningplan | airpay_learningpath | airpay_learningpath.md |
| evaluation | airpay_evaluation | airpay_evaluation.md |
| ratings | airpay_ratings | airpay_ratings.md |
| recompletion | airpay_recompletion | airpay_recompletion.md |
| request | airpay_request | airpay_request.md |
| assignroles | airpay_roles | airpay_roles.md |
| myteam | airpay_manager | airpay_manager.md |
| notifications | airpay_notifications | airpay_notifications.md |
| skillrepository | airpay_skills | airpay_skills.md |
| biz_cart | airpay_cart | airpay_cart.md |
| forum | (no direct equivalent) | unmapped.md |
| groups | (cohorts handled by Moodle) | unmapped.md |
| location | (no direct equivalent) | unmapped.md |
| search | (Moodle global search) | unmapped.md |
| tags | (Moodle tags) | unmapped.md |
| blocks | blocks/ | blocks.md |
| custom_category | (Moodle categories) | unmapped.md |

## Concrete fix waves

After the audit, fixes are batched into **waves**:

- **Wave 1 (P0 only, this week)**: Whatever blocks enterprise admin work today.
- **Wave 2 (P1, next week)**: Important workflows.
- **Wave 3 (P2, ongoing)**: Polish + ergonomics.
