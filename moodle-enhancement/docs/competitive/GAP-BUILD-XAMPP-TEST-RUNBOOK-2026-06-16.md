# XAMPP Test Runbook — Competitive Gap Build (11 workstreams)

**Date:** 2026-06-16
**Branch under test:** `claude/gap-integration` (all 11 gap builds merged; base = gap-analysis docs branch)
**Scope:** Local install/upgrade + PHPUnit + smoke test of the 11 competitive-roadmap builds on Moodle 5.1 XAMPP. **Nothing here has been deployed to live.**

> The cloud build container has PHP 8.4 but **no Moodle DB**, so only static checks ran there
> (all green: 204 PHP files lint-clean, install.xml well-formed, 0 conflict markers, en/hi
> parity exact). The steps below are the dynamic tests that must run on `C:\xampp\htdocs\moodle5\public\`.

---

## 1. What's in the build

| # | Branch | Plugin | Type | Lang parity |
|---|--------|--------|------|-------------|
| 1 | gap-skillsai | `local_sentientia_skillsai` (P0.1) | NEW | 149/149 |
| 2 | gap-authoring | `local_sentientia_authoring` (P0.3) | NEW | 181/181 |
| 3 | gap-adaptive | `local_sentientia_learningpath` v1.8.0 (P0.2) | EXTEND | 96/96 |
| 4 | gap-content-market | `local_sentientia_content_market` (P1.1) | NEW | 67/67 |
| 5 | gap-analytics-predictive | `local_sentientia_analytics` v1.1.0 (P1.2) | EXTEND | 44/44 |
| 6 | gap-copilot | `local_sentientia_assistant` v1.2.0 (P1.3) | EXTEND | 59/59 |
| 7 | gap-xapi | `local_sentientia_xapi` (P1.4) | NEW | 80/80 |
| 8 | gap-trust | `docs/security/*` (P1.5) | DOCS | n/a |
| 9 | gap-talent | `local_sentientia_talent` (P2.1) | NEW | 85/85 |
| 10 | gap-mobile | `mobile/sentientia-app/` Capacitor (P2.2) | SCAFFOLD | n/a |
| 11 | gap-api-lti | `local_sentientia_api` (P2.3) | NEW | 49/49 |

All feature flags ship **default OFF**; all AI/TTS in **mock-mode** (no live API spend) until flags are flipped.

---

## 2. Deploy to XAMPP (Moodle 5.1, `public\`)

```powershell
# From the repo on branch claude/gap-integration:
#   git fetch origin && git checkout claude/gap-integration

# New plugins → public\local\
Copy-Item moodle-enhancement\local\sentientia_skillsai       C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_authoring      C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_content_market C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_xapi           C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_talent         C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_api            C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force

# Extended plugins → overwrite existing dirs (back up first)
Copy-Item moodle-enhancement\local\sentientia_learningpath   C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_analytics      C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force
Copy-Item moodle-enhancement\local\sentientia_assistant      C:\xampp\htdocs\moodle5\public\local\ -Recurse -Force

# Mobile scaffold is NOT a Moodle plugin — it stays under moodle-enhancement\mobile\ (build separately per BUILD.md)
```

**Dependency note:** `skillsai` depends on `local_sentientia_platform` + `local_sentientia_skills` (both already installed). `adaptive`, `talent`, `content_market`, `analytics-predictive` all *optionally* consume `skillsai` and degrade gracefully if it's absent — but install `skillsai` first so the full path is exercised.

```powershell
# Run the upgrade (creates new tables) + purge caches:
php C:\xampp\htdocs\moodle5\admin\cli\upgrade.php --non-interactive
php C:\xampp\htdocs\moodle5\admin\cli\purge_caches.php
```
Expected: 6 new plugins install, 3 plugins upgrade (version bumps + new tables). Watch the Apache error log for notices.

---

## 3. PHPUnit (per plugin)

```powershell
# Init test env once, then run each suite:
php C:\xampp\htdocs\moodle5\admin\tool\phpunit\cli\init.php
cd C:\xampp\htdocs\moodle5\public
vendor\bin\phpunit --filter local_sentientia_skillsai
vendor\bin\phpunit --filter local_sentientia_authoring
vendor\bin\phpunit --filter local_sentientia_learningpath
vendor\bin\phpunit --filter local_sentientia_content_market
vendor\bin\phpunit --filter local_sentientia_analytics
vendor\bin\phpunit --filter local_sentientia_assistant
vendor\bin\phpunit --filter local_sentientia_xapi
vendor\bin\phpunit --filter local_sentientia_talent
vendor\bin\phpunit --filter local_sentientia_api
```
Tests were written but **could not be executed in the build container** (no Moodle DB). Expect to fix harness-specific issues on first run.

---

## 4. Smoke test (enable flags per tenant via switchboard)

Switchboard: `/local/sentientia_platform/admin/switchboard.php`. Enable per flag, test as a **Learner** (not admin), desktop + 590px mobile, zero console errors.

| Flag | Surface to verify |
|------|-------------------|
| `sentientia.skillsai.enabled` | `/local/sentientia_skillsai/` extract → review gate → promote to taxonomy → gaps → impact |
| `sentientia.authoring.enabled` | `/local/sentientia_authoring/studio.php` prompt→draft (mock), MRQ/match question types, review→publish gate; templates CRUD |
| `sentientia.learningpath.adaptive.enabled` | set a path `adaptive_mode=1`, submit a quiz, confirm remediate/accelerate log row; flag-OFF = identical to today |
| `sentientia.content_market.enabled` | `/local/sentientia_content_market/` browse (mock provider), skills mapping |
| `sentientia.analytics.predictive.enabled` / `.roi.enabled` | analytics dashboard predictive + ROI panels; flag-OFF = byte-identical dashboard |
| `sentientia.assistant.agentic.enabled` | agent panel: a tool call is capability+tenant-gated, write requires confirm, audit row written; flag-OFF = legacy nav assistant |
| `sentientia.xapi.enabled` | LRS endpoint auth + statement validation; completion event emits a statement |
| `sentientia.talent.enabled` | career paths, succession (manager-only), opportunity board |
| `sentientia.api.enabled` (+ `.write`, `.lti`) | `/v1/` endpoints token+tenant gated; LTI launch JWT validation; flag-OFF = 404 |

---

## 5. Known pre-deploy fixes (from agent state cards)

1. **copilot privacy provider** — `local_sentientia_assistant` adds a userid-keyed `local_sentientia_agent_audit` table but still ships a `null_provider`. **Must** add a real privacy provider declaring that table before any production enable (DPDP).
2. **authoring** — live ElevenLabs TTS + publish→course/SCORM wiring are deferred (documented); mock path only in this build.
3. **api-lti** — LTI JWKS-URL fetch + `kid` selection and claims→user provisioning are documented extension points.
4. **mobile** — backend push extensions (`save_subscription.php` SSRF allowlist + `push_sender.php` FCM/APNs routing) are P0 before any native build; see `mobile/sentientia-app/BUILD.md`.

---

## 6. Rollback

All changes are additive + flag-gated. To revert: disable the flags (instant), or remove the 6 new plugin dirs and restore the 3 extended plugins from backup, then re-run `upgrade.php`. No live data is touched while flags are OFF.
