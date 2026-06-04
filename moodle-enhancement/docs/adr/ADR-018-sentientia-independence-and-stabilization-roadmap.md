# ADR-018 — Sentientia LMS Independence + 100%-Stabilization Roadmap

**Status:** Accepted (roadmap) · Wave 1 in execution
**Date:** 2026-05-29
**Owner:** Nitin Rajput
**Supersedes/extends:** ADR-001 (fork strategy + product pivot), ADR-002 (customer-level flags), ADR-008 (customer brand), ADR-017 (polymorphic user types)
**Grounded by:** the `sentientia-independence-discovery` multi-agent codebase audit (5 maps: BizLMS coupling, white-label leaks, stabilization debt, rename/architecture, engine dependency), run 2026-05-29 against the live tree.

---

## Context

The directive: *"100% stabilization of Sentientia LMS; move away from BizLMS and Moodle; Sentientia is independent."*

The discovery confirmed, against the real code, that this is **two distinct programs collapsed under one slogan** — and that conflating them is the principal risk:

1. **Sentientia independence from BizLMS / eAbyas** — stop the product depending on the BizLMS heritage (`open_path`, `open_supervisorid`, `local_costcenter`, the removed `epsilon` parent, eAbyas branding). This is **real but mostly-additive engineering**: insert abstraction seams (a `local_sentientia_core` tenant/identity layer) that read the legacy column today behind a default-ON flag, then migrate the data under them. ~6 weeks.

2. **Independence from the Moodle *engine*** — replace enrolment, course-completion, gradebook, quiz, and SCORM. These are the **source of truth in `mdl_*` tables for 408 live courses / ~400 users across 3 tenants**. They cannot be abstracted mid-flight without a dual-source-of-truth data-drift hazard. This is a **24+ month re-platform**, not a rebrand.

**Hard constraints (CLAUDE.md §13):** never break `airpay.academy` (live customer-zero); every new user-visible feature is flag-gated default-OFF; no prod DB migration / core change / >50-file op without a human gate.

## Decision

**Adopt "rebrand + abstraction" as the funded near-term goal. Treat the full engine re-platform as a SCOPED ADR + spike with a go/no-go gate — not a committed delivery.**

Execution is sequenced into **6 waves** so nothing that can orphan a capability, drop a column, or break a mid-flight enrolment ever runs without a human gate. Only **Wave 1** is additive / flag-or-string-or-CSS scoped / zero-production-risk and is being executed autonomously now; **Waves 2–6 are `needs_human` and gated on Nitin.**

### Replatform verdict

> **REBRAND + ABSTRACTION NOW; full engine re-platform SCOPED-ONLY.** Keep `airpay.academy` on the Moodle engine for 12–18 months. Build standalone services behind REST/event seams, prove them on the lowest-risk tenant (**ZEEA, id=177**) first, then the public marketplace, then internal production last — staged rollback at each step. Harvest the already-decoupled-by-design surfaces (`sentientia_live`, `sentientia_aiquiz/translate/recommendations`, the per-customer `customer_brand` resolver, the custom `airpay_catalog` routes) as the post-Moodle reference patterns. **Verified ground truth:** `local_sentientia_core` does **not yet exist**, so every "split `airpay_core → sentientia_core`" item is greenfield infra creation that triggers DB/capability/version migration → categorically `needs_human`.

---

## The Waves

