# Fact base 20: Feature flags, roles and access

Confidential: Airpay Payment Services internal. Not for external distribution.

Fact-base file for the November 2026 stakeholder packs. Date of record: 20 November 2026.
All paths are relative to the repository root `D:/Claude Local/airpay-ld-os/` unless stated. "Canonical tree" means
`moodle-enhancement/<type>/<plugin>/`; the byte-identical twin `<type>/<plugin>/` is not counted separately.

## 1. Headline numbers

| Fact | Value | Source |
|---|---|---|
| Feature flags registered | **142** | `moodle-enhancement/local/*/db/feature_flags.php` (41 files), `moodle-enhancement/enrol/sentientiasub/db/feature_flags.php` (1 file) and `theme/sentientia/db/feature_flags.php` (1 file) = 43 registry files. `feature_flags::load_registry()` walks every plugin type, themes included |
| Flags whose registered default is ON | **13** | same files, section 3.3 |
| Flags whose registered default is OFF | **129** | same files |
| Flags added after the UAT build (commit `af6e65c0e`) | **8** (6 in commit `4a78b8f82` / `76e8ec572`, 2 in the uncommitted working tree) | `git diff af6e65c0e HEAD`, `git status`, section 3.4 |
| Flags in the registry at the UAT build | 134: 133 in the plugin registries plus 1 in the theme registry (`sentientia.dashboard.blocksdrawer.enabled`, in the UAT build). This matches the 19 Nov deploy record in `PROJECT-STATE.md` (134) | section 3.4 (to be confirmed by the Switchboard export on UAT) |
| Flag resolution levels | 5 overrides plus registered default plus fail-safe `false` | `local/sentientia_platform/classes/feature_flags.php` |
| Plugins that declare capabilities | 48 (41 local, 1 enrol, 6 block) | `moodle-enhancement/{local,enrol,blocks}/*/db/access.php` |
| Capabilities declared | **181** (164 local, 13 block, 4 enrol) | same files |
| Capabilities with no archetype default | 47 | same files |
| Capabilities granted to no role in the development database (site administrator only) | 39 | development database read-only count, section 5.4 |
| Roles created by repository code | 2 (`sentientiaauthor`, `sentientia_externaltrainer`) | `local_sentientia_authoring\author_role`, `local_sentientia_users\external_trainer\role` |
| Tenant roots in the validated list | 3 (1 Airpay, 77 Public, 177 ZEEA) | `local_sentientia_platform\tenant::VALID_TENANTS` |
| Cross-tenant authority | Site administrator, or holder of `local/sentientia_platform:crosstenant` (no archetype default) | ADR-031, `tenant::is_cross_tenant()` |

Status label for every flag in this file: all flags are code-complete in the registry. Feature behaviour behind each
flag carries the status of its owning plugin; the registry rows record whether the flag itself was present in the UAT
build or arrived later. Which flags are switched ON on UAT is a runtime setting stored in the database and is **to be
confirmed** from the UAT Switchboard (owner: Head of Learning & Development). Section 3.5 reports the development
database only.

## 2. How feature flags work

### 2.1 Registry and discovery

| Item | Fact | Source |
|---|---|---|
| Declaration | Each plugin declares `$flags = [key => ['default' => bool, 'description' => string]]` in `db/feature_flags.php` | `local/sentientia_platform/db/feature_flags.php` |
| Discovery | `feature_flags::load_registry()` walks every installed plugin of every type (`core_component::get_plugin_types()`) and merges every readable `db/feature_flags.php`; a later plugin with the same key overwrites an earlier one | `local/sentientia_platform/classes/feature_flags.php` (`load_registry`) |
| Validation | An entry missing `default` or `description` is skipped with a developer-level debugging message | same |
| Unknown keys | `feature_flags::set()` throws `unknownflagkey` for a key absent from the registry. `is_enabled()` returns `false` for an unregistered key | same |
| Duplicate keys | None: 142 rows, 142 unique keys | scripted count, 20 Nov 2026 |
| Registry cache | MUC application cache `feature_flags_registry`, TTL 60 s, static acceleration on. The registry changes only with a code deploy; a deploy ends in a cache purge | `local/sentientia_platform/db/caches.php` |
| Storage of overrides | Table `local_sentientia_feature_flags` (flag key, customer id, tenant id, enabled, modified by, times) | `local/sentientia_platform/db/install.xml` |
| Audit trail | Table `local_sentientia_feature_flag_audit` records old value, new value, actor, reason and time for every change | same; `feature_flags::set()` |

### 2.2 Resolution order

For a user in customer C and tenant T, `is_enabled($key)` resolves in this order and stops at the first row found:

| Step | Scope | Applies when |
|---|---|---|
| 1 | (key, customer C, tenant T): most specific | customer layer ON and C > 0 and T > 0 |
| 2 | (key, customer C, all tenants) | customer layer ON and C > 0 |
| 3 | (key, customer 0, tenant T): "legacy tenant override" | T > 0 |
| 4 | (key, customer 0, tenant 0): global override | always |
| 5 | Registered default from `db/feature_flags.php` | key registered |
| 6 | `false`: fail-safe | key unregistered |

Rules around the order (source: `feature_flags.php`, ADR-002 `docs/adr/ADR-002-customer-level-feature-flags.md`):

- **Customer layer gate.** Steps 1 and 2 run only when `sentientia.customer_level_flags.enabled` resolves ON (default OFF; it is itself resolved without a customer scope, and `set()` refuses to write it at customer scope). With the gate OFF the resolver behaves exactly as the original three-level resolver, which is the backward-compatibility guarantee for Airpay Academy.
- **Customer of the current user.** `customer::current()` derives the customer from the tenant root in the user's `open_path` via `local_sentientia_core\tenant_registry::customer_of()`; with the legacy registry ON, or for a site administrator or guest, it returns customer 1 (Airpay).
- **Tenant of the current user.** First segment of `$USER->open_path` (`/1/2/3` gives 1). Site administrators resolve to tenant 0, so they see the registered defaults and global overrides unless they choose a tenant in the Switchboard.
- **Explicit scope.** `is_enabled_for($key, $customerid, $tenantid)` and `is_enabled_for_tenant($key, $tenantid)` evaluate for another viewer's scope.
- **Writes.** `feature_flags::set($key, $tenantid, ?bool $value, $byuserid, $reason, $customerid)`; a `null` value deletes the override (inherit). A customer-scoped write is refused while the customer layer is OFF (`customer_layer_disabled`). Every effective change writes one audit row.
- **Freshness.** The override table is read once per process into a snapshot that expires after 30 seconds (`SNAPSHOT_TTL`), so long-running processes (cron, adhoc task runners, SSE connections) pick up a flip within 30 s without a restart. The writing process invalidates its own snapshot at once. Every resolution takes one clock read and one snapshot.
- **Test isolation.** The registry and snapshot live in a DI-container object (`local\feature_flags_state`) that PHPUnit resets for every test.

### 2.3 Operating the flags

| Item | Fact | Source |
|---|---|---|
| Admin UI | The Switchboard, `/local/sentientia_platform/admin/switchboard.php?customer=C&tenant=T`, listed under Site administration, Local plugins | `local/sentientia_platform/admin/switchboard.php`, `settings.php` |
| Who may use it | Holders of `moodle/site:config` (site administrators) | `switchboard.php` line 27 |
| Grouping | By the first dotted segment of the key (the "category"), with human labels from `flag_category_*` language strings | `feature_flags::all()`, `category_label()` |
| What it shows per flag | Default, resolved value, and which override layer (global, customer, tenant, legacy tenant) is set | `feature_flags::all()` |
| Rule for new features | Every user-visible feature ships behind a flag, default OFF, per-customer and per-tenant override supported | root `CLAUDE.md` section 5 |
| Flip policy | UAT and live flag flips are decided by the Head of Learning & Development; flags are not flipped without that decision | `.claude/CLAUDE.md` escalation list |

### 2.4 Related settings that are not feature flags

These are Moodle admin settings in `local_sentientia_core` that select between legacy and new data models during the
BizLMS-to-Sentientia transition. They are not in the flag registry and are not audited by it.

| Setting | Purpose | Source |
|---|---|---|
| `local_sentientia_core/tenant_identity_legacy` | Read the tenant from `open_path` as BizLMS did, or from the Sentientia tenant identity layer (ADR-019) | `local/sentientia_core/settings.php` |
| `local_sentientia_core/org_legacy` | Read the supervisor/manager hierarchy from BizLMS `open_supervisorid`, or from the Sentientia org model (ADR-020) | same |
| `local_sentientia_core/org_dualwrite_enabled` | Write both models during migration | same |
| `local_sentientia_core/tenant_registry_legacy` | Validate tenants against the hard-coded list `[1, 77, 177]` (legacy, default ON per the class documentation), or against table `local_sentientia_tenant` (ADR-021) | `local/sentientia_core/classes/tenant_registry.php` |

## 3. The flag registry

### 3.1 Counts by owning plugin

41 local plugins, one enrol plugin and the theme declare flags; 4 of the 43 registry files (`sentientia_analytics`,
`sentientia_api`, `sentientia_content_market`, `sentientia_talent`: 15 flags) exist only in the canonical tree. The
twin tree has no registry file for them, which is a twin-drift item (section 7, risk R7).

| Plugin | Flags | Plugin | Flags |
|---|---|---|---|
| `local_sentientia_platform` | 17 | `local_sentientia_aiquiz` | 3 |
| `local_sentientia_live` | 9 | `local_sentientia_ratings` | 3 |
| `local_sentientia_leaderboard` | 7 | `local_sentientia_recommendations` | 3 |
| `local_sentientia_content_market` | 6 | `local_sentientia_ai`, `_analytics`, `_assistant`, `_evaluation`, `_integrations`, `_learningpath`, `_notifications`, `_privacy`, `_programs`, `_recompletion`, `_reports`, `_request`, `_skills`, `_talent`, `_translate` | 2 each (30) |
| `local_sentientia_api`, `_authoring`, `_calendar`, `_emails`, `_proctoring`, `_pwa`, `_xapi` | 5 each (35) | `local_sentientia_core`, `_courses`, `_lifecycle`, `_m365`, `_pages`, `_roles`, `_whatsapp`, `enrol_sentientiasub` | 1 each (8) |
| `local_sentientia_cart`, `_catalog`, `_classroom`, `_skillsai`, `_users` | 4 each (20) | | |
| `theme_sentientia` (registry at `theme/sentientia/db/feature_flags.php`, repository root only) | 1 | | |

Total: 17 + 9 + 7 + 6 + 35 + 20 + 9 + 30 + 8 + 1 (theme) = 142. The theme row was missing from this table until 20 Nov 2026 (correction below).

### 3.2 Counts by category (first dotted segment of the key)

