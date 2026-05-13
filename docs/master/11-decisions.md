## Section 13 — Decisions Required From Management

The following decisions are outside the L&D function's authority and require explicit leadership input. Each item lists the decision, the options under consideration, the recommendation with reasoning, and the cost of further delay.

### 13.1 Production hosting strategy

**Decision needed.** Whether to keep production on the current IT-managed Linux Apache + MySQL hosting or to migrate to a cloud-managed runtime (AWS Elastic Beanstalk, AWS ECS on Fargate, or a managed Moodle host such as MoodleCloud).

**Options.**
- *Status quo:* IT-managed, single-host, manual scaling. Operational cost low; engineering effort to deploy moderate; risk of single point of failure moderate.
- *AWS-managed (Fargate or Beanstalk):* containerised application tier, auto-scaling, managed certificates. Operational cost moderate; engineering effort to migrate one-off significant; risk lower after migration.
- *MoodleCloud or similar SaaS host:* Moodle-as-a-service from a third-party. Operational cost high; engineering effort to migrate moderate; vendor-coupling risk reintroduced.

**Recommendation.** Stay on the current IT-managed hosting through the cutover; revisit the AWS-managed move once the Public tenant crosses two thousand paying users. The savings from the SaaS-vs-in-house calculation in Section 11.1 are large enough that operational improvements should be funded from those savings rather than spent on a hosting migration in the same fiscal year.

**Cost of delay.** Low until the Public tenant grows materially. High if the IT team's ability to operate the single-host model becomes resource-constrained.

### 13.2 Annual budget allocation for third-party APIs

**Decision needed.** Set an annual ceiling for ElevenLabs (voice), Gamma (slides), AWS Rekognition (proctoring identity), AWS S3 (proctoring recordings) spend.

**Recommendation.** Establish a first-year ceiling of ₹6 lakh covering all four vendors combined, with a quarterly review. The actual current run-rate is well below this ceiling; the ceiling is generous to allow SENTIENTIA to ramp up to the ten-courses-per-month target without re-approval friction.

**Cost of delay.** Each calendar quarter SENTIENTIA is not operational is a quarter at vendor-authoring cost rather than in-house authoring cost. At the ten-course-per-month target and a ₹50,000-per-course external benchmark, the cost of delay is approximately ₹15 lakh per quarter.

### 13.3 Dedicated L&D engineer hire

**Decision needed.** Whether to maintain the sole-builder model (Head of L&D personally engineering the platform) or hire a dedicated L&D engineer.

**Recommendation.** Hire. The key-person risk identified in Section 11.6 row 1 is the single highest-rated risk on the platform. The Head-of-L&D-as-engineer model has worked because of one individual's specific capability and is not a sustainable steady state. A mid-senior PHP / Moodle engineer at approximately ₹18-24 lakh per year fully loaded would more than pay for themselves in continuity insurance alone, and would free the Head of L&D to focus on programme strategy rather than personally shipping Mustache templates.

**Cost of delay.** Each month the role is unfilled compounds the risk. A 3- to 6-month hire cycle is realistic from the day the role is approved.

### 13.4 Mobile app build versus responsive web only

**Decision needed.** Whether to invest in a branded mobile application or to accept the standard Moodle Mobile App with Airpay theming.

**Options.**
- *Moodle Mobile baseline:* zero engineering effort; Airpay logo and palette applied through the standard mobile-theme overlay; learners install the official Moodle Mobile app and configure with the `airpay.academy` URL.
- *Branded fork of Moodle Mobile:* moderate engineering effort (one-time React Native customisation, ongoing maintenance); learners install an "Airpay Academy" app from the App Store and Play Store; offline SCORM and push notifications enabled.
- *Native iOS / Android build from scratch:* large engineering effort; full design control; multi-year commitment.

**Recommendation.** Accept the Moodle Mobile baseline for now. Enable mobile-push notifications (the server-side setup is documented in `moodle-enhancement/docs/MOBILE-PUSH-SETUP.md`). Revisit the branded fork question once learner uptake of the mobile app is measured on production and the strategic value of an Airpay-branded app store presence is clearer.

**Cost of delay.** Low. The Moodle Mobile baseline is functional and the gap between it and a branded fork is largely brand and offline-mode features.

### 13.5 AI tutor / agent layer — build, buy, or wait

**Decision needed.** Whether to build an AI tutor layer (in-course question answering, learning-path recommendations, manager-side coaching prompts) using Moodle 5's `core_ai` subsystem plus the existing `local_airpay_assistant` plugin, or to defer this category of feature entirely.

**Recommendation.** Defer until the SENTIENTIA pipeline is in steady state. An AI tutor that draws on Airpay's own training content is materially more valuable than one that draws on a generic foundation model; SENTIENTIA producing high-quality content is the precondition. Re-evaluate in Q4 2026.

**Cost of delay.** Low. The competitive landscape on AI tutoring is moving fast but Airpay is not commercially exposed by being a late adopter in this specific category.

### 13.6 Open-sourcing select plugins under the Airpay Tech brand

**Decision needed.** Whether to open-source the Airpay-owned plugins (or a subset of them) under the Airpay Tech brand, both as a contribution-back to the Moodle ecosystem and as a recruiting / employer-brand signal.

**Recommendation.** Open-source `local_airpay_core`, `local_airpay_recompletion` and the documentation patterns established in `.claude/rules/`. These are general-purpose and would benefit the broader Moodle community without disclosing anything proprietary. Defer the open-sourcing of `local_airpay_cart`, `local_airpay_proctoring` and the commercial tenancy stack until those have at least six months of production hardening.

**Cost of delay.** Low. The opportunity is incremental brand-building rather than competitive necessity.

### 13.7 Commercial offering — productising Airpay Academy for other Indian fintechs

**Decision needed.** Whether to commit to productising the Public tenant (`/77`) into a marketed offering — selling Airpay's L&D platform plus content library to other Indian fintechs as a service.

**Recommendation.** Defer the strategic commitment until the Public tenant reaches one thousand paying users organically through the current low-key positioning. The platform is now technically capable of supporting a commercial offering — payment gateway, per-tenant branding, GST-compliant invoicing — but a formal go-to-market motion (sales team, marketing budget, customer success function) is a different scale of commitment than is currently sized.

**Cost of delay.** Medium. The competitive window for an Indian-fintech-focussed L&D offering is open; competitors are likely to start entering this niche in twelve to eighteen months.

### 13.8 ZEEA tenant scope and renewal

**Decision needed.** Whether to renew the ZEEA enterprise contract at its current scope, expand it, or wind it down. The contract is the seed of the third costcenter (`/177`) but has only six active users at the time of writing.

**Recommendation.** This is outside the platform team's purview. Flagged here so leadership has the technical context: maintaining ZEEA as a separate tenant costs essentially nothing operationally because the multi-tenant infrastructure is already built. Winding it down is also straightforward — disable the tenant in `local_airpay_org`. Expansion would require commercial-team work, not engineering work.
