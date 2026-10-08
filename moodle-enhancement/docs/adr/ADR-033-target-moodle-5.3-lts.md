# ADR-033: Target Moodle 5.3 LTS instead of 5.2

**Status:** Accepted on Nitin's instruction (2026-10-07), **subject to the compatibility gate** in "Decision" item 8. Until the gate passes, 5.2 remains the fallback target. The fixes are dual-target (they work on both 5.2 and 5.3), so the fallback stays buildable.
**Date:** 2026-10-07 (decision); 2026-10-08 (recorded)
**Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
**Implementer:** Claude
**Amends:**
- ADR-028: the "5.2 now" platform decision. The rest of ADR-028 stands.
- ADR-011: the target of the 5.2 staging plan. Its phase discipline stands.
**Evidence:** `docs/upgrade/MOODLE-5.3-COMPATIBILITY-2026-10-07.md` (static compatibility report, 12 blockers). The 5.3 source is at `D:/Claude Local/moodle53/moodle`, version `2026100500.00`, SHA-256 verified.
**Related:** ADR-031 (tenant scope), ADR-032 (BizLMS data import, which runs inside the cutover window).

---

## Context

- **Where Sentientia runs today.** Sentientia runs on Moodle 5.1.3+ locally and on 5.2+ on UAT. The cutover plan (`docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md`) restores the live 4.1.2 database and upgrades it 4.1.2 to 4.5.x (LTS) to 5.2. A one-hop upgrade is impossible because 5.2 requires an upgrade source of 4.4 or later.
- **5.3 LTS is out.** It was released on 2026-10-05.
- **Support windows** (moodledev.io/general/releases). A cutover in late 2026 or early 2027 on 5.2 would leave under 12 months of security fixes and force another major upgrade soon after go-live. 5.3 adds two years of security support.

  | Release | General support until | Security support until |
  |---|---|---|
  | 5.2 | 2027-04-19 | 2027-10-04 |
  | 5.3 LTS | 2027-10-04 | 2029-10-01 |
  | 4.5 LTS | ended | 2027-10-04 |

- **The 5.3 environment is the 5.2 environment** for this deployment:
  - PHP 8.3 or later, MySQL 8.4 or later, sodium, 64-bit, max_input_vars of 5000 or more, and an upgrade source of 4.4 or later. All of these are unchanged.
  - Only the MariaDB floor (10.11 to 11.4) and the PostgreSQL floor (16 to 17) moved. Production uses neither.
  - The IT requests already raised for 5.2 (PHP 8.3, RDS MySQL 8.4) cover 5.3 unchanged.
- **The static compatibility scan found 12 blockers:**
  - Code and packaging: 5 fatal and 6 broken behaviour.
  - Environment: 1 (no 5.3 runtime on the local box).

  Every code fix is small and can be written dual-target. Six of the 11 code blockers already break the 5.2 UAT today, so they must be fixed whichever version is chosen.
- **No core-file edits needed.** On 5.3 no existing core file needs editing:
  - The `setuplib.php` `ini_get_bool` guard and its config.php polyfill can retire, because 5.3 removed the shutdown-path call that needed them. One runtime check must confirm this.
  - The `my/` overlays become added files rather than overrides.
  - Three vendor patches to tool_certificate remain. They patch a vendor plugin, not core.
- **Layout and theme changes.** 5.3 moves Bootstrap 5.3.8, Font Awesome, React and the Moodle design-system to `<root>/lib/bundles`, outside `public/`. It also renders more core UI through React and ESM via `r.php`. This changes packaging and adds theme shims for the standalone Bootstrap 4.6 theme. It does not need a theme redesign.

## Decision

