## Section 3 — Evolution Timeline

The repository contains two thousand three hundred and eighty-six commits dated between 2 November 2022 and 12 May 2026. The platform's evolution falls into seven distinct phases, ordered chronologically.

### Phase 0 — Vendor build (2 November 2022 to mid-2024)

The codebase was originated by eAbyas Info Solutions under the internal codename "Bayer" and accumulated approximately one thousand four hundred commits over twenty-two months of vendor-led development. Major contributors during this phase were `raju`, `mahesh`, `Sachin`, `Kamesh` and `Niranjan`, all of whom carry external vendor email addresses on their commits. The phase delivered the original BizLMS overlay: twenty-two plugins, six blocks, the original Epsilon theme customisation, and the schema extensions to `mdl_user` and `mdl_course` documented in Section 2.1.

Representative commits from this phase:
- `c0c99ac39 2022-11-02 Niranjan: Initial commit`
- `d36126934 2022-11-02 Sachin: local plugins, blocks and theme epsilon added`
- `5f595a46b 2022-11-03 Sachin: Added dashboard page in My`

### Phase 1 — Vendor maintenance + Airpay rebrand (mid-2024 to March 2026)

The vendor relationship continued for approximately eighteen months after handover, during which the platform was rebranded from "Bayer" to "Airpay" and the production URL moved to `airpay.academy`. The first commit authored under the `Nitin Rajput` identity appears in late 2024. This phase delivered incremental bug-fixes, an emerging Airpay design language inside the vendor's theme, and the first wave of Airpay-authored customisations that did not yet attempt to displace vendor code.

### Phase 2 — Theme fork (3 April 2026, Phase 6A and 6B in internal naming)

The strategic shift began with the theme. On 3 April 2026, the existing Epsilon-derived theme was forked into a standalone theme named `airpayux` with `$THEME->parents = []`, meaning the theme no longer inherits from any parent and Airpay owns every file in it.

Key commits:
- `c59f05348 2026-04-03 Phase 6A: Create theme_airpayux child theme + fix FA icons + dashboard URL`
- `0217a9909 2026-04-03 Phase 6B: Fork epsilon to theme_airpayux (standalone, full ownership)`
- `067d2f80d 2026-04-03 Phase 6B: Redesign navbar (B7) and footer (B8) with Airpay design system`

By the end of this phase the theme had grown to six hundred and forty-two files. The custom renderer (`classes/output/core_renderer.php`) reached two thousand three hundred and thirty-nine lines.

### Phase 3 — BizLMS plugin fork (10 April to 17 April 2026)

The most consequential phase of the year. In one intensive week, twenty-two BizLMS plugins were replaced by six initial Airpay-owned plugins, the platform's front page was switched from a redirect to a custom Airpay homepage, multilingual support was added across Hindi, Marathi, Swahili and Kannada, and the post-fork bug bounty produced seventeen runtime fix commits.

Key commits:
- `5250c9e72 2026-04-16 BizLMS fork complete — 6 new Airpay plugins replacing 22 BizLMS plugins (v3.0.0)`
- `45663f887 2026-04-16 Add remaining Airpay replacements: ratings, challenge, evaluation, roles, programs, trainer block`
- `31d2cb1d4 2026-04-17 Fix: 17 post-fork runtime bugs found in bounty hunt audit`
- `308dbfaa2 2026-04-17 Restore correct fork core_renderer.php + disable all 22 BizLMS plugins`
- `7c257b467 2026-04-14 Multilingual: Hindi, Marathi, Swahili, Kannada across 9 Airpay plugins`

### Phase 4 — Capability deepening (April 2026 to early May 2026)

The next four weeks were spent deepening each plugin's capability, fixing the long tail of post-fork regressions, adding privacy providers for the Digital Personal Data Protection Act, and shipping the gamification engine, the manager dashboard, the integrations hooks for KeKa HRMS, the lifecycle automation for joiner/mover/leaver events, and the AI assistant.

Key commits:
- `67a695cd8 2026-04-09 Tier 1: Gamification Engine — core plugin (local_airpay_gamification)`
- `9e3512499 2026-05-05 P1 perf: airpay_org admin 86× faster + airpay_analytics N+1 + cache layer`

### Phase 5 — Feature-parity closure (5 May to 10 May 2026)

A surge of feature-parity work designed to close every gap with the legacy BizLMS feature set. The G-series (G-01 through G-06) closed gaps in `airpay_courses`, `airpay_classroom`, `airpay_learningpath`, `airpay_programs`, `airpay_evaluation` and `airpay_exams`. The A11Y (accessibility) series closed Web Content Accessibility Guideline 2.1 AA gaps across all admin tables. Comprehensive PHPUnit coverage was added across the security-critical paths.

