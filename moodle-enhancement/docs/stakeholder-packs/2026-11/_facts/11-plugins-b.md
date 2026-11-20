# Fact base 11: plugin catalogue, part B (local plugins m..z, all other plugin types, vendored plugins, core version and core modifications)

Confidential: Airpay Payment Services internal. Not for external distribution.

Source tree: `moodle-enhancement/` (canonical) at repository state 2026-11-20. Each plugin is counted once; the byte-identical twin under the repository root (`local/<p>/`, `blocks/<p>/` and so on) is not counted again.

Counting method (file inspection, nothing executed): "Classes" = `classes/**/*.php`; "Tables" = `<TABLE>` entries in `db/install.xml` (tables created only by `db/upgrade.php` are not counted); "Caps" = entries in `db/access.php`; "WS" = entries in `db/services.php` (approximate, line pattern); "Flags" = keys in `db/feature_flags.php`; "Templates" = `templates/**/*.mustache`. A 0 or blank means the file is absent or nothing matched. "Hi" y = `lang/hi` file present (parity enforced by a pre-commit check).

Status placement: git commit `af6e65c0e` is what runs on UAT. No change since that commit = **Available (UAT)**. Later commits or uncommitted changes (checked with `git diff af6e65c0e`) = "Available (UAT) at base; later changes **Built (verified on development)**". Flag-gated items also carry "behind a feature flag, off by default". Purpose detail per plugin: `moodle-enhancement/state-cards/<component>-state.md`.

## 1. Local plugins (`moodle-enhancement/local/`), names m..z: 23 plugins

Component prefix `local_sentientia_`; sorted by the part after `sentientia_`. Part A covers a..l.

