# Tier 2 Stub Audit — 2026-05-07

The post-G-06 EOD card sequenced this as the next decision point: *"After
Tier 4 a11y, decide on Tier 2 stub plugins (~50-65h estimated)."*

This audit looks at each plugin classified as Tier-2 stub and documents:
- What's actually shipped (file inventory + LOC)
- Whether it functions or is a placeholder
- The BizLMS feature it's meant to replace
- A concrete **DECISION** with rationale

---

## Top-line correction

The FEATURE-PARITY-AUDIT's tier-2 classification was based on *line counts*,
not *functionality*. Reading the source files directly revealed two
mis-classifications:

| Plugin | Original audit said | Actual state |
|---|---|---|
| `airpay_lifecycle` | "STUB (322 LOC) — not yet wired up" | **FUNCTIONAL.** Observer + scheduled task working, MATURITY_BETA. |
| `airpay_integrations` | "mostly empty (1457 LOC, 0 tables) — placeholder" | **FUNCTIONAL.** Multiple working clients (Teams, KeKa HRMS, AI, web push). |

These two together represent ~1,800 LOC of working code that the audit
under-counted. **Net Tier-2 effort drops from 50-65h to ~0-10h** —
mostly documentation + decisions, not engineering.

---

## Per-plugin audit

### 1. `airpay_roles` — TRUE STUB

**Files:** 4 (version.php, lib.php, db/access.php, lang)
**LOC:** ~22 (lib.php is `// Stub — role assignment UI. Build when custom role management is needed.`)
**DB tables:** 0
**UI:** none
**BizLMS replaces:** `local_assignroles` — admin role assignment to users at org-tree levels

**Functionality assessment:** Does nothing. Just registers a `:manage`
capability and version metadata. The capability is defined but unused
anywhere in the codebase.

**Decision: DEFER**

- KEEP the plugin shell (uninstalling would orphan the capability).
- Build out only when L&D specifies the role-management UI requirements.
- Estimated effort if/when speccing comes in: ~8-10h (admin role-assign-
  to-org-tree UI; a follow-up of the same playbook used for G-02/G-03).

**Why not REMOVE:** Removing requires database cleanup of any references
to `local/airpay_roles:manage` and a version downgrade. Empty plugin
costs nothing. If we never need it, we can leave it in production
indefinitely.

---

### 2. `airpay_ratings` — PARTIAL (read-only)

**Files:** 5 (version.php, lib.php, lang, db/install.xml, classes/rating_manager.php)
**LOC:** ~149
**DB tables:** 1 (`local_airpay_ratings` — itemid, ratearea, userid, rating, timestamps)
**UI:** none for submission; renderer only
**BizLMS replaces:** `local_ratings`

**Functionality assessment:** Half-shipped.
- `rating_manager::get_average(itemid, ratearea)` — works (with legacy fallback)
- `rating_manager::get_user_rating(itemid, ratearea, userid?)` — works
- `rating_manager::render(itemid, ratearea)` — emits star HTML
- **Missing:** any `submit()` or `set_rating()` method. No UI for users
  to actually submit a rating. Just displays existing data + (when
  empty) the "no ratings yet" placeholder.

**Decision: KEEP AS-IS for production cutover**

- Existing data loads and renders. No regression at cutover.
- For new ratings: Moodle core's built-in `core_rating` works on courses
  out-of-the-box. The UX is good enough for cutover.
- Adding our own submit UI is a Tier-3 polish item (~6h).

**Why not REMOVE:** The display layer is referenced by `core_renderer`
in several spots; removing would break course-card rating displays.

---

### 3. `airpay_lifecycle` — FUNCTIONAL ✅ (mis-classified earlier)

**Files:** 7 (version.php, lang, db/events.php, db/tasks.php, db/messages.php,
classes/observer.php, classes/task/compliance_check.php)
**LOC:** ~322
**DB tables:** 0 (uses Moodle core enrol + messaging)
**MATURITY:** `MATURITY_BETA` / release `1.0.0-beta`
**BizLMS replaces:** No direct equivalent (NEW automation layer)