| Category | Flags | Note |
|---|---|---|
| `sentientia.*` | 115 | Product features, sub-switches, imported-history readers |
| `live.*` | 9 | Live engagement (Mentimeter-style) |
| `engagement.*` | 6 | Gamification, WhatsApp, SMS |
| `ai.*` | 3 | Assistant, authoring pipeline, recommendations |
| `commerce.*` | 3 | Cross-tenant share, request, public marketplace |
| `ux.*` | 3 | Command palette, dark mode, language switcher |
| `identity.*` | 1 | SSO master switch |
| Irregular keys | 2 | `sentientia_m365_enabled`, `sentientia_whatsapp_content_notifications` (underscore form, no dot; they resolve normally but fall in category "other" on the Switchboard) |

### 3.3 Flags whose registered default is ON (13)

| Flag | Plugin |
|---|---|
| `ai.assistant.enabled` | `local_sentientia_platform` |
| `engagement.gamification.enabled` | `local_sentientia_platform` |
| `engagement.gamification.confetti` | `local_sentientia_platform` |
| `commerce.crossTenantShare.enabled` | `local_sentientia_platform` |
| `commerce.crossTenantRequest.enabled` | `local_sentientia_platform` |
| `ux.commandPalette.enabled` | `local_sentientia_platform` |
| `live.realtime.enabled` | `local_sentientia_live` |
| `sentientia.catalog.public_lxp.enabled` | `local_sentientia_catalog` |
| `sentientia.leaderboards.realtime.enabled` | `local_sentientia_leaderboard` |
| `sentientia.leaderboards.optout.enabled` | `local_sentientia_leaderboard` |
| `sentientia.calendar_sync.events.courses` | `local_sentientia_calendar` |
| `sentientia.calendar_sync.events.classroom` | `local_sentientia_calendar` |
| `sentientia.calendar_sync.events.exams` | `local_sentientia_calendar` |

Note: the master switches of the features these sub-flags belong to (`live.enabled`, `sentientia.leaderboards.enabled`,
`sentientia.calendar_sync.enabled`) default OFF, so the ON sub-flags have no visible effect until the master is ON.

### 3.4 Flags added after the UAT build

The UAT build is commit `af6e65c0e`. Eight registry rows are newer. All eight default OFF.

| Flag | Plugin | Placed by | Status label |
|---|---|---|---|
| `sentientia.courses.advanced_actions.enabled` | `local_sentientia_courses` | commit `4a78b8f82` | Built (behind a feature flag, off by default) |
| `sentientia.proctoring.enabled` | `local_sentientia_proctoring` | commit `76e8ec572` | Built (behind a feature flag, off by default) |
| `sentientia.assessment.secure_mode.enabled` | `local_sentientia_proctoring` | commit `76e8ec572` | Built (behind a feature flag, off by default) |
| `sentientia.assessment.seb_preset.enabled` | `local_sentientia_proctoring` | commit `76e8ec572` | Planned (flag reserved; no code reads it) |
| `sentientia.proctoring.recording.enabled` | `local_sentientia_proctoring` | commit `76e8ec572` | Planned (flag reserved; read only by `upload_chunk`, which refuses while it is OFF; no recording feature behind it) |
| `sentientia.assessment.candidates.enabled` | `local_sentientia_proctoring` | commit `76e8ec572` | Planned (flag reserved; no code reads it) |
| `sentientia.classroom.trainer_own_scope.enabled` | `local_sentientia_classroom` | uncommitted working tree, 20 Nov | Built (verified on development), behind a flag, off by default |
| `sentientia.users.external_trainer.enabled` | `local_sentientia_users` | uncommitted working tree, 20 Nov | Built (verified on development), behind a flag, off by default |

The count difference (133 counted at the UAT build versus 134 in the 19 Nov deploy record) is explained by the theme
registry (`theme/sentientia/db/feature_flags.php`, 1 flag, present in `af6e65c0e`), which the 133 count left out. To be
confirmed by the Switchboard export on UAT (owner: platform engineer).

### 3.5 Override state on the development database (not UAT)

Read-only count of `local_sentientia_feature_flags` on the local development database: **35 override rows** covering 31
distinct flags (a test import of a production backup plus development settings). This is development state only and
must not be quoted as UAT or customer state.

| Group | Flags overridden ON at global scope (customer 0, tenant 0) |
|---|---|
| Live engagement | `live.enabled`, `live.allow_anonymous`, `live.realtime.enabled`, `live.questiontype.multichoice`, `.openended`, `.quiz`, `.ranking`, `.rating`, `.wordcloud` |
| Skills and talent | `sentientia.skillsai.enabled`, `.gap_engine`, `.impact_map`, `sentientia.talent.enabled`, `sentientia.talent.opportunities` |
| Analytics and API | `sentientia.analytics.predictive.enabled`, `.roi.enabled`, `sentientia.api.enabled`, `sentientia.api.lti.enabled`, `sentientia.xapi.enabled`, `sentientia.xapi.lrs_endpoint_enabled` |
| Authoring and AI | `sentientia.authoring.enabled`, `sentientia.authoring.tts`, `sentientia.assistant.agentic.enabled`, `sentientia.content_market.enabled` |
| Other | `sentientia.learningpath.adaptive.enabled`, `sentientia.catalog.public_lxp.enabled`, `ux.darkMode.enabled`, `sentientia.customer_level_flags.enabled` (customer layer ON on this database) |
| Tenant or customer scoped | `sentientia.catalog.free_oneclick_enrol.enabled` ON for tenant 1 and tenant 177; `sentientia.dashboard.skillsrecs.enabled` ON for tenant 1 and for customer 1, OFF for customer 2; `sentientia.pwa.install.enabled` OFF globally and for tenant 1 |

Live (`live.*`) and the other flags above are ON on the development database for testing, although the repository
registers them OFF by default.

### 3.6 Full registry (142 flags)

Description = first sentence of the registered description, cleaned of working-note references. "Registry in" tells
whether the row was in the UAT build (`af6e65c0e`). Every row source: `moodle-enhancement/<type>/<plugin>/db/feature_flags.php`
where the plugin column names the component (`local_x` is `local/x`, `enrol_x` is `enrol/x`).

