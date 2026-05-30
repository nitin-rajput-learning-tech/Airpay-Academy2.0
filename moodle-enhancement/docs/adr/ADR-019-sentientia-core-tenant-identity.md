# ADR-019 — Sentientia Core + the tenant-identity seam (Independence Wave 2)

**Status:** Accepted (scaffold shipped 2026-05-30) · **Owner:** Nitin Rajput
**Parent:** ADR-018 (Sentientia independence + stabilization roadmap), Wave 2.
**Pairs with:** `docs/DEPRECATION-SCHEDULE.md` (row 7), `docs/BIZLMS-MIGRATION-NARRATIVE.md`.

## Context

ADR-018 found that `$USER->open_path` — the BizLMS cost-center path — is the
**hardest, most pervasive** coupling to the BizLMS heritage: 24+ files branch on
it directly and ~294 touch it. You cannot move Sentientia off BizLMS while every
plugin reads that column inline. Wave 2's job is to introduce the *seam* that
lets callers stop doing so, **without changing behaviour for customer-zero**.

The non-negotiable constraint from ADR-018: **Airpay (customer-zero) never sees a
regression.** So the seam must default to today's exact behaviour and only change
source when an operator (or a future, rehearsed migration) deliberately flips it.

## Decision

Create **`local_sentientia_core`** — the first "Sentientia layer" plugin, sitting
above the `local_airpay_*` / BizLMS heritage — and give it a single
**`tenant_identity`** service:

- `tenant_identity::root_for_user($user)` / `::root_for_current_user()` become the
  one sanctioned way to resolve a user's tenant root.
- Behind a **default-ON** admin setting `tenant_identity_legacy`, the service
  delegates to the existing `local_airpay_core\tenant` open_path parser — so the
  result is byte-identical to current production.
- When the setting is OFF (reserved for Wave 3+), it will read from a
  Sentientia-owned tenant registry. **That registry does not exist yet**, so the
  OFF path currently falls back to legacy resolution (with a `DEBUG_DEVELOPER`
  note) — meaning even a mis-flip cannot break authentication.
- No hard dependency on `local_airpay_core`: the delegation is `class_exists`-guarded
  with an inline open_path parser fallback, so the seam is self-contained.

### Explicitly NOT in this scaffold (staged / human-gated)
- **Migrating the ~24 existing `open_path` callers** onto `tenant_identity`. This
  is a mechanical but wide refactor; it ships incrementally, file-by-file, each
  behaviour-preserving, in later commits — not here.
- **No DB schema.** Wave 2 adds zero tables. The tenant *registry* table is
  Wave 4 (replaces the hardcoded `tenant::VALID_TENANTS = [1,77,177]`).
- **`local_costcenter` → `local_sentientia_org`** org-hierarchy migration is
  Wave 3 (needs a clone-DB rehearsal + its own ADR).
- **Not installed on production** by this change; the scaffold lives in the repo,
  is exercised by the CI PHPUnit gate, and installs when the owner deploys.

## Why a seam-with-default-ON-flag, not a rewrite

- **Reversible + invisible:** default ON = today's behaviour; nothing changes
  until a flag flips, and the flip is operator-controlled + rehearsable.
- **Incremental:** callers migrate one at a time onto a stable interface, each a
  no-op behaviourally, so review + rollback stay small.
- **Honest about scope:** the hard part (24 callers + a real tenant registry) is
  sequenced behind human gates, not smuggled into a "create plugin" commit.

## Consequences

- **Positive:** there is now a single, tested place to resolve tenancy; new code
  has a non-BizLMS API to target immediately; the decouple has a load-bearing
  foundation instead of just documentation.
- **Negative / accepted:** until the callers are migrated (separate work) the seam
  is used by nobody, so it changes nothing at runtime yet — it's groundwork. The
  `VALID_TENANTS` hardcode + `local_costcenter` coupling are untouched (their
  waves).
- **Verification:** 6 PHPUnit cases (`tenant_identity_test.php`) cover open_path
  parsing, the default-ON flag, the OFF fallback + debug, and the logged-out case;
  they run under the CI `phpunit-52` gate (auto-discovered for `local/sentientia_*`).

## Next (Wave 2 continuation — needs Nitin's go per item)
1. Migrate `open_path` callers onto `tenant_identity` in safe batches (start with
   read-only reporting paths; defer access-control paths to a reviewed batch).
2. ADR-020 / Wave 3: `local_sentientia_org` + the `local_costcenter` migration
   (clone-DB rehearsal first).
3. ADR / Wave 4: the tenant registry table replacing `VALID_TENANTS`.
