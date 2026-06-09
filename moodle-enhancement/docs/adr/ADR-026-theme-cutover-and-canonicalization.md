# ADR-026 — Theme cutover & canonicalization (`theme_airpayux` → `theme_sentientia`)

- **Status:** Proposed — gated (decision + production execution are Nitin's; the
  production *active-theme switch* is gated on the Moodle 5.2 cutover, ADR-011)
- **Date:** 2026-06-09
- **Decision-makers:** Nitin Rajput
- **Implementer:** Claude (engineering) on Nitin's go
- **Relates to:** ADR-011 (5.2 wholesale upgrade staging), ADR-018 (independence
  + stabilization roadmap), ADR-024 (absorb + de-brand BizLMS), ADR-025
  (component rename `airpay_* → sentientia_*`, COMPLETE). Prompted by the theme
  topology finding during the 2026-06-09 loading-fix reconciliation
  (`docs/audits/AMD-LOADING-FIXES-2026-06-09.md` §6).

---

## Context

The active theme exists in **three divergent states**, and the git-tracked
"source" is *not* the version that is actually served. Verified 2026-06-09:

| Where | Theme | Files | Brand | Refactor state |
|-------|-------|-------|-------|----------------|
| **`production` git** (repo-root `theme/airpayux/`) | `theme_airpayux` | 591 | **branded** (airpayux) | **pre-refactor** (Boost-like; no `role_detector`, no `output/traits/*`, no scss `partials/*`, no `db/hooks.php`, no `amd/src` modules) |
| **`5.2-merge-baseline` git** (`moodle-enhancement/theme/airpayux/`) | `theme_airpayux` | 114 (+ the 591 repo-root copy) | branded | **refactored** (the role_detector + trait decomposition + scss partials + 5.2 hooks live *here*) |
| **live webroot** (`C:\xampp\htdocs\moodle5\public\theme\sentientia\`) | `theme_sentientia` | 708 | **de-branded** (sentientia) | refactored **+ app-shell** (sidebar, role-aware 1059-line `dashboard.mustache`, `shell.mustache`) |

There are **0** `theme/sentientia` files anywhere in `production` git. The
de-branded, refactored theme that users actually see is **untracked** — it lives
only in the deployed webroot.

### How this arose

The 5.2-targeted theme refactor (ADR-011 staging work — `role_detector`, the
`core_renderer` trait split, the scss partial decomposition, the Moodle-5.2 hook
migration) and the airpayux→sentientia de-brand (ADR-024/025) were applied on the
**5.2 line + directly in the webroot**, but the result was **never cut over to
`production` git**. `production`'s theme is frozen at the pre-refactor, branded
snapshot. ADR-025 is recorded COMPLETE for the *plugin* renames, but the *theme*
de-brand reached only the webroot/5.2 line, not `production` git.

### Why it matters

1. **A clean deploy-from-`production`-git ships the wrong theme** — the stale,
   branded, pre-refactor `theme_airpayux`, not the `theme_sentientia` users see.
2. **Bus-factor / preservation risk.** The real product theme exists only in the
   untracked webroot. A clean reinstall or a new clone loses it — the exact
   failure mode `AMD-LOADING-FIXES-2026-06-09.md` §6 was written to guard against.
3. **The deploy pipeline sources from the webroot, not git.**
   `tools/overlay-airpay-customs.ps1` has `$Source = …\moodle5\public` and does
   `Copy-Tree 'theme' 'theme\sentientia'` — i.e. it treats the hand-maintained
   webroot as the source of truth. That is backwards from git-as-source-of-truth.
4. **The two-track reality is now in the commit log.** The 2026-06-09 loading
   fixes had to be applied *twice* — once to `production`'s branded `theme_airpayux`
   (`0a3a1c2cc`) and once to the 5.2 line's refactored theme (`a57d93e67`) —
   because the two themes are structurally different trees. Every future theme fix
   pays this double cost until the divergence is resolved.
5. **It is a facet of the larger 5.2 cutover (ADR-011).** The refactored theme
   targets 5.2-era APIs (BS5 variables, the hook subsystem, `core_user/repository`
   prefs). Backporting it wholesale onto production-5.1.3 risks
   compile/runtime mismatches — so "switch production's active theme" cannot be
   fully decoupled from "upgrade production to 5.2."

---

## Decision

Split the problem into **two independently-schedulable moves**, because they have
very different risk and timing:

### Move 1 — Canonicalize the theme source INTO git (urgent, low-risk, do regardless of 5.2 timing)

Commit the de-branded, refactored theme (`theme_sentientia`, the 708-file webroot
tree) into git as a **tracked** source, on a review branch, so it stops being a
webroot-only artifact. This **does not change what production serves** — it only
ends the preservation/bus-factor risk and lets the overlay (eventually) source
from git instead of the hand-maintained webroot. Ship it behind the existing
divergence — `production`'s *active* theme stays `theme_airpayux` until Move 2.

### Move 2 — Switch production's ACTIVE theme `theme_airpayux` → `theme_sentientia` (gated on ADR-011)

Flip airpay.academy to serve `theme_sentientia`. **Gated on the Moodle 5.2
cutover** because the refactored theme targets 5.2 APIs. Execute as part of (or
immediately after) ADR-011's 5.2 wholesale cutover, on restored-prod-data
rehearsal first, with the `config.theme` switch + `purge_caches` as the last step
and a one-line rollback (`config.theme = theme_airpayux`).

**The gating question for Nitin (drives which path Move 2 takes):**
> Is the Moodle 5.2 cutover (ADR-011) **near-term (≤ weeks)** or **deferred
> (months)**?
> - **Near-term →** do Move 1 now; let Move 2 ride the 5.2 cutover (no double-work).
> - **Deferred →** do Move 1 now; additionally decide whether to **backport** the
>   theme refactor onto a 5.1-compatible `theme_sentientia` so production isn't
>   carrying a stale theme for months (Option C below), accepting the backport cost.

---

## Options considered

| Option | What | When it's right | Cost / risk |
|--------|------|-----------------|-------------|
| **A — Ride the 5.2 cutover** (recommended if 5.2 is near-term) | Theme de-brand + refactor lands on production *as part of* the ADR-011 5.2 cutover | 5.2 cutover ≤ weeks away | None extra — but production keeps shipping the stale theme from git until then |
| **B — Canonicalize-in-git now, switch-later** (recommended baseline) | Move 1 now (track the source); Move 2 with 5.2 | Always safe; decouples preservation from the active-theme switch | Low — committing the tree + wiring the overlay to source from git |
| **C — Backport refactor to 5.1** | Bring role_detector/traits/partials/hooks onto a 5.1-compatible `theme_sentientia` and switch production now | 5.2 cutover deferred months AND stale theme is unacceptable | Medium-high — must strip/shim 5.2-only APIs; re-tests the theme on 5.1 |
| **D — Document + defer (do-nothing)** | Accept divergence; rely on the 5.2 mirror branch to keep the live line fixed | Never preferred (leaves the preservation risk open) | The webroot-only theme stays a bus-factor risk |

**Recommendation: B as the baseline (do Move 1 now), then A for Move 2 if 5.2 is
near-term, else revisit C.** Move 1 is safe, reversible, and closes the real risk
(untracked product theme) today; Move 2's path is Nitin's call on the 5.2 timeline.

---

## Consequences

**Positive**
- The product theme becomes git-tracked → survives clean reinstalls / new clones.
- Future theme fixes are applied **once** (to the canonical tree), not twice.
- The deploy pipeline can move to git-as-source (overlay `$Source` → a git
  checkout, not the hand-maintained webroot).
- `production` git stops advertising a theme it doesn't actually serve.

**Negative / cost**
- Move 1 commits a large tree (≈708 files) — one big, reviewable commit.
- Move 2 cannot be fully decoupled from the 5.2 upgrade without backport work (C).
- Until Move 2, the documented divergence persists (mitigated, not removed).

**Neutral**
- No user-visible change from Move 1 (production keeps serving `theme_airpayux`).
- The overlay's `Repair-AmdModuleNames` step (shipped `0a3a1c2cc`) already makes a
  git→webroot theme deploy AMD-safe, so it is ready for the source flip.

---

## Implementation actions

**Move 1 (proposed, on Nitin's go — review branch, not direct to production):**
1. Snapshot the webroot `theme/sentientia` (708 files) into git as a tracked tree.
   Decide the canonical git path (`theme/sentientia/` to mirror the deployed
   layout, vs `moodle-enhancement/theme/sentientia/` as working source).
2. `php -l` every PHP file; run the conflict-marker + lint gates.
3. Point `overlay-airpay-customs.ps1 $Source` at a git checkout (or add a
   `git → webroot` sync step) so the webroot becomes a *regenerable artifact*.
4. Verify a from-git deploy reproduces the live theme byte-for-byte (the
   `Repair-AmdModuleNames` gate already asserts 0 stale AMD names).
5. Commit on a branch; hand a compare URL. Do **not** switch `config.theme`.

**Move 2 (gated on ADR-011 5.2 cutover):**
1. On the restored-prod-data rehearsal instance, upgrade to 5.2 (ADR-011).
2. Confirm `theme_sentientia` installs + renders on 5.2 (charts, sidebar, login,
   dashboard, mobile, dark) across all 8 personas.
3. Flip `config.theme = theme_sentientia`; `purge_caches`.
4. Rollback = `config.theme = theme_airpayux`; `purge_caches` (instant revert).
5. Retire `theme_airpayux` from git only after a soak period.

---

## References

- `moodle-enhancement/docs/audits/AMD-LOADING-FIXES-2026-06-09.md` §6 (the topology
  finding that prompted this ADR).
- ADR-011 (5.2 wholesale upgrade staging) — Move 2 is gated on it.
- ADR-024 / ADR-025 (de-brand + component rename) — the theme is the last
  airpay→sentientia holdout still branded in `production` git.
- Branches `fix/loading-reconcile-2026-06-09` (merged → `production` `0a3a1c2cc`) +
  `fix/loading-reconcile-52-2026-06-09` (merged → `5.2-merge-baseline` `a57d93e67`)
  — the double-applied loading fixes that exposed the two-track theme reality.

## Open questions for Nitin

1. **5.2 cutover timing** — near-term or deferred? (Drives Move 2's path.)
2. **Canonical git path** for the tracked theme — deployed-layout `theme/sentientia/`
   or working-source `moodle-enhancement/theme/sentientia/`?
3. **Overlay source** — flip `$Source` to a git checkout now (git-as-source), or
   keep webroot-sourced until the 5.2 cutover?
4. Should `theme_airpayux` be **kept** as a fallback theme post-cutover, or retired?
