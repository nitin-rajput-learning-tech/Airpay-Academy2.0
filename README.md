# Sentientia LMS

**Sentientia LMS** is a white-label, enterprise-grade Learning Management and
Learning Experience platform (LMS / LXP). It is built on the Moodle open-source
learning platform and hardened for multi-tenant enterprise scale.

**Airpay Academy** (https://www.airpay.academy) is customer-zero: the first
production deployment, used to harden every feature against real-world scale
(3,500+ users, multi-tenant, multi-language) before the platform is offered to
other enterprises.

## What Sentientia adds on top of Moodle

- **Multi-tenant / multi-customer architecture** — a `local_sentientia_core`
  layer with flag-gated seams (tenant identity, org hierarchy, tenant registry)
  that decouple the product from any single deployment. Every seam ships
  default-legacy, so production behaviour is unchanged until an operator flips it.
- **The Sentientia plugin suite** — 30+ `local_sentientia_*` / `local_airpay_*`
  plugins covering catalog, learning paths, programs, classroom, skills,
  evaluation, exams, gamification, compliance reporting, and more.
- **airpayux theme** — a standalone design-system theme (the Sentientia design
  system base).
- **Additive feature workstreams** — live engagement, AI assistance, the
  SOP-to-SCORM content pipeline, PWA + push, and WhatsApp notifications. Every
  one sits behind a default-OFF feature flag.

## Repository layout

```
moodle-enhancement/        Sentientia source of truth
  local/                   local_* plugins
  theme/airpayux/          the Sentientia theme
  enrol/  blocks/          enrol + block plugins
  docs/                    ADRs, runbooks, audits, cutover guides
  state-cards/             per-plugin state cards
  PROJECT-STATE.md         current phase + history
packaging/                 distributable builds (per-plugin ZIPs + full bundle)
```

## Documentation

- Architecture decisions: `moodle-enhancement/docs/adr/`
- Cutover + deployment: `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md`
  and `moodle-enhancement/DEPLOYMENT-RUNBOOK.md`
- Project state: `moodle-enhancement/PROJECT-STATE.md`

## Built on Moodle (license + attribution)

Sentientia LMS is a derivative work of **Moodle** (https://moodle.org), the
open-source learning platform, and is distributed under the **GNU General Public
License v3 or later** — the same license as Moodle. Moodle is a registered
trademark of Moodle Pty Ltd; the Moodle name and logo remain the property of
Moodle Pty Ltd and are referenced here only to credit the upstream foundation.

This repository retains all upstream Moodle copyright notices. The Sentientia
product name and the airpayux design system are additive, GPL-compatible
contributions of Airpay Payment Services.

## License

GNU GPL v3 or later. The base platform follows the upstream Moodle license; all
Sentientia additions inherit the same license.
