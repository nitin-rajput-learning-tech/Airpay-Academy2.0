# State Card — `local_airpay_assistant`

**Component:** `local_airpay_assistant`
**Version:** `2026052001` / `1.1.1-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live floating AI chat assistant on airpay.academy
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Floating AI chat assistant drawer — answers learner questions about
courses, deadlines, policy, "where do I find X" navigation. Bridges
Moodle context into Anthropic Claude (via `local_airpay_core::ai_client`).
Logs every prompt + response for audit + cost analytics.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_airpay_chat_log` | Per-message audit (user, prompt, response, model, tokens, timestamp) |
| `local_airpay_chat_cache` | Response cache for common questions (cost optimisation) |

## Capabilities

None declared. The drawer is gated by the master feature flag
+ login-required check.

## Feature flags

Consumed (registered in `local_airpay_core`):
- `ai.assistant.enabled` (master switch — default ON)

When OFF, the floating assistant button is hidden everywhere and
`ai_client::send_message()` returns a polite "temporarily unavailable"
response.

## Key files

```
local/airpay_assistant/
├── version.php                                   2026052001 / 1.1.1-beta
├── README.md
├── lib.php
├── settings.php                                   Admin: model, system prompt, token caps
├── ai_demo.php                                    Demo / smoke-test page
├── styles.css
├── classes/
│   ├── ai_client.php                              Anthropic API client (mock + live)
│   ├── core_ai_bridge.php                         Bridges Moodle context (course, dashboard, etc.) into prompt
│   ├── external.php                               WS endpoint for the drawer's send-message call
│   ├── hook_callbacks.php                         Moodle 5.x hook callbacks (drawer injection)
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                2 tables
│   └── upgrade.php
├── templates/                                     Drawer + bubble + message templates
├── amd/                                           Drawer client JS
├── lang/
│   ├── en/local_airpay_assistant.php
│   └── hi/local_airpay_assistant.php
└── (no tests/ directory yet)
```

## Tests

None yet. Manual smoke via `ai_demo.php`. PHPUnit coverage is on the
P1 backlog (see Open Items).

## Open items

- [ ] PHPUnit for `ai_client::send_message` mock path (priority)
- [ ] Cost analytics dashboard — token spend by user / tenant
- [ ] Per-customer system prompt override (today: site-wide setting)
- [ ] Course-context awareness — when invoked from a course page,
      bias the system prompt toward course-specific answers
- [ ] Multi-turn conversation history (today: each message is a fresh
      context)
- [ ] PII redaction layer before sending to Anthropic API
- [ ] Hindi quality validation — current responses may default to
      English even when the UI language is `hi`

## State card created — 2026-05-24

Initial state card. Plugin is the user-facing half of the
`ai.assistant.enabled` flag (registered in `local_airpay_core`).
Created now as part of the P1 state-card pass.

## Role-aware quick-action chips (2026-06-01)
chat_bubble chips were hardcoded + shown to ALL users — a Public/external learner saw
"Team status" (no team) + "Quiz me on compliance". `hook_callbacks::quick_actions()` now
builds chips from `theme_airpayux\role_detector` (manager/admin → Team status) +
`local_sentientia_core\tenant_identity` (Public root 77 → "My certificates" instead of
deadlines/team), both class_exists-guarded; template loops them; labels/queries i18n'd
(en+hi+kn+mr+sw). v1.1.2→1.1.3-alpha. Verified live: Public/non-manager session →
[What to learn next?, My certificates, Quiz me] — no Team status.

## 2026-08-05 — Sentientia AI gateway opt-in migration (v2026080500 / 1.2.1-alpha)

Consumer migration onto `local_sentientia_ai` (ADR-028 Phase 2.3, README
recipe; reference: aiquiz 2026080402), covering BOTH Anthropic surfaces:

- **`ai_client::ask()` (chat, purpose `assistant_chat`)** — routes through
  `client::complete()` ONLY when the gateway exists AND
  `sentientia.ai.gateway.enabled` is ON; default OFF → byte- and
  side-effect-identical legacy path (direct POST with the plugin's own key,
  no ledger writes). This client has no local mock and no live_api flag of
  its own, so with routing ON but gateway live flags OFF learners see the
  gateway's clearly-marked generic mock (honest, zero spend). Its own gates
  (`ai.assistant.enabled` flag, rate limit, response cache) stay enforced;
  on the gateway path only LIVE responses are cached (mock/failed text must
  not linger across a later flag flip). `failed`/`denied` both surface as
  the legacy "having trouble connecting" reply. System prompt extracted to
  a shared `build_system_prompt()` so both paths send identical bytes.
