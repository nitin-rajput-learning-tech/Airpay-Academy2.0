# Tenant Administrator — User Guide

!!! info "Who this guide is for"
    You are an **administrator scoped to a single tenant** (Airpay /1, Public /77, or ZEEA /177). You hold the manager role at `CONTEXT_COURSECAT` (level 40) within your tenant's root category — meaning you can manage your tenant's users, courses, and reports, but you **cannot** touch site-wide configuration.

!!! warning "Scope is enforced"
    The Phase 7 multi-role UAT (12 May 2026) deliberately verified that the Tenant Administrator persona is **blocked** from administrative pages outside the assigned category. If you can see site-admin pages, that's a security regression and must be reported.

## Table of contents

1. [Welcome](welcome.md)
2. [Quick Start](quick-start.md) — tenant dashboard, your scope of authority, what you can and can't do
3. [Daily Operations](daily-ops.md) — managing tenant users, courses, classrooms, learning paths
4. [Feature Reference](reference.md)
5. [Troubleshooting &amp; FAQ](troubleshooting.md)
6. [Glossary](../../shared/glossary.md)
7. [What's New vs v1](../../shared/changelog.md#tenant-admin-changes)
8. [Contact &amp; Escalation](../../shared/contact.md)

## Your feature set at a glance

From Section 10.6 of the Master Technical Doc (12 May 2026):

> All L&amp;D Administrator features but scoped to a single tenant. The role is held at CONTEXT_COURSECAT rather than CONTEXT_SYSTEM so the administrator can manage their own tenant's users, courses and reports but cannot touch site-wide configuration.

Practically: every action in the [L&amp;D Administrator guide](../03-ld-administrator/index.md) is available to you, but **only against your own tenant's data**. Cross-tenant operations require escalation to a Site Administrator.
