# `local_airpay_integrations` — Pre-Cutover Audit

**Audit date:** 2026-05-07
**Auditor:** Claude (Sonnet 4.6) under Nitin Rajput direction
**Plugin version on disk:** 2026040500 / 1.0.0-beta (BETA maturity)
**Verdict:** ⚠ **DO NOT ACTIVATE ON PRODUCTION** without the four blocker fixes in §4.

This plugin holds every external-system glue: KeKa HRMS sync, Microsoft Teams alerts,
Microsoft 365 SSO config, AI course recommender, FCM web push, and SENTIENTIA hooks.
It is currently the riskiest plugin in the deck because most features are wired
"on paper" but have never been exercised end-to-end against real services, and the
KeKa webhook receiver has a runtime bug that will throw a fatal SQL error the first
time it fires.

---

## 1. Plugin layout (what's actually on disk)

```
local/airpay_integrations/
├── version.php                 ✓ MATURITY_BETA, requires 2022041900
├── settings.php                ✓ admin tree, 8 sections, all OFF by default
├── lib.php                     ✓ 3 helper functions (recommendations / is_enabled / status)
├── webhook.php                 ⚠ KeKa JML receiver — see §4.1 (BLOCKER)
├── classes/
│   ├── keka_client.php         360 LOC — OAuth client + employee sync (camelCase)
│   ├── ai_recommender.php      218 LOC — 4 strategies (cat/skills/peers/popular)
│   ├── teams_notifier.php      140 LOC — adaptive-card sender via webhook
│   ├── web_push.php            122 LOC — FCM browser push + token store
│   └── task/
│       └── hrms_sync.php       191 LOC — older snake_case employee sync (DUPLICATE)
├── db/
│   └── tasks.php               ✓ registers hrms_sync, every 4h, disabled by default
├── lang/en/
│   └── local_airpay_integrations.php   ✓ string pack
└── templates/                  (empty — plugin has no rendered surfaces)
```

**Missing infrastructure that this plugin's own code assumes exists:**

| Expected file | Why it's needed | Current state |
|---|---|---|
| `db/install.xml` | webhook.php and web_push.php both write to plugin-owned tables | ❌ MISSING |
| `db/upgrade.php` | Ships nothing, so upgrade path is empty | ❌ MISSING |
| `db/access.php` | No capability gate on webhook or admin actions | ❌ MISSING |
| `db/services.php` | web_push token store would be called from JS | ❌ MISSING |
| `db/events.php` | Should fire on user creation/suspension to relay to KeKa | ❌ MISSING |

Net: this plugin is **not a Moodle plugin in the structural sense** — it's a bundle
of helper classes mounted under a settings page.

---

## 2. Per-integration status

Legend: ✓ functional · ⚠ wired but unverified · ✗ broken

### 2.1 KeKa HRMS — sync via OAuth + JML webhook ⚠

**Files:** `classes/keka_client.php` (360 LOC) · `classes/task/hrms_sync.php` (191 LOC) · `webhook.php` (62 LOC)

| Function | Path | Status | Notes |
|---|---|---|---|
| OAuth token exchange | `keka_client::authenticate` | ⚠ unverified | Reads `keka_base_url` / `keka_client_id` / `keka_client_secret` from config |
| Fetch employees (paged) | `keka_client::get_employees` | ⚠ unverified | Honours pagination, returns flat array |
| Fetch departments | `keka_client::get_departments` | ⚠ unverified | Used to map `department → costcenter.path` |
| Bulk sync employees | `keka_client::sync_employees` | ⚠ unverified | camelCase shape (`employeeNumber`, `firstName`, `workEmail`) |
| Single-employee sync | `keka_client::sync_single_employee` | ⚠ uses **legacy table** | See §3.1 — queries `{local_costcenter}` not `{local_airpay_org}` |
| Cron sync task | `task\hrms_sync::execute` | ⚠ **DUPLICATE LOGIC** | snake_case shape (`employee_id`, `first_name`) — see §3.2 |
| Webhook receiver | `webhook.php` | ✗ **CRASHES** | Inserts into table that has no `install.xml` — see §4.1 |
| Webhook dispatcher | `keka_client::handle_webhook` | ⚠ unverified | Switches on `joiner|leaver|mover` but never tested live |

