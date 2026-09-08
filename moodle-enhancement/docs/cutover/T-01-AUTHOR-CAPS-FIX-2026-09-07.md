# T-01 — Course Author persona-caps + authoring nav fix

**Date:** 2026-09-07 · **Owner:** Nitin Rajput · **Engineering:** Claude
**Trigger:** UAT persona visual walk (`UAT-DEMO-READINESS-2026-09-04.md`, "Author persona finding")
**Status (2026-09-08):** reviewed → integrated as **`1dc599466`** on `claude/gap-integration` (24 files, both trees identical) → **deployed to UAT** via `tools/uat/deploy_to_uat.sh --yes --commit 1dc599466` (13 public targets, sha256 13/13 OK, `upgrade.php` Success: theme 2026090701, authoring + skillsai 2026090700; backup `/tmp/uat-predeploy-backup-20260908-072542.tgz`) → **probe-verified on the box**: `sentientiaauthor` (id 11) holds exactly the 7 caps at system context, `creatornewroleid` = editingteacher (authors manage the courses they create).
**Flags (Layer B):** no flip was made. The 3 master flags were found **already ON globally on UAT** (cust 0 / tenant 0, created 3 Sept 2026 12:44 by the admin account during provisioning). Nitin's decision 2026-09-08: **leave them on** for the demo (mock mode; no `*.live_api` flip, no Anthropic key).
**Visual check (2026-09-08):** PASS — logged in as the author on UAT, the sidebar shows Authoring Studio / AI Quiz / Skills AI and all three pages load in mock mode; evidence in `docs/visual-evidence/2026-09-08/uat-author-t01/README.md`.
**Product decisions (§5):** Nitin confirmed the scope — "author should have access to create and manage content that he/she has developed" — which matches the fix as built (own-content caps + coursecreator + editingteacher on created courses); *Manage Courses* stays a tenant-admin surface.
**Branch worked on:** `claude/t01-author-caps` (cut from `claude/gap-integration` @ `fb886fac2`)

---

## 1. Symptom

Logged in as `uat_author_airpay` (Sneha Kulkarni — `sentientiaauthor`@system +
`employee`@system + `coursecreator`@category:AirPay), the author sees a plain
**LEARNER** dashboard + learner sidebar and gets *"Sorry, but you do not
currently have permissions to do that (View course management)"* at
`/local/sentientia_courses/index.php`. The demo-readiness doc requires the
author to demo **Dashboard, Authoring Studio, Skills AI, AI Quiz, question
bank** (AI-assisted authoring, mock mode).

## 2. Root cause — THREE independent layers (verified)

| # | Layer | Detail |
|---|-------|--------|
| **A** | **Theme nav** | `theme_sentientia\role_detector::detect()` classifies only siteadmin / L&D-admin (`sentientia_courses:manage` or `administrator`@category) / manager (`site:viewreports` or direct reports) / learner. The archetype-less `sentientiaauthor` matches none → **learner shell** (`role_detector.php:175`). And `sidebar_navigation.php` had **no Authoring Studio / AI Quiz / Skills-AI / question-bank nav item for ANY tier** — those surfaces were URL-only. |
| **B** | **Feature flags** | `sentientia.authoring.enabled`, `sentientia.aiquiz.enabled`, `sentientia.skillsai.enabled` all default **OFF**. The pages throw `err_feature_off` *before* the capability check, and the plugins' own nav hooks hide their links. |
| **C** | **Caps + fresh-install parity** | The `sentientiaauthor` role already holds the 7 author caps (`authoring:generate/review/managetemplates`, `skillsai:extract/review`, `aiquiz:generate/review`) — but ONLY because they are seeded from `db/upgrade.php` (authoring 2026061701, aiquiz 2026080400) + the UAT CLI. **Moodle never runs `upgrade.php` on a fresh install**, so a brand-new customer comes up with NO author role at all. That is the recurring T-01 "fresh-install vs upgrade parity" bug. |

