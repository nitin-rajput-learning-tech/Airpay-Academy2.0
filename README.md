# Sentientia LMS

**Sentientia LMS** is a white-label, enterprise-grade Learning Management and
Learning Experience platform (LMS / LXP). It is built on the Moodle open-source
learning platform and hardened for multi-tenant, multi-language enterprise
scale (thousands of users across multiple tenants).

Sentientia is designed to be **fully white-labelled per deployment** — branding,
landing page, login experience, colours, logo, and domain are configurable by
the site administrator, so each customer runs the platform under their own
identity.

## What Sentientia adds on top of Moodle

- **Multi-tenant / multi-customer architecture** — a `local_sentientia_core`
  layer with flag-gated seams (tenant identity, org hierarchy, tenant registry)
  that decouple the product from any single deployment. Every seam ships
  default-legacy, so an existing deployment's behaviour is unchanged until an
  operator flips it.
- **First-party tenant substrate** — Sentientia owns its multi-tenant schema
  end-to-end (see `local_sentientia_core`), so it installs and runs on a clean
  Moodle with no external dependency.
- **The Sentientia plugin suite** — a first-party set of `local_sentientia_*`
  plugins covering catalog, learning paths, programs, classroom, skills,
  evaluation, exams, gamification, compliance reporting, and more.
- **Sentientia design-system theme** — a standalone design-system theme,
  white-labelled per customer.
- **Additive feature workstreams** — live engagement, AI assistance, the
  SOP-to-SCORM content pipeline, PWA + push, and WhatsApp notifications. Every
  one sits behind a default-OFF feature flag.

## Repository layout

```
moodle-enhancement/        Sentientia source of truth
  local/                   local_* plugins (the Sentientia plugin suite)
  theme/                   the Sentientia design-system theme
  enrol/  blocks/          enrol + block plugins
  docs/                    ADRs, runbooks, audits, install + cutover guides
  state-cards/             per-plugin state cards
  PROJECT-STATE.md         current phase + history
packaging/                 distributable builds (per-plugin ZIPs + full bundle)
```

## Documentation

- Install from scratch: `moodle-enhancement/docs/INSTALL-SENTIENTIA.md`
- Architecture decisions: `moodle-enhancement/docs/adr/`
- Cutover + deployment: `moodle-enhancement/docs/cutover/` and
  `moodle-enhancement/DEPLOYMENT-RUNBOOK.md`
- Project state: `moodle-enhancement/PROJECT-STATE.md`

## Built on Moodle (license + attribution)

Sentientia LMS is a derivative work of **Moodle** (https://moodle.org), the
open-source learning platform, and is distributed under the **GNU General Public
License v3 or later** — the same license as Moodle. Moodle is a registered
trademark of Moodle Pty Ltd; the Moodle name and logo remain the property of
Moodle Pty Ltd and are referenced here only to credit the upstream foundation.

This repository retains all upstream Moodle copyright notices. The Sentientia
product and design system are GPL-compatible contributions of Airpay Payment
Services Private Limited.

## License

GNU GPL v3 or later. The base platform follows the upstream Moodle license; all
Sentientia additions inherit the same license.
