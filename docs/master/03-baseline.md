## Section 2 — Baseline: Where We Started

### 2.1 State of Airpay Academy v1 before the project began

The platform that became Airpay Academy was originally built by a Bangalore-based vendor (eAbyas Info Solutions) under an internal codename of "Bayer". The first commit in the repository is dated 2 November 2022, authored by a contributor identified as `Niranjan`. Over the subsequent two years and four months the platform accumulated approximately one thousand nine hundred commits before the first commit authored under the `Nitin Rajput` identity appears in late 2024, marking the formal handover from the vendor-led build to an Airpay-led customisation.

At the point that Airpay took the codebase into in-house custody, the inherited platform had the following characteristics, documented in `FORK-PLAN.md` at the project root:

- **Moodle 4.x core** with the vendor's BizLMS overlay applied. BizLMS is a commercial multi-tenant addon for Moodle distributed under licence by eAbyas.
- **Twenty-two BizLMS plugins** spanning user management, course management, organisation hierarchy, classroom scheduling, examinations, learning paths, programmes, evaluations, ratings, role management, search, custom categories and several others. These plugins represented approximately one thousand seven hundred and seventy-five source files of vendor-controlled code.
- **Six BizLMS blocks** for the user dashboard, the learnerscript reporting view, the my-skills surface and three others.
- **Forty plus custom database tables** owned by BizLMS, none of which appear in the standard Moodle distribution.
- **Thirty-nine custom columns** added directly to the `mdl_user` table (every column prefixed `open_`, e.g. `open_path`, `open_managerid`, `open_costcenterid`). These columns are not part of the standard Moodle schema and any future Moodle core upgrade can produce surprising migrations against them.
- **Eleven custom columns** on the `mdl_course` table in the same style.
- **Approximately one hundred web service endpoints** exposed by the BizLMS plugin family.
- **Thirty plus custom capabilities** registered by BizLMS.
- **Thirteen direct calls** in the platform's custom theme renderer to `local_costcenter\accesslib`, plus five direct calls to `local_courses\accesslib`. These calls are the structural reason the BizLMS plugins cannot simply be uninstalled without the platform's renderer crashing.

### 2.2 The strategic decision to fork and rebuild

Three pain points drove the decision to in-house the platform:

1. **Vendor coupling.** Every customisation request — including changes for which there was no commercial product feature gap, only a stylistic or workflow preference — went through the eAbyas change-request queue. Turnaround was measured in weeks, sometimes in months. The L&D team could not ship a change to its own platform without an external vendor's calendar dictating the schedule.
2. **Schema fragility.** The thirty-nine custom columns on `mdl_user` and the eleven on `mdl_course` meant that any Moodle core upgrade would be a non-trivial database migration. The platform was effectively on a private fork of Moodle's schema and the cost of staying on that fork was rising with each Moodle release.
3. **Multi-tenancy contention.** The BizLMS costcenter model is functional but opinionated. It enforces specific assumptions about role context, branding and user provisioning that began to conflict with Airpay's emerging requirement to operate a commercial Public tenant alongside the internal employee tenant. The vendor's tenancy model was designed for a single-customer-multiple-business-units pattern, not a one-platform-multiple-paying-tenants pattern.

The alternative considered was a migration to a commercial SaaS LMS — either an established product like Cornerstone, SuccessFactors or Workday Learning, or a developer-focussed option like Open edX. Three reasons that path was not taken:

- **Capital efficiency.** A SaaS LMS at three thousand five hundred users typically lists at between fifty rupees and two hundred rupees per user per month, or twenty-one lakh to eighty-four lakh rupees per year of recurring spend. The internal cost of the fork-and-rebuild approach is approximately one full-time L&D engineering equivalent plus pay-as-you-go third-party API costs for ElevenLabs and Gamma, which together amount to materially less than the SaaS option.
- **Compliance ownership.** Statutory training records held in an external SaaS would require contractual data-processing agreements and would put Airpay's compliance posture in the hands of the vendor's certifications. An in-house Moodle platform keeps the data on Airpay-controlled infrastructure and brings the compliance perimeter inside the firewall.
- **Strategic optionality.** The Public tenant (`/77`) is the seed of a productised offering — Airpay's L&D capability sold to other Indian fintechs. That optionality is impossible on a SaaS where Airpay is itself a paying tenant.

### 2.3 Original objectives and success criteria

The project's success criteria at kickoff, against which progress has been measured:

| Objective | Success criterion | Status as of 12 May 2026 |
|---|---|---|
| Eliminate BizLMS as a runtime dependency for core workflows | Zero direct calls to `local_costcenter\accesslib` or `local_courses\accesslib` from the theme renderer | Partial — calls reduced significantly, full removal sequenced in FORK-PLAN for Q3 2026 |
| Own the visual identity end to end | Standalone theme with `$THEME->parents = []`, no Epsilon dependency at runtime | Achieved on 3 April 2026 (commit `0217a9909`) |
| Replace SCORM authoring vendor cost | In-house pipeline (SENTIENTIA) processes SOPs into SCORM modules without an external authoring vendor | Architected, agents 1 and 5 prototyped, full pipeline not yet operational — see Section 7 |
| Establish a commercial posture for external training | Working shopping cart, payment gateway integration, per-tenant branding for the Public tenant | Achieved on 11 May 2026 (commit `c44256473`), security-hardened on 12 May 2026 |
| Enable robust hiring assessment | Proctored examination with identity verification, recording and AI-assisted behaviour analysis | Achieved on 11 May 2026 (commit `1572e7da3`), security-hardened on 12 May 2026 |
| Comply with Digital Personal Data Protection Act | Privacy provider on every plugin storing personally identifiable information; data subject request workflow | Achieved on 12 April 2026 (commit `dea49a313`), maintained on every subsequent plugin |
| Achieve and document tenant data isolation under audit | Three-layer isolation: SQL predicate, capability context, application-layer check | Achieved on 12 May 2026 via the `local_airpay_core\tenant` helper |
| Reach a state where a CTO could read the platform and understand it without speaking to the Head of L&D | This document | Delivered 12 May 2026 |
