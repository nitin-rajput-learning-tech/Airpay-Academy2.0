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