1. **Platform target.** Moodle 5.3 LTS is the platform target for UAT, Stage B (the ninja-sandbox live-backup rehearsal) and the airpay.academy cutover. 5.2 is no longer a cutover target.
2. **Migration path.** The path is **4.1.2 to 4.5.x (LTS) to 5.3**: two hops, a clean code directory per hop (never extract over the previous tree), and `admin/cli/upgrade.php --non-interactive` after each hop.
   - **PHP 8.3 throughout.** It is the only version both 4.5 (8.1 to 8.3) and 5.3 (8.3 or later) accept, so the cutover window needs no PHP switch.
   - **MySQL 8.4** as the database.
   - **BizLMS import.** The ADR-032 BizLMS import runs on 5.3 after hop 2, exactly as it was planned for 5.2.
3. **IT infrastructure unchanged.**
   - **Existing asks only:** PHP 8.3 (sodium, intl, soap, gd, zip, mbstring, curl, opcache; 64-bit; `max_input_vars >= 5000`); RDS MySQL 8.4 with a `caching_sha2_password` application user; Apache with mod_rewrite to `r.php`.
   - **No new IT request.** If a MariaDB host is ever used it must be 11.4 or later.
4. **No edits to existing core files on 5.3.**
   - The setuplib guard and the config.php polyfill are retired for 5.3, gated on the php-cgi render smoke.
   - The `my/dashboard.php` and `my/switchrole.php` overlays ship as added files.
   - The tool_certificate patches stay as recorded vendor patches until upstream releases carry them: `reset_caches(): void`, the image guard, and the new duration typo fix.
   - Every record in `docs/core-mods/` gets a 5.3 addendum.
5. **Dual-target code until UAT moves.** Every 5.3 fix works on both 5.2 and 5.3 via `method_exists` / `class_exists` feature detection or theme template overrides, because the 5.2 UAT keeps running until the gate passes. 5.1-only fallbacks may be dropped when the local 5.1 tree is retired.
6. **Packaging.**
   - **New recipe:** a parameterised `build-standalone.sh --target 5.3` replaces the 5.2-only script. It:
     - builds from git, not from the 5.1 XAMPP webroot;
     - ships `<root>/lib` with `public/`;
     - excludes `config.php` using tree-name-aware patterns and verifies the result;
     - excludes `theme/airpayux`.
   - **Retired for 5.3:** `package-sentientia.ps1`, because it packages `public/` only.
7. **UAT.**
   - **Side by side first:** a separate 5.3 instance is built beside the running 5.2 UAT, with its own dirroot, docroot, database schema, dataroot and vhost. 5.3 is validated there first.
   - **Replace in place:** UAT 5.2 is replaced only after the gate passes, by moving a copy of the UAT 5.2 database through the 5.3 upgrade.
   - **What stays:** UAT test accounts, persona passes and the Stage A checklist are re-run on 5.3. The existing 5.2 evidence stays as history.
8. **The gate.** All of the following must hold before 5.3 is the target in any rehearsal, customer-facing document or cutover plan:
   - (a) **Blockers fixed.** Every blocker in the fix plan of the compatibility report is fixed and merged in both trees (TOP and `moodle-enhancement/`), and the cross-tree drift gate is green.
   - (b) **Fresh install.** The 5.3 package installs fresh on PHP 8.3 and MySQL 8.4 with these results:
     - the environment page has no failures;
     - `install_database.php` completes;
     - `check_database_schema.php` shows only the known `open_*` extras;
     - the render smoke is clean: zero browser console errors, and `r.php`, `theme/font.php`, `lib/javascript.php` and `theme/styles.php` return 200 under the production SAPI;
     - persona passes are done, with visual evidence in `docs/visual-evidence/`.
   - (c) **Upgrade rehearsals.**
     - The 4.5.10 production-copy database (the `bizlms_april` rehearsal output) upgrades to 5.3 cleanly, the ADR-032 import and parity checks pass, and the duration is recorded for the maintenance window.
     - A copy of the UAT 5.2 database also upgrades to 5.3 cleanly.
   - (d) **PHPUnit on 5.3.** The Sentientia suites and the vendor plugin suites in the package are green, with zero unexpected-debugging failures. This requires the deprecation fixes for `user_*()` and the legacy `\external_*` classes.
   - (e) **Write paths.** Each write path runs once on 5.3. The 5.2 `reset_caches` fatal was invisible to static checks, so this is required. The paths are:
     - certificate issue, manual and automatic
     - Airpay checkout through to the callback
     - Refund
     - Enrol user
     - HRMS import
     - SCIM PATCH
     - the KeKa sync against a mock
     - signup
     - course delete through the web service
     - an email with a tenant template override
     - every CSV export

   If an item fails and cannot be fixed, the decision falls back to 5.2 (the ADR-011/028 path). The fallback needs no rework because the fixes are dual-target.

