# Tenant Admin user guide — Sentientia LMS / Airpay Academy

**Audience:** Tenant Admin (BizLMS costcenter admin — scoped to ONE tenant within a customer).
**Status:** v1 draft (2026-05-24).
**Tenants on Airpay customer-zero:** id=1 (Airpay employees), id=77 (Public learners), id=177 (ZEEA partner).
**Cross-references:** `site-admin.md`, `course-author.md`, `manager.md`, `learner.md`.

---

## 1. Your scope (what you can and cannot see)

| Capability | Tenant Admin | Site Admin |
|------------|--------------|------------|
| Manage users in YOUR tenant | ✅ | ✅ |
| Manage users in OTHER tenants | ❌ | ✅ |
| Create courses / programs / paths in YOUR tenant | ✅ | ✅ |
| Edit Site Admin–only courses (Sentientia core training) | ❌ | ✅ |
| Configure SMTP, security, schema | ❌ | ✅ |
| Plugin install / uninstall | ❌ | ✅ |
| Customer-level feature flags | ❌ | ✅ |
| Tenant-level feature flags | ✅ (read-only) | ✅ (write) |
| Read tenant-scoped reports | ✅ | ✅ (all tenants) |
| Branding (logo, colours) | ❌ (read-only — Site Admin owns) | ✅ |

Tenant scope is enforced at the DB query layer — every Sentientia query
filters by `costcenterid` derived from `$USER->open_path`. If you ever
see data that doesn't belong to your tenant, escalate to Site Admin
immediately (a missing scope-filter is a P0 bug).

---

## 2. User management within your tenant

### Add a user

`/local/airpay_users/add.php` — 24-column form. Required: username,
email, firstname, lastname. Optional but recommended: employee ID,
manager, designation, DOJ, DOB.

The user is auto-placed in your tenant. You cannot set `costcenterid`
manually — the form takes your tenant from your session.

### Bulk add (CSV)

`/local/airpay_users/import.php`. Same shape as the HRMS sync, scoped
to your tenant. Upload a CSV with header row → preview → confirm.

### Suspend / un-suspend

`/local/airpay_users/manage.php` → row action menu. Suspending a user
blocks login + freezes their enrolments. Use this when an employee
leaves the company (HRMS sync will eventually do it automatically but
you can preempt).

### Reset password

You can trigger a password-reset email but you CANNOT see or set
passwords. If a user is locked out + can't access email, escalate
to Site Admin who can use the CLI tool.

---

## 3. Course / program / path creation + assignment

### Create a course

`/course/edit.php` (when logged in as Tenant Admin, you only see your
tenant's category tree). Fill the standard fields + Sentientia-specific
ones:

- Audience: cohorts, designations, roles within YOUR tenant only
- Deadline reminders: how many days before due-date to nudge
- Hindi parity: 100% required if any Hindi user exists in your tenant
  (check `/local/airpay_courses/hindi_audit.php` to verify)

### Create a learning path

`/local/airpay_path/edit.php` — chain courses into a sequence with
prerequisites. Cohort filtering automatically scopes to your tenant.

### Create a program

`/local/airpay_programs/edit.php` — analogous to paths but with a
fixed delivery schedule (cohorted intake).

### Assign / enrol

Three patterns:

1. **Self-enrol** — set enrolment plugin to "Self enrolment"; learners
   click "Enrol me" from catalogue.
2. **Bulk-enrol UI** — `/local/airpay_courses/bulk_enrol.php` — pick
   users + courses, run.
3. **Cohort-driven** — cohort gets attached to the course; all current
   AND future cohort members enrol automatically.

---

## 4. Reporting dashboards (tenant-scoped)

| Report | URL | What it shows |
|--------|-----|---------------|
| Compliance status | `/local/airpay_compliance_report/dashboard.php` | Mandatory training completion % per user, with manager rollups |
| Course catalogue | `/local/airpay_catalog/mycourses.php` (admin view) | Catalogue browse + enrolment counts |
| Skill matrix | `/local/airpay_skills/matrix.php` | Self-rated + manager-validated skill levels |
| Engagement KPIs | `/local/airpay_analytics/admin.php` | DAU/WAU, course completion funnel, drop-off heatmap |
| Audit log | `/local/airpay_audit_log/index.php` | Sentientia events scoped to your tenant |

All reports support CSV export (semi-colon separator, UTF-8 with BOM).

---

## 5. Compliance status overview

Compliance training is the highest-stakes use case. Tenant Admin's
top-line view is `/local/airpay_compliance_report/dashboard.php`:

- **Green** — completed within deadline.
- **Yellow** — completed late (still counted).
- **Red** — not completed by deadline; auto-escalation email already sent to manager.
- **Grey** — assigned but deadline in the future.

Click any user-row to drill into per-course completion + recompletion history.

---

## 6. Welcome-email templates (per tenant)

`/local/airpay_users/welcome_template.php` — each tenant has its own
template with token substitution:

```
Hello {firstname},
Welcome to {tenant_name}. Your username is {username}.
Login at {login_url}.
```

Tokens supported: `{firstname}`, `{lastname}`, `{username}`, `{email}`,
`{tenant_name}`, `{login_url}`, `{designation}`, `{manager_name}`.

Hindi parity required for tenant id=1 (Airpay employees) since the
Hindi UI is enabled by default for that tenant.

---

## 7. WhatsApp opt-in management

You can see who in your tenant has opted in to WhatsApp notifications:

`/local/airpay_whatsapp/admin/opt_in_status.php`

You CANNOT force-enable a user — that would violate India's DPDP Act
2023 and Meta Business API ToS. If a learner asks "why aren't I getting
notifications?", check their `/user/preferences.php` page — they have
to opt in themselves.

---

## 8. Tenant-specific branding (read-only)

You CAN see the branding your Site Admin has configured for your tenant
at `/local/airpay_core/customer_brand.php?customerid=<your-customer>`.

You CANNOT change it. If you need a logo update or colour change,
file a request via your usual Sentientia support channel + Site Admin
applies it.

---

## 9. Escalation cues

Escalate to Site Admin when:

- A user can't reset their password via email
- A plugin appears disabled or returning errors site-wide
- Compliance training data looks wrong (e.g. completion counts impossible)
- You see data from a tenant that isn't yours (BUG — security report)
- A learner asks for a refund (Site Admin has paygw_airpay refund permissions)
- You need a NEW cohort created (Site Admin manages cohort definitions)

---

## 10. References

- `site-admin.md` — full-scope admin reference
- `manager.md` — what your tenant's line managers see
- `learner.md` — what your tenant's learners see
- `docs/customer-config/airpay.md` — customer-zero tenant tree
- `CLAUDE.md` §11 escalation flags

---

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold |
