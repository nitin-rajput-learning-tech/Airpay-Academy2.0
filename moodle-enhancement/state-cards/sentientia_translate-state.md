# State Card — `local_sentientia_translate` (Sentientia LMS AI Content Translation)

**Current phase:** T.0 — MVP scaffold + C16 admin queue/landing
**Version:** 0.2.0-alpha (2026052801)
**Status:** MVP feature-complete + unified admin landing/queue dashboard.
Feature flag default OFF. Mock-mode demoable.
**Owner:** Nitin Rajput (PM) + Claude (engineering)
**Last updated:** 2026-05-28 (C16 stabilization audit follow-up)

## What changed since last revision (2026-05-25 → 2026-05-28)

- **C16 admin landing/queue dashboard** shipped — new
  `admin/index.php` (~290 LOC) + `admin_externalpage` registration.
  4 stats cards (Total / Pending / Saved / Failed) + status + lang
  filter chips + 25-row recent-translations table. Scoping mirrors
  `translate_engine::list_for_actor()` (full-customer view if
  `manage_all` cap, own-rows-plus-tenant otherwise).
- 30 new EN lang strings (`admin_index_*`, `stats_*`, `filter_*`,
  `col_*`, `action_*`).
- Version bump 0.1.0-alpha → 0.2.0-alpha, savepoint 2026052801.
- State-card naming fixed: renamed from `local_sentientia_translate-state.md`
  → `sentientia_translate-state.md` (Bucket F4 fix) to match
  dir-name convention required by the new state-card freshness gate.
- **C17 seed CLI** shipped: `cli/seed_demo_translations.php`.
  Creates 6 sample translation rows across all 5 statuses (pending,
  translated, saved, failed, discarded) in 4 target languages
  (hi, mr, kn, sw) + 2 brand overrides. Uses `translate_engine`
  static methods so data shape matches live translations. `--purge`
  removes only `[DEMO]`-titled rows. Idempotent; re-runs guard against
  double-seeding.

---

## Mission

Tier 1 Hindi content-pipeline cousin. Admins paste English course content
and Anthropic Claude returns a translation into Hindi, Marathi, Kannada,
or Swahili in the native script, with brand-name preservation (verbatim,
or rendered in the target script per a per-customer override). The admin
reviews a side-by-side diff before saving.

## Architecture decision

See [ADR-016](../docs/adr/ADR-016-ai-content-translation.md). Highlights:

- **Model:** `claude-sonnet-4-6` default; configurable per translation.
- **Prompts:** Versioned in code, script-aware (`prompt_builder::VERSION = 'v1'`).
- **Brand preservation:** prompt instruction (keep verbatim) + deterministic
  whole-token, longest-first post-processing (script rendering). The
  post-processing pass makes brand rendering unit-testable without the API.
- **Cost gating:** 4 layers (master flag + live-API flag + per-call
  [CONFIRM] + per-customer daily token cap).
- **Mock mode:** OFF (default) → deterministic mock echoes source so the
  brand-substitution chain runs end-to-end at zero spend.
- **Human review:** diff-before-save lifecycle; nothing auto-saves.
- **Multi-customer isolation:** brand overrides are customer-scoped by a
  unique key — one customer's brand map never leaks into another's.

## Database schema (2 tables — locked in Phase T.0)

| Table | Rows per | Purpose |
|-------|----------|---------|
| `local_sentientia_tr_log` | one per translation request | ownerid, sourcetext, translatedtext, targetlang, model, prompt_version, brand_terms_applied, tokens, mode, status (pending → translated → saved/discarded/failed), customerid, costcenterid |
| `local_sentientia_tr_brand` | one per (customer, brand, lang) | brand_source, targetlang, brand_target — the per-customer script-override map (unique key prevents dupes) |

## Capability matrix

| Capability | Roles allowed | Notes |
|------------|---------------|-------|
| `local/sentientia_translate:translate` | manager | Paste source + call Claude + review diff |
| `local/sentientia_translate:manage_brands` | manager | Add/edit/remove brand overrides |
| `local/sentientia_translate:manage_all` | manager | History across all owners |

## Feature flags

| Flag | Default | Purpose |
|------|---------|---------|
| `sentientia.translate.enabled` | **OFF** | Master switch. Pages 403 when OFF. |
| `sentientia.translate.live_api` | OFF | OFF → deterministic mock. ON → real Anthropic POST. |

