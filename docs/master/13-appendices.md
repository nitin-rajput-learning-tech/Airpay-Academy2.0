## Section 15 — Appendices

### Appendix A — Full git commit log

The full commit log of two thousand three hundred and eighty-six commits is held at `docs/_working/git-log-full.txt` in the project repository. The log spans 2 November 2022 (commit `c0c99ac39`, the original vendor "Bayer" initial commit) through 12 May 2026 (HEAD `6ce016150`, Phase 8.3 plugin READMEs and smoke fixes).

Contributor distribution from `git shortlog -s -n --all`:

| Contributor | Commits |
|---|---|
| raju | 386 |
| mahesh | 360 |
| **Nitin Rajput** | **287** |
| eAbyas | 241 |
| Sachin | 221 |
| Niranjan | 179 |
| narendrap | 154 |
| Rizwana | 120 |
| Manasa | 85 |
| kamesh | 72 |
| mallikarjunm | 66 |
| (others) | < 50 each |

The Nitin Rajput identity accounts for the second-largest commit share and approximately one hundred per cent of commits since 14 April 2026. The remaining identities are vendor (eAbyas) personnel whose contributions sit in the pre-handover period.

### Appendix B — File inventory (depth 3)

The repository contains the Moodle 4.5.10 core codebase as a working tree alongside the Airpay-specific work at `moodle-enhancement/`. A depth-3 inventory is available at `docs/_working/file-inventory.txt`. Top-level summary:

| Path | Contents |
|---|---|
| `moodle-enhancement/local/` | 30 Airpay-owned local plugins, each in its own subdirectory |
| `moodle-enhancement/mod/quiz/accessrule/airpay_proctoring/` | The quizaccess subplugin |
| `moodle-enhancement/theme/airpayux/` | 642 files of theme code |
| `moodle-enhancement/state-cards/` | 12 markdown state cards covering each significant work session |
| `moodle-enhancement/audit/` | Audit harnesses (Playwright, k6 load, PHP smokes), audit screenshots, audit reports |
| `moodle-enhancement/docs/` | Plugin-specific documentation |
| `moodle-enhancement/PHASE-8-*.md` | The four phase-8 reports (audit, re-audit, deployment runbook, master phase-8 report) |
| `moodle-enhancement/PROJECT-STATE.md` | The current-phase tracker |
| `moodle-enhancement/ENTERPRISE-GRADE-PLAN.md` | The detailed enterprise-grade build plan |
| `moodle-enhancement/FORK-PLAN.md` | The BizLMS displacement plan |
| `content/` | SENTIENTIA artefact directories (currently empty) |
| `sentientia/` | SENTIENTIA agent prototype scripts |
| `knowledge-automation/` | Workstream C scaffolding |
| `.claude/` | Project-specific Claude configuration (rules, hooks, settings) |
| `CLAUDE.md` | The platform's operating constitution |

### Appendix C — Database schema overview

Approximately fifty Airpay-owned tables across the thirty plugins. Categorised:

**Commerce (5 tables, all in `local_airpay_cart`):**
- `local_airpay_cart_history` — cart/order lifecycle
- `local_airpay_cart_id` — order-number sequence
- `local_airpay_cart_ledger` — append-only payment events
- `local_airpay_cart_invoices` — GST invoices
- `local_airpay_cart_credits` — wallet balance

**Proctoring (5 tables, all in `local_airpay_proctoring`):**
- `local_airpay_proctor_sessions` — one row per proctored attempt
- `local_airpay_proctor_identity` — identity match results
- `local_airpay_proctor_events` — append-only event log
- `local_airpay_proctor_recordings` — S3 chunk pointers
- `local_airpay_proctor_reviews` — human reviewer decisions

**Compliance:**
- `local_airpay_recompletion_rules` and `local_airpay_recompletion_history`

