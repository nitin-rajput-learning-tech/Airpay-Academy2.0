# State Card — local_sentientia_skillsai

**Plugin:** Skills Intelligence (AI) — Gap P0.1 (highest-leverage competitive gap)
**Source:** `moodle-enhancement/docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` §6 P0.1
**Version:** 2026111901 / 0.1.5-alpha (MATURITY_ALPHA; 2026-11-19, see the last section. Built as 2026061600 / 0.1.0-alpha)
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

## Update 2026-06-17 — teacher-archetype capability back-fill (T-01 class)
Persona feature-check found the airpay `trainer` role (teacher archetype) excluded
from Skills Intelligence — `extract`/`review` were `editingteacher`+`manager`
only. Added `teacher => CAP_ALLOW` to those two caps in `db/access.php` + an
idempotent `db/upgrade.php` step (2026061700) back-filling them onto existing
teacher-archetype roles. `manage_taxonomy`/`viewgaps`/`manage_all` stay
manager-only. Version 2026061600 → 2026061700. Verified: system-context trainer
resolves `has_capability(:extract)`=YES. Last mile = provisioning (SME authors
need a SYSTEM-context role; airpay assigns `trainer` at category) — per-deployment.

## 2026-08-05 — Sentientia AI gateway opt-in migration (v2026080500 / 0.1.1-alpha)

Consumer migration onto `local_sentientia_ai` (ADR-028 Phase 2.3, README
recipe; reference: aiquiz 2026080402). `anthropic_client::extract()` now
delegates to `\local_sentientia_ai\client::complete()` ONLY when the gateway
class exists AND the `sentientia.ai.gateway.enabled` routing switch is ON.
Default OFF → byte- and side-effect-identical to the pre-gateway build
(local mock, NO ledger writes) — the aiquiz lesson (unconditional delegation
broke 17 tests by writing DB rows in non-reset test contexts) applied from
the start. Our mock passes down as the `'mock'` callable (v2-hindi fidelity
preserved); `'legacy_component'` exposes this plugin's `api_key` as the
gateway's key fallback; gateway `denied` (quota) maps to this plugin's
`failed` semantics (persist a retriable job, never retry-loop). All
plugin-level gates unchanged (enabled/live_api flags, per-call [CONFIRM],
human-review gate). Purpose slug: `skill_extraction`. Standalone fallback
kept for gateway-less deployments. Mirrored to top-level local/ (plugin was
MISSING there — full dir seeded) + deployed to XAMPP webroot.

## 2026-09-07 — T-01 author-role fresh-install parity (v2026090700 / 0.1.2-alpha)