## Consequences

**Positive**
- **Longer support:** security support until 2029-10-01 instead of 2027-10-04, and one fewer major upgrade in the first year after go-live.
- **Same infrastructure:** no new IT request, and the cutover hop count is unchanged.
- **Fixes reach UAT anyway:** six defects that already break the 5.2 UAT today are fixed as part of this work (email overrides, Airpay checkout, activity dates, Refund and Enrol dialogs, the certificate image guard, learnerscript modals).
- **Less core-mod debt:** no edits to existing core files are needed (the setuplib guard retires).
- **Packaging gets safer:** the build moves from webroot copies to git, which closes the twin-drift and config.php-leak traps.

**Negative**
- **Repeated validation:** UAT validation done on 5.2 must be repeated on 5.3 (Stage A install, persona passes, workflow matrix).
- **Local dev runtime:** local development needs a new database (a portable MySQL 8.4 is preferred for parity, or MariaDB 11.4) and a PHP 8.3 or later web runtime. XAMPP's MariaDB 10.11 and mod_php 8.2 cannot host 5.3. On this box there is no local admin and no Docker daemon, so local options need Nitin's approval to download.
- **First-release risk:** 5.3.0 is a .0 release. Rule: develop and rehearse on 5.3.0, cut over on the latest 5.3.x point release. Point releases come every two months; the next is expected in December 2026.
- **Bootstrap gap grows:** core increasingly assumes Bootstrap 5.3, design-system tokens and React. The vendored Bootstrap 4.6 theme needs utility shims now and will need more at each upgrade.
- **Vendor plugin risk:** tool_certificate 4.5.7 declares `supported = [400, 405]`, and learnerscript is a 2019 plugin. Both remain 4.5-era vendor code until replaced or upgraded.

**Neutral**
- **What does not change:** hop 1 (4.1.2 to 4.5.x), the ADR-032 import design, the tenant model (ADR-031) and the rollout gate. The gate order stays: foolproof workflow testing, then the ninja-sandbox live-backup rehearsal, then replacement with the data intact.
- **Live deploy:** remains Nitin-gated.

## Alternatives considered

- **A. Stay on 5.2 (ADR-011/028 as written).**
  - Rejected. Security support ends 2027-10-04, the same day as 4.5 LTS.
  - It needs the same infrastructure as 5.3.
  - It would force a 5.3 or 5.4 upgrade within about a year of go-live, with a second full validation cycle.
  - Six of the 11 code blockers must be fixed on 5.2 anyway.
- **B. Cut over to 4.5 LTS (stop after hop 1).**
  - Rejected. It also loses security support on 2027-10-04.
  - The theme and plugins are now built and tested against 5.x APIs.
  - It moves backwards and still needs a later major upgrade.
- **C. Upgrade 4.1.2 straight to 5.3.**
  - Impossible. 5.3 requires an upgrade source of 4.4 or later (`<MOODLE version="5.3" requires="4.4">`).
- **D. Wait for 5.3.1 before starting any 5.3 work.**
  - Rejected as the starting rule: it wastes the window in which the 12 blockers can be fixed and the gate run.
  - Adopted as the cutover rule: cut over on the latest 5.3.x.
