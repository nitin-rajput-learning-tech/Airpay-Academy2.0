# Architecture Decision Records — Sentientia LMS

Every cross-cutting architectural decision lands here. ADRs are immutable once
accepted; superseded decisions get a new ADR that references the old one.

## Index

| # | Title | Status | Date |
|---|---|---|---|
| [ADR-001](ADR-001-fork-strategy-and-product-pivot.md) | Fork Strategy & Product Pivot to Sentientia LMS | Accepted | 2026-05-20 |
| [ADR-002](ADR-002-customer-level-feature-flags.md) | Customer-Level Feature Flags | Accepted | 2026-05-20 |
| [ADR-003](ADR-003-hand-rolled-web-push-crypto.md) | Hand-rolled Web Push crypto (Phase B.2.5) | Accepted (pending sec review) | 2026-05-21 |
| [ADR-004](ADR-004-realtime-mechanism-for-sentientia-live.md) | Real-time mechanism for `local_sentientia_live` | Accepted | 2026-05-21 |
| [ADR-005](ADR-005-pwa-install-and-native-wrapper-decision.md) | PWA install flow + native-wrapper decision | Accepted | 2026-05-21 |
| [ADR-008](ADR-008-customer-brand-db-schema.md) | Customer brand table design (Phase 2 multi-customer) | Proposed (deferred) | 2026-05-21 |
| [ADR-009](ADR-009-detection-consistency-and-ws-contract-invariants.md) | Detection consistency + WS contract invariants | Accepted | 2026-05-23 |
| [ADR-010](ADR-010-moodle-5.2-borrow-inventory.md) | Moodle 5.2 borrow inventory (no-prod-deploy strategy) | Proposed | 2026-05-23 |
| [ADR-011](ADR-011-moodle-5.2-wholesale-upgrade-staging.md) | Moodle 5.2 wholesale upgrade staging plan | Proposed | 2026-05-23 |
| [ADR-012](ADR-012-ai-quiz-generation.md) | AI Quiz Generation (`local_sentientia_aiquiz`) | Accepted | 2026-05-24 |
| [ADR-013](ADR-013-calendar-sync.md) | Calendar sync: token-URL ICS feed vs OAuth | Accepted | 2026-05-24 |
| [ADR-014](ADR-014-real-time-leaderboards-realtime-mechanism.md) | Real-time mechanism for `local_sentientia_leaderboard` | Accepted | 2026-05-24 |
| [ADR-015](ADR-015-ai-recommendations-engine.md) | AI Course Recommendations (`local_sentientia_recommendations`) | Accepted | 2026-05-25 |
| [ADR-016](ADR-016-ai-content-translation.md) | AI Content Translation (`local_sentientia_translate`) | Accepted | 2026-05-25 |
| [ADR-017](ADR-017-polymorphic-user-types.md) | Polymorphic User Types | Accepted | 2026-05-28 |
| [ADR-018](ADR-018-sentientia-independence-and-stabilization-roadmap.md) | Independence + 100%-Stabilization Roadmap (Waves 1–6) | Accepted (roadmap) | 2026-05-29 |
| [ADR-019](ADR-019-sentientia-core-tenant-identity.md) | `local_sentientia_core` + the `tenant_identity` seam (Wave 2) | Accepted (shipped) | 2026-05-30 |
| [ADR-020](ADR-020-sentientia-org-hierarchy-migration.md) | `local_sentientia_org` + org-hierarchy migration (Wave 3) | 3.1–3.3 shipped · 3.4 cutover gated | 2026-06-02 |
| [ADR-021](ADR-021-sentientia-tenant-registry.md) | `tenant_registry` + multi-customer tenant table (Wave 4) | Proposed — gated | 2026-06-01 |
| [ADR-022](ADR-022-component-rename.md) | `local_airpay_*` → `local_sentientia_*` component rename (Wave 5) | Superseded by ADR-025 | 2026-06-02 |
| [ADR-023](ADR-023-recurring-subscriptions.md) | Recurring subscriptions for course access | Proposed — gated | 2026-06-02 |
| [ADR-024](ADR-024-absorb-and-debrand-bizlms.md) | Absorb & de-brand the purchased BizLMS suite into Sentientia | Proposed | 2026-06-04 |
| [ADR-025](ADR-025-component-rename-airpay-to-sentientia.md) | Component rename `local_airpay_*` → `local_sentientia_*` (executes ADR-022) | **COMPLETE** — 35 plugins renamed | 2026-06-08 |
| [ADR-026](ADR-026-theme-cutover-and-canonicalization.md) | Theme cutover & canonicalization (`theme_airpayux → theme_sentientia`) | Accepted — Move 1 ready · Move 2 gated on 5.2 | 2026-06-09 |
| [ADR-027](ADR-027-quality-gate-system.md) | Quality-gate system (stop auditing, start gating) + surface-upgrade workstream | Accepted — Gate 0 shipped · Gates 1–3 staged | 2026-06-09 |
| [ADR-028](ADR-028-reconciled-product-roadmap.md) | Reconciled product roadmap: ship-and-prove before build (supersedes the 3 coexisting strategy docs as roadmap) | Proposed — awaiting the DECISION-MEMO-2026-08-04 sign-off | 2026-08-04 |

> Complete index — every ADR on disk is listed (ADR-006/007 were never assigned;
> the numbering gap is intentional). ADR-022's component-rename plan was executed by
> **ADR-025** (COMPLETE, 2026-06-08) — which is why the plugins on disk are
> `local_sentientia_*`. The state-card files under `state-cards/` still carry the
> legacy `airpay_*` names; aligning them to `sentientia_*` is the open half of P3-5
> (doc hygiene), pending a per-component pass (gateway / theme / block names differ).

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
