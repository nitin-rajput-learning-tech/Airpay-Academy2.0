## Section 9 — REST API and Integrations Inventory

The platform's integration surface falls into three classes: outbound to vendors, inbound from internal systems, and the Moodle REST API exposed for mobile and third-party consumers.

### 9.1 Outbound integrations (Airpay calls out)

| Counterparty | Direction | Purpose | Auth method | PII flow | Failure handling |
|---|---|---|---|---|---|
| Airpay Payment Gateway (own payment product) | out | Initiate payment from cart checkout; receive callback from gateway after payment confirmation | HMAC checksum over canonical-ordered payload (signed with merchant secret) | Billing name, email, phone, address, GSTN — submitted to gateway with order. Card data never touches our server (PCI-DSS SAQ-A flow). | Generic 500 on exception; webhook IP allow-list with silent 404; amount + currency equality check before `mark_paid` |
| AWS Rekognition `CompareFaces` | out | Compare candidate ID photo to selfie at start of proctored quiz | AWS SigV4 over HTTPS | Two image byte streams sent (not persisted on AWS per documented `CompareFaces` behaviour); only match score retained | curl_exec with 30-second timeout; failure returns `aws_http_5xx` and locks the candidate out pending retry; exponential backoff recommended for Phase 9 |
| AWS S3 PutObject (via browser presigned URL) | out (from browser) | Upload webcam and screen-recording chunks direct from candidate's browser | Presigned URL signed by our server with SigV4 | Webcam and screen footage; retained for `retention_days` (default 90), purged thereafter | The purge task (`purge_old_recordings`) currently marks rows deleted in our DB but the production version of `delete_s3_object()` is a stub — this is finding N2 in the Phase 8 audit and is queued for Phase 9 |
| ElevenLabs Text-To-Speech API | out | Convert narration text into voice MP3 for SENTIENTIA agent 4 | API key in header | Narration text — must be pre-stripped of employee names and PII; the rule is enforced at the agent boundary | [CONFIRM] gate required before each batch — see `CLAUDE.md` Section 9 |
| Gamma slide-generation API | out | Convert narration outline into slide deck for SENTIENTIA agent 3 | API key in header | Narration text only | [CONFIRM] gate required |
| Microsoft Entra (Azure AD) | out | OAuth2 token exchange for SSO | OAuth2 client credentials | Sign-in event metadata | Standard Moodle OAuth2 plugin handles retry and error display |
| Microsoft Graph (planned) | out | Read SharePoint documents, send Teams messages | OAuth2 on-behalf-of | Document content + recipient identifiers | Not yet built — Workstream C |

### 9.2 Inbound integrations (other systems call us)

| Counterparty | Direction | Purpose | Auth method | Failure handling |
|---|---|---|---|---|
| Airpay Payment Gateway callback | in | Confirm payment status after gateway processes a transaction | HMAC checksum over canonical payload + optional IP allow-list (Phase 8.1 B11) | Generic 500 on exception; logs with sensitive-field redaction (`callback_logger::log`) |
| KeKa HRMS webhook | in | Joiner/Mover/Leaver lifecycle events trigger user provisioning, suspension, or org-path moves | Bearer token in header | The `local_airpay_integrations` plugin handles the inbound and writes to its event log; the `local_airpay_lifecycle` plugin acts on the event |
| Moodle Mobile App | in | Standard Moodle web service consumer (mobile app uses the same REST surface as third parties) | Moodle web service token | Standard Moodle handling |

### 9.3 Moodle REST API exposed

The platform exposes approximately one hundred and twenty web service functions across the Airpay plugin set plus the standard Moodle core functions. The full enumeration is in each plugin's `db/services.php`. Web service tokens are managed under `/admin/webservice/tokens.php` and are documented in `.claude/rules/api.md` with the read-vs-write classification — read functions need no human confirmation, write functions require [CONFIRM].

The functions Airpay plugins expose break down as follows:

| Domain | Plugin | Approx WS count |
|---|---|---|
| Cart | `local_airpay_cart` | 12 |
| Proctoring | `local_airpay_proctoring` | 12 |
| Classroom | `local_airpay_classroom` | ~15 |
| Courses admin | `local_airpay_courses` | ~10 |
| Exams | `local_airpay_exams` | ~6 |
| Programs | `local_airpay_programs` | ~10 |
| Learning paths | `local_airpay_learningpath` | ~7 |
| Manager | `local_airpay_manager` | ~5 |
| Notifications | `local_airpay_notifications` | ~5 |
| Org | `local_airpay_org` | ~6 |
| Roles | `local_airpay_roles` | ~5 |
| Skills | `local_airpay_skills` | ~5 |
| Users | `local_airpay_users` | ~6 |
| Evaluation | `local_airpay_evaluation` | ~4 |
| Reports | `local_airpay_reports` | ~3 |
| Request | `local_airpay_request` | 6 |

### 9.4 Rate limits and observed usage

The platform itself does not apply outbound rate limits beyond the ElevenLabs / Gamma per-vendor enforcement. The most heavily called external endpoint in production is AWS Rekognition CompareFaces during the morning hiring-assessment window. AWS Rekognition's standard quota is two hundred transactions per second per account; observed peak load is well below that ceiling.