- **E. Re-base theme_sentientia on core Bootstrap 5.3.8 or the Boost design-system now.**
  - Rejected for this ADR. It is a design decision with brand impact (the C-suite-approved prototypes) and needs its own ADR.
  - This ADR uses shims: `.fs-*`, `.text-bg-*`, `ms-/me-`, `fw-`, and a theme `core/editswitch` override.
- **F. Re-apply both setuplib core-mod halves on 5.3 "to be safe".**
  - Rejected as the default. 5.3 removed the cause, and the polyfill alone is fatal.
  - Kept as the documented fallback if the php-cgi render smoke shows a class-1 500.

## Implementation actions

1. Land the fix plan in order (compatibility report, `fix_plan`). Blockers first, each in both trees, with state cards updated.
2. Add `tools/packaging/build-standalone.sh --target 5.3`, plus the 5.3 vhost and config templates without the polyfill. The 5.2 recipe stays for the UAT 5.2 instance until it is retired.
3. Provision the runtime per the runtime plan: local MySQL 8.4 portable or Docker (needs Nitin's approval), and the UAT side-by-side 5.3 instance (needs IT for the database schema and dumps; SSH cannot take database backups on UAT).
4. Run the gate (Decision item 8) and record the evidence under `docs/upgrade/` and `docs/visual-evidence/<date>/`.
5. Update these documents, each in its own commit:
   - `docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md`: hop 2 targets 5.3; the MariaDB floor is 11.4; PHP 8.3 is the bridge version.
   - `docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md`
   - `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md`
   - `docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md`
   - `tools/uat/stage-a-install.sh`: parameterise `DOCROOT`, which is hard-wired to `moodle5.2`.
   - `PROJECT-STATE.md`
   - CLAUDE.md §2: add the target row; local 5.1 stays as the dev box.
   - `docs/adr/README.md` index row.
   - `docs/core-mods/` addenda and the README index.
   - Every customer-facing "Moodle 5.2" mention in the product guide. Wording rule: Sentientia is not live.
6. After the gate passes, replace the UAT 5.2 instance by moving a copy of the UAT database through the 5.3 upgrade. Then retire the 5.2 packaging recipe and the 5.2 polyfill and guard.

## References

- `docs/upgrade/MOODLE-5.3-COMPATIBILITY-2026-10-07.md`: findings, core-mods verdicts, theme impact, packaging recipe, runtime unknowns.
- ADR-011 (5.2 staging plan), ADR-028 (roadmap, "5.2 now"), ADR-032 (BizLMS import), ADR-031 (tenant authority).
- `docs/core-mods/2026-06-11-setuplib-ini-get-bool-guard.md`, `2026-06-19-my-overlays-5.2.md`, `2026-09-03-tool-certificate-5.2-reset-caches.md`, `2026-05-23-certificate-image-imageinfo-guard.md`.
- Moodle 5.3 source: `public/admin/environment.xml:5316`, `UPGRADING.md`.
- Moodle release and support dates: https://moodledev.io/general/releases

## Open questions

1. **Local 5.3 runtime.** Options:
   - a portable MySQL 8.4 Windows ZIP, run as a console process (no admin needed, about 300 MB download);
   - Docker Desktop with moodle-docker (PHP 8.3 + MySQL 8.4), if Nitin can start the daemon;
   - MariaDB 11.4 portable.

   Each needs Nitin's approval to download.
2. **UAT side-by-side instance.** Does IT create the extra database schema and take the UAT 5.2 database dump and restore? SSH cannot take database backups on UAT.
3. **learnerscript, reportdashboard and reporttiles.** Port their modal code to `core/modal`, or remove them from the package if Sentientia reports replace them?
4. **tool_certificate.** Move to a 5.x-compatible upstream release before cutover (and drop the three vendor patches), or keep 4.5.7 with patches?
5. **Bootstrap 5.3 re-base.** Should theme_sentientia re-base on Bootstrap 5.3.8 and the design-system? This would be a future ADR.
6. **Cutover point release.** Confirm the cutover point release once 5.3.1 ships, expected in December 2026.
