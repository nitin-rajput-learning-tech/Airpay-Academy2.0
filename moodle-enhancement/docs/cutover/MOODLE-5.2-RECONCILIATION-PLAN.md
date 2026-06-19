# Moodle 5.2 Reconciliation — Deploy-Candidate Upgrade Plan

**Decision:** move the Sentientia deploy candidate from Moodle 5.1.3+ to **Moodle 5.2** (Nitin, 2026-06-19).
**Owner:** Nitin Rajput · **Status:** IN PROGRESS (workstream started 2026-06-19) · **Builds on:** ADR-011.

---

## Where we are

| Thing | State |
|---|---|
| `claude/gap-integration` (the product milestone) | Moodle **5.1.3+**; tag `sentientia-milestone-2026-06-19` (commit `fc5836c10`) |
| Local 5.2 **core** | Present + valid: **Moodle 5.2+ (Build 20260519)**, `$branch=502`, MATURITY_STABLE, at `C:\xampp\htdocs\moodle5.2\public` |
| `5.2-merge-baseline` branch / local 5.2 tree | **STALE** — cut 2026-06-09; **641 commits behind** gap-integration; pre-rename, **no gap cohort** (no content_market/skillsai/authoring/talent/xapi) |
| 5.2 prereqs (ADR-011) | **PHP 8.3 + MySQL 8.4.** Local XAMPP is still **PHP 8.2.12**; prod RDS is **MySQL 8.0.44** |

**Implication:** this is the ADR-011 "wholesale upgrade", but the divergence is now 641 commits, and the
gap cohort + sentientia rename + QA fixes all post-date the 5.2 baseline. We bring the **current** Sentientia
layer onto the 5.2 core.

---

## ⛔ Hard gates (must be cleared before this candidate can actually deploy)

1. **Production AWS RDS → MySQL 8.4** (currently 8.0.44) — **IT change request.** Moodle 5.2 will not run on 8.0.
2. **PHP 8.3** on production RDS **and** on local XAMPP (currently 8.2.12) — **IT / local upgrade.**
   - Until local PHP is 8.3, **runtime** validation of the 5.2 candidate on this machine is **not possible**;
     we can do code reconciliation + static checks + (if a portable 8.3 is wired) CLI/PHPUnit only.
3. Rollout-gate "one change at a time" — bundling a core upgrade + the Sentientia layer + the live-data
   migration into one cutover is higher-risk; sequence + rehearse carefully.

---

## Approach — OVERLAY, not a 641-commit git merge

A direct `git merge` of 641 commits across the 5.2 core rebase = conflict-hell. Instead, the Moodle-fork
upgrade pattern:

1. **Base** = the clean Moodle **5.2 core** (from `C:\xampp\htdocs\moodle5.2\public`, vanilla 5.2 + the
   Phase-B core/theme rebase already applied per ADR-011 B.3–B.8).
2. **Overlay** = the **current** Sentientia layer from tag `sentientia-milestone-2026-06-19`
   (theme/sentientia + 46 `local_sentientia_*` + 6 blocks + paygw + quizaccess + tool-cert + core-adjacent `my/*`).
3. **Reconcile** = fix what 5.2 breaks (the compat-audit work-list) — mostly the **theme** (renderer/layout/
   mustache/SCSS vs 5.2 boost) and any **gap-cohort** plugins that use changed/removed 5.2 APIs (those plugins
   post-date the P5 deprecation sweep, so they are the prime suspects).

This avoids re-litigating 641 commits of history and re-uses the Phase-B 5.2 core rebase.

---

## Phased execution

- **P1 — Compat audit (RUNNING).** Parallel 5.2/PHP-8.3 scan of theme + 9 gap plugins + core-adjacent +
  stable plugins against the real 5.2 core → the concrete reconciliation work-list. *(Results folded in below.)*
- **P2 — Build the 5.2 candidate tree.** Isolated git worktree on `claude/gap-5.2-candidate`; overlay the
  tagged Sentientia layer onto the 5.2 core.
- **P3 — Reconcile breaks.** Apply the audit work-list: theme 5.2-compat (re-apply Phase-B deltas onto the
  current theme/sentientia) + gap-plugin API fixes. Component-by-component (ADR-011 B.3-B.8 order).
- **P4 — Static validation.** `php -l` sweep, 5.2/8.3 deprecation re-scan (0 findings gate), `install.xml`
  well-formedness, version.php integrity. PHPUnit if a portable PHP 8.3 is available.
- **P5 — Runtime validation.** *Blocked on local PHP 8.3.* When unblocked: `upgrade.php` on a 5.2 clone,
  smoke + the gap-test link gate, Goal A.y matrix.