| # | Plugin (source path) | Display name (lang/en) | Version | Release | Maturity | Classes | Tables | Caps | WS | Flags | Templates | Hi | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `local/sentientia_m365` | Microsoft 365 | 2026052801 | 0.2.0-alpha | ALPHA | 3 | 1 | 2 | 0 | 1 | 0 | y | Built (behind a feature flag, off by default): `sentientia_m365_enabled` |
| 2 | `local/sentientia_manager` | Manager Dashboard | 2026111801 | 1.3.10 | STABLE | 14 | 2 | 3 | 8 | 0 | 5 | y | Available (UAT) |
| 3 | `local/sentientia_notifications` | Smart Notifications | 2026111600 | 1.5.2 | STABLE | 14 | 3 | 3 | 6 | 2 | 5 | y | Available (UAT); flags `sentientia.notifications.smart_rules.enabled`, `.digest_deadlines.enabled` off by default |
| 4 | `local/sentientia_org` | Organization Engine | 2026111800 | 1.6.2 (version.php also keeps 1.5.0 as history) | STABLE | 21 | 2 | 6 | 3 | 0 | 2 | y | Available (UAT) |
| 5 | `local/sentientia_pages` | Pages (certificate and shared pages) | 2026111801 | 1.2.2 | STABLE | 2 | 0 | 0 | 0 | 1 | 2 | y | Available (UAT) at base; `certificates.php` changed since: Built (verified on development); flag `sentientia.certificate.tenant_scope.enabled` off by default |
| 6 | `local/sentientia_platform` | Core (shared infrastructure): feature flags, customer and tenant model, branding | 2026111300 | 1.13.1 | STABLE | 68 | 11 | 1 | 0 | 17 | 1 | y | Available (UAT); hosts 17 flags: 16 cross-cutting switches (`ai.*` 3, `engagement.*` 6, `commerce.*` 3, `ux.*` 3, `identity.*` 1; 6 of them default ON) plus `sentientia.customer_level_flags.enabled` (default OFF) |
| 7 | `local/sentientia_privacy` | Privacy (DPDP) | 2026111300 | 1.1.0 | STABLE | 7 | 2 | 2 | 0 | 2 | 2 | y | Available (UAT); flags `sentientia.privacy.erasure_completeness.enabled`, `.completion_gap.enabled` off by default |
| 8 | `local/sentientia_proctoring` | Proctoring and secure assessment | 2026112003 | 1.2.1 | STABLE | 28 | 5 | 5 | 14 | 5 | 4 | y | Plugin Available (UAT) at base (56 files); B1 foundation and B2 secure assessment mode (36 files changed since) are Built (behind a feature flag, off by default): `sentientia.proctoring.enabled`, `sentientia.assessment.secure_mode.enabled`, `sentientia.assessment.seb_preset.enabled`, `sentientia.proctoring.recording.enabled`, `sentientia.assessment.candidates.enabled` |
| 9 | `local/sentientia_programs` | Certification Programs | 2026110300 | 1.9.2 | STABLE | 37 | 7 | 6 | 12 | 2 | 4 | y | Available (UAT); flags `sentientia.programs.learner.enabled`, `.history.enabled` |
| 10 | `local/sentientia_pwa` | PWA (installable app shell, web push) | 2026092900 | 0.6.1-alpha | ALPHA | 14 | 2 | 2 | 3 | 5 | 3 | y | Built (behind a feature flag, off by default), code in the UAT commit; 5 flags (`sentientia.pwa.enabled`, `.push.enabled`, `.push.reminders`, `.push.overdue`, `.install.enabled`) off by default |
| 11 | `local/sentientia_ratings` | Ratings | 2026100701 | 1.2.1 | STABLE | 14 | 3 | 1 | 1 | 3 | 1 | y | Available (UAT); flags `sentientia.ratings.widget`, `.reviews`, `.reactions` |
| 12 | `local/sentientia_recommendations` | AI Course Recommendations | 2026092500 | 0.1.2-alpha | ALPHA | 5 | 1 | 3 | 0 | 3 | 0 | y | Built (behind a feature flag, off by default), code in the UAT commit; flags `sentientia.recommendations.enabled`, `.live_api`, `.auto_cron` off by default |
| 13 | `local/sentientia_recompletion` | Recompletion (recertification cycles) | 2026100801 | 1.2.2 | STABLE | 29 | 3 | 3 | 0 | 2 | 3 | y | Available (UAT); flags `sentientia.recompletion.evidence_view`, `.run_rules` |
| 14 | `local/sentientia_reports` | Reports (training transcript, login days) | 2026100101 | 1.3.0 | STABLE | 6 | 1 | 3 | 3 | 2 | 2 | y | Available (UAT); flags `sentientia.reports.training_transcript`, `.login_days` |
| 15 | `local/sentientia_request` | Course Requests | 2026110300 | 1.6.4 | STABLE | 24 | 1 | 4 | 6 | 2 | 3 | y | Available (UAT); flags `sentientia.request.imported_history`, `.catalog_button` |
| 16 | `local/sentientia_roles` | Role Management | 2026100701 | 1.3.2-beta | BETA | 16 | 1 | 5 | 8 | 1 | 4 | y | Available (UAT); flag `sentientia.roles.org_assignments` |
| 17 | `local/sentientia_skills` | Skills Matrix | 2026111900 | 1.7.2 | STABLE | 31 | 9 | 4 | 14 | 2 | 6 | y | Available (UAT); flags `sentientia.dashboard.skillsrecs.enabled`, `sentientia.skills.heldskills.enabled` |
| 18 | `local/sentientia_skillsai` | Skills Intelligence (AI) | 2026111901 | 0.1.5-alpha | ALPHA | 8 | 5 | 5 | 0 | 4 | 0 | y | Built (behind a feature flag, off by default), code in the UAT commit at base (36 files); gap-feed rebuild task changed since: Built (verified on development); flags `sentientia.skillsai.enabled`, `.live_api`, `.gap_engine`, `.impact_map` off by default |
| 19 | `local/sentientia_talent` | Talent Mobility | 2026061600 | 1.0.0-beta | BETA | 4 | 5 | 8 | 0 | 2 | 2 | y | Built (behind a feature flag, off by default), code in the UAT commit; flags `sentientia.talent.enabled`, `.opportunities` |
| 20 | `local/sentientia_translate` | AI Content Translation | 2026092500 | 0.2.2-alpha | ALPHA | 6 | 2 | 3 | 0 | 2 | 0 | y | Built (behind a feature flag, off by default), code in the UAT commit; flags `sentientia.translate.enabled`, `.live_api` off by default |
| 21 | `local/sentientia_users` | User Engine (profiles, position labels, imported history, external trainers) | 2026112000 | 2.10.0 | STABLE | 49 | 7 | 8 | 6 | 4 | 7 | y | Available (UAT) at base (116 files); 21 modified and 7 new files since (uncommitted 20 Nov), including external-trainer accounts and `db/install.xml`: Built (behind a feature flag, off by default) `sentientia.users.external_trainer.enabled`; other flags `sentientia.users.legacy_transcript`, `.position_labels`, `.imported_sync_history`; language packs kn, mr, sw modified in the working tree |
| 22 | `local/sentientia_whatsapp` | WhatsApp and SMS | 2026092500 | 0.4.1-alpha | ALPHA | 10 | 4 | 0 | 0 | 1 | 2 | y | Built (behind a feature flag, off by default), code in the UAT commit: master switch `engagement.whatsapp.enabled` (platform registry) and own flag `sentientia_whatsapp_content_notifications`, both default OFF; `classes/notification_bridge.php` changed since: Built (verified on development) |
| 23 | `local/sentientia_xapi` | xAPI / LRS | 2026090302 | 1.0.2 | ALPHA | 10 | 4 | 3 | 0 | 5 | 0 | y | Built (behind a feature flag, off by default), code in the UAT commit; flags `sentientia.xapi.enabled`, `.emit_login`, `.emit_module_view`, `.lrs_endpoint_enabled`, `.cmi5_enabled` off by default |

Maturity totals (m..z): STABLE 14, ALPHA 7 (m365, pwa, recommendations, skillsai, translate, whatsapp, xapi), BETA 2 (roles, talent). Flag keys in m..z local plugins: 68 across 21 plugins (62 default OFF, 6 default ON, all 6 in `local_sentientia_platform`).

## 2. Other plugin types built by us (10 plugins)

| Type | Plugin (source path) | Display name | Version | Release | Maturity | Classes | Tables | Caps | WS | Flags | Hi | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| block | `blocks/sentientia_cert_health` | Certificate Health | 2026100800 | 1.0.1 | STABLE | 1 | 0 | 2 | 0 | 0 | y | Available (UAT) |
| block | `blocks/sentientia_compliance` | Compliance Dashboard | 2026111800 | 1.0.2-beta | BETA | 2 | 0 | 2 | 0 | 0 | y | Available (UAT) |
| block | `blocks/sentientia_cron_health` | Cron Health | 2026080400 | 1.0.0 | STABLE | 1 | 0 | 2 | 0 | 0 | y | Available (UAT) |
| block | `blocks/sentientia_leaderboard` | Leaderboard | 2026111800 | 0.1.2-alpha | ALPHA | 1 | 0 | 2 | 0 | 0 | y | Available (UAT) |
| block | `blocks/sentientia_recommendations` | Recommended for you (AI) | 2026080400 | 0.1.0-alpha | ALPHA | 1 | 0 | 2 | 0 | 0 | y | Available (UAT) |
| block | `blocks/sentientia_trainer` | Trainer Dashboard | 2026080400 | 1.0.0 | STABLE | 1 | 0 | 3 | 0 | 0 | y | Available (UAT) |
| enrol | `enrol/sentientiasub` | Subscription enrolment | 2026060300 | 0.2.0-alpha | ALPHA | 2 | 1 | 4 | 0 | 1 (`sentientia.subscriptions.enabled`) | y | Built (behind a feature flag, off by default), code in the UAT commit |
| quiz access rule | `mod/quiz/accessrule/sentientia_proctoring` (`quizaccess_sentientia_proctoring`) | Proctoring (quiz access rule) | 2026112002 | 1.3.1 | STABLE | 1 | 1 | 0 | 0 | 0 | y | Available (UAT) at base; 10 files changed since: Built (behind a feature flag, off by default) together with the proctoring flags in section 1 |
| payment gateway | `payment/gateway/airpay` (`paygw_airpay`) | Airpay payment gateway | 2026100800 | 1.0.2 | STABLE | 5 | 3 | 0 | 1 | 0 | n (no Hindi file) | Available (UAT) |
| theme | `theme/sentientia` (repository root only, `theme_sentientia`) | Sentientia theme (standalone, no parent theme) | 2026111902 | 1.0.73-beta | BETA | not counted | 0 | 0 | 0 | 0 | languages en, hi, kn, mr, sw | Available (UAT) at base; 4 theme files changed since (committed): Built (verified on development) |

Theme facts (`theme/sentientia/`): 40 entries in `templates/`, 219 SCSS files under `scss/`, language folders en, hi, kn, mr, sw. `version.php` history runs 1.0.58 to 1.0.73 (1.0.73: Programs link in the L&D admin sidebar; 1.0.72: certificate verification page not indexed by search engines; 1.0.71: certificate verification page and modal form label fixes). Design tokens: `scss/moodle/_tokens.scss`.

