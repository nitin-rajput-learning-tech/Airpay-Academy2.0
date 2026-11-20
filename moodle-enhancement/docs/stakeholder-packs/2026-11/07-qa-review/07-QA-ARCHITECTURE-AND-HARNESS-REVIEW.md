# Sentientia LMS: Quality Engineering Review

## Current mechanisms, test harnesses and proposed test strategy

| Field | Value |
|---|---|
| Prepared for | Neha Bagtharia, QA Architect, and the QA team |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Classification | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

Heading convention: the front matter is numbered 0.1 (document control) and 0.2 (executive summary); the body starts at section 1; appendices are lettered.

## 0.1 Document control

### 0.1.1 Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 November 2026 | Platform engineering, for the Head of Learning & Development | First issue for QA review |

### 0.1.2 Reviewers and sign-off

| Role | What the reviewer signs off |
|---|---|
| QA Architect | Accuracy of the current-state review; acceptance or amendment of the proposed test strategy (section 7) and the severity scheme (section 7.10) |
| Head of Learning & Development (product owner) | Release gates, UAT sign-off process and test-data handling rules |
| DevOps lead | Environment strategy (section 7.4), UAT deployment verification (section 3.11) and the staging proposal |
| Information Security lead | Security-testing gaps and proposals (sections 5.3 and 8) |
| Data Protection Officer | Test-data handling rules (section 6.2) and the retention of database dumps |

### 0.1.3 Related documents

| Document | Location |
|---|---|
| Shared fact base and canonical numbers | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md`, `_facts/60-eng-qa-infra.md` |
| Quality-gate system decision | ADR-027, `moodle-enhancement/docs/adr/ADR-027-quality-gate-system.md` |
| Surface coverage matrix (ADR-027 Gate 3) | `moodle-enhancement/docs/COVERAGE-MATRIX.md` |
| Persona by workflow test matrix | `moodle-enhancement/docs/testing/WORKFLOW-TEST-MATRIX.md` |
| PHPUnit CI gate guide | `moodle-enhancement/docs/ci/PHPUNIT-GATE.md` (partly out of date, see section 8) |
| Playwright CI gate guide | `docs/ci/PLAYWRIGHT-GATE.md` |
| UAT deploy runbook | `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Migration rehearsal runbook and kit | `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md`, `tools/rehearsal/README.md` |
| Cutover guide | `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` |
| Other packs in this series | CTO product guide (pack 03), security review, IT infrastructure and DevOps, UI/UX review, DPDP privacy packs |

---

## 0.2 Executive summary

**What this is.** A review of how Sentientia LMS is tested today, and a proposed test strategy aligned to ISO/IEC/IEEE 29119 for the QA team to own. Sentientia LMS is a multi-tenant learning platform built by Airpay Payment Services on Moodle 5.3 LTS. It is **not live**: the current www.airpay.academy is the legacy BizLMS platform on Moodle 4.1.2. Sentientia runs on the UAT (user acceptance test) environment, academy2.airpay.ninja, which holds a fresh installation with synthetic test personas and sample courses, and on development machines, which hold a copy of the production database imported for testing only.

**Key facts (20 November 2026).**

- 57 product components: 47 local plugins, 6 blocks, 1 enrolment plugin, 1 payment gateway, 1 quiz access rule and 1 theme. 142 feature flags (13 default ON).
- 4,861 PHPUnit test methods in 464 files (static count). 125 Python tests for the content pipeline. 23 Playwright test declarations in 12 specs. 115 scripted persona-journey steps across 11 personas.
- 20 pre-commit checks, 3 GitHub Actions workflows with 15 jobs in the main workflow, and about 20 repository gate scripts.
- 17 recurring defect classes have each been turned into an automated or procedural guard.
- 1,528 screenshots in 47 dated visual-evidence folders, May to November 2026.
- UAT deployments are verified by hashing deployed files against git, a version probe, a feature-flag snapshot diff and server, browser and persona smoke tests.

**What is strong.** Static and structural gates are broad and come from real defects. Tenant isolation and privacy erasure are blocking PHPUnit groups in CI. UAT deployments follow a written runbook with stop rules and rollback. UI changes leave dated screenshot evidence; since October 2026 the evidence scripts mask personal data, and earlier folders are still to be reviewed (owner: QA Architect with the Head of Learning & Development).

**What is weak.**

1. **No test runs on the target stack automatically.** CI runs PHPUnit on Moodle 5.2 with MariaDB (an open-source fork of MySQL), not on Moodle 5.3 with MySQL 8.4. The Playwright lane runs against a Moodle 4.5 snapshot and is advisory.
2. **Only two PHPUnit groups block a merge.** The full suite is advisory.
3. **Several types of testing have not been done:** load and performance, security (no static analysis, dependency scanning or dynamic scanning), automated accessibility beyond 3 surfaces, and mobile devices.
4. **No staging environment.** UAT is a 2 GB single server with test data.
5. **No defect tracker and no code-coverage measurement.** Defects are recorded in project notes and audit documents.
6. Changes reach the release branch by direct push, so checks that run only on pull requests do not run.

**What we ask of the QA Architect** (section 10):

1. Review this document and accept or amend the proposed strategy, severity scheme and release gates by 4 December 2026.
2. Name the QA owners for regression, UAT sign-off and the defect workflow.
3. Choose a defect and test-management tool.
4. Sign the test-data handling rules for every QA team member before any access is granted.
5. Agree the QA-led programme for the gaps: 5.3 CI, load testing, security testing, accessibility and mobile.

**Top risks** (section 11): a defect that appears only on Moodle 5.3 or MySQL 8.4 reaches UAT unseen; the first production-class performance figures arrive at Stage B, too late to fix design issues cheaply; personal data in the imported test data escapes through screenshots, logs or exports; and the 20 November development changes have had no finished PHPUnit run.

---

## 1. Introduction

### 1.1 Purpose

This document gives the QA team an accurate picture of the quality mechanisms that exist today, explains how to run each one, maps coverage and gaps, and proposes a test strategy the QA team can adopt, change and own.

### 1.2 Audience

The QA Architect and the QA team. Readers are assumed to know test design, CI pipelines and web-application testing. Moodle-specific terms are explained in the glossary (Appendix A).

### 1.3 Scope

In scope: the Sentientia product layer (57 components), its feature flags, its CI and repository gates, the test harnesses, UAT deployment verification, migration rehearsal and test data. Out of scope: the legacy BizLMS production site, which is never patched (it is replaced at cutover), and third-party vendored code except where we patch it.

### 1.4 Status labels used

Product features carry the labels from the master brief: **Available (UAT)**, **Built (verified on development)**, **Built (behind a feature flag, off by default)**, **In progress**, **Planned**, **Not planned**.

Engineering tooling is not deployed to UAT, so for tooling this document uses:

| Label | Meaning for a QA mechanism |
|---|---|
| **Built (verified on development)** | The tool or gate exists in the repository and has run on a development machine or in CI. |
| **Available (UAT)** | The verification step has been executed against the UAT environment (recorded deploys of 18 and 19 November 2026). |
| **Planned** | Proposed in this document or in an ADR. No tooling exists yet. |

A separate column states whether a gate is **Blocking** (fails the commit or build), **Advisory** (runs, reports, never fails the build), **Warn** (prints a warning) or **Manual**.

### 1.5 Standards applied

- ISO/IEC/IEEE 29119 (software testing): part 1 concepts, part 2 test processes (organisational, management and dynamic), part 3 test documentation, part 4 test techniques. Section 7 is structured against it. We **map against** the standard. We do not claim conformance.
- The test pyramid model, for the balance of unit, integration and end-to-end tests.
- Requirements traceability and a defect taxonomy (section 4 and section 7.10).
- Supporting references: WCAG 2.2 AA for accessibility targets, and the OWASP ASVS and Top 10 for security testing scope. These are covered in depth by the UI/UX and security packs.

### 1.6 Method and basis

Facts come from the shared fact base (`_facts/00-FACTS-INDEX.md` and `_facts/60-eng-qa-infra.md`) and were checked against the repository on 20 November 2026 (the branch head plus the uncommitted working tree). Appendix E lists 22 claims that were spot-checked, with the evidence for each. Three read-only gates were run for this review: language parity (0 failures, 1 warning), theme layout regions (0 unprinted regions) and tree drift (no drift outside the baseline). No PHPUnit, browser or UAT run was made for this document.

---

## 2. Quality goals and risks

### 2.1 Product context that shapes testing

| Factor | Testing consequence | Source |
|---|---|---|
| 3 tenants (Airpay (internal employees), Public (consumer storefront), ZEEA Mafunzo (Tanzania)) in one site, identified by the user's organisation path | Every data query must be scoped. A scoping defect produces silently wrong numbers or exposes another tenant's data | `local/sentientia_platform/classes/tenant.php` |
| 142 feature flags, 5 override scopes, 13 default ON | Behaviour depends on flag state per customer and tenant. Both states of a flag need testing | `_facts/20-flags-roles.md` |
| 9 personas with different capability sets; 181 declared capabilities | Testing as a site administrator hides permission defects. Tests must run as each persona | `_facts/20-flags-roles.md` section 5.2 |
| Built on Moodle core, which is upgraded upstream | Upgrade paths, core patches and Moodle-version differences are a defect source | ADR-033, ADR-044 |
| 185 database tables, 111 of which hold personal data by column name | Privacy export and erasure must be complete. Test data is real personal data from a backup | `_facts/40-data-privacy.md` |
| Migration from Moodle 4.1.2 in two upgrade hops, plus a BizLMS data import | Data parity is an acceptance criterion: 100% of the baseline counts | `MIGRATION-REHEARSAL-RUNBOOK.md` |
| Two plugin trees deployed from (canonical and twin) | A fix in one tree only leaves the other copy unfixed | `tools/check-tree-drift.php` |
| 5 language packs (en and hi at parity; kn, mr, sw at 81%) | String completeness and layout under longer strings | `_facts/70-ui-surfaces.md` section 5 |
| Design capacity of 3,500 or more users | Performance at that scale has not been measured | ADR-001 (product pivot, design scale); `PHASE-E-PERFORMANCE-BASELINE.md` |

### 2.2 Quality goals

The goals below are proposed for QA adoption. They restate the product's engineering rules as testable goals.

| # | Goal | Measure (proposed) |
|---|---|---|
| Q1 | No cross-tenant data exposure | 0 open tenant-isolation defects; blocking `tenant_isolation` group green on the target stack |
| Q2 | Personal data can be exported and erased completely | `privacy_erasure` group green; erasure rehearsal with 0 residual rows in our tables |
| Q3 | Current Airpay Academy behaviour is not broken when flags are at their defaults | Regression pack green with every flag at its registered default |
| Q4 | Migration preserves all data | 100% parity on the Stage B baseline counts |
| Q5 | Each persona can complete its key workflows | Persona journeys pass on UAT for all 9 personas |
| Q6 | Pages render without script errors and meet the accessibility target | 0 console errors on render smoke; 0 serious or critical axe violations on curated surfaces; WCAG 2.2 AA review of key journeys |
| Q7 | Acceptable performance at design scale | Proposed service level objectives (section 5.4) met on a production-class environment |
| Q8 | Every release is reproducible and verifiable | Deployed files hash-equal to the git commit; flag state unchanged except new flags OFF |

### 2.3 Product risks for risk-based testing

Likelihood and impact are the review's assessment from the defect history in section 4. They are inputs for the QA team to re-rate.

| # | Risk | Likelihood | Impact | Evidence | Current mitigation |
|---|---|---|---|---|---|
| R1 | Tenant scoping defect (wrong numbers or cross-tenant exposure) | High (shipped 4 or more times in September 2026) | High | `tools/check-path-boundary.php` header | Path-boundary gate, blocking `tenant_isolation` group |
| R2 | Incomplete privacy export or erasure | Medium | High (DPDP obligation) | A4 erasure rehearsal; `_facts/40-data-privacy.md` findings F-1 and F-3 | Coverage test, `privacy_erasure` group, supplementary provider behind flags |
| R3 | Defect specific to Moodle 5.3 or MySQL 8.4 | Medium | High | CI runs 5.2 and MariaDB; MySQL 8 reserved words failed at Stage A | Reserved-word scan; local 5.3 runtime used by hand |
| R4 | Migration parity failure or upgrade failure on real data | Medium | High | 2026-10-08 rehearsal found one parity difference (an empty survey activity) | 13-step rehearsal kit with self-test |
| R5 | Capability defect visible only in a real browser session | Medium | High | Role-switch demotion defect, found only in a browser session | Persona walks in the browser |
| R6 | Flag interaction defect (a combination nobody tested) | Medium | Medium | 142 flags, 5 scopes | Flag snapshot diff on deploy; no combinatorial testing |
| R7 | Performance or capacity shortfall at 3,500 users | Unknown | High | No load test; UAT is 2 vCPU and 2 GB | None |
| R8 | Security vulnerability not caught by functional tests | Unknown | High | No static analysis, dependency or dynamic scanning in CI | Pre-commit pattern checks; security regression tests (tenant scope) in the courses, org, reports and users external-function suites, for example `local/sentientia_org/tests/external/delete_org_test.php`; security pack |
| R9 | Accessibility failure on untested surfaces | Medium | Medium | Axe on 3 surfaces. The May 2026 axe audit found 5 serious colour-contrast issues. Token-level contrast fixes followed, but no axe re-run has been recorded since. Current status: to be confirmed (owner: QA Architect). Sources: `moodle-enhancement/PHASE-H-A11Y-AXE-RESULTS.md`; `theme/sentientia/scss/moodle/_tokens.scss` (A11Y-7) | `a11y-smoke.spec.ts`; axe re-run on the current theme (section 8, item 11) |
| R10 | Silent UI failure (page looks fine, script dead) | Medium | Medium | Dead AMD, template leaks, unprinted layout region | Gate 0 scanners, render smoke |
| R11 | Personal data leaks from test data | Low to medium | High | Real names masked in screenshots since October 2026 | Masking in evidence scripts; outbound mail disabled |
| R12 | Deployed code differs from the reviewed commit | Low | High | CRLF line endings differ from git blobs | Hash raw git blobs; per-deploy hash list |