**Course and learning (7+ tables):**
- `local_airpay_classroom`, `local_airpay_classroom_sessions`, `local_airpay_classroom_attendance`, `local_airpay_classroom_users`
- `local_airpay_learningpath`, `local_airpay_learningpath_courses`, `local_airpay_learningpath_users`
- `local_airpay_programs`, `local_airpay_programs_levels`, `local_airpay_programs_courses`, `local_airpay_programs_users`
- `local_airpay_exams`, `local_airpay_featured_courses`, `local_airpay_course_skills`

**Workflow and admin (5+ tables):**
- `local_airpay_request` — course enrolment requests
- `local_airpay_mgr_allocations`, `local_airpay_mgr_requests` — manager workflows
- `local_airpay_roles_auditlog` — role-change audit
- `local_airpay_org` — organisation hierarchy

**Gamification and skills (8+ tables):**
- `local_airpay_badges`, `local_airpay_user_badges`, `local_airpay_streaks`, `local_airpay_points_log`
- `local_airpay_challenge_*` (3 tables)
- `local_airpay_skills`, `local_airpay_skill_cats`, `local_airpay_skill_levels`, `local_airpay_user_skills`, `local_airpay_role_skills`

**Communication and notifications (7+ tables):**
- `local_airpay_email_log`, `local_airpay_email_rules`, `local_airpay_email_overrides`, `local_airpay_email_prefs`
- `local_airpay_notif_log`, `local_airpay_notif_rules`, `local_airpay_notif_prefs`
- `local_airpay_chat_log`, `local_airpay_chat_cache`
- `local_airpay_integration_log`

**Evaluation and ratings (4 tables):**
- `local_airpay_evaluation`, `local_airpay_evaluation_questions`, `local_airpay_evaluation_responses`
- `local_airpay_ratings`

Every Airpay-owned table that holds personally identifiable information also carries a `costcenterid` column or its `open_path` equivalent for tenant scoping, plus the standard Moodle `timecreated` and `timemodified` columns for audit. Every table is referenced through Moodle's `{tablename}` placeholder in queries; no plugin uses raw SQL table names.

### Appendix D — Capability matrix overview

Approximately one hundred custom capabilities across the plugin set:

| Plugin | Capabilities (count) | Default archetype grants |
|---|---|---|
| `airpay_cart` | 5 | user, manager, student, editingteacher (varied per cap) |
| `airpay_classroom` | 6 | manager, editingteacher, trainer |
| `airpay_courses` | 7 | manager, editingteacher |
| `airpay_emails` | 6 | manager, siteadmin |
| `airpay_evaluation` | 2 | manager, student |
| `airpay_exams` | 3 | manager, editingteacher |
| `airpay_learningpath` | 6 | manager, student |
| `airpay_manager` | 3 | manager |
| `airpay_notifications` | 3 | manager |
| `airpay_org` | 6 | manager (system-context for site admin only) |
| `airpay_privacy` | 2 | user, manager |
| `airpay_proctoring` | 5 | manager, editingteacher, student |
| `airpay_programs` | 6 | manager, student |
| `airpay_recompletion` | 3 | manager |
| `airpay_reports` | 3 | manager, editingteacher |
| `airpay_request` | 4 | user, manager |
| `airpay_roles` | 5 | manager (system) |
| `airpay_skills` | 2 | manager, student |
| `airpay_users` | 7 | manager, editingteacher (category-scoped) |
| `airpay_challenge` | 4 | student, manager |

The full capability-by-role matrix is generated dynamically by Moodle at `/admin/roles/check.php`. The static snapshot is maintained at `moodle-enhancement/ACCESS-MATRIX.md` and `moodle-enhancement/access-matrix.xlsx`.

### Appendix E — Glossary

