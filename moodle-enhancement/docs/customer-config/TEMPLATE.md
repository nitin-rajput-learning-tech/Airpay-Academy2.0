# [CUSTOMER NAME] — Sentientia LMS Customer Configuration

> **Customer ID:** [N — assigned by Sentientia LMS at onboarding]
> **Status:** [Trial / Production / Suspended]
> **Onboarded:** [YYYY-MM-DD]
> **Last reviewed:** [YYYY-MM-DD]

Copy this template to `<customer-slug>.md` (e.g. `acme-corp.md`) when
onboarding a new customer. Replace every `[bracketed placeholder]` with
real values. Sections you don't yet know go to "TBD" — never delete a
section, the structure is the audit-trail contract.

See [`airpay.md`](airpay.md) for customer-zero as a worked example.

---

## 1. Identity

| Field | Value |
|---|---|
| Legal entity | [Full legal name from contract] |
| Primary domain | [customer.sentientia.example] |
| Customer contact | [Name, role, email] |
| Internal account manager | [Sentientia LMS team member] |
| Contract tier | [Starter / Pro / Enterprise] |
| Onboarding date | [YYYY-MM-DD] |
| Sentientia LMS customer ID | [N] |
| Contract start | [YYYY-MM-DD] |
| Contract end | [YYYY-MM-DD or "auto-renew"] |

---

## 2. Tenant tree

[Customer's hierarchical sub-organisation. One row per tenant.]

| Tenant ID | Name | open_path | Users (approx) | Purpose |
|---|---|---|---|---|
| [auto-assigned] | [tenant name] | `/[id]` | [count] | [HR-managed / public / partner / etc.] |

---

## 3. Branding

| Token | Value | Notes |
|---|---|---|
| Primary | `#______` | Brand primary colour |
| Primary light | `#______` | Hover backgrounds |
| Primary dark | `#______` | Pressed states |
| Accent | `#______` | Secondary actions |
| BG | `#______` | Page background |
| Logo light | `[URL or theme path]` | Recommended size 200×60 |
| Logo dark | `[URL or theme path]` | Recommended size 200×60 |
| Favicon | `[URL]` | 32×32 |
| Font family | `[Family name]` | Self-host or Google Fonts |
| Email template logo | `[URL]` | Inline-displayed in welcome/reminder emails |

---

## 4. Feature flags

### Customer-wide (customer_id = [N], tenant_id = 0)

| Flag | Value | Reason |
|---|---|---|
| [flag.key.example] | [ON/OFF] | [why this customer wants this value] |

### Per-tenant overrides (within this customer)

| Tenant | Flag | Override | Reason |
|---|---|---|---|
| [tenant_id] | [flag.key.example] | [ON/OFF] | [why] |

---

## 5. Integrations

| Service | Status | Config location | Notes |
|---|---|---|---|
| [Payment gateway] | [Connected / Pending / Not used] | [plugin settings page] | [endpoint, credentials location] |
| [SSO IdP] | [Connected / Pending] | [plugin settings] | [SAML / OIDC, IdP metadata] |
| [Messaging — WhatsApp / Slack / Teams] | [Connected / Pending] | [plugin settings] | [Business API setup notes] |
| [Voice / TTS — ElevenLabs] | [Connected / Pending] | `.env` | [for SENTIENTIA pipeline only] |
| [AI — Anthropic] | [Connected / Pending] | `.env` | [for AI quiz gen / translation] |
| [Video conferencing — Zoom / Teams] | [Connected / Pending] | [classroom session URLs] | [OAuth or manual paste] |
| [HRMS] | [Connected / Pending] | [airpay_users HRMS sync settings] | [Keka / SAP SuccessFactors / etc.] |

---

## 6. SLA / contract

| Field | Value |
|---|---|
| Uptime target | [99.5% / 99.9% / 99.95%] |
| Support tier | [Bronze / Silver / Gold] |
| Response time — P1 | [1h / 4h / 8h] |
| Response time — P2 | [4h / 1 business day] |
| Response time — P3 | [1 business day / 3 business days] |
| Maintenance window | [e.g. Sundays 02:00-04:00 IST] |
| Escalation chain | [Primary → Secondary → Executive] |
| Data residency | [India / EU / US / customer-specified] |
| Contract renewal | [YYYY-MM-DD or "auto-renew with N-day notice"] |

---

## 7. Compliance posture

| Requirement | Status | Notes |
|---|---|---|
| DPDP Act 2023 (India) | [Required / Not applicable / Compliant] | |
| GDPR (EU) | [Required / Not applicable / Compliant] | |
| SOC 2 Type II | [Required / Not yet] | [target audit date] |
| ISO 27001 | [Required / Not yet] | |
| Industry-specific (HIPAA / PCI-DSS / etc.) | [Required / Not applicable] | |
| Audit log retention | [N years] | [legal requirement reference] |

---

## 8. Pricing model

| Dimension | Value |
|---|---|
| Billing model | [Per-MAU / Per-tenant / Tiered features / Custom] |
| Listed price | [USD / INR / EUR amount] |
| Discount applied | [%] |
| Effective price | [final amount] |
| Billing frequency | [Monthly / Quarterly / Annual] |
| Payment terms | [Net 30 / Net 60 / prepaid] |
| Currency | [INR / USD / EUR] |
| Invoice contact | [email + name] |

---

## 9. Operational notes

### Day-2 operations
- **Database backups:** [Schedule + retention]
- **Deploys:** [Process — file copy / CI/CD / blue-green]
- **Cron:** [Schedule + frequency]
- **Monitoring:** [Tools + alert recipients]

### Known scaling constraints
- [Any current bottlenecks specific to this customer]

### Customer-specific roadmap items
[Features this customer has requested or is gating; cross-reference to
issue tracker if applicable]

---

## 10. Audit trail of this file

| Date | Change | Author |
|---|---|---|
| [YYYY-MM-DD] | Initial onboarding | [name] |