- **`agent\agent_client::propose()` (agentic copilot, purpose
  `agent_reasoning`)** — aiquiz-pattern delegation; the keyword mock passes
  down as the `'mock'` callable (strict proposal-JSON contract preserved);
  `denied` → `failed` so the loop degrades gracefully; the untrusted-data
  fencing moved to a shared `wrap_user_message()` (byte-identical payload
  on both paths). The guard chain (agent_loop + tool::authorise_and_run
  capability/tenant re-checks) is untouched — the gateway carries text,
  never authority. `sentientia.assistant.agentic.*` flags stay
  authoritative for live intent.

**`core_ai_bridge` untouched** — remains the alternative core_ai backend
behind the provider toggle. `'legacy_component'` key fallback wired
(this plugin's `api_key`). Drift closed this session: the top-level local/
copy was stale at 1.1.2-era (missing the entire P1.3 agent surface) → full
dir sync from ME; webroot `external.php`/`external_agent.php` still had the
legacy `\external_api` form → ME's `core_external\` versions deployed.

**Test campaign result (2026-08-05):** suite was RED before this session's
fix — 5 of 6 `agent_loop_test` methods errored on a PRE-EXISTING P1.3 bug:
`agent\context_builder::build()` named `open_path`/`open_designation` in an
explicit-column user SELECT, a fatal on any vanilla/Customer-N schema (the
phpunit site has `course.open_path` but not the user columns). Fixed with a
schema-portable `SELECT *` + null-safe reads — the same class as the aiquiz
`draft_manager` fix (2026-08-04). Suite now GREEN: 22/22, 56 assertions.
`ai_client::build_context()` carries the same coupling PLUS unguarded
`local_costcenter` / `open_supervisorid` / `tool_certificate_issues` reads —
no test exercises it; follow-up task spawned rather than scope-ballooned.


## 2026-09-22 - Real privacy provider (was null_provider)

`\core_privacy\local\metadata\null_provider` is not a neutral default. It is a positive assertion
to Moodle's privacy registry that the plugin stores **no** personal data. This plugin owns
`local_sentientia_chat_log` (every message exchanged with the AI assistant, including the message text) and `local_sentientia_agent_audit`, each keyed on a user id, so under DPDP a subject-access
request returned nothing from it and an erasure request deleted nothing - both reporting success, and
the registry page confirming the plugin held nothing.

Replaced with a full provider (`metadata\provider` + `request\plugin\provider` +
`request\core_userlist_provider`) implementing export, per-user erasure, bulk erasure and
context-wide deletion.

Version bumped to 2026092201 so the cached privacy registry picks up the new tables.

Guarded platform-wide by `local_sentientia_platform\privacy_coverage_test`, which walks every
Sentientia plugin's `install.xml` and fails the build if a plugin declaring a user-identifying column
declares `null_provider`, ships no provider, or declares only some of the tables it owns. Structural
rather than an allowlist, so a new plugin with a copy-pasted `null_provider` fails on its first CI run.


## 2026-09-24 - Correction: 951b20982 wrongly replaced this plugin's privacy provider

The 2026-09-22 survey that found five `null_provider` plugins grepped provider files for the word
`null_provider`. This plugin's provider contained it only in a comment ("Replaces the former
null_provider"). It already had a **real, hand-written provider**: both tables, the export labels, and an
`add_external_location_link('anthropic_api', ...)` declaring that chat messages leave the platform when
live AI is on. The generator overwrote it with a generic one and **dropped that external-location
declaration** - a privacy regression, and the entry for this plugin in the 951b20982 commit message and
in the section above is false.

Restored `classes/privacy/provider.php` and both lang files byte-for-byte from `c74bd55be` (the version
UAT runs), in both trees. Nothing else had touched those three files since. `version.php` stays at
2026092201 - sites that already upgraded to it must not see a downgrade - with its comment corrected to
say there is no functional change against 2026080500.

The other four claims held: compliance_report, courses and exams were genuine 20-line `null_provider`
stubs, and ratings had no provider in either tree.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.
