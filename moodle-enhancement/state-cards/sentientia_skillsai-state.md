# State Card — local_sentientia_skillsai

**Plugin:** Skills Intelligence (AI) — Gap P0.1 (highest-leverage competitive gap)
**Source:** `moodle-enhancement/docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` §6 P0.1
**Version:** 2026061600 / 0.1.0-alpha (MATURITY_ALPHA)
**Branch:** `claude/gap-skillsai`
**Status:** MVP complete. All feature flags DEFAULT OFF. No live API calls made during build (zero spend).

---

## What it does (four capabilities)

1. **EXTRACT** — Anthropic Claude reads a course/SCORM transcript, SOP excerpt or
   narration text and proposes candidate skills (name, description, category,
   teach-to level, confidence, grounding evidence).
2. **TAXONOMY** — a per-tenant CANONICAL skills taxonomy assembled from approved
   candidates, behind a **mandatory human review/approve-edit-reject gate**. No AI
   output becomes canonical without a reviewer verdict + explicit promotion.
3. **GAP ENGINE** — compares role-required skills (`local_sentientia_role_skills`,
   joined on the user's designation) against held skills
   (`local_sentientia_user_skills`) and emits a per-user gap feed.
4. **IMPACT** — a skill → business-impact mapping surface (metric + 1–5 priority
   weight) so the gap feed can be ranked by business priority.

---

## Files

```
version.php                              component, deps (platform 2026051401, skills 2026041000)
db/install.xml                           5 tables (below)
db/upgrade.php                           init stub (returns true)
db/access.php                            5 capabilities
db/feature_flags.php                     4 flags, all DEFAULT OFF
db/tasks.php                             nightly rebuild_gap_feed task
settings.php                             api_key + model + limits + per-customer prompt template
lib.php                                  navigation hook (flag- + cap-gated)
classes/anthropic_client.php             mock-mode default + live curl; extract()/call_mock()/call_live()
classes/prompt_builder.php               v1 (EN) + v2-hindi prompts, PII validation, custom-template override
classes/response_parser.php              strict JSON parse, level/confidence clamp, dedupe, mb_* safe
classes/taxonomy_manager.php             jobs + candidate review GATE + promotion + tenant scoping
classes/gap_engine.php                   gap maths + feed persistence + tenant summary
classes/impact_manager.php               skill→business-impact CRUD
classes/task/rebuild_gap_feed.php        scheduled task (self-gates on gap_engine flag; ZERO AI calls)
classes/privacy/provider.php             GDPR: job ownership + per-user gap feed + Anthropic external link
index.php                                extraction job queue (landing)
extract.php                              extract form: 4-gate [CONFIRM] flow → mock/live → review redirect
review.php                               human-review gate UI (approve/edit/reject + promote + finalise)
taxonomy.php                             taxonomy list + impact mapping surface
gaps.php                                 tenant gap summary + per-user feed + rebuild
cli/mock_smoke.php                       end-to-end mock pipeline smoke (runs in sandbox; PASS)
lang/en/local_sentientia_skillsai.php    149 strings
lang/hi/local_sentientia_skillsai.php    149 strings — 100% parity (verified)
tests/response_parser_test.php           mock AI + parser + PII security
tests/taxonomy_manager_test.php          job CRUD + review gate + promotion + tenant isolation
tests/gap_engine_test.php                gap logic + feed persistence + tenant scoping
tests/impact_manager_test.php            impact CRUD + weight clamp + tenant scoping
```

---

## Feature flags (db/feature_flags.php — all DEFAULT OFF)

| Flag | Gates |
|------|-------|
| `sentientia.skillsai.enabled` | master switch — pages 403, nav hidden when OFF |
| `sentientia.skillsai.live_api` | mock (default) vs real api.anthropic.com POST |
| `sentientia.skillsai.gap_engine` | gap UI + nightly rebuild task |
| `sentientia.skillsai.impact_map` | impact mapping surface + gap-feed business weighting |

Registered via the standard pattern; discovered automatically by
`\local_sentientia_platform\feature_flags`.

---

## DB tables (all tenant-scoped: costcenterid + customerid + timecreated + timemodified)

| Table | Purpose |
|-------|---------|
| `local_sentientia_skai_job` | one row per extraction request (pending→extracted→reviewed/failed) |
| `local_sentientia_skai_cand` | AI-proposed candidate skills — the review GATE (proposed→approved/edited/rejected) |
| `local_sentientia_skai_taxonomy` | per-tenant canonical taxonomy; unique (costcenterid, name) |
| `local_sentientia_skai_impact` | skill→business-metric mappings (weight 1–5) |
| `local_sentientia_skai_gap` | per-user gap feed; unique (userid, skillid) |

Indexes on all FKs + tenant columns + the WHERE/ORDER columns used by queries.

---

## Capabilities (db/access.php, CONTEXT_SYSTEM)

`:extract` (editingteacher+, manager) · `:review` (editingteacher+, manager) ·
`:manage_taxonomy` (manager) · `:viewgaps` (manager) · `:manage_all` (manager).

---

## Cost / safety defences (4-layer, mirrors sentientia_aiquiz)

1. master flag OFF by default → no UI at all.
2. live_api OFF by default → `call_mock()` runs, zero spend, no key needed.
3. per-call **[CONFIRM]** checkbox in extract.php before any dispatch.
4. per-user daily token cap (admin setting, default 500k) blocks runaway use.
Plus: PII heuristic (Aadhaar/PAN) rejects source at paste time; API key only in
`get_config` (admin setting, masked), never in code; key never logged or put in
`error_detail`.

**Human-review gate:** `taxonomy_manager::promote_candidate()` throws unless the
candidate status is `approved`/`edited` — AI output cannot reach the canonical
taxonomy without a reviewer.

---

## Tests

Run on XAMPP: `php admin/tool/phpunit/cli/init.php` then
`vendor/bin/phpunit local/sentientia_skillsai/tests/` (cwd = `moodle5/public`).
Coverage: mock-mode AI, security (PII + malformed JSON + clamping), taxonomy CRUD,
review-gate enforcement, candidate promotion (incl. idempotency), gap-engine maths,
tenant isolation across all managers. Tests use the platform's
`open_path_fixture_trait` to provide `mdl_user.open_path` in the sandbox.

`cli/mock_smoke.php` verified PASS in the build sandbox (no Moodle bootstrap, no
network).

---

## Open integration points (next phases)

- **sentientia_recommendations** can consume `local_sentientia_skai_gap` to rank
  remedial courses against a learner's open gaps (rationale model already exists).
- **sentientia_skills**: `taxonomy.linked_skillid` is the seam to materialise a
  canonical node into the production `local_sentientia_skills` matrix (P0.1.x).
- **Auto-extract cron** over newly-published SCORM packages (P0.1.2).
- **Live API**: flip `live_api` flag + set `api_key` (still per-call [CONFIRM]).
- `user.open_designation` drives the gap engine in production; the nightly task
  no-ops gracefully if the column is absent.

---

## Hard-rule compliance

Complete atomic plugin · flags default OFF · multi-tenant scoping via
`\local_sentientia_platform\tenant` (open_path) · `$DB` API + `{tablename}` +
`get_in_or_equal` · `defined('MOODLE_INTERNAL') || die()` · `required_param`/
`optional_param` + `require_sesskey` + `s()`/`format_string()` · EN/HI 100% parity ·
PHPUnit security/tenant/CRUD/gap/mock coverage · NO core edits · NOT deployed · NO PR.