### 9.5 Failure handling philosophy

Every outbound integration logs its failure with enough context to reconstruct what was attempted, redacts secrets and personally identifiable information at the log boundary, and surfaces a generic error message to the calling user. The pattern is documented in `.claude/rules/api.md`. Three failure modes get special treatment:

- **Network failure to the payment gateway.** The cart transitions to `failed` and the user is shown a friendly retry page rather than a stack trace.
- **AWS Rekognition unavailability during a proctored quiz.** The candidate is shown an "identity verification temporarily unavailable" page and the attempt is held in `verifying` state for up to fifteen minutes pending retry.
- **ElevenLabs quota exhausted during a SENTIENTIA batch run.** The pipeline halts at the current course and surfaces the failure for human review; no partial SCORM is generated.

## Section 10 — Features by User Type

The platform serves nine distinct user types. Each has a separate set of available features. The grid below lists every feature available to that user type, and notes what is new versus v1 and what is still missing.

### 10.1 Learner (employee)

Features available: dashboard with continue-learning widget and recommended courses, course catalogue with tenant filter, course detail page with prerequisites and assessment preview, SCORM and quiz attempt, classroom session enrolment and attendance view, learning path progress, skill radar and gap analysis, certificates of completion, profile edit with photo upload, my-requests page, my-orders page (if on cart-enabled tenant), notification preferences, multilingual selection.

New versus v1: skill radar (Phase 4), photo upload with GD resize, dark-mode toggle, mobile-optimised dashboard, cart and order history (cart-enabled tenants).

Still missing: AI tutor for course questions, social learning (peer comments on courses), mobile-app offline mode for SCORM.

### 10.2 Manager (People Manager)

All learner features plus: team dashboard showing direct reports' progress, approval queue for incoming course requests, bulk approval interface, manager-summary weekly notification, team-level completion reports, performance metrics per direct report.

New versus v1: bulk-approval UI, perf dashboard, route-override capability gated by tenant equality.

Still missing: predictive analytics on team training needs, automated nudge campaigns triggered by manager-defined criteria.

### 10.3 L&D Administrator

All manager features plus: course creation, classroom session scheduling, learning path authoring, programme authoring, evaluation form authoring, notification rule creation, cohort management, organisation tree management, user provisioning via bulk CSV, compliance dashboard with audit export, recompletion rule management, gamification challenge creation, badge management.

New versus v1: native single-user enrol modal, bulk-unenrol CSV, CSV export from every datatable, YAML import-export for role definitions.

Still missing: end-to-end SENTIENTIA pipeline operational, automated SOP-to-SCORM workflow.

### 10.4 Course Author / Subject Matter Expert

Currently an overlapping role with L&D Administrator. Subject Matter Experts in functional teams typically have edit-only access to their own courses, not the broader admin surface. The plan is to introduce a dedicated `course_author` role in Phase 9 of the backlog that scopes capabilities tightly to course-context editing without exposing organisation-level administration.

### 10.5 Compliance Officer

All learner features plus read-only access to the compliance dashboard (`local_airpay_compliance_report`), the audit log for recompletion events, the data subject request workflow inside `local_airpay_privacy`, and a CSV export specifically formatted for statutory reporting (RBI returns, POSH committee returns).

New versus v1: DPDP self-service dashboard, six-state compliance engine, recompletion audit log.

Still missing: scheduled compliance-report email-out (currently the officer pulls manually).

### 10.6 Tenant Administrator (BizLMS category-scoped)

All L&D Administrator features but scoped to a single tenant. The role is held at `CONTEXT_COURSECAT` rather than `CONTEXT_SYSTEM` so the administrator can manage their own tenant's users, courses and reports but cannot touch site-wide configuration. The Phase 7 multi-role UAT explicitly verified that the Tenant Administrator persona is correctly blocked from administrative pages outside their category.

### 10.7 Site Administrator (super-admin)

Every feature. Two named site administrators exist on production today: the platform engineer (Nitin Rajput) and a backup account (`academy@airpay.co.in`) held jointly by L&D and IT for break-glass access.

### 10.8 External Public Learner (tenant /77)

Restricted feature set scoped to the Public tenant: catalogue and course detail (Public-only categories), self-enrolment for free Public-tenant courses, cart and checkout for paid courses, payment via Airpay gateway, invoice download (GST-compliant), profile edit, certificates, support contact.

New versus v1 (entire flow is new — v1 had no commercial tenant): cart, checkout, payment, invoice, refund self-service.

Still missing: per-tenant SSO (external organisations cannot yet plug in their own identity provider), self-service organisation management for tenant administrators inside `/77`.

### 10.9 API Consumer (HRMS, payroll, third-party)

Web service token-based access scoped to specific functions. KeKa HRMS consumes the user-provisioning functions. Future third-party consumers (analytics partners, content vendors) will be onboarded through dedicated tokens with the principle-of-least-privilege scoping documented in `.claude/rules/api.md`.