| Wave | Theme | Risk | needs_human |
|------|-------|------|:-----------:|
| **1** | Safe-now stabilization + rebrand (dark-mode AA, branding/string overrides, dead-link fixes, BizLMS-decouple docs) | low | **no — executing now** |
| **2** | Tenant-identity abstraction seam (Decoupling Phase 1): `local_sentientia_core` + `tenant_identity`/`course_resolver` services behind `use_open_path_legacy` flag (default ON); refactor ~24 read callsites + theme. No column drops. | medium | yes |
| **3** | Org + hierarchy data migration (Phase 2): `local_costcenter → local_sentientia_org`, `open_supervisorid → local_sentientia_hierarchy` via guarded CLI; HRMS importer writes through services. | high | yes (prod DB) |
| **4** | Multi-customer SaaS readiness (Phase 3): replace hardcoded `VALID_TENANTS=[1,77,177]` (tenant.php) with a `tenant_registry` table + admin UI + capability; dynamic signup context. All flag-gated default-OFF. | high | yes (DB + cap) |
| **5** | Component rename `airpay_* → sentientia_*` via `class_alias` shims + staged capability migration (437+ flag refs, 150+ caps, 30+ version bumps, lang/theme cache invalidation). | high | yes (DB cap re-registration) |
| **6** | **Engine re-platform — SCOPE ONLY, do not execute.** Design standalone enrol/completion/grade/quiz services + tenant-by-tenant migration; ZEEA pilot. Keep airpay.academy on Moodle 12–18 months. | high | yes (strategic spend / 24+ mo) |

### Wave 1 — executing this session (additive, zero production risk)

1. **Dark-mode AA — anchor-button bleed.** Narrow the global `body.dark-mode a {color:#60a5fa}` (`dark_mode.scss`) with `:not(.btn):not([class*="btn"]):not([role="button"])` so anchor-buttons stop painting light-blue (~2.4:1) on brand fills platform-wide (the catalogue-only override at the seed fix generalised). CSS-only, no class renames.
2. **Dark-mode AA — badge contrast.** Catalogue category (`--ap-color-primary-light` long-token tint + brand text ~2.4:1) + difficulty chips on dark cards → readable AA via flipping semantic tokens. CSS-only.
3. **Dark-mode AA — consolidate Bootstrap `text-*` overrides** into one audited `dark_mode.scss` block.
4. **White-label — `Epsilon` leak.** `configtitle`/`pluginname` say "Epsilon" in `hi/kn/mr/sw` (and partly `en`) theme lang packs → the Sentientia value already used in `en`. Lang-string-only.
5. **White-label — OTP login.** `otploginform.mustache` renders the core `{{#str}}login, moodle{{/str}}` button → add a theme-owned `login_submit` string in all 5 packs.
6. **White-label — copyright/comment hygiene.** Residual `eAbyas` / `forked from epsilon` docblocks (~10 config/AMD/version files) → `2026 Airpay Payment Services`; strip 2 `(BizLMS)` navbar comments. Metadata only, non-user-visible.
7. **Decouple docs + lint.** `ADMIN-UI-STYLING-CONTRACT.md` (`.ap-admin-*` hooks vs BizLMS `.costcenter_data`/`.content_right`), this ADR, `BIZLMS-MIGRATION-NARRATIVE.md`, deprecation comments on the 4 `_bizlms-*.scss` partials, stylelint guard rejecting bare BizLMS selectors in NEW scss.
8. **Dead links.** 4 refs to the defunct `/local/search/coursedetails.php` (mycourses.php, onboarding.php, airpay_skills/index.php, catalog_manager comment) → `/course/view.php?id={id}`. Fixes silent 404s.
9. **Visual evidence (desktop + 590px) + PROJECT-STATE update.**

---

## Key findings behind the plan (discovery summary)

**Coupling.** `open_path` is THE tenant identifier — **HARD** coupling in 24+ files (access control, role detection, course scoping); 294 files touch it. `local_costcenter` (org hierarchy) + `open_supervisorid` (manager) are **SOFT** (already dual-targeted via `org_manager` fallback). `epsilon` theme parent already removed; `_bizlms-*.scss` selectors are cosmetic-only.

**Branding.** ~90% Sentientia-clean. Footer/navbar/login render Sentientia. Pinpoint leaks: `Epsilon` in 4 non-EN lang packs (hard — visible in admin), `eAbyas` copyright in ~10 files (soft — metadata), OTP login uses core Moodle string (hard — visible button).

**Stabilization debt.** 4 dark-mode AA items (anchor-button bleed = the systemic one; category/difficulty badges; Bootstrap `text-*` gaps; chart labels need runtime inspect) + 4 dead `coursedetails.php` links + a pre-existing high-contrast-mode correctness bug (separate P2/P3).

