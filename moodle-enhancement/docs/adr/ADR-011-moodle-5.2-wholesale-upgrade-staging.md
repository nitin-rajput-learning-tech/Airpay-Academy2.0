# ADR-011 — Moodle 5.2 wholesale upgrade staging plan

**Status:** Proposed (2026-05-23). Sequels ADR-010 (borrow inventory).
**Date:** 2026-05-23
**Deciders:** Nitin Rajput, Claude
**Builds on:** ADR-001 (fork strategy), ADR-009 (bug-class extinction),
ADR-010 (borrow inventory + hold-prod strategy)

---

## Context

Per ADR-010, we held production deploy of v4.1.0 to systematically
borrow the P0/P1 items relevant to our fork. As of 2026-05-23 the P0
tier is **complete** — all 13 borrowable items (P0 #1-11, #13-14)
shipped over a 5-day sprint with paired docs, tests, and Hindi parity.
The two unbuildable items (P0 #12 bundled into #2; P0 #15 deferred to
upstream) have known dispositions.

The next step per Nitin's "do pending and deferred, then upgrade 5.2,
1 by 1 100%" directive is the **wholesale upgrade** — pull the 5.2
codebase, three-way-merge it against our fork, migrate the 30
`local_airpay_*` plugins to any changed APIs, and re-validate the
Goal A.y functional matrix.

This is multi-session work. This ADR is the staging plan.

---

## Two hard blockers (still)

```
1. PHP 8.3 — XAMPP local + AWS RDS PHP runtime
   - XAMPP local: PHP 8.2.12 → 8.3.x. Documented runbook at
     docs/PHP-8.3-UPGRADE-RUNBOOK.md but blocked on no-admin-rights
     constraint. Portable install option exists but interferes with
     other XAMPP services.
   - AWS RDS: blocked on IT — separate change request.

2. MySQL 8.4 — production AWS RDS only
   - Local XAMPP runs MariaDB 10.11.16 which IS compatible with 5.2.
   - Production AWS RDS runs MySQL 8.0.44 → needs 8.4 minor version.
   - Blocked on IT — separate change request.
```

**Implication:** we can do the wholesale codebase merge on local now
(MariaDB local is fine, PHP-8.3-only deprecations can be linted), but
we can't actually *upgrade Moodle to 5.2* until PHP 8.3 lands on both
local AND production simultaneously.

---

## Staging plan — 4 phases

### Phase A — Codebase prep (no PHP version change required)

Sessions A.1-A.5 — work continues today.

**A.1 Snapshot the current fork.**
- Branch `5.2-merge-baseline` off `production` at the last P0 commit.
- Tag `v4.1.1-pre-merge` for rollback safety.
- Inventory: count files in `theme/airpayux/` (514+), enumerate
  `local_airpay_*` plugins (30+), document upstream files that contain
  SENTIENTIA-CORE-MOD markers.

**A.2 Pull Moodle 5.2 source.**
- Download 5.2 stable tarball + decompress to `~/moodle-5.2-source/`.
- Generate diff: `diff -r moodle-5.1.3+ moodle-5.2/ > 5.2-upstream.diff`.
- Categorise: pure additions (no conflict expected), core changes that
  touch files we override, removed files, renamed files.

**A.3 Plugin compatibility scan.**
- For each of 30 `local_airpay_*` plugins, lint with PHP 8.3 (via
  PHPStan if installed, otherwise via `php -l` + manual `grep` for
  known-deprecated APIs from ADR-010 P5 sweep).
- Cross-reference P5 deprecation sweep results
  (`docs/P5-DEPRECATION-SWEEP-2026-05-23.md`) — 6 files identified for
  ~70 min of mechanical migration.
- Migration commits land here, BEFORE the upstream merge.

**A.4 Theme conflict map.**
- For every file in `theme/airpayux/` where the corresponding upstream
  file changed in 5.2, mark as CONFLICT.
- Generate `docs/5.2-theme-conflict-map.md` — each conflict gets a row:
  `[file] [our last change] [upstream change] [resolution strategy]`.

**A.5 Test surface inventory.**
- Document existing PHPUnit count (today ~80 across local_airpay_*).
- Identify which tests will need re-running after merge (likely all).
- Plan re-run cadence — CI on every merge commit + manual on push.

**Phase A exit criteria:**
- Branch + tag created.
- 5.2 source diff generated.
- Plugin compatibility matrix complete.
- Theme conflict map complete.
- Test surface counted.
- One PR per pre-merge plugin migration (P5 sweep items shipped
  individually before the wholesale merge starts).

### Phase B — Merge (PHP 8.3 must land first)

Sessions B.1-B.8 — blocked on PHP 8.3 on local XAMPP.

**B.1** Upgrade local XAMPP to PHP 8.3. Verify `php -v`, restart
Apache, smoke-test login + course view + dashboard.

**B.2** Merge upstream 5.2 — `git merge moodle-5.2-source` on the
`5.2-merge-baseline` branch. Expect 50-200 conflicts.

**B.3-B.8** Resolve conflicts one component at a time:
- B.3 — Theme files (heaviest — 514+ overrides)
- B.4 — Core lib/ + admin/ changes (lightest if we've kept hands off)
- B.5 — Block plugins (block_myoverview, etc.)
- B.6 — Activity modules (we have a few mod_airpay_* — should be light)
- B.7 — Backup/restore plumbing (medium, given P0 #11 surface)
- B.8 — Frontend SCSS rebuild + AMD bundle regenerate

After each session: PHPUnit + Goal A.y functional matrix re-run.

**Phase B exit criteria:**
- `upgrade.php` completes successfully against 5.2.
- All PHPUnit passes (or known-skipped with documented reasons).
- Goal A.y matrix re-runs clean (138 URLs).
- AMD bundles all rebuild via grunt with portable Node 22.

### Phase C — Production rehearsal (still on local)

Sessions C.1-C.4 — uses the production-like data set.

**C.1** Pull a fresh production DB snapshot (2,871 users, 411 courses,
3 tenants) — same fixture we used during Phase 16 testing.

**C.2** Run `php admin/cli/upgrade.php` against the snapshot DB.
Capture every schema change to a `5.2-prod-upgrade.log`. Identify
long-running migrations that need maintenance-window timing.

**C.3** Re-run Goal A.y against the production-data snapshot. Bug-fix
anything that surfaces (likely zero — Phase 16 already caught the
common production-vs-fixture diffs).

**C.4** Time the upgrade. Document the maintenance window the
production deploy will need (expect 15-45 min for the DB pass).

**Phase C exit criteria:**
- Production-data snapshot upgrades cleanly.
- All Goal A.y URLs work with real data.
- Maintenance window time estimate documented.

### Phase D — Production deploy (blocked on MySQL 8.4 + IT change window)

Sessions D.1-D.3.

**D.1** Schedule IT change window. Communicate to learners.

**D.2** Deploy steps (rehearsed in Phase C):
1. Put site in maintenance mode
2. DB snapshot (RDS automated + manual logical backup)
3. Pull `5.2-merge-baseline` to production codebase
4. Run `admin/cli/upgrade.php`
5. Smoke-test critical paths (login, gradebook, SCORM, push)
6. Exit maintenance mode
7. Monitor logs for 24h

**D.3** Post-deploy:
- Tag `v4.2.0-on-moodle-5.2` on production branch.
- Update PROJECT-STATE.md.
- Delete the borrow-shim code where the upstream equivalent is now
  live (per each P0 borrow's "migration on 5.2" section).
- Bump customer's Sentientia LMS release (1.5.x → 1.6.0).

**Phase D exit criteria:**
- Production runs Moodle 5.2.
- 24-hour soak clean.
- Borrow shims retired.

---

## Risk register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| Conflict storm in theme_airpayux | High | High | Phase A.4 conflict map; Phase B.3 isolated session |
| PHP 8.3 breaks a `local_airpay_*` plugin | Medium | Medium | Phase A.3 lint scan; one-PR-per-plugin migration |
| MySQL 8.4 migration window | Low | High | Phase C upgrade rehearsal against snapshot |
| AMD build fails on 5.2 Gruntfile | Low | Medium | Portable Node 22 already in place |
| Borrow shim conflicts with upstream | Medium | Low | Each shim's doc has migration steps |
| Production DB snapshot doesn't restore | Low | Critical | Phase D.2 manual logical backup before pull |

---

## What "1 by 1 100%" means in this context

Each phase exit gate is a hard gate. We do not start Phase B until
Phase A is 100% complete. We do not start Phase C until Phase B's
PHPUnit + Goal A.y both pass.

This is slower than parallelising but matches Nitin's directive and
makes rollback at any phase boundary trivial.

---

## Open questions (for Nitin)

1. **PHP 8.3 install — who and when?** XAMPP local upgrade currently
   blocked on admin rights. Options:
   - IT-installed XAMPP rebuild
   - Portable PHP 8.3 alongside (managed by us)
   - Switch local dev to WSL2 + native PHP 8.3 (heavier change)
2. **Maintenance window for the production cutover** — preferably a
   weekend; 4h window with 2h reserved for rollback if needed.
3. **Communication plan** to active learners during the window.
4. **Should the next session start Phase A.1-A.2 (codebase prep)?**
   It's productive work even with PHP 8.2 still on local.

---

## Decision

We adopt the 4-phase staging plan above.

Next session: Phase A.1 — branch `5.2-merge-baseline` + tag
`v4.1.1-pre-merge` + start the source diff against 5.2 upstream.

---

## References

- ADR-010 — Borrow inventory + hold-prod strategy
- `docs/PHP-8.3-UPGRADE-RUNBOOK.md` — Local PHP 8.3 install steps
- `docs/P5-DEPRECATION-SWEEP-2026-05-23.md` — Pre-merge plugin migrations
- `docs/p0-borrows/` — 5 borrow guides each with "migration on 5.2"
- `docs/HANDOFF-2026-05-23.md` — Operational items requiring user action
