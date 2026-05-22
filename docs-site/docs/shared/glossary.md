# Glossary

Terms used across the Airpay Academy 2.0 platform. Where a term comes from a specific framework (Moodle / BizLMS) we note that explicitly.

## Platform terms

| Term | Definition |
|---|---|
| **Airpay Academy** | The brand name of the Learning &amp; Development platform run by Airpay Payment Services. Customer-zero of Sentientia LMS. |
| **Sentientia LMS** | The white-label LMS/LXP product the platform is being shaped into, of which Airpay Academy is the first deployment. |
| **Tenant** | An isolated user population on the platform. Currently three: Airpay (`/1`), Public (`/77`), ZEEA (`/177`). |
| **Customer** | A paying entity that owns one or more tenants. Phase 0/1: only one customer (Airpay). |
| **BizLMS** | The vendor codebase the platform was originally forked from (eAbyas, November 2022). Now being displaced plugin-by-plugin. |
| **Costcenter / costcenterid** | BizLMS's term for a tenant root. The first segment of `$USER->open_path` is the costcenterid. |

## Moodle terms

| Term | Definition |
|---|---|
| **Course** | A container of learning activities (SCORM, quiz, forum, assignment, etc.). Lives in a category. |
| **Category** | A folder of courses. Forms a hierarchy under the site root. Tenant administrators are scoped to a single category. |
| **SCORM** | Sharable Content Object Reference Model. The standard format for self-paced e-learning packages. |
| **Activity** | Anything inside a course that learners interact with: SCORM, quiz, assignment, classroom session, etc. |
| **Cohort** | A named group of users used for bulk operations (enrol, assign, notify). |
| **Capability** | A fine-grained permission like `mod/quiz:attempt`. Capabilities are bundled into roles. |
| **Role** | A bundle of capabilities. Examples: student, teacher, manager, sitemanager. |
| **Context** | A scope at which a role is held. `CONTEXT_SYSTEM` is platform-wide; `CONTEXT_COURSECAT` is a single category; `CONTEXT_COURSE` is a single course. |

## Airpay-specific terms

| Term | Definition |
|---|---|
| `airpayux` | The standalone theme that owns all 642 visual files. Forked from epsilon; no parent theme. |
| `local_airpay_*` | The 30 in-house plugins (catalog, classroom, exams, evaluation, learning paths, programmes, etc.). |
| `local_sentientia_*` | New plugin family for product-level features that aren't tenant-specific. Currently `local_sentientia_pwa` and `local_sentientia_live`. |
| **Switchboard** | The admin UI for toggling feature flags. Lives at `/local/airpay_core/admin/switchboard.php`. |
| **Feature flag** | A runtime toggle on a feature. Default OFF for every new feature. Per-customer + per-tenant override supported. |
| **SENTIENTIA pipeline** | The six-agent SOP→SCORM automation (Parser → Narration → Slides → Voice → Pack → Upload). Architected; not yet operational at scale. |

## Statutory / compliance terms

| Term | Definition |
|---|---|
| **POSH** | Prevention of Sexual Harassment Act, 2013. Statutory training mandatory for all employees. |
| **AML / KYC** | Anti Money Laundering / Know Your Customer regulations. RBI-mandated for payment service providers. |
| **DPDP** | Digital Personal Data Protection Act, 2023. India's data privacy law. |
| **RBI** | Reserve Bank of India. Issues circulars binding on payment service providers. |
| **Recompletion** | Re-running a previously completed course because the certificate has expired. Managed by `local_airpay_recompletion`. |

## Acronyms

| Acronym | Expansion |
|---|---|
| **L&amp;D** | Learning &amp; Development |
| **LMS** | Learning Management System |
| **LXP** | Learning Experience Platform |
| **SME** | Subject Matter Expert |
| **SSO** | Single Sign-On |
| **PWA** | Progressive Web App |
| **VAPID** | Voluntary Application Server Identification (Web Push standard) |
| **WS** | Web Service (Moodle's REST API) |
| **ADR** | Architecture Decision Record |
| **UAT** | User Acceptance Testing |
| **HRMS** | Human Resource Management System (Airpay uses KeKa) |
| **GST** | Goods and Services Tax (India) |