Plugin types searched with none of ours: `auth/` (only Moodle core methods), `report/` (only core reports), `mod/` (only core activities plus our quiz access rule), `admin/tool/` (only core tools plus the vendored `certificate`). Sources: directory listings of those folders and of `moodle-enhancement/{blocks,enrol,mod,payment}`. No `admin/tool/sentientia_*` plugin exists.

## 3. Vendored third-party plugins

| Plugin | Source path | Version in tree | Upstream / supported range | Our patches (records in `moodle-enhancement/docs/core-mods/`) | Status |
|---|---|---|---|---|---|
| `tool_certificate` (certificate issuing and verification) | `admin/tool/certificate/` | release 5.0.12, build 2026111000 | `supported = [500, 503]` (Moodle 5.0 to 5.3); re-vendored from 4.5.7 on 2026-11-20 | 3 patches re-applied on 5.0.12: image-element guard (`2026-05-23-certificate-image-imageinfo-guard.md`); verification result shown on arrival (`2026-11-19-tool-certificate-verify-on-arrival.md`); verification result escapes holder and certificate names (`2026-11-19-tool-certificate-verify-result-escaping.md`). 2 reverted because upstream 5.0.12 fixed them: `reset_caches(): void` (`2026-09-03-tool-certificate-5.2-reset-caches.md`) and the `defaultunit` spelling (`2026-10-08-tool-certificate-duration-defaultunit.md`). Import record: `2026-11-20-tool-certificate-5.0.12-revendor.md`. Additive Hindi pack, staged, 14 keys to top up before activation: `2026-05-29-tool_certificate-hi-pack.md` | The re-vendor record states UAT runs 5.0.12; the repository copy re-vendored on 20 Nov is Built (verified on development) |
| `block_learnerscript` (custom report builder) | `blocks/learnerscript/` | 2019052008.6 (vendor decimal series; our bump from .5) | Vendor declares requires 2019052000 (Moodle 3.7); a later upstream 2019052009 or higher still upgrades cleanly | 1 patch: modal factory removed in Moodle 5.2 (MDL-79182); `amd/src/{ajax,ajaxforms,helper,newgroup}.js`, `js/design.js` and the matching `amd/build` files ported to `core/modal` (`2026-10-08-learnerscript-modal-factory-port.md`); required on 5.2 and 5.3. Patched-file copies `learnerscript_lib_PATCHED.php` and `reportdashboard_dashboard_PATCHED.php` are kept in `moodle-enhancement/blocks/` | Available (UAT) |
| `block_reportdashboard` | `blocks/reportdashboard/` | 2019052001.3 | Learnerscript family | No separate record; `reportdashboard_dashboard_PATCHED.php` copy exists | Available (UAT) |
| `block_reporttiles` | `blocks/reporttiles/` | 2019052001 | Learnerscript family | None recorded | Available (UAT) |
| Legacy blocks carried over from the previous production platform: `block_userdashboard` (2016052304), `block_trainerdashboard` (2019042102.02), `block_masterinfo` (2022122100.02); PROJECT-STATE also names `achievements`, `my_event_calendar`, `quick_navigation` as "kept vendor blocks" | `blocks/<name>/` (top-level tree only, not in `moodle-enhancement/blocks/`) | as stated | Origin: legacy BizLMS platform; upstream version and licence to be confirmed (owner: Head of Learning & Development) | No core-mod record. They referenced the retired `local_costcenter` plugin at 50+ sites; handled without editing vendor files by a compatibility alias in `local_sentientia_org` (`after_config` hook, `compat\bizlms_lib`) | Available (UAT) |
| Reference archives: `local_recompletion` 2025041400 (Moodle 4.5), `tool_certificate` 2025031804 (4.5), `tool_tcpdffonts` 2025120101 (5.1) | `moodle-enhancement/plugins/*.zip` | per file name | Upstream snapshots; not the deployed code (our `local_sentientia_recompletion` 1.2.2 is a derived fork) | None | Reference only |

Vendor-patch rules (ADR-041, `moodle-enhancement/docs/adr/ADR-041-patching-vendored-plugins.md`): patch only when nothing outside the plugin can reach the defect; bug and compatibility fixes only, no feature flag; hunks opened with `SENTIENTIA-CORE-MOD` and closed with `END SENTIENTIA-CORE-MOD`; each has a record and a test; a vendor release import carries a `Core-Mods-Upstream-Sync:` line. Gate: pre-commit CHECK 6 and CI `core-mods.yml` (`tools/ci/check-core-mods.sh`); per ADR-041 the gate covers `admin/` but not `blocks/`.

