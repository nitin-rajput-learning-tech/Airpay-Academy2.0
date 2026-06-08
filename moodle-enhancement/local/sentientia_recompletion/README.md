# local_sentientia_recompletion

Annual compliance reset engine. POSH, AML/KYC, Data Privacy training
expires after N days — this plugin resets the completion state and
notifies the user so they can re-complete.

| Field | Value |
|---|---|
| Component | `local_sentientia_recompletion` |
| Version | `2026051201` (1.0.1) |
| Requires | Moodle 4.5+ (`2024042200`) |
| Maturity | `MATURITY_STABLE` |
| Depends on | `local_airpay_org` |

## What it does

1. Admin defines a **rule** per course (or all-courses-with-completion):
   - `period_days` (e.g. 365 for annual compliance)
   - `trigger_type`: `completion` (count from last completion), `enrolment` (count from enrol date), or `fixed` (single calendar date for all users)
   - `costcenterid` (which tenant the rule applies to; 0 = all tenants)
   - `reset_grades` (bool — also zero the grade rows)
   - `reset_attempts` (bool — also delete quiz attempts)
2. **Daily cron** (02:47 by default) walks every enabled rule:
   - Find users past expiry → reset their completion atomically (DB transaction).
   - Find users within `pre_notify_days` of expiry → send a "due soon" message (24h dedupe via cache).
3. **Bulk reset UI** for ad-hoc admin action.
4. **History page** showing every reset event with reason + dryrun flag.

## Capabilities

| Capability | Granted to | Purpose |
|---|---|---|
| `local/sentientia_recompletion:view` | manager | view history + receive messages |
| `local/sentientia_recompletion:manage` | manager | create/edit/delete rules |
| `local/sentientia_recompletion:reset` | manager | run bulk-reset UI |

## Tables (2)

| Table | Purpose |
|---|---|
| `local_sentientia_recompletion_rules` | Rule definitions (one per course or wildcard) |
| `local_sentientia_recompletion_history` | Audit log of every reset event (cron + bulk) |

## Message providers

| Provider | When |
|---|---|
| `recompletion_due_soon` | `pre_notify_days` before expiry |
| `recompletion_reset` | When a reset actually fires |

## Settings (Site admin → Plugins → Local plugins → Airpay Recompletion)

| Setting | Purpose |
|---|---|
| `pre_notify_days` | Default 30 |
| `max_batch` | Cap on resets per cron pass (default 500) |

## Scheduled tasks

| Task | Schedule | Purpose |
|---|---|---|
| `\local_sentientia_recompletion\task\run_rules` | 02:47 daily | Evaluate every enabled rule |

## Phase 8.1 security hardening

- **B6** (CVSS 7.5): Rule's `costcenterid` now drives a tenant filter on the candidate-users SQL. A rule for tenant /1 no longer resets users in /77 + /177.
- **B8** (CVSS 6.5): `LIMIT $max_batch` and `LIMIT $perpage OFFSET ...` patterns replaced with proper `get_records_sql($sql, $args, $limitfrom, $limitnum)` calls.

## How to verify after install

```powershell
# 1. CLI smoke:
php "C:/xampp/htdocs/moodle5/public/local/sentientia_recompletion/cli/smoke_recompletion.php"
# Expected: 13/13 cases pass

# 2. Manual scheduled-task one-shot (in addition to the daily cron):
php "C:/xampp/htdocs/moodle5/admin/cli/scheduled_task.php" \
    --execute=\\local_sentientia_recompletion\\task\\run_rules
```

## Privacy / GDPR

`classes/privacy/provider.php`:
- History rows store `userid` + `courseid` + `timecreated` + flags.
- DSR `delete_data_for_user` redacts `userid → null` (the row is kept for
  the compliance audit — legal hold — but the user reference is dropped).
- DSR `delete_data_for_users` bulk variant.

## Idempotency

The engine writes a row to `local_sentientia_recompletion_history` for every
reset event. The next cron pass skips users with a history row in the
last 24h, so re-running the cron doesn't double-reset.