**Run-status:** disabled task + endpoint that crashes if KeKa ever POSTs. Production will not break **today**, but the moment IT enables the scheduled task or KeKa is given the webhook URL, this fails.

### 2.2 Microsoft Teams notifications ✓

**File:** `classes/teams_notifier.php` (140 LOC)

| Function | Status | Notes |
|---|---|---|
| `send($title, $message, $facts)` | ✓ functional | Posts adaptive-card JSON to `teams_webhook_url` |
| `send_course_assignment($userid, $courseid)` | ✓ functional | Pulls user + course names, formats card |
| `send_compliance_alert($userid, $courseid, $duedate)` | ✓ functional | Used by airpay_compliance scheduled task |

**Run-status:** ready to enable as soon as a Teams incoming-webhook URL is pasted into Site Admin → Plugins → Local plugins → Airpay Integrations. Lowest-risk integration to switch on first.

### 2.3 AI course recommender ⚠

**File:** `classes/ai_recommender.php` (218 LOC)

| Strategy | Method | Status | Notes |
|---|---|---|---|
| By category history | `recommend_by_category` | ⚠ functional | Pure-Moodle fields (`{course}.category`) — should work everywhere |
| By skills | `recommend_by_skills` | ⚠ **BizLMS-only field** | Uses `{course}.open_skill` — column doesn't exist on stock Moodle |
| By peers (same dept) | `recommend_by_peers` | ⚠ **BizLMS-only field** | Uses `{user}.open_departmentid` — likely missing on Public/ZEEA |
| Popular | `get_popular_courses` | ✓ functional | Pure enrol/completion counts |
| Public entrypoint | `get_recommendations` | ⚠ defensive try/catch | Will swallow MissingColumn errors silently — recommender just returns fewer results |

**Run-status:** would not crash because each strategy is wrapped in `try {} catch {}`, but on any tenant whose `mdl_course` lacks `open_skill` or whose `mdl_user` lacks `open_departmentid`, two of four strategies silently produce zero rows → recommendations effectively degrade to "popular" only. That is an unannounced data-quality regression for non-Airpay tenants.

### 2.4 FCM browser push ⚠

**File:** `classes/web_push.php` (122 LOC)

| Function | Status | Notes |
|---|---|---|
| `send($userid, $title, $body, $url)` | ⚠ unverified | Posts to FCM legacy endpoint with `fcm_server_key` |
| `send_deadline_reminder($userid, $courseid, $duedate)` | ⚠ unverified | Wraps `send()` for compliance reminders |
| `store_token($userid, $token)` | ✗ **STORES TO MISSING TABLE** | Writes to `{local_airpay_integration_pushtokens}` — no install.xml |

**Run-status:** Token storage call will throw the same fatal as the webhook on first user enrolment. This blocks shipping any service-worker push code on the front end.

### 2.5 Microsoft 365 SSO ⚠

**File:** none — settings exist but no client class

The settings page reads `m365_enable / m365_tenant_id / m365_client_id / m365_client_secret`, but there is no PHP class that consumes those values. The actual SSO is presumably wired via the standard `auth_oauth2` plugin elsewhere in Moodle. This integration plugin contributes **only a config screen**, not behaviour.

### 2.6 SENTIENTIA pipeline hooks ✗

**File:** none

Settings page has `sentientia_enable / sentientia_api_key / sentientia_voice_id`, but no PHP hooks call SENTIENTIA. Today this is a dashboard-only flag with no consumer. The pipeline lives entirely in the Python agents under `content/sops/` → `content/scorm-output/`. Integration into Moodle will be a future workstream.

### 2.7 Gamification challenges ✗

**File:** none

Settings expose `gamification_enable / gamification_challenge_frequency`, but the actual gamification engine is in a separate stub plugin (`local_airpay_challenge`) which Nitin has just **upgraded from "low priority" to "PRIORITY"** in EOD 2026-05-07 instructions. Integrations plugin has no code path that touches challenges yet.

---

## 3. Code-quality issues (non-blocking, ship later)

### 3.1 Legacy `local_costcenter` reference in `keka_client.php:177`

```php
$costcenter = $DB->get_record_select('local_costcenter',
    $DB->sql_like('fullname', ':name'), ...);
```

Phase 0A of the BizLMS port replaces every `local_costcenter` reference with
`local_airpay_org`. This single line still queries the legacy table. On production
both names exist (BizLMS shim), so it works, but it's tech debt that makes the
plugin un-portable to a clean Moodle and breaks the rule "no `local_costcenter`
references in Airpay code".

**Fix:** swap `local_costcenter` → `local_airpay_org`. ~5 minutes.

### 3.2 Duplicate employee-sync paths

`keka_client::sync_single_employee` and `task\hrms_sync::sync_employee` both
create/update users from HRMS data, but they speak different shapes:

| Field | keka_client (camelCase) | hrms_sync (snake_case) |
|---|---|---|
| Employee number | `employeeNumber` | `employee_id` |
| First name | `firstName` | `first_name` |
| Status | `status` ∈ {inactive,terminated,exited,relieved} | `status` ∈ {inactive,terminated} |
| Default password | none (relies on `auth = manual` later) | `Airpay@<year>` |
| User creation API | direct `$DB->insert_record('user',…)` | `user_create_user()` (correct path) |

**Risk:** if both run, you can get duplicate users (one created by webhook,
one created later by cron) because they match on `email` vs `open_employeeid OR email`.

**Fix:** decide one canonical path. KeKa-webhook-first is the right call (keka_client),
but borrow `user_create_user()` from hrms_sync. Delete `task/hrms_sync.php`.
~30 minutes plus regression test of the cron task.

### 3.3 BizLMS-only fields in ai_recommender

`{course}.open_skill` and `{user}.open_departmentid` exist on Airpay tenant data
because BizLMS added them. They are not guaranteed on Public (id=77) and ZEEA
(id=177) tenants if those tenants ever onboarded users without the BizLMS profile
field migration.

**Fix:** wrap each strategy's SQL in a `field_exists()` check using the DB manager,
or accept the silent degradation but add an admin notice on the integration
settings page when the columns are absent. ~1 hour.

### 3.4 No capability check on `webhook.php`

The endpoint authenticates with a shared secret only. There's no Moodle
capability layer (`require_capability(...)`) — which is correct for an unauthenticated
webhook, but means any leak of `webhook_secret` lets an attacker create or suspend
users. Add IP-allow-listing for KeKa's published egress range. ~30 min.

### 3.5 No event handlers (db/events.php missing)

When Moodle suspends a user via the standard admin path, KeKa is **not** notified.
Sync is one-way (KeKa → Moodle), which is fine for the learner side, but means
HR can't see "Nitin disabled this account on 2026-05-07" without re-querying.

**Decision needed (Nitin):** is one-way sync acceptable for v1?
If yes — document and move on.
If no — add `db/events.php` listening on `\core\event\user_updated` and POST
back to KeKa. ~3 hours.

---

## 4. BLOCKER bugs (must fix before any production activation)

### 4.1 ✗ Missing table `local_airpay_integration_log` — webhook crashes

**File:** `webhook.php:41` and `:54`
**Symptom:** First time KeKa POSTs to `https://www.airpay.academy/local/airpay_integrations/webhook.php`, `$DB->insert_record('local_airpay_integration_log', ...)` throws `dml_table_missing_exception`. HTTP 500 returned to KeKa.
**Impact:** No JML events get processed. Employees onboard/leave without their accounts being created or suspended. Silent failure mode is worst-case for compliance.

