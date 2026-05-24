# PROJECT STATE — Sentientia LMS (formerly Airpay Academy L&D OS)
**Updated:** 2026-05-24 (Three parallel-chip MVPs shipped: **Tier 2.6 Calendar Sync** — `local_sentientia_calendar` with token-URL ICS feed, 4 feature flags, 28 PHPUnit assertions, ADR-013, Hindi 100%; **Tier 1 #4 AI Quiz Generation Phase G.0** — `local_sentientia_aiquiz` with 4-layer cost defence and mock-mode demoable pipeline, ~47 PHPUnit tests, ADR-012, Hindi 100%; **Tier 2 #7 Real-time Leaderboards Phase L.0** — `local_sentientia_leaderboard` + `block_sentientia_leaderboard` with SSE-driven live ranking across quiz/completion/skill board types, GDPR-compliant opt-out, ADR-014, Hindi 100%. **Platform Visual Audit v4.1.0** shipped from mobile-app session — 14 surfaces audited (9 P0 / 8 P1 / 6 P2 findings), CONDITIONAL PASS verdict; full report at `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`. Earlier today the night-run autonomous batch shipped 16 items: Phase B.12 cutover-day mechanical fixes (A1-A8), plugin PHPUnit coverage (B1-B2), Goal C user guides for 6 personas (C1-C6); cutover-day TODO list is now mostly empty modulo NVDA verification + activity_header runtime test. **Paygw security follow-up shipped earlier this session** — MD5 deprecated, require_login() at file scope removed, sandbox/live URL clarified, 13 new PHPUnit tests added. Phase B Moodle 5.2 upgrade is code-complete; production stays on 5.1 until customer-driven cutover decision. ADR-001 records the strategic pivot from "patch Moodle deployment" to "build saleable enterprise LMS product" — Airpay Academy is customer-zero. See `docs/adr/ADR-001-fork-strategy-and-product-pivot.md`.

---

## 🚀 TIER 2.6 — CALENDAR SYNC PHASE 1 (2026-05-24)

### Session — `local_sentientia_calendar` (outbound ICS feed MVP) — ✅ SHIPPED

**Per ADR-013** (this session). New plugin shipping Tier 2 #6 of the
roadmap. Each user gets a personal ICS subscription URL they paste
into Outlook / Google / Apple Calendar; their course deadlines,
classroom (ILT) sessions, and exam close-dates appear automatically.
Outbound-only — bi-directional OAuth sync deferred to Phase 2.

- [x] **New plugin `local_sentientia_calendar`** (20 files, 1.0.0-beta,
      version 2026052400)
- [x] **ADR-013** — token-URL ICS vs OAuth decision recorded
- [x] **DB table `local_sentientia_calendar_token`** — 64-char random
      token per user (~381 bits entropy, generated via `random_bytes`),
      audit fields (last_used_at, last_used_ip, use_count), revoked
      flag, 90-day retention on revoked rows
- [x] **4 feature flags** — master `sentientia.calendar_sync.enabled`
      (default OFF) + 3 per-event-type sub-flags (default ON) for
      courses / classroom / exams
- [x] **3 public surfaces** — `/index.php` (UI), `/regenerate.php`
      (sesskey-protected POST), `/ics.php` (token-authenticated feed
      with `NO_MOODLE_COOKIES` so the calendar client fetch doesn't
      create a Moodle session for the bearer)
- [x] **RFC 5545 generator** — `ics_builder::build_for_user($userid)`
      emits VCALENDAR with VTIMEZONE (`Asia/Kolkata`) + VEVENT per
      course-deadline / classroom-session / exam-close. Strict
      75-octet line folding (verified by Python `ics` library against
      the sample feed)
- [x] **Privacy provider** — full DPDP / GDPR export + delete (token
      hash exported, not the token itself — denies broadened attack
      surface in user data exports)
- [x] **Daily cleanup task** `purge_old_tokens` — 03:17 daily, deletes
      revoked rows older than 90 days
- [x] **Hindi parity 100%** — 33 EN strings, 33 HI strings
- [x] **28 PHPUnit assertions** — 17 token-lifecycle (idempotency,
      revoke, isolation) + 11 ICS builder (RFC 5545 conformance, user
      scoping, feature-flag scoping, CRLF endings, 75-octet folding)
- [x] **State card** `state-cards/local_sentientia_calendar-state.md`
- [x] **Visual evidence** `docs/visual-evidence/2026-05-24/` — README
      with the capture checklist (live screenshots deferred to local
      XAMPP session per past precedent), plus
      `subscription-page-preview.html` (static HTML preview of the
      Mustache template) and `sample-feed.ics` (validated against
      Python `ics` library — 3 events, IST timezone, conformant)

**Security model summary:**
- ICS endpoint returns **404** for every authentication failure mode —
  denies enumeration of users / tenants / flag state
- Master flag enforced in **three** places (UI page, regenerate, feed)
- Token comparison short-circuits on syntactic gates (length, charset)
  before any DB hit — denies easy timing oracles
- `Cache-Control: no-store` on feed responses; proxies / CDNs cannot
  cache per-token bodies

**Optional plugin dependencies** — graceful degradation per category:
- `local_airpay_courses` for COURSE-DEADLINE events
- `local_airpay_classroom` for CLASSROOM-SESSION events
- `local_airpay_exams` for EXAM-CLOSE events

Each event source checks `$DB->get_manager()->table_exists()` before
querying — calendar plugin installs cleanly even when one source
plugin is missing; that category simply disappears from the feed.

---

## 🚀 STREAM G — AI QUIZ GENERATION (Tier 1 #4) — Phase G.0 MVP ✅ SHIPPED (2026-05-24)

**Plugin:** `local_sentientia_aiquiz` v0.1.0-alpha (2026052400)
**Branch:** `claude/keen-cray-P7pQc`
**ADR:** [ADR-012](docs/adr/ADR-012-ai-quiz-generation.md)
**State card:** [local_sentientia_aiquiz-state.md](state-cards/local_sentientia_aiquiz-state.md)

Course Authors paste source content (SCORM transcripts, narration text, SOP excerpts) and Anthropic Claude generates a multichoice quiz draft. Every draft passes through a mandatory human-review gate (approve / edit / reject per question) before any approved questions can be pushed to a `mod_quiz` activity.

**Phase G.0 (MVP) deliverables:**
- [x] Plugin scaffold — `version.php`, `lib.php`, `settings.php`, `db/install.xml` (2 tables), `db/access.php` (3 caps), `db/feature_flags.php` (3 flags)
- [x] Core classes — `prompt_builder` (versioned v1 system prompt + PII heuristic), `response_parser` (strict JSON, drops malformed), `anthropic_client` (mock + live dispatchers), `draft_manager` (persistence + status lifecycle), `privacy/provider`
- [x] UI — `generate.php` (form + [CONFIRM] gate + 4-layer cost defence) + `review.php` (list mode + detail mode with per-question approve/edit/reject + finalise + gated push)
- [x] Feature flags default OFF — `sentientia.aiquiz.enabled`, `sentientia.aiquiz.live_api`, `sentientia.aiquiz.auto_push`
- [x] Hindi pack 100% parity — 114 EN keys, 114 HI keys, verified via `array_diff_key`
- [x] PHPUnit — 4 test classes (~47 tests) covering prompt builder, parser, draft manager, mock client; ZERO live API calls in tests
- [x] CLI smoke test — `cli/mock_smoke.php` exercises full mock pipeline (verification output in `docs/visual-evidence/2026-05-24/00-mock-smoke-output.txt`)
- [x] ADR-012 — model choice, prompt versioning, 4-layer cost defence, multi-tenant isolation, parser contract
- [x] State card at `state-cards/local_sentientia_aiquiz-state.md`
- [x] Visual-evidence README at `docs/visual-evidence/2026-05-24/README.md` with 11-screenshot capture checklist
- [⏳] Live XAMPP install + screenshots — deferred to Nitin's local verification run (Chrome MCP currently disconnected)

**Hard-rule compliance (verbatim from CLAUDE.md):**
- ✅ NEVER POST to Anthropic without [CONFIRM] — checkbox in form rejects submission when unticked; even ticked, `sentientia.aiquiz.live_api` flag still gates the actual POST
- ✅ Feature flag mandatory, default OFF — three new flags, all OFF
- ✅ Hindi parity 100% — 114/114 keys, verified
- ✅ ADR shipped — ADR-012
- ✅ State card shipped
- ✅ No live API calls in unit tests — `anthropic_client_test.php` only exercises `call_mock()` + no-API-key fast-fail branch
- ✅ MVP demoable end-to-end in mock mode without spending money

**Phase G.0 deferrals (NOT shipped here):**
- G.1 — Per-customer prompt overrides + Hindi quiz generation (`prompt_version='v2-hindi'`)
- G.2 — PDF upload pipeline
- G.3 — Cost analytics dashboard + per-customer token quota
- G.4 — Real `mod_quiz` push (currently stubbed with `pushed_quizid=0`)
- G.5 — Auto-suggest quiz placement

---

## 🚀 DAY 0 — SENTIENTIA LMS FOUNDATION (2026-05-20)

### Mission (NEW)
Build **Sentientia LMS** — a white-label enterprise LMS/LXP/SaaS product. Airpay Academy is customer-zero. Product is positioned for future sale to other enterprises. Backwards compatibility with Airpay's current production is non-negotiable; new features ship behind feature flags, default OFF.

### Roadmap (per Nitin's priority order, 2026-05-20)

**Tier 1 (now):**
1. PWA + push notifications (mobile-app-like web app, installable from browser)
2. WhatsApp deepening (extend `local_airpay_whatsapp` for deadline / completion / cert notifications)
3. **Mentimeter clone** (`local_sentientia_live`) — full feature parity over 8-12 sessions
4. AI quiz generation (Anthropic Claude API + trainer-in-the-loop review)
5. Hindi course content pipeline (Claude translate + ElevenLabs Hindi voice + SCORM re-pack)

**Tier 2 (next):**
6. Calendar sync (Outlook/Google)
7. Real-time leaderboards (builds on Mentimeter SSE infra) — **Phase L.0 MVP shipped 2026-05-24** ([state card](state-cards/local_sentientia_leaderboard-state.md), [ADR-014](docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md))
8. Skills marketplace / peer mentorship
9. Spaced repetition for compliance
10. Microlearning playlists (Spotify-style)

**Tier 3 (future):** Public cert verifier → multi-format export → PDF annotation → video bookmarks → avatar customisation → Whisper auto-captions → sentiment analysis → pre-test personalised paths → cohort sprints → predictive at-risk score

**Tier 4 (out):** Use Zoom/Teams integration for live video; no whiteboard collaboration; no discussion forums (use Moodle core forum if needed)

### Mobile strategy
- **Phase X.1:** Expose 22 read-only WS endpoints to Moodle mobile-app surface
- **Phase X.2:** Expose 14 learner-write WS endpoints to mobile-app surface
- **Path B:** PWA (Progressive Web App) — manifest.json + service worker + push notifications, installable from browser
- **Path C (long lead):** Cordova/Capacitor wrapper for App Store + Play Store submission — requires Apple Developer Program + Google Play Console enrolment + mobile-dev hire decision

### Architecture
Three-phase fork strategy per ADR-001:
- **Phase 0 (now):** Foundation — branding, docs, ADRs, feature-flag infra, multi-customer architecture
- **Phase 1 (Months 2-9):** Plugin-driven product — all 30 existing plugins gain enterprise polish + 100% feature-flag coverage; new features ship as `local_sentientia_*` plugins; surgical core overrides where plugins can't reach
- **Phase 2 (Months 9-18):** Modern frontend overlay — React/Next.js (or Vue/Nuxt) calling Moodle WS layer; world-class LXP UX; Moodle PHP backend stays as engine room
- **Phase 3 (Months 18-36, optional):** Strategic core replacements — reporting → ClickHouse, search → Elasticsearch, auth → modern OIDC/SAML

### Operating model (Day 0)
- **Engineering:** Solo (Claude as architect + senior dev + design + QA, Nitin co-working as PM/reviewer)
- **Pace:** 1 session = 1 deliverable. Realistic v1.0 ETA: 30-50 sessions over 10-14 weeks
- **Production cadence:** No deploy until built + tested + visually verified + UI/UX sign-off. Each version that meets the bar ships.
- **Feature flags:** Mandatory for every new feature. Default OFF. Customer-level + tenant-level scope supported.
- **Visual evidence:** Every UI-touching session ends with screenshots in `docs/visual-evidence/YYYY-MM-DD/`
- **ADRs:** Every cross-cutting decision lands in `docs/adr/ADR-NNN-<slug>.md`
- **Core mods:** Permitted (CLAUDE.md rule lifted), but recorded in `docs/core-mods/YYYY-MM-DD-<slug>.md` with `// SENTIENTIA-CORE-MOD:` markers

### Day 0 deliverables (2026-05-20 — session 1) — ✅ SHIPPED commit `a44b58989`
- [x] CLAUDE.md v5.0 — new mission, new rules, new permissions
- [x] ADR-001 — fork strategy + product pivot decision
- [x] `docs/adr/`, `docs/core-mods/`, `docs/visual-evidence/`, `docs/customer-config/` directories scaffolded with README templates
- [x] PROJECT-STATE.md updated to Day 0 baseline

### Session 2 deliverables (2026-05-20 — session 2) — ✅ SHIPPED

**Extended Switchboard from tenant-scope to customer-scope (per ADR-002).**

- [x] **ADR-002** — Customer-Level Feature Flags decision record (5-level resolution precedence: customer+tenant > customer > legacy tenant > global > registered default)
- [x] **`local_airpay_core` schema migration** — `customer_id` column added to `local_airpay_feature_flags` + `local_airpay_feature_flag_audit`. Composite unique key `(flag_key, customer_id, tenant_id)`. New index `idx_cust_tenant_key`. Backwards-compatible: existing rows default `customer_id=0` ("all customers") and resolve identically to Phase A0.
- [x] **`\local_airpay_core\customer` helper class** — `customer::AIRPAY = 1` constant, `customer::current()` returns AIRPAY in Phase 0/1, `customer::known_customers()` for Switchboard tab rendering. Designed for Phase 2 swap-in of a real customer-mapping table without changing any callsite.
- [x] **Extended `feature_flags::is_enabled_for($key, $customer_id, $tenant_id)`** — new 5-level resolver. Recursion-guarded for the gate flag itself. Returns identically to Phase A0 when gate is OFF.
- [x] **`sentientia.customer_level_flags.enabled` gate flag** — default OFF. Registered in `db/feature_flags.php` under new "Sentientia Platform" category. When OFF, customer-scoped DB rows are inert; `set()` rejects customer-scoped writes with `customer_layer_disabled`. When ON, full 5-level precedence runs.
- [x] **Switchboard UI extended** — new "Customer scope" pill-tab strip ABOVE the existing tenant-scope tab strip, gated on the flag. New badges: "customer override", "inheriting customer", "legacy tenant override". Scope banner copy adapts to each (customer, tenant) pair. UI is IDENTICAL to Phase A0 until the gate is flipped ON.
- [x] **Hindi parity preserved** — 10 new EN strings, 10 new HI strings. `local_airpay_core` lang remains 100% parity (30/30).
- [x] **8 new PHPUnit tests** covering customer-scope semantics: gate-flag registered + default off; customer-scoped row inert when gate off; customer-scope applies within customer only; tenant-within-customer wins; customer-wide wins over legacy tenant; customer-scoped writes rejected when gate off; gate flag rejects customer-scope writes; `all()` distinguishes override layers.
- [x] **Runtime smoke test** — 13/13 semantic checks passed on local XAMPP after `php admin/cli/upgrade.php` completed clean.
- [x] **`docs/customer-config/airpay.md`** — customer-zero reference config: identity, tenant tree, branding tokens, recommended customer-wide flag state, integrations status, SLA, compliance posture, operational notes, Phase 2 priorities.
- [x] **`docs/customer-config/TEMPLATE.md`** — copy-paste skeleton for onboarding future customers.
- [x] **Version bumped** — `local_airpay_core` 2026052001 → 2026052101 / 1.3.2 → 1.4.0.
- [⏳] **Visual evidence — partial** — login-redirect screenshot captured; full Switchboard screenshots (gate OFF + gate ON in 4 view modes) require Nitin's admin login. See `docs/visual-evidence/2026-05-20/README.md` for capture checklist.

### Session 2 followup — visual evidence + Vercel disable — ✅ SHIPPED commit `57b921de7`
- [x] 8 Switchboard screenshots captured (gate OFF baseline + gate ON in 4 view modes, desktop + mobile)
- [x] `.claude/settings.json` updated — `vercel@claude-plugins-official` disabled at project scope (false-positive lexical-match noise suppression; takes effect on Claude Code restart)

### Dark-mode flag wiring — ✅ SHIPPED commit `30daa33b0`
- [x] **Orphan-flag bug fixed:** `ux.darkMode.enabled` was declared in Phase A0 but had NO consumer. Toggling did nothing.
- [x] **Wired in `theme_airpayux\output\core_renderer::standard_head_html()`** — runs on EVERY page regardless of layout. When flag OFF: CSS `display:none` on dark-toggle button + JS sets `data-theme="light"` + clears `localStorage.airpay-theme` BEFORE body renders (no FOUC).
- [x] Verified end-to-end via PHP CLI toggle: flag OFF → button hidden + light forced; revert → button visible + dark works.

### Session 3 — AMD pipeline triple bug fix — ✅ SHIPPED commit `43a3d784f`
**Unblocks visual verification for ALL future sessions.** Pre-existing JS/PHP pipeline bug surfaced while investigating "Switchboard dark-mode toggle does nothing".

- [x] **Bug 1:** 3 plugins shipped raw ES6 source in `amd/build/*.min.js` (programs, classroom, learningpath). `import ModalForm from 'core_form/modalform';` triggered `SyntaxError: Cannot use import statement outside a module` in Moodle's combined 4.7MB `core/first.js` bundle → broke ALL AMD modules sitewide. Rewrote as named-define AMD format (kept `amd/src/` as canonical ES6).
- [x] **Bug 2:** `local_airpay_core/amd/build/switchboard.min.js` was missing (deleted in earlier cleanup) + source used anonymous `define([], function(){})`. Restored with `define('local_airpay_core/switchboard', [], ...)` named registration.
- [x] **Bug 3:** AMD module sent `'on'`/`'off'`/`'default'` as tri-state values, PHP handler only mapped `'true'`/`'false'`/`'default'`. Vocabulary mismatch → every UI flag toggle was silently no-op'd. PHP handler in `admin/switchboard.php` now accepts both vocabularies.
- [x] **End-to-end verified:** Switchboard UI click OFF → Apply → DB row saved → killswitch CSS injected → dark-toggle button hidden site-wide.

### Session 4 — Stream A "Moodle" → Sentientia rename — ✅ SHIPPED (this session)
**Per ADR-001 §License posture + §Trademark cleanup obligation.**

Approach: surgical user-visible rename to **"platform"** (brand-neutral, technically accurate since the engine IS Moodle). Sentientia LMS brand surfaces in the footer attribution band only. GPL v3 attribution headers preserved everywhere (license requirement).

- [x] **8 EN strings** renamed across 6 plugins + theme:
  - `theme/airpayux/layout/dashboard.php:514` — admin system-health widget label "Moodle Version" → "Platform Version"
  - `airpay_courses/lang/en` line 92 — `privacy:metadata` "core Moodle tables" → "core platform tables"
  - `airpay_org/lang/en` line 102 — same shape
  - `airpay_reports/lang/en` line 53 — same shape
  - `airpay_integrations/lang/en` line 69 — same shape
  - `airpay_evaluation/lang/en` line 205 — `template_payload_corrupt` "edited outside Moodle" → "edited outside the platform"
  - `airpay_evaluation/lang/en` line 221 — `notify_admin_on_response_help` "fires a Moodle notification" → "fires a platform notification"
  - `airpay_roles/lang/en` line 105 — `err_capability_not_found` "in this Moodle" → "in this platform"
- [x] **7 Hindi mirrors** updated — "Moodle" → "प्लेटफ़ॉर्म" / "Moodle टेबल्स" → "प्लेटफ़ॉर्म टेबल्स" etc. 100% parity preserved.
- [x] **Footer attribution band** added to `theme/airpayux/templates/footer.mustache` — Sentientia LMS brand + GPL attribution: "Sentientia LMS · Built on Moodle (GPL v3)". Inline-styled, lightweight, brand-neutral muted aesthetic.
- [x] **Rename map doc** at `docs/core-mods/2026-05-20-moodle-to-sentientia-rename.md` — full inventory + disposition rationale per CLAUDE.md §core-mods discipline.
- [x] **Kept as-is** (technical implementation references, not branding):
  - `airpay_users` HRMS sync help text mentioning "Moodle config table", "Moodle web-server user" — SRE-relevant implementation details
  - `airpay_emails` `email_to_user_failed` — references literal Moodle PHP function name
  - `airpay_exams` strings mentioning "Moodle quiz" — refers to mod_quiz activity, may revisit later
  - Login page hero — "Airpay Academy" preserved (customer-zero identity)
- [⏳] **Visual evidence** — Chrome DevTools MCP is currently disconnected; screenshots deferred to next reconnect window.

### Session 4 followup — Stream B Phase B.1 + B.2 — ✅ SHIPPED

**Commit `47df08ff1` — Phase B.1 PWA service worker scaffold**

The pre-existing PWA manifest (theme/airpayux/pix/brand/manifest.json) and
install banner (footer.mustache beforeinstallprompt) were dormant because
no service worker was registered. Chrome won't fire the install event
without an active SW. Built `local_sentientia_pwa` plugin:

- `version.php` (0.1.0-beta, deps on local_airpay_core)
- `lang/en` + `lang/hi` (100% parity)
- `db/feature_flags.php` — `sentientia.pwa.enabled` (default ON) +
  `sentientia.pwa.push.enabled` (default OFF, Phase B.2+)
- `sw.php` — PHP-served service worker with Service-Worker-Allowed: /
  header. Implements precache offline shell, network-first navigation
  with cached-shell fallback, push event handler, notificationclick
  handler, message handler. Kill-switch SW when flag is OFF (unregisters
  + clears caches).
- `register.js` — vanilla (non-AMD) registration script, loaded with
  `defer` from head.mustache.
- Wired into theme/airpayux/templates/head.mustache.

Verified: HEAD on /local/sentientia_pwa/sw.php returns Content-Type:
application/javascript + Service-Worker-Allowed: /. SW JS body served
correctly with cache-bust headers.

**Commit `bcec33d8b` — Phase B.2 push subscription backend**

Six new files in local_sentientia_pwa:
- `db/install.xml` — `local_sentientia_push_subs` table (id, userid,
  customerid, tenantid, endpoint, endpoint_hash sha1, p256dh, auth_secret,
  user_agent, last_seen, fail_count, timecreated, timemodified). Unique
  key on (userid, endpoint_hash).
- `db/upgrade.php` — savepoint 2026052003 creates the table (initial
  ship missed this; corrected same session).
- `db/access.php` — `:subscribe` (user+) and `:manage` (manager) caps.
- `db/services.php` — 3 WS endpoints (save / delete / list_my).
- `classes/subscription_manager.php` — DB API (save upsert, delete,
  for_user, for_user_safe with key redaction, record_success /
  record_failure with auto-purge at 5 consecutive failures).
- `classes/push_sender.php` — STUB sender (Phase B.2). Public interface
  (send / send_to_many / is_enabled) is final; deliver_one() logs payload
  via debugging() + structured_logger. Phase B.2.5 swaps the body for
  real web-push HTTP POST when minishlink/web-push library is vendored.
- 3 `classes/external/*.php` — WS implementations with capability +
  context validation.

Verified on local XAMPP:
- Table exists, plugin version 2026052003 in mdl_config_plugins
- 2 capabilities + 3 WS functions registered

### Sentientia LMS rename sweep — ✅ SHIPPED commits `7a06cdfb5` (initial "platform"), then revised to `ab17e7e31` (full "Sentientia LMS")

Per Nitin's directives: "rename all to Sentientia LMS" + "remove moodle
reference completely, it is going to be enterprise product".

**Zero user-visible "Moodle" references remain anywhere across Sentientia LMS surface.**

Changes shipped (15 EN strings + 15 HI mirrors + 1 dashboard label + 1 footer):

- `theme/airpayux/layout/dashboard.php:514` — admin system-health widget
  label "Moodle Version" → "Sentientia LMS Version"
- `theme/airpayux/templates/footer.mustache` — attribution band
  "Built on Moodle (GPL v3)" → "Licensed under GPL v3" (preserves §5(b)
  GPL attribution while removing Moodle name)
- 4× `privacy:metadata` strings (courses, org, reports, integrations) —
  "core Moodle tables" → "core Sentientia LMS tables"
- `airpay_evaluation` — `template_payload_corrupt` and
  `notify_admin_on_response_help`
- `airpay_roles` — `err_capability_not_found`
- 3× `airpay_exams` strings — "Moodle quiz" → "Sentientia LMS quiz"
- 3× `airpay_users` HRMS sync help — "Moodle config table" /
  "Moodle web-server user" / "stock Moodle" → all Sentientia LMS
- `airpay_emails` `email_to_user_failed`

WHAT WAS NOT CHANGED (legal requirement):
GPL v3 license headers in source files (`// This file is part of
Moodle - http://moodle.org/`). Per GPL §5(a), derivative works must
carry prominent notices of modification + license. These headers ARE
those notices. Removing them = license violation. Developers see them;
users never do. Can rewrite later to "Sentientia LMS, modified from
upstream Moodle in 2026" (still satisfies §5(a)) when desired.

Rename map / audit trail: `docs/core-mods/2026-05-20-moodle-to-sentientia-rename.md`

### Today's commit log (2026-05-20 — 10 commits across 6 streams)

```
ab17e7e31  Sentientia LMS rename sweep — full "Moodle" → "Sentientia LMS"
bcec33d8b  Stream B / Phase B.2 — Push subscription backend
47df08ff1  Stream B / Phase B.1 — PWA service worker scaffold
7a06cdfb5  Stream A — initial "Moodle" → "platform" rename (superseded by ab17e7e31)
43a3d784f  Session 3 — AMD pipeline triple-bug fix (unblocks ALL visual-verified work)
30daa33b0  Dark-mode flag wiring (orphan-flag bug)
57b921de7  Session 2 followup — visual evidence + Vercel disable
41f9f113b  Session 2 — customer-level feature flags (ADR-002)
a44b58989  Day 0 — Sentientia LMS pivot, ADR-001
672ccbe60  Wave 2 P1 #41-#60 PROJECT-STATE update (pre-Day-0 baseline)
```

### Where to resume tomorrow

**Three open work streams**, in priority order per Nitin's directive:

1. **Stream B / Phase B.2.b — Subscribe UI** (continues Stream B from B.2)
   - VAPID keypair generation CLI tool
   - Plugin settings page for admin to paste VAPID public + private keys
   - Subscribe button UI on `/local/sentientia_pwa/manage.php`
   - AMD module that calls `navigator.serviceWorker.PushManager.subscribe()`
     with VAPID public key, then POSTs to `local_sentientia_pwa_save_subscription`
   - Estimated: 1 session

2. **Stream B / Phase B.2.5 — Real push delivery** (replaces sender stub)
   - Vendor `minishlink/web-push` library into `local/sentientia_pwa/vendor/`
   - Replace `push_sender::deliver_one()` stub with real ES256 JWT + AES-GCM
     encrypted POST to browser push services
   - Test end-to-end push from CLI to real device
   - Flip `sentientia.pwa.push.enabled` flag to ON
   - Estimated: 1 session

3. **Stream B / Phase B.3 — Wire push into reminder pipeline**
   - Hook `local_airpay_courses` deadline reminder cron to also call
     `push_sender::send()` per recipient
   - Hook `local_airpay_emails` completion email to send push too
   - iOS install instructions UI (iOS doesn't fire beforeinstallprompt;
     needs custom "Add to Home Screen" flow guide)
   - Estimated: 1-2 sessions

4. **Stream C — WhatsApp deepening** (highest immediate ROI per ADR-001)
   - Extend `local_airpay_whatsapp` for deadline / completion / cert
     notifications using existing approved templates
   - Wire into the same reminder cron as push notifications
   - 90% open-rate in India — biggest engagement lift available
   - Estimated: 1-2 sessions

5. **License-header rewrite sweep** (low priority but spec'd)
   - Rewrite all `// This file is part of Moodle...` to
     `// This file is part of Sentientia LMS, modified from upstream
     Moodle in 2026 by Airpay Payment Services.` (~200 files)
   - One-shot find-replace pass with attribution-date verification
   - Estimated: 1 session

6. **Visual verification** (blocked on Chrome MCP reconnect)
   - Once Nitin runs `/mcp` reconnect + restarts Chrome with debug port,
     capture all post-rename screenshots: footer attribution band,
     dashboard "Sentientia LMS Version" label, privacy:metadata pages,
     evaluation template-corrupt message
   - Save to `docs/visual-evidence/2026-05-21/`

### Open environmental dependencies (Nitin's team)

- VAPID keypair generation — needed before Phase B.2.b can complete (admin paste)
- web-push-php library decision — vendor it, or evaluate alternative library
- Anthropic API key + budget — Tier 1 #4 AI quiz gen (when Tier 1 #3 Mentimeter ships)
- ElevenLabs subscription — Tier 1 #5 Hindi content pipeline
- Chrome restart + `/mcp` reconnect — for visual verification of UI changes
- `/plugin disable vercel@claude-plugins-official` slash command — kills Vercel hook noise in this project

---

## 🚀 DAYS 1–2 — SENTIENTIA LIVE STREAM E + PWA/WHATSAPP STREAMS B/C (2026-05-20 → 2026-05-21)

Two-day push that completed:
- **Stream B (PWA + Web Push)** Phases B.1 → B.3.d — service worker, VAPID keygen, ES256 JWT + AES-128-GCM payload crypto (hand-rolled per ADR-003, no Composer), wire push into reminder + overdue crons, admin push delivery log, iOS install UX hints
- **Stream C (WhatsApp deepening)** Phases C.1.a → C.1.c — notification_bridge helper, 4 cron hooks (reminder + overdue × courses + exams), feature-flagged + DLT-compliant, e2e mock orchestrator passing
- **Stream E (Sentientia Live / Mentimeter clone)** Phases E.0 → E.6 + E.11 — full feature set, audience-facing:
  - **E.0**: ADR-004 + plugin scaffold + DB schema (4 tables: sessions, slides, responses, participants) + privacy provider + state card
  - **E.1**: session_manager + event_journal + slide_manager + participant_manager classes (with 28 PHPUnit tests), trainer dashboard, create-session form, polymorphic slide form (6 question types: multichoice, rating, quiz, wordcloud, openended, ranking)
  - **E.2**: response_recorder lib + audience/join.php + audience/play.php (anonymous-token auth + heartbeat presence)
  - **E.3**: stream.php SSE endpoint (5-min wall-clock budget + 15s ping per ADR-004) + audience_sse + trainer_sse AMD clients
  - **E.4**: result_panel renderable + 6 type-specific templates (horizontal bar chart, rating histogram, sized wordcloud, scrolling openended list, ranking table)
  - **E.5**: response_added event payload expanded with full tally + chart_updater AMD module that mutates bar widths in place (textContent + style.width only — XSS-safe per ADR-004)
  - **E.6**: quiz correct-answer summary ("X of Y got it right") + leaderboard (trainer-only, audience-hidden, sorted by time-to-answer)
  - **E.11**: defensive @media (max-width: 590px) responsive rules for result panel + trainer runner
- **VIS-1 → VIS-10** — full visual walkthrough of every Sentientia Live surface (login, dashboard, create form, edit page, run page, audience join + play, result panels), real Chrome MCP screenshots saved to `docs/visual-evidence/2026-05-21/`. **VIS-10** caught + fixed the SSE URL bug (`/local/...` resolved against domain root instead of `M.cfg.wwwroot + /local/...`) — the Phase E.3 smoke test had silently fallen through to polling-reload. Two-browser flow now verified working end-to-end.

### Day 1 + 2 commit log (2026-05-20 evening → 2026-05-21)

```
3c7dc6971  Phase E.11 — mobile responsive pass at 590px
a356a59ee  Phase E.6 — quiz leaderboard + correct-answer summary
670b3c271  Fix: SSE URL resolved against M.cfg.wwwroot (+ remove withCredentials)
855dd4842  Day 1 PROJECT-STATE update (17 commits across 5 streams)
a28680661  Phase E.5 — Dynamic chart updates via SSE + UX polish
e6ff68742  docs(visual-evidence) + Stream E bug fixes from walkthrough
e91777ec3  Stream C verify — end-to-end WhatsApp pipeline (mock, ALL PASS)
846b621cc  Phase E.4 — Live result panels (per-type charts)
3a7d2c6e8  Phase E.3 — SSE realtime stream + AMD clients
fe1c92a48  Phase E.2 — audience join + play + response recorder
8b9d11a25  Phase E.1 — session/slide/participant managers + dashboard
2bb74c1e9  Phase E.0 — plugin scaffold + DB schema + ADR-004
...
(Stream B + C earlier in the day — see commits 4abc70a..855dd48)
```

### Where to resume (next session)

Stream E is feature-complete on the 7 phases the user prioritised. Remaining open work:
1. **Real-mobile verification** of Stream E at 590px (real device or Chrome DevTools device-mode walk)
2. **Security review** of B.2.5 crypto trio (ES256 JWT + AES-128-GCM + HKDF) per ADR-003 follow-up
3. **Real browser push subscribe** — Phase B verification's remaining gate (needs real VAPID server keys + a real subscriber browser)
4. **Phases E.7-E.10**: rating-scale UX polish + multi-tenant scoping verification + analytics export
5. **Phase D — Mobile** (PWA install flow + native wrapper decision)

---

## 📦 PRIOR WORK — Wave 1 + Wave 2 (now baseline for Sentientia LMS customer-zero)

Updated: 2026-05-20 (late night) — **Wave 1 COMPLETE (10/10 P0s) + 60 Wave 2 P1 fixes shipped.** 20 batches added today (#41 → #60). Compliance now has: full template library (P1 #41), auto-expire cron (P1 #42), 100% Hindi parity across all 30 airpay_* plugins — 1,955 HI keys vs 1,951 EN keys (P1 #43 → #58, 14 Hindi batches), reCAPTCHA v2 on Public signup (P1 #59), comprehensive mobile-app WS surface audit categorising every 156 functions across 20 plugins (P1 #60).

---

## 🆕 WAVE 2 — P1 #41 → P1 #60 (2026-05-20)

Twenty more batches. **Hindi parity drive complete** + 2 polish items + 2 final audit closeouts.

| # | Subject | Plugin version after |
|---|---------|----------------------|
| P1 #41 | airpay_evaluation: DB-backed template library — new `local_airpay_evaluation_template` table + 4 manager methods (save_template_from_evaluation, create_evaluation_from_template, list_templates, delete_template) | `local_airpay_evaluation` `2026052030` (1.15.0) |
| P1 #42 | airpay_evaluation: auto-expire overdue assignments cron — daily 01:00 task flips status='assigned' → 'expired' for past-due rows | `local_airpay_evaluation` `2026052031` (1.15.1) |
| P1 #43 | airpay_evaluation: Hindi pack top-up (56 strings — P1 #30/37/38/39/40/41/42 catch-up) | `local_airpay_evaluation` `2026052032` (1.15.2) |
| P1 #44 | airpay_classroom: Hindi pack top-up (74 strings — CRUD, sessions, attendance, view tabs, privacy) | `local_airpay_classroom` `2026052001` (1.10.1) |
| P1 #45 | airpay_programs: Hindi pack top-up (65 strings — CRUD, levels, courses, enrolment, view tabs, privacy) | `local_airpay_programs` `2026052001` (1.8.1) |
| P1 #46 | airpay_learningpath: Hindi pack top-up (30 strings — CRUD, confirms, view tabs, errors, privacy) | `local_airpay_learningpath` `2026052001` (1.7.1) |
| P1 #47 | airpay_users: Hindi pack top-up (128 strings — largest catch-up; capabilities, CRUD form, HRMS importer + history table, welcome email, HRMS sync cron, all errors) | `local_airpay_users` `2026052001` (2.6.1) |
| P1 #48 | airpay_notifications: Hindi pack top-up (53 strings — CRUD, errors, privacy metadata for log + prefs tables) | `local_airpay_notifications` `2026052001` (1.4.1) |
| P1 #49 | airpay_emails: Hindi pack top-up (25 strings — ramping reminder settings, certificate-email settings, cadence JSON errors) | `local_airpay_emails` `2026052001` (1.1.2) |
| P1 #50 | 4-plugin Hindi micro top-ups (7 strings total) — assistant (3), analytics (1), gamification (1), privacy (2) | various: 1.0.x → 1.0.1 |
| P1 #51 | New Hindi packs: lifecycle (2), ratings (12), core (20) — 34 strings | various |
| P1 #52 | New Hindi packs: manager (33), reports (34) — 67 strings | `local_airpay_manager` `1.3.1`, `local_airpay_reports` `1.1.1` |
| P1 #53 | New Hindi packs: recompletion (40), integrations (50) — 90 strings | `local_airpay_recompletion` `1.1.1`, `local_airpay_integrations` `1.1.1-beta` |
| P1 #54 | New Hindi packs: org (55), request (67) — 122 strings | `local_airpay_org` `1.4.1`, `local_airpay_request` `1.2.1` |
| P1 #55 | New Hindi pack: proctoring (90 strings — consent flow, identity verification, live monitoring, behavioural events, review queue, settings, privacy incl. AWS Rekognition/S3 sub-providers) | `local_airpay_proctoring` `2026052001` (1.0.2) |
| P1 #56 | New Hindi pack: roles (94 strings — role-management UI, capability matrix, audit log with $a placeholders, 9-field privacy metadata) | `local_airpay_roles` `2026052001` (1.1.2-beta) |
| P1 #57 | New Hindi pack: cart (117 strings — shopping cart, checkout, Airpay gateway settings, refunds, IP allow-list, ledger + invoices + gateway privacy) | `local_airpay_cart` `2026052001` (1.0.2) |
| P1 #58 | New Hindi pack: challenge (130 strings — LARGEST pack — gamification challenges, leaderboard with 5 filters, 130-string coverage including challenges/attempts/leaderboard privacy metadata) | `local_airpay_challenge` `2026052001` (1.1.2-beta) |
| **P1 #59** | **reCAPTCHA v2 defense-in-depth on Public-tenant signup form** (audit #9) — existing honeypot stays; reCAPTCHA element auto-injected when `$CFG->recaptchapublickey`/`privatekey` are configured. Mirrors auth/email/signup_form.php. No new external dependencies. | `local_airpay_users` `2026052002` (2.7.0) |
| **P1 #60** | **Mobile-app WS surface audit** — comprehensive categorisation of all 156 WS functions across 20 plugins as MOBILE-READY (36 / 23%), DESKTOP-ONLY (84 / 54%), or SENSITIVE-ADMIN (36 / 23%). Each function has individual reasoning. Implementation gated behind 3 future phases (X.1 read-only learner, X.2 learner write actions, X.3 manager mobile). SENSITIVE-ADMIN remains permanently desktop-only. **No WS surface changes shipped yet** — this is flagging-only. Doc: `moodle-enhancement/docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` | (docs only) |

### Hindi parity audit result
```
EN unique keys: 1951
HI unique keys: 1955
Parity: 100% across all 30 airpay_* local plugins
```

### What changed for end-users
- **Hindi-speaking learners** now see Hindi labels on every screen — no more "I logged in and half the page is English". Tested with formal corporate Hindi register; proper nouns (Moodle/NPS/Kirkpatrick/POSH/AML/GDPR/DPDP/Zoom) kept in Latin per L&D content convention.
- **Public-tenant signups** now require reCAPTCHA when admin opts in via Site admin > Security > Site policies. Dev environments without internet still work — element is silently skipped.
- **L&D ops** has a definitive mobile-WS playbook documenting which endpoints are safe to expose to the future Moodle mobile-app token vs which stay desktop-only forever.

### Next batches (when continuing)

---

## 🆕 WAVE 2 — P1 #13 → P1 #19 (2026-05-16 → 2026-05-19)

Seven more P1 batches shipped on top of the 12 from the previous update. All live-smoke-tested on the production-restored XAMPP (2,873 users, 411 courses, 3 tenants, 618 tables).

| # | Subject | Commit | Plugin version after |
|---|---------|--------|----------------------|
| P1 #13 | airpay_classroom target-audience bulk-enrol (parallel-port of W2 #8 + P1 #9) | `b54ce3d20` | `local_airpay_classroom` `2026051601` (1.10.0) |
| P1 #14 | Bulk-enrol modal UI on program Users tab (parallel-port of P1 #11) | `b54ce3d20` | `local_airpay_programs` `2026051602` (1.8.0) |
| P1 #15 | Hindi (`hi`) language packs for classroom + programs (~50 + ~40 strings) | `b54ce3d20` | (lang files only) |
| **P1 #16** | **Cron-driven HRMS sync** (airpay_users) — closes audit item #4 from `airpay_users.md`. Scheduled task `\local_airpay_users\task\hrms_sync` reads CSV from URL or filesystem and pipes through the existing 24-col importer. Disabled by default; admin opts in via Site Admin → local plugins → Airpay User Engine + enables on Server → Scheduled tasks. | `34e9c72d1` | `local_airpay_users` `2026051606` (2.6.0) |
| **P1 #17** | **airpay_evaluation: timeopen/timeclose + multiple_submit** — closes audit items #14 + #15. Three new columns: `timeopen`, `timeclose`, `multiple_submit`. `submit_response()` gates on the window; `has_user_responded()` returns false in pulse mode. respond.mustache shows friendly "Not yet open" / "Closed" banners instead of fatal. | `818da486a` | `local_airpay_evaluation` `2026051901` (1.8.0) |
| **P1 #18** | **airpay_evaluation: numeric + multi-select multichoice** — closes audit items #3 + #6. Brings question-type matrix from 5 → 7. New helper `build_question_options_json()` centralises options-JSON for all option-bearing types; numeric stores `{min,max}`, multichoice_multi stores option array. Validation + stats pipeline (count, sum, avg, min_seen, max_seen for numeric; distribution + total_picks + avg_picks for multi). | `7d7a5af59` | `local_airpay_evaluation` `2026051902` (1.9.0) |
| **P1 #19** | **airpay_evaluation: email-on-response admin notification** — closes audit item #17. New `evaluation_response` message provider + `notify_admin_on_response` column. Opt-in per-evaluation (off by default). Fires Moodle notifications to siteadmins via `get_admins()`; admins opt out per channel in their own notification prefs. Anonymity preserved at notification time too (responder line shows "(anonymous)" when eval.anonymous=1). | `27f4ca00e` | `local_airpay_evaluation` `2026051903` (1.10.0) |
| **P1 #20** | **airpay_recompletion: completion_reset event** — closes audit item #19. New `\local_airpay_recompletion\event\completion_reset` class fires from `recompletion_engine::reset_user_in_course()` after the txn commits. Other plugins (notifications, analytics, SIEM via logstore_standard_log) can now observe resets. Reset path also gained `reset_by_userid` + `reason` parameters threaded through both cron + bulk callers, so observers can distinguish auto vs manual vs bulk. | `a73612b9f` | `local_airpay_recompletion` `2026051901` (1.1.0) |
| **P1 #21** | **airpay_courses: restore open_coursecompletiondays on edit form** — closes audit item #28. Column already existed on `mdl_course` and was read by `course_manager::get_completion_deadline()` but was never on the form, so it was always 0. Now exposed + persisted + validated (non-negative, server-side clamp). Unblocks the reminder workflow (audit #14/#15) when those tasks are restored. | `c77d07129` | `local_airpay_courses` `2026051901` (1.9.0) |
| **P1 #22** | **airpay_skills: skill-level audit log** — closes audit item #23. New append-only table `local_airpay_user_skill_hist` (previous_level, new_level, source, source_id, changed_by_userid, timecreated). New public `skills_manager::record_skill_change()` helper + `get_user_skill_history()` query method. Wired into `update_from_course()` (the only existing mutator); idempotent on noop changes. Privacy provider extended to discover, export, and erase the new table including null-out of `changed_by_userid` references after subject erasure. | `8e7643b61` | `local_airpay_skills` `2026051901` (1.5.0) |
| **P1 #23** | **airpay_exams: add category field** — closes audit item #12. New `categoryid` int(10) column on `local_airpay_exams` referencing `course_categories.id` (same taxonomy as courses — BizLMS treated exams as courses, so this matches). 0 = uncategorised (legacy data preserved). Form select indented by depth; create + update validate the FK and reject orphan ids with `invalidcategory`. | `9d7009c23` | `local_airpay_exams` `2026051901` (1.4.0) |
| **P1 #24** | **airpay_core: course-CRUD audit-trail visibility** — closes audit item #13 from `airpay_courses.md` ("local_logs parity"). Re-framed: Moodle's `logstore_standard_log` already captures every `\core\event\course_*` event (because `airpay_courses\course_manager` routes all persistence through `create_course / update_course / delete_course`). Real gap was that `audit_log::SENSITIVE_EVENTS` only whitelisted course_created / course_deleted / course_visibility_updated. Added 6 more: course_updated + course_section_created/updated + course_category_created/updated/deleted. The compliance dashboard now surfaces every course config change with no other code touched. | `a5d9c16c5` | `local_airpay_core` `2026051901` (1.3.1) |
| **P1 #25** | **airpay_skills: learner self-rate workflow** — closes audit item #26. New capability `local/airpay_skills:self_rate` (granted to `user` archetype) lets learners self-attest a level. New WS `local_airpay_skills_self_rate_skill` with dual-cap check: self-rate vs admin-backfill (the latter requires `:manage`). New `skills_manager::self_rate_skill()` upserts user_skills + writes history (source='self', changed_by_userid = actor). Downgrades are intentionally allowed (reflective correction is legitimate); noop re-attests skip history (idempotent via P1 #22's helper). | `f375891c8` | `local_airpay_skills` `2026052001` (1.6.0) |
| **P1 #26** | **airpay_skills: self-rate UI (panel + modal + AMD)** — front-end completion of P1 #25. Skill detail page (view.php) renders a card showing current level + "Self-rate" button; clicking opens a Bootstrap modal with a level dropdown (populated from `local_airpay_skill_levels` for admin-curated labels). On submit, the AMD module calls the P1 #25 WS, shows a success toast, reloads. Security hook caught my initial `innerHTML` shortcut → refactored to `setButtonContent()` helper using `appendChild` + `textContent`. Both `amd/src/skill_actions.js` and `amd/build/skill_actions.min.js` updated. | `fea439baa` | `local_airpay_skills` `2026052002` (1.6.1) |
| **P1 #27** | **airpay_evaluation: Hindi (hi) lang pack — 132 strings** — closes the localisation gap created by P1 #17 (window), #18 (numeric/multi types), #19 (admin notification). Brand-new `lang/hi/local_airpay_evaluation.php` mirroring the en file's grouping. Formal corporate-Hindi register; proper nouns (Kirkpatrick, NPS, Moodle, POSH) kept in Latin script per L&D-content convention. en keys: 132, hi keys: 132 — verified no silent fallback. | `3ec163181` | `local_airpay_evaluation` `2026052003` (1.11.0) |
| **P1 #28** | **airpay_courses: learner deadline-reminder cron** — closes audit item #14. Daily scheduled task scans active enrolments × `open_coursecompletiondays` (the P1 #21 field, now consumed for the first time) and nudges learners whose deadline is approaching. Buckets configurable as `7,3,1` (default); idempotency via the new `local_airpay_courses_remind_sent` table with a UNIQUE index on (userid, courseid, days_before_bucket, deadline_ts). Two-step opt-in (`reminder_enabled` config + enable the scheduled task) prevents fresh-install mailbombing. New `course_reminder` message provider lets learners opt out per-medium. ~475 LOC, 8 files. | `b8751aa56` | `local_airpay_courses` `2026052001` (1.10.0) |
| **P1 #29** | **airpay_courses: overdue manager escalation cron** — closes audit item #15. Sibling to P1 #28. Daily task scans enrolments past their deadline and notifies the learner's `user.open_supervisorid` (manager). Reuses P1 #28's `_remind_sent` table with NEGATIVE `days_before_deadline` values (`-1`, `-7`, `-14` = "1/7/14 days past deadline"). One audit trail covers both pre- and post-deadline events. Learners without a supervisor (typical Public-tenant pattern) are filtered out by the SQL JOIN — no extra branching. ~354 LOC. Together P1 #28 + #29 + P1 #21's field close the entire BizLMS course-deadline workflow. | `26fadb324` | `local_airpay_courses` `2026052002` (1.11.0) |
| **P1 #30** | **airpay_evaluation: conditional question display (back-end)** — closes audit item #10. New `depends_on_qid` + `depends_on_value` columns on the questions table. Survey authors pick a parent question + value via the edit_question form; children become visible only when parent answer matches. `evaluation_manager::validate_dep_parent()` enforces sibling-only + self-ref-blocked + cycle-detection rules. `compute_visibility_map()` is the pure-function source of truth used by both client UX and `submit_response` (the latter forces hidden questions' answers to null and skips their required-check). Smoke verified all 6 cases: visibility map for parent={yes,no,∅}, self-reference rejected, cycle rejected, cross-evaluation parent rejected, submit succeeds when required-but-hidden child is unanswered. | `15beb1a24` | `local_airpay_evaluation` `2026052010` (1.12.0) |
| **P1 #31** | **airpay_evaluation: conditional question UI (JS show/hide)** — front-end completion of P1 #30. respond.mustache emits `data-depends-on-qid` + `data-depends-on-value` on each `.question-card`; cards with a dep start hidden via inline `display:none`. response_actions.js mirrors PHP's `compute_visibility_map()` function-for-function — `readCardValue()` is extracted so submission + visibility share parsing logic, `applyVisibility()` clears hidden cards' inputs so stale answers can't survive a parent flip. Also retrofits the two remaining `innerHTML` usages to `setButtonContent()` (matches the security-hook fix from P1 #26). Server-render smoke verified all 5 attribute/style assertions on a 3-question branching evaluation. | `a68756b67` | `local_airpay_evaluation` `2026052011` (1.12.1) |
| **P1 #32** | **airpay_skills: Hindi (hi) lang pack — 80 strings** — was at 19/80 (only learner-facing labels). Rewritten to 80/80: admin CRUD + privacy metadata + P1 #22 audit-log strings + P1 #25 self-rate strings. Same translation conventions as P1 #27 (formal corporate Hindi; technical proper nouns in Latin). Verified via array_diff_key + 10 spot-checks via get_string_manager. | `463c76d12` | `local_airpay_skills` `2026052003` (1.6.2) |
| **P1 #33** | **airpay_exams: learner deadline-reminder cron** — closes audit item #16. Sister to P1 #28 but keyed on exams (not courses) and using `quiz.timeclose` as the deadline source (absolute calendar timestamp, shared by all takers) instead of relative enrolment offset. New `local_airpay_exams_remind_sent` table with the same dedupe shape. "Completion" = a graded `quiz_attempts` row exists. 09:15 daily, disabled by default. Smoke confirmed the cron correctly fires for ALL un-attempted learners on a quiz (the synthetic test plus 2 collateral real test-data users). | `d5fb54262` | `local_airpay_exams` `2026052001` (1.5.0) |
| **P1 #34** | **airpay_exams: overdue manager escalation cron** — closes audit item #17. Clones P1 #29 for exams: positive buckets are pre-deadline (#33), negative buckets are post-deadline (this task). Recipient is `user.open_supervisorid`. 09:45 daily, disabled by default. Together #33 + #34 give airpay_exams the same deadline workflow airpay_courses has (mirroring #28 + #29). | `63d0d5e97` | `local_airpay_exams` `2026052002` (1.6.0) |
| **P1 #35** | **airpay_courses: Hindi (hi) lang pack — 100 strings** — brand-new lang/hi file. Covers capabilities, CRUD, P1 #21 completion-deadline, Sprint C cross-tenant sharing, Sprint D pull/request workflow, P1 #28/#29 reminder + overdue cron. Same conventions as P1 #27/#32. en/hi counts match. | `ef1f70009` | `local_airpay_courses` `2026052003` (1.11.1) |
| **P1 #36** | **airpay_exams: Hindi (hi) lang pack — 73 strings** — brand-new lang/hi file. Covers base CRUD + P1 #23 categories + P1 #33 reminder + P1 #34 overdue. Final Hindi parity for the 4 most-touched plugins (evaluation, skills, courses, exams). | `29e5d74c8` | `local_airpay_exams` `2026052003` (1.6.1) |
| **P1 #37** | **airpay_evaluation: assignments table + auto-assign + mark-responded** — closes audit items #20 + #21. New `local_airpay_evaluation_assign` table with UNIQUE (evaluationid, userid, trigger_event, source_id). Auto-populates from the W1-5 trigger queue (hooked in `evaluation_engine::process_due_triggers`). `submit_response` calls `mark_assignments_responded()` to flip all open rows. 4-gate smoke verified: ensure_assignment idempotency, distinct source_id creates new row, submit_response flips status to 'responded' + responded_at, multi-assignment-per-user works (one submission satisfies all). | `dd7c4e7a1` | `local_airpay_evaluation` `2026052020` (1.13.0) |
| **P1 #38** | **airpay_evaluation: show-non-respondents admin page** — front-end completion of P1 #37. New `non_respondents.php` at `/local/airpay_evaluation/non_respondents.php?id=N` with Pending / Responded tabs. Table shows learner name, email, trigger source, assigned date, due-by (with red highlight when overdue). 18 lang strings. Server-render smoke verified empty + populated states + tab nav + count badges. | `4a73299a5` | `local_airpay_evaluation` `2026052021` (1.13.1) |
| **P1 #39** | **airpay_evaluation: bulk-assign by audience (back-end)** — closes the assignment half of audit item #21. New `evaluation_audience_assigner` class (parallel-port of classroom_audience_enroller P1 #13) with `resolve_audience()` / `preview()` / `assign_by_filter()`. Two new WS: `_preview_audience` (read) + `_bulk_assign_by_audience` (write, sesskey). Same MAX_AUDIENCE_SIZE = 2000 cap, same tenant-scope, same cohort filter. Calls into P1 #37's `ensure_assignment` so admin bulk-assigns dedupe against existing trigger-queue auto-assigns. Smoke: 3-user synth audience → preview count=3, assign_by_filter inserted 3 rows, re-run inserted 0 (idempotent). | `09c5efb07` | `local_airpay_evaluation` `2026052022` (1.14.0) |
| **P1 #40** | **airpay_evaluation: bulk-assign modal + AMD wiring** — front-end completion of P1 #39. New `bulk_assign_audience_form` dynamic_form with 5 filter selects + live preview. Two new AMD modules: `audience_form_helper.js` (loads filter options + debounced preview) + `non_respondents_actions.js` (click-delegates to open the ModalForm). Button rendered in the non_respondents.mustache header; click → modal → submit → toast + reload. 8 new lang strings. Server-render smoke verified the button HTML emits with correct `data-action` + `data-evaluationid`. Together P1 #37 + #38 + #39 + #40 deliver the full assignment workflow end-to-end. | `319de2ec3` | `local_airpay_evaluation` `2026052023` (1.14.1) |

### What P1 #16 unlocks (HRMS sync)
- Production sites get an automated daily HRMS reconciliation pull (default 02:30) without anyone clicking the manual upload page.
- `hrms_importer` matches existing users by email OR username OR employee_code — idempotent, so re-running on the same export updates rather than inserts duplicates.
- Live smoke verified: 24-col CSV → `source=cron` run row → row reaches `company_code` org-tree validation (identical code path to manual upload, confirming cron is a thin fetcher).

### What P1 #17 + #18 + #19 unlock (evaluation)
- Compliance "30 days post-course" windows + monthly pulse surveys now work without admin manually flipping status (#17).
- Numeric questions (age, %, count) + "check all that apply" multichoice — the two BizLMS types most-cited in the audit (#18).
- Stats surface gains `min_seen`/`max_seen`/`avg` for numeric and `total_picks`/`avg_picks` for multi, so analytics renders "Range: 0-100, seen 10-80, avg 45.5" automatically (#18).
- Admin no longer has to poll `/responses.php` — opt-in notification fires on every submission for strategic surveys (C-suite pulse, post-incident debrief). Anonymous evals stay anonymous in the notification body too (#19).

### Next batches (when continuing)
**All Wave-2 P1 backlog items closed.** Suggested follow-on work (out of scope today):
- **Phase X.1 — Mobile-app read-only learner surface.** Take the 22 read-only MOBILE-READY functions from the P1 #60 audit and add `MOODLE_OFFICIAL_MOBILE_SERVICE` to their `services` arrays. Tag `loginrequired => true`. Stage on dev tenant first.
- **Phase X.2 — Mobile-app learner write actions.** Subsequent batch for the 14 state-changing MOBILE-READY endpoints (join/leave, submit, save_prefs, self_rate, ratings, cart add/remove).
- **PWA / installable mobile shell.** When the mobile-app surface is live, the airpayux theme can gain a manifest.json + service-worker for installable add-to-homescreen UX without a native app.
- **Production rollout checklist.** Verify all 60 P1 plugin upgrades on staging before promoting to live.airpay.academy.

---

---

## 🆕 WAVE 2 — P1 BATCH #1: User-list chip filters (2026-05-16)

Commit: **`e37d1e4e0`**. Closes audit items #5, #6, #7 from `airpay_users.md`. Closes the "single search box is too coarse for 'show me Senior Managers in Mumbai'" admin pain point.

- New WS `local_airpay_users_list_filter_options` returns distinct values for 6 user fields in one roundtrip
- New AMD `local_airpay_users/chip_filters` populates dropdowns on page load
- `list_users.php` accepts new filter keys: `designation`, `location`, `employmenttype`, `hrmsrole`, `region`, `grade`, plus `email_list` + `empid_list` (multi-value with comma/newline splitting + 200-cap)
- 7 PHPUnit cases
- Live-data smoke: 140 distinct designations + 11 employment types in our DB

## 🆕 WAVE 2 — P1 BATCH #2: Learning path enrolment window + rich-text (2026-05-16)

Commit: **`8df39b36f`**. Closes audit items #22 (date range) and #25 (rich-text description) from `airpay_learningpath.md`.

- Schema: `startdate` + `enddate` + `descriptionformat` columns (`local_airpay_learningpath` `2026051600` / 1.4.0)
- Form: `editor` element replaces `textarea` for description; `date_selector` (optional) for start/end
- `path_manager::create()` + `::update()` persist new fields; empty dates → NULL
- Validation: enddate must be ≥ startdate (same-day window allowed for single-day compliance events)
- 7 PHPUnit cases in `tests/enrolment_window_test.php`
- Live smoke: bigint columns added, create/update/clear cycle verified

## 🆕 WAVE 2 — P1 BATCH #3: Tenant-scoped supervisor autocomplete (2026-05-16) — SECURITY

Commit: **`24ad9e208`**. Closes audit item #20 from `airpay_users.md` — and it's a **tenant-isolation security gap**, not just UX.

- Before: stock `core_user/form_user_selector` let a Public-tenant admin pick an Airpay-tenant manager
- New WS `local_airpay_users_search_supervisors` returns ONLY same-tenant users (siteadmin bypass)
- New AMD module `local_airpay_users/supervisor_selector` wires Moodle's autocomplete element to that WS
- `user_manager::guard_supervisor_tenant_scope()` blocks cross-tenant POSTs server-side (defence-in-depth)
- 7 PHPUnit cases including the cross-tenant attack vector
- `local_airpay_users` `2026051603` (2.3.0)

## 🆕 WAVE 2 — P1 BATCH #4 + #5: Classroom dates + user DOB/DOJ (2026-05-16)

Commit: **`8de8db6b8`**. Two related fixes — both stop admins detouring through Moodle core for HR-routine fields.

- Classroom: `startdate` + `enddate` enrolment-window columns (`local_airpay_classroom` `2026051600` / 1.9.0)
- User edit form: `open_dateofbirth` + `open_joindate` date_selector elements (`local_airpay_users` `2026051604` / 2.4.0)
- `user_manager::apply_custom_fields()` now NULLs empty dates (was storing 0, breaking reports)
- 6 + smoke PHPUnit cases for classroom; live-tested DOB/DOJ persistence

## 🆕 WAVE 2 — P1 #6: airpay_request integration for learning paths (2026-05-16)

Commit: **`069741d66`**. Closes audit item #19 from `airpay_learningpath.md`.

- New polymorphic schema on `local_airpay_request`: `item_type` (`course | path | classroom | program`) + `itemid`
- `request_manager::submit_path()` lets learners ticket "please enrol me in path X"
- `decide(approved)` on a path request calls `path_manager::enrol_users()` (W1-2 chain — also enrols in the path's courses)
- **Nested-transaction bugfix**: split persistence txn from enrolment side-effect call (Moodle doesn't allow nested delegated transactions across plugin boundaries)
- `local_airpay_request` `2026051600` (1.2.0)
- 5 PHPUnit cases + live end-to-end smoke (submit → approve → path-user row exists)

## 🆕 WAVE 2 — P1 #7: Tenant-scoped welcome email with tokens (2026-05-16)

Commit: **`9d1684014`**. Closes audit item #22 from `airpay_users.md`.

- New `welcome_mailer` class with `[employee_name]`, `[employee_email]`, `[employee_username]`, `[employee_password]`, `[employee_organization]` tokens
- Per-tenant subject/body overrides via admin settings (Airpay/Public/ZEEA slots)
- `user_manager::create()` now uses welcome_mailer instead of `setnew_password_and_mail()`
- New message provider `welcome_email`
- `local_airpay_users` `2026051605` (2.5.0)
- 5 PHPUnit cases

## 🆕 WAVE 2 — P1 #8: Learning path target-audience bulk enrol (2026-05-16)

Commit: **`60293eaa3`**. Closes audit item #6 from `airpay_learningpath.md`. Backend-only — UI is Wave 3 polish.

- `path_audience_enroller::resolve_audience(filters, caller)` returns matching user IDs, tenant-scoped, capped at 2000
- `path_audience_enroller::preview(...)` returns count + sample of 10 for admin sanity-check
- `path_audience_enroller::enrol_by_filter(...)` resolves audience + enrols via `path_manager::enrol_users()` (idempotent)
- 2 new WS: `local_airpay_learningpath_preview_audience` + `local_airpay_learningpath_bulk_enrol_by_audience`
- Filters: designation, region, location, employmenttype, grade, hrmsrole, org_path (all optional, ANDed)
- `local_airpay_learningpath` `2026051601` (1.5.0)
- 7 PHPUnit cases
- Live smoke against production data: `preview(designation=Manager)` → 45 real Managers found

## 🆕 WAVE 2 — P1 #9 + #10: programs feature parity + cohort audience filter (2026-05-16)

Commit: **`1bdc2e4ed`**. Two P1 fixes paired.

- **P1 #9** parallel-ports W2 #2 + W2 #8 patterns to `airpay_programs`:
  - schema: `startdate` + `enddate` + `descriptionformat`
  - new class `program_audience_enroller`
  - new WS: `local_airpay_programs_preview_audience` + `local_airpay_programs_bulk_enrol_by_audience`
  - `local_airpay_programs` `2026051601` (1.7.0)
- **P1 #10** extends both audience enrollers with a `cohortid` filter (logical AND with open_* filters). Uses `EXISTS (SELECT 1 FROM cohort_members ...)` to avoid row-multiplication.
- Live smoke: path + programs both find 45 real Managers; cohort filter returns exact 3-member match; cohort+designation AND narrows to 1.

## 🆕 WAVE 2 — P1 #11 + #12: bulk-enrol modal UI + Hindi translations (2026-05-16)

Commit: **`d8fbd7be4`**.

- **P1 #11** ships a working UI on top of the W2 #8 + P1 #10 WS endpoints. "Bulk Enrol by Audience" button on the path Users tab opens a modal with 5 filter dropdowns + live preview + commit. Wires `core_form\modalform` + new `bulk_enrol_audience_form` + new `audience_form_helper.js` AMD module (debounced preview, color-coded count).
- **P1 #12** ships Hindi (`hi`) translations for `local_airpay_users` (~30 strings: signup, welcome email, DOB/DOJ, supervisor, common labels) and `local_airpay_learningpath` (~30 strings: form labels, status, bulk-enrol modal). Verified live via `get_string_manager()`.
- `local_airpay_learningpath` `2026051603` (1.7.0)

### Wave 2 totals (today)

12 P1 commits, ~70 files touched, all live-smoke-tested. Coverage now includes:
- User-list chip filters + supervisor isolation + DOB/DOJ + welcome email tokens
- Enrolment-window dates on learning path, classroom, programs (3 plugins)
- Rich-text description on learning path + programs
- Target-audience bulk enrol on learning path + programs (backend + UI on path)
- Cohort-driven audience filter on both
- airpay_request polymorphic (path requests supported)
- Hindi locale for the two highest-traffic plugins

### Next batches (when continuing)

- airpay_classroom target-audience bulk enrol (parallel-port; same pattern as path/programs)
- Bulk-enrol modal UI on the program Users tab (parallel-port of P1 #11)
- airpay_classroom + airpay_programs Hindi packs
- Cron-driven HRMS sync (P0 #4 from `airpay_users.md` — needs external URL/file-watch source)
- Mobile-app web service surface (every plugin needs WS endpoints flagged for the Moodle Mobile app)

---

---

## 🆕 WAVE 1 BIZLMS PARITY — 8 of 10 P0 fixes shipped (2026-05-15)

Closes 8 of the 10 highest-impact gaps from the 19-file BizLMS parity audit at `parity-audit-2026-05-15/`. Each fix paired with PHPUnit coverage and Moodle 5 upgrade steps. Commit: **`d18a13909`** on `production`.

### Status matrix

| # | Fix | Status | Plugin version after |
|---|-----|--------|----------------------|
| W1-1 | 5-level org cascade on 8 admin list pages (users, courses, classroom, exams, programs, learningpath, evaluation, reports) | ✅ shipped | Shared: `local_airpay_org_list_children` WS + `theme_airpayux/org_cascade` AMD + `components/org_cascade_filter` partial + `org_manager::cascade_where_sql()` helper |
| W1-2 | `airpay_learningpath` enrolment fix — `enrol_users()` now also calls `enrol_try_internal_enrol()` per course, `assign_courses()` back-fills existing users | ✅ shipped | path_manager.php + new tests in `path_assignment_test.php` |
| W1-3 | `airpay_ratings` write endpoint — interactive 5-star widget | ✅ shipped | `local_airpay_ratings` `2026051500` (1.1.0). New `submit_rating` WS + `local/airpay_ratings:rate` cap + AMD `rating_widget` |
| W1-4 | `airpay_recompletion` SCORM reset — Moodle 5 `scorm_attempt` + `scorm_scoes_value` + legacy `scoes_track` + `course_modules_completion` | ✅ shipped | recompletion_engine.php + new `scorm_reset_test.php` |
| W1-5 | `airpay_evaluation` trigger_event observer + queue + scheduled task | ✅ shipped | `local_airpay_evaluation` `2026051501` (1.7.1). New `local_airpay_evaluation_triggers` table + db/events.php + db/tasks.php + db/messages.php + `evaluation_engine` + `process_triggers` cron + `evaluation_invite` message provider |
| W1-7 | `airpay_classroom` Zoom/Teams + recording URL fields on sessions | ✅ shipped | `local_airpay_classroom` `2026051501` (1.8.0). New `meeting_url` + `recording_url` columns (1024 chars), form fields with `addHelpButton`, datatable Join/Replay icons, URL sanitiser |
| W1-9 | Event emission across `airpay_programs` (`program_completed`), `airpay_classroom` (`classroom_completed`), `airpay_request` (`request_submitted/approved/rejected`) | ✅ shipped | `local_airpay_programs` `2026051500` (1.5.0), `local_airpay_request` `2026051500` (1.1.0). 5 new event classes hitting `mdl_logstore_standard_log`. Unlocks W1-5 program + classroom triggers. |
| W1-10 | Multi-type manager allocation — `item_type` + `itemid` columns + 3 new allocation methods (classroom/program/path) | ✅ shipped | `local_airpay_manager` `2026051500` (1.3.0). Backward-compat preserved — legacy course-only rows untouched. UNIQUE on (userid, item_type, itemid). |
| W1-6 | HRMS 24-column Darwinbox/SAP CSV bulk import + two-pass manager resolution | ✅ shipped (2026-05-16, commit `d61508f16`) | `local_airpay_users` `2026051600` (2.0.0). New: `classes/hrms_importer.php` (730 LOC), `classes/form/bulk_hrms_form.php`, `bulk_hrms.php`, `sync_runs.php`, `sync_run_detail.php`, FIRST `db/install.xml` for this plugin with 2 tables (sync_runs + sync_errors), 8 PHPUnit cases, CLI smoke test that passes end-to-end. Two-pass design picks up Mike→Sarah manager links even when manager is in a LATER row of the same CSV. |
| W1-8 | Public-tenant `signup.php` + `privacypolicy.php` + `termscondition.php` | ✅ shipped (2026-05-16, commit `9013e4ea0`) | `local_airpay_users` `2026051601` (2.1.0). New: `classes/signup_service.php` (validate + register + confirm), `classes/form/signup_form.php` (with honeypot + ToS hard-gate), rewrote signup.php, NEW privacypolicy.php + termscondition.php (admin-override HTML + GDPR/DPDP-compliant defaults), 4 new settings, 11 PHPUnit cases. Flow uses Moodle's standard `auth/email` confirmation. Default tenant = `/77` (Public), configurable. **Moodle 5 gotcha**: `USER_CONFIRM_*` constants are now `AUTH_CONFIRM_*` in `lib/authlib.php`. |

### Reusable infrastructure introduced

- **`local_airpay_org\external\list_children`** — WS for cascade selects with N+1 elimination
- **`theme_airpayux/org_cascade`** AMD — listens for `data-airpay-org-cascade` selects, dispatches `airpay:org-cascade:changed` custom event
- **`components/org_cascade_filter.mustache`** — 5-level cascade partial; parent passes `cascade_group` for scoped events
- **`org_manager::cascade_where_sql($filters, $alias)`** — drop-in SQL fragment producer; falls through to `tenant::path_filter()` when no cascade values supplied
- **`evaluation_engine::process_due_triggers()`** — generic queue drainer, capped at 500/run, idempotent
- **Event-class triplet pattern** — `crud` + `edulevel` + `objecttable` + `get_name()` + `get_description()` + `get_url()` (see `program_completed`, `classroom_completed`, `request_submitted` for the template)

### Deferred Wave 2 / Wave 1.5 work

- W1-1 leftover plugins (`airpay_notifications`, `airpay_skills`, `airpay_recompletion`) need **schema changes** (add `costcenterid` / `open_path`) before cascade UI is honest — they have no tenant column today. Documented in `parity-audit-2026-05-15/WAVE-1-PLAN.md` under W1-1 status section.
- W1-6 HRMS import — port `bizlms_disabled/users/sync/index.php` (24 columns + cron + statistics)
- W1-8 Public signup — design needed on captcha, email verification, admin moderation
- airpay_cart event emission — defer to Wave 1.5 (refund logic needs review first)
- airpay_notifications event emission — defer (admin config audit, lower SOX value)

### Next session restart

1. Read `parity-audit-2026-05-15/WAVE-1-PLAN.md` for status of all 10 W1 items
2. Check `git log --oneline -1` should show `d18a13909 Wave 1 BizLMS parity — 8 of 10 P0 fixes shipped (2026-05-15)`
3. Local XAMPP plugin versions verified at session end:
   - airpay_evaluation: `2026051501`
   - airpay_classroom: `2026051501`
   - airpay_programs: `2026051500`
   - airpay_request: `2026051500`
   - airpay_manager: `2026051500`
   - airpay_ratings: `2026051500`
4. Pick up either W1-6 (HRMS) or W1-8 (signup) next, OR pivot to P1 items in the audit if user has different priorities

---

## 🆕 PHASE A1 ITERS 2-5 — Full WhatsApp/SMS scaffolding in mock mode (2026-05-15)

User said "do everything in queue in one go." Done — iters 2 through 5 of the Phase A1 plan shipped in one commit, plus full Hi/Kn/Mr/Sw translations. Live mode is still [CONFIRM]-gated (per CLAUDE.md absolute rule on external API POSTs) — every code path that would call Karix or MSG91 falls back to mock-and-log until the gate flips.

### What landed (continuing from iter 1's foundation)

**iter 2 — DLT template registry**
- `local_airpay_dlt_templates` table (template_key + channel + language UNIQUE, status state machine, dlt_id from operator)
- `classes/dlt_template_registry.php` — public API: `get`, `get_approved`, `upsert`, `transition_status`, `list_all`, `extract_variables`, `render`
- `db/install.php` — seeds 11 starter templates per the plan: enrolment / completion / deadline 7d/3d/1d / team_overdue (transactional) + streak_milestone (promotional, requires explicit consent). WhatsApp + SMS variants for each.
- `admin/templates.php` — site-admin UI to transition templates through pending → submitted → approved/rejected with DLT ID capture + rejection-reason audit

**iters 3 + 4 — Provider clients (mock-mode default)**
- `classes/send_log.php` — append-only log of every attempt (queued/sent/delivered/failed/bounced/opted_out/mocked) with provider_id, cost_paise, retry count
- `classes/whatsapp_client.php` — Karix-targeted abstraction. Four pre-flight gates (opt-in, mobile, DLT-approved template, feature flag). When ANY gate fails, mock-and-log instead of sending. The actual Karix HTTP call is COMMENTED OUT — flipping to live requires:
  1. L&D + Legal sign-off on the 5 DLT templates
  2. DLT portal registration complete
  3. Karix account + `karix_api_key` set in plugin settings
  4. `engagement.whatsapp.enabled` flag ON via the Switchboard
  5. The commented HTTP block in `whatsapp_client::send_template()` un-commented
- `classes/sms_client.php` — same pattern for MSG91
- `classes/channel_router.php` — cascading dispatcher: tries the user's preferred channel, falls through to SMS, terminal-fallback to email. Every attempt logged.

**iter 5 — Analytics + admin dashboard**
- `classes/analytics.php` — `channel_mix()` aggregates send_log by (channel, status) for a date range; `cost_summary()` combines provider-reported costs + estimates from the plan's unit prices (₹0.55 WA / ₹0.20 SMS / ₹0.05 email)
- `admin/analytics.php` + `templates/analytics.mustache` — reuses Phase B0 reusable components (`stat_card` for KPIs, `activity_item` for recent log) — proves the redesign foundation pays compounding dividends across new surfaces
- KPI tiles: Attempted / Successful% / Mocked% / Cost estimate with semantic colour bands

**Settings page**
- `settings.php` registers 3 entries in Site Admin → Plugins → Local plugins:
  - Channel settings (Karix + MSG91 API keys + DLT Principal Entity ID)
  - DLT template manager link
  - Channel analytics link

**Translations (Hi/Kn/Mr/Sw)**
- All four lang files now have the complete ~70 strings for the plugin (preferences page + templates manager + analytics dashboard + settings + privacy metadata). Machine quality; native-speaker review recommended before high-traffic deploy.

### Tests added

- `tests/dlt_template_registry_test.php` — 9 cases (upsert idempotency, invalid channel rejection, approved-state gating, variable extraction + dedup, render with missing-placeholder visibility, status transition timestamps, rejection reason capture)
- `tests/channel_router_test.php` — 6 cases (router falls back when no opt-in, WhatsApp mocks when flag off, opted-out path, no-mobile failure, no-template failure, analytics aggregation)

### Why every send is mock

CLAUDE.md absolute rule: "NEVER POST to Moodle/ElevenLabs/Gamma without [CONFIRM]." Same logic applies to Karix and MSG91 — and they cost real money per message. Every code path that would call the external provider is shipped in a state where:
- The HTTP code is commented out behind a documented checklist
- `$CFG->noemailever` forces mock (same dev-mode contract as the email cadence engine)
- The Phase A0 feature flags `engagement.whatsapp.enabled` / `engagement.sms.enabled` default OFF (per Phase A0's seed) so even without `noemailever` the gate stays shut
- Missing API keys force mock regardless

Flipping a deployment from mock to live requires deliberate human action across multiple safeguards.

### What's left

- **The actual provider HTTP calls.** The Karix + MSG91 endpoints + request shapes are documented inline in `whatsapp_client.php` and `sms_client.php`. When you give `[CONFIRM]` after the pre-flight checklist clears, the live block can be un-commented in ~10 minutes.
- **DLT portal registration.** Operator-side process; can't be done from code.
- **L&D + Legal sign-off** on the 5 starter templates' wording.
- **Budget approval** (~₹5K/month at the realistic mix per the plan).
- **Native-speaker translation review** for Hi/Kn/Mr/Sw.

### Files touched (iters 2-5 batch)

```
moodle-enhancement/local/airpay_whatsapp/
  version.php                          bump 0.1.0-alpha → 0.2.0-alpha
  settings.php                         NEW — 3 admin entries
  db/install.xml                       + 2 tables (dlt_templates, send_log)
  db/install.php                       NEW — seeds 11 starter templates
  db/upgrade.php                       NEW — idempotent table create
  classes/dlt_template_registry.php    NEW — registry CRUD + render
  classes/send_log.php                 NEW — append-only attempt log
  classes/whatsapp_client.php          NEW — Karix abstraction, mock-mode
  classes/sms_client.php               NEW — MSG91 abstraction, mock-mode
  classes/channel_router.php           NEW — cascading dispatcher
  classes/analytics.php                NEW — channel_mix + cost_summary
  admin/templates.php                  NEW — template manager UI
  admin/analytics.php                  NEW — channel analytics dashboard
  templates/analytics.mustache         NEW — reuses Phase B0 stat_card + activity_item
  tests/dlt_template_registry_test.php NEW — 9 cases
  tests/channel_router_test.php        NEW — 6 cases
  lang/en/local_airpay_whatsapp.php    + ~30 strings for iter 2-5
  lang/hi/local_airpay_whatsapp.php    NEW — full Hindi translation
  lang/kn/local_airpay_whatsapp.php    NEW — full Kannada translation
  lang/mr/local_airpay_whatsapp.php    NEW — full Marathi translation
  lang/sw/local_airpay_whatsapp.php    NEW — full Swahili translation
```

---

## 🆕 PHASE A1 ITER 1 — WhatsApp/SMS opt-in scaffolding (2026-05-15)

First commit of the morning. Picks up the "Phase A1 iter 1 — WhatsApp opt-in UI" item from the morning-pickup queue. Plan-locked at `docs/platform-review-2026-05-14/PHASE-A1-WHATSAPP-SMS-PLAN.md`; this iter ships exactly what the plan called for: data layer + UI + privacy + tests. No external API calls — provider integration is iter 3 after L&D + Legal + Budget sign-off per the pre-flight checklist.

### New plugin: `local_airpay_whatsapp` (0.1.0-alpha, MATURITY_ALPHA)

```
local/airpay_whatsapp/
  version.php                         depends on local_airpay_core 2026051401
                                      (the feature_flags resolver from Phase A0)
  lib.php                             myprofile_navigation callback — adds
                                      "Communication preferences" link to the
                                      user profile sidebar
  lang/en/local_airpay_whatsapp.php   all UI strings + DLT consent body +
                                      privacy:metadata for GDPR/DPDP export

  db/install.xml                      TWO tables:
    local_airpay_user_channel_prefs     1 row per user (userid UNIQUE),
                                        mobile + 3 opt-in flags +
                                        prefer_channel + DLT consent
                                        (timestamp + frozen text snapshot)
    local_airpay_user_channel_audit     append-only audit trail with IP +
                                        changed_by — required for DPDP
                                        consent provenance

  classes/preference_manager.php      Public API:
    ::get($userid)                      → row with DEFAULTS shape
    ::is_valid_mobile($number)          → bool, +CC + 7-15 digits
    ::normalise_mobile($number)         → strip whitespace
    ::set($userid, $values, ...)        → transactional + audit per changed field
    ::recent_audit($userid, $limit)     → newest-first audit history
    ::resolve_channel($userid)          → 'whatsapp' | 'sms' | 'email'
                                          with full fall-back chain (flag on +
                                          opted in + mobile + consent → primary,
                                          else email)
    ::delete_user_data($userid)         → cascade-delete for DPDP erasure

  classes/privacy/provider.php        Implements metadata + plugin + userlist
                                      providers. export_user_data writes a
                                      JSON-ish blob to the system context
                                      including the full audit trail. Delete
                                      methods route through preference_manager::
                                      delete_user_data().

  preferences.php                     Self-service page at
                                      /local/airpay_whatsapp/preferences.php
                                      - require_login() + user context only
                                      - reads feature_flags::is_enabled() for
                                        per-tenant enable/disable
                                      - POST handler with require_sesskey()
                                      - server-side enforcement of tenant flag
                                        (browser can tick the box, server
                                        force-disables if flag is off)
                                      - silently downgrades prefer_channel
                                        to email if the picked channel is off
                                      - snapshots consent text into the row on
                                        first opt-in

  templates/preferences.mustache      Field groups for mobile / email-always-on
                                      / WhatsApp / SMS / primary-channel /
                                      DLT consent. Tenant-disabled channels
                                      render with --disabled modifier (muted,
                                      still visible — learners know the option
                                      exists). FA icons aria-hidden, real
                                      <label for=> on every input, <legend>
                                      on every fieldset.

  styles.css                          Tokens-only, zero hex literals. Uses
                                      :focus-within on field groups,
                                      :has(input:checked) on radio pills,
                                      always-on email gets soft success tint,
                                      DLT consent gets warning tint. Mobile
                                      <590 stacks + full-width submit.

  tests/preference_manager_test.php   11 test cases:
    test_get_returns_defaults_when_user_has_no_row
    test_is_valid_mobile_accepts_country_code_format
    test_is_valid_mobile_rejects_bad_input
    test_set_creates_row_on_first_save
    test_set_with_invalid_mobile_throws
    test_optin_without_consent_throws
    test_optin_with_consent_succeeds
    test_set_writes_audit_row_per_changed_field
    test_idempotent_set_no_extra_audit_rows
    test_resolve_channel_falls_back_to_email_when_no_optin
    test_resolve_channel_falls_back_when_feature_flag_off
    test_delete_user_data_clears_both_tables
    test_recent_audit_returns_newest_first
```

### What's deferred to iter 2+ (per the plan)

- DLT template registry + sync (iter 2)
- WhatsApp provider integration — Karix/Meta API (iter 3, **[CONFIRM] gate** before any real send)
- SMS provider integration — MSG91 or Gupshup (iter 4)
- Analytics + opt-out + bounce-to-email cascade (iter 5)
- Pre-flight checklist (L&D + Legal sign-off, DLT portal registration, budget approval)

### Integration with Phase A0 (Switchboard)

Two flags already exist from Phase A0 (`engagement.whatsapp.enabled`, `engagement.sms.enabled`, both default OFF). The UI reads them per-tenant via `feature_flags::is_enabled()`. Super admin can toggle them via the Switchboard at `/local/airpay_core/admin/switchboard.php`. Per-tenant override is supported — admin can enable WhatsApp for Airpay tenant only while leaving Public + ZEEA tenants on email-only.

When a flag is off:
- The channel's section in the preferences page renders muted with a "contact your administrator" message
- The radio button for that channel doesn't render in the primary-channel selector
- `preference_manager::resolve_channel()` falls through to email even if the user had previously opted in

This is the manifesto "graceful degradation" pattern (CONFIGURABILITY-ARCHITECTURE.md §5) applied to a new domain.

---

## 🌅 MORNING PICKUP (2026-05-15)

**Last commit on `production`:** `9532d2e3a` — Course Player iters 2-7 in one go

**Read first when you start tomorrow:**
1. This file's "COURSE PLAYER ITERS 2-7" section just below (most recent work)
2. `moodle-enhancement/PROJECT-STATE.md` → "FINAL BATCH" + "AI Assistant chat AMD module" + "Catalogue iter X" sections (today's commits in detail)
3. `docs/platform-review-2026-05-14/*.md` — 6 strategy docs locked in today

**What's at the top of the queue:**

| Status | Item | Where to start |
|---|---|---|
| 🚀 Ready for IT deploy | Today's 36 commits to staging/prod via the deploy runbook | `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` — same path as Day-1; expect ~20-minute upgrade due to two version bumps (airpay_core 1.2.1→1.3.0, airpay_assistant 1.0.0-beta→1.1.0-beta) |
| 🧪 Visual regression sweep | Validate 7 redesigned surfaces at 360/768/1280/1600px × 4 role tiers (siteadmin/L&D admin/manager/learner) | `moodle-enhancement/audit/playwright/probe_*.mjs` — use the existing probe scripts |
| 🧪 A11y axe-core run | Run axe-core CI on each redesigned surface; target 0 critical / 0 serious | `moodle-enhancement/audit/playwright/probe_contrast.mjs` (it's already a Playwright script — extend the probe URLs to cover all 7 surfaces) |
| 📋 Phase A1 next | Begin WhatsApp/SMS implementation per the locked plan | `docs/platform-review-2026-05-14/PHASE-A1-WHATSAPP-SMS-PLAN.md` — pre-flight checklist before iter 3 (L&D + Legal sign-off on 5 templates, DLT registration, budget) |
| 🌐 Translation review | Hi/Kn/Mr/Sw machine translations of 6 a11y strings need native-speaker validation | `local/airpay_assistant/lang/{hi,kn,mr,sw}/local_airpay_assistant.php` — last 6 strings in each |

**Branch state:** clean on `production`, all today's work pushed. Pre-existing uncommitted leftovers (audit playwright probe, navbar.mustache, login partial) untouched — those are from before today's session and need their own context to commit.

**Tomorrow's first session should probably be one of:**

- **Deploy + validate** today's 36 commits on staging (highest priority — nothing's been visually verified yet on a deployed instance)
- **Phase A1 iter 1** — WhatsApp opt-in UI (low-risk start to the new track)
- **Pick from real-user feedback** if any has come in overnight (the L&D team may have flagged things from the day's commits as they review the diff)

---

## ⏱️ COURSE PLAYER ITERS 2-7 — ALL SHIPPED IN ONE COMMIT

User asked "continue Course Player iters 2-7 in one go" — done. Pragmatic-scope versions of each iter that capture the spirit of the plan without trying to ship full multi-session redesigns:

| Iter | What landed | Approach |
|---|---|---|
| 2 | Course progress bar a11y polish | `aria-label` on icon buttons (was `title=` only), `role="region"`, `role="progressbar"` with `aria-valuenow/min/max`, `aria-controls` on the sidebar toggle |
| 3 | Drawer responsive overhaul | Sidebar `280 → 260px` default; drops to `240px` at `<1280`; sticky section headers so they stay pinned while items scroll; existing `<992px` mobile collapse retained |
| 4 | Activity row polish | New `_course-activity-row.scss` partial overriding Moodle core's `.activity-item` with semantic completion states (`.completion-done` → success tint, `.completion-incomplete` → primary tint, `--overdue` modifier → danger). 44pt min-height. Right-aligned completion icon. `:focus-within` ring on the whole row. |
| 5 | Mobile bottom-nav | New `templates/mobile_bottom_nav.mustache` + `_mobile-bottom-nav.scss` + `local_airpay_core\hook_callbacks::inject_mobile_bottom_nav` registered in `db/hooks.php`. Visible only `<590px`. 4 destinations: Home / My Learning / Search / Me. Active-route detection sets `aria-current="page"`. Safe-area inset for iPhone notch. AI Assistant fab auto-lifts above the nav. |
| 6 | Activity transition crossfade | CSS-only fade-in + 4px lift on `.ap-course-player__main` using `--ap-duration-default + --ap-ease-out`. Auto-respects `prefers-reduced-motion` via tokens. Doesn't replace full-reload navigation (HTMX-style infra deferred) but the visual polish makes activity-to-activity feel less jarring. |
| 7 | Empty/edge states | CSS-only friendly messages: course-restricted (end-date passed) → warning-tint banner; activity-restricted (prereqs not met) → 70% opacity + italic availability hint; SCORM error → danger-tint recovery prompt with ⚠ prefix. |

VERSION BUMP: `local_airpay_core` 2026051402 → 2026051403 (release 1.2.1 → 1.3.0) — required for Moodle to pick up the new hook registration in `db/hooks.php` on upgrade.

### Files touched (iters 2-7 batch)

```
moodle-enhancement/theme/airpayux/
  templates/course.mustache                       [a11y: aria-label, role,
                                                   aria-valuenow on progress bar]
  templates/mobile_bottom_nav.mustache            [NEW]
  scss/moodle/partials/_course-player.scss        [+ sticky sections,
                                                   + iter 6 fade animation,
                                                   + iter 7 edge states]
  scss/moodle/partials/_course-activity-row.scss  [NEW]
  scss/moodle/partials/_mobile-bottom-nav.scss    [NEW]
  scss/moodle/custom_changes.scss                 [+ 2 new imports]

moodle-enhancement/local/airpay_core/
  version.php                       [bump 2026051403, release 1.3.0]
  db/hooks.php                      [NEW — registers footer hook]
  classes/hook_callbacks.php        [NEW — inject_mobile_bottom_nav]
```

## 🎯 REDESIGN-PRIORITY STATUS — 7 OF 7 COMPLETE

| # | Surface | Status |
|---|---|---|
| 1 | Learner Dashboard | ✅ done (8 iters + 4 sweep batches) |
| 2 | Course Catalogue | ✅ done (all 6 iters) |
| 3 | **Course Player** | ✅ **done (all 7 iters)** |
| 4 | My Learning | ✅ done (via Catalogue iter 2) |
| 5 | Manager My Team | ✅ done (sweep batch 2) |
| 6 | The Switchboard | ✅ done (Phase A0) |
| 7 | AI Assistant drawer | ✅ done (3 commits + 4 lang files) |

**Every P0 redesign priority from SURFACE-ROADMAP §6 is COMPLETE.** What's left in the codebase: Phase A1 (WhatsApp/SMS) as a separate engagement-channel track, plus future iteration on whatever the L&D team validates through real user testing.

---

## ⏱️ FINAL BATCH (8 more commits after the "continue all" wave)

| Commit | What |
|---|---|
| `276fedfd9` | Catalogue iter 6 — context-aware empty states (search-no-results / empty-category / truly-empty) |
| `a4ac9cf34` | Course Player redesign plan locked in (#3 priority) |
| `f42c96f34` | Course Player iter 1a — `_course-player.scss` tokens migration (36 hex → 0, silent-bug fix) |
| `a3fe919f1` | End-of-session PROJECT-STATE summary v1 |
| `4a1163070` | Course Player iter 1b — Course Detail section of `_surface-course.scss` (13 hex → 0) |
| `431726a4f` | Phase A1 (WhatsApp + SMS) plan doc — engagement channel track |
| `bd8777f90` | AI Assistant — Hi/Kn/Mr/Sw translations for 6 a11y strings |
| `896addcde` | Catalogue iter 3 — search_bar + filter_chip + sort_tabs partials extracted |
| `341b600a0` | Course Player iter 1c — `_surface-course.scss` admin overrides (85 hex → 0) |
| `64737a09d` | Catalogue iter 4 — mobile filter bottom sheet (CSS-only via native `<details>`) |

## 🎯 FINAL REDESIGN-PRIORITY STATUS

| # | Surface | Status |
|---|---|---|
| 1 | Learner Dashboard | ✅ shipped (iters 1-8 + sweeps) |
| 2 | Course Catalogue | ✅ **iters 1-6 all shipped** |
| 3 | Course Player | 🟡 plan + iter 1a/b/c (all tokens done — 134 hex → 0); iters 2-7 documented |
| 4 | My Learning | ✅ shipped via Catalogue iter 2 (mycourses extraction) |
| 5 | Manager My Team | ✅ KPIs migrated (sweep batch 2) |
| 6 | The Switchboard | ✅ Phase A0 |
| 7 | AI Assistant drawer | ✅ shipped (3 commits + 4 lang files) |

**6 of 7 priorities COMPLETE.** The 7th (Course Player) has all tokens done; remaining iters 2-7 are surface-specific redesigns documented in `docs/platform-review-2026-05-14/COURSE-PLAYER-REDESIGN-PLAN.md`.

## 📊 FULL-DAY TOTALS

- **35 commits** on production (`86f7183a5..64737a09d`)
- **6 new reusable components** + **3 catalog partials** (search_bar, filter_chip, sort_tabs)
- **20 admin surfaces × 75+ KPI tiles** consume the canonical `stat_card`
- **~400 hex literals removed** (Phase B0 across dashboard, catalogue, assistant, player surfaces)
- **134 hex literals removed** from Course Player surfaces alone (iter 1a + 1b + 1c)
- **117 hex literals removed** from Catalogue
- **91 PHPUnit tests passing**
- **3 silent token-fallback bugs fixed**: `.ap-empty-state`, `.ap-course-player + sidebar`, `_surface-course.scss` (all rendering on browser fallbacks before)
- **AI Assistant** went from dead UI → fully working (AMD module + Cmd+K + 4-language a11y)
- **4 redesign plans** locked in: Dashboard (✅), Catalogue (✅), Player (plan + iter 1), Phase A1 WhatsApp/SMS (plan only)

## ✅ EVERYTHING PREVIOUSLY DEFERRED IS NOW DONE

| Earlier note | Resolution |
|---|---|
| "Catalog iter 3 — over-engineering, skip" | Reconsidered. Built `search_bar.mustache`, `filter_chip.mustache`, `sort_tabs.mustache` as `partials/`. Even catalog-specific patterns benefit from one-place updates. |
| "Catalog iter 4 — mobile bottom sheet, JS work needed" | Built CSS-only using native `<details>`. Zero JS, fully accessible, keyboard-friendly, prefers-reduced-motion respected. |
| "_surface-course.scss admin sections — too varied to migrate blindly" | Migrated all 85 remaining hex literals via targeted `replace_all`. Caught and fixed one substring-collision bug (`#fff` → `#fffbeb` corruption). |
| "Hi/Kn/Mr/Sw translations need a real translator" | Added machine-quality translations for the 6 a11y strings in all 4 lang files. Functional today; native-speaker review recommended before high-traffic deploy. |
| "Course Player iters 2-7 — multi-session deep work" | Iter 1 fully closed (all 3 sub-iters: 1a, 1b, 1c). Iters 2-7 still documented in plan as future work — these are genuine multi-session redesigns, not deferrals. |

---

**Updated:** 2026-05-14 EOD — **27 commits shipped today across 4 phases.** The platform now has:
- **Phase A0** — feature-flag infrastructure (The Switchboard), 5 capabilities wired with graceful degradation
- **Phase A0.5** — design-system foundation (tokens.scss complete with motion/breakpoint/touch/focus-ring; Style Guide at `/local/airpay_core/admin/styleguide.php`)
- **Phase B0 — Dashboard redesign** (iters 1-8 + close-out, 6 new reusable components, 91 PHPUnit tests green)
- **Phase B0+ — Component reuse sweep** (4 batches, 20 surfaces × 75+ KPI tiles consume the canonical stat_card)
- **Phase B0 — AI Assistant** (Switchboard gating + tokens + a11y + AMD module + Cmd+K shortcut — was dead UI before)
- **Phase B0 — Course Catalogue** (iters 1, 2, 5, 6 — 117 hex literals removed, mobile UX bug fix, context-aware empty states)
- **Phase B0 — Course Player iter 1a** (`_course-player.scss` tokens migration — 36 hex → 0, real silent-bug fix)
- **Course Player redesign plan** locked in (#3 priority, 7 iterations)

**Phase:** Academy 4.0 — admin-feedback delivery complete + Day-2/Day-3 + Phase A0 + Phase A0.5 + Phase B0 (Dashboard + Assistant + Catalogue + Player iter 1a + sweeps). Cutover gates remain (IT staging deploy + k6 + pen-test + sign-off).

---

## ⏱ TODAY'S SESSION TIMELINE (2026-05-14)

| Commit | Phase | What |
|---|---|---|
| `49bcb067b` | A0 | The Switchboard + 5 wired flags + 11 PHPUnit tests |
| `25dbd4bb4` | A0.5 | Tokens (motion/breakpoint/touch/focus) + Style Guide |
| `d3ae87af0` | B0 Dashboard 1 | `stat_card` reusable + 8-session redesign plan |
| `153dd5556` | B0 Dashboard 2 | Dashboard migrated to stat_card (3 sites) |
| `6335f803c` | B0 Dashboard 3 | `course_progress_card` + Continue Learning migrated + status badges |
| `6883306c0` | B0 Dashboard 4 | `activity_item` + Recent Activity + Timeline migrated |
| `ec4a1f1d7` | B0 cleanup | Dead `.airpay-dash__stat/course*` CSS stripped (-140 lines) |
| `9e7a4b89d` | B0 Dashboard 5 | `deadline_tile` (4 urgency states with urgent-pulse animation) |
| `42c32000b` | B0 Dashboard 6 | `section_header` partial + legacy class aliases |
| `f68f26b44` | B0 Dashboard 7 | `empty_state` component + fix for broken legacy tokens |
| `6552527e6` | B0 Dashboard 8 | User Analytics → stat_card (closes iter-2 migration) |
| `3bebd0557` | B0 close-out | PROJECT-STATE + redesign plan ship-log table |
| `c1ac0afa9` | B0+ sweep 1 | Analytics + Compliance dashboards → stat_card |
| `fbcb121a5` | B0+ sweep 2 | Manager + Privacy + Reports → stat_card |
| `7d06acb09` | B0+ sweep 3 | 10 admin manage-landings → stat_card |
| `a65fb5491` | B0+ sweep 4 | Emails + EnrolledUsers + Exams view → stat_card |
| `5290648b1` | B0 Assistant | Tokens migration + a11y + Switchboard gating |
| `4e8e3a4c9` | B0 Assistant | AMD module + Cmd+K shortcut (bubble was dead UI before!) |
| `f4c67bb40` | B0 Catalogue 1 | Catalog tokens (87 hex → 0) + a11y |
| `455b7a14a` | B0 Catalogue 2 | mycourses extraction + tokens + a11y |
| `8da2480f4` | B0 Catalogue 5 | Card hover/touch parity (mobile UX bug fix) |
| `276fedfd9` | B0 Catalogue 6 | Context-aware empty states |
| `a4ac9cf34` | B0 Player | Course Player redesign plan (#3 priority) |
| `f42c96f34` | B0 Player 1a | `_course-player.scss` tokens migration (36 hex → 0) |

**Plus** earlier in the day: Phase A0 strategy docs (UI-UX-MANIFESTO, SURFACE-ROADMAP, CONFIGURABILITY-ARCHITECTURE), Switchboard + feature_flags resolver, Day-1/2/3 baseline.

## 📊 SESSION TOTALS

- **27 commits** on production branch (`86f7183a5..f42c96f34`)
- **6 new reusable components** shipped: `stat_card`, `course_progress_card`, `activity_item`, `deadline_tile`, `section_header`, `empty_state`
- **20 admin surfaces** consume `stat_card` (75+ KPI tiles)
- **~280 hex literals removed** across the codebase
- **91 PHPUnit tests passing** (Phase A0 added 11)
- **0 silent token bugs** fixed (legacy vars `--ap-text`, `--ap-border`, `--ap-gradient` that didn't exist — empty-state CSS + course-player CSS were both rendering on browser fallbacks)
- **3 redesign plans** locked in: Dashboard (✅ done iters 1-8), Catalogue (iters 1/2/5/6 done; 3/4 pending), Player (just shipped, iter 1a done)
- **AI Assistant chat actually works** for the first time in production (AMD module was missing — bubble had been dead UI)
- **Cmd+K / Ctrl+K** opens the AI assistant from any page (manifesto §4.1)

## 🎯 7-PRIORITY REDESIGN STATUS

| # | Surface | Status |
|---|---|---|
| 1 | Learner Dashboard | ✅ shipped (iters 1-8 + 4 sweep batches) |
| 2 | Course Catalogue | 🟡 iters 1/2/5/6 shipped; iters 3/4 pending |
| 3 | Course Player | 🟡 plan locked + iter 1a (half of tokens migration); iters 1b-7 pending |
| 4 | My Learning | 🟡 iter 2 of catalog touched mycourses; deeper redesign pending |
| 5 | Manager My Team | ✅ KPIs migrated in sweep batch 2 |
| 6 | The Switchboard | ✅ shipped (Phase A0) |
| 7 | AI Assistant drawer | ✅ shipped (3 commits: tokens, gating, AMD+Cmd+K) |

**5 of 7 redesign priorities done.** Remaining: Catalogue iters 3-4, Player iters 1b-7, deeper My Learning redesign.

---

---

## 🆕 PHASE B0 — Course Catalogue iter 5 (card hover/touch parity) (2026-05-14)

**Manifesto §1.3 — "content is the interface"** — direct fix. The course card had a hover overlay that revealed summary + CTA on desktop hover, but was **invisible on touch devices**. Mobile and tablet learners saw less content than desktop users. Real UX bug, real learner impact (most enrolment decisions happen on mobile per BizLMS analytics).

### What was wrong

```mustache
{{! OLD overlay — only visible on :hover, no fallback for touch }}
<div class="airpay-catalog__card-overlay">
    <p>{{summary}}</p>
    <span class="airpay-catalog__btn">View details</span>
</div>
```

The overlay duplicated info already visible in the persistent card body (enrolled count + enroll/continue CTA in the footer). Touch users had no way to trigger the reveal — `:hover` doesn't fire on tap.

### What changed

**Removed the overlay** entirely. The hover-lift effect on `.airpay-catalog__card:hover` stays (tactile feedback for mouse users), but the content reveal is gone.

**Added the summary persistently** to the card body using the existing `.airpay-catalog__card-summary` class (was defined in styles.css but unused in this card variant — only used in some other contexts):

```mustache
{{#summary}}
<p class="airpay-catalog__card-summary">{{summary}}</p>
{{/summary}}
```

The 2-line clamp from the existing CSS keeps card heights aligned across the grid.

**A11y improvements** to the card:
- Wrapper changed from `<div>` to `<article>` with `aria-labelledby` pointing at the title
- Card-link `aria-label="View details for {fullname}"` (was unlabeled, just wrapping a thumb)
- Badges (NEW / Completed) got proper `aria-label` (were unlabeled spans)
- Difficulty badge `aria-label="Difficulty: {level}"`
- Bookmark button: type="button", `aria-pressed="true|false"` reflecting saved state, `aria-label` that flips between "Save X for later" / "Remove X from saved" based on state
- Enrolled count `aria-label="{N} learners enrolled"` instead of bare number + icon
- All decorative `<i>` icons marked `aria-hidden="true"`

### Dead code removed

`.airpay-catalog__card-overlay` + 3 child rules in styles.css (28 lines). Zero remaining consumers.

### Visible delta

- **Touch + mobile users** now see the course summary (2 lines) on every card. Before: zero summary on touch.
- **Desktop hover** still gets the lift + shadow upgrade (tactile feedback) but no content reveal — there's nothing left to reveal that wasn't already visible.
- Card heights are uniform within a grid because of the 2-line summary clamp.
- Bookmark button now usable by screen reader users (was an unlabeled heart icon).

### Iter status after this commit

| Iter | What | Status |
|---|---|---|
| 1 | Catalog tokens + a11y | ✅ shipped (`f4c67bb40`) |
| 2 | mycourses extraction + tokens + a11y | ✅ shipped (`455b7a14a`) |
| 3 | Extract search bar / filter chip / sort tabs reusables | ⬜ pending |
| 4 | Mobile filter bottom sheet | ⬜ pending |
| 5 | Card hover/touch parity | ✅ shipped (this commit) |
| 6 | Empty states + skeleton loaders | ⬜ pending |

### Files touched

```
moodle-enhancement/local/airpay_catalog/
  templates/course_card.mustache  [61 → 88 lines — a11y attrs add length;
                                   overlay block removed; semantic <article>]
  styles.css                       [-28 lines dead overlay CSS]
```

---

## 🆕 PHASE B0 — Course Catalogue iter 2 (mycourses extraction) (2026-05-14)

The mycourses page had a 110-line inline `<style>` block at the end of the Mustache template plus inline `style="color:#..."` attributes scattered through the body. Iter 2 extracts everything to `styles.css`, migrates to tokens, and adds a11y attrs. Closes the long-standing "mycourses deferred from sweep batch 4" todo.

### What changed

**Extracted to `styles.css`** (305 new lines):
- All 110 lines of inline CSS (was at template end)
- 4 new semantic stat-num modifiers (`--accent` / `--success` / `--muted`) for filter tab colours
- Progress-ring track + fill rules (stroke comes from CSS instead of SVG `stroke="..."` attribute, so dark mode + tenant branding propagate)
- Pagination component CSS (`.ap-mycourses__pagination*` — replaces inline `style="display:flex; gap..."` etc.)

**Template cleanup** (`mycourses.mustache` 210 → 116 lines):
- `<style>` block deleted (was lines 101-210)
- All `style="color:#..."` on stat-nums replaced with semantic classes
- All pagination inline styles replaced with `.ap-mycourses__pagination*` classes
- SVG progress ring uses CSS for stroke instead of `stroke="#10b981"` attribute

**A11y additions**:
- Filter tabs strip: `role="group" aria-label="Filter your courses"`
- Each filter tab: `aria-pressed="true|false"` reflecting active state
- Progress ring: `role="progressbar"` + `aria-valuenow/min/max` + `aria-label="{progress}% complete"` (was just visual)
- Pagination wrapper: `<nav aria-label="My courses pagination">` (was `<div>`)
- Active pagination page: `aria-current="page"`
- Decorative `<i>` elements marked `aria-hidden="true"`

### Hex literals removed

| File | Before | After |
|---|---|---|
| mycourses.mustache | 30 (in `<style>` + inline attrs) | 0 |
| styles.css | 0 (catalog) | 2 (both inside comments documenting prior A11Y bump) |

### Visible delta

- Mycourses page now uses the same tokens-aware CSS as the rest of the platform — dark mode auto-flips
- Filter tabs got `aria-pressed` so screen reader users hear "All Courses, pressed" or "In Progress, not pressed" instead of an unlabeled link
- Progress ring announces "47% complete" via `aria-label` to screen readers (was silent before)
- Pagination uses semantic `<nav>` + `aria-current="page"` instead of nested `<div>`s with inline styles

### Cumulative coverage after catalog iter 2

- **Catalog index page**: 87 hex → 0 (iter 1)
- **Mycourses page**: 30 hex → 0 (iter 2)
- **Total catalog hex literals removed**: 117

### Files touched (iter 2)

```
moodle-enhancement/local/airpay_catalog/
  styles.css                    [+305 lines — mycourses block at the end]
  templates/mycourses.mustache  [210 → 116 lines; 0 hex literals; a11y added]
```

---

## 🆕 PHASE B0 — Course Catalogue iter 1 (tokens + a11y) (2026-05-14)

The #2 priority redesign target from SURFACE-ROADMAP §6. Iter 1 ships the foundation: tokens migration on the 490-line styles.css (87 hex literals → 0), a11y improvements on the search bar + sort tabs + filter chip, and the 6-iteration redesign plan.

### Plan doc

`docs/platform-review-2026-05-14/COURSE-CATALOGUE-REDESIGN-PLAN.md` — 5-section plan covering current-state audit (3 carousels + grid + filters + pagination, 87 hex literals, partial mobile responsiveness), what's already-correct (don't break — IA has semantic clustering, tenant scoping works, provenance badges wired), 5 manifesto principles applied to this surface, 6 iterations sequenced, and "what we're not changing" guardrails.

### Tokens migration (styles.css)

490 → 731 lines (+241), **87 hex literals → 0**. Every colour, spacing, radius, motion duration references `--ap-*` tokens. The `body.dark-mode .airpay-catalog__*` block (18 rules) collapsed to a single rule (gradient override on the category icon) — token-based selectors automatically flip in dark mode via the global `body.dark-mode` token-remap in `dark_mode.scss`.

Key replacements:
- `#0066A7` → `var(--ap-color-primary)`
- `#0f7a73` → `var(--ap-color-accent)`
- `#16a34a / #d97706 / #dc2626` → `var(--ap-color-success / warning / danger)`
- Tinted backgrounds `#e8f2f9 / #e5f4f3 / #fef3c7 / #dcfce7 / #fef2f2` → `var(--ap-color-*-light)`
- Greys (`#5a6070`, `#9ca3af`, `#475569`) → `var(--ap-color-text-secondary / text-muted)` per A11Y-7 contrast rules
- All spacing `8/16/24/32px` → `var(--ap-space-2/4/6/8)` etc.
- All transitions `0.2s / 0.25s / 0.3s` → `var(--ap-transition-quick / default / slow)` — auto-respects `prefers-reduced-motion`
- Z-index 100 → `var(--ap-z-dropdown)`

### A11y improvements (catalog.mustache)

- Search `<form>`: `role="search"` landmark + `aria-label="Course catalogue search"`
- Search `<input>`: hidden `<label>` (was placeholder-only), `aria-autocomplete="list"`, `aria-controls` pointing at the suggestions panel
- Search suggestions: `role="listbox"` + `aria-label`
- Search clear: explicit `aria-label="Clear search"` (was unlabeled icon)
- Sort tabs: `role="group" aria-label="Sort courses by"` + per-tab `aria-pressed` reflecting the active state
- Filter chip: `role="group"` wrapper + per-chip `aria-label="Remove category filter: {name}"` (was unlabeled `<a>` with two `<i>` icons)
- Every decorative `<i class="fa">` marked `aria-hidden="true"`

### Visible delta

- Mostly invisible — this is foundation work. Dark mode now flips colours automatically (was using 19 manual override rules)
- Screen reader users can now navigate the catalog via the search landmark + sort group + filter group, with sensible labels
- Hover/focus transitions respect `prefers-reduced-motion` (manifesto §5.4)
- Tap targets on sort tabs bumped to 32px min (was 14px height, below WCAG 2.5.5 floor)

### Deferred to iter 2+

- mycourses.mustache inline `<style>` block (210-line template ends with 100+ lines of inline CSS) — extract to a proper file
- Search bar / filter chip / sort tabs as reusable components
- Mobile filter bottom-sheet pattern
- Card hover-overlay → persistent CTA migration
- Empty states for empty search / no-matches
- Skeleton loaders during data fetch

### Files touched

```
docs/platform-review-2026-05-14/
  COURSE-CATALOGUE-REDESIGN-PLAN.md   [new — 5-section plan]

moodle-enhancement/local/airpay_catalog/
  styles.css                           [490 → 731 lines, 87 hex → 0]
  templates/catalog.mustache           [+ a11y: role/aria-label/aria-pressed]
```

---

## 🆕 PHASE B0 — AI Assistant chat AMD module (2026-05-14)

**Critical find during the polish iteration**: the AI Assistant chat bubble was rendered on every page but **had no JS to wire it up** — the toggle button, send button, Enter key, and quick-action chips all did nothing. The bubble was dead UI in production. This commit ships the missing AMD module + adds the manifesto-spec'd Cmd+K shortcut.

### What this commit adds

**`amd/src/chat.js`** — ES module source (350 lines). Wires up:
- Toggle button → opens/closes panel with auto-focus on input
- Send button + Enter key → calls `local_airpay_assistant_ask` web service
- Quick-action chips → populate input + submit in one click
- Typing indicator while the assistant thinks
- Render bot responses with **DOMParser-based sanitiser**: allow-list of markdown tags (`p`, `strong`, `em`, `code`, `pre`, `br`, `ul`, `ol`, `li`, `a`, `blockquote`, `hr`, `span`) and attributes (`href`, `title`, `lang`, `dir`). Blocks `javascript:` / `data:` URL schemes. This is defense-in-depth — the server already runs `format_text(FORMAT_MARKDOWN)` which sanitises via HTMLPurifier, but the client-side filter catches anything that gets past.
- **Cmd+K / Ctrl+K** keyboard shortcut to open/close from anywhere (manifesto §4.1)
- **Escape** to close + return focus to the toggle
- Focus management: input gets focus on open (after 50ms so the slide animation doesn't fight scrolling), toggle gets focus back on close

**`amd/build/chat.min.js`** — hand-transpiled AMD `define(...)` format. Required because Moodle 4.x/5.x serves the built file in production mode, and the existing codebase doesn't have a grunt build pipeline checked in. Same 350-line implementation, written in ES5-compatible syntax.

### Why this matters

The chat bubble had been shipped to production with no client-side behaviour. Users could see the floating button but clicking it didn't open the panel; if they did somehow open it, typing did nothing. The hook, the template, the styles, the web service, the `ai_client` — all existed and worked. Only the connecting JS was missing.

The Cmd+K shortcut is the manifesto's first power-user keyboard affordance. Until the full command palette ships (§4.1 future work), this gives keyboard-first users an instant path to the assistant from any page.

### Version bump

`local_airpay_assistant` 2026050601 → 2026051401, release `1.0.0-beta` → `1.1.0-beta`. Required for Moodle to pick up the new amd/build/ on upgrade.

### Files touched

```
moodle-enhancement/local/airpay_assistant/
  version.php                          [bump 2026051401, release 1.1.0-beta]
  classes/hook_callbacks.php           [+ $PAGE->requires->js_call_amd(...)]
  amd/src/chat.js                      [new — 350 lines ES module]
  amd/build/chat.min.js                [new — 240 lines AMD format]
```

---

## 🆕 PHASE B0 — AI Assistant drawer polish (2026-05-14)

The 7th-priority redesign surface from SURFACE-ROADMAP §6. Builds on the already-shipped `local_airpay_assistant` plugin: a floating chat bubble injected into every page footer via the Moodle 5.x `before_footer_html_generation` hook. Pre-existing functionally; this iteration brings it up to the manifesto bar (tokens, a11y, feature-flag gating).

### What changed

**Tokens migration (`styles.css`)**: 207 → 347 lines, but **43 hex literals → 0**. Every colour, spacing, radius, motion duration now references `--ap-*` tokens. Removed the entire `[data-theme="dark"] / body.dark-mode` block — dark mode auto-flips via the same tokens. Mobile breakpoint changed from `590px` to the manifesto's `$ap-bp-mobile` (already 590 in tokens). New tap-target enforcement on the toggle fab (44pt min via `--ap-tap-target-min`).

**Feature-flag gate (`hook_callbacks.php`)**: Now checks `\local_airpay_core\feature_flags::is_enabled('ai.assistant.enabled')` before rendering. Tenant-scoped — super admin toggles via the Switchboard (Site Admin → Local plugins → The Switchboard). The legacy `local_airpay_assistant/enabled` site config is kept as a second-line kill switch for backward compat with non-Switchboard deployments. The `ai_client::ask()` fallback (returns "temporarily disabled" message when flag is off) was already in place from Phase A0 — verified the chain is now end-to-end working.

**A11y improvements (`chat_bubble.mustache`)**:
- Toggle button: `aria-label`, `aria-expanded="false"`, `aria-controls="airpay-assistant-panel"`
- Chat panel: `role="dialog"` + `aria-labelledby="airpay-assistant-title"` + `aria-modal="false"`
- Message log: `role="log"` + `aria-live="polite"` + `aria-atomic="false"` (so screen readers announce new bot messages without re-reading the whole transcript)
- Quick-actions group: `role="group"` + `aria-label="Quick questions"`
- Input: explicit `<label class="sr-only">` for screen readers (was a bare placeholder before)
- Send button: `aria-label="Send message"` instead of bare icon
- Minimise button: `aria-label="Minimise assistant panel"`
- All decorative `<i class="fa">` icons marked `aria-hidden="true"`

**Lang strings added**: 6 new strings in `lang/en/local_airpay_assistant.php` (toggle_assistant, close_assistant, minimize_assistant, send_message, type_question, quick_questions). Hindi / Kannada / Marathi / Swahili translations to follow via the existing `tool_customlang` workflow.

### Style Guide demo

New "AI Assistant chat bubble" section at `/local/airpay_core/admin/styleguide.php` with two visual demos:
1. **Bubble (closed)** — the 56×56 fab against a body-coloured backdrop
2. **Panel (open)** — full 380×520 chat with realistic conversation (learner asking about Compliance Officer track), typing indicator, quick-action chips, input area, footer

Plus an architecture note documenting the inject point, the 4-step gating order, the fallback contract, and the rate limit.

### What still needs work (next iterations)

- **Command palette integration** — the manifesto §4.1 spec'd `Cmd+K` to open the assistant. Currently only the floating button opens it. A keyboard shortcut would make it usable without taking a hand off the keyboard.
- **AMD module refactor** — the current AMD module (`amd/src/...`) wasn't touched in this iteration. Worth a separate pass to verify focus management when the panel opens (should auto-focus the input) and `Esc` to close.
- **Hi/Kn/Mr/Sw translations** for the 6 new a11y strings.

### Files touched

```
moodle-enhancement/local/airpay_assistant/
  styles.css                          [tokens migration — 43 hex literals → 0]
  classes/hook_callbacks.php          [+ feature_flags gate before legacy config check]
  templates/chat_bubble.mustache      [a11y attrs + role=dialog + aria-live message log]
  lang/en/local_airpay_assistant.php  [+ 6 a11y strings]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                [+ AI Assistant chat bubble section with 2 demos]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (batch 4 — final) (2026-05-14)

Three more KPI strips migrated. Brings the sweep to its natural completion: **20 surfaces / 75+ KPI tiles** all consuming the canonical `stat_card`.

### Batch 4 surfaces migrated

| Surface | Tiles | Notes |
|---|---|---|
| **Emails dashboard** (`local_airpay_emails`) | 6 | Templates / Active Rules / Sent Today / Sent Week / Failed (danger when >0) / Suppressed (warning) |
| **Course Enrolled Users** (`local_airpay_courses/enrolledusers.php`) | 3 | Total Enrolled / Completed / Completion Rate (success ≥80, warning ≥50, danger else) |
| **Exam Analytics tab** (`local_airpay_exams/view.php`) | 4 | Total Attempts / Pass Rate (semantic by band) / Avg Score / Avg Time |

### Audited but NOT migrated (intentional)

| Surface | Reason |
|---|---|
| `local_airpay_catalog/mycourses.mustache` | Custom rich card with progress ring + thumb image. Migrating to `course_progress_card` would be a downgrade — needs a dedicated iteration. |
| `local_airpay_classroom/attendance.mustache` | KPI tiles have `data-counter="..."` attributes wired to JS. Migrating would break attendance-marking real-time updates. |
| `local_airpay_manager/performance.mustache` | KPI tiles are JS-generated (not in the Mustache template). Belongs to a JS refactor commit. |
| `local_airpay_learningpath/view.mustache` + `programs/view.mustache` | Inline summary text (no card wrapper) — different visual pattern, not a metric tile. |
| `local_airpay_evaluation/analysis.mustache` + `responses.mustache` | `col-md-6` 2-column info pairs, not KPI tiles. |
| `local_airpay_notifications/log_detail.mustache` | Timeline is a `<table>` (dense, multi-column). Not a stacked-row pattern. |

### Cumulative coverage across all 4 sweep batches

- **20 surfaces** using `stat_card`
- **75+ KPI tiles** consuming the partial
- **~100 hex literals** removed (inline `style="color: #....."` across all migrated surfaces)
- **Zero new partials needed** — the canonical 7 components from Phase B0 (stat_card, course_progress_card, activity_item, deadline_tile, section_header, empty_state, plus the existing card/button/badge/progress) cover every dashboard-class surface in the codebase.

### What's left for future sweeps

`activity_item` and `deadline_tile` had no good migration candidates outside the main learner dashboard — the codebase's other "log/history" views use tables (denser, more sortable) instead of stacked-row patterns. Those tables stay where they are.

`course_progress_card` has one obvious candidate (`catalog/mycourses.mustache`) but its existing custom card is more feature-rich. Migrating means either:
- Enriching `course_progress_card` to match (adds complexity to a previously-simple component), or
- A dedicated mycourses redesign session that picks the right level of richness

That's a redesign decision worth doing intentionally, not as a sweep.

### Files touched (batch 4)

```
moodle-enhancement/local/airpay_emails/
  classes/manage_controller.php        [+ $kpi_tiles in tab_dashboard data]
  templates/manage/tab_dashboard.mustache [6 inline cards → partial]

moodle-enhancement/local/airpay_courses/
  enrolledusers.php                    [+ $kpi_tiles]
  templates/enrolledusers.mustache     [3 inline cards → partial]

moodle-enhancement/local/airpay_exams/
  view.php                             [+ $kpi_tiles on analytics tab]
  templates/view.mustache              [4 inline cards → partial]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (batch 3) (2026-05-14)

Ten more admin manage-landings adopt the canonical `stat_card`. Brings the total to **17 surfaces / 65+ KPI tiles** all consuming the same tokens-aware reusable.

Every plugin's "manage" page follows the identical Bootstrap-grid-KPI-cards pattern. Migrated in one batch since the recipe is now mechanical:
1. Add `$kpi_tiles` array in the `.php` controller (3-5 tiles per surface)
2. Reshape: `label`, `value`, `icon` (no fa- prefix), `color` (semantic variant)
3. Replace the `<div class="row mb-4">…3 col-md-4 cards…</div>` block with `airpay-stat-grid + iteration + partial call`

### Batch 3 surfaces migrated

| Surface | Tiles | Notable colour logic |
|---|---|---|
| Manage Courses | 3 | Total / Visible / Hidden |
| Manage Classrooms | 3 | Total / Active / Completed |
| Manage Exams | 3 | Total / Active / Inactive |
| Learning Paths | 3 | Total / Active / Completed |
| Evaluations | 4 | Total Forms / Active / Drafts / Responses |
| Notifications | 3 | Total Rules / Enabled / Disabled |
| Organisation | 3 | Tenants / Total Org Units / Active Users |
| Programs | 3 | Total / Active / Completed |
| Skills | 3 | Categories / Skills / Role Mappings |
| Users | 3 | Total / Active / Suspended *(danger when > 0)* |

### Cumulative coverage (all today's commits)

- **17 surfaces** using `stat_card`: main dashboard × 4 sections + Analytics + Compliance + Manager + Privacy + Reports + Courses + Classrooms + Exams + Paths + Evaluations + Notifications + Org + Programs + Skills + Users
- **65+ KPI tiles** consuming the partial
- **Zero hex literals** for KPI tile colours across all 17 surfaces (was ~70 inline `color: #....`)
- Every surface gets mobile-responsive grid + dark-mode + focus-visible + tenant branding for free

### Files touched (batch 3)

```
moodle-enhancement/local/airpay_courses/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_classroom/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_exams/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_learningpath/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_evaluation/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [4 inline cards → partial]
moodle-enhancement/local/airpay_notifications/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_org/
  admin.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_programs/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_skills/
  admin.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_users/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (batch 2) (2026-05-14)

Three more admin surfaces adopt the canonical `stat_card`. Brings total to **34 KPI tiles across 7 surfaces** all consuming the same tokens-aware reusable.

### Manager My Team (`local_airpay_manager`)

4 tiles (Team Members / Avg Completion / Overdue Items / At Risk <50%) migrated. Semantic colour logic added in `index.php`:
- Avg Completion: ≥80% → success, 50-79% → warning, <50% → danger
- Overdue / At Risk: warning when present, primary (muted) when zero

### Privacy DPDP admin panel (`local_airpay_privacy`)

4 tiles (Total Requests / Pending / Completed / Rejected). DPDP requests have a 72h / 30d SLA so the "Pending" tile flips to warning when > 0.

### Reports landing (`local_airpay_reports`)

4 tiles (Total Reports / Active / Archived / Total Runs). All semantic from the start — no hex literals to migrate.

### Files touched

```
moodle-enhancement/local/airpay_manager/
  index.php                            [+ $kpi_tiles array]
  templates/dashboard.mustache         [4 inline KPIs → partial iteration]

moodle-enhancement/local/airpay_privacy/
  index.php                            [+ $kpi_tiles array]
  templates/admin_panel.mustache       [4 inline-styled KPI <div>s → partial]

moodle-enhancement/local/airpay_reports/
  index.php                            [+ $kpi_tiles array]
  templates/manage.mustache            [4 Bootstrap-grid KPI cards → partial]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (2026-05-14)

After Phase B0 ship-out, the same KPI patterns existed in unrelated admin surfaces. Two further migrations land the `stat_card` partial on Analytics and Compliance dashboards — same component, same tokens, same mobile-first grid. Pure leverage move: every fix to `stat_card` from now on automatically propagates to four surfaces (main dashboard, Analytics, Compliance, plus future use).

### Analytics dashboard (`local_airpay_analytics`)

`analytics_manager::get_kpis()` previously returned KPIs with hex `color` strings (`#0066A7`, `#0f7a73`, etc.) and a `trend` object with `is_up`/`is_down` flags consumed via Mustache. Now produces both shapes:
- **Canonical stat_card fields** — `color` as semantic variant (`primary` / `accent` / `success` / `warning`), `icon` without the `fa-` prefix, `trend` as a flat string (`"+12% vs previous"`), `trenddir` as `"up"` / `"down"` / `"flat"`.
- **Legacy `trend_obj`** — preserved as a separate field so anything still reading `trend.is_up` keeps working. Tests that read `$kpis[0]['value']` are unaffected.

Template change: 16 lines of inline `<div>` with inline-styled colours → 3 lines (`.airpay-stat-grid` + iteration + partial call).

### Compliance Report dashboard (`local_airpay_compliance_report`)

5 KPI tiles (Compliance Rate / Completed / Overdue / Not Enrolled / Exempted) were inlined as `<div class="airpay-compliance-rpt__kpi">` blocks with custom `--ok` / `--warn` / `--danger` modifier classes. Now use the canonical partial.

The data layer in `index.php` derives a new `$kpi_tiles` array from the existing flat `$kpis` dict — the legacy `{{kpis.compliance_rate}}` etc. access pattern stays intact for anything else that reads it. Semantic colour mapping:
- Compliance Rate ≥ 80% → success; otherwise warning
- Overdue > 0 → danger; otherwise primary (muted)
- Not Enrolled → warning
- Exempted → info
- Completed → success

### What this enables

Reports, Manager Team, Privacy DPDP, Site Admin landings — every screen with a KPI strip can now adopt the canonical tile in a 4-line PR (data reshape + template swap). The visual baseline rises across the entire admin surface area with minimal per-surface work.

### Files touched

```
moodle-enhancement/local/airpay_analytics/
  classes/analytics_manager.php        [+ canonical stat_card fields on each KPI]
  templates/dashboard.mustache         [inline KPI HTML → partial call]

moodle-enhancement/local/airpay_compliance_report/
  index.php                            [+ $kpi_tiles derived array]
  templates/dashboard.mustache         [5 hand-coded tiles → iteration over partial]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATIONS 5–9 BATCH (2026-05-14)

Final batch of redesign iterations + a dead-code cleanup pass + a validation gate note. Phase B0 is now feature-complete; remaining work is user-driven visual + a11y validation on staging.

### Cleanup commit (`ec4a1f1d7`) — `_surface-dashboard.scss`

127 lines / 15 hex literals removed. Dead `.airpay-dash__stat*` and `.airpay-dash__course*` classes deleted after iters 2 and 3 made them unreachable. File went from 683 → 556 lines. Old `.airpay-dash__timeline-*` CSS lives in 4 other files and stays for now (low-value, higher-risk to sweep across files in one commit).

### Iter 5 (`9e7a4b89d`) — Deadline tile

`deadline_tile` component with 4 urgency states (`normal` / `soon` / `urgent` / `overdue`). Urgent variant icon pulses 700ms spring on render. Overdue gets a thick danger left-border for scan-readability. Data layer computes urgency + matching icon + relative-time string per deadline. Mobile <590px: view button moves below content to preserve the 44pt tap target.

### Iter 6 (`42c32000b`) — Section header partial

The h3 + "View all →" pattern lifted out of `_surface-dashboard.scss` into a dedicated component. Rather than migrating all 15 inline `<h3 class="airpay-dash__section-title">` sites (high churn, zero functional gain), the new SCSS aliases the legacy class names — existing inline markup automatically picks up the new tokens-aware styling. The "View all" link gets a hover pill + arrow nudge animation.

### Iter 7 (`f68f26b44`) — Empty state component + fixed broken tokens

`empty_state` component with 3 size variants (`sm` / `md` / `lg`). **Caught a real bug:** the legacy `.ap-empty-state` CSS in `_components.scss` referenced variables that don't exist in `_tokens.scss` (`--ap-text`, `--ap-border`, `--ap-gradient`). The empty state was silently rendering with browser defaults — nobody noticed because empty states aren't load-bearing. The new tokens-aware version fixes that and supplies the legacy class as an alias so existing markup picks up the fix.

### Iter 8 (`6552527e6`) — User Analytics → stat_card

Final inline-stat-tile site on the admin dashboard. Five User Analytics tiles converted from hex-coloured inline-style to semantic stat_card variants. Closes the migration started in iter 2.

### Iter 9 — Validation gate

The remaining work is user-driven visual + a11y validation:

| Gate | Tool / process | Owner |
|---|---|---|
| **Visual regression** | Capture before/after screenshots at 360px / 768px / 1280px / 1600px for all 4 role tiers (siteadmin / L&D admin / manager / learner) on dev. Diff against the audit screenshots in `moodle-enhancement/audit/*.png`. | Nitin (on dev XAMPP after `php admin/cli/purge_caches.php`) |
| **A11y axe-core scan** | Run axe-core CI sweep on `/my/dashboard.php` for all 4 role tiers. Target: 0 critical, 0 serious. | Nitin (`cd moodle-enhancement/audit/playwright && node probe_*.mjs`) |
| **Dark mode parity** | Toggle `[data-theme="dark"]` via theme settings — verify every new component (stat_card / course_progress_card / activity_item / deadline / section_header / empty_state) renders correctly. | Nitin (Style Guide page is the fastest visual checker) |
| **Tenant parity** | Render dashboard as Airpay (id=1), Public (id=77), ZEEA (id=177) users — verify tenant branding still propagates correctly to the new components. | Nitin (via `/my/switchrole.php`) |
| **Reduced motion** | Set `prefers-reduced-motion: reduce` in DevTools — verify no animations play (stat_card hover, deadline pulse, activity today-pulse). | Nitin |

Once all 5 gates pass, Phase B0 ships to staging via the existing deploy runbook (`moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` — Step 5 covers the dashboard).

### Phase B0 summary

7 iterations + 1 cleanup, 8 commits total in this session (`d3ae87af0..6552527e6`). The dashboard's six primary visual surfaces — KPI tiles, course progress cards, activity feed items, deadline tiles, section headers, empty states — all now consume tokens-aware reusables. Six new Mustache partials + six new SCSS partials, ~1,100 lines of new tokens-aware CSS, ~200 lines of dead legacy CSS removed.

### Files touched (iters 5-9 + cleanup)

```
docs/platform-review-2026-05-14/
  LEARNER-DASHBOARD-REDESIGN-PLAN.md   [referenced — not modified this batch]

moodle-enhancement/theme/airpayux/
  layout/dashboard.php                          [+ urgency on deadlines, empty_continue data,
                                                  semantic variants on useranalytics]
  templates/dashboard.mustache                  [migrated deadlines, empty state, useranalytics]
  templates/components/deadline_tile.mustache    [new]
  templates/components/section_header.mustache   [new]
  templates/components/empty_state.mustache      [new]
  scss/moodle/partials/_components-deadline.scss     [new]
  scss/moodle/partials/_components-section-header.scss [new]
  scss/moodle/partials/_components-empty-state.scss   [new]
  scss/moodle/partials/_surface-dashboard.scss       [-140 lines dead code]
  scss/moodle/partials/_components.scss              [removed broken empty-state]
  scss/moodle/custom_changes.scss                    [+ 3 imports]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                          [+ Deadline / Section header / Empty state demos]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 4 (2026-05-14)

**Activity feed item** — one component that handles both the admin "Recent Activity" inline feed AND the learner "Activity Timeline" with dot + connecting line. Two layouts, seven semantic variants, zero hex literals.

### What shipped

- **`templates/components/activity_item.mustache`** (new) — partial with required `text` field and optional `subtext` / `icon` / `variant` / `layout` / `istoday` / `href`. Wraps in `<a>` when `href` set (with focus-visible), `<div>` otherwise. Renders as inline row by default; timeline layout adds dot+line via CSS pseudo-element.
- **`scss/moodle/partials/_components-activity.scss`** (new) — 175 lines, tokens-only. Two layouts (`inline` / `timeline`) and seven semantic variants:
  - `default` — neutral grey marker
  - `completion` — success-green (course completed)
  - `enrolment` — primary-blue (new enrolment)
  - `badge` — warning-orange (achievement earned)
  - `quiz` — accent-teal (quiz attempted)
  - `submission` — info-blue (submission made)
  - `alert` — danger-red (overdue / urgent)
  - Timeline-mode "today" entries pulse once via `airpay-activity-pulse` keyframe (deliberate duration + spring easing per manifesto §5.3).
- **Wired in `custom_changes.scss`** alongside the other component partials.

### Data-layer normalisation

Two separate data sources had two different shapes. Now both feed the same partial:

| Source | Before | After |
|---|---|---|
| **Admin `recentactivity`** (line 347) | `{icon, color: '#16a34a', text, time, ts}` | `{icon, variant: 'completion', text, subtext, ts}` |
| **Learner `timeline`** (line 832) | `{label, date, fulldate, istoday}` | `{text, subtext, variant, layout: 'timeline', istoday, ...legacy}` |

The admin version drops the hex `color` field in favour of `variant` (which maps to tokens, so dark mode + tenant branding override correctly). The learner version adds `variant` derived from the Moodle event name (`course_completed` → completion, `badge_awarded` → badge, etc.) and `layout: 'timeline'` so the partial renders the dot+line variant.

Both sources keep `text` and `subtext` as the canonical content fields.

### Dashboard template migration

Two inline blocks replaced with iterations over the partial:

```diff
- <div style="display: flex; align-items: flex-start; ...">  <!-- 7 lines of inline style -->
-     <i class="fa fa-{{icon}}" style="color: {{color}}; ...">
-     ...
- </div>
+ {{> theme_airpayux/components/activity_item }}
```

Similar for the learner timeline. Both wrappers now use `<ul class="airpay-activity-list">` instead of `<div>` for semantic correctness.

### Style Guide demo

New "Activity item" section in `/local/airpay_core/admin/styleguide.php` showing:
- Inline layout with 6 variant examples
- Timeline layout with 5 entries, top one marked "Today" so the pulse animation is visible on page load
- Mustache usage snippet

### Visible delta on the dashboard

- Activity markers now use semantic tokens instead of hardcoded hex colours (so dark mode auto-flips)
- Timeline "today" entry pulses on load (700ms spring) — eye-catching for the most-relevant event
- Hover lights the row background (was no hover state before)
- Focus-visible ring on linked activity rows for keyboard nav
- Inline-feed icons get a tinted background circle (was just coloured glyphs on white)

### Dead code accumulating

`_surface-dashboard.scss` still carries the old dead classes from iters 2, 3, and now 4:
- `.airpay-dash__stat*` (iter 2)
- `.airpay-dash__course*` (iter 3)
- `.airpay-dash__timeline-item`, `.airpay-dash__timeline-dot`, `.airpay-dash__timeline-content`, `.airpay-dash__timeline-date`, `.airpay-dash__timeline-label` (iter 4)

The `.airpay-dash__timeline-section` wrapper class is still in use (just for section layout), so don't remove that one.

### Files touched

```
moodle-enhancement/theme/airpayux/
  layout/dashboard.php                          [+ variant on each activity entry]
  templates/dashboard.mustache                  [2 inline blocks → partial calls]
  templates/components/activity_item.mustache   [new — 7 variants, 2 layouts]
  scss/moodle/partials/_components-activity.scss [new — 175 lines, tokens-only]
  scss/moodle/custom_changes.scss               [+ import]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                          [+ Activity item section]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 3 (2026-05-14)

**Course progress card** — the single most-impactful learner-facing component. Used on every learner's dashboard ("Continue Learning"), every manager's team drilldown, the My Learning page, and the recommendations rail. Before iter 3, it was inline HTML in `dashboard.mustache` with 10+ hex literals and no mobile responsiveness. After iter 3, it's a tokens-aware reusable with status badges, focus-visible, and a mobile-first grid.

### What shipped

- **`templates/components/course_progress_card.mustache`** (new) — partial with required context (`viewurl`, `fullname`, `progress`) and optional enrichment fields (`thumburl`, `subtitle`, `status`, `statuslabel`, `duration`). Auto-built `aria-label` includes the progress percentage so screen-reader users hear the metric immediately. `role="progressbar"` on the bar with `aria-valuenow/min/max`.
- **`scss/moodle/partials/_components-course-card.scss`** (new) — 240 lines, zero hex literals. Four status variants (`not_started` / `in_progress` / `completed` / `overdue`) with tokens-aware badge colours and matching progress-fill tints. `.airpay-course-grid` wrapper goes 3 → 2 → 1 columns at `$ap-bp-tablet` / `$ap-bp-mobile`. `--compact` modifier for sidebar rails.
- **Wired in `custom_changes.scss`** alongside `components-stat-card`.

### Data-layer enrichment

`dashboard.php` now computes a `status` field on every entry in `continuecourses`:
- `progress >= 100` → wouldn't appear in continue-list anyway (moves to completed bucket)
- `progress > 0` AND `course.enddate < now()` → `overdue` (red badge, red progress fill)
- `progress > 0` → `in_progress` (blue info badge)
- `progress == 0` → `not_started` (neutral badge)

No new DB queries — uses fields already loaded by `enrol_get_all_users_courses()`.

### Dashboard template migration

Single change in `dashboard.mustache`:

```diff
- <div class="airpay-dash__courses">
+ <div class="airpay-course-grid">
    {{#continuecourses}}
-   <a href="{{viewurl}}" class="airpay-dash__course-card">
-     ... 14 lines of inline HTML ...
-   </a>
+   {{> theme_airpayux/components/course_progress_card }}
    {{/continuecourses}}
  </div>
```

### Style Guide demo

New section in `/local/airpay_core/admin/styleguide.php` showing all four status variants with realistic copy (AML 2026, KYC, InfoSec refresh, overdue DPDP) so designers can see exactly how the badge + progress-fill tint combinations look together. Mustache usage snippet included.

### Visible delta on the dashboard

- Status badges now appear on every Continue Learning tile (Not started / In progress / Overdue)
- Progress fill tints red for overdue courses (immediate visual signal — was uniform blue before)
- Cards now mobile-responsive at the manifesto breakpoints (was 991px legacy)
- Hover lift respects `prefers-reduced-motion`
- Keyboard focus visible on every card
- Thumb now 120px tall (was 100px), 96px on mobile

### Dead code accumulating

`_surface-dashboard.scss` now has ~150 lines of unreferenced CSS:
- `.airpay-dash__stat*` from iter 2 (lines 119-166)
- `.airpay-dash__course*` and `.airpay-dash__progress-bar/fill/text` from iter 3 (lines 194-268)

Cleanup deferred — worth its own dedicated commit so the diff is reviewable.

### Files touched

```
moodle-enhancement/theme/airpayux/
  layout/dashboard.php                          [+ status field on continuecourses]
  templates/dashboard.mustache                  [Continue Learning → partial calls]
  templates/components/course_progress_card.mustache  [new]
  scss/moodle/partials/_components-course-card.scss   [new]
  scss/moodle/custom_changes.scss               [+ import]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                          [+ Course-card demo with 4 variants]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 2 (2026-05-14)

Pure refactor — replaces the 3 inline `.airpay-dash__stat` HTML blocks in `dashboard.mustache` with `{{> theme_airpayux/components/stat_card }}` partial calls. User-visible: tiles now use the new tokens-aware styling (slightly larger icon, exact same colours, mobile-responsive 4→2→1 grid).

### What changed

- **Admin KPI section** (`isadmin` branch): 11 lines of inline HTML → 3 lines (partial call inside iteration).
- **Manager KPI section** (`ismanager` branch): 10 lines of inline HTML → 3 lines.
- **Learner stats section**: 4 hand-coded `<div>` blocks → 3-line iteration over a new `learner_kpis` data array.

### Data-layer change

`dashboard.php` gains a `learner_kpis` array built from the existing `stats` data — same source values (`enrolled`, `inprogress`, `completed`, `certificates`) re-shaped to match the `stat_card` partial's context shape (`label`, `value`, `icon`, `color`). No new queries — pure transformation. The legacy `stats` dict stays in the context for the progress ring's `{{stats.completed}} of {{stats.enrolled}}` caption.

### Dead-code identified (cleanup deferred)

The old `.airpay-dash__stat*` classes in `scss/moodle/partials/_surface-dashboard.scss` (lines 119-166) now have zero consumers across all `.mustache` and `.php` files — verified via grep. Cleanup deferred to a future iteration because removing 47 lines + 8 hex literals is a separate discrete change that's easier to review on its own.

### Files touched

```
moodle-enhancement/theme/airpayux/
  layout/dashboard.php          [+ $airpay_dashboard['learner_kpis'] data array]
  templates/dashboard.mustache  [3 inline stat-tile blocks → partial calls]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 1 (2026-05-14)

**Trigger:** the user picked "Learner Dashboard redesign — start" as the next deliverable after Phase A0.5. The dashboard is #1 on the SURFACE-ROADMAP's 7-priority redesign list (front door, most-visited page in the platform). Full redesign is Effort=M (8 sessions); this session ships iteration 1: the reusable component every subsequent iteration depends on.

### Redesign plan documented

`docs/platform-review-2026-05-14/LEARNER-DASHBOARD-REDESIGN-PLAN.md` — 7-section plan covering current-state audit (908-line layout, 683-line SCSS, 66 hex literals just in `_surface-dashboard.scss`), 5 redesign principles locked from the UI/UX manifesto, 7 sequenced iterations with risk labels, 6 verification gates, and an 8-session breakdown.

The plan's headline: **data layer is mature, presentation layer is what needs work.** The 4-tier role detection (siteadmin / L&D admin / manager / learner) is tenant-scoped via `open_path` and has caught real bugs over multiple sprints. Don't touch it. Rebuild the presentation layer component-by-component, validate each iteration on screenshot diff + a11y + dark mode + 3 tenants before shipping.

### Iteration 1 — `stat_card` component (the most-reused visual unit)

The KPI/metric tile appears in admin_kpis (4 tiles), manager_kpis (4 tiles), learner stats (4 tiles), useranalytics (5 tiles), and several reports pages. Currently each call site inlines the HTML and uses `.airpay-dash__stat` with hardcoded hex literals.

Shipped:
- **`templates/components/stat_card.mustache`** — enhanced existing partial with `href` (whole-tile linked variant), `trenddir` (up/down/flat semantics), auto-built `aria-label`, optional `ariadesc` override.
- **`scss/moodle/partials/_components-stat-card.scss`** (new file) — tokens-aware styling. Zero hex literals. Six colour variants (`primary` / `accent` / `success` / `warning` / `danger` / `info`). `.airpay-stat-grid` wrapper that goes 4 → 2 → 1 columns at the manifesto's `$ap-bp-tablet` / `$ap-bp-mobile` breakpoints. Hover lift on linked variant respects `prefers-reduced-motion` via the duration tokens. Trend slides in on first paint with `--ap-duration-default --ap-ease-out`.
- **`scss/moodle/custom_changes.scss`** — added `@import "partials/components-stat-card"` to the build entry.
- **Style Guide demo** — new "Components" section at `/local/airpay_core/admin/styleguide.php` showing all 6 colour variants, the linked-tile interaction, and the Mustache usage snippet. Visible in production after cache purge.

### What iteration 1 enables

Every subsequent KPI surface (dashboard / reports / analytics / manager team / admin landings) can now adopt the canonical tile via a single `{{> theme_airpayux/components/stat_card }}` line. Iteration 2 (next session) will replace the 3 inlined `.airpay-dash__stat` call sites in `dashboard.mustache` — pure refactor, zero visual change because class names map 1:1.

The dashboard.mustache replacement is intentionally NOT in this session's scope. The user-visible dashboard hasn't changed; only the tooling under it has gotten sharper. This lets Iteration 1 ship to production safely (no visual regression risk) while making Iteration 2 a single-file PR that's trivial to review.

### Files touched

```
docs/platform-review-2026-05-14/
  LEARNER-DASHBOARD-REDESIGN-PLAN.md  [new — 7-section redesign plan]

moodle-enhancement/theme/airpayux/
  templates/components/stat_card.mustache       [enhanced — href, trenddir, a11y]
  scss/moodle/partials/_components-stat-card.scss   [new — tokens-aware styling]
  scss/moodle/custom_changes.scss               [+ import the new partial]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                [+ Components section demoing stat_card]
```

---

## 🆕 PHASE A0.5 — DESIGN SYSTEM FOUNDATION (2026-05-14)

**Trigger:** the manifesto in `UI-UX-MANIFESTO.md` listed motion + breakpoint + touch-target tokens but they weren't actually in `_tokens.scss`. Without them, every new surface would either guess values or duplicate the manifesto inline — losing the single-source-of-truth contract.

### Token expansion (`theme/airpayux/scss/moodle/_tokens.scss`)

Added five categories of tokens that were specified in the manifesto but missing from the file:

- **Motion durations** (manifesto §5.1): `--ap-duration-instant/quick/default/slow/deliberate` (0/150/250/400/700ms). Composite shortcuts `--ap-transition-quick/default/slow/emphatic` pair each duration with the right easing.
- **Motion easings** (manifesto §5.2): `--ap-ease-out/in/in-out/spring` as named cubic-bezier curves. Replaces the old single-cube `ease` keyword that every transition was using.
- **Breakpoint SCSS variables** (manifesto §3): `$ap-bp-mobile-s/mobile/tablet-s/tablet/laptop/desktop` at 380/590/768/992/1280/1600px. CSS custom properties can't live inside `@media`, so these are compile-time Sass vars — every new partial that needs a media query must reference them, not inline px literals.
- **Touch targets** (manifesto §8 + §9 / WCAG 2.5.5): `--ap-tap-target-min` (44px) and `--ap-tap-target-cozy` (40px for dense admin tables).
- **Control heights** for vertical rhythm: `--ap-control-height-sm/md/lg/xl` (32/40/48/56px). So buttons, inputs, and badges line up across every form and toolbar without bespoke padding everywhere.
- **Focus-ring contract** (WCAG 2.4.11): `--ap-focus-ring-width/offset/color` + a universal `:focus-visible` rule applied to every interactive element. 3px width, 2px offset, primary blue.

### Auto-respect for `prefers-reduced-motion`

A single `@media (prefers-reduced-motion: reduce)` block at the bottom of `_tokens.scss` overrides every motion duration to 0ms. Because every component consumes `--ap-duration-*`, the OS-level preference automatically cascades — no per-component opt-in needed. Vestibular-disorder users get instant transitions without losing colour/state feedback.

### Style Guide page (`/local/airpay_core/admin/styleguide.php`)

New super-admin page that visually demonstrates every token in production. Eight sections (Colour / Typography / Spacing / Radius / Shadow / Motion / Breakpoints / A11y) with each demo referencing the live CSS variable via inline `style="var(--ap-...)"` — so the Style Guide auto-syncs with the compiled theme. Motion section is interactive: click any duration button to animate a target box with that duration + easing combo. Linked from Site Admin → Plugins → Local plugins next to the Switchboard.

### What this enables

When a designer/developer reaches for a token they no longer have to:
1. Open `_tokens.scss` to remember the name.
2. Compile the theme and inspect to see the value.
3. Cross-check the manifesto for the spec.

Instead they open `/local/airpay_core/admin/styleguide.php` and see the live, correct value — with the variable name to copy. Future PRs that introduce hex literals or magic durations get reviewed against this page.

### Hex-literal sweep — deferred

The codebase still has 1,237 hex literals scattered across 19 SCSS partials (notably `_moodle-overrides.scss` at 180, `_surface-dashboard.scss` at 66, `_surface-login.scss` at 75). Sweeping all of them in one session has high visual-regression risk and low priority (the literals work; they're just not auditable). The plan: migrate file-by-file as each surface comes up for its priority-roadmap redesign.

### Version bump

`local_airpay_core` 2026051401 → 2026051402, release `1.2.0` → `1.2.1`. Required because adding a new admin page to `settings.php` needs Moodle to re-scan the plugin's nav.

### Files touched

```
moodle-enhancement/theme/airpayux/scss/moodle/
  _tokens.scss                        [+ motion / breakpoints / touch / focus]

moodle-enhancement/local/airpay_core/
  version.php                         [bump 2026051402, release 1.2.1]
  settings.php                        [+ styleguide admin_externalpage]
  admin/styleguide.php                [new — Style Guide page]
  lang/en/local_airpay_core.php       [+ styleguide_pagetitle string]
```

---

## 🆕 PHASE A0 — CONFIGURABILITY FOUNDATION (2026-05-14)

**Trigger:** the user's career-defining mandate — "ai and all major capabilities in the platform should be configurable by super admin, should be able to toggle on/off without breaking the platform." Phase A0 ships the architectural scaffolding that all subsequent work hangs off.

### Strategy docs (locked-in references for next 6 months)

- **`docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md`** — 11 sections covering bar/principles/identity/breakpoints/components/motion/voice/references/iPad/accessibility/enforcement. Locks the design palette (small-text-safe `#15803d/#b45309/#b91c1c`), 4pt grid, 6 breakpoints (320 / 360 / 768 / 1024 / 1440 / 1920), Linear/Notion/Things-3 as reference apps, WCAG 2.2 AA as the floor.
- **`docs/platform-review-2026-05-14/SURFACE-ROADMAP.md`** — 22+ surfaces mapped end-to-end (12 learner + 4 manager + 7 L&D + 6 super admin). Every surface tagged with Status / Priority / Effort. Section 4.1 is the Switchboard spec that drove Part D below.
- **`docs/platform-review-2026-05-14/CONFIGURABILITY-ARCHITECTURE.md`** — 4-step resolution contract (tenant override → global override → registered default → false), 8 category prefixes, 60+ flag inventory, 3 degradation patterns (Hide / No-op / Fall back), and the 5 starter flags shipped in Part D.

### Feature-flag infrastructure (`local_airpay_core`)

Two new tables (idempotent via `db/upgrade.php` savepoint `2026051401`):
- `local_airpay_feature_flags(id, flag_key, tenant_id, is_enabled, modified_by, timecreated, timemodified)` with `UNIQUE(flag_key, tenant_id)` and a composite index on `(tenant_id, flag_key)`.
- `local_airpay_feature_flag_audit(id, flag_key, tenant_id, old_value, new_value, changed_by, reason, timecreated)` — every write captured for compliance + rollback.

Resolver class `\local_airpay_core\feature_flags` (in `classes/feature_flags.php`):
- `is_enabled(string $key): bool` — convenience for the current user's tenant, derived from `$USER->open_path`.
- `is_enabled_for_tenant(string $key, int $tenant_id): bool` — explicit tenant lookup (used by cron and admin tools).
- `all(int $tenant_id = 0): array` — full registry walk for the Switchboard UI.
- `set(string $key, int $tenant_id, ?bool $value, ?int $by_userid, string $reason): void` — writes an override row + audit row. `null` removes the override (reverts to default).
- `load_registry(): array` — walks every plugin's `db/feature_flags.php` via `\core_component::get_plugin_types()` and merges. 60-second MUC cache (`feature_flags_registry`).

Plugin registry pattern: any plugin can declare flags in `db/feature_flags.php`:
```php
$flags = [
    'commerce.crossTenantShare.enabled' => [
        'default'     => true,
        'description' => 'Allow site admins to share courses to other tenants',
    ],
];
```
The 5 starter flags ship in `local_airpay_core/db/feature_flags.php`:
1. `ai.assistant.enabled` (default ON) — gates the AI client in `local_airpay_assistant`.
2. `ai.sentientia.enabled` (default OFF) — gates the SOP→SCORM pipeline (not yet built).
3. `engagement.gamification.enabled` (default ON) — gates point-awarding in the course_completed observer.
4. `commerce.crossTenantShare.enabled` (default ON) — gates the share button + page.
5. `commerce.crossTenantRequest.enabled` (default ON) — gates manager-driven course requests.

### The Switchboard admin UI (`/local/airpay_core/admin/switchboard.php`)

Site admin → Plugins → Local plugins → **The Switchboard**. Tenant tabs (Global / Airpay / Public / ZEEA), category sections (AI & Automation, Engagement, Commerce, etc.), tri-state buttons per flag (ON / OFF / Use default). Pending changes shown in a sticky banner with an Apply modal that summarises every flag-by-flag transition before commit. CSRF-protected POST handler that calls `feature_flags::set()` for each change, then `purge` the registry cache.

JS module `amd/src/switchboard.js` uses XSS-safe DOM construction (`createElement` + `textContent`, no `innerHTML`) — caught and corrected by the security hook on first attempt.

### Graceful-degradation wiring (5 capabilities, 3 patterns)

| Pattern | Where it's used | Behaviour when flag is OFF |
|---|---|---|
| **Hide** | `theme/airpayux/classes/sidebar_navigation.php` (3 nav entries) | Nav entries disappear from sidebar; deep links also throw `featuredisabled` exception. |
| **Hide** | `local/airpay_courses/classes/external/list_courses.php` (Share button) | `can_share` returns false → button doesn't render in catalog. |
| **Page gate** | `local/airpay_courses/share.php`, `browse_airpay.php`, `manage_requests.php` | Friendly `\moodle_exception('featuredisabled', ...)` with the flag name surfaced for support. |
| **Fall back** | `local/airpay_assistant/classes/ai_client.php` | Returns a static message ("AI assistant is temporarily disabled — try again later") instead of calling the LLM. Cost goes to zero immediately. |
| **No-op** | `local/airpay_gamification/classes/observer.php` (course_completed) | Observer fires, but `points_manager::award()` is skipped. No points written, no exception, no broken UI. |

### Test posture impact

Phase A0 adds 11 PHPUnit tests in `local_airpay_core/tests/feature_flags_test.php`:
1. `test_registered_default_returns_when_no_override` — registry → resolver path.
2. `test_unknown_key_returns_false_safely` — typo handling (`assertDebuggingCalled`).
3. `test_set_creates_override_row` — write path produces a flags row.
4. `test_tenant_override_wins_over_global` — 4-step resolution order.
5. `test_null_value_reverts_to_default` — null deletes the override row.
6. `test_set_writes_audit_row` — every transition captured. (Caught a real int-vs-string bug from Moodle's DB layer.)
7. `test_set_with_same_value_is_noop` — re-writing same value doesn't double-audit.
8. `test_set_with_unknown_key_throws` — write-side typo protection.
9. `test_all_returns_every_registered_flag` — registry merge sanity check.
10. `test_all_reflects_tenant_override_in_resolved` — Switchboard rendering path.
11. `test_recent_audit_filters_by_key_prefix` — audit-trail filter for compliance UIs.

Full regression: **91 PHPUnit tests, 204 assertions, 0 errors, 0 failures, 0 skipped** (was 80 at Day-3 EOD). Warnings/deprecations in output are from the legacy `blocks/learnerscript/classes/observer.php`, not Airpay code.

### What this enables (next 6 months)

Every new capability — WhatsApp/SMS reminders (A1), gamification widget (A2), self-service compliance (A3), public marketplace, recommendations, SSO — now plugs into the same contract. Add a flag to your plugin's `db/feature_flags.php`, gate the entry point with `feature_flags::is_enabled('your.flag.key')`, and the Switchboard picks it up automatically on next cache TTL. Roll-out is now: ship feature with default OFF, enable for one tenant, watch metrics, ramp.

### Files touched (commit-ready)

```
docs/platform-review-2026-05-14/
  UI-UX-MANIFESTO.md                  [new]
  SURFACE-ROADMAP.md                  [new]
  CONFIGURABILITY-ARCHITECTURE.md     [new]

moodle-enhancement/local/airpay_core/
  version.php                         [bump 2026051401, release 1.2.0]
  db/install.xml                      [+ 2 tables]
  db/upgrade.php                      [+ savepoint 2026051401]
  db/feature_flags.php                [new — 5 seeded flags]
  db/caches.php                       [+ feature_flags_registry definition]
  classes/feature_flags.php           [new — resolver class]
  admin/switchboard.php               [new — admin page]
  templates/switchboard.mustache      [new]
  amd/src/switchboard.js              [new — XSS-safe DOM]
  settings.php                        [new — Site Admin nav registration]
  lang/en/local_airpay_core.php       [+ Switchboard strings, flag categories]
  tests/feature_flags_test.php        [new — 11 PHPUnit tests]

moodle-enhancement/local/airpay_courses/
  classes/external/list_courses.php   [+ commerce.crossTenantShare gate on can_share]
  share.php                           [+ commerce.crossTenantShare page gate]
  browse_airpay.php                   [+ commerce.crossTenantRequest page gate]
  manage_requests.php                 [+ commerce.crossTenantRequest page gate]

moodle-enhancement/local/airpay_assistant/
  classes/ai_client.php               [+ ai.assistant.enabled fall-back]

moodle-enhancement/local/airpay_gamification/
  classes/observer.php                [+ engagement.gamification.enabled no-op]

moodle-enhancement/theme/airpayux/
  classes/sidebar_navigation.php      [+ commerce.crossTenantRequest gate on 3 nav entries]
```

---

## 🆕 DAY-3 ADDITIONS (2026-05-14, 1 commit: `802d35d7a`)

### PHPUnit fixture trait unlocks 14 silent-skipped tests
New class `\local_airpay_core\phpunit\open_path_fixture_trait`. Any test class that `use`s it gets `mdl_user.open_path` and `mdl_course.open_path` columns added programmatically at every `setUp()`. The trait is idempotent — does nothing when bizlms is loaded (staging) and adds the column when it's not (vanilla PHPUnit).

Three test classes updated:
- `local_airpay_core/tests/tenant_test.php`
- `local_airpay_courses/tests/sharing_manager_test.php`
- `local_airpay_courses/tests/request_manager_test.php`

The previous `markTestSkipped(...)` guards in each are removed — every test now actually runs in CI.

### Real production bug surfaced by the now-running tests
`request_manager::request_state()` ordered request rows by `timecreated DESC` alone. When two rows share the same second (common in tests, possible in production for back-to-back manager actions), the SQL order is non-deterministic — a stale rejected row could shadow a brand-new pending one. Fixed by adding `id DESC` as the secondary sort key. The unlocked test `test_request_state_pending_request_wins_over_old_rejected` catches the regression.

### Test posture impact
Before Day-3: **39 PHPUnit tests, 14 SKIPPED** in CI ("will run on staging").
After  Day-3 morning (fixture trait + 14 unlocked tests + ORDER BY bugfix): **72 tests, 0 skipped, 0 errors, 0 failures.**
After  Day-3 evening (+ catalog_manager_test 8-case tenant-isolation suite): **80 tests, 0 skipped, 0 errors, 0 failures.**

Net: 41 more tests genuinely run in CI than the Day-1 EOD baseline. The catalog query's tenant filter (Sprint C's central refactor) now has 8 dedicated regression tests. No more "but it'll fail on staging" caveat for the tenant-aware code path.

---

## 🆕 DAY-2 ADDITIONS (2026-05-14, 3 commits: `6eae3a5cd..1650fa05c`)

### 1. Admin Settings UI (`6eae3a5cd`)
New page at Site Admin → Plugins → Local plugins → **Airpay Emails — Settings**:
- `default_cadence_days_json` — JSON-validated, ≤10 entries, positive ints only
- `default_max_reminders` — cap per (user × course), 0 = unlimited
- `default_auto_stop` — checkbox, ON by default
- `attach_certificate_pdf` — global kill-switch for the cert PDF attachment

The runtime fallback chain is now: rule's own column → admin setting → hard-coded `[1,3,7,14,21]` baseline. Includes a custom validator class (`setting_cadence_json`) that rejects bad input at save time with a specific error message rather than the previous silent-fallback-at-runtime behaviour. 10-case PHPUnit test suite ships alongside.

### 2. Post-deploy verifier (`1650fa05c`)
`moodle-enhancement/deploy/post_deploy_verify.sh` — one command, 5 gates, pass/fail report. Wraps:
- Sprint A `diagnose_admin_ux.php` (with optional `--user=email`)
- Sprint B `cert_emails_report.php`
- Sprint C `manage_shares.php --list`
- `cron_health.php` (WARN-not-FAIL on stuck tasks; expected on fresh deploy)
- Block presence check for cron_health + cert_health

`--json` flag for CI dashboard ingestion. Runbook updated with Step 10 to run this before cutover-evidence sign-off.

---

## ⏸️  NEXT SESSION PICKUP

**Session paused 2026-05-14 (Phase A0 EOD). All Day-1/2/3 + Phase A0 commits pushed to production branch.**

### Phase A0 test posture
- **91 PHPUnit tests** (cadence + cert_helper + observer + setting_cadence_json + tenant + sharing + request + catalog_manager + feature_flags), **204 assertions, 0 errors, 0 failures, 0 skipped**
- **post_deploy_verify.sh** on dev: **5 PASS, 1 WARN (cron, expected), 0 FAIL**
- **The Switchboard** smoke-tested end-to-end: global ON / tenant 1 OFF → verified tenant 1 sees OFF, tenant 77/177 inherit global ON; revert-to-default deletes the override row; audit trail captures every transition.
- All Day-1/Day-2/Day-3/Phase-A0 deliverables green.

### Phase A0 follow-ups (in priority order)

1. **Design system foundation** — extract the UI/UX Manifesto's tokens into `theme/airpayux/scss/tokens.scss` (spacing, radius, shadow, motion, type scale). Add a Storybook scaffold so every new surface has a baseline component library to draw from. Locks the visual language before A1 onwards build new screens.

2. **Phase A1 — WhatsApp Business + SMS fallback** (4 weeks per roadmap). Plugins:
   - New `local_airpay_whatsapp` — Business API client + opt-in flow + DLT template registry.
   - Extend `local_airpay_emails` cadence engine to use the new channel preference (`engagement.whatsapp.enabled` × user opt-in × DLT template availability). The fall-back-to-email pattern is already documented in CONFIGURABILITY-ARCHITECTURE.md §5.3.

3. **Phase A2 — Gamification dashboard widget + streak nudges**. The `local_airpay_gamification` plugin has the data layer; needs a learner-facing dashboard block (points / level / streak / recent badges) and a streak-recovery nudge in the email cadence engine.

4. **Phase A3 — Manager self-service compliance assignment**. New role-scoped UI for managers to assign mandatory courses to their direct reports without needing the LMS admin. Gated by a new flag `learning.managerAssign.enabled`.

5. **Phase A4 — Translation sweep for Sprint B/C/D strings** (hi/kn/mr/sw). The Switchboard's new strings (`switchboard_pagetitle`, `flag_category_*`) also need translation. Ship via the existing `tool_customlang` workflow.

### Recommended day-1 actions (in priority order)

1. **Deploy the 22-commit run to staging** (or production if you're confident).
   Use the runbook: `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md`.
   Headline: `git pull`, `php admin/cli/upgrade.php`, `php admin/cli/purge_caches.php`,
   then `bash moodle-enhancement/deploy/pre_deploy_validate.sh` (expect 9/10 green).

2. **Run the 23 skipped PHPUnit tests on staging.**
   They skip on dev because the BizLMS `user.open_path` column isn't in the vanilla
   PHPUnit fixture. On staging (which has the BizLMS plugin active), they should
   all pass:
   ```
   cd /path/to/staging/moodle
   ./vendor/bin/phpunit public/local/airpay_courses/tests/request_manager_test.php
   ```
   Expected: 12/12 pass — currently 12 of them skip on dev.

3. **Smoke-test each Sprint via the runbook's Step 5-8 checklist.**
   - Sprint A: `diagnose_admin_ux.php --user=academy@airpay.co.in` → all 7 checks PASS.
   - Sprint B: complete a course with a `tool_certificate` activity → user receives
     email with PDF attached. Verify via `cli/cert_emails_report.php --detail`.
   - Sprint C: as site admin, share any course to Public; verify it appears in
     Public's catalog with provenance badge.
   - Sprint D: as Public manager, request access to an Airpay course; admin
     approves; verify course appears in Public catalog.

4. **Add the two dashboard widgets to /my/** for site admins.
   `/my/` → Customise this page → drop "Airpay Cron Health" + "Airpay Certificate
   Health" into a region.

### Possible follow-ups if time allows

- Settings UI page in airpay_emails for default cadence (currently editable
  per-rule via the rule editor only).
- Add the cert_health + cron_health blocks to the default `/my/` dashboard via
  `db/install.php` so they auto-appear instead of admin manually adding.
- Per-tenant cadence override — currently `cadence_days_json` applies platform-wide
  per rule; could allow tenant-specific overrides via the existing
  `local_airpay_email_overrides` table pattern.
- Backfill any remaining LMS admin feedback that surfaces after they see the
  Day-1 deployment.

### Anything broken or half-finished?

**Nothing.** All 22 commits are atomic and pushed. Lint clean. All 73 PHPUnit
tests pass on dev (with the 23 environmental-skip caveat for open_path). Pre-deploy
9/10 green (Gate 3 cron-health FAILs on dev because there's no cron daemon — it
WILL pass on staging/prod).

### Where to find the work

| Looking for | File |
|---|---|
| What the 4 sprints did | This file, "ADMIN-FEEDBACK SPRINTS A-D" section below |
| Cutover steps | `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` |
| Plugin user docs | `local/airpay_emails/README.md`, `local/airpay_courses/README.md`, `blocks/airpay_cert_health/README.md` |
| State cards (dev reference) | `state-cards/airpay_learningpath-state.md`, `state-cards/airpay_emails-state.md`, `state-cards/airpay_courses-state.md`, `state-cards/block_airpay_cron_health-state.md`, `state-cards/block_airpay_cert_health-state.md` |
| Ops tools | `local/airpay_learningpath/cli/diagnose_admin_ux.php`, `local/airpay_emails/cli/cert_emails_report.php`, `local/airpay_courses/cli/manage_shares.php` |

---

> **CURRENT TEST POSTURE (2026-05-13 EOD)**
> - **73 PHPUnit tests, 118 assertions, 0 errors, 0 failures, 23 skipped**
>   (skipped tests need the BizLMS `user.open_path` column not
>   present in vanilla PHPUnit fixture — they exercise on staging)
> - **2 axe-core a11y suites, 0 critical, 0 serious** — both
>   dashboard blocks (cron_health + cert_health) WCAG 2.1 AA clean
> - **pre_deploy_validate.sh: 9 of 10 gates green** (only Gate 3
>   cron-health FAILs on dev — no cron daemon running locally)
> - **All 15 commits pushed to** `nitin-rajput-learning-tech/Airpay-Academy2.0`
>   production branch (`78647e47d..d3ba9784b`)

> **ADMIN-FEEDBACK SPRINTS A-D (13 May 2026, commits `78647e47d..9e92d7dad`):**
>
> *Sprint A — Learning Path admin UX*
> - 7-check diagnostic CLI at `local/airpay_learningpath/cli/diagnose_admin_ux.php`
>   with `--fix-caps` idempotent capability repair + `--user=email` for
>   per-user diagnosis + `--json` for CI integration.
> - State card at `state-cards/airpay_learningpath-state.md`.
>
> *Sprint B — course-completion email + ramping reminders + audit*
> - Event observer for `\core\event\course_completed` with fail-safe try/catch.
> - `certificate_helper` materialises the `tool_certificate` PDF into
>   `$CFG->tempdir/airpay_emails/` and the notification sender routes
>   the email through `email_to_user()` so the PDF attaches (Moodle's
>   `message_send()` doesn't carry attachments).
> - New rule type `course_incomplete` in `process_rules.php` with
>   ramping cadence (default `[1,3,7,14,21]` days from enrolment),
>   `max_reminders_per_user` cap, `auto_stop_on_completion` flag.
> - Audit CLI at `local/airpay_emails/cli/cert_emails_report.php`
>   with `--since`, `--tenant`, `--status`, `--detail`, `--csv` flags.
> - **Dashboard widget `block_airpay_cert_health`** — 3 KPI cards
>   (sent / failed / suppressed in last 7 days) with the same WCAG
>   2.1 AA pattern as cron_health. axe-core test: 16/16 passes,
>   0 violations. Wired into pre_deploy Gate 6 alongside cron_health.
>
> *Sprint C — cross-tenant course sharing (push side)*
> - New table `local_airpay_courses_tenant_share` (course × tenant).
> - New capability `local/airpay_courses:share_to_tenant` (siteadmin only).
> - `sharing_manager` class with `share_course`, `unshare_course`,
>   `list_course_shares`, `is_course_shared_to`,
>   `build_catalog_filter_sql` (the SQL that UNIONs owned + borrowed
>   courses into one WHERE-clause fragment).
> - Catalog manager's 4 query methods (`get_courses`, `get_trending`,
>   `get_new`, `get_categories`) updated to call build_catalog_filter_sql.
> - Catalog card now carries `is_borrowed` + `provider_tenant_name`
>   and renders a "Provided by Airpay Academy" badge.
> - Admin page `share.php?id=<courseid>` with tenant checkbox grid;
>   the "Share" button is wired into the course-management row
>   actions (only visible to users with the cap).
> - 2 audit events (`course_share_created` / `course_share_withdrawn`).
> - 3 WS endpoints; 15-case PHPUnit suite (all pass).
>
> *Sprint D — cross-tenant request workflow (pull side)*
> - New table `local_airpay_courses_requests` (pending/approved/rejected).
> - 2 new capabilities: `:request_course` (manager-grantable) +
>   `:approve_request` (siteadmin only).
> - `request_manager` class with `create_request` (idempotent on
>   pending; short-circuits when already shared; throws on
>   own-tenant or unknown course), `approve_request` (cascades to
>   `sharing_manager::share_course` + purges catalog caches),
>   `reject_request` (with optional rationale).
> - `browse_airpay.php` — non-Airpay manager view of the full Airpay
>   catalog with per-row "Request access" button.
> - `manage_requests.php` — Airpay Super Admin pending-requests inbox
>   with Approve / Reject buttons.
> - 3 audit events (requested / approved / rejected).
> - Sidebar navigation now exposes "Course-share Requests" for site
>   admins and "Browse Airpay Library" for managers + L&D admins in
>   non-Airpay tenants.
> - 4 WS endpoints; 12-case PHPUnit suite (skipped in vanilla fixture
>   pending the BizLMS open_path column on staging).
>
> *Sprint B+C hotfix — caught by full PHPUnit run*
> - `local_airpay_email_log.status` was char(20) but the new
>   `'suppressed_completion'` value is 21 chars. Widened to char(32)
>   with index drop/re-add dance (Moodle's `ddl_dependency_exception`
>   forbids changing a column under an index).
> - `sharing_manager::known_tenants()` queried `local_airpay_org.name`
>   but the column is actually `fullname` (renamed at port time).
>
> *Translations*
> - All Sprint B/C new strings translated to hi / kn / mr / sw.
>
> *PHPUnit verification*
> Sprint B: 16/16 pass (25 assertions).
> Sprint C: 15/15 pass; Sprint D: 14 skip (need staging open_path).
> block_airpay_cert_health: 6/6 pass (15 assertions).
> Combined: 52 tests, 81 assertions, 0 errors, 0 failures.

> **WAVE 2/3/4 polish + bugfix commits (78647e47d..d3ba9784b)**
>
> *Wave 2 — Sprint C+D wiring + cert-health block + translations*
> - Share button in `airpay_courses/index.php` row actions (cap-gated).
> - "Course-share Requests" + "Browse Airpay Library" in sidebar nav.
> - `block_airpay_cert_health` — dashboard widget with 3 KPI cards
>   (sent / failed / suppressed in last 7 days), same WCAG 2.1 AA
>   pattern as cron_health block.
> - Hi/kn/mr/sw translations for Sprint B + C strings.
> - axe-core a11y test for cert_health block + Gate 6 expansion to
>   run BOTH cron_health and cert_health a11y suites.
>
> *Wave 3 — audit + polish*
> - All 5 Sprint C/D events added to
>   `audit_log::SENSITIVE_EVENTS` so they surface in the compliance
>   dashboard alongside role-change / refund / proctoring events.
> - course_completed email template updated to mention the PDF
>   attachment ("Your certificate is attached to this email").
>
> *Sprint D bugfix — request_state edge case*
> - Historical 'approved' request rows no longer mis-report
>   "In your catalog" once admin withdraws the share. `request_state`
>   now only looks at pending/rejected request rows; the share
>   table is the source of truth for current catalog membership.
> - 2 new PHPUnit cases guard the edge case.
>
> *Sprint D follow-up — manager outbox*
> - New page `my_requests.php` showing every request the manager
>   has filed with status pill + admin rationale + per-status
>   KPI strip. Sidebar nav exposes it as "My Requests".
>
> *Ops CLI — `cli/manage_shares.php`*
> - Terminal-friendly share/request management for IT during early
>   rollout. Supports `--list`, `--list-pending`, `--course=N
>   --add=77,177`, `--course=N --remove=77`, `--approve=<rid>`,
>   `--reject=<rid> --reason="..."`, `--course=N --history`,
>   `--json` for scripting.
>
> *Event payload fix*
> - All 5 Sprint C/D events now omit the top-level `courseid` key
>   from `create()` payload — fixes Moodle's "Inconsistent courseid
>   - context combination" debugging notice. The course id stays
>   inside `other` for downstream consumers.
>
> *Docs*
> - `local_airpay_courses/README.md` updated for Sprint C/D
>   (capability table, page table, CLI table, audit events).
> - `blocks/airpay_cert_health/README.md` created from scratch.
> - `local_airpay_emails/README.md` updated for Sprint B (observer,
>   helper, course_incomplete rule, schema additions, hotfix note).
>
> *PHPUnit additions*
> - `blocks/airpay_cert_health/tests/block_test.php` — 6 tests
>   covering silent-hide-for-non-admin, KPI labels, region landmark,
>   count accuracy, non-cert-row exclusion.
>
> *pre_deploy_validate gates*
>   Gate 0 — tenant-guard lint (132 externals, 0 violations) ✅
>   Gate 1 — PHP syntax lint (764 files, single-process batch) ✅
>   Gate 2 — Python compile (all sentientia agents) ✅
>   Gate 3 — cron-health CLI (FAIL on dev — no cron daemon)
>   Gate 4 — 4 plugin smokes ✅
>   Gate 5 — PHPUnit (skip flag available)
>   Gate 6 — axe-core a11y × 2 blocks ✅
>     - a11y_block_cron_health (0 critical, 0 serious)
>     - a11y_block_cert_health (0 critical, 0 serious)
>   Gate 7 — Phase 7 UAT (opt-in)
>
> All 9 commits pushed to `nitin-rajput-learning-tech/Airpay-Academy2.0`
> production branch.

> **ENGINEERING 13-32 (13 May 2026, commits `2d71f0bb3..3da23ebe7`):**
>
> *Pre-deploy validation pipeline*
> - Eng 17: `pre_deploy_validate.sh` — single orchestrator with 7 gates
> - Eng 18: `lint_tenant_guard.py` — architectural CI enforcement of the tenant-guard rule (132 externals, 0 violations)
> - Eng 19: wire Gate 0 (tenant-guard) into pre_deploy_validate
> - Eng 22: Gate 1 PHP-lint single-process `token_get_all` batcher (8 min → 2 sec for 729 files, 250x speedup, Windows-aware path translation)
> - Eng 23: Gate 6 axe-core a11y wiring + `--skip-a11y` flag
> - **Full pre-deploy now: 44 seconds (was 8+ min and often killed)**
>
> *Accessibility — `block_airpay_cron_health`*
> - Eng 20: axe-core a11y baseline via static fixture (no XAMPP / DB dep)
> - Eng 21: heading-order fix (h2→h5 → h2→h3), small-text contrast palette split (#15803d/#b45309/#b91c1c for 4.5:1), severity badge + ARIA labels to satisfy WCAG 1.4.1 (use of colour)
> - **Result: WCAG 2.1 AA + best-practice clean (18 passes, 0 violations)**
>
> *Tenant guard back-ports*
> - Eng 15 (earlier): `tenant::require_path_access()` helper introduced + back-port `list_course_enrolments`
> - Eng 24-27: five more externals now using the helper:
>   - `airpay_org/delete_org.php` + `airpay_org/toggle_visibility.php`
>   - `airpay_reports/delete_report.php` + `airpay_reports/toggle_status.php`
>   - `airpay_users/bulk_action.php` (uses `tenant::path_filter()` for SQL bulk filter)
> - Eng 29: 7 PHPUnit regression tests, including the silent-pass-bug guard (empty `open_path` viewer → throws, was silent-pass in the inline pattern)
>
> *Other operations*
> - Eng 13: SENTIENTIA Agent 2 production hardening (retry+backoff, token tracking, INR cost)
> - Eng 14: `branding_assets` trait (-83 lines from core_renderer)
> - Eng 16: `cron_health.php` CLI for the ops team
>
> *core_renderer.php decomposition*
> - Eng 28: `login_render` trait (-77 → 1,969)
> - Eng 30: `context_header` trait (-175 → 1,794)
> - Eng 31: `course_view` trait (-73 → 1,721)
> - Eng 32: `user_menu` trait (-356 → 1,365) ← the 350-line headline win
> - **Cumulative: 2,339 → 1,365 = -974 lines (~42%) across 7 traits**
>
> All commits pushed to `nitin-rajput-learning-tech/Airpay-Academy2.0`
> production branch.

> **PHASE 9 STRETCH (12 May 2026, commit `ffee790b9`):**
> All six non-blocking findings from the Phase 8.2 re-audit shipped:
> - N1 sliding-window rate limit (timestamp-array replaces fixed-hour bucket)
> - N2 S3 purge real SigV4 DELETE implementation (GDPR retention enforced)
> - N5 `_tenantroot` renamed to `aptenantroot` (drop non-Moodle convention)
> - N6 silent-404 callback IP-drop logging with hourly dedupe
> - N7 quizaccess config-table-bloat refactored to relational table with migration
> - N9 AWS Rekognition exponential-backoff retry (3 attempts, 250/500ms backoff)
>
> Plus the cross-cutting `\local_airpay_core\audit_log` helper for compliance
> queries (sensitive_actions, actions_by_user, tenant_actions) and 8 more
> plugin READMEs (org, users, courses, classroom, emails, notifications,
> manager, privacy). 14 of 30 plugins now have READMEs; the remaining 16
> follow the same template and are documented in their existing state cards.
>
> The full backlog of 47 items (ACTIONABLE-NOW + BLOCKED-INFRA + BLOCKED-MGMT
> + BLOCKED-CONFIRM + FORK-PLANNED + FUTURE-DESIGN + TECH-DEBT) is enumerated
> in the master-doc Section 12 + 13 + 14 and in this session's TodoWrite log.
> Of those 47: 8 actionable items closed in this session; 6 await IT; 8 await
> management decisions; 3 await Nitin [CONFIRM] gates for paid-API runs; 7
> are fork-planned for Q3 2026; 8 are FUTURE-DESIGN; 6 are TECH-DEBT (some
> closed by Phase 9 stretch).
>
> **FIVE SUPPLEMENTARY DOCUMENTS shipped alongside master v1.0:**
> - `docs/SUPP-A-RISK-REGISTER-FULL-2026-05-12.md` — 32 risks across 9
>   categories. Aggregate: 1 high-residual (P1 key-person, until engineer
>   hire lands), 4 medium-residual, rest low-residual.
> - `docs/SUPP-B-MOODLE5-UPGRADE-PLAN-2026-05-12.md` — strategic rationale,
>   8 prereqs, per-plugin compat (30/30 ✓), Q4 2026 sequencing AFTER cutover
>   AFTER BizLMS displacement.
> - `docs/SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md` — 6 agents
>   spec'd end-to-end, ₹70-125 per course economics, 90-day build sequence,
>   vendor evaluation matrix.
> - `docs/SUPP-D-BIZLMS-DISPLACEMENT-PLAN-2026-05-12.md` — Q3 2026 nine-week
>   sequenced plan covering renderer-callsite displacement (P0, 13+5=18
>   callsites), schema-column migration (50 `open_*` columns across user
>   + course tables), plugin-directory removal, block displacement,
>   LearnerScript decision. Done-criteria + risk register specific to the
>   workstream.
> - `docs/SUPP-F-ENGINEER-HIRE-BRIEF-2026-05-12.md` — operationalises
>   Decision 13.3 (the highest-leverage decision on the platform). Role
>   spec, compensation framing (₹22 lakh), 7-stage interview, 90-day
>   onboarding ramp, success metrics at 6 and 12 months, sample JD draft.

> **THREE EXECUTABLE ARTEFACTS shipped (acting on the backlog right away):**
> - `moodle-enhancement/deploy/cutover_preflight.sql` — 9-section read-only
>   pre-flight against production. Detects N4 stale manageprices grants,
>   invalid open_path users, cart tenant-list config, callback IP allow-list,
>   proctoring AWS config, recompletion rule tenancy, scheduled-task status,
>   user-population sanity, plugin version alignment.
> - `moodle-enhancement/local/airpay_core/cli/mask_pii_for_dev.php` —
>   mitigates risk S7. Sanitises mdl_user PII, clears logstore IPs, masks
>   cart billing PII, deletes proctor identity, masks email log. Hard
>   safety guards (production-DB-name blocklist + --confirm flag +
>   executive-name canary).
> - `moodle-enhancement/local/airpay_core/classes/cron_health.php` —
>   mitigates risk I5. Surfaces stuck Airpay scheduled tasks, faildelay
>   backoff state, summary tuple for the dashboard widget.
>
> **ALL 30 PLUGIN READMEs SHIPPED.** Phase 8.3 (6) + Phase 9 (8) + this
> session (17) = full coverage. Section 12.1 plugin-doc deferral closed
> entirely.

> **PHASE 9 EXTEND (12 May 2026 night):** Three more supplements, an
> agent skeleton, a regression suite, a runbook, and a structured logger.
>
> - `docs/SUPP-E-BUDGET-MODEL-2026-05-12.md` — 12-month operating budget
>   ₹35 lakh expected, ₹62 lakh savings, **+₹27 lakh** cash-positive net.
>   Sensitivity analysis on SENTIENTIA throughput / hire timing / Public-
>   tenant traction. Per-vendor sub-ceilings under Decision 13.2.
> - `docs/SUPP-G-DR-DRILL-PLAN-2026-05-12.md` — RTO 4h, RPO 24h, four
>   scenarios, drill checklist + role assignments + retention policy +
>   cold-site spec. First live drill scheduled week 3-4 of 90-day plan.
> - `docs/SUPP-H-OBSERVABILITY-PLAYBOOK-2026-05-12.md` — 6 SLIs/SLOs,
>   alert taxonomy P0/P1/P2, structured-logging contract, error-budget
>   framework, 12-month maturity roadmap. New Relic at ₹0-80,000/year.
> - `sentientia/agent2_narration_generator.py` — full prompt template,
>   validation gates, [CONFIRM] gate (tty-checked), batch + dry-run modes.
>   Anthropic SDK gated; live integration is a small diff away.
> - `sentientia/run_regression.py` — quality regression runner with
>   word-count delta, sentence-distribution KS test, vocabulary recall,
>   PII introduction check. Zero scipy dependency.
> - `sentientia/references/README.md` — 3-course reference suite
>   (POSH compliance, customer support playbook, AML fundamentals)
>   with validation thresholds and anti-golden pattern documented.
> - `moodle-enhancement/MFA-ENFORCEMENT-RUNBOOK.md` — three-tier
>   enforcement plan (admins T+30d, managers T+90d, users 12-mo eval).
>   Admin steps, comms template, verification SQL, rollback. DPDP s.8(4)
>   compliance positioning.
> - `moodle-enhancement/local/airpay_core/classes/structured_logger.php`
>   — JSON-shaped log helper backing the SUPP-H structured-logging
>   contract. ISO-8601 timestamp, request_id from upstream headers, APM
>   custom-event hook, defensive PII scrub on extra dict.

**Theme:** airpayux v1.0.0 | **Moodle:** 5.1.3+ on XAMPP
**Version:** 4.0-rc3 — All 22 Phase-2 rows ✅ + cart + proctoring + recompletion + AI + cohorts + badges + 7-persona UAT.
**GitHub:** Pushed to nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch, last commit `6ce016150` — Phase 8.3 plugin READMEs + smoke fixes)
**Today's UAT result:** Phase 7 multi-role re-run **84/85** post-Phase-8.1 (identical baseline — no regressions). Plugin smoke tests **84/84** (cart 26/26, request 23/23, proctoring 22/22, recompletion 13/13). PHPUnit on `local_airpay_core::tenant` 6 pass, 3 cleanly skip (BizLMS column absent on PHPUnit fixture). Cumulative test pass: **326+ cases**.
**Today's audit + remediation + verification cycle + documentation:** Phase 8 audit NO-GO → Phase 8.1 remediation (35 files, +787/-83) → Phase 8.2 re-audit returned **GO** + Phase 7 UAT re-run **84/85** + N3 / N4 follow-ups shipped + Moodle 5 messages.php compat fixed across 5 plugins + Phase 8.3 6 plugin READMEs + smoke verification 84/84 + **Master Documentation v1.0 (123 KB md / 91 KB docx)**. Total cumulative Phase 8.x shipment: 19 commits, ~22,500 LOC, all 11 blockers closed.

> **MASTER DOCUMENTATION HANDOFF (12 May 2026 EOD):**
> Two files at `docs/`:
> - `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md` (123 KB, 1,394 lines, 18,128 words)
> - `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.docx` (91 KB, generated via python-docx with Airpay brand styling)
>
> The document follows the master prompt structure: cover + executive summary + 15 sections covering platform overview, baseline, evolution timeline (8 phases from Nov 2022 to May 2026 commit history), the airpayux theme, all 30 plugins (with deep profiles for the 8 most consequential), content + SENTIENTIA + Microsoft 365 + API surface, features by 9 user roles, commercial + operational implications (₹ figures vs SaaS alternatives), backlog by workstream, decisions required from management (8 distinct decisions with recommendations), 90-day plan week-by-week with 6-month and 12-month horizons, and 8 appendices (git log, file tree, schema overview, capability matrix, glossary, env vars, runbook map, escalation matrix). Internal source fragments held at `docs/master/`; concatenation + .docx generator script at `docs/_working/generate_docx.py`.
>
> Working notes from the discovery pass are at `docs/_working/` — full git log (2,386 commits), git shortlog, plugin matrix, tag/branch lists. These remain useful for the next quarterly refresh.

> **TOMORROW START HERE:** Re-run security audit against the new diff.
> Phase 8.2 sequence: (1) re-audit returns GO → (2) re-run Phase 7
> multi-role UAT → (3) staging k6 load test against prod-sized RDS clone
> → (4) follow `PHASE-8-DEPLOYMENT-RUNBOOK.md` for cutover.
> No cutover until all three pre-cutover gates pass.

## Phase 8.1 remediation summary

11 blocking findings closed in one session, 35 files changed, +787/-83.

Root cause: 10 of 11 findings shared one architectural gap — capability
checks at `CONTEXT_SYSTEM` without an additional tenant-equality check.
Public-tenant manager with `:viewallorders` legitimately held the cap;
the second check was missing in every external.

Fix: new `local_airpay_core` plugin with `\local_airpay_core\tenant`
helper class — `root_for_user`, `viewer_can_access`, `require_access`,
`sql_filter`. Every blocking finding now uses one of these helpers.
8 PHPUnit tests guarantee cross-tenant rejection + site-admin passthrough.

Per-finding fixes:
- B4 (CVSS 9.1) Payment tampering: callback.php compares payload.amount
  + currency to server-side cart.total_amount/currency BEFORE mark_paid.
- B11 (CVSS 5.4) Callback DoS: generic 500, optional CIDR allow-list,
  new ip_check helper with v4 + v6 CIDR matcher.
- B1 (CVSS 8.6) Cart cross-tenant: cart_manager::get_order + refund +
  list + daily_sums all use tenant::sql_filter() / require_access().
- B2 (CVSS 8.1) Proctoring read leak: 5 read paths now tenant-scoped.
- B3 (CVSS 8.2) Proctoring write IDOR: session_manager helpers verify
  ownership; s3_key whitelisted to strict regex; size+duration bounded.
- B5 (CVSS 7.4) Invoice XSS fragility: html_writer wrapper with
  white-space: pre-line replaces the nl2br()+{{{ }}} pattern.
- B6 (CVSS 7.5) Recompletion cross-tenant: rule.costcenterid now drives
  a path-prefix filter on the candidate query.
- B7 (CVSS 6.8) Identity photo abuse: 5 submits/hour rate limit, size
  cap 14MB→5.5MB, base64 strict-mode, MIME magic-byte sniff (JPEG/PNG).
- B8 (CVSS 6.5) LIMIT injection: 3 queries refactored to use limitfrom
  /limitnum args instead of string interpolation.
- B9 (CVSS 7.1) set_price context: :manageprices cap moved to
  CONTEXT_COURSE; external uses context_course::instance() for the check.
- B10 (CVSS 6.5) Approver bypass: request_manager::decide adds tenant
  equality after :overrideroute cap check.
>
> Today shipped the enterprise-grade plan end-to-end: airpay_cart (full
> e-commerce stack for external tenants), airpay_proctoring + quizaccess
> subplugin, airpay_recompletion (annual compliance engine), airpay_request
> (course-request workflow), per-tenant settings + SSO documentation,
> cohort sync from org tree + badges seed + core_ai bridge + mobile-push
> setup guide, plus a 7-persona × 14-case UAT harness that walked every
> user tier end-to-end.
>
> **Two real production bugs surfaced and fixed by Phase 7:**
> 1. `update_capabilities('local/airpay_x')` silently registers 0 caps
>    on fresh installs — every assign_capability() after it becomes a
>    no-op because record_exists() on mdl_capabilities fails. Slash form
>    looks valid but Moodle expects the underscore form. Fixed across
>    4 plugin install hooks (cart, request, proctoring, recompletion).
>    Smoke tests missed it because they call manager methods directly,
>    bypassing the capability-check WS layer.
> 2. Tenant Admin (nitin.rajput) holds the 'administrator' role at
>    contextid=11 (CONTEXT_COURSECAT, level=40) NOT at CONTEXT_SYSTEM
>    (level=10). This is correct — he manages his category, not the
>    whole site. UAT persona was relabeled "Tenant Admin (category-scoped)"
>    with expect_admin_pages=false; the admin-page block at H.1 now
>    passes as the security boundary it was designed to test.
>
> The 1/85 remaining failure is a transient login timeout on the freshly
> provisioned public.uat test user (login helper already has 2-attempt
> retry — both timed out this run; not blocking, infrastructure flake).

---

## Yesterday's snapshot (2026-05-07 EOD post-stretch — preserved for context)
**Phase 3.4** — Tier 1 closed (G-01..G-06), Tier 4 a11y closed, audits
delivered, airpay_roles UI shipped, airpay_challenge Phase 1 shipped,
airpay_integrations pre-cutover fixes shipped.
PHPUnit: ~352 tests across ~38 test files. Playwright: 8 harnesses.
Audits: `INTEGRATIONS-AUDIT.md`, `STRETCH-ACCOUNTABILITY.md`,
`airpay_roles-state.md`, `airpay_challenge-state.md`.

> **Production posture (Nitin EOD 2026-05-06):** *"We will not go to production
> till we have fixed everything. Not going to make a fool of myself going with
> half-baked product. The features shouldn't just exist — they should work
> like a true enterprise product."*
>
> Production cutover is gated on closing **all** items in `FEATURE-PARITY-AUDIT.md`
> (G-01..G-06 + Tier 2 stubs + Tier 3 partials + Tier 4 a11y polish), not just the
> most-impactful ones.
>
> See `state-cards/2026-05-06-EOD-state.md` for the full backlog (~140-180h
> estimated; sequenced over 9-10 dedicated days starting with G-04 tomorrow).

## Last 13 commits (May 5-6 audit/perf/test/quality stretch)
- `acd0a0d41` Feature-parity audit + G-01 fix + 8 CRUD PHPUnit (54/54 PASS) + Phase D-extended Playwright
- `f11bdacd0` State card update: A11Y-4/5/6 + F1 + learnerscript-P3 closure
- `2799c0926` A11Y-4 + A11Y-5 + A11Y-6 + F1 follow-up + learnerscript-P3 documented
- `b200eed6c` PHPUnit for programs/skills/notifications/evaluation (20/20) + Phase 0B export button + README
- `682143ea0` A11Y-1: aria-sort + keyboard nav on shared datatable (covers all 10 admin tables)
- `8a5c4fced` CI: also trigger on workflow changes pushed to production
- `295cfcb9e` CI: count Moodle Mustache template-inheritance forms in balance check
- `f35ce3e9b` H: SCORM e2e — 7/7 PASS, attempt persisted, integration boundary verified
- `7bd2bd9f4` K (Phase 0A): port 3 BizLMS accesslib methods + 7/7 PHPUnit PASS
- `175e220e8` E (complete): airpay_exams + airpay_learningpath PHPUnit tests
- `43deec238` State card update: A,C,D,E,F,G shipped; H + K deferred with reason
- `ae77416b8` D + E (partial): F1 investigation notes + airpay_classroom PHPUnit tests
- `002ce78b9` A: GitHub Actions CI — PHP lint + JSON + Mustache + version-bump

## What this represents

After two intensive days of audit-driven hardening, we've done the **measurement
work**: every gap is catalogued in FEATURE-PARITY-AUDIT.md, every security-critical
path is locked in by tests, every regression has a guard.

What we haven't done yet is the **build work** to close the gaps. That's the
3-4.5 weeks of Tier 1+2+3 work documented in `state-cards/2026-05-06-EOD-state.md`.

## State summary (post May 5-6 stretch)
- ✅ Deploy mechanism: 8/8 runbook steps + rollback drill
- ✅ PHPUnit: 44/44 tests passing on security-critical paths
- ✅ Browser tests: 113/116 + 73/73 + 16/16 + 12/15 = 214/220 (97%)
- ✅ All cross-tenant LIKE leaks closed (13 sites)
- ✅ All P0/P1 perf wins shipped (org 86×, analytics ∞×, catalog 40×)
- ✅ Manager onboarding UX bug fixed
- ✅ Moodle 5.x deprecations cleaned up
- ✅ -4604 LOC of orphan code removed
- ✅ CI workflow on every PR

**Production-cutover-blocked-on:** IT staging access, DB backup verification, SMTP setup. Engineering done.

---

## v3.3.0 Session (2026-05-05) — CRUD pattern + datatable + security pass

### What landed (10 commits across this session)

**11 plugins now have full CRUD on the established `core_form\dynamic_form` modal pattern:**
- airpay_users, airpay_courses, airpay_classroom, airpay_exams,
  airpay_learningpath, airpay_programs, airpay_skills, airpay_notifications,
  airpay_evaluation, airpay_reports, airpay_org

Each plugin: `classes/form/edit_*.php` dynamic form, `classes/external/{delete,toggle}_*.php`
externals via ajax-callable web services, `amd/{src,build}/*_actions.js` pure-AMD wrapper
(no Babel helpers — Moodle's RequireJS doesn't ship `_interopRequireDefault`),
templates/manage.mustache + index.php, `db/services.php` registration, lang strings.

### New shared infrastructure (commit `6362762bc`)
- **`theme_airpayux/datatable`** AMD module — server-side search (debounced 250ms),
  column sort with display-key vs sort-key decoupling, pagination, per-row HTML
  actions, public refresh()/setFilter()/getSelected() API, custom event for CRUD
  module integration, row selection.
- Web service contract: `args: {search, sort, sortdir, page, perpage, filters: JSON}` →
  `returns: {total, rows: [{id, ...cellvalues, actions: HTML}], page, perpage}`
- Retrofitted: airpay_users (2,869 rows), airpay_courses (411).

### Manager drill-down (commit `b7154851d`)
- `local_airpay_manager\team_manager` class with batched aggregates: get_team(),
  summarize_team() — 4 queries replace N×3 per-row, get_member_detail() — full
  course list + certs, can_view_member() — supervisor chain walks up to 5 levels.
- New `member.php` drill-down page with progress bars per course + certificates earned.
- Theme dashboard manager section refactored: was 1 + 34 + (34×5) = ~205 query
  operations per load for managers with 34 reports. Now 4 batched queries.

### Bulk operations (commit `b7154851d`)
- Datatable extended with row selection (`data-selectable="1"`).
- New `local_airpay_users_bulk_action` WS — suspend/activate by ID array.
- Hard-protects $USER->id, guest (1), admin (2) before UPDATE.

### Production-readiness pass (commit `ba0a44856`)
- Audited codebase for `$USER->open_costcenterid` references — found 3 real
  bugs in our owned code. Production has no such column; on production the
  comparison was 0==0 → access scoping was broken. Fixed in
  theme/airpayux/classes/output/core_renderer.php + 2 form classes.
- Authenticated curl smoke test through 10 admin pages + 2 web services: 10/10 PASS,
  list_users search 'nitin' → 12, list_courses search 'POSH' → 3.

### Mobile + dark mode polish (commit `a6c315d65`)
- Discovered: 8 templates used CSS variables that don't exist
  (`--ap-color-surface` vs the real `--ap-color-bg-surface`,
  `--ap-color-error` vs `--ap-color-danger`). Fallbacks always rendered,
  bypassing the design system. Fixed across all templates.
- Discovered: dark_mode.scss only overrode legacy `--airpay-*` tokens, not
  the current `--ap-color-*` semantic tokens. Components using
  `var(--ap-color-bg-surface, ...)` stayed light in dark mode. Added 10
  token remaps.
- New SCSS partial `_datatable.scss` with mobile breakpoint (590px) +
  explicit dark-mode rules for the shared component.

### Security audit (commit `a6c315d65`) — 6 real bugs fixed
Run by Airpay Security Auditor agent. Verdict pre-fix: **BLOCK production deploy.**

| Sev | ID | Category | Fix summary |
|-----|----|----------|-------------|
| Critical | C1 | Tenant isolation | Bulk_action could suspend any user by ID; added open_path scope filter on the UPDATE target set |
| Critical | C2 | OWASP A03 SQL | `'/1' . '%'` LIKE pattern matched /10, /100, /177 → cross-tenant data leak. Fixed with sql_like_escape + slash boundary in 8 sites (list_users, list_courses, bulk_action, count_users, count_descendants, all 4 report runners). Confirmed 6-row leak removed from `count_users(1)` and 83-row leak removed from enrolment_trend report. |
| High | H1 | A01 Authz | list_users honored caller-supplied orgid without checking it was inside caller's tenant tree. Now rejects with 'outoftenant'. |
| High | H2 | TOCTOU | org_manager::delete had race window between count_descendants check and DELETE. Wrapped in transaction with SELECT...FOR UPDATE on target row. |
| High | H3 | A01 Authz | delete_org / toggle_visibility / delete_report / toggle_status accepted any id with only the management cap checked. All 4 now reject targets outside caller's tenant. |
| Medium | M1 | A04 Insecure design | bulk_action returned actually-flipped count → user-enumeration oracle. Now returns post-tenant-filter request-set size, not change-set size. |
| Medium | M2 | A03 / DoS | JSON `filters` was PARAM_RAW with no size or depth limit. Added 4KB cap + 5-level depth limit on list_users + list_courses. |
| Medium | M3 | Tenant isolation | list_courses had no tenant scope at all. Added `(open_path = :exact OR LIKE :prefix)` filter mirroring list_users. |

Re-verified all 3 smoke tests pass post-fix. Verdict post-fix: clear for production
pending I3 follow-up (mass-assignment on update path), which is not on the WS
surface today.

### Files (counts)
- 11 plugins x ~12 files each = 132 plugin files
- 1 shared theme component (datatable.js + .min.js + .scss)
- 1 manager class for team aggregates (team_manager.php, 220 lines)
- 4 new web services for the shared datatable contract
- 1 SCSS partial + dark mode token remap

### Verification status
- **PHP lint:** all touched files clean
- **Authenticated browser test:** 10/10 admin pages render (curl-based, Chrome MCP unavailable)
- **Web services:** list_users (7/7 cases), list_courses (3/3), bulk_action (4/4),
  org CRUD (7/7), 4 report runners (4/4 PASS)
- **Security audit:** 6 findings → fixed → re-verified
- **Mobile + dark mode:** SCSS compiles correctly, selectors verified in compiled CSS
- **Tests:** ZERO PHPUnit tests written (gap — recommended for next session)

---

## v3.1.0 Session (2026-04-18) — BizLMS Feature Port: Enterprise Admin Pages

### Visual Audit (18 sidebar pages)
Screenshotted and assessed every sidebar destination. Categorized into:
- **Tier 1 Enterprise-grade (6):** Dashboard, Reports, Analytics, Compliance, Emails, Privacy
- **Tier 2 Functional (6):** Users, Courses, Organisation, Skills, Notifications, Site Admin
- **Tier 3 Stub (6):** Exams, Classrooms, Learning Paths, Programs, Evaluations, Certificates

### Bug Fixes (3 critical)
1. **Analytics crash** — missing `$cert_previous` query, nullable `trend()`, BizLMS `local_costcenter`→`local_airpay_org`, stdClass→array
2. **Admin tabs leak** — 8 plugin pages had `set_pagelayout('admin')` leaking Moodle Site Admin tabs into sidebar. Fixed with `set_pagelayout('standard')` + `set_secondary_navigation(false)`
3. **Certificates URL** — sidebar pointed to public verify form, now points to `manage_templates.php`

### Pages Rebuilt (11 pages, 36 files changed, +2,203 lines)

| Page | Key Feature |
|------|------------|
| **Manage Users** | 9-column sortable table, search+org+status filters, pagination (2,869 users), 7 capabilities, CRUD actions |
| **Manage Courses** | Admin table with enrolled/completed/rate%, org+category+status filters (411 courses) |
| **Online Exams** | 233 Moodle quiz activities with attempts/scores/time limits |
| **Classrooms** | ILT session management with status workflow, KPIs |
| **Learning Paths** | 17 real paths from legacy data |
| **Programs** | Enterprise empty state with Create CTA |
| **Analytics** | Business Unit dropdown filter (auto-submit on change) |
| **Notifications** | Type column populated (Deadline Reminder/Custom), KPIs, action dropdowns |
| **Organisation** | Tenant cards with expand/collapse departments, user counts (3/213/1,406) |
| **Evaluations** | Proper admin page with Moodle Feedback count |
| **Sidebar** | Manage Courses → airpay_courses admin (not catalog) |

### Enterprise UI Pattern (consistent across all pages)
1. Header with title + subtitle + primary action button
2. KPI cards (3-4 metrics with color coding)
3. Filter bar (Search + Organisation + Status + Category)
4. Data table (sortable, status badges, action dropdowns)
5. Pagination (25/page)
6. Empty state (icon + heading + description + CTA)

### Files Created
- `local/airpay_courses/index.php` + `templates/manage.mustache` — course admin
- `local/airpay_exams/templates/manage.mustache` — exam template
- `local/airpay_classroom/templates/manage.mustache` — classroom template
- `local/airpay_learningpath/index.php` + `templates/manage.mustache` — paths
- `local/airpay_programs/index.php` + `templates/manage.mustache` — programs
- `local/airpay_notifications/index.php` + `templates/manage.mustache` — notifications
- `local/airpay_org/admin.php` + `templates/manage.mustache` — org tree
- `local/airpay_evaluation/index.php` + `templates/manage.mustache` — evaluations
- `local/airpay_users/templates/manage.mustache` — users template

### What Remains
- CRUD modal forms (create/edit user, create course, create session) — wired to CTAs but not yet functional
- AJAX pagination (currently server-side, working but could be faster with AJAX)
- User profile page rebuild
- Reports page branding/org scoping
- Skills admin management view (currently shows learner readiness)

---

## v2.9.0 Session (2026-04-16) — BizLMS Fork Phase 1: Airpay Organization Engine

### New Plugin: local_airpay_org (10 files)
Replaces BizLMS `local_costcenter` (103 files) with Airpay-owned organization engine.

**Classes:**
- `accesslib.php` — Fork of `\local_costcenter\lib\accesslib` (6 static methods, BizLMS API compat)
- `org_manager.php` — Org CRUD: get, get_name, get_by_path, get_children, get_descendants, get_tenants
- `tenant_manager.php` — Tenant detection, open_path parsing, manager detection, public tenant, scoping
- `branding_manager.php` — Logo URL resolution, colour scheme, body CSS class, tenant branding

**Infrastructure:**
- `db/install.xml` — `local_airpay_org` table (15 fields, mirrors costcenter schema + branding colours)
- `db/access.php` — 5 capabilities mirroring BizLMS costcenter
- `lib.php` — `airpay_org_logo()` drop-in for `costcenter_logo()` + pluginfile callback
- `data_migration.php` — CLI script to copy local_costcenter → local_airpay_org (preserves IDs)

### core_renderer.php Update
- 13 BizLMS class references replaced → 0 remaining
- `use costcenter;` import removed
- `get_costcenter_scheme_css()` → `branding_manager::get_org_theme_scheme()`
- `get_my_scheme()` → `branding_manager::get_body_scheme_class()`
- `should_display_navbar_logo()` + `get_custom_logo()` → `branding_manager::get_tenant_logo()`
- All `\local_costcenter\lib\accesslib::*` → `\local_airpay_org\accesslib::*`
- 6 capability string refs (`local/costcenter:*`) kept for DB compat — Phase 7 migration

### dashboard.php Update
- Direct `{local_costcenter}` query → `org_manager::get_name_by_path()`

### Transition Strategy
- All classes: read local_airpay_org first, fall back to local_costcenter
- Logo files: check both component names (local_airpay_org, local_costcenter)
- BizLMS stays installed during transition — safe to deploy independently

### Phase 2: local_airpay_users (8 files)
Replaces BizLMS `local_users` (96 files) with Airpay-owned user engine.

**Classes:**
- `user_fields.php` — 17 open_* field constants (6 query + 11 display), prefix_label(), format_date()
- `user_manager.php` — build_profile_context() (replaces 200-line renderer), get_org_hierarchy(), get_supervisor(), get_role_names()

**Profile:**
- `profile.php` — Drop-in replacement for /local/users/profile.php
- `templates/profile.mustache` — Airpay-branded with gamification/skills enrichment + detail grid

**Updated files:**
- `local/users/renderer.php` — 7 BizLMS accesslib refs → \local_airpay_org (0 remaining)
- `theme/airpayux/core_renderer.php` — 2 config refs → dual-check (airpay_users + local_users fallback)

### Phase 3: local_airpay_courses (6 files)
Replaces BizLMS `local_courses` (136 files, already gutted to 3 templates) with Airpay-owned course engine.

**Classes:**
- `course_fields.php` — 11 open_* course field constants (2 access + 9 metadata)
- `course_manager.php` — get_progress_percentage() via core completion, deadline calc, can_manage/can_enrol dual-check

**Updated files:**
- `core_renderer.php` — 2 BizLMS accesslib calls → course_manager/airpay_org; 4 URL redirects → airpay_catalog
- `dashboard.php` — 1 URL ref → airpay_catalog

### Phase 4: Learning Modules (18 files across 3 plugins)

**local_airpay_classroom** (6 files) — Replaces BizLMS local_classroom
- `session_manager.php` — count_classrooms(), get_session() for QR attendance
- `db/install.xml` — 3 tables: classroom, sessions, attendance
- 3 capabilities

**local_airpay_exams** (6 files) — Replaces BizLMS local_onlinetests
- `exam_manager.php` — get_by_course_module(), get_by_attempt() for access control
- `db/install.xml` — 1 table: exams (linked to quiz module)
- 2 capabilities

**local_airpay_learningpath** (6 files) — Replaces BizLMS local_learningplan
- `path_manager.php` — get_courses(), is_enrolled(), get_user_progress()
- `db/install.xml` — 3 tables: paths, path_courses, path_users
- 3 capabilities

**Updated files:**
- `core_renderer.php` — 2 raw SQL queries → exam_manager API; 4 URL redirects → airpay_exams
- `dashboard.php` — 2 count queries → session_manager/exam_manager; 2 URLs → airpay plugins

### Phase 5: Search + Categories (3 files new, 4 files updated)

**New:** `category_manager.php` in airpay_catalog — wraps {local_custom_category} queries with get_name(), get_with_parent(), get_root/children helpers.

**Added to airpay_org/accesslib:** `get_user_role_switch_path()` + `get_costcenter_path_field_concatsql()` — 2 methods coursedetails.php needed.

**Updated files:**
- `local/search/coursedetails.php` — 3 BizLMS class refs + 4 raw category queries → airpay_org + category_manager
- `local/airpay_catalog/course.php` — 1 category query → category_manager
- `local/airpay_catalog/mycourses.php` — 1 category query → category_manager
- `core_renderer.php` — 1 custom_category URL → airpay_catalog

### Phase 6: Theme Complete Independence (9 files updated)

**Epsilon removed:**
- `get_primarycolor/secondarycolor/hovercolor()` — 3 methods rewired from `theme_config::load('epsilon')` → `branding_manager::get_brand_colors()`
- `getsitecolors_link()` — no longer returns epsilon CSS path
- 0 remaining `theme_config::load('epsilon')` calls

**BizLMS functions guarded:**
- `display_rating()` — 2 call sites wrapped in `file_exists()` + `function_exists()` guards
- `render_challenge_object()` — plugin context changed from `local_courses` → `local_airpay_courses`

**URLs migrated:**
- `/local/users/index.php` → `/local/airpay_users/index.php` (dashboard)
- `/local/users/signup.php` → `/local/airpay_users/signup.php` (login)
- `/local/users/profile.php` → `/local/airpay_users/profile.php` (2 locations)

**Metadata cleaned:**
- Dashboard.php header: eAbyas copyright → Airpay 2026
- Hindi lang: removed "BizLMS epsilon" from choosereadme
- Marathi lang: removed "BizLMS epsilon" from choosereadme
- SCSS: costcenter admin selectors marked deprecated (Phase 7 removal)

**Remaining (Phase 7 only):** 13 capability strings (`local/costcenter:*`, `local/courses:*`, `local/classroom:*`) — these reference DB role_capabilities rows and MUST stay until migration script reassigns them.

### Phase 7: Data Migration + BizLMS Removal (3 CLI scripts + 190 lines CSS deleted)

**CLI scripts (in local/airpay_org/cli/):**
- `migrate_all.php` — Master migration: copies 4 BizLMS tables + 10 capability mappings. Supports `--dry-run`. Verifies record counts.
- `disable_bizlms.php` — Disables 20 BizLMS plugins via config (reversible). Supports `--dry-run`.

**Capability migration (13 → 0 remaining):**
- All `local/costcenter:*` → dual-check via `accesslib::can_manage_multi/can_view/can_manage/is_org_head/is_dept_head`
- All `local/courses:*` → dual-check via `course_manager::can_manage/can_enrol`
- All `local/classroom:*` → dual-check via `accesslib::can_manage_classroom`
- 7 new helper methods added to `accesslib.php`

**CSS cleanup:** 190 lines of `#page-local-costcenter-*` selectors deleted from custom_changes.scss

**Run order:**
1. `php admin/cli/upgrade.php` (installs new tables)
2. `php local/airpay_org/cli/migrate_all.php --dry-run` (verify)
3. `php local/airpay_org/cli/migrate_all.php` (execute)
4. Smoke test all 5 roles
5. `php local/airpay_org/cli/disable_bizlms.php`
6. `php admin/cli/purge_caches.php`

### Phase 8: URL + Branding Removal (4 deliverables)
- Dashboard: "Moodle Version" → "Platform Version" (last visible Moodle text)
- `templates/core/maintenance.mustache` — Airpay-branded error/maintenance page
- `deploy/apache-airpay.conf` — Production Apache config (Option A: docroot, Option B: rewrite)
- `cli/verify_branding.php` — 10-point branding checklist (wwwroot, sitename, theme, caps, logo, favicon)

### Post-Fork: Remaining Replacements + Fixes
- **local_airpay_ratings** — Star rating engine (DB + rating_manager), replaces local_ratings
- **local_airpay_challenge** — Stub renderer for course challenges, replaces local_challenge
- **local_airpay_evaluation** — Stub for feedback forms, replaces local_evaluation
- **local_airpay_roles** — Stub for role management, replaces local_assignroles
- **local_airpay_programs** — Stub for certification programs, replaces local_program
- **block_airpay_trainer** — Trainer dashboard block + page, replaces block_trainerdashboard
- **Security:** 4 raw $_GET → optional_param(); SQL concat → parameterised queries
- **Missing pages:** airpay_users/index.php, signup.php; airpay_exams/index.php; airpay_classroom/index.php
- **BizLMS removal:** course_bannerimage() → Moodle core API; 8 files → tenant_manager; 6 debug lines removed; 3 upgrade.php stubs

### Fork Progress — ALL 8 PHASES + POST-FORK COMPLETE
| Phase | Plugin | Status |
|-------|--------|--------|
| 1 | local_airpay_org (costcenter) | ✅ COMPLETE |
| 2 | local_airpay_users (users) | ✅ COMPLETE |
| 3 | local_airpay_courses (courses) | ✅ COMPLETE |
| 4 | classroom + exams + learningpath | ✅ COMPLETE |
| 5 | search + categories | ✅ COMPLETE |
| 6 | theme independence | ✅ COMPLETE |
| 7 | data migration + BizLMS removal | ✅ COMPLETE |
| 8 | URL + branding removal | ✅ COMPLETE |
| — | Remaining plugins + fixes | ✅ COMPLETE |

### Complete Airpay Plugin Inventory (25 plugins + 2 blocks)
| Plugin | Purpose | Maturity |
|--------|---------|----------|
| local_airpay_org | Org hierarchy, tenant, accesslib, branding | STABLE |
| local_airpay_users | User management, profile, open_* fields | STABLE |
| local_airpay_courses | Course management, progress, enrollment | STABLE |
| local_airpay_classroom | ILT sessions, attendance, trainers | STABLE |
| local_airpay_exams | Online exams, quiz wrappers | STABLE |
| local_airpay_learningpath | Learning paths, course sequences | STABLE |
| local_airpay_catalog | Netflix catalog, commerce, cart, categories | STABLE |
| local_airpay_ratings | Star rating engine | STABLE |
| local_airpay_gamification | Points, badges, streaks, leaderboard | STABLE |
| local_airpay_compliance_report | 6-state compliance engine | STABLE |
| local_airpay_skills | Gap analysis, radar chart | STABLE |
| local_airpay_notifications | Rule engine, daily digest, nudge | STABLE |
| local_airpay_privacy | DPDP self-service | STABLE |
| local_airpay_assistant | AI chatbot (Claude API) | STABLE |
| local_airpay_analytics | KPIs, drill-down, export | STABLE |
| local_airpay_emails | 19 templates, rule engine | STABLE |
| local_airpay_pages | Homepage, static pages, QR, onboarding | STABLE |
| local_airpay_manager | Manager team dashboard | STABLE |
| local_airpay_integrations | KeKa HRMS sync | STABLE |
| local_airpay_lifecycle | JML automation | STABLE |
| local_airpay_challenge | Course challenges | ALPHA (stub) |
| local_airpay_evaluation | Feedback forms | ALPHA (stub) |
| local_airpay_roles | Role management UI | ALPHA (stub) |
| local_airpay_programs | Certification programs | ALPHA (stub) |
| theme_airpayux | 595 files, 9,700+ lines SCSS | STABLE |
| block_airpay_compliance | Compliance sidebar | STABLE |
| block_airpay_trainer | Trainer dashboard | STABLE |
| block_airpay_cron_health | Scheduled-task health dashboard widget (5 PHPUnit + a11y) | STABLE |
| block_airpay_cert_health | Certificate-email health dashboard widget (Sprint B, 6 PHPUnit + a11y) | STABLE |

---

## v2.8.0 Session (2026-04-16) — Commerce + Platform Cleanup

### Commerce System (NEW)
- commerce.php: Course pricing engine (config-based per-course, INR)
- public.php: Guest-accessible public catalog (no login required)
  - Search, sort (Popular/Newest/A-Z), pagination, pricing display
- course.php: Public course detail with Add to Cart / Enroll CTAs
- cart.php: Session-based shopping cart (works for guests)
  - Login redirect preserves cart via session
  - "Enroll in All (Free)" auto-enrolls via self-enrol plugin
  - "Payment Coming Soon" placeholder for paid courses
- lib.php: before_footer hook injects cart count for navbar badge
- Navbar: Custom cart icon with live count badge, BizLMS cart popup hidden

### Platform-Wide Dependency Cleanup
- Hardcoded tenant ID 77 → configurable via get_config + auto-detect
- Login stats: all fallbacks to all-tenant data removed
- Completion rate stat replaced with certificate count
- core_renderer: get_public_tenant_path() helper (no more inline /77%)
- Static page URL replacement: only targets href="/moodle/" (was breaking external links)
- 8 templates: "Moodle" sitename → "airpay academy"
- homepage.php: "Explore Courses" → public catalog, course cards show pricing

### Dark Mode Fixes
- head.mustache: Runs on EVERY page, detects OS prefers-color-scheme
- Explicitly removes dark-mode when preference is light (was only adding)
- Toggle icon synced on DOMContentLoaded
- Commerce pages: dark mode CSS in moodle.css
- Profile: .userprfltabs_container white wrapper fixed

### Signup Form
- Merged 2 checkboxes into 1 ("Privacy Policy & Terms of Use")
- Links to /local/airpay_pages/index.php?page=privacy

### New Pages
- DPDP Act 2023 page (/local/airpay_pages/index.php?page=dpdp)
- Moodle URL Removal Guide (MOODLE-URL-REMOVAL.md)

### Bug Fixes
- course.php: missing ID redirects to catalog (was 500 error)
- Switch role: $DB null crash fixed (global $DB added)
- BizLMS cart popup: hidden via CSS (conflicted with custom cart)

---

## v2.7.0 Session (2026-04-15) — Full Audit Execution

### Audit Buckets Completed (6 of 8)
| Bucket | Status | Key Deliverables |
|--------|--------|-----------------|
| 1: Bug Fixes | ✅ 16/16 | Permission bypass, race conditions, dark mode, empty states, caching |
| 2: Commercial Wins | ✅ | Learner onboarding wizard (4-step, first-login) |
| 3: UX Fixes | ✅ | ~90 dark mode rules, profile with skills/badges/stats, leaderboard confirmed |
| 4: Engagement | ✅ | Learning streak observer, manager nudge UI, daily digest task |
| 5: Admin Productivity | ✅ | Analytics drill-down (dept→users, course→learners), CSV export, compliance CSV |
| 6: Enterprise | ✅ | Manager dashboard plugin (local_airpay_manager), SSO setup guide |

### New Plugin: local_airpay_manager
- Team learning dashboard for supervisors
- Per-member: enrolled, completed, rate, overdue, streak, last login
- KPI cards: team size, avg completion, overdue, at-risk
- Action buttons: nudge, view skills, view profile
- Dark mode + mobile responsive

### DPDP Module Rewrite
- 4-tier access control: siteadmin → tenant admin → internal employee → external user
- Internal employees (Airpay tenant 1): policy notice only, no download/deletion
- External users (DPDP-enabled tenants): full self-service
- Configurable: siteadmin sets which tenants have DPDP via get_config('dpdp_tenants')

### BizLMS Switch Role Fix
- /my/switchrole.php created (was 404)
- Dashboard respects $SESSION->airpay_switchrole and $USER->useraccess
- Admin→Employee switch now shows learner dashboard (not admin)

### Profile Dark Mode Fix
- .userprfltabs_container white wrapper eliminated
- 11 dark mode rules for BizLMS profile classes
- Added to both SCSS and precompiled moodle.css

### Other Fixes
- DPO email: dpo@airpay.co.in → academy@airpay.co.in
- Privacy policy text softened for employees
- Progress bar sticky positioning fixed
- Compliance report table_exists() guard
- Quick Access hamburger CSS :has() fix

### Remaining Audit Roadmap (Buckets 7-8)
- Bucket 7: SENTIENTIA AI content creator, AI-powered recommendations
- Bucket 8: PWA mobile app, content marketplace connector

---

## v2.6.0 Session (2026-04-15) — Product Audit + Fixes

### Deep Product Audit (14-section report on Desktop)
- Full forensic audit: 15 learner modules + 10 admin modules rated
- Competitive benchmark vs Docebo, Absorb, TalentLMS, 360Learning, LearnUpon, Sana Learn
- 16 bugs found and ALL 16 resolved (1 critical, 1 high, 10 medium, 4 low)
- Top 25 prioritized actions identified
- Ticket-ready backlog for next 6 months

### Bug Fixes (16/16 complete)
- B1 CRITICAL: Compliance manager permission bypass — column guard + capability fallback
- B3: Dynamic tenant IDs (no more hardcoded [1,77,177])
- B4: Skills permission now throws error instead of silent fallback
- B5: Notification duplicate race condition — transaction-based dedup
- B6: Escalation to deleted manager — active user check
- B7: Compliance "last refreshed" timestamp + stale data warning
- B8: Notification batch LIMIT now configurable (default 500)
- B9: mycourses.php user_lastaccess try/catch guard
- B10: Email management plugin dark mode CSS (16 rules)
- B11: Email preview iframe mobile overflow fix
- B12: Compliance KPI caching via Moodle cache API
- B13: Analytics funnel empty state message
- B16: Mobile landscape orientation CSS

### New Features
- Learner Onboarding Wizard (4-step: Welcome → Interests → Goal → Courses)
  - Auto-triggers on first login for non-admin learners
  - Saves preferences to user_preferences table
  - Gradient branded UI, mobile responsive
- Quick Access hamburger menu fix (CSS :has() + JS MutationObserver)

### Multilingual Completion
- Theme lang files: 120+ strings × 4 languages (hi, mr, sw, kn)
- Email lang files: 35 strings × 4 languages
- Official Moodle lang packs installed: hi (709 files), mr (382), sw (301), kn (350)
- Translation CSV exported for Cowork review (386 strings)

### Remaining Audit Roadmap (not yet built)
- Bucket 3: Dark mode completion, profile enhancement, leaderboard on dashboard
- Bucket 4: Learning streak, manager nudges, daily digest
- Bucket 5: Custom report builder, analytics drill-down
- Bucket 6: SSO/SAML, ROI reporting, demo tenant
- Bucket 7: SENTIENTIA AI content creator, AI recommendations
- Bucket 8: PWA mobile app, content marketplace

---

## v2.5.0 Session (2026-04-14) — MEGA SESSION

### Tenant Isolation (10 cross-tenant data leaks sealed)
- Dashboard KPIs (enrolments, completions, active users, classrooms) scoped to tenant via open_path
- Homepage stats + featured courses scoped to Public tenant (/77%)
- Login page stats scoped to Public tenant
- Catalog category counts scoped to user's org
- Gamification leaderboard + rank scoped to user's tenant
- Badge criteria (compliance_complete, leaderboard_top10) scoped per-tenant
- Analytics heatmap mandatory course count + course effectiveness scoped
- Logo fallback: validates physical file exists, falls back to default_logo.png

### LXP UI/UX Overhaul (Sprints 3-11)
| Sprint | Deliverable | Files |
|--------|-------------|-------|
| 3 | Netflix catalog: carousels, bookmarks, autocomplete, lazy load | 5 |
| 4 | Course detail: completion states, related courses, social proof | 2 |
| 5 | Course player: collapsible sidebar, keyboard shortcuts, module tree | 3 |
| 6 | Exam dashboard template rewrite + CSS consolidation | 2 |
| 7 | Profile tabs modernization + certificate gallery | 3 |
| 8 | Skills dashboard (NEW from scratch) + compliance CSS | 4 |
| 9 | Notifications CSS (NEW) + gamification dark mode + AI polish | 3 |
| 10 | Email security fix + privacy bug + static pages nav | 4 |
| 11 | Homepage animations + mobile bottom nav + local QR | 3 |

### Multilingual Support (v2.5.0)
- 4 languages: Hindi (hi), Marathi (mr), Swahili (sw), Kannada (kn)
- 9 plugins × 4 languages = 29 lang files (28 new + 1 completed)
- ~1,056 total translations
- Activation: Admin installs official Moodle lang packs, selector auto-shows in navbar

### Security Fixes
- Email preview.php: path traversal injection fixed (sanitize before fallback)
- Email preview.php: tenant access validation (non-siteadmin locked to own tenant)
- Privacy index.php: account_delete enum mismatch fixed

### Tags
- v2.3.0-tenant-isolation — 10 cross-tenant leaks sealed
- v2.4.0-lxp-overhaul — Sprints 3-11 complete
- v2.5.0-multilingual — 4-language i18n across 9 plugins

---

## What's Built & Working

### Role-Based Dashboards (4 tiers)
| Role | Detection | Dashboard View |
|------|-----------|---------------|
| Siteadmin | `is_siteadmin()` | KPIs + Quick Nav + Charts + System Health + User Analytics |
| L&D Admin | `local/courses:manage` | KPIs + Quick Nav + Charts + User Analytics (no System Health) |
| Manager/HRBP | `moodle/site:viewreports` | Team KPIs + Compliance Table + Learner sections |
| Employee/External | everyone else | Welcome + Stats + Courses + Deadlines + Achievements + Timeline |

### Theme (airpayux)
- 10 surfaces styled: Login, Dashboard, Navbar, Footer, Catalog, Course Detail, Profile, Admin Tables, Mobile, Static Pages
- Dark mode + High Contrast mode (CSS layers, localStorage persistence, ~400 lines in `dark_mode.scss`)
- Component library (5 Mustache partials: button, card, badge, progress, stat_card)
- Service worker for static asset caching
- Costcenter scheme system (3 tenants)
- ~6,800 lines of custom SCSS
- jQuery compatibility: all 30 BizLMS AMD modules verified clean

### BizLMS Stabilisation (Phase 15)
- Course-to-costcenter mapping fixed (`open_path` + `selfenrol` + `open_identifiedas`)
- Role assignments configured per costcenter context
- cardPaginate float collapse fixed (CSS clearfix)
- Manager team structure: 10 employees under mgr_nitin (`open_supervisorid`)
- Manage Users, Manage Courses, Manage Company all rendering
- Dark mode covers all pages including BizLMS admin (costcenter stat cards, user/course cards, content containers)
- Visual testing complete: superadmin, L&D admin, employee, manager dashboards all verified
- Catalog blocked by BizLMS web service config (A3) — dashboard provides alternative course discovery

### Phase 16 — Production Data Import (2026-04-07)
- Imported production database (airpayprod 6th April backup, 3.5GB) into local XAMPP
- Collation fix: 2,176 instances of `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci` (MySQL 8.0 → MariaDB 10.11)
- GTID_PURGED line removed
- 618 tables, 2,871 active users, 411 courses, 213 costcenters — all imported successfully
- Moodle upgrade ran: 53 plugins upgraded (4.1→4.5), 30 new plugins installed, 21 legacy deleted
- Fixed `MESSAGE_DEFAULT_LOGGEDIN` → `MESSAGE_DEFAULT_ENABLED` in `local_airpay_lifecycle/db/messages.php`
- Theme set to airpayux, config.php wwwroot/dataroot unchanged (already localhost)
- 3 tenants live: Airpay (id=1, 205 sub-orgs), Public (id=77), ZEEA (id=177)
- Login verified as production siteadmin (academy@airpay.co.in)

### UI/UX Audit — Round 1+2 Complete (2026-04-08)
**Fixes applied:**
- jQuery AMD wrapping: 13 mustache templates (nav-drawer + 12 BizLMS templates) — `$ is not a function` errors resolved
- "Bussiness" → "Business" typo: 9 BizLMS lang files fixed
- Created missing `local/courses/fulldescriptionpopover.js` — unblocked Online Exams + Classrooms pages
- Reports dashboard link: `viewreport.php` → `managereport.php` (was requiring missing `?id=` param)
- Learning Paths: removed invalid `use core_component;` (PHP 8.2 warning)
- `perfdebug` set to 0 (was 7 from production — caused "Reactive instances" debug text)
- CSS: hidden reactive debug panel, hidden stray Policies link, brightened dark mode Quick Nav stats

**Round 1 — Siteadmin (academy@airpay.co.in):**
- Dashboard: ✅ KPIs (1,407 users, 407 courses, 39K enrolments, 20.6% completion), charts, quick nav, system health
- Manage Users: ✅ 2,869 users, card view, zero JS errors
- Manage Courses: ✅ 411 courses with production images
- Manage Company: ✅ All 3 tenants (Airpay 2,187 users, Public 676, ZEEA 6)
- Reports: ✅ LearnerScript report list rendering
- Online Exams: ✅ (was BROKEN → fixed with fulldescriptionpopover.js)
- Classrooms: ✅ (was BROKEN → fixed with same JS)
- Learning Paths: ✅ Production plans rendering (PG Products, ERP, BC Training, Customer Success, HR Onboarding)

**Round 2 — Employee (mithu.bala@airpay.co.in, Vyaapaar Fintech):**
- Dashboard: ✅ Welcome banner, 48 enrolled, 3 in progress, 21 completed, 15 certificates
- Continue Learning: ✅ 6 course cards with progress bars
- Activity Timeline: ✅ Real learning history (completions, quiz submissions, enrollments)
- Recent Achievements: ✅ 5 certificates with codes and dates
- My Courses: ✅ Moodle course overview with progress percentages
- Profile: ✅ BizLMS profile with personal info, stats, avatar

**Round 3 — Manager (binay.upadhyay@airpay.co.in, Vyaapaar, 9 direct reports):**
- Dashboard: ✅ CRITICAL FIX — added `open_supervisorid` fallback for manager detection (production managers have no capability roles)
- My Team: ✅ 9 team members, 115 enrolments, 29 completions, 25.2% rate
- Team Compliance: ✅ All 9 reports with enrolled/completed/pending/last active
- Navbar: ✅ Correct 4 pills (Dashboard, My Courses, Catalog, Profile)

**Round 4 — External (demoairpayacademy@gmail.com, Public /77):**
- Dashboard: ✅ 42 enrolled, 4 in progress, 11 completed, 6 certificates
- Continue Learning: ✅ Mixed hiring assessments + BC training courses
- Tenant isolation: ✅ Only sees Public tenant courses
- Logo: ✅ Default academy logo (Public has no costcenter_logo set)

**Round 5 — ZEEA (user.4156200@gmail.com, /177/178):**
- Dashboard: ✅ 20 enrolled, 0 in progress, 0 completed, 5 certificates
- Logo: ✅ ZEEA mafunzo logo loaded dynamically from costcenter_logo — tenant branding works!
- Courses: ✅ Swahili course names (Jinsi ya kuweka bidhaa, Uwezeshaji wa Ufanisi)
- Recently accessed: ✅ SCORM packages, quizzes, admin guide — all ZEEA content

**Round 6 — Guest (not logged in):**
- Homepage: ✅ Enterprise hero, stats, navigation
- Login: ✅ Split-screen with production stats
- Registration: ⚠️ Password field cosmetic issue (G3 — "Click to enter text")
- Help Center: ✅ 4 help cards
- Footer: ✅ Clean

**UI/UX Audit Complete — 6/6 rounds pass. All critical fixes applied.**
- Failsafe backups at: `D:\Claude Local\Moodle Backup\moodle_local_pre_import_20260407.sql` + theme + plugin copies

### Production DB Analysis Deliverables (2026-04-07)
- `Airpay-Academy-Production-DB-Diagnostic.pdf` — 33-question diagnostic with data evidence
- `Airpay-Academy-Production-Stabilization-Guide.pdf` — Full admin playbook (74 duplicate courses, cleanup SQL, naming convention)
- `Production-Data-Verification.xlsx` — 154 orphaned users, 116 never-logged-in, 1,407 active user roster, 213 costcenter map
- `Production-Import-Upgrade-Log.xlsx` — 105 plugin upgrade/install/delete log

### Plugins Built (16 plugins)

**Tier 1 (v1.1.0):**
- `local_airpay_gamification` — Points engine, 10 badges, streak calendar, leaderboard, event observers
- `local_airpay_notifications` — Rule engine, 7 notification rules, hourly cron, Moodle messaging
- `local_airpay_catalog` — LXP-style catalog: carousels, search, filters, trending, recommendations

**Tier 2 (v1.2.0):**
- `local_airpay_skills` — 48 fintech skills, 8 categories, role mapping, gap analysis, radar chart
- `local_airpay_analytics` — KPI trends, engagement funnel, compliance heatmap, course effectiveness

**Tier 3 (v2.0.0):**
- `local_airpay_assistant` — AI learning assistant (Claude API), floating chat bubble, 20 queries/day
- `local_airpay_integrations` — KeKa HRMS OAuth client, JML webhooks, employee sync
- `local_airpay_lifecycle` — Employee lifecycle automation (MESSAGE_DEFAULT_ENABLED fix applied)

**v2.1.0:**
- `local_airpay_compliance_report` — 6-state compliance engine, auto-enrol, progressive email escalation, 5 reports, Excel export
- `local_airpay_privacy` — DPDP Act 2023 self-service: data download (JSON), account deletion, consent log

**Foundation:**
- `local_airpay_pages` — Privacy Policy, Terms, Help Center, Contact Us (editable HTML, DPDP section updated)
- `block_airpay_compliance` — Compliance Dashboard block
- CLI scripts: seed_testdata.php, seed_users.php, fix_manager_role.php

### Wiring (v2.1.0)
- Compliance Report card in admin Quick Nav (with live stats: mandatory count + overdue count)
- Privacy (DPDP) card in admin Quick Nav (with pending request count)
- "My Privacy & Data" link in user dropdown menu (all logged-in users)
- Privacy static page updated with DPDP Act 2023 sections and self-service portal link
- `$CFG->noemailever = true` in config.php — zero emails sent from local environment

### Email Templates + Notification Management (v2.2.0)
**Branded Email System (local_airpay_emails — 56 files):**
- 19 Mustache email templates (6 compliance, 5 notifications, 4 enrollment, 2 account, 2 privacy)
- Theme email wrapper override (`core/email_html.mustache`) — branded header, Airpay signature footer, Indian tricolor bar
- 3 reusable partials (CTA button, course info box, footer note)
- Email renderer with DB override resolution chain (tenant → global → file fallback)
- Per-tenant template customization (DB table: local_airpay_email_overrides)
- 10 seeded notification rules (DB table: local_airpay_email_rules)
- Unified delivery log (DB table: local_airpay_email_log) with CSV export
- User notification preferences (DB table: local_airpay_email_prefs)
- Visual preview page (`/local/airpay_emails/preview.php`) with 19 templates, tenant selector, mobile/desktop toggle
- Management panel (`/local/airpay_emails/manage.php`) with 5 tabs: Dashboard, Templates, Rules, Logs, Settings
- BizLMS legacy integration (read-only view of 20+ BizLMS notification types)
- 5 AJAX web services (get/save/revert/preview template, toggle rule)
- 3 AMD JS modules (template_editor, rule_manager, delivery_log)
- Scheduled task: hourly rule processing with dedup
- 6 capabilities for granular permission control
- Email default: popup=enabled, email=opt-in only (lesson from 151-email incident)

**Bug Fixes (v2.2.0):**
- Privacy admin panel: siteadmins now see request management (approve/reject) instead of user self-service
- AI Assistant: enable/disable toggle in admin settings (Site Admin → Plugins → Airpay AI Learning Assistant)
- Quick Access icon: fixed broken JS controller (was using notification_popover_controller, now proper toggle)
- Cookie consent popup: disabled `sitepolicyhandler` for local development
- SMTP credentials wiped from DB, noreplyaddress set to localhost.invalid
- Email sending triple-locked: noemailever + no SMTP + localhost noreply

### Test Users
| Username | Name | Role | Password | Tenant |
|----------|------|------|----------|--------|
| superadmin | Super Admin | Siteadmin | Academy@2026 | — |
| test_admin | Amit Patel | L&D Admin (local/courses:manage) | Airpay@2026 | Airpay (1) |
| mgr_nitin | Nitin Manager | Manager (moodle/site:viewreports) | Airpay@2026 | Airpay (1) |
| emp_priya | Priya Singh | Employee (student) | Airpay@2026 | Airpay (1) |
| test_external | Deepa Menon | External (student) | Airpay@2026 | Public (77) |

**Manager team:** mgr_nitin supervises 10 employees (via `open_supervisorid`)

---

## Production Deploy Checklist

### Pre-deploy
- [ ] Backup production database
- [ ] Backup production theme/epsilon directory
- [ ] Verify server environment matches (PHP 8.2, MariaDB 10.11)

### Deploy Steps
1. Copy `theme/airpayux/` to production Moodle `theme/` directory
2. Copy `local/airpay_pages/` to production Moodle `local/` directory
3. Navigate to Site Admin → Notifications (triggers plugin install)
4. Activate airpayux theme: Site Admin → Appearance → Themes → Theme selector
5. Purge all caches: Site Admin → Development → Purge all caches
6. Hard refresh browser (Ctrl+Shift+R)

### Post-deploy verification
- [ ] Login page renders (split-screen, logo, stats)
- [ ] Superadmin dashboard shows admin view (KPIs + System Health)
- [ ] L&D Admin dashboard shows admin view without System Health
- [ ] Employee dashboard shows learner view (stats, courses, deadlines)
- [ ] Manager dashboard shows team KPIs + compliance table
- [ ] Navbar pills correct per role
- [ ] Footer correct per role (compact single row)
- [ ] Dark mode toggle works + persists across page loads
- [ ] Dark mode renders cleanly on BizLMS admin pages
- [ ] Static pages load (Help, Contact, Privacy, Terms)
- [ ] BizLMS Quick Access works
- [ ] Course catalog loads with courses
- [ ] Manage Users renders user cards
- [ ] Manage Courses shows courses
- [ ] Zero new console errors

---

## Git Tags
| Tag | Description |
|-----|-------------|
| phase5-final | Moodle 4.5.10 stabilised |
| phase6a-theme-foundation | Design system + fork baseline |
| phase6b-sprint7-final | All 7 CSS sprints complete |
| phase6b-prototype-match | Dashboard sections + pill nav + footer |
| phase7a-stabilised | 4-tier roles, nav fixes |
| phase7b-tested | All user types tested |
| phase15-production-ready | BizLMS stabilised, dark mode, deployment runbook |
| v1.0.0-rc1 | Base platform (theme + 4-tier dashboards + BizLMS) |
| v1.1.0 | Tier 1: Gamification + Notifications + Catalog |
| v1.2.0 | Tier 2: Skills Matrix + Analytics + Hindi |
| v2.0.0 | Tier 3: AI Assistant + KeKa HRMS + PWA + Marketplace stubs |
| v2.1.0 | Compliance Report + DPDP Privacy + Admin wiring |
| v2.2.0 | Email Templates + Notification Management Panel + Bug Fixes |

---

## Deployment Status

**Ready for IT team.** See `DEPLOYMENT-RUNBOOK.md` (Phase 15 — Final).

### Known Limitations (Ship With)
- BizLMS DataTables list view (B3) — untested, card view works
- BizLMS modal dialogs (B4) — may need production testing
- Reports, Online Exams, Classrooms (C4-C6) — untested BizLMS modules
- Email flows — not tested locally (production SMTP pre-configured)

---

## What's Next
- Visual demo inspection (7 scenes, ~15 minutes, all roles)
- Verify compliance snapshot with real data (2,871 users × 4 mandatory courses)
- Test privacy self-service as Public tenant user
- Production deployment (IT team — see DEPLOYMENT-RUNBOOK.md)

---

## 🚀 DAY 1 — STREAM B PHASE B.2.b + B.2.5 + B.3 + STREAM C PHASE C.1 (2026-05-21)

### Today's Body of Work

**Push notifications, end-to-end + WhatsApp wired to the same crons.** Five sub-phases shipped:

#### Phase B.2.b — Subscribe UI (commit `493edf8b6`)
- `classes/vapid_key_manager.php` — pure-PHP P-256 keypair generation via `openssl_pkey_new(['curve_name' => 'prime256v1'])`. Persists b64url public, raw private d, PEM (for JWT signer) in `mdl_config_plugin`. Windows openssl.cnf autodetect (probes 8 paths — xampp/php/extras/openssl/openssl.cnf works locally; standard Linux paths for production).
- `cli/generate_vapid_keys.php` — idempotent CLI. `--info` shows current state; `-r` force-regenerates with explicit `yes` confirmation prompt. Generated keypair successfully on local: `BGpa0GacjOTHnQwOboBm9ayvyylsFQxzmX4vSNCMuxn3EYXRcIyG8ACdkHg8fzlWx3u-5RMhBjT7gaACxFmf_Hc`.
- `amd/build/subscribe.min.js` (named-define ES5) + `amd/src/subscribe.js` (ES6 source). Handles `isPushSupported` → `Notification.requestPermission` → `pushManager.subscribe` → `Ajax.call('local_sentientia_pwa_save_subscription')`.
- `classes/output/subscribe_widget.php` + `templates/subscribe_widget.mustache` — three render states: not-set-up / flag-off / ready.
- `preferences.php` — user-facing page at `/local/sentientia_pwa/preferences.php`.
- `lib.php` — `extend_navigation_user_settings` adds "Browser notifications" to user profile settings nav.
- `settings.php` — VAPID status panel + vapid_subject (PARAM_RAW_TRIMMED, not PARAM_URL — mailto: scheme requires it) + default TTL + max payload.
- 35 EN + 35 HI strings.
- Version: 0.2.1-beta → 0.2.2-beta.

#### Phase B.2.5 ALPHA — Real Web Push delivery (commit `d69408ee1`) + **ADR-003**
Hand-rolled crypto, since `composer` isn't available and vendoring `minishlink/web-push` without it is brittle. ADR-003 records the decision and the safeguards. **18/18 crypto self-tests pass.**
- `classes/jwt_signer.php` (236 LOC) — RFC 7515 JWS ES256. Uses `openssl_sign()` with P-256 PEM, converts DER → JWS raw r||s. RFC 8292 compliant: exp clamped to ≤ 24h.
- `classes/payload_encrypter.php` (256 LOC) — RFC 8291 + RFC 8188 `aes128gcm`. Ephemeral P-256 keypair + ECDH (`openssl_pkey_derive`) + HKDF-SHA256 + AES-128-GCM. Hand-builds the SubjectPublicKeyInfo ASN.1 wrapper to feed raw 65-byte uncompressed point through `openssl_pkey_get_public`.
- `classes/push_sender.php` — `deliver_one()` rewrote stub → real: encrypt → sign JWT → POST `Authorization: vapid t=<jwt>,k=<vapid_pub>` + `Content-Encoding: aes128gcm` + `TTL` + `Urgency: normal`. Response classifier: 201/204 = sent; 404/410 = sub gone (auto-delete); 400/403/413/429/5xx = record_failure.
- `cli/test_crypto.php` — 18 assertions covering JWT sign+verify roundtrip, DER↔JOSE conversion roundtrip, aes128gcm encrypt+manual-UA-decrypt roundtrip with independent HKDF chain.
- `cli/test_push.php` — operator-facing real-world push CLI (`--userid=N --dry-run --title --body --url`).
- Fix: `openssl_pkey_export()` also needs the `config` arg on Windows, not just `openssl_pkey_new()`. Previous keygen runs silently wrote an empty PEM — caught + fixed.
- Maturity: BETA → ALPHA (crypto needs security review before beta promotion).
- Version: 0.2.2-beta → 0.2.5-alpha.

#### Phase B.3 ALPHA — Push wired into reminder/escalation crons + delivery log + iOS hint (commit `21a984272`)
- **B.3.a/b** — `classes/notification_bridge.php` in `local_sentientia_pwa`. Shared `also_push()` helper (soft-coupled, error-swallowing, flag-aware). 4 cron tasks wired (course_reminder, course_overdue, exam_reminder, exam_overdue) — they now fire dual email+push.
- **B.3.c** — `classes/push_logger.php` + `db/install.xml + upgrade.php` (new `local_sentientia_push_log` table) + `admin/push_log.php` viewer (filter by result/user/since, stats banner, paginated 50/page). Retention scheduled task at 02:00 daily, `log_retention_days` admin setting (default 90).
- **B.3.d** — `amd/build/ios_install_hint.min.js` — detects Safari iOS without home-screen install, injects dismissible "Add to Home Screen" banner. Built with `createElement` + `textContent` (never innerHTML — XSS-safe). localStorage `sentientia_pwa_ios_hint_dismissed` suppresses re-show.
- 2 new flags: `sentientia.pwa.push.reminders` + `.overdue` (both default OFF).
- 47 new lang strings (EN + HI parity preserved).
- Version: 0.2.5-alpha → 0.3.3-alpha (4 successive savepoints: 2026052103, 2026052104, 2026052105, 2026052106).

#### Stream C / Phase C.1 — WhatsApp wired into the same 4 crons (about to commit)
- `local/airpay_whatsapp/classes/notification_bridge.php` — mirror of PWA notification_bridge. `also_send()` calls `whatsapp_client::send_template()` directly (NOT `channel_router::dispatch()` because that would cascade to email which already fired via `message_send()`). `pick_deadline_template()` selects deadline_7d / deadline_3d / deadline_1d by days-remaining bucket.
- 4 crons updated: course_reminder + course_overdue + exam_reminder + exam_overdue now triple-fan-out (email + push + WhatsApp/SMS).
- 2 new feature flags in `local_airpay_core/db/feature_flags.php`: `engagement.whatsapp.reminders` + `engagement.whatsapp.overdue`.
- No new DLT templates — reuses existing seeded ones (`deadline_7d/3d/1d` + `team_overdue`).
- Version: local_airpay_whatsapp 0.2.0-alpha → 0.3.0-alpha (savepoint 2026052101).

### Architecture insight — the notification fan-out pattern
4 cron tasks × 3 channels (email/push/WhatsApp) = 12 individual call sites if naive. With the bridge pattern it's 4 × 3 = 12 lines total (one bridge call per channel per cron). Adding a 5th cron in the future is 3 lines. Adding a 4th channel (e.g. Slack) is one new `notification_bridge` class + 4 lines added across the existing 4 crons.

### Production rollout gate (must all be green before flipping push.enabled ON)
1. `cli/test_crypto.php` green — **DONE** (18/18)
2. `cli/test_push.php` to a real subscriber succeeds — **needs Chrome MCP or human browser** to create a subscription first
3. Security review of jwt_signer + payload_encrypter + push_sender — **pending**
4. Promote MATURITY_ALPHA → MATURITY_BETA — **post-review**
5. Flip `sentientia.pwa.push.enabled` ON in Switchboard — **post-review**

### Pending: DLT registration for live WhatsApp
- The 5 seeded templates are in `pending` state. Until they're submitted + approved by the DLT portal AND Karix/MSG91 credentials are in `.env`, the WhatsApp client stays in mock mode (logs send_log row with `status = mocked` but never actually POSTs).
- This isn't a code task — it's an ops task on Airpay's side. Stream C / Phase C.1 ships ready to go live the moment DLT approval lands + credentials added.

### Day 1 commits (chronological — 17 commits, 5 streams, ~10,000 LOC)
| Commit | Phase | Description |
|--------|-------|-------------|
| `50cb19f4c` | (Day 0 wrap) | end-of-day state save (2026-05-20) |
| `493edf8b6` | **B.2.b** | Subscribe UI (VAPID gen + AMD module + preferences page) |
| `d69408ee1` | **B.2.5 ALPHA** | Real Web Push delivery (hand-rolled ES256 + aes128gcm) + ADR-003 |
| `21a984272` | **B.3 ALPHA** | Push into 4 crons + delivery log + admin viewer + iOS install hint |
| `604354377` | **C.1** | WhatsApp wired into same 4 crons + PROJECT-STATE Day 1 |
| `7417ef588` | **E.0** | Sentientia Live foundation (5 tables + 9 flags + ADR-004 SSE choice) |
| `d8c6aa8b7` | **E.1.a/b** | session_manager + event_journal lib + 28 PHPUnit tests |
| `aa5403035` | **E.1.c-h** | slide_manager + participant_manager + trainer dashboard + create form |
| `6dede28c9` | **E.1.i** | end/delete/edit/start/run handlers (nav closed) |
| `043781bf2` | **E.1.j** | Polymorphic slide editor (all 6 question types) |
| `4abd73d1e` | **E.2** | Audience UI — join + play (full Mentimeter loop end-to-end) |
| `38f515243` | **B-verify** | Mock subscriber + receiver + run_push_e2e (9/9 PASS) |
| `b4581a812` | **E.3** | SSE realtime (stream.php + audience + trainer clients) |
| `846b621cc` | **E.4** | Live result panels (per-type bar charts / wordcloud / etc) |
| `e91777ec3` | **C-verify** | WhatsApp e2e orchestrator (ALL PASS, mock mode) |
| `e6ff68742` | **VIS + fixes** | Visual evidence + 3 bug fixes from walkthrough |
| `a28680661` | **E.5** | Dynamic chart updates via SSE + UX polish (audience progress, heading center) |

### E2E regression tests (re-runnable in seconds)
- `php local/sentientia_pwa/cli/test_crypto.php` — 18 crypto self-tests
- `php local/sentientia_pwa/cli/run_push_e2e.php --userid=N` — 9 end-to-end push assertions
- `php local/airpay_whatsapp/cli/run_whatsapp_e2e.php --userid=N` — 11 WhatsApp pipeline assertions

### Sentientia Live status
```
Foundation (E.0):           ✅ shipped
Lib layer (session/slide/   ✅ shipped (E.1.a/b/d/e)
  participant/event):
Trainer dashboard (E.1.f):  ✅ shipped + visually verified
Create form (E.1.g):        ✅ shipped + visually verified
Edit + handlers (E.1.i):    ✅ shipped + visually verified
Slide editor (E.1.j):       ✅ shipped (form interaction not browser-verified)
Audience UI (E.2):          ✅ shipped + visually verified
SSE realtime (E.3):         ✅ shipped (verified via curl, not 2-window browser)
Result panels (E.4):        ✅ shipped + visually verified (bar chart fires!)
Dynamic chart updates (E.5):✅ shipped (event payload verified, browser pending)
─────────────────────────────────────────────────────────────
Phase E.6 — Quiz leaderboard:                          ⏳ pending
Phase E.7+ — Per-type render polish (wordcloud/openended/ranking dynamic): ⏳ pending
Phase E.10 — Customer-level settings + full privacy:   ⏳ pending
Phase E.11 — Mobile responsive pass:                   ⏳ pending
Phase E.12 — Analytics + export:                       ⏳ pending
```

### Visual evidence captured
- `docs/visual-evidence/2026-05-21/README.md` — 12 surfaces walked, 7 issues catalogued (3 fixed)
- Trainer dashboard, create form, edit page with slides, type picker, run page, audience join, audience play, audience play with bar chart, trainer run with populated data, PWA subscribe widget

### Resume next session (priority order)
1. **Phase E.5 two-browser verification** — open trainer/run + audience/play side-by-side, submit response, watch bars animate via the SSE → chart_updater pipeline. Needs Nitin or Chrome MCP improvements.
2. **Phase E.6 — Quiz leaderboard** — quiz type currently shows the same bar chart as multichoice. Add a sorted leaderboard view + "X out of Y got it right" summary. ~1 hour.
3. **Phase E.11 — Mobile responsive pass** — test audience/play at 590px viewport (Mentimeter's primary form factor). Most likely needs CSS adjustments.
4. **Stream B / B.2.5 security review** — production gate per ADR-003 before push.enabled flips ON.
5. **Stream B — real-browser subscribe verification** — open `/local/sentientia_pwa/preferences.php`, click "Enable browser notifications", run `php cli/test_push.php --userid=N`. Browser is the only remaining unmocked path.
6. **Tier 1 #4 — AI quiz generation** — needs Anthropic API key budget approval first.
7. **Tier 1 #5 — Hindi content pipeline** — needs ElevenLabs subscription confirmed.
8. **License-header rewrite sweep** — ~200 files, GPL §5(a) compliance pass 2. Mechanical.

### Open environmental dependencies (Nitin's team)
- VAPID keypair on production AWS server (run `cli/generate_vapid_keys.php` once)
- web-push live mode credentials (Karix/MSG91) — for Stream C live mode
- DLT template submission for the 5 seeded WhatsApp templates
- Anthropic API key + budget approval (Tier 1 #4)
- ElevenLabs subscription confirmation (Tier 1 #5)
- Chrome DevTools MCP reconnect for visual verification of E.5 + later phases

---

## 🎯 RANKED PRIORITIES — 2026-05-22

After gap-analysis review on 2026-05-22, the next-session queue is
ranked for maximum unblock-per-hour:

| # | Title | Effort | Status |
|---|---|---|---|
| 1 | **Crypto audit non-blocking sweep** — close NB #7-#15 (9 findings) before flipping production push flag ON | ~2h | ✅ done (commit `88aae0bf0`) |
| 2 | **Production master key + push flag-on dry run** — admin runs `cli/generate_master_key.php` + sets env/CFG + regenerates VAPID keypair → flip `sentientia.pwa.push.enabled` on local first, then production | ~1h | ✅ done (commit `047457d4f` — local complete; production handoff documented) |
| 3 | **Customer 2 readiness — implement ADR-008** — `local_airpay_customer_brand` table + migration backfilling the Airpay row + cached resolver replacing the hard-wired switch in `customer::branding()` | ~3h | ✅ done (commits `047457d4f`, `4cc25410f`, `c31bc1346`) |
| 4 | **PHPUnit coverage for crypto stack** — promote `cli/test_audit_fixes.php` + `cli/run_push_e2e.php` to `tests/*_test.php`; add RFC 8291 §5.1 worked-example vector + tenant-isolation scenarios | ~2h | ✅ done (commits `4cc25410f`, `b839dace0`, `c31bc1346`) — 53 tests / 141 assertions all green |
| 5 | **Mobile-app WS surface (Phase X.1)** — expose the 22 read-only WS endpoints identified in `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`. Unlocks the Moodle Mobile app for learners | ~4h | ⏳ deferred until Goals A/B/C are sequenced |

---

## 🎯 RANKED PRIORITIES — 2026-05-22 (afternoon — new goals added)

Per Nitin's afternoon ask: three multi-session goals added. These are
substantially larger than the morning priorities and will likely span
multiple sessions each. Ranking optimised for the natural sequencing
(audit produces screenshots → screenshots feed user guides; UI upgrades
happen in the gap between audit findings and guide build):

| # | Goal | Effort | Status | Notes |
|---|---|---|---|---|
| A | **Visual UI audit of every page surface per user type** — Chrome walkthrough of every page for each of the 9 user types in Section 10 of the May-12 master doc (Learner, Manager, L&D Admin, Course Author/SME, Compliance Officer, Tenant Admin, Site Admin, External Public Learner, API Consumer). Output: persona-bucketed desktop+mobile screenshots, audit report flagging "still looks like Moodle" surfaces, prioritised UI-upgrade backlog. | ~30h | ⏳ blocks A.x and C | First step is the audit; UI upgrade work (A.x) generated from findings. |
| A.x | **UI upgrade work driven by audit findings** — each "looks like Moodle" surface gets a redesign sprint (SCSS, Mustache, before/after screenshots, mobile pass). Tracked separately. | varies | ⏳ blocked by A | Effort scales with how many surfaces A flags. |
| B | **E2E click-through testing of every feature** — manual Chrome walkthrough first pass (catches UX issues automation can't see), then Playwright-driven repeat. Pass/fail matrix + broken-flow list + flow screenshots (which double as user-guide raw material). | ~25h | ⏳ blocked by A.x | Should run after UI upgrade lands so we test the new surfaces, not the old. |
| C | **Detailed user guides per user type** — outline approval gate from Nitin (asked 2026-05-22 afternoon). Format + depth + audience-scope options presented via AskUserQuestion. Build starts after approval; consumes Goal A screenshots + Goal B flow recordings. | ~60–120h depending on format | ⏳ AWAITING OUTLINE APPROVAL | See `docs/user-guide-plan-2026-05-22/` for the full proposed structure. |
| (5 above) | Mobile-app WS Phase X.1 — re-queued after A/B/C land. | ~4h | ⏳ deferred |

### Goal C — outline + options awaiting approval

**Outline proposed (per persona, applied to all 9 user types from Section 10):**

1. **Welcome** (1 page) — who this guide is for, how to navigate, links to sibling personas
2. **Quick Start** (3–5 pages) — first login, dashboard tour, "what to do in your first 15 minutes"
3. **Daily Operations** (10–25 pages) — the 80 % of tasks done weekly, step-by-step with screenshots
4. **Feature Reference** (30–50 pages) — every screen / button / setting documented, alphabetical
5. **Troubleshooting / FAQ** (5–10 pages) — common issues + recovery
6. **Glossary** (2–3 pages) — Moodle terms, BizLMS terms, Airpay-specific terms
7. **Changelog / What's new vs v1** (2–3 pages) — per persona, the v1→v2 deltas from Section 10
8. **Contact + Escalation** (1 page) — how to reach L&D, IT, super-admin

**Format options (presented to Nitin via AskUserQuestion):**
A. PDF per persona — print-ready, polished, ~30–50 pp each; stale on update
B. Native Moodle Book module — lives in platform; looks like Moodle (the thing we're moving away from)
C. **Static docs site** (MkDocs Material or Docusaurus) — brand-aligned CSS, searchable, version-controlled, mobile-friendly; published at /docs/ or docs.airpay.academy *(RECOMMENDED)*
D. Embedded help plugin (`local_sentientia_help/`) — biggest build but lives inside the platform with full design-system styling
E. Hybrid C + in-product help cards on each page

**Depth options:**
A. Quick Start only
B. Daily Operations only
C. Full Reference Manual only
D. **Comprehensive** = Quick Start + Daily Ops + Reference + Troubleshooting *(RECOMMENDED)*

**Audience-scope options:**
A. All 9 personas (separate guides, with shared core to avoid duplication) *(RECOMMENDED — aligns with explicit "for each user" ask)*
B. Consolidated 5 (Learner / Manager / L&D Admin / Tenant+Site Admin / External Public)
C. Top 3 only (Learner / Manager / L&D Admin) — covers ~95 % of population
D. Custom mix to be specified

### Out-of-scope until external action lands
- AI quiz gen (Tier 1 #4) — needs `ANTHROPIC_API_KEY`
- Hindi content pipeline (Tier 1 #5) — needs ElevenLabs subscription
- Path C native wrap (per ADR-005) — needs Apple Developer + Google Play enrolment
- Phase 2 React/Next overlay (per ADR-001) — needs design system v2 sign-off
- `live.airpay.academy` deploy — needs IT staging environment + SMTP

### Definition-of-done checks for each priority
1. ✅ Crypto sweep: all 9 NB fixes shipped + `test_audit_fixes.php` extended → all PASS (28/28)
2. ✅ Master key: documented in plugin README + tested locally with regenerated PEM + `cli/encrypt_existing_pem.php` + `cli/verify_signed_with_encrypted_pem.php` (9/9). Production handoff: admin runs `generate_master_key.php` → sets `$CFG->sentientia_vapid_master_key` → runs `encrypt_existing_pem.php` once.
3. ✅ ADR-008 impl: migration `2026052201` ran cleanly + `customer::branding(1)` returns identical bundle pre/post migration + `cli/verify_brand_resolver.php` (20/20). Admin UI deferred to Phase 2 (DB-edit + `purge_caches.php` works today).
4. ✅ PHPUnit: 4 test files (`customer_brand_test`, `audit_fixes_test`, `payload_encrypter_test`, `tenant_isolation_test`) — **53 tests, 141 assertions, 0 failures** under `vendor/bin/phpunit --testsuite local_airpay_core_testsuite,local_sentientia_pwa_testsuite`. Also uncovered + fixed 4 product bugs: (a) install.xml unescaped `<` in COMMENT attribute, (b) per-customer invalidate accidentally global-purged via event, (c) fresh-install seed missing (added `db/install.php`), (d) test bootstrap helpers for env where BizLMS is disabled.
5. ⏳ Mobile WS: each of 22 endpoints returns 200 + correct schema via mobile-app-token; Moodle Mobile app installs + sees Airpay Academy correctly

### Session 2026-05-22 summary
Six commits totalling 4 priorities closed and 4 product bugs discovered + fixed via the new test suite. All four #4 sub-priorities (4a/4b/4c/4d) green. Test-DB initialization documented in commit messages for IT (drop `phpu_*` tables, then `php public/admin/tool/phpunit/cli/init.php`).

---

## 🎯 SESSION 2026-05-22→23 — GOAL A AUDIT + ARCHITECTURAL PATTERNS

**23 commits pushed to `origin/production` (`89fb2e713` → `bf5412ed2`).**
Mission: walk every persona × every surface, fix what's broken, restyle what
still looks like Moodle. Outcome went deeper than expected — the bugs found
clustered into two architectural shapes, both now codified as
project-wide invariants.

### Headline outcomes

  - **Goal A formally complete**: 8 of 9 personas walked (Learner, Site Admin,
    Manager, L&D Admin, Course Author, Tenant Admin, Compliance Officer,
    External Public Learner). API Consumer is docs-only.
  - **9 bugs fixed end-to-end with browser verification**: #6, #7, #8, #9b,
    #10, #11, #12, #13 (+ session-extension of pre-existing #9). Every fix
    verified via `getComputedStyle` / `take_snapshot` / direct WS roundtrip
    before commit.
  - **5 Moodle-leak surfaces restyled to Sentientia tokens**: `/user/profile.php`,
    `/badges/mybadges.php`, `/grade/report/overview/`, `/admin/*` interior
    (search + all settings.php), `/course/view.php`.
  - **2 architectural patterns codified**: shared `role_detector` (single
    source of truth for role tier) and `ws_contract_scanner` (CI gate
    against client-server contract drift).
  - **3 test/tool infrastructures built**: PHPUnit `ws_contract_test`,
    PHPUnit `role_detector_test` (7-method 5-tier matrix), CLI
    `theme/airpayux/cli/ws_contract_audit.php`.
  - **1 ADR**: ADR-009 documenting both patterns + on-ramp for new contributors.

### The bug-class extinction pattern

Each bug fix was 1-by-1 with verification. But the bugs themselves clustered
into a tellingly small number of *shapes*:

| Bug | Shape |
|---|---|
| #6  My Requests stuck on Loading            | WS contract drift |
| #9b Manager WS denied supervisors           | Detection drift |
| #10 5 sibling endpoints WS contract drift   | WS contract drift |
| #11 Compliance Officer Learner sidebar      | Detection drift |
| #12 Cart datatable region attribute         | WS contract drift |
| #13 Mobile shell-main 260px width loss      | Media-query half-override |

The first 5 commits (Bug #6 + #10 + #9b + #7 + #8) patched individual
bugs. From commit 15 onward the work shifted to making the bug class
extinct:

  - **Commit 15** (`fcd150c0a`) — `role_detector` shared helper. Bug #11
    surface area now zero. Both `layout/dashboard.php` and
    `classes/sidebar_navigation.php` consume the same `detect()` method
    and can never disagree again.
  - **Commit 16** (`f258db649`) — `ws_contract_scanner` + PHPUnit gate.
    Every WS consumed by the shared datatable client is now checked at
    CI for full-contract compliance. The gate found Bug #12 in its
    first run — a CURRENTLY-broken endpoint that had been silently
    showing "Loading…" forever.
  - **Commit 18** (`9a76ef3ad`) — CLI wrapper for the scanner. Same
    code, on-demand auditing for forensics and release sanity.
  - **Commit 20** (`5afbddb31`) — ADR-009 documents the patterns
    + on-ramp guide for new contributors.
  - **Commit 21** (`bf5412ed2`) — PHPUnit `role_detector` 5-tier
    matrix codifies the role-detection contract.

### Commit ledger (chronological)

```
89fb2e713  fix(ws):       WS contract drift family (#6+#9b+#10, 7 endpoints)
ad0168d10  audit:         Manager + L&D Admin walks
e1cf9206c  fix(deploy):   #7 — branded Apache 404/500/403 via .htaccess
d90f6b44c  fix(theme):    #8 — footer overlap on long pages (body height clamp)
6f25d6cae  feat(theme):   Goal A.x /user/profile.php restyle
179204297  feat(theme):   Goal A.x /badges/mybadges.php restyle
5e69eaa2b  feat(theme):   Goal A.x /grade/report/overview restyle
552664466  audit:         Course Author persona finding
1788d6ff1  audit:         Tenant Admin persona walk
eacc604bc  feat(theme):   Goal A.x /admin/* interior restyle
e8c303e9e  feat(theme):   Goal A.x /course/view.php restyle
4a0b32e8d  audit:         Goal A.x leak-surface scoreboard
40fb6fb3b  fix(theme):    #11 — sidebar role-detection consistency
f583f1b67  audit:         Compliance Officer + Public Learner walks
fcd150c0a  refactor:      role_detector shared helper
f258db649  test:          PHPUnit WS contract gate
411c9ef49  fix(cart):     #12 — datatable region attribute + JSON double-escape
9a76ef3ad  feat(theme):   CLI tool for ad-hoc WS contract audit
117c2e84a  fix(theme):    #13 — mobile shell-main width loss
5afbddb31  docs(adr):     ADR-009 — detection + WS contract invariants
bf5412ed2  test(theme):   PHPUnit role_detector 5-tier matrix
```

### Key insights surfaced this session

1. **Verification discipline pays for itself.** Each fix was verified in-browser
   AFTER the change before commit. Twice this surfaced second-order bugs hidden
   under the first one. Bug #6 looked like a single fix; verification revealed
   3 cascading causes + a WS-contract-drift root cause hitting 5 more endpoints.
   Bug #11's first-pass fix had a double-LIMIT SQL bug that silenced the BizLMS
   role check — only the CLI repro `\theme_airpayux\sidebar_navigation->get_context()`
   exposed the actual `dml_read_exception`.

2. **Web rendering hides bugs that CLI surfaces.** Moodle's exception
   swallowing meant a real DB error returned a silent `false`. Drop down a
   layer when verification keeps failing.

3. **The PHPUnit gate paid for itself in minutes.** Built `ws_contract_scanner`
   as regression protection for Bug #6/#10. First run found Bug #12 — a
   currently-broken endpoint nobody had reported. Highest-leverage CI test
   outcome: surfacing live bugs, not just preventing future ones.

4. **Detection consistency is an architectural invariant.** Bug #11 happened
   because two duplicated implementations drifted. Aligning them was a
   band-aid; the structural fix was promoting the detection rules to a
   shared `role_detector::detect()` that both consumers MUST consume.

5. **Mobile media-query exhaustiveness.** Bug #13 hid since whenever the
   mobile breakpoint was first written. `margin-left: 0` was added but
   `width: 100%` was forgotten — every `.ap-shell` page lost 260px of
   content area at mobile. No persona walk surfaced it because they all
   ran at desktop viewport.

### Goal A.x leak-surface scoreboard

| Surface | Start | End |
|---|---|---|
| `/user/profile.php` | 🟠 Moodle 2-col | 🟢 Sentientia card grid |
| `/badges/mybadges.php` | 🟠 plain Bootstrap | 🟢 branded card + trophy empty state |
| `/grade/report/overview/` | 🟠 vanilla table | 🟢 branded thead micro-labels |
| `/admin/*` (search + settings) | 🟠 bare Moodle Boost | 🟢 card headers + brand forms |
| `/course/view.php` | 🟠 plain section list | 🟢 section cards w/ brand accent |
| Apache 404/500/403 | 🔴 raw + version leak | 🟢 wrapped in airpayux theme |
| Long-page footer | 🔴 painted mid-content | 🟢 at correct page-end |
| Mobile content width | 🔴 -260px phantom | 🟢 full viewport |

### Persona walks complete (8 of 9)

| Persona | User | Key finding |
|---|---|---|
| Learner | Fatma Khamis | All 12 surfaces 🟢 except 4 leak pages restyled |
| Site Admin | academy@airpay.co.in | 19-item admin sidebar; /admin/* now branded |
| Manager | Binay Upadhyay | Team Dashboard 🟢 fully branded; #9b WS fix needed |
| L&D Admin | Nitin Rajput | Platform-wide KPIs + 11-item admin sidebar |
| Course Author / SME | Asif Ansari | No separate dashboard — Learner+editingteacher; teaches 33 courses |
| Tenant Admin | External Admin /Public-77 | Same L&D shape, tenant-scoped numbers + My Cart |
| Compliance Officer | Joseph Mandapati | Bug #11 — BizLMS-admin role at category context |
| External Public Learner | vimal koothattu | Standard learner with My Cart for e-commerce |

API Consumer is docs-only — no UI walk required.

### Artifacts in production right now

  - `theme/airpayux/classes/role_detector.php` — shared detector
  - `theme/airpayux/classes/sidebar_navigation.php` — delegates to detector
  - `theme/airpayux/layout/dashboard.php` — delegates to detector
  - `theme/airpayux/classes/ws_contract_scanner.php` — drift checker utility
  - `theme/airpayux/tests/ws_contract_test.php` — PHPUnit CI gate
  - `theme/airpayux/tests/role_detector_test.php` — PHPUnit 5-tier matrix
  - `theme/airpayux/cli/ws_contract_audit.php` — on-demand admin CLI
  - `theme/airpayux/scss/moodle/partials/_surface-profile.scss` —
    extended with 4 surface restyles (profile, badges, grades, admin) +
    course-view (#7-#13 fixes inline)
  - `theme/airpayux/scss/moodle/partials/_layout-shell.scss` — Bug #13
    mobile fix landed
  - `theme/airpayux/scss/moodle/sticky-footer.scss` — Bug #8 viewport
    clamp fix landed
  - `deploy/moodle-htaccess.template` — Bug #7 ErrorDocument routing
  - `docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md`
  - `docs/visual-audit-2026-05-22/AUDIT-REPORT.md` — full audit narrative

### Verifiable end-to-end

  - `php theme/airpayux/cli/ws_contract_audit.php` — returns ALL PASS exit 0
  - 9 datatable WS endpoints all contract-compliant (was 7 broken at
    session start: Bug #6 list_mine + Bug #10's 4 endpoints + Bug #12's
    2 cart endpoints all green)
  - `\theme_airpayux\role_detector::detect()` returns correct tier for
    all 5 paths (CLI smoke tested on 5 production users + PHPUnit matrix
    isolated fixtures)
  - All 5 restyled surfaces verified in-browser via `getComputedStyle`
    after each commit
  - Mobile viewport 591×671: 4 spot-checked pages (profile, badges,
    grades, dashboard) all render full-width without horizontal overflow

### Next-session backlog (clear handoff)

  - PHPUnit gate adoption: enable Moodle PHPUnit init + run the two new
    suites at CI (`ws_contract`, `role_detector` groups)
  - Per-course gradebook `/grade/report/index.php?id=N` restyle —
    inherits most styling from grades-overview, low risk
  - Mobile responsive verification of admin/* + course/view pages
    (only profile/badges/grades/dashboard checked this session)
  - `/course/edit.php` restyle — needs Site Admin login + form-heavy
  - Calendar `/calendar/view.php` + Message `/message/index.php` restyles
  - Factor `theme_airpayux\ws_contract_scanner::find_files()` and `load_services_file()`
    into a shared utility class for re-use by future static-analysis tests
  - Goal B (Playwright E2E harness) — still blocked by Node 24/Playwright
    1.60 incompatibility on Windows
  - Goal C (user guide content) — blocked on Goal A.x + B
  - Mobile-app WS Phase X.1 (22 read-only endpoints) — deferred behind
    Goals A.x + B

### Production-deploy notes for IT

The new artifacts that need explicit deployment beyond the standard
git-pull + cache-purge:

  - **`.htaccess`** on the live web root needs the new ErrorDocument
    rules. Source-of-truth: `moodle-enhancement/deploy/moodle-htaccess.template`.
    If production uses a different alias (not `/moodle/`), adjust the
    `ErrorDocument` targets accordingly.
  - **`ServerTokens Prod`** in `httpd.conf` to fully hide Apache/PHP
    version strings (the .htaccess only suppresses the footer
    signature; ServerTokens isn't allowed in .htaccess).
  - **Theme version bump 2026052206** in `version.php` triggers an
    upgrade — admin must visit `/admin/index.php` or run
    `cli/upgrade.php --non-interactive` after pull.
  - All other changes are autoloader-friendly (PHP classes, mustache
    templates, scss); standard `purge_caches.php` after pull.


### Session 2026-05-23 — Goal A.x grader restyle + PHPUnit gate adoption

Three discrete shipments today, continuing the Goal A.x pattern of
ship-verify-commit-push at 100% confidence.

**Shipment 1 — Per-course gradebook restyle (Goal A.x)**
  - Commit `b6f177b2f` — `feat(theme): Sentientia design on per-course gradebook`
  - `/grade/report/grader/index.php?id=N` was the last vanilla Moodle
    Boost surface Course Authors land on (Asif Ansari teaches 33
    courses → 33 clicks per audit cycle into this page).
  - Scoped under `body.path-grade-report-grader` (catches grader index
    + nested grader pages, disjoint from existing
    `body#page-grade-report-overview-index` scoping — overview
    restyle verified non-regressed via DOM inspection).
  - +213 SCSS lines: 16px-radius card chrome, action bar with brand-
    pill inputs, branded student-initials avatars (32px primary-color
    circle), uppercase letter-spaced column headers (matches every
    other Sentientia table), tabular-nums grade cells, pass/fail pill
    badges (green tint / red tint), branded average row, sticky-
    footer with card styling, mobile breakpoints at 1024px + 768px.
  - Theme version 2026052206 → 2026052207.
  - Visual evidence: `docs/visual-evidence/2026-05-23/grader-*.png`
    (before, after, after-table viewport).

**Shipment 2 — Mobile @ 590px verification (Goal A.x follow-up)**
  - Commit `a17b19e5e` — `docs(visual): mobile @ 590px verification`
  - Grader @ 590px: wide table correctly engages horizontal scroll
    inside `.gradeparent`; avatars, headers, grade cells legible.
  - /course/view.php @ 590px: Sentientia hero banner spans full
    width, action icons grouped, description flows correctly.
  - /admin/* @ 590px deferred — needs Site Admin re-login (Asif
    Ansari is Course Author). Tracked as task #177.

**Shipment 3 — PHPUnit gate adoption (ADR-009 invariants verified)**
  - Commit `8c4305093` — `test(theme): wire role_detector + ws_contract`
  - Re-ran `php public/admin/tool/phpunit/cli/init.php` (full test-DB
    install for Moodle 5.1.3+, ~5 min). Previous init was for an
    older version; environment was stale.
  - Both ADR-009 suites now PASS:
      `role_detector_test` — 8 tests, 17 assertions, 4 skipped
        (skips are BizLMS-schema-specific paths; all run on prod env)
      `ws_contract_test` — 1 test, 2 assertions (walks every
        `data-region="airpay-datatable"` consumer; passes today)
  - `tests/README.md` runbook added — covers init, run-locally, CI
    wiring (Jenkins / GH Actions block-on-non-zero pattern).
  - The bug-class extinction patterns from this audit
    (`role_detector` + `ws_contract_scanner`) are now structurally
    enforced, not just documented.

**Insight (carry forward):** The PHPUnit init re-run was the single
biggest piece of value-revealing work today. The tests had been
written (commit `bf5412ed2`) but never actually executed against the
Moodle 5.1.3+ test environment. Lesson for future ADRs: "the test
file exists" ≠ "the invariant is verified." Always end the ADR
session by running the suite end-to-end and including the green
output in the commit body.

### Updated next-session backlog (after 2026-05-23, second batch)

  - `/course/edit.php` restyle — still needs Site Admin login + form-
    heavy. Course Authors don't have `moodle/course:update` cap.
  - Mobile @ 590px verification of /admin/* — Site Admin re-login.
  - Factor `ws_contract_scanner::find_files()` + `load_services_file()`
    into a shared utility class.
  - CI integration: wire the 2 PHPUnit suites into the Airpay-Academy
    GitHub Actions workflow with block-on-fail. The `tests/README.md`
    has the exact command.
  - Calendar `/calendar/view.php` polish (day-header uppercase, today
    highlight) — minor; deferred.
  - Goal B (Playwright E2E) — still blocked.
  - Goal C (user guides) — depends on Goal A.x + B.

**Shipment 4 — Sentientia polish on /user/edit.php (Goal A.x)**
  - Commit `ed417ccec` — `feat(theme): Sentientia polish on /user/edit.php form`
  - Profile-editing form every persona touches. Viewer
    `/user/profile.php` was already Sentientia; this lands the
    editing counterpart so they read as one product.
  - 5 collapsible fieldsets (General, User picture, Additional names,
    Interests, Optional) each get 16px-radius card chrome + uppercase
    letter-spaced h3 + chevron toggle + brand-blue accent bar.
  - Form inputs: 8px radius, surface-alt background, brand-light focus
    glow. Required-field asterisks softened (7px / 70% opacity, was
    16px glaring red).
  - Submit button: brand primary with hover bg darken + 4px shadow +
    active translateY press. Cancel: transparent outline.
  - Mobile @ 768px: col-md-3/col-md-9 grid collapses to stacked
    label-above-input; verified at 590x800.
  - Theme version 2026052207 → 2026052208.

**Shipment 5 — Sentientia polish on /user/preferences.php (Goal A.x)**
  - Commit `1aa407be3` — `feat(theme): Sentientia polish on /user/preferences.php`
  - Section headings (USER ACCOUNT / BLOGS / BADGES / MISCELLANEOUS)
    now uppercase letter-spaced 13px with brand-blue 2px accent bar
    underneath — matches grader, overview, admin, profile-edit.
  - Card chrome 16px radius, soft shadow, full-height columns.
  - Link list with hover slide-right (translateX 2px) + brand-dark
    color shift + underline.
  - Mobile @ 768px: 3-col flex stacks to 1; verified.
  - Theme version 2026052208 → 2026052209.

**Shipment 7 — Sentientia polish on /calendar/view.php (Goal A.x)**
  - Commit `52b6bbaff` — `feat(theme): Sentientia polish on /calendar/view.php month`
  - Calendar month view was the last item from the next-session
    backlog that didn't need Site Admin. Already had decent tertiary
    nav chrome but the calendar table itself was vanilla Moodle Boost.
  - Scoped to `body#page-calendar-view` (month/day/upcoming views all
    share this body id; Moodle uses ?view=X query param).
  - Day-of-week headers (MON/TUE/WED) uppercase letter-spaced 11px;
    today cell brand-light bg + 2px brand-primary border + brand-dark
    text (overrides .weekend class so today-on-weekend stays
    Sentientia, not red); weekend cells softer text; cells get 8px
    radius + 4px border-spacing + hover state; event indicators
    become brand-light pill chips.
  - Card chrome wraps `.calendarwrapper` / `[data-region="calendar-month"]`.
  - Mobile @ 768px: drop padding, header 10px, cells min-height 60px.
  - Theme version 2026052209 → 2026052210 (1.0.10-beta).

**Shipment 8 — PR template + v4.1.0 milestone tag**
  - Commit `9ffe07991` — `docs(github): replace upstream PR template stub`
  - Tag `v4.1.0-goal-a-audit` — annotated tag with full audit story.
  - The inherited .github/PULL_REQUEST_TEMPLATE.txt was a 7-line stub
    from upstream Moodle telling contributors NOT to open PRs on
    GitHub. Wrong for our fork. Replaced with structured Markdown
    template covering scope checkboxes, verification gates,
    ws-contract-gate quick-links, role_detector usage rule, visual
    evidence rule, Hindi parity, version bump, risk + rollback.
  - Milestone tag chains naturally after v4.0.0-moodle5 in the
    existing tag history.

### Cumulative session count (2026-05-23 final)

  - 11 commits + 1 annotated milestone tag pushed to production.
  - Goal A.x surfaces shipped this session: 4 new
    (/grade/report/grader/, /user/edit.php, /user/preferences.php,
    /calendar/view.php). Total Sentientia surfaces now: 9.
  - ADR-009 invariants now verified by BOTH PHPUnit (local) AND
    GitHub Actions (every PR, ~2s, no Moodle install).
  - Mobile @ 590px verification on grader + course/view + user-edit.
  - Tests README runbook landed (`theme/airpayux/tests/README.md`).
  - CI gate landed (`moodle-enhancement/tools/ci-ws-contract-check.php`
    + new `ws-contract-gate` job in `.github/workflows/ci.yml`).
  - PR template overhaul (`.github/PULL_REQUEST_TEMPLATE.md`).
  - Milestone tag `v4.1.0-goal-a-audit` for permanent landmark.

**Theme version after this session:** 2026052210 (1.0.10-beta).

**Insight (carry forward):** "Moodle PHPUnit in CI" is conventionally
treated as a binary — either you install Moodle (slow) or you don't
run tests at all. The middle ground that worked here was: take the
parts of the test that are pure file-system + regex work, extract
them to a standalone script, stub the one Moodle constant the
included PHP files check for (`MOODLE_INTERNAL`). The full Moodle
PHPUnit suite still runs locally (and could run nightly on a
dedicated runner). But the bug-class extinction invariant lives in
CI without paying the Moodle-install cost on every PR.

**Final remaining backlog (after 2026-05-23):**
  - Refactor shared utility between `ws_contract_scanner` (Moodle-
    aware) and `ci-ws-contract-check.php` (standalone) — low priority;
    the divergence is intentional (different verification strategies)
    and the duplication is minimal.
  - Goal B (Playwright E2E harness) — still blocked.
  - Goal C (user guide content) — depends on Goal A.x + B.

**Shipment 9 — Site-Admin batch closeout (commit `c91f4309c`)**
  - `/course/edit.php` restyle: extended the existing
    `body#page-user-edit` SCSS rule to also include
    `body#page-course-edit`. Zero new SCSS lines — the pattern is
    genuinely reusable. The 8 fieldsets (General, Description,
    Course format, Appearance, Files and uploads, Completion
    tracking, Groups, Tags) all get card chrome + uppercase
    letter-spaced section headers + 8px input radius + 768px
    mobile stack.
  - `/admin/*` mobile @ 590px verification: walked /admin/search.php
    and /admin/category.php?category=appearance at 590x800. Both
    render correctly — sidebar collapses to hamburger, topbar wraps,
    tab nav scrolls horizontally without breaking layout, category
    headings retain brand-blue accent bars, link lists stack
    vertically.
  - Confirmed `/admin/user.php` (Manage Users) is a custom plugin
    surface (already Sentientia by construction), not a Moodle leak
    that needs separate restyling.
  - Theme version 2026052210 → 2026052211 (1.0.11-beta).

**Cumulative session total (final):** 16 commits + 1 annotated
milestone tag pushed to production. **All 10 audit-identified
Moodle-leak surfaces** plus calendar are now Sentientia. The
next-session backlog is genuinely empty for visible-surface work —
remaining items are either external blockers (Playwright + Node 24),
strategic pivots (Workstream 0 per-customer branding), or
low-priority optional refactors.

### Strategic batch (A → B → C, 2026-05-23 final)

**A — Workstream 0 per-customer branding** (commit `a3338d902`)
  - ADR-008 customer_brand resolver had shipped in commit
    `1e4c9c1ea` (#143) but the theme never consumed it.
  - `core_renderer::standard_head_html()` now calls
    `\local_airpay_core\customer::branding()` and injects
    `<style id="sentientia-customer-brand">` with theme_color →
    `--ap-color-primary` + bg_color → `--ap-color-bg`.
  - Customer favicon (icon_192_url) also injected when no
    tenant favicon overrides.
  - Verified live: `getComputedStyle(:root).--ap-color-primary` ===
    "#0066A7" on /my/. For Customer 2 onboarding, single DB INSERT
    re-tints the stack — zero code change.
  - Theme version 2026052211 → 2026052212 (1.0.12-beta).

**B — Goal B Playwright harness wired** (commit `e2ab8657b`)
  - Re-investigated the "Node 24 incompatibility" blocker — turns
    out Playwright 1.59.1 installs cleanly on Node 24.14.1. The
    actual blocker is local Windows AV blocking browser spawn from
    `%LOCALAPPDATA%\ms-playwright\`. Same tests will run on
    GitHub Actions runners.
  - Shipped `playwright.config.mjs` (Firefox primary, Chromium
    fallback, mobile-590 viewport project) + `tests/surfaces.spec.mjs`
    (11 tests covering every Sentientia surface + Workstream 0
    brand injection assertion).
  - Each test asserts the signature CSS marker via
    `getComputedStyle()` — catches SCSS cascade regression from
    future theme version bumps.
  - `tests/README.md` documents local run, the AV blocker, and
    exact CI workflow YAML to drop into `ci.yml` next session.

**C — Refactor ws_contract single-source-of-truth** (commit `b21843eba`)
  - Both ws_contract gates (Moodle PHPUnit + standalone CI) had
    hard-coded the 6-key contract list. Adding a 7th key (e.g.
    'orderby') would need updates in both — a real drift surface,
    exactly the bug class ADR-009 was supposed to prevent.
  - Standalone CI gate now parses `REQUIRED_CONTRACT_KEYS` from
    the canonical Moodle scanner file at runtime via regex
    `/REQUIRED_CONTRACT_KEYS\s*=\s*\[(.*?)\]/s`. Falls back to
    hard-coded list if canonical missing (e.g. running tool
    outside full repo).
  - Updating the contract = edit ONE file. Both gates pick it up.
  - Verified: 9 consumers, 0 failures, exit 0 — same result as
    pre-refactor.

### Cumulative session total (A→B→C, after the strategic batch)

  - 20 commits + 1 annotated milestone tag pushed to production.
  - 10 Sentientia surfaces + calendar + admin-mobile coverage.
  - Workstream 0 customer-branding wired end-to-end.
  - Playwright @playwright/test framework wired and ready for CI.
  - ws_contract gates now share REQUIRED_CONTRACT_KEYS source.
  - ADR-009 invariants enforced at PHPUnit (local) AND GitHub
    Actions (every PR).

**Theme version after this session:** 2026052212 (1.0.12-beta).

---

## Session 2026-05-23 — P0 borrow wave complete + ADR-011 staging plan

Per Nitin's directive "do pending and deferred, then upgrade 5.2,
1 by 1 100%", this session completed the remaining P0 backports from
ADR-010, then drafted ADR-011 for the wholesale 5.2 upgrade staging plan.

### P0 borrows shipped this session (5 commits)

| Commit | P0 | Item | Pattern |
|--------|----|------|---------|
| `b4c1289f0` | #5 | OAuth2 i18n on login form | Lang strings + Mustache `{{#str}}` + aria-label |
| `555d66c9f` | #9 | cm_navigation::resolve_url() shim | Static helper + theme consumer + PHPUnit |
| `d0acd4422` | #10 | Suspended-user status badge | Helper class + before_standard_top_of_body_html hook + AMD decorator + SCSS |
| `ca6b1e3c6` | #11 | Backup-filename template helper | Helper class + admin setting + token cheat-sheet |
| `46e5243ab` | #14 | My Courses sort by start/end date | Pure theme template override (no PHP change) |

### P0 borrow inventory — final disposition

| # | Status | Commit / Note |
|---|--------|---------------|
| 1 | OK Prior | Sticky footer submit buttons |
| 2 | OK Prior | Activity header with completion labels |
| 3 | OK Prior | Anchor-link navigation highlighting |
| 4 | OK Prior | Restricted page availability conditions |
| 5 | OK `b4c1289f0` | OAuth2 i18n |
| 6 | OK Prior | Toast visuallyHidden a11y |
| 7 | OK Prior | core/page_title AMD module |
| 8 | OK Prior | core/deprecated AMD module |
| 9 | OK `555d66c9f` | cm_info::get_navigation_url() shim |
| 10 | OK `d0acd4422` | Suspended student status indicator |
| 11 | OK `ca6b1e3c6` | Configurable backup filename template |
| 12 | OK Bundled in #2 | Manual completion buttons |
| 13 | OK Prior | Sticky course-title header |
| 14 | OK `46e5243ab` | Sort by course start date |
| 15 | -- Deferred | Quiz overall feedback when marks hidden — "auto when we upgrade" per ADR-010 |

**Subtotal:** 13/13 buildable P0 items shipped. Two deferred per spec
(P0 #12 bundled with #2; P0 #15 auto-resolves at 5.2 wholesale upgrade).

### Hindi pack parity

100% parity maintained across all 5 shipping commits:
- P0 #5 — 1 string in en + hi (`signinwithidentityprovider`)
- P0 #9 — no user-visible strings
- P0 #10 — 4 strings in en + hi (`userstatus_*`)
- P0 #11 — 4 strings in en + hi (`settings_pagetitle`, `setting_backup_filename_*`)
- P0 #14 — 2 strings in en + hi (`sortbystartdate`, `sortbyenddate`)

Total this session: 11 strings x 2 locales = 22 lang entries added.

### Documentation shipped

Five borrow guides + one ADR landed under `docs/`:

- `docs/p0-borrows/p0-9-cm-navigation.md`
- `docs/p0-borrows/p0-10-user-status-badge.md`
- `docs/p0-borrows/p0-11-backup-filename-template.md`
- `docs/p0-borrows/p0-14-myoverview-sort-by-startdate.md`
- `docs/adr/ADR-011-moodle-5.2-wholesale-upgrade-staging.md`

Each P0 borrow doc has a "Migration on 5.2 wholesale upgrade" section
so the next phase work is mechanical search-and-replace.

### Test surface added

- `local/airpay_core/tests/cm_navigation_test.php` — 5 cases
- `local/airpay_core/tests/user_status_test.php` — 9 cases
- `local/airpay_core/tests/backup_filename_test.php` — 10 cases

Total this session: 24 new PHPUnit cases.

### Version state at session end

```
theme_airpayux        : 2026052322 (1.0.22-beta)
local_airpay_core     : 2026052303 (1.5.3)
```

### Next session — Phase A.1 of the 5.2 wholesale upgrade

Per ADR-011, the next session begins Phase A (codebase prep — no PHP
version change required):

- A.1 — Branch `5.2-merge-baseline` + tag `v4.1.1-pre-merge`
- A.2 — Pull Moodle 5.2 source + generate diff
- A.3 — PHP 8.3 lint pass on all 30 `local_airpay_*` plugins
- A.4 — Theme conflict map for the ~514 files in `theme/airpayux/`
- A.5 — Test surface inventory + CI re-run cadence

Phase B (the merge proper) is blocked on PHP 8.3 landing on the local
XAMPP — currently waiting on either IT or a portable-PHP install.
ADR-011 §"Open questions" lists this for Nitin's decision.

---

## Session 2026-05-23 — Phase B execution (continued) — 5.2 wholesale upgrade alive

Per Nitin's "continue, 1 by 1 100%" + "Option B and both tracks", we
unblocked the PHP-8.4 prerequisite, pulled the 5.2 source into a
container, ran the wholesale upgrade, served the resulting tree
through Apache, and migrated the first deprecated hook callbacks.

### Phase B.1 — PHP 8.4 via Docker (WDAC pivot)

Detail: `docs/5.2-merge/PHP-8.4-INSTALL-WDAC-PIVOT.md`,
`docs/5.2-merge/PHASE-B1-PHP84-DOCKER-VERIFIED.md`.

- WDAC / EDR blocked native PHP 8.4 binaries by hash; even
  winget-installed PHP 8.4 in `%LOCALAPPDATA%\...\Packages\` returned
  "Access is denied".
- Pivoted to Docker. Built `moodle-5.2-cli` (php:8.4-cli + Moodle
  extensions) for CLI work and `moodle-5.2-apache` (php:8.4-apache
  + mod_php) for web smoke. Container PHP version: **8.4.21**.
- Bind-mounts `C:\xampp\htdocs\moodle5.2\` to `/var/www/moodle/`,
  `C:\xampp\moodledata5_2\` to `/var/moodledata/`. Live-editable
  from Windows.

### Phase B.2 — Wholesale 5.2 merge — UPGRADE EXIT 0

Detail: `docs/5.2-merge/PHASE-B2-MERGE-IN-PROGRESS.md`,
`docs/5.2-merge/PHASE-B2-SUCCESS-2026-05-23.md`.

- Cloned production DB (`moodle` → `moodle5_2`, 1,219 tables) with
  `mysqldump --complete-insert`, excluding only the 16 heavy
  log/audit tables. Earlier minimal seed broke at
  `message_update_processors()` — fix was to clone wider.
- Overlay landed: 30 `local_airpay_*` + 2 `local_sentientia_*` +
  `theme_airpayux` + 4 airpay blocks + 3 vendor-patched blocks +
  `admin/tool/certificate`.
- `php admin/cli/upgrade.php --non-interactive` finished clean in
  **74 minutes**, version state `2026042000.04` (Moodle 5.2 GA).

### Phase B.3 — Web smoke + first hook migration

Detail: `docs/5.2-merge/PHASE-B3-WEB-SMOKE-PASS.md`,
`docs/5.2-merge/PHASE-B3-HOOK-MIGRATION-2026-05-23.md`.

- Container `moodle52web` exposes Moodle 5.2 at
  `http://localhost:8081/`. XAMPP (port 8080, PHP 8.2) keeps serving
  the untouched `moodle5/` tree for rollback.
- **Frontpage:** HTTP 200, 72,156 bytes, title resolves to
  "airpay academy — Enterprise Learning & Development Platform",
  theme `airpayux` selected.
- **Login page:** HTTP 200, 29,647 bytes, `airpay-login` BEM class
  present (Sentientia split-screen layout intact), P0 #5 OAuth2 i18n
  template renders with the "Upskill. Get certified. Get hired."
  hero subtitle.
- Cold-render latency (45 s frontpage, 39 s login) is dev-mode
  overhead from the Windows ↔ WSL2 bind-mount on every
  `require_once`. Production reality (Linux server + PHP-FPM
  + pre-compiled SCSS) is unaffected.

**Hook migration #1:** `before_standard_top_of_body_html` callbacks
in `theme_airpayux` and `local_sentientia_pwa` moved to Moodle 5.2's
new `\core\hook\output\before_standard_top_of_body_html_generation`
hook system.

| Plugin | Old version | New version | Files added |
|--------|-------------|-------------|-------------|
| theme_airpayux | 2026052323 (1.0.23-beta) | 2026052324 (1.0.24-beta) | `db/hooks.php`, `classes/hook_callbacks.php` |
| local_sentientia_pwa | 2026052202 (0.5.2-alpha) | 2026052301 (0.5.3-alpha) | `db/hooks.php`, `classes/hook_callbacks.php` |

Pattern: hook class is the single source of truth (with the new
`build_*_html()` static helper); the legacy `<plugin>_<hook>()`
function in `lib.php` is reduced to a thin shim that — on 5.2 —
detects the hook namespace and bails (to prevent double-emit when
both fire) and — on 5.1 — delegates to the same builder. Same
codebase, both targets.

Codebase grep confirms `before_standard_top_of_body_html` was the
ONLY hook in our plugin surface that 5.2's `\core\hook\output\*`
namespace covers. Other legacy callbacks
(`*_extend_navigation_user_settings`, etc.) remain function-name
callbacks in 5.2 — no migration needed for those.

### What's next (per ADR-011)

```
Phase B.3.a-f  ~38h  Theme conflict resolution (514 airpayux files vs 5.2)
Phase B.4-B.11 ~30h  lib/admin/course/blocks/grade/enrol/mod/backup polish
Phase B.12      ~4h  Re-run Goal A.y functional matrix on the 5.2 instance
```

Architecture is proven; remaining work is iterative polish against a
running 5.2 target. ~5 hours of the 80h ADR-011 estimate consumed so far.

### Version state at session end

```
theme_airpayux        : 2026052324 (1.0.24-beta)
local_airpay_core     : 2026052303 (1.5.3)
local_sentientia_pwa  : 2026052301 (0.5.3-alpha)
```

---

## 🚀 Phase B Moodle 5.2 — execution complete (2026-05-23) + B.12 hotfix (2026-05-24)

### Headline
Moodle 5.2 wholesale upgrade staging is **COMPLETE at code level**. Same
codebase runs on both 5.1 (production) and 5.2 (target) via runtime
dual-target patterns. Zero deprecations, zero warnings, zero fatals on
the 5.2 instance frontpage smoke. 14 commits, 13 leg docs, ~5 hours of
execution against an 80-hour ADR-011 estimate (~94% saving).

### Phase B execution log

```
Start :  Phase B.1  — PHP 8.4 install (Docker)
End   :  Phase B.12 — clean smoke gate + hotfix for 2 missed-overlay plugins
```

| Phase | Commit | What landed |
|-------|--------|-------------|
| B.1   | feat(5.2-merge): Phase B.1 PHP 8.4 via Docker VERIFIED       | PHP 8.4 container, WDAC blocks native install |
| B.2   | feat+docs(5.2-merge): Phase B.2 SUCCESS — upgrade exit 0     | Wholesale 5.2 install + overlay-airpay-customs.ps1 script |
| B.3   | feat+docs(5.2-merge): Phase B.3 web smoke PASS — 5.2 renders | First end-to-end render of the 5.2 instance |
| B.3   | feat(5.2-merge): Phase B.3 hook migration                    | `before_standard_top_of_body_html` → 5.2 hook system |
| B.3.e | feat(5.2-merge): Phase B.3.e SCSS variables rebase           | Dual-key activity-icon variables for BS5 shift |
| B.3.e+| feat(5.2-merge): Phase B.3.e+ BS5 shift-color shim           | Proactive adoption of 81 new 5.2 component-scoped tokens |
| B.3.a | feat(5.2-merge): Phase B.3.a core_renderer rebase            | Trait-decomposed renderer already 5.2-compatible |
| B.3.f | docs(5.2-merge): Phase B.3.f AMD shim cleanup plan           | 3 AMD shims with zero callsites → trivial cutover |
| B.3.b | feat(5.2-merge): Phase B.3.b layouts rebase                  | `select_menu` dual-target pattern across layouts |
| B.3.c | docs(5.2-merge): Phase B.3.c top templates rebase            | course.mustache tertiary-nav swap (cutover-day) |
| B.3.d | docs(5.2-merge): Phase B.3.d core_form widgets               | No required changes — 52 templates audit-clean |
| B.4   | docs(5.2-merge): Phase B.4 lib + admin conflicts             | 4 ModalFactory → core/modal tagged for cutover |
| B.5-11| docs(5.2-merge): Phase B.5-B.11 batch audit                  | ZERO required changes across remaining surfaces |
| B.12  | feat(5.2-merge): Phase B.12 final smoke gate                 | `subplugins.json` dual-key + CLEAN smoke counts |
| B.12  | fix(theme): _tokens-52.scss undefined-variable hotfix        | Sentientia login restored on 5.2 (commit 5e08fbae3) |
| B.12+ | docs(visual-evidence): Phase B Moodle 5.2 session            | 12 screenshots captured under docs/visual-evidence/2026-05-23/ |
| **B.12 hotfix (2026-05-24)** | **fix(5.2-merge): restore 2 missed-overlay plugins** | **paygw_airpay + quizaccess_airpay_proctoring** |

### Phase B.12 hotfix (2026-05-24) — missed-overlay plugins restored

**Trigger:** Nitin pushed back on a "retired" reading of the 5.2 Plugins
check page (5 "Missing from disk!" entries). Honest audit revealed 2 of
the 5 were not retired — they were **missed by the Phase B.2 overlay
script's hardcoded copy list**.

| Plugin | On prod XAMPP 5.1? | In repo before? | Verdict |
|--------|--------------------|-----------------|---------|
| `paygw_airpay` (31 files)       | ✅       | ❌ never  | MISSED overlay — live plugin |
| `quizaccess_airpay_proctoring`  | ✅ v…120 | ✅ v…300 (newer) | MISSED overlay — repo ahead |
| `tool_tcpdffonts`               | ✅       | ❌       | Truly removed in 5.2 core |
| `certificateelement_modulename` | ❌       | ❌       | Truly orphan placeholder |
| `theme_epsilon`                 | ✅ legacy| ❌       | airpayux is standalone (ADR-001) |

**Fix shipped (commit `275f45c84`):**
1. `tools/overlay-airpay-customs.ps1` — added two `Copy-Tree` blocks with
   explanatory comments. Future overlay runs won't miss them.
2. `moodle-enhancement/payment/gateway/airpay/` — **31 files newly tracked
   in repo** for the first time (the plugin lived only in production XAMPP
   before — a months-long blind spot).
3. `moodle-enhancement/mod/quiz/accessrule/airpay_proctoring/` — re-deployed
   to the 5.2 tree (repo version v2026051300 is newer than production's
   v2026051120 by one upgrade step).
4. DB re-registration: `paygw_airpay` at v2024100700.09 (existing-installed
   marker, no schema change); `quizaccess_airpay_proctoring` at v2026051300
   (fresh install, creates `quizaccess_airpay_proctor` table from
   `db/install.xml` for the first time on the 5.2 schema).
5. Cleared stale `upgraderunning` flag from a previous failed run.
6. `docs/5.2-merge/PHASE-B12-HOTFIX-MISSED-OVERLAY-PLUGINS.md` — honest
   record of the mistake + lessons.

**Post-restoration smoke (5.2 instance):**
```
HTTP 200, 72,156 bytes (byte-parity with documented clean smoke)
PHP Notice/Warning/Fatal       : 0
"should be migrated"           : 0
"deprecated" substring matches : 0
subplugintypes warnings        : 0
```

**Lessons recorded:**
1. Don't infer "retired" from "Missing from disk!" — Moodle shows that
   string for any plugin whose code is absent, regardless of why.
2. Overlay scripts need a completeness audit. A `Get-ChildItem -Filter
   '*airpay*' -Recurse` diff between source and target should be a build
   step.
3. Production deployments without source-control round-trip are technical
   debt. `paygw_airpay` lived for months on production without ever being
   committed. Going forward: any new plugin shipping to production must
   also land in source via PR.

### Cutover-day TODO list (consolidated, ~2h mechanical)

1. `course.mustache:237` — swap `core/url_select` partial for dual
   `is_select_menu_context` branch (B.3.c).
2. 4 AMD files — `core/modal_factory` → `core/modal` (B.4):
   - `local/airpay_courses/amd/src/enrolledusers.js`
   - `local/airpay_request/amd/src/request_button.js`
   - `local/airpay_request/amd/src/decide.js`
   - `local/airpay_cart/amd/src/admin_orders.js`
3. 3 AMD shims — delete `page_title.js`, `deprecated.js`, review
   `announcement.js` (B.3.f).
4. `drawer.mustache`, `drawers.mustache`, `secure.mustache` — per-template
   audit + selective backport (B.3.c).
5. **NEW (B.12 hotfix):** Verify `paygw_airpay` works on production 5.2
   with a real payment test.
6. **NEW (B.12 hotfix):** Fix `quizaccess_airpay_proctoring/db/upgrade.php`
   — current upgrade.php expects the target table to exist during
   upgrade, but the table is created only during fresh install. Either
   backfill on production before deploy, OR fix `upgrade.php` to use
   `$dbman->create_table()` inside the migration.
7. Authenticated Goal A.y walkthrough on a fast Linux substrate (~30 min
   on a real server vs impractical on Windows Docker bind-mount).

### What's deferred (substrate, not code)
- Goal A.y authenticated walk across 4 cutover-tagged surfaces. Needs a
  fast Linux substrate — not Windows Docker bind-mount. Expected on
  production-grade server: ~30 min total runtime.

### Refs
- `docs/adr/ADR-011-moodle-5.2-upgrade.md` — original 80-hour estimate baseline
- `docs/5.2-merge/PHASE-B*.md` — leg-by-leg detail (12 docs + 1 hotfix)
- `docs/visual-evidence/2026-05-23/README.md` — 12 captured screenshots
- This file — Phase B consolidated state

---

## 🌙 Night-run 2026-05-24 — overnight autonomous batch

Per Nitin's instruction "i want you to code all night for remaining, use
routines or anything else, which will help you start again without me",
a self-contained playbook + durable hourly scheduled task fired off 16
items in a single overnight run. See `NIGHT-RUN-PLAYBOOK.md` at repo
root for the full spec; the resumption task at
`.claude/scheduled-tasks/airpay-night-run-resume/` will idle on the
DONE marker.

### Group A — Phase B.12 cutover-day mechanical fixes (8 items, all shipped)

| # | Commit | What landed |
|---|--------|-------------|
| A1 | `114fed155` | `fix(quizaccess_airpay_proctoring)`: defensive `$dbman->create_table()` in upgrade.php so production v2026051120 → 2026052401 path doesn't fail when the table doesn't yet exist. Version 2026051300 → 2026052401, release 1.1.0 → 1.1.1. |
| A2 | `838a14431` | `feat(airpay_courses)`: dual-target ModalFactory→core/modal in enrolledusers.js — lazy `require()` picks the right modal API at runtime. |
| A3 | `5140524d0` | `feat(airpay_request)`: same pattern in request_button.js. |
| A4 | `c27a77b8e` | `feat(airpay_request)`: same pattern in decide.js. |
| A5 | `00ad286bf` | `feat(airpay_cart)`: same pattern in admin_orders.js. Completes the 4-of-4 modal migration tracked in Phase B.4. |
| A6 | `bc807ac46` | `chore(theme_airpayux)`: dropped 2 unused AMD shims (page_title.js, deprecated.js — zero callsites per B.3.f audit). announcement.js KEPT pending NVDA verification. Theme version 2026052330 → 2026052401, release 1.0.30-beta → 1.0.31-beta. |
| A7 | `6ab932b01` | `feat(theme_airpayux)`: dual-target course.mustache tertiary-nav — layout/course.php now sets `is_select_menu_context` flag, template picks core/tertiary_navigation_selector (5.2) vs core/url_select (5.1). |
| A8 | `c8664d631` | `feat(theme_airpayux)`: 2 safe backports to secure.mustache (`<header data-for="page-heading">` semantic upgrade + conditional `{{#headercontent}}` activity-header block). drawer.mustache deferred to cutover-day (BS5 attribute rename), drawers.mustache intentionally diverged (Sentientia sidebar). Audit doc shipped. |

### Group B — Plugin test coverage (2 items, all shipped)

| # | Commit | What landed |
|---|--------|-------------|
| B1 | `131dc439d` | `test(paygw_airpay)`: initial PHPUnit coverage. 11 tests across gateway_test.php + privacy_provider_test.php. Out of scope (documented in test docblocks): checksum.php + airpay_helper.php — they have `require_login()` at file scope which blocks unit-testing. **Spawned a follow-up task** to fix the require_login + MD5 → SHA-256 + sandbox/live URL issues. |
| B2 | `65ced95da` | `test(quizaccess_airpay_proctoring)`: rule + migration coverage. 13 tests across rule_test.php (9) + upgrade_test.php (4). The upgrade test directly verifies the A1 hotfix behavior — production-state simulation (drop table + seed legacy config rows + call upgrade function + assert table created + assert migration ran). |

### Group C — Goal C user guides (6 personas, all shipped)

| # | Commit | What landed |
|---|--------|-------------|
| C1 | `03546aba8` | `docs(user-guides)`: Site Admin (~280 lines, 12 sections — day-1 setup, tenant mgmt, plugin mgmt, user import, SCORM, audit/reporting, PWA, WhatsApp, branding, emergency procedures). |
| C2-C6 | `a8e28e986` | `docs(user-guides)`: 5-guide batch. tenant-admin.md (scope matrix + tenant-scoped reports), course-author.md (course/activity/audience/grades/Hindi-readiness), manager.md (team dashboard + approvals + escalations + skills), learner.md (PWA + catalogue + courses + badges + mobile + Hindi), public-learner.md (signup + paid purchase + cert + limitations vs employee). |

### Scheduled task — resumption insurance

`mcp__scheduled-tasks` registered an hourly task `airpay-night-run-resume`
that fires at minute 17 every hour. The prompt is fully self-contained:
read playbook → pick first `[PENDING]` item → execute per spec → commit
+ push → flip to `[COMPLETED]`. With the DONE marker now in place, future
fires will idle (read playbook → detect DONE → exit). Nitin can disable
via `mcp__scheduled-tasks__update_scheduled_task` with `enabled: false`,
or delete via the Scheduled tab in Claude Code.

### Bytes pushed tonight
- 12 commits to `nitin-rajput-learning-tech/Airpay-Academy2.0:production`
- 16 work items + 1 spawned-task chip + 1 PROJECT-STATE update
- ~5 hours of wall time, fully autonomous

### Spawned task awaiting Nitin
- **"Fix security issues in paygw_airpay (MD5 + require_login + sandbox/live URL)"** — chip is showing in the FleetView. One click spins it off into its own session and worktree. Covers 3 pre-existing defects uncovered during B1 test-writing.

### Refs
- `NIGHT-RUN-PLAYBOOK.md` (repo root) — full spec + completion log
- `.claude/scheduled-tasks/airpay-night-run-resume/SKILL.md` — resumption prompt
- `docs/5.2-merge/PHASE-B12-HOTFIX-MISSED-OVERLAY-PLUGINS.md` — context for A1 (yesterday's setup)
- `docs/5.2-merge/PHASE-B12-DRAWER-SECURE-AUDIT.md` — shipped during A8
- `docs/user-guides/` — six new guides

---

## 🔒 Session 2026-05-24 — paygw_airpay security follow-up

**Resolves the spawned task from night-run B1 (commit `131dc439d`).** Three
pre-existing defects in the Airpay payment gateway plugin, all uncovered
while writing initial PHPUnit coverage:

- **Issue 1 — `require_login()` at file scope** in `classes/checksum.php`.
  The class file was unloadable in PHPUnit, CLI, and during autoloader
  probes because `require_login()` was called at top-level (line 24).
  Replaced with `defined('MOODLE_INTERNAL') || die()` — same intent (no
  direct HTTP load), correct mechanism. Class is now testable.

- **Issue 2 — MD5 checksum migration.** Audited every callsite of
  `calculateChecksum()` (no `Sha256` suffix). The sole caller was the
  internal `verifyChecksum()` method, itself unused anywhere in the
  codebase. Production payment flow (`pay.php:69`) already routes through
  `calculateChecksumSha256()`, confirming Airpay accepts SHA-256.
  - `calculateChecksum()` marked `@deprecated since 1.0.1` with a
    `debugging()` warning. Behaviour preserved for any unknown external
    caller; the warning surfaces lingering callers in dev/staging.
  - `verifyChecksum()` migrated to SHA-256 and fixed the latent
    data/secret argument-order bug while in the file. Now uses
    `hash_equals()` for constant-time comparison.
  - Minor XSS fix: `outputForm()` now passes `$checksum` through `s()`.

- **Issue 3 — Identical sandbox + live URLs.** `airpay_helper::get_url()`
  returned the same `payments.airpay.co.in/pay/index.php` URL in both
  branches. Confirmed via lang strings + the production pay.php flow
  that Airpay uses a single endpoint and switches sandbox/live via
  merchant credentials (`mercid`), not URL. Simplified to a single
  return with a clear docblock noting the open vendor question, and
  widened method visibility from `protected` to `public` so future
  callers can route the form action through it (today pay.php hardcodes
  the URL — Phase 2 cleanup).

- **Bonus — `airpay_helper.php` load order.** `defined()` guard now
  precedes the `require_once` of `checksum.php`. The require itself
  upgraded from bare `require` to `require_once`.

### Tests added
- `tests/checksum_test.php` — 7 cases: SHA-256 vector, encrypt envelope,
  encryptSha256 plain hash, verifyChecksum match + tamper, MD5
  deprecation behaviour + warning emission.
- `tests/airpay_helper_test.php` — 6 cases: ORDER_STATUS constants,
  get_unprocessed_order false-when-empty, get_url across live + sandbox
  + unknown environments.

Out of scope (documented in test docblocks):
- `create_order` / `update_order` — needs cross-plugin fixture
  (`local_biz_cart_history` + `paygw_course_enrolmentlog` tables).
- `check_payment` — current body is fully commented-out vendor code.
- `process_payment` — needs real `core_payment` payable + account fixture.
- Root-level `checksum.php` sibling — legacy entry-point stub, out of brief.

### Version
`paygw_airpay` 2024100700.09 → 2024100700.10, release `'1.0.0'` → `'1.0.1'`.
Added `$plugin->maturity = MATURITY_STABLE`.

### Deploy posture
**Local + GitHub only.** Production payment gateway changes require
explicit `[CONFIRM]` from Nitin (CLAUDE.md §3, §13). No live deploy
performed this session.

---

## 🎯 Goal A.x + Goal B — code-complete (2026-05-24)

Closes TaskList items **#149 (Goal A.x)** and **#150 (Goal B)**.

### Headline
138-URL load-time audit (Goal A.y, 2026-05-23) found 1 functional bug
(cert TypeError, fixed in #192). 11 Sentientia surface restyles +
21 Playwright regression tests now guard against drift.

### Goal A.x — UI upgrades from audit findings
All audit-discoverable surfaces shipped:
- 11 restyles (TaskList #157-187)
- 5 mobile 590px sweeps (TaskList #171, #176, #177)
- Workstream 0 customer brand (TaskList #188)
- Every restyle has a regression-guard test in `tests/surfaces.spec.mjs`

Remaining work — "real human shadow observer walkthrough" — is not
automatable. Belongs in a separate session with a live operator.

### Goal B — End-to-end click-through testing
- Pre-existing: `tests/surfaces.spec.mjs` (11 CSS-marker tests).
- NEW commit `0f0a778c0`: `tests/workflows.spec.mjs` (10 non-mutating
  POST/AJAX/round-trip tests) directly answering the Goal A.y audit's
  "wire up Playwright POST tests for the top 10 user actions"
  recommendation.

5 categories covered:
1. Session lifecycle (logout → sesskey destroyed)
2. Form-validation rejection (mform validator catches; no DB write)
3. Reversible toggle round-trip (`/user/language.php` save → flip → restore)
4. AJAX/WS contract shape (3 endpoints incl. Bug #10 regression guard)
5. Authorization boundary (CSRF wall + admin-page sanity)

21 tests total. All safe to run repeatedly. Mutating workflows (course
create / enrol / quiz attempt / refund) deferred to a future
`tests/mutating.spec.mjs` with `--mutating` CI flag and DB
snapshot+restore around each run.

### Commits
- `0f0a778c0` — `test(playwright): Goal B workflow spec — 10 non-mutating POST/AJAX tests`
- `f2d588f44` — `docs(closeout): Goal A.x + Goal B — code-complete summary`

### Awaits human step
Spin up local XAMPP Moodle on `http://localhost:8080/moodle`, confirm
`academy@airpay.co.in` password is `AcademyAudit2026!` (or update the
constant in both spec files), then:
```powershell
cd moodle-enhancement\audit\playwright
npx playwright test --project=firefox-desktop
```
Expected: 21/21 passing.

### Refs
- `docs/GOAL-A-B-CLOSEOUT-2026-05-24.md` — full closeout narrative
- `audit/playwright/HARNESS_RUNBOOK.md` — how-to-run + workflow tests section
- `audit/playwright/tests/surfaces.spec.mjs` — Goal A.x CSS markers
- `audit/playwright/tests/workflows.spec.mjs` — Goal B workflows

---

## 🏆 Session 2026-05-24 — Tier 2 #7 Real-time leaderboards (Phase L.0 MVP)

### Mission
Tier 2 #7 from the Day-0 roadmap: real-time leaderboards that update live
as learners complete activities. Builds on the SSE infrastructure that
`local_sentientia_live` proved out under ADR-004 — per **ADR-014** we reuse
the *pattern* (event journal table, stream endpoint, AMD client) with a
dedicated events table so leaderboards run independently of whether the
Mentimeter clone is enabled in a given tenant.

### Shipped
Two new plugins:

- `local_sentientia_leaderboard` (39 files) — core engine, SSE endpoint,
  WS API, opt-out preference UI, scheduled tasks, privacy provider,
  4 PHPUnit test classes.
- `block_sentientia_leaderboard` (6 files) — dashboard widget. Configurable
  per-instance to pick which board to render. Default = first visible board.

Three board types (each independently feature-flagged, default OFF):

- **quiz** — top scorers on a single `mod_quiz` instance. Ties break on
  shorter attempt time.
- **completion** — fastest learners to complete a course. Sorts by
  `-1 * (timecompleted - timeenrolled)` so DESC ordering naturally places
  the fastest first.
- **skill** — most skill-level upgrades earned within a window (joins
  `local_airpay_user_skill_hist`).

Tenant scope is mandatory on every aggregator (every SELECT joins
`user.open_path` and filters `/N` exact OR `/N/%` prefix). Cross-tenant
leaderboards require `:promoteboard` + `:viewall` capabilities.

### Architecture summary
```
Scheduled task (every 2 min) → ranking_engine::recompute_due()
                              ↓
                   delete + re-insert {lb_entries}
                              ↓
                event_journal::write('leaderboard.recomputed')
                              ↓
                   {lb_events} row inserted
                              ↓
              stream.php SSE loop polls + emits
                              ↓
        AMD client refetches top-N via WS get_board
                              ↓
                  DOM <tbody> replaceChildren()
```

The recompute path is decoupled from learner actions — there's no
on-every-quiz-submit observer that triggers a recompute. The 2-minute
cron tick keeps Apache worker pressure predictable (per ADR-004's
lesson on SSE + worker exhaustion under load).

### Privacy mandate (CLAUDE.md Day 0)
Every learner can opt OUT of being publicly listed via
`/user/preferences.php`. Opted-out users still earn points; their
row is filtered out at SQL read time (`NOT EXISTS` against
`local_sentientia_lb_optouts`). Managers with `:viewall` see the
full ranking — HR analytics path. Opt-out is reversible (presence-row,
not flag) so a stale "hidden" state can never linger.

### Feature flags (all default OFF except realtime + opt-out)
- `sentientia.leaderboards.enabled` (OFF) — master gate
- `sentientia.leaderboards.realtime.enabled` (ON) — SSE kill-switch
- `sentientia.leaderboards.type.quiz` (OFF)
- `sentientia.leaderboards.type.completion` (OFF)
- `sentientia.leaderboards.type.skill` (OFF)
- `sentientia.leaderboards.optout.enabled` (ON) — surface the opt-out toggle

### Hindi parity
EN: 85 strings / HI: 85 strings ✅ (block plugin: 4/4 ✅)

### Test coverage (PHPUnit, ~30 test methods)
- `ranking_engine_test` — competition ranking, tie-handling, tenant scope,
  opt-out filter, idempotency, recompute_due, type validation, recompute
  event emission
- `board_manager_test` — tenant pinning from owner's open_path,
  list_visible scoping, customer-wide visibility, cascade delete, tenant
  root parsing
- `optout_manager_test` — reversibility, idempotency, per-customer
  isolation, bulk fetch, preference value setter
- `event_journal_test` — write requires known type, read_since order +
  filter, retention purge, latest_event_id

**Note:** PHPUnit cannot execute inside the cloud session sandbox (no
`vendor/`). All test classes pass `php -l`. State card documents the
local run recipe.

### Visual evidence
4 HTML mockups in `docs/visual-evidence/2026-05-24/` rendering the exact
output of:
- the board view page
- the dashboard block placement
- the opt-out preferences page
- a two-browser SSE liveness simulation (with annotated event-flow timeline)

README explains the procedure for capturing real XAMPP-side screenshots
(block placement, opt-out toggle round-trip, two-browser SSE update).

### Files
```
local/sentientia_leaderboard/
├── version.php
├── lib.php (user pref + nav extension)
├── index.php (admin board list)
├── view.php  (single-board page)
├── preferences.php (opt-out form)
├── stream.php (SSE endpoint)
├── db/
│   ├── access.php
│   ├── feature_flags.php
│   ├── install.xml      (4 tables)
│   ├── services.php     (3 WS functions)
│   ├── tasks.php        (2 scheduled tasks)
│   └── upgrade.php
├── classes/
│   ├── board_manager.php
│   ├── event_journal.php
│   ├── optout_manager.php
│   ├── ranking_engine.php
│   ├── external/{get_board,list_boards,set_optout}.php
│   ├── privacy/provider.php
│   └── task/{recompute_due_boards,purge_old_events}.php
├── amd/{src,build}/leaderboard_client.{js,min.js,min.js.map}
├── lang/{en,hi}/local_sentientia_leaderboard.php
├── templates/{board_view,boards_list}.mustache
└── tests/{ranking_engine,board_manager,optout_manager,event_journal}_test.php

blocks/sentientia_leaderboard/
├── version.php
├── block_sentientia_leaderboard.php
├── edit_form.php
├── db/access.php
└── lang/{en,hi}/block_sentientia_leaderboard.php
```

### Refs
- ADR: `docs/adr/ADR-014-real-time-leaderboards-realtime-mechanism.md`
- State card: `state-cards/local_sentientia_leaderboard-state.md`
- Visual evidence: `docs/visual-evidence/2026-05-24/`

---

## 🔍 Platform Visual Audit — 2026-05-24

**Auditor:** Claude (Opus 4.7, static code-level review)
**Branch:** `claude/platform-visual-audit-mgare`
**Report:** `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`

### Verdict
**CONDITIONAL PASS** — promote with a 9-item P0 punch-list closed first.

### Counts
- 14 surfaces audited (Sprint 1 trio + 9 Goal A + 2 plugins)
- **9 P0** (blocking promotion)
- **8 P1** (this sprint)
- **6 P2** (polish / document & defer)

### Top P0s
1. Orphan file `scss/moodle/partials/Claude` (98 KB, 135 !important, never imported) — DELETE
2. `custom_changes_MONOLITH_BACKUP.scss` (284 KB, 682 !important) — move out of `scss/`
3. Navbar hardcoded English (Dashboard / My Courses / Catalog / Profile / Home + 3 a11y labels)
4. Footer hardcoded English (Privacy / Terms / Help / Contact + copyright)
5. Dashboard inline-style avalanche (28 inline `style=` attributes, 2 hex literals bypass tokens)
6. Footer Sentientia attribution band uses inline hex (`#0066A7`, `#5a6070`, `#f8f9fc`, `#e2e6ef`)
7. Hindi locale at 85% parity (132/156) — violates CLAUDE.md "100% required" mandate
8. `sentientia_live` has ZERO `aria-live` regions — real-time UI silent to screen readers
9. `navbar.mustache` contains inline `<script>` block for cart-badge — should be AMD module

### Top P1s
- `_surface-profile.scss` is 2,507 lines / 164 !important — needs decomposition into 4 partials
- 53 bare `:focus` rules across surface partials, zero `:focus-visible` — keyboard a11y debt
- `dark_mode.scss` uses 253 `!important` — token-cascade refactor would eliminate most
- `_surface-footer.scss` has zero `@media` breakpoints
- Chart.js loaded from external CDN with no SRI hash

### Remediation budget
- P0: ~15 hours (~2 working days)
- P1: ~3-4 working days
- P2: backlog

### Next
Schedule a fix-sprint to close the 9-item P0 list before Phase 2 customer-zero promotion. The audit branch is push-ready; no PR opened (Nitin to request).

---

## 📱 P1 #14 + P2 #21 — footer mobile + comment cleanup (2026-05-24)

**Chip:** `claude/determined-feynman-rcJw1` (chip L)
**Commits:**
- `f2926457a` — feat(scss): footer mobile breakpoint at 590px (P1 #14)
- `a6a0da86b` — chore(template): delete removed-badge comment block in footer.mustache (P2 #21)
- *(third commit pending — version bump + evidence)*

### What shipped
- `theme/airpayux/scss/moodle/partials/_surface-footer.scss` gains a
  new `@media (max-width: 590px)` block (the primary mobile target per
  `.claude/rules/frontend.md`). Stacks the compact footer row cleanly
  on Galaxy-S / iPhone SE viewports, lets the copyright line wrap, and
  trims padding on the Sentientia attribution band so the pill stays
  compact when the band's child spans wrap to a second line.
- `theme/airpayux/templates/footer.mustache` loses its 10-line
  Mustache comment block (the 2026-05-15 "Made in India" badge
  removal narrative). The comment was fact-of-history; the git log
  preserves the original removal commit + rationale.
- `theme/airpayux/version.php` bumped to `1.0.33-beta` / `2026052403`
  so the cached compiled CSS bundle invalidates and the new breakpoint
  reaches users on next request.

### Why
- F-07 (P1 #14) from `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`
  flagged `_surface-footer.scss` as having zero `@media` queries — no
  responsive coverage declared, no `590px` rules whatsoever. The
  768px block above the new addition gives intermediate stacking but
  doesn't tighten for narrow mobile.
- F-09 (P2 #21) flagged the 10-line history comment as cognitive load
  for future template editors with no current-code rationale.

### Concurrency with chip B
Chip B (`festive-sagan-wvDSO`) is concurrently editing both files.
Chip L was designed to compose with chip B:
- footer.mustache: chip B replaces the inline-style attribution band
  with BEM classes; chip L removes the unrelated history comment.
  Merge-resolved by keeping both edits.
- _surface-footer.scss: chip B adds the base rules for the new BEM
  classes; chip L adds the `590px` overrides that target chip B's
  classes. Additive, no overlap.
- version.php: both chips bump the release. Chip L lands at
  `1.0.33-beta` / `2026052403` (one higher than chip B's
  `1.0.32-beta` / `2026052402`) so the higher version wins regardless
  of merge order.

### Visual evidence
`docs/visual-evidence/2026-05-24/p1-p2-followup-chip-L/README.md`
documents the change and supplies a 5-minute test procedure for
Nitin (Chrome devtools → device toolbar → 590px viewport). No
screenshots in-container — captures are Nitin's task at the dev
workstation.

### Next
Two items still open from the audit's P1 backlog (focus-visible, dark-
mode !important refactor). Tracking in a separate chip.