Key commits:
- `76496de34 2026-05-07 G-02: airpay_classroom view detail + sessions tab + attendance UI shipped`
- `771508688 2026-05-07 G-03: airpay_programs levels CRUD + courses + enrol UI shipped`
- `fefbe49ce 2026-05-07 G-04: airpay_learningpath assign-courses + enrol-users UI shipped`
- `53d12a349 2026-05-07 G-05: airpay_evaluation analysis dashboard + filtered responses + CSV export`
- `175e220e8 2026-05-06 E (complete): airpay_exams + airpay_learningpath PHPUnit tests`
- `91424ffc7 2026-05-10 L-axis residue closed, 158/158 UAT cases pass`

### Phase 6 — Enterprise-grade build (11 May to 12 May 2026)

The most intensive forty-eight hour build window of the project to date. Driven by the Head of L&D's stated mandate that "the platform needs to be enterprise grade, nothing should be deferred", the phase delivered six new plugins and twelve commits in a single working week.

Day-by-day summary:

| Date | Phase code | Delivery |
|---|---|---|
| 11 May 2026 | Phase 1 | `airpay_cart` (NEW): full e-commerce stack for external tenants — five tables, twelve web service endpoints, GST-compliant invoicing, refund workflow, integrated Airpay payment gateway |
| 11 May 2026 | Phase 1G/H/I | Per-tenant settings + external tenant UAT harness + Single Sign-On documentation |
| 11 May 2026 | Phase 2 | `airpay_request` (NEW): course-request approval workflow. `airpay_proctoring` (NEW): identity verification + webcam recording + AI behaviour flagging. `quizaccess_airpay_proctoring` subplugin: gates the Moodle quiz attempt lifecycle on proctoring session state |
| 12 May 2026 morning | Phase 3 | Deferred-item closure: five plugins, twenty-nine files, two new smoke harnesses |
| 12 May 2026 morning | Phase 4 | Operations polish: four plugins, eighteen files, approximately one thousand eight hundred lines of code |
| 12 May 2026 morning | Phase 5 | `airpay_recompletion` (NEW): annual compliance reset engine with scheduled daily cron, per-course rule definitions, integration with the compliance report dashboard |
| 12 May 2026 morning | Phase 6 | Unused-feature integration: cohort sync from organisation tree, badges seed, Moodle 5 AI subsystem bridge, mobile push notification setup documentation |
| 12 May 2026 afternoon | Phase 7 | Multi-role User Acceptance Test harness covering seven personas across fourteen test cases each. Surfaced two real production bugs (silent capability registration failure across four plugin install hooks, plus the Tenant Admin context-level distinction). Result: 84 of 85 cases pass. |
| 12 May 2026 evening | Phase 8 | Pre-cutover security audit. Verdict: NO-GO. Eleven blocking findings, nine non-blocking. |
| 12 May 2026 evening | Phase 8.1 | Remediation of all eleven blocking findings. Thirty-five files changed. The shared `local_airpay_core\tenant` helper introduced. |
| 12 May 2026 night | Phase 8.2 | Re-audit. Verdict: GO. All eleven findings VERIFIED fixed. Phase 7 UAT re-run returns identical 84/85. Three of nine non-blocking follow-ups addressed in-flight (the remaining six are tracked for Phase 9). |
| 12 May 2026 night | Phase 8.3 | Six plugin README documents written (five plugins plus the quizaccess subplugin). Four plugin-level smoke test suites re-run end-to-end on patched code: cart 26/26, request 23/23, proctoring 22/22, recompletion 13/13 — total 84 of 84 plugin smoke cases pass. |

Cumulative shipment during Phase 6 (the forty-eight hour window): eighteen commits, approximately twenty-two thousand lines of code added, eleven security blockers closed, three non-blocking follow-ups addressed, 326 cumulative test cases passing across User Acceptance Test, plugin smoke and PHPUnit suites.

### Phase 7 — Cutover readiness (the remaining gates)

Three operational gates remain before production cutover:

1. Information technology team deploy to a staging environment with a production-sized database clone.
2. Sustained-load test against staging using the k6 load script at `moodle-enhancement/audit/load/load_test.k6.js` with the `prod` load tier (ramps to ten thousand concurrent virtual users).
3. Manual penetration test against staging attempting to reproduce the eleven blocking findings against the patched code.

After all three gates pass, the cutover proceeds following the runbook at `moodle-enhancement/PHASE-8-DEPLOYMENT-RUNBOOK.md`.
