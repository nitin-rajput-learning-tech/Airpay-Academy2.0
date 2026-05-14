# Admin-Feedback Deployment Runbook

Deploys the 17-commit Sprint A-D + polish work delivered on
2026-05-13. This runbook turns the work in
`78647e47d..87cd8ff41` into a sequenced cutover procedure for the
production server (airpay.academy).

> Estimated cutover window: **30 minutes**.
> Risk level: **LOW** — all changes are additive (new tables,
> capabilities, columns); the only schema mutation on an existing
> column is `local_airpay_email_log.status` widened from char(20)
> to char(32), with the index drop/re-add already coded in the
> upgrade.

---

## Pre-cutover (T-24h)

1. Confirm the production branch is at `87cd8ff41` or later:
   ```
   git fetch origin
   git log --oneline origin/production -3
   ```

2. Schedule the cutover window. No active sessions need to be
   logged out — the schema change is online (drop-index then
   add-index doesn't block reads).

3. Take a database snapshot via the AWS RDS console:
   `airpay-academy-prod-2026-05-13-pre-sprintabcd`.

4. Verify the local_airpay_org plugin is installed and active
   (Sprint C/D both depend on it):
   ```
   mysql> SELECT id, value FROM mdl_config_plugins
            WHERE plugin = 'local_airpay_org'
              AND name = 'version';
   ```
   Expected: a version >= `2026041600`.

---

## Cutover (T-0)

### Step 1 — pull the production branch

```
cd /path/to/airpay.academy/moodle
git pull origin production
```

Should fast-forward 17 commits.

### Step 2 — run the Moodle upgrade

```
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

The upgrade does seven things, in order:

| Migration | What changes |
|-----------|--------------|
| `local_airpay_emails:2026051301` | adds attachment_filename + certificate_issue_id columns to email_log; adds cadence_days_json + max_reminders_per_user + auto_stop_on_completion to rules; seeds 2 default rules (course_completed, course_incomplete) |
| `local_airpay_emails:2026051302` | widens email_log.status from char(20) to char(32) — drops idx_status, changes precision, re-adds idx_status |
| `local_airpay_courses:2026051302` | creates `local_airpay_courses_tenant_share` table (Sprint C share map) |
| `local_airpay_courses:2026051303` | creates `local_airpay_courses_requests` table (Sprint D request workflow) |
| `block_airpay_cron_health` first install | block becomes addable to dashboards |
| `block_airpay_cert_health` first install | block becomes addable to dashboards |
| New capabilities registered | `local/airpay_courses:share_to_tenant`, `:request_course`, `:approve_request` |

### Step 3 — purge caches

```
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 4 — verify with pre-deploy gates

```
cd /path/to/airpay-ld-os
bash moodle-enhancement/deploy/pre_deploy_validate.sh
```

Expected: 9 of 10 gates green. Gate 3 (cron-health CLI) will pass
on prod because the cron daemon IS running there (unlike dev).

### Step 5 — verify Sprint A on production

If the admin's "can't add courses to learning path" symptom was
deployment-related, this will now pass cleanly:

```
sudo -u www-data php local/airpay_learningpath/cli/diagnose_admin_ux.php \
    --user=academy@airpay.co.in
```

Expected: all 7 checks PASS. If anything FAILs, follow the FIX
instruction printed by each check. The `--fix-caps` flag will
idempotently grant the four write caps to the `manager` role.

### Step 6 — smoke-test Sprint B (course-completion email)

Easiest path: have a test user complete a course that has a
`tool_certificate` activity. Verify:

1. User receives the email with subject "Congratulations on
   completing <course name>".
2. Email body says "Your certificate is **attached to this email**
   as a PDF."
3. The PDF attachment is named `Airpay-certificate-<code>.pdf`.
4. Audit log shows the send:
   ```
   sudo -u www-data php local/airpay_emails/cli/cert_emails_report.php \
       --detail --since=YYYY-MM-DD
   ```

### Step 7 — smoke-test Sprint C (cross-tenant share, push side)

1. As site admin: navigate to `/local/airpay_courses/index.php`.
2. On any course row, click the new handshake icon → goes to
   `/local/airpay_courses/share.php?id=<courseid>`.
3. Tick "Public" → submit.
4. As a Public-tenant manager (e.g. login as a /77/... user):
   - The shared course should appear in `/local/airpay_catalog/`.
   - Card should show a "Provided by Airpay Academy" badge.
5. CLI verify:
   ```
   sudo -u www-data php local/airpay_courses/cli/manage_shares.php --list
   ```

### Step 8 — smoke-test Sprint D (request workflow, pull side)

1. As a Public-tenant manager: open the sidebar → "Browse Airpay
   Library" → `/local/airpay_courses/browse_airpay.php`.
2. Find any Airpay-owned course (one in `/1/...` tree) that is NOT
   currently shared. Click "Request access".
3. As site admin: open "Course-share Requests" sidebar entry →
   `/local/airpay_courses/manage_requests.php`. The pending request
   should appear.
4. Click "Approve". The catalogue cache is purged + the share row
   is inserted.
5. Back as the Public manager: refresh `/local/airpay_catalog/` —
   the course is now in the catalog with the provenance badge.

### Step 9 — add the dashboard widgets to /my/

For each Airpay site admin (or for the default /my/ page):

1. Login as siteadmin → `/my/`.
2. Click "Customise this page".
3. Drop the "Airpay Cron Health" and "Airpay Certificate Health"
   blocks into a region.
4. Click "Stop customising this page".

Subsequent admin logins will see the blocks automatically.

### Step 10 — run the post-deploy verification (Day-2 addition)

One command, all sprints checked, pass/fail report.

```
cd <production server's moodle parent root>
bash <path-to-checkout>/moodle-enhancement/deploy/post_deploy_verify.sh \
     --user=<admin email>
```

Expected output: 5+ PASS, 0 FAIL, optionally 1 WARN if cron hasn't
cycled yet. Add `--json` for CI dashboard ingestion.

The script wraps every diagnostic CLI shipped across Sprints A-D
plus a presence check for both dashboard blocks. Run it after Steps
2-9 are complete; sign off the cutover only when it reports 0 FAIL.

---

## Post-cutover (T+1h)

1. Tail the Moodle error log for any unexpected exceptions in the
   new code paths. Filter on `local_airpay_emails`, `local_airpay_courses`,
   `block_airpay_cert_health`, `block_airpay_cron_health`.

2. Watch the audit log for the new event types:
   ```
   sudo -u www-data php local/airpay_core/cli/audit_log_recent.php \
       --hours=2 --filter=course_share
   ```
   (Note: this CLI may not exist yet — alternative: query the
   `mdl_logstore_standard_log` table directly.)

3. Verify the cron-health CLI shows zero stuck Airpay tasks:
   ```
   sudo -u www-data php local/airpay_core/cli/cron_health.php
   ```

---

## Rollback procedure

The cutover is online-safe but if a hard rollback is needed:

1. Restore the pre-cutover RDS snapshot
   `airpay-academy-prod-2026-05-13-pre-sprintabcd`.
2. Reset the git checkout:
   ```
   git reset --hard 78647e47d~1     # the commit BEFORE Sprint A
   php admin/cli/purge_caches.php
   ```
3. Run `pre_deploy_validate.sh` to confirm restoration.

The new tables (`local_airpay_courses_tenant_share`,
`local_airpay_courses_requests`) and the additional columns on
`local_airpay_email_log` / `local_airpay_email_rules` will be
silently dropped by the snapshot restore. No data migration is
needed for rollback because none of the new tables have
production data yet at T+0.

---

## What's NOT in this runbook

- **k6 load test** — owned by Devops, separate runbook.
- **Pen-test sign-off** — owned by Security team, separate
  runbook.
- **Tenant-onboarding** — Sprint C/D capability grants
  (`share_to_tenant` for siteadmin, `request_course` for managers)
  happen automatically at install. For non-default roles, the
  Site Admin needs to grant via Users → Permissions → Define roles.

---

## Quick contact list

| Failure mode | Owner |
|--------------|-------|
| Schema migration fails | Head of L&D (rollback to snapshot) |
| Cert PDF attachment doesn't ship | Head of L&D (check tool_certificate plugin status) |
| Share button missing on course list | Head of L&D (check :share_to_tenant cap grant) |
| Email log fills up too fast | Devops (tune cleanup-old-logs scheduled task cadence) |
