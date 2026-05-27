# local_sentientia_translate — Sentientia LMS AI Content Translation

Tier 1 AI feature. Admins paste English course content and Anthropic
Claude generates a translation into a target language (Hindi, Marathi,
Kannada, Swahili) in the native script, with brand-name preservation —
brands stay verbatim, or render in the target script per a per-customer
override (e.g. "Airpay" → the Kannada-script form). The admin reviews a
side-by-side diff before saving.

**Status:** Phase T.0 — MVP scaffold. Feature flag default OFF.
Mock-mode demoable end-to-end without spending money.

See [ADR-016](../../docs/adr/ADR-016-ai-content-translation.md) for the
architecture record and
[state card](../../state-cards/local_sentientia_translate-state.md) for
current status + open questions.

---

## Quick start (local dev)

```powershell
# 1. Copy plugin to XAMPP
Copy-Item -Recurse "D:\Claude Local\airpay-ld-os\moodle-enhancement\local\sentientia_translate" `
                    "C:\xampp\htdocs\moodle5\public\local\sentientia_translate" -Force

# 2. Run the Moodle installer
cd "C:\xampp\htdocs\moodle5\public"
php admin/cli/upgrade.php

# 3. Purge caches
php admin/cli/purge_caches.php

# 4. (Optional) Flip the master flag ON in the Switchboard:
#    Toggle `sentientia.translate.enabled` to ON.
#    Leave `sentientia.translate.live_api` OFF for a money-free demo.

# 5. As a manager, translate content:
#    http://localhost:8080/moodle/local/sentientia_translate/translate.php
#    Configure brand overrides at:
#    Site admin → Plugins → Local plugins → Brand-name overrides
```

---

## How it works

1. **Admin submits the translate form** (`translate.php`): title + source
   text + target language + the [CONFIRM] checkbox. Four gates checked:
   feature flag ON, `:translate` capability, per-customer daily token cap,
   and the confirm checkbox.

2. **The engine builds the protected-term list.**
   `brand_manager::get_protected_terms()` merges the always-on
   `DEFAULT_PROTECTED` list (Airpay, UPI, RBI, KYC, ...) with any
   customer-configured brand sources. These are fed to Claude with a
   "keep verbatim" instruction.

3. **The pipeline dispatches to mock or live.**
   `anthropic_client::generate()` inspects the
   `sentientia.translate.live_api` flag. OFF → deterministic mock. ON →
   real POST to api.anthropic.com.

4. **Response is parsed.** `response_parser::parse()` decodes strict JSON
   (translated_text + target_lang + brand_terms_preserved).

5. **Brand overrides are applied deterministically.**
   `brand_manager::apply_for()` runs a whole-token, longest-first
   find/replace over the translated text so brand rendering is GUARANTEED
   regardless of model behaviour. This is the testable core of the
   brand-preservation feature.

6. **Admin reviews the diff.** `translate.php?rowid=N` shows source vs
   translation side-by-side with Save / Discard buttons.

---

## Brand-name preservation

Two layers:

1. **Always-protected terms** (`brand_manager::DEFAULT_PROTECTED`) — kept
   verbatim in every translation via the prompt instruction. Curated for
   the fintech-compliance domain: Airpay, Sentientia, UPI, RBI, KYC, PAN,
   Aadhaar, FIU-IND, NEFT, RTGS, IMPS, SCORM.

2. **Per-customer script overrides** (`brand_overrides` table) — a
   `(customer, brand, target-language) → target-script form` map. Applied
   as deterministic post-processing. Example: customer 1 maps
   `Airpay` → the Kannada-script form for `kn`, so every "Airpay" in a
   Kannada translation renders in Kannada script; in Hindi (no override)
   it stays "Airpay".

Substitution is whole-token (so "Airpayment" is never touched) and
longest-first (so "Airpay Payment Services" wins over "Airpay").

---

## Configuration

| Setting | Where | Default | Notes |
|---------|-------|---------|-------|
| Master flag `sentientia.translate.enabled` | Switchboard | OFF | Hides everything |
| Live-API flag `sentientia.translate.live_api` | Switchboard | OFF | Mock mode when OFF |
| API key | Site admin → Plugins → Local plugins → AI Content Translation | (empty) | Required for live mode |
| Default model | Site admin | `claude-sonnet-4-6` | |
| Max output tokens | Site admin | 8192 | Translations can be long |
| Max source words | Site admin | 4,000 | ~8 pages |
| Per-customer daily token cap | Site admin | 3,000,000 | Resets at midnight |
| Brand overrides | Site admin → Brand-name overrides | (none) | Per-customer, per-language |

---

## Supported target languages

| Code | Language | Script |
|------|----------|--------|
| `hi` | Hindi | Devanagari |
| `mr` | Marathi | Devanagari |
| `kn` | Kannada | Kannada |
| `sw` | Swahili | Latin |

---

## Running tests

```powershell
cd "C:\xampp\htdocs\moodle5\public"
php admin/tool/phpunit/cli/util.php --install
vendor/bin/phpunit local/sentientia_translate/tests/brand_manager_test.php
vendor/bin/phpunit local/sentientia_translate/tests/prompt_builder_test.php
vendor/bin/phpunit local/sentientia_translate/tests/response_parser_test.php
vendor/bin/phpunit local/sentientia_translate/tests/translate_engine_test.php
vendor/bin/phpunit local/sentientia_translate/tests/anthropic_client_test.php
```

Expected: 5 test classes, ~55 test methods, 100% pass without an API key.
The brand-name preservation tests (`brand_manager_test`) are the headline
coverage: WITH-override substitution + WITHOUT-override verbatim
preservation + whole-token + longest-first.

A CLI smoke test runs the whole pipeline against the mock client with no
DB and no network:

```powershell
php local/sentientia_translate/cli/mock_smoke.php
```

---

## Cost model

When `sentientia.translate.live_api` is ON, every translation charges
Anthropic per token. A ~4,000-word source is ~5,000 input tokens; the
translation is a similar size in output. At Sonnet 4.6 pricing
(~$0.003/1K input + ~$0.015/1K output) a full-page translation is roughly
$0.10. The per-customer daily cap of 3,000,000 tokens bounds the bill.

---

## Multi-tenant isolation

Every translation row carries `customerid` + `costcenterid` (from the
admin's `open_path`). `translate_engine::load_for_actor()` refuses to
return a row owned by a different tenant unless the actor holds
`:manage_all`. Brand overrides are customer-scoped, so one customer's
brand map never leaks into another's translations.

---

## Hindi (hi) language pack

100% parity with English. Every key in
`lang/en/local_sentientia_translate.php` has a corresponding entry in
`lang/hi/local_sentientia_translate.php`.

---

## Privacy / GDPR / DPDP

`classes/privacy/provider.php` declares the `translations_log` table and
the external Anthropic API as a subsystem we transmit source text to
(only when the live-API flag is ON). The brand-overrides table holds
customer-level configuration only — no personal data. Export + delete
flows are wired through Moodle's privacy API.
