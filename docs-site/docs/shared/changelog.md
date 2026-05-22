# What's New vs v1

The v1 → v2 transition shipped a substantial set of changes across every persona. This page lists the deltas grouped by user type, distilled from Section 10 of the [Master Technical &amp; Strategic Documentation (12 May 2026)](https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/blob/production/docs/master/).

## Learner changes

- Skill radar with gap analysis (Phase 4)
- Photo upload on profile with automatic GD resize
- Dark-mode toggle
- Mobile-optimised dashboard (renders correctly at 590 px)
- Cart and order history on cart-enabled tenants (Public)
- Hindi language pack across most-touched plugins
- PWA install prompt on supported browsers (iOS 16.4+, modern Chrome/Edge)
- Push notifications for course reminders and overdue escalations (opt-in)

## Manager changes

All learner changes plus:

- Bulk-approval UI for incoming course requests
- Performance dashboard with team metrics rolled up
- Route-override capability gated by tenant equality (audit fix #4)
- Manager-summary weekly notification digest

## L&amp;D Admin changes

All manager changes plus:

- Native single-user enrol modal (no more drilling into BizLMS)
- Bulk-unenrol CSV (symmetric with bulk-enrol)
- CSV export from every datatable in the platform
- YAML import-export for role definitions
- 24-column HRMS importer with KeKa cron sync
- Customer brand table (ADR-008) — per-customer logos, colours, PWA manifest

## Course Author changes

Effectively identical to L&amp;D Admin for now. A dedicated `course_author` role scoped to course-context editing only is in Phase 9 of the backlog.

## Compliance changes

- DPDP self-service dashboard for data subject requests
- Six-state compliance engine (replaces v1 spreadsheet tracking)
- Recompletion audit log — immutable trail of every certificate refresh
- CSV export formatted for RBI returns and POSH committee returns

## Tenant Admin changes

All L&amp;D Admin changes, scoped to a single tenant. The role context shift from `CONTEXT_SYSTEM` to `CONTEXT_COURSECAT` is new; v1 did not have a category-scoped administrator.

## Site Admin changes

- Switchboard UI for runtime feature-flag management with per-customer + per-tenant overrides
- Customer brand admin (DB-only in Phase 0/1; UI in Phase 2)
- Master-key infrastructure for VAPID PEM envelope encryption (audit fix #6)
- Push delivery log + admin viewer
- Capability inventory README documenting every cross-cutting capability
- PHPUnit suite (53 tests, 141 assertions) for product-critical paths

## External Public Learner changes

**Entire flow is new** — v1 had no commercial tenant.

- Catalogue and course detail (Public-only categories)
- Self-enrolment for free Public-tenant courses
- Cart and checkout for paid courses
- Payment via Airpay gateway
- GST-compliant invoice download
- Profile edit
- Certificates
- Refund self-service

## API Consumer changes

- Documented WS endpoint surface in `.claude/rules/api.md`
- Principle-of-least-privilege token scoping for new integrations
- Mobile-app WS surface (22 read-only endpoints planned for Phase X.1)
- KeKa HRMS user-provisioning cron (Phase 1)
- WhatsApp deepening: notification bridge wired into 4 cron jobs (Stream C)

## Platform-level changes

- Theme: Standalone `airpayux` fork (642 files; no parent theme)
- 30 in-house plugins (vs 0 truly Airpay-owned in v1)
- 8 ADRs documenting cross-cutting architectural decisions
- PWA install flow + service worker offline fallback (Phase D.1)
- Sentientia Live (Mentimeter-style real-time engagement) plugin
- Push notifications via Web Push (RFC 8291 / aes128gcm)
- 60 P1 issues from the audit shipped (Wave 1 + Wave 2)
- Hindi parity at 100 % on critical surfaces

## Planned (not yet shipped)

- AI tutor for course questions (needs ANTHROPIC_API_KEY budget)
- Social learning (peer comments on courses)
- Mobile-app offline mode for SCORM
- Predictive analytics on team training needs (Manager)
- Automated nudge campaigns (Manager)
- End-to-end SENTIENTIA SOP→SCORM pipeline at scale
- Scheduled compliance-report email-out (Compliance Officer)
- Per-tenant SSO for external learners (External Public Learner)
- Mobile-app WS surface Phase X.1 + X.2 (22 read + 14 write endpoints)
