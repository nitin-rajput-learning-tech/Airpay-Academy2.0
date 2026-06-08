# ADR-025 - Component rename: `local_airpay_* → local_sentientia_*`

- **Status:** COMPLETE (2026-06-08) - all 35 plugins renamed; see OUTCOME below
- **Owner:** Nitin Rajput
- **Relates to:** ADR-018 (independence), ADR-024 (de-brand/absorb)
- **Decision context:** Nitin: "make all plugins as Sentientia" + "rename now,
  incrementally" + "keep airpay.academy" (in-place evolution; see migration ADR).

## Context

30 `local_airpay_*` plugins must become `local_sentientia_*`. A Moodle plugin's
component name is its identity, referenced by: directory, `version.php`
component, namespace, lang file names + `get_string` component arg,
`cache::make`, capability names, `$DB` table names, scheduled-task component,
message providers, `files.component`, external services, and ~1,233 cross-ref
files. **Naive rename = data loss**: Moodle sees the old component as
missing-from-disk and *drops its tables* on uninstall, and orphans
`role_capabilities` (permissions break for 2,888 live users).

Every rename migration eventually runs on **live airpay.academy** (in-place
model), so each is rehearsed on the local clone (restored prod data) first.

## Two rename classes

### Class A - 0 tables AND 0 capabilities (safe; PILOT PROVEN)
Plugins: `analytics` (done), `catalog`, `lifecycle`, `pages`.
Recipe (verified on `airpay_analytics → sentientia_analytics`, 2026-06-04):
1. `mv local/airpay_X local/sentientia_X` (single rename, no `rm`).
2. `mv` the per-locale lang files `local_airpay_X.php → local_sentientia_X.php`.
3. `sed 's/airpay_X/sentientia_X/g'` across the plugin + every external cross-ref
   file (component, namespace, `cache::make`, `get_string`, URLs, `@package`).
4. `php -l`; confirm 0 residual `airpay_X`.
5. `admin/cli/upgrade.php` (installs the new component) →
   `admin/cli/uninstall_plugins.php --plugins=local_airpay_X --run` (removes the
   orphaned old; **safe only because 0 tables**) → `purge_caches.php`.
6. Verify: new URL 200/303, old URL 404, site 200.

### Class B - has tables and/or capabilities (RELABEL-IN-PLACE; do NOT install/uninstall)
Plugins: the other 26 (e.g. `courses` 4t/10c, `users` 2t/7c, `cart` 5t/5c).
Letting Moodle install-fresh + uninstall-old would CREATE empty new tables and
DROP the populated old ones. Instead, **relabel the existing footprint in a
single migration CLI** so Moodle sees the new component as *already installed*:
1. `mv` dir + lang files + `sed` code (as Class A steps 1-4).
2. Migration CLI (transaction), for `local_airpay_X → local_sentientia_X`:
   - rename tables: `mdl_local_airpay_X_* → mdl_local_sentientia_X_*`
     (`$dbman->rename_table`).
   - `UPDATE {config_plugins} SET plugin = REPLACE(plugin,'airpay_X','sentientia_X')`
     (carries the installed version row → Moodle sees it installed, skips install).
   - capabilities: insert/rename `{capabilities}` rows; `UPDATE {role_capabilities}
     SET capability = REPLACE(...)`; same for context-level overrides. **This is
     the dangerous step - preserves permissions for all users.**
   - `UPDATE {task_scheduled} SET component=...`, `{task_adhoc}`,
     `{message_providers}`, `{files} SET component=...` (where component matches),
     `{external_functions}`/`{external_services}`, `{events_handlers}` if any.
   - `mv` the language-pack + purge.
   - **No** `upgrade.php` install of a fresh component; **no** `uninstall_plugins`.
3. Rehearse on the local clone: assert table row-counts preserved, a sample
   user's effective capabilities unchanged (before/after `has_capability`), site
   loads. Only then commit + (later) deploy in a maintenance window.

## Ordering (ascending risk)

1. Class A (4 plugins) - **`analytics` shipped**; then `catalog`, `pages`, `lifecycle`.
2. Class B, tables-but-no-caps (`assistant`, `gamification`, `whatsapp`, `core`, `integrations`, `reports`).
3. Class B, caps-light (`exams`, `manager`, `privacy`, `recompletion`, `notifications`, `compliance_report`, `evaluation`).
4. Class B, caps-heavy (`courses` 10, `users` 7, `cart`/`proctoring` 5, `learningpath`/`programs`/`classroom`/`org`/`roles`/`request` 4-6). Each its own rehearsed migration + maintenance-window deploy.

## Consequences
- **+** All plugins first-party `local_sentientia_*`; product de-branded at the component level.
- **-** ~30 migrations; Class B touches live capabilities/tables → each gated on a clone rehearsal + maintenance window.
- **Risk** mitigated: relabel-in-place (never drop), transaction per plugin, before/after capability assertions, local-clone rehearsal of every migration.

