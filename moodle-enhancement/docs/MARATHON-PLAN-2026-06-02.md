# Sentientia Stability Marathon — 18h Execution Plan
**Date:** 2026-06-02 (morning start) · **Owner:** Nitin Rajput · **Author:** Claude (prepared 2026-06-01 night)
**Goal:** Advance the Sentientia independence program (ADR-018) toward a fully decoupled, 100% stable, sellable product — Airpay = customer-zero — without ever changing live behaviour. Every increment additive, flag-gated, default-legacy, locally rehearsed, FF-pushed to the production **branch** only.

---

## 0. READ FIRST (cold-start, ~20 min, do not skip)

1. Root `CLAUDE.md` + `.claude/CLAUDE.md` + `.claude/rules/*.md`.
2. `moodle-enhancement/PROJECT-STATE.md` (current phase).
3. ADRs: `docs/adr/ADR-018` (roadmap), `ADR-020` (org — read the **Progress log** at the end), `ADR-021` (registry).
4. State card: `state-cards/sentientia_core-state.md` (the live source of truth for the seams).
5. Confirm git state: `git fetch origin production` → **production tip should be `99bfc8ba9`** (W3.2a.1). Local main-DB `local_sentientia_core` is at version **2026060102 / 0.4.1-alpha**.

### Shipped already (do NOT redo)
- W2 tenant_identity seam + ~22 caller migrations · W4 tenant registry (schema + seam + CLIs + admin UI).
- **W3.2a** org schema (`local_sentientia_org_unit` + `_member`) + read API.
- **W3.2a.1** manager **direct edge** (`org_member.managerid`) — `manager_via_model`/`direct_reports`/`is_manager` read it; unit `role` reserved for future "unit lead". 14/14 PHPUnit green.

---

## 1. TOKEN & MODEL EFFICIENCY (read before touching anything)

These are hard-won from the 2026-06-01 session — they are the difference between finishing 18h of work and stalling at hour 8.

1. **START A FRESH SESSION.** Do NOT resume the 2026-06-01 conversation. It carries a **376-entry task ledger that re-injects ~10k tokens every single turn**. A fresh session drops it. This is the single biggest lever. Keep the new session's task list **small** (≤ ~15 live tasks; delete/complete aggressively).
2. **Avoid worktree-file READS.** Reading any file under `D:\Claude Local\...` re-injects the full CLAUDE.md + 3 rules (~20k). Pattern: **edit the DEPLOYED file** (`C:\xampp\htdocs\moodle5\public\...`, no injection) → `php -l` → `cp` deployed→worktree via bash (no injection) → verify `git diff --stat` in the worktree before commit. Only Read a worktree file when unavoidable.
3. **Batch tool calls.** Multiple `Edit`s per message; launch independent background jobs together (PHPUnit `init` + worktree checkout in parallel — saves ~60s/cycle).
4. **Don't re-run PHPUnit `init` unless `install.xml` changed.** It's ~2–3 min. If only PHP/test logic changed, run the test directly against the existing test DB.
5. **Model tiers:** main loop on **Sonnet** for implementation. Escalate to **Opus** only for genuine design forks / ambiguous modelling decisions (like the 2026-06-01 manager-edge call) — surface those to Nitin via AskUserQuestion, don't guess. Use **Explore** subagent for broad code searches (returns the conclusion, not file dumps) and cheap subagents for mechanical grep/lint sweeps.
6. **Workflows:** only if Nitin opts in. Good fit for the W6 de-brand fan-out across many files. Otherwise sequential.
7. The scssphp "Array to string conversion" warning on upgrade is **pre-existing noise** — ignore.
8. The pre-commit hook WARN "version.php component local_sentientia_core may not match directory sentientia_core" is a **known false positive** — commit proceeds.

---

## 2. HARD GUARDRAILS (never violate — STOP and summarize if blocked)

- **NO** feature-flag flip on live; **NO** live RDS writes; **NO** prod deploy to live airpay.academy. Push to the production **branch** only (it is NOT auto-deployed).
- **NO** `airpay_* → sentientia_*` component RENAME execution (capability re-registration = human-gated). Marathon may PREP/design it only.
- **NO** Moodle-engine/core work beyond documented additive mods; **NO** > 50-file op without a checkpoint.
- **NEVER** `--no-verify`; **NEVER** commit credentials; **NEVER** `[CONFIRM]`-gated POSTs (ElevenLabs/Gamma/Anthropic/live API) without explicit approval.
- **NEVER clobber the owner's uncommitted WIP** (`local_airpay_compliance_report/**` + scratch `tools/_*.php`). Re-check `git status` every cycle; stage only your own explicit paths (never `git add -A`).
- Each shipped unit must be **atomic** (complete + tested + dormant). Don't start a unit you can't finish in the remaining budget.
- **STOP + post a summary** when: the safe backlog is exhausted, OR 2 consecutive iterations fail, OR the same error recurs 3×, OR the only remaining work is human-gated, OR a decision genuinely needs Nitin.

---

## 3. PER-INCREMENT DISCIPLINE (every ship, no exceptions)

```
edit deployed (C:\xampp) → php -l (abort on error) → PHPUnit (if testable on vanilla DB)
→ local rehearse (admin/cli/upgrade.php --non-interactive + purge if schema/version changed)
→ worktree add -b <branch> <wt> refs/remotes/origin/production
→ cp deployed→worktree (bash) → git diff --stat (confirm only your files)
→ stage explicit paths → commit (12-check hook) → FF push <sha>:refs/heads/production
→ worktree remove --force + branch -D + prune + git fetch origin production
→ update ADR progress log + state card (stage them too — hook check #12)
```
Default-legacy + dormant throughout. A premature flag flip must never break auth / manager resolution / tenant validation.

