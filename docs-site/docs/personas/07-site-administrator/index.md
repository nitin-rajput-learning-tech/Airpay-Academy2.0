# Site Administrator (Super-Admin) — User Guide

!!! danger "Break-glass access only"
    You hold **platform-wide** privileges. Every action you take is logged, audited, and visible across all three tenants. Use this role only when no lower-privilege path exists.

!!! info "Who holds this role"
    From Section 10.7 of the Master Technical Doc: only **two named site administrators** exist on production — the platform engineer (Nitin Rajput) and a backup account (`academy@airpay.co.in`) held jointly by L&amp;D and IT for break-glass access. If a third Site Administrator account appears, that's a security incident.

## Table of contents

1. [Welcome](welcome.md) — duty of care, audit posture, break-glass discipline
2. [Quick Start](quick-start.md) — site-admin dashboard tour, the difference between site- and tenant-scoped actions
3. [Daily Operations](daily-ops.md) — *rare by design* — purge caches, run upgrades, deploy plugins, rotate VAPID keys, master-key rotation
4. [Feature Reference](reference.md) — every platform setting from `/admin/`
5. [Troubleshooting &amp; FAQ](troubleshooting.md) — recovery procedures, log inspection, common operational issues
6. [Glossary](../../shared/glossary.md)
7. [What's New vs v1](../../shared/changelog.md#site-admin-changes)
8. [Contact &amp; Escalation](../../shared/contact.md)

## Your feature set at a glance

From Section 10.7: **every feature** in the platform, scoped at `CONTEXT_SYSTEM` (level 10).

Because you can do anything, this guide focuses heavily on **what to avoid** — destructive actions, irreversible changes, configurations that break tenant isolation, and operational procedures that must be done in a specific sequence to avoid downtime.
