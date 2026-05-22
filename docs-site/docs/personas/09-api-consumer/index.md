# API Consumer — Integration Cookbook

!!! info "Who this guide is for"
    You are a **developer** integrating Airpay Academy with an external system — typically the KeKa HRMS, a payroll system, an analytics partner, or a content vendor. Unlike the other eight personas, this guide is technical and assumes you can read a `curl` example.

## Table of contents

1. [Welcome](welcome.md) — authentication model, web-service token scoping, rate limits
2. [Quick Start](quick-start.md) — your first authenticated call to `core_user_get_users`, fetching enrolled users
3. [Daily Operations](daily-ops.md) — common integration patterns: user provisioning, completion sync, certificate retrieval
4. [Feature Reference](reference.md) — the full list of exposed REST endpoints, request/response shapes, error codes
5. [Troubleshooting &amp; FAQ](troubleshooting.md) — token rotation, debugging failed calls, rate-limit handling
6. [Glossary](../../shared/glossary.md)
7. [What's New vs v1](../../shared/changelog.md#api-consumer-changes)
8. [Contact &amp; Escalation](../../shared/contact.md)

## Your access surface at a glance

From Section 10.9 of the Master Technical Doc (12 May 2026):

> Web service token-based access scoped to specific functions. KeKa HRMS consumes the user-provisioning functions. Future third-party consumers (analytics partners, content vendors) will be onboarded through dedicated tokens with the principle-of-least-privilege scoping documented in `.claude/rules/api.md`.

## Principle of least privilege

Every API consumer gets a **dedicated web-service token** with the **minimum** set of functions enabled for the integration. Tokens are NOT shared between integrations. If you need a function that isn't enabled on your token, raise a request via [Contact &amp; Escalation](../../shared/contact.md) — do not ask another team to share their token.

## Reference

The authoritative reference for endpoint behaviour, request/response shapes, rate limits, and `[CONFIRM]` gates is `.claude/rules/api.md` in the platform source. The [Feature Reference](reference.md) page of this guide is a learner-friendly distillation of that file; if the two disagree, `.claude/rules/api.md` wins.