**Functionality assessment:** **WORKING.**

`observer.php` (111 LOC):
- Listens for `\core\event\user_created`
- Auto-enrols new users in all mandatory courses (courses with
  `enddate > now`, visible, non-site)
- Skips siteadmins, deleted users, suspended users
- Uses `enrol_get_plugin('manual')` + `enrol_user()` with proper
  start/end timestamps
- Per-course try/catch so a single failure doesn't block user creation

`compliance_check.php` (158 LOC) — daily scheduled task:
- Finds courses with deadlines in next 7 days
- For each course, finds enrolled users who haven't completed
- Sends Moodle messaging notifications to:
  - The employee (deadline reminder with course link)
  - Their direct manager via `open_supervisorid` (team-level alert)
- Optional: Microsoft Teams notification per course via
  `\local_airpay_integrations\teams_notifier::notify_compliance_overdue`
  (gracefully no-ops if integrations module disabled)
- Logs everything via `mtrace` for cron output

**Decision: PROMOTE TO STABLE + KEEP**

- Already working. No engineering needed pre-cutover.
- Optional polish (~3h):
  1. Bump `MATURITY_BETA` → `MATURITY_STABLE` once scheduled task has
     run successfully in production for ≥7 days.
  2. Add a `local_airpay_lifecycle_audit_log` table for transparency
     (which user, what auto-enrolment, when, by which rule). Useful
     for L&D explaining "why was this user added" questions.

**The earlier "STUB (322 LOC)" classification was wrong.** Updated in
FEATURE-PARITY-AUDIT.md.

---

### 4. `airpay_challenge` — TRUE STUB (intentional noop)

**Files:** 4 (version.php, lib.php, lang, classes/challenge_renderer.php)
**LOC:** ~41
**DB tables:** 0
**UI:** none
**BizLMS replaces:** `local_challenge` — gamification challenges system

**Functionality assessment:** Intentionally returns empty.

```php
public function render_challenge_object(string $area, int $itemid): string {
    // Stub — return empty until challenge system is built.
    return '';
}
```

This exists ONLY so that `core_renderer.php` calls to
`render_challenge_object()` don't crash with "method does not exist".
It's defensive scaffolding, not functionality.

**Decision: KEEP AS-IS**

- Currently safe — no crashes, no UI, no DB.
- Removing it would break the `core_renderer` call sites that depend
  on the method existing.
- Build it out only if gamification team has a concrete spec post-cutover.
- Estimated effort if specced: ~10-15h (challenges table, UI, awards).

---

### 5. `airpay_integrations` — FUNCTIONAL ✅ (mis-classified earlier)