The reported "View course management" error is `sentientia_courses:view` — a
**tenant-admin cap that is NOT an author surface** (see Product Decisions).

## 3. What changed

### Layer A — theme nav (`theme/sentientia`, single tree — no ME copy)

- **`classes/role_detector.php`** — adds an informational, capability-based
  `isauthor` to the returned array (holds `authoring:generate` |
  `aiquiz:generate` | `skillsai:extract` at system, each `get_capability_info`-
  guarded). It does **not** change the learner/manager/admin tiering (an author
  is normally also a learner), so the mutually-exclusive tier-invariant holds.
- **`classes/sidebar_navigation.php`** — adds `add_authoring_nav()` +
  `can_use_authoring_studio()` / `can_use_aiquiz()` / `can_use_skillsai()`.
  Each helper gates on the plugin **master feature flag AND the same system
  capability the target page enforces**, safe-failing — exactly the existing
  `can_create_live_session()` (T-02) pattern. Group rendered in the learner +
  manager branches; a leading divider only when ≥1 item qualifies.
  - Authoring Studio → `/local/sentientia_authoring/studio.php` (flag `sentientia.authoring.enabled` + cap `authoring:generate`)
  - AI Quiz → `/local/sentientia_aiquiz/generate.php` (flag `sentientia.aiquiz.enabled` + cap `aiquiz:generate`)
  - Skills AI → `/local/sentientia_skillsai/index.php` (flag `sentientia.skillsai.enabled` + cap **`skillsai:review`** — index.php requires `:review`, not `:extract`, so the link gate matches the page gate)
- **`lang/en` + `lang/hi`** — 3 nav string pairs (`nav_authoringstudio`,
  `nav_aiquiz`, `nav_skillsai`). Parity re-verified: **227/227, zero drift.**
- **`version.php`** 2026090400 → **2026090701**.
- **`tests/role_detector_test.php`** — 2 `isauthor` tests.

Author + trainer + editingteacher all light up **by capability**, no hardcoded
role id. A link never appears unless BOTH the flag is ON and the user holds the
cap — so with flags at their OFF default the sidebar is unchanged (see §4).

### Layer C — author-role fresh-install + upgrade parity (both trees)

- **`local_sentientia_authoring`** — NEW `classes/author_role.php::ensure()`:
  one idempotent seeder (create archetype-less role, pin `CONTEXT_SYSTEM`, grant
  the 7-cap author set, each guarded by capability-existence). Called from **both**
  `db/install.php` (fresh) **and** a NEW `db/upgrade.php` step **2026090700**
  (existing). Historical steps 2026061700/2026061701 left intact. Version
  2026080501 → **2026090700**. NEW `tests/author_role_test.php` (3 tests).
- **`local_sentientia_skillsai`** — NEW `db/install.php` re-runs
  `author_role::ensure()` (class_exists-guarded). Reason: on a fresh install
  Moodle installs plugins alphabetically, so `authoring` installs BEFORE
  `skillsai`; authoring's install-time seed skips the not-yet-registered
  skillsai caps. Because skillsai installs last of the cap owners, re-running
  ensure() here fills them in. Version 2026080500 → **2026090700**.

Both plugins mirrored to `local/` **and** `moodle-enhancement/local/` (verified
byte-identical). No schema change, no new capability, no new feature flag.

## 4. Layer B — the demo REQUIRES a feature-flag flip (config, not code)

The nav links are (correctly) hidden while the master flags are OFF. **This is
by design** (CLAUDE.md §13 — no feature ships default-ON) and is **not changed
in code.** To demo, flip the 3 flags ON for the Airpay tenant (or globally):

- **Preferred — Switchboard UI:** L&D admin → `local/sentientia_platform/admin/switchboard.php`, toggle `sentientia.authoring.enabled`, `sentientia.aiquiz.enabled`, `sentientia.skillsai.enabled` ON for the Airpay tenant.
- **CLI equivalent** (`feature_flags::set(key, tenant_id, value)` — tenant 1 = Airpay, 0 = global):

