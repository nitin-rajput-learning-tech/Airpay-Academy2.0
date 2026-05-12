# Production Cutover Runbook — Airpay Academy 2.0

**Last updated:** 2026-05-12
**Target environment:** `https://www.airpay.academy/` (AWS, MySQL 8.0 RDS, Apache)
**Source environment:** `D:\Claude Local\airpay-ld-os\moodle-enhancement\` (Windows dev box → Git → GitHub `production` branch → IT deploy)

> ⚠ **GATE:** Do not run this runbook until all BLOCKING findings in
> `PHASE-8-SECURITY-AUDIT.md` are resolved. The 2026-05-12 audit returned
> **NO-GO** with 11 blocking findings. Re-audit must return **GO** before
> proceeding to step 1.

---

## 0. Pre-flight checklist (T-minus 24 hours)

Tick every box. If any unchecked, **abort and reschedule**.

```
□ Security audit re-run returns GO verdict (no blocking findings)
□ DB backup taken of production within last 6h, restore tested in staging
□ File backup of /var/www/moodle taken to S3, manifest verified
□ Maintenance-mode plan agreed with Nitin (window: usually Sat 22:00-23:30 IST)
□ Production SMTP confirmed working (test mail sent and received)
□ Production AWS credentials for proctoring S3 + Rekognition rotated and validated
□ Payment gateway sandbox credentials swapped to prod credentials in .env
□ Comms to L&D team scheduled: T-24h heads-up + T-1h "starting" + T+0 "complete"
□ Rollback path documented and IT on-call confirmed available for 24h post-cutover
□ Smoke-test test user accounts confirmed working: academy@, nitin.rajput@,
  shivam.sharma@, asif.ansari@, academyexadmin@, public.uat@, user.4156200@
```

---

## 1. Maintenance mode on (T-minus 5 min)

On production server (SSH):

```bash
sudo -u www-data php /var/www/moodle/admin/cli/maintenance.php --enable
sudo -u www-data php /var/www/moodle/admin/cli/maintenance.php --enablelater=300
```

Verify front-page returns the maintenance banner. Take screenshot for the
post-mortem packet.

---

## 2. Code deploy (T+0)

From the Windows dev box, push the production branch:

```powershell
cd "D:\Claude Local\airpay-ld-os"
git status                                # should be clean
git log --oneline -5                      # confirm head commit you expect
git push origin production
```

On production server, pull and copy:

```bash
cd /opt/deploy/Airpay-Academy2.0
git fetch origin
git checkout production
git pull --ff-only origin production

# Rsync each surface — explicit list, never `*` which can pick up junk:
rsync -av --delete moodle-enhancement/theme/airpayux/      /var/www/moodle/theme/airpayux/
rsync -av --delete moodle-enhancement/local/airpay_cart/   /var/www/moodle/local/airpay_cart/
rsync -av --delete moodle-enhancement/local/airpay_proctoring/ /var/www/moodle/local/airpay_proctoring/
rsync -av --delete moodle-enhancement/local/airpay_recompletion/ /var/www/moodle/local/airpay_recompletion/
rsync -av --delete moodle-enhancement/local/airpay_request/ /var/www/moodle/local/airpay_request/
rsync -av --delete moodle-enhancement/local/airpay_org/    /var/www/moodle/local/airpay_org/
rsync -av --delete moodle-enhancement/mod/quizaccess_airpay_proctoring/ \
    /var/www/moodle/mod/quiz/accessrule/airpay_proctoring/

# Fix ownership and permissions:
sudo chown -R www-data:www-data /var/www/moodle/theme/airpayux \
    /var/www/moodle/local/airpay_cart /var/www/moodle/local/airpay_proctoring \
    /var/www/moodle/local/airpay_recompletion /var/www/moodle/local/airpay_request \
    /var/www/moodle/local/airpay_org \
    /var/www/moodle/mod/quiz/accessrule/airpay_proctoring
sudo find /var/www/moodle/theme/airpayux /var/www/moodle/local/airpay_* \
    -type d -exec chmod 0755 {} \;
sudo find /var/www/moodle/theme/airpayux /var/www/moodle/local/airpay_* \
    -type f -exec chmod 0644 {} \;
```

---

## 3. Database upgrade (T+5 min)

```bash
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
```

Expected output: per-plugin "Upgrading local_airpay_*… success" lines.
**Watch for any** `Error in xmldb_*` **stack trace — that's a hard stop.**
If upgrade errors out, jump to §7 Rollback immediately.

---

## 4. Cache purge + asset rebuild (T+8 min)

```bash
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
sudo -u www-data php /var/www/moodle/admin/cli/cfg.php --name=themedesignermode --set=0
sudo -u www-data php /var/www/moodle/admin/cli/build_theme_css.php --themes=airpayux
```

(Theme CSS pre-build avoids the 30-second compile on the first user's
first hit. Important for the post-cutover smoke window.)

---

## 5. Smoke test (T+10 min) — ALL must pass

Run the multi-role UAT against production:

```powershell
# From dev box:
cd "D:\Claude Local\airpay-ld-os\moodle-enhancement\audit\playwright"
$env:BASE = 'https://www.airpay.academy/moodle'
node uat_phase7_multirole.mjs
```

Expected: **84/85** pass minimum (Public User login may transient-flake;
that's the known weak case from 2026-05-12). If any other persona drops
to <14/14 — abort to §7.

Manual quick-checks while harness runs:

```
□ https://www.airpay.academy/                    → tenant-correct logo
□ Login as academy@airpay.co.in                  → site admin dashboard
□ Login as public.uat@airpay.test                → Public-tenant theme (purple)
□ Login as user.4156200@gmail.com (ZEEA)         → ZEEA-tenant content
□ /local/airpay_cart/index.php (Public user)     → loads, no errors
□ /local/airpay_proctoring/admin.php (admin)     → loads, lists sessions
□ /local/airpay_recompletion/index.php (admin)   → loads, lists rules
□ /local/airpay_request/index.php (any user)     → loads
□ /mod/quiz/view.php?id=PROCTORED_QUIZID         → SEB+proctor consent flows
□ Scheduled tasks: \local_airpay_recompletion\task\run_rules registered
                   \local_airpay_org\task\sync_cohorts registered
                   \local_airpay_proctoring\task\purge_old_recordings registered
