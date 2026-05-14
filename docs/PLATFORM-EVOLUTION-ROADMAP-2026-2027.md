# Airpay Academy — Platform Evolution Roadmap 2026 → 2027

**Author:** Head of L&D + AI partner
**Date:** 2026-05-14
**Status:** STRATEGIC REVIEW — directional, not committed plan
**Source:** Six parallel cluster reviews in `docs/platform-review-2026-05-14/`

---

## 1. Executive summary

Airpay Academy is **not where it needs to be**. Eight months of BizLMS replacement work has delivered a **stable functional clone** — the four LMS Admin feedback items (Learning Path UX, completion email + cert, ramping reminders, cross-tenant sharing) are now shipped + 80 PHPUnit tests green. Day-1 production deployment is unblocked.

But the bar isn't "BizLMS replacement." The bar is **modern enterprise LMS**. Today's market reference is Cornerstone OnDemand, Docebo, Workday Learning, Degreed, LinkedIn Learning, 360Learning, EdCast, Eightfold AI. Against that bar, this is the inventory:

| | Today | The 2027 product |
|---|---|---|
| **Content authoring** | Manual SCORM build, or use SENTIENTIA via CLI | Drag-drop SOP → SCORM in 90 min, no engineering needed |
| **Discovery** | Search by title + category | Semantic search, AI recommendations, skill-tagged paths |
| **Engagement** | Email + popup, gamification in DB but invisible | WhatsApp + push + streak nudges + cohort chat |
| **Personalization** | Per-tenant catalog scope | Per-learner dynamic paths based on skill gap + role |
| **Analytics** | KPI cards + heatmap | Real-time + predictive risk + BI warehouse export |
| **Identity** | Manual CSV import | SSO + SCIM + auto-provisioning from HRIS |
| **Commerce** | Cart with GST works | Revenue-share contracts between tenants, freemium, subscription tier |
| **Mobile** | Web-responsive | PWA with offline + native push |
| **Infrastructure** | Plugin-based monolith | Observable, event-driven, semantic-search backed |
| **AI** | Bolted-on assistant (rate-limited chatbot) | Native — embedded in authoring, grading, recommendations, Q&A |

The user has said: *"I have no rush to production deployment, complete ownership of the code, we can upgrade the UI, add features, a true learning system."* That posture is the unlock — we can take 12-18 months to do this properly rather than racing to a launch date.

This document picks the **5 highest-leverage moves** and the order to do them in.

---

## 2. Full platform inventory (30 plugins)

| Plugin | Cluster | Origin | Current | Strategic gap |
|--------|---------|--------|---------|---------------|
| `airpay_courses` | 1. Learning | BizLMS local_courses | PRODUCTION | AI recommendations, versioning |
| `airpay_catalog` | 1. Learning | new (LXP) | FUNCTIONAL | API layer, AI feed, mobile audit |
| `airpay_classroom` | 1. Learning | BizLMS classroom | FUNCTIONAL | Virtual classroom integration |
| `airpay_exams` | 1. Learning | BizLMS onlineexams | FUNCTIONAL | xAPI, adaptive difficulty |
| `airpay_learningpath` | 1. Learning | BizLMS learningplan | **STUB** | Path-course mapping unbuilt |
| `airpay_programs` | 1. Learning | BizLMS program | **STUB** | Competency model unbuilt |
| `airpay_recompletion` | 1. Learning | BizLMS recompletion | **STUB** | Admin UI + cron task missing |
| `airpay_compliance_report` | 2. Compliance | new | PRODUCTION | Snapshot purge; audit evidence export |
| `airpay_reports` | 2. Compliance | new | FUNCTIONAL | Custom builder; scheduling |
| `airpay_analytics` | 2. Compliance | new | BETA | BI embed; predictive risk |
| `airpay_users` | 3. People | BizLMS users | FUNCTIONAL | SSO onboarding |
| `airpay_org` | 3. People | local_costcenter | FUNCTIONAL | People directory |
| `airpay_manager` | 3. People | myteam | FUNCTIONAL | 1:1 prep view; skill heatmap |
| `airpay_roles` | 3. People | new | FUNCTIONAL | RBAC inheritance |
| `airpay_skills` | 3. People | skillrepository (flat) | FUNCTIONAL | **Skill graph**; growth view; gap analysis |
| `airpay_gamification` | 4. Engagement | new | FUNCTIONAL (beta) | **UI invisibility** |
| `airpay_challenge` | 4. Engagement | new | FUNCTIONAL | Streak challenges; web push |
| `airpay_ratings` | 4. Engagement | BizLMS ratings | FUNCTIONAL | Moderation; sentiment NLP |
| `airpay_emails` | 4. Engagement | BizLMS notifications | **PRODUCTION** (Sprint B) | Teams stub; **no WhatsApp/SMS** |
| `airpay_notifications` | 4. Engagement | BizLMS notifications | FUNCTIONAL | Push prefs exist, no mobile app |
| `airpay_cart` | 5. Commerce | biz_cart | PRODUCTION | Per-tenant pricing; subscription |
| `airpay_request` | 5. Commerce | BizLMS request | PRODUCTION | (working as designed) |
| Sprint C+D cross-tenant share/request | 5. Commerce | new (this session) | PRODUCTION | Revenue model; branding |
| `airpay_core` | 6. Infra | new | FUNCTIONAL | OpenTelemetry; APM |
| `airpay_integrations` | 6. Infra | new (KeKa only) | STUB→BETA | HRIS abstraction; SSO; Calendar; CRM |
| `airpay_evaluation` | 6. Infra | BizLMS evaluation | PRODUCTION | NLP sentiment; AI Q gen |
| `airpay_proctoring` | 6. Infra | new (AWS) | PRODUCTION | ML cheating detection |
| `airpay_lifecycle` | 6. Infra | new | **STUB** | Plugin exists, empty |
| `airpay_pages` | 6. Infra | new | FUNCTIONAL minimal | Headless CMS |
| `airpay_privacy` | 6. Infra | new (Phase Z.1) | PRODUCTION | Stable |
| `airpay_assistant` | 6. AI | new | BETA | Not embedded in workflows |
| `sentientia/` pipeline | 6. AI | new | BUILT pre-prod | **No UI** — biggest unlock |
| `theme/airpayux` | 6. UX | epsilon fork | PRODUCTION | Monolithic; no component lib |