## Pilot record
`local_airpay_analytics → local_sentientia_analytics` (Class A): dir+5 lang
files+namespace+4 cache strings+page URLs rewritten; 3 external cross-refs
(`airpay_org` DSR CLIs ×2, theme sidebar nav) updated; upgrade installed new,
uninstall removed old (0 rows), new page 303 / old 404 / site 200. Clean.

### Class B pilot proven - 2026-06-04
`relabel_plugin.php` shipped + proven on `airpay_integrations -> sentientia_integrations`:
table `local_airpay_integration_log` renamed (data preserved), 28 config_plugins rows
relabeled; `upgrade.php` ran with NO install / NO drop, old component 0 rows, site 200.
The relabel CLI is the reusable Class-B workhorse (add `--migrate-caps` for caps plugins).

Then shipped via the `tools/rename_plugin.sh` driver: `assistant`, `gamification`,
`whatsapp` (tables-no-caps; data preserved - gamification points_log 56/badges 10/
user_badges 51/streaks 24, whatsapp audit 5/dlt_templates 15).

### `--migrate-caps` path proven - 2026-06-04 (reports = first caps plugin)
Dry-run FIRST caught a real bug: capability NAMES are `local/airpay_X:cap` (SLASH),
but the CLI matched/replaced on the underscore component `local_airpay_X` -> caps
would NOT migrate, orphaning role_capabilities (silent permission loss). Fixed: match
by the `{capabilities}.component` column, rename via the `airpay_X -> sentientia_X`
substring. No caps plugin had been renamed before this, so no harm. `reports` then
applied on the clone with a before/after assertion: all **7 role_capabilities rows
preserved** exactly (view roles 1/3/9, manage 1/9, export 1/9 - same roleid/permission/
context, repointed to `local/sentientia_reports:*`); 3 caps moved, old residue 0|0|0|0,
table `local_sentientia_reports` 5 rows preserved, 3 external_functions remapped, site 200.

Class B done: integrations, assistant, gamification, whatsapp, reports (9/30 total
incl. 4 Class A). **Remaining: ~20.** `airpay_core` is BLOCKED (target `sentientia_core`
already exists - needs a distinct name decision). `compliance_report` is HELD (owner WIP).

## OUTCOME - COMPLETE (2026-06-08)

The full de-brand shipped. **35 plugins renamed** `airpay_* -> sentientia_*`:
- **30** `local_airpay_* -> local_sentientia_*` - incl. `airpay_core -> sentientia_platform`
  (the suite core: feature flags, branding, audit, cron-health, PII masking), kept distinct
  from the existing `local_sentientia_core` substrate/seam plugin per Nitin's decision.
  `compliance_report` hold was lifted (its WIP was already committed @8ab957446).
- **4** `block_airpay_* -> block_sentientia_*` (cert_health, compliance, cron_health, trainer).
- **1** `quizaccess_airpay_proctoring -> quizaccess_sentientia_proctoring`.

Verified end-to-end: `admin/cli/upgrade.php` completes successfully; **0** config_plugins,
**0** capabilities, **0** `mdl_local_airpay_*` tables with airpay components remain; all
role-capability assignments + table data preserved (user_type 2879 rows, feature_flags 15,
gamification points 56, etc.). Site 200 throughout. origin/production @ d5cd77c8e.

**Kept by decision (Nitin, 2026-06-08):** `paygw_airpay` (+ `mdl_paygw_airpay`/`_errorlog`)
- the external Airpay PAYMENT GATEWAY product (like paygw_paypal), not company branding;
and theme `airpayux` - internal design-system codename.

**Scope-gaps the driver originally missed (local/+theme/ only) and how each was caught/fixed:**
brand-neutral tables (privacy `local_privacy_*`); install.xml-stale tables added via
upgrade.php (classroom `_waitlist`); abbreviated tables (proctor `_proctor_*`, learningpath
`lp_users`); sibling-name collision (`quizaccess_airpay_proctoring` shielded via placeholder
sed during the local `proctoring` rename); `enrol/` (sentientiasub dependency - was blocking
upgrade.php) and `my/` (switchrole namespace) core-mods; 6 non-standard-named tables
(user-type/profile + locations). Driver hardened: DB-based table discovery, qualified-vs-bare
sed split, scope broadened to blocks/mod/payment, same-name rename guard.

**Non-blocking follow-ups:** (a) a few code refs to *non-existent* airpay-named tables
(email_log, lp_users, customers, ratings, marketplace) - refs only, no DB tables exist, so
harmless; de-brand opportunistically. (b) junk dir `public/local$name/` in the LOCAL CLONE
ONLY (not in git, not loaded by Moodle, stale airpay copies from an early unexpanded-`$name`
script) - safe to delete with [CONFIRM].
The caps-heavy tier (`courses` 10c, `users` 7c, `cart`/`proctoring` 5c, etc.) is each its
own rehearsed migration + maintenance-window deploy per the ordering above.
