# 00 — Context Map
**Phase A output — Airpay Academy 2.0 Master Documentation**
**Produced:** 2026-05-12
**Source reads:** CLAUDE.md (both), PROJECT-STATE.md, all 12 state cards, all version.php files

---

## 1. Repository Structure

**Project root:** `D:\Claude Local\airpay-ld-os\`

```
airpay-ld-os\
├── moodle-enhancement\          ← custom codebase (~2,568 files)
│   ├── local\                   ← 28 local plugins (see §3)
│   ├── blocks\airpay_trainer\   ← 1 block plugin
│   ├── theme\airpayux\          ← custom theme (~514 files)
│   ├── mod\quiz\accessrule\     ← quizaccess_airpay_proctoring
│   ├── state-cards\             ← 12 state cards (see §4)
│   ├── audit\playwright\        ← 30+ Playwright .mjs test scripts
│   ├── PROJECT-STATE.md         ← source-of-truth (~688 lines)
│   ├── UI-UX-OVERHAUL-PLAN.md
│   ├── PHASE-8-REPORT.md
│   ├── PHASE-8-SECURITY-AUDIT.md
│   ├── FEATURE-PARITY-AUDIT.md
│   ├── ENTERPRISE-GRADE-PLAN.md
│   ├── DEPLOYMENT-RUNBOOK.md
│   ├── MOODLE5-UPGRADE-RUNBOOK.md
│   ├── INTEGRATIONS-AUDIT.md
│   ├── PRODUCTION-DEPLOY.md
│   ├── PHASE-H-SCORM-E2E.md
│   └── PHASE-H-A11Y-AUDIT.md
├── content\                     ← SENTIENTIA pipeline content
│   ├── sops\                    ← source SOPs
│   ├── parsed\                  ← parsed JSON
│   ├── narrations\
│   ├── slides\
│   ├── voice\
│   └── scorm-output\
├── docs\                        ← (this directory — created this session)
│   └── _research\
└── tool_certificate_moodle45_2025031804.zip    ← 3rd-party plugin zips
    local_recompletion_moodle45_2025041400.zip
    tool_tcpdffonts_moodle51_2025120101.zip
