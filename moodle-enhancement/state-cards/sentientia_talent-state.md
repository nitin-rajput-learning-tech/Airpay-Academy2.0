# State Card — local_sentientia_talent

**Plugin:** `local_sentientia_talent` (Talent Mobility / Succession / Career Pathing)
**Gap:** Competitive roadmap §6 P2.1 (GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md)
**Status:** Built — 2026-06-16 — `1.0.0-beta` — MATURITY_BETA
**Branch:** `claude/gap-talent`

---

## Mission

Internal talent mobility for Sentientia LMS:
1. **Career paths** — named role→role progressions (designation to designation).
2. **Succession planning** — nominate successors for key roles with a readiness rating (HR-sensitive).
3. **Internal opportunity board** — open internal roles surfaced to employees, skill-matched, with expression-of-interest.

Built ON TOP of the skills taxonomy and role definitions. Default-OFF feature
flag means zero footprint on Airpay's current production until flipped.

---

## Dependencies + graceful degradation

| Dependency | Type | Behaviour |
|------------|------|-----------|
| `local_sentientia_platform` | hard (tenant + feature_flags) | required |
| `local_sentientia_skills` | hard (manual taxonomy fallback) | always present; the fallback source |
| `local_sentientia_skillsai` | **soft, parallel session** | preferred when present + enabled |
| `local_sentientia_roles` | conceptual (role/designation model) | designations sourced from role-skill matrix + `user.open_designation` |

**Skills bridge** (`classes/skills_bridge.php`) is the single chokepoint for
every skills lookup. It prefers `\local_sentientia_skillsai\taxonomy`
(`class_exists` + `get_config('local_sentientia_skillsai','enabled')` guarded),
and falls back to `local_sentientia_skills` tables. If the skillsai contract
ever differs it fails SAFE — logs a developer-debug note and degrades to manual
rather than throwing. skillsai is built in a parallel session and is NOT
required for this plugin to function.

---

## Feature flags (default OFF)

| Key | Default | Effect when OFF |
|-----|---------|-----------------|
| `sentientia.talent.enabled` | **false** | All pages return "feature disabled", nav hidden, WS short-circuit |
| `sentientia.talent.opportunities` | **false** | Learner opportunity board hidden (HR succession/paths still usable) |

Resolved via `\local_sentientia_platform\feature_flags` (per-customer + per-tenant).

---

## Schema (5 tables, all tenant-scoped via costcenterid + indexed)

| Table | Purpose | PII |
|-------|---------|-----|
| `local_sentientia_talent_path` | Career-path definitions (from_designation → to_designation) | editor id only |
| `local_sentientia_talent_succ` | Succession nominations (candidate/incumbent + readiness + notes) | **HR-sensitive** |
| `local_sentientia_talent_opp` | Internal opportunity postings | poster id |
| `local_sentientia_talent_int` | Expressions of interest (one per opp+user) | applicant id |
| `local_sentientia_talent_audit` | Append-only audit of every HR mutation | id-only JSON (no names) |

Every table: `costcenterid`, `timecreated`, `timemodified` (audit uses
timecreated only — append-only), indexes on costcenterid + lookup columns,
unique `(costcenterid, designation, candidateid)` on succession and
`(opportunityid, userid)` on interest.

---

## Capabilities (HR vs learner split — succession never exposed to students)

| Capability | Archetypes | Risk |
|------------|-----------|------|
| `:viewopportunities` | user, student, teacher, manager | read |
| `:registerinterest` | user, student, manager | write |
| `:viewcareerpath` | user, student, manager | read |
| `:viewsuccession` | **manager only** | RISK_PERSONAL |
| `:managesuccession` | **manager only** | RISK_PERSONAL \| RISK_DATALOSS |
| `:managecareerpaths` | manager only | RISK_CONFIG |
| `:manageopportunities` | manager only | RISK_PERSONAL |
| `:audit` | manager only | RISK_PERSONAL |

Every read/write method in `talent_manager` does `require_capability()` AND a
`tenant::require_access()` / `tenant::sql_filter()` tenant check. Candidates
must belong to the planner's tenant — cross-tenant nomination is rejected.

---

## Files

```
version.php                              component, deps, 2026061600
db/install.xml                           5 tables
db/upgrade.php                           idempotent create-if-missing
db/access.php                            8 capabilities
db/feature_flags.php                     2 flags (default OFF)
settings.php                             read-only source indicator + Switchboard link
lib.php                                  flag-gated navigation hook
index.php                                HR/manager console
opportunities.php                        learner opportunity board (POST + sesskey)
classes/talent_manager.php               all CRUD + flag/tenant/cap enforcement
classes/skills_bridge.php                skillsai-preferred, manual-fallback skills lookups
classes/audit.php                        append-only audit writer + tenant-scoped reader
classes/privacy/provider.php             full GDPR provider (4 PII tables)
templates/console.mustache               HR console
templates/opportunities.mustache         learner board
lang/en/local_sentientia_talent.php      85 strings
lang/hi/local_sentientia_talent.php      85 strings (100% parity — verified)
tests/talent_manager_test.php            10 tests
```

---

## Tests (`tests/talent_manager_test.php`)

- `test_flag_default_off_blocks_suite` — flag-OFF no-op (require_enabled throws)
- `test_flag_on_enables_suite`
- `test_career_path_crud` — create/list/paths_from/delete
- `test_learner_cannot_read_succession` — capability gating
- `test_learner_cannot_write_succession` — capability gating
- `test_succession_crud_and_duplicate_guard`
- `test_succession_rejects_cross_tenant_candidate` — tenant isolation
- `test_succession_tenant_isolation_on_list` — Airpay vs Public manager isolation
- `test_opportunity_and_interest_flow` — opportunity CRUD + register/withdraw + applicant list
- `test_match_percentage_uses_manual_fallback` — skills-dependency fallback (manual taxonomy, 0%/50% coverage)

Uses `\local_sentientia_platform\phpunit\open_path_fixture_trait` for tenant fixtures.

---

## Verification done

- `php -l` clean on all 12 PHP files.
- `install.xml` validates as XML.
- No conflict markers.
- Lang en/hi key parity = 85/85.

## Not done (out of scope / future)

- No XAMPP deploy, no PR (per session rules).
- Career-path/succession/opportunity CREATE forms are server-side managed
  via `talent_manager` API; admin edit modals (AMD/dynamic_form) are a
  follow-up — current console is read + nav, write paths covered by the
  manager API + learner board POST flow.
- External web services (`db/services.php`) not added — pages call the
  manager directly. Add WS layer when the mobile/PWA surface needs it.
