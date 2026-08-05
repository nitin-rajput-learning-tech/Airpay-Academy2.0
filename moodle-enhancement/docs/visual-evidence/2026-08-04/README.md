# Visual evidence — 2026-08-04 (UI-NAV residue closure + ninja package rebuild)

Theme `sentientia` bumped **2026080300 → 2026080301** (`1.0.50-beta`).
Deployed + verified on local XAMPP (http://localhost:8080) as `qa_employee`.

## 1. `course_default` banner 404 → fixed (NEW asset)

`course_bannerimage()` (`theme/sentientia/classes/output/traits/course_view.php:74`)
falls back to `image_url('course_default', 'theme_sentientia')` for every course
without an uploaded overview image — but the pix asset was never shipped, so the
fallback 404'd (UI-NAV-AUDIT residue). New branded SVG banner added:

- Asset: [`course_default-banner-asset.svg`](course_default-banner-asset.svg)
  (login-hero brand gradient `#003d66 → #0066A7 → #0d5da1`, decorative circles,
  'a' monogram watermark; 1.5 KB).
- Runtime verification (in-app browser, authenticated session):

  ```json
  {"bannerUrl":"/theme/image.php/sentientia/theme_sentientia/<themerev>/course_default",
   "status":200,"type":"image/svg+xml"}
  ```

  Consumers: `course_full_header.mustache` (CSS `url()` background — the
  injection-safety comment there already documented this exact fallback),
  catalog/detail card surfaces via `core_renderer` full-header context.
  Note: the learner course shell intentionally compacts the header (banner
  chrome hidden), so the visible surfaces are catalog/detail cards.

## 2. `dark_mode.scss` component-rule tokenization (visually a NO-OP by design)

104 hex literals in the component-rule section (lines 76–572) replaced with
`var(--ap-color-*)` tokens. The core token-remap block (lines 8–74) and the
`body.high-contrast` section (581–865) are deliberately untouched — the
high-contrast section has **no token remap of its own**, so substituting vars
there would resolve to light `:root` values and change rendering.

Proof of pixel-identity (screenshot intentionally replaced by measurement —
a screenshot can't prove "identical", the computed values can):

1. **Compile equivalence**: `npx sass` compile of before/after; reverse-mapping
   the 104 `var()` substitutions in the after-CSS yields output **byte-identical**
   to the before-CSS. Line count unchanged, braces balanced 176/176.
2. **Runtime token resolution** under `body.dark-mode` (dashboard, live CSS
   themerev `1785808689_…`) — every token resolves to exactly the hex it replaced:

   | Token | Resolves to | Replaced hex |
   |---|---|---|
   | `--ap-color-bg-body` | `#0f1117` | `#0f1117` (×2) |
   | `--ap-color-bg-surface` | `#1a1d27` | `#1a1d27` (×19) |
   | `--ap-color-bg-surface-alt` | `#232733` | `#232733` (×14) |
   | `--ap-color-border` | `#2d3140` | `#2d3140` (×28) |
   | `--ap-color-border-strong` | `#3d4254` | `#3d4254` (×9) |
   | `--ap-color-text-primary` | `#e8eaed` | `#e8eaed` (×19) |
   | `--ap-color-text-secondary` | `#9ca3b4` | `#9ca3b4` (×10) |
   | `--ap-color-accent` | `#1985DD` | `#1985DD` (×1) |
   | `--ap-blue-deep` | `#0d5da1` | `#0d5da1` (×2, gradient end-stops) |

   Body paints `rgb(15, 17, 23)` = `#0f1117`. ✅

   Served-CSS greps: `var(--ap-blue-deep)` present (only exists via this change),
   `var(--ap-color-bg-surface)` / `var(--ap-color-accent)` present; residual
   `#1a1d27` occurrences are the expected keepers (token-definition remap block +
   high-contrast section + certificate paper pin).

3. The scssphp `Array to string conversion (Compiler.php:927)` warning seen
   during the upgrade run is **pre-existing** `@extend` selector machinery
   (Bootstrap extend chains) — this change altered declaration *values* only,
   no selectors, no `@extend`.

## 2b. ADR-028 Phase 1.5 quick-win batch (afternoon wave, theme 2026080302)

**Shell-chrome i18n** — 31+4 new en+hi string pairs (sidebar 40 call-sites, course
player + aria-labels, topbar). Browser-verified as `qa_employee`:

- en dashboard: sidebar `Dashboard / My Courses / Catalog / My Skills / Certificates`,
  search placeholder byte-identical, 0 `[[key]]` placeholders.
- hi dashboard (`?lang=hi`): `डैशबोर्ड / मेरे कोर्स / कैटलॉग / मेरी स्किल्स / सर्टिफ़िकेट`,
  placeholder `कोर्स, लोग, कंटेंट खोजें...`, aria-labels `मेनू खोलें` / `साइडबार टॉगल करें`,
  0 placeholders. (First pass caught 2 additional emit sites the class-level fix
  missed — `dashboard.mustache` + `topbar.mustache` build their own topbars; fixed
  + 5 sibling hardcoded aria-labels.)
- Course player (course 403): `Course Content` sidebar title + `0/2 · 0%` progress
  render via the new keys.

**Lang-parity gate** (`tools/check-lang-parity.php` + CI job `lang-parity-check` +
pre-commit CHECK 17): first run caught 6 pre-existing FAILs beyond today's work —
81 missing hi strings (catalog 16 / m365 33 / translate 30 / theme 3, wait — theme 3
+ 79 agent) and **2 genuine en bugs** (classroom capability + privacy:metadata,
programs privacy:metadata rendered raw keys in English). All closed: final gate
`56 en files checked, 0 failure(s), 8 warning(s)` (warnings = the known en-only
pack backlog, tracked in the gate's `$failonmissingpack` flip).

**T-01 capability back-fill** (aiquiz 0.2.1-alpha, leaderboard 0.2.1-alpha) —
verified post-upgrade on the local prod-import DB:

```
local/sentientia_aiquiz:generate  -> editingteacher, manager, administrator, teacher, trainer, sentientiaauthor
local/sentientia_aiquiz:review    -> (same six)
local/sentientia_leaderboard:manageboard -> editingteacher, manager, administrator, teacher, trainer
```

Evidence-based non-fixes: `translate:translate` manager-only is BY DESIGN
(cost-sensitive, per its access.php docblock); `xapi:managelrs/deletestatements`
+ `learningpath:delete` deliberately site-admin-only (RISK_DATALOSS / "site admin
only" comments). reCAPTCHA "gap" reclassified: signup already implements
honeypot + reCAPTCHA v2 (P1 #59) — the gap is unset production keys (config item,
see docs/security/ENTERPRISE-IDENTITY-PACK.md).

**Privacy registry** — 8 null providers added (evidence-checked safe); 8 plugins
flagged needing REAL providers (spawned as follow-up task); upgraded all 9
touched components to 2026080400.

## 2c. AI Gateway shipped (`local_sentientia_ai` 0.1.0-alpha) — ADR-028 Phase 2.3

The precondition for the entire signed Addendum-A budget: one gateway replacing
six duplicated per-plugin Anthropic clients. Central key, spend ledger
(`local_sentientia_ai_ledger`), fail-closed quotas (0/empty cap = live BLOCKED),
mock-first routing, REAL privacy provider (ledger is user-attributed; prompt
text never stored). Both flags default OFF.

Verification (local):
- Fresh install clean: table + 5 settings + 2 caps + 2 registry-discovered flags.
- CLI smoke: generic mock ledgered (row 1); **aiquiz migrated dispatcher**
  (0.2.2-alpha, first consumer) returns byte-faithful mocks through the gateway
  incl. v2-hindi Devanagari (rows 2–3); quota aggregates exclude mock/denied.
- Ledger admin page (`/local/sentientia_ai/index.php`, qa_siteadmin): headline
  aggregates + 30-day per-feature roll-up + recent calls render, 0 broken keys;
  anonymous → 303 to login (no bootstrap fatal).
- PHPUnit (full story, all defects fixed same-day):
  1. Gateway suite 11/11 GREEN (35 assertions). Its first run exposed two real
     defects, both fixed: tests could reach the REAL Anthropic API
     (install-applied setting defaults gave the quota check headroom; a fake
     key was actually POSTed once) → structural PHPUNIT/BEHAT no-spend guard
     now inside `gateway::call_live()`; and the platform flag resolver's PHP
     statics leak across test classes → `setUp()` invalidation.
  2. First-cut aiquiz delegation broke 17 of its own tests (unconditional
     routing wrote ledger rows in non-reset test contexts) → routing made
     OPT-IN via `sentientia.ai.gateway.enabled` (default OFF = byte- and
     side-effect-identical legacy path; aiquiz 2026080402).
  3. The combined run then exposed two PRE-EXISTING aiquiz bugs (both
     date from Phase G.1, 2026-05-27, unrelated to today): `draft_manager`
     named the BizLMS `open_path` column in a SELECT → fatal on any
     vanilla/Customer-N schema (fixed: schema-portable `SELECT *`; the
     tenant resolver already isset()-guarded absence), and a
     `prompt_builder` test expecting 4 words in a 5-token string (test
     authoring miscount; implementation was right).
  Final combined result recorded in PROJECT-STATE.
- Ops note: local Apache died silently mid-verification (console-mode
  fragility, no crash log; opcache confirmed OFF for web) — restarted via
  `httpd.exe`, site healthy after.

One-click enrol (item 2): flag confirmed ON for tenant /1 locally (May-29
enable survived; audit-logged re-affirm), OFF for Public /77 by policy; the
production flip already rides the ninja cutover packet (step 5) + package
DEPLOY-README (step 10). No further action.

## 3. Ninja package rebuilt to carry the UI-NAV wave

Overlay re-run (65,349 files at the 5.2 target, AMD-rename gate: 0 stale
tokens), UI-wave artifacts spot-verified in the 5.2 tree (shell
`course.mustache`, `course_editing.mustache`, breadcrumbs + topbar-icon
renderer, tokenized `dark_mode.scss`, `course_default.svg`,
version `2026080301`) → repackaged as
`Sentientia-LMS-5.2-Complete-Standalone-2026-08-04.zip` (SHA-256 in the
regenerated Deployment Guidebook PDF; 2026-08-03 zip renamed `.superseded`).