```

---

## 6. Maintenance mode off (T+25 min)

```bash
sudo -u www-data php /var/www/moodle/admin/cli/maintenance.php --disable
```

Verify front-page is back. Send T+0 comms message:

> **Subject:** Airpay Academy 2.0 — release complete
>
> Hi all,
>
> Academy 2.0 is now live. New capabilities: course shopping for external
> tenants, robust proctoring on hiring quizzes, annual compliance reset
> automation, and a more responsive UI.
>
> If you spot anything that looks off in the next 24 hours, please reply
> to this thread or ping me directly. Full release notes:
> https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/releases
>
> Nitin

---

## 7. Rollback procedure (use only if §3, §4, §5 fail)

If DB upgrade errored:

```bash
# Restore the pre-cutover DB snapshot (you tested this in pre-flight):
sudo mysql -u root -p moodle < /var/backups/moodle-precutover-$(date +%F).sql

# Roll filesystem back via git:
cd /opt/deploy/Airpay-Academy2.0
git checkout $(cat .last_known_good_commit)   # set this BEFORE deploy
# Re-rsync from the rolled-back tree to /var/www/moodle/

sudo -u www-data php /var/www/moodle/admin/cli/maintenance.php --disable
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Send rollback comms:

> Apologies — we hit an issue during the upgrade and have rolled back.
> The platform is at the prior version. Full investigation tomorrow.

---

## 8. Post-cutover monitoring (T+0 to T+24h)

**Logs to tail** (open in 4 separate terminals on the production host):

```bash
# Apache error log:
sudo tail -F /var/log/apache2/error.log | grep -v 'AH00558'

# Moodle PHP error log:
sudo tail -F /var/www/moodle/error.log

# Moodle scheduled-tasks log:
sudo -u www-data tail -F /var/www/moodledata/temp/cron_output.log

# MySQL slow query log (threshold 2s):
sudo tail -F /var/log/mysql/slow.log
```

**Dashboards to watch:**
- AWS RDS performance insights (CPU, connections, slow queries)
- AWS S3 PutObject rate (proctoring recordings ramp-up)
- Apache MaxClients utilisation (should stay under 70%)

**Alarms to verify firing:**
- New Relic / CloudWatch alarm: HTTP 5xx rate > 1%
- New Relic / CloudWatch alarm: DB CPU > 80%
- Airpay payment gateway dashboard: callback failure rate
- Email failures from Moodle's `email_failure` event

**Day-2 check (T+24h):**
- [ ] Recompletion cron ran overnight, reset history shows expected resets
- [ ] Cohort sync ran, no errors in cron log
- [ ] No proctoring sessions stuck in `recording` status > 4h
- [ ] No cart orders stuck in `awaiting_payment` > 30m
- [ ] No "Cross-tenant" log entries in error log (audit finding indicator)

---

## 9. Communications templates

### T-24h heads-up

> **Subject:** Airpay Academy 2.0 — release window Saturday 22:00 IST
>
> Hi all,
>
> We're releasing Academy 2.0 this Saturday between 22:00 and 23:30 IST.
> The platform will be in maintenance mode for ~30 minutes. Please save
> your work before 21:30 and avoid scheduling training during this window.
>
> What's new:
> - External tenants can purchase courses via integrated shopping
> - Robust proctoring (identity verification + recording) on hiring quizzes
> - Annual compliance recompletion runs automatically
> - Faster mobile experience + dark mode polish
>
> Questions: reply here or DM me.

### T-1h "starting"

> Heads up — Academy maintenance window starts in 1 hour. Please log off
> and save anything in-flight. Back online by 23:30 IST.

### T+0 "complete" — see §6.

### Rollback comms — see §7.

---

## 10. Appendix: scheduled task verification

After cutover, confirm these tasks are scheduled and not disabled in
Site administration → Server → Scheduled tasks:

| Task | Schedule | Component |
|------|---------|-----------|
| `\local_airpay_recompletion\task\run_rules`     | 02:47 daily | local_airpay_recompletion |
| `\local_airpay_org\task\sync_cohorts`           | 02:47 daily | local_airpay_org |
| `\local_airpay_proctoring\task\purge_old_recordings` | 03:30 daily | local_airpay_proctoring |
| `\local_airpay_notifications\task\dispatcher`   | every 5 min | local_airpay_notifications |
| `\core\task\database_logger_cleanup_task`       | weekly | core |

If any task missing or disabled, run:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/scheduled_task.php \
    --execute=\\local_airpay_recompletion\\task\\run_rules
```

to one-shot a run and verify it doesn't crash.

---

**END OF RUNBOOK**

> Maintainer: Nitin Rajput. Update this runbook in-place after every cutover.
> Tag the commit `runbook-v{N+1}` for traceability.
