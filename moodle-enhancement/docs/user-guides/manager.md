# Manager user guide — Sentientia LMS / Airpay Academy

**Audience:** Line Manager (sees direct reports' training progress; approves requests; receives escalation emails).
**Status:** v1 draft (2026-05-24).
**Cross-references:** `learner.md`, `tenant-admin.md`.

---

## 1. My Team dashboard

`/local/airpay_users/myteam.php`

Your default landing page. Shows every direct report (via HRMS sync's
`manager_id` field) with:

- Profile photo + name + designation
- Compliance status pill (green/yellow/red/grey — same colours as the
  compliance dashboard, see `tenant-admin.md` §5)
- Last-activity date (when they last logged in or completed something)
- "View profile" link → drill into per-person progress

If a direct report is missing from your view, two likely causes:
- HRMS sync hasn't reflected the reporting-line change yet (cron runs nightly)
- The user's `manager_id` is empty — escalate to your Tenant Admin

---

## 2. Approval queue (`local_airpay_request`)

When a direct report requests access to a course outside their default
audience, you get an approval request:

`/local/airpay_request/myapprovals.php`

Each row shows:
- Requester name + designation
- Course they want
- Their reason (min 20 chars — enforced at request time)
- Submitted timestamp + SLA countdown (48h target)

Action buttons:
- **Approve** — adds them to the course; reason field is optional
- **Reject** — REQUIRES a reason (the requester sees your reason)
- **Delegate** — pass to another manager (skip-level use case)

You get email + WhatsApp (if opted-in) nudges 24h and 47h after submission
if you haven't actioned the request.

---

## 3. Compliance dashboard

`/local/airpay_compliance_report/team.php`

Your team's mandatory-training rollup. By person × by training requirement,
with the same green/yellow/red/grey scheme. Click a red cell to see:
- What's blocking completion (not started? in progress? failed?)
- Days overdue
- Last-nudged date (your direct report has been pinged by cron)

You can also bulk-message the team from this page: "I see X of you are
overdue on compliance training — please prioritise this week."

---

## 4. Overdue escalation emails (system-generated; how to action)

The `local_airpay_courses` overdue-escalation cron fires daily. If a
direct report is past their course deadline, you get an email like:

> Subject: 2 of your direct reports are overdue on Compliance Training
>
> The following learners have not completed required training by their deadline:
>   - Asif Ansari — "Anti-Bribery 2026" (3 days overdue)
>   - Priya Sharma — "Information Security 2026" (1 day overdue)
>
> Take action: <link to compliance dashboard>

The email is generated from `local_airpay_courses/lang/en/manager_escalation_email.md`
(Hindi version in `hi/`). Tenant Admin can customise the template.

You're expected to:

1. Check the compliance dashboard within 24h
2. Reach out 1:1 to each overdue person
3. If a learner has a legitimate blocker (sick leave, equipment issue),
   help unblock — don't just resend the email

---

## 5. Skill ratings + reviews

`local_airpay_skills` supports a self-rate + manager-validate workflow:

### Self-rate (learner does this)

Learner opens `/local/airpay_skills/self_rate.php`, picks skills from
their role's skill catalogue, rates themselves on a 1-5 scale per skill.

### Manager validate (you do this)

`/local/airpay_skills/team_rate.php` — see each direct report's
self-ratings. You can:

- **Endorse** — agree with their rating
- **Override** — set your own rating (with optional comment)
- **Request evidence** — ask them to upload a portfolio piece

The combined "validated rating" feeds into:
- `local_airpay_skills` skill matrix (your team's view, your tenant's matrix)
- `local_airpay_analytics` skill-radar chart on dashboards
- HR review cycle integration (via REST endpoint — Tenant Admin sets this up)

### Audit log

Every skill rating change writes to `local_airpay_skills/audit.php`.
You can see who changed what when (e.g. "Asif self-rated 'SQL' as 4 on
2026-05-15; Priya overrode to 3 on 2026-05-22 with comment 'awaiting
project evidence'").

---

## 6. Reporting + CSV export

Every manager-scoped report has a CSV export button. Useful for:

- Quarterly business reviews (export compliance + skills tables)
- 1:1 prep (export the person's progress history)
- Skip-level dashboards (your skip can re-aggregate your team's CSV with theirs)

Format: semi-colon delimited, UTF-8 with BOM (opens cleanly in Excel
even with Hindi rows).

---

## 7. References

- `learner.md` — what your direct reports see
- `tenant-admin.md` — broader tenant-level view
- `local_airpay_request` plugin docs (state-card in `state-cards/`)

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold |
