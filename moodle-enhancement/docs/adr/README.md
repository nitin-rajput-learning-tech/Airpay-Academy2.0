# Architecture Decision Records — Sentientia LMS

Every cross-cutting architectural decision lands here. ADRs are immutable once
accepted; superseded decisions get a new ADR that references the old one.

## Index

| # | Title | Status | Date |
|---|---|---|---|
| [ADR-001](ADR-001-fork-strategy-and-product-pivot.md) | Fork Strategy & Product Pivot to Sentientia LMS | Accepted | 2026-05-20 |
| [ADR-018](ADR-018-sentientia-independence-and-stabilization-roadmap.md) | Independence + 100%-Stabilization Roadmap (Waves 1–6) | Accepted (roadmap) | 2026-05-29 |
| [ADR-019](ADR-019-sentientia-core-tenant-identity.md) | `local_sentientia_core` + the `tenant_identity` seam (Wave 2) | Accepted (shipped) | 2026-05-30 |
| [ADR-020](ADR-020-sentientia-org-hierarchy-migration.md) | `local_sentientia_org` + org-hierarchy migration (Wave 3) | 3.1–3.3 shipped · 3.4 cutover gated | 2026-06-02 |
| [ADR-021](ADR-021-sentientia-tenant-registry.md) | `tenant_registry` + multi-customer tenant table (Wave 4) | Proposed — gated | 2026-06-01 |
| [ADR-022](ADR-022-component-rename.md) | `local_airpay_*` → `local_sentientia_*` component rename (Wave 5) | Proposed — gated | 2026-06-02 |
| [ADR-026](ADR-026-theme-cutover-and-canonicalization.md) | Theme cutover & canonicalization (`theme_airpayux → theme_sentientia`) | Accepted — Move 1 ready · Move 2 gated on 5.2 | 2026-06-09 |

> The index above tracks ADR-001 + the active **independence cluster (018–022)**.
> ADRs 002–017 (feature-area decisions: flags, push crypto, realtime, PWA, brand
> schema, 5.2 upgrade, AI features, polymorphic user types) exist on disk in this
> folder; backfilling them into this table is tracked as a separate doc-hygiene task.

## Template

When writing a new ADR, copy `ADR-001` as the template. Required sections:
- Status (Proposed / Accepted / Superseded by ADR-NNN / Deprecated)
- Date + Decision-makers + Implementer
- Context (what problem prompted this?)
- Decision (what are we doing?)
- Consequences (positive / negative / neutral)
- Alternatives considered (what we rejected and why)
- Implementation actions
- References
- Open questions for future ADRs

## When to write an ADR

- Cross-cutting architectural decision (affects 3+ plugins or core)
- License / IP / trademark implications
- Choice of major dependency (new framework, paid service, etc.)
- Strategic pivot (this!)
- Breaking change to an established contract

## When NOT to write an ADR

- Single-plugin implementation choices (use the plugin's state-card instead)
- Bug fixes
- Cosmetic / copy changes
- Reversible quick decisions
