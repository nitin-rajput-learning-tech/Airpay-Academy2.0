# Sentientia LMS — Milestone deploy packet for IT (Ninja sandbox)

**Milestone tag:** `sentientia-milestone-2026-06-19` — supersedes `sentientia-milestone-2026-06-18` (adds the post-dry-run rename-miss link fix `d42ff9f16`)  ·  **Branch:** `claude/gap-integration`
**Owner:** Nitin Rajput  ·  **Date:** 2026-06-18  ·  **Target:** ninja-sandbox (rollout-gate **Phase 2**)

> ## ⛔ ROLLOUT GATE (unchanged — Nitin-gated)
> Live `airpay.academy` deploys ONLY on Nitin's explicit go. Path: **Phase 1 Foolproof**
> (workflow testing on local) → **Phase 2 Ninja sandbox** (deploy here + rehearse migrating a
> LIVE backup onto it, verify data parity) → **Phase 3 Replacement** (Nitin-gated). This packet
> drives **Phase 2** (deploy the milestone on the ninja sandbox); it is NOT a live-deploy
> authorization. Full procedure + gate text: [`ROLLOUT-PACKET-2026-06-10.md`](ROLLOUT-PACKET-2026-06-10.md)
> and [`SENTIENTIA-CUTOVER-MASTER.md`](SENTIENTIA-CUTOVER-MASTER.md).

This packet is a **delta on top of `ROLLOUT-PACKET-2026-06-10`** — that packet's IT deploy-window
steps (file deploy + `upgrade.php` + `purge_caches` + the WF-004 task-registration repair + the
SW-1 flag CLI + smoke test + the 5.2 cutover) **still apply verbatim**. Below is only what is NEW
in this milestone and what to verify.

---

## 1. What this milestone adds (since 2026-06-10)

- **The 11-gap competitive build cohort** (`claude/gap-integration`) — 9 plugins + mobile scaffold
  + public API, all **feature-flagged default-OFF, mock-mode (zero AI spend)**:
  skillsai · learningpath(adaptive) · authoring · content_market · analytics+ROI · assistant(agentic)
  · xapi · talent · api(LTI 1.3). Static-verified (204 PHP lint-clean, install.xml well-formed,
  en/hi parity exact). Two install-blocking XMLDB DDL bugs (api over-long index, xapi CHAR>1333)
  were fixed so a **fresh install on MariaDB/utf8mb4 succeeds**.
- **QA fixes from the 2026-06-18 link stress-test** (see
  [`../audits/brand-revamp-2026-06/LINK-STRESS-TEST-2026-06-18.md`](../audits/brand-revamp-2026-06/LINK-STRESS-TEST-2026-06-18.md)):
  - **F1** `content_market/index.php` blank-page (MOODLE_INTERNAL-before-config) — fixed.
  - **F7** org-role `/my/ ↔ /` infinite redirect loop (`theme/sentientia .../user_menu.php`) — fixed
    (one-shot `$SESSION` guard). **Deploy-critical for any multi-role / system-context-role user.**
  - **F3** legacy BizLMS cap fallbacks spamming "Capability not found" debug notices — guarded in
    the Sentientia product code (`local_sentientia_org\accesslib` + theme `core_renderer`).
- **Scoped "Sentientia Author" system-context role** (`local_sentientia_authoring` upgrade 2026061701)
  — assign SME content authors at SYSTEM context to reach the GenAI Authoring Studio + Skills
  Intelligence without over-granting teacher/manager. Provision via
  [`../audits/brand-revamp-2026-06/assign_author_role.php`](../audits/brand-revamp-2026-06/assign_author_role.php).
- **Brand-book seed migrations** (skills-category colours, Public-tenant brand) — DB upgrade steps.

## 2. Deployable manifest (what IT syncs to the ninja webroot)