**Fix:** ship `db/install.xml` defining the table. ~20 minutes.

### 4.2 ✗ Missing table `local_airpay_integration_pushtokens` — push token store crashes

**File:** `classes/web_push.php::store_token`
**Symptom:** First time the front-end registers an FCM service-worker token, the call to `$DB->record_exists('local_airpay_integration_pushtokens', ...)` throws the same fatal.
**Impact:** No push tokens get stored, so `send_deadline_reminder()` has no recipient list and silently sends zero pushes.

**Fix:** add this table to the same `db/install.xml`. ~10 minutes.

### 4.3 ✗ No `install.xml` means plugin upgrade path is undefined

Without `db/install.xml` the plugin can never declare its tables, so even the
manual workaround "create the table by hand on production" leaves Moodle's
upgrade tracker thinking the plugin owns no tables. Future schema changes
have nothing to upgrade *from*.

**Fix:** ship `db/install.xml` (one-time, required by §4.1).

### 4.4 ✗ Duplicate-sync risk if cron + webhook both fire

See §3.2. Even before any of the above is fixed, if an admin re-enables the
hrms_sync task while KeKa is configured to webhook, the same employee can
get inserted twice within a 4-hour window because the two paths match on
different keys.

**Fix:** disable `task/hrms_sync.php` permanently or delete it; rely on
webhook-first sync via `keka_client`. ~5 minutes (just remove the task) or
~30 minutes if we keep cron as a reconciliation backstop.

---

## 5. Recommended `db/install.xml` (drop-in)

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/airpay_integrations/db" VERSION="20260507"
       COMMENT="Airpay Integrations: KeKa webhook log + FCM push tokens">
  <TABLES>
    <TABLE NAME="local_airpay_integration_log"
           COMMENT="Webhook + sync event log for KeKa, Teams, M365, FCM">
      <FIELDS>
        <FIELD NAME="id"          TYPE="int"  LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="source"      TYPE="char" LENGTH="50"  NOTNULL="true"
               COMMENT="keka_webhook|hrms_cron|teams_alert|fcm_push|m365_sso"/>
        <FIELD NAME="event_type"  TYPE="char" LENGTH="100" NOTNULL="false"/>
        <FIELD NAME="payload"     TYPE="text" NOTNULL="false"
               COMMENT="Raw inbound JSON (truncated to 64 KB)"/>
        <FIELD NAME="status"      TYPE="char" LENGTH="20"  NOTNULL="true" DEFAULT="received"
               COMMENT="received|processed|failed|ignored"/>
        <FIELD NAME="errormsg"    TYPE="text" NOTNULL="false"/>
        <FIELD NAME="timecreated" TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS><KEY NAME="primary" TYPE="primary" FIELDS="id"/></KEYS>
      <INDEXES>
        <INDEX NAME="idx_source_status" UNIQUE="false" FIELDS="source, status"/>
        <INDEX NAME="idx_timecreated"   UNIQUE="false" FIELDS="timecreated"/>
      </INDEXES>
    </TABLE>

    <TABLE NAME="local_airpay_integration_pushtokens"
           COMMENT="FCM browser-push tokens, one per user-device pair">
      <FIELDS>
        <FIELD NAME="id"           TYPE="int"  LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="userid"       TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="token"        TYPE="char" LENGTH="255" NOTNULL="true"/>
        <FIELD NAME="useragent"    TYPE="char" LENGTH="255" NOTNULL="false"/>
        <FIELD NAME="timecreated"  TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="fk_user" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="idx_token" UNIQUE="true" FIELDS="token"
               COMMENT="One token can only be active for one user-device"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

After shipping this, bump `version.php` to `2026050700` and run upgrade.

---

## 6. Recommended production activation order