## 4. Moodle core versions

| Environment | Moodle version | Source |
|---|---|---|
| UAT (academy2.airpay.ninja) | 5.3 LTS. Upgraded 2026-10-21 from 5.2 with package `v4.3.2-sentientia-5.3-package-2026-10-21` (repository commit `4f847d434`); current UAT code is commit `af6e65c0e`. Exact Moodle build number on UAT: to be confirmed by IT/DevOps | `PROJECT-STATE.md`, section "2026-10-21 - UAT upgraded to Moodle 5.3 LTS"; `docs/operations/uat-upgrade-2026-10-21/README.md` |
| Local 5.3 runtime | 5.3 (Build 20261005), `$version` 2026100500.00; PHP 8.4, MariaDB 11.4 | `public/version.php` of the local 5.3 tree |
| Local XAMPP | 5.1.3+ (Build 20260415), `$version` 2025100603.13; PHP 8.2.12, MariaDB 10.11.16 | `public/version.php` of the XAMPP tree; project environment table |
| Target | 5.3 LTS (ADR-033); needs PHP 8.3+ and MySQL 8.4 on the new server (infrastructure request open) | ADR-033 |
| Production (legacy BizLMS, not Sentientia) | 4.1.2 | project environment table |

## 5. Core modifications ledger (`moodle-enhancement/docs/core-mods/`: 13 records plus README)

Moodle core is not in git for 5.3. Versioned core patches (ADR-044): `moodle-enhancement/core-patches/MANIFEST` pins the SHA-256 of each upstream file; `tools/check-core-patches.sh` has `check`, `apply` and `verify-tree`; hunks may only add lines. The top-level `lib/` copy (a 4.5.10 snapshot) serves 5.1 and 5.2; `moodle-enhancement/core-patches/5.3/public/...` serves 5.3 (UAT and the package).

| Record | File(s) changed | Reason | Kind | Status on 5.3 |
|---|---|---|---|---|
| `2026-11-20-form-js-dependency-guard.md` | `lib/form/form.js` and `core-patches/5.3/public/lib/form/form.js` | `M.form.initFormDependencies` returns early for a form no longer in the document, which stops the modal-form TypeError (ADR-044) | Core patch; the only edit to an existing Moodle core file on 5.3 | Required on 5.1, 5.2, 5.3; Built (verified on development) |
| `2026-06-19-my-overlays-5.2.md` | `my/dashboard.php`, `my/switchrole.php` | Additive overlay: dashboard shim redirecting to `/my/`, and the role-switch page, that old bookmarks, installed-PWA start URLs and e-mail links still use | New files | Additive overlay on 5.3, both required |
| `2026-06-04-open-substrate-ownership.md` | Database: 37 `open_*` columns on `user`, 18 on `course` (raw ALTER) | Ownership record for the substrate carried over from the legacy platform | Schema note | No collision; runtime check in the upgrade rehearsal |
| `2026-06-11-setuplib-ini-get-bool-guard.md` | `lib/setuplib.php` plus a `config.php` polyfill | `function_exists` guard against an `ini_get_bool()` redeclare fatal (5.2 only) | Core patch (5.2) | Retired on 5.3; do not apply |
| `2026-05-20-moodle-to-sentientia-rename.md` | none (rename map of user-visible "Moodle" strings) | Branding | Note | Version independent |
| `2026-05-23-certificate-image-imageinfo-guard.md` | `admin/tool/certificate/` image element | Fatal inside an element class | Vendor patch | Required; re-applied on 5.0.12 |
| `2026-05-29-tool_certificate-hi-pack.md` | `admin/tool/certificate/lang/hi/` (new file) | Hindi language file | Additive | Version independent |
| `2026-09-03-tool-certificate-5.2-reset-caches.md` | `issue_handler::reset_caches(): void` | Signature must match core | Vendor patch | Reverted 2026-11-20 (upstream 5.0.12 declares it) |
| `2026-10-08-tool-certificate-duration-defaultunit.md` | `admin/tool/certificate/` (`defaultunit` spelling) | Option-key typo throws on 5.3 | Vendor patch | Reverted 2026-11-20 (upstream fixed) |
| `2026-10-08-learnerscript-modal-factory-port.md` | `blocks/learnerscript/amd/src/{ajax,ajaxforms,helper,newgroup}.js`, `js/design.js`, matching `amd/build` | Core removed the modal factory in 5.2 | Vendor patch | Required on 5.2 and 5.3 |
| `2026-11-19-tool-certificate-verify-on-arrival.md` | `admin/tool/certificate/index.php` | Verification links show the result on arrival; the form posts to the bare URL | Vendor patch (ADR-041) | Re-applied on 5.0.12 |
| `2026-11-19-tool-certificate-verify-result-escaping.md` | `admin/tool/certificate/classes/output/verify_certificate_result.php` | Escape the holder name and certificate name in the verification result | Vendor patch (ADR-041) | Re-applied on 5.0.12 |
| `2026-11-20-tool-certificate-5.0.12-revendor.md` | `admin/tool/certificate/` (whole directory) | Import 4.5.7 to 5.0.12 (2026111000); carries the `Core-Mods-Upstream-Sync` line | Vendor import | `supported = [500, 503]` |