- **P6 — Package + docs.** Rebuild the complete standalone package on the 5.2 core; update the Deployment
  Guidebook + this plan; tag a `sentientia-milestone-5.2-*` candidate.

---

## Audit work-list (P1 results — 2026-06-19)

**Verdict: the Sentientia layer is 5.2-ready — 0 fatal/high breaks** across theme + 46 plugins + 6 blocks
+ core-adjacent (6 parallel audits vs the real 5.2 core). The theme is already Phase-B-rebased for 5.2;
the 9 new gap plugins were authored against modern namespaced APIs (`core_external\`, `core_ai\`,
`\core\hook\`) present in 5.2; the P5 sweep held across the stable plugins. **9 medium + ~15 low** — all
polish/debt/visual, none blocking. Reconciliation backlog (P3):

| # | Item | Files | Status |
|---|------|-------|--------|
| 1 | **Removed `\core_auth\output\otplogin` type-hint** (gone in 5.2; load-time landmine) | `theme/sentientia/.../login_render.php:116` | ✅ FIXED 2026-06-19 (type-hint relaxed + class_exists-safe) |
| 2 | **BS4 `badge-*` colour classes → BS5 `text-bg-*`** — 5.2 ships BS5 (core uses `text-bg-`, zero `badge-success`); RAG/status badges render colourless otherwise | **~26 files** across local/blocks/theme (incl. `blocks/sentientia_compliance`) | TODO — version-agnostic (5.x is BS5); mechanical sweep |
| 3 | **Legacy global `external_*` → `core_external\` namespace** (works via 5.2 class_alias shim, but deprecated/removal-path) | `sentientia_assistant/classes/external.php`, `external_agent.php`; `payment/gateway/airpay/classes/external/get_form.php` | TODO — debt; mirror `local_sentientia_api` pattern |
| 4 | **Missing AMD build** `enrolledusers.min.js` (require() 404 in prod mode → enrol modal dead) | `local/sentientia_courses/amd/build/` | TODO — run grunt amd; add CI gate |
| 5 | **PII CSV export: no sesskey/CSRF**, hand-rolled headers (5.2 stricter output) | `blocks/sentientia_compliance/export.php` | TODO — add `require_sesskey()` + `\core\dataformat` |
| 6 | **Core-file overlays now shipped by 5.2** (`my/dashboard.php`, `my/switchrole.php`) — 5.2 ships its own; ours overwrites | `my/dashboard.php`, `my/switchrole.php` | TODO — record in `docs/core-mods/`; re-verify each 5.2.x; sanitise raw `$_GET` |
| 7 | Low: dead code / hygiene / forward-looking notes | various | backlog |

**No item blocks building/running the 5.2 candidate** (all medium, shim-compatible or cosmetic). The
biggest user-visible one is #2 (badge colours).

### P3 progress — 2026-06-19 (committed)
- ✅ **#1 otplogin** type-hint relaxed (commit `5d2185ce9`).
- ✅ **#2 badges** — root cause was a *one-line gap*, not 26 files: the theme already ships a BS5
  compat shim (`scss/.../_bs5-compat.scss`) defining badge-primary/success/warning/danger/info; it was
  only missing **`.badge-secondary`** (the 28× token). Added it → all 26 files covered. No sweep.
- ✅ **#3 external_\*** → `core_external\` migrated in all 3 files (assistant `external.php` +
  `external_agent.php`, paygw `get_form.php`); `require_once externallib.php` dropped; `php -l` clean.
- ◑ **#5 (partial)** — `blocks/sentientia_compliance/export.php` gated on the **non-existent**
  `local/courses:manage` (pre-rename) → fixed to `local/sentientia_courses:manage` (was a latent
  403/“capability not found”). The sesskey/CSRF + `\core\dataformat` hardening is **spawned** as a
  follow-up (read-only endpoint, no current caller link — needs a sesskey'd UI button to not break it).
- ✅ **#6 my/ overlays** recorded in `docs/core-mods/2026-06-19-my-overlays-5.2.md` (cutover re-verify gate).
- ⤴ **#4 AMD build** (`enrolledusers.min.js` missing) **spawned** — needs the grunt/Node build toolchain.

**Net:** the 5.2 reconciliation backlog is closed except two scoped follow-ups (AMD build; CSV
sesskey/dataformat). The candidate remains **0-blocker** for build. Next phase (P2/P6): build + package
the 5.2 candidate tree (5.2 core + this reconciled Sentientia layer); runtime validation still gated on
local PHP 8.3 + prod MySQL 8.4.
