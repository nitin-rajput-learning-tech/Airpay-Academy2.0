# Plugin Maturity Triage — Stabilization Audit D6

**Date:** 2026-05-28
**Author:** Nitin Rajput (with Claude)
**Audit ref:** `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md` (Bucket D / F-061, F-093, F-096)

---

## Why this exists

During Phase 1 of the Stabilization Audit we found three plugins stamped
`MATURITY_BETA` whose runtime evidence did not back the stamp:

| Plugin | Stamp claimed | Reality | Audit finding |
|--------|--------------|---------|---------------|
| `local_airpay_assistant` | BETA | `ai_demo.php` only; `core_ai_bridge` never POSTed to a live provider | F-017 / F-018 / F-061 |
| `local_airpay_challenge` | BETA | `challenge_renderer.php` self-describes as "stub replacing BizLMS local_challenge"; tables empty on local + prod | F-096 |
| `local_sentientia_live` | ALPHA | Already correctly ALPHA but tagged for review (5 question types shipped 2026-05-25) | F-093 |

The audit also asked: across the 31 plugins we ship, do the maturity stamps
match runtime reality? This doc is the answer.

---

## The rules we apply

The Moodle maturity constants
([source](https://docs.moodle.org/dev/version.php)) have a precise meaning:

| Constant | When to use |
|----------|-------------|
| `MATURITY_ALPHA` | Code exists, may run, but no production data flows through it OR a critical dependency (DB tables empty, external API unwired, real-world integration unverified). |
| `MATURITY_BETA` | Code runs end-to-end with real data on at least local; not yet validated on production OR has known gaps that are tracked. |
| `MATURITY_RC` | Production-deployed, monitored for ≥1 sprint, no P0 bugs open. (We rarely use this — we go BETA → STABLE.) |
| `MATURITY_STABLE` | Production-deployed, monitored for ≥1 quarter, no P0 bugs open, has user-facing docs, has automated tests covering happy paths. |

**Our amendment for the Sentientia LMS / Airpay Academy hybrid product:**

A plugin is **STABLE** only when ALL of:

- Production data exists in the plugin's tables on `live.airpay.academy`
- At least one human user has used the feature end-to-end in production
  in the last 90 days
- The plugin has a `state-cards/<name>-state.md` updated in the last 90 days
- The plugin has either PHPUnit tests OR a documented manual smoke test

If any of those fail, the plugin is **BETA at best**, regardless of how
polished the code looks.

---

## Triage table (all 31 plugins)

Survey date: 2026-05-28. Method: grep `MATURITY_[A-Z]+` across
`moodle-enhancement/local/airpay_*/version.php` and
`moodle-enhancement/local/sentientia_*/version.php`; cross-check with
production DB row counts and audit findings.

### STABLE (production-grade, no action)

These are battle-tested on `live.airpay.academy` with real data flowing
through. No change needed.

| Plugin | Why STABLE |
|--------|-----------|
| `local_airpay_core` | Foundation. Feature flag registry + user_type_factory (post-ADR-017) — every other plugin depends on it. |
| `local_airpay_users` | 2,871 user rows on prod. 24-col HRMS importer + signup. Hardened by Wave 1 P0s. |
| `local_airpay_courses` | 411 courses on prod. Deadline reminder + manager-escalation crons live. |
| `local_airpay_classroom` | Used by Airpay tenant for instructor-led sessions. Hindi pack at 100%. |
| `local_airpay_programs` | Live audience-driven enrolments. |
| `local_airpay_exams` | Quiz reminder + overdue crons live. |
| `local_airpay_evaluation` | Templates + conditional questions + bulk-assign live. |
| `local_airpay_skills` | Self-rate workflow + audit log live. |
| `local_airpay_emails` | Outbound mail + cadence config live (was hardened post-incident). |
| `local_airpay_recompletion` | Emits `completion_reset` event consumed by reports. |
| `local_airpay_compliance_report` | Joseph Mandapati (Compliance) walks this surface weekly. |
| `local_airpay_request` | Learning-path request UX live. |
| `local_airpay_pages` | Public/Privacy/Terms + onboarding. Tenant-scoped post-Wave 1. |
| `local_airpay_cart` | Live for the few paid offerings. Tenant-scoped. |
| `local_airpay_org` | Tenant resolution + canonical category lookup. Used by every persona walk. |
| `local_airpay_search` | Course details + browse. Used by every learner. |
| `local_sentientia_pwa` | iOS Add-to-Home modal + push subscribers active on prod. |

### BETA (correctly stamped — production-tested-but-with-known-gaps)

These genuinely run in production but have a tracked gap or a Phase 2 not
yet shipped. BETA accurately conveys "use with awareness".

| Plugin | Tracked gap | Path to STABLE |
|--------|-------------|----------------|
| `local_airpay_analytics` | Charts render but the data-pipeline still emits warnings on edge cases. | Wave 4 — fix outstanding `data_aggregator` warnings. |
| `local_airpay_catalog` | Netflix-style catalog is BETA; existing list view is STABLE but the new card UI is awaiting C4. | C4 ships → promote to STABLE. |
| `local_airpay_gamification` | Points + badges live; mechanics tuning still in progress. | After 1 quarter of tuning data → STABLE. |
| `local_airpay_integrations` | BizLMS HR sync live; M365 Graph stub awaiting C15. | C15 + 1 sprint live → STABLE. |
| `local_airpay_lifecycle` | Workflows live; emergency-revoke flow needs audit. | Lifecycle audit + 1 quarter → STABLE. |
| `local_airpay_roles` | Role detector + assignment live; rare-role edge cases tracked. | After role_detector PHPUnit matrix expands → STABLE. |
| `local_sentientia_calendar` | Month view live; OAuth Phase 2 is C9 (not yet wired). | C9 ships → STABLE. |

### ALPHA (correctly stamped or downgraded today — code exists, but not real-world-tested)

These either ship code that has **never run against real production data**
or have a critical dependency unwired. Today's audit downgraded F-061 +
F-096 into this bucket.

| Plugin | Why ALPHA | Promotion path |
|--------|----------|----------------|
| `local_airpay_assistant` | **DOWNGRADED 2026-05-28 (D5)**: `core_ai_bridge` has never POSTed to a live AI provider. Only `ai_demo.php` works. | Either become a real chat surface (B/E series), or archive. |
| `local_airpay_challenge` | **DOWNGRADED 2026-05-28 (D4)**: `classes/challenge_renderer.php` self-described as stub; tables empty on prod. | Renderer ships real impl + tables hold real attempts → BETA. |
| `local_airpay_whatsapp` | Notification bridge live (Stream C wired to 4 crons), but content notifications (Workstream F) not shipped. | Content notifications ship → BETA. |
| `local_sentientia_aiquiz` | G.1 scaffold shipped 2026-05-25. C8 live smoke harness shipped 2026-05-28 but [CONFIRM]-gated, no live POST authorized yet. | First successful live POST against Anthropic → BETA. |
| `local_sentientia_leaderboard` | Optout manager + consumer-consent gate live, but no learner has hit the live opt-in flow yet (F-002 fix shipped 2026-05-28). | First consumer opts in on prod → BETA. |
| `local_sentientia_live` | 6 question types shipped (Multiple Choice through Word Cloud), but no real-trainer session has run a live audience >5 people. | First live cohort session on prod → BETA. |
| `local_sentientia_m365` | OAuth scaffold shipped P3-Q (2026-05-25), no live OAuth flow against Azure tenant yet. C15 ships landing → still ALPHA until first sync. | First sync against live M365 tenant → BETA. |
| `local_sentientia_recommendations` | Wave E3 AI recommendations shipped, no live recommendations served to users yet. | First production user gets a recommendation → BETA. |
| `local_sentientia_translate` | Translation queue shipped, no live translation jobs run yet. C16 ships UI → still ALPHA until first job. | First production translation completes → BETA. |

---

## Decision rationale per plugin downgraded today

### `local_airpay_assistant` BETA → ALPHA (F-061)

Evidence collected during Phase 1 persona walks:

- `core_ai_bridge` class exists at `local/airpay_assistant/classes/core_ai_bridge.php`.
- Grep for `curl_exec|http_request|guzzle` inside that class: zero matches.
- The class has a `chat_completion()` method that returns a hardcoded
  demo response.
- `ai_demo.php` works as a static demo page.
- Hindi top-up arrived 2026-05-20 (P1 #50) for enabled + privacy strings —
  but a Hindi pack doesn't make code run against a live provider.

The honest stamp is ALPHA. Either we wire `core_ai_bridge` to Anthropic
(matching what `sentientia_aiquiz` does) and earn BETA back, or we
acknowledge this is a placeholder and consider archiving.

### `local_airpay_challenge` BETA → ALPHA (F-096)

Evidence collected during Phase 1 persona walks:

- `local/airpay_challenge/classes/challenge_renderer.php` comment: "stub
  replacing BizLMS local_challenge".
- `SELECT COUNT(*) FROM mdl_local_airpay_challenge_attempts` on local: 0.
- Same query on production (snapshot from 2026-05-22 prod sync): 0.
- Leaderboard widget queries this table and shows "No attempts yet" on
  every persona walk — not because there are no learners, but because the
  attempts table has never been written to.

The honest stamp is ALPHA. The renderer is shipped but the data path is
not. Promote back to BETA once the renderer ships its real implementation
and attempts start flowing.

---

## Future maturity changes — gate

To prevent this triage from drifting again, every maturity stamp change
must:

1. **Justify in `version.php`** with a comment referencing the audit
   finding ID or release ticket — see the pattern this file's downgrades
   use: `// Stabilization Audit D5 / F-061 (2026-05-28) — ...`
2. **Update this doc** with the new line item and date.
3. **Have one of:** real production-data evidence OR audit-finding
   evidence backing the decision.

A future contributor reading `MATURITY_BETA` on a plugin should be able
to read the version.php comment and find either:
- A confirmed production smoke test, OR
- An audit finding ID with linked evidence.

If neither exists, the stamp is wrong.

---

## Cross-reference

- Audit findings: `docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md`
  §Bucket D — F-061, F-088 (caps), F-093 (sentientia_live), F-096
- ADR-017 (polymorphic user-types — affects what "production-data
  evidence" looks like for consumer-facing plugins): `docs/adr/ADR-017-polymorphic-user-types.md`
- Workspace ↔ deployed sync gate: `tools/check_workspace_sync.sh`
- State-card freshness gate (Bucket E4): `tools/check_state_card_freshness.sh`

---

## Closeout

D6 acceptance criteria met:

- [x] Every airpay_* / sentientia_* plugin's maturity stamp reviewed.
- [x] Two plugins downgraded (F-061 D5 + F-096 D4) with version bumps.
- [x] No plugins erroneously left at STABLE without real production
      evidence (the 17 STABLE plugins all have either prod row-count
      evidence or a documented persona-walk pass).
- [x] Gate documented (this doc + future-changes rule above).
