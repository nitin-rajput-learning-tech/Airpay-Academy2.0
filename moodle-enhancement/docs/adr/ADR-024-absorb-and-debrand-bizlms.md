# ADR-024 - Absorb & de-brand the purchased BizLMS suite into Sentientia

- **Status:** Proposed (2026-06-04)
- **Owner:** Nitin Rajput
- **Relates to:** ADR-018 (independence roadmap), ADR-020 (org model),
  ADR-021 (tenant registry)
- **Companion audit:** `docs/DE-BRAND-AUDIT-2026-06-04.md`

## Context

Airpay **purchased** the eAbyas BizLMS source (22 `local_*` plugins,
`@package BizLMS`, `Copyright eAbyas Info Solutons Pvt Ltd`, GPLv3, Moodle
3.3/3.4 vintage). Airpay Academy production runs on it; the full source is
mirrored locally at `Moodle Backup/01-production-codebase/html/`.

Directive (Nitin, 2026-06-04): *"as we had purchased the code from BizLMS,
code will not have anything as BizLMS, every dependency needs to be created in
Sentientia as its own."*

Findings (see audit):
- The deliverable code must carry **zero** "BizLMS"/"eAbyas" identity.
- ~16 of the 22 eAbyas plugins already have first-party `airpay_*`/
  `sentientia_*` forks; ~6 need disposition; the tenant substrate
  (`local_costcenter` + scattered `open_*` columns) is the one deep piece.
- De-brand surface: 331 `bizlms` + 608 `eabyas` files in the purchased
  source; 222 + 16 + 274 in our own code.
- This is the ADR-018 endgame made concrete and is **owned outright now**
  (no longer "an external dependency we cannot touch" - we bought it).

## Decision

Absorb every BizLMS dependency into Sentientia as first-party, fully
de-branded code, in additive flag-gated waves (Wave 0-5 in the audit),
**without ever changing live Airpay Academy behaviour**:

1. **De-brand** all "BizLMS"/"eAbyas" identity (cosmetic first; functional
   identifiers gated).
2. **Own the substrate** - the `open_*` columns (already reproduced
   first-party by `local_sentientia_core/cli/bootstrap_substrate.php`) and the
   `local_costcenter` org/tenant model (already being replaced by the
   `local_sentientia_core` tenant_identity / org / tenant_registry seams).
3. **Retire vs absorb per plugin** - where a first-party fork already exists,
   retire the eAbyas original and de-brand the fork; where none exists
   (`costcenter`, `location`, the `local_users` admin UI), build first-party.
4. **Migrate readers** off `open_*` onto the Sentientia seams once parity is
   100% (flag-gated, ADR-020/021).
5. **Rename + re-register** components last (HUMAN-GATED - capability
   re-registration carries migration risk on 2,888 live users / 3 tenants).

## Governance / guardrails

- Additive + flag-gated; default behaviour = today's production until a flag
  flips. No live RDS writes, no live flag flips, production **branch** only.
- Component renames / capability re-registration are **human-gated** (Nitin
  approves each).
- Each wave: local rehearsal -> PHPUnit -> pre-commit 12-check -> FF push ->
  ADR/state-card update. Owner WIP never clobbered.
- GPLv3 retained (purchased code is GPL); attribution updated to
  Airpay/Sentientia; original eAbyas copyright lines removed from de-branded
  files per the purchase (Airpay holds the rights).

## Consequences

- **+** A genuinely self-owned, de-branded, sellable Sentientia LMS with no
  BizLMS identity and every dependency first-party.
- **+** Clean-installable today via `bootstrap_substrate.php` (the substrate
  no longer requires eAbyas to boot - see `INSTALL-SENTIENTIA.md`).
- **-** Large surface (600+ source files to de-brand; 274 reader files to
  migrate). Multi-wave, multi-session. Wave 5 (rename) is irreversible-ish
  and gated.
- **Risk** mitigated by: flags default-legacy, parity CLIs before any flip,
  ZEEA-first cutover rehearsal, 1-release legacy shims.

## Next step

Wave 0 (verify the 6 uncertain dispositions) + Wave 1 (cosmetic de-brand of
our own code), both safe + additive, begin once Nitin confirms scope/sequence.
