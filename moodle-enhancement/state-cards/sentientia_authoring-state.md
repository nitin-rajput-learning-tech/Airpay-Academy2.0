# State Card — local_sentientia_authoring (GenAI Authoring Studio)

**Plugin:** `local_sentientia_authoring`
**Gap:** P0.3 — GenAI Authoring Studio (Invince "Craft" competitor)
**Source:** `moodle-enhancement/docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` §6 P0.3
**Branch:** `claude/gap-authoring`
**Status:** v0.1.0-alpha — MVP scaffold COMPLETE. All flags default OFF. Mock-mode only. Not deployed.
**Maturity:** MATURITY_ALPHA — requires prod sign-off before any flag flips.
**Updated:** 2026-06-16

---

## Mission

Unify Sentientia's disconnected AI seams (`aiquiz`, `translate`, the Workstream-B
TTS pipeline) into ONE authoring studio that matches Invince Craft:

1. prompt / Doc / PDF text → full microlearning course draft (cards + assessment)
2. editable instructional-design **templates** (CRUD, tenant-scoped)
3. **TTS voiceover** — productized ElevenLabs pipeline, [CONFIRM]-gated + mock by default
4. expanded question types beyond MCQ — **multi-response (MRQ)** + **match-the-following**
5. **AI contextual feedback** per question (correct + incorrect)
6. interactive **cards + mastery scores**

Localized output routes through `local_sentientia_translate` (class_exists-guarded; degrades to English).

---

## Hard-rule compliance (CLAUDE.md)

