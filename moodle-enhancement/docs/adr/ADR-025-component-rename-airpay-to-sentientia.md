# ADR-025 - Component rename: `local_airpay_* → local_sentientia_*`

- **Status:** Accepted (2026-06-04) - pilot proven
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
Remaining Class B: 25 (next tables-no-caps: assistant, gamification, whatsapp, core).