---

## 4. TASK SEQUENCE (≈18h, ordered; each block = its own atomic ship)

### Block A — W3.2b dual-write reconciler  (≈3.5h, ~250k tok)  ← START HERE
The cron that mirrors the legacy org graph into the (empty) `org_*` tables so the model stays warm for an eventual flip. **Default-OFF** flag (`org_dualwrite_enabled`) so deploy changes nothing.
Files (all `local/sentientia_core/`):
- `classes/org_legacy_source.php` — reads `user.open_path` (→ unit tree via segments) + `user.open_supervisorid` (→ `managerid` edge). **Injectable interface** so the reconciler is unit-testable WITHOUT BizLMS columns (tests pass a synthetic array source).
- `classes/org_reconciler.php` — consumes a source iterable; idempotent upsert of `org_unit` (one per distinct open_path segment; `parentid` from prior segment; `tenantrootid` = segment[0]; `idnumber` = costcenter id; name from `local_costcenter` if present else "Unit <id>") + `org_member` (one row per user in leaf unit; `managerid` = supervisor). Idempotent (re-run = no-op).
- `classes/task/reconcile_org.php` + `db/tasks.php` — scheduled task, no-ops unless `org_dualwrite_enabled` is ON. Tenant-scoped.
- `settings.php` — register `org_dualwrite_enabled` (default 0). `tests/org_reconciler_test.php` — synthetic-source tree + edge + idempotency + tenant-scope.
- version bump → 2026060103 / 0.5.0-alpha. State card + ADR-020 progress log.

### Block B — W3.3 backfill + parity CLIs  (≈3h, ~200k tok)
- `cli/backfill_org.php` (`--dry-run` default, `--execute`, `--tenant=`) — runs the reconciler once over all users; reports unit/member counts.
- `cli/parity_check_org.php` — for every user, assert `org::manager_via_model(u) == legacy open_supervisorid(u)` and unit membership matches open_path leaf; exit-coded; **MUST hit 100% before any flip is even considered**. Mirror the `parity_check_tenants.php` shape.
- Rehearse both on the local prod-data DB (2,871 users) with `org_dualwrite_enabled` toggled ON locally; capture counts; flip back OFF. Tests for the parity comparator. version bump. ADR + state card.

### Block C — W3.4 cutover PREP (flip itself stays human-gated)  (≈2h, ~150k tok)
- Grep for remaining DIRECT `open_supervisorid` / `team_manager` readers; migrate them onto `org::manager_id_of()` / `org::direct_reports()` in reviewed batches (so when the flag flips, readers switch source automatically). Each batch its own commit + test.
- Author `docs/RUNBOOK-org-cutover.md`: the ZEEA-first flip checklist (clone-DB rehearsal → parity 100% → site-admin flip `org_legacy` OFF → soak → Public → Airpay → 1-release legacy shim → removal ADR). **Do NOT flip anything.**

### Block D — W6 de-brand + Wave-1 safe backlog  (≈3h, ~200k tok) — infinite safe filler
Pull from the autonomous Wave-1-class backlog (all additive, mostly UI/lang/docs):
- OTP login button → theme `login_submit` string (5 locales) + `otploginform.mustache`.
- eAbyas/epsilon copyright + comment hygiene (~10 metadata files; `privacy:metadata` Epsilon→Sentientia in hi/kn/mr/sw).
- Catalogue category-badge dark-mode AA contrast; consolidate Bootstrap `text-*` dark overrides; dashboard Chart.js dark label colours.
- Decouple-doc hygiene + stylelint guard. Then any other additive de-Moodle / dark-mode-AA / dead-code item. Each tiny + shipped independently; deploy theme `version.php` bump when SCSS/lang changes; visual-verify in Chrome MCP (logged in as qa_siteadmin, dark mode).

### Block E — W5 rename PREP only (no execution)  (≈2h, ~120k tok)
- Inventory every `local_airpay_*` component + cross-references; design the rename codemod + capability/version migration strategy; write `ADR-022-component-rename.md`. **No rename executed** (human-gated).

### Block F — Hardening + verification + closeout  (≈2.5h, ~150k tok)
- Broaden PHPUnit across the seams; confirm CI gate green; fix any flake.
- Chrome MCP visual pass on every UI-touching change (desktop + 590px mobile, dark mode), screenshots → `docs/visual-evidence/2026-06-02/`.
- Update `PROJECT-STATE.md`; write the marathon end-summary; final `git fetch` + confirm production tip; ensure no worktrees leak (`git worktree list`), owner WIP intact.

**Contingency:** if a block overruns, drop Block E (pure prep) first, then trim Block D filler. Blocks A→C are the priority spine (they complete the org model end-to-end behind flags).

---

## 5. DEFINITION OF DONE (per block & overall)
- `php -l` clean · PHPUnit green on the isolated DB · local upgrade clean · pre-commit 12/12 · FF push succeeded · worktree torn down · ADR/state-card updated · owner WIP untouched · **live behaviour unchanged (all flags default-legacy/OFF, model dormant)**.
- Overall: production branch carries W3.2b + W3.3 + W3.4-prep (+ W6/W5-prep as time allows), every piece reversible, with a clean cutover runbook awaiting Nitin's gated flip. Nothing deployed to live; nothing flipped.