Each step is gated on the previous one passing in Airpay tenant before being
extended to Public/ZEEA.

```
┌──────────────────────────────────────────────────────────────────┐
│  Step 0  PRE-CUTOVER FIXES (mandatory)                ~ 4–6 hrs  │
│  ─────────────────────────────────────                            │
│  • Ship db/install.xml from §5                                    │
│  • Decide hrms_sync vs keka_client → delete the loser             │
│  • Migrate keka_client.php:177  local_costcenter → local_airpay_org│
│  • Bump version.php to 2026050700, run admin/cli/upgrade.php      │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│  Step 1  TEAMS ALERTS  (lowest risk)                  ~ 30 min   │
│  ─────────────────────                                            │
│  • Paste teams_webhook_url into Site Admin                        │
│  • Trigger compliance alert manually → confirm Teams card lands   │
│  • Roll out to Airpay tenant only                                 │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│  Step 2  KEKA HRMS (one-way: KeKa → Moodle)           ~ 1 day    │
│  ──────────────────────────────────────────                       │
│  • Configure OAuth credentials in Site Admin                      │
│  • Manually run keka_client::sync_employees() in dry-run          │
│  • Compare diff vs current Moodle users                           │
│  • Enable webhook → KeKa team posts test JML event                │
│  • Monitor local_airpay_integration_log for 48h                   │
│  • Then enable scheduled cron task as reconciliation backstop     │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│  Step 3  AI RECOMMENDER  (privacy review required)    ~ 2 days   │
│  ────────────────────────                                         │
│  • Confirm L&D legal sign-off on dept-based peer recs             │
│  • Add admin notice for Public/ZEEA tenants if open_skill missing │
│  • Roll out behind a per-tenant feature flag                      │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│  Step 4  FCM PUSH                                     ~ 1 day    │
│  ───────────────                                                  │
│  • Front-end service worker (separate workstream — not yet built) │
│  • Token-storage endpoint exposed via web service                 │
│  • First push: deadline reminder for Compliance courses           │
└──────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│  Step 5  SENTIENTIA  (later phase)                              │
│  ────────────────────                                             │
│  • Python pipeline runs locally today (Workstream B)              │
│  • In-Moodle integration is post-cutover                          │
└──────────────────────────────────────────────────────────────────┘
```

---

## 7. Open questions for Nitin

1. **Sync direction:** KeKa → Moodle only, or bidirectional? §3.5
2. **Cron + webhook:** keep cron as 4-hour reconciliation backstop, or webhook-only?
3. **AI recs on non-Airpay tenants:** silent degrade (current behaviour) or hard-disable until profile fields are migrated?
4. **Webhook IP allow-list:** does Procurement have KeKa's published egress CIDR? §3.4
5. **Push consent:** GDPR — is browser-push consent a separate dialog or rolled into existing T&C?

---

## 8. Estimated effort to bring `local_airpay_integrations` to production-ready

| Work item | Effort | Owner |
|---|---:|---|
| db/install.xml (§5) | 30 min | Claude |
| Delete or merge hrms_sync (§3.2) | 30 min | Claude |
| Migrate `local_costcenter` ref (§3.1) | 5 min | Claude |
| Add admin notice for missing AI fields (§3.3) | 1 hr | Claude |
| End-to-end KeKa OAuth dry-run | 4 hrs | Nitin + IT |
| Teams webhook live test | 30 min | Nitin |
| db/events.php for outbound sync (if needed) | 3 hrs | Claude |
| FCM service worker (separate plugin/theme work) | 1 day | Claude |
| **TOTAL pre-cutover (steps 0–1)** | **~ 6 hrs** | |
| **TOTAL to "fully operational" (through Step 4)** | **~ 5 days** | |

The 6-hour pre-cutover window is the minimum that lets us **leave the plugin enabled
without it crashing**. Steps 2–4 can be rolled out post-cutover behind feature flags.
