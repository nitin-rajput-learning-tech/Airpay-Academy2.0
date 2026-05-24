# Site Admin user guide — Sentientia LMS / Airpay Academy

**Audience:** Site Admin (full superuser, all tenants, all customers).
**Status:** v1 draft (2026-05-24).
**Last verified against:** Moodle 5.1.3+ production, customer-zero (Airpay Payment Services).
**Cross-references:** `tenant-admin.md`, `course-author.md`, `manager.md`, `learner.md`, `public-learner.md`.

---

## 1. Day-1 setup checklist (post-deploy)

Run this whenever a fresh Sentientia LMS deploy lands or a customer onboards. Each item links to the relevant admin page.

| # | Step | URL | Notes |
|---|------|-----|-------|
| 1 | Confirm SSL + custom domain | `/admin/settings.php?section=httpsecurity` | Force HTTPS, HSTS, CSP. |
| 2 | Set site name + customer brand | `/local/airpay_core/customer_brand.php` | See §9 "Theme customization". |
| 3 | Run schema validator | `/admin/cli/check_database_schema.php` (CLI) | Must report "clean" — if not, escalate to Nitin before opening login. |
| 4 | Run cron once manually | `/admin/cli/cron.php` (CLI) | Bootstraps scheduled tasks; subsequent runs land via system cron. |
| 5 | Verify Sentientia plugin set | `/admin/plugins.php` → search "airpay" or "sentientia" | 30 `local_airpay_*` plugins + sentientia_* siblings must all be ENABLED at "Stable". |
| 6 | Configure SMTP | `/admin/settings.php?section=outgoingmailconfig` | Use the customer's SMTP relay. Verify with "Test" button. |
| 7 | Set timezone + locale | `/admin/settings.php?section=locale` | Asia/Kolkata for Airpay; per customer for others. |
| 8 | Configure HRMS sync | `/local/airpay_users/sync.php` | See §4 "User import". |
| 9 | Set retention policies | `/admin/settings.php?section=privacysettings` | GDPR-compliant defaults; per-customer override via `local_airpay_core::get_customer_branding()`. |
| 10 | Smoke a Learner login | Log in as a test learner | Walks: catalogue → enrol → SCORM → cert. |

---

## 2. Tenant management (Switchboard + customer-scope flags)

Sentientia LMS supports two layers of scope:

- **Customer** — the enterprise (Airpay Payment Services, Future-Customer-N, ...). Customer ID 1 = Airpay (customer-zero).
- **Tenant** — a BizLMS costcenter inside the customer. Airpay has 3: id=1 (Airpay employees), id=77 (Public learners), id=177 (ZEEA partner).

### Switchboard (`/local/airpay_core/switchboard.php`)

The Switchboard is where Site Admin toggles per-feature flags. Default ALL off. The 5-level precedence is:

```
1. customer + tenant override   (highest)
2. customer-wide override
3. legacy tenant override
4. global override
5. registered default            (lowest — usually OFF for safety)
```

### Customer-scope tab (gated)

The customer-scope tab strip appears only after Site Admin enables
the gate flag `sentientia.customer_level_flags.enabled`. Until then,
customer-scoped DB rows are inert and the UI is identical to Phase A0.
ADR-002 details the precedence rules and rollback safety.

### Tenant detection on every page

