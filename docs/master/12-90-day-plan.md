## Section 14 — Plan of Action (Next 90 Days)

Thirteen weeks from 12 May 2026. The plan is organised week by week and assigns owners explicitly. "Nitin" denotes the Head of L&D; "IT" denotes the Airpay Information Technology team; "Mgmt" denotes a decision required from leadership.

### Weeks 1 and 2 — Cutover (12 May to 26 May 2026)

Goal: production cutover of Airpay Academy 2.0.

| Owner | Milestone | Definition of done | Risk |
|---|---|---|---|
| IT | Stage the production-equivalent environment with prod-sized RDS database clone | Staging URL reachable, all Airpay plugins installed at HEAD `6ce016150`, database migrated cleanly | Database clone may take longer than expected if the production DB is locked during business hours |
| Nitin | Run k6 load test against staging at `LOAD_TIER=prod` | All SLA gates pass (Dashboard p95 < 2000ms, Cart p95 < 2500ms, failed rate < 1%) | Staging hardware may not match production exactly, producing optimistic numbers |
| Nitin | Manual pen-test against staging — attempt to reproduce all eleven Phase 8 BLOCKING findings | All eleven attempts return as designed (rejected with appropriate error) | Test attacker may not have full exploit context; mitigated by detailed audit report |
| Mgmt | Sign-off on cutover from Head of L&D after staging gates pass | Email confirmation on file | None |
| IT + Nitin | Execute cutover per `PHASE-8-DEPLOYMENT-RUNBOOK.md` | All ten runbook sections completed, including 24-hour post-cutover watch list | Same as any production deploy |

**Gate at end of Week 2.** Platform live on production with v2 codebase. Any failure escalates to rollback per runbook section 7.

### Weeks 3 and 4 — Post-cutover stabilisation (26 May to 9 June 2026)

Goal: address every production issue surfaced in the first ten days of v2 live.

| Owner | Milestone | Definition of done |
|---|---|---|
| Nitin | Daily review of error log, slow-query log, cron output log | Zero unresolved P0 issues at end of each day |
| Nitin | Address the six remaining non-blocking Phase 8 findings (N1, N2, N4, N5, N6 plus N7) | Each finding closed with a commit and re-verification |
| IT | Configure observability — APM tool, structured logging, alerting on HTTP 5xx rate > 1% | Dashboards live, alerts firing into the correct on-call channel |
| Nitin | Plugin READMEs for the remaining 24 plugins | All thirty plugins have README files following the Phase 8.3 template |

**Gate at end of Week 4.** Steady-state operation. P0 backlog clear. Observability live.

### Weeks 5 to 8 — SENTIENTIA pipeline build (9 June to 7 July 2026)

Goal: SENTIENTIA producing its first ten SCORM courses end-to-end.

| Week | Owner | Milestone |
|---|---|---|
| 5 | Nitin | Agent 1 (SOP Parser) productionised — batch-mode, error handling, validation gate |
| 5 | Nitin | Agent 2 (Narration Generator) built — using Claude as the language model, enforcing 25-word sentence cap and 130 wpm pacing |
| 6 | Nitin + Mgmt [CONFIRM] | Agent 4 (Voice Generator, ElevenLabs) built — first paid API run produces voice for two pilot courses |
| 6 | Nitin + Mgmt [CONFIRM] | Agent 3 (Slides Generator, Gamma) built — first paid API run produces decks for the same two pilot courses |
| 7 | Nitin | Agent 5 (SCORM Packager) productionised — produces valid SCORM 1.2 ZIPs |
| 7 | Nitin + Mgmt [CONFIRM] | Agent 6 (Moodle Upload) built — first SENTIENTIA-generated SCORM uploaded to staging |
| 8 | Nitin | End-to-end pipeline orchestrator built; ten pilot SOPs processed; SCORMs deployed to staging |

**Gate at end of Week 8.** SENTIENTIA producing SCORM courses end-to-end. Vendor authoring cost per course displaced.

### Weeks 9 to 10 — BizLMS displacement Phase A (7 July to 21 July 2026)

Goal: remove the runtime dependency on `local_costcenter` from the platform's theme renderer.

| Week | Owner | Milestone |
|---|---|---|
| 9 | Nitin | Map every `local_costcenter\accesslib` call in `core_renderer.php` (13 calls) to an Airpay-owned equivalent |
| 9 | Nitin | Map every `local_courses\accesslib` call (5 calls) to an Airpay-owned equivalent |
| 10 | Nitin | Ship replacement helpers; remove `local_costcenter\accesslib` references from renderer |
| 10 | Nitin | Re-run Phase 7 multi-role UAT to confirm zero regression |

**Gate at end of Week 10.** Theme renderer no longer depends on BizLMS plugins.

### Weeks 11 to 12 — BizLMS displacement Phase B (21 July to 4 August 2026)

Goal: replace `local_users` runtime calls.

| Week | Owner | Milestone |
|---|---|---|
| 11 | Nitin | Audit all callers of `local_users` across the codebase (custom field rendering, supervisor tree, profile renderer) |
| 12 | Nitin | Ship Airpay-owned replacements; disable `local_users` |

**Gate at end of Week 12.** Three of the three P0 BizLMS plugins fully displaced. The Public tenant fully running on Airpay-owned code.

### Week 13 — Quarterly review and Q3 planning (4 August to 11 August 2026)

| Owner | Milestone |
|---|---|
| Mgmt | Q2 results review using `local_airpay_analytics` dashboard — course starts, completion rate, statutory compliance coverage, Public tenant revenue |
| Nitin + Mgmt | Q3 plan finalised covering: remaining BizLMS displacement (P1 plugins), L&D engineer hire status, ZEEA tenant decision, AI tutor decision |
| Nitin | Update this master document — version 1.1 reflecting cutover results, first three months of production telemetry, and any revised risk register entries |

### Six-month horizon (12 May 2026 to 12 November 2026)

By the end of Q3 the platform should have: BizLMS dependency fully eliminated (all P0 and P1 plugins displaced), SENTIENTIA producing ten SCORM courses per month at the published cost target, dedicated L&D engineer hired and onboarded, observability and disaster recovery posture matured to enterprise expectations, and the first cohort of Public tenant paying users (target: two hundred and fifty paying users) demonstrating the commercial offering's viability.

### Twelve-month horizon (12 May 2026 to 12 May 2027)

By the end of the first full operating year on v2, the platform should be capable of supporting one thousand paying Public-tenant users without architectural change, three thousand statutorily-compliant Airpay internal users with zero manual compliance chasing, ten SCORM courses per month produced in-house at one-twentieth of the vendor cost baseline, an AI tutor layer in pilot for at least one functional training area, and at least two open-sourced plugins under the Airpay Tech brand contributing back to the Moodle ecosystem.
