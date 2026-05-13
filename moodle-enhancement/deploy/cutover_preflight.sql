-- Cutover pre-flight SQL — Airpay Academy 2.0 production deploy
--
-- Run this against the production database BEFORE the cutover begins.
-- It is read-only — every query is a SELECT. Captures the state of
-- the platform against the audit-driven checks that must pass before
-- the deploy starts.
--
-- Owner: Head of L&D
-- Referenced from: PHASE-8-DEPLOYMENT-RUNBOOK.md § 0
-- Last updated: 2026-05-12

-- ============================================================
-- §1 — Audit finding N4: manageprices cap context migration
-- ============================================================
--
-- Phase 8.1 B9 moved local/airpay_cart:manageprices from
-- CONTEXT_SYSTEM (level 10) to CONTEXT_COURSE (level 50).
-- Existing role-capabilities rows at CONTEXT_SYSTEM are silently
-- inert after the migration. Surface them here so ops can re-grant.

SELECT 'manageprices stale grants' AS check_name,
       rc.id,
       r.shortname        AS role_shortname,
       r.name             AS role_name,
       c.contextlevel,
       c.instanceid,
       rc.permission
  FROM mdl_role_capabilities rc
  JOIN mdl_role r    ON r.id  = rc.roleid
  JOIN mdl_context c ON c.id  = rc.contextid
 WHERE rc.capability = 'local/airpay_cart:manageprices'
   AND c.contextlevel = 10;   -- CONTEXT_SYSTEM

-- Expected: zero rows in a clean post-migration state. Each row
-- returned needs a re-grant at the relevant CONTEXT_COURSECAT (40)
-- via /admin/roles/manage.php → Role assignments.


-- ============================================================
-- §2 — Tenant data integrity: open_path validation
-- ============================================================
--
-- Every active user must have a non-empty open_path starting with a
-- valid tenant root. Phase 8.1 B6 + B2 + B1 all assume this invariant.

SELECT 'users with invalid open_path' AS check_name,
       u.id, u.username, u.firstname, u.lastname, u.open_path
  FROM mdl_user u
 WHERE u.deleted   = 0
   AND u.suspended = 0
   AND u.id        > 2      -- skip guest + admin
   AND (
       u.open_path IS NULL
    OR u.open_path = ''
    OR u.open_path NOT LIKE '/%'
    OR (
        u.open_path NOT LIKE '/1%'
    AND u.open_path NOT LIKE '/77%'
    AND u.open_path NOT LIKE '/177%'
       )
   );

-- Expected: zero rows. Each row returned is a user whose tenant
-- scoping will fail at every airpay_core::tenant call. Fix by
-- assigning open_path before cutover.


-- ============================================================
-- §3 — Cart-tenant settings sanity
-- ============================================================

SELECT 'cart enabled_tenants config' AS check_name,
       value
  FROM mdl_config_plugins
 WHERE plugin = 'local_airpay_cart'
   AND name   = 'enabled_tenants';

-- Expected: '77,177' (or whatever the agreed tenant CSV is).
-- An empty or missing value enables cart for ALL tenants including
-- Airpay internal — almost certainly not intended.


-- ============================================================
-- §4 — Callback IP allow-list configured (Phase 8.1 B11)
-- ============================================================

SELECT 'cart callback iplist' AS check_name,
       COALESCE(value, '(unset — open to internet)') AS iplist
  FROM mdl_config_plugins
 WHERE plugin = 'local_airpay_cart'
   AND name   = 'airpay_callback_iplist';

-- Expected: non-empty CIDR list provided by the Airpay payment-gateway
-- team. Unset is technically allowed (legacy behaviour) but means the
-- callback URL is open to any internet caller. Confirm with gateway
-- team before cutover.


-- ============================================================
-- §5 — Proctoring AWS credentials configured
-- ============================================================

SELECT 'proctoring AWS configured' AS check_name,
       (
         SELECT COUNT(*)
           FROM mdl_config_plugins
          WHERE plugin = 'local_airpay_proctoring'
            AND name IN ('aws_region', 'aws_access_key', 'aws_secret_key',
                         'aws_s3_bucket')
            AND value IS NOT NULL
            AND value <> ''
       ) AS configured_count;

-- Expected: 4. Less means proctoring is not production-ready.
-- (Mock provider can be used for non-production environments.)


-- ============================================================
-- §6 — Recompletion rules have explicit tenant assignment (B6)
-- ============================================================

SELECT 'recompletion rules without tenant' AS check_name,
       id, name, courseid, costcenterid, enabled
  FROM mdl_local_airpay_recompletion_rules
 WHERE enabled      = 1
   AND costcenterid = 0;

-- Expected: zero rows OR explicitly approved cross-tenant rules. A
-- cross-tenant rule (costcenterid=0) would re-set completions across
-- ALL tenants. The B6 fix lets us scope; this check confirms scoping
-- is actually used.


-- ============================================================
-- §7 — Scheduled tasks enabled
-- ============================================================

SELECT 'scheduled task status' AS check_name,
       component, classname, disabled,
       nextruntime,
       FROM_UNIXTIME(nextruntime) AS next_run_at
  FROM mdl_task_scheduled
 WHERE classname IN (
       '\\local_airpay_recompletion\\task\\run_rules',
       '\\local_airpay_org\\task\\sync_cohorts',
       '\\local_airpay_proctoring\\task\\purge_old_recordings',
       '\\local_airpay_request\\task\\escalate_overdue',
       '\\local_airpay_request\\task\\auto_expire',
       '\\local_airpay_notifications\\task\\dispatcher',
       '\\local_airpay_compliance_report\\task\\refresh_aggregates'
       )
 ORDER BY component, classname;

-- Expected: 7+ rows, all with disabled=0. Any disabled task is an
-- operational gap that must be re-enabled before cutover.


-- ============================================================
-- §8 — User population by tenant (sanity)
-- ============================================================

SELECT 'user counts by tenant' AS check_name,
       SUBSTRING_INDEX(SUBSTRING(open_path FROM 2), '/', 1) AS tenant_root,
       COUNT(*) AS active_users
  FROM mdl_user
 WHERE deleted   = 0
   AND suspended = 0
   AND id        > 2
   AND open_path IS NOT NULL
   AND open_path <> ''
 GROUP BY tenant_root
 ORDER BY active_users DESC;

-- Expected approximate distribution:
--   tenant_root=1   (Airpay)  ≈ 2188 users
--   tenant_root=77  (Public)  ≈ 676  users
--   tenant_root=177 (ZEEA)    ≈ 6    users
-- Deviations warrant investigation before cutover.


-- ============================================================
-- §9 — Plugin version alignment
-- ============================================================

SELECT 'airpay plugin versions' AS check_name,
       name AS plugin,
       value AS version
  FROM mdl_config_plugins
 WHERE plugin = 'core_plugin'
   AND name LIKE 'local_airpay_%/version'
 ORDER BY name;

-- Expected: all airpay_* plugins at the version codes documented in
-- their respective version.php files. Drift indicates an incomplete
-- upgrade run.


-- ============================================================
-- §10 — End of pre-flight
-- ============================================================
--
-- If every check above returned the expected result, the cutover may
-- proceed. Save the output of this script to a timestamped file in
-- the cutover-evidence S3 bucket per the deployment runbook § 0.
