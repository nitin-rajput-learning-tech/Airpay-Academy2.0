-- Airpay Academy 2.0 — Post-Deploy SQL Configuration
-- Run AFTER theme and plugin files are deployed and DB upgrades complete.
-- Target: Production Moodle database

-- 1. Activate airpayux theme
UPDATE mdl_config SET value = 'airpayux' WHERE name = 'theme';

-- 2. Enable global search (simpledb backend)
INSERT INTO mdl_config (name, value) VALUES ('searchengine', 'simpledb')
  ON DUPLICATE KEY UPDATE value = 'simpledb';
INSERT INTO mdl_config (name, value) VALUES ('enableglobalsearch', '1')
  ON DUPLICATE KEY UPDATE value = '1';

-- 3. Disable performance debug (prevents "Reactive instances" text)
UPDATE mdl_config SET value = '0' WHERE name = 'perfdebug';

-- 4. Fix catalog permissions — allow admin and manager roles to view catalog
-- (BizLMS had these set to PROHIBIT which blocks all admin users)
UPDATE mdl_role_capabilities SET permission = 1
  WHERE roleid = 9 AND capability = 'local/search:viewcatalog';
UPDATE mdl_role_capabilities SET permission = 1
  WHERE roleid = 1 AND capability = 'local/search:viewcatalog';

-- 5. Verify settings
SELECT name, value FROM mdl_config
  WHERE name IN ('theme', 'searchengine', 'enableglobalsearch', 'perfdebug', 'defaulthomepage');

-- 6. REMINDER: Purge all caches after running this script
-- php admin/cli/purge_caches.php
