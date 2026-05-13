# Supplement E — Budget Model (12-Month Operating + Capex Projection)

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`
Section 11. Operationalises the financial argument for the platform's
continued investment: cost line items, savings projection, sensitivity
analysis, and decisions that move the budget materially.

All figures in Indian Rupees (₹). Reference exchange rate ₹83 = USD 1.

## 1. Twelve-month forward-looking operating budget

Period: June 2026 to May 2027.

| Line item | Low | Expected | High | Notes |
|---|---|---|---|---|
| **Personnel** | | | | |
| Head of L&D allocation to platform engineering | (already in L&D opex) | (already in L&D opex) | (already in L&D opex) | Director-level salary already in payroll; not a marginal cost |
| **Dedicated L&D Engineer (new hire — Decision 13.3)** | 18,00,000 | 22,00,000 | 26,00,000 | Senior PHP / Moodle. Loaded cost. Start month 3 of 12. 9 months × ₹2.4 lakh/month for upper band. |
| Contract Moodle code reviewer (interim, if hire delayed) | 60,000 | 1,80,000 | 3,60,000 | ₹15k–₹40k per review × quarterly. Falls away once permanent hire lands. |
| **Subtotal personnel** | **18,60,000** | **23,80,000** | **29,60,000** | |
| **Third-party APIs** | | | | |
| ElevenLabs (SENTIENTIA voice gen) | 8,000 | 15,000 | 30,000 | At 10 courses/month, 6-month ramp. ~₹2.5/course. |
| Gamma (SENTIENTIA slides) | 5,000 | 12,000 | 25,000 | At 10 courses/month, 6-month ramp. ~₹75/course. |
| Anthropic Claude (SENTIENTIA narration + documentation generation) | 15,000 | 35,000 | 80,000 | Approximately ₹15-20 per generated course + documentation regenerations. |
| AWS Rekognition (proctoring identity verification) | 6,000 | 25,000 | 80,000 | At 500 proctored attempts/month, ~₹4/attempt. Heavier during hiring windows. |
| AWS S3 (proctoring recordings + backups) | 12,000 | 35,000 | 80,000 | Storage + bandwidth. 90-day retention at projected 200 GB/month. |
| **Subtotal API spend** | **46,000** | **1,22,000** | **2,95,000** | Well within the ₹6 lakh ceiling recommended in Decision 13.2. |
| **Infrastructure** | | | | |
| Production hosting (AWS, IT-managed) | (in IT opex) | (in IT opex) | (in IT opex) | Not separately attributable to L&D. |
| Staging environment | 0 | 60,000 | 1,20,000 | Lower-spec replica. Standing cost during pre-cutover; can scale down after. |
| Backup storage (S3) | 6,000 | 12,000 | 24,000 | Daily DB + file backups; 90-day retention. |
| Observability (APM, logging) | 0 | 80,000 | 2,40,000 | New Relic / Datadog / Sentry. Currently zero spend. |
| **Subtotal infrastructure** | **6,000** | **1,52,000** | **3,84,000** | |
| **Software & tooling** | | | | |
| GitHub seats (engineering) | 0 | 18,000 | 30,000 | Continued; team org. |
| Playwright test runner cloud (if added) | 0 | 30,000 | 60,000 | Currently runs locally; cloud only if CI parallelisation needed. |
| Other dev tooling (Moodle plugin tools etc.) | 0 | 12,000 | 30,000 | |
| **Subtotal tooling** | **0** | **60,000** | **1,20,000** | |
| **Content & translation** | | | | |
| Native-speaker translation review (Hindi, Marathi, Kannada, Swahili) | 30,000 | 80,000 | 1,50,000 | 4 languages × ongoing string review at ~₹20k/language/year. |
| Third-party SCORM content licences (legacy, until SENTIENTIA replaces) | 1,50,000 | 4,00,000 | 8,00,000 | Existing vendor contracts. Decreases monotonically as SENTIENTIA delivers. |
| **Subtotal content** | **1,80,000** | **4,80,000** | **9,50,000** | |
| **Compliance & audit** | | | | |
| Penetration test (annual, external firm) | 1,00,000 | 2,50,000 | 5,00,000 | Pre-cutover + annual refresh. |
| Compliance officer consultation hours (DPDP, POSH, RBI) | 0 | 60,000 | 1,20,000 | Likely already in compliance team opex. |
| **Subtotal compliance** | **1,00,000** | **3,10,000** | **6,20,000** | |
| **GRAND TOTAL** | **21,92,000** | **35,04,000** | **53,29,000** | |

Expected total: ₹35 lakh per year fully-loaded. Net new spend (above
what is already in L&D + IT opex baselines): approximately ₹25-30 lakh.

## 2. Twelve-month savings projection

Period matched. Same expected case.

| Saving | Annualised | Source |
|---|---|---|
| SaaS LMS avoidance (mid-band comparable) | ~31,50,000 | Section 11.1: 3,500 users × ₹75/user/month average mid-band rate × 12 |
| External SCORM authoring vendor displacement | 28,80,000 | 10 courses/month × ₹40k blended internal rate × 6 months at full SENTIENTIA throughput, scaled by ramp |
| Vendor support contract (eAbyas BizLMS, post-displacement) | (varies) | Eliminated by end of FORK-PLAN Q3 |
| Manual compliance chasing labour | 1,80,000 | Approximately 30 hours/month at ₹500/hour blended HR + L&D admin time, displaced by recompletion automation |
| **Total expected annual savings** | **62,10,000+** | |

Net 12-month financial position:

| Scenario | Spend | Saving | Net |
|---|---|---|---|
| Low | 21,92,000 | 50,00,000 | **+28,08,000** |
| Expected | 35,04,000 | 62,10,000 | **+27,06,000** |
| High | 53,29,000 | 75,00,000 | **+21,71,000** |

Across all three scenarios, the platform is materially cash-positive
on operating basis. The expected-case net of ₹27 lakh is approximately
the cost of the engineer hire (Decision 13.3) — the hire is fully
self-funding within twelve months.

## 3. Sensitivity analysis

Three variables move the budget materially. Each is examined below for
how the expected case shifts if the variable moves to its low or high
end.

### 3.1 SENTIENTIA throughput

The pipeline targets ten new SCORM courses per month at full throughput.
What if it delivers five (half) or fifteen (1.5×)?

| SENTIENTIA throughput | API spend (annualised) | Vendor authoring displaced | Net move |
|---|---|---|---|
| 5 courses/month (half) | -₹70,000 | -₹14,40,000 | -₹13,70,000 (worse) |
| 10 courses/month (expected) | baseline | baseline | baseline |
| 15 courses/month (1.5×) | +₹50,000 | +₹14,40,000 | +₹13,90,000 (better) |

Reading: SENTIENTIA throughput is the largest single lever on the
12-month financial result. Doubling throughput approximately doubles
the net cash-positive position.

### 3.2 Engineer hire timing

| Hire start | Personnel cost change | Throughput impact |
|---|---|---|
| Month 1 (immediate hire) | +₹4,40,000 (full 12 months) | SENTIENTIA + BizLMS displacement on schedule; full benefit captured |
| Month 3 (expected) | 0 | Baseline |
| Month 6 (delayed) | -₹6,60,000 (only 6 months loaded) | SENTIENTIA delayed 3 months → -₹7,20,000 savings; BizLMS displacement delayed similarly |
| Never (status quo) | -₹22,00,000 | SENTIENTIA stalls indefinitely; BizLMS displacement slips to 14-week effort; compounding key-person risk |

Reading: delaying the hire past Month 3 trades short-term personnel
saving for larger longer-term opportunity cost. Never-hire is the
financially weakest option because it defers the SENTIENTIA savings
indefinitely.

### 3.3 Public-tenant commercial traction

The cart on Public tenant `/77` enables external paid users. Decision
13.7 frames the commercial productisation question. What if the Public
tenant grows organically?

| Public-tenant paying users | Annualised revenue | Marginal cost (Stripe/Airpay gateway fees ~2%) | Net |
|---|---|---|---|
| 250 (current ~700 free; conversion of small fraction to paid) | ₹15,00,000 (₹500/user/year average) | -₹30,000 | +₹14,70,000 |
| 1,000 (Decision 13.7 review threshold) | ₹60,00,000 | -₹1,20,000 | +₹58,80,000 |
| 5,000 (Section 11.5 scalability ceiling without re-tier) | ₹3,00,00,000 | -₹6,00,000 | +₹2,94,00,000 |

Reading: even modest organic growth on Public tenant funds the entire
operating cost of the platform multiple times over. Above 1,000 paying
users, the platform's commercial story changes character entirely.

## 4. Capital expenditure (Capex) items

Distinct from operating spend. Investments where benefit accrues
beyond a single fiscal year.

| Item | Amount | Justification |
|---|---|---|
| Dedicated staging environment (provisioned) | 60,000 (recurring) + 0 setup | One-time provisioning; recurring is in opex above |
| DR cold-site procedure setup (Supplement G) | 1,50,000 | One-time engineering + IT spend to define and exercise the procedure |
| Observability tooling licence (year 1 commitment) | 80,000 | New Relic / Datadog at expected tier |
| Penetration test (annual) | 2,50,000 | Already in compliance opex |
| Mobile app branded fork (Decision 13.4, IF chosen) | 8,00,000-12,00,000 | One-time React Native customisation + first-year App Store presence |
| AI tutor MVP (Decision 13.5, IF chosen) | 6,00,000-15,00,000 | Includes Anthropic API + retrieval infrastructure + engineering time |

The mobile app and AI tutor capex items are conditional on the
respective Decision 13.4 and 13.5 outcomes. Neither is currently
committed.

## 5. Vendor budget allocation under Decision 13.2

Decision 13.2 recommends a ₹6 lakh annual ceiling covering ElevenLabs +
Gamma + AWS Rekognition + AWS S3. Allocation:

| Vendor | Allocation | Headroom vs expected spend |
|---|---|---|
| ElevenLabs | 50,000 | 35,000 (~70% headroom) |
| Gamma | 50,000 | 38,000 (~76% headroom) |
| Anthropic (added to ceiling — was not in original Decision 13.2 wording) | 1,00,000 | 65,000 (~65% headroom) |
| AWS Rekognition | 1,50,000 | 1,25,000 (~83% headroom) |
| AWS S3 | 1,50,000 | 1,15,000 (~77% headroom) |
| Buffer for new vendors | 1,00,000 | n/a |
| **Total ceiling** | **6,00,000** | |

The ceiling is comfortable relative to projected demand at ten
courses/month. If SENTIENTIA throughput doubles or proctoring volume
triples, the ceiling needs review.

## 6. Quarterly budget review cadence

Recommended cadence:

- **Monthly:** L&D team internal review of API spend versus projection.
  Catch a runaway-cost vendor invoice early.
- **Quarterly:** Joint review with Finance covering personnel, API,
  infrastructure, content. Update the projection.
- **Annually:** Full re-baseline of the model. Update sensitivity
  analysis for the next twelve months.

The reviews are timed to the SENTIENTIA milestone schedule so that any
material variance from the model is correlated to a known deliverable
gap rather than an unexplained drift.

## 7. Decisions surfaced by this model

| Decision | Recommendation | Rationale |
|---|---|---|
| Engineer hire timing | Month 3 (i.e. start hiring now) | Section 3.2 sensitivity shows opportunity cost of delay exceeds saved salary |
| Vendor budget ceiling structure | Per-vendor sub-allocations (per Section 5) | Prevents one vendor consuming the whole ceiling silently |
| Observability tooling investment | Approve at the expected level (₹80k/year) | Mitigates risks I5, I6, S6 with material residual reductions |
| Mobile branded-fork capex | Defer (per Decision 13.4) | Current measurement insufficient to justify ₹8-12 lakh capex |
| AI tutor capex | Defer to Q4 2026 (per Decision 13.5) | SENTIENTIA quality is the precondition; build the precondition first |

## 8. Worked example: monthly cash-flow at expected steady state

This is what month 9 of the 12-month window (i.e. February 2027) would
look like:

| Line | Amount/month |
|---|---|
| Engineer salary (loaded) | -1,83,333 |
| ElevenLabs (10 courses/month) | -1,250 |
| Gamma (10 courses/month) | -1,000 |
| Claude (narration + occasional docs) | -3,000 |
| AWS Rekognition (~500 attempts) | -2,000 |
| AWS S3 (200 GB rolling 90-day) | -3,000 |
| Staging env | -5,000 |
| Backup storage | -1,000 |
| Observability | -6,667 |
| Translation review (amortised) | -6,667 |
| Legacy SCORM licence (declining) | -25,000 |
| **Total monthly spend** | **-₹2,37,917** |
| | |
| SaaS-LMS avoidance (monthly equivalent) | +2,62,500 |
| Vendor SCORM authoring displaced (10 × ₹40k blended × 1.0) | +4,00,000 |
| Manual compliance labour saved | +15,000 |
| **Total monthly saving** | **+₹6,77,500** |
| | |
| **Net monthly cash position** | **+₹4,39,583** |

At steady state, the platform generates approximately ₹4.4 lakh per
month of net positive cash on operating basis. This is the engineering
equivalent of half a senior engineer's salary, every month, in returns
beyond the spend.