| Flag key | Default | Owning plugin | One-line description | Registry in |
|---|---|---|---|---|
| `sentientia.subscriptions.enabled` | off | `enrol_sentientiasub` | Sentientia LMS recurring subscriptions (ADR-023). | UAT build |
| `sentientia.ai.gateway.enabled` | off | `local_sentientia_ai` | Sentientia AI Gateway ROUTING switch (ADR-028 Phase 2.3). | UAT build |
| `sentientia.ai.live_api.enabled` | off | `local_sentientia_ai` | Org-level Anthropic live-spend gate — the single switch the signed Addendum-A budget decision governs. | UAT build |
| `sentientia.aiquiz.auto_push` | off | `local_sentientia_aiquiz` | Push-to-mod_quiz workflow. Phase G.4 (2026-08-05) replaced the G.0 "quiz id 0" stub with the REAL publisher: approved/edited questions are imported into the course's default shared questi... | UAT build |
| `sentientia.aiquiz.enabled` | off | `local_sentientia_aiquiz` | Sentientia LMS AI Quiz Generation. Master switch. | UAT build |
| `sentientia.aiquiz.live_api` | off | `local_sentientia_aiquiz` | Anthropic live API gate. When OFF, every generate request uses anthropic_client::call_mock() — deterministic 10-question output, zero cost. | UAT build |
| `sentientia.analytics.predictive.enabled` | off | `local_sentientia_analytics` | Master switch for predictive analytics surfaces in the analytics dashboard: at-risk completion forecasting and team skill-gap projection. | UAT build |
| `sentientia.analytics.roi.enabled` | off | `local_sentientia_analytics` | Training ROI surface in the analytics dashboard. | UAT build |
| `sentientia.api.enabled` | off | `local_sentientia_api` | Master switch for the versioned public REST API (/v1/). | UAT build |
| `sentientia.api.lti.enabled` | off | `local_sentientia_api` | Master switch for the LTI 1.3 provider + consumer. | UAT build |
| `sentientia.api.scim.enabled` | off | `local_sentientia_api` | Sub-switch for the SCIM 2.0 provisioning endpoint (/local/sentientia_api/scim/v2.php — Users resource) used by enterprise IdPs (Entra ID, Okta) to create, update and deactivate accounts. | UAT build |
| `sentientia.api.webhooks.enabled` | off | `local_sentientia_api` | Sub-switch for OUTBOUND webhooks (ADR-030 Wave A): course completion, enrolment and certificate events are queued and POSTed to customer-registered https endpoints with an HMAC-SHA256 sig... | UAT build |
| `sentientia.api.write.enabled` | off | `local_sentientia_api` | Sub-switch for WRITE endpoints of the public API (e.g. | UAT build |
| `sentientia.assistant.agentic.enabled` | off | `local_sentientia_assistant` | Sentientia Agentic Copilot. Master switch for the RAG + tool-use agent loop. | UAT build |
| `sentientia.assistant.agentic.live_api` | off | `local_sentientia_assistant` | Anthropic live API gate for the agent loop. | UAT build |
| `sentientia.authoring.enabled` | off | `local_sentientia_authoring` | Sentientia LMS GenAI Authoring Studio. Master switch. | UAT build |
| `sentientia.authoring.live_api` | off | `local_sentientia_authoring` | Live external-API gate. When OFF, EVERY generation (Anthropic course draft) and EVERY voiceover (ElevenLabs TTS) uses the deterministic mock client — zero cost, no internet, no credenti... | UAT build |
| `sentientia.authoring.publish.enabled` | off | `local_sentientia_authoring` | Course-builder publish (ADR-028 gate #3 closure, 2026-08-05). | UAT build |
| `sentientia.authoring.tts` | off | `local_sentientia_authoring` | TTS voiceover surface (productized Workstream-B ElevenLabs pipeline). | UAT build |
| `sentientia.content.scd.enabled` | off | `local_sentientia_authoring` | Sentientia Canonical Course Document (ADR-034), master switch of the content pipeline. | UAT build |
| `sentientia.calendar_sync.enabled` | off | `local_sentientia_calendar` | Sentientia LMS calendar sync (outbound ICS feed). | UAT build |
| `sentientia.calendar_sync.events.classroom` | ON | `local_sentientia_calendar` | Include classroom (instructor-led training) sessions in the ICS feed. | UAT build |
| `sentientia.calendar_sync.events.courses` | ON | `local_sentientia_calendar` | Include course completion deadlines in the ICS feed. | UAT build |
| `sentientia.calendar_sync.events.exams` | ON | `local_sentientia_calendar` | Include exam close-dates in the ICS feed. | UAT build |
| `sentientia.calendar_sync.oauth.enabled` | off | `local_sentientia_calendar` | Sentientia LMS Calendar Sync — OAuth 2.0 bi-directional sync (Microsoft 365 + Google Calendar). | UAT build |
| `sentientia.cart.gst_tax_invoices` | off | `local_sentientia_cart` | Issues GST tax invoices and credit notes for course sales. | UAT build |
| `sentientia.cart.imported_credits.enabled` | off | `local_sentientia_cart` | Adds the admin credits page (Credits, linked from All orders) that lists the credit bookings and balances imported from BizLMS (ADR-032), per tenant. | UAT build |
| `sentientia.cart.imported_orders.enabled` | off | `local_sentientia_cart` | Shows the order history imported from BizLMS (ADR-032) to order administrators: the order list, the order detail, the "Issued in ERPNext as" reference that stands in for an invoice, and t... | UAT build |
| `sentientia.cart.purchase_receipts` | off | `local_sentientia_cart` | Gives the buyer a payment receipt for every paid order: the receipt page and its PDF (receipt.php), a Receipt link in the order history and on the order page, and a link to it in the orde... | UAT build |
| `sentientia.catalog.course_type_labels.enabled` | off | `local_sentientia_catalog` | Course card type label from the course types (ADR-032 course_lookups import, 2026-10-01). | UAT build |
| `sentientia.catalog.free_oneclick_enrol.enabled` | off | `local_sentientia_catalog` | One-click free self-enrolment for internal-tenant employees. | UAT build |
| `sentientia.catalog.public_lxp.enabled` | ON | `local_sentientia_catalog` | Public guest storefront (public.php) LXP / Netflix restyle. | UAT build |
| `sentientia.catalog.storefront_checkout.enabled` | off | `local_sentientia_catalog` | Storefront basket to order cart hand-off. | UAT build |
| `sentientia.classroom.bulk_enrol_audience` | off | `local_sentientia_classroom` | Bulk enrolment of a classroom by target audience. | UAT build |
| `sentientia.classroom.import_history` | off | `local_sentientia_classroom` | Readers for the classroom history the BizLMS import brings in (ADR-032, classroom code fix 12). | UAT build |
| `sentientia.classroom.qr_attendance` | off | `local_sentientia_classroom` | Entry point to QR attendance (2026-09-30 review). | UAT build |
| `sentientia.classroom.trainer_own_scope.enabled` | off | `local_sentientia_classroom` | Internal trainers see only their own classrooms (ADR-036). | working tree |
| `sentientia.content_market.coursera.enabled` | off | `local_sentientia_content_market` | Enable the Coursera for Business content provider adapter. | UAT build |
| `sentientia.content_market.enabled` | off | `local_sentientia_content_market` | Master switch for the third-party Curated Content Marketplace. | UAT build |
| `sentientia.content_market.go1.enabled` | off | `local_sentientia_content_market` | Enable the Go1 content provider adapter. | UAT build |
| `sentientia.content_market.skills_mapping.enabled` | off | `local_sentientia_content_market` | Auto-map imported catalog items to the Sentientia skills taxonomy (provided by local_sentientia_skillsai). | UAT build |
| `sentientia.content_market.skillsoft.enabled` | off | `local_sentientia_content_market` | Enable the Skillsoft Percipio content provider adapter. | UAT build |
| `sentientia.content_market.udemy_business.enabled` | off | `local_sentientia_content_market` | Enable the Udemy Business content provider adapter. | UAT build |
| `sentientia.legacy_logs.report.enabled` | off | `local_sentientia_core` | Imported admin log report (ADR-032, legacy_logs). | UAT build |
| `sentientia.courses.advanced_actions.enabled` | off | `local_sentientia_courses` | Manage Courses row actions "Open course" and "Advanced settings". | post-UAT commit |
| `sentientia.emails.imported_body_detail.enabled` | off | `local_sentientia_emails` | Adds a View link to imported BizLMS email rows in the notification log that opens the message body. | UAT build |
| `sentientia.emails.imported_history.enabled` | off | `local_sentientia_emails` | Shows the email history imported from BizLMS (ADR-032) in the notification log on the Notification Management page. | UAT build |
| `sentientia.emails.send_course_enrolment.enabled` | off | `local_sentientia_emails` | Sends the learner an e-mail when they are enrolled in a course (the BizLMS course_enrol e-mail), with the course, who enrolled them and a link. | UAT build |
| `sentientia.emails.send_learning_path_enrolment.enabled` | off | `local_sentientia_emails` | Sends the learner an e-mail when they are enrolled in a learning path (the BizLMS learningplan_enrol e-mail), with the path, its courses and its closing date. | UAT build |
| `sentientia.emails.send_manager_completion_copy.enabled` | off | `local_sentientia_emails` | When a learner completes a course, sends their supervisor a copy (the BizLMS course_complete e-mail to a manager), naming the learner and the course. | UAT build |
| `sentientia.evaluation.learner_history` | off | `local_sentientia_evaluation` | A learner's own evaluation history (2026-09-30, ADR-032). | UAT build |
| `sentientia.evaluation.response_drilldown` | off | `local_sentientia_evaluation` | The individual responses pages (2026-10-07, EV-06). | UAT build |
| `sentientia.hrms.reconcile.enabled` | off | `local_sentientia_integrations` | KeKa HRMS scheduled reconciliation pull (task\keka_reconcile → keka_client:: sync_employees). | UAT build |
| `sentientia.hrms.webhook.enabled` | off | `local_sentientia_integrations` | KeKa HRMS inbound webhook (JML events at /local/sentientia_integrations/webhook.php). | UAT build |
| `sentientia.leaderboards.enabled` | off | `local_sentientia_leaderboard` | Sentientia LMS real-time leaderboards. Master flag. | UAT build |
| `sentientia.leaderboards.notifications.enabled` | off | `local_sentientia_leaderboard` | Rank-change notifications via Moodle messaging (Phase L.1). | UAT build |
| `sentientia.leaderboards.optout.enabled` | ON | `local_sentientia_leaderboard` | Surface the "Hide me from public leaderboards" toggle on /user/preferences.php. | UAT build |
| `sentientia.leaderboards.realtime.enabled` | ON | `local_sentientia_leaderboard` | Server-Sent Events for leaderboard updates. | UAT build |
| `sentientia.leaderboards.type.completion` | off | `local_sentientia_leaderboard` | Course completion leaderboard type — fastest learners to N% completion of a course. | UAT build |
| `sentientia.leaderboards.type.quiz` | off | `local_sentientia_leaderboard` | Quiz leaderboard type — top scorers on a single mod_quiz instance. | UAT build |
| `sentientia.leaderboards.type.skill` | off | `local_sentientia_leaderboard` | Skill points leaderboard type — most skill points earned in a date range (reuses local_airpay_user_skill_hist). | UAT build |
| `sentientia.learningpath.adaptive.enabled` | off | `local_sentientia_learningpath` | Adaptive Learning Journeys. When ON, learning paths pivot on learner quiz scores, completion velocity, and skills-gap signals — automatically branching (skip mastered content), accelera... | UAT build |
| `sentientia.learningpath.learner_paths.enabled` | off | `local_sentientia_learningpath` | Learner "My learning paths" page (/local/sentientia_learningpath/mypaths.php). | UAT build |
| `sentientia.lifecycle.autoenrol.enabled` | off | `local_sentientia_lifecycle` | Joiner auto-enrolment into mandatory courses. | UAT build |
| `live.allow_anonymous` | off | `local_sentientia_live` | Allow audience members to join without logging in. | UAT build |
| `live.enabled` | off | `local_sentientia_live` | Sentientia LMS Live engagement (Mentimeter clone). | UAT build |
| `live.questiontype.multichoice` | off | `local_sentientia_live` | Multiple-choice slide type (bar chart of audience selections). | UAT build |
| `live.questiontype.openended` | off | `local_sentientia_live` | Open-ended slide type (scrolling list of raw text responses). | UAT build |
| `live.questiontype.quiz` | off | `local_sentientia_live` | Quiz slide type (right/wrong answer + live leaderboard). | UAT build |
| `live.questiontype.ranking` | off | `local_sentientia_live` | Ranking slide type (drag-to-order a list of N items). | UAT build |
| `live.questiontype.rating` | off | `local_sentientia_live` | Rating-scale slide type (1-5 or 0-10 NPS-style). | UAT build |
| `live.questiontype.wordcloud` | off | `local_sentientia_live` | Word cloud slide type (free text aggregated into a tag cloud). | UAT build |
| `live.realtime.enabled` | ON | `local_sentientia_live` | Server-Sent Events realtime push. When ON, audience and trainer screens use EventSource(stream.php) for instant updates. | UAT build |
| `sentientia_m365_enabled` | off | `local_sentientia_m365` | Sentientia LMS Microsoft 365 Knowledge Automation (Workstream C). | UAT build |
| `sentientia.notifications.digest_deadlines.enabled` | off | `local_sentientia_notifications` | Adds "Due in Nd: course name" lines (at most two) to the weekday daily learning digest, for compliance courses (local_sentientia_compliance_report) the learner has not completed, is activ... | UAT build |
| `sentientia.notifications.smart_rules.enabled` | off | `local_sentientia_notifications` | Lets three notification rules send: course_not_started ("Course not started"), streak_broken ("Streak at risk") and new_course ("New course available"). | UAT build |
| `sentientia.certificate.tenant_scope.enabled` | off | `local_sentientia_pages` | Tenant-scoped certificate template browser. | UAT build |
| `ai.assistant.enabled` | ON | `local_sentientia_platform` | Master switch for the AI chat assistant drawer. | UAT build |
| `ai.recommendations.enabled` | off | `local_sentientia_platform` | Personalised course recommendations on catalogue + dashboard. | UAT build |
| `ai.sentientia.enabled` | off | `local_sentientia_platform` | SOP→SCORM authoring pipeline (Phase B1). | UAT build |
| `commerce.crossTenantRequest.enabled` | ON | `local_sentientia_platform` | Sprint D pull/request workflow. When off, the "Browse Airpay Library" sidebar link is hidden, the /browse_airpay.php page returns 403, and pending requests stay in their current state. | UAT build |
| `commerce.crossTenantShare.enabled` | ON | `local_sentientia_platform` | Sprint C push-share UI. When off, the "Share" button on the course management table is hidden and the share_course web service returns "feature disabled". | UAT build |
| `commerce.publicMarketplace.enabled` | off | `local_sentientia_platform` | Open marketplace at /local/airpay_marketplace/public.php accessible without tenant authentication (Phase F). | UAT build |
| `engagement.gamification.confetti` | ON | `local_sentientia_platform` | Celebration confetti animation when a learner completes a course. | UAT build |
| `engagement.gamification.enabled` | ON | `local_sentientia_platform` | Master switch for points, badges, streaks. | UAT build |
| `engagement.sms.enabled` | off | `local_sentientia_platform` | SMS fallback notification channel via Twilio (Phase Α1). | UAT build |
| `engagement.whatsapp.enabled` | off | `local_sentientia_platform` | WhatsApp Business API notification channel (Phase Α1). | UAT build |
| `engagement.whatsapp.overdue` | off | `local_sentientia_platform` | WhatsApp overdue escalations to managers. | UAT build |
| `engagement.whatsapp.reminders` | off | `local_sentientia_platform` | WhatsApp deadline reminders to learners. | UAT build |
| `identity.sso.enabled` | off | `local_sentientia_platform` | Master switch for SSO providers (Okta + Azure AD + Google). | UAT build |
| `sentientia.customer_level_flags.enabled` | off | `local_sentientia_platform` | Sentientia LMS customer-level feature-flag layer. | UAT build |
| `ux.commandPalette.enabled` | ON | `local_sentientia_platform` | Cmd+K command palette for power-user navigation. | UAT build |
| `ux.darkMode.enabled` | off | `local_sentientia_platform` | Dark mode availability (super-admin switch). | UAT build |
| `ux.languageSwitcher.enabled` | off | `local_sentientia_platform` | Language switcher in the app-shell sidebar (above the dark-mode toggle). | UAT build |
| `sentientia.privacy.completion_gap.enabled` | off | `local_sentientia_privacy` | Privacy: erase course completion records Moodle core never finds. | UAT build |
| `sentientia.privacy.erasure_completeness.enabled` | off | `local_sentientia_privacy` | Privacy: recover contexts the stock data-request approval drops (contextlist truncation). | UAT build |
| `sentientia.assessment.candidates.enabled` | off | `local_sentientia_proctoring` | Hiring candidate accounts (ADR-038). Reserved: registered, no code reads it yet (Planned). | post-UAT commit |
| `sentientia.assessment.seb_preset.enabled` | off | `local_sentientia_proctoring` | Safe Exam Browser preset (ADR-038). Reserved: registered, no code reads it yet (Planned). | post-UAT commit |
| `sentientia.assessment.secure_mode.enabled` | off | `local_sentientia_proctoring` | Secure assessment mode: restricted attempt environment for quizzes (ADR-038). | post-UAT commit |
| `sentientia.proctoring.enabled` | off | `local_sentientia_proctoring` | Proctored quizzes, foundation (ADR-038). | post-UAT commit |
| `sentientia.proctoring.recording.enabled` | off | `local_sentientia_proctoring` | Webcam record-and-review (ADR-038). Reserved: no code reads it yet (Planned). | post-UAT commit |
| `sentientia.programs.history.enabled` | off | `local_sentientia_programs` | Imported program history on the admin pages (ADR-032): the "Completed on" column of the program roster and the program logo on the program page. | UAT build |
| `sentientia.programs.learner.enabled` | off | `local_sentientia_programs` | Learner "My programs" page (ADR-032). Lists the active programs the learner is enrolled in with level progress, completion dates and the program logo. | UAT build |
| `sentientia.pwa.enabled` | off | `local_sentientia_pwa` | Sentientia LMS Progressive Web App (PWA). | UAT build |
| `sentientia.pwa.install.enabled` | off | `local_sentientia_pwa` | Show the "Install Sentientia LMS" CTA on the dashboard when the browser fires beforeinstallprompt. | UAT build |
| `sentientia.pwa.push.enabled` | off | `local_sentientia_pwa` | Web Push notifications via the service worker. | UAT build |
| `sentientia.pwa.push.overdue` | off | `local_sentientia_pwa` | Push overdue escalations to managers when a learner misses a deadline. | UAT build |
| `sentientia.pwa.push.reminders` | off | `local_sentientia_pwa` | Push deadline reminders to learners. Fires alongside the existing email reminder from local_sentientia_courses\task\course_reminder. | UAT build |
| `sentientia.ratings.reactions` | off | `local_sentientia_ratings` | Like and dislike counts (ADR-032). When ON, the item reviews page shows how many learners liked and disliked an item, from the reactions imported from BizLMS (status 1 = like, 2 = dislike... | UAT build |
| `sentientia.ratings.reviews` | off | `local_sentientia_ratings` | Review list (ADR-032). When ON, the item reviews page (/local/sentientia_ratings/reviews.php) lists the written reviews learners left on a course, classroom, programme or learning path, i... | UAT build |
| `sentientia.ratings.widget` | off | `local_sentientia_ratings` | Interactive course star rating. When ON, the stars on a course page are clickable for a signed-in learner who may rate (local/sentientia_ratings:rate): a click saves the rating through th... | UAT build |
| `sentientia.recommendations.auto_cron` | off | `local_sentientia_recommendations` | Background refresh task. When OFF (default), recommendation batches are only generated when a manager clicks the "Generate now" button on the admin UI. | UAT build |
| `sentientia.recommendations.enabled` | off | `local_sentientia_recommendations` | Sentientia LMS AI Course Recommendations. | UAT build |
| `sentientia.recommendations.live_api` | off | `local_sentientia_recommendations` | Anthropic live API gate. When OFF, every batch uses anthropic_client::call_mock() — deterministic fixed-shape output, zero cost. | UAT build |
| `sentientia.recompletion.evidence_view` | off | `local_sentientia_recompletion` | Recompletion evidence view (ADR-032, 2026-09-30). | UAT build |
| `sentientia.recompletion.run_rules` | off | `local_sentientia_recompletion` | Daily recompletion task (ADR-032, 2026-09-30). | UAT build |
| `sentientia.reports.login_days` | off | `local_sentientia_reports` | Imported login days column (ADR-032, users). | UAT build |
| `sentientia.reports.training_transcript` | off | `local_sentientia_reports` | Earlier training records report (ADR-032, users). | UAT build |
| `sentientia.request.catalog_button` | off | `local_sentientia_request` | Show "Request enrolment" on the course catalogue. | UAT build |
| `sentientia.request.imported_history` | off | `local_sentientia_request` | Show the requests imported from BizLMS (ADR-032) in My requests, Pending approvals and All requests, and count them in the approver nav badge. | UAT build |
| `sentientia.roles.org_assignments` | off | `local_sentientia_roles` | Role holders at organisation level (ADR-032 org_roles, 2026-09-30). | UAT build |
| `sentientia.dashboard.blocksdrawer.enabled` | off | `theme_sentientia` | Dashboard blocks in a right-hand drawer, as Boost shows them; when OFF the blocks are printed into a hidden container. | UAT build |
| `sentientia.dashboard.skillsrecs.enabled` | off | `local_sentientia_skills` | Skills-first dashboard recommendations (ADR-028 Phase 2.2). | UAT build |
| `sentientia.skills.heldskills.enabled` | off | `local_sentientia_skills` | Held skills on My Skills (ADR-032, BizLMS import). | UAT build |
| `sentientia.skillsai.enabled` | off | `local_sentientia_skillsai` | Sentientia LMS Skills Intelligence. Master switch. | UAT build |
| `sentientia.skillsai.gap_engine` | off | `local_sentientia_skillsai` | Skills-gap engine. When OFF, the gap UI hides and the gap-feed rebuild task no-ops. | UAT build |
| `sentientia.skillsai.impact_map` | off | `local_sentientia_skillsai` | Skill -> business-impact mapping surface. | UAT build |
| `sentientia.skillsai.live_api` | off | `local_sentientia_skillsai` | Anthropic live API gate. When OFF, every extraction uses anthropic_client::call_mock() — deterministic fixed-shape output, zero cost. | UAT build |
| `sentientia.talent.enabled` | off | `local_sentientia_talent` | Master switch for the Talent Mobility suite (career paths, succession planning, internal opportunity board). | UAT build |
| `sentientia.talent.opportunities` | off | `local_sentientia_talent` | Internal opportunity board visible to employees. | UAT build |
| `sentientia.translate.enabled` | off | `local_sentientia_translate` | Sentientia LMS AI Content Translation. Master switch. | UAT build |
| `sentientia.translate.live_api` | off | `local_sentientia_translate` | Anthropic live API gate. When OFF, every translate request uses anthropic_client::call_mock() — deterministic marked output, zero cost. | UAT build |
| `sentientia.users.external_trainer.enabled` | off | `local_sentientia_users` | External trainer accounts (ADR-036). When OFF (default) the page /local/sentientia_users/external_trainers.php refuses everyone, the Manage Users page shows no "External trainers" button,... | working tree |
| `sentientia.users.imported_sync_history` | off | `local_sentientia_users` | Imported HRMS sync history (ADR-032, users). | UAT build |
| `sentientia.users.legacy_transcript` | off | `local_sentientia_users` | Earlier training records (ADR-032, users). | UAT build |
| `sentientia.users.position_labels` | off | `local_sentientia_users` | Position and domain labels (ADR-032, users). | UAT build |
| `sentientia_whatsapp_content_notifications` | off | `local_sentientia_whatsapp` | WhatsApp content-event notifications (Stream F). | UAT build |
| `sentientia.xapi.cmi5_enabled` | off | `local_sentientia_xapi` | Enable cmi5 Assignable Unit session tracking. | UAT build |
| `sentientia.xapi.emit_login` | off | `local_sentientia_xapi` | Emit an xAPI "experienced" statement when a user logs in. | UAT build |
| `sentientia.xapi.emit_module_view` | off | `local_sentientia_xapi` | Emit an xAPI "experienced" statement on every course module page view. | UAT build |
| `sentientia.xapi.enabled` | off | `local_sentientia_xapi` | Master switch for the Sentientia xAPI / LRS subsystem. | UAT build |
| `sentientia.xapi.lrs_endpoint_enabled` | off | `local_sentientia_xapi` | Enable the external LRS endpoint /local/sentientia_xapi/lrs/statements.php for inbound xAPI statements from SCORM / cmi5 / external clients. | UAT build |

## 4. Roles and archetypes

### 4.1 Roles our plugins create

Only two roles are created by repository code. Both are archetype-less (core's teacher and manager defaults never
reach them), assignable at system context only, and hold an exact allow-list.

| Role (shortname) | Created by | Purpose | Capabilities held | Status | Source |
|---|---|---|---|---|---|
| Sentientia Author (`sentientiaauthor`) | `local_sentientia_authoring\author_role::ensure()`, called from the authoring and Skills AI install hooks and upgrade back-fill steps | Content author or subject-matter expert who uses the AI authoring tools without broader teacher or manager rights | 7: `local/sentientia_authoring:generate`, `:review`, `:managetemplates`; `local/sentientia_skillsai:extract`, `:review`; `local/sentientia_aiquiz:generate`, `:review` | Available (UAT): author-role fix deployed and visually passed on UAT (September 2026 record in `PROJECT-STATE.md`) | `local/sentientia_authoring/classes/author_role.php` |
| Sentientia External Trainer (`sentientia_externaltrainer`) | `local_sentientia_users\external_trainer\role::ensure()` (upgrade step 2026112000) | Time-limited account for a non-employee who delivers classroom training and marks attendance | 2: `local/sentientia_classroom:view`, `:attendance`. Nothing to manage, create, update, delete, and no user, course or report capability | Built (behind a feature flag, off by default): `sentientia.users.external_trainer.enabled`; verified on development, uncommitted working tree | `local/sentientia_users/classes/external_trainer/role.php`, ADR-036 (external-trainer decision) |

Course creation for authors is not part of the Author role. It is provided per deployment by a category-level
`coursecreator` assignment (`moodle/course:create`), as stated in the `author_role.php` header.

### 4.2 Roles that our plugins use but do not create

These roles come from Moodle core or from the BizLMS data (the legacy site's roles, carried into Sentientia by the
production-backup import, ADR-032). Install hooks look them up by shortname and skip them when absent (a fresh
Moodle has none of the BizLMS roles).

| Role shortname | Origin | Archetype | How Sentientia uses it | Source |
|---|---|---|---|---|
| `administrator` | BizLMS (tenant org-admin role; its numeric id differs between copies: 9 on UAT and the Airpay data, 11 on another copy, hence lookup by shortname) | `manager` | The **tenant administrator** role, assigned at the organisation's course category (13 of 18 assignments are at category level in the development data). Install hooks grant it cart, request, recompletion, proctoring and external-trainer capabilities explicitly. Looked up by shortname, never by id, since 17 Nov 2026 | `local_sentientia_compliance_report\permission::COMPLIANCE_ROLE_SHORTNAME`; install hooks in `sentientia_cart`, `_request`, `_recompletion`, `_proctoring`; `sentientia_users/db/upgrade.php` step 2026112000 |
| `employee` | BizLMS | `student` | The **learner** role (assigned in course context to every enrolled employee; 12 system-level assignments on the development data). Install hooks grant cart, request and proctoring-attempt capabilities | install hooks above |
| `trainer` | BizLMS | `teacher` | Classroom and course trainer. Holds `:manage`, `:view` and `:attendance` on classrooms, live-session `:create`/`:run`, authoring and quiz-AI generation and review, leaderboard board management. `local/sentientia_users:edit` is PROHIBITed for it at system context | development database role capabilities |
| `complianceofficer` | Present on UAT per the 19 Nov 2026 deploy record; **not created by repository code** (origin to be confirmed by the platform engineer) | not recorded | Holds `moodle/site:viewreports` and `local/sentientia_compliance_report:export`, so it sees the tenant compliance matrix | `PROJECT-STATE.md` entry "Walk fixes A+B+C deployed to UAT", 19 Nov 2026 |
| `manager`, `editingteacher`, `teacher`, `coursecreator`, `user`, `guest`, `frontpage` | Moodle core | same as name | Archetype defaults in each plugin's `db/access.php` apply to these. `manager` is the main default carrier of administrative capabilities | `moodle-enhancement/*/*/db/access.php` |

Development database role table (test import, 12 roles; 5.1.3+ runtime): core roles `manager`, `coursecreator`,
`editingteacher`, `teacher`, `guest`, `user`, `frontpage`; BizLMS roles `employee`, `administrator`, `trainer`;
Sentientia roles `sentientiaauthor`, `sentientia_externaltrainer`. Role-assignment counts on that database:
`employee` 30,087 (30,075 at course level, 12 at system level), `administrator` 18 (5 system, 13 category), `trainer`
4, `sentientiaauthor` 2, `editingteacher` 2, `sentientia_externaltrainer` 1. These are test-import counts, not customer
figures.

### 4.3 Users who are not role-based: the supervisor relationship

A **line manager** needs no Moodle role. The relationship is the BizLMS field `open_supervisorid` on the user (read
through the org seam, ADR-020; `local_sentientia_core\org::is_manager()`).

| Surface | Rule | Source |
|---|---|---|
| Manager dashboard, approvals, allocations | `team_manager::can_manage()`: site administrator, or holder of `local/sentientia_manager:view` (manager and editingteacher archetypes), or anyone with at least one direct report | `local/sentientia_manager/classes/team_manager.php` |
| View one team member | Self, the member's supervisor, the supervisor's supervisor (skip-level), or a same-tenant holder of `local/sentientia_users:view`; cross-tenant only for cross-tenant principals (ADR-031) | `team_manager::can_view_member()` |
| Enrolment-request approval route | The requester's live direct supervisor, else the course owner or default approver from settings | `local/sentientia_request/classes/approver_routing.php` |
| Compliance report | Level `team`: the line manager's reporting tree (direct reports and everyone below), inside their tenant | `local/sentientia_compliance_report/classes/viewer_scope.php` |

### 4.4 User types (a separate axis from roles)

User type (ADR-017) is a permanent attribute of an account, stored in `local_sentientia_user_type`; accounts never
change type. Roles and user types compose at the call site.

| User type | Meaning | Status | Source |
|---|---|---|---|
| `employee` | Internal staff of a customer | Available (UAT) | `local/sentientia_platform/classes/user_type/employee_provider.php` |
| `consumer` | Public storefront buyer | Available (UAT) | `consumer_provider.php` |
| `partner_employee` | Staff of a B2B partner | Available (UAT) | `partner_employee_provider.php` |
| `operator` | Platform operator (site administrators) | Available (UAT) | `operator_provider.php` |
| `external_trainer` | Non-employee trainer, account expires 30 days after last session (ADR-036 (external-trainer decision)) | Built (behind a feature flag, off by default). The provider class exists, but `user_type_factory::instantiate()` and `all_types()` list only the four types above; until the factory lists it, no user-type row is written for these accounts (stated in `external_trainer/manager.php`) | `local/sentientia_users/classes/user_type/external_trainer_provider.php` |
| `candidate` | Hiring candidate (ADR-038) | Planned (flag `sentientia.assessment.candidates.enabled` reserved; no code) | ADR-038 |

## 5. Capabilities and personas

### 5.1 Capability inventory

| Measure | Value | Source |
|---|---|---|
| Capabilities declared | 181 (local 164, block 13, enrol 4) | `db/access.php` in 48 plugins |
| Default-granted to `manager` archetype | 128 | same |
| Default-granted to `editingteacher` | 51 | same |
| Default-granted to `teacher` | 21 | same |
| Default-granted to `student` | 21 | same |
| Default-granted to `user` (every logged-in account) | 28 | same |
| No archetype default | 47 | same |
| Registered capabilities on the development database | 181 (matches the code) | read-only count |
| Capabilities with a system-context ALLOW grant on at least one role (development database) | 142 | read-only count |
| Capabilities held by no role, i.e. site administrator only | 39 (listed in section 5.4) | read-only count |

A capability may list several archetypes, so the archetype counts add up to more than 181. Appendix A lists every
capability with its type, context, archetype defaults and one-line meaning.

Principle (ADR-031, decision 3): a plugin capability says **what** a user may do, never **where**. Tenant scope always
applies unless the caller is cross-tenant (section 6).

### 5.2 Persona matrix

"Mechanism" shows how the persona is identified. Capability lists are the Sentientia capabilities held at system
context on the development database (test import; the same defaults apply on UAT because they come from the same
install hooks and upgrade steps, but UAT role edits made by hand are **to be confirmed**).

| Persona | Mechanism | Count of Sentientia capabilities | What the persona can do (summary) | Tenant scope | Source |
|---|---|---|---|---|---|
| **Learner** (Airpay, Public or ZEEA tenant) | Role `employee` (student archetype) plus Authenticated user (`user` archetype) | 20 (`employee`) + 27 (`user`), overlapping | Browse and purchase courses (`cart:view`, `cart:purchase`), request enrolment (`request:request`), take quizzes (`proctoring:attempt`, behind flag), respond to evaluations, rate courses, view leaderboards and challenges, self-rate skills, view career paths and internal opportunities, use the AI assistant (`assistant:useagent`, `:recommend`, `:enrol`, `:bookilt`), join live sessions (`live:join`, `:respond`), subscribe to push and calendar feeds, view own privacy data | Own account only; sees own tenant's catalogue and shared content | development database `role_capabilities`; `db/access.php` |
| **Line manager** | Supervisor relationship (`open_supervisorid`), or manager or editingteacher archetype holding `sentientia_manager:view` | 3 plugin capabilities (`sentientia_manager:view`, `:approve`, `:allocate`) via archetype; none required when the user has direct reports | Team dashboard, approve or reject enrolment requests routed to them, allocate training to direct reports, team performance view, compliance report for their reporting tree | Direct and indirect reports inside their tenant (`viewer_scope` level `team`) | `team_manager.php`, `viewer_scope.php`, `approver_routing.php` |
| **Trainer (internal)** | Role `trainer` (teacher archetype) | 20 | Manage and run classroom sessions, mark attendance, run live sessions (`live:create`, `live:run`), generate and review AI content and quiz drafts (`authoring:*`, `aiquiz:*`, `skillsai:extract/review`), manage leaderboards, rate, view recommendations | Their classrooms only when `sentientia.classroom.trainer_own_scope.enabled` is ON (ADR-036 (own-classroom decision)); tenant guard always applies | development database; ADR-036 |
| **Trainer (external)** | Role `sentientia_externaltrainer` | 2 (`classroom:view`, `:attendance`) | View assigned classroom rosters (names only) and mark attendance. Nothing else | Own sessions always; account in the inviter's tenant; expires | `external_trainer/role.php`; ADR-036 (external-trainer decision) |
| **Course author** | Role `sentientiaauthor` (system context) plus a category-level `coursecreator` assignment for course creation | 7 | Generate and review AI-drafted courses, quizzes and skills extractions; manage authoring templates. No user, course-management or tenant-admin capability | Authoring is gated at system context; tenant filter applies to the content it publishes | `author_role.php` |
| **L&D administrator / tenant administrator** | Role `administrator` (manager archetype) at the organisation's category or system context | 133 (of 181) | Users (create, edit, bulk status change, export, invite external trainers), courses (manage, enrol, update, visibility, request), classrooms, programs, learning paths, exams, evaluations, e-mail templates and rules, notifications, cart (all orders, refunds, prices), requests (approve, view all, override route), skills taxonomy and mapping, analytics and reports with export, proctoring (manage, bypass, review), recompletion rules, content-market providers, API (read, write, manage, LTI), talent mobility, org structure, role audit and export | **Own tenant only** (ADR-031). Not cross-tenant, including Airpay's own tenant admins | development database count of role capabilities; ADR-031 |
| **Compliance officer** | Role `complianceofficer` on UAT (not created by repository code), or any holder of `local/sentientia_compliance_report:export` | 1 plugin capability (`compliance_report:export`) plus core `moodle/site:viewreports` | View and export the tenant compliance matrix (personal data: name, designation, department, status) | Level `tenant`: own tenant only (`viewer_scope`) | `viewer_scope.php`, `permission.php`; deploy record 19 Nov 2026 |
| **Site administrator** | Moodle site admin (`$CFG->siteadmins`; 4 accounts on the development database) | All, including the 39 site-administrator-only capabilities | Everything: Switchboard, tenant registry, privacy configuration, AI gateway and spend ledger, SCIM and webhook management, role management, hard deletes, cross-tenant sharing approval | **Cross-tenant** (`tenant::is_cross_tenant()` true) | `tenant.php`; section 5.4 |
| **Platform L&D (cross-tenant staff)** | Holder of `local/sentientia_platform:crosstenant`, granted deliberately to a named role | 1 (the capability itself) | Same reach as a site administrator across tenants, but only for the functions their other capabilities allow | Cross-tenant | ADR-031 decision 1. **No role holds it on the development database**; creating and assigning the role is an open owner action (section 7, R3) |

Tenant admins hold manager-archetype capabilities at system or category context. On a fresh install of the plugins the
`administrator` and `employee` roles do not exist until the BizLMS data import creates them, so the install hooks skip
the grants and the upgrade back-fill steps provide them later (for example `sentientia_cart` step 2026093001).

### 5.3 Capability differences between the `manager` archetype and the `administrator` role

On the development database the `administrator` role (manager archetype) differs from the stock `manager` role by
explicit install-hook grants and one ADR-031 removal. Source: development database `role_capabilities`.

| Capability | `manager` | `administrator` | Why |
|---|---|---|---|
| `local/sentientia_cart:view`, `:purchase`, `:viewallorders`, `:refund`, `:manageprices` | no | yes | Granted by `sentientia_cart` install hook (BizLMS custom role) |
| `local/sentientia_request:request`, `:overrideroute` | no | yes | `sentientia_request` install hook |
| `local/sentientia_recompletion:manage` | no | yes | `sentientia_recompletion` install hook, tenant-scoped in code (ADR-031) |
| `local/sentientia_proctoring:attempt`, `:manage`, `:bypass` | no | yes | `sentientia_proctoring` install hook |
| `local/sentientia_users:inviteexternaltrainer` | no | yes | Upgrade step 2026112000: no archetype by design |
| `local/sentientia_org:manage_multiorganizations`, `:manage_owndepartments` | yes | no | Not granted to the tenant admin role |
| `local/sentientia_courses:create` | yes | no | Not granted to the tenant admin role |

### 5.4 Capabilities no role holds (site administrator only), development database

39 capabilities are registered but granted to no role. Their `db/access.php` entries have an empty archetype list, so
only a site administrator (who bypasses capability checks) can use them until a role is given them deliberately.

| Plugin | Capabilities |
|---|---|
| `local_sentientia_ai` | `:manage`, `:viewledger` |
| `local_sentientia_aiquiz`, `_authoring`, `_live`, `_recommendations`, `_skillsai`, `_translate` | `:manage_all` (each) |
| `local_sentientia_analytics` | `:viewallorgs` |
| `local_sentientia_api` | `:scim_manage`, `:webhooks_manage` |
| `local_sentientia_cart` | `:manageinvoices` |
| `local_sentientia_challenge` | `:viewall` |
| `local_sentientia_classroom`, `_learningpath`, `_programs`, `_courses`, `_users` | `:delete` (each) |
| `local_sentientia_core` | `:managetenants`, `:viewadminlog` |
| `local_sentientia_courses` | `:approve_request`, `:share_to_tenant` |
| `local_sentientia_emails` | `:manage_settings` |
| `local_sentientia_leaderboard` | `:promoteboard`, `:viewall` |
| `local_sentientia_m365` | `:use` |
| `local_sentientia_notifications` | `:manage` |
| `local_sentientia_platform` | `:crosstenant` |
| `local_sentientia_privacy` | `:manage` |
| `local_sentientia_pwa` | `:manage` |
| `local_sentientia_recompletion` | `:reset` |
| `local_sentientia_roles` | `:assign`, `:manage` |
| `local_sentientia_skills` | `:manage` |
| `local_sentientia_xapi` | `:deletestatements`, `:managelrs` |
| `block_sentientia_leaderboard`, `block_sentientia_recommendations` | `:myaddinstance` |
| `enrol_sentientiasub` | `:unenrolself` |

## 6. Tenant model and cross-tenant authority

### 6.1 Tenants and customers

| Concept | Definition | Source |
|---|---|---|
| Customer | A purchaser of Sentientia LMS. Customer 1 is Airpay (customer zero). Table `local_sentientia_customer` | `local/sentientia_core/db/install.xml`; `customer::AIRPAY = 1` |
| Tenant | A top-level organisation tree inside a customer. Table `local_sentientia_tenant` (`rootid`, `customerid`, `name`, `status`) | same |
| Tenant roots in the validated list | **1** Airpay (internal employees), **77** Public (consumer storefront), **177** ZEEA (Tanzania) | `tenant::VALID_TENANTS`; `tenant_registry::LEGACY_ROOTS` |
| Tenant identity of a user | The user's `open_path` (BizLMS column), a slash-separated path of organisation ids. The first segment is the tenant root: `/1/2/3` is tenant 1, `/77` is 77, `/177/5` is 177. `open_costcenterid` is not used (the column does not exist on production) | `tenant::root_for_user()`; root `CLAUDE.md` |
| Registry mode | Legacy ON (default): validation against the hard-coded list and every tenant maps to customer 1. Legacy OFF: validation and `customer_of(root)` read `local_sentientia_tenant` (ADR-021) | `tenant_registry.php` |
| Organisation tree | Departments below the root, tables `local_sentientia_org_unit` and `local_sentientia_org_member` (ADR-020) | `local/sentientia_core/db/install.xml` |
| Customer branding | `customer::branding()` returns name, colours, icons, start URL per customer from `local_sentientia_customer_brand` (ADR-008); default is Airpay Academy | `local/sentientia_platform/classes/customer.php` |

Development database (test import, not production): users by tenant root (non-deleted accounts) are 2,208 under root 1,
685 under root 77, 8 under root 177, and 6 with no `open_path` (system and service accounts). A second customer record
(id 2) with tenant root 500 and one user also exists on that database for multi-customer testing; it is not part of
the repository seed and is not Airpay data. Registry mode on that database is OFF (table-driven) and the customer
layer of the flag resolver is ON. UAT values are **to be confirmed**.

### 6.2 Scoping helpers

All tenant filtering goes through `local_sentientia_platform\tenant` (`local/sentientia_platform/classes/tenant.php`).

| Helper | Behaviour |
|---|---|
| `is_cross_tenant(?int $userid)` | True for a site administrator or a holder of `local/sentientia_platform:crosstenant`. The single authority decision (ADR-031) |
| `cross_tenant_userids()` | Bulk form: all site administrators plus all `:crosstenant` holders, used to exclude them from tenant-scoped people lists and SCIM |
| `scope_path(?stdClass $user)` | `''` cross-tenant; `'/N'` the user's tenant; **`null`** a non-cross-tenant user with no resolvable tenant. Callers treat `null` as "nothing" (fail closed) |
| `viewer_can_access(int $resourcetenant, ?int $viewerid)` and `require_access()` | Cross-tenant callers pass; others must have root > 0 and equal to the resource tenant. A viewer without a tenant is refused everything, including tenant-0 rows |
| `require_path_access(string $resourcepath, ...)` | Same check for an organisation path |
| `require_same_tenant_user(int $targetuserid, ?int $actorid)` | Every write that names another user must target the actor's tenant; throws `error_outoftenant` |
| `sql_filter()` and `path_filter()` | Return `1=1` for cross-tenant callers, `1=0` for a caller with no tenant, otherwise an exact-or-prefix match on `open_path` |
| `path_descendant_filter(string $path, $alias, $column, $tag, $allownull)` | Matches one path exactly or its `/`-bounded descendants for an arbitrary path: `(col = :exact OR col LIKE :prefix)` with prefix `path/%`. `/1` matches `/1` and `/1/2` but never `/10` or `/177`. Replaces hand-written `LIKE '/1%'` filters, which over-counted sibling ids and (in the `%/id/%` form) missed leaf nodes. Rule: bound every tenant path with this helper |
| Guards on the rule | Pre-commit check 18 and a CI gate block unbounded tenant-path prefixes; `@group tenant_isolation` PHPUnit tests are the blocking CI group (ADR-031 decision 8) |

### 6.3 ADR-031: one cross-tenant authority, fail closed

Source: `docs/adr/ADR-031-cross-tenant-authority.md` (Accepted 25 Sep 2026; evidence: cross-tenant authority sweep,
73 hits, 70 confirmed).

| # | Decision |
|---|---|
| Problem | About 30 plugins granted their `manage_all`, `viewall`, `manage` and `view` capabilities to the manager archetype and then treated holding the capability as licence to skip the tenant filter. Every tenant admin could read other tenants' personal data, write into other tenants, escalate roles, take over accounts and reset completions. Where a non-admin's `open_path` did not resolve, most engines read an empty tenant as "every tenant" |
| 1 | Cross-tenant means a site administrator or a holder of `local/sentientia_platform:crosstenant` (no archetype default, granted deliberately to a named role). Tenant admins, including Airpay's own, are confined to their tenant |
| 2 | One helper decides: `tenant::is_cross_tenant()`; `viewer_can_access`, `require_path_access`, `sql_filter`, `path_filter` route through it |
| 3 | A plugin capability says what, never where. Tenant scope always applies unless `is_cross_tenant()` |
| 4 | Fail closed: no resolvable tenant means nothing shown or done (`scope_path()` returns `null`; filters return `1=0`) |
| 5 | Writes check the target (user, course, classroom, program, challenge, template) with `require_same_tenant_user()`, `require_access()`, `require_path_access()` |
| 6 | Escalation closed: `roles:manage` and `roles:assign` limited to site administrators or cross-tenant callers; a scoped `roles:assign` may assign only a role the actor holds, to users in their tenant; `users:create` refuses rows matching an existing user in another tenant |
| 7 | Revoke, not just remove, archetype defaults: an upgrade step calls `unassign_capability()` because archetype changes never revoke existing grants |
| 8 | Every fix ships PHPUnit tests in `@group tenant_isolation` (blocking in CI): other tenants' rows refused, no-tenant callers get nothing, cross-tenant callers still work |

Follow-on records in `PROJECT-STATE.md`: role-id-9 export grant replaced by a shortname lookup (17 Nov 2026), compliance
viewer scope with three levels site, tenant, team (24 Sep and 18 Nov 2026), `moodle/site:viewreports` no longer admits
to the tenant compliance matrix (18 Nov 2026).

Compliance report scope levels (`local/sentientia_compliance_report/classes/viewer_scope.php`):

| Level | Who | Reach |
|---|---|---|
| `site` | Site administrator | Every tenant |
| `tenant` | Compliance admin (`local/courses:manage` where BizLMS capability exists, or role `administrator` at a category), or any holder of `local/sentientia_compliance_report:export` | Own tenant |
| `team` | Line manager (has direct reports) | Own reporting tree |
| none | Anyone else, or a non-admin whose tenant cannot be resolved | Nothing |

## 7. Risks and open questions

| # | Item | Owner role | Proposed resolution |
|---|---|---|---|
| R1 | UAT flag state is not recorded in the repository; this file reports development state only | Platform engineer | Export the Switchboard (all flags, all overrides) from UAT and attach to the next pack revision |
| R2 | Registry count at UAT build: 133 counted versus 134 in the 19 Nov deploy record | Platform engineer | Compare the UAT Switchboard export with the registry; correct whichever side is wrong |
| R3 | No role holds `local/sentientia_platform:crosstenant` on the development database; ADR-031 lists "choose and create that role" as an owner action. UAT state to be confirmed | Head of Learning & Development | Decide the platform L&D role name and assign it to named staff, or record that site administrators remain the only cross-tenant principals |
| R4 | `complianceofficer` role exists on UAT but no repository code creates it | Platform engineer | Document its origin and capabilities, or add a seeder with an explicit allow-list |
| R5 | `external_trainer` provider exists but the user-type factory lists four types; no user-type row is written for external trainers | Platform engineer | Add the type to `user_type_factory` in the same release that turns the flag ON |
| R6 | Tenant roots 1, 77, 177 are a hard-coded fallback list while the legacy registry setting is ON; a second customer needs registry mode OFF and a seeded `local_sentientia_tenant` table (`local/sentientia_core/cli/seed_tenants.php`) | Solution architect | Complete ADR-021 rollout before onboarding the second customer |
| R7 | Four plugins' flag registry files (analytics, api, content_market, talent: 15 flags) exist only in the canonical tree; the twin tree lacks them (the drift gate `tools/check-tree-drift.php` baseline) | Platform engineer | Re-sync the twin tree from the canonical tree before any deploy that reads the twin |
| R8 | Two flag keys use underscore form and sit in category "other" on the Switchboard | Platform engineer | Rename to dotted keys under ADR-002 rules with a migration of any override rows |
| R9 | 39 capabilities are held by no role; some (for example `local/sentientia_privacy:manage`, `local/sentientia_roles:manage`) are needed by operational roles in a customer deployment | Head of Learning & Development | Decide which operational roles (privacy officer, platform L&D) receive which of the 39, per customer |
| R10 | Proctoring flags B3 to B5 are registered but no code reads them | Product owner | Treat as Planned in all external material until the code lands |

## 8. Sources

| Source | Path |
|---|---|
| Flag resolver and API | `moodle-enhancement/local/sentientia_platform/classes/feature_flags.php`, `classes/local/feature_flags_state.php` |
| Flag registries | `moodle-enhancement/local/*/db/feature_flags.php`, `moodle-enhancement/enrol/sentientiasub/db/feature_flags.php` |
| Switchboard | `moodle-enhancement/local/sentientia_platform/admin/switchboard.php`, `settings.php` |
| Customer and tenant helpers | `moodle-enhancement/local/sentientia_platform/classes/customer.php`, `classes/tenant.php` |
| Tenant registry and core settings | `moodle-enhancement/local/sentientia_core/classes/tenant_registry.php`, `settings.php`, `db/install.xml` |
| Capabilities | `moodle-enhancement/{local,enrol,blocks}/*/db/access.php` |
| Role creation | `moodle-enhancement/local/sentientia_authoring/classes/author_role.php`, `local/sentientia_users/classes/external_trainer/role.php` |
| Role grants by install hook | `moodle-enhancement/local/sentientia_{cart,request,recompletion,proctoring}/db/install.php` |
| Line manager rules | `moodle-enhancement/local/sentientia_manager/classes/team_manager.php`, `local/sentientia_request/classes/approver_routing.php` |
| Compliance scope | `moodle-enhancement/local/sentientia_compliance_report/classes/viewer_scope.php`, `permission.php` |
| ADRs | `moodle-enhancement/docs/adr/ADR-002`, `ADR-008`, `ADR-017`, `ADR-019`, `ADR-020`, `ADR-021`, `ADR-031`, `ADR-036`, `ADR-038` |
| Deployment record | `moodle-enhancement/PROJECT-STATE.md` (entries of 3 Sep, 8 Sep, 17 Nov, 19 Nov 2026) |
| Git placement | commits `af6e65c0e` (UAT), `76e8ec572`, `4a78b8f82`, `4df7d4573`; working tree of 20 Nov 2026 |
| Counts of roles, assignments, overrides, users by tenant | Read-only SELECT and COUNT queries on the local development database (Moodle 5.1.3+ runtime, test import of a production backup), 20 Nov 2026; no personal data read |

## Appendix A. Every capability (181)

Type/context is Moodle `captype/contextlevel`. Archetype codes: M manager, ET editingteacher, T teacher, S student,
U user (every logged-in account), none = no default grant. Meaning is the capability's language string; blank
strings are shown as "(no description string)". Source of every row: the plugin's `db/access.php` and `lang/en`.

| Capability | Type/context | Archetype defaults | Meaning |
|---|---|---|---|
| `local/sentientia_ai:viewledger` | read/sys | none | View the AI spend ledger |
| `local/sentientia_ai:manage` | write/sys | none | Manage AI gateway runtime controls |
| `local/sentientia_aiquiz:generate` | write/sys | ET, T, M | Generate AI-drafted quiz questions |
| `local/sentientia_aiquiz:review` | write/sys | ET, T, M | Review AI-drafted quiz questions |
| `local/sentientia_aiquiz:manage_all` | write/sys | none | Manage AI quiz drafts across all owners |
| `local/sentientia_analytics:view` | read/sys | M | View the analytics dashboard |
| `local/sentientia_analytics:viewallorgs` | read/sys | none | View analytics for every organisation, not only your own |
| `local/sentientia_analytics:export` | read/sys | M | Download the analytics CSV export |
| `local/sentientia_api:read` | read/sys | M | Read the Sentientia public API (courses, enrolments, completions, skills) |
| `local/sentientia_api:write` | write/sys | M | Perform write operations via the Sentientia public API |
| `local/sentientia_api:manage` | write/sys | M | Manage Sentientia API and LTI registrations |
| `local/sentientia_api:lti` | read/sys | M | Act as an LTI 1.3 launch endpoint |
| `local/sentientia_api:webhooks_manage` | write/sys | none | Manage outbound webhook subscriptions and the delivery log |
| `local/sentientia_api:scim_manage` | write/sys | none | Manage SCIM 2.0 provisioning clients |
| `local/sentientia_assistant:useagent` | read/sys | U, S, ET, M | Use the agentic learning copilot |
| `local/sentientia_assistant:enrol` | write/sys | U, S, ET, M | Let the copilot self-enrol you into a course |
| `local/sentientia_assistant:bookilt` | write/sys | U, S, ET, M | Let the copilot book you onto an ILT session |
| `local/sentientia_assistant:recommend` | read/sys | U, S, ET, M | Let the copilot recommend gap-closing content |
| `local/sentientia_assistant:manageall` | read/sys | M | View the agentic copilot audit log across the tenant |
| `local/sentientia_authoring:generate` | write/sys | ET, T, M | Generate course drafts in the Authoring Studio |
| `local/sentientia_authoring:review` | write/sys | ET, T, M | Review, edit and finalise generated drafts |
| `local/sentientia_authoring:managetemplates` | write/sys | ET, T, M | Create and edit instructional-design templates |
| `local/sentientia_authoring:manage_all` | write/sys | none | Manage all drafts and templates across owners and tenants |
| `local/sentientia_calendar:subscribe` | read/user | U, ET, M | Manage own calendar subscription URL |
| `local/sentientia_calendar:manage_all` | write/sys | M | Manage any user's calendar subscription tokens |
| `local/sentientia_cart:view` | read/sys | U, M, ET, T, S | View own cart |
| `local/sentientia_cart:purchase` | write/sys | U, M, S | Add items to cart and purchase |
| `local/sentientia_cart:viewallorders` | read/sys | M | View all orders (admin) |
| `local/sentientia_cart:refund` | write/sys | none | Process refunds |
| `local/sentientia_cart:manageprices` | write/course | M, ET | Manage course pricing |
| `local/sentientia_cart:manageinvoices` | write/sys | none | Record e-invoice IRNs and see the invoices that wait for them |
| `local/sentientia_challenge:view` | read/sys | U, S, T, ET, M | View challenges and leaderboards |
| `local/sentientia_challenge:participate` | write/sys | U, S, T, ET, M | Join and leave challenges |
| `local/sentientia_challenge:manage` | write/sys | M | Create, edit, and delete challenges |
| `local/sentientia_challenge:viewall` | read/sys | none | View leaderboards across all tenants |
| `local/sentientia_classroom:manage` | write/sys | M | Manage classroom sessions |
| `local/sentientia_classroom:view` | read/sys | M, ET, T | View classroom sessions |
| `local/sentientia_classroom:attendance` | write/sys | M, ET, T | Manage attendance |
| `local/sentientia_classroom:create` | write/sys | M | Create classroom sessions |
| `local/sentientia_classroom:update` | write/sys | M | Edit classroom sessions |
| `local/sentientia_classroom:delete` | write/sys | none | Delete classroom sessions |
| `local/sentientia_compliance_report:export` | read/sys | M | Export the compliance report |
| `local/sentientia_content_market:view` | read/sys | U, S, T, M | Browse the Content Marketplace catalog |
| `local/sentientia_content_market:syncproviders` | write/sys | M | Trigger a provider catalog sync |
| `local/sentientia_content_market:manageproviders` | write/sys | M | Manage provider configuration and credentials |
| `local/sentientia_content_market:mapskills` | write/sys | M | Manually map catalog items to skills taxonomy |
| `local/sentientia_core:managetenants` | write/sys | none | Manage the Sentientia tenant registry |
| `local/sentientia_core:viewadminlog` | read/sys | none | View the imported admin log |
| `local/sentientia_courses:manage` | write/sys | M | Manage courses |
| `local/sentientia_courses:enrol` | write/sys | M | Enrol users into courses |
| `local/sentientia_courses:view` | read/sys | M, ET | View course management |
| `local/sentientia_courses:create` | write/sys | M | Create courses |
| `local/sentientia_courses:update` | write/sys | M | Edit courses |
| `local/sentientia_courses:delete` | write/sys | none | Delete courses |
| `local/sentientia_courses:visibility` | write/sys | M | Show or hide courses |
| `local/sentientia_courses:share_to_tenant` | write/sys | none | Share a course to other tenants |
| `local/sentientia_courses:request_course` | write/sys | M | Request a course be shared to my tenant |
| `local/sentientia_courses:approve_request` | write/sys | none | Approve / reject share-requests from other tenants |
| `local/sentientia_emails:preview` | read/sys | M | (no description string) |
| `local/sentientia_emails:manage` | write/sys | M | (no description string) |
| `local/sentientia_emails:manage_templates` | write/sys | M | (no description string) |
| `local/sentientia_emails:manage_rules` | write/sys | M | (no description string) |
| `local/sentientia_emails:view_logs` | read/sys | M | (no description string) |
| `local/sentientia_emails:manage_settings` | write/sys | none | (no description string) |
| `local/sentientia_evaluation:manage` | write/sys | M | Manage evaluation forms |
| `local/sentientia_evaluation:respond` | write/sys | S, M | Respond to evaluation forms |
| `local/sentientia_exams:manage` | write/sys | M | Manage online exams |
| `local/sentientia_exams:view` | read/sys | M, ET | View online exams |
| `local/sentientia_exams:enrol` | write/sys | M | Enrol users into exams (via parent quiz course) |
| `local/sentientia_leaderboard:view` | read/sys | U, S, T, ET, M | View leaderboards inside my tenant |
| `local/sentientia_leaderboard:manageboard` | write/sys | ET, T, M | Create, edit, and delete leaderboards inside my tenant |
| `local/sentientia_leaderboard:promoteboard` | write/sys | none | Promote a leaderboard to customer-wide visibility |
| `local/sentientia_leaderboard:viewall` | read/sys | none | View leaderboards across tenants (HR analytics) |
| `local/sentientia_learningpath:manage` | write/sys | M | Manage learning paths |
| `local/sentientia_learningpath:view` | read/sys | M | View learning paths |
| `local/sentientia_learningpath:enrol` | write/sys | M | Enrol users into learning paths |
| `local/sentientia_learningpath:create` | write/sys | M | Create learning paths |
| `local/sentientia_learningpath:update` | write/sys | M | Edit learning paths |
| `local/sentientia_learningpath:delete` | write/sys | none | Delete learning paths |
| `local/sentientia_live:create` | write/sys | T, ET, M | Create a new live session as the trainer |
| `local/sentientia_live:run` | write/sys | T, ET, M | Run (start/advance/end) a live session you created |
| `local/sentientia_live:join` | read/sys | U, ET, M | Join an existing live session by code |
| `local/sentientia_live:respond` | write/sys | U, ET, M | Submit a response to a live slide |
| `local/sentientia_live:manage_all` | write/sys | none | Admin: view and manage every live session across tenants |
| `local/sentientia_m365:use` | read/sys | none | Connect a Microsoft 365 account and read your own M365 data through Sentientia LMS |
| `local/sentientia_m365:admin` | write/sys | M | Administer the Sentientia Microsoft 365 integration (tenant config, revoke any user) |
| `local/sentientia_manager:view` | read/sys | M, ET | View team dashboard + requests + allocations |
| `local/sentientia_manager:approve` | write/sys | M, ET | Approve / reject enrolment requests |
| `local/sentientia_manager:allocate` | write/sys | M, ET | Assign courses to direct reports |
| `local/sentientia_notifications:view` | read/sys | M, ET | View notification rules |
| `local/sentientia_notifications:manage` | write/sys | none | Manage notification rules |
| `local/sentientia_notifications:viewlogs` | read/sys | M | (no description string) |
| `local/sentientia_org:manage_multiorganizations` | write/sys | none | Manage multiple organizations |
| `local/sentientia_org:view` | read/sys | M | View organizations |
| `local/sentientia_org:manage` | write/sys | none | Manage organizations |
| `local/sentientia_org:manage_ownorganization` | write/cat | none | Manage own organization |
| `local/sentientia_org:manage_owndepartments` | write/cat | none | Manage own departments |
| `local/sentientia_org:managetenant` | write/sys | M | (no description string) |
| `local/sentientia_platform:crosstenant` | write/sys | none | Act across all tenants (Sentientia platform) |
| `local/sentientia_privacy:manage` | write/sys | none | (no description string) |
| `local/sentientia_privacy:view` | read/sys | U | (no description string) |
| `local/sentientia_proctoring:attempt` | write/sys | U, S | Attempt a proctored quiz |
| `local/sentientia_proctoring:viewattempts` | read/sys | M, ET | View proctoring attempt details |
| `local/sentientia_proctoring:review` | write/sys | M | Review flagged proctoring sessions |
| `local/sentientia_proctoring:manage` | write/sys | none | Manage proctoring settings + reviewer assignments |
| `local/sentientia_proctoring:bypass` | write/sys | none | Bypass proctoring (emergency, audit-logged) |
| `local/sentientia_programs:view` | read/sys | M, ET | View certification programs |
| `local/sentientia_programs:manage` | write/sys | M | Manage certification programs |
| `local/sentientia_programs:enrol` | write/sys | M | Enrol into programs |
| `local/sentientia_programs:create` | write/sys | M | Create programs |
| `local/sentientia_programs:update` | write/sys | M | Edit programs |
| `local/sentientia_programs:delete` | write/sys | none | Delete programs |
| `local/sentientia_pwa:subscribe` | write/sys | U, ET, M | (no description string) |
| `local/sentientia_pwa:manage` | write/sys | none | (no description string) |
| `local/sentientia_ratings:rate` | write/sys | U, S, T, ET, M | Submit star ratings on courses, classrooms, programs and learning paths |
| `local/sentientia_recommendations:view` | read/sys | U, S, T, ET, M | See AI course recommendations on the dashboard |
| `local/sentientia_recommendations:generate` | write/sys | M | Generate AI course recommendations for a learner |
| `local/sentientia_recommendations:manage_all` | write/sys | none | Manage AI recommendation history across all learners |
| `local/sentientia_recompletion:view` | read/sys | M | View recompletion rules and history |
| `local/sentientia_recompletion:manage` | write/sys | none | Manage recompletion rules |
| `local/sentientia_recompletion:reset` | write/sys | none | Manually reset user completions |
| `local/sentientia_reports:view` | read/sys | M, ET | View saved reports |
| `local/sentientia_reports:manage` | write/sys | M | Manage saved reports |
| `local/sentientia_reports:export` | read/sys | M | Export saved reports to CSV |
| `local/sentientia_request:request` | write/sys | U, S | Request enrolment in restricted courses |
| `local/sentientia_request:approve` | write/sys | M, U | Approve / reject course requests |
| `local/sentientia_request:viewall` | read/sys | M | View all requests across tenant |
| `local/sentientia_request:overrideroute` | write/sys | none | Override the approval routing |
| `local/sentientia_roles:view` | read/sys | M | View role-management UI |
| `local/sentientia_roles:manage` | write/sys | none | Edit capability permissions on roles |
| `local/sentientia_roles:assign` | write/sys | none | Assign / unassign users to roles |
| `local/sentientia_roles:audit` | read/sys | M | View role-management audit log |
| `local/sentientia_roles:export` | read/sys | M | Export role + capability data to CSV |
| `local/sentientia_skills:view` | read/sys | M, ET, S | View skill matrix and gap analysis |
| `local/sentientia_skills:manage` | write/sys | none | Manage skill categories and definitions |
| `local/sentientia_skills:mapcourses` | write/sys | M | Map skills onto courses in their own tenant |
| `local/sentientia_skills:self_rate` | write/sys | U, S, ET, M | Self-attest a proficiency level for a skill (the user's own profile only) |
| `local/sentientia_skillsai:extract` | write/sys | ET, T, M | Extract skills with AI |
| `local/sentientia_skillsai:review` | write/sys | ET, T, M | Review and approve extracted skills |
| `local/sentientia_skillsai:manage_taxonomy` | write/sys | M | Curate the skills taxonomy and impact mappings |
| `local/sentientia_skillsai:viewgaps` | read/sys | M | View skills-gap feeds |
| `local/sentientia_skillsai:manage_all` | write/sys | none | Manage all extraction jobs across owners and tenants |
| `local/sentientia_talent:viewopportunities` | read/sys | U, S, T, ET, M | View the internal opportunity board |
| `local/sentientia_talent:registerinterest` | write/sys | U, S, M | Register interest in an internal opportunity |
| `local/sentientia_talent:viewcareerpath` | read/sys | U, S, M | View career paths for your role |
| `local/sentientia_talent:viewsuccession` | read/sys | M | View succession plans (HR / managers) |
| `local/sentientia_talent:managesuccession` | write/sys | M | Manage succession nominations (HR / managers) |
| `local/sentientia_talent:managecareerpaths` | write/sys | M | Define and edit career paths |
| `local/sentientia_talent:manageopportunities` | write/sys | M | Post and manage internal opportunities |
| `local/sentientia_talent:audit` | read/sys | M | View the talent audit log |
| `local/sentientia_translate:translate` | write/sys | M | Translate content with Anthropic Claude |
| `local/sentientia_translate:manage_brands` | write/sys | M | Manage per-customer brand-name overrides |
| `local/sentientia_translate:manage_all` | write/sys | none | Manage translation history across all owners |
| `local/sentientia_users:view` | read/sys | M, ET | View user profiles |
| `local/sentientia_users:create` | write/sys | M | (no description string) |
| `local/sentientia_users:edit` | write/sys | M | Edit users |
| `local/sentientia_users:delete` | write/sys | none | (no description string) |
| `local/sentientia_users:manage` | write/sys | M | (no description string) |
| `local/sentientia_users:bulkstatuschange` | write/sys | M | Bulk status change |
| `local/sentientia_users:export` | read/sys | M | (no description string) |
| `local/sentientia_users:inviteexternaltrainer` | write/sys | none | Invite and manage external trainers |
| `local/sentientia_xapi:viewstatements` | read/sys | M | (no description string) |
| `local/sentientia_xapi:deletestatements` | write/sys | none | (no description string) |
| `local/sentientia_xapi:managelrs` | write/sys | none | (no description string) |
| `enrol/sentientiasub:config` | write/course | M, ET | Configure Sentientia subscription enrolment instances |
| `enrol/sentientiasub:manage` | write/course | M, ET | Manage subscribed users |
| `enrol/sentientiasub:unenrol` | write/course | M | Unenrol subscribed users from the course |
| `enrol/sentientiasub:unenrolself` | write/course | none | Cancel own subscription enrolment |
| `block/sentientia_cert_health:addinstance` | write/block | ET, M | Add an Airpay Cert Health block |
| `block/sentientia_cert_health:myaddinstance` | write/sys | U | Add an Airpay Cert Health block to the My Moodle page |
| `block/sentientia_compliance:addinstance` | write/block | ET, M | Add a new Compliance Dashboard block |
| `block/sentientia_compliance:myaddinstance` | write/sys | M | Add a new Compliance Dashboard block to My Dashboard |
| `block/sentientia_cron_health:addinstance` | write/block | ET, M | Add an Airpay Cron Health block |
| `block/sentientia_cron_health:myaddinstance` | write/sys | U | Add an Airpay Cron Health block to the My Moodle page |
| `block/sentientia_leaderboard:addinstance` | write/block | ET, M | Add a Sentientia Leaderboard block |
| `block/sentientia_leaderboard:myaddinstance` | write/sys | U, S, T, ET, M | Add a Sentientia Leaderboard block to the dashboard |
| `block/sentientia_recommendations:addinstance` | write/block | ET, M | Add a new AI Recommendations block |
| `block/sentientia_recommendations:myaddinstance` | write/sys | U | Add a new AI Recommendations block to the Dashboard |
| `block/sentientia_trainer:addinstance` | write/block | ET, M | Add trainer dashboard block |
| `block/sentientia_trainer:myaddinstance` | write/sys | ET, M | Add trainer dashboard to my page |
| `block/sentientia_trainer:viewtrainerslist` | read/sys | ET, M | View trainers list |

## Corrections (fact-base cross-check, 20 Nov 2026)

Correction (20 Nov 2026, late): the registry is 142 keys in 43 files, not 141 in 42. `theme/sentientia/db/feature_flags.php` registers `sentientia.dashboard.blocksdrawer.enabled` (default OFF, in the UAT build), and `feature_flags::load_registry()` reads theme registries too. Default OFF is 129; `sentientia.*` is 115. Earlier text: Re-verified: 141 keys, 141 unique, 13 default ON, 42 registry files; 181 capabilities (164 local, 13 block, 4 enrol) in 48 `db/access.php` files; `tenant::VALID_TENANTS = [1, 77, 177]`; `feature_flags::SNAPSHOT_TTL = 30`. Other fact files that disagreed on the flag count (`11-plugins-b.md`, `60-eng-qa-infra.md`, `70-ui-surfaces.md`) were corrected to this file's figures.

- Wording: short decision references (ADR section codes and a working-folder name) replaced by ADR names, per master prompt rule 3.2 (5 places).
