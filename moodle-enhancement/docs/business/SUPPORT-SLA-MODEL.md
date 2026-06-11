# Sentientia LMS — Support & SLA Model (DRAFT for Nitin's sign-off)

> **STATUS: DRAFT — targets are PLACEHOLDER anchors based on what one engineering owner +
> the platform's self-healing tooling can credibly deliver today. Tighten only with headcount.**
> Prepared 2026-06-10 (overnight loop).

## Severity definitions

| Sev | Definition | Examples |
|---|---|---|
| **P1** | Platform down / login broken / data-loss risk for all users of a customer | Site 500s, DB down, auth broken, payment gateway charging wrongly |
| **P2** | Major function broken, no workaround; or one whole tenant impaired | SCORM player dead, certificates not issuing, HRMS sync stopped, a tenant's dashboard blank |
| **P3** | Function degraded with workaround; cosmetic-but-visible; single-user blockers | A report mis-totals, one course's video stalls, notification delay |
| **P4** | Questions, how-to, enhancement requests, content help | "How do I build a learning path?", new feature ask |

## Response / resolution targets (PLACEHOLDER)

| Sev | First response | Workaround | Resolution | Channel |
|---|---|---|---|---|
| P1 | \_\_ (anchor: 1 business hr; 4 hrs off-hours) | \_\_ (anchor: 8 hrs) | \_\_ (anchor: 1 business day) | Phone/WhatsApp + email |
| P2 | \_\_ (anchor: 4 business hrs) | \_\_ (anchor: 2 business days) | \_\_ (anchor: 5 business days) | Email/portal |
| P3 | \_\_ (anchor: 1 business day) | — | \_\_ (anchor: next maintenance release) | Email/portal |
| P4 | \_\_ (anchor: 2 business days) | — | best-effort / roadmap | Email/portal |

- **Support hours (Standard tier):** \_\_ (anchor: Mon–Fri 09:30–18:30 IST, Indian holidays excluded).
- **Priority tier (Enterprise+AI):** adds \_\_ (anchor: P1 24×7 phone bridge, named contact).
- **Uptime commitment (if we host):** \_\_ % monthly (anchor: 99.5% — do NOT promise 99.9%
  until we run redundant infra; airpay.academy today is single-instance).
- Service credits: \_\_ (anchor: 5% monthly fee per 0.5% below target, cap 25%) — or none in v1.

## Maintenance & releases

- **Maintenance window:** \_\_ (anchor: Sunday 02:00–06:00 IST, max 2/month, 72-hr notice).
- **Emergency patching:** security fixes deploy any time with post-hoc notice.
- **Release cadence:** monthly minor (features behind flags, default OFF — already our discipline),
  quarterly platform (Moodle point releases; major upgrades like 5.1→5.2 use the rehearsed
  runbook: sandbox first, parity-checked, customer-approved window).

## Escalation path

1. Customer admin → support mailbox/portal (auto-ticket, sev auto-tagged).
2. Sev breach or P1 → engineering owner (today: Nitin's team) via phone/WhatsApp.
3. 2× breach on same ticket → account owner + customer sponsor call.
4. Monthly service review (Enterprise tier): ticket trends, uptime, roadmap.

## What makes these targets credible today (engineering record)

- `task_health` / `cron_health` CLIs + stuck-task detection → P1s are detectable before users call.
- Feature flags default-OFF → bad features are a flag-flip rollback, not a redeploy.
- The parity CLI + migration runbook → upgrades carry a tested abort path.
- noemailever + cost-defence caps → blast-radius limiters are built into the product.

## Open decisions for Nitin

1. Confirm/replace every \_\_ anchor above.
2. Who staffs L1? (Today everything lands on engineering — fine for customer #2, not for #5.)
3. Ticketing tool (shared mailbox vs Freshdesk/Zoho — Zoho is common in Airpay-sized Indian orgs).
4. Is hosting ours or the customer's? (Changes the uptime row from "commitment" to "assistance".)
5. Penalty/credit regime in v1 contracts, or goodwill-only?