**Engine.** Enrolment / completion / gradebook / quiz / SCORM = **re-platform-only**. Auth / capabilities / file storage / cron / WS / events / templates = **incrementally-replaceable** behind seams. Branding + `sentientia_live` + the AI features = **already abstracted** (the POC patterns).

## Consequences

- **Positive:** the product stops conflating two programs; the safe wins (a11y + white-label) ship immediately; the risky migrations are sequenced with human gates + clone-DB rehearsal + rollback; the engine question gets an honest spike instead of a reckless rip-out.
- **Negative / accepted:** true "off Moodle" independence is a multi-quarter program — not a slogan. Near-term Sentientia *is* a white-label, BizLMS-decoupled Moodle distribution, which is the correct, fundable interim product.
- **Escalations for Nitin (go/no-go):** Wave 2 (create `local_sentientia_core`), Wave 3 (`local_costcenter` data migration), Wave 5 (component rename + capability re-registration), Wave 6 (engine re-platform spend). Each warrants its own ADR before execution.

---

## Wave 1 — overnight execution log (autonomous loop, 2026-05-29)

Self-paced loop continuing the deferred Wave-1 backlog. Each entry = one shipped,
verified, committed item. Hard rules held every iteration: additive / CSS / string /
docs only; never the Wave 2–6 human gates; never clobber the owner's concurrent WIP.

- **iter 1 — Wave 1 item 2 (catalogue badge contrast).** `local_airpay_catalog/styles.css`:
  dark-mode override repaints the category chip text → `var(--ap-blue-300)` (**5.62:1** on
  the `#172554` navy tint, was 2.42:1) and the level chip text → `#5eead4` (**6.41:1** on the
  `#134e4a` teal tint, was 1.83:1). Root cause: the `--ap-color-primary/accent-light` semantic
  tints flip to deep navy/teal in dark mode, but the brand-colour text stays dark. The raw
  `--ap-blue/teal-*` scale does NOT flip, so it's a stable light source. Light mode untouched.
  Verified live (dark mode, alpha-composited WCAG scan). **Tooling note:** plugin `styles.css`
  changes need `theme_reset_all_caches()` to rebuild the theme CSS aggregate — `purge_caches`
  alone does not re-bump themerev for plugin CSS.
- **iter 2 — Wave 1 item 1 (OTP login button white-label).** `theme/airpayux/templates/core/otploginform.mustache`
  line 108: the passwordless/OTP submit button rendered the core `{{#str}}login, moodle{{/str}}` →
  now theme-owned `{{#str}}login_submit, theme_airpayux{{/str}}`, with `login_submit` added to all 5 packs
  (en "Log in" — wording unchanged; hi/kn/mr/sw idiomatic imperatives). Verified all 5 locales resolve
  (no `[[login_submit]]`). Lang+template change → `purge_caches` (string + template caches) is the right
  tool here, NOT a version bump (no upgrade run — owner WIP in flight). Removes the last hard core-string
  dependency on the login surface.
- **iter 3 — Wave 1 item 4 (Bootstrap text-* dark-mode coverage).** `theme/airpayux/scss/moodle/dark_mode.scss`:
  the dark block handled `.text-muted`/`.text-dark` but not the BS5.3 successors. Added (additive)
  `.text-secondary, .text-body-secondary, .text-body-tertiary → #9ca3b4` (7.47:1) and
  `.text-black, .text-black-50 → #e8eaed` (15.66:1). Fixes a REAL failure — `.text-black-50` rendered raw
  `rgba(0,0,0,.5)` = 1.06:1 on the dark bg (verified via synthetic node) — and future-proofs the Moodle 5.2
  cutover (BS 5.3 deprecates `.text-muted` → `.text-body-secondary`). Verified live (synthetic spans, dark
  mode). **Tooling note:** theme SCSS-partial changes (compiled at runtime via `lib.php`) need the FULL
  `admin/cli/purge_caches.php` to clear the scssphp compiled-CSS localcache — `theme_reset_all_caches()`
  bumps themerev but does NOT rebuild the compile.
