## Section 11 — Commercial and Operational Implications

### 11.1 Cost avoided versus SaaS LMS alternatives

A directly comparable commercial LMS at three thousand five hundred users typically prices in one of three bands:

| Vendor band | Price per user per month | Annual cost for 3,500 users |
|---|---|---|
| Mid-market (LearnUpon, TalentLMS, Docebo Lite) | ₹50–₹100 | ₹21 lakh – ₹42 lakh |
| Enterprise specialist (Docebo Enterprise, 360Learning, Cornerstone) | ₹150–₹300 | ₹63 lakh – ₹1.26 crore |
| Tier-one HR suite (SuccessFactors, Workday Learning) | ₹200–₹400 | ₹84 lakh – ₹1.68 crore |

The in-house alternative — one engineering equivalent plus pay-as-you-go third-party API costs — operates at a materially lower run-rate. Pay-as-you-go API spend at current production volumes (zero SENTIENTIA throughput at the time of writing) is negligible. Even at the planned post-pipeline-launch SENTIENTIA throughput of approximately ten new SCORM courses per month, the third-party API spend (ElevenLabs at ~₹3 per thousand characters, Gamma at a per-deck basis) projects to under ₹2 lakh per year.

The commercial conclusion is that the in-house posture saves between ₹19 lakh and ₹1.66 crore per year against the SaaS alternative, depending on which vendor band would have been the realistic comparator.

### 11.2 Content production cost

Pre-SENTIENTIA baseline: an externally-authored SCORM course on a regulatory topic, with voice-over narration and assessment, typically costs between ₹50,000 and ₹1.5 lakh per course depending on the vendor and the language coverage.

Post-SENTIENTIA target: the same course, generated from a Standard Operating Procedure document, with the SENTIENTIA pipeline running, is estimated to cost between ₹500 and ₹2,000 per course in third-party API spend (largely ElevenLabs voice generation for a typical thirty-minute course). The savings per course are approximately ₹48,000 to ₹1.48 lakh, which is significant at the L&D team's published target of producing approximately ten new SCORM courses per month.

This calculation assumes SENTIENTIA reaches operational state. As of 12 May 2026 the pipeline is architected but not delivering, so the savings are theoretical pending the agent build-out scheduled in Section 14.

### 11.3 Compliance posture

The platform now provides demonstrable statutory training coverage across POSH, AML/KYC, Digital Personal Data Protection Act, the Information Technology Act and RBI mobile banking circulars. The `local_airpay_compliance_report` plugin's six-state engine produces an audit-ready report that distinguishes between:

- Not enrolled (user is in scope of the regulation but has no course assignment) — should be zero on a healthy platform.
- Enrolled, not started.
- In progress.
- Completed, certificate current.
- Completed, certificate expiring within thirty days.
- Completed, certificate expired (user is non-compliant pending re-completion).

The annual recompletion engine (`local_airpay_recompletion`) automatically resets expiring certificates on a configurable cycle and triggers notification messages thirty days before expiry. Combined, these two plugins remove the previous manual burden on the compliance officer to track and chase statutory training completions, while leaving an immutable audit trail for the Reserve Bank of India and the internal audit team.

Pre-platform compliance audit posture: manual spreadsheet tracking, reactive chasing, no real-time view, no recompletion automation.

Current compliance audit posture: real-time dashboard, automated cycle, immutable audit log, CSV export formatted for statutory returns. The improvement is material and was a stated objective at project kickoff.

### 11.4 Brand and employee experience

The visual identity of the platform has moved from a generic vendor-supplied theme to an Airpay-owned design system. The Airpay corporate palette, typography and spacing grid are visible at every surface. The platform now feels like an Airpay product, not a Moodle hosting installation with a logo replacement.

Quantitative engagement metrics across the v1-to-v2 transition are tracked in the `local_airpay_analytics` plugin's dashboard. Specific numbers (course starts, completion rate, time-on-platform, Net Promoter Score) require live production data to extract and are not reproduced in this document; the dashboard is the canonical source.

### 11.5 Scalability headroom

The platform's current production hosting (single Apache + MySQL pairing on AWS-managed infrastructure, exact instance sizing held by IT) is sized for the current ~2,870 active users plus the projected Public-tenant growth over the next eighteen months. The k6 load test script at `moodle-enhancement/audit/load/load_test.k6.js` includes a `prod` tier that ramps to ten thousand concurrent virtual users, which represents the design ceiling and corresponds to a roughly three-times-current-user-base load.

If the Public tenant grows past five thousand external paying users (the strategic ceiling for the productised offering as currently scoped), the architecture would need a meaningful re-tier: dedicated database read replicas, a CDN in front of static assets, and quite probably a containerised application tier on Kubernetes rather than the current single-host model.

### 11.6 Risk register — top ten

| # | Risk | Severity | Likelihood | Mitigation |
|---|---|---|---|---|
| 1 | Key-person risk: the platform is materially built by one engineer (Head of L&D, who is also playing the engineering role). Departure or unavailability would halt the build. | HIGH | LOW–MEDIUM | This document is partial mitigation. Section 13 includes a decision to hire a dedicated L&D engineer. |
| 2 | BizLMS plugins still control parts of the production stack. A vendor-side change or licence dispute could disrupt service. | MEDIUM | LOW | FORK-PLAN sequences full BizLMS displacement over Q3 2026. |
| 3 | Payment gateway compromise — the cart's webhook integration is the highest-value attack surface on the platform. | HIGH | LOW | Phase 8.1 B4 and B11 fixes specifically address this. Pen-test against staging is a remaining cutover gate. |
| 4 | Proctoring identity-photo data handling under DPDP. The platform is designed to never persist photos, only the match score, but a misconfiguration could violate that contract. | HIGH | LOW | The current build explicitly `unset()`s photo bytes and never writes them to disk or DB. Code review must catch any future regression. |
| 5 | Annual recompletion engine misfires and wipes legitimate completions across the wrong tenant. | HIGH | LOW | Phase 8.1 B6 specifically addresses cross-tenant scoping. The dryrun flag on every rule lets administrators preview before committing. |
| 6 | Moodle 5.x upgrade introduces breaking changes to airpayux templates or core renderer methods. | MEDIUM | MEDIUM | Local development already runs Moodle 5.1.3; carry-forward discipline documented in `MOODLE5-UPGRADE-RUNBOOK.md`. |
| 7 | ElevenLabs / Gamma vendor lock-in once SENTIENTIA reaches production. | LOW–MEDIUM | MEDIUM | Both vendors are interchangeable with self-hosted alternatives at higher engineering cost. Decision point in Section 13. |
| 8 | Production hosting on infrastructure not in the L&D team's direct control. | LOW | LOW–MEDIUM | The IT team owns the production environment. Deploy runbook explicitly hands off to IT at the file-copy step. |
| 9 | Three-tenant data leak — historically the highest-effort defensive area on the platform. | HIGH | LOW (post-Phase-8.1) | The `local_airpay_core\tenant` helper plus the three-layer enforcement architecture is the structural defence. Pen-test verifies. |
| 10 | Backup and disaster recovery posture not yet exercised in a real drill. | MEDIUM | LOW | Backup procedures documented in `moodle-enhancement/DEPLOYMENT-RUNBOOK.md`; restore drill scheduled before cutover. |

### 11.7 Total project investment to date

| Line item | Approximate value |
|---|---|
| Engineering time (Head of L&D, since April 2026 intensive period) | ~600 hours |
| Vendor engagement time (eAbyas BizLMS support, pre-fork) | ~120 hours during transition |
| Infrastructure spend (XAMPP local + AWS-managed production + staging) | Held by IT; not separately attributable to L&D |
| Third-party API spend to date | < ₹50,000 (predominantly experimentation with ElevenLabs and Gamma) |
| Documentation, design, prototypes (twenty-two C-suite-approved homepage prototypes at `D:\Claude Local\Moodle Backup\03-prototypes\preview\`) | Built in-house |

The cumulative cash investment is materially less than one quarter of the conservative SaaS alternative described in Section 11.1.