| Term | Meaning |
|---|---|
| Airpay Academy | Airpay's in-house Learning and Development platform. |
| airpayux | The Airpay-owned standalone fork of the Epsilon theme. |
| BizLMS | The commercial multi-tenant Moodle overlay distributed by eAbyas Info Solutions. The platform's pre-fork vendor stack. |
| costcenter | A BizLMS-introduced concept that maps a Moodle category to a multi-tenant organisational unit. Tenant identifiers are `costcenterid` values (e.g. `1`, `77`, `177`). |
| eAbyas | Original platform vendor. Bangalore-based. Bayer-codename predecessor of Airpay Academy was built by eAbyas. |
| FORK-PLAN | The strategic document at `D:\Claude Local\airpay-ld-os\moodle-enhancement\FORK-PLAN.md` that sequences the in-housing programme. |
| KeKa | Airpay's HR Management System; integrated for user provisioning. |
| open_path | The slash-delimited tenant + organisation path stored on every `mdl_user` row (a BizLMS schema extension). Example: `/1/183/184/231` for an Airpay employee in a specific department. |
| Phase 8.x | The intensive verification cycle on 12 May 2026: audit, remediation, re-audit, UAT re-run, plugin READMEs, smoke verification. |
| SCORM | Sharable Content Object Reference Model — the industry-standard packaging format for e-learning content. The platform supports SCORM 1.2. |
| SENTIENTIA | Airpay's planned in-house SOP-to-SCORM pipeline. Six-agent chain. Currently architected, partially prototyped. |
| Tenant root | The first segment of `open_path`. Three values in production: `1` (Airpay), `77` (Public), `177` (ZEEA). |
| Workstream A / B / C | The three streams in the L&D operating plan: Moodle Enhancement, SENTIENTIA, Knowledge Automation. |

### Appendix F — Environment variables

All sensitive configuration is held in a `.env` file at the project root which is excluded from version control via `.gitignore`. The expected key names (not values):

```
MOODLE_URL
MOODLE_TOKEN
ELEVENLABS_API_KEY
ELEVENLABS_VOICE_ID
GAMMA_API_KEY
AZURE_CLIENT_ID
AZURE_CLIENT_SECRET
AZURE_TENANT_ID
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
```

Production values are held by IT in the corporate secrets manager. No production credential ever appears in commit history, in chat transcripts or in this document.

### Appendix G — Operations runbook

Three documents collectively form the operations runbook:

| Document | Purpose |
|---|---|
| `moodle-enhancement/PHASE-8-DEPLOYMENT-RUNBOOK.md` | The canonical cutover procedure: pre-flight checklist, maintenance-mode toggle, rsync deploy, database upgrade, cache purge, smoke test, mainentance-mode off, rollback procedure, 24-hour monitoring, communications templates |
| `moodle-enhancement/DEPLOYMENT-RUNBOOK.md` | General deployment operating procedures (backup, restore, cache purge, role re-assignment) |
| `moodle-enhancement/MOODLE5-UPGRADE-RUNBOOK.md` | The path to a Moodle 5.x core upgrade once stable |

The runbooks are tested via the Phase 7 multi-role UAT harness (which exercises the smoke-test section of the cutover runbook) and through periodic restore drills against staging.

### Appendix H — Contact and escalation matrix

| Role | Primary | Backup | Escalation |
|---|---|---|---|
| Platform engineering | Nitin Rajput (Head of L&D) | (to be hired — see Section 13.3) | Chief Technology Officer |
| Infrastructure | IT team lead | (held by IT) | Chief Technology Officer |
| Statutory compliance content | Compliance Officer | Head of L&D | Chief Human Resources Officer |
| Payment gateway and finance reconciliation | Head of L&D (technical) + Finance team (commercial) | (held by Finance) | Chief Financial Officer |
| Data Privacy / Digital Personal Data Protection Act | Data Protection Officer (`academy@airpay.co.in`) | Head of L&D | Chief Compliance Officer |
| Vendor escalation (ElevenLabs, Gamma, AWS) | Head of L&D | (held by IT for AWS specifically) | Chief Technology Officer |

The Data Protection Officer email `academy@airpay.co.in` is also the platform's break-glass site administrator account. The credentials are jointly held by L&D and IT and are not used for routine administrative work.

---

**End of document.**

Version 1.0 was prepared on 12 May 2026 by the Office of the Head of Learning and Development. Next scheduled review: 12 August 2026.