```

The repo root at `D:\Claude Local\airpay-ld-os\` also contains the full XAMPP
Moodle installation at `C:\xampp\htdocs\moodle\` (88,047 files total across the
project). The custom work is entirely within `moodle-enhancement\`.


---

## 2. Git Log

**Note:** Direct git log extraction was not possible in this session (shell tool
non-functional — `start_process` returns exit 0 with 0 lines captured). The
following is reconstructed from state card commit references.

### Known commits (from state cards — most recent first)

| Hash | Description | Date inferred |
|------|-------------|---------------|
| _unknown_ | Phase 8.1 security remediation (35 files, +787/-83) | 2026-05-12 |
| `acd0a0d41` | Feature-parity audit + G-01 fix + 8 CRUD PHPUnit (54/54 PASS) | 2026-05-07 |
| `739af7f87` | `airpay_roles` UI build — 28 files, 3-tab view, audit log, CSV export | 2026-05-07 |
| `248302b3b` | `airpay_roles` full build — 30 files / ~2,500 LOC / 44 PHPUnit / 543 assertions | 2026-05-07 |
| `f11bdacd0` | State card update: A11Y-4/5/6 + F1 + learnerscript-P3 | 2026-05-06 |
| `2799c0926` | A11Y-4/5/6 + F1 static analysis harnesses | 2026-05-06 |
| `682143ea0` | A11Y-1: aria-sort + keyboard nav on shared datatable (10 plugins) | 2026-05-06 |
| `f35ce3e9b` | H: SCORM e2e — 7/7 PASS | 2026-05-06 |
| `7bd2bd9f4` | K (Phase 0A): 3 BizLMS accesslib methods + 7/7 PHPUnit | 2026-05-06 |
| `43deec238` | State card update: A,C,D,E,F,G shipped; H + K deferred | 2026-05-06 |
| `ae77416b8` | D + E partial: F1 investigation + airpay_classroom PHPUnit | 2026-05-06 |
| `b3b9b18f4` | C+F+G: state card + perf baseline + a11y audit | 2026-05-06 |
| `bdfd01d7e` | F4+F6: 6 orphan dirs removed, 2 plugins migrated to Moodle 5.x hooks | 2026-05-06 |
| `07393e4ac` | PHPUnit: 44/44 PASS — fixed 2 test bugs | 2026-05-06 |
| `dadfe1245` | F1+F2+F3 fixes (catalog cache 40×) | 2026-05-06 |
| `ac22501e8` | Cross-tenant LIKE over-count fix: 13 sites across 4 plugins | 2026-05-06 |
| `002ce78b9` | A: GitHub Actions CI — PHP lint + JSON + Mustache + version-bump | 2026-05-05/06 |

**First commit date:** UNKNOWN — requires git log  
**Total commits:** UNKNOWN — requires git log  
**Active branch:** `production` (per CLAUDE.md)  
**GitHub repo:** `nitin-rajput-learning-tech/Airpay-Academy2.0`


---

## 3. Plugin Inventory

**Total custom plugins:** 31  
(28 local + 1 block + 1 theme + 1 quiz access rule)

### 3a. Theme

| Component | Version | Release | Maturity | Requires | Notes |
|-----------|---------|---------|---------|----------|-------|
| `theme_airpayux` | 2026040500 | 1.0.0-beta | BETA | Moodle 4.0 | Standalone epsilon fork; `$THEME->parents = []`; ~514 files |

### 3b. Block plugins

| Component | Version | Release | Maturity | Requires | Notes |
|-----------|---------|---------|---------|----------|-------|
| `block_airpay_trainer` | 2026041600 | 1.0.0 | STABLE | Moodle 4.5 | Trainer-facing dashboard block |

### 3c. Quiz access rule

| Component | Version | Release | Maturity | Requires | Notes |
|-----------|---------|---------|---------|----------|-------|
| `quizaccess_airpay_proctoring` | 2026051120 | — | STABLE | Moodle 4.4 | Paired with `local_airpay_proctoring` |

### 3d. Local plugins (28)

| Component | Version | Maturity | Requires | Purpose / replaces |
|-----------|---------|---------|----------|--------------------|
| `local_airpay_analytics` | 2026050501 | BETA | 4.0 | Analytics dashboard with KPI cards, heatmaps, drill-down |
| `local_airpay_assistant` | 2026050601 | BETA | 4.5 | AI-powered assistant / recommendations bridge |
| `local_airpay_cart` | 2026051201 | STABLE | 4.4 | E-commerce cart for external (Public) tenant; Phase 8.1 security fix |
| `local_airpay_catalog` | 2026050601 | BETA | 4.5 | Course catalogue — replaces BizLMS `local_custom_category` |
| `local_airpay_challenge` | 2026050802 | BETA | 4.5 | Gamification challenges (streak + quiz-score); Phase 1 shipped |
| `local_airpay_classroom` | 2026051160 | STABLE | 4.5 | Classroom/ILT sessions — replaces BizLMS `local_classroom` |
| `local_airpay_compliance_report` | 2026041200 | STABLE | 4.5 | Compliance reporting with caching (Moodle cache API) |
| `local_airpay_core` | 2026051200 | STABLE | 4.0 | Shared tenant helper; Phase 8.1 — `root_for_user`, `viewer_can_access`, `require_access`, `sql_filter` |
| `local_airpay_courses` | 2026050903 | STABLE | 4.5 | Course admin — replaces BizLMS `local_courses` |
| `local_airpay_emails` | 2026041200 | STABLE | 4.5 | Email template engine + preview + multi-tenant send |
| `local_airpay_evaluation` | 2026050901 | STABLE | 4.0 | Kirkpatrick L1/L2 evaluation forms |
| `local_airpay_exams` | 2026050802 | STABLE | 4.5 | Exam management wrapper around Moodle quiz (233 activities) |
| `local_airpay_gamification` | 2026040900 | BETA | 4.5 | Leaderboard, points, badges, streak observer; 8 WS endpoints |
| `local_airpay_integrations` | 2026050700 | BETA | 4.0 | M365 / Keka / FCM integration bridge (release 1.1.0-beta) |
| `local_airpay_learningpath` | 2026050701 | STABLE | 4.0 | Learning paths (17 real paths from legacy data) |
| `local_airpay_manager` | 2026050802 | STABLE | 4.5 | Manager dashboard — team_manager class; batched queries (4 queries replace N×3) |
| `local_airpay_notifications` | 2026050900 | STABLE | 4.0 | Notification engine (release 1.4.0 — per-user prefs, preview, test-send) |
| `local_airpay_org` | 2026051170 | STABLE | 4.0 | Org hierarchy + tenant + accesslib — replaces BizLMS `local_costcenter` (103 files) |
| `local_airpay_privacy` | 2026041200 | STABLE | 4.5 | DPDP Act 2023 compliance; 4-tier access; data download/deletion |
| `local_airpay_proctoring` | 2026051201 | STABLE | 4.4 | Proctoring logic; paired with `quizaccess_airpay_proctoring`; Phase 8.1 |
| `local_airpay_programs` | 2026050901 | STABLE | 4.0 | Enterprise learning programmes; depends on `local_airpay_org` |
| `local_airpay_ratings` | 2026041600 | STABLE | 4.5 | Star rating engine — replaces BizLMS `local_ratings` |
| `local_airpay_recompletion` | 2026051201 | STABLE | 4.4 | Course re-completion rules; Phase 8.1 |
| `local_airpay_reports` | 2026041914 | STABLE | 4.5 | Admin reports; depends on `local_airpay_org` |
| `local_airpay_request` | 2026051201 | STABLE | 4.4 | Training request / approval workflow; Phase 8.1 |
| `local_airpay_roles` | 2026050802 | BETA | 4.5 | Role management UI — reclassified stub→NEEDED→built; 44 PHPUnit, 543 assertions |
| `local_airpay_skills` | 2026050803 | STABLE | 4.5 | Skills framework + radar chart; 4-language support |
| `local_airpay_users` | 2026050904 | STABLE | 4.5 | User management — replaces BizLMS `local_users` (96 files); 2,869 users |

**Mentioned in code but no version.php found:**
- `local_airpay_pages` — referenced at `/local/airpay_pages/index.php?page=privacy` and `?page=dpdp`; may be a thin wrapper or merged into `local_airpay_privacy`

**Third-party plugin ZIPs present (not inventoried as custom):**
- `tool_certificate` (2025031804) — Moodle Certificate tool
- `local_recompletion` (2025041400) — upstream recompletion plugin (before Airpay fork)
- `tool_tcpdffonts` (2025120101) — TCPDF font support for certificates


---

## 4. State Cards

12 state cards in `moodle-enhancement\state-cards\`

### 4a. Date-based session state cards (chronological)

| File | What was completed |
|------|--------------------|
| `2026-05-05-session-state.md` | Day-1 baseline — initial audit setup, GitHub Actions CI (PHP lint + JSON + Mustache + version-bump commit `002ce78b9`) |
| `2026-05-06-session-state.md` | 13 commits in one session; PHPUnit 44/44 PASS; catalog cache 40× speed-up; cross-tenant LIKE over-count fixed across 13 sites / 4 plugins; 6 BizLMS-era orphan dirs removed (−4,604 LOC); 2 plugins migrated to Moodle 5.x hooks; SCORM e2e 7/7 PASS; BizLMS accesslib 7/7 PASS |
| `2026-05-06-EOD-state.md` | A11Y-1/4/5/6 closed; shared datatable (used by 10 plugins) now WCAG 2.1 AA; keyboard-nav + axe harnesses shipped |
| `2026-05-07-EOD-state.md` | Phase 3.4 complete; all 6 Tier-1 feature gaps (G-01 to G-06) closed; `airpay_roles` full UI shipped (commit `248302b3b`; 30 files / ~2,500 LOC / 44 PHPUnit / 543 assertions); `airpay_challenge` Phase 1 shipped; CODE-side production-readiness declared COMPLETE |
| `2026-05-08-EOD-state.md` | Tier 2 UAT pass; grades widget shipped; CSV import shipped; bulk suspend/activate shipped; smoke tests 8/8 PASS; open bugs: photo.php + per-question anonymous CSV identity leak |
| `2026-05-09-EOD-state.md` | All 5 UAT tiers walked; 4 production bugs found and fixed (dashboard widget heading, `featured.php` DB error, XSS in 3 mustache templates, regression after widget fix); 64 UAT test cases now passing overall |
| `2026-05-10-EOD-state.md` | 64/64 UAT cases PASS; 2 production bugs fixed (photo.php `get_area_files` arg-order bug; 6 cascading dark-mode SCSS token mismatches); RFC 5545 ICS compliance verified; new test fixtures written (`make_fixtures.mjs`) |

### 4b. Plugin state cards

| File | Plugin | Status at card creation |
|------|--------|------------------------|
| `airpay_challenge-state.md` | `local_airpay_challenge` | Phase 1 shipped; Phase 2 (observer + auto-complete) pending |
| `airpay_courses-state.md` | `local_airpay_courses` | Replaces `local_courses`; version.php + lang + DB files done |
| `airpay_org-state.md` | `local_airpay_org` | Replaces `local_costcenter`; 13 BizLMS refs replaced; dual-read fallback active; 9 WS endpoints deferred |
| `airpay_roles-state.md` | `local_airpay_roles` | Reclassified stub→NEEDED→built; full CRUD UI + 3-tab view + audit log + CSV export; Phase 2 (scope management) future |
| `airpay_users-state.md` | `local_airpay_users` | Replaces `local_users`; 17 open_* field constants; 2,869-row datatable; 2 config refs in core_renderer dual-checked |


---

## 5. PROJECT-STATE.md Summary

**File:** `moodle-enhancement\PROJECT-STATE.md` (~688 lines)  
**Last updated:** 2026-05-12 END-OF-DAY

### Current status (as of 2026-05-12 EOD)

| Item | Value |
|------|-------|
| Overall version | 4.0-rc3 |
| Active phase | **Phase 8** — security hardening, UAT, pre-production |
| Theme | airpayux v1.0.0 |
| Moodle target | 5.1.3+ on XAMPP *(discrepancy — see §6 open questions)* |
| UAT | 64/64 cases PASS |
| Security audit | 11 blocking findings closed (Phase 8.1), 35 files changed, +787/-83 |
| Master docs shipped | AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md (123 KB) and .docx (91 KB) |

### Next steps declared in PROJECT-STATE.md

1. Re-run security audit against the new diff
2. Multi-role UAT walkthrough
3. Staging k6 load test against prod-sized RDS clone
4. Follow `PHASE-8-DEPLOYMENT-RUNBOOK.md` for cutover
5. No cutover until all three pre-cutover gates pass

### Phase history summary

| Phase | Milestone |
|-------|-----------|
| Phase 5 | BizLMS costcenter → `local_airpay_org` (13 refs replaced); users → `local_airpay_users`; courses → `local_airpay_courses`; Epsilon parent removed |
| Phase 6 | Feature audit (15 learner + 10 admin modules rated); 16 bugs fixed; Onboarding Wizard; DPDP Act 2023; switch-role; multi-language (4 languages, ~1,056 translations) |
| Phase 7 | Enterprise plan end-to-end: `airpay_cart`, `airpay_proctoring`, `airpay_request`, per-tenant SSO docs, cohort sync, badges, core_ai bridge, mobile-push; capability string fix across all plugins; BizLMS migration CLI scripts |
| Phase 8 | Full-stack security audit (H1 authz, H2 TOCTOU, M1/M2); shared `local_airpay_core` tenant helper; datatable AMD module; bulk actions; `local_airpay_manager` team dashboard; full UAT 64/64 PASS; master documentation shipped |

### Plugin status table (from PROJECT-STATE.md line ~471)

| Plugin | Purpose | Status |
|--------|---------|--------|
| `local_airpay_org` | Org hierarchy, tenant, accesslib, branding | STABLE |
| *(all 22 Phase-2 rows)* | *(per FEATURE-PARITY-AUDIT.md)* | COMPLETE |
| `local_airpay_cart` | E-commerce for external tenants | COMPLETE |
| `quizaccess_airpay_proctoring` | Quiz proctoring rule | COMPLETE |
| `local_airpay_recompletion` | Re-completion rules | COMPLETE |
| AI bridge (`core_ai`) | Moodle 4.5 AI subsystem integration | COMPLETE |
| Cohort sync | Org-tree → cohort automation | COMPLETE |
| Badge seed | Badge definitions | COMPLETE |


---

## 6. Open Questions and Gaps

These must be resolved or flagged in the master document. Ordered by significance.

### Q1 — Moodle version discrepancy (CRITICAL)
**CLAUDE.md** states: `Moodle 4.5.10 (Build 20260216)` on XAMPP  
**PROJECT-STATE.md** (updated 2026-05-12) states: `Moodle: 5.1.3+ on XAMPP`  
A 2026-05-06 state card references `2 plugins migrated to Moodle 5.x hooks`.  
**Resolution needed:** Confirm which version the local XAMPP environment is actually running. The target production platform may also differ. Phase documents referencing the upgrade runbook (`MOODLE5-UPGRADE-RUNBOOK.md`) suggest 5.x is the forward target.

### Q2 — Master documentation already exists
PROJECT-STATE.md records: `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.docx` (91 KB)
and the corresponding `.md` (123 KB) were shipped on 2026-05-12 EOD.  
**Resolution needed:** Is this session's `docs\MASTER-PROJECT-DOCUMENTATION.md` a replacement,
a supplement, or a different format/audience from the existing DOCX?

### Q3 — Git log inaccessible
Total commit count, first commit date, and branch list could not be extracted in this session (shell process tool returns no output). All git metadata must be gathered in Phase B via a working shell session or manual git log extraction.

### Q4 — `local_airpay_pages` has no version.php
Referenced in code at `/local/airpay_pages/index.php?page=privacy` and `?page=dpdp`.
No `version.php` found in `moodle-enhancement\local\`. Either: (a) it is a third-party
plugin not tracked in this repo, (b) it is absorbed into `local_airpay_privacy`, or
(c) it is the Moodle core "local pages" custom page feature.

### Q5 — Phase 8 pre-cutover gates status
PROJECT-STATE.md declares three gates before production cutover:
1. Security audit re-run against new diff — declared "tomorrow start here" (2026-05-12 EOD)
2. Multi-role UAT walkthrough
3. Staging k6 load test against prod-sized RDS clone  
Current state: **NOT YET COMPLETE** as of the last state card read. Phases B–F
research should confirm whether these are now done.

### Q6 — Production deployment date / go-live status
No explicit go-live date found in any state card or PROJECT-STATE.md.
`PRODUCTION-DEPLOY.md` exists but content not yet read.

### Q7 — `block_airpay_trainer` depth
State card not found for this block. No `db/install.xml` directory observed in
the file search. Unclear if it has DB tables or is purely a display block.
Needs Phase B code review.

### Q8 — SENTIENTIA pipeline current status
CLAUDE.md describes SENTIENTIA as "PLANNED". One 2026-05-06 state card mentions
it in the future-backlog table with 8–15h estimate. No pipeline implementation
files observed under `content\`. Treating as planned/not-started; needs
confirmation in Phase B.

### Q9 — Playwright test pass/fail status as of today
30+ `.mjs` test scripts exist under `audit\playwright\`. The most recent UAT
(2026-05-10) shows 64/64 PASS, but this was five days ago. Cutover-gate UAT
re-run status unknown.

### Q10 — Third-party plugin install status
ZIP files present: `tool_certificate`, `local_recompletion`, `tool_tcpdffonts`.
None of these are inventoried in `moodle-enhancement\`. Unknown whether they are
installed on local or production Moodle or simply staged for install.

---

## 7. Environment Summary

| Component | Value | Source |
|-----------|-------|--------|
| Moodle (local XAMPP) | 4.5.10 (Build 20260216) — *but see Q1* | CLAUDE.md |
| Moodle (target/stated) | 5.1.3+ | PROJECT-STATE.md 2026-05-12 |
| PHP | 8.2.12 | CLAUDE.md |
| MariaDB | 10.11.16 | CLAUDE.md |
| Apache | 2.4.58 on port 8080 | CLAUDE.md |
| Local URL | http://localhost:8080/moodle/ | CLAUDE.md |
| Production URL | https://www.airpay.academy/ | CLAUDE.md |
| Active theme | airpayux (standalone epsilon fork) | version.php |
| Theme version | 2026040500 / 1.0.0-beta | version.php |
| Multi-tenant | BizLMS costcenter (Airpay id=1, Public id=77) | CLAUDE.md |
| LMS users | 3,500+ across 3 tenants | CLAUDE.md |
| GitHub | nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch) | CLAUDE.md |
| Languages | English + Hindi + Marathi + Swahili + Kannada (~1,056 strings) | PROJECT-STATE.md |

---

*End of context map. Phase B next: deep-read all plugin source files.*