```php
require('config.php'); // run from Moodle dirroot via php -r / a throwaway CLI
\local_sentientia_platform\feature_flags::set('sentientia.authoring.enabled', 1, true, null, 'T-01 demo');
\local_sentientia_platform\feature_flags::set('sentientia.aiquiz.enabled',    1, true, null, 'T-01 demo');
\local_sentientia_platform\feature_flags::set('sentientia.skillsai.enabled',  1, true, null, 'T-01 demo');
```

Live generation stays mock (no `*.live_api` flip, no Anthropic key) — exactly
the demo-readiness "mock mode" story.

## 5. Product decisions to confirm (Nitin)

1. **"Manage Courses" is intentionally NOT granted to the author.** The reported
   error was on `sentientia_courses:view/:manage` — a **tenant-admin RISK_CONFIG
   cap** and an L&D-admin surface per the persona table, not an author surface.
   The task brief also says the author must NOT get tenant-admin caps. Granting
   it would also mis-badge the author as an L&D admin in the sidebar. **Recommend
   leaving it out.** (If you want the author to see the admin course list, it's a
   one-line grant — say the word.)
2. **Course creation** for the author is already covered by their
   `coursecreator`@category:AirPay assignment (`moodle/course:create`) + the
   Authoring Studio publish flow — no new cap needed.
3. **Question bank** — the author is already `editingteacher` in
   `UAT-AP-PRODUCT` + `UAT-AP-SALES-FUND`, giving full question-bank caps **in
   those courses** (where AI-quiz/authoring publish their generated questions).
   I did **NOT** grant site-wide `moodle/question:*` to the system role
   (over-grant). Consequently there is **no standalone "Question bank" nav item**
   — it's reached via the AI-quiz/authoring review→publish flow or the author's
   own course. Confirm this is acceptable, or ask for a scoped grant.

## 6. Verification

- `php -l` clean on all 20 changed/new files (PHP 8.2.12).
- Theme lang parity 227/227 (en↔hi), 3 new keys in both.
- Every referenced cap string, feature-flag key and page URL confirmed to exist.
- Skills AI link gate (`:review`) matches `skillsai/index.php`'s `require_capability`.
- `role_detector::detect()` consumers (`dashboard.php`, `user_menu.php`,
  `sidebar_navigation.php`, existing tests) all read by key name — the added
  `isauthor` key is additive and safe; the tier-invariant test is unaffected.
- Conflict-marker guard (git-marker regex, CLAUDE.md CHECK 11 / CI gate): clean.
- **PHPUnit EXECUTED 2026-09-08** on local XAMPP (Moodle 5.1.3+, PHP 8.2.12,
  MariaDB 10.11.16; theme + authoring + skillsai copied to `public/`, site
  `upgrade.php` run, fresh `phpunit init` — no other php process was holding the
  test DB):
  - `local/sentientia_authoring/tests/author_role_test.php` — **3/3 OK**, 18 assertions.
  - `theme/sentientia/tests/role_detector_test.php` — **10 run, 8 OK, 2 skipped**.
    Both new tests (`test_author_detected_via_authoring_cap`,
    `test_plain_user_not_author`) **pass**. The 2 skips are the pre-existing
    BizLMS-schema guards (`administrator role not present`, `employee role not
    present`), expected on a vanilla PHPUnit install and unrelated to T-01.
  - "1 PHPUnit deprecation" in each run = the known `@covers` doc-comment
    metadata notice, not a test problem.

## 7. Not done / out of scope

- No live flag flip, no deploy, no commit (review diff).
- Did not touch `local_sentientia_courses` course-scoping (that is
  `task_17fc05d8`).
- No core-file changes → no `docs/core-mods/` entry needed.