| Rule | How it's met |
|------|--------------|
| Complete atomic plugin | version.php, lang/en + lang/hi (181/181 parity), index.php, db/install.xml, db/upgrade.php, db/install.php, db/access.php, db/feature_flags.php, settings.php, lib.php, classes/*, *.php UI, tests/* |
| Feature flags default OFF | `sentientia.authoring.enabled`, `.tts`, `.live_api` — all `'default' => false` |
| AI/TTS mock by default, no live spend | `course_generator` + `tts_client` dispatch to `call_mock()` unless `live_api` ON. Build never flips it. No credentials in code (passwordunmask settings, read only on live + [CONFIRM]) |
| Mandatory human-review gate | `review.php`; `mark_published()` throws unless draft status = approved; nothing auto-publishes |
| Multi-tenant | every table has `customerid` + `costcenterid` + `timecreated` + `timemodified` + indexes; tenant root from `open_path`; `$DB` API + `{tablename}` + `get_in_or_equal` (privacy provider) |
| `defined('MOODLE_INTERNAL') || die();` | every PHP file |
| Escaped output | `s()` / `format_string()` / `format_text()` throughout UI; sesskey on every form |
| PHPUnit | security, tenant isolation, generation pipeline (mock), MRQ/match validation, template CRUD, localizer degradation, tts mock |
| No core edits / no deploy / no PR | confirmed |

---

## Architecture

### Tables (db/install.xml)
- `local_sentientia_auth_template` — instructional-design templates (built-ins seeded on install, not deletable)
- `local_sentientia_auth_draft` — one course-generation request (lifecycle: pending → generated → approved → published / rejected / failed)
- `local_sentientia_auth_card` — interactive cards (concept | example | scenario | flip) + narration
- `local_sentientia_auth_question` — MCQ / MRQ / match items with AI contextual feedback + points
- `local_sentientia_auth_voiceover` — TTS jobs (mock by default)

### Classes (classes/)
- `question_type` — **MRQ/match/MCQ validation + normalisation** (heart of gap #4); `decode_answer()` for the grader
- `prompt_builder` — versioned (v1 / v2-hindi) course-generation prompts; template-body injection; source validation (PII, word cap, unicode)
- `response_parser` — parses Claude's `{cards, questions}` JSON; delegates questions to `question_type`
- `course_generator` — Anthropic dispatcher; `call_mock()` (full module, all 3 q-types, Devanagari) + `call_live()` (curl, never logs key)
- `tts_client` — ElevenLabs dispatcher; `call_mock()` placeholder + `call_live()` ([CONFIRM]-gated plumbing, never reached in this build); `estimate_cost()`
- `localizer` — routes non-native languages through `translate`; degrades gracefully when absent
- `template_manager` — template CRUD + `seed_builtins()`; tenant-scoped reads
- `draft_manager` — persistence + review lifecycle + voiceover recording; tenant isolation in `load_for_actor` / `list_for_actor`
- `privacy/provider` — full GDPR/DPDP provider (5 tables + 2 external-API subsystems)

### UI pages
- `index.php` — draft list / landing
- `studio.php` — generate (4 gates: flag, capability, daily token cap, [CONFIRM])
- `review.php` — human-review gate (per-card + per-question approve/edit/reject, finalise)
- `templates.php` — template CRUD
- `voiceover.php` — TTS surface (flag-gated by `enabled` + `tts`, [CONFIRM])

### CLI
- `cli/mock_smoke.php` — end-to-end mock pipeline smoke test (`--lang`, `--cards`, `--questions`)

---

## Feature flags

| Flag | Default | Gates |
|------|---------|-------|
| `sentientia.authoring.enabled` | OFF | master switch; pages 403 + nav hidden when off |
| `sentientia.authoring.tts` | OFF | voiceover surface + jobs |
| `sentientia.authoring.live_api` | OFF | ANY live external call (Anthropic + ElevenLabs); off ⇒ mock |

---

## Capabilities (db/access.php)
`:generate`, `:review`, `:managetemplates` (editingteacher + manager), `:manage_all` (manager only).

---

## Verification done this session
- `php -l` clean on all PHP files
- `db/install.xml` XML well-formed
- Hindi parity: 181/181 keys, identical key sets (en ↔ hi)
- All `get_string()` keys used in code are defined in the lang pack

## NOT done (out of scope / deferred)
- Live API calls (forbidden this build) — `call_live()` paths exist but are never reached
- SCORM packaging of an approved draft (publish step is gated; SOP→SCORM packager wiring deferred to a publish session per CLAUDE.md §8)
- Live ElevenLabs audio persistence to Moodle file store (deferred to first human-confirmed prod session)
- Visual evidence screenshots (UI not deployed to XAMPP per task rules)

## Next steps (future sessions)
1. Publish pipeline: approved draft → real course (cards → pages/labels, questions → `mod_quiz`) + optional SOP→SCORM ZIP (§8 validation gates).
2. Per-customer prompt-template overrides via `customer::get_customer_config` (mirror aiquiz G.1).
3. Expand `localizer` language targets toward Invince's 150+ (incremental).
4. Staging enablement run with `live_api` ON under [CONFIRM] + visual evidence.

## Update 2026-06-17 — teacher-archetype capability back-fill (T-01 class)
Persona feature-check found the airpay `trainer` role (teacher archetype = the
SME/author role) excluded from the Authoring Studio — `generate`/`review`/
`managetemplates` were `editingteacher`+`manager` only. Added `teacher => CAP_ALLOW`
to those three caps in `db/access.php` + an idempotent `db/upgrade.php` step
(2026061700) back-filling them onto existing teacher-archetype roles via
`assign_capability(overwrite=false)`. `manage_all` stays manager-only. Version
2026061600 → 2026061700. Verified: a system-context trainer (qa_trainer) resolves
`has_capability(:generate)`=YES. **Last mile = provisioning:** airpay assigns
`trainer` at CATEGORY context, so SME authors need that role (or a dedicated
Author role) at SYSTEM context to use this CONTEXT_SYSTEM tool — per-deployment.

## Update 2026-06-17 — dedicated "Sentientia Author" system-context role
Closes the provisioning last-mile above without over-granting the broad `trainer`
role. `db/upgrade.php` step **2026061701** ships a scoped role:
`Sentientia Author` (shortname `sentientiaauthor`), **assignable at SYSTEM context
only**, granting exactly the five author/SME caps —
`authoring:generate|review|managetemplates` + `skillsai:extract|review` — and
nothing else (no archetype, so no teacher/manager breadth). Idempotent: created
only when the shortname is free; caps re-synced each run; caps whose owning plugin
isn't installed are skipped. Auto-creates on every deployment (Airpay + future
customers) — no manual step. Version 2026061700 → 2026061701.
Provisioning helper: `docs/audits/brand-revamp-2026-06/assign_author_role.php`
(idempotent — assigns named SMEs at system context + verifies `has_capability`;
`UNASSIGN=1` to revoke; dry-run audits the role). **Verified end-to-end:** role
id=11 SYSTEM-only with all 5 caps ALLOW; assigning `asif.ansari@airpay.co.in`
(uid 2304, Course Author persona) → `has_capability`=YES for all five at system
context.

## 2026-08-05 — Sentientia AI gateway opt-in migration (v2026080500 / 0.1.1-alpha)

Consumer migration onto `local_sentientia_ai` (ADR-028 Phase 2.3, README
recipe; reference: aiquiz 2026080402). `course_generator::generate()` now
delegates to `\local_sentientia_ai\client::complete()` ONLY when the gateway
class exists AND `sentientia.ai.gateway.enabled` is ON. Default OFF → byte-
and side-effect-identical legacy path (local mock, NO ledger writes). The
full-module mock (v2-hindi Devanagari cards + multichoice/mrq/match) passes
down as the `'mock'` callable; gateway `denied` maps to `failed` (studio
persists a retriable draft). Plugin gates unchanged (enabled/live_api flags
+ studio.php [CONFIRM] + human-review gate). Purpose slug:
`course_generation`. **Ops caveat:** this plugin's legacy key setting is
`anthropic_api_key`, which the gateway's `legacy_component` bridge (reads
`api_key`) does NOT pick up — on the gateway path only the CENTRAL
`local_sentientia_ai | api_key` applies. **Scope:** Anthropic generation
only; `tts_client` (ElevenLabs) is outside the gateway and unchanged.
Standalone fallback kept. Mirrored to top-level local/ (plugin was MISSING
there — full dir seeded) + deployed to XAMPP webroot.

## 2026-08-05 — gate #3 closure: REAL course builder (v2026080501 / 0.2.0-alpha)

`mark_published()` finally has a production caller. NEW `classes/course_builder.php`:
an APPROVED draft becomes a real HIDDEN topics course — Section 1 = one mod_book
"Course content" with a chapter per approved/edited card (heading → title; body
paragraphised + flip-back as an alert-info callout + narration as a <details>
transcript; direct {book_chapters} inserts per the mod_book generator's own
pattern + revision bump); Section 2 = a mastery quiz (skipped cleanly when no
approved questions): GIFT-imported into the course's default shared question
bank (5.x question_bank_helper — same proven pattern as aiquiz G.4, kept
plugin-local since shapes differ: per-answer feedback via GIFT `#`, per-question
points as slot maxmark, gradepass = QUIZ_GRADE × mastery_score%). One delegated
transaction; draft records the real course id. review.php gains the publish
action + button (approved drafts only) with a course-category selector
(make_categories_list('moodle/course:create')). NEW flag
`sentientia.authoring.publish.enabled` default OFF (ninja verification flips it).
Capability gates: moodle/course:create (category) + moodle/question:add (bank).
20 en+hi string pairs (parity green). NEW tests/course_builder_test.php
(6 tests: happy path incl. gradepass 8.0@80% + bank entries + published id,
rejected-item exclusion, quiz-less build, status gate, transactional capability
denial, card HTML composition incl. escaping).
