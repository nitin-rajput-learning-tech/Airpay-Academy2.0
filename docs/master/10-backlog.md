## Section 12 — What Is Still To Be Built

The backlog is grouped by workstream. Each item is rated for business value (`H` / `M` / `L`), effort (`S` ≤ 1 week, `M` 1–3 weeks, `L` 3–6 weeks, `XL` > 6 weeks), and a brief dependency note. The backlog is current as of HEAD `6ce016150` (12 May 2026).

### 12.1 Workstream A — Moodle Enhancement

| Item | Value | Effort | Dependency | Note |
|---|---|---|---|---|
| Complete BizLMS displacement (replace `local_costcenter`, `local_users`, `local_courses` runtime calls with Airpay-owned equivalents) | H | XL | None | The strategic anchor; FORK-PLAN sequences this over Q3 2026. |
| Plugin READMEs for remaining 24 plugins (5 done, 25 to go) | M | M | None | Doc-writer agent stalled on the first attempt; manual authoring confirmed as the working approach. |
| Decompose `core_renderer.php` (2,339 lines) into renderer traits | M | M | None | Maintainability concern. Test coverage on the renderer is thin; refactor must include test additions. |
| Tenant-scoped external trait pattern back-ported to remaining plugins (currently 4 of ~12 plugins exposing externals are using the helper) | M | M | None | The architectural recommendation from the Phase 8 audit. |
| Performance: catalog page first-byte time under 800ms at 95th percentile | M | M | None | Currently catalog cold-load on local XAMPP is 6-7 seconds; staging numbers will tell the true story. |
| Site-search across courses, programs, classroom sessions and pages | M | L | None | Currently search is per-domain; a unified search would meaningfully improve learner discoverability. |
| Manager: predictive analytics on team training needs | L–M | L | Production data + analytics maturity | Aspirational; needs at least six months of production usage data first. |

### 12.2 Workstream B — SENTIENTIA

| Item | Value | Effort | Dependency | Note |
|---|---|---|---|---|
| Agent 1 — SOP Parser productionising | H | M | None | Prototype script exists; needs error handling, output validation, batch mode, integration with the disk-artefact contract. |
| Agent 2 — Narration Generator | H | M | Agent 1 | Will use Claude as the language model. Must enforce the 25-word sentence cap and 130 wpm pacing rule. |
| Agent 3 — Slides Generator | H | M | Agent 2 | Gamma API integration; [CONFIRM] gate per batch. |
| Agent 4 — Voice Generator | H | M | Agent 2 | ElevenLabs API integration; [CONFIRM] gate per batch. |
| Agent 5 — SCORM Packager productionising | H | M | Agents 3 and 4 | Prototype script exists; needs the manifest validator, the masteryscore-70 enforcement, and the ZIP-from-root structure check. |
| Agent 6 — Moodle Upload | H | S | Agent 5 | Moodle REST `core_files_upload` plus a follow-up `core_course_update_courses` to attach the SCORM activity. |
| End-to-end pipeline orchestration (one-command run of all six agents in sequence) | M | S | Agents 1-6 | The agents must remain independently runnable; the orchestrator only chains them at runtime. |
| Quality regression suite — generated SCORM verified against three reference courses | M | M | All agents | Catches output drift if the language model or vendor APIs change behaviour. |

### 12.3 Workstream C — Knowledge Automation

| Item | Value | Effort | Dependency | Note |
|---|---|---|---|---|
| OAuth2 SSO against Microsoft Entra for the Airpay tenant | H | M | Azure tenant configuration owned by IT | The Moodle OAuth2 plugin handles the protocol; the work is configuration and user-flow design. |
| SharePoint document pull for SENTIENTIA SOP intake | M | M | SSO live (uses the same OAuth flow) | Replaces the current manual download-and-stage step. |
| Teams notifications for high-signal events (course assigned, request escalated, certificate expiring) | M | M | SSO live | Most learners prefer Teams over email for short, time-sensitive notifications. |
| Auto-provision user accounts from Entra group membership | M | M | SSO live | Closes the new-joiner gap currently filled by KeKa HRMS. |

### 12.4 Cross-cutting

| Item | Value | Effort | Dependency | Note |
|---|---|---|---|---|
| Observability — application performance monitoring (APM), error tracking, structured logging | H | M | IT engagement | The platform currently has Moodle's standard error log only. Production-grade observability requires APM tooling (New Relic / Datadog / similar). |
| Backup automation and quarterly restore drill | H | S | IT engagement | Documented in `DEPLOYMENT-RUNBOOK.md`; the drill itself has not been exercised. |
| Disaster recovery cold-site procedure | M | M | IT engagement + budget | Defines recovery time and recovery point objectives for the platform; required for the planned RBI-aligned business continuity programme. |
| Multi-factor authentication enforcement for site administrators | H | S | None | Moodle supports MFA out of the box; enforcement requires admin-policy configuration. |
| Audit logging for sensitive administrative actions (role changes, capability grants, bulk user operations, refunds, password resets) | H | M | None | Moodle's standard log table captures the events; the work is in producing a queryable audit-friendly view. |
| Mobile app — branded fork or accept Moodle Mobile baseline | M | L or S | Section 13 decision | Discussed in Section 13.4. |
| Learner analytics — engagement drop-off, course-effectiveness metrics, manager-level KPIs | M | L | Six months of production usage data | Aspirational. |
| AI tutor — an in-course question-answering layer using `local_airpay_assistant` as the entry | M | L | Section 13 decision | Discussed in Section 13.5. |
| Social learning — peer comments, study groups, course-level discussion forum integration | L | M | Moodle core forum module + design work | Lower priority; not on the critical path. |

### 12.5 Non-blocking security follow-ups from Phase 8 audit (N-items)

Five of the six non-blocking findings from the 12 May audit are tracked here. The sixth (N3, ip_check edge bug) was fixed in-flight on 12 May.

| # | Finding | Effort | Note |
|---|---|---|---|
| N1 | Rate-limit hour-bucket allows 2× burst at boundary in identity verification | S | Acceptable trade-off today; revisit if logs show abuse. |
| N2 | Cart callback `getremoteaddr()` trusts X-Forwarded-For when reverseproxy enabled | S | Mitigated in deployment runbook §0 with LB-configuration check. |
| N4 | `manageprices` capability migration cleanup (custom-role assignments at CONTEXT_SYSTEM go inert after move to CONTEXT_COURSE) | S | Mitigated in deployment runbook §0 with the SQL pre-flight. |
| N5 | `_tenantroot` named-parameter naming convention | S | Cosmetic. |
| N6 | Silent-404 callback IP drops not logged | S | Optional observability improvement. |
