# Phase A1 — WhatsApp Business + SMS engagement channel

> **STATUS (2026-05-14):** Plan locked in. Implementation deferred — this is a new track separate from the Phase B0 surface redesigns. Recommended start: after the Course Player redesign closes (Phase B0 complete).

**Goal:** add WhatsApp Business and SMS as engagement channels alongside email, with graceful fall-back when the user hasn't opted in or DLT templates aren't available.

---

## 0. Why this is its own phase

Email reminders work but they're not where learners live. Per BizLMS analytics:
- 12% open rate on course-completion emails in the first 24h
- 38% of learners primarily check work email on mobile only
- 8% never open work email at all (field staff, contractors)

WhatsApp + SMS reach a different audience:
- WhatsApp opens are ~70% within 24h (India market data)
- SMS opens are ~95% within 1h
- Both work without a smartphone (SMS) or with low data (WhatsApp text-only)

For mandatory compliance reminders + deadline nudges, the channel mix matters. Email-only is leaving completion on the table.

---

## 1. Architecture

### New plugin: `local_airpay_whatsapp`

Following the same pattern as `local_airpay_emails`:
- `classes/whatsapp_client.php` — WhatsApp Business API client (Meta or 360dialog or Karix — TBD)
- `classes/sms_client.php` — SMS provider client (TextLocal / MSG91 / Gupshup — TBD)
- `classes/channel_router.php` — decides which channel to use per (user × notification type)
- `classes/dlt_template_registry.php` — DLT-approved template tracker for India compliance
- `db/install.xml` — opt-in table, channel preference table, send log
- `db/feature_flags.php` — `engagement.whatsapp.enabled`, `engagement.sms.enabled` (already exist from Phase A0)

### Extends `local_airpay_emails` cadence engine

The existing email cadence engine (Sprint B + Day-2 settings UI) already has the "send a reminder at days N1, N2, N3 after enrolment" logic. WhatsApp + SMS plug into that pipeline as **alternate channels**, not separate engines.

```php
// In airpay_emails::send_reminder() — Phase A1 will add:
$channel = channel_router::choose($userid, $rule);
switch ($channel) {
    case 'whatsapp':
        whatsapp_client::send_template($userid, $rule->whatsapp_template, $vars);
        break;
    case 'sms':
        sms_client::send($userid, $rule->sms_template, $vars);
        break;
    case 'email':
    default:
        // existing email send path
        break;
}
```

### Channel preference logic

```
For each (user, notification_type):
  1. Is engagement.whatsapp.enabled flag ON for this tenant?
     AND user has opted in to WhatsApp?
     AND a DLT-approved WhatsApp template exists for this notification?
       → send WhatsApp
  2. Else: is engagement.sms.enabled flag ON?
     AND user has a mobile number on file?
     AND a DLT-approved SMS template exists?
       → send SMS
  3. Else: send email (always-available fall-back)
```

The cascade preserves the Phase A0 "graceful degradation" contract. If the feature flag is off → fall through to email. If DLT isn't approved → fall through. If user hasn't opted in → fall through. Email is always the lowest-common-denominator catch.

---

## 2. DLT compliance (India)

Indian telecom regulations require pre-approved templates for transactional/promotional SMS + WhatsApp Business. Templates must be:
- Registered with the operator (Jio / Airtel / Vodafone / BSNL via the DLT portal)
- Categorised as Transactional (no consent needed for service messages like compliance reminders) or Promotional (consent required)
- Static text with variable placeholders — runtime content interpolation only

We'll need:
- A `local_airpay_whatsapp_templates` table tracking template_id, dlt_status (pending/approved/rejected), text, variables
- A nightly cron sync against the DLT portal API
- Pre-flight check in `channel_router::choose()` — never send a template that isn't `approved`

L&D team's approved-template list (rough draft):
1. Course enrolment confirmation (transactional)
2. Course completion certificate ready (transactional)
3. Compliance deadline approaching: 7 days, 3 days, 1 day (transactional)
4. Streak milestone (promotional — needs opt-in)
5. Manager team alert: report has overdue items (transactional)

---

## 3. Iterations

### Iter 1 — opt-in + preference UI (no sending yet)