- **iter 4 — Wave 1 item 2 slice (privacy:metadata white-label).** `theme/airpayux/lang/{hi,kn,mr,sw}/theme_airpayux.php`:
  the `privacy:metadata` GDPR/data-registry string still read "Epsilon theme…" in the 4 non-EN packs (en
  already clean). Swapped the brand token → "Airpay Academy UX (Sentientia)" (matching the Wave-1 pluginname),
  preserving each localised sentence. Verified all 5 locales resolve with no "Epsilon" leak (php -r get_string
  probe). User-visible in the privacy registry. (eAbyas copyright docblocks + the `deprecated.txt`
  `theme_epsilon` mapping left as-is — non-user-visible / structural, out of the safe slice.)
- **iter 5 — Wave 1 item 6 (DEPRECATION-SCHEDULE.md).** Wrote `docs/DEPRECATION-SCHEDULE.md` — a grounded
  inventory of the 13 BizLMS/eAbyas/epsilon-coupled assets (the 4 `_bizlms-*.scss` partials, the
  `costcenter_data`/`content_right` DOM coupling, the `epsilonnavbar` class, `open_path`, `local_costcenter`,
  the `VALID_TENANTS` hardcode, the `airpay_*` namespace, the deprecated.txt + behat epsilon artefacts) each
  mapped to its ADR-018 removal wave + a cosmetic-safe / needs-human / structural status, plus the 3 Wave-1
  white-label retirements already done. Chose this over `ADMIN-UI-STYLING-CONTRACT.md` because the `.ap-admin-*`
  hook layer doesn't exist yet (0 grep matches) — it's Wave 2+ work, so that contract would be speculative.
  Doc-only, no deploy.
- **iter 6 — Wave 1 item 6b (_bizlms-*.scss deprecation headers).** Prepended a standalone
  `/* DEPRECATED — ADR-018 Wave 2 (see DEPRECATION-SCHEDULE.md row N) */` banner to all 4
  `theme/airpayux/scss/moodle/partials/_bizlms-{admin,dark,modern,overrides}.scss`, pointing devs to the
  `.ap-admin-*` successor layer + "do not extend". Compile-safe (block comments); verified the theme CSS
  still rebuilds (13,283 rules at the fresh rev — no breakage). Marks the deprecation intent in-code where
  developers see it, complementing iter 5's schedule doc.
- **iter 7 — Wave 1 item 6 (BIZLMS-MIGRATION-NARRATIVE.md).** Wrote `docs/BIZLMS-MIGRATION-NARRATIVE.md` —
  the prose companion to this ADR (decision record) + DEPRECATION-SCHEDULE.md (asset table): fork lineage
  (Moodle → eAbyas BizLMS → Airpay fork → Sentientia), the 7 coupling surfaces with HARD/SOFT/re-platform
  hardness, the seam-not-rewrite strategy, and a prose Wave 2-6 summary anchored on "customer-zero never
  regresses". Onboarding/handoff doc. Doc-only, no deploy. NOTE: the stylelint guard (item 6d) is deferred —
  verifying it needs `npm install` + the portable Node 22 (Node-24 incompat), too heavy for an unattended
  commit; dark-mode AA hunting is also blocked this session (chrome-devtools MCP disconnected on resume). The
  safe + cleanly-verifiable backlog is thinning toward the stop condition.
- **iter 8 — Wave 1 item 6 (eAbyas copyright hygiene, GPL-safe).** Appended `2026 Airpay Payment Services
  (Sentientia white-label fork)` to the `@copyright 2018 eAbyas…` docblock in 8 theme SOURCE files
  (config.php, settings.php, lib.php, layout/dashboard.php, amd/src/quickactions.js, templates/socialicons +
  slider.mustache, lang/en/theme_airpayux.php). KEY DECISION: ADDITIVE, not a strip — epsilon/eAbyas is GPL, so
  the original notice is PRESERVED (stripping a GPL copyright notice would violate the licence); Airpay is
  added as the fork's copyright holder, matching the version.php precedent. Left untouched: the genuinely
  eAbyas-authored `blocks/*_PATCHED.php` (their code), build artifacts (regenerated), and docs (correct
  references). Comment-only — php -l clean, no deploy. Last clean autonomous-safe item; remaining backlog is
  blocked-on-browser (AA), needs-build (Chart.js/stylelint), or human-gated (Waves 2-6) → loop stops here.
