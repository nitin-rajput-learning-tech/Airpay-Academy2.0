# Supplement F — Dedicated L&D Engineer Hire Brief

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`
Section 13.3. Operationalises the highest-leverage management decision
on the platform's roadmap.

The risk register supplement (SUPP-A) lists this as the single most
decisive mitigation across the entire register — directly reduces P1
(key-person), P2 (no PR review), A3 (core_renderer monolith) and
indirectly improves S6 (dependency CVE coverage), C3 (POSH audit
bandwidth) and U1 (a11y regression coverage).

## 1. Business case in one paragraph

The Airpay Academy 2.0 platform is currently engineered by one person
(the Head of L&D) operating in dual roles. That person also runs the
strategic L&D function, manages compliance reporting, owns vendor
relationships, and is the SCORM content curator. Engineering throughput
at this cadence has been remarkable but is fundamentally unsustainable;
the master document's risk register rates key-person dependency as the
single highest-residual risk on the platform. A dedicated L&D engineer
at ₹18-24 lakh annual loaded cost would (a) eliminate the key-person
risk, (b) accelerate the SENTIENTIA pipeline build which itself
generates ₹57.6 lakh to ₹1.77 crore in annual savings, (c) enable the
BizLMS displacement programme to compress from 14 weeks to 6, (d) free
the Head of L&D to focus on programme strategy where his impact is
disproportionate. The hire pays for itself on throughput alone, before
any of the strategic benefits are counted.

## 2. Role specification

**Title:** L&D Platform Engineer
**Reports to:** Head of L&D
**Level:** Senior IC (individual contributor) — not management
**Location:** Mumbai HQ, hybrid (3 days office, 2 days remote)
**Notice:** 60-90 day onboarding ramp expected
**Loaded cost target:** ₹18-24 lakh per year (base + benefits + equity)

### Primary responsibilities

1. **Pair-engineering on every plugin shipped.** No code reaches the
   `production` branch without a second pair of eyes. (Mitigates P2.)
2. **Own the SENTIENTIA pipeline build.** Weeks 5-8 of the 90-day plan
   are scoped around this hire's first deliverable. (Unblocks ST3.)
3. **Lead the BizLMS displacement engineering.** Weeks 9-12. (Owns the
   FORK-PLAN execution.)
4. **Refactor `core_renderer.php` decomposition.** The 2,339-line file
   is too large to maintain confidently; this hire owns the trait
   extraction with test coverage as the deliverable. (Mitigates A3.)
5. **Run the dependency audit + security update pipeline.** Quarterly
   `composer audit`, `npm audit`, Moodle Security Advisories review.
   (Mitigates S6.)
6. **Code-review every change shipped by the Head of L&D.** Reverse-
   accountability — even if the Head of L&D is the architect, the hire
   reviews. (Mitigates P1 directly.)
7. **Backup engineering coverage for L&D.** When the Head of L&D is
   unavailable (illness, leave, conference), this hire holds the rope.

### Required experience

- 5-8 years of PHP web-application engineering, at least 2 years in
  Moodle specifically.
- Familiarity with Moodle's plugin SDK, capability system, database
  manipulation layer.
- Comfort with Mustache templating and SCSS.
- Prior experience with at least one multi-tenant SaaS platform.
- Reading-level comfort with PHPUnit, Playwright, k6.
- Indian English fluency for L&D-team collaboration; bonus for one of
  Hindi / Marathi / Kannada / Swahili for content review.

### Nice-to-have

- Prior work on SCORM / xAPI content pipelines.
- AWS proficiency (Rekognition + S3 + SigV4 + IAM).
- Familiarity with the Anthropic Claude API or comparable LLM SDK.
- Experience operating a regulated platform under RBI / SEBI / IRDAI
  oversight.

### Disqualifiers

- Background only in vendor-customised Moodle (with no understanding
  of Moodle core) — this hire must own the standalone fork.
- No security-awareness training or prior exposure to OWASP Top 10.
- Unwilling to do code review (some senior engineers consider review
  beneath them; this hire's value is largely in review).

## 3. Compensation framing

The recommendation is to position the role at the upper end of the
₹18-24 lakh band — ₹22 lakh — for three reasons:

1. **Niche skill set.** Moodle-PHP engineers are not abundant in the
   Indian fintech market. Most PHP talent has moved to Laravel /
   Symfony stacks; Moodle remains a specialist niche.
2. **Sole-engineer responsibility.** The hire is the second pair of
   eyes for the entire platform. Pricing this at junior-engineer rates
   creates the same key-person concentration we are trying to mitigate.
3. **Cost-of-mistake.** Phase 8's security audit caught 11 blocking
   findings before production. A second pair of eyes on every commit
   would have caught at least some of those at write-time rather than
   audit-time. The premium pays for itself the first time it catches a
   critical defect.

Equity component recommended at standard senior-IC level if Airpay's
ESOP scheme permits.

## 4. Sourcing plan

| Channel | Likely yield | Notes |
|---|---|---|
| Internal referrals (Airpay engineering team) | High signal-to-noise but small pool | Start here; the Airpay engineering team has Moodle-tangential experience. |
| LinkedIn (Moodle Developer / PHP Engineer search) | Medium | Filter on India + experience with multi-tenant SaaS. |
| Moodle community job board (`jobs.moodle.org`) | Low volume, high relevance | Worth posting; Moodle-community engineers are exactly the profile. |
| Mumbai-based consulting firms (eAbyas, Innodata, etc.) | Backup if direct hire fails | Cost-prohibitive long-term but viable for 6-12 month contract while a permanent search runs. |
| Open-source contributors to airpay_* plugins (after Decision 13.6 open-sourcing) | Speculative | Plays out over 12-18 months; not in scope for this hire cycle. |

## 5. Interview process

**Total time investment: 8-10 hours per candidate spread over 2-3
weeks.**

| Stage | Duration | Owner | Pass criterion |
|---|---|---|---|
| 1. Recruiter screen | 30 min | Recruiter | Salary, notice period, motivation alignment |
| 2. Head of L&D screen | 60 min | Nitin | Moodle experience depth, project portfolio, cultural fit |
| 3. Technical deep-dive | 90 min | Nitin + a senior Airpay engineer | Code-reading exercise on `core_renderer.php`; explain the multi-tenant capability scoping question |
| 4. Take-home (24-48 hours) | 4-6 hours | Candidate solo | Build a small Moodle local plugin with one DB table, one web service endpoint, one Mustache template, one privacy provider method. Time-box at 4-6 hours of effort. |
| 5. Take-home review + system-design follow-up | 60 min | Nitin + senior Airpay engineer | Walk through the take-home; design a tenant-scoping helper from scratch and explain the trade-offs |
| 6. Bar-raiser interview | 45 min | A senior engineer from outside L&D (CTO / CTO delegate) | Senior judgment on whether to extend |
| 7. Leadership conversation | 30 min | CHRO or CFO | Compensation, package terms, start date |

**Decision rule:** unanimous Yes across stages 2-6 required. Any one
"weak hire" is a No.

## 6. Onboarding ramp (first 90 days)

| Week | Owner | Deliverable for the new hire |
|---|---|---|
| 1 | Nitin | Pair on the day-to-day workflow. New hire shadows every change. Read `CLAUDE.md`, the master documentation, Supplements A-E. |
| 2 | Nitin | Pair-engineer their first plugin change — a small N-finding follow-up from the backlog. New hire writes; Nitin reviews. |
| 3-4 | Nitin | New hire ships one full plugin README from the open backlog. Walks the Phase 7 multi-role UAT. |
| 5-8 | New hire | Own SENTIENTIA Agents 1 + 2 build. Pair with Nitin on Agent 2 (Claude integration) for one session, then solo. |
| 9-12 | New hire | Own the BizLMS displacement Week 1-2 mapping. Pair on Week 3 renderer changes. |
| 13 | Joint | First quarterly performance review. Adjust scope based on demonstrated capability. |

By day 90 the hire should be capable of shipping a full plugin
end-to-end without supervision and reviewing Nitin's changes with
genuine value-add.

## 7. Success metrics for the hire

Measured at 6 and 12 months post-start.

| Metric | 6-month target | 12-month target |
|---|---|---|
| Code-review coverage | 100% of Nitin's commits reviewed | Same |
| Independent plugin commits | 4 minor + 1 major | 10 minor + 3 major |
| Phase 7 UAT pass rate | Maintained 84/85 | Maintained 84/85 |
| Plugin smoke pass rate | Maintained 84/84 | Maintained 84/84 |
| SENTIENTIA throughput | 10 courses generated end-to-end | 100+ courses |
| BizLMS displacement | P0 complete, P1 in progress | P0 + P1 complete |
| New backlog items shipped | 8-10 | 25-30 |
| Documentation contributions | At least 5 plugin READMEs improved | At least 15 |
| Security audit re-runs | 1, returning GO | 2, both returning GO |

## 8. Failure modes and mitigation

| Failure mode | Likelihood | Mitigation |
|---|---|---|
| Hire underperforms in first 90 days | Medium | Honest 90-day review with exit option; pre-defined performance criteria reduce ambiguity. |
| Hire leaves within 12 months | Medium | Notice-period clause + knowledge-transfer documentation as part of standard exit. The master documentation + supplements is the foundation. |
| Hire and Head of L&D personality mismatch | Low-medium | Stage 5 take-home review surfaces work-style differences; bar-raiser stage adds a third perspective. |
| Compensation creep — hire renegotiates within 12 months | Low | Set expectations honestly during stage 7 leadership conversation. |
| Hire's first major change introduces a regression | Medium | Pair-engineering for the first 4 weeks; PR review required indefinitely after that. |

## 9. Decision required

The Head of L&D recommends initiating the hire process immediately.
Realistic timeline:

- **T+0 (today):** Job description finalised based on this brief.
- **T+1 week:** Posted on LinkedIn + Moodle community board.
- **T+4 weeks:** First-stage screens.
- **T+8-10 weeks:** Offer extended to top candidate.
- **T+12-14 weeks:** Start date (after notice period).
- **T+16-22 weeks:** End of onboarding ramp; hire is ramped.

**Cost of delay:** every month the hire is unfilled compounds the key-
person risk and pushes SENTIENTIA + BizLMS displacement deliveries
right by the same amount. At the SENTIENTIA cost-saving rate of ₹4.8 -
₹14.7 lakh per month at full throughput, the opportunity cost of delay
is materially larger than the hire cost.

## 10. Sample job description draft

```
Title: L&D Platform Engineer — Airpay Academy