Production code uses `$USER->open_path` (NOT `open_costcenterid` — that
column doesn't exist on production). Parse with:

```php
$parts = explode('/', $USER->open_path ?? '');
$costcenterid = (int)($parts[1] ?? 0);
```

---

## 3. Plugin management

### Sentientia plugins (current — renaming to `local_sentientia_*` over time)

| Plugin family | Count | Purpose |
|---------------|-------|---------|
| `local/airpay_*` | 30+ | Core L&D verticals: courses, programs, paths, classroom, evaluation, recompletion, skills, exams, request, cart, users, badges, certificates, audit, analytics, reports, search, settings, switch boards, signup. |
| `local/sentientia_*` | 1 (PWA) + growing | Product surface: PWA, live (mentimeter), AI-quiz (planned). |
| `blocks/airpay_*` | several | Dashboard blocks (catalogue tile, deadline tile, skill radar, etc.) |
| `theme/airpayux` | 1 | Standalone fork of `epsilon`. `$THEME->parents = []`. We own all 514 files. |
| `paygw/airpay` | 1 | Airpay payment gateway (newly tracked in repo, 2026-05-23). |
| `quizaccess/airpay_proctoring` | 1 | Proctored quiz access rule. |

### Disable / enable

`/admin/plugins.php` → click "Settings" or "Disable" per row. Disabling
a `local_airpay_*` plugin is generally safe (each plugin is feature-flag
gated and falls back gracefully). **Exception:** `local_airpay_core` —
disabling breaks every other Sentientia plugin (the feature-flag and
tenant-resolution central registry). Never disable it.

---

## 4. User import

### Cron-driven HRMS sync (`local_airpay_users`)

Production reality: Airpay's HRMS pushes a CSV nightly to a watch
folder; `local_airpay_users::sync_cron()` picks it up. 24 columns,
all optional but the first 4 are required (username, email, firstname,
lastname).

- Settings: `/local/airpay_users/sync_settings.php`
- Logs: `/local/airpay_users/sync_log.php` (admin only)
- Manual trigger: `/admin/cli/scheduled_task.php --execute=\\local_airpay_users\\task\\hrms_sync_task`

### Manual CSV upload

`/local/airpay_users/import.php` — Site Admin or Tenant Admin can paste
a CSV. The form auto-detects columns; first row must be a header.

### Welcome email tokens

After import, new users receive a per-tenant-templated welcome email
(token-substituted: `{firstname}`, `{tenant_name}`, `{login_url}`, etc).
Templates live in `local_airpay_users/templates/welcome-{tenantid}.md`.

---

## 5. SCORM upload + validation gates

Sentientia LMS's signature feature is the SOP → SCORM pipeline. Site Admin
oversees upload + validation.

### Upload flow

1. Author / vendor delivers a SCORM 1.2 ZIP (root level — imsmanifest.xml at ZIP root, not nested).
2. Site Admin opens `/local/airpay_courses/upload_scorm.php`.
3. Validator runs automatically before any file lands in the courses tree:
   - imsmanifest.xml at ZIP root: ✅ required
   - `<organizations default="ORG_01">` has items, href matches real files
   - masteryscore = 70 (Airpay default; per-customer override in customer config)
   - All files in manifest exist in ZIP
4. If validation fails, the form refuses upload + lists the gate that failed.
5. On success, file is staged in the activity's draft area.

### Per-course SCORM admin

`/mod/scorm/view.php?id=<cmid>` → "Reports" link shows per-learner
status + Mastery scores. Site Admin can manually mark complete (audit log
captures who did it + when).

---

## 6. Audit log + reporting

### Site-level audit log

`/admin/report/log/index.php` is Moodle's standard log report. For
Sentientia-specific actions, use:

- `/local/airpay_audit_log/index.php` — combined Sentientia event view
- `/local/airpay_analytics/admin.php` — KPI dashboards + funnel charts (tenant-scoped)

### Compliance dashboards

`/local/airpay_compliance_report/dashboard.php` — Site Admin sees ALL tenants. Tenant Admin sees only their own.

### CSV export

Almost every report has a CSV export button. The format is consistent
across Sentientia: UTF-8 with BOM, semi-colon separator (Excel-friendly
in India).

---

## 7. PWA + push notifications admin

The Progressive Web App (`local_sentientia_pwa`) and push notification
subsystem are admin-managed.

### VAPID key pair

- Generate: `/admin/cli/sentientia_pwa_vapid_keygen.php` (one-time per site)
- View public key (safe to share): `/local/sentientia_pwa/keys.php`
- Private key is envelope-encrypted at rest (ADR-008 + commit history).
- **Never** check the private key into git or send via email.

### Push delivery log

`/local/sentientia_pwa/admin/push_log.php` — per-user delivery success/fail.

### Capability inventory

`/local/sentientia_pwa/capabilities.php` — what every browser-platform
combination can/cannot do (badge counts, notification actions, iOS quirks).

---

## 8. WhatsApp / SMS notifications admin

Sentientia LMS uses WhatsApp Business API for course-deadline + completion
notifications (Stream C — `local_airpay_whatsapp`).

### Configuration

- `/local/airpay_whatsapp/settings.php` — Business API token + phone number ID
- Templates: pre-approved by Meta (per India regulation). Stored in
  `local_airpay_whatsapp/db/templates.php`. Site Admin can add but each
  new template needs Meta approval.

### Opt-in flow

Users opt-in via their `/user/preferences.php` page. Default is OFF (per GDPR + DPDP Act 2023 best practice). Site Admin can NOT force-enable a user — that's a hard rule.

### Delivery analytics

`/local/airpay_whatsapp/admin/analytics.php` — sent / delivered / read /
failed counts per template per tenant.

---

## 9. Theme customization (per-customer branding)

`local_airpay_core` exposes `get_customer_branding($customerid)` which
the `core_renderer` consumes. Each customer can override:

- Logo (light + dark)
- Primary + accent + background colours
- Typography (font family)
- Favicon

### Configure via UI

`/local/airpay_core/customer_brand.php?customerid=1` — Site Admin only.
Changes apply on next page load (no cache purge needed; the renderer
reads the cached customer brand on every request).

### Configure via DB (advanced)

`mdl_local_airpay_customer_brand` table. Schema in ADR-008. Don't edit
directly unless you know exactly what you're doing — the UI handles
validation that raw SQL skips.

### CSS / SCSS overrides

Per-customer SCSS lives in `theme/airpayux/scss/customers/{shortname}.scss`.
Imported conditionally by `lib.php::theme_airpayux_get_pre_scss()`.

---

## 10. Emergency procedures

### Password reset CLI

If a user (including a Site Admin) is locked out:

```bash
php admin/cli/reset_password.php --username=academy@airpay.co.in --password=NewStrongPass1!
```

The user MUST change it on next login.

### Cache purge

```bash
php admin/cli/purge_caches.php
```

Wipes ALL caches (theme, lang, MUC, opcache). Pages re-render slow for the
next 1-2 requests, then back to normal. **Don't** purge during peak hours
on production unless absolutely required.

### Rollback

The production deploy pattern is "file copy + db upgrade". Rollback:

1. Restore `/var/www/moodle` from the pre-deploy snapshot.
2. Run a SQL rollback script (Site Admin should have this ready before
   any deploy that includes a DB migration).
3. `php admin/cli/purge_caches.php` after the file restore.

The cleanest rollback is via the git tag of the pre-deploy commit:

```bash
git checkout <pre-deploy-tag>
# rsync to /var/www/moodle
# restore db dump
# purge caches
```

### Maintenance mode

```bash
php admin/cli/maintenance.php --enable
# ... do work ...
php admin/cli/maintenance.php --disable
```

Maintenance mode shows a configurable holding page to non-admins;
admins can still log in for troubleshooting.

### When NOT to act

If you see something unexpected — DB schema mismatch, fatal error on a
page that worked yesterday, suspicious login pattern — **stop and
escalate to Nitin Rajput before doing anything destructive**. The
escalation flags in `CLAUDE.md` §12 list the specific triggers.

---

## 11. References

- `D:\Claude Local\airpay-ld-os\CLAUDE.md` — operating rules
- `docs/adr/` — architectural decisions (ADR-001 fork strategy, ADR-002 customer-level flags, ADR-008 customer brand schema, ADR-011 Moodle 5.2 upgrade)
- `docs/customer-config/airpay.md` — customer-zero reference config
- `docs/customer-config/TEMPLATE.md` — skeleton for onboarding future customers
- `moodle-enhancement/PROJECT-STATE.md` — current phase + recent shipped items

---

## 12. Versioning

This guide tracks the production-deployed state of Sentientia LMS.
Major version bumps come with a section-by-section diff at the top
("changed since vN: ...") so admins can spot-check what's new.

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold per Goal C outline |