Result: on Moodle 5.3 exactly one existing core file is edited (`public/lib/form/form.js`). Every other record is a vendor-plugin patch, an additive overlay file or a note. Related ADRs: ADR-001 (lifts the no-core-edit rule, creates the ledger), ADR-033 (5.3 target; decision 4: no edits to existing core files), ADR-041, ADR-044. The form.js patch has PHPUnit tripwires in `local/sentientia_courses/tests/modal_form_guard_test.php`.

## 6. Key numbers

- m..z local plugins: 23 (STABLE 14, ALPHA 7, BETA 2); 68 feature-flag keys across 21 of them (6 default ON, all in `local_sentientia_platform`).
- Other plugin types built by us: 10 (6 blocks, 1 enrol, 1 quiz access rule, 1 payment gateway, 1 theme); none of ours under auth, report, mod (apart from the access rule) or admin/tool.
- Changed since the UAT commit (Built, verified on development): proctoring (plugin and access rule, flags off), users (external trainers, flag off), pages, skillsai, whatsapp, theme, and the tool_certificate 5.0.12 re-vendor.
- Vendored: tool_certificate 5.0.12 (3 patches, 2 reverted), block_learnerscript 2019052008.6 (1 patch), 2 learnerscript-family blocks, 3 to 6 legacy BizLMS blocks with unconfirmed provenance.
- Core: UAT Moodle 5.3 LTS, local 5.3 runtime and XAMPP 5.1.3+; one existing core file edited on 5.3; 13 ledger records.

## Corrections (fact-base cross-check, 20 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | Flag keys in m..z were undercounted at 50 (a line-pattern grep missed keys not starting `sentientia.`). Correct: 68 keys across 21 plugins; `local_sentientia_platform` holds 17, not 1; `sentientia_m365` holds 1 (`sentientia_m365_enabled`); `sentientia_whatsapp` holds 1 (`sentientia_whatsapp_content_notifications`). Six default ON, not "all off": `ai.assistant.enabled`, `engagement.gamification.enabled`, `engagement.gamification.confetti`, `commerce.crossTenantShare.enabled`, `commerce.crossTenantRequest.enabled`, `ux.commandPalette.enabled`. | PHP include of every `db/feature_flags.php`, 20 Nov 2026; `00-FACTS-INDEX.md` |
| 2 | `sentientia_m365` declares 2 capabilities, not 0 (m..z capabilities total 80; all local plugins 164). | `moodle-enhancement/local/sentientia_m365/db/access.php` |
| 3 | Status harmonised with the rule in `00-FACTS-INDEX.md` section 1: a plugin whose master switch defaults OFF is "Built (behind a feature flag, off by default)" even though its code is in the UAT commit. Applied to m365, pwa, recommendations, skillsai, talent, translate, whatsapp, xapi and `enrol_sentientiasub`. Whether any of these switches is ON on UAT is to be confirmed (owner: Head of Learning & Development). | registry defaults |
| 4 | Vendored `tool_certificate`: the repository at the UAT commit carries 4.5.7 (version 2025031804); the UAT server itself runs upstream 5.0.12 with two 4.5.7-based patched files deployed on 19 Nov, per the re-vendor record. The 20 Nov repository re-vendor to 5.0.12 is Built (verified on development). | `git show af6e65c0e:admin/tool/certificate/version.php`; `docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md` lines 1-35 |