- **iter 9 — Wave 1 item 6d (stylelint BizLMS-selector guard).** (User opted to continue; took the
  self-contained option.) Added a `selector-disallowed-list` rule to `theme/airpayux/.stylelintrc.json`
  banning `.costcenter_data` / `.content_right` in NEW scss, with the two legacy users (dark_mode.scss,
  custom_media.scss) + `_bizlms-*.scss` grandfathered via an exemption override. Verified with stylelint 15
  (npm-installed, runs fine on Node 24): legacy dark_mode.scss passes (exit 0, exempted); a
  `.costcenter_data{}` selector is caught (exit 2, custom message). Mechanizes DEPRECATION-SCHEDULE row 5's
  "don't couple new SCSS to BizLMS DOM" rule. NOTE: CI doesn't run stylelint, so this is a dev/local gate
  (zero CI risk); wiring it into CI is a future option.
- **post-loop (2026-05-30, user-directed) — stylelint guard → CI + Wave 2 STARTED.** (a) Wired the
  BizLMS-selector guard into CI as a blocking `stylelint-guard` job via a guard-only
  `.stylelintrc.ci.json` (verified green; the full motion rule stays local — 35 pre-existing
  `_surface-*.scss` motion violations surfaced, tracked separately) — commit `e1b9c86f0`. (b) **Wave 2
  STARTED** (ADR-019): created `local_sentientia_core` + the `tenant_identity` seam (default-ON legacy flag
  delegating to `local_airpay_core\tenant`; additive, no DB, no callers migrated yet) — the foundation for
  retiring the `open_path` hard coupling. The ~24 caller migrations + the tenant registry stay staged /
  human-gated. Chrome-devtools MCP remains disconnected this session, so dark-mode AA hunting is still blocked.
- **Wave 3 DESIGNED (2026-05-30) — ADR-020 (Proposed, gated).** `local_costcenter` → `local_sentientia_org`
  org-hierarchy migration design: seam-first (default-ON `org_legacy` flag) → new additive schema →
  dual-write → backfill CLI → dual-read parity gate → ZEEA-first per-tenant cutover (Airpay last) → instant
  flag rollback, every step rehearsed on a production-DB clone first. **Design only — no schema/data touched;
  execution awaits Nitin's go/no-go (DB gate).** Separately: the **motion-rule remediation** (35
  `_surface-*.scss` inline-timing → `--ap-transition-*` token conversions, which would let the
  `declaration-property-value-disallowed-list` rule graduate to a CI gate) is **DEFERRED pending browser
  reconnect** — the conversions are mostly exact (`0.15s`→quick) but several need a judgement mapping +
  visual confirmation of the animations, which the disconnected chrome-devtools MCP blocks.
- **Wave 3.1 SHIPPED (2026-05-30) — org seam.** Added `local_sentientia_core\org::manager_id_of()` /
  `manager_id_for_current_user()` behind a default-ON `org_legacy` flag (reads `open_supervisorid`; OFF safely
  falls back to legacy until the Wave-3.2 schema). Additive, no DB, no callers migrated; 7 PHPUnit cases
  (property-based → vanilla-Moodle-safe, CI-discovered). Bumped `local_sentientia_core` 2026053000→2026053001.
  The schema / dual-write / per-tenant migration (Wave 3.2+) remain gated on the 5 ADR-020 questions +
  clone-DB rehearsal + Nitin's explicit go.

### Progress log — 2026-06-04
Shipped `local_sentientia_core/cli/bootstrap_substrate.php`: a first-party,
idempotent ensurer for the BizLMS-compatible `open_*` columns. This closes the
last from-scratch install gap — Sentientia now stands up end-to-end from this
repo alone (install -> bootstrap_substrate -> seed), with the eAbyas substrate
no longer required to boot. The deeper migration onto first-party
tenant/org tables (retiring `open_*`) stays the flag-gated, human-gated endgame.
See `docs/INSTALL-SENTIENTIA.md` + commit 3354bd947.