**Summary:** 7 PRODUCTION, 13 FUNCTIONAL, 5 BETA, 4 STUB, 1 PRE-PROD-BUILT.

---

## 3. Cross-cutting themes

Six themes cut across multiple clusters. Each is a strategic decision rather than a feature.

### 3.1 AI is bolted-on, not native
- SENTIENTIA exists but has no UI (Cluster 6)
- airpay_assistant is a rate-limited chatbot, not embedded in authoring/grading/Q&A (Cluster 6)
- airpay_skills has no AI inference (Cluster 3)
- airpay_catalog has no recommendation feed (Cluster 1)
- airpay_evaluation has no NLP sentiment (Cluster 6)

**Decision needed:** Do we commit to AI-native (every feature has AI co-pilot) or AI-augmented (a few features get AI assist)?

### 3.2 Mobile is responsive, not first-class
- Catalog assumes desktop datatables (Cluster 1)
- Manager dashboard not mobile-optimized (Cluster 3)
- No PWA / service worker (Cluster 4)
- No native app (Cluster 6)
- Push notification prefs exist in DB but no dispatcher (Cluster 4)

**Decision needed:** Web-first PWA or native iOS/Android app?

### 3.3 No real-time / event-driven backbone
- All dashboards page-reload (Cluster 6)
- Manager sees weekly digests, not live activity (Cluster 4)
- Completion events are scattered, no xAPI stream (Cluster 1)
- No WebSocket / SSE (Cluster 6)

**Decision needed:** Adopt event-streaming (Kafka or similar) or stay synchronous?

### 3.4 India-specific gaps are unaddressed
- **WhatsApp is missing** (95% read rates vs ~25% for email) (Cluster 4)
- No UPI-specific payment UX (Cluster 5)
- No SMS fallback for low-internet users (Cluster 4)
- Translations exist for hi/kn/mr/sw but Sprint B/C/D additions are en-only (Cluster 4)

**Decision needed:** Treat India as the primary market or as one of several?

### 3.5 Multi-tenancy: solid foundation, weak economics
- Phase 8.1 hardening + Sprint C+D made tenant isolation rock-solid (no data leakage) (Cluster 5)
- BUT there's no revenue model between tenants, no per-tenant branding, no white-label (Cluster 5)
- Could not generate revenue from external tenants today (Cluster 5)

**Decision needed:** B2B/marketplace go-to-market or stay employee-only?

### 3.6 Skill graph is the missing centrepiece
- airpay_skills is a flat list with level definitions (good) but no graph (Cluster 3)
- learningpath has no skill-based routing (Cluster 1)
- programs has no competency model (Cluster 1)
- assistant has no skill inference (Cluster 6)

