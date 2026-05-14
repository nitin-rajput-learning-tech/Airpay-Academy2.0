# Cluster 6 — Platform Infrastructure, AI & Modern UX

**Plugins/areas reviewed:** `local_airpay_core`, `local_airpay_integrations`, `local_airpay_evaluation`, `local_airpay_proctoring`, `local_airpay_lifecycle`, `local_airpay_pages`, `local_airpay_privacy`, `local_airpay_assistant`, `sentientia/` pipeline, `theme/airpayux`

## Summary

Robust plugin-based architecture (30+ local_airpay_* plugins). SENTIENTIA is a 6-agent Python pipeline (SOP → SCORM, via Claude + Gamma + ElevenLabs) — built but NO UI. theme/airpayux is 514 files; core_renderer.php is 2,177 lines (post-decomposition). **AI is bolted-on, not native** — assistant is rate-limited cached chatbot, not embedded in workflows. No real-time, no GraphQL, no Elasticsearch, no OpenTelemetry, no feature flags.

## Per-area

| Area | Status | Top gap |
|------|--------|---------|
| airpay_core (tenant + audit + logger + cron_health) | FUNCTIONAL | OpenTelemetry SDK; distributed tracing; APM tool |
| airpay_integrations | STUB → BETA (KeKa HRIS only) | No HRIS abstraction; no SSO; no Calendar/CRM/Storage/Video CDN |
| airpay_evaluation | PRODUCTION (mature) | NLP sentiment on free-text; AI question generation |
| airpay_proctoring | PRODUCTION (AWS Rekognition) | ML-based cheating detection (hardcoded weights only) |
| airpay_lifecycle | **STUB** | Plugin exists, classes/privacy empty, no tasks |
| airpay_pages | FUNCTIONAL minimal | No headless CMS, no versioning, no SEO |
| airpay_privacy | PRODUCTION (Phase Z.1) | Stable |
| airpay_assistant | BETA (nascent) | Rate-limited Claude chatbot, NOT embedded in course authoring/Q&A/recommendations |
| **SENTIENTIA pipeline** | BUILT pre-production | No UI! L&D needs CLI today; regression suite is a stub |
| theme/airpayux | PRODUCTION (monolithic) | No component library; no design tokens published; no Storybook; vanilla JS (no TS) |

## Strategic questions

| Question | Status |
|----------|--------|
| AI-native or AI-bolted-on? | **BOLTED-ON** (6-9mo to go native) |
| Non-technical L&D person creates course in <2 hours? | **PARTIAL** (SENTIENTIA exists, no UI; ~3mo to enable) |
| API-first? Mobile app consumable? | **PARTIAL** (30+ REST WS, no GraphQL, no OpenAPI spec, no versioning) |
| Observable at 3am? | **NO** (structured logging exists, no APM/OTel/alerting) |
| Plug new HRIS in 1 week vs 1 month? | **1 MONTH** (KeKa inline, no abstract base class) |
| Theme reusable for white-label? | **NO** (monolithic; no component system; no Figma sync) |

## Top 5 strategic bets

1. **Embed SENTIENTIA as core authoring UI** — Moodle plugin wrapping the 6 agents with drag-drop SOP upload, progress bars, error recovery, regression-suite-as-guardrails. **Unlocks "AI-native learning platform" positioning.** (P0, 3-4 months)
2. **Unify integrations via abstract interfaces + mocking framework** — extract `abstract_hris_client` from KeKa; implement BambooHR, Personio, mock. Foundation for SSO + Calendar + CRM. **Enables enterprise contract onboarding in 1 week.** (P0, 3-4 weeks)
3. **Deploy observability stack** (Datadog/Honeycomb + OpenTelemetry) — instrument core paths, SLO dashboards, alerting. **Reduces MTTR from hours to minutes.** (P0, 2-3 months)
4. **Decompose theme into reusable component library** (Storybook + design tokens + Figma sync) — extract 10-15 atomic components, publish CSS custom props + JSON. **White-label readiness.** (P1, 2-3 months)
5. **Semantic search + personalized recommendations** (Elasticsearch or Algolia + embeddings) — embed in catalog + dashboard. **AI-native feel + parity with Coursera/Udemy.** (P1, 2-3 months)
