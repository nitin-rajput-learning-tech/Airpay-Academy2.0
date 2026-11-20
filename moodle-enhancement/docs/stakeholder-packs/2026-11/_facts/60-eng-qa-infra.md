# Fact base 60: engineering, QA and infrastructure

Confidential: Airpay Payment Services internal. Not for external distribution.

Document owner: Head of Learning & Development, Airpay Payment Services. Basis: repository at HEAD of `claude/gap-integration` plus the uncommitted working tree of 20 November 2026. Read-only extraction; counts are static unless a row says otherwise.

Status placement used below:

| Marker | Meaning | Source |
|---|---|---|
| Commit `af6e65c0e` | The code on UAT: **Available (UAT)** | `moodle-enhancement/PROJECT-STATE.md` (UAT entries of 11-18 and 11-19) |
| Commits `4a78b8f82`, `76e8ec572` (plus docs commit `4df7d4573`) | After UAT: **Built (verified on development)**; proctoring B1/B2 additionally **Built (behind a feature flag, off by default)** | `git log af6e65c0e..HEAD` (3 commits) |
| Uncommitted working tree (20 Nov): certificate issuer, `tool_certificate` 5.0.12 re-vendor, compliance clean-up option, `form.js` core patch, e-mail rule editor fix, survey anonymity minimum, external-trainer accounts | **Built (verified on development)**; flagged items behind flags, off by default | `git status` (602 changed paths, most of them the re-vendored `admin/tool/certificate`) |

---

## 1. Repository layout and branches