**Decision needed:** Make skills the central organizing concept (vs courses)?

---

## 4. The 10 strategic bets, ranked

Each bet rated on effort (S=1-2 weeks, M=1-2 months, L=3-4 months, XL=6+ months) and impact (1-10 for business outcome / engagement / revenue). Ranked by impact/effort ratio.

| # | Bet | Cluster | Effort | Impact | Ratio | Notes |
|---|-----|---------|--------|--------|-------|-------|
| 1 | **WhatsApp Business API + SMS fallback** | 4 | M | 10 | ★★★★★ | India market unlock; 95% read vs 25% email |
| 2 | **Gamification dashboard widget + streak nudges** | 4 | M | 8 | ★★★★ | Surface infrastructure that already exists |
| 3 | **Manager self-service compliance assignment** | 2 | S-M | 8 | ★★★★ | Unblocks manager accountability |
| 4 | **SENTIENTIA core authoring UI** | 6 | L | 10 | ★★★ | "AI-native" positioning unlock; biggest single UX win |
| 5 | **Skill graph + learner growth dashboard** | 3 | L | 9 | ★★★ | Shifts course-centric → skill-centric; competitive parity |
| 6 | **SSO + SCIM provisioning** | 3 | M | 7 | ★★★ | Removes manual CSV friction; required for enterprise contracts |
| 7 | **Revenue-share + contract model** | 5 | L | 8 | ★★ | Unblocks B2B revenue; needed for Public/ZEEA monetization |
| 8 | **Unified data warehouse + BI export** | 2 | L | 7 | ★★ | Enables predictive analytics + audit evidence |
| 9 | **Observability stack (Datadog/Honeycomb + OTel)** | 6 | M | 6 | ★★ | Reduces MTTR; required at 10K+ user scale |
| 10 | **xAPI / cmi5 + unified event stream** | 1 | L | 7 | ★★ | Foundation for #5 and #8 |

**Honourable mentions** (impact 7+ but effort XL): Theme component library, Public marketplace, AI-native learner Q&A.

---

## 5. Phased roadmap

### Phase Α — "Engagement that scales" (Q2-Q3 2026, ~10 weeks)
**Goal:** Daily-active users grows 2-3x. Indian learners feel the platform matches their habits.

- **A1:** WhatsApp Business API + SMS fallback (4 weeks)
- **A2:** Gamification dashboard widget + streak nudges (3 weeks)
- **A3:** Manager self-service compliance assignment (2 weeks)
- **A4:** Lang translations for Sprint B/C/D strings to hi/kn/mr/sw (1 week)

**Success metric:** WhatsApp open rate >90% on next compliance push. DAU 7-day retention +50% vs current.

### Phase Β — "AI-native authoring" (Q3-Q4 2026, ~14 weeks)
**Goal:** Non-technical L&D admin creates a polished SCORM course in <2 hours.

- **B1:** SENTIENTIA core authoring UI — drag-drop SOP, live progress, error recovery (10 weeks)
- **B2:** SENTIENTIA regression suite + guardrails (3 weeks)
- **B3:** AI assistant embedded in course view (Q&A on content) (3 weeks)

**Success metric:** L&D creates 5 net-new courses without engineering. Time-from-SOP-to-published-course drops from 2 weeks to <2 hours.

### Phase Γ — "Skill-centric, not course-centric" (Q4 2026 - Q1 2027, ~14 weeks)
**Goal:** Skill graph becomes the platform's organizing principle. Personalised paths.

- **C1:** Skill graph schema (prerequisites, related-skills, role-skill matrix) (4 weeks)
- **C2:** Learner growth dashboard ("Last 90 days: +3 skills, L3→L4 Python") (3 weeks)
- **C3:** Learning paths dynamic routing based on skill gaps (3 weeks)
- **C4:** Programs competency model + multi-level progression (4 weeks)

**Success metric:** 80% of active learners have a personalised path. Path completion rate >70% (vs current static-list path).

### Phase Δ — "Enterprise-ready" (Q1-Q2 2027, ~12 weeks)
**Goal:** External tenant onboarding goes from 4 weeks to 1 week.

- **D1:** SSO + SCIM provisioning (Okta + Azure AD) (5 weeks)
- **D2:** HRIS abstraction (BambooHR + Personio added to KeKa) (3 weeks)
- **D3:** Tenant white-label + catalog curation (2 weeks)
- **D4:** Revenue-share + contract model + settlement reports (4 weeks)

**Success metric:** 1 new enterprise tenant onboarded in 1 week. Public/ZEEA convert to paid revenue model.

