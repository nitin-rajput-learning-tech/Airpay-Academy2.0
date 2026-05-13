# Supplement A — Full Risk Register

Companion artefact to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`.
The master document's Section 11.6 lists the top ten risks; this supplement
enumerates the complete register of thirty-two identified risks across
nine categories, each with severity × likelihood × mitigation × owner.

**Source:** HEAD `9038454e9` (12 May 2026)
**Review cadence:** monthly L&D + IT review; quarterly leadership review.

## Severity / likelihood scale

- **Severity** — H: catastrophic (data breach, service outage > 4h, regulatory penalty). M: significant (data leak < 100 users, outage 1-4h, audit finding). L: contained (cosmetic, single-user, recoverable in minutes).
- **Likelihood** — H: probable within 12 months. M: possible within 12 months. L: unlikely barring specific trigger.
- **Residual** = post-mitigation rating. Items where residual is anything other than `L/L` need ongoing attention.

## 1. People & process risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| P1 | Key-person dependency — Head of L&D is currently the sole engineer with full platform context | H | M | CHRO + Head of L&D | (a) This documentation suite. (b) Decision 13.3 — dedicated engineer hire. (c) Phase 8.x verification cycle captured every architectural decision in commit messages, audits, runbooks. | H/L until hire lands |
| P2 | No second pair of eyes on code changes (no PR review process) | M | M | Head of L&D | Engage a contract Moodle engineer for periodic code reviews of new plugins (₹15k–₹40k per review). Establish a PR template that captures change rationale. | M/L |
| P3 | Tribal knowledge in commit messages and state cards rather than searchable wiki | M | H | Head of L&D | The master documentation + state cards + plugin READMEs together constitute the searchable knowledge base. Index them in the L&D Confluence / SharePoint workspace. | L/M |
| P4 | Vendor (eAbyas) relationship continuity if the original BizLMS team disbands before the FORK-PLAN completes | M | L | Head of L&D | FORK-PLAN sequences full BizLMS displacement over Q3 2026. After displacement, vendor relationship becomes optional rather than load-bearing. | M/L post-fork |

## 2. Security risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| S1 | Payment gateway callback compromise — financial-fraud impact | H | L (post-Phase-8.1) | Head of L&D + Finance | Phase 8.1 B4 + B11 + B5 + N6. Webhook IP allow-list. Amount + currency equality check on every callback. Audit logger redacts sensitive fields. | H/L |
| S2 | Cross-tenant data leak via missing tenant-equality check | H | L (post-Phase-8.1) | Head of L&D | `\local_airpay_core\tenant::require_access()` helper. Manual pen-test against staging is a hard cutover gate. | H/L |
| S3 | Proctoring identity-photo data accidental persistence (DPDP violation) | H | L | DPO + Head of L&D | `session_manager::submit_identity()` explicit `unset()` of byte streams. Only the match score is persisted. Code-review checklist must catch any future regression. | H/L |
| S4 | Site-administrator account credential compromise | H | L | IT + Head of L&D | (a) MFA enforcement scheduled in Phase 9 backlog. (b) Break-glass account (`academy@airpay.co.in`) held jointly by L&D and IT. (c) Audit log helper now captures every site-admin action for forensic replay. | H/L when MFA lands |
| S5 | Insider threat — disgruntled employee with manager role exfiltrates team data | M | L | CHRO + IT | Tenant-scoping limits blast radius. Audit log surfaces large export operations for review. Offboarding checklist must revoke role before exit interview. | M/L |
| S6 | Open-source dependency CVE in Moodle core, theme Bootstrap, python-docx or any third-party PHP / JS module | M | H | Head of L&D + IT | Subscribe to Moodle Security Advisories. Quarterly dependency audit (`composer audit`, `npm audit`). Have a hot-patch deploy pathway exercised in the deployment runbook. | M/M |
| S7 | Production data accidentally copied into a local-development environment without anonymisation | M | M | Head of L&D | Existing `noemailever` setting + email-wipe scripts protect against accidental email blasts. Add a `mask-pii.php` CLI that anonymises before importing prod snapshots locally. | M/L when script lands |
| S8 | DNS hijack or SSL certificate expiry on `airpay.academy` | H | L | IT | DNS managed by Airpay corporate (not L&D). Certificate auto-renewal via Let's Encrypt or AWS ACM (verify with IT). Monitoring alert on certificate expiry within 14 days. | H/L |
| S9 | Reverse-proxy misconfiguration enabling `X-Forwarded-For` spoof | M | L | IT | Pre-flight item in deployment runbook §0 (Phase 8.2 N2). Verified with IT before each cutover. | M/L |

## 3. Compliance & regulatory risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| C1 | DPDP Act non-compliance — failure to honour a Data Subject Request within the statutory 30 days | H | L | DPO | `airpay_privacy` plugin's DSR self-service is the primary surface. DPO has direct DB access via the audit-log helper for cross-tenant queries. Monthly DSR backlog review. | H/L |
| C2 | RBI statutory training audit failure — Reserve Bank of India inspector cannot reconcile training records for a payment-service-provider employee | H | L | Compliance Officer + Head of L&D | `airpay_compliance_report` six-state engine. `airpay_recompletion` annual reset cycle. Audit log retains records for the seven-year statutory hold. | H/L |
| C3 | POSH committee audit — failure to demonstrate every employee completed POSH training in the relevant cycle | H | M | CHRO + Head of L&D | Recompletion rules cover POSH at 365-day cycle. Compliance dashboard surfaces non-compliant employees. Monthly review with HR. | H/L if dashboard is reviewed; M/M otherwise |
| C4 | AML/KYC training audit failure | H | L | Compliance Officer | Same as C2. | H/L |
| C5 | L&D content copyright violation — third-party SCORM module deployed without verifiable licence | M | L | Compliance Officer | Content register (Excel) tracks licence terms per course. Onboarding for new SCORM content includes a licence-verification checklist. | M/L |
| C6 | Translation quality variance — Hindi / Marathi / Kannada / Swahili strings translated incorrectly causing user confusion | L | M | Head of L&D | Native-speaker review of every string-update commit. Backstop: English fallback always available. | L/L |
| C7 | Cookie / consent banner non-compliance with future Indian regulation modelled on GDPR ePrivacy | M | L | DPO | Consent UI in `airpay_privacy`. Track regulatory developments via the DPO function. | M/L |

## 4. Infrastructure & operations risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| I1 | Production hosting single point of failure (single Apache + MySQL host) | H | M | IT | Decision 13.1 surfaces this. Containerised tier on Fargate planned when Public tenant crosses 2,000 paying users. Daily DB backup + S3 file backup are the current mitigations. | H/M until migration |
| I2 | Database backup not exercised in real restore drill | H | M | IT | Quarterly restore drill scheduled per `DEPLOYMENT-RUNBOOK.md`. The drill has not yet been performed. | H/M until first drill |
| I3 | Disaster recovery cold-site procedure undefined — recovery time objective (RTO) and recovery point objective (RPO) not formalised | H | L | IT + Head of L&D | Backlog item. Requires IT engagement and likely capex for a cold-site environment. | H/L (low likelihood because primary site is hardened) |
| I4 | Database scaling ceiling at single-host MariaDB / MySQL | M | M | IT | Currently sized for current user base + projected 12-month growth. Read-replica + dedicated DB host is the upgrade path. | M/L for 12 months |
| I5 | Cron silent failure undetected | M | H | IT + Head of L&D | Currently no APM. Phase 9 backlog item: structured alerting on cron failure. Manual review of cron_output.log is the interim. | M/M |
| I6 | Performance regression undetected without baseline observability | M | H | IT | k6 load test script exists but staging deploy is the gate to actually run it. Once observed, regressions surface in real-user APM. | M/L when APM lands |
| I7 | SMTP deliverability — emails marked as spam by recipients' filters because the sending IP reputation is weak | M | M | IT | Use a transactional-email service (SendGrid, Postmark, Mailgun, AWS SES) with proper SPF/DKIM/DMARC alignment. Currently sending via Airpay's corporate SMTP relay. | M/L |
| I8 | AWS Rekognition quota exhausted during peak hiring window | M | L | Head of L&D + IT | Phase 8.2 N9 fix adds exponential backoff retry. Quota monitoring alert. Current quota is 200 TPS per account which is far above projected peak. | M/L |
| I9 | AWS S3 retention not actually enforced (recording bytes persist forever) | M | L (post-Phase-8.2) | Head of L&D | Phase 8.2 N2 fix replaces the stub `delete_s3_object()` with a real SigV4 DELETE. Cron is now load-bearing. | M/L |

## 5. Vendor & supply-chain risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| V1 | Airpay payment gateway API contract change (the cart webhook signature scheme shifts) | M | L | Head of L&D + Payments | The cart and the gateway are both Airpay-owned; coordination is in-house. Subscribe to the payment-product changelog. Re-test the cart smoke after every gateway release. | M/L |
| V2 | ElevenLabs API contract or pricing change post-SENTIENTIA-launch | M | M | Head of L&D | Vendor-replaceable: self-hosted alternatives (Coqui, XTTS) exist at higher engineering cost. Build-time abstraction in SENTIENTIA Agent 4 so swap is feasible. | M/L |
| V3 | Gamma API contract or pricing change | M | M | Head of L&D | Same as V2. Build-time abstraction in SENTIENTIA Agent 3. | M/L |
| V4 | Microsoft Entra (Azure AD) tenant changes affecting OAuth2 SSO when it lands | M | L | IT | Track Microsoft Identity blog. Maintain a fallback to local-password auth that can be re-enabled in minutes via Moodle admin. | M/L |
| V5 | KeKa HRMS contract change / API deprecation breaks user provisioning | M | L | HR + IT | `local_airpay_integrations` event log captures every inbound webhook for replay. Manual provisioning path documented as fallback. | M/L |
| V6 | Third-party SCORM content vendor lock-in (Cornerstone, Coursera-for-Business) | L | L | Head of L&D | Every SCORM package is on disk in Moodle filedir; nothing about it is vendor-coupled at the runtime layer. | L/L |
| V7 | python-docx / Pillow / PyMuPDF dependency unmaintained | L | L | Head of L&D | Documentation generator at `docs/_working/generate_docx.py`. If python-docx is unmaintained, switch to pandoc or LibreOffice headless. | L/L |

## 6. Application & code risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| A1 | Recompletion engine misfire — wipes legitimate completions cross-tenant | H | L (post-Phase-8.1) | Head of L&D | Phase 8.1 B6 fix adds tenant scoping. Dryrun flag on every rule. Daily audit-log review for the first 90 days post-launch. | H/L |
| A2 | Manageprices custom-role grants left at CONTEXT_SYSTEM after Phase 8.1 B9 migration | M | M | Head of L&D | Deployment runbook §0 includes the SQL pre-flight that surfaces stale grants. | M/L if pre-flight is run |
| A3 | core_renderer.php (2,339 lines) too large to maintain confidently — refactor risk vs. stability risk | M | H | Head of L&D | Decomposition into traits is queued in TECH-DEBT. Comprehensive test coverage on the renderer is thin — decomposition must come with test additions. | M/H until refactored |
| A4 | Theme template carry-forward gap — Moodle 5.x introduces a new core template not yet overridden in airpayux | M | M | Head of L&D | Local development environment already runs Moodle 5.1.3. `MOODLE5-UPGRADE-RUNBOOK.md` documents the carry-forward discipline. | M/L if discipline holds |
| A5 | Capability check at CONTEXT_SYSTEM in new external WS classes without `tenant::require_access()` follow-up | M | M | Head of L&D | Phase 8.1 audit established the pattern; the tenant helper is non-optional via plugin-dependency version pinning. Code-review checklist must catch any new occurrence. | M/L |
| A6 | Web service token compromise — third-party API consumer's token is leaked | M | L | IT + Head of L&D | Each token is scoped to specific functions (principle of least privilege). Token rotation procedure documented. Audit log captures every token-driven access. | M/L |

## 7. Data & integrity risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| D1 | Custom column drift on `mdl_user` if BizLMS pushes a schema update mid-fork | M | L | Head of L&D | FORK-PLAN absorbs the columns into Airpay-owned plugins. Until then, no Moodle core upgrade is run without a BizLMS-disabled snapshot test. | M/L |
| D2 | SCORM content corruption / SCORM 2004 forward compatibility | L | L | Head of L&D | All current content is SCORM 1.2 which Moodle 4.5 + Moodle 5.x both support. SCORM 2004 not used. | L/L |
| D3 | Cart ledger immutability violated by future code change | H | L | Head of L&D | INSERT-only schema. Phase 8.1 audit verified the cart_manager only inserts; refunds are negative-amount ledger rows, not updates. PHPUnit on cart should be expanded to enforce immutability invariants. | H/L |
| D4 | Invoice numbering collision under concurrent checkout | M | L | Head of L&D | `invoicer::reserve_invoice_number()` retries on collision with a uniqueness DB constraint. Tested in cart smoke. | M/L |
| D5 | Webhook replay attack — same callback delivered twice with same checksum | M | L | Head of L&D | `mark_paid()` is idempotent; re-calling on an already-paid order is a no-op. | M/L |

## 8. UX / accessibility risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| U1 | WCAG 2.1 AA regression — a new template breaks keyboard navigation or screen-reader semantics | M | M | Head of L&D | A11Y series (Phase 4-5) established axe-core regression coverage. Phase 7 multi-role UAT includes mobile + dark-mode checks. | M/L if coverage runs in CI |
| U2 | Mobile-app (Moodle Mobile baseline) becomes unsupported on a newer iOS / Android version | M | L | IT | Moodle Mobile is vendor-maintained by Moodle HQ. Track upstream releases. Decision 13.4 surfaces the branded-fork question. | M/L |
| U3 | Browser compatibility regression — a CSS/JS change breaks Safari or Firefox while passing on Chrome | L | M | Head of L&D | Phase 7 UAT runs on Chromium-channel Chrome. Cross-browser sampling on every major change. | L/L |
| U4 | Multi-language string drift — a feature ships in English without translations | L | M | Head of L&D | Pre-deploy lint catches missing strings. Backstop: English fallback always renders. | L/L |

## 9. Strategic & commercial risks

| # | Risk | Sev | Likelihood | Owner | Mitigation | Residual |
|---|---|---|---|---|---|---|
| ST1 | Public tenant commercial offering doesn't reach economic break-even within 18 months | M | M | CEO + CFO + Head of L&D | Decision 13.7 frames this. Continue without a formal go-to-market motion; revisit at 1,000 paying users threshold. | M/L if growth is organic |
| ST2 | A competitor enters the Indian-fintech-focused L&D niche before Airpay productises | L | M | CEO | Decision 13.7. Competitive window is 12-18 months. Cost of entering late is a marketing motion, not a re-build. | L/M |
| ST3 | SENTIENTIA pipeline produces lower-quality content than vendor-authored modules, undermining the cost-saving thesis | M | M | Head of L&D | Quality regression suite (Phase 9 backlog) compares generated SCORM against three reference courses. Pilot before scale-up. | M/L if quality gate is honoured |
| ST4 | ZEEA tenant contract not renewed — loss of small but real annuity | L | L | CEO + commercial team | Decision 13.8. Maintaining ZEEA costs nothing operationally; loss is purely revenue-side. | L/L |

## Aggregate

- **High residual:** P1 (key-person) — until engineer hire lands.
- **Medium residual:** A3 (core_renderer monolith), I5 (cron silent failure), S6 (dep CVE), C3 (POSH audit if dashboard not reviewed monthly).
- **All other items:** Low residual after planned mitigations.

The single most decisive mitigation across multiple risks is the dedicated L&D engineer hire (Decision 13.3). It directly reduces P1, P2, A3 and indirectly improves residual on S6 (more eyes on dependencies), C3 (more bandwidth for compliance reviews) and U1 (more time for a11y regression coverage).