---

## 3. Current QA mechanisms and how to run them

### 3.1 Overview: layers of defence

| Layer | Mechanism | When it runs | Gate type | Status |
|---|---|---|---|---|
| Author's machine | Pre-commit hook, 20 checks | `git commit` (if installed) | Blocking for errors, Warn for warnings | Built (verified on development) |
| Repository | About 20 gate scripts in `tools/` and `moodle-enhancement/tools/` | On demand, pre-commit and CI | Varies | Built (verified on development) |
| CI | `ci.yml` (15 jobs), `core-mods.yml`, `deploy-production.yml` (manual only) | Pull request and push to `production` | 12 blocking jobs, 2 partly or fully advisory, 1 pull-request only | Built (verified on development) |
| Unit and integration | PHPUnit (4,861 methods), pytest (125) | Locally and in CI | 2 blocking PHPUnit groups; pytest blocking | Built (verified on development) |
| UI automation | Playwright CI suite (23 tests); visual-pass toolkit and persona journeys (115 steps) | CI (advisory); locally | Advisory and Manual | Built (verified on development) |
| Evidence | Visual-evidence ledger with a README for each change | After each UI change | Manual (product-owner review) | Built (verified on development) |
| Review | AI-assisted code review and screenshot review | Before a commit or merge | Manual | Built (verified on development) |
| Deployment | UAT deploy tool and verification runbook | Each UAT deploy | Manual, with stop rules | Available (UAT) |
| Migration | Rehearsal kit (13 steps), privacy erasure rehearsal | Before Stage B and cutover | Manual | Built (verified on development); run on the target box Planned |

### 3.2 Pre-commit hook

**What it is.** `.claude/hooks/pre-commit.sh` (849 lines) checks the files staged for a commit. Errors stop the commit. Warnings do not. It reads the staged list with `git diff --cached --no-renames --diff-filter=ACM`, so a file that was renamed and edited is still checked.

