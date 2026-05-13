# Supplement B — Moodle 5.x Upgrade Plan

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`.
Augments the operational runbook at
`moodle-enhancement/MOODLE5-UPGRADE-RUNBOOK.md` with the strategic
sequencing, dependencies, and validation gates for moving the platform
from Moodle 4.5.10 (production today) to Moodle 5.1.x.

**Current state (12 May 2026):**
- Production runs Moodle 4.5.10 (build 20260216).
- Local development environment runs Moodle 5.1.3 (build 20260415).
- All thirty Airpay-owned plugins have been forward-compatibility tested
  against Moodle 5.1.3 during Phase 8.x.
- The compatibility gap is in the renderer / template / scheduled-task
  surface, not in plugin schemas. Two specific incompatibilities were
  surfaced and fixed in Phase 8.2:
  - Moodle 5 removed `MESSAGE_DEFAULT_LOGGEDIN` / `MESSAGE_DEFAULT_LOGGEDOFF`
    constants. Five plugins were updated to `MESSAGE_DEFAULT_ENABLED`.
  - Moodle 5's `cron_setup_user()` is deprecated in favour of
    `\core\session\manager::set_user()`. The proctoring CLI smoke
    was updated.

## 1. Strategic rationale

The platform must move from Moodle 4.5 to Moodle 5 within the next twelve
months, for four reasons.

1. **End of life.** Moodle 4.5 LTS support ends in November 2027. Sticking
   with 4.5 past that date means no security patches for the core layer
   that wraps every Airpay plugin.
2. **Native AI subsystem.** Moodle 5 introduced `\core_ai` as a built-in
   abstraction over language-model providers. The `airpay_assistant`
   plugin already has a bridge class (`core_ai_bridge.php`) that targets
   this subsystem. The bridge is dormant on Moodle 4.5 (no `core_ai` to
   bind to) and activates on Moodle 5.
3. **Improved capability model.** Moodle 5 strengthened the context-level
   inheritance documented in Phase 8.1 B9. New plugins shipping at
   `CONTEXT_COURSE` rather than `CONTEXT_SYSTEM` work cleanly with the
   Moodle 5 role-inheritance semantics.
4. **Compatibility with newer PHP / MariaDB.** Moodle 5 supports PHP 8.3
   and 8.4 cleanly; Moodle 4.5 caps at PHP 8.3. Staying on 4.5 limits
   the PHP runtime upgrade pathway.

## 2. Risks (cross-reference to Supplement A)

- **A4** — Theme template carry-forward gap. New core templates Moodle 5
  introduces may not have airpayux overrides. Local development already
  exposed eight such templates; all are queued for carry-forward.
- **D1** — Custom column drift on `mdl_user` if BizLMS pushes a schema
  update mid-fork. The Moodle 5 upgrade does NOT touch BizLMS columns;
  they are pure additions on top of Moodle's schema.
- **I3** — Disaster recovery cold-site procedure undefined. A Moodle 5
  upgrade is the right occasion to exercise the DR drill in parallel.

## 3. Pre-upgrade prerequisites

Each must be complete before the upgrade window starts.

| # | Prerequisite | Owner | Status |
|---|---|---|---|
| 1 | Production database backup verified by restore drill | IT | Open — first drill scheduled in 90-day plan week 3-4 |
| 2 | File backup of `/var/www/moodle` to S3 with verified manifest | IT | Open — covered by DEPLOYMENT-RUNBOOK §0 |
| 3 | Staging environment running Moodle 5.1.x with a prod-sized DB clone | IT | Open — week 1-2 of 90-day plan |
| 4 | All thirty Airpay-owned plugins pass smoke + UAT on Moodle 5 staging | Head of L&D | Local Moodle 5.1.3 runs all four Phase 8.x smokes (84/84 cases). Staging re-run required. |
| 5 | Compatibility report from `php admin/cli/check_database_schema.php` reviewed and approved | IT + Head of L&D | Open |
| 6 | `moodle.org/plugin-database-compatibility` checked for every Airpay-owned plugin's declared `$plugin->requires` ≤ Moodle 5 version | Head of L&D | Open — automated in `MOODLE5-UPGRADE-RUNBOOK.md` |
| 7 | Active session count below 100 (early-morning window) | IT | Window-time gate |
| 8 | Maintenance-mode announcement T-24h, T-1h, T+0 (template in deployment runbook) | Head of L&D | Window-time gate |

## 4. Upgrade sequencing

Two passes:

### Pass 1 — Schema-compatible upgrade (Moodle 4.5.x → 4.6 → 5.0 → 5.1)

Moodle's upgrade machinery handles point-version transitions automatically.
The actual command:

```bash
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
```

The risk in this pass is in long-running database migrations (e.g.
column type changes on `mdl_question`). Mitigation: pass `--allow-unstable`
only when the maintenance window is sized to accommodate the documented
worst-case migration time per the Moodle changelog.

### Pass 2 — Theme + plugin compatibility verification

After the schema upgrade, run:

1. `php admin/cli/build_theme_css.php --themes=airpayux` — pre-compile
   the theme CSS against the new core SCSS.
2. `php admin/cli/check_database_schema.php` — verify no plugin DB
   drift was introduced.
3. Per-plugin smoke tests (cart, proctoring, recompletion, request,
   plus the existing tier-1 smokes).
4. Phase 7 multi-role UAT (84/85 expected, matching the established
   baseline).
5. axe-core a11y scan against the dashboard, catalogue, course view
   and admin pages.

## 5. Plugin-by-plugin compatibility status

Verified against the Moodle 5.1.3 local development environment as of
12 May 2026.

| Plugin | 5.1.3 status | Action required |
|---|---|---|
| local_airpay_analytics | ✓ | None |
| local_airpay_assistant | ✓ | `core_ai_bridge.php` activates only on 5.x — verify post-upgrade |
| local_airpay_cart | ✓ | None |
| local_airpay_catalog | ✓ | None |
| local_airpay_challenge | ✓ | None |
| local_airpay_classroom | ✓ | Phase 8.2 fixed messages.php constants |
| local_airpay_compliance_report | ✓ | None |
| local_airpay_core | ✓ | None |
| local_airpay_courses | ✓ | None |
| local_airpay_emails | ✓ | None |
| local_airpay_evaluation | ✓ | None |
| local_airpay_exams | ✓ | None |
| local_airpay_gamification | ✓ | None |
| local_airpay_integrations | ✓ | None |
| local_airpay_learningpath | ✓ | None |
| local_airpay_lifecycle | ✓ | None |
| local_airpay_manager | ✓ | None |
| local_airpay_notifications | ✓ | Phase 8.2 fixed messages.php constants |
| local_airpay_org | ✓ | None |
| local_airpay_pages | ✓ | None |
| local_airpay_privacy | ✓ | None |
| local_airpay_proctoring | ✓ | Phase 8.2 fixed messages.php; CLI smoke uses `\core\session\manager::set_user()` not deprecated `cron_setup_user()` |
| local_airpay_programs | ✓ | None |
| local_airpay_ratings | ✓ | None |
| local_airpay_recompletion | ✓ | Phase 8.2 fixed messages.php constants |
| local_airpay_reports | ✓ | None |
| local_airpay_request | ✓ | Phase 8.2 fixed messages.php constants |
| local_airpay_roles | ✓ | None |
| local_airpay_skills | ✓ | None |
| local_airpay_users | ✓ | None |
| quizaccess_airpay_proctoring | ✓ | Phase 9 N7 migration runs in `db/upgrade.php` |

**BizLMS plugins (disabled).** The twenty-two BizLMS plugins are bundled
on disk but disabled in `mdl_config_plugins`. Their compatibility with
Moodle 5 has NOT been verified. Plan: keep them disabled through the
Moodle 5 upgrade, and complete the FORK-PLAN displacement before
re-enabling any of them would be considered.

## 6. Theme carry-forward checklist

Eight Moodle 5 core templates lack an airpayux override and currently
render in Moodle's default Boost styling. Each must be reviewed and
either carried forward (with Airpay branding) or explicitly accepted
as Boost-default.

1. `templates/core/notification_modal.mustache` — confirmation dialogs
2. `templates/core/contentbank/contentbank.mustache` — content bank
3. `templates/core/oauth2/login.mustache` — OAuth2 SSO sign-in
4. `templates/core_courseformat/local/content/sectionnav.mustache` — section nav
5. `templates/core/competency/user_competency_summary.mustache` — competencies
6. `templates/core/h5p/h5p.mustache` — H5P container
7. `templates/core/message_drawer.mustache` — message drawer (revamped in 5.0)
8. `templates/core/admin/setting_configduration.mustache` — admin setting widget

The first four are user-facing and should be carried forward before
exposure. The remaining four are admin-or-niche; carry forward optionally.

## 7. Cutover sequencing

The Moodle 5 upgrade is sequenced AFTER the Airpay Academy 2.0 cutover
(the Phase 8 deliverable). Order of operations across the next 6 months:

| Phase | Date target | Deliverable |
|---|---|---|
| Phase 8.4 — Production cutover at Moodle 4.5 | Within 2 weeks of staging gates | Airpay Academy 2.0 live on 4.5 |
| Stabilisation | Weeks 3-4 post-cutover | P0 backlog cleared, observability in place |
| SENTIENTIA build | Weeks 5-8 post-cutover | First 10 SCORM courses generated end-to-end |
| BizLMS displacement P0 | Weeks 9-12 | local_costcenter / local_users / local_courses absorbed |
| Quarter-end review | Week 13 | Decisions on Moodle 5 timing |
| Moodle 5 upgrade (target) | Q4 2026 | Production runs Moodle 5.1.x |

The reason for sequencing Moodle 5 after the cutover is risk management.
The 2.0 cutover is itself a major event. Bundling a Moodle core-version
change with it would mean two failure modes overlapping in the same
maintenance window.

## 8. Validation gates per upgrade pass

Each gate must be cleared before the maintenance window ends or the
upgrade is rolled back.

| Gate | Pass criterion | Source |
|---|---|---|
| Schema migration completes | `admin/cli/upgrade.php` exits zero | runbook §3 |
| Theme CSS builds | `admin/cli/build_theme_css.php` exits zero | runbook §4 |
| Plugin smoke tests pass | 84/84 cases (cart, proctoring, recompletion, request) | manual run |
| Phase 7 multi-role UAT passes | 84/85 minimum (matching baseline) | manual run |
| axe-core a11y scan | 0 critical, 0 serious violations on dashboard / catalogue / course / admin | manual run |
| Capability re-registration | No errors in upgrade.log | runbook §3 |
| Message provider re-registration | No errors in upgrade.log | runbook §4 |

## 9. Rollback plan

Per `PHASE-8-DEPLOYMENT-RUNBOOK.md` §7, with one Moodle 5-specific addition:

- The `airpay_assistant\core_ai_bridge.php` activates on Moodle 5. If
  rollback is to Moodle 4.5, the bridge becomes dormant again; no data
  loss. The bridge is read-only and stores no state of its own.

## 10. Open decisions

| Decision | Owner | Recommended |
|---|---|---|
| Moodle 5 upgrade window — Q3 or Q4 2026? | Head of L&D + IT | Q4 2026 (after BizLMS displacement P0) |
| PHP runtime upgrade — bundle with Moodle 5 (8.3) or sequence after (8.4)? | IT | Bundle (8.3). Reduces total maintenance windows. |
| Theme carry-forward — all 8 templates or only the four user-facing? | Head of L&D | All 8 if engineer hire (Decision 13.3) lands; otherwise the four user-facing only. |
| Moodle Mobile app sync — does the Moodle 5 mobile app require client-side updates from learners? | IT + Communications | Likely yes; bundle in T-24h comms. |
