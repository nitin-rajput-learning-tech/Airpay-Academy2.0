# Compliance Officer — User Guide

!!! info "Who this guide is for"
    You are the **owner of statutory training reporting** at Airpay. You are responsible for the POSH committee returns, RBI returns, Digital Personal Data Protection Act data-subject requests, AML/KYC training records, and the immutable audit trail that any external auditor will inspect.

## Table of contents

1. [Welcome](welcome.md)
2. [Quick Start](quick-start.md) — opening the compliance dashboard, exporting your first audit CSV
3. [Daily Operations](daily-ops.md) — running the six-state compliance engine, recompletion audit log, data-subject request workflow
4. [Feature Reference](reference.md)
5. [Troubleshooting &amp; FAQ](troubleshooting.md)
6. [Glossary](../../shared/glossary.md)
7. [What's New vs v1](../../shared/changelog.md#compliance-changes)
8. [Contact &amp; Escalation](../../shared/contact.md)

## Your feature set at a glance

From Section 10.5 of the Master Technical Doc (12 May 2026):

> All learner features plus read-only access to the compliance dashboard (local_airpay_compliance_report), the audit log for recompletion events, the data subject request workflow inside local_airpay_privacy, and a CSV export specifically formatted for statutory reporting (RBI returns, POSH committee returns).

## The six-state compliance engine

Every employee under a given regulation falls into exactly one of these states at any moment:

1. **Not enrolled** — user is in scope of the regulation but has no course assignment. *Should be zero on a healthy platform.*
2. **Enrolled, not started**
3. **In progress**
4. **Completed, certificate current**
5. **Completed, certificate expiring within 30 days**
6. **Completed, certificate expired** — user is non-compliant pending re-completion

## What's new for you since v1

- **DPDP self-service dashboard** — data subjects can raise requests; you review &amp; action
- **Six-state compliance engine** — replaces the spreadsheet-based tracking from v1
- **Recompletion audit log** — immutable trail of every certificate refresh

## What's still missing

- Scheduled compliance-report email-out (currently you pull manually)
