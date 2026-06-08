# local_sentientia_aiquiz — Sentientia LMS AI Quiz Generation

Tier 1 priority #4 plugin. Course Authors paste source material (SCORM
transcripts, narration text, SOP excerpts) and Anthropic Claude
generates a multichoice quiz draft. Every draft passes through a
mandatory human-review gate (approve / edit / reject per question)
before any approved questions are pushed to a `mod_quiz` activity.

**Status:** Phase G.1 — Hindi + per-customer prompts on top of the G.0
MVP. Feature flag default OFF. Mock-mode demoable end-to-end (English
*and* Hindi) without spending money.

See [ADR-012](../../docs/adr/ADR-012-ai-quiz-generation.md) (G.1 addendum
at the foot) for the architecture record and
[state card](../../state-cards/local_sentientia_aiquiz-state.md) for
current status + open questions.

---

## Quick start (local dev)

```powershell
# 1. Copy plugin to XAMPP
Copy-Item -Recurse "D:\Claude Local\airpay-ld-os\moodle-enhancement\local\sentientia_aiquiz" `
                    "C:\xampp\htdocs\moodle5\public\local\sentientia_aiquiz" -Force

# 2. Run the Moodle installer
cd "C:\xampp\htdocs\moodle5\public"
php admin/cli/upgrade.php

# 3. Purge caches
php admin/cli/purge_caches.php

# 4. (Optional) Flip the master flag ON in the Switchboard:
#    http://localhost:8080/moodle/local/sentientia_platform/admin/switchboard.php
#    Toggle `sentientia.aiquiz.enabled` to ON.
#
#    Leave `sentientia.aiquiz.live_api` OFF for a money-free demo. The
#    pipeline runs against the deterministic mock client.

# 5. Visit the generate page (as an editingteacher or manager):
#    http://localhost:8080/moodle/local/sentientia_aiquiz/generate.php
```

---

## How it works

1. **Course Author submits the generate form.**
   `generate.php` validates (source non-empty, within word cap, no
   Aadhaar/PAN), checks the daily token cap, requires the
   "I confirm I want to call Anthropic" checkbox (the [CONFIRM] gate),
   and persists a `pending` draft row.

2. **The pipeline dispatches to mock or live.**
   `anthropic_client::generate()` inspects the
   `sentientia.aiquiz.live_api` flag. When OFF: deterministic mock
   response. When ON: real POST to api.anthropic.com.

3. **Response is parsed.**
   `response_parser::parse()` decodes strict JSON, drops malformed
   items, normalises to a uniform shape.

4. **Questions persist.**
   `draft_manager::persist_questions()` writes each question with
   `status='generated'` and flips the draft to `status='generated'`.

5. **Reviewer opens `/local/sentientia_aiquiz/review.php?draftid=N`.**
   Approves, edits, or rejects each question individually. Clicks
   "Finalise review" to lock the draft state. (Push-to-mod_quiz is
   stubbed in G.0 — lands in G.4.)

---

## Configuration

| Setting | Where | Default | Notes |
|---------|-------|---------|-------|
| Master flag `sentientia.aiquiz.enabled` | Switchboard | OFF | Hides everything |
| Live-API flag `sentientia.aiquiz.live_api` | Switchboard | OFF | Mock mode when OFF |
| Auto-push flag `sentientia.aiquiz.auto_push` | Switchboard | OFF | Disables push-to-quiz button |
| API key | Site admin → Plugins → Local plugins → AI Quiz | (empty) | Required for live mode |
| Default model | Site admin | `claude-sonnet-4-6` | |
| Max questions per request | Site admin | 10 | |
| Daily token cap (per user) | Site admin | 500,000 | Resets at midnight |
| Max source words | Site admin | 4,000 | ~8 pages |

---

## Running tests

```powershell
cd "C:\xampp\htdocs\moodle5\public"
php admin/tool/phpunit/cli/util.php --install
vendor/bin/phpunit --testsuite local_sentientia_aiquiz_testsuite
# OR run individual files:
vendor/bin/phpunit local/sentientia_aiquiz/tests/prompt_builder_test.php
vendor/bin/phpunit local/sentientia_aiquiz/tests/response_parser_test.php
vendor/bin/phpunit local/sentientia_aiquiz/tests/draft_manager_test.php
vendor/bin/phpunit local/sentientia_aiquiz/tests/anthropic_client_test.php
```

Expected: 4 test classes, 82 test methods (G.0 + G.1), 100% pass
without an API key (everything uses `call_mock()`). The per-customer
config registry is covered separately in
`local/sentientia_platform/tests/customer_config_test.php` (11 methods).

---

## Cost model

When `sentientia.aiquiz.live_api` is ON, every successful generation
charges Anthropic per token. Rough sizing:

- Average source: 1,500 words × ~1.3 tokens/word ≈ 2,000 input tokens
- Average response: 10 questions × ~150 tokens ≈ 1,500 output tokens
- Sonnet 4.6 price: ~$0.003/1K input + ~$0.015/1K output
- Cost per generation: ~$0.03

Daily cap of 500,000 tokens ≈ ~150 generations per user per day.

---

## Multi-tenant isolation

Every draft carries:
- `customerid` — Sentientia LMS customer scope (Phase 1: hardcoded 1).
- `costcenterid` — BizLMS tenant root from creator's `open_path`
  at draft-creation time (1, 77, 177, ...).

`draft_manager::load_for_actor()` refuses to return a draft owned by
a different tenant unless the caller has the `:manage_all` capability.

---

## Hindi (hi) language pack + Hindi quiz generation

100% parity with English (125/125 keys). Every key in
`lang/en/local_sentientia_aiquiz.php` has a corresponding entry in
`lang/hi/local_sentientia_aiquiz.php`. Formal corporate-Hindi register;
technical proper nouns (Anthropic, Claude, Sonnet, Aadhaar, PAN, JSON,
API, SCORM, SOP) kept in Latin script per L&D-content convention.

**Hindi quiz generation (G.1).** The generate form has a language picker
(English / हिन्दी). Choosing Hindi routes the request through the
`v2-hindi` prompt version — a full Devanagari system prompt with a
Devanagari few-shot example — and asks Claude to return questions in
Devanagari. The default picker selection follows the trainer's UI
locale (`current_language()`); they can override per generation. Mock
mode produces Devanagari mock questions so the Hindi flow is demoable
without spending money.

## Per-customer prompt templates (G.1)

Admins can override the system prompt per Sentientia LMS customer at
**Site admin → Plugins → Local plugins → AI Quiz → Per-customer prompt
templates**. A non-empty template replaces the `v1`/`v2-hindi` system
prompt body verbatim (the user-message wrapper still follows the
language picker). The value is stored under
`local_sentientia_platform/customer_<id>_aiquiz_prompt_template` and read on the
generate request via
`\local_sentientia_platform\customer::get_customer_config('aiquiz_prompt_template', $customerid)`.
The generate form's **prompt preview** panel shows the exact resolved
system prompt before the trainer ticks [CONFIRM]. Drafts generated while
a custom template is active record `prompt_version` as `custom:v1` /
`custom:v2-hindi`.

---

## Privacy / GDPR / DPDP

`classes/privacy/provider.php` declares:

- The two DB tables and every field carrying personal data
  (ownerid, sourcetext, reviewed_by, ...).
- The external Anthropic API as a subsystem we transmit data to
  (only when the live-API flag is ON).

The export + delete flows are wired through Moodle's privacy API.
A user-data deletion deletes their drafts + cascades to questions
and nulls out any `reviewed_by` references where they appear as a
reviewer.