Airpay Payment Services is hiring a senior PHP / Moodle engineer to join
the Learning & Development team and own the engineering of the Airpay
Academy platform — a 3,500-user, three-tenant LMS that powers all
statutory training, hiring assessment, and an emerging commercial
training offering for the Indian fintech ecosystem.

You will:
- Pair-engineer every change to the platform with the Head of L&D.
- Lead the build of SENTIENTIA, our in-house SOP-to-SCORM automation
  pipeline using Claude / ElevenLabs / Gamma vendor APIs.
- Own the displacement of the legacy BizLMS vendor stack over Q3 2026.
- Code-review every commit to the production branch.
- Refactor the platform's monolithic theme renderer into maintainable
  composable traits.
- Carry on-call backup for L&D platform incidents.

You bring:
- 5-8 years of PHP web-application engineering experience.
- At least 2 years of Moodle plugin development.
- Comfort with Mustache, SCSS, PHPUnit, AWS SigV4 signing.
- Multi-tenant SaaS background.
- Strong code-review craft.

Compensation: ₹22 lakh per year base + benefits + ESOP, negotiable for
exceptional candidates.

Location: Mumbai HQ, hybrid 3:2.
```

A polished version of this draft, plus the technical take-home brief,
is held at `docs/_working/engineer-hire-jd-draft.md` (to be created
once the recruitment process is initiated).