- **Theme:** `theme_sentientia` v2026061603 (1.0.46-beta) — the standalone fork.
- **Local plugins:** 46 × `local_sentientia_*` (versions below; the `2026061600`+ stamps are the
  new gap-build cohort):

  | plugin | version | release | | plugin | version | release |
  |---|---|---|---|---|---|---|
  | aiquiz | 2026052500 | 0.2.0-alpha | | org | 2026052001 | 1.4.1 |
  | analytics | 2026061600 | 1.1.0-beta | | pages | 2026052900 | 1.1 |
  | api | 2026061600 | 1.0.0 | | platform | 2026052801 | 1.7.0 |
  | assistant | 2026061600 | 1.2.0-alpha | | privacy | 2026052001 | 1.0.1 |
  | authoring | 2026061701 | 0.1.0-alpha | | proctoring | 2026052201 | 1.0.3 |
  | calendar | 2026052700 | 1.2.0-beta | | programs | 2026052001 | 1.8.1 |
  | cart | 2026052001 | 1.0.2 | | pwa | 2026052302 | 0.5.3-alpha |
  | catalog | 2026052902 | 1.0.2-beta | | ratings | (no version.php) | — |
  | challenge | 2026052801 | 1.1.4-alpha | | recommendations | 2026052500 | 0.1.0-alpha |
  | classroom | 2026052001 | 1.10.1 | | recompletion | 2026052001 | 1.1.1 |
  | compliance_report | 2026052900 | 1.0.0 | | reports | 2026052001 | 1.1.1 |
  | content_market | 2026061601 | 1.0.0-beta | | request | 2026061502 | 1.3.3 |
  | core | 2026060400 | 0.7.0-alpha | | roles | 2026052201 | 1.1.3-beta |
  | courses | 2026052501 | 1.11.2 | | skills | 2026052003 | 1.6.2 |
  | emails | 2026052001 | 1.1.2 | | skillsai | 2026061700 | 0.1.0-alpha |
  | evaluation | 2026052032 | 1.15.2 | | talent | 2026061600 | 1.0.0-beta |
  | exams | 2026052003 | 1.6.1 | | translate | 2026052801 | 0.2.0-alpha |
  | gamification | 2026052001 | 1.0.1-beta | | users | 2026052900 | 2.7.1 |
  | integrations | 2026052001 | 1.1.1-beta | | whatsapp | 2026052501 | 0.4.0-alpha |
  | leaderboard | 2026052500 | 0.2.0-alpha | | xapi | 2026061600 | 1.0.0 |
  | learningpath | 2026061600 | 1.8.0 | | lifecycle | 2026040500 | 1.0.0-beta |
  | live | 2026052900 | 0.2.2-alpha | | manager | 2026060200 | 1.3.3 |
  | m365 | 2026052801 | 0.2.0-alpha | | notifications | 2026060200 | 1.4.2 |

  (`ratings` ships without a `version.php` by design — see ADR-022 rename batch.)
- **Blocks:** `block_sentientia_` cert_health · compliance · cron_health · leaderboard ·
  recommendations · trainer.
- **Payment:** `payment/gateway/airpay/` (carries the fail-closed verifier security fix).
- **Core-adjacent (WF-010 — MUST carry):** `my/dashboard.php`, `my/switchrole.php`,
  `my/templates/dropdown.mustache`, root `.htaccess` — the `tools/overlay-airpay-customs.ps1`
  overlay includes these.
- **NOT deployed:** `mobile/sentientia-app/` (Capacitor scaffold — needs backend push extensions first).

## 3. Deploy procedure (ninja sandbox)

Run `ROLLOUT-PACKET-2026-06-10` §"IT deploy-window steps" **pointed at the ninja sandbox**, with
these milestone notes:

1. **File deploy** the `sentientia-milestone-2026-06-19` tag (= `claude/gap-integration` HEAD; this is
   the tag that includes the rename-miss link fix) per the standard overlay (`tools/overlay-airpay-customs.ps1`).
2. **`php admin/cli/upgrade.php --non-interactive`** — installs the 9 new gap plugins (fresh) + applies
   the authoring 2026061701 role step + brand seed migrations. The XMLDB DDL is fixed → no install error.
3. **`php admin/cli/purge_caches.php`**.
4. **WF-004 task-registration repair** (MANDATORY) —
   `php local/sentientia_platform/cli/repair_task_registrations.php --apply`.
5. **SW-1 flag** — `php local/sentientia_catalog/cli/enable_oneclick_enrol.php` (idempotent).
6. **Gap feature flags stay OFF** (default). Enable per-tenant only on Nitin's go, in mock mode first,
   via the platform feature-flag admin / `docs/audits/brand-revamp-2026-06/enable_gap_flags.php`.
7. **Author-role provisioning (optional)** — assign SME authors the Sentientia Author role at SYSTEM
   context: `php docs/audits/brand-revamp-2026-06/assign_author_role.php <username...>`.

