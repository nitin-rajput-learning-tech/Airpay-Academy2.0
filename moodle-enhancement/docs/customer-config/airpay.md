# Airpay Payment Services — Customer-Zero Configuration

> **Customer ID:** 1 (`\local_airpay_core\customer::AIRPAY`)
> **Status:** Production (live.airpay.academy)
> **Onboarded:** Inception (Sentientia LMS Day-0 — 2026-05-20)
> **Last reviewed:** 2026-05-20 (Session 2)

This is the customer-zero reference. Future customers' configuration files in
this directory follow the same shape. See [`TEMPLATE.md`](TEMPLATE.md) for the
copy-paste skeleton.

---

## 1. Identity

| Field | Value |
|---|---|
| Legal entity | Airpay Payment Services Private Limited |
| Primary domain | `live.airpay.academy` |
| Internal contact | Nitin Rajput, Head of L&D |
| Contract tier | Internal — Sentientia LMS pilot customer |
| Onboarding date | 2026-05-20 (Sentientia LMS Day-0) |
| Sentientia LMS customer ID | `1` |

---

## 2. Tenant tree

Airpay operates **3 sub-tenants** under the customer:

| Tenant ID | Name | open_path | Users (approx) | Purpose |
|---|---|---|---|---|
| 1 | Airpay | `/1` | ~3,500 | Internal Airpay employees — HRMS-managed enrolment |
| 77 | Public | `/77` | ~variable | Public-facing learners (self-registration enabled) |
| 177 | ZEEA | `/177` | ~limited | ZEEA subsidiary employees |

All three tenants belong to customer ID 1. Each tenant is independently
configurable for feature flags (via the Session 2 customer-tenant scope) but
shares the same Sentientia LMS deployment.

---

## 3. Branding