| Item | Fact | Source |
|---|---|---|
| Repository | One monorepo. Moodle core is NOT in git for 5.3: the repo holds a Moodle 4.5.10 core snapshot at the root (the CI Playwright source root) plus the product layer | `moodle-enhancement/core-patches/MANIFEST` header |
| Commits on current branch | 1,984 | `git log --oneline` |
| Release branch / work branch | `production` (remote HEAD) / `claude/gap-integration` | git config; `CLAUDE.md` section 14 |
| Branch refs | 213 local, 140 remote-tracking, mostly merged topic branches | `git branch`, `git branch -r` |
| Tags | 53, including packaging tags `v4.2.0-sentientia-5.2-package-2026-09-10` and `v4.3.0` to `v4.3.3-sentientia-5.3-package-2026-10-19..22` | `git tag` |
| Plugin trees | Sentientia plugins exist twice: `moodle-enhancement/<type>/<plugin>/` (canonical) and `<type>/<plugin>/` (twin), except `local_sentientia_api`, `local_sentientia_content_market` and `local_sentientia_talent` (canonical tree only) and the theme (repository root only). 44 of 47 local plugins and all 9 blocks, enrol, payment-gateway and access-rule plugins have a twin. Both trees are deployed from. UAT runs the `moodle-enhancement/` copy (the 5.3 package is built from that tree) | `tools/check-tree-drift.php` header; `docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Theme | `theme/sentientia/` only, at repo root (standalone, `$THEME->parents = []`) | `CLAUDE.md` section 7 |
| Product components (counted once) | 47 `local_sentientia_*`; 6 `block_sentientia_*` (cert_health, compliance, cron_health, leaderboard, recommendations, trainer); 1 `enrol_sentientiasub`; 1 `paygw_airpay`; 1 `quizaccess_sentientia_proctoring`; 1 theme. Total 57 | `ls moodle-enhancement/local blocks enrol payment/gateway mod/quiz/accessrule` |
| Vendored third-party | `tool_certificate` (re-vendor to 5.0.12 built in the working tree), `block_learnerscript` and `block_reportdashboard` (patched; `*_PATCHED.php` kept in `moodle-enhancement/blocks/`) | `moodle-enhancement/docs/core-mods/` records; ADR-041 |
| Core patches (ADR-044) | One file: `lib/form/form.js` (`M.form.initFormDependencies` skips a form no longer in the document). Kept as a full file per Moodle version, pinned upstream SHA-256, hunks may only add lines | `moodle-enhancement/core-patches/MANIFEST`; `docs/core-mods/2026-11-20-form-js-dependency-guard.md` |
| Core-mod discipline | Every core edit needs a `docs/core-mods/` record and a `SENTIENTIA-CORE-MOD` marker; enforced by pre-commit CHECK 6 and CI workflow `core-mods.yml` | `docs/core-mods/README.md` |
| Architecture decisions | 42 ADR files plus a README (ADR-001 to ADR-044; numbers 006 and 007 are not in the folder), for example ADR-027 quality gates, ADR-031 cross-tenant authority, ADR-032 BizLMS import, ADR-033 Moodle 5.3 target | `moodle-enhancement/docs/adr/` |
| State cards | 67 files, one per plugin or subsystem | `moodle-enhancement/state-cards/` |
| Feature flags | 43 registry files (41 local plugins, `enrol_sentientiasub` and `theme_sentientia`) declare 142 flag keys, 13 default ON and 129 default OFF (PHP include count, see `00-FACTS-INDEX.md`). Snapshot from the local rehearsal of the UAT 5.2-to-5.3 upgrade (October 2026; the flag set has grown since): 94 flags before, 31 new, 0 existing changed, all new default OFF | `moodle-enhancement/local/*/db/feature_flags.php`; `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` section 2 |
| Flag API | `\local_sentientia_platform\feature_flags::is_enabled`, `is_enabled_for`, `set`; a key absent from the registry makes `set()` throw; per-customer and per-tenant override; read-only snapshot tool `tools/uat/flag_snapshot.php` | `CLAUDE.md` section 5 |
| Content pipeline code | `scripts/agents/agent1..agent6` (mock by default), 125 pytest cases | `scripts/agents/`; `tests/agents/test_agent*.py` (17+25+21+16+27+19) |

---

## 2. CI (GitHub Actions)

Three workflows. BLOCKING gates fail the pull request, or leave `production` red after a push.

| Workflow / job | Trigger | What it checks | Blocking? | Source |
|---|---|---|---|---|
| `ci.yml` paths filter | PR, and push to `production`, when a path under `moodle-enhancement/`, `local/sentientia_**`, `theme/sentientia/`, `tests/playwright/`, `scripts/agents/`, `tests/agents/`, `requirements.txt`, `.github/workflows/` or the upgrade-step gate files changes | all jobs below (15 jobs) | see rows | `.github/workflows/ci.yml:51-81` |
| `php-lint` | as above | `php -l` over product PHP (PHP 8.2); three ADR-027 Gate 0 scans: Mustache comment leak, stale `theme_airpayux` reference, missing `standard_end_of_body_html` | Yes | `ci.yml:83-156` |
| `static-checks` | as above | JSON validity; Mustache balance | Yes | `ci.yml:158-214` |
| `ws-contract-gate` | as above | Web-service contract drift (ADR-009), `moodle-enhancement/tools/ci-ws-contract-check.php` | Yes | `ci.yml:216-259` |
| `conflict-marker-check` | as above | Stray `<<<<<<<`, `=======`, `>>>>>>>` (born from CI runs 397 and 403) | Yes | `ci.yml:261-314` |
| `path-boundary-check` | as above | `tools/check-path-boundary.php --quiet` | Yes | `ci.yml:316-326` |
| `siteid-compare-check` | as above | `tools/check-siteid-compare.php` | Yes | `ci.yml:328-343` |
| `tree-drift-check` | as above | `tools/check-tree-drift.php` (local, payment/gateway, enrol, quiz accessrule, admin/tool against `moodle-enhancement/`); `tools/check-bizlms-fixture-copies.php` | Yes for drift not in the baseline | `ci.yml:345-367` |
| `lang-parity-check` | as above | `tools/check-lang-parity.php` (en and hi); `tools/check-message-provider-strings.php`; `tools/check-theme-layout-regions.php` | Yes | `ci.yml:369-411` |
| `amd-build-parity` | as above | every `amd/src/<n>.js` has `amd/build/<n>.min.js` (`scan_amd_build_parity.php`, allowlist file now empty) | Yes | `ci.yml:413-456` |
| `upgrade-step-versions` | as above | self-test on throwaway git repos, then new `upgrade.php` savepoints must be above the plugin's version at the base (`tools/check-upgrade-step-versions.php`, `tools/ci/resolve-base.sh`) | Yes | `ci.yml:458-510` |
| `version-bump-check` | pull request only | `version.php` bumped when plugin code changed | Yes (PR) | `ci.yml:512-547` |
| `python-agents` | as above | pytest of the six pipeline agents and an end-to-end mock-mode smoke (`scripts/agents/run_pipeline_test.py`); 10-minute cap | Yes | `ci.yml:549-577` |
| `playwright-linux` | as above | Playwright E2E and visual on chromium, firefox and webkit, against a `moodlehq/moodle-php-apache:8.2` container with Moodle installed from the repo's 4.5 snapshot; seeds persona accounts (`tools/ci/seed_playwright_personas.php`); 30-minute cap; uploads report and traces | Advisory (`continue-on-error: true`) | `ci.yml:579-783` |
| `phpunit-52` | as above | Boots Moodle `MOODLE_502_STABLE` on PHP 8.3 and MariaDB 10.11, copies every Sentientia plugin into `public/`, initialises PHPUnit, builds the testsuite list. Steps: (1) `tenant_isolation` group, **BLOCKING** (tagged-file sanity count must be at least 40); (2) `privacy_erasure` group, **BLOCKING** (at least 1 tagged file); (3) full suite, advisory, JUnit XML kept 30 days. 40-minute cap. Rebuilt for the 5.1+ layout, PHP 8.3 and MariaDB on 2026-11-16 (it had never run green before) | Partly (the two groups) | `ci.yml:785-1254`; `docs/ci/PHPUNIT-GATE.md` |
| `stylelint-guard` | as above | `theme/sentientia/.stylelintrc.ci.json` bans `.costcenter_data` and `.content_right` in new SCSS (ADR-018 decoupling); 87 files, 0 findings when restored 2026-11-16. The `prefers-reduced-motion` rule stays a local check (about 35 known violations in `_surface-*.scss`) | Yes | `ci.yml:1256-1301` |
| `core-mods.yml` / `core-mods-check` | every PR and every push to `production`, no paths filter | `tools/ci/check-core-mods.sh <base> HEAD` (changed core files need a record and marker; push mode is cumulative from pinned baseline `7cb584cd9`, reports but cannot block); `tools/check-core-patches.sh check --ref HEAD` | Blocks PRs | `.github/workflows/core-mods.yml` |
| `deploy-production.yml` | `workflow_dispatch` only; needs the typed string `I-CONFIRM-PRODUCTION-DEPLOY`; SSH to the host, pull, upgrade, purge. Secrets are repository secrets (names only: production host, user, key, port, Moodle path, optional Slack webhook) | Manual; never automatic; not the cutover path | `.github/workflows/deploy-production.yml` |
| Not in CI | PHP 8.4 lint (done by hand), a MySQL 8.4 matrix entry (suggested in the job comments, not built), phpcs, a Moodle 5.3 PHPUnit job | Gap | `ci.yml` comments |

Test groups used by gates (static grep of `@group`): `tenant_isolation` on 135 test files (hard-gate floor 40); `bizlms_import` 61 tags; `privacy_erasure` 1 file. Source: `git grep "@group"` over `moodle-enhancement/local/*/tests`, theme tests.

---

## 3. Pre-commit hook: the 20 checks

File `.claude/hooks/pre-commit.sh` (849 lines). Install per clone with `tools/install-hooks.ps1` (`.git/hooks/` is not tracked). Staged lists use `git diff --cached --no-renames --diff-filter=ACM`, so a renamed-and-edited file is still checked. Errors block the commit; warnings do not.

| # | Check | Severity | CI twin | Source (section header line) |
|---|---|---|---|---|
| 1 | PHP syntax of staged `.php` | Error (warn if PHP missing) | `php-lint` | `pre-commit.sh:136` |
| 2 | `MOODLE_INTERNAL` guard on class and lib files (guarded core files inside an upstream sync are skipped, as in CHECK 6) | Error | none | `:163` |
| 3 | Raw `$_GET`, `$_POST`, `$_REQUEST` access | Error | none | `:214` |
| 4 | Hard-coded credential patterns | Error | none | `:238` |
| 5 | `.env` file staged | Error | none | `:264` |
| 6 | Moodle core file edit needs record and marker; upstream-sync handling | Error and warn | `core-mods.yml` | `:275` |
| 7 | `content/sops/` protection (never delete SOPs) | Error and warn | none | `:398` |
| 8 | SCORM ZIP root validation (`imsmanifest.xml` at root) | Error | none | `:425` |
| 9 | `version.php` format (`YYYYMMDDNN`) | Error and warn | `version-bump-check` (bump) | `:462` |
| 10 | Uncommitted `[CONFIRM]` placeholders | Warn | none | `:516` |
| 11 | Git conflict markers in `.php .mustache .scss .js .json .xml .md .yml` | Error | `conflict-marker-check` | `:530` |
| 12 | State-card freshness (a plugin change should stage its `state-cards/<plugin>-state.md`) | Warn | none | `:564` |
| 13 | Mustache comment leak | Error | `php-lint` step | `:593` |
| 14 | Stale `theme_airpayux` references | Error | `php-lint` step | `:620` |
| 15 | Missing `standard_end_of_body_html` in full-page layouts | Error | `php-lint` step | `:645` |
| 16 | AMD `src`/`build` parity | Error and warn | `amd-build-parity` | `:673` |
| 17 | en/hi lang-pack parity | Error and warn | `lang-parity-check` | `:716` |
| 18 | Tenant path-boundary scan | Error and warn | `path-boundary-check` | `:741` |
| 19 | Cross-tree plugin twin check (staged file differs from its twin) | Warn (CI blocks new drift) | `tree-drift-check` | `:761` |
| 20 | SITEID strict-comparison scan | Error and warn | `siteid-compare-check` | `:808` |

Companion hooks in `.claude/hooks/`: `lint-on-save.sh` (per-filetype checks; as wired it receives no file path and lints nothing, so it is run by hand); `code-quality-coach.sh` (not wired). Permission rules deny `Remove-Item` and `rm -r`, `git push --force`, `git reset --hard`, `git clean -f`, SQL `DROP`, and direct calls to ElevenLabs, Gamma and the live site; they prevent mistakes and are not a sandbox. Source: `.claude/CLAUDE.md`, Settings block.

---

## 4. Repository gates and scanners

| Script | Checks | Origin | Source |
|---|---|---|---|
| `tools/check-path-boundary.php` | Unbounded tenant path prefixes: `unbounded-literal` (`LIKE '/77%'`), `unbounded-concat`, `slash-both-sides` (`'%/'.$x.'/%'`), `unbounded-php-prefix`. Fix: `\local_sentientia_platform\tenant::path_descendant_filter()`. Inline marker `path-boundary-ok:` with a reason | Shipped 4 times, 2026-09-07 to 09-22 | file header |
| `tools/check-siteid-compare.php` | Strict comparison decided by types alone (`(int)$id !== SITEID`; `SITEID` is a database string) | Shipped 5 times; fix commit `0eb31f32f` | file header |
| `tools/check-tree-drift.php` + `tools/tree-drift-baseline.txt` | Byte comparison (line endings normalised) of each plugin across both trees: CONTENT, ONLY-ME, ONLY-TOP. Covers `local/`, `payment/gateway`, `enrol`, `mod/quiz/accessrule`, `admin/tool`. Baseline now 38 entries (25 CONTENT, 12 ONLY-ME, 1 ONLY-TOP); its header records the original 70 differing and 29 one-sided files | 2026-09-22 | file header |
| `tools/check-lang-parity.php` | Fails when a component with a `lang/hi` pack has a key set differing from `lang/en` | ADR-028 Phase 1.5 | file header |
| `tools/check-message-provider-strings.php` | Every declared message provider has its `messageprovider:<name>` string | 3 plugins showed `[[messageprovider:...]]` | file header |
| `tools/check-theme-layout-regions.php` | Every block region a layout declares is printed by its template | UAT `/my/` TypeError, 2026-10-23 | file header |
| `tools/check-upgrade-step-versions.php` and `-selftest.php` | New `upgrade.php` savepoint above the plugin's version at the base | 2026-10-16 skipped steps | file header |
| `tools/check-bizlms-fixture-copies.php` | The two owner-signed ADR-032 files equal the copies PHPUnit reads | ADR-032 | file header |
| `tools/check-core-patches.sh` | `check`, `apply`, `verify-tree` for versioned core patches (ADR-044) | 2026-11-20 | file header |
| `tools/ci/check-core-mods.sh`, `tools/ci/resolve-base.sh` | Core protection over a commit range; resolves a CI base commit and never substitutes another | mirrors CHECK 6 | file headers |
| `tools/check_state_card_freshness.sh` | State cards stale for 90 days while code changed | audit finding F-099 | file header |
| `tools/check_workspace_sync.sh` | Files in the deployed XAMPP tree but not in git | audit finding F-095 | file header; `docs/WORKSPACE-POLICY.md` |
| `moodle-enhancement/tools/scan_mustache_comment_leaks.php` | `{{! ... }}` comments whose body contains `}}` or `{{` and so print on the page | 14 templates, 2026-06-09 | file header; ADR-027 |
| `moodle-enhancement/tools/scan_stale_theme_refs.php` | Runtime `theme_airpayux/` AMD define names after the de-brand | ADR-027 Gate 0 | file header |
| `moodle-enhancement/tools/scan_missing_end_of_body.php` | Full-page layouts that never flush `standard_end_of_body_html` (dead AMD) | ADR-027 Gate 0 | file header |
| `moodle-enhancement/tools/scan_amd_build_parity.php` | `amd/src` module with no `amd/build/*.min.js`; existence-only, so a stale build still passes | Moodle 5.2 audit | file header |
| `moodle-enhancement/tools/ci-ws-contract-check.php` | WS contract drift | ADR-009 | file header |
| `tools/uat/mysql84_reserved_scan.py` | MySQL 8.4 reserved words in new table and column names (88 names for the 11-18 push, 673 for the 5.3 upgrade, 0 hits) | MySQL 8 failures at Stage A | script; runbooks |
| `tools/audit_table_inventory.php`, `tools/probe_*.php`, `tools/f024_qa_account.php` | One-off audit and probe scripts | various | `tools/` |

---

## 5. PHPUnit test inventory

Static count of `function test*` methods in `*_test.php` files, working tree, canonical tree `moodle-enhancement/`. Data providers are not counted, so runtime case counts are higher. Source: `find` and `grep` per plugin.

| Component group | Test files | Test methods |
|---|---|---|
| 47 `local_sentientia_*` plugins | 424 (417 committed at HEAD) | 4,600 |
| 6 `block_sentientia_*` | 6 | 29 |
| `enrol_sentientiasub` | 1 | 9 |
| `paygw_airpay` | 4 | 33 |
| `quizaccess_sentientia_proctoring` | 4 | 41 |
| `theme_sentientia` | 25 | 149 |
| **Total counted** | **464 files** | **4,861 methods** |

Largest by methods: `sentientia_platform` 486 (34 files), `cart` 315 (22), `classroom` 255 (21), `users` 224 (23), `evaluation` 212 (17), `courses` 207 (18), `emails` 183 (18), `learningpath` 163 (15), `live` 148 (12), `programs` 134 (11), `authoring` 129 (14), `recompletion` 128 (11), `catalog` 122 (13), `roles` 116 (10), `core` 114 (9). Thinnest: `lifecycle` 5, `pages` 6, `gamification` 10, `content_market` 13, `talent` 16, `reports` 17, `ai` 19.

Recorded runs (not re-run for this document): Moodle 5.3 platform suite 586 OK; Moodle 5.1 set of 57 flag-writing files / 785 tests OK, platform 585 of 586 with one endpoint-protection rename-lock error that passes on re-run; cart suite 333 tests green on 5.1. The 20 November development changes were NOT covered by a finished PHPUnit run (the local re-initialisation was cut off). Sources: `PROJECT-STATE.md` entries of 2026-10-09 and 2026-11-20; `docs/ci/PHPUNIT-GATE.md`.

| Other test asset | Count | Source |
|---|---|---|
| Playwright specs `tests/playwright/*.spec.ts` | 12 files, 23 `test(` / `test.fixme(` declarations: login, dashboard, navbar, dark-mode, mobile-590, learner, manager, compliance, author, a11y-smoke, render-smoke, visual. Persona specs skip cleanly when their credential env vars are unset; deep mutating journeys are staged as `fixme` | `tests/playwright/README.md` |
| Python pipeline tests | 125 | `tests/agents/` |
| Local PHPUnit procedure | runbook | `moodle-enhancement/PHPUNIT-RUNBOOK.md` |

---

## 6. Playwright and visual-pass toolkit, persona walks, visual evidence

Toolkit: `moodle-enhancement/tools/visual-pass/` (README there). Local only (base URL fixed to `http://localhost:8080`). Logins come from a gitignored `.personas.local.json` written by `provision_local_personas.php`; scripts that import `evidence_lib.mjs` block every host except Google Fonts.

| Component | What it does | Source |
|---|---|---|
| `evidence_lib.mjs` | Helpers `session`, `go`, `scan`, `shots`; `shots()` saves full-page desktop (1440 px) and mobile (590 px); optional `widths` (320 to 2560) with a horizontal-overflow measure | `visual-pass/README.md` |
| `persona_journeys.mjs` + `journeys.json` | 11 personas (learner, manager, trainer, author, admin1, compliance, public77, guest, zeea-learner, zeea-admin, siteadmin), 115 steps in total (19, 10, 10, 11, 13, 5, 13, 6, 11, 7, 10) | `visual-pass/journeys.json`; `docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md` |
| `coverage_crawl.mjs`, `coverage_urls.php` | Route coverage crawl | `visual-pass/` |
| About 15 `*_checks.mjs` | Per-change evidence scripts (cart, GST, evaluation, profile, theme passes of 11-02, 11-12, 11-16, Moodle 5.3, login-as, branches) with matching `seed_*.php` and flag fixtures | `visual-pass/` |
| `surface_walk_1118.mjs` | Surface walk for the 18 Nov demo | `visual-pass/` |
| Fresh-eyes review | Before a UI commit a second agent with no access to the diff reviews the screenshots against the brand rules and lists defects per file; findings go into the day's evidence README | `visual-pass/README.md` |
| CI visual lane | `visual.spec.ts`; `render-smoke.spec.ts` (0 console errors, `window.require` is a function, no literal `{{` in the body, landmarks present); `a11y-smoke.spec.ts` (axe) | ADR-027 Gates 1 and 2 |
| Link stress harness | `moodle-enhancement/tools/gap-test/` (`probe_links.ps1`, about 107 URLs in `urls.core.txt`, 8 personas, circuit breaker). Result 2026-06-18: site admin 91 of 107 OK; learner, manager and public personas 52 to 55 OK with zero errors; one real defect found and fixed | `docs/audits/brand-revamp-2026-06/LINK-STRESS-TEST-2026-06-18.md` |
| Workflow matrix | Persona by workflow (learner, author, trainer, manager, compliance, admin) with coverage mechanism PW / CLI / HTTP / MAN and a status column. A multi-role capability demotion (role-switch force-pin) was found only in a real browser session and fixed 2026-06-15 | `docs/testing/WORKFLOW-TEST-MATRIX.md` |
| Quality-gate doctrine (ADR-027) | Gate 0 static lint (shipped), Gate 1 render smoke, Gate 2 visual diff and axe, Gate 3 coverage matrix as definition of done. Rule: every recurring bug class becomes an automated gate | `docs/adr/ADR-027-quality-gate-system.md` |
| Visual evidence ledger | `moodle-enhancement/docs/visual-evidence/`: 47 dated folders (2026-05-20 to 2026-11-20), 28 of them holding images; 1,528 images (1,489 PNG plus JPG and WebP). Each UI session ends with desktop and mobile screenshots and a README of what changed; the owner reviews before merge | `docs/visual-evidence/README.md` |
| Latest session evidence | `docs/visual-evidence/2026-11-19/session-01/README.md`: read-only lanes at 1440 and 390 px, PASS / FAIL / BLOCKED per step; one console error on Manage Courses found and guarded | `PROJECT-STATE.md` 11-20 entry |
| Accessibility | Phase H audit and axe results | `moodle-enhancement/PHASE-H-A11Y-AUDIT.md`, `PHASE-H-A11Y-AXE-RESULTS.md` |

Limits: local persona passes cannot run state-changing steps against UAT. Browser automation and the VPN used for UAT cannot run at the same time on the development machine (working note in `PROJECT-STATE.md`). Test personas and data are synthetic or a test import; no persona credentials are in the repo.

---

## 7. UAT deploy tooling

| Tool | Behaviour | Source |
|---|---|---|
| `tools/uat/deploy_to_uat.sh` | Surgical, checksum-verified deploy of named files, or of a commit or range's deployable files. **Dry run by default**; `--yes` deploys. Steps: stage, tar, sha256 manifest, `scp`, back up existing files to `/tmp/uat-predeploy-backup-<TS>.tgz`, extract as sudo, `chown www-data`, `sha256sum -c`, `upgrade.php --non-interactive` (exit 4 and no purge on failure), `purge_caches.php`. Only `theme`, `local`, `blocks`, `mod`, `admin`, `lib` paths; deletions are never propagated; aborts (exit 3) when the same target differs between the two plugin trees unless `--prefer-top` or `--prefer-me`; skips the top-level 4.5 `lib/`; maps `core-patches/5.3/public/<path>` to `public/<path>`. Writes no record of what it deployed | `tools/uat/deploy_to_uat.sh` header; `UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| `tools/uat/flag_snapshot.php` | Read-only one-line-per-flag snapshot; run before and after and diff: a deploy may add flags only as OFF and must not change any existing resolved value | file header |
| Hash comparison | Hash raw git blobs, never the CRLF working tree or `git archive` (the repo has `core.autocrlf=true`: 81 of 139 files of the 11-18 push differed from their blobs in the working tree); compare with a hash list taken on the box | `UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| `tools/uat/rehearse_52_to_53.sh`, `rehearse_*.php`, `rehearse_helper.py` | Local rehearsal of the 5.2 to 5.3 upgrade: versions, counts, posture | `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |
| `tools/uat/stage-a-install.sh`, `finish_install.php`, `provision_test_users.php`, `adr031_*.php`, `set_flag.php` | Stage A install, cross-tenant role configuration, test accounts, flag setter. UAT deploys and flag flips are owner decisions | `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` |
| Do not use | `tools/uat/uat-purge.ps1` (wrong path) and `deploy-to-uat.ps1` (PowerShell is disabled on the development machine) | `UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Log scan on the box | `moodle-enhancement/tools/ops/sentientia-logscan.sh`: daily 06:15 root cron; writes resources, Apache PHP errors, 5xx counts and failing tasks to a dated file kept 30 days. The only monitoring artefact today | file header |
| Remote dev loop | VPN, then a long-lived tunnel session (one TOTP per work session) forwarding the box's SSH, the database and HTTP to local ports | `docs/operations/UAT-REMOTE-DEV-WORKFLOW.md` |

Recorded UAT deploys: 2026-10-21 upgrade to Moodle 5.3 (package from `4f847d434`, tag `v4.3.2`); 10-29 (14 files); 10-30 (123 kn, mr, sw language files); 11-11 (20 files, theme 2026110200); 11-18 (the runbook's Option A, 106 files, covering everything since `95bd68689`), then the 11-18 midday and 11-19 fixes up to `af6e65c0e`. Source: `PROJECT-STATE.md`; `UAT-DEPLOY-RUNBOOK-2026-11-18.md`.

---

## 8. Packaging

| Item | Fact | Source |
|---|---|---|
| Recipe | `tools/packaging/build-standalone.sh --target 5.3` (5.2 kept as fallback). Steps: git export of `--ref` (committed files only); stage a fresh copy of the SHA-256-verified vanilla Moodle base; overlay (`moodle-enhancement/tools/overlay-airpay-customs.ps1`); apply core patches (`check-core-patches.sh apply`); verify stage and archive (build fails on any gate); zip with `bsdtar`; hash and tag | `build-standalone.sh` header |
| Build gates | Root `config.php` count in the archive is 0; `public/config.php` equals the vanilla loader shim and holds no DB setting (checked after copy and after overlay); core patches apply only over the pinned upstream file | header |
| Output | `Sentientia-LMS-5.3-Complete-Standalone-<date>.zip` with `DEPLOY-README.txt`; size, entry count and SHA-256 pinned in `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` | header |
| Traps recorded | GNU tar writes a plain tar named `.zip`; Windows `Compress-Archive` fails on Moodle's deep paths; a public-only bundle drops the root `lib/bundles` that 5.3 needs; an earlier package leaked a development `config.php` | header; `package-sentientia.ps1` |
| Retired | `package-sentientia.ps1` (public-only bundle from the local webroot; per-plugin zips and `MANIFEST.md`), `build-5.2-standalone.sh` (thin wrapper) | file headers |

---

## 9. Environments

| Environment | Facts | Source |
|---|---|---|
| Local development (XAMPP) | Moodle 5.1.3+ served at `http://localhost:8080/` (no `/moodle` prefix), PHP 8.2.12, MariaDB 10.11.16, Apache 2.4.58. Deploy by file copy into `public/`, then `php ../admin/cli/purge_caches.php` from `public/` (`config.php` and `admin/cli/` sit one level above `public/`). It can lag the repo: diff before testing. Apache runs in console mode (no admin rights for Windows services). `$CFG->debug` is kept as a database setting, not in `config.php`, because the config form made every request rescan plugins and pages passed the 120 s limit; the login page now loads in about 9 s warm | `CLAUDE.md` section 2; `PROJECT-STATE.md` 2026-11-13 entry; `deploy/deploy-to-xampp.ps1` |
| Local Moodle 5.3 runtime | A private tree outside the repo: Moodle 5.3 (version `2026100500.00`, SHA-256 verified), PHP 8.4.21 CLI, MariaDB 11.4.13 on `127.0.0.1:3308`; used for the 5.3 compatibility gate, rehearsals and 5.3 PHPUnit | ADR-033; `docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` |
| Local resource limits | Endpoint-protection scanning slows full suites and installs (a 5.2 fresh install took 98.8 minutes locally, against minutes on UAT); exclusions are not in place, so full suites run on GitHub CI and targeted tests run locally. XAMPP MariaDB defaults make PHPUnit 5 to 10 times slower (`innodb_flush_log_at_trx_commit=2` at runtime helps). OPcache on Windows crashed Apache under sustained authenticated load; not applicable to the Linux server, where OPcache should be on | `PROJECT-STATE.md` entries 11-13 and 11-18; `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md`; `docs/5.2-merge/PHASE-B1-PHP84-DOCKER-VERIFIED.md` |
| CI | GitHub-hosted `ubuntu-latest`; PHP 8.2 for lint and Playwright; PHP 8.3 and MariaDB 10.11 for PHPUnit on Moodle 5.2 stable | `ci.yml` |
| UAT | `https://academy2.airpay.ninja`, Moodle 5.3 since 2026-10-21. EC2 `t3a.small` (2 vCPU, 2 GB RAM, 40 GB disk); PHP 8.3.6 under PHP-FPM; Apache 2.4 on port 80 behind a load balancer, `flushpackets=on`; RDS MySQL 8.4.9 on `db.t3.small`; `www-data` crontab every minute; dataroot outside the web root; code directory still named `moodle5.2`. The SSH user has passwordless sudo but cannot read `config.php`, so no database backup is possible from SSH (IT can snapshot the database). Access by VPN and a bastion with TOTP. Data is a test import of a production backup plus test personas | `UAT-UPGRADE-5.3-2026-10-19.md`; `UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Production (legacy, not Sentientia) | `https://www.airpay.academy`: Moodle 4.1.2+ (Build 20230401, version 2022112802.06) with the BizLMS plugin set, theme epsilon, AWS RDS MySQL 8.0.44. Not deployed from the branch; Sentientia replaces it at a staged cutover | `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` section 0; `CLAUDE.md` section 2 |

Moodle 5.3 platform floor (ADR-033): PHP 8.3 or later (8.3 is the single bridge version for both upgrade hops; Moodle 4.5 refuses 8.4); MySQL 8.4 or later (MariaDB 11.4, PostgreSQL 17); sodium; 64-bit; `max_input_vars` 5000 or more; `memory_limit` 512M or more; `max_allowed_packet` 64M or more (at 1M, cron loses its connection); Apache 2.4 with `mod_rewrite` to `r.php`; the root `lib/` deployed beside `public/`. Support windows: 5.3 LTS security fixes to 2029-10-01; 5.2 to 2027-10-04. Sources: `docs/adr/ADR-033-target-moodle-5.3-lts.md`; `docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` (a draft, not sent: proposes `t3a.medium` or larger and `db.t3.medium` before cutover; Moodle 4.5 on MySQL 8.4 is unproven, so a temporary MySQL 8.0 database may be needed for hop 1).

---

## 10. Stage A, Stage B, migration and cutover runbooks

| Document | Purpose | Status | Source |
|---|---|---|---|
| Stage A | Fresh Sentientia install on UAT; install-path gaps found and fixed (`install.php` versus `upgrade.php`, Moodle 5.2 defaults `forcelogin=1` and `enablemyhome=0`, MySQL 8 reserved words, router) | Done on UAT, since upgraded to 5.3 | `docs/cutover/STAGE-A-VERIFICATION-MATRIX.md`; `UAT-DEPLOY-2026-10-07.md` |
| Stage B | Restore a fresh live backup on a separate box; upgrade in two hops (4.1.2 to 4.5.x to 5.3); repair; switch theme epsilon to sentientia; run the ADR-032 BizLMS import; prove 100% parity against a baseline taken before any upgrade; one cron cycle; report | Kit built and rehearsed locally; the run on the target box waits for a fresh live backup with checksums and the 5.3 server (IT request drafted, not sent) | `MIGRATION-REHEARSAL-RUNBOOK.md`; `tools/rehearsal/README.md`; `DRAFT-IT-REQUEST-2026-11-12.md` |
| Rehearsal kit | `tools/rehearsal/`, 13 steps: 00 preflight, 01 restore check (filedir SHA-1 gate), 02 source baseline, 03 hop 1 to 4.5, 04 hop 2 to 5.x, 05 repairs, 06 ADR-031 roles, 07 theme switch, 08 import guard, 09 import, 10 parity compare, 11 cron cycle, 12 summary; `run_all.sh`; `selftest.sh` (515 of 515 at last run). DRY by default; refuses a database not on the allow-list, any name containing `prod` or `uat`, and a server holding the production schema; runs as `www-data`; signal-safe status files | `tools/rehearsal/` |
| Local rehearsal on real-shaped data | 2026-10-08, April 2026 production dump (3,533,797,806 bytes; 580 tables; 3,138 user rows): restore 763 to 839 s; hop 1 (4.1.2 to 4.5.10) 1,344 s, parity 100%; hop 2 (4.5.10 to 5.3 plus 47 Sentientia plugins) 4,748 s; one parity difference: Moodle 5.0 removes `mod_survey` (one empty survey activity), so a 5.x-compatible `mod_survey` is to ship in the package. Aggregate baseline: per-tenant user counts total 2,871 (1,463 suspended), 412 courses, 39,220 enrolments, 32,252 course completions, 11,415 certificate issues, 8,685 quiz attempts, 27,166 grades, 95 BizLMS legacy tables with 25,726 rows | PASS, one decision open | `docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` |
| UAT 5.2 to 5.3 | Rehearsed locally in 24 min (20 min on repeat): 0 of 94 flags changed, 31 new flags OFF, no table or row lost, 0 reserved words in 673 new names, PHP peak 169 MB. Executed on UAT 2026-10-21 without a database backup; after the first row is written only a forward fix is possible | Done (UAT) | `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md`; `docs/operations/uat-upgrade-2026-10-21/` |
| Cutover guide | Gate A deploy dormant (maintenance on, copy, `upgrade.php`, purge, maintenance off); Gate B tenant registry; Gate C; Gate D component rename; each gate has a rollback, normally a flag flip. Config checks: `logstore_standard logguests` must stay 1 or `order_paid` is never audited; the "Certificate issued" e-mail default is turned off with the popup kept | Planned | `docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` |
| Smoke test | `scripts/cutover-smoke-test.py`: eight checks (login, dashboard route, catalogue API, SCORM endpoint, tenant switching, dark-mode assets, navbar and footer, REST health), JUnit output, three safety guards | Written for the 5.1 to 5.2 plan; `deploy/render_smoke_53.sh` is the 5.3 render gate | `docs/cutover/CUTOVER-SMOKE-TEST-RUNBOOK.md` |
| Other runbooks | `docs/operations/deploy-runbook.md`, `cutover-day-runbook.md`, `docs/PHP-8.3-UPGRADE-RUNBOOK.md`, `moodle-enhancement/DEPLOYMENT-RUNBOOK.md`, `MFA-ENFORCEMENT-RUNBOOK.md`, `docs/RUNBOOK-org-cutover.md`, `docs/RUNBOOK-component-rename.md` | Reference | file names |
| Privacy erasure rehearsal | 515 automatic delete requests on the local copy: 286 processed (all Deleted), 229 with no user row skipped; 0 violations, 0 failed deletes, 0 e-mails or notifications sent; found a stock-Moodle truncation bug in data-request approval and two erasure gaps (supplementary provider shipped behind two flags, off) | Done on local copy | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md`; ADR-035 |

Cutover rule: UAT sign-off, then the Stage B rehearsal with 100% parity, then cutover with all data. Production is never patched in place. Sources: `CLAUDE.md` section 2; `PROJECT-STATE.md`.

---

## 11. Known performance facts

| Fact | Detail | Source |
|---|---|---|
| Local baseline (2026-05-06, XAMPP cold) | 16 admin pages 7.9 to 14.9 s cold, all HTTP 200. Estimate of 3 to 5 s warm on a production-class server (an estimate, ratio 2 to 3 times) | `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md` |
| Fixes measured | Catalogue 5.0 s cold to 0.056 s warm (cache); analytics 5.76 s to 0.0001 s on a cache hit; org admin N+1 from 213 queries to 1 (86 times faster), which also removed a cross-tenant LIKE leak | same |
| Proposed SLOs (not measured on a production-class box) | Login under 1 s p50; dashboard warm under 2 s p50; admin index under 3 s cold and 1 s warm; catalogue under 3 s cold and 0.5 s warm; analytics under 1 s p95; list web services under 500 ms p95; suspending 50 users under 5 s | same, SLA section |
| Not measured | Per-page query counts, mobile first contentful paint, Lighthouse, load and soak tests, concurrent-user capacity on a production-class environment | same, "What's NOT measured" |
| UAT box size | 2 GB RAM, 2 vCPU: suitable for smoke and persona walks only | `DRAFT-IT-REQUEST-2026-11-12.md` |
| Upgrade durations | Core 5.1 to 5.2 had 2,057 steps with zero errors; 4.5.10 to 5.3 took 4,748 s and 4.1.2 to 4.5.10 took 1,344 s on a workstation under endpoint-protection load; UAT 5.2 to 5.3 took 24 min locally. Production-class timings: to be confirmed in Stage B (owner: DevOps) | `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md`; real-data rehearsal doc |
| Cron | `max_allowed_packet` of 1M drops the connection mid-cron; 32 Sentientia scheduled tasks registered after repair; course reminder and overdue tasks ship disabled; cron and task runners stay off during rehearsals | `MIGRATION-REHEARSAL-RUNBOOK.md`; real-data rehearsal doc; `UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Flag cache | A flag change takes up to about 30 s to apply (operator note) | `PROJECT-STATE.md` 2026-10-09 follow-ups |
| Caching pattern | Application cache with 5 to 10 minute TTL for dashboard and tenant aggregates; static acceleration for per-request reads; hourly TTL on analytics trends | `.claude/rules/database.md`; `PROJECT-STATE.md` 11-20 |
| Capacity design target | 3,500 or more users, 3 tenants | `CLAUDE.md` mission |

---

## 12. Known defect classes and the guard for each

| Class | What happened | Guard | Source |
|---|---|---|---|
| Unbounded tenant path prefix | `LIKE '/1%'` filters counted a neighbouring tenant (749 instead of 743 users; `/1/2` swallowed `/1/20`); silent wrong numbers; shipped 4 or more times in September 2026 | `tenant::path_descendant_filter()`; CHECK 18; CI `path-boundary-check`; blocking `tenant_isolation` group | `tools/check-path-boundary.php` |
| False privacy `null_provider` | 4 plugins declared "no personal data" while owning 9 tables keyed on a user id (one held an e-mail address); a fifth had no provider; a subject-access request returned nothing and erasure deleted nothing while reporting success | `privacy_coverage_test` walks every plugin's `install.xml` and `classes/schema/*::TABLES` and fails when a user-identifying column coexists with `null_provider` or no provider | `local/sentientia_platform/tests/privacy_coverage_test.php` |
| Erasure completeness | Stock `tool_dataprivacy` approval truncates the context list, so an approved deletion leaves data (a Moodle core bug); 229 requests for non-existent users throw a TypeError | Supplementary provider in `local_sentientia_privacy` 1.1.0 behind two flags; blocking `privacy_erasure` group with a canary that fails when core is fixed; ADR-035 | A4 results doc |
| Cross-tree plugin drift | An edit landed in one tree only (`structured_logger` kept a retired component prefix; `sentientia_ratings` truncated); the surface served by the other tree silently did nothing | `check-tree-drift.php` with baseline; CHECK 19; the deploy tool aborts on drift | `tools/check-tree-drift.php` |
| SITEID type comparison | `(int)$id !== SITEID` is always true because `SITEID` is a string; the site-course guard silently did nothing; shipped 5 times | CHECK 20; CI `siteid-compare-check` | `tools/check-siteid-compare.php` |
| Upgrade savepoint below shipped version | A step added at a version sites had already passed is skipped silently (2026-10-16) | `check-upgrade-step-versions.php` and self-test | gate header |
| Dead AMD and template leaks | Dashboard charts blank (no `standard_end_of_body_html`), stale `theme_airpayux/*` AMD names, a Mustache comment leak on 14 templates; all "looked fine" | Gate 0 scanners (CHECKs 13 to 15); render-smoke spec | ADR-027 |
| AMD source without build, or stale build | 23 grandfathered gaps drained; the gate is existence-only, so a stale `.min.js` still passes (modules still calling retired `local_airpay_*` web services) | CHECK 16 and CI job; after renames grep `amd/build/*.min.js` | `scan_amd_build_parity.php` |
| Declared-but-unprinted layout region | Block JS threw on `/my/` on UAT (2026-10-23) | `check-theme-layout-regions.php` | gate header |
| Missing message provider string | `[[messageprovider:...]]` on the preferences page (3 plugins) | `check-message-provider-strings.php` | gate header |
| Role-switch force-pin (WF-025) | The switcher wrote a real core role switch; a force-pinned role demoted multi-role admins site-wide; CLI grants masked it; fixed 2026-06-15 | Browser-tier persona walks; check `$USER->access['rsw']` | `docs/testing/WORKFLOW-TEST-MATRIX.md` |
| Capabilities not reaching real roles | New plugins granted capabilities to archetypes that the production author and manager roles did not inherit, so those users could not see the surface | Archetype grants plus upgrade back-fill; persona walks | `docs/cutover/T-01-AUTHOR-CAPS-FIX-2026-09-07.md` |
| XMLDB DDL traps | 333-character index limit, 1333-character VARCHAR maximum, and a raw `<` in a `COMMENT` attribute silently breaks `install.xml` (`upgrade.php` masks it) | PHPUnit init is the fresh-install gate; `check_database_schema.php` | `PROJECT-STATE.md` (entries on the 333-character class) |
| MySQL 8 reserved words | Names valid on MariaDB failed on MySQL 8 at Stage A | `tools/uat/mysql84_reserved_scan.py` before each push | script header |
| CRLF versus blobs | Working-tree CRLF differs from git blobs, so naive hashes mismatch | Hash raw blobs; `.gitattributes` keeps LF for core patches | UAT runbook; `.gitattributes` |
| Modal form removed before its rules start | Console TypeError on Manage Courses (core starts modal-form rules after the form is removed) | Page guard in `local_sentientia_courses`; core patch `lib/form/form.js` (ADR-044); PHPUnit tripwire `modal_form_guard_test` | `docs/core-mods/2026-11-20-form-js-dependency-guard.md` |
| Destructive local command | A scratch command filled a drive with zero files (162 GB, 2026-10-10) | Command rules and deny rules in the workflow prompts | `PROJECT-STATE.md` |

---

## 13. Environment and process gaps relevant to DevOps and QA (facts, not requests)

| Gap | Evidence | Source |
|---|---|---|
| No staging between UAT and production | UAT is a 2 GB single box with test data; Stage B is the first production-shaped run | `UAT-DEPLOY-RUNBOOK-2026-11-18.md`; `DRAFT-IT-REQUEST-2026-11-12.md` |
| No database backup path from the UAT SSH user | An RDS snapshot needs IT | UAT runbooks |
| Deploys leave no marker of what is deployed | `deploy_to_uat.sh` writes none; the baseline is rebuilt from `PROJECT-STATE.md` | `UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| CI runs PHPUnit on 5.2 stable, not 5.3 | `MOODLE_BRANCH: MOODLE_502_STABLE` | `ci.yml` |
| The full PHPUnit suite is advisory in CI | `continue-on-error` on the full-suite step; only two groups block | `ci.yml` |
| The Playwright lane is advisory and runs against the 4.5 snapshot, not the 5.3 theme runtime | `continue-on-error: true` | `ci.yml:594` |
| Development machine: no admin rights, console Apache, endpoint-protection slowdown, no GitHub CLI or LibreOffice | `PROJECT-STATE.md` 11-18 entry | |
| Monitoring is one log-scan cron on UAT; no APM, uptime check or alerting in the repo | `sentientia-logscan.sh` | |
| Load and soak tests not run on a production-class environment | `PHASE-E-PERFORMANCE-BASELINE.md` | |

---

## 14. Key numbers

- 57 product components (47 local, 6 block, 1 enrol, 1 payment gateway, 1 quiz access rule, 1 theme), 44 of 47 local plugins and all other plugins in two trees, theme at the repository root only; 42 ADR files plus README; 67 state cards; 142 registered flags (13 default ON).
- 4,861 PHPUnit test methods in 464 files (static); 125 pytest cases; 23 Playwright declarations in 12 specs; 115 persona-journey steps across 11 personas.
- CI: 3 workflows, 15 jobs in `ci.yml`, 2 blocking PHPUnit groups; 20 pre-commit checks; about 20 repository gates and scanners.
- 47 visual-evidence folders (28 with images) and 1,528 images, 1,489 of them PNG (2026-05-20 to 2026-11-20).
- UAT: Moodle 5.3, PHP 8.3.6, MySQL 8.4.9, 2 GB box, code at `af6e65c0e`. Production: legacy BizLMS on Moodle 4.1.2, RDS MySQL 8.0.44.
- Real-data rehearsal (April dump, 3.5 GB): hop 1 1,344 s, hop 2 4,748 s, parity 100% except one empty survey activity.

## Corrections (fact-base cross-check, 20 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | Feature flags: 141 keys in 42 registry files (13 ON, 128 OFF), not "about 113 in 41 plugins". The 94 and 31 figures are the historical UAT 5.3-upgrade snapshot and stay as such. | PHP include of every `db/feature_flags.php`; `20-flags-roles.md` |
| 2 | Twin trees: three local plugins (`api`, `content_market`, `talent`) have no twin, and the theme lives only at the repository root, so "every plugin exists twice" is not exact. | `ls moodle-enhancement/local` against `ls local` |
| 3 | ADR folder holds 42 ADR files plus `README.md` (43 files); ADR-006 and ADR-007 are absent. | `ls moodle-enhancement/docs/adr` |
| 4 | PHPUnit totals: the file total was an addition error (424 + 6 + 1 + 4 + 4 + 25 = 464 files). Counting the access rule's 41 methods gives 4,861 methods. | `grep` of `function test` per group, 20 Nov 2026 |
| 5 | Visual evidence: 47 dated folders, not 48; 28 hold images; 1,528 images of which 1,489 PNG. `70-ui-surfaces.md` corrected to match. | `find moodle-enhancement/docs/visual-evidence` |
| 6 | The "false privacy null_provider (4 plugins, 9 tables)" row describes the September 2026 defect that was fixed. The current open item of the same class is `paygw_airpay` (finding F-1 in `40-data-privacy.md`), which the structural guard does not reach. | `40-data-privacy.md` section 3.2 |
| 7 | Scheduled tasks today: 34 declared in `db/tasks.php` across the canonical tree, 7 of them disabled by default. The "32 tasks after repair" figure is from the October rehearsal. | PHP include of every `db/tasks.php` |