### Phase Ε — "Observable, fast, intelligent" (Q2-Q3 2027, ~12 weeks)
**Goal:** Platform behaves like a modern SaaS at scale.

- **E1:** Observability stack (Datadog/Honeycomb + OpenTelemetry) (6 weeks)
- **E2:** Semantic search + personalised recommendations (Elasticsearch or Algolia + embeddings) (4 weeks)
- **E3:** xAPI / cmi5 + unified event stream (Kafka or Moodle event bus extension) (5 weeks)

**Success metric:** P99 page-load <500ms. Recommendation CTR >25%. Cross-plugin completion events feed a single LRS.

### Phase Ζ — "Mobile + community" (Q3-Q4 2027, ~16 weeks)
**Goal:** Learning is something that happens in 5-minute bursts on the bus.

- **Z1:** PWA with offline video + push notifications (6 weeks)
- **Z2:** Cohort chat + Q&A integrated in course view (4 weeks)
- **Z3:** Native iOS + Android wrapper apps (6 weeks)

**Success metric:** Mobile session share >40% (currently <5%). Cohort retention >85% over 4-week course.

---

## 6. What's NOT on this roadmap (and why)

- **AI-graded essay/code submissions** — too high-risk for compliance domain (regulators want human grader). Maybe Phase H if research bears out.
- **Public open marketplace** — requires payment fraud mitigation + content moderation pipeline. Out of scope until D-phase revenue model proves out.
- **Native iOS/Android apps** — PWA covers 90% of use case in Phase Z. Native is Phase Z3 only after PWA validates.
- **Custom report builder (drag-drop)** — superseded by BI warehouse export (admins use Tableau/PowerBI/Looker on the warehouse).
- **Adaptive difficulty in exams** — Phase Γ skill graph achieves similar outcome via path routing.
- **Author marketplace** — needs publishing pipeline + author payouts. Phase H or later.

---

## 7. The first thing to start TODAY

If we pick ONE thing to start immediately, it's **Phase A1: WhatsApp Business API integration.**

Why:
1. **Highest impact-to-effort ratio** (★★★★★ in the bets table)
2. **India-critical** — 80% of learners on consumer-grade plans; email landing in promotional tab is normal
3. **Self-contained** — new plugin `local_airpay_whatsapp`, follows the proven `airpay_emails` template
4. **Foundation reuse** — Sprint B's notification_sender + rule engine + delivery_log all transfer
5. **Unblocks Phase Β too** — SENTIENTIA-generated course notifications need a high-read channel

Estimated work: 4 weeks
- Week 1: WhatsApp Business API account + template approval flow + connect API client
- Week 2: Plugin scaffold (`local_airpay_whatsapp`) — mirror airpay_emails structure
- Week 3: Template engine + delivery log + per-user opt-out + retry/backoff
- Week 4: Integration into existing rules (compliance reminders, course completions) + PHPUnit suite + observability

The work pattern is already proven (Sprint B shipped airpay_emails in similar time). Risk is low.

**Phase A2 (gamification widget) and A3 (manager self-service) can run in parallel** with different developers — they touch independent code surfaces.

---

## 8. Decisions for the Head of L&D to make

Before we start Phase A, three decisions:

1. **WhatsApp template content** — what's the voice? Formal ("Dear Rasika, your AML training is overdue") or friendly ("Hey Rasika — quick nudge, you have 3 days on AML 👋")? Affects template wording for the 20-30 messages we'll need to ship in week 4.

2. **Identity provider choice for Phase Δ** — Okta or Azure AD or both? Affects SSO scope in Phase D1. Worth confirming with Airpay IT.

3. **AI vendor lock-in posture** — current SENTIENTIA uses Claude (Anthropic) + Gamma + ElevenLabs. Should we abstract to allow OpenAI/Gemini swaps, or commit to Claude as the AI partner? Affects Phase Β architecture (1-2 weeks delta).

---

## 9. References

Six detailed cluster reports under `docs/platform-review-2026-05-14/`:
1. `cluster-1-learning-delivery.md`
2. `cluster-2-compliance-reporting.md`
3. `cluster-3-people-identity.md`
4. `cluster-4-engagement-comms.md`
5. `cluster-5-commerce-marketplace.md`
6. `cluster-6-infra-ai-theme.md`

Sprint A-D delivery + Day-2/Day-3 extensions (the work that brought us to this point):
- `PROJECT-STATE.md`
- `state-cards/airpay_emails-state.md`
- `state-cards/airpay_courses-state.md`
- `deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md`
