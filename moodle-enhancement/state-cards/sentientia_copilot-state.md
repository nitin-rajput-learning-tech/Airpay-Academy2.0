# State Card — `local_sentientia_assistant` Agentic Copilot (P1.3)

**Current phase:** P1.3 — Agentic Copilot (upgrade of the nav-assistant)
**Version:** 1.2.0-alpha (2026061600)
**Status:** Feature-complete, BOTH feature flags default OFF, mock-mode demoable, zero live API spend
**Owner:** Nitin Rajput (PM) + Claude (engineering)
**Last updated:** 2026-06-16
**Gap:** Gap analysis §6 P1.3 (`moodle-enhancement/docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md`)

---

## Mission

Upgrade `local_sentientia_assistant` from navigation Q&A into a RAG +
tool-use AGENT over the learner's catalog / skills / progress that can
take ACTIONS — enrol in a course, book an ILT session, surface
gap-closing content — with embedding hooks for WhatsApp and Microsoft
Teams. **The LLM proposes; the platform authorises and executes.**

Backwards compatibility is absolute: when the master flag is OFF (default),
the legacy chat-bubble nav-assistant behaves byte-identically.

---

## Security model (SECURITY-CRITICAL — CLAUDE.md §13)

The LLM NEVER executes anything. The flow is:

1. **Propose** — `agent_client` (mock by default) returns a JSON proposal:
   `{message, tool, args, rationale}`. All of it is UNTRUSTED.
2. **Resolve** — `tool_registry::get()` maps the proposed name to a
   registered tool. An invented/unregistered name returns null →
   audited as `denied_invalid`, never reaches code.
3. **Authorise + run** — `tool::authorise_and_run()` runs a fixed guard
   chain that a tool author cannot skip:
   1. `validate_args()` — cast + bound-check untrusted args (`denied_invalid`)
   2. `require_capability()` at CONTEXT_SYSTEM (`denied_capability`)
   3. tenant scope via `\local_sentientia_platform\tenant::viewer_can_access`
      (`denied_tenant`) — **no action crosses a tenant boundary**
   4. `is_noop()` idempotency check (`noop`)
   5. `execute()` — the ONLY place state changes, after 1–4 pass
4. **Confirm gate** — write tools (enrol, book) only PROPOSE on the first
   turn; they execute only after an explicit learner confirm
   (`agent_confirm` WS). Read-only tools (recommend) auto-run.
5. **Audit** — EVERY outcome (proposed/denied_*/executed/noop/failed)
   writes one immutable row to `{local_sentientia_agent_audit}`.

---

## Feature flags (both DEFAULT OFF)

Registered in `db/feature_flags.php`, resolved by the platform Switchboard:

| Flag | Default | Effect |
|------|---------|--------|
| `sentientia.assistant.agentic.enabled` | OFF | Master switch. OFF = legacy nav Q&A only; the agent loop short-circuits to a disabled no-op and `agent.php` shows a "not available" notice. |
| `sentientia.assistant.agentic.live_api` | OFF | OFF = `agent_client::call_mock()` (deterministic, zero cost, no key). ON = POST to api.anthropic.com (requires `enabled` ON + `api_key` set). |

The pre-existing `ai.assistant.enabled` (platform-registered) is untouched.

---

## Inventory

### Data layer
- `db/feature_flags.php` — 2 new flags (above)
- `db/access.php` — 5 capabilities: `useagent`, `enrol`, `bookilt`,
  `recommend` (learner archetypes), `manageall` (manager only)
- `db/install.xml` + `db/upgrade.php` — new table
  `local_sentientia_agent_audit` (userid, costcenterid, tool, args_json,
  proposed_by, outcome, detail, idempotency_key, timecreated)
- `version.php` — bumped 2026052801 → 2026061600 (1.2.0-alpha)
- `db/services.php` — 2 new AJAX WS: `agent_turn`, `agent_confirm`

### Agent core (`classes/agent/`)
- `tool.php` — abstract base; the un-skippable guard chain orchestrator
- `tool_call.php` / `tool_result.php` — untrusted-proposal + outcome value objects
- `invalid_tool_args.php` — validation exception
- `tool_registry.php` — closed, static, capability-filtered tool set
- `tool/enrol_course.php` — self-enrol current user (self-enrol instance only, tenant-scoped via course open_path, idempotent)
- `tool/book_ilt_session.php` — book current user onto a `local_sentientia_classroom` (class_exists/table-guarded reuse, capacity-aware, idempotent)
- `tool/recommend_content.php` — read-only, tenant-scoped gap recommendations
- `context_builder.php` — tenant-scoped RAG context (own data only)
- `agent_client.php` — mock/live Anthropic client (mirrors aiquiz pattern; injection-hardened system prompt)
- `agent_loop.php` — orchestrates one turn (flag gate → context → propose → resolve → propose-or-run → audit → notify)
- `audit_log.php` — append-only logger + tenant-scoped manager reader
- `integration/channel_hooks.php` — WhatsApp (reuse `notification_bridge`) + Teams (reuse `m365` presence → deep-link) embedding hooks, both class_exists-guarded, mock-safe, NO new live HTTP

### Surface
- `external_agent.php` — `agent_turn` + `agent_confirm` WS (cap + login gated, output format_text-sanitised)
- `agent.php` — full-page copilot surface (flag + capability gated)
- `templates/agent_panel.mustache` — chat UI with confirm/cancel proposal region
- `amd/src/agent.js` + `amd/build/agent.min.js` — panel controller

### Lang (100% parity)
- `lang/en/` + `lang/hi/` — 59 keys each, verified identical key sets, Devanagari values for all new strings

### Tests (`tests/`)
- `agent_loop_test.php` — flag-OFF no-op, mock chat-only, read-only auto-run, propose→confirm→execute, idempotency, audit-row-per-outcome
- `tool_authorisation_test.php` — capability denial, **cross-tenant denial**, prompt-injection (bogus id rejected, unregistered tool unresolvable), non-self-enrollable rejected, recommend tenant-isolation
- `agent_client_test.php` — mock proposal contract, allowed-schema filtering, live fast-fail without key, injection-hardened system prompt

---

## Reused (soft dependencies, class_exists-guarded)
- `\local_sentientia_platform\feature_flags` — flag resolution
- `\local_sentientia_platform\tenant` — tenant root + viewer_can_access + path_filter/sql_filter
- `\local_sentientia_whatsapp\notification_bridge::also_send` — WhatsApp notify (its own master/live flags still gate it)
- `\local_sentientia_m365\graph_client` — Teams presence signal (deep-link only; NO Graph write)
- `\local_sentientia_classroom` roster table — ILT booking

## NO live spend / NO core edits
- Mock mode is the default; live path is double-flag + key gated and never invoked in this build.
- No Moodle core files touched. Not deployed to XAMPP. No PR opened.

---

## Known follow-ups (out of P1.3 scope)
- Privacy provider is still `null_provider`; the audit table is userid-keyed, so a future session should upgrade it to a metadata provider + export/delete (the pre-existing chat_log was already in this state — not regressed here).
- A `book_ilt_session` flow that lists available classrooms (today the learner/LLM must supply a classroom id from context); pair with a future `list_ilt` read tool.
- Manager audit UI page consuming `audit_log::recent_for_manager()`.
- Live-mode smoke CLI (mirror aiquiz `cli/live_smoke.php`) behind [CONFIRM].

## Test run note
PHPUnit not executed in this isolated worktree (no configured Moodle DB).
All 18 PHP files pass `php -l`; lang parity verified (59/59); the
`agent\tool` class vs `agent\tool\*` namespace coexistence verified.
