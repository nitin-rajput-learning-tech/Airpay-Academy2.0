# Architecture Decision Records — Sentientia LMS

Every cross-cutting architectural decision lands here. ADRs are immutable once
accepted; superseded decisions get a new ADR that references the old one.

## Index

| # | Title | Status | Date |
|---|---|---|---|
| [ADR-001](ADR-001-fork-strategy-and-product-pivot.md) | Fork Strategy & Product Pivot to Sentientia LMS | Accepted | 2026-05-20 |

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