## Supported target languages

| Code | Language | Script |
|------|----------|--------|
| `hi` | Hindi | Devanagari |
| `mr` | Marathi | Devanagari |
| `kn` | Kannada | Kannada |
| `sw` | Swahili | Latin |

## Brand-name preservation

- **Always-protected** (`brand_manager::DEFAULT_PROTECTED`): Airpay,
  Sentientia, UPI, RBI, KYC, PAN, Aadhaar, FIU-IND, NEFT, RTGS, IMPS,
  SCORM — kept verbatim with zero config.
- **Per-customer script overrides**: e.g. customer 1 maps `Airpay` → the
  Kannada-script form for `kn`. Applied deterministically post-translation;
  guaranteed regardless of model output.
- Whole-token (so "Airpayment" is safe) + longest-first (so multi-word
  brands win).

## Admin settings (Site admin → Plugins → Local plugins → AI Content Translation)

| Key | Type | Default | Notes |
|-----|------|---------|-------|
| `api_key` | passwordunmask | (empty) | Required only when `live_api` ON. |
| `default_model` | text | `claude-sonnet-4-6` | |
| `max_output_tokens` | int | 8192 | Translations can be long. |
| `max_source_words` | int | 4000 | Reject longer sources. |
| `daily_cost_cap_tokens` | int | 3000000 | Per-customer/day soft cap. |
| `prompt_template_note` | textarea | (empty) | Informational only. |
| Brand-name overrides | external page | — | `/local/sentientia_translate/brands.php` |

## Files shipped in T.0

```
local/sentientia_translate/
├── version.php  README.md  lib.php  settings.php  translate.php  brands.php
├── cli/mock_smoke.php
├── db/{install.xml, access.php, feature_flags.php}
├── classes/{anthropic_client, prompt_builder, response_parser,
│            translate_engine, brand_manager}.php + privacy/provider.php
├── lang/{en,hi}/local_sentientia_translate.php   (99 keys, 100% parity)
└── tests/{brand_manager, prompt_builder, response_parser,
           translate_engine, anthropic_client}_test.php
```

## Verification status

| Gate | Status |
|------|--------|
| Plugin installs cleanly via `php admin/cli/upgrade.php` | ⏳ Pending run on local XAMPP (XML validated, lint clean) |
| Mock-mode end-to-end (translate → diff → save) | ✅ Code-complete; CLI smoke PASS |
| Brand preservation WITH + WITHOUT override | ✅ CLI smoke PASS; dedicated PHPUnit suite |
| PHPUnit (5 classes) pass | ⏳ Need to run on local XAMPP |
| Hindi parity (`array_diff_key` empty) | ✅ 99 keys both files |
| ADR-016 committed | ✅ This commit |
| State card | ✅ This file |
| Feature flag default OFF | ✅ Both flags |
| [CONFIRM] gate enforced | ✅ translate.php rejects when checkbox unticked |
| Privacy provider valid | ✅ tr_log + Anthropic external link declared |

## Open questions for Nitin

- **Anthropic budget approval** before flipping `live_api` ON in prod.
- **Brand seed list** — should we pre-seed Kannada/Hindi/Marathi script
  forms for "Airpay" via an install hook, or leave entirely to admins?
- **Write-back target** — T.1 needs to decide whether translations write
  into Moodle's `{mlang}` multilang filter format or a separate store.
- **Marathi vs Hindi Devanagari** — both use Devanagari; confirm the
  prompt's language naming is enough to disambiguate register/vocabulary.

## Phase ladder

| Phase | Scope | Status |
|-------|-------|--------|
| **T.0** | MVP scaffold + brand manager + diff UI + mock pipeline + PHPUnit + Hindi + ADR-016 | ✅ **CURRENT** |
| T.1 | Bulk course-content translation (walk a course, write back) | pending |
| T.2 | ElevenLabs voice re-pack → SCORM | pending |
| T.3 | Cost analytics dashboard + per-customer quota | pending |
| T.4 | Translation memory (reuse prior translations) | pending |

## Commits

| Commit | Subject |
|--------|---------|
| (this commit) | Tier 1 AI — `local_sentientia_translate` Phase T.0 MVP (brand-preserving translation) |