### OPcache note (F2)
The link stress-test surfaced OPcache **shared-memory instability on Windows** (`VirtualProtect[87]`
+ worker crashes under load) — that is a **local-XAMPP-on-Windows artifact only**. On the
**Linux ninja/production server, enable OPcache normally** (it's stable + needed for performance).
Do NOT carry the local `opcache.enable=0` workaround to the server.

## 4. Post-deploy verification (ninja)

- Run the **cutover smoke checklist** (login, `/my/`, catalog, one course, one admin page; 0 console errors).
- Run the **link/regression gate** shipped this milestone — `moodle-enhancement/tools/gap-test/`
  (`run_all.ps1` / `summarize.ps1`) against the sandbox URL: expect siteadmin ~OK, learner/manager
  0 errors, **no `/my/` redirect loop** for org-role users (F7), `content_market` renders (F1).
- **Re-verify F5** on the ninja's complete data: `mod/quiz/view?id=53` + `mod/forum/view?id=2`
  500'd on the local clone due to **incomplete `filedir`** (a clone artifact, not a code bug) — with
  full `filedir` restored on the sandbox they should render. Confirm.
- Confirm parity counts (users / enrolments / completions / certificates) before vs after the
  backup-migration rehearsal.

## 5. Reconciled task ledger (the "pending list")

| # | Item | Status for this milestone |
|---|------|---------------------------|
| #400 | DEPLOY-PACKET: consolidated rollout packet for IT | ✅ **Delivered** — this packet (+ the 2026-06-10 base + cutover master). |
| #401 | FOOLPROOF: persona × workflow test matrix (gate Phase 1) | ✅ **Delivered the link/persona gate** — `tools/gap-test/` harness + the 8-persona × ~107-URL pass (LINK-STRESS-TEST-2026-06-18). Ongoing per-feature walks are BAU/post-deploy. |
| #398 | E-tail: Gate-2 visual-diff baselines + white-label defers (D3/D4/D6) + ADR-017 next phase | ⏸ **Deferred — out of this milestone** (v1+ scope; Gate-2 visual-diff not built; ADR-017 polymorphic-user-type is Proposed). Not a deploy dependency. |
| #399 | D-prod: Customer-#2 productization (business pack + demo tenant + engineering tail) | ⏸ **Deferred — future customer**, not part of the customer-zero (Airpay) ninja deploy. |

## 6. Known / deferred (not deploy blockers)

> **Rename-miss fix (2026-06-19, commit `d42ff9f16`)** — folded into the
> **`sentientia-milestone-2026-06-19`** tag (the current deploy candidate; the `-06-18` tag predates it).
> Fixed a theme-wide rename-miss
> — `theme/sentientia` still linked to the pre-rename `/local/courses|users|onlineexams|classroom/`
> paths (gone → 404) in the navbar **Profile pill** (every logged-in user), 4 dashboard quicknav
> tiles (Manage Users/Courses, Online Exams, Classrooms), and the 3 course-context-menu icons.
> All 9 repointed to the live `local/sentientia_*` entry points (verified 303→login on local).
> This also resolved **F4**: the `{{#str}}enrolledusers, local_courses{{/str}}` component (no such
> string → debug notice on every course-page render) is now `core_enrol` (string lives in
> `lang/en/enrol.php`).

- **F5** quiz53/forum2 500 — **classified as a clone-data artifact, not a package bug**: both cmids
  are valid + visible; authed probes return 303 (redirect), not 500, and no fatal is logged. The
  prior 500 was the incomplete-`filedir` clone (per the link-stress-test). Re-verify on the
  complete-`filedir` ninja env (see §4) — expected to render.
- **scssphp `Array to string` warning** during SCSS recompile — benign: it fires inside the
  vendored `lib/scssphp` `@extend` selector-matcher (`Compiler.php:927`), the compile **succeeds**,
  CSS is valid, and it's suppressed at production debug level. Cosmetic CLI/dev-debug noise only
  (ref task #275); not a deploy blocker.
- **F3 residual** — `block_reportdashboard` / `block_learnerscript` (legacy BizLMS vendor blocks)
  still call old caps directly → debug-only notices; folded into the BizLMS-decouple track.
- **Gap pre-deploy fixes** (from PROJECT-STATE GAP-BUILD note, before any flag flips):
  `sentientia_assistant` agentic audit table needs a real privacy provider (DPDP) before its flag
  goes on; authoring live-TTS + publish→SCORM deferred; api LTI JWKS fetch + provisioning are
  extension points; mobile needs backend push extensions.
- **Live deploy is Nitin-gated** — this milestone authorizes the **ninja Phase-2** rehearsal only.
