# local_sentientia_assistant

AI chat-bot interface. Currently a thin shell that lets a learner ask
questions about platform navigation. The bridge class `core_ai_bridge.php`
binds to Moodle 5's `\core_ai` subsystem when running on Moodle 5+; it
is dormant on Moodle 4.5.

| Field | Value |
|---|---|
| Component | `local_sentientia_assistant` |
| Version | beta 1.0.0 |
| Depends on | `local_airpay_org` |

## What it does (current scope)

- Chat widget rendered in the sidebar.
- Three actions registered with `\core_ai\manager` (Moodle 5):
  `generate_text`, `summarise`, `translate`.
- Conversation log in `local_sentientia_chat_log` with a per-user cache in
  `local_sentientia_chat_cache`.

## Tables

- `local_sentientia_chat_log` — every conversation turn (user + bot).
- `local_sentientia_chat_cache` — per-user context the bot uses for
  follow-up turns.

## Verify after install

```php
php -r "require '/path/to/moodle/config.php'; var_dump(class_exists('\local_sentientia_assistant\core_ai_bridge'));"
# expected: bool(true)
```

## Phase 9 dependency

The AI tutor backlog item in Section 12.4 of the master doc builds on
this plugin. The current shell is the entry point; the AI tutor would
add (a) course-content-aware retrieval, (b) per-learner progress
context, and (c) Compliance Officer guard-rails on regulated content.

## Privacy / GDPR

Chat-log content can contain personal context the learner volunteers.
DSR export bundles the user's chat history; DSR delete redacts both
log and cache.

## Open backlog

- AI tutor full build (Section 12.4 / Decision 13.5).
- Streaming responses (currently the chat blocks until the full answer
  is generated).
- Multi-language conversation (currently English only).