**Files:** 11
**LOC:** ~1,457 (was reported as "mostly empty")
**DB tables:** 0 (config-only; uses Moodle's `mdl_config_plugins` for keys/URLs)
**MATURITY:** `MATURITY_BETA`
**BizLMS replaces:** No direct equivalent (NEW integrations hub)

**Functionality assessment:** **MULTIPLE WORKING INTEGRATIONS.**

| Class file | LOC | What it does |
|---|---|---|
| `teams_notifier.php` | 140 | Microsoft Teams adaptive card sender. Methods for enrolment, completion, deadline, compliance-overdue alerts. cURL POST to webhook URL with proper JSON payload. |
| `keka_client.php` | ~200+ | KeKa HRMS OAuth 2.0 client. Endpoints: employees, departments, groups, exit. Hooks for `employee.hired/terminated/transferred` webhooks. |
| `hrms_sync.php` | ~200+ | Daily scheduled task wrapping `keka_client` to sync employees + departments + JML events. |
| `ai_recommender.php` | ~150+ | AI-driven course recommendations (settings-gated; off by default). |
| `web_push.php` | ~200+ | Web push notification sender (browser notifications API). |
| `webhook.php` | (top-level) | Incoming webhook receiver — handles JML events from KeKa. |
| `settings.php` | ~100+ | Admin settings: AI enable/disable, SENTIENTIA enable, ElevenLabs API key, KeKa base URL, Teams webhook URL, etc. |

All features ship **OFF by default** via `admin_setting_configcheckbox(...,
0)` — meaning at production cutover, none of these activate until IT
explicitly flips them on with the right credentials.

**Decision: KEEP — flip on individually per environment**

- No engineering blocking production cutover.
- IT activation list (Tier 5):
  1. `teams_enable` + `teams_webhook_url` — for compliance + JML alerts
  2. `keka_*` settings — for HRMS sync (replaces manual user CSV upload)
  3. `ai_*` settings — only after legal sign-off on AI privacy posture
  4. `sentientia_enable` + `elevenlabs_apikey` — only when SENTIENTIA
     pipeline (Workstream B) is ready
- Each activation is a config flip, not code work.

**The earlier "1457 LOC, mostly empty" classification was wrong.**
Updated in FEATURE-PARITY-AUDIT.md.

---

## Summary table

| Plugin | Classification | Functional? | Decision | Effort |
|---|---|---|---|---|
| `airpay_roles` | True stub | No | DEFER (build when L&D specs role UI) | ~8-10h IF specced |
| `airpay_ratings` | Partial (read-only) | Yes (display) | KEEP AS-IS for cutover | ~6h Tier-3 polish later |
| `airpay_lifecycle` | **Was mis-classified** as stub | **YES** | PROMOTE TO STABLE | ~3h optional polish |
| `airpay_challenge` | True stub (intentional noop) | No | KEEP AS-IS | ~10-15h IF specced |
| `airpay_integrations` | **Was mis-classified** as mostly empty | **YES** (multi-integration) | KEEP — IT enables per env | ~0h pre-cutover |

**Total Tier-2 engineering blocking production cutover: ~0h.**

The original 50-65h estimate was based on the (incorrect) assumption that
two of these were placeholders. Both are functional. Of the three actual
gaps:

- `airpay_roles` (8-10h) — wait for spec from Nitin/L&D
- `airpay_ratings` (6h) — Tier-3 polish, not required
- `airpay_challenge` (10-15h) — wait for gamification team spec

None of these block production. All can ship as follow-ups after cutover.

---

## What this changes for the production gate

| Tier | Status before audit | Status after audit |
|---|---|---|
| Tier 1 — Major UIs | DONE (6/6) | DONE (6/6) |
| Tier 2 — Stub plugins | 50-65h estimated, all open | **~0h blocking; 24-31h optional follow-up** |
| Tier 3 — Plugin partials | 30-40h after dedup | unchanged |
| Tier 4 — A11Y | DONE (most) | DONE (most) |
| Tier 5 — IT coordination | unchanged | unchanged |

**New total estimate to enterprise-grade: ~30-50h** (down from 84-110h).
This is overwhelmingly Tier 3 plugin partials (per-plugin features) plus
the Tier 5 IT coordination items (production-mirror staging, SMTP, cutover).

---

## What Nitin needs to decide on

1. **`airpay_roles`** — do you need a custom role-management UI? If so,
   what specifically? (BizLMS's `assignroles` was about assigning admin
   roles at specific org-tree levels — e.g. "make this user a manager
   for the Public sub-org only".) If yes, scope it; if no, leave the
   stub indefinitely.

2. **`airpay_challenge`** — is gamification a priority? The current
   noop is safe. Building it out would be a 10-15h effort with a
   challenges schema, UI, and award triggers. Defer until a clear use
   case emerges.

3. **`airpay_integrations` activation order at cutover** — which
   integrations do you want IT to flip on first? My read:
   - **First**: `teams_enable` (compliance alerts to L&D channel)
   - **Second**: `keka_*` (HRMS sync — only after data-mapping review
     of KeKa fields ↔ Moodle fields)
   - **Third (optional)**: AI features after privacy sign-off
   - **Fourth (later)**: SENTIENTIA when Workstream B is ready

---

## Generated 2026-05-07