Companion to `local_sentientia_authoring`'s T-01 fix (UAT persona walk
2026-09-07). The Sentientia Author role's canonical cap set includes this
plugin's :extract / :review caps. On a FRESH install Moodle installs plugins
alphabetically, so `sentientia_authoring` installs BEFORE `sentientia_skillsai`;
authoring's install-time `author_role::ensure()` therefore runs before THIS
plugin's caps are registered and skips them (the ensure() guard avoids orphan
rows). NEW `db/install.php` re-runs
`\local_sentientia_authoring\author_role::ensure()` (guarded by class_exists)
so — because skillsai installs last of the cap owners — all author caps are
registered and land on the `sentientiaauthor` role. No-op on existing installs
(authoring's 2026090700 upgrade step already reconciles). No schema / flag /
capability-definition change. Mirrored to both trees. Version 2026080500 →
2026090700 (0.1.1 → 0.1.2-alpha). NOT deployed.


## 2026-09-22 - Privacy provider did not declare every table it owns

`privacy_coverage_test` (new, in `local_sentientia_platform`) walks every Sentientia plugin's
`install.xml` and fails the build when a plugin holding a user-identifying column does not declare
it. It found eleven such tables across six plugins on its first run. This plugin held two:

- `local_sentientia_skai_taxonomy` (`approved_by`) - who approved each skill
- `local_sentientia_skai_impact` (`createdby`) - who authored each impact metric

This is the harder version of the `null_provider` bug. A provider that declares *some* of its
tables makes the Privacy registry page read as complete, so nobody looks again. A subject-access
request returned a partial answer and an erasure request left rows behind, in both cases reporting
success.

**Owner versus actor.** A column identifying the data subject has its rows deleted. A column where
the subject merely acted on somebody else's record is anonymised to `0` instead, because deleting
the row would destroy a third party's record or shared configuration. Both are exported.

Both are shared skill configuration, so both are anonymised rather than deleted - consistent with the existing decision to keep extraction jobs as an audit trail.

Version bumped to 2026092202 so the cached privacy registry picks up the new declarations. en + hi
strings added at parity.

## 2026-09-25 - ADR-031: :manage_all is cross-tenant only (0.1.3-alpha, 2026092500)

Sweep hits `local/sentientia_skillsai:manage_all` (default grant) and `:viewgaps` (tenant-0 fail-open), both
CONFIRMED.

- **Default grant removed:** `:manage_all` now has `archetypes => []` (risk: personal + dataloss). Upgrade step 2026092500 revokes it from every role.
- **Caller must also be cross-tenant:** `taxonomy_manager::can_manage_all()` requires `:manage_all` AND `tenant::is_cross_tenant()`, so a stray grant cannot unscope anyone. It is used by gaps, index, review and taxonomy.
- **`gap_engine::tenant_summary(?int)`:** null means every tenant, and only the caller decides that. A tenant root of 0 or less returns nothing; 0 used to mean every tenant.
- **gaps.php:** it resolves the tenant with `tenant::root_for_user()`, and the per-user feed and rebuild use `tenant::require_same_tenant_user()`.
- **No-tenant callers:** `list_for_actor()` and `load_for_actor()` give an actor with no tenant only their own jobs, not the costcenterid-0 bucket. taxonomy.php shows and maps nothing for a caller with no tenant.
- **Still dormant:** all four skillsai flags stay default OFF.
- **Tests:** `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 wave-1 review follow-up (no version change; stays 2026092500)

- **Extract course picker.** `extract.php` listed up to 200 visible courses from every tenant, and a job could be tagged with another tenant's `courseid`. The picker now uses `taxonomy_manager::course_options()` (`tenant::path_filter('c', 'open_path', true)`: own tenant plus legacy NULL-path courses; every course cross-tenant; none without a tenant), and a posted `courseid` that fails `course_in_scope()` is refused with `error_outoftenant`.
- **One tenant-root parser.** `taxonomy_manager::tenant_root_for()` and `gap_engine::tenant_root_for()` used a plain `(int)` cast, so `/1x` read as tenant 1 while gaps.php and taxonomy.php (`tenant::root_for_user()`, `ctype_digit`) read it as none. Both now delegate to `tenant::root_for_user()`, so `create_pending`, `load_for_actor`, `list_for_actor`, `compute_for_user` and `rebuild_for_user` agree with the pages.
- **Tests:** `test_extract_course_picker_is_tenant_scoped` and `test_tenant_root_agrees_with_the_platform_helper` in `tests/tenant_scope_test.php`.
- **Deploy note:** a `:crosstenant` holder without `:manage_all` stays tenant-scoped here, by design (ADR-031 decision 3). See the skills state card for the platform L&D role grants Nitin must make before UAT.

## 2026-09-25 - ADR-031 fix-forward 2: tagging a job is a write (no version change; stays 2026092500)

From the adversarial review of claude/adr031-assessment-ff.

- **Legacy courses.** `course_options()` / `course_in_scope()` used `tenant::path_filter('c', 'open_path', true)`: a scoped caller could tag a job with a NULL-path legacy course but not a ''-path one. Tagging is a write, so both now use `course_tag_scope_sql()` (`tenant::scope_path()` + `path_descendant_filter()`): own tenant only for a scoped caller, every course cross-tenant, nothing without a tenant - the same rule as sentientia_skills' course mapping. The picker offers exactly what the submit accepts.
- **`create_pending()`** refuses a `courseid` that fails `course_in_scope()` (`error_outoftenant`), so the gate no longer lives only in extract.php. `courseid` 0 (untagged) needs no scope.
- **Tests:** `test_extract_course_picker_is_tenant_scoped` now asserts legacy NULL and '' courses are neither offered nor taggable by a scoped caller (it asserted NULL stayed pickable); new `test_tagging_a_job_with_a_course_is_a_scoped_write`.
- **Not run here:** PHPUnit.

## 2026-11-16 - Fresh install now gives the Sentientia Author role :extract and :review (0.1.4-alpha, 2026111600)

**Defect (CI review).** The 2026-09-07 section above is wrong. Moodle runs a plugin's
`db/install.php` BEFORE it registers the plugin's capabilities (`lib/upgradelib.php`
`upgrade_plugins()`: install.php, then `upgrade_component_updated()` -> `update_capabilities()`).
`author_role::ensure()` grants only registered capabilities, so the `ensure()` call in this
plugin's install hook skipped `:extract` / `:review` exactly as authoring's own install did. Every
fresh install since 2026090700 (CI, PHPUnit sites, a new customer, the Stage B cutover, which
installs the Sentientia plugins fresh into the imported live DB) created the role without them.
That call did fill in authoring's three caps, which is why the gap only showed for these two.
(Exception: a site that installed skillsai before authoring, in an earlier run, got both caps from
authoring's install hook.) UAT was installed 2026-09-03, before these hooks existed; its role most
likely came from authoring's 2026090700 upgrade step with every cap registered, so the back-fill is
probably a no-op there. Not checked on UAT.

**Fix.**
- `db/install.php`: `update_capabilities('local_sentientia_skillsai')` (underscore form) before
  `author_role::ensure()`, still inside the `class_exists` guard. Moodle's own later
  `update_capabilities()` call finds nothing new, so archetype defaults are applied once.
- New `db/upgradelib.php` `local_sentientia_skillsai_backfill_author_caps()`, called by new upgrade
  step 2026111600. Back-fill condition (tightened the same night, see the follow-up below): a role
  with shortname `sentientiaauthor` exists AND both caps are registered in `{capabilities}` AND
  `{role_capabilities}` has no system-context row for that role and EITHER cap. Then both
  `local/sentientia_skillsai:extract` and `:review` get CAP_ALLOW with
  `assign_capability(overwrite=false)`; otherwise nothing. Only that role. It does not re-run
  `ensure()` (that resets every author cap to ALLOW).
- Comments corrected: install.php docblock, version.php (2026090700 note), upgrade.php header.
- Version 2026092500 -> 2026111600 (0.1.3 -> 0.1.4-alpha). No schema / flag / capability change.

**Tests (written, not run: another job held the shared PHPUnit DB).** New
`tests/install_caps_test.php` (attributes only: `CoversFunction` x2; no docblock PHPUnit tags):
fresh-install order (caps deleted from `{capabilities}` and `{role_capabilities}`, then the hook)
grants both at CAP_ALLOW; back-fill grants only these two caps and only to the author role, and is
idempotent; keeps a PROHIBIT and a PREVENT; no-op without the role. A standalone stub harness
(no Moodle, no DB) replayed four install orders against the HEAD and new hooks: HEAD left these two
caps off in the same-run and authoring-first orders; the new hooks left nothing off in any order.

**Follow-up (review, same night; still 2026111600, no second bump).** The back-fill now matches the
gap's signature: the gap always leaves BOTH caps without a system-context row at once. So
`local_sentientia_skillsai_backfill_author_caps()` grants only when the role exists AND neither cap
has a system-context `{role_capabilities}` row for it AND both are registered; returns 2 or 0. If
one has a row (any permission), nothing is granted: that is an administrator's configuration, never
the gap. Tests: the PROHIBIT/PREVENT case now expects nothing granted (the unset cap stays unset);
new "one cap cleared to 'Not set' while the other has a row -> nothing granted" and "a cap not
registered -> nothing written" cases. Same change in authoring, which also gained the read-only
real-fresh-install test (see its card). Written, not run (shared PHPUnit DB busy).

**Known limit (narrowed).** Before the follow-up, any cap cleared to "Not set" was re-granted. Now
only an administrator who cleared BOTH caps looks the same as the gap, and gets them back as ALLOW.

Both trees, byte-identical. NOT deployed; PHPUnit needs a re-init for the version bump.

---

## 2026-11-19 — nightly gap-feed task covers every user (2026111901 / 0.1.5-alpha)

**2026-11-20 ~05:15, session 0+1 wrap-up:** deployed to local XAMPP (upgrade 03:06-03:12). Review PASS (plus a stray CR byte in this card, fixed). The task itself was not run (flag `sentientia.skillsai.gap_engine` stays OFF; no `--execute` in the read-only lanes). PHPUnit NOT finished: no test written in session 0+1 has run (the local test-DB re-init was still running at 04:30 and is cut off by the 06:00 shutdown). Not committed, not on UAT. Evidence: `docs/visual-evidence/2026-11-19/session-01/README.md` (G-ANALYTICS).

Post-demo plan 2.4 (Analytics). `task\rebuild_gap_feed` read the first `BATCH_SIZE` (500) matching users by id
and stopped, every night. Its docblock said the next run would pick up where it left off, but nothing stored a
cursor, so from the 501st user with a mapped designation on nobody's gap feed was rebuilt.

- New `rebuild_gap_feed::rebuild_all(array $designations, int $batchsize = BATCH_SIZE)`: keyset batches on user
  id (`id > :lastid ORDER BY id`, 500 per query) until a query returns fewer than a batch; returns
  `{processed, batches, failed}`. One user's failure is logged (user id + message) and skipped; each user's
  rebuild is its own transaction, so a failed user keeps the previous feed.
- `execute()` unchanged otherwise: no-op while `sentientia.skillsai.gap_engine` is OFF (the default), when no
  designation has requirements, or when `user.open_designation` is absent.
- Not changed: users who leave a mapped designation (or are suspended) keep their old gap rows; the task only
  rebuilds current matches (as before).
- Files (both trees, byte-identical): `classes/task/rebuild_gap_feed.php`, `version.php`,
  `tests/rebuild_gap_feed_task_test.php` (new: 5 users in batches of 2, exact multiple of the batch size, empty
  designations, execute with flag ON and OFF). PHPUnit not run in this stage.
