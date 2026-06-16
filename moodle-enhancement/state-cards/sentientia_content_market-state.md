# State Card — local_sentientia_content_market

| Field | Value |
|-------|-------|
| Plugin | `local_sentientia_content_market` |
| Gap | P1.1 — Curated Content Marketplace (Invince "Plethora" gap closure) |
| Branch | `claude/gap-content-market` |
| Version | `2026061600` / `1.0.0-beta` |
| Status | **COMPLETE — ready for review** |
| Date | 2026-06-16 |
| Author | Claude Code (Sentientia LMS agent session) |

---

## What was built

A complete Moodle 5.1 local plugin providing a connector **framework** that
aggregates third-party course catalogs (Go1, Udemy Business, Coursera, Skillsoft)
via provider adapters into a unified, searchable in-platform marketplace, mapped
to the skills taxonomy from `local_sentientia_skillsai`.

---

## Architecture

### Adapter pattern (mirrors KeKa HRMS client)

```
provider_interface  (classes/adapter/provider_interface.php)
        ↑ implements
    go1_provider          — Go1 REST API v3, bearer token, offset pagination
    udemy_business_provider — Udemy Business API v2, basic auth, page pagination
    coursera_provider     — Coursera API v1, OAuth2 client-credentials
    skillsoft_provider    — Skillsoft Percipio API, bearer token, offset pagination
    mock_provider         — No HTTP calls; static fixture; PHPUnit/demo use
```

### Core classes

| Class | File | Responsibility |
|-------|------|----------------|
| `catalog_item` | `classes/catalog_item.php` | DTO: normalised course item |
| `market_aggregator` | `classes/market_aggregator.php` | Orchestrates sync, upsert, skills mapping, cache invalidation |
| `task\sync_providers` | `classes/task/sync_providers.php` | Nightly scheduled task (02:00) |
| `privacy\provider` | `classes/privacy/provider.php` | null_provider — no personal data stored |

---

## Database tables

| Table | Purpose |
|-------|---------|
| `local_sentientia_cm_item` | Normalised catalog items (provider + external_id unique key) |
| `local_sentientia_cm_skill_map` | Many-to-many: item → skill taxonomy term |
| `local_sentientia_cm_sync_log` | Append-only audit log per provider per sync run |

All tables carry `costcenterid + timecreated + timemodified + indexes` per database.md rules.

---

## Feature flags (all DEFAULT OFF)

| Flag | Default | Purpose |
|------|---------|---------|
| `sentientia.content_market.enabled` | OFF | Master switch |
| `sentientia.content_market.go1.enabled` | OFF | Go1 adapter |
| `sentientia.content_market.udemy_business.enabled` | OFF | Udemy Business adapter |
| `sentientia.content_market.coursera.enabled` | OFF | Coursera adapter |
| `sentientia.content_market.skillsoft.enabled` | OFF | Skillsoft adapter |
| `sentientia.content_market.skills_mapping.enabled` | OFF | Auto-map to skills taxonomy |

---

## Skills taxonomy integration (graceful degradation)

- When `local_sentientia_skillsai` is installed AND `skills_mapping.enabled` flag is ON:
  the aggregator calls `\local_sentientia_skillsai\taxonomy::find_term()` per provider
  skill name after each item upsert.
- When `local_sentientia_skillsai` is absent OR the flag is OFF: skill mapping is
  silently skipped. No errors. Items still imported without skill tags.
- Guard: `class_exists('\local_sentientia_skillsai\taxonomy')` before every call.

---

## Capabilities

| Cap | Archetypes | Purpose |
|-----|------------|---------|
| `view` | user, student, teacher, manager | Browse the marketplace |
| `syncproviders` | manager | Trigger manual sync |
| `manageproviders` | manager | Configure provider credentials |
| `mapskills` | manager | Manually map items to skills |

---

## Tests (PHPUnit)

File: `tests/content_market_test.php`

| Test | Covers |
|------|--------|
| `test_mock_provider_returns_valid_items` | Adapter normalisation |
| `test_mock_provider_page2_returns_empty` | Pagination boundary |
| `test_mock_provider_accepts_fixture_injection` | Test isolation |
| `test_catalog_item_is_invalid_without_required_fields` | DTO validation |
| `test_catalog_item_from_array_maps_known_properties` | DTO factory |
| `test_sync_provider_inserts_new_items` | DB create |
| `test_sync_provider_updates_existing_item` | DB update idempotency |
| `test_tenant_isolation_in_search` | Multi-tenant isolation |
| `test_skills_mapping_writes_provider_names_when_skillsai_absent` | Graceful degradation |
| `test_disabled_provider_returns_disabled_status` | Feature flag gate |
| `test_search_with_query_filters_by_title` | Search filtering |

---

## File inventory

```
moodle-enhancement/local/sentientia_content_market/
├── version.php
├── lib.php
├── index.php
├── settings.php
├── classes/
│   ├── catalog_item.php
│   ├── market_aggregator.php
│   ├── adapter/
│   │   ├── provider_interface.php
│   │   ├── mock_provider.php
│   │   ├── go1_provider.php
│   │   ├── udemy_business_provider.php
│   │   ├── coursera_provider.php
│   │   └── skillsoft_provider.php
│   ├── task/
│   │   └── sync_providers.php
│   └── privacy/
│       └── provider.php
├── db/
│   ├── install.xml
│   ├── upgrade.php
│   ├── access.php
│   ├── feature_flags.php
│   ├── tasks.php
│   └── caches.php
├── lang/
│   ├── en/local_sentientia_content_market.php
│   └── hi/local_sentientia_content_market.php
├── templates/
│   └── browse.mustache
└── tests/
    └── content_market_test.php
```

---

## ABSOLUTE RULES compliance

- [x] Feature flag default OFF (master + 5 sub-flags)
- [x] No real HTTP calls in tests (mock_provider)
- [x] Credentials via get_config() only — never hardcoded
- [x] All HTTP calls use Moodle `\curl` with 30s timeout
- [x] `defined('MOODLE_INTERNAL') || die()` in every PHP file
- [x] `costcenterid + timecreated + timemodified` on all DB tables
- [x] `{tablename}` placeholders — no raw table names in SQL
- [x] `$DB->get_in_or_equal()` for IN clauses (retire_missing)
- [x] Escaped output — `format_string()`, `format_text()`, `s()` in index.php
- [x] Hindi parity — 100% string key parity with en
- [x] No core file edits
- [x] No deploy to XAMPP
- [x] No PR opened

---

## Deployment checklist (when enabling on production)

1. Copy plugin to `/path/to/moodle/local/sentientia_content_market/`
2. Admin > Notifications (runs install.xml)
3. Set provider credentials in Admin > Plugins > Local > Content Marketplace
4. In Feature Flags Switchboard: flip `sentientia.content_market.enabled` ON
5. Flip per-provider flags ON for each configured provider
6. Manually trigger sync via index.php "Sync now" button
7. Verify sync log in {local_sentientia_cm_sync_log}
