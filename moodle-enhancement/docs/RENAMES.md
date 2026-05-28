# Plugin Renames — airpay_* → sentientia_*

**Status:** Living document. Owner: Nitin Rajput. Last updated: 2026-05-28
**Audit ref:** `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md`
F-012, F-083, F-099 (Bucket E2)

---

## Why this exists

Per `CLAUDE.md` mission (Day 0, 2026-05-20), we are building **Sentientia
LMS** — a white-label product — with **Airpay Academy** as customer-zero.
Every plugin we ship today is named `local_airpay_*` (legacy) but will
eventually carry the product name `local_sentientia_*`.

Phase 1 of the stabilization audit found:

- 30 plugins named `local_airpay_*` (legacy, Airpay-branded)
- 7 plugins named `local_sentientia_*` (new, product-branded)
- 0 documented migration path between the two naming schemes

This doc is the migration path.

---

## Rules

### Rule 1 — Never rename a STABLE plugin to break production

A Moodle plugin rename is **non-trivial**:

- `mdl_config_plugins` rows keyed on plugin name need migration
- Cron task records keyed on plugin name need migration
- File area records (`mdl_files.component`) need migration
- Capability rows (`mdl_capabilities.component`) need migration
- User preferences keyed on plugin name need migration

Per [Moodle docs](https://docs.moodle.org/dev/Plugin_renaming) the only
safe rename is via a **frankenstyle migration upgrade step** — and even
that doesn't migrate user_preferences keyed off the old plugin name.

For our 17 STABLE plugins (see `MATURITY-TRIAGE-2026-05-28.md`), renaming
breaks production. They stay `local_airpay_*` forever. Internally they
are the Sentientia LMS feature set; externally the namespace is frozen.

### Rule 2 — New plugins ship under the new name from Day 1

Every plugin created after Day 0 (2026-05-20) is `local_sentientia_*`.
This is already the practice — see `sentientia_aiquiz`, `sentientia_live`,
`sentientia_leaderboard`, etc.

### Rule 3 — Alias-table for white-label customers

When a customer other than Airpay deploys Sentientia LMS, they don't
care that the namespace is `local_airpay_*`. They care that the **user-
visible label** says "Sentientia" (or whatever we white-label to). To
support this, every legacy plugin defines its user-visible name via
`lang/en/local_<name>.php` `pluginname` string — never hardcoded.

Customer-facing branding is also overridable via the per-customer
branding table (`local_airpay_core::get_customer_branding()`, see
ADR-008).

### Rule 4 — Internal-only aliases for code clarity

For developer ergonomics, code can refer to the new sentientia name in
documentation and comments — e.g. "the sentientia-live plugin" — even
when the actual Moodle component is `local_sentientia_live` (already
product-named) or `local_airpay_classroom` (legacy-named but conceptually
part of Sentientia's classroom feature). This doc is the authoritative
map between conceptual name and physical Moodle component.

---

## Alias table — conceptual name ↔ physical component

| Conceptual name (Sentientia LMS docs / sales) | Physical Moodle component | Maturity | Rename plan |
|----------------------------------------------|--------------------------|----------|-------------|
| sentientia-core | `local_airpay_core` | STABLE | NEVER rename — too many dependents |
| sentientia-users | `local_airpay_users` | STABLE | NEVER rename — has 2,871 prod rows |
| sentientia-courses | `local_airpay_courses` | STABLE | NEVER rename — 411 courses live |
| sentientia-classroom | `local_airpay_classroom` | STABLE | NEVER rename |
| sentientia-programs | `local_airpay_programs` | STABLE | NEVER rename |
| sentientia-exams | `local_airpay_exams` | STABLE | NEVER rename |
| sentientia-evaluation | `local_airpay_evaluation` | STABLE | NEVER rename |
| sentientia-skills | `local_airpay_skills` | STABLE | NEVER rename |
| sentientia-emails | `local_airpay_emails` | STABLE | NEVER rename |
| sentientia-recompletion | `local_airpay_recompletion` | STABLE | NEVER rename |
| sentientia-compliance | `local_airpay_compliance_report` | STABLE | NEVER rename |
| sentientia-request | `local_airpay_request` | STABLE | NEVER rename |
| sentientia-pages | `local_airpay_pages` | STABLE | NEVER rename |
| sentientia-cart | `local_airpay_cart` | STABLE | NEVER rename |
| sentientia-org | `local_airpay_org` | STABLE | NEVER rename |
| sentientia-search | `local_airpay_search` | STABLE | NEVER rename |
| sentientia-pwa | `local_sentientia_pwa` | STABLE | Already renamed |
| sentientia-analytics | `local_airpay_analytics` | BETA | Deferred — assess at v3.0 product release |
| sentientia-catalog | `local_airpay_catalog` | BETA | Deferred — large refactor; C4 ships first |
| sentientia-gamification | `local_airpay_gamification` | BETA | Deferred |
| sentientia-integrations | `local_airpay_integrations` | BETA | Deferred — split into per-integration plugins eventually |
| sentientia-lifecycle | `local_airpay_lifecycle` | BETA | Deferred |
| sentientia-roles | `local_airpay_roles` | BETA | Deferred |
| sentientia-calendar | `local_sentientia_calendar` | BETA | Already renamed |
| sentientia-assistant | `local_airpay_assistant` | ALPHA | If revived → rename. Otherwise archive. |
| sentientia-challenge | `local_airpay_challenge` | ALPHA | If revived → rename. Otherwise archive. |
| sentientia-whatsapp | `local_airpay_whatsapp` | ALPHA | Rename when Workstream F content notifications ship |
| sentientia-aiquiz | `local_sentientia_aiquiz` | ALPHA | Already renamed |
| sentientia-leaderboard | `local_sentientia_leaderboard` | ALPHA | Already renamed |
| sentientia-live | `local_sentientia_live` | ALPHA | Already renamed |
| sentientia-m365 | `local_sentientia_m365` | ALPHA | Already renamed |
| sentientia-recommendations | `local_sentientia_recommendations` | ALPHA | Already renamed |
| sentientia-translate | `local_sentientia_translate` | ALPHA | Already renamed |

---

## Block, theme, and other component renames

| Component | Plan |
|-----------|------|
| `theme/airpayux` | NEVER rename. Per-customer branding (logo, colours, fonts) is overridable via `local_airpay_core::get_customer_branding()`; the theme name is internal. |
| `block/airpay_*` | Same rule as `local_airpay_*` plugins. STABLE blocks stay; new blocks ship as `block_sentientia_*`. |
| `tool/airpay_*` | We don't have any. New admin tools ship as `tool_sentientia_*`. |

---

## Migration path — if a rename ever becomes necessary

Should a future requirement force a STABLE plugin rename (e.g. legal
challenge to "airpay" trademark in white-label deployments), the
procedure is:

1. Write an ADR documenting the trigger.
2. Write `db/upgrade.php` step that walks every Moodle table containing
   the old frankenstyle and rewrites it.
3. Stage the rename on a fresh DB dump first.
4. Run on production during a maintenance window.
5. Update this doc with the new physical component name.

Steps 2–4 are estimated at 4 engineer-weeks per plugin. **This is not
done casually.**

---

## Cross-reference

- Plugin maturity status: `MATURITY-TRIAGE-2026-05-28.md`
- Customer branding architecture: `docs/adr/ADR-008-customer-brand-table.md`
- Trademark cleanup history: `docs/adr/ADR-001-trademark-cleanup.md`
- Workspace ↔ deployed sync gate: `tools/check_workspace_sync.sh`
- State-card freshness gate: `tools/check_state_card_freshness.sh`
