## 5. Where Sentientia stands — from the code, not the brochure

Repository: `D:\Claude Local\airpay-ld-os`, branch `claude/gap-integration` at 7366c6050 (2026-10-15). Sentientia runs on Moodle 5.1.3 locally, 5.2 on UAT, with 5.3 LTS the cutover target (ADR-033). The live airpay.academy site is still the legacy BizLMS 4.1.2 deployment; Sentientia is not live yet.

### 5.1 The content pipeline as it exists

| Component | What the code does | Gap against the report's mandates |
|---|---|---|
| `sentientia/run_pipeline.py` + six agents (last touched 2026-05-13) | Orchestrates agents by subprocess from its own directory. Agent 2 calls Claude live when confirmed (model string `claude-sonnet-4-5-20250929`, stale); Agent 3 Gamma is a skeleton; **Agent 4 ElevenLabs raises "skeleton — not yet wired"** (`agent4_voice_generator.py:153-178`); Agent 5 packages SCORM 1.2; Agent 6 uploads (mock default). | The orchestrator runs the stale tree; the Decision Memo's statement that "live ElevenLabs call paths exist in both the PHP plugin and the Python pipeline" is true only of the other tree. |
| `scripts/agents/` (last touched 2026-05-27) | Newer copies: Agent 4 has a wired `requests.post` ElevenLabs path; Agent 2 defaults to `claude-opus-4-7`. **No file is identical between the two trees.** | Two sources of truth for a sellable feature. |
| `agent5_scorm_packager.py` | Correct root manifest, `<adlcp:masteryscore>70`, lowercase `scormtype`, all files declared, 50 MB cap, slide/bullet limits. Runtime: `cmi.suspend_data = JSON.stringify(data)` (line 297), no `cmi.core.exit="suspend"`, no `cmi.interactions`, one `narration.mp3` per course, no captions, `<html lang="en">` hardcoded (line 352), finish on `beforeunload` only. | Exactly the four Moodle traps the report describes (suspend_data, exit, interactions, mastery) minus the mastery one, which is handled. SCORM 2004/cmi5: none. |
| `content/` | One synthetic SOP (165 words) → one package. | Validated on one sample. |
| `local_sentientia_authoring` 0.2.2-alpha | Prompt/doc → cards (concept/example/scenario/flip) + MCQ/MRQ/match with feedback; templates; TTS job table (mock); mandatory review gate; **real course builder since 2026-08-05** (mod_book chapters + GIFT mastery quiz into a hidden course); author role seeded on fresh install (T-01 closed 2026-09-07); DPDP erasure anonymises; ADR-031 tenant scoping. Flags `enabled/tts/live_api/publish.enabled` all OFF. | No scenario graph model, no packaging to any standard, TTS never live, `tts_client` outside the AI gateway. |
| `local_sentientia_translate` 0.2.2-alpha (ADR-016) | Paste text → hi/mr/kn/sw via Claude (mock default), brand-term protection in prompt **and** deterministic post-processing, side-by-side diff before save, per-customer brand overrides, admin queue. | No XLIFF, no TM, no bulk course walk, no lang-pack output (T.1–T.4 deferred). |
| `local_sentientia_ai` 0.1.1-alpha | Single gateway: central key, spend ledger, fail-closed quotas (0 = blocked), mock-first routing, golden fixtures; six consumers routed (aiquiz, skillsai, recommendations, translate, authoring, assistant). Default model `claude-sonnet-4-6`. | Live blocked on the Addendum-A cap figure and `ANTHROPIC_API_KEY`; no live call has ever been made. TTS not routed through it. |
| `local_sentientia_aiquiz` 0.3.1-alpha | Real mod_quiz/question-bank publisher (G.4), v1 + v2-hindi prompts, PII heuristic (Aadhaar/PAN), 4-layer cost defence. | Only MCQ; separate from authoring's question model. |
| `local_sentientia_xapi` 1.0.2 | xAPI 1.0.3 statements endpoint (Bearer/Basic, tenant-scoped), cmi5 AU session tracker, observers for course completion/quiz/module view, retention purge; 5 flags OFF. | Statements-only (not a conformant LRS); no cmi5 launcher in Moodle. |
| Core Moodle in tree | `mod/scorm` with SCORM 1.2 and 2004 datamodels; `mod/h5pactivity` (H5P Branching Scenario content type); `mod/lesson`; `filter/multilang`; `admin/tool/mfa`; `auth/oauth2`. SCORM 1.2 API round-trip verified under the theme (`PHASE-H-SCORM-E2E.md`, 7/7). | No cmi5 player; H5P branching is authored by hand, not generated. |

### 5.2 Localisation and languages today

- UI language packs: Hindi 47/47 plugins; Kannada, Marathi, Swahili 12/47 (counted 2026-10-15). Parity is convention-enforced; CI has no lang-parity gate.
- Human review loop exists for UI strings: `TRANSLATION-REVIEW-INSTRUCTIONS.md` (conversational register, loanwords, placeholders preserved) — a good style guide to reuse for content.
- No RTL language is shipped; the theme carries `bootstrap-rtl.scss` from its Boost ancestry; Moodle's `ar` and `ur` packs are available for chrome.
- No translation memory, no XLIFF, no glossary beyond the brand manager's protected list.

### 5.3 Reinforcement, scenarios, analytics

- Notifications/emails are stable production plugins; web push works (PWA alpha); WhatsApp/SMS bridge is mock-only. No spaced-repetition engine (ADR-028 Phase 3.7 "Daily Review" is planned, not built); the heuristic adaptive-journey engine is flag-OFF.
- No branching-scenario or roleplay code anywhere in the Sentientia plugins (git grep confirms); the only references are in ADR-001/028 and the maturity audit.
- Analytics: `local_sentientia_analytics` (descriptive KPIs, flag-OFF) over direct SQL; production reporting still rides the patched LearnerScript vendor plugin. No decision-level (per-edge) analytics; `cmi.interactions` are not written by our packages.

### 5.4 Platform strengths the plan builds on

- 5-level feature flags (`local_sentientia_platform\feature_flags`), per-customer branding (`customer::branding()`), tenant scoping that fails closed (ADR-031), privacy providers across plugins with DPDP erasure that anonymises, the AI gateway's ledger/quotas/golden-set pattern, the mandatory review gates in aiquiz/authoring/translate, and a theme with a token system (`_tokens.scss`), dark mode, reduced-motion tokens, app-shell personas and a living style guide (`local/sentientia_platform/admin/styleguide.php`).
- Strategy record: the Invince gap analysis (2026-06-16), ADR-028 with the signed decision memo (India BFSI beachhead + global; trust track funded ₹80–120L; TTS evaluation; native app funded), the pricing draft (pipeline as add-on/Enterprise tier), and `SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md`.

### 5.5 Where the written record overstates the code

1. "Live in production serving 3,500+ learners" (Product Guide) describes the legacy BizLMS site; Sentientia is on UAT and local.
2. "Live ElevenLabs paths exist in the Python pipeline" is true for `scripts/agents/`, not for the tree the orchestrator runs.
3. CLAUDE.md §9 still describes Gamma as Agent 3's vendor; there is no Gamma client anywhere.
4. The Invince gap analysis lists xAPI/cmi5 as absent; `local_sentientia_xapi` has existed since June.
5. The authoring state card header still says 0.1.0-alpha with "no course builder"; the course builder landed in August (recorded lower in the same card).
6. The Product Guide counts 27 ADRs; the index has 33 (ADR-034 will be the 34th).

---
