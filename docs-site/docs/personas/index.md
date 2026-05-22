# Personas overview

The platform serves **nine distinct user types** as documented in Section 10 of the [Master Technical &amp; Strategic Documentation (12 May 2026)](../shared/changelog.md). Each persona has its own walkthrough — start there.

If you're unsure which persona applies to you, this decision tree should help:

```mermaid
flowchart TD
    A[Are you logging in as an employee or external paying learner?] -->|Employee| B[Do you have direct reports?]
    A -->|External paying learner| EX[External Public Learner]
    B -->|Yes| C[Are you also in the L&D team?]
    B -->|No| L[Learner]
    C -->|Yes| LD[L&D Administrator]
    C -->|No| M[Manager]
    LD --> CA{Do you only author courses, no admin?}
    CA -->|Yes| SME[Course Author / SME]
    CA -->|No| TA{Tenant scope only or platform-wide?}
    TA -->|Single tenant| TAD[Tenant Administrator]
    TA -->|Platform-wide| SA[Site Administrator]
    LD --> CO{Owns statutory reporting?}
    CO -->|Yes| COF[Compliance Officer]
```

If you're a developer building an integration, see the [API Consumer guide](09-api-consumer/index.md) — it's the only persona that isn't a UI walkthrough.

## Permission hierarchy at a glance

Each persona inherits all features of the persona above it in this chain:

> Learner ⊆ Manager ⊆ L&amp;D Administrator ⊆ Tenant Administrator (within scope) ⊆ Site Administrator

Compliance Officer, Course Author/SME, and External Public Learner are **orthogonal** — they sit on dedicated permission sets that intentionally don't inherit. This is documented in Section 10 of the master doc and verified in the Phase 7 multi-role User Acceptance Test (14 personas, 12 May 2026).