Customer-level branding overrides for the airpayux theme. Currently consumed
by `\theme_airpayux\output\core_renderer` (Phase 1 will gain a customer
parameter; today these are hardcoded to Airpay's tokens).

| Token | Value | Notes |
|---|---|---|
| Primary | `#0066A7` | Airpay blue — CTAs, links, active nav |
| Primary light | `#e8f2f9` | Hover backgrounds, tinted sections |
| Primary dark | `#004d80` | Pressed states |
| Accent | `#0f7a73` | Teal — secondary actions, tags, success |
| BG | `#F2F4FB` | Page background |
| Logo light | `/theme/airpayux/pix/logo-airpay.png` | 200×60 |
| Logo dark | `/theme/airpayux/pix/logo-airpay-dark.png` | 200×60 |
| Favicon | `/theme/airpayux/pix/favicon-airpay.ico` | 32×32 |
| Font family | Montserrat 400-800 | Google Fonts, self-hosted in Phase 2 |
| Email template logo | `https://www.airpay.academy/email-logo.png` | Per-tenant in W1-7 welcome email plugin |

Per-tenant overrides for the Public-tenant branding live in
`local_airpay_org` settings (different logo, friendlier landing copy).

---

## 4. Feature flags

State of every customer-scope override for Airpay (customer_id=1). Tenant-level
overrides live in the Switchboard; this section lists customer-wide values.

### Currently set (customer-wide)

> **Note:** As of Session 2 (2026-05-20), the customer-level scope layer is
> NOT yet enabled (`sentientia.customer_level_flags.enabled` = OFF, default).
> No customer-wide overrides exist yet. This section is the reference for
> when the gate is flipped ON.

When the gate is flipped ON, the recommended initial Airpay customer-wide
configuration is:

| Flag | Value | Reason |
|---|---|---|
| `ai.assistant.enabled` | ON | Airpay learners use the AI assistant daily |
| `ai.sentientia.enabled` | OFF | SOP→SCORM pipeline still in Phase B1 — not ready |
| `engagement.gamification.enabled` | ON | Live in production with 3,500 users |
| `engagement.whatsapp.enabled` | ON | Airpay's HRMS holds verified phone numbers; high open-rate |
| `commerce.publicMarketplace.enabled` | OFF | Public marketplace is Phase F |
| `identity.sso.enabled` | OFF | Awaiting M365 SSO config (Stream C) |

### Per-tenant overrides within Airpay

| Tenant | Flag | Override | Reason |
|---|---|---|---|
| Public (77) | `engagement.gamification.confetti` | OFF | Compliance-content tone — quieter UX |
| Public (77) | `airpay_users.self_signup.enabled` | ON | Public-facing — anyone can register |
| Airpay (1) | `airpay_users.self_signup.enabled` | OFF | HRMS-managed only — no self-signup |
| ZEEA (177) | (same as Airpay defaults) | — | No tenant-specific overrides yet |

---

## 5. Integrations

External services Airpay has connected:

| Service | Status | Config location | Notes |
|---|---|---|---|
| **Airpay Payments Gateway** | ✅ Connected | `local_airpay_cart` settings | Endpoint: `https://payments.airpay.co.in/pay/index.php` |
| **WhatsApp Business API** | ⏳ Partial | `local_airpay_whatsapp` settings | Templates pre-approved; full deepening in Stream F |
| **AWS Rekognition** (proctoring identity) | ✅ Connected | `local_airpay_proctoring` settings | Match threshold 85% |
| **AWS S3** (proctoring recordings) | ✅ Connected | `local_airpay_proctoring` settings | 90-day retention |
| **ElevenLabs** | ⚪ Not connected | `.env` `ELEVENLABS_API_KEY` | Pending budget approval (Tier 1 #5 Hindi content pipeline) |
| **Gamma** (slide generation) | ⚪ Not connected | `.env` `GAMMA_API_KEY` | SENTIENTIA Agent 3 |
| **Anthropic Claude API** | ⚪ Not connected | `.env` `ANTHROPIC_API_KEY` | Pending budget approval (Tier 1 #4 AI quiz gen + Tier 1 #5 translation) |
| **Microsoft 365 (Azure AD)** | ⚪ Not connected | `local_airpay_integrations` settings | Stream C, Phase 10 |
| **Microsoft Teams** | ⚪ Not connected | `local_airpay_integrations` settings | Phase 10 |
| **Zoom** | ✅ Connected | `local_airpay_classroom` session URLs | Manual paste per session today; OAuth in Phase 11 |

---

## 6. SLA / contracts

Internal customer — no formal SLA contract today.

When Sentientia LMS sells to external customers, this section captures:
- Uptime target (e.g. 99.9% monthly)
- Support tier (Bronze / Silver / Gold)
- Response-time targets (e.g. P1: 1h, P2: 4h, P3: 1 business day)
- Escalation contacts (account manager, technical lead, executive sponsor)
- Renewal date + commercial terms

For Airpay customer-zero:

| Field | Value |
|---|---|
| Uptime target | Best-effort (internal) |
| Support contact | Nitin Rajput (`nitin.rajput@airpay.co.in`) |
| Escalation | Airpay IT |
| Data residency | India (AWS Mumbai region) |
| Contract renewal | N/A — internal |

---

## 7. Compliance posture

| Requirement | Status | Notes |
|---|---|---|
| **DPDP Act 2023 (India)** | ✅ Compliant | `local_airpay_privacy` handles data-subject requests; consent capture in signup |
| **GDPR (EU)** | ⚠️ Partial | Compliant for Airpay's own employees; full Article 30 record-keeping in Phase 1 |
| **SOC 2 Type II** | ⚪ Not pursued | Required if/when Sentientia LMS sells externally; Airpay auditors engaged when ready |
| **ISO 27001** | ⚪ Not pursued | Same — sales-driven prerequisite |
| **POSH (Indian law on workplace harassment)** | ✅ Compliant | Course catalog includes mandatory POSH training, completion-tracked |
| **AML / KYC training (RBI)** | ✅ Compliant | Annual mandatory courses, recompletion cron enforces 365-day cycle |
| **Audit log retention** | 7 years | `local_airpay_feature_flag_audit`, `local_airpay_skills_user_skill_hist`, `local_airpay_roles_audit` etc. |

---

## 8. Pricing model (internal)

Airpay is an internal customer — no chargeback or invoicing model today.

When Sentientia LMS sells externally, recommended pricing dimensions
(decided per-customer at contract time):

- **Per-MAU** (Monthly Active User) — standard SaaS model, easy to forecast
- **Per-tenant** (one fee per sub-tenant, regardless of user count) — for
  customers with many small subsidiaries
- **Tiered features** — Starter (catalog + basic LMS), Pro (+ Sentientia
  Content Pipeline + AI quiz), Enterprise (+ proctoring + cart + SSO)

---

## 9. Operational notes

### Day-2 operations
- **Database backups:** Airpay IT runs AWS RDS automated snapshots (35-day retention)
- **Deploys:** Manual file copy to live server → admin notifications page → cache purge
- **Cron:** Server cron runs `php admin/cli/cron.php` every minute
- **Monitoring:** Airpay's existing infrastructure monitoring

### Known scaling constraints
- 3,500 concurrent users untested — current scale is ~150 daily active
- Single AWS RDS instance — no read replica yet
- Single Moodle file storage — moving to S3 in Phase 2

### Phase 2 priorities for Airpay
1. PWA + push notifications (Tier 1 #2)
2. WhatsApp deepening (Tier 1 #1)
3. Mentimeter clone live polling (Tier 1 #3)
4. AI quiz generation (Tier 1 #4)
5. Hindi course content pipeline (Tier 1 #5)

See ADR-001 §Implementation actions for the full Phase 1 sequence.

---

## 10. Audit trail of this file

| Date | Change | Author |
|---|---|---|
| 2026-05-20 | Created as part of Session 2 / ADR-002 customer-config foundation | Claude (Session 2) |