| # | Check | Severity | CI twin |
|---|---|---|---|
| 1 | PHP syntax of staged `.php` files | Error (warning if PHP is missing) | `php-lint` |
| 2 | `MOODLE_INTERNAL` guard on class and library files | Error | none |
| 3 | Raw `$_GET`, `$_POST`, `$_REQUEST` access | Error | none |
| 4 | Hard-coded credential patterns | Error | none |
| 5 | `.env` file staged | Error | none |
| 6 | Moodle core file edited without a core-modification record and marker | Error and warn | `core-mods.yml` |
| 7 | Protection of source SOP documents in `content/sops/` | Error and warn | none |
| 8 | SCORM ZIP structure (`imsmanifest.xml` at the root) | Error | none |
| 9 | `version.php` format (`YYYYMMDDNN`) | Error and warn | `version-bump-check` (bump only) |
| 10 | Uncommitted `[CONFIRM]` placeholders | Warn | none |
| 11 | Git conflict markers | Error | `conflict-marker-check` |
| 12 | State-card freshness (a plugin change should stage its state card) | Warn | none |
| 13 | Mustache (Moodle's HTML template language) comment leak | Error | `php-lint` step |
| 14 | Stale `theme_airpayux` references | Error | `php-lint` step |
| 15 | Missing `standard_end_of_body_html` in full-page layouts | Error | `php-lint` step |
| 16 | AMD JavaScript source and build parity | Error and warn | `amd-build-parity` |
| 17 | English and Hindi language-pack parity | Error and warn | `lang-parity-check` |
| 18 | Tenant path-boundary scan | Error and warn | `path-boundary-check` |
| 19 | Cross-tree twin check (staged file differs from its twin) | Warn | `tree-drift-check` (blocks new drift) |
| 20 | `SITEID` strict-comparison scan | Error and warn | `siteid-compare-check` |

**How to install.** The hook is per clone, because `.git/hooks/` is not tracked. From the repository root, in Git Bash:

```bash
cp .claude/hooks/pre-commit.sh .git/hooks/pre-commit
```

Or with PowerShell, where it is allowed: `pwsh -File tools/install-hooks.ps1`.

**How to run without committing.** Stage the files, then run `bash .claude/hooks/pre-commit.sh`.

**Limits.** The hook can be skipped (`--no-verify`, some graphical (GUI) Git clients, a clone without the hook). Checks 2, 3, 4, 5, 7, 8, 10 and 12 have no CI twin, so a skipped hook means nothing else catches those defects. The companion `lint-on-save.sh` is wired so that it receives no file path, so it lints nothing unless run by hand on a file.

### 3.3 CI workflows and gates

Three workflows exist in `.github/workflows/`. GitHub-hosted Ubuntu runners execute them.

| Workflow / job | What it checks | Gate type | Status |
|---|---|---|---|
| `ci.yml` / `php-lint` | `php -l` on product PHP (PHP 8.2); Gate 0 scans for Mustache comment leaks, stale theme references and missing end-of-body output | Blocking | Built (verified on development) |
| `static-checks` | JSON (JavaScript Object Notation) validity; Mustache tag balance | Blocking | Built (verified on development) |
| `ws-contract-gate` | Web-service contract drift (ADR-009) | Blocking | Built (verified on development) |
| `conflict-marker-check` | Stray merge-conflict markers | Blocking | Built (verified on development) |
| `path-boundary-check` | Unbounded tenant path prefixes | Blocking | Built (verified on development) |
| `siteid-compare-check` | Type-unsafe `SITEID` comparisons | Blocking | Built (verified on development) |
| `tree-drift-check` | Byte comparison of the two plugin trees against a baseline; BizLMS fixture copies | Blocking for drift not in the baseline | Built (verified on development) |
| `lang-parity-check` | English and Hindi key parity; message-provider strings; theme layout regions | Blocking | Built (verified on development) |
| `amd-build-parity` | Every `amd/src` module has a built `.min.js` (existence only) | Blocking | Built (verified on development) |
| `upgrade-step-versions` | New upgrade savepoints sit above the plugin's version at the base commit; includes a self-test | Blocking | Built (verified on development) |
| `version-bump-check` | `version.php` bumped when plugin code changed | Blocking, **pull requests only** | Built (verified on development) |
| `python-agents` | 125 pytest cases and a mock-mode pipeline smoke test (no network, no spend) | Blocking | Built (verified on development) |
| `playwright-linux` | Playwright on Chromium, Firefox and WebKit against a container with Moodle installed from the repository's 4.5 snapshot; seeds 4 CI persona accounts | **Advisory** | Built (verified on development) |
| `phpunit-52` | Moodle 5.2 stable, PHP 8.3, MariaDB 10.11. Step 1: `tenant_isolation` group (blocking; at least 40 tagged files required). Step 2: `privacy_erasure` group (blocking). Step 3: full suite (advisory; JUnit XML kept 30 days) | **Partly blocking** | Built (verified on development) |
| `stylelint-guard` | Bans retired BizLMS selectors in new SCSS (Sass stylesheet source) files | Blocking | Built (verified on development) |
| `core-mods.yml` / `core-mods-check` | Changed core files need a record and a marker; core patches match their pinned upstream hash | Blocks pull requests; on push it reports only | Built (verified on development) |
| `deploy-production.yml` | Manual dispatch only, needs a typed confirmation string. Not the cutover path | Manual | Built (verified on development); manual; not used for Sentientia |

**Recorded results.** The `phpunit-52` job was rebuilt on 16 and 17 November 2026 for the Moodle 5.1+ directory layout, PHP 8.3 and MariaDB. Before that it had never reached its test steps. Since then the blocking `tenant_isolation` group has run 2,203 tests and passed. The first run that included the privacy step passed both blocking groups. Every job except Playwright was green on 17 November 2026 (source: `PROJECT-STATE.md`, 2026-11-16/17 entries).

**How to read a CI failure.** Open the run in GitHub Actions and select the red job. PHPUnit failures are published as annotations on the run summary. The full-suite JUnit XML is the `phpunit-5.2-results` artefact. The Playwright report and traces are uploaded as artefacts.

**Gaps found in this review.**

| # | Gap | Evidence |
|---|---|---|
| G1 | PHPUnit does not run on Moodle 5.3 or MySQL 8.4 in CI | `MOODLE_BRANCH: MOODLE_502_STABLE`; MariaDB service |
| G2 | The full PHPUnit suite is advisory | `continue-on-error: true` on the full-suite step |
| G3 | Playwright is advisory and runs on the 4.5 snapshot, not the 5.3 theme runtime | `ci.yml` `playwright-linux` |
| G4 | The `ci.yml` paths filter covers `moodle-enhancement/**`, `local/sentientia_**` and `theme/sentientia/**`, but not the twin trees for blocks, enrolment, payment gateway and access rule, and not most of `tools/`. A change only to those paths does not run `ci.yml` | `ci.yml` lines 40 to 81 |
| G5 | Changes reach `production` by fast-forward push, so `version-bump-check` does not run, and `core-mods-check` reports but cannot block | `PROJECT-STATE.md` (2026-11-18 entry); `core-mods.yml` header |
| G6 | No PHP 8.4 lint, no coding-standard check (PHP_CodeSniffer, `phpcs`), no static analysis, no dependency or secret scanning | `ci.yml` comments; no such step in any workflow |

### 3.4 Repository gate scripts

All are read-only in the modes shown below (`check-core-patches.sh` also has an `apply` mode that writes files; QA should not use it). All run from the repository root with PHP 8.2 or later on the path. Exit code 0 means pass.

| Script | What it checks | Command | Status |
|---|---|---|---|
| `tools/check-path-boundary.php` | Unbounded tenant path prefixes (4 patterns); fix with `tenant::path_descendant_filter()` | `php tools/check-path-boundary.php` (add `--quiet` for failures only) | Built (verified on development) |
| `tools/check-siteid-compare.php` | Strict comparisons with `SITEID`, which is a database string | `php tools/check-siteid-compare.php` | Built (verified on development) |
| `tools/check-tree-drift.php` and `tools/tree-drift-baseline.txt` | Both plugin trees byte-equal (line endings normalised); baseline of 38 recorded differences | `php tools/check-tree-drift.php` | Built (verified on development) |
| `tools/check-lang-parity.php` | English and Hindi key sets match where a Hindi pack exists | `php tools/check-lang-parity.php` | Built (verified on development) |
| `tools/check-message-provider-strings.php` | Each declared message provider has its string | `php tools/check-message-provider-strings.php` | Built (verified on development) |
| `tools/check-theme-layout-regions.php` | Each declared block region is printed by its template | `php tools/check-theme-layout-regions.php` | Built (verified on development) |
| `tools/check-upgrade-step-versions.php` (and `-selftest.php`) | New upgrade savepoints above the base version | `php tools/check-upgrade-step-versions.php --base origin/production` | Built (verified on development) |
| `tools/check-bizlms-fixture-copies.php` | The two signed import-decision files equal the copies PHPUnit reads | `php tools/check-bizlms-fixture-copies.php` | Built (verified on development) |
| `tools/check-core-patches.sh` | Core patches equal the pinned upstream file plus marked hunks (ADR-044) | `bash tools/check-core-patches.sh check` | Built (verified on development) |
| `tools/ci/check-core-mods.sh` | Core-file protection over a commit range | `bash tools/ci/check-core-mods.sh <base> HEAD` | Built (verified on development) |
| `tools/check_state_card_freshness.sh` | State cards older than 90 days while code changed | `bash tools/check_state_card_freshness.sh --mode=stale` | Built (verified on development) |
| `tools/check_workspace_sync.sh` | Files in the local deployed tree that are not in git | `bash tools/check_workspace_sync.sh` | Built (verified on development) |
| `moodle-enhancement/tools/scan_mustache_comment_leaks.php`, `scan_stale_theme_refs.php`, `scan_missing_end_of_body.php`, `scan_amd_build_parity.php` | Gate 0 template and JavaScript scanners | `php moodle-enhancement/tools/<scanner>.php <path>` | Built (verified on development) |
| `moodle-enhancement/tools/ci-ws-contract-check.php` | Web-service contract drift (ADR-009) | `php moodle-enhancement/tools/ci-ws-contract-check.php --verbose` | Built (verified on development) |
| `tools/uat/mysql84_reserved_scan.py` | MySQL 8.4 reserved words and oversize identifiers in new table and column names | `python tools/uat/mysql84_reserved_scan.py --base <ref> --head <ref>` | Built (verified on development); run before each UAT push |

### 3.5 PHPUnit suites

#### 3.5.1 Inventory

Static count of `function test*` methods in `*_test.php` files in the canonical tree. Data-provider cases are not counted, so runtime case counts are higher.

| Component group | Test files | Test methods |
|---|---|---|
| 47 local plugins (`local_sentientia_*`) | 424 | 4,600 |
| 6 blocks (`block_sentientia_*`) | 6 | 29 |
| Enrolment plugin (`enrol_sentientiasub`) | 1 | 9 |
| Payment gateway (`paygw_airpay`) | 4 | 33 |
| Quiz access rule (`quizaccess_sentientia_proctoring`) | 4 | 41 |
| Theme (`theme_sentientia`) | 25 | 149 |
| **Total** | **464** | **4,861** |

Largest local suites by method count: platform 486, cart 315, classroom 255, users 224, evaluation 212, courses 207, emails 183, learningpath 163, live 148, programs 134, authoring 129, recompletion 128, catalog 122, roles 116, core 114.

Thinnest: lifecycle 5, pages 6, gamification 10, content_market 13, talent 16, reports 17, ai 19. The 6 blocks together have 29 methods.

Appendix F gives test files, test methods and `tenant_isolation`-tagged files for each of the 57 components.

#### 3.5.2 Test groups used by gates

| Group | Tagged | Role |
|---|---|---|
| `tenant_isolation` | About 135 test files by the CI sanity-step method: a literal `@group tenant_isolation` match over local plugin and theme tests (other recounts give 134 to 140 depending on scope). Counting every form PHPUnit reads (aligned spacing after `@group`, the `#[Group]` attribute, test sub-folders) gives 183 files (Appendix F). The CI sanity count therefore under-reports the group; its floor is 40. See Appendix E row 9 and risk 15 | Blocking in CI; at least 40 tagged files required, so an empty group cannot pass |
| `privacy_erasure` | 1 file | Blocking in CI; includes a canary test that fails when Moodle core fixes the context-list truncation bug, so the work-around is then reviewed |
| `bizlms_import` | 61 tags | Not a CI gate; supports the ADR-032 import |
| `ws_contract`, `role_detector` | Theme tests | Structural invariants (ADR-009) |

Two structural tests deserve QA attention: `privacy_coverage_test` (in `local_sentientia_platform`) walks every plugin's schema and fails when a table with a user-identifying column sits under a "holds no personal data" privacy declaration. `modal_form_guard_test` is a tripwire for the core patch of ADR-044.

#### 3.5.3 Recorded runs

| Run | Result | Source |
|---|---|---|
| CI, Moodle 5.2 and MariaDB, `tenant_isolation` | 2,203 tests, pass (17 November 2026) | `PROJECT-STATE.md` |
| Local, Moodle 5.3 runtime, platform suite | 586 OK | `PROJECT-STATE.md` (2026-10-09) |
| Local, Moodle 5.1, 57 flag-writing files | 785 tests OK | same |
| Local, Moodle 5.1, targeted suites of 18 November commits | skillsai 59, authoring 129, notifications 73, compliance_report 55, gamification 10, analytics 49, pwa 40: 0 failures | `PROJECT-STATE.md` (2026-11-18) |
| The 20 November development changes | **No finished run.** The local re-initialisation was cut off | `PROJECT-STATE.md` (2026-11-20) |

#### 3.5.4 How to initialise and run

Three environments can run PHPUnit. Each has its own configuration.

**CI (authoritative for the gate).** Push a branch and open a pull request, or push to `production`. The job builds `phpunit.xml` with one test suite per plugin. To reproduce a CI failure locally, follow `moodle-enhancement/docs/ci/PHPUNIT-GATE.md` section 5, but take the database engine (MariaDB 10.11), PHP 8.3 and the `public/` directory layout from the current `ci.yml`. That guide still describes PostgreSQL and PHP 8.2.

**Local Moodle 5.1 (XAMPP, a bundled Apache, MariaDB and PHP stack for Windows).** Test settings (`phpunit_dataroot`, `phpunit_prefix`) are in the local `config.php`. From the Moodle root, one level above `public/`:

```bash
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --group tenant_isolation
vendor/bin/phpunit public/local/sentientia_cart/tests/
```

On the current development machine, `cmd.exe` blocks `init.php`. The working alternative is `public/admin/tool/phpunit/cli/util.php` with its drop, install and build-config options. Re-initialise after any `install.xml` change or plugin addition.

**Local Moodle 5.3 runtime.** A private Moodle 5.3 tree with PHP 8.4 and MariaDB 11.4, outside the repository, is used for the 5.3 compatibility gate. The same commands apply from its root.

#### 3.5.5 Known runtime issues on the development machine

| Issue | Effect | Work-around |
|---|---|---|
| Endpoint-protection scanning without exclusions | Full suites and installs are slow (a Moodle 5.2 fresh install took 98.8 minutes). A local `tenant_isolation` run of 2,221 tests ran at about 12.5 seconds per test | Run full suites in CI; run targeted files locally |
| XAMPP MariaDB defaults | PHPUnit 5 to 10 times slower | Set `innodb_flush_log_at_trx_commit=2` at runtime (it resets when MariaDB restarts) |
| One shared local test database | Two concurrent initialisations or runs corrupt each other | Check that no `init.php` or PHPUnit process is running before starting one |
| No administrator rights; console-mode Apache | Services cannot be managed normally | Start and stop from the command line |
| Locale | Moodle PHPUnit needs `en_AU.UTF-8` (CI generates it) | Installed in CI; check locally |
| XMLDB definition traps | A raw `<` in a `COMMENT` attribute breaks `install.xml` silently; `upgrade.php` hides it | PHPUnit initialisation is the fresh-install gate: run it after any schema change |

### 3.6 Python content-pipeline tests

125 pytest cases cover the six command-line agents that turn an SOP into a SCORM package. External services are replaced with injected fakes, so the tests never reach the network or spend money. A mock-mode end-to-end smoke (`scripts/agents/run_pipeline_test.py`) runs all six agents (1 to 6): Agents 2 and 4 in mock mode, Agent 5 locally, Agent 6 as a mock upload; no network, no spend.

```bash
pip install -r requirements.txt
python -m pytest tests/agents/ -v
python scripts/agents/run_pipeline_test.py
```

Status: Built (verified on development); blocking in CI.

### 3.7 Playwright CI suite

`tests/playwright/` holds 12 specs with 23 test declarations: login, dashboard, navbar, dark mode, mobile at 590 px, learner, manager, compliance, author, accessibility smoke (axe-core), render smoke and visual.

- **Render smoke (ADR-027 Gate 1):** 0 console errors, the JavaScript module loader is present, no literal `{{` in the page body, landmarks present.
- **Accessibility smoke (ADR-027 Gate 2, accessibility half):** 0 axe-core violations of impact serious or critical against the WCAG 2.0 and 2.1 A and AA rule sets, on 3 surfaces (dashboard, my courses, profile), plus course view when a course id is supplied.
- **Visual diff (Gate 2, visual half):** scaffolded; baseline images not yet seeded.
- Persona specs read credentials from environment variables and skip when they are unset. CI seeds four throw-away persona accounts inside the container (`tools/ci/seed_playwright_personas.php`). Deep journeys that change data are staged as `test.fixme`.

How to run locally:

```bash
cd tests/playwright
npm ci
npx playwright install --with-deps
PLAYWRIGHT_BASE_URL=http://localhost:8080 npx playwright test --project=chromium
```

The suite README shows a base URL ending in `/moodle`. The local site has no `/moodle` prefix.

Status: Built (verified on development); advisory in CI. The decision to re-point the lane to Moodle 5.x and the Sentientia theme is recorded; the work is Planned.

### 3.8 Visual-pass toolkit and persona walks

`moodle-enhancement/tools/visual-pass/` drives a local browser through the seeded test personas and saves evidence. It is local only. The base URL is fixed to `http://localhost:8080`, and scripts that use the shared library block every other host except the Google Fonts files.

| Component | Purpose |
|---|---|
| `provision_local_personas.php` | Creates synthetic `vp_*` persona accounts on a local site only (refuses any non-localhost site). Passwords go only to a git-ignored local file and are never printed. No imported user is changed |
| `evidence_lib.mjs` | Shared helpers. `shots()` saves full-page screenshots at 1440 px and 590 px by default, or at chosen widths from 320 to 2560 px, and measures horizontal overflow |
| `persona_journeys.mjs` and `journeys.json` | 11 personas, 115 steps: learner 19, manager 10, trainer 10, author 11, Airpay tenant admin 13, compliance 5, Public learner 13, guest 6, ZEEA learner 11, ZEEA admin 7, site admin 10 |
| `coverage_crawl.mjs`, `coverage_urls.php` | Route coverage crawl |
| About 15 `*_checks.mjs` scripts | Evidence scripts for individual changes, with seed and flag fixtures |
| `moodle-enhancement/tools/gap-test/` | Link stress harness: about 107 URLs, 8 personas, circuit breaker. On 2026-06-18 it found one real defect, which was fixed |

Results are recorded per step as **PASS** (checked and as expected), **FAIL** (checked and wrong) or **BLOCKED** (not checked, with the reason; for example, a step that needs a data write in a read-only pass).

How to run (local site, personas provisioned):

```bash
cd moodle-enhancement/tools/visual-pass
npm install
# with the working directory set to the Moodle public/ folder:
php <repo>/moodle-enhancement/tools/visual-pass/provision_local_personas.php
node persona_journeys.mjs
```

Status: Built (verified on development). Limits: these runs cannot perform state-changing steps against UAT, and browser automation cannot run while the UAT VPN (virtual private network) is connected on the development machine.

### 3.9 Visual-evidence discipline

**Rule.** Every change that touches the user interface ends with desktop and mobile screenshots in `moodle-enhancement/docs/visual-evidence/YYYY-MM-DD/`, with a `README.md` that states what changed, the surfaces, the personas, PASS, FAIL or BLOCKED per step, and what was done about each review finding. The product owner reviews the evidence before merge.

**Ledger size.** 47 dated folders (2026-05-20 to 2026-11-20), 28 of them holding images; 1,528 images, 1,489 of them PNG.

**Masking rule for screenshots.** The development databases contain imported personal data (UAT holds synthetic test personas only), so evidence screenshots must not show real people.

- Before each screenshot, the evidence scripts replace real names and e-mail addresses in the page with `[name hidden]` and `[email hidden]`, in text and in common attributes. Leaderboard names are replaced the same way. A script can also mask whole page regions with a solid block.
- Only the seeded synthetic personas remain readable. Other people's names, e-mail addresses, avatars and certificate codes are blurred or hidden.
- If the list of names to mask is missing, the script warns that masking is a no-op and that the shots must not be committed.
- Evidence READMEs record the masking. This practice is recorded from 2026-10-08 onward (10 READMEs state it). Folders from before October 2026 have not been reviewed against this rule: **to be confirmed** (owner: QA Architect, with the Head of Learning & Development).

**Inconsistencies to fix.** The ledger README asks for 1920 x 1080 desktop and 390 x 844 mobile, and names the retired theme. The toolkit defaults to 1440 px and 590 px. The masking rule is in the scripts and evidence READMEs but not in the ledger README.

Status: Built (verified on development).

### 3.10 AI-assisted independent review

The engineering workflow uses AI-assisted review as an extra check before a commit or merge. It does not replace human review: the product owner reviews evidence and approves merges.

| Review | How it works | Output | Source |
|---|---|---|---|
| Code review | An automated AI reviewer, separate from the author, checks the diff against a written checklist in five sections: PHP security (input handling, output escaping, SQL injection, authentication gates, CSRF protection, hard-coded credentials), Moodle coding standards (including `version.php` format and string externalisation), plugin file completeness, performance at 3,500 users, and multi-tenant rules. The checklist has no privacy section; adding one (privacy provider declarations, personal-data handling) is a gap QA may want closed | Blockers, warnings and info items, with a verdict of approve, request changes or block. Recorded reviews state a verdict such as ship or fix-then-ship, with must-fix and should-fix lists. Findings and their resolution are recorded in the project log and the evidence README | `.claude/agents/code-reviewer.md`; `.claude/commands/pr-review.md`; `PROJECT-STATE.md` (for example the 2026-09-30 review rounds of the import framework) |
| Multi-reviewer read-only review | Used for selected changes, not a standing rule. Example: the November 2026 CI PHPUnit job repair, where several independent automated review passes examined the change and a blocker was accepted only after a second, independent pass confirmed it | List of confirmed blockers | `PROJECT-STATE.md` (CI PHPUnit job repair, November 2026) |
| Screenshot review before a UI commit | An automated reviewer that has not seen the code or the diff receives only the screenshots, the brand rules and one sentence per screenshot. It lists defects per file: overflow, clipped text, missing string markers, raw template tags, off-brand colour or font, low contrast, empty areas, controls too small to tap. It must say "no defects" for a clean screenshot | Findings and actions in the day's evidence README | `moodle-enhancement/tools/visual-pass/README.md` |

**Data sent to the reviewer.** AI-assisted review receives code diffs and masked screenshots only; unmasked screenshots, logs and database content are never sent. Whether any evidence from before October 2026, when masking was not yet recorded, was reviewed unmasked is part of open question 3 (section 11).

**QA view.** The review is checklist-driven and recorded, which makes it auditable. It is not independent of the engineering function in the organisational sense of ISO/IEC/IEEE 29119. Its checklists are not versioned against a defect taxonomy, and its findings are not tracked in a defect tool. Section 7.10 proposes how its findings feed the defect workflow.

Status: Built (verified on development).

### 3.11 UAT deployment verification

UAT deploys and feature-flag changes are product-owner decisions. The deploy tool and the runbook make each deploy verifiable.

| Step | Mechanism | Pass condition |
|---|---|---|
| 0. Local preparation | Clean working tree, CI green, MySQL 8.4 reserved-word scan, export of exactly the files to deploy, hash list of the raw git blobs | Status empty; scan PASS; expected file count; no `skip`, `drift` or `ABORT` line |
| 1. Connect | VPN and an access tunnel for the operator (product owner) | Connection open; no other upgrade running |
| 2. Pre-flight (read-only) | Version probe: a read-only PHP script prints each component's installed version, key role and capability facts and task states. Flag snapshot `tools/uat/flag_snapshot.php`. Remote hash list of the files about to change | Versions match the expected baseline; nothing pending |
| 3. Deploy | `tools/uat/deploy_to_uat.sh` (dry run by default; `--yes` deploys): stage, archive, SHA-256 (a cryptographic hash function) manifest, copy, back up the existing files, extract, `sha256sum -c`, upgrade, cache purge. It aborts when the two plugin trees differ for the same target | Checksum OK; upgrade exit 0 |
| 4. Verify against git | Hash the deployed files and compare with the git blob hashes; repeat the probe and diff it; repeat the flag snapshot and diff it; check that no upgrade is pending | All files equal the git blobs; probe differs only in the expected version lines; flag diff shows only new flags, each OFF, and no existing flag changed |
| 5. Server smoke | HTTP (Hypertext Transfer Protocol) status and time for login, dashboard, catalogue and compliance report; Apache error log | Login 200; others 200 or 303; never 500; no new log errors |
| 6. Browser smoke | Product owner signs in; key pages as learner and L&D administrator personas; browser console | Brand renders; no console errors |
| 7. Persona walk | Scripted walk of the demo journeys; evidence with a README | PASS per step |

**Why raw blobs.** The repository uses `core.autocrlf=true`, so working-tree files can differ from the committed blobs. On the 18 November push, 81 of 139 files differed in the working tree. Hashes must be taken from the blobs.

**Rollback.** The pre-deploy archive restores files. Once an upgrade has run, the new `version.php` files must stay, because the database already records the new versions. The flag snapshot covers site-wide values and global overrides only, not tenant overrides.

**Gaps.** The deploy tool writes no record on the server of what was deployed, so the baseline is rebuilt from the project log. The hash-comparison helper used in the runbook lives outside the repository. No database backup is possible from the UAT shell account; a database snapshot needs IT.

Status: Available (UAT), used for the deploys of 18 and 19 November 2026.

Smoke-test assets for later stages: `moodle-enhancement/deploy/render_smoke_53.sh` (5.3 render gate: the JavaScript, stylesheet, module loader and font URL classes must return 200 on a cache miss and when cached) and `scripts/cutover-smoke-test.py` (8 checks with JUnit output, written for the earlier 5.1 to 5.2 plan). Its usage text includes the legacy production address. The script refuses any target whose hostname contains that address at parse time, but the usage example still shows it; the example should be corrected (section 8, item 15). QA must not point it at production, which is BizLMS, not Sentientia.

### 3.12 Migration and privacy rehearsals

| Rehearsal | What it proves | Result | Status |
|---|---|---|---|
| Real-data upgrade rehearsal, 2026-10-08 (April 2026 backup, 3.5 GB, 580 tables) | Two-hop upgrade 4.1.2 to 4.5.10 to 5.3 with 47 Sentientia plugins; parity against a baseline taken before the upgrade | Hop 1: 1,344 s, 100% parity. Hop 2: 4,748 s. One difference: Moodle 5.0 removes `mod_survey` (one empty survey activity) | Built (verified on development) |
| Rehearsal kit `tools/rehearsal/` (13 steps, `run_all.sh`, `selftest.sh` 515 of 515) | Repeatable Stage B: restore check, baseline, two hops, repairs, roles, theme switch, import, parity compare, one cron cycle | Dry by default; refuses databases not on the allow-list, any name containing `prod` or `uat`, and outbound mail not disabled | Built (verified on development); the run on the target box is Planned |
| Privacy erasure rehearsal (515 automatic delete requests, local copy) | Stock Moodle data-request processing plus our providers | 286 processed, 229 not processable, 0 failed deletes, 0 notifications sent. Found a Moodle core truncation bug and two erasure gaps | Built (verified on development); supplementary provider is Built (behind a feature flag, off by default) (ADR-035) |
| UAT 5.2 to 5.3 upgrade rehearsal | Flag and data safety of the upgrade | 0 of 94 flags changed; 31 new flags OFF; no table or row lost; 0 reserved words in 673 new names | Available (UAT): executed 2026-10-21 |

### 3.13 Cross-tree drift and language parity gates

**Cross-tree drift.** Most plugins exist in two trees, the canonical `moodle-enhancement/<type>/<plugin>/` and the twin `<type>/<plugin>/`, and both are deployed from. Three local plugins (`api`, `content_market`, `talent`) and the theme have only one copy. `tools/check-tree-drift.php` compares every file in both trees (line endings normalised) and reports CONTENT, ONLY-ME and ONLY-TOP differences. Recorded differences sit in a baseline of 38 entries (25 content, 12 canonical-only, 1 twin-only). New drift fails CI. The pre-commit hook warns at commit time (check 19), and the UAT deploy tool aborts on drift. Run on 20 November 2026 for this review: no drift outside the baseline.

**Language parity.** `tools/check-lang-parity.php` fails when a component with a Hindi pack has a key set that differs from English. Run on 20 November 2026: 57 English files checked, 0 failures, 1 warning (the payment gateway has no Hindi pack). Kannada, Marathi and Swahili are not gated: they cover 81% of plugin strings and have had an AI back-translation review only, with no native-speaker review.

---

## 4. Defect classes found and the guards added

Engineering rule (ADR-027): every recurring defect class becomes an automated gate. The table lists the 17 classes with a guard. QA can use it as the seed of a defect taxonomy (section 7.10).

| # | Defect class | What happened | Guard added | Guard type |
|---|---|---|---|---|
| 1 | Unbounded tenant path prefix | `LIKE '/1%'` also matched a neighbouring tenant path (749 users counted instead of 743); shipped 4 or more times in September 2026; silent wrong numbers | `tenant::path_descendant_filter()`; pre-commit check 18; CI `path-boundary-check`; blocking `tenant_isolation` group | Static and test |
| 2 | False "no personal data" privacy declaration | 4 plugins declared no personal data while owning 9 user-keyed tables; subject-access returned nothing; erasure reported success and deleted nothing (fixed September 2026) | `privacy_coverage_test` across every plugin's schema | Test |
| 3 | Erasure incomplete | Stock data-request approval truncates the context list, so approved deletions leave data (a Moodle core bug) | Supplementary provider behind two flags; blocking `privacy_erasure` group with a canary; ADR-035 | Test and feature |
| 4 | Cross-tree drift | An edit landed in one tree only; the other copy was served and did nothing | Drift checker with baseline; check 19; deploy tool aborts | Static and deploy |
| 5 | `SITEID` type comparison | `(int)$id !== SITEID` is always true because `SITEID` is a string; the site-course guard did nothing; shipped 5 times | Check 20; CI `siteid-compare-check` | Static |
| 6 | Upgrade step below the shipped version | A step added at a version sites had already passed was skipped silently | `check-upgrade-step-versions.php` with self-test | Static |
| 7 | Dead JavaScript and template leaks | Blank dashboard charts (missing end-of-body output), stale module names, Mustache comments printed on 14 templates | Gate 0 scanners (checks 13 to 15); render smoke | Static and UI |
| 8 | JavaScript source without a build, or a stale build | 23 historical gaps drained; a stale build still passes the existence-only gate | Check 16 and CI job; manual grep of built files after renames | Static (partial) |
| 9 | Layout region declared but not printed | Block JavaScript error on the UAT dashboard | `check-theme-layout-regions.php` | Static |
| 10 | Missing message-provider string | `[[messageprovider:...]]` shown on the preferences page (3 plugins) | `check-message-provider-strings.php` | Static |
| 11 | Role-switch capability demotion | The role switcher wrote a real core role switch, demoting multi-role administrators site-wide; command-line checks masked it | Browser-level persona walks; check the session's role-switch state | Manual and UI |
| 12 | Capabilities not reaching real roles | New capabilities were granted to Moodle archetypes that the imported author and manager roles do not inherit | Archetype grants plus upgrade back-fill; persona walks | Code and UI |
| 13 | XMLDB definition traps | 333-character index limit, 1,333-character VARCHAR limit, raw `<` in a comment attribute | PHPUnit initialisation as the fresh-install gate; schema check | Test |
| 14 | MySQL 8 reserved words | Names valid on MariaDB failed on MySQL 8 at Stage A | `mysql84_reserved_scan.py` before each push | Static |
| 15 | Line endings versus git blobs | Working-tree hashes did not match committed content | Hash raw blobs; `.gitattributes` keeps LF for core patches | Deploy |
| 16 | Modal form removed before its rules start | Console TypeError on Manage Courses | Page guard; core patch (ADR-044); `modal_form_guard_test` | Code and test |
| 17 | Destructive local command | A scratch command filled a development drive | Command and permission deny rules for automated tooling | Process |

**Open item of class 2.** The payment gateway declares "no personal data" over 3 tables that have user columns (privacy finding F-1). The structural guard does not reach it. Owner: platform engineer, with the DPDP pack reviewers.

**Observations for QA.**

- Classes 1, 2, 3, 5 and 6 are **silent**: the page looks correct and the numbers or the data are wrong. Functional UI testing does not find them. They need data-level oracles and structural tests.
- Classes 5, 11 and 12 were found late: by repetition, by a browser session or by persona testing. Each argues for persona-based testing on production-shaped roles and data.

---

## 5. Coverage map

### 5.1 Coverage by area and test level

Legend: **Y** covered; **P** partial or indirect; **N** none; **M** manual only.

| Area | Unit / component (PHPUnit) | Integration (WS contract, DB, upgrade) | UI automated (Playwright, render, axe) | Persona walk / manual | Notes |
|---|---|---|---|---|---|
| Tenant isolation and scoping | Y (blocking group: about 135 files by the CI count, 183 counting every tag form; CI floor 40) | Y (path-boundary gate) | N | M | Strongest area |
| Privacy export and erasure | Y (coverage test, blocking group) | P (erasure rehearsal, local) | N | M | Payment gateway gap (F-1) |
| Feature flag resolution | Y (platform suite) | P (UAT snapshot diff) | N | M | No combinatorial testing |
| Roles and capabilities | Y | P | P (4 CI personas) | Y (11 local personas) | Real-role inheritance found only by walks |
| Commerce (cart, payment gateway, subscriptions) | Y (cart 315; gateway 33; enrol 9) | N (no gateway sandbox test recorded) | N | M (checkout not clicked on UAT) | Payment provider end-to-end: to be confirmed |
| Courses, catalogue, learning paths, programs | Y | P | P (dashboard, catalogue reachability) | Y | |
| Compliance report and recompletion | Y | P | P (compliance persona smoke) | Y | Matrix numbers need data-level oracles |
| Classroom and evaluation | Y | N | N | Y | |
| Notifications and e-mail | Y | N | N | P | Outbound mail disabled by the 5.3 configuration template; message content not visually tested |
| Live engagement | Y (148) | N | N | P | Real-time behaviour with two browsers not automated |
| AI features (mock mode) | P (ai 19, plus consumers) | N | N | M | Live mode not testable: no key or signed spend cap |
| Proctoring | Y (local proctoring plugin 6 files, 59 methods; access rule 41) | N | N | N | Proctoring base and access rule: Available (UAT). Secure assessment mode (B1/B2): Built (verified on development), behind a feature flag, off by default |
| Theme and layouts | Y (149) | Y (Gate 0 scanners) | P (render smoke on 3 layouts) | Y | Visual baselines not seeded |
| Content pipeline (SOP to SCORM) | Y (125 pytest) | Y (mock smoke) | N | N | SCORM playback in a browser not automated |
| Public API, webhooks, SCIM, LTI, xAPI | P | P (WS contract gate) | N | N | Built (behind a feature flag, off by default) |
| Upgrade and migration | P (upgrade-step gate) | Y (rehearsal kit, local) | N | M | Target-box run Planned |
| Localisation | N (en/hi key parity only) | Y (parity gate) | N | P | kn, mr, sw not gated |

### 5.2 Test pyramid today

| Level | Assets | Where it runs | Observation |
|---|---|---|---|
| Unit and component | 4,861 PHPUnit methods; 125 pytest | CI (5.2), local (5.1 and 5.3) | Broad base. Only 2 groups block. No coverage measurement |
| Integration | WS contract gate; PHPUnit initialisation as install gate; upgrade-step gate; rehearsal kit | CI and local | Upgrade testing is manual and local |
| System and end-to-end | 23 Playwright tests (advisory, 4.5 snapshot); 115 persona-journey steps (local) | CI and local | Thin and mostly local. No automated run against UAT |
| Acceptance | Scripted UAT walks; product-owner browser smoke | UAT | No written acceptance criteria per feature; no formal sign-off record |

### 5.3 Gaps

| # | Gap | Current state | Consequence | Proposal |
|---|---|---|---|---|
| C1 | Load, soak and capacity testing | Not run. Cold-page baselines only, on a workstation (May 2026) | Capacity at 3,500 users is unknown | Section 7.2, type T7; section 8 |
| C2 | Security testing | Pre-commit pattern checks; security regression tests (tenant scope) in the courses, org, reports and users external-function suites; no static analysis, dependency, secret or dynamic scanning; penetration test status **to be confirmed** (owner: Information Security lead) | Vulnerabilities outside the checklists go unseen | Section 8; security pack |
| C3 | Accessibility automation | Axe on 3 surfaces with a serious-or-critical threshold. The May 2026 axe audit found 5 serious colour-contrast issues. Token-level contrast fixes followed, but no axe re-run has been recorded since. Current status: to be confirmed (owner: QA Architect; sources `PHASE-H-A11Y-AXE-RESULTS.md`, `_tokens.scss` A11Y-7). About 35 known reduced-motion violations in the theme | WCAG 2.2 AA conformance cannot be stated | Expand surfaces; add a manual screen-reader pass |
| C4 | Mobile | Screenshots at 590 px and 390 px; one Playwright mobile spec on the login page; PWA is Built (behind a feature flag, off by default); native app Planned (ADR-040) | No real-device or mobile-browser testing | Device matrix (section 7.2) |
| C5 | Target-stack automation | No automated run on Moodle 5.3 and MySQL 8.4 | Version-specific defects reach UAT | CI job on 5.3 and MySQL 8.4 |
| C6 | Cross-browser | Playwright projects for 3 engines, advisory and on the 4.5 snapshot | Browser-specific defects unseen | Re-point lane; make render smoke blocking |
| C7 | Visual regression | Baselines not seeded | Layout regressions found by people only | Seed baselines after the UI stabilises |
| C8 | Code coverage | Not measured | Untested code paths are unknown | PCOV or Xdebug coverage in a nightly CI job |
| C9 | Flag combinations | Each flag tested on its own at best | Interaction defects | Pairwise sets for flags that share surfaces |
| C10 | Operational testing (backup, restore, disaster recovery, monitoring) | Not run; monitoring is one log-scan job on UAT | Recovery behaviour unknown | IT and DevOps pack; Stage B |

### 5.4 Proposed performance targets (from the record, not measured)

These service level objectives were proposed in `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md`. They have **not** been measured on a production-class environment.

| Surface | Proposed target |
|---|---|
| Login | under 1 s at p50 |
| Dashboard, warm | under 2 s at p50 |
| Administration index | under 3 s cold, 1 s warm |
| Catalogue | under 3 s cold, 0.5 s warm |
| Analytics | under 1 s at p95 |
| List web services | under 500 ms at p95 |
| Suspend 50 users | under 5 s |

Measured fixes on development: catalogue 5.0 s cold to 0.056 s warm (cache); organisation administration from 213 queries to 1.

---

## 6. Environments and test data

### 6.1 Environments used for testing

| Environment | Stack | Used for | Limits |
|---|---|---|---|
| Development, XAMPP | Moodle 5.1.3+, PHP 8.2.12, MariaDB 10.11.16, at `http://localhost:8080` | Daily development, persona walks, evidence, targeted PHPUnit | Two Moodle feature versions behind UAT (5.1 versus 5.3); can lag the repository (diff before testing); slow under endpoint protection |
| Development, 5.3 runtime | Moodle 5.3, PHP 8.4, MariaDB 11.4 | 5.3 compatibility gate, rehearsals, 5.3 PHPUnit | Manual use only; MariaDB, not MySQL |
| CI | PHP 8.2 (lint, Playwright); Moodle 5.2, PHP 8.3, MariaDB 10.11 (PHPUnit); Moodle 4.5 snapshot (Playwright) | Gates on every pull request and push | Not the target stack |
| UAT | Moodle 5.3, PHP 8.3.6, MySQL 8.4.9 (managed); 2 vCPU, 2 GB | Deploy verification, persona walks, product-owner acceptance | Smoke and walks only; no database backup from the shell; deploys and flag changes are product-owner decisions |
| Staging | **None** | | Stage B is the first production-shaped run |
| Production | Legacy BizLMS on Moodle 4.1.2 | Not a test target | Never patched; replaced at cutover |

**Version spread.** Testing happens on four Moodle versions (4.5 in Playwright CI, 5.1 locally, 5.2 in PHPUnit CI, 5.3 on UAT and the local runtime) and two database engines. The target is Moodle 5.3 on MySQL 8.4. Only UAT matches the target, and UAT runs no automated tests.

### 6.2 Test data

**Imported production backup.** The development databases hold a copy of the production database imported for testing only (activity up to about April 2026). UAT holds a fresh installation with synthetic test personas and sample courses, not this copy. On the development database: 2,906 registered accounts (Airpay 2,208; Public 685; ZEEA 8; unassigned 5), 423 courses and 23,348 enrolments. These are **test data**. They are not Sentientia usage and must never be reported as users or learners of Sentientia. The data contains real personal data of employees and consumers.

**Handling rules (current practice, proposed for QA sign-off).**

1. Treat the imported data as personal data under DPDP: use it only for testing on approved environments (development machines; it is not loaded on UAT).
2. Never copy it to personal devices, shared drives, tickets, chat tools or external services, including AI services and test-management SaaS (software as a service). AI-assisted review (section 3.10) receives code diffs and masked screenshots only; unmasked screenshots, logs and database content are never sent.
3. Outbound e-mail stays disabled on every test site (`$CFG->noemailever = true` is active in the 5.3 configuration template; the rehearsal kit refuses to run without it). Remove it only at the approved cutover. Whether it is set on UAT today is **to be confirmed** (owner: DevOps lead).
4. Screenshots, videos and logs follow the masking rule (section 3.9). Only synthetic personas may be readable.
5. Defect reports describe data by role and persona label, never by a real person's name, e-mail, employee id or certificate code.
6. Do not create, change or delete imported users on development copies. Test with synthetic personas. Do not click actions on UAT that change shared data (for example, submitting a quiz, approving a request or checking out) unless the test plan says so.
7. Database dumps stay inside the approved backup locations and are deleted when the test purpose ends. Retention: **to be confirmed** (owner: Head of Learning & Development, with the Data Protection Officer).

**Known data artefact.** SCORM and file links return 404 on local copies because the file store was not imported with the database. This is a data artefact, not a product defect. The migration must carry the file store.

### 6.3 Synthetic personas

| Set | Accounts | Where | Credentials |
|---|---|---|---|
| Visual-pass personas `vp_*` | Site admin, Airpay and ZEEA tenant admins, line manager with two reports, learners in Airpay and ZEEA, trainer/author, compliance officer, course author, Public learner | Local only (the script refuses non-localhost sites) | Generated; stored only in a git-ignored local file |
| CI personas `ci.*` | 4 seeded `ci.*` accounts (learner, manager, compliance, author) plus the container's install admin | Inside the CI container, destroyed after the job | Throw-away value per run |
| UAT test accounts | Persona accounts per tenant (for example an Airpay learner and an Airpay L&D administrator); full list **to be confirmed** (owner: Head of Learning & Development) | UAT | Held outside the repository; issued by the Head of Learning & Development. Must never be typed by automation on UAT |
| Earlier QA personas `qa_*` | Seven personas used for the June 2026 workflow matrix | Local Moodle 5.2 copy | Local only |

---

## 7. Proposed test strategy (mapped against ISO/IEC/IEEE 29119)

Status of this whole section: **Planned (proposed in this document)**. The QA team owns the final version. Section 7.13 maps the proposals to the 29119-3 documentation set and section 7.14 to the 29119-2 processes.

### 7.1 Test policy (proposed statements)

1. Testing is risk-based. Risks R1 to R12 (section 2.3) drive the test effort, and the QA team re-rates them each release.
2. Every recurring defect class gets an automated guard (continuing ADR-027).
3. Tests run as the persona who uses the feature, never only as a site administrator.
4. Every feature is tested with its flag at the default and in the state a release will turn on.
5. Test data that came from production is personal data. The handling rules in section 6.2 apply.
6. Exit decisions are based on recorded evidence: CI results, evidence READMEs and signed test completion reports.
7. Product engineering and QA share quality. QA owns the strategy, the acceptance criteria and the sign-off evidence.

### 7.2 Test levels and test types

**Levels.**

| Level | Scope | Owner (proposed) | Basis | Environment |
|---|---|---|---|---|
| L1 Unit and component | Classes, external functions, tasks, privacy providers of one plugin | Engineering | Code, state cards, ADRs | Local; CI |
| L2 Component integration | Web-service contracts, database install and upgrade, cross-plugin events and observers, flag resolution, the two plugin trees | Engineering, QA reviews | ADR-009, `db/` definitions | CI (target: 5.3 and MySQL 8.4) |
| L3 System | Persona journeys end to end on a full site; cross-browser; accessibility; localisation | QA | Persona journeys, coverage matrix | Development 5.3; CI; staging (proposed) |
| L4 Acceptance | User acceptance (business workflows per persona and tenant), operational acceptance (deploy, rollback, backup, monitoring), migration acceptance (parity) | QA leads, product owner signs | Acceptance criteria per feature; Stage B parity baseline | UAT; Stage B box |

**Types.**

| # | Type | Technique (29119-4) | Level | Current state | Proposal |
|---|---|---|---|---|---|
| T1 | Functional | Equivalence partitioning, boundary values, decision tables | L1 to L4 | Strong at L1 | Write acceptance criteria per feature for L4 |
| T2 | Tenant isolation and authorisation | Decision tables (persona x tenant x capability); boundary values on paths (`/1` versus `/10`, `/1/2` versus `/1/20`) | L1 to L3 | Blocking group | Add a cross-tenant negative suite at L3 for each list, report and export |
| T3 | Regression | Risk-based selection | All | 2 blocking groups | Tiers R0 to R4 (section 7.6) |
| T4 | Feature flag configuration | Pairwise combinations of flags that share a surface; state transition (OFF, ON, back to OFF) | L1 to L3 | Snapshot diff on deploy | Flag matrix per release |
| T5 | Data migration and upgrade | Data-level comparison against the baseline | L2, L4 | Rehearsal kit | Stage B with 100% parity as an exit criterion |
| T6 | Privacy (DPDP) | Scenario testing of access, correction, erasure, consent | L1, L4 | Coverage test, erasure rehearsal | Repeat the erasure rehearsal on Stage B data |
| T7 | Performance, load, soak | Workload model from the 3,500-user target and 3 tenants | L3, L4 | Not run | Production-class environment; tooling in section 8 |
| T8 | Security | Checklist-based and exploratory; static and dynamic scanning | L1 to L4 | Partial | Section 8; scope from the security pack (OWASP ASVS level 2) |
| T9 | Accessibility | Automated axe plus manual review against WCAG 2.2 AA | L3 | 3 surfaces | Every persona journey page; one screen-reader pass per release |
| T10 | Compatibility | Browser and device matrix | L3 | Advisory, 3 engines | Matrix below |
| T11 | Localisation | Completeness and layout checks per language | L3 | en/hi parity | Gate kn, mr, sw completeness; native-speaker review for any language before enabling it for a tenant |
| T12 | Usability and visual | Heuristic review, screenshot review | L3 | Evidence ledger | Keep; add seeded visual baselines |
| T13 | Operational | Deploy, rollback, restore, cron, monitoring | L4 | Runbooks | Rehearse rollback and restore on staging |

**Proposed compatibility matrix (to be confirmed with the product owner from analytics of the current site).** Desktop: Chrome, Edge, Firefox (latest two versions) and Safari (latest). Mobile: Chrome on Android and Safari on iOS (latest two major versions), at 390 px and 590 px widths.

### 7.3 Test pyramid targets (proposed)

| Level | Target |
|---|---|
| Unit and component | Every component has tests for its external functions, privacy provider and scheduled tasks. Raise the thinnest suites (lifecycle, pages, gamification, content_market, talent, reports, ai, blocks) first. Set a line-coverage baseline once measured; the QA team sets targets after the first measurement |
| Integration | 100% of web-service functions under the contract gate; install and upgrade from the UAT version tested on 5.3 and MySQL 8.4 in CI |
| System | All 115 persona steps automated and runnable against a Moodle 5.3 site; render smoke and axe blocking on curated surfaces |
| Acceptance | One acceptance checklist per persona per tenant, executed on UAT before sign-off |

### 7.4 Environment strategy

| Environment | Testing done there (proposed) | Change needed |
|---|---|---|
| Development | L1, targeted L2, evidence capture | Move day-to-day testing to the 5.3 runtime |
| CI | R0 to R2 (section 7.6) on every change; nightly full suite with coverage | Add a 5.3 and MySQL 8.4 job; widen the paths filter |
| UAT | L4 user acceptance, deploy verification, persona walks | Record deployed commit on the server; automated smoke after each deploy |
| Staging (proposed) | Production-class sizing for Stage B, performance, operational acceptance, restore tests | Requires IT provisioning (an IT request has been drafted, not sent) |
| Production | Post-cutover smoke and monitoring only | Defined in the cutover guide |

### 7.5 Entry and exit criteria

| Stage | Entry criteria | Exit criteria |
|---|---|---|
| Merge to `production` | Pre-commit hook clean; change has tests; evidence README for UI changes | All blocking CI jobs green; no new tree drift; en/hi parity; AI-assisted review findings resolved or recorded; product-owner approval |
| UAT deploy | Commit green in CI; reserved-word scan PASS; file list approved; pre-flight probe and flag snapshot taken | Deployed files equal git blobs; probe diff as expected; flag diff only new flags OFF; no pending upgrade; server and browser smoke pass |
| UAT acceptance (per release) | Deploy exit criteria met; acceptance checklists written; test accounts issued | All persona checklists executed; 0 open S1 and S2 defects; S3 defects have an agreed plan; regression tier R3 pass; flag configuration per tenant agreed; test completion report signed |
| Stage B | UAT sign-off; fresh live backup with checksums; production-class box | 100% parity on the baseline counts; one clean cron cycle; performance targets measured; erasure rehearsal repeated; rollback rehearsed |
| Cutover go / no-go | Stage B exit met; cutover guide gates prepared; support rota | Go decision recorded by the product owner with QA's test completion report |

### 7.6 Regression suite (proposed tiers)

| Tier | Content | Trigger | Gate type |
|---|---|---|---|
| R0 | Pre-commit checks and repository gates | Every commit and push | Blocking |
| R1 | `tenant_isolation` and `privacy_erasure` groups; pytest | Every push | Blocking |
| R2 | Full PHPUnit suite on 5.3 and MySQL 8.4; render smoke; axe smoke | Nightly, and before any UAT deploy | Blocking once failures are drained |
| R3 | All persona journeys (115 steps) on a 5.3 site; flag matrix for flags changing in the release | Before each UAT deploy | Blocking for the deploy |
| R4 | Full acceptance checklists on UAT; migration parity; performance run | Before UAT sign-off and before Stage B | Blocking for sign-off |

Selection rule: every S1 or S2 defect adds a test at the lowest level that can detect it, and the test joins the tier that runs on every change to that component.

### 7.7 Feature flag testing

- Test each new flag at its default (OFF for 129 of 142 flags) to prove that existing behaviour is unchanged.
- Test it ON at the scope a release will use (global, customer or tenant). Test it OFF again to prove that turning it off is a clean rollback.
- On deploy, the flag snapshot diff must show only new flags, OFF, with no change to existing values.
- Keep the 13 default-ON flags in the default regression pack. One of them (`sentientia.catalog.public_lxp.enabled`) has a description that says OFF while the code default is ON: **to be confirmed** (owner: platform engineer).
- Flag state on UAT is not in the repository. A switchboard export per release is **to be confirmed** (owner: Head of Learning & Development).

### 7.8 Release gates (proposed)

| Gate | Decision | Evidence required | Decision owner |
|---|---|---|---|
| RG1 Merge | Change may enter `production` | CI green; review findings resolved; evidence | Product owner |
| RG2 UAT deploy | Commit may go to UAT | RG1; R3 pass; deploy entry criteria | Product owner, QA advises |
| RG3 UAT sign-off | Release accepted | UAT acceptance exit criteria; test completion report | Product owner and QA Architect |
| RG4 Stage B pass | Migration proven | Stage B exit criteria | Product owner, DevOps lead, QA Architect |
| RG5 Cutover go | Replace BizLMS | RG4; cutover guide gates ready; rollback tested | Product owner, with IT and Information Security |
| RG6 Post-cutover | Hypercare ends | Smoke and monitoring clean for an agreed period | Product owner |

### 7.9 UAT sign-off process (proposed)

1. QA writes acceptance checklists per persona and tenant from the persona journeys and the feature list, with a status label for each feature.
2. The product owner agrees the flag configuration per tenant for the release.
3. QA executes the checklists on UAT with the UAT test accounts, records PASS, FAIL or BLOCKED per step, and captures masked evidence.
4. Defects are logged and triaged (section 7.10). S1 and S2 defects are fixed and re-tested on UAT.
5. QA issues a test completion report (29119-3): scope, results, open defects, residual risks, recommendation.
6. The product owner and the QA Architect sign. The signed report is stored in the repository next to the release's evidence.

### 7.10 Defect workflow and severity scheme (proposed)

**Severity** (impact on the product, set by QA):

| Severity | Definition | Examples from the record |
|---|---|---|
| S1 Critical | Data exposure across tenants, personal-data leak, incomplete erasure reported as complete, data loss or corruption, exploitable security flaw, failed upgrade or migration, site unusable | Unbounded path prefix that leaks another tenant's users; false "no personal data" declaration |
| S2 Major | A persona cannot complete a key workflow, or the system shows wrong figures, with no reasonable work-around | Multi-role administrators demoted site-wide; compliance numbers wrong |
| S3 Minor | Function impaired with a work-around; non-key workflow defect; localisation error | Missing message-provider string shown to users |
| S4 Cosmetic | Visual or text issue with no functional effect | Spacing or alignment off-brand |

Silent wrong numbers are rated at least S2, even when the page looks correct.

**Priority** (urgency, set at triage by the product owner with QA): P1 fix before the next deploy; P2 fix in the current release; P3 schedule; P4 backlog. The legacy audit labels (audit-P0, audit-P1, audit-P2) used in earlier audit documents are severities, not priorities: they map to S1, S2 and S3. They are converted to the S scale, and given a priority at triage, when items are migrated into the defect tool.

**Workflow states.** New, Triaged, In progress, Fixed (on a branch), Verified on development, Deployed to UAT, Verified on UAT, Closed. Side states: Rejected, Duplicate, Deferred (with a reason and a review date).

**Rules.**

- Every defect records: environment and Moodle version, persona and tenant, flag state, steps, expected and actual result, masked evidence, and defect class from section 4 (or "new class").
- An S1 or S2 defect needs a root cause and a test that would have caught it. A defect in a known class whose guard should have caught it triggers a review of that guard.
- Findings from AI-assisted review that are not fixed in the same change are logged as defects, so they are not lost in project notes.
- A tool is needed. Today defects are recorded in `PROJECT-STATE.md`, audit documents and state cards (section 8, tool choice).

### 7.11 Metrics (proposed)

| Metric | Definition | Source |
|---|---|---|
| Gate pass rate | Share of CI runs green per job | GitHub Actions |
| Time to green | Time from a red blocking job to green | GitHub Actions |
| Flaky test rate | Tests that fail and then pass with no code change, per 100 runs | JUnit artefacts |
| Test inventory | Test methods per component (static count) and executed cases per run | `grep`; JUnit |
| Code coverage | Line coverage per component, once measured | Nightly coverage job |
| Defects by level found | Share of defects found at L1, L2, L3, L4 and after release | Defect tool |
| Escape rate | Defects found on UAT or later divided by all defects for the release | Defect tool |
| Class recurrence | Defects in a class that already has a guard | Defect tool; section 4 |
| Persona journey result | PASS, FAIL, BLOCKED counts per persona | Evidence READMEs |
| Deploy verification result | Hash, probe and flag-diff outcome per UAT deploy | Deploy records |
| Change failure rate | UAT deploys that needed a rollback or hot fix | Deploy records |

### 7.12 Traceability (proposed)

A traceability matrix links each feature (from the state cards and ADRs) to its acceptance criteria, its tests (PHPUnit file, journey step, Playwright spec) and its flag. The workflow matrix and the coverage matrix are the starting points. The QA team maintains the matrix in the chosen test-management tool, with a copy in the repository at each release.

### 7.13 Test documentation set (ISO/IEC/IEEE 29119-3)

| Document | Status | Starting material |
|---|---|---|
| Test policy | Planned | Section 7.1 |
| Organisational test strategy | Planned | Section 7 |
| Test plan per release | Planned | Release scope, flag plan |
| Test design and test case specifications | Partly exists | Persona journeys, workflow matrix, PHPUnit suites |
| Test data and environment requirements | Partly exists | Section 6 |
| Test execution logs | Exists | CI artefacts, evidence READMEs |
| Incident (defect) reports | Planned | Section 7.10 |
| Test completion report | Planned | Section 7.9 |

### 7.14 Mapping to the ISO/IEC/IEEE 29119-2 test processes

| 29119-2 process | Where this document addresses it | Status | Proposed owner |
|---|---|---|---|
| Organisational test process: test policy | Section 7.1 | Planned | QA Architect |
| Organisational test process: organisational test strategy | Sections 7.2 to 7.8 | Planned | QA Architect |
| Test management: test planning | Section 7.13 (test plan per release); release scope and flag plan (section 7.7) | Planned | No owner yet (ask A3) |
| Test management: test monitoring and control | Section 7.11 (metrics); section 7.5 (entry and exit criteria) | Planned | No owner yet (ask A3) |
| Test management: test completion | Section 7.9 (test completion report and sign-off) | Planned | QA Architect, with the product owner |
| Dynamic test: test design and implementation | Sections 7.2 (techniques), 7.6 (regression tiers), 7.12 (traceability) | Partly exists (persona journeys, PHPUnit suites); the rest Planned | QA team, with engineering for L1 and L2 |
| Dynamic test: test environment set-up and maintenance | Section 7.4; section 6.1 | Partly exists (development, CI, UAT); staging Planned | DevOps lead |
| Dynamic test: test execution | Sections 3.3 to 3.8 (current); section 7.6 (proposed tiers) | Exists for L1 and CI gates; QA-led execution Planned | QA team |
| Dynamic test: test incident reporting | Section 7.10 | Planned (no defect tool yet) | No owner yet (asks A3 and A4) |

---

## 8. Tooling recommendations

Effort figures are **estimates** by the review, for one engineer familiar with the stack, and exclude the time to fix failures the new checks find.

| # | Recommendation | Closes gap | Priority | Effort (estimate) |
|---|---|---|---|---|
| 1 | Add a CI PHPUnit job on Moodle 5.3 with MySQL 8.4 (keep 5.2 until cutover) | G1, C5, R3 | High | 2 to 3 days |
| 2 | Drain full-suite failures, then make the full suite blocking | G2 | High | Depends on failure count; measure first |
| 3 | Widen the `ci.yml` paths filter to the twin trees and `tools/`; run PR-only checks on push as well | G4, G5 | High | 0.5 day |
| 4 | Re-point Playwright to Moodle 5.3 with the Sentientia theme; make render smoke and axe blocking; port the 115 persona steps to a CI-runnable form | G3, C6 | High | 1 to 2 weeks |
| 5 | Load testing: Moodle's test-site generator (`admin/tool/generator`) with a workload model for 3,500 users and 3 tenants, run with Apache JMeter or Grafana k6 (open-source load-testing tools) on a production-class box | C1, R7 | High (before Stage B) | 1 to 2 weeks, plus environment |
| 6 | Static analysis and standards: Moodle coding standard (`moodle-cs`, run by PHP_CodeSniffer, `phpcs`) and PHPStan (a PHP static-analysis tool), introduced with a baseline file so only new findings fail | G6, C2 | Medium | 2 to 4 days |
| 7 | Dependency and secret scanning: `composer audit`, `npm audit`, GitHub dependency alerts, a secret scanner | C2 | Medium | 1 day |
| 8 | Dynamic scan of UAT (for example the OWASP ZAP (Zed Attack Proxy) baseline scan), with written approval for each run, scoped by the security pack | C2, R8 | Medium | 2 days per cycle |
| 9 | Code coverage (PCOV) in a nightly job, published as an artefact | C8 | Medium | 1 day |
| 10 | Seed visual baselines once the UI freezes; keep the masking rule for baseline images | C7 | Medium | 2 days |
| 11 | Accessibility: first, re-run the May 2026 axe audit on the current theme to establish whether the 5 serious contrast issues are closed; then extend axe to every journey page; add one manual screen-reader pass per release | C3, R9 | Medium (the re-run: High) | 0.5 day for the re-run; 3 days, then per release |
| 12 | Mobile: real-device or device-cloud runs of the persona journeys at 390 px | C4 | Medium | 3 days |
| 13 | Defect and test-management tool (for example Jira with a test add-on, or a dedicated test-management tool). Personal data must not be stored in it | Section 7.10 | High | Selection by QA |
| 14 | Deploy record on UAT: write the deployed commit and file hash list to a marker file on each deploy; bring the hash-comparison helper into the repository | Section 3.11 | Medium | 0.5 day |
| 15 | Refresh out-of-date QA documents: `PHPUNIT-RUNBOOK.md` (names retired plugins), `docs/ci/PHPUNIT-GATE.md` (PostgreSQL and PHP 8.2), the Playwright README (`/moodle` prefix), the visual-evidence README (widths, retired theme, no masking rule), the pull-request template (names the wrong flag API location: `local_sentientia_core` instead of `\local_sentientia_platform\feature_flags`), and the usage example of `scripts/cutover-smoke-test.py` (shows the legacy production address, which the script itself refuses) | Onboarding | High | 1 day |
| 16 | Gate Kannada, Marathi and Swahili completeness (report-only first) | Localisation | Low | 0.5 day |
| 17 | Strengthen the JavaScript build gate from existence to freshness (rebuild and compare) | Defect class 8 | Low | 1 day |
| 18 | Consider Moodle Behat for acceptance scenarios that need Moodle's own fixtures and steps | L3 and L4 | Low (evaluate) | Evaluation 2 days |

---

## 9. Handover plan for the QA team

### 9.1 Day 1: what QA can run

**With repository read access, PHP 8.2 or later, Python 3.11 and Git Bash, no Moodle site needed:**

```bash
php tools/check-path-boundary.php
php tools/check-siteid-compare.php
php tools/check-tree-drift.php
php tools/check-lang-parity.php
php tools/check-message-provider-strings.php
php tools/check-theme-layout-regions.php
php tools/check-upgrade-step-versions.php --base origin/production
php moodle-enhancement/tools/ci-ws-contract-check.php --verbose
bash tools/check-core-patches.sh check
pip install -r requirements.txt
python -m pytest tests/agents/ -v
python scripts/agents/run_pipeline_test.py
```

Some scripts scan the whole repository and take more than a minute on the development machine.

**With GitHub access:** read the last CI runs and their annotations and artefacts; download the PHPUnit JUnit XML and the Playwright report.

**Reading list for day 1:** this document; ADR-027 (quality gates); `moodle-enhancement/docs/COVERAGE-MATRIX.md`; `moodle-enhancement/docs/testing/WORKFLOW-TEST-MATRIX.md`; the latest evidence README (`moodle-enhancement/docs/visual-evidence/2026-11-19/session-01/README.md`); `UAT-DEPLOY-RUNBOOK-2026-11-18.md`.

### 9.2 Week 1 and beyond

| When | Activity | Prerequisite |
|---|---|---|
| Week 1 | Local Moodle 5.3 site; PHPUnit initialisation and the two blocking groups; Playwright suite on the local site; provision `vp_*` personas; run the persona journeys | Development machine with administrator rights or a prepared image; signed test-data rules |
| Week 1 | Re-run the axe audit on the current theme (the surfaces of the May 2026 audit) on a local site and record the result | Local site; masked evidence |
| Week 1 | Shadow one UAT deploy verification (read-only role) | Product-owner approval |
| Weeks 2 to 3 | Write acceptance checklists per persona and tenant; set up the defect tool; load and triage the known open defects (Appendix G, a starting list) and extract the rest of the backlog from the project log, evidence READMEs and audit documents (owner of the extraction: platform engineer, with QA) | Tool chosen |
| Weeks 3 to 4 | First QA-led UAT regression (R4) on the current UAT commit; first test completion report | UAT test accounts |
| Month 2 | Take over R2 and R3 ownership; start the 5.3 CI job and the Playwright re-point with engineering | |
| Month 3 | Load-test plan and run on a production-class box; security and accessibility programmes with the respective packs | Staging or Stage B box |

### 9.3 Access needed

| Access | Purpose | Approver (role) |
|---|---|---|
| GitHub repository, read, with Actions | Code, CI results, artefacts | Head of Learning & Development |
| Development machine able to run Moodle 5.3, PHP 8.3 or later, MySQL 8.4 or MariaDB, Node.js and Python | Local test runs | IT |
| A copy of the test database | Local system testing | Head of Learning & Development, after the test-data rules are signed |
| UAT persona test accounts | Acceptance testing | Head of Learning & Development |
| UAT feature-flag switchboard, read-only view | Record flag state per release | Head of Learning & Development |
| VPN and server access for UAT | Only for deploy verification, read-only steps | Product owner and IT; not needed on day 1 |
| Defect and test-management tool | Defect workflow, traceability | QA Architect |

### 9.4 Training (proposed sessions)

| # | Topic | Length | Material |
|---|---|---|---|
| 1 | Product and tenant model; personas; feature flags | 2 h | Pack 03 (CTO product guide); `_facts/20-flags-roles.md` |
| 2 | Moodle for testers: capabilities, roles, contexts, upgrades, PHPUnit and Behat basics | 3 h | Moodle developer documentation |
| 3 | Our gates and CI: what each check catches, how to read failures | 2 h | Sections 3.2 to 3.4 |
| 4 | Defect classes and data-level oracles (silent wrong numbers) | 2 h | Section 4 |
| 5 | Persona walks, visual evidence and masking | 2 h | Sections 3.8 and 3.9 |
| 6 | UAT deploy verification and stop rules | 1.5 h | Section 3.11; UAT runbook |
| 7 | Test-data handling under DPDP | 1 h | Section 6.2; DPDP pack |

### 9.5 Responsibility split (proposed)

| Activity | Engineering | QA | Product owner |
|---|---|---|---|
| L1 tests and repository gates | Responsible | Reviews | |
| CI pipeline | Responsible | Consulted on gate policy | |
| L3 persona and cross-browser testing | Supports | Responsible | Informed |
| Acceptance checklists and UAT execution | Supports | Responsible | Accountable |
| Defect triage | Consulted | Responsible | Accountable for priority |
| Release gates RG2 to RG5 | Consulted | Recommends | Accountable |

---

## 10. Asks and next steps

| # | Ask of the QA Architect | By | Form |
|---|---|---|---|
| A1 | Review this document; accept or amend sections 7.1 to 7.14 | 4 December 2026 | Comments on this document, then a signed version 1.1 |
| A2 | Accept or amend the severity scheme and defect workflow (section 7.10) | 4 December 2026 | Written decision |
| A3 | Name QA owners for regression, UAT sign-off and defect triage | 4 December 2026 | Names and roles to the Head of Learning & Development |
| A4 | Choose the defect and test-management tool, with a rule that no personal data is stored in it | 11 December 2026 | Tool decision |
| A5 | Sign, and have each QA team member sign, the test-data handling rules (section 6.2) before access is granted | Before any data access | Signed acknowledgement |
| A6 | Agree the compatibility matrix (section 7.2) | 11 December 2026 | Written decision |
| A7 | Agree priorities for the tooling list (section 8), in particular items 1, 3, 4 and 5 before Stage B | 11 December 2026 | Prioritised list |
| A8 | Schedule the training sessions in section 9.4 | 11 December 2026 | Calendar |

**Next steps on the engineering side** (proposed, subject to the product owner): finish the PHPUnit run for the 20 November changes; refresh the out-of-date QA documents (section 8, item 15); add the 5.3 CI job; widen the CI paths filter.

---

## 11. Risks and open questions

| # | Risk or question | Owner (role) | Proposed resolution |
|---|---|---|---|
| 1 | Defects specific to Moodle 5.3 or MySQL 8.4 reach UAT unseen, because no automated test runs on the target stack | Platform engineer | Tooling item 1; R2 tier on 5.3 before each UAT deploy |
| 2 | Performance at 3,500 users is unknown; the first production-class figures would come at Stage B | DevOps lead | Staging or an early Stage B box; tooling item 5 |
| 3 | Personal data in test data leaks through screenshots, logs, exports or tools | QA Architect, with the Data Protection Officer | Section 6.2 rules signed; review pre-October evidence folders |
| 4 | The 20 November development changes have no finished PHPUnit run | Platform engineer | Complete the run before those changes go to UAT |
| 5 | Only 2 PHPUnit groups block merges; full-suite failures can accumulate | Platform engineer | Tooling item 2 |
| 6 | Direct pushes to `production` skip pull-request-only checks | Head of Learning & Development | Use pull requests for release changes, or run those checks on push |
| 7 | No staging environment; UAT is undersized for anything beyond smoke tests | IT, DevOps lead | Send the drafted IT request; provision a production-class box |
| 8 | Security testing beyond pattern checks is absent; penetration-test status unknown | Information Security lead | Tooling items 6 to 8; confirm the external review plan in the security pack |
| 9 | UAT flag state is not recorded in the repository | Head of Learning & Development | Switchboard export per release, stored with the release evidence |
| 10 | Payment-gateway privacy declaration does not match its tables (F-1); the guard does not reach it | Platform engineer | Fix the provider; extend the coverage test to the payment-gateway plugin type |
| 11 | Payment provider end-to-end testing (sandbox mode) is not recorded | Platform engineer, Finance | Confirm whether a provider sandbox exists; add a test plan |
| 12 | Kannada, Marathi and Swahili strings have no native-speaker review | Head of Learning & Development | Native review before enabling a language for a tenant |
| 13 | AI features cannot be tested in live mode (no provider key or signed spend cap; the AI operating budget is pending C-suite approval) | Finance, with the Head of Learning & Development | Test mock mode now; plan live-mode tests when the cap is signed |
| 14 | Hash-comparison helper used for UAT verification is outside the repository | Platform engineer | Tooling item 14 |
| 15 | Count of `tenant_isolation`-tagged files differs by method: about 135 by the CI sanity step's literal match, 183 when every tag form PHPUnit reads is counted. The sanity step under-counts files tagged with aligned spacing or the attribute form | Platform engineer | Publish the count from the CI sanity step in each run, and make the sanity step use a whitespace-tolerant pattern that also matches the attribute form |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record, in `moodle-enhancement/docs/adr/` |
| AMD | Asynchronous Module Definition, the JavaScript module format Moodle uses; `amd/src` holds sources, `amd/build` the built files |
| axe-core | An open-source accessibility testing engine used through Playwright |
| Behat | Moodle's behaviour-driven acceptance test framework |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today |
| BLOCKED | Evidence result: step not checked, with the reason |
| Canonical tree / twin tree | `moodle-enhancement/<type>/<plugin>/` and `<type>/<plugin>/`; both deployed, kept byte-identical by the drift gate |
| CI | Continuous integration: automated checks on each change, here GitHub Actions |
| Core mod | A change to Moodle core or a vendored plugin, recorded in `docs/core-mods/` |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| E2E | End-to-end testing through the user interface |
| GUI | Graphical user interface |
| HTTP | Hypertext Transfer Protocol, the protocol web pages are served over |
| JMeter (Apache JMeter) | An open-source load-testing tool |
| JSON | JavaScript Object Notation, a structured text data format |
| Feature flag | Registered on/off switch with a default, overridable globally, per customer or per tenant |
| Gate 0 to Gate 3 | ADR-027 quality gates: static lint, render smoke, visual and accessibility, coverage matrix |
| JUnit XML | A standard test-result file format read by CI tools |
| k6 (Grafana k6) | An open-source, script-driven load-testing tool |
| KPI | Key performance indicator |
| L&D | Learning and Development |
| LTS | Long-term support release |
| MariaDB | An open-source relational database that forked from MySQL; used on development machines and in CI |
| moodle-cs | Moodle's coding-standard rule set for PHP_CodeSniffer |
| Mustache | The logic-less HTML template language Moodle uses for page output (`.mustache` files) |
| PHPUnit | The PHP unit-test framework Moodle uses |
| PHP_CodeSniffer (phpcs) | A tool that checks PHP code against a coding standard |
| PHPStan | A static-analysis tool that finds type and logic errors in PHP without running it |
| OWASP, ASVS | Open Worldwide Application Security Project; its Application Security Verification Standard |
| p50, p95 | The 50th and 95th percentile of measured response times |
| PCOV | A PHP extension that measures code coverage for PHPUnit |
| PWA | Progressive Web App: an installable web application shell |
| Persona | A role-based test user archetype (learner, line manager, trainer, author, L&D administrator, compliance officer, site administrator and others) |
| Playwright | A browser automation framework for Chromium, Firefox and WebKit |
| Privacy provider | A plugin class that tells Moodle's privacy API what personal data the plugin holds |
| QA | Quality assurance |
| SaaS | Software as a service: software hosted by a vendor and used over the internet |
| SCORM | Sharable Content Object Reference Model, an e-learning packaging standard |
| SCSS | Sass stylesheet source files, compiled to CSS for the theme |
| SHA-256 | A cryptographic hash function; equal hashes show that two files have the same content |
| SOP | Standard operating procedure document, the input of the content pipeline |
| Service level objective (SLO) | A target value for a measured service level, such as page response time |
| SITEID | Moodle constant for the site course id, returned by the database as a string |
| Stage A / Stage B | Fresh install on UAT / rehearsal of the live-backup upgrade (4.1.2 to 4.5 to 5.3) with a 100% parity check |
| State card | Per-plugin status note in `moodle-enhancement/state-cards/` |
| Tenant | A top-level organisation tree: Airpay (1), Public (77), ZEEA (177) |
| UAT | User acceptance test environment, academy2.airpay.ninja |
| VPN | Virtual private network; needed to reach the UAT server |
| WCAG | Web Content Accessibility Guidelines |
| WS | Web service (Moodle external function) |
| XMLDB | Moodle's database schema definition format (`db/install.xml`) |
| XAMPP | A bundled Apache, MariaDB and PHP stack; runs the local development site |
| ZAP (OWASP ZAP) | Zed Attack Proxy, an open-source dynamic web-application security scanner |

## Appendix B. References

### B.1 Repository

| Item | Path |
|---|---|
| Pre-commit hook | `.claude/hooks/pre-commit.sh` |
| CI workflows | `.github/workflows/ci.yml`, `core-mods.yml`, `deploy-production.yml` |
| Pull-request template | `.github/PULL_REQUEST_TEMPLATE.md` |
| Repository gates | `tools/check-*.php`, `tools/check-core-patches.sh`, `tools/ci/` |
| Scanners | `moodle-enhancement/tools/scan_*.php`, `moodle-enhancement/tools/ci-ws-contract-check.php` |
| UAT tools | `tools/uat/deploy_to_uat.sh`, `tools/uat/flag_snapshot.php`, `tools/uat/mysql84_reserved_scan.py` |
| Rehearsal kit | `tools/rehearsal/` |
| Playwright suite | `tests/playwright/` |
| Pipeline tests | `tests/agents/`, `scripts/agents/run_pipeline_test.py` |
| Visual-pass toolkit | `moodle-enhancement/tools/visual-pass/` |
| Link stress harness | `moodle-enhancement/tools/gap-test/` |
| Smoke tests | `moodle-enhancement/deploy/render_smoke_53.sh`, `scripts/cutover-smoke-test.py` |
| AI-assisted review definitions | `.claude/agents/code-reviewer.md`, `.claude/commands/pr-review.md` |
| PHPUnit guides | `moodle-enhancement/PHPUNIT-RUNBOOK.md`, `moodle-enhancement/docs/ci/PHPUNIT-GATE.md`, `theme/sentientia/tests/README.md` |
| Playwright gate guide | `docs/ci/PLAYWRIGHT-GATE.md` |
| Coverage and workflow matrices | `moodle-enhancement/docs/COVERAGE-MATRIX.md`, `moodle-enhancement/docs/testing/WORKFLOW-TEST-MATRIX.md` |
| Accessibility audit | `moodle-enhancement/PHASE-H-A11Y-AUDIT.md`, `moodle-enhancement/PHASE-H-A11Y-AXE-RESULTS.md` |
| Performance baseline | `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md` |
| Visual evidence | `moodle-enhancement/docs/visual-evidence/` |
| Cutover and UAT runbooks | `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md`, `MIGRATION-REHEARSAL-RUNBOOK.md`, `SENTIENTIA-CUTOVER-MASTER.md`, `A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Upgrade rehearsals | `moodle-enhancement/docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md`, `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |
| Project log | `moodle-enhancement/PROJECT-STATE.md` |
| Fact base | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/` |

### B.2 Architecture Decision Records

| ADR | Title |
|---|---|
| ADR-009 | Detection consistency and web-service contract invariants (Accepted) |
| ADR-018 | Sentientia independence and stabilisation roadmap (Accepted) |
| ADR-027 | Quality gate system (Accepted) |
| ADR-031 | Cross-tenant authority (Accepted) |
| ADR-032 | BizLMS data import (Proposed) |
| ADR-033 | Target Moodle 5.3 LTS (Accepted, subject to the compatibility gate) |
| ADR-035 | Privacy erasure completeness (Accepted) |
| ADR-040 | Native mobile app (Proposed) |
| ADR-044 | Modal form dependency guard (Accepted) |

### B.3 External standards

The links below were listed on 20 November 2026 from the official publisher pages. They were not fetched for this review, which was prepared without web access; QA should open each link once before citing it externally.

| Standard | URL | Access note |
|---|---|---|
| ISO/IEC/IEEE 29119-1:2022, Software testing, Part 1: General concepts | https://www.iso.org/standard/81291.html | Listed 2026-11-20; not fetched |
| ISO/IEC/IEEE 29119-2:2021, Part 2: Test processes | https://www.iso.org/standard/79428.html | Listed 2026-11-20; not fetched |
| ISO/IEC/IEEE 29119-3:2021, Part 3: Test documentation | https://www.iso.org/standard/79429.html | Listed 2026-11-20; not fetched |
| ISO/IEC/IEEE 29119-4:2021, Part 4: Test techniques | https://www.iso.org/standard/79430.html | Listed 2026-11-20; not fetched |
| W3C Web Content Accessibility Guidelines (WCAG) 2.2 | https://www.w3.org/TR/WCAG22/ | Listed 2026-11-20; not fetched |
| OWASP Application Security Verification Standard (ASVS) | https://owasp.org/www-project-application-security-verification-standard/ | Listed 2026-11-20; not fetched |
| OWASP Top 10 (2021) | https://owasp.org/Top10/ | Listed 2026-11-20; not fetched |

### B.4 Tools named in this document (official project pages)

Same access note as B.3: listed on 20 November 2026, not fetched.

| Tool | Used or recommended in | URL |
|---|---|---|
| Moodle PHPUnit integration | Section 3.5 | https://moodledev.io/general/development/tools/phpunit |
| Moodle Behat | Section 8, item 18 | https://moodledev.io/general/development/tools/behat |
| Moodle test-site generator (`admin/tool/generator`, part of Moodle core) | Section 8, item 5 | https://github.com/moodle/moodle (path `public/admin/tool/generator` on Moodle 5.1 and later) |
| Playwright | Section 3.7 | https://playwright.dev/ |
| axe-core | Sections 3.7 and 8, item 11 | https://github.com/dequelabs/axe-core |
| moodle-cs | Section 8, item 6 | https://github.com/moodlehq/moodle-cs |
| PHP_CodeSniffer | Section 8, item 6 | https://github.com/PHPCSStandards/PHP_CodeSniffer |
| PHPStan | Section 8, item 6 | https://phpstan.org/ |
| Apache JMeter | Section 8, item 5 | https://jmeter.apache.org/ |
| Grafana k6 | Section 8, item 5 | https://grafana.com/docs/k6/latest/ |
| OWASP ZAP | Section 8, item 8 | https://www.zaproxy.org/ |
| PCOV | Section 8, item 9 | https://github.com/krakjoe/pcov |
| Xdebug (coverage alternative) | Section 5.3, gap C8 | https://xdebug.org/ |
| Composer `audit` command | Section 8, item 7 | https://getcomposer.org/doc/03-cli.md#audit |
| npm `audit` command | Section 8, item 7 | https://docs.npmjs.com/cli/commands/npm-audit |

## Appendix C. Command quick reference

| Task | Command (repository root, Git Bash) |
|---|---|
| Install the pre-commit hook | `cp .claude/hooks/pre-commit.sh .git/hooks/pre-commit` |
| Run the hook on staged files | `bash .claude/hooks/pre-commit.sh` |
| All static gates | See section 9.1 |
| Pipeline tests | `python -m pytest tests/agents/ -v` |
| PHPUnit, one group (from the Moodle root) | `vendor/bin/phpunit --group tenant_isolation` |
| PHPUnit, one plugin (from the Moodle root) | `vendor/bin/phpunit public/local/sentientia_<plugin>/tests/` |
| Playwright, local | `cd tests/playwright && PLAYWRIGHT_BASE_URL=http://localhost:8080 npx playwright test` |
| Persona journeys, local | `cd moodle-enhancement/tools/visual-pass && node persona_journeys.mjs` |
| Flag snapshot (on a server, as the web user) | `php flag_snapshot.php --config=<absolute path to config.php>` |
| 5.3 render gate | `bash moodle-enhancement/deploy/render_smoke_53.sh <site URL>` |

## Appendix D. Status of each QA mechanism

| Mechanism | Status | Gate type |
|---|---|---|
| Pre-commit hook (20 checks) | Built (verified on development) | Blocking for errors (local only) |
| `ci.yml` blocking jobs (12: static, structural, pytest and stylelint) | Built (verified on development) | Blocking |
| `version-bump-check` | Built (verified on development) | Blocking on pull requests only |
| `phpunit-52` | Built (verified on development) | 2 groups blocking, full suite advisory |
| `playwright-linux` | Built (verified on development) | Advisory |
| `core-mods.yml` | Built (verified on development) | Blocks pull requests; reports on push |
| Repository gate scripts | Built (verified on development) | On demand, pre-commit, CI |
| Visual-pass toolkit and persona journeys | Built (verified on development) | Manual |
| Visual-evidence ledger with masking | Built (verified on development) | Manual review |
| AI-assisted code and screenshot review | Built (verified on development) | Manual |
| UAT deploy tool and verification runbook | Available (UAT) | Manual with stop rules |
| Migration rehearsal kit | Built (verified on development); target-box run Planned | Manual |
| Erasure supplementary provider | Built (behind a feature flag, off by default) | Not applicable |
| 5.3 CI job, load testing, security scanning, coverage, defect tool | Planned (proposed in this document) | To be decided |

## Appendix E. Spot checks against the repository (20 November 2026)

| # | Claim | Result | Evidence |
|---|---|---|---|
| 1 | Pre-commit hook has 20 checks and 849 lines | Confirmed | Section headers at lines 136 to 808; `wc -l` |
| 2 | `ci.yml` has 15 jobs | Confirmed | Job ids from `php-lint` to `stylelint-guard` |
| 3 | `phpunit-52` uses Moodle 5.2 stable, MariaDB 10.11, 40-minute cap; full suite has `continue-on-error` | Confirmed | `ci.yml` lines 785 to 1254 |
| 4 | Blocking tenant group requires at least 40 tagged files | Confirmed | `test "$N" -ge 40` in `ci.yml` |
| 5 | Playwright lane is advisory and seeds 4 CI personas | Confirmed | `continue-on-error: true`; `PLAYWRIGHT_*_USER` variables |
| 6 | `ci.yml` paths filter omits the twin trees for blocks, enrolment, payment and access rule, and most of `tools/` | Confirmed | `ci.yml` lines 40 to 81 |
| 7 | `core-mods.yml` blocks pull requests and only reports on push | Confirmed | Workflow header |
| 8 | Local plugins: 424 test files, 4,600 methods; theme 149 methods | Confirmed | `find` and `grep` |
| 9 | `tenant_isolation` tagged on 135 files | Depends on method. A literal `@group tenant_isolation` match (the CI sanity-step method) gives 132 local plus 3 theme files, which equals the fact base's 135; other recounts gave 134 to 140 depending on scope. Counting every tag form PHPUnit reads (any spacing after `@group`, the attribute form, test sub-folders) gives 183 files across the 57 components (Appendix F). The body states "about 135" by the CI method; see risk 15 | `grep -l '@group tenant_isolation'`; `grep -lE` with a whitespace-tolerant pattern |
| 10 | `privacy_erasure` on 1 file | Confirmed | `grep` |
| 11 | 125 pytest cases | Confirmed | Count of `def test` in `tests/agents/test_agent*.py` |
| 12 | 23 Playwright test declarations in 12 specs | Confirmed | `grep` of `test(` and `test.fixme(` |
| 13 | 115 persona-journey steps across 11 personas | Confirmed | `journeys.json` |
| 14 | 47 visual-evidence folders | Confirmed | `ls` |
| 15 | Tree-drift baseline 38 entries; no new drift | Confirmed | `tools/tree-drift-baseline.txt`; drift check exit 0 |
| 16 | Language parity: 0 failures, 1 warning | Confirmed | `php tools/check-lang-parity.php --quiet` |
| 17 | Theme layout regions: 0 unprinted | Confirmed | `php tools/check-theme-layout-regions.php` |
| 18 | Masking replaces real names and e-mails before screenshots and warns when the list is empty | Confirmed | `visual-pass/fix_branches_checks.mjs`; `moodle53_checks.mjs` |
| 19 | Local persona provisioning refuses non-localhost sites and stores passwords only in a git-ignored file | Confirmed | `provision_local_personas.php` header; `.gitignore` line 135 |
| 20 | Flag snapshot is read-only and used before and after each UAT deploy | Confirmed | `tools/uat/flag_snapshot.php`; UAT runbook steps 2 and 4 |
| 21 | Hash-comparison helper used in the UAT runbook is not tracked in git | Confirmed | `git ls-files` |
| 22 | No static analysis, dependency, dynamic or load-testing tool in any workflow | Confirmed | `grep` of workflows for phpstan, psalm, semgrep, codeql, zap, phpcs, lighthouse, k6, jmeter |

## Appendix F. PHPUnit inventory by component

Static count on 20 November 2026 over the canonical trees (`moodle-enhancement/local/`, `moodle-enhancement/blocks/`, `moodle-enhancement/enrol/`, `moodle-enhancement/payment/gateway/`, `moodle-enhancement/mod/quiz/accessrule/`) and `theme/sentientia/`. Test files: `*_test.php` under each component's `tests/` folder, including sub-folders. Test methods: lines matching `function test` in those files; data-provider cases are not counted. Tagged files: files carrying the `tenant_isolation` group in any form PHPUnit reads (a docblock `@group` tag with any spacing, or a `#[Group('tenant_isolation')]` attribute). This tag count is wider than the CI sanity step's literal match; see section 3.5.2.

| # | Component | Test files | Test methods | `tenant_isolation`-tagged files |
|---|---|---|---|---|
| 1 | `local_sentientia_ai` | 3 | 19 | 1 |
| 2 | `local_sentientia_aiquiz` | 8 | 104 | 4 |
| 3 | `local_sentientia_analytics` | 4 | 65 | 4 |
| 4 | `local_sentientia_api` | 11 | 83 | 6 |
| 5 | `local_sentientia_assistant` | 4 | 22 | 1 |
| 6 | `local_sentientia_authoring` | 14 | 129 | 5 |
| 7 | `local_sentientia_calendar` | 4 | 84 | 3 |
| 8 | `local_sentientia_cart` | 22 | 315 | 12 |
| 9 | `local_sentientia_catalog` | 13 | 122 | 8 |
| 10 | `local_sentientia_challenge` | 10 | 73 | 4 |
| 11 | `local_sentientia_classroom` | 21 | 255 | 8 |
| 12 | `local_sentientia_compliance_report` | 8 | 90 | 5 |
| 13 | `local_sentientia_content_market` | 1 | 13 | 1 |
| 14 | `local_sentientia_core` | 9 | 114 | 4 |
| 15 | `local_sentientia_courses` | 18 | 207 | 9 |
| 16 | `local_sentientia_emails` | 18 | 183 | 5 |
| 17 | `local_sentientia_evaluation` | 17 | 212 | 5 |
| 18 | `local_sentientia_exams` | 6 | 47 | 2 |
| 19 | `local_sentientia_gamification` | 3 | 10 | 3 |
| 20 | `local_sentientia_integrations` | 3 | 22 | 1 |
| 21 | `local_sentientia_leaderboard` | 6 | 52 | 3 |
| 22 | `local_sentientia_learningpath` | 15 | 163 | 7 |
| 23 | `local_sentientia_lifecycle` | 1 | 5 | 1 |
| 24 | `local_sentientia_live` | 12 | 148 | 4 |
| 25 | `local_sentientia_m365` | 3 | 36 | 1 |
| 26 | `local_sentientia_manager` | 6 | 58 | 3 |
| 27 | `local_sentientia_notifications` | 8 | 73 | 4 |
| 28 | `local_sentientia_org` | 12 | 89 | 4 |
| 29 | `local_sentientia_pages` | 1 | 6 | 0 |
| 30 | `local_sentientia_platform` | 34 | 486 | 8 |
| 31 | `local_sentientia_privacy` | 4 | 32 | 1 |
| 32 | `local_sentientia_proctoring` | 6 | 59 | 1 |
| 33 | `local_sentientia_programs` | 11 | 134 | 2 |
| 34 | `local_sentientia_pwa` | 5 | 40 | 4 |
| 35 | `local_sentientia_ratings` | 6 | 68 | 2 |
| 36 | `local_sentientia_recommendations` | 6 | 57 | 3 |
| 37 | `local_sentientia_recompletion` | 11 | 128 | 2 |
| 38 | `local_sentientia_reports` | 3 | 17 | 2 |
| 39 | `local_sentientia_request` | 6 | 98 | 5 |
| 40 | `local_sentientia_roles` | 10 | 116 | 3 |
| 41 | `local_sentientia_skills` | 9 | 68 | 4 |
| 42 | `local_sentientia_skillsai` | 8 | 64 | 5 |
| 43 | `local_sentientia_talent` | 2 | 16 | 1 |
| 44 | `local_sentientia_translate` | 8 | 68 | 3 |
| 45 | `local_sentientia_users` | 23 | 224 | 10 |
| 46 | `local_sentientia_whatsapp` | 6 | 54 | 1 |
| 47 | `local_sentientia_xapi` | 5 | 72 | 2 |
| 48 | `block_sentientia_cert_health` | 1 | 6 | 0 |
| 49 | `block_sentientia_compliance` | 1 | 7 | 1 |
| 50 | `block_sentientia_cron_health` | 1 | 5 | 0 |
| 51 | `block_sentientia_leaderboard` | 1 | 4 | 0 |
| 52 | `block_sentientia_recommendations` | 1 | 4 | 0 |
| 53 | `block_sentientia_trainer` | 1 | 3 | 0 |
| 54 | `enrol_sentientiasub` | 1 | 9 | 0 |
| 55 | `paygw_airpay` | 4 | 33 | 0 |
| 56 | `quizaccess_sentientia_proctoring` | 4 | 41 | 0 |
| 57 | `theme_sentientia` | 25 | 149 | 5 |
| | **Total (57 components)** | **464** | **4,861** | **183** |

## Appendix G. Known open defects at handover (starting list)

A starting list taken from the engineering record, not a complete backlog. Severity uses the proposed scheme in section 7.10 and is the review's proposal for QA to confirm. Data is described by role and persona label only. The full backlog is to be extracted in weeks 2 to 3 (owner: platform engineer, with QA; section 9.2).

| # | Area | Description | Severity (proposed) | Source |
|---|---|---|---|---|
| 1 | Compliance report | Snapshot rows are never removed, so rows for courses taken off the list, tenants a learner left, and suspended or deleted users stay frozen and are counted in the key performance indicators (KPIs), scorecard, defaulters list, matrix, manager report and theme widget. The 20 November development changes remove stale rows for course and person pairs that no longer apply; whether that closes the suspended and deleted user case is to be confirmed (owner: platform engineer) | S2 (silent wrong numbers) | `PROJECT-STATE.md`, 2026-11-17 entry; `docs/visual-evidence/2026-11-19/session-01/README.md` (compliance section) |
| 2 | Compliance report | Exempted rows count in every rate's denominator | S2 (silent wrong numbers) | `PROJECT-STATE.md`, 2026-11-17 entry |
| 3 | Compliance report | KPI tiles are cached with no expiry and do not refresh after a rebuild | S2 | `PROJECT-STATE.md`, 2026-11-17 entry |
| 4 | Compliance notifications | Line managers never receive the "overdue" alert, because the de-duplication ignores the recipient | S2 | `PROJECT-STATE.md`, 2026-11-17 entry |
| 5 | Compliance notifications | Escalations continue after an auto-enrolment has ended. The 20 November development changes stop setting an auto-enrolment end date; status for existing enrolments to be confirmed (owner: platform engineer) | S3 | `PROJECT-STATE.md`, 2026-11-17 and 2026-11-20 entries |
| 6 | Compliance report | A deleted tracked course would abort the whole hourly rebuild | S2 | `PROJECT-STATE.md`, 2026-11-17 entry |
| 7 | Certificates | No certificate is issued on course completion (local, UAT and the branch head); the legacy platform issued them | S2 (gap against current behaviour) | `PROJECT-STATE.md`, 2026-11-20 entry (work package 1b) |
| 8 | Payment gateway privacy | The privacy provider declares no personal data over 3 tables with user columns (finding F-1) | S2 | `_facts/40-data-privacy.md`; section 4 of this document |
| 9 | Layout, mobile (390 px) | Learning-path index organisation selector is wider than its card; analytics "Risk Signals" column clipped and Course Effectiveness headers run together; mobile sidebar brand text underlined | S4 | `docs/visual-evidence/2026-11-19/session-01/README.md`, open items |
| 10 | Layout, desktop (1440 px) | E-mail rules Actions column runs past its card | S4 | same |
| 11 | Certificate verification page | The logged-out verification page has no header or heading | S3 (accessibility and navigation) | same |
| 12 | Test status | The 20 November development changes have no finished PHPUnit run; write-path checks for those changes are still owed | Not a defect; a release-entry blocker | same; section 3.5.3 |
