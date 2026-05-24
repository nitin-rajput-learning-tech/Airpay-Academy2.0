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