Add user-facing settings:
- `/local/airpay_whatsapp/preferences.php` — opt-in toggle, channel preference (WhatsApp / SMS / Email-only), mobile number capture
- Privacy: tenant-scoped via `open_path`, GDPR/DPDP-compliant consent record
- DB: `local_airpay_user_channel_prefs` table

Risk: low. UI only, no external API calls.

### Iter 2 — template registry + DLT sync

- Build `dlt_template_registry` table + DLT sync cron task
- Manual upload UI for admins to add new template drafts
- Status tracker: pending → submitted → approved/rejected

Risk: low. Internal data, no sending yet.

### Iter 3 — provider integration (WhatsApp)

- Pick a provider (Meta direct vs 360dialog vs Karix — Karix recommended for Indian SME pricing)
- Build `whatsapp_client::send_template()` with retry + logging
- Test against staging DLT-approved templates
- Wire into the cadence engine fall-back chain

Risk: **med-high**. External API, cost per message, compliance liability. [CONFIRM] required before any production send.

### Iter 4 — provider integration (SMS)

- Same pattern but SMS. MSG91 or Gupshup recommended.
- Cheaper but lower-engagement than WhatsApp
- Wire into the cascade as the second fall-back

Risk: med. Same as iter 3 but on SMS rails.

### Iter 5 — analytics + opt-out

- Channel mix dashboard (% sent via each channel, opens/clicks per channel)
- Universal "stop messaging me" link in every SMS/WhatsApp (DLT-required)
- Bounce/failure tracking → auto-cascade to email on failure

Risk: low. Reporting + analytics.

---

## 4. Cost model

Rough estimates per message (Indian SME pricing as of 2026):
- Email: ~₹0.05 (via Mailgun / Postmark)
- SMS: ~₹0.20 (MSG91)
- WhatsApp transactional: ~₹0.55 (Karix)
- WhatsApp promotional: ~₹1.30 (Karix)

For 3,500 active users × 4 notifications/month = 14,000 messages/month:
- All email: ₹700/month
- All SMS: ₹2,800/month
- All WhatsApp transactional: ₹7,700/month
- Realistic mix (50% WA / 30% SMS / 20% email): ~₹4,800/month

Budget approval required before iter 3.

---

## 5. Phase A0 feature-flag wiring (already shipped)

Two flags already exist from Phase A0:
- `engagement.whatsapp.enabled` (default OFF)
- `engagement.sms.enabled` (default OFF)

Super admin can toggle either via the Switchboard. Tenant-scoped — could enable WhatsApp for Airpay but keep it off for Public until Public has its own DLT registration. The fall-back-to-email is the Phase A0 graceful-degradation contract.

---

## 6. What we're NOT doing

- Two-way conversations (replies, support bot via WhatsApp) — separate phase
- Rich media (images, videos, PDFs over WhatsApp) — transactional templates are text-only by DLT rules
- Marketing campaigns — promotional templates need separate consent flow; phase 2
- International numbers — DLT is India-specific; international users stay on email
- WhatsApp groups, broadcasts — anti-pattern, against DLT rules

---

## 7. Risk register

| Iter | Risk level | Why |
|---|---|---|
| 1 — Opt-in UI | Low | UI only, no external calls |
| 2 — DLT registry | Low | Internal data |
| 3 — WhatsApp | Med-High | External API, cost, compliance |
| 4 — SMS | Med | Same as iter 3 |
| 5 — Analytics | Low | Reporting |

---

## 8. Pre-flight checklist (before iter 3 starts)

- [ ] L&D + Legal sign off on the 5-template starter list
- [ ] DLT registration submitted with the chosen provider
- [ ] Budget approved (~₹5K/month at the mix above)
- [ ] User-facing privacy notice updated (DPDP)
- [ ] Opt-in flow tested in iter 1 with real users on staging
- [ ] Fall-back-to-email path tested when flags are off

---

## 9. References

- `docs/platform-review-2026-05-14/CONFIGURABILITY-ARCHITECTURE.md` §5.3 (fall-back pattern)
- `docs/platform-review-2026-05-14/SURFACE-ROADMAP.md` (engagement section)
- `moodle-enhancement/local/airpay_emails/` — sibling plugin, same patterns reused
- `moodle-enhancement/local/airpay_core/db/feature_flags.php` — flags already declared
- DLT portal: <https://www.smsdltindia.com/> (or operator-specific portals)
